<?php
/**
 * Quiz Renderer
 *
 * Handles rendering of quiz landing pages and quiz interfaces.
 *
 * @package PressPrimer_Quiz
 * @subpackage Frontend
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quiz Renderer class
 *
 * Renders quiz landing pages, quiz interfaces, and manages
 * the quiz-taking experience.
 *
 * @since 1.0.0
 */
class PPQ_Quiz_Renderer {

	/**
	 * Render quiz landing page
	 *
	 * Displays quiz information, previous attempts, and start/resume buttons.
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz $quiz Quiz object.
	 * @return string Rendered HTML.
	 */
	public function render_landing( $quiz ) {
		// Enqueue assets
		$this->enqueue_assets( $quiz );

		$user_id = get_current_user_id();
		$is_logged_in = $user_id > 0;

		// Get user's attempts
		$previous_attempts = [];
		$in_progress_attempt = null;

		if ( $is_logged_in ) {
			$previous_attempts = PPQ_Attempt::get_user_attempts( $quiz->id, $user_id );
			$in_progress_attempt = PPQ_Attempt::get_user_in_progress( $quiz->id, $user_id );
		}

		// Check attempt limits
		$can_start = true;
		$limit_message = '';

		if ( $quiz->max_attempts ) {
			$submitted_count = count( array_filter( $previous_attempts, function( $a ) {
				return 'submitted' === $a->status;
			} ) );

			if ( $submitted_count >= $quiz->max_attempts ) {
				$can_start = false;
				$limit_message = sprintf(
					/* translators: %d: maximum number of attempts */
					__( 'You have reached the maximum number of attempts (%d) for this quiz.', 'pressprimer-quiz' ),
					$quiz->max_attempts
				);
			}
		}

		// Check attempt delay
		if ( $can_start && $quiz->attempt_delay_minutes && ! empty( $previous_attempts ) ) {
			$last_attempt = $previous_attempts[0];
			if ( $last_attempt && 'submitted' === $last_attempt->status && $last_attempt->finished_at ) {
				$elapsed_minutes = ( time() - strtotime( $last_attempt->finished_at ) ) / 60;
				if ( $elapsed_minutes < $quiz->attempt_delay_minutes ) {
					$can_start = false;
					$wait_minutes = ceil( $quiz->attempt_delay_minutes - $elapsed_minutes );
					$limit_message = sprintf(
						/* translators: %d: minutes to wait */
						_n(
							'Please wait %d minute before retaking this quiz.',
							'Please wait %d minutes before retaking this quiz.',
							$wait_minutes,
							'pressprimer-quiz'
						),
						$wait_minutes
					);
				}
			}
		}

		// Count questions
		$question_count = 0;
		if ( 'fixed' === $quiz->generation_mode ) {
			$items = $quiz->get_items();
			$question_count = count( $items );
		} else {
			$rules = $quiz->get_rules();
			foreach ( $rules as $rule ) {
				$question_count += $rule->question_count;
			}
		}

		// Get theme class
		$theme_class = PPQ_Theme_Loader::get_theme_class( PPQ_Theme_Loader::get_quiz_theme( $quiz ) );

		// Start output buffering
		ob_start();

		?>
		<div class="ppq-quiz-landing <?php echo esc_attr( $theme_class ); ?>" data-quiz-id="<?php echo esc_attr( $quiz->id ); ?>">

			<?php if ( $quiz->featured_image_id ) : ?>
				<div class="ppq-quiz-header-image">
					<?php echo wp_get_attachment_image( $quiz->featured_image_id, 'large', false, [ 'class' => 'ppq-featured-image' ] ); ?>
				</div>
			<?php endif; ?>

			<div class="ppq-quiz-content">

				<header class="ppq-quiz-header">
					<h1 class="ppq-quiz-title"><?php echo esc_html( $quiz->title ); ?></h1>

					<?php if ( ! empty( $quiz->description ) ) : ?>
						<div class="ppq-quiz-description">
							<?php echo wp_kses_post( wpautop( $quiz->description ) ); ?>
						</div>
					<?php endif; ?>
				</header>

				<div class="ppq-quiz-meta">
					<div class="ppq-quiz-meta-grid">

						<?php
						// Quiz mode information for tooltip
						$mode_label = '';
						$mode_tooltip = '';
						if ( 'fixed' === $quiz->generation_mode ) {
							$mode_label = __( 'Fixed Quiz', 'pressprimer-quiz' );
							$mode_tooltip = __( 'All participants will receive the same questions in the same order.', 'pressprimer-quiz' );
						} elseif ( 'random' === $quiz->generation_mode ) {
							$mode_label = __( 'Random Quiz', 'pressprimer-quiz' );
							$mode_tooltip = __( 'Questions are randomly selected from question pools each time you take the quiz.', 'pressprimer-quiz' );
						} elseif ( 'adaptive' === $quiz->generation_mode ) {
							$mode_label = __( 'Adaptive Quiz', 'pressprimer-quiz' );
							$mode_tooltip = __( 'Question difficulty adapts based on your performance.', 'pressprimer-quiz' );
						}
						?>

						<?php if ( $mode_label ) : ?>
							<div class="ppq-meta-item ppq-has-tooltip" title="<?php echo esc_attr( $mode_tooltip ); ?>">
								<span class="ppq-meta-icon" aria-hidden="true">●</span>
								<div class="ppq-meta-content">
									<span class="ppq-meta-label"><?php esc_html_e( 'Type', 'pressprimer-quiz' ); ?></span>
									<span class="ppq-meta-value"><?php echo esc_html( $mode_label ); ?></span>
								</div>
							</div>
						<?php endif; ?>

						<div class="ppq-meta-item">
							<span class="ppq-meta-icon" aria-hidden="true">#</span>
							<div class="ppq-meta-content">
								<span class="ppq-meta-label"><?php esc_html_e( 'Questions', 'pressprimer-quiz' ); ?></span>
								<span class="ppq-meta-value"><?php echo esc_html( $question_count ); ?></span>
							</div>
						</div>

						<?php if ( $quiz->time_limit_seconds ) : ?>
							<div class="ppq-meta-item">
								<span class="ppq-meta-icon" aria-hidden="true">⏰</span>
								<div class="ppq-meta-content">
									<span class="ppq-meta-label"><?php esc_html_e( 'Time Limit', 'pressprimer-quiz' ); ?></span>
									<span class="ppq-meta-value">
										<?php
										$minutes = floor( $quiz->time_limit_seconds / 60 );
										echo esc_html( sprintf( _n( '%d minute', '%d minutes', $minutes, 'pressprimer-quiz' ), $minutes ) );
										?>
									</span>
								</div>
							</div>
						<?php endif; ?>

						<div class="ppq-meta-item">
							<span class="ppq-meta-icon" aria-hidden="true">%</span>
							<div class="ppq-meta-content">
								<span class="ppq-meta-label"><?php esc_html_e( 'Passing Score', 'pressprimer-quiz' ); ?></span>
								<span class="ppq-meta-value"><?php echo esc_html( $quiz->pass_percent . '%' ); ?></span>
							</div>
						</div>

						<?php if ( $quiz->max_attempts ) : ?>
							<div class="ppq-meta-item">
								<span class="ppq-meta-icon" aria-hidden="true">✓</span>
								<div class="ppq-meta-content">
									<span class="ppq-meta-label"><?php esc_html_e( 'Attempts', 'pressprimer-quiz' ); ?></span>
									<span class="ppq-meta-value">
										<?php
										$submitted_count = count( array_filter( $previous_attempts, function( $a ) {
											return 'submitted' === $a->status;
										} ) );
										printf(
											/* translators: 1: attempts used, 2: max attempts */
											esc_html__( '%1$d of %2$d', 'pressprimer-quiz' ),
											$submitted_count,
											$quiz->max_attempts
										);
										?>
									</span>
								</div>
							</div>
						<?php endif; ?>

					</div>
				</div>

				<?php
				// Count submitted attempts for display
				$submitted_attempts = array_filter( $previous_attempts, function( $a ) {
					return 'submitted' === $a->status;
				} );
				?>

				<?php if ( ! empty( $submitted_attempts ) && $is_logged_in ) : ?>
					<div class="ppq-previous-attempts">
						<h2 class="ppq-section-title"><?php esc_html_e( 'Your Previous Attempts', 'pressprimer-quiz' ); ?></h2>
						<div class="ppq-attempts-list">
							<?php
							$shown = 0;
							foreach ( $submitted_attempts as $attempt ) {
								if ( $shown >= 3 ) {
									break; // Show max 3 previous attempts
								}
								$shown++;
								?>
								<div class="ppq-attempt-card">
									<div class="ppq-attempt-info">
										<span class="ppq-attempt-date">
											<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $attempt->started_at ) ) ); ?>
										</span>
										<?php if ( null !== $attempt->score_percent ) : ?>
											<span class="ppq-attempt-score <?php echo $attempt->passed ? 'ppq-passed' : 'ppq-failed'; ?>">
												<?php echo esc_html( number_format( $attempt->score_percent, 1 ) . '%' ); ?>
												<?php if ( $attempt->passed ) : ?>
													<span class="ppq-pass-badge" aria-label="<?php esc_attr_e( 'Passed', 'pressprimer-quiz' ); ?>">✓</span>
												<?php endif; ?>
											</span>
										<?php endif; ?>
									</div>
								</div>
								<?php
							}
							?>
						</div>
					</div>
				<?php endif; ?>

				<div class="ppq-quiz-actions">

					<?php if ( ! $can_start ) : ?>
						<div class="ppq-notice ppq-notice-warning">
							<p><?php echo esc_html( $limit_message ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( $in_progress_attempt && $in_progress_attempt->can_resume() ) : ?>
						<div class="ppq-notice ppq-notice-info ppq-resume-notice">
							<p>
								<?php esc_html_e( 'You have an in-progress attempt. You can resume where you left off.', 'pressprimer-quiz' ); ?>
							</p>
						</div>
						<a href="<?php echo esc_url( add_query_arg( 'attempt', $in_progress_attempt->id, get_permalink() ) ); ?>"
						   class="ppq-button ppq-button-primary ppq-button-large ppq-resume-button">
							<span class="ppq-button-icon" aria-hidden="true">▶️</span>
							<?php esc_html_e( 'Resume Quiz', 'pressprimer-quiz' ); ?>
						</a>
					<?php elseif ( $can_start ) : ?>

						<?php if ( ! $is_logged_in ) : ?>
							<div class="ppq-guest-email-form">
								<label for="ppq-guest-email" class="ppq-form-label">
									<?php esc_html_e( 'Email Address (Optional)', 'pressprimer-quiz' ); ?>
								</label>
								<p class="ppq-form-help">
									<?php esc_html_e( 'Enter your email to save your progress and receive your results.', 'pressprimer-quiz' ); ?>
								</p>
								<input type="email"
								       id="ppq-guest-email"
								       class="ppq-input ppq-email-input"
								       placeholder="<?php esc_attr_e( 'your@email.com', 'pressprimer-quiz' ); ?>"
								       autocomplete="email">
							</div>
						<?php endif; ?>

						<button type="button"
						        class="ppq-button ppq-button-primary ppq-button-large ppq-start-quiz-button"
						        data-quiz-id="<?php echo esc_attr( $quiz->id ); ?>">
							<span class="ppq-button-icon" aria-hidden="true">🚀</span>
							<?php esc_html_e( 'Start Quiz', 'pressprimer-quiz' ); ?>
						</button>

					<?php endif; ?>

				</div>

			</div>

		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render quiz interface (in progress)
	 *
	 * Displays the quiz-taking interface with current question.
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Attempt $attempt Attempt object.
	 * @return string Rendered HTML.
	 */
	public function render_quiz( $attempt ) {
		// Get quiz first for asset loading
		$quiz = $attempt->get_quiz();

		// Enqueue assets
		$this->enqueue_assets( $quiz );

		// Check if attempt is valid
		if ( 'in_progress' !== $attempt->status ) {
			return '<div class="ppq-error ppq-notice ppq-notice-error"><p>' .
				esc_html__( 'This quiz attempt is not in progress.', 'pressprimer-quiz' ) .
				'</p></div>';
		}

		// Check if timed out - auto-submit and show helpful options
		if ( $attempt->is_timed_out() ) {
			// Submit the attempt
			$submit_result = $attempt->submit();

			// Get quiz
			$timed_out_quiz = $attempt->get_quiz();

			// Build helpful message with options
			ob_start();
			?>
			<div class="ppq-quiz-content">
				<div class="ppq-notice ppq-notice-warning" style="margin-bottom: 20px;">
					<p><strong><?php esc_html_e( 'Time Expired', 'pressprimer-quiz' ); ?></strong></p>
					<p><?php esc_html_e( 'The time limit for this quiz has expired. Your quiz has been automatically submitted with your current answers.', 'pressprimer-quiz' ); ?></p>
				</div>

				<?php if ( ! is_wp_error( $submit_result ) ) : ?>
					<div class="ppq-quiz-actions">
						<p><?php esc_html_e( 'Your attempt has been saved. What would you like to do next?', 'pressprimer-quiz' ); ?></p>
						<div class="ppq-button-group">
							<a href="<?php echo esc_url( get_permalink( $timed_out_quiz->id ) ); ?>" class="ppq-button ppq-button-primary">
								<?php esc_html_e( 'Return to Quiz Page', 'pressprimer-quiz' ); ?>
							</a>
						</div>
					</div>
				<?php else : ?>
					<div class="ppq-notice ppq-notice-error">
						<p><?php echo esc_html( $submit_result->get_error_message() ); ?></p>
					</div>
				<?php endif; ?>
			</div>
			<?php
			return ob_get_clean();
		}

		// Verify quiz was loaded (already loaded earlier for asset enqueuing)
		if ( ! $quiz ) {
			return '<div class="ppq-error ppq-notice ppq-notice-error"><p>' .
				esc_html__( 'Quiz not found.', 'pressprimer-quiz' ) .
				'</p></div>';
		}

		// Get all attempt items
		$items = $attempt->get_items();
		if ( empty( $items ) ) {
			return '<div class="ppq-error ppq-notice ppq-notice-error"><p>' .
				esc_html__( 'No questions found for this attempt.', 'pressprimer-quiz' ) .
				'</p></div>';
		}

		// Calculate time remaining (if timed)
		$time_remaining = null;
		$time_limit = null;
		if ( $quiz->time_limit_seconds ) {
			$time_limit = $quiz->time_limit_seconds;
			$elapsed = time() - strtotime( $attempt->started_at );
			$time_remaining = max( 0, $time_limit - $elapsed );
		}

		// Get theme class
		$theme_class = PPQ_Theme_Loader::get_theme_class( PPQ_Theme_Loader::get_quiz_theme( $quiz ) );

		// Find first unanswered question (for resume functionality)
		$first_unanswered = 0;
		foreach ( $items as $index => $item ) {
			$selected = $item->get_selected_answers();
			if ( empty( $selected ) ) {
				$first_unanswered = $index;
				break;
			}
			// If we've checked all questions and they're all answered, stay on last question
			if ( $index === count( $items ) - 1 ) {
				$first_unanswered = $index;
			}
		}

		// Start output buffering
		ob_start();

		?>
		<div class="ppq-quiz-interface <?php echo esc_attr( $theme_class ); ?>"
			 data-attempt-id="<?php echo esc_attr( $attempt->id ); ?>"
			 data-quiz-id="<?php echo esc_attr( $quiz->id ); ?>"
			 data-time-limit="<?php echo esc_attr( $time_limit ); ?>"
			 data-time-remaining="<?php echo esc_attr( $time_remaining ); ?>"
			 data-start-question="<?php echo esc_attr( $first_unanswered ); ?>">

			<!-- Quiz Header -->
			<div class="ppq-quiz-interface-header">
				<div class="ppq-quiz-interface-header-content">
					<h1 class="ppq-quiz-interface-title"><?php echo esc_html( $quiz->title ); ?></h1>

					<div class="ppq-quiz-interface-meta">
						<span class="ppq-quiz-interface-meta-item">
							<span class="ppq-meta-icon" aria-hidden="true">📝</span>
							<span class="ppq-progress-text">
								<span class="ppq-current-question">1</span> / <?php echo esc_html( count( $items ) ); ?>
							</span>
						</span>

						<?php if ( $time_limit ) : ?>
							<span class="ppq-quiz-interface-meta-item ppq-timer-container">
								<span class="ppq-meta-icon" aria-hidden="true">⏱️</span>
								<span class="ppq-timer" id="ppq-timer" data-remaining="<?php echo esc_attr( $time_remaining ); ?>">
									<?php echo esc_html( $this->format_time( $time_remaining ) ); ?>
								</span>
							</span>
						<?php endif; ?>
					</div>
				</div>

				<!-- Progress Bar -->
				<div class="ppq-progress-bar-container">
					<div class="ppq-progress-bar" style="width: 0%;" id="ppq-progress-bar"></div>
				</div>
			</div>

			<!-- Questions Container -->
			<div class="ppq-questions-container">
				<?php foreach ( $items as $index => $item ) : ?>
					<?php echo $this->render_question( $item, $index, count( $items ) ); ?>
				<?php endforeach; ?>
			</div>

			<!-- Navigation and Submit -->
			<div class="ppq-quiz-navigation">
				<button type="button"
						class="ppq-button ppq-button-secondary ppq-nav-button ppq-prev-button"
						id="ppq-prev-button"
						disabled>
					<span class="ppq-button-icon" aria-hidden="true">←</span>
					<?php esc_html_e( 'Previous', 'pressprimer-quiz' ); ?>
				</button>

				<div class="ppq-nav-center">
					<button type="button"
							class="ppq-button ppq-button-primary ppq-submit-quiz-button"
							id="ppq-submit-button"
							style="display: none;">
						<?php esc_html_e( 'Submit Quiz', 'pressprimer-quiz' ); ?>
					</button>
				</div>

				<button type="button"
						class="ppq-button ppq-button-secondary ppq-nav-button ppq-next-button"
						id="ppq-next-button">
					<?php esc_html_e( 'Next', 'pressprimer-quiz' ); ?>
					<span class="ppq-button-icon" aria-hidden="true">→</span>
				</button>
			</div>

			<!-- Auto-save indicator -->
			<div class="ppq-autosave-indicator" id="ppq-autosave-indicator" style="display: none;">
				<span class="ppq-autosave-icon">💾</span>
				<span class="ppq-autosave-text"><?php esc_html_e( 'Saved', 'pressprimer-quiz' ); ?></span>
			</div>

		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render a single question
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Attempt_Item $item Attempt item object.
	 * @param int              $index Question index (0-based).
	 * @param int              $total Total number of questions.
	 * @return string Rendered HTML.
	 */
	private function render_question( $item, $index, $total ) {
		$question = $item->get_question();
		$revision = $item->get_question_revision();

		if ( ! $question || ! $revision ) {
			return '';
		}

		$selected_answers = $item->get_selected_answers();
		$answers = $revision->get_answers();
		$question_number = $index + 1;

		// Determine input type based on question type
		$input_type = 'radio';
		$input_name = 'ppq_answer_' . $item->id;

		if ( 'multiple_answer' === $question->type || 'ma' === $question->type ) {
			$input_type = 'checkbox';
			$input_name .= '[]';
		}

		ob_start();
		?>
		<div class="ppq-question"
			 data-question-index="<?php echo esc_attr( $index ); ?>"
			 data-item-id="<?php echo esc_attr( $item->id ); ?>"
			 data-question-type="<?php echo esc_attr( $question->type ); ?>"
			 style="<?php echo 0 === $index ? '' : 'display: none;'; ?>">

			<div class="ppq-question-header">
				<span class="ppq-question-number">
					<?php
					printf(
						/* translators: 1: current question number, 2: total questions */
						esc_html__( 'Question %1$d of %2$d', 'pressprimer-quiz' ),
						$question_number,
						$total
					);
					?>
				</span>
				<?php if ( $question->max_points > 1 ) : ?>
					<span class="ppq-question-points">
						<?php
						printf(
							/* translators: %s: points value */
							esc_html__( '%s points', 'pressprimer-quiz' ),
							number_format( $question->max_points, 0 )
						);
						?>
					</span>
				<?php endif; ?>
			</div>

			<div class="ppq-question-text">
				<?php echo wp_kses_post( wpautop( $revision->stem ) ); ?>
			</div>

			<div class="ppq-answers">
				<?php foreach ( $answers as $answer_index => $answer ) : ?>
					<?php
					$answer_id = 'ppq_answer_' . $item->id . '_' . $answer_index;
					$is_checked = in_array( $answer_index, $selected_answers, true );
					?>
					<label class="ppq-answer-option <?php echo $is_checked ? 'ppq-selected' : ''; ?>"
						   for="<?php echo esc_attr( $answer_id ); ?>">
						<input type="<?php echo esc_attr( $input_type ); ?>"
							   id="<?php echo esc_attr( $answer_id ); ?>"
							   name="<?php echo esc_attr( $input_name ); ?>"
							   value="<?php echo esc_attr( $answer_index ); ?>"
							   class="ppq-answer-input"
							   data-item-id="<?php echo esc_attr( $item->id ); ?>"
							   <?php checked( $is_checked ); ?>>
						<span class="ppq-answer-radio-check"></span>
						<span class="ppq-answer-text">
							<?php echo wp_kses_post( $answer['text'] ); ?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>

			<?php if ( 'multiple_answer' === $question->type || 'ma' === $question->type ) : ?>
				<p class="ppq-question-hint">
					<?php esc_html_e( 'Select all that apply', 'pressprimer-quiz' ); ?>
				</p>
			<?php endif; ?>

		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Format time in MM:SS format
	 *
	 * @since 1.0.0
	 *
	 * @param int $seconds Seconds to format.
	 * @return string Formatted time.
	 */
	private function format_time( $seconds ) {
		$minutes = floor( $seconds / 60 );
		$seconds = $seconds % 60;
		return sprintf( '%02d:%02d', $minutes, $seconds );
	}

	/**
	 * Enqueue frontend assets
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz|null $quiz Quiz object for theme loading.
	 */
	private function enqueue_assets( $quiz = null ) {
		// Enqueue quiz CSS
		wp_enqueue_style(
			'ppq-quiz',
			PPQ_PLUGIN_URL . 'assets/css/quiz.css',
			[],
			PPQ_VERSION
		);

		// Enqueue theme CSS
		if ( $quiz ) {
			PPQ_Theme_Loader::enqueue_quiz_theme( $quiz );
			PPQ_Theme_Loader::output_custom_css( $quiz );
		} else {
			// Fallback to default theme if no quiz provided
			PPQ_Theme_Loader::enqueue_theme( 'default' );
		}

		// Enqueue quiz JavaScript
		wp_enqueue_script(
			'ppq-quiz',
			PPQ_PLUGIN_URL . 'assets/js/quiz.js',
			[ 'jquery' ],
			PPQ_VERSION,
			true
		);

		// Localize script with data
		wp_localize_script(
			'ppq-quiz',
			'ppqQuiz',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ppq_quiz_nonce' ),
				'strings' => [
					'startingQuiz'       => __( 'Starting quiz...', 'pressprimer-quiz' ),
					'error'              => __( 'An error occurred. Please try again.', 'pressprimer-quiz' ),
					'emailRequired'      => __( 'Please enter a valid email address.', 'pressprimer-quiz' ),
					'submittingQuiz'     => __( 'Submitting quiz...', 'pressprimer-quiz' ),
					'confirmSubmit'      => __( 'Are you sure you want to submit your quiz? You cannot change your answers after submitting.', 'pressprimer-quiz' ),
					'timeExpired'        => __( 'Time has expired. Your quiz is being submitted automatically.', 'pressprimer-quiz' ),
					'saved'              => __( 'Saved', 'pressprimer-quiz' ),
					'saving'             => __( 'Saving...', 'pressprimer-quiz' ),
					'saveFailed'         => __( 'Save failed', 'pressprimer-quiz' ),
					'unansweredTitle'    => __( 'Unanswered Questions', 'pressprimer-quiz' ),
					'unansweredSingle'   => __( 'Question {question} has not been answered.', 'pressprimer-quiz' ),
					'unansweredMultiple' => __( 'Questions {questions} have not been answered.', 'pressprimer-quiz' ),
					'unansweredMany'     => __( 'You have {count} unanswered questions.', 'pressprimer-quiz' ),
					'goToQuestion'       => __( 'Go to Question {question}', 'pressprimer-quiz' ),
					'submitAnyway'       => __( 'Submit Anyway', 'pressprimer-quiz' ),
				],
			]
		);
	}
}
