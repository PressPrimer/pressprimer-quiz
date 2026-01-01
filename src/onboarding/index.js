/**
 * Onboarding Tour Entry Point
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { render, unmountComponentAtNode } from '@wordpress/element';
import Onboarding from './components/Onboarding';
import './style.css';

/**
 * Get or create the onboarding container
 */
const getContainer = () => {
	let container = document.getElementById('ppq-onboarding-root');

	if (!container) {
		container = document.createElement('div');
		container.id = 'ppq-onboarding-root';
		document.body.appendChild(container);
	}

	return container;
};

/**
 * Initialize the onboarding tour
 */
const initOnboarding = () => {
	// Get the onboarding data from PHP
	const onboardingData = window.pressprimerQuizOnboardingData || {};

	// Only render if onboarding should be shown
	if (!onboardingData.state?.should_show) {
		return;
	}

	const container = getContainer();
	render(<Onboarding initialData={onboardingData} />, container);
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initOnboarding);

/**
 * Export relaunch function for external use (e.g., from Dashboard)
 * This resets the tour and starts from step 1
 */
window.ppqLaunchOnboarding = async () => {
	const onboardingData = window.pressprimerQuizOnboardingData || {};

	// First reset the state via AJAX
	try {
		const formData = new FormData();
		formData.append('action', 'pressprimer_quiz_onboarding_progress');
		formData.append('onboarding_action', 'reset');
		formData.append('nonce', onboardingData.nonce || '');

		await fetch(onboardingData.ajaxUrl || '/wp-admin/admin-ajax.php', {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		});
	} catch (err) {
		// Silently fail - reset is best-effort
	}

	const container = getContainer();

	// Unmount existing instance first to ensure clean state
	try {
		unmountComponentAtNode(container);
	} catch (e) {
		// Ignore errors if nothing mounted
	}

	// Force show from step 1 with fresh state
	const launchData = {
		...onboardingData,
		state: {
			...onboardingData.state,
			should_show: true,
			step: 1,
		},
	};

	// Small delay to ensure unmount completes
	setTimeout(() => {
		render(<Onboarding initialData={launchData} key={Date.now()} />, container);
	}, 50);
};
