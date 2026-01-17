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
 * Nonce Strategy:
 * ---------------
 * This plugin uses grouped nonces by functional area for security:
 *
 * Frontend (quiz-taking):
 * - 'pressprimer_quiz_nonce'    : Quiz flow operations (start, save, sync, submit, check_answer)
 *                         Shared because these are sequential operations in a single session.
 * - 'pressprimer_quiz_email_results' : Email results only (separate as it's a post-completion action)
 *
 * Admin operations use separate nonces per sensitive action:
 * - 'pressprimer_quiz_admin_nonce'   : General admin operations
 * - 'pressprimer_quiz_ai_generation' : AI question generation
 * - 'pressprimer_quiz_save_quiz'     : Quiz creation/editing
 * - 'pressprimer_quiz_save_bank'     : Bank creation/editing
 * - etc.
 *
 * This grouped approach balances security with usability - quiz flow operations
 * share a nonce because they occur within the same user session, while distinct
 * admin actions have separate nonces to prevent CSRF across different operations.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_AJAX_Handler {

	/**
	 * Initialize AJAX handlers
	 *
	 * Registers AJAX actions for both logged-in users and guests.
	 * All handlers verify the 'pressprimer_quiz_nonce' except email_results
	 * which uses 'pressprimer_quiz_email_results'.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Start quiz (logged in and guests)
		add_action( 'wp_ajax_pressprimer_quiz_start_quiz', [ $this, 'start_quiz' ] );
		add_action( 'wp_ajax_nopriv_pressprimer_quiz_start_quiz', [ $this, 'start_quiz' ] );

		// Save answers (logged in and guests)
		add_action( 'wp_ajax_pressprimer_quiz_save_answers', [ $this, 'save_answers' ] );
		add_action( 'wp_ajax_nopriv_pressprimer_quiz_save_answers', [ $this, 'save_answers' ] );

		// Sync active time (logged in and guests)
		add_action( 'wp_ajax_pressprimer_quiz_sync_time', [ $this, 'sync_time' ] );
		add_action( 'wp_ajax_nopriv_pressprimer_quiz_sync_time', [ $this, 'sync_time' ] );

		// Submit quiz (logged in and guests)
		add_action( 'wp_ajax_pressprimer_quiz_submit_quiz', [ $this, 'submit_quiz' ] );
		add_action( 'wp_ajax_nopriv_pressprimer_quiz_submit_quiz', [ $this, 'submit_quiz' ] );

		// Email results (logged in and guests)
		add_action( 'wp_ajax_pressprimer_quiz_email_results', [ $this, 'email_results' ] );
		add_action( 'wp_ajax_nopriv_pressprimer_quiz_email_results', [ $this, 'email_results' ] );

		// Check answer - Tutorial Mode (logged in and guests)
		add_action( 'wp_ajax_pressprimer_quiz_check_answer', [ $this, 'check_answer' ] );
		add_action( 'wp_ajax_nopriv_pressprimer_quiz_check_answer', [ $this, 'check_answer' ] );
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
		if ( ! check_ajax_referer( 'pressprimer_quiz_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed. Please refresh the page and try again.', 'pressprimer-quiz' ),
				]
			);
		}

		// Get quiz ID
		$quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;

		if ( ! $quiz_id ) {
			wp_send_json_error(
				[
					'message' => __( 'Invalid quiz ID.', 'pressprimer-quiz' ),
				]
			);
		}

		// Load quiz
		$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );

		if ( ! $quiz ) {
			wp_send_json_error(
				[
					'message' => __( 'Quiz not found.', 'pressprimer-quiz' ),
				]
			);
		}

		// Check if quiz is published
		if ( 'published' !== $quiz->status ) {
			wp_send_json_error(
				[
					'message' => __( 'This quiz is not available.', 'pressprimer-quiz' ),
				]
			);
		}

		// Get guest email if provided
		$guest_email = isset( $_POST['guest_email'] ) ? sanitize_email( wp_unslash( $_POST['guest_email'] ) ) : '';

		// Capture source URL from POST (nonce-protected) or referer as fallback
		// This stores where the quiz was taken for analytics and redirect purposes
		$source_url = isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '';

		// Only use HTTP_REFERER as fallback, and validate it's from the same domain
		if ( empty( $source_url ) && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referer      = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			$referer_host = wp_parse_url( $referer, PHP_URL_HOST );
			$site_host    = wp_parse_url( home_url(), PHP_URL_HOST );

			// Only accept referer from same domain to prevent spoofing
			if ( $referer_host === $site_host ) {
				$source_url = $referer;
			}
		}

		// Remove query string to avoid issues with ppq_retake, attempt, etc.
		if ( ! empty( $source_url ) ) {
			$source_url = strtok( $source_url, '?' );
		}

		// Create attempt
		$user_id = get_current_user_id();

		if ( $user_id > 0 ) {
			// Logged-in user
			$attempt = PressPrimer_Quiz_Attempt::create_for_user( $quiz_id, $user_id, $source_url );
		} else {
			// Guest user
			$attempt = PressPrimer_Quiz_Attempt::create_for_guest( $quiz_id, $guest_email, $source_url );
		}

		if ( is_wp_error( $attempt ) ) {
			wp_send_json_error(
				[
					'message' => $attempt->get_error_message(),
				]
			);
		}

		// Build response data
		$response_data = [
			'attempt_id' => $attempt->id,
			'message'    => __( 'Quiz started successfully.', 'pressprimer-quiz' ),
		];

		// Include guest token for URL building (guests need this for access)
		if ( ! $user_id && ! empty( $attempt->guest_token ) ) {
			$response_data['guest_token'] = $attempt->guest_token;
		}

		// Return success with attempt ID
		wp_send_json_success( $response_data );
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
		if ( ! check_ajax_referer( 'pressprimer_quiz_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed.', 'pressprimer-quiz' ),
				]
			);
		}

		// Get attempt ID
		$attempt_id = isset( $_POST['attempt_id'] ) ? absint( wp_unslash( $_POST['attempt_id'] ) ) : 0;

		if ( ! $attempt_id ) {
			wp_send_json_error(
				[
					'message' => __( 'Invalid attempt ID.', 'pressprimer-quiz' ),
				]
			);
		}

		// Load attempt
		$attempt = PressPrimer_Quiz_Attempt::get( $attempt_id );

		if ( ! $attempt ) {
			wp_send_json_error(
				[
					'message' => __( 'Attempt not found.', 'pressprimer-quiz' ),
				]
			);
		}

		// Verify user can access this attempt
		if ( ! $this->can_access_attempt( $attempt ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access this attempt.', 'pressprimer-quiz' ),
				]
			);
		}

		// Check if attempt is in progress
		if ( 'in_progress' !== $attempt->status ) {
			wp_send_json_error(
				[
					'message' => __( 'This attempt is not in progress.', 'pressprimer-quiz' ),
				]
			);
		}

		// Check if timed out
		if ( $attempt->is_timed_out() ) {
			wp_send_json_error(
				[
					'message' => __( 'This attempt has timed out.', 'pressprimer-quiz' ),
				]
			);
		}

		// Get and sanitize answers - sanitized by sanitize_answers_array()
		$answers = $this->sanitize_answers_array(
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by helper method
			isset( $_POST['answers'] ) ? wp_unslash( $_POST['answers'] ) : []
		);

		// Get and sanitize confidence values - sanitized by sanitize_confidence_array()
		$confidence_values = $this->sanitize_confidence_array(
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by helper method
			isset( $_POST['confidence'] ) ? wp_unslash( $_POST['confidence'] ) : []
		);

		// If no answers and no confidence values, return error
		if ( empty( $answers ) && empty( $confidence_values ) ) {
			wp_send_json_error(
				[
					'message' => __( 'No answers provided.', 'pressprimer-quiz' ),
				]
			);
		}

		// Save each answer
		$saved_count = 0;
		foreach ( $answers as $item_id => $selected_answers ) {
			// Get confidence for this item if set
			$confidence = $confidence_values[ $item_id ] ?? false;

			// Save answer with confidence
			$result = $attempt->save_answer( $item_id, $selected_answers, $confidence );

			if ( ! is_wp_error( $result ) ) {
				++$saved_count;
			}
		}

		// Save confidence-only updates (when confidence changes without answer change)
		if ( ! empty( $confidence_values ) ) {
			global $wpdb;
			$items_table = $wpdb->prefix . 'ppq_attempt_items';

			foreach ( $confidence_values as $item_id => $confidence_value ) {
				// Skip if we already saved this item with its answer above
				if ( isset( $answers[ $item_id ] ) ) {
					continue;
				}

				// Verify the item belongs to this attempt
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$items_table} WHERE id = %d AND attempt_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$item_id,
						$attempt->id
					)
				);

				if ( ! $exists ) {
					continue;
				}

				// Update confidence only for this item
				$wpdb->update(
					$items_table,
					[ 'confidence' => $confidence_value ? 1 : 0 ],
					[ 'id' => $item_id ],
					[ '%d' ],
					[ '%d' ]
				);

				++$saved_count;
			}
		}

		// Update active elapsed time if provided
		$active_elapsed_ms = isset( $_POST['active_elapsed_ms'] ) ? absint( wp_unslash( $_POST['active_elapsed_ms'] ) ) : 0;
		if ( $active_elapsed_ms > 0 ) {
			$attempt->active_elapsed_ms = $active_elapsed_ms;
			$attempt->save();
		}

		// Return success
		wp_send_json_success(
			[
				'message'     => sprintf(
					/* translators: %d: number of answers saved */
					_n( '%d answer saved.', '%d answers saved.', $saved_count, 'pressprimer-quiz' ),
					$saved_count
				),
				'saved_count' => $saved_count,
			]
		);
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
		if ( ! check_ajax_referer( 'pressprimer_quiz_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed.', 'pressprimer-quiz' ),
				]
			);
		}

		// Get attempt ID
		$attempt_id = isset( $_POST['attempt_id'] ) ? absint( wp_unslash( $_POST['attempt_id'] ) ) : 0;

		if ( ! $attempt_id ) {
			wp_send_json_error(
				[
					'message' => __( 'Invalid attempt ID.', 'pressprimer-quiz' ),
				]
			);
		}

		// Load attempt
		$attempt = PressPrimer_Quiz_Attempt::get( $attempt_id );

		if ( ! $attempt ) {
			wp_send_json_error(
				[
					'message' => __( 'Attempt not found.', 'pressprimer-quiz' ),
				]
			);
		}

		// Verify user can access this attempt
		if ( ! $this->can_access_attempt( $attempt ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access this attempt.', 'pressprimer-quiz' ),
				]
			);
		}

		// Check if attempt is in progress
		if ( 'in_progress' !== $attempt->status ) {
			wp_send_json_error(
				[
					'message' => __( 'This attempt is not in progress.', 'pressprimer-quiz' ),
				]
			);
		}

		// Update active elapsed time
		$active_elapsed_ms = isset( $_POST['active_elapsed_ms'] ) ? absint( wp_unslash( $_POST['active_elapsed_ms'] ) ) : 0;

		if ( $active_elapsed_ms > 0 ) {
			$attempt->active_elapsed_ms = $active_elapsed_ms;
			$attempt->save();
		}

		// Return success (minimal response for efficiency)
		wp_send_json_success(
			[
				'synced' => true,
			]
		);
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
		if ( ! check_ajax_referer( 'pressprimer_quiz_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed.', 'pressprimer-quiz' ),
				]
			);
		}

		// Get attempt ID
		$attempt_id = isset( $_POST['attempt_id'] ) ? absint( wp_unslash( $_POST['attempt_id'] ) ) : 0;

		if ( ! $attempt_id ) {
			wp_send_json_error(
				[
					'message' => __( 'Invalid attempt ID.', 'pressprimer-quiz' ),
				]
			);
		}

		// Load attempt
		$attempt = PressPrimer_Quiz_Attempt::get( $attempt_id );

		if ( ! $attempt ) {
			wp_send_json_error(
				[
					'message' => __( 'Attempt not found.', 'pressprimer-quiz' ),
				]
			);
		}

		// Verify user can access this attempt
		if ( ! $this->can_access_attempt( $attempt ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access this attempt.', 'pressprimer-quiz' ),
				]
			);
		}

		// Check if attempt is in progress
		if ( 'in_progress' !== $attempt->status ) {
			wp_send_json_error(
				[
					'message' => __( 'This attempt has already been submitted.', 'pressprimer-quiz' ),
				]
			);
		}

		// Save final active elapsed time before submission
		$active_elapsed_ms = isset( $_POST['active_elapsed_ms'] ) ? absint( wp_unslash( $_POST['active_elapsed_ms'] ) ) : 0;
		if ( $active_elapsed_ms > 0 ) {
			$attempt->active_elapsed_ms = $active_elapsed_ms;
		}

		// Check if this is a deadline-triggered submission (from JavaScript).
		$deadline_submit = isset( $_POST['deadline_submit'] ) && rest_sanitize_boolean( wp_unslash( $_POST['deadline_submit'] ) );

		/**
		 * Filter to validate and modify attempt before submission.
		 *
		 * Addons can use this to:
		 * 1. Reject late submissions (return WP_Error)
		 * 2. Mark submissions as late (set meta on attempt)
		 * 3. Track assignment-specific data
		 *
		 * @since 2.0.0
		 *
		 * @param PressPrimer_Quiz_Attempt|WP_Error $attempt         Attempt object or WP_Error to reject.
		 * @param bool                              $deadline_submit Whether this was triggered by deadline.
		 */
		$attempt = apply_filters( 'pressprimer_quiz_before_submit', $attempt, $deadline_submit );

		// If filter returned an error, reject the submission.
		if ( is_wp_error( $attempt ) ) {
			wp_send_json_error(
				[
					'message' => $attempt->get_error_message(),
				]
			);
		}

		// Submit attempt (this handles scoring via PressPrimer_Quiz_Scoring_Service)
		$result = $attempt->submit();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'message' => $result->get_error_message(),
				]
			);
		}

		// Send email if auto-send is enabled
		if ( class_exists( 'PressPrimer_Quiz_Email_Service' ) ) {
			PressPrimer_Quiz_Email_Service::maybe_send_on_completion( $attempt->id );
		}

		// Build results URL (includes token for guests)
		$current_url = isset( $_POST['current_url'] ) ? esc_url_raw( wp_unslash( $_POST['current_url'] ) ) : '';

		// Remove any existing attempt/token parameters and rebuild clean URL
		if ( $current_url ) {
			$base_url = remove_query_arg( [ 'attempt', 'token' ], $current_url );
		} else {
			// Fallback: try to use HTTP referer, but validate same domain
			$referer  = wp_get_referer();
			$base_url = '';

			if ( $referer ) {
				$referer_host = wp_parse_url( $referer, PHP_URL_HOST );
				$site_host    = wp_parse_url( home_url(), PHP_URL_HOST );

				if ( $referer_host === $site_host ) {
					$base_url = remove_query_arg( [ 'attempt', 'token' ], $referer );
				}
			}
		}

		// Use attempt's get_results_url which handles tokens for guests
		$redirect_url = $attempt->get_results_url( $base_url );

		// Add timed_out flag if applicable
		if ( isset( $_POST['timed_out'] ) && rest_sanitize_boolean( wp_unslash( $_POST['timed_out'] ) ) ) {
			$redirect_url = add_query_arg( 'timed_out', '1', $redirect_url );
		}

		// Return success with redirect URL
		wp_send_json_success(
			[
				'message'      => __( 'Quiz submitted successfully.', 'pressprimer-quiz' ),
				'redirect_url' => $redirect_url,
				'score'        => [
					'score_percent' => $attempt->score_percent,
					'passed'        => (bool) $attempt->passed,
				],
			]
		);
	}

	/**
	 * Check if current user can access attempt
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @return bool True if user can access.
	 */
	private function can_access_attempt( $attempt ) {
		// Check if user owns this attempt.
		// Cast to int for comparison since database values are strings.
		if ( is_user_logged_in() && absint( $attempt->user_id ) === get_current_user_id() ) {
			return true;
		}

		// Check if guest with valid token (from cookie or POST data)
		// POST data is checked as fallback for environments where cookies may not work
		// (e.g., SSL termination proxies, strict cookie policies)
		if ( ! is_user_logged_in() && $attempt->guest_token ) {
			// First check cookie
			$token = isset( $_COOKIE['pressprimer_quiz_guest_token'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['pressprimer_quiz_guest_token'] ) ) : '';

			// Fallback to POST data if cookie not available
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in calling AJAX handler methods
			if ( empty( $token ) && isset( $_POST['guest_token'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in calling AJAX handler methods
				$token = sanitize_text_field( wp_unslash( $_POST['guest_token'] ) );
			}

			if ( ! empty( $token ) && $token === $attempt->guest_token ) {
				return true;
			}
		}

		// Check if admin
		if ( current_user_can( 'pressprimer_quiz_manage_all' ) ) {
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
		if ( ! check_ajax_referer( 'pressprimer_quiz_email_results', 'nonce', false ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed. Please refresh the page and try again.', 'pressprimer-quiz' ),
				]
			);
		}

		// Get parameters
		$attempt_id = isset( $_POST['attempt_id'] ) ? absint( wp_unslash( $_POST['attempt_id'] ) ) : 0;
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		// Validate parameters
		if ( ! $attempt_id ) {
			wp_send_json_error(
				[
					'message' => __( 'Invalid attempt ID.', 'pressprimer-quiz' ),
				]
			);
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Invalid email address.', 'pressprimer-quiz' ),
				]
			);
		}

		// Load attempt
		$attempt = PressPrimer_Quiz_Attempt::get( $attempt_id );

		if ( ! $attempt ) {
			wp_send_json_error(
				[
					'message' => __( 'Attempt not found.', 'pressprimer-quiz' ),
				]
			);
		}

		// Verify user can access this attempt
		if ( ! $this->can_access_attempt( $attempt ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to email these results.', 'pressprimer-quiz' ),
				]
			);
		}

		// Verify attempt is submitted
		if ( 'submitted' !== $attempt->status ) {
			wp_send_json_error(
				[
					'message' => __( 'Results are not available for this attempt.', 'pressprimer-quiz' ),
				]
			);
		}

		// Send email
		if ( ! class_exists( 'PressPrimer_Quiz_Email_Service' ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Email service is not available.', 'pressprimer-quiz' ),
				]
			);
		}

		$sent = PressPrimer_Quiz_Email_Service::send_results( $attempt_id, $email );

		if ( $sent ) {
			wp_send_json_success(
				[
					'message' => __( 'Results emailed successfully!', 'pressprimer-quiz' ),
				]
			);
		} else {
			wp_send_json_error(
				[
					'message' => __( 'Failed to send email. Please try again later.', 'pressprimer-quiz' ),
				]
			);
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
		if ( ! check_ajax_referer( 'pressprimer_quiz_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Security check failed.', 'pressprimer-quiz' ),
				]
			);
		}

		// Get parameters
		$attempt_id = isset( $_POST['attempt_id'] ) ? absint( wp_unslash( $_POST['attempt_id'] ) ) : 0;
		$item_id    = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;

		if ( ! $attempt_id || ! $item_id ) {
			wp_send_json_error(
				[
					'message' => __( 'Invalid parameters.', 'pressprimer-quiz' ),
				]
			);
		}

		// Load attempt
		$attempt = PressPrimer_Quiz_Attempt::get( $attempt_id );

		if ( ! $attempt ) {
			wp_send_json_error(
				[
					'message' => __( 'Attempt not found.', 'pressprimer-quiz' ),
				]
			);
		}

		// Verify user can access this attempt
		if ( ! $this->can_access_attempt( $attempt ) ) {
			wp_send_json_error(
				[
					'message' => __( 'You do not have permission to access this attempt.', 'pressprimer-quiz' ),
				]
			);
		}

		// Check if attempt is in progress
		if ( 'in_progress' !== $attempt->status ) {
			wp_send_json_error(
				[
					'message' => __( 'This attempt is not in progress.', 'pressprimer-quiz' ),
				]
			);
		}

		// Verify this is a tutorial mode quiz
		$quiz = $attempt->get_quiz();
		if ( ! $quiz || 'tutorial' !== $quiz->mode ) {
			wp_send_json_error(
				[
					'message' => __( 'Answer checking is only available in tutorial mode.', 'pressprimer-quiz' ),
				]
			);
		}

		// Get the attempt item
		$item = PressPrimer_Quiz_Attempt_Item::get( $item_id );

		if ( ! $item || (int) $item->attempt_id !== $attempt_id ) {
			wp_send_json_error(
				[
					'message' => __( 'Question not found.', 'pressprimer-quiz' ),
				]
			);
		}

		// Get selected answers from POST and sanitize immediately
		// Handle both array (multiple choice) and scalar (single choice) inputs
		if ( isset( $_POST['answers'] ) && is_array( $_POST['answers'] ) ) {
			$answers_raw = array_map( 'sanitize_text_field', wp_unslash( $_POST['answers'] ) );
		} elseif ( isset( $_POST['answers'] ) ) {
			$answers_raw = [ sanitize_text_field( wp_unslash( $_POST['answers'] ) ) ];
		} else {
			$answers_raw = [];
		}

		// Convert to integers and filter non-empty values
		$selected_answers = array_map(
			'absint',
			array_filter(
				$answers_raw,
				function ( $v ) {
					return '' !== $v && null !== $v;
				}
			)
		);

		// Save the answer
		$attempt->save_answer( $item_id, $selected_answers );

		// Get question revision for correct answers and feedback
		$revision = $item->get_question_revision();

		if ( ! $revision ) {
			wp_send_json_error(
				[
					'message' => __( 'Question data not found.', 'pressprimer-quiz' ),
				]
			);
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
		$sorted_correct  = $correct_indices;
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
		wp_send_json_success(
			[
				'is_correct'       => $is_correct,
				'correct_indices'  => $correct_indices,
				'selected_indices' => $selected_answers,
				'feedback_text'    => $feedback_text,
			]
		);
	}

	/**
	 * Sanitize answers array from POST data
	 *
	 * Validates and sanitizes the complex nested answers array structure.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $raw_answers Raw POST data for answers.
	 * @return array Sanitized answers array with integer keys and values.
	 */
	private function sanitize_answers_array( $raw_answers ) {
		if ( ! is_array( $raw_answers ) ) {
			return [];
		}

		$sanitized = [];
		foreach ( $raw_answers as $item_id => $selected_answers ) {
			$item_id = absint( $item_id );

			if ( 0 === $item_id ) {
				continue;
			}

			if ( ! is_array( $selected_answers ) ) {
				$selected_answers = [ $selected_answers ];
			}

			$sanitized[ $item_id ] = array_map( 'intval', $selected_answers );
		}

		return $sanitized;
	}

	/**
	 * Sanitize confidence values array from POST data
	 *
	 * Validates and sanitizes the confidence values array structure.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $raw_confidence Raw POST data for confidence values.
	 * @return array Sanitized confidence array with integer keys and boolean values.
	 */
	private function sanitize_confidence_array( $raw_confidence ) {
		if ( ! is_array( $raw_confidence ) ) {
			return [];
		}

		$sanitized = [];
		foreach ( $raw_confidence as $item_id => $confidence_value ) {
			$item_id = absint( $item_id );

			if ( 0 === $item_id ) {
				continue;
			}

			$sanitized[ $item_id ] = (bool) absint( $confidence_value );
		}

		return $sanitized;
	}
}
