<?php
/**
 * Tests for the WordPress AI request logging status helper.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use Guducat\DeepSeekAiProvider\Admin\DeepSeekRequestLoggingStatus;
use Guducat\DeepSeekAiProvider\Admin\DeepSeekAdminPage;
use PHPUnit\Framework\TestCase;

/** Verifies read-only request logging status detection. */
final class DeepSeekRequestLoggingStatusTest extends TestCase {
	/** Reset the shared WordPress test recorders. */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['deepseek_ai_provider_test_actions'] = array();
		$GLOBALS['deepseek_test_styles']              = array();
	}

	/**
	 * Verify an unavailable WordPress AI plugin is reported separately.
	 */
	public function test_reports_wordpress_ai_as_unavailable(): void {
		$status = $this->status(
			false,
			array(
				'wpai_features_enabled'                   => true,
				'wpai_feature_ai-request-logging_enabled' => true,
			)
		);

		$this->assertFalse( $status->is_ai_available() );
		$this->assertFalse( $status->is_enabled() );
		$this->assertSame( DeepSeekRequestLoggingStatus::STATE_UNAVAILABLE, $status->get_state() );
	}

	/**
	 * Verify both WordPress AI settings are required by the fallback detector.
	 *
	 * @param bool $global_enabled     Whether the global setting is enabled.
	 * @param bool $individual_enabled Whether the logging feature setting is enabled.
	 * @param bool $expected           Expected effective state.
	 * @dataProvider provide_option_states
	 */
	public function test_fallback_requires_both_options( bool $global_enabled, bool $individual_enabled, bool $expected ): void {
		$status = $this->status(
			true,
			array(
				'wpai_features_enabled'                   => $global_enabled,
				'wpai_feature_ai-request-logging_enabled' => $individual_enabled,
			)
		);

		$this->assertSame( $expected, $status->is_enabled() );
		$this->assertSame(
			$expected ? DeepSeekRequestLoggingStatus::STATE_ENABLED : DeepSeekRequestLoggingStatus::STATE_DISABLED,
			$status->get_state()
		);
	}

	/**
	 * Provide global and individual setting combinations.
	 *
	 * @return array<string, array{bool, bool, bool}>
	 */
	public function provide_option_states(): array {
		return array(
			'both enabled'     => array( true, true, true ),
			'global disabled'  => array( false, true, false ),
			'feature disabled' => array( true, false, false ),
			'both disabled'    => array( false, false, false ),
		);
	}

	/**
	 * Verify the individual feature filter is respected by the fallback detector.
	 */
	public function test_fallback_respects_individual_feature_filter(): void {
		$status = $this->status(
			true,
			array(
				'wpai_features_enabled'                   => true,
				'wpai_feature_ai-request-logging_enabled' => false,
			),
			array( 'wpai_feature_ai-request-logging_enabled' => true )
		);

		$this->assertTrue( $status->is_enabled() );
	}

	/**
	 * Verify the WordPress AI loader-level filter can disable all features.
	 */
	public function test_respects_global_runtime_filter(): void {
		$status = $this->status(
			true,
			array(
				'wpai_features_enabled'                   => true,
				'wpai_feature_ai-request-logging_enabled' => true,
			),
			array( 'wpai_features_enabled' => false )
		);

		$this->assertFalse( $status->is_enabled() );
	}

	/**
	 * Verify the official feature object is preferred when it is available.
	 */
	public function test_uses_official_feature_enabled_state_when_available(): void {
		$feature = new class() {
			/** Return the simulated official state. */
			public function is_enabled(): bool {
				return true;
			}
		};
		$status  = $this->status(
			true,
			array(
				'wpai_features_enabled'                   => false,
				'wpai_feature_ai-request-logging_enabled' => false,
			),
			array(),
			static function () use ( $feature ): object {
				return $feature;
			}
		);

		$this->assertTrue( $status->is_enabled() );
	}

	/**
	 * Verify URLs point to WordPress AI's canonical settings and log pages.
	 */
	public function test_exposes_canonical_admin_urls(): void {
		$status = $this->status( true, array(), array(), null, true );

		$this->assertSame( '/wp-admin/options-general.php?page=ai-wp-admin', $status->get_settings_url() );
		$this->assertSame( '/wp-admin/tools.php?page=ai-request-logs', $status->get_logs_url() );
		$this->assertTrue( $status->can_manage() );
	}

	/**
	 * Verify status actions remain restricted to administrators.
	 */
	public function test_reports_missing_manage_options_capability(): void {
		$status = $this->status( true, array(), array(), null, false );

		$this->assertFalse( $status->can_manage() );
	}

	/** Verify the page registers its scoped stylesheet callback. */
	public function test_admin_page_registers_asset_callback(): void {
		$page = new DeepSeekAdminPage();
		$page->register();

		$actions = $GLOBALS['deepseek_ai_provider_test_actions'];
		$this->assertSame( 'admin_notices', $actions[0]['hook_name'] );
		$this->assertSame( 'admin_enqueue_scripts', $actions[1]['hook_name'] );
		$this->assertSame( array( $page, 'enqueue_assets' ), $actions[1]['callback'] );
	}

	/** Verify admin CSS is loaded only for the Connector page hook. */
	public function test_admin_styles_are_scoped_to_connector_page(): void {
		$page = new DeepSeekAdminPage();
		$page->register();

		$page->enqueue_assets( 'settings_page_other-plugin' );
		$this->assertSame( array(), $GLOBALS['deepseek_test_styles'] );

		$page->enqueue_assets( 'settings_page_deepseek-connector' );
		/**
		 * Enqueued styles recorded by the WordPress stub.
		 *
		 * @var array<int, array{handle: string, source: string}> $styles
		 */
		$styles = $GLOBALS['deepseek_test_styles'];
		$this->assertCount( 1, $styles );
		$this->assertSame( 'deepseek-connector-admin', $styles[0]['handle'] );
		$this->assertStringEndsWith( '/assets/css/admin.css', $styles[0]['source'] );
	}

	/** Verify enabled logging exposes the canonical logs action. */
	public function test_enabled_status_renders_logs_action(): void {
		$output = $this->render_status(
			$this->status(
				true,
				array(),
				array(),
				static function () {
					return new class() {
						/** Return enabled for the rendering test. */
						public function is_enabled(): bool {
							return true;
						}
					};
				}
			)
		);

		$this->assertStringContainsString( 'Enabled', $output );
		$this->assertStringContainsString( '/wp-admin/tools.php?page=ai-request-logs', $output );
		$this->assertStringNotContainsString( '/wp-admin/options-general.php?page=ai-wp-admin', $output );
	}

	/** Verify disabled logging guides administrators to WordPress AI settings. */
	public function test_disabled_status_renders_settings_action(): void {
		$output = $this->render_status(
			$this->status(
				true,
				array(
					'wpai_features_enabled' => true,
					'wpai_feature_ai-request-logging_enabled' => false,
				)
			)
		);

		$this->assertStringContainsString( 'Disabled', $output );
		$this->assertStringContainsString( '/wp-admin/options-general.php?page=ai-wp-admin', $output );
		$this->assertStringNotContainsString( '/wp-admin/tools.php?page=ai-request-logs', $output );
	}

	/** Verify an unavailable WordPress AI plugin does not expose a broken action. */
	public function test_unavailable_status_renders_compatibility_message_without_action(): void {
		$output = $this->render_status( $this->status( false, array() ) );

		$this->assertStringContainsString( 'WordPress AI unavailable', $output );
		$this->assertStringNotContainsString( 'class="button', $output );
	}

	/**
	 * Build a status helper with isolated WordPress dependencies.
	 *
	 * @param bool                           $available       Whether WordPress AI is available.
	 * @param array<string, mixed>           $options         Test option values.
	 * @param array<string, mixed>           $filter_values   Filter overrides by hook name.
	 * @param (callable(): object|null)|null $feature_loader  Optional official feature loader.
	 * @param bool                           $can_manage      Whether the user can manage settings.
	 */
	private function status(
		bool $available,
		array $options,
		array $filter_values = array(),
		?callable $feature_loader = null,
		bool $can_manage = true
	): DeepSeekRequestLoggingStatus {
		return new DeepSeekRequestLoggingStatus(
			static function () use ( $available ): bool {
				return $available;
			},
			$feature_loader ?? static function () {
				return null;
			},
			static function ( string $name, $fallback = false ) use ( $options ) {
				return $options[ $name ] ?? $fallback;
			},
			static function ( string $hook_name, $value ) use ( $filter_values ) {
				return $filter_values[ $hook_name ] ?? $value;
			},
			static function ( string $capability ) use ( $can_manage ): bool {
				return 'manage_options' === $capability && $can_manage;
			}
		);
	}

	/**
	 * Render the page's status section without triggering balance or model requests.
	 *
	 * @param DeepSeekRequestLoggingStatus $status Status to render.
	 */
	private function render_status( DeepSeekRequestLoggingStatus $status ): string {
		$page   = new DeepSeekAdminPage();
		$method = new ReflectionMethod( $page, 'render_logging_status' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $page, $status );
		$output = ob_get_clean();

		$this->assertIsString( $output );
		return $output;
	}
}
