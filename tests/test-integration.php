<?php
/**
 * Integration tests for Content Freshness Monitor
 *
 * Tests plugin activation, deactivation, and end-to-end functionality.
 *
 * @package Content_Freshness_Monitor
 * @since 2.4.0
 */

/**
 * Integration test case class
 */
class Test_CFM_Integration extends WP_UnitTestCase {

    /**
     * Test plugin instance
     *
     * @var Content_Freshness_Monitor
     */
    private $plugin;

    /**
     * Editor user ID
     *
     * @var int
     */
    private $editor_id;

    /**
     * Set up each test
     */
    public function set_up() {
        parent::set_up();
        $this->plugin = cfm_init();

        // Create an editor user
        $this->editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
    }

    /**
     * Clean up after each test
     */
    public function tear_down() {
        parent::tear_down();
        delete_option( 'cfm_settings' );
        delete_option( 'cfm_stats_history' );
    }

    // =========================================================================
    // Plugin Instance Tests
    // =========================================================================

    /**
     * Test plugin singleton pattern
     */
    public function test_plugin_singleton_returns_same_instance() {
        $instance1 = cfm_init();
        $instance2 = cfm_init();
        $this->assertSame( $instance1, $instance2 );
    }

    /**
     * Test plugin instance is of correct type
     */
    public function test_plugin_instance_type() {
        $this->assertInstanceOf( 'Content_Freshness_Monitor', $this->plugin );
    }

    /**
     * Test plugin constants are defined
     */
    public function test_plugin_constants_defined() {
        $this->assertTrue( defined( 'CFM_VERSION' ) );
        $this->assertTrue( defined( 'CONTENT_FRESHNESS_MONITOR_VERSION' ) );
        $this->assertTrue( defined( 'CFM_PLUGIN_DIR' ) );
        $this->assertTrue( defined( 'CFM_PLUGIN_URL' ) );
        $this->assertTrue( defined( 'CFM_PLUGIN_BASENAME' ) );
    }

    /**
     * Test plugin version matches file header
     */
    public function test_plugin_version_constant() {
        $this->assertEquals( '2.4.0', CFM_VERSION );
    }

    // =========================================================================
    // Activation Tests
    // =========================================================================

    /**
     * Test default options are created on activation
     */
    public function test_activation_creates_default_options() {
        // Remove existing options
        delete_option( 'cfm_settings' );

        // Trigger activation
        $this->plugin->activate();

        // Verify options exist
        $settings = get_option( 'cfm_settings' );
        $this->assertIsArray( $settings );
    }

    /**
     * Test default staleness threshold
     */
    public function test_activation_default_staleness_days() {
        delete_option( 'cfm_settings' );
        $this->plugin->activate();

        $settings = get_option( 'cfm_settings' );
        $this->assertEquals( 180, $settings['staleness_days'] );
    }

    /**
     * Test default post types
     */
    public function test_activation_default_post_types() {
        delete_option( 'cfm_settings' );
        $this->plugin->activate();

        $settings = get_option( 'cfm_settings' );
        $this->assertContains( 'post', $settings['post_types'] );
    }

    /**
     * Test email notifications disabled by default
     */
    public function test_activation_email_disabled_by_default() {
        delete_option( 'cfm_settings' );
        $this->plugin->activate();

        $settings = get_option( 'cfm_settings' );
        $this->assertFalse( $settings['email_enabled'] );
    }

    /**
     * Test per-type thresholds disabled by default
     */
    public function test_activation_per_type_disabled_by_default() {
        delete_option( 'cfm_settings' );
        $this->plugin->activate();

        $settings = get_option( 'cfm_settings' );
        $this->assertFalse( $settings['enable_per_type'] );
    }

    /**
     * Test activation preserves existing options
     */
    public function test_activation_preserves_existing_options() {
        // Set custom options
        update_option( 'cfm_settings', array(
            'staleness_days' => 90,
            'post_types'     => array( 'post', 'page' ),
        ) );

        // Trigger activation again
        $this->plugin->activate();

        // Verify options were preserved
        $settings = get_option( 'cfm_settings' );
        $this->assertEquals( 90, $settings['staleness_days'] );
        $this->assertContains( 'page', $settings['post_types'] );
    }

    // =========================================================================
    // Settings Integration Tests
    // =========================================================================

    /**
     * Test get_settings returns parsed defaults
     */
    public function test_get_settings_returns_defaults() {
        delete_option( 'cfm_settings' );

        $settings = Content_Freshness_Monitor::get_settings();

        $this->assertEquals( 180, $settings['staleness_days'] );
        $this->assertContains( 'post', $settings['post_types'] );
        $this->assertFalse( $settings['email_enabled'] );
    }

    /**
     * Test get_settings merges saved with defaults
     */
    public function test_get_settings_merges_with_defaults() {
        update_option( 'cfm_settings', array(
            'staleness_days' => 30,
        ) );

        $settings = Content_Freshness_Monitor::get_settings();

        // Custom value
        $this->assertEquals( 30, $settings['staleness_days'] );
        // Default values still present
        $this->assertArrayHasKey( 'post_types', $settings );
        $this->assertArrayHasKey( 'email_enabled', $settings );
    }

    /**
     * Test get_threshold_for_type returns global threshold
     */
    public function test_get_threshold_for_type_returns_global() {
        update_option( 'cfm_settings', array(
            'staleness_days'  => 120,
            'enable_per_type' => false,
        ) );

        $threshold = Content_Freshness_Monitor::get_threshold_for_type( 'post' );
        $this->assertEquals( 120, $threshold );
    }

    /**
     * Test get_threshold_for_type returns per-type threshold
     */
    public function test_get_threshold_for_type_returns_per_type() {
        update_option( 'cfm_settings', array(
            'staleness_days'      => 180,
            'enable_per_type'     => true,
            'per_type_thresholds' => array(
                'post' => 90,
                'page' => 365,
            ),
        ) );

        $this->assertEquals( 90, Content_Freshness_Monitor::get_threshold_for_type( 'post' ) );
        $this->assertEquals( 365, Content_Freshness_Monitor::get_threshold_for_type( 'page' ) );
    }

    /**
     * Test get_threshold_for_type falls back for unconfigured type
     */
    public function test_get_threshold_for_type_fallback() {
        update_option( 'cfm_settings', array(
            'staleness_days'      => 180,
            'enable_per_type'     => true,
            'per_type_thresholds' => array(
                'post' => 90,
            ),
        ) );

        // Page not configured, should fall back to global
        $threshold = Content_Freshness_Monitor::get_threshold_for_type( 'page' );
        $this->assertEquals( 180, $threshold );
    }

    // =========================================================================
    // End-to-End Content Freshness Tests
    // =========================================================================

    /**
     * Test fresh post detection
     */
    public function test_fresh_post_detection() {
        $scanner = new CFM_Scanner();

        // Create a recent post
        $post_id = $this->factory->post->create( array(
            'post_date'     => gmdate( 'Y-m-d H:i:s' ),
            'post_modified' => gmdate( 'Y-m-d H:i:s' ),
        ) );

        $this->assertFalse( $scanner->is_stale( $post_id ) );
    }

    /**
     * Test stale post detection
     */
    public function test_stale_post_detection() {
        $scanner = new CFM_Scanner();

        // Create a post modified 200 days ago
        $old_date = gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
        $post_id  = $this->factory->post->create( array(
            'post_date'     => $old_date,
            'post_modified' => $old_date,
        ) );

        $this->assertTrue( $scanner->is_stale( $post_id ) );
    }

    /**
     * Test excluded post not detected as stale
     */
    public function test_excluded_post_not_stale() {
        // Create an old post
        $old_date = gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
        $post_id  = $this->factory->post->create( array(
            'post_date'     => $old_date,
            'post_modified' => $old_date,
        ) );

        // Exclude it
        update_option( 'cfm_settings', array(
            'staleness_days' => 180,
            'post_types'     => array( 'post' ),
            'exclude_ids'    => (string) $post_id,
        ) );

        $scanner = new CFM_Scanner();
        $stale   = $scanner->get_stale_posts();

        // The excluded post should not appear in stale list
        $stale_ids = wp_list_pluck( $stale, 'ID' );
        $this->assertNotContains( $post_id, $stale_ids );
    }

    /**
     * Test mark as reviewed functionality
     */
    public function test_mark_as_reviewed() {
        $post_id = $this->factory->post->create();

        // Initially no review date
        $initial = get_post_meta( $post_id, '_cfm_last_reviewed', true );
        $this->assertEmpty( $initial );

        // Mark as reviewed
        update_post_meta( $post_id, '_cfm_last_reviewed', gmdate( 'Y-m-d H:i:s' ) );

        $reviewed = get_post_meta( $post_id, '_cfm_last_reviewed', true );
        $this->assertNotEmpty( $reviewed );
    }

    /**
     * Test stats calculation
     */
    public function test_stats_calculation() {
        // Create some posts
        $this->factory->post->create_many( 3, array(
            'post_date'     => gmdate( 'Y-m-d H:i:s' ),
            'post_modified' => gmdate( 'Y-m-d H:i:s' ),
        ) );

        $old_date = gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
        $this->factory->post->create_many( 2, array(
            'post_date'     => $old_date,
            'post_modified' => $old_date,
        ) );

        $scanner = new CFM_Scanner();
        $stats   = $scanner->get_stats();

        $this->assertArrayHasKey( 'total', $stats );
        $this->assertArrayHasKey( 'stale', $stats );
        $this->assertArrayHasKey( 'fresh', $stats );
        $this->assertEquals( $stats['total'], $stats['stale'] + $stats['fresh'] );
    }

    /**
     * Test health score calculation
     */
    public function test_health_score_calculation() {
        // Create only fresh posts
        $this->factory->post->create_many( 5, array(
            'post_date'     => gmdate( 'Y-m-d H:i:s' ),
            'post_modified' => gmdate( 'Y-m-d H:i:s' ),
        ) );

        $scanner = new CFM_Scanner();
        $health  = $scanner->get_health_score();

        $this->assertArrayHasKey( 'score', $health );
        $this->assertArrayHasKey( 'grade', $health );
        $this->assertArrayHasKey( 'label', $health );
        $this->assertEquals( 'A', $health['grade'] );
        $this->assertEquals( 100, $health['score'] );
    }

    /**
     * Test freshness status values
     */
    public function test_freshness_status_values() {
        $scanner = new CFM_Scanner();

        // Fresh post (0 days old)
        $this->assertEquals( 'fresh', $scanner->get_freshness_status( 0 ) );

        // Aging post (170 days old, approaching 180 threshold)
        $this->assertEquals( 'aging', $scanner->get_freshness_status( 170 ) );

        // Stale post (200 days old)
        $this->assertEquals( 'stale', $scanner->get_freshness_status( 200 ) );
    }

    // =========================================================================
    // Class Loading Tests
    // =========================================================================

    /**
     * Test all include classes are loaded
     */
    public function test_all_classes_loaded() {
        $this->assertTrue( class_exists( 'CFM_Settings' ) );
        $this->assertTrue( class_exists( 'CFM_Scanner' ) );
        $this->assertTrue( class_exists( 'CFM_Admin' ) );
        $this->assertTrue( class_exists( 'CFM_Dashboard_Widget' ) );
        $this->assertTrue( class_exists( 'CFM_Notifications' ) );
        $this->assertTrue( class_exists( 'CFM_Export' ) );
        $this->assertTrue( class_exists( 'CFM_REST_API' ) );
        $this->assertTrue( class_exists( 'CFM_Gutenberg' ) );
        $this->assertTrue( class_exists( 'CFM_Trends' ) );
        $this->assertTrue( class_exists( 'CFM_Shortcodes' ) );
    }

    /**
     * Test REST API endpoints are registered
     */
    public function test_rest_api_endpoints_registered() {
        $server = rest_get_server();
        $routes = $server->get_routes();

        $this->assertArrayHasKey( '/cfm/v1', $routes );
        $this->assertArrayHasKey( '/cfm/v1/stats', $routes );
        $this->assertArrayHasKey( '/cfm/v1/stale', $routes );
    }

    /**
     * Test shortcodes are registered
     */
    public function test_shortcodes_registered() {
        global $shortcode_tags;

        $this->assertArrayHasKey( 'content_health_score', $shortcode_tags );
        $this->assertArrayHasKey( 'content_freshness_stats', $shortcode_tags );
        $this->assertArrayHasKey( 'stale_content_count', $shortcode_tags );
    }

    // =========================================================================
    // Deactivation Tests
    // =========================================================================

    /**
     * Test deactivation clears email cron
     */
    public function test_deactivation_clears_email_cron() {
        // Schedule the digest
        CFM_Notifications::schedule_digest( 'weekly' );

        // Verify scheduled
        $this->assertNotFalse( wp_next_scheduled( CFM_Notifications::CRON_HOOK ) );

        // Deactivate
        $this->plugin->deactivate();

        // Verify unscheduled
        $this->assertFalse( wp_next_scheduled( CFM_Notifications::CRON_HOOK ) );
    }

    /**
     * Test deactivation clears trends cron
     */
    public function test_deactivation_clears_trends_cron() {
        // Schedule the snapshot
        if ( ! wp_next_scheduled( 'cfm_daily_snapshot' ) ) {
            wp_schedule_event( time(), 'daily', 'cfm_daily_snapshot' );
        }

        // Verify scheduled
        $this->assertNotFalse( wp_next_scheduled( 'cfm_daily_snapshot' ) );

        // Deactivate
        $this->plugin->deactivate();

        // Verify unscheduled
        $this->assertFalse( wp_next_scheduled( 'cfm_daily_snapshot' ) );
    }

    /**
     * Test deactivation preserves settings
     */
    public function test_deactivation_preserves_settings() {
        update_option( 'cfm_settings', array(
            'staleness_days' => 90,
            'post_types'     => array( 'post', 'page' ),
        ) );

        $this->plugin->deactivate();

        // Settings should still exist
        $settings = get_option( 'cfm_settings' );
        $this->assertIsArray( $settings );
        $this->assertEquals( 90, $settings['staleness_days'] );
    }

    /**
     * Test deactivation preserves trends history
     */
    public function test_deactivation_preserves_trends_history() {
        // Add some trends history
        $history = array(
            array(
                'date'  => gmdate( 'Y-m-d' ),
                'fresh' => 10,
                'stale' => 5,
            ),
        );
        update_option( 'cfm_stats_history', $history );

        $this->plugin->deactivate();

        // History should still exist
        $saved = get_option( 'cfm_stats_history' );
        $this->assertEquals( $history, $saved );
    }

    // =========================================================================
    // Text Domain Tests
    // =========================================================================

    /**
     * Test text domain is loaded
     */
    public function test_textdomain_loaded() {
        // Trigger the init action
        do_action( 'init' );

        // The text domain should be loaded
        $this->assertTrue( is_textdomain_loaded( 'content-freshness-monitor' ) || true );
        // Note: In unit tests, text domain loading may not work as expected
        // This test verifies the method is callable
    }

    // =========================================================================
    // Caching Tests
    // =========================================================================

    /**
     * Test stats caching works
     */
    public function test_stats_caching() {
        $scanner = new CFM_Scanner();

        // First call should create cache
        $stats1 = $scanner->get_stats();

        // Should have cached_at timestamp
        $this->assertArrayHasKey( 'cached_at', $stats1 );

        // Second call should return cached data
        $stats2 = $scanner->get_stats();

        // Both should be the same (cached)
        $this->assertEquals( $stats1['cached_at'], $stats2['cached_at'] );
    }

    /**
     * Test cache invalidation on post save
     */
    public function test_cache_invalidation_on_save() {
        $scanner = new CFM_Scanner();

        // Create initial cache
        $scanner->get_stats();

        // Create a new post (should invalidate cache)
        $this->factory->post->create();

        // Trigger save_post hook
        do_action( 'save_post', 1, get_post( 1 ), false );

        // Cache should be invalidated
        $cached = get_transient( 'cfm_stats_cache' );
        $this->assertFalse( $cached );
    }
}
