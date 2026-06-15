<?php
/**
 * Quiz Settings Template model
 *
 * Represents a named, partial snapshot of quiz settings (feature 003). A
 * template stores only quiz-level *settings* keys as a JSON object; identity
 * and content (title, questions, owner, …) are never part of a template.
 * Built-in presets are code/filter only and are never rows in this table.
 *
 * @package PressPrimer_Quiz
 * @subpackage Models
 * @since 3.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Quiz settings template model class
 *
 * Persists templates to {prefix}ppq_quiz_templates and reuses the Quiz model's
 * canonical per-field sanitizers so a template payload is cleaned with the
 * exact rules the quiz editor applies — on write and (later) on apply.
 *
 * @since 3.0.0
 */
class PressPrimer_Quiz_Quiz_Template extends PressPrimer_Quiz_Model {

	/**
	 * Maximum byte size of the stored settings_json payload.
	 *
	 * Templates are small; the cap guards against an oversized theme/display
	 * JSON blob bloating the row (edge case in feature 003).
	 *
	 * @since 3.0.0
	 * @var int
	 */
	const MAX_SETTINGS_BYTES = 65535;

	/**
	 * Template name (label).
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public $name;

	/**
	 * Optional description.
	 *
	 * @since 3.0.0
	 * @var string|null
	 */
	public $description;

	/**
	 * JSON object of sanitized quiz settings keys.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public $settings_json;

	/**
	 * Author user ID.
	 *
	 * @since 3.0.0
	 * @var int
	 */
	public $created_by;

	/**
	 * Creation timestamp (MySQL datetime).
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public $created_at;

	/**
	 * Last-updated timestamp (MySQL datetime).
	 *
	 * @since 3.0.0
	 * @var string
	 */
	public $updated_at;

	/**
	 * Get table name
	 *
	 * @since 3.0.0
	 *
	 * @return string Table name without prefix.
	 */
	protected static function get_table_name() {
		return 'ppq_quiz_templates';
	}

	/**
	 * Get fillable fields
	 *
	 * The created_at/updated_at columns are intentionally omitted: the table
	 * defaults created_at to CURRENT_TIMESTAMP and updated_at carries ON UPDATE
	 * CURRENT_TIMESTAMP, so the database stamps them.
	 *
	 * @since 3.0.0
	 *
	 * @return array Field names that can be mass-assigned.
	 */
	protected static function get_fillable_fields() {
		return array(
			'name',
			'description',
			'settings_json',
			'created_by',
		);
	}

	/**
	 * Create a new template.
	 *
	 * Accepts: name (required, ≤100 chars), description (optional), settings (an
	 * array of quiz settings keys — unknown keys dropped, values sanitized via
	 * the Quiz model), created_by (defaults to the current user). Returns the
	 * new ID or a WP_Error (missing name, oversized payload, DB failure).
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Template data.
	 * @return int|WP_Error New template ID, or WP_Error on failure.
	 */
	public static function create( array $data ) {
		$prepared = self::prepare_for_write( $data );

		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$prepared['created_by'] = isset( $data['created_by'] ) && absint( $data['created_by'] ) > 0
			? absint( $data['created_by'] )
			: get_current_user_id();

		return parent::create( $prepared );
	}

	/**
	 * Save changes to an existing template.
	 *
	 * Re-runs name/description/settings sanitization (defense in depth: a
	 * hand-edited row is cleaned again on the next save) before persisting.
	 *
	 * @since 3.0.0
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function save() {
		if ( empty( $this->id ) ) {
			return new WP_Error(
				'ppq_no_id',
				__( 'Cannot save template without ID.', 'pressprimer-quiz' )
			);
		}

		$prepared = self::prepare_for_write(
			array(
				'name'        => $this->name,
				'description' => $this->description,
				'settings'    => $this->get_settings(),
			)
		);

		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$this->name          = $prepared['name'];
		$this->description   = $prepared['description'];
		$this->settings_json = $prepared['settings_json'];

		return parent::save();
	}

	/**
	 * Decode the stored settings payload to an associative array.
	 *
	 * @since 3.0.0
	 *
	 * @return array Settings key => value pairs (empty array if unset/invalid).
	 */
	public function get_settings() {
		if ( empty( $this->settings_json ) ) {
			return array();
		}

		$decoded = json_decode( $this->settings_json, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Replace the template's settings with a sanitized payload.
	 *
	 * @since 3.0.0
	 *
	 * @param array $settings Raw settings key => value pairs.
	 * @return void
	 */
	public function set_settings( array $settings ) {
		$clean   = PressPrimer_Quiz_Quiz::sanitize_settings( $settings );
		$encoded = wp_json_encode( $clean );

		$this->settings_json = ( false !== $encoded ) ? $encoded : '{}';
	}

	/**
	 * Validate and sanitize a write payload into stored columns.
	 *
	 * Shared by create() and save(). Returns [ name, description, settings_json ]
	 * or a WP_Error. settings are sanitized through the Quiz model's canonical
	 * sanitizers (single source) and capped at MAX_SETTINGS_BYTES.
	 *
	 * @since 3.0.0
	 *
	 * @param array $data Raw data with keys: name, description, settings.
	 * @return array|WP_Error Prepared columns, or WP_Error on failure.
	 */
	private static function prepare_for_write( array $data ) {
		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';

		if ( '' === $name ) {
			return new WP_Error(
				'ppq_template_name_required',
				__( 'Template name is required.', 'pressprimer-quiz' )
			);
		}

		if ( mb_strlen( $name ) > 100 ) {
			$name = mb_substr( $name, 0, 100 );
		}

		$description = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';

		$settings_input = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
		$settings       = PressPrimer_Quiz_Quiz::sanitize_settings( $settings_input );

		$settings_json = wp_json_encode( $settings );

		if ( false === $settings_json ) {
			$settings_json = '{}';
		}

		if ( strlen( $settings_json ) > self::MAX_SETTINGS_BYTES ) {
			return new WP_Error(
				'ppq_template_too_large',
				sprintf(
					/* translators: %d: maximum settings size in kilobytes. */
					__( 'Template settings exceed the maximum size of %dKB.', 'pressprimer-quiz' ),
					(int) round( self::MAX_SETTINGS_BYTES / 1024 )
				)
			);
		}

		return array(
			'name'          => $name,
			'description'   => $description,
			'settings_json' => $settings_json,
		);
	}
}
