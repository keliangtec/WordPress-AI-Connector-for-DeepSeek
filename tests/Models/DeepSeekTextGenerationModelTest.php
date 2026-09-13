<?php
/**
 * Tests for DeepSeek Chat Completions request construction.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use Guducat\DeepSeekAiProvider\Models\DeepSeekTextGenerationModel;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Messages\Enums\MessagePartChannelEnum;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

/**
 * Verifies image, reasoning, and tool-response request contracts.
 */
final class DeepSeekTextGenerationModelTest extends TestCase {
	/**
	 * Verify a remote image URL remains a structured content part.
	 */
	public function test_remote_image_is_serialized_for_chat_completions(): void {
		$model  = $this->model();
		$prompt = array(
			new UserMessage(
				array(
					new MessagePart( 'Describe this image.' ),
					new MessagePart( new File( 'https://example.com/image.jpg' ) ),
				)
			),
		);

		$params = $this->prepareParams( $model, $prompt );

		$this->assertSame( 'deepseek-flash', $params['model'] );
		$this->assertSame( 'user', $params['messages'][0]['role'] );
		$this->assertSame(
			array(
				'type' => 'text',
				'text' => 'Describe this image.',
			),
			$params['messages'][0]['content'][0]
		);
		$this->assertSame( 'image_url', $params['messages'][0]['content'][1]['type'] );
		$this->assertSame(
			'https://example.com/image.jpg',
			$params['messages'][0]['content'][1]['image_url']['url']
		);
		$this->assertCount( 2, $params['messages'][0]['content'] );
	}

	/**
	 * Verify inline image data remains a base64 data URI.
	 */
	public function test_inline_image_is_serialized_as_data_uri(): void {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- The fixture exercises the documented image data-URI protocol.
		$data_uri = 'data:image/png;base64,' . base64_encode( 'image fixture' );
		$prompt   = array(
			new UserMessage(
				array(
					new MessagePart( 'Describe this image.' ),
					new MessagePart( new File( $data_uri ) ),
				)
			),
		);

		$params = $this->prepareParams( $this->model(), $prompt );

		$this->assertSame( 'image_url', $params['messages'][0]['content'][1]['type'] );
		$this->assertSame( $data_uri, $params['messages'][0]['content'][1]['image_url']['url'] );
		$this->assertCount( 2, $params['messages'][0]['content'] );
	}

	/**
	 * Verify the DeepSeek endpoint and default timeout.
	 */
	public function test_request_uses_chat_completions_endpoint_and_default_timeout(): void {
		$request = $this->createRequest(
			$this->model(),
			HttpMethodEnum::POST(),
			'chat/completions',
			array( 'Content-Type' => 'application/json' ),
			array( 'model' => 'deepseek-flash' )
		);

		$this->assertSame( 'https://api.deepseek.com/v1/chat/completions', $request->getUri() );
		$this->assertSame( HttpMethodEnum::POST(), $request->getMethod() );
		$this->assertNotNull( $request->getOptions() );
		$this->assertSame( 120.0, $request->getOptions()->getTimeout() );
	}

	/**
	 * Verify reasoning content follows assistant messages across tool responses.
	 */
	public function test_reasoning_and_tool_response_history_are_serialized_without_misalignment(): void {
		$prompt = array(
			new ModelMessage(
				array(
					new MessagePart( 'Checking the weather.' ),
					new MessagePart( new FunctionCall( 'call-1', 'weather', array( 'city' => 'Shanghai' ) ) ),
				)
			),
			new UserMessage(
				array(
					new MessagePart( new FunctionResponse( 'call-1', 'weather', array( 'result' => 'sunny' ) ) ),
				)
			),
			new ModelMessage(
				array(
					new MessagePart( 'second thought', MessagePartChannelEnum::thought() ),
					new MessagePart( 'Second answer.' ),
				)
			),
		);

		$params   = $this->prepareParams( $this->model(), $prompt );
		$messages = $params['messages'];

		$this->assertSame( 'assistant', $messages[0]['role'] );
		$this->assertArrayNotHasKey( 'reasoning_content', $messages[0] );
		$this->assertSame(
			array(
				array(
					'type' => 'text',
					'text' => 'Checking the weather.',
				),
			),
			$messages[0]['content']
		);
		$this->assertSame( 'call-1', $messages[0]['tool_calls'][0]['id'] );
		$this->assertSame( 'weather', $messages[0]['tool_calls'][0]['function']['name'] );
		$this->assertSame( '{"city":"Shanghai"}', $messages[0]['tool_calls'][0]['function']['arguments'] );
		$this->assertSame( 'tool', $messages[1]['role'] );
		$this->assertSame( 'call-1', $messages[1]['tool_call_id'] );
		$this->assertSame( '{"result":"sunny"}', $messages[1]['content'] );
		$this->assertArrayNotHasKey( 'reasoning_content', $messages[1] );
		$this->assertSame( 'assistant', $messages[2]['role'] );
		$this->assertSame( 'second thought', $messages[2]['reasoning_content'] );
		$this->assertSame(
			array(
				array(
					'type' => 'text',
					'text' => 'Second answer.',
				),
			),
			$messages[2]['content']
		);
	}

	/**
	 * Create a model instance for request-contract tests.
	 */
	private function model(): DeepSeekTextGenerationModel {
		return new DeepSeekTextGenerationModel(
			new ModelMetadata(
				'deepseek-flash',
				'DeepSeek-Flash',
				array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
				array()
			),
			new ProviderMetadata(
				'deepseek',
				'DeepSeek',
				ProviderTypeEnum::cloud(),
				'https://platform.deepseek.com',
				RequestAuthenticationMethod::apiKey()
			)
		);
	}

	/**
	 * Invoke the protected parameter serializer.
	 *
	 * @param DeepSeekTextGenerationModel $model  Model under test.
	 * @param array                       $prompt Prompt messages.
	 * @return array<string, mixed>
	 *
	 * @phpstan-param list<UserMessage|ModelMessage> $prompt
	 */
	private function prepareParams( DeepSeekTextGenerationModel $model, array $prompt ): array {
		$method = new ReflectionMethod( $model, 'prepareGenerateTextParams' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		/**
		 * Serialized request parameters.
		 *
		 * @var array<string, mixed> $params
		 */
		$params = $method->invoke( $model, $prompt );

		return $params;
	}

	/**
	 * Invoke the protected request factory.
	 *
	 * @param DeepSeekTextGenerationModel $model   Model under test.
	 * @param HttpMethodEnum              $method  HTTP method.
	 * @param string                      $path    API path.
	 * @param array<string,string>        $headers HTTP headers.
	 * @param array<string,mixed>         $data    Request data.
	 * @return Request
	 */
	private function createRequest(
		DeepSeekTextGenerationModel $model,
		HttpMethodEnum $method,
		string $path,
		array $headers,
		array $data
	): Request {
		$reflection = new ReflectionMethod( $model, 'createRequest' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		/**
		 * Constructed request.
		 *
		 * @var Request $request
		 */
		$request = $reflection->invoke( $model, $method, $path, $headers, $data );

		return $request;
	}
}
