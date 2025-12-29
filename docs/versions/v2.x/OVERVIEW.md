# PressPrimer Quiz v2.x - Overview

> **Purpose:** Context document for AI-assisted development. Provides scope and strategic context for the 2.x release series of both the free plugin and premium addons.

> **Last Updated:** 2025-12-26

---

## Strategic Context

### Where We Are

Version 1.0 of the free plugin has been submitted to WordPress.org for review. The plugin includes:

- Three question types (multiple choice, multiple answer, true/false)
- Question banks with category and difficulty tagging
- Quiz builder with fixed and dynamic question assignment
- Timer support, randomization, and navigation options
- Three visual themes (Default, Modern, Minimal)
- Guest and logged-in user support with email capture
- AI question generation (users provide their own OpenAI API key)
- LMS integrations (LearnDash, TutorLMS, LifterLMS)
- Uncanny Automator integration
- Admin dashboard and reports
- Gutenberg blocks and shortcodes

### Where We're Going

Version 2.x establishes the commercial foundation for PressPrimer Quiz:

1. **Free 2.x** adds LearnPress integration, UX improvements (condensed mode, display controls), and premium addon compatibility hooks
2. **Premium addons launch** with three tiers: Educator ($149/yr), School ($299/yr), Enterprise ($499/yr)
3. **User engagement** drives WordPress.org reviews and builds the customer base

---

## Release Philosophy

### Versioning Strategy

| Version | Type | Description |
|---------|------|-------------|
| 1.0.x | Patch | Bug fixes only, no new features |
| 2.0, 2.1, 2.2 | Minor | New features, database changes allowed |
| 3.0+ | Major | Reserved for significant architectural changes |

- **No v1.1 release** — Jump straight from 1.0.x patches to 2.0
- **Premium versions track free versions** — Educator 2.0 launches with Free 2.0
- **1-2 major features per minor release** — Keeps scope manageable

### Release Timing

| Release | Target | Dependencies |
|---------|--------|--------------|
| Free 2.0 | 3-4 weeks after v1.0 approval | WordPress.org approval |
| Premium 2.0 (all tiers) | Simultaneous with Free 2.0 | Licensing platform setup |
| Free 2.1 | 4-5 weeks after 2.0 | — |
| Premium 2.1 (all tiers) | Simultaneous with Free 2.1 | — |
| Free 2.2 | 4-5 weeks after 2.1 | — |
| Premium 2.2 (all tiers) | Simultaneous with Free 2.2 | — |

---

## Free Plugin 2.x Roadmap

### Version 2.0 — "Commercial Foundation"

**Theme:** Premium infrastructure and key UX wins

| Feature | Description |
|---------|-------------|
| Premium Upsell Touchpoints | Locked feature indicators with tooltips throughout admin |
| Addon Compatibility Hooks | Extension points for premium addons to register and add features |
| LearnPress Integration | Fourth LMS integration with lesson completion triggers |
| Require Login Setting | Global + per-quiz option to require authentication |
| Condensed Mode | Display density option reducing vertical space across all themes |

**Key Decision:** No licensing SDK in the free plugin. Premium addons handle licensing independently. Upsells in the free plugin link to pressprimer.com, not in-admin checkout.

### Version 2.1 — "Display Flexibility"

**Theme:** Customization and user engagement

| Feature | Description |
|---------|-------------|
| 100 Attempts Celebration | Admin notice encouraging WordPress.org reviews at milestones |
| Visual Appearance Controls | Line height, spacing, max width settings |
| Block/Shortcode Attributes | Toggle visibility of Start/Results page elements |
| QoL Improvements | Timer position options, mobile navigation, admin quick actions |

### Version 2.2 — "Advanced Quiz Controls"

**Theme:** Sophisticated quiz configuration

| Feature | Description |
|---------|-------------|
| Maximum Questions from Pool | Limit questions shown per attempt from larger pools |
| Cache Clearing Tools | Manual refresh button for statistics and reports |
| Attempt History Pagination | Lazy loading for users with many quiz attempts |

---

## Premium Addons 2.x Roadmap

### Tier Structure

| Tier | Price | Target User | Key Differentiator |
|------|-------|-------------|-------------------|
| **Educator** | $149/yr (1 site) | Individual teachers, tutors, course creators | Groups, assignments, AI tools |
| **School** | $299/yr (3 sites) | Departments, small training teams | xAPI, shared banks, availability controls |
| **Enterprise** | $499/yr (5 sites) | Large organizations, compliance-driven | Audit logging, proctoring, branching |

Each tier includes all features from lower tiers.

### Educator Tier 2.x

| Version | Features |
|---------|----------|
| **2.0** | Group Support, Assigned Quizzes, Question/Bank Import/Export (CSV, XML) |
| **2.1** | AI Distractor Generation, Question Quality Reports |
| **2.2** | Pre/Post Test Comparison Reports (frontend and admin) |

### School Tier 2.x

*Includes all Educator features*

| Version | Features |
|---------|----------|
| **2.0** | Quiz Availability Windows (date/time restrictions), Shared Question Banks |
| **2.1** | xAPI/LRS Integration, Group Reports |
| **2.2** | Curve Grading |

### Enterprise Tier 2.x

*Includes all Educator and School features*

| Version | Features |
|---------|----------|
| **2.0** | Audit History Logging, White-Label Branding |
| **2.1** | Branching/Adaptive Logic |
| **2.2** | Basic Proctoring (tab switching, focus detection) |

---

## Technical Architecture

### Plugin Relationships

```
┌─────────────────────────────────────┐
│     PressPrimer Quiz (Free)         │  ← WordPress.org
│     - Core quiz functionality       │
│     - Addon compatibility hooks     │
│     - No licensing SDK              │
└─────────────────────────────────────┘
              │
              │ depends on
              ▼
┌─────────────────────────────────────┐
│     Educator Addon                  │  ← Freemius or EDD
│     - Licensing SDK                 │
│     - Groups, Assignments           │
│     - Import/Export, AI Distractors │
└─────────────────────────────────────┘
              │
              │ extends
              ▼
┌─────────────────────────────────────┐
│     School Addon                    │  ← Freemius or EDD
│     - Requires Educator             │
│     - xAPI, Availability, Shared    │
│     - Group Reports, Curve Grading  │
└─────────────────────────────────────┘
              │
              │ extends
              ▼
┌─────────────────────────────────────┐
│     Enterprise Addon                │  ← Freemius or EDD
│     - Requires School               │
│     - White-label, Audit, Proctoring│
│     - Branching Logic               │
└─────────────────────────────────────┘
```

### Addon Loading Order

1. Free plugin initializes, fires `pressprimer_quiz_loaded`
2. Educator addon hooks in, initializes licensing SDK
3. School addon hooks in, extends Educator
4. Enterprise addon hooks in, extends School
5. Free plugin fires `pressprimer_quiz_addons_loaded`

### Key Extension Points (Free Plugin)

```php
// Addon registration
do_action( 'pressprimer_quiz_register_addon', $slug, $version, $features );

// Feature detection
pressprimer_quiz_has_addon( 'educator' );     // bool
pressprimer_quiz_feature_enabled( 'groups' ); // bool

// UI extension points
do_action( 'pressprimer_quiz_builder_settings_after', $quiz_id );
do_action( 'pressprimer_quiz_question_editor_after_answers', $question );
do_action( 'pressprimer_quiz_results_after_score', $attempt );
do_action( 'pressprimer_quiz_settings_tabs', $current_tab );
```

---

## What's NOT in 2.x

These features are planned for 3.x or later:

### Free 3.x (Future)
- Student self-quiz generation from banks
- Additional LMS integrations (based on demand)
- New question types (under consideration)

### Premium 3.x (Future)
- **Educator 3.0:** Frontend Quiz Authoring for Teachers
- **School 3.0:** Frontend Quiz Authoring for Students  
- **Enterprise 3.0:** Advanced Audit Logging, Bulk Operations

### Premium 4.x (Future)
- **Educator 4.x:** Advanced Spaced Repetition, Custom Templates
- **School 4.x:** Kirkpatrick L1-3, Predictive Analytics, Longitudinal Tracking
- **Enterprise 4.x:** Psychometrics, IRT, FERPA Compliance

---

## Development Workflow

### Branching Strategy

```
main (stable, released)
├── release/2.0
│   ├── feature/upsell-touchpoints
│   ├── feature/addon-hooks
│   ├── feature/learnpress
│   ├── feature/require-login
│   └── feature/condensed-mode
├── release/2.1
│   └── feature/...
└── release/2.2
    └── feature/...
```

### Automation Goals

- GitHub Actions for CI/CD
- Automated testing (Playwright for E2E)
- Automated WordPress.org deployment via SVN
- Changelog generation from conventional commits
- Version bumping and tagging

### Quality Gates

Before each release:
1. All automated tests pass
2. Manual testing checklist complete
3. Security audit (escaping, sanitization, capabilities)
4. Accessibility review
5. Performance check (1000+ questions)

---

## Success Metrics

### Free Plugin 2.x

| Metric | Target |
|--------|--------|
| Active installs | 1,000+ by end of 2.x cycle |
| WordPress.org rating | 4.5+ stars |
| Support response time | <24 hours |
| Update adoption | 80%+ within 7 days |

### Premium Addons 2.x

| Metric | Target |
|--------|--------|
| Paid licenses | 50+ in first 90 days |
| Tier distribution | 60% Educator, 30% School, 10% Enterprise |
| Refund rate | <5% |
| Renewal rate | 70%+ |

### Long-term (end of 2.x)

| Metric | Target |
|--------|--------|
| ARR | $50,000+ |
| Support ticket volume | <20/month |
| Feature request backlog | Actively triaged |

---

## Key Decisions Made

### Freemius vs EDD: UNDETERMINED

**Status:** The licensing platform for premium addons has not been finalized.

**Options:**
- **Freemius** — Turnkey solution with built-in checkout, licensing, updates, and analytics
- **EDD (Easy Digital Downloads)** — Self-hosted solution with more control but more setup

**Consideration:** Canadian tax compliance (GST/HST collection) is a factor in this decision. Freemius may not adequately handle Canadian tax requirements, which could necessitate using EDD with proper tax configuration.

**Impact on Development:** The addon compatibility hooks in the free plugin are designed to be licensing-agnostic. The choice of Freemius vs EDD affects only the premium addon code, not the free plugin.

### No Licensing SDK in Free Plugin: YES

**Decision:** The free plugin will NOT include any licensing SDK (Freemius or EDD).

**Rationale:**
- Cleaner WordPress.org submission (no SDK review complications)
- Users discover premium via website marketing and in-plugin upsell links
- Premium addons handle licensing independently
- Avoids SDK-related user complaints (privacy, nag screens)

**Trade-off:** No in-admin checkout. Users click through to pressprimer.com to purchase.

### Premium Tier Dependency Chain: YES

**Decision:** Higher tiers require lower tiers (Enterprise requires School requires Educator).

**Rationale:**
- Simplifies development (each tier builds on previous)
- Clear upgrade path for customers
- Reduces code duplication

**Trade-off:** Enterprise customers pay for all three tiers ($499 includes School + Educator value).

### LMS Integrations in Free: YES

**Decision:** All LMS integrations remain in the free plugin.

**Rationale:**
- Differentiator from competitors who gate integrations
- Drives adoption in LMS-heavy markets
- Word of mouth from LearnDash/Tutor/Lifter communities

---

## Documentation Structure

```
docs/
├── versions/
│   ├── v1.0/
│   │   └── CHANGELOG.md          # Historical record
│   │
│   └── v2.x/
│       ├── OVERVIEW.md           # This file
│       ├── ROADMAP.md            # High-level timeline
│       │
│       ├── v2.0/
│       │   ├── SCOPE.md          # Detailed scope
│       │   └── features/         # Feature specs
│       │
│       ├── v2.1/
│       │   ├── SCOPE.md
│       │   └── features/
│       │
│       └── v2.2/
│           ├── SCOPE.md
│           └── features/
│
└── addons/
    ├── educator/
    │   └── OVERVIEW.md           # Educator tier context
    ├── school/
    │   └── OVERVIEW.md           # School tier context
    └── enterprise/
        └── OVERVIEW.md           # Enterprise tier context
```

