<?php
/**
 * Test stub for the WPCOM-only is_private_blog() function.
 *
 * Toggle behavior per test by setting $GLOBALS['polldaddy_test_is_private_blog'].
 *
 * @package Automattic\Crowdsignal\Tests\Integration\Ajax
 */

if ( ! function_exists( 'is_private_blog' ) ) {
	function is_private_blog() {
		return ! empty( $GLOBALS['polldaddy_test_is_private_blog'] );
	}
}
