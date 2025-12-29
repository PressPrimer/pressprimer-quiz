# Feature: Addon Compatibility Hooks

**Version:** 2.0  
**Plugin:** Free  
**Priority:** High  
**Status:** Planning

---

## Overview

Premium addons need clean extension points to add functionality without modifying core plugin files. This feature establishes the addon registration system and provides hooks throughout the admin interface for premium features to integrate.

## User Stories

1. As a premium addon developer, I need to register my addon with the core plugin so it knows what features are available.
2. As the core plugin, I need to check if specific addons/features are enabled before showing upgrade prompts.
3. As a premium addon, I need hooks in the admin UI to inject my settings and controls.

## Technical Specification

### Addon Manager Class

**File:** `includes/class-ppq-addon-manager.php`

```php
<?php
/**
 * Addon Manager
 *
 * Handles registration and status checking for premium addons.
 *
 * @package PressPrimer_Quiz
 * @since 2.0.0
 */

class PressPrimer_Quiz_Addon_Manager {

    /**
     * Registered addons
     *
     * @var array
     */
    private static $addons = array();

    /**
     * Initialize the addon manager
     */
    public static function init() {
        // Fire addon registration hook after core plugin loads
        add_action( 'plugins_loaded', array( __CLASS__, 'register_addons' ), 15 );
    }

    /**
     * Fire the addon registration action
     */
    public static function register_addons() {
        /**
         * Fires when addons should register themselves.
         *
         * @since 2.0.0
         */
        do_action( 'pressprimer_quiz_register_addons' );

        /**
         * Fires after all addons have registered.
         *
         * @since 2.0.0
         */
        do_action( 'pressprimer_quiz_addons_loaded' );
    }

    /**
     * Register an addon
     *
     * @param string $slug    Addon identifier (educator, school, enterprise).
     * @param string $version Addon version.
     * @param array  $features Features provided by this addon.
     */
    public static function register( $slug, $version, $features = array() ) {
        self::$addons[ $slug ] = array(
            'version'  => $version,
            'features' => $features,
            'active'   => true,
        );

        /**
         * Fires when a specific addon is registered.
         *
         * @since 2.0.0
         *
         * @param string $version  Addon version.
         * @param array  $features Features provided.
         */
        do_action( "pressprimer_quiz_addon_{$slug}_registered", $version, $features );
    }

    /**
     * Check if an addon is active
     *
     * @param string $slug Addon identifier.
     * @return bool
     */
    public static function has_addon( $slug ) {
        return isset( self::$addons[ $slug ] ) && self::$addons[ $slug ]['active'];
    }

    /**
     * Check if a specific feature is enabled
     *
     * @param string $feature Feature identifier.
     * @return bool
     */
    public static function feature_enabled( $feature ) {
        foreach ( self::$addons as $addon ) {
            if ( in_array( $feature, $addon['features'], true ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get addon version
     *
     * @param string $slug Addon identifier.
     * @return string|null
     */
    public static function get_addon_version( $slug ) {
        return isset( self::$addons[ $slug ] ) ? self::$addons[ $slug ]['version'] : null;
    }

    /**
     * Get all registered addons
     *
     * @return array
     */
    public static function get_addons() {
        return self::$addons;
    }

    /**
     * Get required addon for a feature
     *
     * @param string $feature Feature identifier.
     * @return string|null Addon slug or null.
     */
    public static function get_feature_addon( $feature ) {
        $feature_map = array(
            // Educator features
            'groups'           => 'educator',
            'assignments'      => 'educator',
            'import_export'    => 'educator',
            'ai_distractors'   => 'educator',
            'quality_reports'  => 'educator',
            
            // School features
            'availability'     => 'school',
            'shared_banks'     => 'school',
            'xapi'             => 'school',
            'group_reports'    => 'school',
            
            // Enterprise features
            'audit_log'        => 'enterprise',
            'white_label'      => 'enterprise',
            'branching'        => 'enterprise',
            'proctoring'       => 'enterprise',
        );

        return isset( $feature_map[ $feature ] ) ? $feature_map[ $feature ] : null;
    }
}
```

### Global Helper Functions

**File:** `includes/functions.php` (or add to main plugin file)

```php
/**
 * Check if an addon is active
 *
 * @since 2.0.0
 *
 * @param string $slug Addon identifier (educator, school, enterprise).
 * @return bool
 */
function pressprimer_quiz_has_addon( $slug ) {
    return PressPrimer_Quiz_Addon_Manager::has_addon( $slug );
}

/**
 * Check if a feature is enabled
 *
 * @since 2.0.0
 *
 * @param string $feature Feature identifier.
 * @return bool
 */
function pressprimer_quiz_feature_enabled( $feature ) {
    return PressPrimer_Quiz_Addon_Manager::feature_enabled( $feature );
}

/**
 * Get the addon required for a feature
 *
 * @since 2.0.0
 *
 * @param string $feature Feature identifier.
 * @return string|null Addon slug or null.
 */
function pressprimer_quiz_get_feature_addon( $feature ) {
    return PressPrimer_Quiz_Addon_Manager::get_feature_addon( $feature );
}
```

### Extension Points (Hooks)

Add these hooks throughout the admin interface:

#### Quiz Builder Hooks

```php
// In class-ppq-admin-quiz-builder.php

// After settings panel
do_action( 'pressprimer_quiz_builder_settings_after', $quiz );

// In question tools area
do_action( 'pressprimer_quiz_builder_question_tools', $quiz, $question );

// After quiz actions (publish, save, etc.)
do_action( 'pressprimer_quiz_builder_actions_after', $quiz );
```

#### Question Editor Hooks

```php
// In question editor template

// After answer options
do_action( 'pressprimer_quiz_question_editor_after_answers', $question );

// After feedback fields
do_action( 'pressprimer_quiz_question_editor_after_feedback', $question );

// In question settings panel
do_action( 'pressprimer_quiz_question_editor_settings', $question );
```

#### Results/Reports Hooks

```php
// In results renderer

// After score display
do_action( 'pressprimer_quiz_results_after_score', $attempt );

// After question review
do_action( 'pressprimer_quiz_results_after_review', $attempt );

// In admin reports page
do_action( 'pressprimer_quiz_admin_reports_tools' );
do_action( 'pressprimer_quiz_admin_reports_after_table', $attempts );
```

#### Settings Page Hooks

```php
// In admin settings

// Add settings tabs
do_action( 'pressprimer_quiz_settings_tabs' );

// Add settings sections
do_action( 'pressprimer_quiz_settings_sections' );

// After each existing section
do_action( 'pressprimer_quiz_settings_after_general' );
do_action( 'pressprimer_quiz_settings_after_appearance' );
do_action( 'pressprimer_quiz_settings_after_email' );
```

#### Admin Menu Hook

```php
// In class-ppq-admin.php

// After registering menu items
do_action( 'pressprimer_quiz_admin_menu' );
```

#### Bank List Hooks

```php
// In banks list table

// Row actions
add_filter( 'pressprimer_quiz_bank_row_actions', $actions, $bank );

// After bank title
do_action( 'pressprimer_quiz_bank_after_title', $bank );
```

### Addon Loading Order

Addons must load in dependency order:
1. Free plugin loads and fires `pressprimer_quiz_register_addons`
2. Educator addon registers (no dependencies)
3. School addon registers (requires Educator)
4. Enterprise addon registers (requires School)

```php
// Example: In pressprimer-quiz-educator.php
add_action( 'pressprimer_quiz_register_addons', function() {
    PressPrimer_Quiz_Addon_Manager::register( 'educator', '2.0.0', array(
        'groups',
        'assignments',
        'import_export',
    ) );
}, 10 );

// Example: In pressprimer-quiz-school.php
add_action( 'pressprimer_quiz_register_addons', function() {
    // Check dependency
    if ( ! pressprimer_quiz_has_addon( 'educator' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'PressPrimer Quiz School requires the Educator addon.', 'pressprimer-quiz-school' );
            echo '</p></div>';
        } );
        return;
    }
    
    PressPrimer_Quiz_Addon_Manager::register( 'school', '2.0.0', array(
        'availability',
        'shared_banks',
    ) );
}, 20 );
```

## Testing Checklist

- [ ] Addon manager initializes on `plugins_loaded`
- [ ] `pressprimer_quiz_register_addons` action fires
- [ ] `pressprimer_quiz_addons_loaded` action fires after registration
- [ ] `pressprimer_quiz_has_addon()` returns correct values
- [ ] `pressprimer_quiz_feature_enabled()` returns correct values
- [ ] `pressprimer_quiz_get_feature_addon()` maps features correctly
- [ ] All extension point hooks fire at correct times
- [ ] Addon dependency checking works (School requires Educator)
- [ ] Admin notices show for missing dependencies

## Dependencies

- None (first feature to implement)

## Files Changed

**New:**
- `includes/class-ppq-addon-manager.php`

**Modified:**
- `pressprimer-quiz.php` — Initialize addon manager
- `includes/class-ppq-plugin.php` — Add hooks initialization
- `includes/admin/class-ppq-admin-quiz-builder.php` — Add extension hooks
- `includes/admin/class-ppq-admin-questions.php` — Add extension hooks
- `includes/admin/class-ppq-admin-settings.php` — Add extension hooks
- `includes/admin/class-ppq-admin-banks.php` — Add extension hooks
- `includes/admin/class-ppq-admin.php` — Add menu hook
- `includes/frontend/class-ppq-results-renderer.php` — Add extension hooks
