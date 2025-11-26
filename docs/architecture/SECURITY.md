# Security Architecture

## Core Security Principle

**Never trust the client.** All quiz data—correct answers, scoring, validation—is handled server-side. The client should never have access to information that would allow cheating.

## Critical Security Rules

### 1. Answer Security

**The most important rule:** Correct answers must NEVER be exposed to the client.

```php
// WRONG - Exposes answers in page source
function ppq_render_question( $question ) {
    $html = '<div class="ppq-question" data-correct="' . $correct_answer . '">';
    // ...
}

// CORRECT - Client only knows answer IDs, not which are correct
function ppq_render_question( $question ) {
    $revision = ppq_get_question_revision( $question->current_revision_id );
    $answers = json_decode( $revision->answers_json, true );
    
    // Shuffle if enabled (must store order for grading)
    // Never include is_correct in output
    $html = '<div class="ppq-question">';
    foreach ( $answers as $answer ) {
        $html .= '<label class="ppq-option">';
        $html .= '<input type="radio" name="q_' . $question->id . '" value="' . esc_attr( $answer['id'] ) . '">';
        $html .= esc_html( $answer['text'] );
        $html .= '</label>';
    }
    // ...
}
```

### 2. Server-Side Validation

All scoring and correctness checks happen on the server after submission:

```php
function ppq_score_question( int $question_id, array $selected_answers ): array {
    global $wpdb;
    
    // Get question revision (never trust client for question data)
    $question = $wpdb->get_row( $wpdb->prepare(
        "SELECT q.*, qr.answers_json 
         FROM {$wpdb->prefix}ppq_questions q
         JOIN {$wpdb->prefix}ppq_question_revisions qr ON q.current_revision_id = qr.id
         WHERE q.id = %d",
        $question_id
    ) );
    
    if ( ! $question ) {
        return [ 'error' => 'Question not found' ];
    }
    
    // Parse answers from database (authoritative source)
    $answers = json_decode( $question->answers_json, true );
    $correct_ids = array_column( 
        array_filter( $answers, fn( $a ) => $a['is_correct'] ), 
        'id' 
    );
    
    // Compare selected to correct
    $is_correct = $selected_answers === $correct_ids;
    
    // Calculate score based on question type
    $score = ppq_calculate_score( $question->type, $correct_ids, $selected_answers, $question->max_points );
    
    return [
        'is_correct' => $is_correct,
        'score' => $score,
        'max_points' => $question->max_points,
    ];
}
```

### 3. Timing Enforcement

Time limits are enforced server-side, not just client-side:

```php
function ppq_validate_submission_timing( int $attempt_id ): bool {
    global $wpdb;
    
    $attempt = $wpdb->get_row( $wpdb->prepare(
        "SELECT a.*, q.time_limit_seconds 
         FROM {$wpdb->prefix}ppq_attempts a
         JOIN {$wpdb->prefix}ppq_quizzes q ON a.quiz_id = q.id
         WHERE a.id = %d",
        $attempt_id
    ) );
    
    // No time limit
    if ( ! $attempt->time_limit_seconds ) {
        return true;
    }
    
    // Calculate elapsed time
    $started = strtotime( $attempt->started_at );
    $now = time();
    $elapsed = $now - $started;
    
    // Allow 30 second grace period for network latency
    $grace_period = 30;
    
    return $elapsed <= ( $attempt->time_limit_seconds + $grace_period );
}
```

---

## Authentication & Authorization

### Capability System

Define custom capabilities for PressPrimer Quiz:

```php
// Capabilities
'ppq_manage_all'        // Admin: Full access to all features
'ppq_manage_own'        // Teacher: Manage own quizzes, banks, see own students
'ppq_view_results_all'  // Admin: View all results
'ppq_view_results_own'  // Teacher: View results for own students
'ppq_take_quiz'         // All: Take quizzes
'ppq_manage_settings'   // Admin: Plugin settings

// Role mapping
function ppq_setup_capabilities() {
    $admin = get_role( 'administrator' );
    $admin->add_cap( 'ppq_manage_all' );
    $admin->add_cap( 'ppq_manage_own' );
    $admin->add_cap( 'ppq_view_results_all' );
    $admin->add_cap( 'ppq_view_results_own' );
    $admin->add_cap( 'ppq_take_quiz' );
    $admin->add_cap( 'ppq_manage_settings' );
    
    // Create Teacher role
    add_role( 'ppq_teacher', __( 'PressPrimer Teacher', 'pressprimer-quiz' ), [
        'read' => true,
        'ppq_manage_own' => true,
        'ppq_view_results_own' => true,
        'ppq_take_quiz' => true,
    ] );
    
    // Subscriber can take quizzes
    $subscriber = get_role( 'subscriber' );
    $subscriber->add_cap( 'ppq_take_quiz' );
}
```

### Permission Checks

Always check permissions before any action:

```php
// Admin action
function ppq_admin_delete_question() {
    // Check nonce
    check_admin_referer( 'ppq_delete_question' );
    
    // Check capability
    if ( ! current_user_can( 'ppq_manage_all' ) && ! current_user_can( 'ppq_manage_own' ) ) {
        wp_die( __( 'Permission denied.', 'pressprimer-quiz' ) );
    }
    
    $question_id = absint( $_GET['question_id'] );
    $question = ppq_get_question( $question_id );
    
    // Check ownership if not admin
    if ( ! current_user_can( 'ppq_manage_all' ) ) {
        if ( $question->author_id !== get_current_user_id() ) {
            wp_die( __( 'You can only delete your own questions.', 'pressprimer-quiz' ) );
        }
    }
    
    // Proceed with deletion
    ppq_delete_question( $question_id );
}
```

### Teacher Access Restrictions

Teachers should only see their own content and their own students:

```php
function ppq_get_teacher_students( int $teacher_id ): array {
    global $wpdb;
    
    // Get groups where this user is a teacher
    $groups = $wpdb->get_col( $wpdb->prepare(
        "SELECT group_id FROM {$wpdb->prefix}ppq_group_members 
         WHERE user_id = %d AND role = 'teacher'",
        $teacher_id
    ) );
    
    if ( empty( $groups ) ) {
        return [];
    }
    
    // Get students in those groups
    $placeholders = implode( ',', array_fill( 0, count( $groups ), '%d' ) );
    $students = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT user_id FROM {$wpdb->prefix}ppq_group_members 
         WHERE group_id IN ($placeholders) AND role = 'student'",
        ...$groups
    ) );
    
    return $students;
}

function ppq_can_teacher_view_attempt( int $teacher_id, int $attempt_id ): bool {
    $attempt = ppq_get_attempt( $attempt_id );
    
    // Admin can view all
    if ( user_can( $teacher_id, 'ppq_view_results_all' ) ) {
        return true;
    }
    
    // Check if teacher owns the quiz
    $quiz = ppq_get_quiz( $attempt->quiz_id );
    if ( $quiz->owner_id === $teacher_id ) {
        return true;
    }
    
    // Check if student is in teacher's groups
    $students = ppq_get_teacher_students( $teacher_id );
    return in_array( $attempt->user_id, $students, true );
}
```

---

## Rate Limiting

### Quiz Attempt Rate Limiting

Prevent rapid-fire quiz attempts (potential cheating or abuse):

```php
function ppq_check_attempt_rate_limit(): bool|WP_Error {
    $ip = ppq_get_client_ip();
    $user_id = get_current_user_id();
    
    // Use transient for rate limiting
    $key = 'ppq_attempts_' . ( $user_id ?: md5( $ip ) );
    $attempts = (int) get_transient( $key );
    
    // Allow 10 quiz starts per 1 minute
    if ( $attempts >= 10 ) {
        return new WP_Error(
            'ppq_rate_limited',
            __( 'Too many quiz attempts. Please wait 1 minute.', 'pressprimer-quiz' )
        );
    }
    
    // Increment counter
    set_transient( $key, $attempts + 1, 60 ); // 1 minute
    
    return true;
}

function ppq_get_client_ip(): string {
    $ip = '';
    
    if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ip = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0];
    } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    return sanitize_text_field( $ip );
}
```

### API Key Rate Limiting

For AI generation using user's API key:

```php
function ppq_check_ai_rate_limit( int $user_id ): bool|WP_Error {
    $key = 'ppq_ai_requests_' . $user_id;
    $requests = (int) get_transient( $key );
    
    // Allow 20 AI requests every 10 minutes
    if ( $requests >= 20 ) {
        return new WP_Error(
            'ppq_ai_rate_limited',
            __( 'AI generation limit reached. Please wait 10 minutes.', 'pressprimer-quiz' )
        );
    }
    
    set_transient( $key, $requests + 1, 600 );
    
    return true;
}
```

---

## Input Validation & Sanitization

### Form Data Validation

```php
function ppq_validate_quiz_data( array $data ): array|WP_Error {
    $errors = [];
    
    // Required fields
    if ( empty( $data['title'] ) ) {
        $errors[] = __( 'Quiz title is required.', 'pressprimer-quiz' );
    }
    
    // Type validation
    if ( isset( $data['time_limit_seconds'] ) ) {
        $time = absint( $data['time_limit_seconds'] );
        if ( $time > 0 && $time < 60 ) {
            $errors[] = __( 'Time limit must be at least 60 seconds.', 'pressprimer-quiz' );
        }
        if ( $time > 86400 ) { // 24 hours
            $errors[] = __( 'Time limit cannot exceed 24 hours.', 'pressprimer-quiz' );
        }
    }
    
    // Range validation
    if ( isset( $data['pass_percent'] ) ) {
        $pass = floatval( $data['pass_percent'] );
        if ( $pass < 0 || $pass > 100 ) {
            $errors[] = __( 'Passing percentage must be between 0 and 100.', 'pressprimer-quiz' );
        }
    }
    
    // Enum validation
    $valid_modes = [ 'tutorial', 'timed' ];
    if ( isset( $data['mode'] ) && ! in_array( $data['mode'], $valid_modes, true ) ) {
        $errors[] = __( 'Invalid quiz mode.', 'pressprimer-quiz' );
    }
    
    if ( ! empty( $errors ) ) {
        return new WP_Error( 'ppq_validation_error', implode( ' ', $errors ) );
    }
    
    // Return sanitized data
    return [
        'title' => sanitize_text_field( $data['title'] ),
        'description' => wp_kses_post( $data['description'] ?? '' ),
        'mode' => sanitize_key( $data['mode'] ?? 'tutorial' ),
        'time_limit_seconds' => isset( $data['time_limit_seconds'] ) ? absint( $data['time_limit_seconds'] ) : null,
        'pass_percent' => floatval( $data['pass_percent'] ?? 70 ),
        // ... etc
    ];
}
```

### Question Answer Validation

```php
function ppq_validate_answer_submission( int $question_id, $answer ): array|WP_Error {
    $question = ppq_get_question( $question_id );
    
    if ( ! $question ) {
        return new WP_Error( 'ppq_invalid_question', 'Question not found.' );
    }
    
    $revision = ppq_get_question_revision( $question->current_revision_id );
    $valid_answer_ids = array_column( json_decode( $revision->answers_json, true ), 'id' );
    
    // Normalize answer to array
    $selected = is_array( $answer ) ? $answer : [ $answer ];
    
    // Validate each selected answer exists
    foreach ( $selected as $ans_id ) {
        if ( ! in_array( $ans_id, $valid_answer_ids, true ) ) {
            return new WP_Error( 'ppq_invalid_answer', 'Invalid answer option.' );
        }
    }
    
    // Validate answer count for question type
    switch ( $question->type ) {
        case 'mc':
        case 'tf':
            if ( count( $selected ) !== 1 ) {
                return new WP_Error( 'ppq_invalid_answer_count', 'Select exactly one answer.' );
            }
            break;
        case 'ma':
            // Multiple answers allowed, but at least one required
            if ( count( $selected ) < 1 ) {
                return new WP_Error( 'ppq_invalid_answer_count', 'Select at least one answer.' );
            }
            break;
    }
    
    return [ 'selected' => $selected ];
}
```

---

## AJAX Security

### Standard AJAX Pattern

```php
// Register AJAX action
add_action( 'wp_ajax_ppq_save_answer', 'ppq_ajax_save_answer' );
add_action( 'wp_ajax_nopriv_ppq_save_answer', 'ppq_ajax_save_answer' ); // For guests

function ppq_ajax_save_answer() {
    // 1. Verify nonce
    if ( ! check_ajax_referer( 'ppq_quiz_nonce', 'nonce', false ) ) {
        wp_send_json_error( [
            'code' => 'invalid_nonce',
            'message' => __( 'Security check failed.', 'pressprimer-quiz' )
        ], 403 );
    }
    
    // 2. Validate required data
    if ( empty( $_POST['attempt_id'] ) || empty( $_POST['question_id'] ) ) {
        wp_send_json_error( [
            'code' => 'missing_data',
            'message' => __( 'Missing required data.', 'pressprimer-quiz' )
        ], 400 );
    }
    
    // 3. Sanitize input
    $attempt_id = absint( $_POST['attempt_id'] );
    $question_id = absint( $_POST['question_id'] );
    $answer = isset( $_POST['answer'] ) ? array_map( 'sanitize_text_field', (array) $_POST['answer'] ) : [];
    
    // 4. Verify ownership/access
    $attempt = ppq_get_attempt( $attempt_id );
    if ( ! $attempt ) {
        wp_send_json_error( [ 'code' => 'invalid_attempt' ], 404 );
    }
    
    // Check if this is user's attempt
    $current_user_id = get_current_user_id();
    if ( $current_user_id && $attempt->user_id !== $current_user_id ) {
        wp_send_json_error( [ 'code' => 'not_your_attempt' ], 403 );
    }
    
    // For guests, verify token
    if ( ! $current_user_id && $attempt->guest_token ) {
        $token = sanitize_text_field( $_POST['guest_token'] ?? '' );
        if ( ! hash_equals( $attempt->guest_token, $token ) ) {
            wp_send_json_error( [ 'code' => 'invalid_token' ], 403 );
        }
    }
    
    // 5. Check attempt is still in progress
    if ( 'in_progress' !== $attempt->status ) {
        wp_send_json_error( [
            'code' => 'attempt_completed',
            'message' => __( 'This quiz has already been submitted.', 'pressprimer-quiz' )
        ], 400 );
    }
    
    // 6. Process the action
    $result = ppq_save_answer( $attempt_id, $question_id, $answer );
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [
            'code' => $result->get_error_code(),
            'message' => $result->get_error_message()
        ], 400 );
    }
    
    // 7. Return success
    wp_send_json_success( [
        'saved' => true,
        'timestamp' => current_time( 'mysql' )
    ] );
}
```

### Localized Nonce

```php
// When enqueueing quiz script
wp_enqueue_script( 'ppq-quiz', PPQ_PLUGIN_URL . 'assets/js/quiz.js', [ 'jquery' ], PPQ_VERSION, true );

wp_localize_script( 'ppq-quiz', 'ppqQuiz', [
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce' => wp_create_nonce( 'ppq_quiz_nonce' ),
    'attemptId' => $attempt_id,
    'guestToken' => $guest_token ?? '',
    'strings' => [
        'saving' => __( 'Saving...', 'pressprimer-quiz' ),
        'saved' => __( 'Saved', 'pressprimer-quiz' ),
        'error' => __( 'Error saving answer', 'pressprimer-quiz' ),
    ]
] );
```

---

## Guest Security

### Guest Token Generation

```php
function ppq_generate_guest_token(): string {
    return bin2hex( random_bytes( 32 ) ); // 64 character hex string
}

function ppq_start_guest_attempt( int $quiz_id, string $email = '' ): array {
    $token = ppq_generate_guest_token();
    
    // Create attempt
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'ppq_attempts',
        [
            'uuid' => wp_generate_uuid4(),
            'quiz_id' => $quiz_id,
            'guest_email' => sanitize_email( $email ),
            'guest_token' => $token,
            'started_at' => current_time( 'mysql' ),
            'status' => 'in_progress',
            'questions_json' => wp_json_encode( ppq_generate_quiz_questions( $quiz_id ) ),
        ],
        [ '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
    );
    
    $attempt_id = $wpdb->insert_id;
    
    // Set cookie for guest session (24 hour expiry)
    $cookie_value = $attempt_id . ':' . $token;
    setcookie(
        'ppq_guest_attempt',
        $cookie_value,
        time() + DAY_IN_SECONDS,
        COOKIEPATH,
        COOKIE_DOMAIN,
        is_ssl(),
        true // HttpOnly
    );
    
    return [
        'attempt_id' => $attempt_id,
        'token' => $token,
    ];
}
```

### Guest Email to User Linking

```php
function ppq_maybe_link_guest_to_user( int $attempt_id ): void {
    $attempt = ppq_get_attempt( $attempt_id );
    
    if ( ! $attempt->guest_email || $attempt->user_id ) {
        return;
    }
    
    // Check if email matches a WP user
    $user = get_user_by( 'email', $attempt->guest_email );
    
    if ( $user ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'ppq_attempts',
            [ 'user_id' => $user->ID ],
            [ 'id' => $attempt_id ],
            [ '%d' ],
            [ '%d' ]
        );
    }
}
```

---

## API Key Security

### Encrypted Storage

```php
function ppq_save_api_key( int $user_id, string $api_key ): bool {
    // Encrypt before storing
    $encrypted = ppq_encrypt( $api_key );
    
    return update_user_meta( $user_id, 'ppq_openai_api_key', $encrypted );
}

function ppq_get_api_key( int $user_id ): string {
    $encrypted = get_user_meta( $user_id, 'ppq_openai_api_key', true );
    
    if ( ! $encrypted ) {
        return '';
    }
    
    return ppq_decrypt( $encrypted );
}

// Simple encryption using WordPress salts
function ppq_encrypt( string $data ): string {
    $key = wp_salt( 'auth' );
    $iv = random_bytes( 16 );
    $encrypted = openssl_encrypt( $data, 'AES-256-CBC', $key, 0, $iv );
    
    return base64_encode( $iv . $encrypted );
}

function ppq_decrypt( string $data ): string {
    $key = wp_salt( 'auth' );
    $data = base64_decode( $data );
    $iv = substr( $data, 0, 16 );
    $encrypted = substr( $data, 16 );
    
    return openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
}
```

### API Key Validation

```php
function ppq_validate_openai_key( string $api_key ): bool|WP_Error {
    // Basic format check
    if ( ! preg_match( '/^sk-[a-zA-Z0-9]{48}$/', $api_key ) ) {
        return new WP_Error( 'invalid_format', __( 'Invalid API key format.', 'pressprimer-quiz' ) );
    }
    
    // Test with minimal API call
    $response = wp_remote_get( 'https://api.openai.com/v1/models', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'timeout' => 10,
    ] );
    
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    
    $code = wp_remote_retrieve_response_code( $response );
    
    if ( 401 === $code ) {
        return new WP_Error( 'invalid_key', __( 'Invalid API key.', 'pressprimer-quiz' ) );
    }
    
    if ( 200 !== $code ) {
        return new WP_Error( 'api_error', __( 'Could not verify API key.', 'pressprimer-quiz' ) );
    }
    
    return true;
}
```

---

## Event Logging

For security audit and future proctoring:

```php
function ppq_log_event( int $attempt_id, string $event_type, array $payload = [] ): void {
    global $wpdb;
    
    $wpdb->insert(
        $wpdb->prefix . 'ppq_events',
        [
            'attempt_id' => $attempt_id,
            'user_id' => get_current_user_id() ?: null,
            'event_type' => $event_type,
            'payload_json' => wp_json_encode( $payload ),
            'created_at' => current_time( 'mysql', true ), // GMT
        ],
        [ '%d', '%d', '%s', '%s', '%s' ]
    );
}

// Usage
ppq_log_event( $attempt_id, 'quiz_started', [
    'ip' => ppq_get_client_ip(),
    'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
] );

ppq_log_event( $attempt_id, 'answer_saved', [
    'question_id' => $question_id,
    'selected' => $selected_answers,
] );

ppq_log_event( $attempt_id, 'focus_blur', [
    'timestamp' => time(),
] );
```

