<?php
/**
 * PHPUnit bootstrap file for the Crowdsignal Plugin
 *
 * @package Crowdsignal_Forms
 */

// Get the WordPress tests directory.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration.
if ( ! getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	$polyfills_path = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills';
	if ( ! file_exists( $polyfills_path ) ) {
		$polyfills_path = dirname( __DIR__ ) . '/phpunit-polyfills.php';
	}
	putenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH=' . $polyfills_path );
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/polldaddy.php';
}

// Hook in our plugin loading.
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Include any additional test helpers or utilities.
require_once dirname( __FILE__ ) . '/class-crowdsignal-test-case.php';