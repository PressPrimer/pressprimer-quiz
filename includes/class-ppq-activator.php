<?php
/**
 * Plugin activation handler
 *
 * Handles tasks that run when the plugin is activated.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activator class
 *
 * Contains all functionality for plugin activation.
 * Sets up database tables, default options, and capabilities.
 *
 * @since 1.0.0
 */
class PPQ_Activator {

	/**
	 * Activate the plugin
	 *
	 * Runs when the plugin is activated.
	 * Sets up database tables, default options, and user capabilities.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		// Check WordPress version
		if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
			wp_die(
				esc_html__( 'PressPrimer Quiz requires WordPress 6.0 or higher.', 'pressprimer-quiz' ),
				esc_html__( 'Plugin Activation Error', 'pressprimer-quiz' ),
				[ 'back_link' => true ]
			);
		}

		// Check PHP version
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			wp_die(
				esc_html__( 'PressPrimer Quiz requires PHP 7.4 or higher.', 'pressprimer-quiz' ),
				esc_html__( 'Plugin Activation Error', 'pressprimer-quiz' ),
				[ 'back_link' => true ]
			);
		}

		// Set default options
		self::set_default_options();

		// Run database migrations
		if ( class_exists( 'PPQ_Migrator' ) ) {
			PPQ_Migrator::maybe_migrate();
		}

		// Setup capabilities
		if ( class_exists( 'PPQ_Capabilities' ) ) {
			PPQ_Capabilities::setup_capabilities();
		}

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set activation flag
		update_option( 'ppq_activation_time', current_time( 'timestamp' ) );
		update_option( 'ppq_version', PPQ_VERSION );
	}

	/**
	 * Set default options
	 *
	 * Creates default plugin settings if they don't exist.
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		// Default settings
		$default_settings = [
			'default_passing_score'     => 70,
			'default_quiz_mode'         => 'tutorial',
			'email_from_name'           => get_bloginfo( 'name' ),
			'email_from_address'        => get_bloginfo( 'admin_email' ),
			'remove_data_on_uninstall'  => false, // Keep data by default for safety
		];

		// Only set if not already exists
		if ( false === get_option( 'ppq_settings' ) ) {
			add_option( 'ppq_settings', $default_settings );
		}
	}
}
