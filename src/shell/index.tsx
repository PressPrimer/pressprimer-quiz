/**
 * Front-end shell entry point.
 *
 * Exposes the PPQ.shell registration API before any screen bundle runs,
 * registers the free plugin's built-in screens, and mounts the shell into the
 * #ppq-shell container output by PressPrimer_Quiz_Shell::render().
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { render, lazy } from '@wordpress/element';
import { registerScreen, registerHomeCard } from './registry';
import ShellApp from './ShellApp';
import './style.css';
import type { BootData, ShellApi } from './types';

// Built-in screens load as their own chunks on route activation (TR-002).
const Home = lazy( () => import( /* webpackChunkName: "shell-home" */ './screens/Home' ) );
const MyResults = lazy(
	() => import( /* webpackChunkName: "shell-my-results" */ './screens/MyResults' )
);

const DEFAULT_BOOT: BootData = {
	restUrl: '',
	restNonce: '',
	user: { id: 0, name: '' },
	screens: [],
	groups: {},
	loginUrl: '',
	branding: { logoUrl: '', productName: 'PressPrimer Quiz' },
};

// Expose the registration API before any addon screen bundle executes.
const api: ShellApi = { registerScreen, registerHomeCard };
window.PPQ = window.PPQ || {};
window.PPQ.shell = api;

// The free plugin registers its own built-in screens (consumer #1).
registerScreen( 'home', Home );
registerScreen( 'my-results', MyResults );

/**
 * Mount the shell into its container.
 */
function boot(): void {
	const container = document.getElementById( 'ppq-shell' );
	if ( ! container ) {
		return;
	}

	const data: BootData = window.PPQShellData || DEFAULT_BOOT;
	render( <ShellApp boot={ data } />, container );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', boot );
} else {
	boot();
}
