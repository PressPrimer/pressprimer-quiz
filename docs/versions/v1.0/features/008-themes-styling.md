# Feature 008: Themes and Styling

## Overview

PressPrimer Quiz includes three professional visual themes and extensive customization options. Quizzes should look modern and polished—rivaling SaaS products—not like typical WordPress plugins from 2010. All themes must be fully accessible (WCAG 2.1 AA) and responsive.

## User Stories

### US-008-1: Choose a Theme
**As a** quiz creator  
**I want to** select from professional pre-built themes  
**So that** my quizzes look polished without design work

### US-008-2: Customize Colors
**As a** quiz creator  
**I want to** customize colors to match my brand  
**So that** quizzes feel consistent with my site

### US-008-3: Mobile Experience
**As a** student on mobile  
**I want to** take quizzes comfortably on my phone  
**So that** I can learn anywhere

### US-008-4: Accessible Design
**As a** student with visual impairments  
**I want to** quizzes to be fully accessible  
**So that** I can participate equally

### US-008-5: Customize via WordPress Customizer
**As a** site administrator  
**I want to** set site-wide quiz colors in the WordPress Customizer  
**So that** I can preview changes live and maintain brand consistency across all quizzes

## Acceptance Criteria

### Theme Selection

- [ ] Three themes available: Default, Modern, Minimal
- [ ] Theme selected per-quiz (not global only)
- [ ] Global default theme in settings
- [ ] Live preview when selecting theme
- [ ] Theme applies to: quiz landing, questions, results

### Default Theme
- Clean, professional appearance
- Blue primary color (#0073aa)
- White backgrounds with subtle shadows
- Rounded corners (4px)
- Clear visual hierarchy
- Comfortable spacing

### Modern Theme
- Bold, contemporary design
- Dark mode aesthetic (dark backgrounds, light text)
- Accent color highlights
- Larger typography
- Card-based question layout
- Subtle animations on interactions

### Minimal Theme
- Stripped-down, content-focused
- Maximum whitespace
- Thin borders, no shadows
- Monochromatic with single accent
- Best for embedding in content-heavy pages
- Least visual competition with host theme

### Color Customization

- [ ] Primary color (buttons, progress bar, selected states)
- [ ] Secondary color (accents, links)
- [ ] Success color (correct answers, pass indicator)
- [ ] Error color (incorrect answers, fail indicator)
- [ ] Background color (quiz container)
- [ ] Text color (primary text)
- [ ] Color picker in quiz settings
- [ ] Per-quiz overrides

**Priority Cascade (highest to lowest):**
1. Per-quiz overrides (set in quiz builder)
2. WordPress Customizer settings (site-wide defaults)
3. Theme defaults (built into each theme)

### WordPress Customizer Integration

- [ ] Panel: "PPQ Quiz Styling" in Customizer
- [ ] Section: "Default Theme" with theme selector (Default/Modern/Minimal)
- [ ] Section: "Colors" with color pickers for all customizable colors
- [ ] Live preview when changing settings
- [ ] Preview loads a sample quiz display (not full quiz)
- [ ] Settings save to options table
- [ ] Customizer settings apply to all quizzes without per-quiz overrides

**Customizer Controls:**
- [ ] Default Theme (select: Default, Modern, Minimal)
- [ ] Primary Color (color picker with default based on selected theme)
- [ ] Secondary Color (color picker)
- [ ] Success Color (color picker)
- [ ] Error Color (color picker)
- [ ] Background Color (color picker)
- [ ] Text Color (color picker)
- [ ] Reset All to Theme Defaults (button)

### Typography

- [ ] Uses system font stack (no external font loading)
- [ ] Responsive font sizes (scales with viewport)
- [ ] Sufficient line height for readability (1.5+)
- [ ] Question stems: larger, bolder
- [ ] Answer options: comfortable reading size
- [ ] Timer: monospace for stable width

### Responsive Design

- [ ] Mobile-first approach
- [ ] Breakpoints: 375px, 768px, 1024px, 1200px
- [ ] Touch-friendly targets (minimum 44x44px)
- [ ] No horizontal scrolling at any width
- [ ] Timer remains visible without scrolling
- [ ] Navigation adapts to narrow screens
- [ ] Question navigator collapsible on mobile

### Accessibility (WCAG 2.1 AA)

- [ ] Color contrast ratio 4.5:1 for normal text
- [ ] Color contrast ratio 3:1 for large text
- [ ] Focus indicators visible and clear
- [ ] No information conveyed by color alone
- [ ] All interactive elements keyboard accessible
- [ ] Skip links for navigation
- [ ] Proper heading hierarchy
- [ ] ARIA labels on interactive elements
- [ ] Live regions for dynamic updates
- [ ] Reduced motion support (prefers-reduced-motion)

### Component Styling

**Quiz Landing Page:**
- Featured image (if set) with proper aspect ratio
- Title prominent
- Meta information (time, questions, passing) in clean layout
- Previous attempts in subtle table/list
- CTA button stands out

**Question Display:**
- Clear question stem (supports HTML)
- Answer options with adequate spacing
- Selected state obvious (not just color change)
- Hover state on desktop
- Confidence checkbox styled consistently
- Question number indicator

**Progress & Navigation:**
- Progress bar shows completion percentage
- Question navigator (grid of numbers)
- Current question highlighted
- Answered questions marked
- Navigation buttons clearly labeled

**Timer:**
- Always visible (sticky positioning)
- Monospace digits
- Warning states (yellow at 5min, red at 1min)
- Accessible announcement of warnings

**Results Page:**
- Pass/fail hero section
- Score prominently displayed
- Visual breakdown (charts/bars for categories)
- Question review in expandable accordion
- Correct/incorrect clearly marked
- Feedback displayed per question

## Technical Implementation

### CSS Architecture

Use CSS custom properties (variables) for theming:

```css
/**
 * Theme: Default
 * Base variables that all components use
 */
.ppq-quiz,
.ppq-quiz-theme-default {
    /* Colors */
    --ppq-primary: #0073aa;
    --ppq-primary-hover: #005a87;
    --ppq-secondary: #50575e;
    --ppq-success: #00a32a;
    --ppq-error: #d63638;
    --ppq-warning: #dba617;
    
    /* Backgrounds */
    --ppq-bg: #ffffff;
    --ppq-bg-alt: #f6f7f7;
    --ppq-bg-hover: #f0f0f1;
    
    /* Text */
    --ppq-text: #1d2327;
    --ppq-text-light: #50575e;
    --ppq-text-inverse: #ffffff;
    
    /* Borders */
    --ppq-border: #c3c4c7;
    --ppq-border-light: #e0e0e0;
    --ppq-radius: 4px;
    
    /* Shadows */
    --ppq-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    --ppq-shadow-lg: 0 4px 12px rgba(0, 0, 0, 0.15);
    
    /* Spacing */
    --ppq-space-xs: 4px;
    --ppq-space-sm: 8px;
    --ppq-space-md: 16px;
    --ppq-space-lg: 24px;
    --ppq-space-xl: 32px;
    
    /* Typography */
    --ppq-font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    --ppq-font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    --ppq-font-size-sm: 0.875rem;
    --ppq-font-size-base: 1rem;
    --ppq-font-size-lg: 1.125rem;
    --ppq-font-size-xl: 1.5rem;
    --ppq-line-height: 1.6;
    
    /* Transitions */
    --ppq-transition: 150ms ease-in-out;
}

/**
 * Theme: Modern (Dark)
 */
.ppq-quiz-theme-modern {
    --ppq-primary: #6366f1;
    --ppq-primary-hover: #4f46e5;
    --ppq-secondary: #a5b4fc;
    --ppq-success: #22c55e;
    --ppq-error: #ef4444;
    --ppq-warning: #f59e0b;
    
    --ppq-bg: #1e1e2e;
    --ppq-bg-alt: #2a2a3e;
    --ppq-bg-hover: #3a3a4e;
    
    --ppq-text: #e4e4e7;
    --ppq-text-light: #a1a1aa;
    --ppq-text-inverse: #1e1e2e;
    
    --ppq-border: #3f3f5a;
    --ppq-border-light: #2a2a3e;
    --ppq-radius: 8px;
    
    --ppq-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
    --ppq-shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.4);
}

/**
 * Theme: Minimal
 */
.ppq-quiz-theme-minimal {
    --ppq-primary: #111827;
    --ppq-primary-hover: #374151;
    --ppq-secondary: #6b7280;
    --ppq-success: #059669;
    --ppq-error: #dc2626;
    --ppq-warning: #d97706;
    
    --ppq-bg: #ffffff;
    --ppq-bg-alt: #fafafa;
    --ppq-bg-hover: #f5f5f5;
    
    --ppq-text: #111827;
    --ppq-text-light: #6b7280;
    --ppq-text-inverse: #ffffff;
    
    --ppq-border: #e5e7eb;
    --ppq-border-light: #f3f4f6;
    --ppq-radius: 2px;
    
    --ppq-shadow: none;
    --ppq-shadow-lg: 0 1px 2px rgba(0, 0, 0, 0.05);
}
```

### Component CSS

```css
/**
 * Quiz Container
 */
.ppq-quiz {
    font-family: var(--ppq-font-family);
    font-size: var(--ppq-font-size-base);
    line-height: var(--ppq-line-height);
    color: var(--ppq-text);
    background: var(--ppq-bg);
    border-radius: var(--ppq-radius);
    box-shadow: var(--ppq-shadow);
    max-width: 800px;
    margin: 0 auto;
    padding: var(--ppq-space-lg);
}

/**
 * Question
 */
.ppq-question {
    margin-bottom: var(--ppq-space-lg);
}

.ppq-question__stem {
    font-size: var(--ppq-font-size-lg);
    font-weight: 600;
    margin-bottom: var(--ppq-space-md);
}

.ppq-question__stem p {
    margin: 0 0 var(--ppq-space-sm);
}

/**
 * Answer Options
 */
.ppq-options {
    list-style: none;
    margin: 0;
    padding: 0;
}

.ppq-option {
    display: block;
    padding: var(--ppq-space-md);
    margin-bottom: var(--ppq-space-sm);
    background: var(--ppq-bg-alt);
    border: 2px solid var(--ppq-border-light);
    border-radius: var(--ppq-radius);
    cursor: pointer;
    transition: all var(--ppq-transition);
}

.ppq-option:hover {
    background: var(--ppq-bg-hover);
    border-color: var(--ppq-border);
}

.ppq-option.is-selected {
    background: color-mix(in srgb, var(--ppq-primary) 10%, var(--ppq-bg));
    border-color: var(--ppq-primary);
}

.ppq-option__input {
    /* Visually hidden but accessible */
    position: absolute;
    opacity: 0;
    width: 1px;
    height: 1px;
}

.ppq-option__input:focus + .ppq-option__label {
    outline: 2px solid var(--ppq-primary);
    outline-offset: 2px;
}

.ppq-option__label {
    display: flex;
    align-items: flex-start;
    gap: var(--ppq-space-sm);
}

.ppq-option__indicator {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    border: 2px solid var(--ppq-border);
    border-radius: 50%; /* Radio */
    background: var(--ppq-bg);
    transition: all var(--ppq-transition);
}

.ppq-option--checkbox .ppq-option__indicator {
    border-radius: var(--ppq-radius); /* Checkbox */
}

.ppq-option.is-selected .ppq-option__indicator {
    background: var(--ppq-primary);
    border-color: var(--ppq-primary);
}

.ppq-option__text {
    flex: 1;
}

/**
 * Timer
 */
.ppq-timer {
    position: sticky;
    top: var(--ppq-space-md);
    display: inline-flex;
    align-items: center;
    gap: var(--ppq-space-xs);
    padding: var(--ppq-space-sm) var(--ppq-space-md);
    background: var(--ppq-bg-alt);
    border-radius: var(--ppq-radius);
    font-family: var(--ppq-font-mono);
    font-size: var(--ppq-font-size-lg);
    font-weight: 600;
    z-index: 100;
}

.ppq-timer.is-warning {
    background: var(--ppq-warning);
    color: var(--ppq-text-inverse);
}

.ppq-timer.is-critical {
    background: var(--ppq-error);
    color: var(--ppq-text-inverse);
    animation: ppq-pulse 1s infinite;
}

@keyframes ppq-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/**
 * Progress Bar
 */
.ppq-progress {
    height: 8px;
    background: var(--ppq-bg-alt);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: var(--ppq-space-md);
}

.ppq-progress__bar {
    height: 100%;
    background: var(--ppq-primary);
    transition: width var(--ppq-transition);
}

/**
 * Buttons
 */
.ppq-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--ppq-space-xs);
    padding: var(--ppq-space-sm) var(--ppq-space-lg);
    font-family: inherit;
    font-size: var(--ppq-font-size-base);
    font-weight: 600;
    line-height: 1.5;
    border: none;
    border-radius: var(--ppq-radius);
    cursor: pointer;
    transition: all var(--ppq-transition);
    min-height: 44px; /* Touch target */
}

.ppq-btn--primary {
    background: var(--ppq-primary);
    color: var(--ppq-text-inverse);
}

.ppq-btn--primary:hover {
    background: var(--ppq-primary-hover);
}

.ppq-btn--primary:focus {
    outline: 2px solid var(--ppq-primary);
    outline-offset: 2px;
}

.ppq-btn--secondary {
    background: var(--ppq-bg-alt);
    color: var(--ppq-text);
    border: 1px solid var(--ppq-border);
}

.ppq-btn--secondary:hover {
    background: var(--ppq-bg-hover);
}

.ppq-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/**
 * Results
 */
.ppq-results__hero {
    text-align: center;
    padding: var(--ppq-space-xl);
    margin-bottom: var(--ppq-space-lg);
}

.ppq-results__hero.is-passed {
    background: color-mix(in srgb, var(--ppq-success) 10%, var(--ppq-bg));
}

.ppq-results__hero.is-failed {
    background: color-mix(in srgb, var(--ppq-error) 10%, var(--ppq-bg));
}

.ppq-results__score {
    font-size: 3rem;
    font-weight: 700;
}

.ppq-results__label {
    font-size: var(--ppq-font-size-lg);
    color: var(--ppq-text-light);
}

/**
 * Responsive
 */
@media (max-width: 768px) {
    .ppq-quiz {
        padding: var(--ppq-space-md);
        border-radius: 0;
        box-shadow: none;
    }
    
    .ppq-question__stem {
        font-size: var(--ppq-font-size-base);
    }
    
    .ppq-option {
        padding: var(--ppq-space-sm) var(--ppq-space-md);
    }
    
    .ppq-results__score {
        font-size: 2rem;
    }
}

/**
 * Reduced Motion
 */
@media (prefers-reduced-motion: reduce) {
    .ppq-quiz *,
    .ppq-quiz *::before,
    .ppq-quiz *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}

/**
 * High Contrast Mode
 */
@media (prefers-contrast: high) {
    .ppq-quiz {
        --ppq-border: currentColor;
        --ppq-shadow: none;
    }
    
    .ppq-option {
        border-width: 3px;
    }
    
    .ppq-option.is-selected {
        outline: 3px solid currentColor;
    }
}
```

### PHP Theme Application

```php
/**
 * Get CSS class for quiz theme
 *
 * @param int $quiz_id Quiz ID.
 * @return string Theme class.
 */
function ppq_get_theme_class( $quiz_id ) {
    $quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
    $theme = $quiz ? $quiz->theme : ppq_get_default_theme();
    
    $valid_themes = array( 'default', 'modern', 'minimal' );
    $theme = in_array( $theme, $valid_themes, true ) ? $theme : 'default';
    
    return 'ppq-quiz-theme-' . $theme;
}

/**
 * Get custom CSS variables from quiz settings
 *
 * @param int $quiz_id Quiz ID.
 * @return string Inline style with CSS variables.
 */
function ppq_get_custom_styles( $quiz_id ) {
    $quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
    
    if ( ! $quiz || empty( $quiz->theme_settings_json ) ) {
        return '';
    }
    
    $settings = json_decode( $quiz->theme_settings_json, true );
    if ( ! $settings ) {
        return '';
    }
    
    $vars = array();
    
    $color_map = array(
        'primary_color'   => '--ppq-primary',
        'secondary_color' => '--ppq-secondary',
        'success_color'   => '--ppq-success',
        'error_color'     => '--ppq-error',
        'bg_color'        => '--ppq-bg',
        'text_color'      => '--ppq-text',
    );
    
    foreach ( $color_map as $setting => $var ) {
        if ( ! empty( $settings[ $setting ] ) ) {
            $color = sanitize_hex_color( $settings[ $setting ] );
            if ( $color ) {
                $vars[] = $var . ':' . $color;
            }
        }
    }
    
    if ( empty( $vars ) ) {
        return '';
    }
    
    return 'style="' . esc_attr( implode( ';', $vars ) ) . '"';
}

/**
 * Apply theme tokens filter
 *
 * @param string $theme Theme name.
 * @return array CSS variable tokens.
 */
function ppq_get_theme_tokens( $theme ) {
    $defaults = array(
        '--ppq-primary'   => '#0073aa',
        '--ppq-secondary' => '#50575e',
        '--ppq-success'   => '#00a32a',
        '--ppq-error'     => '#d63638',
    );
    
    return apply_filters( 'pressprimer_quiz_theme_tokens', $defaults, $theme );
}
```

### WordPress Customizer Integration

```php
<?php
/**
 * Customizer integration for PPQ Quiz styling
 *
 * @package PressPrimer_Quiz
 * @subpackage Customizer
 */

class PressPrimer_Quiz_Customizer {
    
    /**
     * Initialize customizer
     */
    public function init() {
        add_action( 'customize_register', array( $this, 'register_customizer' ) );
        add_action( 'customize_preview_init', array( $this, 'preview_scripts' ) );
        add_action( 'wp_head', array( $this, 'output_customizer_css' ), 100 );
    }
    
    /**
     * Register customizer panel, sections, and controls
     *
     * @param WP_Customize_Manager $wp_customize Customizer manager.
     */
    public function register_customizer( $wp_customize ) {
        // Panel
        $wp_customize->add_panel( 'ppq_styling', array(
            'title'       => __( 'PPQ Quiz Styling', 'pressprimer-quiz' ),
            'description' => __( 'Customize the appearance of PPQ Quizzes site-wide. Per-quiz overrides take priority over these settings.', 'pressprimer-quiz' ),
            'priority'    => 150,
        ) );
        
        // Section: Theme
        $wp_customize->add_section( 'ppq_theme', array(
            'title'    => __( 'Default Theme', 'pressprimer-quiz' ),
            'panel'    => 'ppq_styling',
            'priority' => 10,
        ) );
        
        // Section: Colors
        $wp_customize->add_section( 'ppq_colors', array(
            'title'    => __( 'Colors', 'pressprimer-quiz' ),
            'panel'    => 'ppq_styling',
            'priority' => 20,
        ) );
        
        // Theme selector
        $wp_customize->add_setting( 'ppq_default_theme', array(
            'default'           => 'default',
            'transport'         => 'postMessage',
            'sanitize_callback' => array( $this, 'sanitize_theme' ),
        ) );
        
        $wp_customize->add_control( 'ppq_default_theme', array(
            'label'   => __( 'Default Theme', 'pressprimer-quiz' ),
            'section' => 'ppq_theme',
            'type'    => 'select',
            'choices' => array(
                'default' => __( 'Default - Clean and professional', 'pressprimer-quiz' ),
                'modern'  => __( 'Modern - Bold dark mode', 'pressprimer-quiz' ),
                'minimal' => __( 'Minimal - Content-focused', 'pressprimer-quiz' ),
            ),
        ) );
        
        // Color controls
        $colors = array(
            'ppq_primary_color' => array(
                'label'   => __( 'Primary Color', 'pressprimer-quiz' ),
                'default' => '#0073aa',
                'description' => __( 'Buttons, progress bar, selected states', 'pressprimer-quiz' ),
            ),
            'ppq_secondary_color' => array(
                'label'   => __( 'Secondary Color', 'pressprimer-quiz' ),
                'default' => '#50575e',
                'description' => __( 'Accents and links', 'pressprimer-quiz' ),
            ),
            'ppq_success_color' => array(
                'label'   => __( 'Success Color', 'pressprimer-quiz' ),
                'default' => '#00a32a',
                'description' => __( 'Correct answers, pass indicator', 'pressprimer-quiz' ),
            ),
            'ppq_error_color' => array(
                'label'   => __( 'Error Color', 'pressprimer-quiz' ),
                'default' => '#d63638',
                'description' => __( 'Incorrect answers, fail indicator', 'pressprimer-quiz' ),
            ),
            'ppq_bg_color' => array(
                'label'   => __( 'Background Color', 'pressprimer-quiz' ),
                'default' => '#ffffff',
                'description' => __( 'Quiz container background', 'pressprimer-quiz' ),
            ),
            'ppq_text_color' => array(
                'label'   => __( 'Text Color', 'pressprimer-quiz' ),
                'default' => '#1d2327',
                'description' => __( 'Primary text color', 'pressprimer-quiz' ),
            ),
        );
        
        foreach ( $colors as $setting_id => $args ) {
            $wp_customize->add_setting( $setting_id, array(
                'default'           => $args['default'],
                'transport'         => 'postMessage',
                'sanitize_callback' => 'sanitize_hex_color',
            ) );
            
            $wp_customize->add_control( new WP_Customize_Color_Control(
                $wp_customize,
                $setting_id,
                array(
                    'label'       => $args['label'],
                    'description' => $args['description'],
                    'section'     => 'ppq_colors',
                )
            ) );
        }
    }
    
    /**
     * Sanitize theme selection
     *
     * @param string $value Theme value.
     * @return string Sanitized theme.
     */
    public function sanitize_theme( $value ) {
        $valid = array( 'default', 'modern', 'minimal' );
        return in_array( $value, $valid, true ) ? $value : 'default';
    }
    
    /**
     * Enqueue preview scripts for live refresh
     */
    public function preview_scripts() {
        wp_enqueue_script(
            'ppq-customizer-preview',
            PPQ_PLUGIN_URL . 'assets/js/customizer-preview.js',
            array( 'customize-preview', 'jquery' ),
            PPQ_VERSION,
            true
        );
    }
    
    /**
     * Output customizer CSS in head
     */
    public function output_customizer_css() {
        $css_vars = array();
        
        // Map customizer settings to CSS variables
        $settings_map = array(
            'ppq_primary_color'   => '--ppq-primary',
            'ppq_secondary_color' => '--ppq-secondary',
            'ppq_success_color'   => '--ppq-success',
            'ppq_error_color'     => '--ppq-error',
            'ppq_bg_color'        => '--ppq-bg',
            'ppq_text_color'      => '--ppq-text',
        );
        
        foreach ( $settings_map as $setting => $css_var ) {
            $value = get_theme_mod( $setting );
            if ( $value ) {
                $css_vars[] = $css_var . ':' . sanitize_hex_color( $value );
            }
        }
        
        if ( empty( $css_vars ) ) {
            return;
        }
        
        echo '<style id="ppq-customizer-css">.ppq-quiz{' . esc_html( implode( ';', $css_vars ) ) . '}</style>' . "\n";
    }
}

/**
 * Get resolved styles for a quiz (with priority cascade)
 *
 * @param int $quiz_id Quiz ID.
 * @return array Resolved style settings.
 */
function ppq_get_resolved_styles( $quiz_id ) {
    // Start with theme defaults
    $theme = get_theme_mod( 'ppq_default_theme', 'default' );
    $defaults = ppq_get_theme_defaults( $theme );
    
    // Layer Customizer settings
    $customizer = array(
        'primary_color'   => get_theme_mod( 'ppq_primary_color' ),
        'secondary_color' => get_theme_mod( 'ppq_secondary_color' ),
        'success_color'   => get_theme_mod( 'ppq_success_color' ),
        'error_color'     => get_theme_mod( 'ppq_error_color' ),
        'bg_color'        => get_theme_mod( 'ppq_bg_color' ),
        'text_color'      => get_theme_mod( 'ppq_text_color' ),
    );
    
    // Filter out empty values
    $customizer = array_filter( $customizer );
    
    // Merge with defaults
    $resolved = array_merge( $defaults, $customizer );
    
    // Layer per-quiz overrides (highest priority)
    if ( $quiz_id ) {
        $quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
        if ( $quiz && ! empty( $quiz->theme_settings_json ) ) {
            $quiz_settings = json_decode( $quiz->theme_settings_json, true );
            if ( is_array( $quiz_settings ) ) {
                $quiz_settings = array_filter( $quiz_settings );
                $resolved = array_merge( $resolved, $quiz_settings );
            }
        }
        
        // Per-quiz theme override
        if ( $quiz && ! empty( $quiz->theme ) ) {
            $resolved['theme'] = $quiz->theme;
        }
    }
    
    return $resolved;
}

/**
 * Get theme default colors
 *
 * @param string $theme Theme name.
 * @return array Default colors for theme.
 */
function ppq_get_theme_defaults( $theme ) {
    $themes = array(
        'default' => array(
            'primary_color'   => '#0073aa',
            'secondary_color' => '#50575e',
            'success_color'   => '#00a32a',
            'error_color'     => '#d63638',
            'bg_color'        => '#ffffff',
            'text_color'      => '#1d2327',
        ),
        'modern' => array(
            'primary_color'   => '#6366f1',
            'secondary_color' => '#a5b4fc',
            'success_color'   => '#22c55e',
            'error_color'     => '#ef4444',
            'bg_color'        => '#1e1e2e',
            'text_color'      => '#e4e4e7',
        ),
        'minimal' => array(
            'primary_color'   => '#111827',
            'secondary_color' => '#6b7280',
            'success_color'   => '#059669',
            'error_color'     => '#dc2626',
            'bg_color'        => '#ffffff',
            'text_color'      => '#111827',
        ),
    );
    
    return isset( $themes[ $theme ] ) ? $themes[ $theme ] : $themes['default'];
}
```

### Customizer Preview JavaScript

```javascript
/**
 * Customizer live preview
 */
( function( $ ) {
    'use strict';
    
    // Theme change
    wp.customize( 'ppq_default_theme', function( value ) {
        value.bind( function( newval ) {
            $( '.ppq-quiz' )
                .removeClass( 'ppq-quiz-theme-default ppq-quiz-theme-modern ppq-quiz-theme-minimal' )
                .addClass( 'ppq-quiz-theme-' + newval );
        } );
    } );
    
    // Color controls
    var colorMap = {
        'ppq_primary_color': '--ppq-primary',
        'ppq_secondary_color': '--ppq-secondary',
        'ppq_success_color': '--ppq-success',
        'ppq_error_color': '--ppq-error',
        'ppq_bg_color': '--ppq-bg',
        'ppq_text_color': '--ppq-text',
    };
    
    $.each( colorMap, function( setting, cssVar ) {
        wp.customize( setting, function( value ) {
            value.bind( function( newval ) {
                document.querySelectorAll( '.ppq-quiz' ).forEach( function( el ) {
                    el.style.setProperty( cssVar, newval );
                } );
            } );
        } );
    } );
    
} )( jQuery );
```

### Admin Theme Customizer (React)

```jsx
/**
 * Theme Customizer Component
 * React component for quiz theme settings
 */
import { useState, useEffect } from 'react';
import { ColorPicker } from '@wordpress/components';

const ThemeCustomizer = ({ quizId, initialSettings, onChange }) => {
    const [theme, setTheme] = useState(initialSettings.theme || 'default');
    const [colors, setColors] = useState(initialSettings.colors || {});
    
    const themes = [
        { value: 'default', label: 'Default', description: 'Clean and professional' },
        { value: 'modern', label: 'Modern', description: 'Bold dark mode aesthetic' },
        { value: 'minimal', label: 'Minimal', description: 'Content-focused simplicity' },
    ];
    
    const colorOptions = [
        { key: 'primary_color', label: 'Primary Color', default: '#0073aa' },
        { key: 'success_color', label: 'Success Color', default: '#00a32a' },
        { key: 'error_color', label: 'Error Color', default: '#d63638' },
    ];
    
    const handleThemeChange = (newTheme) => {
        setTheme(newTheme);
        onChange({ theme: newTheme, colors });
    };
    
    const handleColorChange = (key, color) => {
        const newColors = { ...colors, [key]: color };
        setColors(newColors);
        onChange({ theme, colors: newColors });
    };
    
    return (
        <div className="ppq-theme-customizer">
            <div className="ppq-theme-selector">
                <h3>Quiz Theme</h3>
                <div className="ppq-theme-options">
                    {themes.map((t) => (
                        <label 
                            key={t.value}
                            className={`ppq-theme-option ${theme === t.value ? 'is-selected' : ''}`}
                        >
                            <input
                                type="radio"
                                name="ppq_theme"
                                value={t.value}
                                checked={theme === t.value}
                                onChange={() => handleThemeChange(t.value)}
                            />
                            <span className="ppq-theme-option__name">{t.label}</span>
                            <span className="ppq-theme-option__desc">{t.description}</span>
                        </label>
                    ))}
                </div>
            </div>
            
            <div className="ppq-color-customizer">
                <h3>Custom Colors</h3>
                <p className="description">Override theme colors to match your brand.</p>
                
                {colorOptions.map((option) => (
                    <div key={option.key} className="ppq-color-option">
                        <label>{option.label}</label>
                        <ColorPicker
                            color={colors[option.key] || option.default}
                            onChange={(color) => handleColorChange(option.key, color)}
                            enableAlpha={false}
                        />
                        {colors[option.key] && (
                            <button 
                                type="button"
                                className="ppq-btn--link"
                                onClick={() => handleColorChange(option.key, null)}
                            >
                                Reset to default
                            </button>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
};

export default ThemeCustomizer;
```

## Database Requirements

Quiz theme settings stored in `wp_ppq_quizzes`:
- `theme` - Theme name (default, modern, minimal)
- `theme_settings_json` - JSON object with color overrides

**Customizer settings** stored as WordPress theme_mods:
- `ppq_default_theme` - Site-wide default theme
- `ppq_primary_color` - Site-wide primary color
- `ppq_secondary_color` - Site-wide secondary color
- `ppq_success_color` - Site-wide success color
- `ppq_error_color` - Site-wide error color
- `ppq_bg_color` - Site-wide background color
- `ppq_text_color` - Site-wide text color

Note: Using theme_mods means these settings travel with the active theme. If the user switches themes, they may need to reconfigure. This is standard WordPress behavior and can be addressed in documentation.

## UI/UX Requirements

### Preview
- Live preview in quiz builder when changing theme/colors
- Preview shows representative question, not full quiz

### Color Picker
- Standard WordPress color picker component
- Hex input for precise values
- Reset to default option
- Contrast warning if text/background too similar

### Theme Cards
- Visual preview of each theme (screenshot or CSS illustration)
- Clear labels and descriptions
- Radio button selection

## Not In Scope (v1.0)

- Custom CSS input (too risky for support)
- Font family selection (system fonts only)
- Custom theme creation
- Theme import/export
- Per-question styling
- Animation intensity settings

## Testing Checklist

### Theme Rendering
- [ ] Each theme renders correctly
- [ ] Color customization applies
- [ ] Inline styles don't break with CSP
- [ ] Themes work within LMS context
- [ ] No style conflicts with popular themes (Astra, Kadence, GeneratePress)

### WordPress Customizer
- [ ] PPQ Quiz Styling panel appears in Customizer
- [ ] Theme selector works with live preview
- [ ] All color pickers work with live preview
- [ ] Settings persist after save
- [ ] Customizer CSS outputs correctly in wp_head
- [ ] Priority cascade works: per-quiz > Customizer > theme defaults
- [ ] Reset to theme defaults works
- [ ] Preview displays sample quiz content
- [ ] Settings work with theme switching (documented behavior)

### Responsive Design
- [ ] Mobile layout at 375px
- [ ] Tablet layout at 768px
- [ ] Desktop layout at 1200px+

### Accessibility
- [ ] Color contrast passes WCAG AA
- [ ] All elements keyboard accessible
- [ ] Focus indicators visible
- [ ] Screen reader navigation works
- [ ] Timer visible on scroll
- [ ] Progress bar animates smoothly
- [ ] Reduced motion respected
- [ ] High contrast mode works
- [ ] RTL layout supported
