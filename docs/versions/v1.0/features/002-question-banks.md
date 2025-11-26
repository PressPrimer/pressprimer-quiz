# Feature 002: Question Banks

## Overview

Question banks are named collections of questions that can be used to build quizzes. In v1.0, banks are personal (private to their owner). Each user can have unlimited banks, and questions can belong to multiple banks.

## User Stories

### As a Teacher
- I want to create question banks by topic so I can organize my content
- I want to add existing questions to a bank so I can group related content
- I want to create quizzes that pull questions from specific banks so I can reuse content
- I want to see which questions are in each bank so I can manage my content

### As an Administrator
- I want to see all question banks so I can monitor content organization
- I want to see usage statistics so I know which banks are most valuable

## Acceptance Criteria

### Bank Management
- [ ] Can create bank with name and description
- [ ] Can edit bank name and description
- [ ] Can delete bank (questions remain, just unlinked)
- [ ] Can see list of all own banks
- [ ] Administrator can see all banks

### Bank Contents
- [ ] Can add questions to bank (one at a time or bulk)
- [ ] Can remove questions from bank (doesn't delete question)
- [ ] Questions can be in multiple banks
- [ ] Can see all questions in a bank
- [ ] Can filter questions within a bank
- [ ] Can search questions within a bank

### Bank Usage
- [ ] Dynamic quizzes can pull from specific banks
- [ ] Quiz builder shows bank selector
- [ ] Bank shows which quizzes use it

## Technical Implementation

### Database Tables

**wp_ppq_banks**
```sql
id BIGINT PRIMARY KEY
uuid CHAR(36) UNIQUE
name VARCHAR(200)
description TEXT
owner_id BIGINT
visibility ENUM('private', 'shared') DEFAULT 'private'
question_count INT DEFAULT 0
created_at DATETIME
updated_at DATETIME
```

**wp_ppq_bank_questions**
```sql
bank_id BIGINT
question_id BIGINT
added_at DATETIME
PRIMARY KEY (bank_id, question_id)
```

### Visibility Rules

In v1.0, all banks are `private`:
- Owner can view and edit
- Administrators can view all banks
- Other teachers cannot see each other's banks

Shared banks (School/Enterprise tier) will allow:
- Institutional sharing with permissions
- Read-only access for other teachers
- Approval workflow for adding questions

### Question Count Maintenance

Update `question_count` when:
- Question added to bank
- Question removed from bank
- Question deleted (remove from all banks)

Use direct SQL update, not model save, for performance:
```php
$wpdb->query( $wpdb->prepare(
    "UPDATE {$wpdb->prefix}ppq_banks 
     SET question_count = (
         SELECT COUNT(*) FROM {$wpdb->prefix}ppq_bank_questions 
         WHERE bank_id = %d
     ) WHERE id = %d",
    $bank_id, $bank_id
) );
```

## UI/UX Requirements

### Bank List (Admin)
- Table with columns: Name, Questions, Owner, Created, Actions
- Row actions: View | Edit | Delete
- "Add New" button prominently placed
- Search by name
- Filter by owner (admin only)

### Bank Editor
- Simple form: Name, Description
- Below form: Questions in this bank
- Questions table with:
  - Checkbox for bulk remove
  - Question text (truncated)
  - Type, Difficulty, Categories
  - Date added to bank
  - Remove action
- "Add Questions" button opens modal

### Add Questions Modal
- Search/filter interface for all user's questions
- Checkboxes to select multiple
- Filter by: Category, Tag, Difficulty, Type
- Search by stem
- Show which questions already in bank
- "Add Selected" button

### Bank Usage Display
- Section showing quizzes that use this bank
- Link to each quiz
- Warning before delete if bank in use

## API Endpoints

### REST API (optional)

```
GET    /wp-json/ppq/v1/banks
POST   /wp-json/ppq/v1/banks
GET    /wp-json/ppq/v1/banks/{id}
PUT    /wp-json/ppq/v1/banks/{id}
DELETE /wp-json/ppq/v1/banks/{id}
GET    /wp-json/ppq/v1/banks/{id}/questions
POST   /wp-json/ppq/v1/banks/{id}/questions
DELETE /wp-json/ppq/v1/banks/{id}/questions/{question_id}
```

### AJAX Endpoints (required)

```
ppq_create_bank
ppq_update_bank
ppq_delete_bank
ppq_add_questions_to_bank
ppq_remove_question_from_bank
ppq_get_bank_questions
```

## Validation Rules

- Name: required, max 200 characters
- Description: optional, max 2000 characters
- Cannot add same question to bank twice
- Cannot add non-existent question
- Cannot add question user doesn't own (unless admin)

## Edge Cases

1. **Delete bank with questions** - Questions remain, just unlinked from bank
2. **Delete bank used by quiz** - Allow with warning, quiz rule becomes invalid
3. **Delete question in bank** - Auto-remove from all banks
4. **Bank with 0 questions** - Allowed, useful for organization
5. **Question in 10+ banks** - No limit on bank memberships
6. **View bank as non-owner** - 403 error (unless admin)

## Not In Scope (v1.0)

- Shared/institutional banks (School tier)
- Bank import/export (Educator tier)
- Bank templates/presets
- Bank duplication
- Bank statistics/analytics

## Testing Checklist

- [ ] Create new bank
- [ ] Edit bank name and description
- [ ] Add single question to bank
- [ ] Add multiple questions at once
- [ ] Remove question from bank
- [ ] Verify question still exists after removal
- [ ] Delete bank and verify questions remain
- [ ] Search questions within bank
- [ ] Filter questions within bank
- [ ] View bank as different user (should fail)
- [ ] View bank as admin (should succeed)
- [ ] Create quiz using bank
- [ ] Verify usage displayed on bank

