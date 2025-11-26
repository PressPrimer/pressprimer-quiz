# Feature 006: AI Question Generation

## Overview

Teachers can use AI to generate quiz questions from text content or uploaded documents. Users provide their own OpenAI API key to directly access the API with no middleware or credit system.

## User Stories

### As a Admin (and later teachers)
- I want to generate questions from my course content so I can quickly create quizzes
- I want to upload a PDF and get questions from it so I can assess reading materials
- I want to specify how many questions to generate so I get the right amount, as well as set difficulty of the questions/distractors
- I want to review and edit generated questions before saving so I maintain quality control
- I want to use my own API key and choose the OpenAI model from available models so I control costs and have no usage limits

## Acceptance Criteria

### API Key Management
- [ ] Can save OpenAI API key in settings
- [ ] Key is encrypted in database
- [ ] Key validation on save (test API call)
- [ ] Clear indicator of key status (valid/invalid/not set)
- [ ] Per-user key storage (not global)
- [ ] Can remove/update key

### Generation Interface
- [ ] Accessible from question bank page
- [ ] Two input modes: paste text OR upload file
- [ ] Supported file types: PDF, DOCX
- [ ] Parameters: question count, types, difficulty, categories
- [ ] Generate button with loading indicator
- [ ] Error handling with clear messages

### Generation Parameters
- [ ] Question count: 1-50 per generation
- [ ] Question types: MC, MA, TF (select multiple)
- [ ] Difficulty: Easy, Medium, Hard (select multiple, so if "Medium" and "Hard" are selected, 50% will be Medium, 50% Hard)
- [ ] Target categories: select from existing
- [ ] Target bank: select destination

### File Processing
- [ ] PDF text extraction
- [ ] Word document text extraction
- [ ] Handle multi-page documents
- [ ] Size limit: 5MB
- [ ] Error if cannot extract text

### Review & Edit
- [ ] Preview generated questions before saving
- [ ] Edit any question (stem, answers, correct answer)
- [ ] Delete unwanted questions
- [ ] Set categories before bulk save
- [ ] Individual add to bank option

### Error Handling
- [ ] API key invalid
- [ ] API rate limit exceeded
- [ ] API temporary error (retry)
- [ ] File too large
- [ ] File type unsupported
- [ ] Cannot extract text from file
- [ ] AI returned invalid format

## Technical Implementation

### API Key Storage

```php
function ppq_save_api_key( $user_id, $api_key ) {
    $encrypted = ppq_encrypt( $api_key );
    return update_user_meta( $user_id, 'ppq_openai_api_key', $encrypted );
}

function ppq_get_api_key( $user_id ) {
    $encrypted = get_user_meta( $user_id, 'ppq_openai_api_key', true );
    if ( ! $encrypted ) {
        return '';
    }
    return ppq_decrypt( $encrypted );
}

function ppq_validate_api_key( $api_key ) {
    $response = wp_remote_get( 'https://api.openai.com/v1/models', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'timeout' => 10,
    ] );
    
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    
    $code = wp_remote_retrieve_response_code( $response );
    
    if ( 401 === $code ) {
        return new WP_Error( 'invalid_key', 'Invalid API key' );
    }
    
    return 200 === $code;
}
```

### Prompt Template

```php
function ppq_build_generation_prompt( $content, $params ) {
    $system = <<<PROMPT
You are an expert educational assessment designer. Create high-quality quiz questions from the provided content.

For each question, output valid JSON in this exact format:
{
  "questions": [
    {
      "type": "mc|ma|tf",
      "stem": "The question text",
      "answers": [
        {"text": "Answer option", "is_correct": true, "feedback": "Why correct"},
        {"text": "Answer option", "is_correct": false, "feedback": "Why incorrect"}
      ],
      "feedback_correct": "General feedback when correct",
      "feedback_incorrect": "General feedback when incorrect"
    }
  ]
}

Requirements:
- Create exactly {$params['count']} questions
- Question types: {$params['types']}
- Difficulty level: {$params['difficulty']}
- For MC: exactly one correct answer, 4 options total
- For MA: 2-4 correct answers, 4-6 options total
- For TF: exactly 2 options (True and False)
- Make distractors plausible but clearly wrong
- Ensure questions test understanding, not just recall
- Include helpful feedback for each answer
PROMPT;

    $user = "Generate questions from this content:\n\n" . $content;
    
    return [
        'system' => $system,
        'user' => $user,
    ];
}
```

### API Call

```php
function ppq_call_openai( $api_key, $prompt ) {
    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'timeout' => 60,
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode( [
            'model' => 'gpt-4',
            'messages' => [
                [ 'role' => 'system', 'content' => $prompt['system'] ],
                [ 'role' => 'user', 'content' => $prompt['user'] ],
            ],
            'temperature' => 0.7,
            'max_tokens' => 4000,
        ] ),
    ] );
    
    if ( is_wp_error( $response ) ) {
        return $response;
    }
    
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    
    if ( isset( $body['error'] ) ) {
        return new WP_Error( 'api_error', $body['error']['message'] );
    }
    
    return $body['choices'][0]['message']['content'];
}
```

### File Text Extraction

```php
function ppq_extract_text_from_file( $file_path, $mime_type ) {
    switch ( $mime_type ) {
        case 'application/pdf':
            return ppq_extract_pdf_text( $file_path );
            
        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            return ppq_extract_docx_text( $file_path );
            
        default:
            return new WP_Error( 'unsupported', 'Unsupported file type' );
    }
}

function ppq_extract_pdf_text( $file_path ) {
    // Use Smalot\PdfParser or similar
    // Fallback: pdftotext command if available
    
    if ( ! class_exists( 'Smalot\\PdfParser\\Parser' ) ) {
        // Try shell command
        $output = shell_exec( 'pdftotext ' . escapeshellarg( $file_path ) . ' -' );
        if ( $output ) {
            return $output;
        }
        return new WP_Error( 'no_parser', 'PDF parsing not available' );
    }
    
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile( $file_path );
    return $pdf->getText();
}

function ppq_extract_docx_text( $file_path ) {
    // Use PhpWord
    if ( ! class_exists( 'PhpOffice\\PhpWord\\IOFactory' ) ) {
        return new WP_Error( 'no_parser', 'DOCX parsing not available' );
    }
    
    $phpWord = \PhpOffice\PhpWord\IOFactory::load( $file_path );
    $text = '';
    
    foreach ( $phpWord->getSections() as $section ) {
        foreach ( $section->getElements() as $element ) {
            if ( method_exists( $element, 'getText' ) ) {
                $text .= $element->getText() . "\n";
            }
        }
    }
    
    return $text;
}
```

### Rate Limiting

```php
function ppq_check_ai_rate_limit( $user_id ) {
    $key = 'ppq_ai_requests_' . $user_id;
    $count = (int) get_transient( $key );
    
    // 20 requests per hour
    if ( $count >= 20 ) {
        return new WP_Error( 
            'rate_limited', 
            'AI generation limit reached. Please wait an hour.' 
        );
    }
    
    set_transient( $key, $count + 1, HOUR_IN_SECONDS );
    
    return true;
}
```

## UI/UX Requirements

### Generation Panel
```
+----------------------------------+
|  Generate Questions with AI      |
+----------------------------------+
|  ○ Paste Text  ● Upload File     |
|                                  |
|  [Choose File...]                |
|  Supported: PDF, DOCX (max 5MB)  |
|                                  |
|  Generate:                       |
|  [10 ▼] questions                |
|                                  |
|  Types:                          |
|  ☑ Multiple Choice               |
|  ☑ Multiple Answer               |
|  ☐ True/False                    |
|                                  |
|  Difficulty: [Medium ▼]          |
|                                  |
|  Add to bank: [Select... ▼]      |
|  Categories: [Select... ▼]       |
|                                  |
|  [Generate Questions]            |
|                                  |
|  Token usage: ~500 tokens        |
+----------------------------------+
```

### Review Panel
```
+----------------------------------+
|  Generated Questions (8)         |
|  [Add All to Bank] [Discard All] |
+----------------------------------+
|  ☑ Question 1                    |
|  What is the primary function... |
|  Type: MC | Difficulty: Medium   |
|  [Edit] [Delete]                 |
|  ─────────────────────────────   |
|  ☑ Question 2                    |
|  Which of the following are...   |
|  Type: MA | Difficulty: Medium   |
|  [Edit] [Delete]                 |
|  ─────────────────────────────   |
|  ...                             |
+----------------------------------+
|  [Add Selected to Bank (6)]      |
+----------------------------------+
```

### Edit Modal
```
+----------------------------------+
|  Edit Question                   |
+----------------------------------+
|  Type: Multiple Choice           |
|                                  |
|  Question:                       |
|  [                          ]    |
|  [                          ]    |
|                                  |
|  Answers:                        |
|  ● [Answer 1            ]        |
|    [Feedback for answer 1   ]    |
|  ○ [Answer 2            ]        |
|    [Feedback for answer 2   ]    |
|  ○ [Answer 3            ]        |
|    [Feedback for answer 3   ]    |
|  ○ [Answer 4            ]        |
|    [Feedback for answer 4   ]    |
|                                  |
|  [Cancel]         [Save Changes] |
+----------------------------------+
```

## Security Requirements

- API key encrypted at rest
- API key never exposed in frontend
- API calls made server-side only
- File uploads validated and sanitized
- Temporary files deleted after processing
- Rate limiting to prevent abuse

## Edge Cases

1. **API key not set** - Show setup prompt with link to settings
2. **API returns fewer questions** - Show what was generated with note
3. **API returns malformed JSON** - Try to parse, show error if fails
4. **Large document** - Truncate to first 10,000 characters with warning
5. **Scanned PDF (no text)** - Error with suggestion to use OCR'd version
6. **Network timeout** - Retry once, then show error

## Not In Scope (v1.0)

- AI distractor generation for existing questions (Educator tier)
- AI quality scoring of questions (Institution tier)
- Multiple AI provider support
- Image/diagram generation
- Voice-to-text for question input

## Testing Checklist

- [ ] Save valid API key
- [ ] Validation fails for invalid key
- [ ] Generate from pasted text
- [ ] Generate from PDF upload
- [ ] Generate from DOCX upload
- [ ] Preview generated questions
- [ ] Edit generated question
- [ ] Delete generated question
- [ ] Add selected to bank
- [ ] Rate limiting triggers after 20 requests
- [ ] Error handling for API failures
- [ ] Error handling for file issues

