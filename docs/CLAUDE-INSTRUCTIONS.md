# Claude Code Instructions

This document provides guidance for AI coding assistants (Claude Code, Windsurf, etc.) on how to use the PressPrimer Quiz documentation effectively.

## Before Starting Any Work

Always read these files first:
1. `docs/PROJECT.md` - Understand the vision and principles
2. `docs/architecture/CONVENTIONS.md` - Know the naming conventions
3. `docs/architecture/DATABASE.md` - Understand the data model
4. `docs/versions/v1.0/OVERVIEW.md` - Know what's in scope

Then read the specific feature file(s) relevant to your task.

## Documentation Structure

```
docs/
├── PROJECT.md                 # Vision, business context (read first)
├── CLAUDE-INSTRUCTIONS.md     # This file
├── architecture/              # Version-agnostic technical docs
│   ├── DATABASE.md           # Schema, tables, indexes
│   ├── SECURITY.md           # Security patterns
│   ├── CODE-STRUCTURE.md     # File organization
│   ├── CONVENTIONS.md        # Naming standards
│   └── HOOKS.md              # Actions and filters
├── versions/v1.0/            # v1.0 Free specifications
│   ├── OVERVIEW.md           # Goals, metrics, scope
│   ├── PHASES.md             # Development phases
│   └── features/             # Detailed feature specs
└── addons/                   # Premium tier context (for reference)
```

## Understanding the Phase System

Version 1.0 is divided into 8 phases. Each phase has multiple prompts. Work through one phase at a time, completing all prompts before moving on.

**Phase Dependencies:**
- Phase 1 must complete before any other phase
- Phases 2-4 can proceed in order after Phase 1
- Phases 5-7 depend on Phase 4 completion
- Phase 8 depends on all previous phases

**Within Each Phase:**
- Complete prompts in order
- Test each prompt's output before proceeding
- Don't skip prompts or combine them

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

Each feature file in `versions/v1.0/features/` normally contains:

1. **Overview** - What the feature does
2. **User Stories** - Who needs it and why
3. **Acceptance Criteria** - How we know it's done
4. **Technical Implementation** - How to build it
5. **Database Requirements** - Tables and fields needed
6. **UI/UX Requirements** - Interface specifications
7. **Edge Cases** - What could go wrong
8. **Not In Scope** - What to explicitly exclude

**Important:** Pay attention to "Not In Scope" sections. Don't build features that are listed for future versions. And if any of the sections above are not included, develop the feature based on what is known and reasonable assumptions.

## When Implementing

### Starting a Feature

1. Read the feature file completely
2. Check DATABASE.md for relevant tables
3. Check SECURITY.md for security patterns
4. Check CONVENTIONS.md for naming
5. Create database tables first (if needed)
6. Build models/classes
7. Build admin interface
8. Build frontend interface
9. Add hooks for extensibility
10. Test manually

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

## Testing Expectations

After each phase, manually test:
- Does the feature work as specified?
- Does it work on mobile?
- Can you navigate with keyboard only?
- Are all strings translatable?
- Is the code properly escaped and sanitized?
- Do capability checks work?

## Questions to Ask Yourself

Before marking any feature complete:

1. Would this code pass WordPress.org plugin review?
2. Can a screen reader user complete this action?
3. Are all user inputs validated and sanitized?
4. Are correct answers ever visible in page source?
5. Is this translatable to other languages?
6. Is the UI consistent with other parts of the plugin?

## Common Mistakes to Avoid

1. **Don't build premium features** - If it's listed for Educator/School/Enterprise tier, don't include it in v1.0 Free
2. **Don't skip security** - Every input needs sanitization, every output needs escaping
3. **Don't hardcode strings** - Everything user-facing needs `__()`
4. **Don't forget mobile** - Test at 375px width
5. **Don't ignore edge cases** - What if there are 0 questions? What if time runs out?
6. **Don't break accessibility** - Tab order, focus states, ARIA labels
7. **Don't expose answers** - Server validates everything; client knows nothing about correctness until after submission

## Updating Documentation

If you discover something that should be documented:
- Note it at the end of the relevant file
- Mark it clearly as "ADDED DURING DEVELOPMENT"
- Include the date

This helps maintain documentation as a living resource.

