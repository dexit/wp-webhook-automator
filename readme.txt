=== Hookly - Webhook Automator ===
Contributors: ghabri
Tags: webhook, automation, zapier, integrations, api
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress events to external services via webhooks. A lightweight, developer-friendly automation tool.

== Description ==

Hookly allows you to send data from your WordPress site to external services whenever specific events occur. Whether you want to notify Slack when a new post is published, sync user registrations with your CRM, or trigger workflows in Zapier, this plugin makes it easy.

= Key Features =

* **Multiple Triggers** - Post published, user registered, comment added, WooCommerce orders, and more
* **Custom Payloads** - Build your own JSON payloads using merge tags
* **Secure Delivery** - HMAC signatures for webhook verification
* **Retry Logic** - Automatic retries for failed deliveries
* **Detailed Logs** - Track every webhook request and response
* **Developer Friendly** - Hooks and filters for customization

= Supported Triggers =

**Posts**
* Post Published
* Post Updated
* Post Deleted

**Users**
* User Registered
* User Updated
* User Deleted
* User Login

**Comments**
* Comment Created
* Comment Approved

**WooCommerce** (if installed)
* Order Created
* Order Status Changed
* Product Created
* Product Stock Low

= Use Cases =

* Send Slack notifications for new content
* Sync users to email marketing platforms
* Trigger Zapier/Make/n8n workflows
* Update external systems when orders are placed
* Build custom integrations with any webhook-enabled service

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/wp-webhook-automator/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to 'Webhooks' in the admin menu to create your first webhook

== Frequently Asked Questions ==

= What is a webhook? =

A webhook is an HTTP callback that sends data to a specified URL when an event occurs. It's a way for apps to communicate in real-time.

= Is this compatible with WooCommerce? =

Yes! Hookly includes triggers for WooCommerce orders and products. The WooCommerce triggers only appear if WooCommerce is installed and active.

= Can I customize the data sent? =

Absolutely. You can create custom JSON payloads using merge tags like `{{post.title}}` or `{{user.email}}`. See the documentation for all available merge tags.

= How do I verify webhooks on the receiving end? =

Each webhook can be configured with a secret key. We send an HMAC-SHA256 signature in the `X-Webhook-Signature` header that you can verify on your server.

= What happens if a webhook fails? =

By default, webhooks will retry up to 3 times with a 60-second delay between attempts. You can customize this per webhook.

== Screenshots ==

1. Dashboard - Overview with webhook statistics, success rates, and available triggers
2. Webhook List - All webhooks with status indicators, triggers, and quick actions
3. Add New Webhook - Easy webhook configuration with trigger selection and endpoint URL
4. Logs Viewer - Detailed delivery logs with filtering and status tracking
5. Settings - Configure async delivery, timeouts, rate limits, and log retention

== Changelog ==

= 1.0.1 =
* Fixed: Added custom autoloader for WordPress.org distribution
* Fixed: Plugin now works correctly when installed from WordPress.org

= 1.0.0 =
* Initial release
* Core webhook functionality
* Post, User, and Comment triggers
* WooCommerce integration
* Admin dashboard and log viewer
* REST API endpoints

== Upgrade Notice ==

= 1.0.1 =
Critical fix: Plugin now works correctly when installed from WordPress.org.

= 1.0.0 =
Initial release of Hookly - Webhook Automator.
