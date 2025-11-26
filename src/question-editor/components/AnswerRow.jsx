/**
 * Answer Row Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Card,
	Radio,
	Checkbox,
	Button,
	Space,
	Typography,
	Collapse,
	Tooltip,
} from 'antd';
import {
	HolderOutlined,
	DeleteOutlined,
	FormOutlined,
	CheckCircleOutlined,
} from '@ant-design/icons';
import RichTextEditor from './RichTextEditor';

const { Text } = Typography;
const { Panel } = Collapse;

const AnswerRow = ({
	answer,
	index,
	questionType,
	canRemove,
	dragHandleProps,
	onUpdate,
	onRemove,
}) => {
	const [feedbackExpanded, setFeedbackExpanded] = useState(!!answer.feedback);
	const maxChars = 2000;

	const CorrectControl = questionType === 'ma' ? Checkbox : Radio;

	return (
		<Card
			size="small"
			style={{
				backgroundColor: answer.isCorrect ? '#f6ffed' : '#fafafa',
				border: answer.isCorrect ? '1px solid #b7eb8f' : '1px solid #d9d9d9',
			}}
		>
			<Space direction="vertical" style={{ width: '100%' }} size="small">
				{/* Header */}
				<div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
					{/* Drag Handle */}
					{questionType !== 'tf' && (
						<div {...dragHandleProps} style={{ cursor: 'grab' }}>
							<Tooltip title={__('Drag to reorder', 'pressprimer-quiz')}>
								<HolderOutlined style={{ fontSize: 16, color: '#8c8c8c' }} />
							</Tooltip>
						</div>
					)}

					{/* Answer Number */}
					<div
						style={{
							width: 28,
							height: 28,
							borderRadius: '50%',
							backgroundColor: answer.isCorrect ? '#52c41a' : '#d9d9d9',
							color: '#fff',
							display: 'flex',
							alignItems: 'center',
							justifyContent: 'center',
							fontWeight: 600,
						}}
					>
						{index + 1}
					</div>

					{/* Correct Toggle */}
					<Tooltip title={__('Mark this answer as correct', 'pressprimer-quiz')}>
						<CorrectControl
							checked={answer.isCorrect}
							onChange={(e) => onUpdate({ isCorrect: e.target.checked })}
							style={{ fontWeight: 600 }}
						>
							{answer.isCorrect && <CheckCircleOutlined style={{ color: '#52c41a', marginRight: 4 }} />}
							{__('Correct', 'pressprimer-quiz')}
						</CorrectControl>
					</Tooltip>

					{/* Spacer */}
					<div style={{ flex: 1 }} />

					{/* Remove Button */}
					<Tooltip title={canRemove ? __('Remove this answer', 'pressprimer-quiz') : __('At least 2 answers required', 'pressprimer-quiz')}>
						<Button
							type="text"
							danger
							icon={<DeleteOutlined />}
							onClick={onRemove}
							disabled={!canRemove}
						>
							{__('Remove', 'pressprimer-quiz')}
						</Button>
					</Tooltip>
				</div>

				{/* Answer Text */}
				<RichTextEditor
					value={answer.text}
					onChange={(content) => onUpdate({ text: content })}
					placeholder={__('Enter answer text...', 'pressprimer-quiz')}
					maxChars={maxChars}
					rows={3}
				/>

				{/* Feedback Section */}
				<Collapse
					activeKey={feedbackExpanded ? ['feedback'] : []}
					onChange={(keys) => setFeedbackExpanded(keys.includes('feedback'))}
					ghost
					expandIconPosition="end"
				>
					<Panel
						key="feedback"
						header={
							<Space>
								<FormOutlined />
								<Text>
									{answer.feedback
										? __('Edit feedback for this answer', 'pressprimer-quiz')
										: __('Add feedback for this answer', 'pressprimer-quiz')
									}
								</Text>
							</Space>
						}
					>
						<Space direction="vertical" style={{ width: '100%' }} size="small">
							<Text type="secondary" style={{ fontSize: 13 }}>
								{__('Provide explanation about why this answer is correct or incorrect (optional)', 'pressprimer-quiz')}
							</Text>
							<RichTextEditor
								value={answer.feedback}
								onChange={(content) => onUpdate({ feedback: content })}
								placeholder={__('Explain why this answer is correct or incorrect...', 'pressprimer-quiz')}
								maxChars={maxChars}
								rows={2}
							/>
						</Space>
					</Panel>
				</Collapse>
			</Space>
		</Card>
	);
};

export default AnswerRow;
