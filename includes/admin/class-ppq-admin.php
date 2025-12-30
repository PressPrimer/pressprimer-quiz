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
class PressPrimer_Quiz_Admin {

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
		add_action( 'wp_ajax_pressprimer_quiz_search_questions', [ $this, 'ajax_search_questions' ] );
		add_action( 'wp_ajax_pressprimer_quiz_get_recent_questions', [ $this, 'ajax_get_recent_questions' ] );
		add_action( 'wp_ajax_pressprimer_quiz_remove_question_from_bank', [ $this, 'ajax_remove_question_from_bank' ] );

		// Initialize settings
		if ( class_exists( 'PressPrimer_Quiz_Admin_Settings' ) ) {
			$settings = new PressPrimer_Quiz_Admin_Settings();
			$settings->init();
		}

		// Initialize questions admin
		if ( class_exists( 'PressPrimer_Quiz_Admin_Questions' ) ) {
			$questions = new PressPrimer_Quiz_Admin_Questions();
			$questions->init();
		}

		// Initialize banks admin
		if ( class_exists( 'PressPrimer_Quiz_Admin_Banks' ) ) {
			$banks = new PressPrimer_Quiz_Admin_Banks();
			$banks->init();
		}

		// Initialize categories admin
		if ( class_exists( 'PressPrimer_Quiz_Admin_Categories' ) ) {
			$categories = new PressPrimer_Quiz_Admin_Categories();
			$categories->init();
		}

		// Initialize quizzes admin
		if ( class_exists( 'PressPrimer_Quiz_Admin_Quizzes' ) ) {
			$quizzes = new PressPrimer_Quiz_Admin_Quizzes();
			$quizzes->init();
		}

		// Initialize AI generation admin
		if ( class_exists( 'PressPrimer_Quiz_Admin_AI_Generation' ) ) {
			$ai_generation = new PressPrimer_Quiz_Admin_AI_Generation();
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
			'pressprimer_quiz_manage_own',                           // Capability
			'pressprimer-quiz',                                      // Menu slug
			[ $this, 'render_dashboard' ],                          // Callback
			$this->get_menu_icon(),                                  // Icon
			30                                                       // Position (after Comments)
		);

		// Dashboard submenu (replaces main menu link)
		add_submenu_page(
			'pressprimer-quiz',                                      // Parent slug
			__( 'Dashboard', 'pressprimer-quiz' ),                  // Page title
			__( 'Dashboard', 'pressprimer-quiz' ),                  // Menu title
			'pressprimer_quiz_manage_own',                           // Capability
			'pressprimer-quiz',                                      // Menu slug (same as parent)
			[ $this, 'render_dashboard' ]                           // Callback
		);

		// Quizzes submenu
		add_submenu_page(
			'pressprimer-quiz',
			__( 'Quizzes', 'pressprimer-quiz' ),
			__( 'Quizzes', 'pressprimer-quiz' ),
			'pressprimer_quiz_manage_own',
			'pressprimer-quiz-quizzes',
			[ $this, 'render_quizzes' ]
		);

		// Questions submenu
		add_submenu_page(
			'pressprimer-quiz',
			__( 'Questions', 'pressprimer-quiz' ),
			__( 'Questions', 'pressprimer-quiz' ),
			'pressprimer_quiz_manage_own',
			'pressprimer-quiz-questions',
			[ $this, 'render_questions' ]
		);

		// Question Banks submenu
		add_submenu_page(
			'pressprimer-quiz',
			__( 'Question Banks', 'pressprimer-quiz' ),
			__( 'Question Banks', 'pressprimer-quiz' ),
			'pressprimer_quiz_manage_own',
			'pressprimer-quiz-banks',
			[ $this, 'render_banks' ]
		);

		// Categories submenu
		add_submenu_page(
			'pressprimer-quiz',
			__( 'Categories', 'pressprimer-quiz' ),
			__( 'Categories', 'pressprimer-quiz' ),
			'pressprimer_quiz_manage_own',
			'pressprimer-quiz-categories',
			[ $this, 'render_categories' ]
		);

		// Tags submenu
		add_submenu_page(
			'pressprimer-quiz',
			__( 'Tags', 'pressprimer-quiz' ),
			__( 'Tags', 'pressprimer-quiz' ),
			'pressprimer_quiz_manage_own',
			'pressprimer-quiz-tags',
			[ $this, 'render_tags' ]
		);

		// Reports submenu
		add_submenu_page(
			'pressprimer-quiz',
			__( 'Reports', 'pressprimer-quiz' ),
			__( 'Reports', 'pressprimer-quiz' ),
			'pressprimer_quiz_view_results_own',                    // Different capability
			'pressprimer-quiz-reports',
			[ $this, 'render_reports' ]
		);

		// Settings submenu
		add_submenu_page(
			'pressprimer-quiz',
			__( 'Settings', 'pressprimer-quiz' ),
			__( 'Settings', 'pressprimer-quiz' ),
			'pressprimer_quiz_manage_settings',                     // Admin only
			'pressprimer-quiz-settings',
			[ $this, 'render_settings' ]
		);

		/**
		 * Fires after the core admin menu items are registered.
		 *
		 * Premium addons should use this hook to add their own submenu pages.
		 * Menu items added here appear below Settings in the admin menu.
		 *
		 * Example usage:
		 * ```php
		 * add_action( 'pressprimer_quiz_admin_menu', function() {
		 *     add_submenu_page(
		 *         'pressprimer-quiz',
		 *         __( 'Groups', 'pressprimer-quiz-groups' ),
		 *         __( 'Groups', 'pressprimer-quiz-groups' ),
		 *         'pressprimer_quiz_manage_groups',
		 *         'pressprimer-quiz-groups',
		 *         [ $this, 'render_groups' ]
		 *     );
		 * } );
		 * ```
		 *
		 * @since 2.0.0
		 */
		do_action( 'pressprimer_quiz_admin_menu' );
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
		// Only load on PressPrimer Quiz admin pages
		if ( false === strpos( $hook, 'pressprimer-quiz' ) ) {
			return;
		}

		// Enqueue admin CSS
		wp_enqueue_style(
			'ppq-admin',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/css/admin.css',
			[],
			PRESSPRIMER_QUIZ_VERSION
		);

		// Enqueue admin JavaScript
		wp_enqueue_script(
			'ppq-admin',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			PRESSPRIMER_QUIZ_VERSION,
			true
		);

		// Enqueue question builder on question pages
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check for conditional asset loading
		if ( isset( $_GET['page'] ) && 'pressprimer-quiz-questions' === $_GET['page'] ) {
			// Enqueue jQuery UI for sortable
			wp_enqueue_script( 'jquery-ui-sortable' );

			// Enqueue question builder script
			wp_enqueue_script(
				'ppq-question-builder',
				PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/js/question-builder.js',
				[ 'jquery', 'jquery-ui-sortable' ],
				PRESSPRIMER_QUIZ_VERSION,
				true
			);
		}

		// Enqueue Dashboard React app on main dashboard page
		if ( 'toplevel_page_pressprimer-quiz' === $hook ) {
			$this->enqueue_dashboard_assets();
		}

		// Enqueue Reports React app on reports page
		if ( 'pressprimer-quiz_page_pressprimer-quiz-reports' === $hook ) {
			$this->enqueue_reports_assets();
		}

		// Enqueue Onboarding React app on all PPQ pages
		$this->enqueue_onboarding_assets();

		// Localize script with data
		wp_localize_script(
			'ppq-admin',
			'pressprimerQuizAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'pressprimer_quiz_admin_nonce' ),
				'debug'   => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'strings' => [
					'confirmDelete'         => __( 'Are you sure you want to delete this item?', 'pressprimer-quiz' ),
					'confirmDeleteTitle'    => __( 'Delete Item', 'pressprimer-quiz' ),
					'confirmRemoveFromBank' => __( 'Remove this question from the bank?', 'pressprimer-quiz' ),
					'removeFromBankTitle'   => __( 'Remove from Bank', 'pressprimer-quiz' ),
					'error'                 => __( 'An error occurred. Please try again.', 'pressprimer-quiz' ),
					'saved'                 => __( 'Changes saved successfully.', 'pressprimer-quiz' ),
					'delete'                => __( 'Delete', 'pressprimer-quiz' ),
					'remove'                => __( 'Remove', 'pressprimer-quiz' ),
					'cancel'                => __( 'Cancel', 'pressprimer-quiz' ),
					'ok'                    => __( 'OK', 'pressprimer-quiz' ),
					'yes'                   => __( 'Yes', 'pressprimer-quiz' ),
					'no'                    => __( 'No', 'pressprimer-quiz' ),
					'confirmTitle'          => __( 'Confirm', 'pressprimer-quiz' ),
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
		$asset_file = PRESSPRIMER_QUIZ_PLUGIN_PATH . 'build/dashboard.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Enqueue the dashboard script
		wp_enqueue_script(
			'ppq-dashboard',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/dashboard.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue the dashboard styles
		wp_enqueue_style(
			'ppq-dashboard',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/style-dashboard.css',
			[],
			$asset['version']
		);

		// Localize script with dashboard data
		wp_localize_script(
			'ppq-dashboard',
			'pressprimerQuizDashboardData',
			[
				'pluginUrl' => PRESSPRIMER_QUIZ_PLUGIN_URL,
				'urls'      => [
					'create_quiz'  => admin_url( 'admin.php?page=pressprimer-quiz-quizzes&action=new' ),
					'add_question' => admin_url( 'admin.php?page=pressprimer-quiz-questions&action=new' ),
					'create_bank'  => admin_url( 'admin.php?page=pressprimer-quiz-banks&action=new' ),
					'reports'      => admin_url( 'admin.php?page=pressprimer-quiz-reports' ),
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
		$asset_file = PRESSPRIMER_QUIZ_PLUGIN_PATH . 'build/reports.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Enqueue the reports script
		wp_enqueue_script(
			'ppq-reports',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/reports.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue the reports styles
		wp_enqueue_style(
			'ppq-reports',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/style-reports.css',
			[],
			$asset['version']
		);

		// Localize script with reports data
		wp_localize_script(
			'ppq-reports',
			'pressprimerQuizReportsData',
			[
				'pluginUrl'  => PRESSPRIMER_QUIZ_PLUGIN_URL,
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
		$asset_file = PRESSPRIMER_QUIZ_PLUGIN_PATH . 'build/onboarding.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		// Get the onboarding instance to check state
		if ( ! class_exists( 'PressPrimer_Quiz_Onboarding' ) ) {
			return;
		}

		$onboarding = PressPrimer_Quiz_Onboarding::get_instance();

		$asset = require $asset_file;

		// Enqueue the onboarding script
		wp_enqueue_script(
			'ppq-onboarding',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/onboarding.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Enqueue the onboarding styles
		wp_enqueue_style(
			'ppq-onboarding',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/style-onboarding.css',
			[],
			$asset['version']
		);

		// Localize script with onboarding data
		wp_localize_script(
			'ppq-onboarding',
			'pressprimerQuizOnboardingData',
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
		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) ) {
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
		if ( class_exists( 'PressPrimer_Quiz_Admin_Quizzes' ) ) {
			$quizzes_admin = new PressPrimer_Quiz_Admin_Quizzes();
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
		if ( class_exists( 'PressPrimer_Quiz_Admin_Questions' ) ) {
			$questions_admin = new PressPrimer_Quiz_Admin_Questions();
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
		if ( class_exists( 'PressPrimer_Quiz_Admin_Banks' ) ) {
			$banks_admin = new PressPrimer_Quiz_Admin_Banks();
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
		if ( class_exists( 'PressPrimer_Quiz_Admin_Categories' ) ) {
			$categories_admin = new PressPrimer_Quiz_Admin_Categories();
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
		if ( class_exists( 'PressPrimer_Quiz_Admin_Categories' ) ) {
			$categories_admin = new PressPrimer_Quiz_Admin_Categories();
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
		if ( ! current_user_can( 'pressprimer_quiz_view_results_own' ) && ! current_user_can( 'pressprimer_quiz_view_results_all' ) ) {
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
		if ( class_exists( 'PressPrimer_Quiz_Admin_Settings' ) ) {
			$settings = new PressPrimer_Quiz_Admin_Settings();
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
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pressprimer_quiz_search_questions' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission.', 'pressprimer-quiz' ) ] );
		}

		$search      = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$bank_id     = isset( $_POST['bank_id'] ) ? absint( wp_unslash( $_POST['bank_id'] ) ) : 0;
		$type        = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$difficulty  = isset( $_POST['difficulty'] ) ? sanitize_key( wp_unslash( $_POST['difficulty'] ) ) : '';
		$category_id = isset( $_POST['category_id'] ) ? absint( wp_unslash( $_POST['category_id'] ) ) : 0;
		$tag_id      = isset( $_POST['tag_id'] ) ? absint( wp_unslash( $_POST['tag_id'] ) ) : 0;
		$page        = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$per_page    = 5;
		$offset      = ( $page - 1 ) * $per_page;

		global $wpdb;

		// Build search query
		$questions_table = $wpdb->prefix . 'ppq_questions';
		$revisions_table = $wpdb->prefix . 'ppq_question_revisions';
		$taxonomy_table  = $wpdb->prefix . 'ppq_question_tax';

		// Build JOIN clause
		$join_sql = "INNER JOIN {$revisions_table} r ON q.current_revision_id = r.id";
		if ( $category_id > 0 || $tag_id > 0 ) {
			$join_sql .= " INNER JOIN {$taxonomy_table} qt ON q.id = qt.question_id";
		}

		// Build WHERE conditions
		$where_conditions = [ 'q.deleted_at IS NULL' ];
		$params           = [];

		// Search filter
		if ( ! empty( $search ) ) {
			$search_term        = '%' . $wpdb->esc_like( $search ) . '%';
			$where_conditions[] = 'r.stem LIKE %s';
			$params[]           = $search_term;
		}

		// Type filter
		if ( ! empty( $type ) ) {
			$where_conditions[] = 'q.type = %s';
			$params[]           = $type;
		}

		// Difficulty filter
		if ( ! empty( $difficulty ) ) {
			$where_conditions[] = 'q.difficulty_author = %s';
			$params[]           = $difficulty;
		}

		// Category filter
		if ( $category_id > 0 ) {
			$where_conditions[] = 'qt.category_id = %d';
			$params[]           = $category_id;
		}

		// Tag filter
		if ( $tag_id > 0 ) {
			$where_conditions[] = 'qt.category_id = %d';
			$params[]           = $tag_id;
		}

		// Filter by author if not admin
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			$where_conditions[] = 'q.author_id = %d';
			$params[]           = get_current_user_id();
		}

		// Exclude questions already in this bank
		if ( $bank_id > 0 ) {
			$bank_questions_table = $wpdb->prefix . 'ppq_bank_questions';
			$where_conditions[]   = "q.id NOT IN (SELECT question_id FROM {$bank_questions_table} WHERE bank_id = %d)";
			$params[]             = $bank_id;
		}

		// Build WHERE clause
		$where_sql = 'WHERE ' . implode( ' AND ', $where_conditions );

		// Build and execute count query
		$count_sql = "SELECT COUNT(DISTINCT q.id) FROM {$questions_table} q {$join_sql} {$where_sql}";
		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic search with pagination, not suitable for caching
			$total_items = absint( $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query built with placeholders
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic search with pagination, not suitable for caching
			$total_items = absint( $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input in query
		}

		// Build and execute main query with pagination
		$sql = "SELECT DISTINCT q.id, q.type, q.difficulty_author, r.stem FROM {$questions_table} q {$join_sql} {$where_sql} LIMIT %d OFFSET %d";

		// Add pagination params
		$select_params   = $params;
		$select_params[] = $per_page;
		$select_params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic search with pagination, not suitable for caching
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $select_params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query built with placeholders

		$questions = [];
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				// Type labels
				$type_labels = [
					'mc' => __( 'Multiple Choice', 'pressprimer-quiz' ),
					'ma' => __( 'Multiple Answer', 'pressprimer-quiz' ),
					'tf' => __( 'True/False', 'pressprimer-quiz' ),
				];
				$type_label  = isset( $type_labels[ $row->type ] ) ? $type_labels[ $row->type ] : $row->type;

				// Difficulty labels
				$difficulty_labels = [
					'beginner'     => __( 'Beginner', 'pressprimer-quiz' ),
					'intermediate' => __( 'Intermediate', 'pressprimer-quiz' ),
					'advanced'     => __( 'Advanced', 'pressprimer-quiz' ),
					'expert'       => __( 'Expert', 'pressprimer-quiz' ),
				];
				$difficulty_label  = isset( $difficulty_labels[ $row->difficulty_author ] ) ? $difficulty_labels[ $row->difficulty_author ] : $row->difficulty_author;

				// Get categories for this question.
				$category_names = [];
				if ( class_exists( 'PressPrimer_Quiz_Question' ) ) {
					$question = PressPrimer_Quiz_Question::get( $row->id );
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
					'id'               => absint( $row->id ),
					'stem_preview'     => wp_trim_words( wp_strip_all_tags( $row->stem ), 20 ),
					'type'             => $row->type,
					'type_label'       => $type_label,
					'difficulty'       => $row->difficulty_author,
					'difficulty_label' => $difficulty_label,
					'category'         => ! empty( $category_names ) ? implode( ', ', $category_names ) : __( 'None', 'pressprimer-quiz' ),
				];
			}
		}

		$total_pages = ceil( $total_items / $per_page );

		wp_send_json_success(
			[
				'questions'    => $questions,
				'total_items'  => $total_items,
				'total_pages'  => $total_pages,
				'current_page' => $page,
			]
		);
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
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pressprimer_quiz_get_recent_questions' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission.', 'pressprimer-quiz' ) ] );
		}

		$bank_id  = isset( $_POST['bank_id'] ) ? absint( wp_unslash( $_POST['bank_id'] ) ) : 0;
		$page     = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$per_page = 5;
		$offset   = ( $page - 1 ) * $per_page;

		global $wpdb;

		$questions_table      = $wpdb->prefix . 'ppq_questions';
		$revisions_table      = $wpdb->prefix . 'ppq_question_revisions';
		$bank_questions_table = $wpdb->prefix . 'ppq_bank_questions';

		// Build query for recent questions
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names safely constructed from $wpdb->prefix
		$sql = "SELECT q.id, q.type, q.difficulty_author, r.stem
				FROM {$questions_table} q
				INNER JOIN {$revisions_table} r ON q.current_revision_id = r.id
				WHERE q.deleted_at IS NULL
				AND q.author_id = %d";

		$params = [ get_current_user_id() ];

		// Exclude questions already in this bank
		if ( $bank_id > 0 ) {
			$sql     .= " AND q.id NOT IN (SELECT question_id FROM {$bank_questions_table} WHERE bank_id = %d)";
			$params[] = $bank_id;
		}

		$sql .= ' ORDER BY q.created_at DESC';

		// Get total count (use same WHERE conditions)
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names safely constructed from $wpdb->prefix
		$count_sql = "SELECT COUNT(DISTINCT q.id)
				FROM {$questions_table} q
				WHERE q.deleted_at IS NULL
				AND q.author_id = %d";

		$count_params = [ get_current_user_id() ];

		if ( $bank_id > 0 ) {
			$count_sql     .= " AND q.id NOT IN (SELECT question_id FROM {$bank_questions_table} WHERE bank_id = %d)";
			$count_params[] = $bank_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic search with pagination, not suitable for caching
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with placeholders
		$total_items = absint( $wpdb->get_var( $wpdb->prepare( $count_sql, $count_params ) ) );

		// Add pagination
		$sql     .= ' LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic search with pagination, not suitable for caching
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with placeholders
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
				$type_label  = isset( $type_labels[ $row->type ] ) ? $type_labels[ $row->type ] : $row->type;

				// Difficulty labels
				$difficulty_labels = [
					'beginner'     => __( 'Beginner', 'pressprimer-quiz' ),
					'intermediate' => __( 'Intermediate', 'pressprimer-quiz' ),
					'advanced'     => __( 'Advanced', 'pressprimer-quiz' ),
					'expert'       => __( 'Expert', 'pressprimer-quiz' ),
				];
				$difficulty_label  = isset( $difficulty_labels[ $row->difficulty_author ] ) ? $difficulty_labels[ $row->difficulty_author ] : $row->difficulty_author;

				// Get categories for this question
				$categories     = [];
				$category_names = [];
				if ( class_exists( 'PressPrimer_Quiz_Question' ) ) {
					$question = PressPrimer_Quiz_Question::get( $row->id );
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
					'id'               => absint( $row->id ),
					'stem_preview'     => wp_trim_words( wp_strip_all_tags( $row->stem ), 20 ),
					'type'             => $row->type,
					'type_label'       => $type_label,
					'difficulty'       => $row->difficulty_author,
					'difficulty_label' => $difficulty_label,
					'category'         => ! empty( $category_names ) ? implode( ', ', $category_names ) : __( 'None', 'pressprimer-quiz' ),
				];
			}
		}

		$total_pages = ceil( $total_items / $per_page );

		wp_send_json_success(
			[
				'questions'    => $questions,
				'total_items'  => $total_items,
				'total_pages'  => $total_pages,
				'current_page' => $page,
			]
		);
	}

	/**
	 * AJAX handler for removing a question from a bank
	 *
	 * @since 1.0.0
	 */
	public function ajax_remove_question_from_bank() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pressprimer_quiz_admin_nonce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission.', 'pressprimer-quiz' ) ] );
		}

		$bank_id     = isset( $_POST['bank_id'] ) ? absint( wp_unslash( $_POST['bank_id'] ) ) : 0;
		$question_id = isset( $_POST['question_id'] ) ? absint( wp_unslash( $_POST['question_id'] ) ) : 0;

		if ( ! $bank_id || ! $question_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid bank or question ID.', 'pressprimer-quiz' ) ] );
		}

		$bank = null;
		if ( class_exists( 'PressPrimer_Quiz_Bank' ) ) {
			$bank = PressPrimer_Quiz_Bank::get( $bank_id );
		}

		if ( ! $bank ) {
			wp_send_json_error( [ 'message' => __( 'Bank not found.', 'pressprimer-quiz' ) ] );
		}

		// Check ownership
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $bank->owner_id ) !== get_current_user_id() ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to edit this bank.', 'pressprimer-quiz' ) ] );
		}

		// Remove question from bank
		$bank->remove_question( $question_id );

		// Update count
		$bank->update_question_count();

		wp_send_json_success(
			[
				'message'   => __( 'Question removed from bank.', 'pressprimer-quiz' ),
				'new_count' => $bank->question_count,
			]
		);
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

		// SVG must be base64 encoded for WordPress admin menu icon data URI format.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for data URI in add_menu_page()
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
