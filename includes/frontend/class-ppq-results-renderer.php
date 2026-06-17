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
	 * Current display options.
	 *
	 * @since 2.1.0
	 * @var array
	 */
	private $display = [];

	/**
	 * Get default display options for Results page
	 *
	 * @since 2.1.0
	 *
	 * @return array Default display options.
	 */
	private function get_default_results_display_options() {
		return [
			'show_score'                => true,
			'show_pass_fail'            => true,
			'show_time_spent'           => true,
			'show_average'              => true,
			'show_category_breakdown'   => true,
			'show_question_review'      => true,
			'show_retake_button'        => true,
			'show_scoring_explanations' => true,
		];
	}

	/**
	 * Apply conflict rules between display options and quiz settings
	 *
	 * Quiz settings take precedence when they restrict functionality.
	 * Shortcode attributes can hide elements but cannot force-show
	 * elements that quiz settings explicitly disable.
	 *
	 * @since 2.1.0
	 *
	 * @param array                    $display Display options.
	 * @param PressPrimer_Quiz_Quiz    $quiz    Quiz object.
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @return array Modified display options.
	 */
	private function apply_conflict_rules( $display, $quiz, $attempt ) {
		// If quiz has retakes disabled or user cannot retake, never show retake button.
		if ( ! $this->can_retake( $quiz, $attempt ) ) {
			$display['show_retake_button'] = false;
		}

		// If no passing score set (0%), hide pass/fail indicator.
		if ( $quiz->pass_percent <= 0 ) {
			$display['show_pass_fail'] = false;
		}

		/**
		 * Filter display options after conflict rules applied
		 *
		 * Allows addons to modify display options based on their own rules.
		 *
		 * @since 2.1.0
		 *
		 * @param array                    $display Display options.
		 * @param PressPrimer_Quiz_Quiz    $quiz    Quiz object.
		 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
		 */
		return apply_filters( 'pressprimer_quiz_results_display_options', $display, $quiz, $attempt );
	}

	/**
	 * Render results page
	 *
	 * Displays comprehensive results after quiz submission.
	 *
	 * @since 1.0.0
	 * @since 2.1.0 Added display options parameter.
	 * @since 2.3.0 Second parameter is now a sparse map of instance overrides
	 *              (block/shortcode attributes that were explicitly set). The
	 *              final display map resolves through the quiz model so LMS
	 *              embeds inherit the quiz's display_settings_json defaults
	 *              with one resolution path.
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt            Attempt object.
	 * @param array                    $instance_overrides Sparse map of display keys explicitly set
	 *                                                      by the block or shortcode. Absent keys
	 *                                                      fall through to quiz default, then hard default.
	 * @return string HTML output.
	 */
	public function render_results( $attempt, $instance_overrides = [] ) {
		if ( ! $attempt || 'submitted' !== $attempt->status ) {
			return '<div class="ppq-error">' . esc_html__( 'Results not available.', 'pressprimer-quiz' ) . '</div>';
		}

		// Get quiz
		$quiz = $attempt->get_quiz();
		if ( ! $quiz ) {
			return '<div class="ppq-error">' . esc_html__( 'Quiz not found.', 'pressprimer-quiz' ) . '</div>';
		}

		// Resolve display options through the three-tier precedence on the
		// quiz model: instance override > display_settings_json > hard default.
		// Then apply conflict rules that depend on the actual attempt/quiz state.
		$display       = $quiz->resolve_all_display_options( $instance_overrides );
		$display       = $this->apply_conflict_rules( $display, $quiz, $attempt );
		$this->display = $display;

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

		// Get display density class
		$density       = $quiz->get_effective_display_density();
		$density_class = 'condensed' === $density ? 'ppq-quiz--condensed' : '';

		// Build sections array based on display options.
		$default_sections = [ 'header', 'guest_notice', 'email_notice', 'feedback' ];

		// Add score_summary if any of its sub-elements should be shown.
		if ( $display['show_score'] || $display['show_pass_fail'] || $display['show_time_spent'] || $display['show_average'] ) {
			$default_sections[] = 'score_summary';
		}

		// Add category_breakdown if enabled.
		if ( $display['show_category_breakdown'] ) {
			$default_sections[] = 'category_breakdown';
		}

		// Always include confidence (it has its own internal check for data).
		$default_sections[] = 'confidence';

		// Always include actions (retake button visibility is controlled within the method).
		$default_sections[] = 'actions';

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
			$default_sections,
			$attempt,
			$quiz
		);

		// Build output
		ob_start();
		?>
		<div class="ppq-results-container <?php echo esc_attr( $theme_class ); ?> <?php echo esc_attr( $density_class ); ?>">
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
	 * @since 2.1.0 Added display options support.
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
			<?php if ( $this->display['show_score'] ) : ?>
			<div class="ppq-score-display">
				<div class="ppq-score-percentage">
					<?php
					$score_default = esc_html( round( (float) $attempt->score_percent, 1 ) ) . '%';

					/**
					 * Filters the score string displayed on the results page.
					 *
					 * Default value is the rounded percentage followed by a `%`
					 * symbol (already escaped). Themes and addons can return
					 * any HTML — common uses include hiding the percent symbol,
					 * switching to a custom label (e.g., "12.5 star points"),
					 * or rendering the absolute score instead. The returned
					 * value is passed through `wp_kses_post()` before output,
					 * so safe HTML is preserved and scripts/styles are stripped.
					 *
					 * Callbacks merging user input into the returned string
					 * must escape that input themselves; the default value is
					 * escape-safe.
					 *
					 * @since 2.3.0
					 *
					 * @param string                       $score_html Default escaped score string (e.g., "12.5%").
					 * @param PressPrimer_Quiz_Attempt     $attempt    Attempt object.
					 * @param PressPrimer_Quiz_Quiz        $quiz       Quiz object.
					 * @param array                        $results    Calculated results data, including score_percent,
					 *                                                 correct_count, and total_count.
					 */
					$score_display = apply_filters(
						'pressprimer_quiz_results_score_html',
						$score_default,
						$attempt,
						$quiz,
						$results
					);

					echo wp_kses_post( $score_display );
					?>
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
				<?php if ( $quiz->pool_enabled ) : ?>
					<?php
					$pool_info = $quiz->get_pool_size();
					if ( $pool_info['count'] > 0 ) :
						?>
					<div class="ppq-pool-context">
						<?php
						printf(
							/* translators: %d: total number of questions in the pool */
							esc_html__( 'Questions randomly selected from a pool of %d.', 'pressprimer-quiz' ),
							absint( $pool_info['count'] )
						);
						?>
					</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<?php if ( $this->display['show_pass_fail'] ) : ?>
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
			<?php endif; ?>

			<?php if ( $this->display['show_time_spent'] || $this->display['show_average'] ) : ?>
			<div class="ppq-results-meta">
				<?php if ( $this->display['show_time_spent'] ) : ?>
				<div class="ppq-meta-item">
					<span class="ppq-meta-icon">⏱️</span>
					<span class="ppq-meta-label"><?php esc_html_e( 'Time:', 'pressprimer-quiz' ); ?></span>
					<span class="ppq-meta-value"><?php echo esc_html( $this->format_duration( $this->get_display_time( $attempt ) ) ); ?></span>
				</div>
				<?php endif; ?>

				<?php
				// Show average comparison if available and enabled.
				if ( $this->display['show_average'] ) :
					$average = $this->get_quiz_average( $quiz->id );
					if ( null !== $average ) :
						?>
					<div class="ppq-meta-item">
						<span class="ppq-meta-icon">📊</span>
						<span class="ppq-meta-label"><?php esc_html_e( 'Average:', 'pressprimer-quiz' ); ?></span>
						<span class="ppq-meta-value"><?php echo esc_html( round( $average, 1 ) ); ?>%</span>
					</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<?php $this->render_scoring_explainer( $attempt, $quiz ); ?>

			<?php
			/**
			 * Fires after the score summary display.
			 *
			 * Premium addons can use this to add additional score-related information,
			 * such as badges, achievements, or comparative analytics.
			 *
			 * @since 2.0.0
			 *
			 * @param PressPrimer_Quiz_Attempt $attempt The attempt object.
			 * @param PressPrimer_Quiz_Quiz    $quiz    The quiz object.
			 * @param array                    $results The calculated results data.
			 */
			do_action( 'pressprimer_quiz_results_after_score', $attempt, $quiz, $results );
			?>
		</div>
		<?php
	}

	/**
	 * Render the quiz-level "How scoring works" explainer.
	 *
	 * A collapsed-by-default disclosure in the score summary describing the
	 * scoring mode that graded this attempt's multiple-answer questions, using
	 * the same plain-language copy as the builder (shared copy provider). Shown
	 * only when the attempt recorded a scoring mode (pre-3.0 attempts have none)
	 * and the attempt actually included a multiple-answer question.
	 *
	 * Worked examples are omitted when correctness is hidden from the student,
	 * since the example numbers imply how many selections were correct
	 * (feature 005, FR-004, FR-005, FR-006).
	 *
	 * @since 3.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @param PressPrimer_Quiz_Quiz    $quiz    Quiz object.
	 */
	private function render_scoring_explainer( $attempt, $quiz ) {
		// Display toggle (FR-006). Default on if the key is somehow absent.
		if ( isset( $this->display['show_scoring_explanations'] ) && ! $this->display['show_scoring_explanations'] ) {
			return;
		}

		$mode = ( $attempt && isset( $attempt->ma_scoring_mode ) ) ? $attempt->ma_scoring_mode : null;
		if ( ! class_exists( 'PressPrimer_Quiz_Scoring_Copy' )
			|| ! PressPrimer_Quiz_Scoring_Copy::is_mode( $mode ) ) {
			return;
		}

		if ( ! $this->attempt_has_ma_question( $attempt ) ) {
			return;
		}

		$label       = PressPrimer_Quiz_Scoring_Copy::get_label( $mode );
		$description = PressPrimer_Quiz_Scoring_Copy::get_description( $mode );

		// Examples imply correct-selection counts, so omit them when the quiz
		// hides correctness from the student (FR-005).
		$examples = $this->should_show_correct_answers( $quiz, $attempt )
			? PressPrimer_Quiz_Scoring_Copy::get_examples( $mode )
			: array();

		?>
		<details class="ppq-scoring-explainer">
			<summary class="ppq-scoring-explainer-summary"><?php esc_html_e( 'How scoring works', 'pressprimer-quiz' ); ?></summary>
			<div class="ppq-scoring-explainer-body">
				<p class="ppq-scoring-explainer-mode">
					<?php
					printf(
						/* translators: 1: scoring mode label, 2: mode description. */
						esc_html__( 'Multiple-answer questions use %1$s. %2$s', 'pressprimer-quiz' ),
						esc_html( $label ),
						esc_html( $description )
					);
					?>
				</p>
				<?php if ( ! empty( $examples ) ) : ?>
					<ul class="ppq-scoring-explainer-examples">
						<?php foreach ( $examples as $example ) : ?>
							<li><?php echo esc_html( $example ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</details>
		<?php
	}

	/**
	 * Whether this attempt included at least one multiple-answer question.
	 *
	 * Inspects the attempt's items (already loaded for results), so the
	 * explainer only appears when the scoring mode is actually relevant to
	 * what the student saw — pool/random-distractor safe.
	 *
	 * @since 3.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt|null $attempt Attempt object.
	 * @return bool True if any item is a multiple-answer question.
	 */
	private function attempt_has_ma_question( $attempt ) {
		if ( ! $attempt ) {
			return false;
		}

		foreach ( $attempt->get_items() as $item ) {
			$question = $item->get_question();
			if ( $question && in_array( $question->type, array( 'multiple_answer', 'ma' ), true ) ) {
				return true;
			}
		}

		return false;
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
		$settings  = get_option( 'pressprimer_quiz_settings', [] );
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

			<?php if ( $this->display['show_question_review'] ) : ?>
			<a href="#ppq-question-review" class="ppq-button ppq-review-button button">
				<?php esc_html_e( 'Review Answers', 'pressprimer-quiz' ); ?>
			</a>
			<?php endif; ?>

			<?php
			// Retake button if allowed and display option enabled.
			if ( $this->display['show_retake_button'] && $this->can_retake( $quiz, $attempt ) ) :
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
	 * @since 2.1.0 Added display options parameter.
	 * @since 2.3.0 Second parameter is now a sparse map of instance overrides.
	 *              Display resolution is reused from a prior render_results()
	 *              call on the same instance; if absent, the quiz's resolver
	 *              is consulted.
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt            Attempt object.
	 * @param array                    $instance_overrides Sparse map of display keys explicitly set
	 *                                                      by the block or shortcode.
	 * @return string HTML output.
	 */
	public function render_question_review( $attempt, $instance_overrides = [] ) {
		// Resolve display options if not already set from a prior render_results() call.
		if ( empty( $this->display ) ) {
			$quiz = $attempt ? $attempt->get_quiz() : null;
			if ( $quiz ) {
				$this->display = $quiz->resolve_all_display_options( $instance_overrides );
			} else {
				$this->display = wp_parse_args( $instance_overrides, $this->get_default_results_display_options() );
			}
		}

		// Check if question review should be displayed.
		if ( ! $this->display['show_question_review'] ) {
			return '';
		}

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

		// Get display density class
		$density       = $quiz->get_effective_display_density();
		$density_class = 'condensed' === $density ? 'ppq-quiz--condensed' : '';

		// Build output
		ob_start();
		?>
		<div id="ppq-question-review" class="ppq-question-review-container <?php echo esc_attr( $theme_class ); ?> <?php echo esc_attr( $density_class ); ?>">
			<h2 class="ppq-review-title"><?php esc_html_e( 'Question Review', 'pressprimer-quiz' ); ?></h2>

			<?php foreach ( $items as $index => $item ) : ?>
				<?php $this->render_single_question_review( $item, $index + 1, count( $items ), $quiz, $show_correct_answers, $attempt ); ?>
			<?php endforeach; ?>

			<?php
			/**
			 * Fires after the question review section.
			 *
			 * Premium addons can use this to add additional review content,
			 * such as detailed analytics or learning recommendations.
			 *
			 * @since 2.0.0
			 *
			 * @param PressPrimer_Quiz_Attempt $attempt The attempt object.
			 * @param PressPrimer_Quiz_Quiz    $quiz    The quiz object.
			 */
			do_action( 'pressprimer_quiz_results_after_review', $attempt, $quiz );
			?>
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
	 * @param PressPrimer_Quiz_Attempt      $attempt Parent attempt (carries the stored scoring mode).
	 */
	private function render_single_question_review( $item, $current_num, $total_num, $quiz, $show_correct_answers, $attempt = null ) {
		// Get question revision (locked at attempt time)
		$revision = $item->get_question_revision();
		if ( ! $revision ) {
			return;
		}

		$question = $item->get_question();
		if ( ! $question ) {
			return;
		}

		$answers          = $this->resolve_displayed_answers( $revision->get_answers(), $item );
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

				<?php if ( $quiz->show_points ) : ?>
					<?php
					$earned     = (float) ( $item->score_points ?? 0 );
					$max        = (float) $question->max_points;
					$decimals   = ( $max === floor( $max ) && $earned === floor( $earned ) ) ? 0 : 1;
					$earned_fmt = number_format_i18n( $earned, $decimals );
					$max_fmt    = number_format_i18n( $max, $decimals );

					if ( $earned >= $max && $max > 0 ) {
						$points_class = 'ppq-points-full';
					} elseif ( $earned > 0 ) {
						$points_class = 'ppq-points-partial';
					} else {
						$points_class = 'ppq-points-zero';
					}
					?>
					<span class="ppq-review-points <?php echo esc_attr( $points_class ); ?>">
						<?php
						if ( 1.0 === $max ) {
							printf(
								/* translators: 1: earned points, 2: max points */
								esc_html__( '%1$s/%2$s point', 'pressprimer-quiz' ),
								esc_html( $earned_fmt ),
								esc_html( $max_fmt )
							);
						} else {
							printf(
								/* translators: 1: earned points, 2: max points */
								esc_html__( '%1$s/%2$s points', 'pressprimer-quiz' ),
								esc_html( $earned_fmt ),
								esc_html( $max_fmt )
							);
						}
						?>
					</span>
				<?php elseif ( $item->score_points && ! $item->is_correct ) : ?>
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

			<?php if ( $this->display['show_scoring_explanations'] ?? true ) : ?>
				<?php $this->render_score_explanation( $attempt, $item, $question, $answers, $selected_answers, $show_correct_answers ); ?>
			<?php endif; ?>

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
					<?php echo pressprimer_quiz_render_answer_html( $answer['text'] ); ?>
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
	 * Render the per-question scoring explanation line.
	 *
	 * Shows, beneath a question's answers, how its score was produced. For
	 * multiple-answer questions it prints the resolved scoring mode label and
	 * the worked arithmetic; the numbers are derived entirely from stored
	 * attempt data (subset-aware, mirroring the 2.3 scoring path) so they match
	 * the recorded score exactly. For single-answer questions it prints a
	 * uniform correct/incorrect points line.
	 *
	 * Two cases degrade to a numeric points-only line: attempts predating 3.0
	 * (no stored mode — never guess a historical mode) and questions whose
	 * correctness must stay hidden from the student (showing correct/wrong
	 * counts would leak answer information). The visibility gate is evaluated
	 * here, server-side, so counts never reach the page source when hidden
	 * (feature 005, FR-002, FR-003, FR-005).
	 *
	 * @since 3.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt|null $attempt              Parent attempt (carries the stored scoring mode).
	 * @param PressPrimer_Quiz_Attempt_Item $item                 Attempt item.
	 * @param PressPrimer_Quiz_Question     $question             Question object.
	 * @param array                         $answers              Displayed answers (subset-aware), keyed by revision index.
	 * @param array                         $selected_answers     Selected answer indices.
	 * @param bool                          $show_correct_answers Whether correctness may be revealed to the student.
	 */
	private function render_score_explanation( $attempt, $item, $question, $answers, $selected_answers, $show_correct_answers ) {
		$score = (float) ( $item->score_points ?? 0 );
		$max   = (float) $question->max_points;

		$is_ma       = in_array( $question->type, array( 'multiple_answer', 'ma' ), true );
		$stored_mode = ( $attempt && isset( $attempt->ma_scoring_mode ) ) ? $attempt->ma_scoring_mode : null;

		$mode_label = '';
		$formula    = '';
		$simple     = '';

		if ( ! $show_correct_answers ) {
			// Correctness hidden: points only — no counts, no labels (FR-005).
			$simple = $this->format_points_only( $score, $max );
		} elseif ( $is_ma ) {
			// Multiple-answer: mode label + worked arithmetic from stored data.
			if ( class_exists( 'PressPrimer_Quiz_Scoring_Copy' )
				&& PressPrimer_Quiz_Scoring_Copy::is_mode( $stored_mode ) ) {
				$counts = $this->get_ma_count_data( $answers, $selected_answers );
				if ( $counts['total_correct'] > 0 ) {
					$mode_label = PressPrimer_Quiz_Scoring_Copy::get_label( $stored_mode );
					$formula    = $this->build_ma_formula_line( $stored_mode, $counts, $score, $max );
				}
			}

			// Pre-3.0 (NULL mode), unrecognized mode, or underivable counts:
			// numeric only — never guess a historical mode (FR-002).
			if ( '' === $formula ) {
				$simple = $this->format_points_only( $score, $max );
			}
		} else {
			// Single-answer (MC/TF): uniform correct/incorrect line (FR-003).
			$simple = $this->format_mc_tf_line( (bool) $item->is_correct, $score, $max );
		}

		$html = '<div class="ppq-score-explanation">';
		if ( '' !== $formula ) {
			$html .= '<div class="ppq-score-explanation-mode">'
				/* translators: %s: scoring mode label (e.g. "Right Minus Wrong"). */
				. esc_html( sprintf( __( 'Scoring — %s', 'pressprimer-quiz' ), $mode_label ) )
				. '</div>';
			$html .= '<div class="ppq-score-explanation-formula">' . esc_html( $formula ) . '</div>';
		} else {
			$html .= '<div class="ppq-score-explanation-line">' . esc_html( $simple ) . '</div>';
		}
		$html .= '</div>';

		/**
		 * Filters the per-question scoring explanation HTML.
		 *
		 * Addons can append to or replace the explanation — e.g., School's
		 * curve-grading disclosure or CBM explanations. Returned markup passes
		 * through the renderer's kses treatment before output.
		 *
		 * @since 3.0.0
		 *
		 * @param string                        $html     Default explanation HTML.
		 * @param PressPrimer_Quiz_Attempt_Item $item     The attempt item.
		 * @param PressPrimer_Quiz_Question     $question The question object.
		 * @param string|null                   $mode     Stored scoring mode (null for pre-3.0 attempts / non-MA).
		 */
		$html = apply_filters( 'pressprimer_quiz_score_explanation', $html, $item, $question, $stored_mode );

		$allowed = array(
			'div'    => array( 'class' => true ),
			'span'   => array( 'class' => true ),
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
		);

		echo wp_kses( $html, $allowed );
	}

	/**
	 * Derive subset-aware correct/incorrect selection counts for an MA question.
	 *
	 * Mirrors the scoring path (PressPrimer_Quiz_Scoring_Service::score_ma):
	 * correct indices are taken from the answers actually shown (subset-aware
	 * per the 2.3 random-distractor behavior), and selections outside the shown
	 * subset are dropped — so the displayed numbers match the recorded score
	 * (feature 005, TR-004).
	 *
	 * @since 3.0.0
	 *
	 * @param array $answers          Displayed answers keyed by revision index, each with an is_correct flag.
	 * @param array $selected_answers Selected answer indices.
	 * @return array { total_correct, correct_selected, incorrect_selected }.
	 */
	private function get_ma_count_data( $answers, $selected_answers ) {
		$correct_indices = array();
		foreach ( $answers as $idx => $answer ) {
			if ( ! empty( $answer['is_correct'] ) ) {
				$correct_indices[] = (int) $idx;
			}
		}

		$shown_keys = array_map( 'intval', array_keys( $answers ) );
		$selected   = array_values(
			array_intersect( array_map( 'intval', (array) $selected_answers ), $shown_keys )
		);

		return array(
			'total_correct'      => count( $correct_indices ),
			'correct_selected'   => count( array_intersect( $selected, $correct_indices ) ),
			'incorrect_selected' => count( array_diff( $selected, $correct_indices ) ),
		);
	}

	/**
	 * Build the worked-arithmetic line for an MA question from stored counts.
	 *
	 * Fills the mode's formula template (from the shared copy provider) with
	 * i18n-formatted numbers. Returns '' for an unknown mode or missing
	 * template so the caller can fall back to a numeric-only line.
	 *
	 * @since 3.0.0
	 *
	 * @param string $mode   Stored scoring mode key.
	 * @param array  $counts Counts from get_ma_count_data().
	 * @param float  $score  Points earned (stored).
	 * @param float  $max    Maximum points.
	 * @return string Filled formula line, or '' if unavailable.
	 */
	private function build_ma_formula_line( $mode, $counts, $score, $max ) {
		if ( ! class_exists( 'PressPrimer_Quiz_Scoring_Copy' ) ) {
			return '';
		}

		$template = PressPrimer_Quiz_Scoring_Copy::get_formula_template( $mode );
		if ( '' === $template ) {
			return '';
		}

		$right = (int) $counts['correct_selected'];
		$wrong = (int) $counts['incorrect_selected'];
		$total = (int) $counts['total_correct'];
		$net   = max( 0, $right - $wrong );

		$right_fmt = number_format_i18n( $right );
		$wrong_fmt = number_format_i18n( $wrong );
		$total_fmt = number_format_i18n( $total );
		$net_fmt   = number_format_i18n( $net );
		$score_fmt = $this->format_score_number( $score );
		$max_fmt   = $this->format_score_number( $max );

		switch ( $mode ) {
			case 'right_minus_wrong':
				$args = array( $right_fmt, $wrong_fmt, $net_fmt, $total_fmt, $score_fmt, $max_fmt );
				break;

			case 'proportional':
				$args = array( $right_fmt, $total_fmt, $score_fmt, $max_fmt );
				break;

			case 'partial_no_wrong':
			case 'all_or_nothing':
				$args = array( $right_fmt, $total_fmt, $wrong_fmt, $score_fmt, $max_fmt );
				break;

			default:
				return '';
		}

		return vsprintf( $template, $args );
	}

	/**
	 * Format a numeric points-only line ("{score} of {max} point(s)").
	 *
	 * @since 3.0.0
	 *
	 * @param float $score Points earned.
	 * @param float $max   Maximum points.
	 * @return string Localized points line.
	 */
	private function format_points_only( $score, $max ) {
		$score_fmt = $this->format_score_number( $score );
		$max_fmt   = $this->format_score_number( $max );

		if ( 1.0 === (float) $max ) {
			/* translators: 1: points earned, 2: maximum points. */
			return sprintf( __( '%1$s of %2$s point', 'pressprimer-quiz' ), $score_fmt, $max_fmt );
		}

		/* translators: 1: points earned, 2: maximum points. */
		return sprintf( __( '%1$s of %2$s points', 'pressprimer-quiz' ), $score_fmt, $max_fmt );
	}

	/**
	 * Format the uniform correct/incorrect line for a single-answer question.
	 *
	 * @since 3.0.0
	 *
	 * @param bool  $is_correct Whether the response was correct.
	 * @param float $score      Points earned.
	 * @param float $max        Maximum points.
	 * @return string Localized line, e.g. "Correct — 1 of 1 point".
	 */
	private function format_mc_tf_line( $is_correct, $score, $max ) {
		$points = $this->format_points_only( $score, $max );

		if ( $is_correct ) {
			/* translators: %s: points summary, e.g. "1 of 1 point". */
			return sprintf( __( 'Correct — %s', 'pressprimer-quiz' ), $points );
		}

		/* translators: %s: points summary, e.g. "0 of 1 point". */
		return sprintf( __( 'Incorrect — %s', 'pressprimer-quiz' ), $points );
	}

	/**
	 * Format a points value for display: whole numbers without decimals,
	 * fractional values to two places (matching the stored 2-decimal rounding).
	 *
	 * @since 3.0.0
	 *
	 * @param float $value Numeric value.
	 * @return string Localized number.
	 */
	private function format_score_number( $value ) {
		$value = (float) $value;

		if ( $value === floor( $value ) ) {
			return number_format_i18n( $value, 0 );
		}

		return number_format_i18n( $value, 2 );
	}

	/**
	 * Resolve the answers actually shown to the student for this attempt item.
	 *
	 * When a quiz uses random distractor selection (free 2.3
	 * `max_answers_per_question`), the attempt item's `answer_order_json`
	 * stores the indices and order of the answers actually displayed during
	 * the attempt. Reviewing the full revision set on the results page would
	 * surface answers the student never saw, which is confusing and
	 * pedagogically wrong (the score is subset-aware; the review should be
	 * too). The student also expects to see the answers in the same order
	 * they were presented during the attempt.
	 *
	 * When `answer_order_json` is empty, NULL, or malformed, this falls back
	 * to the full answer set so pre-2.3 attempts and attempts on uncapped
	 * quizzes render exactly as before.
	 *
	 * The original answer index is preserved as the array key so downstream
	 * callers (selected-answer matching by index, is_correct lookups, per-
	 * answer feedback) keep working without modification.
	 *
	 * @since 2.3.0
	 *
	 * @param array                         $all_answers Full answer set from the revision.
	 * @param PressPrimer_Quiz_Attempt_Item $item        Attempt item.
	 * @return array Subset of $all_answers preserving the shown order; keys
	 *               are the original revision indices.
	 */
	private function resolve_displayed_answers( $all_answers, $item ) {
		if ( empty( $item->answer_order_json ) ) {
			return $all_answers;
		}

		$shown_indices = json_decode( $item->answer_order_json, true );
		if ( ! is_array( $shown_indices ) || empty( $shown_indices ) ) {
			return $all_answers;
		}

		$displayed = array();
		foreach ( $shown_indices as $shown_index ) {
			$shown_index = (int) $shown_index;
			if ( isset( $all_answers[ $shown_index ] ) ) {
				$displayed[ $shown_index ] = $all_answers[ $shown_index ];
			}
		}

		// Defensive fallback: if the decode yielded zero usable matches
		// (e.g., stored against a different revision), show the full set so
		// the student sees something rather than nothing.
		if ( empty( $displayed ) ) {
			return $all_answers;
		}

		return $displayed;
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
		return add_query_arg( 'pressprimer_quiz_retake', '1', $url );
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
