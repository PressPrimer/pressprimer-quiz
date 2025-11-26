/**
 * Question Editor - React Entry Point
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import QuestionEditor from './components/QuestionEditor';
import './styles/editor.css';

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
	const root = document.getElementById('ppq-question-editor-root');

	if (root) {
		const questionData = window.ppqQuestionData || {};

		render(
			<QuestionEditor questionData={questionData} />,
			root
		);
	}
});
