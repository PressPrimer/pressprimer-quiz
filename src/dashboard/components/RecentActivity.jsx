/**
 * Recent Activity Component
 *
 * Shows recent quiz attempts.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';
import { Table, Tag, Empty, Button } from 'antd';
import {
	ClockCircleOutlined,
	CheckCircleOutlined,
	CloseCircleOutlined,
	UserOutlined,
	ArrowRightOutlined,
} from '@ant-design/icons';

/**
 * Format elapsed time
 *
 * @param {number} ms Milliseconds
 * @return {string} Formatted time
 */
const formatTime = (ms) => {
	if (!ms) return '-';
	const seconds = Math.floor(ms / 1000);
	const minutes = Math.floor(seconds / 60);
	const hours = Math.floor(minutes / 60);

	if (hours > 0) {
		return `${hours}h ${minutes % 60}m`;
	}
	if (minutes > 0) {
		return `${minutes}m ${seconds % 60}s`;
	}
	return `${seconds}s`;
};

/**
 * Format date relative to now
 *
 * @param {string} dateStr Date string (MySQL format, UTC)
 * @return {string} Formatted date
 */
const formatDate = (dateStr) => {
	if (!dateStr) return '-';

	// MySQL datetime format: "2024-12-02 10:30:00"
	// Append 'Z' to indicate UTC if not already present
	let normalizedDate = dateStr;
	if (!dateStr.endsWith('Z') && !dateStr.includes('+') && !dateStr.includes('T')) {
		// Convert MySQL format to ISO format with UTC timezone
		normalizedDate = dateStr.replace(' ', 'T') + 'Z';
	}

	const date = new Date(normalizedDate);

	// Check for invalid date
	if (isNaN(date.getTime())) {
		return '-';
	}

	const now = new Date();
	const diffMs = now - date;
	const diffMins = Math.floor(diffMs / 60000);
	const diffHours = Math.floor(diffMins / 60);
	const diffDays = Math.floor(diffHours / 24);

	if (diffMins < 1) {
		return __('Just now', 'pressprimer-quiz');
	}
	if (diffMins < 60) {
		return `${diffMins}m ${__('ago', 'pressprimer-quiz')}`;
	}
	if (diffHours < 24) {
		return `${diffHours}h ${__('ago', 'pressprimer-quiz')}`;
	}
	if (diffDays < 7) {
		return `${diffDays}d ${__('ago', 'pressprimer-quiz')}`;
	}

	return date.toLocaleDateString();
};

/**
 * Recent Activity Component
 *
 * @param {Object} props Component props
 * @param {Array} props.attempts Recent attempts data
 * @param {boolean} props.loading Loading state
 */
const RecentActivity = ({ attempts = [], loading }) => {
	const columns = [
		{
			title: __('Student', 'pressprimer-quiz'),
			dataIndex: 'student_name',
			key: 'student',
			render: (name, record) => (
				<div className="ppq-activity-student">
					<UserOutlined className="ppq-activity-student-icon" />
					<span>{name || record.guest_email || __('Guest', 'pressprimer-quiz')}</span>
				</div>
			),
		},
		{
			title: __('Quiz', 'pressprimer-quiz'),
			dataIndex: 'quiz_title',
			key: 'quiz',
			render: (title, record) => (
				<a href={`admin.php?page=ppq-quizzes&action=edit&id=${record.quiz_id}`}>
					{title}
				</a>
			),
		},
		{
			title: __('Score', 'pressprimer-quiz'),
			dataIndex: 'score_percent',
			key: 'score',
			width: 100,
			render: (score) => (
				<span className="ppq-activity-score">
					{score !== null ? `${Math.round(score)}%` : '-'}
				</span>
			),
		},
		{
			title: __('Status', 'pressprimer-quiz'),
			dataIndex: 'passed',
			key: 'status',
			width: 100,
			render: (passed) => {
				// Handle string "0"/"1" from database
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
			key: 'duration',
			width: 100,
			render: (ms) => (
				<span className="ppq-activity-duration">
					<ClockCircleOutlined style={{ marginRight: 4 }} />
					{formatTime(ms)}
				</span>
			),
		},
		{
			title: __('When', 'pressprimer-quiz'),
			dataIndex: 'finished_at',
			key: 'date',
			width: 120,
			render: (date) => (
				<span className="ppq-activity-date">
					{formatDate(date)}
				</span>
			),
		},
	];

	return (
		<div className="ppq-dashboard-card ppq-dashboard-card--large">
			<div className="ppq-dashboard-card-header">
				<h3 className="ppq-dashboard-card-title">
					<ClockCircleOutlined style={{ marginRight: 8 }} />
					{__('Recent Activity', 'pressprimer-quiz')}
				</h3>
				<Button
					type="link"
					href="admin.php?page=ppq-reports"
					icon={<ArrowRightOutlined />}
					className="ppq-dashboard-card-action"
				>
					{__('View All', 'pressprimer-quiz')}
				</Button>
			</div>

			{!loading && attempts.length === 0 ? (
				<Empty
					image={Empty.PRESENTED_IMAGE_SIMPLE}
					description={__('No recent activity', 'pressprimer-quiz')}
				/>
			) : (
				<Table
					columns={columns}
					dataSource={attempts}
					rowKey="id"
					loading={loading}
					pagination={false}
					size="middle"
					className="ppq-activity-table"
				/>
			)}
		</div>
	);
};

export default RecentActivity;
