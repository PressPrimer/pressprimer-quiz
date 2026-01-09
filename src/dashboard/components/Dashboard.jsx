/**
 * Dashboard - Main Component
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Spin,
	Alert,
} from 'antd';

import StatsCards from './StatsCards';
import ActivityChart from './ActivityChart';
import PopularQuizzes from './PopularQuizzes';
import QuickActions from './QuickActions';
import RecentActivity from './RecentActivity';

/**
 * Dashboard Component
 *
 * @param {Object} props Component props
 * @param {Object} props.initialData Initial dashboard data from PHP
 */
const Dashboard = ({ initialData = {} }) => {
	const [stats, setStats] = useState(null);
	const [recentAttempts, setRecentAttempts] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);

	// Fetch dashboard data
	useEffect(() => {
		const fetchData = async () => {
			try {
				setLoading(true);

				// Fetch stats and recent attempts in parallel
				const [statsResponse, attemptsResponse] = await Promise.all([
					apiFetch({ path: '/ppq/v1/statistics/dashboard' }),
					apiFetch({ path: '/ppq/v1/statistics/attempts?per_page=10' }),
				]);

				if (statsResponse.success) {
					setStats(statsResponse.data);
				}

				if (attemptsResponse.success) {
					setRecentAttempts(attemptsResponse.data.items || []);
				}

				setError(null);
			} catch (err) {
				setError(err.message || __('Failed to load dashboard data.', 'pressprimer-quiz'));
			} finally {
				setLoading(false);
			}
		};

		fetchData();
	}, []);

	/**
	 * Launch the onboarding tour
	 */
	const handleLaunchTour = useCallback(async () => {
		// Reset onboarding state via AJAX to start from step 1
		const onboardingData = window.pressprimerQuizOnboardingData || {};

		try {
			// Call reset action first
			const formData = new FormData();
			formData.append('action', 'pressprimer_quiz_onboarding_progress');
			formData.append('onboarding_action', 'reset');
			formData.append('nonce', onboardingData.nonce || '');

			await fetch(onboardingData.ajaxUrl || '/wp-admin/admin-ajax.php', {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			});
		} catch (err) {
			// Silently fail - reset is best-effort
		}

		// Launch the onboarding wizard
		if (typeof window.ppqLaunchOnboarding === 'function') {
			window.ppqLaunchOnboarding();
		}
	}, []);

	// Get the plugin URL and teacher flag from localized data
	const dashboardData = window.pressprimerQuizDashboardData || {};
	const pluginUrl = dashboardData.pluginUrl || '';
	const isTeacher = dashboardData.isTeacher || false;

	return (
		<div className="ppq-dashboard-container">
			{/* Header */}
			<div className="ppq-dashboard-header">
				<div className="ppq-dashboard-header-content">
					<img
						src={`${pluginUrl}assets/images/PressPrimer-Logo-White.svg`}
						alt="PressPrimer Quiz"
						className="ppq-dashboard-logo"
					/>
					<p>{__('Welcome to PressPrimer Quiz! Here\'s an overview of recent quiz activity.', 'pressprimer-quiz')}</p>
				</div>
			</div>

			{/* Main Content */}
			{error && (
				<Alert
					message={__('Error', 'pressprimer-quiz')}
					description={error}
					type="error"
					showIcon
					style={{ marginBottom: 24 }}
				/>
			)}

			<Spin spinning={loading} tip={__('Loading...', 'pressprimer-quiz')}>
				<div className="ppq-dashboard-content">
					{/* Top Row: Stats Cards (2 cols) + Quick Actions (1 col) */}
					<div className="ppq-dashboard-top-row">
						<StatsCards stats={stats} loading={loading} isTeacher={isTeacher} />
						<QuickActions urls={initialData.urls || {}} onLaunchTour={handleLaunchTour} />
					</div>

					{/* Activity Chart */}
					<ActivityChart loading={loading} />

					{/* Main Grid */}
					<div className="ppq-dashboard-grid">
						{/* Left Column */}
						<div className="ppq-dashboard-main">
							{/* Recent Activity */}
							<RecentActivity
								attempts={recentAttempts}
								loading={loading}
							/>
						</div>

						{/* Right Column */}
						<div className="ppq-dashboard-sidebar">
							{/* Popular Quizzes */}
							<PopularQuizzes
								quizzes={stats?.popular_quizzes || []}
								loading={loading}
							/>
						</div>
					</div>
				</div>
			</Spin>
		</div>
	);
};

export default Dashboard;
