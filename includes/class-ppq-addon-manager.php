<?php
/**
 * Addon Manager class
 *
 * Central registry for managing premium addons and their integrations.
 *
 * @package PressPrimer_Quiz
 * @since 2.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Addon Manager class
 *
 * Implements singleton pattern for addon registration and compatibility checking.
 * Premium addons register themselves through this manager to enable their features.
 *
 * @since 2.0.0
 */
class PressPrimer_Quiz_Addon_Manager {

	/**
	 * Singleton instance
	 *
	 * @since 2.0.0
	 * @var PressPrimer_Quiz_Addon_Manager|null
	 */
	private static $instance = null;

	/**
	 * Registered addons array
	 *
	 * @since 2.0.0
	 * @var array
	 */
	private $addons = [];

	/**
	 * Get singleton instance
	 *
	 * Returns the single instance of the addon manager.
	 * Creates the instance if it doesn't exist.
	 *
	 * @since 2.0.0
	 *
	 * @return PressPrimer_Quiz_Addon_Manager The addon manager instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor
	 *
	 * Prevents direct instantiation. Use get_instance() instead.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		// Constructor is private for singleton.
	}

	/**
	 * Initialize the addon manager
	 *
	 * Sets up action hooks for addon registration.
	 *
	 * @since 2.0.0
	 */
	public function init() {
		/**
		 * Fires when the addon manager is ready for addons to register.
		 *
		 * Premium addons should hook into this action to register themselves
		 * via the ppq_register_addon() function.
		 *
		 * @since 2.0.0
		 *
		 * @param PressPrimer_Quiz_Addon_Manager $manager The addon manager instance.
		 */
		do_action( 'pressprimer_quiz_register_addons', $this );
	}

	/**
	 * Register an addon
	 *
	 * Addons call this method to register themselves with the manager.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug   Unique addon identifier (e.g., 'ppq-groups', 'ppq-import-export').
	 * @param array  $config Addon configuration:
	 *                       - name: (string) Display name
	 *                       - version: (string) Addon version
	 *                       - file: (string) Main addon file path
	 *                       - requires: (string) Minimum core version required
	 *                       - tier: (string) 'educator', 'school', or 'enterprise'.
	 * @return bool True on success, false if addon already registered.
	 */
	public function register( $slug, $config ) {
		if ( isset( $this->addons[ $slug ] ) ) {
			return false;
		}

		$defaults = [
			'name'     => '',
			'version'  => '1.0.0',
			'file'     => '',
			'requires' => '2.0.0',
			'tier'     => 'educator',
		];

		$this->addons[ $slug ] = wp_parse_args( $config, $defaults );

		/**
		 * Fires after an addon is registered.
		 *
		 * @since 2.0.0
		 *
		 * @param string $slug   The addon slug.
		 * @param array  $config The addon configuration.
		 */
		do_action( 'pressprimer_quiz_addon_registered', $slug, $this->addons[ $slug ] );

		return true;
	}

	/**
	 * Check if an addon is registered
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Addon slug to check.
	 * @return bool True if addon is registered.
	 */
	public function is_registered( $slug ) {
		return isset( $this->addons[ $slug ] );
	}

	/**
	 * Check if an addon is active
	 *
	 * An addon is active if it's registered and compatible with the current core version.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Addon slug to check.
	 * @return bool True if addon is active and compatible.
	 */
	public function is_active( $slug ) {
		if ( ! $this->is_registered( $slug ) ) {
			return false;
		}

		return $this->is_compatible( $slug );
	}

	/**
	 * Check if an addon is compatible with current core version
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Addon slug to check.
	 * @return bool True if compatible, false otherwise.
	 */
	public function is_compatible( $slug ) {
		if ( ! $this->is_registered( $slug ) ) {
			return false;
		}

		$addon        = $this->addons[ $slug ];
		$core_version = defined( 'PRESSPRIMER_QUIZ_VERSION' ) ? PRESSPRIMER_QUIZ_VERSION : '1.0.0';

		return version_compare( $core_version, $addon['requires'], '>=' );
	}

	/**
	 * Get addon configuration
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Addon slug.
	 * @return array|null Addon config array or null if not registered.
	 */
	public function get_addon( $slug ) {
		return isset( $this->addons[ $slug ] ) ? $this->addons[ $slug ] : null;
	}

	/**
	 * Get all registered addons
	 *
	 * @since 2.0.0
	 *
	 * @return array All registered addons.
	 */
	public function get_all() {
		return $this->addons;
	}

	/**
	 * Get addons by tier
	 *
	 * @since 2.0.0
	 *
	 * @param string $tier Tier name: 'educator', 'school', or 'enterprise'.
	 * @return array Addons in the specified tier.
	 */
	public function get_by_tier( $tier ) {
		return array_filter(
			$this->addons,
			function ( $addon ) use ( $tier ) {
				return $addon['tier'] === $tier;
			}
		);
	}

	/**
	 * Check if any premium addon is active
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if at least one addon is active.
	 */
	public function has_active_addons() {
		foreach ( array_keys( $this->addons ) as $slug ) {
			if ( $this->is_active( $slug ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get count of active addons
	 *
	 * @since 2.0.0
	 *
	 * @return int Number of active addons.
	 */
	public function get_active_count() {
		$count = 0;
		foreach ( array_keys( $this->addons ) as $slug ) {
			if ( $this->is_active( $slug ) ) {
				++$count;
			}
		}
		return $count;
	}
}
