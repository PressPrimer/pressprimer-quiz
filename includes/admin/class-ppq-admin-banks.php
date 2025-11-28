<?php
/**
 * Admin Banks class
 *
 * Handles WordPress admin interface for Question Banks.
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
 * Admin Banks class
 *
 * Manages bank list table, editor, and question assignments.
 *
 * @since 1.0.0
 */
class PPQ_Admin_Banks {

	/**
	 * List table instance
	 *
	 * @since 1.0.0
	 * @var PPQ_Banks_List_Table
	 */
	private $list_table;

	/**
	 * Initialize admin functionality
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_post_ppq_save_bank', [ $this, 'handle_save' ] );
		add_action( 'admin_post_ppq_delete_bank', [ $this, 'handle_delete' ] );
		add_action( 'admin_post_ppq_add_question_to_bank', [ $this, 'handle_add_question' ] );
		add_action( 'admin_post_ppq_remove_question_from_bank', [ $this, 'handle_remove_question' ] );
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

		// Only add screen options on the banks list page
		if ( $screen && 'pressprimer-quiz_page_ppq-banks' === $screen->id ) {
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
		if ( in_array( $action, [ 'new', 'edit', 'view' ], true ) ) {
			return;
		}

		// Add per page option
		add_screen_option( 'per_page', [
			'label'   => __( 'Banks per page', 'pressprimer-quiz' ),
			'default' => 20,
			'option'  => 'ppq_banks_per_page',
		] );

		// Instantiate the table and store it
		require_once __DIR__ . '/class-ppq-banks-list-table.php';
		$this->list_table = new PPQ_Banks_List_Table();

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
	 * Save screen option
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $status Screen option value.
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return mixed Screen option value.
	 */
	public function set_screen_option( $status, $option, $value ) {
		if ( 'ppq_banks_per_page' === $option ) {
			return $value;
		}

		return $status;
	}

	/**
	 * Render banks page
	 *
	 * Routes to list, edit, or detail view based on action.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-quiz' ),
				esc_html__( 'Permission Denied', 'pressprimer-quiz' ),
				[ 'response' => 403 ]
			);
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
		$bank_id = isset( $_GET['bank_id'] ) ? absint( $_GET['bank_id'] ) : 0;

		switch ( $action ) {
			case 'new':
			case 'edit':
				$this->render_editor( $bank_id );
				break;
			case 'view':
				$this->render_detail( $bank_id );
				break;
			default:
				$this->render_list();
				break;
		}
	}

	/**
	 * Render banks list table
	 *
	 * @since 1.0.0
	 */
	private function render_list() {
		// Reuse the list table instance if it exists, otherwise create new one
		if ( ! $this->list_table ) {
			require_once __DIR__ . '/class-ppq-banks-list-table.php';
			$this->list_table = new PPQ_Banks_List_Table();
		}

		$this->list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Question Banks', 'pressprimer-quiz' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-banks&action=new' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'pressprimer-quiz' ); ?>
			</a>
			<hr class="wp-header-end">

			<form method="get">
				<input type="hidden" name="page" value="ppq-banks">
				<?php
				$this->list_table->search_box( __( 'Search Banks', 'pressprimer-quiz' ), 'ppq-banks' );
				$this->list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render bank editor (React version)
	 *
	 * @since 1.0.0
	 *
	 * @param int $bank_id Bank ID (0 for new).
	 */
	private function render_editor( $bank_id = 0 ) {
		$bank = null;

		if ( $bank_id > 0 ) {
			if ( class_exists( 'PPQ_Bank' ) ) {
				$bank = PPQ_Bank::get( $bank_id );
			}

			if ( ! $bank ) {
				wp_die(
					esc_html__( 'Bank not found.', 'pressprimer-quiz' ),
					esc_html__( 'Error', 'pressprimer-quiz' ),
					[ 'response' => 404 ]
				);
			}

			// Check ownership
			if ( ! current_user_can( 'ppq_manage_all' ) && absint( $bank->owner_id ) !== get_current_user_id() ) {
				wp_die(
					esc_html__( 'You do not have permission to edit this bank.', 'pressprimer-quiz' ),
					esc_html__( 'Permission Denied', 'pressprimer-quiz' ),
					[ 'response' => 403 ]
				);
			}
		}

		// Enqueue React editor
		$this->enqueue_react_editor( $bank_id );

		?>
		<!-- React Editor Root -->
		<div id="ppq-bank-editor-root"></div>
		<?php
	}

	/**
	 * Enqueue React editor assets
	 *
	 * @since 1.0.0
	 *
	 * @param int $bank_id Bank ID.
	 */
	private function enqueue_react_editor( $bank_id ) {
		// Enqueue Ant Design CSS
		wp_enqueue_style(
			'antd',
			'https://cdn.jsdelivr.net/npm/antd@5.12.0/dist/reset.css',
			[],
			'5.12.0'
		);

		// Enqueue the built React bundle
		wp_enqueue_script(
			'ppq-bank-editor',
			PPQ_PLUGIN_URL . 'build/bank-editor.js',
			[ 'wp-element', 'wp-i18n', 'wp-api-fetch' ],
			PPQ_VERSION,
			true
		);

		wp_enqueue_style(
			'ppq-bank-editor',
			PPQ_PLUGIN_URL . 'build/style-bank-editor.css',
			[],
			PPQ_VERSION
		);

		// Prepare bank data for JavaScript
		$bank_data = [];

		if ( $bank_id > 0 ) {
			$bank = PPQ_Bank::get( $bank_id );

			if ( $bank ) {
				$bank_data = [
					'id'          => $bank->id,
					'uuid'        => $bank->uuid,
					'name'        => $bank->name,
					'description' => $bank->description,
					'owner_id'    => $bank->owner_id,
					'visibility'  => $bank->visibility,
					'question_count' => $bank->question_count,
					'created_at'  => $bank->created_at,
					'updated_at'  => $bank->updated_at,
				];
			}
		}

		// Add user capabilities
		$bank_data['userCan'] = [
			'manage_all' => current_user_can( 'ppq_manage_all' ),
			'manage_own' => current_user_can( 'ppq_manage_own' ),
		];

		// Localize script with data
		wp_localize_script(
			'ppq-bank-editor',
			'ppqBankData',
			$bank_data
		);
	}

	/**
	 * Render bank detail page with questions
	 *
	 * @since 1.0.0
	 *
	 * @param int $bank_id Bank ID.
	 */
	private function render_detail( $bank_id ) {
		if ( ! $bank_id ) {
			wp_die(
				esc_html__( 'Invalid bank ID.', 'pressprimer-quiz' ),
				esc_html__( 'Error', 'pressprimer-quiz' ),
				[ 'response' => 400 ]
			);
		}

		$bank = null;
		if ( class_exists( 'PPQ_Bank' ) ) {
			$bank = PPQ_Bank::get( $bank_id );
		}

		if ( ! $bank ) {
			wp_die(
				esc_html__( 'Bank not found.', 'pressprimer-quiz' ),
				esc_html__( 'Error', 'pressprimer-quiz' ),
				[ 'response' => 404 ]
			);
		}

		// Check access
		if ( ! $bank->can_access( get_current_user_id() ) ) {
			wp_die(
				esc_html__( 'You do not have permission to view this bank.', 'pressprimer-quiz' ),
				esc_html__( 'Permission Denied', 'pressprimer-quiz' ),
				[ 'response' => 403 ]
			);
		}

		// Get questions in bank
		$args = [
			'limit' => 999,
			'offset' => 0,
		];

		// Add filters from request
		if ( isset( $_GET['filter_type'] ) && ! empty( $_GET['filter_type'] ) ) {
			$args['type'] = sanitize_key( $_GET['filter_type'] );
		}
		if ( isset( $_GET['filter_difficulty'] ) && ! empty( $_GET['filter_difficulty'] ) ) {
			$args['difficulty'] = sanitize_key( $_GET['filter_difficulty'] );
		}

		$questions = $bank->get_questions( $args );
		$total_count = $bank->question_count;

		?>
		<div class="wrap">
			<h1>
				<?php echo esc_html( $bank->name ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-banks&action=edit&bank_id=' . $bank_id ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Edit Bank', 'pressprimer-quiz' ); ?>
				</a>
			</h1>

			<hr class="wp-header-end">

			<!-- Bank Info Section -->
			<div class="ppq-form-section">
				<h2><?php esc_html_e( 'Bank Information', 'pressprimer-quiz' ); ?></h2>

				<div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; display: grid; grid-template-columns: 1fr auto; gap: 30px; align-items: start;">
					<div>
						<h3 style="margin: 0 0 10px 0; font-size: 15px; font-weight: 600; color: #1d2327;">
							<?php esc_html_e( 'Description', 'pressprimer-quiz' ); ?>
						</h3>
						<?php if ( $bank->description ) : ?>
							<p style="margin: 0; font-size: 14px; line-height: 1.8; color: #3c434a;">
								<?php echo esc_html( $bank->description ); ?>
							</p>
						<?php else : ?>
							<p style="margin: 0; font-size: 14px; color: #646970; font-style: italic;">
								<?php esc_html_e( 'No description provided for this bank.', 'pressprimer-quiz' ); ?>
							</p>
						<?php endif; ?>
					</div>
					<div style="text-align: center; min-width: 140px; padding: 20px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 3px;">
						<div style="font-size: 48px; font-weight: 700; color: #2271b1; line-height: 1; margin-bottom: 8px;">
							<?php echo absint( $total_count ); ?>
						</div>
						<div style="font-size: 13px; color: #50575e; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
							<?php esc_html_e( 'Total Questions', 'pressprimer-quiz' ); ?>
						</div>
					</div>
				</div>
			</div>

			<!-- Add Question Section -->
			<div class="ppq-form-section">
				<h2><?php esc_html_e( 'Add Questions to Bank', 'pressprimer-quiz' ); ?></h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ppq-add-question-form">
					<?php wp_nonce_field( 'ppq_add_question_to_bank', 'ppq_add_question_nonce' ); ?>
					<input type="hidden" name="action" value="ppq_add_question_to_bank">
					<input type="hidden" name="bank_id" value="<?php echo esc_attr( $bank_id ); ?>">

					<!-- Filter Section -->
					<div class="ppq-question-filters" style="background: #f9f9f9; padding: 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;">
						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px;">
							<div>
								<label for="filter_question_type" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php esc_html_e( 'Type:', 'pressprimer-quiz' ); ?></label>
								<select id="filter_question_type" class="ppq-question-filter" style="width: 100%;">
									<option value=""><?php esc_html_e( 'All Types', 'pressprimer-quiz' ); ?></option>
									<option value="mc"><?php esc_html_e( 'Multiple Choice', 'pressprimer-quiz' ); ?></option>
									<option value="ma"><?php esc_html_e( 'Multiple Answer', 'pressprimer-quiz' ); ?></option>
									<option value="tf"><?php esc_html_e( 'True/False', 'pressprimer-quiz' ); ?></option>
								</select>
							</div>

							<div>
								<label for="filter_question_difficulty" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php esc_html_e( 'Difficulty:', 'pressprimer-quiz' ); ?></label>
								<select id="filter_question_difficulty" class="ppq-question-filter" style="width: 100%;">
									<option value=""><?php esc_html_e( 'All Difficulties', 'pressprimer-quiz' ); ?></option>
									<option value="beginner"><?php esc_html_e( 'Beginner', 'pressprimer-quiz' ); ?></option>
									<option value="intermediate"><?php esc_html_e( 'Intermediate', 'pressprimer-quiz' ); ?></option>
									<option value="advanced"><?php esc_html_e( 'Advanced', 'pressprimer-quiz' ); ?></option>
									<option value="expert"><?php esc_html_e( 'Expert', 'pressprimer-quiz' ); ?></option>
								</select>
							</div>

							<div>
								<label for="filter_question_category" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php esc_html_e( 'Category:', 'pressprimer-quiz' ); ?></label>
								<select id="filter_question_category" class="ppq-question-filter" style="width: 100%;">
									<option value=""><?php esc_html_e( 'All Categories', 'pressprimer-quiz' ); ?></option>
									<?php
									// Get categories
									if ( class_exists( 'PPQ_Category' ) ) {
										$categories = PPQ_Category::find( [
											'where' => [ 'taxonomy' => 'category' ],
											'order_by' => 'name',
											'order' => 'ASC',
										] );
										foreach ( $categories as $category ) {
											echo '<option value="' . esc_attr( $category->id ) . '">' . esc_html( $category->name ) . '</option>';
										}
									}
									?>
								</select>
							</div>

							<div>
								<label for="filter_question_tag" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php esc_html_e( 'Tag:', 'pressprimer-quiz' ); ?></label>
								<select id="filter_question_tag" class="ppq-question-filter" style="width: 100%;">
									<option value=""><?php esc_html_e( 'All Tags', 'pressprimer-quiz' ); ?></option>
									<?php
									// Get tags
									if ( class_exists( 'PPQ_Category' ) ) {
										$tags = PPQ_Category::find( [
											'where' => [ 'taxonomy' => 'tag' ],
											'order_by' => 'name',
											'order' => 'ASC',
										] );
										foreach ( $tags as $tag ) {
											echo '<option value="' . esc_attr( $tag->id ) . '">' . esc_html( $tag->name ) . '</option>';
										}
									}
									?>
								</select>
							</div>
						</div>

						<div>
							<label for="question_search" style="display: block; margin-bottom: 5px; font-weight: 600;"><?php esc_html_e( 'Search:', 'pressprimer-quiz' ); ?></label>
							<input
								type="text"
								id="question_search"
								class="ppq-question-filter regular-text"
								placeholder="<?php esc_attr_e( 'Search question text...', 'pressprimer-quiz' ); ?>"
								style="width: 100%;"
							>
						</div>

						<p style="margin: 10px 0 0 0;">
							<button type="button" id="ppq-search-questions" class="button">
								<?php esc_html_e( 'Search Questions', 'pressprimer-quiz' ); ?>
							</button>
							<button type="button" id="ppq-reset-filters" class="button">
								<?php esc_html_e( 'Reset Filters', 'pressprimer-quiz' ); ?>
							</button>
						</p>
					</div>

					<!-- Most Recent Questions Section -->
					<div class="ppq-recent-questions-section" style="background: #f0f6fc; border: 1px solid #c3c4c7; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
						<h3 style="margin-top: 0; font-size: 14px; font-weight: 600;">
							<?php esc_html_e( 'Most Recent Questions You Created', 'pressprimer-quiz' ); ?>
						</h3>
						<p style="margin: 5px 0 15px; color: #646970; font-size: 13px;">
							<?php esc_html_e( 'Quick access to your recently created questions. Select the ones you want to add to this bank.', 'pressprimer-quiz' ); ?>
						</p>

						<div id="ppq-recent-questions-list">
							<p style="text-align: center; padding: 20px;">
								<span class="spinner is-active" style="float: none; margin: 0;"></span>
								<?php esc_html_e( 'Loading recent questions...', 'pressprimer-quiz' ); ?>
							</p>
						</div>

						<div id="ppq-recent-questions-pagination" style="margin-top: 10px; text-align: center; display: none;">
							<!-- Pagination controls populated via JavaScript -->
						</div>
					</div>

					<div id="ppq-question-search-results" style="display: none; background: #fff; border: 1px solid #c3c4c7; padding: 10px; margin-bottom: 15px; max-height: 400px; overflow-y: auto;">
						<!-- Results populated via JavaScript -->
					</div>

					<p>
						<button type="submit" class="button button-primary" id="ppq-add-selected-questions" disabled>
							<?php esc_html_e( 'Add Selected Questions', 'pressprimer-quiz' ); ?>
						</button>
					</p>
				</form>
			</div>

			<!-- Filter Questions -->
			<div class="ppq-form-section" style="margin-top: 20px;">
				<h2><?php esc_html_e( 'Questions in Bank', 'pressprimer-quiz' ); ?></h2>

				<form method="get" class="ppq-filter-form">
					<input type="hidden" name="page" value="ppq-banks">
					<input type="hidden" name="action" value="view">
					<input type="hidden" name="bank_id" value="<?php echo esc_attr( $bank_id ); ?>">

					<select name="filter_type">
						<option value=""><?php esc_html_e( 'All Types', 'pressprimer-quiz' ); ?></option>
						<option value="mc" <?php selected( isset( $_GET['filter_type'] ) ? $_GET['filter_type'] : '', 'mc' ); ?>>
							<?php esc_html_e( 'Multiple Choice', 'pressprimer-quiz' ); ?>
						</option>
						<option value="ma" <?php selected( isset( $_GET['filter_type'] ) ? $_GET['filter_type'] : '', 'ma' ); ?>>
							<?php esc_html_e( 'Multiple Answer', 'pressprimer-quiz' ); ?>
						</option>
						<option value="tf" <?php selected( isset( $_GET['filter_type'] ) ? $_GET['filter_type'] : '', 'tf' ); ?>>
							<?php esc_html_e( 'True/False', 'pressprimer-quiz' ); ?>
						</option>
					</select>

					<select name="filter_difficulty">
						<option value=""><?php esc_html_e( 'All Difficulties', 'pressprimer-quiz' ); ?></option>
						<option value="beginner" <?php selected( isset( $_GET['filter_difficulty'] ) ? $_GET['filter_difficulty'] : '', 'beginner' ); ?>>
							<?php esc_html_e( 'Beginner', 'pressprimer-quiz' ); ?>
						</option>
						<option value="intermediate" <?php selected( isset( $_GET['filter_difficulty'] ) ? $_GET['filter_difficulty'] : '', 'intermediate' ); ?>>
							<?php esc_html_e( 'Intermediate', 'pressprimer-quiz' ); ?>
						</option>
						<option value="advanced" <?php selected( isset( $_GET['filter_difficulty'] ) ? $_GET['filter_difficulty'] : '', 'advanced' ); ?>>
							<?php esc_html_e( 'Advanced', 'pressprimer-quiz' ); ?>
						</option>
						<option value="expert" <?php selected( isset( $_GET['filter_difficulty'] ) ? $_GET['filter_difficulty'] : '', 'expert' ); ?>>
							<?php esc_html_e( 'Expert', 'pressprimer-quiz' ); ?>
						</option>
					</select>

					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'pressprimer-quiz' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-banks&action=view&bank_id=' . $bank_id ) ); ?>" class="button">
						<?php esc_html_e( 'Clear Filters', 'pressprimer-quiz' ); ?>
					</a>
				</form>

				<!-- Questions Table -->
				<?php if ( empty( $questions ) ) : ?>
					<p><em><?php esc_html_e( 'No questions in this bank yet.', 'pressprimer-quiz' ); ?></em></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped ppq-table" style="margin-top: 15px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Question', 'pressprimer-quiz' ); ?></th>
								<th><?php esc_html_e( 'Type', 'pressprimer-quiz' ); ?></th>
								<th><?php esc_html_e( 'Difficulty', 'pressprimer-quiz' ); ?></th>
								<th><?php esc_html_e( 'Category', 'pressprimer-quiz' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'pressprimer-quiz' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $questions as $question ) : ?>
								<?php
								$revision = $question->get_current_revision();
								$stem_preview = $revision ? wp_trim_words( wp_strip_all_tags( $revision->stem ), 15 ) : '';

								// Type labels
								$type_labels = [
									'mc' => __( 'Multiple Choice', 'pressprimer-quiz' ),
									'ma' => __( 'Multiple Answer', 'pressprimer-quiz' ),
									'tf' => __( 'True/False', 'pressprimer-quiz' ),
								];
								$type_label = isset( $type_labels[ $question->type ] ) ? $type_labels[ $question->type ] : $question->type;

								// Difficulty labels
								$difficulty_labels = [
									'beginner' => __( 'Beginner', 'pressprimer-quiz' ),
									'intermediate' => __( 'Intermediate', 'pressprimer-quiz' ),
									'advanced' => __( 'Advanced', 'pressprimer-quiz' ),
									'expert' => __( 'Expert', 'pressprimer-quiz' ),
								];
								$difficulty_label = isset( $difficulty_labels[ $question->difficulty_author ] ) ? $difficulty_labels[ $question->difficulty_author ] : $question->difficulty_author;

								// Get categories
								$categories = $question->get_categories();
								$category_names = [];
								foreach ( $categories as $cat ) {
									if ( 'category' === $cat->taxonomy ) {
										$category_names[] = $cat->name;
									}
								}
								$category_display = ! empty( $category_names ) ? implode( ', ', $category_names ) : '<span class="ppq-text-muted">' . esc_html__( 'None', 'pressprimer-quiz' ) . '</span>';
								?>
								<tr>
									<td>
										<strong><?php echo esc_html( $stem_preview ); ?></strong>
										<div class="row-actions">
											<span class="edit">
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-questions&action=edit&question_id=' . $question->id ) ); ?>">
													<?php esc_html_e( 'Edit', 'pressprimer-quiz' ); ?>
												</a> |
											</span>
											<span class="view">
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-questions&action=view&question_id=' . $question->id ) ); ?>">
													<?php esc_html_e( 'View', 'pressprimer-quiz' ); ?>
												</a>
											</span>
										</div>
									</td>
									<td><?php echo esc_html( $type_label ); ?></td>
									<td><?php echo esc_html( $difficulty_label ); ?></td>
									<td><?php echo $category_display; // Already escaped above ?></td>
									<td>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
											<?php wp_nonce_field( 'ppq_remove_question_from_bank', 'ppq_remove_question_nonce' ); ?>
											<input type="hidden" name="action" value="ppq_remove_question_from_bank">
											<input type="hidden" name="bank_id" value="<?php echo esc_attr( $bank_id ); ?>">
											<input type="hidden" name="question_id" value="<?php echo esc_attr( $question->id ); ?>">
											<button
												type="submit"
												class="button-link-delete"
												onclick="return confirm('<?php esc_attr_e( 'Remove this question from the bank?', 'pressprimer-quiz' ); ?>');"
											>
												<?php esc_html_e( 'Remove from Bank', 'pressprimer-quiz' ); ?>
											</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var searchTimeout;
			var selectedQuestions = [];
			var currentRecentPage = 1;

			// Function to perform question search with filters
			function searchQuestions() {
				var searchTerm = $('#question_search').val();
				var type = $('#filter_question_type').val();
				var difficulty = $('#filter_question_difficulty').val();
				var categoryId = $('#filter_question_category').val();
				var tagId = $('#filter_question_tag').val();

				console.log('Search parameters:', {
					search: searchTerm,
					type: type,
					difficulty: difficulty,
					category_id: categoryId,
					tag_id: tagId
				});

				// AJAX search for questions
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'ppq_search_questions',
						nonce: '<?php echo esc_js( wp_create_nonce( 'ppq_search_questions' ) ); ?>',
						search: searchTerm,
						type: type,
						difficulty: difficulty,
						category_id: categoryId,
						tag_id: tagId,
						bank_id: <?php echo absint( $bank_id ); ?>
					},
					success: function(response) {
						console.log('Search AJAX response:', response);
						if (response.success && response.data.questions) {
							var html = '';
							if (response.data.questions.length === 0) {
								html = '<p><em><?php esc_html_e( 'No questions found.', 'pressprimer-quiz' ); ?></em></p>';
							} else {
								html = '<div style="max-height: 250px; overflow-y: auto;">';
								$.each(response.data.questions, function(i, q) {
									var checked = selectedQuestions.indexOf(q.id) !== -1 ? ' checked' : '';
									html += '<label style="display: block; padding: 5px; border-bottom: 1px solid #ddd;">';
									html += '<input type="checkbox" name="question_ids[]" value="' + q.id + '" class="ppq-question-checkbox"' + checked + '> ';
									html += '<strong>' + q.stem_preview + '</strong> ';
									html += '<span style="color: #646970;">(' + q.type + ', ' + q.difficulty + ')</span>';
									html += '</label>';
								});
								html += '</div>';
							}
							$('#ppq-question-search-results').html(html).show();
						} else {
							console.error('Search failed or no data:', response);
						}
					},
					error: function(xhr, status, error) {
						console.error('Search AJAX error:', {
							status: status,
							error: error,
							response: xhr.responseText
						});
						$('#ppq-question-search-results').html('<p style="color: #d63638;"><?php esc_html_e( 'Error searching questions. Please try again.', 'pressprimer-quiz' ); ?></p>').show();
					}
				});
			}

			// Function to load recent questions
			function loadRecentQuestions(page) {
				currentRecentPage = page || 1;

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'ppq_get_recent_questions',
						nonce: '<?php echo esc_js( wp_create_nonce( 'ppq_get_recent_questions' ) ); ?>',
						bank_id: <?php echo absint( $bank_id ); ?>,
						page: currentRecentPage
					},
					success: function(response) {
						if (response.success && response.data.questions) {
							var html = '';
							var data = response.data;

							if (data.questions.length === 0) {
								html = '<p style="text-align: center; color: #646970; padding: 20px;"><em><?php esc_html_e( 'No questions found. Create some questions first!', 'pressprimer-quiz' ); ?></em></p>';
								$('#ppq-recent-questions-list').html(html);
								$('#ppq-recent-questions-pagination').hide();
							} else {
								// Build table
								html = '<table class="widefat" style="margin: 0;">';
								html += '<thead><tr>';
								html += '<th style="width: 40px; text-align: center;"><?php esc_html_e( 'Select', 'pressprimer-quiz' ); ?></th>';
								html += '<th><?php esc_html_e( 'Question', 'pressprimer-quiz' ); ?></th>';
								html += '<th style="width: 150px;"><?php esc_html_e( 'Type', 'pressprimer-quiz' ); ?></th>';
								html += '<th style="width: 120px;"><?php esc_html_e( 'Difficulty', 'pressprimer-quiz' ); ?></th>';
								html += '<th style="width: 150px;"><?php esc_html_e( 'Category', 'pressprimer-quiz' ); ?></th>';
								html += '</tr></thead><tbody>';

								$.each(data.questions, function(i, q) {
									var checked = selectedQuestions.indexOf(q.id) !== -1 ? ' checked' : '';
									html += '<tr>';
									html += '<td style="text-align: center;"><input type="checkbox" name="question_ids[]" value="' + q.id + '" class="ppq-question-checkbox"' + checked + '></td>';
									html += '<td><strong>' + q.stem_preview + '</strong></td>';
									html += '<td>' + q.type_label + '</td>';
									html += '<td>' + q.difficulty_label + '</td>';
									html += '<td>' + q.category + '</td>';
									html += '</tr>';
								});

								html += '</tbody></table>';
								$('#ppq-recent-questions-list').html(html);

								// Build pagination
								if (data.total_pages > 1) {
									var pagHtml = '<div class="tablenav-pages">';
									pagHtml += '<span class="displaying-num">' + data.total_items + ' <?php esc_html_e( 'items', 'pressprimer-quiz' ); ?></span>';
									pagHtml += '<span class="pagination-links">';

									// First page
									if (currentRecentPage > 1) {
										pagHtml += '<a class="button ppq-recent-page-nav" data-page="1" title="<?php esc_attr_e( 'First page', 'pressprimer-quiz' ); ?>">&laquo;</a> ';
										pagHtml += '<a class="button ppq-recent-page-nav" data-page="' + (currentRecentPage - 1) + '" title="<?php esc_attr_e( 'Previous page', 'pressprimer-quiz' ); ?>">&lsaquo;</a> ';
									} else {
										pagHtml += '<span class="button disabled">&laquo;</span> ';
										pagHtml += '<span class="button disabled">&lsaquo;</span> ';
									}

									pagHtml += '<span class="paging-input">' + currentRecentPage + ' <?php esc_html_e( 'of', 'pressprimer-quiz' ); ?> ' + data.total_pages + '</span> ';

									// Last page
									if (currentRecentPage < data.total_pages) {
										pagHtml += '<a class="button ppq-recent-page-nav" data-page="' + (currentRecentPage + 1) + '" title="<?php esc_attr_e( 'Next page', 'pressprimer-quiz' ); ?>">&rsaquo;</a> ';
										pagHtml += '<a class="button ppq-recent-page-nav" data-page="' + data.total_pages + '" title="<?php esc_attr_e( 'Last page', 'pressprimer-quiz' ); ?>">&raquo;</a>';
									} else {
										pagHtml += '<span class="button disabled">&rsaquo;</span> ';
										pagHtml += '<span class="button disabled">&raquo;</span>';
									}

									pagHtml += '</span></div>';
									$('#ppq-recent-questions-pagination').html(pagHtml).show();
								} else {
									$('#ppq-recent-questions-pagination').hide();
								}
							}
						}
					},
					error: function(xhr, status, error) {
						console.error('Recent questions AJAX error:', {
							status: status,
							error: error,
							response: xhr.responseText
						});
						$('#ppq-recent-questions-list').html('<p style="color: #d63638; text-align: center;"><?php esc_html_e( 'Error loading questions. Please refresh the page.', 'pressprimer-quiz' ); ?></p>');
					}
				});
			}

			// Load recent questions on page load
			loadRecentQuestions(1);

			// Handle pagination clicks
			$(document).on('click', '.ppq-recent-page-nav', function(e) {
				e.preventDefault();
				var page = parseInt($(this).data('page'));
				loadRecentQuestions(page);
			});

			// Search on keyup with debounce
			$('#question_search').on('keyup', function() {
				var searchTerm = $(this).val();

				clearTimeout(searchTimeout);

				if (searchTerm.length < 2) {
					$('#ppq-question-search-results').hide();
					return;
				}

				searchTimeout = setTimeout(function() {
					searchQuestions();
				}, 300);
			});

			// Search button click
			$('#ppq-search-questions').on('click', function(e) {
				e.preventDefault();
				console.log('Search button clicked');

				// Show results container even if empty
				$('#ppq-question-search-results').show();

				searchQuestions();
			});

			// Reset filters button click
			$('#ppq-reset-filters').on('click', function(e) {
				e.preventDefault();
				$('#question_search').val('');
				$('#filter_question_type').val('');
				$('#filter_question_difficulty').val('');
				$('#filter_question_category').val('');
				$('#filter_question_tag').val('');
				$('#ppq-question-search-results').hide();
				selectedQuestions = [];
				$('#ppq-add-selected-questions').prop('disabled', true);
			});

			// Also trigger search when filters change
			$('.ppq-question-filter').on('change', function() {
				if ($('#question_search').val().length >= 2 ||
					$('#filter_question_type').val() ||
					$('#filter_question_difficulty').val() ||
					$('#filter_question_category').val() ||
					$('#filter_question_tag').val()) {
					searchQuestions();
				}
			});

			// Update selected questions and button state
			$(document).on('change', '.ppq-question-checkbox', function() {
				selectedQuestions = [];
				$('.ppq-question-checkbox:checked').each(function() {
					selectedQuestions.push(parseInt($(this).val()));
				});

				$('#ppq-add-selected-questions').prop('disabled', selectedQuestions.length === 0);
			});
		});
		</script>
		<?php
	}

	/**
	 * Handle bank save
	 *
	 * @since 1.0.0
	 */
	public function handle_save() {
		// Verify nonce
		if ( ! isset( $_POST['ppq_bank_nonce'] ) || ! wp_verify_nonce( $_POST['ppq_bank_nonce'], 'ppq_save_bank' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressprimer-quiz' ) );
		}

		$bank_id = isset( $_POST['bank_id'] ) ? absint( $_POST['bank_id'] ) : 0;
		$name = isset( $_POST['bank_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_name'] ) ) : '';
		$description = isset( $_POST['bank_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bank_description'] ) ) : '';

		// Validate
		if ( empty( $name ) ) {
			wp_die( esc_html__( 'Bank name is required.', 'pressprimer-quiz' ) );
		}

		// Check ownership for updates
		if ( $bank_id > 0 ) {
			$bank = null;
			if ( class_exists( 'PPQ_Bank' ) ) {
				$bank = PPQ_Bank::get( $bank_id );
			}

			if ( ! $bank ) {
				wp_die( esc_html__( 'Bank not found.', 'pressprimer-quiz' ) );
			}

			if ( ! current_user_can( 'ppq_manage_all' ) && absint( $bank->owner_id ) !== get_current_user_id() ) {
				wp_die( esc_html__( 'You do not have permission to edit this bank.', 'pressprimer-quiz' ) );
			}

			// Update existing bank
			$bank->name = $name;
			$bank->description = $description;
			$result = $bank->save();
		} else {
			// Create new bank
			$bank = new PPQ_Bank();
			$bank->name = $name;
			$bank->description = $description;
			$bank->owner_id = get_current_user_id();
			$result = $bank->save();

			if ( ! is_wp_error( $result ) ) {
				$bank_id = $bank->id;
			}
		}

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'ppq-banks',
					'action' => 'view',
					'bank_id' => $bank_id,
					'message' => 'bank_saved',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle bank delete
	 *
	 * @since 1.0.0
	 */
	public function handle_delete() {
		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'ppq_delete_bank_' . absint( $_GET['bank_id'] ) ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressprimer-quiz' ) );
		}

		$bank_id = isset( $_GET['bank_id'] ) ? absint( $_GET['bank_id'] ) : 0;

		if ( ! $bank_id ) {
			wp_die( esc_html__( 'Invalid bank ID.', 'pressprimer-quiz' ) );
		}

		$bank = null;
		if ( class_exists( 'PPQ_Bank' ) ) {
			$bank = PPQ_Bank::get( $bank_id );
		}

		if ( ! $bank ) {
			wp_die( esc_html__( 'Bank not found.', 'pressprimer-quiz' ) );
		}

		// Check ownership
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $bank->owner_id ) !== get_current_user_id() ) {
			wp_die( esc_html__( 'You do not have permission to delete this bank.', 'pressprimer-quiz' ) );
		}

		// Delete bank
		$result = $bank->delete();

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'ppq-banks',
					'message' => 'bank_deleted',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle add question to bank
	 *
	 * @since 1.0.0
	 */
	public function handle_add_question() {
		// Verify nonce
		if ( ! isset( $_POST['ppq_add_question_nonce'] ) || ! wp_verify_nonce( $_POST['ppq_add_question_nonce'], 'ppq_add_question_to_bank' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressprimer-quiz' ) );
		}

		$bank_id = isset( $_POST['bank_id'] ) ? absint( $_POST['bank_id'] ) : 0;
		$question_ids = isset( $_POST['question_ids'] ) ? array_map( 'absint', (array) $_POST['question_ids'] ) : [];

		error_log( '=== PPQ Add Questions Handler ===' );
		error_log( 'Bank ID: ' . $bank_id );
		error_log( 'Question IDs received: ' . print_r( $question_ids, true ) );

		if ( ! $bank_id || empty( $question_ids ) ) {
			wp_die( esc_html__( 'Invalid bank or question IDs.', 'pressprimer-quiz' ) );
		}

		$bank = null;
		if ( class_exists( 'PPQ_Bank' ) ) {
			$bank = PPQ_Bank::get( $bank_id );
		}

		if ( ! $bank ) {
			wp_die( esc_html__( 'Bank not found.', 'pressprimer-quiz' ) );
		}

		// Check ownership
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $bank->owner_id ) !== get_current_user_id() ) {
			wp_die( esc_html__( 'You do not have permission to edit this bank.', 'pressprimer-quiz' ) );
		}

		// Add questions to bank
		foreach ( $question_ids as $question_id ) {
			$result = $bank->add_question( $question_id );
			if ( is_wp_error( $result ) ) {
				error_log( 'Failed to add question ' . $question_id . ': ' . $result->get_error_message() );
			} else {
				error_log( 'Successfully added question ' . $question_id . ' to bank ' . $bank_id );
			}
		}

		// Update count
		$bank->update_question_count();
		error_log( 'Updated question count for bank ' . $bank_id );

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'ppq-banks',
					'action' => 'view',
					'bank_id' => $bank_id,
					'message' => 'questions_added',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle remove question from bank
	 *
	 * @since 1.0.0
	 */
	public function handle_remove_question() {
		// Verify nonce
		if ( ! isset( $_POST['ppq_remove_question_nonce'] ) || ! wp_verify_nonce( $_POST['ppq_remove_question_nonce'], 'ppq_remove_question_from_bank' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressprimer-quiz' ) );
		}

		$bank_id = isset( $_POST['bank_id'] ) ? absint( $_POST['bank_id'] ) : 0;
		$question_id = isset( $_POST['question_id'] ) ? absint( $_POST['question_id'] ) : 0;

		if ( ! $bank_id || ! $question_id ) {
			wp_die( esc_html__( 'Invalid bank or question ID.', 'pressprimer-quiz' ) );
		}

		$bank = null;
		if ( class_exists( 'PPQ_Bank' ) ) {
			$bank = PPQ_Bank::get( $bank_id );
		}

		if ( ! $bank ) {
			wp_die( esc_html__( 'Bank not found.', 'pressprimer-quiz' ) );
		}

		// Check ownership
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $bank->owner_id ) !== get_current_user_id() ) {
			wp_die( esc_html__( 'You do not have permission to edit this bank.', 'pressprimer-quiz' ) );
		}

		// Remove question from bank
		$bank->remove_question( $question_id );

		// Update count
		$bank->update_question_count();

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => 'ppq-banks',
					'action' => 'view',
					'bank_id' => $bank_id,
					'message' => 'question_removed',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		if ( ! isset( $_GET['page'] ) || 'ppq-banks' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		$message = sanitize_key( $_GET['message'] );
		$class = 'notice notice-success is-dismissible';
		$text = '';

		switch ( $message ) {
			case 'bank_saved':
				$text = __( 'Bank saved successfully.', 'pressprimer-quiz' );
				break;
			case 'bank_created':
				$text = __( 'Bank created successfully! You can now add questions to it.', 'pressprimer-quiz' );
				break;
			case 'bank_deleted':
				$text = __( 'Bank deleted successfully.', 'pressprimer-quiz' );
				break;
			case 'questions_added':
				$text = __( 'Questions added to bank successfully.', 'pressprimer-quiz' );
				break;
			case 'question_removed':
				$text = __( 'Question removed from bank successfully.', 'pressprimer-quiz' );
				break;
		}

		if ( $text ) {
			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $text ) );
		}
	}
}
