/**
 * AI Generation Entry Point (wp-admin bank page)
 *
 * Mounts the AI generation panel in the bank page's "Generate with AI" tab.
 * When the panel adds questions to this bank, the page's PHP question list
 * and total are reloaded in place, so generated questions not yet saved stay
 * in the panel. The dashboard's bank editor renders the same panel from the
 * editors bundle.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { message } from 'antd';
import AiGenerationPanel from './components/AiGenerationPanel';
import { toId } from '../editors/events';
import { debugError } from '../utils/debug';
import './style.css';

message.config({
	top: 50, // Position below WordPress admin bar (32px height + some padding)
	duration: 5, // Show messages for 5 seconds
	maxCount: 3, // Show max 3 messages at once
});

/**
 * Reload the bank page's "Questions in Bank" section and total from the
 * server, keeping the page's filters and paging.
 */
const refreshBankQuestions = async () => {
	try {
		const response = await window.fetch(window.location.href, { credentials: 'same-origin' });
		if (!response.ok) {
			throw new Error(`HTTP ${response.status}`);
		}

		const page = new window.DOMParser().parseFromString(await response.text(), 'text/html');
		const nextList = page.getElementById('questions-in-bank');
		const list = document.getElementById('questions-in-bank');
		if (!nextList || !list) {
			throw new Error('The questions list is missing.');
		}

		// The page's own markup; its scripts use delegated handlers.
		list.innerHTML = nextList.innerHTML;

		const nextTotal = page.querySelector('.ppq-bank-total-count');
		const total = document.querySelector('.ppq-bank-total-count');
		if (nextTotal && total) {
			total.textContent = nextTotal.textContent;
		}
	} catch (error) {
		debugError('Failed to refresh the bank questions:', error);
		message.warning(__('The questions were saved, but the list couldn’t be refreshed. Reload the page to see them.', 'pressprimer-quiz'));
	}
};

const init = () => {
	const root = document.getElementById('ppq-ai-generation-root');
	if (!root) {
		return;
	}

	const bankId = toId(root.dataset.bankId);

	document.addEventListener('ppq-bank-questions-added', (event) => {
		if (bankId && toId(event.detail?.bankId) === bankId) {
			refreshBankQuestions();
		}
	});

	createRoot(root).render(<AiGenerationPanel bankId={bankId} />);
};

if ('loading' === document.readyState) {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}
