<?php
/**
 * Tests for CSRF vulnerability fix (CVE-2024-43338)
 *
 * @package Crowdsignal_Forms
 */

/**
 * Test class for CSRF security fix.
 *
 * @group security
 * @group csrf
 * @group unit
 */
class Test_CSRF_Security extends Crowdsignal_Test_Case {

	/**
	 * Test that polldaddy_popups_init() function exists.
	 */
	public function test_popups_init_function_exists() {
		$this->assertTrue( function_exists( 'polldaddy_popups_init' ), 'polldaddy_popups_init function should exist' );
	}

	/**
	 * Test that the popups init hook is properly registered.
	 */
	public function test_popups_init_hook_registered() {
		// Check if the hook is registered
		$this->assertNotFalse( has_action( 'admin_init', 'polldaddy_popups_init' ), 'polldaddy_popups_init should be hooked to admin_init' );
	}

	/**
	 * Test that function returns early when polls_media parameter is missing.
	 */
	public function test_returns_early_without_polls_media_parameter() {
		// Clear any existing $_REQUEST data
		$original_request = $_REQUEST;
		$_REQUEST = array();

		// Mock admin context
		set_current_screen( 'dashboard' );
		$user_id = $this->create_test_user( array( 'edit_posts' ) );
		wp_set_current_user( $user_id );

		// Capture filter count before
		$initial_filter_count = has_filter( 'type_url_form_video', 'pd_video_shortcodes_help' );

		// Call the function
		polldaddy_popups_init();

		// Verify filters were not added
		$this->assertFalse( has_filter( 'type_url_form_video', 'pd_video_shortcodes_help' ), 'Video filter should not be added without polls_media parameter' );
		$this->assertFalse( has_filter( 'type_url_form_audio', 'pd_audio_shortcodes_help' ), 'Audio filter should not be added without polls_media parameter' );
		$this->assertFalse( has_filter( 'type_url_form_image', 'pd_image_shortcodes_help' ), 'Image filter should not be added without polls_media parameter' );

		// Restore original request
		$_REQUEST = $original_request;
	}

	/**
	 * Test that function returns early when not in admin context.
	 */
	public function test_returns_early_when_not_admin() {
		// Set up non-admin context
		$original_request = $_REQUEST;
		$_REQUEST = array( 'polls_media' => '1' );

		// Make sure we're not in admin
		$this->assertFalse( is_admin(), 'Should not be in admin context for this test' );

		// Capture filter count before
		$initial_filter_count = has_filter( 'type_url_form_video', 'pd_video_shortcodes_help' );

		// Call the function
		polldaddy_popups_init();

		// Verify filters were not added
		$this->assertFalse( has_filter( 'type_url_form_video', 'pd_video_shortcodes_help' ), 'Video filter should not be added outside admin context' );

		// Restore original request
		$_REQUEST = $original_request;
	}

	/**
	 * Test that function returns early when user lacks edit_posts capability.
	 */
	public function test_returns_early_without_edit_posts_capability() {
		// Set up request with polls_media parameter
		$original_request = $_REQUEST;
		$_REQUEST = array( 'polls_media' => '1' );

		// Mock admin context
		set_current_screen( 'dashboard' );

		// Create user without edit_posts capability
		$user_id = $this->create_test_user( array( 'read' ) ); // Only basic read capability
		wp_set_current_user( $user_id );

		// Verify user cannot edit posts
		$this->assertFalse( current_user_can( 'edit_posts' ), 'Test user should not have edit_posts capability' );

		// Call the function
		polldaddy_popups_init();

		// Verify filters were not added
		$this->assertFalse( has_filter( 'type_url_form_video', 'pd_video_shortcodes_help' ), 'Video filter should not be added without edit_posts capability' );

		// Restore original request
		$_REQUEST = $original_request;
	}

	/**
	 * Test that function returns early with invalid nonce.
	 */
	public function test_returns_early_with_invalid_nonce() {
		// Set up request with polls_media parameter but invalid nonce
		$original_request = $_REQUEST;
		$user_id = $this->create_test_user( array( 'edit_posts' ) );
		$_REQUEST = array(
			'polls_media' => '1',
			'_wpnonce' => 'invalid_nonce_value'
		);

		// Mock admin context
		set_current_screen( 'dashboard' );
		wp_set_current_user( $user_id );

		// Call the function
		polldaddy_popups_init();

		// Verify filters were not added
		$this->assertFalse( has_filter( 'type_url_form_video', 'pd_video_shortcodes_help' ), 'Video filter should not be added with invalid nonce' );
		$this->assertFalse( has_filter( 'type_url_form_audio', 'pd_audio_shortcodes_help' ), 'Audio filter should not be added with invalid nonce' );
		$this->assertFalse( has_filter( 'type_url_form_image', 'pd_image_shortcodes_help' ), 'Image filter should not be added with invalid nonce' );

		// Restore original request
		$_REQUEST = $original_request;
	}

	/**
	 * Test that function returns early when nonce is missing.
	 */
	public function test_returns_early_with_missing_nonce() {
		// Set up request with polls_media parameter but no nonce
		$original_request = $_REQUEST;
		$user_id = $this->create_test_user( array( 'edit_posts' ) );
		$_REQUEST = array(
			'polls_media' => '1'
			// No _wpnonce parameter
		);

		// Mock admin context
		set_current_screen( 'dashboard' );
		wp_set_current_user( $user_id );

		// Call the function
		polldaddy_popups_init();

		// Verify filters were not added
		$this->assertFalse( has_filter( 'type_url_form_video', 'pd_video_shortcodes_help' ), 'Video filter should not be added without nonce' );

		// Restore original request
		$_REQUEST = $original_request;
	}

	/**
	 * Test that filters are added when all security checks pass.
	 */
	public function test_filters_added_with_valid_security_context() {
		// Set up valid security context
		$original_request = $_REQUEST;
		$user_id = $this->create_test_user( array( 'edit_posts' ) );
		wp_set_current_user( $user_id );

		// Create valid nonce
		$nonce_action = 'polls_media_' . $user_id;
		$valid_nonce = wp_create_nonce( $nonce_action );

		$_REQUEST = array(
			'polls_media' => '1',
			'_wpnonce' => $valid_nonce
		);

		// Mock admin context
		set_current_screen( 'dashboard' );

		// Remove any existing filters first
		remove_filter( 'type_url_form_video', 'pd_video_shortcodes_help' );
		remove_filter( 'type_url_form_audio', 'pd_audio_shortcodes_help' );
		remove_filter( 'type_url_form_image', 'pd_image_shortcodes_help' );

		// Call the function
		polldaddy_popups_init();

		// Verify filters were added
		$this->assertNotFalse( has_filter( 'type_url_form_video', 'pd_video_shortcodes_help' ), 'Video filter should be added with valid security context' );
		$this->assertNotFalse( has_filter( 'type_url_form_audio', 'pd_audio_shortcodes_help' ), 'Audio filter should be added with valid security context' );
		$this->assertNotFalse( has_filter( 'type_url_form_image', 'pd_image_shortcodes_help' ), 'Image filter should be added with valid security context' );

		// Clean up filters
		remove_filter( 'type_url_form_video', 'pd_video_shortcodes_help' );
		remove_filter( 'type_url_form_audio', 'pd_audio_shortcodes_help' );
		remove_filter( 'type_url_form_image', 'pd_image_shortcodes_help' );

		// Restore original request
		$_REQUEST = $original_request;
	}



	/**
	 * Test the complete CSRF attack prevention flow.
	 */
	public function test_csrf_attack_prevention_flow() {
		$original_request = $_REQUEST;

		// Simulate a CSRF attack scenario
		$attacker_user_id = $this->create_test_user( array( 'edit_posts' ) );
		$victim_user_id = $this->create_test_user( array( 'edit_posts' ) );

		// Attacker creates a malicious request with their own context
		wp_set_current_user( $attacker_user_id );
		$attacker_nonce = wp_create_nonce( 'polls_media_' . $attacker_user_id );

		// Victim becomes the current user (simulating CSRF)
		wp_set_current_user( $victim_user_id );

		// Malicious request uses attacker's nonce
		$_REQUEST = array(
			'polls_media' => '1',
			'_wpnonce' => $attacker_nonce
		);

		// Mock admin context
		set_current_screen( 'dashboard' );

		// The function should reject this because the nonce doesn't match the current user
		polldaddy_popups_init();

		// Verify that the attack was prevented - filters should not be added
		$this->assertFalse( has_filter( 'type_url_form_video', 'pd_video_shortcodes_help' ), 'CSRF attack should be prevented - video filter not added' );
		$this->assertFalse( has_filter( 'type_url_form_audio', 'pd_audio_shortcodes_help' ), 'CSRF attack should be prevented - audio filter not added' );
		$this->assertFalse( has_filter( 'type_url_form_image', 'pd_image_shortcodes_help' ), 'CSRF attack should be prevented - image filter not added' );

		// Restore original request
		$_REQUEST = $original_request;
	}

	/**
	 * Test that get_polls_media_nonce() returns different values for different users.
	 */
	public function test_get_polls_media_nonce_user_specific() {
		// Get nonce for current user
		$user1_id = get_current_user_id();
		$user1_nonce = get_polls_media_nonce();

		// Create a new test user
		$user2_id = wp_insert_user( array(
			'user_login' => 'testuser_' . time(),
			'user_email' => 'test' . time() . '@example.com',
			'user_pass'  => 'testpass123'
		) );

		// Switch to the new user
		wp_set_current_user( $user2_id );
		$user2_nonce = get_polls_media_nonce();

		// Switch back to original user
		wp_set_current_user( $user1_id );

		// Verify nonces are different
		$this->assertNotEquals( $user1_nonce, $user2_nonce, 'Different users should have different nonce actions' );
		$this->assertEquals( 'polls_media_' . $user1_id, $user1_nonce, 'User 1 nonce should match expected format' );
		$this->assertEquals( 'polls_media_' . $user2_id, $user2_nonce, 'User 2 nonce should match expected format' );

		// Clean up test user
		wp_delete_user( $user2_id );
	}

	/**
	 * Test that get_polls_media_nonce() returns consistent values for the same user.
	 */
	public function test_get_polls_media_nonce_consistency() {
		$nonce1 = get_polls_media_nonce();
		$nonce2 = get_polls_media_nonce();

		$this->assertEquals( $nonce1, $nonce2, 'get_polls_media_nonce should return consistent values for same user' );
	}
}