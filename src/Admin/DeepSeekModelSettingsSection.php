<?php
/**
 * DeepSeek model settings administration section.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider\Admin;

use Guducat\DeepSeekAiProvider\Metadata\DeepSeekModelMetadataDirectory;
use Guducat\DeepSeekAiProvider\Models\DeepSeekModelSettings;
use Guducat\DeepSeekAiProvider\Provider\DeepSeekProvider;
use Throwable;
use WordPress\AiClient\AiClient;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Handles model discovery and local capability configuration.
 */
final class DeepSeekModelSettingsSection {
	public const ACTION_FIELD = 'deepseek_model_action';

	public const NONCE_REFRESH = 'deepseek_model_refresh';

	public const NONCE_SAVE = 'deepseek_model_save';

	/**
	 * Process a submitted model action.
	 *
	 * @return array{message: string, error: bool}
	 */
	public function process_request(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'message' => 'Permission denied.',
				'error'   => true,
			);
		}

		$action_value = isset( $_POST[ self::ACTION_FIELD ] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			? wp_unslash( $_POST[ self::ACTION_FIELD ] )
			: '';
		$action = is_string( $action_value ) ? sanitize_key( $action_value ) : '';

		if ( 'refresh' === $action ) {
			check_admin_referer( self::NONCE_REFRESH );

			try {
				$directory = $this->directory();
				$directory->refreshRemoteModelIds();
				return array(
					'message' => 'The DeepSeek model list was refreshed.',
					'error'   => false,
				);
			} catch ( Throwable $exception ) {
				unset( $exception );
				return array(
					'message' => 'The DeepSeek model list could not be refreshed.',
					'error'   => true,
				);
			}
		}

		if ( 'save' === $action ) {
			check_admin_referer( self::NONCE_SAVE );
			$settings         = DeepSeekModelSettings::get();
			$overrides        = array();
			$posted_overrides = isset( $_POST['deepseek_model_override'] ) ? wp_unslash( $_POST['deepseek_model_override'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are validated below.
			if ( is_array( $posted_overrides ) ) {
				foreach ( $posted_overrides as $model_id => $input_mode ) {
					if ( is_string( $model_id ) && in_array( $model_id, $settings['remote_models'], true ) && DeepSeekModelSettings::is_valid_input_mode( $input_mode ) ) {
						$overrides[ $model_id ] = $input_mode;
					}
				}
			}

			$experimental_id   = isset( $_POST['deepseek_experimental_id'] ) ? wp_unslash( $_POST['deepseek_experimental_id'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is validated below.
			$experimental_mode = isset( $_POST['deepseek_experimental_mode'] ) ? wp_unslash( $_POST['deepseek_experimental_mode'] ) : DeepSeekModelSettings::INPUT_TEXT; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Value is validated below.
			DeepSeekModelSettings::save(
				array(
					'remote_models'      => $settings['remote_models'],
					'overrides'          => $overrides,
					'experimental_model' => array(
						'id'         => is_string( $experimental_id ) ? $experimental_id : '',
						'input_mode' => $experimental_mode,
					),
				)
			);
			$this->directory()->invalidateCaches();

			return array(
				'message' => 'DeepSeek model settings saved.',
				'error'   => false,
			);
		}

		return array(
			'message' => '',
			'error'   => false,
		);
	}

	/**
	 * Render the section.
	 *
	 * @param array{message: string, error: bool} $result Action result.
	 */
	public function render( array $result = array(
		'message' => '',
		'error'   => false,
	) ): void {
		$settings = DeepSeekModelSettings::get();
		if ( '' !== $result['message'] ) {
			$class = $result['error'] ? 'notice notice-error' : 'notice notice-success';
			printf( '<div class="%s"><p>%s</p></div>', esc_attr( $class ), esc_html( $result['message'] ) );
		}
		?>
		<h2><?php echo esc_html__( 'Model management', 'ai-connector-for-deepseek-guducat-ver' ); ?></h2>
		<p><?php echo esc_html__( 'Refresh the remote list only when needed. Unknown models default to text input.', 'ai-connector-for-deepseek-guducat-ver' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( self::NONCE_REFRESH ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::ACTION_FIELD ); ?>" value="refresh">
			<?php submit_button( __( 'Refresh model list', 'ai-connector-for-deepseek-guducat-ver' ), 'secondary', 'submit', false ); ?>
		</form>
		<form method="post">
			<?php wp_nonce_field( self::NONCE_SAVE ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::ACTION_FIELD ); ?>" value="save">
			<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Remote model', 'ai-connector-for-deepseek-guducat-ver' ); ?></th><th><?php echo esc_html__( 'Input capability', 'ai-connector-for-deepseek-guducat-ver' ); ?></th></tr></thead><tbody>
			<?php
			foreach ( $settings['remote_models'] as $model_id ) :
				$selected = $settings['overrides'][ $model_id ] ?? 'default';
				?>
				<tr><td><?php echo esc_html( $model_id ); ?></td><td><select name="deepseek_model_override[<?php echo esc_attr( $model_id ); ?>]"><option value="default" <?php selected( 'default', $selected ); ?>><?php echo esc_html__( 'Plugin default', 'ai-connector-for-deepseek-guducat-ver' ); ?></option><option value="text" <?php selected( 'text', $selected ); ?>><?php echo esc_html__( 'Text only', 'ai-connector-for-deepseek-guducat-ver' ); ?></option><option value="text_image" <?php selected( 'text_image', $selected ); ?>><?php echo esc_html__( 'Text + image', 'ai-connector-for-deepseek-guducat-ver' ); ?></option></select></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<p><label for="deepseek-experimental-id"><?php echo esc_html__( 'One experimental model ID', 'ai-connector-for-deepseek-guducat-ver' ); ?></label><br><input id="deepseek-experimental-id" type="text" class="regular-text" name="deepseek_experimental_id" value="<?php echo esc_attr( $settings['experimental_model']['id'] ); ?>"></p>
			<p><select name="deepseek_experimental_mode"><option value="text" <?php selected( 'text', $settings['experimental_model']['input_mode'] ); ?>><?php echo esc_html__( 'Experimental: text only', 'ai-connector-for-deepseek-guducat-ver' ); ?></option><option value="text_image" <?php selected( 'text_image', $settings['experimental_model']['input_mode'] ); ?>><?php echo esc_html__( 'Experimental: text + image', 'ai-connector-for-deepseek-guducat-ver' ); ?></option></select></p>
			<?php submit_button( __( 'Save model settings', 'ai-connector-for-deepseek-guducat-ver' ), 'primary', 'submit', false ); ?>
		</form>
		<?php
	}

	/**
	 * Get the registered metadata directory with runtime authentication.
	 *
	 * @throws \RuntimeException If the AI Client or provider is unavailable.
	 */
	private function directory(): DeepSeekModelMetadataDirectory {
		if ( ! class_exists( AiClient::class ) ) {
			throw new \RuntimeException( 'WordPress AI Client is unavailable.' );
		}

		$registry = AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( DeepSeekProvider::class ) ) {
			throw new \RuntimeException( 'DeepSeek provider is unavailable.' );
		}

		$directory = DeepSeekProvider::modelMetadataDirectory();
		if ( ! $directory instanceof DeepSeekModelMetadataDirectory ) {
			throw new \RuntimeException( 'DeepSeek model directory is unavailable.' );
		}

		return $directory;
	}
}
