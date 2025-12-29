# Version 2.1 Free - Scope Document

**Status**: Planning

**Target Release**: 4-5 weeks after v2.0

**Last Updated**: 2025-12-20

---

## Release Goal

Version 2.1 focuses on **display flexibility** and **user engagement**. This release empowers quiz creators to customize exactly what information appears on quiz pages while encouraging satisfied users to leave positive reviews.

### Core Objectives

1. Celebrate user milestones and encourage WordPress.org reviews
2. Add fine-grained controls for visual appearance
3. Enable customization of Start and Results page elements via block/shortcode attributes
4. Deliver quality of life improvements based on user feedback

---

## Features in Scope

### 1. 100 Attempts Celebration Notice

**Priority**: High

**Problem Solved**: Users who are getting value from the plugin (evidenced by student engagement) should be gently encouraged to leave a review, while users with issues should be directed to support.

#### Trigger Condition

- Notice appears after the **100th completed quiz attempt** across all quizzes on the site
- "Completed" means `status = 'submitted'` in wp_ppq_attempts table
- Counts all attempts site-wide, not per-quiz

#### Display Behavior

**Location**: WordPress admin notices area, but only on PPQ admin pages

**Appearance:**
```
┌──────────────────────────────────────────────────────────────────────────────┐
│ 🎉 Congratulations! Students have completed 100 quiz attempts with           │
│    PressPrimer Quiz!                                                         │
│                                                                              │
│    We'd love to hear your feedback. Are you enjoying the plugin?             │
│                                                                              │
│    [Yes, I love it!]  [It could be better]  [Remind me later]  [✕ Dismiss]  │
└──────────────────────────────────────────────────────────────────────────────┘
```

**Response Actions:**

| Button | Action | Result |
|--------|--------|--------|
| "Yes, I love it!" | Opens WordPress.org review page | Permanently dismisses notice |
| "It could be better" | Opens PressPrimer Help Desk | Permanently dismisses notice |
| "Remind me later" | Dismisses for 30 days | Notice reappears after 30 days |
| ✕ (Dismiss icon) | Permanently dismisses | Never shows again |

#### Technical Implementation

**Attempt Count Check:**
```php
/**
 * Check if celebration notice should be shown
 *
 * @return bool True if notice should display.
 */
function pressprimer_quiz_should_show_celebration() {
    // Check if permanently dismissed
    if ( get_option( 'ppq_celebration_dismissed', false ) ) {
        return false;
    }
    
    // Check if snoozed
    $snoozed_until = get_option( 'ppq_celebration_snoozed_until', 0 );
    if ( $snoozed_until && time() < $snoozed_until ) {
        return false;
    }
    
    // Check attempt count
    global $wpdb;
    $table = $wpdb->prefix . 'ppq_attempts';
    $count = $wpdb->get_var( 
        "SELECT COUNT(*) FROM {$table} WHERE status = 'submitted'" 
    );
    
    return $count >= 100;
}
```

**AJAX Handlers:**
```php
// Handle "Yes, I love it!" response
add_action( 'wp_ajax_ppq_celebration_review', function() {
    check_ajax_referer( 'ppq_celebration', 'nonce' );
    update_option( 'ppq_celebration_dismissed', true );
    update_option( 'ppq_celebration_response', 'review' );
    wp_send_json_success( array( 
        'redirect' => 'https://wordpress.org/support/plugin/pressprimer-quiz/reviews/#new-post' 
    ) );
} );

// Handle "It could be better" response
add_action( 'wp_ajax_ppq_celebration_feedback', function() {
    check_ajax_referer( 'ppq_celebration', 'nonce' );
    update_option( 'ppq_celebration_dismissed', true );
    update_option( 'ppq_celebration_response', 'feedback' );
    wp_send_json_success( array( 
        'redirect' => 'https://pressprimer.com/help/' 
    ) );
} );

// Handle "Remind me later" response
add_action( 'wp_ajax_ppq_celebration_snooze', function() {
    check_ajax_referer( 'ppq_celebration', 'nonce' );
    $snooze_until = time() + ( 30 * DAY_IN_SECONDS );
    update_option( 'ppq_celebration_snoozed_until', $snooze_until );
    wp_send_json_success();
} );

// Handle dismiss (X) response
add_action( 'wp_ajax_ppq_celebration_dismiss', function() {
    check_ajax_referer( 'ppq_celebration', 'nonce' );
    update_option( 'ppq_celebration_dismissed', true );
    update_option( 'ppq_celebration_response', 'dismissed' );
    wp_send_json_success();
} );
```

**Options Used:**
- `ppq_celebration_dismissed` — boolean, permanently dismissed
- `ppq_celebration_snoozed_until` — timestamp, snooze expiry
- `ppq_celebration_response` — string, tracks which button was clicked (for analytics)

#### UI/UX Requirements

- Notice styling follows WordPress admin notice conventions
- Uses `notice-info` class for blue styling
- Celebratory emoji (🎉) adds positive tone
- Buttons use WordPress admin button classes
- Non-intrusive: only on PPQ pages, easily dismissible
- Accessible: proper ARIA labels, keyboard navigable

**New Files:**
- `includes/admin/class-ppq-review-notice.php`

**Modified Files:**
- `includes/class-ppq-plugin.php` — Initialize review notice

---

### 2. Visual Appearance Controls

**Priority**: Medium

**Problem Solved**: Different content contexts (embedded lessons, standalone pages, mobile) benefit from different typography and spacing settings.

#### New Settings

**Location**: PPQ Settings → Display → "Typography & Spacing"

**Settings:**

| Setting | Type | Options | Default |
|---------|------|---------|---------|
| Line Height | Select | Tight (1.3), Normal (1.5), Relaxed (1.7) | Normal |
| Answer Option Spacing | Select | Compact (8px), Normal (12px), Comfortable (16px) | Normal |
| Question Spacing | Select | Tight (16px), Normal (24px), Relaxed (32px) | Normal |
| Container Max Width | Select | Narrow (600px), Medium (800px), Wide (1000px), Full | Medium |

#### Per-Quiz Overrides

**Location**: Quiz Builder → Settings → "Appearance" section

Same settings available with "Use global default" option for each.

#### CSS Implementation

These settings output as CSS custom property overrides:

```css
/* Generated inline styles based on settings */
.ppq-quiz[data-quiz-id="123"] {
    --ppq-line-height: 1.3;
    --ppq-option-padding: 8px;
    --ppq-question-margin: 16px;
    max-width: 600px;
}
```

**Rendering:**
```php
/**
 * Get inline style overrides for quiz
 *
 * @param int $quiz_id Quiz ID.
 * @return string Inline style attribute content.
 */
function pressprimer_quiz_get_style_overrides( $quiz_id ) {
    $quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
    $settings = json_decode( $quiz->theme_settings_json, true ) ?: array();
    
    $styles = array();
    
    // Line height
    $line_heights = array( 'tight' => '1.3', 'normal' => '1.5', 'relaxed' => '1.7' );
    $lh = $settings['line_height'] ?? 'default';
    if ( 'default' === $lh ) {
        $lh = get_option( 'ppq_line_height', 'normal' );
    }
    if ( isset( $line_heights[ $lh ] ) ) {
        $styles[] = '--ppq-line-height: ' . $line_heights[ $lh ];
    }
    
    // Answer spacing
    $spacings = array( 'compact' => '8px', 'normal' => '12px', 'comfortable' => '16px' );
    $as = $settings['answer_spacing'] ?? 'default';
    if ( 'default' === $as ) {
        $as = get_option( 'ppq_answer_spacing', 'normal' );
    }
    if ( isset( $spacings[ $as ] ) ) {
        $styles[] = '--ppq-option-padding: ' . $spacings[ $as ];
    }
    
    // Question spacing
    $q_spacings = array( 'tight' => '16px', 'normal' => '24px', 'relaxed' => '32px' );
    $qs = $settings['question_spacing'] ?? 'default';
    if ( 'default' === $qs ) {
        $qs = get_option( 'ppq_question_spacing', 'normal' );
    }
    if ( isset( $q_spacings[ $qs ] ) ) {
        $styles[] = '--ppq-question-margin: ' . $q_spacings[ $qs ];
    }
    
    // Max width
    $widths = array( 'narrow' => '600px', 'medium' => '800px', 'wide' => '1000px', 'full' => '100%' );
    $mw = $settings['max_width'] ?? 'default';
    if ( 'default' === $mw ) {
        $mw = get_option( 'ppq_max_width', 'medium' );
    }
    if ( isset( $widths[ $mw ] ) ) {
        $styles[] = 'max-width: ' . $widths[ $mw ];
    }
    
    return implode( '; ', $styles );
}
```

#### Technical Implementation

**New Options:**
- `ppq_line_height` — string (tight|normal|relaxed)
- `ppq_answer_spacing` — string (compact|normal|comfortable)
- `ppq_question_spacing` — string (tight|normal|relaxed)
- `ppq_max_width` — string (narrow|medium|wide|full)

**Quiz Meta (theme_settings_json additions):**
```json
{
    "line_height": "tight",
    "answer_spacing": "compact",
    "question_spacing": "normal",
    "max_width": "wide"
}
```

**Modified Files:**
- `includes/admin/class-ppq-admin-settings.php` — Add typography/spacing section
- `includes/admin/class-ppq-admin-quiz-builder.php` — Add per-quiz overrides
- `includes/frontend/class-ppq-quiz-renderer.php` — Apply style overrides

---

### 3. Block & Shortcode Display Attributes

**Priority**: High

**Problem Solved**: Quiz creators want control over what information appears on the quiz Start (landing) page and Results page without needing custom CSS or code.

#### Start Page (Landing) Attributes

**Elements that can be shown/hidden:**

| Attribute | Description | Default |
|-----------|-------------|---------|
| `show_featured_image` | Featured image at top | true |
| `show_description` | Quiz description text | true |
| `show_question_count` | "20 Questions" indicator | true |
| `show_time_limit` | "30 Minutes" indicator | true |
| `show_passing_score` | "70% to pass" indicator | true |
| `show_attempt_limit` | "3 attempts allowed" indicator | true |
| `show_previous_attempts` | Previous attempts table (logged-in) | true |
| `show_quiz_type` | "Timed Quiz" or "Practice Quiz" label | true |

#### Results Page Attributes

| Attribute | Description | Default |
|-----------|-------------|---------|
| `show_score_percent` | Percentage score (85%) | true |
| `show_score_points` | Points score (17/20) | true |
| `show_pass_fail` | Pass/Fail indicator | true |
| `show_time_spent` | Duration display | true |
| `show_category_breakdown` | Per-category scores | true |
| `show_question_review` | Individual question review | true |
| `show_correct_answers` | Correct answers in review | quiz setting |
| `show_feedback` | Per-question/answer feedback | true |
| `show_retake_button` | "Try Again" button | true |

#### Shortcode Syntax

```php
// Start page with minimal display
[ppq_quiz id="123" 
    show_description="false" 
    show_previous_attempts="false"
    show_quiz_type="false"]

// Results page with focused display
[ppq_quiz id="123" 
    show_category_breakdown="false" 
    show_time_spent="false"]

// My Attempts block customization
[ppq_my_attempts 
    show_score="true" 
    show_date="true" 
    show_duration="false"
    per_page="10"]
```

#### Block Inspector Controls

**Quiz Block:**

In the Gutenberg sidebar, add a new panel: "Display Options"

**Start Page Section (collapsible):**
- Toggle: Show Featured Image
- Toggle: Show Description
- Toggle: Show Question Count
- Toggle: Show Time Limit
- Toggle: Show Passing Score
- Toggle: Show Attempt Limit
- Toggle: Show Previous Attempts
- Toggle: Show Quiz Type

**Results Page Section (collapsible):**
- Toggle: Show Score Percentage
- Toggle: Show Score Points
- Toggle: Show Pass/Fail Indicator
- Toggle: Show Time Spent
- Toggle: Show Category Breakdown
- Toggle: Show Question Review
- Toggle: Show Correct Answers
- Toggle: Show Feedback
- Toggle: Show Retake Button

**My Attempts Block:**
- Toggle: Show Score
- Toggle: Show Date
- Toggle: Show Duration
- Toggle: Show Quiz Title
- Number: Items Per Page

#### Technical Implementation

**Block Attributes (blocks/quiz/block.json):**
```json
{
    "attributes": {
        "quizId": { "type": "number" },
        "showFeaturedImage": { "type": "boolean", "default": true },
        "showDescription": { "type": "boolean", "default": true },
        "showQuestionCount": { "type": "boolean", "default": true },
        "showTimeLimit": { "type": "boolean", "default": true },
        "showPassingScore": { "type": "boolean", "default": true },
        "showAttemptLimit": { "type": "boolean", "default": true },
        "showPreviousAttempts": { "type": "boolean", "default": true },
        "showQuizType": { "type": "boolean", "default": true },
        "showScorePercent": { "type": "boolean", "default": true },
        "showScorePoints": { "type": "boolean", "default": true },
        "showPassFail": { "type": "boolean", "default": true },
        "showTimeSpent": { "type": "boolean", "default": true },
        "showCategoryBreakdown": { "type": "boolean", "default": true },
        "showQuestionReview": { "type": "boolean", "default": true },
        "showCorrectAnswers": { "type": "boolean", "default": true },
        "showFeedback": { "type": "boolean", "default": true },
        "showRetakeButton": { "type": "boolean", "default": true }
    }
}
```

**Shortcode Parser:**
```php
/**
 * Parse display attributes from shortcode
 *
 * @param array $atts Shortcode attributes.
 * @return array Parsed display settings.
 */
function pressprimer_quiz_parse_display_atts( $atts ) {
    $display = array();
    
    $boolean_atts = array(
        'show_featured_image',
        'show_description',
        'show_question_count',
        'show_time_limit',
        'show_passing_score',
        'show_attempt_limit',
        'show_previous_attempts',
        'show_quiz_type',
        'show_score_percent',
        'show_score_points',
        'show_pass_fail',
        'show_time_spent',
        'show_category_breakdown',
        'show_question_review',
        'show_correct_answers',
        'show_feedback',
        'show_retake_button',
    );
    
    foreach ( $boolean_atts as $att ) {
        if ( isset( $atts[ $att ] ) ) {
            $display[ $att ] = filter_var( $atts[ $att ], FILTER_VALIDATE_BOOLEAN );
        }
    }
    
    return $display;
}
```

**Renderer Integration:**
```php
/**
 * Render landing page with display options
 *
 * @param PPQ_Quiz $quiz    Quiz object.
 * @param int      $user_id User ID.
 * @param array    $display Display options.
 */
public function render_landing( $quiz, $user_id, $display = array() ) {
    $defaults = array(
        'show_featured_image'    => true,
        'show_description'       => true,
        'show_question_count'    => true,
        // ... etc
    );
    
    $display = wp_parse_args( $display, $defaults );
    
    // Use $display['show_featured_image'] to conditionally render
}
```

**New Files:**
- `blocks/quiz/inspector-controls.js` — New display options panel

**Modified Files:**
- `blocks/quiz/block.json` — Add attributes
- `blocks/quiz/edit.js` — Add inspector controls
- `blocks/my-attempts/block.json` — Add attributes
- `blocks/my-attempts/edit.js` — Add inspector controls
- `includes/frontend/class-ppq-shortcodes.php` — Parse new attributes
- `includes/frontend/class-ppq-quiz-renderer.php` — Conditional rendering
- `includes/frontend/class-ppq-results-renderer.php` — Conditional rendering

---

### 4. Quality of Life Improvements

**Priority**: Medium

These are smaller improvements that enhance the overall user experience.

#### 4.1 Timer Position Options

**New Setting**: PPQ Settings → Display → "Timer Position"

**Options:**
- Top right (default)
- Top left
- Bottom fixed bar
- Inline with progress bar

**Per-quiz override available.**

**Implementation:**
```css
/* Timer position variants */
.ppq-timer--top-right {
    position: fixed;
    top: 50px;
    right: 20px;
}

.ppq-timer--top-left {
    position: fixed;
    top: 50px;
    left: 20px;
}

.ppq-timer--bottom {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 8px;
    background: var(--ppq-bg);
    border-top: 1px solid var(--ppq-border);
}

.ppq-timer--inline {
    /* Renders within progress bar area */
}
```

#### 4.2 Improved Mobile Navigation

**Enhancements:**
- Larger touch targets on mobile (minimum 48×48px)
- Current question indicator always visible
- Swipe gesture hint on first question (optional, can be disabled)
- Question navigator collapses to floating button on mobile

**New option**: "Enable swipe navigation" (default: false)

#### 4.3 Admin Quick Actions

**Dashboard Widget Improvements:**
- "Create Quiz" button more prominent
- Quick links: View Reports, Manage Questions, Settings
- Recent activity summary

**Quiz List Improvements:**
- Bulk duplicate action
- Quick status toggle (draft ↔ published)
- "View Results" link for each quiz

#### 4.4 Keyboard Shortcut Hints

**For quiz takers (optional, can be disabled):**
- Show keyboard hints in tooltip: "Press 1-5 for options, Enter to continue"
- Only shown first time user takes a quiz (localStorage flag)

**Setting**: PPQ Settings → Display → "Show keyboard shortcut hints" (default: true)

---

## What's NOT in v2.1

These are explicitly **excluded** from version 2.1:

### Excluded (moved to v2.2)
- Maximum questions from pool
- Cache clearing button
- Quiz attempt history pagination
- Question navigator styling options

### Excluded (premium only)
- Timer pause/extend functionality
- Additional timer warning thresholds
- Custom timer sounds

---

## Database Changes

**No new tables required.**

**New Options:**
- `ppq_line_height` — string
- `ppq_answer_spacing` — string
- `ppq_question_spacing` — string
- `ppq_max_width` — string
- `ppq_timer_position` — string
- `ppq_enable_swipe_nav` — boolean
- `ppq_show_keyboard_hints` — boolean
- `ppq_celebration_dismissed` — boolean
- `ppq_celebration_snoozed_until` — int (timestamp)
- `ppq_celebration_response` — string

**Quiz Meta (theme_settings_json additions):**
```json
{
    "line_height": "normal",
    "answer_spacing": "normal",
    "question_spacing": "normal",
    "max_width": "medium",
    "timer_position": "top-right"
}
```

---

## File Changes Summary

### New Files

```
includes/admin/class-ppq-review-notice.php
blocks/quiz/inspector-controls.js
```

### Modified Files

```
pressprimer-quiz.php                              # Version bump
includes/class-ppq-plugin.php                     # Initialize review notice
includes/admin/class-ppq-admin-settings.php       # Typography, timer position settings
includes/admin/class-ppq-admin-quiz-builder.php   # Per-quiz appearance overrides
includes/frontend/class-ppq-shortcodes.php        # Parse display attributes
includes/frontend/class-ppq-quiz-renderer.php     # Conditional rendering, timer position
includes/frontend/class-ppq-results-renderer.php  # Conditional rendering
blocks/quiz/block.json                            # New attributes
blocks/quiz/edit.js                               # Inspector controls
blocks/my-attempts/block.json                     # New attributes
blocks/my-attempts/edit.js                        # Inspector controls
assets/css/frontend.css                           # Timer positions, mobile nav
assets/js/quiz.js                                 # Swipe nav, keyboard hints
```

---

## Testing Checklist

### 100 Attempts Celebration Notice
- [ ] Notice appears after 100th completed attempt
- [ ] Notice only shows on PPQ admin pages
- [ ] "Yes, I love it!" opens WordPress.org reviews
- [ ] "It could be better" opens Help Desk
- [ ] "Remind me later" snoozes for 30 days
- [ ] Dismiss (X) permanently hides notice
- [ ] Notice doesn't reappear after permanent dismissal
- [ ] Response is logged in options

### Visual Appearance Controls
- [ ] Line height setting applies correctly
- [ ] Answer spacing setting applies correctly
- [ ] Question spacing setting applies correctly
- [ ] Container max width setting applies correctly
- [ ] Per-quiz overrides work
- [ ] Settings cascade properly (quiz → global → default)

### Block & Shortcode Attributes
- [ ] All Start page attributes work in shortcode
- [ ] All Results page attributes work in shortcode
- [ ] Block inspector controls toggle correctly
- [ ] Block preview updates with attribute changes
- [ ] My Attempts block attributes work
- [ ] Boolean parsing handles "true"/"false" strings

### Timer Position Options
- [ ] Top right position works
- [ ] Top left position works
- [ ] Bottom fixed bar works
- [ ] Inline with progress works
- [ ] Per-quiz override works
- [ ] Mobile responsiveness maintained

### Mobile Navigation
- [ ] Touch targets meet 48px minimum
- [ ] Question indicator visible
- [ ] Swipe navigation works (when enabled)
- [ ] Navigator collapses to floating button

### Quality of Life Improvements
- [ ] Dashboard quick actions work
- [ ] Quiz list bulk duplicate works
- [ ] Quick status toggle works
- [ ] Keyboard hint shows on first quiz
- [ ] Keyboard hint can be dismissed

---

## Success Metrics

### Launch Targets (within 14 days)
- 95%+ of v2.0 users update without issues
- <1% support tickets related to upgrade
- Zero critical bugs

### Ongoing Targets
- 10+ WordPress.org reviews driven by celebration notice within 90 days
- 25%+ of quizzes use at least one display customization
- Reduced support tickets about quiz height/scrolling
- Positive feedback mentioning customization options

---

## Development Notes

### Branching Strategy

```
main
└── release/2.1
    ├── feature/celebration-notice
    ├── feature/visual-controls
    ├── feature/block-attributes
    └── feature/qol-improvements
```

### Testing Priorities

1. Celebration notice triggers correctly
2. Block attributes work in Gutenberg and shortcodes
3. Visual controls apply correctly across themes
4. Mobile navigation improvements accessible
5. No regression in quiz taking experience

### Compatibility Notes

- Block attributes are backwards compatible (defaults preserve current behavior)
- Shortcode without display attributes behaves as before
- All new settings default to current behavior

