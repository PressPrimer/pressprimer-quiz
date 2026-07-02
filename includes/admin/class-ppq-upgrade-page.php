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

		$features          = self::get_comparison_features();
		$tiers             = self::get_tiers();
		$pricing_url       = 'https://pressprimer.com/pressprimer-quiz-pricing/#pricing';
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
	 * Curated comparison table contents.
	 *
	 * Rows are added or updated by code change as part of each release. The
	 * canonical editorial source is `docs/guides/upgrade-page-maintenance.md`
	 * — this PHP array MUST match that guide row-for-row. When a tier
	 * boundary changes for any feature, edit both files in the same commit.
	 *
	 * Order is meaningful: it is the display order on the page. Category
	 * headers are emitted by the view whenever the `category` value changes
	 * between consecutive rows.
	 *
	 * Each row's tier value is:
	 *   - true   : feature included in that tier
	 *   - false  : not included
	 *   - string : included with a caveat (e.g., "Unlimited", "2 sites")
	 *
	 * @since 2.3.0
	 *
	 * @return array<int, array<string, mixed>> Array of feature row arrays.
	 */
	public static function get_comparison_features() {
		$core      = __( 'Core Quiz Features', 'pressprimer-quiz' );
		$ai_banks  = __( 'AI & Question Banks', 'pressprimer-quiz' );
		$lms       = __( 'LMS Integrations', 'pressprimer-quiz' );
		$reporting = __( 'Reporting & Analytics', 'pressprimer-quiz' );
		$groups    = __( 'Groups & Assignments', 'pressprimer-quiz' );
		$advanced  = __( 'Advanced & Compliance', 'pressprimer-quiz' );
		$support   = __( 'Support & Branding', 'pressprimer-quiz' );

		return array(
			// Core Quiz Features (4 rows).
			array(
				'category'   => $core,
				'feature'    => __( 'Multiple choice, multiple answer, true/false questions', 'pressprimer-quiz' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $core,
				'feature'    => __( 'Question banks with categories', 'pressprimer-quiz' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $core,
				'feature'    => __( 'Time limits, attempt limits, retakes', 'pressprimer-quiz' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $core,
				'feature'    => __( '6 built-in themes and appearance settings', 'pressprimer-quiz' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $core,
				'feature'    => __( 'Site licenses included', 'pressprimer-quiz' ),
				'free'       => __( 'Unlimited', 'pressprimer-quiz' ),
				'educator'   => __( '1 site', 'pressprimer-quiz' ),
				'school'     => __( '2 sites', 'pressprimer-quiz' ),
				'enterprise' => __( '5 sites', 'pressprimer-quiz' ),
			),

			// AI & Question Banks (4 rows).
			array(
				'category'   => $ai_banks,
				'feature'    => __( 'AI question generation from documents', 'pressprimer-quiz' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $ai_banks,
				'feature'    => __( 'AI-generated distractors for existing questions', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $ai_banks,
				'feature'    => __( 'Question bank import/export (CSV, JSON, XML)', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $ai_banks,
				'feature'    => __( 'Spaced repetition scheduling', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => false,
				'school'     => true,
				'enterprise' => true,
			),

			// LMS Integrations (3 rows).
			array(
				'category'   => $lms,
				'feature'    => __( 'LearnDash, Tutor LMS, LifterLMS, LearnPress integration', 'pressprimer-quiz' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $lms,
				'feature'    => __( 'Uncanny Automator integration', 'pressprimer-quiz' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $lms,
				'feature'    => __( 'LearnDash quiz import (full conversion)', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => false,
				'school'     => true,
				'enterprise' => true,
			),

			// Reporting & Analytics (4 rows).
			array(
				'category'   => $reporting,
				'feature'    => __( 'Basic attempt reporting', 'pressprimer-quiz' ),
				'free'       => true,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $reporting,
				'feature'    => __( 'Visual charts and dashboards', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $reporting,
				'feature'    => __( 'Question analysis (difficulty, discrimination)', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => false,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $reporting,
				'feature'    => __( 'Pre/post test comparison analysis', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => false,
				'school'     => true,
				'enterprise' => true,
			),

			// Groups & Assignments (3 rows).
			array(
				'category'   => $groups,
				'feature'    => __( 'Student groups and group reporting', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $groups,
				'feature'    => __( 'Assignment due dates and reminder emails', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $groups,
				'feature'    => __( 'Availability windows (quiz scheduled open/close)', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => false,
				'school'     => true,
				'enterprise' => true,
			),

			// Advanced & Compliance (4 rows).
			array(
				'category'   => $advanced,
				'feature'    => __( 'xAPI / LRS integration', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => false,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $advanced,
				'feature'    => __( 'Branching logic (conditional questions)', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => false,
				'school'     => false,
				'enterprise' => true,
			),
			array(
				'category'   => $advanced,
				'feature'    => __( 'Proctoring tools', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => false,
				'school'     => false,
				'enterprise' => true,
			),
			array(
				'category'   => $advanced,
				'feature'    => __( 'Audit log', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => false,
				'school'     => false,
				'enterprise' => true,
			),

			// Support & Branding (2 rows).
			array(
				'category'   => $support,
				'feature'    => __( 'Priority support', 'pressprimer-quiz' ),
				'free'       => false,
				'educator'   => true,
				'school'     => true,
				'enterprise' => true,
			),
			array(
				'category'   => $support,
				'feature'    => __( 'White-label branding (remove PressPrimer references)', 'pressprimer-quiz' ),
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
	 * Highlights array drives the bulleted "what you get" list under each
	 * tier's tagline — keep these short and benefit-focused, not a feature
	 * inventory (the comparison table is for that).
	 *
	 * @since 2.3.0
	 *
	 * @return array<string, array<string, mixed>> Map of slug → tier data.
	 */
	public static function get_tiers() {
		return array(
			'educator'   => array(
				'name'        => __( 'Educator', 'pressprimer-quiz' ),
				'tagline'     => __( 'For individual instructors and small teams', 'pressprimer-quiz' ),
				'description' => __( 'Add groups, assignments, AI distractor generation, and quality reports.', 'pressprimer-quiz' ),
				'highlights'  => array(
					__( 'Student groups & assignments', 'pressprimer-quiz' ),
					__( 'AI-generated distractors', 'pressprimer-quiz' ),
					__( 'Question bank import/export', 'pressprimer-quiz' ),
					__( 'Visual reports & charts', 'pressprimer-quiz' ),
					__( 'Priority support', 'pressprimer-quiz' ),
				),
				'url'         => 'https://pressprimer.com/pressprimer-quiz-educator/',
			),
			'school'     => array(
				'name'        => __( 'School', 'pressprimer-quiz' ),
				'tagline'     => __( 'For multi-instructor programs and institutions', 'pressprimer-quiz' ),
				'description' => __( 'Everything in Educator plus item analysis, xAPI/LRS, availability windows, and spaced repetition.', 'pressprimer-quiz' ),
				'highlights'  => array(
					__( 'Everything in Educator', 'pressprimer-quiz' ),
					__( 'Question analysis & pre/post comparison', 'pressprimer-quiz' ),
					__( 'xAPI / LRS integration', 'pressprimer-quiz' ),
					__( 'Spaced repetition scheduling', 'pressprimer-quiz' ),
					__( 'LearnDash quiz import', 'pressprimer-quiz' ),
				),
				'url'         => 'https://pressprimer.com/pressprimer-quiz-school/',
				'featured'    => true,
			),
			'enterprise' => array(
				'name'        => __( 'Enterprise', 'pressprimer-quiz' ),
				'tagline'     => __( 'For organizations with compliance requirements', 'pressprimer-quiz' ),
				'description' => __( 'Everything in School plus audit logging, white-label branding, branching logic, and proctoring.', 'pressprimer-quiz' ),
				'highlights'  => array(
					__( 'Everything in School', 'pressprimer-quiz' ),
					__( 'Proctoring tools', 'pressprimer-quiz' ),
					__( 'Branching logic', 'pressprimer-quiz' ),
					__( 'White-label branding', 'pressprimer-quiz' ),
					__( 'Audit log', 'pressprimer-quiz' ),
				),
				'url'         => 'https://pressprimer.com/pressprimer-quiz-enterprise/',
			),
		);
	}

	/**
	 * Ordered catalog of premium report cards shown on the Reports page.
	 *
	 * The free plugin's own knowledge of the reports each premium tier adds, so
	 * the Reports page can advertise them (locked) even when the providing addon
	 * is not installed. Order is meaningful — it is the display order, grouped by
	 * tier (Educator, then School, then Enterprise) — and MUST stay stable whether
	 * a report is locked or available, so the grid does not reflow when an addon
	 * is activated. Keys, colors, icon types, and the within-tier order mirror the
	 * cards each addon registers via `pressprimer_quiz_reports_addon_reports` (in
	 * that addon's own registration order), so a locked card sits exactly where
	 * its real card appears once the add-on is active.
	 *
	 * @since 3.0.0
	 *
	 * @return array<int, array<string, string>> Ordered premium report catalog.
	 */
	public static function get_premium_report_catalog() {
		return array(
			array(
				'key'         => 'quiz-detail',
				'tier'        => 'educator',
				'title'       => __( 'Quiz Detail', 'pressprimer-quiz' ),
				'description' => __( 'In-depth analysis with score distribution, category performance, and question difficulty.', 'pressprimer-quiz' ),
				'iconType'    => 'BarChartOutlined',
				'color'       => '#14b8a6',
			),
			array(
				'key'         => 'prepost-analysis',
				'tier'        => 'educator',
				'title'       => __( 'Pre/Post Analysis', 'pressprimer-quiz' ),
				'description' => __( 'Compare quiz scores across linked pairs, any two quizzes, or individual attempts.', 'pressprimer-quiz' ),
				'iconType'    => 'SwapOutlined',
				'color'       => '#8b5cf6',
			),
			array(
				'key'         => 'question-quality',
				'tier'        => 'school',
				'title'       => __( 'Question Quality', 'pressprimer-quiz' ),
				'description' => __( 'Analyze question difficulty, discrimination, and distractor efficiency with psychometric measures.', 'pressprimer-quiz' ),
				'iconType'    => 'ExperimentOutlined',
				'color'       => '#52c41a',
			),
			array(
				'key'         => 'curve-grading',
				'tier'        => 'school',
				'title'       => __( 'Curve Grading', 'pressprimer-quiz' ),
				'description' => __( 'Apply grading curves to adjust quiz scores with a before/after distribution preview.', 'pressprimer-quiz' ),
				'iconType'    => 'LineChartOutlined',
				'color'       => '#722ed1',
			),
			array(
				'key'         => 'spaced-repetition',
				'tier'        => 'school',
				'title'       => __( 'Spaced Repetition', 'pressprimer-quiz' ),
				'description' => __( 'Track mastery progress and review questions at optimal intervals using spaced repetition.', 'pressprimer-quiz' ),
				'iconType'    => 'RocketOutlined',
				'color'       => '#14b8a6',
			),
			array(
				'key'         => 'group-performance',
				'tier'        => 'school',
				'title'       => __( 'Group Performance', 'pressprimer-quiz' ),
				'description' => __( 'Compare performance across groups with member drill-down and trends.', 'pressprimer-quiz' ),
				'iconType'    => 'TeamOutlined',
				'color'       => '#f59e0b',
			),
			array(
				'key'         => 'proctoring',
				'tier'        => 'enterprise',
				'title'       => __( 'Proctoring Report', 'pressprimer-quiz' ),
				'description' => __( 'Review proctoring incidents, flagged attempts, and quiz integrity data.', 'pressprimer-quiz' ),
				'iconType'    => 'EyeOutlined',
				'color'       => '#eb2f96',
			),
			array(
				'key'         => 'audit-trail',
				'tier'        => 'enterprise',
				'title'       => __( 'Audit Trail', 'pressprimer-quiz' ),
				'description' => __( 'A complete audit log of quiz, question, and user activity for compliance and troubleshooting.', 'pressprimer-quiz' ),
				'iconType'    => 'AuditOutlined',
				'color'       => '#722ed1',
			),
			array(
				'key'         => 'deleted-questions',
				'tier'        => 'enterprise',
				'title'       => __( 'Deleted Questions', 'pressprimer-quiz' ),
				'description' => __( 'Recover or permanently delete questions that were previously removed.', 'pressprimer-quiz' ),
				'iconType'    => 'DeleteOutlined',
				'color'       => '#ff4d4f',
			),
			array(
				'key'         => 'integrity-review',
				'tier'        => 'enterprise',
				'title'       => __( 'Integrity Review', 'pressprimer-quiz' ),
				'description' => __( 'Review attempts flagged for statistically unusual patterns — timing, answer similarity, shared devices, and concurrent sessions.', 'pressprimer-quiz' ),
				'iconType'    => 'SafetyCertificateOutlined',
				'color'       => '#14b8a6',
			),
		);
	}

	/**
	 * Premium report cards prepared for the Reports page, with lock state.
	 *
	 * Walks the catalog in order and marks each report locked when the tier that
	 * provides it is not active. Locked cards gain the tier's display name and the
	 * pricing URL so the Reports page can render an upgrade prompt in place, and
	 * are shown to administrators only. The order never changes with which tiers
	 * are active — an inactive report keeps its slot as a locked card — so the
	 * grid does not reflow when an add-on is enabled or disabled.
	 *
	 * @since 3.0.0
	 *
	 * @return array<int, array<string, mixed>> Ordered premium report cards.
	 */
	public static function get_premium_report_cards() {
		$pricing_url = 'https://pressprimer.com/pressprimer-quiz-pricing/#pricing';
		$tiers       = self::get_tiers();

		// Locked (upsell) cards are for administrators only — a teacher can neither
		// buy an upgrade nor should be shown a report they cannot reach. Non-admins
		// receive only the reports whose tier is active (resolved to real,
		// capability-checked cards on the client).
		$is_admin = current_user_can( 'manage_options' );

		$cards = array();

		foreach ( self::get_premium_report_catalog() as $entry ) {
			$tier        = isset( $entry['tier'] ) ? (string) $entry['tier'] : '';
			$tier_active = function_exists( 'pressprimer_quiz_has_addon' ) && pressprimer_quiz_has_addon( $tier );
			$locked      = ! $tier_active;

			// Never advertise an unavailable report to non-admins. Skipping keeps
			// the remaining cards in the same catalog order for everyone.
			if ( $locked && ! $is_admin ) {
				continue;
			}

			$card = array(
				'key'         => $entry['key'],
				'title'       => $entry['title'],
				'description' => $entry['description'],
				'iconType'    => $entry['iconType'],
				'color'       => $entry['color'],
				'tier'        => $tier,
				'locked'      => $locked,
			);

			if ( $locked ) {
				$card['tierName']   = isset( $tiers[ $tier ]['name'] ) ? $tiers[ $tier ]['name'] : ucfirst( $tier );
				$card['upgradeUrl'] = $pricing_url;
			}

			$cards[] = $card;
		}

		return $cards;
	}
}
