<?php
/**
 * Privacy integration.
 *
 * Registers a WordPress personal-data exporter and eraser for guest quiz
 * attempts, so site owners can honor GDPR access and erasure requests through
 * Tools → Export/Erase Personal Data. Operates on guest attempts only (those
 * keyed by guest_email); logged-in users' attempts are tied to their WP account
 * and covered by core's user-centric tooling (feature 007, FR-005).
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
 * Privacy integration class.
 *
 * @since 3.0.0
 */
class PressPrimer_Quiz_Privacy {

	/**
	 * Identifier shared by the exporter, eraser, and export group.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const SLUG = 'pressprimer-quiz';

	/**
	 * Attempts processed per batch (core's batching contract).
	 *
	 * @since 3.0.0
	 * @var int
	 */
	const PER_PAGE = 50;

	/**
	 * Register the exporter and eraser with WordPress core.
	 *
	 * @since 3.0.0
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Register the guest-attempt exporter.
	 *
	 * @since 3.0.0
	 *
	 * @param array $exporters Registered exporters.
	 * @return array Filtered exporters.
	 */
	public static function register_exporter( $exporters ) {
		$exporters[ self::SLUG ] = array(
			'exporter_friendly_name' => __( 'PressPrimer Quiz Guest Attempts', 'pressprimer-quiz' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	/**
	 * Register the guest-attempt eraser.
	 *
	 * @since 3.0.0
	 *
	 * @param array $erasers Registered erasers.
	 * @return array Filtered erasers.
	 */
	public static function register_eraser( $erasers ) {
		$erasers[ self::SLUG ] = array(
			'eraser_friendly_name' => __( 'PressPrimer Quiz Guest Attempts', 'pressprimer-quiz' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	/**
	 * Export guest attempts for an email address (one batch per call).
	 *
	 * @since 3.0.0
	 *
	 * @param string $email Email address from the export request.
	 * @param int    $page  1-based page number (core's paging contract).
	 * @return array { data: array, done: bool }.
	 */
	public static function export( $email, $page = 1 ) {
		global $wpdb;

		$email = sanitize_email( $email );
		$page  = max( 1, (int) $page );

		$export_items = array();

		if ( empty( $email ) ) {
			return array(
				'data' => $export_items,
				'done' => true,
			);
		}

		$attempts_table = $wpdb->prefix . 'ppq_attempts';
		$offset         = ( $page - 1 ) * self::PER_PAGE;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix; values are bound below.
		$sql = "SELECT id, quiz_id, started_at, finished_at, score_percent, passed, guest_consent, guest_consent_at
			FROM {$attempts_table} WHERE guest_email = %s ORDER BY id ASC LIMIT %d OFFSET %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; privacy export reads live data.
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with placeholders.
			$wpdb->prepare( $sql, $email, self::PER_PAGE, $offset )
		);

		foreach ( (array) $rows as $row ) {
			$export_items[] = array(
				'group_id'          => self::SLUG,
				'group_label'       => __( 'PressPrimer Quiz', 'pressprimer-quiz' ),
				'group_description' => __( 'Quiz attempts associated with your email address.', 'pressprimer-quiz' ),
				'item_id'           => 'ppq-attempt-' . (int) $row->id,
				'data'              => self::build_export_data( $row ),
			);
		}

		return array(
			'data' => $export_items,
			'done' => count( (array) $rows ) < self::PER_PAGE,
		);
	}

	/**
	 * Build the export data rows for one attempt.
	 *
	 * @since 3.0.0
	 *
	 * @param object $row Attempt row.
	 * @return array Export data ([ name, value ] pairs).
	 */
	private static function build_export_data( $row ) {
		$quiz       = class_exists( 'PressPrimer_Quiz_Quiz' ) ? PressPrimer_Quiz_Quiz::get( (int) $row->quiz_id ) : null;
		$quiz_title = ( $quiz && '' !== (string) $quiz->title )
			? $quiz->title
			/* translators: %d: quiz id. */
			: sprintf( __( 'Quiz #%d', 'pressprimer-quiz' ), (int) $row->quiz_id );

		$score = ( null !== $row->score_percent )
			? number_format_i18n( (float) $row->score_percent, 1 ) . '%'
			: __( 'Not scored', 'pressprimer-quiz' );

		if ( null === $row->guest_consent ) {
			$consent = __( 'Not offered', 'pressprimer-quiz' );
		} elseif ( 1 === (int) $row->guest_consent ) {
			$consent = __( 'Consented', 'pressprimer-quiz' );
		} else {
			$consent = __( 'Declined', 'pressprimer-quiz' );
		}

		$data = array(
			array(
				'name'  => __( 'Quiz', 'pressprimer-quiz' ),
				'value' => $quiz_title,
			),
			array(
				'name'  => __( 'Started', 'pressprimer-quiz' ),
				'value' => $row->started_at,
			),
			array(
				'name'  => __( 'Completed', 'pressprimer-quiz' ),
				'value' => $row->finished_at ? $row->finished_at : __( 'Not completed', 'pressprimer-quiz' ),
			),
			array(
				'name'  => __( 'Score', 'pressprimer-quiz' ),
				'value' => $score,
			),
			array(
				'name'  => __( 'Marketing consent', 'pressprimer-quiz' ),
				'value' => $consent,
			),
		);

		if ( 1 === (int) $row->guest_consent && ! empty( $row->guest_consent_at ) ) {
			$data[] = array(
				'name'  => __( 'Consent given at', 'pressprimer-quiz' ),
				'value' => $row->guest_consent_at,
			);
		}

		return $data;
	}

	/**
	 * Erase (anonymize) guest attempts for an email address (one batch per call).
	 *
	 * Anonymizes rather than deletes: the email, guest name, and marketing-consent
	 * fields are cleared while the anonymous attempt statistics remain (legitimate
	 * aggregate interest — the standard WordPress eraser pattern). Because
	 * anonymizing clears guest_email, the next batch no longer matches, so the
	 * operation drains naturally and a re-run reports nothing to remove.
	 *
	 * @since 3.0.0
	 *
	 * @param string $email Email address from the erase request.
	 * @param int    $page  1-based page number (unused; anonymizing advances the set).
	 * @return array { items_removed: bool, items_retained: bool, messages: array, done: bool }.
	 */
	public static function erase( $email, $page = 1 ) {
		global $wpdb;

		$email = sanitize_email( $email );

		$response = array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);

		if ( empty( $email ) ) {
			return $response;
		}

		$attempts_table = $wpdb->prefix . 'ppq_attempts';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix; value bound below.
		$id_sql = "SELECT id FROM {$attempts_table} WHERE guest_email = %s ORDER BY id ASC LIMIT %d";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; privacy erase reads live data.
		$ids = $wpdb->get_col(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with placeholders.
			$wpdb->prepare( $id_sql, $email, self::PER_PAGE )
		);
		$ids = array_map( 'intval', (array) $ids );

		if ( empty( $ids ) ) {
			return $response;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table from $wpdb->prefix; placeholders are fixed %d, ids bound below.
		$update_sql = "UPDATE {$attempts_table}
			SET guest_email = NULL, guest_name = NULL, guest_consent = NULL, guest_consent_at = NULL
			WHERE id IN ({$placeholders})";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; intentional anonymization.
		$wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is prepared with placeholders.
			$wpdb->prepare( $update_sql, $ids )
		);

		$response['items_removed'] = true;
		$response['messages'][]    = __( 'Guest quiz attempts were anonymized: the email address, name, and marketing-consent details were removed. The anonymous attempt statistics were retained.', 'pressprimer-quiz' );
		$response['done']          = count( $ids ) < self::PER_PAGE;

		/**
		 * Fires after a batch of guest attempts is anonymized for an erasure request.
		 *
		 * Addons (e.g. School xAPI references, Enterprise logs) use this to handle
		 * their own copies of the data per their policies.
		 *
		 * @since 3.0.0
		 *
		 * @param string $email       The erased email address.
		 * @param int[]  $attempt_ids Attempt ids anonymized in this batch.
		 */
		do_action( 'pressprimer_quiz_guest_data_erased', $email, $ids );

		return $response;
	}
}
