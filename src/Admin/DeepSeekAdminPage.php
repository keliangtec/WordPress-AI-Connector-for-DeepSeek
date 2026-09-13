<?php
/**
 * DeepSeek connector administration page.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Coordinates the balance and model management sections.
 */
final class DeepSeekAdminPage {
	public const PAGE_SLUG = 'deepseek-connector';

	/** Register the administration page. */
	public function register(): void {
		add_options_page(
			__( 'DeepSeek Connector', 'ai-connector-for-deepseek-guducat-ver' ),
			__( 'DeepSeek Connector', 'ai-connector-for-deepseek-guducat-ver' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
		add_action( 'admin_notices', array( $this, 'render_connector_notice' ) );
	}

	/** Render the connector page. */
	public function render_page(): void {
		$balance        = new DeepSeekBalanceSection();
		$model_settings = new DeepSeekModelSettingsSection();
		$model_result   = $model_settings->process_request();
		$balance_result = $balance->process_request();
		?>
		<div class="wrap"><h1><?php echo esc_html__( 'DeepSeek Connector', 'ai-connector-for-deepseek-guducat-ver' ); ?></h1>
			<h2><?php echo esc_html__( 'Account balance', 'ai-connector-for-deepseek-guducat-ver' ); ?></h2>
			<p><?php echo esc_html__( 'Balance is queried only after an explicit refresh.', 'ai-connector-for-deepseek-guducat-ver' ); ?></p>
			<form method="post"><?php wp_nonce_field( DeepSeekBalancePage::NONCE_ACTION ); ?><input type="hidden" name="<?php echo esc_attr( DeepSeekBalancePage::ACTION_FIELD ); ?>" value="refresh"><?php submit_button( __( 'Refresh balance', 'ai-connector-for-deepseek-guducat-ver' ), 'secondary', 'submit', false ); ?></form>
			<?php $balance->render_result( $balance_result ); ?>
			<?php $model_settings->render( $model_result ); ?>
		</div>
		<?php
	}

	/** Render the link from the core connector page. */
	public function render_connector_notice(): void {
		global $pagenow;
		if ( 'options-connectors.php' !== $pagenow || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		printf( '<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>', esc_html__( 'DeepSeek connector tools are available here.', 'ai-connector-for-deepseek-guducat-ver' ), esc_url( $url ), esc_html__( 'Open DeepSeek Connector', 'ai-connector-for-deepseek-guducat-ver' ) );
	}
}
