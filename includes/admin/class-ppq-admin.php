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
			$this->get_menu_icon(),                                  // Icon
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
				'pluginUrl' => PPQ_PLUGIN_URL,
				'urls'      => [
					'create_quiz'  => admin_url( 'admin.php?page=ppq-quizzes&action=new' ),
					'add_question' => admin_url( 'admin.php?page=ppq-questions&action=new' ),
					'create_bank'  => admin_url( 'admin.php?page=ppq-banks&action=new' ),
					'reports'      => admin_url( 'admin.php?page=ppq-reports' ),
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
				'pluginUrl'  => PPQ_PLUGIN_URL,
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
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = 5;
		$offset = ( $page - 1 ) * $per_page;

		global $wpdb;

		// Build search query
		$questions_table = $wpdb->prefix . 'ppq_questions';
		$revisions_table = $wpdb->prefix . 'ppq_question_revisions';
		$taxonomy_table = $wpdb->prefix . 'ppq_question_tax';

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

		// Get total count first (using same WHERE conditions without LIMIT)
		$count_sql = str_replace( 'SELECT DISTINCT q.id, q.type, q.difficulty_author, r.stem', 'SELECT COUNT(DISTINCT q.id)', $sql );
		if ( ! empty( $params ) ) {
			$total_items = absint( $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );
		} else {
			$total_items = absint( $wpdb->get_var( $count_sql ) );
		}

		// Add pagination
		$sql .= ' LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = $offset;

		// Execute query
		if ( ! empty( $params ) ) {
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		} else {
			$results = $wpdb->get_results( $sql );
		}

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

				// Get categories for this question.
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
					'id'              => absint( $row->id ),
					'stem_preview'    => wp_trim_words( wp_strip_all_tags( $row->stem ), 20 ),
					'type'            => $row->type,
					'type_label'      => $type_label,
					'difficulty'      => $row->difficulty_author,
					'difficulty_label' => $difficulty_label,
					'category'        => ! empty( $category_names ) ? implode( ', ', $category_names ) : __( 'None', 'pressprimer-quiz' ),
				];
			}
		}

		$total_pages = ceil( $total_items / $per_page );

		wp_send_json_success( [
			'questions'    => $questions,
			'total_items'  => $total_items,
			'total_pages'  => $total_pages,
			'current_page' => $page,
		] );
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
		$per_page = 5;
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

	/**
	 * Get the menu icon as a base64-encoded SVG
	 *
	 * Returns the PressPrimer Quiz checkbox icon for the admin menu.
	 * Uses checkbox.svg optimized for small sizes.
	 *
	 * @since 1.0.0
	 *
	 * @return string Base64-encoded SVG data URI.
	 */
	private function get_menu_icon() {
		// PressPrimer Quiz checkbox icon from checkbox.svg.
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="500" height="500" viewBox="0 0 500 500">'
			. '<path fill="#9CA1A7" d="M63.8246 93.8664C69.7238 93.625 76.5224 93.7616 82.4678 93.7639L115.028 93.7714L231.875 93.786L306.519 93.8056C313.127 93.8325 319.736 93.8246 326.345 93.7819C330.515 93.7645 335.739 93.6044 339.803 94.0571C335.784 98.997 328.816 105.192 324.323 110.094C312.938 122.517 301.742 135.106 289.958 147.152C288.258 148.89 282.498 155.089 280.872 156.06C274.9 156.452 267.916 156.196 261.848 156.211L225.317 156.197L126.822 156.234L99.8917 156.184C95.4981 156.189 90.973 156.135 86.5922 156.308C85.2689 156.361 82.4004 156.729 81.4489 157.573C79.3746 159.412 78.2728 161.466 78.2073 164.26C78.0998 168.844 78.1089 173.493 78.0967 178.075C78.0481 187.591 78.0611 197.108 78.1356 206.625L78.1093 283.577L78.1371 365.314C78.1836 376.338 77.9529 387.367 78.2183 398.389C78.334 403.192 81.6801 405.957 86.3497 406.025C93.3381 406.127 100.327 406.026 107.315 406.009L142.203 406.019L228.125 405.994L306.411 405.998C316.172 405.948 326.131 406.285 335.91 406.026C338.062 405.969 340.116 405.202 341.688 403.679C342.776 402.635 343.471 401.248 343.655 399.751C344.043 396.804 343.853 392.309 343.818 389.265L343.766 375.522L343.829 320.614C343.782 318.665 343.601 311.421 343.991 309.99C345.373 304.925 352.598 296.897 355.513 292.814L365.956 278.275C367.38 276.266 368.587 273.893 370.19 272.068C372.138 269.85 374.011 267.73 375.822 265.393C382.785 256.303 389.601 247.102 396.269 237.793C399.228 233.748 403.361 228.552 405.903 224.484C405.95 225.81 406.008 227.136 406.074 228.462C406.677 240.354 406.137 254.538 406.185 266.561L406.176 354.75C406.265 370.059 406.264 385.368 406.173 400.676C406.198 408.341 406.72 422.251 405.583 429.251C404.138 438.354 400.073 446.839 393.885 453.669C385.117 463.314 373.35 468.699 360.41 468.915C350.352 469.084 339.971 468.986 329.893 468.931L288.948 468.921L167.625 468.901L93.1583 468.895L71.8118 468.987C68.2198 469.003 64.6471 469.038 61.0818 468.94C36.1892 468.259 15.8616 447.509 15.6245 422.628C15.5379 413.54 15.6118 404.488 15.6712 395.404L15.6926 356.877L15.673 235.017L15.6553 177.228C15.5516 169.319 15.5383 161.408 15.6151 153.499C15.6157 146.775 15.4224 139.255 16.3907 132.619C17.721 123.579 21.7711 115.159 28.0026 108.477C34.6288 101.383 44.7732 96.2328 54.3192 94.5598C57.3833 94.0228 60.7055 93.9618 63.8246 93.8664Z"/>'
			. '<path fill="#9CA1A7" d="M233.157 255.324C237.56 250.315 242.357 245.419 246.815 240.441C249.036 237.96 250.967 235.092 253.354 232.777C257.137 229.106 260.475 226.423 264.121 222.501L348.845 132.009L383.336 95.2861C395.7 82.0073 407.524 68.2207 420.516 55.5298C426.36 49.88 431.296 43.1055 436.958 37.3355C446.958 27.1432 456.567 30.9816 466.631 38.6491C472.465 43.0935 479.097 48.2042 482.861 54.7107C483.828 56.3815 484.076 58.6732 484.35 60.5428C483.999 62.8512 483.771 64.1111 483.139 66.3428C477.579 78.3146 467.966 90.0174 460.267 100.666C457.318 104.746 454.054 108.308 451.017 112.33L413.043 163.535L264.536 362.941L255.525 374.96C252.987 378.359 248.67 384.586 245.524 387.08C243.175 388.965 240.357 390.174 237.373 390.578C222.988 392.583 217.925 379.517 210.85 369.467L178.324 322.308L141.159 268.758C132.027 255.773 123.107 242.597 114.203 229.416C104.048 214.157 110.923 206.109 123.18 196.283C141.865 181.305 149.639 189.35 165.758 202.06C173.582 208.196 181.345 214.41 189.044 220.702C197.546 227.516 206.423 234.029 215.057 240.705C221.145 245.51 227.179 250.383 233.157 255.324Z"/>'
			. '</svg>';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
