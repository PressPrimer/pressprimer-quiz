/**
 * Premium Settings Panel Component
 *
 * Settings provided by premium addons (Educator, School, Enterprise), then
 * the locked options of the tiers that aren't active (admins only).
 *
 * @package PressPrimer_Quiz
 * @since 2.2.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Form,
	Select,
	Switch,
	Card,
	Space,
	Typography,
	Tooltip,
	Alert,
	Modal,
} from 'antd';
import {
	QuestionCircleOutlined,
} from '@ant-design/icons';

import PremiumUpsell from './PremiumUpsell';
import { SlotList } from '../../editors/slots';

const { Title, Text } = Typography;

/**
 * Whether a built-in card's own flag lets it show.
 *
 * An addon sets the flag in the editor data per context. Without it (an
 * addon release from before 3.2), the card shows in wp-admin as before and
 * never on the dashboard.
 *
 * @param {Object}  quizData Quiz editor data.
 * @param {string}  flag     Flag name (e.g. 'schoolSrCard').
 * @param {boolean} legacy   Whether the card showed before the flag existed.
 * @param {string}  surface  'admin' or 'dashboard'.
 * @return {boolean} True when the card may show.
 */
const cardFlag = (quizData, flag, legacy, surface) =>
	Object.prototype.hasOwnProperty.call(quizData, flag)
		? !!quizData[flag]
		: 'admin' === surface && !!legacy;

/**
 * Which built-in cards the Premium tab shows.
 *
 * - Pre/post linking: wp-admin only, and not when Educator registers its own
 *   'educator-prepost' card slot.
 * - Proctoring: the enterpriseProctoringCard flag, and not when Enterprise
 *   registers an 'enterprise-proctoring' card slot.
 * - Spaced repetition, measured difficulty, WP Fusion: School's
 *   schoolSrCard, schoolMeasuredCard and wpfCard flags.
 *
 * @param {Object} quizData Quiz editor data.
 * @param {string} surface  'admin' or 'dashboard'.
 * @param {Object} replaced { educatorPrepostSlot, enterpriseProctoringSlot }: addon slots registered for this surface.
 * @return {Object} prepost, proctoring, spacedRepetition, measuredDifficulty, wpFusion.
 */
export const getPremiumCards = (quizData = {}, surface = 'admin', replaced = {}) => ({
	prepost: 'admin' === surface && !!quizData.educatorActive && !replaced.educatorPrepostSlot,
	proctoring: cardFlag(quizData, 'enterpriseProctoringCard', quizData.enterpriseActive, surface) && !replaced.enterpriseProctoringSlot,
	spacedRepetition: cardFlag(quizData, 'schoolSrCard', quizData.schoolActive, surface),
	measuredDifficulty: cardFlag(quizData, 'schoolMeasuredCard', quizData.schoolActive && quizData.schoolMeasuredAvailable, surface),
	wpFusion: cardFlag(quizData, 'wpfCard', quizData.wpfActive, surface),
});

/**
 * Measured Difficulty card (School 3.1).
 *
 * Shown only for dynamic/pool quizzes — the toggle reinterprets existing
 * difficulty rules, so fixed quizzes have nothing for it to do. Watches the
 * form so switching generation mode shows/hides it live.
 *
 * @param {Object} props                Component props.
 * @param {Object} props.form           The quiz editor form instance.
 * @param {string} props.generationMode The editor's generation mode state.
 * @param {string} props.surface        'admin' or 'dashboard'.
 * @return {JSX.Element|null} The card, or null for fixed quizzes.
 */
const MeasuredDifficultyCard = ({ form, generationMode, surface }) => {
	const poolEnabled = Form.useWatch('pool_enabled', form);

	if (generationMode !== 'dynamic' && !poolEnabled) {
		return null;
	}

	return (
		<Card
			title={
				<Space>
					<Title level={4} style={{ margin: 0 }}>
						{__('Measured Difficulty', 'pressprimer-quiz')}
					</Title>
					<Tooltip title={__('Uses each question’s measured difficulty — how students actually perform on it — instead of the authored rating when difficulty rules pick questions.', 'pressprimer-quiz')}>
						<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
					</Tooltip>
				</Space>
			}
			style={{ marginBottom: 24 }}
		>
			<Form.Item
				label={
					<Space>
						<span>{__('Use Measured Difficulty for Rule Matching', 'pressprimer-quiz')}</span>
						<Tooltip title={__('Questions with enough attempts are matched by their measured difficulty band; questions without enough data fall back to the authored rating. Turn this on when measured data shows some questions are easier or harder than rated.', 'pressprimer-quiz')}>
							<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
						</Tooltip>
					</Space>
				}
				name="use_measured_difficulty"
				valuePropName="checked"
				style={{ marginBottom: 0 }}
			>
				<Switch size="small" />
			</Form.Item>
			{'admin' === surface && (
				<Text type="secondary" style={{ fontSize: 12, display: 'block', marginTop: 8 }}>
					{__('See the Measured column on the Questions screen for each question’s current measurement.', 'pressprimer-quiz')}
				</Text>
			)}
		</Card>
	);
};

/**
 * Premium Settings Panel Component
 *
 * @param {Object}   props                Component props
 * @param {Object}   props.form           Ant Design form instance
 * @param {Object}   props.quizData       Initial quiz data (includes addon fields)
 * @param {string}   props.generationMode The editor's generation mode
 * @param {string}   props.surface        'admin' or 'dashboard'
 * @param {Object}   props.cards          Built-in cards to show (getPremiumCards())
 * @param {Object[]} props.cardSlots      quiz.premium.card slots to render
 * @param {Object}   props.slotProps      Props for the card slots
 */
const PremiumSettingsPanel = ({ form, quizData = {}, generationMode = 'fixed', surface = 'admin', cards, cardSlots = [], slotProps = {} }) => {
	const shown = cards || getPremiumCards(quizData, surface);

	// Pre-test selector state (only used when Educator addon is active).
	const [preTestOptions, setPreTestOptions] = useState([]);
	const [preTestLoading, setPreTestLoading] = useState(false);
	const [preTestFetched, setPreTestFetched] = useState(false);

	// WP Fusion CRM tag options (only fetched when the WP Fusion feature is on).
	const [wpfTagOptions, setWpfTagOptions] = useState([]);

	useEffect(() => {
		if (!shown.wpFusion) {
			return;
		}
		apiFetch({ path: '/ppqs/v1/wpf/tags' })
			.then((result) => {
				setWpfTagOptions(
					(result.available_tags || []).map((tag) => ({
						label: tag.label,
						value: tag.id,
					}))
				);
			})
			.catch(() => {});
	}, [shown.wpFusion]);

	// Set initial pre-test option if quiz already has one linked.
	useEffect(() => {
		if (quizData.pre_test_id && quizData.pre_test_title) {
			setPreTestOptions([{
				value: quizData.pre_test_id,
				label: quizData.pre_test_title,
			}]);
		}
	}, [quizData.pre_test_id, quizData.pre_test_title]);

	/**
	 * Fetch available pre-tests from the educator REST endpoint
	 */
	const fetchPreTests = useCallback(async (search = '') => {
		if (!quizData.educatorActive) {
			return;
		}

		setPreTestLoading(true);
		try {
			const params = new URLSearchParams({
				per_page: '20',
			});

			if (quizData.id) {
				params.append('exclude', quizData.id);
			}

			if (search) {
				params.append('search', search);
			}

			const results = await apiFetch({
				path: `/ppqe/v1/quizzes/available-pretests?${params.toString()}`,
			});

			const options = results.map((quiz) => ({
				value: quiz.id,
				label: quiz.owner_name
					? `${quiz.title} (${quiz.owner_name})`
					: quiz.title,
			}));

			setPreTestOptions(options);
			setPreTestFetched(true);
		} catch {
			// Silently fail - the selector will just show no options.
		} finally {
			setPreTestLoading(false);
		}
	}, [quizData.educatorActive, quizData.id]);

	/**
	 * Handle pre-test dropdown open - load initial options
	 */
	const handlePreTestDropdownOpen = useCallback((open) => {
		if (open && !preTestFetched) {
			fetchPreTests();
		}
	}, [preTestFetched, fetchPreTests]);

	/**
	 * Handle pre-test search with debounce
	 */
	const handlePreTestSearch = useCallback((value) => {
		fetchPreTests(value);
	}, [fetchPreTests]);

	/**
	 * Handle pre-test change - confirm before unlinking
	 */
	const handlePreTestChange = useCallback((value) => {
		const currentValue = form.getFieldValue('pre_test_id');

		// If clearing (unlinking) and there was a previous value, confirm.
		if (!value && currentValue) {
			Modal.confirm({
				title: __('Unlink Pre-Test?', 'pressprimer-quiz'),
				content: __('Are you sure you want to unlink this pre-test? Existing comparison data will still be available, but new attempts will not be linked.', 'pressprimer-quiz'),
				okText: __('Unlink', 'pressprimer-quiz'),
				cancelText: __('Cancel', 'pressprimer-quiz'),
				onOk: () => {
					form.setFieldsValue({ pre_test_id: null });
				},
			});
			return;
		}

		form.setFieldsValue({ pre_test_id: value || null });
	}, [form]);

	// Locked options of the tiers that aren't active (admins only, while
	// upsells are on; the editor data empties them on the dashboard).
	const lockedOptions = 'admin' === surface && Array.isArray(quizData.quizOptions)
		? quizData.quizOptions
		: [];

	// Free site: no premium addon active. The tab is the locked options alone.
	const hasAnyAddon =
		quizData.educatorActive ||
		quizData.schoolActive ||
		quizData.enterpriseActive;

	if (!hasAnyAddon && cardSlots.length === 0) {
		return <PremiumUpsell options={lockedOptions} pricingUrl={quizData.quizOptionsPricingUrl} />;
	}

	// No wp-admin menu paths on the dashboard.
	let proctoringDescription = __('These settings override the global proctoring configuration for this quiz only. Choose "Use Global Default" to follow the site’s proctoring settings.', 'pressprimer-quiz');
	if (quizData.proctoring_global_mode === 'off') {
		proctoringDescription = __('Global proctoring is currently off. You can enable it for this quiz individually.', 'pressprimer-quiz');
	} else if ('admin' === surface) {
		proctoringDescription = __('These settings override the global proctoring configuration for this quiz only. Use "Use Global Default" to inherit from Settings > Proctoring.', 'pressprimer-quiz');
	}

	return (
		<Space direction="vertical" size="large" style={{ width: '100%' }}>
			{/* Pre/Post Test Linking - Educator addon (until Educator registers its own card) */}
			{shown.prepost && (
				<Card
					title={
						<Space>
							<Title level={4} style={{ margin: 0 }}>
								{__('Pre/Post Test Linking', 'pressprimer-quiz')}
							</Title>
							<Tooltip title={__('Link this quiz to a pre-test to track student improvement between assessments', 'pressprimer-quiz')}>
								<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
							</Tooltip>
						</Space>
					}
					style={{ marginBottom: 24 }}
				>
					<Alert
						type="info"
						showIcon
						message={__('Pre/Post Test Comparison', 'pressprimer-quiz')}
						description={__('Link a pre-test to this quiz to automatically show students their improvement on the results page. The pre-test should be taken before this quiz.', 'pressprimer-quiz')}
						style={{ marginBottom: 16 }}
					/>

					<Form.Item
						label={
							<Space>
								<span>{__('Pre-Test Quiz', 'pressprimer-quiz')}</span>
								<Tooltip title={__('Select the quiz that serves as the pre-test for this quiz. Students who complete both will see a comparison of their scores.', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
						}
						name="pre_test_id"
						style={{ marginBottom: 0 }}
					>
						<Select
							size="small"
							style={{ width: 300 }}
							placeholder={__('None (no pre-test linked)', 'pressprimer-quiz')}
							allowClear
							showSearch
							filterOption={false}
							loading={preTestLoading}
							options={preTestOptions}
							onDropdownVisibleChange={handlePreTestDropdownOpen}
							onSearch={handlePreTestSearch}
							onChange={handlePreTestChange}
							notFoundContent={preTestLoading ? __('Loading...', 'pressprimer-quiz') : __('No quizzes found', 'pressprimer-quiz')}
						/>
					</Form.Item>
					<Text type="secondary" style={{ fontSize: 13, display: 'block', marginTop: 4, marginBottom: 24 }}>
						{__('Select a quiz to use as the pre-test for this quiz', 'pressprimer-quiz')}
					</Text>
				</Card>
			)}

			{/* Proctoring Overrides - Enterprise addon */}
			{shown.proctoring && (
				<Card
					title={
						<Space>
							<Title level={4} style={{ margin: 0 }}>
								{__('Proctoring', 'pressprimer-quiz')}
							</Title>
							<Tooltip title={__('Override global proctoring settings for this quiz', 'pressprimer-quiz')}>
								<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
							</Tooltip>
						</Space>
					}
					style={{ marginBottom: 24 }}
				>
					<Alert
						type="info"
						showIcon
						message={__('Per-Quiz Proctoring Overrides', 'pressprimer-quiz')}
						description={proctoringDescription}
						style={{ marginBottom: 16 }}
					/>

					<Form.Item
						label={
							<Space>
								<span>{__('Proctoring Mode', 'pressprimer-quiz')}</span>
								<Tooltip title={__('Controls the level of proctoring for this quiz. Monitor logs incidents silently. Strict warns students of violations.', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
						}
						name="proctoring_mode"
					>
						<Select
							size="small"
							style={{ width: 300 }}
							options={[
								{ value: 'default', label: __('Use Global Default', 'pressprimer-quiz') },
								{ value: 'off', label: __('Off', 'pressprimer-quiz') },
								{ value: 'monitor', label: __('Monitor', 'pressprimer-quiz') },
								{ value: 'strict', label: __('Strict', 'pressprimer-quiz') },
							]}
						/>
					</Form.Item>

					<Form.Item
						label={
							<Space>
								<span>{__('Tab/Focus Monitoring', 'pressprimer-quiz')}</span>
								<Tooltip title={__('Detect when the student leaves the quiz tab or switches to another application.', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
						}
						name="proctoring_tab_monitoring"
					>
						<Select
							size="small"
							style={{ width: 300 }}
							options={[
								{ value: 'default', label: __('Use Global Default', 'pressprimer-quiz') },
								{ value: 'on', label: __('Enabled', 'pressprimer-quiz') },
								{ value: 'off', label: __('Disabled', 'pressprimer-quiz') },
							]}
						/>
					</Form.Item>

					<Form.Item
						label={
							<Space>
								<span>{__('Full-Screen Mode', 'pressprimer-quiz')}</span>
								<Tooltip title={__('Require the quiz to be taken in full-screen mode. Exiting full-screen is logged as a proctoring incident.', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
						}
						name="proctoring_fullscreen"
					>
						<Select
							size="small"
							style={{ width: 300 }}
							options={[
								{ value: 'default', label: __('Use Global Default', 'pressprimer-quiz') },
								{ value: 'on', label: __('Enabled', 'pressprimer-quiz') },
								{ value: 'off', label: __('Disabled', 'pressprimer-quiz') },
							]}
						/>
					</Form.Item>

					<Form.Item
						label={
							<Space>
								<span>{__('Integrity Analysis', 'pressprimer-quiz')}</span>
								<Tooltip title={__('Analyze the completed attempts on this quiz for statistically unusual patterns (timing, answer similarity, shared devices). Separate from real-time proctoring; flags only direct reviewer attention and never change scores.', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
						}
						name="proctoring_integrity"
					>
						<Select
							size="small"
							style={{ width: 300 }}
							options={[
								{ value: 'default', label: __('Use Global Default', 'pressprimer-quiz') },
								{ value: 'on', label: __('Enabled', 'pressprimer-quiz') },
								{ value: 'off', label: __('Disabled', 'pressprimer-quiz') },
							]}
						/>
					</Form.Item>

					<Form.Item
						name="proctoring_require_desktop"
						valuePropName="checked"
						style={{ marginBottom: 0 }}
					>
						<Space>
							<Switch size="small" />
							<span>{__('Require Desktop Browser', 'pressprimer-quiz')}</span>
							<Tooltip title={__('Block mobile and tablet devices from taking this quiz. Detection is based on user agent strings and may not be 100% accurate.', 'pressprimer-quiz')}>
								<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
							</Tooltip>
						</Space>
					</Form.Item>
					<Text type="secondary" style={{ fontSize: 13, display: 'block', marginTop: 4, marginBottom: 24 }}>
						{__('Mobile devices have limited proctoring support. Enable this to restrict quizzes to desktop browsers only.', 'pressprimer-quiz')}
					</Text>
				</Card>
			)}

			{/* Spaced Repetition - School addon */}
			{shown.spacedRepetition && (
				<Card
					title={
						<Space>
							<Title level={4} style={{ margin: 0 }}>
								{__('Spaced Repetition', 'pressprimer-quiz')}
							</Title>
							<Tooltip title={__('Track student mastery over time and generate review quizzes for questions they need to practice', 'pressprimer-quiz')}>
								<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
							</Tooltip>
						</Space>
					}
					style={{ marginBottom: 24 }}
				>
					<Form.Item
						label={
							<Space>
								<span>{__('Enable Spaced Repetition', 'pressprimer-quiz')}</span>
								<Tooltip title={__('Questions from this quiz join each student’s review queue, scheduled with SM-2 or FSRS as set in School settings.', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
						}
						name="enable_sr"
						valuePropName="checked"
						style={{ marginBottom: 0 }}
					>
						<Switch size="small" />
					</Form.Item>
					<Text type="secondary" style={{ fontSize: 12, display: 'block', marginTop: 8 }}>
						{__('Students will see review prompts and can generate personalized review quizzes from questions they need to practice.', 'pressprimer-quiz')}
					</Text>
				</Card>
			)}

			{/* Measured Difficulty - School addon, dynamic/pool quizzes only */}
			{shown.measuredDifficulty && (
				<MeasuredDifficultyCard form={form} generationMode={generationMode} surface={surface} />
			)}

			{/* WP Fusion - School's WP Fusion feature */}
			{shown.wpFusion && (
				<Card
					title={
						<Space>
							<Title level={4} style={{ margin: 0 }}>
								{__('WP Fusion', 'pressprimer-quiz')}
							</Title>
							<Tooltip title={'admin' === surface
								? __('Apply CRM tags through WP Fusion for this quiz, on top of the site-wide defaults. Logged-in users always sync. Guests sync only if they tick the marketing-consent checkbox shown on the guest email form — enable that checkbox under PressPrimer Quiz → Settings → General → Guest Email Consent.', 'pressprimer-quiz')
								: __('Apply CRM tags through WP Fusion for this quiz, on top of the site-wide defaults. Logged-in users always sync. Guests sync only if they tick the marketing-consent checkbox shown on the guest email form.', 'pressprimer-quiz')}>
								<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
							</Tooltip>
						</Space>
					}
					style={{ marginBottom: 24 }}
				>
					<Text type="secondary" style={{ fontSize: 12, display: 'block', marginBottom: 16 }}>
						{'admin' === surface
							? __('These tags are added on top of the site-wide defaults set under PressPrimer Quiz → Settings → Integrations → WP Fusion.', 'pressprimer-quiz')
							: __('These tags are added on top of the site-wide WP Fusion defaults.', 'pressprimer-quiz')}
					</Text>
					{[
						{ name: 'wpf_tag_signup', label: __('Sign-up (email registration)', 'pressprimer-quiz') },
						{ name: 'wpf_tag_completion', label: __('Completion', 'pressprimer-quiz') },
						{ name: 'wpf_tag_pass', label: __('Pass', 'pressprimer-quiz') },
						{ name: 'wpf_tag_fail', label: __('Fail', 'pressprimer-quiz') },
					].map((field) => (
						<Form.Item
							key={field.name}
							label={field.label}
							name={field.name}
							style={{ marginBottom: 12 }}
						>
							<Select
								mode="multiple"
								allowClear
								placeholder={__('Select tags…', 'pressprimer-quiz')}
								options={wpfTagOptions}
								optionFilterProp="label"
							/>
						</Form.Item>
					))}
				</Card>
			)}

			{/* quiz.premium.card slots, after the built-in cards. */}
			<SlotList defs={cardSlots} slotProps={slotProps} />

			{/* The tiers this site doesn't have, after its real cards. */}
			<PremiumUpsell options={lockedOptions} pricingUrl={quizData.quizOptionsPricingUrl} compact />
		</Space>
	);
};

export default PremiumSettingsPanel;
