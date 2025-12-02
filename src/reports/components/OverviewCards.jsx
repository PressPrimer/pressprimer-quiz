/**
 * Overview Cards Component
 *
 * Displays summary statistics in card format.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	LineChartOutlined,
	PercentageOutlined,
	CheckCircleOutlined,
	ClockCircleOutlined,
} from '@ant-design/icons';

/**
 * Format seconds to human readable time
 *
 * @param {number} seconds Total seconds
 * @return {string} Formatted time
 */
const formatDuration = (seconds) => {
	if (!seconds || seconds === 0) return '-';

	const mins = Math.floor(seconds / 60);
	const secs = seconds % 60;

	if (mins === 0) {
		return `${secs}s`;
	}

	return `${mins}m ${secs}s`;
};

/**
 * Overview Cards Component
 *
 * @param {Object} props Component props
 * @param {Object} props.stats Statistics data
 * @param {boolean} props.loading Loading state
 */
const OverviewCards = ({ stats, loading }) => {
	const cards = [
		{
			key: 'total_attempts',
			label: __('Total Attempts', 'pressprimer-quiz'),
			value: stats?.total_attempts ?? '-',
			icon: <LineChartOutlined />,
			color: '#1890ff',
			bgColor: '#e6f7ff',
		},
		{
			key: 'avg_score',
			label: __('Average Score', 'pressprimer-quiz'),
			value: stats?.avg_score !== undefined ? `${stats.avg_score}%` : '-',
			icon: <PercentageOutlined />,
			color: '#722ed1',
			bgColor: '#f9f0ff',
		},
		{
			key: 'pass_rate',
			label: __('Pass Rate', 'pressprimer-quiz'),
			value: stats?.pass_rate !== undefined ? `${stats.pass_rate}%` : '-',
			icon: <CheckCircleOutlined />,
			color: '#52c41a',
			bgColor: '#f6ffed',
		},
		{
			key: 'avg_time',
			label: __('Avg. Time', 'pressprimer-quiz'),
			value: formatDuration(stats?.avg_time_seconds),
			icon: <ClockCircleOutlined />,
			color: '#fa8c16',
			bgColor: '#fff7e6',
		},
	];

	return (
		<div className="ppq-overview-cards">
			{cards.map((card) => (
				<div key={card.key} className="ppq-overview-card">
					<div
						className="ppq-overview-card-icon"
						style={{ color: card.color, backgroundColor: card.bgColor }}
					>
						{card.icon}
					</div>
					<div className="ppq-overview-card-content">
						<div className="ppq-overview-card-value">
							{loading ? '-' : card.value}
						</div>
						<div className="ppq-overview-card-label">{card.label}</div>
					</div>
				</div>
			))}
		</div>
	);
};

export default OverviewCards;
