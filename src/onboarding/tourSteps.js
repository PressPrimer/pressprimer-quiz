/**
 * Onboarding Tour Steps Configuration
 *
 * The guided first-quiz build (3.2): the admin builds a real quiz in the
 * real screens. They make a question first (written by hand, or generated
 * into a new bank when an AI provider is set up), then a quiz around it, a
 * page for it, and an attempt of their own.
 *
 * Each step has:
 * - id: Step id. The server keeps the same list and rejects unknown ids.
 * - type: STEP_TYPE.MODAL (renders on any tour screen) or STEP_TYPE.SPOTLIGHT
 * - paths: The question paths the step belongs to (default: both)
 * - title, content: Copy
 * - selector: Spotlight target; comma-separated alternatives
 * - fallbackSelector: Container framed when the target never appears
 * - position: Preferred tooltip side (top, bottom, left, right)
 * - fitContent: Frame the target's children instead of its padded box
 * - matches: ( URLSearchParams ) => boolean, the screen the step lives on.
 *   Handle both action=new and action=edit: the URL changes after a first save.
 * - pageUrl: URL, or ( run ) => URL, that opens the step's screen
 * - advanceOn: { event, when, record }, the editor event that completes the
 *   step. record( detail, run ) returns run fields to keep; when( detail, run )
 *   decides whether the event completes the step (default: always). Next
 *   stays disabled until it does.
 * - nextDisabledReason: Shown on the disabled Next
 * - reloadsAfterAdvance: The screen reloads or redirects right after the
 *   event, so the advance is marked pending and the next load reconciles it
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * Step types
 */
export const STEP_TYPE = {
	MODAL: 'modal',
	SPOTLIGHT: 'spotlight',
};

/**
 * Question paths
 */
export const TOUR_PATH = {
	MANUAL: 'manual',
	AI: 'ai',
};

/**
 * Get an admin URL from the localized map
 *
 * @param {string} key URL key (dashboard, questions, banks, quizzes, reports, settings).
 * @return {string} Admin URL.
 */
export const getAdminUrl = (key) => {
	const baseUrl = window.pressprimerQuizOnboardingData?.urls?.[key] || '';
	if (baseUrl) return baseUrl;

	// Fallback construction
	return `admin.php?page=pressprimer-quiz${key === 'dashboard' ? '' : '-' + key}`;
};

/**
 * Build a screen predicate for a step's matches
 *
 * @param {string}        page    The admin page slug.
 * @param {string[]|null} actions Allowed action values ('' for none), or null for any.
 * @return {Function} ( URLSearchParams ) => boolean.
 */
export const onScreen = (page, actions = null) => (urlParams) =>
	urlParams.get('page') === page && (!actions || actions.includes(urlParams.get('action') || ''));

/**
 * Tour steps, both paths, in tour order
 *
 * Prompt 8.2 adds the build steps between the welcome and the finish.
 */
export const TOUR_STEPS = [
	// Welcome Modal
	{
		id: 'welcome',
		type: STEP_TYPE.MODAL,
		title: __('Welcome to PressPrimer Quiz!', 'pressprimer-quiz'),
		content: __("Let's take a quick tour to help you get started. We'll show you the key features and how to create your first quiz.", 'pressprimer-quiz'),
	},

	// Completion Modal
	{
		id: 'complete',
		type: STEP_TYPE.MODAL,
		title: __("You're Ready!", 'pressprimer-quiz'),
		content: __("You've completed the tour! You now know the basics of PressPrimer Quiz. Start by creating some questions, organize them into banks, and build your first quiz.", 'pressprimer-quiz'),
	},
];

/**
 * The run's question path
 *
 * @param {Object} run Run record.
 * @return {string} TOUR_PATH value.
 */
export const getPath = (run) => (run && run.path === TOUR_PATH.AI ? TOUR_PATH.AI : TOUR_PATH.MANUAL);

/**
 * The steps of one path, in order
 *
 * @param {string} path TOUR_PATH value.
 * @return {Object[]} Steps.
 */
export const getSequence = (path) => TOUR_STEPS.filter((step) => !step.paths || step.paths.includes(path));

/**
 * Get a step by id
 *
 * @param {string} stepId Step id.
 * @return {Object|null} Step or null.
 */
export const getStepById = (stepId) => TOUR_STEPS.find((step) => step.id === stepId) || null;

/**
 * A step's 1-based number on a path
 *
 * @param {string} stepId Step id.
 * @param {string} path   TOUR_PATH value.
 * @return {number} Step number, or 0 when the step isn't on the path.
 */
export const getStepNumber = (stepId, path) => getSequence(path).findIndex((step) => step.id === stepId) + 1;

/**
 * Number of steps on a path
 *
 * @param {string} path TOUR_PATH value.
 * @return {number} Step count.
 */
export const getTotalSteps = (path) => getSequence(path).length;

/**
 * The step before or after another on a path
 *
 * @param {string} stepId Step id.
 * @param {string} path   TOUR_PATH value.
 * @param {number} offset 1 for the next step, -1 for the previous one.
 * @return {string|null} Step id, or null past either end.
 */
export const getAdjacentStepId = (stepId, path, offset) => {
	const sequence = getSequence(path);
	const target = sequence[sequence.findIndex((step) => step.id === stepId) + offset];

	return target ? target.id : null;
};

/**
 * Get the URL that opens a step's screen
 *
 * @param {Object} step Step.
 * @param {Object} run  Run record.
 * @return {string} URL, or '' for steps without a screen.
 */
export const getStepUrl = (step, run = {}) => {
	if (!step || !step.pageUrl) return '';

	return typeof step.pageUrl === 'function' ? step.pageUrl(run) : step.pageUrl;
};

/**
 * Check whether the current page hosts a step
 *
 * Modal steps render on any tour screen; spotlight steps only on theirs.
 *
 * @param {Object} step Step.
 * @return {boolean} True when the step can render here.
 */
export const isOnCorrectPage = (step) => {
	if (!step || step.type !== STEP_TYPE.SPOTLIGHT || typeof step.matches !== 'function') {
		return true;
	}

	return step.matches(new URLSearchParams(window.location.search));
};
