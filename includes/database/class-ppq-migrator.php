<?php
/**
 * Database migrator
 *
 * Handles database schema migrations and updates.
 *
 * @package PressPrimer_Quiz
 * @subpackage Database
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrator class
 *
 * Manages database schema creation and updates using WordPress dbDelta.
 * Checks version and only runs migrations when needed.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Migrator {

	/**
	 * Option name for storing database version
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DB_VERSION_OPTION = 'pressprimer_quiz_db_version';

	/**
	 * Maybe run migrations
	 *
	 * Checks if database needs to be updated and runs migrations if necessary.
	 * Safe to call multiple times - only runs when version changes.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_migrate() {
		$current_version = get_option( self::DB_VERSION_OPTION, '0' );
		$target_version  = PRESSPRIMER_QUIZ_DB_VERSION;

		// Check if migration is needed
		if ( version_compare( $current_version, $target_version, '<' ) ) {
			self::run_migrations( $current_version, $target_version );
		}
	}

	/**
	 * Run migrations
	 *
	 * Executes database migrations from current version to target version.
	 *
	 * @since 1.0.0
	 *
	 * @param string $from_version Current database version.
	 * @param string $to_version   Target database version.
	 */
	private static function run_migrations( $from_version, $to_version ) {
		// Create or update all tables (idempotent). dbDelta adds any new tables;
		// the verified per-step data migrations below handle column adds, which
		// dbDelta cannot be relied upon to perform.
		self::update_schema();

		// Apply data-migration steps, verifying each and advancing the stored
		// version one step at a time. A step that fails verification leaves the
		// version at the last verified step so the chain retries from there on the
		// next load, instead of stranding the site on an advanced version with a
		// half-applied schema (FR-004).
		$result = self::run_data_migrations( $from_version, $to_version );

		if ( ! $result['ok'] ) {
			// Verification failed mid-chain: do not finalize and do not re-arm
			// self-healing. The next load retries the failed step.
			return;
		}

		// A target bump may add only tables (no data step). Advance to the exact
		// target version only after confirming the full schema is healthy.
		if ( version_compare( $result['version'], $to_version, '<' ) ) {
			$report = PressPrimer_Quiz_Schema_Verifier::check();

			if ( ! $report['healthy'] ) {
				PressPrimer_Quiz_Schema_Verifier::record_migration_problem(
					$to_version,
					self::missing_from_report( $report )
				);
				return;
			}

			update_option( self::DB_VERSION_OPTION, $to_version );
		}

		// Whole chain complete: re-verify immediately on the next admin load and
		// record the migration in the history log.
		PressPrimer_Quiz_Schema_Verifier::clear_self_heal_throttle();
		self::log_migration( $from_version, $to_version );
	}

	/**
	 * Update database schema
	 *
	 * Runs dbDelta to create or update all database tables.
	 * Loads wp-admin/includes/upgrade.php and immediately calls dbDelta().
	 *
	 * @since 1.0.0
	 */
	private static function update_schema() {
		// Get schema SQL
		$sql = PressPrimer_Quiz_Schema::get_schema();

		// Load WordPress upgrade functions and immediately use dbDelta
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify critical tables exist
		self::verify_tables();
	}

	/**
	 * Run data migrations
	 *
	 * Runs version-specific data migrations for upgrades.
	 * This is where we handle data transformations between versions.
	 *
	 * @since 1.0.0
	 *
	 * @param string $from_version Version migrating from.
	 * @param string $to_version   Version migrating to.
	 */
	private static function run_data_migrations( $from_version, $to_version ) {
		$current = $from_version;

		foreach ( self::get_migration_steps() as $step ) {
			// Skip steps already applied.
			if ( version_compare( $current, $step['version'], '>=' ) ) {
				continue;
			}

			// Run the (idempotent) step.
			call_user_func( $step['callback'] );

			// Verify the tables/columns this step adds BEFORE advancing the version.
			$missing = PressPrimer_Quiz_Schema_Verifier::verify_targets( $step['targets'] );

			if ( ! empty( $missing ) ) {
				// Do not advance. Log the cause and bail; the chain retries from
				// $current (this same step) on the next load.
				PressPrimer_Quiz_Schema_Verifier::record_migration_problem( $step['version'], $missing );

				return array(
					'ok'      => false,
					'version' => $current,
				);
			}

			// Step verified: advance the stored DB version to this step.
			$current = $step['version'];
			update_option( self::DB_VERSION_OPTION, $current );
		}

		return array(
			'ok'      => true,
			'version' => $current,
		);
	}

	/**
	 * Get the ordered data-migration chain.
	 *
	 * Each step pairs a target version with its (idempotent) migration callback
	 * and the tables/columns it must produce. run_data_migrations() runs and
	 * verifies them in order, advancing the stored DB version one verified step
	 * at a time. A new schema version appends a step here.
	 *
	 * @since 3.0.0
	 *
	 * @return array[] Ordered steps, each [ 'version', 'callback', 'targets' ].
	 */
	private static function get_migration_steps() {
		global $wpdb;

		$quizzes       = $wpdb->prefix . 'ppq_quizzes';
		$attempts      = $wpdb->prefix . 'ppq_attempts';
		$attempt_items = $wpdb->prefix . 'ppq_attempt_items';
		$templates     = $wpdb->prefix . 'ppq_quiz_templates';

		return array(
			array(
				'version'  => '2.0.1',
				'callback' => array( __CLASS__, 'migrate_to_2_0_1' ),
				'targets'  => array( $quizzes => array( 'access_mode', 'login_message' ) ),
			),
			array(
				'version'  => '2.2.0',
				'callback' => array( __CLASS__, 'migrate_to_2_2_0' ),
				'targets'  => array( $quizzes => array( 'pool_enabled', 'max_questions' ) ),
			),
			array(
				'version'  => '2.2.1',
				'callback' => array( __CLASS__, 'migrate_to_2_2_1' ),
				'targets'  => array( $attempt_items => array( 'answer_checked_at' ) ),
			),
			array(
				'version'  => '2.2.2',
				'callback' => array( __CLASS__, 'migrate_to_2_2_2' ),
				'targets'  => array( $quizzes => array( 'show_points' ) ),
			),
			array(
				'version'  => '2.2.3',
				'callback' => array( __CLASS__, 'migrate_to_2_2_3' ),
				'targets'  => array( $quizzes => array( 'enable_sr', 'is_review_quiz' ) ),
			),
			array(
				'version'  => '2.2.4',
				'callback' => array( __CLASS__, 'migrate_to_2_2_4' ),
				'targets'  => array( $attempts => array( 'curved_score' ) ),
			),
			array(
				'version'  => '2.3.0',
				'callback' => array( __CLASS__, 'migrate_to_2_3_0' ),
				'targets'  => array( $quizzes => array( 'ma_scoring_mode', 'display_settings_json', 'max_answers_per_question' ) ),
			),
			array(
				'version'  => '3.0.0',
				'callback' => array( __CLASS__, 'migrate_to_3_0_0' ),
				// Empty column list for the templates table = "verify the table
				// exists" (see verify_targets()); the attempts column is verified by name.
				'targets'  => array(
					$templates => array(),
					$attempts  => array( 'ma_scoring_mode' ),
				),
			),
		);
	}

	/**
	 * Convert a verifier check() report into a [ table => missing columns ] map.
	 *
	 * @since 3.0.0
	 *
	 * @param array $report A PressPrimer_Quiz_Schema_Verifier::check() report.
	 * @return array Map of table => [ missing columns ] ('*' = missing table).
	 */
	private static function missing_from_report( $report ) {
		$missing = array();

		if ( empty( $report['tables'] ) || ! is_array( $report['tables'] ) ) {
			return $missing;
		}

		foreach ( $report['tables'] as $table => $entry ) {
			$status = isset( $entry['status'] ) ? $entry['status'] : '';

			if ( 'missing_table' === $status ) {
				$missing[ $table ] = array( '*' );
			} elseif ( 'missing_columns' === $status ) {
				$missing[ $table ] = isset( $entry['missing_columns'] ) ? $entry['missing_columns'] : array();
			}
		}

		return $missing;
	}

	/**
	 * Migration to version 2.0.1
	 *
	 * Adds access_mode and login_message columns to quizzes table.
	 *
	 * @since 2.0.1
	 */
	private static function migrate_to_2_0_1() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ppq_quizzes';

		// Check if access_mode column exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'access_mode'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add access_mode column
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN access_mode VARCHAR(20) NOT NULL DEFAULT %s',
					$table_name,
					'default'
				)
			);
		}

		// Check if login_message column exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'login_message'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add login_message column
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN login_message TEXT DEFAULT NULL',
					$table_name
				)
			);
		}
	}

	/**
	 * Migration to version 2.2.0
	 *
	 * Adds pool_enabled and max_questions columns to quizzes table.
	 *
	 * @since 2.2.0
	 */
	private static function migrate_to_2_2_0() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ppq_quizzes';

		// Check if pool_enabled column exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'pool_enabled'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add pool_enabled column
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN pool_enabled TINYINT(1) NOT NULL DEFAULT 0',
					$table_name
				)
			);
		}

		// Check if max_questions column exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'max_questions'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add max_questions column
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN max_questions INT UNSIGNED DEFAULT NULL',
					$table_name
				)
			);
		}
	}

	/**
	 * Migration to version 2.2.1
	 *
	 * Adds answer_checked_at column to attempt items table for tutorial mode
	 * answer locking. This column tracks when an answer was validated via
	 * "Check Answer" so it cannot be changed on page reload.
	 *
	 * @since 2.2.1
	 *
	 * @return void
	 */
	private static function migrate_to_2_2_1() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ppq_attempt_items';

		// Check if answer_checked_at column exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'answer_checked_at'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add answer_checked_at column
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN answer_checked_at DATETIME DEFAULT NULL',
					$table_name
				)
			);
		}
	}

	/**
	 * Migration to version 2.2.2
	 *
	 * Adds show_points column to quizzes table. When enabled, point values
	 * are displayed per question during the quiz and on the results page.
	 *
	 * @since 2.2.2
	 *
	 * @return void
	 */
	private static function migrate_to_2_2_2() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ppq_quizzes';

		// Check if show_points column exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'show_points'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add show_points column after enable_confidence
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN show_points TINYINT(1) NOT NULL DEFAULT 0 AFTER enable_confidence',
					$table_name
				)
			);
		}
	}

	/**
	 * Migration to version 2.2.3
	 *
	 * Adds enable_sr and is_review_quiz columns to quizzes table.
	 * enable_sr controls whether spaced repetition tracks questions from
	 * this quiz. is_review_quiz marks quizzes generated by the SR service.
	 *
	 * @since 2.2.3
	 *
	 * @return void
	 */
	private static function migrate_to_2_2_3() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ppq_quizzes';

		// Check if enable_sr column exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'enable_sr'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add enable_sr column
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN enable_sr TINYINT(1) NOT NULL DEFAULT 0',
					$table_name
				)
			);
		}

		// Check if is_review_quiz column exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'is_review_quiz'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add is_review_quiz column
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN is_review_quiz TINYINT(1) NOT NULL DEFAULT 0',
					$table_name
				)
			);
		}
	}

	/**
	 * Migration to version 2.2.4
	 *
	 * Adds curved_score column to attempts table. This column is used by the
	 * School addon's curve grading feature, but must exist in the free plugin's
	 * schema because the Attempt model includes it in get_fillable_fields().
	 *
	 * @since 2.2.4
	 *
	 * @return void
	 */
	private static function migrate_to_2_2_4() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ppq_attempts';

		// Check if curved_score column exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'curved_score'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add curved_score column after passed
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN curved_score DECIMAL(5,2) DEFAULT NULL AFTER passed',
					$table_name
				)
			);
		}
	}

	/**
	 * Migration to version 2.3.0
	 *
	 * Adds the v2.3 quiz columns: ma_scoring_mode (per-quiz override of the
	 * multiple-answer scoring mode), display_settings_json (per-quiz defaults
	 * for the 15 Start/Results display toggles), and max_answers_per_question
	 * (cap on answer options shown per question per attempt). Each column
	 * check is independent so the migration is safe to re-run for
	 * partially-migrated installations.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	private static function migrate_to_2_3_0() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ppq_quizzes';

		// Check if ma_scoring_mode column exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'ma_scoring_mode'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add ma_scoring_mode column after login_message
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN ma_scoring_mode VARCHAR(32) DEFAULT NULL AFTER login_message',
					$table_name
				)
			);
		}

		// Check if display_settings_json column exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'display_settings_json'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add display_settings_json column after ma_scoring_mode
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN display_settings_json TEXT DEFAULT NULL AFTER ma_scoring_mode',
					$table_name
				)
			);
		}

		// Check if max_answers_per_question column exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table_name,
				'max_answers_per_question'
			)
		);

		if ( empty( $column_exists ) ) {
			// Add max_answers_per_question column after display_settings_json
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN max_answers_per_question SMALLINT UNSIGNED DEFAULT NULL AFTER display_settings_json',
					$table_name
				)
			);
		}

		// Pin every pre-2.3 quiz to right_minus_wrong. Under 2.2.x there was
		// only one MA scoring behavior, equivalent to right_minus_wrong, so a
		// NULL value here would otherwise resolve to the site default and
		// silently rescore historic quizzes when an admin changes the default.
		// The IS NULL guard makes this idempotent and a no-op for quizzes
		// authored under 2.3 where the editor leaves the column NULL on
		// purpose so they can inherit future site-default changes.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET ma_scoring_mode = %s WHERE ma_scoring_mode IS NULL',
				$table_name,
				'right_minus_wrong'
			)
		);
	}

	/**
	 * Migration to version 3.0.0
	 *
	 * Creates the quiz settings templates table (wp_ppq_quiz_templates) and adds
	 * the ma_scoring_mode column to the attempts table (Score Transparency, which
	 * records the scoring mode each attempt was graded under). The full-schema
	 * dbDelta in update_schema() already creates the table during an upgrade;
	 * this step re-runs dbDelta on the single CREATE TABLE statement and adds the
	 * column with a guarded ALTER so it is self-contained and idempotent.
	 * verify-before-advance then confirms both before the DB version moves to 3.0.0.
	 *
	 * @since 3.0.0
	 */
	private static function migrate_to_3_0_0() {
		global $wpdb;

		if ( ! class_exists( 'PressPrimer_Quiz_Schema' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = PressPrimer_Quiz_Schema::get_table_sql( $wpdb->prefix . 'ppq_quiz_templates' );

		if ( '' !== $sql ) {
			dbDelta( $sql );
		}

		// Add the attempts ma_scoring_mode column (idempotent).
		$attempts = $wpdb->prefix . 'ppq_attempts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$attempts,
				'ma_scoring_mode'
			)
		);

		if ( empty( $column_exists ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					'ALTER TABLE %i ADD COLUMN ma_scoring_mode VARCHAR(32) DEFAULT NULL AFTER curved_score',
					$attempts
				)
			);
		}
	}

	/**
	 * Verify tables exist
	 *
	 * Checks that all critical database tables were created successfully.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if all tables exist, false otherwise.
	 */
	private static function verify_tables() {
		global $wpdb;

		$required_tables = [
			$wpdb->prefix . 'ppq_questions',
			$wpdb->prefix . 'ppq_question_revisions',
			$wpdb->prefix . 'ppq_categories',
			$wpdb->prefix . 'ppq_question_tax',
			$wpdb->prefix . 'ppq_banks',
			$wpdb->prefix . 'ppq_bank_questions',
			$wpdb->prefix . 'ppq_quizzes',
			$wpdb->prefix . 'ppq_quiz_items',
			$wpdb->prefix . 'ppq_quiz_rules',
			$wpdb->prefix . 'ppq_groups',
			$wpdb->prefix . 'ppq_group_members',
			$wpdb->prefix . 'ppq_assignments',
			$wpdb->prefix . 'ppq_attempts',
			$wpdb->prefix . 'ppq_attempt_items',
			$wpdb->prefix . 'ppq_events',
			$wpdb->prefix . 'ppq_quiz_templates',
		];

		$missing_tables = [];

		foreach ( $required_tables as $table ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			if ( $table !== $table_exists ) {
				$missing_tables[] = $table;
			}
		}

		if ( ! empty( $missing_tables ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Log migration
	 *
	 * Records migration in WordPress log and plugin options.
	 *
	 * @since 1.0.0
	 *
	 * @param string $from_version Version migrated from.
	 * @param string $to_version   Version migrated to.
	 */
	private static function log_migration( $from_version, $to_version ) {
		// Get migration history
		$history = get_option( 'pressprimer_quiz_migration_history', [] );

		// Add this migration
		$history[] = [
			'from'      => $from_version,
			'to'        => $to_version,
			'timestamp' => current_time( 'mysql' ),
		];

		// Keep last 10 migrations
		$history = array_slice( $history, -10 );

		// Save history
		update_option( 'pressprimer_quiz_migration_history', $history );
	}

	/**
	 * Get current database version
	 *
	 * Returns the currently installed database version.
	 *
	 * @since 1.0.0
	 *
	 * @return string Database version.
	 */
	public static function get_current_version() {
		return get_option( self::DB_VERSION_OPTION, '0' );
	}

	/**
	 * Get migration history
	 *
	 * Returns array of past migrations.
	 *
	 * @since 1.0.0
	 *
	 * @return array Migration history.
	 */
	public static function get_migration_history() {
		return get_option( 'pressprimer_quiz_migration_history', [] );
	}

	/**
	 * Force migration
	 *
	 * Forces a migration to run regardless of version.
	 * Useful for debugging or manual repairs.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if migration successful.
	 */
	public static function force_migration() {
		// Verify capability
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$current_version = self::get_current_version();
		$target_version  = PRESSPRIMER_QUIZ_DB_VERSION;

		self::run_migrations( $current_version, $target_version );

		return true;
	}

	/**
	 * Get list of required tables
	 *
	 * Returns array of all table names required by the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of table names with wpdb prefix.
	 */
	public static function get_required_tables() {
		global $wpdb;

		return [
			$wpdb->prefix . 'ppq_questions',
			$wpdb->prefix . 'ppq_question_revisions',
			$wpdb->prefix . 'ppq_categories',
			$wpdb->prefix . 'ppq_question_tax',
			$wpdb->prefix . 'ppq_banks',
			$wpdb->prefix . 'ppq_bank_questions',
			$wpdb->prefix . 'ppq_quizzes',
			$wpdb->prefix . 'ppq_quiz_items',
			$wpdb->prefix . 'ppq_quiz_rules',
			$wpdb->prefix . 'ppq_groups',
			$wpdb->prefix . 'ppq_group_members',
			$wpdb->prefix . 'ppq_assignments',
			$wpdb->prefix . 'ppq_attempts',
			$wpdb->prefix . 'ppq_attempt_items',
			$wpdb->prefix . 'ppq_events',
			$wpdb->prefix . 'ppq_quiz_templates',
		];
	}

	/**
	 * Get table status
	 *
	 * Returns status information for all plugin tables.
	 * Addons can register their own tables via the
	 * `pressprimer_quiz_status_tables` filter.
	 *
	 * @since 1.0.0
	 * @since 2.2.0 Added pressprimer_quiz_status_tables filter for addon tables.
	 *
	 * @return array Array of table status info with keys: name, exists, row_count.
	 */
	public static function get_table_status() {
		global $wpdb;

		$tables = self::get_required_tables();

		/**
		 * Filter the list of database tables shown on the Status page.
		 *
		 * Addons can append their own table names (with $wpdb->prefix)
		 * so they appear alongside the core tables.
		 *
		 * @since 2.2.0
		 *
		 * @param string[] $tables Array of full table names including prefix.
		 */
		$tables = apply_filters( 'pressprimer_quiz_status_tables', $tables );

		$results = [];

		foreach ( $tables as $table ) {
			// Check if table exists
			$table_exists = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
			);

			$status = [
				'name'      => $table,
				'exists'    => ( $table === $table_exists ),
				'row_count' => 0,
			];

			// Get row count if table exists
			if ( $status['exists'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is from our controlled list
				$count               = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
				$status['row_count'] = (int) $count;
			}

			$results[] = $status;
		}

		return $results;
	}

	/**
	 * Check if any tables are missing
	 *
	 * Returns true if any required tables do not exist.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if tables are missing.
	 */
	public static function has_missing_tables() {
		$table_status = self::get_table_status();

		foreach ( $table_status as $table ) {
			if ( ! $table['exists'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Repair tables
	 *
	 * Recreates any missing database tables.
	 * Does not modify existing tables or data.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array with 'success' boolean and 'repaired' array of table names.
	 */
	public static function repair_tables() {
		// Verify capability
		if ( ! current_user_can( 'manage_options' ) ) {
			return [
				'success'  => false,
				'repaired' => [],
				'error'    => __( 'Permission denied.', 'pressprimer-quiz' ),
			];
		}

		// Get current status to know what's missing
		$before_status = self::get_table_status();
		$was_missing   = [];

		foreach ( $before_status as $table ) {
			if ( ! $table['exists'] ) {
				$was_missing[] = $table['name'];
			}
		}

		// Run dbDelta to create missing tables
		if ( class_exists( 'PressPrimer_Quiz_Schema' ) ) {
			$sql = PressPrimer_Quiz_Schema::get_schema();

			// Load WordPress upgrade functions and immediately use dbDelta
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		// Check which tables were actually repaired
		$after_status = self::get_table_status();
		$repaired     = [];

		foreach ( $after_status as $table ) {
			if ( $table['exists'] && in_array( $table['name'], $was_missing, true ) ) {
				$repaired[] = $table['name'];
			}
		}

		// Update database version if tables were repaired
		if ( ! empty( $repaired ) ) {
			update_option( self::DB_VERSION_OPTION, PRESSPRIMER_QUIZ_DB_VERSION );
		}

		return [
			'success'  => count( $repaired ) === count( $was_missing ),
			'repaired' => $repaired,
		];
	}
}
