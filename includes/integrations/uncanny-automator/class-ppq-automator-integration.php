<?php
/**
 * Uncanny Automator Integration
 *
 * Registers PressPrimer Quiz as an integration with Uncanny Automator,
 * providing triggers for quiz events.
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
 * PressPrimer Quiz Integration for Uncanny Automator
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Automator_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup the integration
	 *
	 * @since 1.0.0
	 */
	protected function setup() {
		$this->set_integration( 'PPQ' );
		$this->set_name( 'PressPrimer Quiz' );
		$this->set_icon_url( PPQ_PLUGIN_URL . 'assets/images/checkbox.svg' );
	}
}
