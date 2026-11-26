<?php
/**
 * Upgrade page view under white-label.
 *
 * Rendered by PressPrimer_Quiz_Upgrade_Page::render_page() when
 * pressprimer_quiz_white_label_active() is true. White-label needs Enterprise,
 * so every feature is already on the site: a neutral notice with no
 * PressPrimer name, logo or link (A21).
 *
 * @package PressPrimer_Quiz
 * @subpackage Admin
 * @since 3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Upgrade', 'pressprimer-quiz' ); ?></h1>
	<hr class="wp-header-end">
	<div class="notice notice-success inline">
		<p><?php esc_html_e( 'You have every feature available.', 'pressprimer-quiz' ); ?></p>
	</div>
</div>
