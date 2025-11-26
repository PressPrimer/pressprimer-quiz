# Database Architecture

## Design Philosophy

PressPrimer Quiz uses custom database tables rather than WordPress Custom Post Types. This decision enables:

1. **Performance at scale** - Optimized queries for 10,000+ questions and 100,000+ attempts
2. **Complex queries** - Category breakdowns, analytics, item analysis
3. **Immutable versioning** - Question snapshots for historical accuracy
4. **Precise data structures** - Exact fields we need, no metadata overhead
5. **Future-proof** - Ready for advanced features (psychometrics, xAPI, spaced repetition)

## Table Prefix

All tables use the prefix: `wp_ppq_` (While some WP sites may have a wp_ prefix, we need to support those that don't; here and in all other documentation, make sure other table prefixes are supported if a WP site uses a different prefix. The use of "wp_" here is as an example only.)

Full table name example: `wp_ppq_questions`

## Schema Overview

```
wp_ppq_questions          # Primary question storage
wp_ppq_question_revisions # Immutable question snapshots
wp_ppq_categories         # Categories and tags
wp_ppq_question_tax       # Question-to-category relationships
wp_ppq_banks              # Question bank definitions
wp_ppq_bank_questions     # Bank-to-question relationships
wp_ppq_quizzes            # Quiz configurations
wp_ppq_quiz_items         # Quiz-to-question relationships (fixed quizzes)
wp_ppq_quiz_rules         # Dynamic quiz generation rules
wp_ppq_groups             # User groups
wp_ppq_group_members      # Group membership
wp_ppq_assignments        # Quiz assignments to groups/users
wp_ppq_attempts           # Quiz attempt records
wp_ppq_attempt_items      # Individual question responses
wp_ppq_events             # Event log for proctoring/xAPI
```

---

## Table Definitions

### wp_ppq_questions

Primary question storage. Contains metadata; actual content is in revisions.

```sql
CREATE TABLE wp_ppq_questions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    type ENUM('mc', 'ma', 'tf') NOT NULL DEFAULT 'mc',
    expected_seconds SMALLINT UNSIGNED DEFAULT NULL,
    difficulty_author ENUM('easy', 'medium', 'hard') DEFAULT NULL,
    max_points DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'published',
    current_revision_id BIGINT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uuid (uuid),
    KEY author_id (author_id),
    KEY type (type),
    KEY status (status),
    KEY difficulty_author (difficulty_author),
    KEY created_at (created_at),
    KEY deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Field Notes:**
- `uuid` - For external references (xAPI, exports)
- `type` - mc=Multiple Choice, ma=Multiple Answer, tf=True/False
- `expected_seconds` - Author's estimate of time to answer
- `difficulty_author` - Author's assessment (not calculated)
- `max_points` - Maximum points for this question
- `current_revision_id` - Points to latest revision
- `deleted_at` - Soft delete support

---

### wp_ppq_question_revisions

Immutable snapshots of questions. Every edit creates a new revision.

```sql
CREATE TABLE wp_ppq_question_revisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    question_id BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    stem TEXT NOT NULL,
    answers_json LONGTEXT NOT NULL,
    feedback_correct TEXT DEFAULT NULL,
    feedback_incorrect TEXT DEFAULT NULL,
    settings_json TEXT DEFAULT NULL,
    content_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY question_version (question_id, version),
    KEY content_hash (content_hash),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Field Notes:**
- `version` - Incrementing version number per question
- `stem` - The question text (HTML allowed)
- `answers_json` - JSON array of answer options; needs to support full HTML
- `content_hash` - SHA-256 of stem+answers for deduplication
- Revisions are never updated, only inserted

**answers_json Structure:**
```json
[
    {
        "id": "a1",
        "text": "Answer option text",
        "is_correct": true,
        "feedback": "Why this is correct",
        "order": 1
    },
    {
        "id": "a2",
        "text": "Another option",
        "is_correct": false,
        "feedback": "Why this is wrong",
        "order": 2
    }
]
```

---

### wp_ppq_categories

Categories and tags for questions and quizzes.

```sql
CREATE TABLE wp_ppq_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    parent_id BIGINT UNSIGNED DEFAULT NULL,
    taxonomy ENUM('category', 'tag') NOT NULL DEFAULT 'category',
    question_count INT UNSIGNED NOT NULL DEFAULT 0,
    quiz_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug_taxonomy (slug, taxonomy),
    KEY parent_id (parent_id),
    KEY taxonomy (taxonomy),
    KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Field Notes:**
- `taxonomy` - Either 'category' (hierarchical) or 'tag' (flat)
- `parent_id` - For hierarchical categories only
- Counts maintained via triggers or update queries

---

### wp_ppq_question_tax

Many-to-many relationship between questions and categories/tags.

```sql
CREATE TABLE wp_ppq_question_tax (
    question_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (question_id, category_id),
    KEY category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### wp_ppq_banks

Question bank definitions.

```sql
CREATE TABLE wp_ppq_banks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    owner_id BIGINT UNSIGNED NOT NULL,
    visibility ENUM('private', 'shared') NOT NULL DEFAULT 'private',
    question_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uuid (uuid),
    KEY owner_id (owner_id),
    KEY visibility (visibility),
    KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Field Notes:**
- `visibility` - 'private' = owner only, 'shared' = visible to other teachers
- In v1.0 Free, all banks are 'private'. Shared banks are a premium feature.

---

### wp_ppq_bank_questions

Many-to-many relationship between banks and questions.

```sql
CREATE TABLE wp_ppq_bank_questions (
    bank_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (bank_id, question_id),
    KEY question_id (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### wp_ppq_quizzes

Quiz configuration and settings.

```sql
CREATE TABLE wp_ppq_quizzes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    featured_image_id BIGINT UNSIGNED DEFAULT NULL,
    owner_id BIGINT UNSIGNED NOT NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    
    -- Quiz behavior
    mode ENUM('tutorial', 'timed') NOT NULL DEFAULT 'tutorial',
    time_limit_seconds INT UNSIGNED DEFAULT NULL,
    pass_percent DECIMAL(5,2) NOT NULL DEFAULT 70.00,
    
    -- Navigation
    allow_skip TINYINT(1) NOT NULL DEFAULT 1,
    allow_backward TINYINT(1) NOT NULL DEFAULT 1,
    allow_resume TINYINT(1) NOT NULL DEFAULT 1,
    
    -- Attempts
    max_attempts INT UNSIGNED DEFAULT NULL,
    attempt_delay_minutes INT UNSIGNED DEFAULT NULL,
    
    -- Display
    randomize_questions TINYINT(1) NOT NULL DEFAULT 0,
    randomize_answers TINYINT(1) NOT NULL DEFAULT 0,
    page_mode ENUM('single', 'paged') NOT NULL DEFAULT 'single',
    questions_per_page TINYINT UNSIGNED DEFAULT 1,
    show_answers ENUM('never', 'after_submit', 'after_pass') NOT NULL DEFAULT 'after_submit',
    
    -- Features
    enable_confidence TINYINT(1) NOT NULL DEFAULT 0,
    
    -- Theme
    theme VARCHAR(50) NOT NULL DEFAULT 'default',
    theme_settings_json TEXT DEFAULT NULL,
    
    -- Feedback
    band_feedback_json TEXT DEFAULT NULL,
    
    -- Generation mode
    generation_mode ENUM('fixed', 'dynamic') NOT NULL DEFAULT 'fixed',
    
    -- Metadata
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY uuid (uuid),
    KEY owner_id (owner_id),
    KEY status (status),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Field Notes:**
- `mode` - 'tutorial' shows feedback immediately, 'timed' shows at end
- `generation_mode` - 'fixed' uses quiz_items, 'dynamic' uses quiz_rules
- `band_feedback_json` - Score-banded feedback messages

**band_feedback_json Structure:**
```json
[
    {"min": 0, "max": 59, "message": "Keep practicing!"},
    {"min": 60, "max": 79, "message": "Good effort!"},
    {"min": 80, "max": 100, "message": "Excellent work!"}
]
```

---

### wp_ppq_quiz_items

Fixed questions for quizzes (when generation_mode = 'fixed').

```sql
CREATE TABLE wp_ppq_quiz_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quiz_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    order_index SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    PRIMARY KEY (id),
    UNIQUE KEY quiz_question (quiz_id, question_id),
    KEY question_id (question_id),
    KEY order_index (quiz_id, order_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### wp_ppq_quiz_rules

Dynamic question selection rules (when generation_mode = 'dynamic').

```sql
CREATE TABLE wp_ppq_quiz_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quiz_id BIGINT UNSIGNED NOT NULL,
    rule_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bank_id BIGINT UNSIGNED DEFAULT NULL,
    category_ids_json TEXT DEFAULT NULL,
    tag_ids_json TEXT DEFAULT NULL,
    difficulties_json TEXT DEFAULT NULL,
    question_count SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    PRIMARY KEY (id),
    KEY quiz_id (quiz_id),
    KEY bank_id (bank_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Field Notes:**
- Each rule pulls N questions matching criteria
- Rules are processed in `rule_order` sequence
- NULL values mean "any" (e.g., NULL category_ids = all categories)

**Example Rule:**
"Pull 20 medium-difficulty questions from Bank 5, categories 1 and 2"

---

### wp_ppq_groups

User groups for organizing students and teachers.

```sql
CREATE TABLE wp_ppq_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    owner_id BIGINT UNSIGNED NOT NULL,
    member_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uuid (uuid),
    KEY owner_id (owner_id),
    KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### wp_ppq_group_members

Group membership.

```sql
CREATE TABLE wp_ppq_group_members (
    group_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('teacher', 'student') NOT NULL DEFAULT 'student',
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    added_by BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (group_id, user_id),
    KEY user_id (user_id),
    KEY role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### wp_ppq_assignments

Quiz assignments to groups or individual users.

```sql
CREATE TABLE wp_ppq_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quiz_id BIGINT UNSIGNED NOT NULL,
    assignee_type ENUM('group', 'user') NOT NULL,
    assignee_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NOT NULL,
    due_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY quiz_assignee (quiz_id, assignee_type, assignee_id),
    KEY assignee (assignee_type, assignee_id),
    KEY due_at (due_at),
    KEY assigned_by (assigned_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### wp_ppq_attempts

Quiz attempt records.

```sql
CREATE TABLE wp_ppq_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    quiz_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    guest_email VARCHAR(100) DEFAULT NULL,
    guest_token CHAR(64) DEFAULT NULL,
    
    -- Timing
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME DEFAULT NULL,
    elapsed_ms INT UNSIGNED DEFAULT NULL,
    
    -- Scoring
    score_points DECIMAL(10,2) DEFAULT NULL,
    max_points DECIMAL(10,2) DEFAULT NULL,
    score_percent DECIMAL(5,2) DEFAULT NULL,
    passed TINYINT(1) DEFAULT NULL,
    
    -- State
    status ENUM('in_progress', 'submitted', 'abandoned') NOT NULL DEFAULT 'in_progress',
    current_position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    
    -- Questions for this attempt (snapshot)
    questions_json LONGTEXT NOT NULL,
    
    -- Metadata
    meta_json TEXT DEFAULT NULL,
    
    PRIMARY KEY (id),
    UNIQUE KEY uuid (uuid),
    KEY quiz_id (quiz_id),
    KEY user_id (user_id),
    KEY guest_email (guest_email),
    KEY guest_token (guest_token),
    KEY status (status),
    KEY started_at (started_at),
    KEY finished_at (finished_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Field Notes:**
- `user_id` - For logged-in users; NULL for guests
- `guest_token` - Secure token for guest session/resume
- `questions_json` - Array of question_revision_ids for this attempt
- `meta_json` - Device info, user agent, IP, etc.

**questions_json Structure:**
```json
[
    {"revision_id": 123, "order": 1},
    {"revision_id": 456, "order": 2},
    {"revision_id": 789, "order": 3}
]
```

---

### wp_ppq_attempt_items

Individual question responses within an attempt.

```sql
CREATE TABLE wp_ppq_attempt_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id BIGINT UNSIGNED NOT NULL,
    question_revision_id BIGINT UNSIGNED NOT NULL,
    order_index SMALLINT UNSIGNED NOT NULL,
    
    -- Response
    selected_answers_json TEXT DEFAULT NULL,
    
    -- Timing
    first_view_at DATETIME DEFAULT NULL,
    last_answer_at DATETIME DEFAULT NULL,
    time_spent_ms INT UNSIGNED DEFAULT NULL,
    
    -- Scoring
    is_correct TINYINT(1) DEFAULT NULL,
    score_points DECIMAL(5,2) DEFAULT NULL,
    
    -- Features
    confidence TINYINT(1) DEFAULT NULL,
    
    PRIMARY KEY (id),
    UNIQUE KEY attempt_revision (attempt_id, question_revision_id),
    KEY question_revision_id (question_revision_id),
    KEY is_correct (is_correct)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Field Notes:**
- `selected_answers_json` - Array of selected answer IDs
- `confidence` - 1 if user marked "confident", 0 or NULL otherwise
- `is_correct` and `score_points` calculated on submission

---

### wp_ppq_events

Event log for future proctoring and xAPI.

```sql
CREATE TABLE wp_ppq_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attempt_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    event_type VARCHAR(50) NOT NULL,
    payload_json TEXT DEFAULT NULL,
    created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    PRIMARY KEY (id),
    KEY attempt_id (attempt_id),
    KEY event_type (event_type),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Event Types:**
- `quiz_started` - Quiz attempt began
- `question_viewed` - Question first displayed
- `answer_selected` - Answer chosen (not necessarily submitted)
- `answer_saved` - Answer saved to server
- `quiz_submitted` - Quiz submitted
- `timer_warning` - Timer hit 5/1 minute marks
- `focus_blur` - Browser tab lost focus
- `focus_return` - Browser tab regained focus

---

## Indexes Strategy

All tables include indexes for:
1. Primary keys (automatic)
2. Foreign key relationships
3. Common query patterns (status, date ranges, owner)
4. Search fields (name, slug)

Additional indexes should be added based on actual query patterns after launch.

---

## Migration Strategy

Use a version-based migration system:

```php
function ppq_maybe_run_migrations() {
    $current_version = get_option( 'ppq_db_version', '0' );
    $target_version = PPQ_DB_VERSION;
    
    if ( version_compare( $current_version, $target_version, '<' ) ) {
        ppq_run_migrations( $current_version, $target_version );
        update_option( 'ppq_db_version', $target_version );
    }
}

function ppq_run_migrations( $from, $to ) {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
    // Get schema SQL
    $sql = ppq_get_schema_sql();
    
    // Run dbDelta
    dbDelta( $sql );
    
    // Run any data migrations
    if ( version_compare( $from, '1.1.0', '<' ) ) {
        ppq_migrate_1_1_0();
    }
}
```

---

## Query Patterns

### Get Questions for Dynamic Quiz

```php
function ppq_get_questions_for_rule( $rule ) {
    global $wpdb;
    
    $where = ['q.deleted_at IS NULL', 'q.status = "published"'];
    $params = [];
    
    if ( $rule->bank_id ) {
        $where[] = 'bq.bank_id = %d';
        $params[] = $rule->bank_id;
    }
    
    if ( $rule->difficulties_json ) {
        $difficulties = json_decode( $rule->difficulties_json, true );
        $placeholders = implode( ',', array_fill( 0, count( $difficulties ), '%s' ) );
        $where[] = "q.difficulty_author IN ($placeholders)";
        $params = array_merge( $params, $difficulties );
    }
    
    // Categories handled via JOIN
    
    $sql = "SELECT q.id FROM {$wpdb->prefix}ppq_questions q
            LEFT JOIN {$wpdb->prefix}ppq_bank_questions bq ON q.id = bq.question_id
            WHERE " . implode( ' AND ', $where ) . "
            ORDER BY RAND()
            LIMIT %d";
    
    $params[] = $rule->question_count;
    
    return $wpdb->get_col( $wpdb->prepare( $sql, $params ) );
}
```

### Get Attempt with Items

```php
function ppq_get_attempt_with_items( $attempt_id ) {
    global $wpdb;
    
    $attempt = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}ppq_attempts WHERE id = %d",
        $attempt_id
    ) );
    
    if ( ! $attempt ) {
        return null;
    }
    
    $attempt->items = $wpdb->get_results( $wpdb->prepare(
        "SELECT ai.*, qr.stem, qr.answers_json, qr.feedback_correct, qr.feedback_incorrect
         FROM {$wpdb->prefix}ppq_attempt_items ai
         JOIN {$wpdb->prefix}ppq_question_revisions qr ON ai.question_revision_id = qr.id
         WHERE ai.attempt_id = %d
         ORDER BY ai.order_index ASC",
        $attempt_id
    ) );
    
    return $attempt;
}
```

---

## Data Integrity Notes

1. **Soft deletes** - Questions use `deleted_at` to preserve history
2. **Immutable revisions** - Never update revision rows; create new ones
3. **Attempt snapshots** - `questions_json` captures exact questions at attempt time
4. **Foreign keys** - Not enforced by MySQL in WordPress, but documented in schema
5. **Counts** - `question_count`, `member_count` maintained via code, not triggers

