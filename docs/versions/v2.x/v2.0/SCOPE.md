# Version 2.0 - Scope Document

**Status:** Planning

**Target Release:** 3-4 weeks after WordPress.org v1.0 approval

**Last Updated:** 2025-12-26

---

## Release Goal

Version 2.0 establishes the commercial foundation for PressPrimer Quiz. The free plugin gains UX improvements and addon infrastructure, while three premium tiers launch simultaneously with their core feature sets.

### Objectives

1. **Free Plugin:** Add LearnPress integration, condensed display mode, login requirements, and extensibility hooks for premium addons
2. **Educator Tier:** Deliver groups, assignments, and import/export—the core value proposition for professional educators
3. **School Tier:** Add availability controls and shared question banks for multi-teacher environments
4. **Enterprise Tier:** Provide audit logging and white-label branding for compliance-driven organizations

### Current State (v1.0)

The v1.0 plugin includes database tables for groups, group members, and assignments that were created as part of the schema but have **no admin UI, model classes, or functionality**. These tables provide the foundation for Educator 2.0 features without requiring new migrations.

**Existing Tables (schema only, no UI):**
- `wp_ppq_groups` — Group definitions with uuid, name, description, owner_id, member_count
- `wp_ppq_group_members` — User-group relationships with role (teacher/student)
- `wp_ppq_assignments` — Quiz-group assignments with assignee_type, due_at

**Existing Infrastructure:**
- `wp_ppq_banks.visibility` column ('private', 'shared') — Foundation for shared banks
- `wp_ppq_events` table — Quiz attempt event tracking (stays separate from audit log)

---

## Free Plugin 2.0

### Feature 1: Addon Compatibility Hooks

**Priority:** High

**Problem Solved:** Premium addons need clean extension points to add functionality without modifying core files.

**Addon Registration:**

```php
// Addons register themselves
do_action( 'pressprimer_quiz_register_addon', 'educator', '2.0.0', array(
    'groups',
    'assignments',
    'import_export',
) );

// Free plugin checks addon status
pressprimer_quiz_has_addon( 'educator' );     // bool
pressprimer_quiz_has_addon( 'school' );       // bool
pressprimer_quiz_has_addon( 'enterprise' );   // bool

// Feature-level checks
pressprimer_quiz_feature_enabled( 'groups' ); // bool
```

**Extension Points:**

| Hook | Location | Purpose |
|------|----------|---------|
| `pressprimer_quiz_loaded` | Plugin init | Addons hook in here |
| `pressprimer_quiz_addons_loaded` | After addon init | All addons ready |
| `pressprimer_quiz_builder_settings_after` | Quiz Builder | Add settings sections |
| `pressprimer_quiz_builder_question_tools` | Quiz Builder | Add question actions |
| `pressprimer_quiz_question_editor_after_answers` | Question Editor | Add fields |
| `pressprimer_quiz_results_after_score` | Results page | Add result sections |
| `pressprimer_quiz_settings_tabs` | Settings page | Add tabs |
| `pressprimer_quiz_admin_menu` | Admin menu | Add submenus |

**Files:**
- `includes/class-ppq-addon-manager.php` (new)

---

### Feature 2: LearnPress Integration

**Priority:** High

**Problem Solved:** LearnPress is a popular free LMS; users want PPQ quizzes in LearnPress lessons.

**Admin Interface:**
- Meta box on LearnPress Lesson edit screen
- Quiz selector dropdown (searchable)
- "Require quiz pass to complete lesson" checkbox
- Optional minimum score override

**Frontend Behavior:**
- Quiz renders at end of lesson content
- Respects LearnPress course enrollment
- Passing quiz triggers lesson completion (if enabled)

**Technical:**
- Uses `learn-press/lesson/content` filter
- Minimum LearnPress version: 4.0.0
- Post meta: `_ppq_quiz_id`, `_ppq_require_pass`, `_ppq_min_score`

**Files:**
- `includes/integrations/class-ppq-learnpress.php` (new)

---

### Feature 3: Require Login Setting

**Priority:** High

**Problem Solved:** Some quizzes should only be available to authenticated users.

**Global Setting:**
- Location: PPQ Settings → General → "Guest Access"
- Options: "Allow guests (optional email)", "Allow guests (required email)", "Require login"

**Per-Quiz Override:**
- Location: Quiz Builder → Settings → "Access Mode"
- Options: "Use global default", plus all global options
- Custom login message field

**Frontend Behavior:**
- Quiz landing page shows login message instead of Start button
- "Log In" button redirects to WordPress login with return URL
- Integrates with WooCommerce/membership plugin login pages

**Files:**
- Modified: `includes/admin/class-ppq-admin-settings.php`
- Modified: `includes/admin/class-ppq-admin-quiz-builder.php`
- Modified: `includes/frontend/class-ppq-quiz-renderer.php`

---

### Feature 4: Condensed Mode

**Priority:** High

**Problem Solved:** Current quiz display requires too much scrolling, especially on mobile and when embedded in lessons.

**Global Setting:**
- Location: PPQ Settings → Appearance (new section) → "Display Density"
- Options: "Standard" (default), "Condensed"

**Per-Quiz Override:**
- Location: Quiz Builder → Settings → "Display Density"
- Options: "Use global default", "Standard", "Condensed"

**CSS Changes:**

| Element | Standard | Condensed |
|---------|----------|-----------|
| Featured image max-height | 300px | 150px |
| Title font-size | 1.5rem | 1.25rem |
| Container padding | 24px | 12px |
| Question stem font-size | 1.125rem | 1rem |
| Answer option padding | 12px | 8px |
| Answer option line-height | 1.6 | 1.4 |
| Layout | Separate boxes | Single unified box |
| Navigation | Below answers | Sticky footer (mobile) |

**Theme Compatibility:**
- Applies as BEM modifier: `.ppq-quiz--condensed`
- Works with all three themes (Default, Modern, Minimal)
- Maintains 44×44px minimum touch targets
- Preserves accessibility requirements

**Files:**
- `assets/css/condensed.css` (new)
- Modified: `assets/css/themes/*.css`
- Modified: `includes/frontend/class-ppq-quiz-renderer.php`
- Modified: `includes/admin/class-ppq-admin-settings.php` (add Appearance section)

---

### Feature 5: Premium Upsell Touchpoints

**Priority:** Low (implement last)

**Dependency:** Complete after Educator 2.0, School 2.0, and Enterprise 2.0 features are built. This ensures upsell UI exactly matches the real premium feature interfaces.

**Problem Solved:** Users need to discover premium features exist without disrupting their experience.

**Implementation:**

Display disabled/locked UI elements throughout the admin with tooltips explaining premium features and linking to pressprimer.com. The locked UI should mirror the actual premium UI as closely as possible.

**Touchpoint Locations:**

| Location | Locked Feature | Tier |
|----------|----------------|------|
| Quiz Builder → Settings | Availability Windows | School |
| Quiz Builder → Settings | Branching Logic | Enterprise |
| Quiz Builder → Questions | AI Distractor Generation | Educator |
| Question Editor | Survey/Ungraded Mode | Educator |
| Reports Page | CSV Export | Educator |
| Reports Page | Visual Charts | Educator |
| Reports Page | Group Reports | School |
| Question Bank List | Share with Others | School |
| Settings Page | White-Label Options | Enterprise |

**UI Requirements:**
- Subtle padlock icon (🔒) on locked features
- Tooltip on hover/focus with feature description
- "Available in [Tier Name]" with "Learn More" link
- Links to pressprimer.com/pricing
- No popups, modals, or blocking behavior
- Keyboard accessible

**Files:**
- `includes/admin/class-ppq-upsells.php` (new)
- `assets/css/admin-upsells.css` (new)
- `assets/js/admin-upsells.js` (new)

---

## Educator Addon 2.0

**Price:** $149/year (1 site)

### Feature 1: Group Support

**Priority:** Critical

**Problem Solved:** Educators need to organize students into classes, cohorts, or teams.

**Current State:** Database tables (`wp_ppq_groups`, `wp_ppq_group_members`) were created in v1.0 schema but have no admin UI, model classes, or functionality. v2.0 builds the complete feature on this foundation.

**Group Management:**
- New admin menu: PPQ → Groups
- Create, edit, delete groups
- Group name, description, and optional image
- Bulk add/remove users from groups
- User can belong to multiple groups

**User Assignment:**
- Searchable user selector
- Bulk import from CSV (email list)
- Integration with WordPress roles
- Manual add/remove interface

**Existing Database Schema:**

```sql
-- wp_ppq_groups (EXISTS - created in v1.0)
CREATE TABLE wp_ppq_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    owner_id BIGINT UNSIGNED NOT NULL,
    member_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uuid (uuid),
    KEY owner_id (owner_id),
    KEY name (name)
);

-- wp_ppq_group_members (EXISTS - created in v1.0)
CREATE TABLE wp_ppq_group_members (
    group_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role ENUM('teacher', 'student') NOT NULL DEFAULT 'student',
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    added_by BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (group_id, user_id),
    KEY user_id (user_id),
    KEY role (role)
);
```

**v2.0 Implementation Required:**
- Model class: `includes/models/class-ppqe-group.php`
- Admin UI: `includes/admin/class-ppqe-admin-groups.php`
- List table: `includes/admin/class-ppqe-groups-list-table.php`
- JavaScript: Group management React components

**Permissions Model:**

| Role | Can See | Can Manage |
|------|---------|------------|
| **Administrator** | All groups, all users, all quiz data | All groups, all assignments |
| **Teacher** | Only groups they belong to (as teacher), only users in those groups, only quiz data for users in their groups | Only their own groups and assignments |

**Capabilities:**
- `ppq_manage_all_groups` — Administrator-only: full access to all groups
- `ppq_manage_own_groups` — Teachers: manage groups where they are the owner or have teacher role
- `ppq_view_group_members` — See members of accessible groups
- `ppq_view_group_results` — See quiz results for users in accessible groups

**Data Isolation for Teachers:**
- Group list shows only groups where user has teacher role
- User search limited to members of teacher's groups
- Reports filtered to show only students in teacher's groups
- Quiz attempt data restricted to teacher's group members

---

### Feature 2: Assigned Quizzes

**Priority:** Critical

**Problem Solved:** Educators need to assign specific quizzes to groups with due dates and track completion.

**Current State:** Database table (`wp_ppq_assignments`) was created in v1.0 schema but has no admin UI, model class, or functionality. v2.0 builds the complete feature on this foundation.

**Assignment Interface:**
- New tab in Quiz Builder: "Assignments"
- Select one or more groups
- Set due date (optional)
- Set available from date (optional)
- Enable/disable late submissions
- Send notification on assignment (optional)

**Student Experience:**
- New block/shortcode: `[ppq_assigned_quizzes]`
- Shows quizzes assigned to user's groups
- Displays: quiz title, due date, status (not started/in progress/completed)
- Filter by status, sort by due date

**Tracking:**
- Assignment completion percentage per group
- Individual student status
- Overdue tracking
- Email reminders (optional)

**Existing Database Schema:**

```sql
-- wp_ppq_assignments (EXISTS - created in v1.0)
CREATE TABLE wp_ppq_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quiz_id BIGINT UNSIGNED NOT NULL,
    assignee_type ENUM('group', 'user') NOT NULL,
    assignee_id BIGINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NOT NULL,
    due_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY quiz_assignee (quiz_id, assignee_type, assignee_id),
    KEY assignee (assignee_type, assignee_id),
    KEY due_at (due_at),
    KEY assigned_by (assigned_by)
);
```

**Schema Enhancement Needed:**

```sql
-- Add columns to wp_ppq_assignments
ALTER TABLE wp_ppq_assignments 
    ADD COLUMN available_from DATETIME DEFAULT NULL AFTER due_at,
    ADD COLUMN allow_late TINYINT(1) NOT NULL DEFAULT 1 AFTER available_from;
```

**v2.0 Implementation Required:**
- Model class: `includes/models/class-ppqe-assignment.php`
- Admin UI: Assignments tab in Quiz Builder
- Block: `[ppq_assigned_quizzes]`
- JavaScript: Assignment management components

---

### Feature 3: Question/Bank Import/Export

**Priority:** High

**Problem Solved:** Educators need to migrate questions from other systems or share with colleagues.

**Export Formats:**
- CSV (simple, spreadsheet-compatible)
- XML (preserves all metadata)
- JSON (developer-friendly)

**Export Options:**
- Export single question
- Export selected questions (bulk)
- Export entire bank
- Include/exclude: categories, tags, difficulty, feedback

**Import Interface:**
- Upload file (drag-drop or file picker)
- Format auto-detection
- Preview before import
- Field mapping for CSV
- Duplicate handling: skip, overwrite, create new
- Import to existing bank or create new

**CSV Format:**

```csv
question_type,stem,option_a,option_b,option_c,option_d,correct,difficulty,category,explanation
multiple_choice,"What is 2+2?","3","4","5","6","B","easy","Math","The answer is 4."
true_false,"The sky is blue.","True","False","","","A","easy","Science","Correct!"
```

**XML Format:**

```xml
<questions>
  <question type="multiple_choice" difficulty="easy">
    <stem>What is 2+2?</stem>
    <options>
      <option correct="false">3</option>
      <option correct="true">4</option>
      <option correct="false">5</option>
      <option correct="false">6</option>
    </options>
    <category>Math</category>
    <explanation>The answer is 4.</explanation>
  </question>
</questions>
```

---

## School Addon 2.0

**Price:** $299/year (3 sites)

*Requires Educator addon*

### Feature 1: Quiz Availability Windows

**Priority:** High

**Problem Solved:** Instructors need to control exactly when quizzes are accessible.

**Settings (per quiz):**
- Available from: date and time
- Available until: date and time
- Timezone handling (use WordPress timezone)
- Message shown outside window (customizable)

**Behavior:**
- Before window: show "Quiz opens on [date]" message
- After window: show "Quiz closed on [date]" message
- During window: normal quiz access
- Respects user timezone display preferences

**Admin Interface:**
- Date/time pickers in Quiz Builder → Settings
- Calendar view of all quiz windows (future enhancement)
- Bulk edit availability (future enhancement)

---

### Feature 2: Shared Question Banks

**Priority:** High

**Problem Solved:** Multiple teachers need to collaborate on question banks.

**Current State:** The `wp_ppq_banks` table already has a `visibility` column with values 'private' and 'shared'. v2.0 adds granular permission management via a new `wp_ppq_bank_shares` table.

**Sharing Model:**
- Bank owner can share with specific users
- Permission levels: View, Use, Edit, Admin
- Shared banks appear in user's bank list with indicator

**Permissions:**

| Level | View Questions | Use in Quiz | Edit Questions | Manage Sharing |
|-------|----------------|-------------|----------------|----------------|
| View | ✓ | ✗ | ✗ | ✗ |
| Use | ✓ | ✓ | ✗ | ✗ |
| Edit | ✓ | ✓ | ✓ | ✗ |
| Admin | ✓ | ✓ | ✓ | ✓ |

**Existing Schema:**

```sql
-- wp_ppq_banks already has visibility column
-- visibility ENUM('private', 'shared') NOT NULL DEFAULT 'private'
```

**New Database Table:**

```sql
-- wp_ppq_bank_shares (NEW)
CREATE TABLE wp_ppq_bank_shares (
    bank_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    permission ENUM('view', 'use', 'edit', 'admin') NOT NULL DEFAULT 'use',
    shared_by BIGINT UNSIGNED NOT NULL,
    shared_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (bank_id, user_id),
    KEY user_id (user_id)
);
```

**UI:**
- "Share" button on bank row actions
- Modal with user search and permission selector
- "Shared with me" filter in bank list
- Visual indicator for shared banks

---

## Enterprise Addon 2.0

**Price:** $499/year (5 sites)

*Requires School addon*

### Feature 1: Audit History Logging

**Priority:** High

**Problem Solved:** Organizations need to track changes to questions, quizzes, and question banks for accountability and recovery. When a teacher modifies or deletes content, other teachers need visibility into what changed and the ability to restore previous versions.

**Current State:** The `wp_ppq_events` table exists for quiz-taking events (question views, answer submissions). Quiz attempt data is already tracked there. The new `wp_ppq_audit_log` table is specifically for **content change tracking**—questions, quizzes, and banks.

**Primary Focus:**
- Track all changes to questions (content edits, setting changes, deletions)
- Track all changes to quizzes (configuration changes, question additions/removals, deletions)
- Track all changes to question banks (edits, deletions, sharing changes)
- Enable restoration of deleted or modified content

**Events Logged:**

| Entity | Events | Restorable |
|--------|--------|------------|
| Question | Created, updated, deleted | Yes - restore deleted questions, revert to previous version |
| Quiz | Created, updated, published, archived, deleted | Yes - restore deleted quizzes, revert settings |
| Quiz Items | Question added to quiz, question removed from quiz | Yes - restore question to quiz |
| Bank | Created, updated, deleted, shared, unshared | Yes - restore deleted banks |
| Bank Items | Question added to bank, question removed from bank | Yes - restore question to bank |

**Log Entry Fields:**
- Timestamp
- User ID and name
- Action type (created, updated, deleted, added, removed)
- Entity type (question, quiz, bank, quiz_item, bank_item)
- Entity ID
- Entity title (for display)
- Previous value (full JSON snapshot for updates/deletes)
- New value (full JSON snapshot for creates/updates)
- Related entity (e.g., quiz_id when logging question removal)

**Restoration Capability:**
- "Restore" button on deleted items in audit log
- "Revert to this version" on update entries
- Restoration creates new audit entry ("restored from audit log")
- Soft-delete approach: deleted content retained for restoration period

**New Database Table:**

```sql
-- wp_ppq_audit_log (NEW - content change tracking)
CREATE TABLE wp_ppq_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    user_id BIGINT UNSIGNED,
    user_name VARCHAR(200),
    action ENUM('created', 'updated', 'deleted', 'added', 'removed', 'restored') NOT NULL,
    entity_type ENUM('question', 'quiz', 'bank', 'quiz_item', 'bank_item') NOT NULL,
    entity_id BIGINT UNSIGNED,
    entity_title VARCHAR(200),
    related_entity_type VARCHAR(50),
    related_entity_id BIGINT UNSIGNED,
    old_value LONGTEXT,
    new_value LONGTEXT,
    PRIMARY KEY (id),
    KEY timestamp (timestamp),
    KEY user_id (user_id),
    KEY entity_type_id (entity_type, entity_id),
    KEY action (action),
    KEY related_entity (related_entity_type, related_entity_id)
);
```

**Admin Interface:**
- New submenu: PPQ → Audit Log
- Filterable table: date range, user, action type, entity type
- Search by entity title
- Per-entity history view (see all changes to a specific question/quiz/bank)
- "Restore" and "Revert" action buttons where applicable
- Export to CSV
- Retention settings (auto-delete after X days, default 90)

---

### Feature 2: White-Label Branding

**Priority:** Medium

**Problem Solved:** Organizations want quizzes to match their brand, not show "PressPrimer."

**Customization Options:**

| Element | Customizable |
|---------|--------------|
| Plugin name in admin | Yes |
| Menu label | Yes |
| Primary color | Yes |
| Secondary color | Yes |
| Logo in admin | Yes |
| Footer text in emails | Yes |
| "Powered by" text | Yes (can be hidden) |
| Banner images on admin pages | Yes (can be hidden or replaced) |

**Banner Image Control:**
- Settings, Reports, and Dashboard pages have decorative banner images
- Option to hide banners completely (cleaner look)
- Option to replace with custom image (organization branding)
- Per-page control or global toggle

**Implementation:**
- New settings tab: "Branding"
- Color pickers for primary/secondary
- Logo upload (appears in admin header)
- Text fields for custom labels
- Banner visibility toggles
- Custom banner upload option
- Preview panel

**Scope Limits:**
- Does not change plugin slug or text domain
- Does not affect frontend quiz themes (use theme customization for that)
- Admin-only branding in v2.0

---

## Database Changes Summary

### Existing Tables to Utilize (Educator)

These tables were created in v1.0 schema but have no functionality yet. v2.0 builds the complete feature set.

| Table | Status | v2.0 Work |
|-------|--------|-----------|
| `wp_ppq_groups` | Schema exists | Build model class, admin UI, list table |
| `wp_ppq_group_members` | Schema exists | Build model class, membership management UI |
| `wp_ppq_assignments` | Schema exists | Build model class, admin UI, frontend block |

### Schema Modifications (Educator)

| Table | Changes |
|-------|---------|
| `wp_ppq_assignments` | Add `available_from` DATETIME, `allow_late` TINYINT(1) columns |

### New Tables (School)

| Table | Purpose |
|-------|---------|
| `wp_ppq_bank_shares` | Permission-based bank sharing (builds on existing `visibility` column in `wp_ppq_banks`) |

### New Tables (Enterprise)

| Table | Purpose |
|-------|---------|
| `wp_ppq_audit_log` | Content change tracking for questions, quizzes, and banks with restoration support |

### Schema Modifications (School)

| Table | Changes |
|-------|---------|
| `wp_ppq_quizzes` | Add `available_from`, `available_until` DATETIME columns |

### New Options

| Option | Plugin | Purpose |
|--------|--------|---------|
| `ppq_default_access_mode` | Free | Global guest access setting |
| `ppq_login_message_default` | Free | Default login message |
| `ppq_display_density` | Free | Global display density (in Appearance section) |
| `ppq_audit_retention_days` | Enterprise | Log retention period (default 90 days) |
| `ppq_whitelabel_name` | Enterprise | Custom plugin name |
| `ppq_whitelabel_menu_label` | Enterprise | Custom menu label |
| `ppq_whitelabel_primary_color` | Enterprise | Primary brand color |
| `ppq_whitelabel_secondary_color` | Enterprise | Secondary brand color |
| `ppq_whitelabel_logo_id` | Enterprise | Custom logo attachment ID |
| `ppq_whitelabel_hide_banners` | Enterprise | Hide decorative banner images |
| `ppq_whitelabel_custom_banner_id` | Enterprise | Custom banner image attachment ID |
| `ppq_whitelabel_powered_by` | Enterprise | Custom "powered by" text (empty to hide) |

---

## File Changes Summary

### Free Plugin

**New Files:**
```
includes/admin/class-ppq-upsells.php
includes/class-ppq-addon-manager.php
includes/integrations/class-ppq-learnpress.php
assets/css/admin-upsells.css
assets/css/condensed.css
assets/js/admin-upsells.js
```

**Modified Files:**
```
pressprimer-quiz.php
includes/class-ppq-plugin.php
includes/admin/class-ppq-admin-settings.php  # Adds new "Appearance" section
includes/admin/class-ppq-admin-quiz-builder.php
includes/frontend/class-ppq-quiz-renderer.php
assets/css/frontend.css
assets/css/themes/*.css
```

### Educator Addon

**New Files:**
```
pressprimer-quiz-educator.php
includes/class-ppqe-plugin.php
includes/admin/class-ppqe-admin-groups.php
includes/admin/class-ppqe-groups-list-table.php
includes/admin/class-ppqe-admin-assignments.php
includes/admin/class-ppqe-import-export.php
includes/models/class-ppqe-group.php
includes/models/class-ppqe-assignment.php
assets/css/admin.css
assets/js/admin.js
```

### School Addon

**New Files:**
```
pressprimer-quiz-school.php
includes/class-ppqs-plugin.php
includes/admin/class-ppqs-availability.php
includes/admin/class-ppqs-shared-banks.php
includes/models/class-ppqs-bank-share.php
```

### Enterprise Addon

**New Files:**
```
pressprimer-quiz-enterprise.php
includes/class-ppqent-plugin.php
includes/admin/class-ppqent-audit-log.php
includes/admin/class-ppqent-whitelabel.php
includes/models/class-ppqent-audit-entry.php
```

---

## Testing Checklist

### Free Plugin

- [ ] Upsell indicators appear on correct features
- [ ] Tooltips show tier name and link
- [ ] Addon hooks fire at correct times
- [ ] `pressprimer_quiz_has_addon()` returns correct values
- [ ] LearnPress meta box appears on lessons
- [ ] Quiz renders in LearnPress lesson content
- [ ] Lesson completion triggers on quiz pass
- [ ] Require login setting works globally
- [ ] Per-quiz login override works
- [ ] Login redirect returns to quiz
- [ ] Condensed mode reduces spacing correctly
- [ ] All three themes work with condensed mode
- [ ] Mobile sticky navigation works
- [ ] Touch targets meet 44px minimum

### Educator Addon

- [ ] Group CRUD operations work
- [ ] User assignment works (add/remove)
- [ ] Bulk user import from CSV works
- [ ] Assignment creation works
- [ ] Due dates display correctly
- [ ] `[ppq_assigned_quizzes]` block works
- [ ] Assignment status tracking works
- [ ] CSV export includes all fields
- [ ] XML export preserves metadata
- [ ] Import preview shows correct data
- [ ] Duplicate handling works correctly
- [ ] Import creates questions/banks correctly

### School Addon

- [ ] Availability window settings save
- [ ] Quiz blocked before window opens
- [ ] Quiz blocked after window closes
- [ ] Custom messages display
- [ ] Bank sharing modal works
- [ ] Permission levels enforced correctly
- [ ] Shared banks appear in list
- [ ] Share indicators display

### Enterprise Addon

- [ ] Audit log captures all events
- [ ] Log entries include correct data
- [ ] Filter and search work
- [ ] CSV export works
- [ ] Retention auto-delete works
- [ ] White-label settings save
- [ ] Custom colors apply
- [ ] Custom logo displays
- [ ] Custom labels appear

---

## Success Metrics

### Launch (14 days)

| Metric | Target |
|--------|--------|
| Free plugin update adoption | 80%+ |
| Critical bugs | 0 |
| Support tickets | <10 |
| Premium purchases | 10+ |

### 30 Days

| Metric | Target |
|--------|--------|
| Educator licenses | 20+ |
| School licenses | 5+ |
| Enterprise licenses | 2+ |
| Refund rate | <5% |

### 90 Days

| Metric | Target |
|--------|--------|
| Total premium licenses | 50+ |
| MRR | $500+ |
| WordPress.org rating | 4.5+ stars |
| Feature requests captured | 20+ |

