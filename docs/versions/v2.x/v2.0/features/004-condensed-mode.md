# Feature: Condensed Mode

**Version:** 2.0  
**Plugin:** Free  
**Priority:** High  
**Status:** Planning

---

## Overview

The current quiz display requires significant scrolling, especially on mobile devices and when embedded in LMS lessons. Condensed mode reduces visual spacing and element sizes while maintaining accessibility standards.

## User Stories

1. As an administrator, I want to set a site-wide display density so all quizzes have a consistent appearance.
2. As a quiz author, I want to use condensed mode for quizzes embedded in lessons where space is limited.
3. As a mobile user, I want to see more content without excessive scrolling.

## Technical Specification

### Display Density Options

| Mode | Description |
|------|-------------|
| `standard` | Current default spacing and sizing |
| `condensed` | Reduced spacing, smaller fonts, unified layout |

### Global Setting

**Location:** PPQ Settings → Appearance (new section)

```php
// In class-ppq-admin-settings.php

// Register new Appearance section
add_settings_section(
    'ppq_appearance_section',
    __( 'Appearance', 'pressprimer-quiz' ),
    array( $this, 'render_appearance_section' ),
    'ppq_settings'
);

public function render_appearance_section() {
    echo '<p>' . esc_html__( 'Control how quizzes appear on the frontend.', 'pressprimer-quiz' ) . '</p>';
}

// Display density field
add_settings_field(
    'ppq_display_density',
    __( 'Display Density', 'pressprimer-quiz' ),
    array( $this, 'render_display_density_field' ),
    'ppq_settings',
    'ppq_appearance_section'
);

public function render_display_density_field() {
    $settings = get_option( 'ppq_settings', array() );
    $value    = isset( $settings['display_density'] ) ? $settings['display_density'] : 'standard';
    
    ?>
    <fieldset>
        <label>
            <input type="radio" name="ppq_settings[display_density]" 
                   value="standard" <?php checked( $value, 'standard' ); ?> />
            <?php esc_html_e( 'Standard', 'pressprimer-quiz' ); ?>
            <span class="description"><?php esc_html_e( '— Default spacing and sizing', 'pressprimer-quiz' ); ?></span>
        </label>
        <br>
        <label>
            <input type="radio" name="ppq_settings[display_density]" 
                   value="condensed" <?php checked( $value, 'condensed' ); ?> />
            <?php esc_html_e( 'Condensed', 'pressprimer-quiz' ); ?>
            <span class="description"><?php esc_html_e( '— Reduced spacing for embedded contexts', 'pressprimer-quiz' ); ?></span>
        </label>
        <p class="description">
            <?php esc_html_e( 'Default display density for quizzes. Can be overridden per quiz.', 'pressprimer-quiz' ); ?>
        </p>
    </fieldset>
    <?php
}
```

### Per-Quiz Override

**Location:** Quiz Builder → Settings

```jsx
// In quiz-editor settings panel

const displayDensityOptions = [
    { value: 'default', label: __('Use global default', 'pressprimer-quiz') },
    { value: 'standard', label: __('Standard', 'pressprimer-quiz') },
    { value: 'condensed', label: __('Condensed', 'pressprimer-quiz') },
];

<SelectControl
    label={__('Display Density', 'pressprimer-quiz')}
    value={quiz.display_density || 'default'}
    options={displayDensityOptions}
    onChange={(value) => updateQuiz({ display_density: value })}
    help={__('Condensed mode reduces spacing for embedded contexts.', 'pressprimer-quiz')}
/>
```

### Database Changes

Add column to `wp_ppq_quizzes`:

```sql
ALTER TABLE wp_ppq_quizzes 
    ADD COLUMN display_density VARCHAR(20) DEFAULT 'default' AFTER login_message;
```

### Quiz Model Updates

```php
// In class-ppq-quiz.php

/**
 * Get effective display density for this quiz
 *
 * @return string Display density (standard, condensed)
 */
public function get_effective_display_density() {
    if ( $this->display_density && $this->display_density !== 'default' ) {
        return $this->display_density;
    }
    
    $settings = get_option( 'ppq_settings', array() );
    return isset( $settings['display_density'] ) 
           ? $settings['display_density'] 
           : 'standard';
}
```

### Frontend Rendering

```php
// In class-ppq-quiz-renderer.php

public static function render( $quiz_id, $atts = array() ) {
    $quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
    
    // ... existing checks ...
    
    $density = $quiz->get_effective_display_density();
    $classes = array( 'ppq-quiz' );
    
    if ( $density === 'condensed' ) {
        $classes[] = 'ppq-quiz--condensed';
    }
    
    // Add theme class
    $classes[] = 'ppq-theme-' . sanitize_html_class( $quiz->theme );
    
    ob_start();
    ?>
    <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" 
         data-quiz-id="<?php echo esc_attr( $quiz->id ); ?>"
         data-density="<?php echo esc_attr( $density ); ?>">
        <!-- Quiz content -->
    </div>
    <?php
    return ob_get_clean();
}
```

### CSS Implementation

**File:** `assets/css/condensed.css`

```css
/**
 * Condensed Mode Styles
 *
 * Reduces spacing and sizing while maintaining accessibility.
 * Minimum touch target: 44x44px
 *
 * @package PressPrimer_Quiz
 * @since 2.0.0
 */

/* ==========================================================================
   Base Container
   ========================================================================== */

.ppq-quiz--condensed {
    --ppq-spacing-xs: 4px;
    --ppq-spacing-sm: 8px;
    --ppq-spacing-md: 12px;
    --ppq-spacing-lg: 16px;
    --ppq-spacing-xl: 20px;
    
    --ppq-font-size-sm: 0.875rem;
    --ppq-font-size-base: 0.9375rem;
    --ppq-font-size-lg: 1rem;
    --ppq-font-size-xl: 1.125rem;
    --ppq-font-size-2xl: 1.25rem;
    
    --ppq-line-height-tight: 1.4;
    --ppq-line-height-normal: 1.5;
}

/* ==========================================================================
   Quiz Header
   ========================================================================== */

.ppq-quiz--condensed .ppq-quiz-header {
    padding: var(--ppq-spacing-md);
    margin-bottom: var(--ppq-spacing-md);
}

.ppq-quiz--condensed .ppq-quiz-title {
    font-size: var(--ppq-font-size-2xl);
    margin-bottom: var(--ppq-spacing-sm);
}

.ppq-quiz--condensed .ppq-quiz-description {
    font-size: var(--ppq-font-size-sm);
    line-height: var(--ppq-line-height-normal);
}

/* ==========================================================================
   Featured Image
   ========================================================================== */

.ppq-quiz--condensed .ppq-quiz-featured-image {
    margin-bottom: var(--ppq-spacing-md);
}

.ppq-quiz--condensed .ppq-quiz-featured-image img {
    max-height: 150px;
    width: auto;
    object-fit: cover;
}

/* ==========================================================================
   Progress Bar
   ========================================================================== */

.ppq-quiz--condensed .ppq-progress-bar {
    height: 4px;
    margin-bottom: var(--ppq-spacing-md);
}

.ppq-quiz--condensed .ppq-progress-text {
    font-size: var(--ppq-font-size-sm);
    margin-bottom: var(--ppq-spacing-xs);
}

/* ==========================================================================
   Question Container
   ========================================================================== */

.ppq-quiz--condensed .ppq-question {
    padding: var(--ppq-spacing-md);
    margin-bottom: var(--ppq-spacing-md);
}

.ppq-quiz--condensed .ppq-question-number {
    font-size: var(--ppq-font-size-sm);
    margin-bottom: var(--ppq-spacing-xs);
}

.ppq-quiz--condensed .ppq-question-stem {
    font-size: var(--ppq-font-size-lg);
    line-height: var(--ppq-line-height-normal);
    margin-bottom: var(--ppq-spacing-md);
}

/* ==========================================================================
   Answer Options
   ========================================================================== */

.ppq-quiz--condensed .ppq-answers {
    gap: var(--ppq-spacing-sm);
}

.ppq-quiz--condensed .ppq-answer-option {
    padding: var(--ppq-spacing-sm) var(--ppq-spacing-md);
    min-height: 44px; /* Maintain touch target */
    font-size: var(--ppq-font-size-base);
    line-height: var(--ppq-line-height-tight);
}

.ppq-quiz--condensed .ppq-answer-option label {
    padding: var(--ppq-spacing-sm) 0;
    gap: var(--ppq-spacing-sm);
}

/* Radio/Checkbox sizing - maintain touch target */
.ppq-quiz--condensed .ppq-answer-option input[type="radio"],
.ppq-quiz--condensed .ppq-answer-option input[type="checkbox"] {
    width: 18px;
    height: 18px;
    min-width: 44px; /* Touch target */
    min-height: 44px;
}

/* ==========================================================================
   Feedback Areas
   ========================================================================== */

.ppq-quiz--condensed .ppq-feedback {
    padding: var(--ppq-spacing-sm) var(--ppq-spacing-md);
    margin-top: var(--ppq-spacing-sm);
    font-size: var(--ppq-font-size-sm);
}

.ppq-quiz--condensed .ppq-explanation {
    padding: var(--ppq-spacing-sm) var(--ppq-spacing-md);
    margin-top: var(--ppq-spacing-sm);
    font-size: var(--ppq-font-size-sm);
}

/* ==========================================================================
   Navigation
   ========================================================================== */

.ppq-quiz--condensed .ppq-navigation {
    padding: var(--ppq-spacing-md);
    gap: var(--ppq-spacing-sm);
}

.ppq-quiz--condensed .ppq-btn {
    padding: var(--ppq-spacing-sm) var(--ppq-spacing-lg);
    font-size: var(--ppq-font-size-base);
    min-height: 44px; /* Touch target */
}

/* ==========================================================================
   Timer
   ========================================================================== */

.ppq-quiz--condensed .ppq-timer {
    font-size: var(--ppq-font-size-sm);
    padding: var(--ppq-spacing-xs) var(--ppq-spacing-sm);
}

/* ==========================================================================
   Results
   ========================================================================== */

.ppq-quiz--condensed .ppq-results {
    padding: var(--ppq-spacing-md);
}

.ppq-quiz--condensed .ppq-results-score {
    font-size: var(--ppq-font-size-xl);
    margin-bottom: var(--ppq-spacing-md);
}

.ppq-quiz--condensed .ppq-results-message {
    font-size: var(--ppq-font-size-base);
    margin-bottom: var(--ppq-spacing-md);
}

/* ==========================================================================
   Mobile: Sticky Navigation
   ========================================================================== */

@media (max-width: 768px) {
    .ppq-quiz--condensed .ppq-navigation {
        position: sticky;
        bottom: 0;
        left: 0;
        right: 0;
        background: var(--ppq-bg-primary, #fff);
        border-top: 1px solid var(--ppq-border-color, #e0e0e0);
        margin: 0 calc(-1 * var(--ppq-spacing-md));
        padding: var(--ppq-spacing-sm) var(--ppq-spacing-md);
        box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.1);
        z-index: 100;
    }
    
    /* Account for sticky nav height */
    .ppq-quiz--condensed .ppq-question:last-child {
        padding-bottom: calc(var(--ppq-spacing-md) + 60px);
    }
}

/* ==========================================================================
   Unified Layout (Single Box)
   ========================================================================== */

.ppq-quiz--condensed .ppq-quiz-body {
    background: var(--ppq-bg-primary, #fff);
    border-radius: var(--ppq-border-radius, 8px);
    border: 1px solid var(--ppq-border-color, #e0e0e0);
    overflow: hidden;
}

.ppq-quiz--condensed .ppq-question {
    border: none;
    border-radius: 0;
    box-shadow: none;
    border-bottom: 1px solid var(--ppq-border-color, #e0e0e0);
}

.ppq-quiz--condensed .ppq-question:last-child {
    border-bottom: none;
}

/* ==========================================================================
   Theme Compatibility
   ========================================================================== */

/* Default Theme */
.ppq-quiz--condensed.ppq-theme-default {
    /* Uses base condensed styles */
}

/* Modern Theme */
.ppq-quiz--condensed.ppq-theme-modern .ppq-answer-option {
    border-radius: 4px;
}

.ppq-quiz--condensed.ppq-theme-modern .ppq-question {
    background: transparent;
}

/* Minimal Theme */
.ppq-quiz--condensed.ppq-theme-minimal .ppq-quiz-body {
    border: none;
    box-shadow: none;
}

.ppq-quiz--condensed.ppq-theme-minimal .ppq-question {
    border-bottom: 1px dashed var(--ppq-border-color, #e0e0e0);
}
```

### Comparison Table

| Element | Standard | Condensed |
|---------|----------|-----------|
| Featured image max-height | 300px | 150px |
| Quiz title font-size | 1.5rem | 1.25rem |
| Container padding | 24px | 12px |
| Question stem font-size | 1.125rem | 1rem |
| Answer option padding | 12px 16px | 8px 12px |
| Answer option line-height | 1.6 | 1.4 |
| Gap between answers | 12px | 8px |
| Navigation padding | 16px | 12px |
| Layout | Separate cards | Single unified box |
| Mobile navigation | Below answers | Sticky footer |

### Accessibility Requirements

All condensed styles must maintain:

1. **Minimum touch targets:** 44×44px for all interactive elements
2. **Sufficient contrast:** Same ratios as standard mode
3. **Focus indicators:** Visible focus states on all controls
4. **Text sizing:** Respects user font-size preferences (rem units)
5. **Motion:** Respects `prefers-reduced-motion`

```css
/* Ensure touch targets even with smaller visual appearance */
.ppq-quiz--condensed .ppq-answer-option {
    position: relative;
}

.ppq-quiz--condensed .ppq-answer-option::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    transform: translateY(-50%);
    width: 44px;
    height: 44px;
    /* Invisible but provides touch target */
}
```

### Enqueue Logic

```php
// In class-ppq-theme-loader.php

public static function enqueue_frontend_styles() {
    // Always enqueue base styles
    wp_enqueue_style(
        'ppq-quiz',
        PPQ_PLUGIN_URL . 'assets/css/quiz.css',
        array(),
        PPQ_VERSION
    );
    
    // Enqueue condensed styles (loaded but only applied via class)
    wp_enqueue_style(
        'ppq-condensed',
        PPQ_PLUGIN_URL . 'assets/css/condensed.css',
        array( 'ppq-quiz' ),
        PPQ_VERSION
    );
    
    // Theme-specific styles
    // ...
}
```

## Testing Checklist

### Global Settings
- [ ] Appearance section appears in Settings
- [ ] Display density setting saves correctly
- [ ] Default is 'standard' for backward compatibility

### Per-Quiz Override
- [ ] Display density selector appears in Quiz Builder
- [ ] "Use global default" option works correctly
- [ ] Setting saves with quiz

### Frontend - Standard Mode
- [ ] No `.ppq-quiz--condensed` class added
- [ ] Existing styling unchanged
- [ ] All three themes work correctly

### Frontend - Condensed Mode
- [ ] `.ppq-quiz--condensed` class added to container
- [ ] Spacing reduced as specified
- [ ] Font sizes reduced as specified
- [ ] Featured image max-height reduced
- [ ] Single unified box layout applied
- [ ] Mobile sticky navigation works

### Theme Compatibility
- [ ] Default theme works with condensed
- [ ] Modern theme works with condensed
- [ ] Minimal theme works with condensed

### Accessibility
- [ ] All buttons have 44×44px touch targets
- [ ] All answer options have 44×44px touch targets
- [ ] Focus states visible on all controls
- [ ] Color contrast maintained
- [ ] Screen reader experience unchanged

### Mobile
- [ ] Sticky navigation appears on mobile
- [ ] Navigation has proper shadow/border
- [ ] Content scrollable above sticky nav
- [ ] No content hidden behind sticky nav

## Dependencies

- None (extends existing theme system)

## Files Changed

**New:**
- `assets/css/condensed.css`

**Modified:**
- `includes/admin/class-ppq-admin-settings.php` — Add Appearance section
- `includes/models/class-ppq-quiz.php` — Add display density method
- `includes/frontend/class-ppq-quiz-renderer.php` — Add density class
- `includes/frontend/class-ppq-theme-loader.php` — Enqueue condensed CSS
- `includes/database/class-ppq-schema.php` — Add column (migration)
- `assets/css/themes/default.css` — Theme compatibility
- `assets/css/themes/modern.css` — Theme compatibility
- `assets/css/themes/minimal.css` — Theme compatibility
- `src/quiz-editor/` — Add density selector (React)
