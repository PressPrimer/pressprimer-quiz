<?php
/**
 * AJAX Handler
 *
 * Handles all frontend AJAX requests for quiz functionality.
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
 * AJAX Handler class
 *
 * Processes AJAX requests for starting quizzes, saving answers,
 * and submitting attempts.
 *
 * @since 1.0.0
 */
class PPQ_AJAX_Handler {

	/**
	 * Initialize AJAX handlers
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Start quiz (logged in and guests)
		add_action( 'wp_ajax_ppq_start_quiz', [ $this, 'start_quiz' ] );
		add_action( 'wp_ajax_nopriv_ppq_start_quiz', [ $this, 'start_quiz' ] );

		// Save answers (logged in and guests)
		add_action( 'wp_ajax_ppq_save_answers', [ $this, 'save_answers' ] );
		add_action( 'wp_ajax_nopriv_ppq_save_answers', [ $this, 'save_answers' ] );

		// Sync active time (logged in and guests)
		add_action( 'wp_ajax_ppq_sync_time', [ $this, 'sync_time' ] );
		add_action( 'wp_ajax_nopriv_ppq_sync_time', [ $this, 'sync_time' ] );

		// Submit quiz (logged in and guests)
		add_action( 'wp_ajax_ppq_submit_quiz', [ $this, 'submit_quiz' ] );
		add_action( 'wp_ajax_nopriv_ppq_submit_quiz', [ $this, 'submit_quiz' ] );

		// Email results (logged in and guests)
		add_action( 'wp_ajax_ppq_email_results', [ $this, 'email_results' ] );
		add_action( 'wp_ajax_nopriv_ppq_email_results', [ $this, 'email_results' ] );

		// Check answer - Tutorial Mode (logged in and guests)
		add_action( 'wp_ajax_ppq_check_answer', [ $this, 'check_answer' ] );
		add_action( 'wp_ajax_nopriv_ppq_check_answer', [ $this, 'check_answer' ] );
	}

	/**
	 * Start a new quiz attempt
	 *
	 * Creates a new attempt for the specified quiz and returns the attempt ID.
	 *
	 * @since 1.0.0
	 */
	public function start_quiz() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ppq_quiz_nonce', 'nonce', false ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'pressprimer-quiz' ),
			] );
		}

		// Get quiz ID
		$quiz_id = isset( $_POST['quiz_id'] ) ? absint( $_POST['quiz_id'] ) : 0;

		if ( ! $quiz_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid quiz ID.', 'pressprimer-quiz' ),
			] );
		}

		// Load quiz
		$quiz = PPQ_Quiz::get( $quiz_id );

		if ( ! $quiz ) {
			wp_send_json_error( [
				'message' => __( 'Quiz not found.', 'pressprimer-quiz' ),
			] );
		}

		// Check if quiz is published
		if ( 'published' !== $quiz->status ) {
			wp_send_json_error( [
				'message' => __( 'This quiz is not available.', 'pressprimer-quiz' ),
			] );
		}

		// Get guest email if provided
		$guest_email = isset( $_POST['guest_email'] ) ? sanitize_email( $_POST['guest_email'] ) : '';

		// Create attempt
		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			// Logged-in user
			$attempt = PPQ_Attempt::create_for_user( $quiz_id, $user_id );
		} else {
			// Guest user
			$attempt = PPQ_Attempt::create_for_guest( $quiz_id, $guest_email );
		}

		if ( is_wp_error( $attempt ) ) {
			wp_send_json_error( [
				'message' => $attempt->get_error_message(),
			] );
		}

		// Return success with attempt ID
		wp_send_json_success( [
			'attempt_id' => $attempt->id,
			'message'    => __( 'Quiz started successfully.', 'pressprimer-quiz' ),
		] );
	}

	/**
	 * Save answers (auto-save)
	 *
	 * Saves one or more answers to attempt items.
	 *
	 * @since 1.0.0
	 */
	public function save_answers() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ppq_quiz_nonce', 'nonce', false ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'pressprimer-quiz' ),
			] );
		}

		// Get attempt ID
		$attempt_id = isset( $_POST['attempt_id'] ) ? absint( $_POST['attempt_id'] ) : 0;

		if ( ! $attempt_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid attempt ID.', 'pressprimer-quiz' ),
			] );
		}

		// Load attempt
		$attempt = PPQ_Attempt::get( $attempt_id );

		if ( ! $attempt ) {
			wp_send_json_error( [
				'message' => __( 'Attempt not found.', 'pressprimer-quiz' ),
			] );
		}

		// Verify user can access this attempt
		if ( ! $this->can_access_attempt( $attempt ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to access this attempt.', 'pressprimer-quiz' ),
			] );
		}

		// Check if attempt is in progress
		if ( 'in_progress' !== $attempt->status ) {
			wp_send_json_error( [
				'message' => __( 'This attempt is not in progress.', 'pressprimer-quiz' ),
			] );
		}

		// Check if timed out
		if ( $attempt->is_timed_out() ) {
			wp_send_json_error( [
				'message' => __( 'This attempt has timed out.', 'pressprimer-quiz' ),
			] );
		}

		// Get answers
		$answers = isset( $_POST['answers'] ) ? $_POST['answers'] : [];

		if ( empty( $answers ) || ! is_array( $answers ) ) {
			wp_send_json_error( [
				'message' => __( 'No answers provided.', 'pressprimer-quiz' ),
			] );
		}

		// Save each answer
		$saved_count = 0;
		foreach ( $answers as $item_id => $selected_answers ) {
			$item_id = absint( $item_id );

			// Ensure selected_answers is an array
			if ( ! is_array( $selected_answers ) ) {
				$selected_answers = [ $selected_answers ];
			}

			// Convert to integers
			$selected_answers = array_map( 'intval', $selected_answers );

			// Save answer
			$result = $attempt->save_answer( $item_id, $selected_answers );

			if ( ! is_wp_error( $result ) ) {
				$saved_count++;
			}
		}

		// Update active elapsed time if provided
		$active_elapsed_ms = isset( $_POST['active_elapsed_ms'] ) ? absint( $_POST['active_elapsed_ms'] ) : 0;
		if ( $active_elapsed_ms > 0 ) {
			$attempt->active_elapsed_ms = $active_elapsed_ms;
			$attempt->save();
		}

		// Return success
		wp_send_json_success( [
			'message'     => sprintf(
				/* translators: %d: number of answers saved */
				_n( '%d answer saved.', '%d answers saved.', $saved_count, 'pressprimer-quiz' ),
				$saved_count
			),
			'saved_count' => $saved_count,
		] );
	}

	/**
	 * Sync active time
	 *
	 * Lightweight endpoint to update active elapsed time without saving answers.
	 * Called periodically as a heartbeat when no answers are being saved.
	 *
	 * @since 1.0.0
	 */
	public function sync_time() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ppq_quiz_nonce', 'nonce', false ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'pressprimer-quiz' ),
			] );
		}

		// Get attempt ID
		$attempt_id = isset( $_POST['attempt_id'] ) ? absint( $_POST['attempt_id'] ) : 0;

		if ( ! $attempt_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid attempt ID.', 'pressprimer-quiz' ),
			] );
		}

		// Load attempt
		$attempt = PPQ_Attempt::get( $attempt_id );

		if ( ! $attempt ) {
			wp_send_json_error( [
				'message' => __( 'Attempt not found.', 'pressprimer-quiz' ),
			] );
		}

		// Verify user can access this attempt
		if ( ! $this->can_access_attempt( $attempt ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to access this attempt.', 'pressprimer-quiz' ),
			] );
		}

		// Check if attempt is in progress
		if ( 'in_progress' !== $attempt->status ) {
			wp_send_json_error( [
				'message' => __( 'This attempt is not in progress.', 'pressprimer-quiz' ),
			] );
		}

		// Update active elapsed time
		$active_elapsed_ms = isset( $_POST['active_elapsed_ms'] ) ? absint( $_POST['active_elapsed_ms'] ) : 0;

		if ( $active_elapsed_ms > 0 ) {
			$attempt->active_elapsed_ms = $active_elapsed_ms;
			$attempt->save();
		}

		// Return success (minimal response for efficiency)
		wp_send_json_success( [
			'synced' => true,
		] );
	}

	/**
	 * Submit quiz attempt
	 *
	 * Finalizes the attempt, scores it, and returns the results URL.
	 *
	 * @since 1.0.0
	 */
	public function submit_quiz() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ppq_quiz_nonce', 'nonce', false ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'pressprimer-quiz' ),
			] );
		}

		// Get attempt ID
		$attempt_id = isset( $_POST['attempt_id'] ) ? absint( $_POST['attempt_id'] ) : 0;

		if ( ! $attempt_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid attempt ID.', 'pressprimer-quiz' ),
			] );
		}

		// Load attempt
		$attempt = PPQ_Attempt::get( $attempt_id );

		if ( ! $attempt ) {
			wp_send_json_error( [
				'message' => __( 'Attempt not found.', 'pressprimer-quiz' ),
			] );
		}

		// Verify user can access this attempt
		if ( ! $this->can_access_attempt( $attempt ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to access this attempt.', 'pressprimer-quiz' ),
			] );
		}

		// Check if attempt is in progress
		if ( 'in_progress' !== $attempt->status ) {
			wp_send_json_error( [
				'message' => __( 'This attempt has already been submitted.', 'pressprimer-quiz' ),
			] );
		}

		// Save final active elapsed time before submission
		$active_elapsed_ms = isset( $_POST['active_elapsed_ms'] ) ? absint( $_POST['active_elapsed_ms'] ) : 0;
		if ( $active_elapsed_ms > 0 ) {
			$attempt->active_elapsed_ms = $active_elapsed_ms;
		}

		// Submit attempt (this handles scoring via PPQ_Scoring_Service)
		$result = $attempt->submit();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [
				'message' => $result->get_error_message(),
			] );
		}

		// Send email if auto-send is enabled
		if ( class_exists( 'PPQ_Email_Service' ) ) {
			PPQ_Email_Service::maybe_send_on_completion( $attempt->id );
		}

		// Build results URL (includes token for guests)
		$current_url = isset( $_POST['current_url'] ) ? esc_url_raw( $_POST['current_url'] ) : '';

		// Remove any existing attempt/token parameters and rebuild clean URL
		if ( $current_url ) {
			$base_url = remove_query_arg( [ 'attempt', 'token' ], $current_url );
		} else {
			// Fallback: try to use HTTP referer
			$base_url = remove_query_arg( [ 'attempt', 'token' ], wp_get_referer() );
		}

		// Use attempt's get_results_url which handles tokens for guests
		$redirect_url = $attempt->get_results_url( $base_url );

		// Add timed_out flag if applicable
		if ( isset( $_POST['timed_out'] ) && $_POST['timed_out'] ) {
			$redirect_url = add_query_arg( 'timed_out', '1', $redirect_url );
		}

		// Return success with redirect URL
		wp_send_json_success( [
			'message'      => __( 'Quiz submitted successfully.', 'pressprimer-quiz' ),
			'redirect_url' => $redirect_url,
			'score'        => [
				'score_percent' => $attempt->score_percent,
				'passed'        => (bool) $attempt->passed,
			],
		] );
	}

	/**
	 * Check if current user can access attempt
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Attempt $attempt Attempt object.
	 * @return bool True if user can access.
	 */
	private function can_access_attempt( $attempt ) {
		// Check if user owns this attempt
		if ( is_user_logged_in() && $attempt->user_id === get_current_user_id() ) {
			return true;
		}

		// Check if guest with valid token
		if ( ! is_user_logged_in() && $attempt->guest_token ) {
			$token = isset( $_COOKIE['ppq_guest_token'] ) ? sanitize_text_field( $_COOKIE['ppq_guest_token'] ) : '';
			if ( $token === $attempt->guest_token ) {
				return true;
			}
		}

		// Check if admin
		if ( current_user_can( 'ppq_manage_all' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Email quiz results
	 *
	 * Sends quiz results to specified email address.
	 *
	 * @since 1.0.0
	 */
	public function email_results() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ppq_email_results', 'nonce', false ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'pressprimer-quiz' ),
			] );
		}

		// Get parameters
		$attempt_id = isset( $_POST['attempt_id'] ) ? absint( $_POST['attempt_id'] ) : 0;
		$email      = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

		// Validate parameters
		if ( ! $attempt_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid attempt ID.', 'pressprimer-quiz' ),
			] );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( [
				'message' => __( 'Invalid email address.', 'pressprimer-quiz' ),
			] );
		}

		// Load attempt
		$attempt = PPQ_Attempt::get( $attempt_id );

		if ( ! $attempt ) {
			wp_send_json_error( [
				'message' => __( 'Attempt not found.', 'pressprimer-quiz' ),
			] );
		}

		// Verify user can access this attempt
		if ( ! $this->can_access_attempt( $attempt ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to email these results.', 'pressprimer-quiz' ),
			] );
		}

		// Verify attempt is submitted
		if ( 'submitted' !== $attempt->status ) {
			wp_send_json_error( [
				'message' => __( 'Results are not available for this attempt.', 'pressprimer-quiz' ),
			] );
		}

		// Send email
		if ( ! class_exists( 'PPQ_Email_Service' ) ) {
			wp_send_json_error( [
				'message' => __( 'Email service is not available.', 'pressprimer-quiz' ),
			] );
		}

		$sent = PPQ_Email_Service::send_results( $attempt_id, $email );

		if ( $sent ) {
			wp_send_json_success( [
				'message' => __( 'Results emailed successfully!', 'pressprimer-quiz' ),
			] );
		} else {
			wp_send_json_error( [
				'message' => __( 'Failed to send email. Please try again later.', 'pressprimer-quiz' ),
			] );
		}
	}

	/**
	 * Check answer for a single question (Tutorial Mode)
	 *
	 * Validates the answer, saves it, and returns feedback including
	 * which answers are correct/incorrect.
	 *
	 * @since 1.0.0
	 */
	public function check_answer() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ppq_quiz_nonce', 'nonce', false ) ) {
			wp_send_json_error( [
				'message' => __( 'Security check failed.', 'pressprimer-quiz' ),
			] );
		}

		// Get parameters
		$attempt_id = isset( $_POST['attempt_id'] ) ? absint( $_POST['attempt_id'] ) : 0;
		$item_id    = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

		if ( ! $attempt_id || ! $item_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid parameters.', 'pressprimer-quiz' ),
			] );
		}

		// Load attempt
		$attempt = PPQ_Attempt::get( $attempt_id );

		if ( ! $attempt ) {
			wp_send_json_error( [
				'message' => __( 'Attempt not found.', 'pressprimer-quiz' ),
			] );
		}

		// Verify user can access this attempt
		if ( ! $this->can_access_attempt( $attempt ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to access this attempt.', 'pressprimer-quiz' ),
			] );
		}

		// Check if attempt is in progress
		if ( 'in_progress' !== $attempt->status ) {
			wp_send_json_error( [
				'message' => __( 'This attempt is not in progress.', 'pressprimer-quiz' ),
			] );
		}

		// Verify this is a tutorial mode quiz
		$quiz = $attempt->get_quiz();
		if ( ! $quiz || 'tutorial' !== $quiz->mode ) {
			wp_send_json_error( [
				'message' => __( 'Answer checking is only available in tutorial mode.', 'pressprimer-quiz' ),
			] );
		}

		// Get the attempt item
		$item = PPQ_Attempt_Item::get( $item_id );

		if ( ! $item || (int) $item->attempt_id !== $attempt_id ) {
			wp_send_json_error( [
				'message' => __( 'Question not found.', 'pressprimer-quiz' ),
			] );
		}

		// Get selected answers from POST
		$selected_answers = isset( $_POST['answers'] ) ? $_POST['answers'] : [];

		// Ensure selected_answers is an array
		if ( ! is_array( $selected_answers ) ) {
			$selected_answers = [ $selected_answers ];
		}

		// Convert to integers and filter empty values
		$selected_answers = array_map( 'intval', array_filter( $selected_answers, function( $v ) {
			return '' !== $v && null !== $v;
		} ) );

		// Save the answer
		$attempt->save_answer( $item_id, $selected_answers );

		// Get question revision for correct answers and feedback
		$revision = $item->get_question_revision();

		if ( ! $revision ) {
			wp_send_json_error( [
				'message' => __( 'Question data not found.', 'pressprimer-quiz' ),
			] );
		}

		$answers = $revision->get_answers();

		// Determine correct answer indices
		$correct_indices = [];
		foreach ( $answers as $answer_index => $answer ) {
			if ( ! empty( $answer['is_correct'] ) ) {
				$correct_indices[] = $answer_index;
			}
		}

		// Check if the answer is correct
		$sorted_selected = $selected_answers;
		$sorted_correct = $correct_indices;
		sort( $sorted_selected );
		sort( $sorted_correct );
		$is_correct = ( $sorted_selected === $sorted_correct );

		// Get appropriate feedback text
		$feedback_text = '';
		if ( $is_correct && ! empty( $revision->feedback_correct ) ) {
			$feedback_text = $revision->feedback_correct;
		} elseif ( ! $is_correct && ! empty( $revision->feedback_incorrect ) ) {
			$feedback_text = $revision->feedback_incorrect;
		}

		// Return response with correct answers revealed
		wp_send_json_success( [
			'is_correct'       => $is_correct,
			'correct_indices'  => $correct_indices,
			'selected_indices' => $selected_answers,
			'feedback_text'    => $feedback_text,
		] );
	}
}
