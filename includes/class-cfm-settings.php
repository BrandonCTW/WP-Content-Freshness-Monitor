<?php
/**
 * Settings page handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CFM_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add settings page under Settings menu
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Content Freshness Settings', 'content-freshness-monitor' ),
            __( 'Content Freshness', 'content-freshness-monitor' ),
            'manage_options',
            'cfm-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'cfm_settings_group',
            'cfm_settings',
            array( $this, 'sanitize_settings' )
        );

        // General section
        add_settings_section(
            'cfm_general_section',
            __( 'General Settings', 'content-freshness-monitor' ),
            array( $this, 'general_section_callback' ),
            'cfm-settings'
        );

        // Staleness threshold
        add_settings_field(
            'staleness_days',
            __( 'Staleness Threshold (days)', 'content-freshness-monitor' ),
            array( $this, 'staleness_days_callback' ),
            'cfm-settings',
            'cfm_general_section'
        );

        // Date check type
        add_settings_field(
            'date_check_type',
            __( 'Date to Check', 'content-freshness-monitor' ),
            array( $this, 'date_check_type_callback' ),
            'cfm-settings',
            'cfm_general_section'
        );

        // Post types
        add_settings_field(
            'post_types',
            __( 'Post Types to Monitor', 'content-freshness-monitor' ),
            array( $this, 'post_types_callback' ),
            'cfm-settings',
            'cfm_general_section'
        );

        // Show in post list
        add_settings_field(
            'show_in_list',
            __( 'Show Freshness in Post List', 'content-freshness-monitor' ),
            array( $this, 'show_in_list_callback' ),
            'cfm-settings',
            'cfm_general_section'
        );

        // Exclude IDs
        add_settings_field(
            'exclude_ids',
            __( 'Exclude Post IDs', 'content-freshness-monitor' ),
            array( $this, 'exclude_ids_callback' ),
            'cfm-settings',
            'cfm_general_section'
        );

        // Per-type thresholds section
        add_settings_section(
            'cfm_per_type_section',
            __( 'Per-Type Thresholds', 'content-freshness-monitor' ),
            array( $this, 'per_type_section_callback' ),
            'cfm-settings'
        );

        // Enable per-type thresholds
        add_settings_field(
            'enable_per_type',
            __( 'Enable Custom Thresholds', 'content-freshness-monitor' ),
            array( $this, 'enable_per_type_callback' ),
            'cfm-settings',
            'cfm_per_type_section'
        );

        // Per-type threshold values
        add_settings_field(
            'per_type_thresholds',
            __( 'Content Type Thresholds', 'content-freshness-monitor' ),
            array( $this, 'per_type_thresholds_callback' ),
            'cfm-settings',
            'cfm_per_type_section'
        );

        // Email notifications section
        add_settings_section(
            'cfm_email_section',
            __( 'Email Notifications', 'content-freshness-monitor' ),
            array( $this, 'email_section_callback' ),
            'cfm-settings'
        );

        // Enable email
        add_settings_field(
            'email_enabled',
            __( 'Enable Email Alerts', 'content-freshness-monitor' ),
            array( $this, 'email_enabled_callback' ),
            'cfm-settings',
            'cfm_email_section'
        );

        // Email frequency
        add_settings_field(
            'email_frequency',
            __( 'Email Frequency', 'content-freshness-monitor' ),
            array( $this, 'email_frequency_callback' ),
            'cfm-settings',
            'cfm_email_section'
        );

        // Email recipient
        add_settings_field(
            'email_recipient',
            __( 'Recipient Email', 'content-freshness-monitor' ),
            array( $this, 'email_recipient_callback' ),
            'cfm-settings',
            'cfm_email_section'
        );

        // Test email button
        add_settings_field(
            'test_email',
            __( 'Test Email', 'content-freshness-monitor' ),
            array( $this, 'test_email_callback' ),
            'cfm-settings',
            'cfm_email_section'
        );

        // Author notifications section
        add_settings_section(
            'cfm_author_section',
            __( 'Author Notifications', 'content-freshness-monitor' ),
            array( $this, 'author_section_callback' ),
            'cfm-settings'
        );

        // Enable author notifications
        add_settings_field(
            'author_notifications',
            __( 'Enable Author Notifications', 'content-freshness-monitor' ),
            array( $this, 'author_notifications_callback' ),
            'cfm-settings',
            'cfm_author_section'
        );

        // Author notification frequency
        add_settings_field(
            'author_email_frequency',
            __( 'Author Email Frequency', 'content-freshness-monitor' ),
            array( $this, 'author_email_frequency_callback' ),
            'cfm-settings',
            'cfm_author_section'
        );

        // Minimum stale posts for author email
        add_settings_field(
            'author_min_stale',
            __( 'Minimum Stale Posts', 'content-freshness-monitor' ),
            array( $this, 'author_min_stale_callback' ),
            'cfm-settings',
            'cfm_author_section'
        );
    }

    /**
     * Section description
     */
    public function general_section_callback() {
        echo '<p>' . esc_html__( 'Configure how content freshness is monitored.', 'content-freshness-monitor' ) . '</p>';
    }

    /**
     * Staleness days field
     */
    public function staleness_days_callback() {
        $settings = Content_Freshness_Monitor::get_settings();
        ?>
        <input
            type="number"
            name="cfm_settings[staleness_days]"
            value="<?php echo esc_attr( $settings['staleness_days'] ); ?>"
            min="1"
            max="3650"
            class="small-text"
        />
        <p class="description">
            <?php esc_html_e( 'Content exceeding this age threshold will be flagged as stale. Default: 180 days (6 months).', 'content-freshness-monitor' ); ?>
        </p>
        <?php
    }

    /**
     * Date check type field
     */
    public function date_check_type_callback() {
        $settings = Content_Freshness_Monitor::get_settings();
        $date_check = isset( $settings['date_check_type'] ) ? $settings['date_check_type'] : 'modified';
        ?>
        <select name="cfm_settings[date_check_type]">
            <option value="modified" <?php selected( $date_check, 'modified' ); ?>>
                <?php esc_html_e( 'Last Modified Date', 'content-freshness-monitor' ); ?>
            </option>
            <option value="published" <?php selected( $date_check, 'published' ); ?>>
                <?php esc_html_e( 'Publish Date', 'content-freshness-monitor' ); ?>
            </option>
            <option value="oldest" <?php selected( $date_check, 'oldest' ); ?>>
                <?php esc_html_e( 'Oldest Date (whichever is older)', 'content-freshness-monitor' ); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e( 'Last Modified: Flags content not updated recently (recommended for content audits).', 'content-freshness-monitor' ); ?><br>
            <?php esc_html_e( 'Publish Date: Flags old content regardless of updates (good for evergreen content review).', 'content-freshness-monitor' ); ?><br>
            <?php esc_html_e( 'Oldest Date: Flags content where either date exceeds the threshold.', 'content-freshness-monitor' ); ?>
        </p>
        <?php
    }

    /**
     * Post types field
     */
    public function post_types_callback() {
        $settings = Content_Freshness_Monitor::get_settings();
        $selected = (array) $settings['post_types'];

        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        unset( $post_types['attachment'] );

        foreach ( $post_types as $post_type ) {
            $checked = in_array( $post_type->name, $selected, true ) ? 'checked' : '';
            ?>
            <label style="display: block; margin-bottom: 5px;">
                <input
                    type="checkbox"
                    name="cfm_settings[post_types][]"
                    value="<?php echo esc_attr( $post_type->name ); ?>"
                    <?php echo $checked; ?>
                />
                <?php echo esc_html( $post_type->labels->name ); ?>
            </label>
            <?php
        }
    }

    /**
     * Show in list field
     */
    public function show_in_list_callback() {
        $settings = Content_Freshness_Monitor::get_settings();
        ?>
        <label>
            <input
                type="checkbox"
                name="cfm_settings[show_in_list]"
                value="1"
                <?php checked( $settings['show_in_list'], true ); ?>
            />
            <?php esc_html_e( 'Show freshness status column in post list tables.', 'content-freshness-monitor' ); ?>
        </label>
        <?php
    }

    /**
     * Exclude IDs field
     */
    public function exclude_ids_callback() {
        $settings = Content_Freshness_Monitor::get_settings();
        ?>
        <input
            type="text"
            name="cfm_settings[exclude_ids]"
            value="<?php echo esc_attr( $settings['exclude_ids'] ); ?>"
            class="regular-text"
            placeholder="123, 456, 789"
        />
        <p class="description">
            <?php esc_html_e( 'Comma-separated list of post IDs to exclude from monitoring.', 'content-freshness-monitor' ); ?>
        </p>
        <?php
    }

    /**
     * Per-type section description
     */
    public function per_type_section_callback() {
        echo '<p>' . esc_html__( 'Set different staleness thresholds for each content type. For example, blog posts may go stale quickly while static pages remain relevant longer.', 'content-freshness-monitor' ) . '</p>';
    }

    /**
     * Enable per-type thresholds checkbox
     */
    public function enable_per_type_callback() {
        $settings = Content_Freshness_Monitor::get_settings();
        ?>
        <label>
            <input
                type="checkbox"
                name="cfm_settings[enable_per_type]"
                value="1"
                id="cfm-enable-per-type"
                <?php checked( $settings['enable_per_type'], true ); ?>
            />
            <?php esc_html_e( 'Use different thresholds for different content types.', 'content-freshness-monitor' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When disabled, all content types use the global threshold above.', 'content-freshness-monitor' ); ?>
        </p>
        <?php
    }

    /**
     * Per-type thresholds table
     */
    public function per_type_thresholds_callback() {
        $settings = Content_Freshness_Monitor::get_settings();
        $selected_types = (array) $settings['post_types'];
        $per_type = isset( $settings['per_type_thresholds'] ) ? (array) $settings['per_type_thresholds'] : array();
        $global_threshold = absint( $settings['staleness_days'] );
        $enabled = ! empty( $settings['enable_per_type'] );

        if ( empty( $selected_types ) ) {
            echo '<p class="description">' . esc_html__( 'Please select post types to monitor in the settings above.', 'content-freshness-monitor' ) . '</p>';
            return;
        }

        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        ?>
        <div id="cfm-per-type-thresholds" class="<?php echo $enabled ? '' : 'cfm-disabled'; ?>">
            <table class="cfm-thresholds-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Content Type', 'content-freshness-monitor' ); ?></th>
                        <th><?php esc_html_e( 'Threshold (days)', 'content-freshness-monitor' ); ?></th>
                        <th><?php esc_html_e( 'Description', 'content-freshness-monitor' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $selected_types as $type_name ) :
                        if ( ! isset( $post_types[ $type_name ] ) ) {
                            continue;
                        }
                        $type = $post_types[ $type_name ];
                        $current_value = isset( $per_type[ $type_name ] ) ? absint( $per_type[ $type_name ] ) : '';
                        $placeholder = $global_threshold;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $type->labels->name ); ?></strong>
                            <code style="font-size: 11px; margin-left: 5px;"><?php echo esc_html( $type_name ); ?></code>
                        </td>
                        <td>
                            <input
                                type="number"
                                name="cfm_settings[per_type_thresholds][<?php echo esc_attr( $type_name ); ?>]"
                                value="<?php echo esc_attr( $current_value ); ?>"
                                min="1"
                                max="3650"
                                class="small-text"
                                placeholder="<?php echo esc_attr( $placeholder ); ?>"
                                <?php echo $enabled ? '' : 'disabled'; ?>
                            />
                        </td>
                        <td class="description">
                            <?php
                            if ( 'post' === $type_name ) {
                                esc_html_e( 'Blog posts typically need frequent updates', 'content-freshness-monitor' );
                            } elseif ( 'page' === $type_name ) {
                                esc_html_e( 'Static pages may stay relevant longer', 'content-freshness-monitor' );
                            } else {
                                /* translators: %s: post type label */
                                printf( esc_html__( 'Custom threshold for %s', 'content-freshness-monitor' ), esc_html( $type->labels->name ) );
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description">
                <?php esc_html_e( 'Leave blank to use the global threshold. Enter a value to override for specific content types.', 'content-freshness-monitor' ); ?>
            </p>
        </div>
        <style>
            .cfm-thresholds-table { border-collapse: collapse; margin-top: 10px; }
            .cfm-thresholds-table th,
            .cfm-thresholds-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #ddd; }
            .cfm-thresholds-table th { background: #f5f5f5; font-weight: 600; }
            .cfm-thresholds-table td.description { color: #666; font-size: 12px; }
            #cfm-per-type-thresholds.cfm-disabled { opacity: 0.5; pointer-events: none; }
        </style>
        <script>
            jQuery(function($) {
                $('#cfm-enable-per-type').on('change', function() {
                    var $container = $('#cfm-per-type-thresholds');
                    var $inputs = $container.find('input[type="number"]');
                    if ($(this).is(':checked')) {
                        $container.removeClass('cfm-disabled');
                        $inputs.prop('disabled', false);
                    } else {
                        $container.addClass('cfm-disabled');
                        $inputs.prop('disabled', true);
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Email section description
     */
    public function email_section_callback() {
        echo '<p>' . esc_html__( 'Receive email alerts when you have stale content that needs attention.', 'content-freshness-monitor' ) . '</p>';
    }

    /**
     * Email enabled field
     */
    public function email_enabled_callback() {
        $settings = Content_Freshness_Monitor::get_settings();
        ?>
        <label>
            <input
                type="checkbox"
                name="cfm_settings[email_enabled]"
                value="1"
                <?php checked( $settings['email_enabled'], true ); ?>
            />
            <?php esc_html_e( 'Send scheduled email digests about stale content.', 'content-freshness-monitor' ); ?>
        </label>
        <?php
    }

    /**
     * Email frequency field
     */
    public function email_frequency_callback() {
        $settings = Content_Freshness_Monitor::get_settings();
        ?>
        <select name="cfm_settings[email_frequency]">
            <option value="daily" <?php selected( $settings['email_frequency'], 'daily' ); ?>>
                <?php esc_html_e( 'Daily', 'content-freshness-monitor' ); ?>
            </option>
            <option value="weekly" <?php selected( $settings['email_frequency'], 'weekly' ); ?>>
                <?php esc_html_e( 'Weekly', 'content-freshness-monitor' ); ?>
            </option>
            <option value="monthly" <?php selected( $settings['email_frequency'], 'monthly' ); ?>>
                <?php esc_html_e( 'Monthly', 'content-freshness-monitor' ); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e( 'How often to send stale content digest emails.', 'content-freshness-monitor' ); ?>
        </p>
        <?php
    }

    /**
     * Email recipient field
     */
    public function email_recipient_callback() {
        $settings = Content_Freshness_Monitor::get_settings();
        $default_email = get_option( 'admin_email' );
        ?>
        <input
            type="email"
            name="cfm_settings[email_recipient]"
            value="<?php echo esc_attr( $settings['email_recipient'] ); ?>"
            class="regular-text"
            placeholder="<?php echo esc_attr( $default_email ); ?>"
        />
        <p class="description">
            <?php
            printf(
                /* translators: %s: default admin email */
                esc_html__( 'Email address to receive alerts. Leave blank to use admin email (%s).', 'content-freshness-monitor' ),
                esc_html( $default_email )
            );
            ?>
        </p>
        <?php
    }

    /**
     * Test email button
     */
    public function test_email_callback() {
        $settings = Content_Freshness_Monitor::get_settings();
        $recipient = ! empty( $settings['email_recipient'] )
            ? $settings['email_recipient']
            : get_option( 'admin_email' );
        ?>
        <button type="button" class="button" id="cfm-send-test-email">
            <?php esc_html_e( 'Send Test Email', 'content-freshness-monitor' ); ?>
        </button>
        <span id="cfm-test-email-result" style="margin-left: 10px;"></span>
        <p class="description">
            <?php
            printf(
                /* translators: %s: recipient email */
                esc_html__( 'Send a test email to %s to verify your settings.', 'content-freshness-monitor' ),
                esc_html( $recipient )
            );
            ?>
        </p>
        <?php
    }

    /**
     * Author section description
     */
    public function author_section_callback() {
        echo '<p>' . esc_html__( 'Notify individual authors about their own stale content. Each author will only see posts they have written.', 'content-freshness-monitor' ) . '</p>';
    }

    /**
     * Author notifications enabled field
     */
    public function author_notifications_callback() {
        $settings = Content_Freshness_Monitor::get_settings();
        ?>
        <label>
            <input
                type="checkbox"
                name="cfm_settings[author_notifications]"
                value="1"
                <?php checked( ! empty( $settings['author_notifications'] ), true ); ?>
            />
            <?php esc_html_e( 'Send personalized email digests to authors about their stale content.', 'content-freshness-monitor' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'Authors with the edit_posts capability will receive emails about their own stale posts.', 'content-freshness-monitor' ); ?>
        </p>
        <?php
    }

    /**
     * Author email frequency field
     */
    public function author_email_frequency_callback() {
        $settings = Content_Freshness_Monitor::get_settings();
        $frequency = isset( $settings['author_email_frequency'] ) ? $settings['author_email_frequency'] : 'weekly';
        ?>
        <select name="cfm_settings[author_email_frequency]">
            <option value="daily" <?php selected( $frequency, 'daily' ); ?>>
                <?php esc_html_e( 'Daily', 'content-freshness-monitor' ); ?>
            </option>
            <option value="weekly" <?php selected( $frequency, 'weekly' ); ?>>
                <?php esc_html_e( 'Weekly', 'content-freshness-monitor' ); ?>
            </option>
            <option value="monthly" <?php selected( $frequency, 'monthly' ); ?>>
                <?php esc_html_e( 'Monthly', 'content-freshness-monitor' ); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e( 'How often authors receive their personalized stale content digest.', 'content-freshness-monitor' ); ?>
        </p>
        <?php
    }

    /**
     * Minimum stale posts for author email
     */
    public function author_min_stale_callback() {
        $settings = Content_Freshness_Monitor::get_settings();
        $min_stale = isset( $settings['author_min_stale'] ) ? absint( $settings['author_min_stale'] ) : 1;
        ?>
        <input
            type="number"
            name="cfm_settings[author_min_stale]"
            value="<?php echo esc_attr( $min_stale ); ?>"
            min="1"
            max="100"
            class="small-text"
        />
        <p class="description">
            <?php esc_html_e( 'Only send author emails when they have at least this many stale posts. Default: 1', 'content-freshness-monitor' ); ?>
        </p>
        <?php
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        // Staleness days
        $sanitized['staleness_days'] = absint( $input['staleness_days'] );
        if ( $sanitized['staleness_days'] < 1 ) {
            $sanitized['staleness_days'] = 180;
        }

        // Post types
        $sanitized['post_types'] = array();
        if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
            $valid_types = get_post_types( array( 'public' => true ) );
            foreach ( $input['post_types'] as $type ) {
                if ( isset( $valid_types[ $type ] ) ) {
                    $sanitized['post_types'][] = sanitize_key( $type );
                }
            }
        }

        // Show in list
        $sanitized['show_in_list'] = ! empty( $input['show_in_list'] );

        // Exclude IDs
        $sanitized['exclude_ids'] = '';
        if ( ! empty( $input['exclude_ids'] ) ) {
            $ids = array_map( 'absint', explode( ',', $input['exclude_ids'] ) );
            $ids = array_filter( $ids );
            $sanitized['exclude_ids'] = implode( ', ', $ids );
        }

        // Date check type
        $valid_date_types = array( 'modified', 'published', 'oldest' );
        $sanitized['date_check_type'] = 'modified';
        if ( isset( $input['date_check_type'] ) && in_array( $input['date_check_type'], $valid_date_types, true ) ) {
            $sanitized['date_check_type'] = $input['date_check_type'];
        }

        // Email enabled
        $sanitized['email_enabled'] = ! empty( $input['email_enabled'] );

        // Email frequency
        $valid_frequencies = array( 'daily', 'weekly', 'monthly' );
        $sanitized['email_frequency'] = 'weekly';
        if ( isset( $input['email_frequency'] ) && in_array( $input['email_frequency'], $valid_frequencies, true ) ) {
            $sanitized['email_frequency'] = $input['email_frequency'];
        }

        // Email recipient
        $sanitized['email_recipient'] = '';
        if ( ! empty( $input['email_recipient'] ) ) {
            $sanitized['email_recipient'] = sanitize_email( $input['email_recipient'] );
        }

        // Enable per-type thresholds
        $sanitized['enable_per_type'] = ! empty( $input['enable_per_type'] );

        // Per-type thresholds
        $sanitized['per_type_thresholds'] = array();
        if ( isset( $input['per_type_thresholds'] ) && is_array( $input['per_type_thresholds'] ) ) {
            $valid_types = get_post_types( array( 'public' => true ) );
            foreach ( $input['per_type_thresholds'] as $type => $days ) {
                // Only save if it's a valid post type and has a value
                if ( isset( $valid_types[ $type ] ) && '' !== $days ) {
                    $days = absint( $days );
                    if ( $days >= 1 && $days <= 3650 ) {
                        $sanitized['per_type_thresholds'][ sanitize_key( $type ) ] = $days;
                    }
                }
            }
        }

        // Author notifications
        $sanitized['author_notifications'] = ! empty( $input['author_notifications'] );

        // Author email frequency
        $sanitized['author_email_frequency'] = 'weekly';
        if ( isset( $input['author_email_frequency'] ) && in_array( $input['author_email_frequency'], $valid_frequencies, true ) ) {
            $sanitized['author_email_frequency'] = $input['author_email_frequency'];
        }

        // Author min stale posts
        $sanitized['author_min_stale'] = 1;
        if ( isset( $input['author_min_stale'] ) ) {
            $sanitized['author_min_stale'] = max( 1, min( 100, absint( $input['author_min_stale'] ) ) );
        }

        // Schedule or unschedule email notifications
        if ( $sanitized['email_enabled'] ) {
            CFM_Notifications::schedule_digest( $sanitized['email_frequency'] );
        } else {
            CFM_Notifications::unschedule_digest();
        }

        // Schedule or unschedule author notifications
        if ( $sanitized['author_notifications'] ) {
            CFM_Notifications::schedule_author_digest( $sanitized['author_email_frequency'] );
        } else {
            CFM_Notifications::unschedule_author_digest();
        }

        // Invalidate stats cache when settings change
        delete_transient( CFM_Scanner::STATS_CACHE_KEY );
        delete_transient( CFM_Scanner::COUNT_CACHE_KEY );

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'cfm_settings_group' );
                do_settings_sections( 'cfm-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

new CFM_Settings();
