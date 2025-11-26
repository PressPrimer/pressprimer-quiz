# PressPrimer Quiz v2.0 Free - Context Overview

> **Purpose:** This is a context document for AI-assisted development. It provides high-level scope for v2.0 Free without detailed requirements. Detailed specifications will be created closer to development.

> **Target Release:** 2 months after v1.0 launch  
> **Coincides With:** Premium addon tier 1.0 releases (Educator, School, Enterprise)

---

## Version Theme

**"Groups, Assignments & Learning Science"**

Version 2.0 transforms PressPrimer Quiz from a standalone quiz tool into a classroom/training management system with native group organization, assignment workflows, and learning science features that justify the premium tier upsells.

---

## New Free Features in v2.0

### Groups System
- **Native Groups:** Create and manage learner groups within PressPrimer
- **PressPrimer Teacher Role:** Existing WordPress role from v1.0 can now manage their own groups
- **Flat Structure:** Single-level groups (no nesting - nested groups are School tier)
- **Group Membership:** Manual user assignment to groups
- **Group Visibility:** Teachers see only their own groups; admins see all

### Assignment System
- **Quiz Assignments:** Assign quizzes to groups with due dates
- **Assignment Dashboard:** Teachers see pending/completed assignments for their groups
- **Due Date Tracking:** Visual indicators for upcoming/overdue assignments
- **Student View:** Learners see their assigned quizzes with due dates

### Pre/Post Test Comparison (Basic)
- **Link Quizzes:** Mark two quizzes as pre-test and post-test pair
- **Basic Comparison Report:** Side-by-side score comparison for individual students
- **Improvement Indicator:** Simple percentage point change display
- **Per-Question Comparison:** See which questions improved/declined

### LearnPress Integration
- **New LMS Integration:** Support for LearnPress in addition to LearnDash, TutorLMS, LifterLMS
- **Same Integration Pattern:** Course/lesson embedding, grade passback, user sync

### Enhanced Reporting
- **Group Reports:** Aggregate performance by group
- **Assignment Completion Rates:** Track assignment submission status
- **Time-Based Filtering:** Filter reports by date range

---

## Features NOT in v2.0 Free (Premium Tier)

These features require premium addons:

| Feature | Premium Tier |
|---------|--------------|
| Nested group hierarchies | School |
| Survey/ungraded questions | Educator |
| Confidence ratings UI | Educator |
| Advanced pre/post analysis | School |
| Progress over attempts | School |
| Observational assessments | School |
| xAPI/LRS output | Institution |
| Branching/conditional logic | Educator |
| Front-end quiz authoring | Educator |
| Proctoring features | Enterprise |

---

## Database Implications for v1.0

v1.0 must include database structures to support v2.0 features:

### Tables to Create in v1.0 (for v2.0 use)
```sql
-- Groups table (empty in v1.0, used in v2.0)
ppq_groups (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    description TEXT,
    creator_id BIGINT,
    parent_id BIGINT NULL,  -- NULL in free (flat), used in School tier
    created_at DATETIME,
    updated_at DATETIME
)

-- Group membership
ppq_group_members (
    id BIGINT PRIMARY KEY,
    group_id BIGINT,
    user_id BIGINT,
    added_at DATETIME
)

-- Assignments table
ppq_assignments (
    id BIGINT PRIMARY KEY,
    quiz_id BIGINT,
    group_id BIGINT,
    assigned_by BIGINT,
    due_date DATETIME NULL,
    created_at DATETIME
)
```

### Fields to Add to Existing Tables
- `ppq_quizzes`: Add `pretest_for_quiz_id` (nullable, links to post-test quiz)

---

## Hooks to Expose in v1.0 (for v2.0)

```php
// Group management hooks (no-op in v1.0)
do_action('ppq_group_created', $group_id, $group_data);
do_action('ppq_group_member_added', $group_id, $user_id);
do_action('ppq_group_member_removed', $group_id, $user_id);

// Assignment hooks (no-op in v1.0)
do_action('ppq_assignment_created', $assignment_id, $quiz_id, $group_id);
do_action('ppq_assignment_submitted', $assignment_id, $attempt_id);
do_action('ppq_assignment_due_date_passed', $assignment_id);

// Filter for group-based quiz access
apply_filters('ppq_user_can_access_quiz', $can_access, $quiz_id, $user_id);
```

---

## UI Changes from v1.0

### New Admin Menu Items
- **Groups** submenu under "PPQ Quizzes"
- **Assignments** submenu under "PPQ Quizzes"

### Quiz Builder Updates
- Assignment options panel (assign to groups, set due date)
- Pre/post test linking option

### New Dashboard Widgets
- "My Assignments" widget for students
- "Group Performance" widget for teachers

---

## Success Metrics for v2.0

- 50% of active sites creating at least one group
- Assignment completion tracking being used
- Pre/post test pairing feature adoption
- Premium conversion rate from free users

---

## Development Notes

- Groups/assignments should feel native, not bolted on
- Maintain v1.0 simplicity for users who don't need groups
- Clear upgrade path messaging for nested groups, advanced analytics
- LearnPress integration uses same pattern as other LMS integrations
- Pre/post comparison should be genuinely useful even without premium analytics

---

## Architecture Considerations

### State Management
- Group membership cached per-user session
- Assignment status cached with short TTL
- Group reports can be computationally expensive - consider background processing

### Permissions
- Teachers manage their own groups only
- Admins have full group visibility
- Students see assigned quizzes regardless of direct quiz permissions

### Backward Compatibility
- v1.0 installations upgrading to v2.0 get groups feature automatically
- Existing quizzes remain accessible via shortcode/block/LMS
- No breaking changes to v1.0 quiz-taking experience
