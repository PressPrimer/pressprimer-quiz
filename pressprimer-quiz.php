<?php
/**
 * Plugin Name: PressPrimer Quiz
 * Plugin URI: https://pressprimer.com/quiz
 * Description: Enterprise-grade quiz and assessment system for WordPress
 * Version: 0.1.0
 * Author: PressPrimer
 * Author URI: https://pressprimer.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pressprimer-quiz
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package PressPrimerQuiz
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'PRESSPRIMER_QUIZ_VERSION', '0.1.0' );

/**
 * Plugin directory path.
 */
define( 'PRESSPRIMER_QUIZ_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'PRESSPRIMER_QUIZ_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_pressprimer_quiz() {
	// Activation code here
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_pressprimer_quiz() {
	// Deactivation code here
}

register_activation_hook( __FILE__, 'activate_pressprimer_quiz' );
register_deactivation_hook( __FILE__, 'deactivate_pressprimer_quiz' );

/**
 * Begins execution of the plugin.
 */
function run_pressprimer_quiz() {
	// Plugin initialization code here
}

run_pressprimer_quiz();
