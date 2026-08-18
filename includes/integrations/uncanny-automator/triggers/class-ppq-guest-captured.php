<?php
/**
 * Guest Email Captured Trigger
 *
 * Fires when a guest email is captured with marketing consent. This is an
 * "Everyone" (logged-out) trigger — guests are not logged in.
 *
 * @package PressPrimer_Quiz
 * @subpackage Integrations\UncannyAutomator
 * @since 3.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guest Email Captured Trigger
 *
 * @since 3.1.0
 */
class PressPrimer_Quiz_Guest_Captured extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Helpers instance
	 *
	 * @var PressPrimer_Quiz_Automator_Helpers
	 */
	protected $helpers;

	/**
	 * Setup the trigger
	 *
	 * @since 3.1.0
	 */
	protected function setup_trigger() {
		// Get helpers from dependencies.
		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'PPQ' );
		$this->set_trigger_code( 'PPQ_GUEST_CAPTURED' );
		$this->set_trigger_meta( 'PPQ_QUIZ' );

		// Guests are not logged in: both settings are required for the trigger
		// to appear in and fire for "Everyone" (anonymous) recipes. The login
		// flag alone only skips the logged-in check; the type places the
		// trigger in the Everyone recipe builder.
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Quiz title placeholder */
				esc_attr__( 'A guest email is captured with marketing consent for {{a quiz:%1$s}}', 'pressprimer-quiz' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_attr__( 'A guest email is captured with marketing consent for {{a quiz}}', 'pressprimer-quiz' )
		);

		// Hook into the guest email captured action.
		$this->add_action( 'pressprimer_quiz_guest_email_captured', 10, 5 );
	}

	/**
	 * Define trigger options (dropdown fields)
	 *
	 * @since 3.1.0
	 *
	 * @return array Options array.
	 */
	public function options() {
		$quiz_options = array(
			array(
				'text'  => esc_attr__( 'Any quiz', 'pressprimer-quiz' ),
				'value' => '-1',
			),
		);

		$quiz_options = array_merge( $quiz_options, $this->helpers->get_quiz_options() );

		return array(
			array(
				'input_type'  => 'select',
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_attr__( 'Quiz', 'pressprimer-quiz' ),
				'required'    => true,
				'options'     => $quiz_options,
			),
		);
	}

	/**
	 * Validate the trigger
	 *
	 * Fires only when the guest explicitly opted in to marketing consent
	 * (consent === 1). Declined (0) and not-asked (null) never fire.
	 *
	 * @since 3.1.0
	 *
	 * @param array $trigger   Trigger data.
	 * @param array $hook_args Hook arguments.
	 * @return bool True if trigger should fire.
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		// Get action args: attempt_id, quiz_id, email, consent, consent_at.
		list( , $quiz_id, , $consent ) = array_pad( (array) $hook_args, 5, null );

		// Consent gate: strict opt-in only. Null means the checkbox was never
		// offered; 0 means it was shown and declined. Neither fires.
		if ( null === $consent || 1 !== (int) $consent ) {
			return false;
		}

		$selected_quiz = $trigger['meta'][ $this->get_trigger_meta() ];

		// Any quiz.
		if ( '-1' === $selected_quiz || -1 === (int) $selected_quiz ) {
			return true;
		}

		// Specific quiz.
		return (int) $selected_quiz === (int) $quiz_id;
	}

	/**
	 * Define tokens for this trigger
	 *
	 * @since 3.1.0
	 *
	 * @param array $trigger Trigger data.
	 * @param array $tokens  Existing tokens.
	 * @return array Modified tokens.
	 */
	public function define_tokens( $trigger, $tokens ) {
		$tokens[] = array(
			'tokenId'   => 'GUEST_EMAIL',
			'tokenName' => esc_attr__( 'Guest email', 'pressprimer-quiz' ),
			'tokenType' => 'email',
		);

		$tokens[] = array(
			'tokenId'   => 'GUEST_NAME',
			'tokenName' => esc_attr__( 'Guest name', 'pressprimer-quiz' ),
			'tokenType' => 'text',
		);

		$tokens[] = array(
			'tokenId'   => 'QUIZ_ID',
			'tokenName' => esc_attr__( 'Quiz ID', 'pressprimer-quiz' ),
			'tokenType' => 'int',
		);

		$tokens[] = array(
			'tokenId'   => 'QUIZ_TITLE',
			'tokenName' => esc_attr__( 'Quiz title', 'pressprimer-quiz' ),
			'tokenType' => 'text',
		);

		$tokens[] = array(
			'tokenId'   => 'CONSENT_DATE',
			'tokenName' => esc_attr__( 'Consent date', 'pressprimer-quiz' ),
			'tokenType' => 'text',
		);

		$tokens[] = array(
			'tokenId'   => 'SOURCE_URL',
			'tokenName' => esc_attr__( 'Source URL', 'pressprimer-quiz' ),
			'tokenType' => 'url',
		);

		return $tokens;
	}

	/**
	 * Hydrate tokens with actual values
	 *
	 * @since 3.1.0
	 *
	 * @param array $trigger   Trigger data.
	 * @param array $hook_args Hook arguments.
	 * @return array Token values.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $attempt_id, $quiz_id, $email, , $consent_at ) = array_pad( (array) $hook_args, 5, null );

		return $this->helpers->get_guest_capture_token_data( (int) $attempt_id, (int) $quiz_id, (string) $email, $consent_at );
	}
}
