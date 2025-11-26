# Release Process

**Last Updated**: 2025-01-24  
**For**: PressPrimer Quiz WordPress Plugin

---

## Overview

This document outlines the complete release process for PressPrimer Quiz, from preparing a release to deploying to WordPress.org and post-release monitoring.

**Release Frequency:**
- **Major versions** (1.0, 2.0): Every 6-12 months
- **Minor versions** (1.1, 1.2): Every 1-2 months
- **Patch versions** (1.0.1, 1.0.2): As needed for bugs

**Deployment Target:**
- WordPress.org Plugin Directory (free version)
- PressPrimer.com (premium addons - future)

---

## Semantic Versioning

We follow [Semantic Versioning](https://semver.org/): `MAJOR.MINOR.PATCH`

### Version Number Guidelines

**MAJOR version** (X.0.0):
- Breaking changes to public API
- Removal of deprecated features
- Major architectural changes
- Database schema changes requiring migration
- Minimum WordPress/PHP version increases

Examples:
- `1.0.0` → Initial release
- `2.0.0` → Major rewrite with breaking changes

**MINOR version** (1.X.0):
- New features (backward compatible)
- New question types
- New integrations
- Significant UI improvements
- New API endpoints

Examples:
- `1.1.0` → Add question bank feature
- `1.2.0` → Add quiz randomization
- `1.3.0` → Add analytics dashboard

**PATCH version** (1.0.X):
- Bug fixes
- Security patches
- Performance improvements
- Translation updates
- Documentation updates

Examples:
- `1.0.1` → Fix quiz saving bug
- `1.0.2` → Security patch for XSS vulnerability
- `1.0.3` → Fix PHP 8.2 compatibility issue

---

## Pre-Release Checklist (1-2 Weeks Before)

### Week Before Release

#### 1. Feature Freeze

**What to do:**
- Stop accepting new features
- Merge all completed feature branches to `develop`
- Create `release/X.Y.Z` branch from `develop`

**In GitHub Desktop:**
1. Switch to `develop` branch
2. Pull latest changes
3. Branch → New Branch
4. Name: `release/1.1.0`
5. Base: `develop`
6. Create branch

**Or in terminal:**
```bash
git checkout develop
git pull
git checkout -b release/1.1.0
git push -u origin release/1.1.0
```

#### 2. Update Version Numbers

Update version in all locations:

**Files to update:**
- `pressprimer-quiz.php` (main plugin file header)
- `readme.txt` (Stable tag)
- `package.json` (version field)
- `composer.json` (version field)

**Using find and replace in Warp/Claude Code:**
```bash
claude-code chat

> Update version numbers from 1.0.0 to 1.1.0 in:
> - pressprimer-quiz.php (Version header)
> - readme.txt (Stable tag)
> - package.json (version field)
> - composer.json (version field)
>
> Show me the changes before applying them.
```

**Manual update example:**

`pressprimer-quiz.php`:
```php
/**
 * Version: 1.1.0
 */
define( 'PRESSPRIMER_QUIZ_VERSION', '1.1.0' );
```

`readme.txt`:
```
Stable tag: 1.1.0
```

#### 3. Update Changelog

**In `readme.txt`:**
```
== Changelog ==

= 1.1.0 - 2025-02-01 =
* New: Question bank feature for reusable questions
* New: Random question selection from banks
* Improved: Quiz builder UI with better drag-and-drop
* Fixed: Timer not starting on some mobile devices
* Fixed: Essay question grading interface layout

= 1.0.2 - 2025-01-28 =
* Fixed: PHP 8.2 deprecation warnings
* Fixed: Quiz results not showing for some users
```

**In `CHANGELOG.md`:**
```markdown
# Changelog

All notable changes to PressPrimer Quiz will be documented in this file.

## [1.1.0] - 2025-02-01

### Added
- Question bank feature for creating reusable question libraries
- Random question selection from question banks
- Ability to tag and categorize questions
- Import/export functionality for question banks

### Improved
- Quiz builder UI with enhanced drag-and-drop experience
- Better mobile responsiveness for admin interface
- Performance optimization for large quizzes (100+ questions)

### Fixed
- Timer not starting correctly on iOS Safari
- Essay question grading interface layout issues
- Quiz duplication not copying all settings

### Security
- Enhanced input sanitization for question text
- Improved nonce verification for AJAX requests

## [1.0.2] - 2025-01-28

### Fixed
- PHP 8.2 deprecation warnings
- Quiz results page not displaying for non-admin users
- Memory issues with very large result sets

### Security
- Patched XSS vulnerability in quiz title display
```

**Changelog format:**
- Group by type: Added, Changed, Deprecated, Removed, Fixed, Security
- Be specific but concise
- Link to issues when relevant: "Fixed quiz saving bug (#42)"
- Highlight breaking changes in **bold**

#### 4. Run Complete Test Suite

**Automated tests:**
```bash
# PHP unit tests
composer test

# PHP coding standards
composer phpcs

# JavaScript tests
npm test

# JavaScript linting
npm run lint:js

# Build production assets
npm run build
```

**All tests must pass before proceeding.**

**If tests fail:**
```bash
claude-code chat

> These tests are failing:
> [paste test output]
>
> Fix the failing tests and ensure all pass.
```

#### 5. Manual Testing

Follow the complete testing checklist:

```bash
# Open testing checklist
cat docs/testing/testing-checklist.md
```

**Priority testing areas:**
- Quiz creation and editing
- Quiz taking (frontend)
- Results and grading
- All question types
- Mobile devices
- Different browsers
- LMS integrations

**Create test report:**
```markdown
# Release 1.1.0 Test Report
Date: 2025-01-28

## Environment
- WordPress 6.4
- PHP 8.1
- LearnDash 4.10
- Chrome 121, Safari 17, Firefox 122

## Tests Completed
- [x] Quiz creation
- [x] All 8 question types
- [x] Quiz taking (desktop)
- [x] Quiz taking (mobile)
- [x] Grading system
- [x] Reports
- [x] LMS integrations

## Issues Found
1. Minor: Timer display off by 1 second - Not blocking
2. Minor: Mobile layout slightly off in Safari - Fixed

## Recommendation
✅ Ready for release
```

#### 6. Update Documentation

**User documentation:**
- Update screenshots if UI changed
- Add docs for new features
- Update FAQs

**Developer documentation:**
- Update API docs if endpoints changed
- Update hook reference if hooks added/changed
- Update code examples

**WordPress.org assets:**
- Create/update screenshots (`.wordpress-org/screenshot-X.png`)
- Update banner if needed (`.wordpress-org/banner-1544x500.png`)
- Update icon if changed (`.wordpress-org/icon-256x256.png`)

#### 7. Translation Preparation

**Generate .pot file:**
```bash
# Using WP-CLI
wp i18n make-pot . languages/pressprimer-quiz.pot

# Or use npm script (if configured)
npm run makepot
```

**Upload to translation service:**
- Upload to translate.wordpress.org (after release)
- Or use GlotPress
- Or provide to translation team

#### 8. Security Audit

**Review for security issues:**
- All input sanitized
- All output escaped
- Nonces verified on all forms
- Capability checks on all restricted functions
- SQL queries use $wpdb->prepare()
- File upload validation
- No sensitive data exposed in API

**Consider security scan:**
```bash
# Using WPScan (if you have it set up)
wpscan --url http://quiz-plugin-dev.local --enumerate vp
```

---

## Release Day Process

### Morning of Release

#### 1. Final Checks (30 minutes)

**Verify everything is ready:**
```bash
# Pull latest release branch
git checkout release/1.1.0
git pull

# Verify version numbers
grep "Version:" pressprimer-quiz.php
grep "Stable tag:" readme.txt
grep "\"version\"" package.json

# Run tests one more time
composer test
npm test

# Build production assets
npm run build

# Check build output
ls -la assets/dist/
```

#### 2. Create Git Tag (5 minutes)

**In GitHub Desktop:**
1. Ensure you're on `release/1.1.0` branch
2. All changes committed
3. Repository → Create Tag
4. Tag name: `1.1.0` (no 'v' prefix for WordPress.org compatibility)
5. Tag message: `Release version 1.1.0`
6. Create tag
7. Push tag: Repository → Push Tags

**Or in terminal:**
```bash
# Create annotated tag
git tag -a 1.1.0 -m "Release version 1.1.0

New Features:
- Question bank system
- Random question selection

Bug Fixes:
- Timer issue on mobile
- Grading interface layout

See CHANGELOG.md for full details"

# Push tag
git push origin 1.1.0
```

**This triggers automated deployment via GitHub Actions!**

#### 3. Monitor GitHub Actions (10-15 minutes)

**Check deployment progress:**
1. Go to GitHub repository
2. Click "Actions" tab
3. Find "Deploy to WordPress.org" workflow
4. Watch progress

**Workflow steps:**
1. ✓ Checkout code
2. ✓ Install dependencies
3. ✓ Run tests
4. ✓ Build production assets
5. ✓ Deploy to WordPress.org SVN

**If deployment fails:**
- Check error logs in GitHub Actions
- Common issues:
  - SVN credentials expired (update in GitHub Secrets)
  - Build errors (fix and create new tag)
  - Network timeout (retry)

#### 4. Verify WordPress.org Deployment (10 minutes)

**Check plugin page:**
1. Visit: `https://wordpress.org/plugins/pressprimer-quiz/`
2. Verify version number updated
3. Check changelog displays correctly
4. Verify download link works
5. Check screenshots display

**Test fresh install:**
```bash
# In a clean Local site
wp plugin install pressprimer-quiz --activate

# Verify version
wp plugin list | grep pressprimer-quiz
```

#### 5. Merge to Main Branch (5 minutes)

**After successful WordPress.org deployment:**

**In GitHub Desktop:**
1. Switch to `main` branch
2. Branch → Merge into Current Branch
3. Select `release/1.1.0`
4. Click "Merge"
5. Push to origin

**Or in terminal:**
```bash
git checkout main
git merge release/1.1.0
git push origin main
```

#### 6. Merge Back to Develop (5 minutes)

**Keep develop in sync:**

**In GitHub Desktop:**
1. Switch to `develop` branch
2. Branch → Merge into Current Branch
3. Select `release/1.1.0`
4. Click "Merge"
5. Push to origin

**Or in terminal:**
```bash
git checkout develop
git merge release/1.1.0
git push origin develop
```

#### 7. Delete Release Branch (Optional)

**Clean up:**
```bash
git branch -d release/1.1.0
git push origin --delete release/1.1.0
```

---

## Post-Release Tasks

### Within 1 Hour of Release

#### 1. Create GitHub Release

**On GitHub:**
1. Go to repository
2. Click "Releases"
3. Click "Draft a new release"
4. Choose tag: `1.1.0`
5. Release title: `Version 1.1.0 - Question Banks`
6. Description: Copy from CHANGELOG.md
7. Attach .zip file (optional)
8. Click "Publish release"

**Example release notes:**
```markdown
# Version 1.1.0 - Question Banks

Released: February 1, 2025

## 🎉 New Features

- **Question Banks**: Create reusable question libraries
- **Random Selection**: Select random questions from banks
- **Question Import/Export**: Easily share question sets

## 🐛 Bug Fixes

- Fixed timer not starting on iOS Safari
- Fixed essay question grading layout
- Fixed quiz duplication issue

## 📚 Documentation

- [User Guide](https://pressprimer.com/docs/question-banks)
- [API Documentation](https://pressprimer.com/docs/api)
- [Developer Guide](https://github.com/pressprimer/quiz/wiki)

## 📦 Installation

Download from [WordPress.org](https://wordpress.org/plugins/pressprimer-quiz/) or update via your WordPress admin.

For full changelog, see [CHANGELOG.md](CHANGELOG.md)
```

#### 2. Announce Release

**WordPress.org:**
- Post in support forum
- Announcement shows on plugin page automatically

**Social media:**
- Twitter/X
- LinkedIn
- WordPress community Slack

**Example announcement:**
```
🚀 PressPrimer Quiz v1.1.0 is now available!

New in this release:
✨ Question Banks for reusable questions
🎲 Random question selection
📦 Import/export functionality

Plus bug fixes and performance improvements.

Download: https://wordpress.org/plugins/pressprimer-quiz/
Changelog: [link]

#WordPress #Education #LMS
```

**Email list:**
- Notify existing users
- Highlight new features
- Link to documentation

#### 3. Update Website

**PressPrimer.com:**
- Update version number on homepage
- Add blog post about new release
- Update documentation
- Update screenshots/videos if changed

**Update links:**
- Documentation links
- Download links
- Demo site

---

### Within 24 Hours of Release

#### 1. Monitor Support Channels

**Check frequently:**
- WordPress.org support forum
- GitHub issues
- Email support
- Social media mentions

**Look for:**
- Installation problems
- Upgrade issues
- Bug reports
- Feature requests
- Questions about new features

#### 2. Monitor Error Logs

**If you have error tracking:**
- Check Sentry/Rollbar/Bugsnag
- Look for spikes in errors
- Check for new error types
- Monitor JavaScript errors

**Check server logs:**
- PHP errors
- Database errors
- API errors

#### 3. Check Analytics

**WordPress.org stats:**
- Download count
- Active installs
- Rating changes

**Website analytics:**
- Traffic to docs
- Support requests
- Demo site usage

#### 4. Quick Response Plan

**If critical bug found:**

**Step 1: Assess severity**
- Critical: Breaks plugin, data loss, security issue
- High: Major feature broken, affects many users
- Medium: Minor feature issue, affects some users
- Low: Cosmetic issue, affects few users

**Step 2: For critical bugs**
```bash
# Immediately start working on fix
git checkout main
git pull
git checkout -b hotfix/1.1.1

# Fix the bug
claude-code chat
> Critical bug: [describe bug]
> Fix this immediately and create tests to prevent regression

# Test thoroughly
composer test

# Update version to 1.1.1
# Update changelog with fix

# Commit and tag
git add .
git commit -m "fix: critical bug in question saving"
git push origin hotfix/1.1.1

# Create tag for emergency release
git tag -a 1.1.1 -m "Hotfix: Critical bug in question saving"
git push origin 1.1.1
```

**Step 3: Notify users**
- Post in support forum
- Email notification (if critical)
- Social media update

---

### Within 1 Week of Release

#### 1. Collect Feedback

**Review all feedback:**
- Support forum threads
- GitHub issues
- Email feedback
- Social media comments
- Review ratings/comments

**Categorize:**
- Bugs to fix
- Features requested
- Documentation gaps
- UI/UX improvements

#### 2. Update Documentation

**Based on common questions:**
- Add FAQ entries
- Clarify confusing sections
- Add more examples
- Create video tutorials

#### 3. Plan Next Release

**Create roadmap for next version:**
- Prioritize bugs from feedback
- Consider feature requests
- Plan improvements
- Set timeline

**Update documentation:**
```markdown
# v1.2.0 Roadmap (Target: March 2025)

## Planned Features
- Advanced quiz analytics
- Quiz templates
- Better mobile admin experience

## Bug Fixes
- Address issues from 1.1.0 feedback

## Timeline
- February: Development
- Early March: Testing
- Mid March: Release
```

---

## Emergency Hotfix Process

### When to Release a Hotfix

Release a hotfix immediately for:
- **Security vulnerabilities**
- **Data loss bugs**
- **Critical functionality broken**
- **PHP fatal errors**
- **Database corruption issues**

### Hotfix Release Process (Fast Track)

**Timeline: 2-4 hours from discovery to release**

#### 1. Create Hotfix Branch (5 minutes)

```bash
# Branch from main (not develop)
git checkout main
git pull
git checkout -b hotfix/1.1.1
```

#### 2. Fix the Bug (30-60 minutes)

```bash
claude-code chat

> CRITICAL BUG: [detailed description]
>
> Error: [paste error message]
>
> This is affecting production users.
>
> Fix this bug immediately:
> 1. Identify root cause
> 2. Implement fix
> 3. Add test to prevent regression
> 4. Verify fix works
```

#### 3. Test Thoroughly (30 minutes)

**Minimum testing:**
```bash
# Run automated tests
composer test
npm test

# Test the specific bug fix manually
# Test related functionality
# Quick smoke test of major features
```

#### 4. Update Version and Changelog (10 minutes)

**Increment patch version:**
- `1.1.0` → `1.1.1`

**Update changelog:**
```
= 1.1.1 - 2025-02-02 =
* Fixed: Critical bug causing quiz data loss on save
* Security: Patched XSS vulnerability in quiz title
```

#### 5. Deploy (15 minutes)

```bash
# Commit changes
git add .
git commit -m "fix: critical bug causing data loss"

# Create tag
git tag -a 1.1.1 -m "Hotfix: Critical bug causing data loss

CRITICAL: This fixes a data loss issue when saving quizzes.
All users should update immediately."

# Push
git push origin hotfix/1.1.1
git push origin 1.1.1

# GitHub Actions will deploy automatically
```

#### 6. Notify Users Immediately (15 minutes)

**WordPress.org support forum:**
```
URGENT: Security Update - Please Update to 1.1.1 Immediately

A critical security vulnerability was discovered in version 1.1.0 
that could allow [description]. This has been fixed in version 1.1.1.

All users should update immediately via Dashboard → Updates.

Details: [brief technical description]
CVE: [if assigned]
```

**Email to users (if you have list):**
```
Subject: URGENT: Security Update Required

A critical security issue has been discovered and fixed.

Please update PressPrimer Quiz to version 1.1.1 immediately.

Update via WordPress Dashboard → Updates

Thank you,
PressPrimer Team
```

#### 7. Merge Hotfix Back (10 minutes)

```bash
# Merge to main
git checkout main
git merge hotfix/1.1.1
git push origin main

# Merge to develop
git checkout develop
git merge hotfix/1.1.1
git push origin develop

# Delete hotfix branch
git branch -d hotfix/1.1.1
git push origin --delete hotfix/1.1.1
```

---

## Version Number Reference

### Example Version Progression

```
1.0.0  → Initial release
1.0.1  → Bug fix (timer issue)
1.0.2  → Bug fix (PHP 8.2 compatibility)
1.1.0  → New feature (question banks)
1.1.1  → Bug fix (data loss in question banks)
1.2.0  → New feature (quiz templates)
1.2.1  → Bug fix (template import issue)
2.0.0  → Major update (breaking changes, new architecture)
2.0.1  → Bug fix
2.1.0  → New feature
```

### Beta/RC Versions (Optional)

For major releases, consider beta/RC:

```
2.0.0-beta.1  → First beta
2.0.0-beta.2  → Second beta
2.0.0-rc.1    → Release candidate 1
2.0.0-rc.2    → Release candidate 2
2.0.0         → Final release
```

**Deploy betas to:**
- GitHub releases only (not WordPress.org)
- Separate testing site
- Beta testers group

---

## Rollback Procedure

### If Release Goes Wrong

**Symptoms:**
- Critical bugs reported by multiple users
- Plugin breaking sites
- Data loss reports
- Cannot be fixed with quick hotfix

**Rollback steps:**

#### 1. Revert on WordPress.org (15 minutes)

**Restore previous version in SVN:**
```bash
# Connect to WordPress.org SVN
cd ~/pressprimer-quiz-svn
svn up

# Copy previous version back to trunk
svn cp tags/1.0.0 trunk --force
svn commit -m "Rolling back to 1.0.0 due to critical issues in 1.1.0"

# This makes 1.0.0 the "current" version again
```

#### 2. Notify Users (Immediate)

**WordPress.org forum:**
```
NOTICE: Version 1.1.0 Rolled Back

We've rolled back to version 1.0.0 due to critical issues.

If you're experiencing problems:
1. Go to Dashboard → Plugins
2. Delete PressPrimer Quiz
3. Reinstall from WordPress.org (will install 1.0.0)

We're working on fixes and will release 1.1.1 soon.

We apologize for the inconvenience.
```

#### 3. Fix Issues (As long as needed)

```bash
# Create new hotfix from last stable version
git checkout 1.0.0
git checkout -b hotfix/1.0.3

# Fix all issues reported in 1.1.0
# Increment from last stable version
# Skip the broken version number entirely
```

#### 4. Re-release When Ready

- Skip the broken version (1.1.0 becomes historical mistake)
- Release as next patch version (1.0.3)
- Or incorporate into next minor version (1.2.0)
- Document what went wrong in changelog

---

## Release Checklist Template

**Copy this for each release:**

```markdown
# Release X.Y.Z Checklist

## Pre-Release (1 week before)
- [ ] Feature freeze - all features merged to develop
- [ ] Create release/X.Y.Z branch
- [ ] Update version numbers (plugin file, readme, package.json, composer.json)
- [ ] Update changelog (readme.txt and CHANGELOG.md)
- [ ] Run complete test suite (all passing)
- [ ] Manual testing complete (checklist followed)
- [ ] Documentation updated
- [ ] Screenshots updated (if needed)
- [ ] .pot file generated
- [ ] Security audit complete

## Release Day
- [ ] Final test run (all passing)
- [ ] Production build created (npm run build)
- [ ] Git tag created (X.Y.Z)
- [ ] Tag pushed to GitHub
- [ ] GitHub Actions deployment successful
- [ ] WordPress.org page updated
- [ ] Fresh install tested
- [ ] Merge release branch to main
- [ ] Merge release branch to develop
- [ ] Delete release branch

## Post-Release (same day)
- [ ] GitHub Release created
- [ ] Announcement posted (WordPress.org forum)
- [ ] Social media announcement
- [ ] Website updated
- [ ] Documentation updated
- [ ] Email sent to users

## Post-Release (24 hours)
- [ ] Support channels monitored
- [ ] Error logs checked
- [ ] Analytics reviewed
- [ ] No critical bugs reported

## Post-Release (1 week)
- [ ] Feedback collected and categorized
- [ ] Documentation updated based on questions
- [ ] Next release planned
- [ ] Roadmap updated
```

---

## Automation Configuration

### GitHub Actions Workflow

**File: `.github/workflows/deploy.yml`**

Already configured to deploy automatically when you push a tag!

**What it does:**
1. Detects new tag (X.Y.Z format)
2. Checks out code
3. Installs dependencies
4. Runs tests
5. Builds production assets
6. Removes development files
7. Deploys to WordPress.org SVN
8. Updates WordPress.org assets

**Required secrets:**
- `SVN_USERNAME` - Your WordPress.org username
- `SVN_PASSWORD` - Your WordPress.org password

**Configure in GitHub:**
1. Repository → Settings → Secrets → Actions
2. Add secrets if not already added

### Version Bump Scripts (Optional)

**Create npm script for version bumping:**

**In `package.json`:**
```json
{
  "scripts": {
    "version:patch": "npm version patch && npm run version:sync",
    "version:minor": "npm version minor && npm run version:sync",
    "version:major": "npm version major && npm run version:sync",
    "version:sync": "node scripts/sync-version.js"
  }
}
```

**Create `scripts/sync-version.js`:**
```javascript
const fs = require('fs');
const pkg = require('../package.json');

// Update plugin file
let pluginFile = fs.readFileSync('pressprimer-quiz.php', 'utf8');
pluginFile = pluginFile.replace(/Version: .*/, `Version: ${pkg.version}`);
pluginFile = pluginFile.replace(/PRESSPRIMER_QUIZ_VERSION', '.*'/, `PRESSPRIMER_QUIZ_VERSION', '${pkg.version}'`);
fs.writeFileSync('pressprimer-quiz.php', pluginFile);

// Update readme.txt
let readme = fs.readFileSync('readme.txt', 'utf8');
readme = readme.replace(/Stable tag: .*/, `Stable tag: ${pkg.version}`);
fs.writeFileSync('readme.txt', readme);

console.log(`✓ Version bumped to ${pkg.version}`);
```

**Usage:**
```bash
# Bump patch version (1.0.0 → 1.0.1)
npm run version:patch

# Bump minor version (1.0.0 → 1.1.0)
npm run version:minor

# Bump major version (1.0.0 → 2.0.0)
npm run version:major
```

---

## Common Issues and Solutions

### Issue: GitHub Actions Deployment Fails

**Solutions:**
1. Check SVN credentials in GitHub Secrets
2. Check SVN password hasn't expired
3. Check WordPress.org plugin page not locked
4. Check network/timeout issues (retry)
5. Check logs in GitHub Actions for specific error

### Issue: WordPress.org Not Showing New Version

**Solutions:**
1. Wait 15-30 minutes (can take time to propagate)
2. Clear WordPress.org cache
3. Check SVN commit was successful
4. Check readme.txt "Stable tag" is correct

### Issue: Users Report Update Not Available

**Solutions:**
1. WordPress.org updates are cached for 12 hours
2. Users can force check: Dashboard → Updates → Check Again
3. Wait for WordPress cron to run
4. Verify version on WordPress.org shows correctly

### Issue: Tag Already Exists

**Solution:**
```bash
# Delete local tag
git tag -d 1.1.0

# Delete remote tag
git push origin --delete 1.1.0

# Fix issues and recreate tag
git tag -a 1.1.0 -m "Release 1.1.0"
git push origin 1.1.0
```

---

## Release Calendar Template

**Plan releases in advance:**

```markdown
# 2025 Release Calendar

## Q1 (Jan-Mar)
- v1.1.0 - February 1
  - Question banks
  - Random selection
  
- v1.1.1 - February 15 (if needed for bugs)

- v1.2.0 - March 15
  - Quiz templates
  - Advanced analytics

## Q2 (Apr-Jun)
- v1.3.0 - May 1
  - Mobile app integration
  - Enhanced reports

- v2.0.0 - June 15 (Major release)
  - Complete UI redesign
  - Performance improvements
  - New question types

## Q3 (Jul-Sep)
- v2.1.0 - August 1
  - Premium addons launch
  - Marketplace integration

## Q4 (Oct-Dec)
- v2.2.0 - October 15
- v2.3.0 - December 1 (Year-end features)
```

---

## Summary

### For Regular Releases

1. **One week before**: Feature freeze, create release branch, test
2. **Release day**: Tag, automated deploy, merge branches
3. **After release**: Announce, monitor, support

### For Hotfixes

1. **Immediately**: Branch from main, fix, test
2. **2-4 hours**: Tag, deploy, notify users urgently
3. **Merge back**: To main and develop

### Key Points

- **Always test thoroughly** before tagging
- **Version numbers follow semver** strictly
- **Changelog is detailed** and user-friendly
- **Automation handles deployment** (via GitHub Actions)
- **Monitor closely** after every release
- **Quick response** to critical bugs

**Your release process is now documented and repeatable!**
