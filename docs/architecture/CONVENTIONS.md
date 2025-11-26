# Coding Conventions

## Terminology

Quizzes in the back end should be called "PPQ Quiz", "PPQ Quizzes", or "PressPrimer Quizzes" in the UI to prevent confusion with quizzes in other plugins. In the front end only, the terms "Quiz" and "Quizzes" can be used.

## Naming Conventions

### Global Prefixes

| Type | Prefix | Example |
|------|--------|---------|
| Plugin slug | `pressprimer-quiz` | `pressprimer-quiz/pressprimer-quiz.php` |
| Text domain | `pressprimer-quiz` | `__( 'Quiz', 'pressprimer-quiz' )` |
| Database tables | `wp_ppq_` | `wp_ppq_questions` |
| PHP functions | `ppq_` | `ppq_get_question()` |
| PHP classes | `PPQ_` | `class PPQ_Question` |
| CSS classes | `ppq-` | `.ppq-quiz-container` |
| JavaScript namespace | `PPQ` | `PPQ.Quiz.submit()` |
| Shortcodes | `ppq_` | `[ppq_quiz]` |
| REST API namespace | `ppq/v1` | `/wp-json/ppq/v1/quizzes` |
| Options | `ppq_` | `get_option( 'ppq_settings' )` |
| User meta | `ppq_` | `get_user_meta( $id, 'ppq_openai_key' )` |
| Post meta | `ppq_` | `get_post_meta( $id, 'ppq_quiz_id' )` |
| Transients | `ppq_` | `get_transient( 'ppq_report_1' )` |
| Capabilities | `ppq_` | `ppq_manage_all` |
| Nonces | `ppq_` | `wp_nonce_field( 'ppq_save_quiz' )` |
| AJAX actions | `ppq_` | `add_action( 'wp_ajax_ppq_save_quiz' )` |
| Hooks (actions/filters) | `ppq_` | `do_action( 'ppq_attempt_started' )` |
| Block names | `pressprimer-quiz/` | `pressprimer-quiz/quiz` |

### Class Naming

**Models:**
```php
class PPQ_Question { }
class PPQ_Quiz { }
class PPQ_Attempt { }
class PPQ_Bank { }
class PPQ_Group { }
```

**Controllers/Handlers:**
```php
class PPQ_Quiz_Controller { }
class PPQ_Admin_Controller { }
class PPQ_AJAX_Handler { }
class PPQ_REST_Controller { }
```

**Services:**
```php
class PPQ_Scoring_Service { }
class PPQ_AI_Service { }
class PPQ_Email_Service { }
```

**Admin Pages:**
```php
class PPQ_Admin_Questions_Page { }
class PPQ_Admin_Settings_Page { }
```

### Function Naming

**Getters:**
```php
ppq_get_question( $id )
ppq_get_quiz( $id )
ppq_get_user_attempts( $user_id )
ppq_get_quiz_by_uuid( $uuid )
```

**Boolean checks:**
```php
ppq_is_quiz_published( $quiz_id )
ppq_has_user_passed( $user_id, $quiz_id )
ppq_can_user_retake( $user_id, $quiz_id )
```

**Actions:**
```php
ppq_create_question( $data )
ppq_update_quiz( $id, $data )
ppq_submit_attempt( $attempt_id )
ppq_score_response( $question, $answer )
```

**Rendering:**
```php
ppq_render_quiz( $quiz_id, $args )
ppq_render_results( $attempt_id )
ppq_render_admin_table( $items )
```

### Database Field Naming

- Use `snake_case` for all fields
- Use `_id` suffix for foreign keys: `user_id`, `quiz_id`
- Use `_at` suffix for timestamps: `created_at`, `updated_at`, `deleted_at`
- Use `_json` suffix for JSON fields: `answers_json`, `settings_json`
- Use `_count` suffix for counts: `question_count`, `member_count`
- Boolean fields: `is_correct`, `allow_skip`, `enable_confidence`

### CSS Class Naming

Use BEM-lite methodology:

```css
/* Block */
.ppq-quiz { }

/* Element (double underscore) */
.ppq-quiz__header { }
.ppq-quiz__content { }
.ppq-quiz__footer { }

/* Modifier (double dash) */
.ppq-quiz--tutorial { }
.ppq-quiz--timed { }
.ppq-quiz__button--primary { }
.ppq-quiz__button--disabled { }

/* State (is- prefix) */
.ppq-quiz.is-loading { }
.ppq-question.is-answered { }
.ppq-option.is-selected { }
```

### JavaScript Naming

```javascript
// Namespace
const PPQ = window.PPQ || {};

// Modules
PPQ.Quiz = { };
PPQ.Questions = { };
PPQ.Timer = { };
PPQ.Results = { };

// Public methods use camelCase
PPQ.Quiz.start();
PPQ.Quiz.submit();
PPQ.Timer.pause();

// Private methods prefixed with underscore
PPQ.Quiz._validateAnswers();
PPQ.Quiz._calculateScore();

// Events use colon-separated names
'ppq:quiz:started'
'ppq:answer:selected'
'ppq:timer:warning'
```

---

## WordPress Coding Standards

### PHP Standards

Follow WordPress PHP Coding Standards: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/

**Key Requirements:**

1. **Indentation:** Use tabs, not spaces

2. **Brace Style:**
```php
// Correct
if ( condition ) {
    action();
}

// Not this
if ( condition )
{
    action();
}
```

3. **Space Inside Parentheses:**
```php
// Correct
if ( $condition ) {
    function_call( $arg1, $arg2 );
}

// Not this
if ($condition) {
    function_call($arg1, $arg2);
}
```

4. **Yoda Conditions:**
```php
// Correct
if ( 'published' === $status ) { }
if ( true === $is_valid ) { }

// Not this
if ( $status === 'published' ) { }
```

5. **Array Syntax:**
```php
// Short syntax preferred
$array = [
    'key1' => 'value1',
    'key2' => 'value2',
];

// Not this
$array = array(
    'key1' => 'value1',
    'key2' => 'value2',
);
```


### Documentation Standards

**File Headers:**
```php
<?php
/**
 * Question model
 *
 * @package PressPrimer_Quiz
 * @subpackage Models
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

**Class Documentation:**
```php
/**
 * Question model class
 *
 * Handles question data, revisions, and scoring.
 *
 * @since 1.0.0
 */
class PPQ_Question {
```

**Method Documentation:**
```php
/**
 * Get question by ID
 *
 * @since 1.0.0
 *
 * @param int $id Question ID.
 * @return PPQ_Question|null Question object or null if not found.
 */
public function get( int $id ): ?PPQ_Question {
```

**Inline Comments:**
```php
// Calculate partial credit for multiple answer questions.
$correct_selected = count( array_intersect( $selected, $correct ) );
$incorrect_selected = count( array_diff( $selected, $correct ) );

// Formula: (correct - incorrect) / total_correct, floored at 0
$score = max( 0, ( $correct_selected - $incorrect_selected ) / count( $correct ) );
```

---

## Security Standards

### Input Sanitization

Always sanitize based on expected data type:

```php
// Text fields
$title = sanitize_text_field( $_POST['title'] );

// Textarea (preserves newlines)
$description = sanitize_textarea_field( $_POST['description'] );

// HTML content (use wp_kses for specific allowed tags)
$stem = wp_kses_post( $_POST['stem'] );

// Integer
$quiz_id = absint( $_POST['quiz_id'] );

// Email
$email = sanitize_email( $_POST['email'] );

// URL
$url = esc_url_raw( $_POST['url'] );

// Array of integers
$ids = array_map( 'absint', (array) $_POST['ids'] );
```
Answer text also needs to use wp_kses_post() for full HTML support.

### Output Escaping

Always escape output based on context:

```php
// HTML context
echo esc_html( $title );

// HTML attributes
echo '<input value="' . esc_attr( $value ) . '">';

// URLs
echo '<a href="' . esc_url( $url ) . '">';

// JavaScript in HTML
echo '<script>var data = ' . wp_json_encode( $data ) . ';</script>';

// Textarea content
echo '<textarea>' . esc_textarea( $content ) . '</textarea>';

// Translated strings
echo esc_html__( 'Submit', 'pressprimer-quiz' );

// With placeholders
printf(
    esc_html__( 'Question %d of %d', 'pressprimer-quiz' ),
    absint( $current ),
    absint( $total )
);
```

### Database Queries

Always use prepared statements:

```php
global $wpdb;

// Single value
$question = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ppq_questions WHERE id = %d",
    $id
) );

// Multiple values
$questions = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ppq_questions 
     WHERE author_id = %d AND status = %s",
    $author_id,
    $status
) );

// IN clause
$ids = [ 1, 2, 3, 4, 5 ];
$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
$questions = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ppq_questions WHERE id IN ($placeholders)",
    ...$ids
) );
```

### Nonce Verification

```php
// In form
wp_nonce_field( 'ppq_save_quiz', 'ppq_nonce' );

// Verification
if ( ! isset( $_POST['ppq_nonce'] ) || 
     ! wp_verify_nonce( $_POST['ppq_nonce'], 'ppq_save_quiz' ) ) {
    wp_die( __( 'Security check failed.', 'pressprimer-quiz' ) );
}

// AJAX verification
check_ajax_referer( 'ppq_submit_quiz', 'nonce' );
```

### Capability Checks

```php
// Before any admin action
if ( ! current_user_can( 'ppq_manage_all' ) ) {
    wp_die( __( 'Permission denied.', 'pressprimer-quiz' ) );
}

// For owned content
$quiz = ppq_get_quiz( $quiz_id );
if ( ! current_user_can( 'ppq_manage_all' ) && 
     $quiz->owner_id !== get_current_user_id() ) {
    wp_die( __( 'Permission denied.', 'pressprimer-quiz' ) );
}
```

---

## Internationalization Standards

### Translatable Strings

```php
// Simple string
__( 'Quiz Results', 'pressprimer-quiz' )

// Echo directly
_e( 'Submit Quiz', 'pressprimer-quiz' );

// With escape
esc_html__( 'Start Quiz', 'pressprimer-quiz' )
esc_html_e( 'Continue', 'pressprimer-quiz' );
esc_attr__( 'Enter your answer', 'pressprimer-quiz' )

// With placeholders (use sprintf)
sprintf(
    __( 'Question %1$d of %2$d', 'pressprimer-quiz' ),
    $current,
    $total
)

// Plural forms
sprintf(
    _n(
        '%d question',
        '%d questions',
        $count,
        'pressprimer-quiz'
    ),
    $count
)

// Context for translators
_x( 'Post', 'verb', 'pressprimer-quiz' )
_x( 'Post', 'noun', 'pressprimer-quiz' )
```

### Translator Comments

```php
// translators: %s is the quiz title
printf( __( 'Results for: %s', 'pressprimer-quiz' ), $quiz->title );

// translators: 1: score percentage, 2: passing percentage
printf(
    __( 'You scored %1$d%% (passing is %2$d%%)', 'pressprimer-quiz' ),
    $score,
    $passing
);
```

### JavaScript Translations

```php
// Register script translations
wp_set_script_translations( 
    'ppq-quiz-script', 
    'pressprimer-quiz',
    PPQ_PLUGIN_PATH . 'languages'
);
```

```javascript
// In JavaScript
const { __ } = wp.i18n;

const message = __( 'Quiz submitted!', 'pressprimer-quiz' );
```

---

## File Organization

### Directory Structure

```
pressprimer-quiz/
├── pressprimer-quiz.php      # Main plugin file
├── uninstall.php             # Cleanup on uninstall
├── readme.txt                # WordPress.org readme
│
├── includes/
│   ├── class-ppq-activator.php
│   ├── class-ppq-deactivator.php
│   ├── class-ppq-loader.php
│   │
│   ├── models/
│   │   ├── class-ppq-question.php
│   │   ├── class-ppq-quiz.php
│   │   ├── class-ppq-attempt.php
│   │   ├── class-ppq-bank.php
│   │   └── class-ppq-group.php
│   │
│   ├── admin/
│   │   ├── class-ppq-admin.php
│   │   ├── class-ppq-admin-questions.php
│   │   ├── class-ppq-admin-quizzes.php
│   │   ├── class-ppq-admin-reports.php
│   │   └── class-ppq-admin-settings.php
│   │
│   ├── frontend/
│   │   ├── class-ppq-frontend.php
│   │   ├── class-ppq-quiz-renderer.php
│   │   ├── class-ppq-results-renderer.php
│   │   └── class-ppq-shortcodes.php
│   │
│   ├── services/
│   │   ├── class-ppq-scoring-service.php
│   │   ├── class-ppq-ai-service.php
│   │   └── class-ppq-email-service.php
│   │
│   ├── integrations/
│   │   ├── class-ppq-learndash.php
│   │   ├── class-ppq-tutorlms.php
│   │   ├── class-ppq-lifterlms.php
│   │   └── class-ppq-automator.php
│   │
│   ├── database/
│   │   ├── class-ppq-schema.php
│   │   └── class-ppq-migrator.php
│   │
│   └── utilities/
│       ├── class-ppq-helpers.php
│       └── class-ppq-capabilities.php
│
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   ├── frontend.css
│   │   └── themes/
│   │       ├── default.css
│   │       ├── modern.css
│   │       └── minimal.css
│   │
│   ├── js/
│   │   ├── admin.js
│   │   ├── quiz.js
│   │   ├── question-builder.js
│   │   └── results.js
│   │
│   └── images/
│
├── blocks/
│   ├── quiz/
│   ├── my-attempts/
│   └── assigned-quizzes/
│
├── templates/
│   ├── quiz/
│   │   ├── landing.php
│   │   ├── question.php
│   │   └── results.php
│   └── emails/
│       ├── quiz-assigned.php
│       └── results.php
│
├── languages/
│   └── pressprimer-quiz.pot
│
└── tests/
```

### Autoloading

Use WordPress-style autoloading:

```php
// In main plugin file
spl_autoload_register( function( $class ) {
    // Only handle our classes
    if ( strpos( $class, 'PPQ_' ) !== 0 ) {
        return;
    }
    
    // Convert class name to file name
    $file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
    
    // Check in includes directory
    $path = PPQ_PLUGIN_PATH . 'includes/' . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
        return;
    }
    
    // Check in subdirectories
    $subdirs = [ 'models', 'admin', 'frontend', 'services', 'integrations', 'database', 'utilities' ];
    foreach ( $subdirs as $subdir ) {
        $path = PPQ_PLUGIN_PATH . 'includes/' . $subdir . '/' . $file;
        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }
    }
} );
```

---

## Error Handling

### WP_Error Usage

```php
function ppq_create_quiz( array $data ): int|WP_Error {
    // Validation
    if ( empty( $data['title'] ) ) {
        return new WP_Error(
            'ppq_missing_title',
            __( 'Quiz title is required.', 'pressprimer-quiz' )
        );
    }
    
    // Database operation
    $result = $wpdb->insert( /* ... */ );
    
    if ( false === $result ) {
        return new WP_Error(
            'ppq_db_error',
            __( 'Failed to create quiz.', 'pressprimer-quiz' ),
            [ 'db_error' => $wpdb->last_error ]
        );
    }
    
    return $wpdb->insert_id;
}

// Usage
$result = ppq_create_quiz( $data );
if ( is_wp_error( $result ) ) {
    // Handle error
    $error_message = $result->get_error_message();
}
```

### AJAX Error Responses

```php
function ppq_ajax_save_quiz() {
    check_ajax_referer( 'ppq_save_quiz', 'nonce' );
    
    if ( ! current_user_can( 'ppq_manage_own' ) ) {
        wp_send_json_error( [
            'code' => 'permission_denied',
            'message' => __( 'Permission denied.', 'pressprimer-quiz' )
        ], 403 );
    }
    
    $result = ppq_create_quiz( $_POST['data'] );
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [
            'code' => $result->get_error_code(),
            'message' => $result->get_error_message()
        ], 400 );
    }
    
    wp_send_json_success( [
        'quiz_id' => $result,
        'message' => __( 'Quiz saved.', 'pressprimer-quiz' )
    ] );
}
```

