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
class PPQ_Migrator {

	/**
	 * Option name for storing database version
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const DB_VERSION_OPTION = 'ppq_db_version';

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
		$target_version  = PPQ_DB_VERSION;

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
		global $wpdb;

		// Load WordPress upgrade functions
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Run schema updates
		self::update_schema();

		// Run version-specific data migrations
		self::run_data_migrations( $from_version, $to_version );

		// Update database version
		update_option( self::DB_VERSION_OPTION, $to_version );

		// Log migration
		self::log_migration( $from_version, $to_version );
	}

	/**
	 * Update database schema
	 *
	 * Runs dbDelta to create or update all database tables.
	 *
	 * @since 1.0.0
	 */
	private static function update_schema() {
		// Get schema SQL
		$sql = PPQ_Schema::get_schema();

		// Run dbDelta
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
		// Example: If upgrading from version before 1.1.0
		// if ( version_compare( $from_version, '1.1.0', '<' ) ) {
		//     self::migrate_to_1_1_0();
		// }

		// Example: If upgrading from version before 1.2.0
		// if ( version_compare( $from_version, '1.2.0', '<' ) ) {
		//     self::migrate_to_1_2_0();
		// }

		// For version 1.0.0, no data migrations needed
		// Data migrations will be added in future versions
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
		];

		$missing_tables = [];

		foreach ( $required_tables as $table ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			if ( $table !== $table_exists ) {
				$missing_tables[] = $table;
			}
		}

		if ( ! empty( $missing_tables ) ) {
			// Log error
			error_log(
				sprintf(
					'PressPrimer Quiz: Missing database tables after migration: %s',
					implode( ', ', $missing_tables )
				)
			);
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
		$history = get_option( 'ppq_migration_history', [] );

		// Add this migration
		$history[] = [
			'from'      => $from_version,
			'to'        => $to_version,
			'timestamp' => current_time( 'mysql' ),
		];

		// Keep last 10 migrations
		$history = array_slice( $history, -10 );

		// Save history
		update_option( 'ppq_migration_history', $history );

		// Log to WordPress debug log if enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'PressPrimer Quiz: Database migrated from v%s to v%s',
					$from_version,
					$to_version
				)
			);
		}
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
		return get_option( 'ppq_migration_history', [] );
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
		$target_version  = PPQ_DB_VERSION;

		self::run_migrations( $current_version, $target_version );

		return true;
	}
}
