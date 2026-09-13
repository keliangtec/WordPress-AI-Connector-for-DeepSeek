<?php
/**
 * DeepSeek balance administration page.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider\Admin;

use Guducat\DeepSeekAiProvider\Balance\DeepSeekBalanceClient;
use Guducat\DeepSeekAiProvider\Provider\DeepSeekProvider;
use RuntimeException;
use Throwable;
use WordPress\AiClient\AiClient;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Provides a permission-protected, click-to-query DeepSeek balance page.
 */
final class DeepSeekBalancePage {
	public const PAGE_SLUG = 'deepseek-balance';

	public const ACTION_FIELD = 'deepseek_balance_action';

	public const NONCE_ACTION = 'deepseek_balance_refresh';

	/**
	 * Callback used to load the balance.
	 *
	 * @var callable(): array<string, mixed>
	 */
	private $balance_loader;

	/**
	 * Create the balance administration page.
	 *
	 * @param callable(): array<string, mixed>|null $balance_loader Optional balance loader for testing.
	 */
	public function __construct( ?callable $balance_loader = null ) {
		$this->balance_loader = $balance_loader ?? array( $this, 'load_balance' );
	}

	/**
	 * Register the page and connector-page link.
	 */
	public function register(): void {
		add_options_page(
			__( 'DeepSeek Balance', 'ai-connector-for-deepseek-guducat-ver' ),
			__( 'DeepSeek Balance', 'ai-connector-for-deepseek-guducat-ver' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
		add_action( 'admin_notices', array( $this, 'render_connector_notice' ) );
	}

	/**
	 * Process the explicit refresh action.
	 *
	 * @throws RuntimeException If the current user lacks the required capability.
	 * @return array{balance: array<string, mixed>|null, error: bool}
	 */
	public function process_request(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new RuntimeException( 'DeepSeek balance requires manage_options.' );
		}

		$action_value = isset( $_POST[ self::ACTION_FIELD ] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The value is immediately unslashed, type-checked, and sanitized below.
			? wp_unslash( $_POST[ self::ACTION_FIELD ] )
			: '';
		$action = is_string( $action_value )
			? sanitize_key( $action_value )
			: '';

		if ( 'refresh' !== $action ) {
			return array(
				'balance' => null,
				'error'   => false,
			);
		}

		check_admin_referer( self::NONCE_ACTION );

		try {
			$balance = call_user_func( $this->balance_loader );

			return array(
				'balance' => $balance,
				'error'   => false,
			);
		} catch ( Throwable $exception ) {
			unset( $exception );

			return array(
				'balance' => null,
				'error'   => true,
			);
		}
	}

	/**
	 * Render the balance page.
	 */
	public function render_page(): void {
		$result = $this->process_request();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'DeepSeek Balance', 'ai-connector-for-deepseek-guducat-ver' ); ?></h1>
			<p><?php echo esc_html__( 'The balance is queried only when you click the refresh button.', 'ai-connector-for-deepseek-guducat-ver' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::ACTION_FIELD ); ?>" value="refresh">
				<?php submit_button( __( 'Refresh balance', 'ai-connector-for-deepseek-guducat-ver' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php $this->render_result( $result ); ?>
		</div>
		<?php
	}

	/**
	 * Add a link to the independent balance page above the core connector screen.
	 */
	public function render_connector_notice(): void {
		global $pagenow;

		if ( 'options-connectors.php' !== $pagenow || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		printf(
			'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'DeepSeek account balance is available on the provider status page.', 'ai-connector-for-deepseek-guducat-ver' ),
			esc_url( $url ),
			esc_html__( 'View balance', 'ai-connector-for-deepseek-guducat-ver' )
		);
	}

	/**
	 * Load balance using the runtime provider authentication and transport.
	 *
	 * @throws RuntimeException If the runtime provider or authentication is unavailable.
	 * @return array<string, mixed> Validated DeepSeek balance.
	 */
	private function load_balance(): array {
		if ( ! class_exists( AiClient::class ) ) {
			throw new RuntimeException( 'WordPress AI Client is unavailable.' );
		}

		$registry = AiClient::defaultRegistry();
		if ( ! $registry->hasProvider( DeepSeekProvider::class ) ) {
			throw new RuntimeException( 'DeepSeek provider is unavailable.' );
		}

		$authentication = $registry->getProviderRequestAuthentication( DeepSeekProvider::class );
		if ( null === $authentication ) {
			throw new RuntimeException( 'DeepSeek authentication is unavailable.' );
		}

		return ( new DeepSeekBalanceClient( $registry->getHttpTransporter(), $authentication ) )->get_balance();
	}

	/**
	 * Render a sanitized result without exposing provider error details.
	 *
	 * @param array{balance: array<string, mixed>|null, error: bool} $result Result data.
	 */
	public function render_result( array $result ): void {
		if ( $result['error'] ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'The DeepSeek balance could not be loaded. Check the connector configuration and try again.', 'ai-connector-for-deepseek-guducat-ver' )
			);
			return;
		}

		if ( null === $result['balance'] ) {
			return;
		}

		$balance = $result['balance'];
		if ( ! isset( $balance['is_available'], $balance['balance_infos'] ) || ! is_array( $balance['balance_infos'] ) ) {
			return;
		}

		$status = $balance['is_available']
			? __( 'Available for API calls', 'ai-connector-for-deepseek-guducat-ver' )
			: __( 'Not available for API calls', 'ai-connector-for-deepseek-guducat-ver' );
		printf( '<p><strong>%s</strong></p>', esc_html( $status ) );
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Currency', 'ai-connector-for-deepseek-guducat-ver' ) . '</th>';
		echo '<th>' . esc_html__( 'Total balance', 'ai-connector-for-deepseek-guducat-ver' ) . '</th>';
		echo '<th>' . esc_html__( 'Granted balance', 'ai-connector-for-deepseek-guducat-ver' ) . '</th>';
		echo '<th>' . esc_html__( 'Topped-up balance', 'ai-connector-for-deepseek-guducat-ver' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $balance['balance_infos'] as $balance_info ) {
			if ( ! is_array( $balance_info ) ) {
				continue;
			}

			echo '<tr>';
			foreach ( array( 'currency', 'total_balance', 'granted_balance', 'topped_up_balance' ) as $field ) {
				$value = $balance_info[ $field ] ?? '';
				echo '<td>' . esc_html( is_string( $value ) ? $value : '' ) . '</td>';
			}
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}
