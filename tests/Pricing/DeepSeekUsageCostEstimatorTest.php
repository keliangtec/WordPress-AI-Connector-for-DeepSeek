<?php
/**
 * Tests for DeepSeek usage cost estimation.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use Guducat\DeepSeekAiProvider\Pricing\DeepSeekPricingCatalog;
use Guducat\DeepSeekAiProvider\Pricing\DeepSeekPricingRuleResolver;
use Guducat\DeepSeekAiProvider\Pricing\DeepSeekUsageCostEstimator;
use PHPUnit\Framework\TestCase;

/** Verifies usage extraction and fixed-precision cost calculation. */
final class DeepSeekUsageCostEstimatorTest extends TestCase {
	/** Verify direct DeepSeek cache fields produce an exact off-peak estimate. */
	public function test_estimates_direct_cache_usage_with_fixed_precision(): void {
		$result = $this->estimator()->estimate(
			'deepseek-flash',
			array(
				'prompt_tokens'             => 1295,
				'completion_tokens'         => 898,
				'total_tokens'              => 2193,
				'prompt_cache_hit_tokens'   => 295,
				'prompt_cache_miss_tokens'  => 1000,
				'completion_tokens_details' => array( 'reasoning_tokens' => 600 ),
			),
			new DateTimeImmutable( '2026-09-13T09:06:11Z' )
		);

		$this->assertSame( '0.00459790', $result['estimated_cost'] );
		$this->assertSame( 'CNY', $result['currency'] );
		$this->assertSame( 295, $result['cache_hit_tokens'] );
		$this->assertSame( 1000, $result['cache_miss_tokens'] );
		$this->assertSame( 898, $result['output_tokens'] );
		$this->assertSame( 600, $result['reasoning_tokens'] );
		$this->assertSame( 'off_peak', $result['pricing_tier'] );
		$this->assertSame( 'builtin-20260910-v4-1-flash', $result['pricing_snapshot']['id'] );
		$this->assertSame( '0.02', $result['pricing_snapshot']['rates']['cache_hit'] );
	}

	/** Verify cached token details can derive hit and miss usage. */
	public function test_derives_cache_usage_from_prompt_token_details(): void {
		$result = $this->estimator()->estimate(
			'deepseek-flash',
			array(
				'prompt_tokens'         => 1000,
				'completion_tokens'     => 200,
				'prompt_tokens_details' => array( 'cached_tokens' => 250 ),
			),
			new DateTimeImmutable( '2026-09-14T01:30:00Z' )
		);

		$this->assertSame( 250, $result['cache_hit_tokens'] );
		$this->assertSame( 750, $result['cache_miss_tokens'] );
		$this->assertSame( 'peak', $result['pricing_tier'] );
		$this->assertSame( '0.00311000', $result['estimated_cost'] );
	}

	/** Verify zero tokens are valid and return a zero estimate, not unavailable. */
	public function test_zero_usage_returns_zero_estimate(): void {
		$result = $this->estimator()->estimate(
			'deepseek-flash',
			array(
				'prompt_cache_hit_tokens'  => 0,
				'prompt_cache_miss_tokens' => 0,
				'completion_tokens'        => 0,
			),
			new DateTimeImmutable( '2026-09-13T12:00:00Z' )
		);

		$this->assertSame( '0.00000000', $result['estimated_cost'] );
	}

	/** Verify decimal arithmetic rounds to eight output places without float drift. */
	public function test_estimate_rounds_half_up_at_output_boundary(): void {
		$rule      = array(
			'id'                 => 'custom-precision',
			'label'              => 'Precision',
			'model_ids'          => array( 'precision-model' ),
			'effective_from_utc' => '2026-01-01T00:00:00Z',
			'currency'           => 'CNY',
			'unit_tokens'        => 1000000,
			'rates'              => array(
				'flat' => array(
					'cache_hit'  => '0.123456789',
					'cache_miss' => '0',
					'output'     => '0',
				),
			),
			'peak_schedule'      => array(),
			'source'             => 'custom',
			'active'             => true,
		);
		$estimator = new DeepSeekUsageCostEstimator( new DeepSeekPricingRuleResolver(), array( $rule ) );

		$result = $estimator->estimate(
			'precision-model',
			array(
				'prompt_cache_hit_tokens'  => 1000000,
				'prompt_cache_miss_tokens' => 0,
				'completion_tokens'        => 0,
			),
			new DateTimeImmutable( '2026-09-13T00:00:00Z' )
		);

		$this->assertSame( '0.12345679', $result['estimated_cost'] );
	}

	/** Verify incomplete, negative, string and excessively large usage is unavailable. */
	public function test_invalid_or_incomplete_usage_returns_null(): void {
		$estimator = $this->estimator();
		$base_time = new DateTimeImmutable( '2026-09-13T12:00:00Z' );

		$this->assertNull( $estimator->estimate( 'unknown', $this->valid_usage(), $base_time ) );
		$this->assertNull( $estimator->estimate( 'deepseek-flash', array( 'completion_tokens' => 1 ), $base_time ) );
		$this->assertNull( $estimator->estimate( 'deepseek-flash', array_merge( $this->valid_usage(), array( 'completion_tokens' => -1 ) ), $base_time ) );
		$this->assertNull( $estimator->estimate( 'deepseek-flash', array_merge( $this->valid_usage(), array( 'completion_tokens' => '1' ) ), $base_time ) );
		$this->assertNull( $estimator->estimate( 'deepseek-flash', array_merge( $this->valid_usage(), array( 'completion_tokens' => PHP_INT_MAX ) ), $base_time ) );
	}

	/**
	 * Build the estimator with current built-in rules.
	 */
	private function estimator(): DeepSeekUsageCostEstimator {
		return new DeepSeekUsageCostEstimator( new DeepSeekPricingRuleResolver(), DeepSeekPricingCatalog::built_in_rules() );
	}

	/**
	 * Return complete valid usage.
	 *
	 * @return array<string, int>
	 */
	private function valid_usage(): array {
		return array(
			'prompt_cache_hit_tokens'  => 1,
			'prompt_cache_miss_tokens' => 1,
			'completion_tokens'        => 1,
		);
	}
}
