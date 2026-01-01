<?php
/**
 * LifterLMS Integration
 *
 * Integrates PressPrimer Quiz with LifterLMS for seamless quiz
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
 * LifterLMS Integration Class
 *
 * Handles all LifterLMS integration functionality including:
 * - PPQ Quiz tab in course builder
 * - Quiz display in lesson content
 * - Completion tracking on quiz pass
 * - Access control respecting LifterLMS enrollment
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_LifterLMS {

	/**
	 * Meta key for storing the PPQ quiz ID on lessons
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_QUIZ_ID = '_ppq_lifterlms_quiz_id';

	/**
	 * Meta key for storing require pass setting
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_REQUIRE_PASS = '_ppq_lifterlms_require_pass';

	/**
	 * Track if quiz has been rendered to prevent duplicates
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $quiz_rendered = false;

	/**
	 * Initialize the integration
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Only initialize if LifterLMS is active.
		if ( ! defined( 'LLMS_PLUGIN_FILE' ) ) {
			return;
		}

		// Admin hooks - Meta box for classic editor.
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_box' ], 10, 2 );

		// Course builder hooks - Enqueue assets for sidebar indicators.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_course_builder_assets' ] );

		// Frontend hooks - display quiz inside the lesson button wrapper, before the Mark Complete button.
		// This hook fires for enrolled users when the button wrapper is rendered (classic templates).
		add_action( 'llms_before_lesson_buttons', [ $this, 'display_quiz_in_lesson' ], 10, 2 );

		// Block-based lessons: hook before the navigation block renders.
		// Hook format: {vendor}_{id}_block_render (see LLMS_Blocks_Abstract_Block::get_render_hook).
		add_action( 'llms_lesson-navigation_block_render', [ $this, 'display_quiz_before_navigation_block' ], 5 );

		// Fallback for when neither the progression block nor navigation block exist.
		// Uses the_content filter to append quiz after lesson content.
		add_filter( 'the_content', [ $this, 'append_quiz_to_content' ], 20 );

		// Check if lesson is complete (requires quiz pass).
		add_filter( 'llms_is_complete', [ $this, 'check_ppq_quiz_complete' ], 10, 4 );

		// Hide LifterLMS Mark Complete button when quiz pass is required.
		add_filter( 'llms_show_mark_complete_button', [ $this, 'maybe_hide_complete_button' ], 10, 2 );

		// Completion tracking.
		add_action( 'pressprimer_quiz_quiz_passed', [ $this, 'handle_quiz_passed' ], 10, 2 );

		// Map Instructors to ppq_teacher role.
		add_filter( 'pressprimer_quiz_user_has_teacher_capability', [ $this, 'check_instructor_capability' ], 10, 2 );

		// AJAX handler for metabox quiz search (classic editor).
		add_action( 'wp_ajax_pressprimer_quiz_search_quizzes_lifterlms', [ $this, 'ajax_search_quizzes' ] );

		// Register REST routes.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Register meta boxes for lesson edit screen
	 *
	 * @since 1.0.0
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'ppq_lifterlms_quiz',
			__( 'PressPrimer Quiz', 'pressprimer-quiz' ),
			[ $this, 'render_meta_box' ],
			'lesson',
			'side',
			'default'
		);
	}

	/**
	 * Render the meta box content
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'pressprimer_quiz_lifterlms_meta_box', 'pressprimer_quiz_lifterlms_nonce' );

		$quiz_id      = get_post_meta( $post->ID, self::META_KEY_QUIZ_ID, true );
		$require_pass = get_post_meta( $post->ID, self::META_KEY_REQUIRE_PASS, true );

		// Get quiz display label if one is selected.
		$quiz_display = '';
		if ( $quiz_id ) {
			$quiz         = class_exists( 'PressPrimer_Quiz_Quiz' ) ? PressPrimer_Quiz_Quiz::get( $quiz_id ) : null;
			$quiz_display = $quiz ? sprintf( '%d - %s', $quiz->id, $quiz->title ) : '';
		}
		?>
		<div class="ppq-lifterlms-metabox">
			<p>
				<label for="ppq_lifterlms_quiz_search">
					<?php esc_html_e( 'Select Quiz:', 'pressprimer-quiz' ); ?>
				</label>
			</p>
			<div class="ppq-quiz-selector">
				<input
					type="text"
					id="ppq_lifterlms_quiz_search"
					class="ppq-quiz-search widefat"
					placeholder="<?php esc_attr_e( 'Click to browse or type to search...', 'pressprimer-quiz' ); ?>"
					value="<?php echo esc_attr( $quiz_display ); ?>"
					autocomplete="off"
					<?php echo esc_attr( $quiz_id ? 'readonly' : '' ); ?>
				/>
				<input
					type="hidden"
					id="ppq_lifterlms_quiz_id"
					name="ppq_lifterlms_quiz_id"
					value="<?php echo esc_attr( $quiz_id ); ?>"
				/>
				<div id="ppq_lifterlms_quiz_results" class="ppq-quiz-results" style="display: none;"></div>
				<?php if ( $quiz_id ) : ?>
					<button type="button" class="ppq-remove-quiz button-link" aria-label="<?php esc_attr_e( 'Remove quiz', 'pressprimer-quiz' ); ?>" title="<?php esc_attr_e( 'Remove quiz', 'pressprimer-quiz' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				<?php endif; ?>
			</div>

			<p style="margin-top: 12px;">
				<label>
					<input type="checkbox" name="ppq_lifterlms_require_pass" value="1" <?php checked( $require_pass, '1' ); ?> />
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
			'ppqLifterLMSMetaBox',
			[
				'nonce'   => wp_create_nonce( 'pressprimer_quiz_search_quizzes_lifterlms' ),
				'strings' => [
					'noQuizzesFound' => __( 'No quizzes found', 'pressprimer-quiz' ),
					'removeQuiz'     => __( 'Remove quiz', 'pressprimer-quiz' ),
				],
			]
		);

		// Inline CSS for meta box styling.
		$inline_css = '
			.ppq-lifterlms-metabox .ppq-quiz-selector {
				position: relative;
				display: flex;
				align-items: center;
				gap: 4px;
			}
			.ppq-lifterlms-metabox .ppq-quiz-search {
				flex: 1;
			}
			.ppq-lifterlms-metabox .ppq-quiz-search[readonly] {
				background: #f0f6fc;
				cursor: default;
			}
			.ppq-lifterlms-metabox .ppq-quiz-results {
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
			.ppq-lifterlms-metabox .ppq-quiz-result-item {
				padding: 8px 12px;
				cursor: pointer;
				border-bottom: 1px solid #f0f0f0;
			}
			.ppq-lifterlms-metabox .ppq-quiz-result-item:hover {
				background: #f0f0f0;
			}
			.ppq-lifterlms-metabox .ppq-quiz-result-item:last-child {
				border-bottom: none;
			}
			.ppq-lifterlms-metabox .ppq-remove-quiz {
				color: #d63638;
				text-decoration: none;
				padding: 4px;
				border: none;
				background: none;
				cursor: pointer;
				display: flex;
				align-items: center;
			}
			.ppq-lifterlms-metabox .ppq-remove-quiz:hover {
				color: #b32d2e;
			}
			.ppq-lifterlms-metabox .ppq-no-results {
				padding: 12px;
				color: #666;
				font-style: italic;
			}
			.ppq-lifterlms-metabox .ppq-quiz-result-item .ppq-quiz-id {
				color: #666;
				font-weight: 600;
				margin-right: 4px;
			}
		';
		wp_add_inline_style( 'ppq-admin', $inline_css );

		// Inline JavaScript for meta box functionality.
		$inline_script = <<<'JS'
		jQuery(document).ready(function($) {
			var config = window.ppqLifterLMSMetaBox || {};
			var searchTimeout;
			var $search = $('#ppq_lifterlms_quiz_search');
			var $results = $('#ppq_lifterlms_quiz_results');
			var $quizId = $('#ppq_lifterlms_quiz_id');
			var $removeBtn = $('.ppq-remove-quiz');

			function formatQuiz(quiz) {
				return '<div class="ppq-quiz-result-item" data-id="' + quiz.id + '" data-title="' + $('<div/>').text(quiz.title).html() + '">' +
					'<span class="ppq-quiz-id">' + quiz.id + '</span> - ' + $('<div/>').text(quiz.title).html() +
					'</div>';
			}

			// Load recent quizzes on focus (only if no quiz selected)
			$search.on('focus', function() {
				if ($quizId.val()) {
					return; // Already has a selection
				}

				// Load 50 most recent quizzes
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'pressprimer_quiz_search_quizzes_lifterlms',
						nonce: config.nonce,
						recent: 1
					},
					success: function(response) {
						if (response.success && response.data.quizzes && response.data.quizzes.length > 0) {
							var html = '';
							response.data.quizzes.forEach(function(quiz) {
								html += formatQuiz(quiz);
							});
							$results.html(html).show();
						}
					}
				});
			});

			// Search quizzes on input
			$search.on('input', function() {
				var query = $(this).val();

				clearTimeout(searchTimeout);

				if (query.length < 2) {
					// Show recent quizzes if input is short
					$search.trigger('focus');
					return;
				}

				searchTimeout = setTimeout(function() {
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'pressprimer_quiz_search_quizzes_lifterlms',
							nonce: config.nonce,
							search: query
						},
						success: function(response) {
							if (response.success && response.data.quizzes && response.data.quizzes.length > 0) {
								var html = '';
								response.data.quizzes.forEach(function(quiz) {
									html += formatQuiz(quiz);
								});
								$results.html(html).show();
							} else {
								$results.html('<div class="ppq-no-results">' + config.strings.noQuizzesFound + '</div>').show();
							}
						}
					});
				}, 300);
			});

			// Select quiz
			$results.on('click', '.ppq-quiz-result-item', function() {
				var $item = $(this);
				var id = $item.data('id');
				var title = $item.data('title');
				var display = id + ' - ' + title;

				$quizId.val(id);
				$search.val(display).attr('readonly', true);
				$results.hide();

				// Add remove button if not present
				if (!$removeBtn.length) {
					$search.after('<button type="button" class="ppq-remove-quiz button-link" aria-label="' + config.strings.removeQuiz + '" title="' + config.strings.removeQuiz + '"><span class="dashicons dashicons-no-alt"></span></button>');
					$removeBtn = $('.ppq-remove-quiz');
				}
			});

			// Remove quiz
			$(document).on('click', '.ppq-remove-quiz', function() {
				$quizId.val('');
				$search.val('').removeAttr('readonly');
				$(this).remove();
				$removeBtn = $();
			});

			// Hide results on click outside
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.ppq-quiz-selector').length) {
					$results.hide();
				}
			});
		});
JS;
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
		if ( ! isset( $_POST['pressprimer_quiz_lifterlms_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pressprimer_quiz_lifterlms_nonce'] ) ), 'pressprimer_quiz_lifterlms_meta_box' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check post type.
		if ( 'lesson' !== $post->post_type ) {
			return;
		}

		// Check permissions.
		if ( ! PressPrimer_Quiz_Helpers::current_user_can_edit_post( $post_id ) ) {
			return;
		}

		// Save quiz ID.
		$quiz_id = isset( $_POST['ppq_lifterlms_quiz_id'] ) ? absint( wp_unslash( $_POST['ppq_lifterlms_quiz_id'] ) ) : 0;
		if ( $quiz_id ) {
			update_post_meta( $post_id, self::META_KEY_QUIZ_ID, $quiz_id );
		} else {
			delete_post_meta( $post_id, self::META_KEY_QUIZ_ID );
		}

		// Save require pass setting.
		$require_pass = isset( $_POST['ppq_lifterlms_require_pass'] ) ? '1' : '';
		if ( $require_pass ) {
			update_post_meta( $post_id, self::META_KEY_REQUIRE_PASS, $require_pass );
		} else {
			delete_post_meta( $post_id, self::META_KEY_REQUIRE_PASS );
		}
	}

	/**
	 * Enqueue course builder assets
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_course_builder_assets( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'llms-course-builder' !== $page ) {
			return;
		}

		// Get current course ID.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$course_id = isset( $_GET['course_id'] ) ? absint( wp_unslash( $_GET['course_id'] ) ) : 0;

		// Get lesson quiz mappings for this course (for sidebar indicators).
		$lesson_quizzes = $this->get_lesson_quizzes_for_course( $course_id );

		wp_enqueue_script(
			'ppq-lifterlms-course-builder',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/js/lifterlms-course-builder.js',
			[ 'llms-builder' ],
			PRESSPRIMER_QUIZ_VERSION,
			true
		);

		wp_localize_script(
			'ppq-lifterlms-course-builder',
			'pressprimerQuizLifterLMS',
			[
				'courseId'      => $course_id,
				'lessonQuizzes' => $lesson_quizzes,
			]
		);

		wp_enqueue_style(
			'ppq-lifterlms-course-builder',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/css/lifterlms-course-builder.css',
			[],
			PRESSPRIMER_QUIZ_VERSION
		);
	}

	/**
	 * Get lesson quizzes for a course
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

		$quizzes = [];

		// Get all lessons for this course.
		$course  = new LLMS_Course( $course_id );
		$lessons = $course->get_lessons( 'ids' );

		foreach ( $lessons as $lesson_id ) {
			$quiz_id = get_post_meta( $lesson_id, self::META_KEY_QUIZ_ID, true );
			if ( $quiz_id ) {
				$quiz = class_exists( 'PressPrimer_Quiz_Quiz' ) ? PressPrimer_Quiz_Quiz::get( $quiz_id ) : null;
				if ( $quiz ) {
					$require_pass          = get_post_meta( $lesson_id, self::META_KEY_REQUIRE_PASS, true );
					$quizzes[ $lesson_id ] = [
						'id'          => $quiz_id,
						'title'       => $quiz->title,
						'requirePass' => $require_pass,
					];
				}
			}
		}

		return $quizzes;
	}

	/**
	 * Display quiz in lesson content
	 *
	 * Called from llms_before_lesson_buttons hook which fires inside the
	 * .llms-lesson-button-wrapper div, before the Mark Complete button.
	 * Enrollment is already verified by LifterLMS before this hook fires.
	 *
	 * @since 1.0.0
	 *
	 * @param LLMS_Lesson  $lesson  The lesson object.
	 * @param LLMS_Student $student The student object.
	 */
	public function display_quiz_in_lesson( $lesson, $student ) {
		$lesson_id = $lesson->get( 'id' );
		$quiz_id   = get_post_meta( $lesson_id, self::META_KEY_QUIZ_ID, true );

		if ( ! $quiz_id ) {
			return;
		}

		// Mark as rendered to prevent duplicate from content filter.
		$this->quiz_rendered = true;

		$course = $lesson->get_course();

		// Build context data for the quiz.
		$context_data = array(
			'context'      => 'lifterlms',
			'context_id'   => $lesson_id,
			'context_type' => 'lesson',
			'course_id'    => $course ? $course->get( 'id' ) : 0,
		);

		// Render the quiz using shortcode.
		$quiz_shortcode = sprintf(
			'[pressprimer_quiz id="%d" context="%s"]',
			absint( $quiz_id ),
			esc_attr( base64_encode( wp_json_encode( $context_data ) ) )
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is escaped internally
		echo do_shortcode( $quiz_shortcode );
	}

	/**
	 * Display quiz before the navigation block in block-based lessons
	 *
	 * This hooks into the navigation block render action to display the quiz
	 * right before the navigation when the Mark Complete block is removed.
	 *
	 * @since 1.0.0
	 */
	public function display_quiz_before_navigation_block() {
		// Don't render if already rendered by the primary hook.
		if ( $this->quiz_rendered ) {
			return;
		}

		global $post;

		if ( ! $post || 'lesson' !== $post->post_type ) {
			return;
		}

		$quiz_id = get_post_meta( $post->ID, self::META_KEY_QUIZ_ID, true );

		if ( ! $quiz_id ) {
			return;
		}

		$lesson  = llms_get_post( $post );
		$user_id = get_current_user_id();

		if ( ! $lesson || ! is_a( $lesson, 'LLMS_Lesson' ) ) {
			return;
		}

		$parent_course = $lesson->get( 'parent_course' );

		// Check enrollment.
		if ( ! llms_is_user_enrolled( $user_id, $parent_course ) && ! PressPrimer_Quiz_Helpers::current_user_can_edit_post( $lesson->get( 'id' ) ) ) {
			echo '<div class="ppq-access-denied">';
			esc_html_e( 'Enroll in this course to access the quiz.', 'pressprimer-quiz' );
			echo '</div>';
			return;
		}

		// Mark as rendered.
		$this->quiz_rendered = true;

		$course = $lesson->get_course();

		// Build context data for the quiz.
		$context_data = array(
			'context'      => 'lifterlms',
			'context_id'   => $post->ID,
			'context_type' => 'lesson',
			'course_id'    => $course ? $course->get( 'id' ) : 0,
		);

		// Render the quiz using shortcode.
		$quiz_shortcode = sprintf(
			'[pressprimer_quiz id="%d" context="%s"]',
			absint( $quiz_id ),
			esc_attr( base64_encode( wp_json_encode( $context_data ) ) )
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is escaped internally
		echo do_shortcode( $quiz_shortcode );
	}

	/**
	 * Append quiz to lesson content for block-based lessons
	 *
	 * This handles cases where neither the progression block nor navigation block exist,
	 * as a final fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The post content.
	 * @return string Modified content with quiz appended.
	 */
	public function append_quiz_to_content( $content ) {
		// Only run on single lesson pages in the main query.
		if ( ! is_singular( 'lesson' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		// Don't render if already rendered by the primary hook.
		if ( $this->quiz_rendered ) {
			return $content;
		}

		global $post;

		// If the navigation block exists, let the block hook handle rendering instead.
		// Check raw post content for the block.
		if ( has_block( 'llms/lesson-navigation', $post ) ) {
			return $content;
		}

		$quiz_id = get_post_meta( $post->ID, self::META_KEY_QUIZ_ID, true );

		if ( ! $quiz_id ) {
			return $content;
		}

		$lesson  = llms_get_post( $post );
		$user_id = get_current_user_id();

		if ( ! $lesson || ! is_a( $lesson, 'LLMS_Lesson' ) ) {
			return $content;
		}

		$parent_course = $lesson->get( 'parent_course' );

		// Check enrollment.
		if ( ! llms_is_user_enrolled( $user_id, $parent_course ) && ! PressPrimer_Quiz_Helpers::current_user_can_edit_post( $lesson->get( 'id' ) ) ) {
			$content .= '<div class="ppq-access-denied">';
			$content .= esc_html__( 'Enroll in this course to access the quiz.', 'pressprimer-quiz' );
			$content .= '</div>';
			return $content;
		}

		// Mark as rendered.
		$this->quiz_rendered = true;

		$course = $lesson->get_course();

		// Build context data for the quiz.
		$context_data = array(
			'context'      => 'lifterlms',
			'context_id'   => $post->ID,
			'context_type' => 'lesson',
			'course_id'    => $course ? $course->get( 'id' ) : 0,
		);

		// Render the quiz using shortcode.
		$quiz_shortcode = sprintf(
			'[pressprimer_quiz id="%d" context="%s"]',
			absint( $quiz_id ),
			esc_attr( base64_encode( wp_json_encode( $context_data ) ) )
		);

		$content .= do_shortcode( $quiz_shortcode );

		return $content;
	}

	/**
	 * Check if PPQ quiz completion should affect lesson completion
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $is_complete Whether the item is complete.
	 * @param int    $user_id     User ID.
	 * @param int    $object_id   Object ID (lesson ID).
	 * @param string $type        Type of completion check.
	 * @return bool Modified completion status.
	 */
	public function check_ppq_quiz_complete( $is_complete, $user_id, $object_id, $type ) {
		if ( 'lesson' !== $type ) {
			return $is_complete;
		}

		$quiz_id      = get_post_meta( $object_id, self::META_KEY_QUIZ_ID, true );
		$require_pass = get_post_meta( $object_id, self::META_KEY_REQUIRE_PASS, true );

		// If no quiz attached or pass not required, use default.
		if ( ! $quiz_id || '1' !== $require_pass ) {
			return $is_complete;
		}

		// Check if user has passed the PPQ quiz.
		if ( class_exists( 'PressPrimer_Quiz_Attempt' ) ) {
			$passed = PressPrimer_Quiz_Attempt::user_has_passed( $quiz_id, $user_id );
			return $passed ? $is_complete : false;
		}

		return $is_complete;
	}

	/**
	 * Maybe hide the Mark Complete button
	 *
	 * Hides the button when a PPQ quiz is attached and "Require passing score" is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param bool        $show   Whether to show the button.
	 * @param LLMS_Lesson $lesson The lesson object.
	 * @return bool Whether to show the button.
	 */
	public function maybe_hide_complete_button( $show, $lesson ) {
		if ( ! $show || ! $lesson ) {
			return $show;
		}

		$lesson_id = $lesson->get( 'id' );
		$quiz_id   = get_post_meta( $lesson_id, self::META_KEY_QUIZ_ID, true );

		if ( $quiz_id ) {
			// Only hide the button if "Require passing score" is enabled.
			$require_pass = get_post_meta( $lesson_id, self::META_KEY_REQUIRE_PASS, true );

			if ( '1' === $require_pass ) {
				return false;
			}
		}

		return $show;
	}

	/**
	 * Handle quiz passed event
	 *
	 * @since 1.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt The submitted attempt object.
	 * @param PressPrimer_Quiz_Quiz    $quiz    The quiz object.
	 */
	public function handle_quiz_passed( $attempt, $quiz ) {
		global $wpdb;

		$quiz_id = $quiz->id;
		$user_id = $attempt->user_id;

		// Find lessons using this quiz.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lesson lookup by quiz ID, not suitable for caching
		$lessons = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = %s AND meta_value = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WordPress core table name
				self::META_KEY_QUIZ_ID,
				$quiz_id
			)
		);

		foreach ( $lessons as $lesson_id ) {
			$require_pass = get_post_meta( $lesson_id, self::META_KEY_REQUIRE_PASS, true );

			if ( '1' === $require_pass ) {
				// Mark LifterLMS lesson as complete.
				$this->mark_lesson_complete( $lesson_id, $user_id );
			}
		}
	}

	/**
	 * Mark a LifterLMS lesson as complete
	 *
	 * @since 1.0.0
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $user_id   User ID.
	 */
	private function mark_lesson_complete( $lesson_id, $user_id ) {
		if ( ! function_exists( 'llms_mark_complete' ) ) {
			return;
		}

		// Check if already complete.
		if ( llms_is_complete( $user_id, $lesson_id, 'lesson' ) ) {
			return;
		}

		// Mark as complete.
		llms_mark_complete( $user_id, $lesson_id, 'lesson' );

		/**
		 * Fires after a LifterLMS lesson is marked complete due to PPQ quiz pass.
		 *
		 * @since 1.0.0
		 *
		 * @param int $lesson_id Lesson ID.
		 * @param int $user_id   User ID.
		 */
		do_action( 'pressprimer_quiz_lifterlms_lesson_completed', $lesson_id, $user_id );
	}

	/**
	 * Check if user is a LifterLMS Instructor
	 *
	 * @since 1.0.0
	 *
	 * @param bool $has_capability Whether user has teacher capability.
	 * @param int  $user_id        User ID.
	 * @return bool Modified capability.
	 */
	public function check_instructor_capability( $has_capability, $user_id ) {
		if ( $has_capability ) {
			return $has_capability;
		}

		// Check if user is a LifterLMS Instructor.
		$user = get_userdata( $user_id );
		if ( $user && in_array( 'llms_instructor', (array) $user->roles, true ) ) {
			return true;
		}

		return $has_capability;
	}

	/**
	 * Register REST API routes
	 *
	 * @since 1.0.0
	 */
	public function register_rest_routes() {
		// Status endpoint.
		register_rest_route(
			'ppq/v1',
			'/lifterlms/status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_get_status' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);

		// Quiz search endpoint.
		register_rest_route(
			'ppq/v1',
			'/lifterlms/quizzes/search',
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

		// Save lesson quiz endpoint.
		register_rest_route(
			'ppq/v1',
			'/lifterlms/lesson-quiz',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_save_lesson_quiz' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => [
					'lesson_id'    => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'quiz_id'      => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'require_pass' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
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
		$search = $request->get_param( 'search' );
		$recent = $request->get_param( 'recent' );

		$quizzes = [];

		if ( class_exists( 'PressPrimer_Quiz_Quiz' ) ) {
			$args = [
				'where'    => [
					'status' => 'published',
				],
				'limit'    => 10,
				'order_by' => 'id',
				'order'    => 'DESC',
			];

			$results = PressPrimer_Quiz_Quiz::find( $args );

			// Filter by search term if provided.
			if ( $search ) {
				$search_lower = strtolower( $search );
				$results      = array_filter(
					$results,
					function ( $quiz ) use ( $search_lower ) {
						return strpos( strtolower( $quiz->title ), $search_lower ) !== false
						|| strpos( (string) $quiz->id, $search_lower ) !== false;
					}
				);
			}

			foreach ( $results as $quiz ) {
				$quizzes[] = [
					'id'    => $quiz->id,
					'title' => $quiz->title,
				];
			}
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'quizzes' => $quizzes,
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
		$lesson_id    = $request->get_param( 'lesson_id' );
		$quiz_id      = $request->get_param( 'quiz_id' );
		$require_pass = $request->get_param( 'require_pass' );

		// Validate lesson exists and is a lesson.
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

		// Check permissions.
		if ( ! PressPrimer_Quiz_Helpers::current_user_can_edit_post( $lesson_id ) ) {
			return new WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Permission denied.', 'pressprimer-quiz' ),
				],
				403
			);
		}

		// Save or remove quiz association.
		if ( $quiz_id ) {
			// Validate quiz exists.
			if ( class_exists( 'PressPrimer_Quiz_Quiz' ) ) {
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
			}

			update_post_meta( $lesson_id, self::META_KEY_QUIZ_ID, $quiz_id );

			// Save require pass if provided.
			if ( $require_pass === '1' ) {
				update_post_meta( $lesson_id, self::META_KEY_REQUIRE_PASS, '1' );
			} else {
				delete_post_meta( $lesson_id, self::META_KEY_REQUIRE_PASS );
			}
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

	/**
	 * AJAX handler for quiz search in metabox
	 *
	 * @since 1.0.0
	 */
	public function ajax_search_quizzes() {
		check_ajax_referer( 'pressprimer_quiz_search_quizzes_lifterlms', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ] );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ppq_quizzes';

		// Check if requesting recent quizzes.
		$recent = isset( $_POST['recent'] ) && rest_sanitize_boolean( wp_unslash( $_POST['recent'] ) );

		if ( $recent ) {
			// Get 50 most recent quizzes created by current user.
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
			return;
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
	 * REST endpoint: Get integration status
	 *
	 * Returns information about the LifterLMS integration status.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_status( $request ) {
		$status = [
			'active'      => defined( 'LLMS_PLUGIN_FILE' ),
			'version'     => defined( 'LLMS_VERSION' ) ? LLMS_VERSION : null,
			'integration' => 'working',
		];

		// Count how many LifterLMS lessons have PPQ quizzes attached.
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
}
