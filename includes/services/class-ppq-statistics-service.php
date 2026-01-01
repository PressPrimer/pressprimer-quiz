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
	const CACHE_GROUP = 'ppq_statistics';

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
	 * Get dashboard statistics
	 *
	 * Returns summary statistics for the plugin's Dashboard page.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $owner_id Optional. Limit stats to specific owner. Default null for all.
	 * @return array Statistics array.
	 */
	public function get_dashboard_stats( $owner_id = null ) {
		$cache_key = 'dashboard_stats_' . ( $owner_id ? $owner_id : 'all' );
		$stats     = wp_cache_get( $cache_key, self::CACHE_GROUP );

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

		// Build owner restriction if needed
		$owner_where = '';
		if ( $owner_id ) {
			$owner_where = $wpdb->prepare( ' AND owner_id = %d', $owner_id );
		}

		// Total published quizzes
		$stats['total_quizzes'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$quizzes_table} WHERE status = 'published'{$owner_where}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name safely constructed; results cached at method level
		);

		// Total active questions
		$stats['total_questions'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$questions_table} WHERE deleted_at IS NULL{$owner_where}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name safely constructed; results cached at method level
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
					 WHERE a.status = 'submitted' AND a.finished_at >= %s AND q.owner_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names safely constructed from $wpdb->prefix
					$seven_days_ago,
					$owner_id
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Results cached at method level

			// Questions answered in last 7 days
			$stats['questions_answered'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(ai.id) FROM {$attempt_items_table} ai
					 INNER JOIN {$attempts_table} a ON ai.attempt_id = a.id
					 INNER JOIN {$quizzes_table} q ON a.quiz_id = q.id
					 WHERE a.status = 'submitted' AND a.finished_at >= %s AND q.owner_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names safely constructed from $wpdb->prefix
					$seven_days_ago,
					$owner_id
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
					 WHERE a.status = 'submitted' AND a.finished_at >= %s AND q.owner_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names safely constructed from $wpdb->prefix
					$seven_days_ago,
					$owner_id
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Results cached at method level
		} else {
			$pass_data = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						COUNT(*) as total,
						SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed
					 FROM {$attempts_table}
					 WHERE status = 'submitted' AND finished_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed from $wpdb->prefix
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
			$stats['popular_quizzes'] = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT q.id, q.title, COUNT(a.id) as attempt_count
					 FROM {$quizzes_table} q
					 LEFT JOIN {$attempts_table} a ON q.id = a.quiz_id
						AND a.status = 'submitted' AND a.finished_at >= %s
					 WHERE q.status = 'published' AND q.owner_id = %d
					 GROUP BY q.id
					 ORDER BY attempt_count DESC
					 LIMIT 5", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names safely constructed from $wpdb->prefix
					$thirty_days_ago,
					$owner_id
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Results cached at method level
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
		wp_cache_set( $cache_key, $stats, self::CACHE_GROUP, self::CACHE_EXPIRATION );

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
			return $this->calculate_overview_stats( $args['owner_id'] );
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
	 * @param int|null $owner_id Optional. Limit to specific owner.
	 * @return array Overview statistics.
	 */
	public function calculate_overview_stats( $owner_id = null ) {
		global $wpdb;

		$attempts_table = $wpdb->prefix . 'ppq_attempts';
		$quizzes_table  = $wpdb->prefix . 'ppq_quizzes';

		$where = [ "a.status = 'submitted'" ];

		if ( $owner_id ) {
			$where[] = $wpdb->prepare( 'q.owner_id = %d', $owner_id );
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

		$quizzes_table  = $wpdb->prefix . 'ppq_quizzes';
		$attempts_table = $wpdb->prefix . 'ppq_attempts';

		$where = [ "q.status = 'published'" ];

		// Owner filtering
		if ( $args['owner_id'] ) {
			$where[] = $wpdb->prepare( 'q.owner_id = %d', $args['owner_id'] );
		}

		// Search
		if ( ! empty( $args['search'] ) ) {
			$where[] = $wpdb->prepare( 'q.title LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		}

		$where_sql = implode( ' AND ', $where );

		// Build date filtering for attempts
		$date_where = "a.status = 'submitted'";
		if ( $args['date_from'] ) {
			$date_where .= $wpdb->prepare( ' AND a.finished_at >= %s', $args['date_from'] );
		}
		if ( $args['date_to'] ) {
			// Append end of day time to include the entire end date
			$date_where .= $wpdb->prepare( ' AND a.finished_at <= %s', $args['date_to'] . ' 23:59:59' );
		}

		// Validate orderby
		$allowed_orderby = [
			'title'     => 'q.title',
			'attempts'  => 'attempts',
			'avg_score' => 'avg_score',
			'pass_rate' => 'pass_rate',
			'avg_time'  => 'avg_time',
		];

		$orderby_column = $allowed_orderby[ $args['orderby'] ] ?? 'attempts';
		$order          = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Build ORDER BY with sanitize_sql_orderby
		$order_sql = sanitize_sql_orderby( "{$orderby_column} {$order}" );
		$order_sql = $order_sql ? "ORDER BY {$order_sql}" : 'ORDER BY attempts DESC';

		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		// Main query
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
					ROUND(AVG(CASE WHEN {$date_where} THEN a.elapsed_ms END) / 1000) as avg_time
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
	 * Get recent attempts for reports
	 *
	 * Returns individual attempt records with pagination and filtering.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 * @return array Attempts data with pagination info.
	 */
	public function get_recent_attempts( $args = [] ) {
		global $wpdb;

		$defaults = [
			'quiz_id'   => null,
			'user_id'   => null,
			'passed'    => null, // null = all, 1 = passed, 0 = failed
			'date_from' => null,
			'date_to'   => null,
			'search'    => '',
			'orderby'   => 'finished_at',
			'order'     => 'DESC',
			'per_page'  => 20,
			'page'      => 1,
			'owner_id'  => null,
		];

		$args = wp_parse_args( $args, $defaults );

		$attempts_table = $wpdb->prefix . 'ppq_attempts';
		$quizzes_table  = $wpdb->prefix . 'ppq_quizzes';
		$users_table    = $wpdb->users;

		$where = [ "a.status = 'submitted'" ];

		if ( $args['quiz_id'] ) {
			$where[] = $wpdb->prepare( 'a.quiz_id = %d', $args['quiz_id'] );
		}

		if ( $args['user_id'] ) {
			$where[] = $wpdb->prepare( 'a.user_id = %d', $args['user_id'] );
		}

		if ( $args['passed'] !== null ) {
			$where[] = $wpdb->prepare( 'a.passed = %d', $args['passed'] );
		}

		if ( $args['date_from'] ) {
			$where[] = $wpdb->prepare( 'a.finished_at >= %s', $args['date_from'] );
		}

		if ( $args['date_to'] ) {
			// Append end of day time to include the entire end date
			$where[] = $wpdb->prepare( 'a.finished_at <= %s', $args['date_to'] . ' 23:59:59' );
		}

		if ( $args['owner_id'] ) {
			$where[] = $wpdb->prepare( 'q.owner_id = %d', $args['owner_id'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$search_like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]     = $wpdb->prepare(
				'(u.display_name LIKE %s OR u.user_email LIKE %s OR a.guest_email LIKE %s)',
				$search_like,
				$search_like,
				$search_like
			);
		}

		$where_sql = implode( ' AND ', $where );

		// Validate orderby
		$allowed_orderby = [
			'finished_at'   => 'a.finished_at',
			'score_percent' => 'a.score_percent',
			'elapsed_ms'    => 'a.elapsed_ms',
			'quiz_title'    => 'q.title',
			'student_name'  => 'student_name',
		];

		$orderby_column = $allowed_orderby[ $args['orderby'] ] ?? 'a.finished_at';
		$order          = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Build ORDER BY with sanitize_sql_orderby
		$order_sql = sanitize_sql_orderby( "{$orderby_column} {$order}" );
		$order_sql = $order_sql ? "ORDER BY {$order_sql}" : 'ORDER BY a.finished_at DESC';

		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		$attempt_items_table = $wpdb->prefix . 'ppq_attempt_items';

		// Main query
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic report queries with pagination not suitable for caching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					a.id,
					a.quiz_id,
					a.user_id,
					a.guest_email,
					a.score_points,
					a.score_percent,
					a.passed,
					a.started_at,
					a.finished_at,
					a.elapsed_ms,
					q.title as quiz_title,
					q.pass_percent as quiz_pass_percent,
					COALESCE(u.display_name, a.guest_email, 'Guest') as student_name,
					u.user_email,
					(SELECT COUNT(*) FROM {$attempt_items_table} ai WHERE ai.attempt_id = a.id) as total_questions,
					(SELECT COUNT(*) FROM {$attempt_items_table} ai WHERE ai.attempt_id = a.id AND ai.is_correct = 1) as correct_questions
				 FROM {$attempts_table} a
				 INNER JOIN {$quizzes_table} q ON a.quiz_id = q.id
				 LEFT JOIN {$users_table} u ON a.user_id = u.ID
				 WHERE {$where_sql}
				 {$order_sql}
				 LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and validated clauses safely constructed
				$args['per_page'],
				$offset
			)
		);

		// Get total count
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic report queries
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$attempts_table} a
			 INNER JOIN {$quizzes_table} q ON a.quiz_id = q.id
			 LEFT JOIN {$users_table} u ON a.user_id = u.ID
			 WHERE {$where_sql}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and validated clauses safely constructed
		);

		return [
			'items'       => $results,
			'total'       => $total,
			'total_pages' => ceil( $total / $args['per_page'] ),
			'page'        => $args['page'],
		];
	}

	/**
	 * Get attempt detail for modal
	 *
	 * Returns detailed information about a specific attempt including
	 * per-question breakdown.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $attempt_id Attempt ID.
	 * @param int|null $owner_id   Optional. Owner ID for permission check.
	 * @return array|null Attempt details or null if not found/not permitted.
	 */
	public function get_attempt_detail( $attempt_id, $owner_id = null ) {
		global $wpdb;

		$attempts_table      = $wpdb->prefix . 'ppq_attempts';
		$attempt_items_table = $wpdb->prefix . 'ppq_attempt_items';
		$quizzes_table       = $wpdb->prefix . 'ppq_quizzes';
		$questions_table     = $wpdb->prefix . 'ppq_questions';
		$users_table         = $wpdb->users;

		// Build query with optional owner check
		$where  = [ 'a.id = %d' ];
		$params = [ $attempt_id ];

		if ( $owner_id ) {
			$where[]  = 'q.owner_id = %d';
			$params[] = $owner_id;
		}

		$where_sql = implode( ' AND ', $where );

		// Get attempt
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Single attempt detail lookup with dynamic ID, not suitable for caching
		$attempt = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					a.id,
					a.quiz_id,
					a.user_id,
					a.guest_email,
					a.score_points,
					a.score_percent,
					a.passed,
					a.started_at,
					a.finished_at,
					a.elapsed_ms,
					q.title as quiz_title,
					q.pass_percent as quiz_pass_percent,
					COALESCE(u.display_name, a.guest_email, 'Guest') as student_name,
					u.user_email
				 FROM {$attempts_table} a
				 INNER JOIN {$quizzes_table} q ON a.quiz_id = q.id
				 LEFT JOIN {$users_table} u ON a.user_id = u.ID
				 WHERE {$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names and validated clauses safely constructed
				...$params
			)
		);

		if ( ! $attempt ) {
			return null;
		}

		$revisions_table = $wpdb->prefix . 'ppq_question_revisions';

		// Get attempt items (questions) with full revision data
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Attempt items for specific attempt, not suitable for caching
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ai.id,
					ai.question_revision_id,
					ai.selected_answers_json,
					ai.is_correct,
					ai.score_points as points_earned,
					ai.time_spent_ms,
					qr.question_id,
					qr.stem,
					qr.answers_json
				 FROM {$attempt_items_table} ai
				 LEFT JOIN {$revisions_table} qr ON ai.question_revision_id = qr.id
				 WHERE ai.attempt_id = %d
				 ORDER BY ai.order_index ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names safely constructed from $wpdb->prefix
				$attempt_id
			)
		);

		// Format items with answer details
		$formatted_items = [];
		foreach ( $items as $item ) {
			$answers          = json_decode( $item->answers_json ?? '[]', true ) ?: [];
			$selected_indexes = json_decode( $item->selected_answers_json ?? '[]', true ) ?: [];

			// Build answer display data
			$answer_options = [];
			foreach ( $answers as $idx => $answer ) {
				$answer_options[] = [
					'text'         => wp_strip_all_tags( $answer['text'] ?? '' ),
					'is_correct'   => (bool) ( $answer['correct'] ?? false ),
					'was_selected' => in_array( $idx, $selected_indexes, true ),
				];
			}

			$formatted_items[] = [
				'id'            => (int) $item->id,
				'question_id'   => (int) $item->question_id,
				'stem'          => wp_strip_all_tags( $item->stem ?? '' ),
				'is_correct'    => (bool) $item->is_correct,
				'points_earned' => (float) ( $item->points_earned ?? 0 ),
				'time_spent_ms' => (int) ( $item->time_spent_ms ?? 0 ),
				'answers'       => $answer_options,
			];
		}

		// Handle pass_percent - use 70% default if NULL
		$pass_percent = $attempt->quiz_pass_percent;
		if ( $pass_percent === null || $pass_percent === '' ) {
			$pass_percent = 70.0; // Default passing score
		}

		return [
			'id'                => (int) $attempt->id,
			'quiz_id'           => (int) $attempt->quiz_id,
			'quiz_title'        => $attempt->quiz_title,
			'quiz_pass_percent' => (float) $pass_percent,
			'user_id'           => $attempt->user_id ? (int) $attempt->user_id : null,
			'student_name'      => $attempt->student_name,
			'student_email'     => $attempt->user_email ?? $attempt->guest_email ?? '',
			'score_points'      => (float) $attempt->score_points,
			'score_percent'     => (float) $attempt->score_percent,
			'passed'            => (bool) $attempt->passed,
			'started_at'        => $attempt->started_at,
			'finished_at'       => $attempt->finished_at,
			'elapsed_ms'        => (int) $attempt->elapsed_ms,
			'items'             => $formatted_items,
		];
	}

	/**
	 * Get activity chart data
	 *
	 * Returns daily completions and average scores for the dashboard chart.
	 * Data is cached using transients for performance.
	 *
	 * @since 1.0.0
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

		// Generate cache key
		$cache_key = 'ppq_activity_chart_' . md5( wp_json_encode( $args ) );

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
			$where[] = $wpdb->prepare( 'q.owner_id = %d', $args['owner_id'] );
		}

		$where_sql = implode( ' AND ', $where );

		// Query for daily aggregates
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uses transient caching above for performance
		$results = $wpdb->get_results(
			"SELECT
				DATE(a.finished_at) as date,
				COUNT(*) as completions,
				ROUND(AVG(a.score_percent), 1) as avg_score
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
	 * Get list of quizzes for filter dropdown
	 *
	 * Returns a simple list of quizzes for use in report filters.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $owner_id Optional. Limit to specific owner.
	 * @return array List of quizzes with id and title.
	 */
	public function get_quiz_filter_options( $owner_id = null ) {
		global $wpdb;

		$quizzes_table = $wpdb->prefix . 'ppq_quizzes';

		$where = [ "status = 'published'" ];

		if ( $owner_id ) {
			$where[] = $wpdb->prepare( 'owner_id = %d', $owner_id );
		}

		$where_sql = implode( ' AND ', $where );

		return $wpdb->get_results(
			"SELECT id, title FROM {$quizzes_table} WHERE {$where_sql} ORDER BY title ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}
}
