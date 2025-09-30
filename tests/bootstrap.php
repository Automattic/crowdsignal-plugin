<?php
/**
 * PHPUnit bootstrap file for Crowdsignal plugin tests
 *
 * @package Automattic\Crowdsignal
 */

declare( strict_types = 1 );

namespace Automattic\Crowdsignal\Tests;

// Check for a `--testsuite integration` arg when calling phpunit, and
// use it to conditionally load up WordPress.
$argv_local = $GLOBALS['argv'] ?? [];
$key        = (int) array_search( '--testsuite', $argv_local, true );

if ( $key && isset( $argv_local[ $key + 1 ] ) && 'integration' === $argv_local[ $key + 1 ] ) {
    $tests_dir = getenv( 'WP_TESTS_DIR' );

    if ( ! $tests_dir ) {
        $tests_dir = '/tmp/wordpress-tests-lib';
    }

    // Give access to tests_add_filter() function.
    require_once $tests_dir . '/includes/functions.php';

    // Manually load the plugin being tested.
    \tests_add_filter(
        'muplugins_loaded',
        function (): void {
            require dirname( __DIR__ ) . '/polldaddy.php';
        }
    );

    // Start up the WP testing environment.
    require $tests_dir . '/includes/bootstrap.php';
}
