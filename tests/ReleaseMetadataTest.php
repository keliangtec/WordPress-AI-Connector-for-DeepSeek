<?php
/**
 * Tests for the release metadata and archive builder.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Verifies strict release tag validation and package contents. */
final class ReleaseMetadataTest extends TestCase {
	/** Reject tags that contain prerelease or build metadata. */
	public function test_release_builder_rejects_non_semver_tags(): void {
		$command = 'bash bin/build-release.sh /tmp/deepseek-release-test v3.1.0-rc.1 2>&1';
		$output  = array();
		$status  = 0;
		exec( $command, $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Tests invoke the bounded local release script.

		$this->assertNotSame( 0, $status );
		$this->assertStringContainsString( 'must match vX.Y.Z', implode( "\n", $output ) );
	}

	/** Reject a tag whose version does not match the plugin metadata. */
	public function test_release_builder_rejects_version_mismatch(): void {
		$command = 'GITHUB_REF_NAME=main bash bin/build-release.sh /tmp/deepseek-release-test v9.9.9 2>&1';
		$output  = array();
		$status  = 0;
		exec( $command, $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Tests invoke the bounded local release script.

		$this->assertNotSame( 0, $status );
		$this->assertStringContainsString( 'version mismatch', strtolower( implode( "\n", $output ) ) );
	}

	/** Build an archive with one slug root and no development-only files. */
	public function test_release_builder_creates_whitelisted_archive(): void {
		if ( 0 !== $this->commandStatus( 'command -v zip' ) || 0 !== $this->commandStatus( 'command -v unzip' ) ) {
			$this->markTestSkipped( 'zip and unzip are required for archive validation.' );
		}
		$output_dir = sys_get_temp_dir() . '/deepseek-release-' . uniqid( '', true );
		$output     = array();
		$status     = 0;
		exec( 'GITHUB_REF_NAME=main bash bin/build-release.sh ' . escapeshellarg( $output_dir ) . ' v3.1.1 2>&1', $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Tests invoke the bounded local release script.

		$this->assertSame( 0, $status, implode( "\n", $output ) );
		$archive = $output_dir . '/ai-connector-for-deepseek-guducat-ver-v3.1.1.zip';
		$this->assertFileExists( $archive );

		$entries = trim( (string) shell_exec( 'unzip -Z1 ' . escapeshellarg( $archive ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- Tests inspect the bounded archive created above.
		$this->assertStringStartsWith( 'ai-connector-for-deepseek-guducat-ver/', $entries );
		$this->assertStringNotContainsString( '/tests/', $entries );
		$this->assertStringNotContainsString( '/vendor/', $entries );
		$this->assertStringNotContainsString( 'composer.json', $entries );
		$this->assertStringContainsString( '/assets/css/admin.css', $entries );
		$this->assertStringContainsString( '/languages/ai-connector-for-deepseek-guducat-ver-zh_CN.mo', $entries );

		$this->removeDirectory( $output_dir );
	}

	/**
	 * Return the exit status for a bounded shell capability check.
	 *
	 * @param string $command Capability command.
	 * @return int Exit status.
	 */
	private function commandStatus( string $command ): int {
		$status = 0;
		exec( $command, $output, $status ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Tests perform a bounded local capability check.
		return $status;
	}

	/**
	 * Remove a bounded temporary directory created by the test.
	 *
	 * @param string $directory Temporary directory.
	 */
	private function removeDirectory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$items = scandir( $directory );
		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $directory . DIRECTORY_SEPARATOR . $item;
			if ( is_dir( $path ) ) {
				$this->removeDirectory( $path );
			} else {
				unlink( $path );
			}
		}

		rmdir( $directory );
	}
}
