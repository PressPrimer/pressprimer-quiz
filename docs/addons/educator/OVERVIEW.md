# PressPrimer Quiz - Educator Addon

> **Purpose:** Context document for AI-assisted development. Provides scope for the Educator premium tier across versions.

> **Price:** $149/year (single site license)  
> **Target Market:** Professional educators, tutors, small training teams, course creators

---

## Addon Philosophy

The Educator tier unlocks professional-grade assessment features for individual educators who need more sophisticated tools than the free tier provides. Features focus on enhanced pedagogy, advanced question authoring, and personal productivity improvements.

**Value Proposition:** *Professional-grade assessment tools that would cost $500+/year from competitors, for less than $13/month.*

---

## Version 1.0 Features (Launches with Free v2.0)

### Survey & Ungraded Questions
- **Survey Mode:** Questions without correct answers for opinion gathering
- **Ungraded Questions:** Questions that don't affect final score
- **Mixed Quizzes:** Combine graded and ungraded questions in same quiz
- **Survey Analytics:** Response distribution, trends, word clouds for open responses

### Confidence Ratings
- **Per-Question Confidence:** Students rate confidence before seeing results
- **Confidence Calibration Report:** Compare confidence vs actual performance
- **Metacognition Insights:** Identify overconfident/underconfident patterns
- **Confidence Trends:** Track calibration improvement over time

### Branching & Conditional Logic
- **Conditional Paths:** Show/skip questions based on previous answers
- **Score-Based Branching:** Different paths for high/medium/low performers
- **Remediation Loops:** Automatically redirect to easier questions after failures
- **Branch Visualization:** Visual flow diagram of quiz logic

### Front-End Quiz Authoring
- **Teacher Dashboard:** Create and manage quizzes from front-end
- **No Admin Access Required:** Teachers work entirely from front-end
- **Quick Edit Mode:** Inline editing of questions and answers
- **Preview Integration:** Test quizzes without publishing

### LaTeX Math Support
- **Math Notation:** Full LaTeX rendering in questions and answers
- **Equation Editor:** Visual equation builder for non-LaTeX users
- **Copy/Paste Support:** Import equations from other tools
- **Mobile Rendering:** Responsive math display on all devices

### Enhanced Reporting
- **Detailed Analytics:** Per-question performance breakdown
- **Time Analytics:** How long students spend per question
- **Attempt Comparison:** Compare performance across attempts
- **Exportable Charts:** Download report visualizations

### Attribution Removal
- **Admin-Only Removal:** Remove "Powered by PressPrimer" from admin
- **Front-End Attribution:** Remains on student-facing pages (Enterprise for full removal)

---

## Version 2.0 Features (Launches with Free v3.0)

### Import/Export
- **CSV Question Import:** Bulk import with mapping interface
- **CSV Question Export:** Export question banks for backup/transfer
- **Quiz Structure Export:** JSON export of complete quiz configuration
- **Template Library:** Import from pre-built question sets

### AI Distractor Generation
- **Smart Distractors:** AI generates plausible wrong answers
- **Distractor Quality Scoring:** Rate generated options by difficulty
- **Customization:** Adjust distractor style (common misconceptions, similar terms, etc.)
- **Batch Generation:** Generate distractors for multiple questions

### Spaced Repetition Tools
- **Review Scheduling:** Automatically schedule question reviews
- **Forgetting Curve Tracking:** Visualize retention decay
- **Optimal Intervals:** Algorithm-based review timing
- **Student Self-Study:** Personalized review quiz generation

### Enhanced Branching
- **Complex Logic Trees:** Multiple conditions per branch
- **Variable-Based Logic:** Store and reference values across questions
- **Loop Constructs:** Repeat sections until mastery achieved
- **External Data:** Branch based on user meta or LMS data

---

## Version 3.0 Features (Launches with Free v4.0)

### Advanced Survey Analytics
- **Sentiment Analysis:** AI-powered response categorization
- **Cross-Tabulation:** Compare responses by demographics/groups
- **Trend Reporting:** Track opinion shifts over time
- **Comparative Benchmarks:** Compare against previous cohorts

### Custom Quiz Templates
- **Template Builder:** Create reusable quiz structures
- **Branded Templates:** Apply consistent styling across quizzes
- **Template Sharing:** Share templates within organization (requires School tier for cross-teacher sharing)
- **Template Variables:** Dynamic content insertion

### Enhanced AI Features
- **Content Analysis:** AI suggests questions from uploaded materials
- **Difficulty Prediction:** AI estimates question difficulty before use
- **Question Improvement Suggestions:** AI feedback on question clarity
- **Automatic Tagging:** AI-powered category and tag suggestions

---

## Technical Requirements

### Dependencies
- Requires PressPrimer Quiz Free v2.0+
- PHP 7.4+ (matches free plugin)
- WordPress 6.0+
- OpenAI API key (for AI distractor features)

### Database Extensions
```sql
-- Confidence ratings storage
-- Added to ppq_responses table:
confidence_rating TINYINT NULL,  -- 1-5 scale, NULL if not enabled

-- Survey response storage
ppq_survey_responses (
    id BIGINT PRIMARY KEY,
    question_id BIGINT,
    attempt_id BIGINT,
    response_text TEXT,
    response_data JSON,  -- For structured survey responses
    created_at DATETIME
)

-- Branching logic storage
-- Added to ppq_quizzes settings JSON:
{
    "branching": {
        "enabled": true,
        "rules": [
            {
                "id": "rule_1",
                "trigger": "question_answer",
                "question_id": 123,
                "condition": "equals",
                "value": "answer_a",
                "action": "skip_to",
                "target_question_id": 456
            }
        ]
    }
}

-- Spaced repetition data
ppq_spaced_repetition (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    question_id BIGINT,
    ease_factor DECIMAL(3,2) DEFAULT 2.5,
    interval_days INT DEFAULT 1,
    repetitions INT DEFAULT 0,
    next_review DATE,
    last_review DATE,
    INDEX (user_id, next_review)
)
```

### Hooks Provided
```php
// Confidence rating hooks
apply_filters('ppq_educator_confidence_scale', $scale, $question_id);
do_action('ppq_educator_confidence_recorded', $attempt_id, $question_id, $rating);

// Survey hooks
apply_filters('ppq_educator_survey_response_types', $types);
do_action('ppq_educator_survey_response_saved', $response_id, $data);

// Branching hooks
apply_filters('ppq_educator_branch_conditions', $conditions);
apply_filters('ppq_educator_evaluate_branch', $should_branch, $rule, $attempt);
do_action('ppq_educator_branch_taken', $attempt_id, $rule_id, $target_question_id);

// AI distractor hooks
apply_filters('ppq_educator_distractor_prompt', $prompt, $question, $context);
apply_filters('ppq_educator_distractor_count', $count, $question_id);
do_action('ppq_educator_distractors_generated', $question_id, $distractors);

// Spaced repetition hooks
apply_filters('ppq_educator_sr_algorithm', $algorithm_name);
apply_filters('ppq_educator_sr_next_interval', $days, $ease_factor, $repetitions);
do_action('ppq_educator_sr_review_completed', $user_id, $question_id, $quality);

// Import/export hooks
apply_filters('ppq_educator_import_formats', $formats);
apply_filters('ppq_educator_export_formats', $formats);
do_action('ppq_educator_import_completed', $import_id, $stats);
```

---

## UI Components

### Admin Additions
- **Educator Settings Panel:** Configure tier-specific features
- **Branching Editor:** Visual flow builder within quiz editor
- **Import/Export Interface:** Drag-drop import, format selection
- **AI Distractor Panel:** Generate and review AI suggestions

### Front-End Additions
- **Teacher Dashboard:** `/teacher-dashboard/` page template
- **Confidence Rating UI:** Slider or button scale during quiz
- **Survey Response UI:** Text areas, scales, multi-select options

### Quiz Builder Extensions
- **Question Type Selector:** Adds survey/ungraded options
- **Confidence Toggle:** Enable per-quiz or per-question
- **Branch Configuration:** Condition builder within question settings
- **LaTeX Editor:** Integrated equation editor

---

## Licensing & Activation

### License Validation
- EDD Software Licensing integration
- Single site activation
- Annual renewal required
- Grace period: 14 days after expiration

### Feature Degradation
When license expires:
- Existing data preserved
- New feature usage blocked
- Survey questions become regular questions (no analytics)
- Branching logic continues to work (but can't edit)
- Import/export disabled
- AI features disabled

---

## Success Metrics

- License activation rate from free users
- Feature adoption per licensed user
- Support ticket volume per customer
- Renewal rate (target: 70%+)
- NPS score from Educator customers

---

## Development Priority

### v1.0 Priority Order
1. Survey/ungraded questions (highest differentiation)
2. Confidence ratings (unique feature)
3. Branching logic (high request item)
4. Front-end authoring (teacher productivity)
5. LaTeX support (academic market)
6. Enhanced reporting (value perception)

### Integration Points
- Must integrate cleanly with free quiz builder
- React components extend existing interfaces
- Database migrations must be reversible
- Feature flags for graceful degradation
