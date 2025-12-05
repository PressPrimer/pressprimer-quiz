<?php
/**
 * Uncanny Automator Helpers
 *
 * Provides shared functionality for Uncanny Automator triggers and actions.
 *
 * @package PressPrimer_Quiz
 * @subpackage Integrations
 * @since 1.0.0
 */

namespace Jeero\PressPrimerQuiz\Integrations\UncannyAutomator;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class for PressPrimer Quiz Automator integration
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Automator_Helpers {

	/**
	 * Get all published quizzes for dropdown options
	 *
	 * @since 1.0.0
	 *
	 * @return array Quiz options array with 'text' and 'value' keys.
	 */
	public function get_quiz_options() {
		$options = array();

		if ( ! class_exists( 'PressPrimer_Quiz_Quiz' ) ) {
			return $options;
		}

		$quizzes = \PressPrimer_Quiz_Quiz::find(
			array(
				'where'    => array( 'status' => 'published' ),
				'order_by' => 'title',
				'order'    => 'ASC',
				'limit'    => 999,
			)
		);

		foreach ( $quizzes as $quiz ) {
			$options[] = array(
				'text'  => $quiz->title,
				'value' => (string) $quiz->id,
			);
		}

		return $options;
	}

	/**
	 * Get quiz data for tokens from attempt and quiz objects
	 *
	 * @since 1.0.0
	 *
	 * @param object $attempt PressPrimer_Quiz_Attempt object.
	 * @param object $quiz    PressPrimer_Quiz_Quiz object.
	 * @return array Token data.
	 */
	public function get_quiz_token_data_from_objects( $attempt, $quiz ) {
		$data = array(
			'QUIZ_ID'                    => '',
			'QUIZ_TITLE'                 => '',
			'QUIZ_URL'                   => '',
			'ATTEMPT_ID'                 => '',
			'SCORE'                      => '',
			'SCORE_PERCENT'              => '',
			'PASSING_SCORE'              => '',
			'POINTS_EARNED'              => '',
			'POINTS_POSSIBLE'            => '',
			'CORRECT_ANSWERS'            => '',
			'INCORRECT_ANSWERS'          => '',
			'TOTAL_QUESTIONS'            => '',
			'TIME_SPENT'                 => '',
			'TIME_SPENT_FORMATTED'       => '',
			'PASSED'                     => '',
			'QUESTIONS_AND_ANSWERS'      => '',
			'QUESTIONS_AND_ANSWERS_HTML' => '',
		);

		// Get quiz data.
		if ( $quiz ) {
			$data['QUIZ_ID']       = $quiz->id ?? '';
			$data['QUIZ_TITLE']    = $quiz->title ?? '';
			$data['QUIZ_URL']      = $this->get_quiz_url( $quiz->id ?? 0 );
			$data['PASSING_SCORE'] = $quiz->passing_score ?? '';
		}

		// Get attempt data.
		if ( $attempt ) {
			$data['ATTEMPT_ID']      = $attempt->id ?? '';
			$data['SCORE']           = round( $attempt->score_percent ?? 0 );
			$data['SCORE_PERCENT']   = round( $attempt->score_percent ?? 0 ) . '%';
			$data['POINTS_EARNED']   = $attempt->score_points ?? 0;
			$data['POINTS_POSSIBLE'] = $attempt->max_points ?? 0;
			$data['PASSED']          = ! empty( $attempt->passed ) ? __( 'Yes', 'pressprimer-quiz' ) : __( 'No', 'pressprimer-quiz' );

			// Get attempt items for counting and Q&A.
			$items           = method_exists( $attempt, 'get_items' ) ? $attempt->get_items() : array();
			$correct_count   = 0;
			$total_questions = count( $items );

			foreach ( $items as $item ) {
				if ( ! empty( $item->is_correct ) ) {
					++$correct_count;
				}
			}

			$data['CORRECT_ANSWERS']   = $correct_count;
			$data['INCORRECT_ANSWERS'] = $total_questions - $correct_count;
			$data['TOTAL_QUESTIONS']   = $total_questions;

			// Time spent (elapsed_ms is in milliseconds, convert to seconds).
			$time_spent_seconds           = isset( $attempt->elapsed_ms ) ? round( $attempt->elapsed_ms / 1000 ) : 0;
			$data['TIME_SPENT']           = $time_spent_seconds;
			$data['TIME_SPENT_FORMATTED'] = $this->format_time( $time_spent_seconds );

			// Questions and answers.
			$qa_data                            = $this->get_questions_and_answers( $items );
			$data['QUESTIONS_AND_ANSWERS']      = $qa_data['text'];
			$data['QUESTIONS_AND_ANSWERS_HTML'] = $qa_data['html'];
		}

		return $data;
	}

	/**
	 * Get quiz URL
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return string Quiz URL or empty string.
	 */
	private function get_quiz_url( $quiz_id ) {
		// Try to find a page with the quiz shortcode.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Quiz URL lookup from post content
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_status = 'publish'
				AND post_content LIKE %s
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table name
				'%[ppq_quiz id="' . $quiz_id . '"%'
			)
		);

		if ( $post_id ) {
			return get_permalink( $post_id );
		}

		// Fallback: check for block.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Quiz URL lookup from post content
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_status = 'publish'
				AND post_content LIKE %s
				LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table name
				'%<!-- wp:ppq/quiz {"quizId":' . $quiz_id . '%'
			)
		);

		if ( $post_id ) {
			return get_permalink( $post_id );
		}

		return '';
	}

	/**
	 * Format time in seconds to human readable
	 *
	 * @since 1.0.0
	 *
	 * @param int $seconds Time in seconds.
	 * @return string Formatted time.
	 */
	private function format_time( $seconds ) {
		if ( $seconds < 60 ) {
			/* translators: %d: number of seconds */
			return sprintf( _n( '%d second', '%d seconds', $seconds, 'pressprimer-quiz' ), intval( $seconds ) );
		}

		$minutes           = floor( $seconds / 60 );
		$remaining_seconds = $seconds % 60;

		if ( $minutes < 60 ) {
			if ( $remaining_seconds > 0 ) {
				/* translators: 1: number of minutes, 2: number of seconds */
				return sprintf(
					__( '%1$d min %2$d sec', 'pressprimer-quiz' ),
					intval( $minutes ),
					intval( $remaining_seconds )
				);
			}
			/* translators: %d: number of minutes */
			return sprintf( _n( '%d minute', '%d minutes', $minutes, 'pressprimer-quiz' ), intval( $minutes ) );
		}

		$hours             = floor( $minutes / 60 );
		$remaining_minutes = $minutes % 60;

		/* translators: 1: number of hours, 2: number of minutes */
		return sprintf(
			__( '%1$d hr %2$d min', 'pressprimer-quiz' ),
			intval( $hours ),
			intval( $remaining_minutes )
		);
	}

	/**
	 * Get questions and answers from attempt items
	 *
	 * @since 1.0.0
	 *
	 * @param array $items Array of PressPrimer_Quiz_Attempt_Item objects.
	 * @return array Array with 'text' and 'html' keys.
	 */
	private function get_questions_and_answers( $items ) {
		$result = array(
			'text' => '',
			'html' => '',
		);

		if ( empty( $items ) || ! is_array( $items ) ) {
			return $result;
		}

		$text_parts = array();
		$html_parts = array();

		$question_num = 0;
		foreach ( $items as $item ) {
			++$question_num;

			// Get question revision for the question text and answers.
			$revision = method_exists( $item, 'get_question_revision' ) ? $item->get_question_revision() : null;

			$question_text    = '';
			$user_answer_text = '';
			$correct          = ! empty( $item->is_correct );

			if ( $revision ) {
				// Get question stem (the question text).
				$question_text = $revision->stem ?? '';

				// Get selected answers and convert to text.
				$selected_indices = method_exists( $item, 'get_selected_answers' ) ? $item->get_selected_answers() : array();
				$answers          = method_exists( $revision, 'get_answers' ) ? $revision->get_answers() : array();

				if ( ! empty( $selected_indices ) && ! empty( $answers ) ) {
					$selected_texts = array();
					foreach ( $selected_indices as $index ) {
						if ( isset( $answers[ $index ] ) ) {
							$answer = $answers[ $index ];
							// Answer can be array with 'text' key or just a string.
							if ( is_array( $answer ) && isset( $answer['text'] ) ) {
								$selected_texts[] = $answer['text'];
							} elseif ( is_string( $answer ) ) {
								$selected_texts[] = $answer;
							}
						}
					}
					$user_answer_text = implode( ', ', $selected_texts );
				}
			}

			$status      = $correct ? '✓' : '✗';
			$status_text = $correct ? __( 'Correct', 'pressprimer-quiz' ) : __( 'Incorrect', 'pressprimer-quiz' );

			// Text format.
			$text_parts[] = sprintf(
				"%d. %s\n   Answer: %s (%s)",
				$question_num,
				wp_strip_all_tags( $question_text ),
				wp_strip_all_tags( $user_answer_text ),
				$status_text
			);

			// HTML format.
			$html_parts[] = sprintf(
				'<div class="ppq-qa-item"><p><strong>%d. %s</strong></p><p>Answer: %s <span class="ppq-status-%s">%s</span></p></div>',
				$question_num,
				esc_html( wp_strip_all_tags( $question_text ) ),
				esc_html( $user_answer_text ),
				$correct ? 'correct' : 'incorrect',
				$status
			);
		}

		$result['text'] = implode( "\n\n", $text_parts );
		$result['html'] = implode( '', $html_parts );

		return $result;
	}
}
