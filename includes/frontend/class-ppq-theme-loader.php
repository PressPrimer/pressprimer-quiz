<?php
/**
 * Theme Loader
 *
 * Handles loading and applying visual themes for quizzes.
 *
 * @package PressPrimer_Quiz
 * @subpackage Frontend
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme Loader class
 *
 * Manages theme CSS loading and provides theme-related utilities.
 *
 * @since 1.0.0
 */
class PPQ_Theme_Loader {

	/**
	 * Available themes
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $available_themes = [
		'default' => [
			'name'        => 'Default',
			'description' => 'Clean, professional design with blue accents',
			'file'        => 'default.css',
		],
		'modern' => [
			'name'        => 'Modern',
			'description' => 'Dark mode with bold typography and contemporary styling',
			'file'        => 'modern.css',
		],
		'minimal' => [
			'name'        => 'Minimal',
			'description' => 'Sparse, content-focused design with maximum whitespace',
			'file'        => 'minimal.css',
		],
	];

	/**
	 * Get available themes
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of available themes.
	 */
	public static function get_available_themes() {
		/**
		 * Filter available quiz themes.
		 *
		 * @since 1.0.0
		 *
		 * @param array $themes Available themes.
		 */
		return apply_filters( 'ppq_available_themes', self::$available_themes );
	}

	/**
	 * Get theme info
	 *
	 * @since 1.0.0
	 *
	 * @param string $theme_id Theme identifier.
	 * @return array|null Theme info or null if not found.
	 */
	public static function get_theme( $theme_id ) {
		$themes = self::get_available_themes();
		return isset( $themes[ $theme_id ] ) ? $themes[ $theme_id ] : null;
	}

	/**
	 * Get theme for quiz
	 *
	 * Returns the theme identifier for a quiz, with fallback to default.
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz $quiz Quiz object.
	 * @return string Theme identifier.
	 */
	public static function get_quiz_theme( $quiz ) {
		$theme = 'default';

		// Check quiz-specific theme
		if ( ! empty( $quiz->theme ) ) {
			$theme = $quiz->theme;
		}

		// Validate theme exists
		$themes = self::get_available_themes();
		if ( ! isset( $themes[ $theme ] ) ) {
			$theme = 'default';
		}

		/**
		 * Filter the theme for a specific quiz.
		 *
		 * @since 1.0.0
		 *
		 * @param string   $theme Theme identifier.
		 * @param PPQ_Quiz $quiz Quiz object.
		 */
		return apply_filters( 'ppq_quiz_theme', $theme, $quiz );
	}

	/**
	 * Get theme class for HTML elements
	 *
	 * @since 1.0.0
	 *
	 * @param string $theme_id Theme identifier.
	 * @return string CSS class name.
	 */
	public static function get_theme_class( $theme_id ) {
		return 'ppq-quiz-theme-' . sanitize_html_class( $theme_id );
	}

	/**
	 * Enqueue theme CSS
	 *
	 * @since 1.0.0
	 *
	 * @param string $theme_id Theme identifier.
	 */
	public static function enqueue_theme( $theme_id ) {
		$theme = self::get_theme( $theme_id );

		if ( ! $theme ) {
			$theme_id = 'default';
			$theme    = self::get_theme( 'default' );
		}

		$theme_url  = PPQ_PLUGIN_URL . 'assets/css/themes/' . $theme['file'];
		$theme_path = PPQ_PLUGIN_PATH . 'assets/css/themes/' . $theme['file'];

		// Only enqueue if file exists
		if ( file_exists( $theme_path ) ) {
			wp_enqueue_style(
				'ppq-theme-' . $theme_id,
				$theme_url,
				[ 'ppq-quiz' ],
				PPQ_VERSION
			);
		}

		/**
		 * Fires after theme CSS is enqueued.
		 *
		 * @since 1.0.0
		 *
		 * @param string $theme_id Theme identifier.
		 * @param array  $theme Theme info.
		 */
		do_action( 'ppq_theme_enqueued', $theme_id, $theme );
	}

	/**
	 * Enqueue theme for quiz
	 *
	 * Convenience method to enqueue theme CSS for a quiz.
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz $quiz Quiz object.
	 */
	public static function enqueue_quiz_theme( $quiz ) {
		$theme_id = self::get_quiz_theme( $quiz );
		self::enqueue_theme( $theme_id );
	}

	/**
	 * Get custom CSS for quiz
	 *
	 * Parses theme_settings_json and returns custom CSS overrides.
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz $quiz Quiz object.
	 * @return string Custom CSS or empty string.
	 */
	public static function get_custom_css( $quiz ) {
		if ( empty( $quiz->theme_settings_json ) ) {
			return '';
		}

		$settings = json_decode( $quiz->theme_settings_json, true );

		if ( ! is_array( $settings ) || empty( $settings ) ) {
			return '';
		}

		$css = '';
		$theme_class = self::get_theme_class( self::get_quiz_theme( $quiz ) );

		// Build CSS variable overrides
		$css_vars = [];

		// Primary color override
		if ( ! empty( $settings['primary_color'] ) ) {
			$css_vars['--ppq-primary'] = sanitize_hex_color( $settings['primary_color'] );
		}

		// Success color override
		if ( ! empty( $settings['success_color'] ) ) {
			$css_vars['--ppq-success'] = sanitize_hex_color( $settings['success_color'] );
		}

		// Error color override
		if ( ! empty( $settings['error_color'] ) ) {
			$css_vars['--ppq-error'] = sanitize_hex_color( $settings['error_color'] );
		}

		// Background color override
		if ( ! empty( $settings['background_color'] ) ) {
			$css_vars['--ppq-bg'] = sanitize_hex_color( $settings['background_color'] );
		}

		// Text color override
		if ( ! empty( $settings['text_color'] ) ) {
			$css_vars['--ppq-text'] = sanitize_hex_color( $settings['text_color'] );
		}

		// Border radius override
		if ( isset( $settings['border_radius'] ) && is_numeric( $settings['border_radius'] ) ) {
			$radius                     = absint( $settings['border_radius'] );
			$css_vars['--ppq-radius-md'] = $radius . 'px';
			$css_vars['--ppq-radius-lg'] = ( $radius * 1.5 ) . 'px';
			$css_vars['--ppq-radius-xl'] = ( $radius * 2 ) . 'px';
		}

		// Build CSS output
		if ( ! empty( $css_vars ) ) {
			$css .= ".{$theme_class} {\n";
			foreach ( $css_vars as $property => $value ) {
				$css .= "\t{$property}: {$value};\n";
			}
			$css .= "}\n";
		}

		// Custom CSS block (if allowed)
		if ( ! empty( $settings['custom_css'] ) ) {
			/**
			 * Filter whether to allow custom CSS in theme settings.
			 *
			 * @since 1.0.0
			 *
			 * @param bool $allow Whether to allow custom CSS. Default true.
			 */
			$allow_custom_css = apply_filters( 'ppq_allow_custom_theme_css', true );

			if ( $allow_custom_css ) {
				// Basic sanitization - strip tags and escape
				$custom_css = wp_strip_all_tags( $settings['custom_css'] );
				// Remove potential XSS vectors
				$custom_css = preg_replace( '/(expression|javascript|behavior|vbscript)/i', '', $custom_css );
				$css       .= $custom_css;
			}
		}

		/**
		 * Filter custom CSS output for a quiz.
		 *
		 * @since 1.0.0
		 *
		 * @param string   $css Custom CSS.
		 * @param PPQ_Quiz $quiz Quiz object.
		 * @param array    $settings Theme settings.
		 */
		return apply_filters( 'ppq_quiz_custom_css', $css, $quiz, $settings );
	}

	/**
	 * Output inline custom CSS
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz $quiz Quiz object.
	 */
	public static function output_custom_css( $quiz ) {
		$theme_id = self::get_quiz_theme( $quiz );

		// Get global appearance settings CSS
		$global_css = self::get_global_appearance_css();

		// Get quiz-specific custom CSS
		$quiz_css = self::get_custom_css( $quiz );

		// Combine CSS (global first, then quiz-specific overrides)
		$css = $global_css . $quiz_css;

		if ( ! empty( $css ) ) {
			wp_add_inline_style( 'ppq-theme-' . $theme_id, $css );
		}
	}

	/**
	 * Get global appearance settings CSS
	 *
	 * Generates CSS variable overrides from global plugin settings.
	 * These apply to all themes.
	 *
	 * @since 1.0.0
	 *
	 * @return string CSS string or empty string.
	 */
	public static function get_global_appearance_css() {
		$settings = get_option( PPQ_Admin_Settings::OPTION_NAME, [] );

		$css_vars = [];

		// Font family override
		if ( ! empty( $settings['appearance_font_family'] ) ) {
			$css_vars['--ppq-font-family'] = $settings['appearance_font_family'];
		}

		// Font size override (base font size)
		if ( ! empty( $settings['appearance_font_size'] ) ) {
			$font_size = $settings['appearance_font_size'];
			$css_vars['--ppq-font-size-base'] = $font_size;

			// Calculate relative sizes based on base
			// Parse the pixel value
			$base_px = (float) str_replace( 'px', '', $font_size );
			if ( $base_px > 0 ) {
				$css_vars['--ppq-font-size-xs']  = round( $base_px * 0.75, 2 ) . 'px';
				$css_vars['--ppq-font-size-sm']  = round( $base_px * 0.875, 2 ) . 'px';
				$css_vars['--ppq-font-size-lg']  = round( $base_px * 1.125, 2 ) . 'px';
				$css_vars['--ppq-font-size-xl']  = round( $base_px * 1.25, 2 ) . 'px';
				$css_vars['--ppq-font-size-2xl'] = round( $base_px * 1.5, 2 ) . 'px';
				$css_vars['--ppq-font-size-3xl'] = round( $base_px * 2, 2 ) . 'px';
			}
		}

		// Primary color override
		if ( ! empty( $settings['appearance_primary_color'] ) ) {
			$primary = sanitize_hex_color( $settings['appearance_primary_color'] );
			if ( $primary ) {
				$css_vars['--ppq-primary'] = $primary;
				// Calculate hover color (darken by ~10%)
				$css_vars['--ppq-primary-hover'] = self::adjust_brightness( $primary, -20 );
				// Calculate light variant
				$css_vars['--ppq-primary-light'] = self::hex_to_rgba( $primary, 0.1 );
			}
		}

		// Text color override
		if ( ! empty( $settings['appearance_text_color'] ) ) {
			$text = sanitize_hex_color( $settings['appearance_text_color'] );
			if ( $text ) {
				$css_vars['--ppq-text'] = $text;
			}
		}

		// Background color override
		if ( ! empty( $settings['appearance_background_color'] ) ) {
			$bg = sanitize_hex_color( $settings['appearance_background_color'] );
			if ( $bg ) {
				$css_vars['--ppq-bg'] = $bg;
			}
		}

		// Success color override
		if ( ! empty( $settings['appearance_success_color'] ) ) {
			$success = sanitize_hex_color( $settings['appearance_success_color'] );
			if ( $success ) {
				$css_vars['--ppq-success'] = $success;
				$css_vars['--ppq-success-hover'] = self::adjust_brightness( $success, -20 );
				$css_vars['--ppq-success-light'] = self::hex_to_rgba( $success, 0.15 );
			}
		}

		// Error color override
		if ( ! empty( $settings['appearance_error_color'] ) ) {
			$error = sanitize_hex_color( $settings['appearance_error_color'] );
			if ( $error ) {
				$css_vars['--ppq-error'] = $error;
				$css_vars['--ppq-error-hover'] = self::adjust_brightness( $error, -20 );
				$css_vars['--ppq-error-light'] = self::hex_to_rgba( $error, 0.15 );
			}
		}

		// Border radius override
		if ( isset( $settings['appearance_border_radius'] ) && '' !== $settings['appearance_border_radius'] ) {
			$radius = absint( $settings['appearance_border_radius'] );
			$css_vars['--ppq-radius-sm'] = max( 0, $radius - 2 ) . 'px';
			$css_vars['--ppq-radius-md'] = $radius . 'px';
			$css_vars['--ppq-radius-lg'] = round( $radius * 1.33 ) . 'px';
			$css_vars['--ppq-radius-xl'] = round( $radius * 2 ) . 'px';
		}

		// Build CSS output if we have any overrides
		if ( empty( $css_vars ) ) {
			return '';
		}

		// Apply to all theme class selectors
		$themes = self::get_available_themes();
		$selectors = [];

		foreach ( array_keys( $themes ) as $theme_id ) {
			$class = self::get_theme_class( $theme_id );
			$selectors[] = ".{$class}";
			$selectors[] = ".ppq-quiz-landing.{$class}";
			$selectors[] = ".ppq-quiz-interface.{$class}";
			$selectors[] = ".ppq-results-container.{$class}";
			$selectors[] = ".ppq-question-review-container.{$class}";
		}

		$css = implode( ",\n", $selectors ) . " {\n";
		foreach ( $css_vars as $property => $value ) {
			$css .= "\t{$property}: {$value};\n";
		}
		$css .= "}\n";

		/**
		 * Filter global appearance CSS output.
		 *
		 * @since 1.0.0
		 *
		 * @param string $css Global appearance CSS.
		 * @param array  $settings Plugin settings.
		 */
		return apply_filters( 'ppq_global_appearance_css', $css, $settings );
	}

	/**
	 * Adjust brightness of a hex color
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex    Hex color.
	 * @param int    $amount Amount to adjust (-255 to 255).
	 * @return string Adjusted hex color.
	 */
	private static function adjust_brightness( $hex, $amount ) {
		$hex = ltrim( $hex, '#' );

		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$r = max( 0, min( 255, hexdec( substr( $hex, 0, 2 ) ) + $amount ) );
		$g = max( 0, min( 255, hexdec( substr( $hex, 2, 2 ) ) + $amount ) );
		$b = max( 0, min( 255, hexdec( substr( $hex, 4, 2 ) ) + $amount ) );

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Convert hex color to rgba
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex   Hex color.
	 * @param float  $alpha Alpha value (0-1).
	 * @return string RGBA color string.
	 */
	private static function hex_to_rgba( $hex, $alpha = 1 ) {
		$hex = ltrim( $hex, '#' );

		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		return "rgba({$r}, {$g}, {$b}, {$alpha})";
	}
}
