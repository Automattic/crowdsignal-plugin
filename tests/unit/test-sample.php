<?php
/**
 * Sample test file demonstrating WordPress testing guidelines
 *
 * @package Crowdsignal_Forms
 */

/**
 * Sample test class.
 *
 * @group sample
 */
class Test_Sample extends Crowdsignal_Test_Case {

	/**
	 * Test that the plugin is loaded.
	 */
	public function test_plugin_loaded() {
		$this->assertTrue( class_exists( 'WPORG_Polldaddy' ), 'Main plugin class should exist' );
	}

	/**
	 * Test user capabilities.
	 *
	 * @dataProvider user_capability_provider
	 */
	public function test_user_capabilities( $capability, $expected ) {
		// Create test user.
		$user_id = $this->create_test_user( array( 'edit_posts' ) );
		wp_set_current_user( $user_id );

		// Check capability.
		$this->assertSame( $expected, current_user_can( $capability ) );
	}

	/**
	 * Data provider for user capability tests.
	 *
	 * @return array Test data.
	 */
	public function user_capability_provider() {
		return array(
			'can edit posts'    => array( 'edit_posts', true ),
			'cannot edit theme' => array( 'edit_themes', false ),
		);
	}

	/**
	 * Test nonce verification.
	 */
	public function test_nonce_verification() {
		$action = 'polldaddy_test_action';
		$nonce  = wp_create_nonce( $action );

		$this->assertNonceValid( $nonce, $action );
	}

	/**
	 * Test database operations use prepared statements.
	 */
	public function test_database_preparation() {
		global $wpdb;

		// Example of a properly prepared query.
		$query = $wpdb->prepare(
			"SELECT * FROM {$wpdb->posts} WHERE ID = %d",
			123
		);

		$this->assertUsesPreparation( $query );
	}

	/**
	 * Test HTML output is properly escaped.
	 */
	public function test_output_escaping() {
		// Simulate some output.
		$title  = '<script>alert("XSS")</script>Test Title';
		$output = esc_html( $title );

		$this->assertProperlyEscaped( $output );
		$this->assertStringNotContainsString( '<script>', $output );
	}

	/**
	 * Test AJAX request handling.
	 */
	public function test_ajax_request() {
		// Create admin user.
		$user_id = $this->create_test_user( array( 'manage_options' ) );
		wp_set_current_user( $user_id );

		// Mock AJAX request.
		$this->mock_ajax_request(
			'get_poll',
			array( 'poll_id' => 123 ),
			true // Include nonce.
		);

		// Verify request is set up correctly.
		$this->assertEquals( 'get_poll', $_REQUEST['action'] );
		$this->assertEquals( 123, $_REQUEST['poll_id'] );
		$this->assertArrayHasKey( '_wpnonce', $_REQUEST );
	}

	/**
	 * Test creating a poll.
	 */
	public function test_create_poll() {
		$poll = $this->create_test_poll(
			array(
				'title'    => 'My Test Poll',
				'question' => 'What is your favorite color?',
				'answers'  => array( 'Red', 'Blue', 'Green' ),
			)
		);

		$this->assertIsArray( $poll );
		$this->assertEquals( 'My Test Poll', $poll['title'] );
		$this->assertCount( 3, $poll['answers'] );
	}

	/**
	 * Test that basic WordPress functionality works.
	 */
	public function test_wordpress_functions() {
		// Check if WordPress core functions work.
		$this->assertTrue( function_exists( 'wp_head' ), 'WordPress core functions should be available' );
		$this->assertTrue( function_exists( 'add_action' ), 'WordPress hook functions should be available' );
	}

	/**
	 * Test transient operations.
	 */
	public function test_transient_operations() {
		$key   = 'polldaddy_test_transient';
		$value = array( 'test' => 'data' );

		// Set transient.
		set_transient( $key, $value, HOUR_IN_SECONDS );

		// Get transient.
		$retrieved = get_transient( $key );

		$this->assertEquals( $value, $retrieved );

		// Delete transient.
		delete_transient( $key );

		$this->assertFalse( get_transient( $key ) );
	}

	/**
	 * Test option operations.
	 */
	public function test_option_operations() {
		$option_name = 'polldaddy_test_option';
		$value       = array( 'setting' => 'value' );

		// Add option.
		add_option( $option_name, $value );

		// Get option.
		$retrieved = get_option( $option_name );
		$this->assertEquals( $value, $retrieved );

		// Update option.
		$new_value = array( 'setting' => 'new_value' );
		update_option( $option_name, $new_value );

		$retrieved = get_option( $option_name );
		$this->assertEquals( $new_value, $retrieved );

		// Delete option.
		delete_option( $option_name );
		$this->assertFalse( get_option( $option_name ) );
	}
}