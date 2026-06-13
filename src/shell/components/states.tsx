/**
 * Built-in shell states.
 *
 * Logged-out, zero-screens, not-found, screen-unavailable, loading skeleton,
 * and the locked (upsell) card. FR-006 and FR-008.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { sprintf, __ } from '@wordpress/i18n';
import Icon from './Icon';
import type { ScreenManifestEntry, NavigateFn } from '../types';

/**
 * Logged-out notice with a login link back to the dashboard.
 *
 * @param {{ loginUrl: string }} props Props.
 * @return {JSX.Element} Notice.
 */
export function LoggedOut( { loginUrl }: { loginUrl: string } ) {
	return (
		<div className="ppq-login-notice ppq-shell-state" role="status">
			<Icon name="lock" size={ 32 } />
			<p>{ __( 'Please log in to view your dashboard.', 'pressprimer-quiz' ) }</p>
			{ loginUrl && (
				<a className="ppq-shell-btn ppq-shell-btn--primary" href={ loginUrl }>
					{ __( 'Log In to Continue', 'pressprimer-quiz' ) }
				</a>
			) }
		</div>
	);
}

/**
 * Friendly empty state when the user has no permitted screens.
 *
 * @return {JSX.Element} Empty state.
 */
export function ZeroScreens() {
	return (
		<div className="ppq-shell-state" role="status">
			<p>{ __( 'There is nothing here yet.', 'pressprimer-quiz' ) }</p>
			<a className="ppq-shell-btn" href="/">
				{ __( 'Back to site', 'pressprimer-quiz' ) }
			</a>
		</div>
	);
}

/**
 * Not-found state for an unknown or inaccessible screen id.
 *
 * @param {{ navigate: NavigateFn }} props Props.
 * @return {JSX.Element} Not-found state.
 */
export function NotFound( { navigate }: { navigate: NavigateFn } ) {
	return (
		<div className="ppq-shell-state" role="status">
			<p>{ __( 'That section could not be found.', 'pressprimer-quiz' ) }</p>
			<button
				type="button"
				className="ppq-shell-btn"
				onClick={ () => navigate( 'home' ) }
			>
				{ __( 'Go to Home', 'pressprimer-quiz' ) }
			</button>
		</div>
	);
}

/**
 * State for a manifest screen whose component has not registered.
 *
 * @return {JSX.Element} Unavailable state.
 */
export function ScreenUnavailable() {
	return (
		<div className="ppq-shell-state" role="status">
			<p>
				{ __(
					'This section is not available right now.',
					'pressprimer-quiz'
				) }
			</p>
		</div>
	);
}

/**
 * Loading skeleton while a screen chunk loads.
 *
 * @return {JSX.Element} Skeleton.
 */
export function Skeleton() {
	return (
		<div className="ppq-shell-skeleton" aria-hidden="true">
			<span className="ppq-shell-skeleton-bar" />
			<span className="ppq-shell-skeleton-bar" />
			<span className="ppq-shell-skeleton-bar ppq-shell-skeleton-bar--short" />
		</div>
	);
}

/**
 * Locked upsell card shown when a locked nav entry is opened.
 *
 * The standard locked-card treatment: copy comes from the upgrade-page content
 * source (decision 005), surfaced in the boot manifest. Links to the Upgrade
 * page. FR-008.
 *
 * @param {{ entry: ScreenManifestEntry }} props Props.
 * @return {JSX.Element} Locked card.
 */
export function LockedCard( { entry }: { entry: ScreenManifestEntry } ) {
	const tierName = entry.tierName || '';
	const highlights = entry.tierHighlights || [];

	return (
		<div className="ppq-shell-locked" role="status">
			<Icon name="lock" size={ 32 } />
			<h2>{ entry.label }</h2>

			{ tierName && (
				<p className="ppq-shell-locked-tier">
					{ sprintf(
						/* translators: %s: premium tier name. */
						__( 'Available in PressPrimer Quiz %s', 'pressprimer-quiz' ),
						tierName
					) }
				</p>
			) }

			{ entry.tierDescription && <p>{ entry.tierDescription }</p> }

			{ highlights.length > 0 && (
				<ul className="ppq-shell-locked-highlights">
					{ highlights.map( ( highlight, index ) => (
						<li key={ index }>{ highlight }</li>
					) ) }
				</ul>
			) }

			<div className="ppq-shell-locked-actions">
				{ entry.upgradeUrl && (
					<a
						className="ppq-shell-btn ppq-shell-btn--primary"
						href={ entry.upgradeUrl }
					>
						{ __( 'See upgrade options', 'pressprimer-quiz' ) }
					</a>
				) }
				{ entry.tierUrl && (
					<a
						className="ppq-shell-link"
						href={ entry.tierUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ tierName
							? sprintf(
									/* translators: %s: tier name. */
									__( 'Learn about %s', 'pressprimer-quiz' ),
									tierName
							  )
							: __( 'Learn more', 'pressprimer-quiz' ) }
					</a>
				) }
			</div>
		</div>
	);
}

/**
 * State for a screen whose data request returned 403 (access revoked).
 *
 * @return {JSX.Element} Forbidden state.
 */
export function ScreenForbidden() {
	return (
		<div className="ppq-shell-state" role="status">
			<p>
				{ __(
					'You no longer have access to this section.',
					'pressprimer-quiz'
				) }
			</p>
		</div>
	);
}
