# PressPrimer Quiz v3.0 Free - Context Overview

> **Purpose:** This is a context document for AI-assisted development. It provides high-level scope for v3.0 Free without detailed requirements. Detailed specifications will be created closer to development.

> **Target Release:** 4 months after v2.0 launch (6 months after v1.0)  
> **Coincides With:** Premium addon tier 2.0 releases

---

## Version Theme

**"Advanced Question Features & Student Empowerment"**

Version 3.0 elevates the question-authoring experience with advanced behaviors, student-created quizzes, and self-assessment tools. This version positions free users to get serious value while creating clear differentiation for premium learning science features.

---

## New Free Features in v3.0

### Advanced Question Behaviors
- **Answer Shuffling:** Per-question option to randomize answer order on each attempt
- **"Select All That Apply" Display:** Checkbox UI variation for multiple answer questions
- **Answer Elimination:** Optional UI allowing students to cross out answers they've eliminated
- **Partial Credit for Multiple Answer:** Configurable scoring (all-or-nothing vs partial)

### Student Quiz Creation (Basic)
- **Front-End Quiz Builder:** Students can create quizzes from existing question bank
- **Personal Quizzes Only:** Student-created quizzes visible only to creator
- **Limited to Own Questions:** Students use questions they've seen or created
- **Self-Study Focus:** Designed for personal review, not sharing

### Enhanced Self-Assessment
- **Quiz Retakes:** Configurable retake policy per quiz (unlimited, limited, cooldown)
- **Personal Progress View:** Students see their own attempt history
- **Question History:** "You've answered this question X times" indicator
- **Improvement Tracking:** Personal score trend visualization



---

## Features NOT in v3.0 Free (Premium Tier)

These features require premium addons:

| Feature | Premium Tier |
|---------|--------------|
| CSV/JSON import/export | Educator |
| AI distractor generation | Educator |
| Spaced repetition scheduling | Educator |
| Shared student quizzes | School |
| Institutional question banks | School |
| Nested group hierarchies | Institution |
| QTI/GIFT import formats | Institution |
| Psychometric analysis | Institution |
| Question performance analysis | Institution |
| Kirkpatrick assessment tools | Enterprise |
| Multi-tenant deployment | Enterprise |

---

## Database Implications for v2.0

v2.0 must include database structures to support v3.0 features:

### Fields to Add in v2.0 (for v3.0 use)
```sql
-- Question behavior settings (JSON in ppq_questions)
-- Add to existing question settings:
{
    "shuffle_answers": false,
    "show_checkboxes": false,
    "allow_elimination": false,
    "partial_credit_method": "all_or_nothing" // or "proportional", "right_minus_wrong"
}

-- Quiz retake settings (JSON in ppq_quizzes)
-- Add to existing quiz settings:
{
    "retake_policy": "unlimited",  // "none", "limited", "cooldown"
    "max_retakes": null,
    "cooldown_hours": null
}

-- Student quiz ownership
ppq_student_quizzes (
    id BIGINT PRIMARY KEY,
    quiz_id BIGINT,
    owner_id BIGINT,
    visibility ENUM('private', 'shared'),  -- 'shared' requires School tier
    created_at DATETIME
)
```

---

## Hooks to Expose in v2.0 (for v3.0)

```php
// Question behavior hooks
apply_filters('pressprimer_quiz_shuffle_answers', $shuffle, $question_id, $attempt_id);
apply_filters('pressprimer_quiz_partial_credit_score', $score, $question_id, $selected, $correct);

// Student quiz creation hooks
do_action('pressprimer_quiz_student_quiz_created', $quiz_id, $owner_id);
apply_filters('pressprimer_quiz_student_can_create_quiz', $can_create, $user_id);

// Retake policy hooks
apply_filters('pressprimer_quiz_user_can_retake', $can_retake, $quiz_id, $user_id, $attempt_count);
do_action('pressprimer_quiz_retake_blocked', $quiz_id, $user_id, $reason);
```

---

## UI Changes from v2.0

### Question Builder Updates
- Answer shuffling toggle
- Checkbox display option for multiple answer
- Partial credit configuration
- Elimination mode toggle

### Quiz Builder Updates
- Retake policy panel
- Student creation settings (if enabling student quizzes)

### Student Dashboard Updates
- "My Quizzes" section (if student quiz creation enabled)
- Personal progress charts
- Question history indicators during quiz-taking

---

## Success Metrics for v3.0

- Student quiz creation adoption rate
- Retake feature usage
- Advanced question behavior adoption
- Premium conversion from power users

---

## Development Notes

- Answer elimination must not leak correct answers client-side
- Partial credit calculations must be server-side
- Student quiz creation should feel empowering, not restrictive

---

## Architecture Considerations

### Performance
- Student progress queries can be expensive - consider denormalization

### Security
- Student-created quizzes must be sandboxed
- Front-end quiz builder must validate all inputs server-side

### Data Integrity
- Retake counting must be race-condition safe

---

## Migration Considerations

### From v2.0 to v3.0
- Existing quiz settings extended with new defaults
- No breaking changes to existing workflows

### Data Defaults
- Shuffle answers: OFF (preserve v2.0 behavior)
- Elimination mode: OFF
- Retake policy: matches v2.0 setting
- Student quiz creation: OFF (admin opt-in)
