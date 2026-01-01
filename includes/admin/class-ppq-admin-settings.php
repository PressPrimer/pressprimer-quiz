<?php
/**
 * Admin settings class
 *
 * Handles plugin settings page and configuration.
 *
 * @package PressPrimer_Quiz
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin settings class
 *
 * Manages plugin settings using WordPress Settings API.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Admin_Settings {

	/**
	 * Settings option name
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_NAME = 'pressprimer_quiz_settings';

	/**
	 * Initialize settings
	 *
	 * Registers settings, sections, and fields.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		// AJAX handlers for API key management
		add_action( 'wp_ajax_pressprimer_quiz_save_user_api_key', [ $this, 'ajax_save_user_api_key' ] );
		add_action( 'wp_ajax_pressprimer_quiz_validate_api_key', [ $this, 'ajax_validate_api_key' ] );
		add_action( 'wp_ajax_pressprimer_quiz_clear_user_api_key', [ $this, 'ajax_clear_user_api_key' ] );
		add_action( 'wp_ajax_pressprimer_quiz_get_api_models', [ $this, 'ajax_get_api_models' ] );
		add_action( 'wp_ajax_pressprimer_quiz_save_site_api_key', [ $this, 'ajax_save_site_api_key' ] );
		add_action( 'wp_ajax_pressprimer_quiz_clear_site_api_key', [ $this, 'ajax_clear_site_api_key' ] );

		// AJAX handler for database repair
		add_action( 'wp_ajax_pressprimer_quiz_repair_database_tables', [ $this, 'ajax_repair_database_tables' ] );
	}

	/**
	 * Register settings
	 *
	 * Registers all settings, sections, and fields using Settings API.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		// Register the main settings option
		register_setting(
			'pressprimer_quiz_settings_group',
			self::OPTION_NAME,
			[
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
			]
		);

		// General Section
		add_settings_section(
			'pressprimer_quiz_general_section',
			__( 'General Settings', 'pressprimer-quiz' ),
			[ $this, 'render_general_section' ],
			'pressprimer-quiz-settings'
		);

		// Quiz Defaults Section
		add_settings_section(
			'ppq_quiz_defaults_section',
			__( 'Quiz Defaults', 'pressprimer-quiz' ),
			[ $this, 'render_quiz_defaults_section' ],
			'pressprimer-quiz-settings'
		);

		// Email Section
		add_settings_section(
			'ppq_email_section',
			__( 'Email Settings', 'pressprimer-quiz' ),
			[ $this, 'render_email_section' ],
			'pressprimer-quiz-settings'
		);

		// API Keys Section
		add_settings_section(
			'ppq_api_keys_section',
			__( 'API Keys', 'pressprimer-quiz' ),
			[ $this, 'render_api_keys_section' ],
			'pressprimer-quiz-settings'
		);

		// Social Sharing Section
		add_settings_section(
			'ppq_social_sharing_section',
			__( 'Social Sharing', 'pressprimer-quiz' ),
			[ $this, 'render_social_sharing_section' ],
			'pressprimer-quiz-settings'
		);

		// Advanced Section
		add_settings_section(
			'ppq_advanced_section',
			__( 'Advanced Settings', 'pressprimer-quiz' ),
			[ $this, 'render_advanced_section' ],
			'pressprimer-quiz-settings'
		);

		// Register individual fields
		$this->register_general_fields();
		$this->register_quiz_default_fields();
		$this->register_email_fields();
		$this->register_api_key_fields();
		$this->register_social_sharing_fields();
		$this->register_advanced_fields();
	}

	/**
	 * Register general fields
	 *
	 * @since 1.0.0
	 */
	private function register_general_fields() {
		// No general fields in v1.0, but structure is here for future
	}

	/**
	 * Register quiz default fields
	 *
	 * @since 1.0.0
	 */
	private function register_quiz_default_fields() {
		// Default passing score
		add_settings_field(
			'default_passing_score',
			__( 'Default Passing Score', 'pressprimer-quiz' ),
			[ $this, 'render_passing_score_field' ],
			'pressprimer-quiz-settings',
			'ppq_quiz_defaults_section'
		);

		// Default quiz mode
		add_settings_field(
			'default_quiz_mode',
			__( 'Default Quiz Mode', 'pressprimer-quiz' ),
			[ $this, 'render_quiz_mode_field' ],
			'pressprimer-quiz-settings',
			'ppq_quiz_defaults_section'
		);
	}

	/**
	 * Register email fields
	 *
	 * @since 1.0.0
	 */
	private function register_email_fields() {
		// Email from name
		add_settings_field(
			'email_from_name',
			__( 'From Name', 'pressprimer-quiz' ),
			[ $this, 'render_email_from_name_field' ],
			'pressprimer-quiz-settings',
			'ppq_email_section'
		);

		// Email from address
		add_settings_field(
			'email_from_email',
			__( 'From Email Address', 'pressprimer-quiz' ),
			[ $this, 'render_email_from_address_field' ],
			'pressprimer-quiz-settings',
			'ppq_email_section'
		);

		// Auto-send results on completion
		add_settings_field(
			'email_results_auto_send',
			__( 'Auto-send Results', 'pressprimer-quiz' ),
			[ $this, 'render_email_auto_send_field' ],
			'pressprimer-quiz-settings',
			'ppq_email_section'
		);

		// Email results subject
		add_settings_field(
			'email_results_subject',
			__( 'Results Email Subject', 'pressprimer-quiz' ),
			[ $this, 'render_email_subject_field' ],
			'pressprimer-quiz-settings',
			'ppq_email_section'
		);

		// Email results body
		add_settings_field(
			'email_results_body',
			__( 'Results Email Body', 'pressprimer-quiz' ),
			[ $this, 'render_email_body_field' ],
			'pressprimer-quiz-settings',
			'ppq_email_section'
		);
	}

	/**
	 * Register API key fields
	 *
	 * @since 1.0.0
	 */
	private function register_api_key_fields() {
		// Site-wide OpenAI API key (for all users)
		add_settings_field(
			'site_openai_api_key',
			__( 'OpenAI API Key', 'pressprimer-quiz' ),
			[ $this, 'render_site_openai_api_key_field' ],
			'pressprimer-quiz-settings',
			'ppq_api_keys_section'
		);
	}

	/**
	 * Register social sharing fields
	 *
	 * @since 1.0.0
	 */
	private function register_social_sharing_fields() {
		// Enable Twitter sharing
		add_settings_field(
			'social_sharing_twitter',
			__( 'Enable Twitter', 'pressprimer-quiz' ),
			[ $this, 'render_social_twitter_field' ],
			'pressprimer-quiz-settings',
			'ppq_social_sharing_section'
		);

		// Enable Facebook sharing
		add_settings_field(
			'social_sharing_facebook',
			__( 'Enable Facebook', 'pressprimer-quiz' ),
			[ $this, 'render_social_facebook_field' ],
			'pressprimer-quiz-settings',
			'ppq_social_sharing_section'
		);

		// Enable LinkedIn sharing
		add_settings_field(
			'social_sharing_linkedin',
			__( 'Enable LinkedIn', 'pressprimer-quiz' ),
			[ $this, 'render_social_linkedin_field' ],
			'pressprimer-quiz-settings',
			'ppq_social_sharing_section'
		);

		// Include score in share
		add_settings_field(
			'social_sharing_include_score',
			__( 'Include Score in Share', 'pressprimer-quiz' ),
			[ $this, 'render_social_include_score_field' ],
			'pressprimer-quiz-settings',
			'ppq_social_sharing_section'
		);

		// Share message template
		add_settings_field(
			'social_sharing_message',
			__( 'Share Message Template', 'pressprimer-quiz' ),
			[ $this, 'render_social_message_field' ],
			'pressprimer-quiz-settings',
			'ppq_social_sharing_section'
		);
	}

	/**
	 * Register advanced fields
	 *
	 * @since 1.0.0
	 */
	private function register_advanced_fields() {
		// Remove data on uninstall
		add_settings_field(
			'remove_data_on_uninstall',
			__( 'Remove Data on Uninstall', 'pressprimer-quiz' ),
			[ $this, 'render_remove_data_field' ],
			'pressprimer-quiz-settings',
			'ppq_advanced_section'
		);
	}

	/**
	 * Render general section description
	 *
	 * @since 1.0.0
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'General plugin settings.', 'pressprimer-quiz' ) . '</p>';
	}

	/**
	 * Render quiz defaults section description
	 *
	 * @since 1.0.0
	 */
	public function render_quiz_defaults_section() {
		echo '<p>' . esc_html__( 'Default values used when creating new quizzes.', 'pressprimer-quiz' ) . '</p>';
	}

	/**
	 * Render email section description
	 *
	 * @since 1.0.0
	 */
	public function render_email_section() {
		echo '<p>' . esc_html__( 'Configure email notifications sent by the plugin.', 'pressprimer-quiz' ) . '</p>';
	}

	/**
	 * Render API keys section description
	 *
	 * @since 1.0.0
	 */
	public function render_api_keys_section() {
		echo '<p>' . esc_html__( 'Configure your personal OpenAI API key for AI-powered question generation. Your key is stored securely and encrypted.', 'pressprimer-quiz' ) . '</p>';
	}

	/**
	 * Render social sharing section description
	 *
	 * @since 1.0.0
	 */
	public function render_social_sharing_section() {
		echo '<p>' . esc_html__( 'Control which social networks students can share their quiz results to. All options are disabled by default.', 'pressprimer-quiz' ) . '</p>';
	}

	/**
	 * Render advanced section description
	 *
	 * @since 1.0.0
	 */
	public function render_advanced_section() {
		echo '<p>' . esc_html__( 'Advanced settings. Use caution when modifying these options.', 'pressprimer-quiz' ) . '</p>';
	}

	/**
	 * Render passing score field
	 *
	 * @since 1.0.0
	 */
	public function render_passing_score_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		$value    = isset( $settings['default_passing_score'] ) ? $settings['default_passing_score'] : 70;
		?>
		<input
			type="number"
			name="<?php echo esc_attr( self::OPTION_NAME . '[default_passing_score]' ); ?>"
			id="default_passing_score"
			value="<?php echo esc_attr( $value ); ?>"
			min="0"
			max="100"
			step="1"
			class="small-text"
		/>
		<span class="description">%</span>
		<p class="description">
			<?php esc_html_e( 'Percentage score required to pass quizzes (0-100).', 'pressprimer-quiz' ); ?>
		</p>
		<?php
	}

	/**
	 * Render quiz mode field
	 *
	 * @since 1.0.0
	 */
	public function render_quiz_mode_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		$value    = isset( $settings['default_quiz_mode'] ) ? $settings['default_quiz_mode'] : 'tutorial';
		?>
		<select
			name="<?php echo esc_attr( self::OPTION_NAME . '[default_quiz_mode]' ); ?>"
			id="default_quiz_mode"
		>
			<option value="tutorial" <?php selected( $value, 'tutorial' ); ?>>
				<?php esc_html_e( 'Tutorial Mode (immediate feedback)', 'pressprimer-quiz' ); ?>
			</option>
			<option value="timed" <?php selected( $value, 'timed' ); ?>>
				<?php esc_html_e( 'Test Mode (feedback at end)', 'pressprimer-quiz' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Default mode for new quizzes.', 'pressprimer-quiz' ); ?>
		</p>
		<?php
	}

	/**
	 * Render email from name field
	 *
	 * @since 1.0.0
	 */
	public function render_email_from_name_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		$value    = isset( $settings['email_from_name'] ) ? $settings['email_from_name'] : get_bloginfo( 'name' );
		?>
		<input
			type="text"
			name="<?php echo esc_attr( self::OPTION_NAME . '[email_from_name]' ); ?>"
			id="email_from_name"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Name shown in the "From" field of emails sent by the plugin.', 'pressprimer-quiz' ); ?>
		</p>
		<?php
	}

	/**
	 * Render email from address field
	 *
	 * @since 1.0.0
	 */
	public function render_email_from_address_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		$value    = isset( $settings['email_from_email'] ) ? $settings['email_from_email'] : get_bloginfo( 'admin_email' );
		?>
		<input
			type="email"
			name="<?php echo esc_attr( self::OPTION_NAME . '[email_from_email]' ); ?>"
			id="email_from_email"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Email address shown in the "From" field of emails sent by the plugin.', 'pressprimer-quiz' ); ?>
		</p>
		<?php
	}

	/**
	 * Render email auto-send field
	 *
	 * @since 1.0.0
	 */
	public function render_email_auto_send_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		$value    = isset( $settings['email_results_auto_send'] ) ? $settings['email_results_auto_send'] : false;
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME . '[email_results_auto_send]' ); ?>"
				id="email_results_auto_send"
				value="1"
				<?php checked( $value, true ); ?>
			/>
			<?php esc_html_e( 'Automatically email results to students when they complete a quiz', 'pressprimer-quiz' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'If enabled, students will receive an email with their results immediately after completing a quiz.', 'pressprimer-quiz' ); ?>
		</p>
		<?php
	}

	/**
	 * Render email subject field
	 *
	 * @since 1.0.0
	 */
	public function render_email_subject_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		$value    = isset( $settings['email_results_subject'] ) ? $settings['email_results_subject'] : __( 'Your results for {quiz_title}', 'pressprimer-quiz' );
		?>
		<input
			type="text"
			name="<?php echo esc_attr( self::OPTION_NAME . '[email_results_subject]' ); ?>"
			id="email_results_subject"
			value="<?php echo esc_attr( $value ); ?>"
			class="large-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Subject line for results emails. Available tokens:', 'pressprimer-quiz' ); ?>
			<br>
			<code>{student_name}</code> - <?php esc_html_e( 'Student name', 'pressprimer-quiz' ); ?><br>
			<code>{quiz_title}</code> - <?php esc_html_e( 'Quiz name', 'pressprimer-quiz' ); ?><br>
			<code>{score}</code> - <?php esc_html_e( 'Score percentage', 'pressprimer-quiz' ); ?><br>
			<code>{passed}</code> - <?php esc_html_e( '"Passed" or "Failed"', 'pressprimer-quiz' ); ?>
		</p>
		<?php
	}

	/**
	 * Render email body field
	 *
	 * @since 1.0.0
	 */
	public function render_email_body_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		$default  = __(
			'Hi {student_name},

You recently completed the quiz "{quiz_title}".

Here are your results:
- Score: {score}
- Status: {passed}
- Date: {date}

Click the button below to view your full results and review your answers.

Good luck with your studies!',
			'pressprimer-quiz'
		);
		$value    = isset( $settings['email_results_body'] ) ? $settings['email_results_body'] : $default;
		?>
		<textarea
			name="<?php echo esc_attr( self::OPTION_NAME . '[email_results_body]' ); ?>"
			id="email_results_body"
			rows="10"
			class="large-text code"
		><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Email body for results emails. Available tokens:', 'pressprimer-quiz' ); ?>
			<br>
			<code>{student_name}</code> - <?php esc_html_e( 'Student name', 'pressprimer-quiz' ); ?><br>
			<code>{quiz_title}</code> - <?php esc_html_e( 'Quiz name', 'pressprimer-quiz' ); ?><br>
			<code>{score}</code> - <?php esc_html_e( 'Score percentage', 'pressprimer-quiz' ); ?><br>
			<code>{passed}</code> - <?php esc_html_e( '"Passed" or "Failed"', 'pressprimer-quiz' ); ?><br>
			<code>{date}</code> - <?php esc_html_e( 'Completion date', 'pressprimer-quiz' ); ?><br>
			<code>{points}</code> - <?php esc_html_e( 'Points earned', 'pressprimer-quiz' ); ?><br>
			<code>{max_points}</code> - <?php esc_html_e( 'Maximum points', 'pressprimer-quiz' ); ?><br>
			<code>{results_url}</code> - <?php esc_html_e( 'Link to view full results (optional)', 'pressprimer-quiz' ); ?>
		</p>
		<?php
	}

	/**
	 * Render Site-Wide OpenAI API key field
	 *
	 * Displays the site-wide API key configuration for administrators.
	 * This key is used as a fallback when users don't have their own key.
	 *
	 * @since 1.0.0
	 */
	public function render_site_openai_api_key_field() {
		$site_key      = get_option( 'pressprimer_quiz_site_openai_api_key', '' );
		$is_configured = ! empty( $site_key );
		$masked_key    = '';

		if ( $is_configured ) {
			$decrypted = PressPrimer_Quiz_Helpers::decrypt( $site_key );
			if ( ! is_wp_error( $decrypted ) && ! empty( $decrypted ) ) {
				$masked_key = substr( $decrypted, 0, 7 ) . '...' . substr( $decrypted, -4 );
			}
		}
		?>
		<div class="ppq-site-api-key-manager" id="ppq-site-api-key-manager">
			<!-- Key Status Indicator -->
			<div class="ppq-api-key-status <?php echo esc_attr( $is_configured ? 'ppq-api-key-status--configured' : 'ppq-api-key-status--not-set' ); ?>">
				<?php if ( $is_configured && $masked_key ) : ?>
					<span class="dashicons dashicons-yes-alt ppq-api-key-status-icon"></span>
					<span class="ppq-api-key-status-text">
						<?php
						printf(
							/* translators: %s: masked API key */
							esc_html__( 'API Key Configured: %s', 'pressprimer-quiz' ),
							'<code>' . esc_html( $masked_key ) . '</code>'
						);
						?>
					</span>
					<button type="button" class="button button-small button-link-delete" id="ppq-clear-site-key">
						<?php esc_html_e( 'Clear Key', 'pressprimer-quiz' ); ?>
					</button>
				<?php else : ?>
					<span class="dashicons dashicons-warning ppq-api-key-status-icon"></span>
					<span class="ppq-api-key-status-text">
						<?php esc_html_e( 'No API Key Configured', 'pressprimer-quiz' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<!-- Key Input Section -->
			<div class="ppq-api-key-input-section" style="margin-top: 10px;">
				<label for="ppq-site-api-key-input">
					<?php echo $is_configured ? esc_html__( 'Enter New API Key:', 'pressprimer-quiz' ) : esc_html__( 'Enter Your OpenAI API Key:', 'pressprimer-quiz' ); ?>
				</label>
				<div class="ppq-api-key-input-wrapper" style="margin-top: 5px;">
					<input
						type="password"
						id="ppq-site-api-key-input"
						class="regular-text"
						placeholder="sk-..."
						autocomplete="off"
					/>
					<button type="button" class="button button-primary" id="ppq-save-site-key">
						<?php esc_html_e( 'Save Key', 'pressprimer-quiz' ); ?>
					</button>
				</div>
				<p class="description">
					<?php esc_html_e( 'This API key is used for AI question generation and is shared by all users on this site.', 'pressprimer-quiz' ); ?>
				</p>
			</div>
		</div>

		<?php
		$this->enqueue_site_api_key_script();
	}

	/**
	 * Enqueue site API key management script
	 *
	 * Uses wp_add_inline_script and wp_localize_script per WordPress.org guidelines.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_site_api_key_script() {
		// Localize dynamic data for the inline script.
		wp_localize_script(
			'ppq-admin',
			'ppqSiteApiKey',
			[
				'nonce'   => wp_create_nonce( 'pressprimer_quiz_site_api_key' ),
				'strings' => [
					'enterApiKey'   => __( 'Please enter an API key.', 'pressprimer-quiz' ),
					'saving'        => __( 'Saving...', 'pressprimer-quiz' ),
					'saveKey'       => __( 'Save Key', 'pressprimer-quiz' ),
					'failedToSave'  => __( 'Failed to save API key.', 'pressprimer-quiz' ),
					'requestFailed' => __( 'Request failed. Please try again.', 'pressprimer-quiz' ),
					'confirmClear'  => __( 'Are you sure you want to remove the API key?', 'pressprimer-quiz' ),
					'failedToClear' => __( 'Failed to clear API key.', 'pressprimer-quiz' ),
				],
			]
		);

		$inline_script = <<<'JS'
jQuery(document).ready(function($) {
	var config = window.ppqSiteApiKey || {};
	var strings = config.strings || {};
	var nonce = config.nonce || '';

	// Save site-wide API key
	$('#ppq-save-site-key').on('click', function() {
		var key = $('#ppq-site-api-key-input').val().trim();
		if (!key) {
			alert(strings.enterApiKey);
			return;
		}

		var $button = $(this);
		$button.prop('disabled', true).text(strings.saving);

		$.post(ajaxurl, {
			action: 'pressprimer_quiz_save_site_api_key',
			nonce: nonce,
			api_key: key
		}, function(response) {
			if (response.success) {
				location.reload();
			} else {
				alert(response.data.message || strings.failedToSave);
				$button.prop('disabled', false).text(strings.saveKey);
			}
		}).fail(function() {
			alert(strings.requestFailed);
			$button.prop('disabled', false).text(strings.saveKey);
		});
	});

	// Clear API key
	$('#ppq-clear-site-key').on('click', function() {
		if (!confirm(strings.confirmClear)) {
			return;
		}

		var $button = $(this);
		$button.prop('disabled', true);

		$.post(ajaxurl, {
			action: 'pressprimer_quiz_clear_site_api_key',
			nonce: nonce
		}, function(response) {
			if (response.success) {
				location.reload();
			} else {
				alert(response.data.message || strings.failedToClear);
				$button.prop('disabled', false);
			}
		}).fail(function() {
			alert(strings.requestFailed);
			$button.prop('disabled', false);
		});
	});
});
JS;

		wp_add_inline_script( 'ppq-admin', $inline_script );
	}

	/**
	 * Render OpenAI API key field
	 *
	 * Displays comprehensive per-user API key management interface.
	 *
	 * @since 1.0.0
	 */
	public function render_openai_api_key_field() {
		$user_id    = get_current_user_id();
		$key_status = PressPrimer_Quiz_AI_Service::get_api_key_status( $user_id );
		$model_pref = PressPrimer_Quiz_AI_Service::get_model_preference( $user_id );
		$usage_data = $this->get_user_usage_data( $user_id );

		// Enqueue inline styles for the API key section
		$this->enqueue_api_key_styles();
		?>
		<div class="ppq-api-key-manager" id="ppq-api-key-manager">
			<!-- Key Status Indicator -->
			<div class="ppq-api-key-status <?php echo esc_attr( $key_status['configured'] ? 'ppq-api-key-status--configured' : 'ppq-api-key-status--not-set' ); ?>">
				<?php if ( $key_status['configured'] ) : ?>
					<span class="dashicons dashicons-yes-alt ppq-api-key-status-icon"></span>
					<span class="ppq-api-key-status-text">
						<?php
						printf(
							/* translators: %s: masked API key */
							esc_html__( 'API Key Configured: %s', 'pressprimer-quiz' ),
							'<code>' . esc_html( $key_status['masked_key'] ) . '</code>'
						);
						?>
					</span>
					<button type="button" class="button button-small ppq-api-key-validate" id="ppq-validate-key">
						<?php esc_html_e( 'Validate', 'pressprimer-quiz' ); ?>
					</button>
					<button type="button" class="button button-small button-link-delete ppq-api-key-clear" id="ppq-clear-key">
						<?php esc_html_e( 'Clear Key', 'pressprimer-quiz' ); ?>
					</button>
				<?php else : ?>
					<span class="dashicons dashicons-warning ppq-api-key-status-icon"></span>
					<span class="ppq-api-key-status-text">
						<?php esc_html_e( 'No API Key Configured', 'pressprimer-quiz' ); ?>
					</span>
				<?php endif; ?>
			</div>

			<!-- Validation Result Area -->
			<div class="ppq-api-key-validation-result" id="ppq-validation-result" style="display: none;"></div>

			<!-- Key Input Section -->
			<div class="ppq-api-key-input-section">
				<label for="ppq-api-key-input">
					<?php echo $key_status['configured'] ? esc_html__( 'Enter New API Key:', 'pressprimer-quiz' ) : esc_html__( 'Enter Your OpenAI API Key:', 'pressprimer-quiz' ); ?>
				</label>
				<div class="ppq-api-key-input-wrapper">
					<input
						type="password"
						id="ppq-api-key-input"
						class="regular-text"
						placeholder="sk-..."
						autocomplete="off"
					/>
					<button type="button" class="button ppq-api-key-toggle-visibility" id="ppq-toggle-key-visibility" title="<?php esc_attr_e( 'Show/Hide', 'pressprimer-quiz' ); ?>">
						<span class="dashicons dashicons-visibility"></span>
					</button>
					<button type="button" class="button button-primary ppq-api-key-save" id="ppq-save-key">
						<?php esc_html_e( 'Save Key', 'pressprimer-quiz' ); ?>
					</button>
				</div>
				<p class="description">
					<?php
					printf(
						/* translators: %s: OpenAI API keys URL */
						esc_html__( 'Get your API key from %s. Keys start with "sk-".', 'pressprimer-quiz' ),
						'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">OpenAI Platform</a>'
					);
					?>
				</p>
			</div>

			<!-- Model Selection -->
			<?php
			if ( $key_status['configured'] ) :
				// Fetch available models
				$api_key          = PressPrimer_Quiz_AI_Service::get_api_key( $user_id );
				$available_models = [];
				if ( ! is_wp_error( $api_key ) && ! empty( $api_key ) ) {
					$fetched_models = PressPrimer_Quiz_AI_Service::get_available_models( $api_key );
					if ( ! is_wp_error( $fetched_models ) ) {
						$available_models = $fetched_models;
					}
				}
				// If we couldn't fetch models, use the stored preference as fallback
				if ( empty( $available_models ) && ! empty( $model_pref ) ) {
					$available_models = [ $model_pref ];
				}
				?>
			<div class="ppq-api-model-section">
				<label for="ppq-api-model"><?php esc_html_e( 'Preferred Model:', 'pressprimer-quiz' ); ?></label>
				<select id="ppq-api-model" class="ppq-api-model-select">
					<?php if ( empty( $available_models ) ) : ?>
						<option value=""><?php esc_html_e( '-- Click refresh to load models --', 'pressprimer-quiz' ); ?></option>
					<?php else : ?>
						<?php foreach ( $available_models as $model ) : ?>
							<option value="<?php echo esc_attr( $model ); ?>" <?php selected( $model_pref, $model ); ?>>
								<?php echo esc_html( $model ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
				<button type="button" class="button ppq-api-model-refresh" id="ppq-refresh-models" title="<?php esc_attr_e( 'Refresh available models', 'pressprimer-quiz' ); ?>">
					<span class="dashicons dashicons-update"></span>
				</button>
				<p class="description">
					<?php esc_html_e( 'Select the OpenAI model to use for question generation. Models are fetched from your OpenAI account.', 'pressprimer-quiz' ); ?>
				</p>
			</div>
			<?php endif; ?>

			<!-- Usage Statistics -->
			<?php if ( $key_status['configured'] && $usage_data ) : ?>
			<div class="ppq-api-usage-section">
				<h4><?php esc_html_e( 'Usage This Hour', 'pressprimer-quiz' ); ?></h4>
				<div class="ppq-api-usage-stats">
					<div class="ppq-api-usage-stat">
						<span class="ppq-api-usage-value"><?php echo esc_html( $usage_data['requests_this_hour'] ); ?></span>
						<span class="ppq-api-usage-label"><?php esc_html_e( 'Requests', 'pressprimer-quiz' ); ?></span>
					</div>
					<div class="ppq-api-usage-stat">
						<span class="ppq-api-usage-value"><?php echo esc_html( $usage_data['requests_remaining'] ); ?></span>
						<span class="ppq-api-usage-label"><?php esc_html_e( 'Remaining', 'pressprimer-quiz' ); ?></span>
					</div>
					<div class="ppq-api-usage-progress">
						<div class="ppq-api-usage-progress-bar" style="width: <?php echo esc_attr( $usage_data['usage_percent'] ); ?>%;"></div>
					</div>
				</div>
				<p class="description">
					<?php
					printf(
						/* translators: %d: rate limit per hour */
						esc_html__( 'Rate limit: %d requests per hour. Resets automatically.', 'pressprimer-quiz' ),
						(int) PressPrimer_Quiz_AI_Service::RATE_LIMIT_PER_HOUR
					);
					?>
				</p>
			</div>
			<?php endif; ?>

			<!-- Security Notice -->
			<div class="ppq-api-security-notice">
				<span class="dashicons dashicons-lock"></span>
				<span>
					<?php esc_html_e( 'Your API key is encrypted using AES-256-CBC before storage and is only accessible to your account.', 'pressprimer-quiz' ); ?>
				</span>
			</div>
		</div>

		<?php
		// Add inline JavaScript
		$this->enqueue_api_key_script();
	}

	/**
	 * Enqueue API key management styles
	 *
	 * Uses wp_add_inline_style to properly enqueue CSS per WordPress.org guidelines.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_api_key_styles() {
		$inline_css = '
			.ppq-api-key-manager {
				max-width: 700px;
			}
			.ppq-api-key-status {
				display: flex;
				align-items: center;
				gap: 10px;
				padding: 12px 15px;
				border-radius: 4px;
				margin-bottom: 15px;
			}
			.ppq-api-key-status--configured {
				background: #d1e7dd;
				border: 1px solid #0f5132;
			}
			.ppq-api-key-status--not-set {
				background: #fff3cd;
				border: 1px solid #856404;
			}
			.ppq-api-key-status-icon {
				font-size: 20px;
			}
			.ppq-api-key-status--configured .ppq-api-key-status-icon {
				color: #0f5132;
			}
			.ppq-api-key-status--not-set .ppq-api-key-status-icon {
				color: #856404;
			}
			.ppq-api-key-status-text {
				flex: 1;
			}
			.ppq-api-key-status-text code {
				background: rgba(0,0,0,0.1);
				padding: 2px 6px;
				border-radius: 3px;
			}
			.ppq-api-key-validation-result {
				padding: 10px 15px;
				border-radius: 4px;
				margin-bottom: 15px;
			}
			.ppq-api-key-validation-result.success {
				background: #d1e7dd;
				border: 1px solid #0f5132;
				color: #0f5132;
			}
			.ppq-api-key-validation-result.error {
				background: #f8d7da;
				border: 1px solid #842029;
				color: #842029;
			}
			.ppq-api-key-input-section {
				margin-bottom: 20px;
			}
			.ppq-api-key-input-section label {
				display: block;
				font-weight: 600;
				margin-bottom: 5px;
			}
			.ppq-api-key-input-wrapper {
				display: flex;
				gap: 5px;
				align-items: center;
				margin-bottom: 5px;
			}
			.ppq-api-key-input-wrapper input {
				flex: 1;
				max-width: 400px;
			}
			.ppq-api-key-toggle-visibility .dashicons {
				vertical-align: text-bottom;
			}
			.ppq-api-model-section {
				margin-bottom: 20px;
				padding: 15px;
				background: #f6f7f7;
				border-radius: 4px;
			}
			.ppq-api-model-section label {
				font-weight: 600;
				margin-right: 10px;
			}
			.ppq-api-model-select {
				min-width: 300px;
			}
			.ppq-api-model-refresh .dashicons {
				vertical-align: text-bottom;
			}
			.ppq-api-usage-section {
				margin-bottom: 20px;
				padding: 15px;
				background: #f6f7f7;
				border-radius: 4px;
			}
			.ppq-api-usage-section h4 {
				margin: 0 0 10px 0;
			}
			.ppq-api-usage-stats {
				display: flex;
				gap: 30px;
				align-items: center;
				flex-wrap: wrap;
			}
			.ppq-api-usage-stat {
				text-align: center;
			}
			.ppq-api-usage-value {
				display: block;
				font-size: 24px;
				font-weight: 700;
				color: #1d2327;
			}
			.ppq-api-usage-label {
				font-size: 12px;
				color: #646970;
				text-transform: uppercase;
			}
			.ppq-api-usage-progress {
				flex: 1;
				min-width: 200px;
				height: 8px;
				background: #dcdcde;
				border-radius: 4px;
				overflow: hidden;
			}
			.ppq-api-usage-progress-bar {
				height: 100%;
				background: #2271b1;
				transition: width 0.3s ease;
			}
			.ppq-api-security-notice {
				display: flex;
				align-items: center;
				gap: 8px;
				padding: 10px 15px;
				background: #f0f6fc;
				border: 1px solid #c5d9ed;
				border-radius: 4px;
				color: #1d4ed8;
				font-size: 13px;
			}
			.ppq-api-security-notice .dashicons {
				color: #1d4ed8;
			}
			.ppq-api-key-manager .spinner {
				float: none;
				margin: 0;
			}
		';

		wp_add_inline_style( 'ppq-admin', $inline_css );
	}

	/**
	 * Enqueue API key management script
	 *
	 * Uses wp_add_inline_script and wp_localize_script per WordPress.org guidelines.
	 *
	 * @since 1.0.0
	 */
	private function enqueue_api_key_script() {
		// Localize dynamic data for the inline script.
		wp_localize_script(
			'ppq-admin',
			'ppqApiKeySettings',
			[
				'nonce'   => wp_create_nonce( 'pressprimer_quiz_api_key_nonce' ),
				'strings' => [
					'enterApiKey'         => __( 'Please enter an API key.', 'pressprimer-quiz' ),
					'invalidKeyFormat'    => __( 'Invalid API key format. Keys should start with "sk-".', 'pressprimer-quiz' ),
					'failedToSave'        => __( 'Failed to save API key.', 'pressprimer-quiz' ),
					'errorOccurred'       => __( 'An error occurred. Please try again.', 'pressprimer-quiz' ),
					'invalidApiKey'       => __( 'Invalid API key.', 'pressprimer-quiz' ),
					'confirmClearKey'     => __( 'Are you sure you want to remove your API key? You will not be able to use AI generation until you add a new key.', 'pressprimer-quiz' ),
					'failedToClear'       => __( 'Failed to clear API key.', 'pressprimer-quiz' ),
					'modelSaved'          => __( 'Model preference saved.', 'pressprimer-quiz' ),
					'modelsRefreshed'     => __( 'Models refreshed.', 'pressprimer-quiz' ),
					'failedToFetchModels' => __( 'Failed to fetch models.', 'pressprimer-quiz' ),
				],
			]
		);

		$inline_script = <<<'JS'
jQuery(document).ready(function($) {
	var config = window.ppqApiKeySettings || {};
	var strings = config.strings || {};
	var nonce = config.nonce || '';

	var PPQ_APIKey = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			$('#ppq-save-key').on('click', this.saveKey.bind(this));
			$('#ppq-validate-key').on('click', this.validateKey.bind(this));
			$('#ppq-clear-key').on('click', this.clearKey.bind(this));
			$('#ppq-toggle-key-visibility').on('click', this.toggleVisibility.bind(this));
			$('#ppq-api-model').on('change', this.saveModelPreference.bind(this));
			$('#ppq-refresh-models').on('click', this.refreshModels.bind(this));
		},

		saveKey: function() {
			var key = $('#ppq-api-key-input').val().trim();

			if (!key) {
				this.showResult('error', strings.enterApiKey);
				return;
			}

			if (!key.startsWith('sk-')) {
				this.showResult('error', strings.invalidKeyFormat);
				return;
			}

			this.setLoading('#ppq-save-key', true);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'pressprimer_quiz_save_user_api_key',
					nonce: nonce,
					api_key: key
				},
				success: function(response) {
					this.setLoading('#ppq-save-key', false);

					if (response.success) {
						this.showResult('success', response.data.message);
						setTimeout(function() {
							location.reload();
						}, 1500);
					} else {
						this.showResult('error', response.data.message || strings.failedToSave);
					}
				}.bind(this),
				error: function() {
					this.setLoading('#ppq-save-key', false);
					this.showResult('error', strings.errorOccurred);
				}.bind(this)
			});
		},

		validateKey: function() {
			this.setLoading('#ppq-validate-key', true);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'pressprimer_quiz_validate_api_key',
					nonce: nonce
				},
				success: function(response) {
					this.setLoading('#ppq-validate-key', false);

					if (response.success) {
						this.showResult('success', response.data.message);
					} else {
						this.showResult('error', response.data.message || strings.invalidApiKey);
					}
				}.bind(this),
				error: function() {
					this.setLoading('#ppq-validate-key', false);
					this.showResult('error', strings.errorOccurred);
				}.bind(this)
			});
		},

		clearKey: function() {
			if (!confirm(strings.confirmClearKey)) {
				return;
			}

			this.setLoading('#ppq-clear-key', true);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'pressprimer_quiz_clear_user_api_key',
					nonce: nonce
				},
				success: function(response) {
					this.setLoading('#ppq-clear-key', false);

					if (response.success) {
						this.showResult('success', response.data.message);
						setTimeout(function() {
							location.reload();
						}, 1500);
					} else {
						this.showResult('error', response.data.message || strings.failedToClear);
					}
				}.bind(this),
				error: function() {
					this.setLoading('#ppq-clear-key', false);
					this.showResult('error', strings.errorOccurred);
				}.bind(this)
			});
		},

		toggleVisibility: function() {
			var input = $('#ppq-api-key-input');
			var icon = $('#ppq-toggle-key-visibility .dashicons');

			if (input.attr('type') === 'password') {
				input.attr('type', 'text');
				icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
			} else {
				input.attr('type', 'password');
				icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
			}
		},

		saveModelPreference: function() {
			var model = $('#ppq-api-model').val();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'pressprimer_quiz_save_user_api_key',
					nonce: nonce,
					model: model
				},
				success: function(response) {
					if (response.success) {
						this.showResult('success', strings.modelSaved);
					}
				}.bind(this)
			});
		},

		refreshModels: function() {
			this.setLoading('#ppq-refresh-models', true);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'pressprimer_quiz_get_api_models',
					nonce: nonce
				},
				success: function(response) {
					this.setLoading('#ppq-refresh-models', false);

					if (response.success && response.data.models) {
						var select = $('#ppq-api-model');
						var currentValue = select.val();
						select.empty();

						response.data.models.forEach(function(model) {
							select.append($('<option>', {
								value: model,
								text: model,
								selected: model === currentValue
							}));
						});

						this.showResult('success', strings.modelsRefreshed);
					} else {
						this.showResult('error', response.data.message || strings.failedToFetchModels);
					}
				}.bind(this),
				error: function() {
					this.setLoading('#ppq-refresh-models', false);
					this.showResult('error', strings.errorOccurred);
				}.bind(this)
			});
		},

		showResult: function(type, message) {
			var $result = $('#ppq-validation-result');
			$result.removeClass('success error').addClass(type).text(message).show();

			setTimeout(function() {
				$result.fadeOut();
			}, 5000);
		},

		setLoading: function(selector, loading) {
			var $btn = $(selector);
			if (loading) {
				$btn.prop('disabled', true);
				if (!$btn.find('.spinner').length) {
					$btn.append(' <span class="spinner is-active"></span>');
				}
			} else {
				$btn.prop('disabled', false);
				$btn.find('.spinner').remove();
			}
		}
	};

	PPQ_APIKey.init();
});
JS;

		wp_add_inline_script( 'ppq-admin', $inline_script );
	}

	/**
	 * Get user usage data
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return array|false Usage data or false if no data.
	 */
	private function get_user_usage_data( $user_id ) {
		$key           = 'ppq_ai_requests_' . $user_id;
		$requests      = (int) get_transient( $key );
		$rate_limit    = PressPrimer_Quiz_AI_Service::RATE_LIMIT_PER_HOUR;
		$remaining     = max( 0, $rate_limit - $requests );
		$usage_percent = ( $requests / $rate_limit ) * 100;

		return [
			'requests_this_hour' => $requests,
			'requests_remaining' => $remaining,
			'rate_limit'         => $rate_limit,
			'usage_percent'      => min( 100, $usage_percent ),
		];
	}

	/**
	 * Render social Twitter field
	 *
	 * @since 1.0.0
	 */
	public function render_social_twitter_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		$value    = isset( $settings['social_sharing_twitter'] ) ? $settings['social_sharing_twitter'] : false;
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME . '[social_sharing_twitter]' ); ?>"
				id="social_sharing_twitter"
				value="1"
				<?php checked( $value, true ); ?>
			/>
			<?php esc_html_e( 'Allow students to share results on Twitter', 'pressprimer-quiz' ); ?>
		</label>
		<?php
	}

	/**
	 * Render social Facebook field
	 *
	 * @since 1.0.0
	 */
	public function render_social_facebook_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		$value    = isset( $settings['social_sharing_facebook'] ) ? $settings['social_sharing_facebook'] : false;
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME . '[social_sharing_facebook]' ); ?>"
				id="social_sharing_facebook"
				value="1"
				<?php checked( $value, true ); ?>
			/>
			<?php esc_html_e( 'Allow students to share results on Facebook', 'pressprimer-quiz' ); ?>
		</label>
		<?php
	}

	/**
	 * Render social LinkedIn field
	 *
	 * @since 1.0.0
	 */
	public function render_social_linkedin_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		$value    = isset( $settings['social_sharing_linkedin'] ) ? $settings['social_sharing_linkedin'] : false;
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME . '[social_sharing_linkedin]' ); ?>"
				id="social_sharing_linkedin"
				value="1"
				<?php checked( $value, true ); ?>
			/>
			<?php esc_html_e( 'Allow students to share results on LinkedIn', 'pressprimer-quiz' ); ?>
		</label>
		<?php
	}

	/**
	 * Render social include score field
	 *
	 * @since 1.0.0
	 */
	public function render_social_include_score_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		$value    = isset( $settings['social_sharing_include_score'] ) ? $settings['social_sharing_include_score'] : true;
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME . '[social_sharing_include_score]' ); ?>"
				id="social_sharing_include_score"
				value="1"
				<?php checked( $value, true ); ?>
			/>
			<?php esc_html_e( 'Include score percentage in shared message', 'pressprimer-quiz' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'If enabled, the share message will include the student\'s score.', 'pressprimer-quiz' ); ?>
		</p>
		<?php
	}

	/**
	 * Render social message field
	 *
	 * @since 1.0.0
	 */
	public function render_social_message_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		$value    = isset( $settings['social_sharing_message'] ) ? $settings['social_sharing_message'] : 'I just completed {quiz_title}!';
		?>
		<textarea
			name="<?php echo esc_attr( self::OPTION_NAME . '[social_sharing_message]' ); ?>"
			id="social_sharing_message"
			rows="3"
			class="large-text"
		><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Customize the message that appears when students share their results. Available tokens:', 'pressprimer-quiz' ); ?>
			<br>
			<code>{quiz_title}</code> - <?php esc_html_e( 'Quiz name', 'pressprimer-quiz' ); ?><br>
			<code>{score}</code> - <?php esc_html_e( 'Score percentage (only if "Include Score" is enabled)', 'pressprimer-quiz' ); ?><br>
			<code>{pass_status}</code> - <?php esc_html_e( '"Passed" or "Failed"', 'pressprimer-quiz' ); ?>
		</p>
		<?php
	}

	/**
	 * Render remove data field
	 *
	 * @since 1.0.0
	 */
	public function render_remove_data_field() {
		$settings = get_option( self::OPTION_NAME, [] );
		$value    = isset( $settings['remove_data_on_uninstall'] ) ? $settings['remove_data_on_uninstall'] : false;
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_NAME . '[remove_data_on_uninstall]' ); ?>"
				id="remove_data_on_uninstall"
				value="1"
				<?php checked( $value, true ); ?>
			/>
			<?php esc_html_e( 'Remove all plugin data when uninstalling', 'pressprimer-quiz' ); ?>
		</label>
		<p class="description">
			<strong style="color: #d63638;">⚠ <?php esc_html_e( 'Warning:', 'pressprimer-quiz' ); ?></strong>
			<?php esc_html_e( 'If enabled, uninstalling this plugin will permanently delete all quizzes, questions, attempts, and settings. This action cannot be undone!', 'pressprimer-quiz' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'By default, data is preserved when you uninstall the plugin to prevent accidental data loss. Only enable this if you are certain you want to completely remove all data.', 'pressprimer-quiz' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize settings
	 *
	 * Validates and sanitizes all settings before saving.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = [];

		// Get existing settings
		$existing = get_option( self::OPTION_NAME, [] );

		// Sanitize default passing score
		if ( isset( $input['default_passing_score'] ) ) {
			$score = absint( $input['default_passing_score'] );
			if ( $score < 0 || $score > 100 ) {
				add_settings_error(
					self::OPTION_NAME,
					'invalid_passing_score',
					__( 'Passing score must be between 0 and 100.', 'pressprimer-quiz' ),
					'error'
				);
				$score = 70; // Default
			}
			$sanitized['default_passing_score'] = $score;
		}

		// Sanitize default quiz mode
		if ( isset( $input['default_quiz_mode'] ) ) {
			$mode = sanitize_key( $input['default_quiz_mode'] );
			if ( ! in_array( $mode, [ 'tutorial', 'timed' ], true ) ) {
				$mode = 'tutorial';
			}
			$sanitized['default_quiz_mode'] = $mode;
		}

		// Sanitize email from name
		if ( isset( $input['email_from_name'] ) ) {
			$sanitized['email_from_name'] = sanitize_text_field( $input['email_from_name'] );
		}

		// Sanitize email from address
		if ( isset( $input['email_from_email'] ) ) {
			$email = sanitize_email( $input['email_from_email'] );
			if ( ! is_email( $email ) ) {
				add_settings_error(
					self::OPTION_NAME,
					'invalid_email',
					__( 'Please enter a valid email address.', 'pressprimer-quiz' ),
					'error'
				);
				$email = get_bloginfo( 'admin_email' );
			}
			$sanitized['email_from_email'] = $email;
		}

		// Sanitize email auto-send
		$sanitized['email_results_auto_send'] = isset( $input['email_results_auto_send'] ) && '1' === $input['email_results_auto_send'];

		// Sanitize email subject
		if ( isset( $input['email_results_subject'] ) ) {
			$sanitized['email_results_subject'] = sanitize_text_field( $input['email_results_subject'] );
		}

		// Sanitize email body
		if ( isset( $input['email_results_body'] ) ) {
			$sanitized['email_results_body'] = wp_kses_post( $input['email_results_body'] );
		}

		// Sanitize social sharing fields
		$sanitized['social_sharing_twitter']       = isset( $input['social_sharing_twitter'] ) && '1' === $input['social_sharing_twitter'];
		$sanitized['social_sharing_facebook']      = isset( $input['social_sharing_facebook'] ) && '1' === $input['social_sharing_facebook'];
		$sanitized['social_sharing_linkedin']      = isset( $input['social_sharing_linkedin'] ) && '1' === $input['social_sharing_linkedin'];
		$sanitized['social_sharing_include_score'] = isset( $input['social_sharing_include_score'] ) && '1' === $input['social_sharing_include_score'];

		// Sanitize social sharing message
		if ( isset( $input['social_sharing_message'] ) ) {
			$sanitized['social_sharing_message'] = sanitize_textarea_field( $input['social_sharing_message'] );
		}

		// Handle OpenAI API key (stored globally, encrypted)
		if ( isset( $input['openai_api_key'] ) && ! empty( $input['openai_api_key'] ) ) {
			$api_key = trim( $input['openai_api_key'] );

			// Basic validation
			if ( strlen( $api_key ) < 20 ) {
				add_settings_error(
					self::OPTION_NAME,
					'invalid_api_key',
					__( 'Invalid API key format.', 'pressprimer-quiz' ),
					'error'
				);
			} else {
				// Encrypt and store globally
				$encrypted = PressPrimer_Quiz_Helpers::encrypt( $api_key );

				if ( is_wp_error( $encrypted ) ) {
					add_settings_error(
						self::OPTION_NAME,
						'encryption_failed',
						$encrypted->get_error_message(),
						'error'
					);
				} else {
					$sanitized['openai_api_key'] = $encrypted;
					add_settings_error(
						self::OPTION_NAME,
						'api_key_saved',
						__( 'OpenAI API key saved successfully.', 'pressprimer-quiz' ),
						'success'
					);
				}
			}
		} elseif ( isset( $existing['openai_api_key'] ) ) {
			// Preserve existing key if not updating
			$sanitized['openai_api_key'] = $existing['openai_api_key'];
		}

		// Sanitize remove data on uninstall
		// Handle both form input ('1' string from checkbox) and programmatic updates (boolean)
		if ( isset( $input['remove_data_on_uninstall'] ) ) {
			$value                                 = $input['remove_data_on_uninstall'];
			$sanitized['remove_data_on_uninstall'] = ( true === $value || '1' === $value || 1 === $value );
		} else {
			$sanitized['remove_data_on_uninstall'] = false;
		}

		// Sanitize appearance settings
		if ( isset( $input['appearance_font_family'] ) ) {
			$sanitized['appearance_font_family'] = sanitize_text_field( $input['appearance_font_family'] );
		}

		if ( isset( $input['appearance_font_size'] ) ) {
			$sanitized['appearance_font_size'] = sanitize_text_field( $input['appearance_font_size'] );
		}

		if ( isset( $input['appearance_primary_color'] ) ) {
			$color                                 = $input['appearance_primary_color'];
			$sanitized['appearance_primary_color'] = ! empty( $color ) ? sanitize_hex_color( $color ) : '';
		}

		if ( isset( $input['appearance_text_color'] ) ) {
			$color                              = $input['appearance_text_color'];
			$sanitized['appearance_text_color'] = ! empty( $color ) ? sanitize_hex_color( $color ) : '';
		}

		if ( isset( $input['appearance_background_color'] ) ) {
			$color                                    = $input['appearance_background_color'];
			$sanitized['appearance_background_color'] = ! empty( $color ) ? sanitize_hex_color( $color ) : '';
		}

		if ( isset( $input['appearance_success_color'] ) ) {
			$color                                 = $input['appearance_success_color'];
			$sanitized['appearance_success_color'] = ! empty( $color ) ? sanitize_hex_color( $color ) : '';
		}

		if ( isset( $input['appearance_error_color'] ) ) {
			$color                               = $input['appearance_error_color'];
			$sanitized['appearance_error_color'] = ! empty( $color ) ? sanitize_hex_color( $color ) : '';
		}

		if ( isset( $input['appearance_border_radius'] ) ) {
			$radius                                = $input['appearance_border_radius'];
			$sanitized['appearance_border_radius'] = ( '' !== $radius && null !== $radius ) ? absint( $radius ) : '';
		}

		// Merge with existing settings to preserve any not in the form
		return array_merge( $existing, $sanitized );
	}

	/**
	 * Render settings page
	 *
	 * Displays the React settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_page() {
		// Check capability
		if ( ! current_user_can( 'pressprimer_quiz_manage_settings' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-quiz' ),
				esc_html__( 'Permission Denied', 'pressprimer-quiz' ),
				[ 'response' => 403 ]
			);
		}

		// Enqueue React settings panel
		$this->enqueue_react_settings();

		?>
		<!-- React Settings Root -->
		<div id="ppq-settings-root"></div>
		<?php
	}

	/**
	 * Enqueue React settings panel
	 *
	 * @since 1.0.0
	 */
	private function enqueue_react_settings() {
		// Enqueue WordPress media library for logo selection
		wp_enqueue_media();

		// Enqueue Ant Design CSS
		wp_enqueue_style(
			'antd',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/css/vendor/antd-reset.css',
			[],
			'5.12.0'
		);

		// Enqueue built React app
		$asset_file = PRESSPRIMER_QUIZ_PLUGIN_PATH . 'build/settings-panel.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : [
			'dependencies' => [ 'wp-element', 'wp-i18n', 'wp-api-fetch' ],
			'version'      => PRESSPRIMER_QUIZ_VERSION,
		];

		wp_enqueue_script(
			'ppq-settings-panel',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/settings-panel.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'ppq-settings-panel',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/style-settings-panel.css',
			[],
			$asset['version']
		);

		// Prepare settings data for React
		$settings_data = $this->get_settings_data_for_react();

		// Localize script with data
		wp_localize_script(
			'ppq-settings-panel',
			'pressprimerQuizSettingsData',
			$settings_data
		);

		// Also pass admin URL
		wp_localize_script(
			'ppq-settings-panel',
			'pressprimerQuizAdmin',
			[
				'adminUrl' => admin_url(),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			]
		);
	}

	/**
	 * Get settings data for React
	 *
	 * @since 1.0.0
	 *
	 * @return array Settings data.
	 */
	private function get_settings_data_for_react() {
		global $wpdb;

		$user_id    = get_current_user_id();
		$settings   = get_option( self::OPTION_NAME, [] );
		$key_status = PressPrimer_Quiz_AI_Service::get_api_key_status( $user_id );
		$model_pref = PressPrimer_Quiz_AI_Service::get_model_preference( $user_id );
		$usage_data = $this->get_user_usage_data( $user_id );

		// Fetch available models if key is configured
		$available_models = [];
		if ( $key_status['configured'] ) {
			$api_key = PressPrimer_Quiz_AI_Service::get_api_key( $user_id );
			if ( ! is_wp_error( $api_key ) && ! empty( $api_key ) ) {
				$fetched_models = PressPrimer_Quiz_AI_Service::get_available_models( $api_key );
				if ( ! is_wp_error( $fetched_models ) ) {
					$available_models = $fetched_models;
				}
			}
		}

		// Get statistics (only questions table has deleted_at column)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple count queries for admin settings display
		$total_quizzes = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ppq_quizzes" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_questions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ppq_questions WHERE deleted_at IS NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_banks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ppq_banks" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_attempts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ppq_attempts" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Get theme font family from WordPress theme
		$theme_font = $this->get_theme_font_family();

		// Get database table status
		$table_status = [];
		if ( class_exists( 'PressPrimer_Quiz_Migrator' ) ) {
			$table_status = PressPrimer_Quiz_Migrator::get_table_status();
		}

		// Ensure remove_data_on_uninstall is ALWAYS explicitly set and defaults to false
		// This is a critical safety setting - it should NEVER default to true
		// wp_localize_script converts booleans, so we use integers: 0 = false, 1 = true
		$remove_data_value = false;
		if ( isset( $settings['remove_data_on_uninstall'] ) ) {
			$remove_data_value = ( true === $settings['remove_data_on_uninstall'] || '1' === $settings['remove_data_on_uninstall'] || 1 === $settings['remove_data_on_uninstall'] );
		}
		// Use integer for wp_localize_script compatibility (0 or 1)
		$settings['remove_data_on_uninstall'] = $remove_data_value ? 1 : 0;

		return [
			'pluginUrl'      => PRESSPRIMER_QUIZ_PLUGIN_URL,
			'settings'       => $settings,
			'apiKeyStatus'   => $key_status,
			'apiModels'      => $available_models,
			'modelPref'      => $model_pref,
			'usageData'      => $usage_data,
			'defaults'       => [
				'siteName'   => get_bloginfo( 'name' ),
				'adminEmail' => get_bloginfo( 'admin_email' ),
			],
			'appearance'     => [
				'themeFont'     => $theme_font,
				'defaultColors' => [
					'primary'    => '#0073aa',
					'text'       => '#1d2327',
					'background' => '#ffffff',
					'success'    => '#00a32a',
					'error'      => '#d63638',
				],
			],
			'systemInfo'     => [
				'pluginVersion'          => PRESSPRIMER_QUIZ_VERSION,
				'dbVersion'              => get_option( 'pressprimer_quiz_db_version', 'Not set' ),
				'wpVersion'              => get_bloginfo( 'version' ),
				'memoryLimit'            => WP_MEMORY_LIMIT,
				'phpVersion'             => PHP_VERSION,
				'postMaxSize'            => ini_get( 'post_max_size' ),
				'maxExecutionTime'       => ini_get( 'max_execution_time' ),
				'mysqlVersion'           => $wpdb->db_version(),
				'isMultisite'            => is_multisite(),
				'totalQuizzes'           => $total_quizzes,
				'totalQuestions'         => $total_questions,
				'totalBanks'             => $total_banks,
				'totalAttempts'          => $total_attempts,
				'extractionCapabilities' => PressPrimer_Quiz_File_Processor::get_extraction_capabilities(),
			],
			'databaseTables' => $table_status,
			'nonces'         => [
				'repairTables' => wp_create_nonce( 'pressprimer_quiz_repair_tables_nonce' ),
			],
			'lmsStatus'      => [
				'learndash' => [
					'installed' => defined( 'LEARNDASH_VERSION' ),
					'active'    => defined( 'LEARNDASH_VERSION' ),
					'version'   => defined( 'LEARNDASH_VERSION' ) ? LEARNDASH_VERSION : null,
				],
				'tutorlms'  => [
					'installed' => defined( 'TUTOR_VERSION' ),
					'active'    => defined( 'TUTOR_VERSION' ),
					'version'   => defined( 'TUTOR_VERSION' ) ? TUTOR_VERSION : null,
				],
				'lifterlms' => [
					'installed' => defined( 'LLMS_VERSION' ),
					'active'    => defined( 'LLMS_VERSION' ),
					'version'   => defined( 'LLMS_VERSION' ) ? LLMS_VERSION : null,
				],
			],
		];

		/**
		 * Filter the settings data passed to the React settings panel.
		 *
		 * Allows adding custom tabs, sections, or data to the settings page.
		 * Note: You'll also need to add corresponding React components to display
		 * custom settings data.
		 *
		 * @since 1.0.0
		 *
		 * @param array $data Settings data including settings, apiKeyStatus, systemInfo, etc.
		 */
		return apply_filters( 'pressprimer_quiz_settings_data', $data );
	}

	/**
	 * Get setting value
	 *
	 * Helper method to retrieve a setting value with a default.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value if not set.
	 * @return mixed Setting value.
	 */
	public static function get( $key, $default = null ) {
		$settings = get_option( self::OPTION_NAME, [] );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Get the font family from the active WordPress theme
	 *
	 * Attempts to detect the primary body font from theme.json or
	 * common theme customizer settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array|null Font info array with 'name' and 'value' keys, or null.
	 */
	private function get_theme_font_family() {
		// Try to get font from theme.json (block themes)
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$global_settings = wp_get_global_settings();

			// Check for typography font family
			if ( ! empty( $global_settings['typography']['fontFamily'] ) ) {
				$font_family = $global_settings['typography']['fontFamily'];
				// Clean up CSS var references like var(--wp--preset--font-family--system-font)
				if ( strpos( $font_family, 'var(' ) === 0 ) {
					// Try to resolve the variable
					$font_families = $global_settings['typography']['fontFamilies']['theme'] ?? [];
					foreach ( $font_families as $font ) {
						if ( ! empty( $font['fontFamily'] ) && ! empty( $font['name'] ) ) {
							// Use the first theme font as primary
							return [
								'name'  => $font['name'],
								'value' => $font['fontFamily'],
							];
						}
					}
				} else {
					return [
						'name'  => __( 'Theme Font', 'pressprimer-quiz' ),
						'value' => $font_family,
					];
				}
			}

			// Check font families defined in theme
			$font_families = $global_settings['typography']['fontFamilies']['theme'] ?? [];
			if ( ! empty( $font_families ) ) {
				// Find the body or primary font
				foreach ( $font_families as $font ) {
					$slug = $font['slug'] ?? '';
					// Look for common body font slugs
					if ( in_array( $slug, [ 'body', 'primary', 'base', 'system-font', 'body-font' ], true ) ) {
						return [
							'name'  => $font['name'] ?? __( 'Theme Font', 'pressprimer-quiz' ),
							'value' => $font['fontFamily'] ?? '',
						];
					}
				}
				// If no body font found, use the first one
				$first_font = reset( $font_families );
				if ( ! empty( $first_font['fontFamily'] ) ) {
					return [
						'name'  => $first_font['name'] ?? __( 'Theme Font', 'pressprimer-quiz' ),
						'value' => $first_font['fontFamily'],
					];
				}
			}
		}

		// Try customizer setting (common in classic themes)
		$body_font = get_theme_mod( 'body_font_family' );
		if ( ! empty( $body_font ) ) {
			return [
				'name'  => __( 'Theme Font', 'pressprimer-quiz' ),
				'value' => $body_font,
			];
		}

		// Fallback: try common customizer setting names
		$common_settings = [ 'body_font', 'font_body', 'typography_body_font', 'base_font' ];
		foreach ( $common_settings as $setting ) {
			$font = get_theme_mod( $setting );
			if ( ! empty( $font ) ) {
				return [
					'name'  => __( 'Theme Font', 'pressprimer-quiz' ),
					'value' => $font,
				];
			}
		}

		return null;
	}

	/**
	 * AJAX handler: Save user API key
	 *
	 * Saves the API key and/or model preference for the current user.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_user_api_key() {
		// Verify nonce
		if ( ! check_ajax_referer( 'pressprimer_quiz_api_key_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ] );
		}

		$user_id = get_current_user_id();

		// Handle API key
		if ( isset( $_POST['api_key'] ) && '' !== $_POST['api_key'] ) {
			$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );

			// Basic format validation
			if ( strlen( $api_key ) < 20 || strpos( $api_key, 'sk-' ) !== 0 ) {
				wp_send_json_error( [ 'message' => __( 'Invalid API key format. Keys should start with "sk-".', 'pressprimer-quiz' ) ] );
			}

			// Validate the key with OpenAI
			$validation = PressPrimer_Quiz_AI_Service::validate_api_key( $api_key );

			if ( is_wp_error( $validation ) ) {
				wp_send_json_error( [ 'message' => $validation->get_error_message() ] );
			}

			// Save the key
			$result = PressPrimer_Quiz_AI_Service::save_api_key( $user_id, $api_key );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( [ 'message' => $result->get_error_message() ] );
			}

			wp_send_json_success( [ 'message' => __( 'API key saved and validated successfully.', 'pressprimer-quiz' ) ] );
		}

		// Handle model preference
		if ( isset( $_POST['model'] ) ) {
			$model = sanitize_text_field( wp_unslash( $_POST['model'] ) );
			PressPrimer_Quiz_AI_Service::save_model_preference( $user_id, $model );
			wp_send_json_success( [ 'message' => __( 'Model preference saved.', 'pressprimer-quiz' ) ] );
		}

		wp_send_json_error( [ 'message' => __( 'No data provided.', 'pressprimer-quiz' ) ] );
	}

	/**
	 * AJAX handler: Validate API key
	 *
	 * Validates the current user's stored API key.
	 *
	 * @since 1.0.0
	 */
	public function ajax_validate_api_key() {
		// Verify nonce
		if ( ! check_ajax_referer( 'pressprimer_quiz_api_key_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ] );
		}

		$user_id = get_current_user_id();
		$api_key = PressPrimer_Quiz_AI_Service::get_api_key( $user_id );

		if ( empty( $api_key ) || is_wp_error( $api_key ) ) {
			wp_send_json_error( [ 'message' => __( 'No API key configured.', 'pressprimer-quiz' ) ] );
		}

		// Validate with OpenAI
		$validation = PressPrimer_Quiz_AI_Service::validate_api_key( $api_key );

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( [ 'message' => $validation->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => __( 'API key is valid and working correctly.', 'pressprimer-quiz' ) ] );
	}

	/**
	 * AJAX handler: Clear user API key
	 *
	 * Removes the API key for the current user.
	 *
	 * @since 1.0.0
	 */
	public function ajax_clear_user_api_key() {
		// Verify nonce
		if ( ! check_ajax_referer( 'pressprimer_quiz_api_key_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ] );
		}

		$user_id = get_current_user_id();

		// Clear the key (passing empty string)
		$result = PressPrimer_Quiz_AI_Service::save_api_key( $user_id, '' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => __( 'API key removed successfully.', 'pressprimer-quiz' ) ] );
	}

	/**
	 * AJAX handler: Get available models
	 *
	 * Fetches available GPT models from OpenAI.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_api_models() {
		// Verify nonce
		if ( ! check_ajax_referer( 'pressprimer_quiz_api_key_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'pressprimer_quiz_manage_own' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ] );
		}

		$user_id = get_current_user_id();
		$api_key = PressPrimer_Quiz_AI_Service::get_api_key( $user_id );

		if ( empty( $api_key ) || is_wp_error( $api_key ) ) {
			wp_send_json_error( [ 'message' => __( 'No API key configured.', 'pressprimer-quiz' ) ] );
		}

		// Fetch models
		$models = PressPrimer_Quiz_AI_Service::get_available_models( $api_key );

		if ( is_wp_error( $models ) ) {
			wp_send_json_error( [ 'message' => $models->get_error_message() ] );
		}

		wp_send_json_success( [ 'models' => $models ] );
	}

	/**
	 * AJAX handler: Repair database tables
	 *
	 * Recreates any missing database tables.
	 *
	 * @since 1.0.0
	 */
	public function ajax_repair_database_tables() {
		// Verify nonce
		if ( ! check_ajax_referer( 'pressprimer_quiz_repair_tables_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied. Administrator access required.', 'pressprimer-quiz' ) ] );
		}

		// Attempt repair
		if ( ! class_exists( 'PressPrimer_Quiz_Migrator' ) ) {
			wp_send_json_error( [ 'message' => __( 'Migrator class not available.', 'pressprimer-quiz' ) ] );
		}

		$result = PressPrimer_Quiz_Migrator::repair_tables();

		if ( ! $result['success'] ) {
			$message = isset( $result['error'] )
				? $result['error']
				: __( 'Some tables could not be repaired. Please check your database permissions.', 'pressprimer-quiz' );
			wp_send_json_error( [ 'message' => $message ] );
		}

		// Get updated table status
		$updated_status = PressPrimer_Quiz_Migrator::get_table_status();

		wp_send_json_success(
			[
				'message'     => sprintf(
					/* translators: %d: number of tables repaired */
					_n(
						'%d table was successfully created.',
						'%d tables were successfully created.',
						count( $result['repaired'] ),
						'pressprimer-quiz'
					),
					count( $result['repaired'] )
				),
				'repaired'    => $result['repaired'],
				'tableStatus' => $updated_status,
			]
		);
	}

	/**
	 * AJAX handler: Save site-wide API key
	 *
	 * Saves the site-wide API key that all users can use.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_site_api_key() {
		// Verify nonce
		if ( ! check_ajax_referer( 'pressprimer_quiz_site_api_key', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability - only admins can set site-wide key
		if ( ! current_user_can( 'pressprimer_quiz_manage_settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied. Administrator access required.', 'pressprimer-quiz' ) ] );
		}

		if ( ! isset( $_POST['api_key'] ) || '' === $_POST['api_key'] ) {
			wp_send_json_error( [ 'message' => __( 'No API key provided.', 'pressprimer-quiz' ) ] );
		}

		$api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );

		// Basic format validation
		if ( strlen( $api_key ) < 20 || strpos( $api_key, 'sk-' ) !== 0 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid API key format. Keys should start with "sk-".', 'pressprimer-quiz' ) ] );
		}

		// Validate the key with OpenAI
		$validation = PressPrimer_Quiz_AI_Service::validate_api_key( $api_key );

		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( [ 'message' => $validation->get_error_message() ] );
		}

		// Encrypt and save the key
		$encrypted = PressPrimer_Quiz_Helpers::encrypt( $api_key );

		if ( is_wp_error( $encrypted ) ) {
			wp_send_json_error( [ 'message' => $encrypted->get_error_message() ] );
		}

		update_option( 'pressprimer_quiz_site_openai_api_key', $encrypted );

		wp_send_json_success( [ 'message' => __( 'Site-wide API key saved and validated successfully.', 'pressprimer-quiz' ) ] );
	}

	/**
	 * AJAX handler: Clear site-wide API key
	 *
	 * Removes the site-wide API key.
	 *
	 * @since 1.0.0
	 */
	public function ajax_clear_site_api_key() {
		// Verify nonce
		if ( ! check_ajax_referer( 'pressprimer_quiz_site_api_key', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability - only admins can clear site-wide key
		if ( ! current_user_can( 'pressprimer_quiz_manage_settings' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied. Administrator access required.', 'pressprimer-quiz' ) ] );
		}

		delete_option( 'pressprimer_quiz_site_openai_api_key' );

		wp_send_json_success( [ 'message' => __( 'Site-wide API key removed successfully.', 'pressprimer-quiz' ) ] );
	}
}
