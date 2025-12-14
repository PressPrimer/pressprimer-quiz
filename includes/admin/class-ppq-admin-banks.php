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
class PressPrimer_Quiz_Admin_Banks {

	/**
	 * List table instance
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Quiz_Banks_List_Table
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for display routing
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( in_array( $action, [ 'new', 'edit', 'view' ], true ) ) {
			return;
		}

		// Add per page option
		add_screen_option(
			'per_page',
			[
				'label'   => __( 'Banks per page', 'pressprimer-quiz' ),
				'default' => 20,
				'option'  => 'ppq_banks_per_page',
			]
		);

		// Instantiate the table and store it
		require_once __DIR__ . '/class-ppq-banks-list-table.php';
		$this->list_table = new PressPrimer_Quiz_Banks_List_Table();

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

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing parameters
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
		$bank_id = isset( $_GET['bank_id'] ) ? absint( wp_unslash( $_GET['bank_id'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

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
			$this->list_table = new PressPrimer_Quiz_Banks_List_Table();
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
			if ( class_exists( 'PressPrimer_Quiz_Bank' ) ) {
				$bank = PressPrimer_Quiz_Bank::get( $bank_id );
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
			PPQ_PLUGIN_URL . 'assets/css/vendor/antd-reset.css',
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
			$bank = PressPrimer_Quiz_Bank::get( $bank_id );

			if ( $bank ) {
				$bank_data = [
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
		if ( class_exists( 'PressPrimer_Quiz_Bank' ) ) {
			$bank = PressPrimer_Quiz_Bank::get( $bank_id );
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
			'limit'  => 999,
			'offset' => 0,
		];

		// Add filters from request
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters for display
		$filter_type = isset( $_GET['filter_type'] ) ? sanitize_key( wp_unslash( $_GET['filter_type'] ) ) : '';
		if ( '' !== $filter_type ) {
			$args['type'] = $filter_type;
		}
		$filter_difficulty = isset( $_GET['filter_difficulty'] ) ? sanitize_key( wp_unslash( $_GET['filter_difficulty'] ) ) : '';
		if ( '' !== $filter_difficulty ) {
			$args['difficulty'] = $filter_difficulty;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$questions   = $bank->get_questions( $args );
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

			<!-- Add Questions Tabs -->
			<div class="ppq-bank-add-tabs">
				<div class="ppq-bank-tabs-nav">
					<button type="button" class="ppq-bank-tab ppq-bank-tab--active" data-tab="ai-generate">
						<span class="dashicons dashicons-admin-generic"></span>
						<?php esc_html_e( 'Generate with AI', 'pressprimer-quiz' ); ?>
					</button>
					<button type="button" class="ppq-bank-tab" data-tab="add-existing">
						<span class="dashicons dashicons-plus-alt"></span>
						<?php esc_html_e( 'Add Existing Questions', 'pressprimer-quiz' ); ?>
					</button>
				</div>

				<!-- AI Generation Tab -->
				<div class="ppq-bank-tab-content ppq-bank-tab-content--active" data-tab-content="ai-generate">
					<?php
					// Include AI generation panel
					if ( class_exists( 'PressPrimer_Quiz_Admin_AI_Generation' ) ) {
						$ai_generation = new PressPrimer_Quiz_Admin_AI_Generation();
						$ai_generation->render_panel( $bank_id );
					}
					?>
				</div>

				<!-- Add Existing Questions Tab -->
				<div class="ppq-bank-tab-content" data-tab-content="add-existing">
					<h3><?php esc_html_e( 'Add Existing Questions', 'pressprimer-quiz' ); ?></h3>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ppq-add-question-form" id="ppq-add-question-form">
					<?php wp_nonce_field( 'ppq_add_question_to_bank', 'ppq_add_question_nonce' ); ?>
					<input type="hidden" name="action" value="ppq_add_question_to_bank">
					<input type="hidden" name="bank_id" value="<?php echo esc_attr( $bank_id ); ?>">

					<!-- Most Recent Questions Section (moved to top) -->
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

						<p style="margin: 15px 0 0 0;">
							<button type="button" class="button button-primary ppq-add-recent-selected" disabled>
								<?php esc_html_e( 'Add Selected Questions', 'pressprimer-quiz' ); ?>
							</button>
						</p>
					</div>

					<!-- Search Section -->
					<div class="ppq-question-filters" style="background: #f9f9f9; padding: 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px;">
						<h3 style="margin-top: 0; font-size: 14px; font-weight: 600;">
							<?php esc_html_e( 'Search for Questions', 'pressprimer-quiz' ); ?>
						</h3>
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
									if ( class_exists( 'PressPrimer_Quiz_Category' ) ) {
										$categories = PressPrimer_Quiz_Category::find(
											[
												'where'    => [ 'taxonomy' => 'category' ],
												'order_by' => 'name',
												'order'    => 'ASC',
											]
										);
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
									if ( class_exists( 'PressPrimer_Quiz_Category' ) ) {
										$tags = PressPrimer_Quiz_Category::find(
											[
												'where'    => [ 'taxonomy' => 'tag' ],
												'order_by' => 'name',
												'order'    => 'ASC',
											]
										);
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

					<!-- Search Results Section -->
					<div id="ppq-question-search-results-container" style="display: none; background: #fff; border: 1px solid #c3c4c7; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
						<h3 style="margin-top: 0; font-size: 14px; font-weight: 600;">
							<?php esc_html_e( 'Search Results', 'pressprimer-quiz' ); ?>
						</h3>
						<div id="ppq-question-search-results">
							<!-- Results populated via JavaScript -->
						</div>
						<div id="ppq-search-results-pagination" style="margin-top: 10px; text-align: center; display: none;">
							<!-- Pagination controls populated via JavaScript -->
						</div>
						<p style="margin: 15px 0 0 0;">
							<button type="button" class="button button-primary ppq-add-search-selected" disabled>
								<?php esc_html_e( 'Add Selected Questions', 'pressprimer-quiz' ); ?>
							</button>
						</p>
					</div>
				</form>
				</div><!-- End Add Existing Questions Tab -->
			</div><!-- End Tabs Container -->

			<!-- Filter Questions -->
			<div id="questions-in-bank" class="ppq-form-section" style="margin-top: 20px;">
				<h2><?php esc_html_e( 'Questions in Bank', 'pressprimer-quiz' ); ?></h2>

				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>#questions-in-bank" class="ppq-filter-form">
					<input type="hidden" name="page" value="ppq-banks">
					<input type="hidden" name="action" value="view">
					<input type="hidden" name="bank_id" value="<?php echo esc_attr( $bank_id ); ?>">

					<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter state for selected() ?>
					<select name="filter_type">
						<option value=""><?php esc_html_e( 'All Types', 'pressprimer-quiz' ); ?></option>
						<option value="mc" <?php selected( isset( $_GET['filter_type'] ) ? sanitize_key( wp_unslash( $_GET['filter_type'] ) ) : '', 'mc' ); ?>>
							<?php esc_html_e( 'Multiple Choice', 'pressprimer-quiz' ); ?>
						</option>
						<option value="ma" <?php selected( isset( $_GET['filter_type'] ) ? sanitize_key( wp_unslash( $_GET['filter_type'] ) ) : '', 'ma' ); ?>>
							<?php esc_html_e( 'Multiple Answer', 'pressprimer-quiz' ); ?>
						</option>
						<option value="tf" <?php selected( isset( $_GET['filter_type'] ) ? sanitize_key( wp_unslash( $_GET['filter_type'] ) ) : '', 'tf' ); ?>>
							<?php esc_html_e( 'True/False', 'pressprimer-quiz' ); ?>
						</option>
					</select>

					<select name="filter_difficulty">
						<option value=""><?php esc_html_e( 'All Difficulties', 'pressprimer-quiz' ); ?></option>
						<option value="beginner" <?php selected( isset( $_GET['filter_difficulty'] ) ? sanitize_key( wp_unslash( $_GET['filter_difficulty'] ) ) : '', 'beginner' ); ?>>
							<?php esc_html_e( 'Beginner', 'pressprimer-quiz' ); ?>
						</option>
						<option value="intermediate" <?php selected( isset( $_GET['filter_difficulty'] ) ? sanitize_key( wp_unslash( $_GET['filter_difficulty'] ) ) : '', 'intermediate' ); ?>>
							<?php esc_html_e( 'Intermediate', 'pressprimer-quiz' ); ?>
						</option>
						<option value="advanced" <?php selected( isset( $_GET['filter_difficulty'] ) ? sanitize_key( wp_unslash( $_GET['filter_difficulty'] ) ) : '', 'advanced' ); ?>>
							<?php esc_html_e( 'Advanced', 'pressprimer-quiz' ); ?>
						</option>
						<option value="expert" <?php selected( isset( $_GET['filter_difficulty'] ) ? sanitize_key( wp_unslash( $_GET['filter_difficulty'] ) ) : '', 'expert' ); ?>>
							<?php esc_html_e( 'Expert', 'pressprimer-quiz' ); ?>
						</option>
					</select>
					<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>

					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'pressprimer-quiz' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-banks&action=view&bank_id=' . $bank_id ) ); ?>#questions-in-bank" class="button">
						<?php esc_html_e( 'Clear Filters', 'pressprimer-quiz' ); ?>
					</a>
				</form>

				<!-- Questions Table -->
				<?php
				$has_filters = ( '' !== $filter_type || '' !== $filter_difficulty );
				if ( empty( $questions ) ) :
					?>
					<p><em><?php echo $has_filters ? esc_html__( 'No questions match your filters.', 'pressprimer-quiz' ) : esc_html__( 'No questions in this bank yet.', 'pressprimer-quiz' ); ?></em></p>
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
								$revision     = $question->get_current_revision();
								$stem_preview = $revision ? wp_trim_words( wp_strip_all_tags( $revision->stem ), 15 ) : '';

								// Type labels
								$type_labels = [
									'mc' => __( 'Multiple Choice', 'pressprimer-quiz' ),
									'ma' => __( 'Multiple Answer', 'pressprimer-quiz' ),
									'tf' => __( 'True/False', 'pressprimer-quiz' ),
								];
								$type_label  = isset( $type_labels[ $question->type ] ) ? $type_labels[ $question->type ] : $question->type;

								// Difficulty labels
								$difficulty_labels = [
									'beginner'     => __( 'Beginner', 'pressprimer-quiz' ),
									'intermediate' => __( 'Intermediate', 'pressprimer-quiz' ),
									'advanced'     => __( 'Advanced', 'pressprimer-quiz' ),
									'expert'       => __( 'Expert', 'pressprimer-quiz' ),
								];
								$difficulty_label  = isset( $difficulty_labels[ $question->difficulty_author ] ) ? $difficulty_labels[ $question->difficulty_author ] : $question->difficulty_author;

								// Get categories
								$categories     = $question->get_categories();
								$category_names = [];
								foreach ( $categories as $cat ) {
									if ( 'category' === $cat->taxonomy ) {
										$category_names[] = esc_html( $cat->name );
									}
								}
								$category_display = ! empty( $category_names ) ? implode( ', ', $category_names ) : '<span class="ppq-text-muted">' . esc_html__( 'None', 'pressprimer-quiz' ) . '</span>';
								?>
								<tr>
									<td>
										<strong><?php echo esc_html( $stem_preview ); ?></strong>
										<div class="row-actions">
											<span class="edit">
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-questions&action=edit&question=' . $question->id ) ); ?>">
													<?php esc_html_e( 'Edit', 'pressprimer-quiz' ); ?>
												</a>
											</span>
										</div>
									</td>
									<td><?php echo esc_html( $type_label ); ?></td>
									<td><?php echo esc_html( $difficulty_label ); ?></td>
									<td><?php echo $category_display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Category names escaped in loop above ?></td>
									<td>
										<button
											type="button"
											class="button-link-delete ppq-remove-question-btn"
											data-bank-id="<?php echo esc_attr( $bank_id ); ?>"
											data-question-id="<?php echo esc_attr( $question->id ); ?>"
										>
											<?php esc_html_e( 'Remove from Bank', 'pressprimer-quiz' ); ?>
										</button>
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
			var recentSelectedQuestions = [];
			var searchSelectedQuestions = [];
			var currentRecentPage = 1;
			var currentSearchPage = 1;

			// Helper function to build pagination HTML
			function buildPaginationHtml(currentPage, totalPages, totalItems, navClass) {
				var pagHtml = '<div class="tablenav-pages">';
				pagHtml += '<span class="displaying-num">' + totalItems + ' <?php esc_html_e( 'items', 'pressprimer-quiz' ); ?></span>';
				pagHtml += '<span class="pagination-links">';

				// First page
				if (currentPage > 1) {
					pagHtml += '<a class="button ' + navClass + '" data-page="1" title="<?php esc_attr_e( 'First page', 'pressprimer-quiz' ); ?>">&laquo;</a> ';
					pagHtml += '<a class="button ' + navClass + '" data-page="' + (currentPage - 1) + '" title="<?php esc_attr_e( 'Previous page', 'pressprimer-quiz' ); ?>">&lsaquo;</a> ';
				} else {
					pagHtml += '<span class="button disabled">&laquo;</span> ';
					pagHtml += '<span class="button disabled">&lsaquo;</span> ';
				}

				pagHtml += '<span class="paging-input">' + currentPage + ' <?php esc_html_e( 'of', 'pressprimer-quiz' ); ?> ' + totalPages + '</span> ';

				// Last page
				if (currentPage < totalPages) {
					pagHtml += '<a class="button ' + navClass + '" data-page="' + (currentPage + 1) + '" title="<?php esc_attr_e( 'Next page', 'pressprimer-quiz' ); ?>">&rsaquo;</a> ';
					pagHtml += '<a class="button ' + navClass + '" data-page="' + totalPages + '" title="<?php esc_attr_e( 'Last page', 'pressprimer-quiz' ); ?>">&raquo;</a>';
				} else {
					pagHtml += '<span class="button disabled">&rsaquo;</span> ';
					pagHtml += '<span class="button disabled">&raquo;</span>';
				}

				pagHtml += '</span></div>';
				return pagHtml;
			}

			// Helper function to build table HTML
			function buildQuestionTableHtml(questions, selectedList, checkboxClass) {
				var html = '<table class="widefat" style="margin: 0;">';
				html += '<thead><tr>';
				html += '<th style="width: 40px; text-align: center;"><?php esc_html_e( 'Select', 'pressprimer-quiz' ); ?></th>';
				html += '<th><?php esc_html_e( 'Question', 'pressprimer-quiz' ); ?></th>';
				html += '<th style="width: 150px;"><?php esc_html_e( 'Type', 'pressprimer-quiz' ); ?></th>';
				html += '<th style="width: 120px;"><?php esc_html_e( 'Difficulty', 'pressprimer-quiz' ); ?></th>';
				html += '<th style="width: 150px;"><?php esc_html_e( 'Category', 'pressprimer-quiz' ); ?></th>';
				html += '</tr></thead><tbody>';

				$.each(questions, function(i, q) {
					var checked = selectedList.indexOf(q.id) !== -1 ? ' checked' : '';
					html += '<tr>';
					html += '<td style="text-align: center;"><input type="checkbox" value="' + q.id + '" class="' + checkboxClass + '"' + checked + '></td>';
					html += '<td><strong>' + q.stem_preview + '</strong></td>';
					html += '<td>' + q.type_label + '</td>';
					html += '<td>' + q.difficulty_label + '</td>';
					html += '<td>' + q.category + '</td>';
					html += '</tr>';
				});

				html += '</tbody></table>';
				return html;
			}

			// Function to perform question search with filters and pagination
			function searchQuestions(page) {
				currentSearchPage = page || 1;
				var searchTerm = $('#question_search').val();
				var type = $('#filter_question_type').val();
				var difficulty = $('#filter_question_difficulty').val();
				var categoryId = $('#filter_question_category').val();
				var tagId = $('#filter_question_tag').val();

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
						bank_id: <?php echo absint( $bank_id ); ?>,
						page: currentSearchPage
					},
					success: function(response) {
						if (response.success && response.data.questions) {
							var data = response.data;

							if (data.questions.length === 0) {
								$('#ppq-question-search-results').html('<p style="text-align: center; color: #646970; padding: 20px;"><em><?php esc_html_e( 'No questions found matching your search.', 'pressprimer-quiz' ); ?></em></p>');
								$('#ppq-search-results-pagination').hide();
								$('.ppq-add-search-selected').prop('disabled', true);
							} else {
								var html = buildQuestionTableHtml(data.questions, searchSelectedQuestions, 'ppq-search-checkbox');
								$('#ppq-question-search-results').html(html);

								// Build pagination
								if (data.total_pages > 1) {
									$('#ppq-search-results-pagination').html(buildPaginationHtml(currentSearchPage, data.total_pages, data.total_items, 'ppq-search-page-nav')).show();
								} else {
									$('#ppq-search-results-pagination').hide();
								}

								// Update button state
								updateSearchButtonState();
							}
							$('#ppq-question-search-results-container').show();
						}
					},
					error: function(xhr, status, error) {
						console.error('Search AJAX error:', { status: status, error: error });
						$('#ppq-question-search-results').html('<p style="color: #d63638; text-align: center;"><?php esc_html_e( 'Error searching questions. Please try again.', 'pressprimer-quiz' ); ?></p>');
						$('#ppq-question-search-results-container').show();
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
							var data = response.data;

							if (data.questions.length === 0) {
								$('#ppq-recent-questions-list').html('<p style="text-align: center; color: #646970; padding: 20px;"><em><?php esc_html_e( 'No questions found. Create some questions first!', 'pressprimer-quiz' ); ?></em></p>');
								$('#ppq-recent-questions-pagination').hide();
								$('.ppq-add-recent-selected').prop('disabled', true);
							} else {
								var html = buildQuestionTableHtml(data.questions, recentSelectedQuestions, 'ppq-recent-checkbox');
								$('#ppq-recent-questions-list').html(html);

								// Build pagination
								if (data.total_pages > 1) {
									$('#ppq-recent-questions-pagination').html(buildPaginationHtml(currentRecentPage, data.total_pages, data.total_items, 'ppq-recent-page-nav')).show();
								} else {
									$('#ppq-recent-questions-pagination').hide();
								}

								// Update button state
								updateRecentButtonState();
							}
						}
					},
					error: function(xhr, status, error) {
						console.error('Recent questions AJAX error:', { status: status, error: error });
						$('#ppq-recent-questions-list').html('<p style="color: #d63638; text-align: center;"><?php esc_html_e( 'Error loading questions. Please refresh the page.', 'pressprimer-quiz' ); ?></p>');
					}
				});
			}

			// Update button states
			function updateRecentButtonState() {
				$('.ppq-add-recent-selected').prop('disabled', recentSelectedQuestions.length === 0);
			}

			function updateSearchButtonState() {
				$('.ppq-add-search-selected').prop('disabled', searchSelectedQuestions.length === 0);
			}

			// Load recent questions on page load
			loadRecentQuestions(1);

			// Handle recent questions pagination clicks
			$(document).on('click', '.ppq-recent-page-nav', function(e) {
				e.preventDefault();
				loadRecentQuestions(parseInt($(this).data('page')));
			});

			// Handle search results pagination clicks
			$(document).on('click', '.ppq-search-page-nav', function(e) {
				e.preventDefault();
				searchQuestions(parseInt($(this).data('page')));
			});

			// Search on keyup with debounce
			$('#question_search').on('keyup', function() {
				var searchTerm = $(this).val();
				clearTimeout(searchTimeout);

				if (searchTerm.length < 2) {
					$('#ppq-question-search-results-container').hide();
					return;
				}

				searchTimeout = setTimeout(function() {
					searchQuestions(1);
				}, 300);
			});

			// Search button click
			$('#ppq-search-questions').on('click', function(e) {
				e.preventDefault();
				searchQuestions(1);
			});

			// Reset filters button click
			$('#ppq-reset-filters').on('click', function(e) {
				e.preventDefault();
				$('#question_search').val('');
				$('#filter_question_type').val('');
				$('#filter_question_difficulty').val('');
				$('#filter_question_category').val('');
				$('#filter_question_tag').val('');
				$('#ppq-question-search-results-container').hide();
				searchSelectedQuestions = [];
				updateSearchButtonState();
			});

			// Also trigger search when filters change
			$('.ppq-question-filter').on('change', function() {
				if ($('#question_search').val().length >= 2 ||
					$('#filter_question_type').val() ||
					$('#filter_question_difficulty').val() ||
					$('#filter_question_category').val() ||
					$('#filter_question_tag').val()) {
					searchQuestions(1);
				}
			});

			// Handle recent questions checkbox changes
			$(document).on('change', '.ppq-recent-checkbox', function() {
				var questionId = parseInt($(this).val());
				if ($(this).is(':checked')) {
					if (recentSelectedQuestions.indexOf(questionId) === -1) {
						recentSelectedQuestions.push(questionId);
					}
				} else {
					recentSelectedQuestions = recentSelectedQuestions.filter(function(id) {
						return id !== questionId;
					});
				}
				updateRecentButtonState();
			});

			// Handle search results checkbox changes
			$(document).on('change', '.ppq-search-checkbox', function() {
				var questionId = parseInt($(this).val());
				if ($(this).is(':checked')) {
					if (searchSelectedQuestions.indexOf(questionId) === -1) {
						searchSelectedQuestions.push(questionId);
					}
				} else {
					searchSelectedQuestions = searchSelectedQuestions.filter(function(id) {
						return id !== questionId;
					});
				}
				updateSearchButtonState();
			});

			// Handle Add Selected from recent questions
			$(document).on('click', '.ppq-add-recent-selected', function(e) {
				e.preventDefault();
				if (recentSelectedQuestions.length === 0) return;
				submitSelectedQuestions(recentSelectedQuestions, $(this));
			});

			// Handle Add Selected from search results
			$(document).on('click', '.ppq-add-search-selected', function(e) {
				e.preventDefault();
				if (searchSelectedQuestions.length === 0) return;
				submitSelectedQuestions(searchSelectedQuestions, $(this));
			});

			// Function to submit selected questions via form
			function submitSelectedQuestions(questionIds, $button) {
				var $form = $('#ppq-add-question-form');

				// Remove any existing hidden inputs
				$form.find('input[name="question_ids[]"]').remove();

				// Add hidden inputs for selected questions
				$.each(questionIds, function(i, id) {
					$form.append('<input type="hidden" name="question_ids[]" value="' + id + '">');
				});

				// Disable button and submit
				$button.prop('disabled', true).text('<?php esc_html_e( 'Adding...', 'pressprimer-quiz' ); ?>');
				$form.submit();
			}

			// Remove question from bank via AJAX
			$(document).on('click', '.ppq-remove-question-btn', function() {
				var $btn = $(this);
				var $row = $btn.closest('tr');
				var bankId = $btn.data('bank-id');
				var questionId = $btn.data('question-id');

				// Disable button during request
				$btn.prop('disabled', true).text('<?php esc_html_e( 'Removing...', 'pressprimer-quiz' ); ?>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'ppq_remove_question_from_bank',
						nonce: window.ppqAdmin.nonce,
						bank_id: bankId,
						question_id: questionId
					},
					success: function(response) {
						if (response.success) {
							// Remove the row with a fade effect
							$row.fadeOut(300, function() {
								$(this).remove();

								// Update the question count display
								var $countDisplay = $('.ppq-form-section').first().find('div[style*="font-size: 48px"]');
								if ($countDisplay.length && response.data.new_count !== undefined) {
									$countDisplay.text(response.data.new_count);
								}

								// Check if table is now empty
								if ($('.ppq-table tbody tr').length === 0) {
									$('.ppq-table').replaceWith('<p><em><?php esc_html_e( 'No questions in this bank yet.', 'pressprimer-quiz' ); ?></em></p>');
								}
							});
						} else {
							$btn.prop('disabled', false).text('<?php esc_html_e( 'Remove from Bank', 'pressprimer-quiz' ); ?>');
							alert(response.data.message || '<?php esc_html_e( 'Error removing question.', 'pressprimer-quiz' ); ?>');
						}
					},
					error: function() {
						$btn.prop('disabled', false).text('<?php esc_html_e( 'Remove from Bank', 'pressprimer-quiz' ); ?>');
						alert('<?php esc_html_e( 'Error removing question. Please try again.', 'pressprimer-quiz' ); ?>');
					}
				});
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
		if ( ! isset( $_POST['ppq_bank_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ppq_bank_nonce'] ) ), 'ppq_save_bank' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressprimer-quiz' ) );
		}

		$bank_id     = isset( $_POST['bank_id'] ) ? absint( wp_unslash( $_POST['bank_id'] ) ) : 0;
		$name        = isset( $_POST['bank_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_name'] ) ) : '';
		$description = isset( $_POST['bank_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bank_description'] ) ) : '';

		// Validate
		if ( empty( $name ) ) {
			wp_die( esc_html__( 'Bank name is required.', 'pressprimer-quiz' ) );
		}

		// Check ownership for updates
		if ( $bank_id > 0 ) {
			$bank = null;
			if ( class_exists( 'PressPrimer_Quiz_Bank' ) ) {
				$bank = PressPrimer_Quiz_Bank::get( $bank_id );
			}

			if ( ! $bank ) {
				wp_die( esc_html__( 'Bank not found.', 'pressprimer-quiz' ) );
			}

			if ( ! current_user_can( 'ppq_manage_all' ) && absint( $bank->owner_id ) !== get_current_user_id() ) {
				wp_die( esc_html__( 'You do not have permission to edit this bank.', 'pressprimer-quiz' ) );
			}

			// Update existing bank
			$bank->name        = $name;
			$bank->description = $description;
			$result            = $bank->save();
		} else {
			// Create new bank
			$bank              = new PressPrimer_Quiz_Bank();
			$bank->name        = $name;
			$bank->description = $description;
			$bank->owner_id    = get_current_user_id();
			$result            = $bank->save();

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
					'page'    => 'ppq-banks',
					'action'  => 'view',
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
		// Verify bank_id is set
		if ( ! isset( $_GET['bank_id'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'pressprimer-quiz' ) );
		}

		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ppq_delete_bank_' . absint( wp_unslash( $_GET['bank_id'] ) ) ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressprimer-quiz' ) );
		}

		$bank_id = isset( $_GET['bank_id'] ) ? absint( wp_unslash( $_GET['bank_id'] ) ) : 0;

		if ( ! $bank_id ) {
			wp_die( esc_html__( 'Invalid bank ID.', 'pressprimer-quiz' ) );
		}

		$bank = null;
		if ( class_exists( 'PressPrimer_Quiz_Bank' ) ) {
			$bank = PressPrimer_Quiz_Bank::get( $bank_id );
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
					'page'    => 'ppq-banks',
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
		if ( ! isset( $_POST['ppq_add_question_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ppq_add_question_nonce'] ) ), 'ppq_add_question_to_bank' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressprimer-quiz' ) );
		}

		$bank_id = isset( $_POST['bank_id'] ) ? absint( wp_unslash( $_POST['bank_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values sanitized with absint via array_map
		$question_ids = isset( $_POST['question_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['question_ids'] ) ) : [];

		if ( ! $bank_id || empty( $question_ids ) ) {
			wp_die( esc_html__( 'Invalid bank or question IDs.', 'pressprimer-quiz' ) );
		}

		$bank = null;
		if ( class_exists( 'PressPrimer_Quiz_Bank' ) ) {
			$bank = PressPrimer_Quiz_Bank::get( $bank_id );
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
			$bank->add_question( $question_id );
		}

		// Update count
		$bank->update_question_count();

		// Redirect with success message
		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => 'ppq-banks',
					'action'  => 'view',
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
		if ( ! isset( $_POST['ppq_remove_question_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ppq_remove_question_nonce'] ) ), 'ppq_remove_question_from_bank' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressprimer-quiz' ) );
		}

		$bank_id     = isset( $_POST['bank_id'] ) ? absint( wp_unslash( $_POST['bank_id'] ) ) : 0;
		$question_id = isset( $_POST['question_id'] ) ? absint( wp_unslash( $_POST['question_id'] ) ) : 0;

		if ( ! $bank_id || ! $question_id ) {
			wp_die( esc_html__( 'Invalid bank or question ID.', 'pressprimer-quiz' ) );
		}

		$bank = null;
		if ( class_exists( 'PressPrimer_Quiz_Bank' ) ) {
			$bank = PressPrimer_Quiz_Bank::get( $bank_id );
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
					'page'    => 'ppq-banks',
					'action'  => 'view',
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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only notice flags from redirect
		if ( ! isset( $_GET['page'] ) || 'ppq-banks' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['message'] ) ) {
			return;
		}

		$message = sanitize_key( wp_unslash( $_GET['message'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$class = 'notice notice-success is-dismissible';
		$text  = '';

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
