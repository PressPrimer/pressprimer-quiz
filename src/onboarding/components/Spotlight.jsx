/**
 * Spotlight Component
 *
 * SVG mask overlay with cutout for highlighting target elements.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect, useCallback, useRef, createPortal } from '@wordpress/element';

/**
 * Spotlight Component
 *
 * Creates an overlay with a transparent cutout around the target element.
 *
 * @param {Object} props Component props
 * @param {string} props.targetSelector CSS selector for the target element
 * @param {number} props.padding Padding around the target element (default: 8)
 * @param {number} props.borderRadius Border radius of the cutout (default: 8)
 * @param {boolean} props.pulse Whether to show pulse animation (default: true)
 * @param {boolean} props.scrollIntoView Whether to scroll target into view (default: true)
 * @param {Function} props.onTargetNotFound Callback when target element is not found
 * @param {React.ReactNode} props.children Content to render (usually Tooltip)
 */
const Spotlight = ({
	targetSelector,
	padding = 8,
	borderRadius = 8,
	pulse = true,
	scrollIntoView = true,
	onTargetNotFound,
	children,
}) => {
	const [targetRect, setTargetRect] = useState(null);
	const [isVisible, setIsVisible] = useState(false);
	const resizeObserverRef = useRef(null);
	const targetElementRef = useRef(null);

	/**
	 * Get the bounding rect of the target element
	 */
	const updateTargetRect = useCallback(() => {
		if (!targetSelector) {
			setTargetRect(null);
			setIsVisible(false);
			return;
		}

		const element = document.querySelector(targetSelector);

		if (!element) {
			setTargetRect(null);
			setIsVisible(false);
			if (onTargetNotFound) {
				onTargetNotFound();
			}
			return;
		}

		targetElementRef.current = element;

		// Scroll to top of page first for better context, then ensure element is visible
		if (scrollIntoView) {
			window.scrollTo({ top: 0, behavior: 'smooth' });
		}

		// Wait a bit for scroll to complete before getting rect
		const updateRect = () => {
			const rect = element.getBoundingClientRect();
			setTargetRect({
				top: rect.top - padding,
				left: rect.left - padding,
				width: rect.width + padding * 2,
				height: rect.height + padding * 2,
			});
			setIsVisible(true);
		};

		// If scrolling, wait for it to complete
		if (scrollIntoView) {
			setTimeout(updateRect, 300);
		} else {
			updateRect();
		}
	}, [targetSelector, padding, scrollIntoView, onTargetNotFound]);

	/**
	 * Set up resize observer and event listeners
	 */
	useEffect(() => {
		updateTargetRect();

		// Handle window resize
		const handleResize = () => {
			updateTargetRect();
		};

		// Handle scroll
		const handleScroll = () => {
			if (targetElementRef.current) {
				const rect = targetElementRef.current.getBoundingClientRect();
				setTargetRect({
					top: rect.top - padding,
					left: rect.left - padding,
					width: rect.width + padding * 2,
					height: rect.height + padding * 2,
				});
			}
		};

		window.addEventListener('resize', handleResize);
		window.addEventListener('scroll', handleScroll, true);

		// Set up ResizeObserver for target element changes
		if (typeof ResizeObserver !== 'undefined') {
			resizeObserverRef.current = new ResizeObserver(() => {
				updateTargetRect();
			});

			const element = document.querySelector(targetSelector);
			if (element) {
				resizeObserverRef.current.observe(element);
			}
		}

		return () => {
			window.removeEventListener('resize', handleResize);
			window.removeEventListener('scroll', handleScroll, true);

			if (resizeObserverRef.current) {
				resizeObserverRef.current.disconnect();
			}
		};
	}, [targetSelector, padding, updateTargetRect]);

	// Don't render if no target rect
	if (!targetRect || !isVisible) {
		return null;
	}

	const overlayContent = (
		<div className="ppq-spotlight">
			{/* SVG mask overlay */}
			<svg
				className="ppq-spotlight__overlay"
				width="100%"
				height="100%"
				style={{
					position: 'fixed',
					top: 0,
					left: 0,
					width: '100vw',
					height: '100vh',
					pointerEvents: 'none',
					zIndex: 99998,
				}}
			>
				<defs>
					<mask id="ppq-spotlight-mask">
						{/* White background = visible overlay */}
						<rect x="0" y="0" width="100%" height="100%" fill="white" />
						{/* Black cutout = transparent area */}
						<rect
							x={targetRect.left}
							y={targetRect.top}
							width={targetRect.width}
							height={targetRect.height}
							rx={borderRadius}
							ry={borderRadius}
							fill="black"
						/>
					</mask>
				</defs>
				{/* Semi-transparent overlay with mask */}
				<rect
					x="0"
					y="0"
					width="100%"
					height="100%"
					fill="rgba(0, 0, 0, 0.7)"
					mask="url(#ppq-spotlight-mask)"
				/>
			</svg>

			{/* Highlight border */}
			<div
				className={`ppq-spotlight__highlight ${pulse ? 'ppq-spotlight__highlight--pulse' : ''}`}
				style={{
					position: 'fixed',
					top: targetRect.top,
					left: targetRect.left,
					width: targetRect.width,
					height: targetRect.height,
					borderRadius: borderRadius,
					zIndex: 99999,
					pointerEvents: 'none',
				}}
			/>

			{/* Children (Tooltip) with target rect context */}
			{children && (
				<div className="ppq-spotlight__content" style={{ zIndex: 100000 }}>
					{typeof children === 'function' ? children(targetRect) : children}
				</div>
			)}
		</div>
	);

	// Render as portal to body
	return createPortal(overlayContent, document.body);
};

export default Spotlight;
