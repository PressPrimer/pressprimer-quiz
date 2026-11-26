/**
 * AI generation helpers
 *
 * Labels, file checks and error text shared by the panel's components.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { __, sprintf } from '@wordpress/i18n';

/**
 * The file types extraction reads, by extension and MIME type. Some browsers
 * send no MIME type for Word files, so either one is enough here; the server
 * checks the file itself.
 */
const FILE_EXTENSIONS = ['pdf', 'docx'];
const FILE_TYPES = [
	'application/pdf',
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

export const FILE_ACCEPT = '.pdf,.docx,' + FILE_TYPES.join(',');

/**
 * Question type labels.
 *
 * @return {Object} Labels by type.
 */
export const getTypeLabels = () => ({
	mc: __('Multiple Choice', 'pressprimer-quiz'),
	ma: __('Multiple Answer', 'pressprimer-quiz'),
	tf: __('True/False', 'pressprimer-quiz'),
});

/**
 * Difficulty labels and tag colours, as the generator names the levels.
 *
 * @return {Object} { label, color } by level.
 */
export const getDifficultyLevels = () => ({
	easy: { label: __('Easy', 'pressprimer-quiz'), color: 'green' },
	medium: { label: __('Medium', 'pressprimer-quiz'), color: 'gold' },
	hard: { label: __('Hard', 'pressprimer-quiz'), color: 'red' },
	expert: { label: __('Expert', 'pressprimer-quiz'), color: 'purple' },
});

/**
 * A file size for people (1.5 MB).
 *
 * @param {number} bytes Size in bytes.
 * @return {string} The size.
 */
export const formatFileSize = (bytes) => {
	const size = Number(bytes) || 0;
	if (size < 1024) {
		/* translators: %d: file size in bytes. */
		return sprintf(__('%d bytes', 'pressprimer-quiz'), size);
	}
	const round = (value) => parseFloat(value.toFixed(value < 10 ? 1 : 0)).toLocaleString();
	const kilobytes = size / 1024;
	if (kilobytes < 1024) {
		/* translators: %s: file size in kilobytes. */
		return sprintf(__('%s KB', 'pressprimer-quiz'), round(kilobytes));
	}
	const megabytes = kilobytes / 1024;
	if (megabytes < 1024) {
		/* translators: %s: file size in megabytes. */
		return sprintf(__('%s MB', 'pressprimer-quiz'), round(megabytes));
	}
	/* translators: %s: file size in gigabytes. */
	return sprintf(__('%s GB', 'pressprimer-quiz'), round(megabytes / 1024));
};

/**
 * Whether a file is a PDF or Word file, by its name or type.
 *
 * @param {File} file The file.
 * @return {boolean} Whether extraction can read it.
 */
export const isSupportedFile = (file) => {
	const extension = String(file?.name || '').split('.').pop().toLowerCase();
	return FILE_EXTENSIONS.includes(extension) || FILE_TYPES.includes(file?.type);
};

/**
 * The tokens a generation used, as text, from either provider's usage shape.
 *
 * @param {Object} usage The response's token_usage.
 * @return {string} "Tokens used: …", or '' when the provider reported none.
 */
export const describeTokens = (usage = {}) => {
	const prompt = Number(usage.prompt_tokens ?? usage.input_tokens) || 0;
	const completion = Number(usage.completion_tokens ?? usage.output_tokens) || 0;
	const total = Number(usage.total_tokens) || prompt + completion;

	if (!prompt && !completion) {
		return '';
	}

	return sprintf(
		/* translators: 1: prompt tokens, 2: completion tokens, 3: total tokens. */
		__('Tokens used: %1$s prompt, %2$s completion, %3$s total', 'pressprimer-quiz'),
		prompt.toLocaleString(),
		completion.toLocaleString(),
		total.toLocaleString()
	);
};

/**
 * An apiFetch error as { message, details }.
 *
 * @param {Object} error The rejection.
 * @return {Object} { message, details: string[] }.
 */
export const describeError = (error) => {
	// Not JSON: a proxy or server gave up before WordPress answered.
	if ('invalid_json' === error?.code) {
		return {
			message: __('The server didn’t respond in time or returned an error. Try again with less content or fewer questions.', 'pressprimer-quiz'),
			details: [],
		};
	}

	const data = error?.data || {};
	let details = [];

	if (Array.isArray(data.validation_errors)) {
		details = data.validation_errors.slice(0, 3).map((item) => sprintf(
			/* translators: 1: question number, 2: error message */
			__('Question %1$d: %2$s', 'pressprimer-quiz'),
			item.index,
			item.message
		));
	} else if (Array.isArray(data.errors)) {
		details = data.errors;
	}

	return {
		message: error?.message || __('An error occurred. Please try again.', 'pressprimer-quiz'),
		details,
	};
};
