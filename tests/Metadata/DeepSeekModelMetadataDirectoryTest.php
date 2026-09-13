<?php
/**
 * Tests for DeepSeek model metadata discovery.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use Guducat\DeepSeekAiProvider\Metadata\DeepSeekModelMetadataDirectory;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Verifies exact model capability metadata and request construction.
 */
final class DeepSeekModelMetadataDirectoryTest extends TestCase {
	/**
	 * Verify only the exact flash model advertises image input.
	 */
	public function test_dynamic_models_use_exact_input_modality_matrix(): void {
		$directory = new DeepSeekModelMetadataDirectory();
		$response  = new Response(
			200,
			array( 'Content-Type' => 'application/json' ),
			json_encode(
				array(
					'data' => array(
						array( 'id' => 'deepseek-future' ),
						array( 'id' => 'deepseek-v5-pro' ),
						array( 'id' => 'deepseek-v4-flash-vision-exp' ),
						array( 'id' => 'deepseek-flash' ),
						array( 'id' => 'deepseek-v4-pro' ),
					),
				)
			)
		);

		$models = $this->parseResponse( $directory, $response );

		$expected_modalities = array(
			'deepseek-flash'               => array( ModalityEnum::TEXT, ModalityEnum::IMAGE ),
			'deepseek-v4-pro'              => array( ModalityEnum::TEXT ),
			'deepseek-future'              => array( ModalityEnum::TEXT ),
			'deepseek-v4-flash-vision-exp' => array( ModalityEnum::TEXT ),
			'deepseek-v5-pro'              => array( ModalityEnum::TEXT ),
		);

		foreach ( $models as $model ) {
			$capabilities = array_map(
				static function ( CapabilityEnum $capability ): string {
					return $capability->value;
				},
				$model->getSupportedCapabilities()
			);
			$this->assertSame( array( CapabilityEnum::TEXT_GENERATION, CapabilityEnum::CHAT_HISTORY ), $capabilities );
			$this->assertSame( $expected_modalities[ $model->getId() ], $this->inputModalities( $model ) );
		}

		$this->assertSame(
			array(
				'deepseek-flash',
				'deepseek-v4-pro',
				'deepseek-future',
				'deepseek-v4-flash-vision-exp',
				'deepseek-v5-pro',
			),
			array_map(
				static function ( ModelMetadata $model ): string {
					return $model->getId();
				},
				$models
			)
		);

		$this->assertSame( 'DeepSeek-Flash', $models[0]->getName() );
		$this->assertSame( 'Deepseek Future', $models[2]->getName() );
	}

	/**
	 * Verify the models endpoint request remains dynamic.
	 */
	public function test_models_request_uses_deepseek_models_endpoint(): void {
		$request = $this->createRequest( new DeepSeekModelMetadataDirectory(), HttpMethodEnum::GET(), 'models' );

		$this->assertSame( HttpMethodEnum::GET(), $request->getMethod() );
		$this->assertSame( 'https://api.deepseek.com/v1/models', $request->getUri() );
	}

	/**
	 * Verify image capability matching remains case-sensitive.
	 */
	public function test_image_model_id_matching_is_case_sensitive(): void {
		$response = new Response(
			200,
			array( 'Content-Type' => 'application/json' ),
			'{"data":[{"id":"DeepSeek-Flash"}]}'
		);
		$models   = $this->parseResponse( new DeepSeekModelMetadataDirectory(), $response );

		$this->assertCount( 1, $models );
		$this->assertSame( array( ModalityEnum::TEXT ), $this->inputModalities( $models[0] ) );
	}

	/**
	 * Return serialized input modalities from model metadata.
	 *
	 * @param ModelMetadata $model Model metadata.
	 * @return list<string>
	 */
	private function inputModalities( ModelMetadata $model ): array {
		foreach ( $model->getSupportedOptions() as $option ) {
			if ( OptionEnum::inputModalities() === $option->getName() ) {
				$sets = $option->getSupportedValues();
				$this->assertIsArray( $sets );
				$this->assertCount( 1, $sets );
				$this->assertIsArray( $sets[0] );

				return array_map(
					static function ( ModalityEnum $modality ): string {
						return $modality->value;
					},
					$sets[0]
				);
			}
		}

		$this->fail( 'Input modalities option is missing.' );
	}

	/**
	 * Invoke the protected response parser.
	 *
	 * @param DeepSeekModelMetadataDirectory $directory Metadata directory.
	 * @param Response                       $response  Models response.
	 * @return list<ModelMetadata>
	 */
	private function parseResponse( DeepSeekModelMetadataDirectory $directory, Response $response ): array {
		$method = new ReflectionMethod( $directory, 'parseResponseToModelMetadataList' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		/**
		 * Parsed model metadata.
		 *
		 * @var list<ModelMetadata> $models
		 */
		$models = $method->invoke( $directory, $response );

		return $models;
	}

	/**
	 * Invoke the protected request factory.
	 *
	 * @param DeepSeekModelMetadataDirectory $directory Metadata directory.
	 * @param HttpMethodEnum                 $method    HTTP method.
	 * @param string                         $path      API path.
	 * @return Request
	 */
	private function createRequest(
		DeepSeekModelMetadataDirectory $directory,
		HttpMethodEnum $method,
		string $path
	): Request {
		$reflection = new ReflectionMethod( $directory, 'createRequest' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		/**
		 * Constructed request.
		 *
		 * @var Request $request
		 */
		$request = $reflection->invoke( $directory, $method, $path );

		return $request;
	}
}
