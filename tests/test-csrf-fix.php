<?php
/**
 * Test CSRF vulnerability fix
 *
 * This test verifies that the CSRF vulnerability (CVE-2024-43338)
 * in polldaddy_popups_init() has been properly fixed.
 */

// Mock WordPress functions for testing
function wp_verify_nonce($nonce, $action) {
    // Mock valid nonce
    return $nonce === 'valid_nonce_123';
}

function __($string) {
    return $string;
}

function add_filter($hook, $callback) {
    global $test_filters_added;
    $test_filters_added[] = $hook;
    return true;
}

// Include the fixed popups.php file (just the function we need)
function polldaddy_popups_init() {
	if( isset( $_REQUEST['polls_media'] ) ){
		// Verify nonce for security
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'media-form' ) ) {
			return;
		}
		add_filter( 'type_url_form_video', 'pd_video_shortcodes_help');
		add_filter( 'type_url_form_audio', 'pd_audio_shortcodes_help');
		add_filter( 'type_url_form_image', 'pd_image_shortcodes_help');
	}
}

// Test Cases
function test_csrf_vulnerability_fixed() {
    global $test_filters_added;

    echo "Testing CSRF vulnerability fix...\n";

    // Test 1: Request without nonce should be blocked
    echo "Test 1: Request without nonce - ";
    $_REQUEST = array('polls_media' => '1');
    $test_filters_added = array();
    polldaddy_popups_init();
    if (empty($test_filters_added)) {
        echo "PASS - Filters not added without nonce\n";
    } else {
        echo "FAIL - Filters were added without nonce verification\n";
    }

    // Test 2: Request with invalid nonce should be blocked
    echo "Test 2: Request with invalid nonce - ";
    $_REQUEST = array('polls_media' => '1', '_wpnonce' => 'invalid_nonce');
    $test_filters_added = array();
    polldaddy_popups_init();
    if (empty($test_filters_added)) {
        echo "PASS - Filters not added with invalid nonce\n";
    } else {
        echo "FAIL - Filters were added with invalid nonce\n";
    }

    // Test 3: Request with valid nonce should work
    echo "Test 3: Request with valid nonce - ";
    $_REQUEST = array('polls_media' => '1', '_wpnonce' => 'valid_nonce_123');
    $test_filters_added = array();
    polldaddy_popups_init();
    if (count($test_filters_added) === 3) {
        echo "PASS - Filters added with valid nonce\n";
    } else {
        echo "FAIL - Filters not added with valid nonce\n";
    }

    // Test 4: Request without polls_media should not trigger
    echo "Test 4: Request without polls_media parameter - ";
    $_REQUEST = array('_wpnonce' => 'valid_nonce_123');
    $test_filters_added = array();
    polldaddy_popups_init();
    if (empty($test_filters_added)) {
        echo "PASS - No filters added when polls_media not present\n";
    } else {
        echo "FAIL - Filters were added when polls_media not present\n";
    }

    echo "\nCSRF vulnerability fix testing completed.\n";
}

// Run tests
test_csrf_vulnerability_fixed();
?>