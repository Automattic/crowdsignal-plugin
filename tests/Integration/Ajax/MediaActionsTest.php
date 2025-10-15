<?php
/**
 * Tests for AJAX media actions (upload image, add answer)
 *
 * @package Automattic\Crowdsignal\Tests\Integration\Ajax
 */

declare(strict_types=1);

namespace Automattic\Crowdsignal\Tests\Integration\Ajax;

use Automattic\Crowdsignal\Tests\Integration\TestCase;

/**
 * Test class for AJAX media actions and CSRF protection.
 */
class MediaActionsTest extends TestCase {

	/**
	 * Test that polls_upload_image AJAX action exists and is registered.
	 */
	public function test_polls_upload_image_action_registered(): void {
		$this->assertNotFalse( has_action( 'wp_ajax_polls_upload_image' ), 'polls_upload_image AJAX action should be registered' );
	}

	/**
	 * Test that polls_add_answer AJAX action exists and is registered.
	 */
	public function test_polls_add_answer_action_registered(): void {
		$this->assertNotFalse( has_action( 'wp_ajax_polls_add_answer' ), 'polls_add_answer AJAX action should be registered' );
	}

	/**
	 * Test that polls_upload_image rejects requests without nonce.
	 */
	public function test_polls_upload_image_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action'     => 'polls_upload_image',
			'attach-id'  => '123',
			'media-id'   => '456',
			'uc'         => 'test_code',
			'url'        => 'https://example.com/image.jpg',
		);

		// This should trigger wp_die due to failed nonce check
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		do_action( 'wp_ajax_polls_upload_image' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that polls_upload_image rejects requests with invalid nonce.
	 */
	public function test_polls_upload_image_rejects_invalid_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data with invalid nonce
		$_POST = array(
			'action'     => 'polls_upload_image',
			'_wpnonce'   => 'invalid_nonce_value',
			'attach-id'  => '123',
			'media-id'   => '456',
			'uc'         => 'test_code',
			'url'        => 'https://example.com/image.jpg',
		);

		// This should trigger wp_die due to failed nonce check
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		do_action( 'wp_ajax_polls_upload_image' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that polls_upload_image rejects requests from users without edit_posts capability.
	 */
	public function test_polls_upload_image_rejects_insufficient_capability(): void {
		// Create subscriber user (no edit_posts capability)
		$user_id = $this->create_test_user( array() );
		wp_set_current_user( $user_id );

		// Set up POST data with valid nonce
		$_POST = array(
			'action'     => 'polls_upload_image',
			'_wpnonce'   => wp_create_nonce( 'send-media' ),
			'attach-id'  => '123',
			'media-id'   => '456',
			'uc'         => 'test_code',
			'url'        => 'https://example.com/image.jpg',
		);

		// This should trigger wp_die due to insufficient capability
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		do_action( 'wp_ajax_polls_upload_image' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that polls_add_answer rejects requests without nonce.
	 */
	public function test_polls_add_answer_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'polls_add_answer',
			'aa'     => '1',
			'src'    => 'test_src',
			'popup'  => '0',
		);

		// This should trigger wp_die due to failed nonce check
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		do_action( 'wp_ajax_polls_add_answer' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that polls_add_answer rejects requests with invalid nonce.
	 */
	public function test_polls_add_answer_rejects_invalid_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data with invalid nonce
		$_POST = array(
			'action'   => 'polls_add_answer',
			'_wpnonce' => 'invalid_nonce_value',
			'aa'       => '1',
			'src'      => 'test_src',
			'popup'    => '0',
		);

		// This should trigger wp_die due to failed nonce check
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		do_action( 'wp_ajax_polls_add_answer' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that polls_add_answer rejects requests from users without edit_posts capability.
	 */
	public function test_polls_add_answer_rejects_insufficient_capability(): void {
		// Create subscriber user (no edit_posts capability)
		$user_id = $this->create_test_user( array() );
		wp_set_current_user( $user_id );

		// Set up POST data with valid nonce
		$_POST = array(
			'action'   => 'polls_add_answer',
			'_wpnonce' => wp_create_nonce( 'add-answer' ),
			'aa'       => '1',
			'src'      => 'test_src',
			'popup'    => '0',
		);

		// This should trigger wp_die due to insufficient capability
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		do_action( 'wp_ajax_polls_add_answer' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that polls_add_answer nonce validation works correctly.
	 */
	public function test_polls_add_answer_nonce_validation(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_posts' ) );
		wp_set_current_user( $user_id );

		// Test that valid nonce is created correctly
		$valid_nonce = wp_create_nonce( 'add-answer' );
		$this->assertNotEmpty( $valid_nonce, 'Should create valid nonce for add-answer action' );
		
		// Test that nonce verification works
		$this->assertNotFalse( wp_verify_nonce( $valid_nonce, 'add-answer' ), 'Should verify valid nonce' );
		$this->assertFalse( wp_verify_nonce( 'invalid_nonce', 'add-answer' ), 'Should reject invalid nonce' );
		$this->assertFalse( wp_verify_nonce( $valid_nonce, 'wrong-action' ), 'Should reject nonce for wrong action' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that polls_upload_image nonce validation works correctly.
	 */
	public function test_polls_upload_image_nonce_validation(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_posts' ) );
		wp_set_current_user( $user_id );

		// Test that valid nonce is created correctly
		$valid_nonce = wp_create_nonce( 'send-media' );
		$this->assertNotEmpty( $valid_nonce, 'Should create valid nonce for send-media action' );
		
		// Test that nonce verification works
		$this->assertNotFalse( wp_verify_nonce( $valid_nonce, 'send-media' ), 'Should verify valid nonce' );
		$this->assertFalse( wp_verify_nonce( 'invalid_nonce', 'send-media' ), 'Should reject invalid nonce' );
		$this->assertFalse( wp_verify_nonce( $valid_nonce, 'wrong-action' ), 'Should reject nonce for wrong action' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that AJAX actions are not available to unauthenticated users.
	 */
	public function test_ajax_actions_require_authentication(): void {
		// Ensure no user is logged in
		wp_set_current_user( 0 );

		// Set up POST data
		$_POST = array(
			'action'   => 'polls_add_answer',
			'_wpnonce' => wp_create_nonce( 'add-answer' ),
			'aa'       => '1',
			'src'      => 'test_src',
			'popup'    => '0',
		);

		// This should fail for unauthenticated user
		$this->expectException( 'WPDieException' );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		do_action( 'wp_ajax_polls_add_answer' );
	}
}
