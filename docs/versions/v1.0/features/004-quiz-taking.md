# Feature 004: Quiz Taking Experience

## Overview

The quiz taking experience is the core user-facing feature. It must be smooth, reliable, and cheat-resistant while supporting various configurations (timed, untimed, resumable, etc.).

## User Stories

### As a Student
- I want to see quiz details before starting so I know what to expect
- I want my answers saved automatically so I don't lose work
- I want to resume a quiz I started earlier so I can complete it later
- I want to see how much time is left so I can pace myself
- I want to navigate between questions so I can review my answers
- I want clear feedback when I submit so I know it worked

### As a Guest
- I want to take quizzes without logging in so I can participate easily
- I want my results emailed to me so I have a record

### As a Teacher
- I want students to be unable to cheat so results are valid
- I want to see when students started and finished so I can track engagement

## Quiz Flow

```
Landing Page → Start Quiz → Questions → Submit → Results
     ↓             ↓           ↓
  (info)      (creates     (auto-save,
              attempt)      navigate)
```

## Acceptance Criteria

### Landing Page
- [ ] Shows quiz title, description, featured image
- [ ] Shows question count and estimated time
- [ ] Shows time limit (if set)
- [ ] Shows passing score
- [ ] Shows attempt count (used/allowed)
- [ ] Shows previous attempts summary (logged-in users)
- [ ] Shows "Resume" button if in-progress attempt exists
- [ ] Guest email capture (if enabled)
- [ ] Start Quiz button (with attempt limit check)

### Starting Quiz
- [ ] Creates attempt record
- [ ] Generates question set (for dynamic quizzes)
- [ ] Locks questions to current revisions
- [ ] Stores question order (may be randomized)
- [ ] Initializes timer (if timed)
- [ ] Redirects to first question

### During Quiz
- [ ] Timer always visible (if timed)
- [ ] Timer shows hours:minutes:seconds
- [ ] Timer warnings at 5 minutes and 1 minute
- [ ] Progress indicator (Question X of Y)
- [ ] Question navigator (if backward enabled)
- [ ] Current question with answer options
- [ ] Confidence checkbox (if enabled)
- [ ] Answer selection visual feedback
- [ ] Save indicator (saving/saved)
- [ ] Previous/Next navigation buttons
- [ ] Submit button on last question

### Auto-Save
- [ ] Save triggered on every answer selection
- [ ] Save includes: selected answers, time spent, confidence, position
- [ ] Visual indicator during save
- [ ] Retry on network failure (3 attempts)
- [ ] Queue rapid saves (debounce)
- [ ] Save position for resume

### Navigation
- [ ] Next advances to next question
- [ ] Previous returns to previous (if allowed)
- [ ] Question navigator jumps to any question (if backward allowed)
- [ ] Skip allowed/prevented based on setting
- [ ] Unanswered questions marked in navigator

### Timer
- [ ] Counts down from time limit
- [ ] Pause on save (briefly, for network)
- [ ] Warning popup at 5 minutes
- [ ] Warning popup at 1 minute
- [ ] Auto-submit when timer reaches 0
- [ ] Server validates submission timing

### Submission
- [ ] Confirmation dialog before submit
- [ ] Submit all answers to server
- [ ] Server scores attempt
- [ ] Mark attempt as submitted
- [ ] Redirect to results page
- [ ] Prevent double-submit

### Resume
- [ ] Resume button on landing page for in-progress attempts
- [ ] Resume restores position
- [ ] Resume restores timer (time remaining)
- [ ] Resume works across devices (logged-in)
- [ ] Resume works same device (guest, cookie)
- [ ] If resume disabled, block access to in-progress

### Guest Handling
- [ ] Guest can take quiz without login
- [ ] Optional email capture before start
- [ ] 24-hour session via token
- [ ] Token stored in cookie
- [ ] Results accessible via tokenized URL
- [ ] If email matches WP user, link to account

## Technical Implementation

### Attempt Creation

```php
function ppq_start_attempt( $quiz_id, $user_id = null, $guest_email = '' ) {
    $quiz = PPQ_Quiz::get( $quiz_id );
    
    // Check permissions
    if ( ! ppq_can_take_quiz( $quiz_id, $user_id ) ) {
        return new WP_Error( 'not_allowed', 'Cannot take this quiz' );
    }
    
    // Check attempt limits
    $existing = ppq_get_user_attempts( $quiz_id, $user_id, $guest_email );
    if ( $quiz->max_attempts && count( $existing ) >= $quiz->max_attempts ) {
        return new WP_Error( 'limit_reached', 'Attempt limit reached' );
    }
    
    // Check delay
    if ( $quiz->attempt_delay_minutes && ! empty( $existing ) ) {
        $last = end( $existing );
        $elapsed = time() - strtotime( $last->finished_at );
        if ( $elapsed < $quiz->attempt_delay_minutes * 60 ) {
            return new WP_Error( 'too_soon', 'Must wait before retaking' );
        }
    }
    
    // Generate questions
    $questions = ppq_generate_questions_for_attempt( $quiz_id );
    
    // Create attempt
    $attempt_data = [
        'quiz_id' => $quiz_id,
        'user_id' => $user_id,
        'guest_email' => $guest_email,
        'guest_token' => $user_id ? null : ppq_generate_token(),
        'status' => 'in_progress',
        'questions_json' => wp_json_encode( $questions ),
    ];
    
    return PPQ_Attempt::create( $attempt_data );
}
```

### Answer Saving

```php
function ppq_save_answer( $attempt_id, $question_id, $answers ) {
    $attempt = PPQ_Attempt::get( $attempt_id );
    
    // Validate ownership
    if ( ! ppq_can_access_attempt( $attempt_id ) ) {
        return new WP_Error( 'not_allowed', 'Not your attempt' );
    }
    
    // Validate attempt in progress
    if ( $attempt->status !== 'in_progress' ) {
        return new WP_Error( 'completed', 'Already submitted' );
    }
    
    // Validate question in this attempt
    if ( ! ppq_question_in_attempt( $attempt_id, $question_id ) ) {
        return new WP_Error( 'invalid', 'Question not in attempt' );
    }
    
    // Save/update answer
    $item = PPQ_Attempt_Item::get_or_create( $attempt_id, $question_id );
    $item->selected_answers_json = wp_json_encode( $answers );
    $item->last_answer_at = current_time( 'mysql' );
    $item->save();
    
    return true;
}
```

### Timer Validation

```php
function ppq_validate_submission_time( $attempt_id ) {
    $attempt = PPQ_Attempt::get( $attempt_id );
    $quiz = PPQ_Quiz::get( $attempt->quiz_id );
    
    if ( ! $quiz->time_limit_seconds ) {
        return true; // No time limit
    }
    
    $started = strtotime( $attempt->started_at );
    $now = time();
    $elapsed = $now - $started;
    
    // 30 second grace period for network latency
    $grace = 30;
    
    return $elapsed <= ( $quiz->time_limit_seconds + $grace );
}
```

## UI/UX Requirements

### Landing Page
```
+----------------------------------+
|  [Featured Image]                |
|                                  |
|  Quiz Title                      |
|  Description text here...        |
|                                  |
|  📝 20 questions                 |
|  ⏱️ 30 minutes                   |
|  🎯 70% to pass                  |
|  📊 Attempts: 1 of 3             |
|                                  |
|  [Previous Attempts]             |
|  - Attempt 1: 85% ✓              |
|                                  |
|  Email: [____________]  (guest)  |
|                                  |
|  [    Start Quiz    ]            |
|        or                        |
|  [  Resume Quiz  ]               |
+----------------------------------+
```

### Quiz Interface
```
+----------------------------------+
|  Quiz Title            ⏱️ 25:30  |
+----------------------------------+
|  Question 5 of 20    [progress]  |
+----------------------------------+
|                                  |
|  What is the capital of France?  |
|                                  |
|  ○ London                        |
|  ● Paris                         |
|  ○ Berlin                        |
|  ○ Madrid                        |
|                                  |
|  ☐ I am confident in my answer   |
|                                  |
+----------------------------------+
|  [1][2][3][4][5●][6][7]...      |
+----------------------------------+
|  [Previous]  Saved ✓  [Next]     |
|                      [Submit]    |
+----------------------------------+
```

### Timer Styles
- Green: > 5 minutes remaining
- Yellow: 1-5 minutes remaining
- Red: < 1 minute remaining
- Pulsing animation in last minute

### Save Indicator States
- "Saving..." - spinner, during save
- "Saved ✓" - checkmark, after success
- "Error - Retrying" - warning, on failure
- Auto-hide after 3 seconds

## Security Requirements

### Critical: Never Expose Answers
- Correct answers NOT in HTML
- Correct answers NOT in JavaScript
- Correct answers NOT in any API response during quiz
- All validation happens server-side after submission

### Session Security
- Nonce on all AJAX calls
- Verify user owns attempt
- Verify attempt is in progress
- Rate limit quiz starts (10/10min)
- Server-side timer validation

### Anti-Cheat Measures
- Randomize questions (if enabled)
- Randomize answer order (if enabled)
- Different questions per attempt (dynamic)
- Log focus/blur events (for future proctoring)

## Edge Cases

1. **Network failure during save** - Retry 3 times, show error, don't lose data locally
2. **Timer expires during navigation** - Auto-submit with current answers
3. **Browser tab closed** - Resume available (if enabled)
4. **Double-click submit** - Debounce, prevent duplicate submission
5. **Guest returns after cookie expires** - Cannot resume, must start new attempt
6. **User logs in with guest email** - Link attempt to account
7. **Zero time limit** - Treated as no limit
8. **Attempt limit reached** - Show message, no start button

## Not In Scope (v1.0)

- Question flagging/marking for review
- Calculator widget
- Scratch pad / notes
- Full-screen / locked mode
- Multiple browser detection
- IP logging / comparison
- Proctoring (Enterprise tier)

## Testing Checklist

- [ ] View landing page with all information
- [ ] Start quiz as logged-in user
- [ ] Start quiz as guest
- [ ] Answer question and verify save
- [ ] Navigate forward with Next
- [ ] Navigate backward with Previous
- [ ] Use question navigator to jump
- [ ] See timer count down
- [ ] See timer warnings
- [ ] Complete quiz before time expires
- [ ] Let timer expire and verify auto-submit
- [ ] Start quiz, leave, resume later
- [ ] Resume on different device (logged-in)
- [ ] Resume on same device (guest)
- [ ] Submit quiz and reach results
- [ ] Verify answers not in page source
- [ ] Test with JavaScript disabled (graceful fail)

