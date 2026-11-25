<?php
/**
 * Editor data service
 *
 * Builds the data the quiz, question and bank editors start with. wp-admin
 * localizes it on the editor pages; GET /ppq/v1/editor-data serves the same
 * data to the portable editors on the front-end dashboard.
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 * @since 3.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor data service class
 *
 * Each builder takes the entity ID (0 for new) and the context the editor
 * runs in: 'admin' (wp-admin) or 'dashboard' (the front-end dashboard). The
 * caller checks the user may edit the entity.
 *
 * @since 3.2.0
 */
class PressPrimer_Quiz_Editor_Data_Service {

	/**
	 * Contexts an editor runs in.
	 *
	 * @since 3.2.0
	 * @var string[]
	 */
	const CONTEXTS = [ 'admin', 'dashboard' ];

	/**
	 * Build the quiz editor's data.
	 *
	 * @since 3.2.0
	 *
	 * @param int    $quiz_id Quiz ID; 0 for a new quiz.
	 * @param string $context 'admin' or 'dashboard'.
	 * @return array Quiz editor data.
	 */
	public static function quiz( $quiz_id, $context = 'admin' ) {
		$quiz_id = absint( $quiz_id );
		$context = self::normalize_context( $context );
		$quiz    = $quiz_id > 0 ? PressPrimer_Quiz_Quiz::get( $quiz_id ) : null;

		// Prepare quiz data for JavaScript
		$quiz_data = [];

		if ( $quiz_id > 0 ) {
			if ( $quiz ) {
				$quiz_data = [
					'id'                       => $quiz->id,
					'title'                    => $quiz->title,
					'description'              => $quiz->description,
					'featured_image_id'        => $quiz->featured_image_id,
					'status'                   => $quiz->status,
					'mode'                     => $quiz->mode,
					'time_limit_seconds'       => $quiz->time_limit_seconds,
					'pass_percent'             => $quiz->pass_percent,
					'allow_skip'               => (bool) $quiz->allow_skip,
					'allow_backward'           => (bool) $quiz->allow_backward,
					'allow_resume'             => (bool) $quiz->allow_resume,
					'randomize_questions'      => (bool) $quiz->randomize_questions,
					'randomize_answers'        => (bool) $quiz->randomize_answers,
					'page_mode'                => $quiz->page_mode,
					'questions_per_page'       => $quiz->questions_per_page,
					'show_answers'             => $quiz->show_answers,
					'enable_confidence'        => (bool) $quiz->enable_confidence,
					'theme'                    => $quiz->theme,
					'max_attempts'             => $quiz->max_attempts,
					'attempt_delay_minutes'    => $quiz->attempt_delay_minutes,
					'generation_mode'          => $quiz->generation_mode,
					'band_feedback_json'       => $quiz->band_feedback_json,
					'access_mode'              => $quiz->access_mode,
					'login_message'            => $quiz->login_message,
					'display_density'          => $quiz->display_density,
					'pool_enabled'             => (bool) $quiz->pool_enabled,
					'max_questions'            => $quiz->max_questions,
					'pool_size'                => $quiz->get_pool_size()['count'],
					'enable_sr'                => (bool) $quiz->enable_sr,
					'show_points'              => (bool) $quiz->show_points,
					'is_practice'              => (bool) $quiz->is_practice,
					'exposure_control'         => (bool) $quiz->exposure_control,
					'use_measured_difficulty'  => (bool) $quiz->use_measured_difficulty,
					'ma_scoring_mode'          => $quiz->ma_scoring_mode,
					// Cast to object so empty {} round-trips through wp_localize_script
					// instead of being serialized as a JSON array [].
					'display_settings'         => (object) $quiz->get_display_settings(),
					'max_answers_per_question' => $quiz->max_answers_per_question ? (int) $quiz->max_answers_per_question : null,
				];
			}
		} else {
			// For new quizzes, load defaults from settings
			$settings  = get_option( 'pressprimer_quiz_settings', [] );
			$quiz_data = [
				'mode'                     => isset( $settings['default_quiz_mode'] ) ? $settings['default_quiz_mode'] : 'tutorial',
				'pass_percent'             => isset( $settings['default_passing_score'] ) ? (float) $settings['default_passing_score'] : 70,
				'ma_scoring_mode'          => null,
				'display_settings'         => (object) array(),
				'max_answers_per_question' => null,
			];

			// Pre-fill a fresh quiz from the site default settings template, before
			// the builder first renders (feature 003, FR-006). The editor applies
			// these client-side and shows a "Defaults from" badge; nothing is
			// persisted until the author saves.
			if ( class_exists( 'PressPrimer_Quiz_Default_Template' ) ) {
				$default_template = PressPrimer_Quiz_Default_Template::resolve();

				if ( is_array( $default_template ) ) {
					$quiz_data['defaultTemplate'] = [
						'name'     => $default_template['name'],
						'settings' => (object) $default_template['settings'],
					];
				}
			}
		}

		// Site-wide MA scoring default — used by the editor to render the
		// "Use site default (currently: ...)" label without re-fetching.
		$quiz_data['default_ma_scoring'] = get_option(
			'pressprimer_quiz_default_ma_scoring',
			'right_minus_wrong'
		);

		// Whose questions the quiz's rules draw from, for the Rules tab copy:
		// 'all' when the owner has manage_all, 'own' when the owner is the
		// current user, 'owner' when someone else (an admin) edits it. A new
		// quiz belongs to the current user.
		$rule_owner_id = get_current_user_id();
		if ( $quiz ) {
			$rule_owner_id = absint( $quiz->owner_id );
		}

		if ( $rule_owner_id && user_can( $rule_owner_id, 'pressprimer_quiz_manage_all' ) ) {
			$quiz_data['ruleScope'] = 'all';
		} elseif ( get_current_user_id() === $rule_owner_id ) {
			$quiz_data['ruleScope'] = 'own';
		} else {
			$quiz_data['ruleScope'] = 'owner';
		}

		// Multiple-answer scoring copy (labels, descriptions, worked examples) for
		// the builder's scoring cards. Sourced from the shared copy provider so the
		// builder and the results renderer cannot drift (feature 005, TR-003).
		if ( class_exists( 'PressPrimer_Quiz_Scoring_Copy' ) ) {
			$quiz_data['maScoringModes'] = PressPrimer_Quiz_Scoring_Copy::get_modes_for_js();
		}

		$quiz_data = array_merge( $quiz_data, self::get_site_data() );

		/**
		 * Filter quiz editor data before passing to React
		 *
		 * Allows addons to inject additional data into the quiz editor.
		 *
		 * @since 2.1.0
		 * @since 3.2.0 Added the $context argument.
		 *
		 * @param array  $quiz_data Quiz data array.
		 * @param int    $quiz_id   Quiz ID (0 for new quiz).
		 * @param string $context   Where the editor runs: 'admin' or 'dashboard'.
		 */
		$quiz_data = apply_filters( 'pressprimer_quiz_quiz_editor_data', $quiz_data, $quiz_id, $context );

		return self::remove_upsells( $quiz_data, $context, [ 'touchpoints', 'quizOptions' ] );
	}

	/**
	 * Build the question editor's data.
	 *
	 * @since 3.2.0
	 *
	 * @param int    $question_id Question ID; 0 for a new question.
	 * @param string $context     'admin' or 'dashboard'.
	 * @return array Question editor data, with the addon tabs in addonTabs.
	 */
	public static function question( $question_id, $context = 'admin' ) {
		$question_id = absint( $question_id );
		$context     = self::normalize_context( $context );
		$question    = $question_id > 0 ? PressPrimer_Quiz_Question::get( $question_id ) : null;

		// Prepare question data for React
		$question_data = [];

		if ( $question ) {
			global $wpdb;

			$revision   = $question->get_current_revision();
			$categories = $question->get_categories();
			$tags       = $question->get_tags();

			// Get bank memberships
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Question bank membership lookup
			$bank_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT bank_id FROM {$wpdb->prefix}ppq_bank_questions WHERE question_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name safely constructed from $wpdb->prefix
					$question_id
				)
			);

			$answers = $revision ? $revision->get_answers() : [];

			// Convert answer format for React
			$answer_index  = 0;
			$react_answers = array_map(
				function ( $answer ) use ( &$answer_index ) {
					$answer_index++;
					return [
						'id'        => $answer['id'] ?? 'answer_' . $answer_index,
						'text'      => $answer['text'] ?? '',
						'isCorrect' => $answer['is_correct'] ?? false,
						'feedback'  => $answer['feedback'] ?? '',
						'order'     => $answer['order'] ?? $answer_index,
					];
				},
				$answers
			);

			$question_data = [
				'id'                => $question->id,
				'type'              => $question->type,
				'difficulty'        => $question->difficulty_author,
				'timeLimit'         => $question->expected_seconds,
				'points'            => $question->max_points,
				'status'            => $question->status,
				'stem'              => $revision ? $revision->stem : '',
				'answers'           => $react_answers,
				'feedbackCorrect'   => $revision ? $revision->feedback_correct : '',
				'feedbackIncorrect' => $revision ? $revision->feedback_incorrect : '',
				'categories'        => array_map(
					function ( $cat ) {
						return $cat->id; },
					$categories
				),
				'tags'              => array_map(
					function ( $tag ) {
						return $tag->id; },
					$tags
				),
				'banks'             => array_map( 'absint', $bank_ids ),
				// Banks the user can't edit render read-only, by name.
				'bank_memberships'  => PressPrimer_Quiz_Question_Access_Service::get_bank_memberships( $question->id, get_current_user_id() ),
			];
		}

		$question_data = array_merge( $question_data, self::get_site_data() );

		/**
		 * Filters the addon tabs shown in the question editor.
		 *
		 * Allows addons to add their own tabs to the question editor.
		 * Each tab should be an array with 'key' and 'label' keys.
		 *
		 * @since 2.0.0
		 * @since 3.2.0 Added the $context argument.
		 *
		 * @param array                          $tabs     Array of addon tabs.
		 * @param PressPrimer_Quiz_Question|null $question Question object, or null for new questions.
		 * @param string                         $context  Where the editor runs: 'admin' or 'dashboard'.
		 */
		$question_data['addonTabs'] = apply_filters( 'pressprimer_quiz_question_editor_tabs', array(), $question, $context );

		return self::remove_upsells( $question_data, $context, [ 'touchpoints' ] );
	}

	/**
	 * Build the bank editor's data.
	 *
	 * @since 3.2.0
	 *
	 * @param int    $bank_id Bank ID; 0 for a new bank.
	 * @param string $context 'admin' or 'dashboard'.
	 * @return array Bank editor data.
	 */
	public static function bank( $bank_id, $context = 'admin' ) {
		$bank_id = absint( $bank_id );
		$context = self::normalize_context( $context );

		// Prepare bank data for JavaScript
		$bank_data = [];

		if ( $bank_id > 0 ) {
			$bank = PressPrimer_Quiz_Bank::get( $bank_id );

			if ( $bank ) {
				$bank_data = [
					'id'             => $bank->id,
					'uuid'           => $bank->uuid,
					'name'           => $bank->name,
					'description'    => $bank->description,
					'owner_id'       => $bank->owner_id,
					'visibility'     => $bank->visibility,
					'question_count' => $bank->question_count,
					'created_at'     => $bank->created_at,
					'updated_at'     => $bank->updated_at,
				];
			}
		}

		// Help under the Visibility field. Addons that change who can see a
		// bank say so here.
		$bank_data['visibilityHelp'] = __( 'Private: Only you can access this bank. Shared: Teachers in your groups can access this bank.', 'pressprimer-quiz' );

		$bank_data = array_merge( $bank_data, self::get_site_data() );

		/**
		 * Filters the data the bank editor starts with.
		 *
		 * @since 3.2.0
		 *
		 * @param array  $bank_data Bank fields (empty for a new bank), userCan, visibilityHelp and the site flags.
		 * @param int    $bank_id   Bank ID; 0 for a new bank.
		 * @param string $context   Where the editor runs: 'admin' or 'dashboard'.
		 */
		$bank_data = apply_filters( 'pressprimer_quiz_bank_editor_data', $bank_data, $bank_id, $context );

		return self::remove_upsells( $bank_data, $context, [ 'touchpoints' ] );
	}

	/**
	 * Site flags every editor gets.
	 *
	 * The canManageSettings flag gates the quiz builder's "Save as template"
	 * (templates are a site-wide policy object); isAdmin gates the Premium
	 * Settings tab's upsell preview on a free site (admins-only upsells);
	 * mathEnabled shows the Insert Math control and preview.
	 *
	 * @since 3.2.0
	 *
	 * @return array mathEnabled, canManageSettings, isAdmin and userCan.
	 */
	private static function get_site_data() {
		return [
			'mathEnabled'       => (bool) pressprimer_quiz_math_enabled(),
			'canManageSettings' => current_user_can( 'pressprimer_quiz_manage_settings' ),
			'isAdmin'           => current_user_can( 'manage_options' ),
			'userCan'           => [
				'manage_all' => current_user_can( 'pressprimer_quiz_manage_all' ),
				'manage_own' => current_user_can( 'pressprimer_quiz_manage_own' ),
			],
		];
	}

	/**
	 * Empty the upsell keys on the dashboard.
	 *
	 * Upsells are wp-admin only; on the dashboard these keys are always empty,
	 * whatever a filter added (features 11 and 12).
	 *
	 * @since 3.2.0
	 *
	 * @param array    $data    Editor data.
	 * @param string   $context 'admin' or 'dashboard'.
	 * @param string[] $keys    Upsell keys this editor can carry.
	 * @return array Editor data.
	 */
	private static function remove_upsells( $data, $context, $keys ) {
		if ( 'dashboard' !== $context ) {
			return $data;
		}

		foreach ( $keys as $key ) {
			$data[ $key ] = [];
		}

		return $data;
	}

	/**
	 * Normalize a context name.
	 *
	 * @since 3.2.0
	 *
	 * @param string $context Requested context.
	 * @return string 'admin' or 'dashboard' (unknown values are 'admin').
	 */
	private static function normalize_context( $context ) {
		return in_array( $context, self::CONTEXTS, true ) ? $context : 'admin';
	}
}
