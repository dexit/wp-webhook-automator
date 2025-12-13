<?php
/**
 * Plugin Activator
 *
 * @package WP_Webhook_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WWA_Activator {

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_tables();
		self::set_default_options();
		self::schedule_events();

		// Store version for upgrade routines
		update_option( 'wwa_version', WWA_VERSION );
		update_option( 'wwa_db_version', WWA_DB_VERSION );

		// Flush rewrite rules for REST API
		flush_rewrite_rules();
	}

	/**
	 * Create database tables.
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$webhooks_table  = $wpdb->prefix . 'wwa_webhooks';
		$logs_table      = $wpdb->prefix . 'wwa_logs';

		$sql_webhooks = "CREATE TABLE {$webhooks_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            trigger_type VARCHAR(100) NOT NULL,
            trigger_config LONGTEXT,
            endpoint_url VARCHAR(2048) NOT NULL,
            http_method VARCHAR(10) DEFAULT 'POST',
            headers LONGTEXT,
            payload_format VARCHAR(20) DEFAULT 'json',
            payload_template LONGTEXT,
            secret_key VARCHAR(255),
            is_active TINYINT(1) DEFAULT 1,
            retry_count TINYINT UNSIGNED DEFAULT 3,
            retry_delay INT UNSIGNED DEFAULT 60,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED,
            PRIMARY KEY (id),
            KEY idx_trigger_type (trigger_type),
            KEY idx_is_active (is_active)
        ) {$charset_collate};";

		$sql_logs = "CREATE TABLE {$logs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            webhook_id BIGINT UNSIGNED NOT NULL,
            trigger_type VARCHAR(100) NOT NULL,
            trigger_data LONGTEXT,
            endpoint_url VARCHAR(2048) NOT NULL,
            request_headers LONGTEXT,
            request_payload LONGTEXT,
            response_code SMALLINT,
            response_headers LONGTEXT,
            response_body LONGTEXT,
            duration_ms INT UNSIGNED,
            status VARCHAR(20) NOT NULL,
            error_message TEXT,
            attempt_number TINYINT UNSIGNED DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_webhook_id (webhook_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_webhooks );
		dbDelta( $sql_logs );
	}

	/**
	 * Set default plugin options.
	 *
	 * @return void
	 */
	private static function set_default_options(): void {
		$defaults = [
			'wwa_log_retention_days' => 30,
			'wwa_max_log_entries'    => 1000,
			'wwa_default_timeout'    => 30,
			'wwa_enable_async'       => true,
			'wwa_rate_limit'         => 100, // per minute
		];

		foreach ( $defaults as $key => $value ) {
			if ( get_option( $key ) === false ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Schedule cron events.
	 *
	 * @return void
	 */
	private static function schedule_events(): void {
		if ( ! wp_next_scheduled( 'wwa_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'wwa_cleanup_logs' );
		}
	}
}
