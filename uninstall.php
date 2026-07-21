<?php
/**
 * Uninstall handler
 *
 * Handles complete removal of plugin data when uninstalled.
 * This file is called by WordPress when the user deletes the plugin.
 *
 * IMPORTANT: By default, this script preserves ALL data to prevent accidental
 * data loss. Data is only removed for a site when that site has explicitly
 * enabled "Remove all data on uninstall" in the plugin settings. On multisite,
 * each site is evaluated independently (per-site opt-in). Removable data:
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

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Define plugin path constant if not already defined.
if ( ! defined( 'PRESSPRIMER_QUIZ_PLUGIN_PATH' ) ) {
	define( 'PRESSPRIMER_QUIZ_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 * Remove plugin data.
 *
 * On multisite, each site is evaluated independently: a site's data is removed
 * only if that site has explicitly opted in. Network-global data (user meta and
 * network options) is shared across all sites and cannot be attributed to one
 * site, so it is removed once if at least one site opted in. By default all data
 * is preserved to prevent accidental loss.
 *
 * @since 1.0.0
 */
function pressprimer_quiz_uninstall() {
	if ( is_multisite() ) {
		$site_ids    = get_sites( array( 'fields' => 'ids' ) );
		$any_removed = false;

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );

			if ( pressprimer_quiz_site_wants_removal() ) {
				pressprimer_quiz_remove_site_data();
				$any_removed = true;
			}

			restore_current_blog();
		}

		// User meta and network options are network-global, so remove them once
		// if any site opted in.
		if ( $any_removed ) {
			pressprimer_quiz_remove_user_meta();
			pressprimer_quiz_remove_network_options();
		}
	} elseif ( pressprimer_quiz_site_wants_removal() ) {
		pressprimer_quiz_remove_site_data();
		pressprimer_quiz_remove_user_meta();
	}
}

/**
 * Whether the current site has explicitly opted in to full data removal.
 *
 * Must be evaluated in the target site's context (e.g. after switch_to_blog()).
 * Defaults to false so data is preserved unless explicitly enabled.
 *
 * @since 1.0.0
 *
 * @return bool True if the current site opted in, false otherwise.
 */
function pressprimer_quiz_site_wants_removal() {
	$settings = get_option( 'pressprimer_quiz_settings', array() );

	if ( ! is_array( $settings ) || ! isset( $settings['remove_data_on_uninstall'] ) ) {
		return false;
	}

	$value = $settings['remove_data_on_uninstall'];

	return ( true === $value || '1' === $value || 1 === $value );
}

/**
 * Remove all of the current site's plugin data.
 *
 * Operates on the current blog only; the caller is responsible for the site
 * context (switch_to_blog() on multisite).
 *
 * @since 1.0.0
 */
function pressprimer_quiz_remove_site_data() {
	pressprimer_quiz_drop_tables();
	pressprimer_quiz_remove_options();
	pressprimer_quiz_remove_post_meta();
	pressprimer_quiz_remove_capabilities();
	pressprimer_quiz_clear_transients();
}

/**
 * Drop the current site's plugin database tables.
 *
 * Keep this list in sync with includes/database/class-ppq-schema.php.
 *
 * @since 1.0.0
 */
function pressprimer_quiz_drop_tables() {
	global $wpdb;

	$table_names = array(
		'ppq_questions',
		'ppq_question_revisions',
		'ppq_categories',
		'ppq_question_tax',
		'ppq_banks',
		'ppq_bank_questions',
		'ppq_quizzes',
		'ppq_quiz_items',
		'ppq_quiz_rules',
		'ppq_quiz_templates',
		'ppq_groups',
		'ppq_group_members',
		'ppq_assignments',
		'ppq_attempts',
		'ppq_attempt_items',
		'ppq_events',
	);

	foreach ( $table_names as $table_name ) {
		$full_table_name = $wpdb->prefix . $table_name;
		$wpdb->query( "DROP TABLE IF EXISTS {$full_table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}

/**
 * Remove the current site's plugin options.
 *
 * @since 1.0.0
 */
function pressprimer_quiz_remove_options() {
	global $wpdb;

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( 'pressprimer_quiz_' ) . '%'
		)
	);
}

/**
 * Remove the current site's plugin post meta.
 *
 * @since 1.0.0
 */
function pressprimer_quiz_remove_post_meta() {
	global $wpdb;

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'pressprimer_quiz_' ) . '%'
		)
	);
}

/**
 * Remove plugin capabilities and roles from the current site.
 *
 * @since 1.0.0
 */
function pressprimer_quiz_remove_capabilities() {
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->remove_cap( 'pressprimer_quiz_manage_all' );
		$admin->remove_cap( 'pressprimer_quiz_manage_own' );
		$admin->remove_cap( 'pressprimer_quiz_view_results_all' );
		$admin->remove_cap( 'pressprimer_quiz_view_results_own' );
		$admin->remove_cap( 'pressprimer_quiz_take_quiz' );
		$admin->remove_cap( 'pressprimer_quiz_manage_settings' );
	}

	$subscriber = get_role( 'subscriber' );
	if ( $subscriber ) {
		$subscriber->remove_cap( 'pressprimer_quiz_take_quiz' );
	}

	remove_role( 'pressprimer_quiz_teacher' );
}

/**
 * Clear the current site's plugin transients.
 *
 * @since 1.0.0
 */
function pressprimer_quiz_clear_transients() {
	global $wpdb;

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_pressprimer_quiz_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_pressprimer_quiz_' ) . '%'
		)
	);
}

/**
 * Remove network-global plugin user meta.
 *
 * User meta is shared across all sites in a network, so this runs once.
 *
 * @since 1.0.0
 */
function pressprimer_quiz_remove_user_meta() {
	global $wpdb;

	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'pressprimer_quiz_' ) . '%'
		)
	);
}

/**
 * Remove network-wide plugin options and site transients (multisite).
 *
 * @since 1.0.0
 */
function pressprimer_quiz_remove_network_options() {
	global $wpdb;

	// Network-wide site options.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( 'pressprimer_quiz_' ) . '%'
		)
	);

	// Network site transients.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
			$wpdb->esc_like( '_site_transient_pressprimer_quiz_' ) . '%',
			$wpdb->esc_like( '_site_transient_timeout_pressprimer_quiz_' ) . '%'
		)
	);
}

// Run the uninstall.
pressprimer_quiz_uninstall();
