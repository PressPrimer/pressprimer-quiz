<?php
/**
 * Premium Catalog Tests
 *
 * The catalog's rules: cells derived from each feature's tier, every report,
 * quiz option and touchpoint carrying its feature's tier, report keys equal
 * to the addons' registrations (fixture), the tier-state helpers, the UTM
 * helpers and the Reports page's cards.
 *
 * @package PressPrimer_Quiz
 * @subpackage Tests
 * @since 3.2.0
 */

use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'pressprimer_quiz_has_addon' ) ) {
	/**
	 * Stub: active tiers come from $GLOBALS['pressprimer_quiz_test_tiers'].
	 *
	 * @param string $tier Tier.
	 * @return bool
	 */
	function pressprimer_quiz_has_addon( $tier ) {
		return in_array( $tier, isset( $GLOBALS['pressprimer_quiz_test_tiers'] ) ? (array) $GLOBALS['pressprimer_quiz_test_tiers'] : array(), true );
	}
}

if ( ! function_exists( 'pressprimer_quiz_show_prompts' ) ) {
	/**
	 * Stub: the prompt switch, on unless $GLOBALS['pressprimer_quiz_test_prompts_off'] is set.
	 *
	 * @param string $type Prompt type.
	 * @return bool
	 */
	function pressprimer_quiz_show_prompts( $type = 'upsell' ) {
		return empty( $GLOBALS['pressprimer_quiz_test_prompts_off'] );
	}
}

/**
 * Premium catalog test case.
 *
 * @since 3.2.0
 */
class Test_Premium_Catalog extends TestCase {

	/**
	 * Start each test with no tiers active and no capabilities.
	 */
	protected function setUp(): void {
		$GLOBALS['pressprimer_quiz_test_tiers'] = array();
		$GLOBALS['pressprimer_quiz_test_caps']  = array();
	}

	/**
	 * Clean up the globals.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['pressprimer_quiz_test_tiers'], $GLOBALS['pressprimer_quiz_test_caps'], $GLOBALS['pressprimer_quiz_test_prompts_off'] );
	}

	/**
	 * A feature is included from its tier up, and nowhere below it.
	 */
	public function test_cells_are_derived_from_the_tier() {
		$expected = array(
			'free'       => array( true, true, true, true ),
			'educator'   => array( false, true, true, true ),
			'school'     => array( false, false, true, true ),
			'enterprise' => array( false, false, false, true ),
		);

		foreach ( $expected as $tier => $cells ) {
			$this->assertSame(
				array_combine( PressPrimer_Quiz_Premium_Catalog::COLUMNS, $cells ),
				PressPrimer_Quiz_Premium_Catalog::feature_cells( array( 'tier' => $tier ) ),
				$tier
			);
		}

		foreach ( PressPrimer_Quiz_Premium_Catalog::get_features() as $feature ) {
			if ( isset( $feature['cells'] ) ) {
				continue;
			}

			$this->assertSame(
				array_combine( PressPrimer_Quiz_Premium_Catalog::COLUMNS, $expected[ $feature['tier'] ] ),
				PressPrimer_Quiz_Premium_Catalog::feature_cells( $feature ),
				$feature['id']
			);
		}
	}

	/**
	 * A text row keeps its own cells, one per column.
	 */
	public function test_text_rows_keep_their_cells() {
		$licenses = PressPrimer_Quiz_Premium_Catalog::get_feature( 'site-licenses' );

		$this->assertSame(
			array(
				'free'       => 'Unlimited',
				'educator'   => '1 site',
				'school'     => '2 sites',
				'enterprise' => '5 sites',
			),
			PressPrimer_Quiz_Premium_Catalog::feature_cells( $licenses )
		);
	}

	/**
	 * Every feature has a unique id, a known category and tier, and a label.
	 */
	public function test_features_are_well_formed() {
		$categories = PressPrimer_Quiz_Premium_Catalog::get_categories();
		$ids        = array();

		foreach ( PressPrimer_Quiz_Premium_Catalog::get_features() as $feature ) {
			$this->assertMatchesRegularExpression( '/^[a-z0-9-]+$/', $feature['id'] );
			$this->assertArrayNotHasKey( $feature['id'], $ids, 'Duplicate feature id ' . $feature['id'] );
			$this->assertArrayHasKey( $feature['category'], $categories, $feature['id'] );
			$this->assertContains( $feature['tier'], PressPrimer_Quiz_Premium_Catalog::COLUMNS, $feature['id'] );
			$this->assertNotSame( '', trim( $feature['label'] ), $feature['id'] );
			$ids[ $feature['id'] ] = true;
		}

		// Every category has at least one feature.
		$used = array_unique( array_column( PressPrimer_Quiz_Premium_Catalog::get_features(), 'category' ) );
		$this->assertSame( array(), array_values( array_diff( array_keys( $categories ), $used ) ) );
	}

	/**
	 * Each report, quiz option and touchpoint names a feature and carries
	 * that feature's tier.
	 */
	public function test_references_carry_their_features_tier() {
		$lists = array(
			'report'      => PressPrimer_Quiz_Premium_Catalog::get_reports(),
			'quiz option' => PressPrimer_Quiz_Premium_Catalog::get_quiz_options(),
			'touchpoint'  => PressPrimer_Quiz_Premium_Catalog::get_touchpoints(),
		);

		foreach ( $lists as $kind => $entries ) {
			$keys = array();

			foreach ( $entries as $id => $entry ) {
				$key     = isset( $entry['key'] ) ? $entry['key'] : $id;
				$feature = PressPrimer_Quiz_Premium_Catalog::get_feature( $entry['feature'] );

				$this->assertArrayNotHasKey( $key, $keys, "Duplicate $kind $key" );
				$this->assertNotNull( $feature, "The $kind $key names an unknown feature {$entry['feature']}" );
				$this->assertSame( $feature['tier'], $entry['tier'], "The $kind $key's tier differs from its feature's" );
				$this->assertContains( $entry['tier'], PressPrimer_Quiz_Premium_Catalog::TIERS, "The $kind $key is for a paid tier" );
				$keys[ $key ] = true;
			}
		}
	}

	/**
	 * Report keys equal the addons' registrations, Educator first, each in
	 * its addon's order.
	 */
	public function test_report_keys_match_the_addons_registrations() {
		$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/addon-report-registrations.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture.
		$reports = PressPrimer_Quiz_Premium_Catalog::get_reports();

		$expected_keys  = array();
		$expected_tiers = array();
		foreach ( PressPrimer_Quiz_Premium_Catalog::TIERS as $tier ) {
			foreach ( $fixture[ $tier ] as $key ) {
				$expected_keys[]  = $key;
				$expected_tiers[] = $tier;
			}
		}

		$this->assertSame( $expected_keys, array_column( $reports, 'key' ) );
		$this->assertSame( $expected_tiers, array_column( $reports, 'tier' ) );
	}

	/**
	 * The tier cards cover the paid tiers, lowest first, with full content.
	 */
	public function test_tiers_are_complete() {
		$tiers = PressPrimer_Quiz_Premium_Catalog::get_tiers();

		$this->assertSame( PressPrimer_Quiz_Premium_Catalog::TIERS, array_keys( $tiers ) );

		foreach ( $tiers as $slug => $tier ) {
			foreach ( array( 'name', 'tagline', 'description', 'url', 'color' ) as $field ) {
				$this->assertNotSame( '', (string) $tier[ $field ], "$slug $field" );
			}
			$this->assertNotEmpty( $tier['highlights'], $slug );
			$this->assertStringStartsWith( 'https://pressprimer.com/', $tier['url'], $slug );
		}
	}

	/**
	 * The audit's corrections stay corrected.
	 */
	public function test_corrected_tiers() {
		$tiers = array(
			'prepost'           => 'educator',
			'assignments'       => 'educator',
			'quiz-detail'       => 'educator',
			'question-quality'  => 'school',
			'spaced-repetition' => 'school',
			'ai-generation'     => 'free',
		);

		foreach ( $tiers as $id => $tier ) {
			$this->assertSame( $tier, PressPrimer_Quiz_Premium_Catalog::get_feature( $id )['tier'], $id );
		}

		$this->assertStringContainsString( '3 built-in themes', PressPrimer_Quiz_Premium_Catalog::get_feature( 'themes' )['label'] );
		$this->assertStringContainsString( 'OpenAI or Anthropic', PressPrimer_Quiz_Premium_Catalog::get_feature( 'ai-generation' )['label'] );

		$cards = PressPrimer_Quiz_Premium_Catalog::get_tiers();
		$this->assertStringNotContainsString( 'quality', strtolower( $cards['educator']['description'] . implode( ' ', $cards['educator']['highlights'] ) ) );
		$this->assertStringNotContainsString( 'pre/post', strtolower( $cards['school']['description'] . implode( ' ', $cards['school']['highlights'] ) ) );
		$this->assertStringNotContainsString( 'availability', strtolower( $cards['school']['description'] . implode( ' ', $cards['school']['highlights'] ) ) );
	}

	/**
	 * The 3.2 features sit at the tier that ships them (A26 rechecks each
	 * one shipped before release).
	 */
	public function test_3_2_feature_tiers() {
		$tiers = array(
			'frontend-authoring' => 'educator',
			'ai-feedback'        => 'educator',
			'share-links'        => 'educator',
			'score-reports'      => 'school',
		);

		foreach ( $tiers as $id => $tier ) {
			$this->assertSame( $tier, PressPrimer_Quiz_Premium_Catalog::get_feature( $id )['tier'], $id );
		}
	}

	/**
	 * Comparison rows follow the category order, with a cell per column.
	 */
	public function test_comparison_rows() {
		$rows       = PressPrimer_Quiz_Premium_Catalog::get_comparison_rows();
		$categories = array_values( PressPrimer_Quiz_Premium_Catalog::get_categories() );

		$this->assertCount( count( PressPrimer_Quiz_Premium_Catalog::get_features() ), $rows );
		$this->assertSame( $categories, array_values( array_unique( array_column( $rows, 'category' ) ) ) );

		foreach ( $rows as $row ) {
			foreach ( PressPrimer_Quiz_Premium_Catalog::COLUMNS as $column ) {
				$this->assertArrayHasKey( $column, $row, $row['id'] );
			}
		}
	}

	/**
	 * School is always "Most Popular"; ownership states for none, Educator,
	 * Educator + School, and every tier.
	 */
	public function test_tier_states() {
		$cases = array(
			array( array(), false ),
			array( array( 'educator' ), false ),
			array( array( 'educator', 'school' ), false ),
			array( array( 'educator', 'school', 'enterprise' ), true ),
		);

		foreach ( $cases as $case ) {
			list( $active, $all ) = $case;
			$GLOBALS['pressprimer_quiz_test_tiers'] = $active;

			$featured = array_keys(
				array_filter(
					PressPrimer_Quiz_Premium_Catalog::get_tiers(),
					function ( $tier ) {
						return ! empty( $tier['featured'] );
					}
				)
			);
			$this->assertSame( array( 'school' ), $featured, implode( '+', $active ) );
			$this->assertSame( $all, PressPrimer_Quiz_Premium_Catalog::all_tiers_active(), implode( '+', $active ) );

			foreach ( PressPrimer_Quiz_Premium_Catalog::TIERS as $tier ) {
				$this->assertSame( in_array( $tier, $active, true ), PressPrimer_Quiz_Premium_Catalog::tier_states()[ $tier ] );
			}
		}

		$this->assertTrue( PressPrimer_Quiz_Premium_Catalog::tier_active( 'free' ) );
		$this->assertFalse( PressPrimer_Quiz_Premium_Catalog::tier_active( 'unknown' ) );
	}

	/**
	 * UTM tags use the suite's names; pricing links land on #pricing.
	 */
	public function test_utm_links() {
		$this->assertSame(
			'https://pressprimer.com/pressprimer-quiz-school/?utm_source=pressprimer-quiz&utm_medium=plugin&utm_campaign=upgrade-page&utm_content=tier-card-school',
			PressPrimer_Quiz_Premium_Catalog::utm_url( 'https://pressprimer.com/pressprimer-quiz-school/', 'upgrade-page', 'tier-card-school' )
		);
		$this->assertSame(
			PressPrimer_Quiz_Premium_Catalog::PRICING_URL . '?utm_source=pressprimer-quiz&utm_medium=plugin&utm_campaign=reports&utm_content=locked-card-proctoring#pricing',
			PressPrimer_Quiz_Premium_Catalog::pricing_url( 'reports', 'locked-card-proctoring' )
		);
	}

	/**
	 * Reports page cards: locked cards for admins only, catalog order kept.
	 */
	public function test_report_cards_for_user() {
		$GLOBALS['pressprimer_quiz_test_tiers'] = array( 'educator' );

		// An admin sees every card; School and Enterprise ones are locked.
		$GLOBALS['pressprimer_quiz_test_caps'] = array( 'manage_options' );
		$cards                                 = PressPrimer_Quiz_Premium_Catalog::report_cards_for_user();
		$this->assertSame( array_column( PressPrimer_Quiz_Premium_Catalog::get_reports(), 'key' ), array_column( $cards, 'key' ) );
		foreach ( $cards as $card ) {
			$this->assertSame( 'educator' !== $card['tier'], $card['locked'], $card['key'] );
			if ( $card['locked'] ) {
				$this->assertSame( 'School' === $card['tierName'] ? 'school' : 'enterprise', $card['tier'] );
				$this->assertSame( PressPrimer_Quiz_Premium_Catalog::pricing_url( 'reports', 'locked-card-' . $card['key'] ), $card['upgradeUrl'] );
			} else {
				$this->assertArrayNotHasKey( 'upgradeUrl', $card );
			}
		}

		// Anyone else sees only the active tier's reports.
		$GLOBALS['pressprimer_quiz_test_caps'] = array();
		$this->assertSame( array( 'quiz-detail', 'prepost-analysis' ), array_column( PressPrimer_Quiz_Premium_Catalog::report_cards_for_user(), 'key' ) );

		// With upsells switched off, an admin sees no locked cards either.
		$GLOBALS['pressprimer_quiz_test_caps']        = array( 'manage_options' );
		$GLOBALS['pressprimer_quiz_test_prompts_off'] = true;
		$this->assertSame( array( 'quiz-detail', 'prepost-analysis' ), array_column( PressPrimer_Quiz_Premium_Catalog::report_cards_for_user(), 'key' ) );
	}

	/**
	 * Premium tab: the options of every tier that isn't active, for admins
	 * while upsells are on, each with its tier and a tagged link.
	 */
	public function test_quiz_options_for_user() {
		$by_tier = array();
		foreach ( PressPrimer_Quiz_Premium_Catalog::get_quiz_options() as $option ) {
			$by_tier[ $option['tier'] ][] = $option['key'];
		}

		$GLOBALS['pressprimer_quiz_test_caps'] = array( 'manage_options' );
		$states                                = array(
			array(),
			array( 'educator' ),
			array( 'educator', 'school' ),
			array( 'educator', 'school', 'enterprise' ),
		);

		foreach ( $states as $active ) {
			$GLOBALS['pressprimer_quiz_test_tiers'] = $active;

			$expected = array();
			foreach ( PressPrimer_Quiz_Premium_Catalog::TIERS as $tier ) {
				if ( ! in_array( $tier, $active, true ) ) {
					$expected = array_merge( $expected, $by_tier[ $tier ] );
				}
			}

			$options = PressPrimer_Quiz_Premium_Catalog::quiz_options_for_user();
			$label   = implode( '+', $active ) ? implode( '+', $active ) : 'none';
			$this->assertSame( $expected, array_column( $options, 'key' ), $label );

			foreach ( $options as $option ) {
				$this->assertNotEmpty( $option['tierName'], $option['key'] );
				$this->assertNotEmpty( $option['tierColor'], $option['key'] );
				$this->assertSame( PressPrimer_Quiz_Premium_Catalog::pricing_url( 'quiz-editor', 'premium-' . $option['key'] ), $option['upgradeUrl'] );
			}
		}

		// Nobody but an admin gets any, and nobody while upsells are off.
		$GLOBALS['pressprimer_quiz_test_tiers'] = array();
		$GLOBALS['pressprimer_quiz_test_caps']  = array( 'pressprimer_quiz_manage_settings' );
		$this->assertSame( array(), PressPrimer_Quiz_Premium_Catalog::quiz_options_for_user() );

		$GLOBALS['pressprimer_quiz_test_caps']        = array( 'manage_options' );
		$GLOBALS['pressprimer_quiz_test_prompts_off'] = true;
		$this->assertSame( array(), PressPrimer_Quiz_Premium_Catalog::quiz_options_for_user() );
	}
}
