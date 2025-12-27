<?php
/**
 * Tests for CFM_Dashboard_Widget class
 */

class Test_CFM_Dashboard_Widget extends WP_UnitTestCase {

    /**
     * Test data
     */
    private $editor_id;
    private $subscriber_id;

    /**
     * Set up test fixtures
     */
    public function set_up() {
        parent::set_up();

        // Create test users
        $this->editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        $this->subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
    }

    /**
     * Test that constructor registers wp_dashboard_setup hook
     */
    public function test_constructor_registers_dashboard_setup_hook() {
        // The class is instantiated at file load, so check if hook is registered
        $widget = new CFM_Dashboard_Widget();

        // Check that wp_dashboard_setup action is registered
        $this->assertGreaterThan(
            0,
            has_action( 'wp_dashboard_setup', array( $widget, 'add_dashboard_widget' ) ),
            'Dashboard widget should register wp_dashboard_setup hook'
        );
    }

    /**
     * Test widget is not added for users without edit_posts capability
     */
    public function test_widget_not_added_for_subscribers() {
        global $wp_meta_boxes;

        wp_set_current_user( $this->subscriber_id );

        // Reset meta boxes
        $wp_meta_boxes = array();

        $widget = new CFM_Dashboard_Widget();
        $widget->add_dashboard_widget();

        // Check that widget was not added
        $this->assertFalse(
            isset( $wp_meta_boxes['dashboard']['normal']['core']['cfm_dashboard_widget'] ),
            'Dashboard widget should not be added for subscribers'
        );
    }

    /**
     * Test widget is added for users with edit_posts capability
     */
    public function test_widget_added_for_editors() {
        global $wp_meta_boxes;

        wp_set_current_user( $this->editor_id );

        // Reset meta boxes
        $wp_meta_boxes = array();

        $widget = new CFM_Dashboard_Widget();
        $widget->add_dashboard_widget();

        // Check that widget was added
        $this->assertTrue(
            isset( $wp_meta_boxes['dashboard']['normal']['core']['cfm_dashboard_widget'] ),
            'Dashboard widget should be added for editors'
        );
    }

    /**
     * Test widget ID
     */
    public function test_widget_id() {
        global $wp_meta_boxes;

        wp_set_current_user( $this->editor_id );
        $wp_meta_boxes = array();

        $widget = new CFM_Dashboard_Widget();
        $widget->add_dashboard_widget();

        $this->assertArrayHasKey(
            'cfm_dashboard_widget',
            $wp_meta_boxes['dashboard']['normal']['core'],
            'Widget should have correct ID'
        );
    }

    /**
     * Test widget title contains "Content Freshness"
     */
    public function test_widget_title() {
        global $wp_meta_boxes;

        wp_set_current_user( $this->editor_id );
        $wp_meta_boxes = array();

        $widget = new CFM_Dashboard_Widget();
        $widget->add_dashboard_widget();

        $widget_data = $wp_meta_boxes['dashboard']['normal']['core']['cfm_dashboard_widget'];

        $this->assertStringContainsString(
            'Content Freshness',
            $widget_data['title'],
            'Widget title should contain "Content Freshness"'
        );
    }

    /**
     * Test render_widget outputs wrapper div
     */
    public function test_render_widget_outputs_wrapper_div() {
        wp_set_current_user( $this->editor_id );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            '<div class="cfm-widget">',
            $output,
            'Widget output should contain wrapper div'
        );
    }

    /**
     * Test render_widget outputs health score section
     */
    public function test_render_widget_outputs_health_score() {
        wp_set_current_user( $this->editor_id );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'cfm-health-score-widget',
            $output,
            'Widget output should contain health score section'
        );
    }

    /**
     * Test render_widget outputs health grade
     */
    public function test_render_widget_outputs_health_grade() {
        wp_set_current_user( $this->editor_id );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'cfm-health-grade',
            $output,
            'Widget output should contain health grade element'
        );
    }

    /**
     * Test render_widget outputs stats section
     */
    public function test_render_widget_outputs_stats_section() {
        wp_set_current_user( $this->editor_id );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'cfm-widget-stats',
            $output,
            'Widget output should contain stats section'
        );
    }

    /**
     * Test render_widget outputs stale count
     */
    public function test_render_widget_outputs_stale_count() {
        wp_set_current_user( $this->editor_id );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'cfm-stale-num',
            $output,
            'Widget output should contain stale count element'
        );
    }

    /**
     * Test render_widget outputs fresh count
     */
    public function test_render_widget_outputs_fresh_count() {
        wp_set_current_user( $this->editor_id );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'cfm-fresh-num',
            $output,
            'Widget output should contain fresh count element'
        );
    }

    /**
     * Test render_widget outputs Stale label
     */
    public function test_render_widget_outputs_stale_label() {
        wp_set_current_user( $this->editor_id );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'Stale',
            $output,
            'Widget output should contain "Stale" label'
        );
    }

    /**
     * Test render_widget outputs Fresh label
     */
    public function test_render_widget_outputs_fresh_label() {
        wp_set_current_user( $this->editor_id );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'Fresh',
            $output,
            'Widget output should contain "Fresh" label'
        );
    }

    /**
     * Test render_widget outputs Total label
     */
    public function test_render_widget_outputs_total_label() {
        wp_set_current_user( $this->editor_id );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'Total',
            $output,
            'Widget output should contain "Total" label'
        );
    }

    /**
     * Test render_widget outputs Content Health label
     */
    public function test_render_widget_outputs_content_health_label() {
        wp_set_current_user( $this->editor_id );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'Content Health',
            $output,
            'Widget output should contain "Content Health" label'
        );
    }

    /**
     * Test render_widget outputs footer link
     */
    public function test_render_widget_outputs_footer_link() {
        wp_set_current_user( $this->editor_id );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'View All',
            $output,
            'Widget output should contain "View All" link'
        );
    }

    /**
     * Test render_widget outputs link to content freshness page
     */
    public function test_render_widget_outputs_content_freshness_page_link() {
        wp_set_current_user( $this->editor_id );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'page=content-freshness',
            $output,
            'Widget output should contain link to content freshness page'
        );
    }

    /**
     * Test render_widget shows success message when no stale content
     */
    public function test_render_widget_shows_success_when_no_stale_content() {
        wp_set_current_user( $this->editor_id );

        // Create a fresh post (recently updated)
        $this->factory->post->create( array(
            'post_date' => current_time( 'mysql' ),
            'post_modified' => current_time( 'mysql' ),
            'post_status' => 'publish',
        ) );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'All content is fresh',
            $output,
            'Widget should show success message when no stale content'
        );
    }

    /**
     * Test render_widget shows success icon when no stale content
     */
    public function test_render_widget_shows_success_icon_when_no_stale_content() {
        wp_set_current_user( $this->editor_id );

        // Create a fresh post
        $this->factory->post->create( array(
            'post_date' => current_time( 'mysql' ),
            'post_modified' => current_time( 'mysql' ),
            'post_status' => 'publish',
        ) );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'dashicons-yes-alt',
            $output,
            'Widget should show success icon when no stale content'
        );
    }

    /**
     * Test render_widget shows Needs Attention heading when stale content exists
     */
    public function test_render_widget_shows_needs_attention_when_stale_content() {
        wp_set_current_user( $this->editor_id );

        // Create a stale post (modified 200 days ago)
        $old_date = date( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
        $this->factory->post->create( array(
            'post_date' => $old_date,
            'post_modified' => $old_date,
            'post_status' => 'publish',
        ) );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'Needs Attention',
            $output,
            'Widget should show "Needs Attention" heading when stale content exists'
        );
    }

    /**
     * Test render_widget shows progress bar when stale content exists
     */
    public function test_render_widget_shows_progress_bar_when_stale_content() {
        wp_set_current_user( $this->editor_id );

        // Create a stale post
        $old_date = date( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
        $this->factory->post->create( array(
            'post_date' => $old_date,
            'post_modified' => $old_date,
            'post_status' => 'publish',
        ) );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'cfm-progress-bar',
            $output,
            'Widget should show progress bar when stale content exists'
        );
    }

    /**
     * Test render_widget shows stale post list when stale content exists
     */
    public function test_render_widget_shows_stale_post_list_when_stale_content() {
        wp_set_current_user( $this->editor_id );

        // Create a stale post
        $old_date = date( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
        $this->factory->post->create( array(
            'post_date' => $old_date,
            'post_modified' => $old_date,
            'post_status' => 'publish',
            'post_title' => 'Test Stale Post',
        ) );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'cfm-widget-list',
            $output,
            'Widget should show stale post list when stale content exists'
        );
    }

    /**
     * Test render_widget shows stale post title
     */
    public function test_render_widget_shows_stale_post_title() {
        wp_set_current_user( $this->editor_id );

        // Create a stale post with specific title
        $old_date = date( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
        $this->factory->post->create( array(
            'post_date' => $old_date,
            'post_modified' => $old_date,
            'post_status' => 'publish',
            'post_title' => 'My Stale Post Title',
        ) );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'My Stale Post Title',
            $output,
            'Widget should show stale post title'
        );
    }

    /**
     * Test render_widget outputs age badge for stale posts
     */
    public function test_render_widget_outputs_age_badge() {
        wp_set_current_user( $this->editor_id );

        // Create a stale post
        $old_date = date( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
        $this->factory->post->create( array(
            'post_date' => $old_date,
            'post_modified' => $old_date,
            'post_status' => 'publish',
        ) );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'cfm-widget-age',
            $output,
            'Widget should show age badge for stale posts'
        );
    }

    /**
     * Test render_widget has edit link for stale posts
     */
    public function test_render_widget_has_edit_link_for_stale_posts() {
        wp_set_current_user( $this->editor_id );

        // Create a stale post
        $old_date = date( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
        $post_id = $this->factory->post->create( array(
            'post_date' => $old_date,
            'post_modified' => $old_date,
            'post_status' => 'publish',
        ) );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'post.php?post=' . $post_id,
            $output,
            'Widget should have edit link for stale posts'
        );
    }

    /**
     * Test render_widget has ARIA label for health grade
     */
    public function test_render_widget_has_aria_label_for_health_grade() {
        wp_set_current_user( $this->editor_id );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'aria-label="Content Health Grade:',
            $output,
            'Widget should have ARIA label for health grade'
        );
    }

    /**
     * Test widget limits stale posts to 5
     */
    public function test_widget_limits_stale_posts_to_five() {
        wp_set_current_user( $this->editor_id );

        // Create 10 stale posts
        $old_date = date( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
        for ( $i = 1; $i <= 10; $i++ ) {
            $this->factory->post->create( array(
                'post_date' => $old_date,
                'post_modified' => $old_date,
                'post_status' => 'publish',
                'post_title' => 'Stale Post ' . $i,
            ) );
        }

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        // Count list items in the widget list
        preg_match_all( '/<li>/', $output, $matches );
        $list_item_count = count( $matches[0] );

        $this->assertEquals(
            5,
            $list_item_count,
            'Widget should limit stale posts to 5'
        );
    }

    /**
     * Test render_widget shows fresh percentage
     */
    public function test_render_widget_shows_fresh_percentage() {
        wp_set_current_user( $this->editor_id );

        // Create a stale post
        $old_date = date( 'Y-m-d H:i:s', strtotime( '-200 days' ) );
        $this->factory->post->create( array(
            'post_date' => $old_date,
            'post_modified' => $old_date,
            'post_status' => 'publish',
        ) );

        $widget = new CFM_Dashboard_Widget();

        ob_start();
        $widget->render_widget();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            '% of your content is fresh',
            $output,
            'Widget should show fresh percentage message'
        );
    }
}
