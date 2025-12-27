<?php
/**
 * Tests for CFM_Shortcodes class
 *
 * @package Content_Freshness_Monitor
 */

class Test_CFM_Shortcodes extends WP_UnitTestCase {

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		// Create a user with edit_posts capability
		$this->editor = $this->factory->user->create( array( 'role' => 'editor' ) );

		// Create a user without edit_posts capability
		$this->subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Tear down test fixtures
	 */
	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Test shortcodes are registered
	 */
	public function test_shortcodes_registered() {
		$this->assertTrue( shortcode_exists( 'content_health_score' ) );
		$this->assertTrue( shortcode_exists( 'content_freshness_stats' ) );
		$this->assertTrue( shortcode_exists( 'stale_content_count' ) );
	}

	/**
	 * Test health score shortcode returns empty for unauthorized users
	 */
	public function test_health_score_unauthorized() {
		wp_set_current_user( $this->subscriber );

		$output = do_shortcode( '[content_health_score]' );

		$this->assertEmpty( $output );
	}

	/**
	 * Test health score shortcode returns content for authorized users
	 */
	public function test_health_score_authorized() {
		wp_set_current_user( $this->editor );

		$output = do_shortcode( '[content_health_score]' );

		$this->assertNotEmpty( $output );
		$this->assertStringContainsString( 'cfm-health-score-widget', $output );
		$this->assertStringContainsString( 'cfm-grade-badge', $output );
		$this->assertStringContainsString( 'cfm-grade-letter', $output );
	}

	/**
	 * Test health score shortcode size attribute
	 */
	public function test_health_score_size_attribute() {
		wp_set_current_user( $this->editor );

		$small = do_shortcode( '[content_health_score size="small"]' );
		$large = do_shortcode( '[content_health_score size="large"]' );

		$this->assertStringContainsString( 'cfm-size-small', $small );
		$this->assertStringContainsString( 'cfm-size-large', $large );
	}

	/**
	 * Test health score shortcode show_score attribute
	 */
	public function test_health_score_show_score_attribute() {
		wp_set_current_user( $this->editor );

		$with_score = do_shortcode( '[content_health_score show_score="yes"]' );
		$without_score = do_shortcode( '[content_health_score show_score="no"]' );

		$this->assertStringContainsString( 'cfm-grade-score', $with_score );
		$this->assertStringNotContainsString( 'cfm-grade-score', $without_score );
	}

	/**
	 * Test health score shortcode show_label attribute
	 */
	public function test_health_score_show_label_attribute() {
		wp_set_current_user( $this->editor );

		$with_label = do_shortcode( '[content_health_score show_label="yes"]' );
		$without_label = do_shortcode( '[content_health_score show_label="no"]' );

		$this->assertStringContainsString( 'cfm-grade-label', $with_label );
		$this->assertStringNotContainsString( 'cfm-grade-label', $without_label );
	}

	/**
	 * Test stats shortcode returns empty for unauthorized users
	 */
	public function test_stats_unauthorized() {
		wp_set_current_user( $this->subscriber );

		$output = do_shortcode( '[content_freshness_stats]' );

		$this->assertEmpty( $output );
	}

	/**
	 * Test stats shortcode returns content for authorized users
	 */
	public function test_stats_authorized() {
		wp_set_current_user( $this->editor );

		$output = do_shortcode( '[content_freshness_stats]' );

		$this->assertNotEmpty( $output );
		$this->assertStringContainsString( 'cfm-stats-widget', $output );
		$this->assertStringContainsString( 'cfm-stat-fresh', $output );
		$this->assertStringContainsString( 'cfm-stat-stale', $output );
	}

	/**
	 * Test stats shortcode layout attribute
	 */
	public function test_stats_layout_attribute() {
		wp_set_current_user( $this->editor );

		$horizontal = do_shortcode( '[content_freshness_stats layout="horizontal"]' );
		$vertical = do_shortcode( '[content_freshness_stats layout="vertical"]' );
		$compact = do_shortcode( '[content_freshness_stats layout="compact"]' );

		$this->assertStringContainsString( 'cfm-layout-horizontal', $horizontal );
		$this->assertStringContainsString( 'cfm-layout-vertical', $vertical );
		$this->assertStringContainsString( 'cfm-layout-compact', $compact );
	}

	/**
	 * Test stats shortcode show_total attribute
	 */
	public function test_stats_show_total_attribute() {
		wp_set_current_user( $this->editor );

		$with_total = do_shortcode( '[content_freshness_stats show_total="yes"]' );
		$without_total = do_shortcode( '[content_freshness_stats show_total="no"]' );

		$this->assertStringContainsString( 'cfm-stat-total', $with_total );
		$this->assertStringNotContainsString( 'cfm-stat-total', $without_total );
	}

	/**
	 * Test stats shortcode show_threshold attribute
	 */
	public function test_stats_show_threshold_attribute() {
		wp_set_current_user( $this->editor );

		$with_threshold = do_shortcode( '[content_freshness_stats show_threshold="yes"]' );
		$without_threshold = do_shortcode( '[content_freshness_stats show_threshold="no"]' );

		$this->assertStringContainsString( 'cfm-stat-threshold', $with_threshold );
		$this->assertStringNotContainsString( 'cfm-stat-threshold', $without_threshold );
	}

	/**
	 * Test stale count shortcode returns empty for unauthorized users
	 */
	public function test_stale_count_unauthorized() {
		wp_set_current_user( $this->subscriber );

		$output = do_shortcode( '[stale_content_count]' );

		$this->assertEmpty( $output );
	}

	/**
	 * Test stale count shortcode returns content for authorized users
	 */
	public function test_stale_count_authorized() {
		wp_set_current_user( $this->editor );

		$output = do_shortcode( '[stale_content_count]' );

		// Should return a number (possibly 0)
		$this->assertNotEmpty( $output );
		$this->assertIsNumeric( $output );
	}

	/**
	 * Test stale count shortcode format attribute - number format
	 */
	public function test_stale_count_format_number() {
		wp_set_current_user( $this->editor );

		$output = do_shortcode( '[stale_content_count format="number"]' );

		// Should be just a number
		$this->assertIsNumeric( $output );
	}

	/**
	 * Test stale count shortcode format attribute - text format
	 */
	public function test_stale_count_format_text() {
		wp_set_current_user( $this->editor );

		$output = do_shortcode( '[stale_content_count format="text"]' );

		// Should contain "stale post" or "stale posts"
		$this->assertMatchesRegularExpression( '/stale posts?/', $output );
	}

	/**
	 * Test output escaping in health score shortcode
	 */
	public function test_health_score_output_escaped() {
		wp_set_current_user( $this->editor );

		$output = do_shortcode( '[content_health_score]' );

		// Should not contain unescaped HTML special characters
		$this->assertStringNotContainsString( '<script', $output );
		// Should have proper HTML structure
		$this->assertStringContainsString( '<div class="cfm-health-score-widget', $output );
	}

	/**
	 * Test size attribute sanitization
	 */
	public function test_size_attribute_sanitized() {
		wp_set_current_user( $this->editor );

		// Try to inject malicious content via size attribute
		$output = do_shortcode( '[content_health_score size="<script>alert(1)</script>"]' );

		// Should not contain script tag
		$this->assertStringNotContainsString( '<script>', $output );
		// Size should be sanitized
		$this->assertStringContainsString( 'cfm-size-scriptalert1script', $output );
	}

	/**
	 * Test layout attribute sanitization in stats shortcode
	 */
	public function test_layout_attribute_sanitized() {
		wp_set_current_user( $this->editor );

		// Try to inject malicious content via layout attribute
		$output = do_shortcode( '[content_freshness_stats layout="<script>"]' );

		// Should not contain script tag
		$this->assertStringNotContainsString( '<script>', $output );
	}

	/**
	 * Test anonymous user cannot see shortcode output
	 */
	public function test_anonymous_user_no_access() {
		wp_set_current_user( 0 );

		$health = do_shortcode( '[content_health_score]' );
		$stats = do_shortcode( '[content_freshness_stats]' );
		$count = do_shortcode( '[stale_content_count]' );

		$this->assertEmpty( $health );
		$this->assertEmpty( $stats );
		$this->assertEmpty( $count );
	}
}
