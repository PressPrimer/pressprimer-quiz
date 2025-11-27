<?php
/**
 * REST API Controller
 *
 * Handles REST API endpoints for the question editor.
 *
 * @package PressPrimer_Quiz
 * @subpackage API
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Controller class
 *
 * @since 1.0.0
 */
class PPQ_REST_Controller {

	/**
	 * Initialize REST API routes
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// Questions endpoints
		register_rest_route( 'ppq/v1', '/questions', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_questions' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_question' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
		] );

		register_rest_route( 'ppq/v1', '/questions/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_question' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_question' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
		] );

		// Taxonomies endpoints
		register_rest_route( 'ppq/v1', '/taxonomies', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_taxonomies' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_taxonomy' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
		] );

		// Banks endpoints
		register_rest_route( 'ppq/v1', '/banks', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_banks' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		// Quizzes endpoints
		register_rest_route( 'ppq/v1', '/quizzes', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_quizzes' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_quiz' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
		] );

		register_rest_route( 'ppq/v1', '/quizzes/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_quiz' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_quiz' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
		] );

		// Quiz items endpoints (fixed mode)
		register_rest_route( 'ppq/v1', '/quizzes/(?P<quiz_id>\d+)/items', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_quiz_items' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add_quiz_items' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
		] );

		register_rest_route( 'ppq/v1', '/quizzes/(?P<quiz_id>\d+)/items/(?P<item_id>\d+)', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_quiz_item' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_quiz_item' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
		] );

		register_rest_route( 'ppq/v1', '/quizzes/(?P<quiz_id>\d+)/items/reorder', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'reorder_quiz_items' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );

		// Quiz rules endpoints (dynamic mode)
		register_rest_route( 'ppq/v1', '/quizzes/(?P<quiz_id>\d+)/rules', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_quiz_rules' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_quiz_rule' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
		] );

		register_rest_route( 'ppq/v1', '/quizzes/(?P<quiz_id>\d+)/rules/(?P<rule_id>\d+)', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_quiz_rule' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_quiz_rule' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
		] );

		register_rest_route( 'ppq/v1', '/quizzes/(?P<quiz_id>\d+)/rules/reorder', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'reorder_quiz_rules' ],
			'permission_callback' => [ $this, 'check_permission' ],
		] );
	}

	/**
	 * Check permission
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user has permission.
	 */
	public function check_permission() {
		return current_user_can( 'ppq_manage_own' ) || current_user_can( 'ppq_manage_all' );
	}

	/**
	 * Get questions
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_questions( $request ) {
		global $wpdb;

		$per_page = absint( $request->get_param( 'per_page' ) ?: 100 );
		$page = absint( $request->get_param( 'page' ) ?: 1 );
		$offset = ( $page - 1 ) * $per_page;
		$search = sanitize_text_field( $request->get_param( 'search' ) ?: '' );
		$status = sanitize_key( $request->get_param( 'status' ) ?: '' );
		$type = sanitize_key( $request->get_param( 'type' ) ?: '' );
		$difficulty = sanitize_key( $request->get_param( 'difficulty' ) ?: '' );
		$category_id = absint( $request->get_param( 'category_id' ) ?: 0 );
		$bank_id = absint( $request->get_param( 'bank_id' ) ?: 0 );

		$questions_table = $wpdb->prefix . 'ppq_questions';
		$revisions_table = $wpdb->prefix . 'ppq_question_revisions';
		$tax_table = $wpdb->prefix . 'ppq_question_tax';
		$bank_questions_table = $wpdb->prefix . 'ppq_bank_questions';

		// Build where clauses
		$where = [ 'q.deleted_at IS NULL' ];
		$where_values = [];

		// Filter by status if provided
		if ( ! empty( $status ) ) {
			$where[] = 'q.status = %s';
			$where_values[] = $status;
		}

		if ( ! empty( $type ) ) {
			$where[] = 'q.type = %s';
			$where_values[] = $type;
		}

		if ( ! empty( $difficulty ) ) {
			$where[] = 'q.difficulty_author = %s';
			$where_values[] = $difficulty;
		}

		// Filter by category
		if ( $category_id > 0 ) {
			$where[] = "q.id IN (SELECT question_id FROM {$tax_table} WHERE taxonomy = 'category' AND category_id = %d)";
			$where_values[] = $category_id;
		}

		// Filter by bank
		if ( $bank_id > 0 ) {
			$where[] = "q.id IN (SELECT question_id FROM {$bank_questions_table} WHERE bank_id = %d)";
			$where_values[] = $bank_id;
		}

		// Search in stem
		if ( ! empty( $search ) ) {
			$where[] = "r.stem LIKE %s";
			$where_values[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$where_clause = implode( ' AND ', $where );

		// Get total count
		$count_query = "SELECT COUNT(DISTINCT q.id)
			FROM {$questions_table} q
			LEFT JOIN {$revisions_table} r ON q.current_revision_id = r.id
			WHERE {$where_clause}";

		$total = $wpdb->get_var( $wpdb->prepare( $count_query, $where_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Get questions
		$query = "SELECT DISTINCT q.*, r.stem
			FROM {$questions_table} q
			LEFT JOIN {$revisions_table} r ON q.current_revision_id = r.id
			WHERE {$where_clause}
			ORDER BY q.created_at DESC
			LIMIT %d OFFSET %d";

		$where_values[] = $per_page;
		$where_values[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare( $query, $where_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$data = [];
		foreach ( $rows as $row ) {
			$full_stem = strip_tags( $row->stem );
			$short_stem = mb_strlen( $full_stem ) > 60 ? mb_substr( $full_stem, 0, 60 ) . '...' : $full_stem;

			$data[] = [
				'id'         => $row->id,
				'type'       => $row->type,
				'difficulty' => $row->difficulty_author,
				'points'     => $row->max_points,
				'status'     => $row->status,
				'stem'       => $short_stem,
				'stem_full'  => $full_stem,
				'created_at' => $row->created_at,
			];
		}

		return new WP_REST_Response( [
			'questions' => $data,
			'total'     => absint( $total ),
			'page'      => $page,
			'per_page'  => $per_page,
		], 200 );
	}

	/**
	 * Get single question
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_question( $request ) {
		$question_id = absint( $request['id'] );
		$question = PPQ_Question::get( $question_id );

		if ( ! $question ) {
			return new WP_Error( 'not_found', __( 'Question not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		// Check ownership
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $question->author_id ) !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission.', 'pressprimer-quiz' ), [ 'status' => 403 ] );
		}

		$revision = $question->get_current_revision();
		$categories = $question->get_categories();
		$tags = $question->get_tags();

		// Get bank memberships
		global $wpdb;
		$bank_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT bank_id FROM {$wpdb->prefix}ppq_bank_questions WHERE question_id = %d",
				$question_id
			)
		);

		$data = [
			'id'                => $question->id,
			'type'              => $question->type,
			'difficulty'        => $question->difficulty_author,
			'timeLimit'         => $question->expected_seconds,
			'points'            => $question->max_points,
			'stem'              => $revision ? $revision->stem : '',
			'answers'           => $revision ? $revision->get_answers() : [],
			'feedbackCorrect'   => $revision ? $revision->feedback_correct : '',
			'feedbackIncorrect' => $revision ? $revision->feedback_incorrect : '',
			'categories'        => array_map( function( $cat ) { return $cat->id; }, $categories ),
			'tags'              => array_map( function( $tag ) { return $tag->id; }, $tags ),
			'banks'             => array_map( 'absint', $bank_ids ),
		];

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Create question
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function create_question( $request ) {
		$data = $request->get_json_params();

		// Debug logging
		error_log( 'PPQ Create Question - Received data: ' . print_r( $data, true ) );

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Create question using static create method
			$question_id = PPQ_Question::create( [
				'uuid'              => wp_generate_uuid4(),
				'type'              => sanitize_key( $data['type'] ),
				'difficulty_author' => sanitize_key( $data['difficulty'] ?? '' ),
				'expected_seconds'  => absint( $data['timeLimit'] ?? 0 ),
				'max_points'        => floatval( $data['points'] ?? 1 ),
				'author_id'         => get_current_user_id(),
				'status'            => 'draft',
			] );

			if ( is_wp_error( $question_id ) ) {
				throw new Exception( $question_id->get_error_message() );
			}

			// Load the newly created question
			$question = PPQ_Question::get( $question_id );

			if ( ! $question ) {
				throw new Exception( __( 'Failed to load created question.', 'pressprimer-quiz' ) );
			}

			// Convert answer format from React (isCorrect) to database (is_correct)
			$answers = array_map( function( $answer ) {
				return [
					'id'         => $answer['id'] ?? '',
					'text'       => $answer['text'] ?? '',
					'is_correct' => $answer['isCorrect'] ?? false,
					'feedback'   => $answer['feedback'] ?? '',
					'order'      => $answer['order'] ?? 1,
				];
			}, $data['answers'] ?? [] );

			// Create revision
			$revision = new PPQ_Question_Revision();
			$revision->question_id = $question->id;
			$revision->version = 1;
			$revision->stem = wp_kses_post( $data['stem'] ?? '' );
			$revision->answers_json = wp_json_encode( $answers );
			$revision->settings_json = wp_json_encode( [] );
			$revision->feedback_correct = wp_kses_post( $data['feedbackCorrect'] ?? '' );
			$revision->feedback_incorrect = wp_kses_post( $data['feedbackIncorrect'] ?? '' );
			$revision->content_hash = PPQ_Question_Revision::generate_hash( $revision->stem, $answers );
			$revision->created_by = get_current_user_id();

			$result = $revision->save();

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Update question with revision ID
			$question->current_revision_id = $revision->id;
			$question->save();

			// Set taxonomies
			if ( ! empty( $data['categories'] ) ) {
				$question->set_categories( array_map( 'absint', $data['categories'] ) );
			}

			if ( ! empty( $data['tags'] ) ) {
				$question->set_tags( array_map( 'absint', $data['tags'] ) );
			}

			// Set banks
			if ( ! empty( $data['banks'] ) ) {
				foreach ( $data['banks'] as $bank_id ) {
					$bank = PPQ_Bank::get( absint( $bank_id ) );
					if ( $bank ) {
						$bank->add_question( $question->id );
					}
				}
			}

			$wpdb->query( 'COMMIT' );

			// Debug: Log what was actually saved
			error_log( 'PPQ Create Question - Successfully created question ID: ' . $question->id );
			error_log( 'PPQ Create Question - Stem saved: ' . $revision->stem );

			return new WP_REST_Response( [ 'id' => $question->id ], 201 );

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'create_failed', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Update question
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function update_question( $request ) {
		$question_id = absint( $request['id'] );
		$data = $request->get_json_params();

		// Debug logging
		error_log( 'PPQ Update Question - Received data: ' . print_r( $data, true ) );

		$question = PPQ_Question::get( $question_id );

		if ( ! $question ) {
			return new WP_Error( 'not_found', __( 'Question not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		// Check ownership
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $question->author_id ) !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission.', 'pressprimer-quiz' ), [ 'status' => 403 ] );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Update question metadata
			$question->type = sanitize_key( $data['type'] );
			$question->difficulty_author = sanitize_key( $data['difficulty'] );
			$question->expected_seconds = absint( $data['timeLimit'] );
			$question->max_points = floatval( $data['points'] );

			$result = $question->save();

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Convert answer format from React (isCorrect) to database (is_correct)
			$answers = array_map( function( $answer ) {
				return [
					'id'         => $answer['id'] ?? '',
					'text'       => $answer['text'] ?? '',
					'is_correct' => $answer['isCorrect'] ?? false,
					'feedback'   => $answer['feedback'] ?? '',
					'order'      => $answer['order'] ?? 1,
				];
			}, $data['answers'] ?? [] );

			// Check if content changed (create new revision if it did)
			$current_revision = $question->get_current_revision();
			$new_hash = PPQ_Question_Revision::generate_hash( $data['stem'], $answers );

			if ( ! $current_revision || $current_revision->content_hash !== $new_hash ) {
				// Create new revision
				$revision = new PPQ_Question_Revision();
				$revision->question_id = $question->id;
				$revision->version = $current_revision ? $current_revision->version + 1 : 1;
				$revision->stem = wp_kses_post( $data['stem'] ?? '' );
				$revision->answers_json = wp_json_encode( $answers );
				$revision->settings_json = wp_json_encode( [] );
				$revision->feedback_correct = wp_kses_post( $data['feedbackCorrect'] ?? '' );
				$revision->feedback_incorrect = wp_kses_post( $data['feedbackIncorrect'] ?? '' );
				$revision->content_hash = $new_hash;
				$revision->created_by = get_current_user_id();

				$result = $revision->save();

				if ( is_wp_error( $result ) ) {
					throw new Exception( $result->get_error_message() );
				}

				// Update question with new revision ID
				$question->current_revision_id = $revision->id;
				$question->save();
			}

			// Update taxonomies
			$question->set_categories( array_map( 'absint', $data['categories'] ?? [] ) );
			$question->set_tags( array_map( 'absint', $data['tags'] ?? [] ) );

			// Update banks (this requires more complex logic to handle adds/removes)
			$current_banks = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT bank_id FROM {$wpdb->prefix}ppq_bank_questions WHERE question_id = %d",
					$question_id
				)
			);

			$new_banks = array_map( 'absint', $data['banks'] ?? [] );
			$to_add = array_diff( $new_banks, $current_banks );
			$to_remove = array_diff( $current_banks, $new_banks );

			foreach ( $to_add as $bank_id ) {
				$bank = PPQ_Bank::get( $bank_id );
				if ( $bank ) {
					$bank->add_question( $question->id );
				}
			}

			foreach ( $to_remove as $bank_id ) {
				$bank = PPQ_Bank::get( $bank_id );
				if ( $bank ) {
					$bank->remove_question( $question->id );
				}
			}

			$wpdb->query( 'COMMIT' );

			// Debug: Log what was actually saved
			error_log( 'PPQ Update Question - Successfully updated question ID: ' . $question->id );
			if ( isset( $revision ) ) {
				error_log( 'PPQ Update Question - Stem saved: ' . $revision->stem );
			}

			return new WP_REST_Response( [ 'id' => $question->id ], 200 );

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'update_failed', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Get taxonomies
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_taxonomies( $request ) {
		$type = sanitize_key( $request->get_param( 'type' ) ?? 'category' );

		$items = PPQ_Category::get_all( $type );

		$data = array_map( function( $item ) {
			return [
				'id'   => $item->id,
				'name' => $item->name,
				'slug' => $item->slug,
			];
		}, $items );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Create taxonomy
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function create_taxonomy( $request ) {
		$data = $request->get_json_params();

		$result = PPQ_Category::create( [
			'name'     => sanitize_text_field( $data['name'] ),
			'taxonomy' => sanitize_key( $data['taxonomy'] ),
		] );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'create_failed', $result->get_error_message(), [ 'status' => 500 ] );
		}

		$item = PPQ_Category::get( $result );

		return new WP_REST_Response( [
			'id'   => $item->id,
			'name' => $item->name,
			'slug' => $item->slug,
		], 201 );
	}

	/**
	 * Get banks
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_banks( $request ) {
		$user_id = get_current_user_id();
		$banks = PPQ_Bank::get_for_user( $user_id );

		$data = array_map( function( $bank ) {
			return [
				'id'          => $bank->id,
				'name'        => $bank->name,
				'description' => $bank->description,
			];
		}, $banks );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get quizzes
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_quizzes( $request ) {
		// TODO: Implement listing with pagination/filters
		return new WP_REST_Response( [], 200 );
	}

	/**
	 * Get single quiz
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_quiz( $request ) {
		$quiz_id = absint( $request['id'] );
		$quiz = PPQ_Quiz::get( $quiz_id );

		if ( ! $quiz ) {
			return new WP_Error( 'not_found', __( 'Quiz not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		// Check ownership
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission.', 'pressprimer-quiz' ), [ 'status' => 403 ] );
		}

		$data = [
			'id'                      => $quiz->id,
			'title'                   => $quiz->title,
			'description'             => $quiz->description,
			'featured_image_id'       => $quiz->featured_image_id,
			'status'                  => $quiz->status,
			'mode'                    => $quiz->mode,
			'time_limit_seconds'      => $quiz->time_limit_seconds,
			'pass_percent'            => $quiz->pass_percent,
			'allow_skip'              => $quiz->allow_skip,
			'allow_backward'          => $quiz->allow_backward,
			'allow_resume'            => $quiz->allow_resume,
			'max_attempts'            => $quiz->max_attempts,
			'attempt_delay_minutes'   => $quiz->attempt_delay_minutes,
			'randomize_questions'     => $quiz->randomize_questions,
			'randomize_answers'       => $quiz->randomize_answers,
			'page_mode'               => $quiz->page_mode,
			'questions_per_page'      => $quiz->questions_per_page,
			'show_answers'            => $quiz->show_answers,
			'enable_confidence'       => $quiz->enable_confidence,
			'theme'                   => $quiz->theme,
			'theme_settings_json'     => $quiz->theme_settings_json,
			'band_feedback_json'      => $quiz->band_feedback_json,
			'generation_mode'         => $quiz->generation_mode,
		];

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Create quiz
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function create_quiz( $request ) {
		$data = $request->get_json_params();

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Create quiz
			$quiz_id = PPQ_Quiz::create( [
				'uuid'                    => wp_generate_uuid4(),
				'title'                   => sanitize_text_field( $data['title'] ),
				'description'             => wp_kses_post( $data['description'] ?? '' ),
				'featured_image_id'       => absint( $data['featured_image_id'] ?? 0 ),
				'owner_id'                => get_current_user_id(),
				'status'                  => sanitize_key( $data['status'] ?? 'draft' ),
				'mode'                    => sanitize_key( $data['mode'] ?? 'tutorial' ),
				'time_limit_seconds'      => ! empty( $data['time_limit_seconds'] ) ? absint( $data['time_limit_seconds'] ) : null,
				'pass_percent'            => floatval( $data['pass_percent'] ?? 70 ),
				'allow_skip'              => ! empty( $data['allow_skip'] ),
				'allow_backward'          => ! empty( $data['allow_backward'] ),
				'allow_resume'            => ! empty( $data['allow_resume'] ),
				'max_attempts'            => ! empty( $data['max_attempts'] ) ? absint( $data['max_attempts'] ) : null,
				'attempt_delay_minutes'   => absint( $data['attempt_delay_minutes'] ?? 0 ),
				'randomize_questions'     => ! empty( $data['randomize_questions'] ),
				'randomize_answers'       => ! empty( $data['randomize_answers'] ),
				'page_mode'               => sanitize_key( $data['page_mode'] ?? 'single' ),
				'questions_per_page'      => absint( $data['questions_per_page'] ?? 1 ),
				'show_answers'            => sanitize_key( $data['show_answers'] ?? 'after_submit' ),
				'enable_confidence'       => ! empty( $data['enable_confidence'] ),
				'theme'                   => sanitize_key( $data['theme'] ?? 'default' ),
				'theme_settings_json'     => $data['theme_settings_json'] ?? null,
				'band_feedback_json'      => $data['band_feedback_json'] ?? null,
				'generation_mode'         => sanitize_key( $data['generation_mode'] ?? 'fixed' ),
			] );

			if ( is_wp_error( $quiz_id ) ) {
				throw new Exception( $quiz_id->get_error_message() );
			}

			$wpdb->query( 'COMMIT' );

			return new WP_REST_Response( [ 'id' => $quiz_id ], 201 );

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'create_failed', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Update quiz
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function update_quiz( $request ) {
		$quiz_id = absint( $request['id'] );
		$data = $request->get_json_params();

		$quiz = PPQ_Quiz::get( $quiz_id );

		if ( ! $quiz ) {
			return new WP_Error( 'not_found', __( 'Quiz not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		// Check ownership
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission.', 'pressprimer-quiz' ), [ 'status' => 403 ] );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Update quiz fields
			$quiz->title = sanitize_text_field( $data['title'] );
			$quiz->description = wp_kses_post( $data['description'] ?? '' );
			$quiz->featured_image_id = absint( $data['featured_image_id'] ?? 0 );
			$quiz->status = sanitize_key( $data['status'] ?? 'draft' );
			$quiz->mode = sanitize_key( $data['mode'] ?? 'tutorial' );
			$quiz->time_limit_seconds = ! empty( $data['time_limit_seconds'] ) ? absint( $data['time_limit_seconds'] ) : null;
			$quiz->pass_percent = floatval( $data['pass_percent'] ?? 70 );
			$quiz->allow_skip = ! empty( $data['allow_skip'] );
			$quiz->allow_backward = ! empty( $data['allow_backward'] );
			$quiz->allow_resume = ! empty( $data['allow_resume'] );
			$quiz->max_attempts = ! empty( $data['max_attempts'] ) ? absint( $data['max_attempts'] ) : null;
			$quiz->attempt_delay_minutes = absint( $data['attempt_delay_minutes'] ?? 0 );
			$quiz->randomize_questions = ! empty( $data['randomize_questions'] );
			$quiz->randomize_answers = ! empty( $data['randomize_answers'] );
			$quiz->page_mode = sanitize_key( $data['page_mode'] ?? 'single' );
			$quiz->questions_per_page = absint( $data['questions_per_page'] ?? 1 );
			$quiz->show_answers = sanitize_key( $data['show_answers'] ?? 'after_submit' );
			$quiz->enable_confidence = ! empty( $data['enable_confidence'] );
			$quiz->theme = sanitize_key( $data['theme'] ?? 'default' );
			$quiz->theme_settings_json = $data['theme_settings_json'] ?? null;
			$quiz->band_feedback_json = $data['band_feedback_json'] ?? null;
			$quiz->generation_mode = sanitize_key( $data['generation_mode'] ?? 'fixed' );

			$result = $quiz->save();

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			$wpdb->query( 'COMMIT' );

			return new WP_REST_Response( [ 'id' => $quiz->id ], 200 );

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'update_failed', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Get quiz items
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_quiz_items( $request ) {
		$quiz_id = absint( $request['quiz_id'] );
		$items = PPQ_Quiz_Item::get_for_quiz( $quiz_id );

		$data = array_map( function( $item ) {
			// Get question details
			$question = PPQ_Question::get( $item->question_id );
			$revision = $question ? $question->get_current_revision() : null;

			$stem = '';
			if ( $revision ) {
				$full_stem = strip_tags( $revision->stem );
				$stem = mb_strlen( $full_stem ) > 60 ? mb_substr( $full_stem, 0, 60 ) . '...' : $full_stem;
			}

			return [
				'id'             => $item->id,
				'question_id'    => $item->question_id,
				'order_index'    => $item->order_index,
				'weight'         => $item->weight,
				'question_stem'  => $stem,
				'question_type'  => $question ? $question->type : '',
			];
		}, $items );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Add quiz items
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function add_quiz_items( $request ) {
		$quiz_id = absint( $request['quiz_id'] );
		$data = $request->get_json_params();
		$question_ids = $data['question_ids'] ?? [];

		if ( empty( $question_ids ) ) {
			return new WP_Error( 'no_questions', __( 'No questions provided.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			$next_order = PPQ_Quiz_Item::get_next_order_index( $quiz_id );

			foreach ( $question_ids as $question_id ) {
				PPQ_Quiz_Item::create( [
					'quiz_id'     => $quiz_id,
					'question_id' => absint( $question_id ),
					'order_index' => $next_order++,
					'weight'      => 1.0,
				] );
			}

			$wpdb->query( 'COMMIT' );

			return new WP_REST_Response( [ 'success' => true ], 200 );

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'add_failed', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Update quiz item
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function update_quiz_item( $request ) {
		$item_id = absint( $request['item_id'] );
		$data = $request->get_json_params();

		$item = PPQ_Quiz_Item::get( $item_id );

		if ( ! $item ) {
			return new WP_Error( 'not_found', __( 'Quiz item not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		// Update weight
		if ( isset( $data['weight'] ) ) {
			$item->weight = floatval( $data['weight'] );
			$item->save();
		}

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * Delete quiz item
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function delete_quiz_item( $request ) {
		$item_id = absint( $request['item_id'] );

		$item = PPQ_Quiz_Item::get( $item_id );

		if ( ! $item ) {
			return new WP_Error( 'not_found', __( 'Quiz item not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		$item->delete();

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * Reorder quiz items
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function reorder_quiz_items( $request ) {
		$data = $request->get_json_params();
		$item_ids = $data['item_ids'] ?? [];

		if ( empty( $item_ids ) ) {
			return new WP_Error( 'no_items', __( 'No items provided.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		$result = PPQ_Quiz_Item::reorder( $item_ids );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'reorder_failed', $result->get_error_message(), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * Get quiz rules
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_quiz_rules( $request ) {
		$quiz_id = absint( $request['quiz_id'] );
		$rules = PPQ_Quiz_Rule::get_for_quiz( $quiz_id );

		$data = array_map( function( $rule ) {
			return [
				'id'             => $rule->id,
				'rule_order'     => $rule->rule_order,
				'bank_id'        => $rule->bank_id,
				'category_ids'   => $rule->get_category_ids(),
				'tag_ids'        => $rule->get_tag_ids(),
				'difficulties'   => $rule->get_difficulties(),
				'question_count' => $rule->question_count,
				'matching_count' => $rule->get_matching_count(),
			];
		}, $rules );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Create quiz rule
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function create_quiz_rule( $request ) {
		$quiz_id = absint( $request['quiz_id'] );
		$data = $request->get_json_params();

		$rule_id = PPQ_Quiz_Rule::create( [
			'quiz_id'        => $quiz_id,
			'bank_id'        => ! empty( $data['bank_id'] ) ? absint( $data['bank_id'] ) : null,
			'category_ids'   => $data['category_ids'] ?? [],
			'tag_ids'        => $data['tag_ids'] ?? [],
			'difficulties'   => $data['difficulties'] ?? [],
			'question_count' => absint( $data['question_count'] ?? 10 ),
		] );

		if ( is_wp_error( $rule_id ) ) {
			return new WP_Error( 'create_failed', $rule_id->get_error_message(), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'id' => $rule_id ], 201 );
	}

	/**
	 * Update quiz rule
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function update_quiz_rule( $request ) {
		$rule_id = absint( $request['rule_id'] );
		$data = $request->get_json_params();

		$rule = PPQ_Quiz_Rule::get( $rule_id );

		if ( ! $rule ) {
			return new WP_Error( 'not_found', __( 'Quiz rule not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		// Update fields
		if ( isset( $data['bank_id'] ) ) {
			$rule->bank_id = ! empty( $data['bank_id'] ) ? absint( $data['bank_id'] ) : null;
		}
		if ( isset( $data['category_ids'] ) ) {
			$rule->category_ids_json = wp_json_encode( array_map( 'absint', $data['category_ids'] ) );
		}
		if ( isset( $data['tag_ids'] ) ) {
			$rule->tag_ids_json = wp_json_encode( array_map( 'absint', $data['tag_ids'] ) );
		}
		if ( isset( $data['difficulties'] ) ) {
			$rule->difficulties_json = wp_json_encode( $data['difficulties'] );
		}
		if ( isset( $data['question_count'] ) ) {
			$rule->question_count = absint( $data['question_count'] );
		}

		$rule->save();

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * Delete quiz rule
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function delete_quiz_rule( $request ) {
		$rule_id = absint( $request['rule_id'] );

		$rule = PPQ_Quiz_Rule::get( $rule_id );

		if ( ! $rule ) {
			return new WP_Error( 'not_found', __( 'Quiz rule not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		$rule->delete();

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * Reorder quiz rules
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function reorder_quiz_rules( $request ) {
		$data = $request->get_json_params();
		$order_map = $data['order_map'] ?? [];

		if ( empty( $order_map ) ) {
			return new WP_Error( 'no_rules', __( 'No rules provided.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		$result = PPQ_Quiz_Rule::reorder( $order_map );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'reorder_failed', $result->get_error_message(), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}
}
