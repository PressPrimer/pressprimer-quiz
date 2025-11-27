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
class PPQ_Results_Renderer {

	/**
	 * Render results page
	 *
	 * Displays comprehensive results after quiz submission.
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Attempt $attempt Attempt object.
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

		// Build output
		ob_start();
		?>
		<div class="ppq-results-container">
			<?php $this->render_results_header( $attempt, $quiz, $results ); ?>
			<?php $this->render_score_summary( $attempt, $quiz, $results ); ?>
			<?php $this->render_category_breakdown( $results ); ?>
			<?php $this->render_confidence_calibration( $results ); ?>
			<?php $this->render_score_feedback( $quiz, $results ); ?>
			<?php $this->render_results_actions( $attempt, $quiz ); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render results header
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Attempt $attempt Attempt object.
	 * @param PPQ_Quiz    $quiz Quiz object.
	 * @param array       $results Results data.
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
	 * Render score summary
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Attempt $attempt Attempt object.
	 * @param PPQ_Quiz    $quiz Quiz object.
	 * @param array       $results Results data.
	 */
	private function render_score_summary( $attempt, $quiz, $results ) {
		$passed_class = $attempt->passed ? 'ppq-passed' : 'ppq-failed';
		$passed_text  = $attempt->passed
			? __( 'PASSED', 'pressprimer-quiz' )
			: __( 'FAILED', 'pressprimer-quiz' );

		?>
		<div class="ppq-score-summary <?php echo esc_attr( $passed_class ); ?>">
			<div class="ppq-score-display">
				<div class="ppq-score-percentage">
					<?php echo esc_html( round( $attempt->score_percent, 1 ) ); ?>%
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
					<span class="ppq-meta-value"><?php echo esc_html( $this->format_duration( $attempt->elapsed_ms ) ); ?></span>
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
	 * Render category breakdown
	 *
	 * @since 1.0.0
	 *
	 * @param array $results Results data.
	 */
	private function render_category_breakdown( $results ) {
		if ( empty( $results['category_scores'] ) ) {
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
			$icon = '💡';
		} elseif ( $calibration >= 70 ) {
			$message = __( 'Your confidence is fairly good, but there\'s room for improvement.', 'pressprimer-quiz' );
			$icon = '📊';
		} else {
			$message = __( 'You may be overconfident. Review the questions you marked as confident.', 'pressprimer-quiz' );
			$icon = '⚠️';
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
	 * @param PPQ_Quiz $quiz Quiz object.
	 * @param array    $results Results data.
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
	 * @param PPQ_Attempt $attempt Attempt object.
	 * @param PPQ_Quiz    $quiz Quiz object.
	 */
	private function render_results_actions( $attempt, $quiz ) {
		?>
		<div class="ppq-results-actions">
			<a href="#ppq-question-review" class="ppq-button ppq-review-button">
				<?php esc_html_e( 'Review Answers', 'pressprimer-quiz' ); ?>
			</a>

			<?php
			// Retake button if allowed
			if ( $this->can_retake( $quiz, $attempt ) ) :
				?>
				<a href="<?php echo esc_url( $this->get_retake_url( $quiz ) ); ?>" class="ppq-button ppq-retake-button">
					<?php esc_html_e( 'Retake Quiz', 'pressprimer-quiz' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render question review
	 *
	 * Displays detailed review of each question with user's answers.
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Attempt $attempt Attempt object.
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

		// Build output
		ob_start();
		?>
		<div id="ppq-question-review" class="ppq-question-review-container">
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
	 * @param PPQ_Attempt_Item $item Attempt item.
	 * @param int              $current_num Current question number.
	 * @param int              $total_num Total number of questions.
	 * @param PPQ_Quiz         $quiz Quiz object.
	 * @param bool             $show_correct_answers Whether to show correct answers.
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

		$answers = $revision->get_answers();
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
							esc_html( number_format( $item->score_points, 2 ) )
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
	 * @param array $answers All answer options.
	 * @param array $selected_answers Selected answer indices.
	 * @param bool  $show_correct_answers Whether to show correct answers.
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

			// Determine indicator icon
			$indicator = '';
			if ( $is_selected ) {
				if ( $is_correct ) {
					$indicator = '<span class="ppq-answer-indicator ppq-correct">✓</span>';
				} else {
					$indicator = '<span class="ppq-answer-indicator ppq-incorrect">✗</span>';
				}
			} elseif ( $show_correct_answers && $is_correct ) {
				$indicator = '<span class="ppq-answer-indicator ppq-correct-marker">→</span>';
			}

			?>
			<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
				<?php echo $indicator; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
		<?php endif; ?>
	}

	/**
	 * Render question feedback
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Attempt_Item      $item Attempt item.
	 * @param PPQ_Question_Revision $revision Question revision.
	 * @param array                 $answers All answers.
	 * @param array                 $selected_answers Selected answer indices.
	 * @param bool                  $show_correct_answers Whether showing correct answers.
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
	 * @param PPQ_Quiz    $quiz Quiz object.
	 * @param PPQ_Attempt $attempt Attempt object.
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
	 * @param PPQ_Attempt $attempt Attempt object.
	 * @return array Results data.
	 */
	private function calculate_results( $attempt ) {
		$items = $attempt->get_items();

		$results = [
			'score_points'      => $attempt->score_points,
			'max_points'        => $attempt->max_points,
			'score_percent'     => $attempt->score_percent,
			'correct_count'     => 0,
			'total_count'       => count( $items ),
			'category_scores'   => [],
			'confidence_stats'  => [
				'confident_correct'       => 0,
				'confident_incorrect'     => 0,
				'not_confident_correct'   => 0,
				'not_confident_incorrect' => 0,
			],
		];

		foreach ( $items as $item ) {
			// Count correct answers
			if ( $item->is_correct ) {
				$results['correct_count']++;
			}

			// Category tracking
			$question = $item->get_question();
			if ( $question ) {
				$categories = PPQ_Category::get_for_question( $question->id, 'category' );

				foreach ( $categories as $cat ) {
					if ( ! isset( $results['category_scores'][ $cat->id ] ) ) {
						$results['category_scores'][ $cat->id ] = [
							'name'    => $cat->name,
							'correct' => 0,
							'total'   => 0,
						];
					}

					$results['category_scores'][ $cat->id ]['total']++;

					if ( $item->is_correct ) {
						$results['category_scores'][ $cat->id ]['correct']++;
					}
				}
			}

			// Confidence tracking
			if ( $item->confidence ) {
				if ( $item->is_correct ) {
					$results['confidence_stats']['confident_correct']++;
				} else {
					$results['confidence_stats']['confident_incorrect']++;
				}
			} else {
				if ( $item->is_correct ) {
					$results['confidence_stats']['not_confident_correct']++;
				} else {
					$results['confidence_stats']['not_confident_incorrect']++;
				}
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
	 * @param PPQ_Quiz $quiz Quiz object.
	 * @param float    $score_percent Score percentage.
	 * @return string Feedback message.
	 */
	private function get_score_feedback( $quiz, $score_percent ) {
		// Get feedback bands from quiz meta
		$feedback_bands = get_post_meta( $quiz->id, '_ppq_feedback_bands', true );

		if ( empty( $feedback_bands ) || ! is_array( $feedback_bands ) ) {
			return '';
		}

		// Find matching band
		foreach ( $feedback_bands as $band ) {
			if ( $score_percent >= $band['min'] && $score_percent <= $band['max'] ) {
				return $band['message'];
			}
		}

		return '';
	}

	/**
	 * Check if user can retake quiz
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz    $quiz Quiz object.
	 * @param PPQ_Attempt $attempt Current attempt.
	 * @return bool True if can retake.
	 */
	private function can_retake( $quiz, $attempt ) {
		// Check if quiz has max attempts limit
		if ( $quiz->max_attempts ) {
			$user_id = $attempt->user_id ? $attempt->user_id : 0;

			if ( $user_id ) {
				$attempts = PPQ_Attempt::get_user_attempts( $quiz->id, $user_id );
				$submitted_count = 0;

				foreach ( $attempts as $att ) {
					if ( 'submitted' === $att->status ) {
						$submitted_count++;
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
	 * @param PPQ_Quiz $quiz Quiz object.
	 * @return string Retake URL.
	 */
	private function get_retake_url( $quiz ) {
		// This will be the same URL as the quiz shortcode
		// For now, return current URL with retake parameter
		return add_query_arg( 'ppq_retake', '1' );
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
