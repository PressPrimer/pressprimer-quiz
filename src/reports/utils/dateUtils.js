/**
 * Date Utilities for Reports
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

/**
 * Get date range based on preset
 *
 * @param {string} preset Preset name (7days, 30days, 90days, all)
 * @return {Object} Date range with from and to dates (YYYY-MM-DD format)
 */
export const getDateRange = (preset) => {
	// For "all", return null dates (no filtering)
	if (preset === 'all') {
		return { from: null, to: null };
	}

	// Get today's date at end of day for the "to" date
	const today = new Date();
	// Add one day to include all of today's records
	const tomorrow = new Date(today);
	tomorrow.setDate(tomorrow.getDate() + 1);
	const to = tomorrow.toISOString().split('T')[0];

	// Calculate "from" date based on preset
	const fromDate = new Date(today);

	switch (preset) {
		case '7days':
			fromDate.setDate(fromDate.getDate() - 6); // -6 to include today as day 7
			break;
		case '30days':
			fromDate.setDate(fromDate.getDate() - 29); // -29 to include today as day 30
			break;
		case '90days':
			fromDate.setDate(fromDate.getDate() - 89); // -89 to include today as day 90
			break;
		default:
			return { from: null, to: null };
	}

	const from = fromDate.toISOString().split('T')[0];

	return { from, to };
};

/**
 * Format elapsed time from milliseconds
 *
 * @param {number} ms Milliseconds
 * @return {string} Formatted time string
 */
export const formatTime = (ms) => {
	if (!ms) return '-';
	const seconds = Math.floor(ms / 1000);
	const minutes = Math.floor(seconds / 60);
	const hours = Math.floor(minutes / 60);

	if (hours > 0) {
		return `${hours}h ${minutes % 60}m`;
	}
	if (minutes > 0) {
		return `${minutes}m ${seconds % 60}s`;
	}
	return `${seconds}s`;
};

/**
 * Format seconds to human readable duration
 *
 * @param {number} seconds Total seconds
 * @return {string} Formatted duration
 */
export const formatDuration = (seconds) => {
	if (!seconds || seconds === 0) return '-';

	const mins = Math.floor(seconds / 60);
	const secs = seconds % 60;

	if (mins === 0) {
		return `${secs}s`;
	}

	return `${mins}m ${secs}s`;
};

/**
 * Format MySQL datetime to localized date string
 *
 * @param {string} dateStr MySQL datetime string
 * @param {boolean} includeTime Whether to include time
 * @return {string} Formatted date
 */
export const formatDate = (dateStr, includeTime = true) => {
	if (!dateStr) return '-';

	// Normalize MySQL datetime to ISO format with UTC timezone
	let normalizedDate = dateStr;
	if (!dateStr.endsWith('Z') && !dateStr.includes('+') && !dateStr.includes('T')) {
		normalizedDate = dateStr.replace(' ', 'T') + 'Z';
	}

	const date = new Date(normalizedDate);
	if (isNaN(date.getTime())) return '-';

	const options = {
		month: 'short',
		day: 'numeric',
		year: 'numeric',
	};

	if (includeTime) {
		options.hour = '2-digit';
		options.minute = '2-digit';
	}

	return date.toLocaleDateString(undefined, options);
};
