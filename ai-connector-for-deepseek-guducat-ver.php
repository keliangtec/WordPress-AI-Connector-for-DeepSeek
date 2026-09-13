<?php
/**
 * Plugin bootstrap for the DeepSeek AI provider.
 *
 * @package Guducat\DeepSeekAiProvider
 *
 * Plugin Name:       AI Connector for DeepSeek Guducat.ver
 * Description:       Registers DeepSeek as a provider for the WordPress AI Client.
 * Version:           3.1.1
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            Guducat / 孤独豹猫
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-connector-for-deepseek-guducat-ver
 * Domain Path:       /languages
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider;

use Guducat\DeepSeekAiProvider\Admin\DeepSeekAdminPage;
use Guducat\DeepSeekAiProvider\Observability\DeepSeekRequestLoggingIntegration;
use Guducat\DeepSeekAiProvider\Pricing\DeepSeekPricingCatalog;
use Guducat\DeepSeekAiProvider\Pricing\DeepSeekPricingRuleResolver;
use Guducat\DeepSeekAiProvider\Pricing\DeepSeekPricingSettings;
use Guducat\DeepSeekAiProvider\Pricing\DeepSeekUsageCostEstimator;
use Guducat\DeepSeekAiProvider\Provider\DeepSeekProvider;
use WordPress\AiClient\AiClient;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

define( 'DEEPSEEK_AI_PROVIDER_VERSION', '3.1.1' );
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

/** Load Connector translations for the current WordPress user locale. */
function load_textdomain(): void {
	load_plugin_textdomain(
		'ai-connector-for-deepseek-guducat-ver',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}

add_action( 'init', __NAMESPACE__ . '\\load_textdomain', 1 );

/** Register optional DeepSeek enrichments for WordPress AI request logs. */
function register_request_logging_integration(): void {
	$rules       = DeepSeekPricingCatalog::merge( DeepSeekPricingSettings::get() );
	$estimator   = new DeepSeekUsageCostEstimator( new DeepSeekPricingRuleResolver(), $rules );
	$integration = new DeepSeekRequestLoggingIntegration( $estimator );
	$integration->register();
}

add_action( 'init', __NAMESPACE__ . '\\register_request_logging_integration', 6 );

/** Clear the DeepSeek usage overview cache after a new AI request is logged. */
function clear_cost_overview_cache(): void {
	( new Admin\DeepSeekCostOverview() )->clear_cache();
}

add_action( 'wpai_request_logged', __NAMESPACE__ . '\\clear_cost_overview_cache', 10, 2 );

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
