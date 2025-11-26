# Frontend Architecture

**Last Updated**: 2025-01-24  
**Status**: Approved for v1.0  
**Decision Owner**: Ryan

---

## Executive Summary

PressPrimer Quiz uses a **hybrid frontend architecture** optimized for both user experience and performance:

- **Admin Interface (Quiz Builder)**: React with WordPress Components
- **Quiz Taking (Frontend)**: Vanilla JavaScript with Web Components
- **Build System**: @wordpress/scripts (webpack-based)
- **Type Safety**: TypeScript throughout
- **State Management**: React Context + local state (admin), vanilla JS (frontend)

This approach delivers enterprise-grade quality in the admin while keeping the quiz-taking experience blazingly fast and accessible.

---

## Core Principles

1. **Performance First**: Quiz takers should experience instant load times, not heavy JavaScript bundles
2. **Developer Experience**: Modern tooling and AI-assisted development should be frictionless
3. **WordPress Native**: Leverage WordPress's built-in tools and patterns
4. **Progressive Enhancement**: Core functionality works without JavaScript
5. **Accessibility Always**: WCAG 2.1 AA compliance minimum
6. **Mobile First**: Optimized for smallest screens, enhanced for larger

---

## Architecture Decisions

### Decision 1: React for Admin Interface ✅

**Rationale**:
The quiz builder is a complex, interactive application requiring:
- Real-time question preview as you build
- Drag-and-drop question reordering
- Complex state management (unsaved changes, validation)
- Rich text editing for questions
- Media library integration
- AI-powered question generation with streaming responses
- Multi-step workflows (quiz settings, questions, grading)

**Why React**:
- ✅ WordPress already bundles React (@wordpress/element)
- ✅ @wordpress/components provides enterprise-quality UI components
- ✅ Familiar to WordPress developers (Gutenberg uses React)
- ✅ Excellent TypeScript support
- ✅ AI tools (Claude Code, Cursor) excel at React development
- ✅ Rich ecosystem for complex UI patterns
- ✅ Dev tools and debugging are excellent

**Alternative Considered**: Vue.js
- ❌ Not bundled with WordPress (adds 100KB+ to bundle)
- ❌ Less common in WordPress ecosystem
- ❌ AI tools less familiar with WordPress + Vue patterns

**Alternative Considered**: Vanilla JS
- ❌ Complex state management becomes unwieldy
- ❌ Significantly more code for same functionality
- ❌ Harder to maintain as features grow

**Bundle Size Impact**: 
- React (from WordPress): 0KB additional (already loaded)
- Our admin code: ~150-200KB minified (acceptable for admin-only)
- Loads only on quiz edit screens, not site-wide

**Implementation Details**:
```javascript
// Admin interface entry point
import { render } from '@wordpress/element';
import QuizBuilder from './components/QuizBuilder';

// Mount React app
const rootElement = document.getElementById('pressprimer-quiz-builder');
if (rootElement) {
    render(<QuizBuilder />, rootElement);
}
```

---

### Decision 2: Vanilla JavaScript for Quiz Taking ✅

**Rationale**:
Quiz takers are students, employees, end-users who need:
- Instant page loads (competing with slow LMS platforms)
- Works on low-end devices and slow connections
- Minimal JavaScript for maximum compatibility
- Progressive enhancement (works without JS for basic quizzes)
- Accessibility on screen readers without complex JS state

**Why Vanilla JavaScript**:
- ✅ Zero framework overhead (~10-20KB total JS)
- ✅ Faster initial page load
- ✅ Better SEO (server-rendered HTML)
- ✅ Works without JavaScript for basic functionality
- ✅ Easier to debug for non-technical users
- ✅ Compatible with more assistive technologies
- ✅ Web Components provide encapsulation without framework

**Alternative Considered**: React for quiz taking
- ❌ Adds 100KB+ to every quiz page load
- ❌ Requires hydration (slower perceived performance)
- ❌ Harder to make work without JavaScript
- ❌ Overkill for relatively simple interactive forms
- ❌ Hurts mobile performance significantly

**Alternative Considered**: Alpine.js
- ❌ Still adds framework overhead (15KB+)
- ❌ Less standard than Web Components
- ❌ Another dependency to maintain

**Bundle Size Impact**:
- Quiz taking JS: 10-15KB minified + gzipped
- Loads quickly even on 3G connections
- Can be inlined for critical quizzes

**Progressive Enhancement Strategy**:
```html
<!-- Works without JavaScript -->
<form method="POST" action="<?php echo esc_url($submit_url); ?>">
    <h2>Question 1: What is 2+2?</h2>
    <label>
        <input type="radio" name="q1" value="a"> 3
    </label>
    <label>
        <input type="radio" name="q1" value="b"> 4
    </label>
    <button type="submit">Submit Quiz</button>
</form>

<!-- Enhanced with JavaScript -->
<script>
// Add timer, auto-save, animations, better UX
</script>
```

---

### Decision 3: TypeScript Throughout ✅

**Rationale**:
- Catches bugs before runtime (especially important for quiz grading logic)
- Better autocomplete and documentation for AI tools
- Self-documenting code (types serve as inline documentation)
- Easier refactoring as codebase grows
- WordPress increasingly adopting TypeScript

**TypeScript Configuration**:
```json
{
  "compilerOptions": {
    "target": "ES2020",
    "module": "esnext",
    "jsx": "react",
    "strict": true,
    "esModuleInterop": true,
    "skipLibCheck": true,
    "forceConsistentCasingInFileNames": true,
    "moduleResolution": "node",
    "resolveJsonModule": true,
    "isolatedModules": true,
    "noEmit": true,
    "lib": ["ES2020", "DOM"]
  },
  "include": ["assets/src/**/*"],
  "exclude": ["node_modules", "assets/dist"]
}
```

**Type Definitions**:
```typescript
// Global quiz types
interface Quiz {
    id: number;
    title: string;
    questions: Question[];
    settings: QuizSettings;
}

interface Question {
    id: string;
    type: QuestionType;
    text: string;
    points: number;
    answers: Answer[];
}

type QuestionType = 
    | 'multiple-choice'
    | 'true-false'
    | 'short-answer'
    | 'essay'
    | 'fill-blank'
    | 'matching'
    | 'ordering'
    | 'hotspot';
```

---

### Decision 4: @wordpress/scripts Build System ✅

**Rationale**:
- Official WordPress tooling (maintained by Automattic)
- Zero-config setup for most common needs
- Includes webpack, Babel, ESLint, PostCSS
- Automatic code splitting
- Development and production builds
- Hot Module Replacement (HMR) for fast development

**package.json scripts**:
```json
{
  "scripts": {
    "start": "wp-scripts start",
    "build": "wp-scripts build",
    "check-engines": "wp-scripts check-engines",
    "check-licenses": "wp-scripts check-licenses",
    "format": "wp-scripts format",
    "lint:css": "wp-scripts lint-style",
    "lint:js": "wp-scripts lint-js",
    "lint:pkg-json": "wp-scripts lint-pkg-json",
    "test:e2e": "wp-scripts test-e2e",
    "test:unit": "wp-scripts test-unit-js"
  }
}
```

**Entry Points**:
```javascript
// webpack.config.js (if custom config needed)
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        'admin-quiz-builder': './assets/src/admin/quiz-builder.tsx',
        'admin-reports': './assets/src/admin/reports.tsx',
        'frontend-quiz': './assets/src/frontend/quiz.ts',
        'frontend-results': './assets/src/frontend/results.ts',
    },
};
```

---

## Directory Structure
```
pressprimer-quiz/
├── assets/
│   ├── src/                          # Source files (TypeScript/React)
│   │   ├── admin/                    # Admin interface (React)
│   │   │   ├── components/
│   │   │   │   ├── QuizBuilder/
│   │   │   │   │   ├── QuizBuilder.tsx
│   │   │   │   │   ├── QuestionList.tsx
│   │   │   │   │   ├── QuestionEditor.tsx
│   │   │   │   │   ├── AnswerEditor.tsx
│   │   │   │   │   └── QuizSettings.tsx
│   │   │   │   ├── Reports/
│   │   │   │   │   ├── ReportsDashboard.tsx
│   │   │   │   │   ├── StudentList.tsx
│   │   │   │   │   └── QuizStats.tsx
│   │   │   │   └── shared/
│   │   │   │       ├── Button.tsx
│   │   │   │       ├── Modal.tsx
│   │   │   │       └── LoadingSpinner.tsx
│   │   │   ├── hooks/
│   │   │   │   ├── useQuiz.ts
│   │   │   │   ├── useQuestions.ts
│   │   │   │   └── useAutoSave.ts
│   │   │   ├── context/
│   │   │   │   ├── QuizContext.tsx
│   │   │   │   └── UIContext.tsx
│   │   │   ├── utils/
│   │   │   │   ├── validation.ts
│   │   │   │   └── api.ts
│   │   │   ├── types/
│   │   │   │   └── index.ts
│   │   │   ├── quiz-builder.tsx      # Entry point
│   │   │   └── reports.tsx           # Entry point
│   │   │
│   │   ├── frontend/                 # Quiz taking (Vanilla JS)
│   │   │   ├── components/
│   │   │   │   ├── quiz-timer.ts
│   │   │   │   ├── quiz-navigation.ts
│   │   │   │   ├── question-renderer.ts
│   │   │   │   └── auto-save.ts
│   │   │   ├── utils/
│   │   │   │   ├── api.ts
│   │   │   │   └── storage.ts
│   │   │   ├── types/
│   │   │   │   └── index.ts
│   │   │   ├── quiz.ts               # Entry point
│   │   │   └── results.ts            # Entry point
│   │   │
│   │   └── shared/                   # Shared utilities
│   │       ├── constants.ts
│   │       ├── api-client.ts
│   │       └── types.ts
│   │
│   ├── dist/                         # Built files (git-ignored)
│   │   ├── admin-quiz-builder.js
│   │   ├── admin-quiz-builder.css
│   │   ├── frontend-quiz.js
│   │   └── frontend-quiz.css
│   │
│   └── css/                          # Additional stylesheets
│       ├── admin.css                 # Admin-only styles
│       └── frontend.css              # Quiz-taking styles
│
├── includes/
│   └── class-assets.php              # Asset enqueueing
│
└── package.json
```

---

## Component Architecture

### Admin Components (React)

**Quiz Builder Component Tree**:
```
QuizBuilder (Context Provider)
├── QuizHeader
│   ├── TitleEditor
│   └── SaveButton
├── QuizSettings (Modal)
│   ├── GeneralSettings
│   ├── TimingSettings
│   ├── GradingSettings
│   └── AccessControl
├── QuestionList
│   └── QuestionItem (draggable)
│       ├── QuestionEditor
│       │   ├── QuestionText (RichText)
│       │   ├── QuestionType (Dropdown)
│       │   └── AnswerEditor
│       │       └── Answer (repeater)
│       └── QuestionActions
│           ├── DeleteButton
│           ├── DuplicateButton
│           └── AIGenerateButton
└── QuestionAdder
    ├── AddQuestionButton
    └── AIGenerateModal
```

**State Management Pattern**:
```typescript
// Context for global quiz state
const QuizContext = createContext<QuizContextType>(null);

// Provider component
export function QuizProvider({ children, quizId }: Props) {
    const [quiz, setQuiz] = useState<Quiz | null>(null);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Auto-save hook
    useAutoSave(quiz, async (data) => {
        await api.saveQuiz(quizId, data);
    });

    return (
        <QuizContext.Provider value={{ quiz, setQuiz, saving, error }}>
            {children}
        </QuizContext.Provider>
    );
}

// Usage in components
function QuestionEditor({ questionId }: Props) {
    const { quiz, setQuiz } = useContext(QuizContext);
    const question = quiz.questions.find(q => q.id === questionId);
    
    const updateQuestion = (updates: Partial<Question>) => {
        setQuiz(prev => ({
            ...prev,
            questions: prev.questions.map(q =>
                q.id === questionId ? { ...q, ...updates } : q
            )
        }));
    };
    
    return (/* ... */);
}
```

---

### Frontend Components (Vanilla JS)

**Web Components Pattern**:
```typescript
// quiz-timer.ts
class QuizTimer extends HTMLElement {
    private timeRemaining: number;
    private intervalId: number | null = null;

    connectedCallback() {
        this.timeRemaining = parseInt(this.getAttribute('duration') || '0');
        this.render();
        this.startTimer();
    }

    disconnectedCallback() {
        this.stopTimer();
    }

    private startTimer() {
        this.intervalId = window.setInterval(() => {
            this.timeRemaining--;
            this.render();
            
            if (this.timeRemaining <= 0) {
                this.dispatchEvent(new CustomEvent('time-expired'));
                this.stopTimer();
            }
        }, 1000);
    }

    private stopTimer() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }
    }

    private render() {
        const minutes = Math.floor(this.timeRemaining / 60);
        const seconds = this.timeRemaining % 60;
        this.innerHTML = `
            <div class="pp-quiz-timer ${this.timeRemaining < 60 ? 'warning' : ''}">
                <span class="time">${minutes}:${seconds.toString().padStart(2, '0')}</span>
            </div>
        `;
    }
}

customElements.define('pp-quiz-timer', QuizTimer);
```

**Module Pattern for Core Functionality**:
```typescript
// quiz.ts - Main quiz controller
class QuizController {
    private form: HTMLFormElement;
    private questions: Map<string, QuestionController>;
    private autoSave: AutoSaveManager;

    constructor(formElement: HTMLFormElement) {
        this.form = formElement;
        this.questions = new Map();
        this.autoSave = new AutoSaveManager(this);
        
        this.init();
    }

    private init() {
        this.setupQuestions();
        this.setupNavigation();
        this.setupAutoSave();
        this.setupTimer();
        this.setupSubmit();
    }

    private setupQuestions() {
        const questionElements = this.form.querySelectorAll('.pp-question');
        questionElements.forEach(el => {
            const controller = new QuestionController(el as HTMLElement);
            this.questions.set(controller.getId(), controller);
        });
    }

    public getAnswers(): QuizAnswers {
        const answers: QuizAnswers = {};
        this.questions.forEach((controller, id) => {
            answers[id] = controller.getAnswer();
        });
        return answers;
    }

    public async submit(): Promise<void> {
        const answers = this.getAnswers();
        const response = await api.submitQuiz(this.getQuizId(), answers);
        window.location.href = response.redirect_url;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    const quizForm = document.getElementById('pp-quiz-form') as HTMLFormElement;
    if (quizForm) {
        new QuizController(quizForm);
    }
});
```

---

## Styling Architecture

### CSS Methodology: BEM + CSS Custom Properties

**Rationale**:
- BEM provides clear naming convention
- CSS Custom Properties enable theming
- Mobile-first approach
- No CSS-in-JS overhead

**Structure**:
```css
/* admin.css - Admin interface styles */

/* CSS Custom Properties */
:root {
    --pp-primary: #2271b1;
    --pp-primary-hover: #135e96;
    --pp-danger: #d63638;
    --pp-success: #00a32a;
    --pp-warning: #f0b849;
    
    --pp-spacing-xs: 4px;
    --pp-spacing-sm: 8px;
    --pp-spacing-md: 16px;
    --pp-spacing-lg: 24px;
    --pp-spacing-xl: 32px;
    
    --pp-border-radius: 4px;
    --pp-transition: 0.2s ease;
}

/* BEM Components */
.pp-quiz-builder {
    /* Block */
}

.pp-quiz-builder__header {
    /* Element */
}

.pp-quiz-builder__header--sticky {
    /* Modifier */
    position: sticky;
    top: 32px;
}

.pp-question-list {
    /* Block */
    display: flex;
    flex-direction: column;
    gap: var(--pp-spacing-md);
}

.pp-question-list__item {
    /* Element */
    background: white;
    border: 1px solid #ddd;
    border-radius: var(--pp-border-radius);
    padding: var(--pp-spacing-lg);
}

.pp-question-list__item--dragging {
    /* Modifier */
    opacity: 0.5;
}
```

**Frontend Styles**:
```css
/* frontend.css - Quiz taking styles */

/* Mobile-first approach */
.pp-quiz {
    max-width: 100%;
    margin: 0 auto;
    padding: var(--pp-spacing-md);
}

.pp-question {
    margin-bottom: var(--pp-spacing-lg);
    padding: var(--pp-spacing-lg);
    background: white;
    border-radius: var(--pp-border-radius);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.pp-question__text {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: var(--pp-spacing-md);
}

.pp-answer {
    display: block;
    padding: var(--pp-spacing-sm);
    margin-bottom: var(--pp-spacing-sm);
}

.pp-answer input {
    margin-right: var(--pp-spacing-sm);
}

/* Tablet and up */
@media (min-width: 768px) {
    .pp-quiz {
        max-width: 720px;
        padding: var(--pp-spacing-lg);
    }
}

/* Desktop */
@media (min-width: 1024px) {
    .pp-quiz {
        max-width: 960px;
    }
}
```

---

## API Integration

### WordPress REST API Pattern

**Endpoint Registration** (PHP):
```php
// includes/class-rest-api.php
class REST_API {
    public function register_routes() {
        // Quiz endpoints
        register_rest_route('pressprimer/v1', '/quizzes', [
            'methods' => 'GET',
            'callback' => [$this, 'get_quizzes'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        register_rest_route('pressprimer/v1', '/quizzes/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_quiz'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        register_rest_route('pressprimer/v1', '/quizzes/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'save_quiz'],
            'permission_callback' => [$this, 'check_edit_permission'],
        ]);
        
        // Quiz attempt endpoints
        register_rest_route('pressprimer/v1', '/quizzes/(?P<id>\d+)/attempt', [
            'methods' => 'POST',
            'callback' => [$this, 'submit_attempt'],
            'permission_callback' => [$this, 'check_attempt_permission'],
        ]);
    }
}
```

**API Client** (TypeScript):
```typescript
// assets/src/shared/api-client.ts
import apiFetch from '@wordpress/api-fetch';

export interface Quiz {
    id: number;
    title: string;
    questions: Question[];
    settings: QuizSettings;
}

export class APIClient {
    private baseUrl = '/wp-json/pressprimer/v1';

    async getQuiz(id: number): Promise<Quiz> {
        return await apiFetch({
            path: `${this.baseUrl}/quizzes/${id}`,
            method: 'GET',
        });
    }

    async saveQuiz(id: number, data: Partial<Quiz>): Promise<Quiz> {
        return await apiFetch({
            path: `${this.baseUrl}/quizzes/${id}`,
            method: 'POST',
            data,
        });
    }

    async submitAttempt(quizId: number, answers: QuizAnswers): Promise<AttemptResult> {
        return await apiFetch({
            path: `${this.baseUrl}/quizzes/${quizId}/attempt`,
            method: 'POST',
            data: { answers },
        });
    }
}

export const api = new APIClient();
```

**Usage in React**:
```typescript
// Custom hook for quiz data
function useQuiz(quizId: number) {
    const [quiz, setQuiz] = useState<Quiz | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        api.getQuiz(quizId)
            .then(setQuiz)
            .catch(err => setError(err.message))
            .finally(() => setLoading(false));
    }, [quizId]);

    const saveQuiz = async (updates: Partial<Quiz>) => {
        const updated = await api.saveQuiz(quizId, updates);
        setQuiz(updated);
    };

    return { quiz, loading, error, saveQuiz };
}
```

---

## Performance Optimization

### Code Splitting Strategy

**Admin Interface**:
```typescript
// Lazy load heavy components
const AIGenerateModal = lazy(() => import('./components/AIGenerateModal'));
const ReportsChart = lazy(() => import('./components/ReportsChart'));

function QuizBuilder() {
    const [showAI, setShowAI] = useState(false);
    
    return (
        <>
            <button onClick={() => setShowAI(true)}>Generate with AI</button>
            
            <Suspense fallback={<LoadingSpinner />}>
                {showAI && <AIGenerateModal />}
            </Suspense>
        </>
    );
}
```

**Dynamic Imports**:
```typescript
// Load features on demand
async function loadChartLibrary() {
    const { Chart } = await import('chart.js');
    return Chart;
}
```

### Asset Loading Strategy

**Critical CSS** (inline):
```php
// Inline critical styles for above-the-fold content
function enqueue_critical_css() {
    echo '<style>' . file_get_contents(PLUGIN_PATH . '/assets/dist/critical.css') . '</style>';
}
add_action('wp_head', 'enqueue_critical_css', 1);
```

**Deferred JavaScript**:
```php
// Load non-critical JS with defer
wp_enqueue_script(
    'pp-quiz-frontend',
    PLUGIN_URL . '/assets/dist/frontend-quiz.js',
    [],
    PLUGIN_VERSION,
    ['strategy' => 'defer'] // WordPress 6.3+
);
```

### Caching Strategy

**API Response Caching**:
```typescript
// Simple in-memory cache for quiz data
class CachedAPIClient extends APIClient {
    private cache = new Map<string, { data: any; timestamp: number }>();
    private cacheDuration = 5 * 60 * 1000; // 5 minutes

    async getQuiz(id: number): Promise<Quiz> {
        const cacheKey = `quiz-${id}`;
        const cached = this.cache.get(cacheKey);
        
        if (cached && Date.now() - cached.timestamp < this.cacheDuration) {
            return cached.data;
        }
        
        const quiz = await super.getQuiz(id);
        this.cache.set(cacheKey, { data: quiz, timestamp: Date.now() });
        return quiz;
    }
}
```

---

## Accessibility Implementation

### Semantic HTML Foundation
```html
<!-- Quiz structure with proper ARIA -->
<div class="pp-quiz" role="form" aria-label="Quiz: Introduction to Physics">
    <div class="pp-quiz__progress" role="progressbar" 
         aria-valuenow="3" aria-valuemin="1" aria-valuemax="10"
         aria-label="Question 3 of 10">
        <span class="pp-quiz__progress-bar" style="width: 30%"></span>
    </div>
    
    <div class="pp-question" id="question-1">
        <h2 class="pp-question__text">What is the speed of light?</h2>
        
        <fieldset class="pp-answers">
            <legend class="sr-only">Select your answer</legend>
            
            <label class="pp-answer">
                <input type="radio" name="q1" value="a" 
                       aria-describedby="question-1">
                <span>299,792,458 m/s</span>
            </label>
            
            <label class="pp-answer">
                <input type="radio" name="q1" value="b"
                       aria-describedby="question-1">
                <span>300,000,000 m/s</span>
            </label>
        </fieldset>
    </div>
    
    <div class="pp-quiz__navigation">
        <button type="button" class="pp-button" aria-label="Previous question">
            Previous
        </button>
        <button type="submit" class="pp-button pp-button--primary">
            Submit Quiz
        </button>
    </div>
</div>
```

### Focus Management
```typescript
// Focus management for modal dialogs
class ModalManager {
    private previousFocus: HTMLElement | null = null;
    
    open(modal: HTMLElement) {
        // Save current focus
        this.previousFocus = document.activeElement as HTMLElement;
        
        // Trap focus in modal
        modal.addEventListener('keydown', this.handleKeyDown);
        
        // Focus first focusable element
        const firstFocusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        (firstFocusable as HTMLElement)?.focus();
    }
    
    close(modal: HTMLElement) {
        // Remove trap
        modal.removeEventListener('keydown', this.handleKeyDown);
        
        // Restore focus
        this.previousFocus?.focus();
    }
    
    private handleKeyDown = (e: KeyboardEvent) => {
        if (e.key === 'Escape') {
            this.close(e.currentTarget as HTMLElement);
        }
        // Tab trap logic here
    };
}
```

### Screen Reader Announcements
```typescript
// Live region for dynamic updates
class Announcer {
    private liveRegion: HTMLElement;
    
    constructor() {
        this.liveRegion = document.createElement('div');
        this.liveRegion.setAttribute('aria-live', 'polite');
        this.liveRegion.setAttribute('aria-atomic', 'true');
        this.liveRegion.className = 'sr-only';
        document.body.appendChild(this.liveRegion);
    }
    
    announce(message: string, priority: 'polite' | 'assertive' = 'polite') {
        this.liveRegion.setAttribute('aria-live', priority);
        this.liveRegion.textContent = message;
        
        // Clear after announcement
        setTimeout(() => {
            this.liveRegion.textContent = '';
        }, 1000);
    }
}

// Usage
const announcer = new Announcer();
announcer.announce('Question saved successfully');
announcer.announce('Time remaining: 1 minute', 'assertive');
```

---

## Testing Strategy

### Unit Testing (Jest)
```typescript
// __tests__/validation.test.ts
import { validateQuizAnswers } from '../utils/validation';

describe('Quiz Answer Validation', () => {
    it('validates multiple choice answers', () => {
        const question = {
            id: 'q1',
            type: 'multiple-choice',
            correctAnswers: ['b'],
        };
        
        expect(validateQuizAnswers(question, 'b')).toBe(true);
        expect(validateQuizAnswers(question, 'a')).toBe(false);
    });
    
    it('handles multiple correct answers', () => {
        const question = {
            id: 'q2',
            type: 'multiple-choice',
            correctAnswers: ['a', 'c'],
        };
        
        expect(validateQuizAnswers(question, ['a', 'c'])).toBe(true);
        expect(validateQuizAnswers(question, ['a'])).toBe(false);
    });
});
```

### Component Testing (React Testing Library)
```typescript
// __tests__/QuizBuilder.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import QuizBuilder from '../components/QuizBuilder';

describe('QuizBuilder', () => {
    it('renders quiz title', () => {
        render(<QuizBuilder quiz={{ title: 'Test Quiz' }} />);
        expect(screen.getByText('Test Quiz')).toBeInTheDocument();
    });
    
    it('adds new question on button click', async () => {
        render(<QuizBuilder quiz={{ questions: [] }} />);
        
        fireEvent.click(screen.getByText('Add Question'));
        
        expect(await screen.findByText('Question 1')).toBeInTheDocument();
    });
});
```

### E2E Testing (Playwright)
```typescript
// e2e/quiz-creation.spec.ts
import { test, expect } from '@playwright/test';

test('create and publish quiz', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php?post_type=pp_quiz');
    
    // Fill in quiz details
    await page.fill('#title', 'My Test Quiz');
    await page.click('text=Add Question');
    await page.fill('.pp-question__text', 'What is 2+2?');
    await page.fill('.pp-answer input[type="text"]', '4');
    await page.check('.pp-answer input[type="checkbox"]'); // Mark correct
    
    // Publish
    await page.click('#publish');
    await expect(page.locator('.notice-success')).toContainText('Quiz published');
});
```

---

## Build and Development Workflow

### Development Mode
```bash
# Start development server with HMR
npm run start

# This runs:
# - Webpack dev server
# - Hot Module Replacement for instant updates
# - Source maps for debugging
# - Watch mode for file changes
```

**What happens**:
- Changes to React components update instantly
- Browser auto-refreshes on vanilla JS changes
- CSS updates without full page reload
- TypeScript errors shown in terminal

### Production Build
```bash
# Create optimized production build
npm run build

# This creates:
# - Minified JavaScript bundles
# - Optimized CSS with autoprefixer
# - Source maps (for debugging)
# - Asset manifest for cache busting
```

**Output**:
```
assets/dist/
├── admin-quiz-builder.js        # ~180KB (React + our code)
├── admin-quiz-builder.css       # ~45KB
├── admin-reports.js             # ~120KB (code split)
├── frontend-quiz.js             # ~12KB (vanilla JS)
├── frontend-quiz.css            # ~15KB
└── asset-manifest.json          # For cache busting
```

### Asset Versioning
```php
// Automatic cache busting using file hashes
function enqueue_quiz_assets() {
    $manifest = json_decode(
        file_get_contents(PLUGIN_PATH . '/assets/dist/asset-manifest.json'),
        true
    );
    
    wp_enqueue_script(
        'pp-admin-quiz-builder',
        PLUGIN_URL . '/assets/dist/' . $manifest['admin-quiz-builder.js'],
        ['wp-element', 'wp-components'],
        null, // Version from manifest hash
        true
    );
}
```

---

## AI-Assisted Development Integration

### Claude Code Prompts for Frontend Work

**Creating React Components**:
```
Create a React component for the quiz builder's question editor.

Requirements:
- Use TypeScript with proper types from @/types
- Use @wordpress/components for UI (Button, TextControl, SelectControl)
- Follow the BEM CSS pattern (pp-question-editor)
- Include drag handle for reordering
- Emit onChange event when question updates
- Support all 8 question types defined in types/index.ts
- Add proper ARIA labels for accessibility

Reference: @docs/v1.0/features/001-quiz-builder.md for requirements
```

**Creating Vanilla JS Modules**:
```
Create a vanilla JavaScript module for quiz auto-save functionality.

Requirements:
- Use TypeScript
- Auto-save every 30 seconds or on answer change
- Debounce rapid changes (500ms)
- Show "Saving..." indicator in UI
- Handle API errors gracefully
- Store draft locally as backup (localStorage)
- Restore draft on page reload
- Clear draft after successful submit

Reference: @docs/architecture/frontend-architecture.md for patterns
```

### Code Organization for AI Context

**Use Clear File Names**:
```
✅ Good: quiz-timer-component.ts
❌ Bad: timer.ts

✅ Good: multiple-choice-question-editor.tsx
❌ Bad: mcq.tsx
```

**Document Complex Logic**:
```typescript
/**
 * Calculates quiz score with partial credit for multiple-select questions.
 * 
 * Scoring rules:
 * - Multiple choice (single): 100% or 0%
 * - Multiple choice (multi): Partial credit based on correct selections
 * - Short answer: Case-insensitive exact match
 * - Essay: Manual grading required (returns null)
 * 
 * @param question - Question configuration
 * @param answer - Student's answer(s)
 * @returns Score as percentage (0-100) or null if manual grading needed
 */
export function calculateQuestionScore(
    question: Question,
    answer: Answer
): number | null {
    // Implementation...
}
```

---

## Migration Path for Future Improvements

### v2.0 Potential Enhancements

**Admin Interface**:
- Question bank management (still React)
- Advanced analytics dashboard (consider Chart.js or Recharts)
- Drag-and-drop quiz builder canvas (use React DnD)

**Frontend**:
- Real-time collaborative quiz taking (consider WebSockets)
- Offline quiz support (Service Workers + IndexedDB)
- Progressive Web App features

**Performance**:
- Server-side rendering for quiz pages (WordPress Block Theme integration)
- Edge caching for quiz content
- CDN integration for assets

**These can be added incrementally without major refactoring.**

---

## Key Takeaways

### For Development

1. **Admin = React**: Use modern React patterns, leverage WordPress components
2. **Frontend = Vanilla JS**: Keep it fast and accessible
3. **TypeScript everywhere**: Catches bugs early, helps AI tools
4. **@wordpress/scripts**: Zero-config modern build system
5. **BEM + CSS Variables**: Maintainable, themeable styles

### For AI-Assisted Development

1. **Clear file organization**: Helps AI find the right files
2. **Type definitions**: AI generates better code with TypeScript
3. **Comprehensive documentation**: Reference docs in prompts
4. **Consistent patterns**: Once established, AI follows them

### For Users

1. **Fast quiz taking**: <3 second load times on mobile
2. **Beautiful admin**: Modern, intuitive interface
3. **Accessible**: Works for everyone, including assistive tech users
4. **Reliable**: Progressive enhancement means it works even if JS fails

---

## Questions & Decisions Log

### Q: Why not use a full JavaScript framework (Vue/Angular) for everything?
**A**: React for admin gives us WordPress integration. Vanilla JS for frontend gives us performance. Best of both worlds.

### Q: Should we use TypeScript or just JavaScript with JSDoc?
**A**: TypeScript. AI tools work better with it, catches more bugs, self-documenting.

### Q: What about CSS frameworks like Tailwind?
**A**: No. WordPress has its own design system, and we want a distinctive brand. Custom CSS with variables is more flexible.

### Q: Server-side rendering for quizzes?
**A**: v1.0 is PHP-rendered HTML enhanced with JS. SSR/hydration adds complexity without clear benefit for our use case.

### Q: Bundle size targets?
**A**: Admin: <250KB total (acceptable). Frontend: <20KB total (critical).

---

## Implementation Checklist

### Phase 1: Setup (Week 1)
- [ ] Install @wordpress/scripts
- [ ] Configure TypeScript
- [ ] Set up file structure
- [ ] Create base types
- [ ] Configure build pipeline
- [ ] Set up development workflow

### Phase 2: Admin Interface (Weeks 2-6)
- [ ] Quiz builder shell (React)
- [ ] Question editor components
- [ ] Settings panel
- [ ] Auto-save functionality
- [ ] AI generation integration
- [ ] Reports dashboard

### Phase 3: Frontend (Weeks 7-9)
- [ ] Quiz display (server-rendered)
- [ ] JavaScript enhancements
- [ ] Timer functionality
- [ ] Auto-save drafts
- [ ] Result display
- [ ] Accessibility audit

### Phase 4: Polish (Week 10)
- [ ] Performance optimization
- [ ] Cross-browser testing
- [ ] Accessibility testing
- [ ] Mobile testing
- [ ] Code review
- [ ] Documentation

---

**This architecture provides enterprise-grade quality while remaining maintainable, performant, and friendly to AI-assisted development.**