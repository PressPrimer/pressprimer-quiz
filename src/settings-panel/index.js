/**
 * Settings Panel - React Entry Point
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import { message } from 'antd';
import SettingsPage from './components/SettingsPage';
import './style.css';

// Configure Ant Design message component
message.config({
	top: 50,
	duration: 5,
	maxCount: 3,
});

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
	const root = document.getElementById('ppq-settings-root');

	if (root) {
		const settingsData = window.ppqSettingsData || {};

		render(
			<SettingsPage settingsData={settingsData} />,
			root
		);
	}
});
