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
				// If no report type or unknown type, check if it's an addon report
				// Addon reports render their own content in ppq-addon-report-root
				if (reportType) {
					// Create mount point for addon report and dispatch event
					root.innerHTML = '<div id="ppq-addon-report-root"></div>';
					document.dispatchEvent(new CustomEvent('ppq-addon-report-ready', {
						detail: { reportType },
					}));
					return;
				}
				Component = Reports;
		}

		render(<Component initialData={reportsData} />, root);
	}
});
