/**
 * Quiz Performance Report Component
 *
 * Full-page report showing quiz performance statistics.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Table, Input, Card, Button, Spin, Alert } from 'antd';
import {
	TrophyOutlined,
	SearchOutlined,
	ArrowLeftOutlined,
} from '@ant-design/icons';

import DateRangePicker from './DateRangePicker';
import { getDateRange, formatDuration } from '../utils/dateUtils';

const { Search } = Input;

/**
 * Quiz Performance Report Component
 */
const QuizPerformanceReport = () => {
	const [dateRange, setDateRange] = useState('30days');
	const [customDates, setCustomDates] = useState({ from: null, to: null });
	const [data, setData] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [pagination, setPagination] = useState({
		current: 1,
		pageSize: 20,
		total: 0,
	});
	const [search, setSearch] = useState('');
	const [sortField, setSortField] = useState('attempts');
	const [sortOrder, setSortOrder] = useState('descend');

	// Get effective date range
	const getEffectiveDates = useCallback(() => {
		if (dateRange === 'custom') {
			return customDates;
		}
		return getDateRange(dateRange);
	}, [dateRange, customDates]);

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
			if (dates.from) {
				params.append('date_from', dates.from);
			}
			if (dates.to) {
				params.append('date_to', dates.to);
			}

			const response = await apiFetch({
				path: `/ppq/v1/statistics/quiz-performance?${params.toString()}`,
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
	}, [pagination.current, pagination.pageSize, sortField, sortOrder, search, getEffectiveDates]);

	// Fetch on mount and when dependencies change
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

	// Handle table change (pagination, sorting)
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

	// Table columns
	const columns = [
		{
			title: __('Quiz', 'pressprimer-quiz'),
			dataIndex: 'title',
			key: 'title',
			sorter: true,
			sortOrder: sortField === 'title' ? sortOrder : null,
			render: (title, record) => (
				<a href={`admin.php?page=pressprimer-quiz-quizzes&action=edit&quiz=${record.id}`}>
					{title}
				</a>
			),
		},
		{
			title: __('Attempts', 'pressprimer-quiz'),
			dataIndex: 'attempts',
			key: 'attempts',
			sorter: true,
			sortOrder: sortField === 'attempts' ? sortOrder : null,
			width: 120,
			align: 'center',
			render: (attempts) => attempts || 0,
		},
		{
			title: __('Avg Score', 'pressprimer-quiz'),
			dataIndex: 'avg_score',
			key: 'avg_score',
			sorter: true,
			sortOrder: sortField === 'avg_score' ? sortOrder : null,
			width: 120,
			align: 'center',
			render: (score) => (score !== null ? `${Math.round(score)}%` : '-'),
		},
		{
			title: __('Pass Rate', 'pressprimer-quiz'),
			dataIndex: 'pass_rate',
			key: 'pass_rate',
			sorter: true,
			sortOrder: sortField === 'pass_rate' ? sortOrder : null,
			width: 120,
			align: 'center',
			render: (rate) => (rate !== null ? `${Math.round(rate)}%` : '-'),
		},
		{
			title: __('Avg Time', 'pressprimer-quiz'),
			dataIndex: 'avg_time',
			key: 'avg_time',
			sorter: true,
			sortOrder: sortField === 'avg_time' ? sortOrder : null,
			width: 120,
			align: 'center',
			render: (time) => formatDuration(time),
		},
	];

	// Get the plugin URL and mascot from localized data
	const pluginUrl = window.pressprimerQuizReportsData?.pluginUrl || '';
	const reportsMascot = window.pressprimerQuizReportsData?.reportsMascot ||
		`${pluginUrl}assets/images/reports-mascot.png`;

	return (
		<div className="ppq-reports-container">
			{/* Header */}
			<div className="ppq-reports-header">
				<div className="ppq-reports-header-content">
					<Button
						type="link"
						icon={<ArrowLeftOutlined />}
						href="admin.php?page=pressprimer-quiz-reports"
						className="ppq-reports-back-link"
					>
						{__('All Reports', 'pressprimer-quiz')}
					</Button>
					<h1>
						<TrophyOutlined style={{ marginRight: 12, color: '#faad14' }} />
						{__('Quiz Performance', 'pressprimer-quiz')}
					</h1>
					<p>{__('See how each quiz is performing with attempt counts, average scores, and pass rates.', 'pressprimer-quiz')}</p>
				</div>
				<img
					src={reportsMascot}
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
					<Search
						placeholder={__('Search quizzes...', 'pressprimer-quiz')}
						allowClear
						onSearch={handleSearch}
						className="ppq-reports-search"
					/>
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
					/>
				</Spin>
			</Card>
		</div>
	);
};

export default QuizPerformanceReport;
