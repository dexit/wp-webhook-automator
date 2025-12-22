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

		// Add New
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Add New', 'hookly-webhook-automator' ),
			__( 'Add New', 'hookly-webhook-automator' ),
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

		// Hidden edit page
		add_submenu_page(
			null,
			__( 'Edit Webhook', 'hookly-webhook-automator' ),
			__( 'Edit Webhook', 'hookly-webhook-automator' ),
			'manage_options',
			'hookly-webhook-edit',
			[ $this, 'renderWebhookForm' ]
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
		// Handle form submissions
		if ( ! isset( $_POST['hookly_action'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! isset( $_POST['hookly_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hookly_nonce'] ) ), 'hookly_admin' ) ) {
			return;
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['hookly_action'] ) );

		switch ( $action ) {
			case 'save_webhook':
				$this->handleSaveWebhook();
				break;
			case 'delete_webhook':
				$this->handleDeleteWebhook();
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
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function renderSettings(): void {
		$settings = new Settings();
		$settings->render();
	}
}
