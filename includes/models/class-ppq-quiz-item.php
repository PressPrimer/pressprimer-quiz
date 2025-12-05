<?php
/**
 * Quiz Item Model
 *
 * Represents a question assigned to a fixed quiz.
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
 * Quiz Item model class
 *
 * Manages the relationship between quizzes and questions in fixed mode.
 * Each item represents one question in a specific order with a weight multiplier.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Quiz_Item extends PressPrimer_Quiz_Model {

	/**
	 * Quiz ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $quiz_id;

	/**
	 * Question ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $question_id;

	/**
	 * Display order (0-based index)
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $order_index = 0;

	/**
	 * Point weight multiplier
	 *
	 * @since 1.0.0
	 * @var float
	 */
	public $weight = 1.00;

	/**
	 * Get table name
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name() {
		return 'ppq_quiz_items';
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
			'quiz_id',
			'question_id',
			'order_index',
			'weight',
		];
	}

	/**
	 * Create new quiz item
	 *
	 * Validates input and creates a quiz item record.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Item data.
	 * @return int|WP_Error Item ID on success, WP_Error on failure.
	 */
	public static function create( array $data ) {
		// Validate required fields
		if ( empty( $data['quiz_id'] ) ) {
			return new WP_Error(
				'ppq_invalid_quiz_id',
				__( 'Quiz ID is required.', 'pressprimer-quiz' )
			);
		}

		if ( empty( $data['question_id'] ) ) {
			return new WP_Error(
				'ppq_invalid_question_id',
				__( 'Question ID is required.', 'pressprimer-quiz' )
			);
		}

		// Sanitize IDs
		$data['quiz_id']     = absint( $data['quiz_id'] );
		$data['question_id'] = absint( $data['question_id'] );

		// Validate quiz exists
		if ( ! PressPrimer_Quiz_Quiz::exists( $data['quiz_id'] ) ) {
			return new WP_Error(
				'ppq_quiz_not_found',
				__( 'Quiz not found.', 'pressprimer-quiz' )
			);
		}

		// Validate question exists
		if ( ! PressPrimer_Quiz_Question::exists( $data['question_id'] ) ) {
			return new WP_Error(
				'ppq_question_not_found',
				__( 'Question not found.', 'pressprimer-quiz' )
			);
		}

		// Check for duplicate (same question already in quiz)
		if ( self::exists_for_quiz( $data['quiz_id'], $data['question_id'] ) ) {
			return new WP_Error(
				'ppq_duplicate_item',
				__( 'This question is already in the quiz.', 'pressprimer-quiz' )
			);
		}

		// Sanitize order_index
		if ( isset( $data['order_index'] ) ) {
			$data['order_index'] = absint( $data['order_index'] );
		} else {
			// Set to next available position
			$data['order_index'] = self::get_next_order_index( $data['quiz_id'] );
		}

		// Sanitize weight
		if ( isset( $data['weight'] ) ) {
			$weight = floatval( $data['weight'] );
			if ( $weight < 0 || $weight > 100 ) {
				return new WP_Error(
					'ppq_invalid_weight',
					__( 'Weight must be between 0 and 100.', 'pressprimer-quiz' )
				);
			}
			$data['weight'] = $weight;
		}

		return parent::create( $data );
	}

	/**
	 * Check if item exists for quiz
	 *
	 * Checks if a specific question is already in a quiz.
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id     Quiz ID.
	 * @param int $question_id Question ID.
	 * @return bool True if item exists, false otherwise.
	 */
	public static function exists_for_quiz( int $quiz_id, int $question_id ) {
		global $wpdb;

		$table = static::get_full_table_name();

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE quiz_id = %d AND question_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quiz_id,
				$question_id
			)
		);

		return (bool) $exists;
	}

	/**
	 * Get next order index for quiz
	 *
	 * Returns the next available order_index for a quiz.
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return int Next order index.
	 */
	public static function get_next_order_index( int $quiz_id ) {
		global $wpdb;

		$table = static::get_full_table_name();

		$max_index = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(order_index) FROM {$table} WHERE quiz_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quiz_id
			)
		);

		// If no items exist, start at 0; otherwise increment
		return null === $max_index ? 0 : absint( $max_index ) + 1;
	}

	/**
	 * Get items for quiz
	 *
	 * Returns all items for a quiz in order.
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array Array of PressPrimer_Quiz_Quiz_Item objects.
	 */
	public static function get_for_quiz( int $quiz_id ) {
		global $wpdb;

		$table = static::get_full_table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE quiz_id = %d ORDER BY order_index ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quiz_id
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
	 * Reorder items
	 *
	 * Updates the order_index for multiple items at once.
	 * Expects array of item_id => new_order_index pairs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $order_map Array of item_id => order_index pairs.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function reorder( array $order_map ) {
		global $wpdb;

		if ( empty( $order_map ) ) {
			return new WP_Error(
				'ppq_empty_order',
				__( 'Order map cannot be empty.', 'pressprimer-quiz' )
			);
		}

		$table = static::get_full_table_name();

		// Start transaction to ensure all-or-nothing
		$wpdb->query( 'START TRANSACTION' );

		try {
			foreach ( $order_map as $item_id => $new_index ) {
				$item_id   = absint( $item_id );
				$new_index = absint( $new_index );

				$result = $wpdb->update(
					$table,
					[ 'order_index' => $new_index ],
					[ 'id' => $item_id ],
					[ '%d' ],
					[ '%d' ]
				);

				// Check for database error
				if ( false === $result ) {
					throw new Exception(
						sprintf(
							/* translators: %d: item ID */
							__( 'Failed to update order for item %d.', 'pressprimer-quiz' ),
							$item_id
						)
					);
				}
			}

			$wpdb->query( 'COMMIT' );

			return true;

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			return new WP_Error(
				'ppq_reorder_failed',
				$e->getMessage()
			);
		}
	}

	/**
	 * Move item up
	 *
	 * Swaps order with the previous item.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function move_up() {
		// Can't move up if already first
		if ( 0 === $this->order_index ) {
			return new WP_Error(
				'ppq_already_first',
				__( 'Item is already first.', 'pressprimer-quiz' )
			);
		}

		global $wpdb;
		$table = static::get_full_table_name();

		// Find the item above this one
		$previous_item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE quiz_id = %d AND order_index = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->quiz_id,
				$this->order_index - 1
			)
		);

		if ( ! $previous_item ) {
			return new WP_Error(
				'ppq_no_previous_item',
				__( 'No previous item found.', 'pressprimer-quiz' )
			);
		}

		// Swap order indexes
		return self::reorder(
			[
				$this->id          => $this->order_index - 1,
				$previous_item->id => $this->order_index,
			]
		);
	}

	/**
	 * Move item down
	 *
	 * Swaps order with the next item.
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function move_down() {
		global $wpdb;
		$table = static::get_full_table_name();

		// Find the item below this one
		$next_item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE quiz_id = %d AND order_index = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->quiz_id,
				$this->order_index + 1
			)
		);

		if ( ! $next_item ) {
			return new WP_Error(
				'ppq_already_last',
				__( 'Item is already last.', 'pressprimer-quiz' )
			);
		}

		// Swap order indexes
		return self::reorder(
			[
				$this->id      => $this->order_index + 1,
				$next_item->id => $this->order_index,
			]
		);
	}

	/**
	 * Delete item and reindex remaining items
	 *
	 * Removes the item and updates order_index for all subsequent items
	 * to maintain sequential ordering (no gaps).
	 *
	 * @since 1.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete() {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			// Delete this item
			$result = parent::delete();

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Reindex all items with higher order_index
			$table = static::get_full_table_name();

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET order_index = order_index - 1 WHERE quiz_id = %d AND order_index > %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$this->quiz_id,
					$this->order_index
				)
			);

			$wpdb->query( 'COMMIT' );

			return true;

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			return new WP_Error(
				'ppq_delete_failed',
				$e->getMessage()
			);
		}
	}

	/**
	 * Get question
	 *
	 * Returns the question object for this item.
	 *
	 * @since 1.0.0
	 *
	 * @return PressPrimer_Quiz_Question|null Question object or null if not found.
	 */
	public function get_question() {
		return PressPrimer_Quiz_Question::get( $this->question_id );
	}
}
