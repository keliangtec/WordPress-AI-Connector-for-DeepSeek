<?php
/**
 * Tests for DeepSeek model metadata discovery.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use Guducat\DeepSeekAiProvider\Metadata\DeepSeekModelMetadataDirectory;
use Guducat\DeepSeekAiProvider\Models\DeepSeekModelSettings;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Verifies exact model capability metadata and request construction.
 */
final class DeepSeekModelMetadataDirectoryTest extends TestCase {
	/** Reset test options. */
	protected function setUp(): void {
		$GLOBALS['deepseek_test_options'] = array();
	}

	/** Verify administrator capability overrides are applied. */
	public function test_admin_override_and_experimental_model_change_input_modalities(): void {
		DeepSeekModelSettings::save(
			array(
				'remote_models'      => array( 'deepseek-v4-pro' ),
				'overrides'          => array( 'deepseek-v4-pro' => 'text_image' ),
				'experimental_model' => array(
					'id'         => 'deepseek-exp',
					'input_mode' => 'text_image',
				),
			)
		);
		$response = new Response( 200, array(), '{"data":[{"id":"deepseek-v4-pro"},{"id":"deepseek-exp"}]}' );
		$models   = $this->parseResponse( new DeepSeekModelMetadataDirectory(), $response );

		$this->assertSame( array( ModalityEnum::TEXT, ModalityEnum::IMAGE ), $this->inputModalities( $models[1] ) );
		$this->assertSame( array( ModalityEnum::TEXT, ModalityEnum::IMAGE ), $this->inputModalities( $models[0] ) );
	}
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

	/** Verify refresh uses the remote response directly and excludes the experimental fallback. */
	public function test_refresh_returns_sorted_remote_ids_without_experimental_model(): void {
		DeepSeekModelSettings::save(
			array(
				'remote_models'      => array( 'stale-model' ),
				'experimental_model' => array(
					'id'         => 'deepseek-exp',
					'input_mode' => DeepSeekModelSettings::INPUT_TEXT,
				),
			)
		);

		$transport_calls = 0;
		$directory       = new DeepSeekModelMetadataDirectory();
		$directory->setHttpTransporter(
			new class( $transport_calls ) implements HttpTransporterInterface {
				/**
				 * Number of requests sent.
				 *
				 * @var int
				 */
				private $calls;

				/**
				 * Create a test transporter.
				 *
				 * @param int $calls Request counter passed by reference.
				 */
				public function __construct( &$calls ) {
					$this->calls =& $calls;
				}

				/**
				 * Send a test response.
				 *
				 * @param Request             $request Request.
				 * @param RequestOptions|null $options Options.
				 * @return Response Response.
				 */
				public function send( Request $request, ?RequestOptions $options = null ): Response {
					unset( $request, $options );
					++$this->calls;
					return new Response( 200, array(), '{"data":[{"id":"deepseek-z"},{"id":"deepseek-a"},{"id":"deepseek-z"}]}' );
				}
			}
		);
		$directory->setRequestAuthentication(
			new class() implements RequestAuthenticationInterface {
				/**
				 * Authenticate the test request.
				 *
				 * @param Request $request Request.
				 * @return Request Authenticated request.
				 */
				public function authenticateRequest( Request $request ): Request {
					return $request;
				}

				/**
				 * Return the test authentication schema.
				 *
				 * @return array<string, mixed> JSON schema.
				 */
				public static function getJsonSchema(): array {
					return array();
				}
			}
		);

		$this->assertSame( array( 'deepseek-a', 'deepseek-z' ), $directory->refreshRemoteModelIds() );
		$this->assertSame( 1, $transport_calls );
		$this->assertSame( array( 'deepseek-a', 'deepseek-z' ), DeepSeekModelSettings::get()['remote_models'] );
	}

	/** Verify the configured experimental model is visible to normal runtime discovery. */
	public function test_runtime_discovery_adds_experimental_model(): void {
		DeepSeekModelSettings::save(
			array(
				'experimental_model' => array(
					'id'         => 'deepseek-exp',
					'input_mode' => DeepSeekModelSettings::INPUT_TEXT_IMAGE,
				),
			)
		);

		$directory = new DeepSeekModelMetadataDirectory();
		$directory->setHttpTransporter(
			new class() implements HttpTransporterInterface {
				/**
				 * Send the remote model response.
				 *
				 * @param Request             $request Request.
				 * @param RequestOptions|null $options Request options.
				 * @return Response Response.
				 */
				public function send( Request $request, ?RequestOptions $options = null ): Response {
					unset( $request, $options );
					return new Response( 200, array(), '{"data":[{"id":"deepseek-flash"}]}' );
				}
			}
		);
		$directory->setRequestAuthentication(
			new class() implements RequestAuthenticationInterface {
				/**
				 * Authenticate the remote model request.
				 *
				 * @param Request $request Request.
				 * @return Request Authenticated request.
				 */
				public function authenticateRequest( Request $request ): Request {
					return $request;
				}

				/**
				 * Return the test authentication schema.
				 *
				 * @return array<string, mixed> JSON schema.
				 */
				public static function getJsonSchema(): array {
					return array();
				}
			}
		);

		$reflection = new ReflectionMethod( $directory, 'sendListModelsRequest' );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}
		$models = $reflection->invoke( $directory );

		$this->assertArrayHasKey( 'deepseek-exp', $models );
		$this->assertSame( array( ModalityEnum::TEXT, ModalityEnum::IMAGE ), $this->inputModalities( $models['deepseek-exp'] ) );
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
