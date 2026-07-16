<?php
/**
 * Plugin Name:       PressPrimer Quiz
 * Plugin URI:        https://pressprimer.com/quiz
 * Description:       Enterprise-grade quiz and assessment platform for educators with AI question generation, LMS integration, and modern themes.
 * Version:           3.0.2
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
define( 'PRESSPRIMER_QUIZ_VERSION', '3.0.2' );
define( 'PRESSPRIMER_QUIZ_PLUGIN_FILE', __FILE__ );
define( 'PRESSPRIMER_QUIZ_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PRESSPRIMER_QUIZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PRESSPRIMER_QUIZ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PRESSPRIMER_QUIZ_DB_VERSION', '3.0.1' );

// Composer autoloader (for smalot/pdfparser and other vendor dependencies)
if ( file_exists( PRESSPRIMER_QUIZ_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	require_once PRESSPRIMER_QUIZ_PLUGIN_PATH . 'vendor/autoload.php';
}

// Autoloader
require_once PRESSPRIMER_QUIZ_PLUGIN_PATH . 'includes/class-ppq-autoloader.php';
PressPrimer_Quiz_Autoloader::register();

// Front-end shell companion functions (global helpers, not autoloaded).
require_once PRESSPRIMER_QUIZ_PLUGIN_PATH . 'includes/frontend/ppq-shell-functions.php';

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

/**
 * Check whether a premium addon for a given tier is active.
 *
 * Tier-based companion to {@see pressprimer_quiz_addon_active()} (which checks a
 * single slug). Used by upsell surfaces — e.g. the front-end shell's locked nav
 * entries — to decide whether a tier's real screens have replaced its upsell
 * placeholder. Falls back to the addon's load-time constant, mirroring the
 * Upgrade page, so late or skipped addon registration still reads correctly.
 *
 * @since 3.0.0
 *
 * @param string $tier Tier name: 'educator', 'school', or 'enterprise'.
 * @return bool True when an addon for that tier is active.
 */
function pressprimer_quiz_has_addon( $tier ) {
	$manager = pressprimer_quiz_addon_manager();

	foreach ( array_keys( $manager->get_by_tier( $tier ) ) as $slug ) {
		if ( $manager->is_active( $slug ) ) {
			return true;
		}
	}

	$constants = array(
		'educator'   => 'PRESSPRIMER_QUIZ_EDUCATOR_VERSION',
		'school'     => 'PRESSPRIMER_QUIZ_SCHOOL_VERSION',
		'enterprise' => 'PRESSPRIMER_QUIZ_ENTERPRISE_VERSION',
	);

	return isset( $constants[ $tier ] ) && defined( $constants[ $tier ] );
}

/**
 * Render answer text HTML for output.
 *
 * Sanitizes the stored HTML with {@see wp_kses_post()} and then forces every
 * `<a>` link to open in a new tab with a safe `rel` attribute. This prevents
 * a student from accidentally navigating away from an in-progress quiz when
 * an answer contains a link, and blocks the opened page from accessing the
 * parent window via `window.opener`.
 *
 * Registered with PHPCS in phpcs.xml.dist as a recognized escaping function.
 *
 * @since 2.2.2
 *
 * @param string $html Raw answer text (may include HTML).
 * @return string Sanitized HTML with all links targeting `_blank`.
 */
function pressprimer_quiz_render_answer_html( $html ) {
	if ( ! is_string( $html ) || '' === $html ) {
		return '';
	}

	$html = wp_kses_post( $html );

	return preg_replace_callback(
		'#<a\b([^>]*?)>#i',
		static function ( $captures ) {
			// Strip any existing target and rel attributes so our values win.
			$attrs = $captures[1];
			$attrs = preg_replace(
				'#\s+(?:target|rel)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i',
				'',
				$attrs
			);
			return '<a' . rtrim( $attrs ) . ' target="_blank" rel="noopener noreferrer">';
		},
		$html
	);
}

/**
 * Whether math (LaTeX) notation rendering is enabled site-wide.
 *
 * Off by default; enabled via Settings → General. Gates all KaTeX loading and
 * the editor authoring controls.
 *
 * @since 3.0.0
 *
 * @return bool
 */
function pressprimer_quiz_math_enabled() {
	$settings = get_option( 'pressprimer_quiz_settings', array() );

	return is_array( $settings ) && ! empty( $settings['enable_math'] );
}

/**
 * The math delimiter set passed to KaTeX auto-render.
 *
 * Each entry is `{ left, right, display }`. Filterable so the delimiter set is
 * defined in one place for both the JS renderer and the server-side detection
 * helper {@see PressPrimer_Quiz_Helpers::content_has_math()}.
 *
 * @since 3.0.0
 *
 * @return array[]
 */
function pressprimer_quiz_math_delimiters() {
	$delimiters = array(
		array(
			'left'    => '\\(',
			'right'   => '\\)',
			'display' => false,
		),
		array(
			'left'    => '\\[',
			'right'   => '\\]',
			'display' => true,
		),
		array(
			'left'    => '$$',
			'right'   => '$$',
			'display' => true,
		),
	);

	/**
	 * Filters the math delimiter set used for rendering and detection.
	 *
	 * @since 3.0.0
	 *
	 * @param array[] $delimiters Array of `{ left, right, display }` entries.
	 */
	return apply_filters( 'pressprimer_quiz_math_delimiters', $delimiters );
}

/**
 * Register and enqueue the locally-bundled KaTeX assets and the math initializer.
 *
 * Idempotent. Serves KaTeX from the plugin (no external request) and exposes the
 * JS contract `window.PressPrimerQuizMath.typeset( element )`, which front-end
 * pages use to auto-typeset their quiz/results containers and React surfaces use
 * to typeset fetched content. Callers should gate on
 * {@see pressprimer_quiz_math_enabled()} and, where the content is known,
 * {@see PressPrimer_Quiz_Helpers::content_has_math()}.
 *
 * @since 3.0.0
 */
function pressprimer_quiz_enqueue_math_assets() {
	if ( wp_script_is( 'ppq-math', 'enqueued' ) ) {
		return;
	}

	$vendor  = PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/vendor/katex/';
	$version = PRESSPRIMER_QUIZ_VERSION;

	wp_enqueue_style( 'ppq-katex', $vendor . 'katex.min.css', array(), $version );

	wp_register_script( 'ppq-katex', $vendor . 'katex.min.js', array(), $version, true );
	wp_register_script( 'ppq-katex-autorender', $vendor . 'contrib/auto-render.min.js', array( 'ppq-katex' ), $version, true );
	wp_register_script( 'ppq-math', PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/js/ppq-math.js', array( 'ppq-katex-autorender' ), $version, true );

	wp_localize_script(
		'ppq-math',
		'pressprimerQuizMathConfig',
		array(
			'delimiters'    => pressprimer_quiz_math_delimiters(),
			'autoSelectors' => apply_filters(
				'pressprimer_quiz_math_auto_selectors',
				array( '.ppq-quiz-interface', '.ppq-results-container', '.ppq-question-review-container', '.ppq-quiz-preview-wrap' )
			),
		)
	);

	wp_enqueue_script( 'ppq-math' );
}
