<?php
/**
 * Capabilities handler
 *
 * Manages user roles and capabilities for PressPrimer Quiz.
 *
 * @package PressPrimer_Quiz
 * @subpackage Utilities
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capabilities class
 *
 * Handles setup and removal of custom capabilities and roles.
 * Defines capabilities for quiz management, results viewing, and quiz taking.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Capabilities {

	/**
	 * Setup capabilities
	 *
	 * Adds all plugin capabilities to appropriate roles.
	 * Called during plugin activation.
	 *
	 * @since 1.0.0
	 */
	public static function setup_capabilities() {
		self::add_admin_capabilities();
		self::add_subscriber_capabilities();
		self::create_teacher_role();
	}

	/**
	 * Add capabilities to administrator role
	 *
	 * Administrators get full access to all plugin features.
	 *
	 * @since 1.0.0
	 */
	private static function add_admin_capabilities() {
		$admin = get_role( 'administrator' );

		if ( ! $admin ) {
			return;
		}

		// Full management access
		$admin->add_cap( 'ppq_manage_all' );        // Manage all quizzes, questions, banks, groups
		$admin->add_cap( 'ppq_manage_own' );        // Manage own content
		$admin->add_cap( 'ppq_view_results_all' );  // View all student results
		$admin->add_cap( 'ppq_view_results_own' );  // View results for own students
		$admin->add_cap( 'ppq_take_quiz' );         // Take quizzes
		$admin->add_cap( 'ppq_manage_settings' );   // Access plugin settings
	}

	/**
	 * Add capabilities to subscriber role
	 *
	 * Subscribers can take quizzes but cannot manage content.
	 *
	 * @since 1.0.0
	 */
	private static function add_subscriber_capabilities() {
		$subscriber = get_role( 'subscriber' );

		if ( ! $subscriber ) {
			return;
		}

		// Subscribers can only take quizzes
		$subscriber->add_cap( 'ppq_take_quiz' );
	}

	/**
	 * Create teacher role
	 *
	 * Creates a custom PressPrimer Teacher role with appropriate capabilities.
	 * Teachers can manage their own content and view their own students' results.
	 *
	 * @since 1.0.0
	 */
	public static function create_teacher_role() {
		// Remove role first if it exists (in case of capability changes)
		remove_role( 'ppq_teacher' );

		// Create the role with base capabilities
		add_role(
			'ppq_teacher',
			__( 'PressPrimer Teacher', 'pressprimer-quiz' ),
			[
				// Basic WordPress capabilities
				'read'                 => true,

				// PressPrimer Quiz capabilities
				'ppq_manage_own'       => true,  // Manage own quizzes, questions, banks
				'ppq_view_results_own' => true,  // View results for own students
				'ppq_take_quiz'        => true,  // Take quizzes
			]
		);
	}

	/**
	 * Remove capabilities
	 *
	 * Removes all plugin capabilities from all roles.
	 * Called during plugin uninstall.
	 *
	 * @since 1.0.0
	 */
	public static function remove_capabilities() {
		self::remove_admin_capabilities();
		self::remove_subscriber_capabilities();
		self::remove_teacher_role();
	}

	/**
	 * Remove capabilities from administrator role
	 *
	 * @since 1.0.0
	 */
	private static function remove_admin_capabilities() {
		$admin = get_role( 'administrator' );

		if ( ! $admin ) {
			return;
		}

		$admin->remove_cap( 'ppq_manage_all' );
		$admin->remove_cap( 'ppq_manage_own' );
		$admin->remove_cap( 'ppq_view_results_all' );
		$admin->remove_cap( 'ppq_view_results_own' );
		$admin->remove_cap( 'ppq_take_quiz' );
		$admin->remove_cap( 'ppq_manage_settings' );
	}

	/**
	 * Remove capabilities from subscriber role
	 *
	 * @since 1.0.0
	 */
	private static function remove_subscriber_capabilities() {
		$subscriber = get_role( 'subscriber' );

		if ( ! $subscriber ) {
			return;
		}

		$subscriber->remove_cap( 'ppq_take_quiz' );
	}

	/**
	 * Remove teacher role
	 *
	 * Removes the custom PressPrimer Teacher role.
	 *
	 * @since 1.0.0
	 */
	private static function remove_teacher_role() {
		remove_role( 'ppq_teacher' );
	}

	/**
	 * Get all plugin capabilities
	 *
	 * Returns an array of all capabilities used by the plugin.
	 * Useful for checking capabilities programmatically.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of capability keys.
	 */
	public static function get_all_capabilities() {
		return [
			'ppq_manage_all',
			'ppq_manage_own',
			'ppq_view_results_all',
			'ppq_view_results_own',
			'ppq_take_quiz',
			'ppq_manage_settings',
		];
	}

	/**
	 * Check if user can manage quiz
	 *
	 * Checks if user has permission to manage a specific quiz.
	 * Admins can manage all quizzes, teachers can only manage their own.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id  User ID to check.
	 * @param int $owner_id Quiz owner ID.
	 * @return bool True if user can manage the quiz.
	 */
	public static function user_can_manage_quiz( $user_id, $owner_id ) {
		// Admins can manage all quizzes
		if ( user_can( $user_id, 'ppq_manage_all' ) ) {
			return true;
		}

		// Check if user can manage own content and is the owner
		if ( user_can( $user_id, 'ppq_manage_own' ) && $user_id === $owner_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if user can view results
	 *
	 * Checks if user has permission to view results for a specific student.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id    User ID to check.
	 * @param int $student_id Student whose results to view.
	 * @return bool True if user can view the results.
	 */
	public static function user_can_view_results( $user_id, $student_id ) {
		// Admins can view all results
		if ( user_can( $user_id, 'ppq_view_results_all' ) ) {
			return true;
		}

		// Users can view their own results
		if ( $user_id === $student_id ) {
			return true;
		}

		// Teachers can view their students' results
		// This will be enhanced in later phases to check group membership
		if ( user_can( $user_id, 'ppq_view_results_own' ) ) {
			// For now, teachers can't view other students' results
			// Group membership checking will be added in Phase 2
			return false;
		}

		return false;
	}
}
