<?php
/**
 * Quiz Passed Trigger
 *
 * Fires when a user passes a quiz.
 *
 * @package PressPrimer_Quiz
 * @subpackage Integrations\UncannyAutomator
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quiz Passed Trigger
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Quiz_Passed extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Helpers instance
	 *
	 * @var PressPrimer_Quiz_Automator_Helpers
	 */
	protected $helpers;

	/**
	 * Setup the trigger
	 *
	 * @since 1.0.0
	 */
	protected function setup_trigger() {
		// Get helpers from dependencies.
		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'PPQ' );
		$this->set_trigger_code( 'PPQ_QUIZ_PASSED' );
		$this->set_trigger_meta( 'PPQ_QUIZ' );
		$this->set_is_login_required( true );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Quiz title placeholder */
				esc_attr__( 'A user passes {{a quiz:%1$s}}', 'pressprimer-quiz' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_attr__( 'A user passes {{a quiz}}', 'pressprimer-quiz' )
		);

		// Hook into the quiz passed action.
		$this->add_action( 'pressprimer_quiz_quiz_passed', 10, 2 );
	}

	/**
	 * Define trigger options (dropdown fields)
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
	 *
	 * @param array $trigger   Trigger data.
	 * @param array $hook_args Hook arguments.
	 * @return bool True if trigger should fire.
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$selected_quiz = $trigger['meta'][ $this->get_trigger_meta() ];

		// Get attempt and quiz from hook args.
		list( $attempt, $quiz ) = $hook_args;

		$quiz_id = $quiz->id ?? 0;

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
	 * @since 1.0.0
	 *
	 * @param array $trigger Trigger data.
	 * @param array $tokens  Existing tokens.
	 * @return array Modified tokens.
	 */
	public function define_tokens( $trigger, $tokens ) {
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
			'tokenId'   => 'QUIZ_URL',
			'tokenName' => esc_attr__( 'Quiz URL', 'pressprimer-quiz' ),
			'tokenType' => 'url',
		);

		$tokens[] = array(
			'tokenId'   => 'ATTEMPT_ID',
			'tokenName' => esc_attr__( 'Attempt ID', 'pressprimer-quiz' ),
			'tokenType' => 'int',
		);

		$tokens[] = array(
			'tokenId'   => 'SCORE',
			'tokenName' => esc_attr__( 'Quiz score', 'pressprimer-quiz' ),
			'tokenType' => 'int',
		);

		$tokens[] = array(
			'tokenId'   => 'SCORE_PERCENT',
			'tokenName' => esc_attr__( 'Quiz score (percentage)', 'pressprimer-quiz' ),
			'tokenType' => 'text',
		);

		$tokens[] = array(
			'tokenId'   => 'PASSING_SCORE',
			'tokenName' => esc_attr__( 'Passing score', 'pressprimer-quiz' ),
			'tokenType' => 'int',
		);

		$tokens[] = array(
			'tokenId'   => 'POINTS_EARNED',
			'tokenName' => esc_attr__( 'Points earned', 'pressprimer-quiz' ),
			'tokenType' => 'int',
		);

		$tokens[] = array(
			'tokenId'   => 'POINTS_POSSIBLE',
			'tokenName' => esc_attr__( 'Points possible', 'pressprimer-quiz' ),
			'tokenType' => 'int',
		);

		$tokens[] = array(
			'tokenId'   => 'CORRECT_ANSWERS',
			'tokenName' => esc_attr__( 'Number of correct answers', 'pressprimer-quiz' ),
			'tokenType' => 'int',
		);

		$tokens[] = array(
			'tokenId'   => 'INCORRECT_ANSWERS',
			'tokenName' => esc_attr__( 'Number of incorrect answers', 'pressprimer-quiz' ),
			'tokenType' => 'int',
		);

		$tokens[] = array(
			'tokenId'   => 'TOTAL_QUESTIONS',
			'tokenName' => esc_attr__( 'Total questions', 'pressprimer-quiz' ),
			'tokenType' => 'int',
		);

		$tokens[] = array(
			'tokenId'   => 'TIME_SPENT',
			'tokenName' => esc_attr__( 'Time spent (seconds)', 'pressprimer-quiz' ),
			'tokenType' => 'int',
		);

		$tokens[] = array(
			'tokenId'   => 'TIME_SPENT_FORMATTED',
			'tokenName' => esc_attr__( 'Time spent (formatted)', 'pressprimer-quiz' ),
			'tokenType' => 'text',
		);

		$tokens[] = array(
			'tokenId'   => 'QUESTIONS_AND_ANSWERS',
			'tokenName' => esc_attr__( 'Questions and answers (text)', 'pressprimer-quiz' ),
			'tokenType' => 'text',
		);

		$tokens[] = array(
			'tokenId'   => 'QUESTIONS_AND_ANSWERS_HTML',
			'tokenName' => esc_attr__( 'Questions and answers (HTML)', 'pressprimer-quiz' ),
			'tokenType' => 'text',
		);

		return $tokens;
	}

	/**
	 * Hydrate tokens with actual values
	 *
	 * @since 1.0.0
	 *
	 * @param array $trigger   Trigger data.
	 * @param array $hook_args Hook arguments.
	 * @return array Token values.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $attempt, $quiz ) = $hook_args;

		return $this->helpers->get_quiz_token_data_from_objects( $attempt, $quiz );
	}
}
