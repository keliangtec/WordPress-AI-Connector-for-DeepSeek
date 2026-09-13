<?php
/**
 * Adds DeepSeek-specific metadata to WordPress AI request logs.
 *
 * @package    Guducat\DeepSeekAiProvider
 * @subpackage Guducat\DeepSeekAiProvider/src
 */

declare( strict_types=1 );

namespace Guducat\DeepSeekAiProvider\Observability;

use DateTimeImmutable;
use DateTimeZone;
use Guducat\DeepSeekAiProvider\Pricing\DeepSeekUsageCostEstimator;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Integrates DeepSeek Chat Completions with WordPress AI request logging.
 */
class DeepSeekRequestLoggingIntegration {
	/**
	 * Usage and cost estimator.
	 *
	 * @var DeepSeekUsageCostEstimator
	 */
	private $estimator;

	/**
	 * Whether the filters have already been registered.
	 *
	 * @var bool
	 */
	private $registered = false;

	/**
	 * Create the integration.
	 *
	 * @param DeepSeekUsageCostEstimator|null $estimator Optional configured DeepSeek estimator.
	 */
	public function __construct( ?DeepSeekUsageCostEstimator $estimator = null ) {
		$this->estimator = $estimator ?? new DeepSeekUsageCostEstimator();
	}

	/**
	 * Register WordPress AI logging filters.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->registered ) {
			return;
		}

		add_filter( 'wpai_request_log_kind', array( $this, 'filter_request_kind' ), 10, 4 );
		add_filter( 'wpai_request_log_context', array( $this, 'filter_request_log_context' ), 10, 3 );

		$this->registered = true;
	}

	/**
	 * Mark DeepSeek Chat Completions requests containing images as text and image.
	 *
	 * @param string                    $kind     Existing request kind.
	 * @param string|null               $provider Detected provider.
	 * @param string                    $path     Request URL path.
	 * @param array<string, mixed>|null $payload  Decoded request payload.
	 * @return string Filtered request kind.
	 */
	public function filter_request_kind( string $kind, ?string $provider, string $path, ?array $payload ): string {
		if ( 'deepseek' !== $provider || ! $this->is_chat_completions_path( $path ) || ! is_array( $payload ) ) {
			return $kind;
		}

		return $this->payload_contains_image( $payload ) ? 'text_image' : $kind;
	}

	/**
	 * Add DeepSeek usage and pricing metadata to a Chat Completions log.
	 *
	 * @param array<string, mixed> $context  Existing log context.
	 * @param array<string, mixed> $decoded  Decoded response body.
	 * @param array<string, mixed> $log_data Full WordPress AI log data.
	 * @return array<string, mixed> Filtered log context.
	 */
	public function filter_request_log_context( array $context, array $decoded, array $log_data ): array {
		if (
			'deepseek' !== ( $log_data['provider'] ?? null )
			|| 'deepseek:completions' !== ( $log_data['operation'] ?? null )
			|| ! isset( $log_data['model'] )
			|| ! is_string( $log_data['model'] )
			|| '' === $log_data['model']
			|| ! isset( $decoded['usage'] )
			|| ! is_array( $decoded['usage'] )
		) {
			return $context;
		}

		try {
			$estimate = $this->estimator->estimate(
				$log_data['model'],
				$decoded['usage'],
				new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) )
			);
		} catch ( Throwable $exception ) {
			unset( $exception );
			return $context;
		}

		if ( null === $estimate || array() === $estimate ) {
			return $context;
		}

		$existing            = isset( $context['deepseek'] ) && is_array( $context['deepseek'] )
			? $context['deepseek']
			: array();
		$context['deepseek'] = array_merge( $existing, $estimate );

		return $context;
	}

	/**
	 * Determine whether a URL path is exactly the Chat Completions endpoint.
	 *
	 * @param string $path Request path.
	 * @return bool Whether this is a Chat Completions path.
	 */
	private function is_chat_completions_path( string $path ): bool {
		$normalized = '/' . trim( strtolower( $path ), '/' );

		return '/chat/completions' === $normalized || '/v1/chat/completions' === $normalized;
	}

	/**
	 * Determine whether any chat message has an image content part.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @return bool Whether the payload contains an image.
	 */
	private function payload_contains_image( array $payload ): bool {
		if ( ! isset( $payload['messages'] ) || ! is_array( $payload['messages'] ) ) {
			return false;
		}

		foreach ( $payload['messages'] as $message ) {
			if ( ! is_array( $message ) || ! isset( $message['content'] ) || ! is_array( $message['content'] ) ) {
				continue;
			}

			foreach ( $message['content'] as $part ) {
				if ( ! is_array( $part ) || ! isset( $part['type'] ) || ! is_string( $part['type'] ) ) {
					continue;
				}

				if ( 'image_url' === $part['type'] || 'input_image' === $part['type'] ) {
					return true;
				}
			}
		}

		return false;
	}
}
