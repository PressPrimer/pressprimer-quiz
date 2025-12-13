/**
 * Recent Attempts Report Component
 *
 * Full-page report showing individual quiz attempts.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Table, Input, Select, Space, Tag, Card, Button, Spin, Alert } from 'antd';
import {
	ClockCircleOutlined,
	UserOutlined,
	CheckCircleOutlined,
	CloseCircleOutlined,
	SearchOutlined,
	ArrowLeftOutlined,
} from '@ant-design/icons';

import DateRangePicker from './DateRangePicker';
import AttemptDetailModal from './AttemptDetailModal';
import { getDateRange, formatTime, formatDate } from '../utils/dateUtils';

const { Search } = Input;

/**
 * Recent Attempts Report Component
 */
const RecentAttemptsReport = () => {
	const [dateRange, setDateRange] = useState('30days');
	const [customDates, setCustomDates] = useState({ from: null, to: null });
	const [data, setData] = useState([]);
	const [quizOptions, setQuizOptions] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [pagination, setPagination] = useState({
		current: 1,
		pageSize: 20,
		total: 0,
	});
	const [search, setSearch] = useState('');
	const [quizFilter, setQuizFilter] = useState(null);
	const [statusFilter, setStatusFilter] = useState(null);
	const [sortField, setSortField] = useState('finished_at');
	const [sortOrder, setSortOrder] = useState('descend');
	const [selectedAttempt, setSelectedAttempt] = useState(null);
	const [modalVisible, setModalVisible] = useState(false);

	// Get effective date range
	const getEffectiveDates = useCallback(() => {
		if (dateRange === 'custom') {
			return customDates;
		}
		return getDateRange(dateRange);
	}, [dateRange, customDates]);

	// Fetch quiz options
	const fetchQuizOptions = useCallback(async () => {
		try {
			const response = await apiFetch({
				path: '/ppq/v1/statistics/quiz-options',
			});

			if (response.success) {
				setQuizOptions(response.data || []);
			}
		} catch (err) {
			// Failed to fetch - quiz filter will show no options
		}
	}, []);

	// Fetch data
	const fetchData = useCallback(async () => {
		setLoading(true);
		setError(null);

		try {
			const dates = getEffectiveDates();
			const params = new URLSearchParams();
			params.append('page', pagination.current);
			params.append('per_page', pagination.pageSize);
			params.append('orderby', sortField);
			params.append('order', sortOrder === 'ascend' ? 'ASC' : 'DESC');

			if (search) {
				params.append('search', search);
			}
			if (quizFilter) {
				params.append('quiz_id', quizFilter);
			}
			if (statusFilter !== null) {
				params.append('passed', statusFilter);
			}
			if (dates.from) {
				params.append('date_from', dates.from);
			}
			if (dates.to) {
				params.append('date_to', dates.to);
			}

			const response = await apiFetch({
				path: `/ppq/v1/statistics/attempts?${params.toString()}`,
			});

			if (response.success) {
				setData(response.data.items || []);
				setPagination((prev) => ({
					...prev,
					total: response.data.total || 0,
				}));
			}
		} catch (err) {
			setError(err.message || __('Failed to load report data.', 'pressprimer-quiz'));
		} finally {
			setLoading(false);
		}
	}, [pagination.current, pagination.pageSize, sortField, sortOrder, search, quizFilter, statusFilter, getEffectiveDates]);

	// Initial fetch
	useEffect(() => {
		fetchQuizOptions();
	}, [fetchQuizOptions]);

	// Fetch data when dependencies change
	useEffect(() => {
		fetchData();
	}, [fetchData]);

	// Handle date range change
	const handleDateRangeChange = (preset, custom = null) => {
		setDateRange(preset);
		if (preset === 'custom' && custom) {
			setCustomDates(custom);
		}
		setPagination((prev) => ({ ...prev, current: 1 }));
	};

	// Handle table change
	const handleTableChange = (newPagination, filters, sorter) => {
		setPagination({
			...pagination,
			current: newPagination.current,
			pageSize: newPagination.pageSize,
		});

		if (sorter.field) {
			setSortField(sorter.field);
			setSortOrder(sorter.order || 'descend');
		}
	};

	// Handle search
	const handleSearch = (value) => {
		setSearch(value);
		setPagination((prev) => ({ ...prev, current: 1 }));
	};

	// Handle quiz filter
	const handleQuizFilter = (value) => {
		setQuizFilter(value || null);
		setPagination((prev) => ({ ...prev, current: 1 }));
	};

	// Handle status filter
	const handleStatusFilter = (value) => {
		setStatusFilter(value !== undefined ? value : null);
		setPagination((prev) => ({ ...prev, current: 1 }));
	};

	// Handle row click
	const handleRowClick = (record) => {
		setSelectedAttempt(record);
		setModalVisible(true);
	};

	// Table columns
	const columns = [
		{
			title: __('Student', 'pressprimer-quiz'),
			dataIndex: 'student_name',
			key: 'student_name',
			sorter: true,
			sortOrder: sortField === 'student_name' ? sortOrder : null,
			render: (name, record) => (
				<div className="ppq-attempts-student">
					<UserOutlined className="ppq-attempts-student-icon" />
					<span>{name || record.guest_email || __('Guest', 'pressprimer-quiz')}</span>
				</div>
			),
		},
		{
			title: __('Quiz', 'pressprimer-quiz'),
			dataIndex: 'quiz_title',
			key: 'quiz_title',
			sorter: true,
			sortOrder: sortField === 'quiz_title' ? sortOrder : null,
			render: (title) => title || '-',
		},
		{
			title: __('Score', 'pressprimer-quiz'),
			dataIndex: 'score_percent',
			key: 'score_percent',
			sorter: true,
			sortOrder: sortField === 'score_percent' ? sortOrder : null,
			width: 100,
			align: 'center',
			render: (score) => (
				<span className="ppq-attempts-score">
					{score !== null ? `${Math.round(score)}%` : '-'}
				</span>
			),
		},
		{
			title: __('Status', 'pressprimer-quiz'),
			dataIndex: 'passed',
			key: 'passed',
			width: 100,
			align: 'center',
			render: (passed) => {
				const isPassed = passed === true || passed === 1 || passed === '1';
				return isPassed ? (
					<Tag icon={<CheckCircleOutlined />} color="success">
						{__('Passed', 'pressprimer-quiz')}
					</Tag>
				) : (
					<Tag icon={<CloseCircleOutlined />} color="error">
						{__('Failed', 'pressprimer-quiz')}
					</Tag>
				);
			},
		},
		{
			title: __('Duration', 'pressprimer-quiz'),
			dataIndex: 'elapsed_ms',
			key: 'elapsed_ms',
			sorter: true,
			sortOrder: sortField === 'elapsed_ms' ? sortOrder : null,
			width: 100,
			align: 'center',
			render: (ms) => (
				<span className="ppq-attempts-duration">
					{formatTime(ms)}
				</span>
			),
		},
		{
			title: __('Date', 'pressprimer-quiz'),
			dataIndex: 'finished_at',
			key: 'finished_at',
			sorter: true,
			sortOrder: sortField === 'finished_at' ? sortOrder : null,
			width: 180,
			render: (date) => (
				<span className="ppq-attempts-date">
					{formatDate(date)}
				</span>
			),
		},
	];

	// Get the plugin URL from localized data
	const pluginUrl = window.ppqReportsData?.pluginUrl || '';

	return (
		<div className="ppq-reports-container">
			{/* Header */}
			<div className="ppq-reports-header">
				<div className="ppq-reports-header-content">
					<Button
						type="link"
						icon={<ArrowLeftOutlined />}
						href="admin.php?page=ppq-reports"
						className="ppq-reports-back-link"
					>
						{__('All Reports', 'pressprimer-quiz')}
					</Button>
					<h1>
						<ClockCircleOutlined style={{ marginRight: 12, color: '#1890ff' }} />
						{__('Recent Attempts', 'pressprimer-quiz')}
					</h1>
					<p>{__('View individual student quiz attempts with scores, status, and detailed breakdowns.', 'pressprimer-quiz')}</p>
				</div>
				<img
					src={`${pluginUrl}assets/images/reports-mascot.png`}
					alt=""
					className="ppq-reports-header-mascot"
				/>
			</div>

			{/* Error Alert */}
			{error && (
				<Alert
					message={__('Error', 'pressprimer-quiz')}
					description={error}
					type="error"
					showIcon
					closable
					onClose={() => setError(null)}
					style={{ marginBottom: 24 }}
				/>
			)}

			{/* Date Range Picker */}
			<DateRangePicker
				value={dateRange}
				customDates={customDates}
				onChange={handleDateRangeChange}
			/>

			{/* Data Table */}
			<Card className="ppq-reports-card ppq-reports-table-card">
				<div className="ppq-reports-table-header">
					<Space size="middle" wrap>
						<Select
							placeholder={__('All Quizzes', 'pressprimer-quiz')}
							allowClear
							onChange={handleQuizFilter}
							className="ppq-reports-filter-select"
							options={quizOptions.map((q) => ({
								value: q.id,
								label: q.title,
							}))}
						/>
						<Select
							placeholder={__('All Status', 'pressprimer-quiz')}
							allowClear
							onChange={handleStatusFilter}
							className="ppq-reports-filter-select--small"
							options={[
								{ value: 1, label: __('Passed', 'pressprimer-quiz') },
								{ value: 0, label: __('Failed', 'pressprimer-quiz') },
							]}
						/>
						<Search
							placeholder={__('Search by student name or email...', 'pressprimer-quiz')}
							allowClear
							onSearch={handleSearch}
							className="ppq-reports-search"
						/>
					</Space>
				</div>
				<Spin spinning={loading}>
					<Table
						columns={columns}
						dataSource={data}
						rowKey="id"
						pagination={{
							...pagination,
							showSizeChanger: true,
							pageSizeOptions: ['10', '20', '50', '100'],
							showTotal: (total, range) =>
								`${range[0]}-${range[1]} ${__('of', 'pressprimer-quiz')} ${total}`,
						}}
						onChange={handleTableChange}
						size="middle"
						className="ppq-reports-table"
						sortDirections={['ascend', 'descend', 'ascend']}
						onRow={(record) => ({
							onClick: () => handleRowClick(record),
							style: { cursor: 'pointer' },
						})}
					/>
				</Spin>
			</Card>

			<AttemptDetailModal
				visible={modalVisible}
				attempt={selectedAttempt}
				onClose={() => {
					setModalVisible(false);
					setSelectedAttempt(null);
				}}
			/>
		</div>
	);
};

export default RecentAttemptsReport;
