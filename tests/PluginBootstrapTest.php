<?php
/**
 * Tests for the plugin bootstrap and package identity.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use Guducat\DeepSeekAiProvider\Provider\DeepSeekProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the plugin identity and bootstrap behavior.
 */
final class PluginBootstrapTest extends TestCase {
	private const MAIN_FILE = __DIR__ . '/../ai-connector-for-deepseek-guducat-ver.php';

	/**
	 * Load the plugin when the expected main file is present.
	 */
	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__ ) . '/' );
		}

		$GLOBALS['deepseek_ai_provider_test_actions'] = array();

		if ( is_readable( self::MAIN_FILE ) ) {
			require_once self::MAIN_FILE;
		}
	}

	/**
	 * Verify the public plugin headers.
	 */
	public function test_main_file_has_expected_plugin_headers(): void {
		$this->assertFileExists( self::MAIN_FILE );

		$contents = file_get_contents( self::MAIN_FILE );
		$this->assertIsString( $contents );
		$this->assertStringContainsString( 'Plugin Name:       AI Connector for DeepSeek Guducat.ver', $contents );
		$this->assertStringContainsString( 'Version:           0.3.0', $contents );
		$this->assertStringContainsString( 'Requires at least: 7.0', $contents );
		$this->assertStringContainsString( 'Requires PHP:      7.4', $contents );
		$this->assertMatchesRegularExpression( '/^[ \t]*\*[ \t]*Author:[ \t]+Guducat \/ 孤独豹猫[ \t]*$/m', $contents );
		$this->assertStringContainsString( 'Text Domain:       ai-connector-for-deepseek-guducat-ver', $contents );
		$this->assertStringContainsString( 'License:           GPL-2.0-or-later', $contents );
	}

	/**
	 * Verify the original entry point was replaced.
	 */
	public function test_old_main_file_is_removed(): void {
		$this->assertFileDoesNotExist( __DIR__ . '/../deepseek-ai-provider.php' );
	}

	/**
	 * Verify runtime class files terminate cleanly when requested outside WordPress.
	 */
	public function test_runtime_class_files_block_direct_access(): void {
		$source_files = array(
			__DIR__ . '/../src/Admin/DeepSeekBalancePage.php',
			__DIR__ . '/../src/Admin/DeepSeekBalanceSection.php',
			__DIR__ . '/../src/Admin/DeepSeekModelSettingsSection.php',
			__DIR__ . '/../src/Admin/DeepSeekAdminPage.php',
			__DIR__ . '/../src/Admin/DeepSeekPricingSection.php',
			__DIR__ . '/../src/Admin/DeepSeekRequestLoggingStatus.php',
			__DIR__ . '/../src/Balance/DeepSeekBalanceClient.php',
			__DIR__ . '/../src/Observability/DeepSeekRequestLoggingIntegration.php',
			__DIR__ . '/../src/Pricing/DeepSeekPricingCatalog.php',
			__DIR__ . '/../src/Pricing/DeepSeekPricingRuleResolver.php',
			__DIR__ . '/../src/Pricing/DeepSeekPricingSettings.php',
			__DIR__ . '/../src/Pricing/DeepSeekUsageCostEstimator.php',
			__DIR__ . '/../src/Models/DeepSeekModelSettings.php',
			__DIR__ . '/../src/Metadata/DeepSeekModelMetadataDirectory.php',
			__DIR__ . '/../src/Models/DeepSeekTextGenerationModel.php',
			__DIR__ . '/../src/Provider/DeepSeekProvider.php',
		);
		$script       = <<<'PHP'
require $argv[1];
echo 'AFTER';
PHP;

		foreach ( $source_files as $source_file ) {
			list( $exit_code, $stdout, $stderr ) = $this->runIsolatedPhp( $script, $source_file );

			$this->assertSame( 0, $exit_code, $source_file . ': ' . $stderr );
			$this->assertSame( '', $stdout, $source_file );
			$this->assertSame( '', $stderr, $source_file );
		}
	}

	/**
	 * Verify all plugin classes use the fork namespace.
	 */
	public function test_source_files_use_the_new_namespace(): void {
		$source_files = array(
			__DIR__ . '/../src/Metadata/DeepSeekModelMetadataDirectory.php',
			__DIR__ . '/../src/Models/DeepSeekTextGenerationModel.php',
			__DIR__ . '/../src/Provider/DeepSeekProvider.php',
		);

		foreach ( $source_files as $source_file ) {
			$contents = file_get_contents( $source_file );
			$this->assertIsString( $contents );
			$this->assertStringNotContainsString( 'Sajjad67\\AiProviderForDeepSeek', $contents, $source_file );
		}
	}

	/**
	 * Verify constants, autoloading, and hook registration.
	 */
	public function test_bootstrap_defines_constants_loads_provider_and_registers_init_hook(): void {
		$this->assertSame( '0.3.0', constant( 'DEEPSEEK_AI_PROVIDER_VERSION' ) );
		$this->assertSame( dirname( realpath( self::MAIN_FILE ) ) . DIRECTORY_SEPARATOR, constant( 'DEEPSEEK_AI_PROVIDER_DIR' ) );
		$this->assertTrue( class_exists( DeepSeekProvider::class ) );

		$actions = $GLOBALS['deepseek_ai_provider_test_actions'];
		$this->assertCount( 4, $actions );
		$this->assertSame( 'init', $actions[0]['hook_name'] );
		$this->assertSame( 'Guducat\\DeepSeekAiProvider\\register_provider', $actions[0]['callback'] );
		$this->assertSame( 5, $actions[0]['priority'] );
		$this->assertSame( 'Guducat\\DeepSeekAiProvider\\load_textdomain', $actions[1]['callback'] );
		$this->assertSame( 1, $actions[1]['priority'] );
		$this->assertSame( 'Guducat\\DeepSeekAiProvider\\register_request_logging_integration', $actions[2]['callback'] );
		$this->assertSame( 6, $actions[2]['priority'] );
		$this->assertSame( 'admin_menu', $actions[3]['hook_name'] );
		$this->assertSame( 'Guducat\\DeepSeekAiProvider\\register_admin_pages', $actions[3]['callback'] );
		$this->assertSame( 10, $actions[3]['priority'] );
	}

	/**
	 * Verify the admin hook registers the balance page and its connector notice.
	 */
	public function test_admin_hook_registers_balance_page(): void {
		$temp_dir = sys_get_temp_dir() . '/deepseek-ai-provider-admin-' . uniqid( '', true );
		$this->assertTrue( mkdir( $temp_dir . '/src/Admin', 0777, true ) );
		$this->assertTrue( mkdir( $temp_dir . '/src/Balance', 0777, true ) );
		$this->assertTrue( mkdir( $temp_dir . '/src/Provider', 0777, true ) );
		$this->assertTrue( mkdir( $temp_dir . '/src/Models', 0777, true ) );
		$this->assertTrue( mkdir( $temp_dir . '/src/Metadata', 0777, true ) );
		$this->assertTrue( copy( self::MAIN_FILE, $temp_dir . '/deepseek-ai-provider.php' ) );
		$this->assertTrue(
			copy(
				__DIR__ . '/../src/Admin/DeepSeekBalancePage.php',
				$temp_dir . '/src/Admin/DeepSeekBalancePage.php'
			)
		);
		$this->assertTrue(
			copy(
				__DIR__ . '/../src/Balance/DeepSeekBalanceClient.php',
				$temp_dir . '/src/Balance/DeepSeekBalanceClient.php'
			)
		);
		$this->assertTrue(
			copy(
				__DIR__ . '/../src/Provider/DeepSeekProvider.php',
				$temp_dir . '/src/Provider/DeepSeekProvider.php'
			)
		);
		foreach ( array( 'DeepSeekAdminPage.php', 'DeepSeekBalanceSection.php', 'DeepSeekModelSettingsSection.php' ) as $admin_file ) {
			$this->assertTrue( copy( __DIR__ . '/../src/Admin/' . $admin_file, $temp_dir . '/src/Admin/' . $admin_file ) );
		}
		$this->assertTrue( copy( __DIR__ . '/../src/Models/DeepSeekModelSettings.php', $temp_dir . '/src/Models/DeepSeekModelSettings.php' ) );
		$this->assertTrue( copy( __DIR__ . '/../src/Metadata/DeepSeekModelMetadataDirectory.php', $temp_dir . '/src/Metadata/DeepSeekModelMetadataDirectory.php' ) );

		$script = <<<'PHP'
namespace WordPress\AiClient\Providers\ApiBasedImplementation {
	abstract class AbstractApiProvider {}
}

namespace WordPress\AiClient\Providers\Http\Contracts {
	interface HttpTransporterInterface {}
	interface RequestAuthenticationInterface {}
}

namespace WordPress\AiClient {
	final class AiClient {}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	function plugin_dir_path( string $file ): string {
		return rtrim( dirname( $file ), '/\\' ) . DIRECTORY_SEPARATOR;
	}

	function plugin_basename( string $file ): string {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}

	function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['deepseek_admin_actions'][] = compact( 'hook_name', 'callback', 'priority', 'accepted_args' );
		return true;
	}

	function add_options_page( string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback ): string {
		$GLOBALS['deepseek_admin_page'] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback' );
		return 'settings_page_' . $menu_slug;
	}

	function __( string $text, string $domain = '' ): string {
		return $text;
	}

	require $argv[1];
	\Guducat\DeepSeekAiProvider\register_admin_pages();

	$page = $GLOBALS['deepseek_admin_page'] ?? array();
		if ( 'deepseek-connector' !== ( $page['menu_slug'] ?? null ) || 'manage_options' !== ( $page['capability'] ?? null ) ) {
		exit( 2 );
	}

	$notice_action = $GLOBALS['deepseek_admin_actions'][4] ?? array();
	if ( 'admin_notices' !== ( $notice_action['hook_name'] ?? null ) ) {
		exit( 3 );
	}

	echo 'OK';
}
PHP;

		list( $exit_code, $stdout, $stderr ) = $this->runIsolatedPhp( $script, $temp_dir . '/deepseek-ai-provider.php' );

		unlink( $temp_dir . '/src/Admin/DeepSeekBalancePage.php' );
		unlink( $temp_dir . '/src/Admin/DeepSeekAdminPage.php' );
		unlink( $temp_dir . '/src/Admin/DeepSeekBalanceSection.php' );
		unlink( $temp_dir . '/src/Admin/DeepSeekModelSettingsSection.php' );
		unlink( $temp_dir . '/src/Balance/DeepSeekBalanceClient.php' );
		unlink( $temp_dir . '/src/Provider/DeepSeekProvider.php' );
		unlink( $temp_dir . '/src/Models/DeepSeekModelSettings.php' );
		unlink( $temp_dir . '/src/Metadata/DeepSeekModelMetadataDirectory.php' );
		unlink( $temp_dir . '/deepseek-ai-provider.php' );
		rmdir( $temp_dir . '/src/Admin' );
		rmdir( $temp_dir . '/src/Balance' );
		rmdir( $temp_dir . '/src/Provider' );
		rmdir( $temp_dir . '/src/Models' );
		rmdir( $temp_dir . '/src/Metadata' );
		rmdir( $temp_dir . '/src' );
		rmdir( $temp_dir );

		$this->assertSame( 0, $exit_code, 'stdout=' . $stdout . '; stderr=' . $stderr );
		$this->assertSame( 'OK', $stdout );
	}

	/**
	 * Verify Composer's autoloader is loaded when the plugin package includes it.
	 */
	public function test_bootstrap_loads_composer_autoloader_when_present(): void {
		$temp_dir = sys_get_temp_dir() . '/deepseek-ai-provider-composer-' . uniqid( '', true );
		$this->assertTrue( mkdir( $temp_dir . '/vendor', 0777, true ) );
		$this->assertTrue( copy( self::MAIN_FILE, $temp_dir . '/deepseek-ai-provider.php' ) );
		$this->assertNotFalse(
			file_put_contents(
				$temp_dir . '/vendor/autoload.php',
				"<?php\ndefine( 'DEEPSEEK_AI_PROVIDER_COMPOSER_LOADED', true );\n"
			)
		);

		$script = <<<'PHP'
define( 'ABSPATH', __DIR__ . '/' );

function plugin_dir_path( string $file ): string {
	return rtrim( dirname( $file ), '/\\' ) . DIRECTORY_SEPARATOR;
}

function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	return true;
}

require $argv[1];

if ( ! defined( 'DEEPSEEK_AI_PROVIDER_COMPOSER_LOADED' ) ) {
	exit( 2 );
}

echo 'OK';
PHP;

		list( $exit_code, $stdout, $stderr ) = $this->runIsolatedPhp( $script, $temp_dir . '/deepseek-ai-provider.php' );

		unlink( $temp_dir . '/vendor/autoload.php' );
		unlink( $temp_dir . '/deepseek-ai-provider.php' );
		rmdir( $temp_dir . '/vendor' );
		rmdir( $temp_dir );

		$this->assertSame( 0, $exit_code, $stderr );
		$this->assertSame( 'OK', $stdout );
	}

	/**
	 * Verify repeated registration does not register the provider twice.
	 */
	public function test_registration_is_idempotent(): void {
		$script = <<<'PHP'
namespace WordPress\AiClient {
	final class TestRegistry {
		public int $register_calls = 0;
		private array $providers = array();

		public function hasProvider( string $provider ): bool {
			return isset( $this->providers[ $provider ] );
		}

		public function registerProvider( string $provider ): void {
			++$this->register_calls;
			$this->providers[ $provider ] = true;
		}
	}

	final class AiClient {
		private static ?TestRegistry $registry = null;

		public static function defaultRegistry(): TestRegistry {
			if ( null === self::$registry ) {
				self::$registry = new TestRegistry();
			}

			return self::$registry;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	function plugin_dir_path( string $file ): string {
		return rtrim( dirname( $file ), '/\\' ) . DIRECTORY_SEPARATOR;
	}

	function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		return true;
	}

	require $argv[1];

	\Guducat\DeepSeekAiProvider\register_provider();
	\Guducat\DeepSeekAiProvider\register_provider();

	$registry = \WordPress\AiClient\AiClient::defaultRegistry();
	if ( 1 !== $registry->register_calls ) {
		exit( 2 );
	}

	echo 'OK';
}
PHP;

		list( $exit_code, $stdout, $stderr ) = $this->runIsolatedPhp( $script, self::MAIN_FILE );

		$this->assertSame( 0, $exit_code, $stderr );
		$this->assertSame( 'OK', $stdout );
	}

	/**
	 * Verify the local autoloader works without Composer or the AI Client.
	 */
	public function test_fallback_autoloader_and_registration_without_ai_client(): void {
		$this->assertFileExists( self::MAIN_FILE );

		$temp_dir = sys_get_temp_dir() . '/deepseek-ai-provider-' . uniqid( '', true );
		$this->assertTrue( mkdir( $temp_dir . '/src/Provider', 0777, true ) );
		$this->assertTrue( copy( self::MAIN_FILE, $temp_dir . '/deepseek-ai-provider.php' ) );
		$this->assertTrue(
			copy(
				__DIR__ . '/../src/Provider/DeepSeekProvider.php',
				$temp_dir . '/src/Provider/DeepSeekProvider.php'
			)
		);

		$script = <<<'PHP'
namespace WordPress\AiClient\Providers\ApiBasedImplementation {
	abstract class AbstractApiProvider {}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	function plugin_dir_path( string $file ): string {
		return rtrim( dirname( $file ), '/\\' ) . DIRECTORY_SEPARATOR;
	}

	function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['deepseek_ai_provider_test_actions'][] = compact( 'hook_name', 'callback', 'priority', 'accepted_args' );
		return true;
	}

	require $argv[1];

	\Guducat\DeepSeekAiProvider\register_provider();

	if ( ! class_exists( \Guducat\DeepSeekAiProvider\Provider\DeepSeekProvider::class ) ) {
		exit( 2 );
	}

	$action = $GLOBALS['deepseek_ai_provider_test_actions'][0] ?? array();
	if ( 'init' !== ( $action['hook_name'] ?? null ) || 5 !== ( $action['priority'] ?? null ) ) {
		exit( 3 );
	}

	echo 'OK';
}
PHP;

		list( $exit_code, $stdout, $stderr ) = $this->runIsolatedPhp( $script, $temp_dir . '/deepseek-ai-provider.php' );

		unlink( $temp_dir . '/src/Provider/DeepSeekProvider.php' );
		unlink( $temp_dir . '/deepseek-ai-provider.php' );
		rmdir( $temp_dir . '/src/Provider' );
		rmdir( $temp_dir . '/src' );
		rmdir( $temp_dir );

		$this->assertSame( 0, $exit_code, $stderr );
		$this->assertSame( 'OK', $stdout );
	}

	/**
	 * Execute PHP code in a separate process.
	 *
	 * @param string $script    PHP source passed to the CLI.
	 * @param string $main_file Plugin file passed as the first argument.
	 * @return array{int, string, string} Exit code, standard output, and standard error.
	 */
	private function runIsolatedPhp( string $script, string $main_file ): array {
		$command = array( PHP_BINARY, '-r', $script, $main_file );
		$pipes   = array();
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Isolated runtime verifies behavior without Composer or the AI Client.
		$process = proc_open(
			$command,
			array(
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes
		);

		$this->assertIsResource( $process );
		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$exit_code = proc_close( $process );

		return array( $exit_code, (string) $stdout, (string) $stderr );
	}
}
