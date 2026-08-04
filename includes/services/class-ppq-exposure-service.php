<?php
/**
 * Question Exposure Service
 *
 * Resolves which questions a user has already seen so per-attempt selection
 * can prefer unseen questions (v3.1 feature 002). Seen-ness resolves through
 * question revisions, so any seen revision of a question counts as seen.
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 * @since 3.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposure service class
 *
 * @since 3.1.0
 */
class PressPrimer_Quiz_Exposure_Service {

	/**
	 * Per-request memoization of seen maps, keyed "{quiz_id}:{user_id}".
	 *
	 * The seen-set query runs once per generation; every selection point
	 * (per-rule partitions and the pool cap) reuses the same map. No
	 * persistent cache in v1 — the query is bounded by the user's attempt
	 * volume.
	 *
	 * @since 3.1.0
	 * @var array<string, array<int, string>>
	 */
	private static $seen_cache = [];

	/**
	 * Get the seen map for a user in a quiz's exposure scope.
	 *
	 * Returns [ question_id => last_seen datetime ]. All attempts count as
	 * exposure — practice and graded, any status that produced items.
	 *
	 * @since 3.1.0
	 *
	 * @param int $quiz_id Quiz ID whose generation is running.
	 * @param int $user_id User ID. Guests (0) always yield an empty map.
	 * @return array<int, string> Map of question ID => last seen datetime.
	 */
	public static function get_seen_map( $quiz_id, $user_id ) {
		$quiz_id = absint( $quiz_id );
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return [];
		}

		$cache_key = $quiz_id . ':' . $user_id;
		if ( isset( self::$seen_cache[ $cache_key ] ) ) {
			return self::$seen_cache[ $cache_key ];
		}

		/**
		 * Filters the quiz scope used to resolve a user's seen questions.
		 *
		 * Default scope is per-quiz: a retake of Quiz X prefers questions
		 * unseen in Quiz X. Return an array of quiz IDs to widen or narrow
		 * the scope, or the string 'site' to count exposure across all
		 * quizzes. Documented consumer: the School 3.1 Study Planner widens
		 * the scope for planner-generated attempts.
		 *
		 * @since 3.1.0
		 *
		 * @param array|string $scope   Quiz IDs bounding the seen scope, or 'site'.
		 * @param int          $quiz_id Quiz whose generation is running.
		 * @param int          $user_id User the attempt is generated for.
		 */
		$scope = apply_filters( 'pressprimer_quiz_exposure_scope', [ $quiz_id ], $quiz_id, $user_id );

		global $wpdb;

		$attempt_items_table = $wpdb->prefix . 'ppq_attempt_items';
		$revisions_table     = $wpdb->prefix . 'ppq_question_revisions';
		$attempts_table      = $wpdb->prefix . 'ppq_attempts';

		$quiz_where  = '';
		$quiz_values = [];
		if ( 'site' !== $scope ) {
			$scope_ids = is_array( $scope ) ? array_values( array_filter( array_map( 'absint', $scope ) ) ) : [];
			if ( empty( $scope_ids ) ) {
				// Garbage filter return: fall back to the per-quiz default
				// rather than silently widening to the whole site.
				$scope_ids = [ $quiz_id ];
			}
			$placeholders = implode( ',', array_fill( 0, count( $scope_ids ), '%d' ) );
			$quiz_where   = "AND a.quiz_id IN ({$placeholders})";
			$quiz_values  = $scope_ids;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Memoized per request above; per-user history is not cacheable persistently.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT qr.question_id, MAX(a.started_at) AS last_seen
				 FROM {$attempt_items_table} ai
				 INNER JOIN {$revisions_table} qr ON qr.id = ai.question_revision_id
				 INNER JOIN {$attempts_table} a ON a.id = ai.attempt_id
				 WHERE a.user_id = %d {$quiz_where}
				 GROUP BY qr.question_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix; $quiz_where is a literal %d placeholder list bound below.
				array_merge( [ $user_id ], $quiz_values )
			)
		);

		$seen_map = [];
		foreach ( (array) $rows as $row ) {
			$seen_map[ (int) $row->question_id ] = (string) $row->last_seen;
		}

		/**
		 * Filters the resolved seen map before selection uses it.
		 *
		 * Lets consumers inject or amend entries ([ question_id => last_seen
		 * datetime ]); School may use it for plan-level bookkeeping.
		 *
		 * @since 3.1.0
		 *
		 * @param array<int, string> $seen_map Question ID => last seen datetime.
		 * @param int                $quiz_id  Quiz whose generation is running.
		 * @param int                $user_id  User the attempt is generated for.
		 */
		$seen_map = apply_filters( 'pressprimer_quiz_exposure_seen_questions', $seen_map, $quiz_id, $user_id );
		if ( ! is_array( $seen_map ) ) {
			$seen_map = [];
		}

		self::$seen_cache[ $cache_key ] = $seen_map;

		return $seen_map;
	}

	/**
	 * Reset the per-request memoization (primarily for tests).
	 *
	 * @since 3.1.0
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$seen_cache = [];
	}
}
