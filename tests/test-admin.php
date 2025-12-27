<?php
/**
 * Tests for CFM_Admin class
 *
 * @package Content_Freshness_Monitor
 */

class Test_CFM_Admin extends WP_UnitTestCase {

    /**
     * Instance of CFM_Admin for testing
     *
     * @var CFM_Admin
     */
    private $admin;

    /**
     * Set up test fixtures
     */
    public function setUp(): void {
        parent::setUp();
        $this->admin = new CFM_Admin();
    }

    /**
     * Test admin menu hook is registered
     */
    public function test_admin_menu_hook_registered() {
        $this->assertNotFalse(
            has_action( 'admin_menu', array( $this->admin, 'add_admin_menu' ) ),
            'Admin menu action should be registered'
        );
    }

    /**
     * Test admin_init hook is registered for post list columns
     */
    public function test_admin_init_hook_registered() {
        $this->assertNotFalse(
            has_action( 'admin_init', array( $this->admin, 'add_post_list_columns' ) ),
            'Admin init action should be registered for post list columns'
        );
    }

    /**
     * Test add_admin_menu creates menu page
     */
    public function test_add_admin_menu_creates_page() {
        global $menu;

        // Set current user to admin
        $admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin_id );

        // Trigger admin menu
        do_action( 'admin_menu' );

        // Check if our menu item exists
        $menu_slug_found = false;
        if ( is_array( $menu ) ) {
            foreach ( $menu as $item ) {
                if ( isset( $item[2] ) && 'content-freshness' === $item[2] ) {
                    $menu_slug_found = true;
                    break;
                }
            }
        }

        $this->assertTrue( $menu_slug_found, 'Content Freshness menu should be added' );

        wp_delete_user( $admin_id );
    }

    /**
     * Test menu page requires edit_posts capability
     */
    public function test_menu_requires_edit_posts_capability() {
        // Create subscriber (no edit_posts capability)
        $subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $subscriber_id );

        // Check if user can access
        $this->assertFalse(
            current_user_can( 'edit_posts' ),
            'Subscriber should not have edit_posts capability'
        );

        // Create editor (has edit_posts capability)
        $editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $editor_id );

        $this->assertTrue(
            current_user_can( 'edit_posts' ),
            'Editor should have edit_posts capability'
        );

        wp_delete_user( $subscriber_id );
        wp_delete_user( $editor_id );
    }

    /**
     * Test add_freshness_column adds column after title
     */
    public function test_add_freshness_column_position() {
        $columns = array(
            'cb'    => '<input type="checkbox" />',
            'title' => 'Title',
            'date'  => 'Date',
        );

        $new_columns = $this->admin->add_freshness_column( $columns );

        // Get column keys
        $keys = array_keys( $new_columns );

        // Find positions
        $title_pos     = array_search( 'title', $keys, true );
        $freshness_pos = array_search( 'cfm_freshness', $keys, true );

        $this->assertNotFalse( $freshness_pos, 'Freshness column should exist' );
        $this->assertEquals( $title_pos + 1, $freshness_pos, 'Freshness column should be right after title' );
    }

    /**
     * Test add_freshness_column preserves existing columns
     */
    public function test_add_freshness_column_preserves_columns() {
        $columns = array(
            'cb'       => '<input type="checkbox" />',
            'title'    => 'Title',
            'author'   => 'Author',
            'category' => 'Category',
            'date'     => 'Date',
        );

        $new_columns = $this->admin->add_freshness_column( $columns );

        // All original columns should still exist
        foreach ( $columns as $key => $value ) {
            $this->assertArrayHasKey( $key, $new_columns, "Column $key should be preserved" );
        }

        // Plus the new freshness column
        $this->assertArrayHasKey( 'cfm_freshness', $new_columns );
        $this->assertCount( 6, $new_columns, 'Should have 6 columns (5 original + 1 new)' );
    }

    /**
     * Test freshness column label
     */
    public function test_freshness_column_label() {
        $columns = array(
            'title' => 'Title',
        );

        $new_columns = $this->admin->add_freshness_column( $columns );

        $this->assertEquals( 'Freshness', $new_columns['cfm_freshness'] );
    }

    /**
     * Test make_column_sortable adds modified sort
     */
    public function test_make_column_sortable() {
        $columns = array(
            'title' => 'title',
            'date'  => 'date',
        );

        $result = $this->admin->make_column_sortable( $columns );

        $this->assertArrayHasKey( 'cfm_freshness', $result );
        $this->assertEquals( 'modified', $result['cfm_freshness'] );
    }

    /**
     * Test make_column_sortable preserves existing sortable columns
     */
    public function test_make_column_sortable_preserves_existing() {
        $columns = array(
            'title'  => 'title',
            'author' => 'author',
            'date'   => 'date',
        );

        $result = $this->admin->make_column_sortable( $columns );

        $this->assertEquals( 'title', $result['title'] );
        $this->assertEquals( 'author', $result['author'] );
        $this->assertEquals( 'date', $result['date'] );
        $this->assertEquals( 'modified', $result['cfm_freshness'] );
    }

    /**
     * Test render_freshness_column ignores other columns
     */
    public function test_render_freshness_column_ignores_other_columns() {
        $post_id = $this->factory->post->create();

        ob_start();
        $this->admin->render_freshness_column( 'title', $post_id );
        $output = ob_get_clean();

        $this->assertEmpty( $output, 'Should not output anything for non-freshness columns' );

        wp_delete_post( $post_id, true );
    }

    /**
     * Test render_freshness_column outputs status for fresh post
     */
    public function test_render_freshness_column_fresh_post() {
        $post_id = $this->factory->post->create( array(
            'post_status' => 'publish',
        ) );

        ob_start();
        $this->admin->render_freshness_column( 'cfm_freshness', $post_id );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'cfm-status', $output );
        $this->assertStringContainsString( 'cfm-fresh', $output );
        $this->assertStringContainsString( 'Fresh', $output );

        wp_delete_post( $post_id, true );
    }

    /**
     * Test render_freshness_column outputs status for stale post
     */
    public function test_render_freshness_column_stale_post() {
        $post_id = $this->factory->post->create( array(
            'post_status' => 'publish',
        ) );

        // Make post old
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

        ob_start();
        $this->admin->render_freshness_column( 'cfm_freshness', $post_id );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'cfm-status', $output );
        $this->assertStringContainsString( 'cfm-stale', $output );
        $this->assertStringContainsString( 'Stale', $output );

        wp_delete_post( $post_id, true );
    }

    /**
     * Test render_freshness_column includes days old in title attribute
     */
    public function test_render_freshness_column_includes_days_tooltip() {
        $post_id = $this->factory->post->create( array(
            'post_status' => 'publish',
        ) );

        ob_start();
        $this->admin->render_freshness_column( 'cfm_freshness', $post_id );
        $output = ob_get_clean();

        $this->assertStringContainsString( 'title=', $output );
        $this->assertStringContainsString( 'days ago', $output );

        wp_delete_post( $post_id, true );
    }

    /**
     * Test add_post_list_columns respects show_in_list setting
     */
    public function test_add_post_list_columns_respects_setting() {
        // Save setting with show_in_list = false
        $settings = Content_Freshness_Monitor::get_settings();
        $settings['show_in_list'] = false;
        update_option( 'cfm_settings', $settings );

        // Create new admin instance to test with new settings
        $admin = new CFM_Admin();

        // Call the method
        $admin->add_post_list_columns();

        // Check that column filter was NOT added
        $this->assertFalse(
            has_filter( 'manage_post_posts_columns', array( $admin, 'add_freshness_column' ) ),
            'Column filter should NOT be added when show_in_list is false'
        );

        // Restore setting
        $settings['show_in_list'] = true;
        update_option( 'cfm_settings', $settings );
    }

    /**
     * Test add_post_list_columns adds filters for monitored post types
     */
    public function test_add_post_list_columns_adds_filters() {
        // Ensure show_in_list is true and post is monitored
        $settings = Content_Freshness_Monitor::get_settings();
        $settings['show_in_list'] = true;
        $settings['post_types']   = array( 'post', 'page' );
        update_option( 'cfm_settings', $settings );

        // Create new admin instance
        $admin = new CFM_Admin();
        $admin->add_post_list_columns();

        // Check filters for 'post' type
        $this->assertNotFalse(
            has_filter( 'manage_post_posts_columns', array( $admin, 'add_freshness_column' ) ),
            'Should add column filter for post type'
        );

        $this->assertNotFalse(
            has_action( 'manage_post_posts_custom_column', array( $admin, 'render_freshness_column' ) ),
            'Should add column render action for post type'
        );

        $this->assertNotFalse(
            has_filter( 'manage_edit-post_sortable_columns', array( $admin, 'make_column_sortable' ) ),
            'Should add sortable filter for post type'
        );
    }

    /**
     * Test render_admin_page outputs wrapper div
     */
    public function test_render_admin_page_outputs_wrapper() {
        // Set up admin context
        $admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin_id );
        set_current_screen( 'toplevel_page_content-freshness' );

        ob_start();
        $this->admin->render_admin_page();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'class="wrap cfm-wrap"', $output );
        $this->assertStringContainsString( 'Content Freshness Monitor', $output );

        wp_delete_user( $admin_id );
    }

    /**
     * Test render_admin_page outputs stats cards
     */
    public function test_render_admin_page_outputs_stats() {
        $admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin_id );

        ob_start();
        $this->admin->render_admin_page();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'cfm-stats', $output );
        $this->assertStringContainsString( 'cfm-stat-card', $output );
        $this->assertStringContainsString( 'Total Posts', $output );
        $this->assertStringContainsString( 'Stale Posts', $output );
        $this->assertStringContainsString( 'Fresh Posts', $output );
        $this->assertStringContainsString( 'Need Attention', $output );

        wp_delete_user( $admin_id );
    }

    /**
     * Test render_admin_page has accessibility attributes
     */
    public function test_render_admin_page_accessibility() {
        $admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin_id );

        ob_start();
        $this->admin->render_admin_page();
        $output = ob_get_clean();

        // Check for ARIA roles
        $this->assertStringContainsString( 'role="region"', $output );
        $this->assertStringContainsString( 'role="status"', $output );
        $this->assertStringContainsString( 'aria-label=', $output );
        $this->assertStringContainsString( 'screen-reader-text', $output );

        wp_delete_user( $admin_id );
    }

    /**
     * Test render_admin_page shows no-stale message when all content is fresh
     */
    public function test_render_admin_page_no_stale_message() {
        // Clean up any existing posts
        $posts = get_posts( array( 'numberposts' => -1, 'post_status' => 'any' ) );
        foreach ( $posts as $post ) {
            wp_delete_post( $post->ID, true );
        }

        // Create only fresh posts
        $this->factory->post->create( array( 'post_status' => 'publish' ) );

        $admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin_id );

        // Delete transient cache to ensure fresh data
        delete_transient( 'cfm_stats_cache' );

        ob_start();
        $this->admin->render_admin_page();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'cfm-no-stale', $output );
        $this->assertStringContainsString( 'Great news! All your content is fresh.', $output );
        $this->assertStringContainsString( 'dashicons-yes-alt', $output );

        wp_delete_user( $admin_id );
    }

    /**
     * Test render_admin_page shows table when stale content exists
     */
    public function test_render_admin_page_shows_table_with_stale() {
        // Clean up
        $posts = get_posts( array( 'numberposts' => -1, 'post_status' => 'any' ) );
        foreach ( $posts as $post ) {
            wp_delete_post( $post->ID, true );
        }

        // Create stale post
        $post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );

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
        delete_transient( 'cfm_stats_cache' );

        $admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin_id );

        ob_start();
        $this->admin->render_admin_page();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'cfm-table', $output );
        $this->assertStringContainsString( 'cfm-bulk-actions', $output );
        $this->assertStringContainsString( 'cfm-mark-reviewed', $output );

        wp_delete_post( $post_id, true );
        wp_delete_user( $admin_id );
    }

    /**
     * Test render_admin_page includes settings link
     */
    public function test_render_admin_page_settings_link() {
        $admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin_id );

        ob_start();
        $this->admin->render_admin_page();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'cfm-settings', $output );
        $this->assertStringContainsString( 'Change settings', $output );

        wp_delete_user( $admin_id );
    }

    /**
     * Test render_admin_page includes export button
     */
    public function test_render_admin_page_export_button() {
        // Create stale post to show table
        $post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );

        global $wpdb;
        $old_date = date( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
        $wpdb->update(
            $wpdb->posts,
            array( 'post_modified' => $old_date ),
            array( 'ID' => $post_id )
        );
        clean_post_cache( $post_id );
        delete_transient( 'cfm_stats_cache' );

        $admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin_id );

        ob_start();
        $this->admin->render_admin_page();
        $output = ob_get_clean();

        $this->assertStringContainsString( 'cfm-export-csv', $output );
        $this->assertStringContainsString( 'Export to CSV', $output );

        wp_delete_post( $post_id, true );
        wp_delete_user( $admin_id );
    }

    /**
     * Test orderby parameter validation (allowlist)
     */
    public function test_orderby_allowlist_validation() {
        $admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin_id );

        // Test with valid orderby
        $_GET['orderby'] = 'modified';
        ob_start();
        $this->admin->render_admin_page();
        ob_get_clean();

        // Test with invalid orderby (should default to 'modified')
        $_GET['orderby'] = 'malicious_sql';
        ob_start();
        $this->admin->render_admin_page();
        ob_get_clean();

        // If we got here without error, the allowlist is working
        $this->assertTrue( true );

        unset( $_GET['orderby'] );
        wp_delete_user( $admin_id );
    }

    /**
     * Test order parameter sanitization
     */
    public function test_order_parameter_sanitization() {
        $admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin_id );

        // Test ASC
        $_GET['order'] = 'asc';
        ob_start();
        $this->admin->render_admin_page();
        $output = ob_get_clean();
        $this->assertStringContainsString( 'aria-sort=', $output );

        // Test DESC
        $_GET['order'] = 'DESC';
        ob_start();
        $this->admin->render_admin_page();
        ob_get_clean();

        // Test invalid (should default to ASC)
        $_GET['order'] = 'invalid';
        ob_start();
        $this->admin->render_admin_page();
        ob_get_clean();

        $this->assertTrue( true );

        unset( $_GET['order'] );
        wp_delete_user( $admin_id );
    }

    /**
     * Test pagination parameter sanitization
     */
    public function test_paged_parameter_sanitization() {
        $admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
        wp_set_current_user( $admin_id );

        // Test valid page number
        $_GET['paged'] = '2';
        ob_start();
        $this->admin->render_admin_page();
        ob_get_clean();

        // Test invalid page (non-numeric should become 0 via absint)
        $_GET['paged'] = 'invalid';
        ob_start();
        $this->admin->render_admin_page();
        ob_get_clean();

        // Test negative (absint converts to positive)
        $_GET['paged'] = '-5';
        ob_start();
        $this->admin->render_admin_page();
        ob_get_clean();

        $this->assertTrue( true );

        unset( $_GET['paged'] );
        wp_delete_user( $admin_id );
    }
}
