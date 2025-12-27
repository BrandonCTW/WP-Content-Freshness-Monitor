<?php
/**
 * Tests for CFM_Settings class
 *
 * @package Content_Freshness_Monitor
 */

class Test_CFM_Settings extends WP_UnitTestCase {

    /**
     * Test default settings values
     */
    public function test_default_settings() {
        // Remove any existing settings
        delete_option( 'cfm_settings' );

        $settings = Content_Freshness_Monitor::get_settings();

        $this->assertEquals( 180, $settings['staleness_days'] );
        $this->assertContains( 'post', $settings['post_types'] );
        $this->assertContains( 'page', $settings['post_types'] );
        $this->assertEquals( '', $settings['exclude_ids'] );
    }

    /**
     * Test settings are properly retrieved after save
     */
    public function test_settings_retrieval() {
        $test_settings = array(
            'staleness_days' => 90,
            'post_types'     => array( 'post' ),
            'exclude_ids'    => '1,2,3',
        );

        update_option( 'cfm_settings', $test_settings );

        $settings = Content_Freshness_Monitor::get_settings();

        $this->assertEquals( 90, $settings['staleness_days'] );
        $this->assertEquals( array( 'post' ), $settings['post_types'] );
        $this->assertEquals( '1,2,3', $settings['exclude_ids'] );

        // Cleanup
        delete_option( 'cfm_settings' );
    }

    /**
     * Test settings option name
     */
    public function test_settings_option_name() {
        $this->assertEquals( 'cfm_settings', Content_Freshness_Monitor::SETTINGS_OPTION );
    }
}
