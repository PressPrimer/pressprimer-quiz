<?php
/**
 * Upgrade Page Controller
 *
 * Registers and renders the "Upgrade" submenu shown to free-only users.
 * The page surfaces a comparison table and tier cards for the three premium
 * addons (Educator, School, Enterprise). When any premium addon is active
 * the menu item, inline styles, and asset enqueue are all skipped — so the
 * page is invisible to existing premium customers.
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
	 * Register the Upgrade submenu, conditional on no premium addon being active.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( $this->any_premium_addon_active() ) {
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
	 * Check whether any premium addon is currently active.
	 *
	 * Uses the existing addon manager API rather than a direct constant
	 * check — addons register themselves on the `pressprimer_quiz_register_addons`
	 * action and are activated by the manager once compatibility checks pass.
	 *
	 * @since 2.3.0
	 *
	 * @return bool True if any of educator / school / enterprise is active.
	 */
	public function any_premium_addon_active() {
		if ( ! function_exists( 'pressprimer_quiz_addon_active' ) ) {
			// Defensive — if the helper is missing, behave as if no addons
			// are active so the Upgrade page stays visible.
			return false;
		}

		return pressprimer_quiz_addon_active( 'ppq-educator' )
			|| pressprimer_quiz_addon_active( 'ppq-school' )
			|| pressprimer_quiz_addon_active( 'ppq-enterprise' );
	}

	/**
	 * Output inline admin styles to highlight the Upgrade menu item.
	 *
	 * Targets the submenu link by its slug-anchor href so we don't paint
	 * unrelated submenu items. Skips output entirely when a premium addon
	 * is active (the menu item itself isn't registered in that case).
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
	public function menu_styles() {
		if ( $this->any_premium_addon_active() ) {
			return;
		}

		?>
		<style>
			#adminmenu .wp-submenu a[href$="<?php echo esc_attr( self::MENU_SLUG ); ?>"] {
				background-color: #a5d396;
				color: #11380c;
				font-weight: 500;
			}
			#adminmenu .wp-submenu a[href$="<?php echo esc_attr( self::MENU_SLUG ); ?>"]:hover,
			#adminmenu .wp-submenu a[href$="<?php echo esc_attr( self::MENU_SLUG ); ?>"]:focus {
				background-color: #8fc77d;
				color: #11380c;
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

		if ( $this->any_premium_addon_active() ) {
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
		// Defensive — if a premium addon was activated after the menu item
		// was registered (e.g., admin loaded the page in one tab, then
		// activated an addon in another), the direct-URL access should fail
		// closed.
		if ( $this->any_premium_addon_active() ) {
			wp_die(
				esc_html__( 'This page is only available when no premium addon is active.', 'pressprimer-quiz' ),
				esc_html__( 'Not Available', 'pressprimer-quiz' ),
				array( 'response' => 403 )
			);
		}

		$features    = self::get_comparison_features();
		$tiers       = self::get_tiers();
		$pricing_url = 'https://pressprimer.com/pressprimer-quiz-pricing/';

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
	 * Curated comparison table contents.
	 *
	 * Rows are added or updated by code change as part of each release. See
	 * `docs/guides/upgrade-page-maintenance.md` for the workflow. Phase 4.3
	 * fills this out to the full ~30-row launch list; this stub keeps it to
	 * a handful so the page renders something during 4.1 / 4.2 builds.
	 *
	 * Each row's tier value is:
	 *   - true   : feature included in that tier
	 *   - false  : not included
	 *   - string : included with a caveat (e.g., "3 sites", "Limited")
	 *
	 * @since 2.3.0
	 *
	 * @return array<int, array<string, mixed>> Array of feature row arrays.
	 */
	public static function get_comparison_features() {
		return array(
			array(
				'category'   => __( 'Core', 'pressprimer-quiz' ),
				'feature'    => __( 'Multiple choice, multiple answer, true/false questions', 'pressprimer-quiz' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => __( 'Core', 'pressprimer-quiz' ),
				'feature'    => __( 'Question banks with categories and difficulty tagging', 'pressprimer-quiz' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => __( 'Core', 'pressprimer-quiz' ),
				'feature'    => __( 'Configurable scoring modes for multiple-answer questions', 'pressprimer-quiz' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => __( 'Groups & Assignments', 'pressprimer-quiz' ),
				'feature'    => __( 'Student groups and quiz assignments', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => __( 'Reporting', 'pressprimer-quiz' ),
				'feature'    => __( 'Item analysis (difficulty, discrimination)', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => false,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => __( 'Compliance & Standards', 'pressprimer-quiz' ),
				'feature'    => __( 'Audit logging of quiz and grade changes', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => false,
				'school'     => false,
				'enterprise' => true,
			),
			array(
				'category'   => __( 'Branding & Access', 'pressprimer-quiz' ),
				'feature'    => __( 'White-label branding', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => false,
				'school'     => false,
				'enterprise' => true,
			),
		);
	}

	/**
	 * Tier metadata for the cards section.
	 *
	 * @since 2.3.0
	 *
	 * @return array<string, array<string, string>> Map of slug → tier data.
	 */
	public static function get_tiers() {
		return array(
			'educator'   => array(
				'name'        => __( 'Educator', 'pressprimer-quiz' ),
				'tagline'     => __( 'For individual instructors and small teams', 'pressprimer-quiz' ),
				'description' => __( 'Add groups, assignments, import/export, AI distractor generation, and quality reports.', 'pressprimer-quiz' ),
				'url'         => 'https://pressprimer.com/pressprimer-quiz-educator/',
			),
			'school'     => array(
				'name'        => __( 'School', 'pressprimer-quiz' ),
				'tagline'     => __( 'For multi-instructor programs and institutions', 'pressprimer-quiz' ),
				'description' => __( 'Everything in Educator plus item analysis, xAPI/LRS integration, availability windows, and shared question banks.', 'pressprimer-quiz' ),
				'url'         => 'https://pressprimer.com/pressprimer-quiz-school/',
			),
			'enterprise' => array(
				'name'        => __( 'Enterprise', 'pressprimer-quiz' ),
				'tagline'     => __( 'For organizations with compliance requirements', 'pressprimer-quiz' ),
				'description' => __( 'Everything in School plus audit logging, white-label branding, branching logic, and basic proctoring.', 'pressprimer-quiz' ),
				'url'         => 'https://pressprimer.com/pressprimer-quiz-enterprise/',
			),
		);
	}
}
