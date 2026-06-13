/**
 * Shell data fetching.
 *
 * Relative apiFetch paths; @wordpress/api-fetch prepends the REST root and adds
 * the nonce that WordPress configures when wp-api-fetch is enqueued.
 *
 * @package PressPrimer_Quiz
 * @since 3.0.0
 */

import apiFetch from '@wordpress/api-fetch';
import type { MyAttemptsParams, MyAttemptsResponse } from './types';

/**
 * Fetch the current user's attempts.
 *
 * @param {MyAttemptsParams} params Query params.
 * @return {Promise<MyAttemptsResponse>} The response.
 */
export function fetchMyAttempts(
	params: MyAttemptsParams
): Promise< MyAttemptsResponse > {
	const query: Record< string, string > = {};

	if ( params.page ) {
		query.page = String( params.page );
	}
	if ( params.per_page ) {
		query.per_page = String( params.per_page );
	}
	if ( params.quiz_id ) {
		query.quiz_id = String( params.quiz_id );
	}
	if ( params.status ) {
		query.status = params.status;
	}
	if ( params.orderby ) {
		query.orderby = params.orderby;
	}
	if ( params.order ) {
		query.order = params.order;
	}

	const qs = new URLSearchParams( query ).toString();

	return apiFetch( {
		path: '/ppq/v1/my-attempts' + ( qs ? '?' + qs : '' ),
	} ) as Promise< MyAttemptsResponse >;
}

/**
 * Whether an apiFetch error is a 403 (capability revoked mid-session).
 *
 * @param {unknown} error The rejected apiFetch error.
 * @return {boolean} True for a forbidden response.
 */
export function isForbidden( error: unknown ): boolean {
	const err = error as { data?: { status?: number }; code?: string };
	return (
		!! err &&
		( ( !! err.data && err.data.status === 403 ) ||
			err.code === 'rest_forbidden' )
	);
}
