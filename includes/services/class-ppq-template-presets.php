<?php
/**
 * Built-in quiz settings template presets
 *
 * Registers the free plugin's built-in presets onto the
 * pressprimer_quiz_settings_template_presets filter (feature 003, FR-002).
 * Presets are code/filter only — never database rows. Their settings are
 * sanitized through the canonical Quiz sanitizers wherever the filter output is
 * consumed (the REST list), so the values here are the source intent and a bad
 * registration cannot inject invalid values downstream.
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 * @since 3.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Built-in template presets provider.
 *
 * @since 3.0.0
 */
class PressPrimer_Quiz_Template_Presets {

	/**
	 * Filter the presets list runs on.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const FILTER = 'pressprimer_quiz_settings_template_presets';

	/**
	 * Hook the built-in presets onto the presets filter.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( self::FILTER, array( __CLASS__, 'add_built_in_presets' ) );
	}

	/**
	 * Merge the built-in presets into the presets map.
	 *
	 * Built-ins only fill ids that are not already present, so a site or addon
	 * can override a built-in preset by registering the same id at an earlier
	 * priority.
	 *
	 * @since 3.0.0
	 *
	 * @param mixed $presets Incoming presets map (id => definition).
	 * @return array Presets map including the built-ins.
	 */
	public static function add_built_in_presets( $presets ) {
		if ( ! is_array( $presets ) ) {
			$presets = array();
		}

		foreach ( self::get_built_in_presets() as $id => $preset ) {
			if ( ! isset( $presets[ $id ] ) ) {
				$presets[ $id ] = $preset;
			}
		}

		return $presets;
	}

	/**
	 * Get the built-in preset definitions.
	 *
	 * Each definition has a translatable label/description, a partial map of
	 * quiz settings keys, and optional reminders shown after apply for settings
	 * the preset deliberately leaves to the author.
	 *
	 * Note on paging: the quizzes table models "one question per page" as
	 * page_mode = 'paged' with questions_per_page = 1 (the schema has no
	 * 'per_question' value), so Exam Simulation uses those.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string,array> Map of preset id => definition.
	 */
	public static function get_built_in_presets() {
		return array(
			'exam_simulation' => array(
				'label'       => __( 'Exam Simulation', 'pressprimer-quiz' ),
				'description' => __( 'Locked-down timed exam: no skipping, no going back, no resume, answers never shown.', 'pressprimer-quiz' ),
				'settings'    => array(
					'mode'                => 'timed',
					'allow_skip'          => 0,
					'allow_backward'      => 0,
					'allow_resume'        => 0,
					'max_attempts'        => 1,
					'show_answers'        => 'never',
					'randomize_questions' => 1,
					'randomize_answers'   => 1,
					'page_mode'           => 'paged',
					'questions_per_page'  => 1,
				),
				// Deliberately omits time_limit_seconds: the author must set it.
				'reminders'   => array(
					__( 'Set your time limit — Exam Simulation does not choose one for you.', 'pressprimer-quiz' ),
				),
			),
			'open_practice'   => array(
				'label'       => __( 'Open Practice', 'pressprimer-quiz' ),
				'description' => __( 'Relaxed practice: untimed, unlimited attempts, free navigation, answers shown after each submission.', 'pressprimer-quiz' ),
				'settings'    => array(
					'mode'                => 'tutorial',
					'allow_skip'          => 1,
					'allow_backward'      => 1,
					'allow_resume'        => 1,
					'max_attempts'        => null,
					'show_answers'        => 'after_submit',
					'randomize_questions' => 0,
					'randomize_answers'   => 0,
					'page_mode'           => 'single',
				),
			),
			'standard_graded' => array(
				'label'       => __( 'Standard Graded', 'pressprimer-quiz' ),
				'description' => __( 'Graded quiz on a single page: a few attempts allowed, answers revealed after completion.', 'pressprimer-quiz' ),
				'settings'    => array(
					'mode'           => 'tutorial',
					'page_mode'      => 'single',
					'show_answers'   => 'after_submit',
					'max_attempts'   => 3,
					'allow_skip'     => 1,
					'allow_backward' => 1,
					'allow_resume'   => 1,
				),
			),
		);
	}
}
