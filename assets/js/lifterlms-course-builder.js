/**
 * LifterLMS Course Builder Integration
 *
 * Adds PPQ Quiz indicators to lessons in the course builder sidebar.
 * Quiz editing is done via the lesson edit page metabox.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

(function($) {
	'use strict';

	// Get configuration from localized data.
	var config = window.ppqLifterLMS || {};

	// Store lesson quiz associations from PHP.
	var lessonQuizzes = config.lessonQuizzes || {};

	// Track if we've initialized.
	var initialized = false;

	// Throttle timer for indicator updates.
	var indicatorUpdateTimer = null;
	var lastIndicatorUpdate = 0;

	/**
	 * Initialize the integration
	 */
	function init() {
		if (initialized) return;
		initialized = true;

		// Wait for LifterLMS builder to be ready.
		if (typeof window.LLMS === 'undefined') {
			initialized = false;
			setTimeout(init, 100);
			return;
		}

		// Set up indicator updates.
		setupIndicatorUpdates();
	}

	/**
	 * Set up lesson indicator updates
	 */
	function setupIndicatorUpdates() {
		// Use polling to keep indicators updated as LifterLMS re-renders.
		setInterval(function() {
			updateLessonIndicators();
		}, 1000);

		// Initial update.
		updateLessonIndicators();

		// Also use MutationObserver for faster response.
		var editorContainer = document.getElementById('llms-editor');
		if (editorContainer) {
			var observer = new MutationObserver(function() {
				updateLessonIndicators();
			});
			observer.observe(editorContainer, {
				childList: true,
				subtree: true
			});
		}
	}

	/**
	 * Update PPQ Quiz indicators on lesson items in the sidebar (throttled)
	 */
	function updateLessonIndicators() {
		// Throttle to run at most once every 500ms.
		var now = Date.now();
		if (now - lastIndicatorUpdate < 500) {
			if (!indicatorUpdateTimer) {
				indicatorUpdateTimer = setTimeout(function() {
					indicatorUpdateTimer = null;
					updateLessonIndicatorsNow();
				}, 500 - (now - lastIndicatorUpdate));
			}
			return;
		}

		updateLessonIndicatorsNow();
	}

	/**
	 * Actually update PPQ Quiz indicators (non-throttled)
	 */
	function updateLessonIndicatorsNow() {
		lastIndicatorUpdate = Date.now();

		// Find all lesson items in the sidebar.
		var lessons = document.querySelectorAll('.llms-lesson');

		lessons.forEach(function(lesson) {
			var lessonId = lesson.getAttribute('data-id');
			if (!lessonId || lessonId.indexOf('temp_') === 0) {
				return; // Skip temp lessons.
			}

			var numericId = parseInt(lessonId);

			// Check if this lesson has a PPQ quiz from our cache.
			var quizData = lessonQuizzes[numericId];

			// Find or create the indicator.
			var indicator = lesson.querySelector('.ppq-lesson-indicator');

			if (quizData && quizData.id && quizData.title) {
				if (!indicator) {
					indicator = document.createElement('div');
					indicator.className = 'ppq-lesson-indicator';
					lesson.appendChild(indicator);
				}
				indicator.textContent = 'PPQ: ' + quizData.title;
			} else if (indicator) {
				// Remove indicator if no quiz.
				indicator.remove();
			}
		});
	}

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})(jQuery);
