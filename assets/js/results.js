/**
 * Results Page JavaScript
 *
 * Handles email results functionality and other results page interactions.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * PressPrimer Quiz Results namespace
	 */
	window.PPQ = window.PPQ || {};
	window.PPQ.Results = {
		/**
		 * Initialize results page
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Email results button
			$(document).on('click', '.ppq-email-button', this.handleEmailClick.bind(this));
		},

		/**
		 * Handle email button click
		 *
		 * @param {Event} e Click event
		 */
		handleEmailClick: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const attemptId = $button.data('attempt-id');
			const email = $button.data('email');
			const $status = $('.ppq-email-status');

			// Disable button
			$button.prop('disabled', true);

			// Show loading state
			const originalText = $button.html();
			$button.html('<span class="ppq-spinner"></span> ' + ppqResults.sendingText);

			// Hide previous status
			$status.hide().removeClass('success error');

			// Send AJAX request
			$.ajax({
				url: ppqResults.ajaxUrl,
				type: 'POST',
				data: {
					action: 'ppq_email_results',
					nonce: ppqResults.nonce,
					attempt_id: attemptId,
					email: email
				},
				success: function(response) {
					if (response.success) {
						// Show success message
						$status
							.addClass('success')
							.html(response.data.message || ppqResults.successText)
							.fadeIn();

						// Keep button disabled with success icon
						$button.html('<span class="ppq-email-icon">✓</span> ' + ppqResults.sentText);

						// Re-enable button after 3 seconds
						setTimeout(function() {
							$button.prop('disabled', false).html(originalText);
						}, 3000);
					} else {
						// Show error message
						$status
							.addClass('error')
							.html(response.data.message || ppqResults.errorText)
							.fadeIn();

						// Re-enable button
						$button.prop('disabled', false).html(originalText);
					}
				},
				error: function() {
					// Show generic error
					$status
						.addClass('error')
						.html(ppqResults.errorText)
						.fadeIn();

					// Re-enable button
					$button.prop('disabled', false).html(originalText);
				}
			});
		}
	};

	// Initialize on DOM ready
	$(document).ready(function() {
		PPQ.Results.init();
	});

})(jQuery);
