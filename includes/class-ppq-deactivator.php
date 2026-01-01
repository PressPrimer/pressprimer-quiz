<?php
/**
 * Plugin deactivation handler
 *
 * Handles tasks that run when the plugin is deactivated.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivator class
 *
 * Contains all functionality for plugin deactivation.
 * Performs cleanup tasks that should run when plugin is deactivated.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Deactivator {

	/**
	 * Deactivate the plugin
	 *
	 * Runs when the plugin is deactivated.
	 * Cleans up temporary data and flushes rewrite rules.
	 *
	 * Note: This does NOT delete database tables or permanent data.
	 * That only happens on uninstall. See uninstall.php.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		// Clear any scheduled cron jobs
		$timestamp = wp_next_scheduled( 'pressprimer_quiz_cleanup_abandoned_attempts' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'pressprimer_quiz_cleanup_abandoned_attempts' );
		}

		// Unschedule statistics recalculation cron
		if ( class_exists( 'PressPrimer_Quiz_Statistics_Service' ) ) {
			PressPrimer_Quiz_Statistics_Service::unschedule_cron();
		}

		// Clear transients
		self::clear_plugin_transients();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set deactivation flag
		update_option( 'pressprimer_quiz_deactivation_time', current_time( 'timestamp' ) );
	}

	/**
	 * Clear plugin transients
	 *
	 * Removes all transients created by the plugin.
	 *
	 * @since 1.0.0
	 */
	private static function clear_plugin_transients() {
		global $wpdb;

		// Delete all transients that start with ppq_
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient cleanup during deactivation
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table name
				$wpdb->esc_like( '_transient_ppq_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_ppq_' ) . '%'
			)
		);

		// If using site transients (multisite)
		if ( is_multisite() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient cleanup during deactivation
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->sitemeta}
					WHERE meta_key LIKE %s
					OR meta_key LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table name
					$wpdb->esc_like( '_site_transient_ppq_' ) . '%',
					$wpdb->esc_like( '_site_transient_timeout_ppq_' ) . '%'
				)
			);
		}
	}
}
