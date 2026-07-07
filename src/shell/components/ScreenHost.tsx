/**
 * Screen host.
 *
 * Resolves the active route to content: not-found for unknown/inaccessible
 * ids, the locked card for upsell entries, the unavailable state for a screen
 * whose component never registered, or the registered component wrapped in a
 * Suspense skeleton and a per-screen error boundary.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { Suspense, createElement } from '@wordpress/element';
import { getScreen } from '../registry';
import ErrorBoundary from './ErrorBoundary';
import {
	NotFound,
	ScreenUnavailable,
	Skeleton,
	LockedCard,
} from './states';
import type { ScreenManifestEntry, ScreenProps } from '../types';

interface ScreenHostProps {
	activeId: string;
	manifest: ScreenManifestEntry[];
	screenProps: ScreenProps;
}

/**
 * Render the content for the active screen.
 *
 * @param {ScreenHostProps} props Props.
 * @return {unknown} Screen content.
 */
export default function ScreenHost( {
	activeId,
	manifest,
	screenProps,
}: ScreenHostProps ) {
	const entry = manifest.find( ( screen ) => screen.id === activeId );

	if ( ! entry ) {
		return <NotFound navigate={ screenProps.navigate } />;
	}

	if ( entry.locked ) {
		return <LockedCard entry={ entry } />;
	}

	const Component = getScreen( activeId );

	if ( ! Component ) {
		return <ScreenUnavailable />;
	}

	return (
		<ErrorBoundary key={ activeId }>
			<Suspense fallback={ <Skeleton /> }>
				{ createElement( Component, screenProps ) }
			</Suspense>
		</ErrorBoundary>
	);
}
