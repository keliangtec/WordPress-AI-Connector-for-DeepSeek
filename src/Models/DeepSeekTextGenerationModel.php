<?php
/**
 * This file contains the definition of the DeepSeekTextGenerationModel class.
 *
 * @package    Guducat\DeepSeekAiProvider
 * @subpackage Guducat\DeepSeekAiProvider/src
 * @author     Sajjad Hossain Sagor <sagorh672@gmail.com>
 */

declare( strict_types=1 );

namespace Guducat\DeepSeekAiProvider\Models;

use Guducat\DeepSeekAiProvider\Provider\DeepSeekProvider;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class for an DeepSeek text generation model using the OpenAI-compatible chat completions API.
 *
 * @since 1.0.0
 */
class DeepSeekTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {
	/**
	 * {@inheritDoc}
	 *
	 * @since  0.1.0
	 * @param  HttpMethodEnum       $method  The HTTP method to use for the request.
	 * @param  string               $path    The API endpoint path (e.g., 'v1/models').
	 * @param  array<string,string> $headers Optional. Array of HTTP headers. Default empty array.
	 * @param  mixed                $data    Optional. The data to be sent in the request body. Default null.
	 * @return Request                       The constructed Request object.
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		$existing = $this->getRequestOptions();
		$options  = null !== $existing
			? RequestOptions::fromArray( $existing->toArray() )
			: new RequestOptions();

		// Sometimes inference is slow; force a generous timeout. only set if absent.
		if ( null === $options->getTimeout() ) {
			$options->setTimeout( 120.0 );
		}

		// DeepSeek supports OpenAI-compatible endpoints at /v1/.
		return new Request(
			$method,
			DeepSeekProvider::url( $path ),
			$headers,
			$data,
			$options
		);
	}

	/**
	 * Re-attaches `reasoning_content` to prior assistant turns.
	 *
	 * See https://api-docs.deepseek.com/guides/thinking_mode#tool-calls.
	 *
	 * @since 0.1.0
	 * @param array $prompt The prompt to generate text for.
	 * @return array The parameters for the API request.
	 *
	 * @phpstan-param list<Message> $prompt
	 * @phpstan-return array<string, mixed>
	 */
	protected function prepareGenerateTextParams( array $prompt ): array {
		$params = parent::prepareGenerateTextParams( $prompt );

		if ( ! isset( $params['messages'] ) || ! is_array( $params['messages'] ) ) {
			return $params;
		}

		$thoughts = array();
		foreach ( $prompt as $message ) {
			if ( MessageRoleEnum::model() !== $message->getRole() ) {
				continue;
			}

			$parts = $message->getParts();
			if ( count( $parts ) === 1 && $parts[0]->getType()->isFunctionResponse() ) {
				continue;
			}

			$thought = '';
			foreach ( $parts as $part ) {
				if ( $part->getType()->isText() && $part->getChannel()->isThought() ) {
					$thought .= $part->getText();
				}
			}
			$thoughts[] = $thought;
		}

		$idx = 0;
		foreach ( $params['messages'] as $wire_index => $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['role'] ) || 'assistant' !== $entry['role'] ) {
				continue;
			}
			if ( ! isset( $thoughts[ $idx ] ) ) {
				break;
			}

			$thought = $thoughts[ $idx ];
			++$idx;

			if ( '' === $thought ) {
				continue;
			}

			$params['messages'][ $wire_index ]['reasoning_content'] = $thought;
		}

		return $params;
	}
}
