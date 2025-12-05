# Feature 005: Results & Review

## Overview

After submitting a quiz, students see comprehensive results including their score, category breakdowns, and detailed question review. Teachers can also access student results for analysis.

## User Stories

### As a Student
- I want to see my score immediately after submitting so I know how I did
- I want to see which questions I got right and wrong so I can learn from mistakes
- I want to see feedback explaining why answers are correct so I understand the material
- I want to track my progress over time so I can see improvement
- I want to share my results so I can celebrate achievements

### As a Teacher
- I want to see individual student results so I can help struggling students
- I want to see aggregate performance so I know which topics need attention

## Acceptance Criteria

### Results Page
- [ ] Shows overall score (points and percentage)
- [ ] Shows pass/fail status prominently
- [ ] Shows passing threshold
- [ ] Shows time spent (total)
- [ ] Shows correct/incorrect count
- [ ] Shows category breakdown with per-category percentages
- [ ] Shows confidence calibration (% of confident answers that were correct)
- [ ] Shows comparison to average (if enabled and sufficient data)
- [ ] Shows score-banded feedback message
- [ ] Retake button (if attempts remaining)

### Question Review
- [ ] Lists all questions from the attempt
- [ ] Shows user's selected answer(s)
- [ ] Indicates correct/incorrect per question
- [ ] Shows correct answer (based on quiz setting)
- [ ] Shows per-question feedback
- [ ] Shows per-answer feedback
- [ ] Shows time spent per question
- [ ] Shows confidence indicator (if used)
- [ ] Uses question version from attempt time (not current)

### My Attempts History
- [ ] Lists all completed attempts
- [ ] Shows: Quiz name, Score, Pass/Fail, Date, Duration
- [ ] Can filter by quiz
- [ ] Can filter by date range
- [ ] Can sort by date, score
- [ ] Click to view full results
- [ ] Retake button where applicable
- [ ] Pagination for many attempts

### Sharing
- [ ] Optional social sharing buttons
- [ ] Customizable share message
- [ ] Links return to results page
- [ ] Works for guests (tokenized URL)

### Email Results
- [ ] Option to email results to self
- [ ] Option for automatic email on completion
- [ ] HTML email with summary
- [ ] Link to full results

## Technical Implementation

### Scoring Calculation

```php
function ppq_calculate_results( $attempt_id ) {
    $attempt = PressPrimer_Quiz_Attempt::get( $attempt_id );
    $items = PressPrimer_Quiz_Attempt_Item::get_for_attempt( $attempt_id );
    
    $results = [
        'score_points' => 0,
        'max_points' => 0,
        'correct_count' => 0,
        'total_count' => count( $items ),
        'category_scores' => [],
        'confidence_stats' => [
            'confident_correct' => 0,
            'confident_incorrect' => 0,
            'not_confident_correct' => 0,
            'not_confident_incorrect' => 0,
        ],
    ];
    
    foreach ( $items as $item ) {
        $results['score_points'] += $item->score_points;
        $results['max_points'] += $item->max_points;
        
        if ( $item->is_correct ) {
            $results['correct_count']++;
        }
        
        // Category tracking
        $categories = ppq_get_question_categories( $item->question_id );
        foreach ( $categories as $cat ) {
            if ( ! isset( $results['category_scores'][ $cat->id ] ) ) {
                $results['category_scores'][ $cat->id ] = [
                    'name' => $cat->name,
                    'correct' => 0,
                    'total' => 0,
                ];
            }
            $results['category_scores'][ $cat->id ]['total']++;
            if ( $item->is_correct ) {
                $results['category_scores'][ $cat->id ]['correct']++;
            }
        }
        
        // Confidence tracking
        if ( $item->confidence ) {
            if ( $item->is_correct ) {
                $results['confidence_stats']['confident_correct']++;
            } else {
                $results['confidence_stats']['confident_incorrect']++;
            }
        } else {
            if ( $item->is_correct ) {
                $results['confidence_stats']['not_confident_correct']++;
            } else {
                $results['confidence_stats']['not_confident_incorrect']++;
            }
        }
    }
    
    $results['score_percent'] = $results['max_points'] > 0 
        ? ( $results['score_points'] / $results['max_points'] ) * 100 
        : 0;
    
    return $results;
}
```

### Confidence Calibration

Confidence calibration shows how well-calibrated a student's confidence is:

```
Calibration = (Confident & Correct) / (Confident Total)
```

- 100% = Every confident answer was correct
- 50% = Only half of confident answers were correct (overconfident)
- Display: "You were confident on 15 questions and got 12 correct (80%)"

### Category Breakdown

Calculate per-category percentage:

```
Category Score = (Correct in Category / Total in Category) * 100
```

Display as horizontal bars showing performance per category.

### Average Comparison

Compare to all attempts on this quiz:

```php
function ppq_get_quiz_average( $quiz_id ) {
    global $wpdb;
    
    $avg = $wpdb->get_var( $wpdb->prepare(
        "SELECT AVG(score_percent) 
         FROM {$wpdb->prefix}ppq_attempts 
         WHERE quiz_id = %d AND status = 'submitted'",
        $quiz_id
    ) );
    
    return $avg ? round( $avg, 1 ) : null;
}
```

Only show if at least 5 attempts exist.

## UI/UX Requirements

### Results Summary
```
+----------------------------------+
|  🎉 Quiz Complete!               |
|                                  |
|  ┌────────────────────────────┐  |
|  │  YOUR SCORE                │  |
|  │                            │  |
|  │     85%                    │  |
|  │   17 / 20 correct          │  |
|  │                            │  |
|  │   ✅ PASSED                │  |
|  │   (70% required)           │  |
|  └────────────────────────────┘  |
|                                  |
|  ⏱️ Time: 12:34                  |
|  📊 Average: 78%                 |
|                                  |
|  Feedback:                       |
|  "Excellent work! You've..."     |
|                                  |
|  [Review Answers] [Share] [📧]   |
|  [Retake Quiz]                   |
+----------------------------------+
```

### Category Breakdown
```
Performance by Category:

JavaScript        ████████░░ 80%  (8/10)
HTML              ██████████ 100% (5/5)
CSS               ██████░░░░ 60%  (3/5)
```

### Confidence Calibration
```
Confidence Analysis:
You marked 15 answers as confident.
12 of those were correct (80% calibration).

💡 Your confidence is well-calibrated!
```

### Question Review
```
+----------------------------------+
|  Question 1 of 20         ✅     |
|  Time: 0:45                      |
+----------------------------------+
|  What is the capital of France?  |
|                                  |
|  ○ London                        |
|  ● Paris  ← Your answer ✓        |
|  ○ Berlin                        |
|  ○ Madrid                        |
|                                  |
|  💬 Correct! Paris has been...   |
+----------------------------------+
|  [Previous]  5/20  [Next]        |
+----------------------------------+
```

### My Attempts Page
```
+----------------------------------+
|  My Quiz Attempts                |
|  [Filter: All Quizzes ▼] [Date]  |
+----------------------------------+
|  Quiz              Score  Date   |
|  ─────────────────────────────   |
|  JavaScript Basics  85% ✓ Nov 20 |
|  HTML Fundamentals  92% ✓ Nov 18 |
|  CSS Layout         65% ✗ Nov 15 |
|                       [Retake]   |
+----------------------------------+
```

## Security Requirements

- Only show correct answers based on quiz setting
- Verify user owns attempt before showing results
- Guest results require valid token
- Token expires after 30 days
- Don't expose other users' answers in comparison

## Edge Cases

1. **Zero questions answered** - Show 0% score, still allow review
2. **All questions correct** - Special congratulations message
3. **No categories assigned** - Skip category breakdown section
4. **No confidence used** - Skip confidence section
5. **Quiz deleted** - Still show results, note quiz unavailable
6. **Question edited after attempt** - Show version from attempt time
7. **Guest returns after token expires** - Show login prompt, link if email matches

## Not In Scope (v1.0)

- PDF export of results
- Detailed time analytics charts
- Question-level difficulty feedback
- Comparative analytics with cohort
- Progress charts over time (v2.0)
- Certificate generation

## Testing Checklist

- [ ] Complete quiz and see results
- [ ] Verify score calculation correct
- [ ] Verify pass/fail based on threshold
- [ ] See category breakdown
- [ ] See confidence calibration
- [ ] Review individual questions
- [ ] See feedback on questions
- [ ] See correct answers (when allowed)
- [ ] Share results socially
- [ ] Email results to self
- [ ] View My Attempts page
- [ ] Filter attempts by quiz
- [ ] Sort attempts by score
- [ ] Retake quiz from results
- [ ] Access guest results via token

