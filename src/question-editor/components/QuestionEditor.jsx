/**
 * Question Editor - Main Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect, useRef } from '@wordpress/element';
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
	EditOutlined,
} from '@ant-design/icons';

import QuestionTypeSelector from './QuestionTypeSelector';
import StemEditor from './StemEditor';
import AnswersList, { buildAnswersState } from './AnswersList';
import MetadataPanel from './MetadataPanel';
import TaxonomySelector from './TaxonomySelector';
import BankSelector from './BankSelector';
import FeedbackFields from './FeedbackFields';
import { EditorProvider, getSiteFlags } from '../../editors/EditorContext';
import { emitSaved, toId } from '../../editors/events';
import {
	useSlotRegistry,
	useSlotHost,
	useSlotEnvironment,
	getVisibleSlots,
	SlotList,
	SlotOutlet,
} from '../../editors/slots';

const { Content, Sider } = Layout;
const { Title, Paragraph } = Typography;

/**
 * Main Question Editor Component
 *
 * @param {Object}   props          Component props
 * @param {Object}   props.data     Editor data (editor-data service payload)
 * @param {string}   props.surface  Where the editor runs: 'admin' or 'dashboard'
 * @param {Object}   props.nav      Navigation adapter (list, edit, afterCreate, editUrl, back)
 * @param {Object}   props.slots    Host slot definitions by slot id, added to the registered ones
 * @param {Function} props.onSaved  Called after a successful save, before navigating
 */
const QuestionEditor = ({ data = {}, surface = 'admin', nav, slots = {}, onSaved }) => {
	const questionData = data;
	const [form] = Form.useForm();
	const [loading, setLoading] = useState(false);
	const [saving, setSaving] = useState(false);
	const [questionType, setQuestionType] = useState(questionData.type || 'mc');
	const [stem, setStem] = useState(questionData.stem || '');
	const [answers, setAnswers] = useState(questionData.answers || [
		{ id: 'a1', text: '', isCorrect: false, feedback: '', order: 1 },
		{ id: 'a2', text: '', isCorrect: false, feedback: '', order: 2 },
	]);
	const [activeTab, setActiveTab] = useState('editor');
	const status = Form.useWatch('status', form);

	const isNew = !questionData.id;

	// Addon tabs come with the editor data.
	const addonTabs = Array.isArray(questionData.addonTabs) ? questionData.addonTabs : [];

	// Bumped per answer when a slot replaces its feedback, to re-initialise
	// that answer's feedback editor.
	const [feedbackRevisions, setFeedbackRevisions] = useState({});

	// Addon slots (question.tab, question.answers.actions).
	useSlotRegistry();
	const slotHost = useSlotHost(form);
	const slotEnv = useSlotEnvironment(surface);
	const site = getSiteFlags(questionData);
	const entityId = toId(questionData.id) || 0;
	const slotContext = { surface, site, hostSlots: slots, entityId };
	const tabSlots = getVisibleSlots('question.tab', slotContext);
	const answerSlots = getVisibleSlots('question.answers.actions', slotContext);

	// Slots read the editor's latest state through getValues().
	const latest = useRef({});
	latest.current = { type: questionType, stem, answers };

	const slotProps = {
		entityId,
		surface,
		nav,
		getValues: () => ({ ...form.getFieldsValue(true), ...latest.current }),
		setFieldValue: slotHost.setFieldValue,
		registerValidator: slotHost.registerValidator,
		registerAfterSave: slotHost.registerAfterSave,
		getPopupContainer: slotEnv.getPopupContainer,
		theme: slotEnv.theme,
		// Same shape as the ppq-answers-changed event's detail.
		answersState: buildAnswersState({ questionId: questionData.id, questionType, answers }),
	};

	/**
	 * Replace one answer's feedback (question.answers.actions slots): updates
	 * the answers, re-initialises that answer's feedback editor and opens its
	 * feedback panel. Saved with the question, like a typed edit.
	 *
	 * @param {string} answerId Answer ID.
	 * @param {string} html     Feedback HTML.
	 * @return {boolean} False when no answer has that ID.
	 */
	const setAnswerFeedback = (answerId, html) => {
		if (!latest.current.answers.some((answer) => answer.id === answerId)) {
			return false;
		}
		setAnswers((current) => current.map((answer) => (
			answer.id === answerId ? { ...answer, feedback: String(html ?? '') } : answer
		)));
		setFeedbackRevisions((current) => ({ ...current, [answerId]: (current[answerId] || 0) + 1 }));
		return true;
	};

	const answerSlotProps = { ...slotProps, setAnswerFeedback };

	// Initialize form with question data
	useEffect(() => {
		if (questionData.id) {
			form.setFieldsValue({
				stem: questionData.stem || '',
				type: questionData.type || 'mc',
				status: questionData.status || 'published',
				difficulty: questionData.difficulty || undefined,
				timeLimit: questionData.timeLimit || 0,
				points: questionData.points || 1,
				feedbackCorrect: questionData.feedbackCorrect || '',
				feedbackIncorrect: questionData.feedbackIncorrect || '',
				categories: questionData.categories || [],
				tags: questionData.tags || [],
				banks: questionData.banks || [],
			});

			if (questionData.stem) {
				setStem(questionData.stem);
			}

			if (questionData.answers) {
				setAnswers(questionData.answers);
			}
		}
	}, [questionData, form]);

	/**
	 * Handle form submission
	 */
	const handleSubmit = async (values) => {
		try {
			setSaving(true);

			// Validate answers
			if (answers.length < 2) {
				message.error(__('Please add at least 2 answer options.', 'pressprimer-quiz'));
				return;
			}

			const hasCorrect = answers.some(a => a.isCorrect);
			if (!hasCorrect) {
				message.error(__('Please mark at least one answer as correct.', 'pressprimer-quiz'));
				return;
			}

			// For MC and TF, only one answer can be correct
			if ((questionType === 'mc' || questionType === 'tf') && answers.filter(a => a.isCorrect).length > 1) {
				message.error(__('Multiple Choice and True/False questions can only have one correct answer.', 'pressprimer-quiz'));
				return;
			}

			const slotErrors = await slotHost.runValidators({
				entityId,
				values: { ...values, type: questionType, stem, answers },
			});
			if (slotErrors.length > 0) {
				slotErrors.forEach((err) => message.error(err));
				return;
			}

			// Prepare data for submission. Values slots set are sent too
			// (only when a slot set them).
			const questionPayload = {
				...slotHost.setValues(),
				...values,
				type: questionType,
				stem: stem,
				answers: answers,
				id: questionData.id || null,
			};

			// Submit via REST API
			const endpoint = questionData.id
				? `/ppq/v1/questions/${questionData.id}`
				: '/ppq/v1/questions';

			const method = questionData.id ? 'PUT' : 'POST';

			const response = await apiFetch({
				path: endpoint,
				method: method,
				data: questionPayload,
			});

			message.success(__('Question saved successfully!', 'pressprimer-quiz'));

			const savedId = response.id || questionData.id;
			emitSaved('ppq-question-saved', {
				questionId: toId(savedId),
				isNew,
				type: questionType,
				status: values.status,
				bankIds: (values.banks || []).map(toId).filter(Boolean),
				surface,
			}, onSaved);
			await slotHost.runAfterSave({ entityId: toId(savedId), isNew, values: questionPayload, response });

			// Back to the questions list.
			setTimeout(() => {
				if (isNew) {
					nav.afterCreate('question', savedId);
				} else {
					nav.list('question', { saved: true });
				}
			}, 1000);

		} catch (error) {
			debugError('Failed to save question:', error);
			message.error(error.message || __('Failed to save question.', 'pressprimer-quiz'));
		} finally {
			setSaving(false);
		}
	};

	/**
	 * Publish a draft: set the status and save the question.
	 */
	const handlePublish = () => {
		form.setFieldsValue({ status: 'published' });
		form.submit();
	};

	/**
	 * Handle cancel
	 */
	const handleCancel = () => {
		if (window.confirm(__('Are you sure you want to cancel? Any unsaved changes will be lost.', 'pressprimer-quiz'))) {
			nav.list('question');
		}
	};

	/**
	 * Handle tab change
	 *
	 * When switching to an addon tab, dispatch an event so the addon
	 * can mount its React component into the tab panel.
	 *
	 * @param {string} key - The tab key.
	 */
	const handleTabChange = (key) => {
		setActiveTab(key);

		// If switching to a legacy addon tab, dispatch event for the addon to
		// handle (slot tabs are already mounted).
		if (key !== 'editor' && !key.startsWith('slot:')) {
			document.dispatchEvent(
				new CustomEvent('ppq-question-editor-tab-active', {
					detail: {
						tabKey: key,
						questionId: questionData.id || 0,
					},
				})
			);
		}
	};

	/**
	 * Handle question type change
	 */
	const handleTypeChange = (type) => {
		setQuestionType(type);

		// If switching to TF, adjust answers
		if (type === 'tf' && answers.length > 2) {
			setAnswers(answers.slice(0, 2));
		}

		// Reset correct answers to single selection for MC/TF
		if (type === 'mc' || type === 'tf') {
			const firstCorrect = answers.findIndex(a => a.isCorrect);
			if (firstCorrect !== -1) {
				setAnswers(answers.map((a, idx) => ({
					...a,
					isCorrect: idx === firstCorrect
				})));
			}
		}
	};

	// The editor itself: one tree, shown alone or as the first tab.
	const editorLayout = (
		<Layout style={{ background: 'transparent' }}>
			<Content style={{ paddingRight: 24 }}>
				{/* Question Type Selector */}
				<QuestionTypeSelector
					value={questionType}
					onChange={handleTypeChange}
				/>

				{/* Question Stem */}
				<StemEditor
					value={stem}
					onChange={(value) => {
						setStem(value);
						form.setFieldsValue({ stem: value });
					}}
				/>

				{/* Answers */}
				<AnswersList
					answers={answers}
					questionType={questionType}
					questionId={questionData.id || 0}
					onChange={setAnswers}
					feedbackRevisions={feedbackRevisions}
					addonActions={<SlotList defs={answerSlots} slotProps={answerSlotProps} />}
				/>

				{/* General Feedback */}
				<FeedbackFields form={form} />
			</Content>

			{/* Sidebar */}
			<Sider
				width={320}
				style={{
					background: '#fff',
					padding: '24px 24px 12px 24px',
					borderLeft: '1px solid #f0f0f0',
				}}
			>
				<Space direction="vertical" style={{ width: '100%' }} size="middle">
					{/* Metadata */}
					<MetadataPanel form={form} />

					<Divider style={{ margin: '4px 0' }} />

					{/* Categories */}
					<Form.Item name="categories" noStyle shouldUpdate style={{ marginBottom: 0 }}>
						<TaxonomySelector
							type="category"
						/>
					</Form.Item>

					<Divider style={{ margin: '4px 0' }} />

					{/* Tags */}
					<Form.Item name="tags" noStyle shouldUpdate style={{ marginBottom: 0 }}>
						<TaxonomySelector
							type="tag"
						/>
					</Form.Item>

					<Divider style={{ margin: '4px 0' }} />

					{/* Question Banks */}
					<Form.Item name="banks" noStyle shouldUpdate style={{ marginBottom: 0 }}>
						<BankSelector memberships={questionData.bank_memberships || []} />
					</Form.Item>

					<Divider style={{ margin: '16px 0' }} />

					{/* Save/Cancel Buttons */}
					<Space direction="vertical" style={{ width: '100%' }} size="small">
						<Button
							type="primary"
							icon={<SaveOutlined />}
							htmlType="submit"
							loading={saving}
							size="large"
							block
						>
							{__('Save Question', 'pressprimer-quiz')}
						</Button>
						<Button
							icon={<CloseOutlined />}
							onClick={handleCancel}
							size="large"
							block
						>
							{__('Cancel', 'pressprimer-quiz')}
						</Button>
					</Space>
				</Space>
			</Sider>
		</Layout>
	);

	const tabItems = [
		{
			key: 'editor',
			label: (
				<span>
					<EditOutlined />
					{__('Editor', 'pressprimer-quiz')}
				</span>
			),
			children: editorLayout,
		},
		// Legacy addon tabs (#ppq-question-editor-addon-{key}): wp-admin only,
		// for saved questions, as before.
		...('admin' === surface && !isNew ? addonTabs : []).map((tab) => ({
			key: tab.key,
			label: tab.label,
			children: (
				<div
					id={`ppq-question-editor-addon-${tab.key}`}
					style={{ minHeight: 300, padding: '16px 0' }}
				/>
			),
		})),
		// question.tab slots mount with the editor, so their validators and
		// after-save hooks are always registered.
		...tabSlots.map((def) => ({
			key: `slot:${def.key}`,
			label: def.label || def.key,
			forceRender: true,
			children: <SlotOutlet def={def} slotProps={slotProps} />,
		})),
	];

	// A tab that stops showing (e.g. a slot whose isVisible turns false)
	// falls back to the editor.
	const tabKeys = tabItems.map((item) => item.key).join('|');
	useEffect(() => {
		if (!tabKeys.split('|').includes(activeTab)) {
			setActiveTab('editor');
		}
	}, [tabKeys, activeTab]);

	return (
		<EditorProvider value={{ surface, nav, site, slots }}>
		<div className="ppq-question-editor-container" ref={slotEnv.rootRef}>
			<Spin spinning={loading} tip={__('Loading question...', 'pressprimer-quiz')}>
				<Form
					form={form}
					layout="vertical"
					onFinish={handleSubmit}
					initialValues={{
						type: 'mc',
						status: 'published',
						difficulty: undefined,
						timeLimit: 0,
						points: 1.0,
						stem: '',
						feedbackCorrect: '',
						feedbackIncorrect: '',
						categories: [],
						tags: [],
						banks: [],
					}}
				>
					{/* Header */}
					<div className="ppq-editor-header">
						<Space direction="vertical" style={{ width: '100%' }}>
							<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
								<Title level={2} style={{ margin: 0 }}>
									{isNew
										? __('Create New Question', 'pressprimer-quiz')
										: __('Edit Question', 'pressprimer-quiz')
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
										{__('Save Question', 'pressprimer-quiz')}
									</Button>
								</Space>
							</div>

							<Alert
								message={__('Question Editor Guide', 'pressprimer-quiz')}
								description={
									<>
										<Paragraph style={{ marginBottom: 8 }}>
											{__('Create engaging questions for your quizzes. All fields marked with * are required.', 'pressprimer-quiz')}
										</Paragraph>
										<Paragraph style={{ marginBottom: 0 }}>
											<strong>{__('Pro Tip:', 'pressprimer-quiz')}</strong> {__('Use the feedback fields to provide helpful explanations that guide students\' learning.', 'pressprimer-quiz')}
										</Paragraph>
									</>
								}
								type="info"
								icon={<QuestionCircleOutlined />}
								showIcon
								closable
							/>

							{!isNew && status === 'draft' && (
								<Alert
									type="info"
									showIcon
									message={__('This question is a draft. Dynamic quizzes won\'t draw it until you publish it.', 'pressprimer-quiz')}
									action={
										<Button
											type="primary"
											size="small"
											onClick={handlePublish}
											loading={saving}
										>
											{__('Publish', 'pressprimer-quiz')}
										</Button>
									}
								/>
							)}
						</Space>
					</div>

					<Divider />

					{/* Tabs only when there is more than the editor: legacy addon tabs
					    (wp-admin, saved questions) or question.tab slots. */}
					{tabItems.length > 1 ? (
						<Tabs
							activeKey={activeTab}
							onChange={handleTabChange}
							items={tabItems}
						/>
					) : editorLayout}
				</Form>
			</Spin>
		</div>
		</EditorProvider>
	);
};

export default QuestionEditor;
