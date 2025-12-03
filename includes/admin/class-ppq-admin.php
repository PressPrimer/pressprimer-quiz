<?php
/**
 * Admin class
 *
 * Handles WordPress admin interface for PressPrimer Quiz.
 *
 * @package PressPrimer_Quiz
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class
 *
 * Manages admin menus, pages, and assets for the plugin.
 *
 * @since 1.0.0
 */
class PPQ_Admin {

	/**
	 * Initialize admin functionality
	 *
	 * Hooks into WordPress admin to add menus and enqueue assets.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_ppq_search_questions', [ $this, 'ajax_search_questions' ] );
		add_action( 'wp_ajax_ppq_get_recent_questions', [ $this, 'ajax_get_recent_questions' ] );
		add_action( 'wp_ajax_ppq_remove_question_from_bank', [ $this, 'ajax_remove_question_from_bank' ] );

		// Initialize settings
		if ( class_exists( 'PPQ_Admin_Settings' ) ) {
			$settings = new PPQ_Admin_Settings();
			$settings->init();
		}

		// Initialize questions admin
		if ( class_exists( 'PPQ_Admin_Questions' ) ) {
			$questions = new PPQ_Admin_Questions();
			$questions->init();
		}

		// Initialize banks admin
		if ( class_exists( 'PPQ_Admin_Banks' ) ) {
			$banks = new PPQ_Admin_Banks();
			$banks->init();
		}

		// Initialize categories admin
		if ( class_exists( 'PPQ_Admin_Categories' ) ) {
			$categories = new PPQ_Admin_Categories();
			$categories->init();
		}

		// Initialize quizzes admin
		if ( class_exists( 'PPQ_Admin_Quizzes' ) ) {
			$quizzes = new PPQ_Admin_Quizzes();
			$quizzes->init();
		}

		// Initialize AI generation admin
		if ( class_exists( 'PPQ_Admin_AI_Generation' ) ) {
			$ai_generation = new PPQ_Admin_AI_Generation();
			$ai_generation->init();
		}
	}

	/**
	 * Register admin menus
	 *
	 * Creates the main PressPrimer Quiz menu and all submenus.
	 *
	 * @since 1.0.0
	 */
	public function register_menus() {
		// Main menu page
		add_menu_page(
			__( 'PressPrimer Quiz', 'pressprimer-quiz' ),           // Page title
			__( 'PressPrimer Quiz', 'pressprimer-quiz' ),           // Menu title
			'ppq_manage_own',                                        // Capability
			'ppq',                                                   // Menu slug
			[ $this, 'render_dashboard' ],                          // Callback
			'dashicons-welcome-learn-more',                          // Icon
			30                                                       // Position (after Comments)
		);

		// Dashboard submenu (replaces main menu link)
		add_submenu_page(
			'ppq',                                                   // Parent slug
			__( 'Dashboard', 'pressprimer-quiz' ),                  // Page title
			__( 'Dashboard', 'pressprimer-quiz' ),                  // Menu title
			'ppq_manage_own',                                        // Capability
			'ppq',                                                   // Menu slug (same as parent)
			[ $this, 'render_dashboard' ]                           // Callback
		);

		// Quizzes submenu
		add_submenu_page(
			'ppq',
			__( 'Quizzes', 'pressprimer-quiz' ),
			__( 'Quizzes', 'pressprimer-quiz' ),
			'ppq_manage_own',
			'ppq-quizzes',
			[ $this, 'render_quizzes' ]
		);

		// Questions submenu
		add_submenu_page(
			'ppq',
			__( 'Questions', 'pressprimer-quiz' ),
			__( 'Questions', 'pressprimer-quiz' ),
			'ppq_manage_own',
			'ppq-questions',
			[ $this, 'render_questions' ]
		);

		// Question Banks submenu
		add_submenu_page(
			'ppq',
			__( 'Question Banks', 'pressprimer-quiz' ),
			__( 'Question Banks', 'pressprimer-quiz' ),
			'ppq_manage_own',
			'ppq-banks',
			[ $this, 'render_banks' ]
		);

		// Categories submenu
		add_submenu_page(
			'ppq',
			__( 'Categories', 'pressprimer-quiz' ),
			__( 'Categories', 'pressprimer-quiz' ),
			'ppq_manage_own',
			'ppq-categories',
			[ $this, 'render_categories' ]
		);

		// Tags submenu
		add_submenu_page(
			'ppq',
			__( 'Tags', 'pressprimer-quiz' ),
			__( 'Tags', 'pressprimer-quiz' ),
			'ppq_manage_own',
			'ppq-tags',
			[ $this, 'render_tags' ]
		);

		// Reports submenu
		add_submenu_page(
			'ppq',
			__( 'Reports', 'pressprimer-quiz' ),
			__( 'Reports', 'pressprimer-quiz' ),
			'ppq_view_results_own',                                 // Different capability
			'ppq-reports',
			[ $this, 'render_reports' ]
		);

		// Settings submenu
		add_submenu_page(
			'ppq',
			__( 'Settings', 'pressprimer-quiz' ),
			__( 'Settings', 'pressprimer-quiz' ),
			'ppq_manage_settings',                                  // Admin only
			'ppq-settings',
			[ $this, 'render_settings' ]
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * Loads CSS and JavaScript files for PressPrimer Quiz admin pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Only load on PPQ admin pages
		if ( false === strpos( $hook, 'ppq' ) ) {
			return;
		}

		// Enqueue admin CSS
		wp_enqueue_style(
			'ppq-admin',
			PPQ_PLUGIN_URL . 'assets/css/admin.css',
			[],
			PPQ_VERSION
		);

		// Enqueue admin JavaScript
		wp_enqueue_script(
			'ppq-admin',
			PPQ_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			PPQ_VERSION,
			true
		);

		// Enqueue question builder on question pages
		if ( isset( $_GET['page'] ) && 'ppq-questions' === $_GET['page'] ) {
			// Enqueue jQuery UI for sortable
			wp_enqueue_script( 'jquery-ui-sortable' );

			// Enqueue question builder script
			wp_enqueue_script(
				'ppq-question-builder',
				PPQ_PLUGIN_URL . 'assets/js/question-builder.js',
				[ 'jquery', 'jquery-ui-sortable' ],
				PPQ_VERSION,
				true
			);
		}

		// Enqueue Dashboard React app on main dashboard page
		if ( 'toplevel_page_ppq' === $hook ) {
			$this->enqueue_dashboard_assets();
		}

		// Enqueue Reports React app on reports page
		if ( 'pressprimer-quiz_page_ppq-reports' === $hook ) {
			$this->enqueue_reports_assets();
		}

		// Enqueue Onboarding React app on all PPQ pages
		$this->enqueue_onboarding_assets();

		// Localize script with data
		wp_localize_script(
			'ppq-admin',
			'ppqAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ppq_admin_nonce' ),
				'strings' => [
					'confirmDelete'          => __( 'Are you sure you want to delete this item?', 'pressprimer-quiz' ),
					'confirmDeleteTitle'     => __( 'Delete Item', 'pressprimer-quiz' ),
					'confirmRemoveFromBank'  => __( 'Remove this question from the bank?', 'pressprimer-quiz' ),
					'removeFromBankTitle'    => __( 'Remove from Bank', 'pressprimer-quiz' ),
					'error'                  => __( 'An error occurred. Please try again.', 'pressprimer-quiz' ),
					'saved'                  => __( 'Changes saved successfully.', 'pressprimer-quiz' ),
					'delete'                 => __( 'Delete', 'pressprimer-quiz' ),
					'remove'                 => __( 'Remove', 'pressprimer-quiz' ),
					'cancel'                 => __( 'Cancel', 'pressprimer-quiz' ),
					'ok'                     => __( 'OK', 'pressprimer-quiz' ),
					'yes'                    => __( 'Yes', 'pressprimer-quiz' ),
					'no'                     => __( 'No', 'pressprimer-quiz' ),
					'confirmTitle'           => __( 'Confirm', 'pressprimer-quiz' ),
				],
			]
		);
	}

	/**
	 * Enqueue Dashboard React app assets
	 *
	 * @since 1.0.0
	 */
	private function enqueue_dashboard_assets() {
		$asset_file = PPQ_PLUGIN_PATH . 'build/dashboard.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Enqueue the dashboard script
		wp_enqueue_script(
			'ppq-dashboard',
			PPQ_PLUGIN_URL . 'build/dashboard.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue the dashboard styles
		wp_enqueue_style(
			'ppq-dashboard',
			PPQ_PLUGIN_URL . 'build/style-dashboard.css',
			[],
			$asset['version']
		);

		// Localize script with dashboard data
		wp_localize_script(
			'ppq-dashboard',
			'ppqDashboardData',
			[
				'urls' => [
					'create_quiz'   => admin_url( 'admin.php?page=ppq-quizzes&action=new' ),
					'add_question'  => admin_url( 'admin.php?page=ppq-questions&action=new' ),
					'create_bank'   => admin_url( 'admin.php?page=ppq-banks&action=new' ),
					'reports'       => admin_url( 'admin.php?page=ppq-reports' ),
				],
			]
		);
	}

	/**
	 * Enqueue Reports React app assets
	 *
	 * @since 1.0.0
	 */
	private function enqueue_reports_assets() {
		$asset_file = PPQ_PLUGIN_PATH . 'build/reports.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Enqueue the reports script
		wp_enqueue_script(
			'ppq-reports',
			PPQ_PLUGIN_URL . 'build/reports.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue the reports styles
		wp_enqueue_style(
			'ppq-reports',
			PPQ_PLUGIN_URL . 'build/style-reports.css',
			[],
			$asset['version']
		);

		// Localize script with reports data
		wp_localize_script(
			'ppq-reports',
			'ppqReportsData',
			[
				'resultsUrl' => home_url( '/quiz-results/' ),
			]
		);
	}

	/**
	 * Enqueue Onboarding React app assets
	 *
	 * @since 1.0.0
	 */
	private function enqueue_onboarding_assets() {
		$asset_file = PPQ_PLUGIN_PATH . 'build/onboarding.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		// Get the onboarding instance to check state
		if ( ! class_exists( 'PPQ_Onboarding' ) ) {
			return;
		}

		$onboarding = PPQ_Onboarding::get_instance();

		$asset = require $asset_file;

		// Enqueue the onboarding script
		wp_enqueue_script(
			'ppq-onboarding',
			PPQ_PLUGIN_URL . 'build/onboarding.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue the onboarding styles
		wp_enqueue_style(
			'ppq-onboarding',
			PPQ_PLUGIN_URL . 'build/style-onboarding.css',
			[],
			$asset['version']
		);

		// Localize script with onboarding data
		wp_localize_script(
			'ppq-onboarding',
			'ppqOnboardingData',
			$onboarding->get_js_data()
		);
	}

	/**
	 * Render dashboard page
	 *
	 * Displays the main dashboard with overview and statistics.
	 *
	 * @since 1.0.0
	 */
	public function render_dashboard() {
		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-quiz' ),
				esc_html__( 'Permission Denied', 'pressprimer-quiz' ),
				[ 'response' => 403 ]
			);
		}

		// Render React app container
		echo '<div id="ppq-dashboard-root" class="ppq-admin-react-root"></div>';
	}

	/**
	 * Render quizzes page
	 *
	 * Displays list and management interface for quizzes.
	 *
	 * @since 1.0.0
	 */
	public function render_quizzes() {
		if ( class_exists( 'PPQ_Admin_Quizzes' ) ) {
			$quizzes_admin = new PPQ_Admin_Quizzes();
			$quizzes_admin->render();
		}
	}

	/**
	 * Render questions page
	 *
	 * Displays list and management interface for questions.
	 *
	 * @since 1.0.0
	 */
	public function render_questions() {
		if ( class_exists( 'PPQ_Admin_Questions' ) ) {
			$questions_admin = new PPQ_Admin_Questions();
			$questions_admin->render();
		}
	}

	/**
	 * Render question banks page
	 *
	 * Displays list and management interface for question banks.
	 *
	 * @since 1.0.0
	 */
	public function render_banks() {
		if ( class_exists( 'PPQ_Admin_Banks' ) ) {
			$banks_admin = new PPQ_Admin_Banks();
			$banks_admin->render();
		}
	}

	/**
	 * Render categories page
	 *
	 * Displays list and management interface for categories.
	 *
	 * @since 1.0.0
	 */
	public function render_categories() {
		if ( class_exists( 'PPQ_Admin_Categories' ) ) {
			$categories_admin = new PPQ_Admin_Categories();
			$categories_admin->render();
		}
	}

	/**
	 * Render tags page
	 *
	 * Displays list and management interface for tags.
	 *
	 * @since 1.0.0
	 */
	public function render_tags() {
		if ( class_exists( 'PPQ_Admin_Categories' ) ) {
			$categories_admin = new PPQ_Admin_Categories();
			$categories_admin->render_tags();
		}
	}

	/**
	 * Render reports page
	 *
	 * Displays quiz results and analytics.
	 *
	 * @since 1.0.0
	 */
	public function render_reports() {
		// Check capability
		if ( ! current_user_can( 'ppq_view_results_own' ) && ! current_user_can( 'ppq_view_results_all' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-quiz' ),
				esc_html__( 'Permission Denied', 'pressprimer-quiz' ),
				[ 'response' => 403 ]
			);
		}

		// Render React app container
		echo '<div id="ppq-reports-root" class="ppq-admin-react-root"></div>';
	}

	/**
	 * Render settings page
	 *
	 * Displays plugin settings and configuration.
	 *
	 * @since 1.0.0
	 */
	public function render_settings() {
		if ( class_exists( 'PPQ_Admin_Settings' ) ) {
			$settings = new PPQ_Admin_Settings();
			$settings->render_page();
		}
	}

	/**
	 * AJAX handler for question search
	 *
	 * Searches questions for adding to banks.
	 *
	 * @since 1.0.0
	 */
	public function ajax_search_questions() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ppq_search_questions' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission.', 'pressprimer-quiz' ) ] );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$bank_id = isset( $_POST['bank_id'] ) ? absint( $_POST['bank_id'] ) : 0;
		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : '';
		$difficulty = isset( $_POST['difficulty'] ) ? sanitize_key( $_POST['difficulty'] ) : '';
		$category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
		$tag_id = isset( $_POST['tag_id'] ) ? absint( $_POST['tag_id'] ) : 0;

		global $wpdb;

		// Build search query
		$questions_table = $wpdb->prefix . 'ppq_questions';
		$revisions_table = $wpdb->prefix . 'ppq_question_revisions';
		$taxonomy_table = $wpdb->prefix . 'ppq_question_taxonomy';

		// Start with base query
		$sql = "SELECT DISTINCT q.id, q.type, q.difficulty_author, r.stem
				FROM {$questions_table} q
				INNER JOIN {$revisions_table} r ON q.current_revision_id = r.id";

		// Add taxonomy join if filtering by category or tag
		if ( $category_id > 0 || $tag_id > 0 ) {
			$sql .= " INNER JOIN {$taxonomy_table} qt ON q.id = qt.question_id";
		}

		$sql .= " WHERE q.deleted_at IS NULL";

		$params = [];

		// Search filter
		if ( ! empty( $search ) ) {
			$search_term = '%' . $wpdb->esc_like( $search ) . '%';
			$sql .= ' AND r.stem LIKE %s';
			$params[] = $search_term;
		}

		// Type filter
		if ( ! empty( $type ) ) {
			$sql .= ' AND q.type = %s';
			$params[] = $type;
		}

		// Difficulty filter
		if ( ! empty( $difficulty ) ) {
			$sql .= ' AND q.difficulty_author = %s';
			$params[] = $difficulty;
		}

		// Category filter
		if ( $category_id > 0 ) {
			$sql .= ' AND qt.category_id = %d';
			$params[] = $category_id;
		}

		// Tag filter
		if ( $tag_id > 0 ) {
			$sql .= ' AND qt.category_id = %d';
			$params[] = $tag_id;
		}

		// Filter by author if not admin
		if ( ! current_user_can( 'ppq_manage_all' ) ) {
			$sql .= ' AND q.author_id = %d';
			$params[] = get_current_user_id();
		}

		// Exclude questions already in this bank
		if ( $bank_id > 0 ) {
			$bank_questions_table = $wpdb->prefix . 'ppq_bank_questions';
			$sql .= " AND q.id NOT IN (SELECT question_id FROM {$bank_questions_table} WHERE bank_id = %d)";
			$params[] = $bank_id;
		}

		$sql .= ' LIMIT 50';

		// Execute query
		if ( ! empty( $params ) ) {
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		} else {
			$results = $wpdb->get_results( $sql );
		}

		$questions = [];
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$questions[] = [
					'id'           => absint( $row->id ),
					'stem_preview' => wp_trim_words( wp_strip_all_tags( $row->stem ), 15 ),
					'type'         => $row->type,
					'difficulty'   => $row->difficulty_author,
				];
			}
		}

		wp_send_json_success( [ 'questions' => $questions ] );
	}

	/**
	 * AJAX handler for getting recent questions
	 *
	 * Gets recent questions for display in the bank detail page.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_recent_questions() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ppq_get_recent_questions' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission.', 'pressprimer-quiz' ) ] );
		}

		$bank_id = isset( $_POST['bank_id'] ) ? absint( $_POST['bank_id'] ) : 0;
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = 10;
		$offset = ( $page - 1 ) * $per_page;

		global $wpdb;

		$questions_table = $wpdb->prefix . 'ppq_questions';
		$revisions_table = $wpdb->prefix . 'ppq_question_revisions';
		$bank_questions_table = $wpdb->prefix . 'ppq_bank_questions';

		// Build query for recent questions
		$sql = "SELECT q.id, q.type, q.difficulty_author, r.stem
				FROM {$questions_table} q
				INNER JOIN {$revisions_table} r ON q.current_revision_id = r.id
				WHERE q.deleted_at IS NULL
				AND q.author_id = %d";

		$params = [ get_current_user_id() ];

		// Exclude questions already in this bank
		if ( $bank_id > 0 ) {
			$sql .= " AND q.id NOT IN (SELECT question_id FROM {$bank_questions_table} WHERE bank_id = %d)";
			$params[] = $bank_id;
		}

		$sql .= ' ORDER BY q.created_at DESC';

		// Get total count (use same WHERE conditions)
		$count_sql = "SELECT COUNT(DISTINCT q.id)
				FROM {$questions_table} q
				WHERE q.deleted_at IS NULL
				AND q.author_id = %d";

		$count_params = [ get_current_user_id() ];

		if ( $bank_id > 0 ) {
			$count_sql .= " AND q.id NOT IN (SELECT question_id FROM {$bank_questions_table} WHERE bank_id = %d)";
			$count_params[] = $bank_id;
		}

		$total_items = absint( $wpdb->get_var( $wpdb->prepare( $count_sql, $count_params ) ) );

		// Add pagination
		$sql .= ' LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = $offset;

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		$questions = [];
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				// Type labels
				$type_labels = [
					'mc' => __( 'Multiple Choice', 'pressprimer-quiz' ),
					'ma' => __( 'Multiple Answer', 'pressprimer-quiz' ),
					'tf' => __( 'True/False', 'pressprimer-quiz' ),
				];
				$type_label = isset( $type_labels[ $row->type ] ) ? $type_labels[ $row->type ] : $row->type;

				// Difficulty labels
				$difficulty_labels = [
					'beginner' => __( 'Beginner', 'pressprimer-quiz' ),
					'intermediate' => __( 'Intermediate', 'pressprimer-quiz' ),
					'advanced' => __( 'Advanced', 'pressprimer-quiz' ),
					'expert' => __( 'Expert', 'pressprimer-quiz' ),
				];
				$difficulty_label = isset( $difficulty_labels[ $row->difficulty_author ] ) ? $difficulty_labels[ $row->difficulty_author ] : $row->difficulty_author;

				// Get categories for this question
				$categories = [];
				$category_names = [];
				if ( class_exists( 'PPQ_Question' ) ) {
					$question = PPQ_Question::get( $row->id );
					if ( $question ) {
						$question_categories = $question->get_categories();
						foreach ( $question_categories as $cat ) {
							if ( 'category' === $cat->taxonomy ) {
								$category_names[] = $cat->name;
							}
						}
					}
				}

				$questions[] = [
					'id'           => absint( $row->id ),
					'stem_preview' => wp_trim_words( wp_strip_all_tags( $row->stem ), 20 ),
					'type'         => $row->type,
					'type_label'   => $type_label,
					'difficulty'   => $row->difficulty_author,
					'difficulty_label' => $difficulty_label,
					'category'     => ! empty( $category_names ) ? implode( ', ', $category_names ) : __( 'None', 'pressprimer-quiz' ),
				];
			}
		}

		$total_pages = ceil( $total_items / $per_page );

		wp_send_json_success( [
			'questions' => $questions,
			'total_items' => $total_items,
			'total_pages' => $total_pages,
			'current_page' => $page,
		] );
	}

	/**
	 * AJAX handler for removing a question from a bank
	 *
	 * @since 1.0.0
	 */
	public function ajax_remove_question_from_bank() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ppq_admin_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission.', 'pressprimer-quiz' ) ] );
		}

		$bank_id = isset( $_POST['bank_id'] ) ? absint( $_POST['bank_id'] ) : 0;
		$question_id = isset( $_POST['question_id'] ) ? absint( $_POST['question_id'] ) : 0;

		if ( ! $bank_id || ! $question_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid bank or question ID.', 'pressprimer-quiz' ) ] );
		}

		$bank = null;
		if ( class_exists( 'PPQ_Bank' ) ) {
			$bank = PPQ_Bank::get( $bank_id );
		}

		if ( ! $bank ) {
			wp_send_json_error( [ 'message' => __( 'Bank not found.', 'pressprimer-quiz' ) ] );
		}

		// Check ownership
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $bank->owner_id ) !== get_current_user_id() ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to edit this bank.', 'pressprimer-quiz' ) ] );
		}

		// Remove question from bank
		$bank->remove_question( $question_id );

		// Update count
		$bank->update_question_count();

		wp_send_json_success( [
			'message' => __( 'Question removed from bank.', 'pressprimer-quiz' ),
			'new_count' => $bank->question_count,
		] );
	}
}
