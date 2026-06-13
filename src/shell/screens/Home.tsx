/**
 * Home screen.
 *
 * Greeting, a last-5 recent-attempts summary (reusing the my-attempts
 * endpoint) with a link into My Results, and the home-card slot (addons inject
 * cards via PPQ.shell.registerHomeCard). FR-007.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { sprintf, __ } from '@wordpress/i18n';
import { useState, useEffect, createElement } from '@wordpress/element';
import { getHomeCards } from '../registry';
import { fetchMyAttempts, isForbidden } from '../api';
import AttemptList from '../components/AttemptList';
import { Skeleton, ScreenForbidden } from '../components/states';
import type { ScreenProps, MyAttemptItem } from '../types';

/**
 * Render the Home screen.
 *
 * @param {ScreenProps} props Screen props.
 * @return {JSX.Element} Home content.
 */
export default function Home( { user, navigate }: ScreenProps ) {
	const cards = getHomeCards();
	const [ recent, setRecent ] = useState< MyAttemptItem[] >( [] );
	const [ loading, setLoading ] = useState( true );
	const [ forbidden, setForbidden ] = useState( false );

	useEffect( () => {
		let active = true;
		fetchMyAttempts( { per_page: 5, orderby: 'date', order: 'desc' } )
			.then( ( data ) => {
				if ( active ) {
					setRecent( data.items || [] );
					setLoading( false );
				}
			} )
			.catch( ( error ) => {
				if ( active ) {
					setForbidden( isForbidden( error ) );
					setLoading( false );
				}
			} );
		return () => {
			active = false;
		};
	}, [] );

	return (
		<div className="ppq-shell-home">
			<p className="ppq-shell-home-greeting">
				{ user.name
					? sprintf(
							/* translators: %s: user display name. */
							__( 'Welcome back, %s.', 'pressprimer-quiz' ),
							user.name
					  )
					: __( 'Welcome back.', 'pressprimer-quiz' ) }
			</p>

			{ cards.length > 0 && (
				<div className="ppq-shell-home-cards">
					{ cards.map( ( Card, index ) =>
						createElement( Card, { user, key: index } )
					) }
				</div>
			) }

			<section className="ppq-shell-home-recent">
				<div className="ppq-shell-section-head">
					<h2>{ __( 'Recent activity', 'pressprimer-quiz' ) }</h2>
					<button
						type="button"
						className="ppq-shell-link"
						onClick={ () => navigate( 'my-results' ) }
					>
						{ __( 'View all', 'pressprimer-quiz' ) }
					</button>
				</div>

				{ loading && <Skeleton /> }
				{ ! loading && forbidden && <ScreenForbidden /> }
				{ ! loading && ! forbidden && recent.length > 0 && (
					<AttemptList items={ recent } />
				) }
				{ ! loading && ! forbidden && recent.length === 0 && (
					<p className="ppq-shell-muted">
						{ __(
							'You have not taken any quizzes yet.',
							'pressprimer-quiz'
						) }
					</p>
				) }
			</section>
		</div>
	);
}
