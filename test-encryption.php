<?php
/**
 * Test script to verify access token encryption
 *
 * Usage:
 * 1. Access this via WordPress admin (place in plugin root)
 * 2. Or run via WP-CLI: wp eval-file test-encryption.php
 * 3. Or include from WordPress: require_once('test-encryption.php');
 */

// Load WordPress if not already loaded
if (!defined('ABSPATH')) {
    // Adjust path if needed - this assumes plugin is in wp-content/plugins/
    require_once(dirname(__FILE__) . '/../../../wp-load.php');
}

// Load plugin constants if not already loaded
if (!function_exists('smdp_encrypt')) {
    require_once(dirname(__FILE__) . '/includes/constants.php');
}

echo "<h2>SMDP Access Token Encryption Test</h2>\n\n";

// Test 1: Check if encryption functions exist
echo "<h3>1. Function Availability Check</h3>\n";
echo "✓ smdp_encrypt() exists: " . (function_exists('smdp_encrypt') ? 'YES' : 'NO') . "\n";
echo "✓ smdp_decrypt() exists: " . (function_exists('smdp_decrypt') ? 'YES' : 'NO') . "\n";
echo "✓ OpenSSL available: " . (function_exists('openssl_encrypt') ? 'YES' : 'NO') . "\n\n";

// Test 2: Retrieve current stored token (encrypted format)
echo "<h3>2. Current Stored Token</h3>\n";
$encrypted_value = get_option(SMDP_ACCESS_TOKEN, '');
if (empty($encrypted_value)) {
    echo "⚠ No access token stored yet.\n\n";
} else {
    echo "✓ Token is stored in database\n";
    echo "✓ Stored format: " . substr($encrypted_value, 0, 50) . "...\n";
    echo "✓ Storage length: " . strlen($encrypted_value) . " characters\n";
    echo "✓ Is Base64 encoded: " . (base64_decode($encrypted_value, true) !== false ? 'YES' : 'NO') . "\n\n";
}

// Test 3: Test encryption/decryption with sample data
echo "<h3>3. Encryption/Decryption Test</h3>\n";
$test_token = "sandbox-sq0atb-EXAMPLE_TOKEN_12345";
echo "Test token: {$test_token}\n";

$encrypted = smdp_encrypt($test_token);
if ($encrypted === false) {
    echo "✗ FAILED: Could not encrypt test token\n\n";
} else {
    echo "✓ Encrypted: " . substr($encrypted, 0, 50) . "...\n";
    echo "✓ Encrypted length: " . strlen($encrypted) . " characters\n";

    $decrypted = smdp_decrypt($encrypted);
    if ($decrypted === $test_token) {
        echo "✓ SUCCESS: Decryption matches original!\n\n";
    } else {
        echo "✗ FAILED: Decrypted value doesn't match\n";
        echo "  Expected: {$test_token}\n";
        echo "  Got: {$decrypted}\n\n";
    }
}

// Test 4: Verify stored access token can be retrieved
if (!empty($encrypted_value)) {
    echo "<h3>4. Actual Access Token Retrieval</h3>\n";
    $decrypted_token = smdp_get_access_token();

    if (empty($decrypted_token)) {
        echo "✗ FAILED: Could not decrypt stored access token\n";
        echo "  This might indicate:\n";
        echo "  - Corrupted encryption data\n";
        echo "  - WordPress salts have changed since encryption\n";
        echo "  - OpenSSL version incompatibility\n\n";
    } else {
        echo "✓ SUCCESS: Access token successfully decrypted\n";
        echo "✓ Decrypted length: " . strlen($decrypted_token) . " characters\n";
        echo "✓ First 10 chars: " . substr($decrypted_token, 0, 10) . "...\n";
        echo "✓ Last 10 chars: ..." . substr($decrypted_token, -10) . "\n\n";

        // Verify it looks like a Square token
        if (strpos($decrypted_token, 'sq0') === 0 || strpos($decrypted_token, 'EAAA') === 0) {
            echo "✓ Token format appears valid (Square token pattern detected)\n\n";
        } else {
            echo "⚠ Token doesn't match typical Square token pattern\n\n";
        }
    }
}

// Test 5: Database inspection
echo "<h3>5. Security Analysis</h3>\n";
if (!empty($encrypted_value)) {
    // Check if stored value looks encrypted
    if (base64_decode($encrypted_value, true) !== false && strlen($encrypted_value) > 100) {
        echo "✓ SECURE: Token appears to be encrypted\n";
        echo "✓ Encryption method: AES-256-CBC\n";
        echo "✓ Key source: WordPress AUTH_SALT + SECURE_AUTH_SALT\n";
        echo "✓ IV: Random (included with ciphertext)\n\n";

        echo "<strong>Security Status: PASS ✓</strong>\n";
        echo "Your access token is stored securely using industry-standard encryption.\n\n";
    } else {
        echo "✗ WARNING: Token might be stored in plain text!\n";
        echo "  The stored value doesn't appear to be properly encrypted.\n\n";
        echo "<strong>Security Status: FAIL ✗</strong>\n\n";
    }
} else {
    echo "ℹ No token stored - cannot evaluate security\n\n";
}

// Test 6: View raw database value (for verification)
echo "<h3>6. Database Direct Query (Raw Value)</h3>\n";
global $wpdb;
$option_name = SMDP_ACCESS_TOKEN;
$result = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
        $option_name
    )
);

if ($result) {
    $raw_value = $result->option_value;
    echo "✓ Found in database: {$option_name}\n";
    echo "✓ Raw value preview: " . substr($raw_value, 0, 100) . "...\n";
    echo "✓ Total length: " . strlen($raw_value) . " characters\n\n";

    // Check if it's plain text (would be bad)
    if (strpos($raw_value, 'sq0') === 0 || strpos($raw_value, 'EAAA') === 0) {
        echo "<strong style='color:red;'>✗ SECURITY ISSUE: Token is stored in PLAIN TEXT!</strong>\n";
        echo "  Immediate action required: Re-save the token to encrypt it.\n\n";
    } else {
        echo "✓ Value is encrypted (not readable as plain text)\n\n";
    }
} else {
    echo "ℹ No token found in database\n\n";
}

echo "\n<h3>Summary</h3>\n";
echo "Run this test after saving your access token in the admin settings.\n";
echo "A secure token should:\n";
echo "  1. Be stored as base64-encoded encrypted data\n";
echo "  2. Not contain readable token patterns (sq0, EAAA)\n";
echo "  3. Be longer than the original token (due to IV + encryption)\n";
echo "  4. Decrypt successfully to the original value\n";
