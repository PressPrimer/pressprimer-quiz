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
class PPQ_Plugin {

	/**
	 * Singleton instance
	 *
	 * @since 1.0.0
	 * @var PPQ_Plugin|null
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
	 * @return PPQ_Plugin The plugin instance.
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
	 * This method is called from the ppq_init() function.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		// Check and run migrations
		if ( class_exists( 'PPQ_Migrator' ) ) {
			PPQ_Migrator::maybe_migrate();
		}

		// Initialize components
		$this->init_admin();
		$this->init_frontend();
		$this->init_integrations();
		$this->init_rest_api();
		$this->init_blocks();
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
		if ( class_exists( 'PPQ_Admin' ) ) {
			$admin = new PPQ_Admin();
			$admin->init();
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
		if ( class_exists( 'PPQ_Shortcodes' ) ) {
			$shortcodes = new PPQ_Shortcodes();
			$shortcodes->init();
		}

		// Initialize AJAX handlers
		if ( class_exists( 'PPQ_AJAX_Handler' ) ) {
			$ajax_handler = new PPQ_AJAX_Handler();
			$ajax_handler->init();
		}

		// Initialize Open Graph tags for results pages
		if ( class_exists( 'PPQ_Results_Renderer' ) ) {
			PPQ_Results_Renderer::init_og_tags();
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
			if ( class_exists( 'PPQ_LearnDash' ) ) {
				$learndash = new PPQ_LearnDash();
				$learndash->init();
			}
		}

		// TutorLMS integration
		if ( defined( 'TUTOR_VERSION' ) ) {
			if ( class_exists( 'PPQ_TutorLMS' ) ) {
				$tutor = new PPQ_TutorLMS();
				$tutor->init();
			}
		}

		// LifterLMS integration
		if ( defined( 'LLMS_PLUGIN_FILE' ) ) {
			if ( class_exists( 'PPQ_LifterLMS' ) ) {
				$lifter = new PPQ_LifterLMS();
				$lifter->init();
			}
		}

		// Uncanny Automator integration
		if ( class_exists( 'Uncanny_Automator\Automator_Functions' ) ) {
			require_once PPQ_PLUGIN_PATH . 'includes/integrations/uncanny-automator/class-ppq-automator-loader.php';
			$automator = new \Jeero\PressPrimerQuiz\Integrations\UncannyAutomator\PPQ_Automator_Loader();
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
		if ( class_exists( 'PPQ_REST_Controller' ) ) {
			$controller = new PPQ_REST_Controller();
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
		if ( class_exists( 'PPQ_Blocks' ) ) {
			$blocks = new PPQ_Blocks();
			$blocks->init();
		}
	}
}
