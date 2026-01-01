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
class PressPrimer_Quiz_Shortcodes {

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
		add_shortcode( 'pressprimer_quiz', [ $this, 'render_quiz' ] );
		add_shortcode( 'pressprimer_quiz_my_attempts', [ $this, 'render_my_attempts' ] );
	}

	/**
	 * Render quiz shortcode
	 *
	 * Displays a quiz landing page or quiz interface.
	 *
	 * Usage: [pressprimer_quiz id="123"]
	 * Usage with context: [pressprimer_quiz id="123" context="base64_encoded_json"]
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
			'pressprimer_quiz'
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
			$GLOBALS['pressprimer_quiz_context'] = $context;
		}

		$quiz_id = absint( $atts['id'] );

		// Validate quiz ID
		if ( ! $quiz_id ) {
			return $this->render_error( __( 'Please provide a valid quiz ID.', 'pressprimer-quiz' ) );
		}

		// Load quiz
		$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );

		if ( ! $quiz ) {
			return $this->render_error( __( 'Quiz not found.', 'pressprimer-quiz' ) );
		}

		// Check if quiz is published
		if ( 'published' !== $quiz->status && ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			// Allow quiz owners to preview their own draft quizzes
			if ( ! ( $quiz->owner_id && get_current_user_id() === absint( $quiz->owner_id ) ) ) {
				return $this->render_error( __( 'This quiz is not available.', 'pressprimer-quiz' ) );
			}
		}

		// Check if user wants to retake - ignore attempt parameter if retake is requested
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only URL parameters for quiz display
		$is_retake = isset( $_GET['ppq_retake'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['ppq_retake'] ) );

		// Check if user is viewing an in-progress or submitted attempt
		$attempt_id = isset( $_GET['attempt'] ) ? absint( wp_unslash( $_GET['attempt'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $attempt_id && ! $is_retake ) {
			return $this->render_quiz_attempt( $attempt_id );
		}

		// Render quiz landing page
		if ( ! class_exists( 'PressPrimer_Quiz_Quiz_Renderer' ) ) {
			return $this->render_error( __( 'Quiz renderer not available.', 'pressprimer-quiz' ) );
		}

		$renderer = new PressPrimer_Quiz_Quiz_Renderer();
		return $renderer->render_landing( $quiz, $is_retake );
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
		// Send no-cache headers for attempt pages to prevent Varnish/CDN caching
		// This ensures users always see their current attempt state
		if ( ! headers_sent() ) {
			header( 'Cache-Control: no-cache, no-store, must-revalidate, private' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		$attempt = PressPrimer_Quiz_Attempt::get( $attempt_id );

		if ( ! $attempt ) {
			return $this->render_error( __( 'Attempt not found.', 'pressprimer-quiz' ) );
		}

		// Verify user can access this attempt
		$can_access = false;

		// Check if user owns this attempt
		if ( is_user_logged_in() && (int) $attempt->user_id === get_current_user_id() ) {
			$can_access = true;
		}

		// Check if guest with valid token
		if ( ! is_user_logged_in() && $attempt->guest_token ) {
			// Check URL parameter first, then cookie
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only token for guest access verification
			$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
			if ( ! $token ) {
				$token = isset( $_COOKIE['ppq_guest_token'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['ppq_guest_token'] ) ) : '';
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
		if ( current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			$can_access = true;
		}

		if ( ! $can_access ) {
			return $this->render_error( __( 'You do not have permission to view this attempt.', 'pressprimer-quiz' ) );
		}

		if ( ! class_exists( 'PressPrimer_Quiz_Quiz_Renderer' ) ) {
			return $this->render_error( __( 'Quiz renderer not available.', 'pressprimer-quiz' ) );
		}

		$renderer = new PressPrimer_Quiz_Quiz_Renderer();

		// If submitted, show results
		if ( 'submitted' === $attempt->status ) {
			if ( ! class_exists( 'PressPrimer_Quiz_Results_Renderer' ) ) {
				return $this->render_error( __( 'Results renderer not available.', 'pressprimer-quiz' ) );
			}

			// Get quiz for theme
			$quiz = $attempt->get_quiz();

			// Enqueue quiz CSS (base styles)
			wp_enqueue_style(
				'ppq-quiz',
				PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/css/quiz.css',
				[],
				PRESSPRIMER_QUIZ_VERSION
			);

			// Enqueue results CSS
			wp_enqueue_style(
				'ppq-results',
				PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/css/results.css',
				[ 'ppq-quiz' ],
				PRESSPRIMER_QUIZ_VERSION
			);

			// Enqueue theme CSS
			if ( $quiz ) {
				PressPrimer_Quiz_Theme_Loader::enqueue_quiz_theme( $quiz );
				PressPrimer_Quiz_Theme_Loader::output_custom_css( $quiz );
			} else {
				PressPrimer_Quiz_Theme_Loader::enqueue_theme( 'default' );
			}

			// Enqueue results JavaScript
			wp_enqueue_script(
				'ppq-results',
				PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/js/results.js',
				[ 'jquery' ],
				PRESSPRIMER_QUIZ_VERSION,
				true
			);

			// Localize script
			wp_localize_script(
				'ppq-results',
				'pressprimerQuizResults',
				[
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( 'pressprimer_quiz_email_results' ),
					'sendingText' => __( 'Sending...', 'pressprimer-quiz' ),
					'sentText'    => __( 'Sent', 'pressprimer-quiz' ),
					'successText' => __( 'Results emailed successfully!', 'pressprimer-quiz' ),
					'errorText'   => __( 'Failed to send email. Please try again.', 'pressprimer-quiz' ),
				]
			);

			$results_renderer = new PressPrimer_Quiz_Results_Renderer();
			$output           = $results_renderer->render_results( $attempt );
			$output          .= $results_renderer->render_question_review( $attempt );

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
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/css/results.css',
			[],
			PRESSPRIMER_QUIZ_VERSION
		);

		// Parse attributes
		$atts = shortcode_atts(
			[
				'per_page'   => 20,
				'show_score' => true,
				'show_date'  => true,
			],
			$atts,
			'pressprimer_quiz_my_attempts'
		);

		// Convert string 'false' to boolean false (for shortcode usage)
		$show_score = filter_var( $atts['show_score'], FILTER_VALIDATE_BOOLEAN );
		$show_date  = filter_var( $atts['show_date'], FILTER_VALIDATE_BOOLEAN );

		// Require login
		if ( ! is_user_logged_in() ) {
			return $this->render_notice(
				__( 'Please log in to view your quiz attempts.', 'pressprimer-quiz' ),
				'info'
			);
		}

		$user_id = get_current_user_id();

		// Get filter and sort parameters from URL
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter/sort parameters for history display
		$filter_quiz   = isset( $_GET['filter_quiz'] ) ? absint( wp_unslash( $_GET['filter_quiz'] ) ) : 0;
		$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';
		$filter_from   = isset( $_GET['filter_from'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_from'] ) ) : '';
		$filter_to     = isset( $_GET['filter_to'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_to'] ) ) : '';
		$sort_by       = isset( $_GET['sort_by'] ) ? sanitize_text_field( wp_unslash( $_GET['sort_by'] ) ) : 'date';
		$sort_order    = isset( $_GET['sort_order'] ) ? sanitize_text_field( wp_unslash( $_GET['sort_order'] ) ) : 'desc';
		// Use ppq_paged to avoid conflict with WordPress's internal 'paged' parameter
		$paged = isset( $_GET['ppq_paged'] ) ? absint( wp_unslash( $_GET['ppq_paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

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

		// Build ORDER BY with sanitize_sql_orderby
		$order_sql = sanitize_sql_orderby( "{$sort_column} {$order}" );
		$order_sql = $order_sql ? "ORDER BY {$order_sql}" : 'ORDER BY started_at DESC';

		// Count total
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- User history pagination, not suitable for caching
		$total = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$attempts_table} WHERE {$where_clause}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// Calculate pagination
		$per_page    = absint( $atts['per_page'] );
		$total_pages = ceil( $total / $per_page );
		$offset      = ( $paged - 1 ) * $per_page;

		// Get attempts
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- User history pagination, not suitable for caching
		$attempts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$attempts_table} WHERE {$where_clause} {$order_sql} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			)
		);

		// Get all quizzes for filter dropdown
		$quizzes = PressPrimer_Quiz_Quiz::find(
			[
				'where'    => [ 'status' => 'published' ],
				'order_by' => 'title',
				'order'    => 'ASC',
			]
		);

		// Start output
		ob_start();
		?>
		<div class="ppq-my-attempts-container">
			<h2 class="ppq-attempts-title"><?php esc_html_e( 'My Quiz Attempts', 'pressprimer-quiz' ); ?></h2>

			<!-- Filters -->
			<form method="get" class="ppq-attempts-filters">
				<?php
				// Preserve page identifier for non-pretty permalinks
				// Only preserve specific known WordPress parameters, not the entire $_GET array
				// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading page identifiers for form action, no state change
				if ( isset( $_GET['page_id'] ) ) {
					echo '<input type="hidden" name="page_id" value="' . esc_attr( absint( wp_unslash( $_GET['page_id'] ) ) ) . '">';
				}
				if ( isset( $_GET['p'] ) ) {
					echo '<input type="hidden" name="p" value="' . esc_attr( absint( wp_unslash( $_GET['p'] ) ) ) . '">';
				}
				// phpcs:enable WordPress.Security.NonceVerification.Recommended
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
					<a href="<?php echo esc_url( strtok( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', '?' ) ); ?>" class="ppq-clear-filters"><?php esc_html_e( 'Clear', 'pressprimer-quiz' ); ?></a>
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
								<?php if ( $show_score ) : ?>
									<th><?php esc_html_e( 'Score', 'pressprimer-quiz' ); ?></th>
								<?php endif; ?>
								<th><?php esc_html_e( 'Pass/Fail', 'pressprimer-quiz' ); ?></th>
								<?php if ( $show_date ) : ?>
									<th><?php esc_html_e( 'Date', 'pressprimer-quiz' ); ?></th>
								<?php endif; ?>
								<th><?php esc_html_e( 'Duration', 'pressprimer-quiz' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'pressprimer-quiz' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $attempts as $attempt_row ) {
								$attempt = PressPrimer_Quiz_Attempt::from_row( $attempt_row );
								$quiz    = PressPrimer_Quiz_Quiz::get( $attempt->quiz_id );

								if ( ! $quiz ) {
									continue;
								}

								$this->render_attempt_row( $attempt, $quiz, $show_score, $show_date );
							}
							?>
						</tbody>
					</table>
				</div>

				<!-- Pagination -->
				<?php if ( $total_pages > 1 ) : ?>
					<div class="ppq-pagination">
						<?php
						$base_url = remove_query_arg( 'ppq_paged' );

						// Previous
						if ( $paged > 1 ) {
							echo '<a href="' . esc_url( add_query_arg( 'ppq_paged', $paged - 1, $base_url ) ) . '" class="ppq-page-link ppq-prev">&laquo; ' . esc_html__( 'Previous', 'pressprimer-quiz' ) . '</a>';
						}

						// Page numbers
						for ( $i = 1; $i <= $total_pages; $i++ ) {
							$class = $i === $paged ? 'ppq-page-link ppq-current' : 'ppq-page-link';
							echo '<a href="' . esc_url( add_query_arg( 'ppq_paged', $i, $base_url ) ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $i ) . '</a>';
						}

						// Next
						if ( $paged < $total_pages ) {
							echo '<a href="' . esc_url( add_query_arg( 'ppq_paged', $paged + 1, $base_url ) ) . '" class="ppq-page-link ppq-next">' . esc_html__( 'Next', 'pressprimer-quiz' ) . ' &raquo;</a>';
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
	 * @param PressPrimer_Quiz_Attempt $attempt    Attempt object.
	 * @param PressPrimer_Quiz_Quiz    $quiz       Quiz object.
	 * @param bool                     $show_score Whether to show score column.
	 * @param bool                     $show_date  Whether to show date column.
	 */
	private function render_attempt_row( $attempt, $quiz, $show_score = true, $show_date = true ) {
		?>
		<tr class="ppq-attempt-row">
			<td class="ppq-attempt-quiz">
				<?php echo esc_html( $quiz->title ); ?>
			</td>
			<?php if ( $show_score ) : ?>
				<td class="ppq-attempt-score">
					<?php if ( 'submitted' === $attempt->status && null !== $attempt->score_percent ) : ?>
						<?php echo esc_html( number_format_i18n( $attempt->score_percent, 1 ) ); ?>%
					<?php else : ?>
						<span class="ppq-text-muted">—</span>
					<?php endif; ?>
				</td>
			<?php endif; ?>
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
			<?php if ( $show_date ) : ?>
				<td class="ppq-attempt-date">
					<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $attempt->started_at ) ) ); ?>
				</td>
			<?php endif; ?>
			<td class="ppq-attempt-duration">
				<?php if ( $attempt->elapsed_ms ) : ?>
					<?php echo esc_html( $this->format_duration_readable( $attempt->elapsed_ms ) ); ?>
				<?php else : ?>
					<span class="ppq-text-muted">—</span>
				<?php endif; ?>
			</td>
			<td class="ppq-attempt-actions">
				<?php
				// Use the stored source URL from the attempt, fall back to searching for shortcode
				$quiz_page_url = $attempt->source_url ?: $this->get_quiz_page_url( $quiz->id );
				?>
				<?php if ( 'in_progress' === $attempt->status && $attempt->can_resume() ) : ?>
					<?php if ( $quiz_page_url ) : ?>
						<?php $resume_url = add_query_arg( 'attempt', $attempt->id, $quiz_page_url ); ?>
						<a href="<?php echo esc_url( $resume_url ); ?>" class="ppq-button ppq-button-small">
							<?php esc_html_e( 'Resume', 'pressprimer-quiz' ); ?>
						</a>
					<?php else : ?>
						<span class="ppq-text-muted" title="<?php esc_attr_e( 'Quiz page not found', 'pressprimer-quiz' ); ?>">
							<?php esc_html_e( 'Resume', 'pressprimer-quiz' ); ?>
						</span>
					<?php endif; ?>
				<?php elseif ( 'submitted' === $attempt->status ) : ?>
					<?php if ( $quiz_page_url ) : ?>
						<?php $results_url = add_query_arg( 'attempt', $attempt->id, $quiz_page_url ); ?>
						<a href="<?php echo esc_url( $results_url ); ?>" class="ppq-button ppq-button-small ppq-button-primary">
							<?php esc_html_e( 'View Results', 'pressprimer-quiz' ); ?>
						</a>
						<?php if ( $this->can_retake_quiz( $quiz, $attempt ) ) : ?>
							<?php $retake_url = add_query_arg( 'ppq_retake', '1', $quiz_page_url ); ?>
							<a href="<?php echo esc_url( $retake_url ); ?>" class="ppq-button ppq-button-small ppq-button-secondary">
								<?php esc_html_e( 'Retake', 'pressprimer-quiz' ); ?>
							</a>
						<?php endif; ?>
					<?php else : ?>
						<span class="ppq-text-muted" title="<?php esc_attr_e( 'Quiz page not found', 'pressprimer-quiz' ); ?>">
							<?php esc_html_e( 'View Results', 'pressprimer-quiz' ); ?>
						</span>
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
	 * @param PressPrimer_Quiz_Quiz    $quiz Quiz object.
	 * @param PressPrimer_Quiz_Attempt $current_attempt Current attempt.
	 * @return bool True if can retake.
	 */
	private function can_retake_quiz( $quiz, $current_attempt ) {
		$user_id = get_current_user_id();

		// Check max attempts
		if ( $quiz->max_attempts ) {
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

	/**
	 * Get the URL of a page/post containing a specific quiz shortcode
	 *
	 * Searches for posts/pages containing [pressprimer_quiz id="X"] shortcode.
	 * Results are cached for performance.
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return string|false Page URL or false if not found.
	 */
	private function get_quiz_page_url( $quiz_id ) {
		// Check transient cache first
		$cache_key = 'ppq_quiz_page_url_' . $quiz_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached ?: false; // Empty string means "not found" in cache
		}

		global $wpdb;

		// Search for shortcode in post content
		// Look for [pressprimer_quiz id="X"] or [pressprimer_quiz id='X'] or [pressprimer_quiz id=X]
		$shortcode_patterns = [
			'%[pressprimer_quiz %id="' . $quiz_id . '"%',
			"%[pressprimer_quiz %id='" . $quiz_id . "'%",
			'%[pressprimer_quiz %id=' . $quiz_id . '%',
		];

		$where_clauses = [];
		foreach ( $shortcode_patterns as $pattern ) {
			$where_clauses[] = $wpdb->prepare( 'post_content LIKE %s', $pattern );
		}

		$where = implode( ' OR ', $where_clauses );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom shortcode search with transient caching
		$post_id = $wpdb->get_var(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_status = 'publish'
			AND post_type IN ('post', 'page')
			AND ({$where})
			ORDER BY post_type = 'page' DESC, ID ASC
			LIMIT 1" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where is built with prepare() above
		);

		$url = $post_id ? get_permalink( $post_id ) : '';

		// Cache for 1 hour (empty string if not found)
		set_transient( $cache_key, $url, HOUR_IN_SECONDS );

		return $url ?: false;
	}
}
