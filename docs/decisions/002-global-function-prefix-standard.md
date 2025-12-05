# Decision 002: WordPress.org Plugin Check Prefix Compliance

**Status**: Accepted

**Date**: 2025-12-05

**Decision Makers**: Ryan (Project Owner), Claude Code (Implementation Assistant)

**Related Decisions**: None

---

## Context

### Background

During WordPress Plugin Check compliance testing for WordPress.org submission, the plugin check reported multiple errors about global namespace identifiers using the `ppq_` or `PPQ_` prefix:

1. "Functions declared in the global namespace by a theme/plugin should start with the theme/plugin prefix. Found: 'ppq_uninstall'"
2. "Classes declared by a theme/plugin should start with the theme/plugin prefix. Found: 'PPQ_Quiz_Renderer'"
3. "Hook names invoked by a theme/plugin should start with the theme/plugin prefix. Found: 'ppq_before_quiz_meta'"

The original implementation used short prefixes (`ppq_`, `PPQ_`) throughout the codebase, which WordPress Plugin Check flagged as too short/generic.

### Problem Statement

WordPress.org requires plugins to use unique prefixes for all global namespace identifiers to prevent conflicts with other plugins. The short prefixes were deemed potentially conflicting, failing the Plugin Check requirements.

### Goals and Constraints

**Goals:**
- Pass WordPress.org Plugin Check requirements
- Prevent name conflicts with other plugins
- Maintain consistent naming conventions throughout the plugin
- Complete the rename without breaking functionality

**Constraints:**
- Must change all global namespace identifiers (functions, classes, hooks)
- File names can remain unchanged (using `class-ppq-*.php` pattern)
- Internal identifiers (options, meta keys, capabilities) can remain unchanged
- Documentation must be updated to reflect the new standard

**Requirements:**
- All global PHP functions must use `pressprimer_quiz_` prefix
- All PHP classes must use `PressPrimer_Quiz_` prefix
- All hooks (actions/filters) must use `pressprimer_quiz_` prefix
- Autoloader must be updated to map new class names to existing file names
- All references throughout codebase and documentation must be updated

---

## Decision

### What We Decided

**All global namespace PHP identifiers must use the full plugin prefix:**
- Global functions: `pressprimer_quiz_` (e.g., `pressprimer_quiz_init()`)
- PHP classes: `PressPrimer_Quiz_` (e.g., `PressPrimer_Quiz_Question`)
- Hooks (actions/filters): `pressprimer_quiz_` (e.g., `pressprimer_quiz_quiz_passed`)

**Internal identifiers can continue using the shorter `ppq_` prefix:**
- Options, user meta, post meta, transients
- Capabilities, nonces
- CSS classes, JavaScript namespace
- Shortcodes, AJAX actions
- Database table names

### Rationale

1. **WordPress.org Compliance**: Required to pass Plugin Check for directory submission
2. **Conflict Prevention**: Longer prefix ensures uniqueness in the global namespace
3. **Clear Distinction**: Separates global identifiers from internal/stored identifiers
4. **File Names Preserved**: Keeping `class-ppq-*.php` file names avoids massive file renames
5. **Internal IDs Unchanged**: Options, capabilities, etc. don't conflict globally

---

## Alternatives Considered

### Alternative 1: Change Everything Including File Names

**Description:**
Rename all file names from `class-ppq-*.php` to `class-pressprimer-quiz-*.php`.

**Pros:**
- Complete consistency between class names and file names

**Cons:**
- 50+ file renames required
- Git history disruption
- Higher risk of errors
- Not required by Plugin Check

**Why Not Chosen:**
The autoloader can map `PressPrimer_Quiz_*` classes to `class-ppq-*.php` files. File names are internal and don't conflict.

---

### Alternative 2: Use Shorter Prefix Like `ppquiz_`

**Description:**
Use a slightly longer but still abbreviated prefix.

**Pros:**
- Shorter than full plugin name
- Less verbose

**Cons:**
- May still not pass Plugin Check
- Could still conflict with other quiz plugins
- Uncertain compliance

**Why Not Chosen:**
Using the full plugin name prefix guarantees uniqueness and compliance.

---

## Consequences

### Positive Consequences

**Benefits:**
- Passes WordPress.org Plugin Check requirements
- Prevents all potential name conflicts
- Clear, self-documenting identifier names
- Follows WordPress.org best practices

**Enables:**
- Submission to WordPress.org plugin directory
- Long-term maintainability

### Negative Consequences

**Trade-offs:**
- Longer class and function names
- More verbose hook names
- **Mitigation**: IDE autocomplete makes this manageable

**Risks:**
- Developers might use old prefixes for new code
- **Mitigation**: Clear documentation and decision record

**Technical Debt:**
- None - this is the correct long-term standard

---

## Implementation

### What Changes Were Made

**Global Functions (8 total):**
- `pressprimer-quiz.php`: `ppq_init()` → `pressprimer_quiz_init()`
- `uninstall.php`: 7 functions renamed from `ppq_*` to `pressprimer_quiz_*`

**PHP Classes (~50 classes):**
- All `class PPQ_*` declarations renamed to `class PressPrimer_Quiz_*`
- All `extends PPQ_*` references updated
- All `new PPQ_*` instantiations updated
- All `PPQ_*::` static calls updated
- All `class_exists( 'PPQ_*' )` checks updated
- All docblock type hints (`@param`, `@return`, `@var`) updated

**Hooks (~70 hooks):**
- All `do_action( 'ppq_*' )` calls renamed to `do_action( 'pressprimer_quiz_*' )`
- All `apply_filters( 'ppq_*' )` calls renamed to `apply_filters( 'pressprimer_quiz_*' )`
- All `add_action( 'ppq_*' )` listeners updated
- All `add_filter( 'ppq_*' )` listeners updated

**Autoloader:**
- Updated to recognize `PressPrimer_Quiz_*` prefix
- Maps to existing `class-ppq-*.php` file names

**Documentation:**
- `docs/CLAUDE-INSTRUCTIONS.md` - Updated naming conventions
- `docs/architecture/CONVENTIONS.md` - Updated all prefix documentation
- `docs/architecture/CODE-STRUCTURE.md` - Updated example code
- `docs/architecture/HOOKS.md` - All hook examples updated
- All feature documentation files updated

### Files NOT Changed

**File names preserved:**
- All `class-ppq-*.php` files kept their names
- Autoloader handles the mapping

**Internal identifiers preserved:**
- `ppq_` prefix for options, meta keys, capabilities
- `ppq-` prefix for CSS classes
- `PPQ` namespace for JavaScript
- `ppq_` prefix for shortcodes and AJAX actions
- `wp_ppq_` prefix for database tables
- `PPQ_` prefix for constants (e.g., `PPQ_VERSION`)

### Testing Performed

- PHP syntax validation passed for all 49 PHP files in includes/
- Core files syntax verified: autoloader, plugin, activator, deactivator
- No remaining old-prefix class references in PHP files (except constants)
- All hook names updated in both PHP code and documentation

---

## Success Metrics

### How We'll Know This Was the Right Decision

**Quantitative Metrics:**
- WordPress Plugin Check passes with no prefix errors
- Zero conflicts reported with other plugins

**Qualitative Measures:**
- Clean plugin submission to WordPress.org
- No developer confusion about naming standards

**Review Date**: Upon WordPress.org submission

---

## Rollback Plan

### If This Decision Doesn't Work Out

**Symptoms That Would Trigger Rollback:**
- This is a compliance requirement, so rollback is not applicable
- Would only change if WordPress.org changes their requirements

**Rollback Steps:**
- Not applicable - this is required for compliance

---

## Related Documentation

### Referenced In
- `docs/CLAUDE-INSTRUCTIONS.md` - Naming conventions section
- `docs/architecture/CONVENTIONS.md` - Full naming standards
- `docs/architecture/CODE-STRUCTURE.md` - Example code
- `docs/architecture/HOOKS.md` - Hook documentation

### References
- WordPress Plugin Check tool results
- [WordPress Plugin Handbook - Prefix Everything](https://developer.wordpress.org/plugins/plugin-basics/best-practices/#prefix-everything)

### Supersedes
- None (first decision on this topic)

---

## Notes and Discussion

### Key Discussion Points

**Point 1: Scope of Changes**
- Initially thought only global functions needed updating
- Plugin Check also flagged classes and hooks
- Decision expanded to cover all global namespace identifiers

**Point 2: File Name Preservation**
- Decided to keep `class-ppq-*.php` file names
- Autoloader updated to map new class names to old file names
- Reduces risk and preserves git history

### Open Questions
- None

### Future Considerations
- Any new global functions must use `pressprimer_quiz_` prefix
- Any new classes must use `PressPrimer_Quiz_` prefix
- Any new hooks must use `pressprimer_quiz_` prefix
- Consider adding a linting rule to enforce these standards

---

## Changelog

### Updates to This Decision

**2025-12-05**: Initial decision record created
**2025-12-05**: Expanded scope to include classes and hooks based on Plugin Check requirements

---

## Quick Reference

### Naming Standards Summary

| Type | Prefix | Example |
|------|--------|---------|
| **Global PHP functions** | `pressprimer_quiz_` | `pressprimer_quiz_init()` |
| **PHP classes** | `PressPrimer_Quiz_` | `class PressPrimer_Quiz_Question` |
| **Hooks (actions/filters)** | `pressprimer_quiz_` | `do_action( 'pressprimer_quiz_quiz_passed' )` |
| Options/meta keys | `ppq_` | `get_option( 'ppq_settings' )` |
| Capabilities | `ppq_` | `ppq_manage_all` |
| Nonces | `ppq_` | `ppq_submit_quiz` |
| Transients | `ppq_` | `get_transient( 'ppq_cache' )` |
| Shortcodes | `ppq_` | `[ppq_quiz]` |
| CSS classes | `ppq-` | `.ppq-quiz-container` |
| JavaScript | `PPQ` | `PPQ.Quiz.submit()` |
| Database tables | `wp_ppq_` | `wp_ppq_questions` |
| Constants | `PPQ_` | `PPQ_VERSION` |
| File names | `class-ppq-` | `class-ppq-question.php` |

**Rule**: If it's in the global PHP namespace (function, class, or hook), use the full `pressprimer_quiz_` / `PressPrimer_Quiz_` prefix. Internal identifiers use `ppq_`.
