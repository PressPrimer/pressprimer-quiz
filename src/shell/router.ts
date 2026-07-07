/**
 * Hash router for the shell.
 *
 * Routes are `#/screen-id` with optional screen-owned sub-paths
 * (`#/screen-id/sub/path`). No hash resolves to the default (home) screen.
 * Navigation is client-only; browser back/forward work via hashchange.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import type { ShellRoute } from './types';

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

/**
 * Navigate to a route (updates the hash; adds a history entry).
 *
 * @param {string} route A route id or path, with or without a leading '#/'.
 */
export function navigate( route: string ): void {
	const clean = String( route ).replace( /^#?\/?/, '' );
	window.location.hash = '#/' + clean;
}

/**
 * Subscribe to the current route. Re-renders on hashchange (back/forward too).
 *
 * @return {ShellRoute} The current route.
 */
export function useHashRoute(): ShellRoute {
	const [ route, setRoute ] = useState< ShellRoute >( () =>
		parseRoute( window.location.hash )
	);

	useEffect( () => {
		const onChange = () => setRoute( parseRoute( window.location.hash ) );
		window.addEventListener( 'hashchange', onChange );
		return () => window.removeEventListener( 'hashchange', onChange );
	}, [] );

	return route;
}
