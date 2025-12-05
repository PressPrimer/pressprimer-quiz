<?php
/**
 * Uncanny Automator Loader
 *
 * Registers PressPrimer Quiz integration with Uncanny Automator.
 *
 * @package PressPrimer_Quiz
 * @subpackage Integrations
 * @since 1.0.0
 */

namespace Jeero\PressPrimerQuiz\Integrations\UncannyAutomator;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Automator Loader Class
 *
 * Handles registration of the PPQ integration with Uncanny Automator.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Automator_Loader {

	/**
	 * Initialize the loader
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Register integration when Automator loads integrations.
		add_action( 'automator_add_integration', array( $this, 'add_integration' ) );
	}

	/**
	 * Add integration to Uncanny Automator
	 *
	 * @since 1.0.0
	 */
	public function add_integration() {
		// Include required files.
		$this->include_files();

		// Create helpers instance.
		$helpers = new PressPrimer_Quiz_Automator_Helpers();

		// Register integration.
		new PressPrimer_Quiz_Automator_Integration();

		// Register triggers with helpers dependency.
		new Triggers\PressPrimer_Quiz_Quiz_Completed( $helpers );
		new Triggers\PressPrimer_Quiz_Quiz_Passed( $helpers );
		new Triggers\PressPrimer_Quiz_Quiz_Failed( $helpers );
	}

	/**
	 * Include required files
	 *
	 * @since 1.0.0
	 */
	private function include_files() {
		$base_path = PPQ_PLUGIN_PATH . 'includes/integrations/uncanny-automator/';

		require_once $base_path . 'class-ppq-automator-integration.php';
		require_once $base_path . 'class-ppq-automator-helpers.php';
		require_once $base_path . 'triggers/class-ppq-quiz-completed.php';
		require_once $base_path . 'triggers/class-ppq-quiz-passed.php';
		require_once $base_path . 'triggers/class-ppq-quiz-failed.php';
	}
}
