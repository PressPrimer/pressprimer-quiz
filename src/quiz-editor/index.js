/**
 * Quiz Editor Entry Point
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { createRoot } from '@wordpress/element';
import { message } from 'antd';
import QuizEditor from './components/QuizEditor';
import { createAdminNav } from '../editors/adminNav';
import './style.css';

// Configure Ant Design message component
message.config({
	top: 50, // Position below WordPress admin bar (32px height + some padding)
	duration: 10, // Show messages for 10 seconds
	maxCount: 3, // Show max 3 messages at once
});

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
	const root = document.getElementById('ppq-quiz-editor-root');

	if (root) {
		// Get quiz data from localized script
		const quizData = window.pressprimerQuizQuizData || {};

		// wp-admin passes the localized data and the wp-admin navigation
		// adapter; the editor reads no window globals itself.
		const nav = createAdminNav(window.pressprimerQuizAdmin?.adminUrl || '');

		createRoot(root).render(
			<QuizEditor
				key={`quiz-${quizData.id || 'new'}`}
				data={quizData}
				surface="admin"
				nav={nav}
			/>
		);
	}
});
