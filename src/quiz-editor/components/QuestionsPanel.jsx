/**
 * Quiz Questions Panel Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Table,
	Button,
	Modal,
	message,
	Space,
	InputNumber,
	Popconfirm,
	Empty,
	Typography,
	Card,
} from 'antd';
import {
	PlusOutlined,
	DeleteOutlined,
	HolderOutlined,
} from '@ant-design/icons';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';

const { Text } = Typography;

/**
 * Questions Panel Component
 *
 * @param {Object} props Component props
 * @param {number} props.quizId Quiz ID
 * @param {string} props.generationMode Generation mode (fixed/dynamic)
 */
const QuestionsPanel = ({ quizId, generationMode }) => {
	const [items, setItems] = useState([]);
	const [loading, setLoading] = useState(false);
	const [modalVisible, setModalVisible] = useState(false);
	const [availableQuestions, setAvailableQuestions] = useState([]);
	const [selectedQuestionIds, setSelectedQuestionIds] = useState([]);
	const [totalPoints, setTotalPoints] = useState(0);

	// Load quiz items
	useEffect(() => {
		if (quizId && generationMode === 'fixed') {
			loadQuizItems();
		}
	}, [quizId, generationMode]);

	// Calculate total points
	useEffect(() => {
		const total = items.reduce((sum, item) => sum + (parseFloat(item.weight) || 0), 0);
		setTotalPoints(total);
	}, [items]);

	/**
	 * Load quiz items
	 */
	const loadQuizItems = async () => {
		try {
			setLoading(true);
			const response = await apiFetch({
				path: `/ppq/v1/quizzes/${quizId}/items`,
			});
			setItems(response || []);
		} catch (error) {
			console.error('Failed to load quiz items:', error);
		} finally {
			setLoading(false);
		}
	};

	/**
	 * Load available questions for modal
	 */
	const loadAvailableQuestions = async () => {
		try {
			const response = await apiFetch({
				path: `/ppq/v1/questions?per_page=100&status=published`,
			});

			// Filter out questions already in quiz
			const existingQuestionIds = items.map(item => item.question_id);
			const filtered = response.filter(q => !existingQuestionIds.includes(q.id));

			setAvailableQuestions(filtered);
		} catch (error) {
			console.error('Failed to load questions:', error);
			message.error(__('Failed to load questions', 'pressprimer-quiz'));
		}
	};

	/**
	 * Open add questions modal
	 */
	const handleAddQuestions = () => {
		loadAvailableQuestions();
		setModalVisible(true);
		setSelectedQuestionIds([]);
	};

	/**
	 * Add selected questions
	 */
	const handleAddSelected = async () => {
		if (selectedQuestionIds.length === 0) {
			message.warning(__('Please select at least one question', 'pressprimer-quiz'));
			return;
		}

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
			loadQuizItems();
		} catch (error) {
			console.error('Failed to add questions:', error);
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
			loadQuizItems();
		} catch (error) {
			console.error('Failed to remove question:', error);
			message.error(__('Failed to remove question', 'pressprimer-quiz'));
		}
	};

	/**
	 * Update item weight
	 */
	const handleWeightChange = async (itemId, newWeight) => {
		try {
			await apiFetch({
				path: `/ppq/v1/quizzes/${quizId}/items/${itemId}`,
				method: 'PUT',
				data: {
					weight: newWeight,
				},
			});

			// Update local state
			setItems(items.map(item =>
				item.id === itemId ? { ...item, weight: newWeight } : item
			));
		} catch (error) {
			console.error('Failed to update weight:', error);
			message.error(__('Failed to update weight', 'pressprimer-quiz'));
		}
	};

	/**
	 * Handle drag end
	 */
	const handleDragEnd = async (result) => {
		if (!result.destination) {
			return;
		}

		const reorderedItems = Array.from(items);
		const [removed] = reorderedItems.splice(result.source.index, 1);
		reorderedItems.splice(result.destination.index, 0, removed);

		setItems(reorderedItems);

		// Save new order to backend
		try {
			await apiFetch({
				path: `/ppq/v1/quizzes/${quizId}/items/reorder`,
				method: 'POST',
				data: {
					item_ids: reorderedItems.map(item => item.id),
				},
			});
		} catch (error) {
			console.error('Failed to reorder items:', error);
			message.error(__('Failed to save order', 'pressprimer-quiz'));
			loadQuizItems(); // Reload to reset
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
			render: () => <HolderOutlined style={{ cursor: 'move', color: '#999' }} />,
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
			title: __('Weight', 'pressprimer-quiz'),
			dataIndex: 'weight',
			key: 'weight',
			width: 120,
			render: (weight, record) => (
				<InputNumber
					min={0}
					max={100}
					step={0.01}
					value={weight}
					onChange={(value) => handleWeightChange(record.id, value)}
					style={{ width: '100%' }}
				/>
			),
		},
		{
			title: __('Actions', 'pressprimer-quiz'),
			key: 'actions',
			width: 100,
			render: (_, record) => (
				<Popconfirm
					title={__('Remove this question?', 'pressprimer-quiz')}
					onConfirm={() => handleRemove(record.id)}
					okText={__('Yes', 'pressprimer-quiz')}
					cancelText={__('No', 'pressprimer-quiz')}
				>
					<Button
						type="text"
						danger
						icon={<DeleteOutlined />}
					/>
				</Popconfirm>
			),
		},
	];

	const questionColumns = [
		{
			title: __('Question', 'pressprimer-quiz'),
			dataIndex: 'stem',
			key: 'stem',
			render: (text) => <Text ellipsis>{text}</Text>,
		},
		{
			title: __('Type', 'pressprimer-quiz'),
			dataIndex: 'type',
			key: 'type',
			width: 80,
			render: (type) => type.toUpperCase(),
		},
	];

	const rowSelection = {
		selectedRowKeys: selectedQuestionIds,
		onChange: (selectedKeys) => {
			setSelectedQuestionIds(selectedKeys);
		},
	};

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
						icon={<PlusOutlined />}
						onClick={handleAddQuestions}
					>
						{__('Add Questions', 'pressprimer-quiz')}
					</Button>
				</div>

				<DragDropContext onDragEnd={handleDragEnd}>
					<Droppable droppableId="questions">
						{(provided) => (
							<div {...provided.droppableProps} ref={provided.innerRef}>
								<Table
									loading={loading}
									dataSource={items}
									columns={columns}
									rowKey="id"
									pagination={false}
									locale={{
										emptyText: __('No questions added yet. Click "Add Questions" to get started.', 'pressprimer-quiz'),
									}}
									components={{
										body: {
											wrapper: ({ children }) => (
												<tbody>
													{children.map((child, index) => (
														<Draggable
															key={child.key}
															draggableId={String(child.key)}
															index={index}
														>
															{(provided) => (
																<tr
																	ref={provided.innerRef}
																	{...provided.draggableProps}
																	{...provided.dragHandleProps}
																>
																	{child}
																</tr>
															)}
														</Draggable>
													))}
													{provided.placeholder}
												</tbody>
											),
										},
									}}
								/>
							</div>
						)}
					</Droppable>
				</DragDropContext>
			</Space>

			{/* Add Questions Modal */}
			<Modal
				title={__('Add Questions', 'pressprimer-quiz')}
				open={modalVisible}
				onCancel={() => setModalVisible(false)}
				onOk={handleAddSelected}
				okText={__('Add Selected', 'pressprimer-quiz')}
				cancelText={__('Cancel', 'pressprimer-quiz')}
				width={800}
			>
				<Table
					dataSource={availableQuestions}
					columns={questionColumns}
					rowKey="id"
					rowSelection={rowSelection}
					pagination={{ pageSize: 10 }}
					locale={{
						emptyText: __('No questions available', 'pressprimer-quiz'),
					}}
				/>
			</Modal>
		</Card>
	);
};

export default QuestionsPanel;
