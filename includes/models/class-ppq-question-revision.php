<?php
/**
 * Question Revision model
 *
 * Represents an immutable snapshot of a question's content.
 * Every edit to question content creates a new revision.
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
 * Question Revision model class
 *
 * Handles creation and retrieval of question revisions.
 * Revisions are immutable - they are never updated or deleted.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Question_Revision extends PressPrimer_Quiz_Model {

	/**
	 * Question ID this revision belongs to
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $question_id = 0;

	/**
	 * Revision version number
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $version = 1;

	/**
	 * Question stem (the question text)
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $stem = '';

	/**
	 * Answers as JSON string
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $answers_json = '';

	/**
	 * Feedback for correct answers
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $feedback_correct = null;

	/**
	 * Feedback for incorrect answers
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $feedback_incorrect = null;

	/**
	 * Additional settings as JSON string
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $settings_json = null;

	/**
	 * SHA-256 hash of content for deduplication
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $content_hash = '';

	/**
	 * Created timestamp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $created_at = '';

	/**
	 * User ID who created this revision
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $created_by = 0;

	/**
	 * Cached parsed answers array
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $_answers = null;

	/**
	 * Get table name
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name() {
		return 'ppq_question_revisions';
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
			'question_id',
			'version',
			'stem',
			'answers_json',
			'feedback_correct',
			'feedback_incorrect',
			'settings_json',
			'content_hash',
			'created_by',
		];
	}

	/**
	 * Create new revision
	 *
	 * Creates a new revision for a question with auto-incremented version number.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $question_id Question ID.
	 * @param array $data        Revision data (stem, answers, feedback, settings).
	 * @return int|WP_Error Revision ID on success, WP_Error on failure.
	 */
	public static function create_for_question( $question_id, array $data ) {
		$question_id = absint( $question_id );

		if ( 0 === $question_id ) {
			return new WP_Error(
				'ppq_invalid_question',
				__( 'Invalid question ID.', 'pressprimer-quiz' )
			);
		}

		// Validate required fields
		$validation = self::validate_data( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Get next version number
		$version = self::get_next_version( $question_id );

		// Convert answers array to JSON if needed
		if ( isset( $data['answers'] ) && is_array( $data['answers'] ) ) {
			$data['answers_json'] = wp_json_encode( $data['answers'] );
			unset( $data['answers'] );
		}

		// Convert settings array to JSON if needed
		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$data['settings_json'] = wp_json_encode( $data['settings'] );
			unset( $data['settings'] );
		}

		// Generate content hash
		$stem                 = $data['stem'] ?? '';
		$answers              = ! empty( $data['answers_json'] ) ? $data['answers_json'] : '[]';
		$data['content_hash'] = self::generate_hash( $stem, $answers );

		// Set metadata
		$data['question_id'] = $question_id;
		$data['version']     = $version;
		$data['created_by']  = $data['created_by'] ?? get_current_user_id();

		// Call parent create
		$revision_id = parent::create( $data );

		if ( is_wp_error( $revision_id ) ) {
			return $revision_id;
		}

		// Update question's current_revision_id
		global $wpdb;
		$questions_table = $wpdb->prefix . 'ppq_questions';

		$wpdb->update(
			$questions_table,
			[ 'current_revision_id' => $revision_id ],
			[ 'id' => $question_id ],
			[ '%d' ],
			[ '%d' ]
		);

		return $revision_id;
	}

	/**
	 * Validate revision data
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Revision data to validate.
	 * @return true|WP_Error True on success, WP_Error on validation failure.
	 */
	protected static function validate_data( array $data ) {
		// Validate stem
		if ( empty( $data['stem'] ) ) {
			return new WP_Error(
				'ppq_missing_stem',
				__( 'Question stem is required.', 'pressprimer-quiz' )
			);
		}

		// Validate stem length
		$stem = wp_strip_all_tags( $data['stem'] );
		if ( mb_strlen( $stem ) > 10000 ) {
			return new WP_Error(
				'ppq_stem_too_long',
				__( 'Question stem cannot exceed 10,000 characters.', 'pressprimer-quiz' )
			);
		}

		if ( empty( trim( $stem ) ) ) {
			return new WP_Error(
				'ppq_empty_stem',
				__( 'Question stem cannot be empty or only whitespace.', 'pressprimer-quiz' )
			);
		}

		// Validate answers
		$answers = [];
		if ( isset( $data['answers'] ) && is_array( $data['answers'] ) ) {
			$answers = $data['answers'];
		} elseif ( ! empty( $data['answers_json'] ) ) {
			$answers = json_decode( $data['answers_json'], true );
			if ( null === $answers ) {
				return new WP_Error(
					'ppq_invalid_answers_json',
					__( 'Invalid answers JSON format.', 'pressprimer-quiz' )
				);
			}
		}

		// Must have at least 2 answers
		if ( count( $answers ) < 2 ) {
			return new WP_Error(
				'ppq_insufficient_answers',
				__( 'Question must have at least 2 answer options.', 'pressprimer-quiz' )
			);
		}

		// Must not exceed 8 answers
		if ( count( $answers ) > 8 ) {
			return new WP_Error(
				'ppq_too_many_answers',
				__( 'Question cannot have more than 8 answer options.', 'pressprimer-quiz' )
			);
		}

		// Validate each answer
		$has_correct = false;
		foreach ( $answers as $answer ) {
			// Check for required text field
			if ( empty( $answer['text'] ) || empty( trim( wp_strip_all_tags( $answer['text'] ) ) ) ) {
				return new WP_Error(
					'ppq_empty_answer',
					__( 'Each answer option must have non-empty text.', 'pressprimer-quiz' )
				);
			}

			// Check text length
			if ( mb_strlen( $answer['text'] ) > 2000 ) {
				return new WP_Error(
					'ppq_answer_too_long',
					__( 'Answer text cannot exceed 2,000 characters.', 'pressprimer-quiz' )
				);
			}

			// Check for at least one correct answer
			if ( ! empty( $answer['is_correct'] ) ) {
				$has_correct = true;
			}
		}

		if ( ! $has_correct ) {
			return new WP_Error(
				'ppq_no_correct_answer',
				__( 'Question must have at least one correct answer.', 'pressprimer-quiz' )
			);
		}

		// Validate feedback lengths
		if ( ! empty( $data['feedback_correct'] ) && mb_strlen( $data['feedback_correct'] ) > 2000 ) {
			return new WP_Error(
				'ppq_feedback_too_long',
				__( 'Feedback cannot exceed 2,000 characters.', 'pressprimer-quiz' )
			);
		}

		if ( ! empty( $data['feedback_incorrect'] ) && mb_strlen( $data['feedback_incorrect'] ) > 2000 ) {
			return new WP_Error(
				'ppq_feedback_too_long',
				__( 'Feedback cannot exceed 2,000 characters.', 'pressprimer-quiz' )
			);
		}

		return true;
	}

	/**
	 * Get next version number for a question
	 *
	 * @since 1.0.0
	 *
	 * @param int $question_id Question ID.
	 * @return int Next version number.
	 */
	protected static function get_next_version( $question_id ) {
		global $wpdb;

		$table = static::get_full_table_name();

		$max_version = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(version) FROM {$table} WHERE question_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$question_id
			)
		);

		return $max_version ? absint( $max_version ) + 1 : 1;
	}

	/**
	 * Generate content hash
	 *
	 * Creates SHA-256 hash of normalized stem and answers for deduplication.
	 *
	 * @since 1.0.0
	 *
	 * @param string $stem    Question stem.
	 * @param mixed  $answers Answers (array or JSON string).
	 * @return string SHA-256 hash.
	 */
	public static function generate_hash( $stem, $answers ) {
		// Normalize stem (strip tags, trim, lowercase)
		$normalized_stem = strtolower( trim( wp_strip_all_tags( $stem ) ) );

		// Normalize answers
		if ( is_string( $answers ) ) {
			$answers = json_decode( $answers, true );
		}

		if ( ! is_array( $answers ) ) {
			$answers = [];
		}

		// Sort answers by order/id for consistent hashing
		usort(
			$answers,
			function ( $a, $b ) {
				$order_a = $a['order'] ?? 0;
				$order_b = $b['order'] ?? 0;
				return $order_a - $order_b;
			}
		);

		// Extract just the text and is_correct fields
		$normalized_answers = [];
		foreach ( $answers as $answer ) {
			$normalized_answers[] = [
				'text'       => strtolower( trim( wp_strip_all_tags( $answer['text'] ?? '' ) ) ),
				'is_correct' => ! empty( $answer['is_correct'] ),
			];
		}

		// Create combined string
		$content = $normalized_stem . wp_json_encode( $normalized_answers );

		// Generate SHA-256 hash
		return hash( 'sha256', $content );
	}

	/**
	 * Get answers array
	 *
	 * Parses answers_json and returns as array.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force Force refresh from JSON.
	 * @return array Answers array.
	 */
	public function get_answers( $force = false ) {
		if ( null === $this->_answers || $force ) {
			if ( empty( $this->answers_json ) ) {
				$this->_answers = [];
			} else {
				$answers        = json_decode( $this->answers_json, true );
				$this->_answers = is_array( $answers ) ? $answers : [];
			}
		}

		return $this->_answers;
	}

	/**
	 * Get settings array
	 *
	 * Parses settings_json and returns as array.
	 *
	 * @since 1.0.0
	 *
	 * @return array Settings array.
	 */
	public function get_settings() {
		if ( empty( $this->settings_json ) ) {
			return [];
		}

		$settings = json_decode( $this->settings_json, true );
		return is_array( $settings ) ? $settings : [];
	}

	/**
	 * Get correct answers
	 *
	 * Returns only the answers marked as correct.
	 *
	 * @since 1.0.0
	 *
	 * @return array Correct answers.
	 */
	public function get_correct_answers() {
		$answers = $this->get_answers();
		return array_filter(
			$answers,
			function ( $answer ) {
				return ! empty( $answer['is_correct'] );
			}
		);
	}

	/**
	 * Get answer by ID
	 *
	 * @since 1.0.0
	 *
	 * @param string $answer_id Answer ID (e.g., 'a1', 'a2').
	 * @return array|null Answer array or null if not found.
	 */
	public function get_answer_by_id( $answer_id ) {
		$answers = $this->get_answers();

		foreach ( $answers as $answer ) {
			if ( isset( $answer['id'] ) && $answer['id'] === $answer_id ) {
				return $answer;
			}
		}

		return null;
	}

	/**
	 * Check if content matches another revision
	 *
	 * Compares content hashes to detect duplicate content.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Question_Revision $other Other revision to compare.
	 * @return bool True if content matches.
	 */
	public function matches_content( PressPrimer_Quiz_Question_Revision $other ) {
		return $this->content_hash === $other->content_hash;
	}

	/**
	 * Find duplicate revisions by hash
	 *
	 * Finds other revisions with the same content hash.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hash Content hash to search for.
	 * @return array Array of revision instances.
	 */
	public static function find_by_hash( $hash ) {
		return static::find(
			[
				'where' => [ 'content_hash' => $hash ],
			]
		);
	}

	/**
	 * Get revisions for a question
	 *
	 * Returns all revisions for a specific question.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $question_id Question ID.
	 * @param array $args        Query arguments.
	 * @return array Array of revision instances.
	 */
	public static function get_for_question( $question_id, array $args = [] ) {
		$defaults = [
			'order_by' => 'version',
			'order'    => 'DESC',
		];

		$args          = wp_parse_args( $args, $defaults );
		$args['where'] = [ 'question_id' => absint( $question_id ) ];

		return static::find( $args );
	}

	/**
	 * Prevent updates to revisions
	 *
	 * Revisions are immutable and cannot be updated.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_Error Always returns error.
	 */
	public function save() {
		global $wpdb;

		// If this is a new revision (no ID), insert it
		if ( empty( $this->id ) ) {
			// Build data array from fillable fields
			$fillable = static::get_fillable_fields();
			$data     = [];

			foreach ( $fillable as $field ) {
				if ( property_exists( $this, $field ) ) {
					$data[ $field ] = $this->$field;
				}
			}

			if ( empty( $data ) ) {
				return new WP_Error(
					'ppq_no_data',
					__( 'No valid data to save.', 'pressprimer-quiz' )
				);
			}

			$table = static::get_full_table_name();

			// Insert record
			$result = $wpdb->insert( $table, $data );

			if ( false === $result ) {
				return new WP_Error(
					'ppq_db_error',
					__( 'Database error: Failed to create revision.', 'pressprimer-quiz' ),
					[ 'db_error' => $wpdb->last_error ]
				);
			}

			$this->id = $wpdb->insert_id;
			return true;
		}

		// Otherwise, revisions are immutable
		return new WP_Error(
			'ppq_revision_immutable',
			__( 'Revisions cannot be updated. Create a new revision instead.', 'pressprimer-quiz' )
		);
	}

	/**
	 * Prevent deletion of revisions
	 *
	 * Revisions are permanent and cannot be deleted.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_Error Always returns error.
	 */
	public function delete() {
		return new WP_Error(
			'ppq_revision_permanent',
			__( 'Revisions cannot be deleted. They are permanent for historical accuracy.', 'pressprimer-quiz' )
		);
	}
}
