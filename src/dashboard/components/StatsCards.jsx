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
 * @param {boolean} props.isTeacher Whether the user is a teacher (not admin)
 */
const StatsCards = ({ stats, loading, isTeacher = false }) => {
	// Consistent blue styling for all card icons
	const iconColor = '#1890ff';
	const iconBgColor = '#e6f7ff';

	const allCards = [
		{
			key: 'quizzes',
			// Teachers see "Quiz Count", admins see "Published Quizzes".
			label: isTeacher
				? __('Quiz Count', 'pressprimer-quiz')
				: __('Published Quizzes', 'pressprimer-quiz'),
			value: stats?.total_quizzes ?? '-',
			icon: <FileTextOutlined />,
			hideForTeacher: false,
		},
		{
			key: 'questions',
			label: __('Total Questions', 'pressprimer-quiz'),
			value: stats?.total_questions ?? '-',
			icon: <QuestionCircleOutlined />,
			hideForTeacher: true,
		},
		{
			key: 'banks',
			label: __('Question Banks', 'pressprimer-quiz'),
			value: stats?.total_banks ?? '-',
			icon: <FolderOutlined />,
			hideForTeacher: true,
		},
		{
			key: 'attempts',
			label: __('Attempts (7 days)', 'pressprimer-quiz'),
			value: stats?.recent_attempts ?? '-',
			icon: <CheckCircleOutlined />,
			hideForTeacher: false,
		},
		{
			key: 'questions_answered',
			label: __('Questions Answered (7 days)', 'pressprimer-quiz'),
			value: stats?.questions_answered ?? '-',
			icon: <EditOutlined />,
			hideForTeacher: false,
		},
		{
			key: 'pass_rate',
			label: __('Pass Rate (7 days)', 'pressprimer-quiz'),
			value: stats?.recent_pass_rate !== undefined ? `${stats.recent_pass_rate}%` : '-',
			icon: <PercentageOutlined />,
			hideForTeacher: false,
		},
	];

	// Filter out cards that should be hidden for teachers.
	const cards = isTeacher
		? allCards.filter((card) => !card.hideForTeacher)
		: allCards;

	return (
		<div className="ppq-stats-cards">
			{cards.map((card) => (
				<div key={card.key} className="ppq-stats-card">
					<div
						className="ppq-stats-card-icon"
						style={{ color: iconColor, backgroundColor: iconBgColor }}
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
