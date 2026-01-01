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
class PressPrimer_Quiz_REST_Controller {

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
		register_rest_route(
			'ppq/v1',
			'/questions',
			[
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
			]
		);

		register_rest_route(
			'ppq/v1',
			'/questions/(?P<id>\d+)',
			[
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
			]
		);

		// Taxonomies endpoints
		register_rest_route(
			'ppq/v1',
			'/taxonomies',
			[
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
			]
		);

		// Banks endpoints
		register_rest_route(
			'ppq/v1',
			'/banks',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_banks' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_bank' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/banks/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_bank' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_bank' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_bank' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);

		// Quizzes endpoints
		register_rest_route(
			'ppq/v1',
			'/quizzes',
			[
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
			]
		);

		// Published quizzes endpoint for block editor (less restrictive permissions)
		register_rest_route(
			'ppq/v1',
			'/quizzes/published',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_published_quizzes' ],
				'permission_callback' => [ $this, 'check_editor_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/quizzes/(?P<id>\d+)',
			[
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
			]
		);

		// Quiz items endpoints (fixed mode)
		register_rest_route(
			'ppq/v1',
			'/quizzes/(?P<quiz_id>\d+)/items',
			[
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
			]
		);

		register_rest_route(
			'ppq/v1',
			'/quizzes/(?P<quiz_id>\d+)/items/(?P<item_id>\d+)',
			[
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
			]
		);

		register_rest_route(
			'ppq/v1',
			'/quizzes/(?P<quiz_id>\d+)/items/reorder',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reorder_quiz_items' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Quiz rules endpoints (dynamic mode)
		register_rest_route(
			'ppq/v1',
			'/quizzes/(?P<quiz_id>\d+)/rules',
			[
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
			]
		);

		register_rest_route(
			'ppq/v1',
			'/quizzes/(?P<quiz_id>\d+)/rules/(?P<rule_id>\d+)',
			[
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
			]
		);

		register_rest_route(
			'ppq/v1',
			'/quizzes/(?P<quiz_id>\d+)/rules/reorder',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reorder_quiz_rules' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Settings endpoints
		register_rest_route(
			'ppq/v1',
			'/settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'check_settings_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'check_settings_permission' ],
				],
			]
		);

		// API Key endpoints
		register_rest_route(
			'ppq/v1',
			'/settings/api-key',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'save_api_key' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_api_key' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/settings/api-key/validate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'validate_api_key' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/settings/api-models',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_api_models' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/settings/api-model',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_api_model' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Statistics endpoints
		register_rest_route(
			'ppq/v1',
			'/statistics/dashboard',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_dashboard_stats' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/statistics/overview',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_overview_stats' ],
				'permission_callback' => [ $this, 'check_reports_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/statistics/quiz-performance',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_quiz_performance' ],
				'permission_callback' => [ $this, 'check_reports_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/statistics/attempts',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_attempts_stats' ],
				'permission_callback' => [ $this, 'check_reports_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/statistics/attempts/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_attempt_detail' ],
				'permission_callback' => [ $this, 'check_reports_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/statistics/quiz-options',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_quiz_filter_options' ],
				'permission_callback' => [ $this, 'check_reports_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/statistics/activity-chart',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_activity_chart' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// Email endpoints
		register_rest_route(
			'ppq/v1',
			'/email/test',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'send_test_email' ],
				'permission_callback' => [ $this, 'check_settings_permission' ],
			]
		);
	}

	/**
	 * Check permission
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user has permission.
	 */
	public function check_permission() {
		return current_user_can( 'pressprimer_quiz_manage_own' ) || current_user_can( 'pressprimer_quiz_manage_all' );
	}

	/**
	 * Check settings permission
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user has permission.
	 */
	public function check_settings_permission() {
		return current_user_can( 'pressprimer_quiz_manage_settings' );
	}

	/**
	 * Check editor permission for block usage
	 *
	 * Allows users who can edit posts to see the list of published quizzes
	 * for use in blocks.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user has permission.
	 */
	public function check_editor_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check reports permission
	 *
	 * Allows users who can view quiz results to access reports.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user has permission.
	 */
	public function check_reports_permission() {
		return current_user_can( 'pressprimer_quiz_view_results_own' ) || current_user_can( 'pressprimer_quiz_view_results_all' );
	}

	/**
	 * Get owner ID for current user based on permissions
	 *
	 * Returns null if user can see all results, or user ID if limited to own content.
	 *
	 * @since 1.0.0
	 *
	 * @return int|null Owner ID or null for all.
	 */
	private function get_owner_id_for_reports() {
		if ( current_user_can( 'pressprimer_quiz_view_results_all' ) ) {
			return null;
		}
		return get_current_user_id();
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

		$per_page    = absint( $request->get_param( 'per_page' ) ?: 100 );
		$page        = absint( $request->get_param( 'page' ) ?: 1 );
		$offset      = ( $page - 1 ) * $per_page;
		$search      = sanitize_text_field( $request->get_param( 'search' ) ?: '' );
		$status      = sanitize_key( $request->get_param( 'status' ) ?: '' );
		$type        = sanitize_key( $request->get_param( 'type' ) ?: '' );
		$difficulty  = sanitize_key( $request->get_param( 'difficulty' ) ?: '' );
		$category_id = absint( $request->get_param( 'category_id' ) ?: 0 );
		$bank_id     = absint( $request->get_param( 'bank_id' ) ?: 0 );
		$exclude     = $request->get_param( 'exclude' ) ?: '';

		$questions_table      = $wpdb->prefix . 'ppq_questions';
		$revisions_table      = $wpdb->prefix . 'ppq_question_revisions';
		$tax_table            = $wpdb->prefix . 'ppq_question_tax';
		$bank_questions_table = $wpdb->prefix . 'ppq_bank_questions';

		// Build where clauses
		$where        = [ 'q.deleted_at IS NULL' ];
		$where_values = [];

		// Filter by owner for users who can only manage their own content
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			$where[]        = 'q.owner_id = %d';
			$where_values[] = get_current_user_id();
		}

		// Filter by status if provided
		if ( ! empty( $status ) ) {
			$where[]        = 'q.status = %s';
			$where_values[] = $status;
		}

		if ( ! empty( $type ) ) {
			$where[]        = 'q.type = %s';
			$where_values[] = $type;
		}

		if ( ! empty( $difficulty ) ) {
			$where[]        = 'q.difficulty_author = %s';
			$where_values[] = $difficulty;
		}

		// Filter by category
		if ( $category_id > 0 ) {
			$where[]        = "q.id IN (SELECT question_id FROM {$tax_table} WHERE taxonomy = 'category' AND category_id = %d)";
			$where_values[] = $category_id;
		}

		// Filter by bank
		if ( $bank_id > 0 ) {
			$where[]        = "q.id IN (SELECT question_id FROM {$bank_questions_table} WHERE bank_id = %d)";
			$where_values[] = $bank_id;
		}

		// Exclude specific question IDs
		if ( ! empty( $exclude ) ) {
			$exclude_ids = array_filter( array_map( 'absint', explode( ',', $exclude ) ) );
			if ( ! empty( $exclude_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $exclude_ids ), '%d' ) );
				$where[]      = "q.id NOT IN ({$placeholders})";
				$where_values = array_merge( $where_values, $exclude_ids );
			}
		}

		// Search in stem
		if ( ! empty( $search ) ) {
			$where[]        = 'r.stem LIKE %s';
			$where_values[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$where_clause = implode( ' AND ', $where );

		// Get total count
		$count_query = "SELECT COUNT(DISTINCT q.id)
			FROM {$questions_table} q
			LEFT JOIN {$revisions_table} r ON q.current_revision_id = r.id
			WHERE {$where_clause}";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REST API pagination, not suitable for caching
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REST API pagination, not suitable for caching
		$rows = $wpdb->get_results( $wpdb->prepare( $query, $where_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$data = [];
		foreach ( $rows as $row ) {
			$full_stem  = wp_strip_all_tags( $row->stem );
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

		return new WP_REST_Response(
			[
				'questions' => $data,
				'total'     => absint( $total ),
				'page'      => $page,
				'per_page'  => $per_page,
			],
			200
		);
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
		$question    = PressPrimer_Quiz_Question::get( $question_id );

		if ( ! $question ) {
			return new WP_Error( 'not_found', __( 'Question not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		// Check ownership
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $question->author_id ) !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission.', 'pressprimer-quiz' ), [ 'status' => 403 ] );
		}

		$revision   = $question->get_current_revision();
		$categories = $question->get_categories();
		$tags       = $question->get_tags();

		// Get bank memberships
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Question bank membership lookup
		$bank_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT bank_id FROM {$wpdb->prefix}ppq_bank_questions WHERE question_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed from $wpdb->prefix
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
			'categories'        => array_map(
				function ( $cat ) {
					return $cat->id; },
				$categories
			),
			'tags'              => array_map(
				function ( $tag ) {
					return $tag->id; },
				$tags
			),
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

		// Convert answer format from React (isCorrect) to database (is_correct) for validation
		$answers_for_validation = array_map(
			function ( $answer ) {
				return [
					'text'       => $answer['text'] ?? '',
					'is_correct' => $answer['isCorrect'] ?? false,
				];
			},
			$data['answers'] ?? []
		);

		// Validate question content before creating
		$content_validation = PressPrimer_Quiz_Question::validate_content(
			$data['stem'] ?? '',
			$answers_for_validation,
			sanitize_key( $data['type'] ?? 'mc' )
		);

		if ( is_wp_error( $content_validation ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => $content_validation->get_error_message(),
				],
				400
			);
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Create question using static create method
			$question_id = PressPrimer_Quiz_Question::create(
				[
					'uuid'              => wp_generate_uuid4(),
					'type'              => sanitize_key( $data['type'] ),
					'difficulty_author' => sanitize_key( $data['difficulty'] ?? '' ),
					'expected_seconds'  => absint( $data['timeLimit'] ?? 0 ),
					'max_points'        => floatval( $data['points'] ?? 1 ),
					'author_id'         => get_current_user_id(),
					'status'            => 'published',
				]
			);

			if ( is_wp_error( $question_id ) ) {
				throw new Exception( $question_id->get_error_message() );
			}

			// Load the newly created question
			$question = PressPrimer_Quiz_Question::get( $question_id );

			if ( ! $question ) {
				throw new Exception( __( 'Failed to load created question.', 'pressprimer-quiz' ) );
			}

			// Convert answer format from React (isCorrect) to database (is_correct)
			$answers = array_map(
				function ( $answer ) {
					return [
						'id'         => $answer['id'] ?? '',
						'text'       => $answer['text'] ?? '',
						'is_correct' => $answer['isCorrect'] ?? false,
						'feedback'   => $answer['feedback'] ?? '',
						'order'      => $answer['order'] ?? 1,
					];
				},
				$data['answers'] ?? []
			);

			// Create revision
			$revision                     = new PressPrimer_Quiz_Question_Revision();
			$revision->question_id        = $question->id;
			$revision->version            = 1;
			$revision->stem               = wp_kses_post( $data['stem'] ?? '' );
			$revision->answers_json       = wp_json_encode( $answers );
			$revision->settings_json      = wp_json_encode( [] );
			$revision->feedback_correct   = wp_kses_post( $data['feedbackCorrect'] ?? '' );
			$revision->feedback_incorrect = wp_kses_post( $data['feedbackIncorrect'] ?? '' );
			$revision->content_hash       = PressPrimer_Quiz_Question_Revision::generate_hash( $revision->stem, $answers );
			$revision->created_by         = get_current_user_id();

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
					$bank = PressPrimer_Quiz_Bank::get( absint( $bank_id ) );
					if ( $bank ) {
						$bank->add_question( $question->id );
					}
				}
			}

			$wpdb->query( 'COMMIT' );

			// Clear dashboard stats cache for fresh counts
			if ( class_exists( 'PressPrimer_Quiz_Statistics_Service' ) ) {
				PressPrimer_Quiz_Statistics_Service::clear_dashboard_cache();
			}

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
		$data        = $request->get_json_params();

		$question = PressPrimer_Quiz_Question::get( $question_id );

		if ( ! $question ) {
			return new WP_Error( 'not_found', __( 'Question not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		// Check ownership
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $question->author_id ) !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission.', 'pressprimer-quiz' ), [ 'status' => 403 ] );
		}

		// Convert answer format from React (isCorrect) to database (is_correct) for validation
		$answers_for_validation = array_map(
			function ( $answer ) {
				return [
					'text'       => $answer['text'] ?? '',
					'is_correct' => $answer['isCorrect'] ?? false,
				];
			},
			$data['answers'] ?? []
		);

		// Validate question content before updating
		$content_validation = PressPrimer_Quiz_Question::validate_content(
			$data['stem'] ?? '',
			$answers_for_validation,
			sanitize_key( $data['type'] ?? 'mc' )
		);

		if ( is_wp_error( $content_validation ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => $content_validation->get_error_message(),
				],
				400
			);
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Update question metadata
			$question->type              = sanitize_key( $data['type'] ?? '' );
			$question->difficulty_author = sanitize_key( $data['difficulty'] ?? '' );
			$question->expected_seconds  = absint( $data['timeLimit'] ?? 0 );
			$question->max_points        = floatval( $data['points'] ?? 1 );

			$result = $question->save();

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Convert answer format from React (isCorrect) to database (is_correct)
			$answers = array_map(
				function ( $answer ) {
					return [
						'id'         => $answer['id'] ?? '',
						'text'       => $answer['text'] ?? '',
						'is_correct' => $answer['isCorrect'] ?? false,
						'feedback'   => $answer['feedback'] ?? '',
						'order'      => $answer['order'] ?? 1,
					];
				},
				$data['answers'] ?? []
			);

			// Check if content changed (create new revision if it did)
			$current_revision = $question->get_current_revision();
			$new_hash         = PressPrimer_Quiz_Question_Revision::generate_hash( $data['stem'], $answers );

			if ( ! $current_revision || $current_revision->content_hash !== $new_hash ) {
				// Create new revision
				$revision                     = new PressPrimer_Quiz_Question_Revision();
				$revision->question_id        = $question->id;
				$revision->version            = $current_revision ? $current_revision->version + 1 : 1;
				$revision->stem               = wp_kses_post( $data['stem'] ?? '' );
				$revision->answers_json       = wp_json_encode( $answers );
				$revision->settings_json      = wp_json_encode( [] );
				$revision->feedback_correct   = wp_kses_post( $data['feedbackCorrect'] ?? '' );
				$revision->feedback_incorrect = wp_kses_post( $data['feedbackIncorrect'] ?? '' );
				$revision->content_hash       = $new_hash;
				$revision->created_by         = get_current_user_id();

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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bank membership lookup for comparison
			$current_banks = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT bank_id FROM {$wpdb->prefix}ppq_bank_questions WHERE question_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed from $wpdb->prefix
					$question_id
				)
			);

			$new_banks = array_map( 'absint', $data['banks'] ?? [] );
			$to_add    = array_diff( $new_banks, $current_banks );
			$to_remove = array_diff( $current_banks, $new_banks );

			foreach ( $to_add as $bank_id ) {
				$bank = PressPrimer_Quiz_Bank::get( $bank_id );
				if ( $bank ) {
					$bank->add_question( $question->id );
				}
			}

			foreach ( $to_remove as $bank_id ) {
				$bank = PressPrimer_Quiz_Bank::get( $bank_id );
				if ( $bank ) {
					$bank->remove_question( $question->id );
				}
			}

			$wpdb->query( 'COMMIT' );

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

		$items = PressPrimer_Quiz_Category::get_all( $type );

		$data = array_map(
			function ( $item ) {
				return [
					'id'   => $item->id,
					'name' => $item->name,
					'slug' => $item->slug,
				];
			},
			$items
		);

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

		$result = PressPrimer_Quiz_Category::create(
			[
				'name'     => sanitize_text_field( $data['name'] ),
				'taxonomy' => sanitize_key( $data['taxonomy'] ),
			]
		);

		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'create_failed', $result->get_error_message(), [ 'status' => 500 ] );
		}

		$item = PressPrimer_Quiz_Category::get( $result );

		return new WP_REST_Response(
			[
				'id'   => $item->id,
				'name' => $item->name,
				'slug' => $item->slug,
			],
			201
		);
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

		// Admins can see all banks, others see only their own
		if ( current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			$banks = PressPrimer_Quiz_Bank::get_all();
		} else {
			$banks = PressPrimer_Quiz_Bank::get_for_user( $user_id );
		}

		$data = array_map(
			function ( $bank ) {
				return [
					'id'             => $bank->id,
					'uuid'           => $bank->uuid,
					'name'           => $bank->name,
					'description'    => $bank->description,
					'owner_id'       => $bank->owner_id,
					'visibility'     => $bank->visibility,
					'question_count' => $bank->question_count,
					'created_at'     => $bank->created_at,
					'updated_at'     => $bank->updated_at,
				];
			},
			$banks
		);

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get single bank
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_bank( $request ) {
		$bank_id = absint( $request['id'] );
		$bank    = PressPrimer_Quiz_Bank::get( $bank_id );

		if ( ! $bank ) {
			return new WP_Error( 'not_found', __( 'Bank not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		// Check access
		if ( ! $bank->can_access( get_current_user_id() ) ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to access this bank.', 'pressprimer-quiz' ), [ 'status' => 403 ] );
		}

		return new WP_REST_Response(
			[
				'id'             => $bank->id,
				'uuid'           => $bank->uuid,
				'name'           => $bank->name,
				'description'    => $bank->description,
				'owner_id'       => $bank->owner_id,
				'visibility'     => $bank->visibility,
				'question_count' => $bank->question_count,
				'created_at'     => $bank->created_at,
				'updated_at'     => $bank->updated_at,
			],
			200
		);
	}

	/**
	 * Create bank
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function create_bank( $request ) {
		$params = $request->get_json_params();

		// Validate required fields
		if ( empty( $params['name'] ) ) {
			return new WP_Error( 'invalid_data', __( 'Bank name is required.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		// Create bank
		$result = PressPrimer_Quiz_Bank::create(
			[
				'name'        => sanitize_text_field( $params['name'] ),
				'description' => isset( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : null,
				'visibility'  => isset( $params['visibility'] ) && in_array( $params['visibility'], [ 'private', 'shared' ] ) ? $params['visibility'] : 'private',
				'owner_id'    => get_current_user_id(),
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Get the created bank
		$bank = PressPrimer_Quiz_Bank::get( $result );

		if ( ! $bank ) {
			return new WP_Error( 'creation_failed', __( 'Failed to create bank.', 'pressprimer-quiz' ), [ 'status' => 500 ] );
		}

		// Clear dashboard stats cache
		if ( class_exists( 'PressPrimer_Quiz_Statistics_Service' ) ) {
			PressPrimer_Quiz_Statistics_Service::clear_dashboard_cache();
		}

		return new WP_REST_Response(
			[
				'id'             => $bank->id,
				'uuid'           => $bank->uuid,
				'name'           => $bank->name,
				'description'    => $bank->description,
				'owner_id'       => $bank->owner_id,
				'visibility'     => $bank->visibility,
				'question_count' => $bank->question_count,
				'created_at'     => $bank->created_at,
				'updated_at'     => $bank->updated_at,
			],
			201
		);
	}

	/**
	 * Update bank
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function update_bank( $request ) {
		$bank_id = absint( $request['id'] );
		$params  = $request->get_json_params();

		$bank = PressPrimer_Quiz_Bank::get( $bank_id );

		if ( ! $bank ) {
			return new WP_Error( 'not_found', __( 'Bank not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		// Check ownership
		$user_id = get_current_user_id();
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $bank->owner_id ) !== $user_id ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to edit this bank.', 'pressprimer-quiz' ), [ 'status' => 403 ] );
		}

		// Update fields
		if ( isset( $params['name'] ) ) {
			$bank->name = sanitize_text_field( $params['name'] );
		}

		if ( isset( $params['description'] ) ) {
			$bank->description = sanitize_textarea_field( $params['description'] );
		}

		if ( isset( $params['visibility'] ) && in_array( $params['visibility'], [ 'private', 'shared' ] ) ) {
			$bank->visibility = $params['visibility'];
		}

		// Save bank
		$result = $bank->save();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Refresh to get updated data
		$bank->refresh();

		return new WP_REST_Response(
			[
				'id'             => $bank->id,
				'uuid'           => $bank->uuid,
				'name'           => $bank->name,
				'description'    => $bank->description,
				'owner_id'       => $bank->owner_id,
				'visibility'     => $bank->visibility,
				'question_count' => $bank->question_count,
				'created_at'     => $bank->created_at,
				'updated_at'     => $bank->updated_at,
			],
			200
		);
	}

	/**
	 * Delete bank
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function delete_bank( $request ) {
		$bank_id = absint( $request['id'] );
		$bank    = PressPrimer_Quiz_Bank::get( $bank_id );

		if ( ! $bank ) {
			return new WP_Error( 'not_found', __( 'Bank not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		// Check ownership
		$user_id = get_current_user_id();
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $bank->owner_id ) !== $user_id ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to delete this bank.', 'pressprimer-quiz' ), [ 'status' => 403 ] );
		}

		// Delete bank
		$result = $bank->delete();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Clear dashboard stats cache
		if ( class_exists( 'PressPrimer_Quiz_Statistics_Service' ) ) {
			PressPrimer_Quiz_Statistics_Service::clear_dashboard_cache();
		}

		return new WP_REST_Response(
			[
				'deleted' => true,
				'id'      => $bank_id,
			],
			200
		);
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
	 * Get published quizzes for block editor
	 *
	 * Returns a simplified list of published quizzes for use in
	 * block editor dropdowns.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_published_quizzes( $request ) {
		$quizzes = PressPrimer_Quiz_Quiz::find(
			[
				'where'    => [ 'status' => 'published' ],
				'order_by' => 'title',
				'order'    => 'ASC',
				'limit'    => 100,
			]
		);

		$items = [];

		foreach ( $quizzes as $quiz ) {
			// Get question count based on generation mode
			$question_count = 0;
			if ( 'fixed' === $quiz->generation_mode ) {
				// For fixed quizzes, count the items
				$quiz_items     = $quiz->get_items();
				$question_count = count( $quiz_items );
			} else {
				// For dynamic quizzes, sum question_count from rules
				$rules = $quiz->get_rules();
				foreach ( $rules as $rule ) {
					$question_count += absint( $rule->question_count );
				}
			}

			// Calculate time limit in minutes
			$time_limit_minutes = $quiz->time_limit_seconds ? round( $quiz->time_limit_seconds / 60 ) : 0;

			$items[] = [
				'id'                 => $quiz->id,
				'title'              => $quiz->title,
				'description'        => wp_strip_all_tags( $quiz->description ),
				'question_count'     => $question_count,
				'time_limit_minutes' => $time_limit_minutes,
				'passing_score'      => $quiz->pass_percent,
			];
		}

		return new WP_REST_Response( $items, 200 );
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
		$quiz    = PressPrimer_Quiz_Quiz::get( $quiz_id );

		if ( ! $quiz ) {
			return new WP_Error( 'not_found', __( 'Quiz not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		// Check ownership
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission.', 'pressprimer-quiz' ), [ 'status' => 403 ] );
		}

		$data = [
			'id'                    => $quiz->id,
			'title'                 => $quiz->title,
			'description'           => $quiz->description,
			'featured_image_id'     => $quiz->featured_image_id,
			'status'                => $quiz->status,
			'mode'                  => $quiz->mode,
			'time_limit_seconds'    => $quiz->time_limit_seconds,
			'pass_percent'          => $quiz->pass_percent,
			'allow_skip'            => $quiz->allow_skip,
			'allow_backward'        => $quiz->allow_backward,
			'allow_resume'          => $quiz->allow_resume,
			'max_attempts'          => $quiz->max_attempts,
			'attempt_delay_minutes' => $quiz->attempt_delay_minutes,
			'randomize_questions'   => $quiz->randomize_questions,
			'randomize_answers'     => $quiz->randomize_answers,
			'page_mode'             => $quiz->page_mode,
			'questions_per_page'    => $quiz->questions_per_page,
			'show_answers'          => $quiz->show_answers,
			'enable_confidence'     => $quiz->enable_confidence,
			'theme'                 => $quiz->theme,
			'theme_settings_json'   => $quiz->theme_settings_json,
			'band_feedback_json'    => $quiz->band_feedback_json,
			'generation_mode'       => $quiz->generation_mode,
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
			$quiz_id = PressPrimer_Quiz_Quiz::create(
				[
					'uuid'                  => wp_generate_uuid4(),
					'title'                 => sanitize_text_field( $data['title'] ),
					'description'           => wp_kses_post( $data['description'] ?? '' ),
					'featured_image_id'     => absint( $data['featured_image_id'] ?? 0 ),
					'owner_id'              => get_current_user_id(),
					'status'                => sanitize_key( $data['status'] ?? 'draft' ),
					'mode'                  => sanitize_key( $data['mode'] ?? 'tutorial' ),
					'time_limit_seconds'    => ! empty( $data['time_limit_seconds'] ) ? absint( $data['time_limit_seconds'] ) : null,
					'pass_percent'          => floatval( $data['pass_percent'] ?? 70 ),
					'allow_skip'            => ! empty( $data['allow_skip'] ),
					'allow_backward'        => ! empty( $data['allow_backward'] ),
					'allow_resume'          => ! empty( $data['allow_resume'] ),
					'max_attempts'          => ! empty( $data['max_attempts'] ) ? absint( $data['max_attempts'] ) : null,
					'attempt_delay_minutes' => absint( $data['attempt_delay_minutes'] ?? 0 ),
					'randomize_questions'   => ! empty( $data['randomize_questions'] ),
					'randomize_answers'     => ! empty( $data['randomize_answers'] ),
					'page_mode'             => sanitize_key( $data['page_mode'] ?? 'single' ),
					'questions_per_page'    => absint( $data['questions_per_page'] ?? 1 ),
					'show_answers'          => sanitize_key( $data['show_answers'] ?? 'after_submit' ),
					'enable_confidence'     => ! empty( $data['enable_confidence'] ),
					'theme'                 => sanitize_key( $data['theme'] ?? 'default' ),
					'theme_settings_json'   => $data['theme_settings_json'] ?? null,
					'band_feedback_json'    => $data['band_feedback_json'] ?? null,
					'generation_mode'       => sanitize_key( $data['generation_mode'] ?? 'fixed' ),
				]
			);

			if ( is_wp_error( $quiz_id ) ) {
				throw new Exception( $quiz_id->get_error_message() );
			}

			$wpdb->query( 'COMMIT' );

			// Clear dashboard stats cache
			if ( class_exists( 'PressPrimer_Quiz_Statistics_Service' ) ) {
				PressPrimer_Quiz_Statistics_Service::clear_dashboard_cache();
			}

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
		$data    = $request->get_json_params();

		$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );

		if ( ! $quiz ) {
			return new WP_Error( 'not_found', __( 'Quiz not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		// Check ownership
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission.', 'pressprimer-quiz' ), [ 'status' => 403 ] );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Update quiz fields
			$quiz->title                 = sanitize_text_field( $data['title'] );
			$quiz->description           = wp_kses_post( $data['description'] ?? '' );
			$quiz->featured_image_id     = absint( $data['featured_image_id'] ?? 0 );
			$quiz->status                = sanitize_key( $data['status'] ?? 'draft' );
			$quiz->mode                  = sanitize_key( $data['mode'] ?? 'tutorial' );
			$quiz->time_limit_seconds    = ! empty( $data['time_limit_seconds'] ) ? absint( $data['time_limit_seconds'] ) : null;
			$quiz->pass_percent          = floatval( $data['pass_percent'] ?? 70 );
			$quiz->allow_skip            = ! empty( $data['allow_skip'] );
			$quiz->allow_backward        = ! empty( $data['allow_backward'] );
			$quiz->allow_resume          = ! empty( $data['allow_resume'] );
			$quiz->max_attempts          = ! empty( $data['max_attempts'] ) ? absint( $data['max_attempts'] ) : null;
			$quiz->attempt_delay_minutes = absint( $data['attempt_delay_minutes'] ?? 0 );
			$quiz->randomize_questions   = ! empty( $data['randomize_questions'] );
			$quiz->randomize_answers     = ! empty( $data['randomize_answers'] );
			$quiz->page_mode             = sanitize_key( $data['page_mode'] ?? 'single' );
			$quiz->questions_per_page    = absint( $data['questions_per_page'] ?? 1 );
			$quiz->show_answers          = sanitize_key( $data['show_answers'] ?? 'after_submit' );
			$quiz->enable_confidence     = ! empty( $data['enable_confidence'] );
			$quiz->theme                 = sanitize_key( $data['theme'] ?? 'default' );
			$quiz->theme_settings_json   = $data['theme_settings_json'] ?? null;
			$quiz->band_feedback_json    = $data['band_feedback_json'] ?? null;
			$quiz->generation_mode       = sanitize_key( $data['generation_mode'] ?? 'fixed' );

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
		$items   = PressPrimer_Quiz_Quiz_Item::get_for_quiz( $quiz_id );

		$data = array_map(
			function ( $item ) {
				// Get question details
				$question = PressPrimer_Quiz_Question::get( $item->question_id );
				$revision = $question ? $question->get_current_revision() : null;

				$stem = '';
				if ( $revision ) {
						$full_stem = wp_strip_all_tags( $revision->stem );
						$stem      = mb_strlen( $full_stem ) > 60 ? mb_substr( $full_stem, 0, 60 ) . '...' : $full_stem;
				}

				return [
					'id'            => $item->id,
					'question_id'   => $item->question_id,
					'order_index'   => $item->order_index,
					'weight'        => $item->weight,
					'question_stem' => $stem,
					'question_type' => $question ? $question->type : '',
				];
			},
			$items
		);

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
		$quiz_id      = absint( $request['quiz_id'] );
		$data         = $request->get_json_params();
		$question_ids = $data['question_ids'] ?? [];

		if ( empty( $question_ids ) ) {
			return new WP_Error( 'no_questions', __( 'No questions provided.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			$next_order = PressPrimer_Quiz_Quiz_Item::get_next_order_index( $quiz_id );

			foreach ( $question_ids as $question_id ) {
				PressPrimer_Quiz_Quiz_Item::create(
					[
						'quiz_id'     => $quiz_id,
						'question_id' => absint( $question_id ),
						'order_index' => $next_order++,
						'weight'      => 1.0,
					]
				);
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
		$data    = $request->get_json_params();

		$item = PressPrimer_Quiz_Quiz_Item::get( $item_id );

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

		$item = PressPrimer_Quiz_Quiz_Item::get( $item_id );

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
		$data     = $request->get_json_params();
		$item_ids = $data['item_ids'] ?? [];

		if ( empty( $item_ids ) ) {
			return new WP_Error( 'no_items', __( 'No items provided.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		$result = PressPrimer_Quiz_Quiz_Item::reorder( $item_ids );

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
		$rules   = PressPrimer_Quiz_Quiz_Rule::get_for_quiz( $quiz_id );

		$data = array_map(
			function ( $rule ) {
				$matching_count = $rule->get_matching_count();

				return [
					'id'             => $rule->id,
					'rule_order'     => $rule->rule_order,
					'bank_id'        => $rule->bank_id,
					'category_ids'   => $rule->get_category_ids(),
					'tag_ids'        => $rule->get_tag_ids(),
					'difficulties'   => $rule->get_difficulties(),
					'question_count' => $rule->question_count,
					'matching_count' => $matching_count,
				];
			},
			$rules
		);

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
		$data    = $request->get_json_params();

		$rule_id = PressPrimer_Quiz_Quiz_Rule::create(
			[
				'quiz_id'        => $quiz_id,
				'bank_id'        => ! empty( $data['bank_id'] ) ? absint( $data['bank_id'] ) : null,
				'category_ids'   => $data['category_ids'] ?? [],
				'tag_ids'        => $data['tag_ids'] ?? [],
				'difficulties'   => $data['difficulties'] ?? [],
				'question_count' => absint( $data['question_count'] ?? 10 ),
			]
		);

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
		$data    = $request->get_json_params();

		$rule = PressPrimer_Quiz_Quiz_Rule::get( $rule_id );

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

		$rule = PressPrimer_Quiz_Quiz_Rule::get( $rule_id );

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
		$data      = $request->get_json_params();
		$order_map = $data['order_map'] ?? [];

		if ( empty( $order_map ) ) {
			return new WP_Error( 'no_rules', __( 'No rules provided.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		$result = PressPrimer_Quiz_Quiz_Rule::reorder( $order_map );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'reorder_failed', $result->get_error_message(), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * Get settings
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_settings( $request ) {
		$settings = get_option( PressPrimer_Quiz_Admin_Settings::OPTION_NAME, [] );

		return new WP_REST_Response(
			[
				'success'  => true,
				'settings' => $settings,
			],
			200
		);
	}

	/**
	 * Update settings
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function update_settings( $request ) {
		$data = $request->get_json_params();

		// Get existing settings
		$existing = get_option( PressPrimer_Quiz_Admin_Settings::OPTION_NAME, [] );

		// Sanitize incoming settings
		$sanitized = [];

		// General settings
		if ( isset( $data['default_passing_score'] ) ) {
			$sanitized['default_passing_score'] = min( 100, max( 0, absint( $data['default_passing_score'] ) ) );
		}

		if ( isset( $data['default_quiz_mode'] ) ) {
			$sanitized['default_quiz_mode'] = in_array( $data['default_quiz_mode'], [ 'tutorial', 'timed' ], true )
				? $data['default_quiz_mode']
				: 'tutorial';
		}

		// Email settings
		if ( isset( $data['email_from_name'] ) ) {
			$sanitized['email_from_name'] = sanitize_text_field( $data['email_from_name'] );
		}

		if ( isset( $data['email_from_email'] ) ) {
			$sanitized['email_from_email'] = sanitize_email( $data['email_from_email'] );
		}

		if ( isset( $data['email_results_auto_send'] ) ) {
			$sanitized['email_results_auto_send'] = (bool) $data['email_results_auto_send'];
		}

		if ( isset( $data['email_results_subject'] ) ) {
			$sanitized['email_results_subject'] = sanitize_text_field( $data['email_results_subject'] );
		}

		if ( isset( $data['email_results_body'] ) ) {
			$sanitized['email_results_body'] = wp_kses_post( $data['email_results_body'] );
		}

		if ( isset( $data['email_logo_url'] ) ) {
			$sanitized['email_logo_url'] = esc_url_raw( $data['email_logo_url'] );
		}

		if ( isset( $data['email_logo_id'] ) ) {
			$sanitized['email_logo_id'] = absint( $data['email_logo_id'] );
		}

		// Sharing settings
		if ( isset( $data['social_sharing_twitter'] ) ) {
			$sanitized['social_sharing_twitter'] = (bool) $data['social_sharing_twitter'];
		}

		if ( isset( $data['social_sharing_facebook'] ) ) {
			$sanitized['social_sharing_facebook'] = (bool) $data['social_sharing_facebook'];
		}

		if ( isset( $data['social_sharing_linkedin'] ) ) {
			$sanitized['social_sharing_linkedin'] = (bool) $data['social_sharing_linkedin'];
		}

		if ( isset( $data['social_sharing_include_score'] ) ) {
			$sanitized['social_sharing_include_score'] = (bool) $data['social_sharing_include_score'];
		}

		if ( isset( $data['social_sharing_message'] ) ) {
			$sanitized['social_sharing_message'] = sanitize_text_field( $data['social_sharing_message'] );
		}

		// Advanced settings - CRITICAL: This setting controls data deletion on uninstall
		// Always include this in sanitized to ensure it gets saved
		$sanitized['remove_data_on_uninstall'] = false; // Default to false
		if ( isset( $data['remove_data_on_uninstall'] ) ) {
			$value = $data['remove_data_on_uninstall'];
			// Only true if explicitly true, '1', 1, or 'true' - everything else is false
			if ( true === $value || 'true' === $value || '1' === $value || 1 === $value ) {
				$sanitized['remove_data_on_uninstall'] = true;
			}
			// If value is false/falsy, keep the default false we set above
		}

		// Appearance settings
		if ( isset( $data['appearance_font_family'] ) ) {
			$sanitized['appearance_font_family'] = sanitize_text_field( $data['appearance_font_family'] );
		}

		if ( isset( $data['appearance_font_size'] ) ) {
			$sanitized['appearance_font_size'] = sanitize_text_field( $data['appearance_font_size'] );
		}

		if ( isset( $data['appearance_primary_color'] ) ) {
			$color                                 = $data['appearance_primary_color'];
			$sanitized['appearance_primary_color'] = ! empty( $color ) ? sanitize_hex_color( $color ) : '';
		}

		if ( isset( $data['appearance_text_color'] ) ) {
			$color                              = $data['appearance_text_color'];
			$sanitized['appearance_text_color'] = ! empty( $color ) ? sanitize_hex_color( $color ) : '';
		}

		if ( isset( $data['appearance_background_color'] ) ) {
			$color                                    = $data['appearance_background_color'];
			$sanitized['appearance_background_color'] = ! empty( $color ) ? sanitize_hex_color( $color ) : '';
		}

		if ( isset( $data['appearance_success_color'] ) ) {
			$color                                 = $data['appearance_success_color'];
			$sanitized['appearance_success_color'] = ! empty( $color ) ? sanitize_hex_color( $color ) : '';
		}

		if ( isset( $data['appearance_error_color'] ) ) {
			$color                               = $data['appearance_error_color'];
			$sanitized['appearance_error_color'] = ! empty( $color ) ? sanitize_hex_color( $color ) : '';
		}

		if ( isset( $data['appearance_border_radius'] ) ) {
			$radius                                = $data['appearance_border_radius'];
			$sanitized['appearance_border_radius'] = ( '' !== $radius && null !== $radius ) ? absint( $radius ) : '';
		}

		// Merge with existing settings
		$merged = array_merge( $existing, $sanitized );

		// Save settings
		update_option( PressPrimer_Quiz_Admin_Settings::OPTION_NAME, $merged );

		// Clear object cache to ensure fresh data on next request
		// This prevents issues with persistent object caching plugins (Redis, Memcached, etc.)
		wp_cache_delete( PressPrimer_Quiz_Admin_Settings::OPTION_NAME, 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Settings saved successfully.', 'pressprimer-quiz' ),
			],
			200
		);
	}

	/**
	 * Save API key
	 *
	 * Saves the site-wide OpenAI API key. This is stored as an encrypted option
	 * and is used by all users with AI generation permissions.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function save_api_key( $request ) {
		$data    = $request->get_json_params();
		$api_key = sanitize_text_field( $data['api_key'] ?? '' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'invalid_key', __( 'Please provide an API key.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		if ( strpos( $api_key, 'sk-' ) !== 0 ) {
			return new WP_Error( 'invalid_key', __( 'Invalid API key format.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		// Validate the key with OpenAI before saving
		$validation = PressPrimer_Quiz_AI_Service::validate_api_key( $api_key );

		if ( is_wp_error( $validation ) ) {
			return new WP_Error( 'invalid_key', $validation->get_error_message(), [ 'status' => 400 ] );
		}

		// Encrypt and save the site-wide key
		$encrypted = PressPrimer_Quiz_Helpers::encrypt( $api_key );

		if ( is_wp_error( $encrypted ) ) {
			return new WP_Error( 'encryption_failed', $encrypted->get_error_message(), [ 'status' => 500 ] );
		}

		update_option( 'pressprimer_quiz_site_openai_api_key', $encrypted );

		// Get status for response
		$status = PressPrimer_Quiz_AI_Service::get_api_key_status();

		return new WP_REST_Response(
			[
				'success'    => true,
				'message'    => __( 'API key saved and validated successfully.', 'pressprimer-quiz' ),
				'masked_key' => $status['masked_key'] ?? 'sk-****',
			],
			200
		);
	}

	/**
	 * Delete API key
	 *
	 * Removes the site-wide OpenAI API key.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function delete_api_key( $request ) {
		// Delete the site-wide API key
		delete_option( 'pressprimer_quiz_site_openai_api_key' );

		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'API key removed successfully.', 'pressprimer-quiz' ),
			],
			200
		);
	}

	/**
	 * Validate API key
	 *
	 * Validates the currently configured site-wide API key.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function validate_api_key( $request ) {
		// Get the site-wide API key (get_api_key checks site option first)
		$api_key = PressPrimer_Quiz_AI_Service::get_api_key();

		if ( is_wp_error( $api_key ) || empty( $api_key ) ) {
			return new WP_Error( 'no_key', __( 'No API key configured.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		// Validate by trying to fetch models
		$models = PressPrimer_Quiz_AI_Service::get_available_models( $api_key );

		if ( is_wp_error( $models ) ) {
			return new WP_Error( 'invalid_key', $models->get_error_message(), [ 'status' => 400 ] );
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'API key is valid.', 'pressprimer-quiz' ),
			],
			200
		);
	}

	/**
	 * Get API models
	 *
	 * Returns available OpenAI models for the configured site-wide API key.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_api_models( $request ) {
		// Get the site-wide API key
		$api_key = PressPrimer_Quiz_AI_Service::get_api_key();

		if ( is_wp_error( $api_key ) || empty( $api_key ) ) {
			return new WP_Error( 'no_key', __( 'No API key configured.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		// Fetch models
		$models = PressPrimer_Quiz_AI_Service::get_available_models( $api_key );

		if ( is_wp_error( $models ) ) {
			return new WP_Error( 'fetch_failed', $models->get_error_message(), [ 'status' => 500 ] );
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'models'  => $models,
			],
			200
		);
	}

	/**
	 * Save API model preference
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function save_api_model( $request ) {
		$data    = $request->get_json_params();
		$model   = sanitize_text_field( $data['model'] ?? '' );
		$user_id = get_current_user_id();

		// Save model preference
		PressPrimer_Quiz_AI_Service::save_model_preference( $user_id, $model );

		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Model preference saved.', 'pressprimer-quiz' ),
			],
			200
		);
	}

	/**
	 * Get dashboard statistics
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_dashboard_stats( $request ) {
		try {
			$service = new PressPrimer_Quiz_Statistics_Service();

			// Determine owner restriction based on permissions
			$owner_id = null;
			if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
				$owner_id = get_current_user_id();
			}

			$stats = $service->get_dashboard_stats( $owner_id );

			return new WP_REST_Response(
				[
					'success' => true,
					'data'    => $stats,
				],
				200
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'dashboard_error',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Get overview statistics for reports
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_overview_stats( $request ) {
		$service = new PressPrimer_Quiz_Statistics_Service();

		$args = [
			'date_from' => $request->get_param( 'date_from' ),
			'date_to'   => $request->get_param( 'date_to' ),
			'owner_id'  => $this->get_owner_id_for_reports(),
		];

		$stats = $service->get_overview_stats( $args );

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $stats,
			],
			200
		);
	}

	/**
	 * Get quiz performance statistics
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_quiz_performance( $request ) {
		$service = new PressPrimer_Quiz_Statistics_Service();

		$args = [
			'date_from' => $request->get_param( 'date_from' ),
			'date_to'   => $request->get_param( 'date_to' ),
			'search'    => $request->get_param( 'search' ) ?? '',
			'orderby'   => $request->get_param( 'orderby' ) ?? 'attempts',
			'order'     => $request->get_param( 'order' ) ?? 'DESC',
			'per_page'  => $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 20,
			'page'      => $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1,
			'owner_id'  => $this->get_owner_id_for_reports(),
		];

		$data = $service->get_quiz_performance( $args );

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			200
		);
	}

	/**
	 * Get recent attempts statistics
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_attempts_stats( $request ) {
		$service = new PressPrimer_Quiz_Statistics_Service();

		$args = [
			'quiz_id'   => $request->get_param( 'quiz_id' ) ? absint( $request->get_param( 'quiz_id' ) ) : null,
			'passed'    => $request->get_param( 'passed' ) !== null ? absint( $request->get_param( 'passed' ) ) : null,
			'date_from' => $request->get_param( 'date_from' ),
			'date_to'   => $request->get_param( 'date_to' ),
			'search'    => $request->get_param( 'search' ) ?? '',
			'orderby'   => $request->get_param( 'orderby' ) ?? 'finished_at',
			'order'     => $request->get_param( 'order' ) ?? 'DESC',
			'per_page'  => $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 20,
			'page'      => $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1,
			'owner_id'  => $this->get_owner_id_for_reports(),
		];

		$data = $service->get_recent_attempts( $args );

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			200
		);
	}

	/**
	 * Get attempt detail
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_attempt_detail( $request ) {
		$attempt_id = absint( $request['id'] );
		$service    = new PressPrimer_Quiz_Statistics_Service();

		$data = $service->get_attempt_detail( $attempt_id, $this->get_owner_id_for_reports() );

		if ( ! $data ) {
			return new WP_Error( 'not_found', __( 'Attempt not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			200
		);
	}

	/**
	 * Get quiz filter options
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_quiz_filter_options( $request ) {
		$service = new PressPrimer_Quiz_Statistics_Service();

		$quizzes = $service->get_quiz_filter_options( $this->get_owner_id_for_reports() );

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $quizzes,
			],
			200
		);
	}

	/**
	 * Get activity chart data
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_activity_chart( $request ) {
		$service = new PressPrimer_Quiz_Statistics_Service();

		// Determine owner restriction based on permissions
		$owner_id = null;
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			$owner_id = get_current_user_id();
		}

		$args = [
			'days'     => $request->get_param( 'days' ) ? absint( $request->get_param( 'days' ) ) : 90,
			'owner_id' => $owner_id,
		];

		$data = $service->get_activity_chart_data( $args );

		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			200
		);
	}

	/**
	 * Send test email
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function send_test_email( $request ) {
		$data  = $request->get_json_params();
		$email = sanitize_email( $data['email'] ?? '' );

		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please provide a valid email address.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		// Send test email
		$sent = PressPrimer_Quiz_Email_Service::send_test_email( $email );

		if ( $sent ) {
			return new WP_REST_Response(
				[
					'success' => true,
					'message' => __( 'Test email sent successfully.', 'pressprimer-quiz' ),
				],
				200
			);
		} else {
			return new WP_Error( 'send_failed', __( 'Failed to send test email. Please check your email configuration.', 'pressprimer-quiz' ), [ 'status' => 500 ] );
		}
	}
}
