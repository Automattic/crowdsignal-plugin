<?php
/**
 * Simple test to validate basic PHP functionality
 * This test doesn't require WordPress test suite
 */

// Basic test class without WordPress dependencies
class SimpleTest {

    private $passed = 0;
    private $failed = 0;
    private $tests = array();

    public function test_basic_php_functionality() {
        $this->assert_equals( 2 + 2, 4, 'Basic math should work' );
        $this->assert_true( function_exists( 'strlen' ), 'PHP built-in functions should exist' );
        $this->assert_not_empty( 'test', 'String should not be empty' );
    }

    public function test_polldaddy_file_exists() {
        $plugin_file = dirname( __DIR__ ) . '/polldaddy.php';
        $this->assert_true( file_exists( $plugin_file ), 'Main plugin file should exist' );
    }

    public function test_phpcs_config_exists() {
        $phpcs_file = dirname( __DIR__ ) . '/phpcs.xml';
        $this->assert_true( file_exists( $phpcs_file ), 'PHPCS config should exist' );
    }

    public function test_phpunit_config_exists() {
        $phpunit_file = dirname( __DIR__ ) . '/phpunit.xml';
        $this->assert_true( file_exists( $phpunit_file ), 'PHPUnit config should exist' );
    }

    public function test_composer_config_exists() {
        $composer_file = dirname( __DIR__ ) . '/composer.json';
        $this->assert_true( file_exists( $composer_file ), 'Composer config should exist' );
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

    private function assert_not_empty( $value, $message ) {
        if ( !empty( $value ) ) {
            $this->pass( $message );
        } else {
            $this->fail( $message );
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
        echo "Running simple tests...\n\n";

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
$test = new SimpleTest();
$test->run_all_tests();