/**
 * Quiz Questions Panel Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { debugError } from '../../utils/debug';
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
	Input,
	Select,
	Row,
	Col,
	Tooltip,
	Alert,
} from 'antd';
import {
	PlusOutlined,
	DeleteOutlined,
	EditOutlined,
	HolderOutlined,
	SearchOutlined,
} from '@ant-design/icons';
import { DragDropContext, Droppable, Draggable } from 'react-beautiful-dnd';

const { Text } = Typography;
const { Search } = Input;

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
	const [searchQuery, setSearchQuery] = useState('');
	const [filterType, setFilterType] = useState('');
	const [filterDifficulty, setFilterDifficulty] = useState('');
	const [filterCategory, setFilterCategory] = useState('');
	const [filterBank, setFilterBank] = useState('');
	const [pagination, setPagination] = useState({ current: 1, pageSize: 10, total: 0 });
	const [loadingQuestions, setLoadingQuestions] = useState(false);
	const [categories, setCategories] = useState([]);
	const [banks, setBanks] = useState([]);

	// Load quiz items
	useEffect(() => {
		if (quizId && generationMode === 'fixed') {
			loadQuizItems();
		}
	}, [quizId, generationMode]);

	// Calculate total points
	useEffect(() => {
		const total = items
			.filter(item => item && item.weight !== undefined)
			.reduce((sum, item) => sum + (parseFloat(item.weight) || 0), 0);
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
			// Failed to load - items will be empty
		} finally {
			setLoading(false);
		}
	};

	/**
	 * Load available questions for modal
	 */
	const loadAvailableQuestions = async (page = 1) => {
		loadAvailableQuestionsWithPageSize(page, pagination.pageSize);
	};

	/**
	 * Load available questions with specific page size
	 */
	const loadAvailableQuestionsWithPageSize = async (page = 1, pageSize = 10) => {
		try {
			setLoadingQuestions(true);

			// Build query params
			const params = new URLSearchParams({
				per_page: pageSize.toString(),
				page: page.toString(),
			});

			if (searchQuery) {
				params.append('search', searchQuery);
			}
			if (filterType) {
				params.append('type', filterType);
			}
			if (filterDifficulty) {
				params.append('difficulty', filterDifficulty);
			}
			if (filterCategory) {
				params.append('category_id', filterCategory);
			}
			if (filterBank) {
				params.append('bank_id', filterBank);
			}

			// Exclude questions already in the quiz
			const existingQuestionIds = items.map(item => item.question_id).filter(Boolean);
			if (existingQuestionIds.length > 0) {
				params.append('exclude', existingQuestionIds.join(','));
			}

			const response = await apiFetch({
				path: `/ppq/v1/questions?${params.toString()}`,
			});

			setAvailableQuestions(response.questions || []);
			setPagination(prev => ({
				...prev,
				current: page,
				pageSize: pageSize,
				total: response.total,
			}));
		} catch (error) {
			message.error(__('Failed to load questions', 'pressprimer-quiz'));
		} finally {
			setLoadingQuestions(false);
		}
	};

	/**
	 * Load filter options (categories and banks)
	 */
	const loadFilterOptions = async () => {
		try {
			// Load categories
			const categoriesResponse = await apiFetch({ path: '/ppq/v1/taxonomies?type=category' });
			setCategories(categoriesResponse || []);

			// Load banks
			const banksResponse = await apiFetch({ path: '/ppq/v1/banks' });
			setBanks(banksResponse || []);
		} catch (error) {
			// Failed to load filters - will show empty options
		}
	};

	/**
	 * Open add questions modal
	 */
	const handleAddQuestions = () => {
		// Reset filters
		setSearchQuery('');
		setFilterType('');
		setFilterDifficulty('');
		setFilterCategory('');
		setFilterBank('');
		setPagination({ current: 1, pageSize: 10, total: 0 });
		setSelectedQuestionIds([]);
		setModalVisible(true);
		loadFilterOptions();
		loadAvailableQuestions(1);
	};

	/**
	 * Handle search
	 */
	const handleSearch = (value) => {
		setSearchQuery(value);
		setPagination({ ...pagination, current: 1 });
		loadAvailableQuestions(1);
	};

	/**
	 * Handle filter change
	 */
	const handleFilterChange = () => {
		setPagination({ ...pagination, current: 1 });
		loadAvailableQuestions(1);
	};

	/**
	 * Handle pagination change
	 */
	const handleTableChange = (newPagination) => {
		// Update pageSize in state if it changed
		if (newPagination.pageSize !== pagination.pageSize) {
			setPagination(prev => ({
				...prev,
				pageSize: newPagination.pageSize,
				current: 1, // Reset to page 1 when page size changes
			}));
			// Need to load with new page size - use a callback pattern
			loadAvailableQuestionsWithPageSize(1, newPagination.pageSize);
		} else {
			loadAvailableQuestions(newPagination.current);
		}
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
			loadQuizItems();
		} catch (error) {
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
			message.error(__('Failed to update weight', 'pressprimer-quiz'));
		}
	};

	/**
	 * Handle drag end
	 */
	const handleDragEnd = (result) => {
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

		// Save new order to backend (async, but not awaited to avoid blocking UI)
		apiFetch({
			path: `/ppq/v1/quizzes/${quizId}/items/reorder`,
			method: 'POST',
			data: {
				item_ids: reorderedItems.map(item => item.id),
			},
		}).catch(() => {
			message.error(__('Failed to save order', 'pressprimer-quiz'));
			// Reload to reset
			loadQuizItems();
		});
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
				<Space size="small">
					<Tooltip title={__('Edit question', 'pressprimer-quiz')}>
						<Button
							type="text"
							icon={<EditOutlined />}
							onClick={() => {
								const editUrl = `${window.ppqAdmin.adminUrl}admin.php?page=ppq-questions&action=edit&question=${record.question_id}`;
								window.open(editUrl, '_blank');
							}}
						/>
					</Tooltip>
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

	const questionColumns = [
		{
			title: __('Question', 'pressprimer-quiz'),
			dataIndex: 'stem',
			key: 'stem',
			ellipsis: {
				showTitle: false,
			},
			render: (text, record) => (
				<Tooltip placement="topLeft" title={record.stem_full || text}>
					<span>{text}</span>
				</Tooltip>
			),
		},
		{
			title: __('Type', 'pressprimer-quiz'),
			dataIndex: 'type',
			key: 'type',
			width: 100,
			render: (type) => type ? type.toUpperCase() : '',
		},
		{
			title: __('Date', 'pressprimer-quiz'),
			dataIndex: 'created_at',
			key: 'created_at',
			width: 120,
			render: (date) => {
				if (!date) return '';
				const d = new Date(date);
				return d.toLocaleDateString(undefined, {
					year: 'numeric',
					month: 'short',
					day: 'numeric'
				});
			},
		},
	];

	const rowSelection = {
		selectedRowKeys: selectedQuestionIds,
		onChange: (selectedKeys) => {
			// Get IDs of questions currently visible on this page
			const currentPageIds = availableQuestions.map(q => q.id);

			// Keep selections from other pages (not on current page)
			const otherPageSelections = selectedQuestionIds.filter(
				id => !currentPageIds.includes(id)
			);

			// Combine other page selections with current page selections
			setSelectedQuestionIds([...otherPageSelections, ...selectedKeys]);
		},
		preserveSelectedRowKeys: true,
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
			<Modal
				title={__('Add Questions', 'pressprimer-quiz')}
				open={modalVisible}
				onCancel={() => setModalVisible(false)}
				onOk={handleAddSelected}
				okText={__('Add Selected', 'pressprimer-quiz')}
				cancelText={__('Cancel', 'pressprimer-quiz')}
				width={900}
			>
				<Space direction="vertical" style={{ width: '100%', marginBottom: 16 }} size="middle">
					{/* Search */}
					<Search
						placeholder={__('Search questions...', 'pressprimer-quiz')}
						allowClear
						enterButton={<SearchOutlined />}
						size="large"
						onSearch={handleSearch}
						value={searchQuery}
						onChange={(e) => setSearchQuery(e.target.value)}
					/>

					{/* Filters */}
					<Row gutter={8}>
						<Col span={6}>
							<Select
								placeholder={__('Type', 'pressprimer-quiz')}
								allowClear
								style={{ width: '100%' }}
								value={filterType || undefined}
								onChange={(value) => {
									setFilterType(value || '');
									handleFilterChange();
								}}
								options={[
									{ value: 'mcq', label: __('Multiple Choice', 'pressprimer-quiz') },
									{ value: 'tf', label: __('True/False', 'pressprimer-quiz') },
									{ value: 'essay', label: __('Essay', 'pressprimer-quiz') },
								]}
							/>
						</Col>
						<Col span={6}>
							<Select
								placeholder={__('Difficulty', 'pressprimer-quiz')}
								allowClear
								style={{ width: '100%' }}
								value={filterDifficulty || undefined}
								onChange={(value) => {
									setFilterDifficulty(value || '');
									handleFilterChange();
								}}
								options={[
									{ value: 'beginner', label: __('Beginner', 'pressprimer-quiz') },
									{ value: 'intermediate', label: __('Intermediate', 'pressprimer-quiz') },
									{ value: 'advanced', label: __('Advanced', 'pressprimer-quiz') },
									{ value: 'expert', label: __('Expert', 'pressprimer-quiz') },
								]}
							/>
						</Col>
						<Col span={6}>
							<Select
								placeholder={__('Category', 'pressprimer-quiz')}
								allowClear
								style={{ width: '100%' }}
								value={filterCategory || undefined}
								onChange={(value) => {
									setFilterCategory(value || '');
									handleFilterChange();
								}}
								options={categories.map(cat => ({
									value: cat.id.toString(),
									label: cat.name,
								}))}
							/>
						</Col>
						<Col span={6}>
							<Select
								placeholder={__('Bank', 'pressprimer-quiz')}
								allowClear
								style={{ width: '100%' }}
								value={filterBank || undefined}
								onChange={(value) => {
									setFilterBank(value || '');
									handleFilterChange();
								}}
								options={banks.map(bank => ({
									value: bank.id.toString(),
									label: bank.name,
								}))}
							/>
						</Col>
					</Row>
				</Space>

				{selectedQuestionIds.length > 0 && (
					<Alert
						type="info"
						showIcon
						message={
							/* translators: %d: number of selected questions */
							sprintf(__('%d question(s) selected', 'pressprimer-quiz'), selectedQuestionIds.length)
						}
						style={{ marginBottom: 16 }}
					/>
				)}

				<Table
					loading={loadingQuestions}
					dataSource={availableQuestions}
					columns={questionColumns}
					rowKey="id"
					rowSelection={rowSelection}
					scroll={{ x: 'max-content' }}
					pagination={{
						current: pagination.current,
						pageSize: pagination.pageSize,
						total: pagination.total,
						showSizeChanger: true,
						showTotal: (total) => __('Total', 'pressprimer-quiz') + `: ${total}`,
					}}
					onChange={handleTableChange}
					locale={{
						emptyText: __('No questions available', 'pressprimer-quiz'),
					}}
				/>
			</Modal>
		</Card>
	);
};

export default QuestionsPanel;
