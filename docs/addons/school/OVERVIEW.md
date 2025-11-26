# PressPrimer Quiz - School Addon

> **Purpose:** Context document for AI-assisted development. Provides scope for the School (Institution) premium tier across versions.

> **Price:** $249/year (up to 3 site licenses)  
> **Target Market:** University departments, K-12 schools, training organizations, multi-teacher environments

---

## Addon Philosophy

The School tier unlocks institutional coordination features for organizations where multiple teachers need to collaborate, share resources, and report on aggregate outcomes. Features focus on organizational structure, advanced analytics, and professional reporting.

**Value Proposition:** *Everything departments need to coordinate assessment across multiple teachers and courses, for less than one semester of enterprise LMS costs.*

---

## Includes All Educator Features

School tier includes everything in Educator tier:
- Survey & ungraded questions
- Confidence ratings
- Branching & conditional logic
- Front-end quiz authoring
- LaTeX math support
- Enhanced reporting
- Import/Export
- AI distractor generation
- Spaced repetition tools

---

## Version 1.0 Features (Launches with Free v2.0)

### Advanced Pre/Post Test Analysis
- **Statistical Significance:** Indicate whether improvement is meaningful (p-values)
- **Effect Size Calculation:** Cohen's d for practical significance
- **Confidence Intervals:** Range of likely true improvement
- **Visual Comparison:** Professional charts comparing pre/post distributions
- **Item-Level Analysis:** Which questions showed most improvement

### Progress Over Attempts
- **Attempt Timeline:** Visualize performance across all attempts
- **Learning Curve Analysis:** Identify mastery trajectory
- **Struggle Detection:** Flag students with declining performance
- **Comparative Progress:** Compare individual to cohort average

### Observational Assessments
- **Rubric Builder:** Create multi-criteria rubrics
- **Competency Mapping:** Link rubric criteria to competencies
- **Multi-Evaluator Support:** Multiple teachers rate same performance
- **Inter-Rater Reliability:** Calculate agreement metrics
- **Practical Skills Tracking:** Non-quiz assessment workflows

### xAPI/LRS Integration
- **Statement Generation:** Quiz events as xAPI statements
- **LRS Connector:** Configure connection to Learning Locker, Watershed, etc.
- **Verb Library:** Standard and custom xAPI verbs
- **Activity Profiles:** Rich quiz metadata in xAPI format
- **Result Reporting:** Scores, duration, completion status

### Multi-Teacher Coordination
- **Shared Question Banks:** Teachers access department question pools
- **Question Approval Workflow:** Review before adding to shared banks
- **Teacher Activity Reports:** Who created/edited what
- **Permission Levels:** View, contribute, edit, admin per bank

### Department-Level Reporting
- **Aggregate Dashboards:** Performance across all department quizzes
- **Teacher Comparison:** (Anonymized) teaching effectiveness metrics
- **Course Analytics:** Performance by course/section
- **Trend Reporting:** Semester-over-semester comparisons

---

## Version 2.0 Features (Launches with Free v3.0)

### Nested Group Hierarchies
- **Multi-Level Groups:** University > College > Department > Course > Section
- **Permission Inheritance:** Cascade settings down hierarchy
- **Bulk Assignment:** Assign quizzes to entire branches
- **Hierarchy Visualization:** Interactive org chart view
- **Cross-Group Reporting:** Compare across hierarchy levels

### Question Performance Analysis
- **Difficulty Index:** Percentage correct per question
- **Discrimination Index:** How well question separates high/low performers
- **Point-Biserial Correlation:** Statistical discrimination measure
- **Distractor Analysis:** Which wrong answers are most selected
- **Review Recommendations:** Auto-flag problematic questions
- **Historical Trends:** Question performance over time

### Institutional Question Banks
- **Centralized Repository:** Institution-wide question access
- **Quality Tagging:** Peer-reviewed, validated, draft statuses
- **Usage Analytics:** Which questions used most, by whom
- **Version Control:** Question revision history
- **Deprecation Workflow:** Phase out outdated questions

### PDF Report Export
- **Professional Reports:** Branded PDF output
- **Report Templates:** Quiz summary, student detail, group comparison
- **Batch Generation:** Generate reports for all students at once
- **Custom Branding:** Department logo and colors
- **Scheduled Delivery:** Auto-email reports to stakeholders

### Scheduled Reports
- **Report Scheduling:** Daily, weekly, monthly automation
- **Recipient Management:** Configure who receives what
- **Report Queue:** View pending and completed reports
- **Failure Notifications:** Alert on delivery failures
- **Archive Access:** Historical report storage

---

## Version 3.0 Features (Launches with Free v4.0)

### Statistical Significance Analysis
- **T-Tests:** Compare two groups or pre/post
- **ANOVA:** Compare multiple groups
- **Chi-Square:** Analyze response distributions
- **Regression:** Predict outcomes from variables
- **Effect Size Library:** Multiple effect size calculations
- **Plain Language Summaries:** Stats explained for non-statisticians

### Advanced Psychometric Analysis
- **Classical Test Theory:** Reliability coefficients (KR-20, Cronbach's alpha)
- **Item Response Theory (Basic):** Difficulty and discrimination parameters
- **Test Information Curves:** Visual reliability by ability level
- **Standard Error of Measurement:** Score precision estimates
- **Cut Score Analysis:** Validate passing thresholds

### Cohort Comparison Tools
- **Cross-Cohort Analysis:** Compare this year vs last year
- **Demographic Breakdowns:** Performance by student characteristics
- **Benchmark Comparisons:** Compare against norms
- **Gap Analysis:** Identify achievement gaps
- **Intervention Tracking:** Measure improvement after interventions

### SSO/SAML Integration (Basic)
- **SAML 2.0 Support:** Connect to institutional identity providers
- **Auto-Provisioning:** Create users from SSO data
- **Group Sync:** Map SSO groups to PressPrimer groups
- **Session Management:** SSO-aware session handling

---

## Technical Requirements

### Dependencies
- Requires PressPrimer Quiz Free v2.0+
- Requires Educator Addon (bundled or separate purchase)
- PHP 7.4+ (matches free plugin)
- WordPress 6.0+
- LRS endpoint (for xAPI features)

### Database Extensions
```sql
-- Observational assessment tables
ppq_rubrics (
    id BIGINT PRIMARY KEY,
    title VARCHAR(255),
    description TEXT,
    created_by BIGINT,
    is_shared BOOLEAN DEFAULT FALSE,
    created_at DATETIME,
    updated_at DATETIME
)

ppq_rubric_criteria (
    id BIGINT PRIMARY KEY,
    rubric_id BIGINT,
    title VARCHAR(255),
    description TEXT,
    max_points INT,
    sort_order INT,
    INDEX (rubric_id)
)

ppq_rubric_levels (
    id BIGINT PRIMARY KEY,
    criterion_id BIGINT,
    title VARCHAR(100),
    description TEXT,
    points INT,
    sort_order INT,
    INDEX (criterion_id)
)

ppq_observations (
    id BIGINT PRIMARY KEY,
    rubric_id BIGINT,
    student_id BIGINT,
    evaluator_id BIGINT,
    scores JSON,  -- {criterion_id: level_id, ...}
    notes TEXT,
    created_at DATETIME,
    INDEX (student_id),
    INDEX (evaluator_id)
)

-- Shared question banks
ppq_shared_banks (
    id BIGINT PRIMARY KEY,
    title VARCHAR(255),
    description TEXT,
    owner_group_id BIGINT,  -- Department/institution that owns it
    visibility ENUM('private', 'department', 'institution'),
    approval_required BOOLEAN DEFAULT TRUE,
    created_at DATETIME
)

ppq_bank_questions (
    id BIGINT PRIMARY KEY,
    bank_id BIGINT,
    question_id BIGINT,
    status ENUM('pending', 'approved', 'rejected', 'deprecated'),
    added_by BIGINT,
    approved_by BIGINT NULL,
    approved_at DATETIME NULL,
    INDEX (bank_id, status)
)

-- Scheduled reports
ppq_scheduled_reports (
    id BIGINT PRIMARY KEY,
    report_type VARCHAR(50),
    parameters JSON,
    schedule ENUM('daily', 'weekly', 'monthly'),
    day_of_week TINYINT NULL,  -- For weekly
    day_of_month TINYINT NULL,  -- For monthly
    recipient_ids JSON,
    last_sent DATETIME NULL,
    next_send DATETIME,
    created_by BIGINT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME
)

ppq_report_archive (
    id BIGINT PRIMARY KEY,
    scheduled_report_id BIGINT NULL,
    report_type VARCHAR(50),
    parameters JSON,
    file_path VARCHAR(255),
    file_size INT,
    generated_at DATETIME,
    generated_by BIGINT,
    expires_at DATETIME,
    INDEX (generated_at)
)

-- xAPI configuration
ppq_xapi_config (
    id BIGINT PRIMARY KEY,
    name VARCHAR(100),
    endpoint_url VARCHAR(500),
    auth_type ENUM('basic', 'oauth'),
    credentials JSON,  -- Encrypted
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME
)

ppq_xapi_queue (
    id BIGINT PRIMARY KEY,
    config_id BIGINT,
    statement JSON,
    status ENUM('pending', 'sent', 'failed'),
    attempts INT DEFAULT 0,
    last_attempt DATETIME NULL,
    error_message TEXT NULL,
    created_at DATETIME,
    INDEX (status, created_at)
)
```

### Hooks Provided
```php
// Pre/post analysis hooks
apply_filters('ppq_school_significance_threshold', $alpha, $test_type);
apply_filters('ppq_school_effect_size_method', $method, $context);
do_action('ppq_school_prepost_analyzed', $pretest_id, $posttest_id, $results);

// Observational assessment hooks
apply_filters('ppq_school_rubric_max_criteria', $max);
do_action('ppq_school_observation_recorded', $observation_id, $evaluator_id);
do_action('ppq_school_irr_calculated', $rubric_id, $reliability_score);

// xAPI hooks
apply_filters('ppq_school_xapi_statement', $statement, $event_type, $context);
apply_filters('ppq_school_xapi_verbs', $verbs);
do_action('ppq_school_xapi_sent', $statement_id, $response);
do_action('ppq_school_xapi_failed', $statement_id, $error);

// Shared bank hooks
apply_filters('ppq_school_bank_visibility_levels', $levels);
do_action('ppq_school_question_submitted', $bank_id, $question_id, $user_id);
do_action('ppq_school_question_approved', $bank_id, $question_id, $approver_id);
do_action('ppq_school_question_rejected', $bank_id, $question_id, $reason);

// Nested group hooks
apply_filters('ppq_school_max_hierarchy_depth', $depth);
apply_filters('ppq_school_permission_inheritance', $permissions, $group_id, $parent_id);
do_action('ppq_school_group_hierarchy_changed', $group_id, $old_parent, $new_parent);

// Report hooks
apply_filters('ppq_school_pdf_template', $template, $report_type);
apply_filters('ppq_school_report_branding', $branding, $group_id);
do_action('ppq_school_report_generated', $report_id, $file_path);
do_action('ppq_school_scheduled_report_sent', $schedule_id, $recipients);

// Question analysis hooks
apply_filters('ppq_school_difficulty_thresholds', $thresholds);
apply_filters('ppq_school_discrimination_thresholds', $thresholds);
do_action('ppq_school_question_flagged', $question_id, $reason, $metrics);
```

---

## UI Components

### Admin Additions
- **Department Dashboard:** Aggregate view for department admins
- **Shared Bank Manager:** Browse, contribute, approve questions
- **Rubric Builder:** Visual rubric creation interface
- **Report Center:** Generate, schedule, download reports
- **xAPI Configuration:** LRS connection setup
- **Hierarchy Manager:** Visual group structure editor

### Reporting Views
- **Pre/Post Analysis Dashboard:** Statistical comparison view
- **Question Performance Dashboard:** Item analysis with recommendations
- **Progress Timeline:** Individual and group progress charts
- **Cohort Comparison:** Side-by-side group analytics

### Teacher Additions
- **Observation Entry:** Mobile-friendly rubric scoring
- **Bank Browser:** Search and use shared questions
- **My Reports:** Access scheduled and generated reports

---

## Licensing & Activation

### License Validation
- EDD Software Licensing integration
- Up to 3 site activations
- Sites can be reassigned (deactivate/reactivate)
- Annual renewal required
- Grace period: 14 days after expiration

### Feature Degradation
When license expires:
- Existing data preserved (rubrics, observations, reports)
- New observations blocked
- Report generation disabled
- xAPI queue paused (statements queued, not sent)
- Shared bank access becomes read-only
- Nested groups remain but can't modify hierarchy

---

## Success Metrics

- Conversion rate from Educator tier
- Multi-teacher adoption per license
- xAPI configuration rate
- Report generation volume
- Shared bank contribution rate
- Renewal rate (target: 75%+)

---

## Development Priority

### v1.0 Priority Order
1. xAPI/LRS integration (enterprise requirement)
2. Pre/post analysis (learning measurement)
3. Observational assessments (unique differentiator)
4. Multi-teacher coordination (core value prop)
5. Department reporting (admin value)
6. Progress over attempts (student insight)

### v2.0 Priority Order
1. Nested group hierarchies (organizational structure)
2. Question performance analysis (quality improvement)
3. PDF report export (professional output)
4. Scheduled reports (automation)
5. Institutional question banks (resource sharing)

### Integration Points
- Extends Educator addon (requires Educator)
- Integrates with free group system
- xAPI runs as background queue processor
- PDF generation uses Dompdf or mPDF
- Report scheduling uses WP-Cron or Action Scheduler
