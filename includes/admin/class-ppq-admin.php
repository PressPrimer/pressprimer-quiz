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

		// Groups submenu
		add_submenu_page(
			'ppq',
			__( 'Groups', 'pressprimer-quiz' ),
			__( 'Groups', 'pressprimer-quiz' ),
			'ppq_manage_own',
			'ppq-groups',
			[ $this, 'render_groups' ]
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
		if ( 0 !== strpos( $hook, 'ppq' ) && 'toplevel_page_ppq' !== $hook ) {
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

		// Localize script with data
		wp_localize_script(
			'ppq-admin',
			'ppqAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ppq_admin_nonce' ),
				'strings' => [
					'confirmDelete' => __( 'Are you sure you want to delete this item?', 'pressprimer-quiz' ),
					'error'         => __( 'An error occurred. Please try again.', 'pressprimer-quiz' ),
					'saved'         => __( 'Changes saved successfully.', 'pressprimer-quiz' ),
				],
			]
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

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PressPrimer Quiz Dashboard', 'pressprimer-quiz' ); ?></h1>

			<div class="ppq-dashboard">
				<div class="ppq-dashboard-welcome">
					<h2><?php esc_html_e( 'Welcome to PressPrimer Quiz', 'pressprimer-quiz' ); ?></h2>
					<p><?php esc_html_e( 'Enterprise-grade quiz and assessment platform for WordPress educators.', 'pressprimer-quiz' ); ?></p>
				</div>

				<div class="ppq-dashboard-stats">
					<h3><?php esc_html_e( 'Quick Stats', 'pressprimer-quiz' ); ?></h3>
					<p><em><?php esc_html_e( 'Dashboard statistics will be implemented in future phases.', 'pressprimer-quiz' ); ?></em></p>
				</div>

				<div class="ppq-dashboard-actions">
					<h3><?php esc_html_e( 'Quick Actions', 'pressprimer-quiz' ); ?></h3>
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-questions&action=new' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Create Question', 'pressprimer-quiz' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-quizzes&action=new' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Create Quiz', 'pressprimer-quiz' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-reports' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'View Reports', 'pressprimer-quiz' ); ?>
						</a>
					</p>
				</div>
			</div>
		</div>
		<?php
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
	 * Render groups page
	 *
	 * Displays list and management interface for student groups.
	 *
	 * @since 1.0.0
	 */
	public function render_groups() {
		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-quiz' ),
				esc_html__( 'Permission Denied', 'pressprimer-quiz' ),
				[ 'response' => 403 ]
			);
		}

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Groups', 'pressprimer-quiz' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-groups&action=new' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'pressprimer-quiz' ); ?>
			</a>
			<hr class="wp-header-end">

			<p><em><?php esc_html_e( 'Group management will be implemented in later phases.', 'pressprimer-quiz' ); ?></em></p>
		</div>
		<?php
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

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reports', 'pressprimer-quiz' ); ?></h1>

			<p><em><?php esc_html_e( 'Reporting functionality will be implemented in Phase 5.', 'pressprimer-quiz' ); ?></em></p>
		</div>
		<?php
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

		if ( empty( $search ) ) {
			wp_send_json_success( [ 'questions' => [] ] );
		}

		global $wpdb;

		// Build search query
		$questions_table = $wpdb->prefix . 'ppq_questions';
		$revisions_table = $wpdb->prefix . 'ppq_question_revisions';

		$search_term = '%' . $wpdb->esc_like( $search ) . '%';

		// Get questions matching search
		$sql = "SELECT q.id, q.type, q.difficulty, r.stem
				FROM {$questions_table} q
				INNER JOIN {$revisions_table} r ON q.current_revision_id = r.id
				WHERE q.deleted_at IS NULL
				AND r.stem LIKE %s";

		$params = [ $search_term ];

		// Filter by author if not admin
		if ( ! current_user_can( 'ppq_manage_all' ) ) {
			$sql .= ' AND q.author_id = %d';
			$params[] = get_current_user_id();
		}

		// Exclude questions already in this bank
		if ( $bank_id > 0 ) {
			$membership_table = $wpdb->prefix . 'ppq_bank_memberships';
			$sql .= " AND q.id NOT IN (SELECT question_id FROM {$membership_table} WHERE bank_id = %d)";
			$params[] = $bank_id;
		}

		$sql .= ' LIMIT 20';

		$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		$questions = [];
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$questions[] = [
					'id'           => absint( $row->id ),
					'stem_preview' => wp_trim_words( wp_strip_all_tags( $row->stem ), 15 ),
					'type'         => $row->type,
					'difficulty'   => $row->difficulty,
				];
			}
		}

		wp_send_json_success( [ 'questions' => $questions ] );
	}
}
