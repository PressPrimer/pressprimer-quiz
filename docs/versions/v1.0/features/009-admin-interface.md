# Feature 009: Admin Interface

## Overview

The admin interface provides WordPress administrators with tools to manage PPQ Quizzes, questions, question banks, categories, and plugin settings. The interface uses React for complex interactions (quiz builder, question builder) while maintaining WordPress admin styling conventions.

## User Stories

### US-009-1: Dashboard Overview
**As an** administrator  
**I want to** see a quick overview of quiz activity  
**So that** I can monitor usage at a glance

### US-009-2: Manage Quizzes
**As an** administrator  
**I want to** create, edit, duplicate, and delete quizzes  
**So that** I can maintain my quiz library

### US-009-3: Manage Questions
**As an** administrator  
**I want to** create and organize questions in banks  
**So that** I can build a reusable question library

### US-009-4: Manage Categories
**As an** administrator  
**I want to** organize quizzes and questions with categories and tags  
**So that** content is easy to find and filter

### US-009-5: Configure Settings
**As an** administrator  
**I want to** set global defaults and preferences  
**So that** new quizzes use my preferred configuration

## Acceptance Criteria

### Admin Menu Structure

- [ ] Top-level menu: "PPQ" with quiz icon
- [ ] Submenu: PPQ Quizzes (quiz list and builder)
- [ ] Submenu: Questions (question list and builder)
- [ ] Submenu: Question Banks (bank management)
- [ ] Submenu: Categories (category/tag management)
- [ ] Submenu: Reports (see 010-admin-reporting.md)
- [ ] Submenu: Settings (global configuration)
- [ ] Menu position: 30 (after Comments)

### Dashboard Widget

See `010-admin-reporting.md` for full dashboard widget specification.

- [ ] Widget on WordPress dashboard
- [ ] Shows: Total quizzes, Total questions, Recent attempts (last 7 days)
- [ ] Shows: Most popular quizzes (by attempts)
- [ ] Quick links: Create Quiz, Create Question, View Reports, Launch Onboarding
- [ ] Collapsible/dismissible like standard widgets

### PPQ Quizzes Page

**List View:**
- [ ] Table with columns: Title, Questions, Attempts, Status, Date
- [ ] Sortable by each column
- [ ] Bulk actions: Publish, Draft, Delete
- [ ] Row actions: Edit, Duplicate, View, Delete
- [ ] Filter by status (All, Published, Draft)
- [ ] Search by title
- [ ] Pagination (20 per page default)

**Quiz Builder (React):**
- [ ] Title input (required)
- [ ] Description editor (TinyMCE or simple textarea)
- [ ] Featured image selector
- [ ] Question selection panel
  - [ ] Add from existing questions
  - [ ] Create new question inline
  - [ ] Dynamic rules for pulling from banks
  - [ ] Drag-and-drop reordering
  - [ ] Weight/points per question
- [ ] Settings panel
  - [ ] Mode (Tutorial/Timed)
  - [ ] Time limit
  - [ ] Passing score
  - [ ] Navigation options
  - [ ] Attempt limits
  - [ ] Randomization options
  - [ ] Display mode
  - [ ] Answer visibility
  - [ ] Confidence rating toggle
- [ ] Theme panel (see 008-themes-styling.md)
- [ ] Feedback panel
  - [ ] Score bands with messages
- [ ] Save as Draft / Publish buttons
- [ ] Preview button (opens in new tab)
- [ ] Sidebar: Shortcode display, embed instructions

### Questions Page

**List View:**
- [ ] Table with columns: Question (truncated), Type, Difficulty, Banks, Date
- [ ] Sortable columns
- [ ] Bulk actions: Delete, Add to Bank, Change Category
- [ ] Row actions: Edit, Duplicate, Preview, Delete
- [ ] Filter by: Type (MC/MA/TF), Difficulty, Category, Bank
- [ ] Search by question text
- [ ] Pagination

**Question Builder (React):**
- [ ] Type selector (Multiple Choice, Multiple Answer, True/False)
- [ ] Question stem editor (TinyMCE with HTML support)
- [ ] Answer options panel
  - [ ] Add/remove options (2-8 for MC/MA, fixed 2 for TF)
  - [ ] Mark correct answer(s)
  - [ ] Answer text editor (HTML support)
  - [ ] Per-answer feedback
  - [ ] Drag-and-drop reordering
- [ ] Question-level feedback (correct/incorrect)
- [ ] Metadata panel
  - [ ] Difficulty (Easy/Medium/Hard)
  - [ ] Expected time (seconds)
  - [ ] Points
  - [ ] Categories (multi-select)
  - [ ] Tags (multi-select with creation)
- [ ] Add to bank selector
- [ ] Save / Save and Add Another buttons
- [ ] Preview panel (shows rendered question)

### Question Banks Page

**List View:**
- [ ] Table with columns: Name, Questions, Owner, Date
- [ ] Row actions: Edit, View Questions, Delete
- [ ] Search by name
- [ ] Filter by owner (Admin only: all users)

**Bank Editor:**
- [ ] Name input
- [ ] Description textarea
- [ ] Question list with add/remove
- [ ] Bulk add from existing questions
- [ ] Filter questions within bank

### Categories Page

**Split View (like WordPress categories):**
- [ ] Add New Category form on left
  - [ ] Name
  - [ ] Slug (auto-generated)
  - [ ] Parent (for hierarchy)
  - [ ] Description
- [ ] Category list table on right
  - [ ] Name, Description, Slug, Count
  - [ ] Inline edit
  - [ ] Delete
- [ ] Tab to switch between Categories and Tags
- [ ] Tags use same interface but flat (no parent)

### Settings Page

**Tabbed Interface:**

**General Tab:**
- [ ] Default quiz mode (Tutorial/Timed)
- [ ] Default time limit
- [ ] Default passing score
- [ ] Default theme
- [ ] Allow guest quiz taking (yes/no)
- [ ] Guest email requirement (optional/required/hidden)

**AI Tab:**
- [ ] OpenAI API key input (password field)
- [ ] Test connection button
- [ ] Default AI model selection
- [ ] Rate limit display (requests remaining)

**Integrations Tab:**
- [ ] LearnDash integration status
- [ ] TutorLMS integration status
- [ ] LifterLMS integration status
- [ ] Automator integration status
- [ ] Each shows: Detected/Not Detected, version

**Advanced Tab:**
- [ ] Data retention period
- [ ] Clear all quiz data (with confirmation)
- [ ] Export settings
- [ ] Import settings
- [ ] Debug mode toggle

**Display Tab:**
- [ ] Default primary color
- [ ] Default success/error colors
- [ ] Timer position (top-right, top-left, bottom)
- [ ] Progress bar style

### Access Control

- [ ] Administrators see all content
- [ ] Users with `ppq_manage_own` see only their content
- [ ] Settings page requires `ppq_manage_settings`
- [ ] Appropriate capability checks on all actions

### AJAX Operations

All admin operations use AJAX with proper nonce verification:
- [ ] Save quiz (autosave + manual)
- [ ] Save question
- [ ] Delete items
- [ ] Duplicate items
- [ ] Reorder questions
- [ ] Search/filter
- [ ] Bulk operations

## Technical Implementation

### React Admin App Structure

```
assets/js/admin/
â”œâ”€â”€ index.js                 # Entry point
â”œâ”€â”€ App.jsx                  # Router setup
â”œâ”€â”€ components/
â”‚   â”œâ”€â”€ QuizList.jsx
â”‚   â”œâ”€â”€ QuizBuilder/
â”‚   â”‚   â”œâ”€â”€ index.jsx
â”‚   â”‚   â”œâ”€â”€ QuestionSelector.jsx
â”‚   â”‚   â”œâ”€â”€ DynamicRules.jsx
â”‚   â”‚   â”œâ”€â”€ SettingsPanel.jsx
â”‚   â”‚   â”œâ”€â”€ ThemePanel.jsx
â”‚   â”‚   â””â”€â”€ FeedbackPanel.jsx
â”‚   â”œâ”€â”€ QuestionList.jsx
â”‚   â”œâ”€â”€ QuestionBuilder/
â”‚   â”‚   â”œâ”€â”€ index.jsx
â”‚   â”‚   â”œâ”€â”€ StemEditor.jsx
â”‚   â”‚   â”œâ”€â”€ AnswerOptions.jsx
â”‚   â”‚   â””â”€â”€ MetadataPanel.jsx
â”‚   â”œâ”€â”€ BankList.jsx
â”‚   â”œâ”€â”€ BankEditor.jsx
â”‚   â”œâ”€â”€ CategoryManager.jsx
â”‚   â””â”€â”€ common/
â”‚       â”œâ”€â”€ DataTable.jsx
â”‚       â”œâ”€â”€ Modal.jsx
â”‚       â”œâ”€â”€ Spinner.jsx
â”‚       â””â”€â”€ Notice.jsx
â”œâ”€â”€ hooks/
â”‚   â”œâ”€â”€ useQuizzes.js
â”‚   â”œâ”€â”€ useQuestions.js
â”‚   â”œâ”€â”€ useBanks.js
â”‚   â””â”€â”€ useCategories.js
â”œâ”€â”€ api/
â”‚   â””â”€â”€ client.js            # AJAX wrapper
â””â”€â”€ utils/
    â””â”€â”€ helpers.js
```

### Admin Page Registration

```php
<?php
/**
 * Admin class
 *
 * @package PressPrimer_Quiz
 * @subpackage Admin
 */

class PPQ_Admin {
    
    /**
     * Initialize admin
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_ppq_get_quizzes', array( $this, 'ajax_get_quizzes' ) );
        add_action( 'wp_ajax_ppq_save_quiz', array( $this, 'ajax_save_quiz' ) );
        add_action( 'wp_ajax_ppq_delete_quiz', array( $this, 'ajax_delete_quiz' ) );
        add_action( 'wp_ajax_ppq_duplicate_quiz', array( $this, 'ajax_duplicate_quiz' ) );
        // ... more handlers
    }
    
    /**
     * Register admin menus
     */
    public function register_menus() {
        // Main menu
        add_menu_page(
            __( 'PressPrimer Quiz', 'pressprimer-quiz' ),
            __( 'PPQ', 'pressprimer-quiz' ),
            'ppq_manage_own',
            'ppq-quizzes',
            array( $this, 'render_quizzes_page' ),
            'dashicons-welcome-learn-more',
            30
        );
        
        // Quizzes (same as main)
        add_submenu_page(
            'ppq-quizzes',
            __( 'PPQ Quizzes', 'pressprimer-quiz' ),
            __( 'PPQ Quizzes', 'pressprimer-quiz' ),
            'ppq_manage_own',
            'ppq-quizzes',
            array( $this, 'render_quizzes_page' )
        );
        
        // Questions
        add_submenu_page(
            'ppq-quizzes',
            __( 'Questions', 'pressprimer-quiz' ),
            __( 'Questions', 'pressprimer-quiz' ),
            'ppq_manage_own',
            'ppq-questions',
            array( $this, 'render_questions_page' )
        );
        
        // Question Banks
        add_submenu_page(
            'ppq-quizzes',
            __( 'Question Banks', 'pressprimer-quiz' ),
            __( 'Question Banks', 'pressprimer-quiz' ),
            'ppq_manage_own',
            'ppq-banks',
            array( $this, 'render_banks_page' )
        );
        
        // Categories
        add_submenu_page(
            'ppq-quizzes',
            __( 'Categories', 'pressprimer-quiz' ),
            __( 'Categories', 'pressprimer-quiz' ),
            'ppq_manage_own',
            'ppq-categories',
            array( $this, 'render_categories_page' )
        );
        
        // Settings (admin only)
        add_submenu_page(
            'ppq-quizzes',
            __( 'Settings', 'pressprimer-quiz' ),
            __( 'Settings', 'pressprimer-quiz' ),
            'ppq_manage_settings',
            'ppq-settings',
            array( $this, 'render_settings_page' )
        );
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        // Global admin styles (for dashboard widget, etc.)
        wp_enqueue_style(
            'ppq-admin-global',
            PPQ_PLUGIN_URL . 'assets/css/admin-global.css',
            array(),
            PPQ_VERSION
        );
        
        // Only load React app on PPQ pages
        if ( strpos( $hook, 'ppq-' ) === false ) {
            return;
        }
        
        // React app
        $asset_file = include PPQ_PLUGIN_PATH . 'assets/js/admin/build/index.asset.php';
        
        wp_enqueue_script(
            'ppq-admin',
            PPQ_PLUGIN_URL . 'assets/js/admin/build/index.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );
        
        wp_enqueue_style(
            'ppq-admin',
            PPQ_PLUGIN_URL . 'assets/css/admin.css',
            array( 'wp-components' ),
            PPQ_VERSION
        );
        
        // Localize script data
        wp_localize_script( 'ppq-admin', 'ppqAdmin', array(
            'apiUrl'       => rest_url( 'ppq/v1/' ),
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'ppq_admin' ),
            'restNonce'    => wp_create_nonce( 'wp_rest' ),
            'currentPage'  => $this->get_current_ppq_page( $hook ),
            'userId'       => get_current_user_id(),
            'isAdmin'      => current_user_can( 'ppq_manage_all' ),
            'settings'     => ppq_get_settings(),
            'integrations' => ppq_detect_lms_plugins(),
            'strings'      => array(
                'saving'        => __( 'Saving...', 'pressprimer-quiz' ),
                'saved'         => __( 'Saved!', 'pressprimer-quiz' ),
                'error'         => __( 'Error saving', 'pressprimer-quiz' ),
                'confirmDelete' => __( 'Are you sure? This cannot be undone.', 'pressprimer-quiz' ),
                'noQuestions'   => __( 'No questions found.', 'pressprimer-quiz' ),
                'addQuestion'   => __( 'Add Question', 'pressprimer-quiz' ),
            ),
        ) );
        
        // Set script translations
        wp_set_script_translations( 'ppq-admin', 'pressprimer-quiz' );
    }
    
    /**
     * Render quizzes page (React mount point)
     */
    public function render_quizzes_page() {
        echo '<div class="wrap"><div id="ppq-admin-root" data-page="quizzes"></div></div>';
    }
    
    /**
     * Render questions page
     */
    public function render_questions_page() {
        echo '<div class="wrap"><div id="ppq-admin-root" data-page="questions"></div></div>';
    }
    
    /**
     * Render banks page
     */
    public function render_banks_page() {
        echo '<div class="wrap"><div id="ppq-admin-root" data-page="banks"></div></div>';
    }
    
    /**
     * Render categories page
     */
    public function render_categories_page() {
        echo '<div class="wrap"><div id="ppq-admin-root" data-page="categories"></div></div>';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        echo '<div class="wrap"><div id="ppq-admin-root" data-page="settings"></div></div>';
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        if ( ! current_user_can( 'ppq_manage_own' ) ) {
            return;
        }
        
        wp_add_dashboard_widget(
            'ppq_dashboard_widget',
            __( 'PPQ Quiz Overview', 'pressprimer-quiz' ),
            array( $this, 'render_dashboard_widget' )
        );
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $stats = ppq_get_dashboard_stats();
        ?>
        <div class="ppq-dashboard-widget">
            <div class="ppq-stats">
                <div class="ppq-stat">
                    <span class="ppq-stat__value"><?php echo esc_html( $stats['total_quizzes'] ); ?></span>
                    <span class="ppq-stat__label"><?php esc_html_e( 'Quizzes', 'pressprimer-quiz' ); ?></span>
                </div>
                <div class="ppq-stat">
                    <span class="ppq-stat__value"><?php echo esc_html( $stats['total_questions'] ); ?></span>
                    <span class="ppq-stat__label"><?php esc_html_e( 'Questions', 'pressprimer-quiz' ); ?></span>
                </div>
                <div class="ppq-stat">
                    <span class="ppq-stat__value"><?php echo esc_html( $stats['recent_attempts'] ); ?></span>
                    <span class="ppq-stat__label"><?php esc_html_e( 'Attempts (7 days)', 'pressprimer-quiz' ); ?></span>
                </div>
            </div>
            
            <?php if ( ! empty( $stats['popular_quizzes'] ) ) : ?>
            <h4><?php esc_html_e( 'Popular Quizzes', 'pressprimer-quiz' ); ?></h4>
            <ul class="ppq-popular-list">
                <?php foreach ( $stats['popular_quizzes'] as $quiz ) : ?>
                <li>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-quizzes&action=edit&id=' . $quiz->id ) ); ?>">
                        <?php echo esc_html( $quiz->title ); ?>
                    </a>
                    <span class="ppq-attempt-count"><?php echo esc_html( $quiz->attempt_count ); ?> attempts</span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            
            <div class="ppq-quick-links">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-quizzes&action=new' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Create Quiz', 'pressprimer-quiz' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ppq-questions&action=new' ) ); ?>" class="button">
                    <?php esc_html_e( 'Add Question', 'pressprimer-quiz' ); ?>
                </a>
            </div>
        </div>
        <?php
    }
}
```

### React Quiz Builder Component

```jsx
/**
 * Quiz Builder Component
 */
import { useState, useEffect, useCallback } from 'react';
import { 
    TextControl, 
    TextareaControl, 
    Panel, 
    PanelBody,
    ToggleControl,
    SelectControl,
    RangeControl,
    Button,
    Spinner,
    Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { DndProvider } from 'react-dnd';
import { HTML5Backend } from 'react-dnd-html5-backend';

import QuestionSelector from './QuestionSelector';
import DynamicRules from './DynamicRules';
import SettingsPanel from './SettingsPanel';
import ThemePanel from './ThemePanel';
import FeedbackPanel from './FeedbackPanel';
import { useQuiz, useSaveQuiz } from '../../hooks/useQuizzes';

const QuizBuilder = ({ quizId }) => {
    const { quiz, isLoading, error } = useQuiz(quizId);
    const { saveQuiz, isSaving, saveError } = useSaveQuiz();
    
    const [formData, setFormData] = useState({
        title: '',
        description: '',
        featured_image_id: null,
        mode: 'tutorial',
        time_limit_seconds: null,
        pass_percent: 70,
        allow_skip: true,
        allow_backward: true,
        allow_resume: true,
        max_attempts: null,
        attempt_delay_minutes: null,
        randomize_questions: false,
        randomize_answers: false,
        page_mode: 'single',
        show_answers: 'after_submit',
        enable_confidence: false,
        theme: 'default',
        theme_settings: {},
        band_feedback: [],
        generation_mode: 'fixed',
        questions: [],
        rules: [],
    });
    
    const [hasChanges, setHasChanges] = useState(false);
    
    // Load quiz data
    useEffect(() => {
        if (quiz) {
            setFormData({
                ...formData,
                ...quiz,
            });
        }
    }, [quiz]);
    
    // Handle field changes
    const handleChange = useCallback((field, value) => {
        setFormData(prev => ({
            ...prev,
            [field]: value,
        }));
        setHasChanges(true);
    }, []);
    
    // Handle save
    const handleSave = async (status = null) => {
        const data = { ...formData };
        if (status) {
            data.status = status;
        }
        
        const result = await saveQuiz(quizId, data);
        
        if (result.success) {
            setHasChanges(false);
            if (!quizId && result.quiz_id) {
                // Redirect to edit URL for new quiz
                window.location.href = `admin.php?page=ppq-quizzes&action=edit&id=${result.quiz_id}`;
            }
        }
    };
    
    // Handle preview
    const handlePreview = () => {
        // Save first, then open preview
        handleSave().then(() => {
            window.open(formData.preview_url || `/?ppq_preview=${quizId}`, '_blank');
        });
    };
    
    if (isLoading) {
        return <Spinner />;
    }
    
    if (error) {
        return <Notice status="error">{error.message}</Notice>;
    }
    
    return (
        <DndProvider backend={HTML5Backend}>
            <div className="ppq-quiz-builder">
                <div className="ppq-quiz-builder__header">
                    <h1>
                        {quizId 
                            ? __('Edit PPQ Quiz', 'pressprimer-quiz')
                            : __('Create PPQ Quiz', 'pressprimer-quiz')
                        }
                    </h1>
                    <div className="ppq-quiz-builder__actions">
                        <Button 
                            variant="secondary"
                            onClick={handlePreview}
                            disabled={!quizId}
                        >
                            {__('Preview', 'pressprimer-quiz')}
                        </Button>
                        <Button 
                            variant="secondary"
                            onClick={() => handleSave('draft')}
                            disabled={isSaving}
                        >
                            {__('Save Draft', 'pressprimer-quiz')}
                        </Button>
                        <Button 
                            variant="primary"
                            onClick={() => handleSave('published')}
                            disabled={isSaving}
                        >
                            {isSaving ? <Spinner /> : __('Publish', 'pressprimer-quiz')}
                        </Button>
                    </div>
                </div>
                
                {saveError && (
                    <Notice status="error" isDismissible={false}>
                        {saveError.message}
                    </Notice>
                )}
                
                <div className="ppq-quiz-builder__content">
                    <div className="ppq-quiz-builder__main">
                        {/* Title */}
                        <TextControl
                            label={__('Quiz Title', 'pressprimer-quiz')}
                            value={formData.title}
                            onChange={(value) => handleChange('title', value)}
                            placeholder={__('Enter quiz title...', 'pressprimer-quiz')}
                            className="ppq-quiz-title"
                        />
                        
                        {/* Description */}
                        <TextareaControl
                            label={__('Description', 'pressprimer-quiz')}
                            value={formData.description}
                            onChange={(value) => handleChange('description', value)}
                            placeholder={__('Optional description shown on quiz landing page', 'pressprimer-quiz')}
                            rows={3}
                        />
                        
                        {/* Question Selection */}
                        <Panel>
                            <PanelBody 
                                title={__('Questions', 'pressprimer-quiz')} 
                                initialOpen={true}
                            >
                                <SelectControl
                                    label={__('Question Source', 'pressprimer-quiz')}
                                    value={formData.generation_mode}
                                    options={[
                                        { label: __('Fixed Questions', 'pressprimer-quiz'), value: 'fixed' },
                                        { label: __('Dynamic from Banks', 'pressprimer-quiz'), value: 'dynamic' },
                                    ]}
                                    onChange={(value) => handleChange('generation_mode', value)}
                                />
                                
                                {formData.generation_mode === 'fixed' ? (
                                    <QuestionSelector
                                        questions={formData.questions}
                                        onChange={(questions) => handleChange('questions', questions)}
                                    />
                                ) : (
                                    <DynamicRules
                                        rules={formData.rules}
                                        onChange={(rules) => handleChange('rules', rules)}
                                    />
                                )}
                            </PanelBody>
                        </Panel>
                    </div>
                    
                    <div className="ppq-quiz-builder__sidebar">
                        {/* Settings Panel */}
                        <SettingsPanel 
                            settings={formData}
                            onChange={handleChange}
                        />
                        
                        {/* Theme Panel */}
                        <ThemePanel
                            theme={formData.theme}
                            themeSettings={formData.theme_settings}
                            onChange={(theme, settings) => {
                                handleChange('theme', theme);
                                handleChange('theme_settings', settings);
                            }}
                        />
                        
                        {/* Feedback Panel */}
                        <FeedbackPanel
                            bands={formData.band_feedback}
                            onChange={(bands) => handleChange('band_feedback', bands)}
                        />
                        
                        {/* Shortcode Info */}
                        {quizId && (
                            <Panel>
                                <PanelBody title={__('Embed', 'pressprimer-quiz')}>
                                    <p>{__('Use this shortcode to embed the quiz:', 'pressprimer-quiz')}</p>
                                    <code className="ppq-shortcode">
                                        [ppq_quiz id="{quizId}"]
                                    </code>
                                    <p className="description">
                                        {__('Or use the PPQ Quiz block in the editor.', 'pressprimer-quiz')}
                                    </p>
                                </PanelBody>
                            </Panel>
                        )}
                    </div>
                </div>
            </div>
        </DndProvider>
    );
};

export default QuizBuilder;
```

### Settings Page Component

```jsx
/**
 * Settings Page Component
 */
import { useState, useEffect } from 'react';
import {
    TabPanel,
    Panel,
    PanelBody,
    TextControl,
    SelectControl,
    ToggleControl,
    Button,
    Notice,
    Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSettings, useSaveSettings } from '../../hooks/useSettings';

const SettingsPage = () => {
    const { settings, isLoading } = useSettings();
    const { saveSettings, isSaving, saveResult } = useSaveSettings();
    const [formData, setFormData] = useState({});
    
    useEffect(() => {
        if (settings) {
            setFormData(settings);
        }
    }, [settings]);
    
    const handleChange = (key, value) => {
        setFormData(prev => ({ ...prev, [key]: value }));
    };
    
    const handleSave = async () => {
        await saveSettings(formData);
    };
    
    const handleTestApiKey = async () => {
        // Test OpenAI connection
        const response = await fetch(ppqAdmin.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'ppq_test_openai',
                nonce: ppqAdmin.nonce,
                api_key: formData.openai_api_key,
            }),
        });
        const result = await response.json();
        alert(result.success ? __('Connection successful!', 'pressprimer-quiz') : result.data.message);
    };
    
    if (isLoading) {
        return <Spinner />;
    }
    
    const tabs = [
        {
            name: 'general',
            title: __('General', 'pressprimer-quiz'),
            content: (
                <div className="ppq-settings-section">
                    <SelectControl
                        label={__('Default Quiz Mode', 'pressprimer-quiz')}
                        value={formData.default_mode}
                        options={[
                            { label: __('Tutorial (immediate feedback)', 'pressprimer-quiz'), value: 'tutorial' },
                            { label: __('Timed (feedback at end)', 'pressprimer-quiz'), value: 'timed' },
                        ]}
                        onChange={(value) => handleChange('default_mode', value)}
                    />
                    
                    <TextControl
                        type="number"
                        label={__('Default Time Limit (minutes)', 'pressprimer-quiz')}
                        value={formData.default_time_limit}
                        onChange={(value) => handleChange('default_time_limit', value)}
                        help={__('Leave empty for no time limit', 'pressprimer-quiz')}
                    />
                    
                    <TextControl
                        type="number"
                        label={__('Default Passing Score (%)', 'pressprimer-quiz')}
                        value={formData.default_pass_percent}
                        onChange={(value) => handleChange('default_pass_percent', value)}
                        min={0}
                        max={100}
                    />
                    
                    <SelectControl
                        label={__('Default Theme', 'pressprimer-quiz')}
                        value={formData.default_theme}
                        options={[
                            { label: 'Default', value: 'default' },
                            { label: 'Modern', value: 'modern' },
                            { label: 'Minimal', value: 'minimal' },
                        ]}
                        onChange={(value) => handleChange('default_theme', value)}
                    />
                    
                    <ToggleControl
                        label={__('Allow Guest Quiz Taking', 'pressprimer-quiz')}
                        checked={formData.allow_guests}
                        onChange={(value) => handleChange('allow_guests', value)}
                    />
                    
                    {formData.allow_guests && (
                        <SelectControl
                            label={__('Guest Email Capture', 'pressprimer-quiz')}
                            value={formData.guest_email_mode}
                            options={[
                                { label: __('Hidden', 'pressprimer-quiz'), value: 'hidden' },
                                { label: __('Optional', 'pressprimer-quiz'), value: 'optional' },
                                { label: __('Required', 'pressprimer-quiz'), value: 'required' },
                            ]}
                            onChange={(value) => handleChange('guest_email_mode', value)}
                        />
                    )}
                </div>
            ),
        },
        {
            name: 'ai',
            title: __('AI', 'pressprimer-quiz'),
            content: (
                <div className="ppq-settings-section">
                    <TextControl
                        type="password"
                        label={__('OpenAI API Key', 'pressprimer-quiz')}
                        value={formData.openai_api_key || ''}
                        onChange={(value) => handleChange('openai_api_key', value)}
                        help={__('Your API key is encrypted before storage.', 'pressprimer-quiz')}
                    />
                    
                    <Button variant="secondary" onClick={handleTestApiKey}>
                        {__('Test Connection', 'pressprimer-quiz')}
                    </Button>
                    
                    <SelectControl
                        label={__('Default AI Model', 'pressprimer-quiz')}
                        value={formData.ai_model}
                        options={[
                            { label: 'GPT-4', value: 'gpt-4' },
                            { label: 'GPT-4 Turbo', value: 'gpt-4-turbo' },
                            { label: 'GPT-3.5 Turbo', value: 'gpt-3.5-turbo' },
                        ]}
                        onChange={(value) => handleChange('ai_model', value)}
                    />
                </div>
            ),
        },
        {
            name: 'integrations',
            title: __('Integrations', 'pressprimer-quiz'),
            content: (
                <div className="ppq-settings-section">
                    <h3>{__('Detected Integrations', 'pressprimer-quiz')}</h3>
                    
                    {Object.entries(ppqAdmin.integrations).map(([key, info]) => (
                        <div key={key} className="ppq-integration-status">
                            <span className="ppq-integration-name">{info.name}</span>
                            <span className="ppq-integration-badge ppq-integration-badge--active">
                                {__('Active', 'pressprimer-quiz')} (v{info.version})
                            </span>
                        </div>
                    ))}
                    
                    {Object.keys(ppqAdmin.integrations).length === 0 && (
                        <p className="description">
                            {__('No LMS plugins detected. Install LearnDash, TutorLMS, or LifterLMS to enable integrations.', 'pressprimer-quiz')}
                        </p>
                    )}
                </div>
            ),
        },
    ];
    
    return (
        <div className="ppq-settings-page">
            <h1>{__('PPQ Settings', 'pressprimer-quiz')}</h1>
            
            {saveResult && (
                <Notice 
                    status={saveResult.success ? 'success' : 'error'}
                    isDismissible
                >
                    {saveResult.success 
                        ? __('Settings saved.', 'pressprimer-quiz')
                        : saveResult.error
                    }
                </Notice>
            )}
            
            <TabPanel
                className="ppq-settings-tabs"
                tabs={tabs}
            >
                {(tab) => tab.content}
            </TabPanel>
            
            <div className="ppq-settings-actions">
                <Button 
                    variant="primary" 
                    onClick={handleSave}
                    disabled={isSaving}
                >
                    {isSaving ? <Spinner /> : __('Save Settings', 'pressprimer-quiz')}
                </Button>
            </div>
        </div>
    );
};

export default SettingsPage;
```

## Database Requirements

Settings stored in WordPress options:
- `ppq_settings` - Serialized array of all settings
- Individual options as needed for performance

## UI/UX Requirements

### WordPress Admin Consistency
- Use WordPress admin color scheme variables
- Use WordPress components where available
- Follow WordPress admin spacing patterns
- Consistent button hierarchy (Primary, Secondary)

### React Component Styling
- Use @wordpress/components for consistency
- Custom styles in admin.css following WordPress patterns
- Responsive admin layouts
- Loading states for all async operations

### Error Handling
- Clear error messages
- Inline validation where appropriate
- Confirmation dialogs for destructive actions
- Auto-save with visual indicator

## Not In Scope (v1.0)

- Bulk import of questions (premium feature)
- Advanced analytics dashboard (premium feature)
- User management (handled by WordPress)
- Email template customization (v2.0)

**Note:** Dashboard widget and Reports page are documented in `010-admin-reporting.md`.

## Testing Checklist

- [ ] All menu items accessible
- [ ] Dashboard widget displays correctly
- [ ] Quiz list loads and paginates
- [ ] Quiz builder saves and loads correctly
- [ ] Question builder with all types works
- [ ] Question banks CRUD operations work
- [ ] Categories and tags management works
- [ ] Settings save and persist
- [ ] API key encryption works
- [ ] All AJAX operations have nonce verification
- [ ] Capability checks enforced
- [ ] Admin works without LMS plugins
- [ ] Admin works with each LMS plugin active
- [ ] No console errors
- [ ] Responsive at 1024px width
- [ ] Translations work in React components
