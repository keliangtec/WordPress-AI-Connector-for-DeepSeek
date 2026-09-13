<?php
/**
 * Test suite bootstrap.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! is_readable( $autoload ) ) {
	throw new RuntimeException( 'Composer autoloader not found. Run "composer install" before running the test suite.' );
}

require_once $autoload;

if ( ! function_exists( 'plugin_dir_path' ) ) {
	/**
	 * Return the directory path for a plugin file.
	 *
	 * @param string $file Plugin file path.
	 * @return string
	 */
	function plugin_dir_path( string $file ): string {
		return rtrim( dirname( $file ), '/\\' ) . DIRECTORY_SEPARATOR;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Register a WordPress action callback for isolated tests.
	 *
	 * @param string   $hook_name     Action hook name.
	 * @param callable $callback      Action callback.
	 * @param int      $priority      Callback priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 * @return bool
	 */
	function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['deepseek_ai_provider_test_actions'][] = array(
			'hook_name'     => $hook_name,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return true;
	}
}
