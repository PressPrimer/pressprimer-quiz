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
import { Modal, Spin, Tag, Divider, List, Button, Descriptions } from 'antd';
import {
	UserOutlined,
	MailOutlined,
	CheckCircleOutlined,
	CloseCircleOutlined,
	ClockCircleOutlined,
	TrophyOutlined,
	ExportOutlined,
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
const AttemptDetailModal = ({ visible, attempt, onClose }) => {
	const [details, setDetails] = useState(null);
	const [loading, setLoading] = useState(false);

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
		}
	}, [visible]);

	if (!attempt) return null;

	const isPassed = details?.passed || attempt.passed === true || attempt.passed === 1 || attempt.passed === '1';

	return (
		<Modal
			title={__('Attempt Details', 'pressprimer-quiz')}
			open={visible}
			onCancel={onClose}
			footer={[
				<Button
					key="results"
					type="primary"
					icon={<ExportOutlined />}
					href={`${window.ppqReportsData?.resultsUrl || ''}?attempt_id=${attempt.id}&token=${details?.results_token || ''}`}
					target="_blank"
					disabled={!details?.results_token}
				>
					{__('View Full Results', 'pressprimer-quiz')}
				</Button>,
				<Button key="close" onClick={onClose}>
					{__('Close', 'pressprimer-quiz')}
				</Button>,
			]}
			width={640}
			className="ppq-attempt-modal"
		>
			<Spin spinning={loading}>
				{/* Student Info */}
				<div className="ppq-attempt-modal-section">
					<Descriptions column={1} size="small">
						<Descriptions.Item
							label={
								<span>
									<UserOutlined style={{ marginRight: 8 }} />
									{__('Student', 'pressprimer-quiz')}
								</span>
							}
						>
							{details?.student_name || attempt.student_name || __('Guest', 'pressprimer-quiz')}
						</Descriptions.Item>
						<Descriptions.Item
							label={
								<span>
									<MailOutlined style={{ marginRight: 8 }} />
									{__('Email', 'pressprimer-quiz')}
								</span>
							}
						>
							{details?.student_email || attempt.user_email || attempt.guest_email || '-'}
						</Descriptions.Item>
					</Descriptions>
				</div>

				<Divider style={{ margin: '16px 0' }} />

				{/* Quiz Info */}
				<div className="ppq-attempt-modal-section">
					<Descriptions column={2} size="small">
						<Descriptions.Item label={__('Quiz', 'pressprimer-quiz')} span={2}>
							<strong>{details?.quiz_title || attempt.quiz_title}</strong>
						</Descriptions.Item>
						<Descriptions.Item label={__('Score', 'pressprimer-quiz')}>
							<span className="ppq-attempt-modal-score">
								{details?.score_points !== undefined
									? `${details.score_points} pts`
									: '-'}{' '}
								({details?.score_percent !== undefined
									? `${Math.round(details.score_percent)}%`
									: `${Math.round(attempt.score_percent || 0)}%`})
							</span>
						</Descriptions.Item>
						<Descriptions.Item label={__('Status', 'pressprimer-quiz')}>
							{isPassed ? (
								<Tag icon={<CheckCircleOutlined />} color="success">
									{__('Passed', 'pressprimer-quiz')}
								</Tag>
							) : (
								<Tag icon={<CloseCircleOutlined />} color="error">
									{__('Failed', 'pressprimer-quiz')}
								</Tag>
							)}
						</Descriptions.Item>
						<Descriptions.Item label={__('Duration', 'pressprimer-quiz')}>
							<ClockCircleOutlined style={{ marginRight: 4 }} />
							{formatTime(details?.elapsed_ms || attempt.elapsed_ms)}
						</Descriptions.Item>
						<Descriptions.Item label={__('Passing', 'pressprimer-quiz')}>
							{details?.quiz_pass_percent !== undefined
								? `${details.quiz_pass_percent}%`
								: '-'}
						</Descriptions.Item>
						<Descriptions.Item label={__('Started', 'pressprimer-quiz')} span={2}>
							{formatDate(details?.started_at)}
						</Descriptions.Item>
						<Descriptions.Item label={__('Finished', 'pressprimer-quiz')} span={2}>
							{formatDate(details?.finished_at || attempt.finished_at)}
						</Descriptions.Item>
					</Descriptions>
				</div>

				<Divider style={{ margin: '16px 0' }} />

				{/* Questions Breakdown */}
				<div className="ppq-attempt-modal-section">
					<h4 style={{ marginBottom: 12 }}>
						<TrophyOutlined style={{ marginRight: 8 }} />
						{__('Questions', 'pressprimer-quiz')}
					</h4>

					{details?.items?.length > 0 ? (
						<List
							size="small"
							dataSource={details.items}
							className="ppq-attempt-questions-list"
							renderItem={(item, index) => (
								<List.Item className="ppq-attempt-question-item">
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
											<CheckCircleOutlined style={{ color: '#52c41a' }} />
										) : (
											<CloseCircleOutlined style={{ color: '#ff4d4f' }} />
										)}
										<span className="ppq-attempt-question-time">
											{formatTime(item.time_spent_ms)}
										</span>
									</div>
								</List.Item>
							)}
						/>
					) : (
						<p style={{ color: '#8c8c8c', textAlign: 'center', padding: '12px 0' }}>
							{loading
								? __('Loading questions...', 'pressprimer-quiz')
								: __('No question data available.', 'pressprimer-quiz')}
						</p>
					)}
				</div>
			</Spin>
		</Modal>
	);
};

export default AttemptDetailModal;
