/**
 * Popular Quizzes Component
 *
 * Shows top quizzes by attempt count.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { Empty, Skeleton } from 'antd';
import {
	TrophyOutlined,
	FireOutlined,
} from '@ant-design/icons';

/**
 * Popular Quizzes Component
 *
 * @param {Object} props Component props
 * @param {Array} props.quizzes Popular quizzes data
 * @param {boolean} props.loading Loading state
 */
const PopularQuizzes = ({ quizzes = [], loading }) => {
	// Medal colors for top 3
	const getMedalColor = (index) => {
		switch (index) {
			case 0:
				return '#faad14'; // Gold
			case 1:
				return '#8c8c8c'; // Silver
			case 2:
				return '#d48806'; // Bronze
			default:
				return '#d9d9d9';
		}
	};

	return (
		<div className="ppq-dashboard-card">
			<h3 className="ppq-dashboard-card-title">
				<FireOutlined style={{ marginRight: 8, color: '#fa541c' }} />
				{__('Popular Quizzes', 'pressprimer-quiz')}
				<span className="ppq-dashboard-card-subtitle">
					{__('Last 30 days', 'pressprimer-quiz')}
				</span>
			</h3>

			{loading ? (
				<div className="ppq-popular-quizzes-loading">
					{[1, 2, 3].map((i) => (
						<Skeleton.Input key={i} active size="small" block style={{ marginBottom: 12 }} />
					))}
				</div>
			) : quizzes.length === 0 ? (
				<Empty
					image={Empty.PRESENTED_IMAGE_SIMPLE}
					description={__('No quiz attempts yet', 'pressprimer-quiz')}
				/>
			) : (
				<div className="ppq-popular-quizzes-list">
					{quizzes.map((quiz, index) => (
						<div key={quiz.id} className="ppq-popular-quiz-item">
							<div className="ppq-popular-quiz-rank">
								{index < 3 ? (
									<TrophyOutlined style={{ color: getMedalColor(index), fontSize: 18 }} />
								) : (
									<span className="ppq-popular-quiz-rank-number">{index + 1}</span>
								)}
							</div>
							<div className="ppq-popular-quiz-info">
								<a
									href={`admin.php?page=pressprimer-quiz-quizzes&action=edit&quiz=${quiz.id}`}
									className="ppq-popular-quiz-title"
								>
									{quiz.title}
								</a>
								<span className="ppq-popular-quiz-attempts">
									{quiz.attempt_count} {quiz.attempt_count === 1
										? __('attempt', 'pressprimer-quiz')
										: __('attempts', 'pressprimer-quiz')}
								</span>
							</div>
						</div>
					))}
				</div>
			)}
		</div>
	);
};

export default PopularQuizzes;
