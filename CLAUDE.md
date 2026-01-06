# PressPrimer Quiz - Development Guide

## Project Overview

PressPrimer Quiz is an enterprise-grade quiz and assessment plugin for WordPress. It was approved by WordPress.org in January 2025 (v1.0.0).

## WordPress.org Coding Standards

These rules were established during the WordPress.org plugin review process. **All code must follow these standards.**

---

## SQL Security (CRITICAL)

### Use `%i` Placeholder for Field/Column Names

```php
// CORRECT
$query = $wpdb->prepare( "SELECT * FROM {$table} WHERE %i = %s", $field, $value );

// WRONG - Do not use esc_sql() for field names
$query = $wpdb->prepare( "SELECT * FROM {$table} WHERE " . esc_sql( $field ) . " = %s", $value );
```

### Never Interpolate Variables for ORDER Direction

```php
// CORRECT - Hardcode ASC/DESC in separate branches
$is_asc = 'ASC' === strtoupper( $args['order'] );
if ( $is_asc ) {
    $query = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY %i ASC", $field );
} else {
    $query = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY %i DESC", $field );
}

// WRONG - Do not interpolate $order_dir even if validated
$order_dir = in_array( $order, ['ASC', 'DESC'] ) ? $order : 'DESC';
$query = $wpdb->prepare( "SELECT * FROM {$table} ORDER BY %i {$order_dir}", $field );
```

### Always Validate Field Names Against a Whitelist

```php
$queryable_fields = static::get_queryable_fields();
if ( ! in_array( $field, $queryable_fields, true ) ) {
    $field = 'id'; // Default to safe field
}
```

### No String Manipulation on SQL

```php
// WRONG - str_replace on SQL is NEVER safe
$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s", $status );
$count_sql = str_replace( 'SELECT *', 'SELECT COUNT(*)', $sql ); // REJECTED

// CORRECT - Write separate queries
$count_sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status );
```

---

## Input Sanitization (CRITICAL)

### Sanitize Immediately When Receiving Input

```php
// CORRECT
$quiz_id = isset( $_POST['quiz_id'] ) ? absint( $_POST['quiz_id'] ) : 0;

// WRONG - Sanitizing later
$quiz_id = $_POST['quiz_id'];
// ... other code ...
$quiz_id = absint( $quiz_id );
```

### Sanitize Arrays Element by Element

```php
// CORRECT
$answers = isset( $_POST['answers'] )
    ? array_map( 'sanitize_text_field', wp_unslash( $_POST['answers'] ) )
    : [];

// For nested arrays with mixed types
$data_raw = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : [];
$data = [
    'name'  => isset( $data_raw['name'] ) ? sanitize_text_field( $data_raw['name'] ) : '',
    'count' => isset( $data_raw['count'] ) ? absint( $data_raw['count'] ) : 0,
    'email' => isset( $data_raw['email'] ) ? sanitize_email( $data_raw['email'] ) : '',
];
```

### json_decode() Is NOT Sanitization

```php
// BAD - json_decode doesn't sanitize
$data = json_decode( wp_unslash( $_POST['data'] ), true );
// Using $data directly - NOT SAFE

// GOOD - Sanitize after decoding
$raw = json_decode( wp_unslash( $_POST['data'] ), true );
if ( ! is_array( $raw ) ) {
    $raw = [];
}
$data = [
    'title'   => isset( $raw['title'] ) ? sanitize_text_field( $raw['title'] ) : '',
    'content' => isset( $raw['content'] ) ? wp_kses_post( $raw['content'] ) : '',
];
```

### Never Iterate Over Entire Superglobals

```php
// WRONG - Processing ALL parameters
foreach ( $_GET as $key => $value ) { ... }

// CORRECT - Only process expected parameters
$filter_quiz = isset( $_GET['filter_quiz'] ) ? absint( $_GET['filter_quiz'] ) : 0;
```

### File Uploads - Always Validate

```php
// WRONG - Passing $_FILES directly
$result = $processor->process_upload( $_FILES['file'] );  // REJECTED

// CORRECT - Validate first
if ( ! isset( $_FILES['file'] ) || $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
    wp_send_json_error( [ 'message' => 'Upload failed' ] );
}

$allowed_types = [ 'pdf' => 'application/pdf' ];
$file_type = wp_check_filetype( $_FILES['file']['name'], $allowed_types );

if ( ! $file_type['ext'] ) {
    wp_send_json_error( [ 'message' => 'Invalid file type' ] );
}
```

---

## Output Escaping (CRITICAL)

### Use `wp_kses()` for Complex HTML - phpcs:ignore Is NOT Acceptable

```php
// REJECTED - phpcs:ignore for escaping
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $this->render_question( $item, $index );  // REJECTED

// CORRECT - wp_kses with allowed tags
$allowed_html = wp_kses_allowed_html( 'post' );
$allowed_html['input'] = [
    'type'    => true,
    'name'    => true,
    'value'   => true,
    'checked' => true,
    'class'   => true,
    'id'      => true,
];
$allowed_html['label'] = [ 'for' => true, 'class' => true ];
echo wp_kses( $this->render_question( $item, $index ), $allowed_html );
```

### Never Use Inline Styles for Hiding Elements

```php
// WRONG - wp_kses strips inline styles
<div style="display: none;">...</div>

// CORRECT - Use CSS class
<div class="ppq-hidden">...</div>
// With CSS: .ppq-hidden { display: none !important; }
```

### wp_add_inline_style() Needs CSS Sanitization

```php
// WRONG - Unsanitized CSS
$css = $this->get_theme_css();
wp_add_inline_style( 'my-style', $css );  // REJECTED

// CORRECT - Build from validated values
$color = sanitize_hex_color( $settings['color'] ) ?: '#333333';
$size = absint( $settings['size'] ) ?: 16;
$css = sprintf( '.my-class { color: %s; font-size: %dpx; }', $color, $size );
wp_add_inline_style( 'my-style', $css );
```

### Escape Appropriately by Context

```php
esc_url( $url );           // URLs
esc_attr( $attribute );    // HTML attributes
esc_html( $text );         // Text content
wp_kses_post( $html );     // Post-like HTML content
wp_kses( $html, $allowed ); // Controlled HTML
esc_textarea( $content );  // Textarea content
wp_json_encode( $data );   // JSON in script tags
```

---

## Prefixing (CRITICAL)

### 4+ Character Prefix Required

WordPress.org requires **minimum 4 characters** for global namespace identifiers.

```php
// CORRECT - 4+ character prefix
define( 'PRESSPRIMER_QUIZ_VERSION', '1.0.0' );
function pressprimer_quiz_init() {}
set_transient( 'pressprimer_quiz_cache', $data );
wp_localize_script( 'ppq-quiz', 'pressprimer_quiz_data', $data );

// WRONG - 3 character prefix (REJECTED)
define( 'PPQ_VERSION', '1.0.0' );
function ppq_init() {}
set_transient( 'ppq_cache', $data );
wp_localize_script( 'ppq-quiz', 'ppqData', $data );
```

### Items That Need 4+ Char Prefix

- All `define()` constants
- All global functions
- All classes (class names)
- All hook names (do_action, apply_filters)
- All AJAX action names
- All option names
- **All transient names** (commonly missed!)
- All user/post meta keys
- All shortcode names
- All menu slugs
- **All wp_localize_script object names** (commonly missed!)
- All registered scripts/styles handles

---

## Prohibited Code Patterns

### Never Use These

```php
eval()           // Security risk - REJECTED
create_function() // Deprecated - REJECTED
extract()        // Security risk - REJECTED
goto             // REJECTED
```

### Heredoc/Nowdoc Syntax Is Prohibited

```php
// WRONG - Heredoc not allowed
$html = <<<HTML
<div class="my-class">Content</div>
HTML;

// CORRECT - Use string concatenation or sprintf
$html = '<div class="' . esc_attr( $class ) . '">' . esc_html( $content ) . '</div>';
```

### No Inline Script/Style Tags in PHP

```php
// WRONG - Inline tags rejected
?>
<script>var data = <?php echo wp_json_encode( $data ); ?>;</script>
<?php

// CORRECT - Use WordPress functions
wp_localize_script( 'my-script', 'myData', $data );
wp_add_inline_script( 'my-script', 'console.log("loaded");' );
wp_add_inline_style( 'my-style', '.class { color: red; }' );
```

---

## Required in Distribution

### External Services Disclosure

If plugin connects to external services (OpenAI, etc.), readme.txt MUST include:

```
== External Services ==

This plugin connects to [Service Name] for [purpose].
- When: [When data is sent]
- What data: [What is transmitted]
- Terms of Service: [URL]
- Privacy Policy: [URL]
```

### Minified/Compiled Assets

For any `.min.js`, `.min.css`, or build output:
- Include source files in plugin, OR
- Link to public repository in readme.txt
- Document build instructions

### Files That Must NOT Be in Release ZIP

- `.git`, `.gitignore`, `.gitattributes`
- `node_modules`, `package-lock.json`
- `.wordpress-org` folder
- Test directories (`tests/`, `spec/`)
- Config files (`phpunit.xml`, `phpcs.xml.dist`, `webpack.config.js`)
- IDE folders (`.idea`, `.vscode`)
- `.env` files
- `.dist`, `.bak`, `.sample` files

---

## File Structure

```
pressprimer-quiz/
├── assets/
│   ├── css/           # Stylesheets
│   ├── js/            # JavaScript files
│   └── images/        # Images
├── build/             # Compiled React/JS (generated)
├── blocks/            # Gutenberg blocks
├── includes/
│   ├── admin/         # Admin classes
│   ├── api/           # REST API
│   ├── blocks/        # Block registration
│   ├── database/      # Schema and migrations
│   ├── frontend/      # Frontend rendering
│   ├── integrations/  # LMS integrations
│   ├── models/        # Data models
│   ├── services/      # Business logic
│   └── utilities/     # Helper classes
├── languages/         # Translation files
├── src/               # React source files
├── vendor/            # Composer dependencies
└── .wordpress-org/    # WordPress.org assets (banners, icons, screenshots)
```

## Database

- Tables use prefix: `{wp_prefix}ppq_`
- Schema defined in: `includes/database/class-ppq-schema.php`
- Migrations in: `includes/database/class-ppq-migrator.php`
- Current DB version: Check `PRESSPRIMER_QUIZ_DB_VERSION` constant

---

## Running Code Quality Checks

```bash
# PHP Syntax check
"/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php" -l path/to/file.php

# PHPCS (WordPress coding standards)
"/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php" ./vendor/bin/phpcs --standard=phpcs.xml.dist --report=full path/to/file.php

# Security-specific checks
"/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php" ./vendor/bin/phpcs --standard=WordPress-Extra --sniffs=WordPress.Security.EscapeOutput,WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification,WordPress.DB.PreparedSQL --report=full path/to/file.php

# PHP Compatibility (7.4 - 8.4)
"/Applications/Local.app/Contents/Resources/extraResources/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php" ./vendor/bin/phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4-8.4 --extensions=php path/to/file.php
```

---

## Building and Releasing

### Build Plugin ZIP
```bash
npm run plugin-zip
```
This creates `dist/pressprimer-quiz.zip`

### Release Process
1. Update version in `pressprimer-quiz.php` and `readme.txt`
2. Commit to `main`
3. Create tag: `git tag v1.0.1 && git push origin v1.0.1`
4. Create GitHub Release from the tag
5. Workflow automatically deploys to WordPress.org

### GitHub Actions Workflow
- Location: `.github/workflows/deploy-to-wordpress-org.yml`
- Triggers on: GitHub Release publish OR manual workflow_dispatch
- Deploys to: WordPress.org SVN (`/trunk/` and `/tags/{version}`)

---

## Branching Strategy

- `main` - Release branch, tagged versions, deploys to WordPress.org
- `develop` - Default branch, receives merges from feature branches
- `release/X.X.X` - Release preparation branches (e.g., `release/2.0.0`)
- `feature/*` - Feature branches (merge into release branch)
- `fix/*` - Bug fix branches (merge into main, then main into develop)

---

## Pre-Release Checklist

Before creating a release ZIP:

1. **Prefixes** - Search for `ppq_` in transients, wp_localize_script object names, options
2. **SQL** - No variable interpolation in ORDER BY, use `%i` for field names
3. **Escaping** - No `phpcs:ignore` for EscapeOutput, use `wp_kses()` instead
4. **Inline code** - No `<script>` or `<style>` tags in PHP
5. **External services** - All disclosed in readme.txt
6. **Prohibited files** - No `.git`, `node_modules`, test files in ZIP
7. **Heredoc** - None used anywhere
8. **Array sanitization** - All $_POST arrays sanitized element by element

---

## Important Reminders

1. **Always run PHPCS before committing** - Pre-commit hook does this automatically
2. **Test with WP_DEBUG enabled** - Catches notices and warnings
3. **Sanitize early, escape late** - Core WordPress security principle
4. **No variable interpolation in SQL** - Use placeholders exclusively
5. **4+ character prefixes** - For all global identifiers
6. **Use CSS classes instead of inline styles** - For elements that need wp_kses
7. **phpcs:ignore for escaping = REJECTION** - Always use wp_kses() instead
