<?php
/**
 * Category model
 *
 * Handles categories and tags for questions and quizzes.
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
 * Category model class
 *
 * Manages both categories (hierarchical) and tags (flat).
 * Both are stored in the same table with different taxonomy values.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Category extends PressPrimer_Quiz_Model {

	/**
	 * Category ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $id = 0;

	/**
	 * Category name
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $name = '';

	/**
	 * Category slug
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $slug = '';

	/**
	 * Category description
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $description = '';

	/**
	 * Parent category ID
	 *
	 * @since 1.0.0
	 * @var int|null
	 */
	public $parent_id = null;

	/**
	 * Taxonomy type
	 *
	 * @since 1.0.0
	 * @var string 'category' or 'tag'
	 */
	public $taxonomy = 'category';

	/**
	 * Question count
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $question_count = 0;

	/**
	 * Quiz count
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $quiz_count = 0;

	/**
	 * Created by user ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $created_by = 0;

	/**
	 * Created timestamp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $created_at = '';

	/**
	 * Get table name
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name() {
		return 'ppq_categories';
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
			'name',
			'slug',
			'description',
			'parent_id',
			'taxonomy',
			'question_count',
			'quiz_count',
			'created_by',
		];
	}

	/**
	 * Create category or tag
	 *
	 * Overrides parent to add auto-slug generation and validation.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Category data.
	 * @return int|WP_Error Category ID on success, WP_Error on failure.
	 */
	public static function create( array $data ) {
		// Validate required fields
		if ( empty( $data['name'] ) ) {
			return new WP_Error(
				'ppq_missing_name',
				__( 'Category name is required.', 'pressprimer-quiz' )
			);
		}

		// Set taxonomy default if not provided
		if ( ! isset( $data['taxonomy'] ) ) {
			$data['taxonomy'] = 'category';
		}

		// Validate taxonomy
		if ( ! in_array( $data['taxonomy'], [ 'category', 'tag' ], true ) ) {
			return new WP_Error(
				'ppq_invalid_taxonomy',
				__( 'Taxonomy must be either "category" or "tag".', 'pressprimer-quiz' )
			);
		}

		// Generate slug if not provided
		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		// Ensure slug is unique for this taxonomy
		$data['slug'] = self::generate_unique_slug( $data['slug'], $data['taxonomy'] );

		// Sanitize name
		$data['name'] = sanitize_text_field( $data['name'] );

		// Sanitize description if provided
		if ( ! empty( $data['description'] ) ) {
			$data['description'] = sanitize_textarea_field( $data['description'] );
		}

		// Validate parent_id for categories
		if ( 'category' === $data['taxonomy'] && ! empty( $data['parent_id'] ) ) {
			$parent = self::get( $data['parent_id'] );
			if ( ! $parent || 'category' !== $parent->taxonomy ) {
				return new WP_Error(
					'ppq_invalid_parent',
					__( 'Invalid parent category.', 'pressprimer-quiz' )
				);
			}
		}

		// Tags cannot have parent
		if ( 'tag' === $data['taxonomy'] ) {
			$data['parent_id'] = null;
		}

		// Set created_by to current user if not provided
		if ( empty( $data['created_by'] ) ) {
			$data['created_by'] = get_current_user_id();
		}

		// Initialize counts
		$data['question_count'] = 0;
		$data['quiz_count']     = 0;

		// Call parent create
		return parent::create( $data );
	}

	/**
	 * Generate unique slug
	 *
	 * Appends number if slug already exists for this taxonomy.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug     Desired slug.
	 * @param string $taxonomy Taxonomy type.
	 * @param int    $exclude_id Optional. Category ID to exclude from check.
	 * @return string Unique slug.
	 */
	private static function generate_unique_slug( $slug, $taxonomy, $exclude_id = 0 ) {
		global $wpdb;

		$original_slug = $slug;
		$suffix        = 1;
		$table         = self::get_full_table_name();

		while ( true ) {
			$query = $wpdb->prepare(
				"SELECT id FROM {$table} WHERE slug = %s AND taxonomy = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$slug,
				$taxonomy
			);

			if ( $exclude_id ) {
				$query .= $wpdb->prepare( ' AND id != %d', $exclude_id );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Slug uniqueness check
			$exists = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( ! $exists ) {
				return $slug;
			}

			$slug = $original_slug . '-' . $suffix;
			++$suffix;
		}
	}

	/**
	 * Get categories or tags for a question
	 *
	 * Returns all categories or tags assigned to a specific question.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $question_id Question ID.
	 * @param string $taxonomy    Taxonomy type ('category' or 'tag').
	 * @return array Array of category objects.
	 */
	public static function get_for_question( $question_id, $taxonomy = 'category' ) {
		global $wpdb;

		$question_id = absint( $question_id );
		$table       = self::get_full_table_name();
		$tax_table   = $wpdb->prefix . 'ppq_question_tax';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Question taxonomy lookup
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*
				FROM {$table} c
				INNER JOIN {$tax_table} t ON c.id = t.category_id
				WHERE t.question_id = %d
				AND c.taxonomy = %s
				ORDER BY c.name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names safely constructed from $wpdb->prefix
				$question_id,
				$taxonomy
			)
		);

		if ( ! $results ) {
			return [];
		}

		return array_map( [ __CLASS__, 'from_row' ], $results );
	}

	/**
	 * Get all categories or tags
	 *
	 * Returns all categories or tags of a specific taxonomy type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy Taxonomy type ('category' or 'tag').
	 * @param array  $args     Optional. Query arguments.
	 * @return array Array of category objects.
	 */
	public static function get_all( $taxonomy = 'category', $args = [] ) {
		$defaults = [
			'order_by' => 'name',
			'order'    => 'ASC',
		];

		$args = wp_parse_args( $args, $defaults );

		// Add taxonomy to where clause
		$args['where'] = array_merge(
			$args['where'] ?? [],
			[ 'taxonomy' => $taxonomy ]
		);

		return self::find( $args );
	}

	/**
	 * Get hierarchical category tree
	 *
	 * Returns categories organized in parent-child hierarchy.
	 *
	 * @since 1.0.0
	 *
	 * @param int $parent_id Optional. Parent ID to start from (0 for root).
	 * @return array Hierarchical array of categories.
	 */
	public static function get_hierarchy( $parent_id = null ) {
		global $wpdb;

		$table = self::get_full_table_name();

		// Build WHERE clause
		if ( null === $parent_id ) {
			$where = 'parent_id IS NULL';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Category hierarchy retrieval
			$results = $wpdb->get_results(
				"SELECT * FROM {$table} WHERE taxonomy = 'category' AND {$where} ORDER BY name ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		} else {
			$parent_id = absint( $parent_id );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Category hierarchy retrieval
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE taxonomy = 'category' AND parent_id = %d ORDER BY name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$parent_id
				)
			);
		}

		if ( ! $results ) {
			return [];
		}

		$categories = [];
		foreach ( $results as $row ) {
			$category = self::from_row( $row );

			// Recursively get children
			$category->children = self::get_hierarchy( $category->id );

			$categories[] = $category;
		}

		return $categories;
	}

	/**
	 * Update usage counts
	 *
	 * Updates question_count and quiz_count for a category or tag.
	 * Can update for a specific category or all categories/tags.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $category_id Optional. Specific category ID, or null for all.
	 * @return bool True on success.
	 */
	public static function update_counts( $category_id = null ) {
		global $wpdb;

		$table     = self::get_full_table_name();
		$tax_table = $wpdb->prefix . 'ppq_question_tax';

		if ( null !== $category_id ) {
			// Update specific category
			$category_id = absint( $category_id );

			// Count questions
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Count update operation
			$question_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT question_id)
					FROM {$tax_table}
					WHERE category_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed from $wpdb->prefix
					$category_id
				)
			);

			// Update the count
			$wpdb->update(
				$table,
				[ 'question_count' => $question_count ],
				[ 'id' => $category_id ],
				[ '%d' ],
				[ '%d' ]
			);
		} else {
			// Update all categories/tags
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Batch count update, table names safely constructed from $wpdb->prefix
			$wpdb->query(
				"UPDATE {$table} c
				SET c.question_count = (
					SELECT COUNT(DISTINCT t.question_id)
					FROM {$tax_table} t
					WHERE t.category_id = c.id
				)"
			);
		}

		return true;
	}

	/**
	 * Assign category to question
	 *
	 * Creates relationship between category and question.
	 *
	 * @since 1.0.0
	 *
	 * @param int $question_id Question ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function assign_to_question( $question_id ) {
		global $wpdb;

		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppq_no_category_id',
				__( 'Category must be saved before assigning to question.', 'pressprimer-quiz' )
			);
		}

		$question_id = absint( $question_id );
		$tax_table   = $wpdb->prefix . 'ppq_question_tax';

		// Check if already assigned
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Assignment check
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tax_table} WHERE question_id = %d AND category_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed from $wpdb->prefix
				$question_id,
				$this->id
			)
		);

		if ( $exists ) {
			return true; // Already assigned
		}

		// Create relationship
		$result = $wpdb->insert(
			$tax_table,
			[
				'question_id' => $question_id,
				'category_id' => $this->id,
			],
			[ '%d', '%d' ]
		);

		if ( false === $result ) {
			return new WP_Error(
				'ppq_db_error',
				__( 'Failed to assign category to question.', 'pressprimer-quiz' )
			);
		}

		// Update count
		self::update_counts( $this->id );

		return true;
	}

	/**
	 * Remove category from question
	 *
	 * Deletes relationship between category and question.
	 *
	 * @since 1.0.0
	 *
	 * @param int $question_id Question ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function remove_from_question( $question_id ) {
		global $wpdb;

		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppq_no_category_id',
				__( 'Category must have an ID.', 'pressprimer-quiz' )
			);
		}

		$question_id = absint( $question_id );
		$tax_table   = $wpdb->prefix . 'ppq_question_tax';

		// Delete relationship
		$result = $wpdb->delete(
			$tax_table,
			[
				'question_id' => $question_id,
				'category_id' => $this->id,
			],
			[ '%d', '%d' ]
		);

		if ( false === $result ) {
			return new WP_Error(
				'ppq_db_error',
				__( 'Failed to remove category from question.', 'pressprimer-quiz' )
			);
		}

		// Update count
		self::update_counts( $this->id );

		return true;
	}

	/**
	 * Get by slug
	 *
	 * Retrieves category by slug and taxonomy.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug     Category slug.
	 * @param string $taxonomy Taxonomy type.
	 * @return static|null Category instance or null if not found.
	 */
	public static function get_by_slug( $slug, $taxonomy = 'category' ) {
		global $wpdb;

		$table = self::get_full_table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE slug = %s AND taxonomy = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$slug,
				$taxonomy
			)
		);

		return $row ? self::from_row( $row ) : null;
	}

	/**
	 * Delete category
	 *
	 * Overrides parent to handle relationships and hierarchies.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete() {
		global $wpdb;

		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppq_no_id',
				__( 'Cannot delete category without ID.', 'pressprimer-quiz' )
			);
		}

		// If this is a category with children, move children to parent or root
		if ( 'category' === $this->taxonomy ) {
			$children = self::find(
				[
					'where' => [ 'parent_id' => $this->id ],
				]
			);

			if ( ! empty( $children ) ) {
				foreach ( $children as $child ) {
					$child->parent_id = $this->parent_id;
					$child->save();
				}
			}
		}

		// Remove all question relationships
		$tax_table = $wpdb->prefix . 'ppq_question_tax';
		$wpdb->delete(
			$tax_table,
			[ 'category_id' => $this->id ],
			[ '%d' ]
		);

		// Delete the category
		return parent::delete();
	}
}
