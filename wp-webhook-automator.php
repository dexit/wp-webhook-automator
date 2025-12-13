<?php
/**
 * Plugin Name:       Webhook Automator
 * Plugin URI:        https://github.com/developer/wp-webhook-automator
 * Description:       Connect WordPress events to external services via webhooks. A lightweight, developer-friendly automation tool.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Developer
 * Author URI:        https://developer.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-webhook-automator
 * Domain Path:       /languages
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
 * Load plugin textdomain.
 *
 * @return void
 */
function wwa_load_textdomain(): void {
	load_plugin_textdomain(
		'wp-webhook-automator',
		false,
		dirname( WWA_PLUGIN_BASENAME ) . '/languages'
	);
}

// Load textdomain at init (WordPress 6.7+ requirement)
add_action( 'init', 'wwa_load_textdomain' );

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
