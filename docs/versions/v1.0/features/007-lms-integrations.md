# Feature 007: LMS Integrations

## Overview

PressPrimer Quiz integrates with major WordPress LMS plugins to provide seamless quiz experiences within existing course structures. When an LMS is detected, integration features automatically enable. PPQ Quizzes can be embedded in lessons/topics and trigger LMS completion events.

## Supported LMS Plugins (v1.0)

1. **LearnDash** - Most popular WordPress LMS
2. **TutorLMS** - Growing alternative with modern UI
3. **LifterLMS** - Established player with strong community

**Future (v2.0):** LearnPress integration

## User Stories

### US-007-1: Embed Quiz in Lesson
**As a** course creator  
**I want to** add a PPQ Quiz to my LearnDash/Tutor/Lifter course, lesson, or topic  
**So that** students can take quizzes within the course flow

### US-007-2: Trigger Completion
**As a** course creator  
**I want to** passing a PPQ Quiz to mark the lesson complete  
**So that** students progress through the course automatically

### US-007-3: Respect Access Rules
**As a** course creator  
**I want to** PPQ Quizzes to respect LMS enrollment and access rules  
**So that** only enrolled students can access quizzes in my courses

### US-007-4: View in LMS Context
**As a** student  
**I want to** take PPQ Quizzes without leaving the lesson page  
**So that** my learning experience feels seamless

## Acceptance Criteria

### General Integration Behavior

- [ ] Integrations auto-enable when LMS plugin is active (no manual setup)
- [ ] Integrations gracefully degrade if LMS is deactivated
- [ ] PPQ Quizzes work standalone even with LMS active
- [ ] No conflicts with native LMS quiz functionality
- [ ] Quiz results recorded in PPQ system regardless of LMS context

### LearnDash Integration

**Admin Interface:**
- [ ] Meta box on Course edit screen: "PressPrimer Quiz"
- [ ] Meta box on Lesson edit screen: "PressPrimer Quiz"
- [ ] Meta box on Topic edit screen: "PressPrimer Quiz"
- [ ] Dropdown to select PPQ Quiz (searchable)
- [ ] Option: "Require quiz pass to complete lesson/topic"
- [ ] Option: "Minimum passing score" (uses quiz default if not set)

**Frontend Display:**
- [ ] PPQ Quiz renders at bottom of lesson/topic content
- [ ] Styled to match LearnDash theme
- [ ] Clear visual separation from lesson content
- [ ] "Take Quiz" button if not started
- [ ] Quiz landing page inline or modal (configurable)

**Completion Logic:**
- [ ] On quiz pass: trigger `learndash_process_mark_complete()`
- [ ] Update lesson/topic progress
- [ ] Award course points if configured
- [ ] Trigger LearnDash hooks for other integrations

**Access Control:**
- [ ] Respect LearnDash course enrollment
- [ ] Respect lesson/topic prerequisites
- [ ] Respect drip-feed scheduling
- [ ] If not enrolled: show "Enroll to access quiz" message

### TutorLMS Integration

**Admin Interface:**
- [ ] Integration in Tutor course builder
- [ ] Add "PressPrimer Quiz" as content type in lesson
- [ ] Select quiz from dropdown
- [ ] Completion requirement toggle

**Frontend Display:**
- [ ] Render within Tutor lesson template
- [ ] Match Tutor styling
- [ ] Seamless within course player

**Completion Logic:**
- [ ] On quiz pass: mark Tutor lesson complete
- [ ] Trigger Tutor completion hooks
- [ ] Update course progress percentage

**Access Control:**
- [ ] Respect Tutor course enrollment
- [ ] Respect lesson prerequisites

### LifterLMS Integration

**Admin Interface:**
- [ ] Meta box on LLMS Lesson edit screen
- [ ] Select PPQ Quiz to attach
- [ ] Completion requirement settings

**Frontend Display:**
- [ ] Render in Lifter lesson template
- [ ] Match Lifter styling
- [ ] Position after lesson content

**Completion Logic:**
- [ ] On quiz pass: trigger `llms_mark_complete()`
- [ ] Award achievements if configured
- [ ] Trigger Lifter hooks

**Access Control:**
- [ ] Respect Lifter enrollment
- [ ] Respect access plans
- [ ] Respect prerequisite requirements

### Uncanny Automator Integration

**Triggers (fire when event occurs):**

1. **User completes a PPQ Quiz**
   - Tokens: Quiz ID, Quiz Title, Score %, Points, Pass/Fail, Attempt ID
   - User tokens: ID, Email, Display Name
   - Conditional: Specific quiz or any quiz

2. **User passes a PPQ Quiz**
   - Tokens: Quiz ID, Quiz Title, Score %, Points, Attempt ID
   - User tokens: ID, Email, Display Name
   - Conditional: Specific quiz or any quiz, minimum score

3. **User fails a PPQ Quiz**
   - Tokens: Quiz ID, Quiz Title, Score %, Points, Attempts Remaining, Attempt ID
   - User tokens: ID, Email, Display Name
   - Conditional: Specific quiz or any quiz

**No Actions in v1.0** - Actions like "Assign quiz to user" require the groups/assignment system (Tier 2).

## Technical Implementation

### Integration Detection

```php
/**
 * Detect active LMS plugins
 *
 * @return array Active LMS integrations.
 */
function ppq_detect_lms_plugins() {
    $active = array();
    
    // LearnDash
    if ( defined( 'LEARNDASH_VERSION' ) ) {
        $active['learndash'] = array(
            'name'    => 'LearnDash',
            'version' => LEARNDASH_VERSION,
            'class'   => 'PPQ_LearnDash',
        );
    }
    
    // TutorLMS
    if ( defined( 'TUTOR_VERSION' ) ) {
        $active['tutorlms'] = array(
            'name'    => 'TutorLMS', 
            'version' => TUTOR_VERSION,
            'class'   => 'PPQ_TutorLMS',
        );
    }
    
    // LifterLMS
    if ( defined( 'LLMS_PLUGIN_FILE' ) ) {
        $active['lifterlms'] = array(
            'name'    => 'LifterLMS',
            'version' => defined( 'LLMS_VERSION' ) ? LLMS_VERSION : 'unknown',
            'class'   => 'PPQ_LifterLMS',
        );
    }
    
    return apply_filters( 'ppq_active_lms_integrations', $active );
}
```

### LearnDash Integration Class

```php
<?php
/**
 * LearnDash Integration
 *
 * @package PressPrimer_Quiz
 * @subpackage Integrations
 */

class PPQ_LearnDash {
    
    /**
     * Initialize integration
     */
    public function init() {
        // Admin meta boxes
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_meta_box' ) );
        
        // Frontend display
        add_filter( 'learndash_content', array( $this, 'append_quiz_to_content' ), 20, 2 );
        
        // Completion handling
        add_action( 'ppq_quiz_passed', array( $this, 'handle_quiz_passed' ), 10, 4 );
        
        // Access control
        add_filter( 'ppq_user_can_take_quiz', array( $this, 'check_learndash_access' ), 10, 3 );
    }
    
    /**
     * Add meta box to lesson/topic edit screens
     */
    public function add_meta_boxes() {
        $post_types = array( 'sfwd-lessons', 'sfwd-topic' );
        
        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'ppq_learndash_quiz',
                __( 'PPQ Quiz', 'pressprimer-quiz' ),
                array( $this, 'render_meta_box' ),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render meta box content
     *
     * @param WP_Post $post Current post.
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'ppq_learndash_meta', 'ppq_learndash_nonce' );
        
        $quiz_id = get_post_meta( $post->ID, '_ppq_quiz_id', true );
        $require_pass = get_post_meta( $post->ID, '_ppq_require_pass', true );
        
        // Get all published quizzes
        $quizzes = ppq_get_quizzes( array( 'status' => 'published' ) );
        ?>
        <p>
            <label for="ppq_quiz_id"><?php esc_html_e( 'Select Quiz:', 'pressprimer-quiz' ); ?></label>
            <select name="ppq_quiz_id" id="ppq_quiz_id" class="widefat">
                <option value=""><?php esc_html_e( '— None —', 'pressprimer-quiz' ); ?></option>
                <?php foreach ( $quizzes as $quiz ) : ?>
                    <option value="<?php echo esc_attr( $quiz->id ); ?>" <?php selected( $quiz_id, $quiz->id ); ?>>
                        <?php echo esc_html( $quiz->title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label>
                <input type="checkbox" name="ppq_require_pass" value="1" <?php checked( $require_pass, '1' ); ?>>
                <?php esc_html_e( 'Require passing score to complete', 'pressprimer-quiz' ); ?>
            </label>
        </p>
        <?php
    }
    
    /**
     * Save meta box data
     *
     * @param int $post_id Post ID.
     */
    public function save_meta_box( $post_id ) {
        if ( ! isset( $_POST['ppq_learndash_nonce'] ) ) {
            return;
        }
        
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ppq_learndash_nonce'] ) ), 'ppq_learndash_meta' ) ) {
            return;
        }
        
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        $post_type = get_post_type( $post_id );
        if ( ! in_array( $post_type, array( 'sfwd-lessons', 'sfwd-topic' ), true ) ) {
            return;
        }
        
        $quiz_id = isset( $_POST['ppq_quiz_id'] ) ? absint( $_POST['ppq_quiz_id'] ) : 0;
        $require_pass = isset( $_POST['ppq_require_pass'] ) ? '1' : '0';
        
        update_post_meta( $post_id, '_ppq_quiz_id', $quiz_id );
        update_post_meta( $post_id, '_ppq_require_pass', $require_pass );
    }
    
    /**
     * Append quiz to lesson/topic content
     *
     * @param string $content The content.
     * @param object $post    The post object.
     * @return string Modified content.
     */
    public function append_quiz_to_content( $content, $post ) {
        $quiz_id = get_post_meta( $post->ID, '_ppq_quiz_id', true );
        
        if ( ! $quiz_id ) {
            return $content;
        }
        
        // Check if user has access
        $user_id = get_current_user_id();
        $course_id = learndash_get_course_id( $post->ID );
        
        if ( $course_id && ! sfwd_lms_has_access( $course_id, $user_id ) ) {
            $message = '<div class="ppq-access-denied">';
            $message .= esc_html__( 'Enroll in this course to access the quiz.', 'pressprimer-quiz' );
            $message .= '</div>';
            return $content . $message;
        }
        
        // Render quiz
        $quiz_html = ppq_render_quiz( $quiz_id, array(
            'context'      => 'learndash',
            'context_id'   => $post->ID,
            'context_type' => $post->post_type,
        ) );
        
        return $content . $quiz_html;
    }
    
    /**
     * Handle quiz passed - trigger LearnDash completion
     *
     * @param int   $quiz_id       PPQ Quiz ID.
     * @param int   $user_id       User ID.
     * @param float $score_percent Score percentage.
     * @param int   $attempt_id    Attempt ID.
     */
    public function handle_quiz_passed( $quiz_id, $user_id, $score_percent, $attempt_id ) {
        // Find LearnDash lessons/topics using this quiz
        global $wpdb;
        
        $posts = $wpdb->get_results( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_ppq_quiz_id' AND meta_value = %d",
            $quiz_id
        ) );
        
        foreach ( $posts as $post_data ) {
            $post_id = $post_data->post_id;
            $post_type = get_post_type( $post_id );
            
            if ( ! in_array( $post_type, array( 'sfwd-lessons', 'sfwd-topic' ), true ) ) {
                continue;
            }
            
            $require_pass = get_post_meta( $post_id, '_ppq_require_pass', true );
            
            if ( '1' === $require_pass ) {
                // Mark lesson/topic complete
                $course_id = learndash_get_course_id( $post_id );
                
                if ( $course_id ) {
                    learndash_process_mark_complete( $user_id, $post_id, false, $course_id );
                    
                    do_action( 'ppq_learndash_quiz_passed', $post_id, $quiz_id, $user_id, $attempt_id );
                }
            }
        }
    }
    
    /**
     * Check LearnDash access before allowing quiz
     *
     * @param bool $can_take Whether user can take quiz.
     * @param int  $quiz_id  PPQ Quiz ID.
     * @param int  $user_id  User ID.
     * @return bool Modified access.
     */
    public function check_learndash_access( $can_take, $quiz_id, $user_id ) {
        // Find if this quiz is attached to a LearnDash item
        global $wpdb;
        
        $post_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_ppq_quiz_id' AND meta_value = %d
             LIMIT 1",
            $quiz_id
        ) );
        
        if ( ! $post_id ) {
            return $can_take; // Not in LearnDash context
        }
        
        $post_type = get_post_type( $post_id );
        if ( ! in_array( $post_type, array( 'sfwd-lessons', 'sfwd-topic' ), true ) ) {
            return $can_take;
        }
        
        $course_id = learndash_get_course_id( $post_id );
        
        if ( $course_id && ! sfwd_lms_has_access( $course_id, $user_id ) ) {
            return false;
        }
        
        return $can_take;
    }
}
```

### TutorLMS Integration Class

```php
<?php
/**
 * TutorLMS Integration
 *
 * @package PressPrimer_Quiz
 * @subpackage Integrations
 */

class PPQ_TutorLMS {
    
    /**
     * Initialize integration
     */
    public function init() {
        // Add to course builder
        add_filter( 'tutor_lesson_contents', array( $this, 'add_quiz_content_type' ) );
        
        // Admin settings
        add_action( 'tutor_lesson_edit_modal_after_content', array( $this, 'render_quiz_selector' ) );
        add_action( 'save_post_lessons', array( $this, 'save_quiz_selection' ) );
        
        // Frontend
        add_action( 'tutor_lesson/single/after/content', array( $this, 'render_quiz_in_lesson' ) );
        
        // Completion
        add_action( 'ppq_quiz_passed', array( $this, 'handle_quiz_passed' ), 10, 4 );
    }
    
    /**
     * Handle quiz passed - trigger Tutor completion
     */
    public function handle_quiz_passed( $quiz_id, $user_id, $score_percent, $attempt_id ) {
        global $wpdb;
        
        // Find lessons using this quiz
        $lesson_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_ppq_quiz_id' AND meta_value = %d
             AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'lessons')
             LIMIT 1",
            $quiz_id
        ) );
        
        if ( ! $lesson_id ) {
            return;
        }
        
        $require_pass = get_post_meta( $lesson_id, '_ppq_require_pass', true );
        
        if ( '1' === $require_pass ) {
            // Mark Tutor lesson complete
            if ( function_exists( 'tutor_utils' ) ) {
                tutor_utils()->mark_lesson_complete( $lesson_id, $user_id );
                
                do_action( 'ppq_tutor_quiz_completed', $lesson_id, $quiz_id, $user_id, true );
            }
        }
    }
    
    // Additional methods for admin UI and frontend rendering...
}
```

### LifterLMS Integration Class

```php
<?php
/**
 * LifterLMS Integration
 *
 * @package PressPrimer_Quiz
 * @subpackage Integrations
 */

class PPQ_LifterLMS {
    
    /**
     * Initialize integration
     */
    public function init() {
        // Meta box
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_lesson', array( $this, 'save_meta_box' ) );
        
        // Frontend
        add_action( 'lifterlms_single_lesson_after_summary', array( $this, 'render_quiz' ) );
        
        // Completion
        add_action( 'ppq_quiz_passed', array( $this, 'handle_quiz_passed' ), 10, 4 );
    }
    
    /**
     * Handle quiz passed - trigger Lifter completion
     */
    public function handle_quiz_passed( $quiz_id, $user_id, $score_percent, $attempt_id ) {
        global $wpdb;
        
        $lesson_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_ppq_quiz_id' AND meta_value = %d
             AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'lesson')
             LIMIT 1",
            $quiz_id
        ) );
        
        if ( ! $lesson_id ) {
            return;
        }
        
        $require_pass = get_post_meta( $lesson_id, '_ppq_require_pass', true );
        
        if ( '1' === $require_pass && function_exists( 'llms_mark_complete' ) ) {
            llms_mark_complete( $user_id, $lesson_id, 'lesson' );
            
            do_action( 'ppq_lifter_quiz_completed', $lesson_id, $quiz_id, $user_id, true );
        }
    }
    
    // Additional methods...
}
```

### Uncanny Automator Integration

```php
<?php
/**
 * Uncanny Automator Integration
 *
 * @package PressPrimer_Quiz
 * @subpackage Integrations
 */

class PPQ_Automator {
    
    /**
     * Initialize integration
     */
    public function init() {
        // Register integration
        add_action( 'automator_configuration_complete', array( $this, 'register_integration' ) );
    }
    
    /**
     * Register PPQ integration with Automator
     */
    public function register_integration() {
        // Check Automator is available
        if ( ! class_exists( 'Uncanny_Automator\\Automator_Functions' ) ) {
            return;
        }
        
        // Register triggers
        $this->register_triggers();
    }
    
    /**
     * Register Automator triggers
     */
    private function register_triggers() {
        // User completes quiz
        add_action( 'ppq_attempt_submitted', array( $this, 'trigger_quiz_completed' ), 10, 2 );
        
        // User passes quiz
        add_action( 'ppq_quiz_passed', array( $this, 'trigger_quiz_passed' ), 10, 4 );
        
        // User fails quiz
        add_action( 'ppq_quiz_failed', array( $this, 'trigger_quiz_failed' ), 10, 4 );
    }
    
    /**
     * Fire "User completes quiz" trigger
     *
     * @param int  $attempt_id Attempt ID.
     * @param bool $passed     Whether passed.
     */
    public function trigger_quiz_completed( $attempt_id, $passed ) {
        $attempt = PPQ_Attempt::get( $attempt_id );
        if ( ! $attempt || ! $attempt->user_id ) {
            return; // Skip guests for Automator
        }
        
        $quiz = PPQ_Quiz::get( $attempt->quiz_id );
        
        $args = array(
            'code'           => 'PPQ_QUIZ_COMPLETED',
            'meta'           => 'PPQ_QUIZ',
            'post_id'        => $attempt->quiz_id,
            'user_id'        => $attempt->user_id,
            'recipe_to_match'=> array(
                'quiz_id'      => $attempt->quiz_id,
                'quiz_title'   => $quiz->title,
                'score_percent'=> $attempt->score_percent,
                'score_points' => $attempt->score_points,
                'passed'       => $passed,
                'attempt_id'   => $attempt_id,
            ),
        );
        
        if ( function_exists( 'Automator' ) ) {
            Automator()->complete->trigger( $args );
        }
    }
    
    /**
     * Fire "User passes quiz" trigger
     */
    public function trigger_quiz_passed( $quiz_id, $user_id, $score_percent, $attempt_id ) {
        if ( ! $user_id ) {
            return;
        }
        
        $quiz = PPQ_Quiz::get( $quiz_id );
        $attempt = PPQ_Attempt::get( $attempt_id );
        
        $args = array(
            'code'           => 'PPQ_QUIZ_PASSED',
            'meta'           => 'PPQ_QUIZ',
            'post_id'        => $quiz_id,
            'user_id'        => $user_id,
            'recipe_to_match'=> array(
                'quiz_id'      => $quiz_id,
                'quiz_title'   => $quiz->title,
                'score_percent'=> $score_percent,
                'score_points' => $attempt->score_points,
                'attempt_id'   => $attempt_id,
            ),
        );
        
        if ( function_exists( 'Automator' ) ) {
            Automator()->complete->trigger( $args );
        }
    }
    
    /**
     * Fire "User fails quiz" trigger
     */
    public function trigger_quiz_failed( $quiz_id, $user_id, $score_percent, $attempt_id ) {
        if ( ! $user_id ) {
            return;
        }
        
        $quiz = PPQ_Quiz::get( $quiz_id );
        $attempt = PPQ_Attempt::get( $attempt_id );
        
        // Calculate remaining attempts
        $attempts_used = ppq_count_user_attempts( $quiz_id, $user_id );
        $max_attempts = $quiz->max_attempts;
        $remaining = $max_attempts ? max( 0, $max_attempts - $attempts_used ) : -1; // -1 = unlimited
        
        $args = array(
            'code'           => 'PPQ_QUIZ_FAILED',
            'meta'           => 'PPQ_QUIZ',
            'post_id'        => $quiz_id,
            'user_id'        => $user_id,
            'recipe_to_match'=> array(
                'quiz_id'           => $quiz_id,
                'quiz_title'        => $quiz->title,
                'score_percent'     => $score_percent,
                'score_points'      => $attempt->score_points,
                'attempts_remaining'=> $remaining,
                'attempt_id'        => $attempt_id,
            ),
        );
        
        if ( function_exists( 'Automator' ) ) {
            Automator()->complete->trigger( $args );
        }
    }
}
```

## Database Requirements

No additional tables. Uses post meta to link PPQ Quizzes to LMS content:

- `_ppq_quiz_id` - PPQ Quiz ID attached to lesson/topic
- `_ppq_require_pass` - Whether passing is required for completion

## UI/UX Requirements

### Meta Box Styling
- Match WordPress admin styling
- Searchable dropdown for quiz selection (for sites with many quizzes)
- Clear labeling that distinguishes from native LMS quizzes

### Frontend Integration
- Quiz should feel native to the LMS theme
- Consistent spacing and typography
- Clear visual boundary between lesson content and quiz
- Responsive within LMS course player

## Not In Scope (v1.0)

- LearnPress integration (v2.0)
- Syncing PPQ results to LMS gradebook
- Using LMS groups for PPQ (requires Tier 2 addon)
- Automator Actions (assigning quizzes, etc.)
- Deep analytics integration with LMS dashboards

## Testing Checklist

### LearnDash
- [ ] Meta box appears on lesson edit screen
- [ ] Meta box appears on topic edit screen
- [ ] Quiz selection saves correctly
- [ ] Quiz renders in lesson frontend
- [ ] Non-enrolled user sees access denied message
- [ ] Passing quiz marks lesson complete
- [ ] LearnDash progress updates correctly
- [ ] Course points awarded (if configured)

### TutorLMS
- [ ] Quiz selector appears in lesson editor
- [ ] Quiz renders in lesson frontend
- [ ] Passing quiz marks lesson complete
- [ ] Course progress updates

### LifterLMS
- [ ] Meta box appears on lesson edit
- [ ] Quiz renders after lesson content
- [ ] Passing quiz marks lesson complete
- [ ] Achievements trigger (if configured)

### Uncanny Automator
- [ ] "User completes quiz" trigger fires
- [ ] "User passes quiz" trigger fires
- [ ] "User fails quiz" trigger fires
- [ ] All fields available in trigger data
- [ ] Recipes execute correctly

### General
- [ ] Standalone quiz works when LMS active
- [ ] Quiz works when embedded via shortcode in non-LMS page
- [ ] No PHP errors when LMS deactivated
- [ ] No conflicts with native LMS quizzes
