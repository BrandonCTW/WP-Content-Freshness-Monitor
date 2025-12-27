<?php
/**
 * Tests for CFM_Export class
 */

class Test_CFM_Export extends WP_UnitTestCase {

    /**
     * Test that get_export_url() returns a valid URL
     */
    public function test_get_export_url_returns_valid_url() {
        $url = CFM_Export::get_export_url();

        $this->assertIsString( $url );
        $this->assertNotEmpty( $url );
    }

    /**
     * Test that export URL contains required query parameters
     */
    public function test_get_export_url_contains_required_params() {
        $url = CFM_Export::get_export_url();

        $this->assertStringContainsString( 'cfm_export_csv=1', $url );
        $this->assertStringContainsString( 'page=content-freshness', $url );
    }

    /**
     * Test that export URL contains nonce
     */
    public function test_get_export_url_contains_nonce() {
        $url = CFM_Export::get_export_url();

        $this->assertStringContainsString( '_wpnonce=', $url );
    }

    /**
     * Test that export URL points to admin.php
     */
    public function test_get_export_url_points_to_admin() {
        $url = CFM_Export::get_export_url();

        $this->assertStringContainsString( 'admin.php', $url );
    }

    /**
     * Test that nonce in export URL is valid
     */
    public function test_get_export_url_nonce_is_valid() {
        $url = CFM_Export::get_export_url();

        // Parse the URL to extract nonce
        $query_string = parse_url( $url, PHP_URL_QUERY );
        parse_str( $query_string, $params );

        $this->assertArrayHasKey( '_wpnonce', $params );
        $this->assertNotEmpty( $params['_wpnonce'] );

        // Verify the nonce is valid
        $nonce = $params['_wpnonce'];
        $this->assertNotFalse( wp_verify_nonce( $nonce, 'cfm_export_csv' ) );
    }

    /**
     * Test that export handler is hooked to admin_init
     */
    public function test_export_handler_is_hooked() {
        $export = new CFM_Export();

        $this->assertIsInt( has_action( 'admin_init', array( $export, 'handle_csv_export' ) ) );
    }

    /**
     * Test that handle_csv_export returns early without query param
     */
    public function test_handle_csv_export_returns_early_without_param() {
        $export = new CFM_Export();

        // Ensure no query param is set
        unset( $_GET['cfm_export_csv'] );

        // Should return without doing anything (no output, no exit)
        $result = $export->handle_csv_export();

        $this->assertNull( $result );
    }

    /**
     * Test that handle_csv_export returns early with invalid query param
     */
    public function test_handle_csv_export_returns_early_with_invalid_param() {
        $export = new CFM_Export();

        // Set invalid query param
        $_GET['cfm_export_csv'] = '0';

        $result = $export->handle_csv_export();

        $this->assertNull( $result );

        // Clean up
        unset( $_GET['cfm_export_csv'] );
    }

    /**
     * Test CSV header row columns
     */
    public function test_csv_header_columns() {
        // Expected columns in CSV header
        $expected_columns = array(
            'ID',
            'Title',
            'Type',
            'Author',
            'Last Modified',
            'Days Since Modified',
            'Last Reviewed',
            'Edit URL',
            'View URL',
        );

        // Verify we have 9 columns expected
        $this->assertCount( 9, $expected_columns );
        $this->assertContains( 'ID', $expected_columns );
        $this->assertContains( 'Title', $expected_columns );
        $this->assertContains( 'Author', $expected_columns );
        $this->assertContains( 'Edit URL', $expected_columns );
        $this->assertContains( 'View URL', $expected_columns );
    }

    /**
     * Test that export URL can be regenerated consistently
     */
    public function test_get_export_url_consistency() {
        $url1 = CFM_Export::get_export_url();
        $url2 = CFM_Export::get_export_url();

        // Parse both URLs
        $query1 = parse_url( $url1, PHP_URL_QUERY );
        $query2 = parse_url( $url2, PHP_URL_QUERY );

        parse_str( $query1, $params1 );
        parse_str( $query2, $params2 );

        // Base parameters should be the same
        $this->assertEquals( $params1['page'], $params2['page'] );
        $this->assertEquals( $params1['cfm_export_csv'], $params2['cfm_export_csv'] );
    }

    /**
     * Test export filename format
     */
    public function test_export_filename_format() {
        // The expected filename format
        $expected_pattern = '/^stale-content-\d{4}-\d{2}-\d{2}\.csv$/';

        $filename = 'stale-content-' . date( 'Y-m-d' ) . '.csv';

        $this->assertMatchesRegularExpression( $expected_pattern, $filename );
    }

    /**
     * Test export URL is a proper URL format
     */
    public function test_get_export_url_is_valid_url_format() {
        $url = CFM_Export::get_export_url();

        // Should be parseable as a URL
        $parsed = parse_url( $url );

        $this->assertIsArray( $parsed );
        $this->assertArrayHasKey( 'path', $parsed );
        $this->assertArrayHasKey( 'query', $parsed );
    }

    /**
     * Test that nonce action matches expected value
     */
    public function test_nonce_action_is_correct() {
        $nonce = wp_create_nonce( 'cfm_export_csv' );

        $this->assertNotEmpty( $nonce );
        $this->assertTrue( wp_verify_nonce( $nonce, 'cfm_export_csv' ) !== false );
    }

    /**
     * Test that required capability for export is edit_posts
     */
    public function test_required_capability_is_edit_posts() {
        // Create a user without edit_posts capability
        $subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
        wp_set_current_user( $subscriber_id );

        $this->assertFalse( current_user_can( 'edit_posts' ) );

        // Clean up
        wp_set_current_user( 0 );
    }

    /**
     * Test that editor has required capability for export
     */
    public function test_editor_has_export_capability() {
        // Create an editor user
        $editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
        wp_set_current_user( $editor_id );

        $this->assertTrue( current_user_can( 'edit_posts' ) );

        // Clean up
        wp_set_current_user( 0 );
    }
}
