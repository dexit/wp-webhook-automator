<?php
/**
 * Uninstall Hookly - Webhook Automator
 *
 * Removes all plugin data when uninstalled.
 *
 * @package Hookly_Webhook_Automator
 */

// Exit if not called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete database tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hookly_logs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hookly_webhooks" );

// Delete options
$options = [
	'hookly_version',
	'hookly_db_version',
	'hookly_log_retention_days',
	'hookly_max_log_entries',
	'hookly_default_timeout',
	'hookly_enable_async',
	'hookly_rate_limit',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

// Clear any scheduled events
wp_clear_scheduled_hook( 'hookly_cleanup_logs' );
wp_clear_scheduled_hook( 'hookly_dispatch_webhook' );
wp_clear_scheduled_hook( 'hookly_retry_webhook' );
