# Local Demo Setup

Quick ways to test the Content Freshness Monitor plugin locally.

## Option 1: WordPress Playground (Easiest)

Once this plugin is published to a public repository, use WordPress Playground:

```
https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/YOUR_USERNAME/content-freshness-monitor/main/blueprint.json
```

Replace `YOUR_USERNAME` with your GitHub username.

## Option 2: Local WordPress with Docker

If you have Docker installed:

```bash
# Create a directory for WordPress
mkdir wp-test && cd wp-test

# Run WordPress with Docker
docker run -d --name wp-cfm-test \
  -p 8080:80 \
  -e WORDPRESS_DB_HOST=db \
  -e WORDPRESS_DB_USER=root \
  -e WORDPRESS_DB_PASSWORD=root \
  -e WORDPRESS_DB_NAME=wordpress \
  --network bridge \
  wordpress:latest

# Copy the plugin
docker cp /path/to/content-freshness-monitor wp-cfm-test:/var/www/html/wp-content/plugins/

# Open http://localhost:8080 and complete WordPress setup
# Then activate Content Freshness Monitor in Plugins
```

## Option 3: LocalWP / MAMP / XAMPP

1. Install [LocalWP](https://localwp.com/), MAMP, or XAMPP
2. Create a new WordPress site
3. Extract `content-freshness-monitor.zip` to `wp-content/plugins/`
4. Activate the plugin in WordPress Admin

## Option 4: wp-env (WordPress Development)

If you have Node.js and Docker:

```bash
# Install wp-env globally
npm install -g @wordpress/env

# Create a wp-env config
echo '{"plugins":["./content-freshness-monitor"]}' > .wp-env.json

# Start the environment
wp-env start

# Open http://localhost:8888 (admin: admin/password)
```

## Creating Sample Content

After installation, create test content with varied ages to see the plugin in action:

1. Go to **Posts > Add New**
2. Create several posts
3. Use a database tool or WP-CLI to backdate some posts:

```bash
# If using WP-CLI
wp post update 123 --post_date="2024-01-15 10:00:00"
wp post update 124 --post_date="2024-06-01 10:00:00"
wp post update 125 --post_date="2025-11-01 10:00:00"
```

4. Navigate to **Content Freshness** to see the analysis

## Quick Feature Tour

After activation, explore:

1. **Content Freshness** - Main dashboard with stale content list
2. **Dashboard > Widget** - Content Health Score at a glance
3. **Settings > Content Freshness** - Configure thresholds and notifications
4. **Posts > All Posts** - Freshness column on post list
5. **Edit any post** - Gutenberg sidebar panel (if using block editor)
