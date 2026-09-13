<?php
/**
 * Tests for the DeepSeek pricing catalog and resolver.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use Guducat\DeepSeekAiProvider\Pricing\DeepSeekPricingCatalog;
use Guducat\DeepSeekAiProvider\Pricing\DeepSeekPricingRuleResolver;
use PHPUnit\Framework\TestCase;

/** Verifies built-in history and effective rule selection. */
final class DeepSeekPricingCatalogTest extends TestCase {
	/** Verify all confirmed historical versions remain in the built-in catalog. */
	public function test_catalog_keeps_all_builtin_history(): void {
		$rules = DeepSeekPricingCatalog::built_in_rules();

		$this->assertCount( 6, $rules );
		$this->assertSame(
			array(
				'builtin-20260424-v4-flash',
				'builtin-20260424-v4-pro',
				'builtin-20260817-v4-flash',
				'builtin-20260817-v4-pro',
				'builtin-20260910-v4-1-flash',
				'builtin-20260910-v4-pro',
			),
			array_column( $rules, 'id' )
		);
		$this->assertSame( '0.02', $rules[0]['rates']['flat']['cache_hit'] );
		$this->assertSame( '27', $rules[5]['rates']['peak']['output'] );
	}

	/** Verify the newest active rule effective at request time wins. */
	public function test_resolver_selects_rule_by_model_alias_and_request_time(): void {
		$resolver = new DeepSeekPricingRuleResolver();
		$rules    = DeepSeekPricingCatalog::built_in_rules();

		$april  = $resolver->resolve( 'deepseek-flash', '2026-05-01T12:00:00Z', $rules );
		$latest = $resolver->resolve( 'deepseek-v4.1-flash', '2026-09-13T12:00:00Z', $rules );

		$this->assertSame( 'builtin-20260424-v4-flash', $april['rule']['id'] );
		$this->assertSame( 'flat', $april['tier'] );
		$this->assertSame( 'builtin-20260910-v4-1-flash', $latest['rule']['id'] );
		$this->assertSame( 'off_peak', $latest['tier'] );
	}

	/** Verify a custom rule wins when its effective time equals a built-in rule. */
	public function test_custom_rule_wins_same_effective_time(): void {
		$custom = $this->custom_rule();
		$rules  = DeepSeekPricingCatalog::merge( array( $custom ) );

		$result = ( new DeepSeekPricingRuleResolver() )->resolve( 'deepseek-flash', '2026-09-13T12:00:00Z', $rules );

		$this->assertSame( 'custom-20260910-flash', $result['rule']['id'] );
		$this->assertSame( 'custom', $result['rule']['source'] );
	}

	/** Verify inactive custom rules never override active rules. */
	public function test_inactive_custom_rule_is_ignored(): void {
		$custom           = $this->custom_rule();
		$custom['active'] = false;
		$rules            = DeepSeekPricingCatalog::merge( array( $custom ) );

		$result = ( new DeepSeekPricingRuleResolver() )->resolve( 'deepseek-flash', '2026-09-13T12:00:00Z', $rules );

		$this->assertSame( 'builtin-20260910-v4-1-flash', $result['rule']['id'] );
	}

	/** Verify peak windows are start-inclusive and end-exclusive in their declared timezone. */
	public function test_resolver_applies_peak_window_boundaries(): void {
		$resolver = new DeepSeekPricingRuleResolver();
		$rules    = DeepSeekPricingCatalog::built_in_rules();

		$at_start = $resolver->resolve( 'deepseek-flash', '2026-09-14T01:00:00Z', $rules );
		$at_end   = $resolver->resolve( 'deepseek-flash', '2026-09-14T04:00:00Z', $rules );
		$sunday   = $resolver->resolve( 'deepseek-flash', '2026-09-13T02:00:00Z', $rules );

		$this->assertSame( 'peak', $at_start['tier'] );
		$this->assertSame( 'off_peak', $at_end['tier'] );
		$this->assertSame( 'off_peak', $sunday['tier'] );
	}

	/** Verify unknown or not-yet-effective models do not resolve. */
	public function test_resolver_returns_null_without_effective_match(): void {
		$resolver = new DeepSeekPricingRuleResolver();
		$rules    = DeepSeekPricingCatalog::built_in_rules();

		$this->assertNull( $resolver->resolve( 'unknown-model', '2026-09-13T00:00:00Z', $rules ) );
		$this->assertNull( $resolver->resolve( 'deepseek-flash', '2026-04-23T23:59:59Z', $rules ) );
		$this->assertNull( $resolver->resolve( 'deepseek-flash', 'invalid', $rules ) );
	}

	/**
	 * Build a custom rule which intentionally collides with the current built-in rule.
	 *
	 * @return array<string, mixed>
	 */
	private function custom_rule(): array {
		return array(
			'id'                 => 'custom-20260910-flash',
			'label'              => 'Custom Flash',
			'model_ids'          => array( 'deepseek-flash' ),
			'effective_from_utc' => '2026-09-10T00:00:00Z',
			'currency'           => 'CNY',
			'unit_tokens'        => 1000000,
			'rates'              => array(
				'flat' => array(
					'cache_hit'  => '1',
					'cache_miss' => '2',
					'output'     => '3',
				),
			),
			'peak_schedule'      => array(),
			'source'             => 'custom',
			'active'             => true,
		);
	}
}
