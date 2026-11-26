<?php
/**
 * Upgrade Page Tests
 *
 * The tier cards for each ownership state: which tiers are owned, and where
 * each card links, with its UTM tags.
 *
 * @package PressPrimer_Quiz
 * @subpackage Tests
 * @since 3.2.0
 */

use PHPUnit\Framework\TestCase;

/**
 * Upgrade page test case.
 *
 * @since 3.2.0
 */
class Test_Upgrade_Page extends TestCase {

	/**
	 * Ownership states: none, Educator, Educator + School, every tier.
	 *
	 * @return array[]
	 */
	public function ownership_states() {
		return array(
			'none'             => array( array() ),
			'educator'         => array( array( 'educator' ) ),
			'educator + school' => array( array( 'educator', 'school' ) ),
			'every tier'       => array( array( 'educator', 'school', 'enterprise' ) ),
		);
	}

	/**
	 * Owned tiers link to the knowledge base, the others to their product
	 * page, each tagged with its position.
	 *
	 * @dataProvider ownership_states
	 *
	 * @param string[] $owned Owned tiers.
	 */
	public function test_tier_cards_follow_ownership( array $owned ) {
		$states = array();
		foreach ( PressPrimer_Quiz_Premium_Catalog::TIERS as $tier ) {
			$states[ $tier ] = in_array( $tier, $owned, true );
		}

		$catalog = PressPrimer_Quiz_Premium_Catalog::get_tiers();
		$tiers   = PressPrimer_Quiz_Upgrade_Page::prepare_tiers( $states );

		$this->assertSame( array_keys( $catalog ), array_keys( $tiers ) );

		foreach ( $tiers as $slug => $tier ) {
			$is_owned = in_array( $slug, $owned, true );

			$this->assertSame( $is_owned, $tier['owned'], $slug );
			$this->assertSame( 'school' === $slug, ! empty( $tier['featured'] ), $slug . ': School is always featured' );
			$this->assertStringContainsString( 'utm_source=pressprimer-quiz', $tier['url'], $slug );
			$this->assertStringContainsString( 'utm_medium=plugin', $tier['url'], $slug );
			$this->assertStringContainsString( 'utm_campaign=upgrade-page', $tier['url'], $slug );

			if ( $is_owned ) {
				$this->assertStringStartsWith( PressPrimer_Quiz_Premium_Catalog::KB_URL, $tier['url'], $slug );
				$this->assertStringContainsString( 'utm_content=tier-docs-' . $slug, $tier['url'], $slug );
			} else {
				$this->assertStringStartsWith( $catalog[ $slug ]['url'], $tier['url'], $slug );
				$this->assertStringContainsString( 'utm_content=tier-card-' . $slug, $tier['url'], $slug );
			}
		}
	}
}
