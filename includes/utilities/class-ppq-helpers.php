<?php
/**
 * Helper functions
 *
 * Utility methods used throughout the plugin.
 *
 * @package PressPrimer_Quiz
 * @subpackage Utilities
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers class
 *
 * Provides static utility methods for common operations.
 *
 * @since 1.0.0
 */
class PPQ_Helpers {

	/**
	 * Get client IP address
	 *
	 * Safely retrieves the user's IP address, accounting for proxies.
	 *
	 * @since 1.0.0
	 *
	 * @return string IP address.
	 */
	public static function get_client_ip() {
		$ip = '';

		// Check for CloudFlare
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// X-Forwarded-For can contain multiple IPs, get the first one
			$ip = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED'];
		} elseif ( ! empty( $_SERVER['HTTP_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_FORWARDED_FOR'];
		} elseif ( ! empty( $_SERVER['HTTP_FORWARDED'] ) ) {
			$ip = $_SERVER['HTTP_FORWARDED'];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		// Sanitize and validate
		$ip = trim( $ip );
		$ip = filter_var( $ip, FILTER_VALIDATE_IP );

		return $ip ? $ip : '0.0.0.0';
	}

	/**
	 * Generate UUID
	 *
	 * Wrapper for WordPress UUID generation.
	 *
	 * @since 1.0.0
	 *
	 * @return string UUID v4.
	 */
	public static function generate_uuid() {
		return wp_generate_uuid4();
	}

	/**
	 * Encrypt data
	 *
	 * Encrypts data using WordPress authentication salts.
	 * Used for storing sensitive data like API keys.
	 *
	 * @since 1.0.0
	 *
	 * @param string $data Data to encrypt.
	 * @return string|WP_Error Encrypted data or WP_Error on failure.
	 */
	public static function encrypt( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		// Check if OpenSSL is available
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return new WP_Error(
				'ppq_no_openssl',
				__( 'OpenSSL is not available for encryption.', 'pressprimer-quiz' )
			);
		}

		$key    = wp_salt( 'auth' );
		$method = 'AES-256-CBC';

		// Generate initialization vector
		$iv_length = openssl_cipher_iv_length( $method );
		$iv        = openssl_random_pseudo_bytes( $iv_length );

		// Encrypt the data
		$encrypted = openssl_encrypt( $data, $method, $key, 0, $iv );

		if ( false === $encrypted ) {
			return new WP_Error(
				'ppq_encryption_failed',
				__( 'Failed to encrypt data.', 'pressprimer-quiz' )
			);
		}

		// Combine IV and encrypted data, then base64 encode
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt data
	 *
	 * Decrypts data that was encrypted with the encrypt() method.
	 *
	 * @since 1.0.0
	 *
	 * @param string $data Encrypted data.
	 * @return string|WP_Error Decrypted data or WP_Error on failure.
	 */
	public static function decrypt( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		// Check if OpenSSL is available
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return new WP_Error(
				'ppq_no_openssl',
				__( 'OpenSSL is not available for decryption.', 'pressprimer-quiz' )
			);
		}

		$key    = wp_salt( 'auth' );
		$method = 'AES-256-CBC';

		// Decode from base64
		$decoded = base64_decode( $data, true );

		if ( false === $decoded ) {
			return new WP_Error(
				'ppq_invalid_data',
				__( 'Invalid encrypted data.', 'pressprimer-quiz' )
			);
		}

		// Extract IV and encrypted data
		$iv_length = openssl_cipher_iv_length( $method );
		$iv        = substr( $decoded, 0, $iv_length );
		$encrypted = substr( $decoded, $iv_length );

		// Decrypt the data
		$decrypted = openssl_decrypt( $encrypted, $method, $key, 0, $iv );

		if ( false === $decrypted ) {
			return new WP_Error(
				'ppq_decryption_failed',
				__( 'Failed to decrypt data.', 'pressprimer-quiz' )
			);
		}

		return $decrypted;
	}

	/**
	 * Format duration
	 *
	 * Converts seconds to human-readable time format.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $seconds    Number of seconds.
	 * @param bool $short_form Use short form (e.g., "2h 30m" instead of "2 hours, 30 minutes").
	 * @return string Formatted duration.
	 */
	public static function format_duration( $seconds, $short_form = false ) {
		$seconds = absint( $seconds );

		if ( 0 === $seconds ) {
			return $short_form ? '0s' : __( '0 seconds', 'pressprimer-quiz' );
		}

		$hours   = floor( $seconds / 3600 );
		$minutes = floor( ( $seconds % 3600 ) / 60 );
		$secs    = $seconds % 60;

		$parts = [];

		if ( $hours > 0 ) {
			if ( $short_form ) {
				$parts[] = $hours . 'h';
			} else {
				/* translators: %d: number of hours */
				$parts[] = sprintf( _n( '%d hour', '%d hours', $hours, 'pressprimer-quiz' ), $hours );
			}
		}

		if ( $minutes > 0 ) {
			if ( $short_form ) {
				$parts[] = $minutes . 'm';
			} else {
				/* translators: %d: number of minutes */
				$parts[] = sprintf( _n( '%d minute', '%d minutes', $minutes, 'pressprimer-quiz' ), $minutes );
			}
		}

		if ( $secs > 0 || empty( $parts ) ) {
			if ( $short_form ) {
				$parts[] = $secs . 's';
			} else {
				/* translators: %d: number of seconds */
				$parts[] = sprintf( _n( '%d second', '%d seconds', $secs, 'pressprimer-quiz' ), $secs );
			}
		}

		if ( $short_form ) {
			return implode( ' ', $parts );
		}

		// Long form with commas
		if ( count( $parts ) > 1 ) {
			$last = array_pop( $parts );
			/* translators: %1$s: formatted time parts, %2$s: last time part */
			return sprintf(
				__( '%1$s and %2$s', 'pressprimer-quiz' ),
				implode( ', ', $parts ),
				$last
			);
		}

		return $parts[0];
	}

	/**
	 * Sanitize array
	 *
	 * Sanitizes an array of values based on type.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $array Array to sanitize.
	 * @param string $type  Sanitization type (text, email, url, int, key).
	 * @return array Sanitized array.
	 */
	public static function sanitize_array( $array, $type = 'text' ) {
		if ( ! is_array( $array ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $array as $key => $value ) {
			$sanitized_key = sanitize_key( $key );

			// Handle nested arrays
			if ( is_array( $value ) ) {
				$sanitized[ $sanitized_key ] = self::sanitize_array( $value, $type );
				continue;
			}

			// Sanitize based on type
			switch ( $type ) {
				case 'email':
					$sanitized[ $sanitized_key ] = sanitize_email( $value );
					break;

				case 'url':
					$sanitized[ $sanitized_key ] = esc_url_raw( $value );
					break;

				case 'int':
					$sanitized[ $sanitized_key ] = absint( $value );
					break;

				case 'key':
					$sanitized[ $sanitized_key ] = sanitize_key( $value );
					break;

				case 'html':
					$sanitized[ $sanitized_key ] = wp_kses_post( $value );
					break;

				case 'textarea':
					$sanitized[ $sanitized_key ] = sanitize_textarea_field( $value );
					break;

				case 'text':
				default:
					$sanitized[ $sanitized_key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Check if LMS plugin is active
	 *
	 * Checks if a specific LMS plugin is installed and active.
	 *
	 * @since 1.0.0
	 *
	 * @param string $lms LMS identifier (learndash, tutor, lifter, learnpress).
	 * @return bool True if LMS is active.
	 */
	public static function is_lms_active( $lms ) {
		$lms = strtolower( $lms );

		switch ( $lms ) {
			case 'learndash':
				return defined( 'LEARNDASH_VERSION' );

			case 'tutor':
			case 'tutorlms':
				return defined( 'TUTOR_VERSION' );

			case 'lifter':
			case 'lifterlms':
				return defined( 'LLMS_PLUGIN_FILE' );

			case 'learnpress':
				return defined( 'LEARNPRESS_VERSION' );

			default:
				return false;
		}
	}

	/**
	 * Format percentage
	 *
	 * Formats a decimal as a percentage.
	 *
	 * @since 1.0.0
	 *
	 * @param float $value    Value to format (0-100 or 0-1).
	 * @param int   $decimals Number of decimal places.
	 * @return string Formatted percentage.
	 */
	public static function format_percentage( $value, $decimals = 1 ) {
		$value = floatval( $value );

		// If value is between 0 and 1, assume it's a decimal (convert to percentage)
		if ( $value > 0 && $value < 1 ) {
			$value = $value * 100;
		}

		return number_format_i18n( $value, $decimals ) . '%';
	}

	/**
	 * Truncate string
	 *
	 * Truncates a string to a specific length with ellipsis.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text   Text to truncate.
	 * @param int    $length Maximum length.
	 * @param string $suffix Suffix to append (default: '...').
	 * @return string Truncated text.
	 */
	public static function truncate( $text, $length = 100, $suffix = '...' ) {
		$text = wp_strip_all_tags( $text );

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		return mb_substr( $text, 0, $length ) . $suffix;
	}

	/**
	 * Get time ago string
	 *
	 * Converts a timestamp to "time ago" format (e.g., "2 hours ago").
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $time Timestamp or MySQL datetime string.
	 * @return string Time ago string.
	 */
	public static function time_ago( $time ) {
		if ( is_string( $time ) ) {
			$time = strtotime( $time );
		}

		$time = absint( $time );

		if ( 0 === $time ) {
			return __( 'Never', 'pressprimer-quiz' );
		}

		return sprintf(
			/* translators: %s: human-readable time difference */
			__( '%s ago', 'pressprimer-quiz' ),
			human_time_diff( $time, current_time( 'timestamp' ) )
		);
	}

	/**
	 * Array get with default
	 *
	 * Safely gets a value from an array with a default.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $array   Array to get from.
	 * @param string $key     Key to get.
	 * @param mixed  $default Default value if key doesn't exist.
	 * @return mixed Value or default.
	 */
	public static function array_get( $array, $key, $default = null ) {
		if ( ! is_array( $array ) ) {
			return $default;
		}

		return isset( $array[ $key ] ) ? $array[ $key ] : $default;
	}

	/**
	 * Check if request is AJAX
	 *
	 * Checks if the current request is an AJAX request.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if AJAX request.
	 */
	public static function is_ajax() {
		return wp_doing_ajax();
	}

	/**
	 * Check if request is REST API
	 *
	 * Checks if the current request is a REST API request.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if REST API request.
	 */
	public static function is_rest() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Generate random string
	 *
	 * Generates a random string of specified length.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $length     Length of string.
	 * @param bool $alphanumeric Use only alphanumeric characters.
	 * @return string Random string.
	 */
	public static function random_string( $length = 32, $alphanumeric = true ) {
		if ( $alphanumeric ) {
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		} else {
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
		}

		$characters_length = strlen( $characters );
		$random_string     = '';

		for ( $i = 0; $i < $length; $i++ ) {
			$random_string .= $characters[ wp_rand( 0, $characters_length - 1 ) ];
		}

		return $random_string;
	}
}
