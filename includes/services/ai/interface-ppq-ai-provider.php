<?php
/**
 * AI provider interface.
 *
 * Contract for a single AI transport provider (OpenAI, Anthropic). The
 * PressPrimer_Quiz_AI_Service orchestrator owns everything provider-neutral
 * (prompts, JSON parsing/repair, retries, timeouts, rate limiting); a provider
 * owns only transport, auth headers, request shaping, and response
 * normalization for one vendor.
 *
 * Internal/unstable in 3.0: providers register via the
 * pressprimer_quiz_ai_providers filter, but the interface is not a public
 * extension promise yet (feature 004, FR-001).
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
 * AI provider contract.
 *
 * @since 3.0.0
 */
interface PressPrimer_Quiz_AI_Provider_Interface {

	/**
	 * Stable provider id (e.g. 'openai', 'anthropic').
	 *
	 * @since 3.0.0
	 *
	 * @return string Provider id.
	 */
	public function get_id(): string;

	/**
	 * Human-readable provider label (proper noun, not translated).
	 *
	 * @since 3.0.0
	 *
	 * @return string Provider label.
	 */
	public function get_label(): string;

	/**
	 * Default model id for this provider.
	 *
	 * @since 3.0.0
	 *
	 * @return string Model id.
	 */
	public function get_default_model(): string;

	/**
	 * Curated, filterable list of selectable model ids for this provider.
	 *
	 * @since 3.0.0
	 *
	 * @return array List of model ids (or model definition arrays).
	 */
	public function get_models(): array;

	/**
	 * Send a chat-style request and return a normalized result.
	 *
	 * The orchestrator passes a neutral message array (each entry
	 * [ 'role' => 'system'|'user'|'assistant', 'content' => string ]) and an
	 * options array ('api_key', 'model', 'max_tokens', 'temperature',
	 * 'timeout'). The provider performs transport/auth/shaping and normalizes
	 * the vendor response to:
	 *
	 *   array(
	 *     'content' => string,  // assistant text (JSON for generation)
	 *     'model'   => string,  // model actually used
	 *     'usage'   => array,   // token usage, vendor shape passed through
	 *     'raw'     => array,   // full decoded response (debugging)
	 *   )
	 *
	 * Retryable transport failures must return a WP_Error whose code the
	 * orchestrator recognizes (ppq_api_timeout, ppq_api_server_error,
	 * ppq_api_connection_error).
	 *
	 * @since 3.0.0
	 *
	 * @param array $messages Neutral role/content message array.
	 * @param array $options  Request options.
	 * @return array|WP_Error Normalized result, or WP_Error on failure.
	 */
	public function send( array $messages, array $options );

	/**
	 * Validate an API key with a minimal, no-content probe.
	 *
	 * @since 3.0.0
	 *
	 * @param string $api_key API key to validate.
	 * @return true|WP_Error True when valid, WP_Error otherwise.
	 */
	public function validate_key( string $api_key );
}
