/**
 * Onboarding Tour Steps Configuration
 *
 * Defines the interactive tour steps with selectors and content.
 * Uses resilient selectors that won't break if premium addons add menu items.
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
 * Get the admin URL for a page
 */
const getAdminUrl = (page) => {
	const baseUrl = window.ppqOnboardingData?.urls?.[page] || '';
	if (baseUrl) return baseUrl;

	// Fallback construction
	return `admin.php?page=ppq${page === 'dashboard' ? '' : '-' + page}`;
};

/**
 * Tour steps configuration
 *
 * Each step has:
 * - id: Unique identifier
 * - type: 'modal' or 'spotlight'
 * - title: Step title
 * - content: Step description (can be string or React node)
 * - selector: CSS selector for spotlight target (null for modals)
 * - position: Tooltip position (top, bottom, left, right)
 * - page: Which page this step should be shown on (null = any)
 * - pageUrl: URL to navigate to for this step
 * - fallbackSelector: Alternative selector if primary not found
 */
export const tourSteps = [
	// Step 1: Welcome Modal
	{
		id: 'welcome',
		type: STEP_TYPE.MODAL,
		title: __('Welcome to PressPrimer Quiz!', 'pressprimer-quiz'),
		content: __("Let's take a quick tour to help you get started. We'll show you the key features and how to create your first quiz.", 'pressprimer-quiz'),
		selector: null,
		page: null,
		pageUrl: null,
	},

	// Step 2: Main Menu
	{
		id: 'menu',
		type: STEP_TYPE.SPOTLIGHT,
		title: __('PressPrimer Quiz Menu', 'pressprimer-quiz'),
		content: __('This is your main menu for PressPrimer Quiz. From here you can access all the key areas: Dashboard, Quizzes, Questions, Question Banks, and Reports.', 'pressprimer-quiz'),
		// Use the top-level menu item which has a consistent structure
		selector: '#toplevel_page_ppq',
		fallbackSelector: '.toplevel_page_ppq',
		position: 'right',
		page: null, // Can be shown on any page
		pageUrl: null,
	},

	// Step 3: Dashboard Overview
	{
		id: 'dashboard',
		type: STEP_TYPE.SPOTLIGHT,
		title: __('Dashboard', 'pressprimer-quiz'),
		content: __('The Dashboard gives you a quick overview of your quiz activity including statistics, recent attempts, and quick actions to get things done.', 'pressprimer-quiz'),
		selector: '.ppq-dashboard-container',
		fallbackSelector: '#ppq-dashboard-root',
		position: 'bottom',
		page: 'ppq',
		pageUrl: getAdminUrl('dashboard'),
	},

	// Step 4: Questions Page
	{
		id: 'questions',
		type: STEP_TYPE.SPOTLIGHT,
		title: __('Questions', 'pressprimer-quiz'),
		content: __('This is where you create and manage your questions. You can create multiple choice, multiple answer, or true/false questions with rich text, images, and feedback.', 'pressprimer-quiz'),
		selector: '.ppq-question-editor-container, .ppq-questions-page',
		fallbackSelector: '#ppq-question-editor-root',
		position: 'bottom',
		page: 'ppq-questions',
		pageUrl: getAdminUrl('questions'),
	},

	// Step 5: Question Banks
	{
		id: 'banks',
		type: STEP_TYPE.SPOTLIGHT,
		title: __('Question Banks', 'pressprimer-quiz'),
		content: __('Question Banks help you organize questions by topic, chapter, or skill level. You can then build quizzes that pull questions dynamically from these banks.', 'pressprimer-quiz'),
		selector: '.ppq-bank-editor-container, .ppq-banks-page',
		fallbackSelector: '#ppq-bank-editor-root',
		position: 'bottom',
		page: 'ppq-banks',
		pageUrl: getAdminUrl('banks'),
	},

	// Step 6: Quiz Builder
	{
		id: 'quizzes',
		type: STEP_TYPE.SPOTLIGHT,
		title: __('Quiz Builder', 'pressprimer-quiz'),
		content: __("Create quizzes by hand-picking questions (Fixed mode) or setting rules to randomly select from banks (Dynamic mode). Configure time limits, passing scores, and customize feedback.", 'pressprimer-quiz'),
		selector: '.ppq-quiz-editor-container, .ppq-quizzes-page',
		fallbackSelector: '#ppq-quiz-editor-root',
		position: 'bottom',
		page: 'ppq-quizzes',
		pageUrl: getAdminUrl('quizzes'),
	},

	// Step 7: Reports
	{
		id: 'reports',
		type: STEP_TYPE.SPOTLIGHT,
		title: __('Reports & Analytics', 'pressprimer-quiz'),
		content: __('Track quiz performance, view student attempts, and identify areas for improvement. Filter by date, quiz, or user to find exactly what you need.', 'pressprimer-quiz'),
		selector: '.ppq-reports-container',
		fallbackSelector: '#ppq-reports-root',
		position: 'bottom',
		page: 'ppq-reports',
		pageUrl: getAdminUrl('reports'),
	},

	// Step 8: Completion Modal
	{
		id: 'complete',
		type: STEP_TYPE.MODAL,
		title: __("You're Ready!", 'pressprimer-quiz'),
		content: __("You've completed the tour! You now know the basics of PressPrimer Quiz. Start by creating some questions, organize them into banks, and build your first quiz.", 'pressprimer-quiz'),
		selector: null,
		page: null,
		pageUrl: null,
	},
];

/**
 * Get a step by index (1-based)
 */
export const getStep = (stepNumber) => {
	return tourSteps[stepNumber - 1] || null;
};

/**
 * Get the URL for a step's page
 */
export const getStepUrl = (stepNumber) => {
	const step = getStep(stepNumber);
	if (!step) return null;

	// For dynamic URL generation, re-call getAdminUrl
	if (step.pageUrl) return step.pageUrl;

	switch (step.id) {
		case 'dashboard':
			return getAdminUrl('dashboard');
		case 'questions':
			return getAdminUrl('questions');
		case 'banks':
			return getAdminUrl('banks');
		case 'quizzes':
			return getAdminUrl('quizzes');
		case 'reports':
			return getAdminUrl('reports');
		default:
			return null;
	}
};

/**
 * Check if we're on the correct page for a step
 */
export const isOnCorrectPage = (stepNumber) => {
	const step = getStep(stepNumber);
	if (!step || !step.page) return true; // Modal steps or no page requirement

	const urlParams = new URLSearchParams(window.location.search);
	const currentPage = urlParams.get('page');

	return currentPage === step.page;
};

/**
 * Get total number of steps
 */
export const getTotalSteps = () => tourSteps.length;
