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
class PressPrimer_Quiz_Email_Service {

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
		$attempt = PressPrimer_Quiz_Attempt::get( $attempt_id );
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
		$settings   = get_option( 'pressprimer_quiz_settings', [] );
		$from_name  = isset( $settings['email_from_name'] ) && $settings['email_from_name']
			? $settings['email_from_name']
			: get_bloginfo( 'name' );
		$from_email = isset( $settings['email_from_email'] ) && $settings['email_from_email']
			? $settings['email_from_email']
			: get_bloginfo( 'admin_email' );

		// Get subject and body templates
		$subject_template = isset( $settings['email_results_subject'] ) && $settings['email_results_subject']
			? $settings['email_results_subject']
			: __( 'Your results for {quiz_title}', 'pressprimer-quiz' );
		$body_template    = isset( $settings['email_results_body'] ) && $settings['email_results_body']
			? $settings['email_results_body']
			: self::get_default_body_template();

		// Get student names
		$first_name   = self::get_first_name( $attempt );
		$student_name = self::get_student_name( $attempt );

		// Build token replacements
		$tokens = [
			'{first_name}'   => $first_name,
			'{student_name}' => $student_name, // Keep for backwards compatibility
			'{quiz_title}'   => $quiz->title,
			'{score}'        => round( (float) $attempt->score_percent, 1 ) . '%',
			'{passed}'       => $attempt->passed ? __( 'Passed', 'pressprimer-quiz' ) : __( 'Failed', 'pressprimer-quiz' ),
			'{date}'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $attempt->finished_at ) ),
			'{points}'       => number_format_i18n( $attempt->score_points, 2 ),
			'{max_points}'   => number_format_i18n( $attempt->max_points, 2 ),
			'{site_name}'    => get_bloginfo( 'name' ),
			'{site_url}'     => home_url(),
		];

		// Get results URL and build button HTML for token
		$results_url = self::get_results_url( $attempt );
		if ( $results_url ) {
			$tokens['{results_url}'] = self::build_results_button_html( $results_url );
		} else {
			$tokens['{results_url}'] = '';
		}

		// Build results summary HTML for token
		$results_summary_html        = self::build_results_summary_html( $attempt );
		$tokens['{results_summary}'] = $results_summary_html;

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
			do_action( 'pressprimer_quiz_results_email_sent', $attempt_id, $to_email );
		}

		return $sent;
	}

	/**
	 * Get student first name from attempt
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @return string Student first name.
	 */
	private static function get_first_name( $attempt ) {
		if ( $attempt->user_id ) {
			$user = get_userdata( $attempt->user_id );
			if ( $user ) {
				// Try first_name, fall back to display_name
				$first_name = get_user_meta( $attempt->user_id, 'first_name', true );
				if ( $first_name ) {
					return $first_name;
				}
				// Try to get first word of display name
				$parts = explode( ' ', $user->display_name );
				return $parts[0];
			}
		}

		if ( $attempt->guest_name ) {
			// Try to get first word of guest name
			$parts = explode( ' ', $attempt->guest_name );
			return $parts[0];
		}

		/* translators: Used in email greeting "Hi there" when name is unknown */
		return __( 'there', 'pressprimer-quiz' );
	}

	/**
	 * Get student display name from attempt
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @return string Student display name.
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

		/* translators: Default name when student name is unknown */
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
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @return string Results URL.
	 */
	private static function get_results_url( $attempt ) {
		// Try to get the quiz page URL
		$quiz     = $attempt->get_quiz();
		$base_url = '';

		if ( $quiz ) {
			// Look for a page that contains the quiz shortcode
			global $wpdb;
			$page_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s LIMIT 1",
					'%[pressprimer_quiz id="' . $quiz->id . '"%'
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
	 * @param string                   $body_text Body text with tokens replaced.
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @param PressPrimer_Quiz_Quiz    $quiz Quiz object.
	 * @param array                    $tokens Token replacements.
	 * @return string HTML email body.
	 */
	private static function build_html_email( $body_text, $attempt, $quiz, $tokens ) {
		$settings = get_option( 'pressprimer_quiz_settings', [] );
		$logo_url = isset( $settings['email_logo_url'] ) ? $settings['email_logo_url'] : '';

		$passed_status = $attempt->passed
			? __( 'PASSED', 'pressprimer-quiz' )
			: __( 'FAILED', 'pressprimer-quiz' );
		$passed_color  = $attempt->passed ? '#10b981' : '#ef4444';
		$passed_bg     = $attempt->passed ? '#f0fdf4' : '#fef2f2';

		// Get counts
		$correct_count = 0;
		$total_count   = 0;
		$items         = $attempt->get_items();
		foreach ( $items as $item ) {
			++$total_count;
			if ( $item->is_correct ) {
				++$correct_count;
			}
		}

		// Build header HTML
		$header_html = self::build_email_header( $logo_url, $attempt, $quiz );

		// Build footer HTML
		$footer_html = self::build_email_footer( $attempt, $quiz );

		// Inline styles required: Email clients do not support external stylesheets.
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
			background-color: #ffffff;
			padding: 30px 20px;
			text-align: center;
			border-bottom: 1px solid #e5e5e5;
		}
		.email-header img {
			max-width: 400px;
			max-height: 150px;
			height: auto;
		}
		.email-header h1 {
			margin: 0;
			font-size: 24px;
			font-weight: 700;
			color: #1a1a1a;
		}
		.email-body {
			padding: 30px 20px;
		}
		.message-content {
			line-height: 1.8;
			color: #4b5563;
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
		<?php echo wp_kses_post( $header_html ); ?>

		<div class="email-body">
			<div class="message-content">
				<?php echo wp_kses_post( nl2br( $body_text ) ); ?>
			</div>
		</div>

		<?php echo wp_kses_post( $footer_html ); ?>
	</div>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build results summary HTML for token
	 *
	 * Creates the visual score box HTML that can be inserted via {results_summary} token.
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @return string HTML for results summary.
	 */
	private static function build_results_summary_html( $attempt ) {
		$passed_status = $attempt->passed
			? __( 'PASSED', 'pressprimer-quiz' )
			: __( 'FAILED', 'pressprimer-quiz' );
		$passed_color  = $attempt->passed ? '#10b981' : '#ef4444';
		$passed_bg     = $attempt->passed ? '#f0fdf4' : '#fef2f2';

		// Get counts
		$correct_count = 0;
		$total_count   = 0;
		$items         = $attempt->get_items();
		foreach ( $items as $item ) {
			++$total_count;
			if ( $item->is_correct ) {
				++$correct_count;
			}
		}

		$details_text = sprintf(
			/* translators: 1: correct count, 2: total count */
			__( '%1$d / %2$d correct', 'pressprimer-quiz' ),
			(int) $correct_count,
			(int) $total_count
		);

		// Build inline-styled HTML (email-safe)
		$html  = '<div style="background-color: ' . esc_attr( $passed_bg ) . '; border: 2px solid ' . esc_attr( $passed_color ) . '; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 20px;">';
		$html .= '<div style="font-size: 48px; font-weight: 700; color: #1a1a1a; margin: 0 0 10px;">' . esc_html( round( (float) $attempt->score_percent, 1 ) ) . '%</div>';
		$html .= '<div style="font-size: 16px; color: #666666; margin-bottom: 15px;">' . esc_html( $details_text ) . '</div>';
		$html .= '<div style="display: inline-block; padding: 10px 20px; background-color: ' . esc_attr( $passed_color ) . '; color: #ffffff; border-radius: 4px; font-weight: 600; font-size: 14px;">' . esc_html( $passed_status ) . '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Build test results summary HTML for test emails
	 *
	 * Creates sample visual score box HTML for test emails.
	 *
	 * @since 1.0.0
	 *
	 * @return string HTML for results summary.
	 */
	private static function build_test_results_summary_html() {
		$passed_color = '#10b981';
		$passed_bg    = '#f0fdf4';

		$details_text = sprintf(
			/* translators: 1: correct count, 2: total count */
			__( '%1$d / %2$d correct', 'pressprimer-quiz' ),
			17,
			20
		);

		// Build inline-styled HTML (email-safe)
		$html  = '<div style="background-color: ' . esc_attr( $passed_bg ) . '; border: 2px solid ' . esc_attr( $passed_color ) . '; border-radius: 8px; padding: 20px; text-align: center; margin-bottom: 20px;">';
		$html .= '<div style="font-size: 48px; font-weight: 700; color: #1a1a1a; margin: 0 0 10px;">85%</div>';
		$html .= '<div style="font-size: 16px; color: #666666; margin-bottom: 15px;">' . esc_html( $details_text ) . '</div>';
		$html .= '<div style="display: inline-block; padding: 10px 20px; background-color: ' . esc_attr( $passed_color ) . '; color: #ffffff; border-radius: 4px; font-weight: 600; font-size: 14px;">' . esc_html__( 'PASSED', 'pressprimer-quiz' ) . '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Build results button HTML for token
	 *
	 * Creates the "View Full Results" button HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The results URL.
	 * @return string HTML for results button.
	 */
	private static function build_results_button_html( $url ) {
		$html  = '<div style="text-align: center; margin: 20px 0;">';
		$html .= '<a href="' . esc_url( $url ) . '" style="display: inline-block; padding: 12px 30px; background-color: #3b82f6; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600;">';
		$html .= esc_html__( 'View Full Results', 'pressprimer-quiz' );
		$html .= '</a>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Build email header HTML
	 *
	 * @since 1.0.0
	 *
	 * @param string                   $logo_url Logo URL.
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @param PressPrimer_Quiz_Quiz    $quiz Quiz object.
	 * @return string Header HTML.
	 */
	private static function build_email_header( $logo_url, $attempt, $quiz ) {
		ob_start();
		?>
		<div class="email-header">
			<?php if ( $logo_url ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php else : ?>
				<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
			<?php endif; ?>
		</div>
		<?php
		$header = ob_get_clean();

		/**
		 * Filter the email header HTML.
		 *
		 * @since 1.0.0
		 *
		 * @param string      $header Header HTML.
		 * @param string      $logo_url Logo URL.
		 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
		 * @param PressPrimer_Quiz_Quiz    $quiz Quiz object.
		 */
		return apply_filters( 'pressprimer_quiz_email_header', $header, $logo_url, $attempt, $quiz );
	}

	/**
	 * Build email footer HTML
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @param PressPrimer_Quiz_Quiz    $quiz Quiz object.
	 * @return string Footer HTML.
	 */
	private static function build_email_footer( $attempt, $quiz ) {
		ob_start();
		?>
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
		<?php
		$footer = ob_get_clean();

		/**
		 * Filter the email footer HTML.
		 *
		 * @since 1.0.0
		 *
		 * @param string      $footer Footer HTML.
		 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
		 * @param PressPrimer_Quiz_Quiz    $quiz Quiz object.
		 */
		return apply_filters( 'pressprimer_quiz_email_footer', $footer, $attempt, $quiz );
	}

	/**
	 * Get default email body template
	 *
	 * @since 1.0.0
	 *
	 * @return string Default body template.
	 */
	private static function get_default_body_template() {
		return __(
			'{results_summary}

Hi {first_name},

You recently completed the quiz "{quiz_title}".

Here are your results:
- Score: {score}
- Status: {passed}
- Date: {date}

Good luck with your studies!

{results_url}',
			'pressprimer-quiz'
		);
	}

	/**
	 * Send test email
	 *
	 * Sends a test email using the current template settings.
	 *
	 * @since 1.0.0
	 *
	 * @param string $to_email Recipient email address.
	 * @return bool True on success, false on failure.
	 */
	public static function send_test_email( $to_email ) {
		// Validate email
		if ( ! is_email( $to_email ) ) {
			return false;
		}

		// Get current user info for test data
		$current_user = wp_get_current_user();
		$first_name   = $current_user->first_name ?: $current_user->display_name;
		$parts        = explode( ' ', $first_name );
		$first_name   = $parts[0];

		// Get email settings
		$settings   = get_option( 'pressprimer_quiz_settings', [] );
		$from_name  = isset( $settings['email_from_name'] ) && $settings['email_from_name']
			? $settings['email_from_name']
			: get_bloginfo( 'name' );
		$from_email = isset( $settings['email_from_email'] ) && $settings['email_from_email']
			? $settings['email_from_email']
			: get_bloginfo( 'admin_email' );

		// Get subject and body templates
		$subject_template = isset( $settings['email_results_subject'] ) && $settings['email_results_subject']
			? $settings['email_results_subject']
			: __( 'Your results for {quiz_title}', 'pressprimer-quiz' );
		$body_template    = isset( $settings['email_results_body'] ) && $settings['email_results_body']
			? $settings['email_results_body']
			: self::get_default_body_template();

		// Build sample token replacements
		$tokens = [
			'{first_name}'   => $first_name ?: __( 'there', 'pressprimer-quiz' ),
			'{student_name}' => $current_user->display_name ?: __( 'Test Student', 'pressprimer-quiz' ),
			'{quiz_title}'   => __( 'Sample Quiz', 'pressprimer-quiz' ),
			'{score}'        => '85%',
			'{passed}'       => __( 'Passed', 'pressprimer-quiz' ),
			'{date}'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'{points}'       => '17.00',
			'{max_points}'   => '20.00',
			'{site_name}'    => get_bloginfo( 'name' ),
			'{site_url}'     => home_url(),
		];

		// Build sample results summary HTML for test email
		$tokens['{results_summary}'] = self::build_test_results_summary_html();

		// Build results button HTML for test email
		$tokens['{results_url}'] = self::build_results_button_html( home_url( '/sample-quiz-results/' ) );

		// Replace tokens in subject
		$subject = str_replace( array_keys( $tokens ), array_values( $tokens ), $subject_template );
		$subject = '[' . __( 'TEST', 'pressprimer-quiz' ) . '] ' . $subject;

		// Replace tokens in body
		$body_text = str_replace( array_keys( $tokens ), array_values( $tokens ), $body_template );

		// Build HTML email
		$html_body = self::build_test_html_email( $body_text, $tokens );

		// Set headers
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		];

		// Send email
		return wp_mail( $to_email, $subject, $html_body, $headers );
	}

	/**
	 * Build test HTML email body
	 *
	 * Creates formatted HTML email for testing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $body_text Body text with tokens replaced.
	 * @param array  $tokens Token replacements.
	 * @return string HTML email body.
	 */
	private static function build_test_html_email( $body_text, $tokens ) {
		$settings = get_option( 'pressprimer_quiz_settings', [] );
		$logo_url = isset( $settings['email_logo_url'] ) ? $settings['email_logo_url'] : '';

		// Build header HTML
		ob_start();
		?>
		<div class="email-header">
			<?php if ( $logo_url ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			<?php else : ?>
				<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
			<?php endif; ?>
		</div>
		<?php
		$header_html = ob_get_clean();

		// Build footer HTML
		ob_start();
		?>
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
		<?php
		$footer_html = ob_get_clean();

		// Inline styles required: Email clients do not support external stylesheets.
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
			background-color: #ffffff;
			padding: 30px 20px;
			text-align: center;
			border-bottom: 1px solid #e5e5e5;
		}
		.email-header img {
			max-width: 400px;
			max-height: 150px;
			height: auto;
		}
		.email-header h1 {
			margin: 0;
			font-size: 24px;
			font-weight: 700;
			color: #1a1a1a;
		}
		.email-body {
			padding: 30px 20px;
		}
		.test-banner {
			background-color: #fef3c7;
			border: 1px solid #f59e0b;
			border-radius: 6px;
			padding: 12px 16px;
			margin-bottom: 20px;
			text-align: center;
			color: #92400e;
			font-weight: 600;
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
		<?php echo wp_kses_post( $header_html ); ?>

		<div class="email-body">
			<div class="test-banner">
				<?php esc_html_e( 'This is a test email with sample data', 'pressprimer-quiz' ); ?>
			</div>

			<div class="message-content">
				<?php echo wp_kses_post( nl2br( $body_text ) ); ?>
			</div>
		</div>

		<?php echo wp_kses_post( $footer_html ); ?>
	</div>
</body>
</html>
		<?php
		return ob_get_clean();
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
		$settings  = get_option( 'pressprimer_quiz_settings', [] );
		$auto_send = isset( $settings['email_results_auto_send'] ) && $settings['email_results_auto_send'];

		if ( ! $auto_send ) {
			return;
		}

		// Load attempt
		$attempt = PressPrimer_Quiz_Attempt::get( $attempt_id );
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
