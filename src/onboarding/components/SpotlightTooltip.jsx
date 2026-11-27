/**
 * SpotlightTooltip Component
 *
 * Convenience component combining Spotlight and Tooltip.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import Spotlight from './Spotlight';
import Tooltip from './Tooltip';

/**
 * SpotlightTooltip Component
 *
 * Combines Spotlight overlay with positioned Tooltip for tour steps.
 *
 * @param {Object} props Component props
 * @param {string} props.selector CSS selector for the target element
 * @param {string} props.title Tooltip title
 * @param {string|React.ReactNode} props.content Tooltip content
 * @param {string} props.position Preferred tooltip position (top, bottom, left, right)
 * @param {boolean} props.fitContent Frame the target's children instead of its padded box
 * @param {number} props.currentStep Current step number
 * @param {number} props.totalSteps Total number of steps
 * @param {boolean} props.nextDisabled Whether Next waits for the step's action
 * @param {string|null} props.nextDisabledReason Shown on the disabled Next
 * @param {Function} props.onPrev Previous step handler
 * @param {Function} props.onNext Next step handler
 * @param {Function} props.onSkip Skip handler
 * @param {Function} props.onClose Close handler
 */
const SpotlightTooltip = ({
	selector,
	title,
	content,
	position = 'bottom',
	fitContent = false,
	currentStep,
	totalSteps,
	nextDisabled = false,
	nextDisabledReason = null,
	onPrev,
	onNext,
	onSkip,
	onClose,
}) => {
	return (
		<Spotlight selector={selector} fitContent={fitContent}>
			{(targetRect) => (
				<Tooltip
					targetRect={targetRect}
					title={title}
					content={content}
					position={position}
					currentStep={currentStep}
					totalSteps={totalSteps}
					nextDisabled={nextDisabled}
					nextDisabledReason={nextDisabledReason}
					onPrev={onPrev}
					onNext={onNext}
					onSkip={onSkip}
					onClose={onClose}
				/>
			)}
		</Spotlight>
	);
};

export default SpotlightTooltip;
