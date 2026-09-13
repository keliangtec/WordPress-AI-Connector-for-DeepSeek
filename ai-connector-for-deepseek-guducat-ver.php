<?php
/**
 * Plugin bootstrap for the DeepSeek AI provider.
 *
 * @package Guducat\DeepSeekAiProvider
 *
 * Plugin Name:       AI Connector for DeepSeek Guducat.ver
 * Description:       Registers DeepSeek as a provider for the WordPress AI Client.
 * Version:           0.2.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            Guducat / 孤独豹猫
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-connector-for-deepseek-guducat-ver
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider;

use Guducat\DeepSeekAiProvider\Admin\DeepSeekAdminPage;
use Guducat\DeepSeekAiProvider\Provider\DeepSeekProvider;
use WordPress\AiClient\AiClient;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

define( 'DEEPSEEK_AI_PROVIDER_VERSION', '0.2.0' );
define( 'DEEPSEEK_AI_PROVIDER_DIR', plugin_dir_path( __FILE__ ) );

$composer_autoload = DEEPSEEK_AI_PROVIDER_DIR . 'vendor/autoload.php';
if ( is_readable( $composer_autoload ) ) {
	require_once $composer_autoload;
}

spl_autoload_register(
	// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound -- PSR-4 terminology.
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$file           = DEEPSEEK_AI_PROVIDER_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Register the DeepSeek provider with the WordPress AI Client.
 *
 * @since 0.1.0
 * @return void
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();
	if ( $registry->hasProvider( DeepSeekProvider::class ) ) {
		return;
	}

	$registry->registerProvider( DeepSeekProvider::class );
}

add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );

/**
 * Register the DeepSeek administration page.
 *
 * @since 0.1.0
 * @return void
 */
function register_admin_pages(): void {
	( new DeepSeekAdminPage() )->register();
}

add_action( 'admin_menu', __NAMESPACE__ . '\\register_admin_pages' );
