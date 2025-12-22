<?php
/**
 * Plugin Name:       Hookly - Webhook Automator
 * Plugin URI:        https://github.com/GhDj/wp-webhook-automator
 * Description:       Connect WordPress events to external services via webhooks. A lightweight, developer-friendly automation tool.
 * Version:           1.0.0
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
define( 'WWA_VERSION', '1.0.0' );
define( 'WWA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WWA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WWA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WWA_DB_VERSION', '1.0.0' );

// Composer autoloader for src/ directory
if ( file_exists( WWA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once WWA_PLUGIN_DIR . 'vendor/autoload.php';
}

// Include core files
require_once WWA_PLUGIN_DIR . 'includes/functions.php';
require_once WWA_PLUGIN_DIR . 'includes/class-loader.php';
require_once WWA_PLUGIN_DIR . 'includes/class-activator.php';
require_once WWA_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once WWA_PLUGIN_DIR . 'includes/class-plugin.php';

// Activation/Deactivation hooks
register_activation_hook( __FILE__, [ 'WWA_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'WWA_Deactivator', 'deactivate' ] );

/**
 * Initialize plugin.
 *
 * @return WWA_Plugin
 */
function wwa_init(): WWA_Plugin {
	return WWA_Plugin::get_instance();
}

// Start the plugin
add_action( 'plugins_loaded', 'wwa_init' );
