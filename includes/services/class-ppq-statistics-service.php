<?php
/**
 * Statistics Service
 *
 * Provides statistics and reporting data for the plugin dashboard
 * and reports page.
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Statistics Service class
 *
 * Handles all statistics queries for dashboard and reporting features.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Statistics_Service {

	/**
	 * Cache group for statistics
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const CACHE_GROUP = 'pressprimer_quiz_statistics';

	/**
	 * Default cache expiration in seconds (5 minutes)
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const CACHE_EXPIRATION = 300;

	/**
	 * Option name for cached overview stats
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OVERVIEW_STATS_OPTION = 'pressprimer_quiz_cached_overview_stats';

	/**
	 * Cron hook name for stats recalculation
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const CRON_HOOK = 'pressprimer_quiz_recalculate_overview_stats';

	/**
	 * Most attempts one CSV export holds by default
	 *
	 * @since 3.2.0
	 * @var int
	 */
	const CSV_MAX_ROWS = 10000;

	/**
	 * Users whose attempts a scoped report user also sees, on any quiz
	 *
	 * A report user without pressprimer_quiz_view_results_all is scoped to the
	 * quizzes they own. Addons can widen that with the students they're
	 * responsible for (Educator: the teacher's group members). The two
	 * combine as a union: every attempt on the user's own quizzes, plus
	 * attempts by these users on any quiz. Users who see all results aren't
	 * scoped, so the filter isn't applied for them.
	 *
	 * @since 3.2.0
	 *
	 * @param int|null $owner_id The scoped user, or null when not scoped.
	 * @param string   $context  'dashboard', 'overview', 'quiz_performance',
	 *                           'activity_chart', 'quiz_options',
	 *                           'attempts_list', 'attempt_detail' or
	 *                           'attempts_export'.
	 * @return int[] User IDs; empty means the owner rule alone.
	 */
	public static function get_visible_user_ids( $owner_id, $context ) {
		$owner_id = absint( $owner_id );

		if ( ! $owner_id ) {
			return [];
		}

		/**
		 * Filters the users whose attempts a scoped report user sees on any
		 * quiz, in addition to every attempt on the quizzes they own.
		 *
		 * Applies only to users without pressprimer_quiz_view_results_all.
		 * Return null (the default) or an empty array for the owner rule
		 * alone. Educator 3.2 returns the teacher plus their students.
		 *
		 * @since 3.2.0
		 *
		 * @param int[]|null $user_ids Null, or the user IDs to add.
		 * @param int        $user_id  The user viewing the report.
		 * @param string     $context  Which report or query is asking.
		 */
		$user_ids = apply_filters( 'pressprimer_quiz_report_visible_user_ids', null, $owner_id, (string) $context );

		if ( ! is_array( $user_ids ) ) {
			return [];
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $user_ids ) ) ) );
	}

	/**
	 * SQL for "this attempt is visible" (aliases a and q), for a scoped user
	 *
	 * @since 3.2.0
	 *
	 * @param int|null $owner_id The scoped user, or null when not scoped.
	 * @param int[]    $user_ids get_visible_user_ids() for the query.
	 * @return string Prepared condition, or '' when not scoped.
	 */
	private static function attempt_scope_sql( $owner_id, array $user_ids ) {
		global $wpdb;

		if ( ! $owner_id ) {
			return '';
		}

		if ( empty( $user_ids ) ) {
			return $wpdb->prepare( 'q.owner_id = %d', $owner_id );
		}

		$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );

		return $wpdb->prepare(
			"(q.owner_id = %d OR a.user_id IN ({$placeholders}))", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders built above.
			array_merge( [ (int) $owner_id ], $user_ids )
		);
	}

	/**
	 * SQL for "this quiz belongs in the user's reports" (alias q)
	 *
	 * Quizzes the user owns, plus quizzes with submitted attempts by the
	 * users they also see.
	 *
	 * @since 3.2.0
	 *
	 * @param int|null $owner_id The scoped user, or null when not scoped.
	 * @param int[]    $user_ids get_visible_user_ids() for the query.
	 * @return string Prepared condition, or '' when not scoped.
	 */
	private static function quiz_scope_sql( $owner_id, array $user_ids ) {
		global $wpdb;

		if ( ! $owner_id ) {
			return '';
		}

		if ( empty( $user_ids ) ) {
			return $wpdb->prepare( 'q.owner_id = %d', $owner_id );
		}

		$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );

		return $wpdb->prepare(
			"(q.owner_id = %d OR EXISTS (SELECT 1 FROM {$wpdb->prefix}ppq_attempts sa WHERE sa.quiz_id = q.id AND sa.status = 'submitted' AND sa.user_id IN ({$placeholders})))", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix; placeholders built above.
			array_merge( [ (int) $owner_id ], $user_ids )
		);
	}

	/**
	 * An ORDER BY clause for a whitelisted column
	 *
	 * The direction is written out in each branch, never interpolated.
	 *
	 * @since 3.2.0
	 *
	 * @param string $table_alias Table alias, or '' for a select alias.
	 * @param string $column      Column or select alias (from a whitelist).
	 * @param bool   $is_asc      Whether ascending.
	 * @param string $tiebreak    Optional second sort column, always a literal
	 *                            in code (e.g. 'a.id'), sorted descending.
	 * @return string The clause.
	 */
	private static function order_by_sql( $table_alias, $column, $is_asc, $tiebreak = '' ) {
		global $wpdb;

		if ( '' !== $table_alias ) {
			if ( $is_asc ) {
				$sql = $wpdb->prepare( 'ORDER BY %i.%i ASC', $table_alias, $column );
			} else {
				$sql = $wpdb->prepare( 'ORDER BY %i.%i DESC', $table_alias, $column );
			}
		} elseif ( $is_asc ) {
			$sql = $wpdb->prepare( 'ORDER BY %i ASC', $column );
		} else {
			$sql = $wpdb->prepare( 'ORDER BY %i DESC', $column );
		}

		if ( '' !== $tiebreak ) {
			$sql .= ', ' . $tiebreak . ' DESC';
		}

		return $sql;
	}

	/**
	 * The name reports show for an attempt's student
	 *
	 * The account's display name, else the guest's name or email (the
	 * student_name column), else "Deleted user #ID" for an account that no
	 * longer exists, else "Guest".
	 *
	 * @since 3.2.0
	 *
	 * @param object $row Row with student_name, user_id and wp_user_id.
	 * @return string The name.
	 */
	private static function student_label( $row ) {
		if ( isset( $row->student_name ) && '' !== (string) $row->student_name ) {
			return (string) $row->student_name;
		}

		if ( ! empty( $row->user_id ) && empty( $row->wp_user_id ) ) {
			/* translators: %d: ID of a user account that no longer exists. */
			return sprintf( __( 'Deleted user #%d', 'pressprimer-quiz' ), (int) $row->user_id );
		}

		return __( 'Guest', 'pressprimer-quiz' );
	}

	/**
	 * Whether a report user may see guest marketing consent on an attempt
	 *
	 * Users who see all results see it everywhere; a scoped user only on
	 * attempts on quizzes they own (Educator R21).
	 *
	 * @since 3.2.0
	 *
	 * @param int|null $owner_id      The scoped user, or null when not scoped.
	 * @param int      $quiz_owner_id The attempt's quiz owner.
	 * @return bool Whether consent is shown.
	 */
	private static function can_see_consent( $owner_id, $quiz_owner_id ) {
		return ! $owner_id || absint( $quiz_owner_id ) === absint( $owner_id );
	}

	/**
	 * Get dashboard statistics
	 *
	 * Returns summary statistics for the plugin's Dashboard page.
	 *
	 * @since 1.0.0
	 * @since 3.2.0 Attempt figures for a scoped user include the attempts of
	 *              the users pressprimer_quiz_report_visible_user_ids adds.
	 *
	 * @param int|null $owner_id Optional. Limit stats to specific owner. Default null for all.
	 * @return array Statistics array.
	 */
	public function get_dashboard_stats( $owner_id = null ) {
		$user_ids = self::get_visible_user_ids( $owner_id, 'dashboard' );

		// Widened scopes aren't cached: clear_dashboard_cache() can't find them.
		$cache_key = 'dashboard_stats_' . ( $owner_id ? $owner_id : 'all' );
		$stats     = empty( $user_ids ) ? wp_cache_get( $cache_key, self::CACHE_GROUP ) : false;

		if ( false !== $stats ) {
			return $stats;
		}

		global $wpdb;

		$stats = [
			'total_quizzes'      => 0,
			'total_questions'    => 0,
			'total_banks'        => 0,
			'recent_attempts'    => 0,
			'recent_pass_rate'   => 0,
			'questions_answered' => 0,
			'popular_quizzes'    => [],
		];

		$quizzes_table   = $wpdb->prefix . 'ppq_quizzes';
		$questions_table = $wpdb->prefix . 'ppq_questions';
		$banks_table     = $wpdb->prefix . 'ppq_banks';
		$attempts_table  = $wpdb->prefix . 'ppq_attempts';

		// Build owner restriction if needed.
		// Note: Questions use 'author_id', quizzes and banks use 'owner_id'.
		$owner_where  = '';
		$author_where = '';
		if ( $owner_id ) {
			$owner_where  = $wpdb->prepare( ' AND owner_id = %d', $owner_id );
			$author_where = $wpdb->prepare( ' AND author_id = %d', $owner_id );
		}

		// Which attempts a scoped user sees (aliases a and q).
		$scope = self::attempt_scope_sql( $owner_id, $user_ids );

		// Total published quizzes (excludes School SR review quizzes)
		$stats['total_quizzes'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$quizzes_table} WHERE status = 'published' AND is_review_quiz = 0{$owner_where}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name safely constructed; results cached at method level
		);

		// Total active questions (uses author_id, not owner_id)
		$stats['total_questions'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$questions_table} WHERE deleted_at IS NULL{$author_where}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name safely constructed; results cached at method level
		);

		// Total question banks
		$stats['total_banks'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$banks_table} WHERE deleted_at IS NULL{$owner_where}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name safely constructed; results cached at method level
		);

		// Attempts in last 7 days
		$seven_days_ago      = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$attempt_items_table = $wpdb->prefix . 'ppq_attempt_items';

		if ( $owner_id ) {
			$stats['recent_attempts'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$attempts_table} a
					 INNER JOIN {$quizzes_table} q ON a.quiz_id = q.id
					 WHERE a.status = 'submitted' AND a.finished_at >= %s AND {$scope}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix; scope prepared by attempt_scope_sql()
					$seven_days_ago
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Results cached at method level

			// Questions answered in last 7 days
			$stats['questions_answered'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(ai.id) FROM {$attempt_items_table} ai
					 INNER JOIN {$attempts_table} a ON ai.attempt_id = a.id
					 INNER JOIN {$quizzes_table} q ON a.quiz_id = q.id
					 WHERE a.status = 'submitted' AND a.finished_at >= %s AND {$scope}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix; scope prepared by attempt_scope_sql()
					$seven_days_ago
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Results cached at method level
		} else {
			$stats['recent_attempts'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$attempts_table}
					 WHERE status = 'submitted' AND finished_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed from $wpdb->prefix
					$seven_days_ago
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Results cached at method level

			// Questions answered in last 7 days
			$stats['questions_answered'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(ai.id) FROM {$attempt_items_table} ai
					 INNER JOIN {$attempts_table} a ON ai.attempt_id = a.id
					 WHERE a.status = 'submitted' AND a.finished_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names safely constructed from $wpdb->prefix
					$seven_days_ago
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Results cached at method level
		}

		// Pass rate in last 7 days
		if ( $owner_id ) {
			$pass_data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						COUNT(*) as total,
						SUM(CASE WHEN a.passed = 1 THEN 1 ELSE 0 END) as passed
					 FROM {$attempts_table} a
					 INNER JOIN {$quizzes_table} q ON a.quiz_id = q.id
					 WHERE a.status = 'submitted' AND a.is_practice = 0 AND a.finished_at >= %s AND {$scope}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix; scope prepared by attempt_scope_sql()
					$seven_days_ago
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Results cached at method level
		} else {
			$pass_data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						COUNT(*) as total,
						SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed
					 FROM {$attempts_table}
					 WHERE status = 'submitted' AND is_practice = 0 AND finished_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed from $wpdb->prefix
					$seven_days_ago
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Results cached at method level
		}

		if ( $pass_data && $pass_data->total > 0 ) {
			$stats['recent_pass_rate'] = round( ( $pass_data->passed / $pass_data->total ) * 100, 1 );
		}

		// Popular quizzes (last 30 days)
		$thirty_days_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

		if ( $owner_id ) {
			// The user's own quizzes, and others where they see attempts.
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix; scope prepared by attempt_scope_sql().
			$stats['popular_quizzes'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT q.id, q.title, COUNT(a.id) as attempt_count
					 FROM {$quizzes_table} q
					 LEFT JOIN {$attempts_table} a ON q.id = a.quiz_id
						AND a.status = 'submitted' AND a.finished_at >= %s AND {$scope}
					 WHERE q.status = 'published' AND (q.owner_id = %d OR a.id IS NOT NULL)
					 GROUP BY q.id
					 ORDER BY attempt_count DESC
					 LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix; scope prepared by attempt_scope_sql()
					$thirty_days_ago,
					$owner_id
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Results cached at method level
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$stats['popular_quizzes'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT q.id, q.title, COUNT(a.id) as attempt_count
					 FROM {$quizzes_table} q
					 LEFT JOIN {$attempts_table} a ON q.id = a.quiz_id
						AND a.status = 'submitted' AND a.finished_at >= %s
					 WHERE q.status = 'published'
					 GROUP BY q.id
					 ORDER BY attempt_count DESC
					 LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names safely constructed from $wpdb->prefix
					$thirty_days_ago
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Results cached at method level
		}

		// Cache the results
		if ( empty( $user_ids ) ) {
			wp_cache_set( $cache_key, $stats, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		}

		/**
		 * Filter the dashboard statistics before they are returned.
		 *
		 * Allows adding custom statistics or modifying existing ones for the
		 * admin dashboard display.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $stats    Dashboard statistics array.
		 * @param int|null $owner_id Owner ID filter, or null for all.
		 */
		return apply_filters( 'pressprimer_quiz_dashboard_stats', $stats, $owner_id );
	}

	/**
	 * Clear dashboard statistics cache
	 *
	 * Call this method when questions, quizzes, banks, or attempts change
	 * to ensure dashboard displays fresh data.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $owner_id Optional. Clear cache for specific owner only.
	 */
	public static function clear_dashboard_cache( $owner_id = null ) {
		if ( $owner_id ) {
			wp_cache_delete( 'dashboard_stats_' . $owner_id, self::CACHE_GROUP );
		} else {
			// Clear both the 'all' cache and try to flush the entire group
			wp_cache_delete( 'dashboard_stats_all', self::CACHE_GROUP );

			// If using a persistent cache that supports group flushing
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( self::CACHE_GROUP );
			}
		}
	}

	/**
	 * Get overview statistics for reports page
	 *
	 * Returns aggregate statistics for all time. Uses cached data that is
	 * recalculated hourly via cron to avoid slow queries on large datasets.
	 *
	 * @since 1.0.0
	 *
	 * @since 3.2.0 A scoped user's figures include the attempts of the users
	 *              pressprimer_quiz_report_visible_user_ids adds.
	 *
	 * @param array $args Query arguments (owner_id supported for filtering).
	 * @return array Overview statistics.
	 */
	public function get_overview_stats( $args = [] ) {
		$defaults = [
			'date_from' => null, // Kept for backwards compatibility but not used.
			'date_to'   => null, // Kept for backwards compatibility but not used.
			'owner_id'  => null,
		];

		$args = wp_parse_args( $args, $defaults );

		// For owner-specific stats, calculate fresh (these are less common)
		if ( $args['owner_id'] ) {
			return $this->calculate_overview_stats( $args['owner_id'], self::get_visible_user_ids( $args['owner_id'], 'overview' ) );
		}

		// For global stats, use cached data
		$cached = get_option( self::OVERVIEW_STATS_OPTION );

		if ( $cached && isset( $cached['data'] ) ) {
			$result                    = $cached['data'];
			$result['cached']          = true;
			$result['last_updated']    = $cached['timestamp'] ?? null;
			$result['last_updated_at'] = $cached['timestamp'] ? gmdate( 'Y-m-d H:i:s', $cached['timestamp'] ) : null;

			/** This filter is documented below */
			return apply_filters( 'pressprimer_quiz_overview_stats', $result, $args );
		}

		// No cache exists yet - calculate now and cache it
		// This only happens once on first load
		$result = $this->calculate_and_cache_overview_stats();

		/**
		 * Filter the overview statistics for reports page.
		 *
		 * Allows adding custom metrics or modifying existing ones for
		 * the reports overview cards.
		 *
		 * @since 1.0.0
		 *
		 * @param array $result Overview statistics array.
		 * @param array $args   Query arguments including date_from, date_to, owner_id.
		 */
		return apply_filters( 'pressprimer_quiz_overview_stats', $result, $args );
	}

	/**
	 * Calculate overview statistics from database
	 *
	 * Performs the actual database query to calculate stats.
	 * This is called by the cron job and for owner-specific queries.
	 *
	 * @since 1.0.0
	 *
	 * @since 3.2.0 Added $user_ids.
	 *
	 * @param int|null $owner_id Optional. Limit to specific owner.
	 * @param int[]    $user_ids Optional. Users whose attempts the owner also
	 *                           sees on any quiz (get_visible_user_ids()).
	 * @return array Overview statistics.
	 */
	public function calculate_overview_stats( $owner_id = null, $user_ids = [] ) {
		global $wpdb;

		$attempts_table = $wpdb->prefix . 'ppq_attempts';
		$quizzes_table  = $wpdb->prefix . 'ppq_quizzes';

		// Reports-screen aggregates are grade-bearing and exclude practice
		// attempts (v3.1 feature 001 FR-005 / SCOPE Q7).
		$where = [ "a.status = 'submitted'", 'a.is_practice = 0' ];

		if ( $owner_id ) {
			$where[] = self::attempt_scope_sql( $owner_id, array_map( 'absint', (array) $user_ids ) );
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregate stats query, cached at application level
		$stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total_attempts,
				ROUND(AVG(a.score_percent), 1) as avg_score,
				ROUND((SUM(CASE WHEN a.passed = 1 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 1) as pass_rate,
				ROUND(AVG(a.elapsed_ms) / 1000) as avg_time_seconds
			 FROM {$attempts_table} a
			 INNER JOIN {$quizzes_table} q ON a.quiz_id = q.id
			 WHERE {$where_sql}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return [
			'total_attempts'   => (int) ( $stats->total_attempts ?? 0 ),
			'avg_score'        => (float) ( $stats->avg_score ?? 0 ),
			'pass_rate'        => (float) ( $stats->pass_rate ?? 0 ),
			'avg_time_seconds' => (int) ( $stats->avg_time_seconds ?? 0 ),
		];
	}

	/**
	 * Calculate and cache overview statistics
	 *
	 * Calculates global overview stats and stores them in options table.
	 * Called by the hourly cron job.
	 *
	 * @since 1.0.0
	 *
	 * @return array The calculated statistics.
	 */
	public function calculate_and_cache_overview_stats() {
		$stats = $this->calculate_overview_stats();

		$cached_data = [
			'data'      => $stats,
			'timestamp' => time(),
		];

		update_option( self::OVERVIEW_STATS_OPTION, $cached_data, false );

		$stats['cached']          = true;
		$stats['last_updated']    = $cached_data['timestamp'];
		$stats['last_updated_at'] = gmdate( 'Y-m-d H:i:s', $cached_data['timestamp'] );

		return $stats;
	}

	/**
	 * Schedule the hourly stats recalculation cron job
	 *
	 * Should be called on plugin activation.
	 *
	 * @since 1.0.0
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the stats recalculation cron job
	 *
	 * Should be called on plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public static function unschedule_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Cron callback to recalculate overview stats
	 *
	 * Called hourly by WP-Cron.
	 *
	 * @since 1.0.0
	 */
	public static function cron_recalculate_stats() {
		$service = new self();
		$service->calculate_and_cache_overview_stats();
	}

	/**
	 * Get quiz performance data for reports
	 *
	 * Returns per-quiz statistics with pagination and filtering.
	 *
	 * @since 1.0.0
	 * @since 3.2.0 A scoped user also gets quizzes where users added by
	 *              pressprimer_quiz_report_visible_user_ids have attempts,
	 *              counting only the attempts they see.
	 *
	 * @param array $args Query arguments.
	 * @return array Quiz performance data with pagination info.
	 */
	public function get_quiz_performance( $args = [] ) {
		global $wpdb;

		$defaults = [
			'date_from' => null,
			'date_to'   => null,
			'search'    => '',
			'orderby'   => 'attempts',
			'order'     => 'DESC',
			'per_page'  => 20,
			'page'      => 1,
			'owner_id'  => null,
		];

		$args = wp_parse_args( $args, $defaults );

		$args['per_page'] = max( 1, absint( $args['per_page'] ) );
		$args['page']     = max( 1, absint( $args['page'] ) );

		$quizzes_table  = $wpdb->prefix . 'ppq_quizzes';
		$attempts_table = $wpdb->prefix . 'ppq_attempts';

		$user_ids = self::get_visible_user_ids( $args['owner_id'], 'quiz_performance' );

		$where = [ "q.status = 'published'" ];

		// Owner filtering (and quizzes where the user sees attempts)
		if ( $args['owner_id'] ) {
			$where[] = self::quiz_scope_sql( $args['owner_id'], $user_ids );
		}

		// Search
		if ( ! empty( $args['search'] ) ) {
			$where[] = $wpdb->prepare( 'q.title LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		}

		$where_sql = implode( ' AND ', $where );

		// Build date filtering for attempts. Reports-screen aggregates are
		// grade-bearing and exclude practice attempts (v3.1 feature 001
		// FR-005 / SCOPE Q7); the mixed-era columns below let the UI show a
		// notice when a quiz has both practice and graded history. A scoped
		// user counts only the attempts they see.
		$visible    = $args['owner_id'] ? ' AND ' . self::attempt_scope_sql( $args['owner_id'], $user_ids ) : '';
		$date_where = "a.status = 'submitted' AND a.is_practice = 0{$visible}";
		if ( $args['date_from'] ) {
			$date_where .= $wpdb->prepare( ' AND a.finished_at >= %s', $args['date_from'] );
		}
		if ( $args['date_to'] ) {
			// Append end of day time to include the entire end date
			$date_where .= $wpdb->prepare( ' AND a.finished_at <= %s', $args['date_to'] . ' 23:59:59' );
		}

		// Whitelisted sort columns: [ table alias, column ]; '' for a select alias.
		$allowed_orderby = [
			'title'     => [ 'q', 'title' ],
			'attempts'  => [ '', 'attempts' ],
			'avg_score' => [ '', 'avg_score' ],
			'pass_rate' => [ '', 'pass_rate' ],
			'avg_time'  => [ '', 'avg_time' ],
		];

		$orderby   = isset( $allowed_orderby[ $args['orderby'] ] ) ? $allowed_orderby[ $args['orderby'] ] : $allowed_orderby['attempts'];
		$is_asc    = 'ASC' === strtoupper( (string) $args['order'] );
		$order_sql = self::order_by_sql( $orderby[0], $orderby[1], $is_asc, 'q.id' );

		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		// Main query
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from $wpdb->prefix; clauses prepared or whitelisted above.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic report queries with pagination not suitable for caching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					q.id,
					q.title,
					COUNT(CASE WHEN {$date_where} THEN a.id END) as attempts,
					ROUND(AVG(CASE WHEN {$date_where} THEN a.score_percent END), 1) as avg_score,
					ROUND(
						(SUM(CASE WHEN {$date_where} AND a.passed = 1 THEN 1 ELSE 0 END) /
						 NULLIF(COUNT(CASE WHEN {$date_where} THEN a.id END), 0)) * 100,
					1) as pass_rate,
					ROUND(AVG(CASE WHEN {$date_where} THEN a.elapsed_ms END) / 1000) as avg_time,
					MAX(CASE WHEN a.status = 'submitted' AND a.is_practice = 1{$visible} THEN 1 ELSE 0 END) as has_practice_attempts,
					MAX(CASE WHEN a.status = 'submitted' AND a.is_practice = 0{$visible} THEN 1 ELSE 0 END) as has_graded_attempts
				 FROM {$quizzes_table} q
				 LEFT JOIN {$attempts_table} a ON q.id = a.quiz_id
				 WHERE {$where_sql}
				 GROUP BY q.id
				 {$order_sql}
				 LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and validated clauses safely constructed
				$args['per_page'],
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Get total count
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic report queries
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$quizzes_table} q WHERE {$where_sql}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return [
			'items'       => $results,
			'total'       => $total,
			'total_pages' => ceil( $total / $args['per_page'] ),
			'page'        => $args['page'],
		];
	}

	/**
	 * Default arguments of the attempts list and export
	 *
	 * @since 3.2.0
	 *
	 * @return array Defaults.
	 */
	private static function attempts_defaults() {
		return [
			'quiz_id'   => null,
			'user_id'   => null,
			'passed'    => null, // null = all, 1 = passed, 0 = failed
			'practice'  => null, // null/'show' = include practice attempts, 'hide' = exclude
			'date_from' => null,
			'date_to'   => null,
			'search'    => '',
			'orderby'   => 'finished_at',
			'order'     => 'DESC',
			'per_page'  => 20,
			'page'      => 1,
			'owner_id'  => null,
		];
	}

	/**
	 * The attempts query the list and the CSV export share
	 *
	 * Same tables, filters, scope and order, so the two can't drift.
	 *
	 * @since 3.2.0
	 *
	 * @param array  $args    Parsed arguments (attempts_defaults()).
	 * @param string $context 'attempts_list' or 'attempts_export'.
	 * @return array { select, from, order }: the shared columns, the FROM ...
	 *               WHERE clause, and the ORDER BY clause.
	 */
	private function attempts_query( array $args, $context ) {
		global $wpdb;

		$attempts_table = $wpdb->prefix . 'ppq_attempts';
		$quizzes_table  = $wpdb->prefix . 'ppq_quizzes';
		$users_table    = $wpdb->users;

		// Hidden container quizzes (spaced-repetition review and study-plan
		// containers) never surface on admin activity lists — the containers
		// themselves are invisible everywhere in wp-admin, so their attempts
		// must not leak their titles into Recent Activity / Recent Attempts.
		// Students still see these results on the front end (My Results uses
		// its own query).
		$where = [ "a.status = 'submitted'", 'q.is_review_quiz = 0' ];

		if ( $args['quiz_id'] ) {
			$where[] = $wpdb->prepare( 'a.quiz_id = %d', $args['quiz_id'] );
		}

		if ( $args['user_id'] ) {
			$where[] = $wpdb->prepare( 'a.user_id = %d', $args['user_id'] );
		}

		if ( null !== $args['passed'] ) {
			$where[] = $wpdb->prepare( 'a.passed = %d', $args['passed'] );
		}

		// Attempt lists include practice attempts (badged in the UI) by
		// default; 'hide' excludes them (v3.1 feature 001 FR-005).
		if ( isset( $args['practice'] ) && 'hide' === $args['practice'] ) {
			$where[] = 'a.is_practice = 0';
		}

		if ( $args['date_from'] ) {
			$where[] = $wpdb->prepare( 'a.finished_at >= %s', $args['date_from'] );
		}

		if ( $args['date_to'] ) {
			// Append end of day time to include the entire end date
			$where[] = $wpdb->prepare( 'a.finished_at <= %s', $args['date_to'] . ' 23:59:59' );
		}

		// A scoped user: attempts on their quizzes, plus their students' anywhere.
		if ( $args['owner_id'] ) {
			$where[] = self::attempt_scope_sql( $args['owner_id'], self::get_visible_user_ids( $args['owner_id'], $context ) );
		}

		if ( ! empty( $args['search'] ) ) {
			$search_like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]     = $wpdb->prepare(
				'(u.display_name LIKE %s OR u.user_email LIKE %s OR a.guest_email LIKE %s OR a.guest_name LIKE %s)',
				$search_like,
				$search_like,
				$search_like,
				$search_like
			);
		}

		// Whitelisted sort columns: [ table alias, column ]; '' for a select alias.
		$allowed_orderby = [
			'finished_at'   => [ 'a', 'finished_at' ],
			'score_percent' => [ 'a', 'score_percent' ],
			'elapsed_ms'    => [ 'a', 'elapsed_ms' ],
			'quiz_title'    => [ 'q', 'title' ],
			'student_name'  => [ '', 'student_name' ],
		];

		$orderby = isset( $allowed_orderby[ $args['orderby'] ] ) ? $allowed_orderby[ $args['orderby'] ] : $allowed_orderby['finished_at'];
		$is_asc  = 'ASC' === strtoupper( (string) $args['order'] );

		return [
			'select' => "a.id,
				a.quiz_id,
				a.user_id,
				a.guest_email,
				a.guest_name,
				a.guest_consent,
				a.guest_consent_at,
				a.score_points,
				a.max_points,
				a.score_percent,
				a.passed,
				a.is_practice,
				a.started_at,
				a.finished_at,
				a.elapsed_ms,
				q.title as quiz_title,
				q.pass_percent as quiz_pass_percent,
				q.owner_id as quiz_owner_id,
				u.ID as wp_user_id,
				u.user_email,
				COALESCE(u.display_name, NULLIF(a.guest_name, ''), NULLIF(a.guest_email, '')) as student_name",
			'from'   => "FROM {$attempts_table} a
				 INNER JOIN {$quizzes_table} q ON a.quiz_id = q.id
				 LEFT JOIN {$users_table} u ON a.user_id = u.ID
				 WHERE " . implode( ' AND ', $where ),
			'order'  => self::order_by_sql( $orderby[0], $orderby[1], $is_asc, 'a.id' ),
		];
	}

	/**
	 * Get recent attempts for reports
	 *
	 * Returns individual attempt records with pagination and filtering.
	 *
	 * @since 1.0.0
	 * @since 3.2.0 Shares its query with export_attempts_csv(); applies
	 *              pressprimer_quiz_report_visible_user_ids ('attempts_list');
	 *              guest marketing consent only on the viewer's own quizzes
	 *              for scoped users; adds guest_name and max_points.
	 *
	 * @param array $args Query arguments.
	 * @return array Attempts data with pagination info.
	 */
	public function get_recent_attempts( $args = [] ) {
		global $wpdb;

		$args = wp_parse_args( $args, self::attempts_defaults() );

		$args['per_page'] = max( 1, absint( $args['per_page'] ) );
		$args['page']     = max( 1, absint( $args['page'] ) );

		$query  = $this->attempts_query( $args, 'attempts_list' );
		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		$attempt_items_table = $wpdb->prefix . 'ppq_attempt_items';

		// Main query
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic report queries with pagination not suitable for caching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					{$query['select']},
					(SELECT COUNT(*) FROM {$attempt_items_table} ai WHERE ai.attempt_id = a.id) as total_questions,
					(SELECT COUNT(*) FROM {$attempt_items_table} ai WHERE ai.attempt_id = a.id AND ai.is_correct = 1) as correct_questions
				 {$query['from']}
				 {$query['order']}
				 LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and validated clauses safely constructed
				$args['per_page'],
				$offset
			)
		);

		// Get total count
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic report queries
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) {$query['from']}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and validated clauses safely constructed
		);

		foreach ( (array) $results as $row ) {
			$row->student_name = self::student_label( $row );

			if ( ! self::can_see_consent( $args['owner_id'], $row->quiz_owner_id ) ) {
				unset( $row->guest_consent, $row->guest_consent_at );
			}

			unset( $row->quiz_owner_id, $row->wp_user_id );
		}

		return [
			'items'       => $results,
			'total'       => $total,
			'total_pages' => ceil( $total / $args['per_page'] ),
			'page'        => $args['page'],
		];
	}

	/**
	 * Export the attempts list as CSV
	 *
	 * The same rows, filters, scope and order as get_recent_attempts(),
	 * without paging, as a { filename, content, type } download.
	 *
	 * @since 3.2.0
	 *
	 * @param array $args The list's arguments (page and per_page are ignored).
	 * @return array|WP_Error The download, or too_many_rows (413) past the cap.
	 */
	public function export_attempts_csv( $args = [] ) {
		global $wpdb;

		$args  = wp_parse_args( $args, self::attempts_defaults() );
		$query = $this->attempts_query( $args, 'attempts_export' );

		/**
		 * Filters the most attempts one CSV export may hold.
		 *
		 * @since 3.2.0
		 *
		 * @param int $max_rows Most rows (default 10,000).
		 */
		$max_rows = max( 1, (int) apply_filters( 'pressprimer_quiz_attempts_csv_max_rows', self::CSV_MAX_ROWS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic report query
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) {$query['from']}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and validated clauses safely constructed
		);

		if ( $total > $max_rows ) {
			return new WP_Error(
				'too_many_rows',
				sprintf(
					/* translators: %s: most attempts one export can hold (e.g. 10,000). */
					__( 'This export has more than %s attempts. Narrow the date range or pick one quiz.', 'pressprimer-quiz' ),
					number_format_i18n( $max_rows )
				),
				[
					'status'   => 413,
					'total'    => $total,
					'max_rows' => $max_rows,
				]
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic report query
		$attempts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$query['select']}
				 {$query['from']}
				 {$query['order']}
				 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and validated clauses safely constructed
				$max_rows
			)
		);
		$attempts = (array) $attempts;

		// Consent columns when the viewer may see consent on any row.
		$show_consent = ! $args['owner_id'];
		foreach ( $attempts as $attempt ) {
			if ( self::can_see_consent( $args['owner_id'], $attempt->quiz_owner_id ) ) {
				$show_consent = true;
				break;
			}
		}

		// Stored dates are site local time; the headers name the timezone.
		$timezone = wp_timezone_string();
		$columns  = [
			'attempt_id'    => __( 'Attempt ID', 'pressprimer-quiz' ),
			'quiz_id'       => __( 'Quiz ID', 'pressprimer-quiz' ),
			'quiz'          => __( 'Quiz', 'pressprimer-quiz' ),
			'student'       => __( 'Student', 'pressprimer-quiz' ),
			'email'         => __( 'Email', 'pressprimer-quiz' ),
			'user_id'       => __( 'User ID', 'pressprimer-quiz' ),
			/* translators: %s: the site's timezone (e.g. Europe/London). */
			'started_at'    => sprintf( __( 'Started (site time, %s)', 'pressprimer-quiz' ), $timezone ),
			/* translators: %s: the site's timezone (e.g. Europe/London). */
			'finished_at'   => sprintf( __( 'Finished (site time, %s)', 'pressprimer-quiz' ), $timezone ),
			'duration'      => __( 'Duration (seconds)', 'pressprimer-quiz' ),
			'score_points'  => __( 'Score (points)', 'pressprimer-quiz' ),
			'max_points'    => __( 'Max points', 'pressprimer-quiz' ),
			'score_percent' => __( 'Score (%)', 'pressprimer-quiz' ),
			'pass_percent'  => __( 'Pass mark (%)', 'pressprimer-quiz' ),
			'result'        => __( 'Result', 'pressprimer-quiz' ),
			'practice'      => __( 'Practice', 'pressprimer-quiz' ),
		];

		if ( $show_consent ) {
			$columns['consent'] = __( 'Marketing consent', 'pressprimer-quiz' );
			/* translators: %s: the site's timezone (e.g. Europe/London). */
			$columns['consent_at'] = sprintf( __( 'Consent date (site time, %s)', 'pressprimer-quiz' ), $timezone );
		}

		/**
		 * Filters the attempts CSV columns.
		 *
		 * Keys match the row keys of pressprimer_quiz_attempts_csv_row; values
		 * are the header labels. Add a key here and its value in the row
		 * filter to add a column.
		 *
		 * @since 3.2.0
		 *
		 * @param array  $columns Header labels by key.
		 * @param string $context 'attempts_export'.
		 */
		$columns = (array) apply_filters( 'pressprimer_quiz_attempts_csv_columns', $columns, 'attempts_export' );

		$rows = [];
		foreach ( $attempts as $attempt ) {
			$consent_visible = self::can_see_consent( $args['owner_id'], $attempt->quiz_owner_id );
			$pass_percent    = ( null === $attempt->quiz_pass_percent || '' === $attempt->quiz_pass_percent ) ? 70 : (float) $attempt->quiz_pass_percent;

			if ( ! empty( $attempt->wp_user_id ) ) {
				$student = (string) $attempt->student_name;
			} elseif ( ! empty( $attempt->user_id ) ) {
				/* translators: %d: ID of a user account that no longer exists. */
				$student = sprintf( __( 'Deleted user #%d', 'pressprimer-quiz' ), (int) $attempt->user_id );
			} elseif ( '' !== (string) $attempt->guest_name ) {
				$student = (string) $attempt->guest_name;
			} else {
				$student = __( 'Guest', 'pressprimer-quiz' );
			}

			$row = [
				'attempt_id'    => (int) $attempt->id,
				'quiz_id'       => (int) $attempt->quiz_id,
				'quiz'          => (string) $attempt->quiz_title,
				'student'       => $student,
				'email'         => ! empty( $attempt->user_email ) ? $attempt->user_email : (string) $attempt->guest_email,
				'user_id'       => ! empty( $attempt->user_id ) ? (int) $attempt->user_id : '',
				'started_at'    => (string) $attempt->started_at,
				'finished_at'   => (string) $attempt->finished_at,
				'duration'      => null === $attempt->elapsed_ms ? '' : (int) round( $attempt->elapsed_ms / 1000 ),
				'score_points'  => null === $attempt->score_points ? '' : (float) $attempt->score_points,
				'max_points'    => null === $attempt->max_points ? '' : (float) $attempt->max_points,
				'score_percent' => null === $attempt->score_percent ? '' : (float) $attempt->score_percent,
				'pass_percent'  => $pass_percent,
				'result'        => $attempt->passed ? __( 'Passed', 'pressprimer-quiz' ) : __( 'Failed', 'pressprimer-quiz' ),
				'practice'      => $attempt->is_practice ? __( 'Yes', 'pressprimer-quiz' ) : __( 'No', 'pressprimer-quiz' ),
				'consent'       => '',
				'consent_at'    => '',
			];

			if ( $consent_visible && null !== $attempt->guest_consent ) {
				$row['consent']    = $attempt->guest_consent ? __( 'Yes', 'pressprimer-quiz' ) : __( 'No', 'pressprimer-quiz' );
				$row['consent_at'] = (string) $attempt->guest_consent_at;
			}

			/**
			 * Filters one attempts CSV row.
			 *
			 * @since 3.2.0
			 *
			 * @param array  $row     Cells by column key.
			 * @param object $attempt The attempt row (the list's columns).
			 * @param string $context 'attempts_export'.
			 */
			$row = (array) apply_filters( 'pressprimer_quiz_attempts_csv_row', $row, $attempt, 'attempts_export' );

			$cells = [];
			foreach ( array_keys( $columns ) as $key ) {
				$cells[] = isset( $row[ $key ] ) ? $row[ $key ] : '';
			}
			$rows[] = $cells;
		}

		// attempts-{quiz}-{date}.csv for one quiz, attempts-{date}.csv otherwise.
		// A title sanitize_title() can't reduce to Latin letters gives quiz-{id}.
		$context = '';
		if ( $args['quiz_id'] ) {
			$quiz    = PressPrimer_Quiz_Quiz::get( absint( $args['quiz_id'] ) );
			$slug    = $quiz ? sanitize_title( $quiz->title ) : '';
			$context = ( '' !== $slug && false === strpos( $slug, '%' ) ) ? $slug : 'quiz-' . absint( $args['quiz_id'] );
		}

		return PressPrimer_Quiz_CSV_Service::response(
			PressPrimer_Quiz_CSV_Service::filename( 'attempts', $context ),
			PressPrimer_Quiz_CSV_Service::build( array_values( $columns ), $rows )
		);
	}

	/**
	 * Get attempt detail for modal
	 *
	 * Returns detailed information about a specific attempt including
	 * per-question breakdown.
	 *
	 * @since 1.0.0
	 * @since 3.2.0 Only submitted attempts on quizzes that aren't review
	 *              containers. Scoped users get the attempts the list shows
	 *              (pressprimer_quiz_report_visible_user_ids). Adds is_practice,
	 *              status, max_points, guest_name and can_edit; per question
	 *              its type, max points, confidence (when the quiz collects it)
	 *              and the feedback the student was shown; the answers the
	 *              student saw, in their order; and leaves out questions the
	 *              student never saw (get_result_items()).
	 *
	 * @param int      $attempt_id Attempt ID.
	 * @param int|null $owner_id   Optional. The scoped viewer (null: no scope).
	 * @return array|null Attempt details or null if not found/not permitted.
	 */
	public function get_attempt_detail( $attempt_id, $owner_id = null ) {
		global $wpdb;

		$attempts_table      = $wpdb->prefix . 'ppq_attempts';
		$attempt_items_table = $wpdb->prefix . 'ppq_attempt_items';
		$quizzes_table       = $wpdb->prefix . 'ppq_quizzes';
		$questions_table     = $wpdb->prefix . 'ppq_questions';
		$revisions_table     = $wpdb->prefix . 'ppq_question_revisions';
		$users_table         = $wpdb->users;

		// Submitted attempts on real quizzes, as the list shows them.
		$where = [
			$wpdb->prepare( 'a.id = %d', $attempt_id ),
			"a.status = 'submitted'",
			'q.is_review_quiz = 0',
		];

		if ( $owner_id ) {
			$where[] = self::attempt_scope_sql( $owner_id, self::get_visible_user_ids( $owner_id, 'attempt_detail' ) );
		}

		$where_sql = implode( ' AND ', $where );

		// Get attempt
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Single attempt detail lookup with dynamic ID, not suitable for caching
		$attempt = $wpdb->get_row(
			"SELECT
				a.id,
				a.quiz_id,
				a.user_id,
				a.guest_email,
				a.guest_name,
				a.guest_consent,
				a.guest_consent_at,
				a.score_points,
				a.max_points,
				a.score_percent,
				a.passed,
				a.is_practice,
				a.status,
				a.started_at,
				a.finished_at,
				a.elapsed_ms,
				q.title as quiz_title,
				q.pass_percent as quiz_pass_percent,
				q.owner_id as quiz_owner_id,
				q.enable_confidence,
				u.ID as wp_user_id,
				u.user_email,
				COALESCE(u.display_name, NULLIF(a.guest_name, ''), NULLIF(a.guest_email, '')) as student_name
			 FROM {$attempts_table} a
			 INNER JOIN {$quizzes_table} q ON a.quiz_id = q.id
			 LEFT JOIN {$users_table} u ON a.user_id = u.ID
			 WHERE {$where_sql}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and prepared clauses safely constructed
		);

		if ( ! $attempt ) {
			return null;
		}

		// Get attempt items (questions) with full revision data
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Attempt items for specific attempt, not suitable for caching
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ai.id,
					ai.question_revision_id,
					ai.selected_answers_json,
					ai.answer_order_json,
					ai.is_correct,
					ai.confidence,
					ai.score_points as points_earned,
					ai.time_spent_ms,
					qr.question_id,
					qr.stem,
					qr.answers_json,
					qr.feedback_correct,
					qr.feedback_incorrect,
					qq.type,
					qq.max_points
				 FROM {$attempt_items_table} ai
				 LEFT JOIN {$revisions_table} qr ON ai.question_revision_id = qr.id
				 LEFT JOIN {$questions_table} qq ON qr.question_id = qq.id
				 WHERE ai.attempt_id = %d
				 ORDER BY ai.order_index ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names safely constructed from $wpdb->prefix
				$attempt_id
			)
		);

		// Only the questions the student's results are built from: addons
		// leave out the ones the student never saw (Enterprise branching).
		$attempt_model = class_exists( 'PressPrimer_Quiz_Attempt' ) ? PressPrimer_Quiz_Attempt::get( (int) $attempt->id ) : null;
		if ( $attempt_model ) {
			$items_by_id = [];
			foreach ( (array) $items as $item ) {
				$items_by_id[ (int) $item->id ] = $item;
			}

			$items = [];
			foreach ( $attempt_model->get_result_items() as $result_item ) {
				if ( isset( $items_by_id[ (int) $result_item->id ] ) ) {
					$items[] = $items_by_id[ (int) $result_item->id ];
				}
			}
		}

		$collects_confidence = ! empty( $attempt->enable_confidence );

		// Format items with answer details
		$formatted_items = [];
		foreach ( (array) $items as $item ) {
			$answers          = json_decode( $item->answers_json ?? '[]', true ) ?: [];
			$selected_indexes = array_map( 'intval', (array) ( json_decode( $item->selected_answers_json ?? '[]', true ) ?: [] ) );

			// The answers the student saw, in their order (answer_order_json);
			// every answer when none was stored.
			$shown = json_decode( $item->answer_order_json ?? '', true );
			$shown = is_array( $shown ) ? array_values( array_intersect( array_map( 'intval', $shown ), array_keys( $answers ) ) ) : [];
			if ( empty( $shown ) ) {
				$shown = array_keys( $answers );
			}

			// Build answer display data. Stem and answer text pass through
			// wp_kses_post() so embedded <img> tags from the v2.3 image
			// support feature survive into the Attempt Details modal. The
			// React side renders this via dangerouslySetInnerHTML.
			$answer_options = [];
			foreach ( $shown as $idx ) {
				$answer       = is_array( $answers[ $idx ] ) ? $answers[ $idx ] : [];
				$was_selected = in_array( $idx, $selected_indexes, true );

				$answer_options[] = [
					'index'        => $idx,
					'text'         => wp_kses_post( $answer['text'] ?? '' ),
					'is_correct'   => (bool) ( $answer['is_correct'] ?? false ),
					'was_selected' => $was_selected,
					// Per-answer feedback is shown for the answers the student chose.
					'feedback'     => $was_selected && ! empty( $answer['feedback'] ) ? wp_kses_post( $answer['feedback'] ) : '',
				];
			}

			// The question's feedback the student was shown.
			$feedback = $item->is_correct ? $item->feedback_correct : $item->feedback_incorrect;

			$formatted = [
				'id'            => (int) $item->id,
				'question_id'   => (int) $item->question_id,
				'type'          => (string) ( $item->type ?? '' ),
				'stem'          => wp_kses_post( $item->stem ?? '' ),
				'is_correct'    => (bool) $item->is_correct,
				'points_earned' => (float) ( $item->points_earned ?? 0 ),
				'max_points'    => null === $item->max_points ? null : (float) $item->max_points,
				'time_spent_ms' => (int) ( $item->time_spent_ms ?? 0 ),
				'feedback'      => $feedback ? wp_kses_post( $feedback ) : '',
				'answers'       => $answer_options,
			];

			// 1 low, 2 medium, 3 high; null when not given.
			if ( $collects_confidence ) {
				$formatted['confidence'] = in_array( (int) $item->confidence, [ 1, 2, 3 ], true ) ? (int) $item->confidence : null;
			}

			$formatted_items[] = $formatted;
		}

		// Handle pass_percent - use 70% default if NULL
		$pass_percent = $attempt->quiz_pass_percent;
		if ( $pass_percent === null || $pass_percent === '' ) {
			$pass_percent = 70.0; // Default passing score
		}

		$current_user_id = get_current_user_id();

		$data = [
			'id'                => (int) $attempt->id,
			'quiz_id'           => (int) $attempt->quiz_id,
			'quiz_title'        => $attempt->quiz_title,
			'quiz_pass_percent' => (float) $pass_percent,
			'user_id'           => $attempt->user_id ? (int) $attempt->user_id : null,
			'student_name'      => self::student_label( $attempt ),
			'student_email'     => $attempt->user_email ?? $attempt->guest_email ?? '',
			'guest_name'        => '' !== (string) $attempt->guest_name ? (string) $attempt->guest_name : null,
			'guest_consent'     => is_null( $attempt->guest_consent ) ? null : (int) $attempt->guest_consent,
			'guest_consent_at'  => $attempt->guest_consent_at,
			'score_points'      => (float) $attempt->score_points,
			'max_points'        => null === $attempt->max_points ? null : (float) $attempt->max_points,
			'score_percent'     => (float) $attempt->score_percent,
			'passed'            => (bool) $attempt->passed,
			'is_practice'       => (bool) $attempt->is_practice,
			'status'            => (string) $attempt->status,
			'started_at'        => $attempt->started_at,
			'finished_at'       => $attempt->finished_at,
			'elapsed_ms'        => (int) $attempt->elapsed_ms,
			// Whether the viewer may edit the quiz (its owner, or manage_all).
			'can_edit'          => current_user_can( 'pressprimer_quiz_manage_all' ) || ( $current_user_id && absint( $attempt->quiz_owner_id ) === $current_user_id ),
			'items'             => $formatted_items,
		];

		// Consent only on the viewer's own quizzes, unless they see all results.
		if ( ! self::can_see_consent( $owner_id, $attempt->quiz_owner_id ) ) {
			unset( $data['guest_consent'], $data['guest_consent_at'] );
		}

		/**
		 * Filters an attempt's detail data for reports.
		 *
		 * @since 3.2.0
		 *
		 * @param array                           $data    The detail data.
		 * @param PressPrimer_Quiz_Attempt|object $attempt The attempt (the model when it loads).
		 * @param string                          $context 'attempt_detail'.
		 */
		return apply_filters( 'pressprimer_quiz_attempt_detail_data', $data, $attempt_model ? $attempt_model : $attempt, 'attempt_detail' );
	}

	/**
	 * Get activity chart data
	 *
	 * Returns daily completions and average scores for the dashboard chart.
	 * Data is cached using transients for performance.
	 *
	 * @since 1.0.0
	 *
	 * @since 3.2.0 A scoped user's chart includes the attempts of the users
	 *              pressprimer_quiz_report_visible_user_ids adds.
	 *
	 * @param array $args Query arguments.
	 * @return array Chart data with dates, completions, and average scores.
	 */
	public function get_activity_chart_data( $args = [] ) {
		global $wpdb;

		$defaults = [
			'days'     => 90,
			'owner_id' => null,
		];

		$args = wp_parse_args( $args, $defaults );

		// Limit to 2 years max
		$args['days'] = min( $args['days'], 730 );

		// Part of the cache key, so each visible set gets its own chart.
		$args['user_ids'] = self::get_visible_user_ids( $args['owner_id'], 'activity_chart' );

		// Generate cache key
		$cache_key = 'pressprimer_quiz_activity_chart_' . md5( wp_json_encode( $args ) );

		// Check cache (15 minutes)
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$attempts_table = $wpdb->prefix . 'ppq_attempts';
		$quizzes_table  = $wpdb->prefix . 'ppq_quizzes';

		// Calculate date range
		$end_date   = gmdate( 'Y-m-d' );
		$start_date = gmdate( 'Y-m-d', strtotime( "-{$args['days']} days" ) );

		$where = [
			"a.status = 'submitted'",
			$wpdb->prepare( 'DATE(a.finished_at) >= %s', $start_date ),
			$wpdb->prepare( 'DATE(a.finished_at) <= %s', $end_date ),
		];

		if ( $args['owner_id'] ) {
			$where[] = self::attempt_scope_sql( $args['owner_id'], $args['user_ids'] );
		}

		$where_sql = implode( ' AND ', $where );

		// Query for daily aggregates
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uses transient caching above for performance
		$results = $wpdb->get_results(
			"SELECT
				DATE(a.finished_at) as date,
				COUNT(*) as completions,
				ROUND(AVG(CASE WHEN a.is_practice = 0 THEN a.score_percent END), 1) as avg_score
			 FROM {$attempts_table} a
			 INNER JOIN {$quizzes_table} q ON a.quiz_id = q.id
			 WHERE {$where_sql}
			 GROUP BY DATE(a.finished_at)
			 ORDER BY date ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and validated clauses safely constructed
		);

		// Build a complete date range with zeros for missing days
		$data    = [];
		$current = new DateTime( $start_date );
		$end     = new DateTime( $end_date );

		// Index results by date for quick lookup
		$results_by_date = [];
		foreach ( $results as $row ) {
			$results_by_date[ $row->date ] = $row;
		}

		// Fill in all dates
		while ( $current <= $end ) {
			$date_str = $current->format( 'Y-m-d' );
			$data[]   = [
				'date'        => $date_str,
				'completions' => isset( $results_by_date[ $date_str ] ) ? (int) $results_by_date[ $date_str ]->completions : 0,
				'avg_score'   => isset( $results_by_date[ $date_str ] ) ? (float) $results_by_date[ $date_str ]->avg_score : null,
			];
			$current->modify( '+1 day' );
		}

		$result = [
			'data'       => $data,
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'days'       => $args['days'],
		];

		// Cache for 15 minutes
		set_transient( $cache_key, $result, 15 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Clear activity chart transient cache
	 *
	 * Deletes all activity chart transients so the next request
	 * fetches fresh data from the database. Called when a new
	 * quiz attempt is submitted.
	 *
	 * @since 2.1.0
	 */
	public static function clear_activity_chart_cache() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deleting transients by prefix
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_pressprimer\_quiz\_activity\_chart\_%'
			    OR option_name LIKE '_transient_timeout_pressprimer\_quiz\_activity\_chart\_%'"
		);
	}

	/**
	 * Get list of quizzes for filter dropdown
	 *
	 * Returns a simple list of quizzes for use in report filters.
	 *
	 * @since 1.0.0
	 * @since 3.2.0 Leaves out review containers; a scoped user also gets
	 *              quizzes where users pressprimer_quiz_report_visible_user_ids
	 *              adds have attempts.
	 *
	 * @param int|null $owner_id Optional. Limit to specific owner.
	 * @return array List of quizzes with id and title.
	 */
	public function get_quiz_filter_options( $owner_id = null ) {
		global $wpdb;

		$quizzes_table = $wpdb->prefix . 'ppq_quizzes';

		// Review containers never appear in wp-admin reports.
		$where = [ "q.status = 'published'", 'q.is_review_quiz = 0' ];

		if ( $owner_id ) {
			$where[] = self::quiz_scope_sql( $owner_id, self::get_visible_user_ids( $owner_id, 'quiz_options' ) );
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Filter options for the report screen
		return $wpdb->get_results(
			"SELECT q.id, q.title FROM {$quizzes_table} q WHERE {$where_sql} ORDER BY q.title ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
