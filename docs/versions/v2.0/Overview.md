# Version 2.0 Free - Overview

## Release Goal

Establish the commercial foundation for PressPrimer Quiz by:
1. Integrating Freemius SDK for license management
2. Adding premium upsell touchpoints
3. Enabling analytics opt-in for product improvement
4. Adding LearnPress integration
5. Ensuring compatibility with premium addons

## Core Philosophy

Version 2.0 Free is a **maintenance and commercial release**. It adds no new user-facing features beyond LearnPress integration. The focus is on building infrastructure for the premium business model.

## Target Release

WordPress.org Plugin Repository update

**Timeline:** 2-3 weeks after premium addons ready

---

## What's New in v2.0

### 1. Freemius SDK Integration

**Purpose:** License validation, upsells, analytics, support

**Features:**
- SDK initialization on plugin load
- License validation for premium addons
- Automatic update delivery for premium addons
- Opt-in analytics collection
- In-dashboard support widget

**Implementation:**
```php
// Initialize Freemius
if (!function_exists('ppq_fs')) {
    function ppq_fs() {
        global $ppq_fs;
        if (!isset($ppq_fs)) {
            require_once dirname(__FILE__) . '/vendor/freemius/start.php';
            $ppq_fs = fs_dynamic_init(array(
                'id'                  => 'XXXXX',
                'slug'                => 'pressprimer-quiz',
                'type'                => 'plugin',
                'public_key'          => 'pk_XXXXX',
                'is_premium'          => false,
                'has_addons'          => true,
                'has_paid_plans'      => true,
                'menu'                => array(
                    'slug'    => 'ppq-settings',
                    'support' => false,
                ),
            ));
        }
        return $ppq_fs;
    }
    ppq_fs();
    do_action('pressprimer_quiz_fs_loaded');
}
```

### 2. Premium Upsell Touchpoints

**Locations:**
- Settings page: "Unlock more features" section
- Reports page: Locked feature indicators for advanced reports
- Quiz builder: Locked icons for premium features (availability windows, branching)
- Question editor: Locked indicators for LaTeX, survey mode
- Dashboard widget: "Upgrade" link

**Design Principles:**
- Non-intrusive (no popups or blocking modals)
- Clearly labeled as premium features
- Links to Freemius checkout
- Graceful degradation if offline

**Implementation:**
```php
function ppq_render_upsell_card($feature_name, $tier = 'educator') {
    if (ppq_has_addon($tier)) {
        return; // User has this tier, don't show upsell
    }
    ?>
    <div class="ppq-upsell-card">
        <span class="dashicons dashicons-lock"></span>
        <h4><?php echo esc_html($feature_name); ?></h4>
        <p><?php esc_html_e('Available in', 'pressprimer-quiz'); ?> 
           <?php echo esc_html(ucfirst($tier)); ?></p>
        <a href="<?php echo esc_url(ppq_fs()->get_upgrade_url()); ?>" 
           class="button">
            <?php esc_html_e('Upgrade', 'pressprimer-quiz'); ?>
        </a>
    </div>
    <?php
}
```

### 3. Analytics Opt-In

**What's Collected (with consent):**
- WordPress version
- PHP version
- Plugin version
- Active theme
- Other active plugins (names only)
- Number of quizzes/questions created
- Feature usage (which features are used, not content)

**What's NOT Collected:**
- Quiz content
- Question text
- Student data
- Personal information
- Site URLs (anonymized)

**Implementation:**
- Freemius handles opt-in prompt
- Users can change preference in Settings
- Data used for prioritizing features and compatibility

### 4. LearnPress Integration

**Features:**
- Detect LearnPress installation
- Add meta box to LearnPress lessons
- Quiz selector dropdown
- Display quiz within lesson content
- On quiz pass: mark lesson complete (optional)
- Respect LearnPress enrollment

**Implementation:**
```php
class PressPrimer_Quiz_LearnPress {
    
    public static function init() {
        if (!defined('LEARNPRESS_VERSION')) {
            return;
        }
        
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_lp_lesson', [__CLASS__, 'save_meta']);
        add_filter('learn-press/lesson/content', [__CLASS__, 'append_quiz']);
    }
    
    public static function add_meta_box() {
        add_meta_box(
            'ppq_learnpress_quiz',
            __('PressPrimer Quiz', 'pressprimer-quiz'),
            [__CLASS__, 'render_meta_box'],
            'lp_lesson',
            'side',
            'default'
        );
    }
    
    public static function append_quiz($content) {
        $quiz_id = get_post_meta(get_the_ID(), '_ppq_quiz_id', true);
        if ($quiz_id) {
            $content .= do_shortcode('[ppq_quiz id="' . intval($quiz_id) . '"]');
        }
        return $content;
    }
}
```

### 5. Premium Addon Compatibility

**Hooks for Addons:**
```php
// Allow addons to register themselves
do_action('pressprimer_quiz_register_addon', $addon_slug, $addon_data);

// Check if addon is active
function ppq_has_addon($tier) {
    return apply_filters('pressprimer_quiz_has_addon_' . $tier, false);
}

// Addon feature flags
function ppq_feature_enabled($feature) {
    return apply_filters('pressprimer_quiz_feature_enabled', false, $feature);
}
```

**Addon Loading Order:**
1. Free plugin loads first
2. Freemius SDK initializes
3. Premium addons load via `plugins_loaded` with priority 15
4. Addons register with Free plugin
5. Feature flags updated based on active addons

---

## What's NOT in v2.0 Free

These features have been moved to premium tiers:

- ~~Pre/post test linking~~ → Educator tier
- ~~CSV export of reports~~ → Educator tier
- ~~Visual charts with Chart.js~~ → Educator tier
- Groups and assignments → Educator tier
- Advanced reporting → Educator tier

---

## Migration Notes

### From v1.x to v2.0

**Database:**
- No schema changes
- No data migration required

**Settings:**
- New Freemius settings section added
- Existing settings preserved

**Compatibility:**
- All v1.x quizzes/questions work unchanged
- No breaking changes to shortcodes/blocks
- All hooks remain compatible

---

## Technical Requirements

- WordPress 6.0+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Freemius SDK 2.5+

---

## File Changes

### New Files
```
vendor/freemius/           # Freemius SDK
includes/integrations/class-ppq-learnpress.php
includes/admin/class-ppq-upsells.php
assets/css/admin-upsells.css
```

### Modified Files
```
pressprimer-quiz.php       # Freemius initialization
includes/class-ppq-plugin.php  # Addon loading hooks
includes/admin/class-ppq-admin-settings.php  # Freemius settings
includes/admin/class-ppq-admin-reports.php   # Upsell cards
```

---

## Testing Checklist

- [ ] Freemius SDK loads without errors
- [ ] Opt-in prompt appears on first activation
- [ ] Upsell cards display correctly
- [ ] Upsell links go to correct checkout
- [ ] LearnPress detected when active
- [ ] Quiz displays in LearnPress lessons
- [ ] LearnPress completion triggers work
- [ ] Premium addons load and register
- [ ] Feature flags work correctly
- [ ] No console errors
- [ ] No PHP warnings
- [ ] Performance unchanged from v1.x

---

## Success Metrics

### Launch (7 days)
- 95%+ of existing users update without issues
- <1% support tickets related to upgrade
- Freemius dashboard receiving data

### Ongoing
- 5%+ of users view upgrade page
- 2%+ conversion to paid tier
- 80%+ opt-in rate for analytics
