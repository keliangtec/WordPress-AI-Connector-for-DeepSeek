<?php
/**
 * Tests for the DeepSeek balance admin page.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use Guducat\DeepSeekAiProvider\Admin\DeepSeekBalancePage;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the admin page security boundary and click-to-query behavior.
 */
final class DeepSeekBalancePageTest extends TestCase {
	/**
	 * Reset request globals between tests.
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['deepseek_test_can_manage']   = false;
		$GLOBALS['deepseek_test_nonce_valid']  = false;
		$GLOBALS['deepseek_test_nonce_action'] = null;
		$GLOBALS['pagenow']                    = null;
		$_POST                                 = array();
	}

	/**
	 * Verify unauthorized users cannot process the balance page request.
	 */
	public function test_balance_request_requires_manage_options(): void {
		$page = new DeepSeekBalancePage(
			static function (): array {
				throw new RuntimeException( 'Loader must not be called.' );
			}
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'manage_options' );
		$page->process_request();
	}

	/**
	 * Verify POST refresh requests require the page nonce before loading balance.
	 */
	public function test_refresh_request_requires_nonce_before_query(): void {
		$GLOBALS['deepseek_test_can_manage']        = true;
		$_POST[ DeepSeekBalancePage::ACTION_FIELD ] = 'refresh';
		$loader_called                              = false;
		$page                                       = new DeepSeekBalancePage(
			static function () use ( &$loader_called ): array {
				$loader_called = true;
				return array();
			}
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Invalid nonce.' );
		$page->process_request();

		$this->assertFalse( $loader_called );
		$this->assertSame( DeepSeekBalancePage::NONCE_ACTION, $GLOBALS['deepseek_test_nonce_action'] );
	}

	/**
	 * Verify opening the page without submitting the form does not query DeepSeek.
	 */
	public function test_get_request_does_not_query_balance(): void {
		$GLOBALS['deepseek_test_can_manage'] = true;
		$loader_called                       = false;
		$page                                = new DeepSeekBalancePage(
			static function () use ( &$loader_called ): array {
				$loader_called = true;
				return array();
			}
		);

		$this->assertSame(
			array(
				'balance' => null,
				'error'   => false,
			),
			$page->process_request()
		);
		$this->assertFalse( $loader_called );
	}

	/**
	 * Verify malformed action input does not trigger a query or PHP string conversion warning.
	 */
	public function test_array_action_input_does_not_query_balance(): void {
		$GLOBALS['deepseek_test_can_manage']        = true;
		$_POST[ DeepSeekBalancePage::ACTION_FIELD ] = array( 'refresh' );
		$loader_called                              = false;
		$page                                       = new DeepSeekBalancePage(
			static function () use ( &$loader_called ): array {
				$loader_called = true;
				return array();
			}
		);

		$this->assertSame(
			array(
				'balance' => null,
				'error'   => false,
			),
			$page->process_request()
		);
		$this->assertFalse( $loader_called );
	}

	/**
	 * Verify a valid nonce allows exactly one balance query.
	 */
	public function test_valid_refresh_request_loads_balance_once(): void {
		$GLOBALS['deepseek_test_can_manage']        = true;
		$GLOBALS['deepseek_test_nonce_valid']       = true;
		$_POST[ DeepSeekBalancePage::ACTION_FIELD ] = 'refresh';
		$loader_calls                               = 0;
		$expected                                   = array(
			'is_available'  => true,
			'balance_infos' => array(),
		);
		$page                                       = new DeepSeekBalancePage(
			static function () use ( &$loader_calls, $expected ): array {
				++$loader_calls;
				return $expected;
			}
		);

		$this->assertSame(
			array(
				'balance' => $expected,
				'error'   => false,
			),
			$page->process_request()
		);
		$this->assertSame( 1, $loader_calls );
	}

	/**
	 * Verify the rendered page displays validated balances after an explicit refresh.
	 */
	public function test_render_page_displays_balance_after_refresh(): void {
		$GLOBALS['deepseek_test_can_manage']        = true;
		$GLOBALS['deepseek_test_nonce_valid']       = true;
		$_POST[ DeepSeekBalancePage::ACTION_FIELD ] = 'refresh';
		$page                                       = new DeepSeekBalancePage(
			static function (): array {
				return array(
					'is_available'  => true,
					'balance_infos' => array(
						array(
							'currency'          => 'USD',
							'total_balance'     => '12.3456',
							'granted_balance'   => '1.0000',
							'topped_up_balance' => '11.3456',
						),
					),
				);
			}
		);

		ob_start();
		$page->render_page();
		$output = ob_get_clean();

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'USD', $output );
		$this->assertStringContainsString( '12.3456', $output );
		$this->assertStringContainsString( 'Available for API calls', $output );
	}

	/**
	 * Verify provider error details are not rendered to the administrator.
	 */
	public function test_render_page_hides_provider_error_details(): void {
		$GLOBALS['deepseek_test_can_manage']        = true;
		$GLOBALS['deepseek_test_nonce_valid']       = true;
		$_POST[ DeepSeekBalancePage::ACTION_FIELD ] = 'refresh';
		$page                                       = new DeepSeekBalancePage(
			static function (): array {
				throw new RuntimeException( 'secret-api-key-and-provider-response' );
			}
		);

		ob_start();
		$page->render_page();
		$output = ob_get_clean();

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'could not be loaded', $output );
		$this->assertStringNotContainsString( 'secret-api-key-and-provider-response', $output );
	}

	/**
	 * Verify the connector notice is rendered only for administrators on the connector page.
	 */
	public function test_connector_notice_is_limited_to_the_connector_page_and_admins(): void {
		$GLOBALS['deepseek_test_can_manage'] = true;
		$GLOBALS['pagenow']                  = 'options-connectors.php';
		$page                                = new DeepSeekBalancePage();

		ob_start();
		$page->render_connector_notice();
		$output = ob_get_clean();

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'deepseek-balance', $output );

		$GLOBALS['deepseek_test_can_manage'] = false;
		ob_start();
		$page->render_connector_notice();
		$unauthorized_output = ob_get_clean();

		$this->assertSame( '', $unauthorized_output );
	}
}
