<?php
/**
 * Front-end app shell
 *
 * Mounts the React dashboard shell on a page via the [ppq_dashboard] shortcode
 * or the pressprimer-quiz/dashboard block. This file provides the render
 * container, the one-instance-per-page guard, the render-context guard, and
 * conditional asset enqueueing. The screen registry and boot payload arrive in
 * Prompt 2.3; the JS shell app and its build in Prompt 2.4.
 *
 * @package PressPrimer_Quiz
 * @subpackage Frontend
 * @since 3.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shell class
 *
 * @since 3.0.0
 */
class PressPrimer_Quiz_Shell {

	/**
	 * Registered script and style handle.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const HANDLE = 'ppq-shell';

	/**
	 * Whitelisted shell icon keys. Unknown keys fall back to 'default'.
	 *
	 * @since 3.0.0
	 * @var string[]
	 */
	const ICON_KEYS = array(
		'home',
		'results',
		'teaching',
		'reports',
		'tools',
		'groups',
		'assignments',
		'planner',
		'proctoring',
		'students',
		'settings',
		'default',
	);

	/**
	 * Premium tiers a locked nav entry may advertise.
	 *
	 * @since 3.0.0
	 * @var string[]
	 */
	const LOCKED_TIERS = array( 'educator', 'school', 'enterprise' );

	/**
	 * Whether the shell has already rendered on this request.
	 *
	 * @since 3.0.0
	 * @var bool
	 */
	private static $rendered = false;

	/**
	 * Register hooks.
	 *
	 * @since 3.0.0
	 */
	public static function init() {
		// The free plugin is consumer #1 of its own screen-registration contract.
		add_filter( 'pressprimer_quiz_shell_screens', array( __CLASS__, 'register_builtin_screens' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
	}

	/**
	 * Render the shell mount container.
	 *
	 * Renders only within the main query's singular content (the shortcode and
	 * block both route here). Returns an empty string for excerpts, widgets,
	 * feeds, REST/AJAX, and admin contexts, and for any instance after the
	 * first on a page (one instance per page; subsequent ones are ignored with
	 * a _doing_it_wrong notice).
	 *
	 * @since 3.0.0
	 *
	 * @param array $atts Optional shortcode/block attributes (unused in 2.2).
	 * @return string The mount container HTML, or '' when nothing should render.
	 */
	public static function render( $atts = array() ): string {
		unset( $atts );

		if ( ! self::should_render() ) {
			return '';
		}

		if ( self::$rendered ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The PressPrimer dashboard can appear only once per page. Additional instances are ignored.', 'pressprimer-quiz' ),
				'3.0.0'
			);
			return '';
		}

		self::$rendered = true;

		$html  = '<div id="ppq-shell" class="ppq-shell">';
		$html .= '<noscript>' . esc_html__( 'The dashboard requires JavaScript to be enabled.', 'pressprimer-quiz' ) . '</noscript>';
		$html .= '</div>';

		/**
		 * Fires after the shell mount container is rendered.
		 *
		 * Extension point for white-label and analytics. Runs once per page,
		 * after the (single) container is output.
		 *
		 * @since 3.0.0
		 *
		 * @param int $page_id ID of the post the shell rendered on (0 if unknown).
		 */
		do_action( 'pressprimer_quiz_shell_rendered', (int) get_the_ID() );

		return $html;
	}

	/**
	 * Conditionally enqueue the shell assets for the current page.
	 *
	 * Hooked to wp_enqueue_scripts. Mirrors the plugin's existing front-end
	 * asset gating: enqueue only when the queued singular post actually contains
	 * the dashboard block or shortcode.
	 *
	 * @since 3.0.0
	 */
	public static function maybe_enqueue_assets() {
		if ( ! self::current_post_has_shell() ) {
			return;
		}

		self::register_assets();

		$screens = self::get_visible_screens();

		// Enqueue the shell plus each visible screen's (registered) bundle handle.
		$handles = array( self::HANDLE );
		foreach ( $screens as $screen ) {
			if ( ! empty( $screen['handle'] ) ) {
				$handles[] = $screen['handle'];
			}
		}

		foreach ( array_unique( $handles ) as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_enqueue_script( $handle );
			}
		}

		if ( wp_style_is( self::HANDLE, 'registered' ) ) {
			wp_enqueue_style( self::HANDLE );
		}

		// Boot payload on the shell handle.
		if ( wp_script_is( self::HANDLE, 'registered' ) ) {
			wp_add_inline_script(
				self::HANDLE,
				'window.PPQShellData = ' . wp_json_encode( self::get_boot_payload( $screens ) ) . ';',
				'before'
			);
		}
	}

	/**
	 * Whether the current singular post contains the dashboard block/shortcode.
	 *
	 * @since 3.0.0
	 *
	 * @return bool True when the shell should load on this page.
	 */
	private static function current_post_has_shell(): bool {
		if ( is_admin() || ! is_singular() ) {
			return false;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return false;
		}

		return has_block( 'pressprimer-quiz/dashboard', $post )
			|| has_shortcode( $post->post_content, 'ppq_dashboard' )
			|| has_shortcode( $post->post_content, 'pressprimer_quiz_dashboard' );
	}

	/**
	 * Register the ppq-shell script and style handles.
	 *
	 * The shell app and its asset manifest are built in Prompt 2.4. The handles
	 * are registered now (with fallback dependencies when the manifest is absent)
	 * so the screen registry and boot payload are fully wired; the bundle file
	 * resolves automatically once the build exists.
	 *
	 * @since 3.0.0
	 */
	private static function register_assets() {
		if ( wp_script_is( self::HANDLE, 'registered' ) ) {
			return;
		}

		$asset_file = PRESSPRIMER_QUIZ_PLUGIN_PATH . 'build/shell.asset.php';
		$deps       = array( 'wp-element', 'wp-i18n', 'wp-api-fetch' );
		$version    = PRESSPRIMER_QUIZ_VERSION;

		if ( file_exists( $asset_file ) ) {
			$asset   = require $asset_file;
			$deps    = isset( $asset['dependencies'] ) ? $asset['dependencies'] : $deps;
			$version = isset( $asset['version'] ) ? $asset['version'] : $version;
		}

		wp_register_script(
			self::HANDLE,
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/shell.js',
			$deps,
			$version,
			true
		);

		// @wordpress/scripts emits CSS imported into a JS entry as
		// build/style-<entry>.css, with an auto-generated -rtl.css alongside.
		wp_register_style(
			self::HANDLE,
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/style-shell.css',
			array(),
			$version
		);
		wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
	}

	/**
	 * Whether the shell may render in the current context.
	 *
	 * Only the main query's singular content qualifies. Excerpt, widget, feed,
	 * REST, AJAX, cron, and admin contexts are excluded so the shortcode/block
	 * never mounts the app outside a real page view.
	 *
	 * @since 3.0.0
	 *
	 * @return bool True when rendering is allowed.
	 */
	private static function should_render(): bool {
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ( defined( 'DOING_AJAX' ) && DOING_AJAX )
			|| ( defined( 'DOING_CRON' ) && DOING_CRON )
			|| is_admin()
			|| is_feed() ) {
			return false;
		}

		return is_singular() && is_main_query() && in_the_loop();
	}

	/**
	 * Register the free plugin's built-in screens and upsell placeholders.
	 *
	 * Hooked to pressprimer_quiz_shell_screens. Adds the student Home and My
	 * Results screens plus a locked "Teaching" entry that upsells the Educator
	 * front-end dashboard. Locked entries are always registered here; their
	 * visibility is resolved later in get_visible_screens().
	 *
	 * @since 3.0.0
	 *
	 * @param array $screens Registered screens keyed by id.
	 * @return array Screens with the built-in entries added.
	 */
	public static function register_builtin_screens( $screens ) {
		if ( ! is_array( $screens ) ) {
			$screens = array();
		}

		$screens['home'] = array(
			'label'      => __( 'Home', 'pressprimer-quiz' ),
			'group'      => 'student',
			'capability' => 'read',
			'handle'     => self::HANDLE,
			'order'      => 0,
			'icon'       => 'home',
		);

		$screens['my-results'] = array(
			'label'      => __( 'My Results', 'pressprimer-quiz' ),
			'group'      => 'student',
			'capability' => 'pressprimer_quiz_view_results_own',
			'handle'     => self::HANDLE,
			'order'      => 10,
			'icon'       => 'results',
		);

		$screens['ppq-teaching-locked'] = array(
			'label'       => __( 'Teaching', 'pressprimer-quiz' ),
			'group'       => 'teaching',
			'order'       => 0,
			'icon'        => 'teaching',
			'locked'      => true,
			'locked_tier' => 'educator',
		);

		return $screens;
	}

	/**
	 * Collect and validate all registered screens.
	 *
	 * @since 3.0.0
	 *
	 * @return array Validated screens keyed by id.
	 */
	public static function get_screens() {
		/**
		 * Filters the front-end shell screen registry.
		 *
		 * Each entry is keyed by a unique screen id. A normal screen requires
		 * 'label', 'capability', and 'handle' (a registered script); 'group',
		 * 'order', and 'icon' are optional. A locked upsell entry sets
		 * 'locked' => true plus 'locked_tier' and omits capability/handle.
		 *
		 * @since 3.0.0
		 *
		 * @param array $screens Screens keyed by id.
		 */
		$raw = apply_filters( 'pressprimer_quiz_shell_screens', array() );

		$screens = array();

		if ( ! is_array( $raw ) ) {
			return $screens;
		}

		foreach ( $raw as $id => $entry ) {
			$valid = self::validate_screen( $id, $entry );
			if ( null !== $valid ) {
				$screens[ $valid['id'] ] = $valid;
			}
		}

		return $screens;
	}

	/**
	 * Validate and normalize one screen registration.
	 *
	 * Drops (and debug-logs) entries missing required fields or referencing an
	 * unregistered script handle. Icons are whitelisted; unknown icons fall back
	 * to 'default'.
	 *
	 * @since 3.0.0
	 *
	 * @param mixed $id    Screen id from the registry.
	 * @param mixed $entry Screen definition.
	 * @return array|null Normalized entry, or null when invalid.
	 */
	private static function validate_screen( $id, $entry ) {
		if ( ! is_string( $id ) || '' === $id || ! is_array( $entry ) ) {
			self::debug_log( 'invalid screen id or definition' );
			return null;
		}

		$id    = sanitize_key( $id );
		$group = ( isset( $entry['group'] ) && is_string( $entry['group'] ) && '' !== $entry['group'] ) ? sanitize_key( $entry['group'] ) : 'student';
		$order = isset( $entry['order'] ) ? (int) $entry['order'] : 10;
		$icon  = self::sanitize_icon( isset( $entry['icon'] ) ? $entry['icon'] : '' );
		$label = ( isset( $entry['label'] ) && is_string( $entry['label'] ) ) ? $entry['label'] : '';

		// Locked upsell entry: no capability/handle, but needs a valid tier.
		if ( ! empty( $entry['locked'] ) ) {
			$tier = isset( $entry['locked_tier'] ) ? (string) $entry['locked_tier'] : '';

			if ( '' === $label || ! in_array( $tier, self::LOCKED_TIERS, true ) ) {
				self::debug_log( "locked screen '{$id}' missing label or valid locked_tier" );
				return null;
			}

			return array(
				'id'          => $id,
				'label'       => $label,
				'group'       => $group,
				'order'       => $order,
				'icon'        => $icon,
				'locked'      => true,
				'locked_tier' => $tier,
			);
		}

		// Normal screen: label, capability, and a registered handle are required.
		$capability = ( isset( $entry['capability'] ) && is_string( $entry['capability'] ) ) ? $entry['capability'] : '';
		$handle     = ( isset( $entry['handle'] ) && is_string( $entry['handle'] ) ) ? $entry['handle'] : '';

		if ( '' === $label || '' === $capability || '' === $handle ) {
			self::debug_log( "screen '{$id}' missing label, capability, or handle" );
			return null;
		}

		if ( ! wp_script_is( $handle, 'registered' ) ) {
			self::debug_log( "screen '{$id}' references unregistered handle '{$handle}'" );
			return null;
		}

		return array(
			'id'         => $id,
			'label'      => $label,
			'group'      => $group,
			'order'      => $order,
			'icon'       => $icon,
			'capability' => $capability,
			'handle'     => $handle,
		);
	}

	/**
	 * Get the nav group definitions (defaults merged with the addon filter).
	 *
	 * @since 3.0.0
	 *
	 * @return array Groups keyed by id => [ label, order ].
	 */
	public static function get_groups() {
		$defaults = array(
			'student'  => array(
				'label' => __( 'Student', 'pressprimer-quiz' ),
				'order' => 10,
			),
			'teaching' => array(
				'label' => __( 'Teaching', 'pressprimer-quiz' ),
				'order' => 20,
			),
			'reports'  => array(
				'label' => __( 'Reports', 'pressprimer-quiz' ),
				'order' => 30,
			),
			'tools'    => array(
				'label' => __( 'Tools', 'pressprimer-quiz' ),
				'order' => 40,
			),
		);

		/**
		 * Filters the front-end shell nav groups.
		 *
		 * @since 3.0.0
		 *
		 * @param array $groups Groups keyed by id => [ label, order ].
		 */
		$groups = apply_filters( 'pressprimer_quiz_shell_groups', $defaults );

		$clean = array();

		if ( is_array( $groups ) ) {
			foreach ( $groups as $key => $group ) {
				if ( ! is_string( $key ) || '' === $key || ! is_array( $group ) ) {
					continue;
				}

				$clean[ sanitize_key( $key ) ] = array(
					'label' => ( isset( $group['label'] ) && is_string( $group['label'] ) ) ? $group['label'] : ucfirst( $key ),
					'order' => isset( $group['order'] ) ? (int) $group['order'] : 50,
				);
			}
		}

		return $clean;
	}

	/**
	 * Get the screens visible to the current user, sorted for the nav.
	 *
	 * Normal screens are included when the current user has their capability;
	 * locked entries are included only when their tier is inactive and upsells
	 * are not suppressed (each then gains an upgrade_url). Sorted by group
	 * order, then entry order, then id.
	 *
	 * @since 3.0.0
	 *
	 * @return array Visible, sorted screens (numerically indexed).
	 */
	public static function get_visible_screens() {
		$screens = self::get_screens();
		$groups  = self::get_groups();

		/**
		 * Filters whether front-end upsell (locked) entries are suppressed.
		 *
		 * Defaults to suppressed when the Enterprise (white-label) tier is
		 * active, matching the wp-admin Upgrade page.
		 *
		 * @since 3.0.0
		 *
		 * @param bool $suppressed Whether upsells are suppressed.
		 */
		$upsells_suppressed = (bool) apply_filters( 'pressprimer_quiz_shell_suppress_upsells', pressprimer_quiz_has_addon( 'enterprise' ) );
		$upgrade_url        = admin_url( 'admin.php?page=pressprimer-quiz-upgrade' );

		// Locked-card copy comes from the upgrade-page content source (decision 005).
		$tiers = ( class_exists( 'PressPrimer_Quiz_Upgrade_Page' ) && method_exists( 'PressPrimer_Quiz_Upgrade_Page', 'get_tiers' ) )
			? PressPrimer_Quiz_Upgrade_Page::get_tiers()
			: array();

		$visible = array();

		foreach ( $screens as $screen ) {
			if ( ! empty( $screen['locked'] ) ) {
				if ( $upsells_suppressed || pressprimer_quiz_has_addon( $screen['locked_tier'] ) ) {
					continue;
				}

				$tier = isset( $tiers[ $screen['locked_tier'] ] ) ? $tiers[ $screen['locked_tier'] ] : array();

				$screen['upgrade_url']      = $upgrade_url;
				$screen['tier_name']        = isset( $tier['name'] ) ? $tier['name'] : '';
				$screen['tier_description'] = isset( $tier['description'] ) ? $tier['description'] : '';
				$screen['tier_highlights']  = ( isset( $tier['highlights'] ) && is_array( $tier['highlights'] ) ) ? array_values( $tier['highlights'] ) : array();
				$screen['tier_url']         = isset( $tier['url'] ) ? $tier['url'] : '';
				$visible[]                  = $screen;
				continue;
			}

			if ( current_user_can( $screen['capability'] ) ) {
				$visible[] = $screen;
			}
		}

		usort(
			$visible,
			static function ( $a, $b ) use ( $groups ) {
				$ga = isset( $groups[ $a['group'] ]['order'] ) ? $groups[ $a['group'] ]['order'] : 50;
				$gb = isset( $groups[ $b['group'] ]['order'] ) ? $groups[ $b['group'] ]['order'] : 50;

				if ( $ga !== $gb ) {
					return $ga <=> $gb;
				}

				if ( $a['order'] !== $b['order'] ) {
					return $a['order'] <=> $b['order'];
				}

				return strcmp( $a['id'], $b['id'] );
			}
		);

		return $visible;
	}

	/**
	 * Build the window.PPQShellData boot payload.
	 *
	 * @since 3.0.0
	 *
	 * @param array|null $visible_screens Pre-computed visible screens, or null to compute.
	 * @return array Boot payload.
	 */
	public static function get_boot_payload( $visible_screens = null ) {
		if ( null === $visible_screens ) {
			$visible_screens = self::get_visible_screens();
		}

		$user = wp_get_current_user();

		$client_screens = array();

		foreach ( $visible_screens as $screen ) {
			$entry = array(
				'id'    => $screen['id'],
				'label' => $screen['label'],
				'group' => $screen['group'],
				'icon'  => $screen['icon'],
				'order' => $screen['order'],
			);

			if ( ! empty( $screen['locked'] ) ) {
				$entry['locked']          = true;
				$entry['lockedTier']      = $screen['locked_tier'];
				$entry['upgradeUrl']      = isset( $screen['upgrade_url'] ) ? $screen['upgrade_url'] : '';
				$entry['tierName']        = isset( $screen['tier_name'] ) ? $screen['tier_name'] : '';
				$entry['tierDescription'] = isset( $screen['tier_description'] ) ? $screen['tier_description'] : '';
				$entry['tierHighlights']  = isset( $screen['tier_highlights'] ) ? $screen['tier_highlights'] : array();
				$entry['tierUrl']         = isset( $screen['tier_url'] ) ? $screen['tier_url'] : '';
			}

			$client_screens[] = $entry;
		}

		return array(
			'restUrl'   => esc_url_raw( rest_url( 'ppq/v1/' ) ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'user'      => array(
				'id'   => $user ? (int) $user->ID : 0,
				'name' => ( $user && $user->exists() ) ? $user->display_name : '',
			),
			'screens'   => $client_screens,
			'groups'    => self::get_groups(),
			// Bare login URL; the shell appends redirect_to with the current
			// hash route client-side so login returns to the exact screen.
			'loginUrl'  => wp_login_url(),
			'branding'  => self::get_branding(),
		);
	}

	/**
	 * Get the (filterable) shell branding for the boot payload.
	 *
	 * @since 3.0.0
	 *
	 * @return array [ logoUrl, productName ].
	 */
	private static function get_branding() {
		/**
		 * Filters the front-end shell branding.
		 *
		 * Both values default to empty: the shell shows no brand on the front end
		 * and suffixes document titles with the site name. Enterprise white-label
		 * sets a logo and/or product name to brand the chrome.
		 *
		 * @since 3.0.0
		 *
		 * @param array $branding [ 'logo_url' => string, 'product_name' => string ].
		 */
		$branding = apply_filters(
			'pressprimer_quiz_shell_branding',
			array(
				'logo_url'     => '',
				'product_name' => '',
			)
		);

		$logo_url     = ( is_array( $branding ) && isset( $branding['logo_url'] ) ) ? esc_url_raw( $branding['logo_url'] ) : '';
		$product_name = ( is_array( $branding ) && isset( $branding['product_name'] ) ) ? sanitize_text_field( $branding['product_name'] ) : '';

		return array(
			'logoUrl'     => $logo_url,
			'productName' => $product_name,
			'siteName'    => get_bloginfo( 'name' ),
		);
	}

	/**
	 * Whitelist an icon key.
	 *
	 * @since 3.0.0
	 *
	 * @param mixed $icon Requested icon key.
	 * @return string A whitelisted icon key ('default' when unknown).
	 */
	private static function sanitize_icon( $icon ) {
		$icon = is_string( $icon ) ? $icon : '';

		return in_array( $icon, self::ICON_KEYS, true ) ? $icon : 'default';
	}

	/**
	 * Log a registry validation problem when debugging.
	 *
	 * @since 3.0.0
	 *
	 * @param string $message Message.
	 */
	private static function debug_log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only diagnostic.
			error_log( '[PressPrimer Quiz shell] ' . $message );
		}
	}
}
