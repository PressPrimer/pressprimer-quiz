<?php
/**
 * Upgrade page view.
 *
 * Rendered by PressPrimer_Quiz_Upgrade_Page::render_page(). Receives the
 * following variables in scope:
 *
 * @var array  $features          Comparison features array (from get_comparison_features).
 * @var array  $tiers             Tier metadata array (from get_tiers).
 * @var string $pricing_url       Pricing page URL.
 * @var string $logo_url          URL of the white PressPrimer logo SVG.
 * @var string $hero_mascot_url   URL of the hero mascot image (brown cheering squirrel).
 * @var string $footer_mascot_url URL of the footer mascot image (confetti celebration).
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
		<div class="ppq-upgrade-hero-content">
			<img src="<?php echo esc_url( $logo_url ); ?>"
				alt="<?php esc_attr_e( 'PressPrimer', 'pressprimer-quiz' ); ?>"
				class="ppq-upgrade-hero-logo" />
			<h1><?php esc_html_e( 'Unlock the Full PressPrimer Quiz Experience', 'pressprimer-quiz' ); ?></h1>
			<p class="ppq-upgrade-intro">
				<?php esc_html_e( 'You\'re already using the most flexible free quiz plugin for WordPress. Premium add-ons add the groups, analytics, compliance, and white-label tools that turn it into a complete assessment platform for your organization.', 'pressprimer-quiz' ); ?>
			</p>
			<a href="<?php echo esc_url( $pricing_url ); ?>"
				class="button button-primary button-hero ppq-upgrade-cta"
				target="_blank"
				rel="noopener noreferrer">
				<?php esc_html_e( 'View Pricing & Upgrade', 'pressprimer-quiz' ); ?>
				<span class="ppq-upgrade-cta-arrow" aria-hidden="true">&rarr;</span>
			</a>
		</div>
		<?php if ( $hero_mascot_url ) : ?>
			<div class="ppq-upgrade-hero-mascot-wrap" aria-hidden="true">
				<img src="<?php echo esc_url( $hero_mascot_url ); ?>"
					alt=""
					class="ppq-upgrade-hero-mascot"
					role="presentation" />
			</div>
		<?php endif; ?>
	</section>

	<section class="ppq-upgrade-tiers" aria-labelledby="ppq-upgrade-tiers-heading">
		<header class="ppq-upgrade-section-header">
			<h2 id="ppq-upgrade-tiers-heading"><?php esc_html_e( 'Choose the Plan That Fits Your Program', 'pressprimer-quiz' ); ?></h2>
			<p><?php esc_html_e( 'Every premium tier includes a year of updates, priority support, and a 14-day money-back guarantee.', 'pressprimer-quiz' ); ?></p>
		</header>

		<div class="ppq-upgrade-tier-cards">
			<?php foreach ( $tiers as $tier_slug => $tier ) : ?>
				<?php
				$is_featured = ! empty( $tier['featured'] );
				$card_class  = 'ppq-upgrade-tier-card ppq-upgrade-tier-' . sanitize_html_class( $tier_slug );
				if ( $is_featured ) {
					$card_class .= ' is-featured';
				}
				?>
				<div class="<?php echo esc_attr( $card_class ); ?>">
					<?php if ( $is_featured ) : ?>
						<span class="ppq-upgrade-tier-badge">
							<?php esc_html_e( 'Most Popular', 'pressprimer-quiz' ); ?>
						</span>
					<?php endif; ?>

					<h3 class="ppq-upgrade-tier-name"><?php echo esc_html( $tier['name'] ); ?></h3>
					<p class="ppq-upgrade-tier-tagline"><?php echo esc_html( $tier['tagline'] ); ?></p>
					<p class="ppq-upgrade-tier-description"><?php echo esc_html( $tier['description'] ); ?></p>

					<?php if ( ! empty( $tier['highlights'] ) && is_array( $tier['highlights'] ) ) : ?>
						<ul class="ppq-upgrade-tier-highlights">
							<?php foreach ( $tier['highlights'] as $highlight ) : ?>
								<li>
									<span class="ppq-upgrade-tier-check" aria-hidden="true">&#10003;</span>
									<?php echo esc_html( $highlight ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<a href="<?php echo esc_url( $tier['url'] ); ?>"
						class="button ppq-upgrade-tier-link <?php echo $is_featured ? 'button-primary' : 'button-secondary'; ?>"
						target="_blank"
						rel="noopener noreferrer">
						<?php
						printf(
							/* translators: %s: tier name (Educator, School, or Enterprise) */
							esc_html__( 'Get %s', 'pressprimer-quiz' ),
							esc_html( $tier['name'] )
						);
						?>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="ppq-upgrade-comparison" aria-labelledby="ppq-upgrade-comparison-heading">
		<header class="ppq-upgrade-section-header">
			<h2 id="ppq-upgrade-comparison-heading"><?php esc_html_e( 'Compare Every Feature', 'pressprimer-quiz' ); ?></h2>
			<p><?php esc_html_e( 'A complete side-by-side of what\'s in each plan.', 'pressprimer-quiz' ); ?></p>
		</header>

		<div class="ppq-upgrade-table-scroller">
			<table class="ppq-upgrade-table">
				<thead>
					<tr>
						<th scope="col" class="ppq-upgrade-col-feature">
							<?php esc_html_e( 'Feature', 'pressprimer-quiz' ); ?>
						</th>
						<th scope="col" class="ppq-upgrade-col-tier ppq-upgrade-col-free">
							<span class="ppq-upgrade-col-name"><?php esc_html_e( 'Free', 'pressprimer-quiz' ); ?></span>
						</th>
						<th scope="col" class="ppq-upgrade-col-tier ppq-upgrade-col-educator">
							<span class="ppq-upgrade-col-name"><?php esc_html_e( 'Educator', 'pressprimer-quiz' ); ?></span>
						</th>
						<th scope="col" class="ppq-upgrade-col-tier ppq-upgrade-col-school">
							<span class="ppq-upgrade-col-name"><?php esc_html_e( 'School', 'pressprimer-quiz' ); ?></span>
							<span class="ppq-upgrade-col-pill"><?php esc_html_e( 'Popular', 'pressprimer-quiz' ); ?></span>
						</th>
						<th scope="col" class="ppq-upgrade-col-tier ppq-upgrade-col-enterprise">
							<span class="ppq-upgrade-col-name"><?php esc_html_e( 'Enterprise', 'pressprimer-quiz' ); ?></span>
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
							<th scope="row" class="ppq-upgrade-cell-label">
								<?php echo esc_html( isset( $row['feature'] ) ? (string) $row['feature'] : '' ); ?>
							</th>
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

	<section class="ppq-upgrade-footer-cta" aria-labelledby="ppq-upgrade-footer-heading">
		<?php if ( $footer_mascot_url ) : ?>
			<div class="ppq-upgrade-footer-mascot-wrap" aria-hidden="true">
				<img src="<?php echo esc_url( $footer_mascot_url ); ?>"
					alt=""
					class="ppq-upgrade-footer-mascot"
					role="presentation" />
			</div>
		<?php endif; ?>
		<div class="ppq-upgrade-footer-content">
			<h2 id="ppq-upgrade-footer-heading"><?php esc_html_e( 'Try Risk-Free for 14 Days', 'pressprimer-quiz' ); ?></h2>
			<p>
				<?php esc_html_e( 'Every premium plan comes with a 14-day money-back guarantee. If PressPrimer Quiz isn\'t the right fit, we\'ll refund your purchase — no questions asked.', 'pressprimer-quiz' ); ?>
			</p>
			<a href="<?php echo esc_url( $pricing_url ); ?>"
				class="button button-primary ppq-upgrade-footer-button"
				target="_blank"
				rel="noopener noreferrer">
				<?php esc_html_e( 'View Pricing & Upgrade', 'pressprimer-quiz' ); ?>
			</a>
		</div>
	</section>

	<div class="ppq-upgrade-sticky-bar" role="region" aria-label="<?php esc_attr_e( 'Upgrade actions', 'pressprimer-quiz' ); ?>">
		<div class="ppq-upgrade-sticky-bar-inner">
			<div class="ppq-upgrade-sticky-bar-text">
				<strong><?php esc_html_e( 'Ready to upgrade PressPrimer Quiz?', 'pressprimer-quiz' ); ?></strong>
				<span><?php esc_html_e( 'Compare plans and pick the tier that fits your program. 14-day money-back guarantee.', 'pressprimer-quiz' ); ?></span>
			</div>
			<a href="<?php echo esc_url( $pricing_url ); ?>"
				class="button button-primary button-hero ppq-upgrade-sticky-bar-cta"
				target="_blank"
				rel="noopener noreferrer">
				<?php esc_html_e( 'Upgrade', 'pressprimer-quiz' ); ?>
			</a>
		</div>
	</div>

</div>
