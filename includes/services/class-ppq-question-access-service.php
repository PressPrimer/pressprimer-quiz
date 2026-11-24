<?php
/**
 * Question Access Service
 *
 * One rule for which questions and banks a user may use, shared by the REST
 * API, the models and the wp-admin handlers (v3.2 feature 001).
 *
 * A user with pressprimer_quiz_manage_all may use and change anything.
 * Everyone else may use a question that isn't deleted when they wrote it, it
 * is in a bank they own, or it is in a bank the
 * pressprimer_quiz_accessible_bank_ids filter grants them (Educator's shared
 * banks). Questions are saved only into banks the user owns.
 *
 * Every method takes an explicit user ID: rule matching runs while a student
 * takes the quiz, but is judged as the quiz owner.
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 * @since 3.2.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Question access service class
 *
 * @since 3.2.0
 */
class PressPrimer_Quiz_Question_Access_Service {

	/**
	 * Per-request memoization of the banks each user owns, keyed by user ID.
	 *
	 * @since 3.2.0
	 * @var array<int, int[]>
	 */
	private static $owned_bank_ids = [];

	/**
	 * Per-request memoization of the banks each user may use, keyed by user ID.
	 *
	 * @since 3.2.0
	 * @var array<int, int[]>
	 */
	private static $usable_bank_ids = [];

	/**
	 * Register the hooks that keep the memoized bank sets current.
	 *
	 * @since 3.2.0
	 */
	public static function register() {
		add_action( 'pressprimer_quiz_bank_created', [ __CLASS__, 'flush_cache' ] );
		add_action( 'pressprimer_quiz_bank_updated', [ __CLASS__, 'flush_cache' ] );
		add_action( 'pressprimer_quiz_bank_deleted', [ __CLASS__, 'flush_cache' ] );
	}

	/**
	 * Forget the memoized bank sets.
	 *
	 * Runs when a bank is created, updated or deleted. Call it directly after
	 * changing bank ownership or sharing outside the bank model.
	 *
	 * @since 3.2.0
	 */
	public static function flush_cache() {
		self::$owned_bank_ids  = [];
		self::$usable_bank_ids = [];
	}

	/**
	 * Find the questions a user may not use.
	 *
	 * Deleted and missing questions count as unusable, so a request can't
	 * leave empty rows in a quiz or a bank.
	 *
	 * @since 3.2.0
	 *
	 * @param int[] $question_ids Question IDs to check.
	 * @param int   $user_id      User ID to judge.
	 * @return int[] The IDs the user may not use; empty when all are usable.
	 */
	public static function get_unusable_question_ids( array $question_ids, $user_id ) {
		global $wpdb;

		$question_ids = array_values( array_unique( array_map( 'absint', $question_ids ) ) );

		if ( empty( $question_ids ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $question_ids ), '%d' ) );
		$query        = "SELECT q.id FROM {$wpdb->prefix}ppq_questions q WHERE q.id IN ({$placeholders}) AND q.deleted_at IS NULL";

		$usable_clause = self::usable_questions_clause( $user_id );
		if ( '' !== $usable_clause ) {
			$query .= " AND {$usable_clause}";
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Permission check before a write; must not be cached.
		$usable = array_map( 'absint', $wpdb->get_col( $wpdb->prepare( $query, $question_ids ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholders built above; the usable clause is prepared.

		return array_values( array_diff( $question_ids, $usable ) );
	}

	/**
	 * Build the SQL condition limiting questions to those a user may use.
	 *
	 * Returns a prepared fragment for a WHERE clause on the ppq_questions
	 * table, or '' for users with pressprimer_quiz_manage_all. The fragment
	 * covers ownership only: callers still filter out deleted questions.
	 *
	 * @since 3.2.0
	 *
	 * @param int    $user_id User ID to judge.
	 * @param string $alias   Alias of the ppq_questions table in the query.
	 * @return string Prepared SQL fragment, or '' when every question is usable.
	 */
	public static function usable_questions_clause( $user_id, $alias = 'q' ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( $user_id && user_can( $user_id, 'pressprimer_quiz_manage_all' ) ) {
			return '';
		}

		$alias = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $alias );
		if ( '' === $alias ) {
			$alias = 'q';
		}

		$bank_ids = self::get_usable_bank_ids( $user_id );

		if ( empty( $bank_ids ) ) {
			return $wpdb->prepare( '%i.author_id = %d', $alias, $user_id );
		}

		$placeholders = implode( ',', array_fill( 0, count( $bank_ids ), '%d' ) );
		$clause       = "(%i.author_id = %d OR %i.id IN (SELECT question_id FROM {$wpdb->prefix}ppq_bank_questions WHERE bank_id IN ({$placeholders})))";

		return $wpdb->prepare( $clause, array_merge( [ $alias, $user_id, $alias ], $bank_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name from $wpdb->prefix; placeholders built above.
	}

	/**
	 * Find the banks a user may not add questions to.
	 *
	 * Missing banks count as not owned, so a request can't create rows for a
	 * bank that doesn't exist. Users with pressprimer_quiz_manage_all own
	 * every bank, so only missing IDs come back for them.
	 *
	 * @since 3.2.0
	 *
	 * @param int[] $bank_ids Bank IDs to check.
	 * @param int   $user_id  User ID to judge.
	 * @return int[] The IDs the user doesn't own; empty when they own all.
	 */
	public static function get_unowned_bank_ids( array $bank_ids, $user_id ) {
		global $wpdb;

		$bank_ids = array_values( array_unique( array_map( 'absint', $bank_ids ) ) );
		$user_id  = absint( $user_id );

		if ( empty( $bank_ids ) ) {
			return [];
		}

		if ( $user_id && user_can( $user_id, 'pressprimer_quiz_manage_all' ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $bank_ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Permission check before a write; must not be cached.
			$owned = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}ppq_banks WHERE id IN ({$placeholders}) AND deleted_at IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix; placeholders built above.
					$bank_ids
				)
			);
			$owned = array_map( 'absint', $owned );
		} else {
			$owned = self::get_owned_bank_ids( $user_id );
		}

		return array_values( array_diff( $bank_ids, $owned ) );
	}

	/**
	 * Get the banks a user may draw questions from or pick questions from.
	 *
	 * The banks they own plus those the pressprimer_quiz_accessible_bank_ids
	 * filter grants them. Users with pressprimer_quiz_manage_all may use every
	 * bank.
	 *
	 * @since 3.2.0
	 *
	 * @param int $user_id User ID to judge.
	 * @return int[] Bank IDs, sorted.
	 */
	public static function get_usable_bank_ids( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return [];
		}

		if ( isset( self::$usable_bank_ids[ $user_id ] ) ) {
			return self::$usable_bank_ids[ $user_id ];
		}

		if ( user_can( $user_id, 'pressprimer_quiz_manage_all' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Memoized for the request; must reflect the current banks.
			$bank_ids = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}ppq_banks WHERE deleted_at IS NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		} else {
			/**
			 * Filters the other users' bank IDs a user may use questions from.
			 *
			 * Allows addons (like Educator) to grant access to questions in
			 * shared banks based on group membership or other criteria. The
			 * user's own banks are always usable and needn't be returned.
			 *
			 * Judge the user passed in, never the current user: rule matching
			 * runs while a student takes the quiz but is judged as the quiz
			 * owner.
			 *
			 * @since 2.0.0
			 * @since 3.2.0 Applied for an explicit user that may not be the current user.
			 *
			 * @param int[] $accessible_bank_ids Bank IDs the user can access. Default empty.
			 * @param int   $user_id             User ID being checked.
			 */
			$shared_ids = apply_filters( 'pressprimer_quiz_accessible_bank_ids', [], $user_id );
			$shared_ids = is_array( $shared_ids ) ? $shared_ids : [];

			$bank_ids = array_merge( self::get_owned_bank_ids( $user_id ), $shared_ids );
		}

		$bank_ids = array_values( array_unique( array_filter( array_map( 'absint', $bank_ids ) ) ) );
		sort( $bank_ids );

		self::$usable_bank_ids[ $user_id ] = $bank_ids;

		return $bank_ids;
	}

	/**
	 * Get the banks a user owns.
	 *
	 * @since 3.2.0
	 *
	 * @param int $user_id User ID.
	 * @return int[] Bank IDs.
	 */
	public static function get_owned_bank_ids( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return [];
		}

		if ( ! isset( self::$owned_bank_ids[ $user_id ] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Memoized for the request; must reflect the current banks.
			$bank_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}ppq_banks WHERE owner_id = %d AND deleted_at IS NULL", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
					$user_id
				)
			);

			self::$owned_bank_ids[ $user_id ] = array_map( 'absint', $bank_ids );
		}

		return self::$owned_bank_ids[ $user_id ];
	}

	/**
	 * Check whether a user may change a bank's contents.
	 *
	 * @since 3.2.0
	 *
	 * @param PressPrimer_Quiz_Bank|object $bank    Bank (needs owner_id).
	 * @param int                          $user_id User ID to judge.
	 * @return bool True for the owner and for pressprimer_quiz_manage_all users.
	 */
	public static function can_edit_bank( $bank, $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! $bank ) {
			return false;
		}

		return absint( $bank->owner_id ) === $user_id || user_can( $user_id, 'pressprimer_quiz_manage_all' );
	}

	/**
	 * Get a question's bank memberships with the user's edit right on each.
	 *
	 * Lists every bank the question is in, including banks the user can't
	 * edit, so editors can show those memberships read-only by name.
	 *
	 * @since 3.2.0
	 *
	 * @param int $question_id Question ID.
	 * @param int $user_id     User ID to judge.
	 * @return array[] Rows of { id, name, can_edit }, by bank name.
	 */
	public static function get_bank_memberships( $question_id, $user_id ) {
		global $wpdb;

		$question_id = absint( $question_id );

		if ( ! $question_id ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Editor data for one question.
		$banks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.id, b.name, b.owner_id
				FROM {$wpdb->prefix}ppq_bank_questions bq
				INNER JOIN {$wpdb->prefix}ppq_banks b ON b.id = bq.bank_id
				WHERE bq.question_id = %d AND b.deleted_at IS NULL
				ORDER BY b.name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix.
				$question_id
			)
		);

		$memberships = [];
		foreach ( $banks as $bank ) {
			$memberships[] = [
				'id'       => absint( $bank->id ),
				'name'     => $bank->name,
				'can_edit' => self::can_edit_bank( $bank, $user_id ),
			];
		}

		return $memberships;
	}
}
