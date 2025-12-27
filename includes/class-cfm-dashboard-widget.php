<?php
/**
 * Dashboard widget for quick stats
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFM_Dashboard_Widget {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
    }

    /**
     * Register dashboard widget
     */
    public function add_dashboard_widget() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'cfm_dashboard_widget',
            __( 'Content Freshness', 'content-freshness-monitor' ),
            array( $this, 'render_widget' )
        );
    }

    /**
     * Render widget content
     */
    public function render_widget() {
        $stats = CFM_Scanner::get_stats();
        $health = CFM_Scanner::get_health_score( $stats );
        $stale_posts = CFM_Scanner::get_stale_posts( array( 'per_page' => 5 ) );
        ?>
        <div class="cfm-widget">
            <!-- Content Health Score -->
            <div class="cfm-health-score-widget">
                <div class="cfm-health-grade <?php echo esc_attr( $health['class'] ); ?>" aria-label="<?php printf( esc_attr__( 'Content Health Grade: %s', 'content-freshness-monitor' ), $health['grade'] ); ?>">
                    <?php echo esc_html( $health['grade'] ); ?>
                </div>
                <div class="cfm-health-details">
                    <span class="cfm-health-label"><?php esc_html_e( 'Content Health', 'content-freshness-monitor' ); ?></span>
                    <span class="cfm-health-score"><?php echo esc_html( $health['score'] ); ?>% - <?php echo esc_html( $health['label'] ); ?></span>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="cfm-widget-stats">
                <div class="cfm-widget-stat">
                    <span class="cfm-widget-number cfm-stale-num"><?php echo esc_html( $stats['stale'] ); ?></span>
                    <span class="cfm-widget-label"><?php esc_html_e( 'Stale', 'content-freshness-monitor' ); ?></span>
                </div>
                <div class="cfm-widget-stat">
                    <span class="cfm-widget-number cfm-fresh-num"><?php echo esc_html( $stats['fresh'] ); ?></span>
                    <span class="cfm-widget-label"><?php esc_html_e( 'Fresh', 'content-freshness-monitor' ); ?></span>
                </div>
                <div class="cfm-widget-stat">
                    <span class="cfm-widget-number"><?php echo esc_html( $stats['total'] ); ?></span>
                    <span class="cfm-widget-label"><?php esc_html_e( 'Total', 'content-freshness-monitor' ); ?></span>
                </div>
            </div>

            <?php if ( $stats['stale'] > 0 ) : ?>
                <!-- Progress Bar -->
                <div class="cfm-widget-progress">
                    <div class="cfm-progress-bar">
                        <div class="cfm-progress-fill" style="width: <?php echo esc_attr( 100 - $stats['stale_percent'] ); ?>%;"></div>
                    </div>
                    <p class="cfm-progress-label">
                        <?php
                        printf(
                            /* translators: %d: percentage of fresh content */
                            esc_html__( '%d%% of your content is fresh', 'content-freshness-monitor' ),
                            100 - $stats['stale_percent']
                        );
                        ?>
                    </p>
                </div>

                <!-- Recent Stale Posts -->
                <h4><?php esc_html_e( 'Needs Attention', 'content-freshness-monitor' ); ?></h4>
                <ul class="cfm-widget-list">
                    <?php foreach ( $stale_posts['posts'] as $post ) : ?>
                        <li>
                            <a href="<?php echo esc_url( $post['edit_link'] ); ?>">
                                <?php echo esc_html( wp_trim_words( $post['title'], 8 ) ); ?>
                            </a>
                            <span class="cfm-widget-age"><?php echo esc_html( $post['modified_ago'] ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="cfm-widget-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e( 'All content is fresh!', 'content-freshness-monitor' ); ?>
                </p>
            <?php endif; ?>

            <p class="cfm-widget-footer">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=content-freshness' ) ); ?>">
                    <?php esc_html_e( 'View All', 'content-freshness-monitor' ); ?> &rarr;
                </a>
            </p>
        </div>
        <?php
    }
}

new CFM_Dashboard_Widget();
