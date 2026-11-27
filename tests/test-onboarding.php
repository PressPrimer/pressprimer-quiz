<?php
/**
 * Onboarding Tests
 *
 * The tour gate (site admins only), the step id (validated against the
 * list) and the run record's sanitizing. Run validation against real
 * records is covered by the live check.
 *
 * @package PressPrimer_Quiz
 * @subpackage Tests
 * @since 3.2.0
 */

use PHPUnit\Framework\TestCase;

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

if ( ! function_exists( 'update_user_meta' ) ) {
	/**
	 * Stub: user meta into $GLOBALS['pressprimer_quiz_test_user_meta'][ $user_id ].
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 * @return true
	 */
	function update_user_meta( $user_id, $key, $value ) {
		$GLOBALS['pressprimer_quiz_test_user_meta'][ $user_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Stub: WordPress's key sanitizer.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
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
 * Onboarding test case.
 *
 * @since 3.2.0
 */
class Test_Onboarding extends TestCase {

	/**
	 * No capabilities, no meta.
	 */
	protected function setUp(): void {
		$GLOBALS['pressprimer_quiz_test_caps']      = array();
		$GLOBALS['pressprimer_quiz_test_user_meta'] = array();
	}

	/**
	 * Clean up the globals.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['pressprimer_quiz_test_caps'], $GLOBALS['pressprimer_quiz_test_user_meta'] );
	}

	/**
	 * Site admins with the plugin's manage-all capability only.
	 */
	public function test_tour_gate() {
		$this->assertFalse( PressPrimer_Quiz_Onboarding::user_can_use_tour( 0 ), 'No user' );

		$GLOBALS['pressprimer_quiz_test_caps'] = array( 'pressprimer_quiz_manage_own' );
		$this->assertFalse( PressPrimer_Quiz_Onboarding::user_can_use_tour( 5 ), 'Teacher' );

		$GLOBALS['pressprimer_quiz_test_caps'] = array( 'manage_options' );
		$this->assertFalse( PressPrimer_Quiz_Onboarding::user_can_use_tour( 5 ), 'Admin without the plugin capability' );

		$GLOBALS['pressprimer_quiz_test_caps'] = array( 'pressprimer_quiz_manage_all', 'pressprimer_quiz_manage_own' );
		$this->assertFalse( PressPrimer_Quiz_Onboarding::user_can_use_tour( 5 ), 'Plugin manager without manage_options' );

		$GLOBALS['pressprimer_quiz_test_caps'] = array( 'manage_options', 'pressprimer_quiz_manage_all' );
		$this->assertTrue( PressPrimer_Quiz_Onboarding::user_can_use_tour( 5 ), 'Site admin' );
	}

	/**
	 * Only listed step ids are stored; anything else reads as the welcome.
	 */
	public function test_step_ids_are_validated() {
		$onboarding = PressPrimer_Quiz_Onboarding::get_instance();

		$this->assertSame( 'welcome', $onboarding->get_step_id( 9 ), 'Nothing stored' );

		$this->assertTrue( $onboarding->update_step_id( 'add-questions', 9 ) );
		$this->assertSame( 'add-questions', $onboarding->get_step_id( 9 ) );

		$this->assertFalse( $onboarding->update_step_id( 'menu', 9 ), 'A 1.0 step' );
		$this->assertSame( 'add-questions', $onboarding->get_step_id( 9 ), 'Unchanged' );

		$GLOBALS['pressprimer_quiz_test_user_meta'][9][ PressPrimer_Quiz_Onboarding::META_STEP_ID ] = '<script>';
		$this->assertSame( 'welcome', $onboarding->get_step_id( 9 ), 'A bad stored value' );

		// The 1.0 numeric step is ignored.
		$GLOBALS['pressprimer_quiz_test_user_meta'][10][ PressPrimer_Quiz_Onboarding::META_STEP ] = 5;
		$this->assertSame( 'welcome', $onboarding->get_step_id( 10 ) );
	}

	/**
	 * The run keeps its five fields, each its own type.
	 */
	public function test_run_is_sanitized() {
		$empty = array(
			'path'         => '',
			'bank_id'      => 0,
			'question_ids' => array(),
			'quiz_id'      => 0,
			'page_id'      => 0,
		);

		$this->assertSame( $empty, PressPrimer_Quiz_Onboarding::sanitize_run( null ) );
		$this->assertSame( $empty, PressPrimer_Quiz_Onboarding::sanitize_run( 'path=ai' ) );

		$this->assertSame(
			array(
				'path'         => 'ai',
				'bank_id'      => 4,
				'question_ids' => array( 7, 3, 2 ),
				'quiz_id'      => 12,
				'page_id'      => 30,
			),
			PressPrimer_Quiz_Onboarding::sanitize_run(
				array(
					'path'         => 'AI',
					'bank_id'      => '4',
					'question_ids' => array( '7', 3, 'x', 7, -2, 0 ), // absint( -2 ) is 2; it fails ownership on read.
					'quiz_id'      => 12.0,
					'page_id'      => '30abc',
					'extra'        => 'dropped',
				)
			)
		);

		$this->assertSame( '', PressPrimer_Quiz_Onboarding::sanitize_run( array( 'path' => 'teacher' ) )['path'], 'Unknown path' );
		$this->assertSame( array(), PressPrimer_Quiz_Onboarding::sanitize_run( array( 'question_ids' => '2' ) )['question_ids'], 'A string is not a list' );
	}

	/**
	 * Every step id is a key sanitize_key() keeps.
	 */
	public function test_step_ids_survive_sanitize_key() {
		foreach ( PressPrimer_Quiz_Onboarding::STEP_IDS as $step_id ) {
			$this->assertSame( $step_id, sanitize_key( $step_id ) );
		}
	}
}
