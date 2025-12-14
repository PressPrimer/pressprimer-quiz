<?php
/**
 * Results Renderer
 *
 * Renders quiz results and review pages.
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
 * Results Renderer class
 *
 * Handles rendering of quiz results including scores, feedback,
 * category breakdowns, and question review.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Results_Renderer {

	/**
	 * Render results page
	 *
	 * Displays comprehensive results after quiz submission.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @return string HTML output.
	 */
	public function render_results( $attempt ) {
		if ( ! $attempt || 'submitted' !== $attempt->status ) {
			return '<div class="ppq-error">' . esc_html__( 'Results not available.', 'pressprimer-quiz' ) . '</div>';
		}

		// Get quiz
		$quiz = $attempt->get_quiz();
		if ( ! $quiz ) {
			return '<div class="ppq-error">' . esc_html__( 'Quiz not found.', 'pressprimer-quiz' ) . '</div>';
		}

		// Calculate comprehensive results
		$results = $this->calculate_results( $attempt );

		/**
		 * Filter the calculated results data before rendering.
		 *
		 * Allows modification of score data, category breakdowns, and confidence stats
		 * before they are displayed to the user.
		 *
		 * @since 1.0.0
		 *
		 * @param array       $results Calculated results including score_percent, category_scores, confidence_stats.
		 * @param PressPrimer_Quiz_Attempt $attempt The attempt object.
		 * @param PressPrimer_Quiz_Quiz    $quiz    The quiz object.
		 */
		$results = apply_filters( 'pressprimer_quiz_results_data', $results, $attempt, $quiz );

		// Get theme class
		$theme_class = PressPrimer_Quiz_Theme_Loader::get_theme_class( PressPrimer_Quiz_Theme_Loader::get_quiz_theme( $quiz ) );

		/**
		 * Filter which sections are displayed on the results page.
		 *
		 * Return an array of section IDs to display. Remove items to hide sections.
		 * Default sections: header, guest_notice, score_summary, email_notice,
		 * category_breakdown, confidence, feedback, actions.
		 *
		 * @since 1.0.0
		 *
		 * @param array       $sections Array of section IDs to display.
		 * @param PressPrimer_Quiz_Attempt $attempt  The attempt object.
		 * @param PressPrimer_Quiz_Quiz    $quiz     The quiz object.
		 */
		$sections = apply_filters(
			'pressprimer_quiz_results_sections',
			[
				'header',
				'guest_notice',
				'score_summary',
				'email_notice',
				'category_breakdown',
				'confidence',
				'feedback',
				'actions',
			],
			$attempt,
			$quiz
		);

		// Build output
		ob_start();
		?>
		<div class="ppq-results-container <?php echo esc_attr( $theme_class ); ?>">
			<?php
			/**
			 * Fires before the results content is rendered.
			 *
			 * @since 1.0.0
			 *
			 * @param PressPrimer_Quiz_Attempt $attempt The attempt object.
			 * @param PressPrimer_Quiz_Quiz    $quiz    The quiz object.
			 * @param array       $results The calculated results data.
			 */
			do_action( 'pressprimer_quiz_before_results', $attempt, $quiz, $results );

			if ( in_array( 'header', $sections, true ) ) {
				$this->render_results_header( $attempt, $quiz, $results );
			}
			if ( in_array( 'guest_notice', $sections, true ) ) {
				$this->render_guest_token_notice( $attempt );
			}
			if ( in_array( 'score_summary', $sections, true ) ) {
				$this->render_score_summary( $attempt, $quiz, $results );
			}
			if ( in_array( 'email_notice', $sections, true ) ) {
				$this->render_email_sent_notice( $attempt );
			}
			if ( in_array( 'category_breakdown', $sections, true ) ) {
				$this->render_category_breakdown( $results, $attempt, $quiz );
			}
			if ( in_array( 'confidence', $sections, true ) ) {
				$this->render_confidence_calibration( $results );
			}
			if ( in_array( 'feedback', $sections, true ) ) {
				$this->render_score_feedback( $quiz, $results );
			}
			if ( in_array( 'actions', $sections, true ) ) {
				$this->render_results_actions( $attempt, $quiz );
			}

			/**
			 * Fires after the results content is rendered.
			 *
			 * Use this to add custom sections to the results page.
			 *
			 * @since 1.0.0
			 *
			 * @param PressPrimer_Quiz_Attempt $attempt The attempt object.
			 * @param PressPrimer_Quiz_Quiz    $quiz    The quiz object.
			 * @param array       $results The calculated results data.
			 */
			do_action( 'pressprimer_quiz_after_results', $attempt, $quiz, $results );
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render results header
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @param PressPrimer_Quiz_Quiz    $quiz Quiz object.
	 * @param array                    $results Results data.
	 */
	private function render_results_header( $attempt, $quiz, $results ) {
		?>
		<div class="ppq-results-header">
			<h2 class="ppq-results-title"><?php esc_html_e( 'Quiz Complete!', 'pressprimer-quiz' ); ?></h2>
			<p class="ppq-quiz-title"><?php echo esc_html( $quiz->title ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render guest token expiry notice
	 *
	 * Shows a notice to guest users about token expiration.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 */
	private function render_guest_token_notice( $attempt ) {
		// Only show for guest attempts
		if ( $attempt->user_id || ! $attempt->guest_token ) {
			return;
		}

		// Don't show if already expired (they're viewing it now, so it's still valid)
		if ( $attempt->is_token_expired() ) {
			return;
		}

		// Calculate days until expiration
		if ( $attempt->token_expires_at ) {
			$now            = current_time( 'timestamp' );
			$expires        = strtotime( $attempt->token_expires_at );
			$days_remaining = max( 0, ceil( ( $expires - $now ) / DAY_IN_SECONDS ) );

			?>
			<div class="ppq-guest-notice">
				<p>
					<strong><?php esc_html_e( 'Save this link:', 'pressprimer-quiz' ); ?></strong>
					<?php
					printf(
						esc_html(
							/* translators: %d: number of days */
							_n(
								'You can access your results for %d more day using this unique link.',
								'You can access your results for %d more days using this unique link.',
								$days_remaining,
								'pressprimer-quiz'
							)
						),
						(int) $days_remaining
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Render score summary
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @param PressPrimer_Quiz_Quiz    $quiz Quiz object.
	 * @param array                    $results Results data.
	 */
	private function render_score_summary( $attempt, $quiz, $results ) {
		$passed_class = $attempt->passed ? 'ppq-passed' : 'ppq-failed';
		/* translators: All caps pass/fail status shown on results page */
		$passed_text = $attempt->passed
			? __( 'PASSED', 'pressprimer-quiz' )
			: __( 'FAILED', 'pressprimer-quiz' );

		?>
		<div class="ppq-score-summary <?php echo esc_attr( $passed_class ); ?>">
			<div class="ppq-score-display">
				<div class="ppq-score-percentage">
					<?php echo esc_html( round( (float) $attempt->score_percent, 1 ) ); ?>%
				</div>
				<div class="ppq-score-details">
					<?php
					printf(
						/* translators: 1: correct count, 2: total count */
						esc_html__( '%1$d / %2$d correct', 'pressprimer-quiz' ),
						(int) $results['correct_count'],
						(int) $results['total_count']
					);
					?>
				</div>
			</div>

			<div class="ppq-pass-status <?php echo esc_attr( $passed_class ); ?>">
				<span class="ppq-pass-label"><?php echo esc_html( $passed_text ); ?></span>
				<span class="ppq-pass-threshold">
					<?php
					printf(
						/* translators: %s: passing percentage */
						esc_html__( '(%s%% required)', 'pressprimer-quiz' ),
						esc_html( round( $quiz->pass_percent, 1 ) )
					);
					?>
				</span>
			</div>

			<div class="ppq-results-meta">
				<div class="ppq-meta-item">
					<span class="ppq-meta-icon">⏱️</span>
					<span class="ppq-meta-label"><?php esc_html_e( 'Time:', 'pressprimer-quiz' ); ?></span>
					<span class="ppq-meta-value"><?php echo esc_html( $this->format_duration( $this->get_display_time( $attempt ) ) ); ?></span>
				</div>

				<?php
				// Show average comparison if available
				$average = $this->get_quiz_average( $quiz->id );
				if ( null !== $average ) :
					?>
					<div class="ppq-meta-item">
						<span class="ppq-meta-icon">📊</span>
						<span class="ppq-meta-label"><?php esc_html_e( 'Average:', 'pressprimer-quiz' ); ?></span>
						<span class="ppq-meta-value"><?php echo esc_html( round( $average, 1 ) ); ?>%</span>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render email sent notice
	 *
	 * Shows a notice when auto-send email is enabled and the student has an email.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 */
	private function render_email_sent_notice( $attempt ) {
		// Check if auto-send is enabled
		$settings  = get_option( 'ppq_settings', [] );
		$auto_send = isset( $settings['email_results_auto_send'] ) && $settings['email_results_auto_send'];

		if ( ! $auto_send ) {
			return;
		}

		// Check if student has an email address
		$has_email = false;
		if ( $attempt->user_id ) {
			$user = get_userdata( $attempt->user_id );
			if ( $user && $user->user_email ) {
				$has_email = true;
			}
		} elseif ( $attempt->guest_email ) {
			$has_email = true;
		}

		if ( ! $has_email ) {
			return;
		}

		?>
		<div class="ppq-email-sent-notice">
			<span class="ppq-email-sent-icon">📧</span>
			<span class="ppq-email-sent-text"><?php esc_html_e( 'A copy of your results has been sent to your email address.', 'pressprimer-quiz' ); ?></span>
		</div>
		<?php
	}

	/**
	 * Render category breakdown
	 *
	 * @since 1.0.0
	 *
	 * @param array                    $results Results data.
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @param PressPrimer_Quiz_Quiz    $quiz    Quiz object.
	 */
	private function render_category_breakdown( $results, $attempt = null, $quiz = null ) {
		if ( empty( $results['category_scores'] ) ) {
			return;
		}

		/**
		 * Filter whether to show the category breakdown section.
		 *
		 * @since 1.0.0
		 *
		 * @param bool        $show    Whether to show category breakdown. Default true.
		 * @param array       $results The results data with category_scores.
		 * @param PressPrimer_Quiz_Attempt $attempt The attempt object.
		 * @param PressPrimer_Quiz_Quiz    $quiz    The quiz object.
		 */
		if ( ! apply_filters( 'pressprimer_quiz_show_category_breakdown', true, $results, $attempt, $quiz ) ) {
			return;
		}

		?>
		<div class="ppq-category-breakdown">
			<h3 class="ppq-section-title"><?php esc_html_e( 'Performance by Category', 'pressprimer-quiz' ); ?></h3>
			<div class="ppq-category-bars">
				<?php foreach ( $results['category_scores'] as $category ) : ?>
					<?php
					$percentage = $category['total'] > 0
						? ( $category['correct'] / $category['total'] ) * 100
						: 0;
					?>
					<div class="ppq-category-item">
						<div class="ppq-category-name"><?php echo esc_html( $category['name'] ); ?></div>
						<div class="ppq-category-bar">
							<div class="ppq-category-fill" style="width: <?php echo esc_attr( $percentage ); ?>%;"></div>
						</div>
						<div class="ppq-category-stats">
							<?php echo esc_html( round( $percentage ) ); ?>%
							<span class="ppq-category-count">
								(<?php echo (int) $category['correct']; ?>/<?php echo (int) $category['total']; ?>)
							</span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render confidence calibration
	 *
	 * @since 1.0.0
	 *
	 * @param array $results Results data.
	 */
	private function render_confidence_calibration( $results ) {
		$stats = $results['confidence_stats'];

		// Check if confidence was used
		$total_confident = $stats['confident_correct'] + $stats['confident_incorrect'];
		if ( $total_confident === 0 ) {
			return; // No confidence data
		}

		$calibration = ( $stats['confident_correct'] / $total_confident ) * 100;

		// Determine calibration message
		if ( $calibration >= 90 ) {
			$message = __( 'Your confidence is well-calibrated!', 'pressprimer-quiz' );
			$icon    = '💡';
		} elseif ( $calibration >= 70 ) {
			$message = __( 'Your confidence is fairly good, but there\'s room for improvement.', 'pressprimer-quiz' );
			$icon    = '📊';
		} else {
			$message = __( 'You may be overconfident. Review the questions you marked as confident.', 'pressprimer-quiz' );
			$icon    = '⚠️';
		}

		?>
		<div class="ppq-confidence-calibration">
			<h3 class="ppq-section-title"><?php esc_html_e( 'Confidence Analysis', 'pressprimer-quiz' ); ?></h3>
			<div class="ppq-confidence-content">
				<p>
					<?php
					printf(
						/* translators: 1: number of confident answers, 2: number of correct confident answers, 3: calibration percentage */
						esc_html__( 'You marked %1$d answers as confident. %2$d of those were correct (%3$d%% calibration).', 'pressprimer-quiz' ),
						(int) $total_confident,
						(int) $stats['confident_correct'],
						(int) round( $calibration )
					);
					?>
				</p>
				<p class="ppq-confidence-message">
					<span class="ppq-confidence-icon"><?php echo esc_html( $icon ); ?></span>
					<?php echo esc_html( $message ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render score-banded feedback
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Quiz $quiz Quiz object.
	 * @param array                 $results Results data.
	 */
	private function render_score_feedback( $quiz, $results ) {
		$feedback = $this->get_score_feedback( $quiz, $results['score_percent'] );

		if ( empty( $feedback ) ) {
			return;
		}

		?>
		<div class="ppq-score-feedback">
			<h3 class="ppq-section-title"><?php esc_html_e( 'Feedback', 'pressprimer-quiz' ); ?></h3>
			<div class="ppq-feedback-content">
				<?php echo wp_kses_post( wpautop( $feedback ) ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render results actions
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @param PressPrimer_Quiz_Quiz    $quiz Quiz object.
	 */
	private function render_results_actions( $attempt, $quiz ) {
		// Check for LearnDash navigation data
		$ld_nav = $this->get_learndash_navigation( $attempt );

		?>
		<div class="ppq-results-actions">
			<?php
			/**
			 * Fires at the start of the results actions area.
			 *
			 * Use this to add custom buttons at the beginning of the actions section.
			 *
			 * @since 1.0.0
			 *
			 * @param PressPrimer_Quiz_Attempt $attempt The attempt object.
			 * @param PressPrimer_Quiz_Quiz    $quiz    The quiz object.
			 */
			do_action( 'pressprimer_quiz_results_actions_start', $attempt, $quiz );

			// LearnDash "Continue" button (only if passed and has next URL)
			if ( $ld_nav && $attempt->passed && ! empty( $ld_nav['next_url'] ) ) :
				?>
				<a href="<?php echo esc_url( $ld_nav['next_url'] ); ?>" class="ppq-button ppq-button-primary ppq-continue-button button">
					<?php esc_html_e( 'Continue', 'pressprimer-quiz' ); ?> →
				</a>
			<?php endif; ?>

			<a href="#ppq-question-review" class="ppq-button ppq-review-button button">
				<?php esc_html_e( 'Review Answers', 'pressprimer-quiz' ); ?>
			</a>

			<?php
			// Retake button if allowed (and not using LearnDash context where they need to pass)
			if ( $this->can_retake( $quiz, $attempt ) ) :
				?>
				<a href="<?php echo esc_url( $this->get_retake_url( $quiz ) ); ?>" class="ppq-button ppq-retake-button button">
					<?php esc_html_e( 'Retake Quiz', 'pressprimer-quiz' ); ?>
				</a>
			<?php endif; ?>

			<?php
			// Return to course button for LearnDash (if failed or as secondary option)
			if ( $ld_nav && ! empty( $ld_nav['course_url'] ) && ( ! $attempt->passed || empty( $ld_nav['next_url'] ) ) ) :
				?>
				<a href="<?php echo esc_url( $ld_nav['course_url'] ); ?>" class="ppq-button ppq-course-button button">
					<?php esc_html_e( 'Return to Course', 'pressprimer-quiz' ); ?>
				</a>
			<?php endif; ?>

			<?php
			// Email results button
			$this->render_email_button( $attempt );

			/**
			 * Fires at the end of the results actions area.
			 *
			 * Use this to add custom buttons at the end of the actions section.
			 *
			 * @since 1.0.0
			 *
			 * @param PressPrimer_Quiz_Attempt $attempt The attempt object.
			 * @param PressPrimer_Quiz_Quiz    $quiz    The quiz object.
			 */
			do_action( 'pressprimer_quiz_results_actions_end', $attempt, $quiz );
			?>
		</div>

		<?php
	}

	/**
	 * Get LearnDash navigation data from attempt meta
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @return array|null Navigation data or null if not LearnDash context.
	 */
	private function get_learndash_navigation( $attempt ) {
		if ( empty( $attempt->meta_json ) ) {
			return null;
		}

		$meta = json_decode( $attempt->meta_json, true );

		if ( empty( $meta['learndash_post_id'] ) ) {
			return null;
		}

		// Check if LearnDash integration is available
		if ( ! class_exists( 'PressPrimer_Quiz_LearnDash' ) || ! defined( 'LEARNDASH_VERSION' ) ) {
			return null;
		}

		$learndash  = new PressPrimer_Quiz_LearnDash();
		$ld_post_id = (int) $meta['learndash_post_id'];
		$post_type  = get_post_type( $ld_post_id );

		// For courses, return to course page on completion
		if ( 'sfwd-courses' === $post_type ) {
			return [
				'post_id'    => $ld_post_id,
				'post_type'  => $post_type,
				'next_url'   => get_permalink( $ld_post_id ), // Return to course
				'course_url' => get_permalink( $ld_post_id ),
			];
		}

		// For lessons/topics, get next step URL
		return [
			'post_id'    => $ld_post_id,
			'post_type'  => $post_type,
			'next_url'   => $learndash->get_next_step_url( $ld_post_id ),
			'course_url' => get_permalink( $meta['learndash_course_id'] ?? null ),
		];
	}

	/**
	 * Render email results button
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 */
	private function render_email_button( $attempt ) {
		// Get email address
		$email = '';
		if ( $attempt->user_id ) {
			$user = get_userdata( $attempt->user_id );
			if ( $user ) {
				$email = $user->user_email;
			}
		} elseif ( $attempt->guest_email ) {
			$email = $attempt->guest_email;
		}

		if ( ! $email ) {
			return; // No email available
		}

		?>
		<button
			type="button"
			class="ppq-button ppq-email-button"
			data-attempt-id="<?php echo esc_attr( $attempt->id ); ?>"
			data-email="<?php echo esc_attr( $email ); ?>"
		>
			<?php esc_html_e( 'Email Results', 'pressprimer-quiz' ); ?>
		</button>
		<div class="ppq-email-status" style="display: none;"></div>
		<?php
	}

	/**
	 * Render question review
	 *
	 * Displays detailed review of each question with user's answers.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @return string HTML output.
	 */
	public function render_question_review( $attempt ) {
		if ( ! $attempt || 'submitted' !== $attempt->status ) {
			return '<div class="ppq-error">' . esc_html__( 'Review not available.', 'pressprimer-quiz' ) . '</div>';
		}

		// Get quiz
		$quiz = $attempt->get_quiz();
		if ( ! $quiz ) {
			return '<div class="ppq-error">' . esc_html__( 'Quiz not found.', 'pressprimer-quiz' ) . '</div>';
		}

		// Get all attempt items
		$items = $attempt->get_items();

		if ( empty( $items ) ) {
			return '<div class="ppq-error">' . esc_html__( 'No questions found.', 'pressprimer-quiz' ) . '</div>';
		}

		// Determine if we should show correct answers
		$show_correct_answers = $this->should_show_correct_answers( $quiz, $attempt );

		// Get theme class
		$theme_class = PressPrimer_Quiz_Theme_Loader::get_theme_class( PressPrimer_Quiz_Theme_Loader::get_quiz_theme( $quiz ) );

		// Build output
		ob_start();
		?>
		<div id="ppq-question-review" class="ppq-question-review-container <?php echo esc_attr( $theme_class ); ?>">
			<h2 class="ppq-review-title"><?php esc_html_e( 'Question Review', 'pressprimer-quiz' ); ?></h2>

			<?php foreach ( $items as $index => $item ) : ?>
				<?php $this->render_single_question_review( $item, $index + 1, count( $items ), $quiz, $show_correct_answers ); ?>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render single question review
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt_Item $item Attempt item.
	 * @param int                           $current_num Current question number.
	 * @param int                           $total_num Total number of questions.
	 * @param PressPrimer_Quiz_Quiz         $quiz Quiz object.
	 * @param bool                          $show_correct_answers Whether to show correct answers.
	 */
	private function render_single_question_review( $item, $current_num, $total_num, $quiz, $show_correct_answers ) {
		// Get question revision (locked at attempt time)
		$revision = $item->get_question_revision();
		if ( ! $revision ) {
			return;
		}

		$question = $item->get_question();
		if ( ! $question ) {
			return;
		}

		$answers          = $revision->get_answers();
		$selected_answers = $item->get_selected_answers();

		// Determine status
		$status_class = $item->is_correct ? 'ppq-correct' : 'ppq-incorrect';
		$status_icon  = $item->is_correct ? '✓' : '✗';

		?>
		<div class="ppq-review-item <?php echo esc_attr( $status_class ); ?>">
			<div class="ppq-review-header">
				<div class="ppq-review-number">
					<?php
					printf(
						/* translators: 1: current question number, 2: total questions */
						esc_html__( 'Question %1$d of %2$d', 'pressprimer-quiz' ),
						(int) $current_num,
						(int) $total_num
					);
					?>
				</div>
				<div class="ppq-review-status">
					<span class="ppq-status-icon <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_icon ); ?></span>
				</div>
			</div>

			<div class="ppq-review-meta">
				<?php if ( $item->time_spent_ms ) : ?>
					<span class="ppq-review-time">
						<span class="ppq-meta-icon">⏱️</span>
						<?php
						printf(
							/* translators: %s: time spent */
							esc_html__( 'Time: %s', 'pressprimer-quiz' ),
							esc_html( $this->format_duration( $item->time_spent_ms ) )
						);
						?>
					</span>
				<?php endif; ?>

				<?php if ( $quiz->enable_confidence && null !== $item->confidence ) : ?>
					<span class="ppq-review-confidence">
						<?php
						/* translators: Student's confidence level when answering the question */
						$confidence_text = $item->confidence
							? __( 'Confident', 'pressprimer-quiz' )
							: __( 'Not Confident', 'pressprimer-quiz' );
						$confidence_icon = $item->confidence ? '💪' : '🤔';
						?>
						<span class="ppq-meta-icon"><?php echo esc_html( $confidence_icon ); ?></span>
						<?php echo esc_html( $confidence_text ); ?>
					</span>
				<?php endif; ?>

				<?php if ( $item->score_points && ! $item->is_correct ) : ?>
					<span class="ppq-review-partial">
						<?php
						printf(
							/* translators: %s: points earned */
							esc_html__( 'Partial credit: %s points', 'pressprimer-quiz' ),
							esc_html( number_format_i18n( $item->score_points, 2 ) )
						);
						?>
					</span>
				<?php endif; ?>
			</div>

			<div class="ppq-review-stem">
				<?php echo wp_kses_post( $revision->stem ); ?>
			</div>

			<div class="ppq-review-answers">
				<?php $this->render_answer_options( $answers, $selected_answers, $show_correct_answers, $question->type ); ?>
			</div>

			<?php $this->render_question_feedback( $item, $revision, $answers, $selected_answers, $show_correct_answers ); ?>
		</div>
		<?php
	}

	/**
	 * Render answer options
	 *
	 * @since 1.0.0
	 *
	 * @param array  $answers All answer options.
	 * @param array  $selected_answers Selected answer indices.
	 * @param bool   $show_correct_answers Whether to show correct answers.
	 * @param string $question_type Question type (mc, ma, tf).
	 */
	private function render_answer_options( $answers, $selected_answers, $show_correct_answers, $question_type ) {
		foreach ( $answers as $index => $answer ) {
			$is_selected = in_array( $index, $selected_answers, true );
			$is_correct  = ! empty( $answer['is_correct'] );

			// Determine classes
			$classes = [ 'ppq-review-option' ];

			if ( $is_selected ) {
				$classes[] = 'ppq-selected';

				if ( $is_correct ) {
					$classes[] = 'ppq-correct';
				} else {
					$classes[] = 'ppq-incorrect';
				}
			} elseif ( $show_correct_answers && $is_correct ) {
				$classes[] = 'ppq-correct-answer';
			}

			// Determine indicator icon and label
			$indicator = '';
			$label     = '';
			if ( $is_selected ) {
				if ( $is_correct ) {
					$indicator = '<span class="ppq-answer-indicator ppq-correct">✓</span>';
					$label     = '<span class="ppq-answer-label ppq-your-answer-correct">' . esc_html__( 'Your answer (Correct)', 'pressprimer-quiz' ) . '</span>';
				} else {
					$indicator = '<span class="ppq-answer-indicator ppq-incorrect">✗</span>';
					$label     = '<span class="ppq-answer-label ppq-your-answer-incorrect">' . esc_html__( 'Your answer (Incorrect)', 'pressprimer-quiz' ) . '</span>';
				}
			} elseif ( $show_correct_answers && $is_correct ) {
				$indicator = '<span class="ppq-answer-indicator ppq-correct-marker">→</span>';
				$label     = '<span class="ppq-answer-label ppq-correct-answer-label">' . esc_html__( 'Correct answer', 'pressprimer-quiz' ) . '</span>';
			}

			?>
			<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
				<div class="ppq-answer-header">
					<?php echo $indicator; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="ppq-answer-text">
					<?php echo wp_kses_post( $answer['text'] ); ?>
				</div>

				<?php
				// Show per-answer feedback if available
				if ( $is_selected && ! empty( $answer['feedback'] ) ) :
					?>
					<div class="ppq-answer-feedback">
						<?php echo wp_kses_post( wpautop( $answer['feedback'] ) ); ?>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}

		// Show explanation if correct answers are hidden
		if ( ! $show_correct_answers ) :
			?>
			<div class="ppq-answers-hidden-notice">
				<?php esc_html_e( 'Correct answers are not shown for this quiz.', 'pressprimer-quiz' ); ?>
			</div>
			<?php
		endif;
	}

	/**
	 * Render question feedback
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt_Item      $item Attempt item.
	 * @param PressPrimer_Quiz_Question_Revision $revision Question revision.
	 * @param array                              $answers All answers.
	 * @param array                              $selected_answers Selected answer indices.
	 * @param bool                               $show_correct_answers Whether showing correct answers.
	 */
	private function render_question_feedback( $item, $revision, $answers, $selected_answers, $show_correct_answers ) {
		// Per-question feedback
		$feedback = null;
		if ( $item->is_correct && ! empty( $revision->feedback_correct ) ) {
			$feedback = $revision->feedback_correct;
		} elseif ( ! $item->is_correct && ! empty( $revision->feedback_incorrect ) ) {
			$feedback = $revision->feedback_incorrect;
		}

		if ( ! $feedback ) {
			return;
		}

		?>
		<div class="ppq-review-feedback">
			<div class="ppq-feedback-icon">💬</div>
			<div class="ppq-feedback-text">
				<?php echo wp_kses_post( wpautop( $feedback ) ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Determine if correct answers should be shown
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Quiz    $quiz Quiz object.
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @return bool True if correct answers should be shown.
	 */
	private function should_show_correct_answers( $quiz, $attempt ) {
		switch ( $quiz->show_answers ) {
			case 'never':
				return false;

			case 'after_pass':
				return (bool) $attempt->passed;

			case 'after_submit':
			default:
				return true;
		}
	}

	/**
	 * Calculate comprehensive results
	 *
	 * Calculates all result metrics including category scores and confidence stats.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @return array Results data.
	 */
	private function calculate_results( $attempt ) {
		$items = $attempt->get_items();

		$results = [
			'score_points'     => $attempt->score_points,
			'max_points'       => $attempt->max_points,
			'score_percent'    => $attempt->score_percent,
			'correct_count'    => 0,
			'total_count'      => count( $items ),
			'category_scores'  => [],
			'confidence_stats' => [
				'confident_correct'       => 0,
				'confident_incorrect'     => 0,
				'not_confident_correct'   => 0,
				'not_confident_incorrect' => 0,
			],
		];

		foreach ( $items as $item ) {
			// Count correct answers
			if ( $item->is_correct ) {
				++$results['correct_count'];
			}

			// Category tracking
			$question = $item->get_question();
			if ( $question ) {
				$categories = PressPrimer_Quiz_Category::get_for_question( $question->id, 'category' );

				foreach ( $categories as $cat ) {
					if ( ! isset( $results['category_scores'][ $cat->id ] ) ) {
						$results['category_scores'][ $cat->id ] = [
							'name'    => $cat->name,
							'correct' => 0,
							'total'   => 0,
						];
					}

					++$results['category_scores'][ $cat->id ]['total'];

					if ( $item->is_correct ) {
						++$results['category_scores'][ $cat->id ]['correct'];
					}
				}
			}

			// Confidence tracking
			if ( $item->confidence ) {
				if ( $item->is_correct ) {
					++$results['confidence_stats']['confident_correct'];
				} else {
					++$results['confidence_stats']['confident_incorrect'];
				}
			} elseif ( $item->is_correct ) {
					++$results['confidence_stats']['not_confident_correct'];
			} else {
				++$results['confidence_stats']['not_confident_incorrect'];
			}
		}

		return $results;
	}

	/**
	 * Get quiz average score
	 *
	 * Returns average score across all submitted attempts.
	 * Only returns if at least 5 attempts exist.
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return float|null Average percentage or null.
	 */
	private function get_quiz_average( $quiz_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ppq_attempts';

		// Count attempts
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Quiz statistics, dynamic data
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE quiz_id = %d AND status = 'submitted'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quiz_id
			)
		);

		// Need at least 5 attempts for meaningful average
		if ( $count < 5 ) {
			return null;
		}

		// Calculate average
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Quiz statistics, dynamic data
		$avg = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(score_percent) FROM {$table} WHERE quiz_id = %d AND status = 'submitted'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$quiz_id
			)
		);

		return $avg ? (float) $avg : null;
	}

	/**
	 * Get score-banded feedback message
	 *
	 * Returns feedback message based on score percentage.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Quiz $quiz Quiz object.
	 * @param float                 $score_percent Score percentage.
	 * @return string Feedback message.
	 */
	private function get_score_feedback( $quiz, $score_percent ) {
		return $quiz->get_feedback_for_score( $score_percent );
	}

	/**
	 * Check if user can retake quiz
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Quiz    $quiz Quiz object.
	 * @param PressPrimer_Quiz_Attempt $attempt Current attempt.
	 * @return bool True if can retake.
	 */
	private function can_retake( $quiz, $attempt ) {
		// Check if quiz has max attempts limit
		if ( $quiz->max_attempts ) {
			$user_id = $attempt->user_id ? $attempt->user_id : 0;

			if ( $user_id ) {
				$attempts        = PressPrimer_Quiz_Attempt::get_user_attempts( $quiz->id, $user_id );
				$submitted_count = 0;

				foreach ( $attempts as $att ) {
					if ( 'submitted' === $att->status ) {
						++$submitted_count;
					}
				}

				if ( $submitted_count >= $quiz->max_attempts ) {
					return false;
				}
			}
		}

		// Check attempt delay
		if ( $quiz->attempt_delay_minutes && $attempt->finished_at ) {
			$elapsed_minutes = ( time() - strtotime( $attempt->finished_at ) ) / 60;

			if ( $elapsed_minutes < $quiz->attempt_delay_minutes ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get retake URL
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Quiz $quiz Quiz object.
	 * @return string Retake URL.
	 */
	private function get_retake_url( $quiz ) {
		// Remove attempt/token parameters and add retake flag
		$url = remove_query_arg( [ 'attempt', 'token', 'timed_out' ] );
		return add_query_arg( 'ppq_retake', '1', $url );
	}

	/**
	 * Get display time for attempt
	 *
	 * Returns the wall-clock elapsed time (from start to finish).
	 * This matches user expectations since it aligns with the timer they saw.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @return int Time in milliseconds.
	 */
	private function get_display_time( $attempt ) {
		// Use wall-clock time (matches the timer shown during the quiz)
		return (int) $attempt->elapsed_ms;
	}

	/**
	 * Format duration
	 *
	 * Converts milliseconds to human-readable time format.
	 *
	 * @since 1.0.0
	 *
	 * @param int $milliseconds Duration in milliseconds.
	 * @return string Formatted duration.
	 */
	private function format_duration( $milliseconds ) {
		$seconds = floor( $milliseconds / 1000 );
		$minutes = floor( $seconds / 60 );
		$hours   = floor( $minutes / 60 );

		$seconds = $seconds % 60;
		$minutes = $minutes % 60;

		if ( $hours > 0 ) {
			return sprintf( '%d:%02d:%02d', $hours, $minutes, $seconds );
		}

		return sprintf( '%d:%02d', $minutes, $seconds );
	}
}
