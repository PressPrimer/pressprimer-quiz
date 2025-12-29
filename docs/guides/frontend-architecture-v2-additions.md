# Frontend Architecture - v2.0 Additions

**Purpose:** This document contains additions to the main frontend-architecture.md for v2.0 features. Merge these sections into the appropriate locations in the main document.

---

## Condensed Mode Architecture (v2.0)

### Overview

Condensed mode provides a compact display density option that reduces vertical space requirements for quiz taking. This is especially valuable when quizzes are embedded in LMS lessons where vertical real estate is limited.

**Key Goals:**
- Reduce vertical scrolling by 40-50%
- Maintain full accessibility compliance
- Support all three themes (Default, Modern, Minimal)
- Provide global and per-quiz configuration
- Work seamlessly on mobile devices

### Display Density System

**Density Levels:**
- `standard` - Full spacing, maximum readability (default)
- `condensed` - Compact layout, reduced spacing

**Setting Hierarchy:**
1. Per-quiz override (in Quiz Builder settings)
2. Global default (in Settings → Appearance)
3. Fallback to `standard` if unset

### CSS Custom Properties

Condensed mode uses CSS custom properties (CSS variables) that are overridden when the `.ppq-density-condensed` class is present.

```css
/* Base tokens (standard density) */
:root {
    /* Spacing */
    --ppq-space-xs: 0.5rem;
    --ppq-space-sm: 0.75rem;
    --ppq-space-md: 1rem;
    --ppq-space-lg: 1.5rem;
    --ppq-space-xl: 2rem;
    
    /* Component spacing */
    --ppq-question-padding: var(--ppq-space-lg);
    --ppq-answer-padding: var(--ppq-space-md);
    --ppq-answer-gap: var(--ppq-space-sm);
    --ppq-section-gap: var(--ppq-space-xl);
    --ppq-navigation-padding: var(--ppq-space-lg);
    
    /* Typography */
    --ppq-question-font-size: 1.125rem;
    --ppq-answer-font-size: 1rem;
    --ppq-line-height: 1.6;
    
    /* Card/box properties */
    --ppq-card-padding: var(--ppq-space-lg);
    --ppq-card-border-radius: 0.5rem;
    --ppq-card-margin-bottom: var(--ppq-space-lg);
}

/* Condensed density overrides */
.ppq-density-condensed {
    /* Spacing - reduce by ~40% */
    --ppq-space-xs: 0.25rem;
    --ppq-space-sm: 0.5rem;
    --ppq-space-md: 0.625rem;
    --ppq-space-lg: 1rem;
    --ppq-space-xl: 1.25rem;
    
    /* Component spacing */
    --ppq-question-padding: var(--ppq-space-md);
    --ppq-answer-padding: var(--ppq-space-sm);
    --ppq-answer-gap: var(--ppq-space-xs);
    --ppq-section-gap: var(--ppq-space-lg);
    --ppq-navigation-padding: var(--ppq-space-md);
    
    /* Typography - slightly smaller */
    --ppq-question-font-size: 1rem;
    --ppq-answer-font-size: 0.9375rem;
    --ppq-line-height: 1.5;
    
    /* Card/box properties */
    --ppq-card-padding: var(--ppq-space-md);
    --ppq-card-border-radius: 0.375rem;
    --ppq-card-margin-bottom: var(--ppq-space-md);
}
```

### Structural Changes in Condensed Mode

**Standard Mode Structure:**
```
┌──────────────────────────────────────────────┐
│ Quiz Header Box                              │
│   - Title                                    │
│   - Timer                                    │
│   - Progress                                 │
└──────────────────────────────────────────────┘
                    ↓ Gap
┌──────────────────────────────────────────────┐
│ Question Box                                 │
│   - Question text                            │
│   - Answers                                  │
└──────────────────────────────────────────────┘
                    ↓ Gap
┌──────────────────────────────────────────────┐
│ Navigation Box                               │
│   - Previous/Next                            │
│   - Submit                                   │
└──────────────────────────────────────────────┘
```

**Condensed Mode Structure:**
```
┌──────────────────────────────────────────────┐
│ Quiz Header (inline bar)                     │
│ [Progress: 3/10]  [Timer: 05:32]  [Skip ▼]  │
├──────────────────────────────────────────────┤
│ Question text                                │
│                                              │
│ ○ Answer A                                   │
│ ○ Answer B                                   │
│ ● Answer C (selected)                        │
│ ○ Answer D                                   │
├──────────────────────────────────────────────┤
│ [◀ Previous]           [Submit ▶]           │
└──────────────────────────────────────────────┘
```

### Theme-Specific Condensed Styles

Each theme requires specific condensed adjustments:

#### Default Theme Condensed

```css
.ppq-theme-default.ppq-density-condensed {
    /* Single card instead of separate boxes */
    .ppq-quiz-wrapper {
        background: var(--ppq-bg-surface);
        border-radius: var(--ppq-card-border-radius);
        padding: var(--ppq-card-padding);
    }
    
    /* Header as inline bar */
    .ppq-quiz-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: var(--ppq-space-sm) 0;
        border-bottom: 1px solid var(--ppq-border-color);
        margin-bottom: var(--ppq-space-md);
    }
    
    /* Progress inline */
    .ppq-progress {
        flex: 0 0 auto;
    }
    
    /* Timer inline */
    .ppq-timer {
        flex: 0 0 auto;
        background: none;
        padding: 0;
    }
    
    /* Navigation at bottom of same card */
    .ppq-navigation {
        border-top: 1px solid var(--ppq-border-color);
        padding-top: var(--ppq-space-md);
        margin-top: var(--ppq-space-md);
    }
}
```

#### Modern Theme Condensed

```css
.ppq-theme-modern.ppq-density-condensed {
    /* Floating header becomes sticky bar */
    .ppq-quiz-header {
        position: sticky;
        top: 0;
        z-index: 100;
        background: var(--ppq-bg-primary);
        color: white;
        padding: var(--ppq-space-sm) var(--ppq-space-md);
        margin: calc(-1 * var(--ppq-card-padding));
        margin-bottom: var(--ppq-space-md);
        display: flex;
        align-items: center;
        gap: var(--ppq-space-md);
    }
    
    /* Compact answer cards */
    .ppq-answer {
        padding: var(--ppq-space-sm);
        margin-bottom: var(--ppq-space-xs);
    }
    
    /* Sticky navigation on mobile */
    @media (max-width: 768px) {
        .ppq-navigation {
            position: sticky;
            bottom: 0;
            background: var(--ppq-bg-surface);
            box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.1);
            padding: var(--ppq-space-sm);
            margin: 0 calc(-1 * var(--ppq-card-padding));
            margin-top: var(--ppq-space-md);
        }
    }
}
```

#### Minimal Theme Condensed

```css
.ppq-theme-minimal.ppq-density-condensed {
    /* Header as single line */
    .ppq-quiz-header {
        display: flex;
        align-items: center;
        gap: var(--ppq-space-md);
        font-size: 0.875rem;
        color: var(--ppq-text-muted);
        margin-bottom: var(--ppq-space-md);
    }
    
    /* Answers with less emphasis */
    .ppq-answer {
        border: none;
        border-bottom: 1px solid var(--ppq-border-light);
        padding: var(--ppq-space-sm) 0;
        margin: 0;
    }
    
    .ppq-answer:last-child {
        border-bottom: none;
    }
    
    /* Minimal navigation */
    .ppq-navigation {
        padding-top: var(--ppq-space-md);
        border-top: 1px solid var(--ppq-border-light);
    }
}
```

### Mobile Considerations

Condensed mode on mobile requires careful attention to touch targets:

```css
.ppq-density-condensed {
    /* Ensure touch targets meet 44px minimum */
    @media (pointer: coarse) {
        .ppq-answer {
            min-height: 44px;
            display: flex;
            align-items: center;
        }
        
        .ppq-answer input[type="radio"],
        .ppq-answer input[type="checkbox"] {
            /* Increase touch target */
            min-width: 44px;
            min-height: 44px;
        }
        
        .ppq-button {
            min-height: 44px;
            padding: var(--ppq-space-sm) var(--ppq-space-lg);
        }
    }
    
    /* Sticky navigation on mobile */
    @media (max-width: 768px) {
        .ppq-navigation {
            position: sticky;
            bottom: 0;
            background: var(--ppq-bg-surface);
            padding: var(--ppq-space-sm);
            box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.1);
        }
    }
}
```

### JavaScript Considerations

The display density is set server-side and applied via a CSS class. Minimal JavaScript is needed:

```typescript
// quiz.ts - Check for density class
class QuizApp {
    private density: 'standard' | 'condensed';
    
    constructor() {
        const wrapper = document.querySelector('.ppq-quiz-wrapper');
        this.density = wrapper?.classList.contains('ppq-density-condensed') 
            ? 'condensed' 
            : 'standard';
    }
    
    // Adjust scroll behavior for condensed mode
    scrollToQuestion(questionId: string) {
        const question = document.getElementById(questionId);
        if (!question) return;
        
        // In condensed mode, less offset needed
        const offset = this.density === 'condensed' ? 60 : 100;
        
        window.scrollTo({
            top: question.offsetTop - offset,
            behavior: 'smooth'
        });
    }
}
```

### PHP Implementation

```php
/**
 * Get CSS class for display density
 *
 * @param int $quiz_id Quiz ID.
 * @return string CSS class.
 */
function pressprimer_quiz_get_density_class( int $quiz_id ): string {
    $density = pressprimer_quiz_get_display_density( $quiz_id );
    
    return 'condensed' === $density 
        ? 'ppq-density-condensed' 
        : 'ppq-density-standard';
}

/**
 * Get display density for a quiz
 *
 * @param int $quiz_id Quiz ID.
 * @return string Density (standard or condensed).
 */
function pressprimer_quiz_get_display_density( int $quiz_id ): string {
    $quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
    
    // Per-quiz override
    if ( $quiz && ! empty( $quiz->theme_settings_json ) ) {
        $settings = json_decode( $quiz->theme_settings_json, true );
        if ( isset( $settings['display_density'] ) && 'default' !== $settings['display_density'] ) {
            return apply_filters( 'pressprimer_quiz_display_density', $settings['display_density'], $quiz_id );
        }
    }
    
    // Global default
    $global_settings = get_option( 'ppq_settings', [] );
    $default = $global_settings['default_display_density'] ?? 'standard';
    
    return apply_filters( 'pressprimer_quiz_display_density', $default, $quiz_id );
}
```

### Quiz Renderer Updates

```php
// In class-ppq-quiz-renderer.php

public static function render( $quiz_id, $atts = [] ) {
    $quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
    
    // Build wrapper classes
    $classes = [
        'ppq-quiz-wrapper',
        'ppq-theme-' . esc_attr( $quiz->theme ),
        pressprimer_quiz_get_density_class( $quiz_id ),
    ];
    
    ob_start();
    ?>
    <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
        <?php self::render_quiz_content( $quiz, $atts ); ?>
    </div>
    <?php
    return ob_get_clean();
}
```

### Admin Settings

Add to Settings → Appearance:

```jsx
// In admin settings component

<SelectControl
    label={__('Default Display Density', 'pressprimer-quiz')}
    value={settings.default_display_density || 'standard'}
    options={[
        { value: 'standard', label: __('Standard (full spacing)', 'pressprimer-quiz') },
        { value: 'condensed', label: __('Condensed (compact layout)', 'pressprimer-quiz') },
    ]}
    onChange={(value) => updateSetting('default_display_density', value)}
    help={__('Condensed mode reduces vertical space by combining elements and reducing padding.', 'pressprimer-quiz')}
/>
```

Add to Quiz Builder → Settings:

```jsx
// In quiz settings panel

<SelectControl
    label={__('Display Density', 'pressprimer-quiz')}
    value={quiz.theme_settings?.display_density || 'default'}
    options={[
        { value: 'default', label: __('Use global setting', 'pressprimer-quiz') },
        { value: 'standard', label: __('Standard (full spacing)', 'pressprimer-quiz') },
        { value: 'condensed', label: __('Condensed (compact layout)', 'pressprimer-quiz') },
    ]}
    onChange={(value) => updateThemeSettings({ display_density: value })}
/>
```

### Accessibility Verification

Condensed mode must maintain:

- **Color contrast** - All text meets WCAG AA (4.5:1)
- **Touch targets** - Minimum 44×44px on touch devices
- **Focus indicators** - Visible focus rings on all interactive elements
- **Screen reader** - No content hidden that affects comprehension
- **Keyboard navigation** - Full functionality with keyboard only

```css
/* Accessibility safeguards */
.ppq-density-condensed {
    /* Never reduce focus ring visibility */
    :focus-visible {
        outline: 2px solid var(--ppq-focus-color);
        outline-offset: 2px;
    }
    
    /* Ensure sufficient text contrast */
    --ppq-text-primary: #1a1a1a; /* Never lighten */
    --ppq-text-muted: #4a4a4a;   /* Minimum 4.5:1 on white */
    
    /* Maintain answer option spacing for readability */
    .ppq-answer + .ppq-answer {
        margin-top: var(--ppq-answer-gap);
    }
}
```

---

## Access Control Integration (v2.0)

### Login Required UI States

When `access_mode` is set to `login_required`, the frontend renderer shows a login prompt instead of the Start Quiz button:

```html
<!-- Login required state -->
<div class="ppq-quiz-wrapper ppq-login-required">
    <div class="ppq-quiz-header">
        <h2 class="ppq-quiz-title">Quiz Title</h2>
        <p class="ppq-quiz-description">Quiz description...</p>
        <div class="ppq-quiz-meta">
            <span>📋 20 Questions</span>
            <span>⏱️ 30 Minutes</span>
            <span>🎯 70% to pass</span>
        </div>
    </div>
    
    <div class="ppq-login-notice">
        <div class="ppq-login-notice__icon">🔒</div>
        <p class="ppq-login-notice__message">
            Please log in to take this quiz.
        </p>
        <a href="<?php echo esc_url( $login_url ); ?>" class="ppq-button ppq-button--primary">
            Log In to Continue
        </a>
    </div>
</div>
```

```css
.ppq-login-notice {
    text-align: center;
    padding: var(--ppq-space-xl);
    background: var(--ppq-bg-muted);
    border-radius: var(--ppq-card-border-radius);
    margin-top: var(--ppq-space-lg);
}

.ppq-login-notice__icon {
    font-size: 2rem;
    margin-bottom: var(--ppq-space-sm);
}

.ppq-login-notice__message {
    margin-bottom: var(--ppq-space-md);
    color: var(--ppq-text-muted);
}

/* Condensed mode adjustment */
.ppq-density-condensed .ppq-login-notice {
    padding: var(--ppq-space-lg);
}
```

---

## v2.1/v2.2 Planned Frontend Features

### Visual Appearance Controls (v2.1)

Additional CSS custom properties for user-controlled appearance:

```css
:root {
    /* User-configurable (v2.1) */
    --ppq-custom-spacing-multiplier: 1;
    --ppq-custom-line-height: 1.6;
    --ppq-custom-font-size-base: 1rem;
}

.ppq-quiz-wrapper {
    font-size: calc(var(--ppq-custom-font-size-base) * 1);
    line-height: var(--ppq-custom-line-height);
}

.ppq-question-text {
    margin-bottom: calc(var(--ppq-space-md) * var(--ppq-custom-spacing-multiplier));
}
```

### Block/Shortcode Attributes (v2.1)

Hide/show elements via attributes:

```php
// Shortcode with display attributes
[ppq_quiz id="123" show_timer="false" show_progress="false" show_description="false"]

// Block with equivalent attributes
<!-- wp:pressprimer-quiz/quiz {"id":123,"showTimer":false,"showProgress":false} /-->
```

```css
/* Attribute-controlled visibility */
.ppq-quiz-wrapper[data-hide-timer="true"] .ppq-timer {
    display: none;
}

.ppq-quiz-wrapper[data-hide-progress="true"] .ppq-progress {
    display: none;
}

.ppq-quiz-wrapper[data-hide-description="true"] .ppq-quiz-description {
    display: none;
}
```
