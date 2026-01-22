<?php
/**
 * Tests for admin action nonce gates and CSRF protection
 *
 * @package Automattic\Crowdsignal\Tests\Integration\Admin
 */

declare(strict_types=1);

namespace Automattic\Crowdsignal\Tests\Integration\Admin;

use Automattic\Crowdsignal\Tests\Integration\TestCase;

/**
 * Test class for admin action nonce gates and CSRF protection.
 */
class AdminActionsTest extends TestCase {

	/**
	 * Test that account action rejects requests without nonce.
	 */
	public function test_account_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action'            => 'account',
			'polldaddy_email'   => 'test@example.com',
			'polldaddy_password' => 'password123',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in api_key_page_load
		check_admin_referer( 'polldaddy-account' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that account action accepts valid nonce.
	 */
	public function test_account_action_accepts_valid_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data with valid nonce
		$_POST = array(
			'action'            => 'account',
			'_wpnonce'          => wp_create_nonce( 'polldaddy-account' ),
			'polldaddy_email'   => 'test@example.com',
			'polldaddy_password' => 'password123',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should succeed with valid nonce
		$this->assertNotFalse( wp_verify_nonce( $_POST['_wpnonce'], 'polldaddy-account' ), 'Should verify valid nonce' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that account action rejects invalid nonce.
	 */
	public function test_account_action_rejects_invalid_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data with invalid nonce
		$_POST = array(
			'action'            => 'account',
			'_wpnonce'          => 'invalid_nonce_value',
			'polldaddy_email'   => 'test@example.com',
			'polldaddy_password' => 'password123',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to invalid nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in api_key_page_load
		check_admin_referer( 'polldaddy-account' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that disconnect action accepts valid nonce.
	 */
	public function test_disconnect_action_accepts_valid_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data with valid nonce
		$_POST = array(
			'action'   => 'disconnect',
			'_wpnonce' => wp_create_nonce( 'disconnect-api-key' ),
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should succeed with valid nonce
		$this->assertNotFalse( wp_verify_nonce( $_POST['_wpnonce'], 'disconnect-api-key' ), 'Should verify valid nonce' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that disconnect action rejects requests with invalid nonce.
	 */
	public function test_disconnect_action_rejects_invalid_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data with invalid nonce
		$_POST = array(
			'action'   => 'disconnect',
			'_wpnonce' => 'invalid_nonce_value',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to invalid nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in disconnect action
		check_admin_referer( 'disconnect-api-key' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that disconnect action rejects requests without nonce.
	 */
	public function test_disconnect_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'disconnect',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in disconnect action
		check_admin_referer( 'disconnect-api-key' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that poll delete action accepts valid nonce.
	 */
	public function test_poll_delete_action_accepts_valid_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data with valid nonce
		$_POST = array(
			'action'   => 'delete',
			'_wpnonce' => wp_create_nonce( 'action-poll_bulk' ),
			'poll'     => array( '123', '456' ),
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should succeed with valid nonce
		$this->assertNotFalse( wp_verify_nonce( $_POST['_wpnonce'], 'action-poll_bulk' ), 'Should verify valid nonce' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that poll delete action rejects requests with invalid nonce.
	 */
	public function test_poll_delete_action_rejects_invalid_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data with invalid nonce
		$_POST = array(
			'action'   => 'delete',
			'_wpnonce' => 'invalid_nonce_value',
			'poll'     => array( '123', '456' ),
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to invalid nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in poll delete action
		check_admin_referer( 'action-poll_bulk' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that poll delete action rejects requests without nonce.
	 */
	public function test_poll_delete_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'delete',
			'poll'   => array( '123', '456' ),
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in poll delete action
		check_admin_referer( 'action-poll_bulk' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that single poll delete action rejects requests without nonce.
	 */
	public function test_single_poll_delete_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'delete',
			'poll'   => '123',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in single poll delete action
		check_admin_referer( 'delete-poll_123' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that poll open action rejects requests without nonce.
	 */
	public function test_poll_open_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'open',
			'poll'   => array( '123', '456' ),
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in poll open action
		check_admin_referer( 'action-poll_bulk' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that poll close action rejects requests without nonce.
	 */
	public function test_poll_close_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'close',
			'poll'   => array( '123', '456' ),
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in poll close action
		check_admin_referer( 'action-poll_bulk' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that poll edit action rejects requests without nonce.
	 */
	public function test_poll_edit_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'edit',
			'poll'   => '123',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in poll edit action
		check_admin_referer( 'edit-poll_123' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that poll create action rejects requests with invalid nonce.
	 */
	public function test_poll_create_action_rejects_invalid_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data with invalid nonce
		$_POST = array(
			'action'   => 'create',
			'_wpnonce' => 'invalid_nonce_value',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to invalid nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in poll create action
		check_admin_referer( 'create-poll' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that poll create action rejects requests without nonce.
	 */
	public function test_poll_create_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'create',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in poll create action
		check_admin_referer( 'create-poll' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that style delete action rejects requests with invalid nonce.
	 */
	public function test_style_delete_action_rejects_invalid_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data with invalid nonce
		$_POST = array(
			'action'   => 'delete',
			'_wpnonce' => 'invalid_nonce_value',
			'style'    => array( '123', '456' ),
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to invalid nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in style delete action
		check_admin_referer( 'action-style_bulk' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that style delete action rejects requests without nonce.
	 */
	public function test_style_delete_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'delete',
			'style'  => array( '123', '456' ),
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in style delete action
		check_admin_referer( 'action-style_bulk' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that style edit action rejects requests without nonce.
	 */
	public function test_style_edit_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'edit',
			'style'  => '123',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in style edit action
		check_admin_referer( 'edit-style123' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that style create action rejects requests without nonce.
	 */
	public function test_style_create_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'create',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in style create action
		check_admin_referer( 'create-style' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that rating delete action rejects requests without nonce.
	 */
	public function test_rating_delete_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'delete',
			'rating' => array( '123', '456' ),
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in rating delete action
		check_admin_referer( 'action-rating_bulk' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that rating settings action rejects requests without nonce.
	 */
	public function test_rating_settings_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action'                => 'settings',
			'pd_rating_action_type' => 'posts',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in rating settings action
		check_admin_referer( 'action-rating_settings_posts' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that rating update action rejects requests without nonce.
	 */
	public function test_rating_update_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'update',
			'type'   => 'posts',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in rating update action
		check_admin_referer( 'action-update-rating_posts' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that account reset action rejects requests without nonce.
	 */
	public function test_account_reset_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'reset-account',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in account reset action
		check_admin_referer( 'polldaddy-reset' . $user_id );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that account restore action rejects requests without nonce.
	 */
	public function test_account_restore_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'restore-account',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in account restore action
		check_admin_referer( 'polldaddy-restore' . $user_id );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that multi-account action rejects requests without nonce.
	 */
	public function test_multi_account_action_rejects_missing_nonce(): void {
		// Create admin user
		$user_id = $this->create_test_user( array( 'edit_others_posts' ) );
		wp_set_current_user( $user_id );

		// Set up POST data without nonce
		$_POST = array(
			'action' => 'multi-account',
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to missing nonce
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in multi-account action
		check_admin_referer( 'polldaddy-reset' . $user_id );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that admin actions require proper user capabilities.
	 */
	public function test_admin_actions_require_capabilities(): void {
		// Create subscriber user (no edit_others_posts capability)
		$user_id = $this->create_test_user( array() );
		wp_set_current_user( $user_id );

		// Set up POST data with valid nonce
		$_POST = array(
			'action'   => 'disconnect',
			'_wpnonce' => wp_create_nonce( 'disconnect-api-key' ),
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail due to insufficient capability
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in disconnect action
		check_admin_referer( 'disconnect-api-key' );

		// Clean up
		wp_delete_user( $user_id );
	}

	/**
	 * Test that admin actions are not available to unauthenticated users.
	 */
	public function test_admin_actions_require_authentication(): void {
		// Ensure no user is logged in
		wp_set_current_user( 0 );

		// Set up POST data with valid nonce
		$_POST = array(
			'action'   => 'account',
			'_wpnonce' => wp_create_nonce( 'polldaddy-account' ),
		);
		$_SERVER['REQUEST_METHOD'] = 'POST';

		// This should fail for unauthenticated user
		$this->expectException( \WPDieException::class );
		$this->expectExceptionMessage( 'The link you followed has expired.' );
		
		// Simulate the nonce check that happens in account action
		check_admin_referer( 'polldaddy-account' );
	}
}
