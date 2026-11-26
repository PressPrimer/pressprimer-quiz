<?php
/**
 * Review notice class
 *
 * Handles the 100 attempts celebration notice: site admins see it on
 * PressPrimer Quiz screens once students have completed 100 attempts. It
 * stops once answered or closed, or snoozes for 30 days. Since 3.2 its two
 * outbound buttons are real links, it follows the prompt switch (so
 * white-label hides it), and other asks can hold it so only one shows at a
 * time.
 *
 * @package PressPrimer_Quiz
 * @subpackage Admin
 * @since 2.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Review notice class
 *
 * @since 2.1.0
 */
class PressPrimer_Quiz_Review_Notice {

	/**
	 * Attempt threshold for showing notice
	 *
	 * @var int
	 */
	const ATTEMPT_THRESHOLD = 100;

	/**
	 * Snooze duration in seconds (30 days)
	 *
	 * @var int
	 */
	const SNOOZE_DURATION = 2592000;

	/**
	 * Transient: the site's completed attempt count (12 hours)
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const COUNT_TRANSIENT = 'pressprimer_quiz_completed_attempt_count';

	/**
	 * Nonce action for the notice's AJAX requests
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const NONCE_ACTION = 'pressprimer_quiz_review_prompt';

	/**
	 * The WordPress.org review form
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const REVIEW_URL = 'https://wordpress.org/support/plugin/pressprimer-quiz/reviews/#new-post';

	/**
	 * The feedback form
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const FEEDBACK_URL = 'https://pressprimer.com/contact/';

	/**
	 * Initialize the notice
	 *
	 * @since 2.1.0
	 */
	public function init() {
		add_action( 'admin_notices', array( $this, 'maybe_display_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_pressprimer_quiz_celebration_review', array( $this, 'handle_review' ) );
		add_action( 'wp_ajax_pressprimer_quiz_celebration_feedback', array( $this, 'handle_feedback' ) );
		add_action( 'wp_ajax_pressprimer_quiz_celebration_snooze', array( $this, 'handle_snooze' ) );
		add_action( 'wp_ajax_pressprimer_quiz_celebration_dismiss', array( $this, 'handle_dismiss' ) );
	}

	/**
	 * Check if we're on a PPQ admin page
	 *
	 * @since 2.1.0
	 * @return bool
	 */
	private function is_ppq_admin_page() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		// Check for PPQ page patterns.
		return strpos( $screen->id, 'ppq-' ) !== false
			|| strpos( $screen->id, 'pressprimer-quiz' ) !== false;
	}

	/**
	 * Check if notice should be displayed
	 *
	 * @since 2.1.0
	 * @return bool
	 */
	public function should_show_notice() {
		// Must be on PPQ page.
		if ( ! $this->is_ppq_admin_page() ) {
			return false;
		}

		return self::is_due( get_current_user_id() );
	}

	/**
	 * Whether the review prompt is due for a user (any screen)
	 *
	 * Site admins only, and only while the prompt switch allows review
	 * prompts (off under white-label). Other asks call this to wait their
	 * turn, and hold it through `pressprimer_quiz_review_prompt_due`.
	 *
	 * @since 3.2.0
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_due( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! user_can( $user_id, 'manage_options' ) ) {
			return false;
		}

		if ( ! pressprimer_quiz_show_prompts( 'review' ) ) {
			return false;
		}

		// Check if permanently dismissed.
		if ( get_option( 'pressprimer_quiz_celebration_dismissed', false ) ) {
			return false;
		}

		// Check if snoozed.
		$snoozed_until = (int) get_option( 'pressprimer_quiz_celebration_snoozed_until', 0 );
		if ( $snoozed_until && time() < $snoozed_until ) {
			return false;
		}

		/**
		 * Filters whether the review prompt is due.
		 *
		 * Lets another ask hold it, so only one shows at a time. Free holds
		 * it while the user's onboarding tour is in progress.
		 *
		 * @since 3.2.0
		 *
		 * @param bool $due     Whether the site has reached the attempt threshold.
		 * @param int  $user_id User ID.
		 */
		return (bool) apply_filters( 'pressprimer_quiz_review_prompt_due', self::get_attempt_count() >= self::ATTEMPT_THRESHOLD, $user_id );
	}

	/**
	 * Get completed attempt count
	 *
	 * Cached in a transient for 12 hours.
	 *
	 * @since 2.1.0
	 * @return int
	 */
	private static function get_attempt_count() {
		$count = get_transient( self::COUNT_TRANSIENT );

		if ( false === $count ) {
			global $wpdb;
			$table = $wpdb->prefix . 'ppq_attempts';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, caching implemented.
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
					'submitted'
				)
			);
			set_transient( self::COUNT_TRANSIENT, $count, 12 * HOUR_IN_SECONDS );

			// The object-cache key used before 3.2 (3-character prefix).
			wp_cache_delete( 'ppq_completed_attempt_count' );
		}

		return (int) $count;
	}

	/**
	 * Maybe display the notice
	 *
	 * @since 2.1.0
	 */
	public function maybe_display_notice() {
		if ( ! $this->should_show_notice() ) {
			return;
		}

		$this->render_notice();
	}

	/**
	 * Render the notice HTML
	 *
	 * "Yes, I love it!" and "It could be better" are real links that open in
	 * a new tab; the script only records the answer.
	 *
	 * @since 2.1.0
	 */
	private function render_notice() {
		$nonce = wp_create_nonce( self::NONCE_ACTION );
		?>
		<div class="ppq-celebration-notice" data-ppq-review-nonce="<?php echo esc_attr( $nonce ); ?>">
			<button type="button" class="ppq-celebration-notice__dismiss" data-ppq-review="dismiss" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'pressprimer-quiz' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>

			<div class="ppq-celebration-notice__content">
				<div class="ppq-celebration-notice__icon" aria-hidden="true">🎉</div>

				<div class="ppq-celebration-notice__text">
					<p class="ppq-celebration-notice__title">
						<?php esc_html_e( 'Congratulations! Your students have completed 100 quiz attempts with PressPrimer Quiz!', 'pressprimer-quiz' ); ?>
					</p>
					<p class="ppq-celebration-notice__message">
						<?php esc_html_e( "We'd love to hear your feedback. Are you enjoying the plugin?", 'pressprimer-quiz' ); ?>
					</p>

					<div class="ppq-celebration-notice__actions">
						<a class="button button-primary" href="<?php echo esc_url( self::REVIEW_URL ); ?>" target="_blank" rel="noopener noreferrer" data-ppq-review="review">
							<?php esc_html_e( 'Yes, I love it!', 'pressprimer-quiz' ); ?>
						</a>
						<a class="button button-secondary" href="<?php echo esc_url( self::FEEDBACK_URL ); ?>" target="_blank" rel="noopener noreferrer" data-ppq-review="feedback">
							<?php esc_html_e( 'It could be better', 'pressprimer-quiz' ); ?>
						</a>
						<button type="button" class="button button-link" data-ppq-review="snooze">
							<?php esc_html_e( 'Remind me later', 'pressprimer-quiz' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts for the notice
	 *
	 * @since 2.1.0
	 */
	public function enqueue_scripts() {
		if ( ! $this->should_show_notice() ) {
			return;
		}

		wp_enqueue_script(
			'pressprimer-quiz-review-notice',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/js/review-notice.js',
			array( 'jquery' ),
			PRESSPRIMER_QUIZ_VERSION,
			true
		);
	}

	/**
	 * Check the nonce and capability of an AJAX answer
	 *
	 * @since 3.2.0
	 */
	private function verify_request() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ), 403 );
		}
	}

	/**
	 * Handle review button click
	 *
	 * @since 2.1.0
	 */
	public function handle_review() {
		$this->verify_request();

		update_option( 'pressprimer_quiz_celebration_dismissed', true );
		update_option( 'pressprimer_quiz_celebration_response', 'review' );

		wp_send_json_success();
	}

	/**
	 * Handle feedback button click
	 *
	 * @since 2.1.0
	 */
	public function handle_feedback() {
		$this->verify_request();

		update_option( 'pressprimer_quiz_celebration_dismissed', true );
		update_option( 'pressprimer_quiz_celebration_response', 'feedback' );

		wp_send_json_success();
	}

	/**
	 * Handle snooze ("Remind me later", or Escape inside the notice)
	 *
	 * @since 2.1.0
	 */
	public function handle_snooze() {
		$this->verify_request();

		$snooze_until = time() + self::SNOOZE_DURATION;
		update_option( 'pressprimer_quiz_celebration_snoozed_until', $snooze_until );
		update_option( 'pressprimer_quiz_celebration_response', 'snoozed' );

		wp_send_json_success();
	}

	/**
	 * Handle dismiss button click
	 *
	 * @since 2.1.0
	 */
	public function handle_dismiss() {
		$this->verify_request();

		update_option( 'pressprimer_quiz_celebration_dismissed', true );
		update_option( 'pressprimer_quiz_celebration_response', 'dismissed' );

		wp_send_json_success();
	}
}
