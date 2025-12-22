<?php
/**
 * Webhook List Page
 *
 * Displays a list of all webhooks with actions.
 *
 * @package WP_Webhook_Automator
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET parameters used for read-only filtering.

namespace WWA\Admin;

use WWA\Core\WebhookRepository;
use WWA\Core\Logger;
use WWA\Triggers\TriggerRegistry;

class WebhookList {

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
	 * Items per page.
	 */
	private const PER_PAGE = 20;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new WebhookRepository();
		$this->logger     = new Logger();
	}

	/**
	 * Render the webhooks list.
	 *
	 * @return void
	 */
	public function render(): void {
		$currentPage = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$offset      = ( $currentPage - 1 ) * self::PER_PAGE;

		$criteria   = $this->getFilterCriteria();
		$webhooks   = $this->repository->findAll( $criteria, self::PER_PAGE, $offset );
		$totalItems = $this->repository->count( $criteria );
		$totalPages = ceil( $totalItems / self::PER_PAGE );

		$triggerRegistry = TriggerRegistry::getInstance();
		?>
		<div class="wrap wwa-wrap">
			<div class="wwa-header">
				<h1><?php esc_html_e( 'All Webhooks', 'hookly-webhook-automator' ); ?></h1>
				<div class="wwa-header-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wwa-webhook-new' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Add New Webhook', 'hookly-webhook-automator' ); ?>
					</a>
				</div>
			</div>

			<!-- Filters -->
			<div class="wwa-card" style="margin-bottom: 20px;">
				<form method="get" action="">
					<input type="hidden" name="page" value="wwa-webhooks-list">
					<div style="display: flex; gap: 15px; align-items: center;">
						<div>
							<label for="filter_status" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'hookly-webhook-automator' ); ?></label>
							<select name="status" id="filter_status">
								<option value=""><?php esc_html_e( 'All Statuses', 'hookly-webhook-automator' ); ?></option>
								<option value="1" <?php selected( isset( $_GET['status'] ) && $_GET['status'] === '1' ); ?>><?php esc_html_e( 'Active', 'hookly-webhook-automator' ); ?></option>
								<option value="0" <?php selected( isset( $_GET['status'] ) && $_GET['status'] === '0' ); ?>><?php esc_html_e( 'Inactive', 'hookly-webhook-automator' ); ?></option>
							</select>
						</div>
						<div>
							<label for="filter_trigger" class="screen-reader-text"><?php esc_html_e( 'Filter by trigger', 'hookly-webhook-automator' ); ?></label>
							<select name="trigger_type" id="filter_trigger">
								<option value=""><?php esc_html_e( 'All Triggers', 'hookly-webhook-automator' ); ?></option>
								<?php foreach ( $triggerRegistry->getForSelect() as $category => $triggers ) : ?>
									<optgroup label="<?php echo esc_attr( $category ); ?>">
										<?php foreach ( $triggers as $key => $name ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>" <?php selected( isset( $_GET['trigger_type'] ) && $_GET['trigger_type'] === $key ); ?>>
												<?php echo esc_html( $name ); ?>
											</option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
							</select>
						</div>
						<div>
							<label for="filter_search" class="screen-reader-text"><?php esc_html_e( 'Search', 'hookly-webhook-automator' ); ?></label>
							<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter. ?>
							<input type="text" name="search" id="filter_search" placeholder="<?php esc_attr_e( 'Search...', 'hookly-webhook-automator' ); ?>" value="<?php echo isset( $_GET['search'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['search'] ) ) ) : ''; ?>">
						</div>
						<button type="submit" class="button"><?php esc_html_e( 'Filter', 'hookly-webhook-automator' ); ?></button>
						<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter check. ?>
						<?php if ( ! empty( $_GET['status'] ) || ! empty( $_GET['trigger_type'] ) || ! empty( $_GET['search'] ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wwa-webhooks-list' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'hookly-webhook-automator' ); ?></a>
						<?php endif; ?>
					</div>
				</form>
			</div>

			<!-- Webhooks Table -->
			<div class="wwa-card">
				<?php if ( empty( $webhooks ) ) : ?>
					<div class="wwa-card-body">
						<p class="wwa-text-muted">
							<?php esc_html_e( 'No webhooks found.', 'hookly-webhook-automator' ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wwa-webhook-new' ) ); ?>">
								<?php esc_html_e( 'Create your first webhook', 'hookly-webhook-automator' ); ?>
							</a>
						</p>
					</div>
				<?php else : ?>
					<table class="wwa-table">
						<thead>
							<tr>
								<th class="column-name"><?php esc_html_e( 'Name', 'hookly-webhook-automator' ); ?></th>
								<th class="column-trigger"><?php esc_html_e( 'Trigger', 'hookly-webhook-automator' ); ?></th>
								<th><?php esc_html_e( 'Endpoint', 'hookly-webhook-automator' ); ?></th>
								<th class="column-status"><?php esc_html_e( 'Status', 'hookly-webhook-automator' ); ?></th>
								<th><?php esc_html_e( 'Last Run', 'hookly-webhook-automator' ); ?></th>
								<th class="column-actions"><?php esc_html_e( 'Actions', 'hookly-webhook-automator' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $webhooks as $webhook ) :
								$trigger = $triggerRegistry->get( $webhook->getTriggerType() );
								$stats   = $this->logger->getStatsByWebhook( $webhook->getId() );
								?>
								<tr>
									<td>
										<strong>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=wwa-webhook-edit&id=' . $webhook->getId() ) ); ?>">
												<?php echo esc_html( $webhook->getName() ); ?>
											</a>
										</strong>
										<?php if ( $webhook->getDescription() ) : ?>
											<br><small class="wwa-text-muted"><?php echo esc_html( wp_trim_words( $webhook->getDescription(), 10 ) ); ?></small>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $trigger ) : ?>
											<span class="wwa-badge wwa-badge-info"><?php echo esc_html( $trigger->getName() ); ?></span>
										<?php else : ?>
											<span class="wwa-text-muted"><?php echo esc_html( $webhook->getTriggerType() ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<code style="font-size: 11px; word-break: break-all;">
											<?php echo esc_html( wp_trim_words( $webhook->getEndpointUrl(), 5, '...' ) ); ?>
										</code>
									</td>
									<td>
										<?php if ( $webhook->isActive() ) : ?>
											<span class="wwa-badge wwa-badge-success"><?php esc_html_e( 'Active', 'hookly-webhook-automator' ); ?></span>
										<?php else : ?>
											<span class="wwa-badge"><?php esc_html_e( 'Inactive', 'hookly-webhook-automator' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $stats['last_run'] ) : ?>
											<small><?php echo esc_html( wwa_format_datetime( $stats['last_run'] ) ); ?></small>
											<br>
											<small class="wwa-text-muted">
												<?php
												printf(
													/* translators: 1: success count, 2: failed count */
													esc_html__( '%1$d success, %2$d failed', 'hookly-webhook-automator' ),
													(int) $stats['success'],
													(int) $stats['failed']
												);
												?>
											</small>
										<?php else : ?>
											<small class="wwa-text-muted"><?php esc_html_e( 'Never', 'hookly-webhook-automator' ); ?></small>
										<?php endif; ?>
									</td>
									<td>
										<div style="display: flex; gap: 5px; justify-content: flex-end;">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=wwa-webhook-edit&id=' . $webhook->getId() ) ); ?>" class="button button-small" title="<?php esc_attr_e( 'Edit', 'hookly-webhook-automator' ); ?>">
												<?php esc_html_e( 'Edit', 'hookly-webhook-automator' ); ?>
											</a>
											<button type="button" class="button button-small wwa-test-webhook" data-id="<?php echo esc_attr( $webhook->getId() ); ?>" title="<?php esc_attr_e( 'Test', 'hookly-webhook-automator' ); ?>">
												<?php esc_html_e( 'Test', 'hookly-webhook-automator' ); ?>
											</button>
											<form method="post" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this webhook?', 'hookly-webhook-automator' ); ?>');">
												<?php wp_nonce_field( 'wwa_admin', 'wwa_nonce' ); ?>
												<input type="hidden" name="wwa_action" value="delete_webhook">
												<input type="hidden" name="webhook_id" value="<?php echo esc_attr( $webhook->getId() ); ?>">
												<button type="submit" class="button button-small" style="color: #d63638;">
													<?php esc_html_e( 'Delete', 'hookly-webhook-automator' ); ?>
												</button>
											</form>
										</div>
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

		if ( isset( $_GET['status'] ) && $_GET['status'] !== '' ) {
			$criteria['is_active'] = (int) $_GET['status'];
		}

		if ( ! empty( $_GET['trigger_type'] ) ) {
			$criteria['trigger_type'] = sanitize_text_field( wp_unslash( $_GET['trigger_type'] ) );
		}

		if ( ! empty( $_GET['search'] ) ) {
			$criteria['search'] = sanitize_text_field( wp_unslash( $_GET['search'] ) );
		}

		return $criteria;
	}
}
