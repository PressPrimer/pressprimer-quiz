# Decision 001: Opt-In Data Removal on Uninstall

**Status**: Accepted

**Date**: 2024-11-26

**Decision Makers**: Ryan (Project Owner), Claude Code (Implementation Assistant)

**Related Decisions**: None

---

## Context

### Background

During implementation of Phase 1, Prompt 1.4 (Capabilities System), the uninstall.php file was created with logic to remove all plugin data (database tables, options, user meta, post meta, capabilities, roles, transients) when the plugin is uninstalled.

The original documentation did not specify the default behavior for data removal, leaving ambiguity about whether data should be preserved or removed by default.

### Problem Statement

WordPress plugins can be accidentally uninstalled, and uninstalling a plugin that removes all data by default poses a significant risk of irreversible data loss. This is especially critical for an educational plugin where quiz data, student attempts, and results represent significant value.

### Goals and Constraints

**Goals:**
- Prevent accidental data loss from plugin uninstallation
- Provide administrators explicit control over data removal
- Maintain compliance with WordPress best practices
- Support testing and staging environments where plugins may be reinstalled

**Constraints:**
- Must work within WordPress uninstall.php mechanism
- Cannot prompt user during uninstall (file runs non-interactively)
- Must be compatible with WordPress.org plugin guidelines

**Requirements:**
- Data must be preserved by default (opt-in for removal, not opt-out)
- Clear documentation for administrators about data retention
- Settings interface to control data removal (Phase 1.10)
- Explicit warning when enabling data removal

---

## Decision

### What We Decided

**By default, the plugin will preserve ALL data when uninstalled.** Data will only be removed if the administrator has explicitly enabled the "Remove all data on uninstall" option in the plugin settings page, which will be disabled by default.

### Rationale

1. **Safety First**: Accidental plugin deletion should not result in catastrophic data loss
2. **User Expectations**: Educational institutions expect data preservation unless explicitly chosen otherwise
3. **Testing Friendly**: Developers and site administrators can safely test plugin activation/deactivation
4. **Industry Standard**: Leading WordPress plugins (WooCommerce, LearnDash) preserve data by default
5. **Compliance**: Some educational institutions have data retention requirements
6. **Reversibility**: Users can always manually remove data later, but cannot recover deleted data

---

## Alternatives Considered

### Alternative 1: Remove Data by Default (Original Implementation)

**Description:**
Plugin removes all data on uninstall unless user has enabled "keep data" setting.

**Pros:**
- ✅ Clean uninstallation leaves no traces
- ✅ Simpler for complete removal
- ✅ Less database clutter for trial users

**Cons:**
- ❌ High risk of accidental data loss
- ❌ Cannot recover deleted data
- ❌ Problematic for staging/production workflows
- ❌ Against industry best practices
- ❌ Poor user experience for educational institutions

**Why Not Chosen:**
The risk of irreversible data loss far outweighs the benefit of clean uninstallation. Educational data is too valuable to risk accidental deletion.

---

### Alternative 2: Prompt User During Uninstall

**Description:**
Show confirmation dialog during plugin deletion asking user to confirm data removal.

**Pros:**
- ✅ Explicit user choice at time of uninstall
- ✅ Clear intent capture

**Cons:**
- ❌ Not possible with WordPress uninstall.php mechanism (runs non-interactively)
- ❌ Would require admin AJAX or other workarounds
- ❌ Adds complexity
- ❌ May not work reliably in all environments

**Why Not Chosen:**
WordPress uninstall.php runs non-interactively and cannot prompt users. This would require custom implementation outside WordPress standards.

---

### Alternative 3: Separate Data Removal Tool

**Description:**
Keep data on uninstall, provide separate admin tool to manually delete data.

**Pros:**
- ✅ Maximum safety
- ✅ Explicit separate action for data removal
- ✅ Can provide detailed warnings

**Cons:**
- ❌ Two-step process for users who want clean removal
- ❌ Extra development effort
- ❌ Data remains even if plugin is deleted

**Why Not Chosen:**
While very safe, this doesn't provide a path for users who genuinely want clean uninstallation. Our chosen solution (opt-in setting) provides both safety and the option for complete removal.

---

## Consequences

### Positive Consequences

**Benefits:**
- ✅ Protects against accidental data loss
- ✅ Supports testing and development workflows
- ✅ Aligns with educational institution expectations
- ✅ Follows WordPress plugin best practices
- ✅ Maintains data for reinstallation scenarios
- ✅ Reduces support burden from "lost data" issues

**Enables:**
- Safe plugin updates and reinstallations
- Confidence in testing plugin features
- Data recovery in case of accidental uninstall
- Multi-environment development (staging/production)

### Negative Consequences

**Trade-offs:**
- ⚠️ Database tables persist after uninstall by default
- ⚠️ Users wanting complete removal must explicitly enable setting
- **Mitigation**: Clear documentation in settings page with warning labels

**Risks:**
- ⚠️ Users may accumulate orphaned data if they forget to enable removal
- **Mitigation**: Settings page will display current data size and provide cleanup tools

**Technical Debt:**
- None - this is the long-term intended behavior

---

## Implementation

### What Changes Are Required

**Code Changes:**
- `uninstall.php` - Changed logic to check for `remove_data_on_uninstall` setting (default false)
- `includes/class-ppq-activator.php` - Added `remove_data_on_uninstall => false` to default settings
- Added comprehensive documentation comments to uninstall.php header

**Database Changes:**
- None - affects behavior only

**Configuration Changes:**
- Added new setting: `remove_data_on_uninstall` (boolean, default false)

**Documentation Updates:**
- Enhanced uninstall.php header comments
- This decision record
- Settings page will include warnings (Phase 1.10)

### Implementation Timeline

**Phase 1 (Completed - 2024-11-26):**
- Updated uninstall.php logic
- Added default setting
- Added documentation

**Phase 1.10 (Settings Page - Upcoming):**
- Add checkbox to settings page
- Add prominent warning message
- Add data size display
- Add manual cleanup button

### Testing Requirements

**Manual Testing:**
- Test uninstall with setting disabled (default) - verify data persists
- Test uninstall with setting enabled - verify data removed
- Test reinstall after uninstall - verify data accessible if preserved
- Test settings page checkbox functionality (Phase 1.10)

---

## Success Metrics

### How We'll Know This Was the Right Decision

**Quantitative Metrics:**
- Zero support tickets about "lost data after uninstall"
- Less than 5% of users enable data removal setting
- No negative WordPress.org reviews mentioning unexpected data loss

**Qualitative Measures:**
- Positive feedback about data safety
- Educational institutions report confidence in plugin
- Developers report easy testing workflows

**Review Date**: 2025-03-01 (After 3 months of production use)

---

## Rollback Plan

### If This Decision Doesn't Work Out

**Symptoms That Would Trigger Rollback:**
- WordPress.org reviewers reject due to data persistence
- Overwhelming user feedback requesting default removal
- Database bloat becomes major support issue

**Rollback Steps:**
1. Change default setting from `false` to `true`
2. Update documentation to reflect new default
3. Add migration to notify existing users of behavior change
4. Update this decision record status to "Superseded"

**Fallback Solution:**
Implement Alternative 3 (Separate Data Removal Tool) if pure uninstall-based approach proves insufficient.

---

## Related Documentation

### Referenced In
- `uninstall.php` - Implementation file
- `includes/class-ppq-activator.php` - Default settings
- `docs/versions/v1.0/PHASES.md` - Will reference in Phase 1.10 (Settings)

### References
- [WordPress Plugin Handbook - Uninstall Methods](https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/)
- LearnDash data retention approach (industry reference)
- WooCommerce uninstall behavior (industry reference)

### Supersedes
- None (first decision on this topic)

---

## Notes and Discussion

### Key Discussion Points

**Point 1: Risk of Accidental Data Loss**
- Raised by: Ryan (Project Owner)
- Context: Original implementation removed data by default
- Resolution: Changed to preserve data by default with opt-in removal

**Point 2: WordPress.org Compliance**
- Raised by: Development process
- Context: Need to ensure approach meets WordPress.org guidelines
- Resolution: Researched plugin handbook - data preservation is acceptable and common

### Open Questions
- None at this time

### Future Considerations
- Consider adding data export feature before removal (Phase 2.0+)
- Consider email notification if user enables data removal setting
- Consider showing data size/statistics in settings page

---

## Changelog

### Updates to This Decision

**2024-11-26**: Initial decision record created after implementation

---

**Priority**: This decision takes precedence over any conflicting documentation. All future documentation should reference this decision for data removal behavior.
