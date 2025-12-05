# Feature 003: Quiz Builder

## Overview

The quiz builder allows administrators (and later teachers in version 2.0) to create and configure quizzes with extensive customization options. Quizzes can use fixed questions (same every time) or dynamic generation (different questions each attempt based on rules).

## User Stories

### As a Teacher
- I want to create quizzes with specific questions so I can control exactly what's assessed
- I want to create quizzes that randomly select from my banks so each student gets different questions
- I want to set time limits so students complete work promptly
- I want to configure passing scores so students know the standard
- I want to limit attempts so quizzes can't be retaken indefinitely
- I want to customize feedback messages based on score ranges

### As an Administrator
- I want to set default quiz settings so teachers have sensible starting points
- I want to preview any quiz so I can review content quality
- I want to be able to perform all functions listed above for teachers

## Quiz Generation Modes

### Fixed Mode
- Specific questions added directly to quiz
- Same questions every attempt
- Can set custom order
- Can set point weight per question

### Dynamic Mode
- Rules specify how to select questions
- Different questions each attempt (from pool)
- Rules specify: bank, categories, tags, difficulties, count
- Multiple rules combine to form complete quiz

## Acceptance Criteria

### Quiz Creation
- [ ] Can set title and description
- [ ] Can add featured image
- [ ] Can assign categories and tags
- [ ] Can set quiz mode: Tutorial or Timed
- [ ] Can set time limit (or no limit)
- [ ] Can set passing percentage
- [ ] Can configure navigation options
- [ ] Can configure attempt limits
- [ ] Can select visual theme

### Fixed Quiz
- [ ] Can add questions from search/browse
- [ ] Can reorder questions via drag-and-drop
- [ ] Can set point weight per question
- [ ] Can remove questions
- [ ] Shows total question count and points

### Dynamic Quiz
- [ ] Can add multiple rules
- [ ] Each rule specifies selection criteria
- [ ] Can reorder rules
- [ ] Shows preview of matching question count per rule
- [ ] Shows total expected questions

### Quiz Settings

**Mode:**
- Tutorial: Immediate feedback after each question
- Timed: Feedback only after submission

**Navigation:**
- Allow Skip: Can leave questions unanswered
- Allow Backward: Can return to previous questions
- Allow Resume: Can save and continue later

**Attempts:**
- Max Attempts: Unlimited or specific number
- Delay Between: Minutes required between attempts

**Display:**
- Randomize Questions: Shuffle question order
- Randomize Answers: Shuffle answer options
- Page Mode: Single page (all questions) or Paginated
- Show Answers: Never / After Submit / After Pass

**Features:**
- Enable Confidence: Show confidence checkbox

### Feedback Configuration
- [ ] Can add score bands (e.g., 0-59, 60-79, 80-100)
- [ ] Each band has custom message
- [ ] Bands must cover 0-100 without gaps
- [ ] Bands must not overlap

## Technical Implementation

### Database Tables

**wp_ppq_quizzes**
```sql
id BIGINT PRIMARY KEY
uuid CHAR(36) UNIQUE
title VARCHAR(200)
description TEXT
featured_image_id BIGINT
owner_id BIGINT
status ENUM('draft', 'published', 'archived')

-- Behavior
mode ENUM('tutorial', 'timed')
time_limit_seconds INT NULL
pass_percent DECIMAL(5,2) DEFAULT 70.00

-- Navigation
allow_skip TINYINT(1) DEFAULT 1
allow_backward TINYINT(1) DEFAULT 1
allow_resume TINYINT(1) DEFAULT 1

-- Attempts
max_attempts INT NULL
attempt_delay_minutes INT NULL

-- Display
randomize_questions TINYINT(1) DEFAULT 0
randomize_answers TINYINT(1) DEFAULT 0
page_mode ENUM('single', 'paged') DEFAULT 'single'
questions_per_page TINYINT DEFAULT 1
show_answers ENUM('never', 'after_submit', 'after_pass') DEFAULT 'after_submit'

-- Features
enable_confidence TINYINT(1) DEFAULT 0

-- Theme
theme VARCHAR(50) DEFAULT 'default'
theme_settings_json TEXT

-- Feedback
band_feedback_json TEXT

-- Generation
generation_mode ENUM('fixed', 'dynamic')

created_at DATETIME
updated_at DATETIME
```

**wp_ppq_quiz_items** (Fixed Mode)
```sql
id BIGINT PRIMARY KEY
quiz_id BIGINT
question_id BIGINT
order_index SMALLINT
weight DECIMAL(5,2) DEFAULT 1.00
```

**wp_ppq_quiz_rules** (Dynamic Mode)
```sql
id BIGINT PRIMARY KEY
quiz_id BIGINT
rule_order SMALLINT
bank_id BIGINT NULL
category_ids_json TEXT
tag_ids_json TEXT
difficulties_json TEXT
question_count SMALLINT
```

### band_feedback_json Structure
```json
[
    {"min": 0, "max": 59, "message": "<p>Keep practicing!</p>"},
    {"min": 60, "max": 79, "message": "<p>Good effort!</p>"},
    {"min": 80, "max": 100, "message": "<p>Excellent!</p>"}
]
```

### Question Generation (Dynamic Mode)

```php
function ppq_generate_questions_for_attempt( $quiz_id ) {
    $quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
    $rules = PressPrimer_Quiz_Quiz_Rule::get_for_quiz( $quiz_id );
    
    $all_question_ids = [];
    
    foreach ( $rules as $rule ) {
        $question_ids = $rule->get_matching_questions();
        
        // Shuffle and take requested count
        shuffle( $question_ids );
        $selected = array_slice( $question_ids, 0, $rule->question_count );
        
        $all_question_ids = array_merge( $all_question_ids, $selected );
    }
    
    // Remove duplicates (if same question matched multiple rules)
    $all_question_ids = array_unique( $all_question_ids );
    
    // Randomize if enabled
    if ( $quiz->randomize_questions ) {
        shuffle( $all_question_ids );
    }
    
    return $all_question_ids;
}
```

## UI/UX Requirements

### Quiz List (Admin)
- Table: Title, Questions, Mode, Status, Author, Date
- Row actions: Edit | Preview | Duplicate | Delete
- Bulk actions: Publish, Draft, Delete
- Filters: Status, Mode, Author
- Search by title

### Quiz Editor

**Layout:**
- Tabbed interface or accordion
- Tabs: Content, Questions, Settings, Feedback, Assignment

**Content Tab:**
- Title (required)
- Description (wp_editor)
- Featured Image selector
- Categories (checkbox list)
- Tags (tag input)

**Questions Tab (Fixed Mode):**
- Table of added questions
- Drag handles for reorder
- Point weight input per question
- Remove button
- "Add Questions" button → modal/sidebar
- Total questions and points display

**Questions Tab (Dynamic Mode):**
- List of rules
- Each rule card shows:
  - Source bank selector
  - Category multi-select
  - Tag multi-select
  - Difficulty checkboxes
  - Question count input
  - Matching count indicator
- Add/remove rule buttons
- Reorder via drag
- Total expected questions

**Settings Tab:**
- Mode: Tutorial/Timed toggle
- Time Limit: Number input or "No limit"
- Passing Score: Percentage slider/input
- Navigation checkboxes
- Attempt controls
- Display options
- Theme selector with preview

**Feedback Tab:**
- Band editor
- Add band button
- Each band: min, max, message editor
- Validation indicator

**Assignment Tab:**
- (See Feature 007)

### Add Questions Modal
- Search/filter interface
- Category, Tag, Difficulty, Bank filters
- Checkbox selection
- Preview on hover
- "Add Selected" button
- Already-added indicator

## Validation Rules

### Required
- Title: non-empty, max 200 characters
- At least 1 question (fixed) or 1 rule (dynamic)

### Settings
- Time limit: 60-86400 seconds if set
- Pass percent: 0-100
- Max attempts: 1-100 if set
- Delay: 0-10080 minutes (1 week)

### Feedback Bands
- Must have at least one band
- Min must be < max
- Bands must not overlap
- Bands should cover 0-100 (warn if gaps)

### Dynamic Rules
- Question count: 1-500 per rule
- At least one filter should be set (or warn about "all questions")

## Edge Cases

1. **No matching questions for rule** - Show warning, attempt fails if 0 questions
2. **Fewer questions than requested** - Use all available, proceed with warning
3. **Same question matches multiple rules** - Deduplicate, only shown once
4. **All questions removed from fixed quiz** - Validation error on save
5. **Bank deleted after rule created** - Rule becomes invalid, show warning
6. **Time limit with allow_resume** - Valid combination, timer pauses on save

## Not In Scope (v1.0)

- Branching logic (Enterprise tier)
- Question pools with weights (Educator tier)
- Per-question time limits (Educator tier)
- Quiz templates
- Quiz scheduling (start/end dates)
- Prerequisite quizzes

## Testing Checklist

- [ ] Create fixed quiz with 10 questions
- [ ] Reorder questions in fixed quiz
- [ ] Set point weights
- [ ] Create dynamic quiz with 3 rules
- [ ] Verify matching count accurate
- [ ] Configure all settings
- [ ] Add score-banded feedback
- [ ] Preview quiz (admin preview)
- [ ] Duplicate quiz
- [ ] Publish and verify available
- [ ] Verify dynamic quiz generates different questions

