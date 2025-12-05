<?php
/**
 * Attempt Item Model
 *
 * Represents a student's response to a single question within a quiz attempt.
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
 * Attempt Item model class
 *
 * Manages individual question responses within quiz attempts.
 * Tracks selected answers, timing, scoring, and confidence.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Attempt_Item extends PressPrimer_Quiz_Model {

	/**
	 * Attempt ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $attempt_id;

	/**
	 * Question revision ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $question_revision_id;

	/**
	 * Order index (position in quiz)
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $order_index;

	/**
	 * Selected answers JSON
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $selected_answers_json;

	/**
	 * First view timestamp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $first_view_at;

	/**
	 * Last answer timestamp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $last_answer_at;

	/**
	 * Time spent in milliseconds
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $time_spent_ms;

	/**
	 * Is correct flag
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $is_correct;

	/**
	 * Score points earned
	 *
	 * @since 1.0.0
	 * @var float
	 */
	public $score_points;

	/**
	 * Confidence flag
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $confidence;

	/**
	 * Cached question revision
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Quiz_Question_Revision|null
	 */
	private $_revision = null;

	/**
	 * Cached question
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Quiz_Question|null
	 */
	private $_question = null;

	/**
	 * Get table name
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name() {
		return 'ppq_attempt_items';
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
			'attempt_id',
			'question_revision_id',
			'order_index',
			'selected_answers_json',
			'first_view_at',
			'last_answer_at',
			'time_spent_ms',
			'is_correct',
			'score_points',
			'confidence',
		];
	}

	/**
	 * Get question revision
	 *
	 * Returns the specific revision of the question that was used
	 * in this attempt. This ensures historical accuracy.
	 *
	 * @since 1.0.0
	 *
	 * @return PressPrimer_Quiz_Question_Revision|null Question revision or null.
	 */
	public function get_question_revision() {
		if ( null === $this->_revision ) {
			$this->_revision = PressPrimer_Quiz_Question_Revision::get( $this->question_revision_id );
		}

		return $this->_revision;
	}

	/**
	 * Get question
	 *
	 * Returns the parent question object.
	 *
	 * @since 1.0.0
	 *
	 * @return PressPrimer_Quiz_Question|null Question object or null.
	 */
	public function get_question() {
		if ( null === $this->_question ) {
			$revision = $this->get_question_revision();
			if ( $revision ) {
				$this->_question = PressPrimer_Quiz_Question::get( $revision->question_id );
			}
		}

		return $this->_question;
	}

	/**
	 * Get selected answers
	 *
	 * Returns array of selected answer indices.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of selected answer indices (0-based).
	 */
	public function get_selected_answers() {
		if ( empty( $this->selected_answers_json ) ) {
			return [];
		}

		$selected = json_decode( $this->selected_answers_json, true );

		return is_array( $selected ) ? $selected : [];
	}

	/**
	 * Calculate time spent on this question
	 *
	 * Calculates time between first view and last answer.
	 * Updates the time_spent_ms field.
	 *
	 * @since 1.0.0
	 *
	 * @return int|null Time spent in milliseconds, or null if not enough data.
	 */
	public function calculate_time_spent() {
		if ( ! $this->first_view_at || ! $this->last_answer_at ) {
			return null;
		}

		$first = strtotime( $this->first_view_at );
		$last  = strtotime( $this->last_answer_at );

		$time_spent_seconds = $last - $first;
		$time_spent_ms      = $time_spent_seconds * 1000;

		// Update the field
		$this->time_spent_ms = max( 0, $time_spent_ms );

		return $this->time_spent_ms;
	}

	/**
	 * Record first view
	 *
	 * Sets first_view_at timestamp if not already set.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if updated, false if already set.
	 */
	public function record_first_view() {
		if ( $this->first_view_at ) {
			return false; // Already viewed
		}

		$this->first_view_at = current_time( 'mysql' );

		return true;
	}

	/**
	 * Check if answer is correct
	 *
	 * Compares selected answers to correct answers.
	 * Does NOT save the result - use during submission only.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|null True if correct, false if incorrect, null if cannot determine.
	 */
	public function check_answer() {
		$revision = $this->get_question_revision();
		if ( ! $revision ) {
			return null;
		}

		$selected = $this->get_selected_answers();
		$answers  = $revision->get_answers();

		// Get correct answer indices
		$correct_indices = [];
		foreach ( $answers as $index => $answer ) {
			if ( $answer['is_correct'] ) {
				$correct_indices[] = $index;
			}
		}

		// Sort both arrays for comparison
		sort( $selected );
		sort( $correct_indices );

		return $selected === $correct_indices;
	}

	/**
	 * Get attempt item for specific attempt and question
	 *
	 * @since 1.0.0
	 *
	 * @param int $attempt_id Attempt ID.
	 * @param int $question_revision_id Question revision ID.
	 * @return PressPrimer_Quiz_Attempt_Item|null Item or null if not found.
	 */
	public static function get_by_attempt_and_question( int $attempt_id, int $question_revision_id ) {
		global $wpdb;
		$table = static::get_full_table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE attempt_id = %d AND question_revision_id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$attempt_id,
				$question_revision_id
			)
		);

		return $row ? static::from_row( $row ) : null;
	}

	/**
	 * Get items for an attempt
	 *
	 * @since 1.0.0
	 *
	 * @param int $attempt_id Attempt ID.
	 * @return array Array of PressPrimer_Quiz_Attempt_Item objects.
	 */
	public static function get_by_attempt( int $attempt_id ) {
		global $wpdb;
		$table = static::get_full_table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE attempt_id = %d ORDER BY order_index ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$attempt_id
			)
		);

		$items = [];
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$items[] = static::from_row( $row );
			}
		}

		return $items;
	}

	/**
	 * Create or get attempt item
	 *
	 * Gets existing item or creates new one if it doesn't exist.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attempt_id Attempt ID.
	 * @param int $question_revision_id Question revision ID.
	 * @param int $order_index Order index.
	 * @return PressPrimer_Quiz_Attempt_Item|WP_Error Item object or error.
	 */
	public static function get_or_create( int $attempt_id, int $question_revision_id, int $order_index = 0 ) {
		// Try to get existing
		$existing = static::get_by_attempt_and_question( $attempt_id, $question_revision_id );

		if ( $existing ) {
			return $existing;
		}

		// Create new
		$item_id = static::create(
			[
				'attempt_id'           => $attempt_id,
				'question_revision_id' => $question_revision_id,
				'order_index'          => $order_index,
				'first_view_at'        => current_time( 'mysql' ),
			]
		);

		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		return static::get( $item_id );
	}
}
