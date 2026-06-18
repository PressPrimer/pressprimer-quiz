<?php
/**
 * Progress Reset Service.
 *
 * Resolves the set of attempts targeted by a reset operation (by user, by
 * quiz, or by user-on-a-quiz) and produces the read-only preview shown before
 * anything is deleted. Deletion itself (chunked, transactional) is layered on
 * top of this resolver in a later step (feature 006).
 *
 * Scope semantics:
 *  - By user:        every attempt owned by the user, across all quizzes. Guest
 *                    attempts (NULL user_id) never match.
 *  - By quiz:        every attempt on the quiz, including guest attempts.
 *  - By user + quiz: the intersection (guests excluded, since user_id is set).
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 * @since 3.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Progress Reset Service class.
 *
 * @since 3.0.0
 */
class PressPrimer_Quiz_Progress_Reset_Service {

	/**
	 * Validate and normalize a requested scope.
	 *
	 * At least one of user_id / quiz_id must be present. A by-user scope keeps
	 * the raw user id even when the WordPress user no longer exists, so admins
	 * can clean up attempts left behind by a deleted account (the preview flags
	 * this). Returns the canonical scope array used everywhere downstream.
	 *
	 * @since 3.0.0
	 *
	 * @param mixed $user_id Requested user id (0/empty for "not in scope").
	 * @param mixed $quiz_id Requested quiz id (0/empty for "not in scope").
	 * @return array|WP_Error { user_id:?int, quiz_id:?int, initiator_id:int } or error.
	 */
	public function sanitize_scope( $user_id, $quiz_id ) {
		$user_id = absint( $user_id );
		$quiz_id = absint( $quiz_id );

		if ( ! $user_id && ! $quiz_id ) {
			return new WP_Error(
				'ppq_reset_no_scope',
				__( 'Select a user or a quiz to reset.', 'pressprimer-quiz' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'user_id'      => $user_id ? $user_id : null,
			'quiz_id'      => $quiz_id ? $quiz_id : null,
			'initiator_id' => get_current_user_id(),
		);
	}

	/**
	 * Derive the scope type label from a normalized scope.
	 *
	 * @since 3.0.0
	 *
	 * @param array $scope Normalized scope.
	 * @return string One of: user, quiz, user_quiz.
	 */
	private function get_scope_type( $scope ) {
		$has_user = ! empty( $scope['user_id'] );
		$has_quiz = ! empty( $scope['quiz_id'] );

		if ( $has_user && $has_quiz ) {
			return 'user_quiz';
		}

		return $has_user ? 'user' : 'quiz';
	}

	/**
	 * Build the WHERE fragment (and ordered params) for a scope.
	 *
	 * Only the indexed columns user_id and quiz_id appear, each as a %d
	 * placeholder — no user-supplied text is ever concatenated. The caller is
	 * responsible for prepending the table and running the result through
	 * $wpdb->prepare(). sanitize_scope() guarantees at least one condition, so
	 * the returned fragment is never empty.
	 *
	 * @since 3.0.0
	 *
	 * @param array $scope Normalized scope.
	 * @return array { 0: string WHERE body, 1: int[] ordered params }.
	 */
	private function build_scope_where( $scope ) {
		$conditions = array();
		$params     = array();

		if ( ! empty( $scope['quiz_id'] ) ) {
			$conditions[] = 'quiz_id = %d';
			$params[]     = (int) $scope['quiz_id'];
		}

		if ( ! empty( $scope['user_id'] ) ) {
			$conditions[] = 'user_id = %d';
			$params[]     = (int) $scope['user_id'];
		}

		return array( implode( ' AND ', $conditions ), $params );
	}

	/**
	 * Resolve the attempt ids in a scope (optionally a keyset-paged batch).
	 *
	 * Ordered by id ascending so a cursor (`$after_id`) can walk the full set
	 * in stable batches during chunked deletion. With no limit it returns every
	 * matching id.
	 *
	 * @since 3.0.0
	 *
	 * @param array $scope    Normalized scope.
	 * @param int   $limit    Max ids to return (0 = no limit).
	 * @param int   $after_id Return only ids greater than this (0 = from start).
	 * @return int[] Attempt ids.
	 */
	public function resolve_attempt_ids( $scope, $limit = 0, $after_id = 0 ) {
		global $wpdb;

		list( $where, $params ) = $this->build_scope_where( $scope );
		$table                  = $wpdb->prefix . 'ppq_attempts';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix; $where holds only fixed %d placeholders.
		$sql = "SELECT id FROM {$table} WHERE {$where}";

		if ( $after_id > 0 ) {
			$sql     .= ' AND id > %d';
			$params[] = (int) $after_id;
		}

		$sql .= ' ORDER BY id ASC';

		if ( $limit > 0 ) {
			$sql     .= ' LIMIT %d';
			$params[] = (int) $limit;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; reset tooling must read live ids.
		$ids = $wpdb->get_col(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with placeholders; values bound here.
			$wpdb->prepare( $sql, $params )
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Build the read-only preview for a scope.
	 *
	 * Counts come from aggregate queries on indexed columns (no id set is
	 * materialized). Addons append their own dependent-data lines through the
	 * pressprimer_quiz_reset_preview filter (e.g. spaced-repetition rows).
	 *
	 * @since 3.0.0
	 *
	 * @param array $scope Normalized scope (from sanitize_scope()).
	 * @return array Preview payload.
	 */
	public function get_preview( $scope ) {
		global $wpdb;

		list( $where, $params ) = $this->build_scope_where( $scope );
		$attempts_table         = $wpdb->prefix . 'ppq_attempts';
		$items_table            = $wpdb->prefix . 'ppq_attempt_items';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix; $where holds only fixed %d placeholders.
		$counts_sql = "SELECT
				COUNT(*) AS total,
				SUM( CASE WHEN status = 'submitted' THEN 1 ELSE 0 END ) AS completed,
				SUM( CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END ) AS in_progress,
				SUM( CASE WHEN status = 'abandoned' THEN 1 ELSE 0 END ) AS abandoned,
				SUM( CASE WHEN user_id IS NULL THEN 1 ELSE 0 END ) AS guests,
				MIN( started_at ) AS date_from,
				MAX( started_at ) AS date_to
			FROM {$attempts_table} WHERE {$where}";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; reset preview must reflect live data.
		$counts = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with placeholders; values bound here.
			$wpdb->prepare( $counts_sql, $params )
		);

		// Items belonging to the in-scope attempts. A subquery keeps it to one
		// round trip and one indexed scan instead of materializing the id set.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tables from $wpdb->prefix; $where holds only fixed %d placeholders.
		$items_sql = "SELECT COUNT(*) FROM {$items_table}
			WHERE attempt_id IN ( SELECT id FROM {$attempts_table} WHERE {$where} )";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; reset preview must reflect live data.
		$items_total = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with placeholders; values bound here.
			$wpdb->prepare( $items_sql, $params )
		);

		$preview = array(
			'scope_type'           => $this->get_scope_type( $scope ),
			'user_id'              => $scope['user_id'] ? (int) $scope['user_id'] : null,
			'user_login'           => null,
			'user_display'         => null,
			'deleted_user'         => false,
			'quiz_id'              => $scope['quiz_id'] ? (int) $scope['quiz_id'] : null,
			'quiz_title'           => null,
			'attempts_total'       => $counts ? (int) $counts->total : 0,
			'attempts_completed'   => $counts ? (int) $counts->completed : 0,
			'attempts_in_progress' => $counts ? (int) $counts->in_progress : 0,
			'attempts_abandoned'   => $counts ? (int) $counts->abandoned : 0,
			'items_total'          => $items_total,
			'guest_attempts'       => $counts ? (int) $counts->guests : 0,
			'date_from'            => ( $counts && $counts->date_from ) ? $counts->date_from : null,
			'date_to'              => ( $counts && $counts->date_to ) ? $counts->date_to : null,
			'addon_lines'          => array(),
		);

		// Resolve the human-facing labels used by the confirmation step: the
		// username (user scopes) or quiz title (quiz scope) the admin must type.
		if ( ! empty( $scope['user_id'] ) ) {
			$user = get_userdata( (int) $scope['user_id'] );
			if ( $user ) {
				$preview['user_login']   = $user->user_login;
				$preview['user_display'] = $user->display_name;
			} else {
				$preview['deleted_user'] = true;
			}
		}

		if ( ! empty( $scope['quiz_id'] ) && class_exists( 'PressPrimer_Quiz_Quiz' ) ) {
			$quiz = PressPrimer_Quiz_Quiz::get( (int) $scope['quiz_id'] );
			if ( $quiz ) {
				$preview['quiz_title'] = $quiz->title;
			}
		}

		/**
		 * Filter the addon lines shown in the reset preview.
		 *
		 * Addons append a short, already-formatted line describing the
		 * dependent data they will delete in the same operation — e.g.
		 * "Spaced repetition state: 312 rows" — or an informational note such
		 * as "Previously sent xAPI statements are not retracted." Counts should
		 * be formatted with number_format_i18n().
		 *
		 * @since 3.0.0
		 *
		 * @param string[] $lines Display lines (default empty).
		 * @param array    $scope Normalized scope { user_id, quiz_id, initiator_id }.
		 */
		$lines = apply_filters( 'pressprimer_quiz_reset_preview', array(), $scope );

		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				if ( is_string( $line ) && '' !== $line ) {
					$preview['addon_lines'][] = $line;
				}
			}
		}

		return $preview;
	}
}
