/**
 * PressPrimer Quiz - Frontend JavaScript
 *
 * Handles quiz interactions including starting quizzes, saving answers,
 * auto-save, timer management, and submission.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Quiz Landing Page Handler
	 */
	const QuizLanding = {
		/**
		 * Initialize landing page functionality
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			$(document).on('click', '.ppq-start-quiz-button', this.handleStartQuiz.bind(this));
		},

		/**
		 * Handle Start Quiz button click
		 *
		 * @param {Event} e Click event
		 */
		handleStartQuiz: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const quizId = $button.data('quiz-id');

			// Validate quiz ID
			if (!quizId) {
				this.showError(ppqQuiz.strings.error);
				return;
			}

			// Check if user is guest and get email
			const $emailInput = $('#ppq-guest-email');
			let guestEmail = '';

			if ($emailInput.length) {
				guestEmail = $emailInput.val().trim();

				// Validate email if provided
				if (guestEmail && !this.isValidEmail(guestEmail)) {
					this.showError(ppqQuiz.strings.emailRequired);
					$emailInput.focus();
					return;
				}
			}

			// Disable button and show loading state
			$button.prop('disabled', true).addClass('ppq-loading');

			// Make AJAX request to create attempt
			$.ajax({
				url: ppqQuiz.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ppq_start_quiz',
					nonce: ppqQuiz.nonce,
					quiz_id: quizId,
					guest_email: guestEmail
				},
				success: (response) => {
					if (response.success && response.data.attempt_id) {
						// Redirect to quiz interface
						const attemptUrl = this.buildAttemptUrl(response.data.attempt_id);
						window.location.href = attemptUrl;
					} else {
						// Show error message
						const errorMessage = response.data && response.data.message
							? response.data.message
							: ppqQuiz.strings.error;
						this.showError(errorMessage);
						$button.prop('disabled', false).removeClass('ppq-loading');
					}
				},
				error: () => {
					this.showError(ppqQuiz.strings.error);
					$button.prop('disabled', false).removeClass('ppq-loading');
				}
			});
		},

		/**
		 * Validate email address
		 *
		 * @param {string} email Email to validate
		 * @return {boolean} True if valid
		 */
		isValidEmail: function(email) {
			// Basic email validation regex
			const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			return re.test(email);
		},

		/**
		 * Build attempt URL
		 *
		 * @param {number} attemptId Attempt ID
		 * @return {string} Attempt URL
		 */
		buildAttemptUrl: function(attemptId) {
			const url = new URL(window.location.href);
			url.searchParams.set('attempt', attemptId);
			return url.toString();
		},

		/**
		 * Show error message
		 *
		 * @param {string} message Error message
		 */
		showError: function(message) {
			// Remove any existing error notices
			$('.ppq-quiz-actions .ppq-notice-error').remove();

			// Create and insert error notice
			const $notice = $('<div>')
				.addClass('ppq-notice ppq-notice-error')
				.html('<p>' + this.escapeHtml(message) + '</p>')
				.hide()
				.prependTo('.ppq-quiz-actions')
				.slideDown(200);

			// Auto-remove after 5 seconds
			setTimeout(() => {
				$notice.slideUp(200, function() {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Escape HTML to prevent XSS
		 *
		 * @param {string} text Text to escape
		 * @return {string} Escaped text
		 */
		escapeHtml: function(text) {
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.replace(/[&<>"']/g, function(m) { return map[m]; });
		}
	};

	/**
	 * Quiz Interface Handler
	 *
	 * Handles the quiz-taking interface including:
	 * - Question navigation
	 * - Answer selection
	 * - Timer management
	 * - Quiz submission
	 */
	const QuizInterface = {
		// State
		$container: null,
		attemptId: null,
		quizId: null,
		currentQuestionIndex: 0,
		totalQuestions: 0,
		timeLimit: null,
		timeRemaining: null,
		timerInterval: null,
		isSubmitting: false,
		isAutoSubmit: false,

		// Auto-save state
		autoSaveTimer: null,
		autoSaveDelay: 1000, // 1 second delay
		pendingSaves: {},
		isSaving: false,

		// Active time tracking
		activeElapsedMs: 0,
		lastActiveTimestamp: null,
		isTabActive: true,
		heartbeatInterval: null,
		heartbeatDelay: 30000, // 30 seconds

		// Edge case handling
		timerPaused: false,
		hasUnsavedChanges: false,
		isOnline: true,

		/**
		 * Initialize quiz interface
		 */
		init: function() {
			this.$container = $('.ppq-quiz-interface');

			if (!this.$container.length) {
				return;
			}

			// Get quiz data
			this.attemptId = this.$container.data('attempt-id');
			this.quizId = this.$container.data('quiz-id');
			this.timeLimit = this.$container.data('time-limit');
			this.timeRemaining = this.$container.data('time-remaining');
			this.totalQuestions = $('.ppq-question').length;

			// Initialize components
			this.bindEvents();
			this.bindEdgeCaseHandlers();
			this.syncSelectionState();
			this.updateProgress();
			this.updateNavButtons();

			// Start active time tracking
			this.startActiveTimeTracking();

			// Start timer if timed quiz
			if (this.timeLimit && this.timeRemaining) {
				this.startTimer();
			}

			// Show first question
			this.showQuestion(0);
		},

		/**
		 * Sync visual selection state with actual input state
		 *
		 * Ensures ppq-selected class matches checked inputs on all questions.
		 */
		syncSelectionState: function() {
			$('.ppq-answer-input').each(function() {
				const $input = $(this);
				const $option = $input.closest('.ppq-answer-option');

				if ($input.is(':checked')) {
					$option.addClass('ppq-selected');
				} else {
					$option.removeClass('ppq-selected');
				}
			});
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			const self = this;

			// Navigation buttons
			$('#ppq-prev-button').on('click', function(e) {
				e.preventDefault();
				self.previousQuestion();
			});

			$('#ppq-next-button').on('click', function(e) {
				e.preventDefault();
				self.nextQuestion();
			});

			// Submit button
			$('#ppq-submit-button').on('click', function(e) {
				e.preventDefault();
				self.submitQuiz();
			});

			// Answer selection
			$(document).on('change', '.ppq-answer-input', function() {
				self.handleAnswerChange($(this));
			});

			// Handle clicks on answer options
			// Since the input is hidden (width/height: 0), we need to manually handle clicks
			// on the visible parts of the label (the custom radio/checkbox and text)
			$(document).on('click', '.ppq-answer-option', function(e) {
				// If clicking directly on the input, let native behavior handle it
				if (e.target.tagName === 'INPUT') {
					return;
				}

				// Prevent the label's native "click input" behavior since we'll handle it manually
				e.preventDefault();

				const $input = $(this).find('.ppq-answer-input');

				if ($input.attr('type') === 'checkbox') {
					// Toggle checkbox
					$input.prop('checked', !$input.prop('checked'));
				} else {
					// Select radio
					$input.prop('checked', true);
				}

				// Call handler directly instead of triggering synthetic event
				self.handleAnswerChange($input);
			});

			// Keyboard navigation
			$(document).on('keydown', function(e) {
				// Left arrow - previous
				if (e.key === 'ArrowLeft' && !$('#ppq-prev-button').prop('disabled')) {
					e.preventDefault();
					self.previousQuestion();
				}
				// Right arrow - next
				else if (e.key === 'ArrowRight' && $('#ppq-next-button').is(':visible')) {
					e.preventDefault();
					self.nextQuestion();
				}
			});
		},

		/**
		 * Bind edge case handlers
		 */
		bindEdgeCaseHandlers: function() {
			const self = this;

			// Warn before leaving page with unsaved changes
			$(window).on('beforeunload', function(e) {
				// Don't warn if quiz is being submitted
				if (self.isSubmitting) {
					return undefined;
				}

				// Don't warn if no unsaved changes
				if (!self.hasUnsavedChanges && Object.keys(self.pendingSaves).length === 0) {
					return undefined;
				}

				// Show warning
				const message = 'You have unsaved answers. Are you sure you want to leave?';
				e.returnValue = message;
				return message;
			});

			// Handle visibility change (tab switching)
			$(document).on('visibilitychange', function() {
				if (document.hidden) {
					self.handleTabHidden();
				} else {
					self.handleTabVisible();
				}
			});

			// Handle online/offline status
			$(window).on('online', function() {
				self.handleOnline();
			});

			$(window).on('offline', function() {
				self.handleOffline();
			});

			// Prevent back button during quiz
			if (window.history && window.history.pushState) {
				// Add a dummy state
				window.history.pushState('quiz-active', null, window.location.href);

				// Handle popstate (back button)
				$(window).on('popstate', function(e) {
					if (!self.isSubmitting) {
						// Push state back to prevent navigation
						window.history.pushState('quiz-active', null, window.location.href);

						// Confirm if user wants to abandon quiz
						if (confirm('Are you sure you want to leave this quiz? Your progress is saved, but you can only resume if the time limit allows.')) {
							// Allow navigation
							window.history.back();
						}
					}
				});
			}
		},

		/**
		 * Handle tab hidden (user switched away)
		 */
		handleTabHidden: function() {
			// Pause active time tracking
			this.pauseActiveTimeTracking();

			// Force save any pending answers (includes active time)
			if (Object.keys(this.pendingSaves).length > 0) {
				if (this.autoSaveTimer) {
					clearTimeout(this.autoSaveTimer);
				}
				this.performAutoSave();
			} else {
				// No pending answers, but sync the active time
				this.syncActiveTime();
			}
		},

		/**
		 * Handle tab visible (user switched back)
		 */
		handleTabVisible: function() {
			// Resume active time tracking
			this.resumeActiveTimeTracking();

			// Check if quiz timed out while tab was hidden
			if (this.timeLimit && this.timeRemaining <= 0) {
				this.handleTimeExpired();
			}
		},

		/**
		 * Handle online status restored
		 */
		handleOnline: function() {
			this.isOnline = true;

			// Retry pending saves
			if (Object.keys(this.pendingSaves).length > 0) {
				this.performAutoSave();
			}

			// Update indicator
			$('.ppq-offline-indicator').fadeOut();
		},

		/**
		 * Handle offline status
		 */
		handleOffline: function() {
			this.isOnline = false;

			// Show offline indicator
			let $indicator = $('.ppq-offline-indicator');
			if (!$indicator.length) {
				$indicator = $('<div class="ppq-offline-indicator">You are offline. Answers will be saved when connection is restored.</div>')
					.appendTo('body');
			}
			$indicator.fadeIn();
		},

		/**
		 * Show specific question
		 *
		 * @param {number} index Question index (0-based)
		 */
		showQuestion: function(index) {
			if (index < 0 || index >= this.totalQuestions) {
				return;
			}

			// Hide all questions
			$('.ppq-question').hide();

			// Show current question
			$('.ppq-question[data-question-index="' + index + '"]').fadeIn(200);

			// Update current index
			this.currentQuestionIndex = index;

			// Update UI
			this.updateProgress();
			this.updateNavButtons();
		},

		/**
		 * Navigate to previous question
		 */
		previousQuestion: function() {
			if (this.currentQuestionIndex > 0) {
				this.showQuestion(this.currentQuestionIndex - 1);
			}
		},

		/**
		 * Navigate to next question
		 */
		nextQuestion: function() {
			if (this.currentQuestionIndex < this.totalQuestions - 1) {
				this.showQuestion(this.currentQuestionIndex + 1);
			}
		},

		/**
		 * Update progress indicator
		 */
		updateProgress: function() {
			const questionNumber = this.currentQuestionIndex + 1;
			const percentComplete = (questionNumber / this.totalQuestions) * 100;

			// Update question number
			$('.ppq-current-question').text(questionNumber);

			// Update progress bar
			$('#ppq-progress-bar').css('width', percentComplete + '%');
		},

		/**
		 * Update navigation button states
		 */
		updateNavButtons: function() {
			const $prevButton = $('#ppq-prev-button');
			const $nextButton = $('#ppq-next-button');
			const $submitButton = $('#ppq-submit-button');

			// Previous button - disabled on first question
			if (this.currentQuestionIndex === 0) {
				$prevButton.prop('disabled', true);
			} else {
				$prevButton.prop('disabled', false);
			}

			// On last question - show submit, hide next
			if (this.currentQuestionIndex === this.totalQuestions - 1) {
				$nextButton.hide();
				$submitButton.show();
			} else {
				$nextButton.show();
				$submitButton.hide();
			}
		},

		/**
		 * Handle answer selection change
		 *
		 * @param {jQuery} $input The input that changed
		 */
		handleAnswerChange: function($input) {
			const $question = $input.closest('.ppq-question');
			const questionType = $question.data('question-type');
			const $option = $input.closest('.ppq-answer-option');

			// For radio buttons (MC/TF) - remove other selections
			if ($input.attr('type') === 'radio') {
				$question.find('.ppq-answer-option').removeClass('ppq-selected');
				if ($input.is(':checked')) {
					$option.addClass('ppq-selected');
				}
			}
			// For checkboxes (MA) - toggle selection
			else if ($input.attr('type') === 'checkbox') {
				if ($input.is(':checked')) {
					$option.addClass('ppq-selected');
				} else {
					$option.removeClass('ppq-selected');
				}
			}

			// Mark as having unsaved changes
			this.hasUnsavedChanges = true;

			// Trigger auto-save
			this.triggerAutoSave($input);
		},

		/**
		 * Trigger auto-save with debouncing
		 *
		 * @param {jQuery} $input The input that changed
		 */
		triggerAutoSave: function($input) {
			const self = this;
			const itemId = $input.data('item-id');
			const $question = $input.closest('.ppq-question');

			// Get selected answer(s)
			let selectedAnswers = [];
			if ($input.attr('type') === 'radio') {
				const checkedValue = $question.find('.ppq-answer-input:checked').val();
				if (checkedValue !== undefined) {
					selectedAnswers = [parseInt(checkedValue, 10)];
				}
			} else if ($input.attr('type') === 'checkbox') {
				$question.find('.ppq-answer-input:checked').each(function() {
					selectedAnswers.push(parseInt($(this).val(), 10));
				});
			}

			// Add to pending saves
			this.pendingSaves[itemId] = selectedAnswers;

			// Clear existing timer
			if (this.autoSaveTimer) {
				clearTimeout(this.autoSaveTimer);
			}

			// Set new timer
			this.autoSaveTimer = setTimeout(function() {
				self.performAutoSave();
			}, this.autoSaveDelay);
		},

		/**
		 * Perform auto-save of pending answers
		 */
		performAutoSave: function() {
			const self = this;

			// Check if already saving
			if (this.isSaving) {
				// Retry after delay
				setTimeout(function() {
					self.performAutoSave();
				}, 500);
				return;
			}

			// Check if there are pending saves
			if (Object.keys(this.pendingSaves).length === 0) {
				return;
			}

			// Get pending saves and clear queue
			const saves = Object.assign({}, this.pendingSaves);
			this.pendingSaves = {};

			// Set saving state
			this.isSaving = true;
			this.showAutoSaveIndicator('saving');

			// Get current active elapsed time
			const activeElapsedMs = this.getCurrentActiveElapsedMs();

			// Build form data manually to ensure proper array serialization
			const formData = {
				action: 'ppq_save_answers',
				nonce: ppqQuiz.nonce,
				attempt_id: this.attemptId,
				active_elapsed_ms: activeElapsedMs
			};

			// Add answers with proper array notation for PHP
			Object.keys(saves).forEach(function(itemId) {
				const answers = saves[itemId];
				if (Array.isArray(answers) && answers.length > 0) {
					answers.forEach(function(answerId, index) {
						formData['answers[' + itemId + '][' + index + ']'] = answerId;
					});
				} else if (Array.isArray(answers) && answers.length === 0) {
					// Empty array - send empty marker so PHP knows to clear answers
					formData['answers[' + itemId + '][]'] = '';
				}
			});

			// Make AJAX request
			$.ajax({
				url: ppqQuiz.ajaxUrl,
				type: 'POST',
				data: formData,
				success: function(response) {
					self.isSaving = false;

					if (response.success) {
						// Clear unsaved changes flag
						self.hasUnsavedChanges = false;
						self.showAutoSaveIndicator('saved');
					} else {
						// Save failed - add back to queue
						Object.assign(self.pendingSaves, saves);
						// Show more descriptive error message
						const errorMsg = response.data && response.data.message
							? response.data.message
							: ppqQuiz.strings.saveFailed;
						self.showAutoSaveIndicator('failed', errorMsg);
					}
				},
				error: function() {
					self.isSaving = false;
					// Save failed - add back to queue
					Object.assign(self.pendingSaves, saves);
					self.showAutoSaveIndicator('failed');

					// Retry after delay
					setTimeout(function() {
						self.performAutoSave();
					}, 2000);
				}
			});
		},

		/**
		 * Show auto-save indicator
		 *
		 * @param {string} state State: 'saving', 'saved', 'failed'
		 * @param {string} message Optional custom message for failed state
		 */
		showAutoSaveIndicator: function(state, message) {
			const $indicator = $('#ppq-autosave-indicator');
			const $text = $indicator.find('.ppq-autosave-text');
			const $icon = $indicator.find('.ppq-autosave-icon');

			// Update text and icon based on state
			if (state === 'saving') {
				$text.text(ppqQuiz.strings.saving);
				$icon.text('💾');
				$indicator.removeClass('ppq-autosave-error').addClass('ppq-autosave-saving');
			} else if (state === 'saved') {
				$text.text(ppqQuiz.strings.saved);
				$icon.text('✓');
				$indicator.removeClass('ppq-autosave-saving ppq-autosave-error');
			} else if (state === 'failed') {
				// Use custom message if provided, otherwise use default
				$text.text(message || ppqQuiz.strings.saveFailed);
				$icon.text('⚠️');
				$indicator.removeClass('ppq-autosave-saving').addClass('ppq-autosave-error');
			}

			// Show indicator
			$indicator.fadeIn(200).addClass('ppq-show');

			// Hide after delay (except for errors)
			if (state !== 'failed') {
				setTimeout(function() {
					$indicator.removeClass('ppq-show').fadeOut(200);
				}, 2000);
			}
		},

		/**
		 * Start countdown timer
		 */
		startTimer: function() {
			const self = this;
			const $timer = $('#ppq-timer');

			// Update timer every second
			this.timerInterval = setInterval(function() {
				self.timeRemaining--;

				// Update display
				const minutes = Math.floor(self.timeRemaining / 60);
				const seconds = self.timeRemaining % 60;
				const timeString = self.padZero(minutes) + ':' + self.padZero(seconds);
				$timer.text(timeString);

				// Update timer styling based on time remaining
				if (self.timeRemaining <= 60) {
					// Last minute - danger (red, fast pulse)
					$timer.removeClass('ppq-timer-warning').addClass('ppq-timer-danger');
				} else if (self.timeRemaining <= 300) {
					// Last 5 minutes - warning (orange, slow pulse)
					$timer.addClass('ppq-timer-warning').removeClass('ppq-timer-danger');
				}

				// Time expired - auto-submit
				if (self.timeRemaining <= 0) {
					clearInterval(self.timerInterval);
					self.handleTimeExpired();
				}
			}, 1000);
		},

		/**
		 * Handle time expiration
		 */
		handleTimeExpired: function() {
			// Auto-submit quiz (no alert - will redirect to results with timeout message)
			this.submitQuiz(true);
		},

		/**
		 * Submit quiz
		 *
		 * @param {boolean} autoSubmit Whether this is an auto-submit due to timeout
		 */
		submitQuiz: function(autoSubmit) {
			const self = this;
			autoSubmit = autoSubmit || false;

			// Prevent double submission
			if (this.isSubmitting) {
				return;
			}

			// Check for unanswered questions (skip for auto-submit on timeout)
			if (!autoSubmit) {
				const unanswered = this.getUnansweredQuestions();
				if (unanswered.length > 0) {
					this.showUnansweredWarning(unanswered);
					return;
				}
			}

			// Set submitting state
			this.isSubmitting = true;
			this.isAutoSubmit = autoSubmit;

			// Disable buttons and show loading
			const $submitButton = $('#ppq-submit-button');
			$submitButton.prop('disabled', true).addClass('ppq-loading');

			// Stop timer if running
			if (this.timerInterval) {
				clearInterval(this.timerInterval);
			}

			// Wait for any pending saves to complete
			const waitForSaves = function() {
				// Check if still saving or have pending saves
				if (self.isSaving || Object.keys(self.pendingSaves).length > 0) {
					// Wait a bit and check again
					setTimeout(waitForSaves, 100);
					return;
				}

				// All saves complete - proceed with submission
				self.performSubmission();
			};

			// Start waiting
			waitForSaves();
		},

		/**
		 * Perform the actual submission
		 */
		performSubmission: function() {
			const self = this;
			const $submitButton = $('#ppq-submit-button');

			// Stop heartbeat and get final active time
			this.stopHeartbeat();
			const finalActiveElapsedMs = this.getCurrentActiveElapsedMs();

			// Make AJAX request
			$.ajax({
				url: ppqQuiz.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ppq_submit_quiz',
					nonce: ppqQuiz.nonce,
					attempt_id: this.attemptId,
					timed_out: this.isAutoSubmit,
					current_url: window.location.href,
					active_elapsed_ms: finalActiveElapsedMs
				},
				success: (response) => {
					if (response.success && response.data.redirect_url) {
						// Redirect to results page
						window.location.href = response.data.redirect_url;
					} else {
						// Show error
						const errorMessage = response.data && response.data.message
							? response.data.message
							: ppqQuiz.strings.error;
						alert(errorMessage);

						// Re-enable submit button
						$submitButton.prop('disabled', false).removeClass('ppq-loading');
						self.isSubmitting = false;

						// Restart timer if needed
						if (self.timeLimit && self.timeRemaining > 0) {
							self.startTimer();
						}
					}
				},
				error: () => {
					alert(ppqQuiz.strings.error);
					$submitButton.prop('disabled', false).removeClass('ppq-loading');
					self.isSubmitting = false;

					// Restart timer if needed
					if (self.timeLimit && self.timeRemaining > 0) {
						self.startTimer();
					}
				}
			});
		},

		/**
		 * Pad number with leading zero
		 *
		 * @param {number} num Number to pad
		 * @return {string} Padded string
		 */
		padZero: function(num) {
			return num < 10 ? '0' + num : num.toString();
		},

		/**
		 * Start active time tracking
		 *
		 * Tracks time when the user is actively engaged with the quiz.
		 * Pauses when tab is hidden or browser is minimized.
		 */
		startActiveTimeTracking: function() {
			// Initialize timestamp
			this.lastActiveTimestamp = Date.now();
			this.isTabActive = true;

			// Start heartbeat interval for syncing time when no answers are being saved
			this.startHeartbeat();
		},

		/**
		 * Pause active time tracking
		 *
		 * Called when tab becomes hidden or browser loses focus.
		 */
		pauseActiveTimeTracking: function() {
			if (!this.isTabActive) {
				return; // Already paused
			}

			// Calculate elapsed time since last timestamp
			if (this.lastActiveTimestamp) {
				const now = Date.now();
				this.activeElapsedMs += (now - this.lastActiveTimestamp);
			}

			this.isTabActive = false;
			this.lastActiveTimestamp = null;
		},

		/**
		 * Resume active time tracking
		 *
		 * Called when tab becomes visible again.
		 */
		resumeActiveTimeTracking: function() {
			if (this.isTabActive) {
				return; // Already active
			}

			this.isTabActive = true;
			this.lastActiveTimestamp = Date.now();
		},

		/**
		 * Get current total active elapsed time in milliseconds
		 *
		 * @return {number} Total active time in milliseconds
		 */
		getCurrentActiveElapsedMs: function() {
			let total = this.activeElapsedMs;

			// Add time since last timestamp if currently active
			if (this.isTabActive && this.lastActiveTimestamp) {
				total += (Date.now() - this.lastActiveTimestamp);
			}

			return Math.round(total);
		},

		/**
		 * Start heartbeat interval for syncing time
		 *
		 * Syncs active time every 30 seconds when no auto-save is occurring.
		 */
		startHeartbeat: function() {
			const self = this;

			// Clear any existing interval
			if (this.heartbeatInterval) {
				clearInterval(this.heartbeatInterval);
			}

			// Start heartbeat
			this.heartbeatInterval = setInterval(function() {
				// Only sync if tab is active and not currently saving
				if (self.isTabActive && !self.isSaving && Object.keys(self.pendingSaves).length === 0) {
					self.syncActiveTime();
				}
			}, this.heartbeatDelay);
		},

		/**
		 * Stop heartbeat interval
		 */
		stopHeartbeat: function() {
			if (this.heartbeatInterval) {
				clearInterval(this.heartbeatInterval);
				this.heartbeatInterval = null;
			}
		},

		/**
		 * Get list of unanswered question indices
		 *
		 * @return {Array} Array of question indices (0-based) that are unanswered
		 */
		getUnansweredQuestions: function() {
			const unanswered = [];

			$('.ppq-question').each(function(index) {
				const $question = $(this);
				const hasAnswer = $question.find('.ppq-answer-input:checked').length > 0;

				if (!hasAnswer) {
					unanswered.push(index);
				}
			});

			return unanswered;
		},

		/**
		 * Show warning about unanswered questions
		 *
		 * @param {Array} unanswered Array of unanswered question indices
		 */
		showUnansweredWarning: function(unanswered) {
			const self = this;
			const questionNumbers = unanswered.map(function(idx) { return idx + 1; });

			// Build message
			let message;
			if (unanswered.length === 1) {
				message = ppqQuiz.strings.unansweredSingle.replace('{question}', questionNumbers[0]);
			} else if (unanswered.length <= 5) {
				message = ppqQuiz.strings.unansweredMultiple.replace('{questions}', questionNumbers.join(', '));
			} else {
				message = ppqQuiz.strings.unansweredMany.replace('{count}', unanswered.length);
			}

			// Remove any existing warning
			$('.ppq-unanswered-overlay').remove();

			// Create overlay with warning
			const $overlay = $('<div class="ppq-unanswered-overlay">' +
				'<div class="ppq-unanswered-warning">' +
				'<p><strong>' + ppqQuiz.strings.unansweredTitle + '</strong></p>' +
				'<p>' + message + '</p>' +
				'<div class="ppq-unanswered-actions">' +
				'<button type="button" class="ppq-button ppq-button-secondary ppq-go-to-first">' +
				ppqQuiz.strings.goToQuestion.replace('{question}', questionNumbers[0]) +
				'</button>' +
				'<button type="button" class="ppq-button ppq-button-primary ppq-submit-anyway">' +
				ppqQuiz.strings.submitAnyway +
				'</button>' +
				'</div>' +
				'</div>' +
				'</div>');

			// Append to body
			$('body').append($overlay);

			// Bind events
			$overlay.find('.ppq-go-to-first').on('click', function() {
				$overlay.remove();
				self.showQuestion(unanswered[0]);
			});

			$overlay.find('.ppq-submit-anyway').on('click', function() {
				$overlay.remove();
				// Force submit without checking again
				self.isSubmitting = true;
				self.isAutoSubmit = false;
				$('#ppq-submit-button').prop('disabled', true).addClass('ppq-loading');

				// Stop timer if running
				if (self.timerInterval) {
					clearInterval(self.timerInterval);
				}

				// Wait for pending saves then submit
				const waitForSaves = function() {
					if (self.isSaving || Object.keys(self.pendingSaves).length > 0) {
						setTimeout(waitForSaves, 100);
						return;
					}
					self.performSubmission();
				};
				waitForSaves();
			});

			// Close overlay when clicking backdrop
			$overlay.on('click', function(e) {
				if ($(e.target).hasClass('ppq-unanswered-overlay')) {
					$overlay.remove();
				}
			});
		},

		/**
		 * Sync active time to server
		 *
		 * Lightweight endpoint that only updates active_elapsed_ms.
		 */
		syncActiveTime: function() {
			const self = this;

			// Don't sync if offline
			if (!this.isOnline) {
				return;
			}

			const activeElapsedMs = this.getCurrentActiveElapsedMs();

			$.ajax({
				url: ppqQuiz.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ppq_sync_time',
					nonce: ppqQuiz.nonce,
					attempt_id: this.attemptId,
					active_elapsed_ms: activeElapsedMs
				},
				// Silent - no UI feedback for heartbeat
				success: function() {
					// Time synced successfully
				},
				error: function() {
					// Ignore errors for heartbeat - will retry next interval
				}
			});
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		QuizLanding.init();
		QuizInterface.init();
	});

})(jQuery);
