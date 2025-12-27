<?php
/**
 * Gutenberg Block Editor Integration
 *
 * Adds a sidebar panel showing content freshness status in the block editor.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFM_Gutenberg {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_meta' ) );
    }

    /**
     * Register post meta for REST API access
     */
    public function register_meta() {
        $settings = Content_Freshness_Monitor::get_settings();
        $post_types = $settings['post_types'];

        foreach ( $post_types as $post_type ) {
            register_post_meta( $post_type, CFM_Scanner::REVIEWED_META_KEY, array(
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => 'string',
                'auth_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
            ) );
        }
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueue_editor_assets() {
        global $post;

        if ( ! $post ) {
            return;
        }

        $settings = Content_Freshness_Monitor::get_settings();

        // Only load for monitored post types
        if ( ! in_array( $post->post_type, $settings['post_types'], true ) ) {
            return;
        }

        // Enqueue the sidebar script
        wp_enqueue_script(
            'cfm-gutenberg-sidebar',
            CFM_PLUGIN_URL . 'assets/js/gutenberg-sidebar.js',
            array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ),
            CFM_VERSION,
            true
        );

        // Calculate freshness data for current post
        $modified = strtotime( $post->post_modified );
        $now = time();
        $days_old = floor( ( $now - $modified ) / DAY_IN_SECONDS );
        $freshness = CFM_Scanner::get_freshness_status( $days_old );
        $last_reviewed = get_post_meta( $post->ID, CFM_Scanner::REVIEWED_META_KEY, true );

        // Pass data to script
        wp_localize_script( 'cfm-gutenberg-sidebar', 'cfmGutenberg', array(
            'postId'        => $post->ID,
            'daysOld'       => $days_old,
            'threshold'     => $settings['staleness_days'],
            'status'        => $freshness['label'],
            'statusClass'   => $freshness['class'],
            'lastModified'  => $post->post_modified,
            'lastReviewed'  => $last_reviewed ? $last_reviewed : null,
            'nonce'         => wp_create_nonce( 'cfm_nonce' ),
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'restUrl'       => rest_url( 'content-freshness/v1' ),
            'restNonce'     => wp_create_nonce( 'wp_rest' ),
            'i18n'          => array(
                'title'           => __( 'Content Freshness', 'content-freshness-monitor' ),
                'status'          => __( 'Status', 'content-freshness-monitor' ),
                'lastModified'    => __( 'Last Modified', 'content-freshness-monitor' ),
                'lastReviewed'    => __( 'Last Reviewed', 'content-freshness-monitor' ),
                'daysOld'         => __( 'Days Since Update', 'content-freshness-monitor' ),
                'threshold'       => __( 'Staleness Threshold', 'content-freshness-monitor' ),
                'days'            => __( 'days', 'content-freshness-monitor' ),
                'markReviewed'    => __( 'Mark as Reviewed', 'content-freshness-monitor' ),
                'reviewing'       => __( 'Marking...', 'content-freshness-monitor' ),
                'reviewed'        => __( 'Reviewed!', 'content-freshness-monitor' ),
                'never'           => __( 'Never', 'content-freshness-monitor' ),
                'fresh'           => __( 'Fresh', 'content-freshness-monitor' ),
                'aging'           => __( 'Aging', 'content-freshness-monitor' ),
                'stale'           => __( 'Stale', 'content-freshness-monitor' ),
                'freshDesc'       => __( 'This content is up to date.', 'content-freshness-monitor' ),
                'agingDesc'       => __( 'This content may need attention soon.', 'content-freshness-monitor' ),
                'staleDesc'       => __( 'This content needs to be reviewed and updated.', 'content-freshness-monitor' ),
                'viewSettings'    => __( 'View Settings', 'content-freshness-monitor' ),
            ),
            'settingsUrl'   => admin_url( 'options-general.php?page=cfm-settings' ),
        ) );

        // Enqueue styles
        wp_enqueue_style(
            'cfm-gutenberg-sidebar',
            CFM_PLUGIN_URL . 'assets/css/gutenberg-sidebar.css',
            array(),
            CFM_VERSION
        );
    }
}

new CFM_Gutenberg();
