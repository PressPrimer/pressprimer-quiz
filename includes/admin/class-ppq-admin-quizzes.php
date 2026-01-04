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
class PressPrimer_Quiz_Admin_Quizzes {

	/**
	 * List table instance
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Quiz_Quizzes_List_Table
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
		add_action( 'wp_ajax_pressprimer_quiz_get_available_questions', [ $this, 'ajax_get_available_questions' ] );
		add_action( 'wp_ajax_pressprimer_quiz_add_quiz_questions', [ $this, 'ajax_add_quiz_questions' ] );
		add_action( 'wp_ajax_pressprimer_quiz_remove_quiz_question', [ $this, 'ajax_remove_quiz_question' ] );
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for display routing
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( in_array( $action, [ 'new', 'edit' ], true ) ) {
			return;
		}

		// Add per page option
		add_screen_option(
			'per_page',
			[
				'label'   => __( 'Quizzes per page', 'pressprimer-quiz' ),
				'default' => 20,
				'option'  => 'pressprimer_quiz_quizzes_per_page',
			]
		);

		// Instantiate the table and store it
		$this->list_table = new PressPrimer_Quiz_Quizzes_List_Table();

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
	 * @param mixed  $status Screen option value. Default false to skip.
	 * @param string $option The option name.
	 * @param mixed  $value  The option value.
	 * @return mixed Screen option value.
	 */
	public function set_screen_option( $status, $option, $value ) {
		if ( 'pressprimer_quiz_quizzes_per_page' === $option ) {
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
		// Check for POST actions (save) - nonce verified in handle_save()
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification delegated to handle_save()
		if ( isset( $_POST['pressprimer_quiz_save_quiz'] ) ) {
			$this->handle_save();
		}

		// Check for actions
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified in individual handlers
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'delete' === $action && isset( $_GET['quiz'] ) ) {
			$this->handle_delete();
		}

		if ( 'duplicate' === $action && isset( $_GET['quiz'] ) ) {
			$this->handle_duplicate();
		}

		// Bulk actions - use current_bulk_action() to properly detect which action was submitted
		$bulk_action = $this->current_bulk_action();

		if ( 'delete' === $bulk_action && isset( $_GET['quizzes'] ) ) {
			$this->handle_bulk_delete();
		}

		if ( 'publish' === $bulk_action && isset( $_GET['quizzes'] ) ) {
			$this->handle_bulk_publish();
		}

		if ( 'draft' === $bulk_action && isset( $_GET['quizzes'] ) ) {
			$this->handle_bulk_draft();
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Display admin notices
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only notice flags from redirect
		if ( ! isset( $_GET['page'] ) || 'pressprimer-quiz-quizzes' !== $_GET['page'] ) {
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

		if ( isset( $_GET['deleted'] ) && absint( wp_unslash( $_GET['deleted'] ) ) > 0 ) {
			$count = absint( wp_unslash( $_GET['deleted'] ) );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of quizzes deleted */
						esc_html( _n( '%d quiz deleted.', '%d quizzes deleted.', $count, 'pressprimer-quiz' ) ),
						(int) $count
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

		if ( isset( $_GET['published'] ) && absint( wp_unslash( $_GET['published'] ) ) > 0 ) {
			$count = absint( wp_unslash( $_GET['published'] ) );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of quizzes published */
						esc_html( _n( '%d quiz published.', '%d quizzes published.', $count, 'pressprimer-quiz' ) ),
						(int) $count
					);
					?>
				</p>
			</div>
			<?php
		}

		if ( isset( $_GET['drafted'] ) && absint( wp_unslash( $_GET['drafted'] ) ) > 0 ) {
			$count = absint( wp_unslash( $_GET['drafted'] ) );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: %d: number of quizzes moved to draft */
						esc_html( _n( '%d quiz moved to draft.', '%d quizzes moved to draft.', $count, 'pressprimer-quiz' ) ),
						(int) $count
					);
					?>
				</p>
			</div>
			<?php
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Render admin page
	 *
	 * @since 1.0.0
	 */
	public function render() {
		// Check user permissions
		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pressprimer-quiz' ) );
		}

		// Get action
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for display routing
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		// Route to appropriate view
		if ( 'edit' === $action || 'new' === $action ) {
			$this->render_edit();
		} elseif ( 'preview' === $action ) {
			$this->render_preview();
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
			$this->list_table = new PressPrimer_Quiz_Quizzes_List_Table();
		}

		$this->list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Quizzes', 'pressprimer-quiz' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=pressprimer-quiz-quizzes&action=new' ) ); ?>" class="page-title-action">
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only quiz ID for editor display
		$quiz_id = isset( $_GET['quiz'] ) ? absint( wp_unslash( $_GET['quiz'] ) ) : 0;
		$quiz    = null;

		// Load quiz if editing
		if ( $quiz_id ) {
			$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );

			if ( ! $quiz ) {
				wp_die( esc_html__( 'Quiz not found.', 'pressprimer-quiz' ) );
			}

			// Check permission
			if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
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
	 * Render quiz preview
	 *
	 * @since 1.0.0
	 */
	private function render_preview() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only quiz ID for preview display
		$quiz_id = isset( $_GET['quiz'] ) ? absint( wp_unslash( $_GET['quiz'] ) ) : 0;

		if ( ! $quiz_id ) {
			wp_die( esc_html__( 'Invalid quiz ID.', 'pressprimer-quiz' ) );
		}

		$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );

		if ( ! $quiz ) {
			wp_die( esc_html__( 'Quiz not found.', 'pressprimer-quiz' ) );
		}

		// Check permission
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
			wp_die( esc_html__( 'You do not have permission to preview this quiz.', 'pressprimer-quiz' ) );
		}

		// Get questions for this quiz
		$question_ids = $quiz->get_questions_for_attempt();

		if ( empty( $question_ids ) ) {
			wp_die( esc_html__( 'This quiz has no questions configured.', 'pressprimer-quiz' ) );
		}

		// Load questions
		$questions = [];
		foreach ( $question_ids as $question_id ) {
			$question = PressPrimer_Quiz_Question::get( $question_id );
			if ( $question ) {
				$revision = $question->get_current_revision();
				if ( $revision ) {
					$questions[] = [
						'question' => $question,
						'revision' => $revision,
					];
				}
			}
		}

		// Enqueue preview styles
		wp_enqueue_style(
			'ppq-quiz-preview',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/css/quiz-preview.css',
			[],
			PRESSPRIMER_QUIZ_VERSION
		);

		// Render preview page
		$this->render_preview_page( $quiz, $questions );
	}

	/**
	 * Render preview page HTML
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Quiz $quiz Quiz object.
	 * @param array                 $questions Array of question/revision pairs.
	 */
	private function render_preview_page( $quiz, $questions ) {
		?>
		<div class="wrap ppq-quiz-preview-wrap">
			<!-- Preview Mode Banner -->
			<div class="ppq-preview-banner">
				<strong><?php esc_html_e( 'PREVIEW MODE', 'pressprimer-quiz' ); ?></strong>
				<?php esc_html_e( 'This is a preview - no data will be saved.', 'pressprimer-quiz' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pressprimer-quiz-quizzes' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Exit Preview', 'pressprimer-quiz' ); ?>
				</a>
			</div>

			<!-- Quiz Landing Page Section -->
			<div class="ppq-preview-section">
				<h2><?php esc_html_e( 'Landing Page', 'pressprimer-quiz' ); ?></h2>
				<div class="ppq-quiz-landing">
					<h1 class="ppq-quiz-title"><?php echo esc_html( $quiz->title ); ?></h1>

					<?php if ( ! empty( $quiz->description ) ) : ?>
						<div class="ppq-quiz-description">
							<?php echo wp_kses_post( $quiz->description ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $quiz->featured_image_id ) : ?>
						<div class="ppq-quiz-featured-image">
							<?php echo wp_get_attachment_image( $quiz->featured_image_id, 'large' ); ?>
						</div>
					<?php endif; ?>

					<div class="ppq-quiz-info">
						<div class="ppq-quiz-info-item">
							<strong><?php esc_html_e( 'Questions:', 'pressprimer-quiz' ); ?></strong>
							<?php echo esc_html( count( $questions ) ); ?>
						</div>

						<?php if ( $quiz->time_limit_seconds ) : ?>
							<div class="ppq-quiz-info-item">
								<strong><?php esc_html_e( 'Time Limit:', 'pressprimer-quiz' ); ?></strong>
								<?php
								$minutes = floor( $quiz->time_limit_seconds / 60 );
								/* translators: %d: number of minutes for time limit */
								echo esc_html( sprintf( _n( '%d minute', '%d minutes', $minutes, 'pressprimer-quiz' ), $minutes ) );
								?>
							</div>
						<?php endif; ?>

						<div class="ppq-quiz-info-item">
							<strong><?php esc_html_e( 'Passing Score:', 'pressprimer-quiz' ); ?></strong>
							<?php echo esc_html( $quiz->pass_percent . '%' ); ?>
						</div>

						<div class="ppq-quiz-info-item">
							<strong><?php esc_html_e( 'Mode:', 'pressprimer-quiz' ); ?></strong>
							<?php echo 'tutorial' === $quiz->mode ? esc_html__( 'Tutorial', 'pressprimer-quiz' ) : esc_html__( 'Test', 'pressprimer-quiz' ); ?>
						</div>
					</div>

					<button class="button button-primary button-large" disabled>
						<?php esc_html_e( 'Start Quiz (Preview Only)', 'pressprimer-quiz' ); ?>
					</button>
				</div>
			</div>

			<!-- Quiz Questions Section -->
			<div class="ppq-preview-section">
				<h2><?php esc_html_e( 'Questions', 'pressprimer-quiz' ); ?></h2>
				<?php foreach ( $questions as $index => $item ) : ?>
					<?php
					$question = $item['question'];
					$revision = $item['revision'];
					$answers  = $revision->get_answers();
					?>
					<div class="ppq-preview-question">
						<div class="ppq-question-number">
							<?php
							printf(
								/* translators: 1: current question number, 2: total questions */
								esc_html__( 'Question %1$d of %2$d', 'pressprimer-quiz' ),
								(int) ( $index + 1 ),
								(int) count( $questions )
							);
							?>
						</div>

						<div class="ppq-question-stem">
							<?php echo wp_kses_post( $revision->stem ); ?>
						</div>

						<div class="ppq-question-meta">
							<span class="ppq-question-type">
								<?php
								$types = [
									'multiple_choice' => __( 'Multiple Choice', 'pressprimer-quiz' ),
									'multiple_answer' => __( 'Multiple Answer', 'pressprimer-quiz' ),
									'true_false'      => __( 'True/False', 'pressprimer-quiz' ),
									// Handle abbreviated versions
									'mc'              => __( 'Multiple Choice', 'pressprimer-quiz' ),
									'ma'              => __( 'Multiple Answer', 'pressprimer-quiz' ),
									'tf'              => __( 'True/False', 'pressprimer-quiz' ),
								];
								echo esc_html( $types[ $question->type ] ?? ucwords( str_replace( '_', ' ', $question->type ) ) );
								?>
							</span>
							<span class="ppq-question-difficulty">
								<?php echo esc_html( ucfirst( $question->difficulty_author ) ); ?>
							</span>
							<span class="ppq-question-points">
								<?php
								printf(
									/* translators: %s: point value */
									esc_html__( '%s points', 'pressprimer-quiz' ),
									esc_html( $question->max_points )
								);
								?>
							</span>
						</div>

						<div class="ppq-question-answers">
							<?php foreach ( $answers as $answer_index => $answer ) : ?>
								<div class="ppq-answer-option <?php echo esc_attr( $answer['is_correct'] ? 'ppq-answer-correct' : '' ); ?>">
									<?php if ( 'multiple_choice' === $question->type || 'true_false' === $question->type ) : ?>
										<input type="radio" disabled>
									<?php else : ?>
										<input type="checkbox" disabled>
									<?php endif; ?>
									<span class="ppq-answer-text"><?php echo wp_kses_post( $answer['text'] ); ?></span>
									<?php if ( $answer['is_correct'] ) : ?>
										<span class="ppq-correct-indicator"><?php esc_html_e( '(Correct)', 'pressprimer-quiz' ); ?></span>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>

						<?php if ( ! empty( $revision->feedback_correct ) || ! empty( $revision->feedback_incorrect ) ) : ?>
							<div class="ppq-question-feedback">
								<?php if ( ! empty( $revision->feedback_correct ) ) : ?>
									<div class="ppq-feedback-correct">
										<strong><?php esc_html_e( 'Correct Feedback:', 'pressprimer-quiz' ); ?></strong>
										<?php echo wp_kses_post( $revision->feedback_correct ); ?>
									</div>
								<?php endif; ?>
								<?php if ( ! empty( $revision->feedback_incorrect ) ) : ?>
									<div class="ppq-feedback-incorrect">
										<strong><?php esc_html_e( 'Incorrect Feedback:', 'pressprimer-quiz' ); ?></strong>
										<?php echo wp_kses_post( $revision->feedback_incorrect ); ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<!-- Mock Results Section -->
			<div class="ppq-preview-section">
				<h2><?php esc_html_e( 'Mock Results', 'pressprimer-quiz' ); ?></h2>
				<div class="ppq-preview-results">
					<div class="ppq-results-score">
						<div class="ppq-score-circle">
							<span class="ppq-score-value">85%</span>
						</div>
						<div class="ppq-pass-status ppq-pass">
							<?php esc_html_e( 'PASSED', 'pressprimer-quiz' ); ?>
						</div>
					</div>

					<div class="ppq-results-details">
						<div class="ppq-result-item">
							<strong><?php esc_html_e( 'Correct:', 'pressprimer-quiz' ); ?></strong>
							<?php
							$correct = ceil( count( $questions ) * 0.85 );
							printf(
								/* translators: 1: correct count, 2: total questions */
								esc_html__( '%1$d of %2$d', 'pressprimer-quiz' ),
								(int) $correct,
								(int) count( $questions )
							);
							?>
						</div>
						<div class="ppq-result-item">
							<strong><?php esc_html_e( 'Time Spent:', 'pressprimer-quiz' ); ?></strong>
							<?php esc_html_e( '15 minutes', 'pressprimer-quiz' ); ?>
						</div>
						<div class="ppq-result-item">
							<strong><?php esc_html_e( 'Passing Score:', 'pressprimer-quiz' ); ?></strong>
							<?php echo esc_html( $quiz->pass_percent . '%' ); ?>
						</div>
					</div>

					<?php
					// Show feedback for 85% score
					$feedback = $quiz->get_feedback_for_score( 85 );
					if ( ! empty( $feedback ) ) :
						?>
						<div class="ppq-results-feedback">
							<h3><?php esc_html_e( 'Feedback', 'pressprimer-quiz' ); ?></h3>
							<?php echo wp_kses_post( $feedback ); ?>
						</div>
					<?php endif; ?>

					<p class="ppq-preview-note">
						<em><?php esc_html_e( 'This is a sample result showing 85% score. Actual results will vary based on student performance.', 'pressprimer-quiz' ); ?></em>
					</p>
				</div>
			</div>

			<!-- Exit Preview Button -->
			<div class="ppq-preview-footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pressprimer-quiz-quizzes' ) ); ?>" class="button button-secondary button-large">
					<?php esc_html_e( 'Exit Preview', 'pressprimer-quiz' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pressprimer-quiz-quizzes&action=edit&quiz=' . $quiz->id ) ); ?>" class="button button-primary button-large">
					<?php esc_html_e( 'Edit Quiz', 'pressprimer-quiz' ); ?>
				</a>
			</div>
		</div>
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
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/css/vendor/antd-reset.css',
			[],
			'5.12.0'
		);

		// Enqueue the built React bundle
		wp_enqueue_script(
			'ppq-quiz-editor',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/quiz-editor.js',
			[ 'wp-element', 'wp-i18n', 'wp-api-fetch' ],
			PRESSPRIMER_QUIZ_VERSION,
			true
		);

		wp_enqueue_style(
			'ppq-quiz-editor',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/style-quiz-editor.css',
			[],
			PRESSPRIMER_QUIZ_VERSION
		);

		// Prepare quiz data for JavaScript
		$quiz_data = [];

		if ( $quiz_id > 0 ) {
			$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );

			if ( $quiz ) {
				$quiz_data = [
					'id'                    => $quiz->id,
					'title'                 => $quiz->title,
					'description'           => $quiz->description,
					'featured_image_id'     => $quiz->featured_image_id,
					'status'                => $quiz->status,
					'mode'                  => $quiz->mode,
					'time_limit_seconds'    => $quiz->time_limit_seconds,
					'pass_percent'          => $quiz->pass_percent,
					'allow_skip'            => (bool) $quiz->allow_skip,
					'allow_backward'        => (bool) $quiz->allow_backward,
					'allow_resume'          => (bool) $quiz->allow_resume,
					'randomize_questions'   => (bool) $quiz->randomize_questions,
					'randomize_answers'     => (bool) $quiz->randomize_answers,
					'page_mode'             => $quiz->page_mode,
					'questions_per_page'    => $quiz->questions_per_page,
					'show_answers'          => $quiz->show_answers,
					'enable_confidence'     => (bool) $quiz->enable_confidence,
					'theme'                 => $quiz->theme,
					'max_attempts'          => $quiz->max_attempts,
					'attempt_delay_minutes' => $quiz->attempt_delay_minutes,
					'generation_mode'       => $quiz->generation_mode,
					'band_feedback_json'    => $quiz->band_feedback_json,
				];
			}
		} else {
			// For new quizzes, load defaults from settings
			$settings  = get_option( 'pressprimer_quiz_settings', [] );
			$quiz_data = [
				'mode'         => isset( $settings['default_quiz_mode'] ) ? $settings['default_quiz_mode'] : 'tutorial',
				'pass_percent' => isset( $settings['default_passing_score'] ) ? (float) $settings['default_passing_score'] : 70,
			];
		}

		// Localize script with data
		wp_localize_script(
			'ppq-quiz-editor',
			'pressprimerQuizQuizData',
			$quiz_data
		);

		// Also pass admin URL
		wp_localize_script(
			'ppq-quiz-editor',
			'pressprimerQuizAdmin',
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
		if ( ! isset( $_POST['pressprimer_quiz_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pressprimer_quiz_nonce'] ) ), 'pressprimer_quiz_save_quiz' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to save quizzes.', 'pressprimer-quiz' ) );
		}

		$quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;

		// Prepare data
		$data = [
			'title'                 => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'description'           => isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '',
			'featured_image_id'     => isset( $_POST['featured_image_id'] ) ? absint( wp_unslash( $_POST['featured_image_id'] ) ) : 0,
			'status'                => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'draft',
			'mode'                  => isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'tutorial',
			'pass_percent'          => isset( $_POST['pass_percent'] ) ? floatval( wp_unslash( $_POST['pass_percent'] ) ) : 70,
			'allow_skip'            => isset( $_POST['allow_skip'] ) ? 1 : 0,
			'allow_backward'        => isset( $_POST['allow_backward'] ) ? 1 : 0,
			'allow_resume'          => isset( $_POST['allow_resume'] ) ? 1 : 0,
			'randomize_questions'   => isset( $_POST['randomize_questions'] ) ? 1 : 0,
			'randomize_answers'     => isset( $_POST['randomize_answers'] ) ? 1 : 0,
			'page_mode'             => isset( $_POST['page_mode'] ) ? sanitize_key( wp_unslash( $_POST['page_mode'] ) ) : 'single',
			'questions_per_page'    => isset( $_POST['questions_per_page'] ) ? absint( wp_unslash( $_POST['questions_per_page'] ) ) : 1,
			'show_answers'          => isset( $_POST['show_answers'] ) ? sanitize_key( wp_unslash( $_POST['show_answers'] ) ) : 'after_submit',
			'enable_confidence'     => isset( $_POST['enable_confidence'] ) ? 1 : 0,
			'theme'                 => isset( $_POST['theme'] ) ? sanitize_key( wp_unslash( $_POST['theme'] ) ) : 'default',
			'generation_mode'       => isset( $_POST['generation_mode'] ) ? sanitize_key( wp_unslash( $_POST['generation_mode'] ) ) : 'fixed',
			'attempt_delay_minutes' => isset( $_POST['attempt_delay_minutes'] ) ? absint( wp_unslash( $_POST['attempt_delay_minutes'] ) ) : 0,
		];

		// Handle time limit (convert minutes to seconds, or NULL if not enabled)
		if ( isset( $_POST['time_limit_minutes'] ) && '' !== $_POST['time_limit_minutes'] ) {
			$data['time_limit_seconds'] = absint( wp_unslash( $_POST['time_limit_minutes'] ) ) * 60;
		} else {
			$data['time_limit_seconds'] = null;
		}

		// Handle max attempts (or NULL if not enabled)
		if ( isset( $_POST['max_attempts'] ) && '' !== $_POST['max_attempts'] ) {
			$data['max_attempts'] = absint( wp_unslash( $_POST['max_attempts'] ) );
		} else {
			$data['max_attempts'] = null;
		}

		// Update or create
		if ( $quiz_id ) {
			// Update existing quiz
			$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );

			if ( ! $quiz ) {
				wp_die( esc_html__( 'Quiz not found.', 'pressprimer-quiz' ) );
			}

			// Check permission
			if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
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
			if ( 'fixed' === $quiz->generation_mode && isset( $_POST['item_weights'] ) && is_array( $_POST['item_weights'] ) ) {
				global $wpdb;
				$items_table = $wpdb->prefix . 'ppq_quiz_items';

				// Sanitize the entire item_weights array upfront
				$item_weights_sanitized = array_map( 'sanitize_text_field', wp_unslash( $_POST['item_weights'] ) );

				foreach ( $item_weights_sanitized as $item_id => $weight ) {
					// Validate and sanitize item_id as integer
					$item_id = absint( $item_id );
					if ( ! $item_id ) {
						continue;
					}

					// Convert sanitized weight to float
					$weight = floatval( $weight );

					// Validate weight range (0-100)
					if ( $weight < 0 || $weight > 100 ) {
						continue;
					}

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update for performance, values sanitized above
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
						'page'   => 'pressprimer-quiz-quizzes',
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
			$quiz_id = PressPrimer_Quiz_Quiz::create( $data );

			if ( is_wp_error( $quiz_id ) ) {
				wp_die( esc_html( $quiz_id->get_error_message() ) );
			}

			// Redirect to edit page with success message
			wp_safe_redirect(
				add_query_arg(
					[
						'page'   => 'pressprimer-quiz-quizzes',
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
		if ( ! isset( $_GET['quiz'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'pressprimer-quiz' ) );
		}

		$quiz_id_raw = absint( wp_unslash( $_GET['quiz'] ) );
		check_admin_referer( 'delete-quiz_' . $quiz_id_raw );

		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete quizzes.', 'pressprimer-quiz' ) );
		}

		$quiz_id = absint( wp_unslash( $_GET['quiz'] ) );
		$quiz    = PressPrimer_Quiz_Quiz::get( $quiz_id );

		if ( ! $quiz ) {
			wp_die( esc_html__( 'Quiz not found.', 'pressprimer-quiz' ) );
		}

		// Check permission - user must own quiz or have manage_all capability
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
			wp_die( esc_html__( 'You do not have permission to delete this quiz.', 'pressprimer-quiz' ) );
		}

		// Delete quiz
		$result = $quiz->delete();

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		// Clear dashboard stats cache
		if ( class_exists( 'PressPrimer_Quiz_Statistics_Service' ) ) {
			PressPrimer_Quiz_Statistics_Service::clear_dashboard_cache();
		}

		wp_safe_redirect( add_query_arg( 'deleted', '1', admin_url( 'admin.php?page=pressprimer-quiz-quizzes' ) ) );
		exit;
	}

	/**
	 * Get the current bulk action being performed
	 *
	 * Mimics WP_List_Table::current_action() logic to properly detect
	 * which bulk action was submitted via the Apply button.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The bulk action or false if none.
	 */
	private function current_bulk_action() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified in individual handlers
		$action = false;

		// Check top bulk action dropdown (action)
		if ( isset( $_GET['action'] ) && -1 !== (int) $_GET['action'] && '-1' !== $_GET['action'] ) {
			$action = sanitize_key( wp_unslash( $_GET['action'] ) );
		}

		// Check bottom bulk action dropdown (action2) - takes precedence if set
		if ( isset( $_GET['action2'] ) && -1 !== (int) $_GET['action2'] && '-1' !== $_GET['action2'] ) {
			$action = sanitize_key( wp_unslash( $_GET['action2'] ) );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return $action;
	}

	/**
	 * Handle bulk delete
	 *
	 * @since 1.0.0
	 */
	private function handle_bulk_delete() {
		check_admin_referer( 'bulk-quizzes' );

		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete quizzes.', 'pressprimer-quiz' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Validated above via check_admin_referer
		$quiz_ids = isset( $_GET['quizzes'] ) ? array_map( 'absint', wp_unslash( $_GET['quizzes'] ) ) : [];
		$deleted  = 0;

		foreach ( $quiz_ids as $quiz_id ) {
			$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );

			if ( ! $quiz ) {
				continue;
			}

			// Check permission
			if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
				continue;
			}

			$result = $quiz->delete();

			if ( ! is_wp_error( $result ) ) {
				++$deleted;
			}
		}

		// Clear dashboard stats cache if any quizzes were deleted
		if ( $deleted > 0 && class_exists( 'PressPrimer_Quiz_Statistics_Service' ) ) {
			PressPrimer_Quiz_Statistics_Service::clear_dashboard_cache();
		}

		wp_safe_redirect( add_query_arg( 'deleted', $deleted, admin_url( 'admin.php?page=pressprimer-quiz-quizzes' ) ) );
		exit;
	}

	/**
	 * Handle quiz duplicate
	 *
	 * @since 1.0.0
	 */
	private function handle_duplicate() {
		if ( ! isset( $_GET['quiz'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'pressprimer-quiz' ) );
		}

		$quiz_id_raw = absint( wp_unslash( $_GET['quiz'] ) );
		check_admin_referer( 'duplicate-quiz_' . $quiz_id_raw );

		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate quizzes.', 'pressprimer-quiz' ) );
		}

		$quiz_id = absint( wp_unslash( $_GET['quiz'] ) );
		$quiz    = PressPrimer_Quiz_Quiz::get( $quiz_id );

		if ( ! $quiz ) {
			wp_die( esc_html__( 'Quiz not found.', 'pressprimer-quiz' ) );
		}

		// Check permission
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
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
				admin_url( 'admin.php?page=pressprimer-quiz-quizzes' )
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

		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to publish quizzes.', 'pressprimer-quiz' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Validated above via check_admin_referer
		$quiz_ids  = isset( $_GET['quizzes'] ) ? array_map( 'absint', wp_unslash( $_GET['quizzes'] ) ) : [];
		$published = 0;

		foreach ( $quiz_ids as $quiz_id ) {
			$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );

			if ( ! $quiz ) {
				continue;
			}

			// Check permission
			if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
				continue;
			}

			// Update status to published
			$quiz->status = 'published';
			$result       = $quiz->save();

			if ( ! is_wp_error( $result ) ) {
				++$published;
			}
		}

		wp_safe_redirect( add_query_arg( 'published', $published, admin_url( 'admin.php?page=pressprimer-quiz-quizzes' ) ) );
		exit;
	}

	/**
	 * Handle bulk draft
	 *
	 * @since 1.0.0
	 */
	private function handle_bulk_draft() {
		check_admin_referer( 'bulk-quizzes' );

		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to change quiz status.', 'pressprimer-quiz' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Validated above via check_admin_referer
		$quiz_ids = isset( $_GET['quizzes'] ) ? array_map( 'absint', wp_unslash( $_GET['quizzes'] ) ) : [];
		$drafted  = 0;

		foreach ( $quiz_ids as $quiz_id ) {
			$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );

			if ( ! $quiz ) {
				continue;
			}

			// Check permission
			if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
				continue;
			}

			// Update status to draft
			$quiz->status = 'draft';
			$result       = $quiz->save();

			if ( ! is_wp_error( $result ) ) {
				++$drafted;
			}
		}

		wp_safe_redirect( add_query_arg( 'drafted', $drafted, admin_url( 'admin.php?page=pressprimer-quiz-quizzes' ) ) );
		exit;
	}

	/**
	 * AJAX handler: Get available questions
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_available_questions() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pressprimer_quiz_get_questions' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_send_json_error( __( 'You do not have permission to view questions.', 'pressprimer-quiz' ) );
		}

		$quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;

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

		$query .= ' ORDER BY q.id DESC LIMIT 100';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- AJAX search results, not suitable for caching
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
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pressprimer_quiz_add_questions' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_send_json_error( __( 'You do not have permission to edit quizzes.', 'pressprimer-quiz' ) );
		}

		$quiz_id = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values sanitized with absint via array_map
		$question_ids = isset( $_POST['question_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['question_ids'] ) ) : [];

		if ( ! $quiz_id || empty( $question_ids ) ) {
			wp_send_json_error( __( 'Invalid data provided.', 'pressprimer-quiz' ) );
		}

		// Verify quiz exists and user has permission
		$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
		if ( ! $quiz ) {
			wp_send_json_error( __( 'Quiz not found.', 'pressprimer-quiz' ) );
		}

		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
			wp_send_json_error( __( 'You do not have permission to edit this quiz.', 'pressprimer-quiz' ) );
		}

		// Add each question
		$added = 0;
		foreach ( $question_ids as $question_id ) {
			$result = PressPrimer_Quiz_Quiz_Item::create(
				[
					'quiz_id'     => $quiz_id,
					'question_id' => $question_id,
					'weight'      => 1.00,
				]
			);

			if ( ! is_wp_error( $result ) ) {
				++$added;
			}
		}

		wp_send_json_success(
			[
				'added'   => $added,
				'message' => sprintf(
					/* translators: %d: number of questions added */
					_n( '%d question added.', '%d questions added.', $added, 'pressprimer-quiz' ),
					$added
				),
			]
		);
	}

	/**
	 * AJAX handler: Remove question from quiz
	 *
	 * @since 1.0.0
	 */
	public function ajax_remove_quiz_question() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pressprimer_quiz_remove_question' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'pressprimer-quiz' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_send_json_error( __( 'You do not have permission to edit quizzes.', 'pressprimer-quiz' ) );
		}

		$item_id = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;

		if ( ! $item_id ) {
			wp_send_json_error( __( 'Invalid item ID.', 'pressprimer-quiz' ) );
		}

		// Get item
		$item = PressPrimer_Quiz_Quiz_Item::get( $item_id );
		if ( ! $item ) {
			wp_send_json_error( __( 'Quiz item not found.', 'pressprimer-quiz' ) );
		}

		// Verify quiz exists and user has permission
		$quiz = PressPrimer_Quiz_Quiz::get( $item->quiz_id );
		if ( ! $quiz ) {
			wp_send_json_error( __( 'Quiz not found.', 'pressprimer-quiz' ) );
		}

		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) && absint( $quiz->owner_id ) !== get_current_user_id() ) {
			wp_send_json_error( __( 'You do not have permission to edit this quiz.', 'pressprimer-quiz' ) );
		}

		// Delete item
		$result = $item->delete();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			[
				'message' => __( 'Question removed from quiz.', 'pressprimer-quiz' ),
			]
		);
	}
}

/**
 * Quizzes list table class
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Quizzes_List_Table extends WP_List_Table {

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'quiz',
				'plural'   => 'quizzes',
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
			'cb'        => '<input type="checkbox" />',
			'id'        => __( 'ID', 'pressprimer-quiz' ),
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

		$per_page     = $this->get_items_per_page( 'pressprimer_quiz_quizzes_per_page', 20 );
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// Build query
		$quizzes_table = $wpdb->prefix . 'ppq_quizzes';
		$where_clauses = [];
		$where_values  = [];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters for list table display
		// Filter by search
		if ( isset( $_GET['s'] ) && '' !== $_GET['s'] ) {
			$where_clauses[] = 'title LIKE %s';
			$where_values[]  = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) . '%';
		}

		// Filter by status
		$get_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( '' !== $get_status && 'all' !== $get_status ) {
			$where_clauses[] = 'status = %s';
			$where_values[]  = $get_status;
		}

		// Filter by mode
		$get_mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : '';
		if ( '' !== $get_mode && 'all' !== $get_mode ) {
			$where_clauses[] = 'mode = %s';
			$where_values[]  = $get_mode;
		}

		// Filter by author (if not manage_all, only show own quizzes)
		$get_author = isset( $_GET['author'] ) ? sanitize_text_field( wp_unslash( $_GET['author'] ) ) : '';
		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			$where_clauses[] = 'owner_id = %d';
			$where_values[]  = get_current_user_id();
		} elseif ( '' !== $get_author && 'all' !== $get_author ) {
			$where_clauses[] = 'owner_id = %d';
			$where_values[]  = absint( $get_author );
		}

		// Build WHERE clause
		$where_sql = ! empty( $where_clauses )
			? 'WHERE ' . implode( ' AND ', $where_clauses )
			: '';

		// Get orderby and order - validate against allowed fields
		$allowed_orderby = [ 'id', 'title', 'status', 'mode', 'owner_id', 'created_at', 'updated_at' ];
		$orderby         = isset( $_GET['orderby'] ) && '' !== $_GET['orderby'] ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$orderby         = in_array( $orderby, $allowed_orderby, true ) ? $orderby : 'created_at';
		$order           = isset( $_GET['order'] ) && '' !== $_GET['order'] ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Validate order
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			$order = 'DESC';
		}

		// Build ORDER BY with sanitize_sql_orderby
		$order_sql = sanitize_sql_orderby( "{$orderby} {$order}" );
		$order_sql = $order_sql ? "ORDER BY {$order_sql}" : 'ORDER BY created_at DESC';

		// Get total count
		$total_query = "SELECT COUNT(*) FROM {$quizzes_table} {$where_sql}";

		if ( ! empty( $where_values ) ) {
			$total_query = $wpdb->prepare( $total_query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- List table pagination, not suitable for caching
		$total_items = $wpdb->get_var( $total_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Get items
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name and validated clauses safely constructed
		$items_query  = "SELECT * FROM {$quizzes_table} {$where_sql} {$order_sql} LIMIT %d OFFSET %d";
		$query_values = array_merge( $where_values, [ $per_page, $offset ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- List table pagination, not suitable for caching
		$items = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with placeholders
			$wpdb->prepare( $items_query, $query_values )
		);

		// Convert to model instances
		$this->items = [];
		if ( $items ) {
			foreach ( $items as $item ) {
				$this->items[] = PressPrimer_Quiz_Quiz::from_row( $item );
			}
		}

		// Set pagination
		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			]
		);

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
	 * @param PressPrimer_Quiz_Quiz $item Quiz object.
	 * @return string Column content.
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="quizzes[]" value="%d" />', $item->id );
	}

	/**
	 * Render ID column
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Quiz $item Quiz object.
	 * @return string Column content.
	 */
	public function column_id( $item ) {
		return sprintf( '<strong>%d</strong>', $item->id );
	}

	/**
	 * Render title column
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Quiz $item Quiz object.
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
						'page'   => 'pressprimer-quiz-quizzes',
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
							'page'   => 'pressprimer-quiz-quizzes',
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

		// Delete action
		$actions['delete'] = sprintf(
			'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\')">%s</a>',
			esc_url(
				wp_nonce_url(
					add_query_arg(
						[
							'page'   => 'pressprimer-quiz-quizzes',
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

		// Preview action
		$actions['preview'] = sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url(
				add_query_arg(
					[
						'page'   => 'pressprimer-quiz-quizzes',
						'action' => 'preview',
						'quiz'   => $item->id,
					],
					admin_url( 'admin.php' )
				)
			),
			esc_html__( 'Preview', 'pressprimer-quiz' )
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
	 * @param PressPrimer_Quiz_Quiz $item Quiz object.
	 * @return string Column content.
	 */
	public function column_questions( $item ) {
		if ( 'fixed' === $item->generation_mode ) {
			// Count items
			$items = $item->get_items();
			$count = count( $items );

			/* translators: %s: "question" or "questions" depending on count */
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

			/* translators: %s: "question" or "questions" depending on count */
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
	 * @param PressPrimer_Quiz_Quiz $item Quiz object.
	 * @return string Column content.
	 */
	public function column_mode( $item ) {
		$modes = [
			'tutorial' => __( 'Tutorial', 'pressprimer-quiz' ),
			'timed'    => __( 'Test', 'pressprimer-quiz' ),
		];

		return isset( $modes[ $item->mode ] ) ? esc_html( $modes[ $item->mode ] ) : '—';
	}

	/**
	 * Render status column
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Quiz $item Quiz object.
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
	 * @param PressPrimer_Quiz_Quiz $item Quiz object.
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
						'page'   => 'pressprimer-quiz-quizzes',
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
	 * @param PressPrimer_Quiz_Quiz $item Quiz object.
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
				esc_attr( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ),
				/* translators: %s: human-readable time difference */
				sprintf( esc_html__( '%s ago', 'pressprimer-quiz' ), human_time_diff( $timestamp ) )
			);
		}

		return wp_date( get_option( 'date_format' ), $timestamp );
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
			if ( current_user_can( 'pressprimer_quiz_manage_all' ) ) {
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state for selected()
		$current_status = isset( $_GET['status'] ) && '' !== $_GET['status'] ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';

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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state for selected()
		$current_mode = isset( $_GET['mode'] ) && '' !== $_GET['mode'] ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'all';

		$modes = [
			'all'      => __( 'All Modes', 'pressprimer-quiz' ),
			'tutorial' => __( 'Tutorial', 'pressprimer-quiz' ),
			'timed'    => __( 'Test', 'pressprimer-quiz' ),
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter state for selected()
		$current_author = isset( $_GET['author'] ) && '' !== $_GET['author'] ? absint( wp_unslash( $_GET['author'] ) ) : 0;

		// Get all users who have created quizzes
		global $wpdb;
		$quizzes_table = $wpdb->prefix . 'ppq_quizzes';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin filter dropdown, not suitable for caching
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
