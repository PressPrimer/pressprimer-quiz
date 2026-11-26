/**
 * Quiz Questions Panel Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { debugError } from '../../utils/debug';
import {
	Table,
	Button,
	message,
	Space,
	Popconfirm,
	Empty,
	Typography,
	Card,
	Tooltip,
} from 'antd';
import {
	PlusOutlined,
	DeleteOutlined,
	EditOutlined,
	HolderOutlined,
} from '@ant-design/icons';
import { DragDropContext, Droppable, Draggable } from '@hello-pangea/dnd';
import { useEditorContext } from '../../editors/EditorContext';
import { emitEditorEvent, toId } from '../../editors/events';
import QuestionPickerModal from '../../editors/QuestionPickerModal';

const { Text } = Typography;

/**
 * Questions Panel Component
 *
 * @param {Object} props Component props
 * @param {number} props.quizId Quiz ID
 * @param {string} props.generationMode Generation mode (fixed/dynamic)
 */
const QuestionsPanel = ({ quizId, generationMode }) => {
	const { nav } = useEditorContext();
	const [items, setItems] = useState([]);
	const [loading, setLoading] = useState(false);
	const [modalVisible, setModalVisible] = useState(false);
	// A new picker for each opening, so it starts from its defaults.
	const [pickerKey, setPickerKey] = useState(0);
	const [totalPoints, setTotalPoints] = useState(0);

	// Tracks the in-flight reorder request so reloads can wait for it to commit.
	const pendingReorderRef = useRef(null);

	// Load quiz items
	useEffect(() => {
		if (quizId && generationMode === 'fixed') {
			loadQuizItems();
		}
	}, [quizId, generationMode]);

	// Calculate total points from question max_points
	useEffect(() => {
		const total = items
			.filter(item => item && item.max_points !== undefined)
			.reduce((sum, item) => sum + (parseFloat(item.max_points) || 0), 0);
		setTotalPoints(total);
	}, [items]);

	/**
	 * Load quiz items
	 *
	 * @return {Promise<Array|null>} The loaded items, or null when loading failed.
	 */
	const loadQuizItems = async () => {
		try {
			setLoading(true);
			// Wait for any in-flight reorder to commit so we never reload a stale
			// order (e.g. right after adding or removing a question).
			if (pendingReorderRef.current) {
				try {
					await pendingReorderRef.current;
				} catch (error) {
					// A failed reorder surfaces its own error; reload regardless.
				}
			}
			const response = await apiFetch({
				path: `/ppq/v1/quizzes/${quizId}/items`,
			});
			setItems(response || []);
			return response || [];
		} catch (error) {
			// Failed to load - items will be empty
			return null;
		} finally {
			setLoading(false);
		}
	};

	/**
	 * Announce a change to the quiz's questions (ppq-quiz-items-changed).
	 *
	 * @param {string} action 'added', 'removed' or 'reordered'.
	 * @param {number} count  How many questions the quiz has now.
	 */
	const notifyItemsChanged = (action, count) => {
		emitEditorEvent('ppq-quiz-items-changed', { quizId: toId(quizId), count, action });
	};

	/**
	 * Open add questions modal
	 */
	const handleAddQuestions = () => {
		setPickerKey((key) => key + 1);
		setModalVisible(true);
	};

	/**
	 * Add selected questions
	 *
	 * @param {number[]} selectedQuestionIds IDs picked in the dialog.
	 */
	const handleAddSelected = async (selectedQuestionIds) => {
		try {
			await apiFetch({
				path: `/ppq/v1/quizzes/${quizId}/items`,
				method: 'POST',
				data: {
					question_ids: selectedQuestionIds,
				},
			});

			message.success(__('Questions added successfully', 'pressprimer-quiz'));
			setModalVisible(false);

			// The count comes from the reloaded list (questions already in the
			// quiz are skipped).
			const loaded = await loadQuizItems();
			notifyItemsChanged('added', loaded ? loaded.length : items.length + selectedQuestionIds.length);
		} catch (error) {
			debugError('Failed to add questions:', error);
			message.error(__('Failed to add questions', 'pressprimer-quiz'));
		}
	};

	/**
	 * Remove question from quiz
	 */
	const handleRemove = async (itemId) => {
		try {
			await apiFetch({
				path: `/ppq/v1/quizzes/${quizId}/items/${itemId}`,
				method: 'DELETE',
			});

			message.success(__('Question removed', 'pressprimer-quiz'));
			const loaded = await loadQuizItems();
			notifyItemsChanged('removed', loaded ? loaded.length : Math.max(0, items.length - 1));
		} catch (error) {
			message.error(__('Failed to remove question', 'pressprimer-quiz'));
		}
	};

	/**
	 * Handle drag end
	 */
	const handleDragEnd = async (result) => {
		// Always clear any drag state, even if we don't process the drop
		if (!result.destination) {
			// User dropped outside the list or cancelled
			return;
		}

		// Don't process if source and destination are the same
		if (result.source.index === result.destination.index) {
			return;
		}

		// Validate items array and indices
		if (!items || items.length === 0) {
			return;
		}

		if (result.source.index >= items.length || result.destination.index >= items.length) {
			return;
		}

		const reorderedItems = Array.from(items);
		const [removed] = reorderedItems.splice(result.source.index, 1);

		// Validate removed item
		if (!removed || !removed.id) {
			return;
		}

		reorderedItems.splice(result.destination.index, 0, removed);

		// Optimistically update UI - this triggers re-render which clears drag state
		setItems(reorderedItems);

		// Persist the new order. Track the request so a subsequent reload (e.g.
		// after adding or removing a question) waits for it, and await it so rapid
		// successive reorders can't commit out of order.
		const reorderPromise = apiFetch({
			path: `/ppq/v1/quizzes/${quizId}/items/reorder`,
			method: 'POST',
			data: {
				item_ids: reorderedItems.map(item => item.id),
			},
		});
		pendingReorderRef.current = reorderPromise;

		try {
			await reorderPromise;
			notifyItemsChanged('reordered', reorderedItems.length);
		} catch (error) {
			message.error(__('Failed to save order', 'pressprimer-quiz'));
			// Reload to reset
			loadQuizItems();
		}
	};

	if (generationMode !== 'fixed') {
		return (
			<Card>
				<Empty
					description={__('Dynamic mode selected. Switch to Settings tab to change generation mode.', 'pressprimer-quiz')}
				/>
			</Card>
		);
	}

	if (!quizId) {
		return (
			<Card>
				<Empty
					description={__('Please save the quiz first before adding questions.', 'pressprimer-quiz')}
				/>
			</Card>
		);
	}

	const columns = [
		{
			title: '',
			key: 'drag',
			width: 40,
			className: 'drag-handle-cell',
			render: () => (
				<div style={{ cursor: 'grab', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
					<HolderOutlined style={{ color: '#999', fontSize: 16 }} />
				</div>
			),
		},
		{
			title: __('Question', 'pressprimer-quiz'),
			dataIndex: 'question_stem',
			key: 'question',
			render: (text) => <Text>{text}</Text>,
		},
		{
			title: __('Type', 'pressprimer-quiz'),
			dataIndex: 'question_type',
			key: 'type',
			width: 100,
			render: (type) => type ? type.toUpperCase() : '',
		},
		{
			title: __('Points', 'pressprimer-quiz'),
			dataIndex: 'max_points',
			key: 'max_points',
			width: 100,
			render: (maxPoints) => (
				<Text>{parseFloat(maxPoints || 0).toFixed(2)}</Text>
			),
		},
		{
			title: __('Actions', 'pressprimer-quiz'),
			key: 'actions',
			width: 100,
			render: (_, record) => (
				<Space size="small">
					{/* A host without question URLs (editUrl returns '') shows no edit link. */}
					{nav.editUrl('question', record.question_id) && (
						<Tooltip title={__('Edit question', 'pressprimer-quiz')}>
							<Button
								type="text"
								icon={<EditOutlined />}
								onClick={() => {
									window.open(nav.editUrl('question', record.question_id), '_blank');
								}}
							/>
						</Tooltip>
					)}
					<Popconfirm
						title={__('Remove this question?', 'pressprimer-quiz')}
						onConfirm={() => handleRemove(record.id)}
						okText={__('Yes', 'pressprimer-quiz')}
						cancelText={__('No', 'pressprimer-quiz')}
					>
						<Tooltip title={__('Remove from quiz', 'pressprimer-quiz')}>
							<Button
								type="text"
								danger
								icon={<DeleteOutlined />}
							/>
						</Tooltip>
					</Popconfirm>
				</Space>
			),
		},
	];

	return (
		<Card>
			<Space direction="vertical" style={{ width: '100%' }} size="large">
				<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
					<div>
						<Text strong>{__('Questions:', 'pressprimer-quiz')} {items.length}</Text>
						<Text style={{ marginLeft: 16 }}>{__('Total Points:', 'pressprimer-quiz')} {totalPoints.toFixed(2)}</Text>
					</div>
					<Button
						type="primary"
						className="ppq-add-questions-button"
						icon={<PlusOutlined />}
						onClick={handleAddQuestions}
					>
						{__('Add Questions', 'pressprimer-quiz')}
					</Button>
				</div>

				<DragDropContext onDragEnd={handleDragEnd}>
					<Droppable droppableId="questions">
						{(provided) => (
							<Table
								key={items.map(i => i.id).join('-')}
								loading={loading}
								dataSource={items}
								columns={columns}
								rowKey="id"
								pagination={false}
								locale={{
									emptyText: (
										<div style={{ padding: '40px 20px', textAlign: 'center', maxWidth: 600, margin: '0 auto' }}>
											<span style={{ color: 'rgba(0, 0, 0, 0.45)', fontSize: 14 }}>
												{__('No questions added yet. Click "Add Questions" to get started.', 'pressprimer-quiz')}
											</span>
										</div>
									),
								}}
								components={{
									body: {
										wrapper: (props) => (
											<tbody
												{...props}
												{...provided.droppableProps}
												ref={provided.innerRef}
											>
												{props.children}
												{provided.placeholder}
											</tbody>
										),
										row: ({ children, ...props }) => {
											const index = props['data-row-key'];
											const itemIndex = items.findIndex(item => String(item.id) === String(index));

											if (itemIndex === -1) {
												return <tr {...props}>{children}</tr>;
											}

											return (
												<Draggable
													draggableId={String(index)}
													index={itemIndex}
												>
													{(provided, snapshot) => {
														// Build the style object properly
														const baseStyle = provided.draggableProps.style || {};
														const customStyle = {
															background: snapshot.isDragging ? '#e6f7ff' : 'inherit',
															boxShadow: snapshot.isDragging ? '0 8px 16px rgba(0,0,0,0.2)' : 'none',
															cursor: snapshot.isDragging ? 'grabbing' : 'inherit',
															opacity: snapshot.isDragging ? 0.9 : 1,
														};

														// Don't add transition while dragging to avoid conflicts
														if (!snapshot.isDragging) {
															customStyle.transition = 'background 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease';
														}

														return (
															<tr
																ref={provided.innerRef}
																{...provided.draggableProps}
																{...provided.dragHandleProps}
																{...props}
																style={{
																	...baseStyle,
																	...customStyle,
																}}
															>
																{children}
															</tr>
														);
													}}
												</Draggable>
											);
										},
									},
								}}
							/>
						)}
					</Droppable>
				</DragDropContext>
			</Space>

			{/* Add Questions Modal */}
			<QuestionPickerModal
				key={pickerKey}
				open={modalVisible}
				onCancel={() => setModalVisible(false)}
				onAdd={handleAddSelected}
				query={{ exclude: items.map(item => item.question_id).filter(Boolean).join(',') }}
			/>
		</Card>
	);
};

export default QuestionsPanel;
