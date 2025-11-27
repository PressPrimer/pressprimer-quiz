/**
 * Question Editor - Main Component
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
} from 'antd';
import {
	SaveOutlined,
	CloseOutlined,
	QuestionCircleOutlined,
} from '@ant-design/icons';

import QuestionTypeSelector from './QuestionTypeSelector';
import StemEditor from './StemEditor';
import AnswersList from './AnswersList';
import MetadataPanel from './MetadataPanel';
import TaxonomySelector from './TaxonomySelector';
import BankSelector from './BankSelector';
import FeedbackFields from './FeedbackFields';

const { Content, Sider } = Layout;
const { Title, Paragraph } = Typography;

/**
 * Main Question Editor Component
 *
 * @param {Object} props Component props
 * @param {Object} props.questionData Initial question data
 */
const QuestionEditor = ({ questionData = {} }) => {
	const [form] = Form.useForm();
	const [loading, setLoading] = useState(false);
	const [saving, setSaving] = useState(false);
	const [questionType, setQuestionType] = useState(questionData.type || 'mc');
	const [stem, setStem] = useState(questionData.stem || '');
	const [answers, setAnswers] = useState(questionData.answers || [
		{ id: 'a1', text: '', isCorrect: false, feedback: '', order: 1 },
		{ id: 'a2', text: '', isCorrect: false, feedback: '', order: 2 },
	]);

	const isNew = !questionData.id;

	// Initialize form with question data
	useEffect(() => {
		if (questionData.id) {
			console.log('Loading question data:', questionData);
			form.setFieldsValue({
				stem: questionData.stem || '',
				type: questionData.type || 'mc',
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

			// Prepare data for submission
			const questionPayload = {
				...values,
				type: questionType,
				stem: stem,
				answers: answers,
				id: questionData.id || null,
			};

			console.log('Submitting question payload:', questionPayload);

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

			// Redirect to questions list
			setTimeout(() => {
				window.location.href = `${window.ppqAdmin.adminUrl}admin.php?page=ppq-questions&saved=1`;
			}, 1000);

		} catch (error) {
			console.error('Save error:', error);
			message.error(error.message || __('Failed to save question.', 'pressprimer-quiz'));
		} finally {
			setSaving(false);
		}
	};

	/**
	 * Handle cancel
	 */
	const handleCancel = () => {
		if (window.confirm(__('Are you sure you want to cancel? Any unsaved changes will be lost.', 'pressprimer-quiz'))) {
			window.location.href = `${window.ppqAdmin.adminUrl}admin.php?page=ppq-questions`;
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

	return (
		<div className="ppq-question-editor-container">
			<Spin spinning={loading} tip={__('Loading question...', 'pressprimer-quiz')}>
				<Form
					form={form}
					layout="vertical"
					onFinish={handleSubmit}
					initialValues={{
						type: 'mc',
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
						</Space>
					</div>

					<Divider />

					{/* Main Layout */}
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
									console.log('StemEditor onChange received in QuestionEditor:', value);
									setStem(value);
									form.setFieldsValue({ stem: value });
								}}
							/>

							{/* Answers */}
							<AnswersList
								answers={answers}
								questionType={questionType}
								onChange={setAnswers}
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
									<BankSelector />
								</Form.Item>
							</Space>
						</Sider>
					</Layout>
				</Form>
			</Spin>
		</div>
	);
};

export default QuestionEditor;
