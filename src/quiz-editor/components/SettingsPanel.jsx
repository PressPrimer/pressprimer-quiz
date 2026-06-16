/**
 * Quiz Settings Panel Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	Form,
	Input,
	InputNumber,
	Select,
	Switch,
	Radio,
	Card,
	Space,
	Col,
	Row,
	Typography,
	Tooltip,
	Divider,
	Alert,
	Tag,
	Button,
	Checkbox,
	Anchor,
} from 'antd';
import {
	QuestionCircleOutlined,
	SaveOutlined,
} from '@ant-design/icons';
import ApplyTemplateButton from '../templates/ApplyTemplateButton';
import SaveAsTemplateButton from '../templates/SaveAsTemplateButton';

const { TextArea } = Input;
const { Title, Text } = Typography;

/**
 * Sections rendered on the Settings tab, in display order. Used to drive
 * the right-rail Anchor (table of contents). The `id` here must match the
 * `id` on the section wrapper div.
 */
const TOC_SECTIONS = [
	{ id: 'ppq-settings-basic-info', label: __('Basic Information', 'pressprimer-quiz') },
	{ id: 'ppq-settings-question-generation', label: __('Question Generation', 'pressprimer-quiz') },
	{ id: 'ppq-settings-quiz-behavior', label: __('Quiz Behavior', 'pressprimer-quiz') },
	{ id: 'ppq-settings-scoring', label: __('Scoring', 'pressprimer-quiz') },
	{ id: 'ppq-settings-navigation-attempts', label: __('Navigation & Attempts', 'pressprimer-quiz') },
	{ id: 'ppq-settings-display-presentation', label: __('Display & Presentation', 'pressprimer-quiz') },
];

/**
 * MA scoring modes, ordered lenient → strict. Each entry has a short
 * plain-language description and a worked example rendered side by side
 * in the Quiz Editor so authors can compare modes at a glance.
 */
/**
 * Quiz-level display option keys, grouped by the screen they affect.
 * Values default to `true` (all visible) when absent from the quiz's
 * display_settings_json. Reset buttons in the panel just clear the
 * keys for that section from the form value.
 */
const DISPLAY_OPTION_SECTIONS = [
	{
		key: 'start',
		title: __('Start Page', 'pressprimer-quiz'),
		options: [
			{ key: 'show_description', label: __('Show description', 'pressprimer-quiz'), tooltip: __('Show the quiz description on the landing page before the student starts an attempt.', 'pressprimer-quiz') },
			{ key: 'show_question_count', label: __('Show question count', 'pressprimer-quiz'), tooltip: __('Show the total number of questions in the quiz on the landing page.', 'pressprimer-quiz') },
			{ key: 'show_quiz_type', label: __('Show quiz type', 'pressprimer-quiz'), tooltip: __('Show whether the quiz is a fixed-question or dynamic (rule-based) quiz.', 'pressprimer-quiz') },
			{ key: 'show_time_limit', label: __('Show time limit', 'pressprimer-quiz'), tooltip: __('Show the quiz time limit (when one is set) on the landing page.', 'pressprimer-quiz') },
			{ key: 'show_pass_percentage', label: __('Show pass percentage', 'pressprimer-quiz'), tooltip: __('Show the score required to pass the quiz on the landing page.', 'pressprimer-quiz') },
			{ key: 'show_attempt_count', label: __('Show attempt count', 'pressprimer-quiz'), tooltip: __('Show how many times the student has attempted this quiz already.', 'pressprimer-quiz') },
			{ key: 'show_attempt_history', label: __('Show attempt history', 'pressprimer-quiz'), tooltip: __('Show a list of the student\'s previous attempts (date, score, pass/fail) on the landing page.', 'pressprimer-quiz') },
		],
	},
	{
		key: 'results',
		title: __('Results Page', 'pressprimer-quiz'),
		options: [
			{ key: 'show_score', label: __('Show score', 'pressprimer-quiz'), tooltip: __('Show the student\'s final score on the results page.', 'pressprimer-quiz') },
			{ key: 'show_pass_fail', label: __('Show pass/fail status', 'pressprimer-quiz'), tooltip: __('Show whether the student passed or failed the quiz on the results page.', 'pressprimer-quiz') },
			{ key: 'show_time_spent', label: __('Show time spent', 'pressprimer-quiz'), tooltip: __('Show how long the student took to complete the attempt.', 'pressprimer-quiz') },
			{ key: 'show_average', label: __('Show average score', 'pressprimer-quiz'), tooltip: __('Show the average score across all attempts of this quiz, for comparison.', 'pressprimer-quiz') },
			{ key: 'show_category_breakdown', label: __('Show category breakdown', 'pressprimer-quiz'), tooltip: __('Show per-category performance when the quiz\'s questions are organized by category.', 'pressprimer-quiz') },
			{ key: 'show_question_review', label: __('Show question review', 'pressprimer-quiz'), tooltip: __('Allow the student to review each question, their answer, and any feedback after submitting.', 'pressprimer-quiz') },
			{ key: 'show_retake_button', label: __('Show retake button', 'pressprimer-quiz'), tooltip: __('Show a button to retake the quiz when retakes are allowed on the results page.', 'pressprimer-quiz') },
		],
	},
];

const MA_SCORING_OPTIONS = [
	{
		value: 'right_minus_wrong',
		label: __('Right Minus Wrong', 'pressprimer-quiz'),
		description: __('Each wrong selection cancels one correct selection. Score never goes below zero.', 'pressprimer-quiz'),
		examples: [
			__('2 correct + 1 wrong → 0.33 points', 'pressprimer-quiz'),
		],
	},
	{
		value: 'proportional',
		label: __('Partial Credit', 'pressprimer-quiz'),
		description: __('Each correct selection earns proportional credit. Wrong selections are ignored.', 'pressprimer-quiz'),
		examples: [
			__('2 correct + 1 wrong → 0.67 points', 'pressprimer-quiz'),
		],
	},
	{
		value: 'partial_no_wrong',
		label: __('Partial Credit, No Wrong Answers', 'pressprimer-quiz'),
		description: __('Proportional credit, but any wrong selection scores zero for the question.', 'pressprimer-quiz'),
		examples: [
			__('2 correct + 1 wrong → 0 points', 'pressprimer-quiz'),
			__('2 correct + 0 wrong → 0.67 points', 'pressprimer-quiz'),
		],
	},
	{
		value: 'all_or_nothing',
		label: __('All or Nothing', 'pressprimer-quiz'),
		description: __('Full credit only when every correct answer is selected and none of the wrong ones.', 'pressprimer-quiz'),
		examples: [
			__('2 correct + 0 wrong → 0 points', 'pressprimer-quiz'),
			__('Only 3 correct + 0 wrong → 1.00 points', 'pressprimer-quiz'),
		],
	},
];

/**
 * Settings Panel Component
 *
 * @param {Object}   props                     Component props
 * @param {Object}   props.form                Ant Design form instance
 * @param {string}   props.generationMode      Current generation mode
 * @param {Function} props.setGenerationMode   Function to update generation mode
 * @param {Object}   props.quizData            Initial quiz data (includes educator addon fields)
 * @param {string[]} props.maxAnswersWarnings  Informational warnings returned from the save endpoint
 *                                              for the random distractor cap (e.g., questions whose
 *                                              correct count exceeds the cap).
 * @param {boolean}  props.saving              Whether a save is currently in progress (drives the
 *                                              loading state on the TOC's Save button).
 * @param {Function} props.applyTemplate       Applies a settings template to the editor form
 *                                              (client-side only; nothing persists until save).
 * @param {Function} props.collectTemplateSettings Returns the editor's current settings as a
 *                                              template payload (for "Save as template").
 */
const SettingsPanel = ({ form, generationMode, setGenerationMode, quizData = {}, maxAnswersWarnings = [], saving = false, applyTemplate, collectTemplateSettings }) => {
	// Watch access_mode to show/hide login message field
	const accessMode = Form.useWatch('access_mode', form);

	// Watch pool fields for conditional rendering
	const poolEnabled = Form.useWatch('pool_enabled', form);
	const maxQuestions = Form.useWatch('max_questions', form);
	const passPercent = Form.useWatch('pass_percent', form);

	// Watch scoring mode so the selected card highlights as the user clicks.
	const maScoringMode = Form.useWatch('ma_scoring_mode', form);
	const siteDefaultMaScoring = quizData.default_ma_scoring || 'right_minus_wrong';

	// Watch display_settings so the 14 toggles stay in sync with form state.
	const displaySettings = Form.useWatch('display_settings', form) || {};

	/**
	 * Resolve the current value of one display key for the checkbox.
	 * A key absent from display_settings reflects the hard-coded default
	 * (true), so checkboxes are checked by default for new quizzes.
	 */
	const getDisplayValue = (key) => {
		if (Object.prototype.hasOwnProperty.call(displaySettings, key)) {
			return Boolean(displaySettings[key]);
		}
		return true;
	};

	/**
	 * Set one display key in the form's display_settings object.
	 */
	const setDisplayValue = (key, value) => {
		form.setFieldsValue({
			display_settings: {
				...displaySettings,
				[key]: Boolean(value),
			},
		});
	};

	/**
	 * Remove the keys for a section from display_settings so they fall back
	 * to the hard-coded default at render time. Used by the per-section
	 * "Reset to defaults" button.
	 *
	 * Important: form.setFieldsValue() performs a deep merge, which means
	 * passing an object with deleted keys would leave the old keys in place.
	 * We use form.setFields() instead so the field is fully replaced with
	 * the new (smaller) object.
	 */
	const resetDisplaySection = (sectionKey) => {
		const section = DISPLAY_OPTION_SECTIONS.find((s) => s.key === sectionKey);
		if (!section) {
			return;
		}
		const next = { ...displaySettings };
		section.options.forEach((opt) => {
			delete next[opt.key];
		});
		form.setFields([{ name: 'display_settings', value: next }]);
	};

	// Branching rules enforce certain settings.
	const hasBranchingRules = !! quizData.hasBranchingRules;
	const branchingTooltip = __('This setting is locked because branching rules are active on this quiz.', 'pressprimer-quiz');

	return (
		<div className="ppq-settings-layout">
			<div className="ppq-settings-content">
		{applyTemplate && (
			<div className="ppq-settings-toolbar">
				<ApplyTemplateButton form={form} quizData={quizData} onApply={applyTemplate} />
				{quizData.canManageSettings && collectTemplateSettings && (
					<SaveAsTemplateButton onCollectSettings={collectTemplateSettings} />
				)}
			</div>
		)}
		<Space direction="vertical" size="large" style={{ width: '100%' }}>
			{hasBranchingRules && (
				<Alert
					type="warning"
					showIcon
					message={__('Some settings are locked by branching rules', 'pressprimer-quiz')}
					description={__('This quiz has active branching rules that require paginated mode, no question randomization, and no backward navigation. Remove the branching rules to unlock these settings.', 'pressprimer-quiz')}
				/>
			)}
			{/* Basic Information */}
			<div id="ppq-settings-basic-info" className="ppq-settings-section">
			<Card
				title={
					<Space>
						<Title level={4} style={{ margin: 0 }}>
							{__('Basic Information', 'pressprimer-quiz')} <span style={{ color: '#ff4d4f' }}>*</span>
						</Title>
						<Tooltip title={__('Enter the quiz title, description, and basic settings', 'pressprimer-quiz')}>
							<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
						</Tooltip>
					</Space>
				}
				style={{ marginBottom: 24 }}
			>
				<Form.Item
					label={
						<Space>
							<span>{__('Quiz Title', 'pressprimer-quiz')}</span>
							<Tooltip title={__('Enter a clear, descriptive title that students will see', 'pressprimer-quiz')}>
								<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
							</Tooltip>
						</Space>
					}
					name="title"
					rules={[{ required: true, message: __('Please enter a quiz title', 'pressprimer-quiz') }]}
				>
					<Input
						placeholder={__('e.g., "Chapter 5 Assessment" or "Final Exam"', 'pressprimer-quiz')}
						size="small"
						style={{ maxWidth: 500 }}
					/>
				</Form.Item>

				<Form.Item
					label={
						<Space>
							<span>{__('Description', 'pressprimer-quiz')}</span>
							<Tooltip title={__('Optional instructions or context shown to students before they start', 'pressprimer-quiz')}>
								<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
							</Tooltip>
						</Space>
					}
					name="description"
				>
					<TextArea
						rows={4}
						placeholder={__('Enter instructions, learning objectives, or any context students need...', 'pressprimer-quiz')}
						size="small"
						style={{ maxWidth: 600 }}
					/>
				</Form.Item>

				<Row gutter={16}>
					<Col span={12}>
						<Form.Item
							label={
								<Space>
									<span>{__('Status', 'pressprimer-quiz')}</span>
									<Tooltip title={__('Draft = work in progress (only you can see) • Published = visible to students • Archived = hidden but preserved', 'pressprimer-quiz')}>
										<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
									</Tooltip>
								</Space>
							}
							name="status"
							style={{ marginBottom: 0 }}
						>
							<Select size="small" style={{ width: 240 }} options={[
								{ value: 'draft', label: __('Draft', 'pressprimer-quiz') },
								{ value: 'published', label: __('Published', 'pressprimer-quiz') },
								{ value: 'archived', label: __('Archived', 'pressprimer-quiz') },
							]} />
						</Form.Item>
						<Text type="secondary" style={{ fontSize: 13, display: 'block', marginTop: 4, marginBottom: 24 }}>
							{__('Draft for editing, Published for students, Archived to hide', 'pressprimer-quiz')}
						</Text>
					</Col>
					<Col span={12}>
						<Form.Item
							label={
								<Space>
									<span>{__('Theme', 'pressprimer-quiz')}</span>
									<Tooltip title={__('Visual style for the quiz interface', 'pressprimer-quiz')}>
										<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
									</Tooltip>
								</Space>
							}
							name="theme"
						>
							<Select size="small" style={{ width: 240 }} options={[
								{ value: 'default', label: __('Default', 'pressprimer-quiz') },
								{ value: 'modern', label: __('Modern', 'pressprimer-quiz') },
								{ value: 'minimal', label: __('Minimal', 'pressprimer-quiz') },
							]} />
						</Form.Item>
					</Col>
				</Row>
			</Card>
			</div>

			{/* Question Generation */}
			<div id="ppq-settings-question-generation" className="ppq-settings-section">
			<Card
				title={
					<Space>
						<Title level={4} style={{ margin: 0 }}>
							{__('Question Generation', 'pressprimer-quiz')} <span style={{ color: '#ff4d4f' }}>*</span>
						</Title>
						<Tooltip title={__('Choose how questions are selected for each quiz attempt', 'pressprimer-quiz')}>
							<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
						</Tooltip>
					</Space>
				}
				style={{ marginBottom: 24 }}
			>
				<Form.Item name="generation_mode">
					<Radio.Group onChange={(e) => setGenerationMode(e.target.value)} style={{ width: '100%' }}>
						<Space direction="vertical" style={{ width: '100%' }} size="middle">
							<Card
								hoverable
								style={{
									border: generationMode === 'fixed' ? '2px solid #1890ff' : '1px solid #d9d9d9',
									backgroundColor: generationMode === 'fixed' ? '#e6f7ff' : '#fff',
									cursor: 'pointer',
								}}
								onClick={() => {
									setGenerationMode('fixed');
									form.setFieldsValue({ generation_mode: 'fixed' });
								}}
							>
								<Radio value="fixed" style={{ width: '100%' }}>
									<div>
										<div style={{ fontWeight: 600, fontSize: 16 }}>
											{__('Fixed Questions', 'pressprimer-quiz')}
										</div>
										<Text type="secondary" style={{ fontSize: 13 }}>
											{__('Manually select specific questions - same for every student', 'pressprimer-quiz')}
										</Text>
									</div>
								</Radio>
							</Card>
							<Card
								hoverable
								style={{
									border: generationMode === 'dynamic' ? '2px solid #1890ff' : '1px solid #d9d9d9',
									backgroundColor: generationMode === 'dynamic' ? '#e6f7ff' : '#fff',
									cursor: 'pointer',
								}}
								onClick={() => {
									setGenerationMode('dynamic');
									form.setFieldsValue({ generation_mode: 'dynamic' });
								}}
							>
								<Radio value="dynamic" style={{ width: '100%' }}>
									<div>
										<div style={{ fontWeight: 600, fontSize: 16 }}>
											{__('Dynamic Rules', 'pressprimer-quiz')}
										</div>
										<Text type="secondary" style={{ fontSize: 13 }}>
											{__('Define rules to randomly select questions from banks - different for each student', 'pressprimer-quiz')}
										</Text>
									</div>
								</Radio>
							</Card>
						</Space>
					</Radio.Group>
				</Form.Item>
			</Card>
			</div>

			{/* Quiz Behavior */}
			<div id="ppq-settings-quiz-behavior" className="ppq-settings-section">
			<Card
				title={
					<Space>
						<Title level={4} style={{ margin: 0 }}>
							{__('Quiz Behavior', 'pressprimer-quiz')}
						</Title>
						<Tooltip title={__('Configure how the quiz behaves and how students interact with it', 'pressprimer-quiz')}>
							<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
						</Tooltip>
					</Space>
				}
				style={{ marginBottom: 24 }}
			>
				<Form.Item
					label={
						<Space>
							<span>{__('Quiz Mode', 'pressprimer-quiz')}</span>
							<Tooltip title={__('Controls when students receive feedback on their answers', 'pressprimer-quiz')}>
								<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
							</Tooltip>
						</Space>
					}
					name="mode"
				>
					<Radio.Group style={{ width: '100%' }}>
						<Space direction="vertical" style={{ width: '100%' }} size="small">
							<Radio value="tutorial">
								<div>
									<div style={{ fontWeight: 600 }}>
										{__('Tutorial Mode', 'pressprimer-quiz')}
									</div>
									<Text type="secondary" style={{ fontSize: 13 }}>
										{__('Show feedback immediately after each question - best for learning', 'pressprimer-quiz')}
									</Text>
								</div>
							</Radio>
							<Radio value="timed">
								<div>
									<div style={{ fontWeight: 600 }}>
										{__('Test Mode', 'pressprimer-quiz')}
									</div>
									<Text type="secondary" style={{ fontSize: 13 }}>
										{__('Show feedback only after submission - best for assessments', 'pressprimer-quiz')}
									</Text>
								</div>
							</Radio>
						</Space>
					</Radio.Group>
				</Form.Item>

				<Divider />

				<Row gutter={16}>
					<Col span={12}>
						<Form.Item
							label={
								<Space>
									<span>{__('Time Limit', 'pressprimer-quiz')}</span>
									<Tooltip title={__('Set a time limit in minutes, or leave empty for unlimited time', 'pressprimer-quiz')}>
										<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
									</Tooltip>
								</Space>
							}
							name="time_limit_seconds"
							style={{ marginBottom: 0 }}
						>
							<InputNumber
								min={60}
								max={86400}
								step={60}
								size="small"
								style={{ width: 200 }}
								placeholder={__('Unlimited', 'pressprimer-quiz')}
								formatter={value => value ? Math.round(value / 60) : ''}
								parser={value => value ? value * 60 : null}
								addonAfter={__('min', 'pressprimer-quiz')}
							/>
						</Form.Item>
						<Text type="secondary" style={{ fontSize: 13, display: 'block', marginTop: 4, marginBottom: 24 }}>
							{__('Leave empty for no time limit', 'pressprimer-quiz')}
						</Text>
					</Col>
					<Col span={12}>
						<Form.Item
							label={
								<Space>
									<span>{__('Passing Score', 'pressprimer-quiz')}</span>
									<Tooltip title={__('Percentage needed to pass - affects pass/fail status', 'pressprimer-quiz')}>
										<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
									</Tooltip>
								</Space>
							}
							name="pass_percent"
						>
							<InputNumber
								min={0}
								max={100}
								step={1}
								size="small"
								style={{ width: 150 }}
								addonAfter="%"
							/>
						</Form.Item>
					</Col>
				</Row>
			</Card>
			</div>

			{/* Scoring */}
			<div id="ppq-settings-scoring" className="ppq-settings-section">
			<Card
				title={
					<Space>
						<Title level={4} style={{ margin: 0 }}>
							{__('Scoring', 'pressprimer-quiz')}
						</Title>
						<Tooltip title={__('How multiple-answer questions are scored for this quiz', 'pressprimer-quiz')}>
							<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
						</Tooltip>
					</Space>
				}
				style={{ marginBottom: 24 }}
			>
				<Text type="secondary" style={{ display: 'block', marginBottom: 12, fontSize: 13 }}>
					{__('Choose how multiple-answer (MA) questions are scored. Single-answer (MC, true/false) questions are always all-or-nothing.', 'pressprimer-quiz')}
				</Text>
				<Text type="secondary" style={{ display: 'block', marginBottom: 16, fontSize: 13 }}>
					{__('Examples below assume a question worth 1 point where 3 of the answer options are marked correct.', 'pressprimer-quiz')}
				</Text>

				<Form.Item name="ma_scoring_mode" style={{ marginBottom: 0 }}>
					<Radio.Group style={{ width: '100%' }}>
						<Space direction="vertical" style={{ width: '100%' }} size="small">
							{MA_SCORING_OPTIONS.map((option) => {
								const isSelected = maScoringMode === option.value;
								const isSiteDefault = option.value === siteDefaultMaScoring;
								return (
									<Card
										key={option.value}
										hoverable
										size="small"
										style={{
											border: isSelected ? '2px solid #1890ff' : '1px solid #d9d9d9',
											backgroundColor: isSelected ? '#e6f7ff' : '#fff',
											cursor: 'pointer',
										}}
										onClick={() => form.setFieldsValue({ ma_scoring_mode: option.value })}
									>
										<Row gutter={12} align="top" wrap={false} style={{ width: '100%' }}>
											<Col flex="none" style={{ paddingTop: 2 }}>
												<Radio value={option.value} />
											</Col>
											<Col flex="auto">
												<Row gutter={16} align="top">
													<Col xs={24} sm={14}>
														<div style={{ fontWeight: 600, fontSize: 15, marginBottom: 4 }}>
															{option.label}
															{isSiteDefault && (
																<Tag color="blue" style={{ marginLeft: 8 }}>
																	{__('Site Default', 'pressprimer-quiz')}
																</Tag>
															)}
														</div>
														<Text type="secondary" style={{ fontSize: 13 }}>
															{option.description}
														</Text>
													</Col>
													<Col xs={24} sm={10}>
														<div style={{
															background: '#fffbe6',
															borderLeft: '3px solid #faad14',
															padding: '8px 12px',
															borderRadius: '0 4px 4px 0',
														}}>
															<Text strong style={{ fontSize: 11, color: '#ad8b00', letterSpacing: '0.5px', display: 'block', marginBottom: 2 }}>
																{__('EXAMPLE', 'pressprimer-quiz')}
															</Text>
															{option.examples.map((line, i) => (
																<Text key={i} style={{ fontSize: 13, display: 'block' }}>{line}</Text>
															))}
														</div>
													</Col>
												</Row>
											</Col>
										</Row>
									</Card>
								);
							})}
						</Space>
					</Radio.Group>
				</Form.Item>
			</Card>
			</div>

			{/* Navigation & Attempts */}
			<div id="ppq-settings-navigation-attempts" className="ppq-settings-section">
			<Card
				title={
					<Space>
						<Title level={4} style={{ margin: 0 }}>
							{__('Navigation & Attempts', 'pressprimer-quiz')}
						</Title>
						<Tooltip title={__('Control how students navigate the quiz and retry limits', 'pressprimer-quiz')}>
							<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
						</Tooltip>
					</Space>
				}
				style={{ marginBottom: 24 }}
			>
				<Row gutter={[24, 16]}>
					<Col span={12}>
						<Space direction="vertical" style={{ width: '100%' }} size="small">
							<Text strong>{__('Navigation Options', 'pressprimer-quiz')}</Text>
							<Form.Item
								label={
									<Space>
										<span>{__('Allow Skip Questions', 'pressprimer-quiz')}</span>
										<Tooltip title={__('Students can leave questions unanswered and continue', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="allow_skip"
								valuePropName="checked"
								style={{ marginBottom: 8 }}
							>
								<Switch size="small" />
							</Form.Item>
							<Form.Item
								label={
									<Space>
										<span>{__('Allow Backward Navigation', 'pressprimer-quiz')}</span>
										<Tooltip title={hasBranchingRules ? branchingTooltip : __('Students can go back to previous questions', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="allow_backward"
								valuePropName="checked"
								style={{ marginBottom: 8 }}
							>
								{hasBranchingRules ? (
									<Tooltip title={branchingTooltip}>
										<Switch size="small" disabled />
									</Tooltip>
								) : (
									<Switch size="small" />
								)}
							</Form.Item>
							<Form.Item
								label={
									<Space>
										<span>{__('Allow Resume', 'pressprimer-quiz')}</span>
										<Tooltip title={__('Students can save progress and continue later', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="allow_resume"
								valuePropName="checked"
								style={{ marginBottom: 0 }}
							>
								<Switch size="small" />
							</Form.Item>
						</Space>
					</Col>
					<Col span={12}>
						<Space direction="vertical" style={{ width: '100%' }} size="small">
							<Text strong>{__('Attempt Limits', 'pressprimer-quiz')}</Text>
							<Form.Item
								label={
									<Space>
										<span>{__('Maximum Attempts', 'pressprimer-quiz')}</span>
										<Tooltip title={__('How many times a student can take this quiz (empty = unlimited)', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="max_attempts"
								style={{ marginBottom: 0 }}
							>
								<InputNumber
									min={1}
									max={100}
									size="small"
									style={{ width: 150 }}
									placeholder={__('Unlimited', 'pressprimer-quiz')}
								/>
							</Form.Item>
							<Text type="secondary" style={{ fontSize: 13, display: 'block', marginTop: 4, marginBottom: 24 }}>
								{__('Empty for unlimited attempts', 'pressprimer-quiz')}
							</Text>
							<Form.Item
								label={
									<Space>
										<span>{__('Delay Between Attempts', 'pressprimer-quiz')}</span>
										<Tooltip title={__('Minimum time (in minutes) students must wait between attempts', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="attempt_delay_minutes"
							>
								<InputNumber
									min={0}
									max={10080}
									size="small"
									style={{ width: 200 }}
									placeholder="0"
									addonAfter={__('min', 'pressprimer-quiz')}
								/>
							</Form.Item>
						</Space>
					</Col>
				</Row>

				<Divider />

				{/* Access Control */}
				<Text strong>{__('Access Control', 'pressprimer-quiz')}</Text>
				<Form.Item
					label={
						<Space>
							<span>{__('Access Mode', 'pressprimer-quiz')}</span>
							<Tooltip title={__('Control whether guests can take this quiz or if login is required', 'pressprimer-quiz')}>
								<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
							</Tooltip>
						</Space>
					}
					name="access_mode"
					style={{ marginTop: 12, marginBottom: 0 }}
				>
					<Select
						size="small"
						style={{ width: 300 }}
						options={[
							{ value: 'default', label: __('Use Global Default', 'pressprimer-quiz') },
							{ value: 'guest_optional', label: __('Allow Guests (Email Optional)', 'pressprimer-quiz') },
							{ value: 'guest_required', label: __('Allow Guests (Email Required)', 'pressprimer-quiz') },
							{ value: 'login_required', label: __('Require Login', 'pressprimer-quiz') },
						]}
					/>
				</Form.Item>
				<Text type="secondary" style={{ fontSize: 13, display: 'block', marginTop: 4, marginBottom: 24 }}>
					{__('Override the global access setting for this specific quiz', 'pressprimer-quiz')}
				</Text>

				{accessMode === 'login_required' && (
					<Form.Item
						label={
							<Space>
								<span>{__('Custom Login Message', 'pressprimer-quiz')}</span>
								<Tooltip title={__('Message shown when login is required. Leave empty to use the global default.', 'pressprimer-quiz')}>
									<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
							</Space>
						}
						name="login_message"
					>
						<TextArea
							rows={2}
							placeholder={__('Leave empty to use global default message', 'pressprimer-quiz')}
							style={{ maxWidth: 500 }}
							size="small"
						/>
					</Form.Item>
				)}
			</Card>
			</div>

			{/* Display Options */}
			<div id="ppq-settings-display-presentation" className="ppq-settings-section">
			<Card
				title={
					<Space>
						<Title level={4} style={{ margin: 0 }}>
							{__('Display & Presentation', 'pressprimer-quiz')}
						</Title>
						<Tooltip title={__('Control how questions and answers are displayed to students', 'pressprimer-quiz')}>
							<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
						</Tooltip>
					</Space>
				}
				style={{ marginBottom: 24 }}
			>
				<Row gutter={[24, 16]}>
					<Col span={12}>
						<Space direction="vertical" style={{ width: '100%' }} size="small">
							<Text strong>{__('Randomization', 'pressprimer-quiz')}</Text>
							<Form.Item
								label={
									<Space>
										<span>{__('Randomize Question Order', 'pressprimer-quiz')}</span>
										<Tooltip title={hasBranchingRules ? branchingTooltip : __('Shuffle the order of questions for each attempt', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="randomize_questions"
								valuePropName="checked"
								style={{ marginBottom: 8 }}
							>
								{hasBranchingRules ? (
									<Tooltip title={branchingTooltip}>
										<Switch size="small" disabled />
									</Tooltip>
								) : (
									<Switch size="small" />
								)}
							</Form.Item>
							<Form.Item
								label={
									<Space>
										<span>{__('Randomize Answer Options', 'pressprimer-quiz')}</span>
										<Tooltip title={__('Shuffle answer choices within each question', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="randomize_answers"
								valuePropName="checked"
								style={{ marginBottom: 8 }}
							>
								<Switch size="small" />
							</Form.Item>
							<Form.Item
								label={
									<Space>
										<span>{__('Show Points Per Question', 'pressprimer-quiz')}</span>
										<Tooltip title={__('Display point values on each question during the quiz and on the results page', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="show_points"
								valuePropName="checked"
							>
								<Switch size="small" />
							</Form.Item>
						</Space>
					</Col>
					<Col span={12}>
						<Space direction="vertical" style={{ width: '100%' }} size="small">
							<Text strong>{__('Layout', 'pressprimer-quiz')}</Text>
							<Form.Item
								label={
									<Space>
										<span>{__('Page Mode', 'pressprimer-quiz')}</span>
										<Tooltip title={hasBranchingRules ? branchingTooltip : __('Show all questions on one page or paginate them', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="page_mode"
							>
								{hasBranchingRules ? (
									<Tooltip title={branchingTooltip}>
										<Radio.Group size="small" disabled>
											<Radio value="single">{__('Single Page', 'pressprimer-quiz')}</Radio>
											<Radio value="paged">{__('Paginated', 'pressprimer-quiz')}</Radio>
										</Radio.Group>
									</Tooltip>
								) : (
									<Radio.Group size="small">
										<Radio value="single">{__('Single Page', 'pressprimer-quiz')}</Radio>
										<Radio value="paged">{__('Paginated', 'pressprimer-quiz')}</Radio>
									</Radio.Group>
								)}
							</Form.Item>
							<Form.Item
								label={
									<Space>
										<span>{__('Questions Per Page', 'pressprimer-quiz')}</span>
										<Tooltip title={__('For paginated mode - how many questions to show per page', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="questions_per_page"
							>
								<InputNumber
									min={1}
									max={100}
									size="small"
									style={{ width: 150 }}
								/>
							</Form.Item>
							<Form.Item
								label={
									<Space>
										<span>{__('Display Density', 'pressprimer-quiz')}</span>
										<Tooltip title={__('Control spacing and visual density of the quiz interface', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="display_density"
							>
								<Select
									size="small"
									style={{ width: 240 }}
									options={[
										{ value: 'default', label: __('Use Global Default', 'pressprimer-quiz') },
										{ value: 'standard', label: __('Standard', 'pressprimer-quiz') },
										{ value: 'condensed', label: __('Condensed', 'pressprimer-quiz') },
									]}
								/>
							</Form.Item>
						</Space>
					</Col>
				</Row>

				<Divider />

				<Row gutter={16}>
					<Col span={12}>
						<Form.Item
							label={
								<Space>
									<span>{__('Show Correct Answers', 'pressprimer-quiz')}</span>
									<Tooltip title={__('Control when students can see the correct answers', 'pressprimer-quiz')}>
										<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
									</Tooltip>
								</Space>
							}
							name="show_answers"
							style={{ marginBottom: 0 }}
						>
							<Select
								size="small"
								style={{ width: 240 }}
								options={[
									{ value: 'never', label: __('Never', 'pressprimer-quiz') },
									{ value: 'after_submit', label: __('After Submit', 'pressprimer-quiz') },
									{ value: 'after_pass', label: __('After Passing', 'pressprimer-quiz') },
								]}
							/>
						</Form.Item>
						<Text type="secondary" style={{ fontSize: 13, display: 'block', marginTop: 4, marginBottom: 24 }}>
							{__('When students can view correct answers', 'pressprimer-quiz')}
						</Text>
					</Col>
					<Col span={12}>
						<Form.Item
							label={
								<Space>
									<span>{__('Enable Confidence Rating', 'pressprimer-quiz')}</span>
									<Tooltip title={__('Ask students to rate their confidence on each answer', 'pressprimer-quiz')}>
										<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
									</Tooltip>
								</Space>
							}
							name="enable_confidence"
							valuePropName="checked"
							style={{ marginBottom: 0 }}
						>
							<Switch size="small" />
						</Form.Item>
						<Text type="secondary" style={{ fontSize: 13, display: 'block', marginTop: 4, marginBottom: 24 }}>
							{__('Helps identify knowledge gaps', 'pressprimer-quiz')}
						</Text>
					</Col>
				</Row>

				<Divider />

				{/* Question Pool */}
				<Text strong>{__('Question Pool', 'pressprimer-quiz')}</Text>
				<Text type="secondary" style={{ fontSize: 13, display: 'block', marginBottom: 12 }}>
					{__('Randomly select a subset of questions for each attempt', 'pressprimer-quiz')}
				</Text>

				<Row gutter={24}>
					<Col xs={24} sm={12}>
						{(() => {
							const poolSize = Number(quizData.pool_size) || 0;

							if (poolSize === 0 && !quizData.id) {
								return (
									<Alert
										type="info"
										message={__('Add questions to enable pooling.', 'pressprimer-quiz')}
										showIcon
									/>
								);
							}

							return (
								<>
									<div style={{ display: 'flex', alignItems: 'flex-start', gap: 16, flexWrap: 'wrap' }}>
										<Form.Item
											label={
												<Space>
													<span>{__('Limit Questions Per Attempt', 'pressprimer-quiz')}</span>
													<Tooltip title={__('When enabled, each attempt randomly selects a subset of questions from the full pool', 'pressprimer-quiz')}>
														<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
													</Tooltip>
												</Space>
											}
											name="pool_enabled"
											valuePropName="checked"
											style={{ marginBottom: 0 }}
										>
											<Switch size="small" />
										</Form.Item>

										{poolEnabled && (
											<>
												<Form.Item
													label={
														<Space>
															<span>{__('Questions Per Attempt', 'pressprimer-quiz')}</span>
															<Tooltip title={__('How many questions to randomly draw from the pool for each attempt', 'pressprimer-quiz')}>
																<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
															</Tooltip>
														</Space>
													}
													name="max_questions"
													rules={[
														{
															validator: (_, value) => {
																if (value && Number(poolSize) > 0 && Number(value) > Number(poolSize)) {
																	return Promise.reject(
																		sprintf(
																			/* translators: %d: pool size */
																			__('Cannot exceed pool size (%d).', 'pressprimer-quiz'),
																			poolSize
																		)
																	);
																}
																return Promise.resolve();
															},
														},
													]}
													validateTrigger={['onChange', 'onBlur']}
													style={{ marginBottom: 0 }}
												>
													<InputNumber
														min={1}
														max={poolSize > 0 ? poolSize : undefined}
														placeholder={poolSize > 0 ? String(poolSize) : ''}
														style={{ width: 150 }}
														size="small"
													/>
												</Form.Item>
												<Text type="secondary" style={{ fontSize: 13, display: 'block', marginTop: 4, marginBottom: 24 }}>
													{poolSize > 0
														? sprintf(
															/* translators: %d: total number of questions in pool */
															__('From a pool of %d available questions', 'pressprimer-quiz'),
															poolSize
														)
														: __('Save quiz and add questions to see pool size', 'pressprimer-quiz')}
												</Text>
											</>
										)}
									</div>

									{poolEnabled && (
										<>

											{maxQuestions && maxQuestions > 0 && maxQuestions <= 100 && passPercent > 0 && (
												<Alert
													type="warning"
													message={sprintf(
														/* translators: 1: number of questions, 2: pass percentage, 3: number of correct answers needed */
														__('With %1$d questions and a %2$d%% pass rate, students need at least %3$d correct answers to pass.', 'pressprimer-quiz'),
														maxQuestions,
														passPercent,
														Math.ceil((maxQuestions * passPercent) / 100)
													)}
													showIcon
													style={{ marginTop: 12, marginBottom: 0 }}
												/>
											)}

											<Alert
												type="info"
												message={__('Each attempt randomly selects from the full pool. Students may see different questions on retakes.', 'pressprimer-quiz')}
												showIcon
												style={{ marginTop: 12, marginBottom: 0 }}
											/>
										</>
									)}
								</>
							);
						})()}
					</Col>
					<Col xs={24} sm={12}>
						<Form.Item
							label={
								<Space>
									<span>{__('Maximum Answers Per Question', 'pressprimer-quiz')}</span>
									<Tooltip title={__('Limit how many answer options each question shows per attempt. Correct answers are always included; remaining slots are filled with random distractors. Different attempts may see different subsets. If the number exceeds the available answers for a question, all answers will be shown.', 'pressprimer-quiz')}>
										<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
								</Tooltip>
								</Space>
							}
							name="max_answers_per_question"
							style={{ marginBottom: 0 }}
						>
							<InputNumber
								min={2}
								max={8}
								size="small"
								style={{ width: 150 }}
								placeholder={__('All answers', 'pressprimer-quiz')}
							/>
						</Form.Item>
						<Text type="secondary" style={{ fontSize: 13, display: 'block', marginTop: 4, marginBottom: 24 }}>
							{__('Leave empty to show all answers. Range: 2 to 8.', 'pressprimer-quiz')}
						</Text>
						{maxAnswersWarnings.length > 0 && (
							<Space direction="vertical" style={{ width: '100%', marginTop: 12 }} size={8}>
								{maxAnswersWarnings.map((warning, i) => (
									<Alert key={i} type="warning" message={warning} showIcon />
								))}
							</Space>
						)}
					</Col>
				</Row>

				<Divider />

				{/* Hidden field so the form recognizes display_settings on submit. */}
				<Form.Item name="display_settings" hidden>
					<Input />
				</Form.Item>

				<Text strong style={{ display: 'block', marginBottom: 4 }}>
					{__('Quiz-Level Display Defaults', 'pressprimer-quiz')}
				</Text>
				<Text type="secondary" style={{ display: 'block', marginBottom: 12, fontSize: 13 }}>
					{__('Toggle which sections appear by default on the Start and Results pages for this quiz. Block and shortcode attributes can override these per instance.', 'pressprimer-quiz')}
				</Text>
				<Row gutter={24}>
					{DISPLAY_OPTION_SECTIONS.map((section) => (
						<Col xs={24} sm={12} key={section.key}>
							<Text strong style={{ display: 'block', marginBottom: 8 }}>
								{section.title}
							</Text>
							<Space direction="vertical" style={{ width: '100%' }} size={4}>
								{section.options.map((opt) => (
									<Space key={opt.key} size={6} align="center">
										<Checkbox
											checked={getDisplayValue(opt.key)}
											onChange={(e) => setDisplayValue(opt.key, e.target.checked)}
										>
											{opt.label}
										</Checkbox>
										{opt.tooltip && (
											<Tooltip title={opt.tooltip}>
												<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
											</Tooltip>
										)}
									</Space>
								))}
							</Space>
							<Button
								type="link"
								size="small"
								style={{ paddingLeft: 0, marginTop: 8 }}
								onClick={() => resetDisplaySection(section.key)}
							>
								{__('Reset to defaults', 'pressprimer-quiz')}
							</Button>
						</Col>
					))}
				</Row>
			</Card>
			</div>

		</Space>
			</div>
			<aside className="ppq-settings-toc-wrap" aria-label={__('Settings sections', 'pressprimer-quiz')}>
				<Card
					size="small"
					title={
						<Text strong style={{ fontSize: 13 }}>
							{__('Table of Contents', 'pressprimer-quiz')}
						</Text>
					}
					className="ppq-settings-toc-card"
				>
					<Anchor
						affix={false}
						targetOffset={80}
						items={TOC_SECTIONS.map((s) => ({
							key: s.id,
							href: `#${s.id}`,
							title: s.label,
						}))}
					/>
					<Button
						type="primary"
						icon={<SaveOutlined />}
						htmlType="submit"
						loading={saving}
						block
						style={{ marginTop: 12 }}
					>
						{__('Save Quiz', 'pressprimer-quiz')}
					</Button>
				</Card>
			</aside>
		</div>
	);
};

export default SettingsPanel;
