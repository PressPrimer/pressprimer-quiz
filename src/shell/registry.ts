/**
 * Shell screen registry.
 *
 * Module-level store backing window.PPQ.shell. Screens may register before or
 * after the shell mounts; subscribers (the shell app) re-render on changes so a
 * late-registering addon bundle appears without a reload.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import type { ScreenComponent, HomeCardComponent } from './types';

type Listener = () => void;

const screens: Map< string, ScreenComponent > = new Map();
const homeCards: Map< string, HomeCardComponent > = new Map();
const listeners: Set< Listener > = new Set();

function notify(): void {
	listeners.forEach( ( listener ) => listener() );
}

/**
 * Register a screen component against a screen id from the boot manifest.
 *
 * @param {string}          id        Screen id.
 * @param {ScreenComponent} component Screen component.
 */
export function registerScreen( id: string, component: ScreenComponent ): void {
	if ( ! id || typeof component === 'undefined' || component === null ) {
		return;
	}
	screens.set( id, component );
	notify();
}

/**
 * Register a card component shown on the Home screen.
 *
 * @param {string}            id        Card id.
 * @param {HomeCardComponent} component Card component.
 */
export function registerHomeCard( id: string, component: HomeCardComponent ): void {
	if ( ! id || typeof component === 'undefined' || component === null ) {
		return;
	}
	homeCards.set( id, component );
	notify();
}

/**
 * Get the registered component for a screen id, if any.
 *
 * @param {string} id Screen id.
 * @return {ScreenComponent|undefined} The component, or undefined.
 */
export function getScreen( id: string ): ScreenComponent | undefined {
	return screens.get( id );
}

/**
 * Get all registered home cards in registration order.
 *
 * @return {HomeCardComponent[]} Home card components.
 */
export function getHomeCards(): HomeCardComponent[] {
	return Array.from( homeCards.values() );
}

/**
 * Subscribe to registry changes.
 *
 * @param {Listener} listener Called when a screen or card registers.
 * @return {() => void} Unsubscribe function.
 */
export function subscribe( listener: Listener ): () => void {
	listeners.add( listener );
	return () => {
		listeners.delete( listener );
	};
}
