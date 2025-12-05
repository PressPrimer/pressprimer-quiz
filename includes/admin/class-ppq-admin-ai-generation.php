<?php
/**
 * Admin AI Generation class
 *
 * Handles the AI question generation interface in the admin area.
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
 * Admin AI Generation class
 *
 * Provides the interface for generating questions using AI.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Admin_AI_Generation {

	/**
	 * Initialize admin functionality
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Register AJAX handlers
		add_action( 'wp_ajax_ppq_ai_generate_questions', [ $this, 'ajax_generate_questions' ] );
		add_action( 'wp_ajax_ppq_ai_save_questions', [ $this, 'ajax_save_questions' ] );
		add_action( 'wp_ajax_ppq_ai_upload_file', [ $this, 'ajax_upload_file' ] );
		add_action( 'wp_ajax_ppq_ai_check_status', [ $this, 'ajax_check_status' ] );

		// Enqueue scripts on relevant pages
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only on our pages
		if ( strpos( $hook, 'ppq-banks' ) === false ) {
			return;
		}

		// Enqueue AI generation script
		wp_enqueue_script(
			'ppq-ai-generation',
			PPQ_PLUGIN_URL . 'assets/js/ai-generation.js',
			[ 'jquery' ],
			PPQ_VERSION,
			true
		);

		// Localize script data
		wp_localize_script(
			'ppq-ai-generation',
			'ppqAIGeneration',
			[
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( 'ppq_ai_generation' ),
				'maxFileSize'          => PressPrimer_Quiz_File_Processor::get_max_file_size(),
				'maxFileSizeFormatted' => size_format( PressPrimer_Quiz_File_Processor::get_max_file_size() ),
				'supportedTypes'       => PressPrimer_Quiz_File_Processor::get_supported_types(),
				'strings'              => [
					'generating'       => __( 'Generating questions...', 'pressprimer-quiz' ),
					'processing'       => __( 'Processing file...', 'pressprimer-quiz' ),
					'saving'           => __( 'Saving questions...', 'pressprimer-quiz' ),
					'success'          => __( 'Questions generated successfully!', 'pressprimer-quiz' ),
					'saved'            => __( 'Questions saved to bank!', 'pressprimer-quiz' ),
					'error'            => __( 'An error occurred. Please try again.', 'pressprimer-quiz' ),
					'fileTooLarge'     => __( 'File is too large. Maximum size is', 'pressprimer-quiz' ),
					'invalidFileType'  => __( 'Invalid file type. Supported types: PDF, DOCX', 'pressprimer-quiz' ),
					'noContent'        => __( 'Please enter text or upload a file.', 'pressprimer-quiz' ),
					'noApiKey'         => __( 'OpenAI API key is not configured. Please add your API key in the Settings.', 'pressprimer-quiz' ),
					'confirmDiscard'   => __( 'Are you sure you want to discard these generated questions?', 'pressprimer-quiz' ),
					'selectQuestions'  => __( 'Please select at least one question to save.', 'pressprimer-quiz' ),
					'truncatedWarning' => __( 'Content was truncated to 100,000 characters. Some text may not have been processed.', 'pressprimer-quiz' ),
					'discardTitle'     => __( 'Discard Questions', 'pressprimer-quiz' ),
					'discard'          => __( 'Discard', 'pressprimer-quiz' ),
					'cancel'           => __( 'Cancel', 'pressprimer-quiz' ),
					'successTitle'     => __( 'Success', 'pressprimer-quiz' ),
				],
			]
		);

		// Enqueue AI generation styles
		wp_enqueue_style(
			'ppq-ai-generation',
			PPQ_PLUGIN_URL . 'assets/css/ai-generation.css',
			[],
			PPQ_VERSION
		);
	}

	/**
	 * Render AI generation panel
	 *
	 * Outputs the HTML for the AI generation interface.
	 *
	 * @since 1.0.0
	 *
	 * @param int $bank_id Bank ID to add questions to.
	 */
	public function render_panel( $bank_id ) {
		// Get API key status
		$user_id    = get_current_user_id();
		$api_status = PressPrimer_Quiz_AI_Service::get_api_key_status( $user_id );

		// Get categories for selection
		$categories = [];
		if ( class_exists( 'PressPrimer_Quiz_Category' ) ) {
			$categories = PressPrimer_Quiz_Category::find(
				[
					'where'    => [ 'taxonomy' => 'category' ],
					'order_by' => 'name',
					'order'    => 'ASC',
				]
			);
		}

		// If API key not configured, show setup prompt
		if ( ! $api_status['configured'] ) :
			?>
		<div class="ppq-ai-generation-panel ppq-ai-generation-panel--no-key" data-bank-id="<?php echo esc_attr( $bank_id ); ?>">
			<div class="ppq-ai-setup-prompt">
				<span class="dashicons dashicons-admin-generic"></span>
				<h3><?php esc_html_e( 'AI Question Generation', 'pressprimer-quiz' ); ?></h3>
				<p><?php esc_html_e( 'Generate quiz questions automatically from your course content using AI.', 'pressprimer-quiz' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-settings' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Configure API Key in Settings', 'pressprimer-quiz' ); ?>
				</a>
			</div>
		</div>
			<?php
			return;
		endif;
		?>
		<div class="ppq-ai-generation-panel" data-bank-id="<?php echo esc_attr( $bank_id ); ?>">
			<!-- Input Mode Tabs -->
			<div class="ppq-ai-input-tabs">
				<button type="button" class="ppq-ai-tab ppq-ai-tab--active" data-tab="text">
					<span class="dashicons dashicons-edit"></span>
					<?php esc_html_e( 'Paste Text', 'pressprimer-quiz' ); ?>
				</button>
				<button type="button" class="ppq-ai-tab" data-tab="file">
					<span class="dashicons dashicons-upload"></span>
					<?php esc_html_e( 'Upload File', 'pressprimer-quiz' ); ?>
				</button>
			</div>

			<!-- Text Input Panel -->
			<div class="ppq-ai-tab-content ppq-ai-tab-content--active" data-tab-content="text">
				<label for="ppq-ai-content" class="ppq-ai-label">
					<?php esc_html_e( 'Source Content', 'pressprimer-quiz' ); ?>
					<span class="ppq-ai-label-hint"><?php esc_html_e( 'Paste the text you want to generate questions from.', 'pressprimer-quiz' ); ?></span>
				</label>
				<textarea
					id="ppq-ai-content"
					class="ppq-ai-textarea"
					rows="10"
					placeholder="<?php esc_attr_e( 'Paste your educational content here...', 'pressprimer-quiz' ); ?>"
				></textarea>
				<div class="ppq-ai-char-count">
					<span id="ppq-ai-char-current">0</span> / 100,000 <?php esc_html_e( 'characters', 'pressprimer-quiz' ); ?>
				</div>
			</div>

			<!-- File Upload Panel -->
			<div class="ppq-ai-tab-content" data-tab-content="file">
				<label class="ppq-ai-label">
					<?php esc_html_e( 'Upload Document', 'pressprimer-quiz' ); ?>
					<span class="ppq-ai-label-hint">
						<?php
						printf(
							/* translators: %s: maximum file size */
							esc_html__( 'Supported formats: PDF, DOCX. Maximum size: %s', 'pressprimer-quiz' ),
							esc_html( size_format( PressPrimer_Quiz_File_Processor::get_max_file_size() ) )
						);
						?>
					</span>
				</label>

				<div class="ppq-ai-upload-area" id="ppq-ai-upload-area">
					<div class="ppq-ai-upload-icon">
						<span class="dashicons dashicons-upload"></span>
					</div>
					<p class="ppq-ai-upload-text">
						<?php esc_html_e( 'Drag and drop a file here, or click to select', 'pressprimer-quiz' ); ?>
					</p>
					<input
						type="file"
						id="ppq-ai-file-input"
						class="ppq-ai-file-input"
						accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
					>
				</div>

				<div class="ppq-ai-file-info" id="ppq-ai-file-info" style="display: none;">
					<span class="dashicons dashicons-media-document"></span>
					<span id="ppq-ai-file-name"></span>
					<span id="ppq-ai-file-size"></span>
					<button type="button" class="ppq-ai-file-remove" id="ppq-ai-file-remove">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>

				<div class="ppq-ai-extracted-preview" id="ppq-ai-extracted-preview" style="display: none;">
					<label class="ppq-ai-label"><?php esc_html_e( 'Extracted Text Preview', 'pressprimer-quiz' ); ?></label>
					<div id="ppq-ai-extracted-text" class="ppq-ai-extracted-text"></div>
				</div>
			</div>

			<!-- Generation Parameters -->
			<div class="ppq-ai-parameters">
				<h3><?php esc_html_e( 'Generation Parameters', 'pressprimer-quiz' ); ?></h3>

				<div class="ppq-ai-params-grid">
					<!-- Question Count -->
					<div class="ppq-ai-param">
						<label for="ppq-ai-count"><?php esc_html_e( 'Number of Questions', 'pressprimer-quiz' ); ?></label>
						<input type="number" id="ppq-ai-count" class="ppq-ai-input" min="1" max="100" value="5" />
						<span class="ppq-ai-hint"><?php esc_html_e( '1-100', 'pressprimer-quiz' ); ?></span>
					</div>

					<!-- Answer Count for MC/MA -->
					<div class="ppq-ai-param">
						<label for="ppq-ai-answer-count"><?php esc_html_e( 'Answers per Question', 'pressprimer-quiz' ); ?></label>
						<input type="number" id="ppq-ai-answer-count" class="ppq-ai-input" min="3" max="6" value="4" />
						<span class="ppq-ai-hint"><?php esc_html_e( '3-6 (for MC/MA)', 'pressprimer-quiz' ); ?></span>
					</div>

					<!-- Question Types -->
					<div class="ppq-ai-param">
						<label><?php esc_html_e( 'Question Types', 'pressprimer-quiz' ); ?></label>
						<div class="ppq-ai-checkboxes">
							<label class="ppq-ai-checkbox">
								<input type="checkbox" name="ppq_ai_types[]" value="mc" checked>
								<?php esc_html_e( 'Multiple Choice', 'pressprimer-quiz' ); ?>
							</label>
							<label class="ppq-ai-checkbox">
								<input type="checkbox" name="ppq_ai_types[]" value="ma">
								<?php esc_html_e( 'Multiple Answer', 'pressprimer-quiz' ); ?>
							</label>
							<label class="ppq-ai-checkbox">
								<input type="checkbox" name="ppq_ai_types[]" value="tf">
								<?php esc_html_e( 'True/False', 'pressprimer-quiz' ); ?>
							</label>
						</div>
					</div>

					<!-- Difficulty -->
					<div class="ppq-ai-param">
						<label><?php esc_html_e( 'Difficulty Levels', 'pressprimer-quiz' ); ?></label>
						<div class="ppq-ai-checkboxes">
							<label class="ppq-ai-checkbox">
								<input type="checkbox" name="ppq_ai_difficulty[]" value="easy">
								<?php esc_html_e( 'Easy', 'pressprimer-quiz' ); ?>
							</label>
							<label class="ppq-ai-checkbox">
								<input type="checkbox" name="ppq_ai_difficulty[]" value="medium" checked>
								<?php esc_html_e( 'Medium', 'pressprimer-quiz' ); ?>
							</label>
							<label class="ppq-ai-checkbox">
								<input type="checkbox" name="ppq_ai_difficulty[]" value="hard">
								<?php esc_html_e( 'Hard', 'pressprimer-quiz' ); ?>
							</label>
							<label class="ppq-ai-checkbox">
								<input type="checkbox" name="ppq_ai_difficulty[]" value="expert">
								<?php esc_html_e( 'Expert', 'pressprimer-quiz' ); ?>
							</label>
						</div>
					</div>

					<!-- Categories -->
					<div class="ppq-ai-param">
						<label for="ppq-ai-categories"><?php esc_html_e( 'Assign Categories', 'pressprimer-quiz' ); ?></label>
						<select id="ppq-ai-categories" class="ppq-ai-select" multiple>
							<?php foreach ( $categories as $category ) : ?>
								<option value="<?php echo esc_attr( $category->id ); ?>">
									<?php echo esc_html( $category->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<span class="ppq-ai-hint"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple.', 'pressprimer-quiz' ); ?></span>
					</div>

					<!-- Generate Feedback Option -->
					<div class="ppq-ai-param ppq-ai-param--full-width">
						<label class="ppq-ai-checkbox ppq-ai-checkbox--switch">
							<input type="checkbox" id="ppq-ai-generate-feedback" checked>
							<span class="ppq-ai-switch-slider"></span>
							<span class="ppq-ai-switch-label"><?php esc_html_e( 'Generate correct and incorrect question feedback', 'pressprimer-quiz' ); ?></span>
						</label>
						<span class="ppq-ai-hint"><?php esc_html_e( 'Disabling saves tokens but questions will have no feedback text.', 'pressprimer-quiz' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Generate Button -->
			<div class="ppq-ai-actions">
				<button type="button" id="ppq-ai-generate-btn" class="button button-primary button-hero">
					<span class="dashicons dashicons-admin-generic"></span>
					<?php esc_html_e( 'Generate Questions', 'pressprimer-quiz' ); ?>
				</button>
				<div class="ppq-ai-loading" id="ppq-ai-loading" style="display: none;">
					<span class="spinner is-active"></span>
					<span id="ppq-ai-loading-text"><?php esc_html_e( 'Generating questions...', 'pressprimer-quiz' ); ?></span>
					<p class="ppq-ai-loading-note">
						<?php esc_html_e( 'This may take a few minutes for large content. Please wait...', 'pressprimer-quiz' ); ?>
					</p>
				</div>
			</div>

			<!-- Error Display -->
			<div class="ppq-ai-error" id="ppq-ai-error" style="display: none;">
				<span class="dashicons dashicons-warning"></span>
				<span id="ppq-ai-error-text"></span>
			</div>

			<!-- Results Panel -->
			<div class="ppq-ai-results" id="ppq-ai-results" style="display: none;">
				<div class="ppq-ai-results-header">
					<h3>
						<?php esc_html_e( 'Generated Questions', 'pressprimer-quiz' ); ?>
						<span id="ppq-ai-results-count"></span>
					</h3>
					<div class="ppq-ai-results-actions">
						<button type="button" id="ppq-ai-select-all" class="button">
							<?php esc_html_e( 'Select All', 'pressprimer-quiz' ); ?>
						</button>
						<button type="button" id="ppq-ai-deselect-all" class="button">
							<?php esc_html_e( 'Deselect All', 'pressprimer-quiz' ); ?>
						</button>
						<button type="button" id="ppq-ai-discard" class="button">
							<?php esc_html_e( 'Discard All', 'pressprimer-quiz' ); ?>
						</button>
					</div>
				</div>

				<div class="ppq-ai-results-info" id="ppq-ai-results-info" style="display: none;">
					<!-- Token usage and truncation warning displayed here -->
				</div>

				<div id="ppq-ai-questions-list" class="ppq-ai-questions-list">
					<!-- Questions populated via JavaScript -->
				</div>

				<div class="ppq-ai-save-actions">
					<button type="button" id="ppq-ai-save-selected" class="button button-primary" disabled>
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Add Selected to Bank', 'pressprimer-quiz' ); ?>
						<span id="ppq-ai-selected-count">(0)</span>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX handler: Generate questions
	 *
	 * @since 1.0.0
	 */
	public function ajax_generate_questions() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ppq_ai_generation', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ] );
		}

		$user_id = get_current_user_id();

		// Get API key
		$api_key = PressPrimer_Quiz_AI_Service::get_api_key( $user_id );
		if ( empty( $api_key ) || is_wp_error( $api_key ) ) {
			wp_send_json_error( [ 'message' => __( 'OpenAI API key is not configured.', 'pressprimer-quiz' ) ] );
		}

		// Get parameters
		$content = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
		$count   = isset( $_POST['count'] ) ? absint( wp_unslash( $_POST['count'] ) ) : 5;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values sanitized with sanitize_key via array_map
		$types = isset( $_POST['types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['types'] ) ) : [ 'mc' ];
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values sanitized with sanitize_key via array_map
		$difficulty        = isset( $_POST['difficulty'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['difficulty'] ) ) : [ 'medium' ];
		$answer_count      = isset( $_POST['answer_count'] ) ? absint( wp_unslash( $_POST['answer_count'] ) ) : 4;
		$generate_feedback = isset( $_POST['generate_feedback'] ) ? (bool) absint( wp_unslash( $_POST['generate_feedback'] ) ) : true;

		if ( empty( $content ) ) {
			wp_send_json_error( [ 'message' => __( 'No content provided.', 'pressprimer-quiz' ) ] );
		}

		// Initialize AI service
		$ai_service = new PressPrimer_Quiz_AI_Service( $api_key );

		// Set user's preferred model if available
		$model = PressPrimer_Quiz_AI_Service::get_model_preference( $user_id );
		if ( $model ) {
			$ai_service->set_model( $model );
		}

		// Generate questions
		$result = $ai_service->generate_from_content(
			$content,
			'text',
			[
				'count'             => $count,
				'types'             => $types,
				'difficulty'        => $difficulty,
				'answer_count'      => $answer_count,
				'generate_feedback' => $generate_feedback,
				'user_id'           => $user_id,
			]
		);

		if ( is_wp_error( $result ) ) {
			// Include additional error data if available
			$error_data = $result->get_error_data();
			$response   = [ 'message' => $result->get_error_message() ];

			if ( is_array( $error_data ) ) {
				if ( isset( $error_data['validation_errors'] ) ) {
					$response['validation_errors'] = $error_data['validation_errors'];
				}
				if ( isset( $error_data['total_generated'] ) ) {
					$response['total_generated'] = $error_data['total_generated'];
				}
			}

			wp_send_json_error( $response );
		}

		// Build response with all available metadata
		$response = [
			'questions'    => $result['questions'],
			'content_info' => $result['content_info'],
			'token_usage'  => isset( $result['token_usage'] ) ? $result['token_usage'] : [],
		];

		// Include validation info if available (partial success warnings)
		if ( isset( $result['validation'] ) ) {
			$response['validation'] = $result['validation'];
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX handler: Upload and process file
	 *
	 * @since 1.0.0
	 */
	public function ajax_upload_file() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ppq_ai_generation', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ] );
		}

		// Check if file was uploaded
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File array handled by WordPress functions in process_upload
		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( [ 'message' => __( 'No file uploaded.', 'pressprimer-quiz' ) ] );
		}

		// Process the file - $_FILES is sanitized by WordPress during wp_handle_upload
		$processor = new PressPrimer_Quiz_File_Processor();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitization handled in process_upload via wp_handle_upload
		$result = $processor->process_upload( $_FILES['file'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success(
			[
				'text'      => $result['text'],
				'file_name' => $result['file_name'],
				'file_type' => $result['file_type'],
				'file_size' => size_format( $result['file_size'] ),
			]
		);
	}

	/**
	 * AJAX handler: Save generated questions
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_questions() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ppq_ai_generation', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check capability
		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ] );
		}

		// Get parameters
		$bank_id = isset( $_POST['bank_id'] ) ? absint( wp_unslash( $_POST['bank_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Complex data structure sanitized in sanitize_question_data method
		$questions = isset( $_POST['questions'] ) ? wp_unslash( $_POST['questions'] ) : [];
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values sanitized with absint via array_map
		$categories = isset( $_POST['categories'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['categories'] ) ) : [];

		// Decode questions if sent as JSON string
		if ( is_string( $questions ) ) {
			$questions = json_decode( stripslashes( $questions ), true );
		}

		if ( ! $bank_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid bank ID.', 'pressprimer-quiz' ) ] );
		}

		if ( empty( $questions ) || ! is_array( $questions ) ) {
			wp_send_json_error( [ 'message' => __( 'No questions to save.', 'pressprimer-quiz' ) ] );
		}

		// Verify bank access
		$bank = PressPrimer_Quiz_Bank::get( $bank_id );
		if ( ! $bank ) {
			wp_send_json_error( [ 'message' => __( 'Bank not found.', 'pressprimer-quiz' ) ] );
		}

		if ( ! current_user_can( 'ppq_manage_all' ) && absint( $bank->owner_id ) !== get_current_user_id() ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to add to this bank.', 'pressprimer-quiz' ) ] );
		}

		$saved_count = 0;
		$errors      = [];

		foreach ( $questions as $index => $q_data ) {
			// Sanitize question data
			$question_data = $this->sanitize_question_data( $q_data );

			if ( is_wp_error( $question_data ) ) {
				$errors[] = sprintf( 'Question %d: %s', $index + 1, $question_data->get_error_message() );
				continue;
			}

			// Create the question
			$result = $this->create_question( $question_data, $categories, $bank_id );

			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf( 'Question %d: %s', $index + 1, $result->get_error_message() );
			} else {
				++$saved_count;
			}
		}

		// Update bank question count
		$bank->update_question_count();

		if ( $saved_count === 0 ) {
			wp_send_json_error(
				[
					'message' => __( 'Failed to save any questions.', 'pressprimer-quiz' ),
					'errors'  => $errors,
				]
			);
		}

		wp_send_json_success(
			[
				'saved_count' => $saved_count,
				'errors'      => $errors,
				'message'     => sprintf(
					/* translators: %d: number of questions saved */
					_n(
						'%d question saved to bank.',
						'%d questions saved to bank.',
						$saved_count,
						'pressprimer-quiz'
					),
					$saved_count
				),
			]
		);
	}

	/**
	 * AJAX handler: Check API key status
	 *
	 * @since 1.0.0
	 */
	public function ajax_check_status() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ppq_ai_generation', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		$user_id = get_current_user_id();
		$status  = PressPrimer_Quiz_AI_Service::get_api_key_status( $user_id );

		wp_send_json_success( $status );
	}

	/**
	 * Sanitize question data from AI generation
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Raw question data.
	 * @return array|WP_Error Sanitized data or error.
	 */
	private function sanitize_question_data( $data ) {
		// Decode if JSON string
		if ( is_string( $data ) ) {
			$data = json_decode( stripslashes( $data ), true );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid question data.', 'pressprimer-quiz' ) );
		}

		// Required fields
		if ( empty( $data['type'] ) || empty( $data['stem'] ) || empty( $data['answers'] ) ) {
			return new WP_Error( 'missing_fields', __( 'Question is missing required fields.', 'pressprimer-quiz' ) );
		}

		// Sanitize type
		$valid_types = [ 'mc', 'ma', 'tf' ];
		$type        = sanitize_key( $data['type'] );
		if ( ! in_array( $type, $valid_types, true ) ) {
			return new WP_Error( 'invalid_type', __( 'Invalid question type.', 'pressprimer-quiz' ) );
		}

		// Map AI difficulty to plugin difficulty
		$difficulty_map = [
			'easy'   => 'beginner',
			'medium' => 'intermediate',
			'hard'   => 'advanced',
			'expert' => 'expert',
		];
		$ai_difficulty  = isset( $data['difficulty'] ) ? sanitize_key( $data['difficulty'] ) : 'medium';
		$difficulty     = isset( $difficulty_map[ $ai_difficulty ] ) ? $difficulty_map[ $ai_difficulty ] : 'intermediate';

		// Sanitize answers
		$answers = [];
		if ( is_array( $data['answers'] ) ) {
			foreach ( $data['answers'] as $answer ) {
				if ( is_string( $answer ) ) {
					$answer = json_decode( stripslashes( $answer ), true );
				}

				if ( ! is_array( $answer ) || empty( $answer['text'] ) ) {
					continue;
				}

				$answers[] = [
					'text'       => sanitize_textarea_field( $answer['text'] ),
					'is_correct' => ! empty( $answer['is_correct'] ),
					'feedback'   => isset( $answer['feedback'] ) ? sanitize_textarea_field( $answer['feedback'] ) : '',
				];
			}
		}

		if ( count( $answers ) < 2 ) {
			return new WP_Error( 'insufficient_answers', __( 'Question must have at least 2 answers.', 'pressprimer-quiz' ) );
		}

		return [
			'type'               => $type,
			'difficulty'         => $difficulty,
			'stem'               => wp_kses_post( $data['stem'] ),
			'answers'            => $answers,
			'feedback_correct'   => isset( $data['feedback_correct'] ) ? sanitize_textarea_field( $data['feedback_correct'] ) : '',
			'feedback_incorrect' => isset( $data['feedback_incorrect'] ) ? sanitize_textarea_field( $data['feedback_incorrect'] ) : '',
		];
	}

	/**
	 * Create a question from AI-generated data
	 *
	 * @since 1.0.0
	 *
	 * @param array $data       Sanitized question data.
	 * @param array $categories Category IDs to assign.
	 * @param int   $bank_id    Bank ID to add to.
	 * @return int|WP_Error Question ID or error.
	 */
	private function create_question( $data, $categories, $bank_id ) {
		// Create question record
		$question_data = [
			'uuid'              => wp_generate_uuid4(),
			'type'              => $data['type'],
			'difficulty_author' => $data['difficulty'],
			'status'            => 'published',
			'author_id'         => get_current_user_id(),
		];

		$question_id = PressPrimer_Quiz_Question::create( $question_data );

		if ( is_wp_error( $question_id ) ) {
			return $question_id;
		}

		// Create revision with content
		$revision_data = [
			'stem'               => $data['stem'],
			'answers'            => $data['answers'],
			'feedback_correct'   => $data['feedback_correct'],
			'feedback_incorrect' => $data['feedback_incorrect'],
		];

		$revision_id = PressPrimer_Quiz_Question_Revision::create_for_question( $question_id, $revision_data );

		if ( is_wp_error( $revision_id ) ) {
			// Clean up question if revision fails
			$question = PressPrimer_Quiz_Question::get( $question_id );
			if ( $question ) {
				$question->delete( true );
			}
			return $revision_id;
		}

		// Note: create_for_question already updates current_revision_id on the question

		// Assign categories if provided
		if ( ! empty( $categories ) ) {
			$question = PressPrimer_Quiz_Question::get( $question_id );
			if ( $question ) {
				$question->set_categories( $categories );
			}
		}

		// Add to bank
		$bank = PressPrimer_Quiz_Bank::get( $bank_id );
		if ( $bank ) {
			$bank->add_question( $question_id );
		}

		return $question_id;
	}
}
