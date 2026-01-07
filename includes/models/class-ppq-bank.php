<?php
/**
 * Question Bank model
 *
 * Represents a collection of questions organized for quiz creation.
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
 * Question Bank model class
 *
 * Handles CRUD operations for question banks and their question relationships.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Bank extends PressPrimer_Quiz_Model {

	/**
	 * Bank UUID
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $uuid = '';

	/**
	 * Bank name
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $name = '';

	/**
	 * Bank description
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $description = null;

	/**
	 * Owner user ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $owner_id = 0;

	/**
	 * Visibility setting
	 *
	 * @since 1.0.0
	 * @var string private|shared
	 */
	public $visibility = 'private';

	/**
	 * Question count
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $question_count = 0;

	/**
	 * Created timestamp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $created_at = '';

	/**
	 * Last updated timestamp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $updated_at = '';

	/**
	 * Deleted timestamp (soft delete)
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $deleted_at = null;

	/**
	 * Get table name
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name() {
		return 'ppq_banks';
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
			'name',
			'description',
			'owner_id',
			'visibility',
			'question_count',
		];
	}

	/**
	 * Create new bank
	 *
	 * Validates input and creates a new question bank.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Bank data.
	 * @return int|WP_Error Bank ID on success, WP_Error on failure.
	 */
	public static function create( array $data ) {
		// Validate required fields
		$validation = self::validate_data( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Generate UUID if not provided
		if ( empty( $data['uuid'] ) ) {
			$data['uuid'] = PressPrimer_Quiz_Helpers::generate_uuid();
		}

		// Set owner to current user if not provided
		if ( empty( $data['owner_id'] ) ) {
			$data['owner_id'] = get_current_user_id();
		}

		// Set default visibility
		if ( empty( $data['visibility'] ) ) {
			$data['visibility'] = 'private';
		}

		// Initialize question count
		if ( ! isset( $data['question_count'] ) ) {
			$data['question_count'] = 0;
		}

		// Call parent create
		return parent::create( $data );
	}

	/**
	 * Validate bank data
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Bank data to validate.
	 * @return true|WP_Error True on success, WP_Error on validation failure.
	 */
	protected static function validate_data( array $data ) {
		// Validate name
		if ( empty( $data['name'] ) ) {
			return new WP_Error(
				'ppq_missing_name',
				__( 'Bank name is required.', 'pressprimer-quiz' )
			);
		}

		if ( mb_strlen( $data['name'] ) > 200 ) {
			return new WP_Error(
				'ppq_name_too_long',
				__( 'Bank name cannot exceed 200 characters.', 'pressprimer-quiz' )
			);
		}

		// Validate description length
		if ( ! empty( $data['description'] ) && mb_strlen( $data['description'] ) > 2000 ) {
			return new WP_Error(
				'ppq_description_too_long',
				__( 'Description cannot exceed 2,000 characters.', 'pressprimer-quiz' )
			);
		}

		// Validate visibility
		if ( ! empty( $data['visibility'] ) && ! in_array( $data['visibility'], [ 'private', 'shared' ], true ) ) {
			return new WP_Error(
				'ppq_invalid_visibility',
				__( 'Invalid visibility. Must be private or shared.', 'pressprimer-quiz' )
			);
		}

		return true;
	}

	/**
	 * Add question to bank
	 *
	 * @since 1.0.0
	 *
	 * @param int $question_id Question ID to add.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function add_question( $question_id ) {
		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppq_no_id',
				__( 'Cannot add question without bank ID.', 'pressprimer-quiz' )
			);
		}

		$question_id = absint( $question_id );

		if ( 0 === $question_id ) {
			return new WP_Error(
				'ppq_invalid_question',
				__( 'Invalid question ID.', 'pressprimer-quiz' )
			);
		}

		// Verify question exists
		if ( class_exists( 'PressPrimer_Quiz_Question' ) && ! PressPrimer_Quiz_Question::exists( $question_id ) ) {
			return new WP_Error(
				'ppq_question_not_found',
				__( 'Question not found.', 'pressprimer-quiz' )
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ppq_bank_questions';

		// Check if already in bank
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE bank_id = %d AND question_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->id,
				$question_id
			)
		);

		if ( $exists ) {
			return new WP_Error(
				'ppq_duplicate_entry',
				__( 'Question is already in this bank.', 'pressprimer-quiz' )
			);
		}

		// Add question to bank
		$result = $wpdb->insert(
			$table,
			[
				'bank_id'     => $this->id,
				'question_id' => $question_id,
			],
			[ '%d', '%d' ]
		);

		if ( false === $result ) {
			return new WP_Error(
				'ppq_db_error',
				__( 'Failed to add question to bank.', 'pressprimer-quiz' ),
				[ 'db_error' => $wpdb->last_error ]
			);
		}

		// Update question count
		$this->update_question_count();

		return true;
	}

	/**
	 * Remove question from bank
	 *
	 * @since 1.0.0
	 *
	 * @param int $question_id Question ID to remove.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function remove_question( $question_id ) {
		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppq_no_id',
				__( 'Cannot remove question without bank ID.', 'pressprimer-quiz' )
			);
		}

		$question_id = absint( $question_id );

		if ( 0 === $question_id ) {
			return new WP_Error(
				'ppq_invalid_question',
				__( 'Invalid question ID.', 'pressprimer-quiz' )
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ppq_bank_questions';

		// Remove from bank
		$result = $wpdb->delete(
			$table,
			[
				'bank_id'     => $this->id,
				'question_id' => $question_id,
			],
			[ '%d', '%d' ]
		);

		if ( false === $result ) {
			return new WP_Error(
				'ppq_db_error',
				__( 'Failed to remove question from bank.', 'pressprimer-quiz' ),
				[ 'db_error' => $wpdb->last_error ]
			);
		}

		// Update question count
		$this->update_question_count();

		return true;
	}

	/**
	 * Get questions in this bank
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 *                    - type: Filter by question type
	 *                    - difficulty: Filter by difficulty
	 *                    - search: Search in stem
	 *                    - order_by: Column to order by
	 *                    - order: ASC or DESC
	 *                    - limit: Number of results
	 *                    - offset: Offset for pagination
	 * @return array Array of question instances.
	 */
	public function get_questions( array $args = [] ) {
		if ( empty( $this->id ) ) {
			return [];
		}

		global $wpdb;

		$defaults = [
			'type'       => null,
			'difficulty' => null,
			'search'     => null,
			'order_by'   => 'bq.added_at',
			'order'      => 'DESC',
			'limit'      => null,
			'offset'     => 0,
		];

		$args = wp_parse_args( $args, $defaults );

		$bank_questions_table = $wpdb->prefix . 'ppq_bank_questions';
		$questions_table      = $wpdb->prefix . 'ppq_questions';

		// Build WHERE clauses
		$where_clauses = [ 'bq.bank_id = %d' ];
		$where_values  = [ $this->id ];

		// Exclude deleted questions
		$where_clauses[] = 'q.deleted_at IS NULL';

		// Filter by type
		if ( ! empty( $args['type'] ) ) {
			$where_clauses[] = 'q.type = %s';
			$where_values[]  = $args['type'];
		}

		// Filter by difficulty
		if ( ! empty( $args['difficulty'] ) ) {
			$where_clauses[] = 'q.difficulty_author = %s';
			$where_values[]  = $args['difficulty'];
		}

		// Search in stem (join with revisions table)
		$revision_join = '';
		if ( ! empty( $args['search'] ) ) {
			$revisions_table = $wpdb->prefix . 'ppq_question_revisions';
			$revision_join   = "LEFT JOIN {$revisions_table} qr ON q.current_revision_id = qr.id";
			$where_clauses[] = 'qr.stem LIKE %s';
			$where_values[]  = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

		// Build ORDER BY
		$order_by  = sanitize_sql_orderby( "{$args['order_by']} {$args['order']}" );
		$order_sql = $order_by ? "ORDER BY {$order_by}" : '';

		// Build LIMIT with placeholders
		$limit_sql = '';
		if ( null !== $args['limit'] ) {
			$limit  = absint( $args['limit'] );
			$offset = absint( $args['offset'] );
			if ( $offset > 0 ) {
				$limit_sql      = 'LIMIT %d, %d';
				$where_values[] = $offset;
				$where_values[] = $limit;
			} else {
				$limit_sql      = 'LIMIT %d';
				$where_values[] = $limit;
			}
		}

		// Build final query
		$query = "
			SELECT q.*
			FROM {$bank_questions_table} bq
			INNER JOIN {$questions_table} q ON bq.question_id = q.id
			{$revision_join}
			{$where_sql}
			{$order_sql}
			{$limit_sql}
		";

		// Prepare and execute - always prepare since we always have where_values
		$query = $wpdb->prepare( $query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query built with placeholders

		$rows = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Convert to question instances
		$questions = [];
		if ( $rows && class_exists( 'PressPrimer_Quiz_Question' ) ) {
			foreach ( $rows as $row ) {
				$questions[] = PressPrimer_Quiz_Question::from_row( $row );
			}
		}

		return $questions;
	}

	/**
	 * Get question count
	 *
	 * Returns the cached question count.
	 *
	 * @since 1.0.0
	 *
	 * @return int Question count.
	 */
	public function get_question_count() {
		return absint( $this->question_count );
	}

	/**
	 * Update question count
	 *
	 * Recalculates and updates the question_count field.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function update_question_count() {
		if ( empty( $this->id ) ) {
			return false;
		}

		global $wpdb;

		$bank_questions_table = $wpdb->prefix . 'ppq_bank_questions';
		$banks_table          = $wpdb->prefix . 'ppq_banks';

		// Update count using subquery for accuracy
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$banks_table}
				SET question_count = (
					SELECT COUNT(*)
					FROM {$bank_questions_table}
					WHERE bank_id = %d
				)
				WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->id,
				$this->id
			)
		);

		// Refresh the model to get updated count
		$this->refresh();

		return false !== $result;
	}

	/**
	 * Check if question is in bank
	 *
	 * @since 1.0.0
	 *
	 * @param int $question_id Question ID.
	 * @return bool True if in bank, false otherwise.
	 */
	public function has_question( $question_id ) {
		if ( empty( $this->id ) ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ppq_bank_questions';

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE bank_id = %d AND question_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->id,
				absint( $question_id )
			)
		);

		return (bool) $exists;
	}

	/**
	 * Get banks for a user
	 *
	 * Returns all banks owned by a specific user.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Additional query arguments.
	 * @return array Array of bank instances.
	 */
	public static function get_for_user( $user_id, array $args = [] ) {
		$user_id = absint( $user_id );

		if ( 0 === $user_id ) {
			return [];
		}

		$defaults = [
			'order_by' => 'name',
			'order'    => 'ASC',
		];

		$args          = wp_parse_args( $args, $defaults );
		$args['where'] = [ 'owner_id' => $user_id ];

		return static::find( $args );
	}

	/**
	 * Get all banks (admin only)
	 *
	 * Returns all banks in the system.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 * @return array Array of bank instances.
	 */
	public static function get_all( array $args = [] ) {
		$defaults = [
			'order_by' => 'name',
			'order'    => 'ASC',
		];

		$args = wp_parse_args( $args, $defaults );

		return static::find( $args );
	}

	/**
	 * Get quizzes using this bank
	 *
	 * Returns quizzes that have dynamic rules pulling from this bank.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of quiz IDs.
	 */
	public function get_quiz_usage() {
		if ( empty( $this->id ) ) {
			return [];
		}

		global $wpdb;
		$quiz_rules_table = $wpdb->prefix . 'ppq_quiz_rules';

		$quiz_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT quiz_id FROM {$quiz_rules_table} WHERE bank_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->id
			)
		);

		return array_map( 'absint', $quiz_ids );
	}

	/**
	 * Check if bank is in use
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if used by any quiz, false otherwise.
	 */
	public function is_in_use() {
		$usage = $this->get_quiz_usage();
		return ! empty( $usage );
	}

	/**
	 * Delete bank
	 *
	 * Removes bank and all question relationships.
	 * Questions themselves are not deleted.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete() {
		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppq_no_id',
				__( 'Cannot delete bank without ID.', 'pressprimer-quiz' )
			);
		}

		global $wpdb;
		$bank_questions_table = $wpdb->prefix . 'ppq_bank_questions';

		// Start transaction
		$wpdb->query( 'START TRANSACTION' );

		// Delete all question relationships
		$wpdb->delete(
			$bank_questions_table,
			[ 'bank_id' => $this->id ],
			[ '%d' ]
		);

		// Delete bank
		$result = parent::delete();

		if ( is_wp_error( $result ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $result;
		}

		// Commit transaction
		$wpdb->query( 'COMMIT' );

		return true;
	}

	/**
	 * Check if user can access this bank
	 *
	 * In the free plugin, users can only access their own banks or banks
	 * they have admin privileges for. The "shared" visibility setting
	 * doesn't grant access by itself - it's a flag that addons (like Educator)
	 * can use to enable group-based sharing.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID to check.
	 * @return bool True if user can access, false otherwise.
	 */
	public function can_access( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$user_id = absint( $user_id );

		// Owner can always access.
		if ( $user_id === absint( $this->owner_id ) ) {
			return true;
		}

		// Admins can access all banks.
		if ( user_can( $user_id, 'pressprimer_quiz_manage_all' ) ) {
			return true;
		}

		/**
		 * Filter whether user can access a bank.
		 *
		 * Allows addons to grant access to shared banks based on group membership
		 * or other criteria. The free plugin returns false by default for non-owners.
		 *
		 * @since 2.0.0
		 *
		 * @param bool              $can_access Whether user can access. Default false.
		 * @param PressPrimer_Quiz_Bank $bank   The bank being checked.
		 * @param int               $user_id    User ID being checked.
		 */
		return apply_filters( 'pressprimer_quiz_can_access_bank', false, $this, $user_id );
	}
}
