/**
 * useOnboarding Hook
 *
 * Manages the interactive onboarding tour state across page navigations.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useCallback } from '@wordpress/element';

/**
 * useOnboarding Hook
 *
 * @param {Object} initialData Initial data from PHP (pressprimerQuizOnboardingData)
 * @return {Object} Onboarding state and actions
 */
const useOnboarding = (initialData = {}) => {
	const [isLoading, setIsLoading] = useState(false);
	const [currentStep, setCurrentStep] = useState(initialData.state?.step || 1);
	const [isActive, setIsActive] = useState(initialData.state?.should_show || false);
	const [hasApiKey] = useState(initialData.state?.has_api_key || false);

	const totalSteps = initialData.state?.total_steps || 8;
	const nonce = initialData.nonce || '';
	const ajaxUrl = initialData.ajaxUrl || '/wp-admin/admin-ajax.php';

	/**
	 * Make AJAX request to update onboarding state
	 */
	const updateState = useCallback(async (action, data = {}) => {
		setIsLoading(true);

		try {
			const formData = new FormData();
			formData.append('action', 'pressprimer_quiz_onboarding_progress');
			formData.append('onboarding_action', action);
			formData.append('nonce', nonce);

			Object.entries(data).forEach(([key, value]) => {
				formData.append(key, value);
			});

			const response = await fetch(ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			});

			const result = await response.json();

			if (result.success) {
				return result.data;
			}
		} catch (err) {
			// Silently fail - onboarding state update is non-critical
		} finally {
			setIsLoading(false);
		}

		return null;
	}, [ajaxUrl, nonce]);

	/**
	 * Start the onboarding tour (from welcome modal)
	 */
	const startTour = useCallback(async () => {
		await updateState('start');
		setCurrentStep(2); // Move to first spotlight step
	}, [updateState]);

	/**
	 * Go to next step, optionally navigating to a URL
	 */
	const nextStep = useCallback(async (targetUrl = null) => {
		const newStep = currentStep + 1;

		if (newStep > totalSteps) {
			// Complete the tour
			await updateState('complete');
			setIsActive(false);
			return;
		}

		await updateState('progress', { step: newStep });
		setCurrentStep(newStep);

		// Navigate to target URL if provided and not already there
		if (targetUrl) {
			const currentUrl = window.location.href;
			// Check if we need to navigate (simple check - URL doesn't contain the page slug)
			if (!currentUrl.includes(targetUrl.split('?page=')[1]?.split('&')[0])) {
				window.location.href = targetUrl;
			}
		}
	}, [currentStep, totalSteps, updateState]);

	/**
	 * Go to previous step, optionally navigating to a URL
	 */
	const prevStep = useCallback(async (targetUrl = null) => {
		const newStep = currentStep - 1;

		if (newStep < 1) {
			return;
		}

		await updateState('progress', { step: newStep });
		setCurrentStep(newStep);

		// Navigate to target URL if provided
		if (targetUrl) {
			const currentUrl = window.location.href;
			if (!currentUrl.includes(targetUrl.split('?page=')[1]?.split('&')[0])) {
				window.location.href = targetUrl;
			}
		}
	}, [currentStep, updateState]);

	/**
	 * Skip the tour (with optional permanent flag)
	 */
	const skipTour = useCallback(async (permanent = false) => {
		await updateState('skip', { permanent: permanent ? '1' : '' });
		setIsActive(false);
	}, [updateState]);

	/**
	 * Complete the tour
	 */
	const completeTour = useCallback(async () => {
		await updateState('complete');
		setIsActive(false);
	}, [updateState]);

	/**
	 * Close/dismiss the tour temporarily
	 */
	const closeTour = useCallback(async () => {
		// Just mark as completed so it doesn't show again
		await updateState('complete');
		setIsActive(false);
	}, [updateState]);

	/**
	 * Reset and relaunch tour from step 1
	 */
	const relaunchTour = useCallback(async () => {
		await updateState('reset');
		setCurrentStep(1);
		setIsActive(true);
	}, [updateState]);

	return {
		// State
		isLoading,
		isActive,
		currentStep,
		totalSteps,
		hasApiKey,

		// Actions
		startTour,
		nextStep,
		prevStep,
		skipTour,
		completeTour,
		closeTour,
		relaunchTour,
	};
};

export default useOnboarding;
