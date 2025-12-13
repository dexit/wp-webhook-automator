<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment for Webhook Automator.
 */

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants needed by the plugin
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WWA_VERSION')) {
    define('WWA_VERSION', '1.0.0');
}

if (!defined('WWA_PLUGIN_DIR')) {
    define('WWA_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('WWA_PLUGIN_URL')) {
    define('WWA_PLUGIN_URL', 'https://example.com/wp-content/plugins/wp-webhook-automator/');
}

if (!defined('WWA_PLUGIN_BASENAME')) {
    define('WWA_PLUGIN_BASENAME', 'wp-webhook-automator/wp-webhook-automator.php');
}

if (!defined('WWA_DB_VERSION')) {
    define('WWA_DB_VERSION', '1.0.0');
}

if (!defined('WWA_TESTING')) {
    define('WWA_TESTING', true);
}
