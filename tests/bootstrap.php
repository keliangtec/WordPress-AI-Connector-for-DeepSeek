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

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

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

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Register a WordPress filter callback for isolated tests.
	 *
	 * @param string   $hook_name     Filter name.
	 * @param callable $callback      Filter callback.
	 * @param int      $priority      Callback priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 * @return bool
	 */
	function add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['deepseek_test_filters'][] = array(
			'hook_name'     => $hook_name,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Apply isolated test filters for a hook.
	 *
	 * @param string $hook_name Filter name.
	 * @param mixed  $value     Initial value.
	 * @param mixed  ...$args   Additional arguments.
	 * @return mixed
	 */
	function apply_filters( string $hook_name, $value, ...$args ) {
		$filters = $GLOBALS['deepseek_test_filters'] ?? array();
		usort(
			$filters,
			static function ( array $left, array $right ): int {
				return $left['priority'] <=> $right['priority'];
			}
		);
		foreach ( $filters as $filter ) {
			if ( $hook_name !== $filter['hook_name'] ) {
				continue;
			}
			$arguments = array_slice( array_merge( array( $value ), $args ), 0, $filter['accepted_args'] );
			$value     = $filter['callback']( ...$arguments );
		}
		return $value;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Return the test user's administrator capability.
	 *
	 * @param string $capability Requested capability.
	 * @return bool
	 */
	function current_user_can( string $capability ): bool {
		return 'manage_options' === $capability && (bool) ( $GLOBALS['deepseek_test_can_manage'] ?? false );
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * Verify the test nonce.
	 *
	 * @param string $action Nonce action.
	 * @return int
	 * @throws RuntimeException If the test nonce is invalid.
	 */
	function check_admin_referer( string $action ): int {
		$GLOBALS['deepseek_test_nonce_action'] = $action;
		if ( ! ( $GLOBALS['deepseek_test_nonce_valid'] ?? false ) ) {
			throw new RuntimeException( 'Invalid nonce.' );
		}

		return 1;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Sanitize a test action value.
	 *
	 * @param string $key Value to sanitize.
	 * @return string
	 */
	function sanitize_key( string $key ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '' );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Return an unslashed test value.
	 *
	 * @param mixed $value Value to unslash.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'add_options_page' ) ) {
	/**
	 * Register a test settings page.
	 *
	 * @param string   $page_title Page title.
	 * @param string   $menu_title Menu title.
	 * @param string   $capability Required capability.
	 * @param string   $menu_slug  Menu slug.
	 * @param callable $callback   Page callback.
	 * @return string
	 */
	function add_options_page( string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback ): string {
		$GLOBALS['deepseek_admin_page'] = array(
			'page_title' => $page_title,
			'menu_title' => $menu_title,
			'capability' => $capability,
			'menu_slug'  => $menu_slug,
			'callback'   => $callback,
		);
		return 'settings_page_' . $menu_slug;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Return an untranslated test string.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Translation domain.
	 * @return string
	 */
	function __( string $text, string $domain = '' ): string {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Return an escaped untranslated test string.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Translation domain.
	 * @return string
	 */
	function esc_html__( string $text, string $domain = '' ): string {
		unset( $domain );
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escape a test HTML value.
	 *
	 * @param string $text Value to escape.
	 * @return string
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Escape a test HTML attribute.
	 *
	 * @param string $text Value to escape.
	 * @return string
	 */
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * Return an escaped untranslated test attribute.
	 *
	 * @param string $text   Value.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_attr__( string $text, string $domain = '' ): string {
		unset( $domain );
		return esc_attr( $text );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Escape a test URL.
	 *
	 * @param string $url URL to escape.
	 * @return string
	 */
	function esc_url( string $url ): string {
		return htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Return a test admin URL.
	 *
	 * @param string $path Admin path.
	 * @return string
	 */
	function admin_url( string $path = '' ): string {
		return '/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	/**
	 * Return a stable test plugin basename.
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	function plugin_basename( string $file ): string {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	/**
	 * Record text domain loading in tests.
	 *
	 * @param string $domain          Text domain.
	 * @param bool   $deprecated      Deprecated argument.
	 * @param string $plugin_rel_path Relative language path.
	 * @return bool
	 */
	function load_plugin_textdomain( string $domain, bool $deprecated = false, string $plugin_rel_path = '' ): bool {
		$GLOBALS['deepseek_test_textdomain'] = compact( 'domain', 'deprecated', 'plugin_rel_path' );
		unset( $domain, $deprecated, $plugin_rel_path );
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * Record enqueued styles in tests.
	 *
	 * @param string       $handle       Style handle.
	 * @param string       $source       Style URL.
	 * @param array<mixed> $dependencies Style dependencies.
	 * @param mixed        $version      Style version.
	 * @return void
	 */
	function wp_enqueue_style( string $handle, string $source, array $dependencies = array(), $version = false ): void {
		$GLOBALS['deepseek_test_styles'][] = compact( 'handle', 'source', 'dependencies', 'version' );
		unset( $handle, $source, $dependencies, $version );
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	/**
	 * Return a deterministic test plugin URL.
	 *
	 * @param string $path   Relative path.
	 * @param string $plugin Plugin file.
	 * @return string
	 */
	function plugins_url( string $path = '', string $plugin = '' ): string {
		unset( $plugin );
		return '/wp-content/plugins/ai-connector-for-deepseek-guducat-ver/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Render a test nonce field.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	function wp_nonce_field( string $action ): void {
		unset( $action );
		echo '<input type="hidden" name="_wpnonce" value="test-nonce">';
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Read a test option.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $fallback    Fallback value.
	 * @return mixed Option value.
	 */
	function get_option( string $option_name, $fallback = false ) {
		return $GLOBALS['deepseek_test_options'][ $option_name ] ?? $fallback;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Write a test option.
	 *
	 * @param string    $option_name Option name.
	 * @param mixed     $value       Option value.
	 * @param bool|null $autoload    Autoload flag.
	 * @return bool Whether the write succeeded.
	 */
	function update_option( string $option_name, $value, ?bool $autoload = null ): bool {
		$GLOBALS['deepseek_test_options'][ $option_name ] = $value;
		$GLOBALS['deepseek_test_option_autoload']         = $autoload;
		return true;
	}
}

if ( ! function_exists( 'selected' ) ) {
	/**
	 * Render a selected attribute in tests.
	 *
	 * @param mixed $selected   Selected value.
	 * @param mixed $current    Current value.
	 * @param bool  $should_echo Whether to echo the attribute.
	 * @return string Rendered attribute.
	 */
	function selected( $selected, $current, bool $should_echo = true ): string {
		$result = (string) $selected === (string) $current ? ' selected="selected"' : '';
		if ( $should_echo ) {
			echo esc_attr( $result );
		}
		return $result;
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	/**
	 * Format a number using a deterministic test locale.
	 *
	 * @param float|int $number   Number to format.
	 * @param int       $decimals Decimal places.
	 * @return string
	 */
	function number_format_i18n( $number, int $decimals = 0 ): string {
		return number_format( (float) $number, $decimals, '.', ',' );
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	/**
	 * Render a test submit button.
	 *
	 * @param string $text          Button label.
	 * @param string $type          Button type.
	 * @param string $name          Button name.
	 * @param bool   $wrap          Whether to wrap the button.
	 * @return void
	 */
	function submit_button( string $text = 'Submit', string $type = 'primary', string $name = 'submit', bool $wrap = true ): void {
		unset( $type, $wrap );
		echo '<button type="submit" name="' . esc_attr( $name ) . '">' . esc_html( $text ) . '</button>';
	}
}
