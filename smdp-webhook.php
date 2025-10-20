<?php
/**
 * smdp-webhook.php
 *
 * Handles incoming Square webhooks and auto-syncs the subscription.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1) Register REST routes for the webhook endpoint
add_action( 'rest_api_init', function() {
    register_rest_route( 'smdp/v1', '/webhook', [
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'smdp_handle_webhook',
            'permission_callback' => '__return_true',
        ],
        [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => function() {
                return rest_ensure_response( [ 'alive' => true ] );
            },
            'permission_callback' => '__return_true',
        ],
    ] );
} );

// 2) Handle incoming webhook POSTs
function smdp_handle_webhook( WP_REST_Request $request ) {
    $body      = $request->get_body();
    $signature = $request->get_header( 'x-square-hmacsha256-signature' );
    $url       = rtrim( rest_url( 'smdp/v1/webhook' ), '/' );

    // Verify the webhook signature
    $sig_key = smdp_get_webhook_key();
    if ( empty( $sig_key ) ) {
        error_log( '[SMDP] Webhook rejected: No signature key configured' );
        $response = rest_ensure_response( [
            'success' => false,
            'error' => 'Webhook not configured'
        ] );
        $response->set_status( 500 );
        return $response;
    }

    if ( ! smdp_verify_square_signature( $signature, $body, $url ) ) {
        error_log( '[SMDP] Webhook rejected: Signature verification failed' );
        $response = rest_ensure_response( [
            'success' => false,
            'error' => 'Invalid signature'
        ] );
        $response->set_status( 403 );
        return $response;
    }

    error_log( '[SMDP] Webhook received and verified successfully' );

    $payload = json_decode( $body, true );
    $type    = $payload['type'] ?? '(none)';
    error_log( "[SMDP] Webhook payload type: $type" );

    // Process the webhook immediately for catalog updates
    if ( $type === 'catalog.version.updated' ) {
        error_log( '[SMDP] Detected catalog.version.updated event - triggering immediate sync' );

        // Log function existence check
        if ( function_exists( 'smdp_sync_items' ) ) {
            error_log( '[SMDP] smdp_sync_items() function exists - starting sync' );
            $start_time = microtime(true);

            try {
                smdp_sync_items();
                $duration = microtime(true) - $start_time;
                error_log( sprintf('[SMDP] Webhook sync completed successfully in %.2f seconds', $duration) );
            } catch ( Exception $e ) {
                error_log( '[SMDP] ERROR during sync: ' . $e->getMessage() );
            }
        } else {
            error_log( '[SMDP] ERROR: smdp_sync_items() function not found!' );
            error_log( '[SMDP] Available functions: ' . implode(', ', get_defined_functions()['user']) );
        }
    } else {
        error_log( "[SMDP] Skipping sync for event type: $type" );
    }

    // Respond with 200 OK
    $response = rest_ensure_response( [ 'success' => true, 'type' => $type ] );
    $response->set_status( 200 );

    return $response;
}


    // Verify the HMAC-SHA256 signature
    /**
 * Verify Square HMAC-SHA256 signature, fixing URL-safe base64 & padding.
 */
function smdp_verify_square_signature( $signature, $body, $url ) {
    $signature_key = smdp_get_webhook_key();

    if ( empty( $signature_key ) ) {
        return false;
    }

    // Square's signature key is used AS-IS (not base64-decoded)
    // Compute HMAC-SHA256 over URL + body, then base64 encode the result
    $payload_to_sign = $url . $body;
    $computed = base64_encode( hash_hmac( 'sha256', $payload_to_sign, $signature_key, true ) );

    // Compare signatures
    return hash_equals( $computed, $signature );
}

/**
 * Compute signature (for logging/debugging).
 */
function smdp_compute_signature( $body, $url ) {
    $signature_key = smdp_get_webhook_key();
    if ( empty( $signature_key ) ) {
        return false;
    }
    // Use the signature key as-is (not base64-decoded)
    return base64_encode( hash_hmac( 'sha256', $url . $body, $signature_key, true ) );
}

// 5) Refresh/sync webhook signature keys (doesn't create new webhooks)
function smdp_refresh_webhook_keys() {
    // Check if using OAuth
    $oauth_token = get_option( 'smdp_oauth_access_token', '' );
    if ( ! empty( $oauth_token ) ) {
        error_log( '[SMDP] Webhooks not supported with OAuth authentication.' );
        return false;
    }

    $token      = smdp_get_access_token();
    $notify_url = rtrim( rest_url( 'smdp/v1/webhook' ), '/' );
    $api_ver    = '2025-04-16';
    $base_url   = ( defined('SMDP_ENVIRONMENT') && SMDP_ENVIRONMENT === 'sandbox' )
                  ? 'https://connect.squareupsandbox.com'
                  : 'https://connect.squareup.com';

    if ( empty( $token ) ) {
        error_log( '[SMDP] No Square access token.' );
        return false;
    }

    // List existing subscriptions
    $resp = wp_remote_get( "$base_url/v2/webhooks/subscriptions?include_disabled=true", [
        'headers' => [
            'Authorization'  => "Bearer $token",
            'Square-Version' => $api_ver,
        ],
    ] );

    if ( is_wp_error( $resp ) ) {
        error_log( '[SMDP] List error: ' . $resp->get_error_message() );
        return false;
    }

    $body_data = wp_remote_retrieve_body( $resp );
    $data      = json_decode( $body_data, true ) ?: [];
    $subs      = $data['subscriptions'] ?? [];

    // Check for our subscription
    foreach ( $subs as $sub ) {
        if ( in_array( 'catalog.version.updated', (array) $sub['event_types'], true )
          && rtrim( $sub['notification_url'], '/' ) === $notify_url ) {
            // Retrieve details to get signature_key
            $detail = wp_remote_get( "$base_url/v2/webhooks/subscriptions/{$sub['id']}", [
                'headers' => [
                    'Authorization'  => "Bearer $token",
                    'Square-Version' => $api_ver,
                ],
            ] );

            if ( wp_remote_retrieve_response_code( $detail ) === 200 ) {
                $j   = json_decode( wp_remote_retrieve_body( $detail ), true );
                $key = $j['subscription']['signature_key'] ?? '';

                if ( $key ) {
                    smdp_store_webhook_key( $key );
                    error_log( '[SMDP] Webhook signature key refreshed successfully' );
                    return true;
                }
            }
        }
    }

    // No webhook found - don't create one
    error_log( '[SMDP] No webhook found to refresh. Use "Activate Webhooks" to create one.' );
    return false;
}

// 6) Auto-create or sync subscription
function smdp_ensure_webhook_subscription( $force = false ) {
    // Check if using OAuth - webhooks are application-level and not supported with OAuth
    $oauth_token = get_option( 'smdp_oauth_access_token', '' );
    if ( ! empty( $oauth_token ) ) {
        error_log( '[SMDP] Webhooks not supported with OAuth authentication. Skipping webhook subscription.' );
        return;
    }

    // Check if we recently verified the webhook (cache for 24 hours)
    $cache_key = 'smdp_webhook_verified';
    $last_check = get_transient( $cache_key );

    if ( ! $force && $last_check ) {
        // Already verified within the last 24 hours, skip
        return;
    }

    error_log( '[SMDP] ensure_webhook_subscription() called @ ' . date_i18n( 'c' ) );

    $token      = smdp_get_access_token();
    $notify_url = rtrim( rest_url( 'smdp/v1/webhook' ), '/' );
    $api_ver    = '2025-04-16';
    $base_url   = ( defined('SMDP_ENVIRONMENT') && SMDP_ENVIRONMENT === 'sandbox' )
                  ? 'https://connect.squareupsandbox.com'
                  : 'https://connect.squareup.com';

    if ( empty( $token ) ) {
        error_log( '[SMDP] No Square access token.' );
        return;
    }

    // List existing subscriptions
    $resp = wp_remote_get( "$base_url/v2/webhooks/subscriptions?include_disabled=true", [
        'headers' => [
            'Authorization'  => "Bearer $token",
            'Square-Version' => $api_ver,
        ],
    ] );
    if ( is_wp_error( $resp ) ) {
        error_log( '[SMDP] List error: ' . $resp->get_error_message() );
        return;
    }
    $body_data = wp_remote_retrieve_body( $resp );
    $data      = json_decode( $body_data, true ) ?: [];
    $subs      = $data['subscriptions'] ?? [];

    // Check for our subscription
    foreach ( $subs as $sub ) {
        if ( in_array( 'catalog.version.updated', (array) $sub['event_types'], true )
          && rtrim( $sub['notification_url'], '/' ) === $notify_url ) {
            // Retrieve details to get signature_key
            $detail = wp_remote_get( "$base_url/v2/webhooks/subscriptions/{$sub['id']}", [
                'headers' => [
                    'Authorization'  => "Bearer $token",
                    'Square-Version' => $api_ver,
                ],
            ] );
            $detail_code = wp_remote_retrieve_response_code( $detail );
            $detail_body = wp_remote_retrieve_body( $detail );
            error_log( '[SMDP] Webhook detail API response code: ' . $detail_code );

            if ( $detail_code === 200 ) {
                $j   = json_decode( $detail_body, true );
                error_log( '[SMDP] Full webhook subscription object: ' . print_r($j, true) );
                $key = $j['subscription']['signature_key'] ?? '';
                error_log( '[SMDP] Retrieved signature key from webhook details: ' . substr($key, 0, 20) . '... (length: ' . strlen($key) . ')' );
                error_log( '[SMDP] Full signature key: ' . $key );
                if ( $key ) {
                    $store_result = smdp_store_webhook_key( $key );
                    error_log( '[SMDP] Store webhook key result: ' . ($store_result ? 'SUCCESS' : 'FAILED') );

                    // Verify it was stored correctly
                    $retrieved = smdp_get_webhook_key();
                    if ( $retrieved === $key ) {
                        error_log( '[SMDP] ✓ Webhook key stored and verified successfully' );
                    } else {
                        error_log( '[SMDP] ✗ WARNING: Stored key does not match! Retrieved: ' . substr($retrieved, 0, 20) . '...' );
                    }
                } else {
                    error_log( '[SMDP] ERROR: No signature_key found in webhook details response' );
                }
            } else {
                error_log( '[SMDP] Failed to retrieve webhook details. HTTP code: ' . wp_remote_retrieve_response_code( $detail ) );
            }
            // Cache that we verified the webhook (24 hours)
            set_transient( $cache_key, time(), DAY_IN_SECONDS );
            error_log( '[SMDP] Webhook verified and cached for 24 hours' );
            return;
        }
    }

    // Create new if not found
    $new_data = [
        'idempotency_key' => uniqid( 'smdp_wh_', true ),
        'subscription'    => [
            'name'             => 'SMDP Catalog Version Updated',
            'event_types'      => [ 'catalog.version.updated' ],
            'notification_url' => $notify_url,
            'api_version'      => $api_ver,
        ],
    ];
    $create = wp_remote_post( "$base_url/v2/webhooks/subscriptions", [
        'headers' => [
            'Authorization'  => "Bearer $token",
            'Square-Version' => $api_ver,
            'Content-Type'   => 'application/json',
        ],
        'body'    => wp_json_encode( $new_data ),
    ] );
    if ( is_wp_error( $create ) ) {
        error_log( '[SMDP] Create error: ' . $create->get_error_message() );
        return;
    }
    $create_code = wp_remote_retrieve_response_code( $create );
    if ( 200 !== $create_code && 201 !== $create_code ) {
        error_log( "[SMDP] Create webhook failed with HTTP code: $create_code" );
        error_log( '[SMDP] Response body: ' . wp_remote_retrieve_body( $create ) );
        return;
    }
    $res = json_decode( wp_remote_retrieve_body( $create ), true );
    $k   = $res['subscription']['signature_key'] ?? '';
    error_log( '[SMDP] Created webhook signature key: ' . substr($k, 0, 20) . '... (length: ' . strlen($k) . ')' );
    if ( $k ) {
        $store_result = smdp_store_webhook_key( $k );
        error_log( '[SMDP] Webhook subscription created and signature key saved: ' . ($store_result ? 'SUCCESS' : 'FAILED') );

        // Verify it was stored correctly
        $retrieved = smdp_get_webhook_key();
        if ( $retrieved === $k ) {
            error_log( '[SMDP] ✓ Webhook key stored and verified successfully' );
        } else {
            error_log( '[SMDP] ✗ WARNING: Stored key does not match! Retrieved: ' . substr($retrieved, 0, 20) . '...' );
        }
    }

    // Cache that we verified the webhook (24 hours)
    set_transient( $cache_key, time(), DAY_IN_SECONDS );
    error_log( '[SMDP] Webhook created and cached for 24 hours' );
}

// Add cron action for webhook sync
add_action( 'smdp_webhook_sync', function() {
    error_log( '[SMDP] Processing webhook sync via cron at ' . date_i18n( 'c' ) );
    if ( function_exists( 'smdp_sync_items' ) ) {
        smdp_sync_items();
        error_log( '[SMDP] Webhook sync completed' );
    } else {
        error_log( '[SMDP] ERROR: smdp_sync_items() function not found!' );
    }
} );

// Webhook subscription is now only created manually via admin page buttons
// Removed automatic admin_init hook to prevent excessive API calls
