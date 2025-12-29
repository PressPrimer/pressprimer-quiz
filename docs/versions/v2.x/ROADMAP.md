# PressPrimer Quiz v2.x - Roadmap

> **Purpose:** High-level timeline and feature summary for the 2.x release series.

> **Last Updated:** 2025-12-26

---

## Timeline Overview

```
v1.0 Approval
     │
     ▼
┌─────────────────────────────────────────────────────────────────┐
│  v2.0 — "Commercial Foundation"                                 │
│  Target: 3-4 weeks after v1.0 approval                         │
│  Free + Educator + School + Enterprise                          │
└─────────────────────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────────────────────┐
│  v2.1 — "Display Flexibility" / "AI & Reporting"               │
│  Target: 4-5 weeks after v2.0                                   │
│  Free + Educator + School + Enterprise                          │
└─────────────────────────────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────────────────────────┐
│  v2.2 — "Advanced Controls" / "Analytics & Compliance"         │
│  Target: 4-5 weeks after v2.1                                   │
│  Free + Educator + School + Enterprise                          │
└─────────────────────────────────────────────────────────────────┘
     │
     ▼
v3.0 Planning
```

**Total 2.x cycle:** ~12-14 weeks from v1.0 approval

---

## Version 2.0 — "Commercial Foundation"

**Target:** 3-4 weeks after WordPress.org approval

### Free Plugin

| Feature | Description |
|---------|-------------|
| Upsell Touchpoints | Locked feature indicators linking to pressprimer.com |
| Addon Compatibility Hooks | Extension points for premium addons |
| LearnPress Integration | Fourth LMS with lesson completion triggers |
| Require Login Setting | Global + per-quiz authentication requirement |
| Condensed Mode | Compact display density for all themes |

### Educator Addon ($149/yr)

| Feature | Description |
|---------|-------------|
| Group Support | Create and manage student/employee groups |
| Assigned Quizzes | Assign quizzes to groups with due dates |
| Import/Export | CSV and XML for questions and banks |

### School Addon ($299/yr)

*Includes all Educator features*

| Feature | Description |
|---------|-------------|
| Availability Windows | Date/time restrictions for quiz access |
| Shared Question Banks | Cross-teacher bank sharing with permissions |

### Enterprise Addon ($499/yr)

*Includes all Educator and School features*

| Feature | Description |
|---------|-------------|
| Audit History | Log all quiz and question changes |
| White-Label Branding | Remove PressPrimer branding, custom colors |

---

## Version 2.1 — "Display Flexibility" / "AI & Reporting"

**Target:** 4-5 weeks after v2.0

### Free Plugin

| Feature | Description |
|---------|-------------|
| 100 Attempts Celebration | Review request notice at milestones |
| Visual Appearance Controls | Line height, spacing, max width settings |
| Block/Shortcode Attributes | Toggle Start/Results page elements |
| QoL Improvements | Timer positions, mobile nav, admin shortcuts |

### Educator Addon

| Feature | Description |
|---------|-------------|
| AI Distractor Generation | Generate plausible wrong answers with AI |
| Question Quality Reports | Analysis of question performance metrics |

### School Addon

| Feature | Description |
|---------|-------------|
| xAPI/LRS Integration | Send quiz data to Learning Record Stores |
| Group Reports | Aggregate reporting by group |

### Enterprise Addon

| Feature | Description |
|---------|-------------|
| Branching/Adaptive Logic | Dynamic question paths based on responses |

---

## Version 2.2 — "Advanced Controls" / "Analytics & Compliance"

**Target:** 4-5 weeks after v2.1

### Free Plugin

| Feature | Description |
|---------|-------------|
| Question Pool Limits | Show X questions from larger pools |
| Cache Clearing Tools | Manual stats refresh button |
| Attempt Pagination | Lazy loading for attempt history |

### Educator Addon

| Feature | Description |
|---------|-------------|
| Pre/Post Comparison | Knowledge gain reports (frontend + admin) |

### School Addon

| Feature | Description |
|---------|-------------|
| Curve Grading | Adjust scores based on cohort performance |

### Enterprise Addon

| Feature | Description |
|---------|-------------|
| Basic Proctoring | Tab switching, focus detection, timestamps |

---

## Feature Distribution Summary

### By Tier

| Tier | 2.0 | 2.1 | 2.2 |
|------|-----|-----|-----|
| **Free** | LearnPress, Condensed Mode, Login Required, Addon Hooks, Upsells | Celebration Notice, Visual Controls, Block Attributes, QoL | Question Pools, Cache Tools, Pagination |
| **Educator** | Groups, Assignments, Import/Export | AI Distractors, Quality Reports | Pre/Post Comparison |
| **School** | Availability, Shared Banks | xAPI/LRS, Group Reports | Curve Grading |
| **Enterprise** | Audit Log, White-Label | Branching Logic | Basic Proctoring |

### By Category

| Category | Features | Version |
|----------|----------|---------|
| **Commercial** | Upsells, Addon Hooks | Free 2.0 |
| **LMS** | LearnPress | Free 2.0 |
| **Display** | Condensed Mode | Free 2.0 |
| **Display** | Visual Controls, Block Attributes | Free 2.1 |
| **Access** | Require Login | Free 2.0 |
| **Access** | Availability Windows | School 2.0 |
| **Groups** | Group Support, Assignments | Educator 2.0 |
| **Groups** | Group Reports | School 2.1 |
| **AI** | Distractor Generation | Educator 2.1 |
| **Reporting** | Quality Reports | Educator 2.1 |
| **Reporting** | Pre/Post Comparison | Educator 2.2 |
| **Reporting** | Curve Grading | School 2.2 |
| **Data** | Import/Export | Educator 2.0 |
| **Data** | Shared Banks | School 2.0 |
| **Data** | xAPI/LRS | School 2.1 |
| **Compliance** | Audit History | Enterprise 2.0 |
| **Compliance** | Proctoring | Enterprise 2.2 |
| **Branding** | White-Label | Enterprise 2.0 |
| **Advanced** | Branching Logic | Enterprise 2.1 |
| **Advanced** | Question Pools | Free 2.2 |

---

## Dependencies

### Release Dependencies

```
Free 2.0
    │
    ├── Educator 2.0 (requires Free 2.0)
    │       │
    │       ├── School 2.0 (requires Educator 2.0)
    │       │       │
    │       │       └── Enterprise 2.0 (requires School 2.0)
    │       │
    │       ├── Educator 2.1
    │       │       │
    │       │       ├── School 2.1
    │       │       │       │
    │       │       │       └── Enterprise 2.1
    │       │       │
    │       │       └── Educator 2.2
    │       │               │
    │       │               ├── School 2.2
    │       │               │       │
    │       │               │       └── Enterprise 2.2
```

### Feature Dependencies

| Feature | Depends On |
|---------|------------|
| Assigned Quizzes | Group Support |
| Group Reports | Group Support, xAPI/LRS |
| Shared Banks | Group Support |
| Pre/Post Comparison | Quiz linking infrastructure |
| Branching Logic | Question navigator enhancements |
| Proctoring | JavaScript monitoring framework |

---

## External Dependencies

| Dependency | Status | Impact |
|------------|--------|--------|
| WordPress.org v1.0 approval | Pending | Blocks all 2.x releases |
| Licensing platform decision | Undetermined | Affects premium addon architecture |
| LearnPress 4.0+ | Available | Required for integration |
| OpenAI API | Available | Required for AI Distractors |
| xAPI spec compliance | Research needed | Affects LRS integration |

---

## Risk Factors

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| WordPress.org rejection | Low | High | Thorough pre-submission review completed |
| Licensing platform issues | Medium | Medium | Addon hooks are platform-agnostic |
| Scope creep | Medium | Medium | Strict 1-2 features per release |
| xAPI complexity | Medium | Low | Can defer to 2.2 if needed |
| Branching logic complexity | High | Medium | Start with simple conditional rules |

---

## Post-2.x Preview

### Version 3.x — "Frontend Authoring"

- **Educator 3.0:** Frontend quiz creation for teachers
- **School 3.0:** Student self-quiz generation
- **Enterprise 3.0:** Advanced audit, bulk operations

### Version 4.x — "Learning Science"

- **Educator 4.x:** Spaced repetition, custom templates
- **School 4.x:** Kirkpatrick L1-3, predictive analytics
- **Enterprise 4.x:** Psychometrics, IRT, compliance reporting

