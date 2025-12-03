/**
 * Onboarding Tour Entry Point
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { render } from '@wordpress/element';
import Onboarding from './components/Onboarding';
import './style.css';

/**
 * Initialize the onboarding tour
 */
const initOnboarding = () => {
	// Get the onboarding data from PHP
	const onboardingData = window.ppqOnboardingData || {};

	// Only render if onboarding should be shown
	if (!onboardingData.state?.should_show) {
		return;
	}

	// Create a container for the onboarding
	let container = document.getElementById('ppq-onboarding-root');

	if (!container) {
		container = document.createElement('div');
		container.id = 'ppq-onboarding-root';
		document.body.appendChild(container);
	}

	render(<Onboarding initialData={onboardingData} />, container);
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initOnboarding);

/**
 * Export relaunch function for external use (e.g., from Dashboard)
 * This resets the tour and starts from step 1
 */
window.ppqLaunchOnboarding = async () => {
	const onboardingData = window.ppqOnboardingData || {};

	// First reset the state via AJAX
	try {
		const formData = new FormData();
		formData.append('action', 'ppq_onboarding_progress');
		formData.append('onboarding_action', 'reset');
		formData.append('nonce', onboardingData.nonce || '');

		await fetch(onboardingData.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		});
	} catch (err) {
		console.error('Failed to reset onboarding:', err);
	}

	// Create container if needed
	let container = document.getElementById('ppq-onboarding-root');

	if (!container) {
		container = document.createElement('div');
		container.id = 'ppq-onboarding-root';
		document.body.appendChild(container);
	}

	// Force show from step 1
	const launchData = {
		...onboardingData,
		state: {
			...onboardingData.state,
			should_show: true,
			step: 1,
		},
	};

	render(<Onboarding initialData={launchData} />, container);
};
