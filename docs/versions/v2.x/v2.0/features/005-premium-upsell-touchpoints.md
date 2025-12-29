# Feature: Premium Upsell Touchpoints

**Version:** 2.0  
**Plugin:** Free  
**Priority:** Low (implement last)  
**Status:** Planning

---

## Overview

Display disabled/locked UI elements throughout the admin that hint at premium features. These touchpoints should mirror the actual premium UI as closely as possible, discovered only after the premium addons are built.

## Dependencies

**CRITICAL:** This feature must be implemented AFTER:
- Educator 2.0 features (Groups, Assignments, Import/Export)
- School 2.0 features (Availability Windows, Shared Banks)
- Enterprise 2.0 features (Audit Log, White-Label)

The locked UI should exactly replicate the premium feature interfaces to ensure consistency and accurate representation of what users will get when upgrading.

## User Stories

1. As a free user, I want to discover premium features naturally while using the plugin without being interrupted.
2. As a free user, I want to understand what each premium tier offers before deciding to upgrade.
3. As a free user, I want a non-intrusive way to learn about upgrade options.

## Technical Specification

### Touchpoint Locations

| Location | Locked Feature | Tier | Priority |
|----------|----------------|------|----------|
| Quiz Builder → Settings | Availability Windows | School | Medium |
| Quiz Builder → Settings | Branching Logic | Enterprise | Low |
| Quiz Builder → Questions | AI Distractor Generation | Educator | Medium |
| Question Editor | Survey/Ungraded Mode | Educator | Low |
| Reports Page | CSV Export | Educator | High |
| Reports Page | Visual Charts | Educator | Medium |
| Reports Page | Group Reports | School | Medium |
| Question Bank List | Share with Others | School | High |
| Settings Page | White-Label Options | Enterprise | Low |

### Upsell Manager Class

**File:** `includes/admin/class-ppq-upsells.php`

```php
<?php
/**
 * Upsell Manager
 *
 * Handles display of locked premium features in the admin.
 *
 * @package PressPrimer_Quiz
 * @since 2.0.0
 */

class PressPrimer_Quiz_Upsells {

    /**
     * Premium features configuration
     *
     * @var array
     */
    private static $features = array();

    /**
     * Initialize upsells
     */
    public static function init() {
        // Only show upsells if premium addons not active
        if ( pressprimer_quiz_has_addon( 'educator' ) ) {
            return;
        }

        self::register_features();
        
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        
        // Hook into various admin locations
        add_action( 'pressprimer_quiz_builder_settings_after', array( __CLASS__, 'quiz_builder_upsells' ) );
        add_action( 'pressprimer_quiz_question_editor_settings', array( __CLASS__, 'question_editor_upsells' ) );
        add_action( 'pressprimer_quiz_admin_reports_tools', array( __CLASS__, 'reports_upsells' ) );
        add_action( 'pressprimer_quiz_settings_sections', array( __CLASS__, 'settings_upsells' ) );
        add_filter( 'pressprimer_quiz_bank_row_actions', array( __CLASS__, 'bank_row_upsells' ), 10, 2 );
    }

    /**
     * Register premium features
     */
    private static function register_features() {
        self::$features = array(
            'availability_windows' => array(
                'name'        => __( 'Availability Windows', 'pressprimer-quiz' ),
                'description' => __( 'Control exactly when quizzes are accessible with start and end dates.', 'pressprimer-quiz' ),
                'tier'        => 'school',
                'tier_name'   => __( 'School', 'pressprimer-quiz' ),
                'learn_more'  => 'https://pressprimer.com/features/availability-windows/',
            ),
            'branching_logic' => array(
                'name'        => __( 'Branching Logic', 'pressprimer-quiz' ),
                'description' => __( 'Create adaptive quizzes that change based on student responses.', 'pressprimer-quiz' ),
                'tier'        => 'enterprise',
                'tier_name'   => __( 'Enterprise', 'pressprimer-quiz' ),
                'learn_more'  => 'https://pressprimer.com/features/branching-logic/',
            ),
            'ai_distractors' => array(
                'name'        => __( 'AI Distractor Generation', 'pressprimer-quiz' ),
                'description' => __( 'Generate plausible wrong answers automatically using AI.', 'pressprimer-quiz' ),
                'tier'        => 'educator',
                'tier_name'   => __( 'Educator', 'pressprimer-quiz' ),
                'learn_more'  => 'https://pressprimer.com/features/ai-distractors/',
            ),
            'survey_mode' => array(
                'name'        => __( 'Survey/Ungraded Mode', 'pressprimer-quiz' ),
                'description' => __( 'Create ungraded surveys and assessments for feedback collection.', 'pressprimer-quiz' ),
                'tier'        => 'educator',
                'tier_name'   => __( 'Educator', 'pressprimer-quiz' ),
                'learn_more'  => 'https://pressprimer.com/features/survey-mode/',
            ),
            'csv_export' => array(
                'name'        => __( 'CSV Export', 'pressprimer-quiz' ),
                'description' => __( 'Export quiz results and analytics to CSV for further analysis.', 'pressprimer-quiz' ),
                'tier'        => 'educator',
                'tier_name'   => __( 'Educator', 'pressprimer-quiz' ),
                'learn_more'  => 'https://pressprimer.com/features/reporting/',
            ),
            'visual_charts' => array(
                'name'        => __( 'Visual Charts', 'pressprimer-quiz' ),
                'description' => __( 'See quiz performance with beautiful charts and graphs.', 'pressprimer-quiz' ),
                'tier'        => 'educator',
                'tier_name'   => __( 'Educator', 'pressprimer-quiz' ),
                'learn_more'  => 'https://pressprimer.com/features/reporting/',
            ),
            'group_reports' => array(
                'name'        => __( 'Group Reports', 'pressprimer-quiz' ),
                'description' => __( 'View aggregated results by class, cohort, or team.', 'pressprimer-quiz' ),
                'tier'        => 'school',
                'tier_name'   => __( 'School', 'pressprimer-quiz' ),
                'learn_more'  => 'https://pressprimer.com/features/group-reports/',
            ),
            'shared_banks' => array(
                'name'        => __( 'Share with Others', 'pressprimer-quiz' ),
                'description' => __( 'Share question banks with other teachers and collaborate.', 'pressprimer-quiz' ),
                'tier'        => 'school',
                'tier_name'   => __( 'School', 'pressprimer-quiz' ),
                'learn_more'  => 'https://pressprimer.com/features/shared-banks/',
            ),
            'white_label' => array(
                'name'        => __( 'White-Label Branding', 'pressprimer-quiz' ),
                'description' => __( 'Customize the plugin appearance with your organization\'s branding.', 'pressprimer-quiz' ),
                'tier'        => 'enterprise',
                'tier_name'   => __( 'Enterprise', 'pressprimer-quiz' ),
                'learn_more'  => 'https://pressprimer.com/features/white-label/',
            ),
        );
    }

    /**
     * Enqueue upsell assets
     *
     * @param string $hook Current admin page.
     */
    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'pressprimer-quiz' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'ppq-admin-upsells',
            PPQ_PLUGIN_URL . 'assets/css/admin-upsells.css',
            array(),
            PPQ_VERSION
        );

        wp_enqueue_script(
            'ppq-admin-upsells',
            PPQ_PLUGIN_URL . 'assets/js/admin-upsells.js',
            array( 'jquery' ),
            PPQ_VERSION,
            true
        );

        wp_localize_script( 'ppq-admin-upsells', 'ppqUpsells', array(
            'pricingUrl' => 'https://pressprimer.com/pricing/',
            'features'   => self::$features,
        ) );
    }

    /**
     * Render a locked feature indicator
     *
     * @param string $feature_key Feature identifier.
     * @param string $context     Display context (inline, block, row-action).
     * @return string HTML output.
     */
    public static function render_locked_feature( $feature_key, $context = 'inline' ) {
        if ( ! isset( self::$features[ $feature_key ] ) ) {
            return '';
        }

        // Check if this feature's addon is active
        $feature = self::$features[ $feature_key ];
        if ( pressprimer_quiz_has_addon( $feature['tier'] ) ) {
            return '';
        }

        ob_start();
        
        switch ( $context ) {
            case 'block':
                self::render_locked_block( $feature );
                break;
            
            case 'row-action':
                self::render_locked_row_action( $feature );
                break;
            
            case 'inline':
            default:
                self::render_locked_inline( $feature );
                break;
        }

        return ob_get_clean();
    }

    /**
     * Render inline locked indicator
     *
     * @param array $feature Feature configuration.
     */
    private static function render_locked_inline( $feature ) {
        ?>
        <span class="ppq-upsell-inline" 
              tabindex="0"
              role="button"
              aria-label="<?php echo esc_attr( sprintf( __( '%s - Premium feature', 'pressprimer-quiz' ), $feature['name'] ) ); ?>"
              data-feature="<?php echo esc_attr( $feature['name'] ); ?>"
              data-tier="<?php echo esc_attr( $feature['tier_name'] ); ?>">
            <span class="ppq-upsell-icon" aria-hidden="true">🔒</span>
            <span class="ppq-upsell-tooltip">
                <strong><?php echo esc_html( $feature['name'] ); ?></strong>
                <span><?php echo esc_html( $feature['description'] ); ?></span>
                <span class="ppq-upsell-tier">
                    <?php 
                    printf(
                        /* translators: %s: tier name */
                        esc_html__( 'Available in %s', 'pressprimer-quiz' ),
                        esc_html( $feature['tier_name'] )
                    );
                    ?>
                </span>
                <a href="<?php echo esc_url( $feature['learn_more'] ); ?>" target="_blank" rel="noopener">
                    <?php esc_html_e( 'Learn More →', 'pressprimer-quiz' ); ?>
                </a>
            </span>
        </span>
        <?php
    }

    /**
     * Render block locked indicator (for larger UI sections)
     *
     * @param array $feature Feature configuration.
     */
    private static function render_locked_block( $feature ) {
        ?>
        <div class="ppq-upsell-block" role="region" aria-label="<?php esc_attr_e( 'Premium feature', 'pressprimer-quiz' ); ?>">
            <div class="ppq-upsell-block-content">
                <span class="ppq-upsell-icon" aria-hidden="true">🔒</span>
                <div class="ppq-upsell-block-text">
                    <strong><?php echo esc_html( $feature['name'] ); ?></strong>
                    <p><?php echo esc_html( $feature['description'] ); ?></p>
                </div>
            </div>
            <div class="ppq-upsell-block-footer">
                <span class="ppq-upsell-tier">
                    <?php 
                    printf(
                        /* translators: %s: tier name */
                        esc_html__( 'Available in %s', 'pressprimer-quiz' ),
                        esc_html( $feature['tier_name'] )
                    );
                    ?>
                </span>
                <a href="<?php echo esc_url( $feature['learn_more'] ); ?>" 
                   class="ppq-upsell-link" 
                   target="_blank" 
                   rel="noopener">
                    <?php esc_html_e( 'Learn More', 'pressprimer-quiz' ); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render row action locked indicator
     *
     * @param array $feature Feature configuration.
     */
    private static function render_locked_row_action( $feature ) {
        ?>
        <span class="ppq-upsell-row-action">
            <span class="ppq-upsell-icon" aria-hidden="true">🔒</span>
            <?php echo esc_html( $feature['name'] ); ?>
            <span class="ppq-upsell-badge"><?php echo esc_html( $feature['tier_name'] ); ?></span>
        </span>
        <?php
    }

    /**
     * Quiz Builder upsells
     *
     * @param object $quiz Quiz object.
     */
    public static function quiz_builder_upsells( $quiz ) {
        // Availability Windows (School)
        if ( ! pressprimer_quiz_has_addon( 'school' ) ) {
            echo self::render_locked_feature( 'availability_windows', 'block' );
        }

        // Branching Logic (Enterprise)
        if ( ! pressprimer_quiz_has_addon( 'enterprise' ) ) {
            echo self::render_locked_feature( 'branching_logic', 'block' );
        }
    }

    /**
     * Question Editor upsells
     *
     * @param object $question Question object.
     */
    public static function question_editor_upsells( $question ) {
        // AI Distractors (Educator)
        if ( ! pressprimer_quiz_has_addon( 'educator' ) ) {
            echo self::render_locked_feature( 'ai_distractors', 'inline' );
        }

        // Survey Mode (Educator)
        if ( ! pressprimer_quiz_has_addon( 'educator' ) ) {
            echo self::render_locked_feature( 'survey_mode', 'inline' );
        }
    }

    /**
     * Reports page upsells
     */
    public static function reports_upsells() {
        // CSV Export (Educator)
        if ( ! pressprimer_quiz_has_addon( 'educator' ) ) {
            echo self::render_locked_feature( 'csv_export', 'inline' );
        }

        // Visual Charts (Educator)
        if ( ! pressprimer_quiz_has_addon( 'educator' ) ) {
            echo self::render_locked_feature( 'visual_charts', 'block' );
        }

        // Group Reports (School)
        if ( ! pressprimer_quiz_has_addon( 'school' ) ) {
            echo self::render_locked_feature( 'group_reports', 'block' );
        }
    }

    /**
     * Settings page upsells
     */
    public static function settings_upsells() {
        // White-Label (Enterprise)
        if ( ! pressprimer_quiz_has_addon( 'enterprise' ) ) {
            echo self::render_locked_feature( 'white_label', 'block' );
        }
    }

    /**
     * Bank row action upsells
     *
     * @param array  $actions Existing row actions.
     * @param object $bank    Bank object.
     * @return array Modified actions.
     */
    public static function bank_row_upsells( $actions, $bank ) {
        // Share (School)
        if ( ! pressprimer_quiz_has_addon( 'school' ) ) {
            $actions['share_locked'] = self::render_locked_feature( 'shared_banks', 'row-action' );
        }

        return $actions;
    }
}
```

### CSS Styles

**File:** `assets/css/admin-upsells.css`

```css
/**
 * Admin Upsell Styles
 *
 * Subtle, non-intrusive styling for premium feature indicators.
 *
 * @package PressPrimer_Quiz
 * @since 2.0.0
 */

/* ==========================================================================
   Inline Upsell (Icon + Tooltip)
   ========================================================================== */

.ppq-upsell-inline {
    position: relative;
    display: inline-flex;
    align-items: center;
    cursor: help;
    margin-left: 4px;
}

.ppq-upsell-icon {
    font-size: 14px;
    opacity: 0.6;
    transition: opacity 0.2s ease;
}

.ppq-upsell-inline:hover .ppq-upsell-icon,
.ppq-upsell-inline:focus .ppq-upsell-icon {
    opacity: 1;
}

.ppq-upsell-tooltip {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    width: 240px;
    padding: 12px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s ease, visibility 0.2s ease;
    z-index: 1000;
    text-align: left;
    font-size: 13px;
    line-height: 1.5;
}

.ppq-upsell-inline:hover .ppq-upsell-tooltip,
.ppq-upsell-inline:focus .ppq-upsell-tooltip {
    opacity: 1;
    visibility: visible;
}

.ppq-upsell-tooltip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-top-color: #fff;
}

.ppq-upsell-tooltip strong {
    display: block;
    margin-bottom: 4px;
    color: #1d2327;
}

.ppq-upsell-tooltip span {
    display: block;
    color: #50575e;
}

.ppq-upsell-tier {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #eee;
    font-size: 12px;
    color: #2271b1;
    font-weight: 500;
}

.ppq-upsell-tooltip a {
    display: inline-block;
    margin-top: 8px;
    color: #2271b1;
    text-decoration: none;
}

.ppq-upsell-tooltip a:hover {
    text-decoration: underline;
}

/* ==========================================================================
   Block Upsell (Larger Section)
   ========================================================================== */

.ppq-upsell-block {
    background: #f6f7f7;
    border: 1px dashed #c3c4c7;
    border-radius: 4px;
    padding: 16px;
    margin: 16px 0;
}

.ppq-upsell-block-content {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.ppq-upsell-block .ppq-upsell-icon {
    font-size: 24px;
    opacity: 0.5;
}

.ppq-upsell-block-text strong {
    display: block;
    margin-bottom: 4px;
    color: #1d2327;
}

.ppq-upsell-block-text p {
    margin: 0;
    color: #50575e;
    font-size: 13px;
}

.ppq-upsell-block-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #dcdcde;
}

.ppq-upsell-block .ppq-upsell-tier {
    margin: 0;
    padding: 0;
    border: none;
}

.ppq-upsell-link {
    color: #2271b1;
    text-decoration: none;
    font-size: 13px;
}

.ppq-upsell-link:hover {
    text-decoration: underline;
}

/* ==========================================================================
   Row Action Upsell
   ========================================================================== */

.ppq-upsell-row-action {
    color: #a7aaad;
    cursor: default;
}

.ppq-upsell-row-action .ppq-upsell-icon {
    margin-right: 2px;
    font-size: 12px;
}

.ppq-upsell-badge {
    display: inline-block;
    margin-left: 4px;
    padding: 1px 6px;
    background: #f0f0f1;
    border-radius: 3px;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ==========================================================================
   Focus States (Accessibility)
   ========================================================================== */

.ppq-upsell-inline:focus {
    outline: 2px solid #2271b1;
    outline-offset: 2px;
    border-radius: 2px;
}

.ppq-upsell-block:focus-within {
    border-color: #2271b1;
}

/* ==========================================================================
   Reduced Motion
   ========================================================================== */

@media (prefers-reduced-motion: reduce) {
    .ppq-upsell-icon,
    .ppq-upsell-tooltip {
        transition: none;
    }
}
```

### JavaScript

**File:** `assets/js/admin-upsells.js`

```javascript
/**
 * Admin Upsell Interactions
 *
 * @package PressPrimer_Quiz
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Initialize upsell interactions
     */
    function init() {
        // Keyboard accessibility for inline upsells
        $('.ppq-upsell-inline').on('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).find('.ppq-upsell-tooltip a').get(0)?.click();
            }
        });

        // Track upsell impressions (optional analytics)
        trackImpressions();
    }

    /**
     * Track which upsells are viewed
     */
    function trackImpressions() {
        const viewed = new Set();

        $('.ppq-upsell-inline, .ppq-upsell-block').each(function() {
            const feature = $(this).data('feature') || $(this).find('strong').first().text();
            
            if (feature && !viewed.has(feature)) {
                viewed.add(feature);
                
                // Could send to analytics here
                // console.log('Upsell impression:', feature);
            }
        });
    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);
```

## Implementation Notes

### Replicating Premium UI

When implementing each upsell touchpoint, the locked version should:

1. **Mirror the actual premium UI structure** — Use the same HTML structure, classes, and layout as the real feature
2. **Show disabled state** — Apply visual indicators that the feature is locked (opacity, pointer-events, etc.)
3. **Include the lock icon** — Subtle padlock that doesn't obstruct the preview
4. **Provide context** — Tooltip explains what the feature does and which tier unlocks it

### Example: Availability Windows

After School addon is built, the Quiz Builder will have an availability section. The free version should show:

```html
<div class="ppq-availability-section ppq-locked">
    <div class="ppq-availability-preview" aria-hidden="true">
        <!-- Exact same HTML as real feature, but disabled -->
        <label>Available From</label>
        <input type="datetime-local" disabled>
        <label>Available Until</label>
        <input type="datetime-local" disabled>
    </div>
    <div class="ppq-upsell-overlay">
        <!-- Lock icon and tooltip -->
    </div>
</div>
```

## Testing Checklist

- [ ] No upsells appear when Educator addon is active
- [ ] No Educator upsells when Educator installed (even if School upsells show)
- [ ] Upsells appear in correct locations
- [ ] Tooltips display correctly on hover
- [ ] Tooltips display correctly on keyboard focus
- [ ] "Learn More" links open in new tab
- [ ] Links go to correct feature pages
- [ ] Tier names display correctly
- [ ] Styling is subtle and non-intrusive
- [ ] No popups, modals, or blocking behavior
- [ ] Reduced motion respected

## Dependencies

- Addon Compatibility Hooks (for checking addon status)
- **Educator 2.0** — Must be complete to replicate UI
- **School 2.0** — Must be complete to replicate UI
- **Enterprise 2.0** — Must be complete to replicate UI

## Files Changed

**New:**
- `includes/admin/class-ppq-upsells.php`
- `assets/css/admin-upsells.css`
- `assets/js/admin-upsells.js`

**Modified:**
- `includes/class-ppq-plugin.php` — Initialize upsells
