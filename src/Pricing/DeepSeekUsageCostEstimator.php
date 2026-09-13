<?php
/**
 * DeepSeek usage cost estimator.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider\Pricing;

use DateTimeImmutable;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/** Calculates an estimated request cost using integer fixed-point arithmetic. */
class DeepSeekUsageCostEstimator {
	private const PRICE_SCALE = 1000000000;

	private const OUTPUT_SCALE = 100000000;

	private const MAX_TOKENS = 1000000000;

	/**
	 * Rule resolver.
	 *
	 * @var DeepSeekPricingRuleResolver
	 */
	private $resolver;

	/**
	 * Available pricing rules.
	 *
	 * @var list<array<string, mixed>>
	 */
	private $rules;

	/**
	 * Configure the estimator.
	 *
	 * @param DeepSeekPricingRuleResolver|null $resolver Rule resolver.
	 * @param array<int, mixed>|null           $rules    Pricing rules.
	 */
	public function __construct( ?DeepSeekPricingRuleResolver $resolver = null, ?array $rules = null ) {
		$this->resolver = $resolver ?? new DeepSeekPricingRuleResolver();
		$this->rules    = null === $rules ? DeepSeekPricingCatalog::all() : $rules;
	}

	/**
	 * Estimate a request cost and return a stable pricing snapshot.
	 *
	 * @param string            $model        Provider model ID.
	 * @param array<mixed>      $usage        DeepSeek usage payload.
	 * @param DateTimeImmutable $requested_at Request timestamp.
	 * @return array<string, mixed>|null
	 */
	public function estimate( string $model, array $usage, DateTimeImmutable $requested_at ): ?array {
		$resolved = $this->resolver->resolve( $model, $requested_at, $this->rules );
		if ( null === $resolved ) {
			return null;
		}

		$tokens = $this->extract_tokens( $usage );
		if ( null === $tokens ) {
			return null;
		}

		$rates = $resolved['rates'];
		$cost  = $this->calculate_cost(
			$tokens['cache_hit_tokens'],
			$tokens['cache_miss_tokens'],
			$tokens['output_tokens'],
			$rates,
			(int) $resolved['rule']['unit_tokens']
		);
		if ( null === $cost ) {
			return null;
		}

		$snapshot = array(
			'id'                 => $resolved['rule']['id'],
			'label'              => $resolved['rule']['label'],
			'source'             => $resolved['rule']['source'],
			'effective_from_utc' => $resolved['rule']['effective_from_utc'],
			'currency'           => $resolved['rule']['currency'],
			'unit_tokens'        => $resolved['rule']['unit_tokens'],
			'tier'               => $resolved['tier'],
			'rates'              => $rates,
		);

		$result = array(
			'cache_hit_tokens'  => $tokens['cache_hit_tokens'],
			'cache_miss_tokens' => $tokens['cache_miss_tokens'],
			'output_tokens'     => $tokens['output_tokens'],
			'estimated_cost'    => $cost,
			'currency'          => $resolved['rule']['currency'],
			'pricing_tier'      => $resolved['tier'],
			'pricing_snapshot'  => $snapshot,
		);
		if ( null !== $tokens['reasoning_tokens'] ) {
			$result['reasoning_tokens'] = $tokens['reasoning_tokens'];
		}

		return $result;
	}

	/**
	 * Extract complete cache and output token usage.
	 *
	 * @param array<mixed> $usage Usage payload.
	 * @return array{cache_hit_tokens:int, cache_miss_tokens:int, output_tokens:int, reasoning_tokens:int|null}|null
	 */
	private function extract_tokens( array $usage ): ?array {
		$hit  = $this->token( $usage['prompt_cache_hit_tokens'] ?? null );
		$miss = $this->token( $usage['prompt_cache_miss_tokens'] ?? null );

		if ( null === $hit || null === $miss ) {
			$prompt  = $this->token( $usage['prompt_tokens'] ?? null );
			$details = is_array( $usage['prompt_tokens_details'] ?? null ) ? $usage['prompt_tokens_details'] : array();
			$cached  = $this->token( $details['cached_tokens'] ?? null );
			if ( null === $prompt || null === $cached || $cached > $prompt ) {
				return null;
			}
			$hit  = $cached;
			$miss = $prompt - $cached;
		}

		$output = $this->token( $usage['completion_tokens'] ?? null );
		if ( null === $output ) {
			return null;
		}

		$completion_details = is_array( $usage['completion_tokens_details'] ?? null ) ? $usage['completion_tokens_details'] : array();
		$reasoning          = array_key_exists( 'reasoning_tokens', $completion_details )
			? $this->token( $completion_details['reasoning_tokens'] )
			: null;
		if ( array_key_exists( 'reasoning_tokens', $completion_details ) && null === $reasoning ) {
			return null;
		}

		return array(
			'cache_hit_tokens'  => $hit,
			'cache_miss_tokens' => $miss,
			'output_tokens'     => $output,
			'reasoning_tokens'  => $reasoning,
		);
	}

	/**
	 * Validate a token count.
	 *
	 * @param mixed $value Token count.
	 */
	private function token( $value ): ?int {
		return is_int( $value ) && $value >= 0 && $value <= self::MAX_TOKENS ? $value : null;
	}

	/**
	 * Calculate and format an amount at eight decimal places.
	 *
	 * Prices use nine fixed decimal places. Division is decomposed so normal
	 * PHP integers remain sufficient without float or BCMath arithmetic.
	 *
	 * @param int                  $hit         Cache-hit tokens.
	 * @param int                  $miss        Cache-miss tokens.
	 * @param int                  $output      Output tokens.
	 * @param array<string, mixed> $rates       Selected rate tier.
	 * @param int                  $unit_tokens Pricing unit.
	 */
	private function calculate_cost( int $hit, int $miss, int $output, array $rates, int $unit_tokens ): ?string {
		if ( $unit_tokens <= 0 ) {
			return null;
		}

		$denominator = $unit_tokens * intdiv( self::PRICE_SCALE, self::OUTPUT_SCALE );
		$whole       = 0;
		$remainder   = 0;
		foreach ( array(
			'cache_hit'  => $hit,
			'cache_miss' => $miss,
			'output'     => $output,
		) as $key => $tokens ) {
			$price = $this->decimal_to_scaled_integer( $rates[ $key ] ?? null );
			if ( null === $price ) {
				return null;
			}
			$whole     += $tokens * intdiv( $price, $denominator );
			$remainder += $tokens * ( $price % $denominator );
		}

		$whole += intdiv( $remainder + intdiv( $denominator, 2 ), $denominator );

		return $this->format_scaled_amount( $whole );
	}

	/**
	 * Convert a normalized decimal price to a nine-place integer.
	 *
	 * @param mixed $value Decimal price.
	 */
	private function decimal_to_scaled_integer( $value ): ?int {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{1,6})(?:\.(\d{1,9}))?$/', $value, $matches ) ) {
			return null;
		}

		$fraction = str_pad( $matches[2] ?? '', 9, '0' );

		return (int) $matches[1] * self::PRICE_SCALE + (int) $fraction;
	}

	/**
	 * Format an amount stored at eight fixed decimal places.
	 *
	 * @param int $amount Scaled amount.
	 */
	private function format_scaled_amount( int $amount ): string {
		$integer  = intdiv( $amount, self::OUTPUT_SCALE );
		$fraction = $amount % self::OUTPUT_SCALE;

		return (string) $integer . '.' . str_pad( (string) $fraction, 8, '0', STR_PAD_LEFT );
	}
}
