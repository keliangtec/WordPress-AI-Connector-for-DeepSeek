<?php
/**
 * Tests for DeepSeek custom pricing settings.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use Guducat\DeepSeekAiProvider\Pricing\DeepSeekPricingSettings;
use PHPUnit\Framework\TestCase;

/** Verifies custom pricing rule normalization and persistence. */
final class DeepSeekPricingSettingsTest extends TestCase {
	/** Reset test options. */
	protected function setUp(): void {
		$GLOBALS['deepseek_test_options']         = array();
		$GLOBALS['deepseek_test_option_autoload'] = null;
	}

	/** Verify a valid tiered rule is normalized and saved without autoloading. */
	public function test_save_normalizes_rules_and_disables_autoload(): void {
		$this->assertTrue(
			DeepSeekPricingSettings::save(
				array(
					array(
						'id'                 => ' Custom-Rule-01 ',
						'label'              => ' V4.2 <b>Flash</b> ',
						'model_ids'          => array( 'deepseek-v4.2-flash', 'deepseek-flash', 'bad model', 'deepseek-flash' ),
						'effective_from_utc' => '2026-09-15T00:00:00Z',
						'currency'           => 'cny',
						'rates'              => array(
							'off_peak' => array(
								'cache_hit'  => '0.0300',
								'cache_miss' => '1.20',
								'output'     => '4.50',
							),
							'peak'     => array(
								'cache_hit'  => '0.06',
								'cache_miss' => '2.40',
								'output'     => '9.00',
							),
						),
						'peak_schedule'      => array(
							'timezone' => 'UTC',
							'weekdays' => array( 5, 1, 1, 9 ),
							'windows'  => array( array( '06:00', '10:00' ), array( 'bad', '11:00' ) ),
						),
					),
				)
			)
		);

		$this->assertSame( false, $GLOBALS['deepseek_test_option_autoload'] );
		$rules = DeepSeekPricingSettings::get();
		$this->assertCount( 1, $rules );
		$this->assertSame( 'custom-rule-01', $rules[0]['id'] );
		$this->assertSame( 'V4.2 Flash', $rules[0]['label'] );
		$this->assertSame( array( 'deepseek-flash', 'deepseek-v4.2-flash' ), $rules[0]['model_ids'] );
		$this->assertSame( 'CNY', $rules[0]['currency'] );
		$this->assertSame( 1000000, $rules[0]['unit_tokens'] );
		$this->assertSame( '0.03', $rules[0]['rates']['off_peak']['cache_hit'] );
		$this->assertSame( array( 1, 5 ), $rules[0]['peak_schedule']['weekdays'] );
		$this->assertSame( array( array( '06:00', '10:00' ) ), $rules[0]['peak_schedule']['windows'] );
		$this->assertSame( 'custom', $rules[0]['source'] );
		$this->assertTrue( $rules[0]['active'] );
	}

	/** Verify malformed and incomplete rules are discarded. */
	public function test_sanitize_rejects_invalid_rules(): void {
		$rules = DeepSeekPricingSettings::sanitize(
			array(
				'not-an-array',
				array(
					'id'                 => 'missing-rates',
					'label'              => 'Missing rates',
					'model_ids'          => array( 'deepseek-flash' ),
					'effective_from_utc' => '2026-09-15T00:00:00Z',
					'currency'           => 'CNY',
				),
				array(
					'id'                 => 'bad-time',
					'label'              => 'Bad time',
					'model_ids'          => array( 'deepseek-flash' ),
					'effective_from_utc' => 'tomorrow',
					'currency'           => 'CNY',
					'rates'              => array(
						'flat' => array(
							'cache_hit'  => '1',
							'cache_miss' => '1',
							'output'     => '1',
						),
					),
				),
				array(
					'id'                 => 'negative-price',
					'label'              => 'Negative price',
					'model_ids'          => array( 'deepseek-flash' ),
					'effective_from_utc' => '2026-09-15T00:00:00Z',
					'currency'           => 'CNY',
					'rates'              => array(
						'flat' => array(
							'cache_hit'  => '-1',
							'cache_miss' => '1',
							'output'     => '1',
						),
					),
				),
			)
		);

		$this->assertSame( array(), $rules );
	}

	/** Verify inactive custom rules remain stored for later reactivation. */
	public function test_sanitize_preserves_inactive_rule(): void {
		$rules = DeepSeekPricingSettings::sanitize(
			array(
				$this->flat_rule( array( 'active' => false ) ),
			)
		);

		$this->assertFalse( $rules[0]['active'] );
	}

	/**
	 * Build a valid flat rule.
	 *
	 * @param array<string, mixed> $overrides Rule overrides.
	 * @return array<string, mixed>
	 */
	private function flat_rule( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'                 => 'custom-flat',
				'label'              => 'Custom flat',
				'model_ids'          => array( 'deepseek-flash' ),
				'effective_from_utc' => '2026-09-15T00:00:00Z',
				'currency'           => 'CNY',
				'rates'              => array(
					'flat' => array(
						'cache_hit'  => '0.1',
						'cache_miss' => '1',
						'output'     => '2',
					),
				),
			),
			$overrides
		);
	}
}
