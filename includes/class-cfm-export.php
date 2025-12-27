<?php
/**
 * CSV Export functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFM_Export {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
    }

    /**
     * Handle CSV export request
     */
    public function handle_csv_export() {
        if ( ! isset( $_GET['cfm_export_csv'] ) || '1' !== $_GET['cfm_export_csv'] ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cfm_export_csv' ) ) {
            wp_die( __( 'Security check failed.', 'content-freshness-monitor' ) );
        }

        // Check capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'You do not have permission to export content.', 'content-freshness-monitor' ) );
        }

        $this->generate_csv();
    }

    /**
     * Generate and output CSV file
     */
    private function generate_csv() {
        // Get all stale posts (no pagination limit)
        $result = CFM_Scanner::get_stale_posts( array(
            'per_page' => -1,
            'orderby'  => 'modified',
            'order'    => 'ASC',
        ) );

        $filename = 'stale-content-' . date( 'Y-m-d' ) . '.csv';

        // Set headers for file download
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        // Create output stream
        $output = fopen( 'php://output', 'w' );

        // Add BOM for Excel UTF-8 compatibility
        fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );

        // Write header row
        fputcsv( $output, array(
            __( 'ID', 'content-freshness-monitor' ),
            __( 'Title', 'content-freshness-monitor' ),
            __( 'Type', 'content-freshness-monitor' ),
            __( 'Author', 'content-freshness-monitor' ),
            __( 'Last Modified', 'content-freshness-monitor' ),
            __( 'Days Since Modified', 'content-freshness-monitor' ),
            __( 'Last Reviewed', 'content-freshness-monitor' ),
            __( 'Edit URL', 'content-freshness-monitor' ),
            __( 'View URL', 'content-freshness-monitor' ),
        ) );

        // Write data rows
        foreach ( $result['posts'] as $post ) {
            $post_type_obj = get_post_type_object( $post['post_type'] );
            $post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post['post_type'];

            fputcsv( $output, array(
                $post['ID'],
                $post['title'],
                $post_type_label,
                $post['author'],
                $post['modified'],
                $post['days_old'],
                $post['last_reviewed'] ? $post['last_reviewed'] : __( 'Never', 'content-freshness-monitor' ),
                $post['edit_link'],
                $post['view_link'],
            ) );
        }

        fclose( $output );
        exit;
    }

    /**
     * Get export URL
     *
     * @return string Export URL with nonce
     */
    public static function get_export_url() {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'page'           => 'content-freshness',
                    'cfm_export_csv' => '1',
                ),
                admin_url( 'admin.php' )
            ),
            'cfm_export_csv'
        );
    }
}

new CFM_Export();
