<?php
/**
 * Multisite support for Content Freshness Monitor
 *
 * Provides network-wide content freshness monitoring for WordPress Multisite installations.
 *
 * @package ContentFreshnessMonitor
 * @since 1.6.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class CFM_Multisite
 *
 * Handles multisite-specific functionality including network admin dashboard,
 * aggregated statistics, and network-wide settings.
 */
class CFM_Multisite {

    /**
     * Constructor
     */
    public function __construct() {
        // Only load on multisite installations
        if ( ! is_multisite() ) {
            return;
        }

        add_action( 'network_admin_menu', array( $this, 'add_network_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_network_assets' ) );
        add_action( 'wp_ajax_cfm_network_refresh', array( $this, 'ajax_network_refresh' ) );
    }

    /**
     * Add network admin menu
     */
    public function add_network_menu() {
        add_menu_page(
            __( 'Network Content Freshness', 'content-freshness-monitor' ),
            __( 'Content Freshness', 'content-freshness-monitor' ),
            'manage_network',
            'cfm-network',
            array( $this, 'render_network_dashboard' ),
            'dashicons-clock',
            30
        );
    }

    /**
     * Enqueue network admin assets
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_network_assets( $hook ) {
        if ( 'toplevel_page_cfm-network' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'cfm-admin',
            CFM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CFM_VERSION
        );

        wp_enqueue_script(
            'cfm-admin',
            CFM_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            CFM_VERSION,
            true
        );

        wp_localize_script( 'cfm-admin', 'cfmAjax', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'cfm_network_nonce' ),
        ) );
    }

    /**
     * Render network admin dashboard
     */
    public function render_network_dashboard() {
        $network_stats = $this->get_network_stats();
        $sites_data    = $this->get_sites_freshness_data();
        ?>
        <div class="wrap cfm-wrap">
            <h1><?php esc_html_e( 'Network Content Freshness', 'content-freshness-monitor' ); ?></h1>

            <div class="cfm-stats-grid">
                <div class="cfm-stat-box">
                    <span class="cfm-stat-number"><?php echo esc_html( number_format_i18n( $network_stats['total_sites'] ) ); ?></span>
                    <span class="cfm-stat-label"><?php esc_html_e( 'Sites', 'content-freshness-monitor' ); ?></span>
                </div>
                <div class="cfm-stat-box">
                    <span class="cfm-stat-number"><?php echo esc_html( number_format_i18n( $network_stats['total_posts'] ) ); ?></span>
                    <span class="cfm-stat-label"><?php esc_html_e( 'Total Posts', 'content-freshness-monitor' ); ?></span>
                </div>
                <div class="cfm-stat-box cfm-stat-stale">
                    <span class="cfm-stat-number"><?php echo esc_html( number_format_i18n( $network_stats['total_stale'] ) ); ?></span>
                    <span class="cfm-stat-label"><?php esc_html_e( 'Stale Network-wide', 'content-freshness-monitor' ); ?></span>
                </div>
                <div class="cfm-stat-box cfm-stat-percent">
                    <span class="cfm-stat-number"><?php echo esc_html( $network_stats['stale_percent'] ); ?>%</span>
                    <span class="cfm-stat-label"><?php esc_html_e( 'Stale Percentage', 'content-freshness-monitor' ); ?></span>
                </div>
            </div>

            <h2><?php esc_html_e( 'Sites Overview', 'content-freshness-monitor' ); ?></h2>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Site', 'content-freshness-monitor' ); ?></th>
                        <th scope="col" class="cfm-num-col"><?php esc_html_e( 'Total Posts', 'content-freshness-monitor' ); ?></th>
                        <th scope="col" class="cfm-num-col"><?php esc_html_e( 'Fresh', 'content-freshness-monitor' ); ?></th>
                        <th scope="col" class="cfm-num-col"><?php esc_html_e( 'Aging', 'content-freshness-monitor' ); ?></th>
                        <th scope="col" class="cfm-num-col"><?php esc_html_e( 'Stale', 'content-freshness-monitor' ); ?></th>
                        <th scope="col" class="cfm-num-col"><?php esc_html_e( 'Stale %', 'content-freshness-monitor' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Actions', 'content-freshness-monitor' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $sites_data ) ) : ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e( 'No sites found.', 'content-freshness-monitor' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $sites_data as $site ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $site['name'] ); ?></strong>
                                    <br>
                                    <small><?php echo esc_url( $site['url'] ); ?></small>
                                </td>
                                <td class="cfm-num-col"><?php echo esc_html( number_format_i18n( $site['total'] ) ); ?></td>
                                <td class="cfm-num-col cfm-fresh"><?php echo esc_html( number_format_i18n( $site['fresh'] ) ); ?></td>
                                <td class="cfm-num-col cfm-aging"><?php echo esc_html( number_format_i18n( $site['aging'] ) ); ?></td>
                                <td class="cfm-num-col cfm-stale"><?php echo esc_html( number_format_i18n( $site['stale'] ) ); ?></td>
                                <td class="cfm-num-col">
                                    <?php
                                    $percent = $site['total'] > 0 ? round( ( $site['stale'] / $site['total'] ) * 100 ) : 0;
                                    echo esc_html( $percent ) . '%';
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( $site['admin_url'] ); ?>" class="button button-small" target="_blank">
                                        <?php esc_html_e( 'View Details', 'content-freshness-monitor' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php $this->render_network_chart_data( $sites_data ); ?>
        </div>
        <?php
    }

    /**
     * Get aggregated network statistics
     *
     * @return array Network-wide statistics.
     */
    public function get_network_stats() {
        $sites = get_sites( array(
            'number' => 0, // Get all sites
            'public' => 1,
        ) );

        $stats = array(
            'total_sites'   => count( $sites ),
            'total_posts'   => 0,
            'total_stale'   => 0,
            'total_fresh'   => 0,
            'total_aging'   => 0,
            'stale_percent' => 0,
        );

        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );

            $scanner    = new CFM_Scanner();
            $site_stats = $scanner->get_stats();

            $stats['total_posts'] += $site_stats['total'];
            $stats['total_stale'] += $site_stats['stale'];
            $stats['total_fresh'] += $site_stats['fresh'];
            $stats['total_aging'] += $site_stats['aging'];

            restore_current_blog();
        }

        if ( $stats['total_posts'] > 0 ) {
            $stats['stale_percent'] = round( ( $stats['total_stale'] / $stats['total_posts'] ) * 100 );
        }

        return $stats;
    }

    /**
     * Get freshness data for all sites
     *
     * @return array Array of site freshness data.
     */
    public function get_sites_freshness_data() {
        $sites      = get_sites( array(
            'number' => 0,
            'public' => 1,
        ) );
        $sites_data = array();

        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );

            $scanner    = new CFM_Scanner();
            $site_stats = $scanner->get_stats();

            $sites_data[] = array(
                'id'        => $site->blog_id,
                'name'      => get_bloginfo( 'name' ),
                'url'       => get_site_url(),
                'admin_url' => admin_url( 'admin.php?page=content-freshness' ),
                'total'     => $site_stats['total'],
                'fresh'     => $site_stats['fresh'],
                'aging'     => $site_stats['aging'],
                'stale'     => $site_stats['stale'],
            );

            restore_current_blog();
        }

        // Sort by stale count descending (most problematic sites first)
        usort( $sites_data, function( $a, $b ) {
            return $b['stale'] - $a['stale'];
        } );

        return $sites_data;
    }

    /**
     * Render chart data as JavaScript for visualization
     *
     * @param array $sites_data Sites freshness data.
     */
    private function render_network_chart_data( $sites_data ) {
        if ( empty( $sites_data ) ) {
            return;
        }
        ?>
        <script type="text/javascript">
            // Chart data for potential visualization
            window.cfmNetworkData = <?php echo wp_json_encode( array_map( function( $site ) {
                return array(
                    'name'  => $site['name'],
                    'fresh' => $site['fresh'],
                    'aging' => $site['aging'],
                    'stale' => $site['stale'],
                );
            }, array_slice( $sites_data, 0, 10 ) ) ); ?>;
        </script>
        <?php
    }

    /**
     * AJAX handler for network refresh
     */
    public function ajax_network_refresh() {
        check_ajax_referer( 'cfm_network_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'content-freshness-monitor' ) ) );
        }

        $stats = $this->get_network_stats();
        wp_send_json_success( $stats );
    }

    /**
     * Check if current installation is multisite
     *
     * @return bool True if multisite, false otherwise.
     */
    public static function is_multisite() {
        return function_exists( 'is_multisite' ) && is_multisite();
    }

    /**
     * Get stale posts across all sites (limited for performance)
     *
     * @param int $limit Maximum posts to return per site.
     * @return array Stale posts from all sites.
     */
    public function get_network_stale_posts( $limit = 5 ) {
        $sites       = get_sites( array( 'number' => 0, 'public' => 1 ) );
        $stale_posts = array();

        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );

            $scanner     = new CFM_Scanner();
            $site_stale  = $scanner->get_stale_posts( array(
                'per_page' => $limit,
            ) );

            foreach ( $site_stale as $post ) {
                $post['site_id']   = $site->blog_id;
                $post['site_name'] = get_bloginfo( 'name' );
                $stale_posts[]     = $post;
            }

            restore_current_blog();
        }

        // Sort by days_old descending
        usort( $stale_posts, function( $a, $b ) {
            return $b['days_old'] - $a['days_old'];
        } );

        return $stale_posts;
    }
}

// Initialize multisite support
new CFM_Multisite();
