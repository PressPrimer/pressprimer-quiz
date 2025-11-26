# Version 1.0 Development Phases

## Phase Overview

Version 1.0 is divided into 7 phases, each with specific prompts for Claude Code. Complete phases in order. Complete all prompts within a phase before proceeding.

| Phase | Focus                     | Prompts | Dependencies |
| ----- | ------------------------- | ------- | ------------ |
| 1     | Foundation                | 10      | None         |
| 2     | Question System           | 10      | Phase 1      |
| 3     | Quiz Builder              | 10      | Phase 2      |
| 4     | Quiz Taking Engine        | 10      | Phase 3      |
| 5     | Results & Review          | 8       | Phase 4      |
| 6     | AI Generation             | 6       | Phase 2      |
| 7     | LMS Integrations & Polish | 10      | Phases 5, 6  |

---

## Phase 1: Foundation (10 Prompts)

**Goal:** Establish plugin structure, database schema, core classes, and admin menu.

### Prompt 1.1: Plugin Bootstrap
```
Read docs/PROJECT.md, docs/architecture/CONVENTIONS.md, and docs/architecture/CODE-STRUCTURE.md.

Create the main plugin file structure:
- pressprimer-quiz.php with proper headers, constants, and autoloader registration
- includes/class-ppq-autoloader.php
- includes/class-ppq-plugin.php (singleton pattern)
- includes/class-ppq-activator.php
- includes/class-ppq-deactivator.php
- uninstall.php

The plugin should initialize on 'plugins_loaded' and load text domain.
All files must follow WordPress coding standards.
```

### Prompt 1.2: Database Schema
```
Read docs/architecture/DATABASE.md.

Create includes/database/class-ppq-schema.php that returns the SQL for all tables:
- wp_ppq_questions
- wp_ppq_question_revisions
- wp_ppq_categories
- wp_ppq_question_tax
- wp_ppq_banks
- wp_ppq_bank_questions
- wp_ppq_quizzes
- wp_ppq_quiz_items
- wp_ppq_quiz_rules
- wp_ppq_groups
- wp_ppq_group_members
- wp_ppq_assignments
- wp_ppq_attempts
- wp_ppq_attempt_items
- wp_ppq_events

Use dbDelta-compatible SQL syntax. Include all indexes.
```

### Prompt 1.3: Database Migrator
```
Create includes/database/class-ppq-migrator.php with:
- maybe_migrate() static method that checks PPQ_DB_VERSION
- Runs dbDelta with schema SQL
- Updates ppq_db_version option
- Hook into PPQ_Activator to run on activation

Test by activating plugin and verifying all tables are created.
```

### Prompt 1.4: Capabilities System
```
Read docs/architecture/SECURITY.md.

Create includes/utilities/class-ppq-capabilities.php with:
- setup_capabilities() - adds caps to administrator role
- create_teacher_role() - creates ppq_teacher role
- remove_capabilities() - for uninstall
- Capabilities: ppq_manage_all, ppq_manage_own, ppq_view_results_all, ppq_view_results_own, ppq_take_quiz, ppq_manage_settings

Call setup from activator. Remove on uninstall.
```

### Prompt 1.5: Base Model Class
```
Create includes/models/class-ppq-model.php as abstract base class with:
- Static get($id) method pattern
- Static create($data) method pattern
- save() instance method
- delete() instance method
- from_row() factory method
- Table name abstract property

This provides consistent patterns for all models.
```

### Prompt 1.6: Category Model
```
Read docs/versions/v1.0/features/001-questions.md for category requirements.

Create includes/models/class-ppq-category.php extending PPQ_Model:
- CRUD operations for categories and tags
- get_for_question($question_id, $taxonomy) - get categories/tags for a question
- get_all($taxonomy) - get all of a type
- update_counts() - update question_count and quiz_count
- Hierarchical support for categories (parent_id)
```

### Prompt 1.7: Admin Menu Structure
```
Create includes/admin/class-ppq-admin.php with:
- init() method hooking admin_menu, admin_enqueue_scripts
- register_menus() creating:
  - Main menu: PPQ (dashicons-welcome-learn-more)
  - Submenu: Quizzes (ppq-quizzes)
  - Submenu: Questions (ppq-questions)
  - Submenu: Question Banks (ppq-banks)
  - Submenu: Groups (ppq-groups)
  - Submenu: Reports (ppq-reports)
  - Submenu: Settings (ppq-settings)
- Placeholder render methods for each page
- Capability checks on each menu item
```

### Prompt 1.8: Admin Assets
```
Create assets/css/admin.css with:
- Basic admin page styling
- Form styles
- Table styles
- Button styles
- Use ppq- prefix for all classes

Create assets/js/admin.js with:
- PPQ.Admin namespace
- Basic initialization
- AJAX helper functions

Update PPQ_Admin to enqueue these assets on PPQ pages only.
```

### Prompt 1.9: Helper Functions
```
Create includes/utilities/class-ppq-helpers.php with static utility methods:
- get_client_ip() - get user's IP address safely
- generate_uuid() - wrapper for wp_generate_uuid4
- encrypt($data) - encrypt using WP salts
- decrypt($data) - decrypt using WP salts
- format_duration($seconds) - human readable time
- sanitize_array($array, $type) - sanitize array of values
- is_lms_active($lms) - check if specific LMS plugin active
```

### Prompt 1.10: Settings Framework
```
Create includes/admin/class-ppq-admin-settings.php with:
- Settings page rendering
- Settings registration using Settings API
- Sections: General, Quiz Defaults, Email, API Keys
- Fields: 
  - Default passing score
  - Default quiz mode
  - Email from name/address
  - OpenAI API key (encrypted storage)
- Save/load settings
- Proper sanitization and validation
```

**Phase 1 Testing:**
- [ ] Plugin activates without errors
- [ ] All database tables created
- [ ] Admin menus visible to administrators
- [ ] Settings page saves and loads values
- [ ] Teacher role created with correct capabilities

---

## Phase 2: Question System (10 Prompts)

**Goal:** Complete question CRUD, revisions, categories, and admin interface.

### Prompt 2.1: Question Model
```
Read docs/versions/v1.0/features/001-questions.md and docs/architecture/DATABASE.md.

Create includes/models/class-ppq-question.php:
- Properties matching database schema
- get($id), get_by_uuid($uuid)
- create($data) with validation
- save(), delete() (soft delete)
- get_current_revision() - lazy load revision
- get_categories(), get_tags() - lazy load taxonomies
- set_categories($ids), set_tags($ids)
```

### Prompt 2.2: Question Revision Model
```
Create includes/models/class-ppq-question-revision.php:
- Properties: id, question_id, version, stem, answers_json, feedback_correct, feedback_incorrect, settings_json, content_hash, created_at, created_by
- get($id)
- create($question_id, $data) - creates new revision, increments version
- get_answers() - parse answers_json to array
- generate_hash($stem, $answers) - SHA-256 for deduplication
- Revisions are never updated, only created
```

### Prompt 2.3: Question Bank Model
```
Read docs/versions/v1.0/features/002-question-banks.md.

Create includes/models/class-ppq-bank.php:
- CRUD operations
- add_question($question_id)
- remove_question($question_id)
- get_questions($args) - with filtering/pagination
- get_question_count()
- get_for_user($user_id)
```

### Prompt 2.4: Questions Admin List
```
Create includes/admin/class-ppq-admin-questions.php:
- List table extending WP_List_Table
- Columns: ID, Question (truncated stem), Type, Difficulty, Categories, Banks, Author, Date
- Bulk actions: Delete, Change Category
- Filters: Type, Difficulty, Category, Author
- Search by stem content
- Row actions: Edit, Duplicate, Delete
- Pagination
```

### Prompt 2.5: Question Editor - Structure
```
Create the question editor page structure:
- Add/Edit question admin page
- Form with sections:
  - Question Type selector (MC/MA/TF)
  - Question Stem (wp_editor)
  - Answer Options (dynamic, up to 8)
  - Correct Answer selector
  - Feedback fields (per-question, per-answer)
  - Metadata (difficulty, expected time, points)
  - Categories and Tags (taxonomy selectors)
  - Question Banks (multi-select)
- Save creates new revision if content changed
```

### Prompt 2.6: Question Editor - JavaScript
```
Create assets/js/question-builder.js:
- Dynamic answer option add/remove (max 8)
- Drag-and-drop reorder answers
- Correct answer toggle (radio for MC/TF, checkbox for MA)
- Per-answer feedback toggle
- Character counts
- Unsaved changes warning
- Preview panel (optional)
```

### Prompt 2.7: Question Editor - Save Logic
```
Add AJAX save handler to PPQ_Admin_Questions:
- Validate all input
- Create question if new, or update metadata
- Create new revision if stem/answers changed
- Update category/tag relationships
- Update bank memberships
- Return success with question ID
- Proper nonce verification and capability checks
```

### Prompt 2.8: Question Banks Admin
```
Create includes/admin/class-ppq-admin-banks.php:
- List table for banks
- Create/Edit bank form (name, description)
- Bank detail page showing questions in bank
- Add questions to bank (search/select interface)
- Remove questions from bank
- Filter questions within bank
```

### Prompt 2.9: Categories Admin
```
Create includes/admin/class-ppq-admin-categories.php:
- List table for categories (hierarchical display)
- List table for tags (flat)
- Add/Edit/Delete with AJAX
- Drag-and-drop hierarchy for categories
- Usage counts displayed
- Bulk operations
```

### Prompt 2.10: Question Duplication
```
Add duplicate functionality:
- Duplicate question creates new question with copy of current revision
- Appends "(Copy)" to stem
- Copies all metadata, categories, tags
- Does NOT copy bank memberships (user selects)
- AJAX handler with proper permissions
```

**Phase 2 Testing:**
- [ ] Create question of each type (MC, MA, TF)
- [ ] Edit question and verify revision created
- [ ] Assign categories and tags
- [ ] Create question bank and add questions
- [ ] Search and filter questions
- [ ] Duplicate question
- [ ] Delete question (soft delete)

---

## Phase 3: Quiz Builder (10 Prompts)

**Goal:** Complete quiz creation, configuration, and management.

### Prompt 3.1: Quiz Model
```
Read docs/versions/v1.0/features/003-quiz-builder.md and docs/architecture/DATABASE.md.

Create includes/models/class-ppq-quiz.php:
- All properties from schema
- CRUD operations
- get_items() - for fixed quizzes
- get_rules() - for dynamic quizzes
- get_questions_for_attempt() - generates question set
- duplicate() - copy quiz with all settings
```

### Prompt 3.2: Quiz Item and Rule Models
```
Create includes/models/class-ppq-quiz-item.php:
- For fixed quiz question assignments
- CRUD, reorder functionality

Create includes/models/class-ppq-quiz-rule.php:
- For dynamic quiz generation rules
- CRUD, reorder functionality
- get_matching_questions() - find questions matching rule criteria
```

### Prompt 3.3: Quizzes Admin List
```
Create includes/admin/class-ppq-admin-quizzes.php:
- List table with columns: Title, Questions, Mode, Status, Author, Date
- Row actions: Edit, Duplicate, Preview, Delete
- Bulk actions: Publish, Draft, Delete
- Filters: Status, Mode, Author
- Search by title
```

### Prompt 3.4: Quiz Editor - Settings Panel
```
Create quiz editor page with settings:
- Title, Description (wp_editor), Featured Image
- Quiz Mode: Tutorial / Timed
- Time Limit (minutes, or none)
- Passing Score (percentage)
- Navigation: Allow Skip, Allow Backward, Allow Resume
- Attempts: Max Attempts, Delay Between
- Display: Randomize Questions, Randomize Answers, Page Mode
- Show Answers: Never / After Submit / After Pass
- Enable Confidence Rating
- Theme selector
- Categories and Tags
```

### Prompt 3.5: Quiz Editor - Questions Panel (Fixed Mode)
```
Add questions panel for fixed quiz mode:
- Search and select questions to add
- Drag-and-drop reorder
- Remove questions
- Set point weight per question
- Show question preview on hover
- Question count and total points display
```

### Prompt 3.6: Quiz Editor - Rules Panel (Dynamic Mode)
```
Add rules panel for dynamic quiz mode:
- Add/remove/reorder rules
- Each rule specifies:
  - Source bank (optional, "any" if blank)
  - Categories (multi-select, optional)
  - Tags (multi-select, optional)
  - Difficulties (checkboxes)
  - Question count
- Preview: show how many questions match each rule
- Total questions calculation
```

### Prompt 3.7: Quiz Editor - Feedback Panel
```
Add feedback configuration:
- Score-banded feedback editor
- Add/remove bands
- Each band: min %, max %, message (wp_editor)
- Default bands: 0-59, 60-79, 80-100
- Validation: bands must not overlap, must cover 0-100
```

### Prompt 3.8: Quiz Editor - Save Logic
```
Add quiz save functionality:
- AJAX save handler
- Validate all settings
- Save quiz record
- Save quiz items (fixed) or rules (dynamic)
- Save feedback bands
- Update category/tag relationships
- Handle publish/draft status
- Proper permissions and nonce verification
```

### Prompt 3.9: Quiz Duplication
```
Add quiz duplication:
- Duplicate quiz with all settings
- Duplicate items or rules
- Duplicate feedback bands
- Appends "(Copy)" to title
- Sets status to draft
- New owner is current user
```

### Prompt 3.10: Quiz Preview
```
Add quiz preview functionality:
- Admin can preview quiz without creating attempt
- Shows landing page, then questions, then mock results
- Clearly marked as "Preview Mode"
- No data saved to attempts table
- Works for both fixed and dynamic quizzes
```

**Phase 3 Testing:**
- [ ] Create quiz with fixed questions
- [ ] Create quiz with dynamic rules
- [ ] Configure all settings
- [ ] Add score-banded feedback
- [ ] Preview quiz
- [ ] Duplicate quiz
- [ ] Verify question generation for dynamic quiz

---

## Phase 4: Quiz Taking Engine (10 Prompts)

**Goal:** Complete quiz delivery, answer saving, submission, and scoring.

### Prompt 4.1: Attempt Model
```
Read docs/versions/v1.0/features/004-quiz-taking.md.

Create includes/models/class-ppq-attempt.php:
- All properties from schema
- create_for_user($quiz_id, $user_id)
- create_for_guest($quiz_id, $email, $token)
- get_items() - attempt items
- save_answer($question_id, $answer)
- submit() - finalize and score
- is_timed_out() - check if time expired
- can_resume() - check if resumable
```

### Prompt 4.2: Attempt Item Model
```
Create includes/models/class-ppq-attempt-item.php:
- Properties from schema
- get_for_attempt($attempt_id)
- save_answer($selected_answers)
- score() - calculate score for this item
- get_question_revision() - get the locked revision
```

### Prompt 4.3: Scoring Service
```
Read docs/architecture/CODE-STRUCTURE.md for service pattern.

Create includes/services/class-ppq-scoring-service.php:
- score_response($question, $selected) - score single question
- score_mc($correct, $selected, $max_points)
- score_ma($correct, $selected, $max_points) - partial credit formula
- score_tf($correct, $selected, $max_points)
- calculate_attempt_score($attempt_id) - score entire attempt
- Update attempt and attempt_items with scores
```

### Prompt 4.4: Frontend Shortcodes
```
Create includes/frontend/class-ppq-shortcodes.php:
- [ppq_quiz id="X"] - render quiz
- [ppq_my_attempts] - render attempts list
- [ppq_assigned_quizzes] - render assignments
- Register shortcodes on init
- Handle missing/invalid IDs gracefully
```

### Prompt 4.5: Quiz Landing Page
```
Create includes/frontend/class-ppq-quiz-renderer.php:
- render_landing($quiz, $user) method
- Display: title, description, featured image
- Show: question count, time limit, passing score, attempt limits
- Previous attempts summary (if logged in)
- Resume button (if in-progress attempt exists)
- Guest email capture form (if enabled)
- Start Quiz button
- Check permissions and attempt limits
```

### Prompt 4.6: Quiz Question Display
```
Add to PPQ_Quiz_Renderer:
- render_quiz($attempt) method
- Timer display (always visible, configurable position)
- Progress indicator
- Question navigator (if backward navigation enabled)
- Current question with answer options
- Navigation buttons (Previous/Next/Submit)
- Save indicator
- No correct answers exposed in HTML
```

### Prompt 4.7: Quiz JavaScript - Core
```
Create assets/js/quiz.js:
- PPQ.Quiz namespace
- Initialize with attempt data from wp_localize_script
- Timer countdown with warnings at 5min and 1min
- Progress tracking
- Navigation between questions
- Submit confirmation
- Prevent accidental navigation away
```

### Prompt 4.8: Quiz JavaScript - Auto-Save
```
Add to assets/js/quiz.js:
- Save answer on every selection (not just on blur/next)
- AJAX save with retry logic
- Visual save indicator (saving/saved/error)
- Queue saves if rapid selections
- Store position for resume
- Handle network errors gracefully
```

### Prompt 4.9: Quiz AJAX Handlers
```
Add to includes/frontend/class-ppq-frontend.php:
- ppq_start_quiz - create attempt, return questions
- ppq_save_answer - save single answer
- ppq_submit_quiz - finalize and score
- ppq_get_resume_data - get in-progress attempt
- All handlers: verify nonce, check permissions, validate attempt ownership
- Rate limiting on start_quiz
```

### Prompt 4.10: Auto-Submit and Edge Cases
```
Handle edge cases:
- Auto-submit when timer expires (JS sends submit, server validates timing)
- Server-side timing validation (allow 30 second grace period)
- Handle abandoned attempts (mark as abandoned after 24 hours)
- Prevent double-submit
- Handle guest email matching to WP user
- Graceful degradation if JavaScript disabled
```

**Phase 4 Testing:**
- [ ] Start quiz as logged-in user
- [ ] Start quiz as guest with email
- [ ] Answer saves on each selection
- [ ] Navigate forward and backward
- [ ] Timer counts down and shows warnings
- [ ] Auto-submit on timer expiry
- [ ] Resume quiz on different device
- [ ] Submit quiz and see scoring
- [ ] Verify correct answers never in page source

---

## Phase 5: Results & Review (8 Prompts)

**Goal:** Complete results display, review, and student-facing features.

### Prompt 5.1: Results Page
```
Read docs/versions/v1.0/features/005-results-review.md.

Create includes/frontend/class-ppq-results-renderer.php:
- render_results($attempt) method
- Overall score (points and percentage)
- Pass/fail indicator
- Time spent
- Correct/incorrect count
- Category breakdown (calculate per-category scores)
- Confidence calibration (% of confident answers that were correct)
- Comparison to average (if enough data)
- Score-banded feedback message
```

### Prompt 5.2: Question Review
```
Add to PPQ_Results_Renderer:
- render_question_review($attempt) method
- Show each question with user's answer
- Correct/incorrect indicators
- Display correct answer(s) (based on quiz settings)
- Per-question feedback
- Per-answer feedback
- Time spent on question
- Confidence indicator
- Use locked revision from attempt time
```

### Prompt 5.3: Results CSS
```
Create assets/css/results.css:
- Results page layout
- Score display (large, prominent)
- Pass/fail styling (green/red)
- Category breakdown chart (CSS bars)
- Question review styling
- Correct/incorrect indicators
- Confidence badge
- Responsive design
```

### Prompt 5.4: My Attempts Page
```
Create rendering for [ppq_my_attempts] shortcode:
- List all completed attempts for current user
- Columns: Quiz, Score, Pass/Fail, Date, Duration
- Filter by quiz, date range
- Sort by date, score
- Click to view full results
- Retake button (if allowed)
- Pagination
```

### Prompt 5.5: Social Sharing
```
Add optional social sharing to results:
- Share buttons (Twitter, Facebook, LinkedIn)
- Customizable share message template
- Include score in share (configurable)
- Open Graph meta tags for shared links
- Admin setting to enable/disable
```

### Prompt 5.6: Email Results
```
Create includes/services/class-ppq-email-service.php:
- send_results($attempt_id, $to_email)
- HTML email template with results summary
- Customizable subject and body (admin settings)
- Tokens: {student_name}, {quiz_title}, {score}, {passed}, {date}
- Optional: send on completion (admin setting)
- Optional: send to student (button on results page)
```

### Prompt 5.7: Results Gutenberg Block
```
Create blocks/my-attempts/:
- block.json with attributes
- Edit component (React)
- Render callback (PHP)
- Attributes: show_score, show_date, per_page
- Preview in editor
```

### Prompt 5.8: Guest Results Access
```
Handle guest results access:
- Generate unique results URL with token
- Store token in attempt record
- Validate token on results page access
- 30-day expiry on result links
- Option to email results link to guest
```

**Phase 5 Testing:**
- [ ] View results after completing quiz
- [ ] See category breakdown
- [ ] Review individual questions
- [ ] Access My Attempts page
- [ ] Filter and sort attempts
- [ ] Share results socially
- [ ] Email results
- [ ] Guest can access results via link

---

## Phase 6: AI Generation (6 Prompts)

**Goal:** Complete AI question generation with user-provided API keys.

### Prompt 6.1: AI Service
```
Read docs/versions/v1.0/features/006-ai-generation.md.

Create includes/services/class-ppq-ai-service.php:
- set_api_key($key) - use encrypted stored key
- generate_questions($content, $params) - main method
- build_prompt($content, $params) - construct prompt
- call_api($prompt) - make OpenAI API call
- parse_response($response) - extract questions from response
- validate_questions($questions) - ensure valid structure
```

### Prompt 6.2: AI Prompts
```
Create optimized prompts for question generation:
- System prompt establishing role and format
- User prompt with content and parameters
- Request JSON output format
- Specify: question count, types, difficulty, categories
- Handle different content types (text, extracted PDF, extracted Word)
- Include examples in prompt for better results
```

### Prompt 6.3: File Upload Processing
```
Add file processing for AI generation:
- Accept PDF and Word (.docx) uploads
- Extract text from PDF (use pdf-parser or similar)
- Extract text from Word (use PhpWord or similar)
- Handle large files (chunk if needed)
- Store extracted text temporarily
- Clean up after processing
```

### Prompt 6.4: AI Generation Interface
```
Add AI generation to question bank admin:
- "Generate with AI" button/section
- Input: paste text OR upload file
- Parameters: count, types, difficulty, categories
- API key status indicator (configured/not configured)
- Generate button with loading state
- Preview generated questions before saving
- Edit any question before adding to bank
```

### Prompt 6.5: AI Response Handling
```
Handle AI responses:
- Parse JSON response (handle markdown code blocks)
- Validate each question structure
- Map to PPQ question format
- Handle partial failures (some questions invalid)
- Show token usage
- Error handling with user-friendly messages
- Retry logic for transient failures
```

### Prompt 6.6: API Key Management
```
Add API key management UI:
- Settings section for OpenAI API key
- Encrypted storage
- Key validation on save (test API call)
- Show key status (valid/invalid/not set)
- Per-user key storage (not global)
- Usage tracking (optional, for user reference)
- Clear key option
```

**Phase 7 Testing:**
- [ ] Save and validate OpenAI API key
- [ ] Generate questions from pasted text
- [ ] Generate questions from PDF upload
- [ ] Generate questions from Word upload
- [ ] Preview and edit generated questions
- [ ] Add generated questions to bank
- [ ] Handle API errors gracefully
- [ ] Rate limiting works

---

## Phase 7: LMS Integrations & Polish (10 Prompts)

**Goal:** Complete LMS integrations, Automator triggers, themes, and final polish.

### Prompt 7.1: LearnDash Integration
```
Read docs/versions/v1.0/features/009-lms-integrations.md.

Create includes/integrations/class-ppq-learndash.php:
- init() checks if LearnDash active
- Add meta box to Lesson/Topic edit screens
- Quiz selector in meta box
- Display quiz at bottom of lesson content
- On quiz pass: mark lesson complete (configurable)
- Map Group Leaders to ppq_teacher role
- Filter teacher's visible students to their LD groups
```

### Prompt 7.2: TutorLMS Integration
```
Create includes/integrations/class-ppq-tutorlms.php:
- Check if TutorLMS active
- Add quiz to course builder
- Display in lesson content
- Completion tracking
- Map Instructors to ppq_teacher role
- Sync with Tutor enrollments
```

### Prompt 7.3: LifterLMS Integration
```
Create includes/integrations/class-ppq-lifterlms.php:
- Check if LifterLMS active
- Meta box on lessons
- Display and completion tracking
- Map Instructors to ppq_teacher role
- Integration with Lifter student management
```

### Prompt 7.4: Uncanny Automator Integration
```
Create includes/integrations/class-ppq-automator.php:
- Register triggers with Automator
- Trigger: User completes quiz (fields: quiz_id, score, passed, user)
- Trigger: User passes quiz
- Trigger: User fails quiz
- Proper token definitions for each field
- Test with Automator Pro and Free
```

### Prompt 7.5: Visual Themes
```
Create three visual themes in assets/css/themes/:
- default.css - Clean, professional, neutral colors
- modern.css - Bold, contemporary, gradient accents
- minimal.css - Sparse, typography-focused, maximum whitespace

Each theme uses CSS custom properties for easy customization.
Include quiz container, questions, answers, timer, progress, results.
All themes must be WCAG 2.1 AA compliant.
```

### Prompt 7.6: Theme Customization
```
Add theme customization to settings:
- Color pickers for primary, secondary, success, error colors
- Font family selector
- Font size options
- Preview panel
- Save as CSS custom properties
- Apply per-quiz or globally
```

### Prompt 7.7: Gutenberg Blocks
```
Create remaining Gutenberg blocks:
- blocks/quiz/ - Quiz display block
- blocks/assigned-quizzes/ - Assigned quizzes block
- Each with: block.json, edit.js, save.js (or render.php)
- Block attributes matching shortcode parameters
- Preview in editor
- Inspector controls for settings
```

### Prompt 7.8: Accessibility Audit
```
Review and fix accessibility:
- Keyboard navigation throughout
- Focus indicators visible
- ARIA labels on interactive elements
- Screen reader announcements for timer, saves, errors
- Form labels properly associated
- Color contrast checks
- Skip links where appropriate
- Test with NVDA or VoiceOver
```

### Prompt 7.9: Translation Preparation
```
Ensure translation readiness:
- Verify ALL user-facing strings use __() or _e()
- Create languages/pressprimer-quiz.pot file
- Translator comments for ambiguous strings
- RTL CSS support
- Date/time localization
- Number formatting localization
```

### Prompt 7.10: Final Polish
```
Final cleanup and optimization:
- Remove all debug code
- Minify CSS and JS for production
- Verify no PHP notices/warnings
- Test on WordPress 6.0 and latest
- Test on PHP 7.4 and 8.3
- Performance check with 1000+ questions
- Security review
- Update version numbers
- Prepare readme.txt for WordPress.org
- Create screenshots
```

**Phase 8 Testing:**
- [ ] LearnDash integration works
- [ ] TutorLMS integration works
- [ ] LifterLMS integration works
- [ ] Automator triggers fire correctly
- [ ] All three themes display properly
- [ ] Theme customization applies
- [ ] Blocks work in Gutenberg
- [ ] Keyboard-only navigation possible
- [ ] Screen reader announces properly
- [ ] No translation strings missed
- [ ] No PHP errors on any page
- [ ] Performance acceptable

---

## Post-Phase Checklist

Before submitting to WordPress.org:

### Code Quality
- [ ] WordPress Coding Standards (run PHPCS)
- [ ] No deprecated functions
- [ ] Proper escaping on all output
- [ ] Proper sanitization on all input
- [ ] Prepared statements for all SQL
- [ ] Nonce verification on all forms

### Security
- [ ] Correct answers never in HTML source
- [ ] All AJAX handlers verify capabilities
- [ ] Rate limiting active
- [ ] API keys encrypted
- [ ] No sensitive data in JavaScript

### Compatibility
- [ ] WordPress 6.0 and latest
- [ ] PHP 7.4, 8.1, 8.4
- [ ] MySQL 5.7 and 8.0
- [ ] Popular themes (Twenty Twenty-Four, Astra, Kadence)
- [ ] LearnDash, TutorLMS, LifterLMS

### Assets
- [ ] All CSS/JS minified
- [ ] No console errors
- [ ] Images optimized
- [ ] Fonts loaded properly

### Documentation
- [ ] readme.txt complete
- [ ] Screenshots captured
- [ ] FAQ populated
- [ ] Changelog started

