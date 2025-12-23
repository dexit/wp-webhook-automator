<?php
/**
 * Plugin Name:       Hookly - Webhook Automator
 * Plugin URI:        https://github.com/GhDj/wp-webhook-automator
 * Description:       Connect WordPress events to external services via webhooks. A lightweight, developer-friendly automation tool.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Jalel Ghabri
 * Author URI:        https://github.com/GhDj
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hookly-webhook-automator
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants
define( 'HOOKLY_VERSION', '1.0.1' );
define( 'HOOKLY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HOOKLY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HOOKLY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'HOOKLY_DB_VERSION', '1.0.0' );

// Autoloader for src/ directory
// Use Composer autoloader if available (development), otherwise use custom autoloader (production)
if ( file_exists( HOOKLY_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once HOOKLY_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	require_once HOOKLY_PLUGIN_DIR . 'includes/autoload.php';
}

// Include core files
require_once HOOKLY_PLUGIN_DIR . 'includes/functions.php';
require_once HOOKLY_PLUGIN_DIR . 'includes/class-loader.php';
require_once HOOKLY_PLUGIN_DIR . 'includes/class-activator.php';
require_once HOOKLY_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once HOOKLY_PLUGIN_DIR . 'includes/class-plugin.php';

// Activation/Deactivation hooks
register_activation_hook( __FILE__, [ 'Hookly_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Hookly_Deactivator', 'deactivate' ] );

/**
 * Initialize plugin.
 *
 * @return Hookly_Plugin
 */
function hookly_init(): Hookly_Plugin {
	return Hookly_Plugin::get_instance();
}

/**
 * Add plugin action links.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function hookly_plugin_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'admin.php?page=hookly-webhooks' ),
		__( 'Settings', 'hookly-webhook-automator' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}

// Start the plugin
add_action( 'plugins_loaded', 'hookly_init' );

// Add settings link on plugins page
add_filter( 'plugin_action_links_' . HOOKLY_PLUGIN_BASENAME, 'hookly_plugin_action_links' );
