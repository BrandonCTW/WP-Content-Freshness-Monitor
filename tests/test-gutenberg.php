<?php
/**
 * Tests for CFM_Gutenberg class
 *
 * Tests Gutenberg block editor integration including asset enqueuing,
 * meta registration, and localized script data.
 *
 * @package Content_Freshness_Monitor
 */

class Test_CFM_Gutenberg extends WP_UnitTestCase {

    /**
     * Test post ID
     *
     * @var int
     */
    protected static $post_id;

    /**
     * Editor user ID
     *
     * @var int
     */
    protected static $editor_id;

    /**
     * Subscriber user ID
     *
     * @var int
     */
    protected static $subscriber_id;

    /**
     * Set up before class
     *
     * @param WP_UnitTest_Factory $factory Factory object.
     */
    public static function wpSetUpBeforeClass( $factory ) {
        self::$editor_id = $factory->user->create( array(
            'role' => 'editor',
        ) );

        self::$subscriber_id = $factory->user->create( array(
            'role' => 'subscriber',
        ) );

        // Create a test post
        self::$post_id = $factory->post->create( array(
            'post_title'    => 'Test Gutenberg Post',
            'post_status'   => 'publish',
            'post_type'     => 'post',
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-100 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-100 days' ) ),
        ) );
    }

    /**
     * Test that CFM_Gutenberg class exists
     */
    public function test_class_exists() {
        $this->assertTrue( class_exists( 'CFM_Gutenberg' ) );
    }

    /**
     * Test constructor registers enqueue_block_editor_assets hook
     */
    public function test_constructor_registers_editor_assets_hook() {
        $gutenberg = new CFM_Gutenberg();
        $this->assertNotFalse( has_action( 'enqueue_block_editor_assets', array( $gutenberg, 'enqueue_editor_assets' ) ) );
    }

    /**
     * Test constructor registers rest_api_init hook
     */
    public function test_constructor_registers_rest_api_init_hook() {
        $gutenberg = new CFM_Gutenberg();
        $this->assertNotFalse( has_action( 'rest_api_init', array( $gutenberg, 'register_meta' ) ) );
    }

    /**
     * Test register_meta method is callable
     */
    public function test_register_meta_is_callable() {
        $gutenberg = new CFM_Gutenberg();
        $this->assertTrue( is_callable( array( $gutenberg, 'register_meta' ) ) );
    }

    /**
     * Test enqueue_editor_assets method is callable
     */
    public function test_enqueue_editor_assets_is_callable() {
        $gutenberg = new CFM_Gutenberg();
        $this->assertTrue( is_callable( array( $gutenberg, 'enqueue_editor_assets' ) ) );
    }

    /**
     * Test register_meta registers meta for post type
     */
    public function test_register_meta_registers_post_meta() {
        $gutenberg = new CFM_Gutenberg();
        $gutenberg->register_meta();

        // Get registered meta for posts
        $registered = get_registered_meta_keys( 'post', 'post' );

        $this->assertArrayHasKey( CFM_Scanner::REVIEWED_META_KEY, $registered );
    }

    /**
     * Test registered meta has correct type
     */
    public function test_registered_meta_type_is_string() {
        $gutenberg = new CFM_Gutenberg();
        $gutenberg->register_meta();

        $registered = get_registered_meta_keys( 'post', 'post' );

        $this->assertEquals( 'string', $registered[ CFM_Scanner::REVIEWED_META_KEY ]['type'] );
    }

    /**
     * Test registered meta is single value
     */
    public function test_registered_meta_is_single() {
        $gutenberg = new CFM_Gutenberg();
        $gutenberg->register_meta();

        $registered = get_registered_meta_keys( 'post', 'post' );

        $this->assertTrue( $registered[ CFM_Scanner::REVIEWED_META_KEY ]['single'] );
    }

    /**
     * Test registered meta shows in REST
     */
    public function test_registered_meta_shows_in_rest() {
        $gutenberg = new CFM_Gutenberg();
        $gutenberg->register_meta();

        $registered = get_registered_meta_keys( 'post', 'post' );

        $this->assertTrue( $registered[ CFM_Scanner::REVIEWED_META_KEY ]['show_in_rest'] );
    }

    /**
     * Test meta auth callback requires edit_posts capability
     */
    public function test_meta_auth_callback_requires_edit_posts() {
        $gutenberg = new CFM_Gutenberg();
        $gutenberg->register_meta();

        $registered = get_registered_meta_keys( 'post', 'post' );
        $auth_callback = $registered[ CFM_Scanner::REVIEWED_META_KEY ]['auth_callback'];

        // Test with editor (has edit_posts)
        wp_set_current_user( self::$editor_id );
        $this->assertTrue( call_user_func( $auth_callback ) );

        // Test with subscriber (no edit_posts)
        wp_set_current_user( self::$subscriber_id );
        $this->assertFalse( call_user_func( $auth_callback ) );
    }

    /**
     * Test enqueue_editor_assets returns early without global post
     */
    public function test_enqueue_editor_assets_returns_without_post() {
        global $post;
        $original_post = $post;
        $post = null;

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        // Script should not be enqueued
        $this->assertFalse( wp_script_is( 'cfm-gutenberg-sidebar', 'enqueued' ) );

        $post = $original_post;
    }

    /**
     * Test enqueue_editor_assets returns for non-monitored post type
     */
    public function test_enqueue_editor_assets_returns_for_non_monitored_type() {
        global $post;
        $original_post = $post;

        // Create a custom post type that's not monitored
        $unmonitored_post_id = self::factory()->post->create( array(
            'post_type' => 'attachment',
        ) );

        $post = get_post( $unmonitored_post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        // Script should not be enqueued
        $this->assertFalse( wp_script_is( 'cfm-gutenberg-sidebar', 'enqueued' ) );

        $post = $original_post;
    }

    /**
     * Test enqueue_editor_assets enqueues script for monitored post type
     */
    public function test_enqueue_editor_assets_enqueues_script() {
        global $post;
        $original_post = $post;
        $post = get_post( self::$post_id );

        // Define CFM constants if not defined
        if ( ! defined( 'CFM_PLUGIN_URL' ) ) {
            define( 'CFM_PLUGIN_URL', 'http://example.org/wp-content/plugins/content-freshness-monitor/' );
        }
        if ( ! defined( 'CFM_VERSION' ) ) {
            define( 'CFM_VERSION', '2.3.8' );
        }

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $this->assertTrue( wp_script_is( 'cfm-gutenberg-sidebar', 'enqueued' ) );

        $post = $original_post;
    }

    /**
     * Test enqueue_editor_assets enqueues style for monitored post type
     */
    public function test_enqueue_editor_assets_enqueues_style() {
        global $post;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $this->assertTrue( wp_style_is( 'cfm-gutenberg-sidebar', 'enqueued' ) );

        $post = $original_post;
    }

    /**
     * Test localized script data contains postId
     */
    public function test_localized_data_contains_post_id() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"postId":' . self::$post_id, $data );

        $post = $original_post;
    }

    /**
     * Test localized script data contains daysOld
     */
    public function test_localized_data_contains_days_old() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"daysOld":', $data );

        $post = $original_post;
    }

    /**
     * Test localized script data contains threshold
     */
    public function test_localized_data_contains_threshold() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"threshold":', $data );

        $post = $original_post;
    }

    /**
     * Test localized script data contains status
     */
    public function test_localized_data_contains_status() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"status":', $data );

        $post = $original_post;
    }

    /**
     * Test localized script data contains nonce
     */
    public function test_localized_data_contains_nonce() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"nonce":', $data );

        $post = $original_post;
    }

    /**
     * Test localized script data contains ajaxUrl
     */
    public function test_localized_data_contains_ajax_url() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"ajaxUrl":', $data );
        $this->assertStringContainsString( 'admin-ajax.php', $data );

        $post = $original_post;
    }

    /**
     * Test localized script data contains restUrl
     */
    public function test_localized_data_contains_rest_url() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"restUrl":', $data );

        $post = $original_post;
    }

    /**
     * Test localized script data contains restNonce
     */
    public function test_localized_data_contains_rest_nonce() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"restNonce":', $data );

        $post = $original_post;
    }

    /**
     * Test localized script data contains i18n strings
     */
    public function test_localized_data_contains_i18n() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"i18n":', $data );

        $post = $original_post;
    }

    /**
     * Test i18n contains title translation
     */
    public function test_i18n_contains_title() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"title":"Content Freshness"', $data );

        $post = $original_post;
    }

    /**
     * Test i18n contains status labels
     */
    public function test_i18n_contains_status_labels() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"fresh":"Fresh"', $data );
        $this->assertStringContainsString( '"aging":"Aging"', $data );
        $this->assertStringContainsString( '"stale":"Stale"', $data );

        $post = $original_post;
    }

    /**
     * Test i18n contains action labels
     */
    public function test_i18n_contains_action_labels() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"markReviewed":"Mark as Reviewed"', $data );
        $this->assertStringContainsString( '"reviewing":"Marking..."', $data );
        $this->assertStringContainsString( '"reviewed":"Reviewed!"', $data );

        $post = $original_post;
    }

    /**
     * Test localized script data contains settingsUrl
     */
    public function test_localized_data_contains_settings_url() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"settingsUrl":', $data );
        $this->assertStringContainsString( 'cfm-settings', $data );

        $post = $original_post;
    }

    /**
     * Test localized script data contains statusClass
     */
    public function test_localized_data_contains_status_class() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"statusClass":', $data );

        $post = $original_post;
    }

    /**
     * Test localized script data contains lastModified
     */
    public function test_localized_data_contains_last_modified() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"lastModified":', $data );

        $post = $original_post;
    }

    /**
     * Test localized script data contains lastReviewed
     */
    public function test_localized_data_contains_last_reviewed() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"lastReviewed":', $data );

        $post = $original_post;
    }

    /**
     * Test post with last reviewed meta includes date in localized data
     */
    public function test_localized_data_last_reviewed_with_meta() {
        global $post, $wp_scripts;
        $original_post = $post;

        // Create post with last reviewed meta
        $reviewed_post_id = self::factory()->post->create( array(
            'post_title'    => 'Reviewed Post',
            'post_status'   => 'publish',
        ) );

        $review_date = '2025-12-01 10:00:00';
        update_post_meta( $reviewed_post_id, CFM_Scanner::REVIEWED_META_KEY, $review_date );

        $post = get_post( $reviewed_post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( $review_date, $data );

        $post = $original_post;
    }

    /**
     * Test script dependencies are correct
     */
    public function test_script_dependencies() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $script = $wp_scripts->registered['cfm-gutenberg-sidebar'];
        $expected_deps = array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' );

        foreach ( $expected_deps as $dep ) {
            $this->assertContains( $dep, $script->deps, "Missing dependency: $dep" );
        }

        $post = $original_post;
    }

    /**
     * Test script is loaded in footer
     */
    public function test_script_loads_in_footer() {
        global $post, $wp_scripts;
        $original_post = $post;
        $post = get_post( self::$post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $script = $wp_scripts->registered['cfm-gutenberg-sidebar'];
        $this->assertTrue( $script->extra['group'] === 1 || ! isset( $script->extra['group'] ) );

        $post = $original_post;
    }

    /**
     * Test register_meta works for page post type
     */
    public function test_register_meta_for_page_type() {
        $gutenberg = new CFM_Gutenberg();
        $gutenberg->register_meta();

        $registered = get_registered_meta_keys( 'post', 'page' );

        $this->assertArrayHasKey( CFM_Scanner::REVIEWED_META_KEY, $registered );
    }

    /**
     * Test stale post shows stale status in localized data
     */
    public function test_stale_post_shows_stale_status() {
        global $post, $wp_scripts;
        $original_post = $post;

        // Create a very stale post (300 days old)
        $stale_post_id = self::factory()->post->create( array(
            'post_title'    => 'Very Stale Post',
            'post_status'   => 'publish',
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-300 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-300 days' ) ),
        ) );

        $post = get_post( $stale_post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"status":"Stale"', $data );

        $post = $original_post;
    }

    /**
     * Test fresh post shows fresh status in localized data
     */
    public function test_fresh_post_shows_fresh_status() {
        global $post, $wp_scripts;
        $original_post = $post;

        // Create a fresh post (1 day old)
        $fresh_post_id = self::factory()->post->create( array(
            'post_title'    => 'Fresh Post',
            'post_status'   => 'publish',
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
        ) );

        $post = get_post( $fresh_post_id );

        $gutenberg = new CFM_Gutenberg();
        $gutenberg->enqueue_editor_assets();

        $data = $wp_scripts->get_data( 'cfm-gutenberg-sidebar', 'data' );
        $this->assertStringContainsString( '"status":"Fresh"', $data );

        $post = $original_post;
    }
}
