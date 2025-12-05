# Hooks Reference

PressPrimer Quiz provides actions and filters for extensibility. All hooks are prefixed with `ppq_`.

## Actions

Actions allow external code to execute at specific points in the plugin's lifecycle.

### Quiz Lifecycle

#### `ppq_quiz_created`
Fires when a new quiz is created.

```php
do_action( 'pressprimer_quiz_quiz_created', int $quiz_id, array $data );
```

**Parameters:**
- `$quiz_id` (int) - The ID of the newly created quiz
- `$data` (array) - The data used to create the quiz

---

#### `ppq_quiz_updated`
Fires when a quiz is updated.

```php
do_action( 'pressprimer_quiz_quiz_updated', int $quiz_id, array $changes );
```

**Parameters:**
- `$quiz_id` (int) - The quiz ID
- `$changes` (array) - Array of changed fields

---

#### `ppq_quiz_published`
Fires when a quiz status changes to published.

```php
do_action( 'pressprimer_quiz_quiz_published', int $quiz_id );
```

---

#### `ppq_quiz_deleted`
Fires when a quiz is deleted (soft delete).

```php
do_action( 'pressprimer_quiz_quiz_deleted', int $quiz_id );
```

---

### Attempt Lifecycle

#### `ppq_attempt_started`
Fires when a user starts a quiz attempt.

```php
do_action( 'pressprimer_quiz_attempt_started', int $attempt_id, int $quiz_id, int $user_id );
```

**Parameters:**
- `$attempt_id` (int) - The attempt ID
- `$quiz_id` (int) - The quiz being attempted
- `$user_id` (int) - The user ID (0 for guests)

**Example:**
```php
add_action( 'pressprimer_quiz_attempt_started', function( $attempt_id, $quiz_id, $user_id ) {
    // Log attempt start
    error_log( "User $user_id started quiz $quiz_id (attempt $attempt_id)" );
}, 10, 3 );
```

---

#### `ppq_answer_saved`
Fires when an answer is saved (not submitted).

```php
do_action( 'pressprimer_quiz_answer_saved', int $attempt_id, int $question_id, array $answer );
```

**Parameters:**
- `$attempt_id` (int) - The attempt ID
- `$question_id` (int) - The question ID
- `$answer` (array) - The selected answer IDs

---

#### `ppq_attempt_submitted`
Fires when a quiz attempt is submitted.

```php
do_action( 'pressprimer_quiz_attempt_submitted', int $attempt_id, bool $passed );
```

**Parameters:**
- `$attempt_id` (int) - The attempt ID
- `$passed` (bool) - Whether the user passed

**Example:**
```php
add_action( 'pressprimer_quiz_attempt_submitted', function( $attempt_id, $passed ) {
    $attempt = PressPrimer_Quiz_Attempt::get( $attempt_id );
    
    if ( $passed ) {
        // Award badge, send email, etc.
    }
}, 10, 2 );
```

---

#### `ppq_quiz_passed`
Fires specifically when a user passes a quiz.

```php
do_action( 'pressprimer_quiz_quiz_passed', int $quiz_id, int $user_id, float $score_percent, int $attempt_id );
```

**Parameters:**
- `$quiz_id` (int) - The quiz ID
- `$user_id` (int) - The user ID
- `$score_percent` (float) - The score percentage
- `$attempt_id` (int) - The attempt ID

---

#### `ppq_quiz_failed`
Fires specifically when a user fails a quiz.

```php
do_action( 'pressprimer_quiz_quiz_failed', int $quiz_id, int $user_id, float $score_percent, int $attempt_id );
```

---

#### `ppq_attempt_abandoned`
Fires when an attempt is marked as abandoned (timed out without submission).

```php
do_action( 'pressprimer_quiz_attempt_abandoned', int $attempt_id );
```

---

### Question Lifecycle

#### `ppq_question_created`
Fires when a new question is created.

```php
do_action( 'pressprimer_quiz_question_created', int $question_id, array $data );
```

---

#### `ppq_question_revised`
Fires when a question revision is created (question content updated).

```php
do_action( 'pressprimer_quiz_question_revised', int $question_id, int $revision_id );
```

---

#### `ppq_question_deleted`
Fires when a question is deleted.

```php
do_action( 'pressprimer_quiz_question_deleted', int $question_id );
```

---

### Group & Assignment

#### `ppq_group_created`
Fires when a group is created.

```php
do_action( 'pressprimer_quiz_group_created', int $group_id );
```

---

#### `ppq_member_added_to_group`
Fires when a user is added to a group.

```php
do_action( 'pressprimer_quiz_member_added_to_group', int $group_id, int $user_id, string $role );
```

**Parameters:**
- `$group_id` (int) - The group ID
- `$user_id` (int) - The user ID
- `$role` (string) - 'teacher' or 'student'

---

#### `ppq_quiz_assigned`
Fires when a quiz is assigned to a group or user.

```php
do_action( 'pressprimer_quiz_quiz_assigned', int $quiz_id, string $assignee_type, int $assignee_id, int $assigned_by );
```

**Parameters:**
- `$quiz_id` (int) - The quiz ID
- `$assignee_type` (string) - 'group' or 'user'
- `$assignee_id` (int) - Group or user ID
- `$assigned_by` (int) - User who made the assignment

---

### AI Generation

#### `ppq_ai_generation_started`
Fires when AI question generation begins.

```php
do_action( 'pressprimer_quiz_ai_generation_started', int $user_id, array $params );
```

---

#### `ppq_ai_generation_completed`
Fires when AI question generation completes.

```php
do_action( 'pressprimer_quiz_ai_generation_completed', int $user_id, int $question_count, array $question_ids );
```

---

### Admin

#### `ppq_settings_saved`
Fires when plugin settings are saved.

```php
do_action( 'pressprimer_quiz_settings_saved', array $settings );
```

---

## Filters

Filters allow modification of data at specific points.

### Scoring

#### `ppq_scoring_mc`
Filter the score for a multiple choice question.

```php
$score = apply_filters( 'pressprimer_quiz_scoring_mc', float $score, PPQ_Question $question, array $selected_answers );
```

**Example:**
```php
add_filter( 'pressprimer_quiz_scoring_mc', function( $score, $question, $selected ) {
    // Custom scoring logic
    return $score;
}, 10, 3 );
```

---

#### `ppq_scoring_ma`
Filter the score for a multiple answer question.

```php
$score = apply_filters( 'pressprimer_quiz_scoring_ma', float $score, PPQ_Question $question, array $selected_answers, array $scoring_details );
```

**Parameters:**
- `$score` (float) - Calculated score
- `$question` (PPQ_Question) - The question object
- `$selected_answers` (array) - Selected answer IDs
- `$scoring_details` (array) - Details about partial credit calculation

---

#### `ppq_scoring_tf`
Filter the score for a true/false question.

```php
$score = apply_filters( 'pressprimer_quiz_scoring_tf', float $score, PPQ_Question $question, array $selected_answers );
```

---

#### `ppq_attempt_score`
Filter the final attempt score before saving.

```php
$score_data = apply_filters( 'pressprimer_quiz_attempt_score', array $score_data, int $attempt_id );
```

**Score Data Structure:**
```php
[
    'score_points' => 85.0,
    'max_points' => 100.0,
    'score_percent' => 85.0,
    'correct_count' => 17,
    'total_count' => 20,
]
```

---

### Quiz Generation

#### `ppq_quiz_questions`
Filter the questions selected for a quiz attempt.

```php
$question_ids = apply_filters( 'pressprimer_quiz_quiz_questions', array $question_ids, int $quiz_id, int $user_id );
```

**Example:**
```php
add_filter( 'pressprimer_quiz_quiz_questions', function( $question_ids, $quiz_id, $user_id ) {
    // Customize question selection
    // E.g., exclude questions user answered correctly before
    return $question_ids;
}, 10, 3 );
```

---

#### `ppq_dynamic_quiz_rules`
Filter the rules used for dynamic quiz generation.

```php
$rules = apply_filters( 'pressprimer_quiz_dynamic_quiz_rules', array $rules, int $quiz_id );
```

---

### Results & Display

#### `ppq_result_payload`
Filter the results data sent to the frontend.

```php
$results = apply_filters( 'pressprimer_quiz_result_payload', array $results, int $attempt_id );
```

---

#### `ppq_result_message`
Filter the result message shown to the user.

```php
$message = apply_filters( 'pressprimer_quiz_result_message', string $message, int $attempt_id, bool $passed );
```

---

#### `ppq_quiz_landing_data`
Filter the data shown on the quiz landing page.

```php
$data = apply_filters( 'pressprimer_quiz_quiz_landing_data', array $data, int $quiz_id, int $user_id );
```

---

### Theme & Styling

#### `ppq_theme_tokens`
Filter CSS custom properties for a theme.

```php
$tokens = apply_filters( 'pressprimer_quiz_theme_tokens', array $tokens, string $theme_name );
```

**Example:**
```php
add_filter( 'pressprimer_quiz_theme_tokens', function( $tokens, $theme ) {
    $tokens['--ppq-primary-color'] = '#007bff';
    $tokens['--ppq-success-color'] = '#28a745';
    return $tokens;
}, 10, 2 );
```

---

#### `ppq_available_themes`
Filter the list of available themes.

```php
$themes = apply_filters( 'pressprimer_quiz_available_themes', array $themes );
```

**Default Themes:**
```php
[
    'default' => 'Default',
    'modern' => 'Modern',
    'minimal' => 'Minimal',
]
```

---

### Question Rendering

#### `ppq_question_stem`
Filter the question stem before display.

```php
$stem = apply_filters( 'pressprimer_quiz_question_stem', string $stem, PPQ_Question $question );
```

---

#### `ppq_answer_option`
Filter an answer option before display.

```php
$option = apply_filters( 'pressprimer_quiz_answer_option', array $option, PPQ_Question $question );
```

---

### AI Generation

#### `ppq_ai_prompt`
Filter the prompt sent to the AI API.

```php
$prompt = apply_filters( 'pressprimer_quiz_ai_prompt', string $prompt, array $params );
```

---

#### `ppq_ai_response`
Filter the parsed AI response before creating questions.

```php
$questions = apply_filters( 'pressprimer_quiz_ai_response', array $questions, string $raw_response );
```

---

### Permissions

#### `ppq_user_can_take_quiz`
Filter whether a user can take a specific quiz.

```php
$can_take = apply_filters( 'pressprimer_quiz_user_can_take_quiz', bool $can_take, int $quiz_id, int $user_id );
```

**Example:**
```php
add_filter( 'pressprimer_quiz_user_can_take_quiz', function( $can_take, $quiz_id, $user_id ) {
    // Add custom access control
    if ( ! user_has_required_prerequisite( $user_id ) ) {
        return false;
    }
    return $can_take;
}, 10, 3 );
```

---

#### `ppq_user_can_view_results`
Filter whether a user can view attempt results.

```php
$can_view = apply_filters( 'pressprimer_quiz_user_can_view_results', bool $can_view, int $attempt_id, int $user_id );
```

---

### Rate Limiting

#### `ppq_rate_limit_attempts`
Filter the rate limit for quiz attempts.

```php
$limit = apply_filters( 'pressprimer_quiz_rate_limit_attempts', int $limit, int $user_id );
```

Default: 10 attempts per 10 minutes

---

#### `ppq_rate_limit_ai`
Filter the rate limit for AI generation requests.

```php
$limit = apply_filters( 'pressprimer_quiz_rate_limit_ai', int $limit, int $user_id );
```

Default: 20 requests per hour

---

### Email

#### `ppq_email_headers`
Filter email headers.

```php
$headers = apply_filters( 'pressprimer_quiz_email_headers', array $headers, string $email_type );
```

---

#### `ppq_email_content`
Filter email content before sending.

```php
$content = apply_filters( 'pressprimer_quiz_email_content', string $content, string $email_type, array $data );
```

---

## LMS Integration Hooks

### LearnDash

#### `ppq_learndash_quiz_passed`
Fires when a PressPrimer quiz linked to LearnDash is passed.

```php
do_action( 'pressprimer_quiz_learndash_quiz_passed', int $lesson_id, int $quiz_id, int $user_id, int $attempt_id );
```

---

#### `ppq_learndash_should_complete_lesson`
Filter whether completing the quiz should mark the lesson complete.

```php
$should_complete = apply_filters( 'pressprimer_quiz_learndash_should_complete_lesson', bool $should_complete, int $lesson_id, int $attempt_id );
```

---

### TutorLMS

#### `ppq_tutor_quiz_completed`
Fires when a PressPrimer quiz linked to TutorLMS is completed.

```php
do_action( 'pressprimer_quiz_tutor_quiz_completed', int $lesson_id, int $quiz_id, int $user_id, bool $passed );
```

---

### LifterLMS

#### `ppq_lifter_quiz_completed`
Fires when a PressPrimer quiz linked to LifterLMS is completed.

```php
do_action( 'pressprimer_quiz_lifter_quiz_completed', int $lesson_id, int $quiz_id, int $user_id, bool $passed );
```

---

## Usage Examples

### Custom Scoring

```php
/**
 * Add time bonus to scores
 */
add_filter( 'pressprimer_quiz_attempt_score', function( $score_data, $attempt_id ) {
    $attempt = PressPrimer_Quiz_Attempt::get( $attempt_id );
    $quiz = PressPrimer_Quiz_Quiz::get( $attempt->quiz_id );
    
    // If finished in under half the time limit, add 5% bonus
    if ( $quiz->time_limit_seconds ) {
        $elapsed = $attempt->elapsed_ms / 1000;
        if ( $elapsed < $quiz->time_limit_seconds / 2 ) {
            $score_data['score_percent'] = min( 100, $score_data['score_percent'] + 5 );
        }
    }
    
    return $score_data;
}, 10, 2 );
```

### Integration with External System

```php
/**
 * Send results to external LRS
 */
add_action( 'pressprimer_quiz_attempt_submitted', function( $attempt_id, $passed ) {
    $attempt = PressPrimer_Quiz_Attempt::get( $attempt_id );
    $quiz = PressPrimer_Quiz_Quiz::get( $attempt->quiz_id );
    $user = get_user_by( 'id', $attempt->user_id );
    
    // Build xAPI statement
    $statement = [
        'actor' => [
            'mbox' => 'mailto:' . $user->user_email,
        ],
        'verb' => [
            'id' => 'http://adlnet.gov/expapi/verbs/completed',
        ],
        'object' => [
            'id' => home_url( '/quiz/' . $quiz->uuid ),
            'definition' => [
                'name' => [ 'en-US' => $quiz->title ],
            ],
        ],
        'result' => [
            'score' => [
                'scaled' => $attempt->score_percent / 100,
            ],
            'success' => $passed,
        ],
    ];
    
    // Send to LRS
    wp_remote_post( 'https://lrs.example.com/statements', [
        'body' => wp_json_encode( $statement ),
        'headers' => [ 'Content-Type' => 'application/json' ],
    ] );
}, 10, 2 );
```

### Custom Access Control

```php
/**
 * Require course enrollment to take quiz
 */
add_filter( 'pressprimer_quiz_user_can_take_quiz', function( $can_take, $quiz_id, $user_id ) {
    // Get course associated with quiz
    $course_id = get_post_meta( get_quiz_post_id( $quiz_id ), 'ppq_required_course', true );
    
    if ( ! $course_id ) {
        return $can_take;
    }
    
    // Check LearnDash enrollment
    if ( function_exists( 'sfwd_lms_has_access' ) ) {
        return sfwd_lms_has_access( $course_id, $user_id );
    }
    
    return $can_take;
}, 10, 3 );
```

