# Contributing to PressPrimer Quiz

## Coding Standards

This plugin follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) with additional rules required for WordPress.org plugin directory submission.

### Running Code Checks

```bash
# Run all PHPCS checks (includes heredoc check)
./vendor/bin/phpcs --standard=phpcs.xml.dist

# Run with full report
./vendor/bin/phpcs --standard=phpcs.xml.dist --report=full

# Auto-fix what can be fixed
./vendor/bin/phpcbf --standard=phpcs.xml.dist

# Check PHP compatibility (7.4+)
./vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4-8.4 --extensions=php includes/ pressprimer-quiz.php

# Lint JavaScript (checks for console statements)
npm run lint:js -- assets/js/*.js

# Lint CSS
npm run lint:css
```

### WordPress.org Plugin Directory Requirements

The following rules are enforced by the WordPress.org plugin scanner. Violations will cause automatic rejection.

#### PHP Requirements

1. **No Heredoc/Nowdoc Syntax**

   The `<<<` syntax is not allowed. Use string concatenation instead.

   ```php
   // WRONG - Will be rejected
   $script = <<<'JS'
   jQuery(document).ready(function($) {
       console.log('test');
   });
   JS;

   // CORRECT
   $script = 'jQuery(document).ready(function($) {' .
       'console.log("test");' .
   '});';
   ```

2. **Escape All Output**

   All output must be escaped using appropriate functions:
   - `esc_html()` for text content
   - `esc_attr()` for HTML attributes
   - `esc_url()` for URLs
   - `wp_kses()` or `wp_kses_post()` for HTML content
   - `wp_json_encode()` for JSON data

3. **Sanitize All Input**

   All user input must be sanitized:
   - `sanitize_text_field()` for text
   - `absint()` or `intval()` for integers
   - `sanitize_email()` for emails
   - `wp_kses()` for HTML content

4. **Verify Nonces**

   All form submissions and AJAX requests must verify nonces:
   ```php
   if ( ! wp_verify_nonce( $_POST['nonce'], 'action_name' ) ) {
       wp_die( 'Security check failed' );
   }
   ```

5. **Check Capabilities**

   Always verify user capabilities before performing actions:
   ```php
   if ( ! current_user_can( 'manage_options' ) ) {
       wp_die( 'Unauthorized' );
   }
   ```

6. **Use Prepared Statements**

   All database queries with user data must use `$wpdb->prepare()`:
   ```php
   $wpdb->get_results(
       $wpdb->prepare(
           "SELECT * FROM {$wpdb->prefix}table WHERE id = %d",
           $id
       )
   );
   ```

#### JavaScript Requirements

1. **No Console Statements in Production**

   Remove all `console.log()`, `console.error()`, `console.warn()`, and `console.debug()` statements before submission.

2. **No Debug Code**

   Remove any DEBUG flags, logging systems, or development-only code.

3. **Proper jQuery Usage**

   Use the jQuery wrapper pattern to avoid conflicts:
   ```javascript
   jQuery(document).ready(function($) {
       // Your code here using $
   });
   ```

#### File and Asset Requirements

1. **No External Resources**

   All CSS, JavaScript, and other assets must be included locally. Do not load resources from external CDNs.

2. **No Minified Files Without Source**

   If including minified files, the unminified source must also be included.

3. **Prefix Everything**

   All functions, classes, constants, and global variables must be prefixed:
   - Functions: `ppq_` or `pressprimer_quiz_`
   - Classes: `PPQ_` or `PressPrimer_Quiz_`
   - Constants: `PPQ_`
   - Hooks: `ppq/` or `pressprimer_quiz_`

### Pre-Submission Checklist

Before submitting to WordPress.org, run these checks:

```bash
# 1. PHPCS with project config (includes heredoc check)
./vendor/bin/phpcs --standard=phpcs.xml.dist --report=summary

# 2. Security-focused checks
./vendor/bin/phpcs --standard=WordPress-Extra \
    --sniffs=WordPress.Security.EscapeOutput,WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification \
    --report=summary includes/ pressprimer-quiz.php

# 3. PHP compatibility
./vendor/bin/phpcs --standard=PHPCompatibilityWP \
    --runtime-set testVersion 7.4-8.4 \
    --extensions=php --report=summary includes/ pressprimer-quiz.php

# 4. JavaScript linting (catches console statements, debugger, etc.)
npm run lint:js -- assets/js/*.js

# 5. Search for heredoc syntax (should return nothing)
grep -r "<<<" includes/
```

All checks must pass with zero errors before submission.

### Development Setup

1. Clone the repository
2. Install PHP dependencies:
   ```bash
   composer install
   ```
3. Install JavaScript dependencies:
   ```bash
   npm install
   ```
4. Build assets:
   ```bash
   npm run build
   ```

### Branch Naming

- `main` - Production-ready code
- `develop` - Development branch
- `feature/*` - New features
- `fix/*` - Bug fixes
- `release/*` - Release preparation

### Commit Messages

Use clear, descriptive commit messages:
- `Add feature description`
- `Fix bug description`
- `Update component for reason`
- `Remove deprecated functionality`
