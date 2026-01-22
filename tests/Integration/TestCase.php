<?php
/**
 * Base test case for Crowdsignal plugin tests
 *
 * @package Automattic\Crowdsignal\Tests
 */

declare( strict_types = 1 );

namespace Automattic\Crowdsignal\Tests\Integration;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case class with common functionality
 * 
 * Works with both wp-tester integration mode (no test library) and traditional wp-env mode (with test library)
 */
class TestCase extends PHPUnitTestCase {

	/**
	 * Factory instance (if WordPress test library is available)
	 *
	 * @var \WP_UnitTest_Factory|null
	 */
	protected $factory = null;

	/**
	 * Set up test case
	 */
	protected function setUp(): void {
		parent::setUp();
		
		// Verify WPDieException exists
		if ( ! class_exists( '\WPDieException' ) && ! class_exists( 'WPDieException' ) ) {
			$error = "WPDieException class not found in setUp!";
			if ( defined( 'STDERR' ) ) {
				fwrite( STDERR, "ERROR: {$error}\n" );
			}
			throw new \RuntimeException( $error );
		}
		
		// Try to get factory from WordPress test library if available
		if ( class_exists( '\WP_UnitTest_Factory' ) && isset( $GLOBALS['wp_test_factory'] ) ) {
			$this->factory = $GLOBALS['wp_test_factory'];
		}
		
		// Override wp_die to throw exceptions instead of dying (needed for wp-tester integration mode)
		// WPDieException is defined in bootstrap.php
		if ( ! has_filter( 'wp_die_handler', array( $this, 'wp_die_handler' ) ) ) {
			add_filter( 'wp_die_handler', array( $this, 'wp_die_handler' ) );
		}
	}
	
	/**
	 * wp_die handler that throws exceptions instead of dying
	 *
	 * @param callable $handler Original handler.
	 * @return callable
	 */
	public function wp_die_handler( $handler ) {
		return function( $message, $title = '', $args = array() ) {
			// Extract message from array if needed
			if ( is_array( $message ) ) {
				$message = isset( $message['message'] ) ? $message['message'] : ( isset( $message[0] ) ? $message[0] : '' );
			}
			
			// Convert to string if needed, strip HTML tags
			$message = (string) $message;
			$message = wp_strip_all_tags( $message );
			$message = trim( $message );
			
			// Output to stderr for visibility
			if ( defined( 'STDERR' ) ) {
				fwrite( STDERR, "wp_die called with message: {$message}\n" );
			}
			error_log( "wp_die called with message: {$message}" );
			
			// Verify WPDieException exists
			if ( ! class_exists( '\WPDieException' ) && ! class_exists( 'WPDieException' ) ) {
				$error = "WPDieException class not found!";
				if ( defined( 'STDERR' ) ) {
					fwrite( STDERR, "ERROR: {$error}\n" );
				}
				throw new \RuntimeException( $error );
			}
			
			// Throw WPDieException (defined in bootstrap.php in global namespace)
			$exception_class = class_exists( '\WPDieException' ) ? '\WPDieException' : 'WPDieException';
			throw new $exception_class( $message );
		};
	}

	/**
	 * Tear down test case
	 */
	protected function tearDown(): void {
		// Remove wp_die handler
		remove_filter( 'wp_die_handler', array( $this, 'wp_die_handler' ) );
		
		parent::tearDown();
	}

    /**
     * Create a test user with specified capabilities
     *
     * @param array $capabilities List of capabilities to grant the user.
     * @return int User ID
     */
    protected function create_test_user( $capabilities = array() ) {
		// Use factory if available (traditional mode)
		if ( $this->factory ) {
			$user_id = $this->factory->user->create();
		} else {
			// wp-tester integration mode: create user directly
			$user_id = wp_insert_user( array(
				'user_login' => 'testuser_' . time() . '_' . wp_rand( 1000, 9999 ),
				'user_email' => 'test' . time() . '@example.com',
				'user_pass'  => wp_generate_password(),
			) );
			
			if ( is_wp_error( $user_id ) ) {
				throw new \RuntimeException( 'Failed to create test user: ' . $user_id->get_error_message() );
			}
		}

        if ( ! empty( $capabilities ) ) {
            $user = get_user_by( 'id', $user_id );
            if ( $user ) {
				foreach ( $capabilities as $cap ) {
					$user->add_cap( $cap );
				}
			}
        }

        return $user_id;
    }

    /**
     * Assert that a nonce is valid
     *
     * @param string $nonce  The nonce to check.
     * @param string $action The nonce action.
     */
    protected function assertNonceValid( $nonce, $action ) {
        $this->assertNotFalse( wp_verify_nonce( $nonce, $action ), 'Nonce should be valid' );
    }
}
