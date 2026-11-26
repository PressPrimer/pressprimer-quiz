/**
 * UpsellPrompt - inline premium touchpoint
 *
 * One sentence and one link, placed where an addon's real control appears
 * once its tier is active (feature 012, ported from Certificate). The server
 * sends only the touchpoints the current user may see
 * (PressPrimer_Quiz_Premium_Catalog::touchpoints_for_surface()): admins,
 * upsells on, tier inactive, never on the front-end dashboard. So this
 * component never decides visibility: an absent payload renders nothing.
 *
 * Static: no dismissal, no animation. Render it as a sibling of an addon's
 * mount point, never inside it. Styles: assets/css/admin.css (wp-admin only).
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { __ } from '@wordpress/i18n';
import { Typography } from 'antd';
import { LockOutlined } from '@ant-design/icons';

const { Text } = Typography;

/**
 * UpsellPrompt component.
 *
 * @param {Object}      props            Component props.
 * @param {Object|null} props.touchpoint Touchpoint payload ({ key, copy, linkText, url }).
 * @param {boolean}     props.compact    Tighter padding for tight spots.
 * @param {Object|null} props.style      Optional inline style for the root element.
 * @return {JSX.Element|null} The prompt, or null without a payload.
 */
const UpsellPrompt = ({ touchpoint, compact = false, style = null }) => {
	if (!touchpoint || !touchpoint.copy || !touchpoint.url) {
		return null;
	}

	return (
		<div
			className={`ppq-upsell-prompt${compact ? ' ppq-upsell-prompt--compact' : ''}`}
			style={style || undefined}
			data-touchpoint={touchpoint.key}
		>
			<LockOutlined className="ppq-upsell-prompt__icon" aria-hidden="true" />
			{/* Sentence and link wrap as one line of text beside the icon. */}
			<span className="ppq-upsell-prompt__body">
				<Text className="ppq-upsell-prompt__copy">{touchpoint.copy}</Text>{' '}
				<a
					className="ppq-upsell-prompt__link"
					href={touchpoint.url}
					target="_blank"
					rel="noopener noreferrer"
				>
					{touchpoint.linkText}
					<span aria-hidden="true"> →</span>
					<span className="screen-reader-text">
						{__('(opens in a new tab)', 'pressprimer-quiz')}
					</span>
				</a>
			</span>
		</div>
	);
};

export default UpsellPrompt;
