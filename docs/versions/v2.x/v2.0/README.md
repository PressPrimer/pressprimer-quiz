# PressPrimer Quiz v2.0 Feature Documents

This directory contains detailed feature specifications for v2.0 development.

## Document Structure

Each feature document includes:
- Overview and user stories
- Technical specification with code examples
- Database changes (if applicable)
- Testing checklist
- Dependencies
- Files changed

---

## Free Plugin Features

| # | Feature | Priority | File |
|---|---------|----------|------|
| 1 | [Addon Compatibility Hooks](free/001-addon-compatibility-hooks.md) | High | Extension points for premium addons |
| 2 | [LearnPress Integration](free/002-learnpress-integration.md) | High | Embed quizzes in LearnPress lessons |
| 3 | [Require Login Setting](free/003-require-login-setting.md) | High | Control guest access to quizzes |
| 4 | [Condensed Mode](free/004-condensed-mode.md) | High | Compact display for embedded contexts |
| 5 | [Premium Upsell Touchpoints](free/005-premium-upsell-touchpoints.md) | Low | Locked UI hints for premium features |

**Note:** Feature 5 (Upsell Touchpoints) should be implemented LAST, after all premium features are complete.

---

## Educator Addon Features ($149/year)

| # | Feature | Priority | File |
|---|---------|----------|------|
| 1 | [Group Support](educator/001-group-support.md) | Critical | Organize students into classes/cohorts |
| 2 | [Assigned Quizzes](educator/002-assigned-quizzes.md) | Critical | Assign quizzes to groups with due dates |
| 3 | [Import/Export](educator/003-import-export.md) | High | CSV, XML, JSON import/export |

### Implementation Order
1. Group Support (foundation for assignments)
2. Assigned Quizzes (depends on groups)
3. Import/Export (standalone)

---

## School Addon Features ($299/year)

| # | Feature | Priority | File |
|---|---------|----------|------|
| 1 | [Availability Windows](school/001-availability-windows.md) | High | Schedule when quizzes are accessible |
| 2 | [Shared Question Banks](school/002-shared-question-banks.md) | High | Share banks between teachers |

### Implementation Order
Either feature can be implemented first (no dependencies on each other).

---

## Enterprise Addon Features ($499/year)

| # | Feature | Priority | File |
|---|---------|----------|------|
| 1 | [Audit History Logging](enterprise/001-audit-history-logging.md) | High | Track and restore content changes |
| 2 | [White-Label Branding](enterprise/002-white-label-branding.md) | Medium | Customize plugin appearance |

### Implementation Order
Either feature can be implemented first (no dependencies on each other).

---

## Recommended Implementation Sequence

### Phase 1: Foundation
1. **Free: Addon Compatibility Hooks** — Required for all addons
2. **Educator: Group Support** — Foundation for assignments

### Phase 2: Core Premium Features
3. **Educator: Assigned Quizzes** — Depends on groups
4. **Educator: Import/Export** — High user value
5. **School: Shared Question Banks** — High user value
6. **School: Availability Windows** — High user value

### Phase 3: Enterprise & Polish
7. **Enterprise: Audit History Logging** — Compliance feature
8. **Enterprise: White-Label Branding** — Customization feature
9. **Free: LearnPress Integration** — Expands market
10. **Free: Require Login Setting** — Common request
11. **Free: Condensed Mode** — UX improvement

### Phase 4: Upsells (Last)
12. **Free: Premium Upsell Touchpoints** — After all premium features complete

---

## Database Changes Summary

### Existing Tables (Educator)
- `wp_ppq_groups` — Schema exists, needs UI
- `wp_ppq_group_members` — Schema exists, needs UI
- `wp_ppq_assignments` — Schema exists, needs columns + UI

### New Columns
- `wp_ppq_assignments`: `available_from`, `allow_late`, `notify_on_assign`
- `wp_ppq_quizzes`: `access_mode`, `login_message`, `display_density`, `available_from`, `available_until`

### New Tables
- `wp_ppq_bank_shares` (School) — Permission-based sharing
- `wp_ppq_audit_log` (Enterprise) — Content change tracking

---

## Key Permissions

### Group Access
| Role | See Groups | Manage Groups | See Users | See Results |
|------|------------|---------------|-----------|-------------|
| Administrator | All | All | All | All |
| Teacher | Own groups only | Own groups only | Own group members | Own group members |

### Shared Banks
| Role | Private Banks | View-Shared Banks | Edit-Shared Banks |
|------|---------------|-------------------|-------------------|
| Owner | Full access | Full access | Full access |
| Admin | Full access | Full access | Full access |
| Shared (view) | No access | Can use questions | Cannot edit |
| Shared (edit) | No access | Can use questions | Can add/edit questions |

---

## Testing Requirements

Each feature includes a testing checklist. All features require:
- Unit tests for model classes
- Integration tests for hooks
- Manual QA for admin UI
- Manual QA for frontend rendering
- Accessibility verification
- Cross-browser testing
