/**
 * Spotlight Component
 *
 * SVG mask overlay with a cutout around the target element. The dim layer
 * is visual only, so the admin keeps working in the real screen. A render
 * prop passes the target's rect to the Tooltip.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect, useCallback, createPortal } from '@wordpress/element';

/**
 * The visible-content rect of an element
 *
 * The union of its children's boxes rather than its own border box: framing
 * a padded container leaves the highlight far from what the user sees.
 *
 * @param {Element} element Target element.
 * @return {Object} Rect (the element's own when it has no visible children).
 */
const contentRect = (element) => {
	const children = [...element.children].filter((child) => child.getClientRects().length > 0);

	if (!children.length) {
		return element.getBoundingClientRect();
	}

	let top = Infinity;
	let left = Infinity;
	let bottom = -Infinity;
	let right = -Infinity;

	for (const child of children) {
		const rect = child.getBoundingClientRect();
		top = Math.min(top, rect.top);
		left = Math.min(left, rect.left);
		bottom = Math.max(bottom, rect.bottom);
		right = Math.max(right, rect.right);
	}

	return { top, left, bottom, right, width: right - left, height: bottom - top };
};

/**
 * Spotlight Component
 *
 * @param {Object}   props            Component props
 * @param {string}   props.selector   CSS selector for the target element
 * @param {number}   props.padding    Padding around the target element (default: 8)
 * @param {boolean}  props.fitContent Frame the children's union instead of the padded box
 * @param {Function} props.children   Render prop receiving the target rect
 */
const Spotlight = ({ selector, padding = 8, fitContent = false, children }) => {
	const [targetRect, setTargetRect] = useState(null);

	/**
	 * Measure the target
	 *
	 * Keeps the previous rect when nothing moved, so polling doesn't churn.
	 */
	const updatePosition = useCallback(() => {
		if (!selector) {
			return;
		}

		const element = document.querySelector(selector);
		if (!element) {
			setTargetRect(null);
			return;
		}

		const rect = fitContent ? contentRect(element) : element.getBoundingClientRect();

		setTargetRect((prev) => {
			if (prev && prev.top === rect.top && prev.left === rect.left && prev.width === rect.width && prev.height === rect.height) {
				return prev;
			}

			return {
				top: rect.top,
				left: rect.left,
				width: rect.width,
				height: rect.height,
				bottom: rect.bottom,
				right: rect.right,
			};
		});
	}, [selector, fitContent]);

	/**
	 * Track the target's position
	 */
	useEffect(() => {
		// Bring the target into view: it may sit below the fold.
		const element = selector ? document.querySelector(selector) : null;
		if (element) {
			element.scrollIntoView({ block: 'start', behavior: 'smooth' });
		}

		const timer = setTimeout(updatePosition, 300);

		// Poll while the step is open: late-loading content moves targets
		// without resizing them, which no listener below can see.
		const interval = setInterval(updatePosition, 300);

		window.addEventListener('resize', updatePosition);
		window.addEventListener('scroll', updatePosition);

		let observer;
		if (element && typeof window.ResizeObserver !== 'undefined') {
			observer = new window.ResizeObserver(updatePosition);
			observer.observe(element);
		}

		return () => {
			clearTimeout(timer);
			clearInterval(interval);
			window.removeEventListener('resize', updatePosition);
			window.removeEventListener('scroll', updatePosition);
			if (observer) {
				observer.disconnect();
			}
		};
	}, [selector, updatePosition]);

	if (!targetRect) {
		return typeof children === 'function' ? children(null) : null;
	}

	const cutout = {
		x: targetRect.left - padding,
		y: targetRect.top - padding,
		width: targetRect.width + padding * 2,
		height: targetRect.height + padding * 2,
		rx: 8,
	};

	// The frame stays inside the admin content area: never up into the
	// fixed admin bar, never left into the admin menu.
	const adminBar = document.getElementById('wpadminbar');
	const adminMenu = document.getElementById('adminmenuwrap');
	const minTop = adminBar ? adminBar.getBoundingClientRect().bottom + 4 : 4;
	const minLeft = adminMenu ? adminMenu.getBoundingClientRect().right + 4 : 4;

	if (cutout.y < minTop) {
		cutout.height -= minTop - cutout.y;
		cutout.y = minTop;
	}

	if (cutout.x < minLeft) {
		cutout.width -= minLeft - cutout.x;
		cutout.x = minLeft;
	}

	const overlay = createPortal(
		<>
			<svg
				className="ppq-spotlight__overlay"
				style={{
					position: 'fixed',
					inset: 0,
					width: '100%',
					height: '100%',
					zIndex: 99998,
					pointerEvents: 'none',
				}}
			>
				<defs>
					<mask id="ppq-spotlight-mask">
						<rect x="0" y="0" width="100%" height="100%" fill="white" />
						<rect
							x={cutout.x}
							y={cutout.y}
							width={cutout.width}
							height={cutout.height}
							rx={cutout.rx}
							fill="black"
						/>
					</mask>
				</defs>
				{/* Visual only: an SVG mask cuts a visual hole, not a
				    hit-testing one, so with pointer events on the whole page
				    (cutout included) would be unclickable. */}
				<rect
					x="0"
					y="0"
					width="100%"
					height="100%"
					fill="rgba(0, 0, 0, 0.5)"
					mask="url(#ppq-spotlight-mask)"
					style={{ pointerEvents: 'none' }}
				/>
			</svg>

			<div
				className="ppq-spotlight__highlight ppq-spotlight__highlight--pulse"
				style={{
					position: 'fixed',
					top: cutout.y,
					left: cutout.x,
					width: cutout.width,
					height: cutout.height,
					borderRadius: cutout.rx,
					zIndex: 99999,
					pointerEvents: 'none',
				}}
			/>
		</>,
		document.body
	);

	return (
		<>
			{overlay}
			{typeof children === 'function' ? children(targetRect) : null}
		</>
	);
};

export default Spotlight;
