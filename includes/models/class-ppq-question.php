<?php
/**
 * Question model
 *
 * Represents a quiz question with versioning, categories, and metadata.
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
 * Question model class
 *
 * Handles CRUD operations for questions, including soft delete,
 * revision management, and category/tag relationships.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Question extends PressPrimer_Quiz_Model {

	/**
	 * Question UUID
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $uuid = '';

	/**
	 * Author user ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $author_id = 0;

	/**
	 * Question type
	 *
	 * @since 1.0.0
	 * @var string mc|ma|tf
	 */
	public $type = 'mc';

	/**
	 * Expected completion time in seconds
	 *
	 * @since 1.0.0
	 * @var int|null
	 */
	public $expected_seconds = null;

	/**
	 * Author-assigned difficulty
	 *
	 * @since 1.0.0
	 * @var string|null easy|medium|hard
	 */
	public $difficulty_author = null;

	/**
	 * Maximum points for this question
	 *
	 * @since 1.0.0
	 * @var float
	 */
	public $max_points = 1.00;

	/**
	 * Question status
	 *
	 * @since 1.0.0
	 * @var string draft|published|archived
	 */
	public $status = 'published';

	/**
	 * Current revision ID
	 *
	 * @since 1.0.0
	 * @var int|null
	 */
	public $current_revision_id = null;

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
	 * Soft delete timestamp
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	public $deleted_at = null;

	/**
	 * Cached current revision
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Quiz_Question_Revision|null
	 */
	private $_current_revision = null;

	/**
	 * Cached categories
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $_categories = null;

	/**
	 * Cached tags
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $_tags = null;

	/**
	 * Get table name
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name() {
		return 'ppq_questions';
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
			'author_id',
			'type',
			'expected_seconds',
			'difficulty_author',
			'max_points',
			'status',
			'current_revision_id',
			'deleted_at',
		];
	}

	/**
	 * Get question by UUID
	 *
	 * @since 1.0.0
	 *
	 * @param string $uuid Question UUID.
	 * @return PressPrimer_Quiz_Question|null Question instance or null if not found.
	 */
	public static function get_by_uuid( $uuid ) {
		global $wpdb;

		$table = static::get_full_table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE uuid = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$uuid
			)
		);

		return $row ? static::from_row( $row ) : null;
	}

	/**
	 * Create new question
	 *
	 * Validates input and creates a new question record.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Question data.
	 * @return int|WP_Error Question ID on success, WP_Error on failure.
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

		// Set author to current user if not provided
		if ( empty( $data['author_id'] ) ) {
			$data['author_id'] = get_current_user_id();
		}

		// Set default status if not provided
		if ( empty( $data['status'] ) ) {
			$data['status'] = 'published';
		}

		// Call parent create
		return parent::create( $data );
	}

	/**
	 * Validate question data
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Question data to validate.
	 * @return true|WP_Error True on success, WP_Error on validation failure.
	 */
	protected static function validate_data( array $data ) {
		// Validate type
		if ( ! empty( $data['type'] ) && ! in_array( $data['type'], [ 'mc', 'ma', 'tf' ], true ) ) {
			return new WP_Error(
				'ppq_invalid_type',
				__( 'Invalid question type. Must be mc, ma, or tf.', 'pressprimer-quiz' )
			);
		}

		// Validate difficulty
		if ( ! empty( $data['difficulty_author'] ) && ! in_array( $data['difficulty_author'], [ 'beginner', 'intermediate', 'advanced', 'expert' ], true ) ) {
			return new WP_Error(
				'ppq_invalid_difficulty',
				__( 'Invalid difficulty. Must be beginner, intermediate, advanced, or expert.', 'pressprimer-quiz' )
			);
		}

		// Validate status
		if ( ! empty( $data['status'] ) && ! in_array( $data['status'], [ 'draft', 'published', 'archived' ], true ) ) {
			return new WP_Error(
				'ppq_invalid_status',
				__( 'Invalid status. Must be draft, published, or archived.', 'pressprimer-quiz' )
			);
		}

		// Validate expected_seconds
		if ( ! empty( $data['expected_seconds'] ) ) {
			$seconds = absint( $data['expected_seconds'] );
			if ( $seconds < 1 || $seconds > 3600 ) {
				return new WP_Error(
					'ppq_invalid_time',
					__( 'Expected time must be between 1 and 3600 seconds.', 'pressprimer-quiz' )
				);
			}
		}

		// Validate max_points
		if ( isset( $data['max_points'] ) ) {
			$points = floatval( $data['max_points'] );
			if ( $points < 0.01 || $points > 1000.00 ) {
				return new WP_Error(
					'ppq_invalid_points',
					__( 'Max points must be between 0.01 and 1000.00.', 'pressprimer-quiz' )
				);
			}
		}

		return true;
	}

	/**
	 * Delete question (soft delete)
	 *
	 * Sets deleted_at timestamp instead of removing the record.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete() {
		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppq_no_id',
				__( 'Cannot delete question without ID.', 'pressprimer-quiz' )
			);
		}

		// Soft delete: set deleted_at timestamp
		$this->deleted_at = current_time( 'mysql' );

		return $this->save();
	}

	/**
	 * Permanently delete question
	 *
	 * Removes the question record from the database.
	 * Use with caution - this cannot be undone.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function force_delete() {
		return parent::delete();
	}

	/**
	 * Restore soft-deleted question
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function restore() {
		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppq_no_id',
				__( 'Cannot restore question without ID.', 'pressprimer-quiz' )
			);
		}

		$this->deleted_at = null;

		return $this->save();
	}

	/**
	 * Get current revision
	 *
	 * Lazy loads and returns the current question revision.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force Force refresh from database.
	 * @return PressPrimer_Quiz_Question_Revision|null Revision instance or null if not found.
	 */
	public function get_current_revision( $force = false ) {
		if ( null === $this->_current_revision || $force ) {
			if ( empty( $this->current_revision_id ) ) {
				return null;
			}

			if ( class_exists( 'PressPrimer_Quiz_Question_Revision' ) ) {
				$this->_current_revision = PressPrimer_Quiz_Question_Revision::get( $this->current_revision_id );
			}
		}

		return $this->_current_revision;
	}

	/**
	 * Get categories
	 *
	 * Lazy loads and returns question categories.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force Force refresh from database.
	 * @return array Array of category objects.
	 */
	public function get_categories( $force = false ) {
		if ( null === $this->_categories || $force ) {
			if ( class_exists( 'PressPrimer_Quiz_Category' ) ) {
				$this->_categories = PressPrimer_Quiz_Category::get_for_question( $this->id, 'category' );
			} else {
				$this->_categories = [];
			}
		}

		return $this->_categories;
	}

	/**
	 * Get tags
	 *
	 * Lazy loads and returns question tags.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force Force refresh from database.
	 * @return array Array of tag objects.
	 */
	public function get_tags( $force = false ) {
		if ( null === $this->_tags || $force ) {
			if ( class_exists( 'PressPrimer_Quiz_Category' ) ) {
				$this->_tags = PressPrimer_Quiz_Category::get_for_question( $this->id, 'tag' );
			} else {
				$this->_tags = [];
			}
		}

		return $this->_tags;
	}

	/**
	 * Set categories
	 *
	 * Updates the category relationships for this question.
	 *
	 * @since 1.0.0
	 *
	 * @param array $category_ids Array of category IDs.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function set_categories( array $category_ids ) {
		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppq_no_id',
				__( 'Cannot set categories without question ID.', 'pressprimer-quiz' )
			);
		}

		global $wpdb;
		$tax_table = $wpdb->prefix . 'ppq_question_tax';

		// Start transaction
		$wpdb->query( 'START TRANSACTION' );

		// Remove existing category relationships
		$wpdb->delete(
			$tax_table,
			[
				'question_id' => $this->id,
				'taxonomy'    => 'category',
			],
			[ '%d', '%s' ]
		);

		// Add new relationships
		foreach ( $category_ids as $category_id ) {
			$category_id = absint( $category_id );
			if ( $category_id > 0 ) {
				$wpdb->insert(
					$tax_table,
					[
						'question_id' => $this->id,
						'category_id' => $category_id,
						'taxonomy'    => 'category',
					],
					[ '%d', '%d', '%s' ]
				);
			}
		}

		// Commit transaction
		$wpdb->query( 'COMMIT' );

		// Clear cache
		$this->_categories = null;

		// Update counts
		if ( class_exists( 'PressPrimer_Quiz_Category' ) ) {
			PressPrimer_Quiz_Category::update_counts( 'category' );
		}

		return true;
	}

	/**
	 * Set tags
	 *
	 * Updates the tag relationships for this question.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tag_ids Array of tag IDs.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function set_tags( array $tag_ids ) {
		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppq_no_id',
				__( 'Cannot set tags without question ID.', 'pressprimer-quiz' )
			);
		}

		global $wpdb;
		$tax_table = $wpdb->prefix . 'ppq_question_tax';

		// Start transaction
		$wpdb->query( 'START TRANSACTION' );

		// Remove existing tag relationships
		$wpdb->delete(
			$tax_table,
			[
				'question_id' => $this->id,
				'taxonomy'    => 'tag',
			],
			[ '%d', '%s' ]
		);

		// Add new relationships
		foreach ( $tag_ids as $tag_id ) {
			$tag_id = absint( $tag_id );
			if ( $tag_id > 0 ) {
				$wpdb->insert(
					$tax_table,
					[
						'question_id' => $this->id,
						'category_id' => $tag_id,
						'taxonomy'    => 'tag',
					],
					[ '%d', '%d', '%s' ]
				);
			}
		}

		// Commit transaction
		$wpdb->query( 'COMMIT' );

		// Clear cache
		$this->_tags = null;

		// Update counts
		if ( class_exists( 'PressPrimer_Quiz_Category' ) ) {
			PressPrimer_Quiz_Category::update_counts( 'tag' );
		}

		return true;
	}

	/**
	 * Check if question is deleted
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if deleted, false otherwise.
	 */
	public function is_deleted() {
		return ! empty( $this->deleted_at );
	}

	/**
	 * Get question usage count
	 *
	 * Returns the number of quizzes using this question.
	 *
	 * @since 1.0.0
	 *
	 * @return int Usage count.
	 */
	public function get_usage_count() {
		global $wpdb;

		$quiz_items_table = $wpdb->prefix . 'ppq_quiz_items';

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT quiz_id) FROM {$quiz_items_table} WHERE question_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->id
			)
		);

		return absint( $count );
	}

	/**
	 * Find questions
	 *
	 * Extended find method with additional filters for questions.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 * @return array Array of question instances.
	 */
	public static function find_questions( array $args = [] ) {
		$defaults = [
			'type'              => null,
			'status'            => null,
			'difficulty_author' => null,
			'author_id'         => null,
			'exclude_deleted'   => true,
			'category_id'       => null,
			'tag_id'            => null,
		];

		$args = wp_parse_args( $args, $defaults );

		// Build where conditions
		$where = [];

		if ( ! empty( $args['type'] ) ) {
			$where['type'] = $args['type'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where['status'] = $args['status'];
		}

		if ( ! empty( $args['difficulty_author'] ) ) {
			$where['difficulty_author'] = $args['difficulty_author'];
		}

		if ( ! empty( $args['author_id'] ) ) {
			$where['author_id'] = absint( $args['author_id'] );
		}

		if ( $args['exclude_deleted'] ) {
			// Note: PressPrimer_Quiz_Model::find() doesn't support IS NULL,
			// so we'll need to filter after or extend the query
			// For now, we'll handle this with a custom query
		}

		// Use parent find for simple cases
		if ( empty( $args['category_id'] ) && empty( $args['tag_id'] ) && $args['exclude_deleted'] ) {
			return self::find_with_deleted_filter( $where, $args );
		}

		return self::find( [ 'where' => $where ] );
	}

	/**
	 * Find questions excluding deleted
	 *
	 * @since 1.0.0
	 *
	 * @param array $where Where conditions.
	 * @param array $args  Additional arguments.
	 * @return array Array of question instances.
	 */
	private static function find_with_deleted_filter( array $where, array $args ) {
		global $wpdb;

		$table = static::get_full_table_name();

		// Build WHERE clause
		$where_clauses = [ 'deleted_at IS NULL' ];
		$where_values  = [];

		foreach ( $where as $field => $value ) {
			$where_clauses[] = "`{$field}` = %s";
			$where_values[]  = $value;
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

		// Build ORDER BY
		$order_by  = sanitize_key( $args['order_by'] ?? 'id' );
		$order     = strtoupper( $args['order'] ?? 'DESC' );
		$order_sql = "ORDER BY `{$order_by}` {$order}";

		// Build LIMIT
		$limit_sql = '';
		if ( isset( $args['limit'] ) ) {
			$limit     = absint( $args['limit'] );
			$offset    = absint( $args['offset'] ?? 0 );
			$limit_sql = $offset > 0 ? "LIMIT {$offset}, {$limit}" : "LIMIT {$limit}";
		}

		// Build final query
		$query = "SELECT * FROM {$table} {$where_sql} {$order_sql} {$limit_sql}";

		// Prepare and execute
		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Convert to model instances
		$results = [];
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$results[] = static::from_row( $row );
			}
		}

		return $results;
	}
}
