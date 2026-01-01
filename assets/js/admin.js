/**
 * Admin JavaScript for PressPrimer Quiz
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * PPQ Admin namespace
	 *
	 * @since 1.0.0
	 */
	window.PPQ = window.PPQ || {};
	window.PPQ.Admin = {

		/**
		 * Initialize admin functionality
		 *
		 * @since 1.0.0
		 */
		init: function() {
			this.bindEvents();
			this.initComponents();
		},

		/**
		 * Bind event handlers
		 *
		 * @since 1.0.0
		 */
		bindEvents: function() {
			// Confirm delete actions
			$(document).on('click', '.ppq-delete-confirm', this.confirmDelete);

			// Handle form submissions
			$(document).on('submit', '.ppq-admin-form', this.handleFormSubmit);

			// Bank detail page tabs
			$(document).on('click', '.ppq-bank-tab', this.handleBankTabClick);

			// Remove question from bank - no confirmation needed
			// Form submits directly without intercepting
		},

		/**
		 * Initialize components
		 *
		 * @since 1.0.0
		 */
		initComponents: function() {
			// Component initialization will be added in future phases
		},

		/**
		 * Confirm delete action
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 */
		confirmDelete: function(e) {
			e.preventDefault();
			var $link = $(this);
			var href = $link.attr('href');

			PPQ.Admin.confirm(
				window.pressprimerQuizAdmin?.strings?.confirmDelete || 'Are you sure you want to delete this item?',
				function() {
					// Confirmed - navigate to delete URL
					window.location.href = href;
				},
				null,
				{
					title: window.pressprimerQuizAdmin?.strings?.confirmDeleteTitle || 'Delete Item',
					confirmText: window.pressprimerQuizAdmin?.strings?.delete || 'Delete',
					cancelText: window.pressprimerQuizAdmin?.strings?.cancel || 'Cancel'
				}
			);
		},

		/**
		 * Handle form submission
		 *
		 * @since 1.0.0
		 * @param {Event} e Submit event.
		 */
		handleFormSubmit: function(e) {
			// Form handling will be implemented in future phases
		},

		/**
		 * Handle bank detail page tab click
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 */
		handleBankTabClick: function(e) {
			e.preventDefault();

			var $tab = $(this);
			var tabId = $tab.data('tab');

			// Skip if already active
			if ($tab.hasClass('ppq-bank-tab--active')) {
				return;
			}

			// Remove active class from all tabs and content
			$('.ppq-bank-tab').removeClass('ppq-bank-tab--active');
			$('.ppq-bank-tab-content').removeClass('ppq-bank-tab-content--active');

			// Add active class to clicked tab and corresponding content
			$tab.addClass('ppq-bank-tab--active');
			$('.ppq-bank-tab-content[data-tab-content="' + tabId + '"]').addClass('ppq-bank-tab-content--active');
		},

		/**
		 * Make AJAX request
		 *
		 * @since 1.0.0
		 * @param {string} action AJAX action name.
		 * @param {Object} data   Data to send.
		 * @param {Function} success Success callback.
		 * @param {Function} error   Error callback.
		 */
		ajax: function(action, data, success, error) {
			data = data || {};
			data.action = 'pressprimer_quiz_' + action;
			data.nonce = window.pressprimerQuizAdmin.nonce;

			$.ajax({
				url: window.pressprimerQuizAdmin.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					if (response.success) {
						if (typeof success === 'function') {
							success(response.data);
						}
					} else {
						if (typeof error === 'function') {
							error(response.data);
						} else {
							PPQ.Admin.alert(
								window.pressprimerQuizAdmin?.strings?.error || 'An error occurred.',
								null,
								{ type: 'error', title: 'Error' }
							);
						}
					}
				},
				error: function(xhr, status, err) {
					if (typeof error === 'function') {
						error({
							status: status,
							error: err
						});
					} else {
						PPQ.Admin.alert(
							window.pressprimerQuizAdmin?.strings?.error || 'An error occurred.',
							null,
							{ type: 'error', title: 'Error' }
						);
					}
				}
			});
		},

		/**
		 * Show success notice
		 *
		 * @since 1.0.0
		 * @param {string} message Success message.
		 */
		showSuccess: function(message) {
			this.showNotice(message, 'success');
		},

		/**
		 * Show error notice
		 *
		 * @since 1.0.0
		 * @param {string} message Error message.
		 */
		showError: function(message) {
			this.showNotice(message, 'error');
		},

		/**
		 * Show notice
		 *
		 * @since 1.0.0
		 * @param {string} message Notice message.
		 * @param {string} type    Notice type (success, error, warning, info).
		 */
		showNotice: function(message, type) {
			type = type || 'info';
			var $notice = $('<div>')
				.addClass('ppq-notice notice-' + type)
				.html('<p>' + message + '</p>')
				.hide();

			$('.wrap > h1').after($notice);
			$notice.slideDown();

			// Auto-hide after 5 seconds
			setTimeout(function() {
				$notice.slideUp(function() {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Add loading state to element
		 *
		 * @since 1.0.0
		 * @param {jQuery} $element Element to add loading state to.
		 */
		setLoading: function($element) {
			$element.addClass('ppq-loading');
			$element.prop('disabled', true);

			if (!$element.find('.ppq-spinner').length) {
				$element.append('<span class="ppq-spinner"></span>');
			}
		},

		/**
		 * Remove loading state from element
		 *
		 * @since 1.0.0
		 * @param {jQuery} $element Element to remove loading state from.
		 */
		removeLoading: function($element) {
			$element.removeClass('ppq-loading');
			$element.prop('disabled', false);
			$element.find('.ppq-spinner').remove();
		},

		/**
		 * Show modal dialog
		 *
		 * @since 1.0.0
		 * @param {Object} options Modal options.
		 * @param {string} options.title Modal title.
		 * @param {string} options.message Modal message/content.
		 * @param {string} options.type Modal type (confirm, alert, info, warning, error).
		 * @param {string} options.confirmText Text for confirm button.
		 * @param {string} options.cancelText Text for cancel button.
		 * @param {Function} options.onConfirm Callback when confirmed.
		 * @param {Function} options.onCancel Callback when cancelled.
		 * @return {jQuery} Modal element.
		 */
		showModal: function(options) {
			var self = this;
			var defaults = {
				title: '',
				message: '',
				type: 'info',
				confirmText: window.pressprimerQuizAdmin?.strings?.ok || 'OK',
				cancelText: window.pressprimerQuizAdmin?.strings?.cancel || 'Cancel',
				onConfirm: null,
				onCancel: null
			};

			options = $.extend({}, defaults, options);

			// Remove any existing modal
			$('.ppq-modal-overlay').remove();

			// Determine icon based on type
			var iconClass = 'dashicons-info';
			var iconColorClass = 'ppq-modal-icon--info';
			switch (options.type) {
				case 'confirm':
				case 'warning':
					iconClass = 'dashicons-warning';
					iconColorClass = 'ppq-modal-icon--warning';
					break;
				case 'error':
					iconClass = 'dashicons-dismiss';
					iconColorClass = 'ppq-modal-icon--error';
					break;
				case 'success':
					iconClass = 'dashicons-yes-alt';
					iconColorClass = 'ppq-modal-icon--success';
					break;
			}

			// Build modal HTML
			var modalHtml =
				'<div class="ppq-modal-overlay">' +
					'<div class="ppq-modal">' +
						'<div class="ppq-modal-header">' +
							(options.title ? '<h3 class="ppq-modal-title">' + self.escapeHtml(options.title) + '</h3>' : '') +
							'<button type="button" class="ppq-modal-close" aria-label="Close">' +
								'<span class="dashicons dashicons-no-alt"></span>' +
							'</button>' +
						'</div>' +
						'<div class="ppq-modal-body">' +
							'<div class="ppq-modal-icon ' + iconColorClass + '">' +
								'<span class="dashicons ' + iconClass + '"></span>' +
							'</div>' +
							'<div class="ppq-modal-message">' + options.message + '</div>' +
						'</div>' +
						'<div class="ppq-modal-footer">' +
							(options.type === 'confirm' ?
								'<button type="button" class="button ppq-modal-cancel">' + self.escapeHtml(options.cancelText) + '</button>' : '') +
							'<button type="button" class="button button-primary ppq-modal-confirm">' + self.escapeHtml(options.confirmText) + '</button>' +
						'</div>' +
					'</div>' +
				'</div>';

			var $modal = $(modalHtml);
			$('body').append($modal);

			// Animate in
			setTimeout(function() {
				$modal.addClass('ppq-modal-overlay--visible');
			}, 10);

			// Focus the confirm button
			$modal.find('.ppq-modal-confirm').focus();

			// Handle confirm
			$modal.on('click', '.ppq-modal-confirm', function() {
				self.closeModal($modal);
				if (typeof options.onConfirm === 'function') {
					options.onConfirm();
				}
			});

			// Handle cancel
			$modal.on('click', '.ppq-modal-cancel, .ppq-modal-close', function() {
				self.closeModal($modal);
				if (typeof options.onCancel === 'function') {
					options.onCancel();
				}
			});

			// Handle overlay click (close)
			$modal.on('click', function(e) {
				if ($(e.target).hasClass('ppq-modal-overlay')) {
					self.closeModal($modal);
					if (typeof options.onCancel === 'function') {
						options.onCancel();
					}
				}
			});

			// Handle escape key
			$(document).on('keydown.ppqModal', function(e) {
				if (e.key === 'Escape') {
					self.closeModal($modal);
					if (typeof options.onCancel === 'function') {
						options.onCancel();
					}
				}
			});

			return $modal;
		},

		/**
		 * Close modal
		 *
		 * @since 1.0.0
		 * @param {jQuery} $modal Modal element to close.
		 */
		closeModal: function($modal) {
			$(document).off('keydown.ppqModal');
			$modal.removeClass('ppq-modal-overlay--visible');
			setTimeout(function() {
				$modal.remove();
			}, 200);
		},

		/**
		 * Show confirm dialog
		 *
		 * @since 1.0.0
		 * @param {string} message Confirmation message.
		 * @param {Function} onConfirm Callback when confirmed.
		 * @param {Function} onCancel Callback when cancelled.
		 * @param {Object} options Additional options.
		 */
		confirm: function(message, onConfirm, onCancel, options) {
			options = options || {};
			return this.showModal($.extend({
				type: 'confirm',
				title: options.title || (window.pressprimerQuizAdmin?.strings?.confirmTitle || 'Confirm'),
				message: message,
				confirmText: options.confirmText || (window.pressprimerQuizAdmin?.strings?.yes || 'Yes'),
				cancelText: options.cancelText || (window.pressprimerQuizAdmin?.strings?.no || 'No'),
				onConfirm: onConfirm,
				onCancel: onCancel
			}, options));
		},

		/**
		 * Show alert dialog
		 *
		 * @since 1.0.0
		 * @param {string} message Alert message.
		 * @param {Function} onClose Callback when closed.
		 * @param {Object} options Additional options.
		 */
		alert: function(message, onClose, options) {
			options = options || {};
			return this.showModal($.extend({
				type: options.type || 'info',
				title: options.title || '',
				message: message,
				confirmText: options.confirmText || (window.pressprimerQuizAdmin?.strings?.ok || 'OK'),
				onConfirm: onClose
			}, options));
		},

		/**
		 * Escape HTML entities
		 *
		 * @since 1.0.0
		 * @param {string} str String to escape.
		 * @return {string} Escaped string.
		 */
		escapeHtml: function(str) {
			if (!str) return '';
			var div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		PPQ.Admin.init();
	});

})(jQuery);
