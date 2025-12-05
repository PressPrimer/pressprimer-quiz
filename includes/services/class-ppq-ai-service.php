<?php
/**
 * AI Service
 *
 * Handles AI-powered question generation using OpenAI API.
 *
 * @package PressPrimer_Quiz
 * @subpackage Services
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Service class
 *
 * Provides AI question generation functionality with user-provided API keys.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_AI_Service {

	/**
	 * OpenAI API base URL
	 *
	 * @var string
	 */
	const API_BASE_URL = 'https://api.openai.com/v1';

	/**
	 * Default model to use
	 *
	 * @var string
	 */
	const DEFAULT_MODEL = 'gpt-4';

	/**
	 * Maximum content length for generation (100,000 characters)
	 *
	 * @var int
	 */
	const MAX_CONTENT_LENGTH = 100000;

	/**
	 * Rate limit per hour
	 *
	 * @var int
	 */
	const RATE_LIMIT_PER_HOUR = 30;

	/**
	 * API timeout in seconds
	 *
	 * Extended timeout to handle large content and slow responses.
	 * OpenAI can take several minutes for complex generation tasks.
	 *
	 * @var int
	 */
	const API_TIMEOUT = 300;

	/**
	 * Maximum retry attempts for transient failures
	 *
	 * @var int
	 */
	const MAX_RETRIES = 2;

	/**
	 * Delay between retries in seconds
	 *
	 * @var int
	 */
	const RETRY_DELAY = 5;

	/**
	 * API key for current request
	 *
	 * @var string
	 */
	private $api_key = '';

	/**
	 * Model to use for generation
	 *
	 * @var string
	 */
	private $model = '';

	/**
	 * Last API response
	 *
	 * @var array
	 */
	private $last_response = [];

	/**
	 * Token usage from last request
	 *
	 * @var array
	 */
	private $last_token_usage = [];

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key Optional API key to use.
	 */
	public function __construct( $api_key = '' ) {
		if ( $api_key ) {
			$this->api_key = $api_key;
		}
		$this->model = self::DEFAULT_MODEL;
	}

	/**
	 * Set API key
	 *
	 * Sets the API key for the current instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key OpenAI API key.
	 * @return self
	 */
	public function set_api_key( $api_key ) {
		$this->api_key = sanitize_text_field( $api_key );
		return $this;
	}

	/**
	 * Set model
	 *
	 * Sets the OpenAI model to use for generation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model Model identifier.
	 * @return self
	 */
	public function set_model( $model ) {
		$this->model = sanitize_text_field( $model );
		return $this;
	}

	/**
	 * Save API key for user
	 *
	 * Encrypts and stores the API key in user meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $api_key API key to save.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function save_api_key( $user_id, $api_key ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return new WP_Error(
				'ppq_invalid_user',
				__( 'Invalid user ID.', 'pressprimer-quiz' )
			);
		}

		// Allow clearing the key
		if ( empty( $api_key ) ) {
			delete_user_meta( $user_id, 'ppq_openai_api_key' );
			delete_user_meta( $user_id, 'ppq_openai_model' );
			return true;
		}

		// Encrypt the key
		$encrypted = PressPrimer_Quiz_Helpers::encrypt( $api_key );

		if ( is_wp_error( $encrypted ) ) {
			return $encrypted;
		}

		$result = update_user_meta( $user_id, 'ppq_openai_api_key', $encrypted );

		return false !== $result;
	}

	/**
	 * Get API key for user
	 *
	 * Retrieves and decrypts the API key from user meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return string|WP_Error Decrypted API key or WP_Error.
	 */
	public static function get_api_key( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return '';
		}

		$encrypted = get_user_meta( $user_id, 'ppq_openai_api_key', true );

		if ( empty( $encrypted ) ) {
			return '';
		}

		$decrypted = PressPrimer_Quiz_Helpers::decrypt( $encrypted );

		if ( is_wp_error( $decrypted ) ) {
			return $decrypted;
		}

		return $decrypted;
	}

	/**
	 * Save model preference for user
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id User ID.
	 * @param string $model   Model identifier.
	 * @return bool True on success.
	 */
	public static function save_model_preference( $user_id, $model ) {
		$user_id = absint( $user_id );
		$model   = sanitize_text_field( $model );

		if ( ! $user_id ) {
			return false;
		}

		return false !== update_user_meta( $user_id, 'ppq_openai_model', $model );
	}

	/**
	 * Get model preference for user
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return string Model identifier.
	 */
	public static function get_model_preference( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return self::DEFAULT_MODEL;
		}

		$model = get_user_meta( $user_id, 'ppq_openai_model', true );

		return $model ? $model : self::DEFAULT_MODEL;
	}

	/**
	 * Validate API key
	 *
	 * Tests if the API key is valid by making a request to the models endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key API key to validate.
	 * @return bool|WP_Error True if valid, WP_Error if invalid.
	 */
	public static function validate_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'ppq_empty_key',
				__( 'API key cannot be empty.', 'pressprimer-quiz' )
			);
		}

		$response = wp_remote_get(
			self::API_BASE_URL . '/models',
			[
				'timeout' => 15,
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'ppq_api_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to connect to OpenAI: %s', 'pressprimer-quiz' ),
					$response->get_error_message()
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $code ) {
			return new WP_Error(
				'ppq_invalid_key',
				__( 'Invalid API key. Please check your key and try again.', 'pressprimer-quiz' )
			);
		}

		if ( 429 === $code ) {
			return new WP_Error(
				'ppq_rate_limited',
				__( 'API rate limit exceeded. Please try again later.', 'pressprimer-quiz' )
			);
		}

		if ( 200 !== $code ) {
			$body    = json_decode( wp_remote_retrieve_body( $response ), true );
			$message = isset( $body['error']['message'] )
				? $body['error']['message']
				: __( 'Unknown error occurred.', 'pressprimer-quiz' );

			return new WP_Error( 'ppq_api_error', $message );
		}

		return true;
	}

	/**
	 * Get available models
	 *
	 * Fetches list of available models from OpenAI.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key API key to use.
	 * @return array|WP_Error Array of models or WP_Error.
	 */
	public static function get_available_models( $api_key ) {
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'ppq_no_key',
				__( 'API key is required.', 'pressprimer-quiz' )
			);
		}

		$response = wp_remote_get(
			self::API_BASE_URL . '/models',
			[
				'timeout' => 15,
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new WP_Error(
				'ppq_api_error',
				__( 'Failed to fetch models.', 'pressprimer-quiz' )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
			return new WP_Error(
				'ppq_invalid_response',
				__( 'Invalid response from OpenAI.', 'pressprimer-quiz' )
			);
		}

		// Filter to only relevant text/chat GPT models
		$gpt_models = [];
		foreach ( $body['data'] as $model ) {
			if ( ! isset( $model['id'] ) ) {
				continue;
			}

			$id = $model['id'];

			// Must start with gpt-
			if ( strpos( $id, 'gpt-' ) !== 0 ) {
				continue;
			}

			// Skip old model generations (3.5, 4, 4o, 4.5, etc.)
			if ( preg_match( '/^gpt-(3|4)/', $id ) ) {
				continue;
			}

			// Skip dated/snapshot versions (contain date patterns like -2024, -2025, -0125, etc.)
			if ( preg_match( '/-\d{4}/', $id ) || preg_match( '/-\d{6}/', $id ) ) {
				continue;
			}

			// Skip non-text models (audio, image, vision-specific, realtime, transcription, search, chat-specific)
			if ( preg_match( '/(audio|image|vision|realtime|transcribe|tts|whisper|dall|search|chatgpt)/i', $id ) ) {
				continue;
			}

			// Skip preview/experimental versions
			if ( preg_match( '/(preview|experimental)/i', $id ) ) {
				continue;
			}

			// Skip instruct-only variants (we want chat models)
			if ( preg_match( '/-instruct$/i', $id ) ) {
				continue;
			}

			$gpt_models[] = $id;
		}

		// Remove duplicates and sort
		$gpt_models = array_unique( $gpt_models );
		sort( $gpt_models );

		// Limit to reasonable number (max 10)
		if ( count( $gpt_models ) > 10 ) {
			$gpt_models = array_slice( $gpt_models, 0, 10 );
		}

		return $gpt_models;
	}

	/**
	 * Check rate limit
	 *
	 * Checks if user has exceeded the rate limit for AI generation.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return bool|WP_Error True if within limits, WP_Error if exceeded.
	 */
	public static function check_rate_limit( $user_id ) {
		$user_id = absint( $user_id );
		$key     = 'ppq_ai_requests_' . $user_id;
		$count   = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT_PER_HOUR ) {
			return new WP_Error(
				'ppq_rate_limited',
				sprintf(
					/* translators: %d: rate limit */
					__( 'AI generation limit reached (%d requests per hour). Please wait and try again later.', 'pressprimer-quiz' ),
					self::RATE_LIMIT_PER_HOUR
				)
			);
		}

		return true;
	}

	/**
	 * Increment rate limit counter
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 */
	private static function increment_rate_limit( $user_id ) {
		$user_id = absint( $user_id );
		$key     = 'ppq_ai_requests_' . $user_id;
		$count   = (int) get_transient( $key );

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Generate questions
	 *
	 * Main method to generate questions from content using AI.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Source content to generate questions from.
	 * @param array  $params  Generation parameters {
	 *     @type int      $count      Number of questions to generate (1-50).
	 *     @type array    $types      Question types ('mc', 'ma', 'tf').
	 *     @type array    $difficulty Difficulty levels ('easy', 'medium', 'hard').
	 *     @type int|null $user_id    User ID for rate limiting.
	 * }
	 * @return array|WP_Error Array of generated questions or WP_Error.
	 */
	public function generate_questions( $content, $params = [] ) {
		// Validate API key
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'ppq_no_api_key',
				__( 'OpenAI API key is not configured.', 'pressprimer-quiz' )
			);
		}

		// Check rate limit if user_id provided
		if ( ! empty( $params['user_id'] ) ) {
			$rate_check = self::check_rate_limit( $params['user_id'] );
			if ( is_wp_error( $rate_check ) ) {
				return $rate_check;
			}
		}

		// Validate and sanitize content
		$content = $this->sanitize_content( $content );

		if ( empty( $content ) ) {
			return new WP_Error(
				'ppq_empty_content',
				__( 'Content is required for question generation.', 'pressprimer-quiz' )
			);
		}

		// Truncate if too long
		if ( mb_strlen( $content ) > self::MAX_CONTENT_LENGTH ) {
			$content = mb_substr( $content, 0, self::MAX_CONTENT_LENGTH );
		}

		// Validate and set default parameters
		$params = $this->validate_params( $params );

		// Build the prompt
		$prompt = $this->build_prompt( $content, $params );

		// Make API call
		$response = $this->call_api( $prompt );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Increment rate limit after successful call
		if ( ! empty( $params['user_id'] ) ) {
			self::increment_rate_limit( $params['user_id'] );
		}

		// Parse the response
		$questions = $this->parse_response( $response );

		if ( is_wp_error( $questions ) ) {
			return $questions;
		}

		// Validate questions
		$validated = $this->validate_questions( $questions );

		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		// Extract questions array from validation result
		// validate_questions now returns an array with metadata
		if ( isset( $validated['questions'] ) ) {
			return $validated;
		}

		// Fallback for simple array return
		return [
			'questions'       => $validated,
			'valid_count'     => count( $validated ),
			'invalid_count'   => 0,
			'partial_success' => false,
		];
	}

	/**
	 * Sanitize content
	 *
	 * Cleans and prepares content for generation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Raw content.
	 * @return string Sanitized content.
	 */
	private function sanitize_content( $content ) {
		// Strip HTML tags
		$content = wp_strip_all_tags( $content );

		// Normalize whitespace
		$content = preg_replace( '/\s+/', ' ', $content );

		// Trim
		$content = trim( $content );

		return $content;
	}

	/**
	 * Validate parameters
	 *
	 * Validates and sets defaults for generation parameters.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Raw parameters.
	 * @return array Validated parameters.
	 */
	private function validate_params( $params ) {
		$defaults = [
			'count'             => 5,
			'types'             => [ 'mc' ],
			'difficulty'        => [ 'medium' ],
			'answer_count'      => 4,
			'generate_feedback' => true,
			'user_id'           => 0,
		];

		$params = wp_parse_args( $params, $defaults );

		// Validate count (1-100)
		$params['count'] = max( 1, min( 100, absint( $params['count'] ) ) );

		// Validate types
		$valid_types = [ 'mc', 'ma', 'tf' ];
		if ( ! is_array( $params['types'] ) ) {
			$params['types'] = [ $params['types'] ];
		}
		$params['types'] = array_intersect( $params['types'], $valid_types );
		if ( empty( $params['types'] ) ) {
			$params['types'] = [ 'mc' ];
		}

		// Validate difficulty (including expert)
		$valid_difficulties = [ 'easy', 'medium', 'hard', 'expert' ];
		if ( ! is_array( $params['difficulty'] ) ) {
			$params['difficulty'] = [ $params['difficulty'] ];
		}
		$params['difficulty'] = array_intersect( $params['difficulty'], $valid_difficulties );
		if ( empty( $params['difficulty'] ) ) {
			$params['difficulty'] = [ 'medium' ];
		}

		// Validate answer count (3-6 for MC/MA)
		$params['answer_count'] = max( 3, min( 6, absint( $params['answer_count'] ) ) );

		// Validate generate_feedback (boolean)
		$params['generate_feedback'] = (bool) $params['generate_feedback'];

		return $params;
	}

	/**
	 * Build prompt
	 *
	 * Constructs optimized prompts for OpenAI API with examples for better results.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Source content.
	 * @param array  $params  Generation parameters.
	 * @return array Prompt with system and user messages.
	 */
	private function build_prompt( $content, $params ) {
		$types_str         = implode( ', ', array_map( 'strtoupper', $params['types'] ) );
		$answer_count      = isset( $params['answer_count'] ) ? $params['answer_count'] : 4;
		$generate_feedback = isset( $params['generate_feedback'] ) ? $params['generate_feedback'] : true;

		// Build type instructions with answer count
		$type_instructions = [];
		if ( in_array( 'mc', $params['types'], true ) ) {
			$type_instructions[] = '- MC (Multiple Choice): exactly ONE correct answer, exactly ' . $answer_count . ' options total';
		}
		if ( in_array( 'ma', $params['types'], true ) ) {
			$min_correct         = min( 2, $answer_count - 1 );
			$max_correct         = $answer_count - 1;
			$type_instructions[] = '- MA (Multiple Answer): ' . $min_correct . '-' . $max_correct . ' correct answers, exactly ' . $answer_count . ' options total';
		}
		if ( in_array( 'tf', $params['types'], true ) ) {
			$type_instructions[] = '- TF (True/False): exactly 2 options with text "True" and "False"';
		}

		$type_instructions_str = implode( "\n", $type_instructions );

		// Build difficulty instructions
		$difficulty_instructions = $this->build_difficulty_instructions( $params['difficulty'], $params['count'] );

		// Build examples based on requested types
		$examples = $this->build_example_questions( $params['types'], $generate_feedback );

		// Build JSON structure based on feedback setting
		if ( $generate_feedback ) {
			$json_structure      = '{
  "questions": [
    {
      "type": "mc|ma|tf",
      "difficulty": "easy|medium|hard|expert",
      "stem": "Clear, unambiguous question text",
      "answers": [
        {"text": "Answer option text", "is_correct": true, "feedback": "Explanation of why this is correct/incorrect"}
      ],
      "feedback_correct": "Encouraging feedback when answered correctly",
      "feedback_incorrect": "Helpful feedback explaining the correct answer when answered incorrectly"
    }
  ]
}';
			$feedback_guidelines = '3. **Feedback**:
   - Provide educational value explaining WHY an answer is correct/incorrect
   - Reference specific concepts from the content
   - Help learners understand their mistakes';
		} else {
			$json_structure      = '{
  "questions": [
    {
      "type": "mc|ma|tf",
      "difficulty": "easy|medium|hard|expert",
      "stem": "Clear, unambiguous question text",
      "answers": [
        {"text": "Answer option text", "is_correct": true}
      ]
    }
  ]
}';
			$feedback_guidelines = '3. **No Feedback Required**: Do not include feedback fields - only stem, type, difficulty, and answers with text and is_correct.';
		}

		$system = 'You are an expert educational assessment designer specializing in creating effective quiz questions that test genuine understanding, not mere recall.' . "\n\n" .
			'Your task is to generate ' . $params['count'] . ' high-quality quiz questions from the provided educational content.' . "\n\n" .
			'## Output Format' . "\n\n" .
			'You MUST output ONLY valid JSON with no markdown formatting, no code blocks, and no additional text. The response must start with { and end with }.' . "\n\n" .
			'## JSON Structure' . "\n\n" .
			$json_structure . "\n\n" .
			'## Question Type Requirements' . "\n\n" .
			$type_instructions_str . "\n\n" .
			'## Difficulty Distribution' . "\n\n" .
			$difficulty_instructions . "\n\n" .
			'## Quality Guidelines' . "\n\n" .
			'1. **Question Stems**:' . "\n" .
			'   - Write clear, concise questions that test understanding' . "\n" .
			'   - Avoid negative phrasing (e.g., "Which is NOT...")' . "\n" .
			'   - Ensure one unambiguous correct answer (or correct set for MA)' . "\n" .
			'   - Test application and comprehension, not just memorization' . "\n\n" .
			'2. **Answer Options**:' . "\n" .
			'   - Make distractors plausible but clearly incorrect' . "\n" .
			'   - Avoid "all of the above" or "none of the above"' . "\n" .
			'   - Keep options similar in length and structure' . "\n" .
			'   - Avoid grammatical clues that give away the answer' . "\n\n" .
			$feedback_guidelines . "\n\n" .
			'4. **Difficulty Levels** (IMPORTANT - distractors must match difficulty):' . "\n" .
			'   - **Easy**: Tests direct recall or basic understanding' . "\n" .
			'     * Distractors should be obviously wrong to anyone who read the material' . "\n" .
			'     * Use clearly unrelated or contradictory options' . "\n" .
			'     * Questions focus on key definitions, main concepts, or explicit facts' . "\n" .
			'   - **Medium**: Requires application or analysis' . "\n" .
			'     * Distractors are plausible but distinguishable with careful thought' . "\n" .
			'     * May include common misconceptions' . "\n" .
			'     * Questions require connecting concepts or applying knowledge' . "\n" .
			'   - **Hard**: Demands synthesis, evaluation, or complex application' . "\n" .
			'     * Distractors are highly plausible and require deep understanding to eliminate' . "\n" .
			'     * Include subtle distinctions and near-correct options' . "\n" .
			'     * Questions may involve multiple concepts or edge cases' . "\n" .
			'   - **Expert**: Tests advanced mastery and professional-level knowledge' . "\n" .
			'     * Distractors are extremely plausible, often true in other contexts' . "\n" .
			'     * Require nuanced understanding of exceptions, limitations, or advanced applications' . "\n" .
			'     * Questions target expert-level distinctions that novices would miss' . "\n\n" .
			'## Examples' . "\n\n" .
			$examples . "\n\n" .
			'## Important' . "\n\n" .
			'- Generate EXACTLY ' . $params['count'] . ' questions' . "\n" .
			'- Use ONLY the question types specified: ' . $types_str . "\n" .
			'- Base ALL questions on the provided content' . "\n" .
			'- Output raw JSON only - no markdown, no code fences';

		$user = "Generate {$params['count']} quiz questions from this educational content:\n\n---\n\n" . $content . "\n\n---\n\nRemember: Output only valid JSON, starting with { and ending with }.";

		return [
			'system' => $system,
			'user'   => $user,
		];
	}

	/**
	 * Build difficulty instructions
	 *
	 * Creates distribution instructions for difficulty levels.
	 *
	 * @since 1.0.0
	 *
	 * @param array $difficulties Selected difficulty levels.
	 * @param int   $count        Total question count.
	 * @return string Difficulty distribution instructions.
	 */
	private function build_difficulty_instructions( $difficulties, $count ) {
		$difficulty_count = count( $difficulties );

		if ( 1 === $difficulty_count ) {
			return 'All questions should be ' . ucfirst( $difficulties[0] ) . ' difficulty.';
		}

		$per_difficulty = ceil( $count / $difficulty_count );
		$distribution   = [];

		foreach ( $difficulties as $diff ) {
			$distribution[] = ucfirst( $diff ) . ': approximately ' . $per_difficulty . ' questions';
		}

		return "Distribute questions across difficulty levels:\n- " . implode( "\n- ", $distribution );
	}

	/**
	 * Build example questions
	 *
	 * Creates example questions to guide AI output format.
	 *
	 * @since 1.0.0
	 *
	 * @param array $types             Requested question types.
	 * @param bool  $generate_feedback Whether to include feedback in examples.
	 * @return string Example questions in JSON format.
	 */
	private function build_example_questions( $types, $generate_feedback = true ) {
		$examples = [];

		if ( in_array( 'mc', $types, true ) ) {
			if ( $generate_feedback ) {
				$examples[] = 'Multiple Choice (MC) example:' . "\n" .
					'{' . "\n" .
					'  "type": "mc",' . "\n" .
					'  "difficulty": "medium",' . "\n" .
					'  "stem": "What is the primary function of mitochondria in a cell?",' . "\n" .
					'  "answers": [' . "\n" .
					'    {"text": "Energy production through ATP synthesis", "is_correct": true, "feedback": "Correct! Mitochondria are often called the \'powerhouse of the cell\' because they produce ATP through cellular respiration."},' . "\n" .
					'    {"text": "Protein synthesis", "is_correct": false, "feedback": "Incorrect. Protein synthesis occurs in ribosomes, not mitochondria."},' . "\n" .
					'    {"text": "Cell division", "is_correct": false, "feedback": "Incorrect. Cell division is controlled by the nucleus and involves structures like the centrosome."},' . "\n" .
					'    {"text": "Waste storage", "is_correct": false, "feedback": "Incorrect. Waste storage is primarily handled by vacuoles, especially in plant cells."}' . "\n" .
					'  ],' . "\n" .
					'  "feedback_correct": "Excellent! You understand the crucial role mitochondria play in cellular energy production.",' . "\n" .
					'  "feedback_incorrect": "Mitochondria are responsible for producing ATP through cellular respiration. They convert nutrients into usable energy for the cell."' . "\n" .
					'}';
			} else {
				$examples[] = 'Multiple Choice (MC) example:' . "\n" .
					'{' . "\n" .
					'  "type": "mc",' . "\n" .
					'  "difficulty": "medium",' . "\n" .
					'  "stem": "What is the primary function of mitochondria in a cell?",' . "\n" .
					'  "answers": [' . "\n" .
					'    {"text": "Energy production through ATP synthesis", "is_correct": true},' . "\n" .
					'    {"text": "Protein synthesis", "is_correct": false},' . "\n" .
					'    {"text": "Cell division", "is_correct": false},' . "\n" .
					'    {"text": "Waste storage", "is_correct": false}' . "\n" .
					'  ]' . "\n" .
					'}';
			}
		}

		if ( in_array( 'ma', $types, true ) ) {
			if ( $generate_feedback ) {
				$examples[] = 'Multiple Answer (MA) example:' . "\n" .
					'{' . "\n" .
					'  "type": "ma",' . "\n" .
					'  "difficulty": "hard",' . "\n" .
					'  "stem": "Which of the following are characteristics of effective feedback in education? Select all that apply.",' . "\n" .
					'  "answers": [' . "\n" .
					'    {"text": "Timely delivery after the performance", "is_correct": true, "feedback": "Correct! Timely feedback allows students to connect it with their actions."},' . "\n" .
					'    {"text": "Focused on specific behaviors or outcomes", "is_correct": true, "feedback": "Correct! Specific feedback is more actionable than general comments."},' . "\n" .
					'    {"text": "Always positive to maintain motivation", "is_correct": false, "feedback": "Incorrect. Effective feedback should be honest and constructive, not just positive."},' . "\n" .
					'    {"text": "Includes guidance for improvement", "is_correct": true, "feedback": "Correct! Actionable guidance helps students know how to improve."},' . "\n" .
					'    {"text": "Delivered publicly to increase accountability", "is_correct": false, "feedback": "Incorrect. Feedback is often more effective when delivered privately to avoid embarrassment."}' . "\n" .
					'  ],' . "\n" .
					'  "feedback_correct": "Great job! You correctly identified the key characteristics of effective educational feedback.",' . "\n" .
					'  "feedback_incorrect": "Effective feedback should be timely, specific, and include guidance for improvement. It doesn\'t need to be always positive or delivered publicly."' . "\n" .
					'}';
			} else {
				$examples[] = 'Multiple Answer (MA) example:' . "\n" .
					'{' . "\n" .
					'  "type": "ma",' . "\n" .
					'  "difficulty": "hard",' . "\n" .
					'  "stem": "Which of the following are characteristics of effective feedback in education? Select all that apply.",' . "\n" .
					'  "answers": [' . "\n" .
					'    {"text": "Timely delivery after the performance", "is_correct": true},' . "\n" .
					'    {"text": "Focused on specific behaviors or outcomes", "is_correct": true},' . "\n" .
					'    {"text": "Always positive to maintain motivation", "is_correct": false},' . "\n" .
					'    {"text": "Includes guidance for improvement", "is_correct": true},' . "\n" .
					'    {"text": "Delivered publicly to increase accountability", "is_correct": false}' . "\n" .
					'  ]' . "\n" .
					'}';
			}
		}

		if ( in_array( 'tf', $types, true ) ) {
			if ( $generate_feedback ) {
				$examples[] = 'True/False (TF) example:' . "\n" .
					'{' . "\n" .
					'  "type": "tf",' . "\n" .
					'  "difficulty": "easy",' . "\n" .
					'  "stem": "The Earth revolves around the Sun in approximately 365 days.",' . "\n" .
					'  "answers": [' . "\n" .
					'    {"text": "True", "is_correct": true, "feedback": "Correct! Earth\'s orbital period around the Sun is approximately 365.25 days, which is why we have leap years."},' . "\n" .
					'    {"text": "False", "is_correct": false, "feedback": "Incorrect. Earth does complete one orbit around the Sun in about 365 days."}' . "\n" .
					'  ],' . "\n" .
					'  "feedback_correct": "Correct! This is the basis for our calendar year.",' . "\n" .
					'  "feedback_incorrect": "Earth\'s orbit around the Sun takes approximately 365.25 days, which we round to 365 days for our calendar."' . "\n" .
					'}';
			} else {
				$examples[] = 'True/False (TF) example:' . "\n" .
					'{' . "\n" .
					'  "type": "tf",' . "\n" .
					'  "difficulty": "easy",' . "\n" .
					'  "stem": "The Earth revolves around the Sun in approximately 365 days.",' . "\n" .
					'  "answers": [' . "\n" .
					'    {"text": "True", "is_correct": true},' . "\n" .
					'    {"text": "False", "is_correct": false}' . "\n" .
					'  ]' . "\n" .
					'}';
			}
		}

		return implode( "\n\n", $examples );
	}

	/**
	 * Call API
	 *
	 * Makes the actual API call to OpenAI with extended timeout and retry support.
	 * OpenAI can take several minutes for complex generation tasks, especially
	 * with large content or many questions requested.
	 *
	 * @since 1.0.0
	 *
	 * @param array $prompt Prompt with system and user messages.
	 * @return string|WP_Error Response content or WP_Error.
	 */
	private function call_api( $prompt ) {
		$request_body = [
			'model'                 => $this->model,
			'messages'              => [
				[
					'role'    => 'system',
					'content' => $prompt['system'],
				],
				[
					'role'    => 'user',
					'content' => $prompt['user'],
				],
			],
			'max_completion_tokens' => 16000,
		];

		// GPT-5.x models don't support temperature parameter (only default value of 1)
		// Only add temperature for older models (GPT-4.x and below)
		if ( ! preg_match( '/^gpt-5/', $this->model ) ) {
			$request_body['temperature'] = 0.7;
		}

		$last_error = null;

		// Retry loop for transient failures
		for ( $attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++ ) {
			// Wait before retry (not on first attempt)
			if ( $attempt > 0 ) {
				sleep( self::RETRY_DELAY );
			}

			$response = $this->make_api_request( $request_body );

			// If successful, return the content
			if ( ! is_wp_error( $response ) ) {
				return $response;
			}

			$last_error = $response;

			// Check if error is retryable
			if ( ! $this->is_retryable_error( $response ) ) {
				return $response;
			}
		}

		// All retries exhausted
		return $last_error;
	}

	/**
	 * Make API request
	 *
	 * Executes a single API request to OpenAI with extended timeout.
	 *
	 * @since 1.0.0
	 *
	 * @param array $request_body Request body for OpenAI API.
	 * @return string|WP_Error Response content or WP_Error.
	 */
	private function make_api_request( $request_body ) {
		$response = wp_remote_post(
			self::API_BASE_URL . '/chat/completions',
			[
				'timeout'     => self::API_TIMEOUT,
				'httpversion' => '1.1',
				'headers'     => [
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				],
				'body'        => wp_json_encode( $request_body ),
				'sslverify'   => true,
			]
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();

			// Provide more helpful timeout message
			if ( strpos( $error_message, 'timed out' ) !== false || strpos( $error_message, 'timeout' ) !== false ) {
				return new WP_Error(
					'ppq_api_timeout',
					__( 'The request to OpenAI timed out. This can happen with large content or many questions. Please try again with less content or fewer questions.', 'pressprimer-quiz' )
				);
			}

			return new WP_Error(
				'ppq_api_connection_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to connect to OpenAI API: %s', 'pressprimer-quiz' ),
					$error_message
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Store full response for debugging
		$this->last_response = $body;

		// Store token usage
		if ( isset( $body['usage'] ) ) {
			$this->last_token_usage = $body['usage'];
		}

		if ( 401 === $code ) {
			return new WP_Error(
				'ppq_invalid_key',
				__( 'Invalid API key.', 'pressprimer-quiz' )
			);
		}

		if ( 429 === $code ) {
			return new WP_Error(
				'ppq_rate_limited',
				__( 'OpenAI API rate limit exceeded. Please try again later.', 'pressprimer-quiz' )
			);
		}

		if ( 500 === $code || 502 === $code || 503 === $code || 504 === $code ) {
			return new WP_Error(
				'ppq_api_server_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'OpenAI server error (HTTP %d). This is usually temporary. Please try again.', 'pressprimer-quiz' ),
					$code
				)
			);
		}

		if ( isset( $body['error'] ) ) {
			return new WP_Error(
				'ppq_api_error',
				isset( $body['error']['message'] )
					? $body['error']['message']
					: __( 'OpenAI API error occurred.', 'pressprimer-quiz' )
			);
		}

		if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
			return new WP_Error(
				'ppq_invalid_response',
				__( 'Invalid response format from OpenAI.', 'pressprimer-quiz' )
			);
		}

		return $body['choices'][0]['message']['content'];
	}

	/**
	 * Check if error is retryable
	 *
	 * Determines if an error is transient and worth retrying.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Error $error The error to check.
	 * @return bool True if the error is retryable.
	 */
	private function is_retryable_error( $error ) {
		$retryable_codes = [
			'ppq_api_timeout',
			'ppq_api_server_error',
			'ppq_api_connection_error',
		];

		return in_array( $error->get_error_code(), $retryable_codes, true );
	}

	/**
	 * Parse response
	 *
	 * Extracts questions from the API response using multiple parsing strategies.
	 * Handles various formats including markdown code blocks, nested JSON, and
	 * malformed responses.
	 *
	 * @since 1.0.0
	 *
	 * @param string $response Raw response content.
	 * @return array|WP_Error Parsed questions or WP_Error.
	 */
	private function parse_response( $response ) {
		// Store original for error reporting
		$original_response = $response;

		// Strategy 1: Try to extract JSON from markdown code blocks (```json ... ```)
		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)\s*```/', $response, $matches ) ) {
			$response = $matches[1];
		}

		// Strategy 2: Try to find JSON object boundaries
		$response = trim( $response );

		// If response doesn't start with {, try to find the JSON start
		if ( substr( $response, 0, 1 ) !== '{' ) {
			$json_start = strpos( $response, '{' );
			if ( $json_start !== false ) {
				$response = substr( $response, $json_start );
			}
		}

		// Find matching closing brace
		if ( substr( $response, 0, 1 ) === '{' ) {
			$depth       = 0;
			$in_string   = false;
			$escape_next = false;
			$json_end    = strlen( $response );

			for ( $i = 0; $i < strlen( $response ); $i++ ) {
				$char = $response[ $i ];

				if ( $escape_next ) {
					$escape_next = false;
					continue;
				}

				if ( '\\' === $char && $in_string ) {
					$escape_next = true;
					continue;
				}

				if ( '"' === $char ) {
					$in_string = ! $in_string;
					continue;
				}

				if ( ! $in_string ) {
					if ( '{' === $char ) {
						++$depth;
					} elseif ( '}' === $char ) {
						--$depth;
						if ( 0 === $depth ) {
							$json_end = $i + 1;
							break;
						}
					}
				}
			}

			$response = substr( $response, 0, $json_end );
		}

		// Strategy 3: Parse JSON
		$data = json_decode( $response, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Strategy 4: Try to fix common JSON issues
			$fixed_response = $this->attempt_json_fix( $response );

			if ( $fixed_response !== $response ) {
				$data = json_decode( $fixed_response, true );
			}

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new WP_Error(
					'ppq_json_error',
					sprintf(
						/* translators: %s: error message */
						__( 'Failed to parse AI response: %s. The AI may have returned an invalid format. Please try again.', 'pressprimer-quiz' ),
						json_last_error_msg()
					),
					[
						'response_preview' => substr( $original_response, 0, 500 ),
						'json_error'       => json_last_error_msg(),
					]
				);
			}
		}

		// Strategy 5: Handle various response structures
		$questions = $this->extract_questions_from_data( $data );

		if ( is_wp_error( $questions ) ) {
			return $questions;
		}

		if ( empty( $questions ) ) {
			return new WP_Error(
				'ppq_no_questions',
				__( 'The AI response did not contain any questions. Please try again with different content.', 'pressprimer-quiz' )
			);
		}

		return $questions;
	}

	/**
	 * Attempt to fix common JSON issues
	 *
	 * Tries to repair malformed JSON from AI responses.
	 *
	 * @since 1.0.0
	 *
	 * @param string $json Potentially malformed JSON.
	 * @return string Fixed JSON or original if unfixable.
	 */
	private function attempt_json_fix( $json ) {
		// Remove trailing commas before ] or }
		$json = preg_replace( '/,\s*([}\]])/', '$1', $json );

		// Fix unescaped newlines in strings
		$json = preg_replace( '/([^\\\\])\\n/', '$1\\\\n', $json );

		// Fix single quotes to double quotes (risky but sometimes works)
		// Only do this if there are no double quotes in the response
		if ( strpos( $json, '"' ) === false && strpos( $json, "'" ) !== false ) {
			$json = str_replace( "'", '"', $json );
		}

		// Remove control characters except newlines and tabs
		$json = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $json );

		return $json;
	}

	/**
	 * Extract questions from parsed data
	 *
	 * Handles various data structures that AI might return.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $data Parsed JSON data.
	 * @return array|WP_Error Array of questions or WP_Error.
	 */
	private function extract_questions_from_data( $data ) {
		// Most common: { "questions": [...] }
		if ( isset( $data['questions'] ) && is_array( $data['questions'] ) ) {
			return $data['questions'];
		}

		// Alternative: { "quiz": { "questions": [...] } }
		if ( isset( $data['quiz']['questions'] ) && is_array( $data['quiz']['questions'] ) ) {
			return $data['quiz']['questions'];
		}

		// Alternative: Direct array of questions
		if ( is_array( $data ) && isset( $data[0] ) && is_array( $data[0] ) ) {
			// Check if first item looks like a question
			if ( isset( $data[0]['stem'] ) || isset( $data[0]['question'] ) || isset( $data[0]['type'] ) ) {
				return $data;
			}
		}

		// Alternative: { "data": { "questions": [...] } }
		if ( isset( $data['data']['questions'] ) && is_array( $data['data']['questions'] ) ) {
			return $data['data']['questions'];
		}

		// Try to find any array that looks like questions
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) && ! empty( $value ) ) {
				$first = reset( $value );
				if ( is_array( $first ) && ( isset( $first['stem'] ) || isset( $first['question'] ) ) ) {
					return $value;
				}
			}
		}

		return new WP_Error(
			'ppq_invalid_format',
			__( 'AI response does not contain questions in a recognized format. Please try again.', 'pressprimer-quiz' ),
			[ 'data_keys' => is_array( $data ) ? array_keys( $data ) : 'not_array' ]
		);
	}

	/**
	 * Validation errors from last validation
	 *
	 * @var array
	 */
	private $last_validation_errors = [];

	/**
	 * Validate questions
	 *
	 * Validates the structure of generated questions with detailed error tracking.
	 * Handles partial failures gracefully - returns valid questions even if some fail.
	 *
	 * @since 1.0.0
	 *
	 * @param array $questions Array of questions to validate.
	 * @return array|WP_Error Valid questions array with metadata or WP_Error.
	 */
	private function validate_questions( $questions ) {
		$valid_questions              = [];
		$this->last_validation_errors = [];
		$total_count                  = count( $questions );

		foreach ( $questions as $index => $question ) {
			// Handle alternative field names that AI might use
			$question = $this->normalize_question_fields( $question );

			$validation = $this->validate_single_question( $question, $index );

			if ( is_wp_error( $validation ) ) {
				$this->last_validation_errors[] = [
					'index'   => $index + 1,
					'message' => $validation->get_error_message(),
					'code'    => $validation->get_error_code(),
				];
				continue;
			}

			$valid_questions[] = $validation;
		}

		// If no valid questions, return error with details
		if ( empty( $valid_questions ) ) {
			$error_summary = [];
			foreach ( $this->last_validation_errors as $error ) {
				$error_summary[] = sprintf( 'Q%d: %s', $error['index'], $error['message'] );
			}

			return new WP_Error(
				'ppq_no_valid_questions',
				sprintf(
					/* translators: %s: list of validation errors */
					__( 'No valid questions could be generated. Issues: %s', 'pressprimer-quiz' ),
					implode( '; ', array_slice( $error_summary, 0, 3 ) ) // Show first 3 errors
				),
				[
					'total_generated'   => $total_count,
					'validation_errors' => $this->last_validation_errors,
				]
			);
		}

		// Return questions with validation metadata
		return [
			'questions'         => $valid_questions,
			'total_generated'   => $total_count,
			'valid_count'       => count( $valid_questions ),
			'invalid_count'     => count( $this->last_validation_errors ),
			'validation_errors' => $this->last_validation_errors,
			'partial_success'   => count( $this->last_validation_errors ) > 0,
		];
	}

	/**
	 * Normalize question fields
	 *
	 * Handles alternative field names that AI might use.
	 *
	 * @since 1.0.0
	 *
	 * @param array $question Raw question data.
	 * @return array Normalized question data.
	 */
	private function normalize_question_fields( $question ) {
		if ( ! is_array( $question ) ) {
			return $question;
		}

		// Normalize stem field
		if ( ! isset( $question['stem'] ) ) {
			if ( isset( $question['question'] ) ) {
				$question['stem'] = $question['question'];
			} elseif ( isset( $question['text'] ) ) {
				$question['stem'] = $question['text'];
			} elseif ( isset( $question['prompt'] ) ) {
				$question['stem'] = $question['prompt'];
			}
		}

		// Normalize type field
		if ( isset( $question['type'] ) ) {
			$type_map = [
				'multiple_choice' => 'mc',
				'multiple-choice' => 'mc',
				'multiplechoice'  => 'mc',
				'single_choice'   => 'mc',
				'multiple_answer' => 'ma',
				'multiple-answer' => 'ma',
				'multipleanswer'  => 'ma',
				'multi_select'    => 'ma',
				'true_false'      => 'tf',
				'true-false'      => 'tf',
				'truefalse'       => 'tf',
				'boolean'         => 'tf',
			];

			$normalized_type = strtolower( $question['type'] );
			if ( isset( $type_map[ $normalized_type ] ) ) {
				$question['type'] = $type_map[ $normalized_type ];
			}
		}

		// Normalize answers field
		if ( ! isset( $question['answers'] ) ) {
			if ( isset( $question['options'] ) ) {
				$question['answers'] = $question['options'];
			} elseif ( isset( $question['choices'] ) ) {
				$question['answers'] = $question['choices'];
			}
		}

		// Normalize answer structure
		if ( isset( $question['answers'] ) && is_array( $question['answers'] ) ) {
			$normalized_answers = [];
			foreach ( $question['answers'] as $answer ) {
				if ( is_string( $answer ) ) {
					// Simple string answer (shouldn't happen but handle it)
					$normalized_answers[] = [
						'text'       => $answer,
						'is_correct' => false,
						'feedback'   => '',
					];
				} elseif ( is_array( $answer ) ) {
					$norm_answer = [];

					// Normalize text
					if ( isset( $answer['text'] ) ) {
						$norm_answer['text'] = $answer['text'];
					} elseif ( isset( $answer['answer'] ) ) {
						$norm_answer['text'] = $answer['answer'];
					} elseif ( isset( $answer['content'] ) ) {
						$norm_answer['text'] = $answer['content'];
					} elseif ( isset( $answer['option'] ) ) {
						$norm_answer['text'] = $answer['option'];
					}

					// Normalize is_correct
					if ( isset( $answer['is_correct'] ) ) {
						$norm_answer['is_correct'] = (bool) $answer['is_correct'];
					} elseif ( isset( $answer['correct'] ) ) {
						$norm_answer['is_correct'] = (bool) $answer['correct'];
					} elseif ( isset( $answer['isCorrect'] ) ) {
						$norm_answer['is_correct'] = (bool) $answer['isCorrect'];
					} else {
						$norm_answer['is_correct'] = false;
					}

					// Normalize feedback
					if ( isset( $answer['feedback'] ) ) {
						$norm_answer['feedback'] = $answer['feedback'];
					} elseif ( isset( $answer['explanation'] ) ) {
						$norm_answer['feedback'] = $answer['explanation'];
					} else {
						$norm_answer['feedback'] = '';
					}

					if ( isset( $norm_answer['text'] ) ) {
						$normalized_answers[] = $norm_answer;
					}
				}
			}
			$question['answers'] = $normalized_answers;
		}

		// Normalize feedback fields
		if ( ! isset( $question['feedback_correct'] ) ) {
			if ( isset( $question['correct_feedback'] ) ) {
				$question['feedback_correct'] = $question['correct_feedback'];
			} elseif ( isset( $question['feedbackCorrect'] ) ) {
				$question['feedback_correct'] = $question['feedbackCorrect'];
			}
		}

		if ( ! isset( $question['feedback_incorrect'] ) ) {
			if ( isset( $question['incorrect_feedback'] ) ) {
				$question['feedback_incorrect'] = $question['incorrect_feedback'];
			} elseif ( isset( $question['feedbackIncorrect'] ) ) {
				$question['feedback_incorrect'] = $question['feedbackIncorrect'];
			} elseif ( isset( $question['explanation'] ) ) {
				$question['feedback_incorrect'] = $question['explanation'];
			}
		}

		return $question;
	}

	/**
	 * Get last validation errors
	 *
	 * Returns validation errors from the last validation run.
	 *
	 * @since 1.0.0
	 *
	 * @return array Validation errors.
	 */
	public function get_last_validation_errors() {
		return $this->last_validation_errors;
	}

	/**
	 * Validate single question
	 *
	 * Validates and normalizes a single question structure.
	 *
	 * @since 1.0.0
	 *
	 * @param array $question Question data.
	 * @param int   $index    Question index for error messages.
	 * @return array|WP_Error Normalized question or WP_Error.
	 */
	private function validate_single_question( $question, $index ) {
		// Check required fields
		$required = [ 'type', 'stem', 'answers' ];
		foreach ( $required as $field ) {
			if ( empty( $question[ $field ] ) ) {
				return new WP_Error(
					'ppq_missing_field',
					sprintf(
						/* translators: 1: question number, 2: field name */
						__( 'Question %1$d is missing required field: %2$s', 'pressprimer-quiz' ),
						$index + 1,
						$field
					)
				);
			}
		}

		// Validate type
		$valid_types = [ 'mc', 'ma', 'tf' ];
		$type        = strtolower( $question['type'] );
		if ( ! in_array( $type, $valid_types, true ) ) {
			return new WP_Error(
				'ppq_invalid_type',
				sprintf(
					/* translators: %d: question number */
					__( 'Question %d has an invalid type.', 'pressprimer-quiz' ),
					$index + 1
				)
			);
		}

		// Validate answers
		if ( ! is_array( $question['answers'] ) || count( $question['answers'] ) < 2 ) {
			return new WP_Error(
				'ppq_invalid_answers',
				sprintf(
					/* translators: %d: question number */
					__( 'Question %d must have at least 2 answers.', 'pressprimer-quiz' ),
					$index + 1
				)
			);
		}

		// Count correct answers
		$correct_count = 0;
		foreach ( $question['answers'] as $answer ) {
			if ( ! empty( $answer['is_correct'] ) ) {
				++$correct_count;
			}
		}

		// Validate correct answer count based on type
		if ( 'mc' === $type || 'tf' === $type ) {
			if ( 1 !== $correct_count ) {
				return new WP_Error(
					'ppq_incorrect_count',
					sprintf(
						/* translators: %d: question number */
						__( 'Question %d must have exactly one correct answer.', 'pressprimer-quiz' ),
						$index + 1
					)
				);
			}
		} elseif ( 'ma' === $type ) {
			if ( $correct_count < 1 ) {
				return new WP_Error(
					'ppq_no_correct',
					sprintf(
						/* translators: %d: question number */
						__( 'Question %d must have at least one correct answer.', 'pressprimer-quiz' ),
						$index + 1
					)
				);
			}
		}

		// Normalize the question structure
		$normalized = [
			'type'               => $type,
			'difficulty'         => isset( $question['difficulty'] ) ? strtolower( $question['difficulty'] ) : 'medium',
			'stem'               => sanitize_textarea_field( $question['stem'] ),
			'answers'            => [],
			'feedback_correct'   => isset( $question['feedback_correct'] ) ? sanitize_textarea_field( $question['feedback_correct'] ) : '',
			'feedback_incorrect' => isset( $question['feedback_incorrect'] ) ? sanitize_textarea_field( $question['feedback_incorrect'] ) : '',
		];

		// Normalize answers
		foreach ( $question['answers'] as $answer ) {
			if ( empty( $answer['text'] ) ) {
				continue;
			}

			$normalized['answers'][] = [
				'text'       => sanitize_textarea_field( $answer['text'] ),
				'is_correct' => ! empty( $answer['is_correct'] ),
				'feedback'   => isset( $answer['feedback'] ) ? sanitize_textarea_field( $answer['feedback'] ) : '',
			];
		}

		// Validate difficulty (including expert)
		$valid_difficulties = [ 'easy', 'medium', 'hard', 'expert' ];
		if ( ! in_array( $normalized['difficulty'], $valid_difficulties, true ) ) {
			$normalized['difficulty'] = 'medium';
		}

		return $normalized;
	}

	/**
	 * Get last token usage
	 *
	 * Returns token usage information from the last API call.
	 *
	 * @since 1.0.0
	 *
	 * @return array Token usage data.
	 */
	public function get_last_token_usage() {
		return $this->last_token_usage;
	}

	/**
	 * Get last response
	 *
	 * Returns the full response from the last API call.
	 *
	 * @since 1.0.0
	 *
	 * @return array Last API response.
	 */
	public function get_last_response() {
		return $this->last_response;
	}

	/**
	 * Get API key status for user
	 *
	 * Returns the status of API key configuration for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return array Status information.
	 */
	public static function get_api_key_status( $user_id ) {
		$api_key = self::get_api_key( $user_id );

		if ( is_wp_error( $api_key ) ) {
			return [
				'configured' => false,
				'valid'      => false,
				'message'    => $api_key->get_error_message(),
			];
		}

		if ( empty( $api_key ) ) {
			return [
				'configured' => false,
				'valid'      => false,
				'message'    => __( 'API key not configured.', 'pressprimer-quiz' ),
			];
		}

		// Key is configured, check if masked for display
		$masked = substr( $api_key, 0, 7 ) . '...' . substr( $api_key, -4 );

		return [
			'configured' => true,
			'valid'      => null, // We don't validate on every status check for performance
			'message'    => sprintf(
				/* translators: %s: masked API key */
				__( 'API key configured: %s', 'pressprimer-quiz' ),
				$masked
			),
			'masked_key' => $masked,
		];
	}

	/**
	 * Estimate token count
	 *
	 * Provides a rough estimate of tokens for content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Content to estimate.
	 * @return int Estimated token count.
	 */
	public static function estimate_tokens( $content ) {
		// Rough estimation: ~4 characters per token for English
		return (int) ceil( mb_strlen( $content ) / 4 );
	}

	/**
	 * Prepare content for generation
	 *
	 * Prepares and validates content from various sources for AI generation.
	 * Handles text, extracted PDF content, and extracted Word content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content      Raw content to prepare.
	 * @param string $content_type Type of content ('text', 'pdf', 'docx').
	 * @return string|WP_Error Prepared content or WP_Error.
	 */
	public function prepare_content( $content, $content_type = 'text' ) {
		if ( empty( $content ) ) {
			return new WP_Error(
				'ppq_empty_content',
				__( 'No content provided for question generation.', 'pressprimer-quiz' )
			);
		}

		// Clean content based on type
		switch ( $content_type ) {
			case 'pdf':
				$content = $this->clean_pdf_content( $content );
				break;

			case 'docx':
				$content = $this->clean_docx_content( $content );
				break;

			case 'text':
			default:
				$content = $this->sanitize_content( $content );
				break;
		}

		// Check minimum content length
		$min_length = 100; // Minimum characters for meaningful generation
		if ( mb_strlen( $content ) < $min_length ) {
			return new WP_Error(
				'ppq_content_too_short',
				sprintf(
					/* translators: %d: minimum character count */
					__( 'Content is too short for question generation. Please provide at least %d characters.', 'pressprimer-quiz' ),
					$min_length
				)
			);
		}

		// Check if content appears to be meaningful text
		if ( ! $this->is_meaningful_content( $content ) ) {
			return new WP_Error(
				'ppq_invalid_content',
				__( 'The provided content does not appear to contain meaningful text for question generation.', 'pressprimer-quiz' )
			);
		}

		// Truncate if exceeds maximum
		$was_truncated = false;
		if ( mb_strlen( $content ) > self::MAX_CONTENT_LENGTH ) {
			$content       = mb_substr( $content, 0, self::MAX_CONTENT_LENGTH );
			$was_truncated = true;

			// Try to truncate at a sentence boundary
			$last_period = mb_strrpos( $content, '.' );
			if ( $last_period !== false && $last_period > self::MAX_CONTENT_LENGTH * 0.9 ) {
				$content = mb_substr( $content, 0, $last_period + 1 );
			}
		}

		// Return content with truncation flag
		return [
			'content'        => $content,
			'was_truncated'  => $was_truncated,
			'char_count'     => mb_strlen( $content ),
			'token_estimate' => self::estimate_tokens( $content ),
		];
	}

	/**
	 * Clean PDF content
	 *
	 * Cleans text extracted from PDF files.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Raw PDF text.
	 * @return string Cleaned content.
	 */
	private function clean_pdf_content( $content ) {
		// Remove common PDF artifacts
		$content = preg_replace( '/\f/', "\n\n", $content ); // Form feeds to paragraphs
		$content = preg_replace( '/[^\S\n]+/', ' ', $content ); // Multiple spaces to single
		$content = preg_replace( '/\n{3,}/', "\n\n", $content ); // Multiple newlines to double

		// Remove page numbers that stand alone on a line
		$content = preg_replace( '/^\s*\d+\s*$/m', '', $content );

		// Remove common header/footer patterns (page X of Y)
		$content = preg_replace( '/page\s+\d+\s+(of|\/)\s+\d+/i', '', $content );

		// Clean up hyphenation at line breaks
		$content = preg_replace( '/(\w)-\n(\w)/', '$1$2', $content );

		return $this->sanitize_content( $content );
	}

	/**
	 * Clean DOCX content
	 *
	 * Cleans text extracted from Word documents.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Raw DOCX text.
	 * @return string Cleaned content.
	 */
	private function clean_docx_content( $content ) {
		// Remove multiple spaces
		$content = preg_replace( '/[^\S\n]+/', ' ', $content );

		// Normalize line breaks
		$content = preg_replace( '/\r\n?/', "\n", $content );
		$content = preg_replace( '/\n{3,}/', "\n\n", $content );

		// Remove empty lines with only whitespace
		$content = preg_replace( '/^\s+$/m', '', $content );

		return $this->sanitize_content( $content );
	}

	/**
	 * Check if content is meaningful
	 *
	 * Validates that content contains meaningful text for question generation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Content to check.
	 * @return bool True if content appears meaningful.
	 */
	private function is_meaningful_content( $content ) {
		// Check word count
		$words = str_word_count( $content );
		if ( $words < 20 ) {
			return false;
		}

		// Check ratio of letters to total characters
		$letters = preg_match_all( '/[a-zA-Z]/', $content );
		$total   = mb_strlen( $content );

		if ( $total > 0 && ( $letters / $total ) < 0.5 ) {
			return false;
		}

		// Check for repetitive content (might be corrupted)
		$lines = explode( "\n", $content );
		if ( count( $lines ) > 10 ) {
			$unique_lines = array_unique( $lines );
			if ( count( $unique_lines ) < count( $lines ) * 0.5 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Generate questions from prepared content
	 *
	 * Convenience method that prepares content and generates questions in one call.
	 * Returns comprehensive metadata including content info, token usage, and validation results.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content      Raw content.
	 * @param string $content_type Content type ('text', 'pdf', 'docx').
	 * @param array  $params       Generation parameters.
	 * @return array|WP_Error Generated questions with metadata or WP_Error.
	 */
	public function generate_from_content( $content, $content_type, $params = [] ) {
		// Prepare the content
		$prepared = $this->prepare_content( $content, $content_type );

		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		// Generate questions
		$result = $this->generate_questions( $prepared['content'], $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Build comprehensive response
		$response = [
			'questions'    => isset( $result['questions'] ) ? $result['questions'] : $result,
			'content_info' => [
				'was_truncated'  => $prepared['was_truncated'],
				'char_count'     => $prepared['char_count'],
				'token_estimate' => $prepared['token_estimate'],
			],
			'token_usage'  => $this->get_last_token_usage(),
		];

		// Add validation metadata if available
		if ( isset( $result['valid_count'] ) ) {
			$response['validation'] = [
				'total_generated'   => isset( $result['total_generated'] ) ? $result['total_generated'] : count( $response['questions'] ),
				'valid_count'       => $result['valid_count'],
				'invalid_count'     => $result['invalid_count'],
				'partial_success'   => $result['partial_success'],
				'validation_errors' => isset( $result['validation_errors'] ) ? $result['validation_errors'] : [],
			];
		}

		return $response;
	}

	/**
	 * Get generation estimate
	 *
	 * Provides an estimate of the generation request before making the API call.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Content to generate from.
	 * @param array  $params  Generation parameters.
	 * @return array Estimation data.
	 */
	public static function get_generation_estimate( $content, $params = [] ) {
		$content_length = mb_strlen( $content );
		$content_tokens = self::estimate_tokens( $content );

		// Estimate prompt tokens (system prompt + content)
		$prompt_tokens = $content_tokens + 1500; // ~1500 tokens for system prompt with examples

		// Estimate completion tokens based on question count
		$count             = isset( $params['count'] ) ? absint( $params['count'] ) : 5;
		$completion_tokens = $count * 200; // ~200 tokens per question

		$total_tokens = $prompt_tokens + $completion_tokens;

		// Will content be truncated?
		$will_truncate = $content_length > self::MAX_CONTENT_LENGTH;

		return [
			'content_length'    => $content_length,
			'will_truncate'     => $will_truncate,
			'truncated_length'  => $will_truncate ? self::MAX_CONTENT_LENGTH : $content_length,
			'prompt_tokens'     => $prompt_tokens,
			'completion_tokens' => $completion_tokens,
			'total_tokens'      => $total_tokens,
			'question_count'    => $count,
		];
	}
}
