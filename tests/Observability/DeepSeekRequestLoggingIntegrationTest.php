<?php
/**
 * Tests for the DeepSeek request logging integration.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use Guducat\DeepSeekAiProvider\Observability\DeepSeekRequestLoggingIntegration;
use Guducat\DeepSeekAiProvider\Pricing\DeepSeekUsageCostEstimator;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that only DeepSeek Chat Completions logs receive extra metadata.
 */
final class DeepSeekRequestLoggingIntegrationTest extends TestCase {
	/** Reset registered test filters before each test. */
	protected function setUp(): void {
		$GLOBALS['deepseek_test_filters'] = array();
	}

	/** Verify production bootstrap can use the configured default estimator. */
	public function test_constructor_uses_default_estimator(): void {
		$integration = new DeepSeekRequestLoggingIntegration();

		$integration->register();

		$this->assertCount( 2, $GLOBALS['deepseek_test_filters'] );
	}

	/** Verify both WordPress AI filters are registered once with their full argument counts. */
	public function test_register_adds_logging_filters_once(): void {
		$integration = new DeepSeekRequestLoggingIntegration( $this->createMock( DeepSeekUsageCostEstimator::class ) );

		$integration->register();
		$integration->register();

		$this->assertSame(
			array(
				array(
					'hook_name'     => 'wpai_request_log_kind',
					'priority'      => 10,
					'accepted_args' => 4,
				),
				array(
					'hook_name'     => 'wpai_request_log_context',
					'priority'      => 10,
					'accepted_args' => 3,
				),
			),
			array_map(
				static function ( array $filter ): array {
					unset( $filter['callback'] );
					return $filter;
				},
				$GLOBALS['deepseek_test_filters']
			)
		);
	}

	/** Verify a DeepSeek image URL content part is marked as a text-and-image request. */
	public function test_request_kind_detects_deepseek_chat_image_content(): void {
		$integration = new DeepSeekRequestLoggingIntegration( $this->createMock( DeepSeekUsageCostEstimator::class ) );
		$payload     = array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => 'Describe this image.',
						),
						array(
							'type'      => 'image_url',
							'image_url' => array( 'url' => 'data:image/png;base64,test' ),
						),
					),
				),
			),
		);

		$this->assertSame(
			'text_image',
			$integration->filter_request_kind( 'text', 'deepseek', '/v1/chat/completions', $payload )
		);
	}

	/** Verify input_image parts are supported without changing plain text requests. */
	public function test_request_kind_supports_input_image_and_preserves_plain_text(): void {
		$integration = new DeepSeekRequestLoggingIntegration( $this->createMock( DeepSeekUsageCostEstimator::class ) );
		$image       = array(
			'messages' => array(
				array(
					'content' => array(
						array(
							'type'      => 'input_image',
							'image_url' => 'https://example.com/image.jpg',
						),
					),
				),
			),
		);
		$text        = array(
			'messages' => array( array( 'content' => 'Hello.' ) ),
		);

		$this->assertSame( 'text_image', $integration->filter_request_kind( 'text', 'deepseek', '/chat/completions', $image ) );
		$this->assertSame( 'text', $integration->filter_request_kind( 'text', 'deepseek', '/v1/chat/completions', $text ) );
	}

	/** Verify non-DeepSeek and non-chat endpoints retain the kind supplied by WordPress AI. */
	public function test_request_kind_ignores_other_providers_and_endpoints(): void {
		$integration = new DeepSeekRequestLoggingIntegration( $this->createMock( DeepSeekUsageCostEstimator::class ) );
		$payload     = array(
			'messages' => array(
				array(
					'content' => array(
						array(
							'type'      => 'image_url',
							'image_url' => array( 'url' => 'test' ),
						),
					),
				),
			),
		);

		$this->assertSame( 'text', $integration->filter_request_kind( 'text', 'openai', '/v1/chat/completions', $payload ) );
		$this->assertSame( 'metadata', $integration->filter_request_kind( 'metadata', 'deepseek', '/v1/models', $payload ) );
		$this->assertSame( 'account', $integration->filter_request_kind( 'account', 'deepseek', '/user/balance', $payload ) );
	}

	/** Verify estimator output is merged into the DeepSeek log context. */
	public function test_context_adds_usage_and_pricing_snapshot_for_deepseek_completions(): void {
		$usage     = array(
			'prompt_tokens'             => 1295,
			'completion_tokens'         => 898,
			'prompt_cache_hit_tokens'   => 1000,
			'prompt_cache_miss_tokens'  => 295,
			'completion_tokens_details' => array( 'reasoning_tokens' => 600 ),
			'prompt_tokens_details'     => array( 'cached_tokens' => 1000 ),
		);
		$snapshot  = array(
			'cache_hit_tokens'  => 1000,
			'cache_miss_tokens' => 295,
			'reasoning_tokens'  => 600,
			'estimated_cost'    => '0.004887000',
			'currency'          => 'CNY',
			'pricing_rule_id'   => 'builtin-v41-flash',
			'pricing_snapshot'  => array(
				'unit_tokens' => 1000000,
				'output_rate' => '4.00',
			),
		);
		$estimator = $this->createMock( DeepSeekUsageCostEstimator::class );
		$estimator->expects( $this->once() )
			->method( 'estimate' )
			->with(
				'deepseek-flash',
				$usage,
				$this->callback(
					static function ( DateTimeImmutable $requested_at ): bool {
						return 'UTC' === $requested_at->getTimezone()->getName()
							&& abs( time() - $requested_at->getTimestamp() ) < 5;
					}
				)
			)
			->willReturn( $snapshot );

		$integration = new DeepSeekRequestLoggingIntegration( $estimator );
		$result      = $integration->filter_request_log_context(
			array(
				'url'      => 'https://api.deepseek.com/v1/chat/completions',
				'deepseek' => array( 'existing_field' => 'preserved' ),
			),
			array( 'usage' => $usage ),
			array(
				'provider'  => 'deepseek',
				'operation' => 'deepseek:completions',
				'model'     => 'deepseek-flash',
			)
		);

		$this->assertSame(
			array_merge( array( 'existing_field' => 'preserved' ), $snapshot ),
			$result['deepseek']
		);
	}

	/** Verify unrelated logs and incomplete response data never invoke the estimator. */
	public function test_context_ignores_unrelated_or_incomplete_logs(): void {
		$estimator = $this->createMock( DeepSeekUsageCostEstimator::class );
		$estimator->expects( $this->never() )->method( 'estimate' );
		$integration = new DeepSeekRequestLoggingIntegration( $estimator );
		$context     = array( 'url' => 'unchanged' );
		$response    = array( 'usage' => array( 'prompt_tokens' => 1 ) );

		$this->assertSame(
			$context,
			$integration->filter_request_log_context(
				$context,
				$response,
				array(
					'provider'  => 'openai',
					'operation' => 'openai:completions',
					'model'     => 'deepseek-flash',
				)
			)
		);
		$this->assertSame(
			$context,
			$integration->filter_request_log_context(
				$context,
				$response,
				array(
					'provider'  => 'deepseek',
					'operation' => 'deepseek:models',
					'model'     => 'deepseek-flash',
				)
			)
		);
		$this->assertSame(
			$context,
			$integration->filter_request_log_context(
				$context,
				array(),
				array(
					'provider'  => 'deepseek',
					'operation' => 'deepseek:completions',
					'model'     => 'deepseek-flash',
				)
			)
		);
		$this->assertSame(
			$context,
			$integration->filter_request_log_context(
				$context,
				$response,
				array(
					'provider'  => 'deepseek',
					'operation' => 'deepseek:completions',
				)
			)
		);
	}

	/** Verify estimator failures cannot disrupt the AI request or WordPress AI logging. */
	public function test_context_preserves_original_data_when_estimation_fails(): void {
		$estimator = $this->createMock( DeepSeekUsageCostEstimator::class );
		$estimator->method( 'estimate' )->willThrowException( new RuntimeException( 'Invalid custom price.' ) );
		$integration = new DeepSeekRequestLoggingIntegration( $estimator );
		$context     = array( 'url' => 'https://api.deepseek.com/v1/chat/completions' );

		$this->assertSame(
			$context,
			$integration->filter_request_log_context(
				$context,
				array(
					'usage' => array(
						'prompt_tokens'     => 1,
						'completion_tokens' => 1,
					),
				),
				array(
					'provider'  => 'deepseek',
					'operation' => 'deepseek:completions',
					'model'     => 'deepseek-flash',
				)
			)
		);
	}
}
