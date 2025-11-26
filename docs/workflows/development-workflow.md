# Development Workflow with Claude Code

**Last Updated**: 2025-01-24  
**For**: PressPrimer Quiz v1.0 Development

---

## Overview

This document describes the complete development workflow for building PressPrimer Quiz using:
- **Claude Code**: AI-assisted development
- **GitHub Desktop**: Version control
- **Local by Flywheel**: WordPress testing
- **Warp Terminal**: Command-line interface
- **This Documentation**: Requirements and architecture

---

## Daily Development Workflow

### Morning Routine (5 minutes)

**1. Open Your Tools**
```bash
# Open Warp terminal
# Open GitHub Desktop
# Start Local site (quiz-plugin-dev)
# Open browser to http://quiz-plugin-dev.local/wp-admin
```

**2. Check Status**

In **Warp**:
```bash
cd ~/Development/wordpress-plugins/pressprimer-quiz
git status
```

In **GitHub Desktop**:
- Check for any uncommitted changes
- Pull latest changes (if working with others)

**3. Review Today's Goal**

Check your session notes or roadmap:
```bash
# If you keep session notes
cat docs/sessions/current-sprint.md
```

---

## Feature Development Cycle

### Step 1: Review Requirements (5-10 minutes)

**Identify what you're building:**
```bash
cd ~/Development/wordpress-plugins/pressprimer-quiz
```

**Read the relevant documentation:**
- Feature requirements: `docs/v1.0/features/00X-feature-name.md`
- Architecture: `docs/v1.0/architecture/`
- Hooks needed: `docs/v2.0/hooks-needed-in-v1.md`

**Example - Building Quiz Builder:**
```
Today's task: Implement quiz builder UI (Feature 001)

Read:
- docs/v1.0/features/001-quiz-builder.md
- docs/architecture/frontend-architecture.md
- docs/architecture/class-structure.md
```

### Step 2: Create Feature Branch (30 seconds)

**In GitHub Desktop:**
1. Branch → New Branch
2. Name: `feature/quiz-builder-ui`
3. Base: `develop`
4. Click "Create Branch"

**Or in Warp:**
```bash
git checkout develop
git pull
git checkout -b feature/quiz-builder-ui
```

### Step 3: Start Claude Code (1 minute)

**In Warp:**
```bash
cd ~/Development/wordpress-plugins/pressprimer-quiz
claude-code chat
```

**You'll see:**
```
>
```

This means Claude Code is ready for your commands.

### Step 4: Give Claude Code Context

**Pattern: Reference docs, state goal, let Claude Code work**

**Example - Starting Quiz Builder:**
```
> I'm building the quiz builder feature for PressPrimer Quiz.
>
> Read the following documentation:
> @docs/v1.0/features/001-quiz-builder.md
> @docs/architecture/frontend-architecture.md
> @docs/architecture/class-structure.md
>
> Start by creating the React component structure for the quiz builder admin interface. Follow the class naming conventions from class-structure.md and use the React patterns from frontend-architecture.md.
>
> Create:
> 1. Main QuizBuilder.tsx component
> 2. QuestionList.tsx component
> 3. QuestionEditor.tsx component
> 4. Basic CSS using BEM methodology
> 5. Entry point in assets/src/admin/quiz-builder.tsx
```

**Claude Code will:**
1. Read all referenced documentation
2. Understand the requirements
3. Create the files
4. Show you each change before making it
5. Ask for confirmation

### Step 5: Review and Approve Changes

**Claude Code shows you each change:**
```
Create file: assets/src/admin/components/QuizBuilder.tsx
---
import { useState } from '@wordpress/element';
...
---

[A]pprove, [R]eject, [M]odify, or [V]iew full file?
```

**Your options:**
- Type `A` and press Enter to approve
- Type `R` to reject and ask for changes
- Type `M` to modify the approach
- Type `V` to see the complete file

**Tip**: Check your **Warp sidebar** - you'll see new files appear in real-time!

### Step 6: Iterate and Refine

**After Claude creates the basic structure:**
```
> The QuizBuilder component looks good. Now add the auto-save functionality using the pattern from frontend-architecture.md.
>
> Requirements:
> - Auto-save every 30 seconds
> - Debounce rapid changes (500ms)
> - Show "Saving..." indicator
> - Handle errors gracefully
```

**Then:**
```
> Add drag-and-drop question reordering using react-beautiful-dnd
```

**Then:**
```
> Create the question editor component for multiple-choice questions
```

**Build incrementally, testing as you go.**

### Step 7: Test in Local WordPress (Every 30 minutes)

**Check your work in WordPress:**

1. Open: `http://quiz-plugin-dev.local/wp-admin`
2. Navigate to: Quizzes → Add New
3. Test the features you just built
4. Check browser console for errors (F12 → Console)
5. Check PHP errors: `wp-content/debug.log`

**Common issues:**
- **Assets not loading**: Run `npm run build` in terminal
- **PHP errors**: Check `debug.log` in Local site
- **JavaScript errors**: Check browser console

### Step 8: Make Adjustments

**If you find issues:**
```
> The save button isn't working. When I click it, nothing happens. Check the event handler in QuizBuilder.tsx and fix the issue.
```

**Or:**
```
> The quiz title field isn't showing up in the admin. Debug this:
> 1. Check if the component is rendering
> 2. Check if WordPress is enqueueing the script
> 3. Check the PHP file that registers the admin page
```

**Claude Code will:**
- Search relevant files
- Find the issue
- Propose a fix
- Implement it after your approval

### Step 9: Write Tests

**Once the feature works:**
```
> Create PHPUnit tests for the quiz builder functionality.
>
> Test:
> 1. Quiz creation saves correctly
> 2. Questions are added to quiz
> 3. Question reordering works
> 4. Quiz settings save correctly
> 5. Permissions are checked (only teachers/admins can create)
>
> Follow the test patterns in tests/test-sample.php
```

**Then:**
```
> Create React component tests using React Testing Library for the QuizBuilder component
```

**Run tests:**
```bash
# Exit Claude Code (Ctrl+D)
composer test
npm test
```

### Step 10: Commit Your Work (Every 1-2 hours)

**In GitHub Desktop:**

1. Review changes in the left panel
2. Check diffs look correct
3. Write commit message:
```
   feat: implement quiz builder UI
   
   - Add QuizBuilder React component
   - Add QuestionList and QuestionEditor
   - Implement drag-and-drop reordering
   - Add auto-save functionality
   - Add BEM CSS styles
   - Create component tests
   
   Related to #1
```
4. Click "Commit to feature/quiz-builder-ui"
5. Click "Push origin" (top right)

**Or in Warp:**
```bash
git add .
git commit -m "feat: implement quiz builder UI

- Add QuizBuilder React component
- Add QuestionList and QuestionEditor
- Implement drag-and-drop reordering
- Add auto-save functionality"

git push -u origin feature/quiz-builder-ui
```

---

## Common Development Scenarios

### Scenario 1: Starting from Scratch

**Goal**: Build the complete quiz builder from nothing
```bash
# In Warp
cd ~/Development/wordpress-plugins/pressprimer-quiz
claude-code chat

> I'm starting development of PressPrimer Quiz. This is a brand new WordPress plugin.
>
> Read all documentation in:
> @docs/00-project-vision.md
> @docs/v1.0/README.md
> @docs/architecture/class-structure.md
> @docs/architecture/database-schema.md
>
> Create the complete plugin structure:
> 1. Main plugin file with headers
> 2. Directory structure (includes/, assets/, tests/)
> 3. Composer.json with autoloading
> 4. Package.json with @wordpress/scripts
> 5. Basic plugin class structure
> 6. Database table creation on activation
> 7. .gitignore
> 8. README.md
>
> Use PHP 7.4+ syntax, WordPress coding standards, and PSR-4 autoloading.
```

### Scenario 2: Implementing a Feature

**Goal**: Add a specific feature from requirements
```bash
claude-code chat

> Implement Feature 002: Question Types
>
> Read:
> @docs/v1.0/features/002-question-types.md
> @docs/architecture/class-structure.md
>
> Create all 8 question type classes:
> - Multiple Choice
> - True/False
> - Short Answer
> - Essay
> - Fill in Blank
> - Matching
> - Ordering
> - Hotspot
>
> Follow the interface pattern and use the namespace from class-structure.md
```

### Scenario 3: Fixing a Bug

**Goal**: Debug and fix an issue
```bash
claude-code chat

> There's a bug: When I save a quiz, the questions aren't being saved to the database.
>
> Debug this issue:
> 1. Check the AJAX handler for quiz saving
> 2. Check if data is being sent from React correctly
> 3. Check the PHP function that saves questions
> 4. Check the database schema for the questions table
> 5. Identify the bug and fix it
>
> After fixing, create a test that reproduces the bug and verifies the fix.
```

### Scenario 4: Refactoring Code

**Goal**: Improve code quality without changing functionality
```bash
claude-code chat

> The quiz-display.php file has grown too large (500+ lines).
>
> Refactor it:
> 1. Extract the question rendering logic into separate class (Question_Renderer)
> 2. Extract the timer logic into separate class (Quiz_Timer)
> 3. Extract the navigation logic into separate class (Quiz_Navigation)
> 4. Update the main display class to use these new classes
> 5. Ensure all functionality still works
> 6. Update tests if needed
```

### Scenario 5: Adding a New API Endpoint

**Goal**: Extend the REST API
```bash
claude-code chat

> Add a new REST API endpoint for exporting quiz results to CSV.
>
> Reference:
> @docs/v1.0/architecture/rest-api.md
>
> Endpoint: GET /quizzes/:id/export
> 
> Requirements:
> - Check permissions (teachers/admins only)
> - Include all attempt data
> - Format as CSV with headers
> - Include student name, email, score, date
> - Add to REST_API class following existing patterns
> - Create tests for the endpoint
```

### Scenario 6: Implementing a Complex UI Feature

**Goal**: Build the AI question generation modal
```bash
claude-code chat

> Implement the AI question generation feature.
>
> Read:
> @docs/v1.0/features/006-ai-question-generation.md
> @docs/architecture/frontend-architecture.md
>
> Create:
> 1. AIGenerateModal.tsx React component
> 2. Form fields: topic, count, difficulty, question types
> 3. API integration with OpenAI (user provides API key)
> 4. Loading state with progress indicator
> 5. Error handling (invalid API key, rate limits)
> 6. Display generated questions in preview
> 7. "Add to Quiz" button to insert questions
> 8. PHP REST endpoint for generation
>
> Use @wordpress/components for UI elements.
```

---

## Working with Documentation

### When to Read vs. Reference

**Read thoroughly BEFORE starting:**
- Project vision (once, at beginning)
- Feature requirements (each feature)
- Architecture decisions (once per area)

**Reference DURING development:**
- Class structure (for naming)
- Hooks reference (for extensibility)
- API documentation (for endpoints)
- Frontend architecture (for patterns)

**Update DURING development:**
- Requirements (when you make decisions)
- Decision records (for major changes)
- Session notes (daily progress)

### Updating Requirements

**When you change your mind:**
```bash
claude-code chat

> I've decided to change the quiz builder from 5 steps to 3 steps (simpler UX).
>
> Update:
> @docs/v1.0/features/001-quiz-builder.md
>
> Changes:
> - Reduce from 5 steps to 3 steps
> - Combine "Account Setup" and "Preferences" into "Quick Setup"
> - Add changelog entry explaining the change
> - Update all step references in the document
>
> Then refactor the code to match the updated requirements.
```

**Claude Code will:**
1. Update the markdown document
2. Update the code
3. Update tests
4. Show you all changes

### Creating Decision Records

**For major architectural decisions:**
```bash
claude-code chat

> Create a decision record for choosing React over Vue for the admin interface.
>
> Use the template at @docs/templates/decision-record-template.md
>
> Decision: Use React for admin interface
> Context: Need to choose a JavaScript framework
> Considered: React, Vue, Vanilla JS
> Decision: React
> Rationale: WordPress already bundles React, better ecosystem, AI tools work better with React
>
> Save to: docs/v1.0/decisions/001-use-react-for-admin.md
```

---

## Testing Workflow

### Running Tests Locally

**PHP Tests:**
```bash
# In Warp
cd ~/Development/wordpress-plugins/pressprimer-quiz

# Run all tests
composer test

# Run specific test file
./vendor/bin/phpunit tests/test-quiz-builder.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage/
```

**JavaScript Tests:**
```bash
# Run React component tests
npm test

# Run with watch mode
npm test -- --watch

# Run with coverage
npm test -- --coverage
```

**Coding Standards:**
```bash
# Check PHP coding standards
composer phpcs

# Auto-fix PHP issues
composer phpcbf

# Check JavaScript standards
npm run lint:js

# Auto-fix JavaScript issues
npm run lint:js -- --fix
```

### Manual Testing Checklist

After implementing a feature, test manually:

1. **Create fresh quiz**
   - Admin → Quizzes → Add New
   - Fill in all fields
   - Add questions
   - Save

2. **Take quiz as student**
   - Log out
   - Visit quiz URL
   - Complete quiz
   - Submit
   - View results

3. **Check as teacher**
   - View attempt in admin
   - Grade essay questions (if any)
   - Check reports

4. **Test edge cases**
   - Leave fields empty
   - Submit without answers
   - Test with expired timer
   - Test with max attempts reached

---

## Handling Errors

### PHP Errors

**Check error log:**
```bash
# In Warp
tail -f ~/Local\ Sites/quiz-plugin-dev/app/public/wp-content/debug.log
```

**Ask Claude Code to fix:**
```
> I'm getting this PHP error:
> Fatal error: Call to undefined function pressprimer_quiz_get_settings() in includes/class-quiz-display.php on line 45
>
> Find and fix this error.
```

### JavaScript Errors

**Check browser console:**
- Open DevTools (F12)
- Console tab
- Look for red errors

**Ask Claude Code:**
```
> I'm getting this JavaScript error in the console:
> Uncaught TypeError: Cannot read property 'title' of undefined at QuizBuilder.tsx:23
>
> Debug and fix this error in the QuizBuilder component.
```

### Build Errors

**If `npm run build` fails:**
```
> The webpack build is failing with this error:
> [paste error]
>
> Fix the webpack configuration or source files to resolve this.
```

---

## Git Workflow Best Practices

### Commit Messages

**Format:**
```
type: brief description

- Detailed change 1
- Detailed change 2
- Detailed change 3

Related to #issue_number
```

**Types:**
- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation only
- `style:` Code style (formatting, no logic change)
- `refactor:` Code restructuring
- `test:` Adding or updating tests
- `chore:` Build process, dependencies

**Examples:**
```
feat: add quiz timer functionality

- Create QuizTimer component
- Add countdown display
- Auto-submit on time expiration
- Add warning at 60 seconds remaining

Related to #12
```
```
fix: quiz save button not working

- Add missing event handler
- Fix nonce verification
- Add error handling

Fixes #23
```

### When to Commit

**Commit when:**
- ✅ Feature is complete and tested
- ✅ Bug is fixed and verified
- ✅ Refactoring is done and tests pass
- ✅ Reaching a stable checkpoint
- ✅ End of work session

**Don't commit when:**
- ❌ Code has syntax errors
- ❌ Tests are failing
- ❌ Feature is half-done
- ❌ Debugging code is still present

### Branch Strategy

**For solo development:**
```
main         (production - deployed to WordPress.org)
  ↓
develop      (staging - completed features)
  ↓
feature/*    (work in progress)
```

**Creating branches:**
```bash
# Always branch from develop
git checkout develop
git pull
git checkout -b feature/quiz-timer
```

**Merging back:**
1. Push feature branch
2. Test thoroughly
3. In GitHub Desktop: Branch → Merge into current branch → Select develop
4. Push develop
5. Delete feature branch

### Keeping Up to Date

**Daily:**
```bash
git checkout develop
git pull
```

**Before starting feature:**
```bash
git checkout develop
git pull
git checkout -b feature/new-feature
```

---

## Claude Code Pro Tips

### 1. Be Specific with File References
```
❌ Bad: "Fix the bug in the quiz file"
✅ Good: "Fix the bug in includes/class-quiz-display.php where timer isn't starting"
```

### 2. Reference Multiple Documents
```
> Read the requirements from @docs/v1.0/features/001-quiz-builder.md
> AND the architecture from @docs/architecture/class-structure.md
> AND the hooks reference from @docs/architecture/hooks-reference.md
>
> Then implement the quiz builder with all required hooks.
```

### 3. Ask for Explanations
```
> Before implementing the grading system, explain your approach:
> 1. How will you structure the grading classes?
> 2. How will you handle partial credit?
> 3. How will you store grades in the database?
> 4. What hooks will you add for extensibility?
```

### 4. Iterate Incrementally
```
> Start with just the basic quiz creation form (title, description)
[Claude creates it, you approve]

> Now add the settings panel (time limit, passing score)
[Claude adds it]

> Now add the questions section with add/remove functionality
[Claude adds it]
```

### 5. Request Best Practices
```
> Implement the quiz timer, following WordPress and React best practices:
> - Use WordPress @wordpress/element hooks
> - Follow the patterns from @docs/architecture/frontend-architecture.md
> - Add proper TypeScript types
> - Include accessibility attributes
> - Add comprehensive comments
```

### 6. Ask for Tests
```
> The QuizBuilder component is done. Now create comprehensive tests:
> - Unit tests for helper functions
> - Component tests for user interactions
> - Integration tests for API calls
> - Include edge cases and error scenarios
```

---

## Weekly Workflow

### Monday: Plan the Week
```bash
# Review v1.0 roadmap
cat docs/v1.0/README.md

# Update session notes
code docs/sessions/2025-01-week-4.md
```

**Set weekly goals:**
- Complete Feature 001: Quiz Builder
- Start Feature 002: Question Types
- Write tests for completed features
- Fix any critical bugs

### Tuesday-Thursday: Development

- 2-3 hours focused development per day
- Commit at least once per day
- Test thoroughly as you build
- Update documentation when making decisions

### Friday: Review and Clean Up
```bash
# Run all tests
composer test
npm test

# Check coding standards
composer phpcs
npm run lint:js

# Review week's commits in GitHub Desktop
# Merge completed features to develop
# Update roadmap with progress
```

---

## Troubleshooting Common Issues

### Issue: Claude Code Can't Find Files

**Solution:**
```bash
# Make sure you're in the project directory
pwd
# Should show: /Users/Ryan/Development/wordpress-plugins/pressprimer-quiz

# If not, navigate there:
cd ~/Development/wordpress-plugins/pressprimer-quiz
```

### Issue: Changes Not Showing in WordPress

**Solutions:**
```bash
# 1. Rebuild assets
npm run build

# 2. Clear WordPress cache
# In WordPress admin: Dashboard → Clear Cache (if caching plugin installed)

# 3. Hard refresh browser
# Mac: Cmd+Shift+R
# Windows: Ctrl+Shift+R

# 4. Check if plugin is activated
# In WordPress admin: Plugins → Ensure PressPrimer Quiz is activated
```

### Issue: Git Conflicts

**In GitHub Desktop:**
1. You'll see "Resolve conflicts" message
2. Click the conflicted file
3. Choose: Keep yours, Keep theirs, or Manually resolve
4. Save and mark as resolved
5. Commit the merge

**Or ask Claude Code:**
```
> I have a merge conflict in includes/class-quiz-builder.php
> Help me resolve it. Here's the conflict:
> [paste conflict]
```

### Issue: Tests Failing
```bash
# See which tests are failing
composer test

# Ask Claude Code to fix
> These tests are failing:
> [paste test output]
>
> Fix the failing tests.
```

---

## Keyboard Shortcuts Reference

### Warp
```
Cmd+T         New tab
Cmd+K         Clear screen
Cmd+P         Command palette
Ctrl+D        Exit Claude Code
```

### GitHub Desktop
```
Cmd+N         New branch
Cmd+R         Refresh
Cmd+Shift+O   Open in editor
Cmd+Shift+F   Open in Finder
```

### Browser (DevTools)
```
F12           Open DevTools
Cmd+Opt+I     Open DevTools (Mac)
Cmd+Opt+J     Open Console (Mac)
```

---

## Next Steps

Now that you understand the workflow:

1. **Set up your environment** (if not done):
   - Install Node.js, npm, Claude Code
   - Set up Local, GitHub Desktop, Warp
   - Create symlink from Development to Local Sites

2. **Create your first feature branch**:
   - `feature/plugin-structure`

3. **Start Claude Code**:
```bash
   cd ~/Development/wordpress-plugins/pressprimer-quiz
   claude-code chat
```

4. **Build the foundation**:
   - Start with project structure
   - Then database schema
   - Then basic plugin classes
   - Then first feature (quiz builder)

5. **Commit early, commit often**:
   - Small, logical commits
   - Test before committing
   - Push at end of each session

---

**You're ready to start building! Open Claude Code and reference this workflow whenever you need guidance on the process.**