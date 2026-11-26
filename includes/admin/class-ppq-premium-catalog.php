<?php
/**
 * Premium Catalog
 *
 * The one list of what each PressPrimer Quiz tier adds. The Upgrade page's
 * comparison table and tier cards, the Reports locked cards, the quiz
 * editor's Premium tab and the upsell touchpoints all read it.
 *
 * Editorial rules:
 * - Shipped features only. A feature joins when the release that ships it
 *   ships, never before.
 * - One line each, in words a site owner uses. No jargon, no internal names.
 * - A feature's tier is the lowest tier that ships it. Its cells are derived
 *   from that tier (✓ for it and every tier above), so a feature can't be
 *   shown at the wrong tier by hand.
 * - Reports, quiz options and touchpoints each name a feature and carry that
 *   feature's tier (a unit test checks it).
 * - Reports keep each addon's own registration order, Educator first, so a
 *   locked card sits where the real card appears once the addon is active.
 *   Their keys must equal the keys the addons register through
 *   pressprimer_quiz_reports_addon_reports (tests/fixtures holds them).
 * - The readme's "Premium Features" section mirrors this catalog by hand.
 *
 * Release review (each release, free or addon): when a release adds,
 * removes or moves a premium feature, update this class in the release that
 * ships the change: the feature row, and any report, quiz option or
 * touchpoint that names it. Then update the readme's Premium Features
 * section, update tests/fixtures/addon-report-registrations.json if an
 * addon's reports changed, and run the unit tests.
 *
 * The catalog is static and deliberately unfiltered (decision 005): no
 * database, no remote fetch, no filter. It loads in admin and REST contexts.
 *
 * @package PressPrimer_Quiz
 * @subpackage Admin
 * @since 3.2.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Premium catalog class.
 *
 * @since 3.2.0
 */
final class PressPrimer_Quiz_Premium_Catalog {

	/**
	 * Pricing page.
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const PRICING_URL = 'https://pressprimer.com/pressprimer-quiz-pricing/';

	/**
	 * Knowledge base (linked from owned tiers).
	 *
	 * @since 3.2.0
	 * @var string
	 */
	const KB_URL = 'https://pressprimer.com/knowledge-base/pressprimer-quiz/';

	/**
	 * Paid tiers, lowest first.
	 *
	 * @since 3.2.0
	 * @var string[]
	 */
	const TIERS = array( 'educator', 'school', 'enterprise' );

	/**
	 * Comparison table columns, lowest first.
	 *
	 * @since 3.2.0
	 * @var string[]
	 */
	const COLUMNS = array( 'free', 'educator', 'school', 'enterprise' );

	/**
	 * The paid tiers' cards.
	 *
	 * The highlights are a short, benefit-first list; the comparison table is
	 * the inventory. Each `url` is the tier's product page. School is always
	 * "Most Popular" (`featured`), whatever the site owns.
	 *
	 * @since 3.2.0
	 *
	 * @return array Slug => name, tagline, description, highlights[], url,
	 *               color, and `featured` on School.
	 */
	public static function get_tiers() {
		return array(
			'educator'   => array(
				'name'        => __( 'Educator', 'pressprimer-quiz' ),
				'tagline'     => __( 'For individual instructors and small teams', 'pressprimer-quiz' ),
				'description' => __( 'Add front-end authoring, groups, assignments, AI feedback and distractors, and pre/post test analysis.', 'pressprimer-quiz' ),
				'highlights'  => array(
					__( 'Front-end quiz & question authoring', 'pressprimer-quiz' ),
					__( 'Student groups & assignments', 'pressprimer-quiz' ),
					__( 'Shareable quiz links', 'pressprimer-quiz' ),
					__( 'AI-generated feedback & distractors', 'pressprimer-quiz' ),
					__( 'Question bank import/export', 'pressprimer-quiz' ),
					__( 'Pre/post test analysis', 'pressprimer-quiz' ),
					__( 'Visual reports & charts', 'pressprimer-quiz' ),
					__( 'Priority support', 'pressprimer-quiz' ),
				),
				'url'         => 'https://pressprimer.com/pressprimer-quiz-educator/',
				'color'       => 'blue',
			),
			'school'     => array(
				'name'        => __( 'School', 'pressprimer-quiz' ),
				'tagline'     => __( 'For multi-instructor programs and institutions', 'pressprimer-quiz' ),
				'description' => __( 'Everything in Educator plus score reports, item analysis, xAPI/LRS, and spaced repetition.', 'pressprimer-quiz' ),
				'highlights'  => array(
					__( 'Everything in Educator', 'pressprimer-quiz' ),
					__( 'Scaled scores & score reports', 'pressprimer-quiz' ),
					__( 'Question analysis', 'pressprimer-quiz' ),
					__( 'xAPI / LRS integration', 'pressprimer-quiz' ),
					__( 'Spaced repetition scheduling', 'pressprimer-quiz' ),
					__( 'WP Fusion integration', 'pressprimer-quiz' ),
					__( 'LearnDash, LifterLMS & Tutor LMS quiz import', 'pressprimer-quiz' ),
				),
				'url'         => 'https://pressprimer.com/pressprimer-quiz-school/',
				'color'       => 'gold',
				'featured'    => true,
			),
			'enterprise' => array(
				'name'        => __( 'Enterprise', 'pressprimer-quiz' ),
				'tagline'     => __( 'For organizations with compliance requirements', 'pressprimer-quiz' ),
				'description' => __( 'Everything in School plus audit logging, white-label branding, branching logic, and proctoring.', 'pressprimer-quiz' ),
				'highlights'  => array(
					__( 'Everything in School', 'pressprimer-quiz' ),
					__( 'Proctoring tools', 'pressprimer-quiz' ),
					__( 'Integrity analytics', 'pressprimer-quiz' ),
					__( 'Branching logic', 'pressprimer-quiz' ),
					__( 'White-label branding', 'pressprimer-quiz' ),
					__( 'Audit log', 'pressprimer-quiz' ),
				),
				'url'         => 'https://pressprimer.com/pressprimer-quiz-enterprise/',
				'color'       => 'purple',
			),
		);
	}

	/**
	 * Comparison table categories, in display order.
	 *
	 * @since 3.2.0
	 *
	 * @return array Key => label.
	 */
	public static function get_categories() {
		return array(
			'core'         => __( 'Core Quiz Features', 'pressprimer-quiz' ),
			'questions'    => __( 'AI & Question Banks', 'pressprimer-quiz' ),
			'practice'     => __( 'Practice & Study', 'pressprimer-quiz' ),
			'integrations' => __( 'Integrations', 'pressprimer-quiz' ),
			'reporting'    => __( 'Reporting & Analytics', 'pressprimer-quiz' ),
			'groups'       => __( 'Groups & Assignments', 'pressprimer-quiz' ),
			'advanced'     => __( 'Advanced & Compliance', 'pressprimer-quiz' ),
			'privacy'      => __( 'Data & Privacy', 'pressprimer-quiz' ),
			'support'      => __( 'Support & Branding', 'pressprimer-quiz' ),
		);
	}

	/**
	 * Every feature, by category, in display order.
	 *
	 * `tier` is the lowest tier that ships the feature: free, educator,
	 * school or enterprise. Only a text row sets `cells`.
	 *
	 * @since 3.2.0
	 *
	 * @return array[] Features: id, category, label, tier, and optional cells.
	 */
	public static function get_features() {
		$features = array(
			// Core Quiz Features.
			array( 'core', 'question-types', 'free', __( 'Multiple choice, multiple answer, true/false questions', 'pressprimer-quiz' ) ),
			array( 'core', 'question-banks', 'free', __( 'Question banks with categories', 'pressprimer-quiz' ) ),
			array( 'core', 'quiz-limits', 'free', __( 'Time limits, attempt limits, retakes', 'pressprimer-quiz' ) ),
			array( 'core', 'dynamic-quizzes', 'free', __( 'Dynamic quizzes and question pools', 'pressprimer-quiz' ) ),
			array( 'core', 'ma-scoring', 'free', __( 'Four multiple-answer scoring modes', 'pressprimer-quiz' ) ),
			array( 'core', 'math', 'free', __( 'Math & equation rendering (LaTeX)', 'pressprimer-quiz' ) ),
			array( 'core', 'quiz-templates', 'free', __( 'Quiz templates', 'pressprimer-quiz' ) ),
			array( 'core', 'themes', 'free', __( '3 built-in themes (Default, Modern, Minimal) and appearance settings', 'pressprimer-quiz' ) ),
			array( 'core', 'student-dashboard', 'free', __( 'Front-end student dashboard (My Results)', 'pressprimer-quiz' ) ),
			array( 'core', 'frontend-authoring', 'educator', __( 'Build quizzes, questions, and banks on the front-end dashboard', 'pressprimer-quiz' ) ),
			array( 'core', 'hide-wp-admin', 'educator', __( 'Hide /wp-admin/ from students and teachers', 'pressprimer-quiz' ) ),
			array(
				'core',
				'site-licenses',
				'free',
				__( 'Site licenses included', 'pressprimer-quiz' ),
				array(
					'free'       => __( 'Unlimited', 'pressprimer-quiz' ),
					'educator'   => __( '1 site', 'pressprimer-quiz' ),
					'school'     => __( '2 sites', 'pressprimer-quiz' ),
					'enterprise' => __( '5 sites', 'pressprimer-quiz' ),
				),
			),

			// AI & Question Banks.
			array( 'questions', 'ai-generation', 'free', __( 'AI question generation from documents (OpenAI or Anthropic)', 'pressprimer-quiz' ) ),
			array( 'questions', 'ai-distractors', 'educator', __( 'AI-generated distractors for existing questions', 'pressprimer-quiz' ) ),
			array( 'questions', 'ai-feedback', 'educator', __( 'AI-generated answer feedback', 'pressprimer-quiz' ) ),
			array( 'questions', 'import-export', 'educator', __( 'Question bank import/export (CSV, JSON, XML)', 'pressprimer-quiz' ) ),
			array( 'questions', 'shared-banks', 'educator', __( 'Shared question banks', 'pressprimer-quiz' ) ),
			array( 'questions', 'measured-difficulty', 'school', __( 'Measured question difficulty', 'pressprimer-quiz' ) ),
			array( 'questions', 'version-history', 'enterprise', __( 'Question version history', 'pressprimer-quiz' ) ),

			// Practice & Study.
			array( 'practice', 'practice-mode', 'free', __( 'Practice Mode', 'pressprimer-quiz' ) ),
			array( 'practice', 'confidence', 'free', __( 'Confidence levels with the Confidence Check', 'pressprimer-quiz' ) ),
			array( 'practice', 'spaced-repetition', 'school', __( 'Spaced repetition scheduling (FSRS or SM-2)', 'pressprimer-quiz' ) ),
			array( 'practice', 'study-planner', 'school', __( 'Study Planner with mastery tracking', 'pressprimer-quiz' ) ),

			// Integrations.
			array( 'integrations', 'lms', 'free', __( 'LearnDash, Tutor LMS, LifterLMS, LearnPress integration', 'pressprimer-quiz' ) ),
			array( 'integrations', 'automator', 'free', __( 'Uncanny Automator integration', 'pressprimer-quiz' ) ),
			array( 'integrations', 'lms-quiz-import', 'school', __( 'LearnDash, LifterLMS & Tutor LMS quiz import', 'pressprimer-quiz' ) ),
			array( 'integrations', 'wp-fusion', 'school', __( 'WP Fusion integration', 'pressprimer-quiz' ) ),

			// Reporting & Analytics.
			array( 'reporting', 'attempt-reports', 'free', __( 'Basic attempt reporting with CSV export', 'pressprimer-quiz' ) ),
			array( 'reporting', 'quiz-detail', 'educator', __( 'Visual charts in reports', 'pressprimer-quiz' ) ),
			array( 'reporting', 'prepost', 'educator', __( 'Pre/post test comparison analysis', 'pressprimer-quiz' ) ),
			array( 'reporting', 'frontend-reports', 'educator', __( 'Front-end results and reports (Group Statistics, Quiz Detail, Pre/Post) with CSV export', 'pressprimer-quiz' ) ),
			array( 'reporting', 'question-quality', 'school', __( 'Question analysis (difficulty, discrimination)', 'pressprimer-quiz' ) ),
			array( 'reporting', 'score-reports', 'school', __( 'Scaled scores, percentiles, and printable score reports', 'pressprimer-quiz' ) ),
			array( 'reporting', 'group-performance', 'school', __( 'Group Performance report', 'pressprimer-quiz' ) ),
			array( 'reporting', 'curve-grading', 'school', __( 'Curve Grading', 'pressprimer-quiz' ) ),

			// Groups & Assignments.
			array( 'groups', 'groups', 'educator', __( 'Student groups and group reporting, including LearnDash Groups', 'pressprimer-quiz' ) ),
			array( 'groups', 'teacher-dashboard', 'educator', __( 'Front-end teacher dashboard', 'pressprimer-quiz' ) ),
			array( 'groups', 'assignments', 'educator', __( 'Assignment availability, due dates, and reminder emails', 'pressprimer-quiz' ) ),
			array( 'groups', 'share-links', 'educator', __( 'Shareable quiz links', 'pressprimer-quiz' ) ),

			// Advanced & Compliance.
			array( 'advanced', 'xapi', 'school', __( 'xAPI / LRS integration', 'pressprimer-quiz' ) ),
			array( 'advanced', 'branching', 'enterprise', __( 'Branching logic (conditional questions)', 'pressprimer-quiz' ) ),
			array( 'advanced', 'proctoring', 'enterprise', __( 'Proctoring tools', 'pressprimer-quiz' ) ),
			array( 'advanced', 'integrity', 'enterprise', __( 'Integrity analytics', 'pressprimer-quiz' ) ),
			array( 'advanced', 'audit-log', 'enterprise', __( 'Audit log', 'pressprimer-quiz' ) ),

			// Data & Privacy.
			array( 'privacy', 'guest-eraser', 'free', __( 'Guest data eraser (GDPR)', 'pressprimer-quiz' ) ),
			array( 'privacy', 'guest-consent', 'free', __( 'Marketing-consent capture for guests', 'pressprimer-quiz' ) ),
			array( 'privacy', 'contacts-campaigns', 'educator', __( 'Contacts and Campaigns', 'pressprimer-quiz' ) ),

			// Support & Branding.
			array( 'support', 'priority-support', 'educator', __( 'Priority support', 'pressprimer-quiz' ) ),
			array( 'support', 'white-label', 'enterprise', __( 'White-label branding (remove PressPrimer references)', 'pressprimer-quiz' ) ),
		);

		return array_map(
			function ( $row ) {
				$feature = array(
					'id'       => $row[1],
					'category' => $row[0],
					'label'    => $row[3],
					'tier'     => $row[2],
				);

				if ( isset( $row[4] ) ) {
					$feature['cells'] = $row[4];
				}

				return $feature;
			},
			$features
		);
	}

	/**
	 * The Reports page's premium cards.
	 *
	 * Educator, then School, then Enterprise, each in its addon's own
	 * registration order; keys, titles, descriptions, icons and colors are
	 * the addon's, so a locked card reads like the real one.
	 *
	 * @since 3.2.0
	 *
	 * @return array[] Reports: key, feature, tier, title, description, iconType, color.
	 */
	public static function get_reports() {
		return array(
			array(
				'key'         => 'quiz-detail',
				'feature'     => 'quiz-detail',
				'tier'        => 'educator',
				'title'       => __( 'Quiz Detail', 'pressprimer-quiz' ),
				'description' => __( 'In-depth analysis with score distribution, category performance, question difficulty, and more.', 'pressprimer-quiz' ),
				'iconType'    => 'BarChartOutlined',
				'color'       => '#14b8a6',
			),
			array(
				'key'         => 'prepost-analysis',
				'feature'     => 'prepost',
				'tier'        => 'educator',
				'title'       => __( 'Pre/Post Analysis', 'pressprimer-quiz' ),
				'description' => __( 'Compare quiz scores across linked pairs, any two quizzes, or individual attempts.', 'pressprimer-quiz' ),
				'iconType'    => 'SwapOutlined',
				'color'       => '#8b5cf6',
			),
			array(
				'key'         => 'question-quality',
				'feature'     => 'question-quality',
				'tier'        => 'school',
				'title'       => __( 'Question Quality', 'pressprimer-quiz' ),
				'description' => __( 'Analyze question difficulty, discrimination, and distractor efficiency using psychometric measures.', 'pressprimer-quiz' ),
				'iconType'    => 'ExperimentOutlined',
				'color'       => '#52c41a',
			),
			array(
				'key'         => 'curve-grading',
				'feature'     => 'curve-grading',
				'tier'        => 'school',
				'title'       => __( 'Curve Grading', 'pressprimer-quiz' ),
				'description' => __( 'Apply grading curves to adjust quiz scores with before/after distribution preview.', 'pressprimer-quiz' ),
				'iconType'    => 'LineChartOutlined',
				'color'       => '#722ed1',
			),
			array(
				'key'         => 'spaced-repetition',
				'feature'     => 'spaced-repetition',
				'tier'        => 'school',
				'title'       => __( 'Spaced Repetition', 'pressprimer-quiz' ),
				'description' => __( 'Track mastery progress and review questions at optimal intervals using spaced repetition.', 'pressprimer-quiz' ),
				'iconType'    => 'RocketOutlined',
				'color'       => '#14b8a6',
			),
			array(
				'key'         => 'group-performance',
				'feature'     => 'group-performance',
				'tier'        => 'school',
				'title'       => __( 'Group Performance', 'pressprimer-quiz' ),
				'description' => __( 'Compare performance across groups with member drill-down and trends.', 'pressprimer-quiz' ),
				'iconType'    => 'TeamOutlined',
				'color'       => '#f59e0b',
			),
			array(
				'key'         => 'proctoring',
				'feature'     => 'proctoring',
				'tier'        => 'enterprise',
				'title'       => __( 'Proctoring Report', 'pressprimer-quiz' ),
				'description' => __( 'Review proctoring incidents, flagged attempts, and quiz integrity data.', 'pressprimer-quiz' ),
				'iconType'    => 'EyeOutlined',
				'color'       => '#eb2f96',
			),
			array(
				'key'         => 'audit-trail',
				'feature'     => 'audit-log',
				'tier'        => 'enterprise',
				'title'       => __( 'Audit Trail', 'pressprimer-quiz' ),
				'description' => __( 'View a complete audit log of all quiz, question, and user activity for compliance and troubleshooting.', 'pressprimer-quiz' ),
				'iconType'    => 'AuditOutlined',
				'color'       => '#722ed1',
			),
			array(
				'key'         => 'deleted-questions',
				'feature'     => 'version-history',
				'tier'        => 'enterprise',
				'title'       => __( 'Deleted Questions', 'pressprimer-quiz' ),
				'description' => __( 'Recover or permanently delete questions that were previously removed.', 'pressprimer-quiz' ),
				'iconType'    => 'DeleteOutlined',
				'color'       => '#ff4d4f',
			),
			array(
				'key'         => 'integrity-review',
				'feature'     => 'integrity',
				'tier'        => 'enterprise',
				'title'       => __( 'Integrity Review', 'pressprimer-quiz' ),
				'description' => __( 'Review attempts flagged for statistically unusual patterns — timing, answer similarity, shared devices, and concurrent sessions. Flags never change scores.', 'pressprimer-quiz' ),
				'iconType'    => 'SafetyCertificateOutlined',
				'color'       => '#14b8a6',
			),
		);
	}

	/**
	 * The quiz editor Premium tab's options, in tier order.
	 *
	 * @since 3.2.0
	 *
	 * @return array[] Options: key, feature, tier, title, description, iconType, color.
	 */
	public static function get_quiz_options() {
		return array(
			array(
				'key'         => 'prepost',
				'feature'     => 'prepost',
				'tier'        => 'educator',
				'title'       => __( 'Pre/Post Test Linking', 'pressprimer-quiz' ),
				'description' => __( 'Link a pre-test to this quiz to automatically show students their improvement on the results page.', 'pressprimer-quiz' ),
				'iconType'    => 'SwapOutlined',
				'color'       => '#8b5cf6',
			),
			array(
				'key'         => 'share-link',
				'feature'     => 'share-links',
				'tier'        => 'educator',
				'title'       => __( 'Shareable Link', 'pressprimer-quiz' ),
				'description' => __( 'Turn on a link to this quiz that you can copy and send to students.', 'pressprimer-quiz' ),
				'iconType'    => 'LinkOutlined',
				'color'       => '#2271b1',
			),
			array(
				'key'         => 'ai-feedback',
				'feature'     => 'ai-feedback',
				'tier'        => 'educator',
				'title'       => __( 'AI Answer Feedback', 'pressprimer-quiz' ),
				'description' => __( 'Have AI draft the missing answer feedback for this quiz’s questions, then review it before saving.', 'pressprimer-quiz' ),
				'iconType'    => 'RobotOutlined',
				'color'       => '#14b8a6',
			),
			array(
				'key'         => 'spaced-repetition',
				'feature'     => 'spaced-repetition',
				'tier'        => 'school',
				'title'       => __( 'Spaced Repetition', 'pressprimer-quiz' ),
				'description' => __( 'Track question mastery with the FSRS or SM-2 algorithm and generate personalized review quizzes at optimal intervals.', 'pressprimer-quiz' ),
				'iconType'    => 'RocketOutlined',
				'color'       => '#14b8a6',
			),
			array(
				'key'         => 'measured-difficulty',
				'feature'     => 'measured-difficulty',
				'tier'        => 'school',
				'title'       => __( 'Measured Difficulty', 'pressprimer-quiz' ),
				'description' => __( 'Choose questions by how hard students actually find them, on dynamic and pooled quizzes.', 'pressprimer-quiz' ),
				'iconType'    => 'DashboardOutlined',
				'color'       => '#52c41a',
			),
			array(
				'key'         => 'wp-fusion',
				'feature'     => 'wp-fusion',
				'tier'        => 'school',
				'title'       => __( 'WP Fusion Tags', 'pressprimer-quiz' ),
				'description' => __( 'Tag students in your CRM when they sign up for, complete, pass, or fail this quiz.', 'pressprimer-quiz' ),
				'iconType'    => 'TagsOutlined',
				'color'       => '#f59e0b',
			),
			array(
				'key'         => 'score-report',
				'feature'     => 'score-reports',
				'tier'        => 'school',
				'title'       => __( 'Score Report', 'pressprimer-quiz' ),
				'description' => __( 'Report scaled scores and percentiles for this quiz on a score report students can print or save.', 'pressprimer-quiz' ),
				'iconType'    => 'FileTextOutlined',
				'color'       => '#f59e0b',
			),
			array(
				'key'         => 'branching',
				'feature'     => 'branching',
				'tier'        => 'enterprise',
				'title'       => __( 'Branching Logic', 'pressprimer-quiz' ),
				'description' => __( 'Route students to different questions based on their answers to build adaptive, conditional quizzes.', 'pressprimer-quiz' ),
				'iconType'    => 'BranchesOutlined',
				'color'       => '#1890ff',
			),
			array(
				'key'         => 'proctoring',
				'feature'     => 'proctoring',
				'tier'        => 'enterprise',
				'title'       => __( 'Proctoring', 'pressprimer-quiz' ),
				'description' => __( 'Monitor tab-switching and focus loss, require full-screen mode, and restrict quizzes to desktop browsers.', 'pressprimer-quiz' ),
				'iconType'    => 'EyeOutlined',
				'color'       => '#eb2f96',
			),
			array(
				'key'         => 'integrity',
				'feature'     => 'integrity',
				'tier'        => 'enterprise',
				'title'       => __( 'Integrity Analysis', 'pressprimer-quiz' ),
				'description' => __( 'Flag completed attempts with statistically unusual patterns — timing, answer similarity, shared devices, and concurrent sessions.', 'pressprimer-quiz' ),
				'iconType'    => 'SafetyCertificateOutlined',
				'color'       => '#14b8a6',
			),
		);
	}

	/**
	 * Upsell touchpoints (feature 012): one sentence where the tier's real
	 * control appears once it's active.
	 *
	 * Placement rules: wp-admin only, admins only, never inside an addon's
	 * slot (a sibling of it), static (no dismiss, no animation).
	 *
	 * @since 3.2.0
	 *
	 * @return array Id => surface, location, tier, feature, copy.
	 */
	public static function get_touchpoints() {
		return array(
			'bank-sharing'           => array(
				'surface'  => 'bank-editor',
				'location' => 'bank-visibility',
				'tier'     => 'educator',
				'feature'  => 'shared-banks',
				'copy'     => __( 'Share this bank with the teachers in your groups so they can use its questions in their quizzes.', 'pressprimer-quiz' ),
			),
			'guest-consent-contacts' => array(
				'surface'  => 'settings',
				'location' => 'guest-consent',
				'tier'     => 'educator',
				'feature'  => 'contacts-campaigns',
				'copy'     => __( 'See, filter, export, and email the guests who gave consent with Contacts and Campaigns.', 'pressprimer-quiz' ),
			),
			'bank-practice-access'   => array(
				'surface'  => 'bank-editor',
				'location' => 'bank-practice-access',
				'tier'     => 'school',
				'feature'  => 'study-planner',
				'copy'     => __( 'Let students practice from this bank in their own study plans.', 'pressprimer-quiz' ),
			),
			'assignment-reminders'   => array(
				'surface'  => 'settings',
				'location' => 'email-assignment-reminders',
				'tier'     => 'educator',
				'feature'  => 'assignments',
				'copy'     => __( 'Remind students before an assigned quiz is due.', 'pressprimer-quiz' ),
			),
			'review-reminders'       => array(
				'surface'  => 'settings',
				'location' => 'email-review-reminders',
				'tier'     => 'school',
				'feature'  => 'spaced-repetition',
				'copy'     => __( 'Email students when questions are due for review.', 'pressprimer-quiz' ),
			),
		);
	}

	/**
	 * A feature's comparison cells, derived from its tier.
	 *
	 * ✓ (true) for the feature's tier and every tier above it, false below.
	 * A feature with `cells` (text rows such as site licences) uses those.
	 *
	 * @since 3.2.0
	 *
	 * @param array $feature A feature from get_features().
	 * @return array Column => true|false|string, in COLUMNS order.
	 */
	public static function feature_cells( array $feature ) {
		if ( isset( $feature['cells'] ) && is_array( $feature['cells'] ) ) {
			$cells = array();
			foreach ( self::COLUMNS as $column ) {
				$cells[ $column ] = isset( $feature['cells'][ $column ] ) ? $feature['cells'][ $column ] : false;
			}

			return $cells;
		}

		$tier_index = array_search( isset( $feature['tier'] ) ? $feature['tier'] : '', self::COLUMNS, true );
		$cells      = array();

		foreach ( self::COLUMNS as $index => $column ) {
			$cells[ $column ] = false !== $tier_index && $index >= $tier_index;
		}

		return $cells;
	}

	/**
	 * One feature by id.
	 *
	 * @since 3.2.0
	 *
	 * @param string $id Feature id.
	 * @return array|null The feature, or null.
	 */
	public static function get_feature( $id ) {
		foreach ( self::get_features() as $feature ) {
			if ( $feature['id'] === $id ) {
				return $feature;
			}
		}

		return null;
	}

	/**
	 * The comparison table: one row per feature, in category order.
	 *
	 * Each row: id, category (label), feature (label), and a cell per column
	 * (free, educator, school, enterprise).
	 *
	 * @since 3.2.0
	 *
	 * @return array[] Rows.
	 */
	public static function get_comparison_rows() {
		$categories  = self::get_categories();
		$by_category = array_fill_keys( array_keys( $categories ), array() );

		foreach ( self::get_features() as $feature ) {
			$by_category[ $feature['category'] ][] = $feature;
		}

		$rows = array();
		foreach ( $by_category as $category => $features ) {
			foreach ( $features as $feature ) {
				$rows[] = array_merge(
					array(
						'id'       => $feature['id'],
						'category' => $categories[ $category ],
						'feature'  => $feature['label'],
					),
					self::feature_cells( $feature )
				);
			}
		}

		return $rows;
	}

	/**
	 * Whether a tier's addon is active.
	 *
	 * Through pressprimer_quiz_has_addon(), which also reads the addon's
	 * version constant. Known limit: an addon defines that constant before
	 * its own dependency check, so an addon that refused to boot still reads
	 * as active. That addon shows its own dependency notice.
	 *
	 * @since 3.2.0
	 *
	 * @param string $tier 'educator', 'school' or 'enterprise' ('free' is always active).
	 * @return bool
	 */
	public static function tier_active( $tier ) {
		if ( 'free' === $tier ) {
			return true;
		}

		if ( ! in_array( $tier, self::TIERS, true ) || ! function_exists( 'pressprimer_quiz_has_addon' ) ) {
			return false;
		}

		return (bool) pressprimer_quiz_has_addon( $tier );
	}

	/**
	 * Every paid tier's active state.
	 *
	 * @since 3.2.0
	 *
	 * @return array Tier => bool, lowest first.
	 */
	public static function tier_states() {
		$states = array();
		foreach ( self::TIERS as $tier ) {
			$states[ $tier ] = self::tier_active( $tier );
		}

		return $states;
	}

	/**
	 * Whether every paid tier is active.
	 *
	 * @since 3.2.0
	 *
	 * @return bool
	 */
	public static function all_tiers_active() {
		return ! in_array( false, self::tier_states(), true );
	}

	/**
	 * Add the suite's UTM tags to a pressprimer.com link.
	 *
	 * The same names as Certificate and Assignment: utm_source is the plugin,
	 * utm_medium "plugin", utm_campaign the surface (e.g. 'upgrade-page'),
	 * utm_content the position on it (e.g. 'hero-cta').
	 *
	 * @since 3.2.0
	 *
	 * @param string $url      Link.
	 * @param string $campaign Surface.
	 * @param string $content  Position on the surface.
	 * @return string Tagged link.
	 */
	public static function utm_url( $url, $campaign, $content ) {
		return add_query_arg(
			array(
				'utm_source'   => 'pressprimer-quiz',
				'utm_medium'   => 'plugin',
				'utm_campaign' => rawurlencode( (string) $campaign ),
				'utm_content'  => rawurlencode( (string) $content ),
			),
			$url
		);
	}

	/**
	 * The tagged pricing link, landing on the plans (#pricing).
	 *
	 * @since 3.2.0
	 *
	 * @param string $campaign Surface.
	 * @param string $content  Position on the surface.
	 * @return string Link.
	 */
	public static function pricing_url( $campaign, $content ) {
		return self::utm_url( self::PRICING_URL, $campaign, $content ) . '#pricing';
	}

	/**
	 * Whether the current user sees locked (upsell) cards.
	 *
	 * Administrators only, and only while upsell prompts are on.
	 *
	 * @since 3.2.0
	 *
	 * @return bool
	 */
	public static function user_sees_upsells() {
		return current_user_can( 'manage_options' )
			&& function_exists( 'pressprimer_quiz_show_prompts' )
			&& pressprimer_quiz_show_prompts( 'upsell' );
	}

	/**
	 * The Reports page's premium cards for the current user.
	 *
	 * Catalog order, whatever is active, so the grid doesn't reflow when an
	 * addon is turned on. A report whose tier isn't active is a locked card
	 * with the tier name and an upgrade link, for administrators only while
	 * upsells are on; other users get only the reports of active tiers (the
	 * page swaps in the addon's real card).
	 *
	 * @since 3.2.0
	 *
	 * @return array[] Cards: key, title, description, iconType, color, tier,
	 *                 locked, and for locked cards tierName and upgradeUrl.
	 */
	public static function report_cards_for_user() {
		$tiers   = self::get_tiers();
		$upsells = self::user_sees_upsells();
		$cards   = array();

		foreach ( self::get_reports() as $report ) {
			$locked = ! self::tier_active( $report['tier'] );

			if ( $locked && ! $upsells ) {
				continue;
			}

			$card = array(
				'key'         => $report['key'],
				'title'       => $report['title'],
				'description' => $report['description'],
				'iconType'    => $report['iconType'],
				'color'       => $report['color'],
				'tier'        => $report['tier'],
				'locked'      => $locked,
			);

			if ( $locked ) {
				$card['tierName']   = $tiers[ $report['tier'] ]['name'];
				$card['upgradeUrl'] = self::pricing_url( 'reports', 'locked-card-' . $report['key'] );
			}

			$cards[] = $card;
		}

		return $cards;
	}

	/**
	 * The upsell touchpoints a wp-admin surface shows the current user.
	 *
	 * The rules (feature 012): administrators only, while upsells are on
	 * (user_sees_upsells()), and only touchpoints whose tier isn't active.
	 * Callers on the front-end dashboard send an empty map instead (the
	 * editor data does), and the page renders each one as a sibling of the
	 * addon's mount point, never inside it.
	 *
	 * @since 3.2.0
	 *
	 * @param string $surface 'bank-editor' or 'settings'.
	 * @return array Location => key, copy, linkText ("Upgrade to {tier}") and
	 *               url (the tier's page, tagged touchpoint / {id}).
	 */
	public static function touchpoints_for_surface( $surface ) {
		if ( ! self::user_sees_upsells() ) {
			return array();
		}

		$tiers       = self::get_tiers();
		$touchpoints = array();

		foreach ( self::get_touchpoints() as $id => $touchpoint ) {
			if ( $surface !== $touchpoint['surface'] || self::tier_active( $touchpoint['tier'] ) ) {
				continue;
			}

			$tier = $tiers[ $touchpoint['tier'] ];

			$touchpoints[ $touchpoint['location'] ] = array(
				'key'      => $id,
				'copy'     => $touchpoint['copy'],
				/* translators: %s: tier name (Educator, School, or Enterprise) */
				'linkText' => sprintf( __( 'Upgrade to %s', 'pressprimer-quiz' ), $tier['name'] ),
				'url'      => self::utm_url( $tier['url'], 'touchpoint', $id ),
			);
		}

		return $touchpoints;
	}

	/**
	 * The quiz editor Premium tab's locked options for the current user.
	 *
	 * The options of every tier that isn't active, in catalog order, for
	 * administrators while upsells are on; nobody else gets any. The tab shows
	 * the active tiers' real cards itself. The editor data empties this list
	 * on the front-end dashboard.
	 *
	 * @since 3.2.0
	 *
	 * @return array[] Options: key, title, description, iconType, color, tier,
	 *                 tierName, tierColor, upgradeUrl.
	 */
	public static function quiz_options_for_user() {
		if ( ! self::user_sees_upsells() ) {
			return array();
		}

		$tiers   = self::get_tiers();
		$options = array();

		foreach ( self::get_quiz_options() as $option ) {
			if ( self::tier_active( $option['tier'] ) ) {
				continue;
			}

			$options[] = array(
				'key'         => $option['key'],
				'title'       => $option['title'],
				'description' => $option['description'],
				'iconType'    => $option['iconType'],
				'color'       => $option['color'],
				'tier'        => $option['tier'],
				'tierName'    => $tiers[ $option['tier'] ]['name'],
				'tierColor'   => $tiers[ $option['tier'] ]['color'],
				'upgradeUrl'  => self::pricing_url( 'quiz-editor', 'premium-' . $option['key'] ),
			);
		}

		return $options;
	}
}
