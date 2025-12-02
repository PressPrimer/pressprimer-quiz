# School Addon - Overview

## Product Identity

**Name:** PressPrimer Quiz - School  
**Price:** $299/year (3 sites)  
**Launched At:** Version 2.0 (aligned with Free v2.0)  
**Requires:** Educator addon (includes all Educator features)

## Target Users

Departments, training organizations, and multi-instructor environments who need:
- Multi-teacher coordination
- Advanced scheduling and availability
- Learning standards integration (xAPI)
- Institutional-level features

## Release Strategy

Each release focuses on 1-2 major features. School tier includes all Educator features.

---

## Version 2.0 - Launch Release

**Theme:** Scheduling & Grading  
**Target:** Simultaneous with Educator v2.0

### Major Features

#### 1. Quiz Availability Windows
- Set start date/time for quiz availability
- Set end date/time (hard cutoff)
- Grace period option (allow late submissions with penalty)
- Timezone handling (site timezone or user timezone)
- "Quiz not yet available" and "Quiz expired" messaging
- Force completion within time window (no resume after window closes)
- Scheduled availability with countdown timer
- Email notifications before quiz opens/closes

#### 2. Curve Grading
- Apply curve to completed quiz attempts
- Curve methods:
  - Percentage boost (add X% to all scores)
  - Square root curve (sqrt of score × 10)
  - Bell curve (target specific grade distribution)
  - Custom formula support
- Preview curve impact before applying
- Curve applied retroactively to existing attempts
- Original scores preserved (curve shown separately)
- Curve per-quiz or per-assignment

### Additional Features
- Multi-site license management
- School-specific settings panel
- Integration with Educator features

---

## Version 2.1

**Theme:** xAPI & Standards  
**Target:** 6 weeks after v2.0

### Major Feature

#### xAPI/LRS Integration
- Send quiz data to Learning Record Store
- Configurable LRS endpoint and credentials
- Statement generation for:
  - Quiz started
  - Quiz completed
  - Quiz passed/failed
  - Question answered (optional, verbose mode)
- Activity IDs based on quiz UUIDs
- Actor identification (email or account)
- CMI5 profile support (optional)
- Retry logic for failed transmissions
- Statement queue with batch processing

### Supporting Features
- Quiz attempt audit log
- Data export in xAPI-compatible format

---

## Version 2.2

**Theme:** Multi-Teacher Coordination  
**Target:** 6 weeks after v2.1

### Major Feature

#### Shared Question Banks
- Bank visibility: Private, Shared (read-only), Shared (editable)
- Permission levels per bank
- Bank ownership transfer
- Fork/copy shared banks
- Track bank usage across teachers
- Collaborative bank editing with revision history
- Bank approval workflow (optional)

### Supporting Features
- Teacher activity dashboard
- Cross-teacher reporting (admin only)

---

## Version 2.3

**Theme:** Spaced Repetition  
**Target:** 6 weeks after v2.2

### Major Feature

#### Spaced Repetition System
- SM-2 algorithm implementation
- Per-question mastery tracking
- Automatic review quiz generation
- Configurable intervals (1, 3, 7, 14, 30 days)
- Student mastery dashboard
- "Review due" notifications
- Integration with quiz assignments
- Mastery thresholds per category

### Supporting Features
- Student self-quiz generation from weak areas
- Mastery-based progression requirements

---

## Version 2.4

**Theme:** Longitudinal Reporting  
**Target:** 6 weeks after v2.3

### Major Feature

#### Attempt-Over-Time Reporting
- Track student progress across multiple attempts
- Learning curve visualization
- Cohort comparison (this semester vs last)
- Retention analysis (performance decay over time)
- Category mastery progression
- Export longitudinal data for research

### Supporting Features
- Scheduled report generation
- Email digest of weekly metrics

---

## Version 2.5+

**Future Considerations:**
- Department-level dashboards
- Integration with SIS (Student Information Systems)
- Competency-based progression
- Learning pathway recommendations
- Predictive analytics (at-risk students)

---

## Technical Architecture

### Plugin Structure
```
pressprimer-quiz-school/
├── pressprimer-quiz-school.php
├── includes/
│   ├── class-ppq-school.php
│   ├── admin/
│   │   ├── class-ppq-availability-admin.php
│   │   ├── class-ppq-curve-grading.php
│   │   ├── class-ppq-shared-banks.php
│   │   └── class-ppq-school-reports.php
│   ├── services/
│   │   ├── class-ppq-xapi-service.php
│   │   ├── class-ppq-spaced-repetition.php
│   │   └── class-ppq-curve-calculator.php
│   └── integrations/
│       └── class-ppq-lrs-integration.php
├── assets/
│   ├── css/
│   └── js/
└── languages/
```

### Database Tables
- `wp_ppq_quiz_availability` - Start/end times per quiz
- `wp_ppq_curve_applications` - Curve history and settings
- `wp_ppq_bank_shares` - Bank sharing permissions
- `wp_ppq_xapi_queue` - Pending xAPI statements
- `wp_ppq_mastery_tracking` - Spaced repetition data

### Hooks Added
```php
// Actions
do_action('ppq_school_quiz_available', $quiz_id);
do_action('ppq_school_quiz_expired', $quiz_id);
do_action('ppq_school_curve_applied', $quiz_id, $curve_type);
do_action('ppq_school_xapi_sent', $statement_id);

// Filters
apply_filters('ppq_school_curve_methods', $methods);
apply_filters('ppq_school_xapi_statement', $statement, $attempt);
apply_filters('ppq_school_availability_message', $message, $quiz);
```

### Quiz Availability Logic
```php
function ppq_is_quiz_available($quiz_id, $user_id = null) {
    $availability = PPQ_Quiz_Availability::get_for_quiz($quiz_id);
    
    if (!$availability) {
        return true; // No restrictions
    }
    
    $now = current_time('timestamp');
    $start = strtotime($availability->start_at);
    $end = strtotime($availability->end_at);
    
    if ($now < $start) {
        return new WP_Error('not_yet', __('Quiz opens on', 'ppq-school') . ' ' . $availability->start_at);
    }
    
    if ($now > $end) {
        if ($availability->grace_period_minutes > 0) {
            $grace_end = $end + ($availability->grace_period_minutes * 60);
            if ($now <= $grace_end) {
                return true; // In grace period (late penalty applied separately)
            }
        }
        return new WP_Error('expired', __('Quiz closed on', 'ppq-school') . ' ' . $availability->end_at);
    }
    
    return true;
}
```

---

## Success Metrics

### v2.0 Launch (30 days)
- 50+ paid licenses
- <3% refund rate
- Feature adoption: 80% using availability, 40% using curves

### Ongoing
- 15% upgrade rate from Educator tier
- 85%+ renewal rate
- Average 2.5 teachers per School license
