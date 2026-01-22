<?php
/**
 * PHPUnit bootstrap file for Crowdsignal plugin tests
 *
 * @package Automattic\Crowdsignal
 */

declare( strict_types = 1 );

namespace Automattic\Crowdsignal\Tests;

// Define WPDieException in global namespace (needed for wp-tester integration mode)
if ( ! class_exists( '\WPDieException', false ) ) {
	eval( 'namespace { class WPDieException extends Exception {} }' );
}

use Yoast\WPTestUtils\WPIntegration;

require_once dirname( __DIR__ ) . '/vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';

// Check for integration test suite
$argv_local = $GLOBALS['argv'] ?? [];
$is_integration = false;

if ( ( $key = array_search( '--testsuite', $argv_local, true ) ) !== false && isset( $argv_local[ $key + 1 ] ) && 'integration' === $argv_local[ $key + 1 ] ) {
	$is_integration = true;
}

foreach ( $argv_local as $arg ) {
	if ( '--testsuite=integration' === $arg ) {
		$is_integration = true;
		break;
	}
}

if ( $is_integration ) {
	// Check if WordPress is already loaded (wp-tester integration mode)
	$wp_already_loaded = defined( 'ABSPATH' ) && function_exists( 'get_option' );

	if ( $wp_already_loaded ) {
		// WordPress loaded by wp-tester - load admin includes and plugin
		if ( defined( 'ABSPATH' ) ) {
			$admin_includes = array(
				'wp-admin/includes/user.php',
				'wp-admin/includes/post.php',
				'wp-admin/includes/file.php',
				'wp-admin/includes/admin.php',
			);

			foreach ( $admin_includes as $include_file ) {
				$full_path = ABSPATH . $include_file;
				if ( file_exists( $full_path ) ) {
					require_once $full_path;
				}
			}
		}

		// Load plugin
		$plugin_file = dirname( __DIR__ ) . '/polldaddy.php';
		if ( ! defined( 'POLLDADDY_API_HOST' ) && file_exists( $plugin_file ) ) {
			require_once $plugin_file;
		}

		// Ensure AJAX handlers are registered (init may have already fired)
		if ( function_exists( 'polldaddy_ajax_init' ) && ! has_action( 'wp_ajax_polls_upload_image' ) ) {
			polldaddy_ajax_init();
		}

		if ( ! did_action( 'init' ) ) {
			do_action( 'init' );
		}

		// Load test case
		require __DIR__ . '/Integration/TestCase.php';
	} else {
		// Traditional mode: WordPress not loaded yet, use WPIntegration
		$_tests_dir = WPIntegration\get_path_to_wp_test_dir();

		if ( ! $_tests_dir || ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
			throw new \RuntimeException( "WordPress test library not found at: {$_tests_dir}" );
		}

		require_once $_tests_dir . '/includes/functions.php';

		\tests_add_filter(
			'muplugins_loaded',
			function (): void {
				require dirname( __DIR__ ) . '/polldaddy.php';
			}
		);

		WPIntegration\bootstrap_it();

		require __DIR__ . '/Integration/TestCase.php';
	}
}
