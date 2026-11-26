<?php
/**
 * REST API: AI question generation
 *
 * Generating questions from text or an uploaded PDF or Word file, and saving
 * the chosen ones into a bank, for the wp-admin bank page and the dashboard's
 * bank editor. Replaces the bank page's admin-ajax actions.
 *
 * @package PressPrimer_Quiz
 * @subpackage API
 * @since 3.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI REST controller class
 *
 * @since 3.2.0
 */
class PressPrimer_Quiz_REST_AI_Controller {

	/**
	 * Most questions one generate or save call handles.
	 *
	 * @since 3.2.0
	 * @var int
	 */
	const MAX_QUESTIONS = 50;

	/**
	 * Question types the generator and the save accept.
	 *
	 * @since 3.2.0
	 * @var string[]
	 */
	const TYPES = [ 'mc', 'ma', 'tf' ];

	/**
	 * The generator's difficulty levels, and the question difficulty each saves as.
	 *
	 * @since 3.2.0
	 * @var array<string,string>
	 */
	const DIFFICULTIES = [
		'easy'   => 'beginner',
		'medium' => 'intermediate',
		'hard'   => 'advanced',
		'expert' => 'expert',
	];

	/**
	 * HTTP status for each AI and file error code (see error_status()).
	 *
	 * @since 3.2.0
	 * @var array<string,int>
	 */
	const ERROR_STATUSES = [
		// Setup.
		'ppq_no_provider'              => 503,
		'ppq_no_api_key'               => 409,
		'ppq_no_key'                   => 409,
		'ppq_empty_key'                => 409,
		// Limits.
		'ppq_rate_limited'             => 429,
		'ppq_ai_limit_reached'         => 429,
		'ppq_provider_rate_limited'    => 429,
		// The site's key or model.
		'ppq_invalid_key'              => 424,
		'ppq_model_not_found'          => 424,
		'ppq_quota_billing'            => 402,
		// The provider.
		'ppq_api_timeout'              => 504,
		'ppq_server_timeout'           => 504,
		'ppq_api_server_error'         => 502,
		'ppq_api_connection_error'     => 502,
		'ppq_api_error'                => 502,
		'ppq_response_truncated'       => 502,
		'ppq_invalid_response'         => 502,
		'ppq_json_error'               => 502,
		// What came back.
		'ppq_no_valid_questions'       => 422,
		'ppq_no_questions'             => 422,
		// The content sent.
		'ppq_empty_content'            => 400,
		'ppq_content_too_short'        => 400,
		'ppq_invalid_content'          => 400,
		// Uploaded files.
		'ppq_no_file'                  => 400,
		'ppq_upload_error'             => 400,
		'ppq_file_too_large'           => 413,
		'ppq_unsupported_type'         => 415,
		'ppq_invalid_mime_type'        => 415,
		'ppq_content_type_mismatch'    => 415,
		'ppq_dangerous_extension'      => 415,
		'ppq_invalid_file'             => 415,
		'ppq_no_text_found'            => 422,
		'ppq_docx_no_content'          => 422,
		'ppq_pdf_extraction_failed'    => 422,
		'ppq_pdf_parser_error'         => 422,
		'ppq_docx_extraction_failed'   => 422,
		'ppq_content_detection_failed' => 422,
	];

	/**
	 * Register hooks.
	 *
	 * @since 3.2.0
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the routes.
	 *
	 * @since 3.2.0
	 */
	public function register_routes() {
		register_rest_route(
			'ppq/v1',
			'/ai/status',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_status' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/ai/extract',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'extract' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/ai/generate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'generate' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/ai/usage',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_usage_report' ],
				'permission_callback' => [ $this, 'check_settings_permission' ],
			]
		);

		register_rest_route(
			'ppq/v1',
			'/banks/(?P<id>\d+)/ai-questions',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_questions' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Authors: pressprimer_quiz_manage_own or pressprimer_quiz_manage_all.
	 *
	 * @since 3.2.0
	 *
	 * @return bool Whether the user may use AI generation.
	 */
	public function check_permission() {
		return current_user_can( 'pressprimer_quiz_manage_own' ) || current_user_can( 'pressprimer_quiz_manage_all' );
	}

	/**
	 * Settings managers: pressprimer_quiz_manage_settings.
	 *
	 * @since 3.2.0
	 *
	 * @return bool Whether the user may see everyone's usage.
	 */
	public function check_settings_permission() {
		return current_user_can( 'pressprimer_quiz_manage_settings' );
	}

	/**
	 * The HTTP status for an AI or file error code.
	 *
	 * Addons that call the AI service (Educator's distractor and feedback
	 * routes) return the same statuses with it. Unknown codes are 500.
	 *
	 * @since 3.2.0
	 *
	 * @param string $code Error code (ppq_*).
	 * @return int HTTP status.
	 */
	public static function error_status( $code ) {
		$code = (string) $code;

		if ( isset( self::ERROR_STATUSES[ $code ] ) ) {
			return self::ERROR_STATUSES[ $code ];
		}

		// The PDF reader's own errors are reading failures too.
		if ( 0 === strpos( $code, 'ppq_pdf' ) ) {
			return 422;
		}

		return 500;
	}

	/**
	 * Whether AI generation is set up, and its limits.
	 *
	 * @since 3.2.0
	 *
	 * @return WP_REST_Response Status.
	 */
	public function get_status() {
		$provider = PressPrimer_Quiz_AI_Service::get_active_provider();
		$labels   = PressPrimer_Quiz_AI_Service::get_provider_labels();
		$key      = PressPrimer_Quiz_AI_Service::get_api_key_status( $provider );

		$status = [
			'configured'          => ! empty( $key['configured'] ),
			'provider'            => $provider,
			'provider_label'      => isset( $labels[ $provider ] ) ? $labels[ $provider ] : $provider,
			'model'               => PressPrimer_Quiz_AI_Service::get_site_model( $provider ),
			'max_file_size'       => PressPrimer_Quiz_File_Processor::get_max_file_size(),
			'supported_types'     => PressPrimer_Quiz_File_Processor::get_supported_types(),
			'max_content_length'  => PressPrimer_Quiz_AI_Service::MAX_CONTENT_LENGTH,
			'max_questions'       => self::MAX_QUESTIONS,
			'can_manage_settings' => current_user_can( 'pressprimer_quiz_manage_settings' ),
			// The user's own monthly usage and limit (limit null: none).
			'usage'               => PressPrimer_Quiz_AI_Usage_Service::get_usage( get_current_user_id() ),
		];

		// The masked key is for the people who manage it.
		if ( $status['can_manage_settings'] && ! empty( $key['masked_key'] ) ) {
			$status['masked_key'] = $key['masked_key'];
		}

		return new WP_REST_Response( $status, 200 );
	}

	/**
	 * Extract the text of an uploaded PDF or Word file.
	 *
	 * @since 3.2.0
	 *
	 * @param WP_REST_Request $request Request (multipart, field "file").
	 * @return WP_REST_Response|WP_Error The text and file details, or an error.
	 */
	public function extract( $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return new WP_Error( 'ppq_no_file', __( 'No file was uploaded.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		// Each element sanitized, as the image upload route does.
		$raw  = $files['file'];
		$file = [
			'name'     => isset( $raw['name'] ) ? sanitize_file_name( wp_unslash( $raw['name'] ) ) : '',
			'type'     => isset( $raw['type'] ) ? sanitize_mime_type( wp_unslash( $raw['type'] ) ) : '',
			// A PHP-generated server path; the processor checks the file itself.
			'tmp_name' => isset( $raw['tmp_name'] ) ? (string) $raw['tmp_name'] : '',
			'error'    => isset( $raw['error'] ) ? absint( $raw['error'] ) : UPLOAD_ERR_NO_FILE,
			'size'     => isset( $raw['size'] ) ? absint( $raw['size'] ) : 0,
		];

		$processor = new PressPrimer_Quiz_File_Processor();
		$result    = $processor->process_upload( $file );

		if ( is_wp_error( $result ) ) {
			return $this->to_rest_error( $result );
		}

		$text       = (string) $result['text'];
		$char_count = mb_strlen( $text );

		return new WP_REST_Response(
			[
				'text'          => $text,
				'file_name'     => $result['file_name'],
				'file_type'     => $result['file_type'],
				'file_size'     => (int) $result['file_size'],
				'char_count'    => $char_count,
				// Generation reads at most this much of it.
				'was_truncated' => $char_count > PressPrimer_Quiz_AI_Service::MAX_CONTENT_LENGTH,
			],
			200
		);
	}

	/**
	 * Generate questions from content.
	 *
	 * Body: { content, count, types[], difficulty[], answer_count,
	 * generate_feedback, bank_id? }. With bank_id, the user must be able to
	 * save there, checked before the provider is called.
	 *
	 * @since 3.2.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error The generated questions, or an error.
	 */
	public function generate( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		$user_id = get_current_user_id();

		// A bank to save into: refuse before spending the site's quota.
		$bank_id = isset( $data['bank_id'] ) ? absint( $data['bank_id'] ) : 0;
		if ( $bank_id ) {
			$bank = $this->get_bank_for_saving( $bank_id );
			if ( is_wp_error( $bank ) ) {
				return $bank;
			}
		}

		$api_key = PressPrimer_Quiz_AI_Service::get_api_key_for_provider();
		if ( empty( $api_key ) || is_wp_error( $api_key ) ) {
			return new WP_Error(
				'ppq_no_api_key',
				__( 'No AI API key is configured. Add one in Settings → Integrations.', 'pressprimer-quiz' ),
				[ 'status' => self::error_status( 'ppq_no_api_key' ) ]
			);
		}

		// Images become an "(image)" marker before the HTML is stripped, so
		// the model knows one was there (it never receives the image).
		$content = isset( $data['content'] ) && is_string( $data['content'] ) ? $data['content'] : '';
		$content = sanitize_textarea_field( preg_replace( '/<img\b[^>]*>/i', '(image)', $content ) );

		if ( '' === trim( $content ) ) {
			return new WP_Error( 'ppq_empty_content', __( 'Enter text or upload a file to generate questions from.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		$params = [
			'count'             => max( 1, min( self::MAX_QUESTIONS, absint( $data['count'] ?? 5 ) ) ),
			'types'             => $this->pick( $data['types'] ?? [], self::TYPES, [ 'mc' ] ),
			'difficulty'        => $this->pick( $data['difficulty'] ?? [], array_keys( self::DIFFICULTIES ), [ 'medium' ] ),
			'answer_count'      => max( 3, min( 6, absint( $data['answer_count'] ?? 4 ) ) ),
			'generate_feedback' => isset( $data['generate_feedback'] ) ? rest_sanitize_boolean( $data['generate_feedback'] ) : true,
			'user_id'           => $user_id,
		];

		// Reserve the questions asked for against the monthly limit before
		// the call (429 ppq_ai_limit_reached at the limit), so two open tabs
		// can't overrun it.
		$reservation = PressPrimer_Quiz_AI_Usage_Service::reserve( $user_id, $params['count'], 'questions' );
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}

		// A long generation still settles if the browser goes away.
		ignore_user_abort( true );

		$ai_service = new PressPrimer_Quiz_AI_Service( $api_key );

		$model = PressPrimer_Quiz_AI_Service::get_model_preference( $user_id );
		if ( $model ) {
			$ai_service->set_model( $model );
		}

		$result = $ai_service->generate_from_content( $content, 'text', $params );

		// Charged for the valid questions returned; nothing when the call
		// failed or none were usable.
		$valid = is_wp_error( $result ) ? 0 : count( (array) $result['questions'] );
		if ( $valid > 0 ) {
			PressPrimer_Quiz_AI_Usage_Service::settle( $reservation, $valid, PressPrimer_Quiz_AI_Usage_Service::count_tokens( $result['token_usage'] ?? [] ) );
		} else {
			PressPrimer_Quiz_AI_Usage_Service::release( $reservation );
		}

		if ( is_wp_error( $result ) ) {
			return $this->to_rest_error( $result );
		}

		$response = [
			'questions'    => $result['questions'],
			'content_info' => $result['content_info'],
			'token_usage'  => isset( $result['token_usage'] ) ? $result['token_usage'] : [],
			'usage'        => PressPrimer_Quiz_AI_Usage_Service::get_usage( $user_id ),
		];

		// Partial success: some generated questions didn't pass validation.
		if ( isset( $result['validation'] ) ) {
			$response['validation'] = $result['validation'];
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Everyone's AI usage this month, for Settings.
	 *
	 * @since 3.2.0
	 *
	 * @return WP_REST_Response { period, resets_at, enabled, limit, users[] }.
	 */
	public function get_usage_report() {
		return new WP_REST_Response(
			[
				'period'    => PressPrimer_Quiz_AI_Usage_Service::get_period(),
				'resets_at' => PressPrimer_Quiz_AI_Usage_Service::get_resets_at(),
				'enabled'   => PressPrimer_Quiz_AI_Usage_Service::is_enabled(),
				'limit'     => PressPrimer_Quiz_AI_Usage_Service::get_site_limit(),
				'users'     => PressPrimer_Quiz_AI_Usage_Service::get_report(),
			],
			200
		);
	}

	/**
	 * Save generated questions into a bank.
	 *
	 * Body: { questions[], categories[] }. Each question is checked and saved
	 * on its own through the shared create path, published, authored by the
	 * user; one that fails is reported in errors and the rest still save.
	 *
	 * @since 3.2.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error { saved_count, question_ids, errors }, or an error.
	 */
	public function save_questions( $request ) {
		$bank = $this->get_bank_for_saving( absint( $request['id'] ) );
		if ( is_wp_error( $bank ) ) {
			return $bank;
		}

		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		$questions = isset( $data['questions'] ) && is_array( $data['questions'] ) ? array_values( $data['questions'] ) : [];

		if ( empty( $questions ) ) {
			return new WP_Error( 'ppq_no_questions_sent', __( 'Select at least one question to save.', 'pressprimer-quiz' ), [ 'status' => 400 ] );
		}

		if ( count( $questions ) > self::MAX_QUESTIONS ) {
			return new WP_Error(
				'ppq_too_many_questions',
				sprintf(
					/* translators: %d: most questions per save. */
					__( 'Save at most %d questions at a time.', 'pressprimer-quiz' ),
					self::MAX_QUESTIONS
				),
				[ 'status' => 400 ]
			);
		}

		$category_ids = $this->existing_category_ids( $data['categories'] ?? [] );
		$saved_ids    = [];
		$errors       = [];

		foreach ( $questions as $index => $raw ) {
			$question = $this->save_question( $raw, $category_ids, (int) $bank->id );

			if ( is_wp_error( $question ) ) {
				$errors[] = sprintf(
					/* translators: 1: question number, 2: error message */
					__( 'Question %1$d: %2$s', 'pressprimer-quiz' ),
					$index + 1,
					$question->get_error_message()
				);
				continue;
			}

			$saved_ids[] = (int) $question->id;
		}

		if ( empty( $saved_ids ) ) {
			return new WP_Error(
				'ppq_no_questions_saved',
				__( 'None of the questions could be saved.', 'pressprimer-quiz' ),
				[
					'status' => 422,
					'errors' => $errors,
				]
			);
		}

		return new WP_REST_Response(
			[
				'saved_count'  => count( $saved_ids ),
				'question_ids' => $saved_ids,
				'errors'       => $errors,
				'message'      => sprintf(
					/* translators: %d: number of questions saved */
					_n( '%d question saved to bank.', '%d questions saved to bank.', count( $saved_ids ), 'pressprimer-quiz' ),
					count( $saved_ids )
				),
			],
			200
		);
	}

	/**
	 * Save one generated question: published, authored by the user, in the
	 * bank, through the shared create path.
	 *
	 * @since 3.2.0
	 *
	 * @param mixed $raw          A question from the request.
	 * @param int[] $category_ids Categories to set.
	 * @param int   $bank_id      Bank to add it to.
	 * @return PressPrimer_Quiz_Question|WP_Error The question, or an error.
	 */
	private function save_question( $raw, array $category_ids, $bank_id ) {
		$fields = $this->sanitize_question( $raw );

		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		$fields['status']       = 'published';
		$fields['author_id']    = get_current_user_id();
		$fields['source']       = 'ai';
		$fields['category_ids'] = $category_ids;
		$fields['bank_ids']     = [ $bank_id ];

		return PressPrimer_Quiz_Question::create_with_content( $fields, [ 'revision_source' => 'ai' ] );
	}

	/**
	 * A bank the user may save questions into (its owner, or manage_all).
	 *
	 * @since 3.2.0
	 *
	 * @param int $bank_id Bank ID.
	 * @return PressPrimer_Quiz_Bank|WP_Error The bank, or 404/403.
	 */
	private function get_bank_for_saving( $bank_id ) {
		$bank = $bank_id ? PressPrimer_Quiz_Bank::get( $bank_id ) : null;

		if ( ! $bank || ! empty( $bank->deleted_at ) ) {
			return new WP_Error( 'not_found', __( 'Bank not found.', 'pressprimer-quiz' ), [ 'status' => 404 ] );
		}

		if ( ! empty( PressPrimer_Quiz_Question_Access_Service::get_unowned_bank_ids( [ (int) $bank->id ], get_current_user_id() ) ) ) {
			return new WP_Error( 'forbidden', __( 'You can\'t add questions to this bank.', 'pressprimer-quiz' ), [ 'status' => 403 ] );
		}

		return $bank;
	}

	/**
	 * One generated question, sanitized for saving.
	 *
	 * @since 3.2.0
	 *
	 * @param mixed $data A question from the request.
	 * @return array|WP_Error Fields for create_with_content(), or an error.
	 */
	private function sanitize_question( $data ) {
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid question data.', 'pressprimer-quiz' ) );
		}

		$type = isset( $data['type'] ) ? sanitize_key( $data['type'] ) : '';
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return new WP_Error( 'invalid_type', __( 'Invalid question type.', 'pressprimer-quiz' ) );
		}

		// The generator's levels (easy to expert); a question's own levels are accepted too.
		$difficulty = isset( $data['difficulty'] ) ? sanitize_key( $data['difficulty'] ) : 'medium';
		if ( isset( self::DIFFICULTIES[ $difficulty ] ) ) {
			$difficulty = self::DIFFICULTIES[ $difficulty ];
		} elseif ( ! in_array( $difficulty, self::DIFFICULTIES, true ) ) {
			$difficulty = 'intermediate';
		}

		$answers = [];
		foreach ( (array) ( $data['answers'] ?? [] ) as $answer ) {
			if ( ! is_array( $answer ) || ! isset( $answer['text'] ) || ! is_scalar( $answer['text'] ) ) {
				continue;
			}

			$text = sanitize_textarea_field( (string) $answer['text'] );
			if ( '' === $text ) {
				continue;
			}

			$answers[] = [
				'text'       => $text,
				'is_correct' => ! empty( $answer['is_correct'] ),
				'feedback'   => isset( $answer['feedback'] ) && is_scalar( $answer['feedback'] ) ? sanitize_textarea_field( (string) $answer['feedback'] ) : '',
			];
		}

		$stem = isset( $data['stem'] ) && is_string( $data['stem'] ) ? $data['stem'] : '';

		return [
			'type'               => $type,
			'difficulty_author'  => $difficulty,
			'stem'               => PressPrimer_Quiz_Helpers::trim_trailing_empty_html( wp_kses_post( $stem ) ),
			'answers'            => $answers,
			'feedback_correct'   => isset( $data['feedback_correct'] ) && is_scalar( $data['feedback_correct'] ) ? sanitize_textarea_field( (string) $data['feedback_correct'] ) : '',
			'feedback_incorrect' => isset( $data['feedback_incorrect'] ) && is_scalar( $data['feedback_incorrect'] ) ? sanitize_textarea_field( (string) $data['feedback_incorrect'] ) : '',
		];
	}

	/**
	 * The IDs among those sent that are existing categories.
	 *
	 * @since 3.2.0
	 *
	 * @param mixed $ids Category IDs from the request.
	 * @return int[] Existing category IDs.
	 */
	private function existing_category_ids( $ids ) {
		$existing = [];

		foreach ( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) as $id ) {
			$category = PressPrimer_Quiz_Category::get( $id );
			if ( $category && 'category' === $category->taxonomy ) {
				$existing[] = (int) $id;
			}
		}

		return $existing;
	}

	/**
	 * Keep the allowed values from a list.
	 *
	 * @since 3.2.0
	 *
	 * @param mixed    $values   Values from the request.
	 * @param string[] $allowed  Allowed values.
	 * @param string[] $fallback Used when none are allowed.
	 * @return string[] Allowed values, or the fallback.
	 */
	private function pick( $values, array $allowed, array $fallback ) {
		$values = array_values( array_intersect( array_map( 'sanitize_key', array_filter( (array) $values, 'is_scalar' ) ), $allowed ) );

		return empty( $values ) ? $fallback : array_values( array_unique( $values ) );
	}

	/**
	 * An AI or file error with its HTTP status, keeping its details.
	 *
	 * @since 3.2.0
	 *
	 * @param WP_Error $error Error from the AI service or the file processor.
	 * @return WP_Error The error with a status in its data.
	 */
	private function to_rest_error( WP_Error $error ) {
		$code = $error->get_error_code();
		$data = $error->get_error_data();
		$data = is_array( $data ) ? $data : [];

		$data['status'] = self::error_status( $code );

		return new WP_Error( $code, $error->get_error_message(), $data );
	}
}
