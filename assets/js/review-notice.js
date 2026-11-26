/**
 * PressPrimer Quiz review notice (100 attempts celebration)
 *
 * Records the answer for the notice's buttons over admin-ajax and removes
 * the notice. "Yes, I love it!" and "It could be better" are real links
 * opening in a new tab (no popup-blocked window.open). Escape inside the
 * notice snoozes it, like "Remind me later".
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

/**
 * @param {Object} $ jQuery.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		const $notice = $( '.ppq-celebration-notice' );

		if ( ! $notice.length ) {
			return;
		}

		const nonce = $notice.data( 'ppq-review-nonce' );
		let answered = false;

		/**
		 * Record a response (the first one only) and remove the notice.
		 *
		 * @param {string} response review | feedback | snooze | dismiss.
		 */
		const answer = function ( response ) {
			if ( answered ) {
				return;
			}
			answered = true;

			$.post( window.ajaxurl, {
				action: 'pressprimer_quiz_celebration_' + response,
				nonce,
			} );

			$notice.fadeOut( 200, function () {
				$notice.remove();
			} );
		};

		$notice.on( 'click', '[data-ppq-review]', function ( event ) {
			const response = $( this ).data( 'ppq-review' );

			// Links still open their page in a new tab.
			if ( 'A' !== this.tagName ) {
				event.preventDefault();
			}

			answer( response );
		} );

		// Only while focus is inside the notice, so Escape elsewhere on the
		// page (closing a modal, a menu) never answers it.
		$notice.on( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				event.preventDefault();
				answer( 'snooze' );
			}
		} );
	} );
} )( window.jQuery );
