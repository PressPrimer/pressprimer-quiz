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
 * Provides shortcodes for embedding quizzes and attempts
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
	}

	/**
	 * Render quiz shortcode
	 *
	 * Displays a quiz landing page or quiz interface.
	 *
	 * Usage: [ppq_quiz id="123"]
	 * Usage with context: [ppq_quiz id="123" context="base64_encoded_json"]
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
				'id'      => 0,
				'context' => '', // Base64 encoded JSON for integration context (e.g., LearnDash)
			],
			$atts,
			'ppq_quiz'
		);

		// Decode context if provided (used by LMS integrations)
		$context = [];
		if ( ! empty( $atts['context'] ) ) {
			$decoded = base64_decode( $atts['context'] );
			if ( $decoded ) {
				$context = json_decode( $decoded, true );
				if ( ! is_array( $context ) ) {
					$context = [];
				}
			}
		}

		// Store context in a global for the attempt creation to pick up
		if ( ! empty( $context ) ) {
			$GLOBALS['ppq_quiz_context'] = $context;
		}

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

		// Check if user wants to retake - ignore attempt parameter if retake is requested
		$is_retake = isset( $_GET['ppq_retake'] ) && '1' === $_GET['ppq_retake'];

		// Check if user is viewing an in-progress or submitted attempt
		$attempt_id = isset( $_GET['attempt'] ) ? absint( $_GET['attempt'] ) : 0;

		if ( $attempt_id && ! $is_retake ) {
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
			// Check URL parameter first, then cookie
			$token = isset( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : '';
			if ( ! $token ) {
				$token = isset( $_COOKIE['ppq_guest_token'] ) ? sanitize_text_field( $_COOKIE['ppq_guest_token'] ) : '';
			}

			if ( $token === $attempt->guest_token ) {
				// Validate token is not expired
				if ( $attempt->is_token_expired() ) {
					return $this->render_notice(
						__( 'This results link has expired. Results are available for 30 days after quiz completion.', 'pressprimer-quiz' ),
						'error'
					);
				}
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

			// Get quiz for theme
			$quiz = $attempt->get_quiz();

			// Enqueue quiz CSS (base styles)
			wp_enqueue_style(
				'ppq-quiz',
				PPQ_PLUGIN_URL . 'assets/css/quiz.css',
				[],
				PPQ_VERSION
			);

			// Enqueue results CSS
			wp_enqueue_style(
				'ppq-results',
				PPQ_PLUGIN_URL . 'assets/css/results.css',
				[ 'ppq-quiz' ],
				PPQ_VERSION
			);

			// Enqueue theme CSS
			if ( $quiz ) {
				PPQ_Theme_Loader::enqueue_quiz_theme( $quiz );
				PPQ_Theme_Loader::output_custom_css( $quiz );
			} else {
				PPQ_Theme_Loader::enqueue_theme( 'default' );
			}

			// Enqueue results JavaScript
			wp_enqueue_script(
				'ppq-results',
				PPQ_PLUGIN_URL . 'assets/js/results.js',
				[ 'jquery' ],
				PPQ_VERSION,
				true
			);

			// Localize script
			wp_localize_script(
				'ppq-results',
				'ppqResults',
				[
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( 'ppq_email_results' ),
					'sendingText' => __( 'Sending...', 'pressprimer-quiz' ),
					'sentText'    => __( 'Sent', 'pressprimer-quiz' ),
					'successText' => __( 'Results emailed successfully!', 'pressprimer-quiz' ),
					'errorText'   => __( 'Failed to send email. Please try again.', 'pressprimer-quiz' ),
				]
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
	 * Usage: [ppq_my_attempts per_page="20"]
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Rendered attempts list HTML.
	 */
	public function render_my_attempts( $atts ) {
		// Enqueue results CSS
		wp_enqueue_style(
			'ppq-results',
			PPQ_PLUGIN_URL . 'assets/css/results.css',
			[],
			PPQ_VERSION
		);

		// Parse attributes
		$atts = shortcode_atts(
			[
				'per_page' => 20,
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

		// Get filter and sort parameters from URL
		$filter_quiz   = isset( $_GET['filter_quiz'] ) ? absint( $_GET['filter_quiz'] ) : 0;
		$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';
		$filter_from   = isset( $_GET['filter_from'] ) ? sanitize_text_field( $_GET['filter_from'] ) : '';
		$filter_to     = isset( $_GET['filter_to'] ) ? sanitize_text_field( $_GET['filter_to'] ) : '';
		$sort_by       = isset( $_GET['sort_by'] ) ? sanitize_text_field( $_GET['sort_by'] ) : 'date';
		$sort_order    = isset( $_GET['sort_order'] ) ? sanitize_text_field( $_GET['sort_order'] ) : 'desc';
		$paged         = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

		// Build query
		global $wpdb;
		$attempts_table = $wpdb->prefix . 'ppq_attempts';

		$where = [ $wpdb->prepare( 'user_id = %d', $user_id ) ];

		// Filter by quiz
		if ( $filter_quiz ) {
			$where[] = $wpdb->prepare( 'quiz_id = %d', $filter_quiz );
		}

		// Filter by status
		if ( $filter_status && in_array( $filter_status, [ 'in_progress', 'submitted', 'abandoned' ], true ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $filter_status );
		}

		// Filter by date range
		if ( $filter_from ) {
			$where[] = $wpdb->prepare( 'DATE(started_at) >= %s', $filter_from );
		}
		if ( $filter_to ) {
			$where[] = $wpdb->prepare( 'DATE(started_at) <= %s', $filter_to );
		}

		$where_clause = implode( ' AND ', $where );

		// Determine sort column
		$sort_column = 'started_at';
		if ( 'score' === $sort_by ) {
			$sort_column = 'score_percent';
		} elseif ( 'duration' === $sort_by ) {
			$sort_column = 'elapsed_ms';
		}

		$order = 'desc' === strtolower( $sort_order ) ? 'DESC' : 'ASC';

		// Count total
		$total = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$attempts_table} WHERE {$where_clause}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// Calculate pagination
		$per_page    = absint( $atts['per_page'] );
		$total_pages = ceil( $total / $per_page );
		$offset      = ( $paged - 1 ) * $per_page;

		// Get attempts
		$attempts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$attempts_table} WHERE {$where_clause} ORDER BY {$sort_column} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			)
		);

		// Get all quizzes for filter dropdown
		$quizzes = PPQ_Quiz::find( [
			'where'    => [ 'status' => 'published' ],
			'order_by' => 'title',
			'order'    => 'ASC',
		] );

		// Start output
		ob_start();
		?>
		<div class="ppq-my-attempts-container">
			<h2 class="ppq-attempts-title"><?php esc_html_e( 'My Quiz Attempts', 'pressprimer-quiz' ); ?></h2>

			<!-- Filters -->
			<form method="get" class="ppq-attempts-filters">
				<?php
				// Preserve existing query params
				foreach ( $_GET as $key => $value ) {
					if ( ! in_array( $key, [ 'filter_quiz', 'filter_status', 'filter_from', 'filter_to', 'sort_by', 'sort_order', 'paged' ], true ) ) {
						echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
					}
				}
				?>

				<div class="ppq-filter-row">
					<div class="ppq-filter-group">
						<label for="filter_quiz"><?php esc_html_e( 'Quiz:', 'pressprimer-quiz' ); ?></label>
						<select name="filter_quiz" id="filter_quiz">
							<option value=""><?php esc_html_e( 'All Quizzes', 'pressprimer-quiz' ); ?></option>
							<?php foreach ( $quizzes as $quiz ) : ?>
								<option value="<?php echo esc_attr( $quiz->id ); ?>" <?php selected( $filter_quiz, $quiz->id ); ?>>
									<?php echo esc_html( $quiz->title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="ppq-filter-group">
						<label for="filter_status"><?php esc_html_e( 'Status:', 'pressprimer-quiz' ); ?></label>
						<select name="filter_status" id="filter_status">
							<option value=""><?php esc_html_e( 'All Statuses', 'pressprimer-quiz' ); ?></option>
							<option value="submitted" <?php selected( $filter_status, 'submitted' ); ?>><?php esc_html_e( 'Submitted', 'pressprimer-quiz' ); ?></option>
							<option value="in_progress" <?php selected( $filter_status, 'in_progress' ); ?>><?php esc_html_e( 'In Progress', 'pressprimer-quiz' ); ?></option>
							<option value="abandoned" <?php selected( $filter_status, 'abandoned' ); ?>><?php esc_html_e( 'Abandoned', 'pressprimer-quiz' ); ?></option>
						</select>
					</div>

					<div class="ppq-filter-group">
						<label for="filter_from"><?php esc_html_e( 'From:', 'pressprimer-quiz' ); ?></label>
						<input type="date" name="filter_from" id="filter_from" value="<?php echo esc_attr( $filter_from ); ?>">
					</div>

					<div class="ppq-filter-group">
						<label for="filter_to"><?php esc_html_e( 'To:', 'pressprimer-quiz' ); ?></label>
						<input type="date" name="filter_to" id="filter_to" value="<?php echo esc_attr( $filter_to ); ?>">
					</div>

					<div class="ppq-filter-group">
						<label for="sort_by"><?php esc_html_e( 'Sort by:', 'pressprimer-quiz' ); ?></label>
						<select name="sort_by" id="sort_by">
							<option value="date" <?php selected( $sort_by, 'date' ); ?>><?php esc_html_e( 'Date', 'pressprimer-quiz' ); ?></option>
							<option value="score" <?php selected( $sort_by, 'score' ); ?>><?php esc_html_e( 'Score', 'pressprimer-quiz' ); ?></option>
							<option value="duration" <?php selected( $sort_by, 'duration' ); ?>><?php esc_html_e( 'Duration', 'pressprimer-quiz' ); ?></option>
						</select>
					</div>

					<div class="ppq-filter-group">
						<label for="sort_order"><?php esc_html_e( 'Order:', 'pressprimer-quiz' ); ?></label>
						<select name="sort_order" id="sort_order">
							<option value="desc" <?php selected( $sort_order, 'desc' ); ?>><?php esc_html_e( 'Descending', 'pressprimer-quiz' ); ?></option>
							<option value="asc" <?php selected( $sort_order, 'asc' ); ?>><?php esc_html_e( 'Ascending', 'pressprimer-quiz' ); ?></option>
						</select>
					</div>

					<button type="submit" class="ppq-filter-button"><?php esc_html_e( 'Filter', 'pressprimer-quiz' ); ?></button>
					<a href="<?php echo esc_url( strtok( $_SERVER['REQUEST_URI'], '?' ) ); ?>" class="ppq-clear-filters"><?php esc_html_e( 'Clear', 'pressprimer-quiz' ); ?></a>
				</div>
			</form>

			<?php if ( empty( $attempts ) ) : ?>
				<div class="ppq-no-attempts">
					<p><?php esc_html_e( 'No attempts found.', 'pressprimer-quiz' ); ?></p>
				</div>
			<?php else : ?>
				<!-- Results Table -->
				<div class="ppq-attempts-table-wrapper">
					<table class="ppq-attempts-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Quiz', 'pressprimer-quiz' ); ?></th>
								<th><?php esc_html_e( 'Score', 'pressprimer-quiz' ); ?></th>
								<th><?php esc_html_e( 'Pass/Fail', 'pressprimer-quiz' ); ?></th>
								<th><?php esc_html_e( 'Date', 'pressprimer-quiz' ); ?></th>
								<th><?php esc_html_e( 'Duration', 'pressprimer-quiz' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'pressprimer-quiz' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $attempts as $attempt_row ) {
								$attempt = PPQ_Attempt::from_row( $attempt_row );
								$quiz    = PPQ_Quiz::get( $attempt->quiz_id );

								if ( ! $quiz ) {
									continue;
								}

								$this->render_attempt_row( $attempt, $quiz );
							}
							?>
						</tbody>
					</table>
				</div>

				<!-- Pagination -->
				<?php if ( $total_pages > 1 ) : ?>
					<div class="ppq-pagination">
						<?php
						$base_url = remove_query_arg( 'paged' );

						// Previous
						if ( $paged > 1 ) {
							echo '<a href="' . esc_url( add_query_arg( 'paged', $paged - 1, $base_url ) ) . '" class="ppq-page-link ppq-prev">&laquo; ' . esc_html__( 'Previous', 'pressprimer-quiz' ) . '</a>';
						}

						// Page numbers
						for ( $i = 1; $i <= $total_pages; $i++ ) {
							$class = $i === $paged ? 'ppq-page-link ppq-current' : 'ppq-page-link';
							echo '<a href="' . esc_url( add_query_arg( 'paged', $i, $base_url ) ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $i ) . '</a>';
						}

						// Next
						if ( $paged < $total_pages ) {
							echo '<a href="' . esc_url( add_query_arg( 'paged', $paged + 1, $base_url ) ) . '" class="ppq-page-link ppq-next">' . esc_html__( 'Next', 'pressprimer-quiz' ) . ' &raquo;</a>';
						}
						?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render single attempt row
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Attempt $attempt Attempt object.
	 * @param PPQ_Quiz    $quiz Quiz object.
	 */
	private function render_attempt_row( $attempt, $quiz ) {
		?>
		<tr class="ppq-attempt-row">
			<td class="ppq-attempt-quiz">
				<?php echo esc_html( $quiz->title ); ?>
			</td>
			<td class="ppq-attempt-score">
				<?php if ( 'submitted' === $attempt->status && null !== $attempt->score_percent ) : ?>
					<?php echo esc_html( number_format( $attempt->score_percent, 1 ) ); ?>%
				<?php else : ?>
					<span class="ppq-text-muted">—</span>
				<?php endif; ?>
			</td>
			<td class="ppq-attempt-status">
				<?php if ( 'submitted' === $attempt->status ) : ?>
					<?php if ( $attempt->passed ) : ?>
						<span class="ppq-badge ppq-badge-success"><?php esc_html_e( 'Passed', 'pressprimer-quiz' ); ?></span>
					<?php else : ?>
						<span class="ppq-badge ppq-badge-failed"><?php esc_html_e( 'Failed', 'pressprimer-quiz' ); ?></span>
					<?php endif; ?>
				<?php elseif ( 'in_progress' === $attempt->status ) : ?>
					<span class="ppq-badge ppq-badge-progress"><?php esc_html_e( 'In Progress', 'pressprimer-quiz' ); ?></span>
				<?php else : ?>
					<span class="ppq-badge ppq-badge-abandoned"><?php esc_html_e( 'Abandoned', 'pressprimer-quiz' ); ?></span>
				<?php endif; ?>
			</td>
			<td class="ppq-attempt-date">
				<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $attempt->started_at ) ) ); ?>
			</td>
			<td class="ppq-attempt-duration">
				<?php if ( $attempt->elapsed_ms ) : ?>
					<?php echo esc_html( $this->format_duration_readable( $attempt->elapsed_ms ) ); ?>
				<?php else : ?>
					<span class="ppq-text-muted">—</span>
				<?php endif; ?>
			</td>
			<td class="ppq-attempt-actions">
				<?php if ( 'in_progress' === $attempt->status && $attempt->can_resume() ) : ?>
					<?php $resume_url = add_query_arg( 'attempt', $attempt->id, get_permalink() ); ?>
					<a href="<?php echo esc_url( $resume_url ); ?>" class="ppq-button ppq-button-small">
						<?php esc_html_e( 'Resume', 'pressprimer-quiz' ); ?>
					</a>
				<?php elseif ( 'submitted' === $attempt->status ) : ?>
					<?php $results_url = add_query_arg( 'attempt', $attempt->id, get_permalink() ); ?>
					<a href="<?php echo esc_url( $results_url ); ?>" class="ppq-button ppq-button-small ppq-button-primary">
						<?php esc_html_e( 'View Results', 'pressprimer-quiz' ); ?>
					</a>
					<?php if ( $this->can_retake_quiz( $quiz, $attempt ) ) : ?>
						<?php $retake_url = get_permalink( $quiz->id ); ?>
						<a href="<?php echo esc_url( $retake_url ); ?>" class="ppq-button ppq-button-small ppq-button-secondary">
							<?php esc_html_e( 'Retake', 'pressprimer-quiz' ); ?>
						</a>
					<?php endif; ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Check if user can retake quiz
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Quiz    $quiz Quiz object.
	 * @param PPQ_Attempt $current_attempt Current attempt.
	 * @return bool True if can retake.
	 */
	private function can_retake_quiz( $quiz, $current_attempt ) {
		$user_id = get_current_user_id();

		// Check max attempts
		if ( $quiz->max_attempts ) {
			$attempts        = PPQ_Attempt::get_user_attempts( $quiz->id, $user_id );
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

		// Check attempt delay
		if ( $quiz->attempt_delay_minutes && $current_attempt->finished_at ) {
			$elapsed_minutes = ( time() - strtotime( $current_attempt->finished_at ) ) / 60;

			if ( $elapsed_minutes < $quiz->attempt_delay_minutes ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Format duration in readable format
	 *
	 * @since 1.0.0
	 *
	 * @param int $milliseconds Duration in milliseconds.
	 * @return string Readable duration.
	 */
	private function format_duration_readable( $milliseconds ) {
		$seconds = floor( $milliseconds / 1000 );
		$minutes = floor( $seconds / 60 );
		$hours   = floor( $minutes / 60 );

		$seconds = $seconds % 60;
		$minutes = $minutes % 60;

		if ( $hours > 0 ) {
			return sprintf( '%dh %dm', $hours, $minutes );
		} elseif ( $minutes > 0 ) {
			return sprintf( '%dm %ds', $minutes, $seconds );
		} else {
			return sprintf( '%ds', $seconds );
		}
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
