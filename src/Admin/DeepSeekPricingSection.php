<?php
/**
 * DeepSeek pricing rule administration section.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider\Admin;

use Guducat\DeepSeekAiProvider\Pricing\DeepSeekPricingCatalog;
use Guducat\DeepSeekAiProvider\Pricing\DeepSeekPricingSettings;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/** Handles the small administrator-defined pricing-rule workflow. */
final class DeepSeekPricingSection {
	public const ACTION_FIELD = 'deepseek_pricing_action';

	public const NONCE_ACTION = 'deepseek_pricing_rules';

	/**
	 * Process a submitted save or disable action.
	 *
	 * @return array{message:string,error:bool}
	 */
	public function process_request(): array {
		$action = $this->posted_string( self::ACTION_FIELD );
		if ( '' === $action ) {
			return array(
				'message' => '',
				'error'   => false,
			);
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'message' => __( 'Permission denied.', 'ai-connector-for-deepseek-guducat-ver' ),
				'error'   => true,
			);
		}

		check_admin_referer( self::NONCE_ACTION );
		$rules = DeepSeekPricingSettings::get();
		if ( 'disable' === $action ) {
			$id = $this->posted_string( 'deepseek_pricing_id' );
			foreach ( $rules as &$rule ) {
				if ( $id === $rule['id'] ) {
					$rule['active'] = false;
				}
			}
			unset( $rule );
			DeepSeekPricingSettings::save( $rules );
			return array(
				'message' => __( 'Custom pricing rule disabled.', 'ai-connector-for-deepseek-guducat-ver' ),
				'error'   => false,
			);
		}

		if ( 'save' !== $action ) {
			return array(
				'message' => __( 'Unknown pricing action.', 'ai-connector-for-deepseek-guducat-ver' ),
				'error'   => true,
			);
		}

		$rule       = $this->posted_rule();
		$normalized = DeepSeekPricingSettings::sanitize( array( $rule ) );
		if ( array() === $normalized ) {
			return array(
				'message' => __( 'The pricing rule is invalid.', 'ai-connector-for-deepseek-guducat-ver' ),
				'error'   => true,
			);
		}

		$replacement = $normalized[0];
		$rules       = array_values(
			array_filter(
				$rules,
				static function ( array $existing ) use ( $replacement ): bool {
					return $existing['id'] !== $replacement['id'];
				}
			)
		);
		$rules[]     = $replacement;
		DeepSeekPricingSettings::save( $rules );

		return array(
			'message' => __( 'Custom pricing rule saved.', 'ai-connector-for-deepseek-guducat-ver' ),
			'error'   => false,
		);
	}

	/**
	 * Render built-in and custom prices plus the small add/edit form.
	 *
	 * @param array{message:string,error:bool} $result Request result.
	 */
	public function render( array $result ): void {
		if ( '' !== $result['message'] ) {
			$class = $result['error'] ? 'notice notice-error inline' : 'notice notice-success inline';
			printf( '<div class="%s"><p>%s</p></div>', esc_attr( $class ), esc_html( $result['message'] ) );
		}
		?>
		<h2><?php echo esc_html__( 'Pricing rules', 'ai-connector-for-deepseek-guducat-ver' ); ?></h2>
		<p><?php echo esc_html__( 'Estimated costs use the rule effective when the request is logged. Custom rules override built-in rules with the same effective time.', 'ai-connector-for-deepseek-guducat-ver' ); ?></p>
		<table class="widefat striped deepseek-connector-admin__pricing-table">
			<thead><tr><th><?php echo esc_html__( 'Effective (UTC)', 'ai-connector-for-deepseek-guducat-ver' ); ?></th><th><?php echo esc_html__( 'Model', 'ai-connector-for-deepseek-guducat-ver' ); ?></th><th><?php echo esc_html__( 'Rates per 1M tokens', 'ai-connector-for-deepseek-guducat-ver' ); ?></th><th><?php echo esc_html__( 'Source', 'ai-connector-for-deepseek-guducat-ver' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( DeepSeekPricingCatalog::all() as $rule ) : ?>
				<tr>
					<td><?php echo esc_html( $rule['effective_from_utc'] ); ?></td>
					<td><strong><?php echo esc_html( $rule['label'] ); ?></strong><br><code><?php echo esc_html( implode( ', ', $rule['model_ids'] ) ); ?></code></td>
					<td><?php echo esc_html( $this->format_rates( $rule ) ); ?></td>
					<td><?php echo esc_html( 'custom' === $rule['source'] ? __( 'Custom', 'ai-connector-for-deepseek-guducat-ver' ) : __( 'Built-in', 'ai-connector-for-deepseek-guducat-ver' ) ); ?>
					<?php
					if ( ! $rule['active'] ) :
						?>
						- <?php echo esc_html__( 'Disabled', 'ai-connector-for-deepseek-guducat-ver' ); ?><?php endif; ?>
					<?php if ( 'custom' === $rule['source'] && $rule['active'] ) : ?>
						<form method="post" class="deepseek-connector-admin__inline-form"><?php wp_nonce_field( self::NONCE_ACTION ); ?><input type="hidden" name="<?php echo esc_attr( self::ACTION_FIELD ); ?>" value="disable"><input type="hidden" name="deepseek_pricing_id" value="<?php echo esc_attr( $rule['id'] ); ?>"><?php submit_button( __( 'Disable', 'ai-connector-for-deepseek-guducat-ver' ), 'small', 'submit', false ); ?></form>
					<?php endif; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<details class="deepseek-connector-admin__pricing-details">
			<summary><?php echo esc_html__( 'Add or replace a custom pricing rule', 'ai-connector-for-deepseek-guducat-ver' ); ?></summary>
			<div class="deepseek-connector-admin__pricing-details-content">
			<p class="description"><?php echo esc_html__( 'Use exact API model IDs separated by commas. Effective times and peak windows use UTC.', 'ai-connector-for-deepseek-guducat-ver' ); ?></p>
		<form method="post" class="deepseek-connector-admin__pricing-form">
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::ACTION_FIELD ); ?>" value="save">
			<p><label><?php echo esc_html__( 'Rule ID', 'ai-connector-for-deepseek-guducat-ver' ); ?><br><input required class="regular-text" name="deepseek_pricing_id" type="text"></label></p>
			<p><label><?php echo esc_html__( 'Label', 'ai-connector-for-deepseek-guducat-ver' ); ?><br><input required class="regular-text" name="deepseek_pricing_label" type="text"></label></p>
			<p><label><?php echo esc_html__( 'Model IDs', 'ai-connector-for-deepseek-guducat-ver' ); ?><br><input required class="large-text" name="deepseek_pricing_models" type="text"></label></p>
			<p><label><?php echo esc_html__( 'Effective from (UTC)', 'ai-connector-for-deepseek-guducat-ver' ); ?><br><input required name="deepseek_pricing_effective" type="text" placeholder="2026-09-15T00:00:00Z"></label></p>
			<p><label><?php echo esc_html__( 'Currency', 'ai-connector-for-deepseek-guducat-ver' ); ?><br><input required name="deepseek_pricing_currency" type="text" value="CNY" maxlength="3"></label></p>
			<p><label><?php echo esc_html__( 'Pricing mode', 'ai-connector-for-deepseek-guducat-ver' ); ?><br><select name="deepseek_pricing_mode"><option value="flat"><?php echo esc_html__( 'Flat rate', 'ai-connector-for-deepseek-guducat-ver' ); ?></option><option value="tiered"><?php echo esc_html__( 'Off-peak and peak rates', 'ai-connector-for-deepseek-guducat-ver' ); ?></option></select></label></p>
			<h4><?php echo esc_html__( 'Flat rate', 'ai-connector-for-deepseek-guducat-ver' ); ?></h4>
			<div class="deepseek-connector-admin__rate-fields">
				<label><?php echo esc_html__( 'Cache hit input', 'ai-connector-for-deepseek-guducat-ver' ); ?><input name="deepseek_pricing_flat_hit" type="number" min="0" step="0.000000001"></label>
				<label><?php echo esc_html__( 'Cache miss input', 'ai-connector-for-deepseek-guducat-ver' ); ?><input name="deepseek_pricing_flat_miss" type="number" min="0" step="0.000000001"></label>
				<label><?php echo esc_html__( 'Output', 'ai-connector-for-deepseek-guducat-ver' ); ?><input name="deepseek_pricing_flat_output" type="number" min="0" step="0.000000001"></label>
			</div>
			<h4><?php echo esc_html__( 'Off-peak rates', 'ai-connector-for-deepseek-guducat-ver' ); ?></h4>
			<div class="deepseek-connector-admin__rate-fields">
				<label><?php echo esc_html__( 'Cache hit input', 'ai-connector-for-deepseek-guducat-ver' ); ?><input name="deepseek_pricing_off_peak_hit" type="number" min="0" step="0.000000001"></label>
				<label><?php echo esc_html__( 'Cache miss input', 'ai-connector-for-deepseek-guducat-ver' ); ?><input name="deepseek_pricing_off_peak_miss" type="number" min="0" step="0.000000001"></label>
				<label><?php echo esc_html__( 'Output', 'ai-connector-for-deepseek-guducat-ver' ); ?><input name="deepseek_pricing_off_peak_output" type="number" min="0" step="0.000000001"></label>
			</div>
			<h4><?php echo esc_html__( 'Peak rates', 'ai-connector-for-deepseek-guducat-ver' ); ?></h4>
			<div class="deepseek-connector-admin__rate-fields">
				<label><?php echo esc_html__( 'Cache hit input', 'ai-connector-for-deepseek-guducat-ver' ); ?><input name="deepseek_pricing_peak_hit" type="number" min="0" step="0.000000001"></label>
				<label><?php echo esc_html__( 'Cache miss input', 'ai-connector-for-deepseek-guducat-ver' ); ?><input name="deepseek_pricing_peak_miss" type="number" min="0" step="0.000000001"></label>
				<label><?php echo esc_html__( 'Output', 'ai-connector-for-deepseek-guducat-ver' ); ?><input name="deepseek_pricing_peak_output" type="number" min="0" step="0.000000001"></label>
			</div>
			<p><label><?php echo esc_html__( 'Peak weekdays', 'ai-connector-for-deepseek-guducat-ver' ); ?><br><input class="regular-text" name="deepseek_pricing_weekdays" type="text" value="1,2,3,4,5"><br><span class="description"><?php echo esc_html__( 'ISO weekday numbers, Monday is 1.', 'ai-connector-for-deepseek-guducat-ver' ); ?></span></label></p>
			<p><label><?php echo esc_html__( 'Peak windows (UTC)', 'ai-connector-for-deepseek-guducat-ver' ); ?><br><input class="regular-text" name="deepseek_pricing_windows" type="text" value="01:00-04:00,06:00-10:00"></label></p>
			<?php submit_button( __( 'Save custom pricing rule', 'ai-connector-for-deepseek-guducat-ver' ), 'secondary', 'submit', false ); ?>
		</form>
			</div>
		</details>
		<?php
	}

	/**
	 * Build a custom rule from the submitted flat-rate form.
	 *
	 * @return array<string, mixed>
	 */
	private function posted_rule(): array {
		$models = array_filter( array_map( 'trim', explode( ',', $this->posted_string( 'deepseek_pricing_models' ) ) ) );
		$rule   = array(
			'id'                 => $this->posted_string( 'deepseek_pricing_id' ),
			'label'              => $this->posted_string( 'deepseek_pricing_label' ),
			'model_ids'          => array_values( $models ),
			'effective_from_utc' => $this->posted_string( 'deepseek_pricing_effective' ),
			'currency'           => $this->posted_string( 'deepseek_pricing_currency' ),
			'rates'              => array(
				'flat' => array(
					'cache_hit'  => $this->posted_string( 'deepseek_pricing_flat_hit' ),
					'cache_miss' => $this->posted_string( 'deepseek_pricing_flat_miss' ),
					'output'     => $this->posted_string( 'deepseek_pricing_flat_output' ),
				),
			),
		);
		if ( 'tiered' === $this->posted_string( 'deepseek_pricing_mode' ) ) {
			$rule['rates']         = array(
				'off_peak' => array(
					'cache_hit'  => $this->posted_string( 'deepseek_pricing_off_peak_hit' ),
					'cache_miss' => $this->posted_string( 'deepseek_pricing_off_peak_miss' ),
					'output'     => $this->posted_string( 'deepseek_pricing_off_peak_output' ),
				),
				'peak'     => array(
					'cache_hit'  => $this->posted_string( 'deepseek_pricing_peak_hit' ),
					'cache_miss' => $this->posted_string( 'deepseek_pricing_peak_miss' ),
					'output'     => $this->posted_string( 'deepseek_pricing_peak_output' ),
				),
			);
			$rule['peak_schedule'] = array(
				'timezone' => 'UTC',
				'weekdays' => array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $this->posted_string( 'deepseek_pricing_weekdays' ) ) ) ) ),
				'windows'  => $this->posted_windows(),
			);
		}
		return $rule;
	}

	/**
	 * Parse comma-separated HH:MM-HH:MM peak windows.
	 *
	 * @return list<array{0:string,1:string}>
	 */
	private function posted_windows(): array {
		$windows = array();
		foreach ( explode( ',', $this->posted_string( 'deepseek_pricing_windows' ) ) as $value ) {
			$parts = array_map( 'trim', explode( '-', $value, 2 ) );
			if ( 2 === count( $parts ) ) {
				$windows[] = array( $parts[0], $parts[1] );
			}
		}
		return $windows;
	}

	/**
	 * Read one unslashed scalar POST value.
	 *
	 * @param string $key POST key.
	 * @return string
	 */
	private function posted_string( string $key ): string {
		// Nonce verification is performed once in process_request() before submitted values are used.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			return '';
		}
		$value = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by DeepSeekPricingSettings for its destination field.
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return trim( $value );
	}

	/**
	 * Format one rule's compact rates for the table.
	 *
	 * @param array<string, mixed> $rule Pricing rule.
	 * @return string
	 */
	private function format_rates( array $rule ): string {
		$sets = array();
		foreach ( $rule['rates'] as $tier => $rates ) {
			$sets[] = sprintf( '%s: %s %s / %s / %s', $tier, $rule['currency'], $rates['cache_hit'], $rates['cache_miss'], $rates['output'] );
		}
		return implode( '; ', $sets );
	}
}
