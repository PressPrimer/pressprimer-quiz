<?php
/**
 * OpenAI AI provider.
 *
 * Transport, auth, request shaping, and response normalization for OpenAI's
 * Chat Completions API. Extracted verbatim from PressPrimer_Quiz_AI_Service so
 * existing generation behaves byte-identically (feature 004, FR-002) — this is
 * the regression anchor. The orchestrator owns everything provider-neutral.
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
 * OpenAI provider.
 *
 * @since 3.0.0
 */
class PressPrimer_Quiz_AI_Provider_OpenAI implements PressPrimer_Quiz_AI_Provider_Interface {

	/**
	 * OpenAI API base URL.
	 *
	 * External service usage is documented in readme.txt under "External
	 * Services". Data is only sent when users explicitly trigger AI generation.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const API_BASE_URL = 'https://api.openai.com/v1';

	/**
	 * Default model.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const DEFAULT_MODEL = 'gpt-5-mini';

	/**
	 * Request timeout in seconds (AI generation can take minutes).
	 *
	 * @since 3.0.0
	 * @var int
	 */
	const API_TIMEOUT = 300;

	/**
	 * Provider id.
	 *
	 * @since 3.0.0
	 *
	 * @return string Provider id.
	 */
	public function get_id(): string {
		return 'openai';
	}

	/**
	 * Provider label.
	 *
	 * @since 3.0.0
	 *
	 * @return string Provider label.
	 */
	public function get_label(): string {
		return 'OpenAI';
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
	 * Curated, filterable list of OpenAI model ids.
	 *
	 * The Settings UI populates its dropdown from the user's live account via
	 * PressPrimer_Quiz_AI_Service::get_available_models(); this curated list is
	 * the interface's declared default and extension point. Sites and plugin
	 * updates add or replace ids through pressprimer_quiz_ai_models_openai.
	 *
	 * @since 3.0.0
	 *
	 * @return array Model ids.
	 */
	public function get_models(): array {
		$models = array(
			self::DEFAULT_MODEL,
		);

		/**
		 * Filters the curated OpenAI model id list.
		 *
		 * @since 3.0.0
		 *
		 * @param array $models Model ids.
		 */
		$models = apply_filters( 'pressprimer_quiz_ai_models_openai', $models );

		if ( ! is_array( $models ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( $models, 'is_string' ) ) );
	}

	/**
	 * Validate an API key.
	 *
	 * Delegates to the existing validator (a models/list probe with no content)
	 * during the 3.0 transition; the Settings work (Prompt 4.4) consolidates key
	 * validation per provider.
	 *
	 * @since 3.0.0
	 *
	 * @param string $api_key API key to validate.
	 * @return true|WP_Error True when valid, WP_Error otherwise.
	 */
	public function validate_key( string $api_key ) {
		return PressPrimer_Quiz_AI_Service::validate_api_key( $api_key );
	}

	/**
	 * Send a request to the OpenAI Chat Completions API.
	 *
	 * Behavior-identical to the prior PressPrimer_Quiz_AI_Service transport:
	 * same endpoint, headers, request body shaping, error codes/messages, and
	 * content extraction. Returns the orchestrator's normalized result shape.
	 *
	 * @since 3.0.0
	 *
	 * @param array $messages Neutral role/content message array.
	 * @param array $options  Request options (api_key, model, max_tokens, temperature, timeout).
	 * @return array|WP_Error Normalized result, or WP_Error on failure.
	 */
	public function send( array $messages, array $options ) {
		$model   = isset( $options['model'] ) ? $options['model'] : self::DEFAULT_MODEL;
		$api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
		$timeout = isset( $options['timeout'] ) ? (int) $options['timeout'] : self::API_TIMEOUT;

		$request_body = array(
			'model'                 => $model,
			'messages'              => $messages,
			'max_completion_tokens' => isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 64000,
		);

		// GPT-5.x models don't support the temperature parameter (only the
		// default value of 1). Only add temperature for older models.
		if ( isset( $options['temperature'] ) && ! preg_match( '/^gpt-5/', $model ) ) {
			$request_body['temperature'] = $options['temperature'];
		}

		$response = wp_remote_post(
			self::API_BASE_URL . '/chat/completions',
			array(
				'timeout'     => $timeout,
				'httpversion' => '1.1',
				'headers'     => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'        => wp_json_encode( $request_body ),
				'sslverify'   => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();

			// Provide more helpful timeout message
			if ( strpos( $error_message, 'timed out' ) !== false || strpos( $error_message, 'timeout' ) !== false ) {
				return new WP_Error(
					'ppq_api_timeout',
					__( 'The request to OpenAI timed out. This can happen with large content or many questions. Please try again with less content or fewer questions.', 'pressprimer-quiz' )
				);
			}

			return new WP_Error(
				'ppq_api_connection_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to connect to OpenAI API: %s', 'pressprimer-quiz' ),
					$error_message
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
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
				__( 'OpenAI API rate limit exceeded. Please try again later.', 'pressprimer-quiz' )
			);
		}

		// 502 Bad Gateway is usually a server/proxy timeout, not an OpenAI issue.
		// This happens when the hosting server (nginx, Apache, load balancer) times out
		// waiting for the PHP process, which is still waiting for OpenAI.
		if ( 502 === $code ) {
			return new WP_Error(
				'ppq_server_timeout',
				__( 'Server timeout (502 Bad Gateway). This is not an OpenAI issue — your hosting server closed the connection before the AI response was received. OpenAI can take 1-3 minutes for complex requests, but many hosting servers timeout after 30-60 seconds. To fix this, contact your hosting provider about increasing the proxy timeout (nginx: proxy_read_timeout, Apache: ProxyTimeout). As a workaround, try generating fewer questions at once or using shorter content.', 'pressprimer-quiz' )
			);
		}

		// 504 Gateway Timeout is similar to 502 - a proxy/server level timeout.
		if ( 504 === $code ) {
			return new WP_Error(
				'ppq_server_timeout',
				__( 'Server timeout (504 Gateway Timeout). Your hosting server closed the connection before the AI response was received. OpenAI can take 1-3 minutes for complex requests, but many hosting servers timeout after 30-60 seconds. To fix this, contact your hosting provider about increasing the proxy timeout. As a workaround, try generating fewer questions at once or using shorter content.', 'pressprimer-quiz' )
			);
		}

		if ( 500 === $code || 503 === $code ) {
			return new WP_Error(
				'ppq_api_server_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'OpenAI server error (HTTP %d). This is usually temporary. Please try again in a few moments.', 'pressprimer-quiz' ),
					$code
				)
			);
		}

		if ( isset( $body['error'] ) ) {
			return new WP_Error(
				'ppq_api_error',
				isset( $body['error']['message'] )
					? $body['error']['message']
					: __( 'OpenAI API error occurred.', 'pressprimer-quiz' )
			);
		}

		if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
			return new WP_Error(
				'ppq_invalid_response',
				__( 'Invalid response format from OpenAI.', 'pressprimer-quiz' )
			);
		}

		return array(
			'content' => $body['choices'][0]['message']['content'],
			'model'   => $model,
			'usage'   => ( isset( $body['usage'] ) && is_array( $body['usage'] ) ) ? $body['usage'] : array(),
			'raw'     => is_array( $body ) ? $body : array(),
		);
	}
}
