# Feature: Require Login Setting

**Version:** 2.0  
**Plugin:** Free  
**Priority:** High  
**Status:** Planning

---

## Overview

Some quizzes should only be available to authenticated users. This feature adds a global setting for guest access control and per-quiz overrides, replacing the current behavior where guests can always take quizzes (with optional email capture).

## User Stories

1. As an administrator, I want to set a site-wide default for guest access so I don't have to configure each quiz individually.
2. As a quiz author, I want to override the global setting for specific quizzes that need different access rules.
3. As a guest visitor, I want to see a clear message explaining why I need to log in and an easy way to do so.

## Technical Specification

### Access Modes

| Mode | Description |
|------|-------------|
| `guest_optional` | Guests can take quiz; email capture is optional |
| `guest_required` | Guests can take quiz; email capture is required |
| `login_required` | Only logged-in users can take quiz |

### Global Setting

**Location:** PPQ Settings → General → "Guest Access"

```php
// In class-ppq-admin-settings.php

add_settings_field(
    'ppq_default_access_mode',
    __( 'Guest Access', 'pressprimer-quiz' ),
    array( $this, 'render_access_mode_field' ),
    'ppq_settings',
    'ppq_general_section'
);

public function render_access_mode_field() {
    $settings = get_option( 'ppq_settings', array() );
    $value    = isset( $settings['default_access_mode'] ) ? $settings['default_access_mode'] : 'guest_optional';
    
    ?>
    <fieldset>
        <label>
            <input type="radio" name="ppq_settings[default_access_mode]" 
                   value="guest_optional" <?php checked( $value, 'guest_optional' ); ?> />
            <?php esc_html_e( 'Allow guests (email optional)', 'pressprimer-quiz' ); ?>
        </label>
        <br>
        <label>
            <input type="radio" name="ppq_settings[default_access_mode]" 
                   value="guest_required" <?php checked( $value, 'guest_required' ); ?> />
            <?php esc_html_e( 'Allow guests (email required)', 'pressprimer-quiz' ); ?>
        </label>
        <br>
        <label>
            <input type="radio" name="ppq_settings[default_access_mode]" 
                   value="login_required" <?php checked( $value, 'login_required' ); ?> />
            <?php esc_html_e( 'Require login', 'pressprimer-quiz' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'Default access mode for new quizzes. Can be overridden per quiz.', 'pressprimer-quiz' ); ?>
        </p>
    </fieldset>
    <?php
}
```

**Login Message Setting:**

```php
add_settings_field(
    'ppq_login_message_default',
    __( 'Login Message', 'pressprimer-quiz' ),
    array( $this, 'render_login_message_field' ),
    'ppq_settings',
    'ppq_general_section'
);

public function render_login_message_field() {
    $settings = get_option( 'ppq_settings', array() );
    $value    = isset( $settings['login_message_default'] ) 
                ? $settings['login_message_default'] 
                : __( 'Please log in to take this quiz.', 'pressprimer-quiz' );
    
    ?>
    <textarea name="ppq_settings[login_message_default]" rows="2" class="large-text"><?php 
        echo esc_textarea( $value ); 
    ?></textarea>
    <p class="description">
        <?php esc_html_e( 'Message shown to guests when login is required.', 'pressprimer-quiz' ); ?>
    </p>
    <?php
}
```

### Per-Quiz Override

**Location:** Quiz Builder → Settings → "Access Mode"

Add to quiz settings in the React Quiz Builder component:

```jsx
// In quiz-editor settings panel

const accessModeOptions = [
    { value: 'default', label: __('Use global default', 'pressprimer-quiz') },
    { value: 'guest_optional', label: __('Allow guests (email optional)', 'pressprimer-quiz') },
    { value: 'guest_required', label: __('Allow guests (email required)', 'pressprimer-quiz') },
    { value: 'login_required', label: __('Require login', 'pressprimer-quiz') },
];

<SelectControl
    label={__('Access Mode', 'pressprimer-quiz')}
    value={quiz.access_mode || 'default'}
    options={accessModeOptions}
    onChange={(value) => updateQuiz({ access_mode: value })}
/>

{quiz.access_mode === 'login_required' && (
    <TextareaControl
        label={__('Custom Login Message', 'pressprimer-quiz')}
        value={quiz.login_message || ''}
        onChange={(value) => updateQuiz({ login_message: value })}
        placeholder={globalSettings.login_message_default}
        help={__('Leave empty to use the global default message.', 'pressprimer-quiz')}
    />
)}
```

### Database Changes

Add columns to `wp_ppq_quizzes`:

```sql
ALTER TABLE wp_ppq_quizzes 
    ADD COLUMN access_mode VARCHAR(20) DEFAULT 'default' AFTER theme_settings_json,
    ADD COLUMN login_message TEXT DEFAULT NULL AFTER access_mode;
```

Update Quiz model:

```php
// In class-ppq-quiz.php

/**
 * Get effective access mode for this quiz
 *
 * @return string Access mode (guest_optional, guest_required, login_required)
 */
public function get_effective_access_mode() {
    if ( $this->access_mode && $this->access_mode !== 'default' ) {
        return $this->access_mode;
    }
    
    $settings = get_option( 'ppq_settings', array() );
    return isset( $settings['default_access_mode'] ) 
           ? $settings['default_access_mode'] 
           : 'guest_optional';
}

/**
 * Get login message for this quiz
 *
 * @return string
 */
public function get_login_message() {
    if ( ! empty( $this->login_message ) ) {
        return $this->login_message;
    }
    
    $settings = get_option( 'ppq_settings', array() );
    return isset( $settings['login_message_default'] ) 
           ? $settings['login_message_default'] 
           : __( 'Please log in to take this quiz.', 'pressprimer-quiz' );
}

/**
 * Check if current user can access this quiz
 *
 * @return bool
 */
public function can_user_access() {
    $access_mode = $this->get_effective_access_mode();
    
    if ( $access_mode === 'login_required' ) {
        return is_user_logged_in();
    }
    
    return true;
}
```

### Frontend Changes

**Quiz Renderer Updates:**

```php
// In class-ppq-quiz-renderer.php

public static function render( $quiz_id, $atts = array() ) {
    $quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
    
    if ( ! $quiz ) {
        return self::render_error( __( 'Quiz not found.', 'pressprimer-quiz' ) );
    }
    
    // Check access
    if ( ! $quiz->can_user_access() ) {
        return self::render_login_required( $quiz );
    }
    
    // Continue with normal rendering...
}

/**
 * Render login required message
 *
 * @param PressPrimer_Quiz_Quiz $quiz Quiz object.
 * @return string
 */
private static function render_login_required( $quiz ) {
    $login_url = wp_login_url( get_permalink() );
    
    // Check for WooCommerce account page
    if ( function_exists( 'wc_get_page_id' ) ) {
        $account_page_id = wc_get_page_id( 'myaccount' );
        if ( $account_page_id > 0 ) {
            $login_url = add_query_arg( 'redirect_to', urlencode( get_permalink() ), get_permalink( $account_page_id ) );
        }
    }
    
    /**
     * Filter the login URL for quizzes requiring authentication
     *
     * @since 2.0.0
     *
     * @param string                  $login_url Login URL with redirect.
     * @param PressPrimer_Quiz_Quiz   $quiz      Quiz object.
     */
    $login_url = apply_filters( 'pressprimer_quiz_login_url', $login_url, $quiz );
    
    ob_start();
    ?>
    <div class="ppq-quiz ppq-login-required" data-quiz-id="<?php echo esc_attr( $quiz->id ); ?>">
        <?php if ( $quiz->featured_image_id ) : ?>
            <div class="ppq-quiz-featured-image">
                <?php echo wp_get_attachment_image( $quiz->featured_image_id, 'large' ); ?>
            </div>
        <?php endif; ?>
        
        <div class="ppq-quiz-header">
            <h2 class="ppq-quiz-title"><?php echo esc_html( $quiz->title ); ?></h2>
            <?php if ( $quiz->description ) : ?>
                <div class="ppq-quiz-description">
                    <?php echo wp_kses_post( $quiz->description ); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="ppq-login-message">
            <p><?php echo wp_kses_post( $quiz->get_login_message() ); ?></p>
            <a href="<?php echo esc_url( $login_url ); ?>" class="ppq-btn ppq-btn-primary">
                <?php esc_html_e( 'Log In', 'pressprimer-quiz' ); ?>
            </a>
            
            <?php if ( get_option( 'users_can_register' ) ) : ?>
                <?php 
                $register_url = wp_registration_url();
                
                // Check for WooCommerce
                if ( function_exists( 'wc_get_page_id' ) ) {
                    $account_page_id = wc_get_page_id( 'myaccount' );
                    if ( $account_page_id > 0 ) {
                        $register_url = get_permalink( $account_page_id );
                    }
                }
                ?>
                <p class="ppq-register-link">
                    <?php 
                    printf(
                        /* translators: %s: registration URL */
                        esc_html__( 'Don\'t have an account? %s', 'pressprimer-quiz' ),
                        '<a href="' . esc_url( $register_url ) . '">' . esc_html__( 'Register', 'pressprimer-quiz' ) . '</a>'
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
```

**Guest Email Form Updates:**

```php
// Modify the existing guest email form based on access mode

private static function render_guest_email_form( $quiz ) {
    $access_mode = $quiz->get_effective_access_mode();
    
    if ( $access_mode === 'login_required' ) {
        return ''; // Should not reach here
    }
    
    $required = ( $access_mode === 'guest_required' );
    
    ob_start();
    ?>
    <div class="ppq-guest-email-form">
        <label for="ppq-guest-email" class="ppq-form-label">
            <?php 
            if ( $required ) {
                esc_html_e( 'Email Address (required)', 'pressprimer-quiz' );
            } else {
                esc_html_e( 'Email Address (optional)', 'pressprimer-quiz' );
            }
            ?>
        </label>
        <input type="email" 
               id="ppq-guest-email" 
               name="guest_email" 
               class="ppq-form-input"
               <?php echo $required ? 'required' : ''; ?>
               placeholder="<?php esc_attr_e( 'your@email.com', 'pressprimer-quiz' ); ?>" />
        <?php if ( ! $required ) : ?>
            <p class="ppq-form-help">
                <?php esc_html_e( 'Provide your email to save your progress and receive results.', 'pressprimer-quiz' ); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
```

### CSS Additions

```css
/* Login required state */
.ppq-login-required {
    text-align: center;
}

.ppq-login-required .ppq-quiz-header {
    margin-bottom: 1.5rem;
}

.ppq-login-message {
    background: var(--ppq-bg-secondary, #f5f5f5);
    padding: 2rem;
    border-radius: var(--ppq-border-radius, 8px);
}

.ppq-login-message p {
    margin-bottom: 1rem;
    font-size: 1.1rem;
}

.ppq-register-link {
    margin-top: 1rem;
    font-size: 0.9rem;
    color: var(--ppq-text-muted);
}
```

### JavaScript Updates

```javascript
// In quiz.js - Update start quiz handler

async function startQuiz() {
    const accessMode = quizData.access_mode;
    
    if (accessMode === 'guest_required' && !isLoggedIn) {
        const emailInput = document.getElementById('ppq-guest-email');
        const email = emailInput?.value?.trim();
        
        if (!email || !isValidEmail(email)) {
            showError(__('Please enter a valid email address.', 'pressprimer-quiz'));
            emailInput?.focus();
            return;
        }
    }
    
    // Continue with quiz start...
}
```

## Migration Considerations

Existing quizzes will have `access_mode = 'default'`, which falls back to the global setting. The global setting defaults to `guest_optional` to maintain backward compatibility.

## Testing Checklist

### Global Settings
- [ ] Default access mode setting appears in Settings → General
- [ ] Default login message setting appears in Settings → General
- [ ] Settings save and persist correctly
- [ ] Default value is 'guest_optional' for backward compatibility

### Per-Quiz Override
- [ ] Access mode selector appears in Quiz Builder → Settings
- [ ] "Use global default" option works correctly
- [ ] Custom login message field appears when 'login_required' selected
- [ ] Empty custom message falls back to global default
- [ ] Settings save with quiz

### Frontend - Login Required
- [ ] Guests see login message instead of quiz
- [ ] Quiz title and description still display
- [ ] Featured image still displays
- [ ] "Log In" button redirects to login with return URL
- [ ] After login, user returns to quiz page
- [ ] Register link appears if registration is enabled
- [ ] WooCommerce account page used if available

### Frontend - Guest with Required Email
- [ ] Email field shows "(required)" label
- [ ] HTML5 required attribute present
- [ ] Cannot start quiz without email
- [ ] Validation error shows for invalid email

### Frontend - Guest with Optional Email
- [ ] Email field shows "(optional)" label
- [ ] Can start quiz without email
- [ ] Can provide email if desired

### Edge Cases
- [ ] Logged-in users always see quiz regardless of setting
- [ ] Admin preview works regardless of setting
- [ ] Shortcode respects access settings
- [ ] Block respects access settings
- [ ] LMS integrations respect access settings

## Dependencies

- None (uses existing quiz renderer infrastructure)

## Files Changed

**Modified:**
- `includes/admin/class-ppq-admin-settings.php` — Add global settings
- `includes/admin/class-ppq-admin-quiz-builder.php` — Add per-quiz setting (PHP)
- `includes/models/class-ppq-quiz.php` — Add access mode methods
- `includes/frontend/class-ppq-quiz-renderer.php` — Add login required rendering
- `includes/database/class-ppq-schema.php` — Add columns (migration)
- `assets/css/quiz.css` — Add login required styles
- `assets/js/quiz.js` — Add email validation for required mode
- `src/quiz-editor/` — Add access mode UI (React)
