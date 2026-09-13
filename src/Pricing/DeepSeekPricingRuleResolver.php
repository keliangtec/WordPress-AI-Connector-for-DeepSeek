<?php
/**
 * DeepSeek pricing rule resolver.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider\Pricing;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/** Selects the effective model rule and flat or peak rate tier. */
final class DeepSeekPricingRuleResolver {
	/**
	 * Resolve a rule for one request.
	 *
	 * @param string                   $model_id     Provider model ID.
	 * @param DateTimeInterface|string $requested_at Request timestamp.
	 * @param array<int, mixed>        $rules        Pricing rules.
	 * @return array{rule: array<string, mixed>, tier: string, rates: array<string, string>}|null
	 */
	public function resolve( string $model_id, $requested_at, array $rules ): ?array {
		$request_time = $this->normalize_time( $requested_at );
		if ( null === $request_time ) {
			return null;
		}

		$candidates = array();
		foreach ( $rules as $position => $rule ) {
			if ( ! is_array( $rule ) || false === ( $rule['active'] ?? true ) ) {
				continue;
			}
			if ( ! in_array( $model_id, is_array( $rule['model_ids'] ?? null ) ? $rule['model_ids'] : array(), true ) ) {
				continue;
			}

			$effective_from = $this->normalize_time( $rule['effective_from_utc'] ?? null );
			if ( null === $effective_from || $effective_from > $request_time ) {
				continue;
			}

			$candidates[] = array(
				'rule'           => $rule,
				'effective_from' => $effective_from,
				'custom'         => 'custom' === ( $rule['source'] ?? '' ) ? 1 : 0,
				'position'       => $position,
			);
		}

		if ( array() === $candidates ) {
			return null;
		}

		usort(
			$candidates,
			static function ( array $left, array $right ): int {
				$time_order = $right['effective_from']->getTimestamp() <=> $left['effective_from']->getTimestamp();
				if ( 0 !== $time_order ) {
					return $time_order;
				}
				$source_order = $right['custom'] <=> $left['custom'];
				if ( 0 !== $source_order ) {
					return $source_order;
				}

				return $right['position'] <=> $left['position'];
			}
		);

		$rule = $candidates[0]['rule'];
		$tier = isset( $rule['rates']['flat'] ) ? 'flat' : ( $this->is_peak( $request_time, $rule['peak_schedule'] ?? array() ) ? 'peak' : 'off_peak' );
		if ( ! isset( $rule['rates'][ $tier ] ) || ! is_array( $rule['rates'][ $tier ] ) ) {
			return null;
		}

		return array(
			'rule'  => $rule,
			'tier'  => $tier,
			'rates' => $rule['rates'][ $tier ],
		);
	}

	/**
	 * Normalize an input timestamp to UTC.
	 *
	 * @param mixed $value Timestamp.
	 */
	private function normalize_time( $value ): ?DateTimeImmutable {
		if ( $value instanceof DateTimeInterface ) {
			return ( new DateTimeImmutable( $value->format( 'Y-m-d H:i:s.uP' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) );
		}
		if ( ! is_string( $value ) ) {
			return null;
		}

		try {
			$date = new DateTimeImmutable( $value );
		} catch ( Exception $exception ) {
			unset( $exception );
			return null;
		}

		return $date->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Check the selected rule's peak schedule.
	 *
	 * @param DateTimeImmutable   $request_time UTC request time.
	 * @param array<string,mixed> $schedule     Peak schedule.
	 */
	private function is_peak( DateTimeImmutable $request_time, array $schedule ): bool {
		if ( ! is_string( $schedule['timezone'] ?? null ) ) {
			return false;
		}

		try {
			$local_time = $request_time->setTimezone( new DateTimeZone( $schedule['timezone'] ) );
		} catch ( Exception $exception ) {
			unset( $exception );
			return false;
		}

		$weekday = (int) $local_time->format( 'N' );
		if ( ! in_array( $weekday, is_array( $schedule['weekdays'] ?? null ) ? $schedule['weekdays'] : array(), true ) ) {
			return false;
		}

		$time = $local_time->format( 'H:i' );
		foreach ( is_array( $schedule['windows'] ?? null ) ? $schedule['windows'] : array() as $window ) {
			if ( is_array( $window ) && isset( $window[0], $window[1] ) && $time >= $window[0] && $time < $window[1] ) {
				return true;
			}
		}

		return false;
	}
}
