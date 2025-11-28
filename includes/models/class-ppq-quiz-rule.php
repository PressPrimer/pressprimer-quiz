<?php
/**
 * Quiz Rule Model
 *
 * Represents a dynamic quiz generation rule.
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
 * Quiz Rule model class
 *
 * Manages dynamic quiz generation rules that select questions based on criteria.
 * Each rule specifies filters (bank, categories, tags, difficulty) and count.
 *
 * @since 1.0.0
 */
class PPQ_Quiz_Rule extends PPQ_Model {

	/**
	 * Quiz ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $quiz_id;

	/**
	 * Rule execution order (0-based index)
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $rule_order = 0;

	/**
	 * Bank ID filter (null = any bank)
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $bank_id;

	/**
	 * Category IDs filter (JSON array)
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $category_ids_json;

	/**
	 * Tag IDs filter (JSON array)
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $tag_ids_json;

	/**
	 * Difficulties filter (JSON array)
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $difficulties_json;

	/**
	 * Number of questions to select
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $question_count = 10;

	/**
	 * Get table name
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name() {
		return 'ppq_quiz_rules';
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
			'rule_order',
			'bank_id',
			'category_ids_json',
			'tag_ids_json',
			'difficulties_json',
			'question_count',
		];
	}

	/**
	 * Create new quiz rule
	 *
	 * Validates input and creates a quiz rule record.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Rule data.
	 * @return int|WP_Error Rule ID on success, WP_Error on failure.
	 */
	public static function create( array $data ) {
		// Validate required fields
		if ( empty( $data['quiz_id'] ) ) {
			return new WP_Error(
				'ppq_invalid_quiz_id',
				__( 'Quiz ID is required.', 'pressprimer-quiz' )
			);
		}

		// Sanitize quiz_id
		$data['quiz_id'] = absint( $data['quiz_id'] );

		// Validate quiz exists
		if ( ! PPQ_Quiz::exists( $data['quiz_id'] ) ) {
			return new WP_Error(
				'ppq_quiz_not_found',
				__( 'Quiz not found.', 'pressprimer-quiz' )
			);
		}

		// Sanitize rule_order
		if ( isset( $data['rule_order'] ) ) {
			$data['rule_order'] = absint( $data['rule_order'] );
		} else {
			// Set to next available position
			$data['rule_order'] = self::get_next_rule_order( $data['quiz_id'] );
		}

		// Sanitize bank_id
		if ( isset( $data['bank_id'] ) && ! empty( $data['bank_id'] ) ) {
			$data['bank_id'] = absint( $data['bank_id'] );

			// Validate bank exists
			if ( ! PPQ_Bank::exists( $data['bank_id'] ) ) {
				return new WP_Error(
					'ppq_bank_not_found',
					__( 'Bank not found.', 'pressprimer-quiz' )
				);
			}
		}

		// Sanitize question_count
		if ( isset( $data['question_count'] ) ) {
			$question_count = absint( $data['question_count'] );
			if ( $question_count < 1 || $question_count > 500 ) {
				return new WP_Error(
					'ppq_invalid_question_count',
					__( 'Question count must be between 1 and 500.', 'pressprimer-quiz' )
				);
			}
			$data['question_count'] = $question_count;
		}

		// Validate and encode JSON fields
		if ( isset( $data['category_ids'] ) && is_array( $data['category_ids'] ) ) {
			$data['category_ids_json'] = wp_json_encode( array_map( 'absint', $data['category_ids'] ) );
			unset( $data['category_ids'] );
		}

		if ( isset( $data['tag_ids'] ) && is_array( $data['tag_ids'] ) ) {
			$data['tag_ids_json'] = wp_json_encode( array_map( 'absint', $data['tag_ids'] ) );
			unset( $data['tag_ids'] );
		}

		if ( isset( $data['difficulties'] ) && is_array( $data['difficulties'] ) ) {
			// Validate difficulty values
			$valid_difficulties = [ 'beginner', 'intermediate', 'advanced', 'expert' ];
			$difficulties = array_filter(
				$data['difficulties'],
				function( $diff ) use ( $valid_difficulties ) {
					return in_array( $diff, $valid_difficulties, true );
				}
			);

			$data['difficulties_json'] = wp_json_encode( $difficulties );
			unset( $data['difficulties'] );
		}

		return parent::create( $data );
	}

	/**
	 * Get next rule order for quiz
	 *
	 * Returns the next available rule_order for a quiz.
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return int Next rule order.
	 */
	public static function get_next_rule_order( int $quiz_id ) {
		global $wpdb;

		$table = static::get_full_table_name();

		$max_order = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(rule_order) FROM {$table} WHERE quiz_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quiz_id
			)
		);

		// If no rules exist, start at 0; otherwise increment
		return null === $max_order ? 0 : absint( $max_order ) + 1;
	}

	/**
	 * Get rules for quiz
	 *
	 * Returns all rules for a quiz in order.
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array Array of PPQ_Quiz_Rule objects.
	 */
	public static function get_for_quiz( int $quiz_id ) {
		global $wpdb;

		$table = static::get_full_table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE quiz_id = %d ORDER BY rule_order ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quiz_id
			)
		);

		$rules = [];

		if ( $rows ) {
			foreach ( $rows as $row ) {
				$rules[] = static::from_row( $row );
			}
		}

		return $rules;
	}

	/**
	 * Reorder rules
	 *
	 * Updates the rule_order for multiple rules at once.
	 * Expects array of rule_id => new_rule_order pairs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $order_map Array of rule_id => rule_order pairs.
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
			foreach ( $order_map as $rule_id => $new_order ) {
				$rule_id = absint( $rule_id );
				$new_order = absint( $new_order );

				$result = $wpdb->update(
					$table,
					[ 'rule_order' => $new_order ],
					[ 'id' => $rule_id ],
					[ '%d' ],
					[ '%d' ]
				);

				// Check for database error
				if ( false === $result ) {
					throw new Exception(
						sprintf(
							/* translators: %d: rule ID */
							__( 'Failed to update order for rule %d.', 'pressprimer-quiz' ),
							$rule_id
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
	 * Delete rule and reindex remaining rules
	 *
	 * Removes the rule and updates rule_order for all subsequent rules
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
			// Delete this rule
			$result = parent::delete();

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Reindex all rules with higher rule_order
			$table = static::get_full_table_name();

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET rule_order = rule_order - 1 WHERE quiz_id = %d AND rule_order > %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$this->quiz_id,
					$this->rule_order
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
	 * Get matching questions
	 *
	 * Finds all questions that match this rule's criteria.
	 * Returns question IDs that can be randomly selected for quiz generation.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of question IDs.
	 */
	public function get_matching_questions() {
		global $wpdb;

		$questions_table = $wpdb->prefix . 'ppq_questions';
		$tax_table = $wpdb->prefix . 'ppq_question_tax';
		$bank_questions_table = $wpdb->prefix . 'ppq_bank_questions';

		// Debug: Check bank_questions table
		if ( ! empty( $this->bank_id ) ) {
			error_log( '=== PPQ Rule Debug for bank_id=' . $this->bank_id . ' ===' );

			// Check how many records exist in bank_questions for this bank
			$bank_count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$bank_questions_table} WHERE bank_id = %d",
					$this->bank_id
				)
			);
			error_log( 'Total records in bank_questions for bank ' . $this->bank_id . ': ' . $bank_count );

			// Get question IDs in this bank
			$bank_question_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT question_id FROM {$bank_questions_table} WHERE bank_id = %d",
					$this->bank_id
				)
			);
			error_log( 'Question IDs in bank: ' . print_r( $bank_question_ids, true ) );

			// For each question, check its status and deleted_at
			if ( ! empty( $bank_question_ids ) ) {
				foreach ( $bank_question_ids as $q_id ) {
					$q_data = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT id, status, deleted_at FROM {$questions_table} WHERE id = %d",
							$q_id
						)
					);
					error_log( 'Question ' . $q_id . ' data: ' . print_r( $q_data, true ) );
				}
			}
		}

		// Start with base query - published and draft questions, non-deleted
		$where_clauses = [
			"q.status IN ('published', 'draft')",
			'q.deleted_at IS NULL',
		];
		$join_clauses = [];
		$where_values = [];

		// Filter by bank if specified
		if ( ! empty( $this->bank_id ) ) {
			$join_clauses[] = "INNER JOIN {$bank_questions_table} bq ON q.id = bq.question_id";
			$where_clauses[] = 'bq.bank_id = %d';
			$where_values[] = $this->bank_id;
		}

		// Filter by categories if specified
		$category_ids = $this->get_category_ids();
		if ( ! empty( $category_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
			$join_clauses[] = "INNER JOIN {$tax_table} tc ON q.id = tc.question_id";
			$where_clauses[] = "tc.taxonomy = 'category'";
			$where_clauses[] = "tc.category_id IN ($placeholders)";
			$where_values = array_merge( $where_values, $category_ids );
		}

		// Filter by tags if specified
		$tag_ids = $this->get_tag_ids();
		if ( ! empty( $tag_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $tag_ids ), '%d' ) );
			$join_clauses[] = "INNER JOIN {$tax_table} tt ON q.id = tt.question_id";
			$where_clauses[] = "tt.taxonomy = 'tag'";
			$where_clauses[] = "tt.category_id IN ($placeholders)";
			$where_values = array_merge( $where_values, $tag_ids );
		}

		// Filter by difficulties if specified
		$difficulties = $this->get_difficulties();
		if ( ! empty( $difficulties ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $difficulties ), '%s' ) );
			$where_clauses[] = "q.difficulty_author IN ($placeholders)";
			$where_values = array_merge( $where_values, $difficulties );
		}

		// Build final query
		$joins = implode( ' ', $join_clauses );
		$where = implode( ' AND ', $where_clauses );

		$query = "SELECT DISTINCT q.id FROM {$questions_table} q {$joins} WHERE {$where}";

		// Prepare query if we have values
		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		error_log( 'PPQ Rule Query: ' . $query );

		$results = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		error_log( 'PPQ Rule Results: ' . print_r( $results, true ) );
		error_log( 'PPQ Rule Count: ' . count( $results ) );

		return $results ? array_map( 'absint', $results ) : [];
	}

	/**
	 * Get category IDs
	 *
	 * Decodes and returns category IDs from JSON.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of category IDs.
	 */
	public function get_category_ids() {
		if ( empty( $this->category_ids_json ) ) {
			return [];
		}

		$ids = json_decode( $this->category_ids_json, true );

		return is_array( $ids ) ? array_map( 'absint', $ids ) : [];
	}

	/**
	 * Get tag IDs
	 *
	 * Decodes and returns tag IDs from JSON.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of tag IDs.
	 */
	public function get_tag_ids() {
		if ( empty( $this->tag_ids_json ) ) {
			return [];
		}

		$ids = json_decode( $this->tag_ids_json, true );

		return is_array( $ids ) ? array_map( 'absint', $ids ) : [];
	}

	/**
	 * Get difficulties
	 *
	 * Decodes and returns difficulty filters from JSON.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of difficulty strings.
	 */
	public function get_difficulties() {
		if ( empty( $this->difficulties_json ) ) {
			return [];
		}

		$difficulties = json_decode( $this->difficulties_json, true );

		return is_array( $difficulties ) ? $difficulties : [];
	}

	/**
	 * Get matching question count
	 *
	 * Returns the count of questions that match this rule's criteria.
	 * Useful for UI to show how many questions are available.
	 *
	 * @since 1.0.0
	 *
	 * @return int Count of matching questions.
	 */
	public function get_matching_count() {
		$question_ids = $this->get_matching_questions();

		return count( $question_ids );
	}
}
