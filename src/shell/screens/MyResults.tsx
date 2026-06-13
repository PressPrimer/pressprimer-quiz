/**
 * My Results screen.
 *
 * The current user's attempts: filter by quiz and status, sort by date or
 * score, paginated. Completed rows link to the existing results page;
 * in-progress rows resume the quiz. Inviting empty state for a fresh user.
 * US-2.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { useState, useEffect } from '@wordpress/element';
import { sprintf, __ } from '@wordpress/i18n';
import { fetchMyAttempts, isForbidden } from '../api';
import AttemptList from '../components/AttemptList';
import { Skeleton, ScreenForbidden } from '../components/states';
import type {
	ScreenProps,
	MyAttemptItem,
	AttemptQuizOption,
	MyAttemptsParams,
} from '../types';

const PER_PAGE = 10;

/**
 * Render the My Results screen.
 *
 * @param {ScreenProps} props Screen props (unused; data is the session user's).
 * @return {JSX.Element} My Results content.
 */
export default function MyResults( props: ScreenProps ) {
	void props;

	const [ items, setItems ] = useState< MyAttemptItem[] >( [] );
	const [ quizzes, setQuizzes ] = useState< AttemptQuizOption[] >( [] );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ loading, setLoading ] = useState( true );
	const [ forbidden, setForbidden ] = useState( false );
	const [ page, setPage ] = useState( 1 );
	const [ quizId, setQuizId ] = useState( 0 );
	const [ status, setStatus ] = useState( 'all' );
	const [ orderby, setOrderby ] = useState( 'date' );
	const [ order, setOrder ] = useState( 'desc' );

	useEffect( () => {
		let active = true;
		setLoading( true );
		setForbidden( false );

		const params: MyAttemptsParams = {
			page,
			per_page: PER_PAGE,
			orderby,
			order,
		};
		if ( quizId ) {
			params.quiz_id = quizId;
		}
		if ( 'all' !== status ) {
			params.status = status;
		}

		fetchMyAttempts( params )
			.then( ( data ) => {
				if ( ! active ) {
					return;
				}
				setItems( data.items || [] );
				setTotalPages( data.total_pages || 1 );
				if ( Array.isArray( data.quizzes ) ) {
					setQuizzes( data.quizzes );
				}
				setLoading( false );
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
	}, [ page, quizId, status, orderby, order ] );

	return (
		<div className="ppq-shell-results">
			<div className="ppq-shell-filters">
				<label className="ppq-shell-field" htmlFor="ppq-shell-filter-quiz">
					<span>{ __( 'Quiz', 'pressprimer-quiz' ) }</span>
					<select
						id="ppq-shell-filter-quiz"
						value={ quizId }
						onChange={ ( event ) => {
							setQuizId( Number( event.target.value ) );
							setPage( 1 );
						} }
					>
						<option value={ 0 }>
							{ __( 'All quizzes', 'pressprimer-quiz' ) }
						</option>
						{ quizzes.map( ( quiz ) => (
							<option key={ quiz.id } value={ quiz.id }>
								{ quiz.title }
							</option>
						) ) }
					</select>
				</label>

				<label className="ppq-shell-field" htmlFor="ppq-shell-filter-status">
					<span>{ __( 'Status', 'pressprimer-quiz' ) }</span>
					<select
						id="ppq-shell-filter-status"
						value={ status }
						onChange={ ( event ) => {
							setStatus( event.target.value );
							setPage( 1 );
						} }
					>
						<option value="all">{ __( 'All', 'pressprimer-quiz' ) }</option>
						<option value="completed">
							{ __( 'Completed', 'pressprimer-quiz' ) }
						</option>
						<option value="in_progress">
							{ __( 'In progress', 'pressprimer-quiz' ) }
						</option>
					</select>
				</label>

				<label className="ppq-shell-field" htmlFor="ppq-shell-filter-sort">
					<span>{ __( 'Sort by', 'pressprimer-quiz' ) }</span>
					<select
						id="ppq-shell-filter-sort"
						value={ orderby }
						onChange={ ( event ) => {
							setOrderby( event.target.value );
							setPage( 1 );
						} }
					>
						<option value="date">{ __( 'Date', 'pressprimer-quiz' ) }</option>
						<option value="score">
							{ __( 'Score', 'pressprimer-quiz' ) }
						</option>
					</select>
				</label>

				<button
					type="button"
					className="ppq-shell-btn"
					aria-label={
						'asc' === order
							? __( 'Sorted ascending', 'pressprimer-quiz' )
							: __( 'Sorted descending', 'pressprimer-quiz' )
					}
					onClick={ () => {
						setOrder( 'desc' === order ? 'asc' : 'desc' );
						setPage( 1 );
					} }
				>
					{ 'desc' === order ? '↓' : '↑' }
				</button>
			</div>

			{ forbidden && <ScreenForbidden /> }

			{ ! forbidden && loading && <Skeleton /> }

			{ ! forbidden && ! loading && items.length === 0 && (
				<div className="ppq-shell-state" role="status">
					<p>
						{ __(
							'You have not taken any quizzes yet. Your results will appear here.',
							'pressprimer-quiz'
						) }
					</p>
				</div>
			) }

			{ ! forbidden && ! loading && items.length > 0 && (
				<>
					<AttemptList items={ items } />
					{ totalPages > 1 && (
						<nav
							className="ppq-shell-pagination"
							aria-label={ __( 'Results pages', 'pressprimer-quiz' ) }
						>
							<button
								type="button"
								className="ppq-shell-btn"
								disabled={ page <= 1 }
								onClick={ () => setPage( page - 1 ) }
							>
								{ __( 'Previous', 'pressprimer-quiz' ) }
							</button>
							<span className="ppq-shell-pagination-status">
								{ sprintf(
									/* translators: 1: current page, 2: total pages. */
									__( 'Page %1$d of %2$d', 'pressprimer-quiz' ),
									page,
									totalPages
								) }
							</span>
							<button
								type="button"
								className="ppq-shell-btn"
								disabled={ page >= totalPages }
								onClick={ () => setPage( page + 1 ) }
							>
								{ __( 'Next', 'pressprimer-quiz' ) }
							</button>
						</nav>
					) }
				</>
			) }
		</div>
	);
}
