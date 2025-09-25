<?php
/**
 * Base test case for Crowdsignal plugin tests
 *
 * @package Automattic\Crowdsignal\Tests
 */

namespace Automattic\Crowdsignal\Tests;

use WP_UnitTestCase;

/**
 * Base test case class with common functionality
 */
class TestCase extends WP_UnitTestCase {

    /**
     * Create a test user with specified capabilities
     *
     * @param array $capabilities List of capabilities to grant the user.
     * @return int User ID
     */
    protected function create_test_user( $capabilities = array() ) {
        $user_id = $this->factory->user->create();

        if ( ! empty( $capabilities ) ) {
            $user = get_user_by( 'id', $user_id );
            foreach ( $capabilities as $cap ) {
                $user->add_cap( $cap );
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