<?php
/**
 * Tests for the DeepSeek usage overview.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use Guducat\DeepSeekAiProvider\Admin\DeepSeekCostOverview;
use PHPUnit\Framework\TestCase;

/** Verifies cost overview aggregation without requiring a WordPress database. */
final class DeepSeekCostOverviewTest extends TestCase {
	/** Verify periods normalize to the supported set. */
	public function test_normalizes_unknown_period_to_day(): void {
		$overview = new DeepSeekCostOverview();

		$this->assertSame( 'day', $overview->normalize_period( 'quarter' ) );
		$this->assertSame( 'month', $overview->normalize_period( 'month' ) );
	}

	/** Verify totals, coverage, cache rate, and currency buckets are aggregated. */
	public function test_aggregates_priced_and_unpriced_rows(): void {
		$overview = new DeepSeekCostOverview(
			static function ( string $period ): array {
				unset( $period );
				return array(
					array(
						'model'             => 'deepseek-flash',
						'requests'          => 2,
						'priced_requests'   => 1,
						'tokens'            => 1121,
						'cache_hit_tokens'  => 512,
						'cache_miss_tokens' => 419,
						'output_tokens'     => 190,
						'duration_ms'       => 2079,
						'success_requests'  => 2,
						'currency'          => 'CNY',
						'estimated_cost'    => '0.00118924',
					),
					array(
						'model'             => '',
						'requests'          => 1,
						'priced_requests'   => 0,
						'tokens'            => 0,
						'cache_hit_tokens'  => 0,
						'cache_miss_tokens' => 0,
						'output_tokens'     => 0,
						'duration_ms'       => 0,
						'success_requests'  => 0,
						'currency'          => '',
						'estimated_cost'    => '0',
					),
				);
			}
		);

		$summary = $overview->get_summary( 'week' );

		$this->assertTrue( $summary['available'] );
		$this->assertSame( 'week', $summary['period'] );
		$this->assertSame( 3, $summary['total_requests'] );
		$this->assertSame( 1, $summary['priced_requests'] );
		$this->assertSame( 2, $summary['unpriced_requests'] );
		$this->assertSame( 1121, $summary['total_tokens'] );
		$this->assertSame( 512, $summary['cache_hit_tokens'] );
		$this->assertSame( '0.00118924', $summary['estimated_costs']['CNY'] );
		$this->assertEqualsWithDelta( 33.3333, $summary['pricing_coverage'], 0.001 );
		$this->assertCount( 2, $summary['models'] );
		$this->assertSame( 'deepseek-flash', $summary['models'][0]['model'] );
		$this->assertEqualsWithDelta( 54.9946, $summary['models'][0]['cache_hit_rate'], 0.001 );
	}

	/** Verify an unavailable log table returns an empty, unavailable summary. */
	public function test_reports_unavailable_source(): void {
		$overview = new DeepSeekCostOverview(
			/**
			 * Return no log source for this isolated test.
			 *
			 * @return list<array<string, mixed>>|null
			 */
			static function ( string $period ): ?array {
				unset( $period );
				return null;
			}
		);

		$summary = $overview->get_summary( 'all' );

		$this->assertFalse( $summary['available'] );
		$this->assertSame( 0, $summary['total_requests'] );
	}
}
