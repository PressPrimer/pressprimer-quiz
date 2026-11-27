/**
 * Tour Session State
 *
 * The pending-advance marker. Some screens reload or redirect right after
 * the action that completes a step (the question editor redirects a second
 * after saving), and the reloaded page's server render can beat the
 * keepalive request that stores the new step. The marker lets that one
 * page load recognize the stale step and move on.
 *
 * The run's records (bank, questions, quiz, page) live on the server, which
 * validates them; only this marker lives in session storage.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

const ADVANCE_PENDING_KEY = 'pressprimerQuizSetupAdvancePending';

// Consumed at most once per page load; the memo keeps the answer stable
// for later callers after the marker is removed.
let advancePendingMemo;

/**
 * Mark an advance as pending
 *
 * @param {string} from Step id the advance leaves.
 * @param {string} to   Step id it lands on.
 */
export const writeAdvancePending = (from, to) => {
	try {
		window.sessionStorage.setItem(ADVANCE_PENDING_KEY, JSON.stringify({ from, to }));
	} catch (e) {
		// Session storage unavailable: reconciliation just won't run.
	}
};

/**
 * Consume the pending-advance marker (one-shot)
 *
 * Cleared on first read, so it only influences the page load that follows
 * the advance: going Back to that step later is never overridden.
 *
 * @return {{from: string, to: string}|null} The pending advance, or null.
 */
export const consumeAdvancePending = () => {
	if (undefined === advancePendingMemo) {
		advancePendingMemo = null;

		try {
			const parsed = JSON.parse(window.sessionStorage.getItem(ADVANCE_PENDING_KEY) || 'null');
			window.sessionStorage.removeItem(ADVANCE_PENDING_KEY);

			if (parsed && typeof parsed.from === 'string' && typeof parsed.to === 'string') {
				advancePendingMemo = parsed;
			}
		} catch (e) {
			// Unreadable marker: nothing pending.
		}
	}

	return advancePendingMemo;
};

/**
 * Forget the marker (called whenever a tour starts fresh)
 */
export const clearSetupSession = () => {
	try {
		window.sessionStorage.removeItem(ADVANCE_PENDING_KEY);
	} catch (e) {
		// Nothing to clear.
	}
	advancePendingMemo = null;
};
