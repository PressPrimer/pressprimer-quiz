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
- [ ] LearnPress site configured (v2.0+)
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

### Multiple Answer
- [ ] Create multiple answer question
- [ ] Add multiple answer options
- [ ] Mark multiple correct answers
- [ ] Checkboxes display
- [ ] Partial credit works correctly
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

---

## v2.0 Feature Testing

### Premium Upsell Touchpoints
- [ ] Locked features show padlock icon
- [ ] Tooltip appears on hover
- [ ] Tooltip text is correct for each tier
- [ ] "Learn More" link works
- [ ] Styling doesn't disrupt admin layout
- [ ] Accessible via keyboard (focus shows tooltip)

### Addon Compatibility Hooks
- [ ] `pressprimer_quiz_loaded` action fires
- [ ] `pressprimer_quiz_register_addon` action fires
- [ ] `pressprimer_quiz_has_addon()` returns correct values
- [ ] `pressprimer_quiz_feature_enabled()` filter works
- [ ] Extension point actions fire at correct times
- [ ] No PHP errors when no addons installed

### LearnPress Integration
- [ ] Meta box appears on lesson edit screen
- [ ] Quiz selector shows all published quizzes
- [ ] "Require pass" checkbox saves correctly
- [ ] Quiz renders at bottom of lesson content
- [ ] Enrolled users can take quiz
- [ ] Non-enrolled users see enrollment message
- [ ] Quiz pass triggers lesson completion (when enabled)
- [ ] Course progress updates after lesson completion
- [ ] Works with LearnPress 4.0+
- [ ] No errors with older LearnPress versions

### Require Login Setting
- [ ] Global setting appears in Settings → General
- [ ] Global setting saves correctly
- [ ] Per-quiz override appears in Quiz Builder
- [ ] Per-quiz override saves correctly
- [ ] "Use global default" option works
- [ ] Login message displays correctly for logged-out users
- [ ] Custom login message displays when set
- [ ] Login button redirects to WordPress login
- [ ] After login, user returns to quiz page
- [ ] Guest access still works when set to allow
- [ ] Integration with WooCommerce My Account (if active)

### Condensed Mode
- [ ] Global setting appears in Settings → Appearance
- [ ] Global setting saves correctly
- [ ] Per-quiz override appears in Quiz Builder
- [ ] Per-quiz override saves correctly
- [ ] Default theme displays correctly in condensed mode
- [ ] Modern theme displays correctly in condensed mode
- [ ] Minimal theme displays correctly in condensed mode
- [ ] Mobile layout works with condensed mode
- [ ] Touch targets meet 44px minimum
- [ ] Accessibility requirements maintained
- [ ] Previous attempts collapsible works
- [ ] Results accordions work
- [ ] No horizontal scrolling on mobile
- [ ] Navigation buttons visible without scrolling

---

## v2.1 Feature Testing (When Released)

### 100 Attempts Celebration Notice
- [ ] Notice appears after 100 total attempts
- [ ] Notice only shows on PPQ admin pages
- [ ] "Yes, I love it!" links to WordPress.org
- [ ] "It could be better" links to PressPrimer help desk
- [ ] "Remind me later" dismisses for 30 days
- [ ] "Don't show again" permanently dismisses
- [ ] Dismissal state persists across sessions
- [ ] Count query is accurate

### Visual Appearance Controls
- [ ] Spacing controls appear in Settings
- [ ] Line height controls appear
- [ ] Changes apply to all themes
- [ ] Changes apply to condensed mode
- [ ] Settings save correctly
- [ ] Preview updates in real-time (if implemented)

### Block/Shortcode Attributes
- [ ] `show_start` attribute works
- [ ] `show_results` attribute works
- [ ] `show_timer` attribute works
- [ ] `show_progress` attribute works
- [ ] Attributes work in shortcode
- [ ] Attributes work in block

---

## v2.2 Feature Testing (When Released)

### Question Pool Maximum
- [ ] Pool enabled checkbox appears
- [ ] Max questions field appears when enabled
- [ ] Setting saves correctly
- [ ] Quiz respects max question limit
- [ ] Questions are randomized from pool
- [ ] Works with dynamic quiz rules
- [ ] Works with fixed quiz items
- [ ] Edge case: max greater than available questions

### Cache Clearing Button
- [ ] Button appears on Settings page
- [ ] Button clears transients
- [ ] Success message displays
- [ ] No PHP errors

### Attempt Pagination
- [ ] Pagination appears when >10 attempts
- [ ] Previous/Next links work
- [ ] Page numbers display correctly
- [ ] Works in admin attempts list
- [ ] Works in frontend My Attempts

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
- [ ] Condensed mode works on mobile (v2.0+)

---

## LMS Integrations

### LearnDash Integration
- [ ] Meta box appears on LearnDash lessons
- [ ] Quiz selector works
- [ ] Quiz embeds in lesson
- [ ] Passing quiz completes lesson (if set)
- [ ] Course progress updates
- [ ] Works with LearnDash 3.x+

### TutorLMS Integration
- [ ] Quiz block available in TutorLMS lessons
- [ ] Quiz embeds correctly
- [ ] Passing quiz completes lesson (if set)
- [ ] Works with TutorLMS 2.x+

### LifterLMS Integration
- [ ] Quiz block available in LifterLMS lessons
- [ ] Quiz embeds correctly
- [ ] Passing quiz completes lesson (if set)
- [ ] Works with LifterLMS 6.x+

### LearnPress Integration (v2.0+)
- [ ] Meta box appears on LearnPress lessons
- [ ] Quiz selector works
- [ ] Quiz embeds at end of lesson content
- [ ] Enrolled users can take quiz
- [ ] Non-enrolled users see message
- [ ] Passing quiz completes lesson (if set)
- [ ] Works with LearnPress 4.0+

### Standalone Operation
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
- [ ] Correct answers never in page source

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

### v2.0.0 Release Testing

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
