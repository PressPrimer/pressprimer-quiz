<?php
/**
 * Blocks Registration
 *
 * Registers all Gutenberg blocks for the plugin.
 *
 * @package PressPrimer_Quiz
 * @subpackage Blocks
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocks class
 *
 * Handles registration and rendering of Gutenberg blocks.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Blocks {

	/**
	 * Initialize blocks
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Register block category filter
		add_filter( 'block_categories_all', [ $this, 'register_block_category' ], 10, 2 );

		// Register blocks on init
		add_action( 'init', [ $this, 'register_blocks' ] );
	}

	/**
	 * Register block category
	 *
	 * Creates a custom category for PressPrimer Quiz blocks.
	 *
	 * @since 1.0.0
	 *
	 * @param array                   $categories Array of block categories.
	 * @param WP_Block_Editor_Context $context Block editor context.
	 * @return array Modified categories array.
	 */
	public function register_block_category( $categories, $context ) {
		// Check if category already exists
		foreach ( $categories as $category ) {
			if ( 'pressprimer-quiz' === $category['slug'] ) {
				return $categories;
			}
		}

		// Add our category at the beginning
		return array_merge(
			[
				[
					'slug'  => 'pressprimer-quiz',
					'title' => __( 'PressPrimer Quiz', 'pressprimer-quiz' ),
					'icon'  => $this->get_checkbox_icon(),
				],
			],
			$categories
		);
	}

	/**
	 * Register all blocks
	 *
	 * @since 1.0.0
	 */
	public function register_blocks() {
		// Check if block registration is available
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Register Quiz block
		$this->register_quiz_block();

		// Register My Attempts block
		$this->register_my_attempts_block();
	}

	/**
	 * Register Quiz block
	 *
	 * @since 1.0.0
	 */
	private function register_quiz_block() {
		// Get asset file for dependencies and version
		$asset_file = PRESSPRIMER_QUIZ_PLUGIN_PATH . 'build/blocks/quiz/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Register block script
		wp_register_script(
			'pressprimer-quiz-quiz-block-editor',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/blocks/quiz/index.js',
			$asset['dependencies'],
			$asset['version']
		);

		// Register editor style
		wp_register_style(
			'pressprimer-quiz-quiz-block-editor-style',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'blocks/quiz/editor.css',
			[],
			PRESSPRIMER_QUIZ_VERSION
		);

		// Register frontend style
		wp_register_style(
			'pressprimer-quiz-quiz-block-style',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'blocks/quiz/style.css',
			[],
			PRESSPRIMER_QUIZ_VERSION
		);

		// Register block type
		register_block_type(
			'pressprimer-quiz/quiz',
			[
				'api_version'     => 2,
				'title'           => __( 'PPQ Quiz', 'pressprimer-quiz' ),
				'description'     => __( 'Display a quiz for users to take.', 'pressprimer-quiz' ),
				'category'        => 'pressprimer-quiz',
				'icon'            => $this->get_checkbox_icon(),
				'supports'        => [
					'html'  => false,
					'align' => [ 'wide', 'full' ],
				],
				'editor_script'   => 'pressprimer-quiz-quiz-block-editor',
				'editor_style'    => 'pressprimer-quiz-quiz-block-editor-style',
				'style'           => 'pressprimer-quiz-quiz-block-style',
				'render_callback' => [ $this, 'render_quiz_block' ],
				'attributes'      => [
					'quizId' => [
						'type'    => 'number',
						'default' => 0,
					],
				],
			]
		);
	}

	/**
	 * Render Quiz block
	 *
	 * @since 1.0.0
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered block HTML.
	 */
	public function render_quiz_block( $attributes ) {
		// Get quiz ID from attributes
		$quiz_id = isset( $attributes['quizId'] ) ? absint( $attributes['quizId'] ) : 0;

		// If no quiz selected, show placeholder
		if ( ! $quiz_id ) {
			return '<div class="wp-block-pressprimer-quiz-quiz ppq-block-placeholder">' .
				'<p>' . esc_html__( 'Please select a quiz to display.', 'pressprimer-quiz' ) . '</p>' .
				'</div>';
		}

		// Use the shortcode handler to render
		if ( ! class_exists( 'PressPrimer_Quiz_Shortcodes' ) ) {
			return '<div class="ppq-error">' . esc_html__( 'Quiz renderer not available.', 'pressprimer-quiz' ) . '</div>';
		}

		// Build shortcode attributes
		$shortcode_atts = [
			'id' => $quiz_id,
		];

		// Call the shortcode handler
		$shortcodes = new PressPrimer_Quiz_Shortcodes();
		$output     = $shortcodes->render_quiz( $shortcode_atts );

		// Wrap in block div
		return '<div class="wp-block-pressprimer-quiz-quiz">' . $output . '</div>';
	}

	/**
	 * Register My Attempts block
	 *
	 * @since 1.0.0
	 */
	private function register_my_attempts_block() {
		// Get asset file for dependencies and version
		$asset_file = PRESSPRIMER_QUIZ_PLUGIN_PATH . 'build/blocks/my-attempts/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Register block script
		wp_register_script(
			'pressprimer-quiz-my-attempts-block-editor',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/blocks/my-attempts/index.js',
			$asset['dependencies'],
			$asset['version']
		);

		// Register editor style
		wp_register_style(
			'pressprimer-quiz-my-attempts-block-editor-style',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'blocks/my-attempts/editor.css',
			[],
			PRESSPRIMER_QUIZ_VERSION
		);

		// Register frontend style
		wp_register_style(
			'pressprimer-quiz-my-attempts-block-style',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'blocks/my-attempts/style.css',
			[],
			PRESSPRIMER_QUIZ_VERSION
		);

		// Register block type
		register_block_type(
			'pressprimer-quiz/my-attempts',
			[
				'api_version'     => 2,
				'title'           => __( 'PPQ Quiz Attempts', 'pressprimer-quiz' ),
				'description'     => __( 'Display a list of the current user\'s quiz attempts.', 'pressprimer-quiz' ),
				'category'        => 'pressprimer-quiz',
				'icon'            => 'list-view',
				'supports'        => [
					'html'  => false,
					'align' => true,
				],
				'editor_script'   => 'pressprimer-quiz-my-attempts-block-editor',
				'editor_style'    => 'pressprimer-quiz-my-attempts-block-editor-style',
				'style'           => 'pressprimer-quiz-my-attempts-block-style',
				'render_callback' => [ $this, 'render_my_attempts_block' ],
				'attributes'      => [
					'showScore' => [
						'type'    => 'boolean',
						'default' => true,
					],
					'showDate'  => [
						'type'    => 'boolean',
						'default' => true,
					],
					'perPage'   => [
						'type'    => 'number',
						'default' => 20,
					],
				],
			]
		);
	}

	/**
	 * Render My Attempts block
	 *
	 * @since 1.0.0
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered block HTML.
	 */
	public function render_my_attempts_block( $attributes ) {
		// Get attributes with defaults
		$show_score = isset( $attributes['showScore'] ) ? (bool) $attributes['showScore'] : true;
		$show_date  = isset( $attributes['showDate'] ) ? (bool) $attributes['showDate'] : true;
		$per_page   = isset( $attributes['perPage'] ) ? absint( $attributes['perPage'] ) : 20;

		// Use the shortcode handler to render
		if ( ! class_exists( 'PressPrimer_Quiz_Shortcodes' ) ) {
			return '<div class="ppq-error">' . esc_html__( 'My Attempts renderer not available.', 'pressprimer-quiz' ) . '</div>';
		}

		// Build shortcode attributes
		$shortcode_atts = [
			'per_page'   => $per_page,
			'show_score' => $show_score,
			'show_date'  => $show_date,
		];

		// Call the shortcode handler
		$shortcodes = new PressPrimer_Quiz_Shortcodes();
		$output     = $shortcodes->render_my_attempts( $shortcode_atts );

		// Wrap in block div
		return '<div class="wp-block-pressprimer-quiz-my-attempts">' . $output . '</div>';
	}

	/**
	 * Get checkbox icon SVG for blocks
	 *
	 * Returns an SVG icon element for use in block registration.
	 *
	 * @since 1.0.0
	 *
	 * @return string SVG markup for the checkbox icon.
	 */
	private function get_checkbox_icon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 500" width="24" height="24"><path fill="currentColor" d="M63.8246 93.8664C69.7238 93.625 76.5224 93.7616 82.4678 93.7639L115.028 93.7714L231.875 93.786L306.519 93.8056C313.127 93.8325 319.736 93.8246 326.345 93.7819C330.515 93.7645 335.739 93.6044 339.803 94.0571C335.784 98.997 328.816 105.192 324.323 110.094C312.938 122.517 301.742 135.106 289.958 147.152C288.258 148.89 282.498 155.089 280.872 156.06C274.9 156.452 267.916 156.196 261.848 156.211L225.317 156.197L126.822 156.234L99.8917 156.184C95.4981 156.189 90.973 156.135 86.5922 156.308C85.2689 156.361 82.4004 156.729 81.4489 157.573C79.3746 159.412 78.2728 161.466 78.2073 164.26C78.0998 168.844 78.1089 173.493 78.0967 178.075C78.0481 187.591 78.0611 197.108 78.1356 206.625L78.1093 283.577L78.1371 365.314C78.1836 376.338 77.9529 387.367 78.2183 398.389C78.334 403.192 81.6801 405.957 86.3497 406.025C93.3381 406.127 100.327 406.026 107.315 406.009L142.203 406.019L228.125 405.994L306.411 405.998C316.172 405.948 326.131 406.285 335.91 406.026C338.062 405.969 340.116 405.202 341.688 403.679C342.776 402.635 343.471 401.248 343.655 399.751C344.043 396.804 343.853 392.309 343.818 389.265L343.766 375.522L343.829 320.614C343.782 318.665 343.601 311.421 343.991 309.99C345.373 304.925 352.598 296.897 355.513 292.814L365.956 278.275C367.38 276.266 368.587 273.893 370.19 272.068C372.138 269.85 374.011 267.73 375.822 265.393C382.785 256.303 389.601 247.102 396.269 237.793C399.228 233.748 403.361 228.552 405.903 224.484C405.95 225.81 406.008 227.136 406.074 228.462C406.677 240.354 406.137 254.538 406.185 266.561L406.176 354.75C406.265 370.059 406.264 385.368 406.173 400.676C406.198 408.341 406.72 422.251 405.583 429.251C404.138 438.354 400.073 446.839 393.885 453.669C385.117 463.314 373.35 468.699 360.41 468.915C350.352 469.084 339.971 468.986 329.893 468.931L288.948 468.921L167.625 468.901L93.1583 468.895L71.8118 468.987C68.2198 469.003 64.6471 469.038 61.0818 468.94C36.1892 468.259 15.8616 447.509 15.6245 422.628C15.5379 413.54 15.6118 404.488 15.6712 395.404L15.6926 356.877L15.673 235.017L15.6553 177.228C15.5516 169.319 15.5383 161.408 15.6151 153.499C15.6157 146.775 15.4224 139.255 16.3907 132.619C17.721 123.579 21.7711 115.159 28.0026 108.477C34.6288 101.383 44.7732 96.2328 54.3192 94.5598C57.3833 94.0228 60.7055 93.9618 63.8246 93.8664Z"/><path fill="currentColor" d="M233.157 255.324C237.56 250.315 242.357 245.419 246.815 240.441C249.036 237.96 250.967 235.092 253.354 232.777C257.137 229.106 260.475 226.423 264.121 222.501L348.845 132.009L383.336 95.2861C395.7 82.0073 407.524 68.2207 420.516 55.5298C426.36 49.88 431.296 43.1055 436.958 37.3355C446.958 27.1432 456.567 30.9816 466.631 38.6491C472.465 43.0935 479.097 48.2042 482.861 54.7107C483.828 56.3815 484.076 58.6732 484.35 60.5428C483.999 62.8512 483.771 64.1111 483.139 66.3428C477.579 78.3146 467.966 90.0174 460.267 100.666C457.318 104.746 454.054 108.308 451.017 112.33L413.043 163.535L264.536 362.941L255.525 374.96C252.987 378.359 248.67 384.586 245.524 387.08C243.175 388.965 240.357 390.174 237.373 390.578C222.988 392.583 217.925 379.517 210.85 369.467L178.324 322.308L141.159 268.758C132.027 255.773 123.107 242.597 114.203 229.416C104.048 214.157 110.923 206.109 123.18 196.283C141.865 181.305 149.639 189.35 165.758 202.06C173.582 208.196 181.345 214.41 189.044 220.702C197.546 227.516 206.423 234.029 215.057 240.705C221.145 245.51 227.179 250.383 233.157 255.324Z"/></svg>';
	}
}
