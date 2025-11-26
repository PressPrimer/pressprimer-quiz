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
		require_once __DIR__ . '/class-ppq-banks-list-table.php';
		$list_table = new PPQ_Banks_List_Table();
		$list_table->prepare_items();

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
				$list_table->search_box( __( 'Search Banks', 'pressprimer-quiz' ), 'ppq-bank' );
				$list_table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render bank editor
	 *
	 * @since 1.0.0
	 *
	 * @param int $bank_id Bank ID (0 for new).
	 */
	private function render_editor( $bank_id = 0 ) {
		$bank = null;
		$is_new = true;

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
			if ( ! current_user_can( 'ppq_manage_all' ) && absint( $bank->author_id ) !== get_current_user_id() ) {
				wp_die(
					esc_html__( 'You do not have permission to edit this bank.', 'pressprimer-quiz' ),
					esc_html__( 'Permission Denied', 'pressprimer-quiz' ),
					[ 'response' => 403 ]
				);
			}

			$is_new = false;
		}

		// Default values
		$name = $bank ? $bank->name : '';
		$description = $bank ? $bank->description : '';

		?>
		<div class="wrap">
			<h1><?php echo $is_new ? esc_html__( 'Add Question Bank', 'pressprimer-quiz' ) : esc_html__( 'Edit Question Bank', 'pressprimer-quiz' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ppq-bank-form">
				<?php wp_nonce_field( 'ppq_save_bank', 'ppq_bank_nonce' ); ?>
				<input type="hidden" name="action" value="ppq_save_bank">
				<input type="hidden" name="bank_id" value="<?php echo esc_attr( $bank_id ); ?>">

				<table class="form-table ppq-form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="bank_name"><?php esc_html_e( 'Name', 'pressprimer-quiz' ); ?> <span class="required">*</span></label>
							</th>
							<td>
								<input
									type="text"
									id="bank_name"
									name="bank_name"
									value="<?php echo esc_attr( $name ); ?>"
									class="regular-text"
									required
									maxlength="200"
								>
								<p class="description">
									<?php esc_html_e( 'A descriptive name for this question bank.', 'pressprimer-quiz' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="bank_description"><?php esc_html_e( 'Description', 'pressprimer-quiz' ); ?></label>
							</th>
							<td>
								<textarea
									id="bank_description"
									name="bank_description"
									rows="5"
									class="large-text"
									maxlength="2000"
								><?php echo esc_textarea( $description ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Optional description of the bank\'s purpose or contents.', 'pressprimer-quiz' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php echo $is_new ? esc_html__( 'Create Bank', 'pressprimer-quiz' ) : esc_html__( 'Update Bank', 'pressprimer-quiz' ); ?>
					</button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-banks' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Cancel', 'pressprimer-quiz' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php
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

			<?php if ( $bank->description ) : ?>
				<p class="description"><?php echo esc_html( $bank->description ); ?></p>
			<?php endif; ?>

			<p>
				<strong><?php esc_html_e( 'Total Questions:', 'pressprimer-quiz' ); ?></strong>
				<?php echo absint( $total_count ); ?>
			</p>

			<hr class="wp-header-end">

			<!-- Add Question Section -->
			<div class="ppq-form-section">
				<h2><?php esc_html_e( 'Add Questions to Bank', 'pressprimer-quiz' ); ?></h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ppq-add-question-form">
					<?php wp_nonce_field( 'ppq_add_question_to_bank', 'ppq_add_question_nonce' ); ?>
					<input type="hidden" name="action" value="ppq_add_question_to_bank">
					<input type="hidden" name="bank_id" value="<?php echo esc_attr( $bank_id ); ?>">

					<p>
						<label for="question_search"><?php esc_html_e( 'Search Questions:', 'pressprimer-quiz' ); ?></label>
						<input
							type="text"
							id="question_search"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'Type to search questions...', 'pressprimer-quiz' ); ?>"
						>
					</p>

					<div id="ppq-question-search-results" style="display: none; background: #fff; border: 1px solid #c3c4c7; padding: 10px; margin-bottom: 15px; max-height: 300px; overflow-y: auto;">
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
								$difficulty_label = isset( $difficulty_labels[ $question->difficulty ] ) ? $difficulty_labels[ $question->difficulty ] : $question->difficulty;
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

			$('#question_search').on('keyup', function() {
				var searchTerm = $(this).val();

				clearTimeout(searchTimeout);

				if (searchTerm.length < 2) {
					$('#ppq-question-search-results').hide();
					return;
				}

				searchTimeout = setTimeout(function() {
					// AJAX search for questions
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'ppq_search_questions',
							nonce: '<?php echo esc_js( wp_create_nonce( 'ppq_search_questions' ) ); ?>',
							search: searchTerm,
							bank_id: <?php echo absint( $bank_id ); ?>
						},
						success: function(response) {
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
							}
						}
					});
				}, 300);
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

			if ( ! current_user_can( 'ppq_manage_all' ) && absint( $bank->author_id ) !== get_current_user_id() ) {
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
			$bank->author_id = get_current_user_id();
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
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $bank->author_id ) !== get_current_user_id() ) {
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
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $bank->author_id ) !== get_current_user_id() ) {
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
		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $bank->author_id ) !== get_current_user_id() ) {
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
