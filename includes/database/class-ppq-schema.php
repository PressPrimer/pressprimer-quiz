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
class PressPrimer_Quiz_Schema {

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
		return implode( '', self::get_core_table_sql() );
	}

	/**
	 * Get the CREATE TABLE statement for every core table, keyed by table name.
	 *
	 * Single source of truth for the plugin's core schema. Both consumers derive
	 * from it: get_schema() concatenates the statements for dbDelta(), and
	 * get_expected_schema() parses them into a column map for the schema
	 * verifier. A column added to a CREATE TABLE definition therefore flows into
	 * both the installer and the verifier with no second list to keep in sync.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string,string> Map of full table name => CREATE TABLE SQL.
	 */
	private static function get_core_table_sql() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		return array(
			$wpdb->prefix . 'ppq_questions'          => self::get_questions_table( $charset_collate ),
			$wpdb->prefix . 'ppq_question_revisions' => self::get_question_revisions_table( $charset_collate ),
			$wpdb->prefix . 'ppq_categories'         => self::get_categories_table( $charset_collate ),
			$wpdb->prefix . 'ppq_question_tax'       => self::get_question_tax_table( $charset_collate ),
			$wpdb->prefix . 'ppq_banks'              => self::get_banks_table( $charset_collate ),
			$wpdb->prefix . 'ppq_bank_questions'     => self::get_bank_questions_table( $charset_collate ),
			$wpdb->prefix . 'ppq_quizzes'            => self::get_quizzes_table( $charset_collate ),
			$wpdb->prefix . 'ppq_quiz_items'         => self::get_quiz_items_table( $charset_collate ),
			$wpdb->prefix . 'ppq_quiz_rules'         => self::get_quiz_rules_table( $charset_collate ),
			$wpdb->prefix . 'ppq_groups'             => self::get_groups_table( $charset_collate ),
			$wpdb->prefix . 'ppq_group_members'      => self::get_group_members_table( $charset_collate ),
			$wpdb->prefix . 'ppq_assignments'        => self::get_assignments_table( $charset_collate ),
			$wpdb->prefix . 'ppq_attempts'           => self::get_attempts_table( $charset_collate ),
			$wpdb->prefix . 'ppq_attempt_items'      => self::get_attempt_items_table( $charset_collate ),
			$wpdb->prefix . 'ppq_events'             => self::get_events_table( $charset_collate ),
		);
	}

	/**
	 * Get the CREATE TABLE statement for a single core table.
	 *
	 * Returns the canonical dbDelta statement for one table, used by the schema
	 * verifier to recreate a missing core table from the same source get_schema()
	 * uses. Returns an empty string for tables not defined by the core plugin
	 * (e.g. addon tables registered only via the expected-schema filter — their
	 * own migrator owns creation).
	 *
	 * @since 3.0.0
	 *
	 * @param string $table Full table name (with prefix).
	 * @return string CREATE TABLE SQL, or '' if not a core table.
	 */
	public static function get_table_sql( string $table ): string {
		$tables = self::get_core_table_sql();

		return isset( $tables[ $table ] ) ? $tables[ $table ] : '';
	}

	/**
	 * Get the expected schema map for the current DB version.
	 *
	 * Returns [ table_name => [ column_name => column_definition ] ] derived from
	 * the same CREATE TABLE definitions get_schema() feeds to dbDelta() (see
	 * get_core_table_sql()). The schema verifier uses this map to detect missing
	 * columns/tables and to run the idempotent column-add for a repair.
	 * Comparison is presence-only in 3.0; the definition strings are the SQL
	 * fragments used when a missing column is re-added.
	 *
	 * Addons register their own tables via the pressprimer_quiz_expected_schema
	 * filter so they get the same check/heal coverage.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string,array<string,string>> Map of table => column => definition.
	 */
	public static function get_expected_schema() {
		$schema = array();

		foreach ( self::get_core_table_sql() as $table => $create_sql ) {
			$schema[ $table ] = self::parse_columns_from_create( $create_sql );
		}

		/**
		 * Filters the expected database schema map.
		 *
		 * Lets addons register their own tables so the schema verifier checks and
		 * heals them with the same machinery used for core tables. Each entry is
		 * keyed by the full table name (including $wpdb->prefix) and maps to one
		 * of two shapes:
		 *
		 *  - A simple [ column_name => column_definition ] array (always checked).
		 *  - A structured array [ 'columns' => [ column => definition ],
		 *    'ready' => bool ]. When 'ready' is false the table is skipped — an
		 *    addon sets this while its own migrations are still pending so the
		 *    verifier does not flag a table that has not been created yet.
		 *
		 * Column definitions are the SQL fragments used to add the column, e.g.
		 * 'ma_scoring_mode VARCHAR(32) DEFAULT NULL'.
		 *
		 * @since 3.0.0
		 *
		 * @param array $schema Map of table name => column definitions.
		 */
		$schema = apply_filters( 'pressprimer_quiz_expected_schema', $schema );

		return self::normalize_expected_schema( $schema );
	}

	/**
	 * Parse column definitions out of a CREATE TABLE statement.
	 *
	 * Reads the body of a dbDelta-style CREATE TABLE string and returns each
	 * column as [ column_name => full_column_definition ], skipping index and
	 * key lines (PRIMARY KEY, UNIQUE KEY, KEY, ...). Presence detection only
	 * needs the column names; the definitions are retained so the verifier can
	 * re-add a missing column from the canonical source.
	 *
	 * @since 3.0.0
	 *
	 * @param string $create_sql A CREATE TABLE statement.
	 * @return array<string,string> Map of column name => column definition.
	 */
	private static function parse_columns_from_create( $create_sql ) {
		$columns     = array();
		$lines       = explode( "\n", (string) $create_sql );
		$in_body     = false;
		$key_markers = array( 'PRIMARY', 'UNIQUE', 'KEY', 'INDEX', 'FULLTEXT', 'SPATIAL', 'CONSTRAINT', 'FOREIGN', 'CHECK' );

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			if ( '' === $trimmed ) {
				continue;
			}

			if ( ! $in_body ) {
				// The opening "CREATE TABLE ... (" line starts the column body.
				if ( 0 === stripos( $trimmed, 'CREATE TABLE' ) && false !== strpos( $trimmed, '(' ) ) {
					$in_body = true;
				}
				continue;
			}

			// The closing ") ..." line ends the column body.
			if ( 0 === strpos( $trimmed, ')' ) ) {
				break;
			}

			$trimmed = rtrim( $trimmed, ',' );
			$parts   = preg_split( '/\s+/', $trimmed );

			if ( empty( $parts[0] ) ) {
				continue;
			}

			// Skip index/key declarations — only real columns are wanted.
			if ( in_array( strtoupper( $parts[0] ), $key_markers, true ) ) {
				continue;
			}

			$column_name = trim( $parts[0], '`' );

			if ( '' !== $column_name ) {
				$columns[ $column_name ] = $trimmed;
			}
		}

		return $columns;
	}

	/**
	 * Normalize a (possibly filtered) expected-schema map.
	 *
	 * Accepts both supported entry shapes (see get_expected_schema()), drops
	 * tables whose registrant reports its migrations are not ready, unwraps the
	 * structured shape to [ column => definition ], and discards malformed
	 * entries so a bad filter registration cannot corrupt the verifier input.
	 *
	 * @since 3.0.0
	 *
	 * @param mixed $schema The filtered schema map.
	 * @return array<string,array<string,string>> Normalized table => column => definition map.
	 */
	private static function normalize_expected_schema( $schema ) {
		$normalized = array();

		if ( ! is_array( $schema ) ) {
			return $normalized;
		}

		foreach ( $schema as $table => $definition ) {
			if ( ! is_string( $table ) || '' === $table || ! is_array( $definition ) ) {
				continue;
			}

			// Structured shape: [ 'columns' => [...], 'ready' => bool ].
			if ( isset( $definition['columns'] ) && is_array( $definition['columns'] ) ) {
				$ready = ! isset( $definition['ready'] ) || (bool) $definition['ready'];

				if ( ! $ready ) {
					continue;
				}

				$columns = $definition['columns'];
			} else {
				// Simple shape: [ column => definition ].
				$columns = $definition;
			}

			$clean = array();

			foreach ( $columns as $column => $column_def ) {
				if ( is_string( $column ) && '' !== $column && is_string( $column_def ) ) {
					$clean[ $column ] = $column_def;
				}
			}

			if ( ! empty( $clean ) ) {
				$normalized[ $table ] = $clean;
			}
		}

		return $normalized;
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
			show_points TINYINT(1) NOT NULL DEFAULT 0,
			theme VARCHAR(50) NOT NULL DEFAULT 'default',
			theme_settings_json TEXT DEFAULT NULL,
			band_feedback_json TEXT DEFAULT NULL,
			generation_mode ENUM('fixed', 'dynamic') NOT NULL DEFAULT 'fixed',
			access_mode VARCHAR(20) NOT NULL DEFAULT 'default',
			login_message TEXT DEFAULT NULL,
			ma_scoring_mode VARCHAR(32) DEFAULT NULL,
			display_settings_json TEXT DEFAULT NULL,
			max_answers_per_question SMALLINT UNSIGNED DEFAULT NULL,
			display_density VARCHAR(20) NOT NULL DEFAULT 'default',
			pool_enabled TINYINT(1) NOT NULL DEFAULT 0,
			max_questions INT UNSIGNED DEFAULT NULL,
			enable_sr TINYINT(1) NOT NULL DEFAULT 0,
			is_review_quiz TINYINT(1) NOT NULL DEFAULT 0,
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
			source_url VARCHAR(2048) DEFAULT NULL,
			started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			finished_at DATETIME DEFAULT NULL,
			elapsed_ms INT UNSIGNED DEFAULT NULL,
			active_elapsed_ms INT UNSIGNED DEFAULT NULL,
			score_points DECIMAL(10,2) DEFAULT NULL,
			max_points DECIMAL(10,2) DEFAULT NULL,
			score_percent DECIMAL(5,2) DEFAULT NULL,
			passed TINYINT(1) DEFAULT NULL,
			curved_score DECIMAL(5,2) DEFAULT NULL,
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
			answer_order_json TEXT DEFAULT NULL,
			first_view_at DATETIME DEFAULT NULL,
			last_answer_at DATETIME DEFAULT NULL,
			time_spent_ms INT UNSIGNED DEFAULT NULL,
			is_correct TINYINT(1) DEFAULT NULL,
			score_points DECIMAL(5,2) DEFAULT NULL,
			confidence TINYINT(1) DEFAULT NULL,
			answer_checked_at DATETIME DEFAULT NULL,
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
