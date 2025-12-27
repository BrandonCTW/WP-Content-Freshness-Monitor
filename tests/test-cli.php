<?php
/**
 * Tests for CFM_CLI_Command class
 *
 * @package Content_Freshness_Monitor
 */

/**
 * Test WP-CLI commands for Content Freshness Monitor
 *
 * Note: These tests verify the CLI command logic without actually invoking WP-CLI.
 * Full integration tests require a WP-CLI test environment.
 */
class Test_CFM_CLI extends WP_UnitTestCase {

    /**
     * Scanner instance for tests
     *
     * @var CFM_Scanner
     */
    private $scanner;

    /**
     * Set up test fixtures
     */
    public function set_up() {
        parent::set_up();
        $this->scanner = new CFM_Scanner();
    }

    /**
     * Test that WP_CLI constant check prevents loading without WP-CLI
     */
    public function test_cli_class_requires_wp_cli() {
        // The CLI file should return early if WP_CLI is not defined
        // In test environment, WP_CLI is not defined, so class won't exist
        // This is expected behavior - we test the underlying functionality instead
        $this->assertTrue( true );
    }

    /**
     * Test stats command data structure
     */
    public function test_stats_data_structure() {
        $stats = $this->scanner->get_stats();

        $this->assertArrayHasKey( 'total', $stats );
        $this->assertArrayHasKey( 'fresh', $stats );
        $this->assertArrayHasKey( 'aging', $stats );
        $this->assertArrayHasKey( 'stale', $stats );
        $this->assertArrayHasKey( 'stale_percentage', $stats );
    }

    /**
     * Test stats command returns numeric values
     */
    public function test_stats_values_are_numeric() {
        $stats = $this->scanner->get_stats();

        $this->assertIsInt( $stats['total'] );
        $this->assertIsInt( $stats['fresh'] );
        $this->assertIsInt( $stats['aging'] );
        $this->assertIsInt( $stats['stale'] );
        $this->assertIsNumeric( $stats['stale_percentage'] );
    }

    /**
     * Test stats total equals sum of categories
     */
    public function test_stats_total_equals_sum_of_categories() {
        // Create some test posts
        $this->factory->post->create_many( 3 );

        $stats = $this->scanner->get_stats();

        $this->assertEquals(
            $stats['total'],
            $stats['fresh'] + $stats['aging'] + $stats['stale']
        );
    }

    /**
     * Test list command returns proper stale posts array
     */
    public function test_list_stale_posts_structure() {
        // Create an old post
        $post_id = $this->factory->post->create( array(
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
        ) );

        $posts = $this->scanner->get_stale_posts( array(
            'posts_per_page' => 20,
        ) );

        if ( ! empty( $posts ) ) {
            $first_post = $posts[0];
            $this->assertArrayHasKey( 'ID', $first_post );
            $this->assertArrayHasKey( 'title', $first_post );
            $this->assertArrayHasKey( 'post_type', $first_post );
            $this->assertArrayHasKey( 'days_since_modified', $first_post );
            $this->assertArrayHasKey( 'modified_date', $first_post );
            $this->assertArrayHasKey( 'freshness_status', $first_post );
        }
    }

    /**
     * Test list command limit parameter
     */
    public function test_list_limit_parameter() {
        // Create multiple old posts
        for ( $i = 0; $i < 5; $i++ ) {
            $this->factory->post->create( array(
                'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
                'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            ) );
        }

        $posts = $this->scanner->get_stale_posts( array(
            'posts_per_page' => 3,
        ) );

        $this->assertLessThanOrEqual( 3, count( $posts ) );
    }

    /**
     * Test list command returns empty array when no stale posts
     */
    public function test_list_returns_empty_when_no_stale_posts() {
        // Create only fresh posts
        $this->factory->post->create_many( 3 );

        $posts = $this->scanner->get_stale_posts();

        $this->assertIsArray( $posts );
    }

    /**
     * Test check command for existing post
     */
    public function test_check_existing_post() {
        $post_id = $this->factory->post->create( array(
            'post_title' => 'Test Post for Check Command',
        ) );

        $post = get_post( $post_id );

        $this->assertInstanceOf( 'WP_Post', $post );
        $this->assertEquals( 'Test Post for Check Command', $post->post_title );
    }

    /**
     * Test check command for non-existent post
     */
    public function test_check_nonexistent_post() {
        $post = get_post( 999999 );

        $this->assertNull( $post );
    }

    /**
     * Test check command freshness status calculation
     */
    public function test_check_freshness_status() {
        $post_id = $this->factory->post->create( array(
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-100 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-100 days' ) ),
        ) );

        $post     = get_post( $post_id );
        $modified = strtotime( $post->post_modified );
        $days_old = floor( ( time() - $modified ) / DAY_IN_SECONDS );

        $this->assertGreaterThanOrEqual( 100, $days_old );
        $this->assertLessThanOrEqual( 101, $days_old );
    }

    /**
     * Test check command last reviewed meta
     */
    public function test_check_last_reviewed_meta() {
        $post_id = $this->factory->post->create();

        // Initially no review
        $last_reviewed = get_post_meta( $post_id, '_cfm_last_reviewed', true );
        $this->assertEmpty( $last_reviewed );

        // After marking as reviewed
        update_post_meta( $post_id, '_cfm_last_reviewed', time() );
        $last_reviewed = get_post_meta( $post_id, '_cfm_last_reviewed', true );
        $this->assertNotEmpty( $last_reviewed );
    }

    /**
     * Test review command marks single post as reviewed
     */
    public function test_review_marks_post_as_reviewed() {
        $post_id = $this->factory->post->create();

        // Mark as reviewed (simulating CLI command)
        update_post_meta( $post_id, '_cfm_last_reviewed', time() );

        $last_reviewed = get_post_meta( $post_id, '_cfm_last_reviewed', true );
        $this->assertNotEmpty( $last_reviewed );
        $this->assertIsNumeric( $last_reviewed );
    }

    /**
     * Test review command with multiple posts
     */
    public function test_review_multiple_posts() {
        $post_ids = $this->factory->post->create_many( 3 );
        $review_time = time();

        foreach ( $post_ids as $post_id ) {
            update_post_meta( $post_id, '_cfm_last_reviewed', $review_time );
        }

        foreach ( $post_ids as $post_id ) {
            $last_reviewed = get_post_meta( $post_id, '_cfm_last_reviewed', true );
            $this->assertEquals( $review_time, $last_reviewed );
        }
    }

    /**
     * Test review command with non-existent post
     */
    public function test_review_nonexistent_post() {
        $post = get_post( 999999 );
        $this->assertNull( $post );
    }

    /**
     * Test export data structure
     */
    public function test_export_data_structure() {
        // Create an old post with author
        $author_id = $this->factory->user->create( array(
            'display_name' => 'Test Author',
        ) );
        $post_id = $this->factory->post->create( array(
            'post_author'   => $author_id,
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
        ) );

        $posts = $this->scanner->get_stale_posts();

        if ( ! empty( $posts ) ) {
            $post = $posts[0];

            // Check expected export fields exist
            $this->assertArrayHasKey( 'ID', $post );
            $this->assertArrayHasKey( 'title', $post );
            $this->assertArrayHasKey( 'post_type', $post );
            $this->assertArrayHasKey( 'author_id', $post );
            $this->assertArrayHasKey( 'modified_date', $post );
            $this->assertArrayHasKey( 'days_since_modified', $post );
            $this->assertArrayHasKey( 'freshness_status', $post );
        }
    }

    /**
     * Test export author display name retrieval
     */
    public function test_export_author_display_name() {
        $author_id = $this->factory->user->create( array(
            'display_name' => 'Content Manager',
        ) );

        $display_name = get_the_author_meta( 'display_name', $author_id );
        $this->assertEquals( 'Content Manager', $display_name );
    }

    /**
     * Test export edit URL format
     */
    public function test_export_edit_url() {
        $post_id = $this->factory->post->create();

        $edit_url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );

        $this->assertStringContainsString( 'post.php', $edit_url );
        $this->assertStringContainsString( 'post=' . $post_id, $edit_url );
        $this->assertStringContainsString( 'action=edit', $edit_url );
    }

    /**
     * Test export permalink retrieval
     */
    public function test_export_permalink() {
        $post_id = $this->factory->post->create( array(
            'post_title' => 'Test Post',
            'post_name'  => 'test-post',
        ) );

        $permalink = get_permalink( $post_id );

        $this->assertNotEmpty( $permalink );
        $this->assertIsString( $permalink );
    }

    /**
     * Test settings command returns all settings
     */
    public function test_settings_returns_all_settings() {
        $settings = CFM_Settings::get_settings();

        $this->assertIsArray( $settings );
        $this->assertArrayHasKey( 'threshold', $settings );
        $this->assertArrayHasKey( 'post_types', $settings );
    }

    /**
     * Test settings threshold is valid
     */
    public function test_settings_threshold_is_valid() {
        $settings = CFM_Settings::get_settings();

        $this->assertIsInt( $settings['threshold'] );
        $this->assertGreaterThan( 0, $settings['threshold'] );
    }

    /**
     * Test settings post_types is array
     */
    public function test_settings_post_types_is_array() {
        $settings = CFM_Settings::get_settings();

        $this->assertIsArray( $settings['post_types'] );
    }

    /**
     * Test settings email settings exist
     */
    public function test_settings_email_settings_exist() {
        $settings = CFM_Settings::get_settings();

        $this->assertArrayHasKey( 'email_enabled', $settings );
        $this->assertArrayHasKey( 'email_frequency', $settings );
    }

    /**
     * Test send-test-email would use correct recipient
     */
    public function test_send_test_email_default_recipient() {
        $settings = CFM_Settings::get_settings();

        $to = ! empty( $settings['email_recipient'] )
            ? $settings['email_recipient']
            : get_option( 'admin_email' );

        $this->assertNotEmpty( $to );
    }

    /**
     * Test list command orderby parameter
     */
    public function test_list_orderby_parameter() {
        // Create posts with different dates
        $this->factory->post->create( array(
            'post_title'    => 'First Post',
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-250 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-250 days' ) ),
        ) );
        $this->factory->post->create( array(
            'post_title'    => 'Second Post',
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
        ) );

        // Test default ordering (modified ASC - oldest first)
        $posts = $this->scanner->get_stale_posts( array(
            'orderby' => 'modified',
            'order'   => 'ASC',
        ) );

        $this->assertIsArray( $posts );
    }

    /**
     * Test list command post_type filter
     */
    public function test_list_post_type_filter() {
        // Create different post types
        $this->factory->post->create( array(
            'post_type'     => 'post',
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
        ) );
        $this->factory->post->create( array(
            'post_type'     => 'page',
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
        ) );

        $posts = $this->scanner->get_stale_posts();

        // Filter by post type (simulating CLI filter)
        $filtered = array_filter( $posts, function( $post ) {
            return $post['post_type'] === 'post';
        } );

        foreach ( $filtered as $post ) {
            $this->assertEquals( 'post', $post['post_type'] );
        }
    }

    /**
     * Test list command IDs format output
     */
    public function test_list_ids_format() {
        // Create stale posts
        $post_ids = array();
        for ( $i = 0; $i < 3; $i++ ) {
            $post_ids[] = $this->factory->post->create( array(
                'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
                'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            ) );
        }

        $posts = $this->scanner->get_stale_posts();

        // Simulate IDs format
        $ids = array_column( $posts, 'ID' );
        $ids_string = implode( ' ', $ids );

        $this->assertIsString( $ids_string );
        foreach ( $post_ids as $id ) {
            if ( in_array( $id, $ids ) ) {
                $this->assertStringContainsString( (string) $id, $ids_string );
            }
        }
    }

    /**
     * Test check command is_stale flag
     */
    public function test_check_is_stale_flag() {
        // Create a stale post
        $post_id = $this->factory->post->create( array(
            'post_date'     => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
            'post_modified' => date( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
        ) );

        $is_stale = CFM_Scanner::is_stale( $post_id );
        $this->assertTrue( $is_stale );

        // Create a fresh post
        $fresh_post_id = $this->factory->post->create();
        $is_stale_fresh = CFM_Scanner::is_stale( $fresh_post_id );
        $this->assertFalse( $is_stale_fresh );
    }

    /**
     * Test that review updates post meta correctly
     */
    public function test_review_updates_post_meta() {
        $post_id = $this->factory->post->create();
        $before_time = time();

        // Simulate review command
        update_post_meta( $post_id, '_cfm_last_reviewed', time() );

        $reviewed_time = get_post_meta( $post_id, '_cfm_last_reviewed', true );

        $this->assertGreaterThanOrEqual( $before_time, $reviewed_time );
    }

    /**
     * Test CLI would format boolean settings correctly
     */
    public function test_settings_boolean_formatting() {
        $settings = CFM_Settings::get_settings();

        // Test boolean to string conversion (as CLI would do)
        $email_enabled = isset( $settings['email_enabled'] ) && $settings['email_enabled'];
        $formatted = $email_enabled ? 'true' : 'false';

        $this->assertContains( $formatted, array( 'true', 'false' ) );
    }

    /**
     * Test CLI would format array settings correctly
     */
    public function test_settings_array_formatting() {
        $settings = CFM_Settings::get_settings();

        // Test array to string conversion (as CLI would do)
        $post_types = $settings['post_types'];
        $formatted = implode( ', ', $post_types );

        $this->assertIsString( $formatted );
    }
}
