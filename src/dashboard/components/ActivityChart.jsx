/**
 * Activity Chart Component
 *
 * Displays quiz completions and average scores over time.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Spin, Select, Empty } from 'antd';
import {
	LineChart,
	Line,
	XAxis,
	YAxis,
	CartesianGrid,
	Tooltip,
	Legend,
	ResponsiveContainer,
} from 'recharts';

/**
 * Format date for display
 *
 * @param {string} dateStr Date string in YYYY-MM-DD format
 * @return {string} Formatted date
 */
const formatDateLabel = (dateStr) => {
	const date = new Date(dateStr + 'T00:00:00');
	return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
};

/**
 * Custom tooltip component
 */
const CustomTooltip = ({ active, payload, label }) => {
	if (!active || !payload || !payload.length) {
		return null;
	}

	const date = new Date(label + 'T00:00:00');
	const formattedDate = date.toLocaleDateString(undefined, {
		weekday: 'short',
		month: 'short',
		day: 'numeric',
		year: 'numeric',
	});

	return (
		<div className="ppq-chart-tooltip">
			<p className="ppq-chart-tooltip-date">{formattedDate}</p>
			{payload.map((entry, index) => (
				<p key={index} style={{ color: entry.color }}>
					{entry.name}: {entry.value !== null ? entry.value : '-'}
					{entry.dataKey === 'avg_score' && entry.value !== null ? '%' : ''}
				</p>
			))}
		</div>
	);
};

/**
 * Activity Chart Component
 *
 * @param {Object} props Component props
 * @param {boolean} props.loading Parent loading state
 */
const ActivityChart = ({ loading: parentLoading }) => {
	const [data, setData] = useState([]);
	const [loading, setLoading] = useState(true);
	const [days, setDays] = useState(90);

	// Fetch chart data
	const fetchData = useCallback(async () => {
		setLoading(true);
		try {
			const response = await apiFetch({
				path: `/ppq/v1/statistics/activity-chart?days=${days}`,
			});

			if (response.success && response.data?.data) {
				setData(response.data.data);
			}
		} catch (err) {
			console.error('Failed to fetch chart data:', err);
		} finally {
			setLoading(false);
		}
	}, [days]);

	useEffect(() => {
		fetchData();
	}, [fetchData]);

	// Calculate tick interval based on data length
	const getTickInterval = () => {
		if (data.length <= 30) return 0; // Show all
		if (data.length <= 90) return 6; // Weekly
		if (data.length <= 180) return 13; // Bi-weekly
		if (data.length <= 365) return 29; // Monthly
		return 59; // Bi-monthly for 2 years
	};

	// Check if there's any actual data
	const hasData = data.some((d) => d.completions > 0);

	// Range options
	const rangeOptions = [
		{ value: 30, label: __('Last 30 days', 'pressprimer-quiz') },
		{ value: 90, label: __('Last 90 days', 'pressprimer-quiz') },
		{ value: 180, label: __('Last 6 months', 'pressprimer-quiz') },
		{ value: 365, label: __('Last year', 'pressprimer-quiz') },
		{ value: 730, label: __('Last 2 years', 'pressprimer-quiz') },
	];

	return (
		<div className="ppq-dashboard-card ppq-activity-chart-card">
			<div className="ppq-dashboard-card-header">
				<h3 className="ppq-dashboard-card-title">
					{__('Quiz Activity', 'pressprimer-quiz')}
				</h3>
				<Select
					value={days}
					onChange={setDays}
					options={rangeOptions}
					size="small"
					className="ppq-chart-range-select"
				/>
			</div>

			<Spin spinning={loading || parentLoading}>
				<div className="ppq-activity-chart-container">
					{hasData ? (
						<ResponsiveContainer width="100%" height={225}>
							<LineChart
								data={data}
								margin={{ top: 10, right: 30, left: 0, bottom: 0 }}
							>
								<CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
								<XAxis
									dataKey="date"
									tickFormatter={formatDateLabel}
									interval={getTickInterval()}
									tick={{ fontSize: 11, fill: '#8c8c8c' }}
									axisLine={{ stroke: '#d9d9d9' }}
									tickLine={{ stroke: '#d9d9d9' }}
								/>
								<YAxis
									yAxisId="left"
									tick={{ fontSize: 11, fill: '#8c8c8c' }}
									axisLine={{ stroke: '#d9d9d9' }}
									tickLine={{ stroke: '#d9d9d9' }}
									allowDecimals={false}
								/>
								<YAxis
									yAxisId="right"
									orientation="right"
									domain={[0, 100]}
									tick={{ fontSize: 11, fill: '#8c8c8c' }}
									axisLine={{ stroke: '#d9d9d9' }}
									tickLine={{ stroke: '#d9d9d9' }}
									tickFormatter={(value) => `${value}%`}
								/>
								<Tooltip content={<CustomTooltip />} />
								<Legend
									wrapperStyle={{ paddingTop: 10, fontSize: 12 }}
								/>
								<Line
									yAxisId="left"
									type="monotone"
									dataKey="completions"
									name={__('Completions', 'pressprimer-quiz')}
									stroke="#1890ff"
									strokeWidth={2}
									dot={false}
									activeDot={{ r: 4 }}
								/>
								<Line
									yAxisId="right"
									type="monotone"
									dataKey="avg_score"
									name={__('Avg Score', 'pressprimer-quiz')}
									stroke="#52c41a"
									strokeWidth={2}
									dot={false}
									activeDot={{ r: 4 }}
									connectNulls
								/>
							</LineChart>
						</ResponsiveContainer>
					) : (
						<Empty
							image={Empty.PRESENTED_IMAGE_SIMPLE}
							description={__('No quiz activity in this period', 'pressprimer-quiz')}
							style={{ padding: '40px 0' }}
						/>
					)}
				</div>
			</Spin>
		</div>
	);
};

export default ActivityChart;
