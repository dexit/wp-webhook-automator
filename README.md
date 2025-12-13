# WP Webhook Automator

[![CI](https://github.com/GhDj/wp-webhook-automator/actions/workflows/ci.yml/badge.svg)](https://github.com/GhDj/wp-webhook-automator/actions/workflows/ci.yml)
[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/wp-webhook-automator)](https://wordpress.org/plugins/wp-webhook-automator/)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/wp-webhook-automator)](https://wordpress.org/plugins/wp-webhook-automator/)
[![WordPress Plugin Rating](https://img.shields.io/wordpress/plugin/stars/wp-webhook-automator)](https://wordpress.org/plugins/wp-webhook-automator/)
[![License](https://img.shields.io/badge/license-GPLv2-blue.svg)](LICENSE)

Connect WordPress events to external services via webhooks. A lightweight, developer-friendly automation tool — like Zapier, but self-hosted and free.

## Features

- **13+ Event Triggers** — Posts, users, comments, and more
- **Flexible Payloads** — JSON or form-encoded with 50+ merge tags
- **Conditional Logic** — Filter by post type, user role, status
- **Delivery Logging** — Track success/failure with detailed logs
- **Auto-Retry** — Configurable retry for failed webhooks
- **Secure** — HMAC signatures for payload verification
- **Developer Friendly** — REST API, custom hooks, extensible triggers

## Quick Start

### Installation

1. Download from [WordPress.org](https://wordpress.org/plugins/wp-webhook-automator/) or search "WP Webhook Automator" in your WordPress admin
2. Activate the plugin
3. Go to **Webhooks → Add New**
4. Select a trigger, enter your endpoint URL, customize the payload
5. Save and test!

### Example: Post to Slack on New Post

1. Create a Slack Incoming Webhook
2. Add new webhook in WordPress:
   - **Trigger:** Post Published
   - **URL:** Your Slack webhook URL
   - **Payload:**
   ```json
   {
     "text": "New post published: {{post.title}}",
     "blocks": [
       {
         "type": "section",
         "text": {
           "type": "mrkdwn",
           "text": "*<{{post.url}}|{{post.title}}>*\n{{post.excerpt}}"
         }
       }
     ]
   }
   ```

## Available Triggers

### Posts
- Post Published
- Post Updated
- Post Deleted
- Post Trashed

### Users
- User Registered
- User Updated
- User Deleted
- User Login
- User Logout

### Comments
- Comment Created
- Comment Approved
- Comment Marked as Spam
- Comment Reply

## Documentation

- [Getting Started Guide](https://dev-tools.online/docs/wp-webhook-automator/getting-started)
- [Available Triggers](https://dev-tools.online/docs/wp-webhook-automator/triggers)
- [Merge Tags Reference](https://dev-tools.online/docs/wp-webhook-automator/merge-tags)
- [REST API](https://dev-tools.online/docs/wp-webhook-automator/api)

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Contributing

Contributions are welcome! Please read our [Contributing Guide](CONTRIBUTING.md) and [Code of Conduct](CODE_OF_CONDUCT.md).

```bash
# Clone the repo
git clone https://github.com/GhDj/wp-webhook-automator.git

# Install dependencies
composer install

# Run tests
composer test

# Check coding standards
composer phpcs
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes.

## Pro Version

Need more power? [WP Webhook Automator Pro](https://dev-tools.online/wp-webhook-automator-pro) includes:

- Unlimited webhooks (free: 3)
- 90-day log retention (free: 7 days)
- Advanced conditional logic
- Incoming webhooks
- Scheduled triggers
- WooCommerce triggers
- Priority support

## License

GPL v2 or later. See [LICENSE](LICENSE) for details.

## Credits

Built by [Ghabri Jalel](https://dev-tools.online)

---

If this plugin helps you, please [leave a review](https://wordpress.org/support/plugin/wp-webhook-automator/reviews/#new-post)
