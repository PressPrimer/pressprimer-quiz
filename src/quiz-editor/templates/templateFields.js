/**
 * Quiz settings template field metadata.
 *
 * Maps each template settings key to a human label and value formatter that
 * mirror the Quiz Builder's existing field labels, so the Apply preview reads
 * the same way the Settings tab does. Also classifies the three JSON-blob keys
 * and addon-gated keys so the preview can show old → new for simple fields and
 * "skipped" for keys it cannot apply.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { __, sprintf } from '@wordpress/i18n';

const yesNo = (value) =>
	value ? __('Yes', 'pressprimer-quiz') : __('No', 'pressprimer-quiz');

const enumLabel = (labels) => (value) =>
	Object.prototype.hasOwnProperty.call(labels, value) ? labels[value] : String(value ?? '');

const MODE_LABELS = {
	tutorial: __('Tutorial Mode', 'pressprimer-quiz'),
	timed: __('Test Mode', 'pressprimer-quiz'),
};
const PAGE_MODE_LABELS = {
	single: __('Single Page', 'pressprimer-quiz'),
	paged: __('Paginated', 'pressprimer-quiz'),
};
const SHOW_ANSWERS_LABELS = {
	never: __('Never', 'pressprimer-quiz'),
	after_submit: __('After Submit', 'pressprimer-quiz'),
	after_pass: __('After Passing', 'pressprimer-quiz'),
};
const ACCESS_MODE_LABELS = {
	default: __('Use Global Default', 'pressprimer-quiz'),
	guest_optional: __('Allow Guests (Email Optional)', 'pressprimer-quiz'),
	guest_required: __('Allow Guests (Email Required)', 'pressprimer-quiz'),
	login_required: __('Require Login', 'pressprimer-quiz'),
};
const DENSITY_LABELS = {
	default: __('Use Global Default', 'pressprimer-quiz'),
	standard: __('Standard', 'pressprimer-quiz'),
	condensed: __('Condensed', 'pressprimer-quiz'),
};
const THEME_LABELS = {
	default: __('Default', 'pressprimer-quiz'),
	modern: __('Modern', 'pressprimer-quiz'),
	minimal: __('Minimal', 'pressprimer-quiz'),
};
const MA_SCORING_LABELS = {
	right_minus_wrong: __('Right Minus Wrong', 'pressprimer-quiz'),
	proportional: __('Partial Credit', 'pressprimer-quiz'),
	partial_no_wrong: __('Partial Credit, No Wrong Answers', 'pressprimer-quiz'),
	all_or_nothing: __('All or Nothing', 'pressprimer-quiz'),
};

// Friendly summary for JSON-blob settings (display defaults, feedback bands):
// empty/unset reads as "Default", anything else as "Customized" — never raw "[]".
const jsonSummary = (value) => {
	if (value === null || value === undefined || value === '') {
		return __('Default', 'pressprimer-quiz');
	}
	let parsed = value;
	if (typeof value === 'string') {
		try {
			parsed = JSON.parse(value);
		} catch (e) {
			return __('Customized', 'pressprimer-quiz');
		}
	}
	if (parsed === null) {
		return __('Default', 'pressprimer-quiz');
	}
	if (Array.isArray(parsed)) {
		return parsed.length ? __('Customized', 'pressprimer-quiz') : __('Default', 'pressprimer-quiz');
	}
	if (typeof parsed === 'object') {
		return Object.keys(parsed).length ? __('Customized', 'pressprimer-quiz') : __('Default', 'pressprimer-quiz');
	}
	return __('Customized', 'pressprimer-quiz');
};

const minutes = (value) =>
	value
		? sprintf(
				/* translators: %d: number of minutes. */
				__('%d min', 'pressprimer-quiz'),
				Math.round(Number(value) / 60)
		  )
		: __('Unlimited', 'pressprimer-quiz');

const countOrUnlimited = (value) =>
	value ? String(value) : __('Unlimited', 'pressprimer-quiz');

/**
 * Ordered field metadata, keyed by settings key. `type` drives value
 * comparison; `requiresAddon` marks a key the free editor cannot apply unless
 * the named addon is active; `json` marks a blob key with no simple old → new.
 */
export const TEMPLATE_FIELDS = [
	{ key: 'mode', label: __('Quiz Mode', 'pressprimer-quiz'), type: 'enum', format: enumLabel(MODE_LABELS) },
	{ key: 'time_limit_seconds', label: __('Time Limit', 'pressprimer-quiz'), type: 'number', format: minutes },
	{ key: 'pass_percent', label: __('Passing Score', 'pressprimer-quiz'), type: 'number', format: (v) => `${Number(v)}%` },
	{ key: 'allow_skip', label: __('Allow Skip Questions', 'pressprimer-quiz'), type: 'bool', format: yesNo },
	{ key: 'allow_backward', label: __('Allow Backward Navigation', 'pressprimer-quiz'), type: 'bool', format: yesNo },
	{ key: 'allow_resume', label: __('Allow Resume', 'pressprimer-quiz'), type: 'bool', format: yesNo },
	{ key: 'max_attempts', label: __('Maximum Attempts', 'pressprimer-quiz'), type: 'number', format: countOrUnlimited },
	{ key: 'attempt_delay_minutes', label: __('Delay Between Attempts', 'pressprimer-quiz'), type: 'number', format: (v) => sprintf( /* translators: %d: number of minutes. */ __('%d min', 'pressprimer-quiz'), Number(v) || 0 ) },
	{ key: 'randomize_questions', label: __('Randomize Question Order', 'pressprimer-quiz'), type: 'bool', format: yesNo },
	{ key: 'randomize_answers', label: __('Randomize Answer Options', 'pressprimer-quiz'), type: 'bool', format: yesNo },
	{ key: 'page_mode', label: __('Page Mode', 'pressprimer-quiz'), type: 'enum', format: enumLabel(PAGE_MODE_LABELS) },
	{ key: 'questions_per_page', label: __('Questions Per Page', 'pressprimer-quiz'), type: 'number', format: (v) => String(v) },
	{ key: 'show_answers', label: __('Show Correct Answers', 'pressprimer-quiz'), type: 'enum', format: enumLabel(SHOW_ANSWERS_LABELS) },
	{ key: 'enable_confidence', label: __('Enable Confidence Rating', 'pressprimer-quiz'), type: 'bool', format: yesNo },
	{ key: 'show_points', label: __('Show Points Per Question', 'pressprimer-quiz'), type: 'bool', format: yesNo },
	{ key: 'theme', label: __('Theme', 'pressprimer-quiz'), type: 'enum', format: enumLabel(THEME_LABELS) },
	{ key: 'access_mode', label: __('Access Mode', 'pressprimer-quiz'), type: 'enum', format: enumLabel(ACCESS_MODE_LABELS) },
	{ key: 'login_message', label: __('Custom Login Message', 'pressprimer-quiz'), type: 'text', format: (v) => ( v ? __('Custom message', 'pressprimer-quiz') : __('Default', 'pressprimer-quiz') ) },
	{ key: 'ma_scoring_mode', label: __('Multiple-Answer Scoring', 'pressprimer-quiz'), type: 'enum', format: (v) => ( v ? enumLabel(MA_SCORING_LABELS)(v) : __('Use Site Default', 'pressprimer-quiz') ) },
	{ key: 'display_density', label: __('Display Density', 'pressprimer-quiz'), type: 'enum', format: enumLabel(DENSITY_LABELS) },
	{ key: 'max_answers_per_question', label: __('Maximum Answers Per Question', 'pressprimer-quiz'), type: 'number', format: (v) => ( v ? String(v) : __('All answers', 'pressprimer-quiz') ) },
	{ key: 'pool_enabled', label: __('Limit Questions Per Attempt', 'pressprimer-quiz'), type: 'bool', format: yesNo },
	{ key: 'max_questions', label: __('Questions Per Attempt', 'pressprimer-quiz'), type: 'number', format: (v) => ( v ? String(v) : __('Not limited', 'pressprimer-quiz') ) },
	{ key: 'display_settings_json', label: __('Display Defaults', 'pressprimer-quiz'), type: 'json', format: jsonSummary, formField: 'display_settings' },
	{ key: 'band_feedback_json', label: __('Score Feedback Bands', 'pressprimer-quiz'), type: 'json', format: jsonSummary },
	{ key: 'enable_sr', label: __('Spaced Repetition', 'pressprimer-quiz'), type: 'bool', format: yesNo, requiresAddon: 'school' },
	// Core key with no field in this builder, so it cannot be applied here.
	{ key: 'theme_settings_json', label: __('Theme Settings', 'pressprimer-quiz'), type: 'json', format: jsonSummary, unappliable: true },
];

export const TEMPLATE_FIELD_MAP = TEMPLATE_FIELDS.reduce((map, field) => {
	map[field.key] = field;
	return map;
}, {});

/** All settings keys a template may carry (matches the server include map). */
export const TEMPLATE_SETTINGS_KEYS = TEMPLATE_FIELDS.map((field) => field.key);

/**
 * Capture the editor's current values as a template settings payload.
 *
 * Full snapshot of the included settings keys: form fields map directly,
 * feedback bands map to band_feedback_json, the display_settings object maps to
 * display_settings_json. theme_settings_json has no editor field and is
 * omitted; enable_sr is only captured when the School addon is active. The
 * server sanitizes every value on save, so raw form values are sent as-is.
 *
 * @param {Object} values        Form values (form.getFieldsValue(true)).
 * @param {Array}  feedbackBands Current feedback bands state.
 * @param {Object} quizData      Quiz editor boot data (addon flags).
 * @return {Object} Settings payload keyed by settings key.
 */
export function buildTemplateSettings(values = {}, feedbackBands = [], quizData = {}) {
	const settings = {};

	TEMPLATE_SETTINGS_KEYS.forEach((key) => {
		if ('theme_settings_json' === key) {
			return;
		}
		if ('band_feedback_json' === key) {
			settings[key] = Array.isArray(feedbackBands) ? feedbackBands : [];
			return;
		}
		if ('display_settings_json' === key) {
			settings[key] =
				values.display_settings && typeof values.display_settings === 'object'
					? values.display_settings
					: {};
			return;
		}
		if ('enable_sr' === key && !quizData.schoolActive) {
			return;
		}
		if (Object.prototype.hasOwnProperty.call(values, key) && values[key] !== undefined) {
			settings[key] = values[key];
		}
	});

	return settings;
}

/**
 * Whether the addon a key requires is active for this quiz.
 *
 * @param {string} addon    Addon slug ('school', 'educator', 'enterprise').
 * @param {Object} quizData Quiz editor boot data with *Active flags.
 * @return {boolean} True when the addon is active.
 */
export function isAddonActive(addon, quizData = {}) {
	const flags = {
		school: !!quizData.schoolActive,
		educator: !!quizData.educatorActive,
		enterprise: !!quizData.enterpriseActive,
	};
	return !!flags[addon];
}

/**
 * Human label for the addon a key requires (for "skipped" rows).
 *
 * @param {string} addon Addon slug.
 * @return {string} Display name.
 */
export function addonLabel(addon) {
	const labels = {
		school: __('School add-on', 'pressprimer-quiz'),
		educator: __('Educator add-on', 'pressprimer-quiz'),
		enterprise: __('Enterprise add-on', 'pressprimer-quiz'),
	};
	return labels[addon] || __('an add-on', 'pressprimer-quiz');
}

/**
 * Format a settings value for display using its field metadata.
 *
 * @param {string} key   Settings key.
 * @param {*}      value Raw value.
 * @return {string} Human-readable value.
 */
export function formatTemplateValue(key, value) {
	const field = TEMPLATE_FIELD_MAP[key];
	if (field && typeof field.format === 'function') {
		return field.format(value);
	}
	if (value === null || value === undefined || value === '') {
		return __('Not set', 'pressprimer-quiz');
	}
	return String(value);
}

/**
 * Normalize a value to a comparable primitive for the given field type.
 *
 * @param {Object} field Field metadata.
 * @param {*}      value Raw value (editor form value or template value).
 * @return {string} Comparison key.
 */
export function normalizeForCompare(field, value) {
	if (!field) {
		return JSON.stringify(value ?? null);
	}
	switch (field.type) {
		case 'bool':
			return value === true || Number(value) === 1 ? '1' : '0';
		case 'number':
			return value === null || value === undefined || value === ''
				? ''
				: String(Number(value));
		case 'json':
			return jsonCompareKey(value);
		default:
			return value === null || value === undefined ? '' : String(value);
	}
}

/**
 * Build a stable comparison key for a JSON blob (object/array or JSON string).
 *
 * @param {*} value Object, array, or JSON string.
 * @return {string} Stable string.
 */
export function jsonCompareKey(value) {
	if (value === null || value === undefined || value === '') {
		return '';
	}
	if (typeof value === 'string') {
		try {
			return JSON.stringify(JSON.parse(value));
		} catch (e) {
			return value;
		}
	}
	try {
		return JSON.stringify(value);
	} catch (e) {
		return '';
	}
}
