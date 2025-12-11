/**
 * AI Question Generation
 *
 * Handles the AI generation interface for creating quiz questions.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

(function($) {
	'use strict';

	var PPQ_AI = {
		// State
		extractedContent: '',
		generatedQuestions: [],
		selectedQuestions: [],
		isGenerating: false,
		currentTab: 'text',

		/**
		 * Initialize the AI generation interface
		 */
		init: function() {
			this.bindEvents();
			this.updateCharCount();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			var self = this;

			// Tab switching
			$(document).on('click', '.ppq-ai-tab', function(e) {
				e.preventDefault();
				self.switchTab($(this).data('tab'));
			});

			// Text input character count
			$(document).on('input', '#ppq-ai-content', function() {
				self.updateCharCount();
			});

			// File upload handling
			$(document).on('change', '#ppq-ai-file-input', function(e) {
				if (e.target.files.length > 0) {
					self.handleFileSelect(e.target.files[0]);
				}
			});

			// Drag and drop - use event delegation for elements that may not exist at init
			$(document).on('dragover', '#ppq-ai-upload-area', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$(this).addClass('ppq-ai-upload-area--dragover');
			});

			$(document).on('dragleave', '#ppq-ai-upload-area', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$(this).removeClass('ppq-ai-upload-area--dragover');
			});

			$(document).on('drop', '#ppq-ai-upload-area', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$(this).removeClass('ppq-ai-upload-area--dragover');

				var files = e.originalEvent.dataTransfer.files;
				if (files.length > 0) {
					self.handleFileSelect(files[0]);
				}
			});

			// Click to select file - trigger the hidden file input
			$(document).on('click', '#ppq-ai-upload-area', function(e) {
				// Don't trigger if clicking on the file input itself
				if (e.target.id === 'ppq-ai-file-input') {
					return;
				}

				// Use native click for better browser compatibility with file inputs
				var fileInput = document.getElementById('ppq-ai-file-input');
				if (fileInput) {
					fileInput.click();
				}
			});

			// Remove file
			$(document).on('click', '#ppq-ai-file-remove', function(e) {
				e.preventDefault();
				self.removeFile();
			});

			// Generate button
			$(document).on('click', '#ppq-ai-generate-btn', function(e) {
				e.preventDefault();
				self.generateQuestions();
			});

			// Question selection
			$(document).on('change', '.ppq-ai-question-checkbox', function() {
				self.updateSelectedQuestions();
			});

			// Select/Deselect all
			$(document).on('click', '#ppq-ai-select-all', function(e) {
				e.preventDefault();
				$('.ppq-ai-question-checkbox').prop('checked', true);
				self.updateSelectedQuestions();
			});

			$(document).on('click', '#ppq-ai-deselect-all', function(e) {
				e.preventDefault();
				$('.ppq-ai-question-checkbox').prop('checked', false);
				self.updateSelectedQuestions();
			});

			// Discard all
			$(document).on('click', '#ppq-ai-discard', function(e) {
				e.preventDefault();
				PPQ.Admin.confirm(
					ppqAIGeneration.strings.confirmDiscard,
					function() {
						self.discardQuestions();
					},
					null,
					{
						title: ppqAIGeneration.strings.discardTitle || 'Discard Questions',
						confirmText: ppqAIGeneration.strings.discard || 'Discard',
						cancelText: ppqAIGeneration.strings.cancel || 'Cancel'
					}
				);
			});

			// Save selected
			$(document).on('click', '#ppq-ai-save-selected', function(e) {
				e.preventDefault();
				self.saveQuestions();
			});

			// Edit question
			$(document).on('click', '.ppq-ai-question-edit', function(e) {
				e.preventDefault();
				var index = $(this).closest('.ppq-ai-question-item').data('index');
				self.editQuestion(index);
			});

			// Delete question
			$(document).on('click', '.ppq-ai-question-delete', function(e) {
				e.preventDefault();
				var index = $(this).closest('.ppq-ai-question-item').data('index');
				self.deleteQuestion(index);
			});

			// Save edit modal
			$(document).on('click', '#ppq-ai-edit-save', function(e) {
				e.preventDefault();
				self.saveQuestionEdit();
			});

			// Cancel edit modal
			$(document).on('click', '#ppq-ai-edit-cancel, .ppq-ai-modal-close', function(e) {
				e.preventDefault();
				self.closeEditModal();
			});
		},

		/**
		 * Switch between tabs
		 */
		switchTab: function(tab) {
			this.currentTab = tab;

			// Update tab buttons
			$('.ppq-ai-tab').removeClass('ppq-ai-tab--active');
			$('.ppq-ai-tab[data-tab="' + tab + '"]').addClass('ppq-ai-tab--active');

			// Update tab content
			$('.ppq-ai-tab-content').removeClass('ppq-ai-tab-content--active');
			$('.ppq-ai-tab-content[data-tab-content="' + tab + '"]').addClass('ppq-ai-tab-content--active');
		},

		/**
		 * Update character count
		 */
		updateCharCount: function() {
			var content = $('#ppq-ai-content').val() || '';
			var count = content.length;
			$('#ppq-ai-char-current').text(count.toLocaleString());

			// Warning if over limit (250,000 characters)
			if (count > 250000) {
				$('#ppq-ai-char-current').addClass('ppq-ai-char-warning');
			} else {
				$('#ppq-ai-char-current').removeClass('ppq-ai-char-warning');
			}
		},

		/**
		 * Handle file selection
		 */
		handleFileSelect: function(file) {
			var self = this;

			// Validate file type
			var validTypes = [
				'application/pdf',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
			];

			if (validTypes.indexOf(file.type) === -1) {
				this.showError(ppqAIGeneration.strings.invalidFileType);
				return;
			}

			// Validate file size
			if (file.size > ppqAIGeneration.maxFileSize) {
				this.showError(ppqAIGeneration.strings.fileTooLarge + ' ' + ppqAIGeneration.maxFileSizeFormatted);
				return;
			}

			// Show file info
			$('#ppq-ai-file-name').text(file.name);
			$('#ppq-ai-file-size').text('(' + this.formatFileSize(file.size) + ')');
			$('#ppq-ai-file-info').show();
			$('#ppq-ai-upload-area').hide();

			// Upload and extract text
			this.uploadFile(file);
		},

		/**
		 * Upload file and extract text
		 */
		uploadFile: function(file) {
			var self = this;
			var formData = new FormData();

			formData.append('action', 'ppq_ai_upload_file');
			formData.append('nonce', ppqAIGeneration.nonce);
			formData.append('file', file);

			// Show loading
			this.showLoading(ppqAIGeneration.strings.processing);

			$.ajax({
				url: ppqAIGeneration.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					self.hideLoading();

					if (response.success) {
						self.extractedContent = response.data.text;

						// Show preview
						var previewText = self.extractedContent;
						if (previewText.length > 1000) {
							previewText = previewText.substring(0, 1000) + '...';
						}
						$('#ppq-ai-extracted-text').text(previewText);
						$('#ppq-ai-extracted-preview').show();
					} else {
						self.showError(response.data.message || ppqAIGeneration.strings.error);
						self.removeFile();
					}
				},
				error: function() {
					self.hideLoading();
					self.showError(ppqAIGeneration.strings.error);
					self.removeFile();
				}
			});
		},

		/**
		 * Remove uploaded file
		 */
		removeFile: function() {
			this.extractedContent = '';
			$('#ppq-ai-file-input').val('');
			$('#ppq-ai-file-info').hide();
			$('#ppq-ai-extracted-preview').hide();
			$('#ppq-ai-upload-area').show();
		},

		/**
		 * Generate questions
		 */
		generateQuestions: function() {
			var self = this;

			if (this.isGenerating) {
				return;
			}

			// Get content based on current tab
			var content = '';
			if (this.currentTab === 'text') {
				content = $('#ppq-ai-content').val().trim();
			} else {
				content = this.extractedContent;
			}

			if (!content) {
				this.showError(ppqAIGeneration.strings.noContent);
				return;
			}

			// Get parameters
			var types = [];
			$('input[name="ppq_ai_types[]"]:checked').each(function() {
				types.push($(this).val());
			});

			if (types.length === 0) {
				types = ['mc']; // Default to multiple choice
			}

			var difficulty = [];
			$('input[name="ppq_ai_difficulty[]"]:checked').each(function() {
				difficulty.push($(this).val());
			});

			if (difficulty.length === 0) {
				difficulty = ['medium']; // Default to medium
			}

			var count = parseInt($('#ppq-ai-count').val()) || 5;
			var answerCount = parseInt($('#ppq-ai-answer-count').val()) || 4;
			var generateFeedback = $('#ppq-ai-generate-feedback').is(':checked');

			// Clamp values to valid ranges
			count = Math.max(1, Math.min(50, count));
			answerCount = Math.max(3, Math.min(6, answerCount));

			// Hide previous results and errors
			$('#ppq-ai-results').hide();
			this.hideError();

			// Show loading
			this.isGenerating = true;
			this.showLoading(ppqAIGeneration.strings.generating);

			// Make AJAX request
			$.ajax({
				url: ppqAIGeneration.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ppq_ai_generate_questions',
					nonce: ppqAIGeneration.nonce,
					content: content,
					count: count,
					types: types,
					difficulty: difficulty,
					answer_count: answerCount,
					generate_feedback: generateFeedback ? 1 : 0
				},
				timeout: 600000, // 10 minute timeout
				success: function(response) {
					self.isGenerating = false;
					self.hideLoading();

					if (response.success) {
						self.generatedQuestions = response.data.questions;
						self.displayResults(response.data);
					} else {
						// Build detailed error message
						var errorMessage = response.data.message || ppqAIGeneration.strings.error;

						// Add validation error details if available
						if (response.data.validation_errors && response.data.validation_errors.length > 0) {
							var errorDetails = response.data.validation_errors.slice(0, 3).map(function(e) {
								return 'Q' + e.index + ': ' + e.message;
							});
							errorMessage += '\n\nDetails:\n' + errorDetails.join('\n');
						}

						self.showError(errorMessage);
					}
				},
				error: function(xhr, status, error) {
					self.isGenerating = false;
					self.hideLoading();

					var message = ppqAIGeneration.strings.error;
					if (status === 'timeout') {
						message = 'Request timed out. The AI is taking too long to respond. Please try with less content or fewer questions.';
					} else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						message = xhr.responseJSON.data.message;
					} else if (status === 'error' && error) {
						message = 'Connection error: ' + error + '. Please check your internet connection and try again.';
					}
					self.showError(message);
				}
			});
		},

		/**
		 * Display generated questions
		 */
		displayResults: function(data) {
			var self = this;
			var questions = data.questions || [];

			// Update count
			$('#ppq-ai-results-count').text('(' + questions.length + ')');

			// Build info messages
			var infoMessages = [];

			// Show info if content was truncated
			if (data.content_info && data.content_info.was_truncated) {
				infoMessages.push('<span class="dashicons dashicons-info"></span> ' + ppqAIGeneration.strings.truncatedWarning);
			}

			// Show token usage info
			if (data.token_usage && (data.token_usage.prompt_tokens || data.token_usage.completion_tokens)) {
				var tokenInfo = '<span class="dashicons dashicons-dashboard"></span> Tokens used: ';
				var parts = [];
				if (data.token_usage.prompt_tokens) {
					parts.push(data.token_usage.prompt_tokens.toLocaleString() + ' prompt');
				}
				if (data.token_usage.completion_tokens) {
					parts.push(data.token_usage.completion_tokens.toLocaleString() + ' completion');
				}
				if (data.token_usage.total_tokens) {
					parts.push(data.token_usage.total_tokens.toLocaleString() + ' total');
				}
				tokenInfo += parts.join(', ');
				infoMessages.push(tokenInfo);
			}

			// Show validation warnings for partial success
			if (data.validation && data.validation.partial_success) {
				var validationWarning = '<span class="dashicons dashicons-warning" style="color: #dba617;"></span> ';
				validationWarning += data.validation.valid_count + ' of ' + data.validation.total_generated;
				validationWarning += ' questions were valid. ';
				validationWarning += data.validation.invalid_count + ' question(s) could not be parsed.';
				infoMessages.push(validationWarning);
			}

			// Display info messages
			if (infoMessages.length > 0) {
				$('#ppq-ai-results-info')
					.html(infoMessages.join('<br>'))
					.show();
			} else {
				$('#ppq-ai-results-info').hide();
			}

			// Build questions HTML
			var html = '';
			questions.forEach(function(q, index) {
				html += self.buildQuestionHTML(q, index);
			});

			$('#ppq-ai-questions-list').html(html);

			// Show results panel
			$('#ppq-ai-results').show();

			// Reset selection
			this.selectedQuestions = [];
			this.updateSelectedQuestions();

			// Scroll to results
			$('html, body').animate({
				scrollTop: $('#ppq-ai-results').offset().top - 50
			}, 500);
		},

		/**
		 * Build HTML for a single question
		 */
		buildQuestionHTML: function(question, index) {
			var typeLabels = {
				'mc': 'Multiple Choice',
				'ma': 'Multiple Answer',
				'tf': 'True/False'
			};

			var difficultyLabels = {
				'easy': 'Easy',
				'medium': 'Medium',
				'hard': 'Hard'
			};

			var html = '<div class="ppq-ai-question-item" data-index="' + index + '">';
			html += '<div class="ppq-ai-question-header">';
			html += '<label class="ppq-ai-question-select">';
			html += '<input type="checkbox" class="ppq-ai-question-checkbox" value="' + index + '">';
			html += '<span class="ppq-ai-question-number">Q' + (index + 1) + '</span>';
			html += '</label>';
			html += '<div class="ppq-ai-question-meta">';
			html += '<span class="ppq-ai-question-type">' + (typeLabels[question.type] || question.type) + '</span>';
			html += '<span class="ppq-ai-question-difficulty ppq-ai-difficulty--' + question.difficulty + '">' + (difficultyLabels[question.difficulty] || question.difficulty) + '</span>';
			html += '</div>';
			html += '<div class="ppq-ai-question-actions">';
			html += '<button type="button" class="ppq-ai-question-edit button button-small"><span class="dashicons dashicons-edit"></span> Edit</button>';
			html += '<button type="button" class="ppq-ai-question-delete button button-small button-link-delete"><span class="dashicons dashicons-trash"></span></button>';
			html += '</div>';
			html += '</div>';

			html += '<div class="ppq-ai-question-content">';
			html += '<div class="ppq-ai-question-stem">' + this.escapeHtml(question.stem) + '</div>';

			html += '<div class="ppq-ai-question-answers">';
			question.answers.forEach(function(answer, aIndex) {
				var correctClass = answer.is_correct ? 'ppq-ai-answer--correct' : '';
				html += '<div class="ppq-ai-answer ' + correctClass + '">';
				html += '<span class="ppq-ai-answer-letter">' + String.fromCharCode(65 + aIndex) + '</span>';
				html += '<span class="ppq-ai-answer-text">' + this.escapeHtml(answer.text) + '</span>';
				if (answer.is_correct) {
					html += '<span class="ppq-ai-answer-correct-icon dashicons dashicons-yes-alt"></span>';
				}
				html += '</div>';
			}, this);
			html += '</div>';

			html += '</div>';
			html += '</div>';

			return html;
		},

		/**
		 * Update selected questions count
		 */
		updateSelectedQuestions: function() {
			this.selectedQuestions = [];
			var self = this;

			$('.ppq-ai-question-checkbox:checked').each(function() {
				self.selectedQuestions.push(parseInt($(this).val()));
			});

			var count = this.selectedQuestions.length;
			$('#ppq-ai-selected-count').text('(' + count + ')');
			$('#ppq-ai-save-selected').prop('disabled', count === 0);
		},

		/**
		 * Edit a question
		 */
		editQuestion: function(index) {
			var question = this.generatedQuestions[index];
			if (!question) {
				return;
			}

			// Build edit modal
			var html = '<div class="ppq-ai-modal" id="ppq-ai-edit-modal">';
			html += '<div class="ppq-ai-modal-content">';
			html += '<div class="ppq-ai-modal-header">';
			html += '<h3>Edit Question</h3>';
			html += '<button type="button" class="ppq-ai-modal-close"><span class="dashicons dashicons-no-alt"></span></button>';
			html += '</div>';
			html += '<div class="ppq-ai-modal-body">';

			// Stem
			html += '<div class="ppq-ai-edit-field">';
			html += '<label>Question Text</label>';
			html += '<textarea id="ppq-ai-edit-stem" rows="4">' + this.escapeHtml(question.stem) + '</textarea>';
			html += '</div>';

			// Answers
			html += '<div class="ppq-ai-edit-field">';
			html += '<label>Answers</label>';
			html += '<div id="ppq-ai-edit-answers">';

			question.answers.forEach(function(answer, aIndex) {
				html += '<div class="ppq-ai-edit-answer">';
				html += '<input type="' + (question.type === 'ma' ? 'checkbox' : 'radio') + '" name="ppq_ai_edit_correct" value="' + aIndex + '"' + (answer.is_correct ? ' checked' : '') + '>';
				html += '<input type="text" class="ppq-ai-edit-answer-text" value="' + this.escapeHtml(answer.text) + '" data-index="' + aIndex + '">';
				html += '</div>';
			}, this);

			html += '</div>';
			html += '</div>';

			// Feedback
			html += '<div class="ppq-ai-edit-field">';
			html += '<label>Feedback (Correct)</label>';
			html += '<textarea id="ppq-ai-edit-feedback-correct" rows="2">' + this.escapeHtml(question.feedback_correct || '') + '</textarea>';
			html += '</div>';

			html += '<div class="ppq-ai-edit-field">';
			html += '<label>Feedback (Incorrect)</label>';
			html += '<textarea id="ppq-ai-edit-feedback-incorrect" rows="2">' + this.escapeHtml(question.feedback_incorrect || '') + '</textarea>';
			html += '</div>';

			html += '</div>';
			html += '<div class="ppq-ai-modal-footer">';
			html += '<button type="button" id="ppq-ai-edit-cancel" class="button">Cancel</button>';
			html += '<button type="button" id="ppq-ai-edit-save" class="button button-primary" data-index="' + index + '">Save Changes</button>';
			html += '</div>';
			html += '</div>';
			html += '</div>';

			$('body').append(html);
		},

		/**
		 * Save question edit
		 */
		saveQuestionEdit: function() {
			var index = parseInt($('#ppq-ai-edit-save').data('index'));
			var question = this.generatedQuestions[index];

			if (!question) {
				return;
			}

			// Update stem
			question.stem = $('#ppq-ai-edit-stem').val();

			// Update answers
			$('.ppq-ai-edit-answer-text').each(function() {
				var aIndex = parseInt($(this).data('index'));
				question.answers[aIndex].text = $(this).val();
			});

			// Update correct answers
			question.answers.forEach(function(answer, aIndex) {
				if (question.type === 'ma') {
					answer.is_correct = $('input[name="ppq_ai_edit_correct"][value="' + aIndex + '"]').is(':checked');
				} else {
					answer.is_correct = $('input[name="ppq_ai_edit_correct"]:checked').val() == aIndex;
				}
			});

			// Update feedback
			question.feedback_correct = $('#ppq-ai-edit-feedback-correct').val();
			question.feedback_incorrect = $('#ppq-ai-edit-feedback-incorrect').val();

			// Re-render the question
			var newHtml = this.buildQuestionHTML(question, index);
			$('.ppq-ai-question-item[data-index="' + index + '"]').replaceWith(newHtml);

			// Restore checkbox state if was selected
			if (this.selectedQuestions.indexOf(index) !== -1) {
				$('.ppq-ai-question-item[data-index="' + index + '"] .ppq-ai-question-checkbox').prop('checked', true);
			}

			this.closeEditModal();
		},

		/**
		 * Close edit modal
		 */
		closeEditModal: function() {
			$('#ppq-ai-edit-modal').remove();
		},

		/**
		 * Delete a question
		 */
		deleteQuestion: function(index) {
			// Remove from array
			this.generatedQuestions.splice(index, 1);

			// Remove from selected
			var selectedIndex = this.selectedQuestions.indexOf(index);
			if (selectedIndex !== -1) {
				this.selectedQuestions.splice(selectedIndex, 1);
			}

			// Re-render all questions (indexes changed)
			var self = this;
			var html = '';
			this.generatedQuestions.forEach(function(q, i) {
				html += self.buildQuestionHTML(q, i);
			});

			$('#ppq-ai-questions-list').html(html);
			$('#ppq-ai-results-count').text('(' + this.generatedQuestions.length + ')');

			// Update selected count
			this.selectedQuestions = [];
			this.updateSelectedQuestions();

			// Hide results if no questions left
			if (this.generatedQuestions.length === 0) {
				$('#ppq-ai-results').hide();
			}
		},

		/**
		 * Discard all questions
		 */
		discardQuestions: function() {
			this.generatedQuestions = [];
			this.selectedQuestions = [];
			$('#ppq-ai-results').hide();
		},

		/**
		 * Save selected questions to bank
		 */
		saveQuestions: function() {
			var self = this;

			if (this.selectedQuestions.length === 0) {
				this.showError(ppqAIGeneration.strings.selectQuestions);
				return;
			}

			var bankId = $('.ppq-ai-generation-panel').data('bank-id');
			var categories = $('#ppq-ai-categories').val() || [];

			// Get selected questions
			var questionsToSave = [];
			this.selectedQuestions.forEach(function(index) {
				if (self.generatedQuestions[index]) {
					questionsToSave.push(self.generatedQuestions[index]);
				}
			});

			// Show loading
			this.showLoading(ppqAIGeneration.strings.saving);

			// Make AJAX request
			$.ajax({
				url: ppqAIGeneration.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ppq_ai_save_questions',
					nonce: ppqAIGeneration.nonce,
					bank_id: bankId,
					questions: JSON.stringify(questionsToSave),
					categories: categories
				},
				success: function(response) {
					self.hideLoading();

					if (response.success) {
						// Show success message with modal
						PPQ.Admin.alert(
							response.data.message,
							function() {
								// Reload page to show updated bank
								window.location.reload();
							},
							{
								type: 'success',
								title: ppqAIGeneration.strings.successTitle || 'Success'
							}
						);

						// Remove saved questions from the list
						self.selectedQuestions.sort(function(a, b) { return b - a; }); // Sort descending
						self.selectedQuestions.forEach(function(index) {
							self.generatedQuestions.splice(index, 1);
						});

						// Re-render
						if (self.generatedQuestions.length > 0) {
							var html = '';
							self.generatedQuestions.forEach(function(q, i) {
								html += self.buildQuestionHTML(q, i);
							});
							$('#ppq-ai-questions-list').html(html);
							$('#ppq-ai-results-count').text('(' + self.generatedQuestions.length + ')');
						} else {
							$('#ppq-ai-results').hide();
						}

						self.selectedQuestions = [];
						self.updateSelectedQuestions();
						// Page will reload after user closes success modal
					} else {
						// Build detailed error message
						var errorMessage = response.data.message || ppqAIGeneration.strings.error;

						// Log full response for debugging
						console.error('Save questions failed:', response.data);

						// Add individual errors if available
						if (response.data.errors && response.data.errors.length > 0) {
							errorMessage += '\n\nDetails:\n' + response.data.errors.join('\n');
							console.error('Individual errors:', response.data.errors);
						}

						self.showError(errorMessage);
					}
				},
				error: function(xhr, status, error) {
					self.hideLoading();
					console.error('AJAX error:', status, error, xhr.responseText);
					self.showError(ppqAIGeneration.strings.error);
				}
			});
		},

		/**
		 * Show loading state
		 */
		showLoading: function(message) {
			$('#ppq-ai-loading-text').text(message);
			$('#ppq-ai-loading').show();
			$('#ppq-ai-generate-btn').prop('disabled', true);
		},

		/**
		 * Hide loading state
		 */
		hideLoading: function() {
			$('#ppq-ai-loading').hide();
			$('#ppq-ai-generate-btn').prop('disabled', false);
		},

		/**
		 * Show error message
		 */
		showError: function(message) {
			// Handle multi-line messages by converting newlines to <br>
			var formattedMessage = this.escapeHtml(message).replace(/\n/g, '<br>');
			$('#ppq-ai-error-text').html(formattedMessage);
			$('#ppq-ai-error').show();

			// Scroll error into view
			$('html, body').animate({
				scrollTop: $('#ppq-ai-error').offset().top - 100
			}, 300);
		},

		/**
		 * Hide error message
		 */
		hideError: function() {
			$('#ppq-ai-error').hide();
		},

		/**
		 * Format file size
		 */
		formatFileSize: function(bytes) {
			if (bytes === 0) return '0 Bytes';
			var k = 1024;
			var sizes = ['Bytes', 'KB', 'MB', 'GB'];
			var i = Math.floor(Math.log(bytes) / Math.log(k));
			return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
		},

		/**
		 * Escape HTML
		 */
		escapeHtml: function(text) {
			if (!text) return '';
			var div = document.createElement('div');
			div.appendChild(document.createTextNode(text));
			return div.innerHTML;
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		if ($('.ppq-ai-generation-panel').length) {
			PPQ_AI.init();
		}
	});

})(jQuery);
