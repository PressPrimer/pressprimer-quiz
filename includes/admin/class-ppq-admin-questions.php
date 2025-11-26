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
class PPQ_Admin_Questions {

	/**
	 * Initialize questions admin
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_init', [ $this, 'handle_actions' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		if ( ! isset( $_GET['page'] ) || 'ppq-questions' !== $_GET['page'] ) {
			return;
		}

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
			$count = absint( $_GET['deleted'] );
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
	}

	/**
	 * Render questions page
	 *
	 * @since 1.0.0
	 */
	public function render() {
		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) && ! current_user_can( 'ppq_manage_all' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-quiz' ),
				esc_html__( 'Permission Denied', 'pressprimer-quiz' ),
				[ 'response' => 403 ]
			);
		}

		// Check if we're editing or creating
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

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
		$list_table = new PPQ_Questions_List_Table();
		$list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Questions', 'pressprimer-quiz' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-questions&action=new' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'pressprimer-quiz' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php $list_table->views(); ?>

			<form method="get">
				<input type="hidden" name="page" value="ppq-questions">
				<?php
				$list_table->search_box( __( 'Search Questions', 'pressprimer-quiz' ), 'ppq-question' );
				$list_table->display();
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
		$question_id = isset( $_GET['question'] ) ? absint( $_GET['question'] ) : 0;
		$question    = null;

		// Load existing question
		if ( $question_id > 0 ) {
			$question = PPQ_Question::get( $question_id );

			if ( ! $question ) {
				wp_die( esc_html__( 'Question not found.', 'pressprimer-quiz' ) );
			}

			// Check permission
			if ( ! current_user_can( 'ppq_manage_all' ) && absint( $question->author_id ) !== get_current_user_id() ) {
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
			'https://cdn.jsdelivr.net/npm/antd@5.12.0/dist/reset.css',
			[],
			'5.12.0'
		);

		// Enqueue built React app
		wp_enqueue_script(
			'ppq-question-editor',
			PPQ_PLUGIN_URL . 'build/question-editor.js',
			[ 'wp-element', 'wp-i18n', 'wp-api-fetch', 'wp-editor' ],
			PPQ_VERSION,
			true
		);

		wp_enqueue_style(
			'ppq-question-editor',
			PPQ_PLUGIN_URL . 'build/question-editor.css',
			[],
			PPQ_VERSION
		);

		// Prepare question data for React
		$question_data = [];

		if ( $question_id > 0 ) {
			$question = PPQ_Question::get( $question_id );
			if ( $question ) {
				$revision = $question->get_current_revision();
				$categories = $question->get_categories();
				$tags = $question->get_tags();

				// Get bank memberships
				global $wpdb;
				$bank_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT bank_id FROM {$wpdb->prefix}ppq_bank_memberships WHERE question_id = %d",
						$question_id
					)
				);

				$answers = $revision ? $revision->get_answers() : [];

				// Convert answer format for React
				$react_answers = array_map( function( $answer ) {
					return [
						'id'        => $answer['id'],
						'text'      => $answer['text'],
						'isCorrect' => $answer['is_correct'] ?? false,
						'feedback'  => $answer['feedback'] ?? '',
						'order'     => $answer['order'] ?? 1,
					];
				}, $answers );

				$question_data = [
					'id'                => $question->id,
					'type'              => $question->type,
					'difficulty'        => $question->difficulty,
					'timeLimit'         => $question->time_limit,
					'points'            => $question->points,
					'stem'              => $revision ? $revision->stem : '',
					'answers'           => $react_answers,
					'feedbackCorrect'   => $revision ? $revision->feedback_correct : '',
					'feedbackIncorrect' => $revision ? $revision->feedback_incorrect : '',
					'categories'        => array_map( function( $cat ) { return $cat->id; }, $categories ),
					'tags'              => array_map( function( $tag ) { return $tag->id; }, $tags ),
					'banks'             => array_map( 'absint', $bank_ids ),
				];
			}
		}

		// Localize script with data
		wp_localize_script(
			'ppq-question-editor',
			'ppqQuestionData',
			$question_data
		);

		// Also pass admin URL
		wp_localize_script(
			'ppq-question-editor',
			'ppqAdmin',
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
		if ( ! isset( $_GET['page'] ) || 'ppq-questions' !== $_GET['page'] ) {
			return;
		}

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
	}


	/**
	 * Handle single question delete
	 *
	 * @since 1.0.0
	 */
	private function handle_delete() {
		check_admin_referer( 'delete-question_' . $_GET['question'] );

		if ( ! current_user_can( 'ppq_manage_own' ) && ! current_user_can( 'ppq_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete questions.', 'pressprimer-quiz' ) );
		}

		$question_id = absint( $_GET['question'] );
		$question    = PPQ_Question::get( $question_id );

		if ( ! $question ) {
			wp_die( esc_html__( 'Question not found.', 'pressprimer-quiz' ) );
		}

		// Check permission
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $question->author_id ) !== get_current_user_id() ) {
			wp_die( esc_html__( 'You do not have permission to delete this question.', 'pressprimer-quiz' ) );
		}

		// Soft delete
		$result = $question->delete();

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		wp_safe_redirect( add_query_arg( 'deleted', '1', admin_url( 'admin.php?page=ppq-questions' ) ) );
		exit;
	}

	/**
	 * Handle bulk delete
	 *
	 * @since 1.0.0
	 */
	private function handle_bulk_delete() {
		check_admin_referer( 'bulk-questions' );

		if ( ! current_user_can( 'ppq_manage_own' ) && ! current_user_can( 'ppq_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete questions.', 'pressprimer-quiz' ) );
		}

		$question_ids = array_map( 'absint', $_GET['questions'] );
		$deleted      = 0;

		foreach ( $question_ids as $question_id ) {
			$question = PPQ_Question::get( $question_id );

			if ( ! $question ) {
				continue;
			}

			// Check permission
			if ( ! current_user_can( 'ppq_manage_all' ) && absint( $question->author_id ) !== get_current_user_id() ) {
				continue;
			}

			$result = $question->delete();

			if ( ! is_wp_error( $result ) ) {
				$deleted++;
			}
		}

		wp_safe_redirect( add_query_arg( 'deleted', $deleted, admin_url( 'admin.php?page=ppq-questions' ) ) );
		exit;
	}

	/**
	 * Handle question duplicate
	 *
	 * @since 1.0.0
	 */
	private function handle_duplicate() {
		check_admin_referer( 'duplicate-question_' . $_GET['question'] );

		if ( ! current_user_can( 'ppq_manage_own' ) && ! current_user_can( 'ppq_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate questions.', 'pressprimer-quiz' ) );
		}

		$question_id = absint( $_GET['question'] );
		$question    = PPQ_Question::get( $question_id );

		if ( ! $question ) {
			wp_die( esc_html__( 'Question not found.', 'pressprimer-quiz' ) );
		}

		// Check permission
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $question->author_id ) !== get_current_user_id() ) {
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
			// Create new question
			$new_question = new PPQ_Question();
			$new_question->uuid = wp_generate_uuid4();
			$new_question->type = $question->type;
			$new_question->difficulty = $question->difficulty;
			$new_question->time_limit = $question->time_limit;
			$new_question->points = $question->points;
			$new_question->author_id = get_current_user_id(); // Current user becomes author of duplicate
			$new_question->status = 'draft'; // Start as draft

			$result = $new_question->save();

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Create new revision with duplicated content
			$answers = $revision->get_answers();

			// Regenerate answer IDs
			$new_answers = [];
			foreach ( $answers as $answer ) {
				$new_answer = $answer;
				$new_answer['id'] = 'a' . wp_rand( 1000, 9999 );
				$new_answers[] = $new_answer;
			}

			// Prepend "Copy of" to stem to indicate it's a duplicate
			$duplicated_stem = $revision->stem;
			if ( 0 !== strpos( strip_tags( $duplicated_stem ), 'Copy of ' ) ) {
				// If it's a simple text stem, prepend
				if ( strip_tags( $duplicated_stem ) === $duplicated_stem ) {
					$duplicated_stem = 'Copy of ' . $duplicated_stem;
				} else {
					// If it has HTML, wrap in a paragraph at the start
					$duplicated_stem = '<p><strong>Copy of:</strong></p>' . $duplicated_stem;
				}
			}

			$new_revision = new PPQ_Question_Revision();
			$new_revision->question_id = $new_question->id;
			$new_revision->version = 1;
			$new_revision->stem = $duplicated_stem;
			$new_revision->answers = wp_json_encode( $new_answers );
			$new_revision->correct_answers = $revision->correct_answers;
			$new_revision->settings = $revision->settings;
			$new_revision->feedback_correct = $revision->feedback_correct;
			$new_revision->feedback_incorrect = $revision->feedback_incorrect;
			$new_revision->content_hash = PPQ_Question_Revision::generate_hash( $new_revision->stem, $new_answers );
			$new_revision->created_by = get_current_user_id();

			$result = $new_revision->save();

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Update question to point to this revision
			$new_question->current_revision_id = $new_revision->id;
			$result = $new_question->save();

			if ( is_wp_error( $result ) ) {
				throw new Exception( $result->get_error_message() );
			}

			// Duplicate categories
			$categories = $question->get_categories();
			if ( ! empty( $categories ) ) {
				$category_ids = array_map( function( $cat ) {
					return $cat->id;
				}, $categories );
				$new_question->set_categories( $category_ids );
			}

			// Duplicate tags
			$tags = $question->get_tags();
			if ( ! empty( $tags ) ) {
				$tag_ids = array_map( function( $tag ) {
					return $tag->id;
				}, $tags );
				$new_question->set_tags( $tag_ids );
			}

			// Duplicate bank memberships
			$bank_memberships_table = $wpdb->prefix . 'ppq_bank_memberships';
			$memberships = $wpdb->get_results(
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
					if ( class_exists( 'PPQ_Bank' ) ) {
						$bank = PPQ_Bank::get( $membership->bank_id );
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
						'page' => 'ppq-questions',
						'action' => 'edit',
						'question_id' => $new_question->id,
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
class PPQ_Questions_List_Table extends WP_List_Table {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct([
			'singular' => 'question',
			'plural'   => 'questions',
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

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// Build query
		$questions_table = $wpdb->prefix . 'ppq_questions';
		$where_clauses   = [ 'deleted_at IS NULL' ];
		$where_values    = [];

		// Filter by type
		if ( ! empty( $_GET['type'] ) ) {
			$where_clauses[] = 'type = %s';
			$where_values[]  = sanitize_key( $_GET['type'] );
		}

		// Filter by difficulty
		if ( ! empty( $_GET['difficulty'] ) ) {
			$where_clauses[] = 'difficulty_author = %s';
			$where_values[]  = sanitize_key( $_GET['difficulty'] );
		}

		// Filter by author
		if ( ! empty( $_GET['author'] ) ) {
			$where_clauses[] = 'author_id = %d';
			$where_values[]  = absint( $_GET['author'] );
		} elseif ( ! current_user_can( 'ppq_manage_all' ) ) {
			// Non-admins see only their own questions
			$where_clauses[] = 'author_id = %d';
			$where_values[]  = get_current_user_id();
		}

		// Search
		if ( ! empty( $_GET['s'] ) ) {
			$revisions_table = $wpdb->prefix . 'ppq_question_revisions';
			$search_term     = '%' . $wpdb->esc_like( sanitize_text_field( $_GET['s'] ) ) . '%';

			// Search in stem via revision
			$where_clauses[] = "current_revision_id IN (
				SELECT id FROM {$revisions_table} WHERE stem LIKE %s
			)";
			$where_values[] = $search_term;
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

		// Order by
		$orderby = ! empty( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at';
		$order   = ! empty( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC';

		$order_sql = "ORDER BY {$orderby} {$order}";

		// Count total items
		$count_query = "SELECT COUNT(*) FROM {$questions_table} {$where_sql}";
		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare( $count_query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total_items = $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Get items
		$query = "SELECT * FROM {$questions_table} {$where_sql} {$order_sql} LIMIT %d OFFSET %d";
		$query_values = array_merge( $where_values, [ $per_page, $offset ] );
		$query = $wpdb->prepare( $query, $query_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$rows = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Convert to question objects
		$items = [];
		foreach ( $rows as $row ) {
			$items[] = PPQ_Question::from_row( $row );
		}

		$this->items = $items;

		// Set pagination
		$this->set_pagination_args([
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		]);

		// Set columns
		$this->_column_headers = [
			$this->get_columns(),
			[],
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
			<?php if ( current_user_can( 'ppq_manage_all' ) ) : ?>
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
		$current_type = ! empty( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';

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
		$current_difficulty = ! empty( $_GET['difficulty'] ) ? sanitize_key( $_GET['difficulty'] ) : '';

		?>
		<select name="difficulty">
			<option value=""><?php esc_html_e( 'All Difficulties', 'pressprimer-quiz' ); ?></option>
			<option value="easy" <?php selected( $current_difficulty, 'easy' ); ?>>
				<?php esc_html_e( 'Easy', 'pressprimer-quiz' ); ?>
			</option>
			<option value="medium" <?php selected( $current_difficulty, 'medium' ); ?>>
				<?php esc_html_e( 'Medium', 'pressprimer-quiz' ); ?>
			</option>
			<option value="hard" <?php selected( $current_difficulty, 'hard' ); ?>>
				<?php esc_html_e( 'Hard', 'pressprimer-quiz' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Render author filter
	 *
	 * @since 1.0.0
	 */
	private function render_author_filter() {
		$current_author = ! empty( $_GET['author'] ) ? absint( $_GET['author'] ) : 0;

		wp_dropdown_users([
			'name'             => 'author',
			'show_option_all'  => __( 'All Authors', 'pressprimer-quiz' ),
			'selected'         => $current_author,
			'include_selected' => true,
		]);
	}

	/**
	 * Column: Checkbox
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Question $item Question object.
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
	 * @param PPQ_Question $item Question object.
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
	 * @param PPQ_Question $item Question object.
	 * @return string Column content.
	 */
	public function column_question( $item ) {
		$revision = $item->get_current_revision();
		$stem     = $revision ? $revision->stem : __( '(No content)', 'pressprimer-quiz' );

		// Truncate to 100 characters
		$truncated = PPQ_Helpers::truncate( $stem, 100 );

		// Build row actions
		$actions = [];

		// Check if user can edit this question
		$can_edit = current_user_can( 'ppq_manage_all' ) || ( current_user_can( 'ppq_manage_own' ) && absint( $item->author_id ) === get_current_user_id() );

		if ( $can_edit ) {
			$actions['edit'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=ppq-questions&action=edit&question=' . $item->id ) ),
				esc_html__( 'Edit', 'pressprimer-quiz' )
			);
		}

		// Users can duplicate any question they can view (to create their own copy)
		if ( current_user_can( 'ppq_manage_own' ) ) {
			$actions['duplicate'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url(
					admin_url( 'admin.php?page=ppq-questions&action=duplicate&question=' . $item->id ),
					'duplicate-question_' . $item->id
				)),
				esc_html__( 'Duplicate', 'pressprimer-quiz' )
			);
		}

		if ( $can_edit ) {
			$actions['delete'] = sprintf(
				'<a href="%s" class="ppq-delete-confirm">%s</a>',
				esc_url( wp_nonce_url(
					admin_url( 'admin.php?page=ppq-questions&action=delete&question=' . $item->id ),
					'delete-question_' . $item->id
				)),
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
	 * @param PPQ_Question $item Question object.
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
	 * @param PPQ_Question $item Question object.
	 * @return string Column content.
	 */
	public function column_difficulty( $item ) {
		if ( empty( $item->difficulty_author ) ) {
			return '—';
		}

		$difficulties = [
			'easy'   => __( 'Easy', 'pressprimer-quiz' ),
			'medium' => __( 'Medium', 'pressprimer-quiz' ),
			'hard'   => __( 'Hard', 'pressprimer-quiz' ),
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
	 * @param PPQ_Question $item Question object.
	 * @return string Column content.
	 */
	public function column_categories( $item ) {
		$categories = $item->get_categories();

		if ( empty( $categories ) ) {
			return '—';
		}

		$category_names = array_map( function ( $cat ) {
			return esc_html( $cat->name );
		}, $categories );

		return implode( ', ', $category_names );
	}

	/**
	 * Column: Banks
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Question $item Question object.
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

		$bank_names = array_map( function ( $bank ) {
			return esc_html( $bank->name );
		}, $banks );

		return implode( ', ', $bank_names );
	}

	/**
	 * Column: Author
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Question $item Question object.
	 * @return string Column content.
	 */
	public function column_author( $item ) {
		$author = get_userdata( $item->author_id );

		if ( ! $author ) {
			return '—';
		}

		if ( current_user_can( 'ppq_manage_all' ) ) {
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
	 * @param PPQ_Question $item Question object.
	 * @return string Column content.
	 */
	public function column_date( $item ) {
		$timestamp = strtotime( $item->created_at );

		if ( ! $timestamp ) {
			return '—';
		}

		return sprintf(
			'<abbr title="%s">%s</abbr>',
			esc_attr( date_i18n( 'Y/m/d g:i:s a', $timestamp ) ),
			esc_html( date_i18n( 'Y/m/d', $timestamp ) )
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
