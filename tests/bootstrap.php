<?php
/**
 * PHPUnit Bootstrap
 *
 * Minimal bootstrap for unit tests that don't require WordPress.
 * Defines required constants and loads the plugin autoloader so
 * model classes can be instantiated (and mocked) without a database.
 *
 * @package PressPrimer_Quiz
 * @subpackage Tests
 * @since 2.2.0
 */

// Define ABSPATH so the "prevent direct access" guards don't exit().
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

// Define the plugin path constant used by the autoloader.
define( 'PRESSPRIMER_QUIZ_PLUGIN_PATH', dirname( __DIR__ ) . '/' );

// Load Composer autoloader (PHPUnit, etc.).
require_once PRESSPRIMER_QUIZ_PLUGIN_PATH . 'vendor/autoload.php';

// Register the plugin autoloader so PressPrimer_Quiz_* classes resolve.
require_once PRESSPRIMER_QUIZ_PLUGIN_PATH . 'includes/class-ppq-autoloader.php';
PressPrimer_Quiz_Autoloader::register();

// Stub WordPress functions referenced by model classes during load/mock.
if ( ! function_exists( 'absint' ) ) {
	/**
	 * Stub: Convert a value to a non-negative integer.
	 *
	 * @param mixed $maybeint Data to convert.
	 * @return int
	 */
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Stub: Sanitize a string.
	 *
	 * @param string $str String to sanitize.
	 * @return string
	 */
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Stub: Encode a variable as JSON.
	 *
	 * @param mixed $data Data to encode.
	 * @param int   $options JSON encode options.
	 * @param int   $depth Maximum depth.
	 * @return string|false
	 */
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Stub: Remove slashes added by WordPress.
	 *
	 * @param string|array $value Value to unslash.
	 * @return string|array
	 */
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}
