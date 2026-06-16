<?php
/**
 * Anthropic AI provider.
 *
 * Transport, auth, request shaping, and response normalization for Anthropic's
 * Messages API (feature 004, FR-003). Conventions mirror the PressPrimer
 * Assignment School AI provider (TR-004): same endpoint, anthropic-version
 * header, system-as-parameter request shape, default model, max_tokens, and
 * /v1/models key probe. Mapped to the Quiz orchestrator's neutral message array
 * and normalized result/error vocabulary.
 *
 * @package PressPrimer_Quiz
 * @subpackage Services\AI
 * @since 3.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Anthropic provider.
 *
 * @since 3.0.0
 */
class PressPrimer_Quiz_AI_Provider_Anthropic implements PressPrimer_Quiz_AI_Provider_Interface {

	/**
	 * Anthropic API base URL.
	 *
	 * External service usage is documented in readme.txt under "External
	 * Services". Data is only sent when users explicitly trigger AI generation.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const API_BASE_URL = 'https://api.anthropic.com/v1';

	/**
	 * Messages endpoint.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const ENDPOINT = 'https://api.anthropic.com/v1/messages';

	/**
	 * Anthropic API version header value.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const API_VERSION = '2023-06-01';

	/**
	 * Default model (mirrors PressPrimer Assignment).
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const DEFAULT_MODEL = 'claude-sonnet-4-6';

	/**
	 * Max output tokens (mirrors PressPrimer Assignment).
	 *
	 * @since 3.0.0
	 * @var int
	 */
	const MAX_TOKENS = 50000;

	/**
	 * Request timeout in seconds (AI generation can take minutes).
	 *
	 * @since 3.0.0
	 * @var int
	 */
	const API_TIMEOUT = 300;

	/**
	 * Timeout in seconds for the lightweight key-validation probe.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	const VALIDATION_TIMEOUT = 15;

	/**
	 * Provider id.
	 *
	 * @since 3.0.0
	 *
	 * @return string Provider id.
	 */
	public function get_id(): string {
		return 'anthropic';
	}

	/**
	 * Provider label.
	 *
	 * @since 3.0.0
	 *
	 * @return string Provider label.
	 */
	public function get_label(): string {
		return 'Anthropic';
	}

	/**
	 * Default model id.
	 *
	 * @since 3.0.0
	 *
	 * @return string Model id.
	 */
	public function get_default_model(): string {
		return self::DEFAULT_MODEL;
	}

	/**
	 * Curated, filterable list of Anthropic model ids.
	 *
	 * Set at build time to current models; sites and plugin updates add or
	 * replace ids through pressprimer_quiz_ai_models_anthropic.
	 *
	 * @since 3.0.0
	 *
	 * @return array Model ids.
	 */
	public function get_models(): array {
		$models = array(
			self::DEFAULT_MODEL,
			'claude-opus-4-8',
		);

		/**
		 * Filters the curated Anthropic model id list.
		 *
		 * @since 3.0.0
		 *
		 * @param array $models Model ids.
		 */
		$models = apply_filters( 'pressprimer_quiz_ai_models_anthropic', $models );

		if ( ! is_array( $models ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( $models, 'is_string' ) ) );
	}

	/**
	 * Validate an API key with a minimal no-content probe (GET /v1/models).
	 *
	 * @since 3.0.0
	 *
	 * @param string $api_key API key to validate.
	 * @return true|WP_Error True when valid, WP_Error otherwise.
	 */
	public function validate_key( string $api_key ) {
		$response = wp_remote_get(
			self::API_BASE_URL . '/models',
			array(
				'timeout' => self::VALIDATION_TIMEOUT,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => self::API_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ppq_api_connection_error', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 === $code ) {
			return true;
		}

		if ( 401 === $code ) {
			return new WP_Error(
				'ppq_invalid_key',
				__( 'This API key is invalid. Please check and try again.', 'pressprimer-quiz' )
			);
		}

		if ( 429 === $code ) {
			return new WP_Error(
				'ppq_rate_limited',
				__( 'Rate limited by Anthropic. The key may still be valid — try again later.', 'pressprimer-quiz' )
			);
		}

		return new WP_Error(
			'ppq_api_error',
			sprintf(
				/* translators: %d: HTTP status code */
				__( 'Anthropic returned HTTP %d during validation.', 'pressprimer-quiz' ),
				$code
			)
		);
	}

	/**
	 * Send a request to the Anthropic Messages API.
	 *
	 * Maps the orchestrator's neutral message array to Anthropic's shape: system
	 * messages become the top-level `system` parameter; user/assistant turns
	 * become `messages`. Returns the orchestrator's normalized result shape.
	 *
	 * @since 3.0.0
	 *
	 * @param array $messages Neutral role/content message array.
	 * @param array $options  Request options (api_key, model, max_tokens, timeout).
	 * @return array|WP_Error Normalized result, or WP_Error on failure.
	 */
	public function send( array $messages, array $options ) {
		$model   = isset( $options['model'] ) ? $options['model'] : self::DEFAULT_MODEL;
		$api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
		$timeout = isset( $options['timeout'] ) ? (int) $options['timeout'] : self::API_TIMEOUT;

		// Anthropic caps output tokens; respect a smaller request but never exceed
		// the provider max (mirrors PressPrimer Assignment).
		$max_tokens = isset( $options['max_tokens'] )
			? min( (int) $options['max_tokens'], self::MAX_TOKENS )
			: self::MAX_TOKENS;

		// System messages become the `system` parameter; other turns become
		// `messages`. Anthropic does not accept a 'system' role inside messages.
		$system = '';
		$mapped = array();
		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) || ! isset( $message['role'], $message['content'] ) ) {
				continue;
			}
			if ( 'system' === $message['role'] ) {
				$system .= ( '' === $system ) ? (string) $message['content'] : "\n\n" . (string) $message['content'];
				continue;
			}
			$mapped[] = array(
				'role'    => (string) $message['role'],
				'content' => (string) $message['content'],
			);
		}

		$body = array(
			'model'      => $model,
			'max_tokens' => $max_tokens,
			'messages'   => $mapped,
		);

		if ( '' !== $system ) {
			$body['system'] = $system;
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout'     => $timeout,
				'httpversion' => '1.1',
				'headers'     => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => self::API_VERSION,
					'Content-Type'      => 'application/json',
				),
				'body'        => wp_json_encode( $body ),
				'sslverify'   => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();

			if ( false !== stripos( $error_message, 'timed out' ) || false !== stripos( $error_message, 'timeout' ) ) {
				return new WP_Error(
					'ppq_api_timeout',
					__( 'The request to Anthropic timed out. This can happen with large content or many questions. Please try again with less content or fewer questions.', 'pressprimer-quiz' )
				);
			}

			return new WP_Error(
				'ppq_api_connection_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to connect to Anthropic API: %s', 'pressprimer-quiz' ),
					$error_message
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code ) {
			return new WP_Error(
				'ppq_invalid_key',
				__( 'Invalid API key.', 'pressprimer-quiz' )
			);
		}

		if ( 429 === $code ) {
			return new WP_Error(
				'ppq_rate_limited',
				__( 'Anthropic API rate limit exceeded. Please try again later.', 'pressprimer-quiz' )
			);
		}

		if ( $code >= 500 ) {
			return new WP_Error(
				'ppq_api_server_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Anthropic server error (HTTP %d). This is usually temporary. Please try again in a few moments.', 'pressprimer-quiz' ),
					$code
				)
			);
		}

		if ( 200 !== $code ) {
			return new WP_Error(
				'ppq_api_error',
				isset( $body['error']['message'] )
					? $body['error']['message']
					: sprintf(
						/* translators: %d: HTTP status code */
						__( 'Anthropic returned HTTP %d.', 'pressprimer-quiz' ),
						$code
					)
			);
		}

		// Truncated output (hit max_tokens) is usually cut mid-JSON and would
		// fail the parser; return a clear error instead (mirrors Assignment).
		if ( isset( $body['stop_reason'] ) && 'max_tokens' === $body['stop_reason'] ) {
			return new WP_Error(
				'ppq_response_truncated',
				__( 'The AI response was truncated because it exceeded the maximum output length. Please try again with less content or fewer questions.', 'pressprimer-quiz' )
			);
		}

		if ( ! isset( $body['content'][0]['text'] ) || ! is_string( $body['content'][0]['text'] ) ) {
			return new WP_Error(
				'ppq_invalid_response',
				__( 'Invalid response format from Anthropic.', 'pressprimer-quiz' )
			);
		}

		return array(
			'content' => $body['content'][0]['text'],
			'model'   => $model,
			'usage'   => ( isset( $body['usage'] ) && is_array( $body['usage'] ) ) ? $body['usage'] : array(),
			'raw'     => is_array( $body ) ? $body : array(),
		);
	}
}
