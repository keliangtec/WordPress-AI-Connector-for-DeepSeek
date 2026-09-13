<?php
/**
 * Tests for administrator-defined DeepSeek pricing rules.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use Guducat\DeepSeekAiProvider\Admin\DeepSeekPricingSection;
use Guducat\DeepSeekAiProvider\Pricing\DeepSeekPricingSettings;
use PHPUnit\Framework\TestCase;

/** Verifies the small pricing-rule administration workflow. */
final class DeepSeekPricingSectionTest extends TestCase {
	/** Reset request and option state. */
	protected function setUp(): void {
		$_POST                                    = array();
		$GLOBALS['deepseek_test_options']         = array();
		$GLOBALS['deepseek_test_can_manage']      = true;
		$GLOBALS['deepseek_test_nonce_valid']     = true;
		$GLOBALS['deepseek_test_option_autoload'] = null;
	}

	/** Verify a valid flat custom rule is persisted without autoloading. */
	public function test_process_request_saves_custom_flat_rule(): void {
		$_POST = array(
			DeepSeekPricingSection::ACTION_FIELD => 'save',
			'deepseek_pricing_id'                => 'future-flash',
			'deepseek_pricing_label'             => 'Future Flash',
			'deepseek_pricing_models'            => 'deepseek-flash, deepseek-v4.2-flash',
			'deepseek_pricing_effective'         => '2026-09-15T00:00:00Z',
			'deepseek_pricing_currency'          => 'CNY',
			'deepseek_pricing_mode'              => 'flat',
			'deepseek_pricing_flat_hit'          => '0.03',
			'deepseek_pricing_flat_miss'         => '1.20',
			'deepseek_pricing_flat_output'       => '4.50',
		);

		$result = ( new DeepSeekPricingSection() )->process_request();

		$this->assertFalse( $result['error'] );
		$this->assertCount( 1, DeepSeekPricingSettings::get() );
		$this->assertSame( false, $GLOBALS['deepseek_test_option_autoload'] );
		$this->assertSame( array( 'deepseek-flash', 'deepseek-v4.2-flash' ), DeepSeekPricingSettings::get()[0]['model_ids'] );
	}

	/** Verify a peak/off-peak rule and schedule can be entered before a plugin update. */
	public function test_process_request_saves_custom_tiered_rule(): void {
		$_POST = array(
			DeepSeekPricingSection::ACTION_FIELD => 'save',
			'deepseek_pricing_id'                => 'future-tiered',
			'deepseek_pricing_label'             => 'Future Tiered',
			'deepseek_pricing_models'            => 'deepseek-flash',
			'deepseek_pricing_effective'         => '2026-09-15T00:00:00Z',
			'deepseek_pricing_currency'          => 'CNY',
			'deepseek_pricing_mode'              => 'tiered',
			'deepseek_pricing_off_peak_hit'      => '0.03',
			'deepseek_pricing_off_peak_miss'     => '1.20',
			'deepseek_pricing_off_peak_output'   => '4.50',
			'deepseek_pricing_peak_hit'          => '0.06',
			'deepseek_pricing_peak_miss'         => '2.40',
			'deepseek_pricing_peak_output'       => '9.00',
			'deepseek_pricing_weekdays'          => '1,2,3,4,5',
			'deepseek_pricing_windows'           => '01:00-04:00,06:00-10:00',
		);

		$result = ( new DeepSeekPricingSection() )->process_request();
		$rule   = DeepSeekPricingSettings::get()[0];

		$this->assertFalse( $result['error'] );
		$this->assertSame( '0.03', $rule['rates']['off_peak']['cache_hit'] );
		$this->assertSame( '9', $rule['rates']['peak']['output'] );
		$this->assertSame( array( 1, 2, 3, 4, 5 ), $rule['peak_schedule']['weekdays'] );
		$this->assertSame( array( array( '01:00', '04:00' ), array( '06:00', '10:00' ) ), $rule['peak_schedule']['windows'] );
	}

	/** Verify an existing rule can be disabled without deleting it. */
	public function test_process_request_disables_custom_rule(): void {
		DeepSeekPricingSettings::save(
			array(
				array(
					'id'                 => 'future-flash',
					'label'              => 'Future Flash',
					'model_ids'          => array( 'deepseek-flash' ),
					'effective_from_utc' => '2026-09-15T00:00:00Z',
					'currency'           => 'CNY',
					'rates'              => array(
						'flat' => array(
							'cache_hit'  => '0.03',
							'cache_miss' => '1.2',
							'output'     => '4.5',
						),
					),
				),
			)
		);
		$_POST = array(
			DeepSeekPricingSection::ACTION_FIELD => 'disable',
			'deepseek_pricing_id'                => 'future-flash',
		);

		$result = ( new DeepSeekPricingSection() )->process_request();

		$this->assertFalse( $result['error'] );
		$this->assertFalse( DeepSeekPricingSettings::get()[0]['active'] );
	}

	/** Verify malformed input is rejected and does not replace existing settings. */
	public function test_process_request_rejects_invalid_rule(): void {
		$_POST = array(
			DeepSeekPricingSection::ACTION_FIELD => 'save',
			'deepseek_pricing_id'                => 'bad-rule',
			'deepseek_pricing_label'             => 'Bad rule',
			'deepseek_pricing_models'            => 'bad model',
			'deepseek_pricing_effective'         => 'tomorrow',
			'deepseek_pricing_currency'          => 'CNY',
			'deepseek_pricing_mode'              => 'flat',
		);

		$result = ( new DeepSeekPricingSection() )->process_request();

		$this->assertTrue( $result['error'] );
		$this->assertSame( array(), DeepSeekPricingSettings::get() );
	}
}
