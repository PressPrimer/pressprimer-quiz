<?php
/**
 * Uninstall handler
 *
 * Handles complete removal of plugin data when uninstalled.
 * This file is called by WordPress when the user deletes the plugin.
 *
 * IMPORTANT: By default, this script preserves ALL data to prevent accidental data loss.
 * Data is only removed if the user has explicitly enabled "Remove all data on uninstall"
 * in the plugin settings page. This includes:
 * - Database tables
 * - Options
 * - User meta
 * - Post meta
 * - Capabilities and roles
 * - Transients
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Define plugin path constant if not already defined
if ( ! defined( 'PPQ_PLUGIN_PATH' ) ) {
	define( 'PPQ_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 * Remove plugin data
 *
 * Only runs if user has explicitly opted in to remove all data.
 * By default, all data is preserved to prevent accidental data loss.
 */
function ppq_uninstall() {
	global $wpdb;

	// Get settings
	$settings = get_option( 'ppq_settings', [] );

	// Check if user has EXPLICITLY opted to remove data on uninstall
	// Default behavior is to KEEP all data for safety
	if ( ! isset( $settings['remove_data_on_uninstall'] ) || ! $settings['remove_data_on_uninstall'] ) {
		// Keep all data - exit without removing anything
		return;
	}

	// User has explicitly chosen to remove all data
	// Proceed with complete removal

	// Remove all database tables
	ppq_drop_tables();

	// Remove all options
	ppq_remove_options();

	// Remove all user meta
	ppq_remove_user_meta();

	// Remove all post meta
	ppq_remove_post_meta();

	// Remove capabilities and roles
	ppq_remove_capabilities();

	// Clear any remaining transients
	ppq_clear_transients();
}

/**
 * Drop all plugin database tables
 *
 * @since 1.0.0
 */
function ppq_drop_tables() {
	global $wpdb;

	$tables = [
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

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}

/**
 * Remove all plugin options
 *
 * @since 1.0.0
 */
function ppq_remove_options() {
	global $wpdb;

	// Delete all options that start with ppq_
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s",
			$wpdb->esc_like( 'ppq_' ) . '%'
		)
	);

	// If multisite, delete site options
	if ( is_multisite() ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->sitemeta}
				WHERE meta_key LIKE %s",
				$wpdb->esc_like( 'ppq_' ) . '%'
			)
		);
	}
}

/**
 * Remove all plugin user meta
 *
 * @since 1.0.0
 */
function ppq_remove_user_meta() {
	global $wpdb;

	// Delete all user meta that starts with ppq_
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta}
			WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'ppq_' ) . '%'
		)
	);
}

/**
 * Remove all plugin post meta
 *
 * @since 1.0.0
 */
function ppq_remove_post_meta() {
	global $wpdb;

	// Delete all post meta that starts with ppq_
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta}
			WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'ppq_' ) . '%'
		)
	);
}

/**
 * Remove capabilities from all roles
 *
 * @since 1.0.0
 */
function ppq_remove_capabilities() {
	// Remove capabilities from administrator
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->remove_cap( 'ppq_manage_all' );
		$admin->remove_cap( 'ppq_manage_own' );
		$admin->remove_cap( 'ppq_view_results_all' );
		$admin->remove_cap( 'ppq_view_results_own' );
		$admin->remove_cap( 'ppq_take_quiz' );
		$admin->remove_cap( 'ppq_manage_settings' );
	}

	// Remove capabilities from subscriber
	$subscriber = get_role( 'subscriber' );
	if ( $subscriber ) {
		$subscriber->remove_cap( 'ppq_take_quiz' );
	}

	// Remove custom teacher role
	remove_role( 'ppq_teacher' );
}

/**
 * Clear all plugin transients
 *
 * @since 1.0.0
 */
function ppq_clear_transients() {
	global $wpdb;

	// Delete all transients that start with ppq_
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE %s
			OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_ppq_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_ppq_' ) . '%'
		)
	);

	// If multisite, delete site transients
	if ( is_multisite() ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->sitemeta}
				WHERE meta_key LIKE %s
				OR meta_key LIKE %s",
				$wpdb->esc_like( '_site_transient_ppq_' ) . '%',
				$wpdb->esc_like( '_site_transient_timeout_ppq_' ) . '%'
			)
		);
	}
}

// Run the uninstall
ppq_uninstall();
