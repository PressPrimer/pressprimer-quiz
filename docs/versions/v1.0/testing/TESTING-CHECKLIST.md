# PressPrimer Quiz - Testing Checklist

**Purpose**: Comprehensive testing checklist for all features before each release.

**How to Use**: 
- Copy this checklist for each release
- Test on fresh WordPress install
- Test with each target LMS platform
- Mark items complete as you test
- Document any issues found

---

## Pre-Testing Setup

### Test Environment Preparation
- [ ] Fresh WordPress 6.0 installation
- [ ] Fresh WordPress 6.4 installation (current)
- [ ] PHP 7.4 environment
- [ ] PHP 8.0 environment
- [ ] PHP 8.1 environment
- [ ] PHP 8.2 environment

### LMS Platform Testing Sites
- [ ] LearnDash site configured
- [ ] LifterLMS site configured
- [ ] TutorLMS site configured
- [ ] Standalone WordPress (no LMS)

### Test User Accounts
- [ ] Administrator account
- [ ] Teacher/Instructor account
- [ ] Student account
- [ ] Subscriber account (no quiz permissions)

---

## Installation & Activation

### Fresh Installation
- [ ] Install from .zip file
- [ ] Plugin appears in plugin list
- [ ] Activate plugin successfully
- [ ] No PHP errors in debug log
- [ ] No JavaScript errors in console
- [ ] Admin menu items appear correctly
- [ ] Database tables created (if applicable)
- [ ] Default settings initialized

### Upgrade from Previous Version
- [ ] Backup test site before upgrade
- [ ] Upgrade via WordPress admin
- [ ] Database migration runs successfully
- [ ] Existing quizzes still work
- [ ] Existing settings preserved
- [ ] No data loss
- [ ] No PHP errors during upgrade

### Deactivation
- [ ] Deactivate plugin
- [ ] No PHP errors
- [ ] Admin menu items removed
- [ ] Frontend quizzes show appropriate message

### Uninstallation
- [ ] Test on disposable site
- [ ] Uninstall via WordPress admin
- [ ] Plugin files removed
- [ ] Database tables removed (if cleanup enabled)
- [ ] Options cleaned up
- [ ] No orphaned data

---

## Core Quiz Functionality

### Quiz Creation (Admin)
- [ ] Create new quiz via admin
- [ ] Quiz title saves correctly
- [ ] Quiz settings save correctly
- [ ] Quiz permalink works
- [ ] Quiz appears in quiz list
- [ ] Can edit quiz after creation
- [ ] Can duplicate quiz
- [ ] Can trash quiz
- [ ] Can permanently delete quiz
- [ ] Can restore from trash

### Quiz Builder Interface
- [ ] Quiz builder loads without errors
- [ ] Interface is responsive (mobile/tablet/desktop)
- [ ] All UI elements visible and functional
- [ ] Save button works
- [ ] Auto-save works (if implemented)
- [ ] Undo/redo works (if implemented)
- [ ] Loading states display correctly
- [ ] Success/error messages display

### Question Management
- [ ] Add question button works
- [ ] Question appears in list
- [ ] Can reorder questions (drag/drop)
- [ ] Can edit question text
- [ ] Can delete question
- [ ] Question deletion shows confirmation
- [ ] Question numbering updates correctly
- [ ] Can add media to questions (images)
- [ ] Media uploads successfully
- [ ] Media displays in quiz preview

---

## Question Types Testing

### Multiple Choice
- [ ] Create multiple choice question
- [ ] Add 2-10 answer options
- [ ] Mark correct answer(s)
- [ ] Single correct answer works
- [ ] Multiple correct answers work
- [ ] Radio buttons for single answer
- [ ] Checkboxes for multiple answers
- [ ] Answer text displays correctly
- [ ] Can reorder answers
- [ ] Can delete answers
- [ ] Correct answer validation works

### True/False
- [ ] Create true/false question
- [ ] Only two options appear
- [ ] Can select correct answer
- [ ] Options cannot be edited/added
- [ ] Displays correctly on frontend
- [ ] Grading works correctly

### Short Answer
- [ ] Create short answer question
- [ ] Text input appears on frontend
- [ ] Can set correct answer(s)
- [ ] Case sensitivity option works
- [ ] Multiple acceptable answers work
- [ ] Grading works correctly
- [ ] Partial credit works (if implemented)

### Essay/Long Answer
- [ ] Create essay question
- [ ] Textarea appears on frontend
- [ ] Character/word limit works (if set)
- [ ] Requires manual grading
- [ ] Teacher can grade essay
- [ ] Teacher can add feedback
- [ ] Student can view feedback

### Fill in the Blank
- [ ] Create fill-in-blank question
- [ ] Blank indicators work
- [ ] Multiple blanks per question work
- [ ] Can set correct answer per blank
- [ ] Case sensitivity option works
- [ ] Grading works correctly

### Matching
- [ ] Create matching question
- [ ] Add pairs of items
- [ ] Can reorder pairs
- [ ] Frontend displays correctly
- [ ] Drag-and-drop works (if implemented)
- [ ] Dropdown matching works
- [ ] Grading works correctly
- [ ] All pairs must be matched

### Ordering/Sequencing
- [ ] Create ordering question
- [ ] Add items to order
- [ ] Can set correct sequence
- [ ] Frontend randomizes order
- [ ] Drag-and-drop works
- [ ] Grading works correctly

### Hotspot/Image Click
- [ ] Create hotspot question
- [ ] Upload image
- [ ] Define clickable regions
- [ ] Frontend displays image
- [ ] Click detection works
- [ ] Multiple hotspots work
- [ ] Grading works correctly

---

## Quiz Settings

### General Settings
- [ ] Quiz title edits save
- [ ] Quiz description saves
- [ ] Quiz status (draft/published) works
- [ ] Featured image uploads
- [ ] Featured image displays
- [ ] Permalink customization works
- [ ] Quiz categories work (if implemented)
- [ ] Quiz tags work (if implemented)

### Timing Settings
- [ ] Time limit can be set
- [ ] Time limit displays on quiz
- [ ] Timer counts down correctly
- [ ] Quiz auto-submits at time limit
- [ ] No time limit option works
- [ ] Time per question works (if implemented)
- [ ] Overtime penalties work (if implemented)

### Attempts & Retakes
- [ ] Unlimited attempts works
- [ ] Limited attempts setting works
- [ ] Attempt count enforced correctly
- [ ] "No more attempts" message shows
- [ ] Reset attempts button works (teacher)
- [ ] Attempt history displays correctly

### Passing & Grading
- [ ] Pass percentage can be set
- [ ] Pass/fail calculated correctly
- [ ] Grade displays correctly
- [ ] Letter grades work (if implemented)
- [ ] Points-based grading works
- [ ] Percentage-based grading works
- [ ] Pass/fail message displays

### Question Behavior
- [ ] Random question order works
- [ ] Fixed question order works
- [ ] Random answer order works (per question type)
- [ ] Show one question at a time works
- [ ] Show all questions works
- [ ] Previous/Next navigation works
- [ ] Question review works
- [ ] Can skip questions (if allowed)

### Results & Feedback
- [ ] Show results immediately works
- [ ] Hide results until attempt complete works
- [ ] Show correct answers option works
- [ ] Hide correct answers works
- [ ] Show score/percentage works
- [ ] Custom pass message displays
- [ ] Custom fail message displays
- [ ] Question-level feedback works

### Access Control
- [ ] Require login option works
- [ ] Public quiz access works
- [ ] Scheduled start date works
- [ ] Scheduled end date works
- [ ] Password protection works
- [ ] Role restrictions work
- [ ] Group restrictions work (if implemented)

---

## Frontend Quiz Taking

### Quiz Discovery
- [ ] Quiz appears in quiz list
- [ ] Quiz search works
- [ ] Quiz categories filter works
- [ ] Quiz featured image displays
- [ ] Quiz excerpt displays
- [ ] "Start Quiz" button appears

### Starting a Quiz
- [ ] Click "Start Quiz" works
- [ ] Login required if set
- [ ] Redirect to login works
- [ ] Password prompt appears if set
- [ ] Correct password allows access
- [ ] Wrong password shows error
- [ ] Attempt count displays
- [ ] Timer starts (if set)

### Taking the Quiz
- [ ] Questions display correctly
- [ ] Question numbers display
- [ ] Images display correctly
- [ ] Answer inputs work (radio/checkbox/text)
- [ ] Can select/change answers
- [ ] Navigation buttons work (Next/Previous)
- [ ] Progress indicator works
- [ ] Timer displays and counts down
- [ ] Can review answers before submit
- [ ] Submit button appears
- [ ] Confirmation prompt on submit

### Quiz Submission
- [ ] Submit button works
- [ ] Confirmation required (if set)
- [ ] Can cancel submission
- [ ] Quiz saves on submit
- [ ] Loading indicator shows
- [ ] Success message displays
- [ ] Redirects to results (if set)

### Viewing Results
- [ ] Results page loads
- [ ] Score displays correctly
- [ ] Percentage displays correctly
- [ ] Pass/fail status correct
- [ ] Correct answers shown (if allowed)
- [ ] Incorrect answers marked
- [ ] Explanations display (if set)
- [ ] Can review all questions
- [ ] Can retake (if allowed)

### Mobile Responsiveness
- [ ] Quiz displays on mobile (375px width)
- [ ] Quiz displays on tablet (768px width)
- [ ] Touch interactions work
- [ ] No horizontal scrolling
- [ ] Buttons are tappable
- [ ] Text is readable
- [ ] Images scale correctly

---

## Teacher/Instructor Features

### Quiz Management
- [ ] Can create quizzes
- [ ] Can edit own quizzes
- [ ] Cannot edit others' quizzes (if restricted)
- [ ] Can delete own quizzes
- [ ] Can duplicate quizzes
- [ ] Can preview quizzes

### Student Management
- [ ] Can view student list
- [ ] Can view student attempts
- [ ] Can view individual student results
- [ ] Can reset student attempts
- [ ] Can manually grade essays
- [ ] Can add feedback to attempts
- [ ] Can export student data

### Grading Interface
- [ ] Pending grades list displays
- [ ] Can filter by quiz
- [ ] Can filter by student
- [ ] Essay grading interface works
- [ ] Can assign points/grades
- [ ] Can add written feedback
- [ ] Save and continue works
- [ ] Batch grading works (if implemented)

### Reports & Analytics
- [ ] Quiz statistics display
- [ ] Average score calculated correctly
- [ ] Pass rate calculated correctly
- [ ] Question difficulty analysis works
- [ ] Time to complete statistics accurate
- [ ] Can export reports (CSV/PDF)
- [ ] Date range filtering works

---

## Group Management

### Group Creation
- [ ] Can create groups
- [ ] Group name saves
- [ ] Group description saves
- [ ] Can edit groups
- [ ] Can delete groups

### Group Membership
- [ ] Can add students to groups
- [ ] Can remove students from groups
- [ ] Students can be in multiple groups
- [ ] Group member list displays
- [ ] Can search/filter members

### Quiz Assignment to Groups
- [ ] Can assign quiz to group
- [ ] Only group members see quiz
- [ ] Non-members cannot access
- [ ] Can assign to multiple groups
- [ ] Can make quiz available to all

---

## AI Question Generation

### OpenAI Integration
- [ ] Settings page for API key
- [ ] Can save API key
- [ ] API key validation works
- [ ] Error message if invalid key
- [ ] API key encrypted in database

### Generate Questions
- [ ] "Generate with AI" button appears
- [ ] Topic input field works
- [ ] Question count selector works
- [ ] Difficulty selector works
- [ ] Generate button triggers API call
- [ ] Loading indicator shows
- [ ] Questions generated successfully
- [ ] Questions added to quiz
- [ ] Can edit generated questions
- [ ] Can delete generated questions

### Error Handling
- [ ] API rate limit error handled
- [ ] Invalid API key error shown
- [ ] Network error handled gracefully
- [ ] Timeout error handled
- [ ] Malformed response handled

---

## WordPress Integration

### Custom Post Type
- [ ] Quiz post type registered
- [ ] Appears in admin menu
- [ ] Custom icon displays
- [ ] Supports required features
- [ ] Permalink structure works
- [ ] Archive page works (if enabled)
- [ ] Single quiz page works

### REST API
- [ ] Endpoints registered
- [ ] Authentication required
- [ ] Permissions checked
- [ ] Data validation works
- [ ] Error responses correct
- [ ] Rate limiting works (if implemented)

### Shortcodes
- [ ] [pressprimer_quiz id="X"] works
- [ ] [pressprimer_quiz_list] works
- [ ] [pressprimer_my_results] works
- [ ] Shortcodes work in posts
- [ ] Shortcodes work in pages
- [ ] Shortcodes work in widgets
- [ ] Invalid parameters handled

### Gutenberg Blocks
- [ ] Quiz block available in editor
- [ ] Block settings panel works
- [ ] Block preview displays
- [ ] Block saves correctly
- [ ] Block renders on frontend
- [ ] Multiple blocks per page work

### Widgets
- [ ] Quiz widget available
- [ ] Widget settings save
- [ ] Widget displays in sidebar
- [ ] Widget displays in footer
- [ ] Multiple widgets work

---

## LMS Integration Testing

### LearnDash Integration
- [ ] Quizzes appear in LearnDash courses
- [ ] Quiz completion tracked
- [ ] Course progress updated
- [ ] Certificates triggered (if set)
- [ ] LearnDash groups integration works
- [ ] Reporting integrates with LearnDash

### LifterLMS Integration
- [ ] Quizzes appear in LifterLMS courses
- [ ] Quiz completion tracked
- [ ] Course progress updated
- [ ] Achievements triggered (if set)
- [ ] LifterLMS groups integration works
- [ ] Reporting integrates with LifterLMS

### TutorLMS Integration
- [ ] Quizzes appear in TutorLMS courses
- [ ] Quiz completion tracked
- [ ] Course progress updated
- [ ] Certificates triggered (if set)
- [ ] TutorLMS groups integration works
- [ ] Reporting integrates with TutorLMS

### Standalone Mode (No LMS)
- [ ] All features work without LMS
- [ ] Native group management works
- [ ] Native teacher roles work
- [ ] Reporting works independently

---

## Performance Testing

### Load Testing
- [ ] 100 questions in single quiz works
- [ ] 1,000 student attempts performs acceptably
- [ ] Large media files don't break quiz
- [ ] Multiple quizzes on same page work
- [ ] Quiz list with 100+ quizzes performs well

### Caching
- [ ] Quiz data cached appropriately
- [ ] Cache invalidates on quiz update
- [ ] Page cache compatible
- [ ] Object cache compatible
- [ ] Transients used correctly

### Database Queries
- [ ] No N+1 query issues
- [ ] Queries optimized with indexes
- [ ] Large result sets paginated
- [ ] Query Monitor shows acceptable query count

---

## Security Testing

### Input Validation
- [ ] Quiz titles sanitized
- [ ] Question text sanitized
- [ ] Answer text sanitized
- [ ] XSS prevention works
- [ ] SQL injection prevention works
- [ ] HTML allowed only where appropriate

### Nonce Verification
- [ ] All form submissions use nonces
- [ ] AJAX requests verify nonces
- [ ] Nonces expire correctly
- [ ] Invalid nonce shows error

### Capability Checks
- [ ] Admin functions check admin capability
- [ ] Teacher functions check appropriate capability
- [ ] Student functions check appropriate capability
- [ ] Subscribers cannot access restricted features

### Data Exposure
- [ ] Other students' answers not visible
- [ ] Other students' grades not visible
- [ ] API endpoints require authentication
- [ ] Direct file access blocked

### File Upload Security
- [ ] File type validation works
- [ ] File size limits enforced
- [ ] Malicious files rejected
- [ ] Uploaded files not executable

---

## Accessibility Testing

### Keyboard Navigation
- [ ] Can tab through all form fields
- [ ] Focus indicators visible
- [ ] Enter key submits forms
- [ ] Escape key closes modals
- [ ] Can complete entire quiz with keyboard only

### Screen Reader Testing
- [ ] Quiz structure announced correctly
- [ ] Question numbers announced
- [ ] Answer options announced
- [ ] Error messages announced
- [ ] Success messages announced
- [ ] ARIA labels present and correct

### Color Contrast
- [ ] All text meets WCAG AA standards (4.5:1)
- [ ] Links distinguishable from text
- [ ] Error messages not color-only
- [ ] Success messages not color-only

### Focus Management
- [ ] Focus moves logically
- [ ] Modal focus trapped
- [ ] Focus returns after modal close
- [ ] Skip links work

---

## Browser & Device Testing

### Desktop Browsers
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Chrome (1 version old)
- [ ] Firefox (1 version old)

### Mobile Browsers
- [ ] Safari iOS (latest)
- [ ] Chrome Android (latest)
- [ ] Firefox Android (latest)
- [ ] Samsung Internet

### Devices
- [ ] iPhone (latest iOS)
- [ ] iPad (latest iOS)
- [ ] Android phone (latest)
- [ ] Android tablet (latest)

### Screen Sizes
- [ ] 320px (small mobile)
- [ ] 375px (iPhone)
- [ ] 768px (tablet)
- [ ] 1024px (small desktop)
- [ ] 1920px (large desktop)

---

## Localization & Internationalization

### Text Domain
- [ ] All strings use correct text domain
- [ ] No hardcoded English strings
- [ ] Pluralization handled correctly
- [ ] Context added where needed

### Translation Functions
- [ ] __() used for simple strings
- [ ] _e() used for echoed strings
- [ ] _n() used for plurals
- [ ] _x() used for context

### RTL Support
- [ ] Interface displays correctly in RTL
- [ ] Text alignment correct
- [ ] Icons flip correctly (if applicable)

### Translation Files
- [ ] .pot file generated
- [ ] .pot file up to date
- [ ] Sample translations work
- [ ] String extraction works

---

## WordPress Coding Standards

### PHP Code Standards
- [ ] PHPCS WordPress-Core passes
- [ ] PHPCS WordPress-Extra passes
- [ ] No PHP warnings/notices
- [ ] PHP 7.4+ compatibility verified
- [ ] PHP 8.2 compatibility verified

### JavaScript Standards
- [ ] ESLint passes (if configured)
- [ ] No console errors
- [ ] No console warnings
- [ ] Modern JS syntax used appropriately

### CSS Standards
- [ ] No CSS validation errors
- [ ] Vendor prefixes included
- [ ] Mobile-first approach
- [ ] No !important unless necessary

---

## Documentation

### User Documentation
- [ ] README.md complete
- [ ] Installation instructions clear
- [ ] Screenshots included
- [ ] FAQ section complete
- [ ] Changelog up to date

### Developer Documentation
- [ ] Code comments present
- [ ] PHPDoc blocks complete
- [ ] Hook documentation complete
- [ ] API documentation complete

### WordPress.org Assets
- [ ] Icon 256x256 created
- [ ] Banner 1544x500 created
- [ ] Screenshots captured
- [ ] readme.txt complete
- [ ] readme.txt validated

---

## Pre-Release Checklist

### Code Quality
- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] Code coverage >70%
- [ ] No TODO comments in production code
- [ ] No debug code left in

### Version Control
- [ ] All changes committed
- [ ] Commit messages descriptive
- [ ] Version number updated in all files
- [ ] Git tag created
- [ ] Changelog updated

### Build Process
- [ ] Production build created
- [ ] Assets minified
- [ ] Source maps removed (or kept if desired)
- [ ] Unnecessary files excluded (.git, tests, etc.)

### Final Testing
- [ ] Fresh install on production-like environment
- [ ] Upgrade from previous version tested
- [ ] All critical paths tested
- [ ] No errors in PHP error log
- [ ] No errors in JavaScript console

---

## Post-Release Monitoring

### First 24 Hours
- [ ] Monitor WordPress.org support forum
- [ ] Monitor error logs (if telemetry implemented)
- [ ] Check for PHP errors in user reports
- [ ] Monitor activation/installation rates

### First Week
- [ ] Review all support tickets
- [ ] Check for common issues
- [ ] Monitor reviews/ratings
- [ ] Prepare hotfix if needed

---

## Issue Tracking Template

When issues are found during testing, document using this format:
```
### Issue #[NUMBER]

**Title**: [Brief description]

**Severity**: Critical / High / Medium / Low

**Steps to Reproduce**:
1. [Step 1]
2. [Step 2]
3. [Step 3]

**Expected Result**:
[What should happen]

**Actual Result**:
[What actually happens]

**Environment**:
- WordPress: [version]
- PHP: [version]
- Browser: [name and version]
- LMS: [name and version if applicable]

**Screenshots/Videos**:
[Attach if applicable]

**Status**: Open / In Progress / Testing / Closed

**Fix Version**: [version number when fixed]
```

---

## Testing Sign-Off

### v1.0.0 Release Testing

**Tested By**: ___________________
**Date**: ___________________
**Environment**: ___________________

**Test Results Summary**:
- Total Tests: ___
- Passed: ___
- Failed: ___
- Blocked: ___

**Critical Issues Found**: ___ (must be 0 for release)

**Release Approved**: [ ] Yes [ ] No

**Approver**: ___________________
**Date**: ___________________

---

## Notes

- This checklist should be customized based on features actually implemented
- Not all items may apply to every version
- Add new test cases as features are added
- Remove test cases for features not implemented
- Keep this checklist under version control
- Update after each release based on issues found