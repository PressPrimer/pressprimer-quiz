<?php
/**
 * Base model class
 *
 * Abstract base class providing common CRUD functionality for all models.
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
 * Base model class
 *
 * Provides Active Record pattern for database models.
 * All model classes (Question, Quiz, Attempt, etc.) extend this base class.
 *
 * @since 1.0.0
 */
abstract class PressPrimer_Quiz_Model {

	/**
	 * Primary key ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $id = 0;

	/**
	 * Get table name
	 *
	 * Returns the database table name for this model.
	 * Must be implemented by child classes.
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	abstract protected static function get_table_name();

	/**
	 * Get fillable fields
	 *
	 * Returns array of field names that can be mass-assigned.
	 * Must be implemented by child classes.
	 *
	 * @since 1.0.0
	 *
	 * @return array Field names.
	 */
	abstract protected static function get_fillable_fields();

	/**
	 * Get full table name with prefix
	 *
	 * Returns the complete table name including WordPress prefix.
	 *
	 * @since 1.0.0
	 *
	 * @return string Full table name with prefix.
	 */
	protected static function get_full_table_name() {
		global $wpdb;
		return $wpdb->prefix . static::get_table_name();
	}

	/**
	 * Get record by ID
	 *
	 * Retrieves a single record from the database by primary key.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Record ID.
	 * @return static|null Model instance or null if not found.
	 */
	public static function get( $id ) {
		global $wpdb;

		$id    = absint( $id );
		$table = static::get_full_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries; caching handled by calling code where appropriate
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		return $row ? static::from_row( $row ) : null;
	}

	/**
	 * Create instance from database row
	 *
	 * Factory method to create a model instance from a database row object.
	 *
	 * @since 1.0.0
	 *
	 * @param object $row Database row object.
	 * @return static Model instance.
	 */
	public static function from_row( $row ) {
		$instance = new static();

		foreach ( get_object_vars( $row ) as $key => $value ) {
			if ( property_exists( $instance, $key ) ) {
				$instance->$key = $value;
			}
		}

		return $instance;
	}

	/**
	 * Create new record
	 *
	 * Inserts a new record into the database.
	 * Child classes should override this to add validation.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Record data as associative array.
	 * @return int|WP_Error Record ID on success, WP_Error on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;

		// Filter to only fillable fields
		$fillable = static::get_fillable_fields();
		$data     = array_intersect_key( $data, array_flip( $fillable ) );

		if ( empty( $data ) ) {
			return new WP_Error(
				'ppq_no_data',
				__( 'No valid data provided for creation.', 'pressprimer-quiz' )
			);
		}

		$table = static::get_full_table_name();

		// Insert record
		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			return new WP_Error(
				'ppq_db_error',
				__( 'Database error: Failed to create record.', 'pressprimer-quiz' ),
				[ 'db_error' => $wpdb->last_error ]
			);
		}

		return $wpdb->insert_id;
	}

	/**
	 * Save changes to database
	 *
	 * Updates the record in the database.
	 * Only updates fields that are fillable.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function save() {
		global $wpdb;

		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppq_no_id',
				__( 'Cannot save record without ID.', 'pressprimer-quiz' )
			);
		}

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

		// Update record
		$result = $wpdb->update(
			$table,
			$data,
			[ 'id' => $this->id ],
			null,
			[ '%d' ]
		);

		if ( false === $result ) {
			return new WP_Error(
				'ppq_db_error',
				__( 'Database error: Failed to save record.', 'pressprimer-quiz' ),
				[ 'db_error' => $wpdb->last_error ]
			);
		}

		return true;
	}

	/**
	 * Delete record
	 *
	 * Removes the record from the database (hard delete).
	 * Child classes may override this to implement soft delete.
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
				__( 'Cannot delete record without ID.', 'pressprimer-quiz' )
			);
		}

		$table = static::get_full_table_name();

		$result = $wpdb->delete(
			$table,
			[ 'id' => $this->id ],
			[ '%d' ]
		);

		if ( false === $result ) {
			return new WP_Error(
				'ppq_db_error',
				__( 'Database error: Failed to delete record.', 'pressprimer-quiz' ),
				[ 'db_error' => $wpdb->last_error ]
			);
		}

		return true;
	}

	/**
	 * Find records
	 *
	 * Retrieves multiple records based on conditions.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 *                    - where: array of field => value conditions
	 *                    - order_by: column to order by
	 *                    - order: ASC or DESC
	 *                    - limit: number of records to return
	 *                    - offset: number of records to skip
	 * @return array Array of model instances.
	 */
	public static function find( array $args = [] ) {
		global $wpdb;

		$defaults = [
			'where'    => [],
			'order_by' => 'id',
			'order'    => 'DESC',
			'limit'    => null,
			'offset'   => null,
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = static::get_full_table_name();

		// Build WHERE clause
		$where_clauses = [];
		$where_values  = [];

		if ( ! empty( $args['where'] ) ) {
			foreach ( $args['where'] as $field => $value ) {
				if ( null === $value ) {
					// Use IS NULL for null values
					$where_clauses[] = "`{$field}` IS NULL";
				} else {
					$where_clauses[] = "`{$field}` = %s";
					$where_values[]  = $value;
				}
			}
		}

		$where_sql = ! empty( $where_clauses )
			? 'WHERE ' . implode( ' AND ', $where_clauses )
			: '';

		// Build ORDER BY clause
		$order_by  = sanitize_sql_orderby( "{$args['order_by']} {$args['order']}" );
		$order_sql = $order_by ? "ORDER BY {$order_by}" : '';

		// Build LIMIT clause
		$limit_sql = '';
		if ( null !== $args['limit'] ) {
			$limit     = absint( $args['limit'] );
			$offset    = absint( $args['offset'] ?? 0 );
			$limit_sql = $offset > 0
				? "LIMIT {$offset}, {$limit}"
				: "LIMIT {$limit}";
		}

		// Build final query
		$query = "SELECT * FROM {$table} {$where_sql} {$order_sql} {$limit_sql}";

		// Prepare and execute
		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table queries with dynamic conditions

		// Convert rows to model instances
		$results = [];
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$results[] = static::from_row( $row );
			}
		}

		return $results;
	}

	/**
	 * Count records
	 *
	 * Returns the count of records matching conditions.
	 *
	 * @since 1.0.0
	 *
	 * @param array $where Array of field => value conditions.
	 * @return int Record count.
	 */
	public static function count( array $where = [] ) {
		global $wpdb;

		$table = static::get_full_table_name();

		// Build WHERE clause
		$where_clauses = [];
		$where_values  = [];

		if ( ! empty( $where ) ) {
			foreach ( $where as $field => $value ) {
				$where_clauses[] = "`{$field}` = %s";
				$where_values[]  = $value;
			}
		}

		$where_sql = ! empty( $where_clauses )
			? 'WHERE ' . implode( ' AND ', $where_clauses )
			: '';

		// Build query
		$query = "SELECT COUNT(*) FROM {$table} {$where_sql}";

		// Prepare and execute
		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table count queries
	}

	/**
	 * Check if record exists
	 *
	 * Checks if a record with the given ID exists.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Record ID.
	 * @return bool True if exists, false otherwise.
	 */
	public static function exists( $id ) {
		global $wpdb;

		$id    = absint( $id );
		$table = static::get_full_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple existence check, not worth caching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		return (bool) $exists;
	}

	/**
	 * Refresh from database
	 *
	 * Reloads the record data from the database.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function refresh() {
		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppq_no_id',
				__( 'Cannot refresh record without ID.', 'pressprimer-quiz' )
			);
		}

		$fresh = static::get( $this->id );

		if ( ! $fresh ) {
			return new WP_Error(
				'ppq_not_found',
				__( 'Record not found in database.', 'pressprimer-quiz' )
			);
		}

		// Update all properties
		foreach ( get_object_vars( $fresh ) as $key => $value ) {
			$this->$key = $value;
		}

		return true;
	}

	/**
	 * Convert to array
	 *
	 * Returns model data as an associative array.
	 *
	 * @since 1.0.0
	 *
	 * @return array Model data.
	 */
	public function to_array() {
		$data = [];

		foreach ( get_object_vars( $this ) as $key => $value ) {
			// Skip private properties (those starting with underscore)
			if ( 0 !== strpos( $key, '_' ) ) {
				$data[ $key ] = $value;
			}
		}

		return $data;
	}
}
