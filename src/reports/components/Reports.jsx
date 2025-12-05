/**
 * Reports - Main Index Component
 *
 * Shows overview stats and available reports grid.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Spin, Alert, Card, Row, Col } from 'antd';
import {
	TrophyOutlined,
	ClockCircleOutlined,
	BarChartOutlined,
	LineChartOutlined,
	PieChartOutlined,
	TeamOutlined,
	LockOutlined,
} from '@ant-design/icons';

import OverviewCards from './OverviewCards';

/**
 * Reports Component
 *
 * @param {Object} props Component props
 * @param {Object} props.initialData Initial data from PHP
 */
const Reports = ({ initialData = {} }) => {
	const [overviewStats, setOverviewStats] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);

	// Fetch overview stats (all time for index page)
	const fetchOverviewStats = async () => {
		try {
			const response = await apiFetch({
				path: '/ppq/v1/statistics/overview',
			});

			if (response.success) {
				setOverviewStats(response.data);
			}
		} catch (err) {
			console.error('Failed to fetch overview stats:', err);
			setError(err.message || __('Failed to load overview statistics.', 'pressprimer-quiz'));
		}
	};

	// Initial data fetch
	useEffect(() => {
		const fetchData = async () => {
			setLoading(true);
			await fetchOverviewStats();
			setLoading(false);
		};

		fetchData();
	}, []);

	// Available reports
	const reports = [
		{
			key: 'quiz-performance',
			title: __('Quiz Performance', 'pressprimer-quiz'),
			description: __('See how each quiz is performing with attempt counts, average scores, and pass rates.', 'pressprimer-quiz'),
			icon: <TrophyOutlined />,
			color: '#faad14',
			available: true,
			href: 'admin.php?page=ppq-reports&report=quiz-performance',
		},
		{
			key: 'recent-attempts',
			title: __('Recent Attempts', 'pressprimer-quiz'),
			description: __('View individual student quiz attempts with scores, status, and detailed breakdowns.', 'pressprimer-quiz'),
			icon: <ClockCircleOutlined />,
			color: '#1890ff',
			available: true,
			href: 'admin.php?page=ppq-reports&report=recent-attempts',
		},
		{
			key: 'question-analysis',
			title: __('Question Analysis', 'pressprimer-quiz'),
			description: __('Identify difficult questions and see which ones students struggle with most.', 'pressprimer-quiz'),
			icon: <BarChartOutlined />,
			color: '#722ed1',
			available: false,
			badge: __('Pro', 'pressprimer-quiz'),
		},
		{
			key: 'student-progress',
			title: __('Student Progress', 'pressprimer-quiz'),
			description: __('Track individual student performance over time across all quizzes.', 'pressprimer-quiz'),
			icon: <LineChartOutlined />,
			color: '#52c41a',
			available: false,
			badge: __('Pro', 'pressprimer-quiz'),
		},
		{
			key: 'category-breakdown',
			title: __('Category Breakdown', 'pressprimer-quiz'),
			description: __('Analyze performance by question category to identify knowledge gaps.', 'pressprimer-quiz'),
			icon: <PieChartOutlined />,
			color: '#eb2f96',
			available: false,
			badge: __('Pro', 'pressprimer-quiz'),
		},
		{
			key: 'group-comparison',
			title: __('Group Comparison', 'pressprimer-quiz'),
			description: __('Compare quiz performance across different student groups.', 'pressprimer-quiz'),
			icon: <TeamOutlined />,
			color: '#13c2c2',
			available: false,
			badge: __('Educator', 'pressprimer-quiz'),
		},
	];

	// Get the plugin URL from localized data
	const pluginUrl = window.ppqReportsData?.pluginUrl || '';

	return (
		<div className="ppq-reports-container">
			{/* Header */}
			<div className="ppq-reports-header">
				<div className="ppq-reports-header-content">
					<h1>{__('Reports', 'pressprimer-quiz')}</h1>
					<p>{__('View quiz performance and student results.', 'pressprimer-quiz')}</p>
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

			<Spin spinning={loading} tip={__('Loading...', 'pressprimer-quiz')}>
				<div className="ppq-reports-content">
					{/* Overview Cards */}
					<OverviewCards stats={overviewStats} loading={loading} />

					{/* Available Reports Grid */}
					<div className="ppq-reports-section">
						<h2 className="ppq-reports-section-title">
							{__('Available Reports', 'pressprimer-quiz')}
						</h2>
						<Row gutter={[16, 16]}>
							{reports.map((report) => (
								<Col key={report.key} xs={24} sm={12} lg={8}>
									<Card
										className={`ppq-report-card ${!report.available ? 'ppq-report-card--locked' : ''}`}
										hoverable={report.available}
										onClick={() => {
											if (report.available && report.href) {
												window.location.href = report.href;
											}
										}}
									>
										<div className="ppq-report-card-icon" style={{ backgroundColor: report.color }}>
											{report.icon}
										</div>
										<div className="ppq-report-card-content">
											<h3 className="ppq-report-card-title">
												{report.title}
												{report.badge && (
													<span className="ppq-report-card-badge">
														<LockOutlined style={{ marginRight: 4 }} />
														{report.badge}
													</span>
												)}
											</h3>
											<p className="ppq-report-card-description">
												{report.description}
											</p>
										</div>
									</Card>
								</Col>
							))}
						</Row>
					</div>
				</div>
			</Spin>
		</div>
	);
};

export default Reports;
