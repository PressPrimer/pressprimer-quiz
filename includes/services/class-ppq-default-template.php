<?php
/**
 * Default quiz settings template resolver.
 *
 * Owns the pressprimer_quiz_default_template option and resolves it to a usable
 * settings payload for new quizzes (FR-006). The option stores one of:
 *   ''               — no default (hard-coded defaults apply)
 *   'preset:{id}'    — a built-in/addon preset
 *   'template:{id}'  — a saved template row
 * A stored target that no longer exists auto-clears the option.
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 * @since 3.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default template resolver.
 *
 * @since 3.0.0
 */
class PressPrimer_Quiz_Default_Template {

	/**
	 * Option name.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const OPTION = 'pressprimer_quiz_default_template';

	/**
	 * Get the raw stored option value (unvalidated).
	 *
	 * @since 3.0.0
	 *
	 * @return string Option value or ''.
	 */
	public static function get_raw() {
		return (string) get_option( self::OPTION, '' );
	}

	/**
	 * Get the current option, auto-clearing a stale (deleted-target) value.
	 *
	 * @since 3.0.0
	 *
	 * @return string Validated option value, or '' when none/cleared.
	 */
	public static function get_validated() {
		$value = self::get_raw();

		if ( '' === $value ) {
			return '';
		}

		if ( null === self::resolve_value( $value ) ) {
			delete_option( self::OPTION );
			return '';
		}

		return $value;
	}

	/**
	 * Store the default-template option after validating its target.
	 *
	 * An empty value clears the option; an invalid or missing target also clears
	 * it (returns '').
	 *
	 * @since 3.0.0
	 *
	 * @param mixed $value Candidate value ('', 'preset:{id}', 'template:{id}').
	 * @return string The stored value ('' when cleared).
	 */
	public static function set_value( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value || null === self::resolve_value( $value ) ) {
			delete_option( self::OPTION );
			return '';
		}

		update_option( self::OPTION, $value );
		return $value;
	}

	/**
	 * Resolve the current default to its settings payload.
	 *
	 * Auto-clears the option when the stored target no longer exists.
	 *
	 * @since 3.0.0
	 *
	 * @return array|null [ source, name, settings ] or null when none/cleared.
	 */
	public static function resolve() {
		$value = self::get_raw();

		if ( '' === $value ) {
			return null;
		}

		$resolved = self::resolve_value( $value );

		if ( null === $resolved ) {
			delete_option( self::OPTION );
			return null;
		}

		return $resolved;
	}

	/**
	 * Resolve a value string to its settings payload.
	 *
	 * Preset settings pass through the canonical sanitizers; saved-template
	 * settings are already sanitized on write.
	 *
	 * @since 3.0.0
	 *
	 * @param string $value 'preset:{id}' or 'template:{id}'.
	 * @return array|null [ source, name, settings ] or null when invalid/missing.
	 */
	public static function resolve_value( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		if ( 0 === strpos( $value, 'preset:' ) ) {
			$id      = substr( $value, strlen( 'preset:' ) );
			$presets = apply_filters( 'pressprimer_quiz_settings_template_presets', array() );

			if ( is_array( $presets ) && isset( $presets[ $id ] ) && is_array( $presets[ $id ] ) ) {
				$preset   = $presets[ $id ];
				$settings = isset( $preset['settings'] ) && is_array( $preset['settings'] )
					? PressPrimer_Quiz_Quiz::sanitize_settings( $preset['settings'] )
					: array();

				return array(
					'source'   => $value,
					'name'     => isset( $preset['label'] ) ? (string) $preset['label'] : (string) $id,
					'settings' => $settings,
				);
			}

			return null;
		}

		if ( 0 === strpos( $value, 'template:' ) ) {
			$id       = absint( substr( $value, strlen( 'template:' ) ) );
			$template = $id > 0 ? PressPrimer_Quiz_Quiz_Template::get( $id ) : null;

			if ( $template ) {
				return array(
					'source'   => $value,
					'name'     => (string) $template->name,
					'settings' => $template->get_settings(),
				);
			}

			return null;
		}

		return null;
	}
}
