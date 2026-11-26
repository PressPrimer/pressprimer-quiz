<?php
/**
 * Admin AI Generation class
 *
 * Mounts the AI question generation panel on the bank page.
 *
 * @package PressPrimer_Quiz
 * @subpackage Admin
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin AI Generation class
 *
 * The panel is React (src/ai-generation/, build/ai-generation.js) and talks
 * to the /ppq/v1/ai routes; the dashboard's bank editor renders the same
 * component. Since 3.2.0 there is no PHP markup or admin-ajax action.
 *
 * @since 1.0.0
 */
class PressPrimer_Quiz_Admin_AI_Generation {

	/**
	 * Render the AI generation panel's mount point and load its script.
	 *
	 * Called while the bank page renders, for users who can edit the bank.
	 *
	 * @since 1.0.0
	 * @since 3.2.0 Mounts the React panel.
	 *
	 * @param int $bank_id Bank ID to add questions to.
	 */
	public function render_panel( $bank_id ) {
		$asset_file = PRESSPRIMER_QUIZ_PLUGIN_PATH . 'build/ai-generation.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : [
			'dependencies' => [ 'wp-element', 'wp-i18n', 'wp-api-fetch' ],
			'version'      => PRESSPRIMER_QUIZ_VERSION,
		];

		wp_enqueue_style(
			'antd',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'assets/css/vendor/antd-reset.css',
			[],
			'5.12.0'
		);

		wp_enqueue_script(
			'ppq-ai-generation',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/ai-generation.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'ppq-ai-generation', 'pressprimer-quiz', PRESSPRIMER_QUIZ_PLUGIN_PATH . 'languages' );

		wp_enqueue_style(
			'ppq-ai-generation',
			PRESSPRIMER_QUIZ_PLUGIN_URL . 'build/style-ai-generation.css',
			[],
			$asset['version']
		);

		?>
		<div id="ppq-ai-generation-root" data-bank-id="<?php echo esc_attr( absint( $bank_id ) ); ?>"></div>
		<?php
	}
}
