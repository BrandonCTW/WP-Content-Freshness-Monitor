<?php
/**
 * Content scanner for finding stale posts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFM_Scanner {

    /**
     * Meta key for last reviewed date
     */
    const REVIEWED_META_KEY = '_cfm_last_reviewed';

    /**
     * Transient key for stats cache
     */
    const STATS_CACHE_KEY = 'cfm_stats_cache';

    /**
     * Transient key for stale posts count cache
     */
    const COUNT_CACHE_KEY = 'cfm_stale_count_cache';

    /**
     * Cache expiration in seconds (15 minutes)
     */
    const CACHE_EXPIRATION = 900;

    /**
     * Build date query based on settings
     *
     * @param string $cutoff_date Cutoff date in Y-m-d H:i:s format
     * @param array  $settings    Plugin settings
     * @return array Date query array for WP_Query
     */
    private static function build_date_query( $cutoff_date, $settings ) {
        $date_check_type = isset( $settings['date_check_type'] ) ? $settings['date_check_type'] : 'modified';

        switch ( $date_check_type ) {
            case 'published':
                return array(
                    array(
                        'column' => 'post_date',
                        'before' => $cutoff_date,
                    ),
                );

            case 'oldest':
                // Flag if either date is older than threshold
                return array(
                    'relation' => 'OR',
                    array(
                        'column' => 'post_modified',
                        'before' => $cutoff_date,
                    ),
                    array(
                        'column' => 'post_date',
                        'before' => $cutoff_date,
                    ),
                );

            case 'modified':
            default:
                return array(
                    array(
                        'column' => 'post_modified',
                        'before' => $cutoff_date,
                    ),
                );
        }
    }

    /**
     * Get the relevant date for a post based on settings
     *
     * @param WP_Post $post     Post object
     * @param array   $settings Plugin settings
     * @return string Date string
     */
    private static function get_relevant_date( $post, $settings ) {
        $date_check_type = isset( $settings['date_check_type'] ) ? $settings['date_check_type'] : 'modified';

        switch ( $date_check_type ) {
            case 'published':
                return $post->post_date;

            case 'oldest':
                // Return the older of the two dates
                $modified = strtotime( $post->post_modified );
                $published = strtotime( $post->post_date );
                return $published < $modified ? $post->post_date : $post->post_modified;

            case 'modified':
            default:
                return $post->post_modified;
        }
    }

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_ajax_cfm_mark_reviewed', array( $this, 'ajax_mark_reviewed' ) );
        add_action( 'wp_ajax_cfm_bulk_mark_reviewed', array( $this, 'ajax_bulk_mark_reviewed' ) );

        // Invalidate cache when posts are modified
        add_action( 'save_post', array( $this, 'invalidate_cache' ) );
        add_action( 'delete_post', array( $this, 'invalidate_cache' ) );
        add_action( 'trashed_post', array( $this, 'invalidate_cache' ) );
        add_action( 'untrashed_post', array( $this, 'invalidate_cache' ) );
    }

    /**
     * Invalidate stats cache when content changes
     *
     * @param int $post_id Post ID
     */
    public function invalidate_cache( $post_id = 0 ) {
        // Skip revisions
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        delete_transient( self::STATS_CACHE_KEY );
        delete_transient( self::COUNT_CACHE_KEY );

        // Also invalidate multisite network cache if available
        if ( is_multisite() ) {
            delete_site_transient( 'cfm_network_stats_cache' );
        }
    }

    /**
     * Get stale posts
     *
     * @param array $args Query arguments
     * @return array Array with 'posts' and 'total' keys
     */
    public static function get_stale_posts( $args = array() ) {
        $settings = Content_Freshness_Monitor::get_settings();

        $defaults = array(
            'per_page' => 20,
            'paged'    => 1,
            'orderby'  => 'modified',
            'order'    => 'ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        // Get excluded IDs
        $exclude_ids = array();
        if ( ! empty( $settings['exclude_ids'] ) ) {
            $exclude_ids = array_map( 'absint', explode( ',', $settings['exclude_ids'] ) );
        }

        // Check if per-type thresholds are enabled
        $use_per_type = ! empty( $settings['enable_per_type'] );

        if ( $use_per_type ) {
            // With per-type thresholds, we need to query each type separately
            // and merge the results, which is more complex for pagination
            return self::get_stale_posts_per_type( $args, $settings, $exclude_ids );
        }

        // Standard query with global threshold
        $staleness_days = absint( $settings['staleness_days'] );
        $cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$staleness_days} days" ) );

        $query_args = array(
            'post_type'      => $settings['post_types'],
            'post_status'    => 'publish',
            'posts_per_page' => $args['per_page'],
            'paged'          => $args['paged'],
            'orderby'        => $args['orderby'],
            'order'          => $args['order'],
            'date_query'     => self::build_date_query( $cutoff_date, $settings ),
            'post__not_in'   => $exclude_ids,
        );

        $query = new WP_Query( $query_args );

        $posts = array();
        foreach ( $query->posts as $post ) {
            $posts[] = self::prepare_post_data( $post );
        }

        return array(
            'posts' => $posts,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
        );
    }

    /**
     * Get stale posts with per-type thresholds
     *
     * @param array $args Query arguments
     * @param array $settings Plugin settings
     * @param array $exclude_ids Post IDs to exclude
     * @return array Array with 'posts' and 'total' keys
     */
    private static function get_stale_posts_per_type( $args, $settings, $exclude_ids ) {
        $all_stale_ids = array();

        // First, get all stale post IDs for each type
        foreach ( $settings['post_types'] as $post_type ) {
            $threshold = Content_Freshness_Monitor::get_threshold_for_type( $post_type );
            $cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$threshold} days" ) );

            $query_args = array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'date_query'     => self::build_date_query( $cutoff_date, $settings ),
                'post__not_in'   => $exclude_ids,
                'no_found_rows'  => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            );

            $query = new WP_Query( $query_args );
            $all_stale_ids = array_merge( $all_stale_ids, $query->posts );
        }

        $total = count( $all_stale_ids );

        if ( empty( $all_stale_ids ) ) {
            return array(
                'posts' => array(),
                'total' => 0,
                'pages' => 0,
            );
        }

        // Now query the actual posts with pagination
        $query_args = array(
            'post_type'      => $settings['post_types'],
            'post_status'    => 'publish',
            'posts_per_page' => $args['per_page'],
            'paged'          => $args['paged'],
            'orderby'        => $args['orderby'],
            'order'          => $args['order'],
            'post__in'       => $all_stale_ids,
        );

        $query = new WP_Query( $query_args );

        $posts = array();
        foreach ( $query->posts as $post ) {
            $posts[] = self::prepare_post_data( $post );
        }

        return array(
            'posts' => $posts,
            'total' => $total,
            'pages' => ceil( $total / $args['per_page'] ),
        );
    }

    /**
     * Prepare post data for display
     *
     * @param WP_Post $post Post object
     * @return array Prepared data
     */
    private static function prepare_post_data( $post ) {
        $settings = Content_Freshness_Monitor::get_settings();
        $relevant_date = self::get_relevant_date( $post, $settings );
        $date_timestamp = strtotime( $relevant_date );
        $now = time();
        $days_old = floor( ( $now - $date_timestamp ) / DAY_IN_SECONDS );

        $last_reviewed = get_post_meta( $post->ID, self::REVIEWED_META_KEY, true );

        $data = array(
            'ID'            => $post->ID,
            'title'         => $post->post_title,
            'edit_link'     => get_edit_post_link( $post->ID, 'raw' ),
            'view_link'     => get_permalink( $post->ID ),
            'post_type'     => $post->post_type,
            'date'          => $relevant_date,
            'modified'      => $post->post_modified,
            'published'     => $post->post_date,
            'date_ago'      => sprintf(
                /* translators: %d: number of days */
                _n( '%d day ago', '%d days ago', $days_old, 'content-freshness-monitor' ),
                $days_old
            ),
            'days_old'      => $days_old,
            'last_reviewed' => $last_reviewed ? $last_reviewed : null,
            'author'        => get_the_author_meta( 'display_name', $post->post_author ),
        );

        /**
         * Filter the prepared post data for stale content display.
         *
         * @since 2.7.0
         * @param array   $data Prepared post data array.
         * @param WP_Post $post The original post object.
         */
        return apply_filters( 'cfm_post_data', $data, $post );
    }

    /**
     * Get content statistics with caching
     *
     * @param bool $force_refresh Force cache refresh
     * @return array Statistics
     */
    public static function get_stats( $force_refresh = false ) {
        // Try to get cached stats
        if ( ! $force_refresh ) {
            $cached = get_transient( self::STATS_CACHE_KEY );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $settings = Content_Freshness_Monitor::get_settings();
        $use_per_type = ! empty( $settings['enable_per_type'] );

        // Get excluded IDs
        $exclude_ids = array();
        if ( ! empty( $settings['exclude_ids'] ) ) {
            $exclude_ids = array_map( 'absint', explode( ',', $settings['exclude_ids'] ) );
        }

        // Total published posts
        $total_args = array(
            'post_type'      => $settings['post_types'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post__not_in'   => $exclude_ids,
            'no_found_rows'  => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );
        $total_query = new WP_Query( $total_args );
        $total = $total_query->found_posts;

        // Calculate stale count based on threshold mode
        if ( $use_per_type ) {
            $stale = self::count_stale_per_type( $settings, $exclude_ids );
        } else {
            $staleness_days = absint( $settings['staleness_days'] );
            $cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$staleness_days} days" ) );

            $stale_args = array(
                'post_type'      => $settings['post_types'],
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'post__not_in'   => $exclude_ids,
                'date_query'     => self::build_date_query( $cutoff_date, $settings ),
                'no_found_rows'  => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            );
            $stale_query = new WP_Query( $stale_args );
            $stale = $stale_query->found_posts;
        }

        // Fresh posts
        $fresh = $total - $stale;

        // Percentage
        $stale_percent = $total > 0 ? round( ( $stale / $total ) * 100 ) : 0;

        // Get the display threshold (global or note about per-type)
        $threshold = absint( $settings['staleness_days'] );

        $stats = array(
            'total'         => $total,
            'stale'         => $stale,
            'fresh'         => $fresh,
            'stale_percent' => $stale_percent,
            'threshold'     => $threshold,
            'per_type'      => $use_per_type,
            'cached_at'     => current_time( 'mysql' ),
        );

        /**
         * Filter the content freshness statistics.
         *
         * @since 2.7.0
         * @param array $stats Statistics array with total, stale, fresh, stale_percent, threshold, per_type, cached_at.
         */
        $stats = apply_filters( 'cfm_stats', $stats );

        // Cache the results
        set_transient( self::STATS_CACHE_KEY, $stats, self::CACHE_EXPIRATION );

        return $stats;
    }

    /**
     * Count stale posts with per-type thresholds
     *
     * @param array $settings Plugin settings
     * @param array $exclude_ids Post IDs to exclude
     * @return int Total stale post count
     */
    private static function count_stale_per_type( $settings, $exclude_ids ) {
        $stale_count = 0;

        foreach ( $settings['post_types'] as $post_type ) {
            $threshold = Content_Freshness_Monitor::get_threshold_for_type( $post_type );
            $cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$threshold} days" ) );

            $query_args = array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'post__not_in'   => $exclude_ids,
                'date_query'     => self::build_date_query( $cutoff_date, $settings ),
                'no_found_rows'  => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            );

            $query = new WP_Query( $query_args );
            $stale_count += $query->found_posts;
        }

        return $stale_count;
    }

    /**
     * Check if a post is stale
     *
     * @param int $post_id Post ID
     * @return bool|array False if fresh, array with data if stale
     */
    public static function is_stale( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return false;
        }

        $settings = Content_Freshness_Monitor::get_settings();

        // Check if post type is monitored
        if ( ! in_array( $post->post_type, $settings['post_types'], true ) ) {
            return false;
        }

        // Check if excluded
        $exclude_ids = array();
        if ( ! empty( $settings['exclude_ids'] ) ) {
            $exclude_ids = array_map( 'absint', explode( ',', $settings['exclude_ids'] ) );
        }
        if ( in_array( $post_id, $exclude_ids, true ) ) {
            return false;
        }

        // Get threshold for this post type
        $staleness_days = Content_Freshness_Monitor::get_threshold_for_type( $post->post_type );
        $cutoff = strtotime( "-{$staleness_days} days" );
        $relevant_date = self::get_relevant_date( $post, $settings );
        $date_timestamp = strtotime( $relevant_date );

        if ( $date_timestamp >= $cutoff ) {
            return false;
        }

        $days_old = floor( ( time() - $date_timestamp ) / DAY_IN_SECONDS );

        return array(
            'days_old'  => $days_old,
            'date'      => $relevant_date,
            'threshold' => $staleness_days,
        );
    }

    /**
     * Mark post as reviewed via AJAX
     */
    public function ajax_mark_reviewed() {
        check_ajax_referer( 'cfm_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'content-freshness-monitor' ) ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        if ( ! $post_id || ! get_post( $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid post.', 'content-freshness-monitor' ) ) );
        }

        $reviewed_date = current_time( 'mysql' );
        update_post_meta( $post_id, self::REVIEWED_META_KEY, $reviewed_date );

        /**
         * Fires after a post is marked as reviewed.
         *
         * @since 2.7.0
         * @param int    $post_id       The post ID that was reviewed.
         * @param string $reviewed_date The review timestamp.
         */
        do_action( 'cfm_post_reviewed', $post_id, $reviewed_date );

        wp_send_json_success( array(
            'message' => __( 'Marked as reviewed.', 'content-freshness-monitor' ),
            'date'    => $reviewed_date,
        ) );
    }

    /**
     * Bulk mark as reviewed via AJAX
     */
    public function ajax_bulk_mark_reviewed() {
        check_ajax_referer( 'cfm_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'content-freshness-monitor' ) ) );
        }

        $post_ids = isset( $_POST['post_ids'] ) ? array_map( 'absint', (array) $_POST['post_ids'] ) : array();

        if ( empty( $post_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'No posts selected.', 'content-freshness-monitor' ) ) );
        }

        $reviewed_date = current_time( 'mysql' );
        $count = 0;

        foreach ( $post_ids as $post_id ) {
            if ( get_post( $post_id ) ) {
                update_post_meta( $post_id, self::REVIEWED_META_KEY, $reviewed_date );
                $count++;

                /**
                 * Fires after a post is marked as reviewed.
                 *
                 * @since 2.7.0
                 * @param int    $post_id       The post ID that was reviewed.
                 * @param string $reviewed_date The review timestamp.
                 */
                do_action( 'cfm_post_reviewed', $post_id, $reviewed_date );
            }
        }

        /**
         * Fires after bulk posts are marked as reviewed.
         *
         * @since 2.7.0
         * @param array  $post_ids      Array of reviewed post IDs.
         * @param int    $count         Number of posts reviewed.
         * @param string $reviewed_date The review timestamp.
         */
        do_action( 'cfm_bulk_posts_reviewed', $post_ids, $count, $reviewed_date );

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %d: number of posts */
                _n( '%d post marked as reviewed.', '%d posts marked as reviewed.', $count, 'content-freshness-monitor' ),
                $count
            ),
            'count' => $count,
        ) );
    }

    /**
     * Calculate Content Health Score
     *
     * Returns a score from 0-100 and a letter grade based on content freshness.
     * Scoring considers: fresh percentage, aging content, and distribution.
     *
     * @param array|null $stats Optional stats array, will fetch if not provided
     * @return array Health score data with 'score', 'grade', 'label', 'class'
     */
    public static function get_health_score( $stats = null ) {
        if ( null === $stats ) {
            $stats = self::get_stats();
        }

        $total = $stats['total'];

        // If no content, return perfect score
        if ( $total === 0 ) {
            return array(
                'score' => 100,
                'grade' => 'A',
                'label' => __( 'Excellent', 'content-freshness-monitor' ),
                'class' => 'cfm-grade-a',
            );
        }

        // Calculate base score from fresh percentage
        $fresh_percent = 100 - $stats['stale_percent'];

        // The score is primarily based on fresh content percentage
        // but we apply a curve to make the grading more meaningful
        $score = $fresh_percent;

        // Determine grade and label
        if ( $score >= 90 ) {
            $health = array(
                'score' => $score,
                'grade' => 'A',
                'label' => __( 'Excellent', 'content-freshness-monitor' ),
                'class' => 'cfm-grade-a',
            );
        } elseif ( $score >= 80 ) {
            $health = array(
                'score' => $score,
                'grade' => 'B',
                'label' => __( 'Good', 'content-freshness-monitor' ),
                'class' => 'cfm-grade-b',
            );
        } elseif ( $score >= 70 ) {
            $health = array(
                'score' => $score,
                'grade' => 'C',
                'label' => __( 'Fair', 'content-freshness-monitor' ),
                'class' => 'cfm-grade-c',
            );
        } elseif ( $score >= 60 ) {
            $health = array(
                'score' => $score,
                'grade' => 'D',
                'label' => __( 'Poor', 'content-freshness-monitor' ),
                'class' => 'cfm-grade-d',
            );
        } else {
            $health = array(
                'score' => $score,
                'grade' => 'F',
                'label' => __( 'Critical', 'content-freshness-monitor' ),
                'class' => 'cfm-grade-f',
            );
        }

        /**
         * Filter the content health score data.
         *
         * @since 2.7.0
         * @param array      $health Health score data with score, grade, label, class.
         * @param array|null $stats  The statistics used to calculate the score.
         */
        return apply_filters( 'cfm_health_score', $health, $stats );
    }

    /**
     * Get freshness status label
     *
     * @param int         $days_old  Days since last modification
     * @param string|null $post_type Optional post type for per-type threshold
     * @return array Status with label and class
     */
    public static function get_freshness_status( $days_old, $post_type = null ) {
        // Get appropriate threshold
        if ( $post_type ) {
            $threshold = Content_Freshness_Monitor::get_threshold_for_type( $post_type );
        } else {
            $settings = Content_Freshness_Monitor::get_settings();
            $threshold = $settings['staleness_days'];
        }

        if ( $days_old < $threshold * 0.5 ) {
            return array(
                'label'     => __( 'Fresh', 'content-freshness-monitor' ),
                'class'     => 'cfm-fresh',
                'threshold' => $threshold,
            );
        } elseif ( $days_old < $threshold ) {
            return array(
                'label'     => __( 'Aging', 'content-freshness-monitor' ),
                'class'     => 'cfm-aging',
                'threshold' => $threshold,
            );
        } else {
            return array(
                'label'     => __( 'Stale', 'content-freshness-monitor' ),
                'class'     => 'cfm-stale',
                'threshold' => $threshold,
            );
        }
    }
}

new CFM_Scanner();
