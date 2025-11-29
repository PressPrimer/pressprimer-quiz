<?php
/**
 * LearnDash Integration
 *
 * Integrates PressPrimer Quiz with LearnDash LMS.
 * Adds meta boxes to courses, lessons, and topics for quiz assignment.
 * Handles completion tracking and navigation.
 *
 * @package PressPrimer_Quiz
 * @subpackage Integrations
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LearnDash Integration class
 *
 * @since 1.0.0
 */
class PPQ_LearnDash {

	/**
	 * Meta key for storing PPQ quiz ID
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_QUIZ_ID = '_ppq_learndash_quiz_id';

	/**
	 * Meta key for course restriction setting
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_RESTRICT_UNTIL_COMPLETE = '_ppq_learndash_restrict_until_complete';

	/**
	 * Supported post types for quiz assignment
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $supported_post_types = [
		'sfwd-courses',
		'sfwd-lessons',
		'sfwd-topic',
	];

	/**
	 * Initialize the integration
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Only initialize if LearnDash is active
		if ( ! defined( 'LEARNDASH_VERSION' ) ) {
			return;
		}

		// Admin hooks
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_meta_box' ], 10, 2 );

		// AJAX handler for classic editor quiz search
		add_action( 'wp_ajax_ppq_search_quizzes_learndash', [ $this, 'ajax_search_quizzes' ] );

		// Gutenberg support
		add_action( 'init', [ $this, 'register_meta_fields' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );

		// Frontend hooks
		add_filter( 'the_content', [ $this, 'maybe_display_quiz' ], 20 );
		add_filter( 'learndash_mark_complete_button', [ $this, 'maybe_hide_mark_complete' ], 10, 2 );

		// Completion tracking
		add_action( 'ppq_quiz_passed', [ $this, 'handle_quiz_passed' ], 10, 2 );

		// Prevent course auto-completion when PPQ quiz is attached
		add_filter( 'learndash_process_mark_complete', [ $this, 'maybe_prevent_course_completion' ], 10, 3 );

		// Quiz access restriction
		add_filter( 'ppq_quiz_access_allowed', [ $this, 'check_course_restriction' ], 10, 3 );

		// REST API endpoints
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Add navigation data to results page
		add_filter( 'ppq_results_data', [ $this, 'add_navigation_data' ], 10, 2 );
	}

	/**
	 * Register meta boxes for LearnDash post types
	 *
	 * Only registers for Classic Editor - Gutenberg uses the sidebar panel.
	 *
	 * @since 1.0.0
	 */
	public function register_meta_boxes() {
		// Don't register metabox if using block editor - we use the sidebar panel instead
		$screen = get_current_screen();
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return;
		}

		foreach ( $this->supported_post_types as $post_type ) {
			add_meta_box(
				'ppq_learndash_quiz',
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
		wp_nonce_field( 'ppq_learndash_meta_box', 'ppq_learndash_nonce' );

		$quiz_id = get_post_meta( $post->ID, self::META_KEY_QUIZ_ID, true );
		$restrict_until_complete = get_post_meta( $post->ID, self::META_KEY_RESTRICT_UNTIL_COMPLETE, true );
		$is_course = 'sfwd-courses' === $post->post_type;

		// Get quiz display label if one is selected
		$quiz_display = '';
		if ( $quiz_id ) {
			$quiz = PPQ_Quiz::get( $quiz_id );
			$quiz_display = $quiz ? sprintf( '%d - %s', $quiz->id, $quiz->title ) : '';
		}
		?>
		<div class="ppq-learndash-meta-box">
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
					<?php echo $quiz_id ? 'readonly' : ''; ?>
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

			<?php if ( $is_course ) : ?>
				<p style="margin-top: 15px;">
					<label>
						<input
							type="checkbox"
							name="ppq_restrict_until_complete"
							value="1"
							<?php checked( $restrict_until_complete, '1' ); ?>
						/>
						<?php esc_html_e( 'Restrict access until all lessons and topics are completed', 'pressprimer-quiz' ); ?>
					</label>
				</p>
				<p class="description">
					<?php esc_html_e( 'When enabled, users must complete all course content before taking the quiz.', 'pressprimer-quiz' ); ?>
				</p>
			<?php else : ?>
				<p class="description" style="margin-top: 10px;">
					<?php esc_html_e( 'The quiz will appear at the end of this content. Users must pass to mark it complete.', 'pressprimer-quiz' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<style>
			.ppq-learndash-meta-box .ppq-quiz-selector {
				position: relative;
				display: flex;
				align-items: center;
				gap: 4px;
			}
			.ppq-learndash-meta-box .ppq-quiz-search {
				flex: 1;
			}
			.ppq-learndash-meta-box .ppq-quiz-search[readonly] {
				background: #f0f6fc;
				cursor: default;
			}
			.ppq-learndash-meta-box .ppq-quiz-results {
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
			.ppq-learndash-meta-box .ppq-quiz-result-item {
				padding: 8px 12px;
				cursor: pointer;
				border-bottom: 1px solid #f0f0f0;
			}
			.ppq-learndash-meta-box .ppq-quiz-result-item:hover {
				background: #f0f0f0;
			}
			.ppq-learndash-meta-box .ppq-quiz-result-item:last-child {
				border-bottom: none;
			}
			.ppq-learndash-meta-box .ppq-remove-quiz {
				color: #d63638;
				text-decoration: none;
				padding: 4px;
				border: none;
				background: none;
				cursor: pointer;
				display: flex;
				align-items: center;
			}
			.ppq-learndash-meta-box .ppq-remove-quiz:hover {
				color: #b32d2e;
			}
			.ppq-learndash-meta-box .ppq-no-results {
				padding: 12px;
				color: #666;
				font-style: italic;
			}
			.ppq-learndash-meta-box .ppq-quiz-result-item .ppq-quiz-id {
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

			// Format quiz display
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
						action: 'ppq_search_quizzes_learndash',
						nonce: '<?php echo esc_js( wp_create_nonce( 'ppq_learndash_search' ) ); ?>',
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
							action: 'ppq_search_quizzes_learndash',
							nonce: '<?php echo esc_js( wp_create_nonce( 'ppq_learndash_search' ) ); ?>',
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
					$search.after('<button type="button" class="ppq-remove-quiz button-link" aria-label="<?php echo esc_attr__( 'Remove quiz', 'pressprimer-quiz' ); ?>" title="<?php echo esc_attr__( 'Remove quiz', 'pressprimer-quiz' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>');
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

			// Hide results on blur
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
	 * Save meta box data
	 *
	 * @since 1.0.0
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_meta_box( $post_id, $post ) {
		// Verify nonce
		if ( ! isset( $_POST['ppq_learndash_nonce'] ) || ! wp_verify_nonce( $_POST['ppq_learndash_nonce'], 'ppq_learndash_meta_box' ) ) {
			return;
		}

		// Check autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check post type
		if ( ! in_array( $post->post_type, $this->supported_post_types, true ) ) {
			return;
		}

		// Save quiz ID
		$quiz_id = isset( $_POST['ppq_quiz_id'] ) ? absint( $_POST['ppq_quiz_id'] ) : 0;

		if ( $quiz_id ) {
			update_post_meta( $post_id, self::META_KEY_QUIZ_ID, $quiz_id );
		} else {
			delete_post_meta( $post_id, self::META_KEY_QUIZ_ID );
		}

		// Save restriction setting (courses only)
		if ( 'sfwd-courses' === $post->post_type ) {
			$restrict = isset( $_POST['ppq_restrict_until_complete'] ) ? '1' : '';

			if ( $restrict ) {
				update_post_meta( $post_id, self::META_KEY_RESTRICT_UNTIL_COMPLETE, '1' );
			} else {
				delete_post_meta( $post_id, self::META_KEY_RESTRICT_UNTIL_COMPLETE );
			}
		}
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
					'type'              => 'integer',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => function() {
						return current_user_can( 'edit_posts' );
					},
				]
			);
		}

		register_post_meta(
			'sfwd-courses',
			self::META_KEY_RESTRICT_UNTIL_COMPLETE,
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
			]
		);
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
			'ppq-learndash-editor',
			PPQ_PLUGIN_URL . 'assets/js/learndash-editor.js',
			[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ],
			PPQ_VERSION,
			true
		);

		wp_localize_script(
			'ppq-learndash-editor',
			'ppqLearnDash',
			[
				'metaKeyQuizId'   => self::META_KEY_QUIZ_ID,
				'metaKeyRestrict' => self::META_KEY_RESTRICT_UNTIL_COMPLETE,
				'postType'        => $screen->post_type,
				'isCourse'        => 'sfwd-courses' === $screen->post_type,
				'restNonce'       => wp_create_nonce( 'wp_rest' ),
				'strings'         => [
					'panelTitle'       => __( 'PressPrimer Quiz', 'pressprimer-quiz' ),
					'selectQuiz'       => __( 'Select Quiz', 'pressprimer-quiz' ),
					'searchPlaceholder' => __( 'Search for a quiz...', 'pressprimer-quiz' ),
					'noQuiz'           => __( 'No quiz selected', 'pressprimer-quiz' ),
					'restrictLabel'    => __( 'Restrict access until all lessons and topics are completed', 'pressprimer-quiz' ),
					'restrictHelp'     => __( 'When enabled, users must complete all course content before taking the quiz.', 'pressprimer-quiz' ),
					'quizHelp'         => __( 'The quiz will appear at the end of this content. Users must pass to mark it complete.', 'pressprimer-quiz' ),
				],
			]
		);
	}

	/**
	 * Maybe display quiz in content
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public function maybe_display_quiz( $content ) {
		// Only on singular LearnDash content
		if ( ! is_singular( $this->supported_post_types ) ) {
			return $content;
		}

		$post_id = get_the_ID();
		$quiz_id = get_post_meta( $post_id, self::META_KEY_QUIZ_ID, true );

		if ( ! $quiz_id ) {
			return $content;
		}

		// Check course restriction for course-level quizzes
		if ( 'sfwd-courses' === get_post_type( $post_id ) ) {
			$restrict = get_post_meta( $post_id, self::META_KEY_RESTRICT_UNTIL_COMPLETE, true );

			if ( $restrict && ! $this->is_course_content_complete( $post_id ) ) {
				// Get the quiz for title
				$quiz = PPQ_Quiz::get( $quiz_id );
				$quiz_title = $quiz ? $quiz->title : __( 'Quiz', 'pressprimer-quiz' );

				// Get custom message or use default
				$restriction_message = get_option( 'ppq_learndash_restriction_message', '' );
				if ( empty( $restriction_message ) ) {
					$restriction_message = __( 'Complete all lessons and topics to unlock this quiz.', 'pressprimer-quiz' );
				}

				return $content . $this->render_restriction_placeholder( $quiz_title, $restriction_message );
			}
		}

		// Add context data for navigation
		$context_data = [
			'learndash_post_id'   => $post_id,
			'learndash_post_type' => get_post_type( $post_id ),
			'learndash_course_id' => $this->get_course_id_for_post( $post_id ),
		];

		// Render quiz shortcode with context
		$quiz_shortcode = sprintf(
			'[ppq_quiz id="%d" context="%s"]',
			absint( $quiz_id ),
			esc_attr( base64_encode( wp_json_encode( $context_data ) ) )
		);

		return $content . do_shortcode( $quiz_shortcode );
	}

	/**
	 * Maybe hide Mark Complete button
	 *
	 * @since 1.0.0
	 *
	 * @param string $button Button HTML.
	 * @param array  $args   Button arguments.
	 * @return string Modified button HTML.
	 */
	public function maybe_hide_mark_complete( $button, $args ) {
		if ( empty( $args['post'] ) ) {
			return $button;
		}

		$post_id = is_object( $args['post'] ) ? $args['post']->ID : $args['post'];
		$post_type = get_post_type( $post_id );

		// Only hide for lessons and topics
		if ( ! in_array( $post_type, [ 'sfwd-lessons', 'sfwd-topic' ], true ) ) {
			return $button;
		}

		// Check if a quiz is mapped
		$quiz_id = get_post_meta( $post_id, self::META_KEY_QUIZ_ID, true );

		if ( $quiz_id ) {
			// Return empty string to hide the button
			return '';
		}

		return $button;
	}

	/**
	 * Handle quiz passed event
	 *
	 * @since 1.0.0
	 *
	 * @param PPQ_Attempt $attempt Attempt object.
	 * @param PPQ_Quiz    $quiz    Quiz object.
	 */
	public function handle_quiz_passed( $attempt, $quiz ) {
		// Only for logged-in users
		if ( ! $attempt->user_id ) {
			return;
		}

		// Find LearnDash content that uses this quiz
		$ld_posts = $this->get_learndash_posts_for_quiz( $quiz->id );

		foreach ( $ld_posts as $ld_post ) {
			$this->mark_learndash_complete( $ld_post->ID, $attempt->user_id );
		}
	}

	/**
	 * Get LearnDash posts that use a specific quiz
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array Array of post objects.
	 */
	private function get_learndash_posts_for_quiz( $quiz_id ) {
		$args = [
			'post_type'      => $this->supported_post_types,
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
	 * Mark LearnDash content as complete
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id LearnDash post ID.
	 * @param int $user_id User ID.
	 */
	private function mark_learndash_complete( $post_id, $user_id ) {
		$post_type = get_post_type( $post_id );
		$course_id = $this->get_course_id_for_post( $post_id );

		if ( ! $course_id ) {
			return;
		}

		switch ( $post_type ) {
			case 'sfwd-lessons':
				if ( function_exists( 'learndash_process_mark_complete' ) ) {
					learndash_process_mark_complete( $user_id, $post_id, false, $course_id );
				}
				break;

			case 'sfwd-topic':
				if ( function_exists( 'learndash_process_mark_complete' ) ) {
					learndash_process_mark_complete( $user_id, $post_id, false, $course_id );
				}
				break;

			case 'sfwd-courses':
				// Mark course complete when course-level quiz is passed
				if ( function_exists( 'learndash_process_mark_complete' ) ) {
					// Temporarily remove our filter to allow completion
					remove_filter( 'learndash_process_mark_complete', [ $this, 'maybe_prevent_course_completion' ], 10 );

					learndash_process_mark_complete( $user_id, $post_id, false, $course_id );

					// Re-add the filter
					add_filter( 'learndash_process_mark_complete', [ $this, 'maybe_prevent_course_completion' ], 10, 3 );
				}
				break;
		}
	}

	/**
	 * Prevent course auto-completion when a PPQ quiz is attached
	 *
	 * This filter blocks LearnDash from auto-completing a course when all
	 * lessons/topics are done, if the course has a PPQ quiz attached.
	 * The course will only be marked complete when the quiz is passed.
	 *
	 * @since 1.0.0
	 *
	 * @param bool    $mark_complete Whether to mark complete.
	 * @param WP_Post $post          The post being marked complete.
	 * @param WP_User $current_user  The current user.
	 * @return bool Modified mark_complete value.
	 */
	public function maybe_prevent_course_completion( $mark_complete, $post, $current_user ) {
		// Only intercept course completion
		if ( ! $post || 'sfwd-courses' !== $post->post_type ) {
			return $mark_complete;
		}

		// Check if this course has a PPQ quiz attached
		$quiz_id = get_post_meta( $post->ID, self::META_KEY_QUIZ_ID, true );

		if ( ! $quiz_id ) {
			// No quiz attached, allow normal completion
			return $mark_complete;
		}

		// Check if the user has passed the quiz
		$user_id = $current_user->ID;

		if ( $this->has_user_passed_quiz( $user_id, $quiz_id ) ) {
			// User has passed the quiz, allow completion
			return $mark_complete;
		}

		// Block completion - user hasn't passed the quiz yet
		return false;
	}

	/**
	 * Check if a user has passed a specific quiz
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @param int $quiz_id Quiz ID.
	 * @return bool True if user has passed.
	 */
	private function has_user_passed_quiz( $user_id, $quiz_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ppq_attempts';

		$passed = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND quiz_id = %d AND passed = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$quiz_id
			)
		);

		return (int) $passed > 0;
	}

	/**
	 * Get course ID for a LearnDash post
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @return int|null Course ID or null.
	 */
	private function get_course_id_for_post( $post_id ) {
		$post_type = get_post_type( $post_id );

		if ( 'sfwd-courses' === $post_type ) {
			return $post_id;
		}

		// Get course ID from post meta (LearnDash stores this)
		$course_id = get_post_meta( $post_id, 'course_id', true );

		if ( $course_id ) {
			return (int) $course_id;
		}

		// Try LearnDash function
		if ( function_exists( 'learndash_get_course_id' ) ) {
			return learndash_get_course_id( $post_id );
		}

		return null;
	}

	/**
	 * Check if all course content is complete
	 *
	 * @since 1.0.0
	 *
	 * @param int $course_id Course ID.
	 * @return bool True if all content is complete.
	 */
	private function is_course_content_complete( $course_id ) {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		if ( ! function_exists( 'learndash_course_progress' ) ) {
			return true; // Allow if we can't check
		}

		$progress = learndash_course_progress( [
			'user_id'   => $user_id,
			'course_id' => $course_id,
			'array'     => true,
		] );

		if ( ! is_array( $progress ) ) {
			return true;
		}

		// Check if all steps (except the final quiz/course itself) are complete
		$total = isset( $progress['total'] ) ? (int) $progress['total'] : 0;
		$completed = isset( $progress['completed'] ) ? (int) $progress['completed'] : 0;

		// All lessons/topics should be complete
		return $completed >= $total;
	}

	/**
	 * Check course restriction for quiz access
	 *
	 * @since 1.0.0
	 *
	 * @param bool        $allowed   Current access status.
	 * @param PPQ_Quiz    $quiz      Quiz object.
	 * @param PPQ_Attempt $attempt   Attempt object (may be null).
	 * @return bool Modified access status.
	 */
	public function check_course_restriction( $allowed, $quiz, $attempt ) {
		if ( ! $allowed ) {
			return false;
		}

		// Find if this quiz is used on a course with restriction
		$args = [
			'post_type'      => 'sfwd-courses',
			'posts_per_page' => 1,
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'   => self::META_KEY_QUIZ_ID,
					'value' => $quiz->id,
				],
				[
					'key'   => self::META_KEY_RESTRICT_UNTIL_COMPLETE,
					'value' => '1',
				],
			],
		];

		$courses = get_posts( $args );

		if ( empty( $courses ) ) {
			return $allowed;
		}

		$course = $courses[0];

		if ( ! $this->is_course_content_complete( $course->ID ) ) {
			return false;
		}

		return $allowed;
	}

	/**
	 * AJAX handler for searching quizzes (classic editor)
	 *
	 * @since 1.0.0
	 */
	public function ajax_search_quizzes() {
		// Verify nonce
		if ( ! check_ajax_referer( 'ppq_learndash_search', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'pressprimer-quiz' ) ] );
		}

		// Check permissions
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ] );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'ppq_quizzes';

		// Check if requesting recent quizzes
		$recent = isset( $_POST['recent'] ) && $_POST['recent'];

		if ( $recent ) {
			// Get 50 most recent quizzes created by current user
			$user_id = get_current_user_id();
			$quizzes = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, title FROM {$table} WHERE status = 'published' AND owner_id = %d ORDER BY id DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id
				)
			);

			wp_send_json_success( [ 'quizzes' => $quizzes ] );
			return;
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( [ 'quizzes' => [] ] );
		}

		$quizzes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title FROM {$table} WHERE title LIKE %s AND status = 'published' ORDER BY title ASC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%' . $wpdb->esc_like( $search ) . '%'
			)
		);

		wp_send_json_success( [ 'quizzes' => $quizzes ] );
	}

	/**
	 * Register REST routes
	 *
	 * @since 1.0.0
	 */
	public function register_rest_routes() {
		register_rest_route( 'ppq/v1', '/learndash/quizzes/search', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_search_quizzes' ],
			'permission_callback' => function() {
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
		] );

		register_rest_route( 'ppq/v1', '/learndash/navigation', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_get_navigation' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'post_id' => [
					'required'          => true,
					'sanitize_callback' => 'absint',
				],
			],
		] );

		register_rest_route( 'ppq/v1', '/learndash/status', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_get_status' ],
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		] );

		register_rest_route( 'ppq/v1', '/learndash/settings', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rest_save_settings' ],
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
		] );
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

		$table = $wpdb->prefix . 'ppq_quizzes';
		$recent = $request->get_param( 'recent' );

		if ( $recent ) {
			// Get 50 most recent quizzes created by current user
			$user_id = get_current_user_id();
			$quizzes = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, title FROM {$table} WHERE status = 'published' AND owner_id = %d ORDER BY id DESC LIMIT 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id
				)
			);

			return new WP_REST_Response( [
				'success' => true,
				'quizzes' => $quizzes,
			] );
		}

		$search = $request->get_param( 'search' );

		if ( empty( $search ) || strlen( $search ) < 2 ) {
			return new WP_REST_Response( [
				'success' => true,
				'quizzes' => [],
			] );
		}

		$quizzes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, title FROM {$table} WHERE title LIKE %s AND status = 'published' ORDER BY title ASC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%' . $wpdb->esc_like( $search ) . '%'
			)
		);

		return new WP_REST_Response( [
			'success' => true,
			'quizzes' => $quizzes,
		] );
	}

	/**
	 * REST endpoint: Get LearnDash integration status
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_status( $request ) {
		$status = [
			'active'      => defined( 'LEARNDASH_VERSION' ),
			'version'     => defined( 'LEARNDASH_VERSION' ) ? LEARNDASH_VERSION : null,
			'integration' => 'working',
		];

		// Count how many LearnDash posts have PPQ quizzes attached
		if ( $status['active'] ) {
			global $wpdb;
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
					self::META_KEY_QUIZ_ID
				)
			);
			$status['attached_quizzes'] = (int) $count;
		}

		// Get settings
		$settings = [
			'restriction_message' => get_option( 'ppq_learndash_restriction_message', '' ),
		];

		return new WP_REST_Response( [
			'success'  => true,
			'status'   => $status,
			'settings' => $settings,
		] );
	}

	/**
	 * REST endpoint: Save LearnDash settings
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_save_settings( $request ) {
		$restriction_message = $request->get_param( 'restriction_message' );

		if ( null !== $restriction_message ) {
			update_option( 'ppq_learndash_restriction_message', sanitize_textarea_field( $restriction_message ) );
		}

		return new WP_REST_Response( [
			'success' => true,
		] );
	}

	/**
	 * REST endpoint: Get navigation data
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function rest_get_navigation( $request ) {
		$post_id = $request->get_param( 'post_id' );

		$next_url = $this->get_next_step_url( $post_id );
		$course_id = $this->get_course_id_for_post( $post_id );

		return new WP_REST_Response( [
			'success'   => true,
			'next_url'  => $next_url,
			'course_id' => $course_id,
			'course_url' => $course_id ? get_permalink( $course_id ) : null,
		] );
	}

	/**
	 * Get URL for next step in course
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Current LearnDash post ID.
	 * @return string|null Next step URL or null.
	 */
	public function get_next_step_url( $post_id ) {
		$post_type = get_post_type( $post_id );
		$course_id = $this->get_course_id_for_post( $post_id );

		if ( ! $course_id ) {
			return null;
		}

		// For courses, return to course page
		if ( 'sfwd-courses' === $post_type ) {
			return get_permalink( $course_id );
		}

		// Use LearnDash's next step logic if available
		if ( function_exists( 'learndash_next_post_link' ) ) {
			$user_id = get_current_user_id();
			$next_link = learndash_next_post_link( '', true, get_post( $post_id ), $user_id, $course_id );

			// Extract URL from link HTML
			if ( preg_match( '/href=["\']([^"\']+)["\']/', $next_link, $matches ) ) {
				return $matches[1];
			}
		}

		// Fallback: Get next lesson/topic manually
		$next_step = $this->get_next_step( $post_id, $course_id );

		if ( $next_step ) {
			return get_permalink( $next_step );
		}

		// Default to course page
		return get_permalink( $course_id );
	}

	/**
	 * Get next step in course progression
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id   Current post ID.
	 * @param int $course_id Course ID.
	 * @return int|null Next step post ID or null.
	 */
	private function get_next_step( $post_id, $course_id ) {
		$post_type = get_post_type( $post_id );

		if ( 'sfwd-topic' === $post_type ) {
			// Get parent lesson
			$lesson_id = get_post_meta( $post_id, 'lesson_id', true );

			if ( ! $lesson_id && function_exists( 'learndash_get_lesson_id' ) ) {
				$lesson_id = learndash_get_lesson_id( $post_id );
			}

			if ( $lesson_id ) {
				// Get all topics for this lesson
				$topics = $this->get_lesson_topics( $lesson_id, $course_id );
				$current_index = array_search( $post_id, $topics, true );

				if ( false !== $current_index && isset( $topics[ $current_index + 1 ] ) ) {
					return $topics[ $current_index + 1 ];
				}

				// No more topics, get next lesson
				return $this->get_next_lesson( $lesson_id, $course_id );
			}
		}

		if ( 'sfwd-lessons' === $post_type ) {
			// Check for topics in this lesson
			$topics = $this->get_lesson_topics( $post_id, $course_id );

			if ( ! empty( $topics ) ) {
				return $topics[0];
			}

			// No topics, get next lesson
			return $this->get_next_lesson( $post_id, $course_id );
		}

		return null;
	}

	/**
	 * Get topics for a lesson
	 *
	 * @since 1.0.0
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $course_id Course ID.
	 * @return array Array of topic IDs.
	 */
	private function get_lesson_topics( $lesson_id, $course_id ) {
		if ( function_exists( 'learndash_get_topic_list' ) ) {
			$topics = learndash_get_topic_list( $lesson_id, $course_id );

			if ( is_array( $topics ) ) {
				return wp_list_pluck( $topics, 'ID' );
			}
		}

		return [];
	}

	/**
	 * Get next lesson in course
	 *
	 * @since 1.0.0
	 *
	 * @param int $lesson_id Current lesson ID.
	 * @param int $course_id Course ID.
	 * @return int|null Next lesson ID or null.
	 */
	private function get_next_lesson( $lesson_id, $course_id ) {
		if ( function_exists( 'learndash_get_lesson_list' ) ) {
			$lessons = learndash_get_lesson_list( $course_id );

			if ( is_array( $lessons ) ) {
				$lesson_ids = wp_list_pluck( $lessons, 'ID' );
				$current_index = array_search( $lesson_id, $lesson_ids, true );

				if ( false !== $current_index && isset( $lesson_ids[ $current_index + 1 ] ) ) {
					return $lesson_ids[ $current_index + 1 ];
				}
			}
		}

		return null;
	}

	/**
	 * Add navigation data to results page
	 *
	 * @since 1.0.0
	 *
	 * @param array       $data    Results data.
	 * @param PPQ_Attempt $attempt Attempt object.
	 * @return array Modified results data.
	 */
	public function add_navigation_data( $data, $attempt ) {
		// Check if this attempt was taken in LearnDash context
		$meta = $attempt->meta_json ? json_decode( $attempt->meta_json, true ) : [];

		if ( empty( $meta['learndash_post_id'] ) ) {
			return $data;
		}

		$ld_post_id = (int) $meta['learndash_post_id'];
		$post_type = get_post_type( $ld_post_id );

		// Add LearnDash navigation data
		$data['learndash'] = [
			'post_id'      => $ld_post_id,
			'post_type'    => $post_type,
			'next_url'     => $this->get_next_step_url( $ld_post_id ),
			'course_id'    => $this->get_course_id_for_post( $ld_post_id ),
			'course_url'   => get_permalink( $this->get_course_id_for_post( $ld_post_id ) ),
			'show_advance' => $attempt->passed && in_array( $post_type, [ 'sfwd-lessons', 'sfwd-topic' ], true ),
		];

		return $data;
	}

	/**
	 * Render the restriction placeholder for locked quizzes
	 *
	 * Shows a blurred quiz-like placeholder with the restriction message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $quiz_title         Quiz title.
	 * @param string $restriction_message Message to display.
	 * @return string HTML output.
	 */
	private function render_restriction_placeholder( $quiz_title, $restriction_message ) {
		ob_start();
		?>
		<div class="ppq-restriction-placeholder">
			<div class="ppq-restriction-placeholder__blurred">
				<div class="ppq-restriction-placeholder__header">
					<div class="ppq-restriction-placeholder__title"><?php echo esc_html( $quiz_title ); ?></div>
				</div>
				<div class="ppq-restriction-placeholder__content">
					<div class="ppq-restriction-placeholder__question">
						<div class="ppq-restriction-placeholder__question-text"></div>
						<div class="ppq-restriction-placeholder__options">
							<div class="ppq-restriction-placeholder__option"></div>
							<div class="ppq-restriction-placeholder__option"></div>
							<div class="ppq-restriction-placeholder__option"></div>
							<div class="ppq-restriction-placeholder__option"></div>
						</div>
					</div>
				</div>
			</div>
			<div class="ppq-restriction-placeholder__overlay">
				<div class="ppq-restriction-placeholder__lock">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
						<path d="M12 1C8.676 1 6 3.676 6 7v2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V11c0-1.1-.9-2-2-2h-2V7c0-3.324-2.676-6-6-6zm0 2c2.276 0 4 1.724 4 4v2H8V7c0-2.276 1.724-4 4-4zm0 10c1.1 0 2 .9 2 2 0 .74-.4 1.38-1 1.72V19h-2v-2.28c-.6-.34-1-.98-1-1.72 0-1.1.9-2 2-2z"/>
					</svg>
				</div>
				<div class="ppq-restriction-placeholder__message">
					<?php echo esc_html( $restriction_message ); ?>
				</div>
			</div>
		</div>
		<style>
			.ppq-restriction-placeholder {
				position: relative;
				margin: 24px 0;
				border-radius: 8px;
				overflow: hidden;
				background: #f8f9fa;
			}
			.ppq-restriction-placeholder__blurred {
				filter: blur(6px);
				opacity: 0.6;
				pointer-events: none;
				user-select: none;
			}
			.ppq-restriction-placeholder__header {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				padding: 24px;
			}
			.ppq-restriction-placeholder__title {
				color: #fff;
				font-size: 20px;
				font-weight: 600;
				background: rgba(255,255,255,0.2);
				height: 24px;
				width: 60%;
				border-radius: 4px;
			}
			.ppq-restriction-placeholder__content {
				padding: 24px;
			}
			.ppq-restriction-placeholder__question-text {
				background: #e9ecef;
				height: 20px;
				width: 80%;
				border-radius: 4px;
				margin-bottom: 16px;
			}
			.ppq-restriction-placeholder__options {
				display: flex;
				flex-direction: column;
				gap: 12px;
			}
			.ppq-restriction-placeholder__option {
				background: #e9ecef;
				height: 44px;
				border-radius: 6px;
			}
			.ppq-restriction-placeholder__option:nth-child(2) { width: 90%; }
			.ppq-restriction-placeholder__option:nth-child(3) { width: 85%; }
			.ppq-restriction-placeholder__option:nth-child(4) { width: 75%; }
			.ppq-restriction-placeholder__overlay {
				position: absolute;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				background: rgba(255,255,255,0.4);
				backdrop-filter: blur(2px);
			}
			.ppq-restriction-placeholder__lock {
				color: #6c757d;
				margin-bottom: 16px;
			}
			.ppq-restriction-placeholder__message {
				font-size: 16px;
				font-weight: 500;
				color: #495057;
				text-align: center;
				padding: 0 24px;
				max-width: 400px;
				background: #fff;
				padding: 16px 24px;
				border-radius: 8px;
				box-shadow: 0 2px 8px rgba(0,0,0,0.1);
			}
		</style>
		<?php
		return ob_get_clean();
	}
}
