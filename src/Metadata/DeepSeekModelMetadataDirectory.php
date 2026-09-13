<?php
/**
 * This file contains the definition of the DeepSeekModelMetadataDirectory class.
 *
 * @package    Guducat\DeepSeekAiProvider
 * @subpackage Guducat\DeepSeekAiProvider/src
 * @author     Sajjad Hossain Sagor <sagorh672@gmail.com>
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider\Metadata;

use Guducat\DeepSeekAiProvider\Models\DeepSeekModelSettings;
use Guducat\DeepSeekAiProvider\Provider\DeepSeekProvider;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Class for the model metadata directory used by the provider for DeepSeek.
 *
 * @since 1.0.0
 */
class DeepSeekModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {
	/**
	 * Models that accept image parts in Chat Completions user messages.
	 *
	 * @var list<string>
	 */
	private const IMAGE_INPUT_MODEL_IDS = array(
		'deepseek-flash',
	);

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
		return new Request(
			$method,
			DeepSeekProvider::url( $path ),
			$headers,
			$data
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since  0.1.0
	 * @param  Response $response Response.
	 * @return list<ModelMetadata> Parsed model metadata.
	 * @throws ResponseException  Response data not valid.
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		$response_data = $response->getData();
		if ( ! isset( $response_data['data'] ) || empty( $response_data['data'] ) ) {
			throw ResponseException::fromMissingData( 'DeepSeek', 'data' );
		}

		// Options shared by all text models.
		$base_text_options = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
		);

		$models_data = (array) $response_data['data'];

		$models = array_map(
			static function ( array $model_data ) use ( $base_text_options ): ModelMetadata {
				$model_id         = (string) $model_data['id'];
				$input_modalities = self::inputModalitiesForModel( $model_id );
				$settings         = DeepSeekModelSettings::get();
				$input_mode       = $settings['overrides'][ $model_id ] ?? null;
				if ( null === $input_mode && $settings['experimental_model']['id'] === $model_id ) {
					$input_mode = $settings['experimental_model']['input_mode'];
				}
				if ( DeepSeekModelSettings::INPUT_TEXT === $input_mode ) {
					$input_modalities = array( ModalityEnum::text() );
				} elseif ( DeepSeekModelSettings::INPUT_TEXT_IMAGE === $input_mode ) {
					$input_modalities = array( ModalityEnum::text(), ModalityEnum::image() );
				}
				$options = array_merge(
					$base_text_options,
					array(
						new SupportedOption(
							OptionEnum::inputModalities(),
							array( $input_modalities )
						),
					)
				);

				return new ModelMetadata(
					$model_id,
					self::formatDisplayName( $model_id ),
					array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
					$options
				);
			},
			$models_data
		);

		usort( $models, array( $this, 'modelSortCallback' ) );

		return $models;
	}

	/**
	 * Refresh and return only the models reported by DeepSeek.
	 *
	 * @return list<string> Normalized remote model IDs.
	 */
	public function refreshRemoteModelIds(): array {
		$this->invalidateCaches();
		$remote_models = array_keys( parent::sendListModelsRequest() );
		$remote_models = array_values( array_unique( array_filter( $remote_models, array( DeepSeekModelSettings::class, 'is_valid_model_id' ) ) ) );
		usort( $remote_models, 'strcmp' );

		$settings                  = DeepSeekModelSettings::get();
		$settings['remote_models'] = $remote_models;
		DeepSeekModelSettings::save( $settings );
		$this->invalidateCaches();

		return $remote_models;
	}

	/**
	 * Include the configured experimental model in normal runtime discovery.
	 *
	 * @return array<string, ModelMetadata> Model metadata keyed by model ID.
	 */
	protected function sendListModelsRequest(): array {
		$models       = parent::sendListModelsRequest();
		$settings     = DeepSeekModelSettings::get();
		$experimental = $settings['experimental_model'];

		if ( '' !== $experimental['id'] && ! isset( $models[ $experimental['id'] ] ) ) {
			$models[ $experimental['id'] ] = self::createModelMetadata( $experimental['id'], $experimental['input_mode'] );
		}

		return $models;
	}

	/**
	 * Build one model metadata object using the configured capability.
	 *
	 * @param string $model_id   Model ID.
	 * @param string $input_mode Input mode.
	 * @return ModelMetadata Model metadata.
	 */
	private static function createModelMetadata( string $model_id, string $input_mode ): ModelMetadata {
		$modalities = DeepSeekModelSettings::INPUT_TEXT_IMAGE === $input_mode
			? array( ModalityEnum::text(), ModalityEnum::image() )
			: array( ModalityEnum::text() );

		return new ModelMetadata(
			$model_id,
			self::formatDisplayName( $model_id ),
			array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
			array(
				new SupportedOption( OptionEnum::systemInstruction() ),
				new SupportedOption( OptionEnum::functionDeclarations() ),
				new SupportedOption( OptionEnum::maxTokens() ),
				new SupportedOption( OptionEnum::temperature() ),
				new SupportedOption( OptionEnum::topP() ),
				new SupportedOption( OptionEnum::stopSequences() ),
				new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
				new SupportedOption( OptionEnum::customOptions() ),
				new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
				new SupportedOption( OptionEnum::inputModalities(), array( $modalities ) ),
			)
		);
	}

	/**
	 * Formats technical IDs into readable names.
	 *
	 * @since 1.0.0
	 * @param string $id ID.
	 */
	private static function formatDisplayName( string $id ): string {
		$map = array(
			'deepseek-flash'    => 'DeepSeek-Flash',
			'deepseek-v4-flash' => 'DeepSeek-V4-Flash',
			'deepseek-v4-pro'   => 'DeepSeek-V4-Pro',
		);

		return $map[ $id ] ?? ucwords( str_replace( array( '-', '_' ), ' ', $id ) );
	}

	/**
	 * Return the exact input modalities supported by a model.
	 *
	 * Unknown and future models default to text until DeepSeek documents otherwise.
	 *
	 * @param string $model_id Model ID returned by the API.
	 * @return list<ModalityEnum>
	 */
	private static function inputModalitiesForModel( string $model_id ): array {
		if ( in_array( $model_id, self::IMAGE_INPUT_MODEL_IDS, true ) ) {
			return array( ModalityEnum::text(), ModalityEnum::image() );
		}

		return array( ModalityEnum::text() );
	}

	/**
	 * Callback function for sorting models.
	 *
	 * Sorts known flagship models first, then falls back to model ID.
	 *
	 * @since  1.0.0
	 * @param  ModelMetadata $a First model.
	 * @param  ModelMetadata $b Second model.
	 * @return int              Comparison result.
	 */
	protected function modelSortCallback( ModelMetadata $a, ModelMetadata $b ): int {
		$a_id = $a->getId();
		$b_id = $b->getId();

		// Pin Flagship models to the top.
		$priority = array(
			'deepseek-flash'  => 1,
			'deepseek-v4-pro' => 2,
		);

		$a_priority = $priority[ $a_id ] ?? 99;
		$b_priority = $priority[ $b_id ] ?? 99;

		if ( $a_priority !== $b_priority ) {
			return $a_priority <=> $b_priority;
		}

		return strcmp( $a_id, $b_id );
	}
}
