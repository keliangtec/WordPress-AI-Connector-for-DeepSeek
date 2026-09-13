<?php
/**
 * WordPress AI request logging status.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider\Admin;

use Throwable;
use WordPress\AI\Experiments\AI_Request_Logging\AI_Request_Logging;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Reads the effective WordPress AI request logging state without changing it.
 */
final class DeepSeekRequestLoggingStatus {
	public const STATE_ENABLED = 'enabled';

	public const STATE_DISABLED = 'disabled';

	public const STATE_UNAVAILABLE = 'unavailable';

	/**
	 * WordPress AI availability checker.
	 *
	 * @var callable(): bool
	 */
	private $availability_checker;

	/**
	 * Official feature loader.
	 *
	 * @var callable(): object|null
	 */
	private $feature_loader;

	/**
	 * WordPress option reader.
	 *
	 * @var callable(string, mixed=): mixed
	 */
	private $option_reader;

	/**
	 * WordPress filter runner.
	 *
	 * @var callable(string, mixed): mixed
	 */
	private $filter_runner;

	/**
	 * WordPress capability checker.
	 *
	 * @var callable(string): bool
	 */
	private $capability_checker;

	/**
	 * Create the status reader.
	 *
	 * @param (callable(): bool)|null                $availability_checker Optional availability checker.
	 * @param (callable(): object|null)|null         $feature_loader       Optional official feature loader.
	 * @param (callable(string, mixed=): mixed)|null $option_reader        Optional option reader.
	 * @param (callable(string, mixed): mixed)|null  $filter_runner        Optional filter runner.
	 * @param (callable(string): bool)|null          $capability_checker   Optional capability checker.
	 */
	public function __construct(
		?callable $availability_checker = null,
		?callable $feature_loader = null,
		?callable $option_reader = null,
		?callable $filter_runner = null,
		?callable $capability_checker = null
	) {
		$this->availability_checker = $availability_checker ?? static function (): bool {
			return defined( 'WPAI_VERSION' ) || class_exists( AI_Request_Logging::class );
		};
		$this->feature_loader       = $feature_loader ?? static function () {
			if ( ! class_exists( AI_Request_Logging::class ) ) {
				return null;
			}

			return new AI_Request_Logging();
		};
		$this->option_reader        = $option_reader ?? 'get_option';
		$this->filter_runner        = $filter_runner ?? 'apply_filters';
		$this->capability_checker   = $capability_checker ?? 'current_user_can';
	}

	/** Whether the WordPress AI plugin is available. */
	public function is_ai_available(): bool {
		return (bool) call_user_func( $this->availability_checker );
	}

	/** Whether AI request logging is effectively enabled. */
	public function is_enabled(): bool {
		if ( ! $this->is_ai_available() ) {
			return false;
		}

		try {
			$feature = call_user_func( $this->feature_loader );
			if ( is_object( $feature ) && is_callable( array( $feature, 'is_enabled' ) ) ) {
				$runtime_enabled = (bool) call_user_func( $this->filter_runner, 'wpai_features_enabled', true );
				return $runtime_enabled && (bool) $feature->is_enabled();
			}
		} catch ( Throwable $exception ) {
			unset( $exception );
		}

		$global_enabled  = (bool) call_user_func( $this->option_reader, 'wpai_features_enabled', false );
		$runtime_enabled = (bool) call_user_func( $this->filter_runner, 'wpai_features_enabled', true );
		$feature_enabled = (bool) call_user_func(
			$this->option_reader,
			'wpai_feature_ai-request-logging_enabled',
			false
		);
		$feature_enabled = (bool) call_user_func(
			$this->filter_runner,
			'wpai_feature_ai-request-logging_enabled',
			$feature_enabled
		);

		return $runtime_enabled && $global_enabled && $feature_enabled;
	}

	/** Get the current status identifier. */
	public function get_state(): string {
		if ( ! $this->is_ai_available() ) {
			return self::STATE_UNAVAILABLE;
		}

		return $this->is_enabled() ? self::STATE_ENABLED : self::STATE_DISABLED;
	}

	/** Whether the current user can open the linked administrator pages. */
	public function can_manage(): bool {
		return (bool) call_user_func( $this->capability_checker, 'manage_options' );
	}

	/** Get the WordPress AI settings URL. */
	public function get_settings_url(): string {
		return admin_url( 'options-general.php?page=ai-wp-admin' );
	}

	/** Get the canonical AI Request Logs URL. */
	public function get_logs_url(): string {
		return admin_url( 'tools.php?page=ai-request-logs' );
	}
}
