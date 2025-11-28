/**
 * Bank Editor Entry Point
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import { message } from 'antd';
import BankEditor from './components/BankEditor';
import './style.css';

// Configure Ant Design message component
message.config({
	top: 50, // Position below WordPress admin bar (32px height + some padding)
	duration: 10, // Show messages for 10 seconds
	maxCount: 3, // Show max 3 messages at once
});

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
	const root = document.getElementById('ppq-bank-editor-root');

	if (root) {
		// Get bank data from localized script
		const bankData = window.ppqBankData || {};

		// Render the editor
		render(<BankEditor bankData={bankData} />, root);
	}
});
