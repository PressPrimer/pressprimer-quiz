/**
 * Tooltip Component
 *
 * Positioned tooltip for spotlight explanations.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Tooltip as AntTooltip } from 'antd';
import { LeftOutlined, RightOutlined, CloseOutlined } from '@ant-design/icons';

/**
 * Calculate optimal position for tooltip
 *
 * @param {Object} targetRect Target element bounding rect
 * @param {Object} tooltipRect Tooltip element bounding rect
 * @param {string} preferredPosition Preferred position (top, bottom, left, right)
 * @param {number} offset Distance from target element
 * @return {Object} Position object with top, left, and actual position
 */
const calculatePosition = (targetRect, tooltipRect, preferredPosition = 'bottom', offset = 16) => {
	const viewport = {
		width: window.innerWidth,
		height: window.innerHeight,
	};

	const positions = {
		top: {
			top: targetRect.top - tooltipRect.height - offset,
			left: targetRect.left + (targetRect.width - tooltipRect.width) / 2,
		},
		bottom: {
			top: targetRect.top + targetRect.height + offset,
			left: targetRect.left + (targetRect.width - tooltipRect.width) / 2,
		},
		left: {
			top: targetRect.top + (targetRect.height - tooltipRect.height) / 2,
			left: targetRect.left - tooltipRect.width - offset,
		},
		right: {
			top: targetRect.top + (targetRect.height - tooltipRect.height) / 2,
			left: targetRect.left + targetRect.width + offset,
		},
	};

	/**
	 * Check if position fits in viewport
	 */
	const fitsInViewport = (pos) => {
		return (
			pos.top >= 10 &&
			pos.left >= 10 &&
			pos.top + tooltipRect.height <= viewport.height - 10 &&
			pos.left + tooltipRect.width <= viewport.width - 10
		);
	};

	// Try preferred position first
	if (fitsInViewport(positions[preferredPosition])) {
		return {
			...positions[preferredPosition],
			position: preferredPosition,
		};
	}

	// Try other positions in order of preference
	const fallbackOrder = ['bottom', 'top', 'right', 'left'].filter((p) => p !== preferredPosition);

	for (const pos of fallbackOrder) {
		if (fitsInViewport(positions[pos])) {
			return {
				...positions[pos],
				position: pos,
			};
		}
	}

	// If nothing fits, use bottom and constrain to viewport
	const constrained = { ...positions.bottom };
	constrained.top = Math.max(10, Math.min(constrained.top, viewport.height - tooltipRect.height - 10));
	constrained.left = Math.max(10, Math.min(constrained.left, viewport.width - tooltipRect.width - 10));
	constrained.position = 'bottom';

	return constrained;
};

/**
 * Tooltip Component
 *
 * @param {Object} props Component props
 * @param {Object} props.targetRect Target element bounding rect from Spotlight
 * @param {string} props.title Tooltip title
 * @param {string|React.ReactNode} props.content Tooltip content
 * @param {string} props.position Preferred position (top, bottom, left, right)
 * @param {number} props.currentStep Current step number
 * @param {number} props.totalSteps Total number of steps
 * @param {boolean} props.nextDisabled Whether Next waits for the step's action
 * @param {string|null} props.nextDisabledReason Shown on the disabled Next
 * @param {Function} props.onPrev Previous step handler
 * @param {Function} props.onNext Next step handler
 * @param {Function} props.onSkip Skip handler
 * @param {Function} props.onClose Close handler
 */
const Tooltip = ({
	targetRect,
	title,
	content,
	position = 'bottom',
	currentStep,
	totalSteps,
	nextDisabled = false,
	nextDisabledReason = null,
	onPrev,
	onNext,
	onSkip,
	onClose,
}) => {
	const tooltipRef = useRef(null);
	const [tooltipStyle, setTooltipStyle] = useState({ opacity: 0 });
	const [arrowPosition, setArrowPosition] = useState(position);

	/**
	 * Update tooltip position
	 */
	const updatePosition = useCallback(() => {
		if (!tooltipRef.current || !targetRect) {
			return;
		}

		const tooltipRect = tooltipRef.current.getBoundingClientRect();
		const calculated = calculatePosition(targetRect, tooltipRect, position);

		setTooltipStyle({
			position: 'fixed',
			top: calculated.top,
			left: calculated.left,
			opacity: 1,
			zIndex: 100001,
		});
		setArrowPosition(calculated.position);
	}, [targetRect, position]);

	/**
	 * Update position when target or tooltip changes
	 */
	useEffect(() => {
		// Initial position calculation after render
		const timer = setTimeout(updatePosition, 50);

		// Handle resize
		window.addEventListener('resize', updatePosition);

		return () => {
			clearTimeout(timer);
			window.removeEventListener('resize', updatePosition);
		};
	}, [updatePosition]);

	if (!targetRect) {
		return null;
	}

	return (
		<div
			ref={tooltipRef}
			className={`ppq-tooltip ppq-tooltip--${arrowPosition}`}
			style={tooltipStyle}
		>
			{/* Arrow */}
			<div className={`ppq-tooltip__arrow ppq-tooltip__arrow--${arrowPosition}`} />

			{/* Close button */}
			{onClose && (
				<button
					type="button"
					className="ppq-tooltip__close"
					onClick={onClose}
					aria-label={__('Close', 'pressprimer-quiz')}
				>
					<CloseOutlined />
				</button>
			)}

			{/* Content */}
			<div className="ppq-tooltip__content">
				{title && <h4 className="ppq-tooltip__title">{title}</h4>}
				{content && <div className="ppq-tooltip__body">{content}</div>}
			</div>

			{/* Navigation */}
			<div className="ppq-tooltip__navigation">
				<div className="ppq-tooltip__nav-left">
					{onPrev && (
						<Button
							type="text"
							icon={<LeftOutlined />}
							onClick={onPrev}
							size="small"
						>
							{__('Back', 'pressprimer-quiz')}
						</Button>
					)}
				</div>

				<div className="ppq-tooltip__nav-center">
					{currentStep > 0 && totalSteps > 0 && (
						<span
							className="ppq-tooltip__step-indicator"
							aria-label={sprintf(
								/* translators: 1: current step number, 2: total number of steps */
								__('Step %1$d of %2$d', 'pressprimer-quiz'),
								currentStep,
								totalSteps
							)}
						>
							{currentStep} / {totalSteps}
						</span>
					)}
				</div>

				<div className="ppq-tooltip__nav-right">
					{onSkip && currentStep < totalSteps && (
						<Button type="text" onClick={onSkip} size="small">
							{__('Skip', 'pressprimer-quiz')}
						</Button>
					)}
					{onNext && (
						<AntTooltip
							title={nextDisabled ? nextDisabledReason : null}
							overlayStyle={{ zIndex: 100002 }}
						>
							{/* Disabled buttons swallow mouse events; the span
							    keeps the reason's tooltip working. */}
							<span className="ppq-tooltip__next-wrap">
								<Button
									type="primary"
									onClick={onNext}
									size="small"
									disabled={nextDisabled}
								>
									{currentStep === totalSteps ? __('Finish', 'pressprimer-quiz') : __('Next', 'pressprimer-quiz')}
									{currentStep < totalSteps && <RightOutlined />}
								</Button>
							</span>
						</AntTooltip>
					)}
				</div>
			</div>
		</div>
	);
};

export default Tooltip;
