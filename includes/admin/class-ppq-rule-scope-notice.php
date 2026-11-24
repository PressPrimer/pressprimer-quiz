<?php
/**
 * Rule Scope Notice
 *
 * One-time disclosure after the 3.2 update (feature 001 FR-004.6). From 3.2,
 * rules on a quiz whose owner lacks pressprimer_quiz_manage_all draw only from
 * questions the owner may use. This notice lists the quizzes whose rules now
 * match fewer questions, for manage_all users, until one of them dismisses it.
 *
 * @package PressPrimer_Quiz
 * @subpackage Admin
 * @since 3.2.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rule scope notice class
 *
 * @since 3.2.0
 */
class PressPrimer_Quiz_Rule_Scope_Notice {

	/**
	 * Option holding the affected quiz IDs, or the pending marker.
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const OPTION = 'pressprimer_quiz_rule_scope_affected';

	/**
	 * Option value while the scan hasn't run yet.
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const PENDING = 'pending';

	/**
	 * Admin-post action (and nonce action) for dismissing the notice.
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const DISMISS_ACTION = 'pressprimer_quiz_dismiss_rule_scope_notice';

	/**
	 * Most quizzes listed by name; the rest are counted.
	 *
	 * @since 3.2.0
	 * @var int
	 */
	const MAX_LISTED = 20;

	/**
	 * Register the notice and its dismiss handler.
	 *
	 * @since 3.2.0
	 */
	public static function init() {
		add_action( 'admin_notices', [ __CLASS__, 'maybe_render_notice' ] );
		add_action( 'admin_post_' . self::DISMISS_ACTION, [ __CLASS__, 'handle_dismiss' ] );
	}

	/**
	 * Queue the scan. Called by the 3.2.0 migration step.
	 *
	 * Does nothing when a scan is already queued or its result is stored.
	 *
	 * @since 3.2.0
	 */
	public static function queue_scan() {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::PENDING, '', false );
		}
	}

	/**
	 * Find the quizzes whose rules match fewer questions under owner scoping.
	 *
	 * Checks dynamic quizzes whose owner lacks pressprimer_quiz_manage_all.
	 * Runs once all plugins have loaded, so shared banks count.
	 *
	 * @since 3.2.0
	 *
	 * @return int[] Quiz IDs.
	 */
	public static function scan() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time scan.
		$quizzes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, owner_id FROM {$wpdb->prefix}ppq_quizzes WHERE generation_mode = %s ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
				'dynamic'
			)
		);

		$affected = [];

		foreach ( $quizzes as $quiz ) {
			$owner_id = absint( $quiz->owner_id );

			if ( $owner_id && user_can( $owner_id, 'pressprimer_quiz_manage_all' ) ) {
				continue;
			}

			foreach ( PressPrimer_Quiz_Quiz_Rule::get_for_quiz( absint( $quiz->id ) ) as $rule ) {
				if ( $rule->is_narrowed_by_owner_scope() ) {
					$affected[] = absint( $quiz->id );
					break;
				}
			}
		}

		return $affected;
	}

	/**
	 * Show the notice on PressPrimer screens for pressprimer_quiz_manage_all users.
	 *
	 * Runs the queued scan first. Clears the option when no listed quiz is left.
	 *
	 * @since 3.2.0
	 */
	public static function maybe_render_notice() {
		global $wpdb;

		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) || ! self::is_plugin_screen() ) {
			return;
		}

		$affected = get_option( self::OPTION, [] );

		if ( empty( $affected ) ) {
			return;
		}

		if ( self::PENDING === $affected ) {
			$affected = self::scan();

			if ( empty( $affected ) ) {
				delete_option( self::OPTION );
				return;
			}

			update_option( self::OPTION, $affected, false );
		}

		$quiz_ids = is_array( $affected ) ? array_values( array_filter( array_map( 'absint', $affected ) ) ) : [];
		$quizzes  = [];

		if ( ! empty( $quiz_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $quiz_ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Titles for a short-lived notice.
			$quizzes = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, title FROM {$wpdb->prefix}ppq_quizzes WHERE id IN ({$placeholders}) ORDER BY title ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix; placeholders built above.
					$quiz_ids
				)
			);
		}

		// Every listed quiz was deleted since the scan.
		if ( empty( $quizzes ) ) {
			delete_option( self::OPTION );
			return;
		}

		$plugin_name = apply_filters( 'pressprimer_quiz_plugin_name', __( 'PressPrimer Quiz', 'pressprimer-quiz' ) );
		$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION ), self::DISMISS_ACTION );
		$listed      = array_slice( $quizzes, 0, self::MAX_LISTED );
		$more        = count( $quizzes ) - count( $listed );
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php echo esc_html( $plugin_name ); ?>:</strong>
				<?php esc_html_e( 'These quizzes now draw only from their owners\' own and shared questions.', 'pressprimer-quiz' ); ?>
			</p>
			<p><?php esc_html_e( 'Before this update, their rules could also draw questions their owners can\'t use, such as other teachers\' private questions. Check that each quiz still has enough questions to draw.', 'pressprimer-quiz' ); ?></p>
			<ul class="ul-disc">
				<?php foreach ( $listed as $quiz ) : ?>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pressprimer-quiz-quizzes&action=edit&quiz=' . absint( $quiz->id ) ) ); ?>">
							<?php echo '' !== (string) $quiz->title ? esc_html( $quiz->title ) : esc_html__( '(no title)', 'pressprimer-quiz' ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $more > 0 ) : ?>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of quizzes not listed by name */
							_n( 'And %d more quiz.', 'And %d more quizzes.', $more, 'pressprimer-quiz' ),
							$more
						)
					);
					?>
				</p>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( $dismiss_url ); ?>" class="button"><?php esc_html_e( 'Dismiss', 'pressprimer-quiz' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Dismiss the notice for everyone by clearing the option.
	 *
	 * @since 3.2.0
	 */
	public static function handle_dismiss() {
		check_admin_referer( self::DISMISS_ACTION );

		if ( ! current_user_can( 'pressprimer_quiz_manage_all' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'pressprimer-quiz' ) );
		}

		delete_option( self::OPTION );

		$redirect = wp_get_referer();
		wp_safe_redirect( $redirect ? $redirect : admin_url( 'admin.php?page=pressprimer-quiz' ) );
		exit;
	}

	/**
	 * Check whether the current screen belongs to the plugin.
	 *
	 * @since 3.2.0
	 *
	 * @return bool
	 */
	private static function is_plugin_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen ) {
			return false;
		}

		return false !== strpos( $screen->id, 'ppq-' )
			|| false !== strpos( $screen->id, 'pressprimer-quiz' );
	}
}
