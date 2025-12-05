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

## Main Plugin File

`pressprimer-quiz.php` is the entry point:

```php
<?php
/**
 * Plugin Name:       PressPrimer Quiz
 * Plugin URI:        https://pressprimer.com/quiz
 * Description:       Enterprise-grade quiz and assessment platform for WordPress educators.
 * Version:           1.0.0
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
define( 'PPQ_VERSION', '1.0.0' );
define( 'PPQ_PLUGIN_FILE', __FILE__ );
define( 'PPQ_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PPQ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PPQ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PPQ_DB_VERSION', '1.0.0' );

// Autoloader
require_once PPQ_PLUGIN_PATH . 'includes/class-ppq-autoloader.php';
PressPrimer_Quiz_Autoloader::register();

// Activation/Deactivation hooks
register_activation_hook( __FILE__, [ 'PPQ_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'PPQ_Deactivator', 'deactivate' ] );

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
        // Only handle our classes
        if ( strpos( $class, 'PPQ_' ) !== 0 ) {
            return;
        }
        
        // Convert class name to file name
        // PPQ_Question -> class-ppq-question.php
        $file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
        
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
    private static ?PPQ_Plugin $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance(): PPQ_Plugin {
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
        
        // Initialize components
        $this->init_admin();
        $this->init_frontend();
        $this->init_integrations();
        $this->init_rest_api();
        $this->init_blocks();
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
            
            // Assigned Quizzes block
            register_block_type( PPQ_PLUGIN_PATH . 'blocks/assigned-quizzes' );
        } );
    }
}
```

## Model Classes

Models represent database entities with CRUD operations:

```php
<?php
/**
 * Question model
 *
 * @package PressPrimer_Quiz
 * @subpackage Models
 */

class PressPrimer_Quiz_Question {
    
    public int $id = 0;
    public string $uuid = '';
    public int $author_id = 0;
    public string $type = 'mc';
    public ?int $expected_seconds = null;
    public ?string $difficulty_author = null;
    public float $max_points = 1.0;
    public string $status = 'published';
    public ?int $current_revision_id = null;
    public string $created_at = '';
    public string $updated_at = '';
    public ?string $deleted_at = null;
    
    // Related data (lazy loaded)
    private ?PPQ_Question_Revision $current_revision = null;
    private ?array $categories = null;
    private ?array $tags = null;
    
    /**
     * Create from database row
     */
    public static function from_row( object $row ): self {
        $question = new self();
        
        foreach ( get_object_vars( $row ) as $key => $value ) {
            if ( property_exists( $question, $key ) ) {
                $question->$key = $value;
            }
        }
        
        return $question;
    }
    
    /**
     * Get question by ID
     */
    public static function get( int $id ): ?self {
        global $wpdb;
        
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppq_questions WHERE id = %d AND deleted_at IS NULL",
            $id
        ) );
        
        return $row ? self::from_row( $row ) : null;
    }
    
    /**
     * Get question by UUID
     */
    public static function get_by_uuid( string $uuid ): ?self {
        global $wpdb;
        
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppq_questions WHERE uuid = %s AND deleted_at IS NULL",
            $uuid
        ) );
        
        return $row ? self::from_row( $row ) : null;
    }
    
    /**
     * Create new question
     *
     * @param array $data Question data.
     * @return int|WP_Error Question ID on success, WP_Error on failure.
     */
    public static function create( array $data ) {
        global $wpdb;
        
        // Validate
        if ( empty( $data['stem'] ) ) {
            return new WP_Error( 'ppq_missing_stem', __( 'Question text is required.', 'pressprimer-quiz' ) );
        }
        
        if ( empty( $data['answers'] ) || count( $data['answers'] ) < 2 ) {
            return new WP_Error( 'ppq_missing_answers', __( 'At least two answer options required.', 'pressprimer-quiz' ) );
        }
        
        // Create question record
        $result = $wpdb->insert(
            $wpdb->prefix . 'ppq_questions',
            [
                'uuid' => wp_generate_uuid4(),
                'author_id' => $data['author_id'] ?? get_current_user_id(),
                'type' => $data['type'] ?? 'mc',
                'expected_seconds' => $data['expected_seconds'] ?? null,
                'difficulty_author' => $data['difficulty_author'] ?? null,
                'max_points' => $data['max_points'] ?? 1.0,
                'status' => $data['status'] ?? 'published',
            ],
            [ '%s', '%d', '%s', '%d', '%s', '%f', '%s' ]
        );
        
        if ( ! $result ) {
            return new WP_Error( 'ppq_db_error', __( 'Failed to create question.', 'pressprimer-quiz' ) );
        }
        
        $question_id = $wpdb->insert_id;
        
        // Create initial revision
        $revision_id = PressPrimer_Quiz_Question_Revision::create( $question_id, [
            'stem' => $data['stem'],
            'answers' => $data['answers'],
            'feedback_correct' => $data['feedback_correct'] ?? null,
            'feedback_incorrect' => $data['feedback_incorrect'] ?? null,
        ] );
        
        if ( is_wp_error( $revision_id ) ) {
            // Rollback question
            $wpdb->delete( $wpdb->prefix . 'ppq_questions', [ 'id' => $question_id ] );
            return $revision_id;
        }
        
        // Update current revision
        $wpdb->update(
            $wpdb->prefix . 'ppq_questions',
            [ 'current_revision_id' => $revision_id ],
            [ 'id' => $question_id ]
        );
        
        return $question_id;
    }
    
    /**
     * Save changes to question
     *
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function save() {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'ppq_questions',
            [
                'type' => $this->type,
                'expected_seconds' => $this->expected_seconds,
                'difficulty_author' => $this->difficulty_author,
                'max_points' => $this->max_points,
                'status' => $this->status,
            ],
            [ 'id' => $this->id ],
            [ '%s', '%d', '%s', '%f', '%s' ],
            [ '%d' ]
        );
        
        if ( false === $result ) {
            return new WP_Error( 'ppq_db_error', __( 'Failed to save question.', 'pressprimer-quiz' ) );
        }
        
        return true;
    }
    
    /**
     * Soft delete
     */
    public function delete(): bool {
        global $wpdb;
        
        return (bool) $wpdb->update(
            $wpdb->prefix . 'ppq_questions',
            [ 'deleted_at' => current_time( 'mysql' ) ],
            [ 'id' => $this->id ]
        );
    }
    
    /**
     * Get current revision (lazy loaded)
     */
    public function get_current_revision(): ?PPQ_Question_Revision {
        if ( null === $this->current_revision && $this->current_revision_id ) {
            $this->current_revision = PressPrimer_Quiz_Question_Revision::get( $this->current_revision_id );
        }
        return $this->current_revision;
    }
    
    /**
     * Get categories (lazy loaded)
     */
    public function get_categories(): array {
        if ( null === $this->categories ) {
            $this->categories = PressPrimer_Quiz_Category::get_for_question( $this->id, 'category' );
        }
        return $this->categories;
    }
    
    /**
     * Get tags (lazy loaded)
     */
    public function get_tags(): array {
        if ( null === $this->tags ) {
            $this->tags = PressPrimer_Quiz_Category::get_for_question( $this->id, 'tag' );
        }
        return $this->tags;
    }
}
```

## Service Classes

Services contain business logic:

```php
<?php
/**
 * Scoring service
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 */

class PressPrimer_Quiz_Scoring_Service {
    
    /**
     * Score a single question response
     */
    public function score_response( PPQ_Question $question, array $selected_answers ): array {
        $revision = $question->get_current_revision();
        $answers = json_decode( $revision->answers_json, true );
        $correct_ids = array_column(
            array_filter( $answers, fn( $a ) => $a['is_correct'] ),
            'id'
        );
        
        switch ( $question->type ) {
            case 'mc':
            case 'tf':
                return $this->score_single_answer( $correct_ids, $selected_answers, $question->max_points );
                
            case 'ma':
                return $this->score_multiple_answer( $correct_ids, $selected_answers, $question->max_points );
                
            default:
                return [
                    'is_correct' => false,
                    'score' => 0,
                    'max_points' => $question->max_points,
                ];
        }
    }
    
    /**
     * Score single-answer question (MC, TF)
     */
    private function score_single_answer( array $correct_ids, array $selected_answers, float $max_points ): array {
        $is_correct = count( $selected_answers ) === 1 
            && in_array( $selected_answers[0], $correct_ids, true );
        
        return [
            'is_correct' => $is_correct,
            'score' => $is_correct ? $max_points : 0,
            'max_points' => $max_points,
        ];
    }
    
    /**
     * Score multiple-answer question with partial credit
     * 
     * Formula: (correct_selected - incorrect_selected) / total_correct * max_points
     * Floored at 0 (no negative scores unless enabled)
     */
    private function score_multiple_answer( array $correct_ids, array $selected_answers, float $max_points ): array {
        $total_correct = count( $correct_ids );
        
        if ( 0 === $total_correct ) {
            return [
                'is_correct' => empty( $selected_answers ),
                'score' => empty( $selected_answers ) ? $max_points : 0,
                'max_points' => $max_points,
            ];
        }
        
        $correct_selected = count( array_intersect( $selected_answers, $correct_ids ) );
        $incorrect_selected = count( array_diff( $selected_answers, $correct_ids ) );
        
        $is_correct = $correct_selected === $total_correct && 0 === $incorrect_selected;
        
        // Proportional scoring with penalty for incorrect
        $raw_score = ( $correct_selected - $incorrect_selected ) / $total_correct;
        $score = max( 0, $raw_score ) * $max_points;
        
        return [
            'is_correct' => $is_correct,
            'score' => round( $score, 2 ),
            'max_points' => $max_points,
            'partial_credit' => ! $is_correct && $score > 0,
        ];
    }
    
    /**
     * Calculate total score for an attempt
     */
    public function calculate_attempt_score( int $attempt_id ): array {
        global $wpdb;
        
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT ai.*, qr.answers_json, q.type, q.max_points
             FROM {$wpdb->prefix}ppq_attempt_items ai
             JOIN {$wpdb->prefix}ppq_question_revisions qr ON ai.question_revision_id = qr.id
             JOIN {$wpdb->prefix}ppq_questions q ON qr.question_id = q.id
             WHERE ai.attempt_id = %d",
            $attempt_id
        ) );
        
        $total_score = 0;
        $total_max = 0;
        $correct_count = 0;
        
        foreach ( $items as $item ) {
            $selected = json_decode( $item->selected_answers_json ?: '[]', true );
            
            $question = new PressPrimer_Quiz_Question();
            $question->type = $item->type;
            $question->max_points = (float) $item->max_points;
            $question->current_revision_id = $item->question_revision_id;
            
            // Create mock revision for scoring
            $revision = new stdClass();
            $revision->answers_json = $item->answers_json;
            
            // This is a simplified approach - in production, use proper model
            $result = $this->score_response( $question, $selected );
            
            $total_score += $result['score'];
            $total_max += $result['max_points'];
            
            if ( $result['is_correct'] ) {
                $correct_count++;
            }
            
            // Update attempt item with score
            $wpdb->update(
                $wpdb->prefix . 'ppq_attempt_items',
                [
                    'is_correct' => $result['is_correct'] ? 1 : 0,
                    'score_points' => $result['score'],
                ],
                [ 'id' => $item->id ]
            );
        }
        
        $percentage = $total_max > 0 ? ( $total_score / $total_max ) * 100 : 0;
        
        return [
            'score_points' => round( $total_score, 2 ),
            'max_points' => round( $total_max, 2 ),
            'score_percent' => round( $percentage, 2 ),
            'correct_count' => $correct_count,
            'total_count' => count( $items ),
        ];
    }
}
```

## Admin Classes

Admin classes handle wp-admin functionality:

```php
<?php
/**
 * Admin class
 *
 * @package PressPrimer_Quiz
 * @subpackage Admin
 */

class PressPrimer_Quiz_Admin {
    
    /**
     * Initialize admin hooks
     */
    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_ppq_save_question', [ $this, 'ajax_save_question' ] );
        // ... more hooks
    }
    
    /**
     * Register admin menus
     */
    public function register_menus(): void {
        // Main menu
        add_menu_page(
            __( 'PressPrimer Quiz', 'pressprimer-quiz' ),
            __( 'PPQ', 'pressprimer-quiz' ),
            'ppq_manage_own',
            'ppq-quizzes',
            [ $this, 'render_quizzes_page' ],
            'dashicons-welcome-learn-more',
            30
        );
        
        // Submenus
        add_submenu_page(
            'ppq-quizzes',
            __( 'Quizzes', 'pressprimer-quiz' ),
            __( 'Quizzes', 'pressprimer-quiz' ),
            'ppq_manage_own',
            'ppq-quizzes',
            [ $this, 'render_quizzes_page' ]
        );
        
        add_submenu_page(
            'ppq-quizzes',
            __( 'Questions', 'pressprimer-quiz' ),
            __( 'Questions', 'pressprimer-quiz' ),
            'ppq_manage_own',
            'ppq-questions',
            [ $this, 'render_questions_page' ]
        );
        
        add_submenu_page(
            'ppq-quizzes',
            __( 'Question Banks', 'pressprimer-quiz' ),
            __( 'Question Banks', 'pressprimer-quiz' ),
            'ppq_manage_own',
            'ppq-banks',
            [ $this, 'render_banks_page' ]
        );
        
        add_submenu_page(
            'ppq-quizzes',
            __( 'Groups', 'pressprimer-quiz' ),
            __( 'Groups', 'pressprimer-quiz' ),
            'ppq_manage_own',
            'ppq-groups',
            [ $this, 'render_groups_page' ]
        );
        
        add_submenu_page(
            'ppq-quizzes',
            __( 'Reports', 'pressprimer-quiz' ),
            __( 'Reports', 'pressprimer-quiz' ),
            'ppq_view_results_own',
            'ppq-reports',
            [ $this, 'render_reports_page' ]
        );
        
        add_submenu_page(
            'ppq-quizzes',
            __( 'Settings', 'pressprimer-quiz' ),
            __( 'Settings', 'pressprimer-quiz' ),
            'ppq_manage_settings',
            'ppq-settings',
            [ $this, 'render_settings_page' ]
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets( string $hook ): void {
        // Only on our pages
        if ( strpos( $hook, 'ppq-' ) === false ) {
            return;
        }
        
        wp_enqueue_style(
            'ppq-admin',
            PPQ_PLUGIN_URL . 'assets/css/admin.css',
            [],
            PPQ_VERSION
        );
        
        wp_enqueue_script(
            'ppq-admin',
            PPQ_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery', 'wp-util' ],
            PPQ_VERSION,
            true
        );
        
        wp_localize_script( 'ppq-admin', 'ppqAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'ppq_admin_nonce' ),
            'strings' => [
                'confirm_delete' => __( 'Are you sure you want to delete this?', 'pressprimer-quiz' ),
                'saving' => __( 'Saving...', 'pressprimer-quiz' ),
                'saved' => __( 'Saved!', 'pressprimer-quiz' ),
            ]
        ] );
    }
    
    // ... render methods and AJAX handlers
}
```

## Frontend Classes

Frontend classes handle public-facing functionality:

```php
<?php
/**
 * Frontend class
 *
 * @package PressPrimer_Quiz
 * @subpackage Frontend
 */

class PressPrimer_Quiz_Frontend {
    
    /**
     * Initialize frontend hooks
     */
    public function init(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_ppq_start_quiz', [ $this, 'ajax_start_quiz' ] );
        add_action( 'wp_ajax_nopriv_ppq_start_quiz', [ $this, 'ajax_start_quiz' ] );
        add_action( 'wp_ajax_ppq_save_answer', [ $this, 'ajax_save_answer' ] );
        add_action( 'wp_ajax_nopriv_ppq_save_answer', [ $this, 'ajax_save_answer' ] );
        add_action( 'wp_ajax_ppq_submit_quiz', [ $this, 'ajax_submit_quiz' ] );
        add_action( 'wp_ajax_nopriv_ppq_submit_quiz', [ $this, 'ajax_submit_quiz' ] );
    }
    
    /**
     * Conditionally enqueue assets
     */
    public function enqueue_assets(): void {
        // Only enqueue when quiz is on page
        global $post;
        
        if ( ! $post ) {
            return;
        }
        
        // Check for shortcode or block
        $has_quiz = has_shortcode( $post->post_content, 'ppq_quiz' )
            || has_block( 'pressprimer-quiz/quiz', $post );
        
        if ( ! $has_quiz ) {
            return;
        }
        
        wp_enqueue_style(
            'ppq-quiz',
            PPQ_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            PPQ_VERSION
        );
        
        wp_enqueue_script(
            'ppq-quiz',
            PPQ_PLUGIN_URL . 'assets/js/quiz.js',
            [ 'jquery' ],
            PPQ_VERSION,
            true
        );
    }
    
    // ... AJAX handlers
}
```

