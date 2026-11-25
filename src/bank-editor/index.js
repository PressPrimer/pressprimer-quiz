/**
 * Bank Editor Entry Point
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { createRoot } from '@wordpress/element';
import { message } from 'antd';
import BankEditor from './components/BankEditor';
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
	const root = document.getElementById('ppq-bank-editor-root');

	if (root) {
		// Get bank data from localized script
		const bankData = window.pressprimerQuizBankData || {};

		// wp-admin passes the localized data and the wp-admin navigation
		// adapter; the editor reads no window globals itself.
		const nav = createAdminNav(window.pressprimerQuizAdmin?.adminUrl || '');

		createRoot(root).render(
			<BankEditor
				key={`bank-${bankData.id || 'new'}`}
				data={bankData}
				surface="admin"
				nav={nav}
			/>
		);
	}
});
