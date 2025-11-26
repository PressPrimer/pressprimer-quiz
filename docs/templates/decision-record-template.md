# Decision Record Template

**Use this template when making significant architectural or design decisions.**

Copy this template to create new decision records in `docs/decisions/` or `docs/v1.0/decisions/`.

---

## Decision [NUMBER]: [SHORT TITLE]

**Status**: [Proposed | Accepted | Rejected | Deprecated | Superseded]

**Date**: [YYYY-MM-DD]

**Decision Makers**: [Names/Roles]

**Related Decisions**: [Links to related decision records, if any]

---

## Context

### Background

[What is the issue we're trying to solve? What is the current situation?]

[Provide enough context so someone reading this in the future understands why this decision was necessary.]

### Problem Statement

[What specific problem are we solving? Be clear and concise.]

### Goals and Constraints

**Goals:**
- [Goal 1]
- [Goal 2]
- [Goal 3]

**Constraints:**
- [Constraint 1 - e.g., budget, timeline, technical]
- [Constraint 2]
- [Constraint 3]

**Requirements:**
- [Must-have requirement 1]
- [Must-have requirement 2]
- [Nice-to-have requirement 1]

---

## Decision

### What We Decided

[State the decision clearly in 1-2 sentences]

### Rationale

[Explain WHY this decision was made. This is the most important section.]

[What factors influenced this decision?]

[What makes this the best choice among the alternatives?]

---

## Alternatives Considered

### Alternative 1: [Name]

**Description:**
[Brief description of this alternative]

**Pros:**
- ✅ [Advantage 1]
- ✅ [Advantage 2]
- ✅ [Advantage 3]

**Cons:**
- ❌ [Disadvantage 1]
- ❌ [Disadvantage 2]
- ❌ [Disadvantage 3]

**Why Not Chosen:**
[Specific reason this was rejected]

---

### Alternative 2: [Name]

**Description:**
[Brief description of this alternative]

**Pros:**
- ✅ [Advantage 1]
- ✅ [Advantage 2]

**Cons:**
- ❌ [Disadvantage 1]
- ❌ [Disadvantage 2]

**Why Not Chosen:**
[Specific reason this was rejected]

---

### Alternative 3: [Name]

[Add more alternatives as needed]

---

## Consequences

### Positive Consequences

**Benefits:**
- ✅ [Benefit 1 - what we gain]
- ✅ [Benefit 2]
- ✅ [Benefit 3]

**Enables:**
- [What this decision makes possible]
- [Future opportunities this opens]

### Negative Consequences

**Trade-offs:**
- ⚠️ [Trade-off 1 - what we give up]
- ⚠️ [Trade-off 2]

**Risks:**
- ⚠️ [Risk 1 - potential problems]
- ⚠️ [Risk 2]
- **Mitigation**: [How we'll address this risk]

**Technical Debt:**
- [Any technical debt incurred]
- **Plan to Address**: [When/how we'll resolve this]

---

## Implementation

### What Changes Are Required

**Code Changes:**
- [File/component to change 1]
- [File/component to change 2]
- [New files to create]

**Database Changes:**
- [Schema changes if any]
- [Migration required? Yes/No]

**Configuration Changes:**
- [Settings to add/change]
- [Environment variables]

**Documentation Updates:**
- [Docs to update 1]
- [Docs to update 2]

### Implementation Timeline

**Phase 1**: [Timeframe]
- [Task 1]
- [Task 2]

**Phase 2**: [Timeframe]
- [Task 3]
- [Task 4]

### Testing Requirements

**Unit Tests:**
- [Tests to add 1]
- [Tests to add 2]

**Integration Tests:**
- [Integration tests needed]

**Manual Testing:**
- [Scenarios to test manually]

---

## Success Metrics

### How We'll Know This Was the Right Decision

**Quantitative Metrics:**
- [Metric 1: e.g., Performance improved by X%]
- [Metric 2: e.g., Build time reduced by X minutes]
- [Metric 3: e.g., User adoption rate of X%]

**Qualitative Measures:**
- [Quality improvement 1]
- [Developer experience improvement]
- [User feedback indicators]

**Review Date**: [When we'll evaluate if this was successful]

---

## Rollback Plan

### If This Decision Doesn't Work Out

**Symptoms That Would Trigger Rollback:**
- [Problem 1 that would indicate failure]
- [Problem 2 that would indicate we need to reverse course]

**Rollback Steps:**
1. [Step 1 to revert this decision]
2. [Step 2]
3. [Step 3]

**Fallback Solution:**
[What we'd do instead if we need to rollback]

---

## Related Documentation

### Referenced In

- [Feature document that uses this decision]
- [Architecture document that depends on this]

### References

- [External article/documentation 1]
- [External article/documentation 2]
- [Internal document 1]

### Supersedes

- [Previous decision record that this replaces, if any]

---

## Notes and Discussion

### Key Discussion Points

**Point 1:**
[Summary of important discussion point]
- Raised by: [Person/Role]
- Resolution: [How it was resolved]

**Point 2:**
[Summary of important discussion point]
- Raised by: [Person/Role]
- Resolution: [How it was resolved]

### Open Questions

- [Question 1 that remains unanswered]
- [Question 2 that needs future resolution]

### Future Considerations

- [Related decision that needs to be made later]
- [Enhancement to consider in v2.0]

---

## Changelog

### Updates to This Decision

**[Date]**: [What changed in this decision record]

**[Date]**: [Another update]

---

## Example Decision Records

See these examples for reference:

- `001-use-react-for-admin.md` - Frontend framework choice
- `002-simplify-onboarding.md` - UX simplification
- `003-custom-tables-vs-post-meta.md` - Database architecture

---

## Tips for Writing Good Decision Records

### Do:

✅ **Be specific** - "We chose React" not "We chose a framework"

✅ **Explain why** - Rationale is more important than the decision itself

✅ **List alternatives** - Show you considered other options

✅ **Be honest about trade-offs** - Every decision has downsides

✅ **Include context** - Future you needs to understand the situation

✅ **Use data** - Include metrics, benchmarks, user feedback

✅ **Write for the future** - Assume the reader has no context

### Don't:

❌ **Don't be vague** - "It's better" is not a rationale

❌ **Don't skip alternatives** - Show your thinking process

❌ **Don't hide problems** - Document risks and trade-offs honestly

❌ **Don't assume knowledge** - Explain acronyms and technical terms

❌ **Don't make it too long** - Be thorough but concise

❌ **Don't forget to update status** - Mark as Accepted/Rejected when decided

---

## When to Create a Decision Record

### Create a decision record for:

✅ **Architectural decisions**
- Choice of frameworks, libraries, technologies
- Database schema design
- API design
- System architecture

✅ **Major design decisions**
- UI/UX approaches that affect multiple features
- Workflow changes
- Breaking changes to existing functionality

✅ **Process decisions**
- Development workflow changes
- Testing strategy
- Deployment process

✅ **Technical direction**
- Support for older versions
- Performance vs. feature trade-offs
- Security approaches

### Don't create a decision record for:

❌ **Routine tasks**
- Bug fixes
- Small UI tweaks
- Dependency updates

❌ **Obvious choices**
- Following established patterns
- Using standard WordPress functions
- Implementing documented requirements

❌ **Temporary decisions**
- Quick experiments
- Prototypes
- "Try and see" approaches

**Rule of thumb**: If the decision will impact the codebase for more than 6 months or affects how other developers work, document it.

---

## Decision Record File Naming

### Naming Convention

Format: `NNN-short-descriptive-title.md`

Examples:
- `001-use-react-for-admin.md`
- `002-simplify-onboarding-wizard.md`
- `003-implement-custom-database-tables.md`
- `004-choose-rest-api-over-graphql.md`

### Numbering

- Start at `001` and increment
- Use leading zeros (001, 002, ... 010, 011)
- Numbers are sequential across the project
- Don't reuse numbers even if a decision is superseded

---

## Decision Status Definitions

### Proposed
Decision is being discussed, not yet finalized.

### Accepted
Decision has been made and will be implemented.

### Rejected
Decision was considered but rejected in favor of alternative.

### Deprecated
Decision was once valid but is now outdated (don't use anymore).

### Superseded
Decision was replaced by a newer decision (link to the new one).

---

## Quick Start

### Creating Your First Decision Record

1. **Copy this template**:
   ```bash
   cp docs/templates/decision-record-template.md docs/decisions/001-your-decision.md
   ```

2. **Fill in the sections** - Focus on Context, Decision, and Rationale first

3. **Review with team** (if applicable) - Get feedback before marking Accepted

4. **Mark as Accepted** - Change status when decision is finalized

5. **Link from relevant docs** - Reference from architecture or feature docs

6. **Update as needed** - Add to Changelog if decision evolves

---

## Real-World Example

See `docs/architecture/frontend-architecture.md` for examples of decisions made about:
- React for admin interface
- Vanilla JS for frontend quiz taking
- TypeScript throughout
- @wordpress/scripts build system

Each of these could have its own detailed decision record following this template.

---

**Remember**: The goal is to help future you (or future team members) understand why decisions were made, not just what was decided.
