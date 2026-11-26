<?php
/**
 * Upgrade Page Controller
 *
 * Registers and renders the "Upgrade" submenu shown to free-only users.
 * The page surfaces a comparison table and tier cards for the three premium
 * addons (Educator, School, Enterprise). The menu, inline highlight styles,
 * and asset enqueue are all skipped when the Enterprise addon is active —
 * because at that point the user already has the full feature set.
 *
 * @package PressPrimer_Quiz
 * @subpackage Admin
 * @since 2.3.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Upgrade page class.
 *
 * @since 2.3.0
 */
class PressPrimer_Quiz_Upgrade_Page {

	/**
	 * Menu slug for the Upgrade page.
	 *
	 * @since 2.3.0
	 * @var string
	 */
	const MENU_SLUG = 'pressprimer-quiz-upgrade';

	/**
	 * Initialize hooks.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function init() {
		// Register the menu after the core PPQ menu (priority 99 so it lands
		// after Dashboard / Quizzes / Settings, but before WP's default 100).
		add_action( 'admin_menu', array( $this, 'register_menu' ), 99 );
		add_action( 'admin_head', array( $this, 'menu_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the Upgrade submenu, conditional on Enterprise not being active.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( $this->enterprise_addon_active() ) {
			return;
		}

		add_submenu_page(
			'pressprimer-quiz',
			esc_html__( 'Upgrade PressPrimer Quiz', 'pressprimer-quiz' ),
			esc_html__( 'Upgrade', 'pressprimer-quiz' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Check whether the Enterprise addon is currently active.
	 *
	 * The Upgrade page hides itself only at the top tier — users on Educator
	 * or School still see "Upgrade" because there are higher tiers available
	 * to them.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True when the Enterprise addon is active.
	 */
	public function enterprise_addon_active() {
		// Constant fallback: the Enterprise plugin defines
		// PRESSPRIMER_QUIZ_ENTERPRISE_VERSION on load (before its addon
		// manager registration runs). This catches the rare case where
		// addon registration fires late or is skipped — e.g., a third
		// party hooks `pressprimer_quiz_register_addons` to remove
		// Enterprise's registration, or load ordering shifts. The constant
		// is the simplest "is the plugin loaded?" signal we can rely on.
		if ( defined( 'PRESSPRIMER_QUIZ_ENTERPRISE_VERSION' ) ) {
			return true;
		}

		if ( ! function_exists( 'pressprimer_quiz_addon_active' ) ) {
			// Defensive — if the helper is missing, show the page.
			return false;
		}

		return pressprimer_quiz_addon_active( 'ppq-enterprise' );
	}

	/**
	 * Output inline admin styles to highlight the Upgrade menu item.
	 *
	 * Targets the submenu link by its slug-anchor href so we don't paint
	 * unrelated submenu items. Skips output entirely when Enterprise is
	 * active (the menu item itself isn't registered in that case).
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function menu_styles() {
		if ( $this->enterprise_addon_active() ) {
			return;
		}

		?>
		<style>
			#adminmenu .wp-submenu a[href$="<?php echo esc_attr( self::MENU_SLUG ); ?>"],
			#adminmenu .wp-submenu li.current a[href$="<?php echo esc_attr( self::MENU_SLUG ); ?>"] {
				background-color: #1f7a3a;
				color: #fff !important;
				font-weight: 700;
			}
			#adminmenu .wp-submenu a[href$="<?php echo esc_attr( self::MENU_SLUG ); ?>"]:hover,
			#adminmenu .wp-submenu a[href$="<?php echo esc_attr( self::MENU_SLUG ); ?>"]:focus {
				background-color: #186730;
				color: #fff !important;
			}
		</style>
		<?php
	}

	/**
	 * Enqueue assets only on the upgrade page.
	 *
	 * @since 2.3.0
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// $hook for submenus under a top-level slug looks like
		// "pressprimer-quiz_page_pressprimer-quiz-upgrade". Cheap suffix check.
		if ( false === strpos( (string) $hook, self::MENU_SLUG ) ) {
			return;
		}

		if ( $this->enterprise_addon_active() ) {
			return;
		}

		wp_enqueue_style(
			'ppq-upgrade-page',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/css/admin/upgrade-page.css',
			array(),
			PRESSPRIMER_QUIZ_VERSION
		);
	}

	/**
	 * Render the upgrade page.
	 *
	 * Loads the view file with the prepared data in scope. The view file
	 * uses esc_url(), esc_html(), etc. on every dynamic value.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function render_page() {
		// Defensive — if Enterprise was activated after the menu item was
		// registered (e.g., admin loaded the page in one tab, then activated
		// Enterprise in another), the direct-URL access should fail closed.
		if ( $this->enterprise_addon_active() ) {
			wp_die(
				esc_html__( 'You already have the full PressPrimer Quiz feature set.', 'pressprimer-quiz' ),
				esc_html__( 'Not Available', 'pressprimer-quiz' ),
				array( 'response' => 403 )
			);
		}

		$features          = PressPrimer_Quiz_Premium_Catalog::get_comparison_rows();
		$tiers             = PressPrimer_Quiz_Premium_Catalog::get_tiers();
		$pricing_url       = PressPrimer_Quiz_Premium_Catalog::PRICING_URL . '#pricing';
		$logo_url          = PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/images/PressPrimer-Logo-White.svg';
		$hero_mascot_url   = PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/images/mascot-waving.png';
		$footer_mascot_url = PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/images/mascot-celebrating-confetti.png';

		// Make $this available to the view so render_cell_value() is callable.
		$upgrade_page = $this;

		include PRESSPRIMER_QUIZ_PLUGIN_PATH . 'includes/admin/views/upgrade-page.php';
	}

	/**
	 * Render a single comparison-table cell value.
	 *
	 * Converts the raw row value into display HTML:
	 *   - true   → green checkmark
	 *   - false  → em dash
	 *   - string → escaped text (allows row-specific notes like "Limited")
	 *
	 * @since 2.3.0
	 *
	 * @param mixed $value Raw value from a feature row's tier column.
	 * @return string HTML to render inside the cell.
	 */
	public function render_cell_value( $value ) {
		if ( true === $value ) {
			return '<span class="ppq-upgrade-cell-yes" aria-label="' . esc_attr__( 'Included', 'pressprimer-quiz' ) . '">&#10003;</span>';
		}

		if ( false === $value ) {
			return '<span class="ppq-upgrade-cell-no" aria-label="' . esc_attr__( 'Not included', 'pressprimer-quiz' ) . '">&mdash;</span>';
		}

		return '<span class="ppq-upgrade-cell-note">' . esc_html( (string) $value ) . '</span>';
	}

	/**
	 * Comparison table rows.
	 *
	 * @since 2.3.0
	 * @deprecated 3.2.0 Use PressPrimer_Quiz_Premium_Catalog::get_comparison_rows().
	 *
	 * @return array[] Rows: category, feature, and a cell per tier column.
	 */
	public static function get_comparison_features() {
		_deprecated_function( __METHOD__, '3.2.0', 'PressPrimer_Quiz_Premium_Catalog::get_comparison_rows()' );

		return PressPrimer_Quiz_Premium_Catalog::get_comparison_rows();
	}

	/**
	 * Tier cards.
	 *
	 * @since 2.3.0
	 * @deprecated 3.2.0 Use PressPrimer_Quiz_Premium_Catalog::get_tiers().
	 *
	 * @return array Slug => tier, with `featured` on School.
	 */
	public static function get_tiers() {
		_deprecated_function( __METHOD__, '3.2.0', 'PressPrimer_Quiz_Premium_Catalog::get_tiers()' );

		return PressPrimer_Quiz_Premium_Catalog::get_tiers();
	}

	/**
	 * Premium report cards, in display order.
	 *
	 * @since 3.0.0
	 * @deprecated 3.2.0 Use PressPrimer_Quiz_Premium_Catalog::get_reports().
	 *
	 * @return array[] Reports.
	 */
	public static function get_premium_report_catalog() {
		_deprecated_function( __METHOD__, '3.2.0', 'PressPrimer_Quiz_Premium_Catalog::get_reports()' );

		return PressPrimer_Quiz_Premium_Catalog::get_reports();
	}

	/**
	 * Premium report cards for the current user, with lock state.
	 *
	 * @since 3.0.0
	 * @deprecated 3.2.0 Use PressPrimer_Quiz_Premium_Catalog::report_cards_for_user().
	 *
	 * @return array[] Cards.
	 */
	public static function get_premium_report_cards() {
		_deprecated_function( __METHOD__, '3.2.0', 'PressPrimer_Quiz_Premium_Catalog::report_cards_for_user()' );

		return PressPrimer_Quiz_Premium_Catalog::report_cards_for_user();
	}
}
