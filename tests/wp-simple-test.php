<?php
/**
 * WordPress-integrated test without complex dependencies
 */

// Load WordPress
require_once '/var/www/html/wp-load.php';

// Trigger WordPress initialization
do_action( 'init' );
do_action( 'wp_loaded' );

class WPSimpleTest {
    private $passed = 0;
    private $failed = 0;
    private $tests = array();

    public function test_wordpress_loaded() {
        $this->assert_true( function_exists( 'wp_head' ), 'WordPress should be loaded' );
        $this->assert_true( defined( 'ABSPATH' ), 'ABSPATH should be defined' );
    }

    public function test_basic_framework_setup() {
        // Test that WordPress testing framework is working
        $this->assert_true( function_exists( 'add_action' ), 'WordPress hook system should be available' );
        $this->assert_true( function_exists( 'get_option' ), 'WordPress option system should be available' );
        $this->assert_true( defined( 'ABSPATH' ), 'WordPress should be properly loaded' );
    }


    // Simple assertion methods
    private function assert_equals( $actual, $expected, $message ) {
        if ( $actual === $expected ) {
            $this->pass( $message );
        } else {
            $this->fail( $message . " (Expected: $expected, Actual: $actual)" );
        }
    }

    private function assert_true( $condition, $message ) {
        if ( $condition ) {
            $this->pass( $message );
        } else {
            $this->fail( $message );
        }
    }

    private function assert_not_false( $value, $message ) {
        if ( $value !== false ) {
            $this->pass( $message );
        } else {
            $this->fail( $message );
        }
    }

    private function assert_not_empty( $value, $message ) {
        if ( !empty( $value ) ) {
            $this->pass( $message );
        } else {
            $this->fail( $message );
        }
    }

    private function assert_contains( $needle, $haystack, $message ) {
        if ( strpos( $haystack, $needle ) !== false ) {
            $this->pass( $message );
        } else {
            $this->fail( $message . " ('$needle' not found in '$haystack')" );
        }
    }

    private function pass( $message ) {
        $this->passed++;
        $this->tests[] = "✓ PASS: $message";
    }

    private function fail( $message ) {
        $this->failed++;
        $this->tests[] = "✗ FAIL: $message";
    }

    public function run_all_tests() {
        echo "Running WordPress integration tests...\n\n";

        $methods = get_class_methods( $this );
        foreach ( $methods as $method ) {
            if ( strpos( $method, 'test_' ) === 0 ) {
                $this->$method();
            }
        }

        // Print results
        foreach ( $this->tests as $test ) {
            echo $test . "\n";
        }

        echo "\n";
        echo "Tests: " . ( $this->passed + $this->failed ) . "\n";
        echo "Passed: " . $this->passed . "\n";
        echo "Failed: " . $this->failed . "\n";

        if ( $this->failed > 0 ) {
            echo "\nSome tests failed!\n";
            exit( 1 );
        } else {
            echo "\nAll tests passed!\n";
            exit( 0 );
        }
    }
}

// Run the tests
$test = new WPSimpleTest();
$test->run_all_tests();