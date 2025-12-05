# Educator Addon - Overview

## Product Identity

**Name:** PressPrimer Quiz - Educator  
**Price:** $149/year (1 site)  
**Launched At:** Version 2.0 (aligned with Free v2.0)

## Target Users

Individual educators and small teams who need:
- Advanced reporting and analytics
- Content portability (import/export)
- Group-based quiz assignment
- Professional assessment features

## Release Strategy

Each release focuses on 1-2 major features to maintain quality and provide regular marketing moments.

---

## Version 2.0 - Launch Release

**Theme:** Groups & Import/Export  
**Target:** 4 weeks after Free v1.0

### Major Features

#### 1. Groups & Quiz Assignments
- Create student groups
- Assign quizzes to groups or individual users
- Set due dates for assignments
- Track assignment completion
- `ppq_teacher` role with scoped permissions
- Teachers see only their own groups/students
- Assigned Quizzes block and `[ppq_assigned_quizzes]` shortcode

#### 2. Import/Export
- Export questions to CSV/JSON format
- Export entire question banks
- Import questions from CSV/JSON
- Import from other quiz plugins (Quiz Cat, WP Quiz, Learn Dash Quiz)
- Duplicate detection during import
- Field mapping interface for CSV imports

### Additional Features
- Freemius license validation
- Settings panel for Educator-specific options
- Integration hooks for Free plugin

### Technical Requirements
- Requires Free v2.0+
- Adds 2 new database tables (uses existing groups tables from Free)
- Admin-only features (no frontend changes)

---

## Version 2.1

**Theme:** Enhanced Reporting  
**Target:** 6 weeks after v2.0

### Major Feature

#### Pre/Post Test Linking
- Link two quizzes as pre-test and post-test
- Automatic learning gain calculation
- Visual comparison of pre vs post scores
- Per-category improvement tracking
- Aggregate class improvement reports

### Supporting Features
- CSV export of all reports
- Visual charts with Chart.js (score distribution, category performance)
- Enhanced dashboard with Educator-specific metrics

---

## Version 2.2

**Theme:** AI Enhancement  
**Target:** 6 weeks after v2.1

### Major Feature

#### AI Distractor Generation
- Generate plausible wrong answers for existing questions
- Configurable distractor count (2-7)
- Quality scoring for generated distractors
- Edit/approve before saving
- Bulk generation for question banks

### Supporting Features
- Confidence ratings detailed reports
- Student confidence calibration analytics
- Question difficulty analysis based on actual performance

---

## Version 2.3

**Theme:** Content Flexibility  
**Target:** 6 weeks after v2.2

### Major Feature

#### Survey/Ungraded Questions
- New question mode: Survey (no correct answer)
- Likert scale support (1-5, 1-7, 1-10)
- Open-ended text responses (short answer, no auto-grading)
- Mixed quiz support (graded + survey questions)
- Survey response reports and aggregation

### Supporting Features
- LaTeX/math rendering support (MathJax integration)
- Question templates for common assessment patterns

---

## Version 2.4+

**Future Considerations:**
- Question quality metrics
- Item analysis reports
- Distractor effectiveness analysis
- More import sources (Kahoot, Quizlet, Moodle)
- Question bank templates

---

## Technical Architecture

### Plugin Structure
```
pressprimer-quiz-educator/
├── pressprimer-quiz-educator.php
├── includes/
│   ├── class-ppq-educator.php
│   ├── admin/
│   │   ├── class-ppq-groups-admin.php
│   │   ├── class-ppq-assignments-admin.php
│   │   ├── class-ppq-import-export.php
│   │   └── class-ppq-educator-reports.php
│   ├── models/
│   │   ├── class-ppq-group.php
│   │   ├── class-ppq-group-member.php
│   │   └── class-ppq-assignment.php
│   └── integrations/
│       └── class-ppq-educator-automator.php
├── assets/
│   ├── css/
│   └── js/
└── languages/
```

### Database Tables (leverages Free tables)
- `wp_ppq_groups` - Group definitions
- `wp_ppq_group_members` - User-group relationships
- `wp_ppq_assignments` - Quiz assignments to groups/users

### Hooks Added
```php
// Actions
do_action('pressprimer_quiz_educator_group_created', $group_id);
do_action('pressprimer_quiz_educator_assignment_created', $assignment_id);
do_action('pressprimer_quiz_educator_questions_imported', $question_ids);

// Filters
apply_filters('pressprimer_quiz_educator_import_formats', $formats);
apply_filters('pressprimer_quiz_educator_export_data', $data, $format);
```

### Automator Integration
- Trigger: Quiz assigned to user
- Trigger: Assignment due date approaching
- Trigger: Assignment completed

---

## Success Metrics

### v2.0 Launch (30 days)
- 100+ paid licenses
- <5% refund rate
- <10% support tickets per customer

### Ongoing
- 20% conversion rate from Free users who view upgrade page
- 80%+ renewal rate at year end
- 4.5+ average rating
