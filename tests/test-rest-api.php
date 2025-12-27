<?php
/**
 * Class Test_CFM_REST_API
 *
 * Tests for the Content Freshness Monitor REST API.
 */

class Test_CFM_REST_API extends WP_Test_REST_TestCase {

    /**
     * Admin user ID
     */
    protected static $admin_id;

    /**
     * Editor user ID
     */
    protected static $editor_id;

    /**
     * Subscriber user ID
     */
    protected static $subscriber_id;

    /**
     * Test post ID
     */
    protected static $post_id;

    /**
     * Set up before class
     */
    public static function wpSetUpBeforeClass( $factory ) {
        self::$admin_id = $factory->user->create( array(
            'role' => 'administrator',
        ) );

        self::$editor_id = $factory->user->create( array(
            'role' => 'editor',
        ) );

        self::$subscriber_id = $factory->user->create( array(
            'role' => 'subscriber',
        ) );

        // Create a stale post (modified 200 days ago)
        self::$post_id = $factory->post->create( array(
            'post_title'    => 'Test Stale Post',
            'post_status'   => 'publish',
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
        ) );
    }

    /**
     * Test API namespace is registered
     */
    public function test_register_routes() {
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey( '/content-freshness-monitor/v1', $routes );
    }

    /**
     * Test stats endpoint requires authentication
     */
    public function test_stats_requires_auth() {
        $request  = new WP_REST_Request( 'GET', '/content-freshness-monitor/v1/stats' );
        $response = rest_get_server()->dispatch( $request );

        $this->assertEquals( 401, $response->get_status() );
    }

    /**
     * Test stats endpoint returns data for editor
     */
    public function test_stats_returns_data_for_editor() {
        wp_set_current_user( self::$editor_id );

        $request  = new WP_REST_Request( 'GET', '/content-freshness-monitor/v1/stats' );
        $response = rest_get_server()->dispatch( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertArrayHasKey( 'total_posts', $data );
        $this->assertArrayHasKey( 'stale_posts', $data );
        $this->assertArrayHasKey( 'fresh_posts', $data );
        $this->assertArrayHasKey( 'stale_percent', $data );
        $this->assertArrayHasKey( 'threshold_days', $data );
    }

    /**
     * Test subscriber cannot access stats
     */
    public function test_subscriber_cannot_access_stats() {
        wp_set_current_user( self::$subscriber_id );

        $request  = new WP_REST_Request( 'GET', '/content-freshness-monitor/v1/stats' );
        $response = rest_get_server()->dispatch( $request );

        $this->assertEquals( 403, $response->get_status() );
    }

    /**
     * Test stale posts endpoint with pagination
     */
    public function test_stale_posts_pagination() {
        wp_set_current_user( self::$editor_id );

        $request = new WP_REST_Request( 'GET', '/content-freshness-monitor/v1/stale' );
        $request->set_param( 'per_page', 10 );
        $request->set_param( 'page', 1 );

        $response = rest_get_server()->dispatch( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertArrayHasKey( 'posts', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertArrayHasKey( 'total_pages', $data );
        $this->assertArrayHasKey( 'page', $data );
        $this->assertArrayHasKey( 'per_page', $data );
    }

    /**
     * Test stale posts orderby validation
     */
    public function test_stale_posts_orderby_validation() {
        wp_set_current_user( self::$editor_id );

        $request = new WP_REST_Request( 'GET', '/content-freshness-monitor/v1/stale' );
        $request->set_param( 'orderby', 'invalid_column' );

        $response = rest_get_server()->dispatch( $request );

        $this->assertEquals( 400, $response->get_status() );
    }

    /**
     * Test post freshness endpoint
     */
    public function test_post_freshness() {
        wp_set_current_user( self::$editor_id );

        $request  = new WP_REST_Request( 'GET', '/content-freshness-monitor/v1/post/' . self::$post_id . '/freshness' );
        $response = rest_get_server()->dispatch( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( self::$post_id, $data['post_id'] );
        $this->assertArrayHasKey( 'status', $data );
        $this->assertArrayHasKey( 'is_stale', $data );
        $this->assertArrayHasKey( 'days_old', $data );
    }

    /**
     * Test post freshness 404 for non-existent post
     */
    public function test_post_freshness_404() {
        wp_set_current_user( self::$editor_id );

        $request  = new WP_REST_Request( 'GET', '/content-freshness-monitor/v1/post/999999/freshness' );
        $response = rest_get_server()->dispatch( $request );

        $this->assertEquals( 404, $response->get_status() );
    }

    /**
     * Test mark post as reviewed
     */
    public function test_mark_post_reviewed() {
        wp_set_current_user( self::$editor_id );

        $request  = new WP_REST_Request( 'POST', '/content-freshness-monitor/v1/post/' . self::$post_id . '/review' );
        $response = rest_get_server()->dispatch( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertTrue( $data['success'] );
        $this->assertEquals( self::$post_id, $data['post_id'] );
        $this->assertArrayHasKey( 'reviewed_date', $data );
    }

    /**
     * Test bulk mark reviewed
     */
    public function test_bulk_mark_reviewed() {
        wp_set_current_user( self::$editor_id );

        $request = new WP_REST_Request( 'POST', '/content-freshness-monitor/v1/bulk-review' );
        $request->set_param( 'post_ids', array( self::$post_id ) );

        $response = rest_get_server()->dispatch( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertEquals( 1, $data['updated_count'] );
        $this->assertContains( self::$post_id, $data['updated_posts'] );
    }

    /**
     * Test settings endpoint requires admin
     */
    public function test_settings_requires_admin() {
        wp_set_current_user( self::$editor_id );

        $request  = new WP_REST_Request( 'GET', '/content-freshness-monitor/v1/settings' );
        $response = rest_get_server()->dispatch( $request );

        $this->assertEquals( 403, $response->get_status() );
    }

    /**
     * Test admin can access settings
     */
    public function test_admin_can_access_settings() {
        wp_set_current_user( self::$admin_id );

        $request  = new WP_REST_Request( 'GET', '/content-freshness-monitor/v1/settings' );
        $response = rest_get_server()->dispatch( $request );
        $data     = $response->get_data();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertArrayHasKey( 'threshold_days', $data );
        $this->assertArrayHasKey( 'monitored_post_types', $data );
    }
}
