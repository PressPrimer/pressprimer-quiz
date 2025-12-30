/**
 * Quiz Settings Panel Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
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
} from 'antd';
import {
	QuestionCircleOutlined,
	ClockCircleOutlined,
	TrophyOutlined,
	CompassOutlined,
	ReloadOutlined,
	EyeOutlined,
	ExperimentOutlined,
	FileTextOutlined,
} from '@ant-design/icons';

const { TextArea } = Input;
const { Title, Text } = Typography;

/**
 * Settings Panel Component
 *
 * @param {Object} props Component props
 * @param {Object} props.form Ant Design form instance
 * @param {string} props.generationMode Current generation mode
 * @param {Function} props.setGenerationMode Function to update generation mode
 */
const SettingsPanel = ({ form, generationMode, setGenerationMode }) => {
	// Watch access_mode to show/hide login message field
	const accessMode = Form.useWatch('access_mode', form);
	return (
		<Space direction="vertical" size="large" style={{ width: '100%' }}>
			{/* Basic Information */}
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
							<FileTextOutlined />
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
					/>
				</Form.Item>

				<Form.Item
					label={
						<Space>
							<FileTextOutlined />
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
						>
							<Select size="small" options={[
								{ value: 'draft', label: __('Draft', 'pressprimer-quiz') },
								{ value: 'published', label: __('Published', 'pressprimer-quiz') },
								{ value: 'archived', label: __('Archived', 'pressprimer-quiz') },
							]} />
						</Form.Item>
						<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: -8 }}>
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
							<Select size="small" options={[
								{ value: 'default', label: __('Default', 'pressprimer-quiz') },
								{ value: 'modern', label: __('Modern', 'pressprimer-quiz') },
								{ value: 'minimal', label: __('Minimal', 'pressprimer-quiz') },
							]} />
						</Form.Item>
					</Col>
				</Row>
			</Card>

			{/* Question Generation */}
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
										<Text type="secondary" style={{ fontSize: 14 }}>
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
										<Text type="secondary" style={{ fontSize: 14 }}>
											{__('Define rules to randomly select questions from banks - different for each student', 'pressprimer-quiz')}
										</Text>
									</div>
								</Radio>
							</Card>
						</Space>
					</Radio.Group>
				</Form.Item>
			</Card>

			{/* Quiz Behavior */}
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
									<Text type="secondary" style={{ fontSize: 14 }}>
										{__('Show feedback immediately after each question - best for learning', 'pressprimer-quiz')}
									</Text>
								</div>
							</Radio>
							<Radio value="timed">
								<div>
									<div style={{ fontWeight: 600 }}>
										{__('Test Mode', 'pressprimer-quiz')}
									</div>
									<Text type="secondary" style={{ fontSize: 14 }}>
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
									<ClockCircleOutlined />
									<span>{__('Time Limit', 'pressprimer-quiz')}</span>
									<Tooltip title={__('Set a time limit in minutes, or leave empty for unlimited time', 'pressprimer-quiz')}>
										<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
									</Tooltip>
								</Space>
							}
							name="time_limit_seconds"
						>
							<InputNumber
								min={1}
								max={1440}
								size="small"
								style={{ width: '100%' }}
								placeholder={__('Unlimited', 'pressprimer-quiz')}
								formatter={value => value ? Math.round(value / 60) : ''}
								parser={value => value ? value * 60 : null}
								addonAfter={__('min', 'pressprimer-quiz')}
							/>
						</Form.Item>
						<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: -8 }}>
							{__('Leave empty for no time limit', 'pressprimer-quiz')}
						</Text>
					</Col>
					<Col span={12}>
						<Form.Item
							label={
								<Space>
									<TrophyOutlined />
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
								step={0.01}
								size="small"
								style={{ width: '100%' }}
								addonAfter="%"
							/>
						</Form.Item>
					</Col>
				</Row>
			</Card>

			{/* Navigation & Attempts */}
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
										<Tooltip title={__('Students can go back to previous questions', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="allow_backward"
								valuePropName="checked"
								style={{ marginBottom: 8 }}
							>
								<Switch size="small" />
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
										<ReloadOutlined />
										<span>{__('Maximum Attempts', 'pressprimer-quiz')}</span>
										<Tooltip title={__('How many times a student can take this quiz (empty = unlimited)', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="max_attempts"
							>
								<InputNumber
									min={1}
									max={100}
									size="small"
									style={{ width: '100%' }}
									placeholder={__('Unlimited', 'pressprimer-quiz')}
								/>
							</Form.Item>
							<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: -8 }}>
								{__('Empty for unlimited attempts', 'pressprimer-quiz')}
							</Text>
							<Form.Item
								label={
									<Space>
										<ClockCircleOutlined />
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
									style={{ width: '100%' }}
									placeholder="0"
									addonAfter={__('min', 'pressprimer-quiz')}
								/>
							</Form.Item>
						</Space>
					</Col>
				</Row>
			</Card>

			{/* Display Options */}
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
										<Tooltip title={__('Shuffle the order of questions for each attempt', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="randomize_questions"
								valuePropName="checked"
								style={{ marginBottom: 8 }}
							>
								<Switch size="small" />
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
								style={{ marginBottom: 0 }}
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
										<Tooltip title={__('Show all questions on one page or paginate them', 'pressprimer-quiz')}>
											<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
										</Tooltip>
									</Space>
								}
								name="page_mode"
							>
								<Radio.Group size="small">
									<Radio value="single">{__('Single Page', 'pressprimer-quiz')}</Radio>
									<Radio value="paged">{__('Paginated', 'pressprimer-quiz')}</Radio>
								</Radio.Group>
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
									style={{ width: '100%' }}
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
									<EyeOutlined />
									<span>{__('Show Correct Answers', 'pressprimer-quiz')}</span>
									<Tooltip title={__('Control when students can see the correct answers', 'pressprimer-quiz')}>
										<QuestionCircleOutlined style={{ fontSize: 12, color: '#8c8c8c' }} />
									</Tooltip>
								</Space>
							}
							name="show_answers"
						>
							<Select
								size="small"
								options={[
									{ value: 'never', label: __('Never', 'pressprimer-quiz') },
									{ value: 'after_submit', label: __('After Submit', 'pressprimer-quiz') },
									{ value: 'after_pass', label: __('After Passing', 'pressprimer-quiz') },
								]}
							/>
						</Form.Item>
						<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: -8 }}>
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
						>
							<Switch size="small" />
						</Form.Item>
						<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: -8 }}>
							{__('Helps identify knowledge gaps', 'pressprimer-quiz')}
						</Text>
					</Col>
				</Row>
			</Card>

			{/* Access Control */}
			<Card
				title={
					<Space>
						<Title level={4} style={{ margin: 0 }}>
							{__('Access Control', 'pressprimer-quiz')}
						</Title>
						<Tooltip title={__('Control who can access this quiz', 'pressprimer-quiz')}>
							<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
						</Tooltip>
					</Space>
				}
				style={{ marginBottom: 24 }}
			>
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
				>
					<Select
						size="small"
						style={{ width: 300 }}
						options={[
							{ value: 'default', label: __('Use global default', 'pressprimer-quiz') },
							{ value: 'guest_optional', label: __('Allow guests (email optional)', 'pressprimer-quiz') },
							{ value: 'guest_required', label: __('Allow guests (email required)', 'pressprimer-quiz') },
							{ value: 'login_required', label: __('Require login', 'pressprimer-quiz') },
						]}
					/>
				</Form.Item>
				<Text type="secondary" style={{ fontSize: 10, display: 'block', marginTop: -8, marginBottom: 16 }}>
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
		</Space>
	);
};

export default SettingsPanel;
