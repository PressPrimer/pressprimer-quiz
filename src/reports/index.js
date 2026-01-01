/**
 * Reports App Entry Point
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import Reports from './components/Reports';
import QuizPerformanceReport from './components/QuizPerformanceReport';
import RecentAttemptsReport from './components/RecentAttemptsReport';
import './style.css';

document.addEventListener('DOMContentLoaded', () => {
	const root = document.getElementById('ppq-reports-root');

	if (root) {
		const reportsData = window.pressprimerQuizReportsData || {};

		// Check URL for specific report
		const urlParams = new URLSearchParams(window.location.search);
		const reportType = urlParams.get('report');

		let Component;

		switch (reportType) {
			case 'quiz-performance':
				Component = QuizPerformanceReport;
				break;
			case 'recent-attempts':
				Component = RecentAttemptsReport;
				break;
			default:
				Component = Reports;
		}

		render(<Component initialData={reportsData} />, root);
	}
});
