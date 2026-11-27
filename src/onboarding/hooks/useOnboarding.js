/**
 * useOnboarding Hook
 *
 * Tour state across the page loads the tour choreographs: the current step
 * (by id) and the run's records, persisted over admin-ajax.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useCallback } from '@wordpress/element';
import {
	STEP_TYPE,
	TOUR_PATH,
	getAdjacentStepId,
	getPath,
	getStepById,
	getStepNumber,
	getStepUrl,
	getTotalSteps,
	isOnCorrectPage,
} from '../tourSteps';
import { clearSetupSession, consumeAdvancePending, writeAdvancePending } from '../setupSession';

/**
 * Send a progress update
 *
 * keepalive lets the request finish while the page navigates or reloads.
 * Callers that navigate wait for it, so the landing step is stored first.
 *
 * @param {Object} initialData Localized data (nonce, ajaxUrl).
 * @param {string} action      start, progress, complete, skip or reset.
 * @param {Object} extra       Extra fields (step_id, run, permanent).
 * @return {Promise} Fetch promise (never rejects).
 */
const sendProgress = (initialData, action, extra = {}) => {
	const formData = new FormData();
	formData.append('action', 'pressprimer_quiz_onboarding_progress');
	formData.append('onboarding_action', action);
	formData.append('nonce', initialData.nonce || '');

	Object.entries(extra).forEach(([key, value]) => {
		formData.append(key, value);
	});

	return fetch(initialData.ajaxUrl || '/wp-admin/admin-ajax.php', {
		method: 'POST',
		body: formData,
		credentials: 'same-origin',
		keepalive: true,
	}).catch(() => {
		// Silently fail - progress is best-effort
	});
};

// The reconciliation heal is sent at most once per page load.
let healSent = false;

/**
 * The step to start this page load on
 *
 * When the previous page marked an advance pending and the server still
 * reports the step it left, the advance wins: the reload beat the request
 * that stored it, so it's sent again.
 *
 * @param {Object} initialData Localized data.
 * @return {string} Step id.
 */
const getInitialStepId = (initialData) => {
	const state = initialData.state || {};
	const path = getPath(state.run);
	let stepId = state.step_id || 'welcome';

	// Always consume: the marker only ever affects the next page load.
	const pending = consumeAdvancePending();

	if (pending && pending.from === stepId && getStepNumber(pending.to, path)) {
		stepId = pending.to;

		if (!healSent) {
			healSent = true;
			sendProgress(initialData, 'progress', { step_id: stepId });
		}
	}

	// A step this path doesn't have (or doesn't have yet) starts over.
	return getStepNumber(stepId, path) ? stepId : 'welcome';
};

/**
 * useOnboarding Hook
 *
 * @param {Object} initialData Initial data from PHP (pressprimerQuizOnboardingData)
 * @return {Object} Onboarding state and actions
 */
const useOnboarding = (initialData = {}) => {
	const [isLoading, setIsLoading] = useState(false);
	const [isActive, setIsActive] = useState(initialData.state?.should_show || false);
	const [run, setRun] = useState(() => initialData.state?.run || {});
	const [stepId, setStepId] = useState(() => getInitialStepId(initialData));

	const path = getPath(run);
	const step = getStepById(stepId);

	/**
	 * Move to a step, storing it (and any run fields) first
	 *
	 * @param {string}      targetId     Step id.
	 * @param {string}      action       'start' or 'progress'.
	 * @param {Object|null} runChanges   Run fields to merge (or the fresh run on start).
	 * @param {Object}      baseRun      Run the changes merge into.
	 */
	const goTo = useCallback((targetId, action, runChanges, baseRun) => {
		const target = getStepById(targetId);
		const nextRun = runChanges ? { ...baseRun, ...runChanges } : baseRun;
		const needsNavigation = target && target.type === STEP_TYPE.SPOTLIGHT && !isOnCorrectPage(target);

		setRun(nextRun);
		setStepId(targetId);

		// Hide the tour until the step's screen takes over.
		if (needsNavigation) {
			setIsLoading(true);
		}

		const extra = { step_id: targetId };
		if (runChanges) {
			extra.run = JSON.stringify(runChanges);
		}

		sendProgress(initialData, action, extra).then(() => {
			if (needsNavigation) {
				const url = getStepUrl(target, nextRun);
				if (url) {
					window.location.href = url;
				}
			}
		});
	}, [initialData]);

	/**
	 * Complete the tour
	 */
	const completeTour = useCallback(() => {
		setIsActive(false);
		sendProgress(initialData, 'complete');
	}, [initialData]);

	/**
	 * Start the build (from the welcome modal): a fresh run on a path
	 *
	 * @param {string} startPath TOUR_PATH value.
	 */
	const startTour = useCallback((startPath = TOUR_PATH.MANUAL) => {
		// A fresh run must never reconcile against a previous one.
		clearSetupSession();

		const freshRun = { path: startPath };
		const firstId = getAdjacentStepId('welcome', startPath, 1) || 'complete';

		goTo(firstId, 'start', freshRun, {});
	}, [goTo]);

	/**
	 * Go to the next step (the last one completes the tour)
	 */
	const nextStep = useCallback(() => {
		const nextId = getAdjacentStepId(stepId, path, 1);

		if (!nextId) {
			completeTour();
			return;
		}

		goTo(nextId, 'progress', null, run);
	}, [stepId, path, run, goTo, completeTour]);

	/**
	 * Go to the previous step
	 */
	const prevStep = useCallback(() => {
		const prevId = getAdjacentStepId(stepId, path, -1);

		if (prevId) {
			goTo(prevId, 'progress', null, run);
		}
	}, [stepId, path, run, goTo]);

	/**
	 * Advance because the step's real action happened
	 *
	 * @param {Object|null} runChanges Run fields the action produced.
	 */
	const advance = useCallback((runChanges = null) => {
		const nextId = getAdjacentStepId(stepId, path, 1);

		if (!nextId) {
			completeTour();
			return;
		}

		if (step && step.reloadsAfterAdvance) {
			writeAdvancePending(stepId, nextId);
		}

		goTo(nextId, 'progress', runChanges, run);
	}, [step, stepId, path, run, goTo, completeTour]);

	/**
	 * Keep run fields without moving
	 *
	 * @param {Object} runChanges Run fields to merge.
	 */
	const recordRun = useCallback((runChanges) => {
		setRun((current) => ({ ...current, ...runChanges }));
		sendProgress(initialData, 'progress', { run: JSON.stringify(runChanges) });
	}, [initialData]);

	/**
	 * Skip the tour (with optional permanent flag)
	 *
	 * @param {boolean} permanent Don't show it again.
	 */
	const skipTour = useCallback((permanent = false) => {
		setIsActive(false);
		sendProgress(initialData, 'skip', { permanent: permanent ? '1' : '' });
	}, [initialData]);

	/**
	 * Close the tour: it doesn't reopen until relaunched
	 */
	const closeTour = useCallback(() => {
		skipTour(false);
	}, [skipTour]);

	return {
		// State
		isLoading,
		isActive,
		step,
		stepId,
		run,
		path,
		stepNumber: getStepNumber(stepId, path),
		totalSteps: getTotalSteps(path),
		prevStepId: getAdjacentStepId(stepId, path, -1),

		// Actions
		startTour,
		nextStep,
		prevStep,
		advance,
		recordRun,
		skipTour,
		completeTour,
		closeTour,
	};
};

export default useOnboarding;
