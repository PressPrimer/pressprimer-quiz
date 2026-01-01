/**
 * Debug logging utility
 *
 * Logs messages only when WP_DEBUG is enabled.
 * This follows WordPress.org best practices for plugin development.
 *
 * @package PressPrimer_Quiz
 */

/**
 * Check if debug mode is enabled
 *
 * @return {boolean} True if WP_DEBUG is enabled
 */
export const isDebugMode = () => {
	return window.pressprimerQuizAdmin?.debug === true;
};

/**
 * Log a debug message (only when WP_DEBUG is enabled)
 *
 * @param {...any} args - Arguments to log
 */
export const debugLog = (...args) => {
	if (isDebugMode()) {
		console.log('[PPQ]', ...args);
	}
};

/**
 * Log a debug error (only when WP_DEBUG is enabled)
 *
 * @param {...any} args - Arguments to log
 */
export const debugError = (...args) => {
	if (isDebugMode()) {
		console.error('[PPQ]', ...args);
	}
};

/**
 * Log a debug warning (only when WP_DEBUG is enabled)
 *
 * @param {...any} args - Arguments to log
 */
export const debugWarn = (...args) => {
	if (isDebugMode()) {
		console.warn('[PPQ]', ...args);
	}
};
