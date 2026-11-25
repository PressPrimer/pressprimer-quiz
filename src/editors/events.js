/**
 * Editor events
 *
 * The quiz, question and bank editors announce what the author did with DOM
 * events on `document`, fired after the REST call succeeds and before the
 * editor navigates, on every surface. Addons and the onboarding tour listen
 * for them; the host's `onSaved` callback gets the same detail as the saved
 * events.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { debugError } from '../utils/debug';

/**
 * An entity ID as a number (REST responses and localized data carry
 * strings), or null when there is none.
 *
 * @param {number|string|null|undefined} value ID.
 * @return {number|null} The ID, or null.
 */
export const toId = (value) => {
	const id = parseInt(value, 10);
	return id > 0 ? id : null;
};

/**
 * Fire an editor event on document.
 *
 * @param {string} name   Event name (e.g. 'ppq-quiz-items-changed').
 * @param {Object} detail Event detail.
 */
export const emitEditorEvent = (name, detail) => {
	document.dispatchEvent(new CustomEvent(name, { detail }));
};

/**
 * Announce a save: fire the saved event, then call the host's onSaved.
 *
 * A host callback that throws is logged, so a save that succeeded is never
 * reported as failed.
 *
 * @param {string}        name    Saved event name (e.g. 'ppq-quiz-saved').
 * @param {Object}        detail  Event detail, also passed to onSaved.
 * @param {Function|null} onSaved The host's onSaved callback, if any.
 */
export const emitSaved = (name, detail, onSaved) => {
	emitEditorEvent(name, detail);

	if (typeof onSaved === 'function') {
		try {
			onSaved(detail);
		} catch (error) {
			debugError('onSaved callback failed:', error);
		}
	}
};
