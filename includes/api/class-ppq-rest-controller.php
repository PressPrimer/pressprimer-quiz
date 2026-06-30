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

		// Question image upload endpoint (v2.3 image support feature).
		register_rest_route(
			'ppq/v1',
			'/questions/(?P<id>\d+)/upload-image',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'upload_question_image' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);

		// Decoupled upload endpoint (since 2.3.0). Uploads an image to the
		// media library without binding it to a specific question — ownership
		// is registered at question save time via register_image_ownership.
		// Lets the Question Editor's image button work on brand-new questions
		// (where the question ID doesn't exist yet) without an auto-draft
		// hack. The older `/questions/{id}/upload-image` is preserved for any
		// integrator that depended on the upload-time ownership tagging.
		register_rest_route(
			'ppq/v1',
			'/upload-image',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'upload_image' ],
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
					'args'                => [
						'include_review_quizzes' => [
							'type'              => 'boolean',
							'required'          => false,
							'sanitize_callback' => 'rest_sanitize_boolean',
						],
					],
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
					'permission_callback' => [ $this, 'check_settings_permission' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_api_key' ],
					'permission_callback' => [ $this, 'check_settings_permission' ],
				],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/settings/api-key/validate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'validate_api_key' ],
				'permission_callback' => [ $this, 'check_settings_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/settings/api-key/clear',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'delete_api_key' ],
				'permission_callback' => [ $this, 'check_settings_permission' ],
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
				'permission_callback' => [ $this, 'check_settings_permission' ],
			]
		);

		// Active AI provider (site-level). Settings-capable users only.
		register_rest_route(
			'ppq/v1',
			'/settings/ai-provider',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'set_ai_provider' ],
				'permission_callback' => [ $this, 'check_settings_permission' ],
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

		// Schema integrity endpoints (v3.0). Power the Status tab's schema
		// health section: run a presence-only check and repair missing
		// tables/columns from the canonical schema map.
		register_rest_route(
			'ppq/v1',
			'/status/schema',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_schema_status' ],
				'permission_callback' => [ $this, 'check_settings_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/status/schema/repair',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'repair_schema_status' ],
				'permission_callback' => [ $this, 'check_settings_permission' ],
			]
		);

		// Front-end dashboard page helper (v3.0). Creates a published page
		// containing the dashboard block and designates it.
		register_rest_route(
			'ppq/v1',
			'/dashboard-page',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_dashboard_page' ],
				'permission_callback' => [ $this, 'check_settings_permission' ],
			]
		);

		// Current user's own quiz attempts (v3.0 shell My Results).
		register_rest_route(
			'ppq/v1',
			'/my-attempts',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_my_attempts' ],
				'permission_callback' => [ $this, 'check_own_attempts_permission' ],
			]
		);

		// Quiz settings templates (feature 003). Listing is open to any
		// quiz-editing user (they can apply templates); mutations require the
		// settings capability since templates are a site-wide policy object.
		register_rest_route(
			'ppq/v1',
			'/quiz-templates',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_quiz_templates' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_quiz_template' ],
					'permission_callback' => [ $this, 'check_settings_permission' ],
				],
			]
		);

		// Default-template selector target. Registered before the numeric-id route;
		// 'default' never matches the \d+ pattern, but keeping it first is explicit.
		register_rest_route(
			'ppq/v1',
			'/quiz-templates/default',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_default_quiz_template' ],
					'permission_callback' => [ $this, 'check_settings_permission' ],
				],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/quiz-templates/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_quiz_template' ],
					'permission_callback' => [ $this, 'check_settings_permission' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_quiz_template' ],
					'permission_callback' => [ $this, 'check_settings_permission' ],
				],
			]
		);

		// Progress reset tools (Data Tools).
		register_rest_route(
			'ppq/v1',
			'/tools/reset-progress/preview',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'preview_reset_progress' ],
					'permission_callback' => [ $this, 'check_reset_progress_permission' ],
					'args'                => [
						'user_id' => [
							'type'              => 'integer',
							'required'          => false,
							'sanitize_callback' => 'absint',
						],
						'quiz_id' => [
							'type'              => 'integer',
							'required'          => false,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/tools/reset-progress',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'reset_progress' ],
					'permission_callback' => [ $this, 'check_reset_progress_permission' ],
					'args'                => [
						'user_id'       => [
							'type'              => 'integer',
							'required'          => false,
							'sanitize_callback' => 'absint',
						],
						'quiz_id'       => [
							'type'              => 'integer',
							'required'          => false,
							'sanitize_callback' => 'absint',
						],
						'confirm_token' => [
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'cursor'        => [
							'type'              => 'integer',
							'required'          => false,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/tools/reset-progress/log',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_reset_log' ],
					'permission_callback' => [ $this, 'check_reset_progress_permission' ],
				],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/tools/users',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'search_reset_users' ],
					'permission_callback' => [ $this, 'check_reset_progress_permission' ],
					'args'                => [
						'search' => [
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);
	}

	/**
	 * Permission for the current user's own attempts.
	 *
	 * @since 3.0.0
	 *
	 * @return bool True if the user may view their own attempts.
	 */
	public function check_own_attempts_permission() {
		return current_user_can( 'pressprimer_quiz_take_quiz' )
			|| current_user_can( 'pressprimer_quiz_view_results_own' );
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
	 * Permission for the progress reset tools.
	 *
	 * Deliberately stricter than the settings gate: resetting progress is a
	 * destructive, site-wide operation, so it requires BOTH full management
	 * and settings capabilities. Teachers with only manage_own do not qualify.
	 *
	 * @since 3.0.0
	 *
	 * @return bool True if the user may preview or run a progress reset.
	 */
	public function check_reset_progress_permission() {
		return current_user_can( 'pressprimer_quiz_manage_all' )
			&& current_user_can( 'pressprimer_quiz_manage_settings' );
	}

	/**
	 * Load a quiz by ID and verify the current user owns it.
	 *
	 * Centralizes the ownership pattern so every per-quiz handler shares
	 * the same check. Admins with pressprimer_quiz_manage_all bypass the
	 * owner test. Returns 404 when the quiz does not exist and 403 when
	 * the requester does not own it.
	 *
	 * @since 2.3.1
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return PressPrimer_Quiz_Quiz|WP_Error Quiz on success, error otherwise.
	 */
	private function get_owned_quiz_or_error( $quiz_id ) {
		$quiz = PressPrimer_Quiz_Quiz::get( absint( $quiz_id ) );
		if ( ! $quiz ) {
			return new WP_Error( 'not_found', __( 'Quiz not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' )
			&& absint( $quiz->owner_id ) !== get_current_user_id() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission.', 'pressprimer-quiz' ), [ 'status' => 403 ] );
		}
		return $quiz;
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
	 * GET /tools/reset-progress/preview — read-only preview of a reset scope.
	 *
	 * Resolves the requested scope (user, quiz, or user + quiz) and returns the
	 * counts and labels the Data Tools UI shows before enabling deletion. No
	 * data is modified.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Preview payload or error.
	 */
	public function preview_reset_progress( $request ) {
		if ( ! class_exists( 'PressPrimer_Quiz_Progress_Reset_Service' ) ) {
			return new WP_Error(
				'ppq_reset_unavailable',
				__( 'Reset tools are not available.', 'pressprimer-quiz' ),
				[ 'status' => 500 ]
			);
		}

		$service = new PressPrimer_Quiz_Progress_Reset_Service();

		$scope = $service->sanitize_scope(
			$request->get_param( 'user_id' ),
			$request->get_param( 'quiz_id' )
		);

		if ( is_wp_error( $scope ) ) {
			return $scope;
		}

		return rest_ensure_response( $service->get_preview( $scope ) );
	}

	/**
	 * POST /tools/reset-progress — delete one batch of in-scope attempts.
	 *
	 * Validates the typed confirmation token server-side, enforces the
	 * site-wide single-operation lock, then deletes one batch and reports the
	 * remaining count and cursor. The client re-POSTs (with the returned cursor)
	 * until remaining reaches zero. The lock is released when the operation
	 * completes (feature 006, FR-003, FR-004).
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Batch result or error.
	 */
	public function reset_progress( $request ) {
		if ( ! class_exists( 'PressPrimer_Quiz_Progress_Reset_Service' ) ) {
			return new WP_Error(
				'ppq_reset_unavailable',
				__( 'Reset tools are not available.', 'pressprimer-quiz' ),
				[ 'status' => 500 ]
			);
		}

		$service = new PressPrimer_Quiz_Progress_Reset_Service();

		$scope = $service->sanitize_scope(
			$request->get_param( 'user_id' ),
			$request->get_param( 'quiz_id' )
		);

		if ( is_wp_error( $scope ) ) {
			return $scope;
		}

		// Stop with an accurate error if the quiz was deleted (including
		// between batches) so we never report a misleading token mismatch.
		$targets = $service->verify_scope_targets( $scope );
		if ( is_wp_error( $targets ) ) {
			return $targets;
		}

		// Validate the confirmation token before touching the lock or any data.
		if ( ! $service->verify_token( $scope, $request->get_param( 'confirm_token' ) ) ) {
			return new WP_Error(
				'ppq_reset_bad_token',
				__( 'The confirmation text did not match. Nothing was deleted.', 'pressprimer-quiz' ),
				[ 'status' => 400 ]
			);
		}

		// Enforce the site-wide single-operation lock (409 if another runs).
		$lock = $service->guard_lock( $scope );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}

		$result = $service->delete_batch( $scope, absint( $request->get_param( 'cursor' ) ) );

		if ( is_wp_error( $result ) ) {
			// Leave the lock in place; it expires on inactivity so a retry of
			// this same operation can resume without being blocked.
			return $result;
		}

		// Accumulate this batch into the operation's running totals.
		$totals           = $service->record_batch( $scope, $result['deleted'], $result['items'] );
		$result['totals'] = $totals;

		if ( 0 === (int) $result['remaining'] ) {
			if ( $totals['attempts'] > 0 ) {
				// Real operation finished: fire the completion hook, log it,
				// and release the lock.
				$service->complete_operation( $scope, $totals );
			} else {
				// Nothing matched the scope; just release the lock.
				$service->release_lock();
			}
		}

		return rest_ensure_response( $result );
	}

	/**
	 * GET /tools/reset-progress/log — the recent reset operation log.
	 *
	 * Enriches each stored entry with the initiator's display name (or null
	 * when that user no longer exists) for the Data Tools tab.
	 *
	 * @since 3.0.0
	 *
	 * @return WP_REST_Response|WP_Error Log entries (newest first) or error.
	 */
	public function get_reset_log() {
		if ( ! class_exists( 'PressPrimer_Quiz_Progress_Reset_Service' ) ) {
			return new WP_Error(
				'ppq_reset_unavailable',
				__( 'Reset tools are not available.', 'pressprimer-quiz' ),
				[ 'status' => 500 ]
			);
		}

		$service = new PressPrimer_Quiz_Progress_Reset_Service();
		$entries = $service->get_log();
		$out     = [];

		foreach ( $entries as $entry ) {
			$initiator               = isset( $entry['initiator_id'] ) ? get_userdata( (int) $entry['initiator_id'] ) : false;
			$entry['initiator_name'] = $initiator ? $initiator->display_name : null;
			$out[]                   = $entry;
		}

		return rest_ensure_response( $out );
	}

	/**
	 * GET /tools/users — search users for the reset scope picker.
	 *
	 * Returns up to 20 matches as { id, label, email }. Gated by the reset
	 * capability, so it is not a general-purpose user directory.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Matching users.
	 */
	public function search_reset_users( $request ) {
		$search = (string) $request->get_param( 'search' );

		$args = [
			'number'  => 20,
			'orderby' => 'display_name',
			'order'   => 'ASC',
		];

		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = [ 'user_login', 'user_email', 'user_nicename' ];
		}

		$users = get_users( $args );
		$out   = [];

		foreach ( $users as $user ) {
			$out[] = [
				'id'    => (int) $user->ID,
				'label' => sprintf(
					/* translators: 1: display name, 2: user login. */
					__( '%1$s (%2$s)', 'pressprimer-quiz' ),
					$user->display_name,
					$user->user_login
				),
				'email' => $user->user_email,
			];
		}

		return rest_ensure_response( $out );
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

		// Filter by owner for users who can only manage their own content.
		// For quiz building, teachers need to see questions from accessible banks too.
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			$user_id = get_current_user_id();

			/**
			 * Filter additional bank IDs a user can access questions from.
			 *
			 * Allows addons (like Educator) to grant access to questions from
			 * shared banks based on group membership or other criteria.
			 *
			 * @since 2.0.0
			 *
			 * @param array $accessible_bank_ids Array of bank IDs user can access. Default empty.
			 * @param int   $user_id             User ID being checked.
			 */
			$accessible_bank_ids = apply_filters( 'pressprimer_quiz_accessible_bank_ids', [], $user_id );
			$accessible_bank_ids = array_filter( array_map( 'absint', $accessible_bank_ids ) );

			if ( ! empty( $accessible_bank_ids ) ) {
				// User can see their own questions OR questions from accessible banks.
				$bank_placeholders = implode( ',', array_fill( 0, count( $accessible_bank_ids ), '%d' ) );
				$where[]           = "(q.author_id = %d OR q.id IN (SELECT question_id FROM {$bank_questions_table} WHERE bank_id IN ({$bank_placeholders})))";
				$where_values[]    = $user_id;
				$where_values      = array_merge( $where_values, $accessible_bank_ids );
			} else {
				// Default: only show questions authored by user.
				$where[]        = 'q.author_id = %d';
				$where_values[] = $user_id;
			}
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

			// Register image ownership for any `data-ppq-attachment-id`
			// markers in the saved content. With the 2.3 decoupled upload
			// endpoint, the /upload-image route creates attachments without
			// binding them — this is where they get attached to the saved
			// question. The helper is idempotent (skips attachment IDs that
			// already own this question), so it's safe even when no new
			// attachments were added.
			$ownership_html = implode(
				"\n",
				array_merge(
					array( $revision->stem ),
					array_map(
						static function ( $answer ) {
							return isset( $answer['text'] ) ? (string) $answer['text'] : '';
						},
						$answers
					),
					array( $revision->feedback_correct, $revision->feedback_incorrect )
				)
			);
			PressPrimer_Quiz_Question::register_image_ownership( $question->id, $ownership_html );

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
			$current_revision       = $question->get_current_revision();
			$new_hash               = PressPrimer_Quiz_Question_Revision::generate_hash( $data['stem'], $answers );
			$new_feedback_correct   = wp_kses_post( $data['feedbackCorrect'] ?? '' );
			$new_feedback_incorrect = wp_kses_post( $data['feedbackIncorrect'] ?? '' );

			// Check if feedback changed (feedback is also part of revision content).
			$feedback_changed = false;
			if ( $current_revision ) {
				$feedback_changed = ( $current_revision->feedback_correct !== $new_feedback_correct )
					|| ( $current_revision->feedback_incorrect !== $new_feedback_incorrect );
			}

			if ( ! $current_revision || $current_revision->content_hash !== $new_hash || $feedback_changed ) {
				// Create new revision
				$revision                     = new PressPrimer_Quiz_Question_Revision();
				$revision->question_id        = $question->id;
				$revision->version            = $current_revision ? $current_revision->version + 1 : 1;
				$revision->stem               = wp_kses_post( $data['stem'] ?? '' );
				$revision->answers_json       = wp_json_encode( $answers );
				$revision->settings_json      = wp_json_encode( [] );
				$revision->feedback_correct   = $new_feedback_correct;
				$revision->feedback_incorrect = $new_feedback_incorrect;
				$revision->content_hash       = $new_hash;
				$revision->created_by         = get_current_user_id();

				$result = $revision->save();

				if ( is_wp_error( $result ) ) {
					throw new Exception( $result->get_error_message() );
				}

				// Update question with new revision ID
				$question->current_revision_id = $revision->id;
				$question->save();

				// Register image ownership for any `data-ppq-attachment-id`
				// markers in the new revision (free 2.3 decoupled-upload
				// flow). Same idempotent helper as create_question; only
				// runs when a new revision was actually saved.
				$ownership_html = implode(
					"\n",
					array_merge(
						array( $revision->stem ),
						array_map(
							static function ( $answer ) {
								return isset( $answer['text'] ) ? (string) $answer['text'] : '';
							},
							$answers
						),
						array( $revision->feedback_correct, $revision->feedback_incorrect )
					)
				);
				PressPrimer_Quiz_Question::register_image_ownership( $question->id, $ownership_html );
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
	 * Upload an image to a question
	 *
	 * Accepts a single image file from the Question Editor's private upload
	 * widget and runs it through the standard WordPress upload pipeline. The
	 * resulting attachment is tagged with a non-unique `_ppq_question_id`
	 * post meta row so its lifecycle can be refcounted against the question
	 * (see PressPrimer_Quiz_Question::delete() and the question-duplicate
	 * flow for cleanup).
	 *
	 * This endpoint deliberately does NOT use wp.media() or expose the
	 * WordPress media library to the caller. Each upload creates a fresh
	 * attachment that only the owning question references.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_REST_Request $request Request object. URL param `id` is the question ID.
	 * @return WP_REST_Response|WP_Error Image metadata on success, WP_Error on validation failure.
	 */
	public function upload_question_image( $request ) {
		$question_id = absint( $request['id'] );

		// Verify the question exists.
		$question = PressPrimer_Quiz_Question::get( $question_id );
		if ( ! $question ) {
			return new WP_Error(
				'invalid_question_id',
				__( 'Question not found.', 'pressprimer-quiz' ),
				array( 'status' => 404 )
			);
		}

		// Ownership: same pattern used by update_quiz — managers bypass the
		// owner check, everyone else must own the question.
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $question->author_id ) !== get_current_user_id() ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to upload images to this question.', 'pressprimer-quiz' ),
				array( 'status' => 403 )
			);
		}

		// Validate $_FILES.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Permission check above; nonce handled by REST framework.
		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			return new WP_Error(
				'missing_file',
				__( 'No file was uploaded.', 'pressprimer-quiz' ),
				array( 'status' => 400 )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$upload_error = isset( $_FILES['file']['error'] ) ? (int) $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $upload_error ) {
			$status = ( UPLOAD_ERR_INI_SIZE === $upload_error || UPLOAD_ERR_FORM_SIZE === $upload_error ) ? 413 : 400;
			return new WP_Error(
				'upload_error',
				__( 'Upload failed.', 'pressprimer-quiz' ),
				array( 'status' => $status )
			);
		}

		// Sanitize incoming file metadata early.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$file_name = sanitize_file_name( wp_unslash( $_FILES['file']['name'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$file_size = isset( $_FILES['file']['size'] ) ? absint( $_FILES['file']['size'] ) : 0;
		// tmp_name is a PHP-generated server path, not user-controlled content; passing it through wp_check_filetype_and_ext() below validates the actual file contents.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file_tmp = isset( $_FILES['file']['tmp_name'] ) ? wp_unslash( $_FILES['file']['tmp_name'] ) : '';

		// Enforce the size cap (default 8 MB, filterable).
		$max_size = (int) apply_filters( 'pressprimer_quiz_image_upload_max_size', 8 * 1024 * 1024 );
		if ( $file_size > $max_size ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: maximum file size in MB */
					__( 'File too large. Maximum allowed: %s MB.', 'pressprimer-quiz' ),
					number_format_i18n( $max_size / ( 1024 * 1024 ), 1 )
				),
				array( 'status' => 413 )
			);
		}

		// MIME whitelist. SVG is excluded by default — see security note in
		// docs/versions/v2.x/v2.3/features/005-image-support.md.
		$allowed_mimes = (array) apply_filters(
			'pressprimer_quiz_image_upload_allowed_mimes',
			array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' )
		);

		// Build an extension → mime map for the wp_handle_upload overrides
		// from the (possibly filtered) allowed mime set.
		$ext_to_mime  = array();
		$mime_ext_map = array(
			'image/jpeg' => 'jpg|jpeg|jpe',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);
		foreach ( $allowed_mimes as $mime ) {
			if ( isset( $mime_ext_map[ $mime ] ) ) {
				$ext_to_mime[ $mime_ext_map[ $mime ] ] = $mime;
			}
		}

		// Verify the actual file contents match an allowed image MIME.
		// wp_check_filetype_and_ext inspects the file (via finfo) and rejects
		// extension/MIME spoofing.
		$detected = wp_check_filetype_and_ext( $file_tmp, $file_name, $ext_to_mime );
		if ( empty( $detected['type'] ) || ! in_array( $detected['type'], $allowed_mimes, true ) ) {
			return new WP_Error(
				'unsupported_mime_type',
				__( 'Unsupported file type. Allowed: JPG, PNG, GIF, WebP.', 'pressprimer-quiz' ),
				array( 'status' => 415 )
			);
		}

		// Load the WP upload pipeline (not auto-loaded on REST requests).
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// Run the upload. test_form=false because REST is not a classic form.
		$overrides = array(
			'test_form' => false,
			'mimes'     => $ext_to_mime,
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$upload = wp_handle_upload( $_FILES['file'], $overrides );

		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error(
				'upload_failed',
				$upload['error'],
				array( 'status' => 500 )
			);
		}

		// Insert the attachment record.
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $upload['type'],
				'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Generate image metadata (sizes, etc.).
		$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

		// Tag for refcount cleanup. Use add_post_meta (not update_post_meta)
		// so duplicating a question can add a SECOND row referencing the
		// same attachment without overwriting the original ownership.
		add_post_meta( $attachment_id, '_ppq_question_id', $question_id );

		return new WP_REST_Response(
			array(
				'id'     => (int) $attachment_id,
				'url'    => wp_get_attachment_url( $attachment_id ),
				'alt'    => '',
				'width'  => isset( $attachment_metadata['width'] ) ? (int) $attachment_metadata['width'] : 0,
				'height' => isset( $attachment_metadata['height'] ) ? (int) $attachment_metadata['height'] : 0,
			),
			200
		);
	}

	/**
	 * Upload an image without binding to a specific question
	 *
	 * Decoupled counterpart to upload_question_image(). Runs the same upload
	 * pipeline (size, MIME, content-type validation, wp_handle_upload,
	 * attachment insert) but does not tag the new attachment with
	 * `_ppq_question_id` — ownership is registered at question save time
	 * via PressPrimer_Quiz_Question::register_image_ownership, scanning the
	 * saved stem/answers/feedback for `data-ppq-attachment-id` markers.
	 *
	 * This lets the Question Editor's image button work on a brand-new
	 * question that doesn't have an ID yet, with no auto-draft hack.
	 *
	 * @since 2.3.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Image metadata on success, WP_Error on validation failure.
	 */
	public function upload_image( $request ) {
		unset( $request );

		// Validate $_FILES.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Permission check on the route; nonce handled by REST framework.
		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			return new WP_Error(
				'missing_file',
				__( 'No file was uploaded.', 'pressprimer-quiz' ),
				array( 'status' => 400 )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$upload_error = isset( $_FILES['file']['error'] ) ? (int) $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $upload_error ) {
			$status = ( UPLOAD_ERR_INI_SIZE === $upload_error || UPLOAD_ERR_FORM_SIZE === $upload_error ) ? 413 : 400;
			return new WP_Error(
				'upload_error',
				__( 'Upload failed.', 'pressprimer-quiz' ),
				array( 'status' => $status )
			);
		}

		// Sanitize incoming file metadata early.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$file_name = sanitize_file_name( wp_unslash( $_FILES['file']['name'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$file_size = isset( $_FILES['file']['size'] ) ? absint( $_FILES['file']['size'] ) : 0;
		// tmp_name is a PHP-generated server path, not user-controlled content; passing it through wp_check_filetype_and_ext() below validates the actual file contents.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file_tmp = isset( $_FILES['file']['tmp_name'] ) ? wp_unslash( $_FILES['file']['tmp_name'] ) : '';

		// Enforce the size cap (default 8 MB, filterable).
		$max_size = (int) apply_filters( 'pressprimer_quiz_image_upload_max_size', 8 * 1024 * 1024 );
		if ( $file_size > $max_size ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: maximum file size in MB */
					__( 'File too large. Maximum allowed: %s MB.', 'pressprimer-quiz' ),
					number_format_i18n( $max_size / ( 1024 * 1024 ), 1 )
				),
				array( 'status' => 413 )
			);
		}

		// MIME whitelist.
		$allowed_mimes = (array) apply_filters(
			'pressprimer_quiz_image_upload_allowed_mimes',
			array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' )
		);

		$ext_to_mime  = array();
		$mime_ext_map = array(
			'image/jpeg' => 'jpg|jpeg|jpe',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);
		foreach ( $allowed_mimes as $mime ) {
			if ( isset( $mime_ext_map[ $mime ] ) ) {
				$ext_to_mime[ $mime_ext_map[ $mime ] ] = $mime;
			}
		}

		$detected = wp_check_filetype_and_ext( $file_tmp, $file_name, $ext_to_mime );
		if ( empty( $detected['type'] ) || ! in_array( $detected['type'], $allowed_mimes, true ) ) {
			return new WP_Error(
				'unsupported_mime_type',
				__( 'Unsupported file type. Allowed: JPG, PNG, GIF, WebP.', 'pressprimer-quiz' ),
				array( 'status' => 415 )
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$overrides = array(
			'test_form' => false,
			'mimes'     => $ext_to_mime,
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$upload = wp_handle_upload( $_FILES['file'], $overrides );

		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error(
				'upload_failed',
				$upload['error'],
				array( 'status' => 500 )
			);
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $upload['type'],
				'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

		// No _ppq_question_id tagging here — ownership is registered at
		// question save time by the create_question / update_question
		// handlers calling register_image_ownership.

		return new WP_REST_Response(
			array(
				'id'     => (int) $attachment_id,
				'url'    => wp_get_attachment_url( $attachment_id ),
				'alt'    => '',
				'width'  => isset( $attachment_metadata['width'] ) ? (int) $attachment_metadata['width'] : 0,
				'height' => isset( $attachment_metadata['height'] ) ? (int) $attachment_metadata['height'] : 0,
			),
			200
		);
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

		// Admins can see all banks, others see only their own plus shared banks.
		if ( current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			$banks = PressPrimer_Quiz_Bank::get_all();
		} else {
			$banks = PressPrimer_Quiz_Bank::get_for_user( $user_id );

			// Allow addons to add additional accessible banks (e.g., shared banks).
			$additional_bank_ids = apply_filters( 'pressprimer_quiz_accessible_bank_ids', array(), $user_id );
			$additional_bank_ids = array_filter( array_map( 'absint', $additional_bank_ids ) );

			if ( ! empty( $additional_bank_ids ) ) {
				// Get IDs of banks we already have.
				$existing_ids = array_map(
					function ( $bank ) {
						return $bank->id;
					},
					$banks
				);

				// Add banks that aren't already in the list.
				foreach ( $additional_bank_ids as $bank_id ) {
					if ( ! in_array( $bank_id, $existing_ids, true ) ) {
						$bank = PressPrimer_Quiz_Bank::get( $bank_id );
						if ( $bank ) {
							$banks[] = $bank;
						}
					}
				}
			}
		}

		// Sort banks alphabetically by name.
		usort(
			$banks,
			function ( $a, $b ) {
				return strcasecmp( $a->name, $b->name );
			}
		);

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
	 * Returns quizzes the current user can access. Administrators see all quizzes,
	 * while teachers see only their own quizzes.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Implemented with owner filtering.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_quizzes( $request ) {
		$user_id = get_current_user_id();

		// Build query args.
		$args = array(
			'order_by' => 'title',
			'order'    => 'ASC',
			'limit'    => 100,
		);

		$where = array();

		// Non-admins only see their own quizzes.
		if ( ! current_user_can( 'manage_options' ) ) {
			$where['owner_id'] = $user_id;
		}

		// Exclude School's spaced-repetition review quizzes by default; the
		// include_review_quizzes flag restores them (Post-Scope Behavioral
		// Amendment, 2026-06-11). Fetch-by-ID is unaffected.
		if ( ! rest_sanitize_boolean( $request->get_param( 'include_review_quizzes' ) ) ) {
			$where['is_review_quiz'] = 0;
		}

		if ( ! empty( $where ) ) {
			$args['where'] = $where;
		}

		$quizzes = PressPrimer_Quiz_Quiz::find( $args );

		$items = array();

		foreach ( $quizzes as $quiz ) {
			$items[] = array(
				'id'    => $quiz->id,
				'title' => $quiz->title,
			);
		}

		return new WP_REST_Response( $items, 200 );
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
				// Review quizzes (School SR) are never embeddable, so they are
				// always excluded from the block-editor list.
				'where'    => [
					'status'         => 'published',
					'is_review_quiz' => 0,
				],
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
	 * Sanitize a submitted max_answers_per_question value
	 *
	 * Accepts null/empty (meaning "show all answers"), or an integer in the
	 * range [2, 8]. The upper bound matches the per-question answer-option
	 * limit enforced by the Question editor (8 for MC/MA, 2 for true/false).
	 * Out-of-range integers and non-numeric strings return a WP_Error with
	 * HTTP 400 so the caller can short-circuit before any database
	 * transaction opens.
	 *
	 * @since 2.3.0
	 *
	 * @param mixed $value Raw value from the request body.
	 * @return int|null|WP_Error Integer 2-8, NULL for "show all", or WP_Error on out-of-range input.
	 */
	private function sanitize_max_answers_per_question( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( ! is_numeric( $value ) ) {
			return new WP_Error(
				'invalid_max_answers_per_question',
				__( 'max_answers_per_question must be an integer between 2 and 8, or null.', 'pressprimer-quiz' ),
				array( 'status' => 400 )
			);
		}

		$intval = (int) $value;
		if ( $intval < 2 || $intval > 8 ) {
			return new WP_Error(
				'invalid_max_answers_per_question',
				__( 'max_answers_per_question must be between 2 and 8.', 'pressprimer-quiz' ),
				array( 'status' => 400 )
			);
		}

		return $intval;
	}

	/**
	 * Compute informational warnings for the random distractor cap
	 *
	 * Returns a list of human-readable warning strings the REST client can
	 * surface to the quiz author. None of these block the save. The cap is
	 * only inspected for fixed-mode quizzes, where the assigned questions
	 * are statically known; dynamic quizzes generate their question set at
	 * attempt time and have no static "question list" to warn against.
	 *
	 * @since 2.3.0
	 *
	 * @param PressPrimer_Quiz_Quiz $quiz Quiz to inspect after save.
	 * @return string[] Zero or more warning messages.
	 */
	private function compute_max_answers_warnings( $quiz ) {
		$warnings = array();

		if ( empty( $quiz->max_answers_per_question ) ) {
			return $warnings;
		}

		if ( 'fixed' !== $quiz->generation_mode ) {
			return $warnings;
		}

		$cap                            = (int) $quiz->max_answers_per_question;
		$items                          = $quiz->get_items();
		$any_question_exceeds_cap_total = false;

		foreach ( $items as $item ) {
			$question = PressPrimer_Quiz_Question::get( $item->question_id );
			if ( ! $question ) {
				continue;
			}

			$revision = $question->get_current_revision();
			if ( ! $revision ) {
				continue;
			}

			$answers = $revision->get_answers();
			$total   = is_array( $answers ) ? count( $answers ) : 0;
			$correct = 0;
			if ( is_array( $answers ) ) {
				foreach ( $answers as $answer ) {
					if ( ! empty( $answer['is_correct'] ) ) {
						++$correct;
					}
				}
			}

			if ( $total > $cap ) {
				$any_question_exceeds_cap_total = true;
			}

			if ( $correct > $cap ) {
				$stem         = is_object( $revision ) && ! empty( $revision->stem ) ? wp_strip_all_tags( $revision->stem ) : '';
				$stem_preview = '' !== $stem ? mb_substr( $stem, 0, 60 ) : __( '(untitled)', 'pressprimer-quiz' );

				$warnings[] = sprintf(
					/* translators: 1: question stem preview, 2: number of correct answers, 3: configured cap */
					__( 'Question "%1$s" has %2$d correct answers, more than your cap of %3$d. All correct answers will be shown for that question.', 'pressprimer-quiz' ),
					$stem_preview,
					(int) $correct,
					$cap
				);
			}
		}

		if ( ! empty( $items ) && ! $any_question_exceeds_cap_total ) {
			$warnings[] = sprintf(
				/* translators: %d: configured cap */
				__( 'No questions in this quiz have more than %d answers. This setting will not affect anything until you add a question with more options.', 'pressprimer-quiz' ),
				$cap
			);
		}

		return $warnings;
	}

	/**
	 * Sanitize a submitted display_settings payload
	 *
	 * Accepts an object or associative array whose keys are a subset of the
	 * 15 display option keys. Returns a clean array of (string => bool)
	 * pairs ready to pass to PressPrimer_Quiz_Quiz::set_display_settings(),
	 * or a WP_Error with HTTP 400 if the payload contains any unknown key.
	 *
	 * Strict validation lives here so REST clients see a clear error rather
	 * than the silent key-drop the model's setter performs.
	 *
	 * @since 2.3.0
	 *
	 * @param mixed $value Raw value from the request body.
	 * @return array|WP_Error Sanitized map of display keys → booleans, or WP_Error on unknown key.
	 */
	private function sanitize_display_settings( $value ) {
		// An object cast (or empty array) means "clear all per-quiz defaults."
		if ( null === $value || '' === $value ) {
			return array();
		}

		if ( ! is_array( $value ) && ! is_object( $value ) ) {
			return new WP_Error(
				'invalid_display_settings',
				__( 'display_settings must be an object.', 'pressprimer-quiz' ),
				array( 'status' => 400 )
			);
		}

		$payload      = (array) $value;
		$allowed_keys = array(
			'show_description',
			'show_question_count',
			'show_quiz_type',
			'show_time_limit',
			'show_pass_percentage',
			'show_attempt_count',
			'show_attempt_history',
			'show_score',
			'show_pass_fail',
			'show_time_spent',
			'show_average',
			'show_category_breakdown',
			'show_question_review',
			'show_retake_button',
			'show_scoring_explanations',
		);

		$sanitized = array();
		foreach ( $payload as $key => $val ) {
			if ( ! is_string( $key ) || ! in_array( $key, $allowed_keys, true ) ) {
				return new WP_Error(
					'invalid_display_key',
					sprintf(
						/* translators: %s: invalid display option key */
						__( 'Unknown display option key: %s', 'pressprimer-quiz' ),
						is_scalar( $key ) ? (string) $key : '(non-string)'
					),
					array( 'status' => 400 )
				);
			}
			$sanitized[ $key ] = rest_sanitize_boolean( $val );
		}

		return $sanitized;
	}

	/**
	 * Sanitize a submitted ma_scoring_mode value
	 *
	 * Empty, null, or absent inputs map to NULL (meaning "use site default").
	 * Whitelisted strings pass through unchanged. Any other value returns a
	 * WP_Error with HTTP 400 so the caller can short-circuit the save.
	 *
	 * @since 2.3.0
	 *
	 * @param mixed $value Raw value from the request body.
	 * @return string|null|WP_Error Sanitized mode, NULL for site default, or WP_Error on invalid input.
	 */
	private function sanitize_ma_scoring_mode( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		$allowed_modes = array( 'right_minus_wrong', 'proportional', 'partial_no_wrong', 'all_or_nothing' );

		if ( is_string( $value ) && in_array( $value, $allowed_modes, true ) ) {
			return $value;
		}

		return new WP_Error(
			'invalid_scoring_mode',
			__( 'Invalid scoring mode. Allowed values: right_minus_wrong, proportional, partial_no_wrong, all_or_nothing.', 'pressprimer-quiz' ),
			array( 'status' => 400 )
		);
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
			'id'                       => $quiz->id,
			'title'                    => $quiz->title,
			'description'              => $quiz->description,
			'featured_image_id'        => $quiz->featured_image_id,
			'status'                   => $quiz->status,
			'mode'                     => $quiz->mode,
			'time_limit_seconds'       => $quiz->time_limit_seconds,
			'pass_percent'             => $quiz->pass_percent,
			'allow_skip'               => $quiz->allow_skip,
			'allow_backward'           => $quiz->allow_backward,
			'allow_resume'             => $quiz->allow_resume,
			'max_attempts'             => $quiz->max_attempts,
			'attempt_delay_minutes'    => $quiz->attempt_delay_minutes,
			'randomize_questions'      => $quiz->randomize_questions,
			'randomize_answers'        => $quiz->randomize_answers,
			'page_mode'                => $quiz->page_mode,
			'questions_per_page'       => $quiz->questions_per_page,
			'show_answers'             => $quiz->show_answers,
			'enable_confidence'        => $quiz->enable_confidence,
			'show_points'              => (bool) $quiz->show_points,
			'theme'                    => $quiz->theme,
			'display_density'          => $quiz->display_density,
			'theme_settings_json'      => $quiz->theme_settings_json,
			'band_feedback_json'       => $quiz->band_feedback_json,
			'generation_mode'          => $quiz->generation_mode,
			'access_mode'              => $quiz->access_mode,
			'login_message'            => $quiz->login_message,
			'pool_enabled'             => (bool) $quiz->pool_enabled,
			'max_questions'            => $quiz->max_questions ? (int) $quiz->max_questions : null,
			'pool_size'                => $quiz->get_pool_size()['count'],
			'enable_sr'                => (bool) $quiz->enable_sr,
			'is_review_quiz'           => (bool) $quiz->is_review_quiz,
			'ma_scoring_mode'          => $quiz->ma_scoring_mode,
			// Cast to object so an empty sparse map serializes as {} rather than [].
			'display_settings'         => (object) $quiz->get_display_settings(),
			'max_answers_per_question' => $quiz->max_answers_per_question ? (int) $quiz->max_answers_per_question : null,
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

		// Validate ma_scoring_mode: empty/null/absent → NULL ("use site default"),
		// whitelisted string → accepted, anything else → 400.
		$ma_scoring_mode = $this->sanitize_ma_scoring_mode( $data['ma_scoring_mode'] ?? null );
		if ( is_wp_error( $ma_scoring_mode ) ) {
			return $ma_scoring_mode;
		}

		// Validate display_settings: object with keys from the 15-key whitelist.
		// Unknown keys short-circuit with HTTP 400 invalid_display_key.
		$display_settings = null;
		if ( array_key_exists( 'display_settings', $data ) ) {
			$display_settings = $this->sanitize_display_settings( $data['display_settings'] );
			if ( is_wp_error( $display_settings ) ) {
				return $display_settings;
			}
		}

		// Validate max_answers_per_question: integer 2-20 or null.
		$max_answers_per_question = $this->sanitize_max_answers_per_question( $data['max_answers_per_question'] ?? null );
		if ( is_wp_error( $max_answers_per_question ) ) {
			return $max_answers_per_question;
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Create quiz
			$quiz_id = PressPrimer_Quiz_Quiz::create(
				[
					'uuid'                     => wp_generate_uuid4(),
					'title'                    => sanitize_text_field( $data['title'] ),
					'description'              => wp_kses_post( $data['description'] ?? '' ),
					'featured_image_id'        => absint( $data['featured_image_id'] ?? 0 ),
					'owner_id'                 => get_current_user_id(),
					'status'                   => sanitize_key( $data['status'] ?? 'draft' ),
					'mode'                     => sanitize_key( $data['mode'] ?? 'tutorial' ),
					'time_limit_seconds'       => ! empty( $data['time_limit_seconds'] ) ? absint( $data['time_limit_seconds'] ) : null,
					'pass_percent'             => floatval( $data['pass_percent'] ?? 70 ),
					'allow_skip'               => ! empty( $data['allow_skip'] ),
					'allow_backward'           => ! empty( $data['allow_backward'] ),
					'allow_resume'             => ! empty( $data['allow_resume'] ),
					'max_attempts'             => ! empty( $data['max_attempts'] ) ? absint( $data['max_attempts'] ) : null,
					'attempt_delay_minutes'    => absint( $data['attempt_delay_minutes'] ?? 0 ),
					'randomize_questions'      => ! empty( $data['randomize_questions'] ),
					'randomize_answers'        => ! empty( $data['randomize_answers'] ),
					'page_mode'                => sanitize_key( $data['page_mode'] ?? 'single' ),
					'questions_per_page'       => absint( $data['questions_per_page'] ?? 1 ),
					'show_answers'             => sanitize_key( $data['show_answers'] ?? 'after_submit' ),
					'enable_confidence'        => ! empty( $data['enable_confidence'] ),
					'show_points'              => ! empty( $data['show_points'] ),
					'theme'                    => sanitize_key( $data['theme'] ?? 'default' ),
					'display_density'          => sanitize_key( $data['display_density'] ?? 'default' ),
					'theme_settings_json'      => $data['theme_settings_json'] ?? null,
					'band_feedback_json'       => $data['band_feedback_json'] ?? null,
					'generation_mode'          => sanitize_key( $data['generation_mode'] ?? 'fixed' ),
					'access_mode'              => sanitize_key( $data['access_mode'] ?? 'default' ),
					'login_message'            => wp_kses_post( $data['login_message'] ?? '' ),
					'pool_enabled'             => ! empty( $data['pool_enabled'] ),
					'max_questions'            => isset( $data['max_questions'] ) && '' !== $data['max_questions'] && null !== $data['max_questions'] ? absint( $data['max_questions'] ) : null,
					'enable_sr'                => ! empty( $data['enable_sr'] ),
					'is_review_quiz'           => ! empty( $data['is_review_quiz'] ),
					'ma_scoring_mode'          => $ma_scoring_mode,
					'max_answers_per_question' => $max_answers_per_question,
				]
			);

			if ( is_wp_error( $quiz_id ) ) {
				throw new Exception( $quiz_id->get_error_message() );
			}

			// Persist display_settings via the model setter using the
			// pre-validated payload from sanitize_display_settings() above.
			$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );

			if ( $quiz && null !== $display_settings ) {
				$quiz->set_display_settings( $display_settings );
				$quiz->save();
			}

			// Validate max_questions against pool size.

			if ( $quiz && $quiz->pool_enabled && $quiz->max_questions ) {
				$pool_size = $quiz->get_pool_size();
				if ( $pool_size['count'] > 0 && $quiz->max_questions > $pool_size['count'] ) {
					$wpdb->query( 'ROLLBACK' );
					return new WP_Error(
						'invalid_max_questions',
						sprintf(
							/* translators: %d: pool size */
							__( 'Maximum questions cannot exceed pool size (%d).', 'pressprimer-quiz' ),
							$pool_size['count']
						),
						[ 'status' => 400 ]
					);
				}
			}

			if ( $quiz ) {
				do_action( 'pressprimer_quiz_rest_quiz_saved', $quiz, $data );
			}

			$wpdb->query( 'COMMIT' );

			// Clear dashboard stats cache
			if ( class_exists( 'PressPrimer_Quiz_Statistics_Service' ) ) {
				PressPrimer_Quiz_Statistics_Service::clear_dashboard_cache();
			}

			return new WP_REST_Response(
				array(
					'id'       => $quiz_id,
					'warnings' => $quiz ? $this->compute_max_answers_warnings( $quiz ) : array(),
				),
				201
			);

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

		// Validate ma_scoring_mode: empty/null/absent → NULL ("use site default"),
		// whitelisted string → accepted, anything else → 400.
		$ma_scoring_mode = $this->sanitize_ma_scoring_mode( $data['ma_scoring_mode'] ?? null );
		if ( is_wp_error( $ma_scoring_mode ) ) {
			return $ma_scoring_mode;
		}

		// Validate display_settings: object with keys from the 15-key whitelist.
		// Unknown keys short-circuit with HTTP 400 invalid_display_key.
		$display_settings = null;
		if ( array_key_exists( 'display_settings', $data ) ) {
			$display_settings = $this->sanitize_display_settings( $data['display_settings'] );
			if ( is_wp_error( $display_settings ) ) {
				return $display_settings;
			}
		}

		// Validate max_answers_per_question: integer 2-20 or null.
		$max_answers_per_question = $this->sanitize_max_answers_per_question( $data['max_answers_per_question'] ?? null );
		if ( is_wp_error( $max_answers_per_question ) ) {
			return $max_answers_per_question;
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Update quiz fields
			$quiz->title                    = sanitize_text_field( $data['title'] );
			$quiz->description              = wp_kses_post( $data['description'] ?? '' );
			$quiz->featured_image_id        = absint( $data['featured_image_id'] ?? 0 );
			$quiz->status                   = sanitize_key( $data['status'] ?? 'draft' );
			$quiz->mode                     = sanitize_key( $data['mode'] ?? 'tutorial' );
			$quiz->time_limit_seconds       = ! empty( $data['time_limit_seconds'] ) ? absint( $data['time_limit_seconds'] ) : null;
			$quiz->pass_percent             = floatval( $data['pass_percent'] ?? 70 );
			$quiz->allow_skip               = ! empty( $data['allow_skip'] );
			$quiz->allow_backward           = ! empty( $data['allow_backward'] );
			$quiz->allow_resume             = ! empty( $data['allow_resume'] );
			$quiz->max_attempts             = ! empty( $data['max_attempts'] ) ? absint( $data['max_attempts'] ) : null;
			$quiz->attempt_delay_minutes    = absint( $data['attempt_delay_minutes'] ?? 0 );
			$quiz->randomize_questions      = ! empty( $data['randomize_questions'] );
			$quiz->randomize_answers        = ! empty( $data['randomize_answers'] );
			$quiz->page_mode                = sanitize_key( $data['page_mode'] ?? 'single' );
			$quiz->questions_per_page       = absint( $data['questions_per_page'] ?? 1 );
			$quiz->show_answers             = sanitize_key( $data['show_answers'] ?? 'after_submit' );
			$quiz->enable_confidence        = ! empty( $data['enable_confidence'] );
			$quiz->show_points              = ! empty( $data['show_points'] );
			$quiz->theme                    = sanitize_key( $data['theme'] ?? 'default' );
			$quiz->display_density          = sanitize_key( $data['display_density'] ?? 'default' );
			$quiz->theme_settings_json      = $data['theme_settings_json'] ?? null;
			$quiz->band_feedback_json       = $data['band_feedback_json'] ?? null;
			$quiz->generation_mode          = sanitize_key( $data['generation_mode'] ?? 'fixed' );
			$quiz->access_mode              = sanitize_key( $data['access_mode'] ?? 'default' );
			$quiz->login_message            = wp_kses_post( $data['login_message'] ?? '' );
			$quiz->pool_enabled             = ! empty( $data['pool_enabled'] );
			$quiz->max_questions            = isset( $data['max_questions'] ) && '' !== $data['max_questions'] && null !== $data['max_questions'] ? absint( $data['max_questions'] ) : null;
			$quiz->enable_sr                = ! empty( $data['enable_sr'] );
			$quiz->is_review_quiz           = ! empty( $data['is_review_quiz'] );
			$quiz->ma_scoring_mode          = $ma_scoring_mode;
			$quiz->max_answers_per_question = $max_answers_per_question;

			// Apply display_settings via the model setter using the
			// pre-validated payload from sanitize_display_settings() above.
			if ( null !== $display_settings ) {
				$quiz->set_display_settings( $display_settings );
			}

			$result = $quiz->save();

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Validate max_questions against pool size.
			if ( $quiz->pool_enabled && $quiz->max_questions ) {
				$pool_size = $quiz->get_pool_size();
				if ( $pool_size['count'] > 0 && $quiz->max_questions > $pool_size['count'] ) {
					$wpdb->query( 'ROLLBACK' );
					return new WP_Error(
						'invalid_max_questions',
						sprintf(
							/* translators: %d: pool size */
							__( 'Maximum questions cannot exceed pool size (%d).', 'pressprimer-quiz' ),
							$pool_size['count']
						),
						[ 'status' => 400 ]
					);
				}
			}

			/**
			 * Fires after a quiz is saved via REST API
			 *
			 * Allows addons to process additional quiz data (e.g., pre_test_id)
			 * that the core plugin does not handle directly.
			 *
			 * @since 2.1.0
			 *
			 * @param PressPrimer_Quiz_Quiz $quiz The saved quiz object.
			 * @param array                 $data The raw request data.
			 */
			do_action( 'pressprimer_quiz_rest_quiz_saved', $quiz, $data );

			$wpdb->query( 'COMMIT' );

			return new WP_REST_Response(
				array(
					'id'       => $quiz->id,
					'warnings' => $this->compute_max_answers_warnings( $quiz ),
				),
				200
			);

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

		$quiz = $this->get_owned_quiz_or_error( $quiz_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

		$items = PressPrimer_Quiz_Quiz_Item::get_for_quiz( $quiz_id );

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
					'max_points'    => $question ? (float) $question->max_points : 1.0,
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

		$quiz = $this->get_owned_quiz_or_error( $quiz_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

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
		$quiz_id = absint( $request['quiz_id'] );
		$item_id = absint( $request['item_id'] );
		$data    = $request->get_json_params();

		$quiz = $this->get_owned_quiz_or_error( $quiz_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

		$item = PressPrimer_Quiz_Quiz_Item::get( $item_id );

		if ( ! $item || absint( $item->quiz_id ) !== absint( $quiz->id ) ) {
			return new WP_Error( 'not_found', __( 'Quiz item not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		// Update order index if provided
		if ( isset( $data['order_index'] ) ) {
			$item->order_index = absint( $data['order_index'] );
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
		$quiz_id = absint( $request['quiz_id'] );
		$item_id = absint( $request['item_id'] );

		$quiz = $this->get_owned_quiz_or_error( $quiz_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

		$item = PressPrimer_Quiz_Quiz_Item::get( $item_id );

		if ( ! $item || absint( $item->quiz_id ) !== absint( $quiz->id ) ) {
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
		$quiz_id  = absint( $request['quiz_id'] );
		$data     = $request->get_json_params();
		$item_ids = $data['item_ids'] ?? [];

		$quiz = $this->get_owned_quiz_or_error( $quiz_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

		if ( empty( $item_ids ) ) {
			return new WP_Error( 'no_items', __( 'No items provided.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		// Every supplied item must belong to the URL's quiz so the
		// reorder cannot reach across into another teacher's items.
		foreach ( $item_ids as $item_id ) {
			$item = PressPrimer_Quiz_Quiz_Item::get( absint( $item_id ) );
			if ( ! $item || absint( $item->quiz_id ) !== absint( $quiz->id ) ) {
				return new WP_Error( 'not_found', __( 'Quiz item not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
			}
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

		$quiz = $this->get_owned_quiz_or_error( $quiz_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

		$rules = PressPrimer_Quiz_Quiz_Rule::get_for_quiz( $quiz_id );

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

		$quiz = $this->get_owned_quiz_or_error( $quiz_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

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
		$quiz_id = absint( $request['quiz_id'] );
		$rule_id = absint( $request['rule_id'] );
		$data    = $request->get_json_params();

		$quiz = $this->get_owned_quiz_or_error( $quiz_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

		$rule = PressPrimer_Quiz_Quiz_Rule::get( $rule_id );

		if ( ! $rule || absint( $rule->quiz_id ) !== absint( $quiz->id ) ) {
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
		$quiz_id = absint( $request['quiz_id'] );
		$rule_id = absint( $request['rule_id'] );

		$quiz = $this->get_owned_quiz_or_error( $quiz_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

		$rule = PressPrimer_Quiz_Quiz_Rule::get( $rule_id );

		if ( ! $rule || absint( $rule->quiz_id ) !== absint( $quiz->id ) ) {
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
		$quiz_id    = absint( $request['quiz_id'] );
		$data       = $request->get_json_params();
		$rule_order = isset( $data['rule_order'] ) && is_array( $data['rule_order'] ) ? $data['rule_order'] : [];

		$quiz = $this->get_owned_quiz_or_error( $quiz_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

		if ( empty( $rule_order ) ) {
			return new WP_Error( 'no_rules', __( 'No rules provided.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		$order_map = [];
		foreach ( $rule_order as $index => $rule_id ) {
			$rule_id = absint( $rule_id );

			// Every supplied rule must belong to the URL's quiz so the
			// reorder cannot reach across into another teacher's rules.
			$rule = PressPrimer_Quiz_Quiz_Rule::get( $rule_id );
			if ( ! $rule || absint( $rule->quiz_id ) !== absint( $quiz->id ) ) {
				return new WP_Error( 'not_found', __( 'Quiz rule not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
			}

			$order_map[ $rule_id ] = (int) $index;
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

		// Site-wide default MA scoring is stored as its own option (not in the
		// bundle) so the scoring service can resolve it without touching the
		// settings array. Expose it under the same response shape for the
		// React settings UI.
		$settings['default_ma_scoring'] = get_option(
			'pressprimer_quiz_default_ma_scoring',
			'right_minus_wrong'
		);

		// Designated front-end dashboard page (v3.0) is its own option; expose it
		// in the same bundle so the General tab can select it like any field.
		$settings['dashboard_page_id'] = (int) get_option( 'pressprimer_quiz_dashboard_page_id', 0 );

		// Guest marketing-consent settings are standalone options (read on the
		// quiz-taking path without loading the settings bundle). Expose them
		// under the same response shape; surface the translatable default label
		// when none has been saved.
		$settings['guest_consent_enabled'] = (bool) get_option( 'pressprimer_quiz_guest_consent_enabled', false );
		$consent_label                     = get_option( 'pressprimer_quiz_guest_consent_label', '' );
		$settings['guest_consent_label']   = ( '' !== trim( (string) $consent_label ) )
			? $consent_label
			: __( 'Add me to the newsletter. I understand that I can unsubscribe at any time.', 'pressprimer-quiz' );

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

		if ( isset( $data['enable_math'] ) ) {
			$sanitized['enable_math'] = rest_sanitize_boolean( $data['enable_math'] );
		}

		if ( isset( $data['default_access_mode'] ) ) {
			$sanitized['default_access_mode'] = in_array( $data['default_access_mode'], [ 'guest_optional', 'guest_required', 'login_required' ], true )
				? $data['default_access_mode']
				: 'guest_optional';
		}

		if ( isset( $data['login_message_default'] ) ) {
			$sanitized['login_message_default'] = wp_kses_post( $data['login_message_default'] );
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

		if ( isset( $data['display_density'] ) ) {
			$density                      = sanitize_key( $data['display_density'] );
			$sanitized['display_density'] = in_array( $density, [ 'standard', 'condensed' ], true ) ? $density : 'standard';
		}

		// New spacing settings (v2.1).
		if ( isset( $data['appearance_line_height'] ) ) {
			$value                               = floatval( $data['appearance_line_height'] );
			$sanitized['appearance_line_height'] = max( 1.2, min( 1.8, $value ) );
		}

		if ( isset( $data['appearance_answer_spacing'] ) ) {
			$value                                  = absint( $data['appearance_answer_spacing'] );
			$sanitized['appearance_answer_spacing'] = max( 4, min( 24, $value ) );
		}

		if ( isset( $data['appearance_question_spacing'] ) ) {
			$value                                    = absint( $data['appearance_question_spacing'] );
			$sanitized['appearance_question_spacing'] = max( 8, min( 48, $value ) );
		}

		if ( isset( $data['appearance_max_width'] ) ) {
			$value                             = absint( $data['appearance_max_width'] );
			$sanitized['appearance_max_width'] = max( 400, min( 1200, $value ) );
		}

		// Condensed mode spacing settings (v2.1).
		if ( isset( $data['appearance_condensed_line_height'] ) ) {
			$value = floatval( $data['appearance_condensed_line_height'] );
			$sanitized['appearance_condensed_line_height'] = max( 1.2, min( 1.8, $value ) );
		}

		if ( isset( $data['appearance_condensed_answer_spacing'] ) ) {
			$value = absint( $data['appearance_condensed_answer_spacing'] );
			$sanitized['appearance_condensed_answer_spacing'] = max( 4, min( 24, $value ) );
		}

		if ( isset( $data['appearance_condensed_question_spacing'] ) ) {
			$value = absint( $data['appearance_condensed_question_spacing'] );
			$sanitized['appearance_condensed_question_spacing'] = max( 8, min( 48, $value ) );
		}

		if ( isset( $data['appearance_condensed_max_width'] ) ) {
			$value                                       = absint( $data['appearance_condensed_max_width'] );
			$sanitized['appearance_condensed_max_width'] = max( 400, min( 1200, $value ) );
		}

		// Site-wide default MA scoring is stored as a separate WordPress option
		// rather than inside the settings bundle, so the scoring service can
		// resolve it cheaply via get_option(). Validate the whitelist here and
		// silently keep the existing value when an unknown mode is submitted.
		// When the value actually changes, the change is tracked separately
		// (in $extra_changes below) and merged into the settings_updated hook
		// payload so audit-log consumers see it like any other setting.
		$extra_changes = array();
		if ( isset( $data['default_ma_scoring'] ) ) {
			$allowed_ma_modes = array( 'right_minus_wrong', 'proportional', 'partial_no_wrong', 'all_or_nothing' );
			$submitted_mode   = is_string( $data['default_ma_scoring'] ) ? $data['default_ma_scoring'] : '';
			if ( in_array( $submitted_mode, $allowed_ma_modes, true ) ) {
				$old_ma_default = get_option( 'pressprimer_quiz_default_ma_scoring', 'right_minus_wrong' );
				if ( $old_ma_default !== $submitted_mode ) {
					update_option( 'pressprimer_quiz_default_ma_scoring', $submitted_mode );
					$extra_changes[] = array(
						'field'  => 'default_ma_scoring',
						'before' => $old_ma_default,
						'after'  => $submitted_mode,
					);
				}
			}
		}

		// Front-end dashboard page (v3.0) is also a standalone option. 0 clears
		// the designation; any other value must be a real page. Invalid IDs are
		// ignored so a bad submission never points the option at a non-page.
		if ( isset( $data['dashboard_page_id'] ) ) {
			$submitted_page = absint( $data['dashboard_page_id'] );
			$page_valid     = false;

			if ( 0 === $submitted_page ) {
				$page_valid = true;
			} else {
				$page       = get_post( $submitted_page );
				$page_valid = ( $page && 'page' === $page->post_type );
			}

			if ( $page_valid ) {
				$old_page = (int) get_option( 'pressprimer_quiz_dashboard_page_id', 0 );
				if ( $old_page !== $submitted_page ) {
					update_option( 'pressprimer_quiz_dashboard_page_id', $submitted_page );
					$extra_changes[] = array(
						'field'  => 'dashboard_page_id',
						'before' => $old_page,
						'after'  => $submitted_page,
					);
				}
			}
		}

		// Guest marketing-consent settings are standalone options.
		if ( isset( $data['guest_consent_enabled'] ) ) {
			$new_consent_enabled = rest_sanitize_boolean( $data['guest_consent_enabled'] );
			$old_consent_enabled = (bool) get_option( 'pressprimer_quiz_guest_consent_enabled', false );

			if ( $old_consent_enabled !== $new_consent_enabled ) {
				update_option( 'pressprimer_quiz_guest_consent_enabled', $new_consent_enabled );
				$extra_changes[] = array(
					'field'  => 'guest_consent_enabled',
					'before' => $old_consent_enabled,
					'after'  => $new_consent_enabled,
				);
			}
		}

		if ( isset( $data['guest_consent_label'] ) ) {
			// Owner-entered label is plain text; the privacy link is appended by
			// the plugin at render time, never stored.
			$new_consent_label = sanitize_textarea_field( wp_unslash( $data['guest_consent_label'] ) );
			$old_consent_label = (string) get_option( 'pressprimer_quiz_guest_consent_label', '' );

			if ( $old_consent_label !== $new_consent_label ) {
				update_option( 'pressprimer_quiz_guest_consent_label', $new_consent_label );
				$extra_changes[] = array(
					'field'  => 'guest_consent_label',
					'before' => $old_consent_label,
					'after'  => $new_consent_label,
				);
			}
		}

		/**
		 * Filter the sanitized settings before saving.
		 *
		 * Allows addons to add their own settings to be saved via the core REST API.
		 *
		 * @since 2.0.0
		 *
		 * @param array $sanitized Already sanitized settings from core plugin.
		 * @param array $data      Raw input data from the REST request.
		 */
		$sanitized = apply_filters( 'pressprimer_quiz_sanitize_settings', $sanitized, $data );

		// Merge with existing settings
		$merged = array_merge( $existing, $sanitized );

		// Save settings
		update_option( PressPrimer_Quiz_Admin_Settings::OPTION_NAME, $merged );

		// Clear object cache to ensure fresh data on next request
		// This prevents issues with persistent object caching plugins (Redis, Memcached, etc.)
		wp_cache_delete( PressPrimer_Quiz_Admin_Settings::OPTION_NAME, 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		// Determine which section was updated based on the keys.
		$section = 'general';
		if ( isset( $sanitized['email_from_name'] ) || isset( $sanitized['email_results_auto_send'] ) ) {
			$section = 'email';
		} elseif ( isset( $sanitized['social_sharing_twitter'] ) || isset( $sanitized['social_sharing_facebook'] ) ) {
			$section = 'sharing';
		} elseif ( isset( $sanitized['appearance_font_family'] ) || isset( $sanitized['appearance_primary_color'] ) || isset( $sanitized['appearance_line_height'] ) || isset( $sanitized['appearance_answer_spacing'] ) || isset( $sanitized['appearance_condensed_line_height'] ) || isset( $sanitized['appearance_condensed_answer_spacing'] ) ) {
			$section = 'appearance';
		} elseif ( isset( $sanitized['remove_data_on_uninstall'] ) && 1 === count( $sanitized ) ) {
			$section = 'advanced';
		}

		// Build list of actually changed settings (compare old vs new values).
		$actual_changes = array();
		foreach ( $sanitized as $key => $new_value ) {
			$old_value = isset( $existing[ $key ] ) ? $existing[ $key ] : null;
			// Use loose comparison to handle type differences (e.g., "1" vs 1).
			// phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
			if ( $old_value != $new_value ) {
				$actual_changes[] = array(
					'field'  => $key,
					'before' => $old_value,
					'after'  => $new_value,
				);
			}
		}

		// Append any standalone option changes (e.g., default_ma_scoring,
		// which is stored outside the settings bundle but should still surface
		// in the settings_updated payload for audit-log consumers).
		if ( ! empty( $extra_changes ) ) {
			$actual_changes = array_merge( $actual_changes, $extra_changes );
		}

		// Only fire the hook if something actually changed.
		if ( ! empty( $actual_changes ) ) {
			/**
			 * Fires after settings are updated.
			 *
			 * @since 2.0.0
			 *
			 * @param string $section The settings section that was updated.
			 * @param array  $data    The changed settings data with before/after values.
			 */
			do_action(
				'pressprimer_quiz_settings_updated',
				$section,
				array(
					'changes' => $actual_changes,
				)
			);
		}

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
		$provider = $this->resolve_request_provider_id( $request );
		$data     = $request->get_json_params();
		$api_key  = sanitize_text_field( is_array( $data ) && isset( $data['api_key'] ) ? $data['api_key'] : '' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'invalid_key', __( 'Please provide an API key.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		$providers = PressPrimer_Quiz_AI_Service::get_providers();
		if ( ! isset( $providers[ $provider ] ) ) {
			return new WP_Error( 'invalid_provider', __( 'Unknown AI provider.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		// Validate the key with the provider before saving (the key is never logged).
		$validation = $providers[ $provider ]->validate_key( $api_key );
		if ( is_wp_error( $validation ) ) {
			return new WP_Error( 'invalid_key', $validation->get_error_message(), [ 'status' => 400 ] );
		}

		$saved = PressPrimer_Quiz_AI_Service::save_site_api_key( $provider, $api_key );
		if ( is_wp_error( $saved ) ) {
			return new WP_Error( 'encryption_failed', $saved->get_error_message(), [ 'status' => 500 ] );
		}

		$status = PressPrimer_Quiz_AI_Service::get_api_key_status( $provider );

		return new WP_REST_Response(
			[
				'success'    => true,
				'provider'   => $provider,
				'message'    => __( 'API key saved and validated successfully.', 'pressprimer-quiz' ),
				'masked_key' => $status['masked_key'] ?? '',
			],
			200
		);
	}

	/**
	 * Delete API key
	 *
	 * Removes the site-wide API key for the requested (or active) provider.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function delete_api_key( $request ) {
		$provider = $this->resolve_request_provider_id( $request );

		PressPrimer_Quiz_AI_Service::save_site_api_key( $provider, '' );

		return new WP_REST_Response(
			[
				'success'  => true,
				'provider' => $provider,
				'message'  => __( 'API key removed successfully.', 'pressprimer-quiz' ),
			],
			200
		);
	}

	/**
	 * Validate API key
	 *
	 * Validates the configured site-wide key for the requested (or active)
	 * provider against that provider.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function validate_api_key( $request ) {
		$provider  = $this->resolve_request_provider_id( $request );
		$providers = PressPrimer_Quiz_AI_Service::get_providers();
		$api_key   = PressPrimer_Quiz_AI_Service::get_api_key_for_provider( $provider );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_key', __( 'No API key configured.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		$result = $providers[ $provider ]->validate_key( $api_key );

		if ( is_wp_error( $result ) ) {
			// Preserve the provider's normalized code so the UI can distinguish a
			// genuine invalid key from a "could not confirm" rate limit.
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), [ 'status' => 400 ] );
		}

		return new WP_REST_Response(
			[
				'success'  => true,
				'provider' => $provider,
				'message'  => __( 'API key is valid.', 'pressprimer-quiz' ),
			],
			200
		);
	}

	/**
	 * Get API models
	 *
	 * Returns selectable models for the requested (or active) provider: OpenAI's
	 * live account list, or the provider's curated list otherwise.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_api_models( $request ) {
		$provider  = $this->resolve_request_provider_id( $request );
		$providers = PressPrimer_Quiz_AI_Service::get_providers();

		if ( 'openai' === $provider ) {
			$api_key = PressPrimer_Quiz_AI_Service::get_api_key_for_provider( 'openai' );

			if ( empty( $api_key ) ) {
				return new WP_Error( 'no_key', __( 'No API key configured.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
			}

			$models = PressPrimer_Quiz_AI_Service::get_available_models( $api_key );

			if ( is_wp_error( $models ) ) {
				return new WP_Error( 'fetch_failed', $models->get_error_message(), [ 'status' => 500 ] );
			}
		} else {
			$models = $providers[ $provider ]->get_models();
		}

		return new WP_REST_Response(
			[
				'success'  => true,
				'provider' => $provider,
				'models'   => $models,
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
		$provider = $this->resolve_request_provider_id( $request );
		$data     = $request->get_json_params();
		$model    = sanitize_text_field( is_array( $data ) && isset( $data['model'] ) ? $data['model'] : '' );

		PressPrimer_Quiz_AI_Service::save_site_model( $provider, $model );

		return new WP_REST_Response(
			[
				'success'  => true,
				'provider' => $provider,
				'message'  => __( 'Model preference saved.', 'pressprimer-quiz' ),
			],
			200
		);
	}

	/**
	 * Set the active AI provider (site-level).
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function set_ai_provider( $request ) {
		$data     = $request->get_json_params();
		$provider = sanitize_key( is_array( $data ) && isset( $data['provider'] ) ? (string) $data['provider'] : '' );

		if ( ! PressPrimer_Quiz_AI_Service::set_active_provider( $provider ) ) {
			return new WP_Error( 'invalid_provider', __( 'Unknown AI provider.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		return new WP_REST_Response(
			[
				'success'  => true,
				'provider' => $provider,
			],
			200
		);
	}

	/**
	 * Resolve the provider id for an AI settings request.
	 *
	 * Reads `provider` from the JSON body or query, validates it against the
	 * registered providers, and falls back to the active provider.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return string Registered provider id.
	 */
	private function resolve_request_provider_id( $request ) {
		$json     = $request->get_json_params();
		$provider = '';

		if ( is_array( $json ) && isset( $json['provider'] ) && is_string( $json['provider'] ) ) {
			$provider = $json['provider'];
		} elseif ( null !== $request->get_param( 'provider' ) ) {
			$provider = (string) $request->get_param( 'provider' );
		}

		$provider  = sanitize_key( $provider );
		$providers = PressPrimer_Quiz_AI_Service::get_providers();

		if ( '' === $provider || ! isset( $providers[ $provider ] ) ) {
			$provider = PressPrimer_Quiz_AI_Service::get_active_provider();
		}

		return $provider;
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
		$type  = isset( $data['type'] ) ? sanitize_key( $data['type'] ) : 'results';

		if ( empty( $email ) || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please provide a valid email address.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		// Send test email based on type.
		$sent = PressPrimer_Quiz_Email_Service::send_test_email( $email, $type );

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

	/**
	 * Get database schema health.
	 *
	 * Runs a presence-only schema check and returns the report plus the rolling
	 * check/repair log tail for the Status tab.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_schema_status( $request ) {
		if ( ! class_exists( 'PressPrimer_Quiz_Schema_Verifier' ) ) {
			return new WP_Error( 'schema_verifier_unavailable', __( 'Schema verifier is unavailable.', 'pressprimer-quiz' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response(
			[
				'report' => PressPrimer_Quiz_Schema_Verifier::check(),
				'log'    => PressPrimer_Quiz_Schema_Verifier::get_log(),
			],
			200
		);
	}

	/**
	 * Repair database schema findings.
	 *
	 * Repairs missing tables/columns from the canonical schema map (manual
	 * action, so the auto-repair attempt cap is overridden), then returns the
	 * repair outcome alongside a fresh check report and log so the Status tab
	 * re-renders from a single request.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function repair_schema_status( $request ) {
		if ( ! class_exists( 'PressPrimer_Quiz_Schema_Verifier' ) ) {
			return new WP_Error( 'schema_verifier_unavailable', __( 'Schema verifier is unavailable.', 'pressprimer-quiz' ), [ 'status' => 500 ] );
		}

		$result = PressPrimer_Quiz_Schema_Verifier::repair_findings();

		return new WP_REST_Response(
			[
				'result' => $result,
				'report' => PressPrimer_Quiz_Schema_Verifier::check(),
				'log'    => PressPrimer_Quiz_Schema_Verifier::get_log(),
			],
			200
		);
	}

	/**
	 * Create and designate a front-end dashboard page.
	 *
	 * Publishes a page containing the dashboard block and stores its ID in the
	 * pressprimer_quiz_dashboard_page_id option. Requires the publish_pages
	 * capability in addition to the route's manage_settings permission.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function create_dashboard_page( $request ) {
		if ( ! current_user_can( 'publish_pages' ) ) {
			return new WP_Error( 'cannot_publish_pages', __( 'You do not have permission to create pages.', 'pressprimer-quiz' ), [ 'status' => 403 ] );
		}

		/**
		 * Filters the title of the auto-created dashboard page.
		 *
		 * @since 3.0.0
		 *
		 * @param string $title Default page title.
		 */
		$title = apply_filters( 'pressprimer_quiz_dashboard_page_title', __( 'Dashboard', 'pressprimer-quiz' ) );

		/**
		 * Filters the slug of the auto-created dashboard page.
		 *
		 * @since 3.0.0
		 *
		 * @param string $slug Default page slug.
		 */
		$slug = apply_filters( 'pressprimer_quiz_dashboard_page_slug', 'dashboard' );

		$page_id = wp_insert_post(
			[
				'post_title'   => $title,
				'post_name'    => sanitize_title( $slug ),
				'post_content' => '<!-- wp:pressprimer-quiz/dashboard /-->',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			],
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return new WP_Error( 'create_failed', $page_id->get_error_message(), [ 'status' => 500 ] );
		}

		update_option( 'pressprimer_quiz_dashboard_page_id', (int) $page_id );

		return new WP_REST_Response(
			[
				'success'   => true,
				'pageId'    => (int) $page_id,
				'pageTitle' => $title,
				'editUrl'   => get_edit_post_link( $page_id, 'raw' ),
				'viewUrl'   => get_permalink( $page_id ),
			],
			200
		);
	}

	/**
	 * Get the current user's own quiz attempts (paginated).
	 *
	 * Hard-scoped to the authenticated user. A user_id parameter is rejected
	 * (400) so cross-user scope violations are impossible rather than merely
	 * checked (SR-002); cross-user listings are addon reporting territory.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function get_my_attempts( $request ) {
		// SR-002: never accept a user_id; the user is the session, full stop.
		if ( null !== $request->get_param( 'user_id' ) ) {
			return new WP_Error( 'user_id_not_allowed', __( 'The user_id parameter is not allowed on this endpoint.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		$user_id = get_current_user_id();

		// Pagination.
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = absint( $request->get_param( 'per_page' ) );
		$per_page = $per_page > 0 ? min( 50, $per_page ) : 20;

		// Optional quiz filter.
		$quiz_id = absint( $request->get_param( 'quiz_id' ) );

		// Optional status filter: completed | in_progress -> DB status.
		$status_param = $request->get_param( 'status' );
		$db_status    = '';
		if ( null !== $status_param && '' !== $status_param ) {
			$status_map = [
				'completed'   => 'submitted',
				'in_progress' => 'in_progress',
			];
			if ( ! isset( $status_map[ $status_param ] ) ) {
				return new WP_Error( 'invalid_status', __( 'Invalid status filter.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
			}
			$db_status = $status_map[ $status_param ];
		}

		// orderby whitelist (SECURITY.md: validate field against a whitelist).
		$orderby_param = $request->get_param( 'orderby' );
		if ( null === $orderby_param || '' === $orderby_param ) {
			$orderby_param = 'date';
		}
		$orderby_map = [
			'date'  => 'started_at',
			'score' => 'score_percent',
		];
		if ( ! isset( $orderby_map[ $orderby_param ] ) ) {
			return new WP_Error( 'invalid_orderby', __( 'Invalid orderby value.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}
		$order_column = $orderby_map[ $orderby_param ];

		// Direction defaults to descending; invalid values fall back to it.
		$is_asc = 'asc' === strtolower( (string) $request->get_param( 'order' ) );

		global $wpdb;
		$table = $wpdb->prefix . 'ppq_attempts';

		// WHERE fragments are prepared individually then composed — the same
		// pattern the my-attempts shortcode uses. user_id is always bound here.
		$where = [ $wpdb->prepare( 'user_id = %d', $user_id ) ];
		if ( $quiz_id ) {
			$where[] = $wpdb->prepare( 'quiz_id = %d', $quiz_id );
		}
		if ( '' !== $db_status ) {
			$where[] = $wpdb->prepare( 'status = %s', $db_status );
		}
		$where_clause = implode( ' AND ', $where );

		// Total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_clause is composed from prepared fragments; user history is not cacheable.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}" );

		$total_pages = (int) ceil( $total / $per_page );
		$offset      = ( $page - 1 ) * $per_page;

		// Data query. ORDER direction via hardcoded branches and the column via
		// %i (SECURITY.md), never interpolated; the composed WHERE carries
		// already-prepared values.
		if ( $is_asc ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_clause is composed from prepared fragments; user history is not cacheable.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY %i ASC, id ASC LIMIT %d OFFSET %d", $order_column, $per_page, $offset ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_clause is composed from prepared fragments; user history is not cacheable.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY %i DESC, id DESC LIMIT %d OFFSET %d", $order_column, $per_page, $offset ) );
		}

		$items = $this->format_my_attempts( is_array( $rows ) ? $rows : [] );

		return new WP_REST_Response(
			[
				'items'       => $items,
				'total'       => $total,
				'total_pages' => $total_pages,
				'quizzes'     => $this->get_attempted_quizzes( $user_id ),
			],
			200
		);
	}

	/**
	 * Get the distinct quizzes a user has attempted (for the My Results filter).
	 *
	 * @since 3.0.0
	 *
	 * @param int $user_id User ID.
	 * @return array List of [ id, title ].
	 */
	private function get_attempted_quizzes( $user_id ) {
		global $wpdb;

		$attempts = $wpdb->prefix . 'ppq_attempts';
		$quizzes  = $wpdb->prefix . 'ppq_quizzes';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names from prefix; user_id bound; filter options for the user's own attempts are not cacheable.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT a.quiz_id AS id, q.title AS title FROM {$attempts} a LEFT JOIN {$quizzes} q ON a.quiz_id = q.id WHERE a.user_id = %d ORDER BY q.title ASC", $user_id ) );

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[] = [
				'id'    => (int) $row->id,
				'title' => $row->title ? $row->title : '',
			];
		}

		return $out;
	}

	/**
	 * Format attempt rows into My Results response items.
	 *
	 * Resolves quiz titles in one query (no N+1) and builds the per-attempt
	 * results/resume URLs from the stored source page.
	 *
	 * @since 3.0.0
	 *
	 * @param array $rows Attempt rows.
	 * @return array Response items.
	 */
	private function format_my_attempts( $rows ) {
		global $wpdb;

		// Resolve quiz titles for this page's attempts in a single query.
		$quiz_ids = [];
		foreach ( $rows as $row ) {
			$quiz_ids[ (int) $row->quiz_id ] = true;
		}
		$quiz_ids = array_keys( $quiz_ids );

		$quiz_titles = [];
		if ( ! empty( $quiz_ids ) ) {
			$placeholders  = implode( ', ', array_fill( 0, count( $quiz_ids ), '%d' ) );
			$quizzes_table = $wpdb->prefix . 'ppq_quizzes';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a literal %d list bound via spread $quiz_ids.
			$title_rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, title FROM {$quizzes_table} WHERE id IN ($placeholders)", ...$quiz_ids ) );
			foreach ( $title_rows as $title_row ) {
				$quiz_titles[ (int) $title_row->id ] = $title_row->title;
			}
		}

		$datetime_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$items           = [];

		foreach ( $rows as $row ) {
			$db_status = $row->status;
			$base      = ! empty( $row->source_url ) ? $row->source_url : home_url( '/' );
			$action    = add_query_arg( 'attempt', (int) $row->id, $base );

			$items[] = [
				'attempt_id'    => (int) $row->id,
				'quiz_id'       => (int) $row->quiz_id,
				'quiz_title'    => isset( $quiz_titles[ (int) $row->quiz_id ] ) ? $quiz_titles[ (int) $row->quiz_id ] : '',
				'started_at'    => $row->started_at ? wp_date( $datetime_format, strtotime( $row->started_at ) ) : null,
				'completed_at'  => $row->finished_at ? wp_date( $datetime_format, strtotime( $row->finished_at ) ) : null,
				'score_percent' => ( null !== $row->score_percent ) ? (float) $row->score_percent : null,
				'passed'        => ( null !== $row->passed ) ? (bool) (int) $row->passed : null,
				'status'        => ( 'submitted' === $db_status ) ? 'completed' : $db_status,
				'results_url'   => ( 'submitted' === $db_status ) ? $action : '',
				'resume_url'    => ( 'in_progress' === $db_status ) ? $action : '',
			];
		}

		return $items;
	}

	/**
	 * List quiz settings templates (presets + saved).
	 *
	 * Merges built-in/addon presets (registered behind the
	 * pressprimer_quiz_settings_template_presets filter; see feature 003,
	 * Prompt 3.3) with saved template rows. Each item carries a `source` of
	 * 'preset' or 'template'. Open to any quiz-editing user so they can apply.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with the merged template list.
	 */
	public function get_quiz_templates( $request ) {
		$items = $this->get_template_presets();

		$templates = PressPrimer_Quiz_Quiz_Template::find(
			array(
				'order_by' => 'updated_at',
				'order'    => 'DESC',
			)
		);

		foreach ( $templates as $template ) {
			$items[] = $this->format_quiz_template( $template );
		}

		// Resolve the site default, auto-clearing a stale (deleted-target) value
		// so the selector and the "cleared" notice stay accurate (FR-006).
		$raw_default = PressPrimer_Quiz_Default_Template::get_raw();
		$default     = PressPrimer_Quiz_Default_Template::get_validated();

		return new WP_REST_Response(
			array(
				'items'           => $items,
				'default'         => $default,
				'default_cleared' => ( '' !== $raw_default && '' === $default ),
			),
			200
		);
	}

	/**
	 * Set (or clear) the default settings template for new quizzes.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response with the stored value.
	 */
	public function set_default_quiz_template( $request ) {
		$data  = $request->get_json_params();
		$value = is_array( $data ) && isset( $data['value'] ) && is_string( $data['value'] ) ? $data['value'] : '';

		$stored = PressPrimer_Quiz_Default_Template::set_value( $value );

		return new WP_REST_Response(
			array(
				'success' => true,
				'default' => $stored,
			),
			200
		);
	}

	/**
	 * Create a quiz settings template.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function create_quiz_template( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error(
				'rest_template_name_required',
				__( 'Template name is required.', 'pressprimer-quiz' ),
				array( 'status' => 400 )
			);
		}

		$settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();

		$template_id = PressPrimer_Quiz_Quiz_Template::create(
			array(
				'name'        => $name,
				'description' => isset( $data['description'] ) ? $data['description'] : '',
				'settings'    => $settings,
			)
		);

		if ( is_wp_error( $template_id ) ) {
			$status = 'ppq_template_too_large' === $template_id->get_error_code() ? 400 : 500;
			return new WP_Error( $template_id->get_error_code(), $template_id->get_error_message(), array( 'status' => $status ) );
		}

		$response = array(
			'id'                => absint( $template_id ),
			'duplicate_warning' => $this->template_name_in_use( $name, $template_id ),
		);

		$template = PressPrimer_Quiz_Quiz_Template::get( $template_id );
		if ( $template ) {
			$response['template'] = $this->format_quiz_template( $template );
		}

		return new WP_REST_Response( $response, 201 );
	}

	/**
	 * Update a quiz settings template (rename, description, overwrite payload).
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function update_quiz_template( $request ) {
		$template_id = absint( $request['id'] );
		$data        = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$template = PressPrimer_Quiz_Quiz_Template::get( $template_id );
		if ( ! $template ) {
			return new WP_Error( 'not_found', __( 'Template not found.', 'pressprimer-quiz' ), array( 'status' => 404 ) );
		}

		if ( isset( $data['name'] ) ) {
			$name = sanitize_text_field( $data['name'] );
			if ( '' === $name ) {
				return new WP_Error(
					'rest_template_name_required',
					__( 'Template name is required.', 'pressprimer-quiz' ),
					array( 'status' => 400 )
				);
			}
			$template->name = $name;
		}

		if ( array_key_exists( 'description', $data ) ) {
			$template->description = $data['description'];
		}

		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$template->set_settings( $data['settings'] );
		}

		$result = $template->save();
		if ( is_wp_error( $result ) ) {
			$status = 'ppq_template_too_large' === $result->get_error_code() ? 400 : 500;
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => $status ) );
		}

		$template = PressPrimer_Quiz_Quiz_Template::get( $template_id );

		$response = array(
			'success'           => true,
			'duplicate_warning' => $template ? $this->template_name_in_use( $template->name, $template_id ) : false,
		);
		if ( $template ) {
			$response['template'] = $this->format_quiz_template( $template );
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Delete a quiz settings template.
	 *
	 * @since 3.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function delete_quiz_template( $request ) {
		$template_id = absint( $request['id'] );

		$template = PressPrimer_Quiz_Quiz_Template::get( $template_id );
		if ( ! $template ) {
			return new WP_Error( 'not_found', __( 'Template not found.', 'pressprimer-quiz' ), array( 'status' => 404 ) );
		}

		$result = $template->delete();
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'delete_failed', $result->get_error_message(), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Get normalized preset entries for the template list.
	 *
	 * Presets are code/filter only (never rows). Each preset's settings pass
	 * through the canonical Quiz sanitizers so a bad filter registration cannot
	 * inject invalid values into the apply flow.
	 *
	 * @since 3.0.0
	 *
	 * @return array[] Normalized preset entries (source = 'preset').
	 */
	private function get_template_presets() {
		/**
		 * Filters the built-in quiz settings template presets.
		 *
		 * Each entry is keyed by a preset id and is an array with: label,
		 * description, settings (a map of quiz settings keys), and optional
		 * reminders (strings shown after apply). Built-in presets are registered
		 * in feature 003, Prompt 3.3; addons may add their own.
		 *
		 * @since 3.0.0
		 *
		 * @param array $presets Map of preset id => preset definition.
		 */
		$presets = apply_filters( 'pressprimer_quiz_settings_template_presets', array() );

		$normalized = array();

		if ( ! is_array( $presets ) ) {
			return $normalized;
		}

		foreach ( $presets as $key => $preset ) {
			if ( ! is_string( $key ) || '' === $key || ! is_array( $preset ) ) {
				continue;
			}

			$settings = isset( $preset['settings'] ) && is_array( $preset['settings'] )
				? PressPrimer_Quiz_Quiz::sanitize_settings( $preset['settings'] )
				: array();

			$reminders = array();
			if ( isset( $preset['reminders'] ) && is_array( $preset['reminders'] ) ) {
				foreach ( $preset['reminders'] as $reminder ) {
					$reminders[] = sanitize_text_field( $reminder );
				}
			}

			$normalized[] = array(
				'id'          => sanitize_key( $key ),
				'source'      => 'preset',
				'name'        => isset( $preset['label'] ) ? sanitize_text_field( $preset['label'] ) : sanitize_key( $key ),
				'description' => isset( $preset['description'] ) ? sanitize_text_field( $preset['description'] ) : '',
				'settings'    => $settings,
				'reminders'   => $reminders,
			);
		}

		return $normalized;
	}

	/**
	 * Format a saved template row for the REST response.
	 *
	 * @since 3.0.0
	 *
	 * @param PressPrimer_Quiz_Quiz_Template $template Template instance.
	 * @return array Response-ready template entry (source = 'template').
	 */
	private function format_quiz_template( $template ) {
		$author = get_userdata( absint( $template->created_by ) );

		return array(
			'id'          => absint( $template->id ),
			'source'      => 'template',
			'name'        => $template->name,
			'description' => (string) $template->description,
			'settings'    => $template->get_settings(),
			'created_by'  => absint( $template->created_by ),
			'author_name' => $author ? $author->display_name : '',
			'is_mine'     => absint( $template->created_by ) === get_current_user_id(),
			'created_at'  => $template->created_at,
			'updated_at'  => $template->updated_at,
		);
	}

	/**
	 * Whether a template name is already used by a different template.
	 *
	 * Names are labels, not identity, so duplicates are allowed; this only
	 * powers the soft warning flag in the create/update response.
	 *
	 * @since 3.0.0
	 *
	 * @param string $name       Candidate name.
	 * @param int    $exclude_id Template id to exclude (the one being saved).
	 * @return bool True if another template already uses the name.
	 */
	private function template_name_in_use( $name, $exclude_id = 0 ) {
		$matches = PressPrimer_Quiz_Quiz_Template::find(
			array( 'where' => array( 'name' => $name ) )
		);

		foreach ( $matches as $match ) {
			if ( absint( $match->id ) !== absint( $exclude_id ) ) {
				return true;
			}
		}

		return false;
	}
}
