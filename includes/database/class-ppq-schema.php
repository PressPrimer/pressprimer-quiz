<?php
/**
 * Database schema
 *
 * Defines all database table structures for PressPrimer Quiz.
 *
 * @package PressPrimer_Quiz
 * @subpackage Database
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema class
 *
 * Provides SQL definitions for all plugin database tables.
 * Uses dbDelta-compatible syntax for safe schema updates.
 *
 * @since 1.0.0
 */
class PPQ_Schema {

	/**
	 * Get the complete schema SQL
	 *
	 * Returns SQL for creating all plugin tables.
	 * Uses dbDelta-compatible syntax.
	 *
	 * @since 1.0.0
	 *
	 * @return string SQL for all table creation.
	 */
	public static function get_schema() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = self::get_questions_table( $charset_collate );
		$sql .= self::get_question_revisions_table( $charset_collate );
		$sql .= self::get_categories_table( $charset_collate );
		$sql .= self::get_question_tax_table( $charset_collate );
		$sql .= self::get_banks_table( $charset_collate );
		$sql .= self::get_bank_questions_table( $charset_collate );
		$sql .= self::get_quizzes_table( $charset_collate );
		$sql .= self::get_quiz_items_table( $charset_collate );
		$sql .= self::get_quiz_rules_table( $charset_collate );
		$sql .= self::get_groups_table( $charset_collate );
		$sql .= self::get_group_members_table( $charset_collate );
		$sql .= self::get_assignments_table( $charset_collate );
		$sql .= self::get_attempts_table( $charset_collate );
		$sql .= self::get_attempt_items_table( $charset_collate );
		$sql .= self::get_events_table( $charset_collate );

		return $sql;
	}

	/**
	 * Get questions table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for questions table.
	 */
	private static function get_questions_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_questions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			uuid CHAR(36) NOT NULL,
			author_id BIGINT UNSIGNED NOT NULL,
			type ENUM('mc', 'ma', 'tf') NOT NULL DEFAULT 'mc',
			expected_seconds SMALLINT UNSIGNED DEFAULT NULL,
			difficulty_author ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT NULL,
			max_points DECIMAL(5,2) NOT NULL DEFAULT 1.00,
			status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'published',
			current_revision_id BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY author_id (author_id),
			KEY type (type),
			KEY status (status),
			KEY difficulty_author (difficulty_author),
			KEY created_at (created_at),
			KEY deleted_at (deleted_at)
		) $charset_collate;\n";
	}

	/**
	 * Get question revisions table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for question revisions table.
	 */
	private static function get_question_revisions_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_question_revisions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			question_id BIGINT UNSIGNED NOT NULL,
			version INT UNSIGNED NOT NULL,
			stem TEXT NOT NULL,
			answers_json LONGTEXT NOT NULL,
			feedback_correct TEXT DEFAULT NULL,
			feedback_incorrect TEXT DEFAULT NULL,
			settings_json TEXT DEFAULT NULL,
			content_hash CHAR(64) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_by BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY question_version (question_id, version),
			KEY content_hash (content_hash),
			KEY created_at (created_at)
		) $charset_collate;\n";
	}

	/**
	 * Get categories table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for categories table.
	 */
	private static function get_categories_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_categories (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(200) NOT NULL,
			slug VARCHAR(200) NOT NULL,
			description TEXT DEFAULT NULL,
			parent_id BIGINT UNSIGNED DEFAULT NULL,
			taxonomy ENUM('category', 'tag') NOT NULL DEFAULT 'category',
			question_count INT UNSIGNED NOT NULL DEFAULT 0,
			quiz_count INT UNSIGNED NOT NULL DEFAULT 0,
			created_by BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug_taxonomy (slug, taxonomy),
			KEY parent_id (parent_id),
			KEY taxonomy (taxonomy),
			KEY name (name)
		) $charset_collate;\n";
	}

	/**
	 * Get question taxonomy table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for question taxonomy table.
	 */
	private static function get_question_tax_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_question_tax (
			question_id BIGINT UNSIGNED NOT NULL,
			category_id BIGINT UNSIGNED NOT NULL,
			taxonomy ENUM('category', 'tag') NOT NULL DEFAULT 'category',
			PRIMARY KEY  (question_id, category_id),
			KEY category_id (category_id),
			KEY taxonomy (taxonomy)
		) $charset_collate;\n";
	}

	/**
	 * Get banks table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for banks table.
	 */
	private static function get_banks_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_banks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			uuid CHAR(36) NOT NULL,
			name VARCHAR(200) NOT NULL,
			description TEXT DEFAULT NULL,
			owner_id BIGINT UNSIGNED NOT NULL,
			visibility ENUM('private', 'shared') NOT NULL DEFAULT 'private',
			question_count INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			deleted_at DATETIME DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY owner_id (owner_id),
			KEY visibility (visibility),
			KEY name (name),
			KEY deleted_at (deleted_at)
		) $charset_collate;\n";
	}

	/**
	 * Get bank questions table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for bank questions table.
	 */
	private static function get_bank_questions_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_bank_questions (
			bank_id BIGINT UNSIGNED NOT NULL,
			question_id BIGINT UNSIGNED NOT NULL,
			added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (bank_id, question_id),
			KEY question_id (question_id)
		) $charset_collate;\n";
	}

	/**
	 * Get quizzes table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for quizzes table.
	 */
	private static function get_quizzes_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_quizzes (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			uuid CHAR(36) NOT NULL,
			title VARCHAR(200) NOT NULL,
			description TEXT DEFAULT NULL,
			featured_image_id BIGINT UNSIGNED DEFAULT NULL,
			owner_id BIGINT UNSIGNED NOT NULL,
			status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
			mode ENUM('tutorial', 'timed') NOT NULL DEFAULT 'tutorial',
			time_limit_seconds INT UNSIGNED DEFAULT NULL,
			pass_percent DECIMAL(5,2) NOT NULL DEFAULT 70.00,
			allow_skip TINYINT(1) NOT NULL DEFAULT 1,
			allow_backward TINYINT(1) NOT NULL DEFAULT 1,
			allow_resume TINYINT(1) NOT NULL DEFAULT 1,
			max_attempts INT UNSIGNED DEFAULT NULL,
			attempt_delay_minutes INT UNSIGNED DEFAULT NULL,
			randomize_questions TINYINT(1) NOT NULL DEFAULT 0,
			randomize_answers TINYINT(1) NOT NULL DEFAULT 0,
			page_mode ENUM('single', 'paged') NOT NULL DEFAULT 'single',
			questions_per_page TINYINT UNSIGNED DEFAULT 1,
			show_answers ENUM('never', 'after_submit', 'after_pass') NOT NULL DEFAULT 'after_submit',
			enable_confidence TINYINT(1) NOT NULL DEFAULT 0,
			theme VARCHAR(50) NOT NULL DEFAULT 'default',
			theme_settings_json TEXT DEFAULT NULL,
			band_feedback_json TEXT DEFAULT NULL,
			generation_mode ENUM('fixed', 'dynamic') NOT NULL DEFAULT 'fixed',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY owner_id (owner_id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;\n";
	}

	/**
	 * Get quiz items table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for quiz items table.
	 */
	private static function get_quiz_items_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_quiz_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			quiz_id BIGINT UNSIGNED NOT NULL,
			question_id BIGINT UNSIGNED NOT NULL,
			order_index SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
			PRIMARY KEY  (id),
			UNIQUE KEY quiz_question (quiz_id, question_id),
			KEY question_id (question_id),
			KEY order_index (quiz_id, order_index)
		) $charset_collate;\n";
	}

	/**
	 * Get quiz rules table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for quiz rules table.
	 */
	private static function get_quiz_rules_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_quiz_rules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			quiz_id BIGINT UNSIGNED NOT NULL,
			rule_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			bank_id BIGINT UNSIGNED DEFAULT NULL,
			category_ids_json TEXT DEFAULT NULL,
			tag_ids_json TEXT DEFAULT NULL,
			difficulties_json TEXT DEFAULT NULL,
			question_count SMALLINT UNSIGNED NOT NULL DEFAULT 10,
			PRIMARY KEY  (id),
			KEY quiz_id (quiz_id),
			KEY bank_id (bank_id)
		) $charset_collate;\n";
	}

	/**
	 * Get groups table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for groups table.
	 */
	private static function get_groups_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_groups (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			uuid CHAR(36) NOT NULL,
			name VARCHAR(200) NOT NULL,
			description TEXT DEFAULT NULL,
			owner_id BIGINT UNSIGNED NOT NULL,
			member_count INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY owner_id (owner_id),
			KEY name (name)
		) $charset_collate;\n";
	}

	/**
	 * Get group members table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for group members table.
	 */
	private static function get_group_members_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_group_members (
			group_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			role ENUM('teacher', 'student') NOT NULL DEFAULT 'student',
			added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			added_by BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (group_id, user_id),
			KEY user_id (user_id),
			KEY role (role)
		) $charset_collate;\n";
	}

	/**
	 * Get assignments table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for assignments table.
	 */
	private static function get_assignments_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_assignments (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			quiz_id BIGINT UNSIGNED NOT NULL,
			assignee_type ENUM('group', 'user') NOT NULL,
			assignee_id BIGINT UNSIGNED NOT NULL,
			assigned_by BIGINT UNSIGNED NOT NULL,
			due_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY quiz_assignee (quiz_id, assignee_type, assignee_id),
			KEY assignee (assignee_type, assignee_id),
			KEY due_at (due_at),
			KEY assigned_by (assigned_by)
		) $charset_collate;\n";
	}

	/**
	 * Get attempts table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for attempts table.
	 */
	private static function get_attempts_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_attempts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			uuid CHAR(36) NOT NULL,
			quiz_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			guest_email VARCHAR(100) DEFAULT NULL,
			guest_name VARCHAR(100) DEFAULT NULL,
			guest_token CHAR(64) DEFAULT NULL,
			token_expires_at DATETIME DEFAULT NULL,
			started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			finished_at DATETIME DEFAULT NULL,
			elapsed_ms INT UNSIGNED DEFAULT NULL,
			score_points DECIMAL(10,2) DEFAULT NULL,
			max_points DECIMAL(10,2) DEFAULT NULL,
			score_percent DECIMAL(5,2) DEFAULT NULL,
			passed TINYINT(1) DEFAULT NULL,
			status ENUM('in_progress', 'submitted', 'abandoned') NOT NULL DEFAULT 'in_progress',
			current_position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			questions_json LONGTEXT NOT NULL,
			meta_json TEXT DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY quiz_id (quiz_id),
			KEY user_id (user_id),
			KEY guest_email (guest_email),
			KEY guest_token (guest_token),
			KEY status (status),
			KEY started_at (started_at),
			KEY finished_at (finished_at),
			KEY token_expires_at (token_expires_at)
		) $charset_collate;\n";
	}

	/**
	 * Get attempt items table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for attempt items table.
	 */
	private static function get_attempt_items_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_attempt_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			attempt_id BIGINT UNSIGNED NOT NULL,
			question_revision_id BIGINT UNSIGNED NOT NULL,
			order_index SMALLINT UNSIGNED NOT NULL,
			selected_answers_json TEXT DEFAULT NULL,
			first_view_at DATETIME DEFAULT NULL,
			last_answer_at DATETIME DEFAULT NULL,
			time_spent_ms INT UNSIGNED DEFAULT NULL,
			is_correct TINYINT(1) DEFAULT NULL,
			score_points DECIMAL(5,2) DEFAULT NULL,
			confidence TINYINT(1) DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY attempt_revision (attempt_id, question_revision_id),
			KEY question_revision_id (question_revision_id),
			KEY is_correct (is_correct)
		) $charset_collate;\n";
	}

	/**
	 * Get events table schema
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset_collate Character set and collation.
	 * @return string SQL for events table.
	 */
	private static function get_events_table( $charset_collate ) {
		global $wpdb;

		return "CREATE TABLE {$wpdb->prefix}ppq_events (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			attempt_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			event_type VARCHAR(50) NOT NULL,
			payload_json TEXT DEFAULT NULL,
			created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
			PRIMARY KEY  (id),
			KEY attempt_id (attempt_id),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) $charset_collate;\n";
	}
}
