/**
 * Consent badge — shown next to a guest's email in admin attempt views.
 *
 * Renders "Consented" (with the date on hover), "Declined", or nothing when the
 * checkbox was not offered. Logged-in attempts have a null consent value, so
 * they render no badge. Tri-state values may arrive as ints or as DB strings,
 * so both are accepted (feature 007, FR-006).
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { __, sprintf } from '@wordpress/i18n';
import { Tag, Tooltip } from 'antd';
import { CheckCircleOutlined } from '@ant-design/icons';

/**
 * Format a stored consent datetime for the tooltip, or '' when unavailable.
 *
 * @param {string} value Datetime string.
 * @return {string} Localized date-time, or empty string.
 */
const formatConsentDate = (value) => {
	if (!value) {
		return '';
	}
	const date = new Date(String(value).replace(' ', 'T'));
	return Number.isNaN(date.getTime()) ? '' : date.toLocaleString();
};

const ConsentBadge = ({ consent, consentAt }) => {
	if (consent === 1 || consent === '1') {
		const when = formatConsentDate(consentAt);
		let title = __('Marketing consent given', 'pressprimer-quiz');
		if (when) {
			/* translators: %s: date and time consent was given. */
			title = sprintf(__('Marketing consent given on %s', 'pressprimer-quiz'), when);
		}

		return (
			<Tooltip title={title}>
				<Tag color="green" icon={<CheckCircleOutlined />} style={{ marginLeft: 8 }}>
					{__('Consented', 'pressprimer-quiz')}
				</Tag>
			</Tooltip>
		);
	}

	if (consent === 0 || consent === '0') {
		return (
			<Tag style={{ marginLeft: 8 }}>
				{__('Declined', 'pressprimer-quiz')}
			</Tag>
		);
	}

	// NULL / undefined: the checkbox was not offered (or a logged-in user) — no badge.
	return null;
};

export default ConsentBadge;
