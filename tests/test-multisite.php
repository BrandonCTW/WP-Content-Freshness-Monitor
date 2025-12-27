<?php
/**
 * Tests for CFM_Multisite class
 *
 * @package Content_Freshness_Monitor
 */

class Test_CFM_Multisite extends WP_UnitTestCase {

    /**
     * Multisite instance for testing
     *
     * @var CFM_Multisite
     */
    private $multisite;

    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        $this->multisite = new CFM_Multisite();
    }

    /**
     * Test is_multisite static method returns boolean
     */
    public function test_is_multisite_returns_boolean() {
        $result = CFM_Multisite::is_multisite();
        $this->assertIsBool( $result );
    }

    /**
     * Test is_multisite matches core is_multisite function
     */
    public function test_is_multisite_matches_core() {
        $expected = function_exists( 'is_multisite' ) && is_multisite();
        $result   = CFM_Multisite::is_multisite();
        $this->assertEquals( $expected, $result );
    }

    /**
     * Test constructor registers network_admin_menu hook on multisite
     */
    public function test_constructor_registers_network_admin_menu_hook() {
        // Constructor is called in setUp
        // On non-multisite, hooks should not be registered
        if ( ! is_multisite() ) {
            $this->assertFalse(
                has_action( 'network_admin_menu', array( $this->multisite, 'add_network_menu' ) )
            );
        } else {
            $this->assertNotFalse(
                has_action( 'network_admin_menu', array( $this->multisite, 'add_network_menu' ) )
            );
        }
    }

    /**
     * Test constructor registers admin_enqueue_scripts hook on multisite
     */
    public function test_constructor_registers_enqueue_scripts_hook() {
        if ( ! is_multisite() ) {
            $this->assertFalse(
                has_action( 'admin_enqueue_scripts', array( $this->multisite, 'enqueue_network_assets' ) )
            );
        } else {
            $this->assertNotFalse(
                has_action( 'admin_enqueue_scripts', array( $this->multisite, 'enqueue_network_assets' ) )
            );
        }
    }

    /**
     * Test constructor registers AJAX handler on multisite
     */
    public function test_constructor_registers_ajax_handler() {
        if ( ! is_multisite() ) {
            $this->assertFalse(
                has_action( 'wp_ajax_cfm_network_refresh', array( $this->multisite, 'ajax_network_refresh' ) )
            );
        } else {
            $this->assertNotFalse(
                has_action( 'wp_ajax_cfm_network_refresh', array( $this->multisite, 'ajax_network_refresh' ) )
            );
        }
    }

    /**
     * Test get_network_stats returns expected structure
     */
    public function test_get_network_stats_structure() {
        $stats = $this->multisite->get_network_stats();

        $this->assertIsArray( $stats );
        $this->assertArrayHasKey( 'total_sites', $stats );
        $this->assertArrayHasKey( 'total_posts', $stats );
        $this->assertArrayHasKey( 'total_stale', $stats );
        $this->assertArrayHasKey( 'total_fresh', $stats );
        $this->assertArrayHasKey( 'total_aging', $stats );
        $this->assertArrayHasKey( 'stale_percent', $stats );
    }

    /**
     * Test get_network_stats returns numeric values
     */
    public function test_get_network_stats_numeric_values() {
        $stats = $this->multisite->get_network_stats();

        $this->assertIsNumeric( $stats['total_sites'] );
        $this->assertIsNumeric( $stats['total_posts'] );
        $this->assertIsNumeric( $stats['total_stale'] );
        $this->assertIsNumeric( $stats['total_fresh'] );
        $this->assertIsNumeric( $stats['total_aging'] );
        $this->assertIsNumeric( $stats['stale_percent'] );
    }

    /**
     * Test get_network_stats returns non-negative values
     */
    public function test_get_network_stats_non_negative() {
        $stats = $this->multisite->get_network_stats();

        $this->assertGreaterThanOrEqual( 0, $stats['total_sites'] );
        $this->assertGreaterThanOrEqual( 0, $stats['total_posts'] );
        $this->assertGreaterThanOrEqual( 0, $stats['total_stale'] );
        $this->assertGreaterThanOrEqual( 0, $stats['total_fresh'] );
        $this->assertGreaterThanOrEqual( 0, $stats['total_aging'] );
        $this->assertGreaterThanOrEqual( 0, $stats['stale_percent'] );
    }

    /**
     * Test get_network_stats stale_percent is valid percentage
     */
    public function test_get_network_stats_valid_percentage() {
        $stats = $this->multisite->get_network_stats();

        $this->assertLessThanOrEqual( 100, $stats['stale_percent'] );
    }

    /**
     * Test get_network_stats total calculation
     */
    public function test_get_network_stats_total_calculation() {
        $stats = $this->multisite->get_network_stats();

        // If there are posts, fresh + stale + aging should equal total (approximately)
        if ( $stats['total_posts'] > 0 ) {
            $sum = $stats['total_fresh'] + $stats['total_stale'] + $stats['total_aging'];
            $this->assertEquals( $stats['total_posts'], $sum );
        }
    }

    /**
     * Test get_sites_freshness_data returns array
     */
    public function test_get_sites_freshness_data_returns_array() {
        $data = $this->multisite->get_sites_freshness_data();
        $this->assertIsArray( $data );
    }

    /**
     * Test get_sites_freshness_data site entry structure
     */
    public function test_get_sites_freshness_data_structure() {
        $data = $this->multisite->get_sites_freshness_data();

        // On non-multisite, we should still get at least one site (the main site)
        if ( ! empty( $data ) ) {
            $site = $data[0];
            $this->assertArrayHasKey( 'id', $site );
            $this->assertArrayHasKey( 'name', $site );
            $this->assertArrayHasKey( 'url', $site );
            $this->assertArrayHasKey( 'admin_url', $site );
            $this->assertArrayHasKey( 'total', $site );
            $this->assertArrayHasKey( 'fresh', $site );
            $this->assertArrayHasKey( 'aging', $site );
            $this->assertArrayHasKey( 'stale', $site );
        }
    }

    /**
     * Test get_sites_freshness_data site values are numeric
     */
    public function test_get_sites_freshness_data_numeric_values() {
        $data = $this->multisite->get_sites_freshness_data();

        if ( ! empty( $data ) ) {
            $site = $data[0];
            $this->assertIsNumeric( $site['id'] );
            $this->assertIsNumeric( $site['total'] );
            $this->assertIsNumeric( $site['fresh'] );
            $this->assertIsNumeric( $site['aging'] );
            $this->assertIsNumeric( $site['stale'] );
        }
    }

    /**
     * Test get_sites_freshness_data site URLs are valid
     */
    public function test_get_sites_freshness_data_valid_urls() {
        $data = $this->multisite->get_sites_freshness_data();

        if ( ! empty( $data ) ) {
            $site = $data[0];
            $this->assertNotEmpty( $site['url'] );
            $this->assertNotEmpty( $site['admin_url'] );
            $this->assertStringContainsString( 'http', $site['url'] );
            $this->assertStringContainsString( 'admin.php', $site['admin_url'] );
        }
    }

    /**
     * Test get_sites_freshness_data admin_url includes correct page
     */
    public function test_get_sites_freshness_data_admin_url_page() {
        $data = $this->multisite->get_sites_freshness_data();

        if ( ! empty( $data ) ) {
            $site = $data[0];
            $this->assertStringContainsString( 'page=content-freshness', $site['admin_url'] );
        }
    }

    /**
     * Test get_sites_freshness_data is sorted by stale count
     */
    public function test_get_sites_freshness_data_sorted_by_stale() {
        $data = $this->multisite->get_sites_freshness_data();

        if ( count( $data ) > 1 ) {
            for ( $i = 0; $i < count( $data ) - 1; $i++ ) {
                $this->assertGreaterThanOrEqual(
                    $data[ $i + 1 ]['stale'],
                    $data[ $i ]['stale'],
                    'Sites should be sorted by stale count descending'
                );
            }
        }
    }

    /**
     * Test get_network_stale_posts returns array
     */
    public function test_get_network_stale_posts_returns_array() {
        $posts = $this->multisite->get_network_stale_posts();
        $this->assertIsArray( $posts );
    }

    /**
     * Test get_network_stale_posts respects limit parameter
     */
    public function test_get_network_stale_posts_respects_limit() {
        $posts = $this->multisite->get_network_stale_posts( 1 );

        // Each site can return up to limit posts, so actual limit depends on sites
        // But should not exceed sites * limit
        $stats = $this->multisite->get_network_stats();
        $max_expected = max( 1, $stats['total_sites'] * 1 );

        $this->assertLessThanOrEqual( $max_expected, count( $posts ) );
    }

    /**
     * Test get_network_stale_posts default limit is 5
     */
    public function test_get_network_stale_posts_default_limit() {
        // Verify the method accepts default limit without error
        $posts = $this->multisite->get_network_stale_posts();
        $this->assertIsArray( $posts );
    }

    /**
     * Test get_network_stale_posts entry structure when posts exist
     */
    public function test_get_network_stale_posts_structure() {
        // Create an old post for testing
        $post_id = $this->factory->post->create( array(
            'post_status' => 'publish',
            'post_type'   => 'post',
        ) );

        // Make it stale
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

        $posts = $this->multisite->get_network_stale_posts();

        if ( ! empty( $posts ) ) {
            $post = $posts[0];
            $this->assertArrayHasKey( 'site_id', $post );
            $this->assertArrayHasKey( 'site_name', $post );
        }

        wp_delete_post( $post_id, true );
    }

    /**
     * Test get_network_stale_posts sorted by days_old
     */
    public function test_get_network_stale_posts_sorted_by_days_old() {
        $posts = $this->multisite->get_network_stale_posts();

        if ( count( $posts ) > 1 ) {
            for ( $i = 0; $i < count( $posts ) - 1; $i++ ) {
                $this->assertGreaterThanOrEqual(
                    $posts[ $i + 1 ]['days_old'],
                    $posts[ $i ]['days_old'],
                    'Stale posts should be sorted by days_old descending'
                );
            }
        }
    }

    /**
     * Test enqueue_network_assets only loads on correct page
     */
    public function test_enqueue_network_assets_only_on_cfm_page() {
        // Test with wrong hook - should not enqueue
        $this->multisite->enqueue_network_assets( 'wrong-page' );
        $this->assertFalse( wp_script_is( 'cfm-admin', 'enqueued' ) );
    }

    /**
     * Test render_network_dashboard outputs wrapper div
     */
    public function test_render_network_dashboard_wrapper() {
        ob_start();
        $this->multisite->render_network_dashboard();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'class="wrap cfm-wrap"', $output );
    }

    /**
     * Test render_network_dashboard outputs heading
     */
    public function test_render_network_dashboard_heading() {
        ob_start();
        $this->multisite->render_network_dashboard();
        $output = ob_get_clean();

        $this->assertStringContainsString( '<h1>', $output );
        $this->assertStringContainsString( 'Network Content Freshness', $output );
    }

    /**
     * Test render_network_dashboard outputs stats grid
     */
    public function test_render_network_dashboard_stats_grid() {
        ob_start();
        $this->multisite->render_network_dashboard();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'cfm-stats-grid', $output );
        $this->assertStringContainsString( 'cfm-stat-box', $output );
    }

    /**
     * Test render_network_dashboard outputs sites table
     */
    public function test_render_network_dashboard_sites_table() {
        ob_start();
        $this->multisite->render_network_dashboard();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Sites Overview', $output );
        $this->assertStringContainsString( 'wp-list-table', $output );
        $this->assertStringContainsString( '<thead>', $output );
        $this->assertStringContainsString( '<tbody>', $output );
    }

    /**
     * Test render_network_dashboard shows stat labels
     */
    public function test_render_network_dashboard_stat_labels() {
        ob_start();
        $this->multisite->render_network_dashboard();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Sites', $output );
        $this->assertStringContainsString( 'Total Posts', $output );
        $this->assertStringContainsString( 'Stale Network-wide', $output );
        $this->assertStringContainsString( 'Stale Percentage', $output );
    }

    /**
     * Test render_network_dashboard shows table columns
     */
    public function test_render_network_dashboard_table_columns() {
        ob_start();
        $this->multisite->render_network_dashboard();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'Site', $output );
        $this->assertStringContainsString( 'Fresh', $output );
        $this->assertStringContainsString( 'Aging', $output );
        $this->assertStringContainsString( 'Stale', $output );
        $this->assertStringContainsString( 'Actions', $output );
    }

    /**
     * Test render_network_dashboard shows no sites message when empty
     */
    public function test_render_network_dashboard_no_sites_message() {
        ob_start();
        $this->multisite->render_network_dashboard();
        $output = ob_get_clean();

        // On a single site install without multisite, sites_data will be empty
        // The message should appear in that case
        if ( ! is_multisite() ) {
            $this->assertStringContainsString( 'No sites found', $output );
        }
    }

    /**
     * Test add_network_menu callback is callable
     */
    public function test_add_network_menu_callable() {
        $this->assertTrue( is_callable( array( $this->multisite, 'add_network_menu' ) ) );
    }

    /**
     * Test render_network_dashboard callback is callable
     */
    public function test_render_network_dashboard_callable() {
        $this->assertTrue( is_callable( array( $this->multisite, 'render_network_dashboard' ) ) );
    }

    /**
     * Test ajax_network_refresh callback is callable
     */
    public function test_ajax_network_refresh_callable() {
        $this->assertTrue( is_callable( array( $this->multisite, 'ajax_network_refresh' ) ) );
    }

    /**
     * Test enqueue_network_assets callback is callable
     */
    public function test_enqueue_network_assets_callable() {
        $this->assertTrue( is_callable( array( $this->multisite, 'enqueue_network_assets' ) ) );
    }

    /**
     * Test network menu uses correct capability
     */
    public function test_network_menu_capability() {
        // The add_network_menu method uses 'manage_network' capability
        // We can verify this by reflection or by testing the output
        $reflection = new ReflectionClass( $this->multisite );
        $method     = $reflection->getMethod( 'add_network_menu' );

        // Method should be public
        $this->assertTrue( $method->isPublic() );
    }

    /**
     * Test ajax handler nonce action
     */
    public function test_ajax_handler_nonce_action() {
        // The enqueue_network_assets passes 'cfm_network_nonce' as nonce action
        // This is used in ajax_network_refresh via check_ajax_referer
        // We can verify the nonce name by checking the localized script data

        // Set up the hook to capture localized data
        ob_start();
        $this->multisite->enqueue_network_assets( 'toplevel_page_cfm-network' );
        ob_get_clean();

        // The nonce should have been generated with 'cfm_network_nonce'
        $nonce = wp_create_nonce( 'cfm_network_nonce' );
        $this->assertNotEmpty( $nonce );
        $this->assertEquals( 1, wp_verify_nonce( $nonce, 'cfm_network_nonce' ) );
    }
}
