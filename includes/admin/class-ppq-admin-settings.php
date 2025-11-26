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
class PPQ_Admin_Settings {

	/**
	 * Settings option name
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_NAME = 'ppq_settings';

	/**
	 * Initialize settings
	 *
	 * Registers settings, sections, and fields.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
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
			'ppq_settings_group',
			self::OPTION_NAME,
			[
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
			]
		);

		// General Section
		add_settings_section(
			'ppq_general_section',
			__( 'General Settings', 'pressprimer-quiz' ),
			[ $this, 'render_general_section' ],
			'ppq-settings'
		);

		// Quiz Defaults Section
		add_settings_section(
			'ppq_quiz_defaults_section',
			__( 'Quiz Defaults', 'pressprimer-quiz' ),
			[ $this, 'render_quiz_defaults_section' ],
			'ppq-settings'
		);

		// Email Section
		add_settings_section(
			'ppq_email_section',
			__( 'Email Settings', 'pressprimer-quiz' ),
			[ $this, 'render_email_section' ],
			'ppq-settings'
		);

		// API Keys Section
		add_settings_section(
			'ppq_api_keys_section',
			__( 'API Keys', 'pressprimer-quiz' ),
			[ $this, 'render_api_keys_section' ],
			'ppq-settings'
		);

		// Advanced Section
		add_settings_section(
			'ppq_advanced_section',
			__( 'Advanced Settings', 'pressprimer-quiz' ),
			[ $this, 'render_advanced_section' ],
			'ppq-settings'
		);

		// Register individual fields
		$this->register_general_fields();
		$this->register_quiz_default_fields();
		$this->register_email_fields();
		$this->register_api_key_fields();
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
			'ppq-settings',
			'ppq_quiz_defaults_section'
		);

		// Default quiz mode
		add_settings_field(
			'default_quiz_mode',
			__( 'Default Quiz Mode', 'pressprimer-quiz' ),
			[ $this, 'render_quiz_mode_field' ],
			'ppq-settings',
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
			'ppq-settings',
			'ppq_email_section'
		);

		// Email from address
		add_settings_field(
			'email_from_address',
			__( 'From Email Address', 'pressprimer-quiz' ),
			[ $this, 'render_email_from_address_field' ],
			'ppq-settings',
			'ppq_email_section'
		);
	}

	/**
	 * Register API key fields
	 *
	 * @since 1.0.0
	 */
	private function register_api_key_fields() {
		// OpenAI API key
		add_settings_field(
			'openai_api_key',
			__( 'OpenAI API Key', 'pressprimer-quiz' ),
			[ $this, 'render_openai_api_key_field' ],
			'ppq-settings',
			'ppq_api_keys_section'
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
			'ppq-settings',
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
		echo '<p>' . esc_html__( 'API keys for third-party integrations. All keys are encrypted before storage.', 'pressprimer-quiz' ) . '</p>';
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
				<?php esc_html_e( 'Timed Mode (feedback at end)', 'pressprimer-quiz' ); ?>
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
		$value    = isset( $settings['email_from_address'] ) ? $settings['email_from_address'] : get_bloginfo( 'admin_email' );
		?>
		<input
			type="email"
			name="<?php echo esc_attr( self::OPTION_NAME . '[email_from_address]' ); ?>"
			id="email_from_address"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Email address shown in the "From" field of emails sent by the plugin.', 'pressprimer-quiz' ); ?>
		</p>
		<?php
	}

	/**
	 * Render OpenAI API key field
	 *
	 * @since 1.0.0
	 */
	public function render_openai_api_key_field() {
		$current_user_id = get_current_user_id();
		$encrypted       = get_user_meta( $current_user_id, 'ppq_openai_api_key', true );
		$has_key         = ! empty( $encrypted );
		?>
		<input
			type="password"
			name="<?php echo esc_attr( self::OPTION_NAME . '[openai_api_key]' ); ?>"
			id="openai_api_key"
			value=""
			class="regular-text"
			placeholder="<?php echo $has_key ? esc_attr__( '••••••••••••••••', 'pressprimer-quiz' ) : ''; ?>"
			autocomplete="off"
		/>
		<?php if ( $has_key ) : ?>
			<p class="description">
				<?php esc_html_e( 'API key is currently set. Enter a new key to replace it, or leave blank to keep current key.', 'pressprimer-quiz' ); ?>
			</p>
		<?php else : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: OpenAI API keys URL */
					esc_html__( 'Your OpenAI API key for AI question generation. Get your key from %s.', 'pressprimer-quiz' ),
					'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">OpenAI</a>'
				);
				?>
			</p>
		<?php endif; ?>
		<p class="description">
			<strong><?php esc_html_e( 'Note:', 'pressprimer-quiz' ); ?></strong>
			<?php esc_html_e( 'API keys are stored per-user (not globally) and are encrypted in the database.', 'pressprimer-quiz' ); ?>
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
		if ( isset( $input['email_from_address'] ) ) {
			$email = sanitize_email( $input['email_from_address'] );
			if ( ! is_email( $email ) ) {
				add_settings_error(
					self::OPTION_NAME,
					'invalid_email',
					__( 'Please enter a valid email address.', 'pressprimer-quiz' ),
					'error'
				);
				$email = get_bloginfo( 'admin_email' );
			}
			$sanitized['email_from_address'] = $email;
		}

		// Handle OpenAI API key (stored per-user, encrypted)
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
				// Encrypt and store per-user
				$encrypted = PPQ_Helpers::encrypt( $api_key );

				if ( is_wp_error( $encrypted ) ) {
					add_settings_error(
						self::OPTION_NAME,
						'encryption_failed',
						$encrypted->get_error_message(),
						'error'
					);
				} else {
					update_user_meta( get_current_user_id(), 'ppq_openai_api_key', $encrypted );
					add_settings_error(
						self::OPTION_NAME,
						'api_key_saved',
						__( 'OpenAI API key saved successfully.', 'pressprimer-quiz' ),
						'success'
					);
				}
			}
		}

		// Sanitize remove data on uninstall
		$sanitized['remove_data_on_uninstall'] = isset( $input['remove_data_on_uninstall'] ) && '1' === $input['remove_data_on_uninstall'];

		// Merge with existing settings to preserve any not in the form
		return array_merge( $existing, $sanitized );
	}

	/**
	 * Render settings page
	 *
	 * Displays the settings page HTML.
	 *
	 * @since 1.0.0
	 */
	public function render_page() {
		// Check capability
		if ( ! current_user_can( 'ppq_manage_settings' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-quiz' ),
				esc_html__( 'Permission Denied', 'pressprimer-quiz' ),
				[ 'response' => 403 ]
			);
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'ppq_settings_group' );
				do_settings_sections( 'ppq-settings' );
				submit_button();
				?>
			</form>

			<div class="ppq-settings-footer" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #c3c4c7;">
				<h2><?php esc_html_e( 'System Information', 'pressprimer-quiz' ); ?></h2>
				<table class="widefat striped" style="max-width: 600px;">
					<tbody>
						<tr>
							<th style="width: 200px;"><?php esc_html_e( 'Plugin Version', 'pressprimer-quiz' ); ?></th>
							<td><?php echo esc_html( PPQ_VERSION ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Database Version', 'pressprimer-quiz' ); ?></th>
							<td><?php echo esc_html( get_option( 'ppq_db_version', 'Not set' ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'WordPress Version', 'pressprimer-quiz' ); ?></th>
							<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'PHP Version', 'pressprimer-quiz' ); ?></th>
							<td><?php echo esc_html( PHP_VERSION ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
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
}
