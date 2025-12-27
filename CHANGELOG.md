# Changelog

All notable changes to Content Freshness Monitor will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.8.1] - 2025-12-27

### Fixed
- Fixed Last Modified column styling in stale content table for better visual alignment
- Date information now displays with consistent font sizing and vertical centering

## [2.8.0] - 2025-12-27

### Added
- Configurable date check option in settings: choose between Last Modified Date, Publish Date, or Oldest Date
- Posts published long ago can now be flagged as stale based on their publish date
- Helper methods for building date queries based on settings

### Changed
- Bundled Chart.js locally instead of loading from CDN for better reliability and WordPress.org compliance
- Improved email notification styling with table-based layout for better email client compatibility
- Stat boxes in emails are now centered with improved visual appearance
- Enhanced link visibility in email notifications

## [2.7.1] - 2025-12-27

### Changed
- Enhanced WordPress.org screenshot descriptions with detailed feature explanations
- Expanded screenshot section from 4 to 8 screenshots covering all major features

## [2.7.0] - 2025-12-27

### Added
- Developer extensibility hooks for customization and integration
  - `cfm_post_data` filter: Customize post data in stale content listings
  - `cfm_stats` filter: Modify or extend content statistics
  - `cfm_health_score` filter: Customize health score calculation and grading
  - `cfm_digest_email_args` filter: Modify digest email recipient, subject, body, headers
  - `cfm_post_reviewed` action: Trigger custom logic when a post is marked as reviewed
  - `cfm_bulk_posts_reviewed` action: Trigger custom logic after bulk review operations
  - `cfm_digest_email_sent` action: Execute actions after digest email is sent
- HOOKS.md developer documentation with complete examples for all hooks

## [2.6.0] - 2025-12-27

### Added
- WordPress Playground blueprint for instant browser-based demo
  - blueprint.json for one-click testing in WordPress Playground
  - Sample content with various ages (fresh, aging, stale posts)
  - Direct link to Content Freshness dashboard after launch
  - "Try It Now" button in README.md

## [2.5.0] - 2025-12-27

### Added
- OpenAPI 3.0 specification (openapi.yaml) for REST API documentation
  - Complete schema definitions for all 6 REST endpoints
  - Request/response examples for all operations
  - Security scheme documentation (cookie auth, application passwords)
  - Error response schemas (401, 403, 404)
  - Pagination header documentation (X-WP-Total, X-WP-TotalPages)
  - Compatible with Swagger Editor, OpenAPI Generator, Postman, Insomnia

## [2.4.0] - 2025-12-27

### Added
- Integration test suite (tests/test-integration.php) with 32 test cases covering:
  - Plugin singleton pattern and instance type validation
  - Plugin constants verification (CFM_VERSION, CFM_PLUGIN_DIR, etc.)
  - Activation tests (default options, staleness days, post types, email settings)
  - Activation preservation of existing settings
  - Settings integration tests (get_settings, get_threshold_for_type)
  - End-to-end content freshness tests (fresh/stale detection, exclusions, mark as reviewed)
  - Stats and health score calculation verification
  - Freshness status value validation
  - Class loading tests (all 12 include classes)
  - REST API endpoint registration verification
  - Shortcode registration verification
  - Deactivation tests (cron clearing, settings preservation, trends history preservation)
  - Caching tests (stats caching, cache invalidation on post save)

## [2.3.9] - 2025-12-27

### Added
- Unit tests for CFM_Gutenberg class (38 test cases)
  - Class existence and method callable tests
  - Constructor hook registration tests (enqueue_block_editor_assets, rest_api_init)
  - register_meta tests (meta key registration, type, single, show_in_rest)
  - Meta auth_callback tests (edit_posts capability required)
  - enqueue_editor_assets tests (returns without post, non-monitored type handling)
  - Script and style enqueuing verification
  - Localized script data tests (postId, daysOld, threshold, status, nonce)
  - URL data tests (ajaxUrl, restUrl, settingsUrl)
  - REST nonce inclusion tests
  - i18n translation string tests (title, status labels, action labels)
  - statusClass and lastModified data tests
  - lastReviewed meta handling tests
  - Script dependencies verification (wp-plugins, wp-edit-post, wp-element, etc.)
  - Fresh/stale post status detection tests
  - Page post type meta registration tests

## [2.3.8] - 2025-12-27

### Added
- Unit tests for CFM_Multisite class (38 test cases)
  - Static method tests (is_multisite returns boolean, matches core function)
  - Constructor hook registration tests (network_admin_menu, admin_enqueue_scripts, AJAX handler)
  - get_network_stats tests (structure, numeric values, non-negative, valid percentage, total calculation)
  - get_sites_freshness_data tests (returns array, structure, numeric values, valid URLs, admin_url page, sorted by stale)
  - get_network_stale_posts tests (returns array, respects limit, structure, sorted by days_old)
  - enqueue_network_assets tests (only loads on correct page)
  - render_network_dashboard tests (wrapper div, heading, stats grid, sites table, stat labels, columns, no sites message)
  - Method callable verification tests
  - Network menu capability tests
  - AJAX handler nonce action tests

## [2.3.7] - 2025-12-27

### Added
- Unit tests for CFM_Dashboard_Widget class (29 test cases)
  - Constructor and hook registration tests
  - Capability checks (subscribers denied, editors allowed)
  - Widget ID and title validation
  - Widget rendering tests (wrapper, health score, stats sections)
  - Health grade ARIA accessibility tests
  - Success state tests (all content fresh message and icon)
  - Stale content state tests (Needs Attention, progress bar, post list)
  - Stale post display tests (title, age badge, edit link)
  - Post limit enforcement (max 5 posts displayed)
  - Fresh percentage message display

## [2.3.6] - 2025-12-27

### Added
- Unit tests for CFM_Admin class (27 test cases)
  - Hook registration tests (admin_menu, admin_init)
  - Menu page creation and capability tests
  - Post list column tests (add, render, sortable)
  - Freshness column position and label tests
  - Admin page output tests (wrapper, stats cards, accessibility)
  - No-stale message display tests
  - Stale content table rendering tests
  - Parameter validation tests (orderby allowlist, order sanitization, paged sanitization)
  - Settings link and export button tests

## [2.3.5] - 2025-12-27

### Added
- Unit tests for CFM_Trends class (25 test cases)
  - History storage and retrieval tests
  - MAX_DATA_POINTS limit enforcement tests
  - Cron scheduling tests (schedule_snapshot, no duplicate events)
  - Snapshot recording tests (data structure, field validation)
  - clear_history cleanup tests (option deletion, cron unscheduling)
  - render_chart output tests (HTML structure, accessibility attributes)
  - Time range button tests (7/30/90 days)
  - Legend label tests (Fresh/Stale content)

## [2.3.4] - 2025-12-27

### Added
- Unit tests for CFM_Export class (16 test cases)
  - Export URL generation and validation tests
  - Nonce verification tests
  - Query parameter validation tests
  - Capability requirement tests (edit_posts)
  - Filename format validation
  - Handler hook registration tests

## [2.3.3] - 2025-12-27

### Added
- WP-CLI unit tests for CFM_CLI_Command class (35 test cases)
  - Stats command tests (data structure, numeric values, total calculation)
  - List command tests (structure, limit, orderby, post_type filter, IDs format)
  - Check command tests (existing/non-existent post, freshness status, is_stale flag)
  - Review command tests (single post, multiple posts, post meta updates)
  - Export command tests (data structure, author display name, URLs)
  - Settings command tests (all settings, threshold, post_types, email, formatting)
  - Send test email command tests (default recipient)

## [2.3.2] - 2025-12-27

### Added
- Unit tests for CFM_Notifications class (26 test cases)
  - Cron scheduling tests (daily, weekly, monthly, disabled)
  - Admin digest tests (respects disabled setting, no stale content scenario)
  - Author digest tests (respects disabled setting, minimum threshold)
  - Email content tests (subject includes count, body includes site name)
  - Test email functionality tests ([TEST] prefix, HTML content type)
  - Custom recipient configuration tests
  - AJAX handler security tests (nonce, capability checks)

## [2.3.1] - 2025-12-27

### Added
- Unit tests for shortcodes feature (23 test cases)
  - Authorization tests for all 3 shortcodes (edit_posts capability required)
  - Attribute validation tests (size, show_score, show_label, layout, format)
  - Output escaping and XSS protection tests
  - Anonymous user access tests

## [2.3.0] - 2025-12-27

### Added
- **Shortcodes for Frontend Display** - Display content freshness information on posts and pages
  - `[content_health_score]` - Display the Content Health Score grade badge
    - Attributes: `show_score` (yes/no), `show_label` (yes/no), `size` (small/medium/large)
  - `[content_freshness_stats]` - Display content freshness statistics
    - Attributes: `layout` (horizontal/vertical/compact), `show_percentage`, `show_total`, `show_threshold`
  - `[stale_content_count]` - Display just the number of stale posts
    - Attributes: `format` (number/text)
- New frontend CSS stylesheet with responsive design and gradient-styled badges
- All shortcodes respect user capabilities (edit_posts required to view)
- Size variations for health score widget (small, medium, large)
- Layout options for stats widget (horizontal, vertical, compact)

## [2.2.0] - 2025-12-27

### Added
- **Author-Specific Notifications** - Personalized email digests sent directly to content authors
- Each author receives only their own stale content list (not the entire site's stale posts)
- Beautiful HTML email template with personalized greeting, stale post list with edit links, and helpful tips
- Separate cron schedule for author notifications (can be daily, weekly, or monthly)
- Configurable minimum stale post threshold (only email authors with X+ stale posts)
- New "Author Notifications" settings section with toggle, frequency, and minimum threshold options
- Automatic cleanup of author notification cron on plugin uninstall

### Changed
- Settings page now has separate sections for Admin Notifications and Author Notifications
- Deactivation hook now properly clears author notification scheduled events

## [2.1.0] - 2025-12-27

### Added
- **Per-Type Staleness Thresholds** - Set different staleness thresholds for each content type
- New "Per-Type Thresholds" settings section with toggle to enable custom thresholds
- Table interface showing all monitored post types with customizable threshold fields
- Helpful hints for common content types (blog posts need frequent updates, pages stay relevant longer)
- Per-type threshold is used in all freshness calculations: stale posts list, stats, health score, status labels

### Changed
- Scanner now respects per-type thresholds when calculating stale content
- `get_freshness_status()` now accepts optional post_type parameter for accurate per-type status
- `is_stale()` now returns the threshold used in its response
- Stats response includes `per_type` boolean indicating if per-type mode is active

## [2.0.2] - 2025-12-27

### Fixed
- Deactivation hook no longer deletes trends history data (data is now preserved when plugin is deactivated)
- Historical trends data is only deleted on full plugin uninstall, not on temporary deactivation

## [2.0.1] - 2025-12-27

### Added
- Unit tests for Content Health Score feature (10 new test cases)
  - Tests for all grade boundaries (A through F)
  - Tests for empty site edge case
  - Tests for score/grade/label/class structure validation
  - Boundary condition tests for 89%/90% threshold

## [2.0.0] - 2025-12-27

### Added
- **Content Health Score** - At-a-glance letter grade (A-F) and numeric score (0-100) for overall content health
- Health score prominently displayed in dashboard widget
- Health score included in REST API `/stats` endpoint (`health_score`, `health_grade`, `health_label`)
- Grades: A (90-100% fresh, Excellent), B (80-89%, Good), C (70-79%, Fair), D (60-69%, Poor), F (<60%, Critical)
- Beautiful gradient styling for grade badges

### Changed
- Dashboard widget now shows Content Health Score as primary metric
- Quick stats now show Fresh, Stale, and Total counts

## [1.9.0] - 2025-12-27

### Added
- **Content Freshness Trends** - Visual chart showing how content health changes over time
- Daily snapshot recording of fresh/stale content counts
- Interactive Chart.js visualization on the admin dashboard
- Time range selector (7, 30, or 90 days)
- Automatic trend data collection via WP Cron
- AJAX-powered chart updates without page reload
- Color-coded legend for fresh (green) and stale (red) content lines

### Improved
- Site owners can now track whether their content is getting fresher or staler over time
- Visual representation helps identify content maintenance trends
- Supports up to 90 days of historical data

## [1.8.0] - 2025-12-27

### Added
- PHPStan static analysis configuration (level 6)
- szepeviktor/phpstan-wordpress extension for WordPress-aware analysis
- `composer phpstan` script for running static analysis
- Static analysis job in GitHub Actions CI pipeline

### Developer Experience
- Catch type errors and bugs before runtime with PHPStan
- Integrates with CI/CD for automated static analysis on every push

## [1.7.1] - 2025-12-27

### Added
- composer.json for development dependencies (PHPUnit, PHPCS, WordPress Coding Standards)
- Composer scripts for testing, linting, and security audits

### Developer Experience
- `composer test` - Run PHPUnit test suite
- `composer phpcs` - Check code against WordPress Coding Standards
- `composer phpcs:fix` - Auto-fix coding standards violations
- `composer security-check` - Audit dependencies for vulnerabilities

## [1.7.0] - 2025-12-27

### Added
- Performance optimization with transient caching for large sites
- Stats are now cached for 15 minutes, dramatically improving performance on sites with thousands of posts
- Cache is automatically invalidated when posts are created, updated, deleted, trashed, or restored
- Cache is also cleared when plugin settings are changed
- Added `cached_at` timestamp to stats response for cache visibility
- Optimized WP_Query calls with `update_post_meta_cache` and `update_post_term_cache` disabled

## [1.6.1] - 2025-12-27

### Improved
- Added ARIA labels and roles for better screen reader support
- Added live region announcements for AJAX actions
- Enhanced keyboard accessibility for bulk actions toolbar
- Added aria-sort attribute to sortable table columns
- Added semantic `<time>` element for dates
- Added `rel="noopener"` to external links for security

## [1.6.0] - 2025-12-27

### Added
- WordPress Multisite support with network admin dashboard
- Network-wide content freshness statistics aggregation
- Per-site freshness breakdown table in network admin
- Quick links to individual site dashboards from network view
- Network admin menu item for super admins
- Sorted sites by stale content count (most problematic first)
- AJAX refresh for network statistics

## [1.5.0] - 2025-12-27

### Added
- Gutenberg block editor sidebar panel
- Real-time freshness status display in the post editor
- Color-coded status indicator (Fresh/Aging/Stale)
- Days since update counter with live updates
- "Mark as Reviewed" button directly in the editor
- Link to plugin settings from sidebar
- Dark mode support for editor sidebar

## [1.4.0] - 2025-12-27

### Added
- WP-CLI integration for command-line management
- `wp content-freshness stats` - Display freshness statistics
- `wp content-freshness list` - List stale posts with filtering and sorting
- `wp content-freshness check <post_id>` - Check freshness of specific post
- `wp content-freshness review <post_id>...` - Mark post(s) as reviewed
- `wp content-freshness export` - Export stale posts to CSV
- `wp content-freshness send-test-email` - Send test notification email
- `wp content-freshness settings` - Show current plugin settings
- Multiple output formats (table, json, csv, yaml) for all commands

## [1.3.0] - 2025-12-27

### Added
- REST API endpoints for headless CMS and external integrations
- `GET /wp-json/content-freshness-monitor/v1/stats` - Get content freshness statistics
- `GET /wp-json/content-freshness-monitor/v1/stale` - List stale posts with pagination
- `GET /wp-json/content-freshness-monitor/v1/post/{id}/freshness` - Check freshness of specific post
- `POST /wp-json/content-freshness-monitor/v1/post/{id}/review` - Mark post as reviewed
- `POST /wp-json/content-freshness-monitor/v1/bulk-review` - Bulk mark posts as reviewed
- `GET /wp-json/content-freshness-monitor/v1/settings` - Get plugin settings (admin only)
- Pagination headers (X-WP-Total, X-WP-TotalPages) on list endpoints
- Permission callbacks with capability checks for all endpoints

## [1.2.0] - 2025-12-27

### Added
- CSV export functionality for stale content reports
- Export button on the Content Freshness admin page
- Exports include: ID, title, type, author, last modified, days since modified, last reviewed, edit URL, view URL
- UTF-8 BOM for Excel compatibility
- Secure export with nonce verification and capability checks

## [1.1.0] - 2025-12-27

### Added
- Email notification system for stale content alerts
- Scheduled digest emails (daily, weekly, or monthly frequency)
- Custom email recipient option (defaults to admin email)
- Send test email button in settings page
- WP Cron integration for scheduled notifications
- Beautiful HTML email template with content statistics

### Changed
- Updated uninstall handler to clear scheduled cron events

## [1.0.0] - 2025-12-27

### Added
- Initial release
- Dashboard widget showing stale content count
- Dedicated admin page for viewing all stale content
- Freshness status column in Posts list table
- "Mark as Reviewed" functionality (single and bulk actions)
- Configurable staleness threshold (default: 180 days)
- Support for multiple post types
- Post exclusion by ID
- Full internationalization support with POT file
- Clean uninstall handler that removes all plugin data
- Security features: nonce verification, capability checks, input sanitization, output escaping
