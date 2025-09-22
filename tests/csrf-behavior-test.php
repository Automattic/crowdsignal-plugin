<?php
/**
 * CSRF Behavior Test
 *
 * This script shows the actual difference between vulnerable and fixed versions
 * by monitoring what happens when polls_media parameter is processed.
 */

// Simulate the CSRF attack scenario
$_REQUEST['polls_media'] = '1';
$_REQUEST['type'] = 'image';
$_REQUEST['csrf_test'] = 'malicious_payload';

echo "🔍 CSRF BEHAVIOR TEST\n";
echo "====================\n\n";

// Check if we're in vulnerable or fixed version
$popups_file = dirname(__DIR__) . '/popups.php';
$content = file_get_contents($popups_file);

if (strpos($content, 'Security check:') !== false) {
    echo "🛡️  TESTING FIXED VERSION\n";
    echo "Expected: Attack should be blocked\n\n";
} else {
    echo "⚠️  TESTING VULNERABLE VERSION\n";
    echo "Expected: Attack should succeed\n\n";
}

// Mock WordPress functions
function is_admin() {
    return false; // Simulate external request (CSRF scenario)
}

function current_user_can($capability) {
    return false; // Simulate unauthorized user
}

function add_filter($hook, $callback) {
    global $filters_added;
    $filters_added[] = $hook;
    echo "🚨 FILTER ADDED: $hook (VULNERABILITY EXPLOITED!)\n";
}

$filters_added = array();

// Include and test the actual function
if (file_exists($popups_file)) {
    // Extract just the function we need
    preg_match('/function polldaddy_popups_init\(\).*?^}/ms', $content, $matches);
    if (isset($matches[0])) {
        // Remove the function declaration and just get the body
        $function_body = $matches[0];
        $function_body = str_replace('function polldaddy_popups_init() {', '', $function_body);
        $function_body = rtrim($function_body, '}');

        echo "💻 Executing polldaddy_popups_init() logic...\n";
        eval($function_body);

        echo "\n📊 RESULTS:\n";
        if (empty($filters_added)) {
            echo "✅ ATTACK BLOCKED - No filters were added\n";
            echo "🛡️  Security check prevented CSRF exploitation\n";
        } else {
            echo "🚨 ATTACK SUCCEEDED - " . count($filters_added) . " filters added:\n";
            foreach ($filters_added as $filter) {
                echo "   - $filter\n";
            }
            echo "⚠️  This demonstrates the CSRF vulnerability!\n";
        }
    } else {
        echo "❌ Could not extract function from file\n";
    }
} else {
    echo "❌ popups.php file not found\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "🔬 Test completed. Switch branches and run again to compare!\n";
?>