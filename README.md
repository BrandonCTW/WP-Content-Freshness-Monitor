# Content Freshness Monitor

[![WordPress Plugin Version](https://img.shields.io/badge/version-2.7.1-blue.svg)](https://wordpress.org/plugins/content-freshness-monitor/)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)](https://www.php.net/)
[![WordPress Version](https://img.shields.io/badge/wordpress-%3E%3D5.0-21759B.svg)](https://wordpress.org/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Monitor and manage stale content on your WordPress site. Get visibility into which posts need updating.

## 🚀 Try It Now

Once published, try the plugin instantly in WordPress Playground:

```
https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/caltechweb/content-freshness-monitor/main/blueprint.json
```

Replace `caltechweb` with your GitHub username if different. The demo includes sample content of various ages to showcase all features.

## Why Content Freshness Matters

- Search engines favor regularly updated content
- Outdated information erodes user trust
- Dead links and old references create poor user experience
- Regular content audits improve overall site quality

## Features

### Core Functionality
- **Stale Content Detection** - Automatically identifies posts that haven't been updated within your configured timeframe
- **Dashboard Widget** - Quick overview of your content freshness right on your WordPress dashboard
- **Admin Dashboard** - Dedicated page showing all stale content with sorting and pagination
- **Post List Integration** - See freshness status directly in your Posts list
- **Mark as Reviewed** - Track which content you've reviewed, even if you didn't need to modify it
- **Configurable Threshold** - Set your own staleness threshold (default: 6 months)
- **Multi Post Type Support** - Monitor posts, pages, and custom post types
- **Bulk Actions** - Mark multiple posts as reviewed at once

### Notifications & Export
- **Email Notifications** - Receive scheduled digest emails (daily, weekly, or monthly) about stale content
- **CSV Export** - Export stale content list to CSV for reporting and analysis

### Integrations
- **Block Editor Integration** - See freshness status directly in Gutenberg sidebar
- **REST API** - Full REST API for headless CMS and external integrations
- **WP-CLI Integration** - Manage content freshness from the command line
- **Multisite Support** - Network-wide content freshness dashboard for super admins

### Developer Experience
- **Performance Optimized** - Transient caching for large sites (15-minute cache with auto-invalidation)
- **Accessible** - WCAG 2.1 compliant with full ARIA support
- **PHPUnit Tests** - Comprehensive test suite
- **PHPStan** - Level 6 static analysis configuration
- **WordPress Coding Standards** - PHPCS configuration included

## Installation

### Via WordPress Admin
1. Download the latest release ZIP file
2. Go to WordPress Admin > Plugins > Add New > Upload Plugin
3. Upload the ZIP file and activate

### Via Composer (Coming Soon)
```bash
composer require caltechweb/content-freshness-monitor
```

### Manual Installation
1. Download and extract the plugin
2. Upload the `content-freshness-monitor` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress

## Configuration

1. Navigate to **Settings > Content Freshness**
2. Set your staleness threshold (default: 180 days)
3. Select which post types to monitor
4. Optionally exclude specific post IDs
5. Configure email notifications if desired

## Usage

### Admin Dashboard
Navigate to **Content Freshness** in the admin menu to see all stale content. From here you can:
- Sort by title, date, or days since update
- Paginate through results
- Mark individual or bulk posts as reviewed
- Export to CSV

### Dashboard Widget
A widget on your WordPress dashboard shows a quick summary of fresh, aging, and stale content counts.

### Post List Column
In your Posts list, a new "Freshness" column shows the status of each post at a glance.

### Block Editor Sidebar
When editing a post in Gutenberg, the sidebar shows:
- Current freshness status (Fresh/Aging/Stale)
- Days since last update
- "Mark as Reviewed" button

## REST API

All endpoints are prefixed with `/wp-json/content-freshness-monitor/v1/`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/stats` | GET | Get content statistics |
| `/stale` | GET | List stale posts (paginated) |
| `/post/{id}/freshness` | GET | Check specific post freshness |
| `/post/{id}/review` | POST | Mark post as reviewed |
| `/bulk-review` | POST | Bulk mark posts as reviewed |
| `/settings` | GET | Get plugin settings (admin only) |

Authentication required with a user who has `edit_posts` capability.

### OpenAPI Specification

The plugin includes a complete OpenAPI 3.0 specification (`openapi.yaml`) for API documentation and client generation. You can:

- Import into [Swagger Editor](https://editor.swagger.io/) for interactive documentation
- Generate API clients using [OpenAPI Generator](https://openapi-generator.tech/)
- Use with Postman, Insomnia, or other API tools

## WP-CLI Commands

```bash
# Display freshness statistics
wp content-freshness stats

# List stale posts
wp content-freshness list
wp content-freshness list --format=json --orderby=date --order=asc

# Check specific post
wp content-freshness check 123

# Mark posts as reviewed
wp content-freshness review 123 456 789

# Export to CSV
wp content-freshness export --file=stale-posts.csv

# Send test email
wp content-freshness send-test-email

# View settings
wp content-freshness settings
```

## Multisite

For WordPress Multisite installations, super admins will see a **Content Freshness** menu in the Network Admin dashboard with:
- Network-wide statistics across all sites
- Per-site breakdown with fresh/aging/stale counts
- Sites sorted by stale content count
- Quick links to each site's dashboard

## Development

### Requirements
- PHP 7.4+
- WordPress 5.0+
- Composer (for development)

### Setup
```bash
# Clone the repository
git clone https://github.com/caltechweb/content-freshness-monitor.git
cd content-freshness-monitor

# Install dependencies
composer install

# Run tests
composer test

# Check coding standards
composer phpcs

# Fix coding standards
composer phpcs:fix

# Run static analysis
composer phpstan

# Security audit
composer security-check
```

### Testing
The plugin includes a PHPUnit test suite. To run tests:

1. Install the WordPress test suite:
   ```bash
   bin/install-wp-tests.sh wordpress_test root '' localhost latest
   ```

2. Run the tests:
   ```bash
   composer test
   ```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a detailed version history.

### Latest: v2.7.1
- Enhanced screenshot descriptions in readme.txt
- Developer extensibility hooks (filters and actions)
- HOOKS.md developer documentation
- Complete WordPress.org submission checklist
- Branding and screenshot guidelines

## License

This plugin is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Roadmap

### Phase 1: Foundation (Current)
- [x] Core content freshness monitoring
- [x] Configurable staleness thresholds
- [x] Email notification system
- [x] Admin dashboard and statistics
- [x] REST API and WP-CLI support
- [x] Gutenberg block editor integration
- [x] Multisite network support
- [x] Historical trend tracking

### Phase 2: WooCommerce Integration
- [ ] Product-specific freshness rules
- [ ] Inventory and pricing change detection
- [ ] Seasonal product flagging
- [ ] Product category monitoring

### Phase 3: Claude AI Integration
Intelligent content management powered by Claude AI:

- [ ] Smart content analysis and recommendations
- [ ] AI-assisted content refresh suggestions
- [ ] Intelligent priority scoring
- [ ] Automated content insights
- [ ] Natural language content queries
- [ ] Predictive staleness detection

*AI features coming soon*

### Phase 4: Team Workflow
- [ ] Content assignment system
- [ ] Due date tracking
- [ ] Team notifications (Slack, Teams)
- [ ] Editorial calendar integration

### Phase 5: Analytics & Reporting
- [ ] Traffic correlation for stale content
- [ ] Google Analytics integration
- [ ] Custom report generation
- [ ] Performance impact scoring

## Support

- Email: support@caltechweb.com
- [GitHub Issues](https://github.com/caltechweb/content-freshness-monitor/issues)
- [WordPress.org Support Forum](https://wordpress.org/support/plugin/content-freshness-monitor/)
