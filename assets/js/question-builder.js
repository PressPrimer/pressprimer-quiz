/**
 * Question Builder JavaScript
 *
 * Handles dynamic behavior for the question editor interface.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Question Builder namespace
	 *
	 * @since 1.0.0
	 */
	window.PPQ = window.PPQ || {};
	window.PPQ.QuestionBuilder = {

		/**
		 * Answer counter for generating unique IDs
		 *
		 * @since 1.0.0
		 */
		answerCounter: 0,

		/**
		 * Has unsaved changes
		 *
		 * @since 1.0.0
		 */
		hasUnsavedChanges: false,

		/**
		 * Initialize question builder
		 *
		 * @since 1.0.0
		 */
		init: function() {
			// Set initial answer counter
			this.answerCounter = $('#ppq-answers-container .ppq-answer-row').length;

			// Bind events
			this.bindEvents();

			// Initialize sortable
			this.initSortable();

			// Mark form as pristine
			this.hasUnsavedChanges = false;
		},

		/**
		 * Bind event handlers
		 *
		 * @since 1.0.0
		 */
		bindEvents: function() {
			var self = this;

			// Add answer button
			$('#ppq-add-answer').on('click', function(e) {
				e.preventDefault();
				self.addAnswer();
			});

			// Remove answer button
			$(document).on('click', '.ppq-remove-answer', function(e) {
				e.preventDefault();
				if (!$(this).prop('disabled')) {
					self.removeAnswer($(this));
				}
			});

			// Question type change
			$('input[name="question_type"]').on('change', function() {
				self.updateAnswerInputTypes($(this).val());
			});

			// Feedback toggle
			$(document).on('click', '.ppq-feedback-toggle', function(e) {
				e.preventDefault();
				self.toggleFeedback($(this));
			});

			// Track changes for unsaved warning
			$('#ppq-question-form').on('change', 'input, select, textarea', function() {
				self.hasUnsavedChanges = true;
			});

			// Unsaved changes warning
			$(window).on('beforeunload', function() {
				if (self.hasUnsavedChanges) {
					return 'You have unsaved changes. Are you sure you want to leave?';
				}
			});

			// Don't warn on form submit
			$('#ppq-question-form').on('submit', function() {
				self.hasUnsavedChanges = false;
			});

			// Character counters
			this.initCharacterCounters();
		},

		/**
		 * Initialize sortable for drag-and-drop
		 *
		 * @since 1.0.0
		 */
		initSortable: function() {
			var self = this;

			$('#ppq-answers-container').sortable({
				handle: '.ppq-drag-handle',
				placeholder: 'ppq-answer-placeholder',
				axis: 'y',
				cursor: 'move',
				opacity: 0.7,
				update: function() {
					self.updateAnswerOrder();
					self.hasUnsavedChanges = true;
				}
			});
		},

		/**
		 * Add new answer option
		 *
		 * @since 1.0.0
		 */
		addAnswer: function() {
			var $container = $('#ppq-answers-container');
			var currentCount = $container.find('.ppq-answer-row').length;

			// Check max limit
			if (currentCount >= 8) {
				alert(window.ppqAdmin.strings.error || 'Maximum 8 answer options allowed.');
				return;
			}

			// Get question type
			var questionType = $('input[name="question_type"]:checked').val() || 'mc';
			var inputType = (questionType === 'mc' || questionType === 'tf') ? 'radio' : 'checkbox';

			// Generate new answer ID
			this.answerCounter++;
			var answerId = 'a' + this.answerCounter;
			var index = currentCount;

			// Create answer row HTML
			var html = this.getAnswerRowHTML(answerId, index, '', false, '', inputType);

			// Append to container
			$container.append(html);

			// Update order
			this.updateAnswerOrder();

			// Update button states
			this.updateButtonStates();

			// Mark as changed
			this.hasUnsavedChanges = true;

			// Focus on new answer text field
			$container.find('.ppq-answer-row:last .ppq-answer-text').focus();
		},

		/**
		 * Remove answer option
		 *
		 * @since 1.0.0
		 * @param {jQuery} $button Remove button element.
		 */
		removeAnswer: function($button) {
			var $row = $button.closest('.ppq-answer-row');
			var currentCount = $('#ppq-answers-container .ppq-answer-row').length;

			// Don't allow removing if only 2 answers remain
			if (currentCount <= 2) {
				alert('At least 2 answer options are required.');
				return;
			}

			// Remove the row
			$row.fadeOut(300, function() {
				$(this).remove();

				// Update order and button states
				PPQ.QuestionBuilder.updateAnswerOrder();
				PPQ.QuestionBuilder.updateButtonStates();
			});

			// Mark as changed
			this.hasUnsavedChanges = true;
		},

		/**
		 * Update answer order after reordering
		 *
		 * @since 1.0.0
		 */
		updateAnswerOrder: function() {
			$('#ppq-answers-container .ppq-answer-row').each(function(index) {
				// Update data-index attribute
				$(this).attr('data-index', index);

				// Update order hidden field
				$(this).find('.ppq-answer-order').val(index + 1);

				// Update field names to maintain proper array indices
				$(this).find('input, textarea').each(function() {
					var name = $(this).attr('name');
					if (name && name.indexOf('answers[') === 0) {
						// Replace the index in the name
						var newName = name.replace(/answers\[\d+\]/, 'answers[' + index + ']');
						$(this).attr('name', newName);
					}
				});
			});

			// Update button states
			this.updateButtonStates();
		},

		/**
		 * Update button states based on current state
		 *
		 * @since 1.0.0
		 */
		updateButtonStates: function() {
			var $container = $('#ppq-answers-container');
			var count = $container.find('.ppq-answer-row').length;

			// Update add button
			if (count >= 8) {
				$('#ppq-add-answer').prop('disabled', true);
			} else {
				$('#ppq-add-answer').prop('disabled', false);
			}

			// Update remove buttons - disable first 2
			$container.find('.ppq-remove-answer').each(function(index) {
				if (index < 2 || count <= 2) {
					$(this).prop('disabled', true);
				} else {
					$(this).prop('disabled', false);
				}
			});
		},

		/**
		 * Update answer input types when question type changes
		 *
		 * @since 1.0.0
		 * @param {string} questionType Question type (mc, ma, tf).
		 */
		updateAnswerInputTypes: function(questionType) {
			var inputType = (questionType === 'mc' || questionType === 'tf') ? 'radio' : 'checkbox';

			$('#ppq-answers-container .ppq-correct-input').each(function() {
				var $input = $(this);
				var isChecked = $input.prop('checked');

				// Create new input with same attributes
				var $newInput = $('<input>')
					.attr('type', inputType)
					.attr('name', 'correct_answers[]')
					.attr('value', $input.val())
					.addClass('ppq-correct-input')
					.prop('checked', isChecked);

				// Replace old input
				$input.replaceWith($newInput);
			});

			// For MC/TF, ensure only one is checked
			if (inputType === 'radio') {
				var checkedCount = $('#ppq-answers-container .ppq-correct-input:checked').length;
				if (checkedCount > 1) {
					// Uncheck all except the first
					$('#ppq-answers-container .ppq-correct-input:checked').not(':first').prop('checked', false);
				}
			}

			// Mark as changed
			this.hasUnsavedChanges = true;
		},

		/**
		 * Toggle answer feedback visibility
		 *
		 * @since 1.0.0
		 * @param {jQuery} $toggle Toggle element.
		 */
		toggleFeedback: function($toggle) {
			var $row = $toggle.closest('.ppq-answer-feedback');
			var $content = $row.find('.ppq-feedback-content');
			var $icon = $toggle.find('.dashicons');

			if ($content.is(':visible')) {
				$content.slideUp(200);
				$icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
			} else {
				$content.slideDown(200);
				$icon.removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');

				// Focus on textarea
				$content.find('textarea').focus();
			}
		},

		/**
		 * Initialize character counters
		 *
		 * @since 1.0.0
		 */
		initCharacterCounters: function() {
			var self = this;

			// Add counter to question stem - wait for TinyMCE to initialize
			this.initStemCounter();

			// Add counters to answer fields
			$(document).on('keyup change', '.ppq-answer-text', function() {
				self.updateAnswerCounter($(this));
			});

			// Initialize existing answer counters
			$('.ppq-answer-text').each(function() {
				self.updateAnswerCounter($(this));
			});

			// Add counters to feedback fields
			$(document).on('keyup change', '#feedback_correct, #feedback_incorrect', function() {
				self.updateFeedbackCounter($(this));
			});

			// Initialize feedback counters
			$('#feedback_correct, #feedback_incorrect').each(function() {
				self.updateFeedbackCounter($(this));
			});
		},

		/**
		 * Initialize stem character counter
		 *
		 * Waits for TinyMCE to be ready before setting up the counter.
		 *
		 * @since 1.0.0
		 */
		initStemCounter: function() {
			var self = this;

			// Add counter container if not exists
			var $stemContainer = $('#wp-question_stem-wrap');
			if ($stemContainer.length && !$stemContainer.find('.ppq-char-counter').length) {
				$stemContainer.append('<div class="ppq-char-counter" id="stem-char-counter"></div>');
			}

			// Wait for TinyMCE to initialize
			if (typeof tinyMCE !== 'undefined') {
				// Check if editor already exists
				var editor = tinyMCE.get('question_stem');
				if (editor) {
					self.setupStemEditorCounter(editor);
				} else {
					// Wait for editor to initialize
					$(document).on('tinymce-editor-init', function(event, editor) {
						if (editor.id === 'question_stem') {
							self.setupStemEditorCounter(editor);
						}
					});
				}
			} else {
				// Fallback for plain textarea (text mode)
				$('#question_stem').on('keyup change', function() {
					self.updateStemCounterFromTextarea();
				});
				self.updateStemCounterFromTextarea();
			}
		},

		/**
		 * Setup counter for TinyMCE stem editor
		 *
		 * @since 1.0.0
		 * @param {object} editor TinyMCE editor instance.
		 */
		setupStemEditorCounter: function(editor) {
			var self = this;

			// Update counter immediately
			self.updateStemCounter();

			// Update on editor changes
			editor.on('keyup change NodeChange', function() {
				self.updateStemCounter();
			});
		},

		/**
		 * Update stem counter from plain textarea
		 *
		 * @since 1.0.0
		 */
		updateStemCounterFromTextarea: function() {
			var $textarea = $('#question_stem');
			if ($textarea.length) {
				var length = $textarea.val().length;
				var max = 10000;
				var $counter = $('#stem-char-counter');

				var html = length + ' / ' + max + ' characters';
				if (length > max) {
					html = '<span style="color: #d63638;">' + html + ' (exceeds limit)</span>';
				}

				$counter.html(html);
			}
		},

		/**
		 * Update stem character counter
		 *
		 * @since 1.0.0
		 */
		updateStemCounter: function() {
			if (typeof tinyMCE !== 'undefined' && tinyMCE.get('question_stem')) {
				var content = tinyMCE.get('question_stem').getContent({format: 'text'});
				var length = content.length;
				var max = 10000;
				var $counter = $('#stem-char-counter');

				var html = length + ' / ' + max + ' characters';
				if (length > max) {
					html = '<span style="color: #d63638;">' + html + ' (exceeds limit)</span>';
				}

				$counter.html(html);
			}
		},

		/**
		 * Update answer character counter
		 *
		 * @since 1.0.0
		 * @param {jQuery} $textarea Answer textarea.
		 */
		updateAnswerCounter: function($textarea) {
			var length = $textarea.val().length;
			var max = 2000;
			var $counter = $textarea.next('.ppq-char-counter');

			if (!$counter.length) {
				$counter = $('<div class="ppq-char-counter"></div>');
				$textarea.after($counter);
			}

			var html = length + ' / ' + max;
			if (length > max) {
				html = '<span style="color: #d63638;">' + html + '</span>';
			}

			$counter.html(html);
		},

		/**
		 * Update feedback character counter
		 *
		 * @since 1.0.0
		 * @param {jQuery} $textarea Feedback textarea.
		 */
		updateFeedbackCounter: function($textarea) {
			var length = $textarea.val().length;
			var max = 2000;
			var $counter = $textarea.next('.ppq-char-counter');

			if (!$counter.length) {
				$counter = $('<div class="ppq-char-counter"></div>');
				$textarea.after($counter);
			}

			var html = length + ' / ' + max;
			if (length > max) {
				html = '<span style="color: #d63638;">' + html + '</span>';
			}

			$counter.html(html);
		},

		/**
		 * Get answer row HTML
		 *
		 * @since 1.0.0
		 * @param {string} answerId Answer ID.
		 * @param {int} index Answer index.
		 * @param {string} text Answer text.
		 * @param {bool} isCorrect Is correct answer.
		 * @param {string} feedback Answer feedback.
		 * @param {string} inputType Input type (radio or checkbox).
		 * @return {string} HTML string.
		 */
		getAnswerRowHTML: function(answerId, index, text, isCorrect, feedback, inputType) {
			text = text || '';
			feedback = feedback || '';
			isCorrect = isCorrect || false;
			inputType = inputType || 'radio';

			var checkedAttr = isCorrect ? ' checked' : '';
			var disabledAttr = index < 2 ? ' disabled' : '';
			var feedbackDisplay = feedback ? 'block' : 'none';
			var iconClass = feedback ? 'dashicons-arrow-down' : 'dashicons-arrow-right';

			var html = '<div class="ppq-answer-row" data-index="' + index + '">' +
				'<div class="ppq-answer-header">' +
					'<span class="ppq-drag-handle dashicons dashicons-menu"></span>' +
					'<label class="ppq-correct-toggle">' +
						'<input type="' + inputType + '" name="correct_answers[]" value="' + answerId + '"' + checkedAttr + ' class="ppq-correct-input">' +
						'<span>Correct</span>' +
					'</label>' +
					'<button type="button" class="ppq-remove-answer button-link-delete"' + disabledAttr + '>Remove</button>' +
				'</div>' +
				'<input type="hidden" name="answers[' + index + '][id]" value="' + answerId + '">' +
				'<input type="hidden" name="answers[' + index + '][order]" value="' + (index + 1) + '" class="ppq-answer-order">' +
				'<div class="ppq-answer-content">' +
					'<textarea name="answers[' + index + '][text]" rows="2" class="large-text ppq-answer-text" placeholder="Answer text...">' + this.escapeHtml(text) + '</textarea>' +
				'</div>' +
				'<div class="ppq-answer-feedback">' +
					'<label>' +
						'<span class="ppq-feedback-toggle">' +
							'<span class="dashicons ' + iconClass + '"></span>' +
							'Add feedback for this answer' +
						'</span>' +
					'</label>' +
					'<div class="ppq-feedback-content" style="display: ' + feedbackDisplay + ';">' +
						'<textarea name="answers[' + index + '][feedback]" rows="2" class="large-text" placeholder="Explain why this answer is correct or incorrect...">' + this.escapeHtml(feedback) + '</textarea>' +
					'</div>' +
				'</div>' +
			'</div>';

			return html;
		},

		/**
		 * Escape HTML for safe insertion
		 *
		 * @since 1.0.0
		 * @param {string} text Text to escape.
		 * @return {string} Escaped text.
		 */
		escapeHtml: function(text) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		// Only initialize if we're on the question editor page
		if ($('#ppq-question-form').length) {
			PPQ.QuestionBuilder.init();
		}
	});

})(jQuery);
