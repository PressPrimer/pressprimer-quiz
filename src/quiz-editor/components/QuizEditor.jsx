/**
 * Quiz Editor - Main Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
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
import QuestionsPanel from './QuestionsPanel';
import RulesPanel from './RulesPanel';
import FeedbackPanel from './FeedbackPanel';

const { Content } = Layout;
const { Title, Paragraph } = Typography;

/**
 * Main Quiz Editor Component
 *
 * @param {Object} props Component props
 * @param {Object} props.quizData Initial quiz data
 */
const QuizEditor = ({ quizData = {} }) => {
	const [form] = Form.useForm();
	const [loading, setLoading] = useState(false);
	const [saving, setSaving] = useState(false);
	const [generationMode, setGenerationMode] = useState(quizData.generation_mode || 'fixed');
	const [activeTab, setActiveTab] = useState('settings');
	const [currentQuizId, setCurrentQuizId] = useState(quizData.id || null);
	const [feedbackBands, setFeedbackBands] = useState([]);

	const isNew = !currentQuizId;

	// Initialize form with quiz data
	useEffect(() => {
		if (quizData.id) {
			console.log('Loading quiz data:', quizData);
			form.setFieldsValue({
				title: quizData.title || '',
				description: quizData.description || '',
				featured_image_id: quizData.featured_image_id || null,
				status: quizData.status || 'draft',
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
				enable_confidence: quizData.enable_confidence ?? false,
				theme: quizData.theme || 'default',
				max_attempts: quizData.max_attempts || null,
				attempt_delay_minutes: quizData.attempt_delay_minutes || 0,
				generation_mode: quizData.generation_mode || 'fixed',
			});

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
					console.error('Failed to parse feedback bands:', error);
				}
			}
		}
	}, [quizData, form]);

	/**
	 * Handle form submission
	 */
	const handleSubmit = async (values) => {
		try {
			setSaving(true);

			// Prepare data for submission
			const quizPayload = {
				...values,
				id: quizData.id || null,
				band_feedback_json: JSON.stringify(feedbackBands),
			};

			console.log('Submitting quiz payload:', quizPayload);

			// Submit via REST API
			const endpoint = quizData.id
				? `/ppq/v1/quizzes/${quizData.id}`
				: '/ppq/v1/quizzes';

			const method = quizData.id ? 'PUT' : 'POST';

			const response = await apiFetch({
				path: endpoint,
				method: method,
				data: quizPayload,
			});

			message.success(__('Quiz saved successfully!', 'pressprimer-quiz'));

			// Redirect to quizzes list
			setTimeout(() => {
				window.location.href = `${window.ppqAdmin.adminUrl}admin.php?page=ppq-quizzes&saved=1`;
			}, 1000);

		} catch (error) {
			console.error('Save error:', error);
			message.error(error.message || __('Failed to save quiz.', 'pressprimer-quiz'));
		} finally {
			setSaving(false);
		}
	};

	/**
	 * Handle cancel
	 */
	const handleCancel = () => {
		if (window.confirm(__('Are you sure you want to cancel? Any unsaved changes will be lost.', 'pressprimer-quiz'))) {
			window.location.href = `${window.ppqAdmin.adminUrl}admin.php?page=ppq-quizzes`;
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

			// Prepare data for submission
			const quizPayload = {
				...values,
				id: currentQuizId || null,
				band_feedback_json: JSON.stringify(feedbackBands),
			};

			console.log('Auto-saving quiz:', quizPayload);

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

			// Update current quiz ID if this was a new quiz
			if (!currentQuizId && response.id) {
				setCurrentQuizId(response.id);
				// Update URL without reload
				window.history.replaceState(
					{},
					'',
					`${window.ppqAdmin.adminUrl}admin.php?page=ppq-quizzes&action=edit&id=${response.id}`
				);
			}

			message.success(__('Quiz saved successfully!', 'pressprimer-quiz'));
			return true;

		} catch (error) {
			console.error('Auto-save error:', error);
			if (error.errorFields) {
				message.error(__('Please fill in all required fields in Settings.', 'pressprimer-quiz'));
			} else {
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
	};

	const tabItems = [
		{
			key: 'settings',
			label: __('Settings', 'pressprimer-quiz'),
			children: <SettingsPanel form={form} generationMode={generationMode} setGenerationMode={setGenerationMode} />,
		},
		{
			key: 'questions',
			label: generationMode === 'fixed' ? __('Questions', 'pressprimer-quiz') : __('Rules', 'pressprimer-quiz'),
			children: generationMode === 'fixed'
				? <QuestionsPanel quizId={currentQuizId} generationMode={generationMode} />
				: <RulesPanel quizId={currentQuizId} generationMode={generationMode} />,
		},
		{
			key: 'feedback',
			label: __('Feedback', 'pressprimer-quiz'),
			children: <FeedbackPanel value={feedbackBands} onChange={setFeedbackBands} />,
		},
	];

	return (
		<div className="ppq-quiz-editor-container">
			<Spin spinning={loading || saving} tip={saving ? __('Saving quiz...', 'pressprimer-quiz') : __('Loading quiz...', 'pressprimer-quiz')}>
				<Form
					form={form}
					layout="vertical"
					onFinish={handleSubmit}
					initialValues={{
						title: '',
						description: '',
						status: 'draft',
						mode: 'tutorial',
						pass_percent: 70,
						allow_skip: true,
						allow_backward: true,
						allow_resume: true,
						randomize_questions: false,
						randomize_answers: false,
						page_mode: 'single',
						questions_per_page: 1,
						show_answers: 'after_submit',
						enable_confidence: false,
						theme: 'default',
						generation_mode: 'fixed',
						attempt_delay_minutes: 0,
					}}
				>
					{/* Header */}
					<div className="ppq-editor-header">
						<Space direction="vertical" style={{ width: '100%' }}>
							<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
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
										<Paragraph style={{ marginBottom: 8 }}>
											{__('Create engaging quizzes for your students. Configure settings, add questions or rules, and customize the experience.', 'pressprimer-quiz')}
										</Paragraph>
										<Paragraph style={{ marginBottom: 0 }}>
											<strong>{__('Pro Tip:', 'pressprimer-quiz')}</strong> {__('Use Fixed mode to select specific questions (same for everyone), or Dynamic mode with rules to randomly select from question banks (different for each student).', 'pressprimer-quiz')}
										</Paragraph>
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
	);
};

export default QuizEditor;
