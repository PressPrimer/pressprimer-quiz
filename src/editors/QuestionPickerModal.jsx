/**
 * Question Picker Modal
 *
 * The "Add Questions" dialog the quiz editor and the bank editor share. It
 * lists the questions the user may use (GET /ppq/v1/questions applies the
 * usable-question rule), with search, filters and paging, and hands the
 * selected IDs to the editor. Mount it with a new key each time it opens, so
 * it starts from its defaults.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Table,
	Modal,
	message,
	Input,
	Select,
	Row,
	Col,
	Tooltip,
	Alert,
	Tag,
	Space,
} from 'antd';
import { SearchOutlined } from '@ant-design/icons';

const { Search } = Input;

const DEFAULT_FILTERS = {
	search: '',
	type: '',
	difficulty: '',
	category: '',
	bank: '',
	// Published so drafts never mix in silently; '' means published and
	// draft (archived questions aren't offered).
	status: 'published',
};

/**
 * Question Picker Modal
 *
 * @param {Object}   props            Component props.
 * @param {boolean}  props.open       Whether the dialog is open.
 * @param {Function} props.onCancel   Closes the dialog.
 * @param {Function} props.onAdd      Called with the selected question IDs.
 * @param {Object}   props.query      Extra query parameters, e.g. { exclude } or { exclude_bank_id }.
 * @param {number}   props.hideBankId A bank left out of the Bank filter (the bank being edited).
 * @return {JSX.Element} The dialog.
 */
const QuestionPickerModal = ({ open, onCancel, onAdd, query = {}, hideBankId = 0 }) => {
	const [availableQuestions, setAvailableQuestions] = useState([]);
	const [selectedQuestionIds, setSelectedQuestionIds] = useState([]);
	const [searchQuery, setSearchQuery] = useState('');
	const [filterType, setFilterType] = useState('');
	const [filterDifficulty, setFilterDifficulty] = useState('');
	const [filterCategory, setFilterCategory] = useState('');
	const [filterBank, setFilterBank] = useState('');
	const [filterStatus, setFilterStatus] = useState('published');
	const [pagination, setPagination] = useState({ current: 1, pageSize: 10, total: 0 });
	const [loadingQuestions, setLoadingQuestions] = useState(open);
	const [categories, setCategories] = useState([]);
	const [banks, setBanks] = useState([]);

	/**
	 * Load available questions with specific page size
	 *
	 * @param {number} page     Page number.
	 * @param {number} pageSize Items per page.
	 * @param {Object} filters  Optional filter overrides (avoids stale closure state).
	 */
	const loadAvailableQuestionsWithPageSize = async (page = 1, pageSize = 10, filters = null) => {
		try {
			setLoadingQuestions(true);

			// Use explicit filter values when provided, otherwise fall back to state.
			const search     = filters ? filters.search     : searchQuery;
			const type       = filters ? filters.type       : filterType;
			const difficulty = filters ? filters.difficulty  : filterDifficulty;
			const category   = filters ? filters.category   : filterCategory;
			const bank       = filters ? filters.bank       : filterBank;
			const status     = filters ? filters.status     : filterStatus;

			// Build query params
			const params = new URLSearchParams({
				per_page: pageSize.toString(),
				page: page.toString(),
			});

			if (search) {
				params.append('search', search);
			}
			if (type) {
				params.append('type', type);
			}
			if (difficulty) {
				params.append('difficulty', difficulty);
			}
			if (category) {
				params.append('category_id', category);
			}
			if (bank) {
				params.append('bank_id', bank);
			}
			if (status) {
				params.append('status', status);
			}

			// The editor's own exclusions (questions already in the quiz or bank).
			Object.entries(query).forEach(([key, value]) => {
				if (value !== undefined && value !== null && value !== '') {
					params.append(key, String(value));
				}
			});

			const response = await apiFetch({
				path: `/ppq/v1/questions?${params.toString()}`,
			});

			// Ensure IDs are consistently typed (numbers) for reliable row selection
			const questions = (response.questions || []).map(q => ({
				...q,
				id: Number(q.id),
			}));
			setAvailableQuestions(questions);
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
	 * Load available questions
	 *
	 * @param {number} page Page number.
	 */
	const loadAvailableQuestions = async (page = 1) => {
		loadAvailableQuestionsWithPageSize(page, pagination.pageSize);
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

	// Opened: load the filters and the first page, from the defaults.
	useEffect(() => {
		if (open) {
			loadFilterOptions();
			loadAvailableQuestionsWithPageSize(1, 10, DEFAULT_FILTERS);
		}
		// Load when opened; a new opening mounts a new picker.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [open]);

	/**
	 * Handle search
	 *
	 * @param {string} value Submitted search text.
	 */
	const handleSearch = (value) => {
		setSearchQuery(value);
		handleFilterChange({ search: value });
	};

	/**
	 * Handle filter change
	 *
	 * State setters are async, so the value that just changed must be passed
	 * explicitly — reading it back from state here would fetch with the
	 * previous filter values.
	 *
	 * @param {Object} changed The filter value(s) that just changed.
	 */
	const handleFilterChange = (changed = {}) => {
		setPagination({ ...pagination, current: 1 });
		loadAvailableQuestionsWithPageSize(1, pagination.pageSize, {
			search: searchQuery,
			type: filterType,
			difficulty: filterDifficulty,
			category: filterCategory,
			bank: filterBank,
			status: filterStatus,
			...changed,
		});
	};

	/**
	 * Handle pagination change
	 *
	 * @param {Object} newPagination Table pagination.
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
	 * Hand the selection to the editor.
	 */
	const handleOk = () => {
		if (selectedQuestionIds.length === 0) {
			message.warning(__('Please select at least one question', 'pressprimer-quiz'));
			return;
		}
		onAdd(selectedQuestionIds);
	};

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
			title: __('Status', 'pressprimer-quiz'),
			dataIndex: 'status',
			key: 'status',
			width: 100,
			render: (status) => {
				// Published rows stay unmarked; only flag the exceptions so
				// Draft/All views stay readable.
				if (!status || status === 'published') {
					return '';
				}
				const statusLabels = {
					draft: __('Draft', 'pressprimer-quiz'),
					archived: __('Archived', 'pressprimer-quiz'),
				};
				return (
					<Tag color={status === 'draft' ? 'gold' : 'default'}>
						{statusLabels[status] || status}
					</Tag>
				);
			},
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
			// Convert all keys to numbers for consistent comparison
			const numericKeys = selectedKeys.map(k => Number(k));
			const currentPageIds = availableQuestions.map(q => q.id);

			// Keep selections from other pages (not on current page)
			const otherPageSelections = selectedQuestionIds.filter(
				id => !currentPageIds.includes(id)
			);

			// Combine other page selections with current page selections (deduplicated)
			const combined = [...new Set([...otherPageSelections, ...numericKeys])];
			setSelectedQuestionIds(combined);
		},
		preserveSelectedRowKeys: true,
	};

	return (
		<Modal
			title={__('Add Questions', 'pressprimer-quiz')}
			open={open}
			onCancel={onCancel}
			onOk={handleOk}
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
					<Col span={5}>
						<Select
							placeholder={__('Type', 'pressprimer-quiz')}
							allowClear
							style={{ width: '100%' }}
							value={filterType || undefined}
							onChange={(value) => {
								setFilterType(value || '');
								handleFilterChange({ type: value || '' });
							}}
							options={[
								{ value: 'mc', label: __('Multiple Choice', 'pressprimer-quiz') },
								{ value: 'ma', label: __('Multiple Answer', 'pressprimer-quiz') },
								{ value: 'tf', label: __('True/False', 'pressprimer-quiz') },
							]}
						/>
					</Col>
					<Col span={5}>
						<Select
							placeholder={__('Difficulty', 'pressprimer-quiz')}
							allowClear
							style={{ width: '100%' }}
							value={filterDifficulty || undefined}
							onChange={(value) => {
								setFilterDifficulty(value || '');
								handleFilterChange({ difficulty: value || '' });
							}}
							options={[
								{ value: 'beginner', label: __('Beginner', 'pressprimer-quiz') },
								{ value: 'intermediate', label: __('Intermediate', 'pressprimer-quiz') },
								{ value: 'advanced', label: __('Advanced', 'pressprimer-quiz') },
								{ value: 'expert', label: __('Expert', 'pressprimer-quiz') },
							]}
						/>
					</Col>
					<Col span={5}>
						<Select
							placeholder={__('Category', 'pressprimer-quiz')}
							allowClear
							style={{ width: '100%' }}
							value={filterCategory || undefined}
							onChange={(value) => {
								setFilterCategory(value || '');
								handleFilterChange({ category: value || '' });
							}}
							options={categories.map(cat => ({
								value: cat.id.toString(),
								label: cat.name,
							}))}
						/>
					</Col>
					<Col span={5}>
						<Select
							placeholder={__('Bank', 'pressprimer-quiz')}
							allowClear
							style={{ width: '100%' }}
							value={filterBank || undefined}
							onChange={(value) => {
								setFilterBank(value || '');
								handleFilterChange({ bank: value || '' });
							}}
							options={banks
								.filter(bank => Number(bank.id) !== Number(hideBankId))
								.map(bank => ({
									value: bank.id.toString(),
									label: bank.name,
								}))}
						/>
					</Col>
					<Col span={4}>
						<Select
							style={{ width: '100%' }}
							value={filterStatus}
							onChange={(value) => {
								setFilterStatus(value);
								handleFilterChange({ status: value });
							}}
							options={[
								{ value: 'published', label: __('Published', 'pressprimer-quiz') },
								{ value: 'draft', label: __('Draft', 'pressprimer-quiz') },
								{ value: '', label: __('All statuses', 'pressprimer-quiz') },
							]}
						/>
					</Col>
				</Row>
			</Space>

			{selectedQuestionIds.length > 0 && (
				<Alert
					type="info"
					showIcon
					message={sprintf(
						/* translators: %d: number of selected questions */
						_n('%d question selected', '%d questions selected', selectedQuestionIds.length, 'pressprimer-quiz'),
						selectedQuestionIds.length
					)}
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
	);
};

export default QuestionPickerModal;
