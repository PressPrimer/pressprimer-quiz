/**
 * Per-screen error boundary.
 *
 * Catches a failed screen render so the shell chrome and other screens keep
 * working, and offers a retry. FR-006.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

interface State {
	hasError: boolean;
}

/**
 * Error boundary component.
 */
export default class ErrorBoundary extends Component< Record< string, unknown >, State > {
	constructor( props: Record< string, unknown > ) {
		super( props );
		this.state = { hasError: false };
		this.handleRetry = this.handleRetry.bind( this );
	}

	static getDerivedStateFromError(): State {
		return { hasError: true };
	}

	handleRetry(): void {
		this.setState( { hasError: false } );
	}

	render() {
		if ( this.state.hasError ) {
			return (
				<div className="ppq-shell-state ppq-shell-state--error" role="alert">
					<p>
						{ __(
							'Something went wrong loading this section.',
							'pressprimer-quiz'
						) }
					</p>
					<button
						type="button"
						className="ppq-shell-btn"
						onClick={ this.handleRetry }
					>
						{ __( 'Try again', 'pressprimer-quiz' ) }
					</button>
				</div>
			);
		}

		return ( this.props as { children?: unknown } ).children;
	}
}
