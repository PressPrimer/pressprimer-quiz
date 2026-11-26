<?php
/**
 * Front-end shell companion functions.
 *
 * Global helpers that accompany PressPrimer_Quiz_Shell (feature 002). Loaded
 * directly (not autoloaded) so the URL helper is available everywhere — emails,
 * results pages, and addon UIs — without instantiating the shell.
 *
 * @package PressPrimer_Quiz
 * @subpackage Frontend
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pressprimer_quiz_get_dashboard_url' ) ) {
	/**
	 * Get the URL of the designated front-end dashboard page, optionally deep-linked.
	 *
	 * Returns the dashboard page permalink with the hash route appended, or an
	 * empty string when no valid dashboard page is designated — unset, deleted,
	 * the wrong post type, or not published. Callers treat '' as "no dashboard"
	 * and hide the link.
	 *
	 * @since 3.0.0
	 *
	 * @param string $route Optional shell route, with or without a leading '#/'
	 *                      (e.g. 'my-results' or '#/my-results'). Empty returns
	 *                      the dashboard home.
	 * @return string The dashboard URL, or '' when no valid page is designated.
	 */
	function pressprimer_quiz_get_dashboard_url( $route = '' ): string {
		$page_id = (int) get_option( 'pressprimer_quiz_dashboard_page_id', 0 );

		if ( $page_id <= 0 ) {
			return '';
		}

		$page = get_post( $page_id );

		if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return '';
		}

		$permalink = get_permalink( $page_id );

		if ( ! $permalink ) {
			return '';
		}

		$route = ltrim( (string) $route, '#/' );

		if ( '' !== $route ) {
			$permalink .= '#/' . $route;
		}

		return $permalink;
	}
}

if ( ! function_exists( 'pressprimer_quiz_enqueue_portable_editors' ) ) {
	/**
	 * Enqueue what a dashboard page needs to open the portable editors.
	 *
	 * For hosts (Educator's authoring screens): call it on a dashboard page
	 * for a user who can author (pressprimer_quiz_manage_own), during
	 * wp_enqueue_scripts. It enqueues the small editors loader and its
	 * manifest, TinyMCE and the math assets; the editors bundle itself loads
	 * when the host calls PPQ.editors.load(). Hosts check function_exists()
	 * first: free 3.1 has no portable editors.
	 *
	 * @since 3.2.0
	 */
	function pressprimer_quiz_enqueue_portable_editors() {
		PressPrimer_Quiz_Portable_Editors::enqueue();
	}
}
