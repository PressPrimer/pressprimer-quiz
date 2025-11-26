# PressPrimer Quiz - Project Vision

## Product Identity

**Name:** PressPrimer Quiz  
**Tagline:** Enterprise-grade assessment for WordPress educators.
**Type:** WordPress plugin for learning assessment  
**Brand:** Part of the PressPrimer plugin suite  

## The Problem We Solve

The WordPress education space faces a stark divide: overly simplistic quiz plugins on one side, and expensive enterprise LMS platforms ($10K-$100K/year) on the other. Typical WordPress quiz plugins offer basic multiple-choice functionality with dated interfaces, minimal reporting, and no understanding of learning science. They treat assessment as an afterthought—simple forms that check answers without measuring actual knowledge transfer.

Meanwhile, serious educators and training departments—universities running certificate programs, corporate teams developing employees, course creators building businesses—need enterprise-quality assessment but can't justify expensive LMS migrations or abandoning WordPress.

## Our Solution

PressPrimer Quiz bridges this gap by bringing learning science, beautiful modern design, and enterprise-grade features to WordPress at accessible price points. We focus on doing multiple choice questions exceptionally well, with features that serious educators expect: massive shareable question banks, evidence-based assessment tools (confidence ratings, knowledge transfer measurement, item analysis), and deep integrations with the WordPress LMS ecosystem.

## Target Users

**Primary Markets:**
- Individual educators using LearnDash, TutorLMS, LearnPress, or LifterLMS
- University departments with 50-500 students
- Corporate training teams with 100-5,000 employees
- Educational entrepreneurs running course businesses
- Professional associations delivering certifications

**User Personas:**
1. **Sarah the Professor** - Uses LearnDash for her department's certificate program. Needs pre/post testing, real analytics, and something that looks professional.
2. **Mike the Training Manager** - Runs compliance training for 2,000 employees on WordPress. Needs audit trails, xAPI support, and enterprise-level reliability.
3. **Lisa the Course Creator** - Sells courses online using LifterLMS. Needs beautiful quiz experiences that don't look like forms from 2005.

## Core Principles

### 1. Learning-Focused
Built on cognitive science, not just answer checking. We measure actual learning transfer, support evidence-based teaching practices, and provide meaningful analytics.

### 2. Visually Exceptional
Design quality that rivals SaaS products. Modern, clean, professional. No one should be embarrassed to show a PressPrimer quiz to students or stakeholders.

### 3. WordPress-Native
Deep LMS integration, not bolted-on compatibility. We work seamlessly with LearnDash, TutorLMS, and LifterLMS. We feel like a natural extension of WordPress.

### 4. Scale-Ready
From 10 students to 10,000 employees. Custom database tables, proper indexing, object caching support. Enterprise reliability at WordPress prices.

### 5. Free That's Actually Free
The free version isn't crippled. It's genuinely useful forever with unlimited quizzes, AI generation, LMS integration, and beautiful themes. Premium tiers add specialized features for edge cases.

## Business Model

### Free (WordPress.org)
Full-featured quiz platform: unlimited quizzes, unlimited questions, AI generation (user's own API key), LMS integration, three professional themes, groups and assignments. Not a trial—genuinely useful forever.

### Educator ($149/year - 1 site)
For individual teachers and small teams who need advanced features: enhanced reporting, AI distractor generation, import/export, LaTeX math support, confidence ratings, survey/ungraded questions.

### School ($249/year - 3 sites)
For departments and organizations: group/multi-teacher system, xAPI support, spaced repetition, student self-quiz generation, reporting by attempt over time periods.

### Enterprise ($399/year - 5 sites)
For large organizations: white labeling, adaptive/branching quizzes, proctoring, emailed reports, audit logging.

## Question Types (Scope)

We intentionally limit to three question types in v1.0 and do them exceptionally well:

1. **Multiple Choice** - Single correct answer
2. **Multiple Answer** - Select all that apply, with partial credit
3. **True/False** - Binary choice

We do NOT support: essay, fill-in-blank, matching, ordering, hotspot, video, audio, or other question types in v1.0. Focus beats feature sprawl.

## Key Differentiators

**vs. Simple Quiz Plugins (Quiz Cat, Quiz Maker, etc.):**
- Enterprise-quality design (not dated 2010 aesthetics)
- AI-powered question generation
- Learning science features (confidence, spaced repetition, psychometrics)
- Advanced reporting (not just score percentages)
- Deep LMS integration

**vs. Built-in LMS Quizzes:**
- Cross-LMS compatibility
- Better reporting and analytics
- More sophisticated question handling
- Beautiful, modern design
- Specialized focus on assessment

**vs. Enterprise SaaS LMS:**
- WordPress-native (familiar, flexible, owned)
- Affordable pricing ($149-399 vs $10K-100K)
- No per-user fees (unlimited students)
- No vendor lock-in
- Customizable and extensible

## Technical Requirements

**Platform:**
- WordPress 6.0+ required
- PHP 7.4+ required
- MySQL 5.7+ / MariaDB 10.3+
- Works with or without LMS plugins

**Quality Standards:**
- Custom database tables for performance at scale (10K+ questions, 100K+ attempts)
- Object caching support (Redis, Memcached)
- WCAG 2.1 AA accessibility compliance
- Full translation/i18n support
- WordPress coding standards compliance
- Server-side answer validation only (never expose correct answers)
- Comprehensive security (nonce verification, prepared statements, rate limiting)

## Success Metrics

**v1.0 Free (90 days post-launch):**
- 1,000+ active installations
- 4.0+ star average rating
- <15% unresolved support threads
- 10+ five-star reviews with testimonials

**Premium 1.0 (6 months):**
- $30K+ ARR
- 100+ paying customers
- 3,000+ free installs

**Year 3 Target:**
- $550K ARR
- $330K EBITDA
- Positioned for acquisition at 2.5x EBITDA ($825K+)

## Development Approach

This is a solo-developed plugin using AI-assisted development (Claude Code). The documentation in this folder serves as the source of truth for all development work. Claude Code should read these documents before implementing any feature.

### Key Development Principles:
1. **Code quality over speed** - This is a commercial plugin; reliability matters
2. **Security first** - Never expose answers client-side; validate everything server-side
3. **Accessibility from day one** - WCAG 2.1 AA, not retrofitted later
4. **Translation ready** - All strings wrapped properly from the start
5. **Test as you go** - Manual testing at each phase, not just at the end

## Document Index

Read documents in this order:

1. **This file (PROJECT.md)** - Vision and context
2. **CLAUDE-INSTRUCTIONS.md** - How to use documentation with Claude Code
3. **architecture/DATABASE.md** - Understand the data model
4. **architecture/CONVENTIONS.md** - Naming and coding standards
5. **versions/v1.0/OVERVIEW.md** - What's in v1.0
6. **versions/v1.0/PHASES.md** - Development breakdown
7. **Individual feature files** - As needed for implementation

