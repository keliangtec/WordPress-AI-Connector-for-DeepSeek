<?php
/**
 * Tests for DeepSeek model settings.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use Guducat\DeepSeekAiProvider\Models\DeepSeekModelSettings;
use PHPUnit\Framework\TestCase;

/** Verifies model settings normalization and persistence. */
final class DeepSeekModelSettingsTest extends TestCase {
	/** Reset test options. */
	protected function setUp(): void {
		$GLOBALS['deepseek_test_options'] = array();
	}

	/** Verify normalization rejects invalid IDs and sorts remote models. */
	public function test_sanitize_normalizes_models_and_rejects_invalid_values(): void {
		$settings = DeepSeekModelSettings::sanitize(
			array(
				'remote_models'      => array( 'deepseek-z', 'deepseek-a', 'deepseek-z', 'bad model' ),
				'overrides'          => array(
					'deepseek-z' => 'text_image',
					'bad model'  => 'text',
				),
				'experimental_model' => array(
					'id'         => 'deepseek-exp',
					'input_mode' => 'text_image',
				),
			)
		);

		$this->assertSame( array( 'deepseek-a', 'deepseek-z' ), $settings['remote_models'] );
		$this->assertSame( array( 'deepseek-z' => 'text_image' ), $settings['overrides'] );
		$this->assertSame(
			array(
				'id'         => 'deepseek-exp',
				'input_mode' => 'text_image',
			),
			$settings['experimental_model']
		);
	}

	/** Verify settings are persisted without autoloading. */
	public function test_save_uses_non_autoloading_option(): void {
		$this->assertTrue( DeepSeekModelSettings::save( array( 'remote_models' => array( 'deepseek-flash' ) ) ) );
		$this->assertSame( false, $GLOBALS['deepseek_test_option_autoload'] );
		$this->assertSame( array( 'deepseek-flash' ), DeepSeekModelSettings::get()['remote_models'] );
	}

	/** Verify the default capability selection removes an override. */
	public function test_default_override_is_not_persisted(): void {
		$settings = DeepSeekModelSettings::sanitize(
			array(
				'overrides' => array( 'deepseek-flash' => 'default' ),
			)
		);

		$this->assertSame( array(), $settings['overrides'] );
	}
}
