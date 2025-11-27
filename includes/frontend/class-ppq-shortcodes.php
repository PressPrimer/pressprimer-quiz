<?php
/**
 * Shortcodes
 *
 * Registers and handles all PressPrimer Quiz shortcodes.
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
 * Shortcodes class
 *
 * Provides shortcodes for embedding quizzes, attempts, and assignments
 * into posts and pages.
 *
 * @since 1.0.0
 */
class PPQ_Shortcodes {

	/**
	 * Initialize shortcodes
	 *
	 * Registers all shortcodes with WordPress.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_shortcodes' ] );
	}

	/**
	 * Register all shortcodes
	 *
	 * @since 1.0.0
	 */
	public function register_shortcodes() {
		add_shortcode( 'ppq_quiz', [ $this, 'render_quiz' ] );
		add_shortcode( 'ppq_my_attempts', [ $this, 'render_my_attempts' ] );
		add_shortcode( 'ppq_assigned_quizzes', [ $this, 'render_assigned_quizzes' ] );
	}

	/**
	 * Render quiz shortcode
	 *
	 * Displays a quiz landing page or quiz interface.
	 *
	 * Usage: [ppq_quiz id="123"]
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered quiz HTML.
	 */
	public function render_quiz( $atts ) {
		// Parse attributes
		$atts = shortcode_atts(
			[
				'id' => 0,
			],
			$atts,
			'ppq_quiz'
		);

		$quiz_id = absint( $atts['id'] );

		// Validate quiz ID
		if ( ! $quiz_id ) {
			return $this->render_error( __( 'Please provide a valid quiz ID.', 'pressprimer-quiz' ) );
		}

		// Load quiz
		$quiz = PPQ_Quiz::get( $quiz_id );

		if ( ! $quiz ) {
			return $this->render_error( __( 'Quiz not found.', 'pressprimer-quiz' ) );
		}

		// Check if quiz is published
		if ( 'published' !== $quiz->status && ! current_user_can( 'ppq_manage_all' ) ) {
			// Allow quiz owners to preview their own draft quizzes
			if ( ! ( $quiz->owner_id && get_current_user_id() === absint( $quiz->owner_id ) ) ) {
				return $this->render_error( __( 'This quiz is not available.', 'pressprimer-quiz' ) );
			}
		}

		// Check if user is viewing an in-progress or submitted attempt
		$attempt_id = isset( $_GET['attempt'] ) ? absint( $_GET['attempt'] ) : 0;

		if ( $attempt_id ) {
			return $this->render_quiz_attempt( $attempt_id );
		}

		// Render quiz landing page
		if ( ! class_exists( 'PPQ_Quiz_Renderer' ) ) {
			return $this->render_error( __( 'Quiz renderer not available.', 'pressprimer-quiz' ) );
		}

		$renderer = new PPQ_Quiz_Renderer();
		return $renderer->render_landing( $quiz );
	}

	/**
	 * Render quiz attempt (in progress or submitted)
	 *
	 * @since 1.0.0
	 *
	 * @param int $attempt_id Attempt ID.
	 * @return string Rendered HTML.
	 */
	private function render_quiz_attempt( $attempt_id ) {
		$attempt = PPQ_Attempt::get( $attempt_id );

		if ( ! $attempt ) {
			return $this->render_error( __( 'Attempt not found.', 'pressprimer-quiz' ) );
		}

		// Verify user can access this attempt
		$can_access = false;

		// Check if user owns this attempt
		if ( is_user_logged_in() && $attempt->user_id === get_current_user_id() ) {
			$can_access = true;
		}

		// Check if guest with valid token
		if ( ! is_user_logged_in() && $attempt->guest_token ) {
			$token = isset( $_COOKIE['ppq_guest_token'] ) ? sanitize_text_field( $_COOKIE['ppq_guest_token'] ) : '';
			if ( $token === $attempt->guest_token ) {
				$can_access = true;
			}
		}

		// Check if admin
		if ( current_user_can( 'ppq_manage_all' ) ) {
			$can_access = true;
		}

		if ( ! $can_access ) {
			return $this->render_error( __( 'You do not have permission to view this attempt.', 'pressprimer-quiz' ) );
		}

		if ( ! class_exists( 'PPQ_Quiz_Renderer' ) ) {
			return $this->render_error( __( 'Quiz renderer not available.', 'pressprimer-quiz' ) );
		}

		$renderer = new PPQ_Quiz_Renderer();

		// If submitted, show results
		if ( 'submitted' === $attempt->status ) {
			if ( ! class_exists( 'PPQ_Results_Renderer' ) ) {
				return $this->render_error( __( 'Results renderer not available.', 'pressprimer-quiz' ) );
			}

			// Enqueue results CSS
			wp_enqueue_style(
				'ppq-results',
				PPQ_PLUGIN_URL . 'assets/css/results.css',
				[],
				PPQ_VERSION
			);

			$results_renderer = new PPQ_Results_Renderer();
			$output = $results_renderer->render_results( $attempt );
			$output .= $results_renderer->render_question_review( $attempt );

			return $output;
		}

		// If in progress, show quiz interface
		if ( 'in_progress' === $attempt->status ) {
			return $renderer->render_quiz( $attempt );
		}

		return $this->render_error( __( 'This attempt is no longer available.', 'pressprimer-quiz' ) );
	}

	/**
	 * Render my attempts shortcode
	 *
	 * Displays a list of the current user's quiz attempts.
	 *
	 * Usage: [ppq_my_attempts]
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered attempts list HTML.
	 */
	public function render_my_attempts( $atts ) {
		// Parse attributes
		$atts = shortcode_atts(
			[
				'limit'  => 20,
				'status' => '', // all, in_progress, submitted
			],
			$atts,
			'ppq_my_attempts'
		);

		// Require login
		if ( ! is_user_logged_in() ) {
			return $this->render_notice(
				__( 'Please log in to view your quiz attempts.', 'pressprimer-quiz' ),
				'info'
			);
		}

		$user_id = get_current_user_id();

		// Get user's attempts
		global $wpdb;
		$attempts_table = $wpdb->prefix . 'ppq_attempts';

		$where = $wpdb->prepare( 'user_id = %d', $user_id );

		if ( ! empty( $atts['status'] ) && in_array( $atts['status'], [ 'in_progress', 'submitted', 'abandoned' ], true ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $atts['status'] );
		}

		$limit = absint( $atts['limit'] );

		$attempts = $wpdb->get_results(
			"SELECT * FROM {$attempts_table} WHERE {$where} ORDER BY started_at DESC LIMIT {$limit}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// Start output buffer
		ob_start();

		if ( empty( $attempts ) ) {
			echo '<div class="ppq-my-attempts ppq-no-attempts">';
			echo '<p>' . esc_html__( 'You have not attempted any quizzes yet.', 'pressprimer-quiz' ) . '</p>';
			echo '</div>';
			return ob_get_clean();
		}

		echo '<div class="ppq-my-attempts">';
		echo '<h2>' . esc_html__( 'My Quiz Attempts', 'pressprimer-quiz' ) . '</h2>';
		echo '<table class="ppq-attempts-table">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__( 'Quiz', 'pressprimer-quiz' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'pressprimer-quiz' ) . '</th>';
		echo '<th>' . esc_html__( 'Score', 'pressprimer-quiz' ) . '</th>';
		echo '<th>' . esc_html__( 'Started', 'pressprimer-quiz' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'pressprimer-quiz' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ( $attempts as $attempt_row ) {
			$attempt = PPQ_Attempt::from_row( $attempt_row );
			$quiz = PPQ_Quiz::get( $attempt->quiz_id );

			if ( ! $quiz ) {
				continue;
			}

			echo '<tr>';
			echo '<td>' . esc_html( $quiz->title ) . '</td>';
			echo '<td>';
			if ( 'submitted' === $attempt->status ) {
				echo '<span class="ppq-status ppq-status-submitted">' . esc_html__( 'Submitted', 'pressprimer-quiz' ) . '</span>';
			} elseif ( 'in_progress' === $attempt->status ) {
				echo '<span class="ppq-status ppq-status-in-progress">' . esc_html__( 'In Progress', 'pressprimer-quiz' ) . '</span>';
			} else {
				echo '<span class="ppq-status ppq-status-abandoned">' . esc_html__( 'Abandoned', 'pressprimer-quiz' ) . '</span>';
			}
			echo '</td>';
			echo '<td>';
			if ( 'submitted' === $attempt->status && null !== $attempt->score_percent ) {
				echo esc_html( number_format( $attempt->score_percent, 1 ) . '%' );
				if ( $attempt->passed ) {
					echo ' <span class="ppq-passed">✓</span>';
				}
			} else {
				echo '—';
			}
			echo '</td>';
			echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $attempt->started_at ) ) ) . '</td>';
			echo '<td>';
			if ( 'in_progress' === $attempt->status && $attempt->can_resume() ) {
				$resume_url = add_query_arg( 'attempt', $attempt->id, get_permalink() );
				echo '<a href="' . esc_url( $resume_url ) . '" class="ppq-button">' . esc_html__( 'Resume', 'pressprimer-quiz' ) . '</a>';
			} elseif ( 'submitted' === $attempt->status ) {
				$results_url = add_query_arg( 'attempt', $attempt->id, get_permalink() );
				echo '<a href="' . esc_url( $results_url ) . '" class="ppq-button">' . esc_html__( 'View Results', 'pressprimer-quiz' ) . '</a>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Render assigned quizzes shortcode
	 *
	 * Displays quizzes assigned to the current user.
	 *
	 * Usage: [ppq_assigned_quizzes]
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered assigned quizzes HTML.
	 */
	public function render_assigned_quizzes( $atts ) {
		// Parse attributes
		$atts = shortcode_atts(
			[
				'limit' => 20,
			],
			$atts,
			'ppq_assigned_quizzes'
		);

		// Require login
		if ( ! is_user_logged_in() ) {
			return $this->render_notice(
				__( 'Please log in to view your assigned quizzes.', 'pressprimer-quiz' ),
				'info'
			);
		}

		// Get user's assignments
		// Note: Assignment functionality will be implemented in Phase 6
		// For now, return a placeholder

		ob_start();

		echo '<div class="ppq-assigned-quizzes">';
		echo '<h2>' . esc_html__( 'My Assigned Quizzes', 'pressprimer-quiz' ) . '</h2>';
		echo '<p>' . esc_html__( 'Quiz assignment functionality will be available in a future update.', 'pressprimer-quiz' ) . '</p>';
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * Render error message
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Error message.
	 * @return string HTML error message.
	 */
	private function render_error( $message ) {
		return sprintf(
			'<div class="ppq-error ppq-notice ppq-notice-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Render notice message
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Notice message.
	 * @param string $type Notice type (info, warning, success, error).
	 * @return string HTML notice message.
	 */
	private function render_notice( $message, $type = 'info' ) {
		return sprintf(
			'<div class="ppq-notice ppq-notice-%s"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}
