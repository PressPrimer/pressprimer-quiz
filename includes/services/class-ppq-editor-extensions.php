<?php
/**
 * Editor extensions
 *
 * The addon scripts that load beside the portable editors on the front-end
 * dashboard, registered through pressprimer_quiz_editor_extension_handles.
 * On wp-admin, addons keep enqueueing their own scripts on the editor pages.
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 * @since 3.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor extensions class
 *
 * @since 3.2.0
 */
class PressPrimer_Quiz_Editor_Extensions {

	/**
	 * Surfaces an extension can load on.
	 *
	 * @since 3.2.0
	 * @var string[]
	 */
	const SURFACES = [ 'admin', 'dashboard' ];

	/**
	 * The extension handles to load for a surface and the current user.
	 *
	 * Each entry of the filtered array is `handle => [ 'capability' => string,
	 * 'surfaces' => string[] ]`. `surfaces` defaults to `[ 'dashboard' ]`; a
	 * `capability` the current user lacks leaves the handle out. Handles that
	 * aren't registered scripts are left out too.
	 *
	 * @since 3.2.0
	 *
	 * @param string $surface 'admin' or 'dashboard'.
	 * @return string[] Script handles.
	 */
	public static function get_handles( $surface = 'dashboard' ) {
		if ( ! in_array( $surface, self::SURFACES, true ) ) {
			return [];
		}

		/**
		 * Filters the addon scripts the portable editors load with.
		 *
		 * The scripts register editor slots (window.PPQ.editors.registerSlot())
		 * for the front-end dashboard. Register the script first; free
		 * enqueues it beside the editors for users with the capability.
		 *
		 * @since 3.2.0
		 *
		 * @param array $handles Map of script handle => [
		 *     @type string   $capability Capability the user needs (optional).
		 *     @type string[] $surfaces   'dashboard' and/or 'admin' (default [ 'dashboard' ]).
		 * ].
		 */
		$extensions = apply_filters( 'pressprimer_quiz_editor_extension_handles', [] );

		if ( ! is_array( $extensions ) ) {
			return [];
		}

		$handles = [];

		foreach ( $extensions as $handle => $config ) {
			// A plain list entry is a handle with the defaults.
			if ( is_int( $handle ) && is_string( $config ) ) {
				$handle = $config;
				$config = [];
			}

			if ( ! is_string( $handle ) || '' === $handle || ! is_array( $config ) ) {
				continue;
			}

			$surfaces = isset( $config['surfaces'] ) && is_array( $config['surfaces'] ) ? $config['surfaces'] : [ 'dashboard' ];
			if ( ! in_array( $surface, $surfaces, true ) ) {
				continue;
			}

			$capability = isset( $config['capability'] ) && is_string( $config['capability'] ) ? $config['capability'] : '';
			if ( '' !== $capability && ! current_user_can( $capability ) ) {
				continue;
			}

			if ( ! wp_script_is( $handle, 'registered' ) ) {
				continue;
			}

			$handles[] = $handle;
		}

		return array_values( array_unique( $handles ) );
	}
}
