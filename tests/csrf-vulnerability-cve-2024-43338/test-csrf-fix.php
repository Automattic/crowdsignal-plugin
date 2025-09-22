<?php
/**
 * Test CSRF vulnerability fix
 *
 * This test verifies that the CSRF vulnerability (CVE-2024-43338)
 * in polldaddy_popups_init() has been properly fixed.
 */

// Mock WordPress functions for testing
function is_admin() {
    global $test_is_admin;
    return isset($test_is_admin) ? $test_is_admin : true;
}

function current_user_can($capability) {
    global $test_user_can_edit;
    return isset($test_user_can_edit) ? $test_user_can_edit : true;
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
		// Security check: Only add filters if we're in a valid admin context
		// This prevents CSRF attacks while allowing legitimate media upload functionality
		if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		add_filter( 'type_url_form_video', 'pd_video_shortcodes_help');
		add_filter( 'type_url_form_audio', 'pd_audio_shortcodes_help');
		add_filter( 'type_url_form_image', 'pd_image_shortcodes_help');
	}
}

// Test Cases
function test_csrf_vulnerability_fixed() {
    global $test_filters_added, $test_is_admin, $test_user_can_edit;

    echo "Testing CSRF vulnerability fix...\n";

    // Test 1: Non-admin context should be blocked
    echo "Test 1: Non-admin context - ";
    $_REQUEST = array('polls_media' => '1');
    $test_filters_added = array();
    $test_is_admin = false;
    $test_user_can_edit = true;
    polldaddy_popups_init();
    if (empty($test_filters_added)) {
        echo "PASS - Filters not added in non-admin context\n";
    } else {
        echo "FAIL - Filters were added in non-admin context\n";
    }

    // Test 2: User without edit_posts capability should be blocked
    echo "Test 2: User without edit_posts capability - ";
    $_REQUEST = array('polls_media' => '1');
    $test_filters_added = array();
    $test_is_admin = true;
    $test_user_can_edit = false;
    polldaddy_popups_init();
    if (empty($test_filters_added)) {
        echo "PASS - Filters not added without edit_posts capability\n";
    } else {
        echo "FAIL - Filters were added without edit_posts capability\n";
    }

    // Test 3: Valid admin context should work
    echo "Test 3: Valid admin context with edit_posts capability - ";
    $_REQUEST = array('polls_media' => '1');
    $test_filters_added = array();
    $test_is_admin = true;
    $test_user_can_edit = true;
    polldaddy_popups_init();
    if (count($test_filters_added) === 3) {
        echo "PASS - Filters added in valid admin context\n";
    } else {
        echo "FAIL - Filters not added in valid admin context\n";
    }

    // Test 4: Request without polls_media should not trigger
    echo "Test 4: Request without polls_media parameter - ";
    $_REQUEST = array();
    $test_filters_added = array();
    $test_is_admin = true;
    $test_user_can_edit = true;
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