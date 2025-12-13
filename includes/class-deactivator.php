<?php
/**
 * Plugin Deactivator
 *
 * @package WP_Webhook_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WWA_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Clear scheduled events
		wp_clear_scheduled_hook( 'wwa_cleanup_logs' );
		wp_clear_scheduled_hook( 'wwa_dispatch_webhook' );
		wp_clear_scheduled_hook( 'wwa_retry_webhook' );

		// Flush rewrite rules
		flush_rewrite_rules();
	}
}
