<?php
/**
 * Tests for CFM_Notifications class
 *
 * @package Content_Freshness_Monitor
 */

class Test_CFM_Notifications extends WP_UnitTestCase {

    /**
     * Notifications instance
     *
     * @var CFM_Notifications
     */
    private $notifications;

    /**
     * Test author user
     *
     * @var WP_User
     */
    private $test_author;

    /**
     * Test admin user
     *
     * @var WP_User
     */
    private $test_admin;

    /**
     * Set up test fixtures
     */
    public function set_up() {
        parent::set_up();

        $this->notifications = new CFM_Notifications();

        // Create test users
        $this->test_author = $this->factory->user->create_and_get( array(
            'role'         => 'author',
            'display_name' => 'Test Author',
            'user_email'   => 'author@example.com',
        ) );

        $this->test_admin = $this->factory->user->create_and_get( array(
            'role'         => 'administrator',
            'display_name' => 'Test Admin',
            'user_email'   => 'admin@example.com',
        ) );

        // Set default settings
        update_option( 'cfm_settings', array(
            'staleness_days' => 180,
            'post_types'     => array( 'post', 'page' ),
            'exclude_ids'    => '',
            'email_enabled'  => 1,
            'email_frequency' => 'daily',
            'email_recipient' => '',
            'author_notifications' => 0,
            'author_frequency' => 'weekly',
            'author_min_stale' => 1,
        ) );
    }

    /**
     * Tear down test fixtures
     */
    public function tear_down() {
        // Clean up settings
        delete_option( 'cfm_settings' );

        // Clear scheduled events
        CFM_Notifications::unschedule_digest();
        CFM_Notifications::unschedule_author_digest();

        parent::tear_down();
    }

    /**
     * Test cron hook constant is defined
     */
    public function test_cron_hook_constant() {
        $this->assertEquals( 'cfm_send_stale_digest', CFM_Notifications::CRON_HOOK );
    }

    /**
     * Test author cron hook constant is defined
     */
    public function test_author_cron_hook_constant() {
        $this->assertEquals( 'cfm_send_author_digest', CFM_Notifications::AUTHOR_CRON_HOOK );
    }

    /**
     * Test custom cron schedules are added
     */
    public function test_cron_schedules_added() {
        $schedules = apply_filters( 'cron_schedules', array() );

        $this->assertArrayHasKey( 'cfm_weekly', $schedules );
        $this->assertArrayHasKey( 'cfm_monthly', $schedules );

        $this->assertEquals( WEEK_IN_SECONDS, $schedules['cfm_weekly']['interval'] );
        $this->assertEquals( MONTH_IN_SECONDS, $schedules['cfm_monthly']['interval'] );
    }

    /**
     * Test schedule_digest with daily frequency
     */
    public function test_schedule_digest_daily() {
        CFM_Notifications::schedule_digest( 'daily' );

        $next_scheduled = wp_next_scheduled( CFM_Notifications::CRON_HOOK );
        $this->assertNotFalse( $next_scheduled );

        // Verify it's scheduled
        $this->assertGreaterThan( time(), $next_scheduled );
    }

    /**
     * Test schedule_digest with weekly frequency
     */
    public function test_schedule_digest_weekly() {
        CFM_Notifications::schedule_digest( 'weekly' );

        $next_scheduled = wp_next_scheduled( CFM_Notifications::CRON_HOOK );
        $this->assertNotFalse( $next_scheduled );
    }

    /**
     * Test schedule_digest with monthly frequency
     */
    public function test_schedule_digest_monthly() {
        CFM_Notifications::schedule_digest( 'monthly' );

        $next_scheduled = wp_next_scheduled( CFM_Notifications::CRON_HOOK );
        $this->assertNotFalse( $next_scheduled );
    }

    /**
     * Test schedule_digest with disabled clears schedule
     */
    public function test_schedule_digest_disabled() {
        // First schedule something
        CFM_Notifications::schedule_digest( 'daily' );
        $this->assertNotFalse( wp_next_scheduled( CFM_Notifications::CRON_HOOK ) );

        // Then disable
        CFM_Notifications::schedule_digest( 'disabled' );
        $this->assertFalse( wp_next_scheduled( CFM_Notifications::CRON_HOOK ) );
    }

    /**
     * Test unschedule_digest clears schedule
     */
    public function test_unschedule_digest() {
        CFM_Notifications::schedule_digest( 'daily' );
        $this->assertNotFalse( wp_next_scheduled( CFM_Notifications::CRON_HOOK ) );

        CFM_Notifications::unschedule_digest();
        $this->assertFalse( wp_next_scheduled( CFM_Notifications::CRON_HOOK ) );
    }

    /**
     * Test schedule_author_digest with daily frequency
     */
    public function test_schedule_author_digest_daily() {
        CFM_Notifications::schedule_author_digest( 'daily' );

        $next_scheduled = wp_next_scheduled( CFM_Notifications::AUTHOR_CRON_HOOK );
        $this->assertNotFalse( $next_scheduled );
    }

    /**
     * Test schedule_author_digest with disabled clears schedule
     */
    public function test_schedule_author_digest_disabled() {
        CFM_Notifications::schedule_author_digest( 'daily' );
        $this->assertNotFalse( wp_next_scheduled( CFM_Notifications::AUTHOR_CRON_HOOK ) );

        CFM_Notifications::schedule_author_digest( 'disabled' );
        $this->assertFalse( wp_next_scheduled( CFM_Notifications::AUTHOR_CRON_HOOK ) );
    }

    /**
     * Test unschedule_author_digest clears schedule
     */
    public function test_unschedule_author_digest() {
        CFM_Notifications::schedule_author_digest( 'weekly' );
        $this->assertNotFalse( wp_next_scheduled( CFM_Notifications::AUTHOR_CRON_HOOK ) );

        CFM_Notifications::unschedule_author_digest();
        $this->assertFalse( wp_next_scheduled( CFM_Notifications::AUTHOR_CRON_HOOK ) );
    }

    /**
     * Test send_stale_digest does not send when disabled
     */
    public function test_send_stale_digest_respects_disabled_setting() {
        // Disable email notifications
        update_option( 'cfm_settings', array(
            'staleness_days' => 180,
            'post_types'     => array( 'post' ),
            'exclude_ids'    => '',
            'email_enabled'  => 0,
        ) );

        // Create stale post
        $this->factory->post->create( array(
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
        ) );

        // Mock wp_mail by capturing calls
        $emails_sent = 0;
        add_filter( 'pre_wp_mail', function() use ( &$emails_sent ) {
            $emails_sent++;
            return true;
        } );

        $this->notifications->send_stale_digest();

        $this->assertEquals( 0, $emails_sent );
    }

    /**
     * Test send_stale_digest does not send when no stale content
     */
    public function test_send_stale_digest_no_stale_content() {
        // Enable notifications
        update_option( 'cfm_settings', array(
            'staleness_days' => 180,
            'post_types'     => array( 'post' ),
            'exclude_ids'    => '',
            'email_enabled'  => 1,
        ) );

        // Create fresh post
        $this->factory->post->create( array(
            'post_status' => 'publish',
        ) );

        $emails_sent = 0;
        add_filter( 'pre_wp_mail', function() use ( &$emails_sent ) {
            $emails_sent++;
            return true;
        } );

        $this->notifications->send_stale_digest();

        $this->assertEquals( 0, $emails_sent );
    }

    /**
     * Test send_author_digests does not send when disabled
     */
    public function test_send_author_digests_respects_disabled_setting() {
        update_option( 'cfm_settings', array(
            'staleness_days'       => 180,
            'post_types'           => array( 'post' ),
            'exclude_ids'          => '',
            'author_notifications' => 0,
        ) );

        $emails_sent = 0;
        add_filter( 'pre_wp_mail', function() use ( &$emails_sent ) {
            $emails_sent++;
            return true;
        } );

        $this->notifications->send_author_digests();

        $this->assertEquals( 0, $emails_sent );
    }

    /**
     * Test AJAX handler requires nonce
     */
    public function test_ajax_send_test_email_requires_nonce() {
        wp_set_current_user( $this->test_admin->ID );

        // No nonce set
        $this->expectException( 'WPDieException' );
        $this->notifications->ajax_send_test_email();
    }

    /**
     * Test AJAX handler requires manage_options capability
     */
    public function test_ajax_send_test_email_requires_capability() {
        wp_set_current_user( $this->test_author->ID );

        // Set valid nonce
        $_REQUEST['nonce'] = wp_create_nonce( 'cfm_nonce' );

        // Capture JSON response
        try {
            $this->notifications->ajax_send_test_email();
        } catch ( WPDieException $e ) {
            // Expected when wp_send_json_error is called
        }

        // Author should be denied
        $this->assertFalse( current_user_can( 'manage_options' ) );
    }

    /**
     * Test send_test_email returns boolean
     */
    public function test_send_test_email_returns_boolean() {
        add_filter( 'pre_wp_mail', '__return_true' );

        $result = CFM_Notifications::send_test_email( 'test@example.com' );

        $this->assertIsBool( $result );
    }

    /**
     * Test test email has [TEST] prefix in subject
     */
    public function test_send_test_email_has_test_prefix() {
        $captured_subject = '';

        add_filter( 'wp_mail', function( $args ) use ( &$captured_subject ) {
            $captured_subject = $args['subject'];
            return $args;
        } );

        add_filter( 'pre_wp_mail', '__return_true' );

        CFM_Notifications::send_test_email( 'test@example.com' );

        $this->assertStringStartsWith( '[TEST]', $captured_subject );
    }

    /**
     * Test email content type is HTML
     */
    public function test_email_content_type_is_html() {
        $captured_headers = array();

        add_filter( 'wp_mail', function( $args ) use ( &$captured_headers ) {
            $captured_headers = $args['headers'];
            return $args;
        } );

        add_filter( 'pre_wp_mail', '__return_true' );

        CFM_Notifications::send_test_email( 'test@example.com' );

        $this->assertContains( 'Content-Type: text/html; charset=UTF-8', $captured_headers );
    }

    /**
     * Test custom recipient is used when set
     */
    public function test_custom_recipient_used() {
        update_option( 'cfm_settings', array(
            'staleness_days'   => 180,
            'post_types'       => array( 'post' ),
            'exclude_ids'      => '',
            'email_enabled'    => 1,
            'email_recipient'  => 'custom@example.com',
        ) );

        // Create stale post
        $this->factory->post->create( array(
            'post_status'   => 'publish',
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
        ) );

        $captured_to = '';
        add_filter( 'wp_mail', function( $args ) use ( &$captured_to ) {
            $captured_to = $args['to'];
            return $args;
        } );

        add_filter( 'pre_wp_mail', '__return_true' );

        $this->notifications->send_stale_digest();

        $this->assertEquals( 'custom@example.com', $captured_to );
    }

    /**
     * Test default admin email is used when no custom recipient
     */
    public function test_default_admin_email_used() {
        $admin_email = get_option( 'admin_email' );

        update_option( 'cfm_settings', array(
            'staleness_days'  => 180,
            'post_types'      => array( 'post' ),
            'exclude_ids'     => '',
            'email_enabled'   => 1,
            'email_recipient' => '',
        ) );

        // Create stale post
        $this->factory->post->create( array(
            'post_status'   => 'publish',
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
        ) );

        $captured_to = '';
        add_filter( 'wp_mail', function( $args ) use ( &$captured_to ) {
            $captured_to = $args['to'];
            return $args;
        } );

        add_filter( 'pre_wp_mail', '__return_true' );

        $this->notifications->send_stale_digest();

        $this->assertEquals( $admin_email, $captured_to );
    }

    /**
     * Test email subject includes stale count
     */
    public function test_email_subject_includes_stale_count() {
        update_option( 'cfm_settings', array(
            'staleness_days' => 180,
            'post_types'     => array( 'post' ),
            'exclude_ids'    => '',
            'email_enabled'  => 1,
        ) );

        // Create 3 stale posts
        for ( $i = 0; $i < 3; $i++ ) {
            $this->factory->post->create( array(
                'post_status'   => 'publish',
                'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
                'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            ) );
        }

        $captured_subject = '';
        add_filter( 'wp_mail', function( $args ) use ( &$captured_subject ) {
            $captured_subject = $args['subject'];
            return $args;
        } );

        add_filter( 'pre_wp_mail', '__return_true' );

        $this->notifications->send_stale_digest();

        $this->assertStringContainsString( '3', $captured_subject );
    }

    /**
     * Test email body contains site name
     */
    public function test_email_body_contains_site_name() {
        $site_name = get_bloginfo( 'name' );

        update_option( 'cfm_settings', array(
            'staleness_days' => 180,
            'post_types'     => array( 'post' ),
            'exclude_ids'    => '',
            'email_enabled'  => 1,
        ) );

        // Create stale post
        $this->factory->post->create( array(
            'post_status'   => 'publish',
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
        ) );

        $captured_message = '';
        add_filter( 'wp_mail', function( $args ) use ( &$captured_message ) {
            $captured_message = $args['message'];
            return $args;
        } );

        add_filter( 'pre_wp_mail', '__return_true' );

        $this->notifications->send_stale_digest();

        $this->assertStringContainsString( $site_name, $captured_message );
    }

    /**
     * Test author digest respects minimum stale threshold
     */
    public function test_author_digest_respects_min_stale_threshold() {
        update_option( 'cfm_settings', array(
            'staleness_days'       => 180,
            'post_types'           => array( 'post' ),
            'exclude_ids'          => '',
            'author_notifications' => 1,
            'author_min_stale'     => 5, // Require 5 stale posts minimum
        ) );

        // Create only 2 stale posts (below threshold)
        for ( $i = 0; $i < 2; $i++ ) {
            $this->factory->post->create( array(
                'post_status'   => 'publish',
                'post_author'   => $this->test_author->ID,
                'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
                'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            ) );
        }

        $emails_sent = 0;
        add_filter( 'pre_wp_mail', function() use ( &$emails_sent ) {
            $emails_sent++;
            return true;
        } );

        $this->notifications->send_author_digests();

        // Should not send because 2 < 5
        $this->assertEquals( 0, $emails_sent );
    }
}
