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
class PPQ_Scoring_Service {

	/**
	 * Score a single question response
	 *
	 * Routes to appropriate scoring method based on question type.
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Question          $question Question object.
	 * @param PPQ_Question_Revision $revision Question revision.
	 * @param array                 $selected_answers Array of selected answer indices.
	 * @return array Scoring result with is_correct, score, max_points.
	 */
	public function score_response( $question, $revision, array $selected_answers ) {
		$answers = $revision->get_answers();

		// Get correct answer indices
		$correct_indices = [];
		foreach ( $answers as $index => $answer ) {
			if ( $answer['is_correct'] ) {
				$correct_indices[] = $index;
			}
		}

		// Route to appropriate scoring method
		switch ( $question->type ) {
			case 'multiple_choice':
			case 'mc':
				return $this->score_mc( $correct_indices, $selected_answers, $question->max_points );

			case 'multiple_answer':
			case 'ma':
				return $this->score_ma( $correct_indices, $selected_answers, $question->max_points );

			case 'true_false':
			case 'tf':
				return $this->score_tf( $correct_indices, $selected_answers, $question->max_points );

			default:
				return [
					'is_correct' => false,
					'score'      => 0,
					'max_points' => $question->max_points,
				];
		}
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
	 * Score multiple answer question with partial credit
	 *
	 * Formula: (correct_selected - incorrect_selected) / total_correct * max_points
	 * Minimum score is 0 (no negative scores).
	 *
	 * Examples:
	 * - 3 correct answers, selected 2 correct + 0 incorrect = 2/3 credit
	 * - 3 correct answers, selected 2 correct + 1 incorrect = 1/3 credit
	 * - 3 correct answers, selected 1 correct + 2 incorrect = -1/3 = 0 credit
	 *
	 * @since 1.0.0
	 *
	 * @param array $correct_indices Array of correct answer indices.
	 * @param array $selected_answers Array of selected answer indices.
	 * @param float $max_points Maximum points possible.
	 * @return array Scoring result.
	 */
	public function score_ma( array $correct_indices, array $selected_answers, float $max_points ) {
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
		$correct_selected = count( array_intersect( $selected_answers, $correct_indices ) );
		$incorrect_selected = count( array_diff( $selected_answers, $correct_indices ) );

		// Determine if completely correct
		$is_correct = $correct_selected === $total_correct && 0 === $incorrect_selected;

		// Calculate proportional score with penalty for incorrect selections
		$raw_score = ( $correct_selected - $incorrect_selected ) / $total_correct;
		$score = max( 0, $raw_score ) * $max_points;

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
		$attempt = PPQ_Attempt::get( $attempt_id );

		if ( ! $attempt ) {
			return new WP_Error(
				'ppq_attempt_not_found',
				__( 'Attempt not found.', 'pressprimer-quiz' )
			);
		}

		// Get all attempt items
		$items = $attempt->get_items( true ); // Force refresh

		$total_score = 0;
		$total_max = 0;
		$correct_count = 0;
		$total_count = count( $items );

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

			// Score the response
			$result = $this->score_response( $question, $revision, $selected );

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
				$correct_count++;
			}
		}

		// Calculate percentage
		$score_percent = $total_max > 0 ? ( $total_score / $total_max ) * 100 : 0;

		// Get quiz to determine if passed
		$quiz = $attempt->get_quiz();
		$passed = $score_percent >= $quiz->pass_percent;

		// Update attempt with scores
		$attempt->score_points = round( $total_score, 2 );
		$attempt->max_points = round( $total_max, 2 );
		$attempt->score_percent = round( $score_percent, 2 );
		$attempt->passed = $passed ? 1 : 0;
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
	 * @return PPQ_Scoring_Service Service instance.
	 */
	public static function instance() {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}
