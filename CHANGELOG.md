# Changelog

All notable changes to WP Webhook Automator will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-12-13

### Added
- Initial release
- 13 built-in triggers across 3 categories (posts, users, comments)
- Post triggers: published, updated, deleted, trashed
- User triggers: registered, updated, deleted, login, logout
- Comment triggers: created, approved, spam, reply
- Customizable JSON and form-encoded payloads
- Dynamic merge tags for payload building (e.g., `{{post.title}}`, `{{user.email}}`)
- Conditional filtering by post type, user role
- Delivery logging with search and filters
- Auto-retry mechanism for failed webhooks
- HMAC-SHA256 payload signatures for security
- REST API endpoints for webhooks, logs, and triggers
- Admin dashboard with statistics and quick actions
- Webhook list with status toggle, test, and delete actions
- Full webhook editor with trigger configuration
- Settings page with system information
- WordPress.org distribution ready

[Unreleased]: https://github.com/GhDj/wp-webhook-automator/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/GhDj/wp-webhook-automator/releases/tag/v1.0.0
