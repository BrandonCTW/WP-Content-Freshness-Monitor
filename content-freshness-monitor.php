<?php
/**
 * Plugin Name: Content Freshness Monitor
 * Plugin URI: https://github.com/caltechweb/content-freshness-monitor
 * Description: Monitor and manage stale content on your WordPress site. Get alerts when posts need updating.
 * Version: 2.8.1
 * Author: CalTech Web
 * Author URI: https://caltechweb.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: content-freshness-monitor
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'CFM_VERSION', '2.8.1' );
define( 'CONTENT_FRESHNESS_MONITOR_VERSION', '2.8.1' );
define( 'CFM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CFM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
final class Content_Freshness_Monitor {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once CFM_PLUGIN_DIR . 'includes/class-cfm-settings.php';
        require_once CFM_PLUGIN_DIR . 'includes/class-cfm-scanner.php';
        require_once CFM_PLUGIN_DIR . 'includes/class-cfm-admin.php';
        require_once CFM_PLUGIN_DIR . 'includes/class-cfm-dashboard-widget.php';
        require_once CFM_PLUGIN_DIR . 'includes/class-cfm-notifications.php';
        require_once CFM_PLUGIN_DIR . 'includes/class-cfm-export.php';
        require_once CFM_PLUGIN_DIR . 'includes/class-cfm-rest-api.php';
        require_once CFM_PLUGIN_DIR . 'includes/class-cfm-gutenberg.php';
        require_once CFM_PLUGIN_DIR . 'includes/class-cfm-trends.php';
        require_once CFM_PLUGIN_DIR . 'includes/class-cfm-shortcodes.php';

        // Load WP-CLI commands if available.
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once CFM_PLUGIN_DIR . 'includes/class-cfm-cli.php';
        }

        // Load multisite support if on a multisite installation.
        if ( is_multisite() ) {
            require_once CFM_PLUGIN_DIR . 'includes/class-cfm-multisite.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $defaults = array(
            'staleness_days'         => 180,
            'post_types'             => array( 'post' ),
            'exclude_ids'            => '',
            'show_in_list'           => true,
            'email_enabled'          => false,
            'email_frequency'        => 'weekly',
            'email_recipient'        => '',
            'per_type_thresholds'    => array(),
            'enable_per_type'        => false,
        );

        if ( ! get_option( 'cfm_settings' ) ) {
            add_option( 'cfm_settings', $defaults );
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Unschedule email notifications
        CFM_Notifications::unschedule_digest();
        CFM_Notifications::unschedule_author_digest();

        // Unschedule trends snapshot cron (but preserve historical data)
        $timestamp = wp_next_scheduled( 'cfm_daily_snapshot' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'cfm_daily_snapshot' );
        }

        flush_rewrite_rules();
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'content-freshness-monitor',
            false,
            dirname( CFM_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our pages and post list
        $allowed_hooks = array(
            'toplevel_page_content-freshness',
            'settings_page_cfm-settings',
            'edit.php',
            'index.php',
        );

        if ( ! in_array( $hook, $allowed_hooks, true ) ) {
            return;
        }

        wp_enqueue_style(
            'cfm-admin',
            CFM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CFM_VERSION
        );

        // Load Chart.js on the main content freshness page for trends visualization
        $script_deps = array( 'jquery' );
        if ( 'toplevel_page_content-freshness' === $hook ) {
            wp_enqueue_script(
                'chartjs',
                CFM_PLUGIN_URL . 'assets/js/chart.min.js',
                array(),
                '4.4.1',
                true
            );
            $script_deps[] = 'chartjs';
        }

        wp_enqueue_script(
            'cfm-admin',
            CFM_PLUGIN_URL . 'assets/js/admin.js',
            $script_deps,
            CFM_VERSION,
            true
        );

        wp_localize_script( 'cfm-admin', 'cfmAjax', array(
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'cfm_nonce' ),
            'freshLabel' => __( 'Fresh Content', 'content-freshness-monitor' ),
            'staleLabel' => __( 'Stale Content', 'content-freshness-monitor' ),
        ) );
    }

    /**
     * Get plugin settings
     */
    public static function get_settings() {
        $defaults = array(
            'staleness_days'         => 180,
            'date_check_type'        => 'modified',
            'post_types'             => array( 'post' ),
            'exclude_ids'            => '',
            'show_in_list'           => true,
            'email_enabled'          => false,
            'email_frequency'        => 'weekly',
            'email_recipient'        => '',
            'per_type_thresholds'    => array(),
            'enable_per_type'        => false,
            'author_notifications'   => false,
            'author_email_frequency' => 'weekly',
            'author_min_stale'       => 1,
        );

        $settings = get_option( 'cfm_settings', $defaults );
        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Get staleness threshold for a specific post type
     *
     * @param string $post_type Post type name
     * @return int Staleness threshold in days
     */
    public static function get_threshold_for_type( $post_type ) {
        $settings = self::get_settings();

        // If per-type thresholds are enabled and this type has a custom value
        if ( ! empty( $settings['enable_per_type'] ) &&
             ! empty( $settings['per_type_thresholds'][ $post_type ] ) ) {
            return absint( $settings['per_type_thresholds'][ $post_type ] );
        }

        // Fall back to global threshold
        return absint( $settings['staleness_days'] );
    }
}

// Initialize plugin
function cfm_init() {
    return Content_Freshness_Monitor::get_instance();
}
add_action( 'plugins_loaded', 'cfm_init' );
