<?php
/**
 * Tests for basic framework setup validation
 *
 * @package Crowdsignal_Forms
 */

/**
 * Test class for basic WordPress testing framework setup.
 *
 * @group framework
 * @group unit
 */
class Test_Framework_Setup extends Crowdsignal_Test_Case {

	/**
	 * Test that WordPress test environment is properly set up.
	 */
	public function test_wordpress_test_environment() {
		$this->assertTrue( defined( 'ABSPATH' ), 'WordPress should be loaded in test environment' );
		$this->assertTrue( function_exists( 'wp_head' ), 'WordPress functions should be available' );
	}

	/**
	 * Test that the main plugin file loads without errors.
	 */
	public function test_plugin_file_loads() {
		$plugin_file = dirname( dirname( __DIR__ ) ) . '/polldaddy.php';
		$this->assertTrue( file_exists( $plugin_file ), 'Main plugin file should exist' );
	}

	/**
	 * Test basic WordPress testing capabilities.
	 */
	public function test_wordpress_testing_functions() {
		// Test user creation
		$user_id = $this->create_test_user( array( 'edit_posts' ) );
		$this->assertIsInt( $user_id, 'Should be able to create test users' );
		$this->assertGreaterThan( 0, $user_id, 'User ID should be positive' );

		// Test post creation
		$post_id = $this->factory()->post->create( array(
			'post_title' => 'Test Post',
			'post_content' => 'Test content',
		) );
		$this->assertIsInt( $post_id, 'Should be able to create test posts' );
		$this->assertGreaterThan( 0, $post_id, 'Post ID should be positive' );
	}

	/**
	 * Test configuration file validation.
	 */
	public function test_configuration_files() {
		$base_dir = dirname( dirname( __DIR__ ) );

		// Test PHPUnit configuration
		$phpunit_config = $base_dir . '/phpunit.xml';
		$this->assertTrue( file_exists( $phpunit_config ), 'PHPUnit config should exist' );

		// Test PHPCS configuration
		$phpcs_config = $base_dir . '/phpcs.xml';
		$this->assertTrue( file_exists( $phpcs_config ), 'PHPCS config should exist' );

		// Test Composer configuration
		$composer_config = $base_dir . '/composer.json';
		$this->assertTrue( file_exists( $composer_config ), 'Composer config should exist' );
	}

	/**
	 * Test base test case functionality.
	 */
	public function test_base_test_case_methods() {
		// Test nonce creation and validation
		$action = 'test_action';
		$nonce = wp_create_nonce( $action );
		$this->assertNotEmpty( $nonce, 'Should be able to create nonces' );

		// Test our custom assertion
		$this->assertNonceValid( $nonce, $action );

		// Test escape validation
		$safe_html = '<p>Safe content</p>';
		$this->assertProperlyEscaped( $safe_html );
	}

	/**
	 * Test WordPress hook system.
	 */
	public function test_wordpress_hooks() {
		// Test adding and checking hooks
		$test_function = function() {
			return 'test';
		};

		add_action( 'test_hook', $test_function );
		$this->assertNotFalse( has_action( 'test_hook' ), 'Should be able to register hooks' );

		remove_action( 'test_hook', $test_function );
		$this->assertFalse( has_action( 'test_hook' ), 'Should be able to remove hooks' );
	}

	/**
	 * Test WordPress option system.
	 */
	public function test_wordpress_options() {
		$option_name = 'test_framework_option';
		$option_value = array( 'test' => 'value' );

		// Add option
		add_option( $option_name, $option_value );
		$retrieved = get_option( $option_name );
		$this->assertEquals( $option_value, $retrieved, 'Should be able to store and retrieve options' );

		// Update option
		$new_value = array( 'test' => 'new_value' );
		update_option( $option_name, $new_value );
		$retrieved = get_option( $option_name );
		$this->assertEquals( $new_value, $retrieved, 'Should be able to update options' );

		// Delete option
		delete_option( $option_name );
		$this->assertFalse( get_option( $option_name ), 'Should be able to delete options' );
	}

	/**
	 * Test transient system.
	 */
	public function test_transient_system() {
		$transient_key = 'test_framework_transient';
		$transient_value = array( 'cached' => 'data' );

		// Set transient
		set_transient( $transient_key, $transient_value, HOUR_IN_SECONDS );
		$retrieved = get_transient( $transient_key );
		$this->assertEquals( $transient_value, $retrieved, 'Should be able to set and get transients' );

		// Delete transient
		delete_transient( $transient_key );
		$this->assertFalse( get_transient( $transient_key ), 'Should be able to delete transients' );
	}
}