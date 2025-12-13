<?php
/**
 * Uninstall WP Webhook Automator
 *
 * Removes all plugin data when uninstalled.
 *
 * @package WP_Webhook_Automator
 */

// Exit if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete database tables
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wwa_logs");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wwa_webhooks");

// Delete options
$options = [
    'wwa_version',
    'wwa_db_version',
    'wwa_log_retention_days',
    'wwa_max_log_entries',
    'wwa_default_timeout',
    'wwa_enable_async',
    'wwa_rate_limit',
];

foreach ($options as $option) {
    delete_option($option);
}

// Clear any scheduled events
wp_clear_scheduled_hook('wwa_cleanup_logs');
wp_clear_scheduled_hook('wwa_dispatch_webhook');
wp_clear_scheduled_hook('wwa_retry_webhook');
