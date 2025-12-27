<?php
/**
 * WP-CLI Commands for Content Freshness Monitor
 *
 * @package ContentFreshnessMonitor
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

/**
 * Manage content freshness from the command line.
 *
 * ## EXAMPLES
 *
 *     # Get freshness statistics
 *     $ wp content-freshness stats
 *
 *     # List stale posts
 *     $ wp content-freshness list --limit=10
 *
 *     # Check a specific post
 *     $ wp content-freshness check 123
 *
 *     # Mark a post as reviewed
 *     $ wp content-freshness review 123
 *
 *     # Export stale posts to CSV
 *     $ wp content-freshness export --output=/path/to/file.csv
 */
class CFM_CLI_Command extends WP_CLI_Command {

    /**
     * Display content freshness statistics.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp content-freshness stats
     *     $ wp content-freshness stats --format=json
     *
     * @subcommand stats
     */
    public function stats( $args, $assoc_args ) {
        $scanner = new CFM_Scanner();
        $stats   = $scanner->get_stats();

        $data = array(
            array(
                'metric' => 'Total Posts',
                'value'  => $stats['total'],
            ),
            array(
                'metric' => 'Fresh Content',
                'value'  => $stats['fresh'],
            ),
            array(
                'metric' => 'Aging Content',
                'value'  => $stats['aging'],
            ),
            array(
                'metric' => 'Stale Content',
                'value'  => $stats['stale'],
            ),
            array(
                'metric' => 'Stale Percentage',
                'value'  => $stats['stale_percentage'] . '%',
            ),
        );

        $format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

        WP_CLI\Utils\format_items( $format, $data, array( 'metric', 'value' ) );
    }

    /**
     * List stale posts.
     *
     * ## OPTIONS
     *
     * [--limit=<number>]
     * : Maximum number of posts to show.
     * ---
     * default: 20
     * ---
     *
     * [--post_type=<type>]
     * : Filter by post type (e.g., post, page).
     *
     * [--orderby=<field>]
     * : Order by field.
     * ---
     * default: modified
     * options:
     *   - modified
     *   - title
     *   - date
     * ---
     *
     * [--order=<direction>]
     * : Sort direction.
     * ---
     * default: ASC
     * options:
     *   - ASC
     *   - DESC
     * ---
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *   - yaml
     *   - ids
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp content-freshness list
     *     $ wp content-freshness list --limit=50 --format=csv
     *     $ wp content-freshness list --post_type=page
     *
     * @subcommand list
     */
    public function list_stale( $args, $assoc_args ) {
        $scanner = new CFM_Scanner();
        $limit   = absint( WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 20 ) );
        $orderby = WP_CLI\Utils\get_flag_value( $assoc_args, 'orderby', 'modified' );
        $order   = WP_CLI\Utils\get_flag_value( $assoc_args, 'order', 'ASC' );
        $format  = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

        $posts = $scanner->get_stale_posts( array(
            'posts_per_page' => $limit,
            'orderby'        => $orderby,
            'order'          => $order,
        ) );

        if ( empty( $posts ) ) {
            WP_CLI::success( 'No stale posts found. Your content is fresh!' );
            return;
        }

        // Filter by post type if specified.
        $post_type = WP_CLI\Utils\get_flag_value( $assoc_args, 'post_type', '' );
        if ( $post_type ) {
            $posts = array_filter( $posts, function( $post ) use ( $post_type ) {
                return $post['post_type'] === $post_type;
            } );
        }

        if ( 'ids' === $format ) {
            echo implode( ' ', array_column( $posts, 'ID' ) ) . "\n";
            return;
        }

        $data = array_map( function( $post ) {
            return array(
                'ID'           => $post['ID'],
                'title'        => mb_substr( $post['title'], 0, 40 ),
                'type'         => $post['post_type'],
                'days_old'     => $post['days_since_modified'],
                'last_updated' => $post['modified_date'],
                'status'       => $post['freshness_status'],
            );
        }, $posts );

        WP_CLI\Utils\format_items( $format, $data, array( 'ID', 'title', 'type', 'days_old', 'last_updated', 'status' ) );
    }

    /**
     * Check freshness status of a specific post.
     *
     * ## OPTIONS
     *
     * <post_id>
     * : The ID of the post to check.
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp content-freshness check 123
     *     $ wp content-freshness check 123 --format=json
     *
     * @subcommand check
     */
    public function check( $args, $assoc_args ) {
        $post_id = absint( $args[0] );
        $post    = get_post( $post_id );

        if ( ! $post ) {
            WP_CLI::error( "Post #{$post_id} not found." );
        }

        $scanner   = new CFM_Scanner();
        $settings  = CFM_Settings::get_settings();
        $threshold = $settings['threshold'];

        $modified     = strtotime( $post->post_modified );
        $days_old     = floor( ( time() - $modified ) / DAY_IN_SECONDS );
        $status       = $scanner->get_freshness_status( $days_old );
        $is_stale     = $scanner->is_stale( $post_id );
        $last_reviewed = get_post_meta( $post_id, '_cfm_last_reviewed', true );

        $data = array(
            array(
                'field' => 'Post ID',
                'value' => $post_id,
            ),
            array(
                'field' => 'Title',
                'value' => $post->post_title,
            ),
            array(
                'field' => 'Type',
                'value' => $post->post_type,
            ),
            array(
                'field' => 'Last Modified',
                'value' => $post->post_modified,
            ),
            array(
                'field' => 'Days Since Modified',
                'value' => $days_old,
            ),
            array(
                'field' => 'Freshness Status',
                'value' => ucfirst( $status ),
            ),
            array(
                'field' => 'Is Stale',
                'value' => $is_stale ? 'Yes' : 'No',
            ),
            array(
                'field' => 'Threshold (days)',
                'value' => $threshold,
            ),
            array(
                'field' => 'Last Reviewed',
                'value' => $last_reviewed ? wp_date( 'Y-m-d H:i:s', $last_reviewed ) : 'Never',
            ),
        );

        $format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

        WP_CLI\Utils\format_items( $format, $data, array( 'field', 'value' ) );
    }

    /**
     * Mark a post as reviewed.
     *
     * ## OPTIONS
     *
     * <post_id>...
     * : One or more post IDs to mark as reviewed.
     *
     * ## EXAMPLES
     *
     *     $ wp content-freshness review 123
     *     $ wp content-freshness review 123 456 789
     *
     * @subcommand review
     */
    public function review( $args, $assoc_args ) {
        $count = 0;

        foreach ( $args as $post_id ) {
            $post_id = absint( $post_id );
            $post    = get_post( $post_id );

            if ( ! $post ) {
                WP_CLI::warning( "Post #{$post_id} not found. Skipping." );
                continue;
            }

            update_post_meta( $post_id, '_cfm_last_reviewed', time() );
            WP_CLI::log( "Marked post #{$post_id} ({$post->post_title}) as reviewed." );
            $count++;
        }

        if ( $count > 0 ) {
            WP_CLI::success( "Marked {$count} post(s) as reviewed." );
        }
    }

    /**
     * Export stale posts to a CSV file.
     *
     * ## OPTIONS
     *
     * [--output=<file>]
     * : Output file path. Defaults to stale-content-{date}.csv in current directory.
     *
     * [--limit=<number>]
     * : Maximum number of posts to export.
     * ---
     * default: -1
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp content-freshness export
     *     $ wp content-freshness export --output=/tmp/stale-posts.csv
     *     $ wp content-freshness export --limit=100
     *
     * @subcommand export
     */
    public function export( $args, $assoc_args ) {
        $scanner = new CFM_Scanner();
        $limit   = intval( WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', -1 ) );
        $output  = WP_CLI\Utils\get_flag_value( $assoc_args, 'output', '' );

        if ( empty( $output ) ) {
            $output = getcwd() . '/stale-content-' . date( 'Y-m-d' ) . '.csv';
        }

        $posts = $scanner->get_stale_posts( array(
            'posts_per_page' => $limit,
        ) );

        if ( empty( $posts ) ) {
            WP_CLI::success( 'No stale posts to export. Your content is fresh!' );
            return;
        }

        $fp = fopen( $output, 'w' );

        if ( ! $fp ) {
            WP_CLI::error( "Cannot write to file: {$output}" );
        }

        // UTF-8 BOM for Excel compatibility.
        fwrite( $fp, "\xEF\xBB\xBF" );

        // Header row.
        fputcsv( $fp, array(
            'ID',
            'Title',
            'Type',
            'Author',
            'Last Modified',
            'Days Old',
            'Status',
            'Last Reviewed',
            'Edit URL',
            'View URL',
        ) );

        foreach ( $posts as $post ) {
            $last_reviewed = get_post_meta( $post['ID'], '_cfm_last_reviewed', true );

            fputcsv( $fp, array(
                $post['ID'],
                $post['title'],
                $post['post_type'],
                get_the_author_meta( 'display_name', $post['author_id'] ),
                $post['modified_date'],
                $post['days_since_modified'],
                $post['freshness_status'],
                $last_reviewed ? wp_date( 'Y-m-d H:i:s', $last_reviewed ) : '',
                admin_url( 'post.php?post=' . $post['ID'] . '&action=edit' ),
                get_permalink( $post['ID'] ),
            ) );
        }

        fclose( $fp );

        WP_CLI::success( sprintf(
            'Exported %d stale post(s) to: %s',
            count( $posts ),
            $output
        ) );
    }

    /**
     * Send a test notification email.
     *
     * ## OPTIONS
     *
     * [--to=<email>]
     * : Email address to send to. Defaults to configured recipient or admin email.
     *
     * ## EXAMPLES
     *
     *     $ wp content-freshness send-test-email
     *     $ wp content-freshness send-test-email --to=editor@example.com
     *
     * @subcommand send-test-email
     */
    public function send_test_email( $args, $assoc_args ) {
        if ( ! class_exists( 'CFM_Notifications' ) ) {
            WP_CLI::error( 'Notifications class not loaded.' );
        }

        $to = WP_CLI\Utils\get_flag_value( $assoc_args, 'to', '' );

        if ( empty( $to ) ) {
            $settings = CFM_Settings::get_settings();
            $to       = ! empty( $settings['email_recipient'] ) ? $settings['email_recipient'] : get_option( 'admin_email' );
        }

        $notifications = new CFM_Notifications();
        $result        = $notifications->send_digest();

        if ( $result ) {
            WP_CLI::success( "Test email sent to: {$to}" );
        } else {
            WP_CLI::error( 'Failed to send test email. Check WordPress mail settings.' );
        }
    }

    /**
     * Show current plugin settings.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - yaml
     * ---
     *
     * ## EXAMPLES
     *
     *     $ wp content-freshness settings
     *     $ wp content-freshness settings --format=json
     *
     * @subcommand settings
     */
    public function settings( $args, $assoc_args ) {
        $settings = CFM_Settings::get_settings();
        $format   = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );

        $data = array();

        foreach ( $settings as $key => $value ) {
            if ( is_array( $value ) ) {
                $value = implode( ', ', $value );
            } elseif ( is_bool( $value ) ) {
                $value = $value ? 'true' : 'false';
            }

            $data[] = array(
                'setting' => $key,
                'value'   => $value,
            );
        }

        WP_CLI\Utils\format_items( $format, $data, array( 'setting', 'value' ) );
    }
}

WP_CLI::add_command( 'content-freshness', 'CFM_CLI_Command' );
