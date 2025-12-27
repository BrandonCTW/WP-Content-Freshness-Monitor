# Contributing to Content Freshness Monitor

Thank you for your interest in contributing to Content Freshness Monitor!

## Development Setup

### Prerequisites

- PHP 7.4 or higher
- WordPress 6.0 or higher
- MySQL 5.7 or higher
- Composer (optional, for development)

### Local Development

1. Clone the repository into your WordPress `wp-content/plugins/` directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/caltechweb/content-freshness-monitor.git
   ```

2. Activate the plugin in WordPress Admin.

### Running Tests

1. Install the WordPress test suite:
   ```bash
   bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
   ```

2. Run PHPUnit:
   ```bash
   phpunit
   # or with Composer:
   composer test
   ```

## Coding Standards

This plugin follows the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/).

Run PHPCS to check your code:
```bash
phpcs --standard=WordPress-Core --extensions=php .
```

## Pull Request Process

1. Fork the repository and create a feature branch from `develop`.
2. Make your changes and ensure all tests pass.
3. Update documentation if needed.
4. Submit a pull request to the `develop` branch.

## Reporting Issues

Please use the GitHub issue tracker to report bugs or request features. Include:

- WordPress version
- PHP version
- Steps to reproduce the issue
- Expected vs actual behavior

## Security

If you discover a security vulnerability, please email support@caltechweb.com instead of using the public issue tracker.

## License

By contributing, you agree that your contributions will be licensed under the GPL v2 or later.
