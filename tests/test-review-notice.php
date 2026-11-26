<?php
/**
 * Review Notice Tests
 *
 * When the 100-attempt review prompt is due: site admins only, the prompt
 * switch (white-label), the dismiss and snooze answers, the threshold, and
 * the onboarding tour's hold.
 *
 * @package PressPrimer_Quiz
 * @subpackage Tests
 * @since 3.2.0
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'pressprimer_quiz_show_prompts' ) ) {
	/**
	 * Stub: the prompt switch, on unless $GLOBALS['pressprimer_quiz_test_prompts_off'] is set.
	 *
	 * @param string $type Prompt type.
	 * @return bool
	 */
	function pressprimer_quiz_show_prompts( $type = 'upsell' ) {
		return empty( $GLOBALS['pressprimer_quiz_test_prompts_off'] );
	}
}

if ( ! function_exists( 'user_can' ) ) {
	/**
	 * Stub: capability check for any user, driven by $GLOBALS['pressprimer_quiz_test_caps'].
	 *
	 * @param int    $user_id    User ID.
	 * @param string $capability Capability.
	 * @return bool
	 */
	function user_can( $user_id, $capability ) {
		return in_array( $capability, isset( $GLOBALS['pressprimer_quiz_test_caps'] ) ? (array) $GLOBALS['pressprimer_quiz_test_caps'] : array(), true );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Stub: options from $GLOBALS['pressprimer_quiz_test_options'].
	 *
	 * @param string $option        Option name.
	 * @param mixed  $default_value Default.
	 * @return mixed
	 */
	function get_option( $option, $default_value = false ) {
		return isset( $GLOBALS['pressprimer_quiz_test_options'][ $option ] ) ? $GLOBALS['pressprimer_quiz_test_options'][ $option ] : $default_value;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Stub: transients from $GLOBALS['pressprimer_quiz_test_transients'].
	 *
	 * @param string $transient Transient name.
	 * @return mixed
	 */
	function get_transient( $transient ) {
		return isset( $GLOBALS['pressprimer_quiz_test_transients'][ $transient ] ) ? $GLOBALS['pressprimer_quiz_test_transients'][ $transient ] : false;
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	/**
	 * Stub: user meta from $GLOBALS['pressprimer_quiz_test_user_meta'][ $user_id ].
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Unused.
	 * @return mixed
	 */
	function get_user_meta( $user_id, $key = '', $single = false ) {
		return isset( $GLOBALS['pressprimer_quiz_test_user_meta'][ $user_id ][ $key ] ) ? $GLOBALS['pressprimer_quiz_test_user_meta'][ $user_id ][ $key ] : '';
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Stub: hooks are not run in unit tests.
	 *
	 * @return true
	 */
	function add_action() {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Stub: hooks are not run in unit tests.
	 *
	 * @return true
	 */
	function add_filter() {
		return true;
	}
}

/**
 * Review notice test case.
 *
 * @since 3.2.0
 */
class Test_Review_Notice extends TestCase {

	/**
	 * An admin, prompts on, never answered, at the threshold.
	 */
	protected function setUp(): void {
		$GLOBALS['pressprimer_quiz_test_caps']       = array( 'manage_options' );
		$GLOBALS['pressprimer_quiz_test_options']    = array();
		$GLOBALS['pressprimer_quiz_test_user_meta']  = array();
		$GLOBALS['pressprimer_quiz_test_transients'] = array(
			PressPrimer_Quiz_Review_Notice::COUNT_TRANSIENT => PressPrimer_Quiz_Review_Notice::ATTEMPT_THRESHOLD,
		);
	}

	/**
	 * Clean up the globals.
	 */
	protected function tearDown(): void {
		unset(
			$GLOBALS['pressprimer_quiz_test_caps'],
			$GLOBALS['pressprimer_quiz_test_options'],
			$GLOBALS['pressprimer_quiz_test_user_meta'],
			$GLOBALS['pressprimer_quiz_test_transients'],
			$GLOBALS['pressprimer_quiz_test_prompts_off']
		);
	}

	/**
	 * Due from the threshold up, not below it.
	 */
	public function test_due_at_the_threshold() {
		$this->assertTrue( PressPrimer_Quiz_Review_Notice::is_due( 1 ) );

		$GLOBALS['pressprimer_quiz_test_transients'][ PressPrimer_Quiz_Review_Notice::COUNT_TRANSIENT ] = PressPrimer_Quiz_Review_Notice::ATTEMPT_THRESHOLD - 1;
		$this->assertFalse( PressPrimer_Quiz_Review_Notice::is_due( 1 ) );
	}

	/**
	 * Site admins only; no user, no prompt.
	 */
	public function test_site_admins_only() {
		$this->assertFalse( PressPrimer_Quiz_Review_Notice::is_due( 0 ) );

		$GLOBALS['pressprimer_quiz_test_caps'] = array( 'pressprimer_quiz_manage_settings', 'pressprimer_quiz_manage_all' );
		$this->assertFalse( PressPrimer_Quiz_Review_Notice::is_due( 1 ), 'Plugin capabilities alone are not enough' );
	}

	/**
	 * The prompt switch (off under white-label) hides it.
	 */
	public function test_prompt_switch_hides_it() {
		$GLOBALS['pressprimer_quiz_test_prompts_off'] = true;

		$this->assertFalse( PressPrimer_Quiz_Review_Notice::is_due( 1 ) );
	}

	/**
	 * Answered or closed, it never returns; snoozed, it returns after the date.
	 */
	public function test_answers_and_snooze() {
		$GLOBALS['pressprimer_quiz_test_options'] = array( 'pressprimer_quiz_celebration_dismissed' => true );
		$this->assertFalse( PressPrimer_Quiz_Review_Notice::is_due( 1 ), 'Dismissed' );

		$GLOBALS['pressprimer_quiz_test_options'] = array( 'pressprimer_quiz_celebration_snoozed_until' => time() + 86400 );
		$this->assertFalse( PressPrimer_Quiz_Review_Notice::is_due( 1 ), 'Snoozed' );

		$GLOBALS['pressprimer_quiz_test_options'] = array( 'pressprimer_quiz_celebration_snoozed_until' => time() - 1 );
		$this->assertTrue( PressPrimer_Quiz_Review_Notice::is_due( 1 ), 'Snooze over' );
	}

	/**
	 * The tour holds it while in progress (started, not completed or skipped).
	 */
	public function test_tour_in_progress_holds_it() {
		$onboarding = PressPrimer_Quiz_Onboarding::get_instance();
		$started    = array( PressPrimer_Quiz_Onboarding::META_STARTED => time() );

		$states = array(
			'never started' => array( array(), true ),
			'in progress'   => array( $started, false ),
			'completed'     => array( $started + array( PressPrimer_Quiz_Onboarding::META_COMPLETED => true ), true ),
			'skipped'       => array( $started + array( PressPrimer_Quiz_Onboarding::META_SKIPPED => true ), true ),
		);

		foreach ( $states as $label => $state ) {
			$GLOBALS['pressprimer_quiz_test_user_meta'] = array( 7 => $state[0] );

			$this->assertSame( $state[1], $onboarding->hold_review_prompt( true, 7 ), $label );
			$this->assertFalse( $onboarding->hold_review_prompt( false, 7 ), $label . ': never makes it due' );
		}
	}
}
