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
					'icon'  => 'welcome-learn-more',
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
		$asset_file = PPQ_PLUGIN_PATH . 'build/blocks/quiz/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Register block script
		wp_register_script(
			'pressprimer-quiz-quiz-block-editor',
			PPQ_PLUGIN_URL . 'build/blocks/quiz/index.js',
			$asset['dependencies'],
			$asset['version']
		);

		// Register editor style
		wp_register_style(
			'pressprimer-quiz-quiz-block-editor-style',
			PPQ_PLUGIN_URL . 'blocks/quiz/editor.css',
			[],
			PPQ_VERSION
		);

		// Register frontend style
		wp_register_style(
			'pressprimer-quiz-quiz-block-style',
			PPQ_PLUGIN_URL . 'blocks/quiz/style.css',
			[],
			PPQ_VERSION
		);

		// Register block type
		register_block_type(
			'pressprimer-quiz/quiz',
			[
				'api_version'     => 2,
				'title'           => __( 'PPQ Quiz', 'pressprimer-quiz' ),
				'description'     => __( 'Display a quiz for users to take.', 'pressprimer-quiz' ),
				'category'        => 'pressprimer-quiz',
				'icon'            => 'welcome-learn-more',
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
		$asset_file = PPQ_PLUGIN_PATH . 'build/blocks/my-attempts/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Register block script
		wp_register_script(
			'pressprimer-quiz-my-attempts-block-editor',
			PPQ_PLUGIN_URL . 'build/blocks/my-attempts/index.js',
			$asset['dependencies'],
			$asset['version']
		);

		// Register editor style
		wp_register_style(
			'pressprimer-quiz-my-attempts-block-editor-style',
			PPQ_PLUGIN_URL . 'blocks/my-attempts/editor.css',
			[],
			PPQ_VERSION
		);

		// Register frontend style
		wp_register_style(
			'pressprimer-quiz-my-attempts-block-style',
			PPQ_PLUGIN_URL . 'blocks/my-attempts/style.css',
			[],
			PPQ_VERSION
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
			'per_page' => $per_page,
		];

		// Call the shortcode handler
		$shortcodes = new PressPrimer_Quiz_Shortcodes();
		$output     = $shortcodes->render_my_attempts( $shortcode_atts );

		// If we need to hide score or date, we could filter the output here
		// For now, we'll use the full output from the shortcode
		// In a future version, we could pass these as parameters to the shortcode

		// Wrap in block div
		return '<div class="wp-block-pressprimer-quiz-my-attempts">' . $output . '</div>';
	}
}
