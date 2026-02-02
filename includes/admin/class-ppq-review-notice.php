<?php
/**
 * Review notice class
 *
 * Handles the 100 attempts celebration notice.
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

		// Must have capability.
		if ( ! current_user_can( 'pressprimer_quiz_manage_settings' ) ) {
			return false;
		}

		// Check if permanently dismissed.
		if ( get_option( 'pressprimer_quiz_celebration_dismissed', false ) ) {
			return false;
		}

		// Check if snoozed.
		$snoozed_until = get_option( 'pressprimer_quiz_celebration_snoozed_until', 0 );
		if ( $snoozed_until && time() < $snoozed_until ) {
			return false;
		}

		// Check attempt count.
		return $this->get_attempt_count() >= self::ATTEMPT_THRESHOLD;
	}

	/**
	 * Get completed attempt count
	 *
	 * @since 2.1.0
	 * @return int
	 */
	private function get_attempt_count() {
		global $wpdb;
		$table = $wpdb->prefix . 'ppq_attempts';

		$cache_key = 'ppq_completed_attempt_count';
		$count     = wp_cache_get( $cache_key );

		if ( false === $count ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table, caching implemented.
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
					'submitted'
				)
			);
			wp_cache_set( $cache_key, $count, '', HOUR_IN_SECONDS );
		}

		return $count;
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
	 * @since 2.1.0
	 */
	private function render_notice() {
		$nonce = wp_create_nonce( 'ppq_celebration_nonce' );
		?>
		<div class="ppq-celebration-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<button type="button" class="ppq-celebration-notice__dismiss" data-action="dismiss" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'pressprimer-quiz' ); ?>">
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
						<button type="button" class="button button-primary" data-action="review">
							<?php esc_html_e( 'Yes, I love it!', 'pressprimer-quiz' ); ?>
						</button>
						<button type="button" class="button button-secondary" data-action="feedback">
							<?php esc_html_e( 'It could be better', 'pressprimer-quiz' ); ?>
						</button>
						<button type="button" class="button button-link" data-action="snooze">
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

		wp_add_inline_script(
			'jquery',
			$this->get_inline_script(),
			'after'
		);
	}

	/**
	 * Get inline JavaScript for notice interactions
	 *
	 * @since 2.1.0
	 * @return string
	 */
	private function get_inline_script() {
		return "
		jQuery(function($) {
			var notice = $('.ppq-celebration-notice');
			if (!notice.length) return;

			var nonce = notice.data('nonce');

			notice.on('click', '[data-action]', function(e) {
				e.preventDefault();
				var action = $(this).data('action');
				var ajaxAction = 'pressprimer_quiz_celebration_' + action;

				$.post(ajaxurl, {
					action: ajaxAction,
					nonce: nonce
				}, function(response) {
					if (response.success) {
						notice.fadeOut(300, function() {
							notice.remove();
						});

						if (response.data && response.data.redirect) {
							window.open(response.data.redirect, '_blank');
						}
					}
				});
			});
		});
		";
	}

	/**
	 * Handle review button click
	 *
	 * @since 2.1.0
	 */
	public function handle_review() {
		check_ajax_referer( 'ppq_celebration_nonce', 'nonce' );

		if ( ! current_user_can( 'pressprimer_quiz_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ) );
		}

		update_option( 'pressprimer_quiz_celebration_dismissed', true );
		update_option( 'pressprimer_quiz_celebration_response', 'review' );

		wp_send_json_success(
			array(
				'redirect' => 'https://wordpress.org/support/plugin/pressprimer-quiz/reviews/#new-post',
			)
		);
	}

	/**
	 * Handle feedback button click
	 *
	 * @since 2.1.0
	 */
	public function handle_feedback() {
		check_ajax_referer( 'ppq_celebration_nonce', 'nonce' );

		if ( ! current_user_can( 'pressprimer_quiz_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ) );
		}

		update_option( 'pressprimer_quiz_celebration_dismissed', true );
		update_option( 'pressprimer_quiz_celebration_response', 'feedback' );

		wp_send_json_success(
			array(
				'redirect' => 'https://pressprimer.com/contact/',
			)
		);
	}

	/**
	 * Handle snooze button click
	 *
	 * @since 2.1.0
	 */
	public function handle_snooze() {
		check_ajax_referer( 'ppq_celebration_nonce', 'nonce' );

		if ( ! current_user_can( 'pressprimer_quiz_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ) );
		}

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
		check_ajax_referer( 'ppq_celebration_nonce', 'nonce' );

		if ( ! current_user_can( 'pressprimer_quiz_manage_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ) );
		}

		update_option( 'pressprimer_quiz_celebration_dismissed', true );
		update_option( 'pressprimer_quiz_celebration_response', 'dismissed' );

		wp_send_json_success();
	}
}
