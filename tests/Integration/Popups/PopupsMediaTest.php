<?php
/**
 * Tests for popups media functionality and CSRF protection
 *
 * @package Automattic\Crowdsignal\Tests\Integration\Popups
 */

declare(strict_types=1);

namespace Automattic\Crowdsignal\Tests\Integration\Popups;

use Automattic\Crowdsignal\Tests\Integration\TestCase;

/**
 * Test class for popups media functionality and CSRF protection.
 */
class PopupsMediaTest extends TestCase {

	/**
	 * Test that get_polls_media_nonce() returns different values for different users.
	 */
	public function test_get_polls_media_nonce_user_specific(): void {
		// Get nonce for current user
		$user1_id = get_current_user_id();
		$user1_nonce = get_polls_media_nonce();

		// Create a new test user
		$user2_id = wp_insert_user( array(
			'user_login' => 'testuser_' . time(),
			'user_email' => 'test' . time() . '@example.com',
			'user_pass'  => 'testpass123',
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
	public function test_get_polls_media_nonce_consistency(): void {
		$nonce1 = get_polls_media_nonce();
		$nonce2 = get_polls_media_nonce();

		$this->assertEquals( $nonce1, $nonce2, 'get_polls_media_nonce should return consistent values for same user' );
	}

	/**
	 * Test that polldaddy_popups_init() function exists.
	 */
	public function test_popups_init_function_exists(): void {
		$this->assertTrue( function_exists( 'polldaddy_popups_init' ), 'polldaddy_popups_init function should exist' );
	}

	/**
	 * Test that the popups init hook is properly registered.
	 */
	public function test_popups_init_hook_registered(): void {
		// Check if the hook is registered
		$this->assertNotFalse( has_action( 'admin_init', 'polldaddy_popups_init' ), 'polldaddy_popups_init should be hooked to admin_init' );
	}

	/**
	 * Test that filters are not added when polls_media parameter is missing.
	 */
	public function test_no_filters_without_polls_media_parameter(): void {
		$original_request = $_REQUEST;

		// Clear the request
		$_REQUEST = array();

		// Mock admin context
		set_current_screen( 'dashboard' );

		// Call the function
		polldaddy_popups_init();

		// Verify no filters were added
		$this->assertFalse( has_filter( 'type_url_form_video', 'pd_video_shortcodes_help' ), 'Video filter should not be added without polls_media parameter' );
		$this->assertFalse( has_filter( 'type_url_form_audio', 'pd_audio_shortcodes_help' ), 'Audio filter should not be added without polls_media parameter' );
		$this->assertFalse( has_filter( 'type_url_form_image', 'pd_image_shortcodes_help' ), 'Image filter should not be added without polls_media parameter' );

		// Restore original request
		$_REQUEST = $original_request;
	}

	/**
	 * Test that filters are not added without proper capability.
	 */
	public function test_no_filters_without_capability(): void {
		$original_request = $_REQUEST;

		// Create a test user without edit_posts capability
		$user_id = wp_insert_user( array(
			'user_login' => 'testsubscriber_' . time(),
			'user_email' => 'testsub' . time() . '@example.com',
			'user_pass'  => 'testpass123',
			'role'       => 'subscriber',
		) );

		// Switch to the test user
		wp_set_current_user( $user_id );

		// Set up request with polls_media and valid nonce
		$nonce_action = get_polls_media_nonce();
		$_REQUEST = array(
			'polls_media' => '1',
			'_wpnonce'    => wp_create_nonce( $nonce_action ),
		);

		// Mock admin context
		set_current_screen( 'dashboard' );

		// Call the function
		polldaddy_popups_init();

		// Verify filters were not added due to insufficient capability
		$this->assertFalse( has_filter( 'type_url_form_video', 'pd_video_shortcodes_help' ), 'Video filter should not be added without edit_posts capability' );
		$this->assertFalse( has_filter( 'type_url_form_audio', 'pd_audio_shortcodes_help' ), 'Audio filter should not be added without edit_posts capability' );
		$this->assertFalse( has_filter( 'type_url_form_image', 'pd_image_shortcodes_help' ), 'Image filter should not be added without edit_posts capability' );

		// Clean up
		wp_delete_user( $user_id );
		$_REQUEST = $original_request;
	}

	/**
	 * Test that filters are not added with invalid nonce.
	 */
	public function test_no_filters_with_invalid_nonce(): void {
		$original_request = $_REQUEST;

		// Create an admin user
		$user_id = wp_insert_user( array(
			'user_login' => 'testadmin_' . time(),
			'user_email' => 'testadmin' . time() . '@example.com',
			'user_pass'  => 'testpass123',
			'role'       => 'administrator',
		) );

		// Switch to the admin user
		wp_set_current_user( $user_id );

		// Set up request with polls_media but invalid nonce
		$_REQUEST = array(
			'polls_media' => '1',
			'_wpnonce'    => 'invalid_nonce_value',
		);

		// Mock admin context
		set_current_screen( 'dashboard' );

		// Call the function
		polldaddy_popups_init();

		// Verify filters were not added due to invalid nonce
		$this->assertFalse( has_filter( 'type_url_form_video', 'pd_video_shortcodes_help' ), 'Video filter should not be added with invalid nonce' );
		$this->assertFalse( has_filter( 'type_url_form_audio', 'pd_audio_shortcodes_help' ), 'Audio filter should not be added with invalid nonce' );
		$this->assertFalse( has_filter( 'type_url_form_image', 'pd_image_shortcodes_help' ), 'Image filter should not be added with invalid nonce' );

		// Clean up
		wp_delete_user( $user_id );
		$_REQUEST = $original_request;
	}

	/**
	 * Test that filters ARE added with valid nonce and proper permissions.
	 */
	public function test_filters_added_with_valid_nonce(): void {
		$original_request = $_REQUEST;

		// Create an admin user
		$user_id = wp_insert_user( array(
			'user_login' => 'testadmin2_' . time(),
			'user_email' => 'testadmin2' . time() . '@example.com',
			'user_pass'  => 'testpass123',
			'role'       => 'administrator',
		) );

		// Switch to the admin user
		wp_set_current_user( $user_id );

		// Set up request with polls_media and valid nonce
		$nonce_action = get_polls_media_nonce();
		$valid_nonce = wp_create_nonce( $nonce_action );

		$_REQUEST = array(
			'polls_media' => '1',
			'_wpnonce'    => $valid_nonce,
		);

		// Mock admin context
		set_current_screen( 'dashboard' );

		// Call the function
		polldaddy_popups_init();

		// Verify filters were added
		$this->assertNotFalse( has_filter( 'type_url_form_video', 'pd_video_shortcodes_help' ), 'Video filter should be added with valid nonce' );
		$this->assertNotFalse( has_filter( 'type_url_form_audio', 'pd_audio_shortcodes_help' ), 'Audio filter should be added with valid nonce' );
		$this->assertNotFalse( has_filter( 'type_url_form_image', 'pd_image_shortcodes_help' ), 'Image filter should be added with valid nonce' );

		// Clean up
		wp_delete_user( $user_id );
		$_REQUEST = $original_request;
	}

	/**
	 * Test that cross-user CSRF attack is prevented.
	 */
	public function test_csrf_attack_prevented(): void {
		$original_request = $_REQUEST;

		// Create victim user (admin)
		$victim_user_id = wp_insert_user( array(
			'user_login' => 'victim_' . time(),
			'user_email' => 'victim' . time() . '@example.com',
			'user_pass'  => 'testpass123',
			'role'       => 'administrator',
		) );

		// Create attacker user (also admin for this test)
		$attacker_user_id = wp_insert_user( array(
			'user_login' => 'attacker_' . time(),
			'user_email' => 'attacker' . time() . '@example.com',
			'user_pass'  => 'testpass123',
			'role'       => 'administrator',
		) );

		// Attacker creates a nonce for their own user
		wp_set_current_user( $attacker_user_id );
		$attacker_nonce_action = 'polls_media_' . $attacker_user_id;
		$attacker_nonce = wp_create_nonce( $attacker_nonce_action );

		// Switch to victim user
		wp_set_current_user( $victim_user_id );

		// Simulate CSRF attack: attacker's nonce used in victim's context
		$_REQUEST = array(
			'polls_media' => '1',
			'_wpnonce'    => $attacker_nonce,
		);

		// Mock admin context
		set_current_screen( 'dashboard' );

		// The function should reject this because the nonce doesn't match the current user
		polldaddy_popups_init();

		// Verify that the attack was prevented - filters should not be added
		$this->assertFalse( has_filter( 'type_url_form_video', 'pd_video_shortcodes_help' ), 'CSRF attack should be prevented - video filter not added' );
		$this->assertFalse( has_filter( 'type_url_form_audio', 'pd_audio_shortcodes_help' ), 'CSRF attack should be prevented - audio filter not added' );
		$this->assertFalse( has_filter( 'type_url_form_image', 'pd_image_shortcodes_help' ), 'CSRF attack should be prevented - image filter not added' );

		// Clean up
		wp_delete_user( $victim_user_id );
		wp_delete_user( $attacker_user_id );
		$_REQUEST = $original_request;
	}
}
