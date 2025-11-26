# Feature 001: Question Management

## Overview

The question system is the foundation of PressPrimer Quiz. It supports three question types with comprehensive metadata, versioning, and organization features.

## User Stories

### As a Teacher (in version 2.0 of premium Tier 2)
- I want to create multiple choice questions so that I can assess student knowledge
- I want to add feedback for each answer option so students understand why answers are right or wrong
- I want to organize questions by category and tag so I can find them easily
- I want to set difficulty levels so I can create appropriately challenging quizzes
- I want to reuse questions across multiple quizzes so I don't duplicate work

### As an Administrator
- I want to see all questions in the system so I can manage content quality
- I want to bulk-edit question categories so I can reorganize content efficiently
- I want to delete questions while preserving historical data so past results remain accurate

## Question Types

### Multiple Choice (MC)
- Single correct answer from 2-8 options
- Radio button interface
- Full points if correct, zero if incorrect

### Multiple Answer (MA)
- Multiple correct answers from 2-8 options
- Checkbox interface
- Partial credit scoring: `(correct_selected - incorrect_selected) / total_correct * max_points`
- Score floors at 0 (no negative scores by default)

### True/False (TF)
- Two options only: True and False
- Radio button interface
- Full points if correct, zero if incorrect

## Acceptance Criteria

### Question Creation
- [ ] Can create question with 2-8 answer options
- [ ] Can set exactly one correct answer for MC/TF
- [ ] Can set multiple correct answers for MA
- [ ] Can add rich text (HTML) to question stem
- [ ] Can add rich text to answer options
- [ ] Can set per-question feedback (correct and incorrect)
- [ ] Can set per-answer feedback (why each option is right/wrong)
- [ ] Can set difficulty: Easy, Medium, Hard
- [ ] Can set expected completion time in seconds
- [ ] Can set max points (default 1.0)
- [ ] Can assign multiple categories
- [ ] Can assign multiple tags
- [ ] Can add to multiple question banks

### Question Editing
- [ ] Editing question content creates new revision
- [ ] Editing metadata does not create revision
- [ ] Previous revisions preserved and accessible
- [ ] Can view revision history
- [ ] Attempts lock to specific revision

### Question Organization
- [ ] Categories are hierarchical (parent/child)
- [ ] Tags are flat (no hierarchy)
- [ ] Can filter questions by category, tag, difficulty, type, author
- [ ] Can search questions by stem content
- [ ] Usage count shows how many quizzes use each question

### Question Operations
- [ ] Can duplicate question (creates new question with copy of current revision)
- [ ] Can soft-delete question (sets deleted_at, preserves for history)
- [ ] Deleted questions don't appear in lists but remain for past attempts

## Technical Implementation

### Database Tables

**wp_ppq_questions**
```sql
id BIGINT PRIMARY KEY
uuid CHAR(36) UNIQUE
author_id BIGINT
type ENUM('mc', 'ma', 'tf')
expected_seconds SMALLINT
difficulty_author ENUM('easy', 'medium', 'hard')
max_points DECIMAL(5,2) DEFAULT 1.00
status ENUM('draft', 'published', 'archived')
current_revision_id BIGINT
created_at DATETIME
updated_at DATETIME
deleted_at DATETIME NULL
```

**wp_ppq_question_revisions**
```sql
id BIGINT PRIMARY KEY
question_id BIGINT
version INT
stem TEXT
answers_json LONGTEXT
feedback_correct TEXT
feedback_incorrect TEXT
settings_json TEXT
content_hash CHAR(64)
created_at DATETIME
created_by BIGINT
```

### answers_json Structure
```json
[
    {
        "id": "a1",
        "text": "<p>Answer option with <strong>HTML</strong></p>",
        "is_correct": true,
        "feedback": "This is correct because...",
        "order": 1
    },
    {
        "id": "a2", 
        "text": "Plain text answer",
        "is_correct": false,
        "feedback": "This is incorrect because...",
        "order": 2
    }
]
```

### Answer ID Generation
- Use format: `a` + sequential number within question
- IDs are stable within a revision
- New revision may have different IDs

### Content Hash
- SHA-256 of normalized stem + answers
- Used to detect duplicate content
- Helps prevent accidental re-creation

## UI/UX Requirements

### Question List (Admin)
- Table with sortable columns
- Columns: Checkbox, ID, Question (truncated), Type, Difficulty, Categories, Banks, Author, Date
- Hover shows full question text
- Row actions: Edit | Duplicate | Delete
- Bulk actions: Delete, Move to Category
- Filters above table: Type, Difficulty, Category, Author
- Search box searches stem content

### Question Editor (Admin)
- Full-width editor layout
- Left column: Question content
- Right column: Metadata and taxonomies

**Content Section:**
- Type selector (radio buttons with icons)
- Stem editor (TinyMCE/wp_editor)
- Answer options:
  - Add/remove buttons
  - Drag handles for reorder
  - Text input per option
  - Correct checkbox/radio
  - Expand for feedback
- Question feedback:
  - Correct feedback textarea
  - Incorrect feedback textarea

**Metadata Section:**
- Difficulty dropdown
- Expected time (number input, seconds)
- Max points (number input)
- Categories (hierarchical checkbox list)
- Tags (tag input with autocomplete)
- Question Banks (multi-select)

**Actions:**
- Save Draft
- Publish
- Preview (modal)

## Validation Rules

### Required Fields
- Stem: non-empty after stripping HTML
- At least 2 answer options
- At least 1 correct answer
- For TF: exactly 2 options

### Field Limits
- Stem: max 10,000 characters
- Answer text: max 2,000 characters per option
- Feedback: max 2,000 characters each
- Max 8 answer options
- Expected time: 1-3600 seconds
- Max points: 0.01-1000.00

### Content Rules
- Stem must not be only whitespace
- Each answer option must have non-empty text
- MC/TF must have exactly 1 correct answer
- MA must have at least 1 correct answer

## Security Considerations

- Correct answers never exposed in frontend HTML
- Only question authors and admins can edit
- Capability check: `ppq_manage_own` for own questions, `ppq_manage_all` for all
- All input sanitized with `wp_kses_post` for rich text
- All output escaped with `esc_html`, `esc_attr`, etc.

## Edge Cases

1. **Question with no correct answer** - Validation prevents this
2. **Question with all correct answers (MA)** - Allowed, full credit for selecting all
3. **Empty answer option** - Validation prevents this
4. **Duplicate question content** - Allowed but hash can warn
5. **Question in use deleted** - Soft delete, remains for past attempts
6. **Category deleted with questions** - Questions moved to uncategorized

## Not In Scope (v1.0)

- Image/media in questions (v2.0+)
- Math/LaTeX rendering (Educator tier)
- Question import/export (Educator tier)
- AI distractor generation (Educator tier)
- Question quality metrics (Institution tier)
- Shared question banks (School tier)

## Testing Checklist

- [ ] Create MC question with 4 options
- [ ] Create MA question with 3 correct answers
- [ ] Create TF question
- [ ] Edit question and verify new revision created
- [ ] Verify old revision preserved
- [ ] Add per-answer feedback
- [ ] Assign to 2 categories and 3 tags
- [ ] Add to question bank
- [ ] Search for question by keyword
- [ ] Filter by difficulty
- [ ] Duplicate question
- [ ] Delete question (verify soft delete)
- [ ] Verify validation errors for invalid input

