<?php
/**
 * Admin Controller
 *
 * Main admin class that initializes all admin functionality.
 *
 * @package WP_Webhook_Automator
 */

namespace Hookly\Admin;

class Admin {

	/**
	 * Menu slug.
	 */
	private const MENU_SLUG = 'hookly-webhooks';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'addMenuPages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAssets' ] );
		add_action( 'admin_init', [ $this, 'handleActions' ] );
		add_action( 'admin_notices', [ $this, 'displayNotices' ] );
	}

	/**
	 * Add admin menu pages.
	 *
	 * @return void
	 */
	public function addMenuPages(): void {
		// Main menu
		add_menu_page(
			__( 'Webhook Automator', 'hookly-webhook-automator' ),
			__( 'Webhooks', 'hookly-webhook-automator' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'renderDashboard' ],
			'dashicons-rss',
			80
		);

		// Dashboard submenu (same as main)
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'hookly-webhook-automator' ),
			__( 'Dashboard', 'hookly-webhook-automator' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'renderDashboard' ]
		);

		// All Webhooks
		add_submenu_page(
			self::MENU_SLUG,
			__( 'All Webhooks', 'hookly-webhook-automator' ),
			__( 'All Webhooks', 'hookly-webhook-automator' ),
			'manage_options',
			'hookly-webhooks-list',
			[ $this, 'renderWebhooksList' ]
		);

		// REST Routes (Listener)
		add_submenu_page(
			self::MENU_SLUG,
			__( 'REST Routes', 'hookly-webhook-automator' ),
			__( 'REST Routes', 'hookly-webhook-automator' ),
			'manage_options',
			'hookly-rest-routes',
			[ $this, 'renderRestRoutesList' ]
		);

		// Consumers (Scheduled)
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Consumers', 'hookly-webhook-automator' ),
			__( 'Consumers', 'hookly-webhook-automator' ),
			'manage_options',
			'hookly-consumers',
			[ $this, 'renderConsumersList' ]
		);

		// Add New
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Add New Webhook', 'hookly-webhook-automator' ),
			__( 'Add New Webhook', 'hookly-webhook-automator' ),
			'manage_options',
			'hookly-webhook-new',
			[ $this, 'renderWebhookForm' ]
		);

		// Logs
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Logs', 'hookly-webhook-automator' ),
			__( 'Logs', 'hookly-webhook-automator' ),
			'manage_options',
			'hookly-logs',
			[ $this, 'renderLogs' ]
		);

		// Settings
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'hookly-webhook-automator' ),
			__( 'Settings', 'hookly-webhook-automator' ),
			'manage_options',
			'hookly-settings',
			[ $this, 'renderSettings' ]
		);

		// Hidden edit pages
		add_submenu_page(
			null,
			__( 'Edit Webhook', 'hookly-webhook-automator' ),
			__( 'Edit Webhook', 'hookly-webhook-automator' ),
			'manage_options',
			'hookly-webhook-edit',
			[ $this, 'renderWebhookForm' ]
		);

		add_submenu_page(
			null,
			__( 'Edit REST Route', 'hookly-webhook-automator' ),
			__( 'Edit REST Route', 'hookly-webhook-automator' ),
			'manage_options',
			'hookly-rest-route-edit',
			[ $this, 'renderRestRouteForm' ]
		);

		add_submenu_page(
			null,
			__( 'Edit Consumer', 'hookly-webhook-automator' ),
			__( 'Edit Consumer', 'hookly-webhook-automator' ),
			'manage_options',
			'hookly-consumer-edit',
			[ $this, 'renderConsumerForm' ]
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string|null $hook The current admin page.
	 * @return void
	 */
	public function enqueueAssets( ?string $hook ): void {
		// Only load on our plugin pages
		if ( ! $this->isPluginPage( $hook ) ) {
			return;
		}

		// CSS
		wp_enqueue_style(
			'hookly-admin',
			HOOKLY_PLUGIN_URL . 'assets/css/admin.css',
			[],
			HOOKLY_VERSION
		);

		// JavaScript
		wp_enqueue_script(
			'hookly-admin',
			HOOKLY_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			HOOKLY_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'hookly-admin',
			'hooklyAdmin',
			[
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'restUrl'   => rest_url( 'hookly/v1/' ),
				'nonce'     => wp_create_nonce( 'hookly_admin' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'strings'   => [
					'confirmDelete'    => __( 'Are you sure you want to delete this webhook? This action cannot be undone.', 'hookly-webhook-automator' ),
					'confirmClearLogs' => __( 'Are you sure you want to clear all logs? This action cannot be undone.', 'hookly-webhook-automator' ),
					'testSent'         => __( 'Test webhook sent! Check the logs for results.', 'hookly-webhook-automator' ),
					'testFailed'       => __( 'Failed to send test webhook.', 'hookly-webhook-automator' ),
					'copied'           => __( 'Copied to clipboard!', 'hookly-webhook-automator' ),
				],
			]
		);
	}

	/**
	 * Check if current page is a plugin page.
	 *
	 * @param string|null $hook The current admin page hook.
	 * @return bool
	 */
	private function isPluginPage( ?string $hook ): bool {
		if ( $hook === null || $hook === '' ) {
			return false;
		}

		$pluginPages = [
			'toplevel_page_hookly-webhooks',
			'webhooks_page_hookly-webhooks-list',
			'webhooks_page_hookly-webhook-new',
			'webhooks_page_hookly-webhook-edit',
			'webhooks_page_hookly-logs',
			'webhooks_page_hookly-settings',
		];

		return in_array( $hook, $pluginPages, true ) || str_contains( $hook, 'hookly-' );
	}

	/**
	 * Handle admin actions.
	 *
	 * @return void
	 */
	public function handleActions(): void {
		$action = $_POST['hookly_action'] ?? $_GET['hookly_action'] ?? null;
		$nonce  = $_POST['hookly_nonce'] ?? $_GET['hookly_nonce'] ?? null;

		if ( ! $action ) {
			return;
		}

		// Verify nonce
		if ( ! $nonce || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'hookly_admin' ) ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $action ) );

		switch ( $action ) {
			case 'save_webhook':
				$this->handleSaveWebhook();
				break;
			case 'delete_webhook':
				$this->handleDeleteWebhook();
				break;
			case 'save_rest_route':
				$this->handleSaveRestRoute();
				break;
			case 'delete_rest_route':
				$this->handleDeleteRestRoute();
				break;
			case 'save_consumer':
				$this->handleSaveConsumer();
				break;
			case 'delete_consumer':
				$this->handleDeleteConsumer();
				break;
			case 'save_settings':
				$this->handleSaveSettings();
				break;
			case 'clear_logs':
				$this->handleClearLogs();
				break;
		}
	}

	/**
	 * Handle saving a webhook.
	 *
	 * @return void
	 */
	private function handleSaveWebhook(): void {
		$form = new WebhookForm();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handleFormSubmission.
		$result = $form->handleSubmit( $_POST );

		if ( $result['success'] ) {
			$this->addNotice( 'success', $result['message'] );
			wp_safe_redirect( admin_url( 'admin.php?page=hookly-webhooks-list' ) );
			exit;
		} else {
			$this->addNotice( 'error', $result['message'] );
		}
	}

	/**
	 * Handle deleting a webhook.
	 *
	 * @return void
	 */
	private function handleDeleteWebhook(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handleFormSubmission.
		$webhookId = isset( $_POST['webhook_id'] ) ? (int) $_POST['webhook_id'] : 0;

		if ( ! $webhookId ) {
			$this->addNotice( 'error', __( 'Invalid webhook ID.', 'hookly-webhook-automator' ) );
			return;
		}

		$repository = new \Hookly\Core\WebhookRepository();
		if ( $repository->delete( $webhookId ) ) {
			$this->addNotice( 'success', __( 'Webhook deleted successfully.', 'hookly-webhook-automator' ) );
		} else {
			$this->addNotice( 'error', __( 'Failed to delete webhook.', 'hookly-webhook-automator' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=hookly-webhooks-list' ) );
		exit;
	}

	/**
	 * Handle saving settings.
	 *
	 * @return void
	 */
	private function handleSaveSettings(): void {
		$settings = new Settings();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handleFormSubmission.
		$result = $settings->handleSubmit( $_POST );

		if ( $result['success'] ) {
			$this->addNotice( 'success', $result['message'] );
		} else {
			$this->addNotice( 'error', $result['message'] );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=hookly-settings' ) );
		exit;
	}

	/**
	 * Handle clearing logs.
	 *
	 * @return void
	 */
	private function handleClearLogs(): void {
		$logger = new \Hookly\Core\Logger();
		$logger->clearAll();

		$this->addNotice( 'success', __( 'All logs have been cleared.', 'hookly-webhook-automator' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=hookly-logs' ) );
		exit;
	}

	/**
	 * Add an admin notice.
	 *
	 * @param string $type    Notice type (success, error, warning, info).
	 * @param string $message Notice message.
	 * @return void
	 */
	private function addNotice( string $type, string $message ): void {
		$notices   = get_transient( 'hookly_admin_notices' ) ?: [];
		$notices[] = [
			'type'    => $type,
			'message' => $message,
		];
		set_transient( 'hookly_admin_notices', $notices, 60 );
	}

	/**
	 * Display admin notices.
	 *
	 * @return void
	 */
	public function displayNotices(): void {
		$notices = get_transient( 'hookly_admin_notices' );

		if ( ! $notices ) {
			return;
		}

		delete_transient( 'hookly_admin_notices' );

		foreach ( $notices as $notice ) {
			$class = 'notice notice-' . esc_attr( $notice['type'] ) . ' is-dismissible';
			printf(
				'<div class="%1$s"><p>%2$s</p></div>',
				esc_attr( $class ),
				esc_html( $notice['message'] )
			);
		}
	}

	/**
	 * Handle saving a REST route.
	 */
	private function handleSaveRestRoute(): void {
		$data = [
			'id'         => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
			'name'       => sanitize_text_field( $_POST['name'] ?? '' ),
			'route_path' => sanitize_title( $_POST['route_path'] ?? '' ),
			'is_active'  => isset( $_POST['is_active'] ),
			'is_async'   => isset( $_POST['is_async'] ),
			'actions'    => json_decode( wp_unslash( $_POST['actions'] ?? '[]' ), true ),
		];
		$route   = new \Hookly\Extensions\RestRoutes\RestRoute( $data );
		$repo    = new \Hookly\Extensions\RestRoutes\RestRouteRepository();
		$saved_id = $repo->save( $route );

		if ( $saved_id ) {
			$this->addNotice( 'success', __( 'REST Route saved successfully.', 'hookly-webhook-automator' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=hookly-rest-routes' ) );
			exit;
		}
	}

	/**
	 * Handle deleting a REST route.
	 */
	private function handleDeleteRestRoute(): void {
		$id   = isset( $_POST['route_id'] ) ? (int) $_POST['route_id'] : 0;
		$repo = new \Hookly\Extensions\RestRoutes\RestRouteRepository();
		if ( $repo->delete( $id ) ) {
			$this->addNotice( 'success', __( 'REST Route deleted successfully.', 'hookly-webhook-automator' ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=hookly-rest-routes' ) );
		exit;
	}

	/**
	 * Handle saving a consumer.
	 */
	private function handleSaveConsumer(): void {
		$data = [
			'id'          => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
			'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
			'source_url'  => esc_url_raw( $_POST['source_url'] ?? '' ),
			'http_method' => sanitize_text_field( $_POST['http_method'] ?? 'GET' ),
			'schedule'    => sanitize_text_field( $_POST['schedule'] ?? 'hourly' ),
			'actions'     => json_decode( wp_unslash( $_POST['actions'] ?? '[]' ), true ),
			'is_active'   => isset( $_POST['is_active'] ),
		];
		$consumer = new \Hookly\Extensions\Consumers\Consumer( $data );
		$repo     = new \Hookly\Extensions\Consumers\ConsumerRepository();
		$saved_id = $repo->save( $consumer );

		if ( $saved_id ) {
			$this->addNotice( 'success', __( 'Consumer saved successfully.', 'hookly-webhook-automator' ) );
			wp_safe_redirect( admin_url( 'admin.php?page=hookly-consumers' ) );
			exit;
		}
	}

	/**
	 * Handle deleting a consumer.
	 */
	private function handleDeleteConsumer(): void {
		$id   = isset( $_POST['consumer_id'] ) ? (int) $_POST['consumer_id'] : 0;
		$repo = new \Hookly\Extensions\Consumers\ConsumerRepository();
		if ( $repo->delete( $id ) ) {
			$this->addNotice( 'success', __( 'Consumer deleted successfully.', 'hookly-webhook-automator' ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=hookly-consumers' ) );
		exit;
	}

	/**
	 * Render the dashboard page.
	 *
	 * @return void
	 */
	public function renderDashboard(): void {
		$dashboard = new Dashboard();
		$dashboard->render();
	}

	/**
	 * Render the webhooks list page.
	 *
	 * @return void
	 */
	public function renderWebhooksList(): void {
		$list = new WebhookList();
		$list->render();
	}

	/**
	 * Render the webhook form page.
	 *
	 * @return void
	 */
	public function renderWebhookForm(): void {
		$form = new WebhookForm();
		$form->render();
	}

	/**
	 * Render the logs page.
	 *
	 * @return void
	 */
	public function renderLogs(): void {
		$logs = new LogsViewer();
		$logs->render();
	}

	/**
	 * Render the REST routes list.
	 */
	public function renderRestRoutesList(): void {
		echo '<div class="wrap"><h1>' . __( 'REST Routes (Listeners)', 'hookly-webhook-automator' ) . ' <a href="' . admin_url( 'admin.php?page=hookly-rest-route-edit' ) . '" class="page-title-action">' . __( 'Add New', 'hookly-webhook-automator' ) . '</a></h1>';
		echo '<p>' . __( 'Define custom endpoints to receive data from external services.', 'hookly-webhook-automator' ) . '</p>';
		$repo   = new \Hookly\Extensions\RestRoutes\RestRouteRepository();
		$routes = $repo->findAll();
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Name</th><th>Path</th><th>Actions Count</th><th>Active</th><th>Manage</th></tr></thead><tbody>';
		foreach ( $routes as $route ) {
			$edit_url   = admin_url( 'admin.php?page=hookly-rest-route-edit&id=' . $route->getId() );
			$delete_url = wp_nonce_url( admin_url( 'admin.php?page=hookly-rest-routes&hookly_action=delete_rest_route&route_id=' . $route->getId() ), 'hookly_admin', 'hookly_nonce' );
			echo '<tr>';
			echo '<td><strong><a href="' . $edit_url . '">' . esc_html( $route->getName() ) . '</a></strong>';
			echo '<div class="row-actions"><span class="edit"><a href="' . $edit_url . '">Edit</a> | </span><span class="trash"><a href="' . $delete_url . '" class="submitdelete" onclick="return confirm(\'Delete this route?\')">Delete</a></span></div>';
			echo '</td>';
			echo '<td><code>hookly/v1/incoming/' . esc_html( $route->getRoutePath() ) . '</code></td>';
			echo '<td>' . count( $route->getActions() ) . '</td>';
			echo '<td>' . ( $route->isActive() ? 'Yes' : 'No' ) . '</td>';
			echo '<td><a href="' . $edit_url . '">Edit</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Render the REST route form.
	 */
	public function renderRestRouteForm(): void {
		$id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$repo  = new \Hookly\Extensions\RestRoutes\RestRouteRepository();
		$route = $id ? $repo->find( $id ) : new \Hookly\Extensions\RestRoutes\RestRoute();
		?>
		<div class="wrap">
			<h1><?php echo $id ? 'Edit REST Route' : 'Add New REST Route'; ?></h1>
			<form method="post" action="<?php echo admin_url( 'admin.php?page=hookly-rest-routes' ); ?>">
				<?php wp_nonce_field( 'hookly_admin', 'hookly_nonce' ); ?>
				<input type="hidden" name="hookly_action" value="save_rest_route">
				<input type="hidden" name="id" value="<?php echo $id; ?>">
				<table class="form-table">
					<tr><th>Name</th><td><input type="text" name="name" value="<?php echo esc_attr( $route->getName() ); ?>" class="regular-text"></td></tr>
					<tr><th>Route Path</th><td><code>hookly/v1/incoming/</code> <input type="text" name="route_path" value="<?php echo esc_attr( $route->getRoutePath() ); ?>" class="regular-text"></td></tr>
					<tr><th>Active</th><td><input type="checkbox" name="is_active" value="1" <?php checked( $route->isActive() ); ?>></td></tr>
					<tr><th>Async</th><td><input type="checkbox" name="is_async" value="1" <?php checked( $route->isAsync() ); ?>></td></tr>
					<tr><th>Actions (JSON)</th><td><textarea name="actions" class="large-text" rows="10"><?php echo esc_textarea( wp_json_encode( $route->getActions(), JSON_PRETTY_PRINT ) ); ?></textarea><p class="description">Define your action chain here.</p></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the consumers list.
	 */
	public function renderConsumersList(): void {
		echo '<div class="wrap"><h1>' . __( 'Consumers (Scheduled Requests)', 'hookly-webhook-automator' ) . ' <a href="' . admin_url( 'admin.php?page=hookly-consumer-edit' ) . '" class="page-title-action">' . __( 'Add New', 'hookly-webhook-automator' ) . '</a></h1>';
		$repo      = new \Hookly\Extensions\Consumers\ConsumerRepository();
		$consumers = $repo->findAll();
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Name</th><th>Source URL</th><th>Schedule</th><th>Active</th><th>Manage</th></tr></thead><tbody>';
		foreach ( $consumers as $c ) {
			$edit_url   = admin_url( 'admin.php?page=hookly-consumer-edit&id=' . $c->getId() );
			$delete_url = wp_nonce_url( admin_url( 'admin.php?page=hookly-consumers&hookly_action=delete_consumer&consumer_id=' . $c->getId() ), 'hookly_admin', 'hookly_nonce' );
			echo '<tr>';
			echo '<td><strong><a href="' . $edit_url . '">' . esc_html( $c->getName() ) . '</a></strong>';
			echo '<div class="row-actions"><span class="edit"><a href="' . $edit_url . '">Edit</a> | </span><span class="trash"><a href="' . $delete_url . '" class="submitdelete" onclick="return confirm(\'Delete this consumer?\')">Delete</a></span></div>';
			echo '</td>';
			echo '<td>' . esc_html( $c->getSourceUrl() ) . '</td>';
			echo '<td>' . esc_html( $c->getSchedule() ) . '</td>';
			echo '<td>' . ( $c->isActive() ? 'Yes' : 'No' ) . '</td>';
			echo '<td><a href="' . $edit_url . '">Edit</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Render the consumer form.
	 */
	public function renderConsumerForm(): void {
		$id       = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$repo     = new \Hookly\Extensions\Consumers\ConsumerRepository();
		$consumer = $id ? $repo->find( $id ) : new \Hookly\Extensions\Consumers\Consumer();
		?>
		<div class="wrap">
			<h1><?php echo $id ? 'Edit Consumer' : 'Add New Consumer'; ?></h1>
			<form method="post" action="<?php echo admin_url( 'admin.php?page=hookly-consumers' ); ?>">
				<?php wp_nonce_field( 'hookly_admin', 'hookly_nonce' ); ?>
				<input type="hidden" name="hookly_action" value="save_consumer">
				<input type="hidden" name="id" value="<?php echo $id; ?>">
				<table class="form-table">
					<tr><th>Name</th><td><input type="text" name="name" value="<?php echo esc_attr( $consumer->getName() ); ?>" class="regular-text"></td></tr>
					<tr><th>Source URL</th><td><input type="text" name="source_url" value="<?php echo esc_url( $consumer->getSourceUrl() ); ?>" class="large-text"></td></tr>
					<tr><th>Schedule</th><td><input type="text" name="schedule" value="<?php echo esc_attr( $consumer->getSchedule() ); ?>"> (hourly, daily, etc)</td></tr>
					<tr><th>Active</th><td><input type="checkbox" name="is_active" value="1" <?php checked( $consumer->isActive() ); ?>></td></tr>
					<tr><th>Actions (JSON)</th><td><textarea name="actions" class="large-text" rows="10"><?php echo esc_textarea( wp_json_encode( $consumer->getActions(), JSON_PRETTY_PRINT ) ); ?></textarea></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function renderSettings(): void {
		$settings = new Settings();
		$settings->render();
	}
}
