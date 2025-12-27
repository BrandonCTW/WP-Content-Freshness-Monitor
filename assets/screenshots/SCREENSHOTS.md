# Screenshots for WordPress.org Submission

The `readme.txt` file references 8 screenshots for the WordPress.org plugin directory. Before submitting to WordPress.org, you need to create these screenshots.

## Required Screenshots

Place these files in this directory (or in the `/assets/` folder of your WordPress.org SVN repository):

| File | Description |
|------|-------------|
| `screenshot-1.png` | Main Content Freshness dashboard showing the stale content list with bulk actions, statistics cards, and export button. |
| `screenshot-2.png` | Dashboard widget displaying the Content Health Score grade (A-F) with a quick overview of content statistics. |
| `screenshot-3.png` | Settings page with staleness threshold configuration, post type selection, email notification options, and per-type thresholds. |
| `screenshot-4.png` | Posts list table with the Freshness column showing color-coded status indicators (Fresh/Aging/Stale). |
| `screenshot-5.png` | Gutenberg block editor sidebar panel showing real-time content freshness status and "Mark as Reviewed" button. |
| `screenshot-6.png` | Content freshness trends chart visualization showing fresh vs stale content over the past 30 days. |
| `screenshot-7.png` | WordPress Multisite network admin dashboard with per-site freshness breakdown and network-wide statistics. |
| `screenshot-8.png` | Email digest notification showing stale content summary delivered to administrators. |

## Screenshot Specifications

- **Format**: PNG (recommended) or JPEG
- **Width**: 772px or 1544px (2x for retina) recommended
- **Height**: Variable, but keep consistent aspect ratios
- **Naming**: Must match exactly as listed above

## How to Create Screenshots

1. Install the plugin on a WordPress site with sample content
2. Navigate to each feature area
3. Use browser dev tools to set viewport to 772px width
4. Capture screenshots with a tool like:
   - Browser's built-in screenshot (F12 → Ctrl+Shift+P → "Capture screenshot")
   - [Snagit](https://www.techsmith.com/snagit.html)
   - [Greenshot](https://getgreenshot.org/)
   - macOS: Cmd+Shift+4
   - Windows: Win+Shift+S

## WordPress.org SVN Location

When uploading to WordPress.org SVN, screenshots go in:
```
/assets/screenshot-1.png
/assets/screenshot-2.png
...
```

(Note: The `/assets/` folder in WordPress.org SVN is separate from your plugin's `assets/` folder)
