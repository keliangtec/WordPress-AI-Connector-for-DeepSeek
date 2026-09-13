<?php
/**
 * DeepSeek account balance client.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider\Balance;

use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Reads the authenticated DeepSeek account balance.
 */
final class DeepSeekBalanceClient {
	private const BALANCE_ENDPOINT = 'https://api.deepseek.com/user/balance';

	private const REQUEST_TIMEOUT = 15.0;

	/**
	 * HTTP transport used for the balance request.
	 *
	 * @var HttpTransporterInterface
	 */
	private HttpTransporterInterface $http_transporter;

	/**
	 * Runtime authentication used by the registered provider.
	 *
	 * @var RequestAuthenticationInterface
	 */
	private RequestAuthenticationInterface $request_authentication;

	/**
	 * Create a balance client.
	 *
	 * @param HttpTransporterInterface       $http_transporter       HTTP transport.
	 * @param RequestAuthenticationInterface $request_authentication Runtime provider authentication.
	 */
	public function __construct(
		HttpTransporterInterface $http_transporter,
		RequestAuthenticationInterface $request_authentication
	) {
		$this->http_transporter       = $http_transporter;
		$this->request_authentication = $request_authentication;
	}

	/**
	 * Fetch and validate the current account balance.
	 *
	 * @throws ResponseException If DeepSeek returns an unsuccessful or invalid response.
	 * @return array{is_available: bool, balance_infos: list<array{currency: string, total_balance: string, granted_balance: string, topped_up_balance: string}>}
	 */
	public function get_balance(): array {
		$options = new RequestOptions();
		$options->setTimeout( self::REQUEST_TIMEOUT );
		$options->setMaxRedirects( 0 );

		$request  = new Request(
			HttpMethodEnum::GET(),
			self::BALANCE_ENDPOINT,
			array( 'Accept' => 'application/json' ),
			null,
			$options
		);
		$request  = $this->request_authentication->authenticateRequest( $request );
		$response = $this->http_transporter->send( $request );

		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->parse_response( $response );
	}

	/**
	 * Validate the balance response without including provider data in errors.
	 *
	 * @param Response $response Successful HTTP response.
	 * @throws ResponseException If the response payload is malformed.
	 * @return array{is_available: bool, balance_infos: list<array{currency: string, total_balance: string, granted_balance: string, topped_up_balance: string}>}
	 */
	private function parse_response( Response $response ): array {
		$data = $response->getData();
		if ( ! is_array( $data ) ) {
			throw new ResponseException( 'Unexpected DeepSeek balance API response: Invalid JSON.' );
		}

		if ( ! array_key_exists( 'is_available', $data ) || ! is_bool( $data['is_available'] ) ) {
			throw ResponseException::fromInvalidData( 'DeepSeek balance', 'is_available', 'Expected a boolean.' );
		}

		if ( ! isset( $data['balance_infos'] ) || ! is_array( $data['balance_infos'] ) ) {
			throw ResponseException::fromInvalidData( 'DeepSeek balance', 'balance_infos', 'Expected an array.' );
		}

		$balance_infos = array();
		foreach ( $data['balance_infos'] as $balance_info ) {
			if ( ! is_array( $balance_info ) ) {
				throw ResponseException::fromInvalidData( 'DeepSeek balance', 'balance_infos', 'Expected balance entries to be objects.' );
			}

			$balance_infos[] = $this->parse_balance_info( $balance_info );
		}

		return array(
			'is_available'  => $data['is_available'],
			'balance_infos' => $balance_infos,
		);
	}

	/**
	 * Validate one balance entry.
	 *
	 * @param array<string, mixed> $balance_info Raw balance entry.
	 * @throws ResponseException If a balance field is missing or invalid.
	 * @return array{currency: string, total_balance: string, granted_balance: string, topped_up_balance: string}
	 */
	private function parse_balance_info( array $balance_info ): array {
		$required_fields = array( 'currency', 'total_balance', 'granted_balance', 'topped_up_balance' );
		foreach ( $required_fields as $field ) {
			if ( ! array_key_exists( $field, $balance_info ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Field names are internal fixed keys used in exception context.
				throw ResponseException::fromMissingData( 'DeepSeek balance', $field );
			}
		}

		$currency = $balance_info['currency'];
		if ( ! is_string( $currency ) || ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			throw ResponseException::fromInvalidData( 'DeepSeek balance', 'currency', 'Expected a three-letter currency code.' );
		}

		$balances = array();
		foreach ( array( 'total_balance', 'granted_balance', 'topped_up_balance' ) as $field ) {
			$value = $balance_info[ $field ];
			if ( ! is_string( $value ) || ! preg_match( '/^\d+(?:\.\d+)?$/', $value ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Field names are internal fixed keys used in exception context.
				throw ResponseException::fromInvalidData( 'DeepSeek balance', $field, 'Expected a non-negative decimal string.' );
			}

			$balances[ $field ] = $value;
		}

		return array(
			'currency'          => $currency,
			'total_balance'     => $balances['total_balance'],
			'granted_balance'   => $balances['granted_balance'],
			'topped_up_balance' => $balances['topped_up_balance'],
		);
	}
}
