/**
 * Shell application.
 *
 * Owns routing, the nav chrome, built-in states, and accessibility basics
 * (document.title, aria-current, focus-to-heading on route change). Screens
 * render inside the main region. FR-003, FR-006, FR-009, TR-004, TR-006.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useHashRoute, navigate } from './router';
import { subscribe } from './registry';
import Sidebar from './components/Sidebar';
import ScreenHost from './components/ScreenHost';
import Icon from './components/Icon';
import { LoggedOut, ZeroScreens } from './components/states';
import type { BootData, ScreenProps } from './types';

/**
 * Re-render the app whenever the screen registry changes (late registrations).
 */
function useRegistryVersion(): void {
	const [ , setVersion ] = useState( 0 );
	useEffect(
		() => subscribe( () => setVersion( ( value ) => value + 1 ) ),
		[]
	);
}

interface ShellAppProps {
	boot: BootData;
}

/**
 * The shell root component.
 *
 * @param {ShellAppProps} props Props.
 * @return {JSX.Element} The shell.
 */
export default function ShellApp( { boot }: ShellAppProps ) {
	useRegistryVersion();

	const route = useHashRoute();
	const [ menuOpen, setMenuOpen ] = useState( false );
	const [ announce, setAnnounce ] = useState( '' );
	const headingRef = useRef< HTMLHeadingElement | null >( null );

	const manifest = Array.isArray( boot.screens ) ? boot.screens : [];
	const ids = manifest.map( ( screen ) => screen.id );
	const defaultId = ids.indexOf( 'home' ) !== -1 ? 'home' : ids[ 0 ] || '';
	const activeId = route.screenId || defaultId;

	const activeEntry = manifest.find( ( screen ) => screen.id === activeId );
	const activeLabel = activeEntry
		? activeEntry.label
		: __( 'Dashboard', 'pressprimer-quiz' );

	// Title, focus, and live-region announcement on route change.
	useEffect( () => {
		document.title = activeLabel + ' – ' + boot.branding.productName;
		if ( headingRef.current ) {
			headingRef.current.focus();
		}
		setAnnounce( activeLabel );
		setMenuOpen( false );
	}, [ activeId, activeLabel, boot.branding.productName ] );

	// Escape closes the mobile navigation menu.
	useEffect( () => {
		if ( ! menuOpen ) {
			return undefined;
		}
		const onKeyDown = ( event: KeyboardEvent ) => {
			if ( 'Escape' === event.key ) {
				setMenuOpen( false );
			}
		};
		document.addEventListener( 'keydown', onKeyDown );
		return () => document.removeEventListener( 'keydown', onKeyDown );
	}, [ menuOpen ] );

	// Logged out: login prompt whose redirect_to is the current URL including the
	// hash route, so login returns the visitor to the exact screen they wanted.
	if ( ! boot.user || boot.user.id === 0 ) {
		const sep = boot.loginUrl.indexOf( '?' ) !== -1 ? '&' : '?';
		const loginHref =
			boot.loginUrl +
			sep +
			'redirect_to=' +
			encodeURIComponent( window.location.href );

		return (
			<div className="ppq-shell-app">
				<LoggedOut loginUrl={ loginHref } />
			</div>
		);
	}

	// No permitted screens.
	if ( manifest.length === 0 ) {
		return (
			<div className="ppq-shell-app">
				<ZeroScreens />
			</div>
		);
	}

	const screenProps: ScreenProps = {
		user: boot.user,
		restNonce: boot.restNonce,
		navigate,
		route,
	};

	return (
		<div className="ppq-shell-app">
			<div
				className="screen-reader-text"
				role="status"
				aria-live="polite"
			>
				{ announce }
			</div>

			<header className="ppq-shell-topbar">
				<button
					type="button"
					className="ppq-shell-menu-toggle"
					aria-expanded={ menuOpen }
					aria-controls="ppq-shell-nav"
					onClick={ () => setMenuOpen( ( open ) => ! open ) }
				>
					<Icon name="menu" />
					<span className="screen-reader-text">
						{ __( 'Toggle navigation', 'pressprimer-quiz' ) }
					</span>
				</button>
				<span className="ppq-shell-topbar-title">
					{ boot.branding.logoUrl ? (
						<img
							className="ppq-shell-topbar-logo"
							src={ boot.branding.logoUrl }
							alt={ boot.branding.productName }
						/>
					) : (
						boot.branding.productName
					) }
				</span>
			</header>

			<div className="ppq-shell-body">
				<Sidebar
					screens={ manifest }
					groups={ boot.groups || {} }
					branding={ boot.branding }
					activeId={ activeId }
					open={ menuOpen }
					onNavigate={ ( id ) => navigate( id ) }
				/>

				<main className="ppq-shell-main">
					<h1
						className="ppq-shell-screen-title"
						tabIndex={ -1 }
						ref={ headingRef }
					>
						{ activeLabel }
					</h1>
					<ScreenHost
						activeId={ activeId }
						manifest={ manifest }
						screenProps={ screenProps }
					/>
				</main>
			</div>
		</div>
	);
}
