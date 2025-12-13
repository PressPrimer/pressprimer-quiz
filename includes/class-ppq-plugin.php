<?php
/**
 * Main plugin class
 *
 * Coordinates the plugin initialization and component setup.
 *
 * @package PressPrimer_Quiz
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class
 *
 * Implements singleton pattern to ensure only one instance exists.
 * Initializes all plugin components on plugins_loaded hook.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Plugin {

	/**
	 * Singleton instance
	 *
	 * @since 1.0.0
	 * @var PressPrimer_Quiz_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * Returns the single instance of the plugin class.
	 * Creates the instance if it doesn't exist.
	 *
	 * @since 1.0.0
	 *
	 * @return PressPrimer_Quiz_Plugin The plugin instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor
	 *
	 * Prevents direct instantiation. Use get_instance() instead.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Constructor is private for singleton
	}

	/**
	 * Run the plugin
	 *
	 * Initializes all plugin components in the correct order.
	 * This method is called from the pressprimer_quiz_init() function.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		// Ensure capabilities are set up (handles cases where activation hook didn't run,
		// such as WordPress Playground or manual file installations)
		$this->ensure_capabilities();

		// Check and run migrations
		if ( class_exists( 'PressPrimer_Quiz_Migrator' ) ) {
			PressPrimer_Quiz_Migrator::maybe_migrate();
		}

		// Initialize components
		$this->init_admin();
		$this->init_frontend();
		$this->init_integrations();
		$this->init_rest_api();
		$this->init_blocks();
		$this->init_cron();
	}

	/**
	 * Ensure capabilities are set up
	 *
	 * Checks if plugin capabilities exist and sets them up if missing.
	 * This handles cases where the activation hook didn't run properly,
	 * such as in WordPress Playground or manual file installations.
	 *
	 * @since 1.0.0
	 */
	private function ensure_capabilities() {
		// Check if admin role has our capabilities
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( 'ppq_manage_own' ) ) {
			// Capabilities missing, set them up
			if ( class_exists( 'PressPrimer_Quiz_Capabilities' ) ) {
				PressPrimer_Quiz_Capabilities::setup_capabilities();
			}
		}
	}

	/**
	 * Initialize admin components
	 *
	 * Loads admin-only functionality when in wp-admin.
	 *
	 * @since 1.0.0
	 */
	private function init_admin() {
		if ( ! is_admin() ) {
			return;
		}

		// Initialize admin class
		if ( class_exists( 'PressPrimer_Quiz_Admin' ) ) {
			$admin = new PressPrimer_Quiz_Admin();
			$admin->init();
		}

		// Initialize onboarding
		if ( class_exists( 'PressPrimer_Quiz_Onboarding' ) ) {
			PressPrimer_Quiz_Onboarding::get_instance();
		}
	}

	/**
	 * Initialize frontend components
	 *
	 * Loads public-facing functionality (shortcodes, frontend rendering).
	 *
	 * @since 1.0.0
	 */
	private function init_frontend() {
		// Initialize shortcodes
		if ( class_exists( 'PressPrimer_Quiz_Shortcodes' ) ) {
			$shortcodes = new PressPrimer_Quiz_Shortcodes();
			$shortcodes->init();
		}

		// Initialize AJAX handlers
		if ( class_exists( 'PressPrimer_Quiz_AJAX_Handler' ) ) {
			$ajax_handler = new PressPrimer_Quiz_AJAX_Handler();
			$ajax_handler->init();
		}
	}

	/**
	 * Initialize LMS integrations
	 *
	 * Detects and initializes integrations with supported LMS plugins.
	 * Only loads integration if the corresponding LMS is active.
	 *
	 * @since 1.0.0
	 */
	private function init_integrations() {
		// LearnDash integration
		if ( defined( 'LEARNDASH_VERSION' ) ) {
			if ( class_exists( 'PressPrimer_Quiz_LearnDash' ) ) {
				$learndash = new PressPrimer_Quiz_LearnDash();
				$learndash->init();
			}
		}

		// TutorLMS integration
		if ( defined( 'TUTOR_VERSION' ) ) {
			if ( class_exists( 'PressPrimer_Quiz_TutorLMS' ) ) {
				$tutor = new PressPrimer_Quiz_TutorLMS();
				$tutor->init();
			}
		}

		// LifterLMS integration
		if ( defined( 'LLMS_PLUGIN_FILE' ) ) {
			if ( class_exists( 'PressPrimer_Quiz_LifterLMS' ) ) {
				$lifter = new PressPrimer_Quiz_LifterLMS();
				$lifter->init();
			}
		}

		// Uncanny Automator integration
		if ( class_exists( 'Uncanny_Automator\Automator_Functions' ) ) {
			require_once PPQ_PLUGIN_PATH . 'includes/integrations/uncanny-automator/class-ppq-automator-loader.php';
			$automator = new \Jeero\PressPrimerQuiz\Integrations\UncannyAutomator\PressPrimer_Quiz_Automator_Loader();
			$automator->init();
		}
	}

	/**
	 * Initialize REST API
	 *
	 * Registers REST API endpoints for quiz functionality.
	 *
	 * @since 1.0.0
	 */
	private function init_rest_api() {
		if ( class_exists( 'PressPrimer_Quiz_REST_Controller' ) ) {
			$controller = new PressPrimer_Quiz_REST_Controller();
			$controller->init();
		}
	}

	/**
	 * Initialize Gutenberg blocks
	 *
	 * Registers block types for the block editor.
	 *
	 * @since 1.0.0
	 */
	private function init_blocks() {
		if ( class_exists( 'PressPrimer_Quiz_Blocks' ) ) {
			$blocks = new PressPrimer_Quiz_Blocks();
			$blocks->init();
		}
	}

	/**
	 * Initialize cron jobs
	 *
	 * Registers cron hook callbacks and ensures cron is scheduled.
	 *
	 * @since 1.0.0
	 */
	private function init_cron() {
		// Register the cron hook for statistics recalculation
		if ( class_exists( 'PressPrimer_Quiz_Statistics_Service' ) ) {
			add_action(
				PressPrimer_Quiz_Statistics_Service::CRON_HOOK,
				[ 'PressPrimer_Quiz_Statistics_Service', 'cron_recalculate_stats' ]
			);

			// Ensure cron is scheduled (handles upgrades where activation hook didn't run)
			PressPrimer_Quiz_Statistics_Service::schedule_cron();
		}
	}
}
