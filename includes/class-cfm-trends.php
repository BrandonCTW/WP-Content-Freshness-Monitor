<?php
/**
 * Content freshness trends tracking and visualization
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFM_Trends {

    /**
     * Option key for storing historical data
     */
    const HISTORY_OPTION = 'cfm_stats_history';

    /**
     * Maximum number of data points to keep
     */
    const MAX_DATA_POINTS = 90;

    /**
     * Constructor
     */
    public function __construct() {
        // Schedule daily snapshot
        add_action( 'cfm_daily_snapshot', array( $this, 'record_snapshot' ) );
        add_action( 'wp', array( $this, 'schedule_snapshot' ) );
        add_action( 'admin_init', array( $this, 'schedule_snapshot' ) );

        // AJAX handler for trends data
        add_action( 'wp_ajax_cfm_get_trends', array( $this, 'ajax_get_trends' ) );
    }

    /**
     * Schedule daily snapshot if not already scheduled
     */
    public function schedule_snapshot() {
        if ( ! wp_next_scheduled( 'cfm_daily_snapshot' ) ) {
            wp_schedule_event( time(), 'daily', 'cfm_daily_snapshot' );
        }
    }

    /**
     * Record a snapshot of current stats
     */
    public function record_snapshot() {
        $stats = CFM_Scanner::get_stats( true ); // Force refresh

        $history = get_option( self::HISTORY_OPTION, array() );

        $history[] = array(
            'date'          => current_time( 'Y-m-d' ),
            'total'         => $stats['total'],
            'stale'         => $stats['stale'],
            'fresh'         => $stats['fresh'],
            'stale_percent' => $stats['stale_percent'],
        );

        // Keep only the last MAX_DATA_POINTS entries
        if ( count( $history ) > self::MAX_DATA_POINTS ) {
            $history = array_slice( $history, -self::MAX_DATA_POINTS );
        }

        update_option( self::HISTORY_OPTION, $history, false );
    }

    /**
     * Get historical trends data
     *
     * @param int $days Number of days to retrieve (default 30)
     * @return array Historical data
     */
    public static function get_history( $days = 30 ) {
        $history = get_option( self::HISTORY_OPTION, array() );

        if ( empty( $history ) ) {
            return array();
        }

        // Get the last N days
        $days = min( $days, count( $history ) );
        return array_slice( $history, -$days );
    }

    /**
     * AJAX handler for getting trends data
     */
    public function ajax_get_trends() {
        check_ajax_referer( 'cfm_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'content-freshness-monitor' ) ) );
        }

        $days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
        $days = min( $days, self::MAX_DATA_POINTS );

        $history = self::get_history( $days );

        // If no history, create initial snapshot
        if ( empty( $history ) ) {
            $this->record_snapshot();
            $history = self::get_history( $days );
        }

        wp_send_json_success( array(
            'history' => $history,
            'labels'  => self::generate_labels( $history ),
        ) );
    }

    /**
     * Generate chart labels from history
     *
     * @param array $history History data
     * @return array Labels for chart
     */
    private static function generate_labels( $history ) {
        $labels = array();
        foreach ( $history as $entry ) {
            $labels[] = date_i18n( 'M j', strtotime( $entry['date'] ) );
        }
        return $labels;
    }

    /**
     * Render trends chart HTML
     */
    public static function render_chart() {
        $history = self::get_history( 30 );
        ?>
        <div class="cfm-trends-section">
            <h2><?php esc_html_e( 'Content Freshness Trends', 'content-freshness-monitor' ); ?></h2>

            <?php if ( empty( $history ) || count( $history ) < 2 ) : ?>
                <div class="cfm-trends-notice">
                    <span class="dashicons dashicons-chart-area"></span>
                    <p><?php esc_html_e( 'Trends data is collected daily. Check back after a few days to see your content freshness trends.', 'content-freshness-monitor' ); ?></p>
                </div>
            <?php else : ?>
                <div class="cfm-trends-controls" role="group" aria-label="<?php esc_attr_e( 'Trends time range selector', 'content-freshness-monitor' ); ?>">
                    <button type="button" class="button cfm-trends-range active" data-days="7" aria-pressed="false">
                        <?php esc_html_e( '7 Days', 'content-freshness-monitor' ); ?>
                    </button>
                    <button type="button" class="button cfm-trends-range" data-days="30" aria-pressed="true">
                        <?php esc_html_e( '30 Days', 'content-freshness-monitor' ); ?>
                    </button>
                    <button type="button" class="button cfm-trends-range" data-days="90" aria-pressed="false">
                        <?php esc_html_e( '90 Days', 'content-freshness-monitor' ); ?>
                    </button>
                </div>
                <div class="cfm-chart-container" role="img" aria-label="<?php esc_attr_e( 'Content freshness trends chart', 'content-freshness-monitor' ); ?>">
                    <canvas id="cfm-trends-chart" height="300"></canvas>
                </div>
                <div class="cfm-chart-legend">
                    <span class="cfm-legend-item cfm-legend-fresh">
                        <span class="cfm-legend-color"></span>
                        <?php esc_html_e( 'Fresh Content', 'content-freshness-monitor' ); ?>
                    </span>
                    <span class="cfm-legend-item cfm-legend-stale">
                        <span class="cfm-legend-color"></span>
                        <?php esc_html_e( 'Stale Content', 'content-freshness-monitor' ); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Clear history on plugin uninstall
     */
    public static function clear_history() {
        delete_option( self::HISTORY_OPTION );
        $timestamp = wp_next_scheduled( 'cfm_daily_snapshot' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'cfm_daily_snapshot' );
        }
    }
}

new CFM_Trends();
