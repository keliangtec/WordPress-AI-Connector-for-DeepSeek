<?php
/**
 * Versioned DeepSeek pricing catalog.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider\Pricing;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/** Provides immutable built-in history and merges custom fallback rules. */
final class DeepSeekPricingCatalog {
	/**
	 * Return confirmed built-in price history.
	 *
	 * Existing entries must remain unchanged so historical request timestamps
	 * keep resolving against the price that applied at the time.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function built_in_rules(): array {
		$peak_schedule = array(
			'timezone' => 'UTC',
			'weekdays' => array( 1, 2, 3, 4, 5 ),
			'windows'  => array( array( '01:00', '04:00' ), array( '06:00', '10:00' ) ),
		);

		return array(
			self::rule(
				'builtin-20260424-v4-flash',
				'V4 Flash',
				array( 'deepseek-flash', 'deepseek-v4-flash' ),
				'2026-04-24T00:00:00Z',
				array( 'flat' => self::rates( '0.02', '1', '2' ) )
			),
			self::rule(
				'builtin-20260424-v4-pro',
				'V4 Pro',
				array( 'deepseek-pro', 'deepseek-v4-pro' ),
				'2026-04-24T00:00:00Z',
				array( 'flat' => self::rates( '0.025', '3', '6' ) )
			),
			self::rule(
				'builtin-20260817-v4-flash',
				'V4 Flash',
				array( 'deepseek-flash', 'deepseek-v4-flash' ),
				'2026-08-17T00:00:00Z',
				array(
					'off_peak' => self::rates( '0.05', '1.5', '4.5' ),
					'peak'     => self::rates( '0.1', '3', '9' ),
				),
				$peak_schedule
			),
			self::rule(
				'builtin-20260817-v4-pro',
				'V4 Pro',
				array( 'deepseek-pro', 'deepseek-v4-pro' ),
				'2026-08-17T00:00:00Z',
				array(
					'off_peak' => self::rates( '0.15', '4.5', '13.5' ),
					'peak'     => self::rates( '0.3', '9', '27' ),
				),
				$peak_schedule
			),
			self::rule(
				'builtin-20260910-v4-1-flash',
				'V4.1 Flash',
				array( 'deepseek-flash', 'deepseek-v4.1-flash' ),
				'2026-09-10T00:00:00Z',
				array(
					'off_peak' => self::rates( '0.02', '1', '4' ),
					'peak'     => self::rates( '0.04', '2', '8' ),
				),
				$peak_schedule
			),
			self::rule(
				'builtin-20260910-v4-pro',
				'V4 Pro',
				array( 'deepseek-pro', 'deepseek-v4-pro' ),
				'2026-09-10T00:00:00Z',
				array(
					'off_peak' => self::rates( '0.15', '4.5', '13.5' ),
					'peak'     => self::rates( '0.3', '9', '27' ),
				),
				$peak_schedule
			),
		);
	}

	/**
	 * Merge built-in history with sanitized custom rules.
	 *
	 * @param mixed $custom_rules Custom rules.
	 * @return list<array<string, mixed>>
	 */
	public static function merge( $custom_rules ): array {
		return array_merge( self::built_in_rules(), DeepSeekPricingSettings::sanitize( $custom_rules ) );
	}

	/**
	 * Return built-in history plus currently persisted custom rules.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function all(): array {
		return array_merge( self::built_in_rules(), DeepSeekPricingSettings::get() );
	}

	/**
	 * Build an immutable built-in rule.
	 *
	 * @param string                              $id             Rule ID.
	 * @param string                              $label          Display label.
	 * @param array<int, string>                  $model_ids      Provider model IDs.
	 * @param string                              $effective_from UTC timestamp.
	 * @param array<string, array<string,string>> $rates          Rate tiers.
	 * @param array<string, mixed>                $schedule       Optional peak schedule.
	 * @return array<string, mixed>
	 */
	private static function rule( string $id, string $label, array $model_ids, string $effective_from, array $rates, array $schedule = array() ): array {
		return array(
			'id'                 => $id,
			'label'              => $label,
			'model_ids'          => $model_ids,
			'effective_from_utc' => $effective_from,
			'currency'           => 'CNY',
			'unit_tokens'        => 1000000,
			'rates'              => $rates,
			'peak_schedule'      => $schedule,
			'source'             => 'built_in',
			'active'             => true,
		);
	}

	/**
	 * Build one rate tier.
	 *
	 * @param string $cache_hit  Cache-hit input price.
	 * @param string $cache_miss Cache-miss input price.
	 * @param string $output     Output price.
	 * @return array{cache_hit: string, cache_miss: string, output: string}
	 */
	private static function rates( string $cache_hit, string $cache_miss, string $output ): array {
		return array(
			'cache_hit'  => $cache_hit,
			'cache_miss' => $cache_miss,
			'output'     => $output,
		);
	}
}
