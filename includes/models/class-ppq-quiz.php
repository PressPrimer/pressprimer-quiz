<?php
/**
 * Quiz Model
 *
 * Represents a quiz with all configuration and settings.
 *
 * @package PressPrimer_Quiz
 * @subpackage Models
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quiz model class
 *
 * Manages quiz configuration including settings, behavior, navigation,
 * attempts, display options, and question generation.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Quiz extends PressPrimer_Quiz_Model {

	/**
	 * Quiz UUID
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $uuid;

	/**
	 * Quiz title
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $title;

	/**
	 * Quiz description
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $description;

	/**
	 * Featured image attachment ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $featured_image_id;

	/**
	 * Owner user ID
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $owner_id;

	/**
	 * Quiz status
	 *
	 * @since 1.0.0
	 * @var string draft|published|archived
	 */
	public $status = 'draft';

	/**
	 * Quiz mode
	 *
	 * @since 1.0.0
	 * @var string tutorial|timed
	 */
	public $mode = 'tutorial';

	/**
	 * Time limit in seconds
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $time_limit_seconds;

	/**
	 * Passing percentage
	 *
	 * @since 1.0.0
	 * @var float
	 */
	public $pass_percent = 70.00;

	/**
	 * Allow skip questions
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $allow_skip = 1;

	/**
	 * Allow backward navigation
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $allow_backward = 1;

	/**
	 * Allow resume
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $allow_resume = 1;

	/**
	 * Maximum attempts allowed
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $max_attempts;

	/**
	 * Delay between attempts in minutes
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $attempt_delay_minutes;

	/**
	 * Randomize question order
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $randomize_questions = 0;

	/**
	 * Randomize answer options
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $randomize_answers = 0;

	/**
	 * Page mode
	 *
	 * @since 1.0.0
	 * @var string single|paged
	 */
	public $page_mode = 'single';

	/**
	 * Questions per page (for paged mode)
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $questions_per_page = 1;

	/**
	 * When to show answers
	 *
	 * @since 1.0.0
	 * @var string never|after_submit|after_pass
	 */
	public $show_answers = 'after_submit';

	/**
	 * Enable confidence rating
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $enable_confidence = 0;

	/**
	 * Theme identifier
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $theme = 'default';

	/**
	 * Theme settings JSON
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $theme_settings_json;

	/**
	 * Band feedback JSON
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $band_feedback_json;

	/**
	 * Generation mode
	 *
	 * @since 1.0.0
	 * @var string fixed|dynamic
	 */
	public $generation_mode = 'fixed';

	/**
	 * Access mode for the quiz
	 *
	 * @since 2.0.0
	 * @var string default|guest_optional|guest_required|login_required
	 */
	public $access_mode = 'default';

	/**
	 * Custom login message for this quiz
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public $login_message;

	/**
	 * Display density
	 *
	 * @since 2.0.0
	 * @var string default|standard|condensed
	 */
	public $display_density = 'default';

	/**
	 * Created timestamp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $created_at;

	/**
	 * Updated timestamp
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $updated_at;

	/**
	 * Cached quiz items (for fixed mode)
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $_items = null;

	/**
	 * Cached quiz rules (for dynamic mode)
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	private $_rules = null;

	/**
	 * Get table name
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name() {
		return 'ppq_quizzes';
	}

	/**
	 * Get fillable fields
	 *
	 * @since 1.0.0
	 *
	 * @return array Field names that can be mass-assigned.
	 */
	protected static function get_fillable_fields() {
		return [
			'uuid',
			'title',
			'description',
			'featured_image_id',
			'owner_id',
			'status',
			'mode',
			'time_limit_seconds',
			'pass_percent',
			'allow_skip',
			'allow_backward',
			'allow_resume',
			'max_attempts',
			'attempt_delay_minutes',
			'randomize_questions',
			'randomize_answers',
			'page_mode',
			'questions_per_page',
			'show_answers',
			'enable_confidence',
			'theme',
			'theme_settings_json',
			'band_feedback_json',
			'generation_mode',
			'access_mode',
			'login_message',
			'display_density',
		];
	}

	/**
	 * Get quiz by UUID
	 *
	 * @since 1.0.0
	 *
	 * @param string $uuid Quiz UUID.
	 * @return PressPrimer_Quiz_Quiz|null Quiz instance or null if not found.
	 */
	public static function get_by_uuid( string $uuid ) {
		global $wpdb;

		$table = static::get_full_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- UUID lookup
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE uuid = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$uuid
			)
		);

		return $row ? static::from_row( $row ) : null;
	}

	/**
	 * Create new quiz
	 *
	 * Validates all input and creates a new quiz record.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Quiz data.
	 * @return int|WP_Error Quiz ID on success, WP_Error on failure.
	 */
	public static function create( array $data ) {
		// Validate required fields
		if ( empty( $data['title'] ) ) {
			return new WP_Error(
				'ppq_invalid_title',
				__( 'Quiz title is required.', 'pressprimer-quiz' )
			);
		}

		// Sanitize title
		$data['title'] = sanitize_text_field( $data['title'] );

		if ( mb_strlen( $data['title'] ) > 200 ) {
			return new WP_Error(
				'ppq_invalid_title',
				__( 'Quiz title must be 200 characters or less.', 'pressprimer-quiz' )
			);
		}

		// Sanitize description
		if ( ! empty( $data['description'] ) ) {
			$data['description'] = wp_kses_post( $data['description'] );
		}

		// Generate UUID if not provided
		if ( empty( $data['uuid'] ) ) {
			$data['uuid'] = wp_generate_uuid4();
		}

		// Set owner if not provided (use current user)
		if ( empty( $data['owner_id'] ) ) {
			$data['owner_id'] = get_current_user_id();
		}

		// Validate and sanitize numeric fields
		if ( isset( $data['featured_image_id'] ) ) {
			$data['featured_image_id'] = absint( $data['featured_image_id'] );
		}

		if ( isset( $data['pass_percent'] ) ) {
			$pass_percent = floatval( $data['pass_percent'] );
			if ( $pass_percent < 0 || $pass_percent > 100 ) {
				return new WP_Error(
					'ppq_invalid_pass_percent',
					__( 'Passing percentage must be between 0 and 100.', 'pressprimer-quiz' )
				);
			}
			$data['pass_percent'] = $pass_percent;
		}

		// Validate time limit (if set, must be between 1 minute and 24 hours)
		if ( isset( $data['time_limit_seconds'] ) && ! empty( $data['time_limit_seconds'] ) ) {
			$time_limit = absint( $data['time_limit_seconds'] );
			if ( $time_limit < 60 || $time_limit > 86400 ) {
				return new WP_Error(
					'ppq_invalid_time_limit',
					__( 'Time limit must be between 60 and 86400 seconds (1 minute to 24 hours).', 'pressprimer-quiz' )
				);
			}
			$data['time_limit_seconds'] = $time_limit;
		}

		// Validate max attempts
		if ( isset( $data['max_attempts'] ) && ! empty( $data['max_attempts'] ) ) {
			$max_attempts = absint( $data['max_attempts'] );
			if ( $max_attempts < 1 || $max_attempts > 100 ) {
				return new WP_Error(
					'ppq_invalid_max_attempts',
					__( 'Maximum attempts must be between 1 and 100.', 'pressprimer-quiz' )
				);
			}
			$data['max_attempts'] = $max_attempts;
		}

		// Validate delay between attempts (max 1 week)
		if ( isset( $data['attempt_delay_minutes'] ) && ! empty( $data['attempt_delay_minutes'] ) ) {
			$delay = absint( $data['attempt_delay_minutes'] );
			if ( $delay < 0 || $delay > 10080 ) {
				return new WP_Error(
					'ppq_invalid_delay',
					__( 'Attempt delay must be between 0 and 10080 minutes (1 week).', 'pressprimer-quiz' )
				);
			}
			$data['attempt_delay_minutes'] = $delay;
		}

		// Validate and sanitize ENUM fields
		if ( isset( $data['status'] ) ) {
			$valid_statuses = [ 'draft', 'published', 'archived' ];
			if ( ! in_array( $data['status'], $valid_statuses, true ) ) {
				$data['status'] = 'draft';
			}
		}

		if ( isset( $data['mode'] ) ) {
			$valid_modes = [ 'tutorial', 'timed' ];
			if ( ! in_array( $data['mode'], $valid_modes, true ) ) {
				$data['mode'] = 'tutorial';
			}
		}

		if ( isset( $data['page_mode'] ) ) {
			$valid_page_modes = [ 'single', 'paged' ];
			if ( ! in_array( $data['page_mode'], $valid_page_modes, true ) ) {
				$data['page_mode'] = 'single';
			}
		}

		if ( isset( $data['show_answers'] ) ) {
			$valid_show_answers = [ 'never', 'after_submit', 'after_pass' ];
			if ( ! in_array( $data['show_answers'], $valid_show_answers, true ) ) {
				$data['show_answers'] = 'after_submit';
			}
		}

		if ( isset( $data['generation_mode'] ) ) {
			$valid_generation_modes = [ 'fixed', 'dynamic' ];
			if ( ! in_array( $data['generation_mode'], $valid_generation_modes, true ) ) {
				$data['generation_mode'] = 'fixed';
			}
		}

		if ( isset( $data['access_mode'] ) ) {
			$valid_access_modes = [ 'default', 'guest_optional', 'guest_required', 'login_required' ];
			if ( ! in_array( $data['access_mode'], $valid_access_modes, true ) ) {
				$data['access_mode'] = 'default';
			}
		}

		// Sanitize login message
		if ( isset( $data['login_message'] ) && ! empty( $data['login_message'] ) ) {
			$data['login_message'] = wp_kses_post( $data['login_message'] );
		}

		// Validate boolean fields (ensure they're 0 or 1)
		$boolean_fields = [
			'allow_skip',
			'allow_backward',
			'allow_resume',
			'randomize_questions',
			'randomize_answers',
			'enable_confidence',
		];

		foreach ( $boolean_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = absint( $data[ $field ] ) ? 1 : 0;
			}
		}

		// Sanitize theme name
		if ( isset( $data['theme'] ) ) {
			$data['theme'] = sanitize_key( $data['theme'] );
		}

		// Validate questions_per_page
		if ( isset( $data['questions_per_page'] ) ) {
			$questions_per_page = absint( $data['questions_per_page'] );
			if ( $questions_per_page < 1 || $questions_per_page > 100 ) {
				$data['questions_per_page'] = 1;
			} else {
				$data['questions_per_page'] = $questions_per_page;
			}
		}

		$quiz_id = parent::create( $data );

		// Fire action hook for addons (e.g., audit logging).
		if ( ! is_wp_error( $quiz_id ) ) {
			/**
			 * Fires after a quiz is created.
			 *
			 * @since 2.0.0
			 *
			 * @param int   $quiz_id The quiz ID.
			 * @param array $data    The quiz data.
			 */
			do_action( 'pressprimer_quiz_quiz_created', $quiz_id, $data );
		}

		return $quiz_id;
	}

	/**
	 * Save changes to database
	 *
	 * Updates the record in the database with hook for addons.
	 *
	 * @since 2.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function save() {
		// Track old status before save to detect publish transition.
		$old_status = null;
		if ( $this->id ) {
			$existing = static::get( $this->id );
			if ( $existing ) {
				$old_status = $existing->status;
			}
		}

		$result = parent::save();

		// Fire action hook for addons (e.g., audit logging).
		if ( true === $result ) {
			/**
			 * Fires after a quiz is updated.
			 *
			 * @since 2.0.0
			 *
			 * @param PressPrimer_Quiz_Quiz $quiz The quiz instance.
			 */
			do_action( 'pressprimer_quiz_quiz_updated', $this );

			// Fire quiz_published if transitioning to published status.
			if ( 'published' === $this->status && $old_status && 'published' !== $old_status ) {
				/**
				 * Fires when a quiz is published.
				 *
				 * @since 2.0.0
				 *
				 * @param int   $quiz_id The quiz ID.
				 * @param array $data    Quiz data including old status.
				 */
				do_action(
					'pressprimer_quiz_quiz_published',
					$this->id,
					array(
						'title'      => $this->title,
						'old_status' => $old_status,
					)
				);
			}
		}

		return $result;
	}

	/**
	 * Delete quiz
	 *
	 * Removes the quiz record with hook for addons.
	 *
	 * @since 2.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete() {
		// Capture quiz data before deletion for the hook.
		$quiz_id    = $this->id;
		$quiz_title = $this->title;

		$result = parent::delete();

		// Fire action hook for addons (e.g., audit logging).
		if ( true === $result ) {
			/**
			 * Fires after a quiz is deleted.
			 *
			 * @since 2.0.0
			 *
			 * @param int    $quiz_id    The quiz ID.
			 * @param string $quiz_title The quiz title.
			 */
			do_action( 'pressprimer_quiz_quiz_deleted', $quiz_id, $quiz_title );
		}

		return $result;
	}

	/**
	 * Get quiz items
	 *
	 * Returns quiz items for fixed quizzes (ordered list of questions).
	 * Items are cached after first load unless force refresh is requested.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force_refresh Force refresh from database.
	 * @return array Array of PressPrimer_Quiz_Quiz_Item objects.
	 */
	public function get_items( bool $force_refresh = false ) {
		// Only fixed quizzes have items
		if ( 'fixed' !== $this->generation_mode ) {
			return [];
		}

		// Return cached items if available
		if ( null !== $this->_items && ! $force_refresh ) {
			return $this->_items;
		}

		global $wpdb;
		$items_table = $wpdb->prefix . 'ppq_quiz_items';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Quiz items retrieval
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$items_table} WHERE quiz_id = %d ORDER BY order_index ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->id
			)
		);

		$this->_items = [];

		if ( $rows ) {
			foreach ( $rows as $row ) {
				$this->_items[] = PressPrimer_Quiz_Quiz_Item::from_row( $row );
			}
		}

		return $this->_items;
	}

	/**
	 * Get quiz rules
	 *
	 * Returns quiz rules for dynamic quizzes (generation rules).
	 * Rules are cached after first load unless force refresh is requested.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force_refresh Force refresh from database.
	 * @return array Array of PressPrimer_Quiz_Quiz_Rule objects.
	 */
	public function get_rules( bool $force_refresh = false ) {
		// Only dynamic quizzes have rules
		if ( 'dynamic' !== $this->generation_mode ) {
			return [];
		}

		// Return cached rules if available
		if ( null !== $this->_rules && ! $force_refresh ) {
			return $this->_rules;
		}

		global $wpdb;
		$rules_table = $wpdb->prefix . 'ppq_quiz_rules';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Quiz rules retrieval
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$rules_table} WHERE quiz_id = %d ORDER BY rule_order ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->id
			)
		);

		$this->_rules = [];

		if ( $rows ) {
			foreach ( $rows as $row ) {
				$this->_rules[] = PressPrimer_Quiz_Quiz_Rule::from_row( $row );
			}
		}

		return $this->_rules;
	}

	/**
	 * Get questions for attempt
	 *
	 * Generates the question set for a quiz attempt.
	 * For fixed quizzes, returns the ordered question IDs.
	 * For dynamic quizzes, applies rules to select questions then randomizes if enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of question IDs.
	 */
	public function get_questions_for_attempt() {
		$question_ids = [];

		if ( 'fixed' === $this->generation_mode ) {
			// Fixed quiz - get questions from items
			$items = $this->get_items();

			foreach ( $items as $item ) {
				$question_ids[] = $item->question_id;
			}

			// Apply randomization if enabled
			if ( $this->randomize_questions ) {
				shuffle( $question_ids );
			}
		} else {
			// Dynamic quiz - generate from rules
			$rules            = $this->get_rules();
			$all_question_ids = [];

			foreach ( $rules as $rule ) {
				$matching_ids = $rule->get_matching_questions();

				// Shuffle and take requested count
				shuffle( $matching_ids );
				$selected = array_slice( $matching_ids, 0, $rule->question_count );

				$all_question_ids = array_merge( $all_question_ids, $selected );
			}

			// Remove duplicates (if same question matched multiple rules)
			$question_ids = array_unique( $all_question_ids );

			// Randomize if enabled
			if ( $this->randomize_questions ) {
				shuffle( $question_ids );
			}
		}

		return $question_ids;
	}

	/**
	 * Duplicate quiz
	 *
	 * Creates a copy of this quiz with all settings, items, and rules.
	 * New quiz is set to draft status and owned by current user.
	 * Uses transaction to ensure all-or-nothing operation.
	 *
	 * @since 1.0.0
	 *
	 * @return PressPrimer_Quiz_Quiz|WP_Error New quiz instance or WP_Error on failure.
	 */
	public function duplicate() {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			// Create new quiz with copied settings
			$new_quiz_data = [
				'uuid'                  => wp_generate_uuid4(),
				'title'                 => $this->title . ' ' . __( '(Copy)', 'pressprimer-quiz' ),
				'description'           => $this->description,
				'featured_image_id'     => $this->featured_image_id,
				'owner_id'              => get_current_user_id(),
				'status'                => 'draft',
				'mode'                  => $this->mode,
				'time_limit_seconds'    => $this->time_limit_seconds,
				'pass_percent'          => $this->pass_percent,
				'allow_skip'            => $this->allow_skip,
				'allow_backward'        => $this->allow_backward,
				'allow_resume'          => $this->allow_resume,
				'max_attempts'          => $this->max_attempts,
				'attempt_delay_minutes' => $this->attempt_delay_minutes,
				'randomize_questions'   => $this->randomize_questions,
				'randomize_answers'     => $this->randomize_answers,
				'page_mode'             => $this->page_mode,
				'questions_per_page'    => $this->questions_per_page,
				'show_answers'          => $this->show_answers,
				'enable_confidence'     => $this->enable_confidence,
				'theme'                 => $this->theme,
				'theme_settings_json'   => $this->theme_settings_json,
				'band_feedback_json'    => $this->band_feedback_json,
				'generation_mode'       => $this->generation_mode,
				'access_mode'           => $this->access_mode,
				'login_message'         => $this->login_message,
				'display_density'       => $this->display_density,
			];

			$new_quiz_id = self::create( $new_quiz_data );

			if ( is_wp_error( $new_quiz_id ) ) {
				throw new Exception( $new_quiz_id->get_error_message() );
			}

			$new_quiz = self::get( $new_quiz_id );

			if ( ! $new_quiz ) {
				throw new Exception( __( 'Failed to load duplicated quiz.', 'pressprimer-quiz' ) );
			}

			// Duplicate items or rules based on generation mode
			if ( 'fixed' === $this->generation_mode ) {
				// Duplicate quiz items
				$items = $this->get_items();

				foreach ( $items as $item ) {
					$item_data = [
						'quiz_id'     => $new_quiz->id,
						'question_id' => $item->question_id,
						'order_index' => $item->order_index,
						'weight'      => $item->weight,
					];

					$result = PressPrimer_Quiz_Quiz_Item::create( $item_data );

					if ( is_wp_error( $result ) ) {
						throw new Exception( $result->get_error_message() );
					}
				}
			} else {
				// Duplicate quiz rules
				$rules = $this->get_rules();

				foreach ( $rules as $rule ) {
					$rule_data = [
						'quiz_id'           => $new_quiz->id,
						'rule_order'        => $rule->rule_order,
						'bank_id'           => $rule->bank_id,
						'category_ids_json' => $rule->category_ids_json,
						'tag_ids_json'      => $rule->tag_ids_json,
						'difficulties_json' => $rule->difficulties_json,
						'question_count'    => $rule->question_count,
					];

					$result = PressPrimer_Quiz_Quiz_Rule::create( $rule_data );

					if ( is_wp_error( $result ) ) {
						throw new Exception( $result->get_error_message() );
					}
				}
			}

			$wpdb->query( 'COMMIT' );

			return $new_quiz;

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			return new WP_Error(
				'ppq_duplicate_failed',
				$e->getMessage()
			);
		}
	}

	/**
	 * Get feedback for score
	 *
	 * Returns the appropriate feedback message for a given score percentage.
	 * Searches through score bands to find matching range.
	 *
	 * @since 1.0.0
	 *
	 * @param float $score_percent Score as percentage (0-100).
	 * @return string Feedback message HTML or empty string if no match.
	 */
	public function get_feedback_for_score( float $score_percent ) {
		if ( empty( $this->band_feedback_json ) ) {
			return '';
		}

		$bands = json_decode( $this->band_feedback_json, true );

		if ( ! is_array( $bands ) ) {
			return '';
		}

		// Find the matching band
		foreach ( $bands as $band ) {
			if ( $score_percent >= $band['min'] && $score_percent <= $band['max'] ) {
				return $band['message'];
			}
		}

		return '';
	}

	/**
	 * Get effective access mode for this quiz
	 *
	 * Returns the access mode to use, falling back to global setting
	 * if quiz is set to 'default'.
	 *
	 * @since 2.0.0
	 *
	 * @return string Access mode (guest_optional, guest_required, login_required).
	 */
	public function get_effective_access_mode() {
		if ( $this->access_mode && 'default' !== $this->access_mode ) {
			return $this->access_mode;
		}

		$settings = get_option( 'pressprimer_quiz_settings', array() );

		return isset( $settings['default_access_mode'] )
			? $settings['default_access_mode']
			: 'guest_optional';
	}

	/**
	 * Get login message for this quiz
	 *
	 * Returns the custom login message if set, otherwise falls back
	 * to global default.
	 *
	 * @since 2.0.0
	 *
	 * @return string Login message.
	 */
	public function get_login_message() {
		if ( ! empty( $this->login_message ) ) {
			return $this->login_message;
		}

		$settings = get_option( 'pressprimer_quiz_settings', array() );

		return isset( $settings['login_message_default'] )
			? $settings['login_message_default']
			: __( 'Please log in to take this quiz.', 'pressprimer-quiz' );
	}

	/**
	 * Check if current user can access this quiz
	 *
	 * Returns true if the user has permission to take this quiz
	 * based on access mode settings.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if user can access, false otherwise.
	 */
	public function can_user_access() {
		$access_mode = $this->get_effective_access_mode();

		if ( 'login_required' === $access_mode ) {
			return is_user_logged_in();
		}

		return true;
	}

	/**
	 * Check if email is required for guests
	 *
	 * Returns true if guests must provide an email to take the quiz.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if email is required, false otherwise.
	 */
	public function is_guest_email_required() {
		$access_mode = $this->get_effective_access_mode();

		return 'guest_required' === $access_mode;
	}

	/**
	 * Get effective display density for this quiz
	 *
	 * Returns the display density to use, accounting for per-quiz override
	 * and global default setting.
	 *
	 * @since 2.0.0
	 *
	 * @return string Display density: 'standard' or 'condensed'.
	 */
	public function get_effective_display_density() {
		// If quiz has specific setting (not 'default'), use it
		if ( $this->display_density && 'default' !== $this->display_density ) {
			return $this->display_density;
		}

		// Fall back to global setting
		$settings = get_option( 'pressprimer_quiz_settings', [] );

		return isset( $settings['display_density'] ) ? $settings['display_density'] : 'standard';
	}
}
