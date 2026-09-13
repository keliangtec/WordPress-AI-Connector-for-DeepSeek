<?php
/**
 * DeepSeek custom pricing settings.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider\Pricing;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/** Stores normalized administrator-defined pricing rules. */
final class DeepSeekPricingSettings {
	public const OPTION_NAME = 'ai_connector_deepseek_pricing_rules';

	private const UNIT_TOKENS = 1000000;

	/**
	 * Return normalized custom pricing rules.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function get(): array {
		$value = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();

		return self::sanitize( $value );
	}

	/**
	 * Persist normalized custom pricing rules without autoloading them.
	 *
	 * @param mixed $value Untrusted settings.
	 */
	public static function save( $value ): bool {
		$rules = self::sanitize( $value );

		if ( function_exists( 'update_option' ) ) {
			return (bool) update_option( self::OPTION_NAME, $rules, false );
		}

		return false;
	}

	/**
	 * Normalize untrusted custom pricing rules.
	 *
	 * Invalid rules are omitted instead of being partly persisted.
	 *
	 * @param mixed $value Untrusted settings.
	 * @return list<array<string, mixed>>
	 */
	public static function sanitize( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$rules = array();
		foreach ( $value as $rule ) {
			$normalized = is_array( $rule ) ? self::sanitize_rule( $rule ) : null;
			if ( null !== $normalized ) {
				$rules[] = $normalized;
			}
		}

		return $rules;
	}

	/**
	 * Normalize one rule.
	 *
	 * @param array<string, mixed> $rule Untrusted rule.
	 * @return array<string, mixed>|null
	 */
	private static function sanitize_rule( array $rule ): ?array {
		$id             = self::sanitize_id( $rule['id'] ?? null );
		$label          = self::sanitize_label( $rule['label'] ?? null );
		$model_ids      = self::sanitize_model_ids( $rule['model_ids'] ?? null );
		$effective_from = self::sanitize_utc_timestamp( $rule['effective_from_utc'] ?? null );
		$currency       = self::sanitize_currency( $rule['currency'] ?? null );
		$rates          = self::sanitize_rates( $rule['rates'] ?? null );

		if ( '' === $id || '' === $label || array() === $model_ids || null === $effective_from || '' === $currency || null === $rates ) {
			return null;
		}

		$peak_schedule = array();
		if ( isset( $rates['off_peak'], $rates['peak'] ) ) {
			$peak_schedule = self::sanitize_peak_schedule( $rule['peak_schedule'] ?? null );
			if ( null === $peak_schedule ) {
				return null;
			}
		}

		return array(
			'id'                 => $id,
			'label'              => $label,
			'model_ids'          => $model_ids,
			'effective_from_utc' => $effective_from,
			'currency'           => $currency,
			'unit_tokens'        => self::UNIT_TOKENS,
			'rates'              => $rates,
			'peak_schedule'      => $peak_schedule,
			'source'             => 'custom',
			'active'             => ! array_key_exists( 'active', $rule ) || false !== filter_var( $rule['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ),
		);
	}

	/**
	 * Normalize a stable rule identifier.
	 *
	 * @param mixed $value Identifier.
	 */
	private static function sanitize_id( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9_-]/', '-', $value ) ?? '';
		$value = trim( preg_replace( '/-+/', '-', $value ) ?? '', '-' );

		return strlen( $value ) <= 128 ? $value : '';
	}

	/**
	 * Normalize a display label.
	 *
	 * @param mixed $value Label.
	 */
	private static function sanitize_label( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( strip_tags( $value ) );

		return '' !== $value && strlen( $value ) <= 160 ? $value : '';
	}

	/**
	 * Normalize provider model identifiers.
	 *
	 * @param mixed $value Model IDs.
	 * @return list<string>
	 */
	private static function sanitize_model_ids( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$model_ids = array();
		foreach ( $value as $model_id ) {
			if ( is_string( $model_id ) && strlen( $model_id ) <= 128 && 1 === preg_match( '/^[A-Za-z0-9._:\/-]+$/', $model_id ) ) {
				$model_ids[] = $model_id;
			}
		}

		$model_ids = array_values( array_unique( $model_ids ) );
		usort( $model_ids, 'strcmp' );

		return $model_ids;
	}

	/**
	 * Normalize an exact UTC timestamp.
	 *
	 * @param mixed $value Timestamp.
	 */
	private static function sanitize_utc_timestamp( $value ): ?string {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value ) ) {
			return null;
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s\Z', $value, new DateTimeZone( 'UTC' ) );
		if ( false === $date || $date->format( 'Y-m-d\TH:i:s\Z' ) !== $value ) {
			return null;
		}

		return $value;
	}

	/**
	 * Normalize an ISO-style currency code.
	 *
	 * @param mixed $value Currency.
	 */
	private static function sanitize_currency( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = strtoupper( trim( $value ) );

		return 1 === preg_match( '/^[A-Z]{3}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize flat or peak/off-peak rates.
	 *
	 * @param mixed $value Rates.
	 * @return array<string, array<string, string>>|null
	 */
	private static function sanitize_rates( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}

		if ( array_key_exists( 'flat', $value ) ) {
			$flat = self::sanitize_rate_set( $value['flat'] );

			return null === $flat ? null : array( 'flat' => $flat );
		}

		$off_peak = self::sanitize_rate_set( $value['off_peak'] ?? null );
		$peak     = self::sanitize_rate_set( $value['peak'] ?? null );
		if ( null === $off_peak || null === $peak ) {
			return null;
		}

		return array(
			'off_peak' => $off_peak,
			'peak'     => $peak,
		);
	}

	/**
	 * Normalize the three token prices in a tier.
	 *
	 * @param mixed $value Rate set.
	 * @return array{cache_hit: string, cache_miss: string, output: string}|null
	 */
	private static function sanitize_rate_set( $value ): ?array {
		if ( ! is_array( $value ) ) {
			return null;
		}

		$cache_hit  = self::sanitize_decimal( $value['cache_hit'] ?? null );
		$cache_miss = self::sanitize_decimal( $value['cache_miss'] ?? null );
		$output     = self::sanitize_decimal( $value['output'] ?? null );
		if ( null === $cache_hit || null === $cache_miss || null === $output ) {
			return null;
		}

		return array(
			'cache_hit'  => $cache_hit,
			'cache_miss' => $cache_miss,
			'output'     => $output,
		);
	}

	/**
	 * Normalize a non-negative decimal with at most nine fractional places.
	 *
	 * @param mixed $value Decimal.
	 */
	private static function sanitize_decimal( $value ): ?string {
		if ( is_int( $value ) ) {
			$value = (string) $value;
		}
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = trim( $value );
		if ( 1 !== preg_match( '/^(?:0|[1-9]\d{0,5})(?:\.\d{1,9})?$/', $value ) ) {
			return null;
		}

		$parts    = explode( '.', $value, 2 );
		$integer  = ltrim( $parts[0], '0' );
		$fraction = isset( $parts[1] ) ? rtrim( $parts[1], '0' ) : '';
		$integer  = '' === $integer ? '0' : $integer;

		return '' === $fraction ? $integer : $integer . '.' . $fraction;
	}

	/**
	 * Normalize a peak schedule.
	 *
	 * @param mixed $value Schedule.
	 * @return array{timezone: string, weekdays: list<int>, windows: list<array{0: string, 1: string}>}|null
	 */
	private static function sanitize_peak_schedule( $value ): ?array {
		if ( ! is_array( $value ) || ! is_string( $value['timezone'] ?? null ) ) {
			return null;
		}

		$timezone = trim( $value['timezone'] );
		try {
			new DateTimeZone( $timezone );
		} catch ( Exception $exception ) {
			unset( $exception );
			return null;
		}

		$weekdays = array();
		foreach ( is_array( $value['weekdays'] ?? null ) ? $value['weekdays'] : array() as $weekday ) {
			if ( is_int( $weekday ) && $weekday >= 1 && $weekday <= 7 ) {
				$weekdays[] = $weekday;
			}
		}
		$weekdays = array_values( array_unique( $weekdays ) );
		sort( $weekdays, SORT_NUMERIC );

		$windows = array();
		foreach ( is_array( $value['windows'] ?? null ) ? $value['windows'] : array() as $window ) {
			if ( ! is_array( $window ) || ! isset( $window[0], $window[1] ) || ! is_string( $window[0] ) || ! is_string( $window[1] ) ) {
				continue;
			}
			if ( self::is_valid_time( $window[0] ) && self::is_valid_time( $window[1] ) && $window[0] < $window[1] ) {
				$windows[] = array( $window[0], $window[1] );
			}
		}

		if ( array() === $weekdays || array() === $windows ) {
			return null;
		}

		return array(
			'timezone' => $timezone,
			'weekdays' => $weekdays,
			'windows'  => $windows,
		);
	}

	/**
	 * Check an HH:MM time.
	 *
	 * @param string $value Time value.
	 */
	private static function is_valid_time( string $value ): bool {
		return 1 === preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value );
	}
}
