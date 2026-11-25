/**
 * Hash router for the shell.
 *
 * Routes are `#/screen-id` with optional screen-owned sub-paths
 * (`#/screen-id/sub/path`). No hash resolves to the default (home) screen.
 * Navigation is client-only; browser back/forward work via hashchange.
 *
 * An optional navigation guard (one at a time, e.g. an editor with unsaved
 * changes) is consulted before navigate() moves, before back/forward or a
 * typed hash takes effect (the hash is put back when it refuses), and on
 * reload or close (the browser asks for confirmation when it refuses).
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import type { ShellRoute, NavigateOptions, NavigationGuard } from './types';

/**
 * Parse a location hash into a route.
 *
 * @param {string} hash The location hash (e.g. '#/my-results/42').
 * @return {ShellRoute} Parsed route.
 */
export function parseRoute( hash: string ): ShellRoute {
	const raw = String( hash ).replace( /^#\/?/, '' );
	const parts = raw.split( '/' ).filter( ( part ) => part !== '' );

	return {
		screenId: parts.length > 0 ? parts[ 0 ] : '',
		subPath: parts.slice( 1 ),
		raw,
	};
}

type Listener = () => void;

const listeners: Set< Listener > = new Set();
let guard: NavigationGuard | null = null;
// The route the shell is showing (accepted by the guard).
let currentRaw = '';
// A route navigate() already cleared, so its hashchange skips the guard.
let clearedRaw: string | null = null;
let installed = false;

/**
 * The address for a route: the page URL with the route's hash, or no hash
 * for the default route.
 *
 * @param {string} raw Route without the leading '#/'.
 * @return {string} URL for history.replaceState.
 */
function urlFor( raw: string ): string {
	const base = window.location.pathname + window.location.search;
	return raw ? base + '#/' + raw : base;
}

/**
 * Ask the guard whether a navigation may happen.
 *
 * @param {string} to     Route the navigation goes to.
 * @param {string} reason 'navigate', 'history' or 'unload'.
 * @return {boolean} True when there is no guard or it allows the navigation.
 */
function guardAllows( to: string, reason: 'navigate' | 'history' | 'unload' ): boolean {
	if ( ! guard ) {
		return true;
	}
	try {
		return guard( { to, from: currentRaw, reason } ) !== false;
	} catch ( error ) {
		return true;
	}
}

function notify(): void {
	listeners.forEach( ( listener ) => listener() );
}

/**
 * Watch the address once: hash changes from back/forward or a typed hash go
 * through the guard; reload and close ask the guard too.
 */
function install(): void {
	if ( installed ) {
		return;
	}
	installed = true;
	currentRaw = parseRoute( window.location.hash ).raw;

	window.addEventListener( 'hashchange', () => {
		const next = parseRoute( window.location.hash ).raw;

		if ( clearedRaw !== null && next === clearedRaw ) {
			clearedRaw = null;
		} else if ( next !== currentRaw && ! guardAllows( next, 'history' ) ) {
			// Refused: put the address back on the route still showing.
			window.history.replaceState( window.history.state, '', urlFor( currentRaw ) );
			return;
		}

		currentRaw = next;
		notify();
	} );

	window.addEventListener( 'beforeunload', ( event ) => {
		if ( ! guardAllows( currentRaw, 'unload' ) ) {
			event.preventDefault();
			// Older browsers need returnValue set to show the prompt.
			event.returnValue = '';
		}
	} );
}

/**
 * Navigate to a route.
 *
 * Adds a history entry, or with `replace` swaps the current one (no history
 * entry, no hashchange). The navigation guard is asked first unless `force`
 * is set (e.g. right after the screen saved its changes).
 *
 * @param {string}          route   A route id or path, with or without a leading '#/'.
 * @param {NavigateOptions} options Options: replace, force.
 * @return {boolean} False when the guard refused.
 */
export function navigate( route: string, options: NavigateOptions = {} ): boolean {
	install();

	const clean = String( route ).replace( /^#?\/?/, '' );

	if ( ! options.force && clean !== currentRaw && ! guardAllows( clean, 'navigate' ) ) {
		return false;
	}

	if ( options.replace ) {
		window.history.replaceState( window.history.state, '', urlFor( clean ) );
		currentRaw = clean;
		notify();
		return true;
	}

	if ( clean !== parseRoute( window.location.hash ).raw ) {
		clearedRaw = clean;
	}
	window.location.hash = '#/' + clean;
	return true;
}

/**
 * Set or clear the navigation guard.
 *
 * The guard gets `{ to, from, reason }` and returns false to stay. Reasons:
 * 'navigate' (navigate() and Sidebar clicks), 'history' (back/forward or a
 * typed hash; the hash is put back), 'unload' (reload or close; returning
 * false makes the browser ask, so don't open a dialog for it).
 *
 * @param {NavigationGuard|null} fn Guard, or null to clear it.
 */
export function setNavigationGuard( fn: NavigationGuard | null ): void {
	install();
	guard = typeof fn === 'function' ? fn : null;
}

/**
 * Subscribe to the current route. Re-renders on hashchange (back/forward too)
 * and on navigate( route, { replace: true } ).
 *
 * @return {ShellRoute} The current route.
 */
export function useHashRoute(): ShellRoute {
	const [ route, setRoute ] = useState< ShellRoute >( () =>
		parseRoute( window.location.hash )
	);

	useEffect( () => {
		install();
		const onChange = () => setRoute( parseRoute( window.location.hash ) );
		listeners.add( onChange );
		return () => {
			listeners.delete( onChange );
		};
	}, [] );

	return route;
}
