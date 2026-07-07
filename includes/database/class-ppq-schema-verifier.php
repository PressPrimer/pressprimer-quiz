<?php
/**
 * Schema verifier
 *
 * Compares the live database structure against the canonical expected-schema
 * map produced by PressPrimer_Quiz_Schema::get_expected_schema().
 *
 * @package PressPrimer_Quiz
 * @subpackage Database
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema verifier class
 *
 * Presence-only schema integrity checking: detects missing tables and missing
 * columns against the expected-schema map. Type and index drift are
 * intentionally out of scope in 3.0 (feature 001, TR-004) because of the high
 * false-positive risk across MySQL/MariaDB versions and collations.
 *
 * @since 3.0.0
 */
class PressPrimer_Quiz_Schema_Verifier {

	/**
	 * Option storing the rolling check/repair log and per-problem attempt counters.
	 *
	 * Shape: [ 'entries' => array (capped at LOG_LIMIT, newest first),
	 * 'attempts' => [ problem_key => int ] ]. Structural information only — never
	 * row data.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const SCHEMA_LOG_OPTION = 'pressprimer_quiz_schema_log';

	/**
	 * Maximum rolling log entries retained.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	const LOG_LIMIT = 20;

	/**
	 * Failed auto-repair attempts allowed for a single problem before pausing.
	 *
	 * After this many failures the problem stops auto-retrying and a persistent
	 * admin notice is shown until it is repaired (e.g. manually via the Status
	 * tab once the DB user gains ALTER privileges).
	 *
	 * @since 3.0.0
	 * @var int
	 */
	const MAX_REPAIR_ATTEMPTS = 3;

	/**
	 * Prefix for the daily self-heal throttle transient.
	 *
	 * Suffixed with PRESSPRIMER_QUIZ_DB_VERSION so a new schema version forces a
	 * fresh pass.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const SELF_HEAL_TRANSIENT_PREFIX = 'ppq_schema_verified_';

	/**
	 * Register the self-healing and notice hooks.
	 *
	 * @since 3.0.0
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_self_heal' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_render_notice' ) );
	}

	/**
	 * Check the live schema against the expected-schema map.
	 *
	 * Compares each expected table's columns (from
	 * PressPrimer_Quiz_Schema::get_expected_schema()) against the live structure
	 * read from information_schema.COLUMNS, plus a table-existence check. The
	 * comparison is presence-only: a column counts as present when a column of
	 * that name exists, regardless of type, length, default, or index.
	 *
	 * @since 3.0.0
	 *
	 * @param string|null $table Optional single table (full name including the
	 *                           $wpdb prefix) to check. Null checks every table
	 *                           in the expected-schema map.
	 * @return array {
	 *     Structured report.
	 *
	 *     @type bool   $healthy      True when every checked table reports 'ok'.
	 *     @type array  $tables       Map of table name => {
	 *         @type string   $status          'ok' | 'missing_table' | 'missing_columns'.
	 *         @type string[] $missing_columns Missing column names (empty unless 'missing_columns').
	 *     }
	 *     @type string $generated_at Site-local 'mysql' timestamp of the check.
	 * }
	 */
	public static function check( ?string $table = null ): array {
		$expected = PressPrimer_Quiz_Schema::get_expected_schema();

		if ( null !== $table ) {
			$expected = isset( $expected[ $table ] )
				? array( $table => $expected[ $table ] )
				: array();
		}

		$tables  = array();
		$healthy = true;

		foreach ( $expected as $table_name => $columns ) {
			$result                = self::check_table( $table_name, $columns );
			$tables[ $table_name ] = $result;

			if ( 'ok' !== $result['status'] ) {
				$healthy = false;
			}
		}

		return array(
			'healthy'      => $healthy,
			'tables'       => $tables,
			'generated_at' => current_time( 'mysql' ),
		);
	}

	/**
	 * Check a single table's presence and columns.
	 *
	 * @since 3.0.0
	 *
	 * @param string $table            Full table name (with prefix).
	 * @param array  $expected_columns Map of column name => definition.
	 * @return array {
	 *     @type string   $status          'ok' | 'missing_table' | 'missing_columns'.
	 *     @type string[] $missing_columns Missing column names (empty unless 'missing_columns').
	 * }
	 */
	private static function check_table( string $table, array $expected_columns ): array {
		if ( ! self::table_exists( $table ) ) {
			return array(
				'status'          => 'missing_table',
				'missing_columns' => array(),
			);
		}

		$live_columns = self::get_live_columns( $table );
		$missing      = array();

		foreach ( array_keys( $expected_columns ) as $column ) {
			if ( ! in_array( $column, $live_columns, true ) ) {
				$missing[] = $column;
			}
		}

		return array(
			'status'          => empty( $missing ) ? 'ok' : 'missing_columns',
			'missing_columns' => $missing,
		);
	}

	/**
	 * Whether a table exists, using the established existence check.
	 *
	 * Mirrors the SHOW TABLES LIKE pattern already used by the activator and
	 * migrator so existence is determined identically everywhere.
	 *
	 * @since 3.0.0
	 *
	 * @param string $table Full table name (with prefix).
	 * @return bool True when the table exists.
	 */
	private static function table_exists( string $table ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema introspection; structural result, not cacheable.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return ( $table === $found );
	}

	/**
	 * Get the live column names for a table.
	 *
	 * Presence-only: returns column names from information_schema.COLUMNS with no
	 * type or index inspection (feature 001, TR-004). The query is fully prepared
	 * — both the schema name and table name are bound as values.
	 *
	 * @since 3.0.0
	 *
	 * @param string $table Full table name (with prefix).
	 * @return string[] Live column names (empty array when none).
	 */
	private static function get_live_columns( string $table ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema introspection; structural result, not cacheable.
		$columns = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				$wpdb->dbname,
				$table
			)
		);

		return is_array( $columns ) ? $columns : array();
	}

	/**
	 * Whether a single column exists on a table.
	 *
	 * Used as the idempotency guard before an ADD COLUMN and as the success check
	 * after one. Fully prepared.
	 *
	 * @since 3.0.0
	 *
	 * @param string $table  Full table name (with prefix).
	 * @param string $column Column name.
	 * @return bool True when the column exists.
	 */
	private static function column_exists( string $table, string $column ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema introspection; structural result, not cacheable.
		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				$wpdb->dbname,
				$table,
				$column
			)
		);

		return ( null !== $found );
	}

	/**
	 * Verify that specific tables/columns exist (targeted, presence-only).
	 *
	 * Unlike check(), which compares whole tables against the full expected
	 * schema, this verifies only the named targets. The migrator uses it to
	 * confirm a single migration step applied before advancing the DB version:
	 * later steps' columns are legitimately absent mid-chain, so a full check()
	 * would mis-report them as missing.
	 *
	 * @since 3.0.0
	 *
	 * @param array $targets Map of table name => [ column, ... ]. An empty column
	 *                       list means "verify the table exists".
	 * @return array Map of table name => [ missing columns ] for anything absent;
	 *               a missing table is reported as [ '*' ]. Empty array means all
	 *               targets are present.
	 */
	public static function verify_targets( array $targets ): array {
		$missing = array();

		foreach ( $targets as $table => $columns ) {
			if ( ! is_string( $table ) || ! is_array( $columns ) ) {
				continue;
			}

			if ( ! self::table_exists( $table ) ) {
				$missing[ $table ] = empty( $columns ) ? array( '*' ) : array_values( $columns );
				continue;
			}

			if ( empty( $columns ) ) {
				continue;
			}

			$live = self::get_live_columns( $table );

			foreach ( $columns as $column ) {
				if ( is_string( $column ) && ! in_array( $column, $live, true ) ) {
					$missing[ $table ][] = $column;
				}
			}
		}

		return $missing;
	}

	/**
	 * Run a full check and repair every problem found, overriding the attempt cap.
	 *
	 * The manual "Repair now" entry point (Status tab, Prompt 1.5). Unlike the
	 * daily self-heal it ignores the per-problem cap, so an admin can force a
	 * retry after fixing the cause (e.g. granting ALTER).
	 *
	 * @since 3.0.0
	 *
	 * @return array Repair result (see repair()).
	 */
	public static function repair_findings(): array {
		return self::repair( self::problems_from_report( self::check() ), false );
	}

	/**
	 * Repair missing tables and columns from the canonical schema.
	 *
	 * Accepts the per-table entries from a check() report (table => [ status,
	 * missing_columns ]); entries with status 'ok' are ignored, so a full
	 * report's 'tables' map may be passed directly. Only tables and columns that
	 * exist in the canonical expected-schema map are ever touched — the input is
	 * used to decide *what* to repair, never *how* (every ALTER/dbDelta statement
	 * is derived from the map, not from the caller).
	 *
	 * @since 3.0.0
	 *
	 * @param array $problems    Map of table name => [ status, missing_columns ].
	 * @param bool  $respect_cap When true, problems that have exhausted their
	 *                           auto-repair attempts are skipped (the self-heal
	 *                           path uses this). When false (the default, used by
	 *                           the manual "Repair now" action) the cap is
	 *                           overridden so an admin can force a retry after
	 *                           fixing the underlying cause.
	 * @return array {
	 *     @type string[] $repaired     Repaired targets ('table' or 'table.column').
	 *     @type string[] $failed       Targets whose repair failed this pass.
	 *     @type string[] $skipped      Targets skipped (capped, non-canonical, or not recreatable).
	 *     @type string   $generated_at Site-local 'mysql' timestamp.
	 * }
	 */
	public static function repair( array $problems, bool $respect_cap = false ): array {
		$expected = PressPrimer_Quiz_Schema::get_expected_schema();

		$result = array(
			'repaired'     => array(),
			'failed'       => array(),
			'skipped'      => array(),
			'generated_at' => current_time( 'mysql' ),
		);

		foreach ( $problems as $table => $entry ) {
			if ( ! is_string( $table ) || ! is_array( $entry ) ) {
				continue;
			}

			// Security: never operate on a table outside the canonical map.
			if ( ! isset( $expected[ $table ] ) ) {
				$result['skipped'][] = $table;
				continue;
			}

			$status = isset( $entry['status'] ) ? $entry['status'] : '';

			if ( 'missing_table' === $status ) {
				self::repair_table( $table, $result, $respect_cap );
			} elseif ( 'missing_columns' === $status ) {
				$columns = ( isset( $entry['missing_columns'] ) && is_array( $entry['missing_columns'] ) )
					? $entry['missing_columns']
					: array();

				foreach ( $columns as $column ) {
					self::repair_column( $table, $column, $expected[ $table ], $result, $respect_cap );
				}
			}
		}

		return $result;
	}

	/**
	 * Recreate a missing core table via dbDelta from the canonical statement.
	 *
	 * @since 3.0.0
	 *
	 * @param string $table       Full table name (validated against the map by repair()).
	 * @param array  $result      Repair result accumulator (by reference).
	 * @param bool   $respect_cap Whether to honor the auto-repair attempt cap.
	 */
	private static function repair_table( string $table, array &$result, bool $respect_cap ) {
		$create_sql = PressPrimer_Quiz_Schema::get_table_sql( $table );

		// No canonical CREATE (e.g. an addon table) — its own migrator owns creation.
		if ( '' === $create_sql ) {
			$result['skipped'][] = $table;
			self::log( 'repair_table', $table, null, 'skipped', 'No canonical CREATE statement; the owning migrator must recreate this table.' );
			return;
		}

		$key = self::problem_key( $table );

		if ( $respect_cap && self::get_attempt_count( $key ) >= self::MAX_REPAIR_ATTEMPTS ) {
			$result['skipped'][] = $table;
			self::log( 'repair_table', $table, null, 'capped', 'Auto-repair paused after repeated failures.' );
			return;
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta( $create_sql );

		if ( self::table_exists( $table ) ) {
			self::reset_attempt_count( $key );
			$result['repaired'][] = $table;
			self::log( 'repair_table', $table, null, 'success', '' );
		} else {
			$count              = self::increment_attempt_count( $key );
			$result['failed'][] = $table;
			self::log( 'repair_table', $table, null, 'failure', self::failure_message( $count ) );
		}
	}

	/**
	 * Add a single missing column via an idempotent ALTER from the canonical definition.
	 *
	 * @since 3.0.0
	 *
	 * @param string $table            Full table name (validated by repair()).
	 * @param mixed  $column           Column name from the problem report.
	 * @param array  $expected_columns Canonical column => definition map for the table.
	 * @param array  $result           Repair result accumulator (by reference).
	 * @param bool   $respect_cap      Whether to honor the auto-repair attempt cap.
	 */
	private static function repair_column( string $table, $column, array $expected_columns, array &$result, bool $respect_cap ) {
		// Security: only repair a column the canonical map defines for this table.
		if ( ! is_string( $column ) || ! isset( $expected_columns[ $column ] ) ) {
			$result['skipped'][] = $table . '.' . ( is_string( $column ) ? $column : '?' );
			return;
		}

		$key   = self::problem_key( $table, $column );
		$label = $table . '.' . $column;

		if ( $respect_cap && self::get_attempt_count( $key ) >= self::MAX_REPAIR_ATTEMPTS ) {
			$result['skipped'][] = $label;
			self::log( 'repair_column', $table, $column, 'capped', 'Auto-repair paused after repeated failures.' );
			return;
		}

		// Idempotency guard: already present (e.g. a concurrent repair) — nothing to do.
		if ( self::column_exists( $table, $column ) ) {
			self::reset_attempt_count( $key );
			$result['repaired'][] = $label;
			return;
		}

		self::run_add_column( $table, $expected_columns[ $column ] );

		if ( self::column_exists( $table, $column ) ) {
			self::reset_attempt_count( $key );
			$result['repaired'][] = $label;
			self::log( 'repair_column', $table, $column, 'success', '' );
		} else {
			$count              = self::increment_attempt_count( $key );
			$result['failed'][] = $label;
			self::log( 'repair_column', $table, $column, 'failure', self::failure_message( $count ) );
		}
	}

	/**
	 * Execute an ADD COLUMN derived from the canonical schema.
	 *
	 * The table name and column definition both originate from
	 * PressPrimer_Quiz_Schema::get_expected_schema() (validated by the caller),
	 * never from user input, so this DDL is safe to interpolate. DDL identifiers
	 * and full column definitions cannot be bound as prepared placeholders.
	 *
	 * @since 3.0.0
	 *
	 * @param string $table      Full table name (with prefix).
	 * @param string $definition Canonical column definition (e.g. 'foo VARCHAR(32) DEFAULT NULL').
	 */
	private static function run_add_column( string $table, string $definition ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- DDL from the canonical schema map; identifiers and definition are plugin-defined, not user input.
		$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN {$definition}" );
	}

	/**
	 * Self-healing pass: check the schema once per day and repair any findings.
	 *
	 * Hooked to admin_init. Throttled by a transient keyed to the current DB
	 * version (24h), set before the work runs so a fatal cannot create a repair
	 * loop. A completed migration clears the transient so the pass re-runs
	 * immediately (handled by the migrator).
	 *
	 * @since 3.0.0
	 */
	public static function maybe_self_heal() {
		$transient = self::SELF_HEAL_TRANSIENT_PREFIX . PRESSPRIMER_QUIZ_DB_VERSION;

		if ( get_transient( $transient ) ) {
			return;
		}

		// Throttle to one pass per day per DB version regardless of outcome.
		set_transient( $transient, time(), DAY_IN_SECONDS );

		$report = self::check();

		if ( ! $report['healthy'] ) {
			// Auto path: honor the per-problem attempt cap (no infinite retries).
			self::repair( self::problems_from_report( $report ), true );
		}
	}

	/**
	 * Clear the daily self-heal throttle so the next admin load re-verifies.
	 *
	 * Called by the migrator after a migration chain completes so a freshly
	 * migrated schema is verified immediately rather than up to a day later.
	 *
	 * @since 3.0.0
	 */
	public static function clear_self_heal_throttle() {
		delete_transient( self::SELF_HEAL_TRANSIENT_PREFIX . PRESSPRIMER_QUIZ_DB_VERSION );
	}

	/**
	 * Record a migration verification failure to the schema log.
	 *
	 * Used by the migrator when a step's tables/columns are absent after the step
	 * ran, so the cause of a stalled (not-advanced) version is visible on the
	 * Status tab.
	 *
	 * @since 3.0.0
	 *
	 * @param string $version Target version of the step that failed verification.
	 * @param array  $missing Map of table => [ missing columns ] ('*' = missing table).
	 */
	public static function record_migration_problem( string $version, array $missing ) {
		foreach ( $missing as $table => $columns ) {
			if ( ! is_string( $table ) ) {
				continue;
			}

			$columns = is_array( $columns ) ? $columns : array();

			if ( empty( $columns ) || array( '*' ) === $columns ) {
				self::log(
					'migration',
					$table,
					null,
					'failure',
					sprintf( 'Migration to %s did not create the table; version not advanced.', $version )
				);
				continue;
			}

			foreach ( $columns as $column ) {
				self::log(
					'migration',
					$table,
					is_string( $column ) ? $column : null,
					'failure',
					sprintf( 'Migration to %s did not add the column; version not advanced.', $version )
				);
			}
		}
	}

	/**
	 * Render a persistent admin notice when a problem has exhausted auto-repair.
	 *
	 * Hooked to admin_notices. Shown only to users who can act on it; clears
	 * automatically once the problem is repaired (its attempt counter resets).
	 *
	 * @since 3.0.0
	 */
	public static function maybe_render_notice() {
		if ( ! current_user_can( 'pressprimer_quiz_manage_settings' ) ) {
			return;
		}

		$capped = self::get_capped_problems();

		if ( empty( $capped ) ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: comma-separated list of affected tables/columns. */
			__( 'PressPrimer Quiz could not automatically repair part of its database (%s) after several attempts. This usually means the database user lacks the ALTER privilege. Please review the schema status or contact your host.', 'pressprimer-quiz' ),
			implode( ', ', $capped )
		);

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'PressPrimer Quiz:', 'pressprimer-quiz' ),
			esc_html( $message ),
			esc_url( admin_url( 'admin.php?page=pressprimer-quiz-settings' ) ),
			esc_html__( 'Open schema status', 'pressprimer-quiz' )
		);
	}

	/**
	 * Get the rolling check/repair log entries (newest first).
	 *
	 * @since 3.0.0
	 *
	 * @return array Log entries.
	 */
	public static function get_log(): array {
		$data = self::get_log_data();

		return $data['entries'];
	}

	/**
	 * Extract the non-ok entries from a check report as a problems map.
	 *
	 * @since 3.0.0
	 *
	 * @param array $report A check() report.
	 * @return array Map of table name => entry for every non-ok table.
	 */
	private static function problems_from_report( array $report ): array {
		$problems = array();

		if ( empty( $report['tables'] ) || ! is_array( $report['tables'] ) ) {
			return $problems;
		}

		foreach ( $report['tables'] as $table => $entry ) {
			if ( is_array( $entry ) && isset( $entry['status'] ) && 'ok' !== $entry['status'] ) {
				$problems[ $table ] = $entry;
			}
		}

		return $problems;
	}

	/**
	 * Build the stable counter/log key for a problem.
	 *
	 * @since 3.0.0
	 *
	 * @param string      $table  Full table name.
	 * @param string|null $column Column name, or null for a table-level problem.
	 * @return string Problem key.
	 */
	private static function problem_key( string $table, ?string $column = null ): string {
		return null === $column ? $table . '::table' : $table . '::' . $column;
	}

	/**
	 * Convert a problem key back to a human-readable label.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key Problem key.
	 * @return string Label ('table' or 'table.column').
	 */
	private static function label_from_key( string $key ): string {
		$parts = explode( '::', $key, 2 );

		if ( 2 === count( $parts ) ) {
			return 'table' === $parts[1] ? $parts[0] : $parts[0] . '.' . $parts[1];
		}

		return $key;
	}

	/**
	 * Get the labels of problems whose auto-repair is capped.
	 *
	 * @since 3.0.0
	 *
	 * @return string[] Human-readable labels.
	 */
	private static function get_capped_problems(): array {
		$data   = self::get_log_data();
		$capped = array();

		foreach ( $data['attempts'] as $key => $count ) {
			if ( (int) $count >= self::MAX_REPAIR_ATTEMPTS ) {
				$capped[] = self::label_from_key( (string) $key );
			}
		}

		return $capped;
	}

	/**
	 * Build a failure message, escalating when the cap is reached.
	 *
	 * @since 3.0.0
	 *
	 * @param int $attempt_count The post-increment attempt count.
	 * @return string Message.
	 */
	private static function failure_message( int $attempt_count ): string {
		if ( $attempt_count >= self::MAX_REPAIR_ATTEMPTS ) {
			return 'Repair failed; auto-repair paused. Check that the database user has the ALTER privilege.';
		}

		return 'Repair statement did not apply; will retry.';
	}

	/**
	 * Read the schema-log option, normalized to its expected shape.
	 *
	 * @since 3.0.0
	 *
	 * @return array{entries:array,attempts:array} Normalized log data.
	 */
	private static function get_log_data(): array {
		$data = get_option( self::SCHEMA_LOG_OPTION, array() );

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( ! isset( $data['entries'] ) || ! is_array( $data['entries'] ) ) {
			$data['entries'] = array();
		}

		if ( ! isset( $data['attempts'] ) || ! is_array( $data['attempts'] ) ) {
			$data['attempts'] = array();
		}

		return $data;
	}

	/**
	 * Persist the schema-log option, capping the rolling entries.
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Log data to store.
	 */
	private static function save_log_data( array $data ) {
		$data['entries'] = array_slice( $data['entries'], 0, self::LOG_LIMIT );

		update_option( self::SCHEMA_LOG_OPTION, $data, false );
	}

	/**
	 * Append a structural log entry (newest first) and mirror to error_log in debug.
	 *
	 * @since 3.0.0
	 *
	 * @param string      $action  Action key (e.g. 'repair_column').
	 * @param string      $table   Table name.
	 * @param string|null $column  Column name, or null.
	 * @param string      $outcome 'success' | 'failure' | 'capped' | 'skipped'.
	 * @param string      $message Human-readable detail (no row data).
	 */
	private static function log( string $action, string $table, ?string $column, string $outcome, string $message ) {
		$data = self::get_log_data();

		array_unshift(
			$data['entries'],
			array(
				'time'    => current_time( 'mysql' ),
				'action'  => $action,
				'table'   => $table,
				'column'  => $column,
				'outcome' => $outcome,
				'message' => $message,
			)
		);

		self::save_log_data( $data );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$column_part = null === $column ? '' : '.' . $column;
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only structural diagnostic.
			error_log( sprintf( '[PressPrimer Quiz schema] %s %s%s: %s %s', $action, $table, $column_part, $outcome, $message ) );
		}
	}

	/**
	 * Get the failed-attempt count for a problem.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key Problem key.
	 * @return int Attempt count.
	 */
	private static function get_attempt_count( string $key ): int {
		$data = self::get_log_data();

		return isset( $data['attempts'][ $key ] ) ? (int) $data['attempts'][ $key ] : 0;
	}

	/**
	 * Increment and persist the failed-attempt count for a problem.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key Problem key.
	 * @return int The new attempt count.
	 */
	private static function increment_attempt_count( string $key ): int {
		$data  = self::get_log_data();
		$count = isset( $data['attempts'][ $key ] ) ? (int) $data['attempts'][ $key ] + 1 : 1;

		$data['attempts'][ $key ] = $count;
		self::save_log_data( $data );

		return $count;
	}

	/**
	 * Clear the failed-attempt count for a repaired problem.
	 *
	 * @since 3.0.0
	 *
	 * @param string $key Problem key.
	 */
	private static function reset_attempt_count( string $key ) {
		$data = self::get_log_data();

		if ( isset( $data['attempts'][ $key ] ) ) {
			unset( $data['attempts'][ $key ] );
			self::save_log_data( $data );
		}
	}
}
