/**
 * Answers List Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { Card, Button, Typography, Space, Tooltip, Alert } from 'antd';
import { PlusOutlined, QuestionCircleOutlined } from '@ant-design/icons';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';
import AnswerRow from './AnswerRow';

const { Title, Text } = Typography;

const AnswersList = ({ answers, questionType, onChange }) => {
	const maxAnswers = questionType === 'tf' ? 2 : 8;
	const minAnswers = 2;

	/**
	 * Handle drag end
	 */
	const handleDragEnd = (result) => {
		if (!result.destination) return;

		const items = Array.from(answers);
		const [reorderedItem] = items.splice(result.source.index, 1);
		items.splice(result.destination.index, 0, reorderedItem);

		// Update order field
		const reordered = items.map((item, index) => ({
			...item,
			order: index + 1,
		}));

		onChange(reordered);
	};

	/**
	 * Add new answer
	 */
	const handleAddAnswer = () => {
		if (answers.length >= maxAnswers) return;

		const newAnswer = {
			id: `a${Date.now()}`,
			text: '',
			isCorrect: false,
			feedback: '',
			order: answers.length + 1,
		};

		onChange([...answers, newAnswer]);
	};

	/**
	 * Remove answer
	 */
	const handleRemoveAnswer = (id) => {
		if (answers.length <= minAnswers) return;

		const filtered = answers.filter(a => a.id !== id);
		const reordered = filtered.map((item, index) => ({
			...item,
			order: index + 1,
		}));

		onChange(reordered);
	};

	/**
	 * Update answer
	 */
	const handleUpdateAnswer = (id, updates) => {
		const updated = answers.map(a =>
			a.id === id ? { ...a, ...updates } : a
		);

		// For MC and TF, uncheck other answers when one is marked correct
		if ((questionType === 'mc' || questionType === 'tf') && updates.isCorrect) {
			updated.forEach(a => {
				if (a.id !== id) {
					a.isCorrect = false;
				}
			});
		}

		onChange(updated);
	};

	return (
		<Card
			title={
				<Space>
					<Title level={4} style={{ margin: 0 }}>
						{__('Answer Options', 'pressprimer-quiz')} <span style={{ color: '#ff4d4f' }}>*</span>
					</Title>
					<Tooltip title={__('Add answer choices for students to select. Mark correct answers and optionally add feedback for each option.', 'pressprimer-quiz')}>
						<QuestionCircleOutlined style={{ color: '#8c8c8c' }} />
					</Tooltip>
				</Space>
			}
			extra={
				<Text type="secondary">
					{answers.length} / {maxAnswers} {__('answers', 'pressprimer-quiz')}
				</Text>
			}
			style={{ marginBottom: 24 }}
		>
			<Space direction="vertical" style={{ width: '100%' }} size="middle">
				{questionType === 'tf' && (
					<Alert
						message={__('True/False questions have exactly 2 answer options.', 'pressprimer-quiz')}
						type="info"
						showIcon
					/>
				)}

				<DragDropContext onDragEnd={handleDragEnd}>
					<Droppable droppableId="answers">
						{(provided, snapshot) => (
							<div
								{...provided.droppableProps}
								ref={provided.innerRef}
								style={{
									backgroundColor: snapshot.isDraggingOver ? '#f0f5ff' : 'transparent',
									padding: snapshot.isDraggingOver ? 8 : 0,
									borderRadius: 4,
									transition: 'all 0.2s',
								}}
							>
								<Space direction="vertical" style={{ width: '100%' }} size="middle">
									{answers.map((answer, index) => (
										<Draggable
											key={answer.id}
											draggableId={answer.id}
											index={index}
											isDragDisabled={questionType === 'tf'}
										>
											{(provided, snapshot) => (
												<div
													ref={provided.innerRef}
													{...provided.draggableProps}
													style={{
														...provided.draggableProps.style,
														opacity: snapshot.isDragging ? 0.8 : 1,
													}}
												>
													<AnswerRow
														answer={answer}
														index={index}
														questionType={questionType}
														canRemove={answers.length > minAnswers}
														dragHandleProps={provided.dragHandleProps}
														onUpdate={(updates) => handleUpdateAnswer(answer.id, updates)}
														onRemove={() => handleRemoveAnswer(answer.id)}
													/>
												</div>
											)}
										</Draggable>
									))}
									{provided.placeholder}
								</Space>
							</div>
						)}
					</Droppable>
				</DragDropContext>

				{questionType !== 'tf' && answers.length < maxAnswers && (
					<Button
						type="dashed"
						icon={<PlusOutlined />}
						onClick={handleAddAnswer}
						block
						size="large"
					>
						{__('Add Answer Option', 'pressprimer-quiz')}
					</Button>
				)}
			</Space>
		</Card>
	);
};

export default AnswersList;
