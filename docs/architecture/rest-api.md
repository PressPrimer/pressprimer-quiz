# REST API Documentation

**Last Updated**: 2025-01-24  
**API Version**: v1  
**Base URL**: `/wp-json/pressprimer/v1`

---

## Overview

PressPrimer Quiz provides a comprehensive REST API for:
- Quiz management (CRUD operations)
- Question management
- Quiz attempts and submissions
- Results and grading
- Student progress tracking
- AI question generation
- Reports and analytics

All endpoints follow WordPress REST API conventions and use WordPress authentication.

---

## Authentication

### Methods Supported

**1. Cookie Authentication** (Default for logged-in users)

```javascript
// Automatically authenticated if user is logged in to WordPress
fetch('/wp-json/pressprimer/v1/quizzes')
    .then(response => response.json());
```

**2. Application Passwords** (For external applications)

```bash
# Create application password in WordPress profile
# Then use Basic Auth
curl -u "username:app_password" \
  https://example.com/wp-json/pressprimer/v1/quizzes
```

**3. JWT Authentication** (Optional plugin required)

```javascript
// Requires JWT Authentication plugin
const token = 'your_jwt_token';
fetch('/wp-json/pressprimer/v1/quizzes', {
    headers: {
        'Authorization': `Bearer ${token}`
    }
});
```

### Nonces for AJAX Requests

WordPress nonces required for state-changing operations:

**PHP: Localize nonce for JavaScript**

```php
wp_localize_script('pp-admin-script', 'ppQuiz', [
    'nonce' => wp_create_nonce('wp_rest'),
    'apiUrl' => rest_url('pressprimer/v1'),
]);
```

**JavaScript: Include nonce in requests**

```javascript
fetch('/wp-json/pressprimer/v1/quizzes/123', {
    method: 'POST',
    headers: {
        'X-WP-Nonce': ppQuiz.nonce,
        'Content-Type': 'application/json',
    },
    body: JSON.stringify(data)
});
```

---

## Response Format

### Success Response

```json
{
    "success": true,
    "data": {
        "id": 123,
        "title": "Introduction to Physics",
        "...": "..."
    },
    "message": "Quiz saved successfully"
}
```

### Error Response

```json
{
    "success": false,
    "code": "quiz_not_found",
    "message": "The requested quiz does not exist",
    "data": {
        "status": 404
    }
}
```

### Pagination Response

```json
{
    "success": true,
    "data": [...],
    "pagination": {
        "total": 150,
        "per_page": 20,
        "current_page": 1,
        "total_pages": 8,
        "next_page": 2
    }
}
```

---

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `unauthorized` | 401 | User not authenticated |
| `forbidden` | 403 | User lacks permission |
| `quiz_not_found` | 404 | Quiz doesn't exist |
| `invalid_quiz_data` | 400 | Invalid quiz data |
| `invalid_attempt` | 400 | Invalid attempt data |
| `attempt_limit_reached` | 403 | Max attempts exceeded |
| `quiz_not_available` | 403 | Quiz not open for attempts |
| `validation_error` | 400 | Data validation failed |
| `rate_limit_exceeded` | 429 | Too many requests |
| `internal_error` | 500 | Server error |

---

## Quiz Endpoints

### List Quizzes

**GET** `/quizzes`

Get a paginated list of quizzes.

**Query Parameters:**

- `per_page` (int) - Results per page (default: 20, max: 100)
- `page` (int) - Page number (default: 1)
- `search` (string) - Search quiz titles
- `status` (string) - Filter by status: 'publish', 'draft', 'private'
- `author` (int) - Filter by author ID
- `orderby` (string) - Sort field: 'date', 'title', 'modified'
- `order` (string) - Sort direction: 'asc', 'desc'

**Permissions:** 
- Public: Returns published quizzes only
- Teachers: Returns own quizzes (all statuses)
- Admins: Returns all quizzes

**Example Request:**

```bash
GET /wp-json/pressprimer/v1/quizzes?per_page=10&status=publish&search=physics
```

**Example Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 123,
            "title": "Introduction to Physics",
            "slug": "introduction-to-physics",
            "status": "publish",
            "author": {
                "id": 5,
                "name": "John Doe",
                "avatar": "https://..."
            },
            "date_created": "2025-01-15T10:30:00",
            "date_modified": "2025-01-20T14:15:00",
            "question_count": 15,
            "attempt_count": 234,
            "average_score": 78.5,
            "settings": {
                "time_limit": 3600,
                "passing_score": 70,
                "max_attempts": 3
            },
            "permalink": "https://example.com/quiz/introduction-to-physics"
        }
    ],
    "pagination": {
        "total": 45,
        "per_page": 10,
        "current_page": 1,
        "total_pages": 5
    }
}
```

---

### Get Single Quiz

**GET** `/quizzes/:id`

Get complete quiz data including questions.

**Path Parameters:**

- `id` (int) - Quiz ID

**Query Parameters:**

- `include_questions` (bool) - Include full question data (default: true)
- `include_answers` (bool) - Include correct answers (default: false, true for teachers/admins)

**Permissions:**
- Public: Published quizzes only (no correct answers)
- Teachers: Own quizzes (all statuses, includes correct answers)
- Admins: All quizzes (includes correct answers)

**Example Request:**

```bash
GET /wp-json/pressprimer/v1/quizzes/123?include_questions=true
```

**Example Response:**

```json
{
    "success": true,
    "data": {
        "id": 123,
        "title": "Introduction to Physics",
        "description": "Test your knowledge of basic physics concepts",
        "status": "publish",
        "author": {
            "id": 5,
            "name": "John Doe"
        },
        "date_created": "2025-01-15T10:30:00",
        "date_modified": "2025-01-20T14:15:00",
        "settings": {
            "time_limit": 3600,
            "time_limit_enabled": true,
            "passing_score": 70,
            "max_attempts": 3,
            "randomize_questions": false,
            "randomize_answers": true,
            "show_results_immediately": true,
            "show_correct_answers": true,
            "require_login": true,
            "scheduled_start": null,
            "scheduled_end": null,
            "password_protected": false
        },
        "questions": [
            {
                "id": "q1",
                "type": "multiple-choice",
                "order": 1,
                "text": "What is the speed of light?",
                "points": 10,
                "explanation": "The speed of light in vacuum is exactly 299,792,458 m/s",
                "media": {
                    "type": "image",
                    "url": "https://...",
                    "alt": "Speed of light diagram"
                },
                "settings": {
                    "randomize_answers": true,
                    "partial_credit": false
                },
                "answers": [
                    {
                        "id": "a1",
                        "text": "299,792,458 m/s",
                        "correct": true
                    },
                    {
                        "id": "a2",
                        "text": "300,000,000 m/s",
                        "correct": false
                    }
                ]
            }
        ],
        "statistics": {
            "total_attempts": 234,
            "average_score": 78.5,
            "pass_rate": 72.5,
            "average_time": 1845
        }
    }
}
```

---

### Create Quiz

**POST** `/quizzes`

Create a new quiz.

**Permissions:** Teachers and Admins only

**Request Body:**

```json
{
    "title": "New Quiz Title",
    "description": "Quiz description",
    "status": "draft",
    "settings": {
        "time_limit": 3600,
        "passing_score": 70,
        "max_attempts": 3
    },
    "questions": []
}
```

**Example Response:**

```json
{
    "success": true,
    "data": {
        "id": 456,
        "title": "New Quiz Title",
        "status": "draft",
        "...": "..."
    },
    "message": "Quiz created successfully"
}
```

---

### Update Quiz

**POST** `/quizzes/:id`

Update existing quiz.

**Permissions:** 
- Teachers: Can update own quizzes
- Admins: Can update any quiz

**Request Body:**

```json
{
    "title": "Updated Quiz Title",
    "status": "publish",
    "settings": {
        "time_limit": 7200
    },
    "questions": [
        {
            "id": "q1",
            "text": "Updated question text",
            "answers": [...]
        }
    ]
}
```

**Auto-save Support:**

The endpoint supports partial updates for auto-save functionality:

```javascript
// Auto-save just title
POST /quizzes/123
{
    "title": "Updated Title",
    "_autosave": true
}

// Auto-save single question
POST /quizzes/123
{
    "questions": [
        {
            "id": "q1",
            "text": "Updated question"
        }
    ],
    "_autosave": true
}
```

**Example Response:**

```json
{
    "success": true,
    "data": {
        "id": 123,
        "title": "Updated Quiz Title",
        "...": "..."
    },
    "message": "Quiz updated successfully"
}
```

---

### Delete Quiz

**DELETE** `/quizzes/:id`

Delete a quiz (moves to trash, or permanently deletes if already trashed).

**Query Parameters:**

- `force` (bool) - Skip trash and permanently delete (default: false)

**Permissions:**
- Teachers: Can delete own quizzes
- Admins: Can delete any quiz

**Example Request:**

```bash
DELETE /wp-json/pressprimer/v1/quizzes/123
```

**Example Response:**

```json
{
    "success": true,
    "message": "Quiz moved to trash",
    "data": {
        "id": 123,
        "status": "trash"
    }
}
```

---

### Duplicate Quiz

**POST** `/quizzes/:id/duplicate`

Create a copy of an existing quiz.

**Permissions:** Teachers and Admins

**Example Request:**

```bash
POST /wp-json/pressprimer/v1/quizzes/123/duplicate
```

**Example Response:**

```json
{
    "success": true,
    "data": {
        "id": 789,
        "title": "Introduction to Physics (Copy)",
        "status": "draft",
        "...": "..."
    },
    "message": "Quiz duplicated successfully"
}
```

---

## Question Endpoints

### Add Question

**POST** `/quizzes/:id/questions`

Add a new question to a quiz.

**Permissions:** Quiz owner or Admin

**Request Body:**

```json
{
    "type": "multiple-choice",
    "text": "What is the capital of France?",
    "points": 10,
    "answers": [
        {
            "text": "Paris",
            "correct": true
        },
        {
            "text": "London",
            "correct": false
        }
    ],
    "explanation": "Paris is the capital and largest city of France.",
    "order": 5
}
```

**Example Response:**

```json
{
    "success": true,
    "data": {
        "id": "q15",
        "type": "multiple-choice",
        "text": "What is the capital of France?",
        "...": "..."
    },
    "message": "Question added successfully"
}
```

---

### Update Question

**POST** `/quizzes/:quiz_id/questions/:question_id`

Update an existing question.

**Permissions:** Quiz owner or Admin

**Request Body:**

```json
{
    "text": "Updated question text",
    "points": 15,
    "answers": [...]
}
```

---

### Delete Question

**DELETE** `/quizzes/:quiz_id/questions/:question_id`

Delete a question from a quiz.

**Permissions:** Quiz owner or Admin

**Example Request:**

```bash
DELETE /wp-json/pressprimer/v1/quizzes/123/questions/q5
```

---

### Reorder Questions

**POST** `/quizzes/:id/questions/reorder`

Update the order of questions.

**Permissions:** Quiz owner or Admin

**Request Body:**

```json
{
    "order": ["q3", "q1", "q5", "q2", "q4"]
}
```

**Example Response:**

```json
{
    "success": true,
    "message": "Questions reordered successfully"
}
```

---

## Quiz Attempt Endpoints

### Start Attempt

**POST** `/quizzes/:id/attempt/start`

Start a new quiz attempt.

**Permissions:** Authenticated users (if quiz requires login) or public

**Request Body:**

```json
{
    "password": "quiz_password"
}
```

Note: Password is optional, only if quiz is password-protected

**Validation:**
- Checks if user has remaining attempts
- Checks if quiz is available (scheduled start/end)
- Validates password if required

**Example Response:**

```json
{
    "success": true,
    "data": {
        "attempt_id": "att_abc123",
        "quiz_id": 123,
        "started_at": "2025-01-24T15:30:00",
        "time_limit": 3600,
        "expires_at": "2025-01-24T16:30:00",
        "questions": [
            {
                "id": "q1",
                "type": "multiple-choice",
                "text": "What is the speed of light?",
                "answers": [
                    {
                        "id": "a1",
                        "text": "299,792,458 m/s"
                    },
                    {
                        "id": "a2",
                        "text": "300,000,000 m/s"
                    }
                ]
            }
        ]
    }
}
```

Note: Correct answers are not included in the response

---

### Auto-save Attempt

**POST** `/quizzes/:id/attempt/:attempt_id/save`

Auto-save current answers without submitting.

**Permissions:** Attempt owner

**Request Body:**

```json
{
    "answers": {
        "q1": "a1",
        "q2": ["b1", "b3"],
        "q3": "Short answer text"
    }
}
```

**Example Response:**

```json
{
    "success": true,
    "message": "Progress saved",
    "data": {
        "saved_at": "2025-01-24T15:45:00"
    }
}
```

---

### Submit Attempt

**POST** `/quizzes/:id/attempt/:attempt_id/submit`

Submit quiz attempt for grading.

**Permissions:** Attempt owner

**Request Body:**

```json
{
    "answers": {
        "q1": "a1",
        "q2": ["b1", "b3"],
        "q3": "Short answer text",
        "q4": "Essay response here..."
    }
}
```

**Processing:**
1. Validates all answers
2. Grades automatically gradable questions
3. Calculates score
4. Marks essay questions for manual grading

**Example Response:**

```json
{
    "success": true,
    "data": {
        "attempt_id": "att_abc123",
        "quiz_id": 123,
        "submitted_at": "2025-01-24T16:15:00",
        "time_taken": 2700,
        "score": 85,
        "percentage": 85,
        "passed": true,
        "total_points": 100,
        "earned_points": 85,
        "remaining_attempts": 2,
        "requires_grading": false,
        "results": {
            "q1": {
                "correct": true,
                "points_earned": 10,
                "points_possible": 10,
                "user_answer": "a1",
                "correct_answer": "a1",
                "explanation": "Correct! The speed of light is..."
            },
            "q2": {
                "correct": true,
                "points_earned": 15,
                "points_possible": 15,
                "user_answer": ["b1", "b3"],
                "correct_answer": ["b1", "b3"]
            }
        }
    },
    "message": "Quiz submitted successfully"
}
```

---

### Get Attempt Results

**GET** `/quizzes/:id/attempt/:attempt_id/results`

Get results for a specific attempt.

**Permissions:** 
- Attempt owner: Can view own results
- Teachers/Admins: Can view any results

**Query Parameters:**

- `include_answers` (bool) - Include correct/incorrect answers (default: true)

**Example Response:**

```json
{
    "success": true,
    "data": {
        "attempt_id": "att_abc123",
        "quiz": {
            "id": 123,
            "title": "Introduction to Physics"
        },
        "student": {
            "id": 45,
            "name": "Jane Student",
            "email": "jane@example.com"
        },
        "submitted_at": "2025-01-24T16:15:00",
        "time_taken": 2700,
        "score": 85,
        "percentage": 85,
        "passed": true,
        "results": {}
    }
}
```

---

### List Student Attempts

**GET** `/quizzes/:id/attempts`

Get all attempts for a quiz (for current user or specific student).

**Permissions:**
- Students: See own attempts only
- Teachers: See attempts for students in their groups
- Admins: See all attempts

**Query Parameters:**

- `student_id` (int) - Filter by student (teachers/admins only)
- `status` (string) - Filter by status: 'in-progress', 'completed'
- `per_page` (int) - Results per page (default: 20)
- `page` (int) - Page number

**Example Request:**

```bash
GET /wp-json/pressprimer/v1/quizzes/123/attempts?student_id=45
```

**Example Response:**

```json
{
    "success": true,
    "data": [
        {
            "attempt_id": "att_abc123",
            "quiz_id": 123,
            "student_id": 45,
            "started_at": "2025-01-24T15:30:00",
            "submitted_at": "2025-01-24T16:15:00",
            "status": "completed",
            "score": 85,
            "percentage": 85,
            "passed": true
        },
        {
            "attempt_id": "att_def456",
            "quiz_id": 123,
            "student_id": 45,
            "started_at": "2025-01-20T10:00:00",
            "submitted_at": "2025-01-20T10:45:00",
            "status": "completed",
            "score": 72,
            "percentage": 72,
            "passed": true
        }
    ],
    "pagination": {
        "total": 3,
        "per_page": 20,
        "current_page": 1,
        "total_pages": 1
    }
}
```

---

## Grading Endpoints

### List Pending Grades

**GET** `/grading/pending`

Get list of attempts requiring manual grading.

**Permissions:** Teachers and Admins only

**Query Parameters:**

- `quiz_id` (int) - Filter by quiz
- `student_id` (int) - Filter by student
- `per_page` (int) - Results per page
- `page` (int) - Page number

**Example Response:**

```json
{
    "success": true,
    "data": [
        {
            "attempt_id": "att_xyz789",
            "quiz": {
                "id": 123,
                "title": "Introduction to Physics"
            },
            "student": {
                "id": 45,
                "name": "Jane Student"
            },
            "submitted_at": "2025-01-24T16:15:00",
            "pending_questions": ["q4", "q7"],
            "auto_graded_score": 70,
            "auto_graded_percentage": 70
        }
    ]
}
```

---

### Grade Question

**POST** `/grading/attempt/:attempt_id/question/:question_id`

Manually grade a question (typically essay questions).

**Permissions:** Teachers and Admins only

**Request Body:**

```json
{
    "points_earned": 8,
    "feedback": "Good answer, but could include more detail about Newton's laws."
}
```

**Example Response:**

```json
{
    "success": true,
    "data": {
        "attempt_id": "att_xyz789",
        "question_id": "q4",
        "points_earned": 8,
        "points_possible": 10,
        "feedback": "Good answer, but could include more detail...",
        "graded_by": {
            "id": 5,
            "name": "John Teacher"
        },
        "graded_at": "2025-01-25T09:30:00"
    },
    "message": "Question graded successfully"
}
```

---

### Complete Grading

**POST** `/grading/attempt/:attempt_id/complete`

Mark grading as complete and notify student.

**Permissions:** Teachers and Admins only

**Request Body:**

```json
{
    "overall_feedback": "Great job! You show a strong understanding of the material.",
    "notify_student": true
}
```

**Example Response:**

```json
{
    "success": true,
    "data": {
        "attempt_id": "att_xyz789",
        "final_score": 88,
        "final_percentage": 88,
        "passed": true,
        "grading_completed_at": "2025-01-25T09:45:00"
    },
    "message": "Grading completed and student notified"
}
```

---

## AI Question Generation Endpoints

### Generate Questions

**POST** `/ai/generate-questions`

Generate questions using AI (OpenAI).

**Permissions:** Teachers and Admins only

**Request Body:**

```json
{
    "topic": "Newton's Laws of Motion",
    "count": 5,
    "difficulty": "medium",
    "question_types": ["multiple-choice", "short-answer"],
    "api_key": "user_openai_api_key"
}
```

**Parameters:**
- `topic` (string, required) - Topic to generate questions about
- `count` (int, required) - Number of questions (1-20)
- `difficulty` (string) - "easy", "medium", "hard"
- `question_types` (array) - Question types to generate
- `api_key` (string, required) - User's OpenAI API key

**Processing:**
1. Validates API key
2. Sends request to OpenAI API
3. Parses response into question format
4. Returns structured questions

**Example Response:**

```json
{
    "success": true,
    "data": {
        "questions": [
            {
                "type": "multiple-choice",
                "text": "What is Newton's First Law of Motion?",
                "points": 10,
                "answers": [
                    {
                        "text": "An object at rest stays at rest unless acted upon by a force",
                        "correct": true
                    },
                    {
                        "text": "Force equals mass times acceleration",
                        "correct": false
                    },
                    {
                        "text": "For every action there is an equal and opposite reaction",
                        "correct": false
                    }
                ],
                "explanation": "Newton's First Law, also known as the Law of Inertia..."
            }
        ],
        "usage": {
            "tokens": 850,
            "cost_estimate": 0.02
        }
    },
    "message": "5 questions generated successfully"
}
```

**Error Responses:**

```json
{
    "success": false,
    "code": "invalid_api_key",
    "message": "The provided OpenAI API key is invalid",
    "data": {
        "status": 401
    }
}
```

```json
{
    "success": false,
    "code": "rate_limit_exceeded",
    "message": "OpenAI rate limit exceeded. Please try again later.",
    "data": {
        "status": 429,
        "retry_after": 60
    }
}
```

---

## Group Management Endpoints

### List Groups

**GET** `/groups`

Get list of groups.

**Permissions:** Teachers and Admins

**Example Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 10,
            "name": "Physics 101 - Section A",
            "description": "Morning class",
            "teacher_id": 5,
            "student_count": 25,
            "created_at": "2025-01-15T10:00:00"
        }
    ]
}
```

---

### Add Students to Group

**POST** `/groups/:id/students`

Add students to a group.

**Permissions:** Group teacher or Admin

**Request Body:**

```json
{
    "student_ids": [45, 67, 89]
}
```

---

### Assign Quiz to Group

**POST** `/groups/:id/quizzes`

Assign a quiz to a group.

**Permissions:** Group teacher or Admin

**Request Body:**

```json
{
    "quiz_id": 123,
    "available_from": "2025-01-25T00:00:00",
    "available_until": "2025-02-01T23:59:59"
}
```

---

## Analytics & Reports Endpoints

### Quiz Statistics

**GET** `/quizzes/:id/statistics`

Get comprehensive statistics for a quiz.

**Permissions:** Quiz owner or Admin

**Example Response:**

```json
{
    "success": true,
    "data": {
        "quiz_id": 123,
        "total_attempts": 234,
        "unique_students": 89,
        "average_score": 78.5,
        "median_score": 82,
        "pass_rate": 72.5,
        "average_time": 1845,
        "question_statistics": [
            {
                "question_id": "q1",
                "text": "What is the speed of light?",
                "times_answered": 234,
                "times_correct": 198,
                "difficulty": 0.85,
                "average_time": 45
            }
        ],
        "score_distribution": {
            "0-20": 5,
            "21-40": 12,
            "41-60": 28,
            "61-80": 85,
            "81-100": 104
        }
    }
}
```

---

### Student Progress Report

**GET** `/students/:id/progress`

Get progress report for a student.

**Permissions:** 
- Students: Own progress only
- Teachers: Students in their groups
- Admins: Any student

**Query Parameters:**

- `quiz_id` (int) - Filter by specific quiz
- `from_date` (date) - Start date
- `to_date` (date) - End date

**Example Response:**

```json
{
    "success": true,
    "data": {
        "student": {
            "id": 45,
            "name": "Jane Student"
        },
        "summary": {
            "quizzes_completed": 15,
            "average_score": 82.3,
            "total_time_spent": 12540,
            "improvement_trend": "+5.2%"
        },
        "quiz_attempts": [
            {
                "quiz_id": 123,
                "quiz_title": "Introduction to Physics",
                "attempts": 2,
                "best_score": 85,
                "latest_attempt": "2025-01-24T16:15:00"
            }
        ]
    }
}
```

---

## Rate Limiting

To prevent abuse, the API implements rate limiting:

### Limits by Endpoint Type

| Endpoint Type | Limit | Window |
|---------------|-------|--------|
| GET requests | 300 requests | 5 minutes |
| POST/PUT/DELETE | 60 requests | 5 minutes |
| AI Generation | 10 requests | 1 hour |
| Quiz Submissions | 100 requests | 1 hour |

### Rate Limit Headers

All responses include rate limit information:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1706112000
```

### Rate Limit Exceeded Response

```json
{
    "success": false,
    "code": "rate_limit_exceeded",
    "message": "Rate limit exceeded. Please try again in 120 seconds.",
    "data": {
        "status": 429,
        "retry_after": 120,
        "limit": 60,
        "window": "5 minutes"
    }
}
```

---

## Webhook Support (Future v2.0)

Planned webhooks for:
- Quiz completion
- Grading completion
- Student enrollment
- Score thresholds reached

---

## API Versioning

### Current Version: v1

Base URL: `/wp-json/pressprimer/v1`

### Version Support Policy

- Current version (v1): Fully supported
- Previous version: Supported for 6 months after new version release
- Deprecated endpoints: 3 months notice before removal

### Breaking Changes

Will trigger new version:
- Removing endpoints
- Changing required parameters
- Changing response structure
- Changing authentication requirements

Will NOT trigger new version:
- Adding new endpoints
- Adding optional parameters
- Adding fields to responses
- Bug fixes

---

## Code Examples

### JavaScript (Admin Interface)

```javascript
// Using WordPress's apiFetch
import apiFetch from '@wordpress/api-fetch';

// Get quiz
const quiz = await apiFetch({
    path: '/pressprimer/v1/quizzes/123',
});

// Save quiz
const updated = await apiFetch({
    path: '/pressprimer/v1/quizzes/123',
    method: 'POST',
    data: {
        title: 'Updated Title',
        settings: {
            time_limit: 7200
        }
    }
});

// Handle errors
try {
    await apiFetch({
        path: '/pressprimer/v1/quizzes/123/attempt/start',
        method: 'POST',
    });
} catch (error) {
    if (error.code === 'attempt_limit_reached') {
        alert('No attempts remaining');
    }
}
```

### JavaScript (Frontend - Vanilla)

```javascript
// Submit quiz
async function submitQuiz(quizId, answers) {
    const response = await fetch(`/wp-json/pressprimer/v1/quizzes/${quizId}/attempt/submit`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': ppQuiz.nonce
        },
        body: JSON.stringify({ answers })
    });
    
    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message);
    }
    
    return await response.json();
}

// Usage
try {
    const result = await submitQuiz(123, {
        'q1': 'a1',
        'q2': ['b1', 'b3']
    });
    
    console.log(`Score: ${result.data.percentage}%`);
    window.location.href = `/quiz-results/${result.data.attempt_id}`;
} catch (error) {
    alert(error.message);
}
```

### PHP (Server-side)

```php
// Make internal API request
$request = new WP_REST_Request('POST', '/pressprimer/v1/quizzes/123');
$request->set_param('title', 'Updated Title');

$response = rest_do_request($request);
$data = $response->get_data();

if ($response->is_error()) {
    error_log('API Error: ' . $data['message']);
}
```

### cURL (External Applications)

```bash
# Get quiz (using application password)
curl -u "username:app_password" \
  "https://example.com/wp-json/pressprimer/v1/quizzes/123"

# Create quiz
curl -u "username:app_password" \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"title":"New Quiz","status":"draft"}' \
  "https://example.com/wp-json/pressprimer/v1/quizzes"
```

---

## Testing the API

### WordPress REST API Test Tools

**1. Built-in WordPress Tool:**

Visit: `https://yoursite.com/wp-json/pressprimer/v1`

**2. REST API Handbook:**

Test endpoints using the WordPress REST API Handbook's testing guide.

**3. Postman Collection:**

Import our Postman collection for easy testing (provided with plugin).

**4. WP-CLI:**

```bash
# Test endpoint
wp rest pressprimer/v1/quizzes get --user=admin
```

---

## Security Best Practices

### For API Consumers

**1. Never expose API keys in frontend code**

```javascript
// ❌ Bad
const apiKey = 'sk-1234567890abcdef';

// ✅ Good - API key handled server-side
const response = await fetch('/generate-questions', {
    method: 'POST',
    body: JSON.stringify({ topic: 'Physics' })
});
```

**2. Always validate responses**

```javascript
const response = await fetch('/api/endpoint');
if (!response.ok) {
    // Handle error
}
const data = await response.json();
// Validate data structure before use
```

**3. Use HTTPS only**

- Never send credentials over HTTP
- Enforce HTTPS in production

**4. Rotate application passwords regularly**

- Generate new passwords every 90 days
- Revoke unused passwords

---

## Migration & Compatibility

### Migrating from Custom Endpoints

If migrating from custom implementation:

```javascript
// Old custom endpoint
const response = await fetch('/wp-admin/admin-ajax.php?action=submit_quiz', {
    method: 'POST',
    body: formData
});

// New REST API endpoint
const response = await fetch('/wp-json/pressprimer/v1/quizzes/123/attempt/submit', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': ppQuiz.nonce
    },
    body: JSON.stringify({ answers })
});
```

### Backward Compatibility

v1.0 maintains compatibility with WordPress 6.0+. Future versions will provide:
- Migration guides
- Compatibility layers
- Deprecation warnings

---

## Support & Resources

- **API Documentation**: https://pressprimer.com/docs/api
- **GitHub Issues**: https://github.com/pressprimer/quiz/issues
- **Support Forum**: https://wordpress.org/support/plugin/pressprimer-quiz
- **Developer Slack**: https://pressprimer.com/slack

---

**This REST API provides a complete, secure, and performant interface for all quiz operations, designed for both internal WordPress use and external integrations.**
