<?php
/**
 * TutorLMS Integration
 *
 * Integrates PressPrimer Quiz with TutorLMS for seamless quiz
 * experiences within courses.
 *
 * @package PressPrimer_Quiz
 * @subpackage Integrations
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TutorLMS Integration Class
 *
 * Handles all TutorLMS integration functionality including:
 * - Meta boxes for lesson post type
 * - Quiz display in lesson content
 * - Completion tracking on quiz pass
 * - Access control respecting TutorLMS enrollment
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_TutorLMS {

	/**
	 * Meta key for storing the PPQ quiz ID on lessons
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_QUIZ_ID = '_ppq_tutorlms_quiz_id';

	/**
	 * Meta key for storing require pass setting
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_REQUIRE_PASS = '_ppq_tutorlms_require_pass';

	/**
	 * Meta key for storing topic-level PPQ quiz ID
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_TOPIC_QUIZ_ID = '_ppq_tutorlms_topic_quiz_id';

	/**
	 * Supported post types for integration
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $supported_post_types = [ 'lesson' ];

	/**
	 * Initialize the integration
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Only initialize if TutorLMS is active.
		if ( ! defined( 'TUTOR_VERSION' ) ) {
			return;
		}

		// Admin hooks.
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_box' ], 10, 2 );

		// AJAX handler for classic editor quiz search.
		add_action( 'wp_ajax_pressprimer_quiz_search_quizzes_tutorlms', [ $this, 'ajax_search_quizzes' ] );

		// Gutenberg support.
		add_action( 'init', [ $this, 'register_meta_fields' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );

		// Course builder support.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_course_builder_assets' ] );

		// Frontend hooks.
		add_action( 'tutor_lesson/single/after/content', [ $this, 'display_quiz_in_lesson' ] );
		add_action( 'tutor_course/single/enrolled/after/lesson_list', [ $this, 'display_topic_quizzes' ] );
		add_filter( 'tutor_course/single/enrolled/topic_contents', [ $this, 'inject_topic_quiz_content' ], 10, 2 );

		// Hide Tutor's mark complete button when quiz is attached.
		add_filter( 'tutor_lesson/single/complete_form', [ $this, 'maybe_hide_complete_button' ] );

		// Completion tracking.
		add_action( 'pressprimer_quiz_quiz_passed', [ $this, 'handle_quiz_passed' ], 10, 2 );

		// REST API endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Register meta boxes for TutorLMS post types
	 *
	 * Only registers for Classic Editor - Gutenberg uses the sidebar panel.
	 *
	 * @since 1.0.0
	 */
	public function register_meta_boxes() {
		// Don't register metabox if using block editor.
		$screen = get_current_screen();
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return;
		}

		foreach ( $this->supported_post_types as $post_type ) {
			add_meta_box(
				'ppq_tutorlms_quiz',
				__( 'PressPrimer Quiz', 'pressprimer-quiz' ),
				[ $this, 'render_meta_box' ],
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render the meta box
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'pressprimer_quiz_tutorlms_meta_box', 'pressprimer_quiz_tutorlms_nonce' );

		$quiz_id      = get_post_meta( $post->ID, self::META_KEY_QUIZ_ID, true );
		$require_pass = get_post_meta( $post->ID, self::META_KEY_REQUIRE_PASS, true );

		// Get quiz display label if one is selected.
		$quiz_display = '';
		if ( $quiz_id ) {
			$quiz         = PressPrimer_Quiz_Quiz::get( $quiz_id );
			$quiz_display = $quiz ? sprintf( '%d - %s', $quiz->id, $quiz->title ) : '';
		}
		?>
		<div class="ppq-tutorlms-meta-box">
			<p>
				<label for="ppq_quiz_search">
					<?php esc_html_e( 'Select Quiz:', 'pressprimer-quiz' ); ?>
				</label>
			</p>
			<div class="ppq-quiz-selector">
				<input
					type="text"
					id="ppq_quiz_search"
					class="ppq-quiz-search widefat"
					placeholder="<?php esc_attr_e( 'Click to browse or type to search...', 'pressprimer-quiz' ); ?>"
					value="<?php echo esc_attr( $quiz_display ); ?>"
					autocomplete="off"
					<?php echo esc_attr( $quiz_id ? 'readonly' : '' ); ?>
				/>
				<input
					type="hidden"
					id="ppq_quiz_id"
					name="ppq_quiz_id"
					value="<?php echo esc_attr( $quiz_id ); ?>"
				/>
				<div id="ppq_quiz_results" class="ppq-quiz-results" style="display: none;"></div>
				<?php if ( $quiz_id ) : ?>
					<button type="button" class="ppq-remove-quiz button-link" aria-label="<?php esc_attr_e( 'Remove quiz', 'pressprimer-quiz' ); ?>" title="<?php esc_attr_e( 'Remove quiz', 'pressprimer-quiz' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				<?php endif; ?>
			</div>

			<p style="margin-top: 12px;">
				<label>
					<input
						type="checkbox"
						name="ppq_require_pass"
						value="1"
						<?php checked( $require_pass, '1' ); ?>
					/>
					<?php esc_html_e( 'Require passing score to complete lesson', 'pressprimer-quiz' ); ?>
				</label>
			</p>
			<p class="description">
				<?php esc_html_e( 'When enabled, students must pass this quiz to mark the lesson complete.', 'pressprimer-quiz' ); ?>
			</p>
		</div>
		<?php
		$this->enqueue_meta_box_assets();
	}

	/**
	 * Enqueue meta box assets (styles and scripts)
	 *
	 * @since 1.0.0
	 */
	private function enqueue_meta_box_assets() {
		// Ensure admin scripts are enqueued (they may not be on LMS post type edit screens).
		wp_enqueue_style(
			'ppq-admin',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/css/admin.css',
			[],
			PRESSPRIMER_QUIZ_VERSION
		);

		wp_enqueue_script(
			'ppq-admin',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			PRESSPRIMER_QUIZ_VERSION,
			true
		);

		// Localize script with nonces and translatable strings.
		wp_localize_script(
			'ppq-admin',
			'ppqTutorLMSMetaBox',
			[
				'nonce'   => wp_create_nonce( 'pressprimer_quiz_tutorlms_search' ),
				'strings' => [
					'noQuizzesFound' => __( 'No quizzes found', 'pressprimer-quiz' ),
					'removeQuiz'     => __( 'Remove quiz', 'pressprimer-quiz' ),
				],
			]
		);

		// Inline CSS for meta box styling.
		$inline_css = '
			.ppq-tutorlms-meta-box .ppq-quiz-selector {
				position: relative;
				display: flex;
				align-items: center;
				gap: 4px;
			}
			.ppq-tutorlms-meta-box .ppq-quiz-search {
				flex: 1;
			}
			.ppq-tutorlms-meta-box .ppq-quiz-search[readonly] {
				background: #f0f6fc;
				cursor: default;
			}
			.ppq-tutorlms-meta-box .ppq-quiz-results {
				position: absolute;
				top: 100%;
				left: 0;
				right: 30px;
				background: #fff;
				border: 1px solid #ddd;
				border-top: none;
				max-height: 250px;
				overflow-y: auto;
				z-index: 1000;
				box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			}
			.ppq-tutorlms-meta-box .ppq-quiz-result-item {
				padding: 8px 12px;
				cursor: pointer;
				border-bottom: 1px solid #f0f0f0;
			}
			.ppq-tutorlms-meta-box .ppq-quiz-result-item:hover {
				background: #f0f0f0;
			}
			.ppq-tutorlms-meta-box .ppq-quiz-result-item:last-child {
				border-bottom: none;
			}
			.ppq-tutorlms-meta-box .ppq-remove-quiz {
				color: #d63638;
				text-decoration: none;
				padding: 4px;
				border: none;
				background: none;
				cursor: pointer;
				display: flex;
				align-items: center;
			}
			.ppq-tutorlms-meta-box .ppq-remove-quiz:hover {
				color: #b32d2e;
			}
			.ppq-tutorlms-meta-box .ppq-no-results {
				padding: 12px;
				color: #666;
				font-style: italic;
			}
			.ppq-tutorlms-meta-box .ppq-quiz-result-item .ppq-quiz-id {
				color: #666;
				font-weight: 600;
				margin-right: 4px;
			}
		';
		wp_add_inline_style( 'ppq-admin', $inline_css );

		// Inline JavaScript for meta box functionality.
		$inline_script = 'jQuery(document).ready(function($) {' .
			'var config = window.ppqTutorLMSMetaBox || {};' .
			'var searchTimeout;' .
			'var $search = $("#ppq_quiz_search");' .
			'var $results = $("#ppq_quiz_results");' .
			'var $quizId = $("#ppq_quiz_id");' .
			'var $removeBtn = $(".ppq-remove-quiz");' .
			'function formatQuiz(quiz) {' .
				'return \'<div class="ppq-quiz-result-item" data-id="\' + quiz.id + \'" data-title="\' + $("<div/>").text(quiz.title).html() + \'">\' +' .
					'\'<span class="ppq-quiz-id">\' + quiz.id + \'</span> - \' + $("<div/>").text(quiz.title).html() +' .
					'\'</div>\';' .
			'}' .
			'$search.on("focus", function() {' .
				'if ($quizId.val()) { return; }' .
				'$.ajax({' .
					'url: ajaxurl,' .
					'type: "POST",' .
					'data: {' .
						'action: "pressprimer_quiz_search_quizzes_tutorlms",' .
						'nonce: config.nonce,' .
						'recent: 1' .
					'},' .
					'success: function(response) {' .
						'if (response.success && response.data.quizzes.length > 0) {' .
							'var html = "";' .
							'response.data.quizzes.forEach(function(quiz) {' .
								'html += formatQuiz(quiz);' .
							'});' .
							'$results.html(html).show();' .
						'}' .
					'}' .
				'});' .
			'});' .
			'$search.on("input", function() {' .
				'var query = $(this).val();' .
				'clearTimeout(searchTimeout);' .
				'if (query.length < 2) {' .
					'$search.trigger("focus");' .
					'return;' .
				'}' .
				'searchTimeout = setTimeout(function() {' .
					'$.ajax({' .
						'url: ajaxurl,' .
						'type: "POST",' .
						'data: {' .
							'action: "pressprimer_quiz_search_quizzes_tutorlms",' .
							'nonce: config.nonce,' .
							'search: query' .
						'},' .
						'success: function(response) {' .
							'if (response.success && response.data.quizzes.length > 0) {' .
								'var html = "";' .
								'response.data.quizzes.forEach(function(quiz) {' .
									'html += formatQuiz(quiz);' .
								'});' .
								'$results.html(html).show();' .
							'} else {' .
								'$results.html(\'<div class="ppq-no-results">\' + config.strings.noQuizzesFound + \'</div>\').show();' .
							'}' .
						'}' .
					'});' .
				'}, 300);' .
			'});' .
			'$results.on("click", ".ppq-quiz-result-item", function() {' .
				'var $item = $(this);' .
				'var id = $item.data("id");' .
				'var title = $item.data("title");' .
				'var display = id + " - " + title;' .
				'$quizId.val(id);' .
				'$search.val(display).attr("readonly", true);' .
				'$results.hide();' .
				'if (!$removeBtn.length) {' .
					'$search.after(\'<button type="button" class="ppq-remove-quiz button-link" aria-label="\' + config.strings.removeQuiz + \'" title="\' + config.strings.removeQuiz + \'"><span class="dashicons dashicons-no-alt"></span></button>\');' .
					'$removeBtn = $(".ppq-remove-quiz");' .
				'}' .
			'});' .
			'$(document).on("click", ".ppq-remove-quiz", function() {' .
				'$quizId.val("");' .
				'$search.val("").removeAttr("readonly");' .
				'$(this).remove();' .
				'$removeBtn = $();' .
			'});' .
			'$(document).on("click", function(e) {' .
				'if (!$(e.target).closest(".ppq-quiz-selector").length) {' .
					'$results.hide();' .
				'}' .
			'});' .
		'});';
		wp_add_inline_script( 'ppq-admin', $inline_script );
	}

	/**
	 * Save meta box data
	 *
	 * @since 1.0.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta_box( $post_id, $post ) {
		// Verify nonce.
		if ( ! isset( $_POST['pressprimer_quiz_tutorlms_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pressprimer_quiz_tutorlms_nonce'] ) ), 'pressprimer_quiz_tutorlms_meta_box' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check post type.
		if ( ! in_array( $post->post_type, $this->supported_post_types, true ) ) {
			return;
		}

		// Check permissions.
		if ( ! PressPrimer_Quiz_Helpers::current_user_can_edit_post( $post_id ) ) {
			return;
		}

		// Save quiz ID.
		$quiz_id = isset( $_POST['ppq_quiz_id'] ) ? absint( wp_unslash( $_POST['ppq_quiz_id'] ) ) : 0;
		if ( $quiz_id ) {
			update_post_meta( $post_id, self::META_KEY_QUIZ_ID, $quiz_id );
		} else {
			delete_post_meta( $post_id, self::META_KEY_QUIZ_ID );
		}

		// Save require pass setting.
		$require_pass = isset( $_POST['ppq_require_pass'] ) ? '1' : '';
		update_post_meta( $post_id, self::META_KEY_REQUIRE_PASS, $require_pass );
	}

	/**
	 * Register meta fields for Gutenberg
	 *
	 * @since 1.0.0
	 */
	public function register_meta_fields() {
		foreach ( $this->supported_post_types as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY_QUIZ_ID,
				[
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'integer',
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				]
			);

			register_post_meta(
				$post_type,
				self::META_KEY_REQUIRE_PASS,
				[
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				]
			);
		}
	}

	/**
	 * Enqueue block editor assets
	 *
	 * @since 1.0.0
	 */
	public function enqueue_block_editor_assets() {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, $this->supported_post_types, true ) ) {
			return;
		}

		wp_enqueue_script(
			'ppq-tutorlms-editor',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/js/tutorlms-editor.js',
			[ 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ],
			PRESSPRIMER_QUIZ_VERSION,
			true
		);

		wp_localize_script(
			'ppq-tutorlms-editor',
			'pressprimerQuizTutorLMS',
			[
				'metaKeyQuizId'      => self::META_KEY_QUIZ_ID,
				'metaKeyRequirePass' => self::META_KEY_REQUIRE_PASS,
				'postType'           => $screen->post_type,
				'restNonce'          => wp_create_nonce( 'wp_rest' ),
				'strings'            => [
					'panelTitle'        => __( 'PressPrimer Quiz', 'pressprimer-quiz' ),
					'selectQuiz'        => __( 'Select Quiz', 'pressprimer-quiz' ),
					'searchPlaceholder' => __( 'Click to browse or type to search...', 'pressprimer-quiz' ),
					'noQuiz'            => __( 'No quiz selected', 'pressprimer-quiz' ),
					'requirePassLabel'  => __( 'Require passing score to complete lesson', 'pressprimer-quiz' ),
					'requirePassHelp'   => __( 'When enabled, students must pass this quiz to mark the lesson complete.', 'pressprimer-quiz' ),
				],
			]
		);
	}

	/**
	 * Enqueue course builder assets
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_course_builder_assets( $hook ) {
		// Only load on TutorLMS course builder page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'tutor_page_create-course' !== $hook && ! isset( $_GET['page'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'create-course' !== $page ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$course_id = isset( $_GET['course_id'] ) ? absint( wp_unslash( $_GET['course_id'] ) ) : 0;

		// Get existing lesson quiz associations for this course.
		$lesson_quizzes = $this->get_lesson_quizzes_for_course( $course_id );

		wp_enqueue_script(
			'ppq-tutorlms-course-builder',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/js/tutorlms-course-builder.js',
			[],
			PRESSPRIMER_QUIZ_VERSION,
			true
		);

		wp_localize_script(
			'ppq-tutorlms-course-builder',
			'pressprimerQuizTutorCourseBuilder',
			[
				'courseId'      => $course_id,
				'restUrl'       => rest_url(),
				'restNonce'     => wp_create_nonce( 'wp_rest' ),
				'adminUrl'      => admin_url(),
				'lessonQuizzes' => $lesson_quizzes,
				'strings'       => [
					'searchPlaceholder' => __( 'Search quizzes...', 'pressprimer-quiz' ),
					'noQuizzes'         => __( 'No quizzes found', 'pressprimer-quiz' ),
					'error'             => __( 'Error loading quizzes', 'pressprimer-quiz' ),
				],
			]
		);
	}

	/**
	 * Get lesson quiz associations for a course
	 *
	 * @since 1.0.0
	 *
	 * @param int $course_id Course ID.
	 * @return array Associative array of lesson_id => quiz data.
	 */
	private function get_lesson_quizzes_for_course( $course_id ) {
		if ( ! $course_id ) {
			return [];
		}

		$lesson_quizzes = [];

		// Get all lessons for this course (lessons are children of topics, which are children of courses).
		$topics = get_posts(
			[
				'post_type'      => 'topics',
				'post_parent'    => $course_id,
				'posts_per_page' => -1,
				'post_status'    => 'any',
			]
		);

		foreach ( $topics as $topic ) {
			$lessons = get_posts(
				[
					'post_type'      => 'lesson',
					'post_parent'    => $topic->ID,
					'posts_per_page' => -1,
					'post_status'    => 'any',
				]
			);

			foreach ( $lessons as $lesson ) {
				$quiz_id = get_post_meta( $lesson->ID, self::META_KEY_QUIZ_ID, true );
				if ( $quiz_id ) {
					$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
					if ( $quiz ) {
						$lesson_quizzes[ $lesson->ID ] = [
							'id'    => $quiz->id,
							'title' => $quiz->title,
						];
					}
				}
			}
		}

		return $lesson_quizzes;
	}

	/**
	 * AJAX handler for searching quizzes
	 *
	 * @since 1.0.0
	 */
	public function ajax_search_quizzes() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'pressprimer_quiz_tutorlms_search', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ] );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ppq_quizzes';

		$recent = isset( $_POST['recent'] ) && rest_sanitize_boolean( wp_unslash( $_POST['recent'] ) );

		if ( $recent ) {
			$user_id = get_current_user_id();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- AJAX search results, not suitable for caching
			$quizzes = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, title FROM {$table} WHERE status = 'published' AND owner_id = %d ORDER BY id DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id
				)
			);

			wp_send_json_success( [ 'quizzes' => $quizzes ] );
			return;
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( [ 'quizzes' => [] ] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- AJAX search results, not suitable for caching
		$quizzes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title FROM {$table} WHERE title LIKE %s AND status = 'published' ORDER BY title ASC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%' . $wpdb->esc_like( $search ) . '%'
			)
		);

		wp_send_json_success( [ 'quizzes' => $quizzes ] );
	}

	/**
	 * Display quiz in lesson content
	 *
	 * @since 1.0.0
	 */
	public function display_quiz_in_lesson() {
		$post_id = get_the_ID();
		$quiz_id = get_post_meta( $post_id, self::META_KEY_QUIZ_ID, true );

		if ( ! $quiz_id ) {
			return;
		}

		// Check if user is enrolled in the course.
		$course_id = $this->get_course_id_for_lesson( $post_id );
		$user_id   = get_current_user_id();

		if ( $course_id && ! $this->is_user_enrolled( $user_id, $course_id ) ) {
			echo '<div class="ppq-tutorlms-access-denied">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML
			echo '<p>' . esc_html__( 'Enroll in this course to access the quiz.', 'pressprimer-quiz' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML with escaped string
			echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML
			return;
		}

		// Add context data for navigation.
		$context_data = [
			'tutorlms_lesson_id' => $post_id,
			'tutorlms_course_id' => $course_id,
		];

		// Render quiz shortcode with context.
		$quiz_shortcode = sprintf(
			'[pressprimer_quiz id="%d" context="%s"]',
			absint( $quiz_id ),
			esc_attr( base64_encode( wp_json_encode( $context_data ) ) )
		);

		echo '<div class="ppq-tutorlms-quiz-wrapper">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML
		echo do_shortcode( $quiz_shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output
		echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML
	}

	/**
	 * Display topic quizzes on course page
	 *
	 * This adds PPQ quizzes to the topic content list on the course page.
	 *
	 * @since 1.0.0
	 *
	 * @param int $course_id Course ID.
	 */
	public function display_topic_quizzes( $course_id ) {
		// Get all topics for this course.
		$topics = get_posts(
			[
				'post_type'      => 'topics',
				'post_parent'    => $course_id,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			]
		);

		$has_quizzes = false;
		foreach ( $topics as $topic ) {
			$quiz_id = get_post_meta( $topic->ID, self::META_KEY_TOPIC_QUIZ_ID, true );
			if ( $quiz_id ) {
				$has_quizzes = true;
				break;
			}
		}

		if ( ! $has_quizzes ) {
			return;
		}

		// Enqueue frontend styles for topic quizzes.
		wp_add_inline_style(
			'tutor-frontend',
			'
			.ppq-topic-quiz-item {
				display: flex;
				align-items: center;
				padding: 12px 16px;
				background: #f8f9fa;
				border-left: 3px solid #7b68ee;
				margin: 8px 0;
				border-radius: 4px;
			}
			.ppq-topic-quiz-item .ppq-quiz-icon {
				margin-right: 12px;
				color: #7b68ee;
			}
			.ppq-topic-quiz-item .ppq-quiz-title {
				flex: 1;
				font-weight: 500;
			}
			.ppq-topic-quiz-item .ppq-quiz-link {
				color: #7b68ee;
				text-decoration: none;
			}
			.ppq-topic-quiz-item .ppq-quiz-link:hover {
				text-decoration: underline;
			}
		'
		);
	}

	/**
	 * Inject topic quiz content into the topic content list
	 *
	 * @since 1.0.0
	 *
	 * @param array $contents Topic contents array.
	 * @param int   $topic_id Topic post ID.
	 * @return array Modified contents array.
	 */
	public function inject_topic_quiz_content( $contents, $topic_id ) {
		$quiz_id = get_post_meta( $topic_id, self::META_KEY_TOPIC_QUIZ_ID, true );

		if ( ! $quiz_id ) {
			return $contents;
		}

		$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
		if ( ! $quiz ) {
			return $contents;
		}

		// Create a pseudo-content item for the PPQ quiz.
		$quiz_content = (object) [
			'ID'           => 'ppq-quiz-' . $quiz_id,
			'post_title'   => $quiz->title,
			'post_type'    => 'ppq_quiz',
			'post_status'  => 'publish',
			'ppq_quiz_id'  => $quiz_id,
			'ppq_topic_id' => $topic_id,
			'is_ppq_quiz'  => true,
		];

		// Add to the end of the contents.
		$contents[] = $quiz_content;

		return $contents;
	}

	/**
	 * Maybe hide the complete button when quiz is attached
	 *
	 * @since 1.0.0
	 *
	 * @param string $form Complete form HTML.
	 * @return string Modified form HTML.
	 */
	public function maybe_hide_complete_button( $form ) {
		$post_id = get_the_ID();
		$quiz_id = get_post_meta( $post_id, self::META_KEY_QUIZ_ID, true );

		if ( $quiz_id ) {
			// Only hide the complete button if "Require passing score" is enabled.
			$require_pass = get_post_meta( $post_id, self::META_KEY_REQUIRE_PASS, true );

			if ( '1' === $require_pass ) {
				// Return empty to hide the complete button - user must pass quiz.
				return '';
			}
		}

		return $form;
	}

	/**
	 * Handle quiz passed event
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @param PressPrimer_Quiz_Quiz    $quiz    Quiz object.
	 */
	public function handle_quiz_passed( $attempt, $quiz ) {
		// Only for logged-in users.
		if ( ! $attempt->user_id ) {
			return;
		}

		// Find TutorLMS lessons that use this quiz.
		$lessons = $this->get_lessons_for_quiz( $quiz->id );

		foreach ( $lessons as $lesson ) {
			$require_pass = get_post_meta( $lesson->ID, self::META_KEY_REQUIRE_PASS, true );

			if ( '1' === $require_pass ) {
				$this->mark_lesson_complete( $lesson->ID, $attempt->user_id );
			}
		}
	}

	/**
	 * Get lessons that use a specific quiz
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array Array of post objects.
	 */
	private function get_lessons_for_quiz( $quiz_id ) {
		$args = [
			'post_type'      => 'lesson',
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'   => self::META_KEY_QUIZ_ID,
					'value' => $quiz_id,
				],
			],
		];

		return get_posts( $args );
	}

	/**
	 * Mark a TutorLMS lesson as complete
	 *
	 * @since 1.0.0
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @param int $user_id   User ID.
	 */
	private function mark_lesson_complete( $lesson_id, $user_id ) {
		// Use TutorLMS LessonModel class (TutorLMS 2.x+).
		if ( class_exists( '\Tutor\Models\LessonModel' ) ) {
			\Tutor\Models\LessonModel::mark_lesson_complete( $lesson_id, $user_id );

			/**
			 * Fires after PPQ marks a TutorLMS lesson complete.
			 *
			 * @since 1.0.0
			 *
			 * @param int $lesson_id Lesson post ID.
			 * @param int $user_id   User ID.
			 */
			do_action( 'pressprimer_quiz_tutorlms_lesson_completed', $lesson_id, $user_id );
		}
	}

	/**
	 * Get course ID for a lesson
	 *
	 * @since 1.0.0
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @return int|null Course ID or null.
	 */
	private function get_course_id_for_lesson( $lesson_id ) {
		// TutorLMS stores course ID in post meta.
		$course_id = get_post_meta( $lesson_id, '_tutor_course_id_for_lesson', true );

		if ( $course_id ) {
			return (int) $course_id;
		}

		// Try TutorLMS function.
		if ( function_exists( 'tutor_utils' ) ) {
			$utils = tutor_utils();
			if ( method_exists( $utils, 'get_course_id_by_lesson' ) ) {
				return $utils->get_course_id_by_lesson( $lesson_id );
			}
		}

		return null;
	}

	/**
	 * Check if user is enrolled in a course
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id   User ID.
	 * @param int $course_id Course ID.
	 * @return bool True if enrolled.
	 */
	private function is_user_enrolled( $user_id, $course_id ) {
		if ( ! $user_id ) {
			return false;
		}

		// Use TutorLMS function.
		if ( function_exists( 'tutor_utils' ) ) {
			$utils = tutor_utils();
			if ( method_exists( $utils, 'is_enrolled' ) ) {
				return (bool) $utils->is_enrolled( $course_id, $user_id );
			}
		}

		return true; // Default to allowing if we can't check.
	}

	/**
	 * Register REST routes
	 *
	 * @since 1.0.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			'ppq/v1',
			'/tutorlms/quizzes/search',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_search_quizzes' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'search' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'recent' => [
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/tutorlms/status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_get_status' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		register_rest_route(
			'ppq/v1',
			'/tutorlms/lesson-quiz',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_save_lesson_quiz' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'course_id' => [
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
					'lesson_id' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'quiz_id'   => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * REST endpoint: Search quizzes
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_search_quizzes( $request ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'ppq_quizzes';
		$recent = $request->get_param( 'recent' );

		if ( $recent ) {
			$user_id = get_current_user_id();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REST search results, not suitable for caching
			$quizzes = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, title FROM {$table} WHERE status = 'published' AND owner_id = %d ORDER BY id DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id
				)
			);

			return new WP_REST_Response(
				[
					'success' => true,
					'quizzes' => $quizzes,
				]
			);
		}

		$search = $request->get_param( 'search' );

		if ( empty( $search ) || strlen( $search ) < 2 ) {
			return new WP_REST_Response(
				[
					'success' => true,
					'quizzes' => [],
				]
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REST search results, not suitable for caching
		$quizzes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title FROM {$table} WHERE title LIKE %s AND status = 'published' ORDER BY title ASC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%' . $wpdb->esc_like( $search ) . '%'
			)
		);

		return new WP_REST_Response(
			[
				'success' => true,
				'quizzes' => $quizzes,
			]
		);
	}

	/**
	 * REST endpoint: Get TutorLMS integration status
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_status( $request ) {
		$status = [
			'active'      => defined( 'TUTOR_VERSION' ),
			'version'     => defined( 'TUTOR_VERSION' ) ? TUTOR_VERSION : null,
			'integration' => 'working',
		];

		// Count how many TutorLMS lessons have PPQ quizzes attached.
		if ( $status['active'] ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Status count query, not suitable for caching
			$count                      = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table name
					self::META_KEY_QUIZ_ID
				)
			);
			$status['attached_quizzes'] = (int) $count;
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'status'  => $status,
			]
		);
	}

	/**
	 * REST endpoint: Save lesson quiz association
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_save_lesson_quiz( $request ) {
		$lesson_id = $request->get_param( 'lesson_id' );
		$quiz_id   = $request->get_param( 'quiz_id' );

		// Handle temporary lesson IDs (lesson-0, lesson-1, etc.).
		$is_temp_id = strpos( $lesson_id, 'lesson-' ) === 0;

		if ( $is_temp_id ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Please save the lesson first before attaching a quiz.', 'pressprimer-quiz' ),
				],
				400
			);
		}

		$lesson_id = absint( $lesson_id );

		// Validate lesson exists.
		$lesson = get_post( $lesson_id );
		if ( ! $lesson || 'lesson' !== $lesson->post_type ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid lesson.', 'pressprimer-quiz' ),
				],
				400
			);
		}

		// Check user can edit this lesson.
		if ( ! PressPrimer_Quiz_Helpers::current_user_can_edit_post( $lesson_id ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Permission denied.', 'pressprimer-quiz' ),
				],
				403
			);
		}

		// Save or remove the quiz association.
		if ( $quiz_id ) {
			// Validate quiz exists.
			$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
			if ( ! $quiz ) {
				return new WP_REST_Response(
					[
						'success' => false,
						'message' => __( 'Quiz not found.', 'pressprimer-quiz' ),
					],
					400
				);
			}

			update_post_meta( $lesson_id, self::META_KEY_QUIZ_ID, $quiz_id );
			// Default to requiring pass when attaching a quiz via REST API.
			update_post_meta( $lesson_id, self::META_KEY_REQUIRE_PASS, '1' );
		} else {
			delete_post_meta( $lesson_id, self::META_KEY_QUIZ_ID );
			delete_post_meta( $lesson_id, self::META_KEY_REQUIRE_PASS );
		}

		return new WP_REST_Response(
			[
				'success'   => true,
				'lesson_id' => $lesson_id,
				'quiz_id'   => $quiz_id,
			]
		);
	}
}
