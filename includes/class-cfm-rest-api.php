<?php
/**
 * REST API endpoints for Content Freshness Monitor
 *
 * Provides endpoints for headless CMS and external integrations.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFM_REST_API {

    /**
     * API namespace
     */
    const NAMESPACE = 'content-freshness-monitor/v1';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get content statistics
        register_rest_route(
            self::NAMESPACE,
            '/stats',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_stats' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
            )
        );

        // Get stale posts list
        register_rest_route(
            self::NAMESPACE,
            '/stale',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_stale_posts' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
                'args'                => $this->get_stale_posts_args(),
            )
        );

        // Check freshness of a specific post
        register_rest_route(
            self::NAMESPACE,
            '/post/(?P<id>\d+)/freshness',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_post_freshness' ),
                'permission_callback' => array( $this, 'check_read_permission' ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param ) && $param > 0;
                        },
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // Mark post as reviewed
        register_rest_route(
            self::NAMESPACE,
            '/post/(?P<id>\d+)/review',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'mark_post_reviewed' ),
                'permission_callback' => array( $this, 'check_write_permission' ),
                'args'                => array(
                    'id' => array(
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param ) && $param > 0;
                        },
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        // Bulk mark posts as reviewed
        register_rest_route(
            self::NAMESPACE,
            '/bulk-review',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'bulk_mark_reviewed' ),
                'permission_callback' => array( $this, 'check_write_permission' ),
                'args'                => array(
                    'post_ids' => array(
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            if ( ! is_array( $param ) ) {
                                return false;
                            }
                            foreach ( $param as $id ) {
                                if ( ! is_numeric( $id ) || $id <= 0 ) {
                                    return false;
                                }
                            }
                            return true;
                        },
                        'sanitize_callback' => function( $param ) {
                            return array_map( 'absint', $param );
                        },
                    ),
                ),
            )
        );

        // Get plugin settings (read-only)
        register_rest_route(
            self::NAMESPACE,
            '/settings',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_settings' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            )
        );
    }

    /**
     * Get arguments for stale posts endpoint
     */
    private function get_stale_posts_args() {
        return array(
            'per_page' => array(
                'default'           => 20,
                'validate_callback' => function( $param ) {
                    return is_numeric( $param ) && $param >= 1 && $param <= 100;
                },
                'sanitize_callback' => 'absint',
            ),
            'page'     => array(
                'default'           => 1,
                'validate_callback' => function( $param ) {
                    return is_numeric( $param ) && $param >= 1;
                },
                'sanitize_callback' => 'absint',
            ),
            'orderby'  => array(
                'default'           => 'modified',
                'validate_callback' => function( $param ) {
                    return in_array( $param, array( 'modified', 'title', 'date', 'ID' ), true );
                },
                'sanitize_callback' => 'sanitize_key',
            ),
            'order'    => array(
                'default'           => 'ASC',
                'validate_callback' => function( $param ) {
                    return in_array( strtoupper( $param ), array( 'ASC', 'DESC' ), true );
                },
                'sanitize_callback' => function( $param ) {
                    return strtoupper( $param );
                },
            ),
        );
    }

    /**
     * Check read permission
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if permitted, WP_Error otherwise.
     */
    public function check_read_permission( $request ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to access this resource.', 'content-freshness-monitor' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }
        return true;
    }

    /**
     * Check write permission
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if permitted, WP_Error otherwise.
     */
    public function check_write_permission( $request ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to modify this resource.', 'content-freshness-monitor' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }
        return true;
    }

    /**
     * Check admin permission
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if permitted, WP_Error otherwise.
     */
    public function check_admin_permission( $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to access settings.', 'content-freshness-monitor' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }
        return true;
    }

    /**
     * Get content statistics
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function get_stats( $request ) {
        $stats  = CFM_Scanner::get_stats();
        $health = CFM_Scanner::get_health_score( $stats );

        return rest_ensure_response( array(
            'total_posts'    => $stats['total'],
            'stale_posts'    => $stats['stale'],
            'fresh_posts'    => $stats['fresh'],
            'stale_percent'  => $stats['stale_percent'],
            'threshold_days' => $stats['threshold'],
            'health_score'   => $health['score'],
            'health_grade'   => $health['grade'],
            'health_label'   => $health['label'],
        ) );
    }

    /**
     * Get stale posts list
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function get_stale_posts( $request ) {
        $args = array(
            'per_page' => $request->get_param( 'per_page' ),
            'paged'    => $request->get_param( 'page' ),
            'orderby'  => $request->get_param( 'orderby' ),
            'order'    => $request->get_param( 'order' ),
        );

        $result = CFM_Scanner::get_stale_posts( $args );

        $response = rest_ensure_response( array(
            'posts'       => $result['posts'],
            'total'       => $result['total'],
            'total_pages' => $result['pages'],
            'page'        => $args['paged'],
            'per_page'    => $args['per_page'],
        ) );

        // Add pagination headers
        $response->header( 'X-WP-Total', $result['total'] );
        $response->header( 'X-WP-TotalPages', $result['pages'] );

        return $response;
    }

    /**
     * Get freshness status of a specific post
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_post_freshness( $request ) {
        $post_id = $request->get_param( 'id' );
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return new WP_Error(
                'rest_post_not_found',
                __( 'Post not found.', 'content-freshness-monitor' ),
                array( 'status' => 404 )
            );
        }

        $modified     = strtotime( $post->post_modified );
        $days_old     = floor( ( time() - $modified ) / DAY_IN_SECONDS );
        $status       = CFM_Scanner::get_freshness_status( $days_old );
        $stale_info   = CFM_Scanner::is_stale( $post_id );
        $last_reviewed = get_post_meta( $post_id, CFM_Scanner::REVIEWED_META_KEY, true );

        return rest_ensure_response( array(
            'post_id'       => $post_id,
            'title'         => $post->post_title,
            'post_type'     => $post->post_type,
            'status'        => $status['label'],
            'status_class'  => $status['class'],
            'is_stale'      => (bool) $stale_info,
            'days_old'      => $days_old,
            'last_modified' => $post->post_modified,
            'last_reviewed' => $last_reviewed ? $last_reviewed : null,
            'edit_url'      => get_edit_post_link( $post_id, 'raw' ),
            'view_url'      => get_permalink( $post_id ),
        ) );
    }

    /**
     * Mark a post as reviewed
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function mark_post_reviewed( $request ) {
        $post_id = $request->get_param( 'id' );
        $post    = get_post( $post_id );

        if ( ! $post ) {
            return new WP_Error(
                'rest_post_not_found',
                __( 'Post not found.', 'content-freshness-monitor' ),
                array( 'status' => 404 )
            );
        }

        $reviewed_date = current_time( 'mysql' );
        update_post_meta( $post_id, CFM_Scanner::REVIEWED_META_KEY, $reviewed_date );

        return rest_ensure_response( array(
            'success'       => true,
            'post_id'       => $post_id,
            'reviewed_date' => $reviewed_date,
            'message'       => __( 'Post marked as reviewed.', 'content-freshness-monitor' ),
        ) );
    }

    /**
     * Bulk mark posts as reviewed
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function bulk_mark_reviewed( $request ) {
        $post_ids      = $request->get_param( 'post_ids' );
        $reviewed_date = current_time( 'mysql' );
        $updated       = array();
        $failed        = array();

        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( $post ) {
                update_post_meta( $post_id, CFM_Scanner::REVIEWED_META_KEY, $reviewed_date );
                $updated[] = $post_id;
            } else {
                $failed[] = $post_id;
            }
        }

        return rest_ensure_response( array(
            'success'        => count( $failed ) === 0,
            'updated_count'  => count( $updated ),
            'updated_posts'  => $updated,
            'failed_posts'   => $failed,
            'reviewed_date'  => $reviewed_date,
            'message'        => sprintf(
                /* translators: %d: number of posts */
                _n(
                    '%d post marked as reviewed.',
                    '%d posts marked as reviewed.',
                    count( $updated ),
                    'content-freshness-monitor'
                ),
                count( $updated )
            ),
        ) );
    }

    /**
     * Get plugin settings
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function get_settings( $request ) {
        $settings = Content_Freshness_Monitor::get_settings();

        return rest_ensure_response( array(
            'threshold_days'      => $settings['staleness_days'],
            'monitored_post_types' => $settings['post_types'],
            'excluded_ids'        => $settings['exclude_ids'],
            'show_in_list'        => $settings['show_in_list'],
            'email_enabled'       => $settings['email_enabled'],
            'email_frequency'     => $settings['email_frequency'],
        ) );
    }
}

new CFM_REST_API();
