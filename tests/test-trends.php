<?php
/**
 * Unit tests for CFM_Trends class
 */

class Test_CFM_Trends extends WP_UnitTestCase {

    /**
     * Set up test fixtures
     */
    public function setUp(): void {
        parent::setUp();
        // Clear any existing history
        delete_option( CFM_Trends::HISTORY_OPTION );
    }

    /**
     * Tear down test fixtures
     */
    public function tearDown(): void {
        // Clean up
        delete_option( CFM_Trends::HISTORY_OPTION );
        wp_clear_scheduled_hook( 'cfm_daily_snapshot' );
        parent::tearDown();
    }

    /**
     * Test HISTORY_OPTION constant
     */
    public function test_history_option_constant() {
        $this->assertEquals( 'cfm_stats_history', CFM_Trends::HISTORY_OPTION );
    }

    /**
     * Test MAX_DATA_POINTS constant
     */
    public function test_max_data_points_constant() {
        $this->assertEquals( 90, CFM_Trends::MAX_DATA_POINTS );
    }

    /**
     * Test get_history returns empty array when no data
     */
    public function test_get_history_returns_empty_array_when_no_data() {
        $history = CFM_Trends::get_history();
        $this->assertIsArray( $history );
        $this->assertEmpty( $history );
    }

    /**
     * Test get_history returns data when present
     */
    public function test_get_history_returns_data_when_present() {
        $test_data = array(
            array(
                'date'          => '2025-12-01',
                'total'         => 100,
                'stale'         => 20,
                'fresh'         => 80,
                'stale_percent' => 20,
            ),
            array(
                'date'          => '2025-12-02',
                'total'         => 100,
                'stale'         => 18,
                'fresh'         => 82,
                'stale_percent' => 18,
            ),
        );

        update_option( CFM_Trends::HISTORY_OPTION, $test_data );
        $history = CFM_Trends::get_history();

        $this->assertCount( 2, $history );
        $this->assertEquals( '2025-12-01', $history[0]['date'] );
        $this->assertEquals( '2025-12-02', $history[1]['date'] );
    }

    /**
     * Test get_history limits results by days parameter
     */
    public function test_get_history_limits_by_days() {
        $test_data = array();
        for ( $i = 1; $i <= 30; $i++ ) {
            $test_data[] = array(
                'date'          => sprintf( '2025-12-%02d', $i ),
                'total'         => 100,
                'stale'         => 20 - $i,
                'fresh'         => 80 + $i,
                'stale_percent' => 20 - $i,
            );
        }

        update_option( CFM_Trends::HISTORY_OPTION, $test_data );

        // Request 7 days
        $history = CFM_Trends::get_history( 7 );
        $this->assertCount( 7, $history );

        // Should return the LAST 7 entries (most recent)
        $this->assertEquals( '2025-12-24', $history[0]['date'] );
        $this->assertEquals( '2025-12-30', $history[6]['date'] );
    }

    /**
     * Test get_history with days exceeding available data
     */
    public function test_get_history_with_days_exceeding_data() {
        $test_data = array(
            array(
                'date'          => '2025-12-01',
                'total'         => 100,
                'stale'         => 20,
                'fresh'         => 80,
                'stale_percent' => 20,
            ),
        );

        update_option( CFM_Trends::HISTORY_OPTION, $test_data );

        // Request 30 days when only 1 exists
        $history = CFM_Trends::get_history( 30 );
        $this->assertCount( 1, $history );
    }

    /**
     * Test history data structure
     */
    public function test_history_data_structure() {
        $test_data = array(
            array(
                'date'          => '2025-12-27',
                'total'         => 150,
                'stale'         => 30,
                'fresh'         => 120,
                'stale_percent' => 20,
            ),
        );

        update_option( CFM_Trends::HISTORY_OPTION, $test_data );
        $history = CFM_Trends::get_history();

        $this->assertArrayHasKey( 'date', $history[0] );
        $this->assertArrayHasKey( 'total', $history[0] );
        $this->assertArrayHasKey( 'stale', $history[0] );
        $this->assertArrayHasKey( 'fresh', $history[0] );
        $this->assertArrayHasKey( 'stale_percent', $history[0] );
    }

    /**
     * Test schedule_snapshot creates cron event
     */
    public function test_schedule_snapshot_creates_cron_event() {
        // Clear any existing schedule
        wp_clear_scheduled_hook( 'cfm_daily_snapshot' );
        $this->assertFalse( wp_next_scheduled( 'cfm_daily_snapshot' ) );

        // Schedule snapshot
        $trends = new CFM_Trends();
        $trends->schedule_snapshot();

        // Verify cron is scheduled
        $this->assertNotFalse( wp_next_scheduled( 'cfm_daily_snapshot' ) );
    }

    /**
     * Test schedule_snapshot does not duplicate events
     */
    public function test_schedule_snapshot_does_not_duplicate() {
        // Clear and schedule
        wp_clear_scheduled_hook( 'cfm_daily_snapshot' );

        $trends = new CFM_Trends();
        $trends->schedule_snapshot();
        $first_schedule = wp_next_scheduled( 'cfm_daily_snapshot' );

        // Call again
        $trends->schedule_snapshot();
        $second_schedule = wp_next_scheduled( 'cfm_daily_snapshot' );

        // Should be the same (not rescheduled)
        $this->assertEquals( $first_schedule, $second_schedule );
    }

    /**
     * Test record_snapshot creates history entry
     */
    public function test_record_snapshot_creates_history() {
        // Create a test post to ensure stats work
        $this->factory->post->create( array(
            'post_status' => 'publish',
            'post_date'   => date( 'Y-m-d H:i:s', strtotime( '-10 days' ) ),
        ) );

        $trends = new CFM_Trends();
        $trends->record_snapshot();

        $history = get_option( CFM_Trends::HISTORY_OPTION, array() );
        $this->assertNotEmpty( $history );
        $this->assertCount( 1, $history );
        $this->assertEquals( current_time( 'Y-m-d' ), $history[0]['date'] );
    }

    /**
     * Test record_snapshot enforces MAX_DATA_POINTS limit
     */
    public function test_record_snapshot_enforces_max_limit() {
        // Create 95 existing entries (more than MAX_DATA_POINTS)
        $existing_data = array();
        for ( $i = 1; $i <= 95; $i++ ) {
            $existing_data[] = array(
                'date'          => sprintf( '2025-09-%02d', min( $i, 30 ) ),
                'total'         => 100,
                'stale'         => $i,
                'fresh'         => 100 - $i,
                'stale_percent' => $i,
            );
        }
        update_option( CFM_Trends::HISTORY_OPTION, $existing_data );

        // Record new snapshot
        $trends = new CFM_Trends();
        $trends->record_snapshot();

        $history = get_option( CFM_Trends::HISTORY_OPTION, array() );

        // Should be limited to MAX_DATA_POINTS (90)
        $this->assertLessThanOrEqual( CFM_Trends::MAX_DATA_POINTS, count( $history ) );
    }

    /**
     * Test record_snapshot includes all required fields
     */
    public function test_record_snapshot_includes_all_fields() {
        $trends = new CFM_Trends();
        $trends->record_snapshot();

        $history = get_option( CFM_Trends::HISTORY_OPTION, array() );
        $entry = $history[0];

        $this->assertArrayHasKey( 'date', $entry );
        $this->assertArrayHasKey( 'total', $entry );
        $this->assertArrayHasKey( 'stale', $entry );
        $this->assertArrayHasKey( 'fresh', $entry );
        $this->assertArrayHasKey( 'stale_percent', $entry );
    }

    /**
     * Test clear_history removes option
     */
    public function test_clear_history_removes_option() {
        $test_data = array(
            array(
                'date'          => '2025-12-27',
                'total'         => 100,
                'stale'         => 20,
                'fresh'         => 80,
                'stale_percent' => 20,
            ),
        );
        update_option( CFM_Trends::HISTORY_OPTION, $test_data );

        CFM_Trends::clear_history();

        $this->assertFalse( get_option( CFM_Trends::HISTORY_OPTION ) );
    }

    /**
     * Test clear_history unschedules cron event
     */
    public function test_clear_history_unschedules_cron() {
        // Schedule the cron
        wp_schedule_event( time(), 'daily', 'cfm_daily_snapshot' );
        $this->assertNotFalse( wp_next_scheduled( 'cfm_daily_snapshot' ) );

        // Clear history
        CFM_Trends::clear_history();

        // Cron should be unscheduled
        $this->assertFalse( wp_next_scheduled( 'cfm_daily_snapshot' ) );
    }

    /**
     * Test AJAX handler is registered
     */
    public function test_ajax_handler_registered() {
        $this->assertTrue( has_action( 'wp_ajax_cfm_get_trends' ) !== false );
    }

    /**
     * Test render_chart outputs HTML
     */
    public function test_render_chart_outputs_html() {
        ob_start();
        CFM_Trends::render_chart();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'cfm-trends-section', $output );
        $this->assertStringContainsString( 'Content Freshness Trends', $output );
    }

    /**
     * Test render_chart shows notice when insufficient data
     */
    public function test_render_chart_shows_notice_with_insufficient_data() {
        // Only one data point
        update_option( CFM_Trends::HISTORY_OPTION, array(
            array(
                'date'          => '2025-12-27',
                'total'         => 100,
                'stale'         => 20,
                'fresh'         => 80,
                'stale_percent' => 20,
            ),
        ) );

        ob_start();
        CFM_Trends::render_chart();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'cfm-trends-notice', $output );
        $this->assertStringContainsString( 'Trends data is collected daily', $output );
    }

    /**
     * Test render_chart shows chart when sufficient data
     */
    public function test_render_chart_shows_chart_with_sufficient_data() {
        // Two or more data points
        update_option( CFM_Trends::HISTORY_OPTION, array(
            array(
                'date'          => '2025-12-26',
                'total'         => 100,
                'stale'         => 22,
                'fresh'         => 78,
                'stale_percent' => 22,
            ),
            array(
                'date'          => '2025-12-27',
                'total'         => 100,
                'stale'         => 20,
                'fresh'         => 80,
                'stale_percent' => 20,
            ),
        ) );

        ob_start();
        CFM_Trends::render_chart();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'cfm-trends-controls', $output );
        $this->assertStringContainsString( 'cfm-chart-container', $output );
        $this->assertStringContainsString( 'cfm-trends-chart', $output );
        $this->assertStringContainsString( 'cfm-chart-legend', $output );
    }

    /**
     * Test render_chart includes time range buttons
     */
    public function test_render_chart_includes_time_range_buttons() {
        update_option( CFM_Trends::HISTORY_OPTION, array(
            array( 'date' => '2025-12-26', 'total' => 100, 'stale' => 20, 'fresh' => 80, 'stale_percent' => 20 ),
            array( 'date' => '2025-12-27', 'total' => 100, 'stale' => 18, 'fresh' => 82, 'stale_percent' => 18 ),
        ) );

        ob_start();
        CFM_Trends::render_chart();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'data-days="7"', $output );
        $this->assertStringContainsString( 'data-days="30"', $output );
        $this->assertStringContainsString( 'data-days="90"', $output );
        $this->assertStringContainsString( '7 Days', $output );
        $this->assertStringContainsString( '30 Days', $output );
        $this->assertStringContainsString( '90 Days', $output );
    }

    /**
     * Test render_chart has accessibility attributes
     */
    public function test_render_chart_has_accessibility_attributes() {
        update_option( CFM_Trends::HISTORY_OPTION, array(
            array( 'date' => '2025-12-26', 'total' => 100, 'stale' => 20, 'fresh' => 80, 'stale_percent' => 20 ),
            array( 'date' => '2025-12-27', 'total' => 100, 'stale' => 18, 'fresh' => 82, 'stale_percent' => 18 ),
        ) );

        ob_start();
        CFM_Trends::render_chart();
        $output = ob_get_clean();

        // Check ARIA attributes
        $this->assertStringContainsString( 'role="group"', $output );
        $this->assertStringContainsString( 'aria-label="Trends time range selector"', $output );
        $this->assertStringContainsString( 'role="img"', $output );
        $this->assertStringContainsString( 'aria-label="Content freshness trends chart"', $output );
        $this->assertStringContainsString( 'aria-pressed=', $output );
    }

    /**
     * Test get_history default days is 30
     */
    public function test_get_history_default_days_is_30() {
        // Create 50 entries
        $test_data = array();
        for ( $i = 1; $i <= 50; $i++ ) {
            $test_data[] = array(
                'date'          => sprintf( '2025-11-%02d', min( $i, 30 ) ),
                'total'         => 100,
                'stale'         => $i,
                'fresh'         => 100 - $i,
                'stale_percent' => $i,
            );
        }
        update_option( CFM_Trends::HISTORY_OPTION, $test_data );

        // Call without days parameter
        $history = CFM_Trends::get_history();

        // Should return 30 entries (default)
        $this->assertCount( 30, $history );
    }

    /**
     * Test cron action hook is registered
     */
    public function test_cron_action_hook_registered() {
        $this->assertTrue( has_action( 'cfm_daily_snapshot' ) !== false );
    }

    /**
     * Test render_chart legend includes Fresh and Stale labels
     */
    public function test_render_chart_legend_labels() {
        update_option( CFM_Trends::HISTORY_OPTION, array(
            array( 'date' => '2025-12-26', 'total' => 100, 'stale' => 20, 'fresh' => 80, 'stale_percent' => 20 ),
            array( 'date' => '2025-12-27', 'total' => 100, 'stale' => 18, 'fresh' => 82, 'stale_percent' => 18 ),
        ) );

        ob_start();
        CFM_Trends::render_chart();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'cfm-legend-fresh', $output );
        $this->assertStringContainsString( 'cfm-legend-stale', $output );
        $this->assertStringContainsString( 'Fresh Content', $output );
        $this->assertStringContainsString( 'Stale Content', $output );
    }

    /**
     * Test history preserves insertion order
     */
    public function test_history_preserves_insertion_order() {
        $dates = array( '2025-12-20', '2025-12-21', '2025-12-22', '2025-12-23', '2025-12-24' );
        $test_data = array();
        foreach ( $dates as $date ) {
            $test_data[] = array(
                'date'          => $date,
                'total'         => 100,
                'stale'         => 20,
                'fresh'         => 80,
                'stale_percent' => 20,
            );
        }
        update_option( CFM_Trends::HISTORY_OPTION, $test_data );

        $history = CFM_Trends::get_history( 5 );

        // Verify order matches input
        for ( $i = 0; $i < 5; $i++ ) {
            $this->assertEquals( $dates[ $i ], $history[ $i ]['date'] );
        }
    }
}
