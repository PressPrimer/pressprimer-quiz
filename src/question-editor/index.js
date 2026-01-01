/**
 * Question Editor - React Entry Point
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import { message } from 'antd';
import QuestionEditor from './components/QuestionEditor';
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

		render(
			<QuestionEditor questionData={questionData} />,
			root
		);
	}
});
