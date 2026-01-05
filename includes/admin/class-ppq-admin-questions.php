<?php
/**
 * Questions admin class
 *
 * Handles the questions list and management interface.
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
 * Questions admin class
 *
 * Manages the questions list table and edit interface.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Admin_Questions {

	/**
	 * List table instance
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Quiz_Questions_List_Table
	 */
	private $list_table;

	/**
	 * Initialize questions admin
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_init', [ $this, 'handle_actions' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );

		// Add screen options on the right hook
		add_action( 'current_screen', [ $this, 'maybe_add_screen_options' ] );
	}

	/**
	 * Maybe add screen options based on current screen
	 *
	 * @since 1.0.0
	 */
	public function maybe_add_screen_options() {
		$screen = get_current_screen();

		// Only add screen options on the questions list page
		if ( $screen && $screen->id === 'pressprimer-quiz_page_pressprimer-quiz-questions' ) {
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for display routing
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( in_array( $action, [ 'new', 'edit' ], true ) ) {
			return;
		}

		// Add per page option
		add_screen_option(
			'per_page',
			[
				'label'   => __( 'Questions per page', 'pressprimer-quiz' ),
				'default' => 100,
				'option'  => 'pressprimer_quiz_questions_per_page',
			]
		);

		// Instantiate the table and store it
		$this->list_table = new PressPrimer_Quiz_Questions_List_Table();

		// Get screen and register columns with it
		$screen = get_current_screen();
		if ( $screen ) {
			// Get columns from the table
			$columns = $this->list_table->get_columns();

			// Register columns with the screen
			add_filter(
				"manage_{$screen->id}_columns",
				function () use ( $columns ) {
					return $columns;
				}
			);
		}

		// Set up filter for saving screen option
		add_filter( 'set-screen-option', [ $this, 'set_screen_option' ], 10, 3 );
	}

	/**
	 * Set screen option
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $status Screen option value.
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return mixed
	 */
	public function set_screen_option( $status, $option, $value ) {
		if ( 'pressprimer_quiz_questions_per_page' === $option ) {
			return $value;
		}

		return $status;
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for page routing
		if ( ! isset( $_GET['page'] ) || 'pressprimer-quiz-questions' !== $_GET['page'] ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only notice flags from redirect
		// Saved notice
		if ( isset( $_GET['saved'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'Question saved successfully.', 'pressprimer-quiz' )
			);
		}

		// Duplicated notice
		if ( isset( $_GET['duplicated'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'Question duplicated successfully.', 'pressprimer-quiz' )
			);
		}

		// Deleted notice
		if ( isset( $_GET['deleted'] ) ) {
			$count = absint( wp_unslash( $_GET['deleted'] ) );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of questions deleted */
						_n( '%d question deleted.', '%d questions deleted.', $count, 'pressprimer-quiz' ),
						$count
					)
				)
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Render questions page
	 *
	 * @since 1.0.0
	 */
	public function render() {
		// Check capability
		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-quiz' ),
				esc_html__( 'Permission Denied', 'pressprimer-quiz' ),
				[ 'response' => 403 ]
			);
		}

		// Check if we're editing or creating
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for display routing
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( in_array( $action, [ 'new', 'edit' ], true ) ) {
			$this->render_editor();
		} else {
			$this->render_list();
		}
	}

	/**
	 * Render questions list
	 *
	 * @since 1.0.0
	 */
	private function render_list() {
		// Reuse the list table instance if it exists, otherwise create new one
		if ( ! $this->list_table ) {
			$this->list_table = new PressPrimer_Quiz_Questions_List_Table();
		}

		$this->list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Questions', 'pressprimer-quiz' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=pressprimer-quiz-questions&action=new' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'pressprimer-quiz' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php $this->list_table->views(); ?>

			<form method="get">
				<input type="hidden" name="page" value="pressprimer-quiz-questions">
				<?php
				$this->list_table->search_box( __( 'Search Questions', 'pressprimer-quiz' ), 'ppq-question' );
				$this->list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render question editor (React version)
	 *
	 * @since 1.0.0
	 */
	private function render_editor() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only ID for loading question to edit
		$question_id = isset( $_GET['question'] ) ? absint( wp_unslash( $_GET['question'] ) ) : 0;
		$question    = null;

		// Load existing question
		if ( $question_id > 0 ) {
			$question = PressPrimer_Quiz_Question::get( $question_id );

			if ( ! $question ) {
				wp_die( esc_html__( 'Question not found.', 'pressprimer-quiz' ) );
			}

			// Check permission
			if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $question->author_id ) !== get_current_user_id() ) {
				wp_die( esc_html__( 'You do not have permission to edit this question.', 'pressprimer-quiz' ) );
			}
		}

		// Enqueue React editor
		$this->enqueue_react_editor( $question_id );

		?>
		<!-- React Editor Root -->
		<div id="ppq-question-editor-root"></div>
		<?php
	}

	/**
	 * Enqueue React editor
	 *
	 * @since 1.0.0
	 *
	 * @param int $question_id Question ID (0 for new).
	 */
	private function enqueue_react_editor( $question_id = 0 ) {
		// Enqueue WordPress editor
		wp_enqueue_editor();
		wp_enqueue_media();

		// Enqueue Ant Design CSS
		wp_enqueue_style(
			'antd',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/css/vendor/antd-reset.css',
			[],
			'5.12.0'
		);

		// Enqueue built React app
		wp_enqueue_script(
			'ppq-question-editor',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/question-editor.js',
			[ 'wp-element', 'wp-i18n', 'wp-api-fetch', 'wp-editor' ],
			PRESSPRIMER_QUIZ_VERSION,
			true
		);

		wp_enqueue_style(
			'ppq-question-editor',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/question-editor.css',
			[],
			PRESSPRIMER_QUIZ_VERSION
		);

		// Prepare question data for React
		$question_data = [];

		if ( $question_id > 0 ) {
			$question = PressPrimer_Quiz_Question::get( $question_id );
			if ( $question ) {
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

				$answers = $revision ? $revision->get_answers() : [];

				// Convert answer format for React
				$answer_index  = 0;
				$react_answers = array_map(
					function ( $answer ) use ( &$answer_index ) {
						$answer_index++;
						return [
							'id'        => $answer['id'] ?? 'answer_' . $answer_index,
							'text'      => $answer['text'] ?? '',
							'isCorrect' => $answer['is_correct'] ?? false,
							'feedback'  => $answer['feedback'] ?? '',
							'order'     => $answer['order'] ?? $answer_index,
						];
					},
					$answers
				);

				$question_data = [
					'id'                => $question->id,
					'type'              => $question->type,
					'difficulty'        => $question->difficulty_author,
					'timeLimit'         => $question->expected_seconds,
					'points'            => $question->max_points,
					'stem'              => $revision ? $revision->stem : '',
					'answers'           => $react_answers,
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
			}
		}

		// Localize script with data
		wp_localize_script(
			'ppq-question-editor',
			'pressprimerQuizQuestionData',
			$question_data
		);

		// Also pass admin URL
		wp_localize_script(
			'ppq-question-editor',
			'pressprimerQuizAdmin',
			[
				'adminUrl' => admin_url(),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			]
		);
	}

	/**
	 * Handle admin actions
	 *
	 * @since 1.0.0
	 */
	public function handle_actions() {
		// Only on questions page
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check; nonce verified in individual handlers
		if ( ! isset( $_GET['page'] ) || 'pressprimer-quiz-questions' !== $_GET['page'] ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified in individual action handlers below
		// Handle duplicate action
		if ( isset( $_GET['action'] ) && 'duplicate' === $_GET['action'] && isset( $_GET['question'] ) ) {
			$this->handle_duplicate();
		}

		// Handle delete action
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['question'] ) ) {
			$this->handle_delete();
		}

		// Handle bulk actions
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['questions'] ) ) {
			$this->handle_bulk_delete();
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}


	/**
	 * Handle single question delete
	 *
	 * @since 1.0.0
	 */
	private function handle_delete() {
		if ( ! isset( $_GET['question'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'pressprimer-quiz' ) );
		}

		$question_id_raw = absint( wp_unslash( $_GET['question'] ) );
		check_admin_referer( 'delete-question_' . $question_id_raw );

		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete questions.', 'pressprimer-quiz' ) );
		}

		$question_id = absint( wp_unslash( $_GET['question'] ) );
		$question    = PressPrimer_Quiz_Question::get( $question_id );

		if ( ! $question ) {
			wp_die( esc_html__( 'Question not found.', 'pressprimer-quiz' ) );
		}

		// Check permission
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $question->author_id ) !== get_current_user_id() ) {
			wp_die( esc_html__( 'You do not have permission to delete this question.', 'pressprimer-quiz' ) );
		}

		// Soft delete
		$result = $question->delete();

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		// Clear dashboard stats cache
		if ( class_exists( 'PressPrimer_Quiz_Statistics_Service' ) ) {
			PressPrimer_Quiz_Statistics_Service::clear_dashboard_cache();
		}

		wp_safe_redirect( add_query_arg( 'deleted', '1', admin_url( 'admin.php?page=pressprimer-quiz-questions' ) ) );
		exit;
	}

	/**
	 * Handle bulk delete
	 *
	 * @since 1.0.0
	 */
	private function handle_bulk_delete() {
		check_admin_referer( 'bulk-questions' );

		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete questions.', 'pressprimer-quiz' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Validated above via check_admin_referer
		$question_ids = isset( $_GET['questions'] ) ? array_map( 'absint', wp_unslash( $_GET['questions'] ) ) : [];
		$deleted      = 0;

		foreach ( $question_ids as $question_id ) {
			$question = PressPrimer_Quiz_Question::get( $question_id );

			if ( ! $question ) {
				continue;
			}

			// Check permission
			if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $question->author_id ) !== get_current_user_id() ) {
				continue;
			}

			$result = $question->delete();

			if ( ! is_wp_error( $result ) ) {
				++$deleted;
			}
		}

		// Clear dashboard stats cache if any questions were deleted
		if ( $deleted > 0 && class_exists( 'PressPrimer_Quiz_Statistics_Service' ) ) {
			PressPrimer_Quiz_Statistics_Service::clear_dashboard_cache();
		}

		wp_safe_redirect( add_query_arg( 'deleted', $deleted, admin_url( 'admin.php?page=pressprimer-quiz-questions' ) ) );
		exit;
	}

	/**
	 * Handle question duplicate
	 *
	 * @since 1.0.0
	 */
	private function handle_duplicate() {
		if ( ! isset( $_GET['question'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'pressprimer-quiz' ) );
		}

		$question_id_raw = absint( wp_unslash( $_GET['question'] ) );
		check_admin_referer( 'duplicate-question_' . $question_id_raw );

		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate questions.', 'pressprimer-quiz' ) );
		}

		$question_id = absint( wp_unslash( $_GET['question'] ) );
		$question    = PressPrimer_Quiz_Question::get( $question_id );

		if ( ! $question ) {
			wp_die( esc_html__( 'Question not found.', 'pressprimer-quiz' ) );
		}

		// Check permission
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $question->author_id ) !== get_current_user_id() ) {
			wp_die( esc_html__( 'You do not have permission to duplicate this question.', 'pressprimer-quiz' ) );
		}

		// Get current revision
		$revision = $question->get_current_revision();

		if ( ! $revision ) {
			wp_die( esc_html__( 'Question revision not found.', 'pressprimer-quiz' ) );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Create new question using static create method
			$new_question_id = PressPrimer_Quiz_Question::create(
				[
					'uuid'              => wp_generate_uuid4(),
					'type'              => $question->type,
					'difficulty_author' => $question->difficulty_author,
					'expected_seconds'  => $question->expected_seconds,
					'max_points'        => $question->max_points,
					'author_id'         => get_current_user_id(), // Current user becomes author of duplicate
					'status'            => 'draft', // Start as draft
				]
			);

			if ( is_wp_error( $new_question_id ) ) {
				throw new Exception( $new_question_id->get_error_message() );
			}

			// Load the newly created question
			$new_question = PressPrimer_Quiz_Question::get( $new_question_id );

			if ( ! $new_question ) {
				throw new Exception( __( 'Failed to load duplicated question.', 'pressprimer-quiz' ) );
			}

			// Create new revision with duplicated content
			$answers = $revision->get_answers();

			// Regenerate answer IDs
			$new_answers = [];
			foreach ( $answers as $answer ) {
				$new_answer       = $answer;
				$new_answer['id'] = 'a' . wp_rand( 1000, 9999 );
				$new_answers[]    = $new_answer;
			}

			// Prepend "Copy of" to stem to indicate it's a duplicate
			$duplicated_stem = $revision->stem;
			if ( 0 !== strpos( wp_strip_all_tags( $duplicated_stem ), 'Copy of ' ) ) {
				// If it's a simple text stem, prepend
				if ( wp_strip_all_tags( $duplicated_stem ) === $duplicated_stem ) {
					$duplicated_stem = 'Copy of ' . $duplicated_stem;
				} else {
					// If it has HTML, wrap in a paragraph at the start
					$duplicated_stem = '<p><strong>Copy of:</strong></p>' . $duplicated_stem;
				}
			}

			$new_revision                     = new PressPrimer_Quiz_Question_Revision();
			$new_revision->question_id        = $new_question->id;
			$new_revision->version            = 1;
			$new_revision->stem               = $duplicated_stem;
			$new_revision->answers_json       = wp_json_encode( $new_answers );
			$new_revision->settings_json      = $revision->settings_json;
			$new_revision->feedback_correct   = $revision->feedback_correct;
			$new_revision->feedback_incorrect = $revision->feedback_incorrect;
			$new_revision->content_hash       = PressPrimer_Quiz_Question_Revision::generate_hash( $new_revision->stem, $new_answers );
			$new_revision->created_by         = get_current_user_id();

			$result = $new_revision->save();

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Update question to point to this revision
			$new_question->current_revision_id = $new_revision->id;
			$result                            = $new_question->save();

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Duplicate categories
			$categories = $question->get_categories();
			if ( ! empty( $categories ) ) {
				$category_ids = array_map(
					function ( $cat ) {
						return $cat->id;
					},
					$categories
				);
				$new_question->set_categories( $category_ids );
			}

			// Duplicate tags
			$tags = $question->get_tags();
			if ( ! empty( $tags ) ) {
				$tag_ids = array_map(
					function ( $tag ) {
						return $tag->id;
					},
					$tags
				);
				$new_question->set_tags( $tag_ids );
			}

			// Duplicate bank memberships
			$bank_memberships_table = $wpdb->prefix . 'ppq_bank_questions';
			$memberships            = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safely constructed from $wpdb->prefix
				$wpdb->prepare(
					"SELECT bank_id FROM {$bank_memberships_table} WHERE question_id = %d",
					$question_id
				)
			);

			if ( ! empty( $memberships ) ) {
				foreach ( $memberships as $membership ) {
					$wpdb->insert(
						$bank_memberships_table,
						[
							'bank_id'     => $membership->bank_id,
							'question_id' => $new_question->id,
							'added_at'    => current_time( 'mysql' ),
						],
						[ '%d', '%d', '%s' ]
					);

					// Update bank question count
					if ( class_exists( 'PressPrimer_Quiz_Bank' ) ) {
						$bank = PressPrimer_Quiz_Bank::get( $membership->bank_id );
						if ( $bank ) {
							$bank->update_question_count();
						}
					}
				}
			}

			$wpdb->query( 'COMMIT' );

			// Redirect to edit the new question
			wp_safe_redirect(
				add_query_arg(
					[
						'page'       => 'pressprimer-quiz-questions',
						'action'     => 'edit',
						'question'   => $new_question->id,
						'duplicated' => '1',
					],
					admin_url( 'admin.php' )
				)
			);
			exit;

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			wp_die( esc_html( $e->getMessage() ) );
		}
	}
}

/**
 * Questions list table class
 *
 * Displays questions in a filterable, sortable table.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Questions_List_Table extends WP_List_Table {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'question',
				'plural'   => 'questions',
				'ajax'     => false,
			]
		);
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
			'cb'         => '<input type="checkbox" />',
			'id'         => __( 'ID', 'pressprimer-quiz' ),
			'question'   => __( 'Question', 'pressprimer-quiz' ),
			'type'       => __( 'Type', 'pressprimer-quiz' ),
			'difficulty' => __( 'Difficulty', 'pressprimer-quiz' ),
			'categories' => __( 'Categories', 'pressprimer-quiz' ),
			'banks'      => __( 'Banks', 'pressprimer-quiz' ),
			'author'     => __( 'Author', 'pressprimer-quiz' ),
			'date'       => __( 'Date', 'pressprimer-quiz' ),
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
		$hidden = get_user_option( 'managepressprimer-quiz_page_pressprimer-quiz-questionscolumnshidden' );

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
			'id'         => [ 'id', true ],
			'type'       => [ 'type', false ],
			'difficulty' => [ 'difficulty_author', false ],
			'author'     => [ 'author_id', false ],
			'date'       => [ 'created_at', false ],
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
			'delete' => __( 'Delete', 'pressprimer-quiz' ),
		];
	}

	/**
	 * Prepare items for display
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		global $wpdb;

		$per_page     = $this->get_items_per_page( 'pressprimer_quiz_questions_per_page', 100 );
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// Build query
		$questions_table = $wpdb->prefix . 'ppq_questions';
		$where_clauses   = [ 'deleted_at IS NULL' ];
		$where_values    = [];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters for list table display
		// Filter by type
		$get_type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		if ( '' !== $get_type ) {
			$where_clauses[] = 'type = %s';
			$where_values[]  = $get_type;
		}

		// Filter by difficulty
		$get_difficulty = isset( $_GET['difficulty'] ) ? sanitize_key( wp_unslash( $_GET['difficulty'] ) ) : '';
		if ( '' !== $get_difficulty ) {
			$where_clauses[] = 'difficulty_author = %s';
			$where_values[]  = $get_difficulty;
		}

		// Filter by category
		$get_category = isset( $_GET['category'] ) ? absint( wp_unslash( $_GET['category'] ) ) : 0;
		if ( $get_category > 0 ) {
			$taxonomy_table = $wpdb->prefix . 'ppq_question_tax';

			$where_clauses[] = "id IN (
				SELECT question_id FROM {$taxonomy_table} WHERE category_id = %d
			)";
			$where_values[]  = $get_category;
		}

		// Filter by bank
		$get_bank = isset( $_GET['bank'] ) ? absint( wp_unslash( $_GET['bank'] ) ) : 0;
		if ( $get_bank > 0 ) {
			$bank_questions_table = $wpdb->prefix . 'ppq_bank_questions';

			$where_clauses[] = "id IN (
				SELECT question_id FROM {$bank_questions_table} WHERE bank_id = %d
			)";
			$where_values[]  = $get_bank;
		}

		// Filter by author
		$get_author = isset( $_GET['author'] ) ? absint( wp_unslash( $_GET['author'] ) ) : 0;
		if ( $get_author > 0 ) {
			$where_clauses[] = 'author_id = %d';
			$where_values[]  = $get_author;
		} elseif ( ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			// Non-admins see only their own questions
			$where_clauses[] = 'author_id = %d';
			$where_values[]  = get_current_user_id();
		}

		// Search
		if ( isset( $_GET['s'] ) && '' !== $_GET['s'] ) {
			$revisions_table = $wpdb->prefix . 'ppq_question_revisions';
			$search_term     = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) . '%';

			// Search in stem via revision
			$where_clauses[] = "current_revision_id IN (
				SELECT id FROM {$revisions_table} WHERE stem LIKE %s
			)";
			$where_values[]  = $search_term;
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

		// Order by - validate against allowed fields
		$allowed_orderby = [ 'id', 'type', 'difficulty_author', 'author_id', 'created_at', 'updated_at' ];
		$orderby         = isset( $_GET['orderby'] ) && '' !== $_GET['orderby'] ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'created_at';
		$order           = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$order_sql = sanitize_sql_orderby( "{$orderby} {$order}" );
		$order_sql = $order_sql ? "ORDER BY {$order_sql}" : 'ORDER BY created_at DESC';

		// Count total items
		$count_query = "SELECT COUNT(*) FROM {$questions_table} {$where_sql}";
		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare( $count_query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total_items = $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Get items
		$query        = "SELECT * FROM {$questions_table} {$where_sql} {$order_sql} LIMIT %d OFFSET %d";
		$query_values = array_merge( $where_values, [ $per_page, $offset ] );
		$query        = $wpdb->prepare( $query, $query_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rows = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Convert to question objects
		$items = [];
		foreach ( $rows as $row ) {
			$items[] = PressPrimer_Quiz_Question::from_row( $row );
		}

		$this->items = $items;

		// Set pagination
		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			]
		);

		// Set columns
		$this->_column_headers = [
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns(),
		];
	}

	/**
	 * Display extra tablenav
	 *
	 * @since 1.0.0
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		?>
		<div class="alignleft actions">
			<?php $this->render_type_filter(); ?>
			<?php $this->render_difficulty_filter(); ?>
			<?php $this->render_category_filter(); ?>
			<?php $this->render_bank_filter(); ?>
			<?php if ( current_user_can( 'pressprimer_quiz_manage_all' ) ) : ?>
				<?php $this->render_author_filter(); ?>
			<?php endif; ?>
			<?php submit_button( __( 'Filter', 'pressprimer-quiz' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Render type filter
	 *
	 * @since 1.0.0
	 */
	private function render_type_filter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state for selected()
		$current_type = isset( $_GET['type'] ) && '' !== $_GET['type'] ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';

		?>
		<select name="type">
			<option value=""><?php esc_html_e( 'All Types', 'pressprimer-quiz' ); ?></option>
			<option value="mc" <?php selected( $current_type, 'mc' ); ?>>
				<?php esc_html_e( 'Multiple Choice', 'pressprimer-quiz' ); ?>
			</option>
			<option value="ma" <?php selected( $current_type, 'ma' ); ?>>
				<?php esc_html_e( 'Multiple Answer', 'pressprimer-quiz' ); ?>
			</option>
			<option value="tf" <?php selected( $current_type, 'tf' ); ?>>
				<?php esc_html_e( 'True/False', 'pressprimer-quiz' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Render difficulty filter
	 *
	 * @since 1.0.0
	 */
	private function render_difficulty_filter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state for selected()
		$current_difficulty = isset( $_GET['difficulty'] ) && '' !== $_GET['difficulty'] ? sanitize_key( wp_unslash( $_GET['difficulty'] ) ) : '';

		?>
		<select name="difficulty">
			<option value=""><?php esc_html_e( 'All Difficulties', 'pressprimer-quiz' ); ?></option>
			<option value="beginner" <?php selected( $current_difficulty, 'beginner' ); ?>>
				<?php esc_html_e( 'Beginner', 'pressprimer-quiz' ); ?>
			</option>
			<option value="intermediate" <?php selected( $current_difficulty, 'intermediate' ); ?>>
				<?php esc_html_e( 'Intermediate', 'pressprimer-quiz' ); ?>
			</option>
			<option value="advanced" <?php selected( $current_difficulty, 'advanced' ); ?>>
				<?php esc_html_e( 'Advanced', 'pressprimer-quiz' ); ?>
			</option>
			<option value="expert" <?php selected( $current_difficulty, 'expert' ); ?>>
				<?php esc_html_e( 'Expert', 'pressprimer-quiz' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Render category filter
	 *
	 * @since 1.0.0
	 */
	private function render_category_filter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state for selected()
		$current_category = isset( $_GET['category'] ) && '' !== $_GET['category'] ? absint( wp_unslash( $_GET['category'] ) ) : 0;
		$categories       = PressPrimer_Quiz_Category::get_all( 'category' );

		if ( empty( $categories ) ) {
			return;
		}

		?>
		<select name="category">
			<option value=""><?php esc_html_e( 'All Categories', 'pressprimer-quiz' ); ?></option>
			<?php foreach ( $categories as $category ) : ?>
				<option value="<?php echo esc_attr( $category->id ); ?>" <?php selected( $current_category, $category->id ); ?>>
					<?php echo esc_html( $category->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render bank filter
	 *
	 * @since 1.0.0
	 */
	private function render_bank_filter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state for selected()
		$current_bank = isset( $_GET['bank'] ) && '' !== $_GET['bank'] ? absint( wp_unslash( $_GET['bank'] ) ) : 0;
		$user_id      = get_current_user_id();
		$banks        = PressPrimer_Quiz_Bank::get_for_user( $user_id );

		if ( empty( $banks ) ) {
			return;
		}

		?>
		<select name="bank">
			<option value=""><?php esc_html_e( 'All Banks', 'pressprimer-quiz' ); ?></option>
			<?php foreach ( $banks as $bank ) : ?>
				<option value="<?php echo esc_attr( $bank->id ); ?>" <?php selected( $current_bank, $bank->id ); ?>>
					<?php echo esc_html( $bank->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render author filter
	 *
	 * Only shows users who have created at least one question.
	 *
	 * @since 1.0.0
	 */
	private function render_author_filter() {
		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state for selected()
		$current_author = isset( $_GET['author'] ) && '' !== $_GET['author'] ? absint( wp_unslash( $_GET['author'] ) ) : 0;

		// Get only users who have created questions
		$questions_table = $wpdb->prefix . 'ppq_questions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$author_ids = $wpdb->get_col(
			"SELECT DISTINCT author_id FROM {$questions_table} WHERE deleted_at IS NULL AND author_id IS NOT NULL"
		);

		if ( empty( $author_ids ) ) {
			// No authors found, show empty dropdown
			echo '<select name="author"><option value="">' . esc_html__( 'All Authors', 'pressprimer-quiz' ) . '</option></select>';
			return;
		}

		wp_dropdown_users(
			[
				'name'             => 'author',
				'show_option_all'  => __( 'All Authors', 'pressprimer-quiz' ),
				'selected'         => $current_author,
				'include_selected' => true,
				'include'          => $author_ids,
			]
		);
	}

	/**
	 * Column: Checkbox
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Question $item Question object.
	 * @return string Checkbox HTML.
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="questions[]" value="%d" />',
			$item->id
		);
	}

	/**
	 * Column: ID
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Question $item Question object.
	 * @return string Column content.
	 */
	public function column_id( $item ) {
		return absint( $item->id );
	}

	/**
	 * Column: Question
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Question $item Question object.
	 * @return string Column content.
	 */
	public function column_question( $item ) {
		$revision = $item->get_current_revision();
		$stem     = $revision ? $revision->stem : __( '(No content)', 'pressprimer-quiz' );

		// Strip HTML tags and get plain text
		$plain_text = wp_strip_all_tags( $stem );

		// Get first sentence (up to first period, question mark, or exclamation)
		if ( preg_match( '/^.+?[.!?](?:\s|$)/', $plain_text, $matches ) ) {
			$first_sentence = trim( $matches[0] );
		} else {
			// If no sentence ending found, use the whole text
			$first_sentence = $plain_text;
		}

		// Truncate to 150 characters if still too long
		$truncated = mb_strlen( $first_sentence ) > 150
			? mb_substr( $first_sentence, 0, 150 ) . '...'
			: $first_sentence;

		// Build row actions
		$actions = [];

		// Check if user can edit this question
		$can_edit = current_user_can( 'pressprimer_quiz_manage_all' ) || ( current_user_can( 'pressprimer_quiz_manage_own' ) && absint( $item->author_id ) === get_current_user_id() );

		if ( $can_edit ) {
			$actions['edit'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=pressprimer-quiz-questions&action=edit&question=' . $item->id ) ),
				esc_html__( 'Edit', 'pressprimer-quiz' )
			);
		}

		// Users can duplicate any question they can view (to create their own copy)
		if ( current_user_can( 'pressprimer_quiz_manage_own' ) ) {
			$actions['duplicate'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url(
					wp_nonce_url(
						admin_url( 'admin.php?page=pressprimer-quiz-questions&action=duplicate&question=' . $item->id ),
						'duplicate-question_' . $item->id
					)
				),
				esc_html__( 'Duplicate', 'pressprimer-quiz' )
			);
		}

		if ( $can_edit ) {
			$actions['delete'] = sprintf(
				'<a href="%s" class="ppq-delete-confirm">%s</a>',
				esc_url(
					wp_nonce_url(
						admin_url( 'admin.php?page=pressprimer-quiz-questions&action=delete&question=' . $item->id ),
						'delete-question_' . $item->id
					)
				),
				esc_html__( 'Delete', 'pressprimer-quiz' )
			);
		}

		return sprintf(
			'<strong>%s</strong>%s',
			esc_html( $truncated ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Column: Type
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Question $item Question object.
	 * @return string Column content.
	 */
	public function column_type( $item ) {
		$types = [
			'mc' => __( 'Multiple Choice', 'pressprimer-quiz' ),
			'ma' => __( 'Multiple Answer', 'pressprimer-quiz' ),
			'tf' => __( 'True/False', 'pressprimer-quiz' ),
		];

		return isset( $types[ $item->type ] ) ? esc_html( $types[ $item->type ] ) : esc_html( $item->type );
	}

	/**
	 * Column: Difficulty
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Question $item Question object.
	 * @return string Column content.
	 */
	public function column_difficulty( $item ) {
		if ( empty( $item->difficulty_author ) ) {
			return '—';
		}

		$difficulties = [
			'beginner'     => __( 'Beginner', 'pressprimer-quiz' ),
			'intermediate' => __( 'Intermediate', 'pressprimer-quiz' ),
			'advanced'     => __( 'Advanced', 'pressprimer-quiz' ),
			'expert'       => __( 'Expert', 'pressprimer-quiz' ),
		];

		return isset( $difficulties[ $item->difficulty_author ] )
			? esc_html( $difficulties[ $item->difficulty_author ] )
			: esc_html( $item->difficulty_author );
	}

	/**
	 * Column: Categories
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Question $item Question object.
	 * @return string Column content.
	 */
	public function column_categories( $item ) {
		$categories = $item->get_categories();

		if ( empty( $categories ) ) {
			return '—';
		}

		$category_names = array_map(
			function ( $cat ) {
				return esc_html( $cat->name );
			},
			$categories
		);

		return implode( ', ', $category_names );
	}

	/**
	 * Column: Banks
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Question $item Question object.
	 * @return string Column content.
	 */
	public function column_banks( $item ) {
		global $wpdb;

		$bank_questions_table = $wpdb->prefix . 'ppq_bank_questions';
		$banks_table          = $wpdb->prefix . 'ppq_banks';

		$banks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.id, b.name
				FROM {$bank_questions_table} bq
				INNER JOIN {$banks_table} b ON bq.bank_id = b.id
				WHERE bq.question_id = %d
				LIMIT 3", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$item->id
			)
		);

		if ( empty( $banks ) ) {
			return '—';
		}

		$bank_names = array_map(
			function ( $bank ) {
				return esc_html( $bank->name );
			},
			$banks
		);

		return implode( ', ', $bank_names );
	}

	/**
	 * Column: Author
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Question $item Question object.
	 * @return string Column content.
	 */
	public function column_author( $item ) {
		$author = get_userdata( $item->author_id );

		if ( ! $author ) {
			return '—';
		}

		if ( current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			return sprintf(
				'<a href="%s">%s</a>',
				esc_url( add_query_arg( 'author', $item->author_id ) ),
				esc_html( $author->display_name )
			);
		}

		return esc_html( $author->display_name );
	}

	/**
	 * Column: Date
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Question $item Question object.
	 * @return string Column content.
	 */
	public function column_date( $item ) {
		$timestamp = strtotime( $item->created_at );

		if ( ! $timestamp ) {
			return '—';
		}

		return sprintf(
			'<abbr title="%s">%s</abbr>',
			esc_attr( wp_date( 'Y/m/d g:i:s a', $timestamp ) ),
			esc_html( wp_date( 'Y/m/d', $timestamp ) )
		);
	}

	/**
	 * Message to display when no items
	 *
	 * @since 1.0.0
	 */
	public function no_items() {
		esc_html_e( 'No questions found.', 'pressprimer-quiz' );
	}
}
