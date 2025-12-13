# Contributing to WP Webhook Automator

Thank you for considering contributing!

## How to Contribute

### Reporting Bugs

1. Check existing [issues](https://github.com/GhDj/wp-webhook-automator/issues)
2. Create a new issue using the bug report template
3. Include WordPress version, PHP version, and steps to reproduce

### Suggesting Features

1. Check existing [feature requests](https://github.com/GhDj/wp-webhook-automator/issues?q=label%3Aenhancement)
2. Create a new issue using the feature request template
3. Describe the problem and your proposed solution

### Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests (`composer test`)
5. Run coding standards check (`composer phpcs`)
6. Commit with clear messages (`git commit -m 'Add amazing feature'`)
7. Push to your branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

### Development Setup

```bash
# Clone your fork
git clone https://github.com/GhDj/wp-webhook-automator.git
cd wp-webhook-automator

# Install dependencies
composer install

# Run tests
composer test

# Run unit tests only
composer test:unit

# Run integration tests only
composer test:integration

# Check coding standards
composer phpcs

# Auto-fix coding standards
composer phpcbf
```

### Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Use PHP 8.0+ features appropriately
- Add PHPDoc blocks for all classes and methods
- Write tests for new features

### Commit Messages

- Use present tense ("Add feature" not "Added feature")
- Use imperative mood ("Move cursor to..." not "Moves cursor to...")
- Reference issues and PRs when relevant

## Code of Conduct

Please read our [Code of Conduct](CODE_OF_CONDUCT.md).

## Questions?

Open an issue or reach out to the maintainers.
