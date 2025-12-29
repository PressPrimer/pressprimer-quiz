# Claude Code Instructions

This document provides guidance for AI coding assistants (Claude Code, Windsurf, etc.) on how to use the PressPrimer Quiz documentation effectively.

## Current Version Context

**Current Development Focus:** v2.x Free Plugin + Premium Addons  
**v1.0 Status:** Released and submitted to WordPress.org  
**Last Updated:** December 2025

Version 2.x establishes the commercial foundation for PressPrimer Quiz:
- **Free 2.x** adds LearnPress integration, UX improvements (condensed mode, display controls), and premium addon compatibility hooks
- **Premium addons launch** with three tiers: Educator ($149/yr), School ($299/yr), Enterprise ($499/yr)
- Premium licensing handled via Freemius SDK (not included in free plugin repo)

## Before Starting Any Work

Always read these files first:
1. `docs/PROJECT.md` - Understand the vision and principles
2. `docs/architecture/CONVENTIONS.md` - Know the naming conventions
3. `docs/architecture/DATABASE.md` - Understand the data model
4. `docs/versions/v2.x/OVERVIEW.md` - Know current development context

Then read the specific feature file(s) relevant to your task.

## Documentation Structure

```
docs/
├── PROJECT.md                       # Vision, business context (read first)
├── CLAUDE-INSTRUCTIONS.md           # This file
│
├── architecture/                    # Version-agnostic technical docs
│   ├── DATABASE.md                  # Schema, tables, indexes
│   ├── SECURITY.md                  # Security patterns
│   ├── CODE-STRUCTURE.md            # File organization
│   ├── CONVENTIONS.md               # Naming standards
│   ├── HOOKS.md                     # Actions and filters
│   ├── ACCESSIBILITY.md             # A11y requirements
│   └── rest-api.md                  # REST endpoint documentation
│
├── guides/                          # Process documentation
│   ├── development-workflow.md      # Dev process with Claude Code
│   ├── release-process.md           # Release steps
│   ├── testing-checklist.md         # QA checklist
│   └── frontend-architecture.md     # React/JS architecture patterns
│
├── decisions/                       # Architecture Decision Records
│   ├── 001-opt-in-data-removal.md
│   ├── 002-global-function-prefix.md
│   └── 003-no-freemius-in-free.md
│
├── versions/
│   ├── v1.0/                        # Historical reference (archived)
│   │   ├── CHANGELOG.md             # What shipped in v1.0
│   │   └── archive/                 # Old feature specs for reference
│   │
│   └── v2.x/                        # Current development
│       ├── OVERVIEW.md              # Current state, what's shipping
│       ├── ROADMAP.md               # 2.0 → 2.1 → 2.2 summary
│       │
│       ├── v2.0/
│       │   ├── SCOPE.md             # v2.0 scope document
│       │   └── features/
│       │       ├── upsell-touchpoints.md
│       │       ├── addon-compatibility.md
│       │       ├── learnpress-integration.md
│       │       ├── require-login.md
│       │       └── condensed-mode.md
│       │
│       ├── v2.1/
│       │   ├── SCOPE.md
│       │   └── features/
│       │       ├── celebration-notice.md
│       │       ├── visual-controls.md
│       │       ├── block-attributes.md
│       │       └── qol-improvements.md
│       │
│       └── v2.2/
│           ├── SCOPE.md
│           └── features/
│               ├── question-pool.md
│               ├── cache-clearing.md
│               └── attempt-pagination.md
│
└── addons/                          # Premium tier context
    ├── OVERVIEW.md                  # Premium tiers summary
    ├── educator/                    # $149/yr tier specs
    ├── school/                      # $299/yr tier specs
    └── enterprise/                  # $499/yr tier specs
```

## v2.x Development Priorities

### v2.0 Features (Immediate)
1. **Addon Compatibility Hooks** - Infrastructure for premium addons
2. **LearnPress Integration** - Fourth LMS integration
3. **Require Login Setting** - Global and per-quiz access control
4. **Condensed Mode** - Compact display density for themes
5. **Premium Upsell Touchpoints** - Locked UI hints (implement last)

### v2.1 Features (Next)
1. **100 Attempts Celebration** - Engagement notice for reviews
2. **Visual Appearance Controls** - Spacing and line height controls
3. **Block/Shortcode Attributes** - Control visible elements
4. **QoL Improvements** - Minor enhancements

### v2.2 Features (Following)
1. **Question Pool Maximum** - Limit questions from dynamic rules
2. **Cache Clearing Button** - Manual cache management
3. **Attempt Pagination** - Browse history of attempts

## Code Generation Guidelines

### WordPress Standards

Follow WordPress coding standards:
- PHP: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/
- JavaScript: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/
- CSS: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/

Key points:
- Use tabs for indentation (not spaces)
- Yoda conditions: `if ( 'value' === $variable )`
- Space inside parentheses: `function_name( $arg1, $arg2 )`
- Doc blocks for all functions and classes
- Escape all output, sanitize all input
- Use clear, effective documentation throughout the code; assume other developers will be reviewing it and that it will be turned into developer documentation

### Naming Conventions

**Critical - use these prefixes consistently:**

| Type | Prefix | Example |
|------|--------|---------|
| Database tables | `wp_ppq_` | `wp_ppq_questions` |
| **Global PHP functions** | `pressprimer_quiz_` | `pressprimer_quiz_init()` |
| **PHP classes** | `PressPrimer_Quiz_` | `class PressPrimer_Quiz_Question` |
| **Hooks (actions/filters)** | `pressprimer_quiz_` | `do_action( 'pressprimer_quiz_quiz_passed' )` |
| CSS classes | `ppq-` | `.ppq-quiz-container` |
| JavaScript | `PPQ` | `PPQ.submitQuiz()` |
| Shortcodes | `ppq_` | `[ppq_quiz]` |
| Text domain | `pressprimer-quiz` | `__( 'Quiz', 'pressprimer-quiz' )` |
| Options | `ppq_` | `get_option( 'ppq_settings' )` |
| User meta | `ppq_` | `get_user_meta( $id, 'ppq_api_key' )` |
| Transients | `ppq_` | `get_transient( 'ppq_report_cache' )` |
| Capabilities | `ppq_` | `ppq_manage_all` |
| Nonces | `ppq_` | `ppq_submit_quiz` |

**Important:** Global namespace identifiers (functions, classes, and hooks) must use the full `pressprimer_quiz_` or `PressPrimer_Quiz_` prefix to meet WordPress.org Plugin Check requirements. The shorter `ppq_` prefix is acceptable for internal identifiers like options, meta keys, capabilities, CSS classes, and JavaScript since these are either stored values or scoped to plugin output.

### Security Requirements

**Never compromise on these:**

1. **Server-side validation only** - Never expose correct answers in HTML or JavaScript
2. **Nonce verification** - All forms and AJAX calls must verify nonces
3. **Capability checks** - Check permissions before any action
4. **Prepared statements** - Use `$wpdb->prepare()` for all SQL queries
5. **Sanitization** - Sanitize all user input
6. **Escaping** - Escape all output
7. **Rate limiting** - 10 quiz attempts per 1 minute per IP

### Internationalization

Every user-facing string must be translatable:

```php
// Simple string
__( 'Quiz Results', 'pressprimer-quiz' )

// String with placeholders
sprintf(
    __( 'Question %1$d of %2$d', 'pressprimer-quiz' ),
    $current,
    $total
)

// String that escapes output
esc_html__( 'Start Quiz', 'pressprimer-quiz' )

// String with HTML that escapes
esc_html_e( 'Submit', 'pressprimer-quiz' );
```

### Accessibility Requirements

All UI components must be:
- Keyboard navigable (Tab, Enter, Space, Arrow keys)
- Screen reader compatible (ARIA labels, roles, live regions)
- High contrast friendly
- Focus visible
- Error messages announced

## Reading Feature Specifications

Each feature file in `versions/v2.x/` normally contains:

1. **Overview** - What the feature does
2. **User Stories** - Who needs it and why
3. **Acceptance Criteria** - How we know it's done
4. **Technical Implementation** - How to build it
5. **Database Requirements** - Tables and fields needed
6. **UI/UX Requirements** - Interface specifications
7. **Edge Cases** - What could go wrong
8. **Not In Scope** - What to explicitly exclude

**Important:** Pay attention to "Not In Scope" sections. Don't build features that are listed for future versions or premium tiers. If any sections are not included, develop the feature based on what is known and reasonable assumptions.

## When Implementing

### Starting a Feature

1. Read the feature file completely
2. Check DATABASE.md for relevant tables
3. Check SECURITY.md for security patterns
4. Check CONVENTIONS.md for naming
5. Check HOOKS.md for addon compatibility hooks needed
6. Create database tables first (if needed)
7. Build models/classes
8. Build admin interface
9. Build frontend interface
10. Add hooks for extensibility
11. Test manually

### Creating Database Tables

Use the migration pattern from DATABASE.md:
- Version check before running migrations
- Use `dbDelta()` for table creation
- Add proper indexes
- Include foreign key comments (WordPress doesn't enforce FK constraints)

### Building Admin Interfaces

- Use WordPress admin UI patterns
- Add settings pages under "PressPrimer Quiz" menu
- Use WordPress native controls (not custom React unless necessary)
- Follow the UX patterns in the feature specs

### Building Frontend Interfaces

- Use shortcodes AND Gutenberg blocks
- Enqueue scripts/styles properly
- Never expose answers in page source
- Support mobile devices
- Meet accessibility requirements
- Support condensed mode for all themes (v2.0+)

### Adding Addon Compatibility Hooks

When building free plugin features, add extension points for premium addons:
- Use `pressprimer_quiz_*` hook prefix for all addon hooks
- Document hooks in `architecture/HOOKS.md`
- Provide sensible defaults for filters
- Consider what premium tiers might extend

## Testing Expectations

After each feature, manually test:
- Does the feature work as specified?
- Does it work on mobile?
- Can you navigate with keyboard only?
- Are all strings translatable?
- Is the code properly escaped and sanitized?
- Do capability checks work?
- Does condensed mode display correctly? (if frontend)

## Questions to Ask Yourself

Before marking any feature complete:

1. Would this code pass WordPress.org plugin review?
2. Can a screen reader user complete this action?
3. Are all user inputs validated and sanitized?
4. Are correct answers ever visible in page source?
5. Is this translatable to other languages?
6. Is the UI consistent with other parts of the plugin?
7. Are appropriate addon hooks in place for premium extensibility?

## Common Mistakes to Avoid

1. **Don't build premium features** - If it's listed for Educator/School/Enterprise tier, don't include it in Free
2. **Don't skip security** - Every input needs sanitization, every output needs escaping
3. **Don't hardcode strings** - Everything user-facing needs `__()`
4. **Don't forget mobile** - Test at 375px width
5. **Don't ignore edge cases** - What if there are 0 questions? What if time runs out?
6. **Don't break accessibility** - Tab order, focus states, ARIA labels
7. **Don't expose answers** - Server validates everything; client knows nothing about correctness until after submission
8. **Don't forget condensed mode** - New frontend features must support both standard and condensed display densities
9. **Don't skip addon hooks** - Features should have extension points for premium addons

## Updating Documentation

If you discover something that should be documented:
- Note it at the end of the relevant file
- Mark it clearly as "ADDED DURING DEVELOPMENT"
- Include the date

This helps maintain documentation as a living resource.
