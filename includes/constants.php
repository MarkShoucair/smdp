<?php
/**
 * Plugin Constants
 *
 * Defines all constants used throughout the Square Menu Display plugin.
 *
 * @package Square_Menu_Display
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * -------------------------
 * Global Option Keys
 * -------------------------
 */

// Square API Access Token
define( 'SMDP_ACCESS_TOKEN', 'square_menu_access_token' );

// Sync interval in seconds
define( 'SMDP_SYNC_INTERVAL', 'square_menu_sync_interval' );

// Sync mode: 1 = manual only; 0 = automatic enabled
define( 'SMDP_SYNC_MODE', 'square_menu_sync_mode' );

// Cached Square items (catalog objects)
define( 'SMDP_ITEMS_OPTION', 'square_menu_items' );

// Item to category mappings and metadata
define( 'SMDP_MAPPING_OPTION', 'square_menu_item_mapping' );

// Active categories
define( 'SMDP_CATEGORIES_OPTION', 'square_menu_categories' );

// API request/response log
define( 'SMDP_API_LOG_OPTION', 'smdp_api_log' );

// Cron job hook name
define( 'SMDP_CRON_HOOK', 'square_menu_cron_sync' );

/**
 * -------------------------
 * Security Helper Functions
 * -------------------------
 */

/**
 * Encrypt sensitive data using WordPress salts
 *
 * @param string $data Data to encrypt
 * @return string|false Encrypted data (base64 encoded) or false on failure
 */
function smdp_encrypt( $data ) {
    if ( empty( $data ) ) {
        return false;
    }

    // Check if OpenSSL is available
    if ( ! function_exists( 'openssl_encrypt' ) ) {
        error_log( '[SMDP] OpenSSL not available, storing data unencrypted' );
        return $data; // Fallback to plain text
    }

    $method = 'AES-256-CBC';

    // Use WordPress salts as encryption key
    $key = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );

    // Generate a random IV
    $iv_length = openssl_cipher_iv_length( $method );
    $iv = openssl_random_pseudo_bytes( $iv_length );

    // Encrypt the data
    $encrypted = openssl_encrypt( $data, $method, $key, 0, $iv );

    if ( $encrypted === false ) {
        error_log( '[SMDP] Encryption failed' );
        return false;
    }

    // Combine IV and encrypted data, then base64 encode
    return base64_encode( $iv . $encrypted );
}

/**
 * Decrypt sensitive data
 *
 * @param string $encrypted_data Encrypted data (base64 encoded)
 * @return string|false Decrypted data or false on failure
 */
function smdp_decrypt( $encrypted_data ) {
    if ( empty( $encrypted_data ) ) {
        return false;
    }

    // Check if OpenSSL is available
    if ( ! function_exists( 'openssl_decrypt' ) ) {
        // Data was stored unencrypted as fallback
        return $encrypted_data;
    }

    $method = 'AES-256-CBC';

    // Use WordPress salts as encryption key
    $key = hash( 'sha256', wp_salt( 'auth' ) . wp_salt( 'secure_auth' ), true );

    // Decode the base64 data
    $decoded = base64_decode( $encrypted_data, true );

    if ( $decoded === false ) {
        // Not base64 encoded, might be plain text (legacy data)
        return $encrypted_data;
    }

    // Extract IV and encrypted data
    $iv_length = openssl_cipher_iv_length( $method );
    $iv = substr( $decoded, 0, $iv_length );
    $encrypted = substr( $decoded, $iv_length );

    // Decrypt the data
    $decrypted = openssl_decrypt( $encrypted, $method, $key, 0, $iv );

    if ( $decrypted === false ) {
        error_log( '[SMDP] Decryption failed, data may be corrupted or use old format' );
        // Try to return the original data as fallback (might be plain text)
        return $encrypted_data;
    }

    return $decrypted;
}

/**
 * Store encrypted webhook signature key
 *
 * @param string $key Signature key to store
 * @param string $option_name Option name (defaults to main webhook key)
 * @return bool True on success, false on failure
 */
function smdp_store_webhook_key( $key, $option_name = 'smdp_square_webhook_signature_key' ) {
    if ( empty( $key ) ) {
        return false;
    }

    $encrypted = smdp_encrypt( $key );
    if ( $encrypted === false ) {
        error_log( '[SMDP] Failed to encrypt webhook signature key' );
        return false;
    }

    return update_option( $option_name, $encrypted );
}

/**
 * Retrieve and decrypt webhook signature key
 *
 * @param string $option_name Option name (defaults to main webhook key)
 * @return string|false Decrypted key or false on failure
 */
function smdp_get_webhook_key( $option_name = 'smdp_square_webhook_signature_key' ) {
    $encrypted = get_option( $option_name, '' );

    if ( empty( $encrypted ) ) {
        return false;
    }

    return smdp_decrypt( $encrypted );
}

/**
 * Store encrypted Square API access token
 *
 * @param string $token Access token to store
 * @return bool True on success, false on failure
 */
function smdp_store_access_token( $token ) {
    if ( empty( $token ) ) {
        return false;
    }

    $encrypted = smdp_encrypt( $token );
    if ( $encrypted === false ) {
        error_log( '[SMDP] Failed to encrypt access token' );
        return false;
    }

    return update_option( SMDP_ACCESS_TOKEN, $encrypted );
}

/**
 * Retrieve and decrypt Square API access token
 * Checks both OAuth token and manual token (OAuth takes priority)
 *
 * @return string Decrypted access token (empty string if not found)
 */
function smdp_get_access_token() {
    // First check OAuth token (takes priority if both exist)
    $oauth_token = get_option( 'smdp_oauth_access_token', '' );

    if ( ! empty( $oauth_token ) ) {
        $decrypted = smdp_decrypt( $oauth_token );
        if ( $decrypted !== false && ! empty( $decrypted ) ) {
            return $decrypted;
        }
    }

    // Fall back to manual token
    $encrypted = get_option( SMDP_ACCESS_TOKEN, '' );

    if ( empty( $encrypted ) ) {
        return '';
    }

    $decrypted = smdp_decrypt( $encrypted );

    // Return empty string if decryption fails (instead of false)
    return $decrypted !== false ? $decrypted : '';
}

/**
 * -------------------------
 * Rate Limiting Functions
 * -------------------------
 */

/**
 * Check if action is rate limited
 *
 * @param string $action Action identifier (e.g., 'sync_sold_out')
 * @param int    $limit Number of attempts allowed
 * @param int    $period Time period in seconds
 * @return bool True if rate limited (should block), false if allowed
 */
function smdp_is_rate_limited( $action, $limit = 5, $period = 60 ) {
    // Get user identifier (IP + User ID for logged in, or just IP)
    $user_id = get_current_user_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $identifier = $user_id ? "user_{$user_id}" : "ip_{$ip}";

    // Create transient key
    $transient_key = "smdp_rate_limit_{$action}_{$identifier}";

    // Get current attempts
    $attempts = get_transient( $transient_key );

    if ( false === $attempts ) {
        // No attempts yet, start fresh
        set_transient( $transient_key, 1, $period );
        return false; // Not rate limited
    }

    if ( $attempts >= $limit ) {
        // Rate limit exceeded
        error_log( "[SMDP Rate Limit] Action '{$action}' blocked for {$identifier} (attempt {$attempts})" );
        return true; // Rate limited
    }

    // Increment attempts
    set_transient( $transient_key, $attempts + 1, $period );
    return false; // Not rate limited yet
}

/**
 * Reset rate limit for specific action and user
 *
 * @param string $action Action identifier
 * @return bool True on success
 */
function smdp_reset_rate_limit( $action ) {
    $user_id = get_current_user_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $identifier = $user_id ? "user_{$user_id}" : "ip_{$ip}";

    $transient_key = "smdp_rate_limit_{$action}_{$identifier}";
    return delete_transient( $transient_key );
}

/**
 * -------------------------
 * Input Validation Functions
 * -------------------------
 */

/**
 * Sanitize text field with length limit
 *
 * @param string $value Input value to sanitize
 * @param int    $max_length Maximum allowed length (default: 255)
 * @return string Sanitized and length-limited string
 */
function smdp_sanitize_text_field( $value, $max_length = 255 ) {
    // First sanitize
    $sanitized = sanitize_text_field( $value );

    // Then enforce length limit
    if ( strlen( $sanitized ) > $max_length ) {
        $sanitized = substr( $sanitized, 0, $max_length );
        error_log( "[SMDP Input Validation] Input truncated to {$max_length} characters" );
    }

    return $sanitized;
}
