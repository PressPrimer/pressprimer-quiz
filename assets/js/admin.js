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
		},

		/**
		 * Initialize components
		 *
		 * @since 1.0.0
		 */
		initComponents: function() {
			// Component initialization will be added in future phases
			console.log('PPQ Admin initialized');
		},

		/**
		 * Confirm delete action
		 *
		 * @since 1.0.0
		 * @param {Event} e Click event.
		 */
		confirmDelete: function(e) {
			if (!confirm(window.ppqAdmin.strings.confirmDelete)) {
				e.preventDefault();
				return false;
			}
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
			data.action = 'ppq_' + action;
			data.nonce = window.ppqAdmin.nonce;

			$.ajax({
				url: window.ppqAdmin.ajaxUrl,
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
							alert(window.ppqAdmin.strings.error);
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
						alert(window.ppqAdmin.strings.error);
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
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		PPQ.Admin.init();
	});

})(jQuery);
