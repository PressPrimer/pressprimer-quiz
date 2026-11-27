<?php
/**
 * Onboarding Wizard State Management
 *
 * The guided first-quiz tour (3.2): an admin builds a real quiz in the real
 * screens, guided step by step. This class owns the tour gate, the per-user
 * state (the current step and the run's records), the progress handlers,
 * and the one-time redirect after a fresh install.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PPQ Onboarding Class
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Onboarding {

	/**
	 * User meta key for onboarding completion status.
	 *
	 * Closing the tour also sets it, so the tour doesn't reopen until
	 * relaunched.
	 *
	 * @var string
	 */
	const META_COMPLETED = 'pressprimer_quiz_onboarding_completed';

	/**
	 * User meta key for permanent skip status ("Don't show this again").
	 *
	 * @var string
	 */
	const META_SKIPPED = 'pressprimer_quiz_onboarding_skipped';

	/**
	 * User meta key for the 1.0 tour's numeric step.
	 *
	 * Ignored since 3.2 and deleted on reset.
	 *
	 * @var string
	 */
	const META_STEP = 'pressprimer_quiz_onboarding_step';

	/**
	 * User meta key for started timestamp.
	 *
	 * @var string
	 */
	const META_STARTED = 'pressprimer_quiz_onboarding_started';

	/**
	 * User meta key for the current step id.
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const META_STEP_ID = 'pressprimer_quiz_onboarding_step_id';

	/**
	 * User meta key for the run's records (path, bank, questions, quiz, page).
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const META_RUN = 'pressprimer_quiz_onboarding_run';

	/**
	 * Every step id, both paths, in tour order.
	 *
	 * The manual path writes one question; the AI path creates a bank and
	 * generates questions into it. The tour's JS holds the steps themselves.
	 *
	 * @since 3.2.0
	 * @var string[]
	 */
	const STEP_IDS = array(
		'welcome',
		'write-question',
		'create-bank',
		'generate',
		'template',
		'name-save',
		'add-questions',
		'page',
		'results',
		'complete',
	);

	/**
	 * The question paths.
	 *
	 * @since 3.2.0
	 * @var string[]
	 */
	const PATHS = array( 'manual', 'ai' );

	/**
	 * Nonce action for the progress handlers.
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const NONCE_ACTION = 'pressprimer_quiz_onboarding';

	/**
	 * Transient: the user who activated a fresh install (5 minutes).
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const REDIRECT_TRANSIENT = 'pressprimer_quiz_setup_redirect';

	/**
	 * Singleton instance.
	 *
	 * @var PressPrimer_Quiz_Onboarding
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return PressPrimer_Quiz_Onboarding
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// AJAX handlers.
		add_action( 'wp_ajax_pressprimer_quiz_onboarding_progress', array( $this, 'ajax_onboarding_progress' ) );
		add_action( 'wp_ajax_pressprimer_quiz_get_onboarding_state', array( $this, 'ajax_get_onboarding_state' ) );

		// The one-time redirect after a fresh install.
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_setup' ) );

		// One ask at a time: the review notice waits while a tour is in progress.
		add_filter( 'pressprimer_quiz_review_prompt_due', array( $this, 'hold_review_prompt' ), 10, 2 );
	}

	/**
	 * Whether a user can use the tour.
	 *
	 * The tour builds and publishes a quiz and a page, so it's for site
	 * admins: teachers never load it.
	 *
	 * @since 3.2.0
	 *
	 * @param int|null $user_id Optional. User ID. Default current user.
	 * @return bool
	 */
	public static function user_can_use_tour( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		return $user_id
			&& user_can( $user_id, 'manage_options' )
			&& user_can( $user_id, 'pressprimer_quiz_manage_all' );
	}

	/**
	 * Whether a user's tour is in progress (started, not completed or skipped).
	 *
	 * @since 3.2.0
	 *
	 * @param int|null $user_id Optional. User ID. Default current user.
	 * @return bool
	 */
	public function is_tour_in_progress( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		return (int) get_user_meta( $user_id, self::META_STARTED, true ) > 0
			&& ! get_user_meta( $user_id, self::META_COMPLETED, true )
			&& ! get_user_meta( $user_id, self::META_SKIPPED, true );
	}

	/**
	 * Hold the review notice while the user's tour is in progress.
	 *
	 * @since 3.2.0
	 *
	 * @param bool $due     Whether the review notice is due.
	 * @param int  $user_id User ID.
	 * @return bool
	 */
	public function hold_review_prompt( $due, $user_id ) {
		return $due && ! $this->is_tour_in_progress( (int) $user_id );
	}

	/**
	 * Check if onboarding should be shown for the current user.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $user_id Optional. User ID. Default current user.
	 * @return bool True if onboarding should be shown.
	 */
	public function should_show_onboarding( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! self::user_can_use_tour( $user_id ) ) {
			return false;
		}

		// Check if completed (or closed).
		$completed = get_user_meta( $user_id, self::META_COMPLETED, true );
		if ( $completed ) {
			return false;
		}

		// Check if permanently skipped.
		$skipped = get_user_meta( $user_id, self::META_SKIPPED, true );
		if ( $skipped ) {
			return false;
		}

		return true;
	}

	/**
	 * Mark onboarding as completed for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $user_id Optional. User ID. Default current user.
	 * @return bool True on success.
	 */
	public function complete_onboarding( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		update_user_meta( $user_id, self::META_COMPLETED, true );
		update_user_meta( $user_id, self::META_STEP_ID, 'complete' );

		/**
		 * Fires when a user completes the onboarding wizard.
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id The user ID.
		 */
		do_action( 'pressprimer_quiz_onboarding_completed', $user_id );

		return true;
	}

	/**
	 * Skip the onboarding wizard.
	 *
	 * Closing the tour skips it for now: it doesn't reopen until relaunched.
	 * "Don't show this again" is permanent.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $user_id   Optional. User ID. Default current user.
	 * @param bool     $permanent Whether to permanently skip (don't show again).
	 * @return bool True on success.
	 */
	public function skip_onboarding( $user_id = null, $permanent = false ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( $permanent ) {
			update_user_meta( $user_id, self::META_SKIPPED, true );
		}

		// Mark as completed so the tour doesn't reopen.
		update_user_meta( $user_id, self::META_COMPLETED, true );

		/**
		 * Fires when a user skips the onboarding wizard.
		 *
		 * @since 1.0.0
		 *
		 * @param int  $user_id   The user ID.
		 * @param bool $permanent Whether the skip is permanent.
		 */
		do_action( 'pressprimer_quiz_onboarding_skipped', $user_id, $permanent );

		return true;
	}

	/**
	 * Reset onboarding state for a user.
	 *
	 * Allows the user to go through onboarding again, from the first step
	 * with a fresh run.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $user_id Optional. User ID. Default current user.
	 * @return bool True on success.
	 */
	public function reset_onboarding( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		delete_user_meta( $user_id, self::META_COMPLETED );
		delete_user_meta( $user_id, self::META_SKIPPED );
		delete_user_meta( $user_id, self::META_STEP );
		delete_user_meta( $user_id, self::META_STARTED );
		delete_user_meta( $user_id, self::META_STEP_ID );
		delete_user_meta( $user_id, self::META_RUN );

		/**
		 * Fires when onboarding state is reset for a user.
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id The user ID.
		 */
		do_action( 'pressprimer_quiz_onboarding_reset', $user_id );

		return true;
	}

	/**
	 * Get the current onboarding state for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $user_id Optional. User ID. Default current user.
	 * @return array Onboarding state data.
	 */
	public function get_onboarding_state( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		return array(
			'should_show' => $this->should_show_onboarding( $user_id ),
			'step_id'     => $this->get_step_id( $user_id ),
			'completed'   => (bool) get_user_meta( $user_id, self::META_COMPLETED, true ),
			'skipped'     => (bool) get_user_meta( $user_id, self::META_SKIPPED, true ),
			'started'     => (int) get_user_meta( $user_id, self::META_STARTED, true ),
			'run'         => $this->get_run( $user_id ),
		);
	}

	/**
	 * Get the user's current step id.
	 *
	 * @since 3.2.0
	 *
	 * @param int|null $user_id Optional. User ID. Default current user.
	 * @return string A step id; 'welcome' when none is stored.
	 */
	public function get_step_id( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$step_id = (string) get_user_meta( $user_id, self::META_STEP_ID, true );

		return in_array( $step_id, self::STEP_IDS, true ) ? $step_id : 'welcome';
	}

	/**
	 * Set the user's current step.
	 *
	 * @since 3.2.0
	 *
	 * @param string   $step_id Step id; unknown ids are ignored.
	 * @param int|null $user_id Optional. User ID. Default current user.
	 * @return bool True when the step was stored.
	 */
	public function update_step_id( $step_id, $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! in_array( $step_id, self::STEP_IDS, true ) ) {
			return false;
		}

		update_user_meta( $user_id, self::META_STEP_ID, $step_id );

		return true;
	}

	/**
	 * Get the run's records, validated.
	 *
	 * Each record must still exist, belong to the user and not be deleted,
	 * or it reads as empty: the run survives a new tab and a later session,
	 * and the tour can tell when an object it made has gone.
	 *
	 * @since 3.2.0
	 *
	 * @param int|null $user_id Optional. User ID. Default current user.
	 * @return array { path: string, bank_id: int, question_ids: int[], quiz_id: int, page_id: int }
	 */
	public function get_run( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$user_id = (int) $user_id;
		$run     = self::sanitize_run( get_user_meta( $user_id, self::META_RUN, true ) );

		if ( $run['bank_id'] && ! self::owns_bank( $run['bank_id'], $user_id ) ) {
			$run['bank_id'] = 0;
		}

		$run['question_ids'] = array_values(
			array_filter(
				$run['question_ids'],
				function ( $question_id ) use ( $user_id ) {
					return self::owns_question( $question_id, $user_id );
				}
			)
		);

		if ( $run['quiz_id'] && ! self::owns_quiz( $run['quiz_id'], $user_id ) ) {
			$run['quiz_id'] = 0;
		}

		if ( $run['page_id'] && ! self::owns_page( $run['page_id'], $user_id ) ) {
			$run['page_id'] = 0;
		}

		return $run;
	}

	/**
	 * Merge fields into the run.
	 *
	 * @since 3.2.0
	 *
	 * @param array    $changes Run fields to set (sanitized here).
	 * @param int|null $user_id Optional. User ID. Default current user.
	 * @return array The run as stored.
	 */
	public function update_run( $changes, $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$changes = is_array( $changes ) ? $changes : array();
		$run     = $this->get_run( $user_id );

		foreach ( self::sanitize_run( $changes ) as $key => $value ) {
			if ( array_key_exists( $key, $changes ) ) {
				$run[ $key ] = $value;
			}
		}

		update_user_meta( $user_id, self::META_RUN, $run );

		return $run;
	}

	/**
	 * Normalize a run record.
	 *
	 * @since 3.2.0
	 *
	 * @param mixed $raw Raw run.
	 * @return array Every field present, each its own type.
	 */
	public static function sanitize_run( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();

		$path         = isset( $raw['path'] ) ? sanitize_key( $raw['path'] ) : '';
		$question_ids = isset( $raw['question_ids'] ) && is_array( $raw['question_ids'] )
			? array_values( array_unique( array_filter( array_map( 'absint', $raw['question_ids'] ) ) ) )
			: array();

		return array(
			'path'         => in_array( $path, self::PATHS, true ) ? $path : '',
			'bank_id'      => isset( $raw['bank_id'] ) ? absint( $raw['bank_id'] ) : 0,
			'question_ids' => array_slice( $question_ids, 0, 100 ),
			'quiz_id'      => isset( $raw['quiz_id'] ) ? absint( $raw['quiz_id'] ) : 0,
			'page_id'      => isset( $raw['page_id'] ) ? absint( $raw['page_id'] ) : 0,
		);
	}

	/**
	 * Whether a bank exists, isn't deleted and belongs to the user.
	 *
	 * @param int $bank_id Bank ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function owns_bank( $bank_id, $user_id ) {
		$bank = PressPrimer_Quiz_Bank::get( $bank_id );

		return $bank && empty( $bank->deleted_at ) && (int) $bank->owner_id === $user_id;
	}

	/**
	 * Whether a question exists, isn't deleted and belongs to the user.
	 *
	 * @param int $question_id Question ID.
	 * @param int $user_id     User ID.
	 * @return bool
	 */
	private static function owns_question( $question_id, $user_id ) {
		$question = PressPrimer_Quiz_Question::get( $question_id );

		return $question && empty( $question->deleted_at ) && (int) $question->author_id === $user_id;
	}

	/**
	 * Whether a quiz exists and belongs to the user (deleting a quiz removes it).
	 *
	 * @param int $quiz_id Quiz ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function owns_quiz( $quiz_id, $user_id ) {
		$quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );

		return $quiz && (int) $quiz->owner_id === $user_id;
	}

	/**
	 * Whether a page exists, isn't trashed and belongs to the user.
	 *
	 * @param int $page_id Page ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function owns_page( $page_id, $user_id ) {
		$page = get_post( $page_id );

		return $page
			&& 'page' === $page->post_type
			&& 'trash' !== $page->post_status
			&& (int) $page->post_author === $user_id;
	}

	/**
	 * Start the onboarding wizard for a user.
	 *
	 * A start is a fresh run: the previous run's records are cleared.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $user_id Optional. User ID. Default current user.
	 * @return bool True on success.
	 */
	public function start_onboarding( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		update_user_meta( $user_id, self::META_STARTED, time() );
		update_user_meta( $user_id, self::META_STEP_ID, 'welcome' );
		delete_user_meta( $user_id, self::META_RUN );
		delete_user_meta( $user_id, self::META_STEP );

		// Clear any previous completion/skip state for relaunch.
		delete_user_meta( $user_id, self::META_COMPLETED );
		delete_user_meta( $user_id, self::META_SKIPPED );

		/**
		 * Fires when a user starts the onboarding wizard.
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id The user ID.
		 */
		do_action( 'pressprimer_quiz_onboarding_started', $user_id );

		return true;
	}

	/**
	 * AJAX handler for onboarding progress updates.
	 *
	 * Actions: start, progress (a step and/or run fields), complete, skip,
	 * reset. 'start' and 'progress' take the landing step, so it's stored
	 * before the tour navigates.
	 *
	 * @since 1.0.0
	 */
	public function ajax_onboarding_progress() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! self::user_can_use_tour() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'pressprimer-quiz' ) ), 403 );
		}

		$action  = isset( $_POST['onboarding_action'] ) ? sanitize_key( wp_unslash( $_POST['onboarding_action'] ) ) : '';
		$step_id = isset( $_POST['step_id'] ) ? sanitize_key( wp_unslash( $_POST['step_id'] ) ) : '';
		$user_id = get_current_user_id();

		// Run fields arrive as JSON; update_run() sanitizes each one.
		$run_json = isset( $_POST['run'] ) ? sanitize_text_field( wp_unslash( $_POST['run'] ) ) : '';
		$run      = '' !== $run_json ? json_decode( $run_json, true ) : null;

		switch ( $action ) {
			case 'start':
				$this->start_onboarding( $user_id );
				if ( is_array( $run ) ) {
					$this->update_run( $run, $user_id );
				}
				if ( $step_id ) {
					$this->update_step_id( $step_id, $user_id );
				}
				break;

			case 'progress':
				if ( is_array( $run ) ) {
					$this->update_run( $run, $user_id );
				}
				if ( $step_id ) {
					$this->update_step_id( $step_id, $user_id );
				}
				break;

			case 'complete':
				$this->complete_onboarding( $user_id );
				break;

			case 'skip':
				$permanent = isset( $_POST['permanent'] ) && rest_sanitize_boolean( wp_unslash( $_POST['permanent'] ) );
				$this->skip_onboarding( $user_id, $permanent );
				break;

			case 'reset':
				// Users can reset their own onboarding state to relaunch the tour.
				$this->reset_onboarding( $user_id );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Invalid action.', 'pressprimer-quiz' ) ) );
		}

		wp_send_json_success( $this->get_onboarding_state( $user_id ) );
	}

	/**
	 * AJAX handler to get current onboarding state.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_onboarding_state() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! self::user_can_use_tour() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'pressprimer-quiz' ) ), 403 );
		}

		wp_send_json_success( $this->get_onboarding_state() );
	}

	/**
	 * Flag a fresh install for the one-time redirect.
	 *
	 * Called from the activator's single-site branch with the fresh-install
	 * status read before activation ran. Never for network activation, a new
	 * network site, AJAX, cron or WP-CLI, or without a logged-in user.
	 *
	 * @since 3.2.0
	 *
	 * @param bool $is_fresh_install Whether the version option was absent.
	 */
	public static function maybe_flag_setup_redirect( $is_fresh_install ) {
		if ( ! $is_fresh_install || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		set_transient( self::REDIRECT_TRANSIENT, $user_id, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Redirect the activating user to the Dashboard, once.
	 *
	 * The tour opens there for an admin who hasn't taken it.
	 *
	 * @since 3.2.0
	 */
	public function maybe_redirect_to_setup() {
		$url = $this->get_setup_redirect_url();

		if ( $url ) {
			wp_safe_redirect( $url );
			exit;
		}
	}

	/**
	 * Whether this request should redirect to the Dashboard.
	 *
	 * Every guard must pass. The flag is deleted before redirecting, and on
	 * the guards that can never pass later for its user, so the redirect
	 * fires at most once.
	 *
	 * @since 3.2.0
	 *
	 * @return string|null Redirect URL, or null.
	 */
	private function get_setup_redirect_url() {
		if ( wp_doing_ajax() || wp_doing_cron() || is_network_admin() ) {
			return null;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return null;
		}

		$flag_user = (int) get_transient( self::REDIRECT_TRANSIENT );

		// No flag, or another user's: leave it for them (it expires on its own).
		if ( ! $flag_user || get_current_user_id() !== $flag_user ) {
			return null;
		}

		// Bulk activation: never redirect, and never retry.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only detection of the bulk-activation context.
		if ( isset( $_GET['activate-multi'] ) ) {
			delete_transient( self::REDIRECT_TRANSIENT );
			return null;
		}

		if ( ! self::user_can_use_tour() ) {
			delete_transient( self::REDIRECT_TRANSIENT );
			return null;
		}

		// Single-shot: the flag is consumed before redirecting.
		delete_transient( self::REDIRECT_TRANSIENT );

		// Reinstall guard: a site that already has quizzes never redirects.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One count, once per install.
		$quiz_count = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . 'ppq_quizzes' )
		);

		if ( $quiz_count > 0 ) {
			return null;
		}

		return admin_url( 'admin.php?page=pressprimer-quiz' );
	}

	/**
	 * Get localized data for JavaScript.
	 *
	 * @since 1.0.0
	 *
	 * @return array Localized data array.
	 */
	public function get_js_data() {
		return array(
			'state'     => $this->get_onboarding_state(),
			'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'pluginUrl' => PRESSPRIMER_QUIZ_PLUGIN_URL,
			'urls'      => array(
				'dashboard' => admin_url( 'admin.php?page=pressprimer-quiz' ),
				'questions' => admin_url( 'admin.php?page=pressprimer-quiz-questions' ),
				'banks'     => admin_url( 'admin.php?page=pressprimer-quiz-banks' ),
				'quizzes'   => admin_url( 'admin.php?page=pressprimer-quiz-quizzes' ),
				'reports'   => admin_url( 'admin.php?page=pressprimer-quiz-reports' ),
				'settings'  => admin_url( 'admin.php?page=pressprimer-quiz-settings' ),
			),
		);
	}
}
