<?php
/**
 * Dashboard Page
 *
 * Displays webhook statistics and recent activity.
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Admin;

use Hookly\Core\WebhookRepository;
use Hookly\Core\Logger;
use Hookly\Triggers\TriggerRegistry;

class Dashboard {

	/**
	 * Webhook repository.
	 *
	 * @var WebhookRepository
	 */
	private WebhookRepository $repository;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new WebhookRepository();
		$this->logger     = new Logger();
	}

	/**
	 * Render the dashboard.
	 *
	 * @return void
	 */
	public function render(): void {
		$stats           = $this->getStats();
		$recentLogs      = $this->logger->getRecentLogs( 10 );
		$triggerRegistry = TriggerRegistry::getInstance();
		?>
		<div class="wrap hookly-wrap">
			<div class="hookly-header">
				<h1><?php esc_html_e( 'Webhook Automator Dashboard', 'hookly-webhook-automator' ); ?></h1>
				<div class="hookly-header-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=hookly-webhook-new' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Add New Webhook', 'hookly-webhook-automator' ); ?>
					</a>
				</div>
			</div>

			<!-- Stats Grid -->
			<div class="hookly-stats-grid">
				<div class="hookly-stat-card">
					<div class="hookly-stat-value"><?php echo esc_html( $stats['total_webhooks'] ); ?></div>
					<div class="hookly-stat-label"><?php esc_html_e( 'Total Webhooks', 'hookly-webhook-automator' ); ?></div>
				</div>
				<div class="hookly-stat-card success">
					<div class="hookly-stat-value"><?php echo esc_html( $stats['active_webhooks'] ); ?></div>
					<div class="hookly-stat-label"><?php esc_html_e( 'Active Webhooks', 'hookly-webhook-automator' ); ?></div>
				</div>
				<div class="hookly-stat-card">
					<div class="hookly-stat-value"><?php echo esc_html( $stats['deliveries_today'] ); ?></div>
					<div class="hookly-stat-label"><?php esc_html_e( 'Deliveries Today', 'hookly-webhook-automator' ); ?></div>
				</div>
				<div class="hookly-stat-card success">
					<div class="hookly-stat-value"><?php echo esc_html( $stats['success_rate'] ); ?>%</div>
					<div class="hookly-stat-label"><?php esc_html_e( 'Success Rate (Today)', 'hookly-webhook-automator' ); ?></div>
				</div>
			</div>

			<div class="hookly-dashboard-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
				<!-- Recent Activity -->
				<div class="hookly-card">
					<div class="hookly-card-header">
						<h2><?php esc_html_e( 'Recent Activity', 'hookly-webhook-automator' ); ?></h2>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=hookly-logs' ) ); ?>">
							<?php esc_html_e( 'View All Logs', 'hookly-webhook-automator' ); ?> &rarr;
						</a>
					</div>
					<div class="hookly-card-body">
						<?php if ( empty( $recentLogs ) ) : ?>
							<p class="hookly-text-muted"><?php esc_html_e( 'No webhook activity yet.', 'hookly-webhook-automator' ); ?></p>
						<?php else : ?>
							<table class="hookly-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Webhook', 'hookly-webhook-automator' ); ?></th>
										<th><?php esc_html_e( 'Status', 'hookly-webhook-automator' ); ?></th>
										<th><?php esc_html_e( 'Time', 'hookly-webhook-automator' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $recentLogs as $log ) : ?>
										<tr>
											<td>
												<strong><?php echo esc_html( $log['webhook_name'] ?: __( 'Unknown', 'hookly-webhook-automator' ) ); ?></strong>
												<br>
												<small class="hookly-text-muted"><?php echo esc_html( $log['trigger_type'] ); ?></small>
											</td>
											<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hookly_get_status_badge returns escaped HTML. ?>
											<td><?php echo hookly_get_status_badge( $log['status'] ); ?></td>
											<td>
												<small><?php echo esc_html( hookly_format_datetime( $log['created_at'] ) ); ?></small>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>

				<!-- Quick Stats & Info -->
				<div>
					<!-- Today's Stats -->
					<div class="hookly-card">
						<div class="hookly-card-header">
							<h2><?php esc_html_e( "Today's Stats", 'hookly-webhook-automator' ); ?></h2>
						</div>
						<div class="hookly-card-body">
							<ul style="margin: 0; padding: 0; list-style: none;">
								<li style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
									<span><?php esc_html_e( 'Successful', 'hookly-webhook-automator' ); ?></span>
									<strong class="hookly-text-success"><?php echo esc_html( $stats['success_today'] ); ?></strong>
								</li>
								<li style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee;">
									<span><?php esc_html_e( 'Failed', 'hookly-webhook-automator' ); ?></span>
									<strong class="hookly-text-error"><?php echo esc_html( $stats['failed_today'] ); ?></strong>
								</li>
								<li style="display: flex; justify-content: space-between; padding: 8px 0;">
									<span><?php esc_html_e( 'Pending', 'hookly-webhook-automator' ); ?></span>
									<strong><?php echo esc_html( $stats['pending_today'] ); ?></strong>
								</li>
							</ul>
						</div>
					</div>

					<!-- Available Triggers -->
					<div class="hookly-card" style="margin-top: 20px;">
						<div class="hookly-card-header">
							<h2><?php esc_html_e( 'Available Triggers', 'hookly-webhook-automator' ); ?></h2>
						</div>
						<div class="hookly-card-body">
							<?php
							$categories = $triggerRegistry->getCategories();
							foreach ( $categories as $category => $triggers ) :
								?>
								<div style="margin-bottom: 15px;">
									<strong><?php echo esc_html( $category ); ?></strong>
									<ul style="margin: 5px 0 0 20px; padding: 0;">
										<?php foreach ( $triggers as $trigger ) : ?>
											<li style="color: #666; font-size: 13px;">
												<?php echo esc_html( $trigger->getName() ); ?>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get dashboard statistics.
	 *
	 * @return array
	 */
	private function getStats(): array {
		$logStats = $this->logger->getStats();

		return [
			'total_webhooks'   => $this->repository->count(),
			'active_webhooks'  => $this->repository->count( [ 'is_active' => 1 ] ),
			'deliveries_today' => $logStats['today'],
			'success_today'    => $logStats['success_today'],
			'failed_today'     => $logStats['failed_today'],
			'pending_today'    => $logStats['pending_today'] ?? 0,
			'success_rate'     => $logStats['success_rate'],
		];
	}
}
