<?php
/**
 * Math Detection Tests
 *
 * Unit tests for PressPrimer_Quiz_Helpers::content_has_math(), the helper used
 * to decide — at output time — whether the KaTeX assets need to load for a
 * given block of content. This is the gate that keeps quizzes without math from
 * loading any math assets even when the feature is enabled.
 *
 * @package PressPrimer_Quiz
 * @subpackage Tests
 * @since 3.0.0
 */

use PHPUnit\Framework\TestCase;

// content_has_math() reads the math delimiter set from a global function defined
// in the main plugin file, which the no-WordPress test bootstrap does not load.
// Provide the same delimiters here so the helper can be exercised in isolation.
if ( ! function_exists( 'pressprimer_quiz_math_delimiters' ) ) {
	/**
	 * Test stub mirroring the production delimiter set.
	 *
	 * @return array[]
	 */
	function pressprimer_quiz_math_delimiters() {
		return array(
			array(
				'left'  => '\\(',
				'right' => '\\)',
			),
			array(
				'left'  => '\\[',
				'right' => '\\]',
			),
			array(
				'left'  => '$$',
				'right' => '$$',
			),
		);
	}
}

/**
 * Math Detection Test Case
 *
 * @since 3.0.0
 */
class Test_Math_Detection extends TestCase {

	/**
	 * Inline \( ... \) is detected.
	 */
	public function test_detects_inline_delimiter() {
		$this->assertTrue(
			PressPrimer_Quiz_Helpers::content_has_math( 'What is \\( x^2 \\)?' )
		);
	}

	/**
	 * Display \[ ... \] is detected.
	 */
	public function test_detects_display_delimiter() {
		$this->assertTrue(
			PressPrimer_Quiz_Helpers::content_has_math( 'before \\[ \\frac{a}{b} \\] after' )
		);
	}

	/**
	 * The $$ ... $$ display delimiter is detected.
	 */
	public function test_detects_double_dollar() {
		$this->assertTrue(
			PressPrimer_Quiz_Helpers::content_has_math( 'foo $$x$$ bar' )
		);
	}

	/**
	 * Math written inside HTML markup is still detected.
	 */
	public function test_detects_math_inside_markup() {
		$this->assertTrue(
			PressPrimer_Quiz_Helpers::content_has_math( '<div class="ppq-answer-text">\\( a + b \\)</div>' )
		);
	}

	/**
	 * Plain text without delimiters is not treated as math.
	 */
	public function test_plain_text_is_not_math() {
		$this->assertFalse(
			PressPrimer_Quiz_Helpers::content_has_math( 'x squared is x2' )
		);
	}

	/**
	 * A single dollar sign (currency) is not a math delimiter.
	 */
	public function test_single_dollar_is_not_a_delimiter() {
		$this->assertFalse(
			PressPrimer_Quiz_Helpers::content_has_math( 'price is $5 and $9' )
		);
	}

	/**
	 * An empty string contains no math.
	 */
	public function test_empty_string_is_not_math() {
		$this->assertFalse(
			PressPrimer_Quiz_Helpers::content_has_math( '' )
		);
	}

	/**
	 * A non-string value contains no math.
	 */
	public function test_non_string_is_not_math() {
		$this->assertFalse(
			PressPrimer_Quiz_Helpers::content_has_math( null )
		);
	}
}
