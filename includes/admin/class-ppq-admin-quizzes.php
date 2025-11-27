<?php
/**
 * Quizzes admin class
 *
 * Handles the quizzes list and management interface.
 *
 * @package PressPrimer_Quiz
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not already loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Quizzes admin class
 *
 * Manages the quizzes list table and edit interface.
 *
 * @since 1.0.0
 */
class PPQ_Admin_Quizzes {

	/**
	 * List table instance
	 *
	 * @since 1.0.0
	 * @var PPQ_Quizzes_List_Table
	 */
	private $list_table;

	/**
	 * Initialize quizzes admin
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_init', [ $this, 'handle_actions' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );

		// Add screen options on the right hook
		add_action( 'current_screen', [ $this, 'maybe_add_screen_options' ] );

		// Register AJAX handlers
		add_action( 'wp_ajax_ppq_get_available_questions', [ $this, 'ajax_get_available_questions' ] );
		add_action( 'wp_ajax_ppq_add_quiz_questions', [ $this, 'ajax_add_quiz_questions' ] );
		add_action( 'wp_ajax_ppq_remove_quiz_question', [ $this, 'ajax_remove_quiz_question' ] );
	}

	/**
	 * Maybe add screen options based on current screen
	 *
	 * @since 1.0.0
	 */
	public function maybe_add_screen_options() {
		$screen = get_current_screen();

		// Only add screen options on the quizzes list page
		if ( $screen && 'pressprimer-quiz_page_ppq-quizzes' === $screen->id ) {
			$this->screen_options();
		}
	}

	/**
	 * Set up screen options
	 *
	 * @since 1.0.0
	 */
	public function screen_options() {
		// Only on list view
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		if ( in_array( $action, [ 'new', 'edit' ], true ) ) {
			return;
		}

		// Add per page option
		add_screen_option( 'per_page', [
			'label'   => __( 'Quizzes per page', 'pressprimer-quiz' ),
			'default' => 20,
			'option'  => 'ppq_quizzes_per_page',
		] );

		// Instantiate the table and store it
		$this->list_table = new PPQ_Quizzes_List_Table();

		// Get screen and register columns with it
		$screen = get_current_screen();
		if ( $screen ) {
			// Get columns from the table
			$columns = $this->list_table->get_columns();

			// Register columns with the screen
			add_filter( "manage_{$screen->id}_columns", function() use ( $columns ) {
				return $columns;
			} );
		}

		// Set up filter for saving screen option
		add_filter( 'set-screen-option', [ $this, 'set_screen_option' ], 10, 3 );
	}

	/**
	 * Set screen option
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $status Screen option value. Default false to skip.
	 * @param string $option The option name.
	 * @param mixed  $value  The option value.
	 * @return mixed Screen option value.
	 */
	public function set_screen_option( $status, $option, $value ) {
		if ( 'ppq_quizzes_per_page' === $option ) {
			return $value;
		}

		return $status;
	}

	/**
	 * Handle actions
	 *
	 * @since 1.0.0
	 */
	public function handle_actions() {
		// Check for POST actions (save)
		if ( isset( $_POST['ppq_save_quiz'] ) ) {
			$this->handle_save();
		}

		// Check for actions
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

		if ( 'delete' === $action && isset( $_GET['quiz'] ) ) {
			$this->handle_delete();
		}

		if ( 'duplicate' === $action && isset( $_GET['quiz'] ) ) {
			$this->handle_duplicate();
		}

		// Bulk actions
		if ( isset( $_GET['action2'] ) && 'delete' === $_GET['action2'] && isset( $_GET['quizzes'] ) ) {
			$this->handle_bulk_delete();
		}

		if ( isset( $_GET['action2'] ) && 'publish' === $_GET['action2'] && isset( $_GET['quizzes'] ) ) {
			$this->handle_bulk_publish();
		}

		if ( isset( $_GET['action2'] ) && 'draft' === $_GET['action2'] && isset( $_GET['quizzes'] ) ) {
			$this->handle_bulk_draft();
		}
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		if ( ! isset( $_GET['page'] ) || 'ppq-quizzes' !== $_GET['page'] ) {
			return;
		}

		// Success messages
		if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Quiz saved successfully.', 'pressprimer-quiz' ); ?></p>
			</div>
			<?php
		}

		if ( isset( $_GET['deleted'] ) && absint( $_GET['deleted'] ) > 0 ) {
			$count = absint( $_GET['deleted'] );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of quizzes deleted */
						esc_html( _n( '%d quiz deleted.', '%d quizzes deleted.', $count, 'pressprimer-quiz' ) ),
						$count
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['duplicated'] ) && '1' === $_GET['duplicated'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Quiz duplicated successfully.', 'pressprimer-quiz' ); ?></p>
			</div>
			<?php
		}

		if ( isset( $_GET['published'] ) && absint( $_GET['published'] ) > 0 ) {
			$count = absint( $_GET['published'] );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of quizzes published */
						esc_html( _n( '%d quiz published.', '%d quizzes published.', $count, 'pressprimer-quiz' ) ),
						$count
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['drafted'] ) && absint( $_GET['drafted'] ) > 0 ) {
			$count = absint( $_GET['drafted'] );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of quizzes moved to draft */
						esc_html( _n( '%d quiz moved to draft.', '%d quizzes moved to draft.', $count, 'pressprimer-quiz' ) ),
						$count
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Render admin page
	 *
	 * @since 1.0.0
	 */
	public function render() {
		// Check user permissions
		if ( ! current_user_can( 'ppq_manage_own' ) && ! current_user_can( 'ppq_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pressprimer-quiz' ) );
		}

		// Get action
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

		// Route to appropriate view
		if ( 'edit' === $action || 'new' === $action ) {
			$this->render_edit();
		} else {
			$this->render_list();
		}
	}

	/**
	 * Render quizzes list
	 *
	 * @since 1.0.0
	 */
	private function render_list() {
		// Reuse the list table instance if it exists, otherwise create new one
		if ( ! $this->list_table ) {
			$this->list_table = new PPQ_Quizzes_List_Table();
		}

		$this->list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Quizzes', 'pressprimer-quiz' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-quizzes&action=new' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'pressprimer-quiz' ); ?>
			</a>
			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="page" value="ppq-quizzes">
				<?php
				$this->list_table->search_box( __( 'Search Quizzes', 'pressprimer-quiz' ), 'ppq-quiz' );
				$this->list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render quiz editor (React version)
	 *
	 * @since 1.0.0
	 */
	private function render_edit() {
		$quiz_id = isset( $_GET['quiz'] ) ? absint( $_GET['quiz'] ) : 0;
		$quiz    = null;

		// Load quiz if editing
		if ( $quiz_id ) {
			$quiz = PPQ_Quiz::get( $quiz_id );

			if ( ! $quiz ) {
				wp_die( esc_html__( 'Quiz not found.', 'pressprimer-quiz' ) );
			}

			// Check permission
			if ( ! current_user_can( 'ppq_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
				wp_die( esc_html__( 'You do not have permission to edit this quiz.', 'pressprimer-quiz' ) );
			}
		}

		// Enqueue React editor
		$this->enqueue_react_editor( $quiz_id );

		?>
		<!-- React Editor Root -->
		<div id="ppq-quiz-editor-root"></div>
		<?php
	}

	/**
	 * Enqueue React quiz editor
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id Quiz ID (0 for new).
	 */
	private function enqueue_react_editor( int $quiz_id ) {
		// Enqueue Ant Design CSS
		wp_enqueue_style(
			'antd',
			'https://cdn.jsdelivr.net/npm/antd@5.12.0/dist/reset.css',
			[],
			'5.12.0'
		);

		// Enqueue the built React bundle
		wp_enqueue_script(
			'ppq-quiz-editor',
			PPQ_PLUGIN_URL . 'build/quiz-editor.js',
			[ 'wp-element', 'wp-i18n', 'wp-api-fetch' ],
			PPQ_VERSION,
			true
		);

		wp_enqueue_style(
			'ppq-quiz-editor',
			PPQ_PLUGIN_URL . 'build/style-quiz-editor.css',
			[],
			PPQ_VERSION
		);

		// Prepare quiz data for JavaScript
		$quiz_data = [];

		if ( $quiz_id > 0 ) {
			$quiz = PPQ_Quiz::get( $quiz_id );

			if ( $quiz ) {
				$quiz_data = [
					'id'                     => $quiz->id,
					'title'                  => $quiz->title,
					'description'            => $quiz->description,
					'featured_image_id'      => $quiz->featured_image_id,
					'status'                 => $quiz->status,
					'mode'                   => $quiz->mode,
					'time_limit_seconds'     => $quiz->time_limit_seconds,
					'pass_percent'           => $quiz->pass_percent,
					'allow_skip'             => (bool) $quiz->allow_skip,
					'allow_backward'         => (bool) $quiz->allow_backward,
					'allow_resume'           => (bool) $quiz->allow_resume,
					'randomize_questions'    => (bool) $quiz->randomize_questions,
					'randomize_answers'      => (bool) $quiz->randomize_answers,
					'page_mode'              => $quiz->page_mode,
					'questions_per_page'     => $quiz->questions_per_page,
					'show_answers'           => $quiz->show_answers,
					'enable_confidence'      => (bool) $quiz->enable_confidence,
					'theme'                  => $quiz->theme,
					'max_attempts'           => $quiz->max_attempts,
					'attempt_delay_minutes'  => $quiz->attempt_delay_minutes,
					'generation_mode'        => $quiz->generation_mode,
				];
			}
		}

		// Localize script with data
		wp_localize_script(
			'ppq-quiz-editor',
			'ppqQuizData',
			$quiz_data
		);

		// Also pass admin URL
		wp_localize_script(
			'ppq-quiz-editor',
			'ppqAdmin',
			[
				'adminUrl' => admin_url(),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			]
		);
	}

	/**
	 * Handle quiz save
	 *
	 * @since 1.0.0
	 */
	private function handle_save() {
		// Verify nonce
		if ( ! isset( $_POST['ppq_quiz_nonce'] ) || ! wp_verify_nonce( $_POST['ppq_quiz_nonce'], 'ppq_save_quiz' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'ppq_manage_own' ) && ! current_user_can( 'ppq_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to save quizzes.', 'pressprimer-quiz' ) );
		}

		$quiz_id = isset( $_POST['quiz_id'] ) ? absint( $_POST['quiz_id'] ) : 0;

		// Prepare data
		$data = [
			'title'                 => sanitize_text_field( $_POST['title'] ?? '' ),
			'description'           => wp_kses_post( $_POST['description'] ?? '' ),
			'featured_image_id'     => absint( $_POST['featured_image_id'] ?? 0 ),
			'status'                => sanitize_key( $_POST['status'] ?? 'draft' ),
			'mode'                  => sanitize_key( $_POST['mode'] ?? 'tutorial' ),
			'pass_percent'          => floatval( $_POST['pass_percent'] ?? 70 ),
			'allow_skip'            => isset( $_POST['allow_skip'] ) ? 1 : 0,
			'allow_backward'        => isset( $_POST['allow_backward'] ) ? 1 : 0,
			'allow_resume'          => isset( $_POST['allow_resume'] ) ? 1 : 0,
			'randomize_questions'   => isset( $_POST['randomize_questions'] ) ? 1 : 0,
			'randomize_answers'     => isset( $_POST['randomize_answers'] ) ? 1 : 0,
			'page_mode'             => sanitize_key( $_POST['page_mode'] ?? 'single' ),
			'questions_per_page'    => absint( $_POST['questions_per_page'] ?? 1 ),
			'show_answers'          => sanitize_key( $_POST['show_answers'] ?? 'after_submit' ),
			'enable_confidence'     => isset( $_POST['enable_confidence'] ) ? 1 : 0,
			'theme'                 => sanitize_key( $_POST['theme'] ?? 'default' ),
			'generation_mode'       => sanitize_key( $_POST['generation_mode'] ?? 'fixed' ),
			'attempt_delay_minutes' => absint( $_POST['attempt_delay_minutes'] ?? 0 ),
		];

		// Handle time limit (convert minutes to seconds, or NULL if not enabled)
		if ( ! empty( $_POST['time_limit_minutes'] ) ) {
			$data['time_limit_seconds'] = absint( $_POST['time_limit_minutes'] ) * 60;
		} else {
			$data['time_limit_seconds'] = null;
		}

		// Handle max attempts (or NULL if not enabled)
		if ( ! empty( $_POST['max_attempts'] ) ) {
			$data['max_attempts'] = absint( $_POST['max_attempts'] );
		} else {
			$data['max_attempts'] = null;
		}

		// Update or create
		if ( $quiz_id ) {
			// Update existing quiz
			$quiz = PPQ_Quiz::get( $quiz_id );

			if ( ! $quiz ) {
				wp_die( esc_html__( 'Quiz not found.', 'pressprimer-quiz' ) );
			}

			// Check permission
			if ( ! current_user_can( 'ppq_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
				wp_die( esc_html__( 'You do not have permission to edit this quiz.', 'pressprimer-quiz' ) );
			}

			// Update properties
			foreach ( $data as $key => $value ) {
				$quiz->$key = $value;
			}

			$result = $quiz->save();

			if ( is_wp_error( $result ) ) {
				wp_die( esc_html( $result->get_error_message() ) );
			}

			// Update item weights for fixed quizzes
			if ( 'fixed' === $quiz->generation_mode && ! empty( $_POST['item_weights'] ) ) {
				global $wpdb;
				$items_table = $wpdb->prefix . 'ppq_quiz_items';

				foreach ( $_POST['item_weights'] as $item_id => $weight ) {
					$item_id = absint( $item_id );
					$weight  = floatval( $weight );

					// Validate weight range
					if ( $weight < 0 || $weight > 100 ) {
						continue;
					}

					$wpdb->update(
						$items_table,
						[ 'weight' => $weight ],
						[ 'id' => $item_id ],
						[ '%f' ],
						[ '%d' ]
					);
				}
			}

			// Redirect back to edit page with success message
			wp_safe_redirect(
				add_query_arg(
					[
						'page'   => 'ppq-quizzes',
						'action' => 'edit',
						'quiz'   => $quiz->id,
						'saved'  => '1',
					],
					admin_url( 'admin.php' )
				)
			);
			exit;

		} else {
			// Create new quiz
			$quiz_id = PPQ_Quiz::create( $data );

			if ( is_wp_error( $quiz_id ) ) {
				wp_die( esc_html( $quiz_id->get_error_message() ) );
			}

			// Redirect to edit page with success message
			wp_safe_redirect(
				add_query_arg(
					[
						'page'   => 'ppq-quizzes',
						'action' => 'edit',
						'quiz'   => $quiz_id,
						'saved'  => '1',
					],
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
	}

	/**
	 * Handle quiz delete
	 *
	 * @since 1.0.0
	 */
	private function handle_delete() {
		check_admin_referer( 'delete-quiz_' . $_GET['quiz'] );

		if ( ! current_user_can( 'ppq_manage_own' ) && ! current_user_can( 'ppq_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete quizzes.', 'pressprimer-quiz' ) );
		}

		$quiz_id = absint( $_GET['quiz'] );
		$quiz    = PPQ_Quiz::get( $quiz_id );

		if ( ! $quiz ) {
			wp_die( esc_html__( 'Quiz not found.', 'pressprimer-quiz' ) );
		}

		// Check permission - user must own quiz or have manage_all capability
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
			wp_die( esc_html__( 'You do not have permission to delete this quiz.', 'pressprimer-quiz' ) );
		}

		// Delete quiz
		$result = $quiz->delete();

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		wp_safe_redirect( add_query_arg( 'deleted', '1', admin_url( 'admin.php?page=ppq-quizzes' ) ) );
		exit;
	}

	/**
	 * Handle bulk delete
	 *
	 * @since 1.0.0
	 */
	private function handle_bulk_delete() {
		check_admin_referer( 'bulk-quizzes' );

		if ( ! current_user_can( 'ppq_manage_own' ) && ! current_user_can( 'ppq_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete quizzes.', 'pressprimer-quiz' ) );
		}

		$quiz_ids = array_map( 'absint', $_GET['quizzes'] );
		$deleted  = 0;

		foreach ( $quiz_ids as $quiz_id ) {
			$quiz = PPQ_Quiz::get( $quiz_id );

			if ( ! $quiz ) {
				continue;
			}

			// Check permission
			if ( ! current_user_can( 'ppq_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
				continue;
			}

			$result = $quiz->delete();

			if ( ! is_wp_error( $result ) ) {
				$deleted++;
			}
		}

		wp_safe_redirect( add_query_arg( 'deleted', $deleted, admin_url( 'admin.php?page=ppq-quizzes' ) ) );
		exit;
	}

	/**
	 * Handle quiz duplicate
	 *
	 * @since 1.0.0
	 */
	private function handle_duplicate() {
		check_admin_referer( 'duplicate-quiz_' . $_GET['quiz'] );

		if ( ! current_user_can( 'ppq_manage_own' ) && ! current_user_can( 'ppq_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate quizzes.', 'pressprimer-quiz' ) );
		}

		$quiz_id = absint( $_GET['quiz'] );
		$quiz    = PPQ_Quiz::get( $quiz_id );

		if ( ! $quiz ) {
			wp_die( esc_html__( 'Quiz not found.', 'pressprimer-quiz' ) );
		}

		// Check permission
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
			wp_die( esc_html__( 'You do not have permission to duplicate this quiz.', 'pressprimer-quiz' ) );
		}

		// Duplicate quiz (uses Quiz model's duplicate method)
		$new_quiz = $quiz->duplicate();

		if ( is_wp_error( $new_quiz ) ) {
			wp_die( esc_html( $new_quiz->get_error_message() ) );
		}

		// Redirect to edit the new quiz
		wp_safe_redirect(
			add_query_arg(
				[
					'action'     => 'edit',
					'quiz'       => $new_quiz->id,
					'duplicated' => '1',
				],
				admin_url( 'admin.php?page=ppq-quizzes' )
			)
		);
		exit;
	}

	/**
	 * Handle bulk publish
	 *
	 * @since 1.0.0
	 */
	private function handle_bulk_publish() {
		check_admin_referer( 'bulk-quizzes' );

		if ( ! current_user_can( 'ppq_manage_own' ) && ! current_user_can( 'ppq_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to publish quizzes.', 'pressprimer-quiz' ) );
		}

		$quiz_ids  = array_map( 'absint', $_GET['quizzes'] );
		$published = 0;

		foreach ( $quiz_ids as $quiz_id ) {
			$quiz = PPQ_Quiz::get( $quiz_id );

			if ( ! $quiz ) {
				continue;
			}

			// Check permission
			if ( ! current_user_can( 'ppq_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
				continue;
			}

			// Update status to published
			$quiz->status = 'published';
			$result       = $quiz->save();

			if ( ! is_wp_error( $result ) ) {
				$published++;
			}
		}

		wp_safe_redirect( add_query_arg( 'published', $published, admin_url( 'admin.php?page=ppq-quizzes' ) ) );
		exit;
	}

	/**
	 * Handle bulk draft
	 *
	 * @since 1.0.0
	 */
	private function handle_bulk_draft() {
		check_admin_referer( 'bulk-quizzes' );

		if ( ! current_user_can( 'ppq_manage_own' ) && ! current_user_can( 'ppq_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to change quiz status.', 'pressprimer-quiz' ) );
		}

		$quiz_ids = array_map( 'absint', $_GET['quizzes'] );
		$drafted  = 0;

		foreach ( $quiz_ids as $quiz_id ) {
			$quiz = PPQ_Quiz::get( $quiz_id );

			if ( ! $quiz ) {
				continue;
			}

			// Check permission
			if ( ! current_user_can( 'ppq_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
				continue;
			}

			// Update status to draft
			$quiz->status = 'draft';
			$result       = $quiz->save();

			if ( ! is_wp_error( $result ) ) {
				$drafted++;
			}
		}

		wp_safe_redirect( add_query_arg( 'drafted', $drafted, admin_url( 'admin.php?page=ppq-quizzes' ) ) );
		exit;
	}

	/**
	 * AJAX handler: Get available questions
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_available_questions() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ppq_get_questions' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'ppq_manage_own' ) && ! current_user_can( 'ppq_manage_all' ) ) {
			wp_send_json_error( __( 'You do not have permission to view questions.', 'pressprimer-quiz' ) );
		}

		$quiz_id = isset( $_POST['quiz_id'] ) ? absint( $_POST['quiz_id'] ) : 0;

		global $wpdb;
		$questions_table = $wpdb->prefix . 'ppq_questions';
		$revisions_table = $wpdb->prefix . 'ppq_question_revisions';

		// Get all published questions that are not already in this quiz
		$query = "SELECT q.id, q.question_type, r.stem
				  FROM {$questions_table} q
				  INNER JOIN {$revisions_table} r ON q.current_revision_id = r.id
				  WHERE q.status = 'published'
				  AND q.deleted_at IS NULL";

		// Exclude questions already in quiz
		if ( $quiz_id ) {
			$items_table = $wpdb->prefix . 'ppq_quiz_items';
			$query      .= $wpdb->prepare(
				" AND q.id NOT IN (SELECT question_id FROM {$items_table} WHERE quiz_id = %d)",
				$quiz_id
			);
		}

		$query  .= ' ORDER BY q.id DESC LIMIT 100';
		$results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$questions = [];
		foreach ( $results as $row ) {
			$questions[] = [
				'id'   => absint( $row->id ),
				'type' => $row->question_type,
				'stem' => wp_strip_all_tags( $row->stem ),
			];
		}

		wp_send_json_success( $questions );
	}

	/**
	 * AJAX handler: Add questions to quiz
	 *
	 * @since 1.0.0
	 */
	public function ajax_add_quiz_questions() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ppq_add_questions' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'ppq_manage_own' ) && ! current_user_can( 'ppq_manage_all' ) ) {
			wp_send_json_error( __( 'You do not have permission to edit quizzes.', 'pressprimer-quiz' ) );
		}

		$quiz_id      = isset( $_POST['quiz_id'] ) ? absint( $_POST['quiz_id'] ) : 0;
		$question_ids = isset( $_POST['question_ids'] ) ? array_map( 'absint', $_POST['question_ids'] ) : [];

		if ( ! $quiz_id || empty( $question_ids ) ) {
			wp_send_json_error( __( 'Invalid data provided.', 'pressprimer-quiz' ) );
		}

		// Verify quiz exists and user has permission
		$quiz = PPQ_Quiz::get( $quiz_id );
		if ( ! $quiz ) {
			wp_send_json_error( __( 'Quiz not found.', 'pressprimer-quiz' ) );
		}

		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
			wp_send_json_error( __( 'You do not have permission to edit this quiz.', 'pressprimer-quiz' ) );
		}

		// Add each question
		$added = 0;
		foreach ( $question_ids as $question_id ) {
			$result = PPQ_Quiz_Item::create( [
				'quiz_id'     => $quiz_id,
				'question_id' => $question_id,
				'weight'      => 1.00,
			] );

			if ( ! is_wp_error( $result ) ) {
				$added++;
			}
		}

		wp_send_json_success( [
			'added'   => $added,
			'message' => sprintf(
				/* translators: %d: number of questions added */
				_n( '%d question added.', '%d questions added.', $added, 'pressprimer-quiz' ),
				$added
			),
		] );
	}

	/**
	 * AJAX handler: Remove question from quiz
	 *
	 * @since 1.0.0
	 */
	public function ajax_remove_quiz_question() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ppq_remove_question' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'ppq_manage_own' ) && ! current_user_can( 'ppq_manage_all' ) ) {
			wp_send_json_error( __( 'You do not have permission to edit quizzes.', 'pressprimer-quiz' ) );
		}

		$item_id = isset( $_POST['item_id'] ) ? absint( $_POST['item_id'] ) : 0;

		if ( ! $item_id ) {
			wp_send_json_error( __( 'Invalid item ID.', 'pressprimer-quiz' ) );
		}

		// Get item
		$item = PPQ_Quiz_Item::get( $item_id );
		if ( ! $item ) {
			wp_send_json_error( __( 'Quiz item not found.', 'pressprimer-quiz' ) );
		}

		// Verify quiz exists and user has permission
		$quiz = PPQ_Quiz::get( $item->quiz_id );
		if ( ! $quiz ) {
			wp_send_json_error( __( 'Quiz not found.', 'pressprimer-quiz' ) );
		}

		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
			wp_send_json_error( __( 'You do not have permission to edit this quiz.', 'pressprimer-quiz' ) );
		}

		// Delete item
		$result = $item->delete();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( [
			'message' => __( 'Question removed from quiz.', 'pressprimer-quiz' ),
		] );
	}
}

/**
 * Quizzes list table class
 *
 * @since 1.0.0
 */
class PPQ_Quizzes_List_Table extends WP_List_Table {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct([
			'singular' => 'quiz',
			'plural'   => 'quizzes',
			'ajax'     => false,
		]);
	}

	/**
	 * Get columns
	 *
	 * @since 1.0.0
	 *
	 * @return array Column definitions.
	 */
	public function get_columns() {
		return [
			'cb'        => '<input type="checkbox" />',
			'title'     => __( 'Title', 'pressprimer-quiz' ),
			'questions' => __( 'Questions', 'pressprimer-quiz' ),
			'mode'      => __( 'Mode', 'pressprimer-quiz' ),
			'status'    => __( 'Status', 'pressprimer-quiz' ),
			'author'    => __( 'Author', 'pressprimer-quiz' ),
			'date'      => __( 'Date', 'pressprimer-quiz' ),
		];
	}

	/**
	 * Get default hidden columns
	 *
	 * @since 1.0.0
	 *
	 * @return array Hidden columns.
	 */
	public function get_hidden_columns() {
		// Get user's hidden columns preference
		$hidden = get_user_option( 'managepressprimer-quiz_page_ppq-quizzescolumnshidden' );

		// If not set, return default hidden columns (none hidden by default)
		if ( false === $hidden ) {
			return [];
		}

		return $hidden;
	}

	/**
	 * Get sortable columns
	 *
	 * @since 1.0.0
	 *
	 * @return array Sortable columns.
	 */
	public function get_sortable_columns() {
		return [
			'title'  => [ 'title', true ],
			'mode'   => [ 'mode', false ],
			'status' => [ 'status', false ],
			'author' => [ 'owner_id', false ],
			'date'   => [ 'created_at', false ],
		];
	}

	/**
	 * Get bulk actions
	 *
	 * @since 1.0.0
	 *
	 * @return array Bulk actions.
	 */
	public function get_bulk_actions() {
		return [
			'publish' => __( 'Publish', 'pressprimer-quiz' ),
			'draft'   => __( 'Move to Draft', 'pressprimer-quiz' ),
			'delete'  => __( 'Delete', 'pressprimer-quiz' ),
		];
	}

	/**
	 * Prepare items for display
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		global $wpdb;

		$per_page     = $this->get_items_per_page( 'ppq_quizzes_per_page', 20 );
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// Build query
		$quizzes_table = $wpdb->prefix . 'ppq_quizzes';
		$where_clauses = [];
		$where_values  = [];

		// Filter by search
		if ( ! empty( $_GET['s'] ) ) {
			$where_clauses[] = 'title LIKE %s';
			$where_values[]  = '%' . $wpdb->esc_like( sanitize_text_field( $_GET['s'] ) ) . '%';
		}

		// Filter by status
		if ( ! empty( $_GET['status'] ) && 'all' !== $_GET['status'] ) {
			$where_clauses[] = 'status = %s';
			$where_values[]  = sanitize_key( $_GET['status'] );
		}

		// Filter by mode
		if ( ! empty( $_GET['mode'] ) && 'all' !== $_GET['mode'] ) {
			$where_clauses[] = 'mode = %s';
			$where_values[]  = sanitize_key( $_GET['mode'] );
		}

		// Filter by author (if not manage_all, only show own quizzes)
		if ( ! current_user_can( 'ppq_manage_all' ) ) {
			$where_clauses[] = 'owner_id = %d';
			$where_values[]  = get_current_user_id();
		} elseif ( ! empty( $_GET['author'] ) && 'all' !== $_GET['author'] ) {
			$where_clauses[] = 'owner_id = %d';
			$where_values[]  = absint( $_GET['author'] );
		}

		// Build WHERE clause
		$where_sql = ! empty( $where_clauses )
			? 'WHERE ' . implode( ' AND ', $where_clauses )
			: '';

		// Get orderby and order
		$orderby = ! empty( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at';
		$order   = ! empty( $_GET['order'] ) ? strtoupper( sanitize_key( $_GET['order'] ) ) : 'DESC';

		// Validate order
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			$order = 'DESC';
		}

		// Get total count
		$total_query = "SELECT COUNT(*) FROM {$quizzes_table} {$where_sql}";

		if ( ! empty( $where_values ) ) {
			$total_query = $wpdb->prepare( $total_query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$total_items = $wpdb->get_var( $total_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Get items
		$items_query = "SELECT * FROM {$quizzes_table} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_values = array_merge( $where_values, [ $per_page, $offset ] );

		$items = $wpdb->get_results(
			$wpdb->prepare( $items_query, $query_values ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		// Convert to model instances
		$this->items = [];
		if ( $items ) {
			foreach ( $items as $item ) {
				$this->items[] = PPQ_Quiz::from_row( $item );
			}
		}

		// Set pagination
		$this->set_pagination_args([
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		]);

		// Set columns
		$columns               = $this->get_columns();
		$hidden                = $this->get_hidden_columns();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];
	}

	/**
	 * Render checkbox column
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz $item Quiz object.
	 * @return string Column content.
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="quizzes[]" value="%d" />', $item->id );
	}

	/**
	 * Render title column
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz $item Quiz object.
	 * @return string Column content.
	 */
	public function column_title( $item ) {
		// Build row actions
		$actions = [];

		// Edit action
		$actions['edit'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				add_query_arg(
					[
						'page'   => 'ppq-quizzes',
						'action' => 'edit',
						'quiz'   => $item->id,
					],
					admin_url( 'admin.php' )
				)
			),
			esc_html__( 'Edit', 'pressprimer-quiz' )
		);

		// Duplicate action
		$actions['duplicate'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				wp_nonce_url(
					add_query_arg(
						[
							'page'   => 'ppq-quizzes',
							'action' => 'duplicate',
							'quiz'   => $item->id,
						],
						admin_url( 'admin.php' )
					),
					'duplicate-quiz_' . $item->id
				)
			),
			esc_html__( 'Duplicate', 'pressprimer-quiz' )
		);

		// Preview action (placeholder for now)
		$actions['preview'] = sprintf(
			'<a href="%s" target="_blank">%s</a>',
			'#', // Will be implemented with quiz shortcode/block
			esc_html__( 'Preview', 'pressprimer-quiz' )
		);

		// Delete action
		$actions['delete'] = sprintf(
			'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\')">%s</a>',
			esc_url(
				wp_nonce_url(
					add_query_arg(
						[
							'page'   => 'ppq-quizzes',
							'action' => 'delete',
							'quiz'   => $item->id,
						],
						admin_url( 'admin.php' )
					),
					'delete-quiz_' . $item->id
				)
			),
			esc_js( __( 'Are you sure you want to delete this quiz?', 'pressprimer-quiz' ) ),
			esc_html__( 'Delete', 'pressprimer-quiz' )
		);

		// Build output
		$title = ! empty( $item->title ) ? esc_html( $item->title ) : '<em>' . esc_html__( '(no title)', 'pressprimer-quiz' ) . '</em>';

		return sprintf( '<strong>%s</strong>%s', $title, $this->row_actions( $actions ) );
	}

	/**
	 * Render questions column
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz $item Quiz object.
	 * @return string Column content.
	 */
	public function column_questions( $item ) {
		if ( 'fixed' === $item->generation_mode ) {
			// Count items
			$items = $item->get_items();
			$count = count( $items );

			return sprintf(
				'%d %s',
				$count,
				esc_html( _n( 'question', 'questions', $count, 'pressprimer-quiz' ) )
			);
		} else {
			// Count expected from rules
			$rules = $item->get_rules();
			$total = 0;

			foreach ( $rules as $rule ) {
				$total += $rule->question_count;
			}

			return sprintf(
				'~%d %s',
				$total,
				esc_html( _n( 'question', 'questions', $total, 'pressprimer-quiz' ) )
			);
		}
	}

	/**
	 * Render mode column
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz $item Quiz object.
	 * @return string Column content.
	 */
	public function column_mode( $item ) {
		$modes = [
			'tutorial' => __( 'Tutorial', 'pressprimer-quiz' ),
			'timed'    => __( 'Timed', 'pressprimer-quiz' ),
		];

		return isset( $modes[ $item->mode ] ) ? esc_html( $modes[ $item->mode ] ) : '—';
	}

	/**
	 * Render status column
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz $item Quiz object.
	 * @return string Column content.
	 */
	public function column_status( $item ) {
		$statuses = [
			'draft'     => __( 'Draft', 'pressprimer-quiz' ),
			'published' => __( 'Published', 'pressprimer-quiz' ),
			'archived'  => __( 'Archived', 'pressprimer-quiz' ),
		];

		return isset( $statuses[ $item->status ] ) ? esc_html( $statuses[ $item->status ] ) : '—';
	}

	/**
	 * Render author column
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz $item Quiz object.
	 * @return string Column content.
	 */
	public function column_author( $item ) {
		$user = get_userdata( $item->owner_id );

		if ( ! $user ) {
			return '—';
		}

		// Link to filter by author
		return sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				add_query_arg(
					[
						'page'   => 'ppq-quizzes',
						'author' => $item->owner_id,
					],
					admin_url( 'admin.php' )
				)
			),
			esc_html( $user->display_name )
		);
	}

	/**
	 * Render date column
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz $item Quiz object.
	 * @return string Column content.
	 */
	public function column_date( $item ) {
		$timestamp = strtotime( $item->created_at );

		if ( ! $timestamp ) {
			return '—';
		}

		$time_diff = time() - $timestamp;

		// Show relative time if less than 24 hours
		if ( $time_diff < DAY_IN_SECONDS ) {
			return sprintf(
				'<abbr title="%s">%s</abbr>',
				esc_attr( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ),
				/* translators: %s: human-readable time difference */
				sprintf( esc_html__( '%s ago', 'pressprimer-quiz' ), human_time_diff( $timestamp ) )
			);
		}

		return date_i18n( get_option( 'date_format' ), $timestamp );
	}

	/**
	 * Render filters above table
	 *
	 * @since 1.0.0
	 *
	 * @param string $which Top or bottom of table.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		?>
		<div class="alignleft actions">
			<?php
			// Status filter
			$this->render_status_filter();

			// Mode filter
			$this->render_mode_filter();

			// Author filter (only if user can manage all)
			if ( current_user_can( 'ppq_manage_all' ) ) {
				$this->render_author_filter();
			}

			submit_button( __( 'Filter', 'pressprimer-quiz' ), '', 'filter_action', false );
			?>
		</div>
		<?php
	}

	/**
	 * Render status filter dropdown
	 *
	 * @since 1.0.0
	 */
	private function render_status_filter() {
		$current_status = ! empty( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'all';

		$statuses = [
			'all'       => __( 'All Statuses', 'pressprimer-quiz' ),
			'draft'     => __( 'Draft', 'pressprimer-quiz' ),
			'published' => __( 'Published', 'pressprimer-quiz' ),
			'archived'  => __( 'Archived', 'pressprimer-quiz' ),
		];

		?>
		<label class="screen-reader-text" for="filter-by-status"><?php esc_html_e( 'Filter by status', 'pressprimer-quiz' ); ?></label>
		<select name="status" id="filter-by-status">
			<?php foreach ( $statuses as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_status, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render mode filter dropdown
	 *
	 * @since 1.0.0
	 */
	private function render_mode_filter() {
		$current_mode = ! empty( $_GET['mode'] ) ? sanitize_key( $_GET['mode'] ) : 'all';

		$modes = [
			'all'      => __( 'All Modes', 'pressprimer-quiz' ),
			'tutorial' => __( 'Tutorial', 'pressprimer-quiz' ),
			'timed'    => __( 'Timed', 'pressprimer-quiz' ),
		];

		?>
		<label class="screen-reader-text" for="filter-by-mode"><?php esc_html_e( 'Filter by mode', 'pressprimer-quiz' ); ?></label>
		<select name="mode" id="filter-by-mode">
			<?php foreach ( $modes as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_mode, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render author filter dropdown
	 *
	 * @since 1.0.0
	 */
	private function render_author_filter() {
		$current_author = ! empty( $_GET['author'] ) ? absint( $_GET['author'] ) : 0;

		// Get all users who have created quizzes
		global $wpdb;
		$quizzes_table = $wpdb->prefix . 'ppq_quizzes';

		$authors = $wpdb->get_results(
			"SELECT DISTINCT owner_id FROM {$quizzes_table} ORDER BY owner_id ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( empty( $authors ) ) {
			return;
		}

		?>
		<label class="screen-reader-text" for="filter-by-author"><?php esc_html_e( 'Filter by author', 'pressprimer-quiz' ); ?></label>
		<select name="author" id="filter-by-author">
			<option value="all"><?php esc_html_e( 'All Authors', 'pressprimer-quiz' ); ?></option>
			<?php foreach ( $authors as $author ) : ?>
				<?php
				$user = get_userdata( $author->owner_id );
				if ( ! $user ) {
					continue;
				}
				?>
				<option value="<?php echo esc_attr( $author->owner_id ); ?>" <?php selected( $current_author, $author->owner_id ); ?>>
					<?php echo esc_html( $user->display_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}
}
