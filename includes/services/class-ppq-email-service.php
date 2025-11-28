<?php
/**
 * Email Service
 *
 * Handles sending quiz results via email.
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
 * Email Service class
 *
 * Provides email functionality for quiz results.
 *
 * @since 1.0.0
 */
class PPQ_Email_Service {

	/**
	 * Send results email
	 *
	 * Sends quiz results to specified email address.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $attempt_id Attempt ID.
	 * @param string $to_email Recipient email address.
	 * @return bool True on success, false on failure.
	 */
	public static function send_results( $attempt_id, $to_email ) {
		// Load attempt
		$attempt = PPQ_Attempt::get( $attempt_id );
		if ( ! $attempt || 'submitted' !== $attempt->status ) {
			return false;
		}

		// Load quiz
		$quiz = $attempt->get_quiz();
		if ( ! $quiz ) {
			return false;
		}

		// Validate email
		if ( ! is_email( $to_email ) ) {
			return false;
		}

		// Get email settings
		$settings = get_option( 'ppq_settings', [] );
		$from_name = isset( $settings['email_from_name'] ) && $settings['email_from_name']
			? $settings['email_from_name']
			: get_bloginfo( 'name' );
		$from_email = isset( $settings['email_from_email'] ) && $settings['email_from_email']
			? $settings['email_from_email']
			: get_bloginfo( 'admin_email' );

		// Get subject and body templates
		$subject_template = isset( $settings['email_results_subject'] ) && $settings['email_results_subject']
			? $settings['email_results_subject']
			: __( 'Your results for {quiz_title}', 'pressprimer-quiz' );
		$body_template = isset( $settings['email_results_body'] ) && $settings['email_results_body']
			? $settings['email_results_body']
			: self::get_default_body_template();

		// Get student name
		$student_name = self::get_student_name( $attempt );

		// Build token replacements
		$tokens = [
			'{student_name}' => $student_name,
			'{quiz_title}'   => $quiz->title,
			'{score}'        => round( (float) $attempt->score_percent, 1 ) . '%',
			'{passed}'       => $attempt->passed ? __( 'Passed', 'pressprimer-quiz' ) : __( 'Failed', 'pressprimer-quiz' ),
			'{date}'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $attempt->finished_at ) ),
			'{points}'       => number_format( $attempt->score_points, 2 ),
			'{max_points}'   => number_format( $attempt->max_points, 2 ),
			'{site_name}'    => get_bloginfo( 'name' ),
			'{site_url}'     => home_url(),
		];

		// Get results URL if available
		$results_url = self::get_results_url( $attempt );
		if ( $results_url ) {
			$tokens['{results_url}'] = $results_url;
		}

		// Replace tokens in subject
		$subject = str_replace( array_keys( $tokens ), array_values( $tokens ), $subject_template );

		// Replace tokens in body
		$body_text = str_replace( array_keys( $tokens ), array_values( $tokens ), $body_template );

		// Build HTML email
		$html_body = self::build_html_email( $body_text, $attempt, $quiz, $tokens );

		// Set headers
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		];

		// Send email
		$sent = wp_mail( $to_email, $subject, $html_body, $headers );

		// Log event if sent successfully
		if ( $sent ) {
			do_action( 'ppq_results_email_sent', $attempt_id, $to_email );
		}

		return $sent;
	}

	/**
	 * Get student name from attempt
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Attempt $attempt Attempt object.
	 * @return string Student name.
	 */
	private static function get_student_name( $attempt ) {
		if ( $attempt->user_id ) {
			$user = get_userdata( $attempt->user_id );
			if ( $user ) {
				return $user->display_name;
			}
		}

		if ( $attempt->guest_name ) {
			return $attempt->guest_name;
		}

		if ( $attempt->guest_email ) {
			return $attempt->guest_email;
		}

		return __( 'Student', 'pressprimer-quiz' );
	}

	/**
	 * Get results URL for attempt
	 *
	 * Generates a secure URL for viewing quiz results.
	 * For guests, includes the secure token that expires after 30 days.
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Attempt $attempt Attempt object.
	 * @return string Results URL.
	 */
	private static function get_results_url( $attempt ) {
		// Try to get the quiz page URL
		$quiz = $attempt->get_quiz();
		$base_url = '';

		if ( $quiz ) {
			// Look for a page that contains the quiz shortcode
			global $wpdb;
			$page_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s LIMIT 1",
					'%[ppq_quiz id="' . $quiz->id . '"%'
				)
			);

			if ( $page_id ) {
				$base_url = get_permalink( $page_id );
			}
		}

		// Fallback to home URL if no quiz page found
		if ( empty( $base_url ) ) {
			$base_url = home_url( '/' );
		}

		// Use the attempt's get_results_url method which handles tokens for guests
		return $attempt->get_results_url( $base_url );
	}

	/**
	 * Build HTML email body
	 *
	 * Creates formatted HTML email with results summary.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $body_text Body text with tokens replaced.
	 * @param PPQ_Attempt $attempt Attempt object.
	 * @param PPQ_Quiz    $quiz Quiz object.
	 * @param array       $tokens Token replacements.
	 * @return string HTML email body.
	 */
	private static function build_html_email( $body_text, $attempt, $quiz, $tokens ) {
		$passed_status = $attempt->passed
			? __( 'PASSED', 'pressprimer-quiz' )
			: __( 'FAILED', 'pressprimer-quiz' );
		$passed_color = $attempt->passed ? '#10b981' : '#ef4444';
		$passed_bg = $attempt->passed ? '#f0fdf4' : '#fef2f2';

		ob_start();
		?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
			line-height: 1.6;
			color: #333333;
			background-color: #f5f5f5;
			margin: 0;
			padding: 0;
		}
		.email-container {
			max-width: 600px;
			margin: 20px auto;
			background-color: #ffffff;
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
		}
		.email-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #ffffff;
			padding: 30px 20px;
			text-align: center;
		}
		.email-header h1 {
			margin: 0;
			font-size: 24px;
			font-weight: 700;
		}
		.email-body {
			padding: 30px 20px;
		}
		.score-summary {
			background-color: <?php echo esc_attr( $passed_bg ); ?>;
			border: 2px solid <?php echo esc_attr( $passed_color ); ?>;
			border-radius: 8px;
			padding: 20px;
			text-align: center;
			margin-bottom: 30px;
		}
		.score-percentage {
			font-size: 48px;
			font-weight: 700;
			color: #1a1a1a;
			margin: 0 0 10px;
		}
		.score-details {
			font-size: 16px;
			color: #666666;
			margin-bottom: 15px;
		}
		.pass-status {
			display: inline-block;
			padding: 10px 20px;
			background-color: <?php echo esc_attr( $passed_color ); ?>;
			color: #ffffff;
			border-radius: 4px;
			font-weight: 600;
			font-size: 14px;
		}
		.results-table {
			width: 100%;
			border-collapse: collapse;
			margin: 20px 0;
		}
		.results-table th {
			text-align: left;
			padding: 10px;
			background-color: #f9fafb;
			border-bottom: 2px solid #e5e5e5;
			font-weight: 600;
			color: #374151;
		}
		.results-table td {
			padding: 10px;
			border-bottom: 1px solid #f3f4f6;
			color: #1a1a1a;
		}
		.message-content {
			line-height: 1.8;
			color: #4b5563;
			margin: 20px 0;
		}
		.message-content p {
			margin: 0 0 15px;
		}
		.cta-button {
			display: inline-block;
			padding: 12px 30px;
			background-color: #3b82f6;
			color: #ffffff;
			text-decoration: none;
			border-radius: 6px;
			font-weight: 600;
			margin: 20px 0;
		}
		.email-footer {
			background-color: #f9fafb;
			padding: 20px;
			text-align: center;
			color: #6b7280;
			font-size: 14px;
			border-top: 1px solid #e5e5e5;
		}
		.email-footer p {
			margin: 5px 0;
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
		</div>

		<div class="email-body">
			<div class="score-summary">
				<div class="score-percentage"><?php echo esc_html( round( (float) $attempt->score_percent, 1 ) ); ?>%</div>
				<div class="score-details">
					<?php
					$correct_count = 0;
					$total_count = 0;
					$items = $attempt->get_items();
					foreach ( $items as $item ) {
						$total_count++;
						if ( $item->is_correct ) {
							$correct_count++;
						}
					}
					printf(
						/* translators: 1: correct count, 2: total count */
						esc_html__( '%1$d / %2$d correct', 'pressprimer-quiz' ),
						(int) $correct_count,
						(int) $total_count
					);
					?>
				</div>
				<div class="pass-status"><?php echo esc_html( $passed_status ); ?></div>
			</div>

			<table class="results-table">
				<tr>
					<th><?php esc_html_e( 'Quiz', 'pressprimer-quiz' ); ?></th>
					<td><?php echo esc_html( $quiz->title ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Student', 'pressprimer-quiz' ); ?></th>
					<td><?php echo esc_html( $tokens['{student_name}'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Score', 'pressprimer-quiz' ); ?></th>
					<td><?php echo esc_html( $tokens['{score}'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Points', 'pressprimer-quiz' ); ?></th>
					<td><?php echo esc_html( $tokens['{points}'] . ' / ' . $tokens['{max_points}'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Date', 'pressprimer-quiz' ); ?></th>
					<td><?php echo esc_html( $tokens['{date}'] ); ?></td>
				</tr>
			</table>

			<div class="message-content">
				<?php echo wp_kses_post( wpautop( $body_text ) ); ?>
			</div>

			<?php if ( isset( $tokens['{results_url}'] ) && $tokens['{results_url}'] ) : ?>
				<div style="text-align: center;">
					<a href="<?php echo esc_url( $tokens['{results_url}'] ); ?>" class="cta-button">
						<?php esc_html_e( 'View Full Results', 'pressprimer-quiz' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</div>

		<div class="email-footer">
			<p>
				<?php
				printf(
					/* translators: %s: site name */
					esc_html__( 'This email was sent from %s', 'pressprimer-quiz' ),
					esc_html( get_bloginfo( 'name' ) )
				);
				?>
			</p>
			<p><?php echo esc_html( home_url() ); ?></p>
		</div>
	</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get default email body template
	 *
	 * @since 1.0.0
	 *
	 * @return string Default body template.
	 */
	private static function get_default_body_template() {
		return __( 'Hi {student_name},

You recently completed the quiz "{quiz_title}".

Here are your results:
- Score: {score}
- Status: {passed}
- Date: {date}

Click the button below to view your full results and review your answers.

Good luck with your studies!', 'pressprimer-quiz' );
	}

	/**
	 * Send results automatically on quiz completion
	 *
	 * Hooked to quiz submission if auto-send is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attempt_id Attempt ID.
	 */
	public static function maybe_send_on_completion( $attempt_id ) {
		$settings = get_option( 'ppq_settings', [] );
		$auto_send = isset( $settings['email_results_auto_send'] ) && $settings['email_results_auto_send'];

		if ( ! $auto_send ) {
			return;
		}

		// Load attempt
		$attempt = PPQ_Attempt::get( $attempt_id );
		if ( ! $attempt ) {
			return;
		}

		// Determine recipient
		$to_email = null;
		if ( $attempt->user_id ) {
			$user = get_userdata( $attempt->user_id );
			if ( $user ) {
				$to_email = $user->user_email;
			}
		} elseif ( $attempt->guest_email ) {
			$to_email = $attempt->guest_email;
		}

		if ( $to_email ) {
			self::send_results( $attempt_id, $to_email );
		}
	}
}
