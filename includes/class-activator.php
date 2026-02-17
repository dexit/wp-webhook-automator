<?php
/**
 * Plugin Activator
 *
 * @package WP_Webhook_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hookly_Activator {

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_tables();
		self::set_default_options();
		self::schedule_events();
		self::add_example_rest_route();

		// Store version for upgrade routines
		update_option( 'hookly_version', HOOKLY_VERSION );
		update_option( 'hookly_db_version', HOOKLY_DB_VERSION );

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
		$webhooks_table    = $wpdb->prefix . 'hookly_webhooks';
		$logs_table        = $wpdb->prefix . 'hookly_logs';
		$rest_routes_table = $wpdb->prefix . 'hookly_rest_routes';
		$consumers_table   = $wpdb->prefix . 'hookly_consumers';

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

		$sql_rest_routes = "CREATE TABLE {$rest_routes_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            route_path VARCHAR(255) NOT NULL,
            methods VARCHAR(100) DEFAULT '[\"POST\"]',
            actions LONGTEXT,
            is_active TINYINT(1) DEFAULT 1,
            is_async TINYINT(1) DEFAULT 0,
            secret_key VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_route_path (route_path)
        ) {$charset_collate};";

		$sql_consumers = "CREATE TABLE {$consumers_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            source_url VARCHAR(2048) NOT NULL,
            http_method VARCHAR(10) DEFAULT 'GET',
            headers LONGTEXT,
            schedule VARCHAR(50) DEFAULT 'hourly',
            actions LONGTEXT,
            is_active TINYINT(1) DEFAULT 1,
            last_run DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_webhooks );
		dbDelta( $sql_logs );
		dbDelta( $sql_rest_routes );
		dbDelta( $sql_consumers );
	}

	/**
	 * Set default plugin options.
	 *
	 * @return void
	 */
	private static function set_default_options(): void {
		$defaults = [
			'hookly_log_retention_days' => 30,
			'hookly_max_log_entries'    => 1000,
			'hookly_default_timeout'    => 30,
			'hookly_enable_async'       => true,
			'hookly_rate_limit'         => 100, // per minute
		];

		foreach ( $defaults as $key => $value ) {
			if ( get_option( $key ) === false ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Add an example REST route.
	 *
	 * @return void
	 */
	private static function add_example_rest_route(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'hookly_rest_routes';

		// Check if table exists
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) !== $table ) {
			return;
		}

		// Check if any routes exist
		if ( $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) > 0 ) {
			return;
		}

		$wpdb->insert(
			$table,
			[
				'name'       => 'Example: Log Request',
				'route_path' => 'example-log',
				'methods'    => '["POST"]',
				'actions'    => wp_json_encode( [
					[
						'type'   => 'php_code',
						'config' => [ 'code' => '<?php error_log("Hookly REST Route received: " . print_r($data, true)); ?>' ],
					]
				] ),
				'is_active'  => 1,
			]
		);
	}

	/**
	 * Schedule cron events.
	 *
	 * @return void
	 */
	private static function schedule_events(): void {
		if ( ! wp_next_scheduled( 'hookly_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'hookly_cleanup_logs' );
		}
	}
}
