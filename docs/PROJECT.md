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
Full-featured quiz platform: unlimited quizzes, unlimited questions, AI generation (user's own API key), LMS integration (LearnDash, TutorLMS, LifterLMS), three professional themes. Not a trial—genuinely useful forever.

### Educator ($149/year - 1 site)
For individual teachers and small teams who need: groups and quiz assignments, import/export, pre/post test linking, enhanced reporting with charts, AI distractor generation, LaTeX math support, confidence ratings reports.

### School ($299/year - 3 sites)
For departments and organizations: multi-teacher coordination, shared question banks, xAPI/LRS support, spaced repetition, curve grading, quiz availability windows (date/time restrictions), longitudinal reporting.

### Enterprise ($499/year - 5 sites)
For large organizations: white labeling, branching/adaptive quizzes, proctoring suite, comprehensive audit logging, compliance reporting.

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
- Superior question management
- AI generation capabilities
- More assessment options
- Better reporting

**vs. Enterprise Assessment Platforms:**
- WordPress-native (no migration)
- 10-100x more affordable
- Familiar ecosystem
- Self-hosted data ownership

## Technical Philosophy

1. **Custom tables over CPTs** - Performance at scale requires proper database design
2. **React admin, vanilla frontend** - Modern admin experience, fast quiz delivery
3. **Server-side everything** - Never trust the client, especially with correct answers
4. **Hooks everywhere** - Extensibility for developers and future premium features
5. **WordPress standards** - Coding standards, translation ready, accessibility compliant

## Version Strategy

### v1.0 Free (Initial Release)
Prove product-market fit with a genuinely useful free plugin. 1,000+ active installs, 4.0+ star rating.

### v2.0 (Premium Launch)
Launch all three premium tiers simultaneously. Establish recurring revenue.

### v3.0+ (Feature Expansion)
Additional question types, deeper integrations, advanced psychometrics based on user feedback.

## Success Metrics

### Product Success
- Active installations: 1,000 (90 days), 5,000 (1 year)
- WordPress.org rating: 4.0+ stars
- Support resolution: <48 hours average
- Churn rate: <20% annual

### Business Success
- Paid conversions: 2-5% of active free users
- ARR target: $100K by month 12
- ARPU: $200+ (blended across tiers)

## Team & Resources

**Current:** Solo founder with AI-assisted development
**Near-term:** Contract designers for marketing assets
**Future:** Support contractor when volume requires

Development velocity multiplied by Claude Code and comprehensive documentation.
