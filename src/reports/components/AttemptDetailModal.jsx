/**
 * Attempt Detail Modal Component
 *
 * Shows detailed information about a specific quiz attempt.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Modal, Spin, Tag, Divider, List, Button, Descriptions, Tooltip, Pagination } from 'antd';
import {
	UserOutlined,
	CheckCircleOutlined,
	CloseCircleOutlined,
	ClockCircleOutlined,
	TrophyOutlined,
	QuestionCircleOutlined,
	CalendarOutlined,
	FileTextOutlined,
	DownOutlined,
	UpOutlined,
} from '@ant-design/icons';

import { formatTime, formatDate } from '../utils/dateUtils';

/**
 * Attempt Detail Modal Component
 *
 * @param {Object} props Component props
 * @param {boolean} props.visible Modal visibility
 * @param {Object} props.attempt Attempt summary data
 * @param {Function} props.onClose Close handler
 */
const QUESTIONS_PER_PAGE = 5;

const AttemptDetailModal = ({ visible, attempt, onClose }) => {
	const [details, setDetails] = useState(null);
	const [loading, setLoading] = useState(false);
	const [showQuestionDetails, setShowQuestionDetails] = useState(false);
	const [questionPage, setQuestionPage] = useState(1);

	// Fetch full attempt details when modal opens
	useEffect(() => {
		if (visible && attempt?.id) {
			const fetchDetails = async () => {
				setLoading(true);
				try {
					const response = await apiFetch({
						path: `/ppq/v1/statistics/attempts/${attempt.id}`,
					});

					if (response.success) {
						setDetails(response.data);
					}
				} catch (err) {
					console.error('Failed to fetch attempt details:', err);
				} finally {
					setLoading(false);
				}
			};

			fetchDetails();
		}
	}, [visible, attempt?.id]);

	// Reset when modal closes
	useEffect(() => {
		if (!visible) {
			setDetails(null);
			setShowQuestionDetails(false);
			setQuestionPage(1);
		}
	}, [visible]);

	if (!attempt) return null;

	const isPassed = details?.passed || attempt.passed === true || attempt.passed === 1 || attempt.passed === '1';

	// Calculate score display
	const scorePoints = details?.score_points;
	const scorePercent = details?.score_percent ?? attempt.score_percent ?? 0;
	const hasScorePoints = scorePoints !== undefined && scorePoints !== null;

	// Get correct/total questions count - prefer from attempt list data, fallback to details
	const totalQuestions = attempt.total_questions || details?.items?.length || 0;
	const correctQuestions = attempt.correct_questions || details?.items?.filter(item => item.is_correct).length || 0;

	return (
		<Modal
			title={
				<span className="ppq-attempt-modal-title">
					<FileTextOutlined style={{ marginRight: 8 }} />
					{__('Attempt Details', 'pressprimer-quiz')}
				</span>
			}
			open={visible}
			onCancel={onClose}
			footer={[
				<Button key="close" type="primary" onClick={onClose}>
					{__('Close', 'pressprimer-quiz')}
				</Button>,
			]}
			width={680}
			className="ppq-attempt-modal"
		>
			<Spin spinning={loading}>
				{/* Student Info Section */}
				<div className="ppq-attempt-modal-section ppq-attempt-modal-section--student">
					<div className="ppq-attempt-modal-section-header">
						<UserOutlined />
						<span>{__('Student Information', 'pressprimer-quiz')}</span>
					</div>
					<Descriptions column={1} size="small" className="ppq-attempt-descriptions">
						<Descriptions.Item
							label={
								<Tooltip title={__('The name of the person who took this quiz', 'pressprimer-quiz')}>
									<span className="ppq-attempt-label">
										{__('Name', 'pressprimer-quiz')}
										<QuestionCircleOutlined className="ppq-attempt-label-help" />
									</span>
								</Tooltip>
							}
						>
							<span className="ppq-attempt-value">
								{details?.student_name || attempt.student_name || __('Guest', 'pressprimer-quiz')}
							</span>
						</Descriptions.Item>
						<Descriptions.Item
							label={
								<Tooltip title={__('Email address of the quiz taker', 'pressprimer-quiz')}>
									<span className="ppq-attempt-label">
										{__('Email', 'pressprimer-quiz')}
										<QuestionCircleOutlined className="ppq-attempt-label-help" />
									</span>
								</Tooltip>
							}
						>
							<span className="ppq-attempt-value ppq-attempt-value--email">
								{details?.student_email || attempt.user_email || attempt.guest_email || (
									<span className="ppq-attempt-value--empty">{__('Not provided', 'pressprimer-quiz')}</span>
								)}
							</span>
						</Descriptions.Item>
					</Descriptions>
				</div>

				<Divider className="ppq-attempt-divider" />

				{/* Quiz Results Section */}
				<div className="ppq-attempt-modal-section ppq-attempt-modal-section--results">
					<div className="ppq-attempt-modal-section-header">
						<TrophyOutlined />
						<span>{__('Quiz Results', 'pressprimer-quiz')}</span>
					</div>

					<Descriptions column={2} size="small" className="ppq-attempt-descriptions">
						<Descriptions.Item
							label={
								<Tooltip title={__('The quiz that was taken', 'pressprimer-quiz')}>
									<span className="ppq-attempt-label">
										{__('Quiz', 'pressprimer-quiz')}
										<QuestionCircleOutlined className="ppq-attempt-label-help" />
									</span>
								</Tooltip>
							}
							span={2}
						>
							<span className="ppq-attempt-value ppq-attempt-value--quiz">
								{details?.quiz_title || attempt.quiz_title}
							</span>
						</Descriptions.Item>

						<Descriptions.Item
							label={
								<Tooltip title={__('Final score achieved on this attempt', 'pressprimer-quiz')}>
									<span className="ppq-attempt-label">
										{__('Score', 'pressprimer-quiz')}
										<QuestionCircleOutlined className="ppq-attempt-label-help" />
									</span>
								</Tooltip>
							}
						>
							<span className={`ppq-attempt-score ${isPassed ? 'ppq-attempt-score--passed' : 'ppq-attempt-score--failed'}`}>
								{hasScorePoints ? (
									<>
										<strong>{scorePoints}</strong>
										<span className="ppq-attempt-score-pts"> pts</span>
										<span className="ppq-attempt-score-percent"> ({Math.round(scorePercent)}%)</span>
									</>
								) : (
									<strong>{Math.round(scorePercent)}%</strong>
								)}
							</span>
						</Descriptions.Item>

						<Descriptions.Item
							label={
								<Tooltip title={__('Whether the student met the passing threshold', 'pressprimer-quiz')}>
									<span className="ppq-attempt-label">
										{__('Result', 'pressprimer-quiz')}
										<QuestionCircleOutlined className="ppq-attempt-label-help" />
									</span>
								</Tooltip>
							}
						>
							{isPassed ? (
								<Tag icon={<CheckCircleOutlined />} color="success" className="ppq-attempt-tag">
									{__('Passed', 'pressprimer-quiz')}
								</Tag>
							) : (
								<Tag icon={<CloseCircleOutlined />} color="error" className="ppq-attempt-tag">
									{__('Failed', 'pressprimer-quiz')}
								</Tag>
							)}
						</Descriptions.Item>

						<Descriptions.Item
							label={
								<Tooltip title={__('Minimum percentage required to pass this quiz', 'pressprimer-quiz')}>
									<span className="ppq-attempt-label">
										{__('Passing Score', 'pressprimer-quiz')}
										<QuestionCircleOutlined className="ppq-attempt-label-help" />
									</span>
								</Tooltip>
							}
						>
							<span className="ppq-attempt-value">
								{details?.quiz_pass_percent !== undefined && details?.quiz_pass_percent !== null
									? `${details.quiz_pass_percent}%`
									: '70%'}
							</span>
						</Descriptions.Item>

						<Descriptions.Item
							label={
								<Tooltip title={__('Number of questions answered correctly out of total', 'pressprimer-quiz')}>
									<span className="ppq-attempt-label">
										{__('Questions', 'pressprimer-quiz')}
										<QuestionCircleOutlined className="ppq-attempt-label-help" />
									</span>
								</Tooltip>
							}
						>
							{totalQuestions > 0 ? (
								<span className="ppq-attempt-value">
									<span className="ppq-attempt-correct-count">{correctQuestions}</span>
									<span className="ppq-attempt-total-count"> / {totalQuestions}</span>
								</span>
							) : (
								<span className="ppq-attempt-value--empty">—</span>
							)}
						</Descriptions.Item>
					</Descriptions>
				</div>

				<Divider className="ppq-attempt-divider" />

				{/* Timing Section */}
				<div className="ppq-attempt-modal-section ppq-attempt-modal-section--timing">
					<div className="ppq-attempt-modal-section-header">
						<CalendarOutlined />
						<span>{__('Timing', 'pressprimer-quiz')}</span>
					</div>

					<Descriptions column={2} size="small" className="ppq-attempt-descriptions">
						<Descriptions.Item
							label={
								<Tooltip title={__('When the student started this quiz attempt', 'pressprimer-quiz')}>
									<span className="ppq-attempt-label">
										{__('Started', 'pressprimer-quiz')}
										<QuestionCircleOutlined className="ppq-attempt-label-help" />
									</span>
								</Tooltip>
							}
						>
							<span className="ppq-attempt-value">
								{formatDate(details?.started_at || attempt.started_at)}
							</span>
						</Descriptions.Item>

						<Descriptions.Item
							label={
								<Tooltip title={__('When the student submitted the quiz', 'pressprimer-quiz')}>
									<span className="ppq-attempt-label">
										{__('Finished', 'pressprimer-quiz')}
										<QuestionCircleOutlined className="ppq-attempt-label-help" />
									</span>
								</Tooltip>
							}
						>
							<span className="ppq-attempt-value">
								{formatDate(details?.finished_at || attempt.finished_at)}
							</span>
						</Descriptions.Item>

						<Descriptions.Item
							label={
								<Tooltip title={__('Total time spent actively taking the quiz', 'pressprimer-quiz')}>
									<span className="ppq-attempt-label">
										<ClockCircleOutlined style={{ marginRight: 4 }} />
										{__('Duration', 'pressprimer-quiz')}
										<QuestionCircleOutlined className="ppq-attempt-label-help" />
									</span>
								</Tooltip>
							}
							span={2}
						>
							<span className="ppq-attempt-value ppq-attempt-value--duration">
								{formatTime(details?.elapsed_ms || attempt.elapsed_ms)}
							</span>
						</Descriptions.Item>
					</Descriptions>
				</div>

				{/* Question Results Section - always show with button to expand */}
				{totalQuestions > 0 && (
					<>
						<Divider className="ppq-attempt-divider" />
						<div className="ppq-attempt-modal-section ppq-attempt-modal-section--questions">
							<div className="ppq-attempt-modal-section-header">
								<FileTextOutlined />
								<span>{__('Question Results', 'pressprimer-quiz')}</span>
								{details?.items?.length > 0 && (
									<Button
										type="link"
										size="small"
										onClick={() => setShowQuestionDetails(!showQuestionDetails)}
										style={{ marginLeft: 'auto' }}
									>
										{showQuestionDetails ? (
											<><UpOutlined /> {__('Hide Details', 'pressprimer-quiz')}</>
										) : (
											<><DownOutlined /> {__('Show Details', 'pressprimer-quiz')}</>
										)}
									</Button>
								)}
							</div>

							{!details?.items ? (
								<div className="ppq-attempt-questions-empty">
									<Spin size="small" />
									<span>{__('Loading question data...', 'pressprimer-quiz')}</span>
								</div>
							) : showQuestionDetails ? (
								<>
									<div className="ppq-attempt-questions-detailed">
										{details.items
											.slice((questionPage - 1) * QUESTIONS_PER_PAGE, questionPage * QUESTIONS_PER_PAGE)
											.map((item, index) => {
												const actualIndex = (questionPage - 1) * QUESTIONS_PER_PAGE + index;
												return (
													<div
														key={item.id}
														className={`ppq-attempt-question-card ${item.is_correct ? 'ppq-attempt-question-card--correct' : 'ppq-attempt-question-card--incorrect'}`}
													>
														<div className="ppq-attempt-question-header">
															<span className="ppq-attempt-question-number">
																{__('Question', 'pressprimer-quiz')} {actualIndex + 1}
															</span>
															{item.is_correct ? (
																<Tag color="success" className="ppq-attempt-question-tag">
																	<CheckCircleOutlined /> {__('Correct', 'pressprimer-quiz')}
																</Tag>
															) : (
																<Tag color="error" className="ppq-attempt-question-tag">
																	<CloseCircleOutlined /> {__('Incorrect', 'pressprimer-quiz')}
																</Tag>
															)}
														</div>
														<div className="ppq-attempt-question-stem-full">
															{item.stem || __('Question', 'pressprimer-quiz')}
														</div>
														{item.answers?.length > 0 && (
															<div className="ppq-attempt-answers-list">
																{item.answers.map((answer, ansIdx) => (
																	<div
																		key={ansIdx}
																		className={`ppq-attempt-answer ${answer.was_selected ? 'ppq-attempt-answer--selected' : ''} ${answer.is_correct ? 'ppq-attempt-answer--correct' : ''}`}
																	>
																		<span className="ppq-attempt-answer-indicator">
																			{answer.was_selected && answer.is_correct && <CheckCircleOutlined style={{ color: '#52c41a' }} />}
																			{answer.was_selected && !answer.is_correct && <CloseCircleOutlined style={{ color: '#ff4d4f' }} />}
																			{!answer.was_selected && answer.is_correct && <CheckCircleOutlined style={{ color: '#52c41a', opacity: 0.5 }} />}
																		</span>
																		<span className="ppq-attempt-answer-text">{answer.text}</span>
																	</div>
																))}
															</div>
														)}
														{item.time_spent_ms > 0 && (
															<div className="ppq-attempt-question-footer">
																<span className="ppq-attempt-question-time">
																	<ClockCircleOutlined style={{ marginRight: 4 }} />
																	{formatTime(item.time_spent_ms)}
																</span>
															</div>
														)}
													</div>
												);
											})}
									</div>
									{details.items.length > QUESTIONS_PER_PAGE && (
										<div className="ppq-attempt-questions-pagination">
											<Pagination
												current={questionPage}
												pageSize={QUESTIONS_PER_PAGE}
												total={details.items.length}
												onChange={(page) => setQuestionPage(page)}
												size="small"
												showSizeChanger={false}
												showTotal={(total, range) => `${range[0]}-${range[1]} of ${total}`}
											/>
										</div>
									)}
								</>
							) : (
								<List
									size="small"
									dataSource={details.items}
									className="ppq-attempt-questions-list"
									renderItem={(item, index) => (
										<List.Item className={`ppq-attempt-question-item ${item.is_correct ? 'ppq-attempt-question-item--correct' : 'ppq-attempt-question-item--incorrect'}`}>
											<div className="ppq-attempt-question-content">
												<span className="ppq-attempt-question-number">
													{index + 1}.
												</span>
												<span className="ppq-attempt-question-stem">
													{item.stem || __('Question', 'pressprimer-quiz')}
												</span>
											</div>
											<div className="ppq-attempt-question-meta">
												{item.is_correct ? (
													<Tag color="success" className="ppq-attempt-question-tag">
														<CheckCircleOutlined /> {__('Correct', 'pressprimer-quiz')}
													</Tag>
												) : (
													<Tag color="error" className="ppq-attempt-question-tag">
														<CloseCircleOutlined /> {__('Incorrect', 'pressprimer-quiz')}
													</Tag>
												)}
											</div>
										</List.Item>
									)}
								/>
							)}
						</div>
					</>
				)}
			</Spin>
		</Modal>
	);
};

export default AttemptDetailModal;
