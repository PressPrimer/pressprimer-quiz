/**
 * Welcome Modal Component
 *
 * Initial welcome modal for the onboarding tour.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Checkbox } from 'antd';

/**
 * WelcomeModal Component
 *
 * @param {Object} props Component props
 * @param {string} props.title Modal title
 * @param {string} props.content Modal content
 * @param {Function} props.onStart Start tour callback
 * @param {Function} props.onSkip Skip tour callback
 */
const WelcomeModal = ({ title, content, onStart, onSkip }) => {
	// Get plugin URL for logo
	const pluginUrl = window.ppqOnboardingData?.pluginUrl || window.ppqDashboardData?.pluginUrl || '';
	const [dontShowAgain, setDontShowAgain] = useState(false);
	const modalRef = useRef(null);
	const startButtonRef = useRef(null);

	// Focus management
	useEffect(() => {
		// Focus the start button when modal opens
		if (startButtonRef.current) {
			startButtonRef.current.focus();
		}

		// Prevent body scroll
		document.body.style.overflow = 'hidden';

		return () => {
			document.body.style.overflow = '';
		};
	}, []);

	// Handle escape key
	useEffect(() => {
		const handleKeyDown = (e) => {
			if (e.key === 'Escape') {
				onSkip(dontShowAgain);
			}
		};

		document.addEventListener('keydown', handleKeyDown);
		return () => document.removeEventListener('keydown', handleKeyDown);
	}, [dontShowAgain, onSkip]);

	const handleSkip = () => {
		onSkip(dontShowAgain);
	};

	return (
		<div className="ppq-onboarding-overlay" role="dialog" aria-modal="true" aria-labelledby="ppq-welcome-title">
			<div ref={modalRef} className="ppq-onboarding-modal ppq-onboarding-modal--welcome">
				<div className="ppq-onboarding-modal__logo">
					<img
						src={`${pluginUrl}assets/images/PressPrimer-Logo.svg`}
						alt="PressPrimer"
						className="ppq-onboarding-modal__logo-img"
					/>
				</div>

				<h1 id="ppq-welcome-title" className="ppq-onboarding-modal__title">
					{title}
				</h1>

				<p className="ppq-onboarding-modal__content">
					{content}
				</p>

				<div className="ppq-onboarding-modal__actions">
					<Button
						ref={startButtonRef}
						type="primary"
						size="large"
						onClick={onStart}
						className="ppq-onboarding-modal__start-btn"
					>
						{__("Let's Go!", 'pressprimer-quiz')}
					</Button>

					<Button
						type="link"
						onClick={handleSkip}
						className="ppq-onboarding-modal__skip-btn"
					>
						{__('Skip Tour', 'pressprimer-quiz')}
					</Button>

					<div className="ppq-onboarding-modal__checkbox">
						<Checkbox
							checked={dontShowAgain}
							onChange={(e) => setDontShowAgain(e.target.checked)}
						>
							{__("Don't show this again", 'pressprimer-quiz')}
						</Checkbox>
					</div>
				</div>
			</div>
		</div>
	);
};

export default WelcomeModal;
