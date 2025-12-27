<?php
/**
 * Email notification handler for stale content alerts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFM_Notifications {

    /**
     * Cron hook name for admin digest
     */
    const CRON_HOOK = 'cfm_send_stale_digest';

    /**
     * Cron hook name for author notifications
     */
    const AUTHOR_CRON_HOOK = 'cfm_send_author_digest';

    /**
     * Constructor
     */
    public function __construct() {
        add_action( self::CRON_HOOK, array( $this, 'send_stale_digest' ) );
        add_action( self::AUTHOR_CRON_HOOK, array( $this, 'send_author_digests' ) );
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
        add_action( 'wp_ajax_cfm_send_test_email', array( $this, 'ajax_send_test_email' ) );
    }

    /**
     * Send test email via AJAX
     */
    public function ajax_send_test_email() {
        check_ajax_referer( 'cfm_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'content-freshness-monitor' ) ) );
        }

        $settings = Content_Freshness_Monitor::get_settings();
        $recipient = ! empty( $settings['email_recipient'] )
            ? $settings['email_recipient']
            : get_option( 'admin_email' );

        $sent = self::send_test_email( $recipient );

        if ( $sent ) {
            wp_send_json_success( array(
                'message' => sprintf(
                    /* translators: %s: email address */
                    __( 'Test email sent to %s', 'content-freshness-monitor' ),
                    $recipient
                ),
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to send test email. Check your WordPress mail settings.', 'content-freshness-monitor' ),
            ) );
        }
    }

    /**
     * Add custom cron schedules
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_cron_schedules( $schedules ) {
        $schedules['cfm_weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Once Weekly', 'content-freshness-monitor' ),
        );
        $schedules['cfm_monthly'] = array(
            'interval' => MONTH_IN_SECONDS,
            'display'  => __( 'Once Monthly', 'content-freshness-monitor' ),
        );
        return $schedules;
    }

    /**
     * Schedule the digest email
     *
     * @param string $frequency Frequency: daily, weekly, monthly
     */
    public static function schedule_digest( $frequency ) {
        // Clear existing schedule
        self::unschedule_digest();

        if ( 'disabled' === $frequency ) {
            return;
        }

        $schedule = 'daily';
        if ( 'weekly' === $frequency ) {
            $schedule = 'cfm_weekly';
        } elseif ( 'monthly' === $frequency ) {
            $schedule = 'cfm_monthly';
        }

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, $schedule, self::CRON_HOOK );
        }
    }

    /**
     * Unschedule the digest email
     */
    public static function unschedule_digest() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    /**
     * Send the stale content digest email
     */
    public function send_stale_digest() {
        $settings = Content_Freshness_Monitor::get_settings();

        // Check if notifications are enabled
        if ( empty( $settings['email_enabled'] ) ) {
            return;
        }

        // Get stale content stats
        $stats = CFM_Scanner::get_stats();

        // Only send if there's stale content
        if ( $stats['stale'] < 1 ) {
            return;
        }

        // Get recipient
        $recipient = ! empty( $settings['email_recipient'] )
            ? $settings['email_recipient']
            : get_option( 'admin_email' );

        // Build email
        $subject = $this->get_email_subject( $stats );
        $message = $this->get_email_body( $stats );
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        /**
         * Filter the email arguments before sending the admin digest.
         *
         * @since 2.7.0
         * @param array $email_args {
         *     Email arguments.
         *     @type string $recipient Email recipient.
         *     @type string $subject   Email subject.
         *     @type string $message   Email body HTML.
         *     @type array  $headers   Email headers.
         * }
         * @param array $stats Content statistics.
         */
        $email_args = apply_filters( 'cfm_digest_email_args', array(
            'recipient' => $recipient,
            'subject'   => $subject,
            'message'   => $message,
            'headers'   => $headers,
        ), $stats );

        // Send email
        wp_mail( $email_args['recipient'], $email_args['subject'], $email_args['message'], $email_args['headers'] );

        /**
         * Fires after the admin digest email is sent.
         *
         * @since 2.7.0
         * @param string $recipient Email recipient.
         * @param array  $stats     Content statistics.
         */
        do_action( 'cfm_digest_email_sent', $email_args['recipient'], $stats );
    }

    /**
     * Get email subject
     *
     * @param array $stats Content statistics
     * @return string Email subject
     */
    private function get_email_subject( $stats ) {
        $site_name = get_bloginfo( 'name' );
        return sprintf(
            /* translators: 1: site name, 2: number of stale posts */
            __( '[%1$s] Content Freshness Alert: %2$d stale posts need attention', 'content-freshness-monitor' ),
            $site_name,
            $stats['stale']
        );
    }

    /**
     * Get email body
     *
     * @param array $stats Content statistics
     * @return string Email body HTML
     */
    private function get_email_body( $stats ) {
        $settings = Content_Freshness_Monitor::get_settings();
        $site_name = get_bloginfo( 'name' );
        $admin_url = admin_url( 'admin.php?page=content-freshness' );

        // Get top stale posts
        $stale_posts = CFM_Scanner::get_stale_posts( array(
            'per_page' => 10,
            'orderby'  => 'modified',
            'order'    => 'ASC',
        ) );

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0073aa; color: #fff; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; background: #f9f9f9; }
                .stats-table { width: 100%; margin: 20px 0; border-spacing: 10px; }
                .stat-box { text-align: center; padding: 20px 15px; background: #fff; border-radius: 8px; border: 1px solid #e0e0e0; }
                .stat-number { font-size: 36px; font-weight: bold; color: #0073aa; display: block; margin-bottom: 5px; }
                .stat-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
                .stale-number { color: #d63638; }
                .post-list { background: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; border: 1px solid #e0e0e0; }
                .post-item { padding: 12px 0; border-bottom: 1px solid #eee; }
                .post-item:last-child { border-bottom: none; }
                .post-title { font-weight: 600; color: #0073aa; text-decoration: none; font-size: 15px; }
                .post-title:hover { text-decoration: underline; }
                .post-meta { font-size: 12px; color: #666; margin-top: 4px; }
                .button { display: inline-block; background: #0073aa; color: #ffffff !important; padding: 14px 28px; text-decoration: none; border-radius: 5px; font-weight: 600; font-size: 15px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                .footer a { color: #0073aa; text-decoration: underline; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php esc_html_e( 'Content Freshness Report', 'content-freshness-monitor' ); ?></h1>
                    <p><?php echo esc_html( $site_name ); ?></p>
                </div>

                <div class="content">
                    <p><?php esc_html_e( 'Here is your content freshness summary:', 'content-freshness-monitor' ); ?></p>

                    <table class="stats-table" cellpadding="0" cellspacing="10" align="center" style="margin: 20px auto;">
                        <tr>
                            <td class="stat-box" width="33%">
                                <span class="stat-number"><?php echo esc_html( $stats['total'] ); ?></span>
                                <span class="stat-label"><?php esc_html_e( 'Total Posts', 'content-freshness-monitor' ); ?></span>
                            </td>
                            <td class="stat-box" width="33%">
                                <span class="stat-number stale-number"><?php echo esc_html( $stats['stale'] ); ?></span>
                                <span class="stat-label"><?php esc_html_e( 'Stale Posts', 'content-freshness-monitor' ); ?></span>
                            </td>
                            <td class="stat-box" width="33%">
                                <span class="stat-number"><?php echo esc_html( $stats['stale_percent'] ); ?>%</span>
                                <span class="stat-label"><?php esc_html_e( 'Need Attention', 'content-freshness-monitor' ); ?></span>
                            </td>
                        </tr>
                    </table>

                    <p>
                        <?php
                        printf(
                            /* translators: %d: staleness threshold in days */
                            esc_html__( 'Posts are considered stale if not updated in the last %d days.', 'content-freshness-monitor' ),
                            $settings['staleness_days']
                        );
                        ?>
                    </p>

                    <?php if ( ! empty( $stale_posts['posts'] ) ) : ?>
                        <div class="post-list">
                            <h3><?php esc_html_e( 'Posts Needing Attention', 'content-freshness-monitor' ); ?></h3>
                            <?php foreach ( $stale_posts['posts'] as $post ) : ?>
                                <div class="post-item">
                                    <a href="<?php echo esc_url( $post['edit_link'] ); ?>" class="post-title">
                                        <?php echo esc_html( $post['title'] ); ?>
                                    </a>
                                    <div class="post-meta">
                                        <?php
                                        printf(
                                            /* translators: 1: post type, 2: days ago string, 3: author name */
                                            esc_html__( '%1$s &bull; Last updated %2$s &bull; By %3$s', 'content-freshness-monitor' ),
                                            esc_html( ucfirst( $post['post_type'] ) ),
                                            esc_html( $post['date_ago'] ),
                                            esc_html( $post['author'] )
                                        );
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <p style="text-align: center;">
                        <a href="<?php echo esc_url( $admin_url ); ?>" class="button">
                            <?php esc_html_e( 'View All Stale Content', 'content-freshness-monitor' ); ?>
                        </a>
                    </p>
                </div>

                <div class="footer">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: plugin name */
                            esc_html__( 'This email was sent by %s.', 'content-freshness-monitor' ),
                            'Content Freshness Monitor'
                        );
                        ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_url( admin_url( 'options-general.php?page=cfm-settings' ) ); ?>">
                            <?php esc_html_e( 'Manage notification settings', 'content-freshness-monitor' ); ?>
                        </a>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Send a test email
     *
     * @param string $recipient Email recipient
     * @return bool Whether email was sent
     */
    public static function send_test_email( $recipient ) {
        $stats = CFM_Scanner::get_stats();

        $instance = new self();
        $subject = '[TEST] ' . $instance->get_email_subject( $stats );
        $message = $instance->get_email_body( $stats );
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        return wp_mail( $recipient, $subject, $message, $headers );
    }

    /**
     * Schedule author digest emails
     *
     * @param string $frequency Frequency: daily, weekly, monthly
     */
    public static function schedule_author_digest( $frequency ) {
        // Clear existing schedule
        self::unschedule_author_digest();

        if ( 'disabled' === $frequency ) {
            return;
        }

        $schedule = 'daily';
        if ( 'weekly' === $frequency ) {
            $schedule = 'cfm_weekly';
        } elseif ( 'monthly' === $frequency ) {
            $schedule = 'cfm_monthly';
        }

        if ( ! wp_next_scheduled( self::AUTHOR_CRON_HOOK ) ) {
            // Offset by 2 hours from admin digest to spread load
            wp_schedule_event( time() + ( 3 * HOUR_IN_SECONDS ), $schedule, self::AUTHOR_CRON_HOOK );
        }
    }

    /**
     * Unschedule author digest emails
     */
    public static function unschedule_author_digest() {
        $timestamp = wp_next_scheduled( self::AUTHOR_CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::AUTHOR_CRON_HOOK );
        }
        wp_clear_scheduled_hook( self::AUTHOR_CRON_HOOK );
    }

    /**
     * Send personalized digest emails to all authors with stale content
     */
    public function send_author_digests() {
        $settings = Content_Freshness_Monitor::get_settings();

        // Check if author notifications are enabled
        if ( empty( $settings['author_notifications'] ) ) {
            return;
        }

        $min_stale = isset( $settings['author_min_stale'] ) ? absint( $settings['author_min_stale'] ) : 1;

        // Get all authors with stale content
        $authors_with_stale = $this->get_authors_with_stale_content();

        foreach ( $authors_with_stale as $author_id => $stale_count ) {
            // Skip if below minimum threshold
            if ( $stale_count < $min_stale ) {
                continue;
            }

            $author = get_userdata( $author_id );
            if ( ! $author || ! $author->user_email ) {
                continue;
            }

            // Check if user can edit posts
            if ( ! user_can( $author_id, 'edit_posts' ) ) {
                continue;
            }

            $this->send_author_digest( $author );
        }
    }

    /**
     * Get authors with stale content and their stale post counts
     *
     * @return array Author ID => stale post count
     */
    private function get_authors_with_stale_content() {
        $settings = Content_Freshness_Monitor::get_settings();
        $post_types = $settings['post_types'];
        $threshold = absint( $settings['staleness_days'] );
        $stale_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$threshold} days" ) );

        // Get excluded IDs
        $exclude_ids = array();
        if ( ! empty( $settings['exclude_ids'] ) ) {
            $exclude_ids = array_map( 'absint', explode( ',', $settings['exclude_ids'] ) );
            $exclude_ids = array_filter( $exclude_ids );
        }

        $args = array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'date_query'     => array(
                array(
                    'column' => 'post_modified',
                    'before' => $stale_date,
                ),
            ),
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_cfm_last_reviewed',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => '_cfm_last_reviewed',
                    'value'   => $stale_date,
                    'compare' => '<',
                    'type'    => 'DATETIME',
                ),
            ),
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );

        if ( ! empty( $exclude_ids ) ) {
            $args['post__not_in'] = $exclude_ids;
        }

        $query = new WP_Query( $args );
        $post_ids = $query->posts;

        // Group by author
        $authors = array();
        foreach ( $post_ids as $post_id ) {
            $author_id = get_post_field( 'post_author', $post_id );
            if ( ! isset( $authors[ $author_id ] ) ) {
                $authors[ $author_id ] = 0;
            }
            $authors[ $author_id ]++;
        }

        return $authors;
    }

    /**
     * Send digest email to a specific author
     *
     * @param WP_User $author Author user object
     */
    private function send_author_digest( $author ) {
        // Get author's stale posts
        $stale_posts = $this->get_author_stale_posts( $author->ID );

        if ( empty( $stale_posts ) ) {
            return;
        }

        $subject = $this->get_author_email_subject( $author, count( $stale_posts ) );
        $message = $this->get_author_email_body( $author, $stale_posts );
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        wp_mail( $author->user_email, $subject, $message, $headers );
    }

    /**
     * Get stale posts for a specific author
     *
     * @param int $author_id Author user ID
     * @return array Array of stale post data
     */
    private function get_author_stale_posts( $author_id ) {
        $settings = Content_Freshness_Monitor::get_settings();
        $post_types = $settings['post_types'];
        $threshold = absint( $settings['staleness_days'] );
        $stale_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$threshold} days" ) );

        // Get excluded IDs
        $exclude_ids = array();
        if ( ! empty( $settings['exclude_ids'] ) ) {
            $exclude_ids = array_map( 'absint', explode( ',', $settings['exclude_ids'] ) );
            $exclude_ids = array_filter( $exclude_ids );
        }

        $args = array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'author'         => $author_id,
            'posts_per_page' => 20, // Limit to 20 posts per email
            'orderby'        => 'modified',
            'order'          => 'ASC',
            'date_query'     => array(
                array(
                    'column' => 'post_modified',
                    'before' => $stale_date,
                ),
            ),
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_cfm_last_reviewed',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => '_cfm_last_reviewed',
                    'value'   => $stale_date,
                    'compare' => '<',
                    'type'    => 'DATETIME',
                ),
            ),
        );

        if ( ! empty( $exclude_ids ) ) {
            $args['post__not_in'] = $exclude_ids;
        }

        $query = new WP_Query( $args );
        $posts = array();

        foreach ( $query->posts as $post ) {
            $modified_date = strtotime( $post->post_modified );
            $days_ago = floor( ( time() - $modified_date ) / DAY_IN_SECONDS );

            $posts[] = array(
                'ID'           => $post->ID,
                'title'        => $post->post_title,
                'post_type'    => $post->post_type,
                'modified'     => $post->post_modified,
                'days_ago'     => $days_ago,
                'edit_link'    => get_edit_post_link( $post->ID, 'raw' ),
                'view_link'    => get_permalink( $post->ID ),
            );
        }

        return $posts;
    }

    /**
     * Get author email subject
     *
     * @param WP_User $author Author user object
     * @param int     $stale_count Number of stale posts
     * @return string Email subject
     */
    private function get_author_email_subject( $author, $stale_count ) {
        $site_name = get_bloginfo( 'name' );
        return sprintf(
            /* translators: 1: site name, 2: author display name, 3: number of stale posts */
            __( '[%1$s] %2$s, you have %3$d posts that need updating', 'content-freshness-monitor' ),
            $site_name,
            $author->display_name,
            $stale_count
        );
    }

    /**
     * Get author email body
     *
     * @param WP_User $author Author user object
     * @param array   $stale_posts Array of stale post data
     * @return string Email body HTML
     */
    private function get_author_email_body( $author, $stale_posts ) {
        $settings = Content_Freshness_Monitor::get_settings();
        $site_name = get_bloginfo( 'name' );
        $stale_count = count( $stale_posts );

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0073aa; color: #fff; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .greeting { font-size: 18px; margin-bottom: 20px; }
                .summary-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 15px; margin: 20px 0; text-align: center; }
                .summary-number { font-size: 36px; font-weight: bold; color: #856404; }
                .post-list { background: #fff; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .post-item { padding: 12px 0; border-bottom: 1px solid #eee; }
                .post-item:last-child { border-bottom: none; }
                .post-title { font-weight: 600; color: #0073aa; text-decoration: none; display: block; margin-bottom: 5px; }
                .post-meta { font-size: 12px; color: #666; }
                .post-actions { margin-top: 8px; }
                .post-actions a { display: inline-block; font-size: 12px; color: #0073aa; margin-right: 15px; text-decoration: none; }
                .post-actions a:hover { text-decoration: underline; }
                .days-badge { display: inline-block; background: #d63638; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
                .tips-box { background: #e7f5ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0; }
                .tips-title { font-weight: 600; margin-bottom: 10px; }
                .tips-list { margin: 0; padding-left: 20px; }
                .tips-list li { margin-bottom: 5px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php esc_html_e( 'Your Content Needs Attention', 'content-freshness-monitor' ); ?></h1>
                    <p><?php echo esc_html( $site_name ); ?></p>
                </div>

                <div class="content">
                    <p class="greeting">
                        <?php
                        printf(
                            /* translators: %s: author display name */
                            esc_html__( 'Hi %s,', 'content-freshness-monitor' ),
                            esc_html( $author->display_name )
                        );
                        ?>
                    </p>

                    <p>
                        <?php esc_html_e( 'Some of your content hasn\'t been updated in a while. Keeping your posts fresh helps maintain SEO rankings and provides value to your readers.', 'content-freshness-monitor' ); ?>
                    </p>

                    <div class="summary-box">
                        <div class="summary-number"><?php echo esc_html( $stale_count ); ?></div>
                        <div>
                            <?php
                            printf(
                                /* translators: %d: staleness threshold in days */
                                esc_html( _n(
                                    'post not updated in over %d days',
                                    'posts not updated in over %d days',
                                    $stale_count,
                                    'content-freshness-monitor'
                                ) ),
                                absint( $settings['staleness_days'] )
                            );
                            ?>
                        </div>
                    </div>

                    <div class="post-list">
                        <h3><?php esc_html_e( 'Posts Needing Your Attention', 'content-freshness-monitor' ); ?></h3>
                        <?php foreach ( $stale_posts as $post ) : ?>
                            <div class="post-item">
                                <a href="<?php echo esc_url( $post['edit_link'] ); ?>" class="post-title">
                                    <?php echo esc_html( $post['title'] ); ?>
                                </a>
                                <div class="post-meta">
                                    <span class="days-badge">
                                        <?php
                                        printf(
                                            /* translators: %d: number of days */
                                            esc_html( _n( '%d day old', '%d days old', $post['days_ago'], 'content-freshness-monitor' ) ),
                                            absint( $post['days_ago'] )
                                        );
                                        ?>
                                    </span>
                                    &nbsp;&bull;&nbsp;
                                    <?php echo esc_html( ucfirst( $post['post_type'] ) ); ?>
                                </div>
                                <div class="post-actions">
                                    <a href="<?php echo esc_url( $post['edit_link'] ); ?>">
                                        &#9998; <?php esc_html_e( 'Edit', 'content-freshness-monitor' ); ?>
                                    </a>
                                    <a href="<?php echo esc_url( $post['view_link'] ); ?>">
                                        &#128065; <?php esc_html_e( 'View', 'content-freshness-monitor' ); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="tips-box">
                        <div class="tips-title"><?php esc_html_e( 'Quick Refresh Tips:', 'content-freshness-monitor' ); ?></div>
                        <ul class="tips-list">
                            <li><?php esc_html_e( 'Update statistics and data references', 'content-freshness-monitor' ); ?></li>
                            <li><?php esc_html_e( 'Add new insights or recent developments', 'content-freshness-monitor' ); ?></li>
                            <li><?php esc_html_e( 'Check for broken links', 'content-freshness-monitor' ); ?></li>
                            <li><?php esc_html_e( 'Improve readability and formatting', 'content-freshness-monitor' ); ?></li>
                        </ul>
                    </div>
                </div>

                <div class="footer">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: plugin name */
                            esc_html__( 'This email was sent by %s.', 'content-freshness-monitor' ),
                            'Content Freshness Monitor'
                        );
                        ?>
                    </p>
                    <p>
                        <?php esc_html_e( 'Contact your site administrator to adjust notification settings.', 'content-freshness-monitor' ); ?>
                    </p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

new CFM_Notifications();
