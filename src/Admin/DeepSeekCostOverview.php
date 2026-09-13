<?php
/**
 * DeepSeek request cost overview.
 *
 * @package Guducat\DeepSeekAiProvider
 */

declare(strict_types=1);

namespace Guducat\DeepSeekAiProvider\Admin;

use WordPress\AI\Logging\AI_Request_Log_Schema;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Aggregates DeepSeek Chat Completions metadata from WordPress AI logs.
 */
final class DeepSeekCostOverview {
	/** Cache duration for an overview snapshot. */
	private const CACHE_TTL = 60;
	/**
	 * Supported overview periods.
	 *
	 * @var list<string>
	 */
	public const PERIODS = array( 'day', 'week', 'month', 'all' );

	/**
	 * Optional row loader used by tests and integrations.
	 *
	 * @var callable(string): (list<array<string, mixed>>|null)|null
	 */
	private $row_loader;

	/**
	 * Create the overview service.
	 *
	 * @param (callable(string): (list<array<string, mixed>>|null))|null $row_loader Optional aggregate-row loader.
	 */
	public function __construct( ?callable $row_loader = null ) {
		$this->row_loader = $row_loader;
	}

	/**
	 * Get a normalized overview period.
	 *
	 * @param string $period Requested period.
	 * @return string
	 */
	public function normalize_period( string $period ): string {
		return in_array( $period, self::PERIODS, true ) ? $period : 'day';
	}

	/**
	 * Aggregate DeepSeek Chat Completions logs.
	 *
	 * @param string $period Requested period.
	 * @return array<string, mixed>
	 */
	public function get_summary( string $period = 'day' ): array {
		$period    = $this->normalize_period( $period );
		$cache_key = 'deepseek_cost_overview_' . $period;
		if ( null === $this->row_loader && function_exists( 'get_transient' ) ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$rows = null !== $this->row_loader
			? call_user_func( $this->row_loader, $period )
			: $this->query_rows( $period );

		if ( null === $rows ) {
			$summary = $this->empty_summary( $period, false );
		} else {
			$summary = $this->aggregate_rows( $period, $rows );
		}

		if ( null === $this->row_loader && function_exists( 'set_transient' ) ) {
			set_transient( $cache_key, $summary, self::CACHE_TTL );
		}

		return $summary;
	}

	/** Clear cached summaries after a new WordPress AI log is written. */
	public function clear_cache(): void {
		if ( ! function_exists( 'delete_transient' ) ) {
			return;
		}
		foreach ( self::PERIODS as $period ) {
			delete_transient( 'deepseek_cost_overview_' . $period );
		}
	}

	/**
	 * Render the overview section.
	 *
	 * @param array<string, mixed> $summary Summary data.
	 * @param string               $logs_url Existing AI logs URL.
	 * @return void
	 */
	public function render( array $summary, string $logs_url ): void {
		$period = (string) ( $summary['period'] ?? 'day' );
		?>
		<div class="deepseek-connector-admin__overview-header">
			<div>
				<h2 id="deepseek-cost-overview-heading"><?php echo esc_html__( 'DeepSeek usage overview', 'ai-connector-for-deepseek-guducat-ver' ); ?></h2>
				<p><?php echo esc_html__( 'Estimated usage is calculated from WordPress AI request logs and saved pricing snapshots.', 'ai-connector-for-deepseek-guducat-ver' ); ?></p>
			</div>
			<form method="get" class="deepseek-connector-admin__period-form">
				<input type="hidden" name="page" value="deepseek-connector">
				<label for="deepseek-cost-period"><?php echo esc_html__( 'Period', 'ai-connector-for-deepseek-guducat-ver' ); ?></label>
				<select id="deepseek-cost-period" name="deepseek_cost_period">
					<?php foreach ( self::PERIODS as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $period, $option ); ?>><?php echo esc_html( $this->period_label( $option ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Refresh', 'ai-connector-for-deepseek-guducat-ver' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php if ( empty( $summary['available'] ) ) : ?>
			<div class="notice notice-warning inline">
				<p><?php echo esc_html__( 'AI Request Logging is not available. Enable it to collect DeepSeek usage data.', 'ai-connector-for-deepseek-guducat-ver' ); ?></p>
			</div>
			<?php return; ?>
		<?php endif; ?>
		<div class="deepseek-connector-admin__metric-grid" aria-label="<?php echo esc_attr__( 'DeepSeek usage metrics', 'ai-connector-for-deepseek-guducat-ver' ); ?>">
			<?php $this->render_metric( __( 'Estimated cost', 'ai-connector-for-deepseek-guducat-ver' ), $this->format_costs( $summary['estimated_costs'] ?? array() ), __( 'Priced requests only', 'ai-connector-for-deepseek-guducat-ver' ) ); ?>
			<?php $this->render_metric( __( 'Requests', 'ai-connector-for-deepseek-guducat-ver' ), number_format_i18n( (int) ( $summary['total_requests'] ?? 0 ) ), sprintf( '%s %s', number_format_i18n( (int) ( $summary['priced_requests'] ?? 0 ) ), esc_html__( 'priced', 'ai-connector-for-deepseek-guducat-ver' ) ) ); ?>
			<?php $this->render_metric( __( 'Total tokens', 'ai-connector-for-deepseek-guducat-ver' ), number_format_i18n( (int) ( $summary['total_tokens'] ?? 0 ) ), sprintf( '%s %s', number_format_i18n( (int) ( $summary['cache_hit_tokens'] ?? 0 ) ), esc_html__( 'cache hit', 'ai-connector-for-deepseek-guducat-ver' ) ) ); ?>
			<?php $this->render_metric( __( 'Pricing coverage', 'ai-connector-for-deepseek-guducat-ver' ), $this->format_percentage( (float) ( $summary['pricing_coverage'] ?? 0 ) ), sprintf( '%s %s', number_format_i18n( (int) ( $summary['unpriced_requests'] ?? 0 ) ), esc_html__( 'without a snapshot', 'ai-connector-for-deepseek-guducat-ver' ) ) ); ?>
		</div>
		<?php if ( ! empty( $summary['unpriced_requests'] ) ) : ?>
			<div class="notice notice-warning inline deepseek-connector-admin__coverage-notice">
				<p><?php echo esc_html__( 'Some historical requests do not contain a DeepSeek pricing snapshot. They are excluded from estimated cost totals.', 'ai-connector-for-deepseek-guducat-ver' ); ?></p>
			</div>
		<?php endif; ?>
		<div class="deepseek-connector-admin__overview-actions">
			<a class="button button-primary" href="<?php echo esc_url( $logs_url ); ?>"><?php echo esc_html__( 'View AI Request Logs', 'ai-connector-for-deepseek-guducat-ver' ); ?></a>
			<span class="description"><?php echo esc_html__( 'Costs are estimates and may differ from the provider invoice.', 'ai-connector-for-deepseek-guducat-ver' ); ?></span>
		</div>
		<h3><?php echo esc_html__( 'By model', 'ai-connector-for-deepseek-guducat-ver' ); ?></h3>
		<table class="widefat striped deepseek-connector-admin__overview-table">
			<thead><tr><th><?php echo esc_html__( 'Model', 'ai-connector-for-deepseek-guducat-ver' ); ?></th><th><?php echo esc_html__( 'Requests', 'ai-connector-for-deepseek-guducat-ver' ); ?></th><th><?php echo esc_html__( 'Tokens', 'ai-connector-for-deepseek-guducat-ver' ); ?></th><th><?php echo esc_html__( 'Cache hit', 'ai-connector-for-deepseek-guducat-ver' ); ?></th><th><?php echo esc_html__( 'Estimated cost', 'ai-connector-for-deepseek-guducat-ver' ); ?></th></tr></thead>
			<tbody>
			<?php if ( empty( $summary['models'] ) ) : ?>
				<tr><td colspan="5"><?php echo esc_html__( 'No DeepSeek Chat Completions requests found for this period.', 'ai-connector-for-deepseek-guducat-ver' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $summary['models'] as $model ) : ?>
					<tr>
						<td><strong><?php echo esc_html( (string) $model['model'] ); ?></strong>
						<?php
						if ( ! empty( $model['unpriced_requests'] ) ) :
							?>
							<br><span class="description">
							<?php
							echo esc_html(
								sprintf(
								/* translators: %d: Number of requests without a saved pricing snapshot. */
									__( '%d without pricing snapshot', 'ai-connector-for-deepseek-guducat-ver' ),
									(int) $model['unpriced_requests']
								)
							);
							?>
							</span><?php endif; ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $model['requests'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $model['tokens'] ) ); ?></td>
						<td><?php echo esc_html( $this->format_percentage( (float) $model['cache_hit_rate'] ) ); ?></td>
						<td><?php echo esc_html( $this->format_costs( $model['estimated_costs'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Aggregate normalized rows. This method is public for deterministic tests.
	 *
	 * @param string                     $period Period label.
	 * @param list<array<string, mixed>> $rows   Aggregate rows.
	 * @return array<string, mixed>
	 */
	public function aggregate_rows( string $period, array $rows ): array {
		$summary = $this->empty_summary( $period, true );

		foreach ( $rows as $row ) {
			$requests = max( 0, (int) ( $row['requests'] ?? 0 ) );
			$priced   = max( 0, min( $requests, (int) ( $row['priced_requests'] ?? 0 ) ) );
			$tokens   = max( 0, (int) ( $row['tokens'] ?? 0 ) );
			$hit      = max( 0, (int) ( $row['cache_hit_tokens'] ?? 0 ) );
			$miss     = max( 0, (int) ( $row['cache_miss_tokens'] ?? 0 ) );
			$output   = max( 0, (int) ( $row['output_tokens'] ?? 0 ) );
			$model    = is_string( $row['model'] ?? null ) && '' !== $row['model'] ? $row['model'] : __( 'Unknown model', 'ai-connector-for-deepseek-guducat-ver' );

			$summary['total_requests']    += $requests;
			$summary['priced_requests']   += $priced;
			$summary['unpriced_requests'] += $requests - $priced;
			$summary['total_tokens']      += $tokens;
			$summary['cache_hit_tokens']  += $hit;
			$summary['cache_miss_tokens'] += $miss;
			$summary['output_tokens']     += $output;
			$summary['total_duration_ms'] += max( 0, (int) ( $row['duration_ms'] ?? 0 ) ) * $requests;
			$summary['success_requests']  += max( 0, min( $requests, (int) ( $row['success_requests'] ?? 0 ) ) );
			$this->merge_costs( $summary['estimated_costs'], $row['currency'] ?? '', $row['estimated_cost'] ?? '0' );

			$model_key = (string) $model;
			if ( ! isset( $summary['models'][ $model_key ] ) ) {
				$summary['models'][ $model_key ] = array(
					'model'             => $model,
					'requests'          => 0,
					'priced_requests'   => 0,
					'unpriced_requests' => 0,
					'tokens'            => 0,
					'cache_hit_tokens'  => 0,
					'cache_miss_tokens' => 0,
					'estimated_costs'   => array(),
				);
			}
			$model_summary                       = &$summary['models'][ $model_key ];
			$model_summary['requests']          += $requests;
			$model_summary['priced_requests']   += $priced;
			$model_summary['unpriced_requests'] += $requests - $priced;
			$model_summary['tokens']            += $tokens;
			$model_summary['cache_hit_tokens']  += $hit;
			$model_summary['cache_miss_tokens'] += $miss;
			$this->merge_costs( $model_summary['estimated_costs'], $row['currency'] ?? '', $row['estimated_cost'] ?? '0' );
			unset( $model_summary );
		}

		$summary['pricing_coverage'] = $summary['total_requests'] > 0 ? ( $summary['priced_requests'] / $summary['total_requests'] ) * 100 : 0.0;
		$summary['success_rate']     = $summary['total_requests'] > 0 ? ( $summary['success_requests'] / $summary['total_requests'] ) * 100 : 0.0;
		$summary['avg_duration_ms']  = $summary['total_requests'] > 0 ? $summary['total_duration_ms'] / $summary['total_requests'] : 0.0;

		foreach ( $summary['models'] as &$model_summary ) {
			$input_tokens                    = $model_summary['cache_hit_tokens'] + $model_summary['cache_miss_tokens'];
			$model_summary['cache_hit_rate'] = $input_tokens > 0 ? ( $model_summary['cache_hit_tokens'] / $input_tokens ) * 100 : 0.0;
		}
		unset( $model_summary );
		$summary['models'] = array_values( $summary['models'] );

		return $summary;
	}

	// This service reads the dedicated WordPress AI log table for aggregation.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	/**
	 * Query grouped rows from the WordPress AI log table.
	 *
	 * @param string $period Period label.
	 * @return list<array<string, mixed>>|null
	 */
	private function query_rows( string $period ): ?array {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return null;
		}

		$table  = $this->get_log_table_name( $wpdb->prefix );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return null;
		}

		$date_condition = $this->date_condition( $period );
		$sql            = "SELECT model, tokens_total, duration_ms, status, context
			FROM {$table}
			WHERE provider = 'deepseek' AND operation = 'deepseek:completions' {$date_condition}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$raw_rows = $wpdb->get_results( $sql, 'ARRAY_A' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $raw_rows ) ) {
			return array();
		}

		$groups = array();
		foreach ( $raw_rows as $raw_row ) {
			$context  = isset( $raw_row['context'] ) && is_string( $raw_row['context'] )
				? json_decode( $raw_row['context'], true )
				: array();
			$deepseek = is_array( $context ) && isset( $context['deepseek'] ) && is_array( $context['deepseek'] )
				? $context['deepseek']
				: array();
			$currency = is_string( $deepseek['currency'] ?? null ) ? strtoupper( $deepseek['currency'] ) : '';
			$has_cost = '' !== $currency && array_key_exists( 'estimated_cost', $deepseek ) && is_numeric( $deepseek['estimated_cost'] );
			$model    = is_string( $raw_row['model'] ?? null ) ? $raw_row['model'] : '';
			$key      = $model . "\0" . $currency;

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'model'             => $model,
					'requests'          => 0,
					'priced_requests'   => 0,
					'tokens'            => 0,
					'cache_hit_tokens'  => 0,
					'cache_miss_tokens' => 0,
					'output_tokens'     => 0,
					'duration_ms'       => 0,
					'success_requests'  => 0,
					'currency'          => $currency,
					'estimated_cost'    => '0',
				);
			}

			++$groups[ $key ]['requests'];
			$groups[ $key ]['priced_requests']   += $has_cost ? 1 : 0;
			$groups[ $key ]['tokens']            += max( 0, (int) ( $raw_row['tokens_total'] ?? 0 ) );
			$groups[ $key ]['cache_hit_tokens']  += max( 0, (int) ( $deepseek['cache_hit_tokens'] ?? 0 ) );
			$groups[ $key ]['cache_miss_tokens'] += max( 0, (int) ( $deepseek['cache_miss_tokens'] ?? 0 ) );
			$groups[ $key ]['output_tokens']     += max( 0, (int) ( $deepseek['output_tokens'] ?? 0 ) );
			$groups[ $key ]['duration_ms']       += max( 0, (int) ( $raw_row['duration_ms'] ?? 0 ) );
			$groups[ $key ]['success_requests']  += 'success' === ( $raw_row['status'] ?? '' ) ? 1 : 0;
			if ( $has_cost ) {
				$groups[ $key ]['estimated_cost'] = number_format(
					(float) $groups[ $key ]['estimated_cost'] + (float) $deepseek['estimated_cost'],
					8,
					'.',
					''
				);
			}
		}

		foreach ( $groups as &$group ) {
			$group['duration_ms'] = $group['duration_ms'] / $group['requests'];
		}
		unset( $group );

		return array_values( $groups );
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	/**
	 * Resolve the WordPress AI log table name when its schema class is available.
	 *
	 * @param string $prefix WordPress database prefix.
	 * @return string
	 */
	private function get_log_table_name( string $prefix ): string {
		if ( class_exists( AI_Request_Log_Schema::class ) ) {
			$schema = new AI_Request_Log_Schema();
			if ( is_callable( array( $schema, 'get_table_name' ) ) ) {
				return (string) $schema->get_table_name();
			}
		}

		return $prefix . 'wpai_request_logs';
	}

	/**
	 * Return an empty summary.
	 *
	 * @param string $period    Period label.
	 * @param bool   $available Whether the log source is available.
	 * @return array<string, mixed>
	 */
	private function empty_summary( string $period, bool $available ): array {
		return array(
			'available'         => $available,
			'period'            => $period,
			'total_requests'    => 0,
			'priced_requests'   => 0,
			'unpriced_requests' => 0,
			'total_tokens'      => 0,
			'cache_hit_tokens'  => 0,
			'cache_miss_tokens' => 0,
			'output_tokens'     => 0,
			'estimated_costs'   => array(),
			'pricing_coverage'  => 0.0,
			'success_requests'  => 0,
			'success_rate'      => 0.0,
			'total_duration_ms' => 0,
			'avg_duration_ms'   => 0.0,
			'models'            => array(),
		);
	}

	/**
	 * Merge a decimal cost into a currency bucket.
	 *
	 * @param array<string, string> $costs    Currency buckets.
	 * @param mixed                 $currency Currency code.
	 * @param mixed                 $amount   Decimal amount.
	 */
	private function merge_costs( array &$costs, $currency, $amount ): void {
		if ( ! is_string( $currency ) || '' === $currency || ! is_numeric( $amount ) ) {
			return;
		}
		$key = strtoupper( $currency );
		if ( ! isset( $costs[ $key ] ) ) {
			$costs[ $key ] = '0.00000000';
		}
		$costs[ $key ] = number_format( (float) $costs[ $key ] + (float) $amount, 8, '.', '' );
	}

	/**
	 * Format currency buckets for display.
	 *
	 * @param mixed $costs Currency buckets.
	 * @return string
	 */
	private function format_costs( $costs ): string {
		if ( ! is_array( $costs ) || empty( $costs ) ) {
			return '-';
		}
		$formatted = array();
		foreach ( $costs as $currency => $amount ) {
			$formatted[] = sprintf( '%s %s', $currency, number_format_i18n( (float) $amount, 8 ) );
		}
		return implode( ', ', $formatted );
	}

	/**
	 * Render one metric tile.
	 *
	 * @param string $label   Metric label.
	 * @param string $value   Metric value.
	 * @param string $caption Supporting caption.
	 * @return void
	 */
	private function render_metric( string $label, string $value, string $caption ): void {
		?>
		<div class="deepseek-connector-admin__metric">
			<span class="deepseek-connector-admin__metric-label"><?php echo esc_html( $label ); ?></span>
			<strong class="deepseek-connector-admin__metric-value"><?php echo esc_html( $value ); ?></strong>
			<span class="deepseek-connector-admin__metric-caption"><?php echo esc_html( $caption ); ?></span>
		</div>
		<?php
	}

	/**
	 * Format a percentage without excessive precision.
	 *
	 * @param float $value Percentage value.
	 * @return string
	 */
	private function format_percentage( float $value ): string {
		return number_format_i18n( $value, 1 ) . '%';
	}

	/**
	 * Return a translated period label.
	 *
	 * @param string $period Period identifier.
	 * @return string
	 */
	private function period_label( string $period ): string {
		$labels = array(
			'day'   => __( 'Last 24 hours', 'ai-connector-for-deepseek-guducat-ver' ),
			'week'  => __( 'Last 7 days', 'ai-connector-for-deepseek-guducat-ver' ),
			'month' => __( 'Last 30 days', 'ai-connector-for-deepseek-guducat-ver' ),
			'all'   => __( 'All available logs', 'ai-connector-for-deepseek-guducat-ver' ),
		);
		return $labels[ $period ] ?? $labels['day'];
	}

	/**
	 * Return the UTC date filter for a period.
	 *
	 * @param string $period Period identifier.
	 * @return string
	 */
	private function date_condition( string $period ): string {
		$intervals = array(
			'day'   => '1 DAY',
			'week'  => '7 DAY',
			'month' => '30 DAY',
		);
		return isset( $intervals[ $period ] ) ? 'AND timestamp >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . $intervals[ $period ] . ')' : '';
	}
}
