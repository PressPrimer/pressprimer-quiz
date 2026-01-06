<?php
/**
 * Plugin Name:       PressPrimer Quiz
 * Plugin URI:        https://pressprimer.com/quiz
 * Description:       Enterprise-grade quiz and assessment platform for WordPress educators.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            PressPrimer
 * Author URI:        https://pressprimer.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pressprimer-quiz
 * Domain Path:       /languages
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'PRESSPRIMER_QUIZ_VERSION', '1.0.0' );
define( 'PRESSPRIMER_QUIZ_PLUGIN_FILE', __FILE__ );
define( 'PRESSPRIMER_QUIZ_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PRESSPRIMER_QUIZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PRESSPRIMER_QUIZ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PRESSPRIMER_QUIZ_DB_VERSION', '2.0.1' );

// Composer autoloader (for smalot/pdfparser and other vendor dependencies)
if ( file_exists( PRESSPRIMER_QUIZ_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	require_once PRESSPRIMER_QUIZ_PLUGIN_PATH . 'vendor/autoload.php';
}

// Autoloader
require_once PRESSPRIMER_QUIZ_PLUGIN_PATH . 'includes/class-ppq-autoloader.php';
PressPrimer_Quiz_Autoloader::register();

// Activation/Deactivation hooks
register_activation_hook( __FILE__, [ 'PressPrimer_Quiz_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'PressPrimer_Quiz_Deactivator', 'deactivate' ] );

// Multisite: Hook for new site creation to set up tables
add_action( 'wp_initialize_site', [ 'PressPrimer_Quiz_Activator', 'activate_new_site' ], 10, 1 );

/**
 * Initialize plugin
 *
 * Initializes the main plugin class.
 * Hooked to 'init' to comply with WordPress 6.7+ translation loading requirements.
 *
 * @since 1.0.0
 */
function pressprimer_quiz_init() {
	// Initialize main plugin class
	$plugin = PressPrimer_Quiz_Plugin::get_instance();
	$plugin->run();
}
add_action( 'init', 'pressprimer_quiz_init', 0 );

/**
 * Get the addon manager instance
 *
 * Returns the singleton instance of the addon manager for addon registration
 * and compatibility checking.
 *
 * @since 2.0.0
 *
 * @return PressPrimer_Quiz_Addon_Manager The addon manager instance.
 */
function pressprimer_quiz_addon_manager() {
	return PressPrimer_Quiz_Addon_Manager::get_instance();
}

/**
 * Register a premium addon
 *
 * Helper function for addons to register themselves with the addon manager.
 *
 * Example usage:
 * ```php
 * add_action( 'pressprimer_quiz_register_addons', function() {
 *     pressprimer_quiz_register_addon( 'ppq-educator', [
 *         'name'     => 'PressPrimer Quiz Educator',
 *         'version'  => '1.0.0',
 *         'file'     => __FILE__,
 *         'requires' => '2.0.0',
 *         'tier'     => 'educator',
 *     ] );
 * } );
 * ```
 *
 * @since 2.0.0
 *
 * @param string $slug   Unique addon identifier.
 * @param array  $config Addon configuration array.
 * @return bool True on success, false if already registered.
 */
function pressprimer_quiz_register_addon( $slug, $config ) {
	return pressprimer_quiz_addon_manager()->register( $slug, $config );
}

/**
 * Check if a premium addon is active
 *
 * Use this to conditionally enable features that depend on premium addons.
 *
 * @since 2.0.0
 *
 * @param string $slug Addon slug to check.
 * @return bool True if addon is registered and compatible.
 */
function pressprimer_quiz_addon_active( $slug ) {
	return pressprimer_quiz_addon_manager()->is_active( $slug );
}
