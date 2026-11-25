/**
 * Question Editor - React Entry Point
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { createRoot } from '@wordpress/element';
import { message } from 'antd';
import QuestionEditor from './components/QuestionEditor';
import { createAdminNav } from '../editors/adminNav';
import './styles/editor.css';

// Configure Ant Design message component
message.config({
	top: 50, // Position below WordPress admin bar (32px height + some padding)
	duration: 10, // Show messages for 10 seconds
	maxCount: 3, // Show max 3 messages at once
});

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
	const root = document.getElementById('ppq-question-editor-root');

	if (root) {
		const questionData = window.pressprimerQuizQuestionData || {};

		// wp-admin passes the localized data and the wp-admin navigation
		// adapter; the editor reads no window globals itself.
		const nav = createAdminNav(window.pressprimerQuizAdmin?.adminUrl || '');

		createRoot(root).render(
			<QuestionEditor
				key={`question-${questionData.id || 'new'}`}
				data={questionData}
				surface="admin"
				nav={nav}
			/>
		);
	}
});
