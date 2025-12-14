<?php
/**
 * Attempt Model
 *
 * Represents a user's attempt at taking a quiz.
 *
 * @package PressPrimer_Quiz
 * @subpackage Models
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attempt model class
 *
 * Manages quiz attempts including creation, answer saving, submission,
 * and scoring. Supports both logged-in users and guests.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Attempt extends PressPrimer_Quiz_Model {

	/**
	 * Attempt UUID
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $uuid;

	/**
	 * Quiz ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $quiz_id;

	/**
	 * User ID (NULL for guests)
	 *
	 * @since 1.0.0
	 * @var int|null
	 */
	public $user_id;

	/**
	 * Guest email
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $guest_email;

	/**
	 * Guest name
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $guest_name;

	/**
	 * Guest token (for session/resume)
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $guest_token;

	/**
	 * Token expiration timestamp
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $token_expires_at;

	/**
	 * Source URL where the quiz was taken
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $source_url;

	/**
	 * Start timestamp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $started_at;

	/**
	 * Finish timestamp
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $finished_at;

	/**
	 * Elapsed time in milliseconds (wall-clock time)
	 *
	 * @since 1.0.0
	 * @var int|null
	 */
	public $elapsed_ms;

	/**
	 * Active elapsed time in milliseconds (actual engagement time)
	 *
	 * Tracks time when the browser tab is visible/active.
	 * Pauses when tab is hidden, browser is minimized, or device is locked.
	 *
	 * @since 1.0.0
	 * @var int|null
	 */
	public $active_elapsed_ms;

	/**
	 * Score in points
	 *
	 * @since 1.0.0
	 * @var float|null
	 */
	public $score_points;

	/**
	 * Maximum possible points
	 *
	 * @since 1.0.0
	 * @var float|null
	 */
	public $max_points;

	/**
	 * Score as percentage
	 *
	 * @since 1.0.0
	 * @var float|null
	 */
	public $score_percent;

	/**
	 * Whether attempt passed
	 *
	 * @since 1.0.0
	 * @var int|null
	 */
	public $passed;

	/**
	 * Attempt status
	 *
	 * @since 1.0.0
	 * @var string in_progress|submitted|abandoned
	 */
	public $status = 'in_progress';

	/**
	 * Current question position
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $current_position = 0;

	/**
	 * Questions JSON (array of question revision IDs and order)
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $questions_json;

	/**
	 * Metadata JSON
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $meta_json;

	/**
	 * Cached attempt items
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $_items = null;

	/**
	 * Cached quiz object
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Quiz_Quiz|null
	 */
	private $_quiz = null;

	/**
	 * Get table name
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name() {
		return 'ppq_attempts';
	}

	/**
	 * Get fillable fields
	 *
	 * @since 1.0.0
	 *
	 * @return array Field names that can be mass-assigned.
	 */
	protected static function get_fillable_fields() {
		return [
			'uuid',
			'quiz_id',
			'user_id',
			'guest_email',
			'guest_token',
			'source_url',
			'started_at',
			'finished_at',
			'elapsed_ms',
			'active_elapsed_ms',
			'score_points',
			'max_points',
			'score_percent',
			'passed',
			'status',
			'current_position',
			'questions_json',
			'meta_json',
		];
	}

	/**
	 * Create attempt for logged-in user
	 *
	 * Validates permissions, attempt limits, and generates question set.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $quiz_id    Quiz ID.
	 * @param int    $user_id    User ID.
	 * @param string $source_url Optional. URL of the page where the quiz was started.
	 * @return int|WP_Error Attempt ID on success, WP_Error on failure.
	 */
	public static function create_for_user( int $quiz_id, int $user_id, string $source_url = '' ) {
		// Load quiz
		$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
		if ( ! $quiz ) {
			return new WP_Error(
				'ppq_quiz_not_found',
				__( 'Quiz not found.', 'pressprimer-quiz' )
			);
		}

		// Check quiz is published
		if ( 'published' !== $quiz->status ) {
			return new WP_Error(
				'ppq_quiz_not_published',
				__( 'This quiz is not available.', 'pressprimer-quiz' )
			);
		}

		// Check for existing in-progress attempt
		$existing_in_progress = static::get_user_in_progress( $quiz_id, $user_id );
		if ( $existing_in_progress ) {
			// If the attempt can be resumed, return it so the user can continue
			if ( $existing_in_progress->can_resume() ) {
				return $existing_in_progress;
			}

			// If it can't be resumed (timed out or quiz doesn't allow resume), abandon it
			$existing_in_progress->status = 'abandoned';
			$existing_in_progress->save();
		}

		// Check attempt limits
		if ( $quiz->max_attempts ) {
			$previous_attempts = static::get_user_attempts( $quiz_id, $user_id );
			if ( count( $previous_attempts ) >= $quiz->max_attempts ) {
				return new WP_Error(
					'ppq_attempt_limit_reached',
					sprintf(
						/* translators: %d: maximum number of attempts allowed */
						__( 'You have reached the maximum number of attempts (%d) for this quiz.', 'pressprimer-quiz' ),
						$quiz->max_attempts
					)
				);
			}
		}

		// Check attempt delay
		if ( $quiz->attempt_delay_minutes ) {
			$last_attempt = static::get_last_user_attempt( $quiz_id, $user_id );
			if ( $last_attempt && $last_attempt->finished_at ) {
				$elapsed_minutes = ( time() - strtotime( $last_attempt->finished_at ) ) / 60;
				if ( $elapsed_minutes < $quiz->attempt_delay_minutes ) {
					$wait_minutes = ceil( $quiz->attempt_delay_minutes - $elapsed_minutes );
					return new WP_Error(
						'ppq_attempt_too_soon',
						sprintf(
							/* translators: %d: number of minutes to wait */
							_n(
								'Please wait %d minute before retaking this quiz.',
								'Please wait %d minutes before retaking this quiz.',
								$wait_minutes,
								'pressprimer-quiz'
							),
							$wait_minutes
						)
					);
				}
			}
		}

		// Generate questions for this attempt
		$question_ids = $quiz->get_questions_for_attempt();

		if ( empty( $question_ids ) ) {
			return new WP_Error(
				'ppq_no_questions',
				__( 'This quiz has no questions configured.', 'pressprimer-quiz' )
			);
		}

		// Build questions JSON with revision IDs
		$questions_data = [];
		foreach ( $question_ids as $index => $question_id ) {
			$question = PressPrimer_Quiz_Question::get( $question_id );
			if ( ! $question || ! $question->current_revision_id ) {
				continue;
			}

			$questions_data[] = [
				'revision_id' => $question->current_revision_id,
				'order'       => $index + 1,
			];
		}

		if ( empty( $questions_data ) ) {
			return new WP_Error(
				'ppq_no_valid_questions',
				__( 'No valid questions found for this quiz.', 'pressprimer-quiz' )
			);
		}

		// Build meta data including any integration context
		$meta = [];
		if ( ! empty( $GLOBALS['pressprimer_quiz_context'] ) && is_array( $GLOBALS['pressprimer_quiz_context'] ) ) {
			$meta = $GLOBALS['pressprimer_quiz_context'];
			unset( $GLOBALS['pressprimer_quiz_context'] );
		}

		// Create attempt
		$attempt_data = [
			'uuid'             => wp_generate_uuid4(),
			'quiz_id'          => $quiz_id,
			'user_id'          => $user_id,
			'guest_email'      => null,
			'guest_token'      => null,
			'source_url'       => $source_url ?: null,
			'status'           => 'in_progress',
			'current_position' => 0,
			'questions_json'   => wp_json_encode( $questions_data ),
			'meta_json'        => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
			'started_at'       => current_time( 'mysql' ),
		];

		$attempt_id = static::create( $attempt_data );

		if ( is_wp_error( $attempt_id ) ) {
			return $attempt_id;
		}

		// Create attempt items for each question
		global $wpdb;
		$items_table = $wpdb->prefix . 'ppq_attempt_items';

		foreach ( $questions_data as $question_data ) {
			$item_data = [
				'attempt_id'            => $attempt_id,
				'question_revision_id'  => $question_data['revision_id'],
				'order_index'           => $question_data['order'],
				'selected_answers_json' => '[]',
			];
			$formats   = [ '%d', '%d', '%d', '%s' ];

			// Generate randomized answer order if quiz setting enabled
			if ( $quiz->randomize_answers ) {
				$answer_order = static::generate_answer_order( $question_data['revision_id'] );
				if ( $answer_order ) {
					$item_data['answer_order_json'] = $answer_order;
					$formats[]                      = '%s';
				}
			}

			$result = $wpdb->insert( $items_table, $item_data, $formats );

			// Check for insert error
			if ( false === $result ) {
				// Delete the attempt we just created since items failed
				$wpdb->delete(
					static::get_full_table_name(),
					[ 'id' => $attempt_id ],
					[ '%d' ]
				);

				return new WP_Error(
					'ppq_item_creation_failed',
					sprintf(
						/* translators: %s: database error message */
						__( 'Failed to create quiz questions: %s', 'pressprimer-quiz' ),
						$wpdb->last_error
					)
				);
			}
		}

		// Load and return the attempt object
		return static::get( $attempt_id );
	}

	/**
	 * Create attempt for guest
	 *
	 * Creates attempt with guest email and secure token.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $quiz_id    Quiz ID.
	 * @param string $email      Guest email address.
	 * @param string $source_url Optional. URL of the page where the quiz was started.
	 * @return int|WP_Error Attempt ID on success, WP_Error on failure.
	 */
	public static function create_for_guest( int $quiz_id, string $email, string $source_url = '' ) {
		// Validate email only if provided (email is optional for guests)
		$email = sanitize_email( $email );
		if ( ! empty( $email ) && ! is_email( $email ) ) {
			return new WP_Error(
				'ppq_invalid_email',
				__( 'Please provide a valid email address.', 'pressprimer-quiz' )
			);
		}

		// Load quiz
		$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
		if ( ! $quiz ) {
			return new WP_Error(
				'ppq_quiz_not_found',
				__( 'Quiz not found.', 'pressprimer-quiz' )
			);
		}

		// Check quiz is published
		if ( 'published' !== $quiz->status ) {
			return new WP_Error(
				'ppq_quiz_not_published',
				__( 'This quiz is not available.', 'pressprimer-quiz' )
			);
		}

		// Check for existing in-progress attempt for this email (only if email provided)
		if ( ! empty( $email ) ) {
			$existing_in_progress = static::get_guest_in_progress( $quiz_id, $email );
			if ( $existing_in_progress ) {
				// If the attempt can be resumed, return it so the user can continue
				if ( $existing_in_progress->can_resume() ) {
					return $existing_in_progress;
				}

				// If it can't be resumed (timed out or quiz doesn't allow resume), abandon it
				$existing_in_progress->status = 'abandoned';
				$existing_in_progress->save();
			}
		}

		// Generate questions for this attempt
		$question_ids = $quiz->get_questions_for_attempt();

		if ( empty( $question_ids ) ) {
			return new WP_Error(
				'ppq_no_questions',
				__( 'This quiz has no questions configured.', 'pressprimer-quiz' )
			);
		}

		// Build questions JSON with revision IDs
		$questions_data = [];
		foreach ( $question_ids as $index => $question_id ) {
			$question = PressPrimer_Quiz_Question::get( $question_id );
			if ( ! $question || ! $question->current_revision_id ) {
				continue;
			}

			$questions_data[] = [
				'revision_id' => $question->current_revision_id,
				'order'       => $index + 1,
			];
		}

		if ( empty( $questions_data ) ) {
			return new WP_Error(
				'ppq_no_valid_questions',
				__( 'No valid questions found for this quiz.', 'pressprimer-quiz' )
			);
		}

		// Generate secure token (64 character random string)
		$token = bin2hex( random_bytes( 32 ) );

		// Set token expiration to 30 days from now
		$token_expires = gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) );

		// Create attempt
		$attempt_data = [
			'uuid'             => wp_generate_uuid4(),
			'quiz_id'          => $quiz_id,
			'user_id'          => null,
			'guest_email'      => $email,
			'guest_token'      => $token,
			'token_expires_at' => $token_expires,
			'source_url'       => $source_url ?: null,
			'status'           => 'in_progress',
			'current_position' => 0,
			'questions_json'   => wp_json_encode( $questions_data ),
			'started_at'       => current_time( 'mysql' ),
		];

		$attempt_id = static::create( $attempt_data );

		if ( is_wp_error( $attempt_id ) ) {
			return $attempt_id;
		}

		// Create attempt items for each question
		global $wpdb;
		$items_table = $wpdb->prefix . 'ppq_attempt_items';

		foreach ( $questions_data as $question_data ) {
			$item_data = [
				'attempt_id'            => $attempt_id,
				'question_revision_id'  => $question_data['revision_id'],
				'order_index'           => $question_data['order'],
				'selected_answers_json' => '[]',
			];
			$formats   = [ '%d', '%d', '%d', '%s' ];

			// Generate randomized answer order if quiz setting enabled
			if ( $quiz->randomize_answers ) {
				$answer_order = static::generate_answer_order( $question_data['revision_id'] );
				if ( $answer_order ) {
					$item_data['answer_order_json'] = $answer_order;
					$formats[]                      = '%s';
				}
			}

			$result = $wpdb->insert( $items_table, $item_data, $formats );

			// Check for insert error
			if ( false === $result ) {
				// Delete the attempt we just created since items failed
				$wpdb->delete(
					static::get_full_table_name(),
					[ 'id' => $attempt_id ],
					[ '%d' ]
				);

				return new WP_Error(
					'ppq_item_creation_failed',
					sprintf(
						/* translators: %s: database error message */
						__( 'Failed to create quiz questions: %s', 'pressprimer-quiz' ),
						$wpdb->last_error
					)
				);
			}
		}

		// Set guest token cookie (7 days)
		setcookie( 'ppq_guest_token', $token, time() + ( 7 * 24 * 60 * 60 ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		// Load and return the attempt object
		return static::get( $attempt_id );
	}

	/**
	 * Get attempt items
	 *
	 * Returns all attempt items for this attempt.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force_refresh Force refresh from database.
	 * @return array Array of PressPrimer_Quiz_Attempt_Item objects.
	 */
	public function get_items( bool $force_refresh = false ) {
		// Return cached items if available
		if ( null !== $this->_items && ! $force_refresh ) {
			return $this->_items;
		}

		global $wpdb;
		$items_table = $wpdb->prefix . 'ppq_attempt_items';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$items_table} WHERE attempt_id = %d ORDER BY order_index ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->id
			)
		);

		$this->_items = [];

		if ( $rows ) {
			foreach ( $rows as $row ) {
				$this->_items[] = PressPrimer_Quiz_Attempt_Item::from_row( $row );
			}
		}

		return $this->_items;
	}

	/**
	 * Save answer for a question
	 *
	 * Saves student's selected answer(s) for a specific question.
	 * Accepts either an attempt_item_id or question_revision_id.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $item_or_revision_id Attempt item ID or question revision ID.
	 * @param array $selected_answers Array of selected answer indices.
	 * @param bool  $confidence Whether student is confident in answer.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function save_answer( int $item_or_revision_id, array $selected_answers, bool $confidence = false ) {
		// Validate attempt is in progress
		if ( 'in_progress' !== $this->status ) {
			return new WP_Error(
				'ppq_attempt_completed',
				__( 'This attempt has already been submitted.', 'pressprimer-quiz' )
			);
		}

		global $wpdb;
		$items_table = $wpdb->prefix . 'ppq_attempt_items';

		// First, try to find by attempt_item_id (this is what the frontend sends)
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$items_table} WHERE id = %d AND attempt_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$item_or_revision_id,
				$this->id
			)
		);

		// If not found by item ID, try by question_revision_id (backwards compatibility)
		if ( ! $existing ) {
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$items_table} WHERE attempt_id = %d AND question_revision_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$this->id,
					$item_or_revision_id
				)
			);
		}

		if ( ! $existing ) {
			return new WP_Error(
				'ppq_invalid_question',
				__( 'This question is not part of this quiz attempt.', 'pressprimer-quiz' )
			);
		}

		$answer_data = [
			'selected_answers_json' => wp_json_encode( $selected_answers ),
			'last_answer_at'        => current_time( 'mysql' ),
			'confidence'            => $confidence ? 1 : 0,
		];

		// Update the existing item
		$wpdb->update(
			$items_table,
			$answer_data,
			[ 'id' => $existing->id ],
			[ '%s', '%s', '%d' ],
			[ '%d' ]
		);

		// Clear cached items
		$this->_items = null;

		return true;
	}

	/**
	 * Update confidence only for an attempt item
	 *
	 * Updates just the confidence value without changing the answer.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $item_id    Attempt item ID.
	 * @param bool $confidence Confidence value.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_confidence( int $item_id, bool $confidence ) {
		// Validate attempt is in progress
		if ( 'in_progress' !== $this->status ) {
			return new WP_Error(
				'ppq_attempt_completed',
				__( 'This attempt has already been submitted.', 'pressprimer-quiz' )
			);
		}

		global $wpdb;
		$items_table = $wpdb->prefix . 'ppq_attempt_items';

		// Verify the item belongs to this attempt
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$items_table} WHERE id = %d AND attempt_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$item_id,
				$this->id
			)
		);

		if ( ! $existing ) {
			return new WP_Error(
				'ppq_invalid_item',
				__( 'This question is not part of this quiz attempt.', 'pressprimer-quiz' )
			);
		}

		// Update confidence
		$wpdb->update(
			$items_table,
			[ 'confidence' => $confidence ? 1 : 0 ],
			[ 'id' => $item_id ],
			[ '%d' ],
			[ '%d' ]
		);

		// Clear cached items
		$this->_items = null;

		return true;
	}

	/**
	 * Submit attempt
	 *
	 * Finalizes attempt, calculates score, and marks as submitted.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function submit() {
		// Validate attempt is in progress
		if ( 'in_progress' !== $this->status ) {
			return new WP_Error(
				'ppq_attempt_already_submitted',
				__( 'This attempt has already been submitted.', 'pressprimer-quiz' )
			);
		}

		// Note: We don't check is_timed_out() here because we want to allow
		// submission even if time has expired - this saves the user's answers.
		// The timeout check is enforced when resuming attempts or saving new answers.

		// Calculate elapsed time using WordPress timezone-aware functions
		// started_at is stored in WordPress local time via current_time('mysql')
		$started_timestamp = strtotime( get_gmt_from_date( $this->started_at ) );
		$now               = time(); // UTC timestamp
		$elapsed_seconds   = $now - $started_timestamp;
		$elapsed_ms        = $elapsed_seconds * 1000;

		// Score the attempt - this updates scores in the database
		$scoring_result = $this->score_attempt();

		if ( is_wp_error( $scoring_result ) ) {
			return $scoring_result;
		}

		// Reload from database to get updated score values
		$refreshed = static::get( $this->id );
		if ( $refreshed ) {
			$this->score_points  = $refreshed->score_points;
			$this->max_points    = $refreshed->max_points;
			$this->score_percent = $refreshed->score_percent;
			$this->passed        = $refreshed->passed;
		}

		// Get quiz to check passing percentage
		$quiz   = $this->get_quiz();
		$passed = $this->score_percent >= $quiz->pass_percent ? 1 : 0;

		// Update attempt
		$this->status      = 'submitted';
		$this->finished_at = current_time( 'mysql' );
		$this->elapsed_ms  = $elapsed_ms;
		$this->passed      = $passed;

		$result = $this->save();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after a quiz attempt is submitted.
		 *
		 * @since 1.0.0
		 *
		 * @param PressPrimer_Quiz_Attempt $attempt The submitted attempt object.
		 * @param PressPrimer_Quiz_Quiz    $quiz    The quiz object.
		 */
		do_action( 'pressprimer_quiz_attempt_submitted', $this, $quiz );

		/**
		 * Fires when a user completes a quiz (pass or fail).
		 *
		 * @since 1.0.0
		 *
		 * @param PressPrimer_Quiz_Attempt $attempt The submitted attempt object.
		 * @param PressPrimer_Quiz_Quiz    $quiz    The quiz object.
		 */
		do_action( 'pressprimer_quiz_quiz_completed', $this, $quiz );

		// Fire pass/fail specific hooks
		if ( $passed ) {
			/**
			 * Fires when a user passes a quiz.
			 *
			 * @since 1.0.0
			 *
			 * @param PressPrimer_Quiz_Attempt $attempt The submitted attempt object.
			 * @param PressPrimer_Quiz_Quiz    $quiz    The quiz object.
			 */
			do_action( 'pressprimer_quiz_quiz_passed', $this, $quiz );
		} else {
			/**
			 * Fires when a user fails a quiz.
			 *
			 * @since 1.0.0
			 *
			 * @param PressPrimer_Quiz_Attempt $attempt The submitted attempt object.
			 * @param PressPrimer_Quiz_Quiz    $quiz    The quiz object.
			 */
			do_action( 'pressprimer_quiz_quiz_failed', $this, $quiz );
		}

		return true;
	}

	/**
	 * Score attempt
	 *
	 * Calculates score for all questions using the scoring service.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function score_attempt() {
		$scoring_service = PressPrimer_Quiz_Scoring_Service::instance();
		$result          = $scoring_service->calculate_attempt_score( $this->id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Scores are already updated by the service
		return true;
	}

	/**
	 * Check if attempt has timed out
	 *
	 * Compares elapsed time to quiz time limit.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if timed out, false otherwise.
	 */
	public function is_timed_out() {
		$quiz = $this->get_quiz();

		// No time limit
		if ( ! $quiz || ! $quiz->time_limit_seconds ) {
			return false;
		}

		// Already submitted
		if ( 'in_progress' !== $this->status ) {
			return false;
		}

		// Calculate elapsed time using WordPress timezone-aware functions
		// started_at is stored in WordPress local time via current_time('mysql')
		$started_timestamp = strtotime( get_gmt_from_date( $this->started_at ) );
		$now               = time(); // UTC timestamp
		$elapsed_seconds   = $now - $started_timestamp;

		// 30 second grace period for network latency
		$grace_period = 30;

		return $elapsed_seconds > ( $quiz->time_limit_seconds + $grace_period );
	}

	/**
	 * Check if attempt can be resumed
	 *
	 * Determines if user can continue this attempt.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if resumable, false otherwise.
	 */
	public function can_resume() {
		// Must be in progress
		if ( 'in_progress' !== $this->status ) {
			return false;
		}

		// Check quiz allows resume
		$quiz = $this->get_quiz();
		if ( ! $quiz || ! $quiz->allow_resume ) {
			return false;
		}

		// Check if timed out
		if ( $this->is_timed_out() ) {
			return false;
		}

		return true;
	}

	/**
	 * Get quiz for this attempt
	 *
	 * @since 1.0.0
	 *
	 * @return PressPrimer_Quiz_Quiz|null Quiz object or null.
	 */
	public function get_quiz() {
		if ( null === $this->_quiz ) {
			$this->_quiz = PressPrimer_Quiz_Quiz::get( $this->quiz_id );
		}

		return $this->_quiz;
	}

	/**
	 * Get user's attempts for a quiz
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @param int $user_id User ID.
	 * @return array Array of PressPrimer_Quiz_Attempt objects.
	 */
	public static function get_user_attempts( int $quiz_id, int $user_id ) {
		global $wpdb;
		$table = static::get_full_table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE quiz_id = %d AND user_id = %d ORDER BY started_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quiz_id,
				$user_id
			)
		);

		$attempts = [];
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$attempts[] = static::from_row( $row );
			}
		}

		return $attempts;
	}

	/**
	 * Get user's in-progress attempt for a quiz
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @param int $user_id User ID.
	 * @return PressPrimer_Quiz_Attempt|null Attempt object or null.
	 */
	public static function get_user_in_progress( int $quiz_id, int $user_id ) {
		global $wpdb;
		$table = static::get_full_table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE quiz_id = %d AND user_id = %d AND status = 'in_progress' ORDER BY started_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quiz_id,
				$user_id
			)
		);

		return $row ? static::from_row( $row ) : null;
	}

	/**
	 * Get last user attempt for a quiz
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @param int $user_id User ID.
	 * @return PressPrimer_Quiz_Attempt|null Attempt object or null.
	 */
	public static function get_last_user_attempt( int $quiz_id, int $user_id ) {
		global $wpdb;
		$table = static::get_full_table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE quiz_id = %d AND user_id = %d AND status = 'submitted' ORDER BY finished_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quiz_id,
				$user_id
			)
		);

		return $row ? static::from_row( $row ) : null;
	}

	/**
	 * Get guest's in-progress attempt for a quiz
	 *
	 * @since 1.0.0
	 *
	 * @param int    $quiz_id Quiz ID.
	 * @param string $email Guest email.
	 * @return PressPrimer_Quiz_Attempt|null Attempt object or null.
	 */
	public static function get_guest_in_progress( int $quiz_id, string $email ) {
		global $wpdb;
		$table = static::get_full_table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE quiz_id = %d AND guest_email = %s AND status = 'in_progress' ORDER BY started_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quiz_id,
				$email
			)
		);

		return $row ? static::from_row( $row ) : null;
	}

	/**
	 * Get attempt by guest token
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Guest token.
	 * @return PressPrimer_Quiz_Attempt|null Attempt object or null.
	 */
	public static function get_by_token( string $token ) {
		global $wpdb;
		$table = static::get_full_table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE guest_token = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$token
			)
		);

		return $row ? static::from_row( $row ) : null;
	}

	/**
	 * Check if guest token is expired
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if token is expired, false otherwise.
	 */
	public function is_token_expired() {
		// Logged-in users don't have token expiration
		if ( $this->user_id ) {
			return false;
		}

		// No expiration date set
		if ( ! $this->token_expires_at ) {
			return false;
		}

		// Check if current time is past expiration
		$now     = current_time( 'timestamp' );
		$expires = strtotime( $this->token_expires_at );

		return $now > $expires;
	}

	/**
	 * Get results URL for this attempt
	 *
	 * Returns a URL that can be used to view results for this attempt.
	 * For guests, includes the secure token. For logged-in users, uses attempt ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $base_url Optional base URL. Defaults to current page.
	 * @return string Results URL.
	 */
	public function get_results_url( $base_url = '' ) {
		// Use home URL as fallback
		if ( empty( $base_url ) ) {
			$base_url = home_url( '/' );
		}

		// For logged-in users, use attempt ID
		if ( $this->user_id ) {
			return add_query_arg( 'attempt', $this->id, $base_url );
		}

		// For guests, use secure token
		if ( $this->guest_token ) {
			return add_query_arg(
				[
					'attempt' => $this->id,
					'token'   => $this->guest_token,
				],
				$base_url
			);
		}

		// Fallback to attempt ID only
		return add_query_arg( 'attempt', $this->id, $base_url );
	}

	/**
	 * Regenerate guest token with new expiration
	 *
	 * Useful for extending access to results.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function regenerate_token() {
		// Only for guest attempts
		if ( $this->user_id ) {
			return false;
		}

		// Generate new token
		$this->guest_token      = bin2hex( random_bytes( 32 ) );
		$this->token_expires_at = gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) );

		return $this->save();
	}

	/**
	 * Generate randomized answer order for a question
	 *
	 * Creates a shuffled array of answer indices to use for display order.
	 *
	 * @since 1.0.0
	 *
	 * @param int $revision_id Question revision ID.
	 * @return string|null JSON-encoded answer order, or null if not needed.
	 */
	private static function generate_answer_order( int $revision_id ) {
		$revision = PressPrimer_Quiz_Question_Revision::get( $revision_id );
		if ( ! $revision ) {
			return null;
		}

		$answers = $revision->get_answers();
		if ( empty( $answers ) || count( $answers ) < 2 ) {
			return null;
		}

		// Create array of indices [0, 1, 2, ...]
		$order = range( 0, count( $answers ) - 1 );

		// Shuffle the order
		shuffle( $order );

		return wp_json_encode( $order );
	}
}
