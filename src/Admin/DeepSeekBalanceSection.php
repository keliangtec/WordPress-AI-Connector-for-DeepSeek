<?php
/**
 * DeepSeek balance section compatibility wrapper.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Reuses the hardened balance implementation as a page section.
 */
final class DeepSeekBalanceSection {
	/**
	 * Wrapped balance page.
	 *
	 * @var DeepSeekBalancePage
	 */
	private DeepSeekBalancePage $page;

	/**
	 * Create the balance section.
	 *
	 * @param callable(): array<string, mixed>|null $balance_loader Optional loader.
	 */
	public function __construct( ?callable $balance_loader = null ) {
		$this->page = new DeepSeekBalancePage( $balance_loader );
	}

	/**
	 * Process a balance request.
	 *
	 * @return array{balance: array<string, mixed>|null, error: bool} Result.
	 */
	public function process_request(): array {
		return $this->page->process_request();
	}

	/**
	 * Render a balance result.
	 *
	 * @param array{balance: array<string, mixed>|null, error: bool} $result Result.
	 */
	public function render_result( array $result ): void {
		$this->page->render_result( $result );
	}
}
