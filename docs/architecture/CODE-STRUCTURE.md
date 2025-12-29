# Code Structure

## Plugin Organization

```
pressprimer-quiz/
├── pressprimer-quiz.php          # Main plugin file (bootstrap)
├── uninstall.php                 # Cleanup on uninstall
├── readme.txt                    # WordPress.org readme
├── LICENSE.txt                   # GPL v2+
│
├── includes/                     # PHP classes and functions
│   ├── class-ppq-plugin.php      # Main plugin class
│   ├── class-ppq-activator.php   # Activation hooks
│   ├── class-ppq-deactivator.php # Deactivation hooks
│   ├── class-ppq-addon-manager.php # v2.0 - Addon registration (NEW)
│   │
│   ├── models/                   # Data models
│   ├── admin/                    # Admin-only classes
│   ├── frontend/                 # Frontend rendering
│   ├── services/                 # Business logic services
│   ├── integrations/             # LMS and third-party
│   ├── database/                 # Schema and migrations
│   └── utilities/                # Helper classes
│
├── assets/                       # Static assets
│   ├── css/
│   ├── js/
│   └── images/
│
├── blocks/                       # Gutenberg blocks
│
├── templates/                    # PHP templates
│
├── languages/                    # Translation files
│
└── tests/                        # Test files
```

## v2.0 New Files

The following files are added in v2.0:

### Addon System
```
includes/
├── class-ppq-addon-manager.php   # Addon registration and feature detection
```

### LearnPress Integration
```
includes/integrations/
├── class-ppq-learnpress.php      # LearnPress LMS integration
```

### Access Control
```
includes/services/
├── class-ppq-access-controller.php # Login requirement handling
```

### Condensed Mode
```
assets/css/
├── frontend-condensed.css        # Condensed display density styles
```

---

## Main Plugin File

`pressprimer-quiz.php` is the entry point:

```php
<?php
/**
 * Plugin Name:       PressPrimer Quiz
 * Plugin URI:        https://pressprimer.com/quiz
 * Description:       Enterprise-grade quiz and assessment platform for WordPress educators.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            PressPrimer
 * Author URI:        https://pressprimer.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pressprimer-quiz
 * Domain Path:       /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'PPQ_VERSION', '2.0.0' );
define( 'PPQ_PLUGIN_FILE', __FILE__ );
define( 'PPQ_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PPQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PPQ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PPQ_DB_VERSION', '2.0.0' );

// Autoloader
require_once PPQ_PLUGIN_PATH . 'includes/class-ppq-autoloader.php';
PressPrimer_Quiz_Autoloader::register();

// Activation/Deactivation hooks
register_activation_hook( __FILE__, [ 'PressPrimer_Quiz_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'PressPrimer_Quiz_Deactivator', 'deactivate' ] );

// Initialize plugin
function pressprimer_quiz_init() {
    // Load text domain
    load_plugin_textdomain(
        'pressprimer-quiz',
        false,
        dirname( PPQ_PLUGIN_BASENAME ) . '/languages'
    );

    // Initialize main plugin class
    $plugin = PressPrimer_Quiz_Plugin::get_instance();
    $plugin->run();
}
add_action( 'plugins_loaded', 'pressprimer_quiz_init' );
```

## Autoloader

```php
<?php
/**
 * Autoloader
 *
 * @package PressPrimer_Quiz
 */

class PressPrimer_Quiz_Autoloader {
    
    /**
     * Class to file mapping for subdirectories
     */
    private static $directories = [
        'models',
        'admin',
        'frontend',
        'services',
        'integrations',
        'database',
        'utilities',
    ];
    
    /**
     * Register the autoloader
     */
    public static function register(): void {
        spl_autoload_register( [ __CLASS__, 'autoload' ] );
    }
    
    /**
     * Autoload a class
     */
    public static function autoload( string $class ): void {
        // Handle both old PPQ_ prefix and new PressPrimer_Quiz_ prefix
        if ( strpos( $class, 'PressPrimer_Quiz_' ) === 0 ) {
            // New prefix: PressPrimer_Quiz_Question -> class-ppq-question.php
            $suffix = substr( $class, strlen( 'PressPrimer_Quiz_' ) );
            $file = 'class-ppq-' . strtolower( str_replace( '_', '-', $suffix ) ) . '.php';
        } elseif ( strpos( $class, 'PPQ_' ) === 0 ) {
            // Legacy: PPQ_Question -> class-ppq-question.php
            $file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
        } else {
            return;
        }
        
        // Check in includes root
        $path = PPQ_PLUGIN_PATH . 'includes/' . $file;
        if ( file_exists( $path ) ) {
            require_once $path;
            return;
        }
        
        // Check in subdirectories
        foreach ( self::$directories as $dir ) {
            $path = PPQ_PLUGIN_PATH . 'includes/' . $dir . '/' . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
                return;
            }
        }
    }
}
```

## Main Plugin Class

```php
<?php
/**
 * Main plugin class
 *
 * @package PressPrimer_Quiz
 */

class PressPrimer_Quiz_Plugin {
    
    /**
     * Singleton instance
     */
    private static ?PressPrimer_Quiz_Plugin $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance(): PressPrimer_Quiz_Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     */
    private function __construct() {
        // Constructor is private for singleton
    }
    
    /**
     * Run the plugin
     */
    public function run(): void {
        // Check and run migrations
        PressPrimer_Quiz_Migrator::maybe_migrate();
        
        // Initialize addon manager (v2.0)
        $this->init_addon_manager();
        
        // Initialize components
        $this->init_admin();
        $this->init_frontend();
        $this->init_integrations();
        $this->init_rest_api();
        $this->init_blocks();
        
        // Signal that PPQ is fully loaded (v2.0 - for addons)
        do_action( 'pressprimer_quiz_loaded' );
        
        // After addons register, signal completion (v2.0)
        do_action( 'pressprimer_quiz_addons_loaded' );
    }
    
    /**
     * Initialize addon manager (v2.0)
     */
    private function init_addon_manager(): void {
        $addon_manager = new PressPrimer_Quiz_Addon_Manager();
        $addon_manager->init();
    }
    
    /**
     * Initialize admin components
     */
    private function init_admin(): void {
        if ( ! is_admin() ) {
            return;
        }
        
        $admin = new PressPrimer_Quiz_Admin();
        $admin->init();
    }
    
    /**
     * Initialize frontend components
     */
    private function init_frontend(): void {
        $frontend = new PressPrimer_Quiz_Frontend();
        $frontend->init();
        
        $shortcodes = new PressPrimer_Quiz_Shortcodes();
        $shortcodes->init();
    }
    
    /**
     * Initialize LMS integrations
     */
    private function init_integrations(): void {
        // LearnDash
        if ( defined( 'LEARNDASH_VERSION' ) ) {
            $learndash = new PressPrimer_Quiz_LearnDash();
            $learndash->init();
        }
        
        // TutorLMS
        if ( defined( 'TUTOR_VERSION' ) ) {
            $tutor = new PressPrimer_Quiz_TutorLMS();
            $tutor->init();
        }
        
        // LifterLMS
        if ( defined( 'LLMS_PLUGIN_FILE' ) ) {
            $lifter = new PressPrimer_Quiz_LifterLMS();
            $lifter->init();
        }
        
        // LearnPress (v2.0)
        if ( defined( 'LEARNPRESS_VERSION' ) && version_compare( LEARNPRESS_VERSION, '4.0.0', '>=' ) ) {
            $learnpress = new PressPrimer_Quiz_LearnPress();
            $learnpress->init();
        }
        
        // Uncanny Automator
        if ( class_exists( 'Uncanny_Automator\\Automator_Functions' ) ) {
            $automator = new PressPrimer_Quiz_Automator();
            $automator->init();
        }
    }
    
    /**
     * Initialize REST API
     */
    private function init_rest_api(): void {
        add_action( 'rest_api_init', function() {
            $controller = new PressPrimer_Quiz_REST_Controller();
            $controller->register_routes();
        } );
    }
    
    /**
     * Initialize Gutenberg blocks
     */
    private function init_blocks(): void {
        add_action( 'init', function() {
            // Quiz block
            register_block_type( PPQ_PLUGIN_PATH . 'blocks/quiz' );
            
            // My Attempts block
            register_block_type( PPQ_PLUGIN_PATH . 'blocks/my-attempts' );
            
            // Assigned Quizzes block (premium feature but block registered in free)
            register_block_type( PPQ_PLUGIN_PATH . 'blocks/assigned-quizzes' );
        } );
    }
}
```

## Addon Manager (v2.0)

```php
<?php
/**
 * Addon Manager
 *
 * Handles registration and detection of premium addons.
 *
 * @package PressPrimer_Quiz
 * @since 2.0.0
 */

class PressPrimer_Quiz_Addon_Manager {
    
    /**
     * Registered addons
     *
     * @var array
     */
    private static array $addons = [];
    
    /**
     * Registered features
     *
     * @var array
     */
    private static array $features = [];
    
    /**
     * Initialize the addon manager
     */
    public function init(): void {
        // Listen for addon registrations
        add_action( 'pressprimer_quiz_register_addon', array( $this, 'register_addon' ), 10, 3 );
    }
    
    /**
     * Register an addon
     *
     * @param string $addon_id   Addon identifier.
     * @param string $version    Addon version.
     * @param array  $features   Features provided by this addon.
     */
    public function register_addon( string $addon_id, string $version, array $features ): void {
        self::$addons[ $addon_id ] = [
            'version' => $version,
            'features' => $features,
        ];
        
        foreach ( $features as $feature ) {
            self::$features[ $feature ] = $addon_id;
        }
    }
    
    /**
     * Check if an addon is active
     *
     * @param string $addon_id Addon identifier.
     * @return bool
     */
    public static function has_addon( string $addon_id ): bool {
        return isset( self::$addons[ $addon_id ] );
    }
    
    /**
     * Check if a feature is enabled
     *
     * @param string $feature Feature slug.
     * @return bool
     */
    public static function feature_enabled( string $feature ): bool {
        return isset( self::$features[ $feature ] );
    }
    
    /**
     * Get addon providing a feature
     *
     * @param string $feature Feature slug.
     * @return string|null
     */
    public static function get_feature_addon( string $feature ): ?string {
        return self::$features[ $feature ] ?? null;
    }
    
    /**
     * Get all registered addons
     *
     * @return array
     */
    public static function get_addons(): array {
        return self::$addons;
    }
}

/**
 * Check if an addon is active (global function)
 *
 * @param string $addon_id Addon identifier.
 * @return bool
 */
function pressprimer_quiz_has_addon( string $addon_id ): bool {
    return PressPrimer_Quiz_Addon_Manager::has_addon( $addon_id );
}

/**
 * Check if a feature is enabled (global function)
 *
 * @param string $feature Feature slug.
 * @return bool
 */
function pressprimer_quiz_feature_enabled( string $feature ): bool {
    return PressPrimer_Quiz_Addon_Manager::feature_enabled( $feature );
}
```

## Access Controller (v2.0)

```php
<?php
/**
 * Access Controller
 *
 * Handles quiz access control and login requirements.
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 * @since 2.0.0
 */

class PressPrimer_Quiz_Access_Controller {
    
    /**
     * Get effective access mode for a quiz
     *
     * @param int $quiz_id Quiz ID.
     * @return string Access mode (guest_optional, guest_required, login_required).
     */
    public static function get_access_mode( int $quiz_id ): string {
        $quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
        
        if ( ! $quiz ) {
            return 'guest_optional';
        }
        
        // Per-quiz override
        if ( $quiz->access_mode && 'default' !== $quiz->access_mode ) {
            return apply_filters( 'pressprimer_quiz_access_mode', $quiz->access_mode, $quiz_id );
        }
        
        // Global default
        $settings = get_option( 'ppq_settings', [] );
        $default = $settings['default_access_mode'] ?? 'guest_optional';
        
        return apply_filters( 'pressprimer_quiz_access_mode', $default, $quiz_id );
    }
    
    /**
     * Check if user can access a quiz
     *
     * @param int      $quiz_id Quiz ID.
     * @param int|null $user_id User ID (null for current user).
     * @return bool
     */
    public static function can_access( int $quiz_id, ?int $user_id = null ): bool {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }
        
        $mode = self::get_access_mode( $quiz_id );
        
        if ( 'login_required' === $mode && ! $user_id ) {
            return false;
        }
        
        return apply_filters( 'pressprimer_quiz_user_can_take_quiz', true, $quiz_id, $user_id );
    }
    
    /**
     * Get login URL for quiz
     *
     * @param int $quiz_id Quiz ID.
     * @return string
     */
    public static function get_login_url( int $quiz_id ): string {
        $quiz_url = pressprimer_quiz_get_quiz_url( $quiz_id );
        $login_url = wp_login_url( $quiz_url );
        
        return apply_filters( 'pressprimer_quiz_login_url', $login_url, $quiz_id );
    }
    
    /**
     * Get login message for quiz
     *
     * @param int $quiz_id Quiz ID.
     * @return string
     */
    public static function get_login_message( int $quiz_id ): string {
        $quiz = PressPrimer_Quiz_Quiz::get( $quiz_id );
        
        // Per-quiz message
        if ( $quiz && ! empty( $quiz->login_message ) ) {
            return $quiz->login_message;
        }
        
        // Global default
        $settings = get_option( 'ppq_settings', [] );
        return $settings['login_message_default'] ?? __( 'Please log in to take this quiz.', 'pressprimer-quiz' );
    }
}
```

## LearnPress Integration (v2.0)

```php
<?php
/**
 * LearnPress Integration
 *
 * @package PressPrimer_Quiz
 * @subpackage Integrations
 * @since 2.0.0
 */

class PressPrimer_Quiz_LearnPress {
    
    /**
     * Initialize the integration
     */
    public function init(): void {
        // Add meta box to lesson edit screen
        add_action( 'add_meta_boxes', array( $this, 'add_lesson_meta_box' ) );
        add_action( 'save_post_lp_lesson', array( $this, 'save_lesson_meta' ) );
        
        // Render quiz in lesson content
        add_filter( 'learn-press/lesson/content', array( $this, 'render_quiz_in_lesson' ), 20, 2 );
        
        // Handle quiz completion
        add_action( 'pressprimer_quiz_quiz_passed', array( $this, 'handle_quiz_passed' ), 10, 4 );
    }
    
    /**
     * Add meta box to lesson edit screen
     */
    public function add_lesson_meta_box(): void {
        add_meta_box(
            'ppq-learnpress-quiz',
            __( 'PressPrimer Quiz', 'pressprimer-quiz' ),
            array( $this, 'render_meta_box' ),
            'lp_lesson',
            'side',
            'default'
        );
    }
    
    /**
     * Render the meta box
     *
     * @param WP_Post $post Post object.
     */
    public function render_meta_box( WP_Post $post ): void {
        wp_nonce_field( 'ppq_learnpress_lesson', 'ppq_learnpress_nonce' );
        
        $quiz_id = get_post_meta( $post->ID, '_ppq_quiz_id', true );
        $require_pass = get_post_meta( $post->ID, '_ppq_require_pass', true );
        
        // Get all published quizzes
        $quizzes = PressPrimer_Quiz_Quiz::get_all( [ 'status' => 'published' ] );
        
        ?>
        <p>
            <label for="ppq_quiz_id"><?php esc_html_e( 'Select Quiz:', 'pressprimer-quiz' ); ?></label>
            <select name="ppq_quiz_id" id="ppq_quiz_id" class="widefat">
                <option value=""><?php esc_html_e( '— None —', 'pressprimer-quiz' ); ?></option>
                <?php foreach ( $quizzes as $quiz ) : ?>
                    <option value="<?php echo esc_attr( $quiz->id ); ?>" <?php selected( $quiz_id, $quiz->id ); ?>>
                        <?php echo esc_html( $quiz->title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label>
                <input type="checkbox" name="ppq_require_pass" value="1" <?php checked( $require_pass, '1' ); ?>>
                <?php esc_html_e( 'Require quiz pass to complete lesson', 'pressprimer-quiz' ); ?>
            </label>
        </p>
        <?php
    }
    
    /**
     * Save lesson meta
     *
     * @param int $post_id Post ID.
     */
    public function save_lesson_meta( int $post_id ): void {
        if ( ! isset( $_POST['ppq_learnpress_nonce'] ) ) {
            return;
        }
        
        if ( ! wp_verify_nonce( $_POST['ppq_learnpress_nonce'], 'ppq_learnpress_lesson' ) ) {
            return;
        }
        
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        $quiz_id = isset( $_POST['ppq_quiz_id'] ) ? absint( $_POST['ppq_quiz_id'] ) : 0;
        $require_pass = isset( $_POST['ppq_require_pass'] ) ? '1' : '0';
        
        update_post_meta( $post_id, '_ppq_quiz_id', $quiz_id );
        update_post_meta( $post_id, '_ppq_require_pass', $require_pass );
    }
    
    /**
     * Render quiz in lesson content
     *
     * @param string $content Lesson content.
     * @param object $lesson  Lesson object.
     * @return string
     */
    public function render_quiz_in_lesson( string $content, $lesson ): string {
        $quiz_id = get_post_meta( $lesson->get_id(), '_ppq_quiz_id', true );
        
        if ( ! $quiz_id ) {
            return $content;
        }
        
        // Check if user is enrolled in course
        $course_id = $lesson->get_course_id();
        $user_id = get_current_user_id();
        
        if ( $course_id && $user_id ) {
            $user = learn_press_get_user( $user_id );
            if ( ! $user->has_enrolled_course( $course_id ) ) {
                $content .= '<div class="ppq-learnpress-enrollment-required">';
                $content .= esc_html__( 'You must be enrolled in this course to take the quiz.', 'pressprimer-quiz' );
                $content .= '</div>';
                return $content;
            }
        }
        
        // Render the quiz
        $content .= PressPrimer_Quiz_Shortcodes::quiz_shortcode( [ 'id' => $quiz_id ] );
        
        return $content;
    }
    
    /**
     * Handle quiz passed
     *
     * @param int   $quiz_id       Quiz ID.
     * @param int   $user_id       User ID.
     * @param float $score_percent Score percentage.
     * @param int   $attempt_id    Attempt ID.
     */
    public function handle_quiz_passed( int $quiz_id, int $user_id, float $score_percent, int $attempt_id ): void {
        global $wpdb;
        
        // Find lessons that use this quiz
        $lessons = $wpdb->get_results( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_ppq_quiz_id' AND meta_value = %d",
            $quiz_id
        ) );
        
        foreach ( $lessons as $lesson_row ) {
            $lesson_id = $lesson_row->post_id;
            $require_pass = get_post_meta( $lesson_id, '_ppq_require_pass', true );
            
            if ( '1' === $require_pass ) {
                /**
                 * Filter whether to complete the LearnPress lesson
                 *
                 * @since 2.0.0
                 *
                 * @param bool $should_complete Whether to mark lesson complete.
                 * @param int  $lesson_id       Lesson ID.
                 * @param int  $attempt_id      Quiz attempt ID.
                 */
                $should_complete = apply_filters( 'pressprimer_quiz_learnpress_should_complete_lesson', true, $lesson_id, $attempt_id );
                
                if ( $should_complete ) {
                    $this->complete_lesson( $lesson_id, $user_id );
                }
                
                /**
                 * Fires when a PressPrimer quiz linked to LearnPress is completed
                 *
                 * @since 2.0.0
                 *
                 * @param int  $lesson_id  Lesson ID.
                 * @param int  $quiz_id    Quiz ID.
                 * @param int  $user_id    User ID.
                 * @param bool $passed     Whether user passed.
                 */
                do_action( 'pressprimer_quiz_learnpress_quiz_completed', $lesson_id, $quiz_id, $user_id, true );
            }
        }
    }
    
    /**
     * Mark LearnPress lesson as complete
     *
     * @param int $lesson_id Lesson ID.
     * @param int $user_id   User ID.
     */
    private function complete_lesson( int $lesson_id, int $user_id ): void {
        if ( ! function_exists( 'learn_press_get_user' ) ) {
            return;
        }
        
        $user = learn_press_get_user( $user_id );
        $lesson = learn_press_get_lesson( $lesson_id );
        
        if ( $user && $lesson ) {
            // Use LearnPress API to complete lesson
            $user->complete_lesson( $lesson_id );
        }
    }
}
```

---

## Directory Structure by Component

### Models (`includes/models/`)

```
models/
├── class-ppq-question.php           # Question model
├── class-ppq-question-revision.php  # Question revision model
├── class-ppq-quiz.php               # Quiz model
├── class-ppq-attempt.php            # Attempt model
├── class-ppq-attempt-item.php       # Attempt item model
├── class-ppq-bank.php               # Question bank model
├── class-ppq-category.php           # Category model
├── class-ppq-group.php              # Group model (schema in Free, features in Educator)
├── class-ppq-assignment.php         # Assignment model (schema in Free, features in Educator)
└── class-ppq-event.php              # Event model
```

### Admin (`includes/admin/`)

```
admin/
├── class-ppq-admin.php              # Main admin class
├── class-ppq-admin-settings.php     # Settings page
├── class-ppq-admin-quiz-builder.php # Quiz builder
├── class-ppq-admin-questions.php    # Questions page
├── class-ppq-admin-banks.php        # Question banks page
├── class-ppq-admin-reports.php      # Reports page
├── class-ppq-admin-onboarding.php   # Onboarding wizard
└── class-ppq-admin-upsells.php      # v2.0 - Premium upsell UI (NEW)
```

### Frontend (`includes/frontend/`)

```
frontend/
├── class-ppq-frontend.php           # Main frontend class
├── class-ppq-shortcodes.php         # Shortcode handlers
├── class-ppq-quiz-renderer.php      # Quiz display logic
├── class-ppq-results-renderer.php   # Results display logic
├── class-ppq-guest-handler.php      # Guest email handling
└── class-ppq-access-controller.php  # v2.0 - Login requirement handling (NEW)
```

### Services (`includes/services/`)

```
services/
├── class-ppq-grader.php             # Grading/scoring logic
├── class-ppq-ai-generator.php       # AI question generation
├── class-ppq-quiz-generator.php     # Dynamic quiz assembly
├── class-ppq-email-service.php      # Email sending
├── class-ppq-export-service.php     # Data export (Educator addon)
├── class-ppq-import-service.php     # Data import (Educator addon)
└── class-ppq-cache-service.php      # v2.2 - Cache management (NEW)
```

### Integrations (`includes/integrations/`)

```
integrations/
├── class-ppq-learndash.php          # LearnDash integration
├── class-ppq-tutor.php              # TutorLMS integration
├── class-ppq-lifter.php             # LifterLMS integration
├── class-ppq-learnpress.php         # v2.0 - LearnPress integration (NEW)
└── class-ppq-automator.php          # Uncanny Automator integration
```

### Database (`includes/database/`)

```
database/
├── class-ppq-schema.php             # Table definitions
└── class-ppq-migrator.php           # Migration runner
```

### Utilities (`includes/utilities/`)

```
utilities/
├── class-ppq-helpers.php            # General helpers
├── class-ppq-rate-limiter.php       # Rate limiting
├── class-ppq-sanitizer.php          # Input sanitization
└── class-ppq-capabilities.php       # Role/capability management
```
