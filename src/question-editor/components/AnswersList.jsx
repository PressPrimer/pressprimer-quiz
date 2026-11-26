/**
 * Answers List Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, Button, Typography, Space, Tooltip, Alert } from 'antd';
import { PlusOutlined, QuestionCircleOutlined } from '@ant-design/icons';
import { DragDropContext, Droppable, Draggable } from '@hello-pangea/dnd';
import AnswerRow from './AnswerRow';
import { useEditorContext } from '../../editors/EditorContext';

const { Title, Text } = Typography;

/**
 * How many answers a question type allows.
 *
 * @param {string} questionType Question type.
 * @return {number} Maximum answers.
 */
export const getMaxAnswers = (questionType) => (questionType === 'tf' ? 2 : 8);

/**
 * The answers state addons read: the ppq-answers-changed event's detail,
 * window._ppqAnswersState, and the answersState slot prop.
 *
 * @param {Object} args              Arguments.
 * @param {number} args.questionId   Question ID (0 for a new question).
 * @param {string} args.questionType Question type.
 * @param {Array}  args.answers      Answers.
 * @return {Object} { questionId, questionType, answers, answerCount, maxAnswers }.
 */
export const buildAnswersState = ({ questionId, questionType, answers }) => ({
	questionId: questionId || 0,
	questionType,
	answers,
	answerCount: answers.length,
	maxAnswers: getMaxAnswers(questionType),
});

/**
 * Answers List
 *
 * @param {Object}      props                   Component props.
 * @param {Array}       props.answers           Answers.
 * @param {string}      props.questionType      Question type.
 * @param {number}      props.questionId        Question ID (0 for new).
 * @param {Function}    props.onChange          Called with the new answers.
 * @param {Object}      props.feedbackRevisions Per-answer counters; a change re-initialises that answer's feedback editor.
 * @param {JSX.Element} props.addonActions      question.answers.actions slots.
 * @return {JSX.Element} The list.
 */
const AnswersList = ({ answers, questionType, questionId, onChange, feedbackRevisions = {}, addonActions = null }) => {
	const { surface } = useEditorContext();
	const maxAnswers = getMaxAnswers(questionType);
	const minAnswers = 2;

	// Bumped after each reorder to force the answer editors to re-initialize.
	// TinyMCE loses its displayed content when @hello-pangea/dnd moves its DOM,
	// so remounting the editors (via a key) restores them from state after a drag.
	const [dragRevision, setDragRevision] = useState(0);

	/**
	 * Notify addons when answers change.
	 *
	 * Dispatches a CustomEvent with current answer state so addons
	 * (e.g., AI distractor generation) can update their UI.
	 *
	 * @since 2.2.0
	 */
	useEffect( () => {
		const detail = buildAnswersState( { questionId, questionType, answers } );

		// Cache latest state so late-mounting addons can read it immediately.
		window._ppqAnswersState = detail;

		document.dispatchEvent(
			new CustomEvent( 'ppq-answers-changed', { detail } )
		);
	}, [ answers, questionType, questionId, maxAnswers ] );

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
		// Remount the answer editors so they re-sync from state after the DOM move.
		setDragRevision((r) => r + 1);
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
													dragRevision={dragRevision}
													feedbackRevision={feedbackRevisions[answer.id] || 0}
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

				{/* Legacy addon mount point for answer-related actions (e.g., AI
				    distractor generation): wp-admin, non-TF only. @since 2.2.0 */}
				{'admin' === surface && questionType !== 'tf' && (
					<div id="ppq-answers-addon-actions" />
				)}

				{/* question.answers.actions slots, for every question type. */}
				{addonActions}
			</Space>
		</Card>
	);
};

export default AnswersList;
