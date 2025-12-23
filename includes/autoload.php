<?php
/**
 * PSR-4 Autoloader for Hookly namespace
 *
 * This file provides autoloading when Composer is not available
 * (e.g., when installed from WordPress.org).
 *
 * @package Hookly_Webhook_Automator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	function ( $class ) {
		// Only handle Hookly namespace
		$prefix = 'Hookly\\';

		// Check if class uses our namespace
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		// Get the relative class name
		$relative_class = substr( $class, $len );

		// Build the file path
		// Hookly\Admin\Admin -> src/Admin/Admin.php
		$file = HOOKLY_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		// If the file exists, require it
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
