/**
 * PressPrimer Quiz - Frontend Image Lightbox
 *
 * Lightweight click-to-zoom for images embedded in question stems, answer
 * options, and feedback fields. Self-contained (no jQuery, no external
 * dependencies). Uses a capture-phase click handler so it runs before the
 * answer-selection delegate in quiz.js — clicking an image inside an
 * answer card opens the lightbox WITHOUT also selecting that answer.
 *
 * @package PressPrimer_Quiz
 * @since 2.3.0
 */

(function () {
	'use strict';

	// Selectors whose <img> children should open the lightbox on click.
	var IMAGE_SELECTORS = [
		'.ppq-question-text img',
		'.ppq-answer-text img',
		'.ppq-answer-feedback img',
		'.ppq-feedback-text img',
		'.ppq-question-review-container img',
	];

	var overlay = null;
	var lastTrigger = null;
	var previousBodyOverflow = '';

	/**
	 * Lazily build the overlay element on first use and append it to <body>.
	 * The overlay is reused across all subsequent opens.
	 */
	function ensureOverlay() {
		if (overlay) {
			return overlay;
		}

		overlay = document.createElement('div');
		overlay.className = 'ppq-lightbox-overlay';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.setAttribute('aria-label', 'Image viewer');
		overlay.hidden = true;

		var closeBtn = document.createElement('button');
		closeBtn.type = 'button';
		closeBtn.className = 'ppq-lightbox-close';
		closeBtn.setAttribute('aria-label', 'Close image viewer');
		closeBtn.textContent = '×';

		var img = document.createElement('img');
		img.className = 'ppq-lightbox-image';
		img.alt = '';

		overlay.appendChild(closeBtn);
		overlay.appendChild(img);

		// Backdrop click closes (clicking the image itself does not — the image
		// is a child of the overlay, so this check picks up clicks on the
		// surrounding dark area only).
		overlay.addEventListener('click', function (event) {
			if (event.target === overlay) {
				closeLightbox();
			}
		});

		// Close button click.
		closeBtn.addEventListener('click', function (event) {
			event.stopPropagation();
			closeLightbox();
		});

		document.body.appendChild(overlay);
		return overlay;
	}

	function openLightbox(src, alt) {
		ensureOverlay();
		var img = overlay.querySelector('.ppq-lightbox-image');
		img.src = src;
		img.alt = alt || '';
		overlay.hidden = false;

		// Prevent the page from scrolling behind the overlay.
		previousBodyOverflow = document.body.style.overflow;
		document.body.style.overflow = 'hidden';

		document.addEventListener('keydown', onKeyDown, true);

		// Move focus into the dialog.
		var closeBtn = overlay.querySelector('.ppq-lightbox-close');
		if (closeBtn) {
			closeBtn.focus();
		}
	}

	function closeLightbox() {
		if (!overlay || overlay.hidden) {
			return;
		}
		overlay.hidden = true;
		document.body.style.overflow = previousBodyOverflow;
		document.removeEventListener('keydown', onKeyDown, true);

		// Restore focus to the image that opened the lightbox.
		if (lastTrigger && typeof lastTrigger.focus === 'function') {
			lastTrigger.focus();
		}
		lastTrigger = null;
	}

	function onKeyDown(event) {
		if (event.key === 'Escape' || event.keyCode === 27) {
			event.preventDefault();
			closeLightbox();
			return;
		}
		// Single focusable inside the dialog (close button). Trap Tab on it.
		if (event.key === 'Tab' || event.keyCode === 9) {
			event.preventDefault();
			var closeBtn = overlay.querySelector('.ppq-lightbox-close');
			if (closeBtn) {
				closeBtn.focus();
			}
		}
	}

	/**
	 * Check whether `target` is an <img> that matches one of our containers.
	 *
	 * @param {EventTarget} target Click target to test.
	 * @return {boolean} True if clicking the target should open the lightbox.
	 */
	function isLightboxTrigger(target) {
		if (!target || target.tagName !== 'IMG') {
			return false;
		}
		for (var i = 0; i < IMAGE_SELECTORS.length; i++) {
			if (target.matches && target.matches(IMAGE_SELECTORS[i])) {
				return true;
			}
		}
		return false;
	}

	// Capture-phase document listener. Runs BEFORE quiz.js's bubble-phase
	// answer-option click handler, so we can stop the answer from being
	// selected when the user clicks an image inside it.
	document.addEventListener(
		'click',
		function (event) {
			if (!isLightboxTrigger(event.target)) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			lastTrigger = event.target;
			openLightbox(event.target.src, event.target.alt);
		},
		true
	);
})();
