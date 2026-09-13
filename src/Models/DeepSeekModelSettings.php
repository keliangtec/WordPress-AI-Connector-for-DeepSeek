<?php
/**
 * DeepSeek model management settings.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider\Models;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Stores the small, non-secret model configuration used by the connector.
 */
final class DeepSeekModelSettings {
	public const OPTION_NAME = 'ai_connector_deepseek_model_settings';

	public const INPUT_TEXT = 'text';

	public const INPUT_TEXT_IMAGE = 'text_image';

	/**
	 * Return normalized persisted settings.
	 *
	 * @return array{remote_models: list<string>, overrides: array<string, string>, experimental_model: array{id: string, input_mode: string}}
	 */
	public static function get(): array {
		$value = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();

		return self::sanitize( $value );
	}

	/**
	 * Persist normalized settings without autoloading them.
	 *
	 * @param mixed $value Settings value.
	 */
	public static function save( $value ): bool {
		$settings = self::sanitize( $value );

		if ( function_exists( 'update_option' ) ) {
			return (bool) update_option( self::OPTION_NAME, $settings, false );
		}

		return false;
	}

	/**
	 * Normalize untrusted option or form data.
	 *
	 * @param mixed $value Untrusted settings.
	 * @return array{remote_models: list<string>, overrides: array<string, string>, experimental_model: array{id: string, input_mode: string}}
	 */
	public static function sanitize( $value ): array {
		$value = is_array( $value ) ? $value : array();

		$remote_models = array();
		if ( isset( $value['remote_models'] ) && is_array( $value['remote_models'] ) ) {
			foreach ( $value['remote_models'] as $model_id ) {
				if ( is_string( $model_id ) && self::is_valid_model_id( $model_id ) ) {
					$remote_models[] = $model_id;
				}
			}
		}
		$remote_models = array_values( array_unique( $remote_models ) );
		usort( $remote_models, 'strcmp' );

		$overrides = array();
		if ( isset( $value['overrides'] ) && is_array( $value['overrides'] ) ) {
			foreach ( $value['overrides'] as $model_id => $input_mode ) {
				if ( ! is_string( $model_id ) || ! self::is_valid_model_id( $model_id ) ) {
					continue;
				}
				if ( 'default' === $input_mode ) {
					continue;
				}
				if ( self::is_valid_input_mode( $input_mode ) ) {
					$overrides[ $model_id ] = $input_mode;
				}
			}
		}
		ksort( $overrides, SORT_STRING );

		$experimental_model = array(
			'id'         => '',
			'input_mode' => self::INPUT_TEXT,
		);
		if ( isset( $value['experimental_model'] ) && is_array( $value['experimental_model'] ) ) {
			$id = $value['experimental_model']['id'] ?? '';
			if ( is_string( $id ) && self::is_valid_model_id( $id ) ) {
				$experimental_model['id']         = $id;
				$experimental_model['input_mode'] = self::is_valid_input_mode( $value['experimental_model']['input_mode'] ?? null )
					? $value['experimental_model']['input_mode']
					: self::INPUT_TEXT;
			}
		}

		return array(
			'remote_models'      => $remote_models,
			'overrides'          => $overrides,
			'experimental_model' => $experimental_model,
		);
	}

	/**
	 * Check a provider model identifier.
	 *
	 * @param string $model_id Model ID.
	 */
	public static function is_valid_model_id( string $model_id ): bool {
		return strlen( $model_id ) <= 128 && 1 === preg_match( '/^[A-Za-z0-9._:\/-]+$/', $model_id );
	}

	/**
	 * Check an input capability value.
	 *
	 * @param mixed $input_mode Input mode.
	 */
	public static function is_valid_input_mode( $input_mode ): bool {
		return in_array( $input_mode, array( self::INPUT_TEXT, self::INPUT_TEXT_IMAGE ), true );
	}
}
