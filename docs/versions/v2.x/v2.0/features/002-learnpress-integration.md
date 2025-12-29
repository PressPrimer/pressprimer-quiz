# Feature: LearnPress Integration

**Version:** 2.0  
**Plugin:** Free  
**Priority:** High  
**Status:** Planning

---

## Overview

LearnPress is a popular free LMS plugin (v4.3.2.2 as of Dec 2025). This integration allows PPQ quizzes to be embedded in LearnPress lessons with optional completion tracking—matching the existing LearnDash, TutorLMS, and LifterLMS integrations.

## User Stories

1. As a LearnPress instructor, I want to add a PPQ quiz to my lesson so students can test their knowledge.
2. As a LearnPress instructor, I want to require students to pass a quiz before the lesson is marked complete.
3. As a student, I want my quiz results to automatically update my lesson progress.

## Technical Specification

### LearnPress Architecture Notes (v4.x)

Based on analysis of LearnPress 4.3.2.2:

- **Post type**: `lp_lesson` for lessons (constant `LP_LESSON_CPT`)
- **Database tables**: `{prefix}learnpress_sections` and `{prefix}learnpress_section_items` link lessons to courses
- **Frontend globals**: `LP_Global::course_item()` returns current lesson, `learn_press_get_course()` returns current course
- **User API**: `learn_press_get_user($user_id)` returns `LP_User` or `LP_User_Guest`
- **Enrollment**: `$user->has_enrolled_course($course_id)` checks enrollment status
- **Completion**: `$user->complete_lesson($lesson_id, $course_id)` marks lesson complete

### Key Hooks (Verified in Plugin Source)

| Hook | Type | Location | Purpose |
|------|------|----------|---------|
| `learnpress/lesson-settings/after` | action | `inc/admin/views/meta-boxes/lesson/settings.php:75` | Add fields to lesson settings meta box |
| `learn-press/after-content-item-summary/lp_lesson` | action | `inc/lp-template-hooks.php:338` | Insert content after lesson (priority 9 = before materials) |
| `learn-press/user-completed-lesson` | action | `inc/user/abstract-lp-user.php:789` | Fires when lesson is completed |

### Integration Class

**File:** `includes/integrations/class-ppq-learnpress.php`

```php
<?php
/**
 * LearnPress Integration
 *
 * Adds PPQ quiz support to LearnPress lessons.
 *
 * @package PressPrimer_Quiz
 * @since 2.0.0
 */

class PressPrimer_Quiz_LearnPress {

    /**
     * Minimum LearnPress version required
     */
    const MIN_VERSION = '4.0.0';

    /**
     * Initialize integration
     */
    public static function init() {
        // Only load if LearnPress is active and meets version requirement
        if ( ! self::is_learnpress_active() ) {
            return;
        }

        // Admin hooks - inject into LP's native lesson settings panel
        add_action( 'learnpress/lesson-settings/after', array( __CLASS__, 'render_lesson_settings' ) );
        add_action( 'save_post_lp_lesson', array( __CLASS__, 'save_lesson_meta' ) );

        // Frontend hooks - add quiz after lesson content, before materials (priority 9)
        add_action( 'learn-press/after-content-item-summary/lp_lesson', array( __CLASS__, 'render_quiz_in_lesson' ), 9 );
        
        // Quiz completion hooks
        add_action( 'pressprimer_quiz_attempt_submitted', array( __CLASS__, 'handle_quiz_completion' ), 10, 2 );

        // Enqueue styles
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
    }

    /**
     * Check if LearnPress is active and meets version requirement
     *
     * @return bool
     */
    public static function is_learnpress_active() {
        if ( ! defined( 'LEARNPRESS_VERSION' ) ) {
            return false;
        }

        return version_compare( LEARNPRESS_VERSION, self::MIN_VERSION, '>=' );
    }

    /**
     * Render PPQ settings in LearnPress lesson settings meta box
     * 
     * Hooks into: learnpress/lesson-settings/after
     * This integrates with LP's native meta box rather than adding a separate one.
     */
    public static function render_lesson_settings() {
        global $post;
        
        if ( ! $post || ! defined( 'LP_LESSON_CPT' ) || $post->post_type !== LP_LESSON_CPT ) {
            return;
        }

        wp_nonce_field( 'ppq_learnpress_meta', 'ppq_learnpress_nonce' );

        $quiz_id      = get_post_meta( $post->ID, '_ppq_quiz_id', true );
        $require_pass = get_post_meta( $post->ID, '_ppq_require_pass', true );
        $min_score    = get_post_meta( $post->ID, '_ppq_min_score', true );

        // Get all published quizzes
        $quizzes = PressPrimer_Quiz_Quiz::get_all( array(
            'status'  => 'published',
            'orderby' => 'title',
            'order'   => 'ASC',
        ) );

        ?>
        <div class="lp-meta-box__field ppq-learnpress-settings">
            <h4 style="margin: 20px 0 10px; padding-top: 15px; border-top: 1px solid #ddd;">
                <?php esc_html_e( 'PressPrimer Quiz', 'pressprimer-quiz' ); ?>
            </h4>
            
            <div class="lp-meta-box__field-input" style="margin-bottom: 15px;">
                <label for="ppq_quiz_id" style="display: block; margin-bottom: 5px; font-weight: 600;">
                    <?php esc_html_e( 'Embed Quiz', 'pressprimer-quiz' ); ?>
                </label>
                <select name="ppq_quiz_id" id="ppq_quiz_id" style="width: 100%; max-width: 400px;">
                    <option value=""><?php esc_html_e( '— None —', 'pressprimer-quiz' ); ?></option>
                    <?php foreach ( $quizzes as $quiz ) : ?>
                        <option value="<?php echo esc_attr( $quiz->id ); ?>" <?php selected( $quiz_id, $quiz->id ); ?>>
                            <?php echo esc_html( $quiz->title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lp-meta-box__field-input" style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="ppq_require_pass" value="1" <?php checked( $require_pass, '1' ); ?> />
                    <?php esc_html_e( 'Require quiz pass to complete lesson', 'pressprimer-quiz' ); ?>
                </label>
            </div>

            <div class="lp-meta-box__field-input">
                <label for="ppq_min_score" style="display: block; margin-bottom: 5px; font-weight: 600;">
                    <?php esc_html_e( 'Minimum Score Override (%)', 'pressprimer-quiz' ); ?>
                </label>
                <input type="number" name="ppq_min_score" id="ppq_min_score" 
                       value="<?php echo esc_attr( $min_score ); ?>" 
                       min="0" max="100" step="1" style="width: 100px;"
                       placeholder="<?php esc_attr_e( 'Default', 'pressprimer-quiz' ); ?>" />
                <p class="description" style="margin-top: 5px; color: #666;">
                    <?php esc_html_e( 'Leave empty to use the quiz\'s pass percentage.', 'pressprimer-quiz' ); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Save lesson meta
     *
     * @param int $post_id Post ID.
     */
    public static function save_lesson_meta( $post_id ) {
        // Verify nonce
        if ( ! isset( $_POST['ppq_learnpress_nonce'] ) || 
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ppq_learnpress_nonce'] ) ), 'ppq_learnpress_meta' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save quiz ID
        if ( isset( $_POST['ppq_quiz_id'] ) ) {
            $quiz_id = absint( $_POST['ppq_quiz_id'] );
            if ( $quiz_id > 0 ) {
                update_post_meta( $post_id, '_ppq_quiz_id', $quiz_id );
            } else {
                delete_post_meta( $post_id, '_ppq_quiz_id' );
            }
        }

        // Save require pass
        $require_pass = isset( $_POST['ppq_require_pass'] ) ? '1' : '0';
        update_post_meta( $post_id, '_ppq_require_pass', $require_pass );

        // Save minimum score
        if ( isset( $_POST['ppq_min_score'] ) && $_POST['ppq_min_score'] !== '' ) {
            $min_score = absint( $_POST['ppq_min_score'] );
            $min_score = min( 100, max( 0, $min_score ) );
            update_post_meta( $post_id, '_ppq_min_score', $min_score );
        } else {
            delete_post_meta( $post_id, '_ppq_min_score' );
        }
    }

    /**
     * Render quiz in lesson frontend
     * 
     * Hooks into: learn-press/after-content-item-summary/lp_lesson at priority 9
     * This places the quiz after lesson content but before:
     * - Materials (priority 10)
     * - Complete button (priority 11)
     * - Finish course button (priority 15)
     */
    public static function render_quiz_in_lesson() {
        // Get current lesson from LearnPress global
        $lesson = LP_Global::course_item();
        
        if ( ! $lesson || ! is_a( $lesson, 'LP_Lesson' ) ) {
            return;
        }

        $lesson_id = $lesson->get_id();
        $quiz_id   = get_post_meta( $lesson_id, '_ppq_quiz_id', true );

        if ( empty( $quiz_id ) ) {
            return;
        }

        // Get course from LearnPress global
        $course    = learn_press_get_course();
        $course_id = $course ? $course->get_id() : 0;

        // Check if user is enrolled (if course exists)
        if ( $course_id && ! self::is_user_enrolled( $course_id ) ) {
            return;
        }

        // Render the quiz
        $quiz_html = PressPrimer_Quiz_Shortcodes::render_quiz( array(
            'id'     => $quiz_id,
            'source' => 'learnpress',
        ) );

        // Output with wrapper
        printf(
            '<div class="ppq-learnpress-quiz" data-lesson-id="%d" data-course-id="%d">%s</div>',
            esc_attr( $lesson_id ),
            esc_attr( $course_id ),
            $quiz_html
        );
    }

    /**
     * Handle quiz completion for LearnPress
     *
     * @param PressPrimer_Quiz_Attempt $attempt Quiz attempt.
     * @param array                    $data    Submission data.
     */
    public static function handle_quiz_completion( $attempt, $data ) {
        // Find the lesson with this quiz
        $lesson_id = self::find_lesson_by_quiz( $attempt->quiz_id );
        if ( ! $lesson_id ) {
            return;
        }

        // Get course ID for this lesson
        $course_id = self::get_lesson_course_id( $lesson_id );
        if ( ! $course_id ) {
            return;
        }

        // Check if pass is required
        $require_pass = get_post_meta( $lesson_id, '_ppq_require_pass', true );
        if ( $require_pass !== '1' ) {
            // No pass required, mark as complete
            self::complete_lesson( $lesson_id, $course_id, $attempt->user_id );
            return;
        }

        // Check if passed
        $min_score = get_post_meta( $lesson_id, '_ppq_min_score', true );
        if ( empty( $min_score ) ) {
            // Use quiz default
            $passed = $attempt->passed;
        } else {
            // Use lesson override
            $passed = $attempt->score_percent >= floatval( $min_score );
        }

        if ( $passed ) {
            self::complete_lesson( $lesson_id, $course_id, $attempt->user_id );
        }
    }

    /**
     * Mark a LearnPress lesson as complete
     *
     * Uses LearnPress API: $user->complete_lesson($lesson_id, $course_id)
     * This method is defined in inc/user/abstract-lp-user.php:760
     *
     * @param int $lesson_id Lesson post ID.
     * @param int $course_id Course post ID.
     * @param int $user_id   User ID.
     */
    private static function complete_lesson( $lesson_id, $course_id, $user_id ) {
        if ( ! $user_id || ! $course_id ) {
            return;
        }

        // Use LearnPress API to complete the lesson
        if ( function_exists( 'learn_press_get_user' ) ) {
            $user = learn_press_get_user( $user_id );
            if ( $user && method_exists( $user, 'complete_lesson' ) ) {
                $result = $user->complete_lesson( $lesson_id, $course_id );
                
                // Log any errors for debugging
                if ( is_wp_error( $result ) ) {
                    error_log( 'PPQ LearnPress: Failed to complete lesson - ' . $result->get_error_message() );
                }
            }
        }
    }

    /**
     * Get course ID for a lesson
     * 
     * LearnPress stores lesson-course relationships in:
     * - {prefix}learnpress_sections (section_id, section_course_id)
     * - {prefix}learnpress_section_items (section_id, item_id)
     *
     * @param int $lesson_id Lesson post ID.
     * @return int|null Course post ID or null.
     */
    private static function get_lesson_course_id( $lesson_id ) {
        global $wpdb;
        
        $course_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT s.section_course_id 
             FROM {$wpdb->prefix}learnpress_section_items AS si
             INNER JOIN {$wpdb->prefix}learnpress_sections AS s ON si.section_id = s.section_id
             WHERE si.item_id = %d
             LIMIT 1",
            $lesson_id
        ) );

        return $course_id ? absint( $course_id ) : null;
    }

    /**
     * Check if user is enrolled in a course
     * 
     * Uses LearnPress API: $user->has_enrolled_course($course_id)
     * Defined in inc/user/class-lp-user.php:208
     *
     * @param int $course_id Course ID.
     * @return bool
     */
    private static function is_user_enrolled( $course_id ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }

        if ( function_exists( 'learn_press_get_user' ) ) {
            $user = learn_press_get_user( $user_id );
            if ( $user && method_exists( $user, 'has_enrolled_course' ) ) {
                return (bool) $user->has_enrolled_course( $course_id );
            }
        }

        return false;
    }

    /**
     * Find lesson that has a specific quiz attached
     *
     * @param int $quiz_id PPQ Quiz ID.
     * @return int|null Lesson post ID or null.
     */
    private static function find_lesson_by_quiz( $quiz_id ) {
        global $wpdb;

        $lesson_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_ppq_quiz_id' AND meta_value = %s
             LIMIT 1",
            $quiz_id
        ) );

        return $lesson_id ? absint( $lesson_id ) : null;
    }

    /**
     * Enqueue frontend styles
     */
    public static function enqueue_styles() {
        // Check if we're in a LearnPress lesson context
        if ( ! class_exists( 'LP_Global' ) ) {
            return;
        }

        $item = LP_Global::course_item();
        if ( ! $item || ! is_a( $item, 'LP_Lesson' ) ) {
            return;
        }

        // Add inline styles for LearnPress context
        wp_add_inline_style( 'ppq-quiz', '
            .ppq-learnpress-quiz {
                margin-top: 2rem;
                padding-top: 2rem;
                border-top: 1px solid #e0e0e0;
            }
        ' );
    }
}
```

### Post Meta Fields

| Meta Key | Type | Description |
|----------|------|-------------|
| `_ppq_quiz_id` | int | PPQ Quiz ID to embed |
| `_ppq_require_pass` | string | "1" if pass required for completion |
| `_ppq_min_score` | int | Override minimum score (0-100) |

### LearnPress API Reference (v4.3.2.2)

| Function/Method | Location | Purpose |
|-----------------|----------|---------|
| `LP_Global::course_item()` | `inc/class-lp-global.php:67` | Get current lesson object in frontend |
| `learn_press_get_course()` | `inc/lp-core-functions.php` | Get current course object |
| `learn_press_get_user($id)` | `inc/user/lp-user-functions.php:106` | Get LP_User object |
| `$user->has_enrolled_course($id)` | `inc/user/class-lp-user.php:208` | Check enrollment status |
| `$user->complete_lesson($l, $c)` | `inc/user/abstract-lp-user.php:760` | Mark lesson complete |

### LearnPress Database Tables

| Table | Key Columns | Purpose |
|-------|-------------|---------|
| `{prefix}learnpress_sections` | `section_id`, `section_course_id` | Links sections to courses |
| `{prefix}learnpress_section_items` | `section_id`, `item_id` | Links lessons to sections |

### Enrollment Check Flow

```
User views lesson (LP popup or page)
    ↓
learn-press/after-content-item-summary/lp_lesson fires
    ↓
Get lesson via LP_Global::course_item()
    ↓
Check if quiz attached (get_post_meta _ppq_quiz_id)
    ↓
Get course via learn_press_get_course()
    ↓
Check enrollment via $user->has_enrolled_course()
    ↓ (enrolled)
Render quiz
    ↓ (not enrolled)
Return without rendering
```

### Completion Flow

```
User submits quiz attempt
    ↓
pressprimer_quiz_attempt_submitted hook fires
    ↓
Find lesson by quiz ID (query postmeta)
    ↓
Get course ID (query LP section tables)
    ↓
Check if require_pass enabled
    ↓ (not required)
$user->complete_lesson($lesson_id, $course_id)
    ↓ (required)
Check score against min_score or quiz default
    ↓ (passed)
$user->complete_lesson($lesson_id, $course_id)
    ↓ (failed)
Do nothing (user can retry)
```

## Testing Checklist

### Setup & Admin
- [ ] Integration only loads when LearnPress 4.0+ is active
- [ ] Settings appear in LP's "Lesson Settings" meta box (not separate box)
- [ ] Quiz selector shows all published PPQ quizzes
- [ ] Settings save correctly (quiz ID, require pass, min score)
- [ ] Settings persist after lesson save

### Frontend Display
- [ ] Quiz renders after lesson content
- [ ] Quiz renders before materials section (priority 9 vs 10)
- [ ] Quiz renders before complete button (priority 9 vs 11)
- [ ] Quiz works in LP popup/modal lesson display
- [ ] Quiz works on dedicated lesson pages
- [ ] Quiz hidden for non-enrolled users
- [ ] Quiz hidden for logged-out users (if enrollment required)

### Completion Tracking
- [ ] Lesson completes when quiz submitted (require_pass = false)
- [ ] Lesson completes only on pass (require_pass = true)
- [ ] Custom min_score overrides quiz default
- [ ] Empty min_score uses quiz pass_percent
- [ ] LP course progress updates after lesson completion
- [ ] LP "Complete Lesson" button still works independently

### Edge Cases
- [ ] Multiple lessons can embed same quiz
- [ ] Changing quiz on lesson doesn't affect old attempts
- [ ] Deleting quiz doesn't break lesson display
- [ ] Preview lessons (non-enrolled access) work correctly

## Dependencies

- LearnPress 4.0.0+ (verified with 4.3.2.2)
- Addon Compatibility Hooks (for integration detection)

## Files Changed

**New:**
- `includes/integrations/class-ppq-learnpress.php`

**Modified:**
- `includes/class-ppq-plugin.php` — Load LearnPress integration
- `includes/admin/class-ppq-admin-settings.php` — Add to integrations list
