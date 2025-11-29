# Feature 010: Admin Reporting

## Overview

Admin reporting provides administrators with insights into quiz performance, student progress, and content effectiveness. The reporting system includes a WordPress dashboard widget for at-a-glance metrics and a dedicated Reports page for detailed analysis.

## User Stories

### US-010-1: Dashboard Overview
**As an** administrator  
**I want to** see key quiz metrics on my WordPress dashboard  
**So that** I can monitor activity without navigating to the plugin

### US-010-2: Quiz Performance Reports
**As an** administrator  
**I want to** see how each quiz is performing  
**So that** I can identify which quizzes need attention

### US-010-3: Student Results
**As an** administrator  
**I want to** view individual student results  
**So that** I can track progress and provide support

### US-010-4: Filter and Search
**As an** administrator  
**I want to** filter reports by quiz, student, and date range  
**So that** I can focus on specific data

## Acceptance Criteria

### Dashboard Widget (WordPress Dashboard)

- [ ] Widget on main WordPress dashboard (wp-admin/)
- [ ] Requires `ppq_manage_own` capability to view
- [ ] Shows summary statistics:
  - Total quizzes (published)
  - Total questions (active)
  - Total question banks
  - Attempts in last 7 days
  - Pass rate in last 7 days (percentage)
- [ ] Shows top 5 most popular quizzes (by attempt count, last 30 days)
- [ ] Quick action links:
  - Create Quiz
  - Add Question
  - View All Reports
  - Launch Onboarding (if not completed)
- [ ] Collapsible/dismissible like standard WordPress widgets
- [ ] Respects user's dashboard widget preferences

### Reports Page (PPQ > Reports)

**Overview Section:**
- [ ] Total attempts (all time)
- [ ] Average score (all quizzes)
- [ ] Overall pass rate
- [ ] Average completion time
- [ ] Date range selector (Last 7 days, 30 days, 90 days, All time, Custom)

**Quiz Performance Table:**
- [ ] Columns: Quiz Name, Attempts, Avg Score, Pass Rate, Avg Time
- [ ] Sortable by each column
- [ ] Click quiz name to filter by that quiz
- [ ] Pagination (20 per page)
- [ ] Search by quiz title

**Recent Attempts Table:**
- [ ] Columns: Student, Quiz, Score, Pass/Fail, Date, Duration
- [ ] Filter by quiz (dropdown)
- [ ] Filter by pass/fail status
- [ ] Filter by date range
- [ ] Search by student name/email
- [ ] Click row to view attempt details
- [ ] Pagination (20 per page)
- [ ] Export filtered results (CSV) - deferred to v2.0

**Attempt Detail Modal:**
- [ ] Student information (name, email)
- [ ] Quiz title
- [ ] Score (points and percentage)
- [ ] Pass/fail status
- [ ] Time spent
- [ ] Started at / Finished at
- [ ] Per-question breakdown:
  - Question stem (truncated)
  - Correct/Incorrect indicator
  - Time spent on question
- [ ] Link to view full results page

### Access Control

- [ ] Reports page requires `ppq_view_results_all` or `ppq_view_results_own`
- [ ] Users with `ppq_view_results_own` see only their own content's results
- [ ] Administrators see all data
- [ ] Dashboard widget respects same permissions

## Technical Implementation

### Dashboard Statistics Query

```php
/**
 * Get dashboard statistics
 *
 * @return array Statistics array
 */
function ppq_get_dashboard_stats() {
    global $wpdb;
    
    $stats = array(
        'total_quizzes'    => 0,
        'total_questions'  => 0,
        'total_banks'      => 0,
        'recent_attempts'  => 0,
        'recent_pass_rate' => 0,
        'popular_quizzes'  => array(),
    );
    
    // Total published quizzes
    $stats['total_quizzes'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ppq_quizzes WHERE status = 'published'"
    );
    
    // Total active questions
    $stats['total_questions'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ppq_questions WHERE deleted_at IS NULL"
    );
    
    // Total question banks
    $stats['total_banks'] = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ppq_banks WHERE deleted_at IS NULL"
    );
    
    // Attempts in last 7 days
    $seven_days_ago = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
    $stats['recent_attempts'] = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ppq_attempts 
         WHERE status = 'submitted' AND finished_at >= %s",
        $seven_days_ago
    ) );
    
    // Pass rate in last 7 days
    $pass_data = $wpdb->get_row( $wpdb->prepare(
        "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed
         FROM {$wpdb->prefix}ppq_attempts 
         WHERE status = 'submitted' AND finished_at >= %s",
        $seven_days_ago
    ) );
    
    if ( $pass_data && $pass_data->total > 0 ) {
        $stats['recent_pass_rate'] = round( ( $pass_data->passed / $pass_data->total ) * 100, 1 );
    }
    
    // Popular quizzes (last 30 days)
    $thirty_days_ago = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
    $stats['popular_quizzes'] = $wpdb->get_results( $wpdb->prepare(
        "SELECT q.id, q.title, COUNT(a.id) as attempt_count
         FROM {$wpdb->prefix}ppq_quizzes q
         LEFT JOIN {$wpdb->prefix}ppq_attempts a ON q.id = a.quiz_id 
            AND a.status = 'submitted' AND a.finished_at >= %s
         WHERE q.status = 'published'
         GROUP BY q.id
         ORDER BY attempt_count DESC
         LIMIT 5",
        $thirty_days_ago
    ) );
    
    return $stats;
}
```

### Reports Page Data Structure

```php
/**
 * Get quiz performance data for reports
 *
 * @param array $args Query arguments
 * @return array Quiz performance data
 */
function ppq_get_quiz_performance( $args = array() ) {
    global $wpdb;
    
    $defaults = array(
        'date_from'  => null,
        'date_to'    => null,
        'search'     => '',
        'orderby'    => 'attempts',
        'order'      => 'DESC',
        'per_page'   => 20,
        'page'       => 1,
    );
    
    $args = wp_parse_args( $args, $defaults );
    
    $where = array( "q.status = 'published'" );
    $having = array();
    
    // Date filtering on attempts
    $date_where = "a.status = 'submitted'";
    if ( $args['date_from'] ) {
        $date_where .= $wpdb->prepare( " AND a.finished_at >= %s", $args['date_from'] );
    }
    if ( $args['date_to'] ) {
        $date_where .= $wpdb->prepare( " AND a.finished_at <= %s", $args['date_to'] );
    }
    
    // Search
    if ( ! empty( $args['search'] ) ) {
        $where[] = $wpdb->prepare( "q.title LIKE %s", '%' . $wpdb->esc_like( $args['search'] ) . '%' );
    }
    
    $where_sql = implode( ' AND ', $where );
    
    // Validate orderby
    $allowed_orderby = array( 'title', 'attempts', 'avg_score', 'pass_rate', 'avg_time' );
    $orderby = in_array( $args['orderby'], $allowed_orderby ) ? $args['orderby'] : 'attempts';
    $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
    
    $offset = ( $args['page'] - 1 ) * $args['per_page'];
    
    $results = $wpdb->get_results( $wpdb->prepare(
        "SELECT 
            q.id,
            q.title,
            COUNT(CASE WHEN {$date_where} THEN a.id END) as attempts,
            ROUND(AVG(CASE WHEN {$date_where} THEN a.score_percent END), 1) as avg_score,
            ROUND(
                (SUM(CASE WHEN {$date_where} AND a.passed = 1 THEN 1 ELSE 0 END) / 
                 NULLIF(COUNT(CASE WHEN {$date_where} THEN a.id END), 0)) * 100, 
            1) as pass_rate,
            ROUND(AVG(CASE WHEN {$date_where} THEN a.duration_seconds END)) as avg_time
         FROM {$wpdb->prefix}ppq_quizzes q
         LEFT JOIN {$wpdb->prefix}ppq_attempts a ON q.id = a.quiz_id
         WHERE {$where_sql}
         GROUP BY q.id
         ORDER BY {$orderby} {$order}
         LIMIT %d OFFSET %d",
        $args['per_page'],
        $offset
    ) );
    
    // Get total count
    $total = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ppq_quizzes q WHERE {$where_sql}"
    );
    
    return array(
        'items'       => $results,
        'total'       => (int) $total,
        'total_pages' => ceil( $total / $args['per_page'] ),
        'page'        => $args['page'],
    );
}

/**
 * Get recent attempts for reports
 *
 * @param array $args Query arguments
 * @return array Attempts data
 */
function ppq_get_recent_attempts( $args = array() ) {
    global $wpdb;
    
    $defaults = array(
        'quiz_id'    => null,
        'user_id'    => null,
        'passed'     => null, // null = all, 1 = passed, 0 = failed
        'date_from'  => null,
        'date_to'    => null,
        'search'     => '',
        'orderby'    => 'finished_at',
        'order'      => 'DESC',
        'per_page'   => 20,
        'page'       => 1,
    );
    
    $args = wp_parse_args( $args, $defaults );
    
    $where = array( "a.status = 'submitted'" );
    
    if ( $args['quiz_id'] ) {
        $where[] = $wpdb->prepare( "a.quiz_id = %d", $args['quiz_id'] );
    }
    
    if ( $args['user_id'] ) {
        $where[] = $wpdb->prepare( "a.user_id = %d", $args['user_id'] );
    }
    
    if ( $args['passed'] !== null ) {
        $where[] = $wpdb->prepare( "a.passed = %d", $args['passed'] );
    }
    
    if ( $args['date_from'] ) {
        $where[] = $wpdb->prepare( "a.finished_at >= %s", $args['date_from'] );
    }
    
    if ( $args['date_to'] ) {
        $where[] = $wpdb->prepare( "a.finished_at <= %s", $args['date_to'] );
    }
    
    if ( ! empty( $args['search'] ) ) {
        $search_like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
        $where[] = $wpdb->prepare(
            "(u.display_name LIKE %s OR u.user_email LIKE %s OR a.guest_email LIKE %s)",
            $search_like, $search_like, $search_like
        );
    }
    
    $where_sql = implode( ' AND ', $where );
    
    $offset = ( $args['page'] - 1 ) * $args['per_page'];
    
    $results = $wpdb->get_results( $wpdb->prepare(
        "SELECT 
            a.id,
            a.quiz_id,
            a.user_id,
            a.guest_email,
            a.score_points,
            a.score_percent,
            a.passed,
            a.started_at,
            a.finished_at,
            a.duration_seconds,
            q.title as quiz_title,
            COALESCE(u.display_name, a.guest_email, 'Guest') as student_name,
            u.user_email
         FROM {$wpdb->prefix}ppq_attempts a
         LEFT JOIN {$wpdb->prefix}ppq_quizzes q ON a.quiz_id = q.id
         LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
         WHERE {$where_sql}
         ORDER BY a.finished_at DESC
         LIMIT %d OFFSET %d",
        $args['per_page'],
        $offset
    ) );
    
    // Get total count
    $total = $wpdb->get_var(
        "SELECT COUNT(*) 
         FROM {$wpdb->prefix}ppq_attempts a
         LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
         WHERE {$where_sql}"
    );
    
    return array(
        'items'       => $results,
        'total'       => (int) $total,
        'total_pages' => ceil( $total / $args['per_page'] ),
        'page'        => $args['page'],
    );
}
```

### React Reports Page Structure

```
assets/js/admin/components/Reports/
├── index.jsx              # Main reports page container
├── OverviewCards.jsx      # Summary statistics cards
├── QuizPerformance.jsx    # Quiz performance table
├── RecentAttempts.jsx     # Recent attempts table
├── AttemptDetailModal.jsx # Individual attempt modal
├── DateRangePicker.jsx    # Date range selector
└── ReportsFilters.jsx     # Filter controls
```

## UI/UX Requirements

### Dashboard Widget Layout

```
+----------------------------------------+
|  PPQ Quiz Overview                 [x] |
+----------------------------------------+
|  +--------+  +--------+  +--------+    |
|  |   12   |  |  245   |  |   8    |    |
|  |Quizzes |  |Questions|  | Banks  |    |
|  +--------+  +--------+  +--------+    |
|                                        |
|  +--------+  +--------+                |
|  |   47   |  |  78%   |                |
|  |Attempts|  |Pass Rate|               |
|  |(7 days)|  |(7 days)|                |
|  +--------+  +--------+                |
|                                        |
|  Popular Quizzes                       |
|  • JavaScript Basics (23 attempts)     |
|  • HTML Fundamentals (18 attempts)     |
|  • CSS Layout (12 attempts)            |
|                                        |
|  [Create Quiz] [Add Question]          |
|  [View Reports] [Launch Onboarding]    |
+----------------------------------------+
```

### Reports Page Layout

```
+--------------------------------------------------+
|  Reports                                          |
+--------------------------------------------------+
|  Date Range: [Last 7 days ▼] [Custom...]          |
+--------------------------------------------------+
|                                                   |
|  +----------+  +----------+  +----------+  +------|
|  |   156    |  |   72%    |  |   65%    |  | 8:32 |
|  |  Total   |  |  Avg     |  |  Pass    |  | Avg  |
|  | Attempts |  |  Score   |  |  Rate    |  | Time |
|  +----------+  +----------+  +----------+  +------+
|                                                   |
+--------------------------------------------------+
|  Quiz Performance                                 |
|  [Search: ________________]                       |
+--------------------------------------------------+
|  Quiz Name        | Attempts | Avg | Pass | Time |
|  ---------------  | -------- | --- | ---- | ---- |
|  JavaScript Basics|    47    | 78% | 72%  | 12:34|
|  HTML Fundamentals|    38    | 85% | 84%  | 8:22 |
|  CSS Layout       |    29    | 71% | 65%  | 15:03|
|  [< 1 2 3 ... >]                                 |
+--------------------------------------------------+
|  Recent Attempts                                  |
|  Quiz: [All ▼] Status: [All ▼] [Search...]       |
+--------------------------------------------------+
|  Student     | Quiz       | Score | Status | Date |
|  ----------- | ---------- | ----- | ------ | ---- |
|  John Smith  | JavaScript |  85%  |   ✓    | Nov 20|
|  Jane Doe    | HTML       |  92%  |   ✓    | Nov 20|
|  Guest       | CSS        |  58%  |   ✗    | Nov 19|
|  [< 1 2 3 ... >]                                 |
+--------------------------------------------------+
```

### Attempt Detail Modal

```
+------------------------------------------+
|  Attempt Details                     [×] |
+------------------------------------------+
|  Student: John Smith                     |
|  Email: john@example.com                 |
|                                          |
|  Quiz: JavaScript Basics                 |
|  Score: 17/20 (85%)                      |
|  Status: ✓ Passed                        |
|  Time: 12:34                             |
|                                          |
|  Started: Nov 20, 2024 2:30 PM           |
|  Finished: Nov 20, 2024 2:42 PM          |
|                                          |
|  Questions:                              |
|  1. What is a closure?            ✓ 0:45 |
|  2. Explain hoisting...           ✗ 1:23 |
|  3. What does === do?             ✓ 0:32 |
|  ...                                     |
|                                          |
|  [View Full Results]              [Close]|
+------------------------------------------+
```

## Not In Scope (v1.0 Free)

These features are reserved for premium tiers:

- Attempts over time chart (visual trend)
- Score distribution histogram
- Category performance heatmap
- Question difficulty analysis
- CSV/PDF export of reports
- Scheduled email reports
- Comparative cohort analysis
- Learning curve visualization

## Database Requirements

No new tables required. Reports use existing tables:
- `wp_ppq_quizzes`
- `wp_ppq_questions`
- `wp_ppq_banks`
- `wp_ppq_attempts`
- `wp_ppq_attempt_items`

**Index Recommendations:**
Ensure these indexes exist for report query performance:
- `wp_ppq_attempts`: `(status, finished_at)`
- `wp_ppq_attempts`: `(quiz_id, status)`
- `wp_ppq_attempts`: `(user_id, status)`

## Testing Checklist

### Dashboard Widget
- [ ] Widget appears on WordPress dashboard
- [ ] Statistics display correctly
- [ ] Popular quizzes list updates
- [ ] Quick links navigate correctly
- [ ] Widget can be dismissed/collapsed
- [ ] Respects user capabilities

### Reports Page
- [ ] Page loads without errors
- [ ] Overview statistics are accurate
- [ ] Date range filter works
- [ ] Quiz performance table sorts correctly
- [ ] Recent attempts table filters work
- [ ] Search finds students by name/email
- [ ] Pagination works on both tables
- [ ] Attempt detail modal opens
- [ ] Modal shows correct data
- [ ] View Full Results link works
- [ ] Performance acceptable with 1000+ attempts

### Access Control
- [ ] Non-logged-in users cannot access
- [ ] Users without capabilities cannot access
- [ ] Teachers see only their content (if applicable)
- [ ] Administrators see all data
