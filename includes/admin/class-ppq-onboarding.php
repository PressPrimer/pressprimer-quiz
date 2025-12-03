<?php
/**
 * Onboarding Wizard State Management
 *
 * Handles the onboarding wizard state for new users including
 * tracking completion, skipping, and current step progress.
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
class PPQ_Onboarding {

	/**
	 * User meta key for onboarding completion status.
	 *
	 * @var string
	 */
	const META_COMPLETED = 'ppq_onboarding_completed';

	/**
	 * User meta key for permanent skip status.
	 *
	 * @var string
	 */
	const META_SKIPPED = 'ppq_onboarding_skipped';

	/**
	 * User meta key for current step.
	 *
	 * @var string
	 */
	const META_STEP = 'ppq_onboarding_step';

	/**
	 * User meta key for started timestamp.
	 *
	 * @var string
	 */
	const META_STARTED = 'ppq_onboarding_started';

	/**
	 * Total number of wizard steps.
	 *
	 * @var int
	 */
	const TOTAL_STEPS = 8;

	/**
	 * Singleton instance.
	 *
	 * @var PPQ_Onboarding
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return PPQ_Onboarding
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
		add_action( 'wp_ajax_ppq_onboarding_progress', array( $this, 'ajax_onboarding_progress' ) );
		add_action( 'wp_ajax_ppq_get_onboarding_state', array( $this, 'ajax_get_onboarding_state' ) );
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

		// Must have appropriate capability.
		if ( ! user_can( $user_id, 'ppq_manage_own' ) ) {
			return false;
		}

		// Check if completed.
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
		update_user_meta( $user_id, self::META_STEP, self::TOTAL_STEPS );

		/**
		 * Fires when a user completes the onboarding wizard.
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id The user ID.
		 */
		do_action( 'ppq_onboarding_completed', $user_id );

		return true;
	}

	/**
	 * Skip the onboarding wizard.
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

		// Mark as completed for this session.
		update_user_meta( $user_id, self::META_COMPLETED, true );

		/**
		 * Fires when a user skips the onboarding wizard.
		 *
		 * @since 1.0.0
		 *
		 * @param int  $user_id   The user ID.
		 * @param bool $permanent Whether the skip is permanent.
		 */
		do_action( 'ppq_onboarding_skipped', $user_id, $permanent );

		return true;
	}

	/**
	 * Reset onboarding state for a user.
	 *
	 * Allows the user to go through onboarding again.
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

		/**
		 * Fires when onboarding state is reset for a user.
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id The user ID.
		 */
		do_action( 'ppq_onboarding_reset', $user_id );

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

		$step = (int) get_user_meta( $user_id, self::META_STEP, true );

		return array(
			'should_show' => $this->should_show_onboarding( $user_id ),
			'step'        => $step > 0 ? $step : 1,
			'total_steps' => self::TOTAL_STEPS,
			'completed'   => (bool) get_user_meta( $user_id, self::META_COMPLETED, true ),
			'skipped'     => (bool) get_user_meta( $user_id, self::META_SKIPPED, true ),
			'started'     => (int) get_user_meta( $user_id, self::META_STARTED, true ),
			'has_api_key' => $this->has_valid_api_key(),
		);
	}

	/**
	 * Update the current step for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $step    Step number (1-7).
	 * @param int|null $user_id Optional. User ID. Default current user.
	 * @return bool True on success.
	 */
	public function update_step( $step, $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		// Validate step number.
		$step = max( 1, min( self::TOTAL_STEPS, (int) $step ) );

		update_user_meta( $user_id, self::META_STEP, $step );

		return true;
	}

	/**
	 * Start the onboarding wizard for a user.
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
		update_user_meta( $user_id, self::META_STEP, 1 );

		// Clear any previous completion/skip state for relaunch.
		delete_user_meta( $user_id, self::META_COMPLETED );

		/**
		 * Fires when a user starts the onboarding wizard.
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id The user ID.
		 */
		do_action( 'ppq_onboarding_started', $user_id );

		return true;
	}

	/**
	 * Check if the user has a valid OpenAI API key configured.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if API key is configured.
	 */
	private function has_valid_api_key() {
		$api_key = get_option( 'ppq_openai_api_key', '' );
		return ! empty( $api_key );
	}

	/**
	 * AJAX handler for onboarding progress updates.
	 *
	 * @since 1.0.0
	 */
	public function ajax_onboarding_progress() {
		check_ajax_referer( 'ppq_admin', 'nonce' );

		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'pressprimer-quiz' ) ) );
		}

		$action  = isset( $_POST['onboarding_action'] ) ? sanitize_text_field( wp_unslash( $_POST['onboarding_action'] ) ) : '';
		$step    = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 0;
		$user_id = get_current_user_id();

		switch ( $action ) {
			case 'start':
				$this->start_onboarding( $user_id );
				break;

			case 'progress':
				$this->update_step( $step, $user_id );
				break;

			case 'complete':
				$this->complete_onboarding( $user_id );
				break;

			case 'skip':
				$permanent = ! empty( $_POST['permanent'] );
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
		check_ajax_referer( 'ppq_admin', 'nonce' );

		if ( ! current_user_can( 'ppq_manage_own' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'pressprimer-quiz' ) ) );
		}

		wp_send_json_success( $this->get_onboarding_state() );
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
			'state'   => $this->get_onboarding_state(),
			'nonce'   => wp_create_nonce( 'ppq_admin' ),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'urls'    => array(
				'dashboard' => admin_url( 'admin.php?page=ppq-dashboard' ),
				'questions' => admin_url( 'admin.php?page=ppq-questions' ),
				'banks'     => admin_url( 'admin.php?page=ppq-banks' ),
				'quizzes'   => admin_url( 'admin.php?page=ppq-quizzes' ),
				'reports'   => admin_url( 'admin.php?page=ppq-reports' ),
				'settings'  => admin_url( 'admin.php?page=ppq-settings' ),
			),
			'i18n'    => array(
				'welcome'          => __( 'Welcome to PressPrimer Quiz!', 'pressprimer-quiz' ),
				'welcomeSubtitle'  => __( 'Create powerful quizzes for your WordPress site in minutes. Let us show you around!', 'pressprimer-quiz' ),
				'getStarted'       => __( 'Get Started', 'pressprimer-quiz' ),
				'skipForNow'       => __( 'Skip for Now', 'pressprimer-quiz' ),
				'dontShowAgain'    => __( "Don't show this again", 'pressprimer-quiz' ),
				'back'             => __( 'Back', 'pressprimer-quiz' ),
				'next'             => __( 'Next', 'pressprimer-quiz' ),
				'skip'             => __( 'Skip', 'pressprimer-quiz' ),
				'finish'           => __( 'Finish', 'pressprimer-quiz' ),
				'stepOf'           => __( 'Step %1$d of %2$d', 'pressprimer-quiz' ),
				'overviewTitle'    => __( 'How PressPrimer Quiz Works', 'pressprimer-quiz' ),
				'overviewDesc'     => __( 'PressPrimer Quiz follows a simple workflow: create questions, organize them into banks, build quizzes, and track results.', 'pressprimer-quiz' ),
				'questionsTitle'   => __( 'Create Questions', 'pressprimer-quiz' ),
				'questionsDesc'    => __( 'Start by creating questions. Choose from multiple choice, multiple answer, or true/false formats.', 'pressprimer-quiz' ),
				'banksTitle'       => __( 'Organize with Banks', 'pressprimer-quiz' ),
				'banksDesc'        => __( 'Group related questions into banks. This makes it easy to build dynamic quizzes and reuse questions.', 'pressprimer-quiz' ),
				'quizBuilderTitle' => __( 'Build Quizzes', 'pressprimer-quiz' ),
				'quizBuilderDesc'  => __( 'Create quizzes by selecting specific questions or using rules to pull from your banks.', 'pressprimer-quiz' ),
				'aiTitle'          => __( 'AI Question Generation', 'pressprimer-quiz' ),
				'aiDesc'           => __( 'Generate questions automatically from your content using AI. Just paste text or upload a document.', 'pressprimer-quiz' ),
				'aiNotConfigured'  => __( 'Configure your OpenAI API key in Settings to enable AI generation.', 'pressprimer-quiz' ),
				'reportsTitle'     => __( 'Track Results', 'pressprimer-quiz' ),
				'reportsDesc'      => __( 'Monitor quiz performance, view student attempts, and identify areas for improvement.', 'pressprimer-quiz' ),
				'completionTitle'  => __( "You're All Set!", 'pressprimer-quiz' ),
				'completionDesc'   => __( "You've learned the basics of PressPrimer Quiz. Here's what you can do next:", 'pressprimer-quiz' ),
				'createFirstQuiz'  => __( 'Create Your First Quiz', 'pressprimer-quiz' ),
				'addQuestions'     => __( 'Add Questions', 'pressprimer-quiz' ),
				'exploreSettings'  => __( 'Explore Settings', 'pressprimer-quiz' ),
				'viewDocs'         => __( 'View Documentation', 'pressprimer-quiz' ),
			),
		);
	}
}
