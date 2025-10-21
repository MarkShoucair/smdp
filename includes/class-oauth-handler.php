<?php
/**
 * OAuth Handler Class
 *
 * Handles Square OAuth 2.0 authentication flow including:
 * - Authorization URL generation
 * - Token exchange (authorization code for access/refresh tokens)
 * - Token refresh
 * - Secure token storage
 *
 * @package Square_Menu_Display
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SMDP_OAuth_Handler {

    /**
     * Option keys for OAuth credentials
     */
    const OPT_APP_ID          = 'smdp_oauth_app_id';
    const OPT_APP_SECRET      = 'smdp_oauth_app_secret';
    const OPT_ACCESS_TOKEN    = 'smdp_oauth_access_token';
    const OPT_REFRESH_TOKEN   = 'smdp_oauth_refresh_token';
    const OPT_TOKEN_EXPIRES   = 'smdp_oauth_token_expires';
    const OPT_MERCHANT_ID     = 'smdp_oauth_merchant_id';

    /**
     * Square OAuth URLs
     */
    const SQUARE_AUTH_URL     = 'https://connect.squareup.com/oauth2/authorize';
    const SQUARE_TOKEN_URL    = 'https://connect.squareup.com/oauth2/token';
    const SQUARE_REVOKE_URL   = 'https://connect.squareup.com/oauth2/revoke';

    /**
     * Singleton instance
     *
     * @var SMDP_OAuth_Handler
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return SMDP_OAuth_Handler
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'init', array( $this, 'handle_oauth_callback' ) );
        add_action( 'admin_init', array( $this, 'maybe_refresh_token' ) );
    }

    /**
     * Get Square App ID from database
     *
     * @return string|false
     */
    private function get_app_id() {
        return get_option( self::OPT_APP_ID );
    }

    /**
     * Get Square App Secret from database (encrypted)
     *
     * @return string|false
     */
    private function get_app_secret() {
        return smdp_get_encrypted_option( self::OPT_APP_SECRET );
    }

    /**
     * Get the OAuth callback URL
     *
     * @return string
     */
    public function get_callback_url() {
        return admin_url( 'admin.php?page=smdp_main&oauth_callback=1' );
    }

    /**
     * Get the authorization URL for Square OAuth
     *
     * @return string|false Authorization URL or false if app ID not set
     */
    public function get_authorization_url() {
        $app_id = $this->get_app_id();

        if ( empty( $app_id ) ) {
            return false;
        }

        // Generate state for CSRF protection
        $state = wp_create_nonce( 'smdp_oauth_state' );
        set_transient( 'smdp_oauth_state_' . get_current_user_id(), $state, 600 ); // 10 minute expiry

        $params = array(
            'client_id'    => $app_id,
            'scope'        => 'MERCHANT_PROFILE_READ ITEMS_READ ITEMS_WRITE ORDERS_READ ORDERS_WRITE PAYMENTS_WRITE',
            'session'      => 'false', // Use code flow (not PKCE)
            'state'        => $state,
            'redirect_uri' => $this->get_callback_url(),
        );

        return self::SQUARE_AUTH_URL . '?' . http_build_query( $params );
    }

    /**
     * Handle OAuth callback from Square
     */
    public function handle_oauth_callback() {
        // Check if this is an OAuth callback
        if ( ! isset( $_GET['oauth_callback'] ) || ! isset( $_GET['code'] ) ) {
            return;
        }

        error_log( '[SMDP OAuth] Callback received! Processing authorization code...' );

        // Verify user is admin
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( '[SMDP OAuth] Callback failed - user not authorized' );
            wp_die( 'Unauthorized', 'Authorization Error', array( 'response' => 403 ) );
        }

        // Verify state (CSRF protection)
        $state = isset( $_GET['state'] ) ? sanitize_text_field( $_GET['state'] ) : '';
        $stored_state = get_transient( 'smdp_oauth_state_' . get_current_user_id() );
        delete_transient( 'smdp_oauth_state_' . get_current_user_id() );

        if ( empty( $state ) || $state !== $stored_state ) {
            wp_die( 'Invalid state parameter. Please try again.', 'OAuth Error', array( 'response' => 400 ) );
        }

        // Exchange authorization code for access token
        $code = sanitize_text_field( $_GET['code'] );
        $result = $this->exchange_code_for_token( $code );

        if ( is_wp_error( $result ) ) {
            wp_die(
                'Failed to obtain access token: ' . esc_html( $result->get_error_message() ),
                'OAuth Error',
                array( 'response' => 500 )
            );
        }

        // Redirect back to settings page with success message
        wp_redirect( admin_url( 'admin.php?page=smdp_main&oauth_success=1' ) );
        exit;
    }

    /**
     * Exchange authorization code for access and refresh tokens
     *
     * @param string $code Authorization code from Square
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function exchange_code_for_token( $code ) {
        $app_id = $this->get_app_id();
        $app_secret = $this->get_app_secret();

        // SECURITY: Only log sensitive data in debug mode
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[SMDP OAuth] App ID retrieved: ' . ( $app_id ? 'YES' : 'NO' ) );
            error_log( '[SMDP OAuth] App Secret retrieved: ' . ( $app_secret ? 'YES' : 'NO' ) );
            error_log( '[SMDP OAuth] Redirect URI: ' . $this->get_callback_url() );
        }

        if ( empty( $app_id ) || empty( $app_secret ) ) {
            error_log( '[SMDP OAuth] ERROR: Missing credentials! App ID or Secret not configured' );
            return new WP_Error( 'missing_credentials', 'OAuth app ID or secret not configured' );
        }

        $response = wp_remote_post( self::SQUARE_TOKEN_URL, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Square-Version' => '2024-01-18',
            ),
            'body' => wp_json_encode( array(
                'client_id'     => $app_id,
                'client_secret' => $app_secret,
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $this->get_callback_url(),
            ) ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( '[SMDP OAuth] Token exchange failed: ' . $response->get_error_message() );
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // SECURITY: Only log API responses in debug mode
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[SMDP OAuth] Square API Response Code: ' . $response_code );
        }

        $body = json_decode( $response_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log( '[SMDP OAuth] JSON decode error: ' . json_last_error_msg() );
            return new WP_Error( 'json_decode_failed', 'Failed to parse Square API response' );
        }

        if ( isset( $body['error'] ) ) {
            error_log( '[SMDP OAuth] Token exchange error: ' . $body['error'] );
            if ( isset( $body['error_description'] ) ) {
                error_log( '[SMDP OAuth] Error description: ' . $body['error_description'] );
            }
            return new WP_Error( 'token_exchange_failed', $body['error_description'] ?? $body['error'] );
        }

        // Store tokens securely
        if ( isset( $body['access_token'] ) ) {
            $store_result = smdp_store_encrypted_option( self::OPT_ACCESS_TOKEN, $body['access_token'] );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[SMDP OAuth] Access token stored: ' . ( $store_result ? 'SUCCESS' : 'FAILED' ) );
            }

            if ( ! $store_result ) {
                error_log( '[SMDP OAuth] CRITICAL: Failed to store access token!' );
            }
        } else {
            error_log( '[SMDP OAuth] ERROR: No access_token in response body!' );
        }

        if ( isset( $body['refresh_token'] ) ) {
            $store_result = smdp_store_encrypted_option( self::OPT_REFRESH_TOKEN, $body['refresh_token'] );

            if ( ! $store_result ) {
                error_log( '[SMDP OAuth] WARNING: Failed to store refresh token' );
            }
        }

        if ( isset( $body['expires_at'] ) ) {
            update_option( self::OPT_TOKEN_EXPIRES, $body['expires_at'] );
        }

        if ( isset( $body['merchant_id'] ) ) {
            update_option( self::OPT_MERCHANT_ID, $body['merchant_id'] );
        }

        error_log( '[SMDP OAuth] Token exchange successful' );
        return true;
    }

    /**
     * Refresh the access token using the refresh token
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function refresh_access_token() {
        $app_id = $this->get_app_id();
        $app_secret = $this->get_app_secret();
        $refresh_token = smdp_get_encrypted_option( self::OPT_REFRESH_TOKEN );

        if ( empty( $app_id ) || empty( $app_secret ) || empty( $refresh_token ) ) {
            return new WP_Error( 'missing_credentials', 'OAuth credentials or refresh token not available' );
        }

        $response = wp_remote_post( self::SQUARE_TOKEN_URL, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Square-Version' => '2024-01-18',
            ),
            'body' => wp_json_encode( array(
                'client_id'     => $app_id,
                'client_secret' => $app_secret,
                'refresh_token' => $refresh_token,
                'grant_type'    => 'refresh_token',
            ) ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( '[SMDP OAuth] Token refresh failed: ' . $response->get_error_message() );
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            error_log( '[SMDP OAuth] Token refresh error: ' . $body['error'] );
            return new WP_Error( 'token_refresh_failed', $body['error_description'] ?? $body['error'] );
        }

        // Store new access token
        if ( isset( $body['access_token'] ) ) {
            smdp_store_encrypted_option( self::OPT_ACCESS_TOKEN, $body['access_token'] );
        }

        // Update refresh token if provided (though typically stays the same in code flow)
        if ( isset( $body['refresh_token'] ) ) {
            smdp_store_encrypted_option( self::OPT_REFRESH_TOKEN, $body['refresh_token'] );
        }

        if ( isset( $body['expires_at'] ) ) {
            update_option( self::OPT_TOKEN_EXPIRES, $body['expires_at'] );
        }

        error_log( '[SMDP OAuth] Token refresh successful' );
        return true;
    }

    /**
     * Check if token needs refresh and refresh if necessary
     * Called on admin_init
     */
    public function maybe_refresh_token() {
        // Only check once per hour to avoid excessive checks
        $last_check = get_transient( 'smdp_oauth_last_refresh_check' );
        if ( $last_check ) {
            return;
        }
        set_transient( 'smdp_oauth_last_refresh_check', time(), HOUR_IN_SECONDS );

        $expires_at = get_option( self::OPT_TOKEN_EXPIRES );

        if ( empty( $expires_at ) ) {
            return; // No OAuth token, nothing to refresh
        }

        // Refresh if token expires in less than 7 days
        $seven_days = 7 * DAY_IN_SECONDS;
        $time_until_expiry = strtotime( $expires_at ) - time();

        if ( $time_until_expiry < $seven_days ) {
            error_log( '[SMDP OAuth] Token expiring soon, attempting refresh...' );
            $this->refresh_access_token();
        }
    }

    /**
     * Revoke the current OAuth authorization
     *
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function revoke_authorization() {
        $app_id = $this->get_app_id();
        $app_secret = $this->get_app_secret();
        $access_token = smdp_get_encrypted_option( self::OPT_ACCESS_TOKEN );

        if ( empty( $app_id ) || empty( $app_secret ) || empty( $access_token ) ) {
            return new WP_Error( 'missing_credentials', 'OAuth credentials not available' );
        }

        $response = wp_remote_post( self::SQUARE_REVOKE_URL, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Square-Version' => '2024-01-18',
            ),
            'body' => wp_json_encode( array(
                'client_id'     => $app_id,
                'client_secret' => $app_secret,
                'access_token'  => $access_token,
            ) ),
            'timeout' => 30,
        ) );

        // Clear stored tokens regardless of API response
        delete_option( self::OPT_ACCESS_TOKEN );
        delete_option( self::OPT_REFRESH_TOKEN );
        delete_option( self::OPT_TOKEN_EXPIRES );
        delete_option( self::OPT_MERCHANT_ID );

        if ( is_wp_error( $response ) ) {
            error_log( '[SMDP OAuth] Token revocation failed: ' . $response->get_error_message() );
            return $response;
        }

        error_log( '[SMDP OAuth] Authorization revoked successfully' );
        return true;
    }

    /**
     * Check if OAuth is currently authorized
     *
     * @return bool
     */
    public function is_authorized() {
        $access_token = smdp_get_encrypted_option( self::OPT_ACCESS_TOKEN );
        $is_authorized = ! empty( $access_token );

        // Debug logging (only log occasionally to avoid spam)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && isset( $_GET['page'] ) && $_GET['page'] === 'smdp_main' ) {
            error_log( '[SMDP OAuth] Authorization check: ' . ( $is_authorized ? 'AUTHORIZED' : 'NOT AUTHORIZED' ) );
        }

        return $is_authorized;
    }

    /**
     * Get the current OAuth access token
     *
     * @return string|false Access token or false if not available
     */
    public function get_access_token() {
        return smdp_get_encrypted_option( self::OPT_ACCESS_TOKEN );
    }

    /**
     * Get token expiration info for display
     *
     * @return array Array with 'expires_at', 'days_remaining', 'expired' keys
     */
    public function get_token_info() {
        $expires_at = get_option( self::OPT_TOKEN_EXPIRES );

        if ( empty( $expires_at ) ) {
            return array(
                'expires_at' => null,
                'days_remaining' => null,
                'expired' => false,
            );
        }

        $expiry_timestamp = strtotime( $expires_at );
        $time_remaining = $expiry_timestamp - time();
        $days_remaining = floor( $time_remaining / DAY_IN_SECONDS );

        return array(
            'expires_at' => $expires_at,
            'days_remaining' => max( 0, $days_remaining ),
            'expired' => $time_remaining < 0,
        );
    }
}

// Helper functions for encrypted option storage

if ( ! function_exists( 'smdp_get_encrypted_option' ) ) {
    /**
     * Get and decrypt an option value
     *
     * @param string $option_name Option name
     * @return string|false Decrypted value or false
     */
    function smdp_get_encrypted_option( $option_name ) {
        $encrypted = get_option( $option_name );

        if ( empty( $encrypted ) ) {
            return false;
        }

        // Use existing encryption function if available
        if ( function_exists( 'smdp_decrypt' ) ) {
            return smdp_decrypt( $encrypted );
        }

        return $encrypted; // Fallback to plain text
    }
}

if ( ! function_exists( 'smdp_store_encrypted_option' ) ) {
    /**
     * Encrypt and store an option value
     *
     * @param string $option_name Option name
     * @param string $value Value to encrypt and store
     * @return bool True on success, false on failure
     */
    function smdp_store_encrypted_option( $option_name, $value ) {
        if ( empty( $value ) ) {
            return delete_option( $option_name );
        }

        // Use existing encryption function if available
        if ( function_exists( 'smdp_encrypt' ) ) {
            $encrypted = smdp_encrypt( $value );

            if ( $encrypted === false ) {
                error_log( "[SMDP OAuth] CRITICAL: Encryption failed for $option_name" );
                return false;
            }

            $result = update_option( $option_name, $encrypted );

            // Only log failures, not successes (reduce log noise)
            if ( ! $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "[SMDP OAuth] WARNING: Failed to update option $option_name" );
            }

            return $result;
        }

        error_log( "[SMDP OAuth] WARNING: Encryption function not available, storing plain text for $option_name" );
        return update_option( $option_name, $value ); // Fallback to plain text
    }
}

// Initialize OAuth handler
SMDP_OAuth_Handler::instance();
