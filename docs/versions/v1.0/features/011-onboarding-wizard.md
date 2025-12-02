# Feature 011: Onboarding Wizard

## Overview

The onboarding wizard provides new users with an interactive, guided introduction to PPQ Quizzes. It walks users through the main features, helps them understand the workflow, and optionally guides them through creating their first quiz. The wizard is engaging, skippable, and can be relaunched at any time.

## User Stories

### US-011-1: First-Time Guidance
**As a** new administrator  
**I want to** understand how PPQ Quizzes works  
**So that** I can start creating effective quizzes quickly

### US-011-2: Interactive Tour
**As a** new user  
**I want to** explore each section of the plugin interactively  
**So that** I learn by doing rather than reading documentation

### US-011-3: Skip Option
**As an** experienced user  
**I want to** skip the onboarding wizard  
**So that** I can get straight to work

### US-011-4: Relaunch Wizard
**As a** user  
**I want to** access the onboarding wizard again  
**So that** I can refresh my knowledge or train colleagues

## Acceptance Criteria

### Wizard Trigger

- [ ] Wizard launches automatically on first plugin activation
- [ ] Wizard shows for any user with `ppq_manage_own` who hasn't completed/skipped it
- [ ] "Launch Onboarding" button available on Dashboard widget
- [ ] "Launch Onboarding" link in Settings page
- [ ] Wizard state stored per-user in user meta

### Welcome Screen

- [ ] Full-screen modal overlay
- [ ] Welcoming headline and brief description
- [ ] "Get Started" button (begins wizard)
- [ ] "Skip for Now" button (dismisses wizard)
- [ ] "Don't Show Again" checkbox (permanent skip)
- [ ] Animated logo or illustration
- [ ] Progress indicator showing wizard steps

### Wizard Steps

**Step 1: Overview**
- [ ] Explains the PPQ workflow: Questions → Banks → Quizzes → Results
- [ ] Visual diagram or animation showing the flow
- [ ] Highlights key benefits
- [ ] "Next" button to continue

**Step 2: Questions Tour**
- [ ] Navigates user to Questions page
- [ ] Highlights key UI elements with tooltips/spotlights
- [ ] Explains question types (MC, MA, TF)
- [ ] Shows where to create new questions
- [ ] Optional: Creates a sample question together
- [ ] "Next" to continue, "Skip" available

**Step 3: Question Banks Tour**
- [ ] Navigates to Question Banks page
- [ ] Explains organizing questions into banks
- [ ] Shows how banks help with quiz generation
- [ ] Highlights "Create Bank" button
- [ ] "Next" to continue, "Skip" available

**Step 4: Quiz Builder Tour**
- [ ] Navigates to Quizzes page
- [ ] Explains fixed vs dynamic quiz modes
- [ ] Shows quiz builder interface
- [ ] Highlights settings panels
- [ ] Demonstrates drag-and-drop (if fixed mode)
- [ ] "Next" to continue, "Skip" available

**Step 5: AI Generation (Optional)**
- [ ] Only shows if OpenAI API key is configured
- [ ] Otherwise shows teaser about AI capabilities
- [ ] Explains how to generate questions from content
- [ ] Link to Settings to configure API key
- [ ] "Next" to continue, "Skip" available

**Step 6: Reports Tour**
- [ ] Navigates to Reports page
- [ ] Shows where to find quiz performance
- [ ] Explains available metrics
- [ ] "Next" to continue

**Step 7: Completion**
- [ ] Congratulations message
- [ ] Summary of what was learned
- [ ] Quick action buttons:
  - Create Your First Quiz
  - Add Questions
  - Explore Settings
  - View Documentation (external link)
- [ ] "Finish" button to complete wizard

### Interactive Elements

- [ ] Spotlight/highlight effect on target elements
- [ ] Tooltips with explanatory text
- [ ] Smooth page transitions
- [ ] Progress bar showing current step
- [ ] Step counter (e.g., "Step 3 of 7")
- [ ] Back button to revisit previous steps
- [ ] Skip current step option
- [ ] Exit wizard button (with confirmation)

### State Management

- [ ] Track wizard completion in user meta: `ppq_onboarding_completed`
- [ ] Track permanent skip in user meta: `ppq_onboarding_skipped`
- [ ] Track current step for resume: `ppq_onboarding_step`
- [ ] Reset option in Settings (for admins)

## Technical Implementation

### User Meta Keys

```php
// User meta keys for onboarding state
'ppq_onboarding_completed' => true/false
'ppq_onboarding_skipped'   => true/false  
'ppq_onboarding_step'      => 1-7 (for resume)
'ppq_onboarding_started'   => timestamp
```

### Onboarding Check

```php
/**
 * Check if onboarding should be shown
 *
 * @return bool
 */
function ppq_should_show_onboarding() {
    if ( ! current_user_can( 'ppq_manage_own' ) ) {
        return false;
    }
    
    $user_id = get_current_user_id();
    
    // Check if completed or permanently skipped
    $completed = get_user_meta( $user_id, 'ppq_onboarding_completed', true );
    $skipped = get_user_meta( $user_id, 'ppq_onboarding_skipped', true );
    
    if ( $completed || $skipped ) {
        return false;
    }
    
    return true;
}

/**
 * Mark onboarding as completed
 *
 * @param int $user_id User ID.
 */
function ppq_complete_onboarding( $user_id = null ) {
    $user_id = $user_id ?: get_current_user_id();
    
    update_user_meta( $user_id, 'ppq_onboarding_completed', true );
    update_user_meta( $user_id, 'ppq_onboarding_step', 7 );
    
    // Log completion event
    ppq_log_event( 'onboarding_completed', array( 'user_id' => $user_id ) );
}

/**
 * Skip onboarding
 *
 * @param int  $user_id   User ID.
 * @param bool $permanent Whether to permanently skip.
 */
function ppq_skip_onboarding( $user_id = null, $permanent = false ) {
    $user_id = $user_id ?: get_current_user_id();
    
    if ( $permanent ) {
        update_user_meta( $user_id, 'ppq_onboarding_skipped', true );
    }
    
    // Mark as "completed" for this session
    update_user_meta( $user_id, 'ppq_onboarding_completed', true );
}

/**
 * Reset onboarding for a user
 *
 * @param int $user_id User ID.
 */
function ppq_reset_onboarding( $user_id = null ) {
    $user_id = $user_id ?: get_current_user_id();
    
    delete_user_meta( $user_id, 'ppq_onboarding_completed' );
    delete_user_meta( $user_id, 'ppq_onboarding_skipped' );
    delete_user_meta( $user_id, 'ppq_onboarding_step' );
    delete_user_meta( $user_id, 'ppq_onboarding_started' );
}
```

### React Component Structure

```
assets/js/admin/components/Onboarding/
├── index.jsx              # Main onboarding container
├── WelcomeScreen.jsx      # Initial welcome modal
├── WizardStep.jsx         # Base step component
├── steps/
│   ├── OverviewStep.jsx   # Step 1: Workflow overview
│   ├── QuestionsStep.jsx  # Step 2: Questions tour
│   ├── BanksStep.jsx      # Step 3: Question banks tour
│   ├── QuizBuilderStep.jsx# Step 4: Quiz builder tour
│   ├── AIStep.jsx         # Step 5: AI generation
│   ├── ReportsStep.jsx    # Step 6: Reports tour
│   └── CompletionStep.jsx # Step 7: Completion
├── Spotlight.jsx          # Element highlighting
├── Tooltip.jsx            # Explanatory tooltips
├── ProgressBar.jsx        # Step progress indicator
└── hooks/
    └── useOnboarding.js   # Onboarding state hook
```

### Spotlight Component

```jsx
/**
 * Spotlight component to highlight UI elements
 */
import { useState, useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';

const Spotlight = ({ targetSelector, children, position = 'bottom' }) => {
    const [targetRect, setTargetRect] = useState(null);
    
    useEffect(() => {
        const target = document.querySelector(targetSelector);
        if (target) {
            const rect = target.getBoundingClientRect();
            setTargetRect(rect);
            
            // Scroll target into view if needed
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, [targetSelector]);
    
    if (!targetRect) return null;
    
    return createPortal(
        <>
            {/* Overlay with cutout */}
            <div className="ppq-spotlight-overlay">
                <svg className="ppq-spotlight-svg">
                    <defs>
                        <mask id="spotlight-mask">
                            <rect fill="white" width="100%" height="100%" />
                            <rect 
                                fill="black" 
                                x={targetRect.left - 8}
                                y={targetRect.top - 8}
                                width={targetRect.width + 16}
                                height={targetRect.height + 16}
                                rx="8"
                            />
                        </mask>
                    </defs>
                    <rect 
                        fill="rgba(0,0,0,0.75)" 
                        width="100%" 
                        height="100%" 
                        mask="url(#spotlight-mask)"
                    />
                </svg>
            </div>
            
            {/* Highlight border */}
            <div 
                className="ppq-spotlight-highlight"
                style={{
                    top: targetRect.top - 8,
                    left: targetRect.left - 8,
                    width: targetRect.width + 16,
                    height: targetRect.height + 16,
                }}
            />
            
            {/* Tooltip content */}
            <div 
                className={`ppq-spotlight-tooltip ppq-spotlight-tooltip--${position}`}
                style={getTooltipPosition(targetRect, position)}
            >
                {children}
            </div>
        </>,
        document.body
    );
};
```

### AJAX Handlers

```php
/**
 * AJAX handler to save onboarding progress
 */
add_action( 'wp_ajax_ppq_onboarding_progress', 'ppq_ajax_onboarding_progress' );

function ppq_ajax_onboarding_progress() {
    check_ajax_referer( 'ppq_admin', 'nonce' );
    
    if ( ! current_user_can( 'ppq_manage_own' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
    }
    
    $action = sanitize_text_field( $_POST['onboarding_action'] ?? '' );
    $step = absint( $_POST['step'] ?? 0 );
    $user_id = get_current_user_id();
    
    switch ( $action ) {
        case 'start':
            update_user_meta( $user_id, 'ppq_onboarding_started', time() );
            update_user_meta( $user_id, 'ppq_onboarding_step', 1 );
            break;
            
        case 'progress':
            update_user_meta( $user_id, 'ppq_onboarding_step', $step );
            break;
            
        case 'complete':
            ppq_complete_onboarding( $user_id );
            break;
            
        case 'skip':
            $permanent = ! empty( $_POST['permanent'] );
            ppq_skip_onboarding( $user_id, $permanent );
            break;
            
        case 'reset':
            if ( current_user_can( 'ppq_manage_settings' ) ) {
                ppq_reset_onboarding( $user_id );
            }
            break;
    }
    
    wp_send_json_success();
}

/**
 * AJAX handler to get onboarding state
 */
add_action( 'wp_ajax_ppq_get_onboarding_state', 'ppq_ajax_get_onboarding_state' );

function ppq_ajax_get_onboarding_state() {
    check_ajax_referer( 'ppq_admin', 'nonce' );
    
    $user_id = get_current_user_id();
    
    wp_send_json_success( array(
        'should_show' => ppq_should_show_onboarding(),
        'step'        => (int) get_user_meta( $user_id, 'ppq_onboarding_step', true ) ?: 1,
        'completed'   => (bool) get_user_meta( $user_id, 'ppq_onboarding_completed', true ),
        'skipped'     => (bool) get_user_meta( $user_id, 'ppq_onboarding_skipped', true ),
        'has_api_key' => ppq_has_valid_api_key(),
    ) );
}
```

## UI/UX Requirements

### Welcome Screen

```
+--------------------------------------------------+
|                                                   |
|                    [PPQ Logo]                     |
|                                                   |
|         Welcome to PPQ Quizzes! 🎉                |
|                                                   |
|   Create powerful quizzes for your WordPress      |
|   site in minutes. Let us show you around!        |
|                                                   |
|           [●○○○○○○] Step 1 of 7                   |
|                                                   |
|        +-------------------------+                |
|        |     Get Started →       |                |
|        +-------------------------+                |
|                                                   |
|        [Skip for Now]                             |
|        □ Don't show this again                    |
|                                                   |
+--------------------------------------------------+
```

### Step with Spotlight

```
+--------------------------------------------------+
|  ████████████████████████████████████████████████|
|  ███+----------------------------------------+███|
|  ███|  Create Your First Question            |███|
|  ███|                                        |███|
|  ███|  This is where you write your question |███|
|  ███|  stem. You can use rich text including |███|
|  ███|  bold, links, and images.              |███|
|  ███|                                        |███|
|  ███|  💡 Tip: Clear, concise questions      |███|
|  ███|     lead to better learning outcomes!  |███|
|  ███|                                        |███|
|  ███|  [← Back]  [Skip Step]  [Next →]       |███|
|  ███+----------------------------------------+███|
|  █████████████████↓████████████████████████████|
|  ███████████+============+████████████████████|
|  ███████████| HIGHLIGHTED |███████████████████|
|  ███████████| ELEMENT     |███████████████████|
|  ███████████+============+████████████████████|
|  ████████████████████████████████████████████████|
+--------------------------------------------------+
```

### Completion Screen

```
+--------------------------------------------------+
|                                                   |
|                      🎉                           |
|                                                   |
|          You're All Set!                          |
|                                                   |
|   You've learned the basics of PPQ Quizzes.       |
|   Here's what you can do next:                    |
|                                                   |
|   ✓ Create questions and organize them in banks   |
|   ✓ Build quizzes with fixed or dynamic content   |
|   ✓ Track results and student performance         |
|                                                   |
|   +---------------------+  +------------------+   |
|   | Create Your First   |  | Add Questions    |   |
|   | Quiz →              |  |                  |   |
|   +---------------------+  +------------------+   |
|                                                   |
|   +---------------------+  +------------------+   |
|   | Explore Settings    |  | View Docs ↗      |   |
|   +---------------------+  +------------------+   |
|                                                   |
|        +-------------------------+                |
|        |        Finish           |                |
|        +-------------------------+                |
|                                                   |
+--------------------------------------------------+
```

### CSS Classes

```css
/* Onboarding overlay */
.ppq-onboarding-overlay {
    position: fixed;
    inset: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Welcome modal */
.ppq-onboarding-welcome {
    background: #fff;
    border-radius: 12px;
    padding: 48px;
    max-width: 520px;
    text-align: center;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    animation: ppq-fade-in 0.3s ease-out;
}

/* Spotlight */
.ppq-spotlight-overlay {
    position: fixed;
    inset: 0;
    z-index: 99998;
}

.ppq-spotlight-highlight {
    position: fixed;
    border: 3px solid var(--ppq-primary, #2271b1);
    border-radius: 8px;
    box-shadow: 0 0 0 4px rgba(34, 113, 177, 0.3);
    z-index: 99999;
    pointer-events: none;
    animation: ppq-pulse 2s infinite;
}

.ppq-spotlight-tooltip {
    position: fixed;
    background: #fff;
    border-radius: 8px;
    padding: 24px;
    max-width: 400px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    z-index: 100000;
}

/* Progress bar */
.ppq-onboarding-progress {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin: 24px 0;
}

.ppq-onboarding-progress__dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ddd;
    transition: all 0.2s;
}

.ppq-onboarding-progress__dot--active {
    background: var(--ppq-primary);
    transform: scale(1.2);
}

.ppq-onboarding-progress__dot--completed {
    background: var(--ppq-success);
}

/* Animations */
@keyframes ppq-fade-in {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes ppq-pulse {
    0%, 100% { box-shadow: 0 0 0 4px rgba(34, 113, 177, 0.3); }
    50% { box-shadow: 0 0 0 8px rgba(34, 113, 177, 0.1); }
}
```

## Accessibility Requirements

- [ ] All elements keyboard navigable
- [ ] Focus trapped within modal
- [ ] Escape key closes/skips wizard
- [ ] Progress announced to screen readers
- [ ] Tooltips have proper ARIA labels
- [ ] Spotlight doesn't prevent keyboard navigation
- [ ] Sufficient color contrast
- [ ] Reduced motion support (respect prefers-reduced-motion)

## Localization

All wizard text must use proper i18n functions:
- Headings and body text via `__()`
- Buttons and labels via `__()`
- Numbered steps via `sprintf()` with proper placeholders
- Consider RTL layout for tooltip positioning

## Testing Checklist

### First Launch
- [ ] Wizard appears on first admin page load
- [ ] Welcome screen displays correctly
- [ ] Progress dots show correctly
- [ ] "Get Started" begins wizard
- [ ] "Skip for Now" dismisses wizard
- [ ] "Don't show again" sets permanent skip

### Wizard Navigation
- [ ] Each step loads correctly
- [ ] Spotlight highlights correct elements
- [ ] Tooltips position correctly
- [ ] Back button returns to previous step
- [ ] Skip step advances without action
- [ ] Page navigation works between steps
- [ ] Progress bar updates correctly

### State Persistence
- [ ] Current step saved on navigation
- [ ] Refresh resumes at correct step
- [ ] Completion state saved
- [ ] Skip state saved
- [ ] Reset clears all state

### Relaunch
- [ ] Dashboard widget shows "Launch Onboarding"
- [ ] Settings shows "Launch Onboarding" 
- [ ] Relaunch starts from beginning
- [ ] Previously completed users can still relaunch

### Edge Cases
- [ ] Works on all PPQ admin pages
- [ ] Handles missing elements gracefully
- [ ] Works with different screen sizes
- [ ] No conflicts with other modals
- [ ] No z-index conflicts

### Accessibility
- [ ] Tab navigation works
- [ ] Screen reader announces steps
- [ ] Escape closes wizard
- [ ] Focus management correct
- [ ] Works with reduced motion
