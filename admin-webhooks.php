<?php
/**
 * admin-webhooks.php
 *
 * WP-Admin page to automatically manage Square webhook subscriptions.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Square\SquareClient;
use Square\Environments;
use Square\Webhooks\Subscriptions\Requests\CreateWebhookSubscriptionRequest;
use Square\Types\WebhookSubscription;

/**
 * Render the Webhooks admin page: list existing & create new, auto-sync keys.
 */
function smdp_render_webhooks_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Handle "Refresh Webhooks" button click
    if ( isset( $_POST['smdp_refresh_webhook_nonce'] )
      && wp_verify_nonce( $_POST['smdp_refresh_webhook_nonce'], 'smdp_refresh_webhook' ) ) {
        if ( function_exists( 'smdp_ensure_webhook_subscription' ) ) {
            smdp_ensure_webhook_subscription( true ); // Force refresh
            echo '<div class="updated"><p><strong>Webhooks refreshed successfully!</strong> Signature keys have been synced.</p></div>';
        }
    }

    // Handle "Activate Webhooks" button click (one-time setup)
    if ( isset( $_POST['smdp_activate_webhook_nonce'] )
      && wp_verify_nonce( $_POST['smdp_activate_webhook_nonce'], 'smdp_activate_webhook' ) ) {
        if ( function_exists( 'smdp_ensure_webhook_subscription' ) ) {
            // Check if webhook already exists
            $already_exists = false;
            $token = smdp_get_access_token();
            $api_ver = '2025-04-16';
            $base_url = ( defined('SMDP_ENVIRONMENT') && SMDP_ENVIRONMENT === 'sandbox' )
                      ? 'https://connect.squareupsandbox.com'
                      : 'https://connect.squareup.com';
            $notify_url = rtrim( rest_url( 'smdp/v1/webhook' ), '/' );

            if ( ! empty( $token ) ) {
                $resp = wp_remote_get( "$base_url/v2/webhooks/subscriptions?include_disabled=true", [
                    'headers' => [
                        'Authorization'  => "Bearer $token",
                        'Square-Version' => $api_ver,
                    ],
                ] );

                if ( ! is_wp_error( $resp ) ) {
                    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
                    $subs = $data['subscriptions'] ?? [];

                    foreach ( $subs as $sub ) {
                        if ( in_array( 'catalog.version.updated', (array) $sub['event_types'], true )
                          && rtrim( $sub['notification_url'], '/' ) === $notify_url ) {
                            $already_exists = true;
                            break;
                        }
                    }
                }
            }

            if ( $already_exists ) {
                echo '<div class="notice notice-info"><p><strong>Webhook already exists!</strong> No new webhook was created. Use "Refresh Webhooks" to sync signature keys.</p></div>';
            } else {
                // Force create new webhook
                smdp_ensure_webhook_subscription( true );
                echo '<div class="updated"><p><strong>Webhook activated successfully!</strong> Your catalog will now sync automatically when changes are made in Square.</p></div>';
            }
        }
    }

    $token    = smdp_get_access_token();
    $api_ver  = '2025-04-16';
    $base_url = ( defined('SMDP_ENVIRONMENT') && SMDP_ENVIRONMENT === 'sandbox'
                ) ? 'https://connect.squareupsandbox.com'
                  : 'https://connect.squareup.com';

    echo '<div class="wrap"><h1>Square Webhooks</h1>';

    // Check if using OAuth authentication
    $oauth_token = get_option( 'smdp_oauth_access_token', '' );
    if ( ! empty( $oauth_token ) ) {
        echo '<div class="notice notice-warning" style="padding:15px;margin:20px 0;">';
        echo '<h2 style="margin-top:0;">⚠️ Webhooks Not Available with OAuth</h2>';
        echo '<p><strong>Square webhook subscriptions are application-level and require a personal access token.</strong></p>';
        echo '<p>You are currently using OAuth authentication, which provides merchant-specific tokens. To use webhooks:</p>';
        echo '<ol>';
        echo '<li>Go to <strong>Square Menu Settings</strong></li>';
        echo '<li>Disconnect from OAuth (click "Disconnect from Square")</li>';
        echo '<li>Enter a <strong>personal access token</strong> from your Square Developer Dashboard</li>';
        echo '<li>Save settings and return here to configure webhooks</li>';
        echo '</ol>';
        echo '<p><em>Note: Automatic catalog syncing via webhooks will only work with personal access tokens.</em></p>';
        echo '</div>';
        echo '</div>'; // Close wrap
        return; // Stop rendering the rest of the page
    }

    // Always show webhooks (removed button requirement for better UX)
    $show_webhooks = true;

    // Webhook action buttons
    echo '<div style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
    echo '<h2 style="margin-top: 0;">Webhook Actions</h2>';
    echo '<p>Use these buttons to manage your webhook subscriptions:</p>';

    // Activate Webhooks button
    echo '<form method="post" style="display: inline-block; margin-right: 10px;">';
    wp_nonce_field( 'smdp_activate_webhook', 'smdp_activate_webhook_nonce' );
    echo '<button type="submit" class="button button-primary" style="height: 40px; font-size: 14px;">';
    echo '<span class="dashicons dashicons-yes" style="margin-top: 7px;"></span> Activate Webhooks (One-Time Setup)';
    echo '</button>';
    echo '</form>';

    // Refresh Webhooks button
    echo '<form method="post" style="display: inline-block;">';
    wp_nonce_field( 'smdp_refresh_webhook', 'smdp_refresh_webhook_nonce' );
    echo '<button type="submit" class="button button-secondary" style="height: 40px; font-size: 14px;">';
    echo '<span class="dashicons dashicons-update" style="margin-top: 7px;"></span> Refresh Webhooks';
    echo '</button>';
    echo '</form>';

    echo '<p style="margin-top: 15px;"><em>';
    echo '<strong>Activate Webhooks:</strong> Creates the webhook subscription automatically if it doesn\'t exist. Only needs to be done once.<br>';
    echo '<strong>Refresh Webhooks:</strong> Syncs the signature keys from Square. <strong>Use this if you\'re getting 403 errors in Square webhook logs.</strong>';
    echo '</em></p>';

    // Check if we have a stored webhook key
    $stored_key = smdp_get_webhook_key();
    if ( empty( $stored_key ) ) {
        echo '<div class="notice notice-warning" style="margin-top:15px; padding:10px;">';
        echo '<p><strong>⚠️ Warning:</strong> No webhook signature key is currently stored. Webhooks from Square will be rejected with 403 errors.</p>';
        echo '<p><strong>Action Required:</strong> Click "Refresh Webhooks" above to sync the signature key from your existing webhook subscription.</p>';
        echo '</div>';
    } else {
        echo '<div class="notice notice-success" style="margin-top:15px; padding:10px;">';
        echo '<p><strong>✓ Webhook signature key is configured.</strong></p>';
        echo '<p>Key (first 20 chars): <code>' . esc_html( substr($stored_key, 0, 20) ) . '...</code> (Length: ' . strlen($stored_key) . ' characters)</p>';
        echo '<details style="margin-top:10px;">';
        echo '<summary style="cursor:pointer; color:#2271b1;"><strong>Show full key for debugging</strong></summary>';
        echo '<pre style="background:#f5f5f5; padding:10px; margin-top:10px; overflow:auto; font-size:11px;">' . esc_html($stored_key) . '</pre>';
        echo '</details>';
        echo '<p style="margin-top:10px;">If Square webhook logs show 403 errors, click "Refresh Webhooks" to re-sync the key.</p>';
        echo '</div>';
    }

    echo '</div>';

    // List existing subscriptions
    echo '<h2>Existing Webhook Subscriptions</h2>';
    if ( empty( $token ) ) {
        echo '<div class="error"><p>No access token set. Please configure your token in Settings.</p></div>';
    } else {
        $resp = wp_remote_get( "$base_url/v2/webhooks/subscriptions?include_disabled=true", [
            'headers' => [
                'Authorization'  => "Bearer $token",
                'Square-Version' => $api_ver,
            ],
        ] );
        if ( is_wp_error( $resp ) ) {
            echo '<div class="error"><p>HTTP error: ' . esc_html( $resp->get_error_message() ) . '</p></div>';
        } else {
                $code = wp_remote_retrieve_response_code( $resp );
                $body = wp_remote_retrieve_body( $resp );
                $data = json_decode( $body, true );
                $subs = $data['subscriptions'] ?? [];
                if ( 200 !== $code ) {
                    echo '<div class="error"><p><strong>Square API Error (HTTP ' . esc_html( $code ) . ')</strong></p>';
                    if ( $code === 403 ) {
                        echo '<p><strong>Permission Denied (403 Forbidden)</strong></p>';
                        echo '<p>This error usually means:</p>';
                        echo '<ul style="margin-left:20px;">';
                        echo '<li>You are using an OAuth token instead of a personal access token</li>';
                        echo '<li>Your access token does not have the required webhook permissions</li>';
                        echo '<li>Your access token does not have application-level access</li>';
                        echo '</ul>';
                        echo '<p><strong>To fix this:</strong></p>';
                        echo '<ol style="margin-left:20px;">';
                        echo '<li>Go to your Square Developer Dashboard</li>';
                        echo '<li>Generate a new <strong>Personal Access Token</strong> (not OAuth)</li>';
                        echo '<li>Ensure it has "Webhooks" permission enabled</li>';
                        echo '<li>Disconnect OAuth in Settings and use the personal token instead</li>';
                        echo '</ol>';
                    }
                    if ( !empty($data['errors']) ) {
                        echo '<p><strong>Error details:</strong></p><pre style="background:#f5f5f5; padding:10px; overflow:auto;">' . esc_html( print_r( $data['errors'], true ) ) . '</pre>';
                    } else {
                        echo '<p><strong>Raw response:</strong></p><pre style="background:#f5f5f5; padding:10px; overflow:auto;">' . esc_html( $body ) . '</pre>';
                    }
                    echo '</div>';
                } elseif ( $subs ) {
                    echo '<table class="widefat fixed striped"><thead><tr>'
                       . '<th>ID</th><th>Events</th><th>URL</th><th>Signature Key</th><th>Created</th></tr></thead><tbody>';
                    foreach ( $subs as $sub ) {
                        // Check if this is the catalog.version.updated webhook
                        $is_catalog_webhook = in_array('catalog.version.updated', $sub['event_types'], true);
                        $stored = $is_catalog_webhook
                            ? smdp_get_webhook_key()
                            : smdp_get_webhook_key( "smdp_webhook_signature_key_{$sub['id']}" );
                        echo '<tr>'
                           . '<td>' . esc_html( $sub['id'] ) . '</td>'
                           . '<td>' . esc_html( implode(', ', $sub['event_types']) ) . '</td>'
                           . '<td><code>' . esc_url( $sub['notification_url'] ) . '</code></td>'
                           . '<td><code>' . esc_html( $stored ? substr($stored, 0, 20) . '...' : '(not stored)' ) . '</code></td>'
                           . '<td>' . esc_html( $sub['created_at'] ) . '</td>'
                           . '</tr>';
                    }
                    echo '</tbody></table>';
            } else {
                echo '<p>No webhook subscriptions found. Click "Activate Webhooks" above to create one.</p>';
            }
        }
    }

    // Handle creation form submission
    if ( isset( $_POST['smdp_new_webhook_nonce'] )
      && wp_verify_nonce( $_POST['smdp_new_webhook_nonce'], 'smdp_new_webhook' ) ) {
        $event = sanitize_text_field( $_POST['event_type'] );
        $url   = esc_url_raw( $_POST['notification_url'] );
        $client = new SquareClient([
            'accessToken' => $token,
            'environment' => Environments::Production,
        ]);
        $sub_obj = new WebhookSubscription([
            'name'            => 'SMDP ' . $event,
            'eventTypes'      => [ $event ],
            'notificationUrl' => $url,
            'apiVersion'      => $api_ver,
            'enabled'         => true,
        ]);
        $req = new CreateWebhookSubscriptionRequest([
            'idempotencyKey' => uniqid('smdp_wh_', true),
            'subscription'   => $sub_obj,
        ]);
        $resp = $client->webhooks->subscriptions->create( $req );
        if ( $errors = $resp->getErrors() ) {
            echo '<div class="error"><p><strong>Error:</strong><br>';
            foreach ( $errors as $e ) {
                echo esc_html( $e->getCategory() . ': ' . $e->getDetail() ) . '<br>';
            }
            echo '</p></div>';
        } else {
            $new = $resp->getResult()->getSubscription();
            $new_id = $new->getId();
            $new_key = $new->getSignatureKey();
            // Store with catalog-specific key if it's a catalog webhook (encrypted)
            if ( $event === 'catalog.version.updated' ) {
                smdp_store_webhook_key( $new_key );
            } else {
                smdp_store_webhook_key( $new_key, "smdp_webhook_signature_key_{$new_id}" );
            }
            echo '<div class="updated"><p>Created and synced webhook: ' . esc_html( $new_id ) . '</p></div>';
            echo '<meta http-equiv="refresh" content="0">';
            return;
        }
    }

    // Render creation form
    echo '<h2>Create New Webhook</h2>';
    echo '<form method="post">';
    wp_nonce_field( 'smdp_new_webhook', 'smdp_new_webhook_nonce' );
    echo '<table class="form-table">'
       . '<tr><th><label for="event_type">Event Type</label></th>'
       . '<td><input name="event_type" type="text" id="event_type"'
       . ' value="catalog.version.updated" class="regular-text" /></td></tr>'
       . '<tr><th><label for="notification_url">Notification URL</label></th>'
       . '<td><input name="notification_url" type="text" id="notification_url"'
       . ' value="' . esc_attr( rest_url( 'smdp/v1/webhook' ) ) . '" class="regular-text" /></td></tr>'
       . '</table>';
    submit_button( 'Create Webhook' );
    echo '</form>';

    echo '</div>';
}
