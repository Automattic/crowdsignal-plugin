<?php
/**
 * PHPUnit bootstrap file for Crowdsignal plugin tests
 *
 * @package Automattic\Crowdsignal
 */

declare( strict_types = 1 );

namespace Automattic\Crowdsignal\Tests;

// Define WPDieException in global namespace
// This is needed for wp-tester integration mode and must be available during test discovery
// Use eval to define in global namespace from within a namespaced file
if ( ! class_exists( '\WPDieException', false ) ) {
	eval( 'namespace { class WPDieException extends Exception {} }' );
}

use Yoast\WPTestUtils\WPIntegration;

// Output immediately to stderr so we know bootstrap is running
if ( defined( 'STDERR' ) ) {
	fwrite( STDERR, "\n=== BOOTSTRAP STARTED ===\n" );
}

// Enable error reporting for debugging
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
ini_set( 'log_errors', '1' );

// Set up error handler to catch fatal errors
register_shutdown_function( function() {
	$error = error_get_last();
	if ( $error !== null && in_array( $error['type'], [ E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE ], true ) ) {
		echo "\n\n=== FATAL ERROR IN BOOTSTRAP ===\n";
		echo "Type: {$error['type']}\n";
		echo "Message: {$error['message']}\n";
		echo "File: {$error['file']}\n";
		echo "Line: {$error['line']}\n";
		echo "================================\n\n";
	}
} );

// Set up exception handler
set_exception_handler( function( $exception ) {
	echo "\n\n=== UNCAUGHT EXCEPTION IN BOOTSTRAP ===\n";
	echo "Message: {$exception->getMessage()}\n";
	echo "File: {$exception->getFile()}\n";
	echo "Line: {$exception->getLine()}\n";
	echo "Trace:\n{$exception->getTraceAsString()}\n";
	echo "=====================================\n\n";
	throw $exception;
} );

require_once dirname( __DIR__ ) . '/vendor/yoast/wp-test-utils/src/WPIntegration/bootstrap-functions.php';

// Check for a `--testsuite integration` or `--testsuite=integration` arg when calling phpunit
$argv_local = $GLOBALS['argv'] ?? [];
$key        = (int) array_search( '--testsuite', $argv_local, true );
$is_integration = false;

// Check for --testsuite integration (two separate args)
if ( $key && isset( $argv_local[ $key + 1 ] ) && 'integration' === $argv_local[ $key + 1 ] ) {
	$is_integration = true;
}

// Check for --testsuite=integration (single arg with equals)
foreach ( $argv_local as $arg ) {
	if ( '--testsuite=integration' === $arg ) {
		$is_integration = true;
		break;
	}
}

if ( $is_integration ) {
	// Output to stderr immediately
	if ( defined( 'STDERR' ) ) {
		fwrite( STDERR, "=== BOOTSTRAP DEBUG: Integration mode detected ===\n" );
	}
	
	// Use error_log which should be visible
	error_log( "=== BOOTSTRAP DEBUG: Integration mode detected ===" );
	
	// Check if WordPress is already loaded (wp-tester integration mode)
	$wp_already_loaded = defined( 'ABSPATH' ) && function_exists( 'get_option' );
	
	if ( defined( 'STDERR' ) ) {
		fwrite( STDERR, "WordPress loaded: " . ( $wp_already_loaded ? 'yes' : 'no' ) . "\n" );
		fwrite( STDERR, "ABSPATH defined: " . ( defined( 'ABSPATH' ) ? ABSPATH : 'no' ) . "\n" );
	}
	
	error_log( "WordPress loaded: " . ( $wp_already_loaded ? 'yes' : 'no' ) );
	error_log( "ABSPATH defined: " . ( defined( 'ABSPATH' ) ? ABSPATH : 'no' ) );
	
	if ( $wp_already_loaded ) {
		error_log( "Using wp-tester integration mode (WordPress pre-loaded)" );
		
		// WordPress already loaded by wp-tester - just ensure plugin is loaded
		$plugin_file = dirname( __DIR__ ) . '/polldaddy.php';
		error_log( "Plugin file: {$plugin_file}" );
		error_log( "Plugin file exists: " . ( file_exists( $plugin_file ) ? 'yes' : 'no' ) );
		
		if ( ! file_exists( $plugin_file ) ) {
			$error = "Plugin file not found: {$plugin_file}";
			error_log( "ERROR: {$error}" );
			throw new \RuntimeException( $error );
		}
		
		// Load plugin if not already loaded
		if ( ! defined( 'POLLDADDY_API_HOST' ) ) {
			error_log( "Loading plugin..." );
			require_once $plugin_file;
			error_log( "Plugin loaded, POLLDADDY_API_HOST defined: " . ( defined( 'POLLDADDY_API_HOST' ) ? 'yes' : 'no' ) );
		} else {
			error_log( "Plugin already loaded" );
		}
		
		// Load test case
		$testcase_file = __DIR__ . '/Integration/TestCase.php';
		error_log( "TestCase file: {$testcase_file}" );
		error_log( "TestCase file exists: " . ( file_exists( $testcase_file ) ? 'yes' : 'no' ) );
		
		if ( ! file_exists( $testcase_file ) ) {
			$error = "TestCase file not found: {$testcase_file}";
			error_log( "ERROR: {$error}" );
			throw new \RuntimeException( $error );
		}
		
		if ( defined( 'STDERR' ) ) {
			fwrite( STDERR, "Requiring TestCase...\n" );
		}
		error_log( "Requiring TestCase..." );
		
		try {
			require $testcase_file;
			if ( defined( 'STDERR' ) ) {
				fwrite( STDERR, "TestCase required successfully\n" );
			}
			error_log( "TestCase required successfully" );
		} catch ( \Throwable $e ) {
			$error = "Error requiring TestCase: " . $e->getMessage() . "\n" . $e->getTraceAsString();
			error_log( "ERROR: {$error}" );
			// Also output to stderr
			if ( defined( 'STDERR' ) ) {
				fwrite( STDERR, "\n\n=== BOOTSTRAP ERROR ===\n{$error}\n=====================\n\n" );
			}
			throw $e;
		}
		
		if ( defined( 'STDERR' ) ) {
			fwrite( STDERR, "=== BOOTSTRAP COMPLETE ===\n" );
		}
		error_log( "=== BOOTSTRAP COMPLETE ===" );
	} else {
		// Traditional mode: WordPress not loaded yet, use WPIntegration
		$_tests_dir = WPIntegration\get_path_to_wp_test_dir();

		if ( ! $_tests_dir || ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
			throw new \RuntimeException( "WordPress test library not found at: {$_tests_dir}" );
		}

		// Give access to tests_add_filter() function.
		require_once $_tests_dir . '/includes/functions.php';

		// Manually load the plugin being tested.
		\tests_add_filter(
			'muplugins_loaded',
			function (): void {
				require dirname( __DIR__ ) . '/polldaddy.php';
			}
		);

		/*
		* Bootstrap WordPress. This will also load the Composer autoload file, the PHPUnit Polyfills
		* and the custom autoloader for the TestCase and the mock object classes.
		*/
		WPIntegration\bootstrap_it();

		// Add custom test case.
		require __DIR__ . '/Integration/TestCase.php';
	}
}