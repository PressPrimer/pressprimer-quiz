/**
 * Onboarding Component
 *
 * The tour engine: renders the current step (a modal, or a spotlight on its
 * own screen), advances a step when its real action happens (an editor
 * event), and steps aside while the screen's own modal is open.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import useOnboarding from '../hooks/useOnboarding';
import { isOnCorrectPage, STEP_TYPE } from '../tourSteps';
import WelcomeModal from './WelcomeModal';
import CompletionModal from './CompletionModal';
import SpotlightTooltip from './SpotlightTooltip';

/**
 * The selector polling interval and limit (ms)
 */
const RESOLVE_INTERVAL = 200;
const RESOLVE_LIMIT = 4000;

/**
 * How long the tour stays hidden waiting for a reload after an advance (ms)
 */
const RELOAD_GRACE = 4000;

/**
 * Find the first selector, of a comma-separated list, that matches
 *
 * @param {string} selector Selector list.
 * @return {string|null} Matching selector or null.
 */
const findValidSelector = (selector) => {
	if (!selector) return null;

	const selectors = selector.split(',').map((s) => s.trim());

	for (const sel of selectors) {
		if (document.querySelector(sel)) {
			return sel;
		}
	}

	return null;
};

/**
 * Whether one of the screen's own Ant Design modals is open
 *
 * Closed modals keep their wrapper in the DOM, hidden.
 *
 * @return {boolean} True when a modal is showing.
 */
const isScreenModalOpen = () =>
	[...document.querySelectorAll('.ant-modal-wrap')].some((wrap) => window.getComputedStyle(wrap).display !== 'none');

/**
 * Onboarding Component
 *
 * @param {Object} props Component props
 * @param {Object} props.initialData Initial data from PHP
 */
const Onboarding = ({ initialData = {} }) => {
	const {
		isActive,
		isLoading,
		step,
		stepNumber,
		totalSteps,
		prevStepId,
		run,
		startTour,
		nextStep,
		prevStep,
		advance,
		recordRun,
		skipTour,
		completeTour,
		closeTour,
	} = useOnboarding(initialData);

	// undefined = still resolving (render nothing yet),
	// null      = gave up (render the floating fallback),
	// string    = found.
	const [resolvedSelector, setResolvedSelector] = useState(undefined);

	// The screen's own modal is open (a template preview, the question picker).
	const [screenModalOpen, setScreenModalOpen] = useState(false);

	// After an advance on a screen that reloads, the tour stays hidden until
	// the reload, so the next step renders once, after it.
	const [awaitingReload, setAwaitingReload] = useState(false);

	const isSpotlight = !!step && step.type === STEP_TYPE.SPOTLIGHT;
	const onStepScreen = isOnCorrectPage(step);

	/**
	 * Step aside while the screen's own modal is open
	 */
	useEffect(() => {
		if (!isActive || !isSpotlight || !onStepScreen) {
			setScreenModalOpen(false);
			return undefined;
		}

		const check = () => setScreenModalOpen(isScreenModalOpen());
		const interval = setInterval(check, 300);
		check();

		return () => clearInterval(interval);
	}, [isActive, isSpotlight, onStepScreen]);

	/**
	 * Advance when the step's real action happens
	 *
	 * The editors fire their events on document.
	 */
	useEffect(() => {
		if (!isActive || !step || !step.advanceOn) {
			return undefined;
		}

		const { event, when, record } = step.advanceOn;

		const onEvent = (e) => {
			const detail = e.detail || {};
			const changes = record ? record(detail, run) : null;
			const merged = changes ? { ...run, ...changes } : run;

			if (when && !when(detail, merged)) {
				if (changes) {
					recordRun(changes);
				}
				return;
			}

			if (step.reloadsAfterAdvance) {
				setAwaitingReload(true);
				// If the reload never comes, show the tour again.
				setTimeout(() => setAwaitingReload(false), RELOAD_GRACE);
			}

			advance(changes);
		};

		document.addEventListener(event, onEvent);
		return () => document.removeEventListener(event, onEvent);
	}, [isActive, step, run, advance, recordRun]);

	/**
	 * Resolve the spotlight target
	 *
	 * Polls, because the React screens mount after the page loads: one early
	 * check would race them and fall back to the whole container.
	 */
	useEffect(() => {
		setResolvedSelector(undefined);

		if (!isActive || !isSpotlight || !onStepScreen) {
			return undefined;
		}

		const startedAt = Date.now();
		let poll = null;

		const stop = () => {
			if (poll) {
				clearInterval(poll);
				poll = null;
			}
		};

		const tryResolve = () => {
			const found = findValidSelector(step.selector);

			if (found) {
				setResolvedSelector(found);
				stop();
				return;
			}

			// The target never appeared: frame the fallback container, or
			// float the tooltip if even that is missing.
			if (Date.now() - startedAt > RESOLVE_LIMIT) {
				setResolvedSelector(step.fallbackSelector && document.querySelector(step.fallbackSelector) ? step.fallbackSelector : null);
				stop();
			}
		};

		poll = setInterval(tryResolve, RESOLVE_INTERVAL);
		tryResolve();

		return stop;
	}, [isActive, step, isSpotlight, onStepScreen]);

	if (!isActive || !step || isLoading || awaitingReload) {
		return null;
	}

	// Welcome Modal
	if (step.id === 'welcome') {
		return (
			<WelcomeModal
				title={step.title}
				content={step.content}
				onStart={() => startTour()}
				onSkip={skipTour}
			/>
		);
	}

	// Completion Modal
	if (step.id === 'complete') {
		return (
			<CompletionModal
				title={step.title}
				content={step.content}
				onComplete={completeTour}
				urls={initialData.urls || {}}
			/>
		);
	}

	if (!isSpotlight) {
		return null;
	}

	// A mid-tour step renders only on its own screen. Elsewhere the tour
	// stays out of the way until the admin returns.
	if (!onStepScreen || screenModalOpen || resolvedSelector === undefined) {
		return null;
	}

	// Users advance by doing the real thing, not by clicking past it:
	// otherwise the next step has nothing to point at.
	const nextDisabled = !!step.advanceOn;
	const nextDisabledReason = nextDisabled ? step.nextDisabledReason || null : null;

	// Back never returns to the welcome modal (starting again starts a new run).
	const onPrev = prevStepId && prevStepId !== 'welcome' ? prevStep : null;

	// The target never appeared: show the content in a floating tooltip
	if (!resolvedSelector) {
		return (
			<div className="ppq-onboarding-floating">
				<div className="ppq-onboarding-floating__content">
					<button
						type="button"
						className="ppq-onboarding-floating__close"
						onClick={closeTour}
						aria-label={__('Close', 'pressprimer-quiz')}
					>
						&times;
					</button>
					<h3 className="ppq-onboarding-floating__title">{step.title}</h3>
					<div className="ppq-onboarding-floating__text">{step.content}</div>
					{nextDisabledReason && (
						<p className="ppq-onboarding-floating__hint">{nextDisabledReason}</p>
					)}
					<div className="ppq-onboarding-floating__nav">
						{onPrev ? (
							<button
								type="button"
								className="ppq-onboarding-floating__btn ppq-onboarding-floating__btn--back"
								onClick={onPrev}
							>
								{__('Back', 'pressprimer-quiz')}
							</button>
						) : (
							<span />
						)}
						<span className="ppq-onboarding-floating__step">
							{stepNumber} / {totalSteps}
						</span>
						<button
							type="button"
							className="ppq-onboarding-floating__btn ppq-onboarding-floating__btn--next"
							onClick={nextStep}
							disabled={nextDisabled}
						>
							{stepNumber === totalSteps ? __('Finish', 'pressprimer-quiz') : __('Next', 'pressprimer-quiz')}
						</button>
					</div>
				</div>
			</div>
		);
	}

	return (
		<SpotlightTooltip
			selector={resolvedSelector}
			title={step.title}
			content={step.content}
			position={step.position || 'bottom'}
			fitContent={!!step.fitContent}
			currentStep={stepNumber}
			totalSteps={totalSteps}
			nextDisabled={nextDisabled}
			nextDisabledReason={nextDisabledReason}
			onPrev={onPrev}
			onNext={nextStep}
			onSkip={closeTour}
			onClose={closeTour}
		/>
	);
};

export default Onboarding;
