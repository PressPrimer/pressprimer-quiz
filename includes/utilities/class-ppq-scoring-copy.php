<?php
/**
 * Scoring copy provider.
 *
 * Single source of truth for the human-facing copy describing the four
 * multiple-answer scoring modes: short labels, plain-language descriptions,
 * worked examples, and per-mode formula templates (feature 005, TR-003).
 *
 * Consumed by the Quiz Builder's scoring UI (localized to React) and the
 * results renderer's per-question lines and "How scoring works" explainer, so
 * the two surfaces cannot drift. Strings are translatable; the renderer formats
 * dynamic numbers with number_format_i18n() when filling the templates.
 *
 * @package PressPrimer_Quiz
 * @subpackage Utilities
 * @since 3.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scoring copy provider.
 *
 * @since 3.0.0
 */
class PressPrimer_Quiz_Scoring_Copy {

	/**
	 * Mode keys in display order (lenient → strict), matching the 2.3 builder.
	 *
	 * @since 3.0.0
	 *
	 * @return string[] Ordered mode keys.
	 */
	public static function get_mode_keys() {
		return array( 'right_minus_wrong', 'proportional', 'partial_no_wrong', 'all_or_nothing' );
	}

	/**
	 * Whether a mode key is a recognized scoring mode.
	 *
	 * @since 3.0.0
	 *
	 * @param string $mode Mode key.
	 * @return bool True if recognized.
	 */
	public static function is_mode( $mode ) {
		return is_string( $mode ) && in_array( $mode, self::get_mode_keys(), true );
	}

	/**
	 * Short label for a scoring mode.
	 *
	 * @since 3.0.0
	 *
	 * @param string $mode Mode key.
	 * @return string Label, or '' for an unknown mode.
	 */
	public static function get_label( $mode ) {
		switch ( $mode ) {
			case 'right_minus_wrong':
				return __( 'Right Minus Wrong', 'pressprimer-quiz' );
			case 'proportional':
				return __( 'Partial Credit', 'pressprimer-quiz' );
			case 'partial_no_wrong':
				return __( 'Partial Credit, No Wrong Answers', 'pressprimer-quiz' );
			case 'all_or_nothing':
				return __( 'All or Nothing', 'pressprimer-quiz' );
			default:
				return '';
		}
	}

	/**
	 * Plain-language description for a scoring mode.
	 *
	 * @since 3.0.0
	 *
	 * @param string $mode Mode key.
	 * @return string Description, or '' for an unknown mode.
	 */
	public static function get_description( $mode ) {
		switch ( $mode ) {
			case 'right_minus_wrong':
				return __( 'Each wrong selection cancels one correct selection. Score never goes below zero.', 'pressprimer-quiz' );
			case 'proportional':
				return __( 'Each correct selection earns proportional credit. Wrong selections are ignored.', 'pressprimer-quiz' );
			case 'partial_no_wrong':
				return __( 'Proportional credit, but any wrong selection scores zero for the question.', 'pressprimer-quiz' );
			case 'all_or_nothing':
				return __( 'Full credit only when every correct answer is selected and none of the wrong ones.', 'pressprimer-quiz' );
			default:
				return '';
		}
	}

	/**
	 * Worked examples for a scoring mode.
	 *
	 * Examples assume a question worth 1 point with 3 correct answer options
	 * (matching the 2.3 builder's "EXAMPLE" copy).
	 *
	 * @since 3.0.0
	 *
	 * @param string $mode Mode key.
	 * @return string[] Example lines (empty for an unknown mode).
	 */
	public static function get_examples( $mode ) {
		switch ( $mode ) {
			case 'right_minus_wrong':
				return array(
					__( '2 correct + 1 wrong → 0.33 points', 'pressprimer-quiz' ),
				);
			case 'proportional':
				return array(
					__( '2 correct + 1 wrong → 0.67 points', 'pressprimer-quiz' ),
				);
			case 'partial_no_wrong':
				return array(
					__( '2 correct + 1 wrong → 0 points', 'pressprimer-quiz' ),
					__( '2 correct + 0 wrong → 0.67 points', 'pressprimer-quiz' ),
				);
			case 'all_or_nothing':
				return array(
					__( '2 correct + 0 wrong → 0 points', 'pressprimer-quiz' ),
					__( 'Only 3 correct + 0 wrong → 1.00 points', 'pressprimer-quiz' ),
				);
			default:
				return array();
		}
	}

	/**
	 * Per-question formula template for a scoring mode.
	 *
	 * The results renderer fills these with stored, i18n-formatted counts. They
	 * describe the scoring arithmetic only; the resulting point value is shown
	 * separately by the quiz's "Show points per question" setting, so it is not
	 * repeated here. The numbered placeholders differ per mode:
	 *
	 *  - right_minus_wrong: 1 right, 2 wrong, 3 net counted, 4 total correct
	 *  - proportional:      1 right, 2 total correct
	 *  - partial_no_wrong:  1 right, 2 total correct, 3 wrong
	 *  - all_or_nothing:    1 right, 2 total correct, 3 wrong
	 *
	 * @since 3.0.0
	 *
	 * @param string $mode Mode key.
	 * @return string Template, or '' for an unknown mode.
	 */
	public static function get_formula_template( $mode ) {
		switch ( $mode ) {
			case 'right_minus_wrong':
				/* translators: 1: correct selections, 2: wrong selections, 3: net correct counted, 4: total correct. */
				return __( '%1$s correct − %2$s wrong = %3$s of %4$s correct counted (never below zero)', 'pressprimer-quiz' );
			case 'proportional':
				/* translators: 1: correct selections, 2: total correct. */
				return __( '%1$s of %2$s correct selected, wrong selections ignored', 'pressprimer-quiz' );
			case 'partial_no_wrong':
				/* translators: 1: correct selections, 2: total correct, 3: wrong selections. */
				return __( '%1$s of %2$s correct, %3$s wrong (any wrong selection scores zero)', 'pressprimer-quiz' );
			case 'all_or_nothing':
				/* translators: 1: correct selections, 2: total correct, 3: wrong selections. */
				return __( '%1$s of %2$s correct, %3$s wrong (all correct and none wrong required)', 'pressprimer-quiz' );
			default:
				return '';
		}
	}

	/**
	 * Full structured copy for one mode.
	 *
	 * @since 3.0.0
	 *
	 * @param string $mode Mode key.
	 * @return array { value, label, description, examples, formula_template }.
	 */
	public static function get_mode( $mode ) {
		return array(
			'value'            => $mode,
			'label'            => self::get_label( $mode ),
			'description'      => self::get_description( $mode ),
			'examples'         => self::get_examples( $mode ),
			'formula_template' => self::get_formula_template( $mode ),
		);
	}

	/**
	 * All modes, in display order, with full copy.
	 *
	 * @since 3.0.0
	 *
	 * @return array[] One entry per mode (see get_mode()).
	 */
	public static function get_modes() {
		$modes = array();
		foreach ( self::get_mode_keys() as $key ) {
			$modes[] = self::get_mode( $key );
		}
		return $modes;
	}

	/**
	 * Modes shaped for the builder's React scoring cards.
	 *
	 * Returns value/label/description/examples per mode (no formula template —
	 * the builder shows examples, not the per-question formula).
	 *
	 * @since 3.0.0
	 *
	 * @return array[] One entry per mode.
	 */
	public static function get_modes_for_js() {
		$modes = array();
		foreach ( self::get_mode_keys() as $key ) {
			$modes[] = array(
				'value'       => $key,
				'label'       => self::get_label( $key ),
				'description' => self::get_description( $key ),
				'examples'    => self::get_examples( $key ),
			);
		}
		return $modes;
	}
}
