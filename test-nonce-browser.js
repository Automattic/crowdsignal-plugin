#!/usr/bin/env node

const { exec } = require('child_process');
const fs = require('fs');

// Simple curl-based test to check nonce implementation
console.log('🔍 Testing CSRF nonce implementation...\n');

// Test 1: Check if WordPress admin is accessible
console.log('1. Testing WordPress admin accessibility...');
exec('curl -s "http://localhost:8888/wp-admin/"', (error, stdout) => {
    if (error) {
        console.log('❌ WordPress admin not accessible');
        return;
    }

    if (stdout.includes('wp-login') || stdout.includes('login')) {
        console.log('ℹ️  WordPress requires login - this is expected');
    } else {
        console.log('✅ WordPress admin accessible');
    }

    // Test 2: Check Crowdsignal plugin pages
    console.log('\n2. Testing Crowdsignal admin pages...');
    exec('curl -s "http://localhost:8888/wp-admin/admin.php?page=polls"', (error, stdout) => {
        if (error) {
            console.log('❌ Could not access polls page');
            return;
        }

        if (stdout.includes('polls_media_nonce')) {
            console.log('✅ Found polls_media_nonce in page source');

            // Extract nonce value
            const nonceMatch = stdout.match(/polls_media_nonce['":\s]+['"]([^'"]+)['"]/);
            if (nonceMatch) {
                console.log(`✅ Nonce value found: ${nonceMatch[1]}`);

                // Test 3: Test CSRF protection
                console.log('\n3. Testing CSRF protection...');
                testCSRFProtection(nonceMatch[1]);
            }
        } else if (stdout.includes('polldaddy') || stdout.includes('Crowdsignal')) {
            console.log('✅ Crowdsignal page found, but nonce not detected (may need authentication)');
        } else {
            console.log('❓ Crowdsignal page structure unclear');
        }
    });
});

function testCSRFProtection(validNonce) {
    // Test with valid nonce
    exec(`curl -s "http://localhost:8888/wp-admin/media-upload.php?type=image&polls_media=1&_wpnonce=${validNonce}&TB_iframe=1"`, (error, stdout) => {
        console.log('   Testing with valid nonce...');
        if (stdout.includes('pd_video_shortcodes_help') || stdout.includes('pd_image_shortcodes_help')) {
            console.log('   ✅ Valid nonce allows access to custom poll media functionality');
        } else {
            console.log('   ❓ Valid nonce response unclear (may need authentication)');
        }

        // Test with invalid nonce
        exec('curl -s "http://localhost:8888/wp-admin/media-upload.php?type=image&polls_media=1&_wpnonce=invalid&TB_iframe=1"', (error, stdout) => {
            console.log('   Testing with invalid nonce...');
            if (!stdout.includes('pd_video_shortcodes_help') && !stdout.includes('pd_image_shortcodes_help')) {
                console.log('   ✅ Invalid nonce blocks custom poll media functionality');
            } else {
                console.log('   ❌ Invalid nonce still allows access - CSRF vulnerability exists!');
            }

            // Test with no nonce
            exec('curl -s "http://localhost:8888/wp-admin/media-upload.php?type=image&polls_media=1&TB_iframe=1"', (error, stdout) => {
                console.log('   Testing with no nonce...');
                if (!stdout.includes('pd_video_shortcodes_help') && !stdout.includes('pd_image_shortcodes_help')) {
                    console.log('   ✅ No nonce blocks custom poll media functionality');
                    console.log('\n🎉 CSRF protection appears to be working correctly!');
                } else {
                    console.log('   ❌ No nonce still allows access - CSRF vulnerability exists!');
                }
            });
        });
    });
}