# Version 1.0 Free - Overview

## Release Goal

Prove product-market fit by delivering a genuinely useful, enterprise-quality quiz plugin that:
1. Demonstrates the PressPrimer vision
2. Generates 1,000+ active installs in 90 days
3. Earns 4.0+ star rating on WordPress.org
4. Creates clear upgrade paths to premium tiers

## Core Philosophy

**This isn't a crippled trial.** Version 1.0 Free is a powerful, complete quiz platform that most users won't need to upgrade from. We earn upgrades by delivering genuine value in specialized features, not by hobbling the free version.

## Target Release

WordPress.org Plugin Repository

**Timeline:** 10-12 weeks of development

## What's In v1.0

### Question Management
- **Three question types:** Multiple Choice, Multiple Answer, True/False
- Unlimited questions and question banks
- Multiple categories and tags per question
- Creator-assigned difficulty (Easy/Medium/Hard)
- Expected completion time
- Up to 8 answer options
- Per-question and per-answer feedback
- Question versioning (immutable snapshots)
- Question reuse across quizzes

### Quiz Builder
- Visual drag-and-drop interface
- Fixed quizzes (specific questions) and dynamic quizzes (rules-based generation)
- Dynamic generation: "Pull N questions from Bank X, categories Y, difficulty Z"
- Tutorial mode (immediate feedback) and Timed mode (feedback at end)
- Time limits with always-visible timer
- Passing score percentage
- Randomize questions and/or answers
- Allow/disallow skipping, backward navigation
- **Save and resume** (configurable per quiz)
- Attempt limits with delays between retakes
- Three professional themes (Default, Modern, Minimal)
- Score-banded feedback

### AI Integration
- Users provide their own OpenAI API key
- Generate questions from text input
- Generate questions from uploaded PDF/Word documents
- Specify count, difficulty, question types, categories
- Review and edit before adding to bank
- Direct API calls (no middleware, no credits)

### Quiz Taking
- Landing page with overview, attempt history, resume option
- Always-visible timer (customizable position)
- Progress indicator
- Question navigator (if backward navigation enabled)
- **Real-time auto-save on every answer selection**
- Cross-device resume (logged-in users)
- Guest support with optional email capture
- 24-hour guest sessions
- Confidence rating checkbox (optional)
- Auto-submit when time expires

### Results & Review
- Overall score (points and percentage)
- Pass/fail indicator
- Time spent (total and per question)
- Category breakdown display
- Confidence calibration metric
- Comparison to average
- Question-by-question review
- "My Attempts" history page
- Score-banded feedback messages

### Admin Reporting
- Dashboard widget with key metrics
- Filter by quiz, date range, pass/fail
- Key metrics: attempts, average score, pass rate, completion time
- Individual attempt drill-down
- Data captured for future features (psychometrics, xAPI, etc.)

### LMS Integrations
- **LearnDash:** Meta box on lessons, completion triggers
- **TutorLMS:** Course builder integration
- **LifterLMS:** Lesson builder integration
- Auto-enable when LMS detected

### Uncanny Automator Integration
- Triggers: User completes quiz, passes quiz, fails quiz
- Fields: quiz ID, score, pass/fail, user info

### Admin Experience
- Dashboard widget with metrics
- Question bank management with search/filters
- Category and tag management
- Quiz list with bulk operations
- Settings page with global defaults, theme customization
- Interactive onboarding wizard

### Frontend Components
- Two Gutenberg blocks: Quiz, My Attempts
- Matching shortcodes: `[ppq_quiz]`, `[ppq_my_attempts]`

### Technical Foundation
- Custom database tables (not CPTs)
- Support for 10,000+ questions, 100,000+ attempts
- Object caching support
- WCAG 2.1 AA accessibility
- Full i18n/l10n support
- RTL language support
- Server-side answer validation only
- Rate limiting (10 attempts per 1 minute)
- Nonce verification on all forms
- No PressPrimer branding in frontend

## What's NOT in v1.0

**These are explicitly excluded from v1.0 Free:**

### Premium-Only Features (Educator Tier - $149/yr)
- Groups and quiz assignments
- LaTeX/math support
- AI distractor generation
- Import/export questions
- Confidence ratings detailed reports
- Survey/ungraded questions
- Pre/post test linking
- CSV export of reports
- Visual charts with Chart.js

### Premium-Only Features (School Tier - $299/yr)
- xAPI/LRS output
- Spaced repetition
- Multi-teacher/group management
- Student self-quiz generation
- Reporting by attempt number over time
- Shared question banks
- Curve grading
- Quiz availability windows (date/time restrictions)

### Premium-Only Features (Enterprise Tier - $499/yr)
- Proctoring
- Branching/adaptive logic
- White-label
- Audit logging

### Future Free Features (v2.0)
- Freemius SDK integration
- Premium upsells and locked feature indicators
- Analytics opt-in
- LearnPress integration

### Explicitly Not Building
- No essay, fill-in-blank, matching, ordering, hotspot, video, or audio question types
- No certificate system
- No gamification (badges, leaderboards)

## User Roles

### Administrator
- Full access to all features
- Manage all quizzes, questions, banks
- Plugin settings

### Student (WordPress Subscriber + capability)
- Take quizzes (no assignment system in free)
- View own results and history
- Cannot browse question banks

### Guest
- Take quizzes with optional email capture
- Access results via tokenized link
- 24-hour session duration

**Note:** The `ppq_teacher` role and group/assignment features are reserved for the Educator tier addon.

## Technical Requirements

- WordPress 6.0+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+

## Success Metrics

### 90 Days Post-Launch
- 1,000+ active installations
- 4.0+ star average rating
- <15% unresolved support threads
- 10+ five-star reviews with testimonials
- 50+ quizzes created per week (based on telemetry-free estimates)

### Quality Gates Before Release
- [ ] All features in scope working as specified
- [ ] Tested on WordPress 6.0 and latest
- [ ] Tested on PHP 8.0 and 8.4
- [ ] Tested with LearnDash, TutorLMS, LifterLMS
- [ ] Keyboard navigation works throughout
- [ ] Screen reader tested (NVDA or JAWS)
- [ ] Mobile tested (375px minimum)
- [ ] All strings translatable
- [ ] No PHP errors or warnings
- [ ] Security audit completed
- [ ] Performance acceptable with 1,000+ questions

## Files & Assets Needed

### WordPress.org Submission
- `readme.txt` with full documentation
- Screenshots (1280×960px minimum)
- Plugin icon (128×128, 256×256)
- Plugin banner (772×250, 1544×500)

### Documentation
- User guide (for WordPress.org FAQ)
- Developer hooks reference
- Theme customization guide

## Development Approach

This version is divided into 10 phases. See `PHASES.md` for the detailed breakdown.

Each phase includes:
- Specific prompts for Claude Code
- Acceptance criteria
- Testing requirements

Work through phases in order. Complete all prompts in a phase before moving to the next.
