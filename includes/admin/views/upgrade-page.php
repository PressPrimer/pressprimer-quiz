<?php
/**
 * Upgrade page view.
 *
 * Rendered by PressPrimer_Quiz_Upgrade_Page::render_page(). Receives the
 * following variables in scope:
 *
 * @var array  $features     Comparison features array (from get_comparison_features).
 * @var array  $tiers        Tier metadata array (from get_tiers).
 * @var string $pricing_url  Pricing page URL.
 * @var PressPrimer_Quiz_Upgrade_Page $upgrade_page The controller instance, for render_cell_value().
 *
 * @package PressPrimer_Quiz
 * @subpackage Admin
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap ppq-upgrade-page">

	<section class="ppq-upgrade-hero">
		<h1><?php esc_html_e( 'Upgrade PressPrimer Quiz', 'pressprimer-quiz' ); ?></h1>
		<p class="ppq-upgrade-intro">
			<?php esc_html_e( 'Unlock advanced assessment, analytics, and compliance features with PressPrimer Quiz premium add-ons.', 'pressprimer-quiz' ); ?>
		</p>
		<a href="<?php echo esc_url( $pricing_url ); ?>"
			class="button button-primary button-hero ppq-upgrade-cta"
			target="_blank"
			rel="noopener noreferrer">
			<?php esc_html_e( 'View Pricing and Upgrade', 'pressprimer-quiz' ); ?>
		</a>
	</section>

	<section class="ppq-upgrade-comparison">
		<h2><?php esc_html_e( 'Compare Plans', 'pressprimer-quiz' ); ?></h2>
		<div class="ppq-upgrade-table-scroller">
			<table class="ppq-upgrade-table widefat striped">
				<thead>
					<tr>
						<th scope="col" class="ppq-upgrade-col-feature">
							<?php esc_html_e( 'Feature', 'pressprimer-quiz' ); ?>
						</th>
						<th scope="col" class="ppq-upgrade-col-tier ppq-upgrade-col-free">
							<?php esc_html_e( 'Free', 'pressprimer-quiz' ); ?>
						</th>
						<th scope="col" class="ppq-upgrade-col-tier ppq-upgrade-col-educator">
							<?php esc_html_e( 'Educator', 'pressprimer-quiz' ); ?>
						</th>
						<th scope="col" class="ppq-upgrade-col-tier ppq-upgrade-col-school">
							<?php esc_html_e( 'School', 'pressprimer-quiz' ); ?>
						</th>
						<th scope="col" class="ppq-upgrade-col-tier ppq-upgrade-col-enterprise">
							<?php esc_html_e( 'Enterprise', 'pressprimer-quiz' ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$current_category = '';
					foreach ( $features as $row ) :
						$row_category = isset( $row['category'] ) ? (string) $row['category'] : '';

						// Print a category header row whenever the category changes.
						if ( $row_category !== $current_category ) :
							$current_category = $row_category;
							?>
							<tr class="ppq-upgrade-category-row">
								<th colspan="5" scope="colgroup">
									<?php echo esc_html( $current_category ); ?>
								</th>
							</tr>
							<?php
						endif;
						?>
						<tr>
							<td class="ppq-upgrade-cell-label">
								<?php echo esc_html( isset( $row['feature'] ) ? (string) $row['feature'] : '' ); ?>
							</td>
							<?php foreach ( array( 'free', 'educator', 'school', 'enterprise' ) as $tier_key ) : ?>
								<td class="ppq-upgrade-cell ppq-upgrade-cell-<?php echo esc_attr( $tier_key ); ?>">
									<?php
									// $upgrade_page->render_cell_value() returns hardcoded safe HTML
									// (checkmark span, em dash, or esc_html'd string). Output as-is.
									echo $upgrade_page->render_cell_value( isset( $row[ $tier_key ] ) ? $row[ $tier_key ] : false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>

	<section class="ppq-upgrade-tiers">
		<h2><?php esc_html_e( 'Choose Your Plan', 'pressprimer-quiz' ); ?></h2>
		<div class="ppq-upgrade-tier-cards">
			<?php foreach ( $tiers as $tier_slug => $tier ) : ?>
				<div class="ppq-upgrade-tier-card ppq-upgrade-tier-<?php echo esc_attr( $tier_slug ); ?>">
					<h3 class="ppq-upgrade-tier-name"><?php echo esc_html( $tier['name'] ); ?></h3>
					<p class="ppq-upgrade-tier-tagline"><?php echo esc_html( $tier['tagline'] ); ?></p>
					<p class="ppq-upgrade-tier-description"><?php echo esc_html( $tier['description'] ); ?></p>
					<a href="<?php echo esc_url( $tier['url'] ); ?>"
						class="button button-secondary ppq-upgrade-tier-link"
						target="_blank"
						rel="noopener noreferrer">
						<?php
						printf(
							/* translators: %s: tier name (Educator, School, or Enterprise) */
							esc_html__( 'Learn More about %s', 'pressprimer-quiz' ),
							esc_html( $tier['name'] )
						);
						?>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="ppq-upgrade-footer-cta">
		<h2><?php esc_html_e( 'Ready to Upgrade?', 'pressprimer-quiz' ); ?></h2>
		<p>
			<?php esc_html_e( 'Compare plans, pick the tier that fits your program, and get the features your team needs.', 'pressprimer-quiz' ); ?>
		</p>
		<a href="<?php echo esc_url( $pricing_url ); ?>"
			class="button button-primary button-hero ppq-upgrade-cta"
			target="_blank"
			rel="noopener noreferrer">
			<?php esc_html_e( 'View Pricing and Upgrade', 'pressprimer-quiz' ); ?>
		</a>
	</section>

</div>
