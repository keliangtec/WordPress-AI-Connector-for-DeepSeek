<?php
/**
 * Tests for the DeepSeek balance client.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use Guducat\DeepSeekAiProvider\Balance\DeepSeekBalanceClient;

/**
 * Verifies the server-side DeepSeek balance request contract.
 */
final class DeepSeekBalanceClientTest extends TestCase {
	/**
	 * Verify the balance request uses the account endpoint and bearer auth.
	 */
	public function test_balance_request_uses_deepseek_account_endpoint_and_runtime_authentication(): void {
		$transport = $this->createMock( HttpTransporterInterface::class );
		$transport->expects( $this->once() )
			->method( 'send' )
			->with(
				$this->callback(
					static function ( Request $request ): bool {
						return 'GET' === $request->getMethod()->value
							&& 'https://api.deepseek.com/user/balance' === $request->getUri()
							&& array( 'Bearer test-key' ) === $request->getHeader( 'Authorization' );
					}
				)
			)
			->willReturn(
				new Response(
					200,
					array( 'Content-Type' => 'application/json' ),
					'{"is_available":true,"balance_infos":[{"currency":"CNY","total_balance":"110.00","granted_balance":"10.00","topped_up_balance":"100.00"}]}'
				)
			);

		$balance = new DeepSeekBalanceClient( $transport, new ApiKeyRequestAuthentication( 'test-key' ) );

		$this->assertSame(
			array(
				'is_available'  => true,
				'balance_infos' => array(
					array(
						'currency'          => 'CNY',
						'total_balance'     => '110.00',
						'granted_balance'   => '10.00',
						'topped_up_balance' => '100.00',
					),
				),
			),
			$balance->get_balance()
		);
	}

	/**
	 * Verify multiple currencies remain separate and string-preserving.
	 */
	public function test_balance_response_preserves_multiple_currency_strings(): void {
		$transport = $this->createMock( HttpTransporterInterface::class );
		$transport->method( 'send' )->willReturn(
			new Response(
				200,
				array( 'Content-Type' => 'application/json' ),
				'{"is_available":false,"balance_infos":[{"currency":"CNY","total_balance":"0.00","granted_balance":"0","topped_up_balance":"0.00"},{"currency":"USD","total_balance":"1.234567890123","granted_balance":"0.00","topped_up_balance":"1.234567890123"}]}'
			)
		);

		$balance = new DeepSeekBalanceClient( $transport, new ApiKeyRequestAuthentication( 'test-key' ) );
		$result  = $balance->get_balance();

		$this->assertFalse( $result['is_available'] );
		$this->assertSame( '1.234567890123', $result['balance_infos'][1]['total_balance'] );
		$this->assertSame( 'USD', $result['balance_infos'][1]['currency'] );
	}

	/**
	 * Verify malformed balance responses are rejected without exposing payloads.
	 *
	 * @param string $body Malformed response body.
	 * @dataProvider malformedResponseProvider
	 */
	public function test_malformed_balance_response_is_rejected( string $body ): void {
		$transport = $this->createMock( HttpTransporterInterface::class );
		$transport->method( 'send' )->willReturn(
			new Response(
				200,
				array( 'Content-Type' => 'application/json' ),
				$body
			)
		);

		$balance = new DeepSeekBalanceClient( $transport, new ApiKeyRequestAuthentication( 'test-key' ) );

		$this->expectException( Throwable::class );
		$this->expectExceptionMessage( 'Unexpected DeepSeek balance API response' );
		$balance->get_balance();
	}

	/**
	 * Return malformed API responses.
	 *
	 * @return array<string, array{string}>
	 */
	public function malformedResponseProvider(): array {
		return array(
			'missing availability flag'     => array( '{"balance_infos":[]}' ),
			'non boolean availability flag' => array( '{"is_available":"yes","balance_infos":[]}' ),
			'missing balances'              => array( '{"is_available":true}' ),
			'non numeric balance'           => array( '{"is_available":true,"balance_infos":[{"currency":"CNY","total_balance":"one","granted_balance":"0","topped_up_balance":"0"}]}' ),
			'invalid json'                  => array( 'not-json' ),
		);
	}
}
