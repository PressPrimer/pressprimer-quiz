/**
 * Stats Cards Component
 *
 * Displays key metrics in card format.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import {
	FileTextOutlined,
	QuestionCircleOutlined,
	FolderOutlined,
	CheckCircleOutlined,
	PercentageOutlined,
	EditOutlined,
} from '@ant-design/icons';

/**
 * Stats Cards Component
 *
 * @param {Object} props Component props
 * @param {Object} props.stats Statistics data
 * @param {boolean} props.loading Loading state
 */
const StatsCards = ({ stats, loading }) => {
	const cards = [
		{
			key: 'quizzes',
			label: __('Published Quizzes', 'pressprimer-quiz'),
			value: stats?.total_quizzes ?? '-',
			icon: <FileTextOutlined />,
			color: '#1890ff',
			bgColor: '#e6f7ff',
		},
		{
			key: 'questions',
			label: __('Total Questions', 'pressprimer-quiz'),
			value: stats?.total_questions ?? '-',
			icon: <QuestionCircleOutlined />,
			color: '#722ed1',
			bgColor: '#f9f0ff',
		},
		{
			key: 'banks',
			label: __('Question Banks', 'pressprimer-quiz'),
			value: stats?.total_banks ?? '-',
			icon: <FolderOutlined />,
			color: '#13c2c2',
			bgColor: '#e6fffb',
		},
		{
			key: 'attempts',
			label: __('Attempts (7 days)', 'pressprimer-quiz'),
			value: stats?.recent_attempts ?? '-',
			icon: <CheckCircleOutlined />,
			color: '#52c41a',
			bgColor: '#f6ffed',
		},
		{
			key: 'questions_answered',
			label: __('Questions Answered (7 days)', 'pressprimer-quiz'),
			value: stats?.questions_answered ?? '-',
			icon: <EditOutlined />,
			color: '#eb2f96',
			bgColor: '#fff0f6',
		},
		{
			key: 'pass_rate',
			label: __('Pass Rate (7 days)', 'pressprimer-quiz'),
			value: stats?.recent_pass_rate !== undefined ? `${stats.recent_pass_rate}%` : '-',
			icon: <PercentageOutlined />,
			color: '#fa8c16',
			bgColor: '#fff7e6',
		},
	];

	return (
		<div className="ppq-stats-cards">
			{cards.map((card) => (
				<div key={card.key} className="ppq-stats-card">
					<div
						className="ppq-stats-card-icon"
						style={{ color: card.color, backgroundColor: card.bgColor }}
					>
						{card.icon}
					</div>
					<div className="ppq-stats-card-content">
						<div className="ppq-stats-card-value">
							{loading ? '-' : card.value}
						</div>
						<div className="ppq-stats-card-label">{card.label}</div>
					</div>
				</div>
			))}
		</div>
	);
};

export default StatsCards;
