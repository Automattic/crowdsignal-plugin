<?php
/**
 * Integration test for plugin loading and basic functionality
 *
 * @package Crowdsignal_Forms
 */

/**
 * Plugin integration test class.
 *
 * @group integration
 */
class Test_Plugin_Integration extends Crowdsignal_Test_Case {

	/**
	 * Test that WordPress and the plugin are properly loaded.
	 */
	public function test_wordpress_and_plugin_loaded() {
		// Verify WordPress is loaded.
		$this->assertTrue( function_exists( 'is_admin' ), 'WordPress should be loaded' );
		$this->assertTrue( function_exists( 'add_action' ), 'WordPress hooks should be available' );

		// Verify the main plugin class exists.
		$this->assertTrue( class_exists( 'WPORG_Polldaddy' ), 'Main plugin class should be loaded' );
	}

	/**
	 * Test that plugin hooks are properly registered.
	 */
	public function test_plugin_hooks_registered() {
		// Check that common WordPress hooks have our plugin callbacks.
		$this->assertGreaterThan( 0, has_action( 'init' ), 'Plugin should register init hooks' );
		$this->assertGreaterThan( 0, has_action( 'admin_menu' ), 'Plugin should register admin menu hooks' );
	}

	/**
	 * Test basic database functionality.
	 */
	public function test_database_functionality() {
		global $wpdb;

		// Test basic database connectivity.
		$result = $wpdb->get_var( "SELECT 1" );
		$this->assertEquals( 1, $result, 'Database should be accessible' );

		// Test WordPress tables exist.
		$tables = $wpdb->get_results( "SHOW TABLES" );
		$this->assertNotEmpty( $tables, 'WordPress tables should exist' );
	}

	/**
	 * Test plugin options and settings.
	 */
	public function test_plugin_options() {
		// Test option creation.
		$option_name = 'crowdsignal_integration_test';
		$option_value = array( 'test' => 'value' );

		add_option( $option_name, $option_value );
		$retrieved = get_option( $option_name );

		$this->assertEquals( $option_value, $retrieved, 'Options should be stored and retrieved correctly' );

		// Clean up.
		delete_option( $option_name );
	}

	/**
	 * Test that plugin constants are defined.
	 */
	public function test_plugin_constants() {
		// Check for common WordPress constants.
		$this->assertTrue( defined( 'ABSPATH' ), 'ABSPATH should be defined' );
		$this->assertTrue( defined( 'WP_CONTENT_DIR' ), 'WP_CONTENT_DIR should be defined' );
	}
}