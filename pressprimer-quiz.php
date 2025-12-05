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
define( 'PPQ_VERSION', '1.0.0' );
define( 'PPQ_PLUGIN_FILE', __FILE__ );
define( 'PPQ_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PPQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PPQ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PPQ_DB_VERSION', '1.0.4' );

// Autoloader
require_once PPQ_PLUGIN_PATH . 'includes/class-ppq-autoloader.php';
PressPrimer_Quiz_Autoloader::register();

// Activation/Deactivation hooks
register_activation_hook( __FILE__, [ 'PressPrimer_Quiz_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'PressPrimer_Quiz_Deactivator', 'deactivate' ] );

/**
 * Initialize plugin
 *
 * Initializes the main plugin class.
 *
 * @since 1.0.0
 */
function pressprimer_quiz_init() {
	// Initialize main plugin class
	$plugin = PressPrimer_Quiz_Plugin::get_instance();
	$plugin->run();
}
add_action( 'plugins_loaded', 'pressprimer_quiz_init' );
