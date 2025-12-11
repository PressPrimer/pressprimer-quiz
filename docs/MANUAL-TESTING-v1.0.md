# PressPrimer Quiz v1.0 - Manual Testing Checklist

This document provides a comprehensive manual testing checklist for the PressPrimer Quiz plugin v1.0 free version.

## Pre-Testing Setup

### Environment Requirements
- [ ] Fresh WordPress installation (6.4+)
- [ ] PHP 7.4 or higher
- [ ] Admin user account
- [ ] Test user account (Subscriber role)
- [ ] Optional: LearnDash, TutorLMS, or LifterLMS installed
- [ ] Optional: Uncanny Automator installed

### Initial State
- [ ] Plugin activated successfully without errors
- [ ] Database tables created (check `wp_ppq_*` tables)
- [ ] Admin menu "PressPrimer Quiz" appears
- [ ] No PHP errors in debug log

---

## 1. Plugin Activation & Onboarding

### 1.1 Fresh Activation
- [ ] Activate plugin on fresh install
- [ ] Verify no errors on activation
- [ ] Check database tables exist:
  - `wp_ppq_quizzes`
  - `wp_ppq_questions`
  - `wp_ppq_question_revisions`
  - `wp_ppq_banks`
  - `wp_ppq_bank_questions`
  - `wp_ppq_quiz_items`
  - `wp_ppq_quiz_rules`
  - `wp_ppq_categories`
  - `wp_ppq_attempts`
  - `wp_ppq_attempt_items`
- [ ] Verify custom role "PressPrimer Teacher" created
- [ ] Verify capabilities assigned to Administrator

### 1.2 Onboarding Wizard
- [ ] Onboarding wizard appears on first visit to plugin pages
- [ ] Can progress through all steps
- [ ] Can skip onboarding
- [ ] Skipping with "permanently" option prevents future display
- [ ] Completing onboarding prevents future display
- [ ] Can reset onboarding from settings (if applicable)

---

## 2. Dashboard

### 2.1 Dashboard Display
- [ ] Dashboard page loads without errors
- [ ] Statistics cards display correctly:
  - Total quizzes count
  - Total questions count
  - Total banks count
  - Recent attempts (last 7 days)
- [ ] Pass rate displays correctly
- [ ] Popular quizzes list shows data (when available)
- [ ] Empty state displays appropriately with no data

### 2.2 Dashboard Links
- [ ] "Create Quiz" button works
- [ ] "Create Question" button works
- [ ] Links to other plugin pages function

---

## 3. Questions Management

### 3.1 Questions List Page
- [ ] List page loads without errors
- [ ] Table displays questions with columns:
  - Title/Stem
  - Type
  - Difficulty
  - Categories
  - Status
  - Author
  - Date
- [ ] Pagination works correctly
- [ ] Per-page screen options work
- [ ] Search by stem text works
- [ ] Filter by status works
- [ ] Filter by type works
- [ ] Filter by difficulty works
- [ ] Sort by columns works

### 3.2 Create Question - Multiple Choice
- [ ] "Add New" opens question editor
- [ ] Can enter question stem (with formatting)
- [ ] Can add 2-6 answer options
- [ ] Can mark one answer as correct
- [ ] Can add feedback per answer
- [ ] Can set difficulty level
- [ ] Can set expected completion time
- [ ] Can set max points
- [ ] Can assign categories
- [ ] Can assign tags
- [ ] Save as Draft works
- [ ] Publish works
- [ ] Validation: requires stem
- [ ] Validation: requires at least 2 answers
- [ ] Validation: requires one correct answer

### 3.3 Create Question - Multiple Answer
- [ ] Can select "Multiple Answer" type
- [ ] Can mark multiple answers as correct
- [ ] Validation: requires at least one correct answer
- [ ] Scoring works correctly with partial credit

### 3.4 Create Question - True/False
- [ ] Can select "True/False" type
- [ ] Only shows True and False options
- [ ] Can mark correct answer
- [ ] Saves and displays correctly

### 3.5 Edit Question
- [ ] Can open existing question for editing
- [ ] All fields populate correctly
- [ ] Can modify all fields
- [ ] Save creates new revision
- [ ] Revision history shows previous versions
- [ ] Can view previous revisions

### 3.6 Question Actions
- [ ] Duplicate question works
- [ ] Delete question works (soft delete)
- [ ] Deleted questions hidden from list
- [ ] Bulk select works
- [ ] Bulk delete works

---

## 4. Question Banks

### 4.1 Banks List Page
- [ ] List page loads without errors
- [ ] Table displays banks with columns:
  - Name
  - Description
  - Question Count
  - Owner
  - Date
- [ ] Pagination works
- [ ] Search works
- [ ] Sort by columns works

### 4.2 Create Bank
- [ ] "Add New" opens bank creation page
- [ ] Can enter name and description
- [ ] Save creates bank successfully
- [ ] Redirects to bank detail page

### 4.3 Bank Detail Page
- [ ] Bank info displays correctly
- [ ] Questions in bank listed
- [ ] Can search for questions to add
- [ ] Search filters work (type, difficulty, category)
- [ ] Can add questions to bank
- [ ] Can remove questions from bank
- [ ] Question count updates correctly

### 4.4 Bank Actions
- [ ] Edit bank works
- [ ] Delete bank works
- [ ] Deleting bank does NOT delete questions

---

## 5. Categories & Tags

### 5.1 Categories Page
- [ ] Categories page loads
- [ ] Can create new category
- [ ] Can set parent category (hierarchy)
- [ ] Can edit category
- [ ] Can delete category
- [ ] Question count displays correctly
- [ ] Slug auto-generates from name

### 5.2 Tags Page
- [ ] Tags page loads
- [ ] Can create new tag
- [ ] Can edit tag
- [ ] Can delete tag
- [ ] Question count displays correctly

---

## 6. Quiz Management

### 6.1 Quiz List Page
- [ ] List page loads without errors
- [ ] Table displays quizzes with columns:
  - Title
  - Status
  - Questions count
  - Attempts count
  - Pass Rate
  - Owner
  - Date
- [ ] Pagination works
- [ ] Search works
- [ ] Filter by status works
- [ ] Sort by columns works

### 6.2 Create Quiz - Basic Settings
- [ ] "Add New" opens quiz editor
- [ ] Can enter title
- [ ] Can enter description (with formatting)
- [ ] Can set featured image
- [ ] Can set status (Draft/Published)
- [ ] Save as Draft works
- [ ] Publish works

### 6.3 Quiz Settings - Mode & Timing
- [ ] Can select Tutorial Mode
- [ ] Can select Timed Mode
- [ ] Time limit field appears for Timed Mode
- [ ] Can set time limit in seconds
- [ ] Time limit saves correctly

### 6.4 Quiz Settings - Display
- [ ] Can select Single Page display
- [ ] Can select Paged display
- [ ] Questions per page field appears for Paged
- [ ] Can set questions per page (1-100)

### 6.5 Quiz Settings - Passing & Attempts
- [ ] Can set passing score percentage (0-100)
- [ ] Can set max attempts (1-100)
- [ ] Can set delay between attempts (minutes)
- [ ] Settings save correctly

### 6.6 Quiz Settings - Navigation
- [ ] Allow skip toggle works
- [ ] Allow backward navigation toggle works
- [ ] Allow resume toggle works

### 6.7 Quiz Settings - Randomization
- [ ] Randomize questions toggle works
- [ ] Randomize answers toggle works

### 6.8 Quiz Settings - Results Display
- [ ] Can select "Never show answers"
- [ ] Can select "Show after submit"
- [ ] Can select "Show only after passing"

### 6.9 Quiz Settings - Advanced
- [ ] Enable confidence rating toggle works
- [ ] Guest access settings work
- [ ] Band feedback configuration works

### 6.10 Quiz Questions - Fixed Mode
- [ ] Can add questions from banks
- [ ] Question search/filter works
- [ ] Can add individual questions
- [ ] Can remove questions
- [ ] Can reorder questions (drag & drop)
- [ ] Question count updates

### 6.11 Quiz Questions - Dynamic Mode (if applicable)
- [ ] Can switch to dynamic generation
- [ ] Can create rules for question selection
- [ ] Rules save correctly

### 6.12 Quiz Preview
- [ ] Preview button opens quiz preview
- [ ] Preview shows all questions
- [ ] Preview styling matches frontend

### 6.13 Quiz Actions
- [ ] Edit quiz works
- [ ] Duplicate quiz works
- [ ] Delete quiz works
- [ ] Bulk publish works
- [ ] Bulk draft works
- [ ] Bulk delete works

---

## 7. Frontend Quiz Experience

### 7.1 Quiz Shortcode
- [ ] `[ppq_quiz id="X"]` renders quiz
- [ ] Invalid ID shows appropriate error
- [ ] Unpublished quiz hidden from non-admins

### 7.2 Quiz Landing Page
- [ ] Title displays correctly
- [ ] Description displays correctly
- [ ] Featured image displays (if set)
- [ ] "Start Quiz" button appears
- [ ] Previous attempts listed (if any)
- [ ] Attempt limit message shows (if applicable)
- [ ] Delay message shows (if in cooldown period)

### 7.3 Taking Quiz - Tutorial Mode
- [ ] Questions display correctly
- [ ] All question types render properly
- [ ] Can select answers
- [ ] Answer feedback shows immediately (if configured)
- [ ] Navigation buttons work
- [ ] Skip works (if enabled)
- [ ] Back navigation works (if enabled)
- [ ] Progress indicator accurate
- [ ] Submit quiz button works

### 7.4 Taking Quiz - Timed Mode
- [ ] Timer displays correctly
- [ ] Timer counts down
- [ ] Warning at low time (if configured)
- [ ] Auto-submit on time expiry
- [ ] Time tracked correctly in results

### 7.5 Taking Quiz - Paged Mode
- [ ] Correct number of questions per page
- [ ] Next/Previous page buttons work
- [ ] Progress shows current page
- [ ] Can navigate between pages (if allowed)

### 7.6 Taking Quiz - Single Page Mode
- [ ] All questions display on one page
- [ ] Scroll to navigate
- [ ] Submit button at bottom

### 7.7 Answer Selection
- [ ] Multiple Choice: can select one answer
- [ ] Multiple Choice: selecting new deselects old
- [ ] Multiple Answer: can select multiple
- [ ] Multiple Answer: can deselect
- [ ] True/False: can select one option
- [ ] Confidence rating appears (if enabled)
- [ ] Can set confidence 1-5

### 7.8 Quiz Resume
- [ ] Leaving quiz mid-way saves progress
- [ ] Returning shows "Resume" option
- [ ] Resume restores previous answers
- [ ] Resume restores position
- [ ] Stale attempts auto-abandoned (1 hour)

### 7.9 Quiz Results Page
- [ ] Results page loads after submit
- [ ] Score percentage displays
- [ ] Pass/Fail status displays
- [ ] Points earned vs max points shown
- [ ] Time taken displays
- [ ] Category breakdown shows (if categories used)
- [ ] Confidence analysis shows (if enabled)
- [ ] Band feedback displays based on score
- [ ] Question review shows (if answer display enabled)
- [ ] Correct/incorrect indicators on review
- [ ] Answer feedback shows in review
- [ ] Retry button appears (if attempts remaining)
- [ ] Social sharing buttons work (if enabled)

---

## 8. Guest Quiz Taking

### 8.1 Guest Access (if enabled)
- [ ] Non-logged-in user can access quiz
- [ ] Email prompt appears at start
- [ ] Can proceed with email entry
- [ ] Can proceed without email (if optional)
- [ ] Quiz taking works normally
- [ ] Results page accessible
- [ ] Token-based URL works for results
- [ ] Results email sent (if configured)

---

## 9. My Attempts Shortcode

### 9.1 Shortcode Display
- [ ] `[ppq_my_attempts]` renders attempt list
- [ ] Shows quiz title, date, score, status
- [ ] Pagination works
- [ ] Filter by quiz works
- [ ] Filter by status works
- [ ] Filter by date range works
- [ ] Sort options work
- [ ] Click to view results works
- [ ] Empty state shows for no attempts

---

## 10. Reports

### 10.1 Reports Page
- [ ] Reports page loads
- [ ] Overview statistics display
- [ ] Available reports listed

### 10.2 Quiz Performance Report
- [ ] Report loads
- [ ] Shows quiz-level statistics
- [ ] Attempt counts accurate
- [ ] Average scores accurate
- [ ] Pass rates accurate

### 10.3 Recent Attempts Report
- [ ] Report loads
- [ ] Lists individual attempts
- [ ] Filter by quiz works
- [ ] Filter by date works
- [ ] Filter by user works
- [ ] Pagination works
- [ ] Can view attempt details
- [ ] Attempt detail shows question-level data

---

## 11. Settings

### 11.1 Settings Page Access
- [ ] Settings page loads
- [ ] Only accessible to admins
- [ ] Tabs/sections display correctly

### 11.2 General Settings
- [ ] Settings save correctly
- [ ] Default values appropriate

### 11.3 Quiz Default Settings
- [ ] Can set default passing score
- [ ] Can set default quiz mode
- [ ] Defaults apply to new quizzes

### 11.4 Email Settings
- [ ] Can set From Name
- [ ] Can set From Email
- [ ] Can enable auto-send results
- [ ] Can customize email subject
- [ ] Can customize email body
- [ ] Template tags work in email

### 11.5 API Settings
- [ ] OpenAI API key field works
- [ ] Can save API key (encrypted)
- [ ] Can validate API key
- [ ] Can remove API key
- [ ] Usage stats display (if key set)

### 11.6 Social Sharing Settings
- [ ] Can enable/disable Twitter
- [ ] Can enable/disable Facebook
- [ ] Can enable/disable LinkedIn
- [ ] Can toggle score inclusion
- [ ] Can customize share message

### 11.7 Advanced Settings
- [ ] "Remove data on uninstall" toggle works

---

## 12. AI Question Generation

### 12.1 AI Generation Access
- [ ] AI Generation accessible from Questions page
- [ ] Requires API key to be set
- [ ] Shows helpful message if no API key

### 12.2 Content Input
- [ ] Can paste text directly
- [ ] Can upload PDF file
- [ ] Can upload DOCX file
- [ ] File size limits enforced
- [ ] Character limit enforced (100,000)
- [ ] Text extraction from files works

### 12.3 Generation Process
- [ ] Can initiate generation
- [ ] Progress indicator shows
- [ ] Can cancel generation
- [ ] Error handling for API failures
- [ ] Rate limiting works

### 12.4 Generated Questions
- [ ] Preview of generated questions displays
- [ ] Can select/deselect questions
- [ ] Can edit before saving
- [ ] Can save selected to bank
- [ ] Questions save with correct type
- [ ] Questions save with answers

---

## 13. LMS Integrations

### 13.1 LearnDash Integration (if installed)
- [ ] Integration activates when LearnDash detected
- [ ] Meta box appears on Course edit screen
- [ ] Meta box appears on Lesson edit screen
- [ ] Meta box appears on Topic edit screen
- [ ] Can search and select quiz
- [ ] Can enable "require pass" setting
- [ ] Quiz displays in lesson content
- [ ] Quiz completion tracked
- [ ] Pass triggers LearnDash completion
- [ ] Navigation restriction works (if enabled)

### 13.2 TutorLMS Integration (if installed)
- [ ] Integration activates when TutorLMS detected
- [ ] Quiz option in lesson editor
- [ ] Can select quiz for lesson
- [ ] Can enable "require pass" setting
- [ ] Quiz displays in lesson
- [ ] Completion syncs with TutorLMS
- [ ] Course builder integration works

### 13.3 LifterLMS Integration (if installed)
- [ ] Integration activates when LifterLMS detected
- [ ] PPQ Quiz tab in course builder
- [ ] Can assign quiz to lesson
- [ ] Can set require pass
- [ ] Quiz displays in lesson
- [ ] Completion syncs with LifterLMS

---

## 14. Uncanny Automator Integration

### 14.1 Triggers (if Automator installed)
- [ ] "Quiz Completed" trigger available
- [ ] "Quiz Passed" trigger available
- [ ] "Quiz Failed" trigger available
- [ ] Can select specific quiz
- [ ] Triggers fire correctly on events
- [ ] Trigger data includes quiz and score info

---

## 15. User Roles & Permissions

### 15.1 Administrator
- [ ] Full access to all plugin features
- [ ] Can manage all quizzes/questions/banks
- [ ] Can view all results
- [ ] Can access settings
- [ ] Can manage other users' content

### 15.2 PressPrimer Teacher Role
- [ ] Can access plugin admin pages
- [ ] Can create quizzes
- [ ] Can create questions
- [ ] Can create banks
- [ ] Can only edit own content
- [ ] Cannot see other teachers' content
- [ ] Cannot access settings
- [ ] Can view own students' results

### 15.3 Subscriber
- [ ] Cannot access plugin admin pages
- [ ] Can take quizzes on frontend
- [ ] Can view own results
- [ ] Can use My Attempts shortcode

### 15.4 Logged-Out User
- [ ] Can take quizzes (if guest access enabled)
- [ ] Cannot access any admin features
- [ ] Results accessible via token URL

---

## 16. Error Handling & Edge Cases

### 16.1 Validation Errors
- [ ] Quiz without questions shows error
- [ ] Question without answers shows error
- [ ] Required fields enforced
- [ ] Appropriate error messages display

### 16.2 Edge Cases
- [ ] Empty quiz bank handled gracefully
- [ ] Deleted question in quiz handled
- [ ] User deleted mid-attempt handled
- [ ] Concurrent quiz attempts handled
- [ ] Very long question stems handled
- [ ] Special characters in content handled
- [ ] HTML in allowed fields works
- [ ] Script tags stripped from content

### 16.3 Performance
- [ ] Large quiz (100+ questions) loads
- [ ] Large question bank (500+ questions) usable
- [ ] Reports with many attempts load
- [ ] No timeout on normal operations

---

## 17. Uninstall & Cleanup

### 17.1 Deactivation
- [ ] Plugin deactivates without errors
- [ ] Data preserved on deactivation
- [ ] Re-activation works normally

### 17.2 Uninstall (with data removal enabled)
- [ ] All database tables removed
- [ ] All options removed
- [ ] Custom role removed
- [ ] Capabilities cleaned up
- [ ] No orphaned data remains

### 17.3 Uninstall (with data removal disabled)
- [ ] Database tables preserved
- [ ] Options preserved
- [ ] Can reinstall and data intact

---

## Testing Notes

### Browser Testing
Test in multiple browsers:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

### Mobile Testing
- [ ] Quiz taking works on mobile
- [ ] Admin pages accessible on tablet
- [ ] Touch interactions work

### Accessibility
- [ ] Keyboard navigation works
- [ ] Screen reader compatible
- [ ] Focus indicators visible
- [ ] Color contrast adequate

---

## Issue Tracking

| Issue # | Description | Severity | Status |
|---------|-------------|----------|--------|
| | | | |

**Severity Levels:**
- Critical: Blocks core functionality
- High: Major feature broken
- Medium: Feature partially broken
- Low: Minor issue or cosmetic

---

## Sign-Off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Tester | | | |
| Developer | | | |
| Product Owner | | | |
