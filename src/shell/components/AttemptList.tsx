/**
 * Attempt list.
 *
 * Shared rendering of attempt rows used by Home (recent) and My Results.
 * Completed attempts link to their existing results page; in-progress attempts
 * link back into the quiz to resume. Results are not reimplemented here.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import { __ } from '@wordpress/i18n';
import type { MyAttemptItem } from '../types';

/**
 * Status badge for an attempt.
 *
 * @param {{ item: MyAttemptItem }} props Props.
 * @return {JSX.Element} Badge.
 */
function StatusBadge( { item }: { item: MyAttemptItem } ) {
	if ( 'completed' === item.status ) {
		return item.passed ? (
			<span className="ppq-shell-badge ppq-shell-badge--success">
				{ __( 'Passed', 'pressprimer-quiz' ) }
			</span>
		) : (
			<span className="ppq-shell-badge ppq-shell-badge--error">
				{ __( 'Failed', 'pressprimer-quiz' ) }
			</span>
		);
	}

	if ( 'in_progress' === item.status ) {
		return (
			<span className="ppq-shell-badge">
				{ __( 'In progress', 'pressprimer-quiz' ) }
			</span>
		);
	}

	return (
		<span className="ppq-shell-badge">
			{ __( 'Abandoned', 'pressprimer-quiz' ) }
		</span>
	);
}

/**
 * Render a list of attempts.
 *
 * @param {{ items: MyAttemptItem[] }} props Props.
 * @return {JSX.Element} List.
 */
export default function AttemptList( { items }: { items: MyAttemptItem[] } ) {
	return (
		<ul className="ppq-shell-attempts">
			{ items.map( ( item ) => {
				const meta = [
					item.started_at,
					null !== item.score_percent ? item.score_percent + '%' : null,
				]
					.filter( Boolean )
					.join( ' · ' );

				return (
					<li className="ppq-shell-attempt" key={ item.attempt_id }>
						<div className="ppq-shell-attempt-main">
							<span className="ppq-shell-attempt-title">
								{ item.quiz_title }
							</span>
							<span className="ppq-shell-attempt-meta">{ meta }</span>
						</div>
						<StatusBadge item={ item } />
						<div className="ppq-shell-attempt-action">
							{ 'completed' === item.status && item.results_url && (
								<a className="ppq-shell-btn" href={ item.results_url }>
									{ __( 'View results', 'pressprimer-quiz' ) }
								</a>
							) }
							{ 'in_progress' === item.status && item.resume_url && (
								<a
									className="ppq-shell-btn ppq-shell-btn--primary"
									href={ item.resume_url }
								>
									{ __( 'Resume', 'pressprimer-quiz' ) }
								</a>
							) }
						</div>
					</li>
				);
			} ) }
		</ul>
	);
}
