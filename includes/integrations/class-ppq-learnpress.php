<?php
/**
 * LearnPress Integration
 *
 * Integrates PressPrimer Quiz with LearnPress LMS.
 * Adds PPQ quiz support to LearnPress lessons with optional completion tracking.
 *
 * @package PressPrimer_Quiz
 * @subpackage Integrations
 * @since 2.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LearnPress Integration Class
 *
 * Handles all LearnPress integration functionality including:
 * - Settings in LearnPress lesson settings meta box
 * - Quiz display in lesson content
 * - Completion tracking on quiz pass
 * - Access control respecting LearnPress enrollment
 *
 * @since 2.0.0
 */
class PressPrimer_Quiz_LearnPress {

	/**
	 * Minimum LearnPress version required
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const MIN_VERSION = '4.0.0';

	/**
	 * Meta key for storing the PPQ quiz ID on lessons
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const META_KEY_QUIZ_ID = '_ppq_quiz_id';

	/**
	 * Meta key for storing require pass setting
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const META_KEY_REQUIRE_PASS = '_ppq_require_pass';

	/**
	 * Initialize the integration
	 *
	 * @since 2.0.0
	 */
	public function init() {
		// Only initialize if LearnPress is active and meets version requirement.
		if ( ! $this->is_learnpress_active() ) {
			return;
		}

		// Admin hooks - inject into LP's native lesson settings panel.
		add_action( 'learnpress/lesson-settings/after', array( $this, 'render_lesson_settings' ) );
		add_action( 'save_post_lp_lesson', array( $this, 'save_lesson_meta' ), 10, 2 );

		// AJAX handler for classic editor quiz search.
		add_action( 'wp_ajax_pressprimer_quiz_search_quizzes_learnpress', array( $this, 'ajax_search_quizzes' ) );

		// Register meta fields for REST API / Gutenberg support.
		add_action( 'init', array( $this, 'register_meta_fields' ) );

		// Frontend hooks - add quiz after lesson content, before materials (priority 9).
		add_action( 'learn-press/after-content-item-summary/lp_lesson', array( $this, 'render_quiz_in_lesson' ), 9 );

		// Hide LearnPress complete button when quiz with require_pass is attached (runs before LP's button at priority 11).
		add_action( 'learn-press/after-content-item-summary/lp_lesson', array( $this, 'maybe_hide_complete_button' ), 10 );

		// Quiz completion hooks.
		add_action( 'pressprimer_quiz_attempt_submitted', array( $this, 'handle_quiz_completion' ), 10, 2 );
		add_action( 'pressprimer_quiz_quiz_passed', array( $this, 'handle_quiz_passed' ), 10, 2 );

		// REST API endpoints.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Enqueue frontend styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Check if LearnPress is active and meets version requirement
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if LearnPress is active and compatible.
	 */
	public function is_learnpress_active() {
		if ( ! defined( 'LEARNPRESS_VERSION' ) ) {
			return false;
		}

		return version_compare( LEARNPRESS_VERSION, self::MIN_VERSION, '>=' );
	}

	/**
	 * Render PPQ settings in LearnPress lesson settings meta box
	 *
	 * Hooks into: learnpress/lesson-settings/after
	 * This integrates with LP's native meta box rather than adding a separate one.
	 *
	 * @since 2.0.0
	 */
	public function render_lesson_settings() {
		global $post;

		if ( ! $post || ! defined( 'LP_LESSON_CPT' ) || LP_LESSON_CPT !== $post->post_type ) {
			return;
		}

		wp_nonce_field( 'ppq_learnpress_meta', 'ppq_learnpress_nonce' );

		$quiz_id      = get_post_meta( $post->ID, self::META_KEY_QUIZ_ID, true );
		$require_pass = get_post_meta( $post->ID, self::META_KEY_REQUIRE_PASS, true );

		// Get quiz display label if one is selected.
		$quiz_display = '';
		if ( $quiz_id ) {
			$quiz         = PressPrimer_Quiz_Quiz::get( $quiz_id );
			$quiz_display = $quiz ? sprintf( '%d - %s', $quiz->id, $quiz->title ) : '';
		}

		?>
		<div class="lp-meta-box__field ppq-learnpress-settings">
			<h4 style="margin: 20px 0 10px; padding-top: 15px; border-top: 1px solid #ddd;">
				<?php esc_html_e( 'PressPrimer Quiz', 'pressprimer-quiz' ); ?>
			</h4>

			<div class="lp-meta-box__field-input ppq-quiz-selector-wrapper" style="margin-bottom: 15px;">
				<label for="ppq_quiz_search" style="display: block; margin-bottom: 5px; font-weight: 600;">
					<?php esc_html_e( 'Embed Quiz', 'pressprimer-quiz' ); ?>
				</label>
				<div class="ppq-quiz-selector" style="position: relative; display: flex; align-items: center; gap: 4px;">
					<input
						type="text"
						id="ppq_quiz_search"
						class="ppq-quiz-search"
						placeholder="<?php esc_attr_e( 'Click to browse or type to search...', 'pressprimer-quiz' ); ?>"
						value="<?php echo esc_attr( $quiz_display ); ?>"
						autocomplete="off"
						style="width: 100%; max-width: 400px; <?php echo $quiz_id ? 'background: #f0f6fc; cursor: default;' : ''; ?>"
						<?php echo $quiz_id ? 'readonly' : ''; ?>
					/>
					<input
						type="hidden"
						id="ppq_quiz_id"
						name="ppq_quiz_id"
						value="<?php echo esc_attr( $quiz_id ); ?>"
					/>
					<div id="ppq_quiz_results" class="ppq-quiz-results" style="display: none; position: absolute; top: 100%; left: 0; right: 30px; background: #fff; border: 1px solid #ddd; border-top: none; max-height: 250px; overflow-y: auto; z-index: 1000; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
					<?php if ( $quiz_id ) : ?>
						<button type="button" class="ppq-remove-quiz button-link" aria-label="<?php esc_attr_e( 'Remove quiz', 'pressprimer-quiz' ); ?>" title="<?php esc_attr_e( 'Remove quiz', 'pressprimer-quiz' ); ?>" style="color: #d63638; text-decoration: none; padding: 4px; border: none; background: none; cursor: pointer; display: flex; align-items: center;">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					<?php endif; ?>
				</div>
			</div>

			<div class="lp-meta-box__field-input">
				<label style="display: flex; align-items: center; gap: 8px;">
					<input type="checkbox" name="ppq_require_pass" value="1" <?php checked( $require_pass, '1' ); ?> />
					<?php esc_html_e( 'Require quiz pass to complete lesson', 'pressprimer-quiz' ); ?>
				</label>
			</div>
		</div>

		<?php // Inline styles for quiz results dropdown. ?>
		<style>
			.ppq-learnpress-settings .ppq-quiz-result-item {
				padding: 8px 12px;
				cursor: pointer;
				border-bottom: 1px solid #f0f0f0;
			}
			.ppq-learnpress-settings .ppq-quiz-result-item:hover {
				background: #f0f0f0;
			}
			.ppq-learnpress-settings .ppq-quiz-result-item:last-child {
				border-bottom: none;
			}
			.ppq-learnpress-settings .ppq-remove-quiz:hover {
				color: #b32d2e;
			}
			.ppq-learnpress-settings .ppq-no-results {
				padding: 12px;
				color: #666;
				font-style: italic;
			}
			.ppq-learnpress-settings .ppq-quiz-result-item .ppq-quiz-id {
				color: #666;
				font-weight: 600;
				margin-right: 4px;
			}
		</style>

		<script>
		jQuery(document).ready(function($) {
			var searchTimeout;
			var $search = $('#ppq_quiz_search');
			var $results = $('#ppq_quiz_results');
			var $quizId = $('#ppq_quiz_id');
			var $removeBtn = $('.ppq-remove-quiz');

			// Format quiz display.
			function formatQuiz(quiz) {
				return '<div class="ppq-quiz-result-item" data-id="' + quiz.id + '" data-title="' + $('<div/>').text(quiz.title).html() + '">' +
					'<span class="ppq-quiz-id">' + quiz.id + '</span> - ' + $('<div/>').text(quiz.title).html() +
					'</div>';
			}

			// Load recent quizzes on focus (only if no quiz selected).
			$search.on('focus', function() {
				if ($quizId.val()) {
					return; // Already has a selection.
				}

				// Load 50 most recent quizzes.
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'pressprimer_quiz_search_quizzes_learnpress',
						nonce: '<?php echo esc_js( wp_create_nonce( 'ppq_learnpress_search' ) ); ?>',
						recent: 1
					},
					success: function(response) {
						if (response.success && response.data.quizzes.length > 0) {
							var html = '';
							response.data.quizzes.forEach(function(quiz) {
								html += formatQuiz(quiz);
							});
							$results.html(html).show();
						}
					}
				});
			});

			// Search quizzes on input.
			$search.on('input', function() {
				var query = $(this).val();

				clearTimeout(searchTimeout);

				if (query.length < 2) {
					// Show recent quizzes if input is short.
					$search.trigger('focus');
					return;
				}

				searchTimeout = setTimeout(function() {
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'pressprimer_quiz_search_quizzes_learnpress',
							nonce: '<?php echo esc_js( wp_create_nonce( 'ppq_learnpress_search' ) ); ?>',
							search: query
						},
						success: function(response) {
							if (response.success && response.data.quizzes.length > 0) {
								var html = '';
								response.data.quizzes.forEach(function(quiz) {
									html += formatQuiz(quiz);
								});
								$results.html(html).show();
							} else {
								$results.html('<div class="ppq-no-results"><?php echo esc_js( __( 'No quizzes found', 'pressprimer-quiz' ) ); ?></div>').show();
							}
						}
					});
				}, 300);
			});

			// Select quiz.
			$results.on('click', '.ppq-quiz-result-item', function() {
				var $item = $(this);
				var id = $item.data('id');
				var title = $item.data('title');
				var display = id + ' - ' + title;

				$quizId.val(id);
				$search.val(display).attr('readonly', true).css({'background': '#f0f6fc', 'cursor': 'default'});
				$results.hide();

				// Add remove button if not present.
				if (!$removeBtn.length) {
					$search.parent().append('<button type="button" class="ppq-remove-quiz button-link" aria-label="<?php echo esc_attr__( 'Remove quiz', 'pressprimer-quiz' ); ?>" title="<?php echo esc_attr__( 'Remove quiz', 'pressprimer-quiz' ); ?>" style="color: #d63638; text-decoration: none; padding: 4px; border: none; background: none; cursor: pointer; display: flex; align-items: center;"><span class="dashicons dashicons-no-alt"></span></button>');
					$removeBtn = $('.ppq-remove-quiz');
				}
			});

			// Remove quiz.
			$(document).on('click', '.ppq-remove-quiz', function() {
				$quizId.val('');
				$search.val('').removeAttr('readonly').css({'background': '', 'cursor': ''});
				$(this).remove();
				$removeBtn = $();
			});

			// Hide results on blur.
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.ppq-quiz-selector').length) {
					$results.hide();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Save lesson meta
	 *
	 * @since 2.0.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_lesson_meta( $post_id, $post ) {
		// Verify nonce.
		if ( ! isset( $_POST['ppq_learnpress_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ppq_learnpress_nonce'] ) ), 'ppq_learnpress_meta' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save quiz ID.
		if ( isset( $_POST['ppq_quiz_id'] ) ) {
			$quiz_id = absint( wp_unslash( $_POST['ppq_quiz_id'] ) );
			if ( $quiz_id > 0 ) {
				update_post_meta( $post_id, self::META_KEY_QUIZ_ID, $quiz_id );
			} else {
				delete_post_meta( $post_id, self::META_KEY_QUIZ_ID );
			}
		}

		// Save require pass.
		$require_pass = isset( $_POST['ppq_require_pass'] ) ? '1' : '0';
		update_post_meta( $post_id, self::META_KEY_REQUIRE_PASS, $require_pass );
	}

	/**
	 * Register meta fields for REST API / Gutenberg
	 *
	 * @since 2.0.0
	 */
	public function register_meta_fields() {
		if ( ! defined( 'LP_LESSON_CPT' ) ) {
			return;
		}

		register_post_meta(
			LP_LESSON_CPT,
			self::META_KEY_QUIZ_ID,
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			LP_LESSON_CPT,
			self::META_KEY_REQUIRE_PASS,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * AJAX handler for searching quizzes
	 *
	 * @since 2.0.0
	 */
	public function ajax_search_quizzes() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'ppq_learnpress_search', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ppq_quizzes';

		// Check if requesting recent quizzes.
		$recent = isset( $_POST['recent'] ) && rest_sanitize_boolean( wp_unslash( $_POST['recent'] ) );

		if ( $recent ) {
			// Get 50 most recent published quizzes.
			$user_id = get_current_user_id();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- AJAX search results, not suitable for caching
			$quizzes = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, title FROM {$table} WHERE status = 'published' AND owner_id = %d ORDER BY id DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id
				)
			);

			wp_send_json_success( array( 'quizzes' => $quizzes ) );
			return;
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( array( 'quizzes' => array() ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- AJAX search results, not suitable for caching
		$quizzes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title FROM {$table} WHERE title LIKE %s AND status = 'published' ORDER BY title ASC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%' . $wpdb->esc_like( $search ) . '%'
			)
		);

		wp_send_json_success( array( 'quizzes' => $quizzes ) );
	}

	/**
	 * Render quiz in lesson frontend
	 *
	 * Hooks into: learn-press/after-content-item-summary/lp_lesson at priority 9
	 * This places the quiz after lesson content but before:
	 * - Materials (priority 10)
	 * - Complete button (priority 11)
	 * - Finish course button (priority 15)
	 *
	 * @since 2.0.0
	 */
	public function render_quiz_in_lesson() {
		// Get current lesson from LearnPress global.
		if ( ! class_exists( 'LP_Global' ) ) {
			return;
		}

		$lesson = LP_Global::course_item();

		if ( ! $lesson || ! is_a( $lesson, 'LP_Lesson' ) ) {
			return;
		}

		$lesson_id = $lesson->get_id();
		$quiz_id   = get_post_meta( $lesson_id, self::META_KEY_QUIZ_ID, true );

		if ( empty( $quiz_id ) ) {
			return;
		}

		// Get course from LearnPress global.
		$course    = function_exists( 'learn_press_get_course' ) ? learn_press_get_course() : null;
		$course_id = $course ? $course->get_id() : 0;

		// Check if user is enrolled (if course exists).
		if ( $course_id && ! $this->is_user_enrolled( $course_id ) ) {
			return;
		}

		// Build context data for navigation.
		$context_data = array(
			'learnpress_lesson_id' => $lesson_id,
			'learnpress_course_id' => $course_id,
		);

		// Render the quiz via shortcode.
		$quiz_shortcode = sprintf(
			'[pressprimer_quiz id="%d" context="%s"]',
			absint( $quiz_id ),
			esc_attr( base64_encode( wp_json_encode( $context_data ) ) )
		);

		// Hide the "lesson content is empty" notice when we have a quiz.
		echo '<style>.learn-press-message.notice { display: none; }</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe CSS

		// Output with wrapper.
		echo '<div class="ppq-learnpress-quiz" data-lesson-id="' . esc_attr( $lesson_id ) . '" data-course-id="' . esc_attr( $course_id ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML
		echo do_shortcode( $quiz_shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output
		echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML
	}

	/**
	 * Maybe hide the LearnPress complete button
	 *
	 * When a quiz is attached with require_pass enabled, we remove the LearnPress
	 * complete button so the only way to complete the lesson is by passing the quiz.
	 *
	 * This runs at priority 10, just before LearnPress adds the button at priority 11.
	 *
	 * @since 2.0.0
	 */
	public function maybe_hide_complete_button() {
		// Get current lesson from LearnPress global.
		if ( ! class_exists( 'LP_Global' ) ) {
			return;
		}

		$lesson = LP_Global::course_item();

		if ( ! $lesson || ! is_a( $lesson, 'LP_Lesson' ) ) {
			return;
		}

		$lesson_id    = $lesson->get_id();
		$quiz_id      = get_post_meta( $lesson_id, self::META_KEY_QUIZ_ID, true );
		$require_pass = get_post_meta( $lesson_id, self::META_KEY_REQUIRE_PASS, true );

		// Only hide if quiz is attached AND require_pass is enabled.
		if ( ! empty( $quiz_id ) && '1' === $require_pass ) {
			// Remove LearnPress complete button (added at priority 11).
			$lp_template = LearnPress::instance()->template( 'course' );
			if ( $lp_template ) {
				remove_action(
					'learn-press/after-content-item-summary/lp_lesson',
					$lp_template->func( 'item_lesson_complete_button' ),
					11
				);
			}
		}
	}

	/**
	 * Handle quiz attempt submission
	 *
	 * Called on pressprimer_quiz_attempt_submitted hook.
	 * This runs for all quiz submissions (pass or fail) when require_pass is disabled.
	 *
	 * @since 2.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Quiz attempt.
	 * @param array                    $data    Submission data.
	 */
	public function handle_quiz_completion( $attempt, $data ) {
		// Find the lesson with this quiz.
		$lesson_id = $this->find_lesson_by_quiz( $attempt->quiz_id );
		if ( ! $lesson_id ) {
			return;
		}

		// Check if pass is required.
		$require_pass = get_post_meta( $lesson_id, self::META_KEY_REQUIRE_PASS, true );
		if ( '1' === $require_pass ) {
			// Let handle_quiz_passed handle this case.
			return;
		}

		// No pass required, mark as complete on any submission.
		$course_id = $this->get_lesson_course_id( $lesson_id );
		if ( $course_id && $attempt->user_id ) {
			$this->complete_lesson( $lesson_id, $course_id, $attempt->user_id );
		}
	}

	/**
	 * Handle quiz passed event
	 *
	 * Called on pressprimer_quiz_quiz_passed hook.
	 * Marks the lesson complete when the quiz is passed.
	 *
	 * @since 2.0.0
	 *
	 * @param PressPrimer_Quiz_Attempt $attempt Attempt object.
	 * @param PressPrimer_Quiz_Quiz    $quiz    Quiz object.
	 */
	public function handle_quiz_passed( $attempt, $quiz ) {
		// Only for logged-in users.
		if ( ! $attempt->user_id ) {
			return;
		}

		// Find the lesson with this quiz.
		$lesson_id = $this->find_lesson_by_quiz( $quiz->id );
		if ( ! $lesson_id ) {
			return;
		}

		// Check if pass is required.
		$require_pass = get_post_meta( $lesson_id, self::META_KEY_REQUIRE_PASS, true );
		if ( '1' !== $require_pass ) {
			// Already handled by handle_quiz_completion.
			return;
		}

		// Use quiz's pass status (determined by quiz's passing score).
		if ( $attempt->passed ) {
			$course_id = $this->get_lesson_course_id( $lesson_id );
			if ( $course_id ) {
				$this->complete_lesson( $lesson_id, $course_id, $attempt->user_id );
			}
		}
	}

	/**
	 * Mark a LearnPress lesson as complete
	 *
	 * Uses LearnPress API: $user->complete_lesson($lesson_id, $course_id)
	 *
	 * @since 2.0.0
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @param int $course_id Course post ID.
	 * @param int $user_id   User ID.
	 */
	private function complete_lesson( $lesson_id, $course_id, $user_id ) {
		if ( ! $user_id || ! $course_id ) {
			return;
		}

		// Use LearnPress API to complete the lesson.
		if ( function_exists( 'learn_press_get_user' ) ) {
			$user = learn_press_get_user( $user_id );
			if ( $user && method_exists( $user, 'complete_lesson' ) ) {
				$result = $user->complete_lesson( $lesson_id, $course_id );

				// Log any errors for debugging.
				if ( is_wp_error( $result ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging
					error_log( 'PPQ LearnPress: Failed to complete lesson - ' . $result->get_error_message() );
				} else {
					/**
					 * Fires after PPQ marks a LearnPress lesson complete.
					 *
					 * @since 2.0.0
					 *
					 * @param int $lesson_id Lesson post ID.
					 * @param int $course_id Course post ID.
					 * @param int $user_id   User ID.
					 */
					do_action( 'pressprimer_quiz_learnpress_lesson_completed', $lesson_id, $course_id, $user_id );
				}
			}
		}
	}

	/**
	 * Get course ID for a lesson
	 *
	 * LearnPress stores lesson-course relationships in:
	 * - {prefix}learnpress_sections (section_id, section_course_id)
	 * - {prefix}learnpress_section_items (section_id, item_id)
	 *
	 * @since 2.0.0
	 *
	 * @param int $lesson_id Lesson post ID.
	 * @return int|null Course post ID or null.
	 */
	private function get_lesson_course_id( $lesson_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic lesson lookup, not suitable for caching
		$course_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT s.section_course_id
				 FROM {$wpdb->prefix}learnpress_section_items AS si
				 INNER JOIN {$wpdb->prefix}learnpress_sections AS s ON si.section_id = s.section_id
				 WHERE si.item_id = %d
				 LIMIT 1",
				$lesson_id
			)
		);

		return $course_id ? absint( $course_id ) : null;
	}

	/**
	 * Check if user is enrolled in a course
	 *
	 * Uses LearnPress API: $user->has_enrolled_course($course_id)
	 *
	 * @since 2.0.0
	 *
	 * @param int $course_id Course ID.
	 * @return bool True if enrolled or if enrollment can't be checked.
	 */
	private function is_user_enrolled( $course_id ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		if ( function_exists( 'learn_press_get_user' ) ) {
			$user = learn_press_get_user( $user_id );
			if ( $user && method_exists( $user, 'has_enrolled_course' ) ) {
				return (bool) $user->has_enrolled_course( $course_id );
			}
		}

		// Default to allowing if we can't check enrollment.
		return true;
	}

	/**
	 * Find lesson that has a specific quiz attached
	 *
	 * @since 2.0.0
	 *
	 * @param int $quiz_id PPQ Quiz ID.
	 * @return int|null Lesson post ID or null.
	 */
	private function find_lesson_by_quiz( $quiz_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dynamic quiz lookup, not suitable for caching
		$lesson_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = %s AND meta_value = %s
				 LIMIT 1",
				self::META_KEY_QUIZ_ID,
				$quiz_id
			)
		);

		return $lesson_id ? absint( $lesson_id ) : null;
	}

	/**
	 * Enqueue frontend styles
	 *
	 * @since 2.0.0
	 */
	public function enqueue_styles() {
		// Check if we're in a LearnPress lesson context.
		if ( ! class_exists( 'LP_Global' ) ) {
			return;
		}

		$item = LP_Global::course_item();
		if ( ! $item || ! is_a( $item, 'LP_Lesson' ) ) {
			return;
		}

		// Check if this lesson has a quiz.
		$quiz_id = get_post_meta( $item->get_id(), self::META_KEY_QUIZ_ID, true );
		if ( ! $quiz_id ) {
			return;
		}

		// Add inline styles for LearnPress context.
		wp_add_inline_style(
			'ppq-quiz',
			'
			.ppq-learnpress-quiz {
				margin-top: 2rem;
				padding-top: 2rem;
				border-top: 1px solid #e0e0e0;
			}
		'
		);
	}

	/**
	 * Register REST routes
	 *
	 * @since 2.0.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			'ppq/v1',
			'/learnpress/quizzes/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_search_quizzes' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'search' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'recent' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'ppq/v1',
			'/learnpress/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_status' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * REST endpoint: Search quizzes
	 *
	 * @since 2.0.0
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
				array(
					'success' => true,
					'quizzes' => $quizzes,
				)
			);
		}

		$search = $request->get_param( 'search' );

		if ( empty( $search ) || strlen( $search ) < 2 ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					'quizzes' => array(),
				)
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
			array(
				'success' => true,
				'quizzes' => $quizzes,
			)
		);
	}

	/**
	 * REST endpoint: Get LearnPress integration status
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_status( $request ) {
		$status = array(
			'active'      => defined( 'LEARNPRESS_VERSION' ),
			'version'     => defined( 'LEARNPRESS_VERSION' ) ? LEARNPRESS_VERSION : null,
			'min_version' => self::MIN_VERSION,
			'compatible'  => $this->is_learnpress_active(),
			'integration' => 'working',
		);

		// Count how many LearnPress lessons have PPQ quizzes attached.
		if ( $status['active'] ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Status count query, not suitable for caching
			$count                      = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
					self::META_KEY_QUIZ_ID
				)
			);
			$status['attached_quizzes'] = (int) $count;
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'status'  => $status,
			)
		);
	}
}
