/**
 * Quiz Editor - Main Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { debugError } from '../../utils/debug';
import {
	Layout,
	Form,
	Button,
	message,
	Spin,
	Space,
	Typography,
	Alert,
	Divider,
	Tabs,
} from 'antd';
import {
	SaveOutlined,
	CloseOutlined,
	QuestionCircleOutlined,
} from '@ant-design/icons';

import SettingsPanel from './SettingsPanel';
import { buildTemplateSettings } from '../templates/templateFields';
import PremiumSettingsPanel, { getPremiumCards } from './PremiumSettingsPanel';
import QuestionsPanel from './QuestionsPanel';
import RulesPanel from './RulesPanel';
import FeedbackPanel from './FeedbackPanel';
import { EditorProvider, getSiteFlags } from '../../editors/EditorContext';
import { emitEditorEvent, emitSaved, toId } from '../../editors/events';
import {
	useSlotRegistry,
	useSlotHost,
	useSlotEnvironment,
	getVisibleSlots,
	hasSlotKey,
	SlotOutlet,
} from '../../editors/slots';
import { useDirtyTracker } from '../../editors/dirty';
import RulesSummary from './RulesSummary';

const { Content } = Layout;
const { Title, Paragraph } = Typography;

/**
 * Multiple-answer scoring value for the form.
 *
 * The form holds null while the quiz uses the site default, so a save never
 * pins the quiz to today's default. The default itself can't be picked as a
 * fixed mode (choosing it means "use the site default"), so a stored value
 * equal to it is read as null too.
 *
 * @param {string|null} value    Stored or template value.
 * @param {Object}      quizData Editor boot data (default_ma_scoring).
 * @return {string|null} A mode other than the site default, or null.
 */
const toFormMaScoring = (value, quizData) => {
	const siteDefault = quizData.default_ma_scoring || 'right_minus_wrong';
	return value && value !== siteDefault ? value : null;
};

/**
 * Main Quiz Editor Component
 *
 * @param {Object}   props               Component props
 * @param {Object}   props.data          Editor data (editor-data service payload)
 * @param {string}   props.surface       Where the editor runs: 'admin' or 'dashboard'
 * @param {Object}   props.nav           Navigation adapter (list, edit, afterCreate, editUrl, back)
 * @param {Object}   props.slots         Host slot definitions by slot id, added to the registered ones
 * @param {Function} props.onSaved       Called after a successful save, before navigating
 * @param {Object}   props.initial       Preset values for a new quiz: templateId (a preset key or saved template ID)
 * @param {Function} props.onDirtyChange Called with true or false when unsaved changes appear or go
 */
const QuizEditor = ({ data = {}, surface = 'admin', nav, slots = {}, onSaved, initial = {}, onDirtyChange }) => {
	const quizData = data;
	const [form] = Form.useForm();
	const [loading, setLoading] = useState(false);
	const [saving, setSaving] = useState(false);
	const [generationMode, setGenerationMode] = useState(quizData.generation_mode || 'fixed');
	const [activeTab, setActiveTab] = useState('settings');
	const [currentQuizId, setCurrentQuizId] = useState(quizData.id || null);
	const [feedbackBands, setFeedbackBands] = useState([]);
	// Inline warnings returned by the REST save (e.g., random distractor cap edge cases).
	const [maxAnswersWarnings, setMaxAnswersWarnings] = useState([]);
	// Name of the default template that pre-filled a fresh quiz (FR-006 badge).
	const [defaultsFromName, setDefaultsFromName] = useState('');

	const isNew = !currentQuizId;

	// Unsaved changes, for the host's navigation guard.
	const dirty = useDirtyTracker(onDirtyChange);

	// Addon slots (quiz.tab, quiz.premium.card).
	useSlotRegistry();
	const slotHost = useSlotHost(form);
	const slotEnv = useSlotEnvironment(surface);
	const site = getSiteFlags(quizData);
	const slotContext = {
		surface,
		site,
		hostSlots: slots,
		entityId: toId(currentQuizId) || 0,
		generationMode,
	};
	const tabSlots = getVisibleSlots('quiz.tab', slotContext);
	const cardSlots = getVisibleSlots('quiz.premium.card', slotContext);
	const slotProps = {
		entityId: toId(currentQuizId) || 0,
		surface,
		nav,
		getValues: () => form.getFieldsValue(true),
		setFieldValue: (name, value) => {
			slotHost.setFieldValue(name, value);
			dirty.markDirty();
		},
		registerValidator: slotHost.registerValidator,
		registerAfterSave: slotHost.registerAfterSave,
		getPopupContainer: slotEnv.getPopupContainer,
		theme: slotEnv.theme,
	};

	// Initialise the addon validator registry.
	// Addons (e.g. Enterprise branching) can push async validator functions
	// into this array.  Each validator receives { quizId, values } and must
	// return { valid: bool, errors: string[] }.
	useEffect(() => {
		if (!window.ppqQuizEditorValidators) {
			window.ppqQuizEditorValidators = [];
		}
	}, []);

	// Initialize form with quiz data
	useEffect(() => {
		if (quizData.id) {
			const fieldValues = {
				title: quizData.title || '',
				description: quizData.description || '',
				featured_image_id: quizData.featured_image_id || null,
				status: quizData.status || 'published',
				mode: quizData.mode || 'tutorial',
				time_limit_seconds: quizData.time_limit_seconds || null,
				pass_percent: quizData.pass_percent || 70,
				allow_skip: quizData.allow_skip ?? true,
				allow_backward: quizData.allow_backward ?? true,
				allow_resume: quizData.allow_resume ?? true,
				randomize_questions: quizData.randomize_questions ?? false,
				randomize_answers: quizData.randomize_answers ?? false,
				page_mode: quizData.page_mode || 'single',
				questions_per_page: quizData.questions_per_page || 1,
				show_answers: quizData.show_answers || 'after_submit',
				is_practice: quizData.is_practice ?? false,
				exposure_control: quizData.exposure_control ?? false,
				enable_confidence: quizData.enable_confidence ?? false,
				show_points: quizData.show_points ?? false,
				theme: quizData.theme || 'default',
				display_density: quizData.display_density || 'default',
				pool_enabled: quizData.pool_enabled ?? false,
				max_questions: quizData.max_questions || null,
				max_attempts: quizData.max_attempts || null,
				attempt_delay_minutes: quizData.attempt_delay_minutes || 0,
				generation_mode: quizData.generation_mode || 'fixed',
				access_mode: quizData.access_mode || 'default',
				login_message: quizData.login_message || '',
				ma_scoring_mode: toFormMaScoring(quizData.ma_scoring_mode, quizData),
				display_settings: quizData.display_settings && typeof quizData.display_settings === 'object' && ! Array.isArray(quizData.display_settings)
					? { ...quizData.display_settings }
					: {},
				max_answers_per_question: quizData.max_answers_per_question || null,
			};

			// Add pre_test_id if Educator addon is active.
			if (quizData.educatorActive) {
				fieldValues.pre_test_id = quizData.pre_test_id || null;
			}

			// Add spaced repetition field if School addon is active.
			if (quizData.schoolActive) {
				fieldValues.enable_sr = quizData.enable_sr ?? false;
				fieldValues.use_measured_difficulty = quizData.use_measured_difficulty ?? false;
			}

			// Add proctoring fields if Enterprise addon is active.
			if (quizData.enterpriseActive) {
				fieldValues.proctoring_mode = quizData.proctoring_mode || 'default';
				fieldValues.proctoring_tab_monitoring = quizData.proctoring_tab_monitoring || 'default';
				fieldValues.proctoring_fullscreen = quizData.proctoring_fullscreen || 'default';
				fieldValues.proctoring_require_desktop = quizData.proctoring_require_desktop ?? false;
				fieldValues.proctoring_integrity = quizData.proctoring_integrity || 'default';
			}

			// Add WP Fusion per-quiz tag fields if the WP Fusion feature is active.
			if (quizData.wpfActive) {
				const wpfTags = quizData.wpf_tags || {};
				fieldValues.wpf_tag_signup = wpfTags.signup || [];
				fieldValues.wpf_tag_completion = wpfTags.completion || [];
				fieldValues.wpf_tag_pass = wpfTags.pass || [];
				fieldValues.wpf_tag_fail = wpfTags.fail || [];
			}

			form.setFieldsValue(fieldValues);

			if (quizData.generation_mode) {
				setGenerationMode(quizData.generation_mode);
			}

			// Load feedback bands
			if (quizData.band_feedback_json) {
				try {
					const bands = typeof quizData.band_feedback_json === 'string'
						? JSON.parse(quizData.band_feedback_json)
						: quizData.band_feedback_json;
					setFeedbackBands(bands);
				} catch (error) {
					// Invalid JSON - use empty bands
				}
			}
		}
	}, [quizData, form]);

	/**
	 * Run all registered addon validators.
	 *
	 * Returns an array of error strings. An empty array means all validators
	 * passed.  Each validator function receives { quizId, values } and must
	 * return { valid: bool, errors: string[] }.
	 *
	 * @param {number|null} quizId Current quiz ID.
	 * @param {Object}      values Form values.
	 * @return {Promise<string[]>} Collected error messages.
	 */
	const runAddonValidators = async (quizId, values) => {
		const validators = window.ppqQuizEditorValidators || [];
		if (!validators.length) {
			return [];
		}

		const allErrors = [];

		for (const validator of validators) {
			try {
				const result = await validator({ quizId, values });
				if (result && !result.valid && Array.isArray(result.errors)) {
					allErrors.push(...result.errors);
				}
			} catch (err) {
				debugError('Addon validator error:', err);
			}
		}

		return allErrors;
	};

	/**
	 * Handle form submission
	 */
	const handleSubmit = async (values) => {
		try {
			setSaving(true);

			// Use currentQuizId (which may have been set by auto-save) or fall back to quizData.id
			const quizId = currentQuizId || quizData.id;

			// Run addon validators before saving: the legacy
			// ppqQuizEditorValidators for a saved quiz (as before), and the
			// slots' validators on every save.
			const addonErrors = [
				...(quizId ? await runAddonValidators(quizId, values) : []),
				...(await slotHost.runValidators({ entityId: toId(quizId) || 0, quizId: toId(quizId) || 0, values })),
			];
			if (addonErrors.length > 0) {
				addonErrors.forEach((err) => message.error(err));
				setSaving(false);
				return;
			}

			// Prepare data for submission. Values slots or templates set are
			// sent too, even when their fields aren't on screen (only when
			// they set them, so a partial update stays partial).
			const quizPayload = {
				...slotHost.setValues(),
				...values,
				id: quizId || null,
				band_feedback_json: JSON.stringify(feedbackBands),
			};

			// Submit via REST API
			const endpoint = quizId
				? `/ppq/v1/quizzes/${quizId}`
				: '/ppq/v1/quizzes';

			const method = quizId ? 'PUT' : 'POST';

			const response = await apiFetch({
				path: endpoint,
				method: method,
				data: quizPayload,
			});

			message.success(__('Quiz saved successfully!', 'pressprimer-quiz'));

			// Capture any informational warnings returned by the save
			// (e.g., random distractor cap edge cases) so the SettingsPanel
			// can render them inline.
			setMaxAnswersWarnings(Array.isArray(response.warnings) ? response.warnings : []);

			dirty.markClean();
			emitSaved('ppq-quiz-saved', {
				quizId: toId(response.id || quizId),
				isNew: !quizId,
				status: values.status,
				generationMode: values.generation_mode || generationMode,
				surface,
			}, onSaved);
			await slotHost.runAfterSave({ entityId: toId(response.id || quizId), isNew: !quizId, values: quizPayload, response });

			// After the first save the editor stays open on the new quiz: the
			// host swaps the address in place and must not remount.
			if (!quizId && response.id) {
				nav.edit('quiz', response.id, { replace: true });
				setCurrentQuizId(response.id);
			}

		} catch (error) {
			debugError('Failed to save quiz:', error);
			message.error(error.message || __('Failed to save quiz.', 'pressprimer-quiz'));
		} finally {
			setSaving(false);
		}
	};

	/**
	 * Fill the editor form from a template's settings (client-side only).
	 *
	 * Nothing is persisted until the author saves the quiz. Booleans are
	 * converted to the Switch's boolean form value; display_settings and feedback
	 * bands are replaced (not merged); keys with no editable field are skipped.
	 * No user-facing messages — used by both Apply and the default-template
	 * pre-fill.
	 *
	 * @param {Object} settings Sanitized settings map from the template.
	 */
	const applyTemplateToForm = (settings = {}) => {
		const booleanKeys = [
			'allow_skip', 'allow_backward', 'allow_resume',
			'randomize_questions', 'randomize_answers',
			'enable_confidence', 'show_points', 'pool_enabled', 'enable_sr',
			'is_practice', 'exposure_control', 'use_measured_difficulty',
		];

		const parseJson = (value) => {
			if (value === null || value === undefined || value === '') {
				return null;
			}
			if (typeof value === 'object') {
				return value;
			}
			try {
				return JSON.parse(value);
			} catch (error) {
				return null;
			}
		};

		const patch = {};
		let nextBands = null;
		let nextDisplaySettings;

		Object.entries(settings).forEach(([key, value]) => {
			if (key === 'theme_settings_json') {
				return; // No editor field for this key.
			}
			if (key === 'band_feedback_json') {
				const bands = parseJson(value);
				if (Array.isArray(bands)) {
					nextBands = bands;
				}
				return;
			}
			if (key === 'display_settings_json') {
				const obj = parseJson(value);
				if (obj && typeof obj === 'object' && !Array.isArray(obj)) {
					nextDisplaySettings = obj;
				}
				return;
			}
			if (key === 'enable_sr' && !quizData.schoolActive) {
				return; // School addon inactive.
			}
			if (key === 'use_measured_difficulty' && !(quizData.schoolActive && quizData.schoolMeasuredAvailable)) {
				return; // School measured-difficulty unavailable.
			}
			if (booleanKeys.includes(key)) {
				patch[key] = !! Number(value);
				return;
			}
			if (key === 'ma_scoring_mode') {
				patch[key] = toFormMaScoring(value, quizData);
				return;
			}
			patch[key] = value;
		});

		form.setFieldsValue(patch);

		// setFieldsValue deep-merges objects, so replace display_settings wholesale.
		if (nextDisplaySettings !== undefined) {
			form.setFields([{ name: 'display_settings', value: nextDisplaySettings }]);
		}

		// Send what the template set with the next save, including fields on
		// a tab the author hasn't opened (e.g. Premium Settings' spaced
		// repetition).
		slotHost.markSet([
			...Object.keys(patch),
			...(nextDisplaySettings !== undefined ? ['display_settings'] : []),
		]);

		if (Array.isArray(nextBands)) {
			setFeedbackBands(nextBands);
		}
	};

	/**
	 * Apply a template the author picked (Apply template).
	 *
	 * @param {Object} settings  Sanitized settings map from the template.
	 * @param {Array}  reminders Reminders to show as warnings.
	 * @param {Object} template  The template entry ({ id, source }).
	 */
	const applyTemplate = (settings = {}, reminders = [], template = {}) => {
		applyTemplateToForm(settings);
		dirty.markDirty();
		reminders.forEach((reminder) => message.warning(reminder));
		message.success(__('Template applied. Review the settings and save the quiz to keep them.', 'pressprimer-quiz'));

		emitEditorEvent('ppq-quiz-template-applied', {
			quizId: toId(currentQuizId),
			templateId: template.id ?? null,
			source: 'preset' === template.source ? 'preset' : 'saved',
		});
	};

	// Pre-fill a brand-new quiz once on mount: from the template the host
	// asked for (mount()'s initial.templateId), else from the site default
	// template (feature 003, FR-006). Existing quizzes load their own saved
	// values.
	useEffect(() => {
		if (quizData.id) {
			return undefined;
		}

		const applyDefault = () => {
			if (quizData.defaultTemplate && quizData.defaultTemplate.settings) {
				applyTemplateToForm(quizData.defaultTemplate.settings);
				setDefaultsFromName(quizData.defaultTemplate.name || '');
			}
		};

		const templateId = initial && initial.templateId;
		if (templateId === undefined || templateId === null || templateId === '') {
			applyDefault();
			return undefined;
		}

		// A numeric ID is a saved template; anything else is a preset key.
		const source = /^\d+$/.test(String(templateId)) ? 'template' : 'preset';
		let active = true;
		setLoading(true);
		apiFetch({ path: '/ppq/v1/quiz-templates' })
			.then((res) => {
				if (!active) {
					return;
				}
				const items = Array.isArray(res?.items) ? res.items : [];
				const template = items.find((item) => item.source === source && String(item.id) === String(templateId));
				if (template && template.settings) {
					applyTemplateToForm(template.settings);
					setDefaultsFromName(template.name || '');
				} else {
					applyDefault();
				}
			})
			.catch((error) => {
				debugError('Failed to load the quiz template:', error);
				if (active) {
					applyDefault();
				}
			})
			.finally(() => {
				if (active) {
					setLoading(false);
				}
			});

		return () => {
			active = false;
		};
		// Intentionally mount-only: pre-fill once before the author edits.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	/**
	 * Collect the editor's current settings as a template payload.
	 *
	 * Used by "Save as template"; the server sanitizes every value on save.
	 *
	 * @return {Object} Settings payload keyed by settings key.
	 */
	const collectTemplateSettings = () =>
		buildTemplateSettings(form.getFieldsValue(true), feedbackBands, quizData);

	/**
	 * Handle cancel
	 */
	const handleCancel = () => {
		if (window.confirm(__('Are you sure you want to cancel? Any unsaved changes will be lost.', 'pressprimer-quiz'))) {
			dirty.markClean();
			nav.list('quiz');
		}
	};

	/**
	 * Auto-save quiz (called when switching to Questions tab)
	 */
	const autoSaveQuiz = async () => {
		try {
			setSaving(true);

			// Validate form
			const values = await form.validateFields();

			const slotErrors = await slotHost.runValidators({ entityId: toId(currentQuizId) || 0, quizId: toId(currentQuizId) || 0, values });
			if (slotErrors.length > 0) {
				slotErrors.forEach((err) => message.error(err));
				return false;
			}

			// Prepare data for submission (with what slots or templates set).
			const quizPayload = {
				...slotHost.setValues(),
				...values,
				id: currentQuizId || null,
				band_feedback_json: JSON.stringify(feedbackBands),
			};

			// Submit via REST API
			const endpoint = currentQuizId
				? `/ppq/v1/quizzes/${currentQuizId}`
				: '/ppq/v1/quizzes';

			const method = currentQuizId ? 'PUT' : 'POST';

			const response = await apiFetch({
				path: endpoint,
				method: method,
				data: quizPayload,
			});

			dirty.markClean();
			emitSaved('ppq-quiz-saved', {
				quizId: toId(response.id || currentQuizId),
				isNew: !currentQuizId,
				status: values.status,
				generationMode: values.generation_mode || generationMode,
				surface,
			}, onSaved);
			await slotHost.runAfterSave({ entityId: toId(response.id || currentQuizId), isNew: !currentQuizId, values: quizPayload, response });

			// Update current quiz ID if this was a new quiz (first save: the
			// address is swapped in place, no remount).
			if (!currentQuizId && response.id) {
				setCurrentQuizId(response.id);
				nav.edit('quiz', response.id, { replace: true });
			}

			// Capture inline save warnings (e.g., random distractor cap edge cases).
			setMaxAnswersWarnings(Array.isArray(response.warnings) ? response.warnings : []);

			message.success(__('Quiz saved successfully!', 'pressprimer-quiz'));
			return true;

		} catch (error) {
			if (error.errorFields) {
				message.error(__('Please fill in all required fields in Settings.', 'pressprimer-quiz'));
			} else {
				debugError('Auto-save failed:', error);
				message.error(error.message || __('Failed to save quiz.', 'pressprimer-quiz'));
			}
			return false;
		} finally {
			setSaving(false);
		}
	};

	/**
	 * Handle tab change
	 */
	const handleTabChange = async (newTab) => {
		// If switching to Questions tab and quiz is not saved yet, auto-save first
		if (newTab === 'questions' && !currentQuizId) {
			const saved = await autoSaveQuiz();
			if (saved) {
				setActiveTab(newTab);
			}
		} else {
			// Allow switching freely between Settings and Feedback
			setActiveTab(newTab);
		}

		// Notify addon components when their tab becomes active.
		if (newTab === 'branching') {
			window.dispatchEvent(
				new CustomEvent('ppq-quiz-editor-tab-active', { detail: { tab: 'branching' } })
			);
		}
	};

	const hasPremiumAddons = quizData.educatorActive || quizData.enterpriseActive || quizData.schoolActive;

	// Which of the Premium tab's built-in cards show here (per-card flags,
	// and an addon's own slot replacing its built-in card).
	const premiumCards = getPremiumCards(quizData, surface, {
		educatorPrepostSlot: hasSlotKey('quiz.premium.card', 'educator-prepost', surface, slots),
		enterpriseProctoringSlot: hasSlotKey('quiz.premium.card', 'enterprise-proctoring', surface, slots),
	});

	// wp-admin: always surface the Premium Settings tab so free-site admins can
	// discover what premium quiz options exist. On a free site it is
	// admin-only: a teacher can neither upgrade nor use these options, so they
	// never see the upsell. When any add-on is active the tab shows the real
	// settings to everyone who can edit the quiz.
	// Dashboard: only when it has cards to show; never the upsell (R14).
	const showPremiumTab = 'dashboard' === surface
		? cardSlots.length > 0 || Object.values(premiumCards).some(Boolean)
		: hasPremiumAddons || !!quizData.isAdmin || cardSlots.length > 0;

	// Branching tab is available only for fixed-mode quizzes when Enterprise
	// addon provides branching, and only in wp-admin (its legacy mount).
	const showBranchingTab = 'admin' === surface && quizData.branchingActive && generationMode === 'fixed' && currentQuizId;

	// A dynamic quiz on the dashboard shows its rules read-only.
	let questionsPane;
	if (generationMode === 'fixed') {
		questionsPane = <QuestionsPanel quizId={currentQuizId} generationMode={generationMode} />;
	} else if ('dashboard' === surface) {
		questionsPane = <RulesSummary quizId={currentQuizId} ruleScope={quizData.ruleScope} />;
	} else {
		questionsPane = <RulesPanel quizId={currentQuizId} generationMode={generationMode} ruleScope={quizData.ruleScope} />;
	}

	const tabItems = [
		{
			key: 'settings',
			label: __('Settings', 'pressprimer-quiz'),
			children: <SettingsPanel form={form} generationMode={generationMode} setGenerationMode={setGenerationMode} quizData={quizData} maxAnswersWarnings={maxAnswersWarnings} saving={saving} applyTemplate={applyTemplate} collectTemplateSettings={collectTemplateSettings} defaultsFromName={defaultsFromName} />,
		},
		showPremiumTab && {
			key: 'premium',
			label: __('Premium Settings', 'pressprimer-quiz'),
			children: (
				<PremiumSettingsPanel
					form={form}
					quizData={quizData}
					generationMode={generationMode}
					surface={surface}
					cards={premiumCards}
					cardSlots={cardSlots}
					slotProps={slotProps}
				/>
			),
		},
		{
			key: 'questions',
			label: generationMode === 'fixed' ? __('Questions', 'pressprimer-quiz') : __('Rules', 'pressprimer-quiz'),
			children: questionsPane,
		},
		showBranchingTab && {
			key: 'branching',
			label: __('Branching', 'pressprimer-quiz'),
			children: <div id="ppqen-branching-root" data-quiz-id={currentQuizId} />,
		},
		{
			key: 'feedback',
			label: __('Feedback', 'pressprimer-quiz'),
			children: (
				<FeedbackPanel
					value={feedbackBands}
					onChange={(bands) => {
						setFeedbackBands(bands);
						dirty.markDirty();
					}}
				/>
			),
		},
		// quiz.tab slots mount with the editor (not when first opened), so
		// their validators and after-save hooks are always registered.
		...tabSlots.map((def) => ({
			key: `slot:${def.key}`,
			label: def.label || def.key,
			forceRender: true,
			children: <SlotOutlet def={def} slotProps={slotProps} />,
		})),
	].filter(Boolean);

	// A tab that stops showing (e.g. a slot whose isVisible turns false)
	// falls back to Settings.
	const tabKeys = tabItems.map((item) => item.key).join('|');
	useEffect(() => {
		if (!tabKeys.split('|').includes(activeTab)) {
			setActiveTab('settings');
		}
	}, [tabKeys, activeTab]);

	return (
		<EditorProvider value={{ surface, nav, site, slots }}>
		<div className="ppq-quiz-editor-container" ref={slotEnv.rootRef}>
			<Spin spinning={loading || saving} tip={saving ? __('Saving quiz...', 'pressprimer-quiz') : __('Loading quiz...', 'pressprimer-quiz')}>
				<Form
					form={form}
					layout="vertical"
					onFinish={handleSubmit}
					onValuesChange={dirty.markDirty}
					initialValues={{
						title: '',
						description: '',
						status: 'published',
						mode: quizData.mode || 'tutorial',
						pass_percent: quizData.pass_percent || 70,
						allow_skip: true,
						allow_backward: true,
						allow_resume: true,
						randomize_questions: false,
						randomize_answers: false,
						page_mode: 'single',
						questions_per_page: 1,
						show_answers: 'after_submit',
						enable_confidence: false,
						show_points: false,
						theme: 'default',
						display_density: 'default',
						pool_enabled: false,
						max_questions: null,
						generation_mode: 'fixed',
						attempt_delay_minutes: 0,
						access_mode: 'default',
						login_message: '',
						ma_scoring_mode: toFormMaScoring(quizData.ma_scoring_mode, quizData),
						display_settings: quizData.display_settings && typeof quizData.display_settings === 'object' && ! Array.isArray(quizData.display_settings)
							? { ...quizData.display_settings }
							: {},
						max_answers_per_question: quizData.max_answers_per_question || null,
					}}
				>
					{/* Header */}
					<div className="ppq-editor-header">
						<Space direction="vertical" style={{ width: '100%' }}>
							<div className="ppq-editor-header-row" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
								<Title level={2} style={{ margin: 0 }}>
									{isNew
										? __('Create New Quiz', 'pressprimer-quiz')
										: __('Edit Quiz', 'pressprimer-quiz')
									}
								</Title>
								<Space>
									<Button
										icon={<CloseOutlined />}
										onClick={handleCancel}
									>
										{__('Cancel', 'pressprimer-quiz')}
									</Button>
									<Button
										type="primary"
										icon={<SaveOutlined />}
										htmlType="submit"
										loading={saving}
										size="large"
									>
										{__('Save Quiz', 'pressprimer-quiz')}
									</Button>
								</Space>
							</div>

							<Alert
								message={__('Quiz Editor Guide', 'pressprimer-quiz')}
								description={
									<>
										<Paragraph style={{ marginBottom: 'admin' === surface ? 8 : 0 }}>
											{__('Create engaging quizzes for your students. Configure settings, add questions or rules, and customize the experience.', 'pressprimer-quiz')}
										</Paragraph>
										{/* Dynamic mode is set in wp-admin only. */}
										{'admin' === surface && (
											<Paragraph style={{ marginBottom: 0 }}>
												<strong>{__('Pro Tip:', 'pressprimer-quiz')}</strong> {__('Use Fixed mode to select specific questions (same for everyone), or Dynamic mode with rules to randomly select from question banks (different for each student).', 'pressprimer-quiz')}
											</Paragraph>
										)}
									</>
								}
								type="info"
								icon={<QuestionCircleOutlined />}
								showIcon
								closable
							/>
						</Space>
					</div>

					<Divider />

					{/* Tabbed Content */}
					<Tabs
						activeKey={activeTab}
						onChange={handleTabChange}
						items={tabItems}
						size="large"
					/>

					{/* Bottom Action Buttons */}
					<div style={{
						background: '#fff',
						padding: '20px 24px',
						borderRadius: 8,
						marginTop: 20,
						display: 'flex',
						justifyContent: 'flex-end',
						gap: 12,
						boxShadow: '0 2px 8px rgba(0, 0, 0, 0.06)'
					}}>
						<Button
							icon={<CloseOutlined />}
							onClick={handleCancel}
							size="large"
						>
							{__('Cancel', 'pressprimer-quiz')}
						</Button>
						<Button
							type="primary"
							icon={<SaveOutlined />}
							htmlType="submit"
							loading={saving}
							size="large"
						>
							{__('Save Quiz', 'pressprimer-quiz')}
						</Button>
					</div>
				</Form>
			</Spin>
		</div>
		</EditorProvider>
	);
};

export default QuizEditor;
