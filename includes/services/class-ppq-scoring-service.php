<?php
/**
 * Scoring Service
 *
 * Handles scoring logic for quiz attempts and individual questions.
 * Supports partial credit for multiple answer questions.
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scoring Service class
 *
 * Provides scoring algorithms for different question types
 * and calculates total attempt scores.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Scoring_Service {

	/**
	 * Score a single question response
	 *
	 * Routes to the appropriate per-type scoring method and then applies the
	 * unified pressprimer_quiz_question_score filter so addons can implement
	 * custom rubrics without replacing the scoring service.
	 *
	 * @since 1.0.0
	 * @since 2.3.0 Added $quiz parameter for MA scoring mode resolution and
	 *              the unified pressprimer_quiz_question_score filter.
	 * @since 2.3.0 Added $shown_indices for subset-aware scoring under
	 *              max_answers_per_question. When provided, correct_indices
	 *              and selected_answers are both intersected with the shown
	 *              subset so the student is scored only on answers they
	 *              could actually see (and select).
	 *
	 * @param PressPrimer_Quiz_Question          $question         Question object.
	 * @param PressPrimer_Quiz_Question_Revision $revision         Question revision.
	 * @param array                              $selected_answers Array of selected answer indices.
	 * @param PressPrimer_Quiz_Quiz|null         $quiz             Optional. Quiz the question is being scored under.
	 *                                                              When provided, the quiz's resolved MA scoring mode is used
	 *                                                              for multiple-answer questions. When omitted, the site default
	 *                                                              applies.
	 * @param array|null                         $shown_indices    Optional. Integer indices that were actually presented to the
	 *                                                              student for this attempt item. NULL means "all answers were
	 *                                                              shown" (the pre-v2.3 default). When provided, correct_indices
	 *                                                              and selected_answers are filtered against this subset before
	 *                                                              the per-type scoring method runs.
	 * @return array Scoring result with is_correct, score, max_points.
	 */
	public function score_response( $question, $revision, array $selected_answers, $quiz = null, $shown_indices = null ) {
		$answers = $revision->get_answers();

		// Ensure selected_answers are integers for consistent comparison
		$selected_answers = array_map( 'intval', $selected_answers );

		// Get every correct index from the revision (the full set).
		$all_correct_indices = array();
		foreach ( $answers as $index => $answer ) {
			if ( ! empty( $answer['is_correct'] ) ) {
				$all_correct_indices[] = (int) $index;
			}
		}

		// Subset-aware scoring (v2.3 random distractors). When the attempt
		// item only showed a subset of answers, the student could not have
		// missed (or wrongly selected) anything outside that subset. Reduce
		// correct_indices to the corrects that were shown, and defensively
		// drop any submitted indices that were not in the stored subset so
		// a tampered submission cannot claim credit for answers off-screen.
		// NULL preserves the pre-v2.3 behavior where every index counts.
		if ( null === $shown_indices ) {
			$correct_indices = $all_correct_indices;
		} else {
			$shown_indices    = array_map( 'intval', $shown_indices );
			$correct_indices  = array_values( array_intersect( $all_correct_indices, $shown_indices ) );
			$selected_answers = array_values( array_intersect( $selected_answers, $shown_indices ) );
		}

		// Route to appropriate scoring method
		switch ( $question->type ) {
			case 'multiple_choice':
			case 'mc':
				$result = $this->score_mc( $correct_indices, $selected_answers, $question->max_points );
				break;

			case 'multiple_answer':
			case 'ma':
				$mode   = $this->resolve_ma_scoring_mode( $quiz );
				$result = $this->score_ma( $correct_indices, $selected_answers, $question->max_points, $mode );
				break;

			case 'true_false':
			case 'tf':
				$result = $this->score_tf( $correct_indices, $selected_answers, $question->max_points );
				break;

			default:
				$result = [
					'is_correct' => false,
					'score'      => 0,
					'max_points' => $question->max_points,
				];
		}

		/**
		 * Filters the per-question score result before it is persisted.
		 *
		 * Use this filter to implement custom scoring rules without replacing
		 * the scoring service. Return the original $result unchanged to leave
		 * scoring unaffected.
		 *
		 * Replaces three previously-documented but never-implemented filters:
		 * pressprimer_quiz_scoring_mc, pressprimer_quiz_scoring_ma, and
		 * pressprimer_quiz_scoring_tf.
		 *
		 * @since 2.3.0
		 *
		 * @param array                              $result {
		 *     The scoring result.
		 *
		 *     @type bool  $is_correct     Whether the response is fully correct.
		 *     @type float $score          Points earned.
		 *     @type float $max_points     Maximum points possible for this question.
		 *     @type bool  $partial_credit (MA only) Whether the score reflects partial credit.
		 * }
		 * @param PressPrimer_Quiz_Question          $question         The question object.
		 * @param PressPrimer_Quiz_Question_Revision $revision         The revision scored against.
		 * @param array                              $selected_answers Integer indices the student selected.
		 * @param array                              $correct_indices  Integer indices marked correct on the revision.
		 */
		$filtered = apply_filters(
			'pressprimer_quiz_question_score',
			$result,
			$question,
			$revision,
			$selected_answers,
			$correct_indices
		);

		// Validate filter return. A buggy callback must not corrupt scoring,
		// so fall back to the pre-filter result when required keys are missing.
		if ( ! is_array( $filtered )
			|| ! array_key_exists( 'is_correct', $filtered )
			|| ! array_key_exists( 'score', $filtered )
			|| ! array_key_exists( 'max_points', $filtered ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Defensive logging for malformed filter output.
			error_log( 'PressPrimer Quiz: pressprimer_quiz_question_score filter returned malformed result; using pre-filter result.' );
			return $result;
		}

		// Clamp the filtered score to a defensive range so a buggy filter
		// cannot produce absurd scores. Upper bound is max_points * 2 to
		// allow modest bonus scoring while still rejecting nonsense values.
		$max_clamp         = max( 0.0, (float) $result['max_points'] * 2 );
		$filtered['score'] = max( 0.0, min( (float) $filtered['score'], $max_clamp ) );

		return $filtered;
	}

	/**
	 * Resolve the multiple-answer scoring mode for a quiz context.
	 *
	 * Returns the quiz's resolved scoring mode when a quiz is available,
	 * otherwise the site-wide default. Always returns a non-null string.
	 *
	 * @since 2.3.0
	 *
	 * @param PressPrimer_Quiz_Quiz|null $quiz Quiz the question is being scored under, or null.
	 * @return string One of: right_minus_wrong, proportional, partial_no_wrong, all_or_nothing.
	 */
	private function resolve_ma_scoring_mode( $quiz ) {
		if ( $quiz instanceof PressPrimer_Quiz_Quiz ) {
			return $quiz->get_resolved_ma_scoring_mode();
		}

		return get_option( 'pressprimer_quiz_default_ma_scoring', 'right_minus_wrong' );
	}

	/**
	 * Score multiple choice question
	 *
	 * All or nothing scoring - must select exactly the one correct answer.
	 *
	 * @since 1.0.0
	 *
	 * @param array $correct_indices Array of correct answer indices.
	 * @param array $selected_answers Array of selected answer indices.
	 * @param float $max_points Maximum points possible.
	 * @return array Scoring result.
	 */
	public function score_mc( array $correct_indices, array $selected_answers, float $max_points ) {
		// Must select exactly one answer
		if ( count( $selected_answers ) !== 1 ) {
			return [
				'is_correct' => false,
				'score'      => 0,
				'max_points' => $max_points,
			];
		}

		// Must be the correct one
		$is_correct = in_array( $selected_answers[0], $correct_indices, true );

		return [
			'is_correct' => $is_correct,
			'score'      => $is_correct ? $max_points : 0,
			'max_points' => $max_points,
		];
	}

	/**
	 * Score a multiple-answer question with the specified mode
	 *
	 * Four modes are supported, spanning lenient to strict rubrics:
	 *
	 * - right_minus_wrong (default): wrong selections cancel out correct
	 *   selections, with a floor at zero. Formula:
	 *   max(0, (correct_selected - incorrect_selected) / total_correct) * max_points
	 * - proportional: each correct selection earns proportional credit,
	 *   wrong selections are ignored.
	 * - partial_no_wrong: proportional credit, but any wrong selection
	 *   results in zero for the question.
	 * - all_or_nothing: full credit only when every correct is selected and
	 *   no wrong is selected; otherwise zero.
	 *
	 * is_correct is true only on exact match (every correct selected, no
	 * wrong selected) regardless of mode. partial_credit is true when
	 * !is_correct && score > 0.
	 *
	 * @since 1.0.0
	 * @since 2.3.0 Added $mode parameter and four-mode branching.
	 *
	 * @param array  $correct_indices  Array of correct answer indices.
	 * @param array  $selected_answers Array of selected answer indices.
	 * @param float  $max_points       Maximum points possible.
	 * @param string $mode             One of: right_minus_wrong, proportional, partial_no_wrong, all_or_nothing.
	 *                                  Unknown modes fall back to right_minus_wrong.
	 * @return array Scoring result.
	 */
	public function score_ma( array $correct_indices, array $selected_answers, float $max_points, string $mode = 'right_minus_wrong' ) {
		$total_correct = count( $correct_indices );

		// Edge case: No correct answers (shouldn't happen, but handle gracefully)
		if ( 0 === $total_correct ) {
			return [
				'is_correct'     => empty( $selected_answers ),
				'score'          => empty( $selected_answers ) ? $max_points : 0,
				'max_points'     => $max_points,
				'partial_credit' => false,
			];
		}

		// Calculate correct and incorrect selections
		$correct_selected   = count( array_intersect( $selected_answers, $correct_indices ) );
		$incorrect_selected = count( array_diff( $selected_answers, $correct_indices ) );

		// Determine if completely correct (independent of mode)
		$is_correct = $correct_selected === $total_correct && 0 === $incorrect_selected;

		switch ( $mode ) {
			case 'all_or_nothing':
				$score = $is_correct ? $max_points : 0;
				break;

			case 'partial_no_wrong':
				if ( $incorrect_selected > 0 ) {
					$score = 0;
				} else {
					$score = ( $correct_selected / $total_correct ) * $max_points;
				}
				break;

			case 'proportional':
				$score = ( $correct_selected / $total_correct ) * $max_points;
				break;

			case 'right_minus_wrong':
			default:
				$raw_score = ( $correct_selected - $incorrect_selected ) / $total_correct;
				$score     = max( 0, $raw_score ) * $max_points;
				break;
		}

		return [
			'is_correct'     => $is_correct,
			'score'          => round( $score, 2 ),
			'max_points'     => $max_points,
			'partial_credit' => ! $is_correct && $score > 0,
		];
	}

	/**
	 * Score true/false question
	 *
	 * All or nothing scoring - must select exactly the one correct answer.
	 * Identical to multiple choice scoring.
	 *
	 * @since 1.0.0
	 *
	 * @param array $correct_indices Array of correct answer indices.
	 * @param array $selected_answers Array of selected answer indices.
	 * @param float $max_points Maximum points possible.
	 * @return array Scoring result.
	 */
	public function score_tf( array $correct_indices, array $selected_answers, float $max_points ) {
		// True/False uses same logic as multiple choice
		return $this->score_mc( $correct_indices, $selected_answers, $max_points );
	}

	/**
	 * Calculate total score for an attempt
	 *
	 * Scores all items in an attempt and updates attempt and item records.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attempt_id Attempt ID.
	 * @return array|WP_Error Score summary or error.
	 */
	public function calculate_attempt_score( int $attempt_id ) {
		$attempt = PressPrimer_Quiz_Attempt::get( $attempt_id );

		if ( ! $attempt ) {
			return new WP_Error(
				'ppq_attempt_not_found',
				__( 'Attempt not found.', 'pressprimer-quiz' )
			);
		}

		// Load quiz upfront so we can resolve the MA scoring mode once and
		// thread the quiz into every score_response() call.
		$quiz = $attempt->get_quiz();

		// Get all attempt items
		$items = $attempt->get_items( true ); // Force refresh

		$total_score   = 0;
		$total_max     = 0;
		$correct_count = 0;
		$total_count   = count( $items );

		foreach ( $items as $item ) {
			// Get question revision
			$revision = $item->get_question_revision();
			if ( ! $revision ) {
				continue;
			}

			// Get question
			$question = $item->get_question();
			if ( ! $question ) {
				continue;
			}

			$total_max += $question->max_points;

			// Get selected answers
			$selected = $item->get_selected_answers();

			// Read the per-attempt-item answer_order so the scoring service
			// can intersect correct_indices and selected_answers against the
			// subset actually shown (v2.3 random distractor support). Returns
			// null for pre-v2.3 attempts, preserving the original scoring.
			$shown_indices = $item->get_answer_order();

			// Score the response
			$result = $this->score_response( $question, $revision, $selected, $quiz, $shown_indices );

			// Update attempt item with score
			global $wpdb;
			$items_table = $wpdb->prefix . 'ppq_attempt_items';

			$wpdb->update(
				$items_table,
				[
					'is_correct'   => $result['is_correct'] ? 1 : 0,
					'score_points' => $result['score'],
				],
				[ 'id' => $item->id ],
				[ '%d', '%f' ],
				[ '%d' ]
			);

			$total_score += $result['score'];

			if ( $result['is_correct'] ) {
				++$correct_count;
			}
		}

		/**
		 * Filters the scoring totals before percentage calculation.
		 *
		 * Addons can adjust the denominator — for example, the Enterprise addon
		 * excludes questions that were skipped by branching rules so students
		 * are scored only on the questions they were actually presented.
		 *
		 * @since 2.2.0
		 *
		 * @param array                    $totals  {
		 *     Scoring totals.
		 *
		 *     @type float $total_score   Total points earned.
		 *     @type float $total_max     Maximum possible points (denominator).
		 *     @type int   $correct_count Number of correct answers.
		 *     @type int   $total_count   Total number of scored items.
		 * }
		 * @param PressPrimer_Quiz_Attempt $attempt The attempt being scored.
		 * @param array                    $items   The attempt items.
		 */
		$totals = apply_filters(
			'pressprimer_quiz_scoring_totals',
			array(
				'total_score'   => $total_score,
				'total_max'     => $total_max,
				'correct_count' => $correct_count,
				'total_count'   => $total_count,
			),
			$attempt,
			$items
		);

		$total_score   = (float) $totals['total_score'];
		$total_max     = (float) $totals['total_max'];
		$correct_count = (int) $totals['correct_count'];
		$total_count   = (int) $totals['total_count'];

		// Calculate percentage
		$score_percent = $total_max > 0 ? ( $total_score / $total_max ) * 100 : 0;

		// Determine pass/fail (quiz was loaded upfront)
		$passed = $score_percent >= $quiz->pass_percent;

		// Update attempt with scores
		$attempt->score_points  = round( $total_score, 2 );
		$attempt->max_points    = round( $total_max, 2 );
		$attempt->score_percent = round( $score_percent, 2 );
		$attempt->passed        = $passed ? 1 : 0;
		$attempt->save();

		return [
			'score_points'  => $attempt->score_points,
			'max_points'    => $attempt->max_points,
			'score_percent' => $attempt->score_percent,
			'passed'        => $passed,
			'correct_count' => $correct_count,
			'total_count'   => $total_count,
		];
	}

	/**
	 * Get singleton instance
	 *
	 * @since 1.0.0
	 *
	 * @return PressPrimer_Quiz_Scoring_Service Service instance.
	 */
	public static function instance() {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}
