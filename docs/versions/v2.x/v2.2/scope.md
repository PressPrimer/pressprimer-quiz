# Version 2.2 Free - Scope Document

**Status**: Planning

**Target Release**: 4-5 weeks after v2.1

**Last Updated**: 2025-12-20

---

## Release Goal

Version 2.2 delivers **advanced quiz controls** that put PressPrimer Quiz on par with premium competitors. The headline feature—question pooling—transforms any question set into a randomized pool, enabling varied assessments from the same source material.

### Core Objectives

1. Enable maximum questions from pool for varied quiz attempts
2. Provide maintenance tools for cache management
3. Improve pagination for users with many quiz attempts
4. Polish the overall experience before moving to premium addons

---

## Features in Scope

### 1. Maximum Questions from Pool

**Priority**: Critical

**Problem Solved**: Currently, if you add 50 questions to a quiz, students see all 50. For review quizzes, practice tests, and certification preparation, educators want large question pools where each attempt shows a random subset (e.g., "Show 20 of these 50 questions").

#### Overview

This feature treats all assigned questions (whether fixed or dynamic) as a **pool** from which a specified number are randomly drawn for each attempt. Different students (or the same student on different attempts) get different question combinations.

#### Quiz Builder UI

**Location**: Quiz Builder → Settings → "Question Pool" section

**New Controls:**

| Control | Type | Description |
|---------|------|-------------|
| Enable Question Pool | Toggle | When enabled, limits questions shown per attempt |
| Maximum Questions | Number | How many questions from the pool to show (1 to total) |
| Pool Preview | Info | Shows "20 of 47 questions will be shown per attempt" |

**Validation Rules:**
- Maximum Questions must be ≤ total available questions
- If dynamic rules are used, shows estimated range
- Warning if max < passing score would make (e.g., "With 5 questions at 70% pass, students need 4 correct")

**UI Mockup:**
```
┌─────────────────────────────────────────────────────────────┐
│ Question Pool                                               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ☑ Limit questions per attempt                              │
│                                                             │
│  Show [20 ▼] questions from pool of 47                     │
│                                                             │
│  ℹ️ Each attempt will randomly select 20 questions.         │
│     Students may see different questions on retakes.        │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

#### Behavior

**Question Selection:**
1. When attempt starts, all eligible questions are identified
2. For fixed quizzes: all items in wp_ppq_quiz_items
3. For dynamic quizzes: questions matching all rules
4. Random subset of `max_questions` is selected
5. Selected questions stored with attempt (so resume shows same questions)
6. Order randomized if "Randomize Questions" setting enabled

**Scoring Implications:**
- Points calculated from actual questions shown, not total pool
- Pass percentage applies to shown questions only
- Example: 20 questions shown, 70% pass = 14 correct needed
- Category breakdown based on shown questions only

**Reporting:**
- Reports show questions attempted vs pool size
- "Questions: 18/20 (from pool of 47)"
- Per-question analytics counts actual appearances

#### Database Changes

**wp_ppq_quizzes Table Additions:**
```sql
ALTER TABLE wp_ppq_quizzes ADD COLUMN pool_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE wp_ppq_quizzes ADD COLUMN max_questions INT UNSIGNED DEFAULT NULL;
```

| Column | Type | Description |
|--------|------|-------------|
| `pool_enabled` | TINYINT(1) | 0 = show all, 1 = limit from pool |
| `max_questions` | INT UNSIGNED | NULL = show all, else limit |

**wp_ppq_attempt_items Notes:**
- Already stores which questions were shown per attempt
- No schema change needed
- Pool selection happens at attempt creation

#### Technical Implementation

**Pool Question Selection:**
```php
/**
 * Select questions for a new quiz attempt
 *
 * @param PPQ_Quiz $quiz    Quiz object.
 * @param int      $user_id User taking the quiz.
 * @return array Array of question IDs to include.
 */
function pressprimer_quiz_select_attempt_questions( $quiz, $user_id ) {
    // Get all eligible questions
    if ( 'dynamic' === $quiz->generation_mode ) {
        $questions = pressprimer_quiz_get_dynamic_questions( $quiz->id );
    } else {
        $questions = pressprimer_quiz_get_fixed_questions( $quiz->id );
    }
    
    $question_ids = wp_list_pluck( $questions, 'id' );
    
    // Apply pool limit if enabled
    if ( $quiz->pool_enabled && $quiz->max_questions ) {
        $max = min( $quiz->max_questions, count( $question_ids ) );
        shuffle( $question_ids );
        $question_ids = array_slice( $question_ids, 0, $max );
    }
    
    // Apply question randomization if enabled
    if ( $quiz->randomize_questions ) {
        shuffle( $question_ids );
    }
    
    return $question_ids;
}
```

**Attempt Creation Update:**
```php
/**
 * Create attempt with pool-selected questions
 */
public function create_for_user( $quiz_id, $user_id ) {
    $quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
    
    // Select questions (handles pooling)
    $question_ids = pressprimer_quiz_select_attempt_questions( $quiz, $user_id );
    
    // Create attempt record
    $attempt_id = $this->insert_attempt( $quiz_id, $user_id );
    
    // Create attempt items for selected questions only
    foreach ( $question_ids as $position => $question_id ) {
        $this->create_attempt_item( $attempt_id, $question_id, $position );
    }
    
    return $attempt_id;
}
```

**Pool Size Calculation (for dynamic quizzes):**
```php
/**
 * Calculate expected pool size for display
 *
 * @param int $quiz_id Quiz ID.
 * @return array Array with 'min', 'max', 'exact' keys.
 */
function pressprimer_quiz_get_pool_size( $quiz_id ) {
    $quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
    
    if ( 'fixed' === $quiz->generation_mode ) {
        $count = pressprimer_quiz_count_quiz_items( $quiz_id );
        return array( 'min' => $count, 'max' => $count, 'exact' => true );
    }
    
    // For dynamic, calculate from rules
    $rules = pressprimer_quiz_get_quiz_rules( $quiz_id );
    $total = 0;
    
    foreach ( $rules as $rule ) {
        $bank_count = pressprimer_quiz_count_bank_questions( $rule->bank_id );
        $total += min( $rule->question_count, $bank_count );
    }
    
    // Dynamic rules might have variable counts
    return array( 'min' => $total, 'max' => $total, 'exact' => true );
}
```

#### Edge Cases

**Pool larger than max:**
- Normal operation, random subset selected

**Pool smaller than max:**
- Warning shown in quiz builder
- All questions shown (max effectively ignored)
- "Only 15 questions available, all will be shown"

**Pool equals max:**
- All questions shown
- Randomization still applies if enabled

**Pool size changes after attempts exist:**
- New attempts use current pool/max
- Existing attempts unchanged (questions already recorded)

**Zero questions in pool:**
- Quiz cannot be taken
- Error message: "No questions available for this quiz"

#### Modified Files

```
includes/models/class-ppq-quiz.php           # Add pool_enabled, max_questions
includes/models/class-ppq-attempt.php        # Use pool selection
includes/admin/class-ppq-admin-quiz-builder.php  # Pool settings UI
includes/services/class-ppq-scoring-service.php  # Handle pool scoring
assets/js/admin/quiz-builder.js              # Pool settings interaction
```

---

### 2. Cache Clearing Button

**Priority**: Medium

**Problem Solved**: Cached statistics sometimes become stale, especially after bulk operations or database maintenance. Administrators need a way to force-refresh all cached data.

#### Admin Interface

**Location**: PPQ Settings → Tools tab (new)

**Tools Section:**
```
┌─────────────────────────────────────────────────────────────┐
│ Maintenance Tools                                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Clear Statistics Cache                                      │
│ ─────────────────────────────────────────────────────────── │
│ Force recalculation of all quiz statistics, including       │
│ attempt counts, pass rates, and average scores.             │
│                                                             │
│ Last cleared: December 15, 2025 at 3:42 PM                 │
│                                                             │
│ [Clear Cache Now]                                           │
│                                                             │
│ ⚠️ This may take a moment for sites with many attempts.     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

#### What Gets Cleared

| Cache Type | Storage | Description |
|------------|---------|-------------|
| Dashboard stats | Transient | Total quizzes, questions, attempts, pass rate |
| Quiz stats | Object cache | Per-quiz attempt count, average score |
| Question stats | Object cache | Times used, actual difficulty |
| Report data | Transient | Cached report queries |

**Transient Keys:**
- `ppq_dashboard_stats` — Dashboard widget statistics
- `ppq_quiz_stats_{quiz_id}` — Per-quiz statistics
- `ppq_question_stats_{question_id}` — Per-question statistics
- `ppq_report_cache_*` — Report query results

#### Technical Implementation

**Cache Clearing Function:**
```php
/**
 * Clear all PPQ caches
 *
 * @return array Results of clearing operation.
 */
function pressprimer_quiz_clear_all_caches() {
    global $wpdb;
    
    $results = array(
        'transients_cleared' => 0,
        'object_cache_cleared' => false,
        'time' => current_time( 'mysql' ),
    );
    
    // Clear transients
    $transients = $wpdb->get_col(
        "SELECT option_name FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_ppq_%' 
         OR option_name LIKE '_transient_timeout_ppq_%'"
    );
    
    foreach ( $transients as $transient ) {
        $name = str_replace( array( '_transient_', '_transient_timeout_' ), '', $transient );
        delete_transient( $name );
        $results['transients_cleared']++;
    }
    
    // Clear object cache if available
    if ( wp_using_ext_object_cache() ) {
        wp_cache_delete_group( 'ppq_stats' );
        wp_cache_delete_group( 'ppq_quizzes' );
        wp_cache_delete_group( 'ppq_questions' );
        $results['object_cache_cleared'] = true;
    }
    
    // Update last cleared timestamp
    update_option( 'ppq_last_cache_clear', $results['time'] );
    
    // Fire action for addons
    do_action( 'pressprimer_quiz_caches_cleared', $results );
    
    return $results;
}
```

**AJAX Handler:**
```php
add_action( 'wp_ajax_ppq_clear_cache', function() {
    check_ajax_referer( 'ppq_clear_cache', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'pressprimer-quiz' ) ) );
    }
    
    $results = pressprimer_quiz_clear_all_caches();
    
    wp_send_json_success( array(
        'message' => sprintf(
            /* translators: %d: number of transients cleared */
            __( 'Cache cleared successfully. %d cached items removed.', 'pressprimer-quiz' ),
            $results['transients_cleared']
        ),
        'last_cleared' => $results['time'],
    ) );
} );
```

**New Options:**
- `ppq_last_cache_clear` — Timestamp of last cache clear

#### Modified Files

```
includes/admin/class-ppq-admin-settings.php  # Add Tools tab
includes/services/class-ppq-stats-service.php  # Add clear function
assets/js/admin/settings.js                  # Cache clear button handler
```

---

### 3. Quiz Attempt History Pagination

**Priority**: Medium

**Problem Solved**: For quizzes with many attempts, the "Previous Attempts" section on the landing page and "My Attempts" pages become unwieldy. Loading hundreds of attempts impacts performance.

#### Landing Page Previous Attempts

**Current Behavior:**
- Shows all previous attempts in a table
- Performance degrades with many attempts

**New Behavior:**
- Show 5 most recent attempts by default
- "Show more" link to expand/paginate
- Total count indicator: "Showing 5 of 23 attempts"

**UI Mockup:**
```
┌─────────────────────────────────────────────────────────────┐
│ Your Previous Attempts                                      │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Date          Score    Status    Time                      │
│  ───────────────────────────────────────────────────────    │
│  Dec 15, 2025   85%     Passed    12:34                     │
│  Dec 10, 2025   72%     Passed    15:21                     │
│  Dec 5, 2025    65%     Failed    18:45                     │
│  Nov 28, 2025   58%     Failed    14:02                     │
│  Nov 20, 2025   45%     Failed    11:33                     │
│                                                             │
│  Showing 5 of 23 attempts   [Show more ▼]                  │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Interaction:**
- Click "Show more" loads next 5 via AJAX
- Repeat until all shown
- When all shown, link changes to "Show less" to collapse

#### My Attempts Page Pagination

**Current Behavior:**
- All attempts loaded at once
- Basic filtering available

**New Behavior:**
- 10 attempts per page by default
- Standard pagination controls
- Filter and sort apply to paginated results
- URL reflects current page for bookmarking

**Pagination Controls:**
```
┌───────────────────────────────────────────────────────────────────────┐
│ [← Previous]  Page [2 ▼] of 5  [Next →]        Showing 11-20 of 47   │
└───────────────────────────────────────────────────────────────────────┘
```

#### Technical Implementation

**Landing Page AJAX:**
```php
add_action( 'wp_ajax_ppq_load_more_attempts', 'pressprimer_quiz_ajax_load_more_attempts' );
add_action( 'wp_ajax_nopriv_ppq_load_more_attempts', 'pressprimer_quiz_ajax_load_more_attempts' );

function pressprimer_quiz_ajax_load_more_attempts() {
    check_ajax_referer( 'ppq_load_attempts', 'nonce' );
    
    $quiz_id = intval( $_POST['quiz_id'] ?? 0 );
    $offset = intval( $_POST['offset'] ?? 0 );
    $per_page = 5;
    
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        wp_send_json_error();
    }
    
    $attempts = pressprimer_quiz_get_user_attempts( $user_id, $quiz_id, array(
        'limit' => $per_page,
        'offset' => $offset,
    ) );
    
    $total = pressprimer_quiz_count_user_attempts( $quiz_id, $user_id );
    
    ob_start();
    foreach ( $attempts as $attempt ) {
        pressprimer_quiz_render_attempt_row( $attempt );
    }
    $html = ob_get_clean();
    
    wp_send_json_success( array(
        'html' => $html,
        'loaded' => count( $attempts ),
        'total' => $total,
        'has_more' => ( $offset + count( $attempts ) ) < $total,
    ) );
}
```

**Paginated Query Function:**
```php
/**
 * Get paginated user attempts
 *
 * @param int   $user_id User ID.
 * @param int   $quiz_id Optional quiz filter.
 * @param array $args    Query arguments.
 * @return array Array of attempt objects.
 */
function pressprimer_quiz_get_user_attempts( $user_id, $quiz_id = null, $args = array() ) {
    global $wpdb;
    
    $defaults = array(
        'limit'   => 10,
        'offset'  => 0,
        'orderby' => 'finished_at',
        'order'   => 'DESC',
    );
    
    $args = wp_parse_args( $args, $defaults );
    
    $where = array(
        $wpdb->prepare( "user_id = %d", $user_id ),
        "status = 'submitted'",
    );
    
    if ( $quiz_id ) {
        $where[] = $wpdb->prepare( "quiz_id = %d", $quiz_id );
    }
    
    $where_sql = implode( ' AND ', $where );
    $order_sql = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );
    
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ppq_attempts
         WHERE {$where_sql}
         ORDER BY {$order_sql}
         LIMIT %d OFFSET %d",
        $args['limit'],
        $args['offset']
    ) );
}
```

**New Settings:**
- `ppq_attempts_per_page` — Number per page (default: 10)
- `ppq_landing_attempts_initial` — Initial count on landing (default: 5)

#### Modified Files

```
includes/frontend/class-ppq-quiz-renderer.php   # Paginated previous attempts
includes/frontend/class-ppq-shortcodes.php      # Paginated My Attempts
assets/js/quiz.js                               # Load more AJAX
assets/js/my-attempts.js                        # Pagination controls
assets/css/frontend.css                         # Pagination styling
```

---

## What's NOT in v2.2

These are explicitly **excluded** from version 2.2:

### Excluded (moved to v3.x)
- Advanced question navigator styling
- Additional pool randomization options (weighted, category-balanced)
- Attempt comparison view

### Excluded (premium only)
- Question pool by category (balanced distribution)
- Pool exclusion rules ("never show these together")
- Smart pool (adaptive based on past performance)
- Scheduled cache clearing

---

## Database Changes

**wp_ppq_quizzes Alterations:**
```sql
ALTER TABLE wp_ppq_quizzes ADD COLUMN pool_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE wp_ppq_quizzes ADD COLUMN max_questions INT UNSIGNED DEFAULT NULL;
```

**New Options:**
- `ppq_last_cache_clear` — Timestamp
- `ppq_attempts_per_page` — Integer (default: 10)
- `ppq_landing_attempts_initial` — Integer (default: 5)

---

## File Changes Summary

### New Files

```
assets/js/my-attempts.js     # Pagination for My Attempts page
```

### Modified Files

```
pressprimer-quiz.php                            # Version bump
includes/class-ppq-activator.php                # Database migration
includes/models/class-ppq-quiz.php              # Pool columns
includes/models/class-ppq-attempt.php           # Pool question selection
includes/admin/class-ppq-admin-quiz-builder.php # Pool settings UI
includes/admin/class-ppq-admin-settings.php     # Tools tab
includes/services/class-ppq-stats-service.php   # Cache clearing
includes/services/class-ppq-scoring-service.php # Pool scoring
includes/frontend/class-ppq-quiz-renderer.php   # Paginated previous attempts
includes/frontend/class-ppq-shortcodes.php      # Paginated My Attempts
assets/js/admin/quiz-builder.js                 # Pool settings
assets/js/admin/settings.js                     # Cache clear button
assets/js/quiz.js                               # Load more attempts
assets/css/frontend.css                         # Pagination styling
assets/css/admin.css                            # Tools tab styling
```

---

## Testing Checklist

### Maximum Questions from Pool
- [ ] Pool toggle enables/disables max questions field
- [ ] Max questions validates against total available
- [ ] Warning shows when max > pool size
- [ ] Different attempts get different questions
- [ ] Same attempt always shows same questions (resume)
- [ ] Scoring uses only shown questions
- [ ] Category breakdown accurate for pool
- [ ] Reports show "X of Y from pool of Z"
- [ ] Works with fixed quizzes
- [ ] Works with dynamic quizzes
- [ ] Works with question randomization

### Cache Clearing
- [ ] Tools tab appears in Settings
- [ ] Clear button triggers AJAX
- [ ] Success message shows count
- [ ] Dashboard stats actually refresh
- [ ] Quiz stats actually refresh
- [ ] Last cleared timestamp updates
- [ ] Works with object cache enabled
- [ ] Works without object cache

### Quiz Attempt History Pagination
- [ ] Landing page shows 5 attempts initially
- [ ] "Show more" loads next 5
- [ ] Total count displays correctly
- [ ] "Show less" collapses back to 5
- [ ] My Attempts page shows 10 per page
- [ ] Pagination controls work
- [ ] Filters work with pagination
- [ ] Sort works with pagination
- [ ] URL updates with page number

---

## Success Metrics

### Launch Targets (within 14 days)
- 95%+ of v2.1 users update without issues
- <1% support tickets related to upgrade
- Zero critical bugs

### Ongoing Targets
- 25%+ of quizzes using pool mode within 90 days
- Reduced support tickets about "wrong attempt counts"
- Improved page load times for users with many attempts
- Positive reviews mentioning question pool feature

---

## Development Notes

### Branching Strategy

```
main
└── release/2.2
    ├── feature/question-pool
    ├── feature/cache-clearing
    └── feature/attempt-pagination
```

### Testing Priorities

1. Question pool generates correct subsets
2. Different attempts get different questions
3. Cache clearing actually clears everything
4. Stats recalculate accurately after cache clear
5. Pagination works with many attempts

### Migration Notes

**Database Migration:**
- Adds two new columns to wp_ppq_quizzes
- No data migration needed (new columns have defaults)
- Migration runs on plugin update

**Backwards Compatibility:**
- Existing quizzes have pool_enabled = 0 (no change in behavior)
- Existing attempts unaffected by pool feature
- Pagination is enhancement, not breaking change

### Performance Considerations

**Question Pool:**
- Pool selection happens once at attempt creation
- No additional queries during quiz taking
- Selected questions stored with attempt

**Cache Clearing:**
- May take several seconds for large sites
- Shows progress indicator
- Runs in single request (no background processing)

**Pagination:**
- Lazy loads additional attempts
- Initial page load faster
- Maintains state in URL for bookmarking

---

## Completion Criteria

Version 2.2 is complete when:

1. ✅ Question pool works for fixed and dynamic quizzes
2. ✅ Different attempts from same quiz show different questions
3. ✅ Scoring and reporting accurate for pooled quizzes
4. ✅ Cache clearing removes all cached data
5. ✅ Previous attempts paginate on landing page
6. ✅ My Attempts page fully paginated
7. ✅ All tests pass
8. ✅ Documentation updated
9. ✅ No performance regressions

