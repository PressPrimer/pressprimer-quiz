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
		// Implementation for listing questions
		return new WP_REST_Response( [], 200 );
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
}
