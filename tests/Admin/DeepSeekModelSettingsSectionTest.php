<?php
/**
 * Tests for the model settings administration section.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use Guducat\DeepSeekAiProvider\Admin\DeepSeekModelSettingsSection;
use PHPUnit\Framework\TestCase;

/** Verifies the model settings section request boundary. */
final class DeepSeekModelSettingsSectionTest extends TestCase {
	/** Reset request state. */
	protected function setUp(): void {
		$GLOBALS['deepseek_test_can_manage']  = true;
		$GLOBALS['deepseek_test_nonce_valid'] = false;
		$_POST                                = array();
	}

	/** Verify opening the section does not query DeepSeek. */
	public function test_get_request_does_not_refresh_or_save(): void {
		$section = new DeepSeekModelSettingsSection();

		$this->assertSame(
			array(
				'message' => '',
				'error'   => false,
			),
			$section->process_request()
		);
	}

	/** Verify unauthorized users cannot trigger model actions. */
	public function test_model_action_requires_manage_options(): void {
		$GLOBALS['deepseek_test_can_manage']                 = false;
		$_POST[ DeepSeekModelSettingsSection::ACTION_FIELD ] = 'refresh';

		$this->assertSame(
			array(
				'message' => 'Permission denied.',
				'error'   => true,
			),
			( new DeepSeekModelSettingsSection() )->process_request()
		);
	}
}
