<?php
/**
 * Tests for CFM_Scanner class
 *
 * @package Content_Freshness_Monitor
 */

class Test_CFM_Scanner extends WP_UnitTestCase {

    /**
     * Test freshness status for fresh content
     */
    public function test_get_freshness_status_fresh() {
        // Default threshold is 180 days, so 30 days should be fresh
        $status = CFM_Scanner::get_freshness_status( 30 );

        $this->assertEquals( 'Fresh', $status['label'] );
        $this->assertEquals( 'cfm-fresh', $status['class'] );
    }

    /**
     * Test freshness status for aging content
     */
    public function test_get_freshness_status_aging() {
        // At 120 days (between 90-180 with default 180 threshold), should be aging
        $status = CFM_Scanner::get_freshness_status( 120 );

        $this->assertEquals( 'Aging', $status['label'] );
        $this->assertEquals( 'cfm-aging', $status['class'] );
    }

    /**
     * Test freshness status for stale content
     */
    public function test_get_freshness_status_stale() {
        // At 200 days (over 180 threshold), should be stale
        $status = CFM_Scanner::get_freshness_status( 200 );

        $this->assertEquals( 'Stale', $status['label'] );
        $this->assertEquals( 'cfm-stale', $status['class'] );
    }

    /**
     * Test is_stale returns false for non-existent post
     */
    public function test_is_stale_nonexistent_post() {
        $result = CFM_Scanner::is_stale( 999999 );
        $this->assertFalse( $result );
    }

    /**
     * Test is_stale with a fresh post
     */
    public function test_is_stale_fresh_post() {
        // Create a post with current timestamp
        $post_id = $this->factory->post->create( array(
            'post_status' => 'publish',
            'post_type'   => 'post',
        ) );

        $result = CFM_Scanner::is_stale( $post_id );
        $this->assertFalse( $result );

        wp_delete_post( $post_id, true );
    }

    /**
     * Test is_stale with an old post
     */
    public function test_is_stale_old_post() {
        // Create a post
        $post_id = $this->factory->post->create( array(
            'post_status' => 'publish',
            'post_type'   => 'post',
        ) );

        // Manually set the modified date to 200 days ago
        global $wpdb;
        $old_date = date( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
        $wpdb->update(
            $wpdb->posts,
            array(
                'post_modified'     => $old_date,
                'post_modified_gmt' => get_gmt_from_date( $old_date ),
            ),
            array( 'ID' => $post_id )
        );
        clean_post_cache( $post_id );

        $result = CFM_Scanner::is_stale( $post_id );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'days_old', $result );
        $this->assertGreaterThanOrEqual( 199, $result['days_old'] );

        wp_delete_post( $post_id, true );
    }

    /**
     * Test is_stale excludes non-monitored post types
     */
    public function test_is_stale_excluded_post_type() {
        // Register a custom post type not in monitored types
        register_post_type( 'custom_cpt' );

        $post_id = $this->factory->post->create( array(
            'post_status' => 'publish',
            'post_type'   => 'custom_cpt',
        ) );

        // Set old date
        global $wpdb;
        $old_date = date( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
        $wpdb->update(
            $wpdb->posts,
            array( 'post_modified' => $old_date ),
            array( 'ID' => $post_id )
        );
        clean_post_cache( $post_id );

        $result = CFM_Scanner::is_stale( $post_id );
        $this->assertFalse( $result );

        wp_delete_post( $post_id, true );
    }

    /**
     * Test get_stale_posts returns expected structure
     */
    public function test_get_stale_posts_structure() {
        $result = CFM_Scanner::get_stale_posts();

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'posts', $result );
        $this->assertArrayHasKey( 'total', $result );
        $this->assertArrayHasKey( 'pages', $result );
    }

    /**
     * Test get_stats returns expected structure
     */
    public function test_get_stats_structure() {
        $stats = CFM_Scanner::get_stats();

        $this->assertIsArray( $stats );
        $this->assertArrayHasKey( 'total', $stats );
        $this->assertArrayHasKey( 'stale', $stats );
        $this->assertArrayHasKey( 'fresh', $stats );
        $this->assertArrayHasKey( 'stale_percent', $stats );
        $this->assertArrayHasKey( 'threshold', $stats );
    }

    /**
     * Test stats calculation is accurate
     */
    public function test_get_stats_calculation() {
        // Clean up any existing posts
        $posts = get_posts( array( 'numberposts' => -1, 'post_status' => 'any' ) );
        foreach ( $posts as $post ) {
            wp_delete_post( $post->ID, true );
        }

        // Create 2 fresh posts
        $this->factory->post->create( array( 'post_status' => 'publish' ) );
        $this->factory->post->create( array( 'post_status' => 'publish' ) );

        $stats = CFM_Scanner::get_stats();

        $this->assertEquals( 2, $stats['total'] );
        $this->assertEquals( 0, $stats['stale'] );
        $this->assertEquals( 2, $stats['fresh'] );
        $this->assertEquals( 0, $stats['stale_percent'] );
    }

    /**
     * Test REVIEWED_META_KEY constant exists
     */
    public function test_reviewed_meta_key_constant() {
        $this->assertEquals( '_cfm_last_reviewed', CFM_Scanner::REVIEWED_META_KEY );
    }

    /**
     * Test get_health_score returns expected structure
     */
    public function test_get_health_score_structure() {
        $stats = array(
            'total'         => 100,
            'stale'         => 10,
            'fresh'         => 90,
            'stale_percent' => 10,
        );
        $health = CFM_Scanner::get_health_score( $stats );

        $this->assertIsArray( $health );
        $this->assertArrayHasKey( 'score', $health );
        $this->assertArrayHasKey( 'grade', $health );
        $this->assertArrayHasKey( 'label', $health );
        $this->assertArrayHasKey( 'class', $health );
    }

    /**
     * Test health score grade A (90-100%)
     */
    public function test_get_health_score_grade_a() {
        $stats = array(
            'total'         => 100,
            'stale'         => 5,
            'fresh'         => 95,
            'stale_percent' => 5,
        );
        $health = CFM_Scanner::get_health_score( $stats );

        $this->assertEquals( 'A', $health['grade'] );
        $this->assertEquals( 95, $health['score'] );
        $this->assertEquals( 'cfm-grade-a', $health['class'] );
    }

    /**
     * Test health score grade B (80-89%)
     */
    public function test_get_health_score_grade_b() {
        $stats = array(
            'total'         => 100,
            'stale'         => 15,
            'fresh'         => 85,
            'stale_percent' => 15,
        );
        $health = CFM_Scanner::get_health_score( $stats );

        $this->assertEquals( 'B', $health['grade'] );
        $this->assertEquals( 85, $health['score'] );
        $this->assertEquals( 'cfm-grade-b', $health['class'] );
    }

    /**
     * Test health score grade C (70-79%)
     */
    public function test_get_health_score_grade_c() {
        $stats = array(
            'total'         => 100,
            'stale'         => 25,
            'fresh'         => 75,
            'stale_percent' => 25,
        );
        $health = CFM_Scanner::get_health_score( $stats );

        $this->assertEquals( 'C', $health['grade'] );
        $this->assertEquals( 75, $health['score'] );
        $this->assertEquals( 'cfm-grade-c', $health['class'] );
    }

    /**
     * Test health score grade D (60-69%)
     */
    public function test_get_health_score_grade_d() {
        $stats = array(
            'total'         => 100,
            'stale'         => 35,
            'fresh'         => 65,
            'stale_percent' => 35,
        );
        $health = CFM_Scanner::get_health_score( $stats );

        $this->assertEquals( 'D', $health['grade'] );
        $this->assertEquals( 65, $health['score'] );
        $this->assertEquals( 'cfm-grade-d', $health['class'] );
    }

    /**
     * Test health score grade F (below 60%)
     */
    public function test_get_health_score_grade_f() {
        $stats = array(
            'total'         => 100,
            'stale'         => 50,
            'fresh'         => 50,
            'stale_percent' => 50,
        );
        $health = CFM_Scanner::get_health_score( $stats );

        $this->assertEquals( 'F', $health['grade'] );
        $this->assertEquals( 50, $health['score'] );
        $this->assertEquals( 'cfm-grade-f', $health['class'] );
    }

    /**
     * Test health score with zero content returns A grade
     */
    public function test_get_health_score_empty_site() {
        $stats = array(
            'total'         => 0,
            'stale'         => 0,
            'fresh'         => 0,
            'stale_percent' => 0,
        );
        $health = CFM_Scanner::get_health_score( $stats );

        $this->assertEquals( 'A', $health['grade'] );
        $this->assertEquals( 100, $health['score'] );
    }

    /**
     * Test health score boundary at 90% (should be A)
     */
    public function test_get_health_score_boundary_90() {
        $stats = array(
            'total'         => 100,
            'stale'         => 10,
            'fresh'         => 90,
            'stale_percent' => 10,
        );
        $health = CFM_Scanner::get_health_score( $stats );

        $this->assertEquals( 'A', $health['grade'] );
    }

    /**
     * Test health score boundary at 89% (should be B)
     */
    public function test_get_health_score_boundary_89() {
        $stats = array(
            'total'         => 100,
            'stale'         => 11,
            'fresh'         => 89,
            'stale_percent' => 11,
        );
        $health = CFM_Scanner::get_health_score( $stats );

        $this->assertEquals( 'B', $health['grade'] );
    }
}
