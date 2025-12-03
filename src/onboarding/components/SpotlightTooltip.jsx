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
 * @param {string} props.targetSelector CSS selector for the target element
 * @param {string} props.title Tooltip title
 * @param {string|React.ReactNode} props.content Tooltip content
 * @param {string} props.position Preferred tooltip position (top, bottom, left, right)
 * @param {number} props.padding Spotlight padding around target
 * @param {number} props.borderRadius Spotlight border radius
 * @param {boolean} props.pulse Whether to show pulse animation
 * @param {boolean} props.scrollIntoView Whether to scroll target into view
 * @param {number} props.currentStep Current step number
 * @param {number} props.totalSteps Total number of steps
 * @param {Function} props.onPrev Previous step handler
 * @param {Function} props.onNext Next step handler
 * @param {Function} props.onSkip Skip handler
 * @param {Function} props.onClose Close handler
 * @param {boolean} props.showNavigation Whether to show navigation buttons
 * @param {string} props.nextLabel Label for next button
 * @param {string} props.prevLabel Label for previous button
 * @param {Function} props.onTargetNotFound Callback when target element is not found
 */
const SpotlightTooltip = ({
	targetSelector,
	title,
	content,
	position = 'bottom',
	padding = 8,
	borderRadius = 8,
	pulse = true,
	scrollIntoView = true,
	currentStep,
	totalSteps,
	onPrev,
	onNext,
	onSkip,
	onClose,
	showNavigation = true,
	nextLabel = 'Next',
	prevLabel = 'Back',
	onTargetNotFound,
}) => {
	return (
		<Spotlight
			targetSelector={targetSelector}
			padding={padding}
			borderRadius={borderRadius}
			pulse={pulse}
			scrollIntoView={scrollIntoView}
			onTargetNotFound={onTargetNotFound}
		>
			{(targetRect) => (
				<Tooltip
					targetRect={targetRect}
					title={title}
					content={content}
					position={position}
					currentStep={currentStep}
					totalSteps={totalSteps}
					onPrev={onPrev}
					onNext={onNext}
					onSkip={onSkip}
					onClose={onClose}
					showNavigation={showNavigation}
					nextLabel={nextLabel}
					prevLabel={prevLabel}
				/>
			)}
		</Spotlight>
	);
};

export default SpotlightTooltip;
