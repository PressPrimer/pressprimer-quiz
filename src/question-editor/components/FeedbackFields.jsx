/**
 * Feedback Fields Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, Form, Input, Typography, Space, Tooltip, Progress } from 'antd';
import {
	QuestionCircleOutlined,
	CheckCircleOutlined,
	CloseCircleOutlined,
} from '@ant-design/icons';

const { TextArea } = Input;
const { Title, Text } = Typography;

const FeedbackFields = ({ form }) => {
	const maxChars = 2000;

	const [correctLength, setCorrectLength] = useState(0);
	const [incorrectLength, setIncorrectLength] = useState(0);

	// Initialize character counts when form values are available
	useEffect(() => {
		const correctValue = form.getFieldValue('feedbackCorrect');
		const incorrectValue = form.getFieldValue('feedbackIncorrect');
		if (correctValue) {
			setCorrectLength(correctValue.length);
		}
		if (incorrectValue) {
			setIncorrectLength(incorrectValue.length);
		}
	}, [form]);

	const correctPercent = (correctLength / maxChars) * 100;
	const incorrectPercent = (incorrectLength / maxChars) * 100;

	return (
		<Card
			title={
				<Space>
					<Title level={4} style={{ margin: 0 }}>
						{__('General Feedback', 'pressprimer-quiz')}
					</Title>
					<Tooltip title={__('Provide feedback that students will see after answering, regardless of which specific answer they chose.', 'pressprimer-quiz')}>
						<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
					</Tooltip>
				</Space>
			}
			style={{ marginBottom: 24 }}
		>
			<Space direction="vertical" style={{ width: '100%' }} size="large">
				{/* Correct Feedback */}
				<div>
					<Form.Item
						name="feedbackCorrect"
						label={
							<Space>
								<CheckCircleOutlined style={{ color: '#52c41a' }} />
								<span>{__('Feedback for Correct Answers', 'pressprimer-quiz')}</span>
							</Space>
						}
					>
						<TextArea
							placeholder={__('Congratulations! Here\'s why this is correct...', 'pressprimer-quiz')}
							autoSize={{ minRows: 3, maxRows: 6 }}
							maxLength={maxChars}
							showCount={false}
							onChange={(e) => setCorrectLength(e.target.value.length)}
						/>
					</Form.Item>
					<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: -16 }}>
						<Text type="secondary" style={{ fontSize: 12 }}>
							{__('This message appears when students answer correctly', 'pressprimer-quiz')}
						</Text>
						<Space size={8} align="center">
							<Text type="secondary" style={{ fontSize: 12 }}>
								{correctLength.toLocaleString()} / {maxChars.toLocaleString()}
							</Text>
							<Progress
								type="circle"
								percent={correctPercent}
								strokeColor={correctPercent > 90 ? '#ff4d4f' : '#52c41a'}
								width={24}
								format={() => ''}
							/>
						</Space>
					</div>
				</div>

				{/* Incorrect Feedback */}
				<div>
					<Form.Item
						name="feedbackIncorrect"
						label={
							<Space>
								<CloseCircleOutlined style={{ color: '#ff4d4f' }} />
								<span>{__('Feedback for Incorrect Answers', 'pressprimer-quiz')}</span>
							</Space>
						}
					>
						<TextArea
							placeholder={__('Not quite. Let me explain the correct answer...', 'pressprimer-quiz')}
							autoSize={{ minRows: 3, maxRows: 6 }}
							maxLength={maxChars}
							showCount={false}
							onChange={(e) => setIncorrectLength(e.target.value.length)}
						/>
					</Form.Item>
					<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: -16 }}>
						<Text type="secondary" style={{ fontSize: 12 }}>
							{__('This message appears when students answer incorrectly', 'pressprimer-quiz')}
						</Text>
						<Space size={8} align="center">
							<Text type="secondary" style={{ fontSize: 12 }}>
								{incorrectLength.toLocaleString()} / {maxChars.toLocaleString()}
							</Text>
							<Progress
								type="circle"
								percent={incorrectPercent}
								strokeColor={incorrectPercent > 90 ? '#ff4d4f' : '#52c41a'}
								width={24}
								format={() => ''}
							/>
						</Space>
					</div>
				</div>

				<Text type="secondary" style={{ fontSize: 12, display: 'block' }}>
					{__('💡 Tip: Good feedback helps students learn from their mistakes and reinforces correct understanding.', 'pressprimer-quiz')}
				</Text>
			</Space>
		</Card>
	);
};

export default FeedbackFields;
