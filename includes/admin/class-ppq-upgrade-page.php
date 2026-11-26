<?php
/**
 * Upgrade Page Controller
 *
 * Registers and renders the "Upgrade" page: the hero, the tier cards and the
 * comparison table for the three premium addons (Educator, School,
 * Enterprise), all read from PressPrimer_Quiz_Premium_Catalog.
 *
 * Ownership states (3.2, spec 011 FR-003): an owned tier's card shows
 * "Active on This Site" and a documentation link instead of a buy button.
 * School is always "Most Popular". The menu item hides once Enterprise is
 * active, but the page stays reachable by URL: with every tier owned it is
 * calm (no buy buttons, footer CTA or sticky bar), and under white-label it
 * is a neutral notice with no PressPrimer name, logo or link (A21).
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
	 * UTM campaign for every link on the page.
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const UTM_CAMPAIGN = 'upgrade-page';

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
		add_action( 'admin_head', array( $this, 'hide_menu_item' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_menu_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the Upgrade page.
	 *
	 * Always registered, so the page answers by URL. hide_menu_item() takes
	 * the menu item away once Enterprise is active.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function register_menu() {
		// Under white-label the browser title carries no PressPrimer name.
		$page_title = pressprimer_quiz_white_label_active()
			? __( 'Upgrade', 'pressprimer-quiz' )
			: __( 'Upgrade PressPrimer Quiz', 'pressprimer-quiz' );

		add_submenu_page(
			'pressprimer-quiz',
			esc_html( $page_title ),
			esc_html__( 'Upgrade', 'pressprimer-quiz' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Whether the Upgrade menu item shows.
	 *
	 * It hides only at the top tier: Educator and School sites still see it,
	 * because a higher tier is available to them.
	 *
	 * @since 3.2.0
	 *
	 * @return bool
	 */
	public function menu_visible() {
		return ! $this->enterprise_addon_active();
	}

	/**
	 * Take the Upgrade item out of the menu when it shouldn't show.
	 *
	 * Runs on admin_head, after WordPress has checked access to the current
	 * page, so the page itself still opens by URL (Educator's import page
	 * does the same).
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	public function hide_menu_item() {
		if ( ! $this->menu_visible() ) {
			remove_submenu_page( 'pressprimer-quiz', self::MENU_SLUG );
		}
	}

	/**
	 * Check whether the Enterprise addon is currently active.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True when the Enterprise addon is active.
	 */
	public function enterprise_addon_active() {
		return PressPrimer_Quiz_Premium_Catalog::tier_active( 'enterprise' );
	}

	/**
	 * Highlight the Upgrade menu item.
	 *
	 * The submenu is visible on every wp-admin page, so this loads everywhere
	 * the item shows. A source-less handle carries the inline CSS (no raw
	 * style tags); the CSS is static apart from the class-constant slug.
	 *
	 * @since 3.2.0
	 *
	 * @return void
	 */
	public function enqueue_menu_styles() {
		if ( ! $this->menu_visible() ) {
			return;
		}

		$css = sprintf(
			'#adminmenu .wp-submenu a[href$="%1$s"],
			#adminmenu .wp-submenu li.current a[href$="%1$s"] {
				background-color: #1f7a3a;
				color: #fff !important;
				font-weight: 700;
			}
			#adminmenu .wp-submenu a[href$="%1$s"]:hover,
			#adminmenu .wp-submenu a[href$="%1$s"]:focus {
				background-color: #186730;
				color: #fff !important;
			}',
			self::MENU_SLUG
		);

		wp_register_style( 'pressprimer-quiz-upgrade-menu', false, array(), PRESSPRIMER_QUIZ_VERSION );
		wp_enqueue_style( 'pressprimer-quiz-upgrade-menu' );
		wp_add_inline_style( 'pressprimer-quiz-upgrade-menu', $css );
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

		// The white-label notice uses core styles only.
		if ( pressprimer_quiz_white_label_active() ) {
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
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'pressprimer-quiz' ),
				esc_html__( 'Permission Denied', 'pressprimer-quiz' ),
				array( 'response' => 403 )
			);
		}

		if ( pressprimer_quiz_white_label_active() ) {
			include PRESSPRIMER_QUIZ_PLUGIN_PATH . 'includes/admin/views/upgrade-page-white-label.php';
			return;
		}

		$tier_states = PressPrimer_Quiz_Premium_Catalog::tier_states();
		$all_active  = ! in_array( false, $tier_states, true );
		$tiers       = self::prepare_tiers( $tier_states );

		$features           = PressPrimer_Quiz_Premium_Catalog::get_comparison_rows();
		$pricing_hero_url   = PressPrimer_Quiz_Premium_Catalog::pricing_url( self::UTM_CAMPAIGN, 'hero-cta' );
		$pricing_footer_url = PressPrimer_Quiz_Premium_Catalog::pricing_url( self::UTM_CAMPAIGN, 'footer-cta' );
		$pricing_sticky_url = PressPrimer_Quiz_Premium_Catalog::pricing_url( self::UTM_CAMPAIGN, 'sticky-bar' );
		$kb_url             = PressPrimer_Quiz_Premium_Catalog::utm_url( PressPrimer_Quiz_Premium_Catalog::KB_URL, self::UTM_CAMPAIGN, 'hero-cta' );
		$logo_url           = PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/images/PressPrimer-Logo-White.svg';
		$hero_mascot_url    = PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/images/mascot-waving.png';
		$footer_mascot_url  = PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/images/mascot-celebrating-confetti.png';

		// Make $this available to the view so render_cell_value() is callable.
		$upgrade_page = $this;

		include PRESSPRIMER_QUIZ_PLUGIN_PATH . 'includes/admin/views/upgrade-page.php';
	}

	/**
	 * The tier cards for a set of ownership states.
	 *
	 * Adds `owned` to each tier and tags its link: an owned tier links to the
	 * knowledge base (tier-docs-{tier}), any other to its product page
	 * (tier-card-{tier}).
	 *
	 * @since 3.2.0
	 *
	 * @param array $tier_states Tier => active (PressPrimer_Quiz_Premium_Catalog::tier_states()).
	 * @return array Tiers from the catalog, with `owned` and a tagged `url`.
	 */
	public static function prepare_tiers( array $tier_states ) {
		$tiers = PressPrimer_Quiz_Premium_Catalog::get_tiers();

		foreach ( $tiers as $tier_slug => $tier ) {
			$owned = ! empty( $tier_states[ $tier_slug ] );

			$tiers[ $tier_slug ]['owned'] = $owned;
			$tiers[ $tier_slug ]['url']   = $owned
				? PressPrimer_Quiz_Premium_Catalog::utm_url( PressPrimer_Quiz_Premium_Catalog::KB_URL, self::UTM_CAMPAIGN, 'tier-docs-' . $tier_slug )
				: PressPrimer_Quiz_Premium_Catalog::utm_url( $tier['url'], self::UTM_CAMPAIGN, 'tier-card-' . $tier_slug );
		}

		return $tiers;
	}

	/**
	 * Render a single comparison-table cell value.
	 *
	 * Converts the raw row value into display HTML:
	 *   - true   → green checkmark
	 *   - false  → em dash
	 *   - string → escaped text (allows row-specific notes like "Limited")
	 *
	 * Output contains only spans and escaped text; the view runs it through
	 * wp_kses() with a matching allowlist.
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
