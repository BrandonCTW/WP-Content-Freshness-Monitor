<?php
/**
 * Admin page and post list integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFM_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'add_post_list_columns' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Content Freshness', 'content-freshness-monitor' ),
            __( 'Content Freshness', 'content-freshness-monitor' ),
            'edit_posts',
            'content-freshness',
            array( $this, 'render_admin_page' ),
            'dashicons-calendar-alt',
            30
        );
    }

    /**
     * Add columns to post list
     */
    public function add_post_list_columns() {
        $settings = Content_Freshness_Monitor::get_settings();

        if ( ! $settings['show_in_list'] ) {
            return;
        }

        foreach ( $settings['post_types'] as $post_type ) {
            add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_freshness_column' ) );
            add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_freshness_column' ), 10, 2 );
            add_filter( "manage_edit-{$post_type}_sortable_columns", array( $this, 'make_column_sortable' ) );
        }
    }

    /**
     * Add freshness column header
     */
    public function add_freshness_column( $columns ) {
        $new_columns = array();

        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( 'title' === $key ) {
                $new_columns['cfm_freshness'] = __( 'Freshness', 'content-freshness-monitor' );
            }
        }

        return $new_columns;
    }

    /**
     * Render freshness column content
     */
    public function render_freshness_column( $column, $post_id ) {
        if ( 'cfm_freshness' !== $column ) {
            return;
        }

        $post = get_post( $post_id );
        $modified = strtotime( $post->post_modified );
        $days_old = floor( ( time() - $modified ) / DAY_IN_SECONDS );

        $status = CFM_Scanner::get_freshness_status( $days_old );

        printf(
            '<span class="cfm-status %s" title="%s">%s</span>',
            esc_attr( $status['class'] ),
            /* translators: %d: number of days */
            esc_attr( sprintf( __( 'Last modified %d days ago', 'content-freshness-monitor' ), $days_old ) ),
            esc_html( $status['label'] )
        );
    }

    /**
     * Make column sortable
     */
    public function make_column_sortable( $columns ) {
        $columns['cfm_freshness'] = 'modified';
        return $columns;
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $allowed_orderby = array( 'modified', 'date', 'title', 'author' );
        $orderby = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby, true ) ? $_GET['orderby'] : 'modified';
        $order = isset( $_GET['order'] ) && 'desc' === strtolower( $_GET['order'] ) ? 'DESC' : 'ASC';

        $result = CFM_Scanner::get_stale_posts( array(
            'per_page' => 20,
            'paged'    => $paged,
            'orderby'  => $orderby,
            'order'    => $order,
        ) );

        $stats = CFM_Scanner::get_stats();
        ?>
        <div class="wrap cfm-wrap">
            <h1><?php esc_html_e( 'Content Freshness Monitor', 'content-freshness-monitor' ); ?></h1>

            <!-- Stats Cards -->
            <div class="cfm-stats" role="region" aria-label="<?php esc_attr_e( 'Content freshness statistics', 'content-freshness-monitor' ); ?>">
                <div class="cfm-stat-card" role="status" aria-label="<?php esc_attr_e( 'Total posts count', 'content-freshness-monitor' ); ?>">
                    <span class="cfm-stat-number" aria-hidden="true"><?php echo esc_html( $stats['total'] ); ?></span>
                    <span class="cfm-stat-label"><?php esc_html_e( 'Total Posts', 'content-freshness-monitor' ); ?></span>
                    <span class="screen-reader-text"><?php printf( esc_html__( '%d total posts', 'content-freshness-monitor' ), $stats['total'] ); ?></span>
                </div>
                <div class="cfm-stat-card cfm-stat-stale" role="status" aria-label="<?php esc_attr_e( 'Stale posts count', 'content-freshness-monitor' ); ?>">
                    <span class="cfm-stat-number" aria-hidden="true"><?php echo esc_html( $stats['stale'] ); ?></span>
                    <span class="cfm-stat-label"><?php esc_html_e( 'Stale Posts', 'content-freshness-monitor' ); ?></span>
                    <span class="screen-reader-text"><?php printf( esc_html__( '%d stale posts', 'content-freshness-monitor' ), $stats['stale'] ); ?></span>
                </div>
                <div class="cfm-stat-card cfm-stat-fresh" role="status" aria-label="<?php esc_attr_e( 'Fresh posts count', 'content-freshness-monitor' ); ?>">
                    <span class="cfm-stat-number" aria-hidden="true"><?php echo esc_html( $stats['fresh'] ); ?></span>
                    <span class="cfm-stat-label"><?php esc_html_e( 'Fresh Posts', 'content-freshness-monitor' ); ?></span>
                    <span class="screen-reader-text"><?php printf( esc_html__( '%d fresh posts', 'content-freshness-monitor' ), $stats['fresh'] ); ?></span>
                </div>
                <div class="cfm-stat-card" role="status" aria-label="<?php esc_attr_e( 'Percentage needing attention', 'content-freshness-monitor' ); ?>">
                    <span class="cfm-stat-number" aria-hidden="true"><?php echo esc_html( $stats['stale_percent'] ); ?>%</span>
                    <span class="cfm-stat-label"><?php esc_html_e( 'Need Attention', 'content-freshness-monitor' ); ?></span>
                    <span class="screen-reader-text"><?php printf( esc_html__( '%d percent of posts need attention', 'content-freshness-monitor' ), $stats['stale_percent'] ); ?></span>
                </div>
            </div>

            <p class="cfm-threshold-note">
                <?php
                printf(
                    /* translators: %d: number of days */
                    esc_html__( 'Posts not modified in over %d days are considered stale.', 'content-freshness-monitor' ),
                    esc_html( $stats['threshold'] )
                );
                ?>
                <a href="<?php echo esc_url( admin_url( 'options-general.php?page=cfm-settings' ) ); ?>">
                    <?php esc_html_e( 'Change settings', 'content-freshness-monitor' ); ?>
                </a>
            </p>

            <?php if ( empty( $result['posts'] ) ) : ?>
                <div class="cfm-no-stale">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <p><?php esc_html_e( 'Great news! All your content is fresh.', 'content-freshness-monitor' ); ?></p>
                </div>
            <?php else : ?>

                <!-- Bulk Actions -->
                <div class="cfm-bulk-actions" role="toolbar" aria-label="<?php esc_attr_e( 'Bulk actions for stale content', 'content-freshness-monitor' ); ?>">
                    <button type="button" class="button cfm-select-all" aria-pressed="false">
                        <?php esc_html_e( 'Select All', 'content-freshness-monitor' ); ?>
                    </button>
                    <button type="button" class="button cfm-bulk-review" disabled aria-disabled="true">
                        <?php esc_html_e( 'Mark Selected as Reviewed', 'content-freshness-monitor' ); ?>
                    </button>
                    <a href="<?php echo esc_url( CFM_Export::get_export_url() ); ?>" class="button cfm-export-csv" aria-label="<?php esc_attr_e( 'Export stale content to CSV file', 'content-freshness-monitor' ); ?>">
                        <span class="dashicons dashicons-download" style="vertical-align: middle; margin-top: -2px;" aria-hidden="true"></span>
                        <?php esc_html_e( 'Export to CSV', 'content-freshness-monitor' ); ?>
                    </a>
                </div>

                <!-- Stale Content Table -->
                <table class="wp-list-table widefat fixed striped cfm-table" role="table" aria-label="<?php esc_attr_e( 'Stale content requiring review', 'content-freshness-monitor' ); ?>">
                    <thead>
                        <tr>
                            <th class="check-column" scope="col">
                                <input type="checkbox" class="cfm-check-all" aria-label="<?php esc_attr_e( 'Select all posts', 'content-freshness-monitor' ); ?>" />
                            </th>
                            <th class="cfm-col-title" scope="col">
                                <?php esc_html_e( 'Title', 'content-freshness-monitor' ); ?>
                            </th>
                            <th class="cfm-col-type" scope="col">
                                <?php esc_html_e( 'Type', 'content-freshness-monitor' ); ?>
                            </th>
                            <th class="cfm-col-author" scope="col">
                                <?php esc_html_e( 'Author', 'content-freshness-monitor' ); ?>
                            </th>
                            <th class="cfm-col-modified" scope="col" aria-sort="<?php echo 'modified' === $orderby ? ( 'ASC' === $order ? 'ascending' : 'descending' ) : 'none'; ?>">
                                <?php
                                $sort_url = add_query_arg( array(
                                    'orderby' => 'modified',
                                    'order'   => 'ASC' === $order ? 'desc' : 'asc',
                                ) );
                                ?>
                                <a href="<?php echo esc_url( $sort_url ); ?>" aria-label="<?php esc_attr_e( 'Sort by last modified date', 'content-freshness-monitor' ); ?>">
                                    <?php esc_html_e( 'Last Modified', 'content-freshness-monitor' ); ?>
                                    <span class="sorting-indicator" aria-hidden="true"></span>
                                </a>
                            </th>
                            <th class="cfm-col-reviewed" scope="col">
                                <?php esc_html_e( 'Last Reviewed', 'content-freshness-monitor' ); ?>
                            </th>
                            <th class="cfm-col-actions" scope="col">
                                <?php esc_html_e( 'Actions', 'content-freshness-monitor' ); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $result['posts'] as $post ) : ?>
                            <tr data-post-id="<?php echo esc_attr( $post['ID'] ); ?>">
                                <td class="check-column">
                                    <input type="checkbox" class="cfm-post-check" value="<?php echo esc_attr( $post['ID'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Select %s', 'content-freshness-monitor' ), $post['title'] ) ); ?>" />
                                </td>
                                <td class="cfm-col-title">
                                    <strong>
                                        <a href="<?php echo esc_url( $post['edit_link'] ); ?>">
                                            <?php echo esc_html( $post['title'] ); ?>
                                        </a>
                                    </strong>
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="<?php echo esc_url( $post['edit_link'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Edit %s', 'content-freshness-monitor' ), $post['title'] ) ); ?>">
                                                <?php esc_html_e( 'Edit', 'content-freshness-monitor' ); ?>
                                            </a> |
                                        </span>
                                        <span class="view">
                                            <a href="<?php echo esc_url( $post['view_link'] ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( sprintf( __( 'View %s (opens in new tab)', 'content-freshness-monitor' ), $post['title'] ) ); ?>">
                                                <?php esc_html_e( 'View', 'content-freshness-monitor' ); ?>
                                            </a>
                                        </span>
                                    </div>
                                </td>
                                <td class="cfm-col-type">
                                    <?php
                                    $post_type_obj = get_post_type_object( $post['post_type'] );
                                    echo esc_html( $post_type_obj ? $post_type_obj->labels->singular_name : $post['post_type'] );
                                    ?>
                                </td>
                                <td class="cfm-col-author">
                                    <?php echo esc_html( $post['author'] ); ?>
                                </td>
                                <td class="cfm-col-modified">
                                    <span class="cfm-days-old"><?php echo esc_html( $post['modified_ago'] ); ?></span>
                                    <small><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $post['modified'] ) ) ); ?></small>
                                </td>
                                <td class="cfm-col-reviewed">
                                    <?php if ( $post['last_reviewed'] ) : ?>
                                        <time datetime="<?php echo esc_attr( date( 'c', strtotime( $post['last_reviewed'] ) ) ); ?>">
                                            <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $post['last_reviewed'] ) ) ); ?>
                                        </time>
                                    <?php else : ?>
                                        <span class="cfm-never"><?php esc_html_e( 'Never', 'content-freshness-monitor' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="cfm-col-actions">
                                    <button type="button" class="button button-small cfm-mark-reviewed" data-post-id="<?php echo esc_attr( $post['ID'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Mark %s as reviewed', 'content-freshness-monitor' ), $post['title'] ) ); ?>">
                                        <?php esc_html_e( 'Mark Reviewed', 'content-freshness-monitor' ); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ( $result['pages'] > 1 ) : ?>
                    <nav class="cfm-pagination" role="navigation" aria-label="<?php esc_attr_e( 'Stale content pagination', 'content-freshness-monitor' ); ?>">
                        <?php
                        echo paginate_links( array(
                            'base'      => add_query_arg( 'paged', '%#%' ),
                            'format'    => '',
                            'current'   => $paged,
                            'total'     => $result['pages'],
                            'prev_text' => '<span aria-hidden="true">&laquo;</span><span class="screen-reader-text">' . __( 'Previous page', 'content-freshness-monitor' ) . '</span>',
                            'next_text' => '<span aria-hidden="true">&raquo;</span><span class="screen-reader-text">' . __( 'Next page', 'content-freshness-monitor' ) . '</span>',
                        ) );
                        ?>
                    </nav>
                <?php endif; ?>

            <?php endif; ?>

            <?php
            // Render trends chart
            CFM_Trends::render_chart();
            ?>
        </div>
        <?php
    }
}

new CFM_Admin();
