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

	/**
	 * Page hook returned by WordPress.
	 *
	 * @var string
	 */
	private string $page_hook = '';

	/** Register the administration page. */
	public function register(): void {
		$this->page_hook = add_options_page(
			__( 'DeepSeek Connector', 'ai-connector-for-deepseek-guducat-ver' ),
			__( 'DeepSeek Connector', 'ai-connector-for-deepseek-guducat-ver' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
		add_action( 'admin_notices', array( $this, 'render_connector_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Load styles only on the DeepSeek Connector page.
	 *
	 * @param string $hook_suffix Current administrator page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $this->page_hook !== $hook_suffix ) {
			return;
		}

		$plugin_dir = defined( 'DEEPSEEK_AI_PROVIDER_DIR' ) ? constant( 'DEEPSEEK_AI_PROVIDER_DIR' ) : dirname( __DIR__, 2 ) . '/';
		$version    = defined( 'DEEPSEEK_AI_PROVIDER_VERSION' ) ? constant( 'DEEPSEEK_AI_PROVIDER_VERSION' ) : false;

		wp_enqueue_style(
			'deepseek-connector-admin',
			plugins_url( 'assets/css/admin.css', $plugin_dir . 'ai-connector-for-deepseek-guducat-ver.php' ),
			array(),
			$version
		);
	}

	/** Render the connector page. */
	public function render_page(): void {
		$balance        = new DeepSeekBalanceSection();
		$model_settings = new DeepSeekModelSettingsSection();
		$pricing        = new DeepSeekPricingSection();
		$logging_status = new DeepSeekRequestLoggingStatus();
		$pricing_result = $pricing->process_request();
		$model_result   = $model_settings->process_request();
		$balance_result = $balance->process_request();
		?>
		<div class="wrap deepseek-connector-admin">
			<div class="deepseek-connector-admin__header">
				<h1><?php echo esc_html__( 'DeepSeek Connector', 'ai-connector-for-deepseek-guducat-ver' ); ?></h1>
				<p><?php echo esc_html__( 'Manage DeepSeek request observability, account balance, and model capabilities.', 'ai-connector-for-deepseek-guducat-ver' ); ?></p>
			</div>

			<section class="deepseek-connector-admin__section" aria-labelledby="deepseek-request-logging-heading">
				<h2 id="deepseek-request-logging-heading"><?php echo esc_html__( 'AI Request Logging', 'ai-connector-for-deepseek-guducat-ver' ); ?></h2>
				<?php $this->render_logging_status( $logging_status ); ?>
			</section>

			<section class="deepseek-connector-admin__section" aria-labelledby="deepseek-balance-heading">
				<h2 id="deepseek-balance-heading"><?php echo esc_html__( 'Account balance', 'ai-connector-for-deepseek-guducat-ver' ); ?></h2>
				<p><?php echo esc_html__( 'Balance is queried only after an explicit refresh.', 'ai-connector-for-deepseek-guducat-ver' ); ?></p>
				<form method="post" class="deepseek-connector-admin__actions"><?php wp_nonce_field( DeepSeekBalancePage::NONCE_ACTION ); ?><input type="hidden" name="<?php echo esc_attr( DeepSeekBalancePage::ACTION_FIELD ); ?>" value="refresh"><?php submit_button( __( 'Refresh balance', 'ai-connector-for-deepseek-guducat-ver' ), 'secondary', 'submit', false ); ?></form>
				<?php $balance->render_result( $balance_result ); ?>
			</section>

			<section class="deepseek-connector-admin__section" aria-label="<?php echo esc_attr__( 'Pricing rules', 'ai-connector-for-deepseek-guducat-ver' ); ?>">
				<?php $pricing->render( $pricing_result ); ?>
			</section>

			<section class="deepseek-connector-admin__section deepseek-connector-admin__models">
				<?php $model_settings->render( $model_result ); ?>
			</section>
		</div>
		<?php
	}

	/**
	 * Render the WordPress AI request logging status and next action.
	 *
	 * @param DeepSeekRequestLoggingStatus $status Current request logging status.
	 */
	private function render_logging_status( DeepSeekRequestLoggingStatus $status ): void {
		$state = $status->get_state();
		if ( DeepSeekRequestLoggingStatus::STATE_ENABLED === $state ) {
			?>
			<div class="notice notice-success inline deepseek-connector-admin__status">
				<p><strong><?php echo esc_html__( 'Enabled', 'ai-connector-for-deepseek-guducat-ver' ); ?></strong> <?php echo esc_html__( 'WordPress AI is recording requests for observability and debugging.', 'ai-connector-for-deepseek-guducat-ver' ); ?></p>
			</div>
			<p><a class="button button-primary" href="<?php echo esc_url( $status->get_logs_url() ); ?>"><?php echo esc_html__( 'View AI Request Logs', 'ai-connector-for-deepseek-guducat-ver' ); ?></a></p>
			<?php
			return;
		}

		if ( DeepSeekRequestLoggingStatus::STATE_DISABLED === $state ) {
			?>
			<div class="notice notice-warning inline deepseek-connector-admin__status">
				<p><strong><?php echo esc_html__( 'Disabled', 'ai-connector-for-deepseek-guducat-ver' ); ?></strong> <?php echo esc_html__( 'Enable the experimental AI Request Logging feature in WordPress AI settings to record requests.', 'ai-connector-for-deepseek-guducat-ver' ); ?></p>
			</div>
			<p><a class="button button-secondary" href="<?php echo esc_url( $status->get_settings_url() ); ?>"><?php echo esc_html__( 'Open AI Settings', 'ai-connector-for-deepseek-guducat-ver' ); ?></a></p>
			<?php
			return;
		}
		?>
		<div class="notice notice-info inline deepseek-connector-admin__status">
			<p><strong><?php echo esc_html__( 'WordPress AI unavailable', 'ai-connector-for-deepseek-guducat-ver' ); ?></strong> <?php echo esc_html__( 'Install and activate the WordPress AI plugin to use AI Request Logging.', 'ai-connector-for-deepseek-guducat-ver' ); ?></p>
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
