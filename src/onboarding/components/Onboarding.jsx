/**
 * Onboarding Component
 *
 * Main component that orchestrates the interactive onboarding tour.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useEffect, useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import useOnboarding from '../hooks/useOnboarding';
import { tourSteps, getStep, getStepUrl, isOnCorrectPage, STEP_TYPE, getTotalSteps } from '../tourSteps';
import WelcomeModal from './WelcomeModal';
import CompletionModal from './CompletionModal';
import SpotlightTooltip from './SpotlightTooltip';

/**
 * Find a valid selector for the current step
 * Returns the first selector that matches an element
 */
const findValidSelector = (step) => {
	if (!step || !step.selector) return null;

	// Try primary selector(s) - can be comma-separated
	const selectors = step.selector.split(',').map((s) => s.trim());

	for (const selector of selectors) {
		if (document.querySelector(selector)) {
			return selector;
		}
	}

	// Try fallback selector
	if (step.fallbackSelector && document.querySelector(step.fallbackSelector)) {
		return step.fallbackSelector;
	}

	return null;
};

/**
 * Onboarding Component
 *
 * @param {Object} props Component props
 * @param {Object} props.initialData Initial data from PHP
 */
const Onboarding = ({ initialData = {} }) => {
	const {
		isLoading,
		isActive,
		currentStep,
		totalSteps,
		startTour,
		nextStep,
		prevStep,
		skipTour,
		completeTour,
		closeTour,
	} = useOnboarding(initialData);

	const [activeSelector, setActiveSelector] = useState(null);

	const urls = initialData.urls || {};

	// Get current step data
	const step = getStep(currentStep);

	// Update active selector when step changes
	useEffect(() => {
		if (step && step.type === STEP_TYPE.SPOTLIGHT) {
			// Small delay to ensure page has rendered
			const timer = setTimeout(() => {
				const selector = findValidSelector(step);
				setActiveSelector(selector);
			}, 100);

			return () => clearTimeout(timer);
		} else {
			setActiveSelector(null);
		}
	}, [step]);

	/**
	 * Handle next step with navigation
	 */
	const handleNext = useCallback(() => {
		const nextStepNumber = currentStep + 1;
		const nextStepData = getStep(nextStepNumber);

		if (nextStepNumber > getTotalSteps()) {
			completeTour();
			return;
		}

		// Check if we need to navigate to a different page
		if (nextStepData && nextStepData.pageUrl && !isOnCorrectPage(nextStepNumber)) {
			// Navigate to the page - the step will continue after page load
			nextStep(nextStepData.pageUrl);
		} else {
			nextStep();
		}
	}, [currentStep, nextStep, completeTour]);

	/**
	 * Handle previous step with navigation
	 */
	const handlePrev = useCallback(() => {
		const prevStepNumber = currentStep - 1;
		const prevStepData = getStep(prevStepNumber);

		if (prevStepNumber < 1) {
			return;
		}

		// Check if we need to navigate to a different page
		if (prevStepData && prevStepData.pageUrl && !isOnCorrectPage(prevStepNumber)) {
			prevStep(prevStepData.pageUrl);
		} else {
			prevStep();
		}
	}, [currentStep, prevStep]);

	/**
	 * Handle skip
	 */
	const handleSkip = useCallback((permanent = false) => {
		skipTour(permanent);
	}, [skipTour]);

	/**
	 * Handle close (just dismiss without permanent skip)
	 */
	const handleClose = useCallback(() => {
		closeTour();
	}, [closeTour]);

	// Don't render if not active
	if (!isActive) {
		return null;
	}

	// Don't render if no step data
	if (!step) {
		return null;
	}

	// Render Welcome Modal (Step 1)
	if (step.type === STEP_TYPE.MODAL && step.id === 'welcome') {
		return (
			<WelcomeModal
				title={step.title}
				content={step.content}
				onStart={startTour}
				onSkip={handleSkip}
			/>
		);
	}

	// Render Completion Modal (Final Step)
	if (step.type === STEP_TYPE.MODAL && step.id === 'complete') {
		return (
			<CompletionModal
				title={step.title}
				content={step.content}
				onComplete={completeTour}
				urls={urls}
			/>
		);
	}

	// Render Spotlight for interactive steps
	if (step.type === STEP_TYPE.SPOTLIGHT) {
		// Check if we're on the correct page for this step
		if (!isOnCorrectPage(currentStep)) {
			// We need to navigate - show a loading state or auto-navigate
			const targetUrl = getStepUrl(currentStep);
			if (targetUrl) {
				window.location.href = targetUrl;
			}
			return null;
		}

		// If no valid selector found, show the content in a floating tooltip
		if (!activeSelector) {
			return (
				<div className="ppq-onboarding-floating">
					<div className="ppq-onboarding-floating__content">
						<button
							type="button"
							className="ppq-onboarding-floating__close"
							onClick={handleClose}
							aria-label={__('Close', 'pressprimer-quiz')}
						>
							&times;
						</button>
						<h3 className="ppq-onboarding-floating__title">{step.title}</h3>
						<p className="ppq-onboarding-floating__text">{step.content}</p>
						<div className="ppq-onboarding-floating__nav">
							{currentStep > 1 && (
								<button
									type="button"
									className="ppq-onboarding-floating__btn ppq-onboarding-floating__btn--back"
									onClick={handlePrev}
								>
									{__('Back', 'pressprimer-quiz')}
								</button>
							)}
							<span className="ppq-onboarding-floating__step">
								{currentStep} / {totalSteps}
							</span>
							<button
								type="button"
								className="ppq-onboarding-floating__btn ppq-onboarding-floating__btn--next"
								onClick={handleNext}
							>
								{currentStep === totalSteps - 1 ? __('Finish', 'pressprimer-quiz') : __('Next', 'pressprimer-quiz')}
							</button>
						</div>
					</div>
				</div>
			);
		}

		return (
			<SpotlightTooltip
				targetSelector={activeSelector}
				title={step.title}
				content={step.content}
				position={step.position || 'bottom'}
				currentStep={currentStep}
				totalSteps={totalSteps}
				onPrev={currentStep > 1 ? handlePrev : null}
				onNext={handleNext}
				onSkip={() => handleSkip(false)}
				onClose={handleClose}
				showNavigation={true}
				nextLabel={currentStep === totalSteps - 1 ? __('Finish', 'pressprimer-quiz') : __('Next', 'pressprimer-quiz')}
				prevLabel={__('Back', 'pressprimer-quiz')}
			/>
		);
	}

	return null;
};

export default Onboarding;
