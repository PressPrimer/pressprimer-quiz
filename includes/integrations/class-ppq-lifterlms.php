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
class PPQ_LifterLMS {

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

		// Frontend hooks.
		add_action( 'lifterlms_single_lesson_after_summary', [ $this, 'display_quiz_in_lesson' ] );

		// Hide LifterLMS complete button when quiz is attached.
		add_filter( 'llms_is_complete', [ $this, 'check_ppq_quiz_complete' ], 10, 4 );

		// Completion tracking.
		add_action( 'ppq_quiz_passed', [ $this, 'handle_quiz_passed' ], 10, 2 );

		// Map Instructors to ppq_teacher role.
		add_filter( 'ppq_user_has_teacher_capability', [ $this, 'check_instructor_capability' ], 10, 2 );

		// AJAX handler for metabox quiz search (classic editor).
		add_action( 'wp_ajax_ppq_search_quizzes_lifterlms', [ $this, 'ajax_search_quizzes' ] );

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
		wp_nonce_field( 'ppq_lifterlms_meta_box', 'ppq_lifterlms_nonce' );

		$quiz_id      = get_post_meta( $post->ID, self::META_KEY_QUIZ_ID, true );
		$require_pass = get_post_meta( $post->ID, self::META_KEY_REQUIRE_PASS, true );

		// Get quiz title if one is selected.
		$quiz_title = '';
		if ( $quiz_id ) {
			$quiz = class_exists( 'PPQ_Quiz' ) ? PPQ_Quiz::get( $quiz_id ) : null;
			if ( $quiz ) {
				$quiz_title = $quiz->title;
			}
		}
		?>
		<div class="ppq-lifterlms-metabox">
			<p>
				<label for="ppq_lifterlms_quiz_id"><?php esc_html_e( 'Select Quiz:', 'pressprimer-quiz' ); ?></label>
				<input type="hidden" name="ppq_lifterlms_quiz_id" id="ppq_lifterlms_quiz_id" value="<?php echo esc_attr( $quiz_id ); ?>" />
				<span class="ppq-quiz-input-wrapper" style="display: flex; gap: 5px;">
					<input type="text" id="ppq_lifterlms_quiz_search" class="widefat" placeholder="<?php esc_attr_e( 'Search quizzes...', 'pressprimer-quiz' ); ?>" value="<?php echo esc_attr( $quiz_title ? $quiz_id . ' - ' . $quiz_title : '' ); ?>" autocomplete="off" style="flex: 1;" />
					<button type="button" id="ppq_lifterlms_quiz_clear" class="button" title="<?php esc_attr_e( 'Remove quiz', 'pressprimer-quiz' ); ?>" <?php echo $quiz_id ? '' : 'style="display:none;"'; ?>>&times;</button>
				</span>
				<span id="ppq_lifterlms_quiz_results" class="ppq-search-results hidden"></span>
			</p>
			<p>
				<label>
					<input type="checkbox" name="ppq_lifterlms_require_pass" value="1" <?php checked( $require_pass, '1' ); ?> />
					<?php esc_html_e( 'Require passing score to complete lesson', 'pressprimer-quiz' ); ?>
				</label>
			</p>
			<p class="description">
				<?php esc_html_e( 'When enabled, students must pass this quiz to mark the lesson complete.', 'pressprimer-quiz' ); ?>
			</p>
		</div>
		<style>
			.ppq-lifterlms-metabox .ppq-search-results {
				display: block;
				max-height: 150px;
				overflow-y: auto;
				border: 1px solid #ddd;
				border-top: none;
				background: #fff;
			}
			.ppq-lifterlms-metabox .ppq-search-results.hidden {
				display: none;
			}
			.ppq-lifterlms-metabox .ppq-search-result {
				padding: 8px 12px;
				cursor: pointer;
				border-bottom: 1px solid #f0f0f0;
			}
			.ppq-lifterlms-metabox .ppq-search-result:hover {
				background: #f0f6fc;
			}
		</style>
		<script>
		jQuery(document).ready(function($) {
			var $search = $('#ppq_lifterlms_quiz_search');
			var $input = $('#ppq_lifterlms_quiz_id');
			var $results = $('#ppq_lifterlms_quiz_results');
			var $clearBtn = $('#ppq_lifterlms_quiz_clear');
			var searchTimeout;

			$search.on('input', function() {
				clearTimeout(searchTimeout);
				var query = $(this).val();

				if (query.length < 2) {
					$results.addClass('hidden').empty();
					return;
				}

				searchTimeout = setTimeout(function() {
					$.ajax({
						url: ajaxurl,
						data: {
							action: 'ppq_search_quizzes_lifterlms',
							search: query,
							nonce: '<?php echo esc_js( wp_create_nonce( 'ppq_search_quizzes_lifterlms' ) ); ?>'
						},
						success: function(response) {
							if (response.success && response.data.length) {
								$results.removeClass('hidden').empty();
								response.data.forEach(function(quiz) {
									$('<div class="ppq-search-result">')
										.text(quiz.id + ' - ' + quiz.title)
										.data('quiz', quiz)
										.appendTo($results);
								});
							} else {
								$results.addClass('hidden').empty();
							}
						}
					});
				}, 300);
			});

			$results.on('click', '.ppq-search-result', function() {
				var quiz = $(this).data('quiz');
				$input.val(quiz.id);
				$search.val(quiz.id + ' - ' + quiz.title);
				$results.addClass('hidden').empty();
				$clearBtn.show();
			});

			// Clear button click handler.
			$clearBtn.on('click', function() {
				$input.val('');
				$search.val('');
				$results.addClass('hidden').empty();
				$(this).hide();
			});

			// Select text on focus for easy replacement.
			$search.on('focus', function() {
				if ($input.val()) {
					$(this).select();
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
		// Verify nonce.
		if ( ! isset( $_POST['ppq_lifterlms_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ppq_lifterlms_nonce'] ) ), 'ppq_lifterlms_meta_box' ) ) {
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
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save quiz ID.
		$quiz_id = isset( $_POST['ppq_lifterlms_quiz_id'] ) ? absint( $_POST['ppq_lifterlms_quiz_id'] ) : 0;
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
		$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;

		// Get lesson quiz mappings for this course (for sidebar indicators).
		$lesson_quizzes = $this->get_lesson_quizzes_for_course( $course_id );

		wp_enqueue_script(
			'ppq-lifterlms-course-builder',
			PPQ_PLUGIN_URL . 'assets/js/lifterlms-course-builder.js',
			[ 'llms-builder' ],
			PPQ_VERSION,
			true
		);

		wp_localize_script(
			'ppq-lifterlms-course-builder',
			'ppqLifterLMS',
			[
				'courseId'      => $course_id,
				'lessonQuizzes' => $lesson_quizzes,
			]
		);

		wp_enqueue_style(
			'ppq-lifterlms-course-builder',
			PPQ_PLUGIN_URL . 'assets/css/lifterlms-course-builder.css',
			[],
			PPQ_VERSION
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
				$quiz = class_exists( 'PPQ_Quiz' ) ? PPQ_Quiz::get( $quiz_id ) : null;
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
	 * @since 1.0.0
	 */
	public function display_quiz_in_lesson() {
		global $post;

		if ( ! $post || 'lesson' !== $post->post_type ) {
			return;
		}

		$quiz_id = get_post_meta( $post->ID, self::META_KEY_QUIZ_ID, true );

		if ( ! $quiz_id ) {
			return;
		}

		// Check if user is enrolled in the course.
		$lesson  = new LLMS_Lesson( $post->ID );
		$course  = $lesson->get_course();
		$user_id = get_current_user_id();

		if ( $course && ! llms_is_user_enrolled( $user_id, $course->get( 'id' ) ) ) {
			echo '<div class="ppq-access-denied">';
			esc_html_e( 'Enroll in this course to access the quiz.', 'pressprimer-quiz' );
			echo '</div>';
			return;
		}

		// Render the quiz.
		if ( function_exists( 'ppq_render_quiz' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo ppq_render_quiz(
				$quiz_id,
				[
					'context'      => 'lifterlms',
					'context_id'   => $post->ID,
					'context_type' => 'lesson',
					'course_id'    => $course ? $course->get( 'id' ) : 0,
				]
			);
		}
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
		if ( class_exists( 'PPQ_Attempt' ) ) {
			$passed = PPQ_Attempt::user_has_passed( $quiz_id, $user_id );
			return $passed ? $is_complete : false;
		}

		return $is_complete;
	}

	/**
	 * Handle quiz passed event
	 *
	 * @since 1.0.0
	 *
	 * @param int $quiz_id PPQ Quiz ID.
	 * @param int $user_id User ID.
	 */
	public function handle_quiz_passed( $quiz_id, $user_id ) {
		global $wpdb;

		// Find lessons using this quiz.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$lessons = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = %s AND meta_value = %d",
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
		do_action( 'ppq_lifterlms_lesson_completed', $lesson_id, $user_id );
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
					'lesson_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'quiz_id'   => [
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

		if ( class_exists( 'PPQ_Quiz' ) ) {
			$args = [
				'where'    => [
					'status' => 'published',
				],
				'limit'    => 10,
				'order_by' => 'id',
				'order'    => 'DESC',
			];

			$results = PPQ_Quiz::find( $args );

			// Filter by search term if provided.
			if ( $search ) {
				$search_lower = strtolower( $search );
				$results = array_filter( $results, function( $quiz ) use ( $search_lower ) {
					return strpos( strtolower( $quiz->title ), $search_lower ) !== false
						|| strpos( (string) $quiz->id, $search_lower ) !== false;
				} );
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
		if ( ! current_user_can( 'edit_post', $lesson_id ) ) {
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
			if ( class_exists( 'PPQ_Quiz' ) ) {
				$quiz = PPQ_Quiz::get( $quiz_id );
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
		check_ajax_referer( 'ppq_search_quizzes_lifterlms', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error();
		}

		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

		$quizzes = [];

		if ( class_exists( 'PPQ_Quiz' ) && strlen( $search ) >= 2 ) {
			// Use PPQ_Quiz::find() instead of get_all() which doesn't exist.
			$results = PPQ_Quiz::find(
				[
					'where'    => [ 'status' => 'published' ],
					'limit'    => 10,
					'order_by' => 'title',
					'order'    => 'ASC',
				]
			);

			// Filter by search term.
			$search_lower = strtolower( $search );
			foreach ( $results as $quiz ) {
				if ( strpos( strtolower( $quiz->title ), $search_lower ) !== false
					|| strpos( (string) $quiz->id, $search_lower ) !== false ) {
					$quizzes[] = [
						'id'    => $quiz->id,
						'title' => $quiz->title,
					];
				}
			}
		}

		wp_send_json_success( $quizzes );
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
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != ''",
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
