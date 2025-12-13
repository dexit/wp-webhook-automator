<?php
/**
 * Logs Viewer Page
 *
 * Displays webhook delivery logs.
 *
 * @package WP_Webhook_Automator
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET parameters used for read-only filtering.

namespace WWA\Admin;

use WWA\Core\Logger;
use WWA\Core\WebhookRepository;

class LogsViewer {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Webhook repository.
	 *
	 * @var WebhookRepository
	 */
	private WebhookRepository $repository;

	/**
	 * Items per page.
	 */
	private const PER_PAGE = 50;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger     = new Logger();
		$this->repository = new WebhookRepository();
	}

	/**
	 * Render the logs viewer.
	 *
	 * @return void
	 */
	public function render(): void {
		$currentPage = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$offset      = ( $currentPage - 1 ) * self::PER_PAGE;

		$criteria   = $this->getFilterCriteria();
		$logs       = $this->logger->getLogs( $criteria, self::PER_PAGE, $offset );
		$totalItems = $this->logger->count( $criteria );
		$totalPages = ceil( $totalItems / self::PER_PAGE );

		$webhooks = $this->repository->getForSelect();
		$stats    = $this->logger->getStats();
		?>
		<div class="wrap wwa-wrap">
			<div class="wwa-header">
				<h1><?php esc_html_e( 'Webhook Logs', 'wp-webhook-automator' ); ?></h1>
				<div class="wwa-header-actions">
					<?php if ( $totalItems > 0 ) : ?>
						<form method="post" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to clear all logs? This action cannot be undone.', 'wp-webhook-automator' ); ?>');">
							<?php wp_nonce_field( 'wwa_admin', 'wwa_nonce' ); ?>
							<input type="hidden" name="wwa_action" value="clear_logs">
							<button type="submit" class="button" style="color: #d63638;">
								<?php esc_html_e( 'Clear All Logs', 'wp-webhook-automator' ); ?>
							</button>
						</form>
					<?php endif; ?>
				</div>
			</div>

			<!-- Stats -->
			<div class="wwa-stats-grid" style="margin-bottom: 20px;">
				<div class="wwa-stat-card">
					<div class="wwa-stat-value"><?php echo esc_html( $stats['total'] ); ?></div>
					<div class="wwa-stat-label"><?php esc_html_e( 'Total Logs', 'wp-webhook-automator' ); ?></div>
				</div>
				<div class="wwa-stat-card">
					<div class="wwa-stat-value"><?php echo esc_html( $stats['today'] ); ?></div>
					<div class="wwa-stat-label"><?php esc_html_e( 'Today', 'wp-webhook-automator' ); ?></div>
				</div>
				<div class="wwa-stat-card success">
					<div class="wwa-stat-value"><?php echo esc_html( $stats['success_today'] ); ?></div>
					<div class="wwa-stat-label"><?php esc_html_e( 'Successful Today', 'wp-webhook-automator' ); ?></div>
				</div>
				<div class="wwa-stat-card error">
					<div class="wwa-stat-value"><?php echo esc_html( $stats['failed_today'] ); ?></div>
					<div class="wwa-stat-label"><?php esc_html_e( 'Failed Today', 'wp-webhook-automator' ); ?></div>
				</div>
			</div>

			<!-- Filters -->
			<div class="wwa-card" style="margin-bottom: 20px;">
				<form method="get" action="">
					<input type="hidden" name="page" value="wwa-logs">
					<div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
						<div>
							<label for="filter_webhook" class="screen-reader-text"><?php esc_html_e( 'Filter by webhook', 'wp-webhook-automator' ); ?></label>
							<select name="webhook_id" id="filter_webhook">
								<option value=""><?php esc_html_e( 'All Webhooks', 'wp-webhook-automator' ); ?></option>
								<?php foreach ( $webhooks as $id => $name ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>" <?php selected( isset( $_GET['webhook_id'] ) && (int) $_GET['webhook_id'] === (int) $id ); ?>>
										<?php echo esc_html( $name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div>
							<label for="filter_status" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'wp-webhook-automator' ); ?></label>
							<select name="status" id="filter_status">
								<option value=""><?php esc_html_e( 'All Statuses', 'wp-webhook-automator' ); ?></option>
								<option value="success" <?php selected( isset( $_GET['status'] ) && $_GET['status'] === 'success' ); ?>><?php esc_html_e( 'Success', 'wp-webhook-automator' ); ?></option>
								<option value="failed" <?php selected( isset( $_GET['status'] ) && $_GET['status'] === 'failed' ); ?>><?php esc_html_e( 'Failed', 'wp-webhook-automator' ); ?></option>
								<option value="pending" <?php selected( isset( $_GET['status'] ) && $_GET['status'] === 'pending' ); ?>><?php esc_html_e( 'Pending', 'wp-webhook-automator' ); ?></option>
							</select>
						</div>
						<div>
							<label for="filter_date_from" class="screen-reader-text"><?php esc_html_e( 'From date', 'wp-webhook-automator' ); ?></label>
							<input type="date" name="date_from" id="filter_date_from" value="<?php echo isset( $_GET['date_from'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) ) : ''; ?>" placeholder="<?php esc_attr_e( 'From', 'wp-webhook-automator' ); ?>">
						</div>
						<div>
							<label for="filter_date_to" class="screen-reader-text"><?php esc_html_e( 'To date', 'wp-webhook-automator' ); ?></label>
							<input type="date" name="date_to" id="filter_date_to" value="<?php echo isset( $_GET['date_to'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) ) : ''; ?>" placeholder="<?php esc_attr_e( 'To', 'wp-webhook-automator' ); ?>">
						</div>
						<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-webhook-automator' ); ?></button>
						<?php if ( ! empty( $_GET['webhook_id'] ) || ! empty( $_GET['status'] ) || ! empty( $_GET['date_from'] ) || ! empty( $_GET['date_to'] ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wwa-logs' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'wp-webhook-automator' ); ?></a>
						<?php endif; ?>
					</div>
				</form>
			</div>

			<!-- Logs Table -->
			<div class="wwa-card">
				<?php if ( empty( $logs ) ) : ?>
					<div class="wwa-card-body">
						<p class="wwa-text-muted"><?php esc_html_e( 'No logs found.', 'wp-webhook-automator' ); ?></p>
					</div>
				<?php else : ?>
					<table class="wwa-table">
						<thead>
							<tr>
								<th style="width: 50px;"><?php esc_html_e( 'ID', 'wp-webhook-automator' ); ?></th>
								<th><?php esc_html_e( 'Webhook', 'wp-webhook-automator' ); ?></th>
								<th><?php esc_html_e( 'Trigger', 'wp-webhook-automator' ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Status', 'wp-webhook-automator' ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Response', 'wp-webhook-automator' ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Duration', 'wp-webhook-automator' ); ?></th>
								<th style="width: 60px;"><?php esc_html_e( 'Attempt', 'wp-webhook-automator' ); ?></th>
								<th><?php esc_html_e( 'Time', 'wp-webhook-automator' ); ?></th>
								<th style="width: 100px;"><?php esc_html_e( 'Actions', 'wp-webhook-automator' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $log ) : ?>
								<tr>
									<td><small>#<?php echo esc_html( $log['id'] ); ?></small></td>
									<td>
										<strong><?php echo esc_html( $log['webhook_name'] ?: __( 'Deleted', 'wp-webhook-automator' ) ); ?></strong>
									</td>
									<td><code style="font-size: 11px;"><?php echo esc_html( $log['trigger_type'] ); ?></code></td>
									<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wwa_get_status_badge returns escaped HTML. ?>
									<td><?php echo wwa_get_status_badge( $log['status'] ); ?></td>
									<td>
										<?php if ( $log['response_code'] ) : ?>
											<code><?php echo esc_html( $log['response_code'] ); ?></code>
										<?php else : ?>
											<span class="wwa-text-muted">-</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $log['duration_ms'] ) : ?>
											<small><?php echo esc_html( wwa_format_duration( (int) $log['duration_ms'] ) ); ?></small>
										<?php else : ?>
											<span class="wwa-text-muted">-</span>
										<?php endif; ?>
									</td>
									<td><small>#<?php echo esc_html( $log['attempt_number'] ); ?></small></td>
									<td><small><?php echo esc_html( wwa_format_datetime( $log['created_at'] ) ); ?></small></td>
									<td>
										<button type="button" class="button button-small wwa-view-log" data-id="<?php echo esc_attr( $log['id'] ); ?>">
											<?php esc_html_e( 'Details', 'wp-webhook-automator' ); ?>
										</button>
										<?php if ( $log['status'] === 'failed' ) : ?>
											<button type="button" class="button button-small wwa-retry-webhook" data-id="<?php echo esc_attr( $log['id'] ); ?>">
												<?php esc_html_e( 'Retry', 'wp-webhook-automator' ); ?>
											</button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<!-- Pagination -->
					<?php if ( $totalPages > 1 ) : ?>
						<div style="padding: 15px; border-top: 1px solid #ddd;">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links returns safe HTML.
							echo paginate_links(
								[
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
									'total'     => $totalPages,
									'current'   => $currentPage,
								]
							);
							?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get filter criteria from query parameters.
	 *
	 * @return array
	 */
	private function getFilterCriteria(): array {
		$criteria = [];

		if ( ! empty( $_GET['webhook_id'] ) ) {
			$criteria['webhook_id'] = (int) $_GET['webhook_id'];
		}

		if ( ! empty( $_GET['status'] ) ) {
			$criteria['status'] = sanitize_text_field( wp_unslash( $_GET['status'] ) );
		}

		if ( ! empty( $_GET['date_from'] ) ) {
			$criteria['date_from'] = sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) . ' 00:00:00';
		}

		if ( ! empty( $_GET['date_to'] ) ) {
			$criteria['date_to'] = sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) . ' 23:59:59';
		}

		return $criteria;
	}
}
