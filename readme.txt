=== Content Freshness Monitor ===
Contributors: bhopctw
Tags: content, freshness, seo, stale content, content audit
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitor and manage stale content on your WordPress site. Get visibility into which posts need updating.

== Description ==

Content Freshness Monitor helps you identify and manage outdated content on your WordPress site. Stale content can hurt your SEO rankings and user trust - this plugin gives you the visibility you need to keep your content fresh.

**Features:**

* **Content Health Score** - At-a-glance letter grade (A-F) showing your overall content health
* **Stale Content Detection** - Automatically identifies posts that haven't been updated within your configured timeframe
* **Email Notifications** - Receive scheduled digest emails (daily, weekly, or monthly) about stale content
* **Author Notifications** - Send personalized stale content emails directly to each author about their own posts
* **Dashboard Widget** - Quick overview of your content freshness right on your WordPress dashboard
* **Admin Dashboard** - Dedicated page showing all stale content with sorting and pagination
* **Post List Integration** - See freshness status directly in your Posts list
* **Mark as Reviewed** - Track which content you've reviewed, even if you didn't need to modify it
* **Configurable Threshold** - Set your own staleness threshold (default: 6 months)
* **Per-Type Thresholds** - Different content types can have different staleness thresholds (e.g., 90 days for posts, 365 days for pages)
* **Multi Post Type Support** - Monitor posts, pages, and custom post types
* **Bulk Actions** - Mark multiple posts as reviewed at once
* **CSV Export** - Export stale content list to CSV for reporting and analysis
* **REST API** - Full REST API for headless CMS and external integrations
* **WP-CLI Integration** - Manage content freshness from the command line
* **Block Editor Integration** - See freshness status directly in Gutenberg sidebar
* **Multisite Support** - Network-wide content freshness dashboard for super admins
* **Trends Visualization** - Chart showing content freshness trends over time (7, 30, or 90 days)
* **Shortcodes** - Display content health score and stats on your posts and pages

**Why Content Freshness Matters:**

* Search engines favor regularly updated content
* Outdated information erodes user trust
* Dead links and old references create poor user experience
* Regular content audits improve overall site quality

== Installation ==

1. Upload the `content-freshness-monitor` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under Settings > Content Freshness
4. View stale content under the Content Freshness menu item

== Frequently Asked Questions ==

= What counts as "stale" content? =

By default, any published post that hasn't been modified in 180 days (6 months) is considered stale. You can change this threshold in the settings.

= Does this plugin modify my content? =

No. The plugin only reads your content to analyze freshness. The "Mark as Reviewed" feature adds a metadata timestamp but doesn't change the content itself.

= Can I exclude certain posts? =

Yes, you can enter specific post IDs to exclude from monitoring in the settings.

= Does it work with custom post types? =

Yes, you can select which post types to monitor in the settings page.

= Can I receive email notifications about stale content? =

Yes! Enable email notifications in Settings > Content Freshness under the "Email Notifications" section. You can choose to receive digests daily, weekly, or monthly. The email includes a summary of stale content with direct links to edit them.

= Can individual authors receive notifications about their own stale content? =

Yes! Enable "Author Notifications" in the settings to send personalized emails to each author about their own stale posts. This is great for multi-author sites where you want to distribute the content maintenance workload. You can set a minimum threshold (e.g., only email authors with 3+ stale posts).

= Can I display content freshness information on my site? =

Yes! Version 2.3.0 adds shortcodes for displaying content freshness on your posts and pages:

* `[content_health_score]` - Displays the Content Health Score grade badge
  * `show_score="yes"` - Show percentage score (default: yes)
  * `show_label="yes"` - Show label like "Excellent" (default: yes)
  * `size="medium"` - Size: small, medium, or large (default: medium)

* `[content_freshness_stats]` - Displays content statistics
  * `layout="horizontal"` - Layout: horizontal, vertical, or compact
  * `show_percentage="yes"` - Show stale percentage
  * `show_total="yes"` - Show total post count
  * `show_threshold="no"` - Show threshold days

* `[stale_content_count]` - Displays just the stale post count
  * `format="number"` - Format: number or text (e.g., "5 stale posts")

Note: Shortcodes are only visible to logged-in users with `edit_posts` capability for security.

== Screenshots ==

1. Main Content Freshness dashboard showing stale content list with bulk actions, stats cards, and export functionality.
2. Dashboard widget displaying Content Health Score grade (A-F), stale post count, and quick stats at a glance.
3. Settings page with configurable threshold, post type selection, exclusions, email notifications, and per-type thresholds.
4. Freshness column in the Posts list showing days since last update with color-coded status badges.
5. Gutenberg sidebar panel showing content freshness status and "Mark as Reviewed" button in the block editor.
6. Content freshness trends chart visualizing fresh vs. stale content over time (7, 30, or 90 days).
7. Network admin dashboard for WordPress Multisite showing per-site content freshness breakdown.
8. Email digest notification with stale content summary and direct edit links.

= Can I export the stale content list? =

Yes! Click the "Export to CSV" button on the Content Freshness page. The CSV file includes all stale posts with their details, perfect for sharing with stakeholders or importing into spreadsheets.

= Is there a REST API? =

Yes! Version 1.3.0 adds a full REST API for headless WordPress and external integrations. Available endpoints:

* `GET /wp-json/content-freshness-monitor/v1/stats` - Get content statistics
* `GET /wp-json/content-freshness-monitor/v1/stale` - List stale posts (paginated)
* `GET /wp-json/content-freshness-monitor/v1/post/{id}/freshness` - Check specific post freshness
* `POST /wp-json/content-freshness-monitor/v1/post/{id}/review` - Mark post as reviewed
* `POST /wp-json/content-freshness-monitor/v1/bulk-review` - Bulk mark posts as reviewed
* `GET /wp-json/content-freshness-monitor/v1/settings` - Get plugin settings (admin only)

All endpoints require authentication with a user who has `edit_posts` capability (or `manage_options` for settings).

= Is there a WP-CLI command? =

Yes! Version 1.4.0 adds full WP-CLI support for command-line management. Available commands:

* `wp content-freshness stats` - Display freshness statistics
* `wp content-freshness list` - List stale posts with filtering and sorting options
* `wp content-freshness check <post_id>` - Check freshness of a specific post
* `wp content-freshness review <post_id>...` - Mark one or more posts as reviewed
* `wp content-freshness export` - Export stale posts to CSV file
* `wp content-freshness send-test-email` - Send a test notification email
* `wp content-freshness settings` - Show current plugin settings

All commands support multiple output formats: table, json, csv, yaml.

= Does it work with WordPress Multisite? =

Yes! Version 1.6.0 adds full Multisite support. Super admins will see a "Content Freshness" menu in the Network Admin dashboard with:

* Network-wide statistics (total posts, stale posts across all sites)
* Per-site breakdown table showing fresh, aging, and stale content counts
* Sites sorted by stale content count (most problematic sites first)
* Quick links to each site's individual Content Freshness dashboard

Individual site admins can still access their site-specific dashboards as usual.

= Does it perform well on large sites? =

Yes! Version 1.7.0 adds intelligent caching for improved performance on sites with thousands of posts. Statistics are cached for 15 minutes and automatically invalidated when posts are created, updated, deleted, or when settings change. This dramatically reduces database queries on high-traffic sites.

== Changelog ==

= 2.8.1 =
* Fixed: Last Modified column styling for better visual alignment
* Fixed: Date information now displays with consistent font sizing

= 2.8.0 =
* New: Configurable date check option - choose between Last Modified Date, Publish Date, or Oldest Date
* New: Posts published long ago can now be flagged as stale based on publish date
* Improved: Email notification styling with centered stat boxes and better link visibility
* Changed: Bundled Chart.js locally instead of loading from CDN for better reliability

= 2.7.0 =
* New: Developer extensibility hooks for customization and integration
* New: `cfm_post_data` filter to customize post data in stale content listings
* New: `cfm_stats` filter to modify or extend content statistics
* New: `cfm_health_score` filter to customize health score calculation
* New: `cfm_digest_email_args` filter to modify digest email settings
* New: `cfm_post_reviewed` action fired when a post is marked as reviewed
* New: `cfm_bulk_posts_reviewed` action fired after bulk review operations
* New: `cfm_digest_email_sent` action fired after digest email is sent
* New: HOOKS.md developer documentation with complete examples

= 2.6.0 =
* New: WordPress Playground blueprint for instant browser-based demo
* New: blueprint.json for one-click testing without installation
* New: Sample content with various ages to showcase all features
* New: "Try It Now" button in README.md for quick access

= 2.5.0 =
* Added: OpenAPI 3.0 specification (openapi.yaml) for REST API documentation
* Added: Complete schema definitions for all 6 REST endpoints
* Added: Request/response examples for all API operations
* Added: Security scheme documentation (cookie auth, application passwords)
* Added: Error response schemas and pagination header documentation
* Compatible with Swagger Editor, OpenAPI Generator, Postman, Insomnia

= 2.4.0 =
* Added: Integration test suite with 32 test cases
* Added: Plugin singleton pattern and instance type validation tests
* Added: Plugin constants verification tests
* Added: Activation tests (default options, staleness days, post types, email settings)
* Added: Settings integration tests (get_settings, get_threshold_for_type)
* Added: End-to-end content freshness tests
* Added: Class loading and REST API endpoint registration tests
* Added: Deactivation tests (cron clearing, settings preservation)
* Added: Caching tests (stats caching, cache invalidation)

= 2.3.9 =
* Added: Unit tests for CFM_Gutenberg class (38 test cases)
* Added: Constructor hook registration tests (enqueue_block_editor_assets, rest_api_init)
* Added: Meta registration tests (type, single, show_in_rest, auth_callback)
* Added: Editor assets enqueuing tests for monitored post types
* Added: Localized script data tests (postId, status, threshold, nonces, URLs)
* Added: i18n translation string tests (status labels, action labels)
* Added: Fresh/stale post status detection tests

= 2.3.8 =
* Added: Unit tests for CFM_Multisite class (38 test cases)
* Added: Network stats tests (structure, numeric values, calculations)
* Added: Sites freshness data tests (structure, URLs, sorting)
* Added: Network stale posts tests (limit, structure, sorting)
* Added: Dashboard rendering tests (stats grid, sites table, accessibility)
* Added: Hook registration and capability tests

= 2.3.7 =
* Added: Unit tests for CFM_Dashboard_Widget class (29 test cases)
* Added: Constructor and hook registration tests
* Added: Capability and permission tests (subscribers, editors)
* Added: Widget rendering tests for all display states
* Added: Accessibility tests for ARIA labels

= 2.3.6 =
* Added: Unit tests for CFM_Admin class (27 test cases)
* Added: Hook registration tests (admin_menu, admin_init)
* Added: Post list column tests (add, render, sortable)
* Added: Admin page output and accessibility tests
* Added: Parameter validation tests (orderby allowlist, sanitization)

= 2.3.5 =
* Added: Unit tests for CFM_Trends class (25 test cases)
* Added: History storage and retrieval tests
* Added: Snapshot recording and MAX_DATA_POINTS tests
* Added: Chart rendering and accessibility tests

= 2.3.4 =
* Added: Unit tests for CFM_Export class (16 test cases)
* Added: Export URL generation and validation tests
* Added: Nonce verification tests
* Added: Capability requirement tests (edit_posts)

= 2.3.3 =
* Added: WP-CLI unit tests for CFM_CLI_Command class (35 test cases)
* Added: Stats command tests for data structure and calculations
* Added: List command tests for filtering, ordering, and output formats
* Added: Check/Review command tests for post operations
* Added: Export/Settings command tests for data output

= 2.3.2 =
* Added: Unit tests for CFM_Notifications class (26 test cases)
* Added: Cron scheduling tests for daily, weekly, monthly, and disabled modes
* Added: Admin digest tests for respecting disabled setting and no stale content
* Added: Author digest tests for minimum threshold enforcement
* Added: Email content validation tests (subject, body, HTML format)
* Added: AJAX handler security tests (nonce and capability checks)

= 2.3.1 =
* Added: Unit tests for shortcodes feature (23 test cases)
* Added: Authorization tests verifying edit_posts capability requirement
* Added: Attribute validation tests for all shortcode options
* Added: XSS protection tests for shortcode output escaping

= 2.3.0 =
* New: Shortcodes for frontend display of content freshness information
* New: `[content_health_score]` shortcode with size and display options
* New: `[content_freshness_stats]` shortcode with layout and display options
* New: `[stale_content_count]` shortcode for simple stale post count
* New: Frontend CSS stylesheet with responsive design and gradient badges
* Security: Shortcodes respect user capabilities (edit_posts required)

= 2.2.0 =
* New: Author-specific notifications - personalized emails sent directly to each content author
* New: Authors receive only their own stale content list (not the entire site's)
* New: Beautiful HTML email template with personalized greeting and helpful tips
* New: Configurable minimum stale post threshold for author emails
* New: Separate frequency settings for author notifications (daily, weekly, monthly)
* Improved: Settings page now has separate sections for Admin and Author notifications

= 2.1.0 =
* New: Per-type staleness thresholds - set different thresholds for each content type
* New: Enable/disable per-type mode with a simple toggle
* New: Table interface showing all monitored content types with customizable threshold fields
* Improved: Scanner respects per-type thresholds when calculating stale content
* Improved: Status labels use correct threshold for each content type

= 2.0.0 =
* New: Content Health Score - At-a-glance letter grade (A-F) for overall content health
* New: Health score prominently displayed in dashboard widget
* New: Health score included in REST API /stats endpoint
* New: Grades range from A (Excellent, 90-100%) to F (Critical, <60%)
* New: Beautiful gradient styling for grade badges
* Changed: Dashboard widget now shows Content Health Score as primary metric
* Changed: Quick stats now show Fresh, Stale, and Total counts

= 1.9.0 =
* New: Content Freshness Trends visualization with Chart.js
* New: Daily snapshot recording of fresh/stale content counts
* New: Interactive chart on admin dashboard showing trends over time
* New: Time range selector (7, 30, or 90 days)
* New: AJAX-powered chart updates without page reload
* New: Color-coded legend for fresh and stale content lines
* Improved: Visual tracking of whether content health is improving or declining

= 1.8.0 =
* New: PHPStan static analysis configuration (level 6)
* New: WordPress-aware PHPStan extension for better type checking
* New: `composer phpstan` script for running static analysis
* Dev: Static analysis job added to GitHub Actions CI pipeline
* Dev: Catch type errors and bugs before runtime

= 1.7.1 =
* New: composer.json for development dependencies (PHPUnit, PHPCS, WordPress Coding Standards)
* New: Composer scripts for testing, linting, and security audits
* Dev: `composer test` - Run PHPUnit test suite
* Dev: `composer phpcs` - Check code against WordPress Coding Standards

= 1.7.0 =
* New: Performance optimization with transient caching for large sites
* New: Stats cached for 15 minutes for dramatically faster page loads
* New: Automatic cache invalidation when posts are modified
* New: Cache cleared when plugin settings change
* Improved: Optimized WP_Query calls with disabled post meta and term caches
* Improved: Clean cache removal on plugin uninstall

= 1.6.1 =
* Improved: Added ARIA labels and roles for better screen reader support
* Improved: Added live region announcements for AJAX actions
* Improved: Enhanced keyboard accessibility for bulk actions
* Improved: Added aria-sort attribute to sortable table columns
* Improved: Added semantic time element for dates
* Improved: Added rel="noopener" to external links

= 1.6.0 =
* New: WordPress Multisite support with network admin dashboard
* New: Network-wide content freshness statistics
* New: Per-site freshness breakdown table
* New: Quick links to individual site dashboards
* New: Sites sorted by stale content count
* Improved: Super admin visibility across the entire network

= 1.5.0 =
* New: Gutenberg block editor sidebar panel
* New: Real-time freshness status display while editing
* New: Color-coded status indicator (Fresh/Aging/Stale)
* New: "Mark as Reviewed" button in editor sidebar
* New: Quick access to plugin settings from editor
* Improved: Dark mode support for editor sidebar

= 1.4.0 =
* New: WP-CLI integration for command-line management
* New: `wp content-freshness stats` - Display freshness statistics
* New: `wp content-freshness list` - List stale posts with filtering
* New: `wp content-freshness check` - Check specific post freshness
* New: `wp content-freshness review` - Mark posts as reviewed
* New: `wp content-freshness export` - Export to CSV from CLI
* New: `wp content-freshness send-test-email` - Test email notifications
* New: `wp content-freshness settings` - View current configuration
* Improved: Multiple output formats (table, json, csv, yaml)

= 1.3.0 =
* New: REST API endpoints for headless CMS and external integrations
* New: GET /stats - Content freshness statistics
* New: GET /stale - Paginated stale content list with sorting
* New: GET /post/{id}/freshness - Check specific post freshness status
* New: POST /post/{id}/review - Mark post as reviewed via API
* New: POST /bulk-review - Bulk mark posts as reviewed
* New: GET /settings - Retrieve plugin configuration (admin only)
* Improved: Standard WordPress REST API authentication and permissions

= 1.2.0 =
* New: CSV export functionality for stale content reports
* New: Export button on the Content Freshness admin page
* Improved: Export includes comprehensive post data (ID, title, type, author, dates, URLs)

= 1.1.0 =
* New: Email notifications for stale content digests
* New: Configurable email frequency (daily, weekly, monthly)
* New: Custom email recipient option
* New: Send test email button in settings
* Improved: WP Cron integration for scheduled notifications

= 1.0.0 =
* Initial release
* Stale content detection
* Admin dashboard
* Dashboard widget
* Settings page
* Mark as reviewed functionality
* Post list column integration

== Roadmap ==

= Phase 1: Foundation (Current) =
* Core content freshness monitoring
* Configurable staleness thresholds
* Email notification system
* Admin dashboard and statistics
* REST API and WP-CLI support
* Gutenberg block editor integration
* Multisite network support
* Historical trend tracking

= Phase 2: WooCommerce Integration =
* Product-specific freshness rules
* Inventory and pricing change detection
* Seasonal product flagging
* Product category monitoring

= Phase 3: Claude AI Integration =
Intelligent content management powered by Claude AI:

* Smart content analysis and recommendations
* AI-assisted content refresh suggestions
* Intelligent priority scoring
* Automated content insights
* Natural language content queries
* Predictive staleness detection

*AI features coming soon*

= Phase 4: Team Workflow =
* Content assignment system
* Due date tracking
* Team notifications (Slack, Teams)
* Editorial calendar integration

= Phase 5: Analytics & Reporting =
* Traffic correlation for stale content
* Google Analytics integration
* Custom report generation
* Performance impact scoring

== Upgrade Notice ==

= 2.1.0 =
New feature! Set different staleness thresholds for each content type. Blog posts can go stale after 90 days while pages stay fresh for a year.

= 2.0.0 =
Major update! See your Content Health Score at a glance with a letter grade (A-F) on the dashboard widget. Know instantly if your site content is healthy.

= 1.9.0 =
New trends visualization! See how your content freshness changes over time with an interactive Chart.js chart on the admin dashboard.

= 1.8.0 =
Developer experience update! Added PHPStan static analysis for catching type errors and bugs before runtime.

= 1.7.1 =
Developer experience update! Added composer.json for easy setup of development dependencies and code quality tools.

= 1.7.0 =
Performance improvements! Stats are now cached, making the plugin significantly faster on sites with thousands of posts.

= 1.6.0 =
Added WordPress Multisite support! Super admins can now view network-wide content freshness statistics and identify sites with the most stale content.

= 1.5.0 =
Added Gutenberg block editor integration! See content freshness status and mark posts as reviewed directly in the post editor.

= 1.4.0 =
Added WP-CLI support! Manage content freshness from the command line with powerful filtering, export, and review commands.

= 1.3.0 =
Added REST API! Integrate content freshness data with headless WordPress, static site generators, or external tools.

= 1.2.0 =
Added CSV export! Export your stale content list to share with team members or for external analysis.

= 1.1.0 =
Added email notifications! Receive scheduled digests about stale content. Enable in Settings > Content Freshness.

= 1.0.0 =
Initial release of Content Freshness Monitor.
