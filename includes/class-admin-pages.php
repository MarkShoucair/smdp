<?php
/**
 * Admin Pages Class
 *
 * Handles all admin page rendering for the plugin.
 *
 * @package Square_Menu_Display
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SMDP_Admin_Pages Class
 *
 * Manages admin menu registration and page rendering.
 */
class SMDP_Admin_Pages {

    /**
     * Instance of this class
     *
     * @var SMDP_Admin_Pages
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return SMDP_Admin_Pages
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Register hooks
     */
    private function __construct() {
        $this->register_hooks();
    }

    /**
     * Register all admin page hooks
     */
    private function register_hooks() {
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_oauth_redirect' ) );
    }

    /**
     * Handle OAuth redirect before any output (to avoid headers already sent error)
     */
    public function handle_oauth_redirect() {
        // Only process on our settings page
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'smdp_main' ) {
            return;
        }

        // Handle OAuth connect button - must redirect before any output
        if ( isset( $_POST['smdp_oauth_connect'] ) && check_admin_referer( 'smdp_oauth_connect', 'smdp_oauth_nonce', false ) ) {
            $oauth = SMDP_OAuth_Handler::instance();
            $auth_url = $oauth->get_authorization_url();

            if ( $auth_url ) {
                wp_redirect( $auth_url );
                exit;
            }
        }
    }

    /**
     * Register admin menu and submenu pages
     */
    public function register_admin_menu() {
        add_menu_page(
            'Square Menu Settings',
            'Square Menu',
            'manage_options',
            'smdp_main',
            array( $this, 'render_settings_page' ),
            'dashicons-list-view'
        );

        add_submenu_page(
            'smdp_main',
            'Settings',
            'Settings',
            'manage_options',
            'smdp_main',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'smdp_main',
            'Categories',
            'Categories',
            'manage_options',
            'smdp_categories',
            array( $this, 'render_categories_page' )
        );

        add_submenu_page(
            'smdp_main',
            'Menu Editor',
            'Menu Editor',
            'manage_options',
            'smdp_menu_editor',
            array( $this, 'render_items_page' )
        );

        add_submenu_page(
            'smdp_main',
            'Items',
            'Items',
            'manage_options',
            'smdp_items_list',
            array( $this, 'render_items_list' )
        );

        add_submenu_page(
            'smdp_main',
            'Webhooks',
            'Webhooks',
            'manage_options',
            'smdp-webhooks',
            'smdp_render_webhooks_page'
        );

        add_submenu_page(
            'smdp_main',
            'API Log',
            'API Log',
            'manage_options',
            'smdp_api_log',
            array( $this, 'render_api_log_page' )
        );
    }

    /**
     * Render the main settings page
     */
    public function render_settings_page() {
        // Get OAuth handler instance
        $oauth = SMDP_OAuth_Handler::instance();

        // Show error if OAuth connect was attempted but failed (handled in admin_init)
        if ( isset( $_POST['smdp_oauth_connect'] ) ) {
            echo '<div class="error"><p>OAuth not configured. App ID or Secret is missing.</p></div>';
        }

        // Handle OAuth disconnect button
        if ( isset( $_POST['smdp_oauth_disconnect'] ) ) {
            check_admin_referer( 'smdp_oauth_disconnect', 'smdp_oauth_disconnect_nonce' );
            $oauth->revoke_authorization();
            echo '<div class="updated"><p>Disconnected from Square successfully.</p></div>';
        }

        // Handle Clear OAuth Credentials button
        if ( isset( $_POST['smdp_clear_oauth_credentials'] ) ) {
            check_admin_referer( 'smdp_clear_oauth_credentials', 'smdp_clear_oauth_credentials_nonce' );
            delete_option( SMDP_OAuth_Handler::OPT_APP_ID );
            delete_option( SMDP_OAuth_Handler::OPT_APP_SECRET );
            echo '<div class="updated"><p><strong>✓ OAuth credentials cleared successfully!</strong></p></div>';
        }

        // Handle OAuth credentials save
        if ( isset( $_POST['smdp_save_oauth_credentials'] ) ) {
            check_admin_referer( 'smdp_oauth_credentials', 'smdp_oauth_credentials_nonce' );

            $app_id = smdp_sanitize_text_field( $_POST['smdp_oauth_app_id'], 100 );
            $app_secret = smdp_sanitize_text_field( $_POST['smdp_oauth_app_secret'], 200 );

            // Save App ID (plain text - it's public)
            update_option( SMDP_OAuth_Handler::OPT_APP_ID, $app_id );

            // Only save App Secret if it's not empty and not the placeholder
            if ( ! empty( $app_secret ) && $app_secret !== '••••••••••••' ) {
                smdp_store_encrypted_option( SMDP_OAuth_Handler::OPT_APP_SECRET, $app_secret );
                echo '<div class="updated"><p><strong>✓ OAuth credentials saved securely!</strong> You can now click "Connect with Square" above.</p></div>';
            } else {
                echo '<div class="updated"><p><strong>✓ App ID saved!</strong> App Secret unchanged.</p></div>';
            }
        }

        // Show OAuth success message
        if ( isset( $_GET['oauth_success'] ) && $_GET['oauth_success'] === '1' ) {
            echo '<div class="updated"><p><strong>✓ Successfully connected to Square!</strong> Your access token has been securely stored.</p></div>';
        }

        // Handle Clear Token button
        if ( isset( $_POST['smdp_clear_token'] ) ) {
            check_admin_referer( 'smdp_clear_token', 'smdp_clear_token_nonce' );
            delete_option( SMDP_ACCESS_TOKEN );
            echo '<div class="updated"><p><strong>✓ Access token cleared successfully!</strong></p></div>';
        }

        // Process settings form submission
        if ( isset( $_POST['smdp_save_settings'] ) ) {
            check_admin_referer( 'smdp_settings_save', 'smdp_nonce' );
            $token = smdp_sanitize_text_field( $_POST['smdp_access_token'], 500 ); // Square tokens ~200 chars

            // Only update token if it's not the masked placeholder
            if ( ! empty( $token ) && strpos( $token, '••••' ) === false ) {
                // SECURITY: Validate token format before storing
                $validated_token = smdp_validate_access_token( $token );
                if ( $validated_token !== false ) {
                    $result = smdp_store_access_token( $validated_token );
                    if ( ! $result ) {
                        echo '<div class="error"><p><strong>Error:</strong> Failed to save access token.</p></div>';
                    }
                } else {
                    echo '<div class="error"><p><strong>Error:</strong> Invalid access token format. Please check your token and try again.</p></div>';
                    error_log( '[SMDP Security] Invalid access token format rejected' );
                }
            }

            $interval = intval( $_POST['smdp_sync_interval'] );
            update_option( SMDP_SYNC_INTERVAL, $interval );
            $sync_mode = isset( $_POST['smdp_sync_mode'] ) ? 1 : 0;
            update_option( SMDP_SYNC_MODE, $sync_mode );

            // Save debug mode settings
            $debug_mode = isset( $_POST['smdp_pwa_debug_mode'] ) ? 1 : 0;
            update_option( 'smdp_pwa_debug_mode', $debug_mode );

            $cache_version = intval( $_POST['smdp_cache_version'] );
            update_option( 'smdp_cache_version', $cache_version );

            wp_clear_scheduled_hook( SMDP_CRON_HOOK );
            if ( ! $sync_mode ) {
                wp_schedule_event( time(), 'smdp_custom_interval', SMDP_CRON_HOOK );
            }
            echo '<div class="updated"><p>Settings saved.</p></div>';
        }

        // Process manual sync
        if ( isset( $_POST['smdp_sync_now'] ) ) {
            check_admin_referer( 'smdp_sync_now', 'smdp_nonce_sync' );
            smdp_sync_items();
            echo '<div class="updated"><p>Sync completed.</p></div>';
        }

        // Process clear all cached data
        if ( isset( $_POST['clear_all_data'] ) ) {
            check_admin_referer( 'smdp_clear_all_data', 'smdp_clear_data_nonce' );
            delete_option( SMDP_ITEMS_OPTION );
            delete_option( SMDP_MAPPING_OPTION );
            delete_option( SMDP_CATEGORIES_OPTION );
            delete_option( SMDP_API_LOG_OPTION );
            echo '<div class="updated"><p>All cached data has been cleared.</p></div>';
        }

        $token = smdp_get_access_token();

        // Determine token source for display
        $token_source = '';
        $oauth_token_raw = get_option( 'smdp_oauth_access_token', '' );
        $manual_token_raw = get_option( SMDP_ACCESS_TOKEN, '' );

        if ( ! empty( $oauth_token_raw ) ) {
            $token_source = 'OAuth';
        } elseif ( ! empty( $manual_token_raw ) ) {
            $token_source = 'Manual';
        }

        // Mask the token for display (show only last 8 characters)
        $display_token = '';
        if ( ! empty( $token ) ) {
            $token_length = strlen( $token );
            if ( $token_length > 8 ) {
                $display_token = '••••••••••••' . substr( $token, -8 );
            } else {
                $display_token = str_repeat( '•', $token_length );
            }
        }

        $interval = get_option( SMDP_SYNC_INTERVAL, 3600 );
        $sync_mode = get_option( SMDP_SYNC_MODE, 0 );
        $debug_mode = get_option( 'smdp_pwa_debug_mode', 0 );
        $cache_version = get_option( 'smdp_cache_version', 1 );

        $interval_options = array(
            3600   => 'Hourly',
            21600  => 'Every 6 Hours',
            43200  => 'Every 12 Hours',
            86400  => 'Daily',
        );

        // Get OAuth status
        $oauth_connected = $oauth->is_authorized();
        $oauth_token_info = $oauth->get_token_info();
        ?>
        <div class="wrap">
            <h1>Square Menu Settings</h1>

            <!-- OAuth Debug Info (temporary diagnostic) -->
            <div class="notice notice-info">
                <p><strong>OAuth Debug Info:</strong></p>
                <ul style="font-family:monospace;font-size:11px;">
                    <li>OAuth Token in DB: <?php echo get_option( 'smdp_oauth_access_token' ) ? 'YES (' . strlen(get_option( 'smdp_oauth_access_token' )) . ' chars encrypted)' : 'NO'; ?></li>
                    <li>Manual Token in DB: <?php echo $manual_token_raw ? 'YES (' . strlen($manual_token_raw) . ' chars encrypted)' : 'NO'; ?></li>
                    <li>is_authorized() returns: <?php echo $oauth_connected ? 'TRUE' : 'FALSE'; ?></li>
                    <li>smdp_get_access_token() returns: <?php echo ! empty( $token ) ? 'Token exists (' . strlen($token) . ' chars decrypted)' : 'EMPTY'; ?></li>
                    <li>Token Source: <?php echo $token_source ? $token_source : 'NONE'; ?></li>
                </ul>
            </div>

            <!-- Debug Mode Warning -->
            <?php if ($debug_mode): ?>
            <div class="notice notice-warning" style="border-left:4px solid #f39c12;">
                <p><strong>⚠️ PWA Debug Mode is Active!</strong> Caching is bypassed and a debug panel is visible on the frontend. This should only be enabled during development.</p>
            </div>
            <?php endif; ?>

            <!-- OAuth Connection Section -->
            <div style="background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,0.04);padding:20px;margin:20px 0;">
                <h2 style="margin-top:0;">Square Connection</h2>

                <?php if ( $oauth_connected ): ?>
                    <!-- Connected State -->
                    <div class="notice notice-success inline" style="margin:10px 0;padding:10px;">
                        <p style="margin:0;">
                            <span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span>
                            <strong>Connected to Square</strong>
                        </p>
                    </div>

                    <?php if ( ! empty( $oauth_token_info['expires_at'] ) ): ?>
                        <p>
                            <strong>Token Status:</strong>
                            <?php if ( $oauth_token_info['expired'] ): ?>
                                <span style="color:#dc3232;">Expired</span>
                            <?php elseif ( $oauth_token_info['days_remaining'] < 7 ): ?>
                                <span style="color:#f56e28;">Expires in <?php echo $oauth_token_info['days_remaining']; ?> days</span>
                            <?php else: ?>
                                <span style="color:#46b450;">Active (<?php echo $oauth_token_info['days_remaining']; ?> days remaining)</span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>

                    <!-- Webhook Limitation Notice for OAuth -->
                    <div style="margin-top:20px;padding:12px;background:#fff8e1;border-left:4px solid #ff9800;border-radius:4px;">
                        <p style="margin:0;"><strong>ℹ️ Note:</strong> Webhooks are not available when using OAuth authentication. Square webhook subscriptions are application-level and require a personal access token. You can still use manual syncing or scheduled syncing.</p>
                    </div>

                    <form method="post" style="margin-top:15px;">
                        <?php wp_nonce_field( 'smdp_oauth_disconnect', 'smdp_oauth_disconnect_nonce' ); ?>
                        <button type="submit" name="smdp_oauth_disconnect" class="button button-secondary">
                            Disconnect from Square
                        </button>
                    </form>

                <?php else: ?>
                    <!-- Disconnected State - Show OAuth Setup Form -->
                    <?php
                    $app_id = get_option( SMDP_OAuth_Handler::OPT_APP_ID );
                    $app_secret = smdp_get_encrypted_option( SMDP_OAuth_Handler::OPT_APP_SECRET );
                    $has_credentials = ! empty( $app_id ) && ! empty( $app_secret );
                    ?>

                    <?php if ( $has_credentials ): ?>
                        <!-- Has credentials - show connect button -->
                        <p>✓ OAuth credentials configured. Click below to connect your Square account.</p>

                        <form method="post" style="margin-top:15px;">
                            <?php wp_nonce_field( 'smdp_oauth_connect', 'smdp_oauth_nonce' ); ?>
                            <button type="submit" name="smdp_oauth_connect" class="button button-primary button-hero" style="padding:10px 20px;height:auto;line-height:1.4;">
                                <span class="dashicons dashicons-admin-plugins" style="margin-top:4px;"></span>
                                Connect with Square
                            </button>
                        </form>

                        <p style="margin-top:15px;">
                            <a href="#" onclick="document.getElementById('oauth-credentials-form').style.display='block'; this.style.display='none'; return false;">
                                Update OAuth credentials
                            </a>
                        </p>

                    <?php else: ?>
                        <!-- No credentials - show setup instructions -->
                        <p><strong>Step 1:</strong> Register your Square application at <a href="https://developer.squareup.com/apps" target="_blank">developer.squareup.com/apps</a></p>
                        <p><strong>Step 2:</strong> Enter your App ID and App Secret below</p>
                        <p><strong>Step 3:</strong> Click "Connect with Square"</p>
                    <?php endif; ?>

                    <!-- OAuth Credentials Form (hidden if already configured) -->
                    <div id="oauth-credentials-form" style="<?php echo $has_credentials ? 'display:none;' : ''; ?>margin-top:20px;padding:15px;background:#f9f9f9;border:1px solid #ddd;border-radius:4px;">
                        <h3 style="margin-top:0;">OAuth Application Credentials</h3>
                        <form method="post">
                            <?php wp_nonce_field( 'smdp_oauth_credentials', 'smdp_oauth_credentials_nonce' ); ?>

                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="smdp_oauth_app_id">Square App ID</label>
                                    </th>
                                    <td>
                                        <input type="text"
                                               name="smdp_oauth_app_id"
                                               id="smdp_oauth_app_id"
                                               value="<?php echo esc_attr( $app_id ); ?>"
                                               placeholder="sq0idp-..."
                                               style="width:100%;max-width:500px;"
                                               class="regular-text">
                                        <p class="description">
                                            Your Square Application ID (starts with sq0idp-)
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="smdp_oauth_app_secret">Square App Secret</label>
                                    </th>
                                    <td>
                                        <input type="password"
                                               name="smdp_oauth_app_secret"
                                               id="smdp_oauth_app_secret"
                                               value=""
                                               placeholder="<?php echo $app_secret ? 'Secret is already saved (leave blank to keep current)' : 'sq0csp-...'; ?>"
                                               style="width:100%;max-width:500px;"
                                               class="regular-text">
                                        <p class="description">
                                            <?php if ( $app_secret ): ?>
                                                <span style="color:green;">✓ App Secret is saved and encrypted</span><br>
                                                Leave blank to keep current secret, or enter a new one to update.
                                            <?php else: ?>
                                                Your Square Application Secret (will be encrypted when saved)
                                            <?php endif; ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <?php submit_button( 'Save OAuth Credentials', 'primary', 'smdp_save_oauth_credentials' ); ?>
                        </form>

                        <?php if ( $has_credentials ): ?>
                            <!-- Clear OAuth Credentials Button -->
                            <form method="post" style="display:inline-block;margin-top:10px;">
                                <?php wp_nonce_field( 'smdp_clear_oauth_credentials', 'smdp_clear_oauth_credentials_nonce' ); ?>
                                <button type="submit"
                                        name="smdp_clear_oauth_credentials"
                                        class="button button-secondary"
                                        onclick="return confirm('Are you sure you want to clear OAuth credentials? This will remove your App ID and App Secret.');"
                                        style="color:#b32d2e;">
                                    Clear OAuth Credentials
                                </button>
                            </form>
                        <?php endif; ?>

                        <p style="margin-top:15px;padding:10px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;">
                            <strong>Note:</strong> Get these credentials from your Square Developer Dashboard at
                            <a href="https://developer.squareup.com/apps" target="_blank">developer.squareup.com/apps</a>
                        </p>
                    </div>

                    <hr style="margin:20px 0;">

                    <p style="color:#666;font-size:13px;">
                        <strong>Or use manual token entry below</strong> (for advanced users or custom Square applications)
                    </p>
                <?php endif; ?>
            </div>

            <!-- Settings Form -->
            <form method="post">
                <?php wp_nonce_field( 'smdp_settings_save', 'smdp_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Square Access Token</th>
                        <td>
                            <input type="text"
                                name="smdp_access_token"
                                id="smdp_access_token"
                                value="<?php echo esc_attr( $display_token ); ?>"
                                placeholder="Enter new token to update"
                                style="width:400px">

                            <?php if ( ! empty( $token ) ): ?>
                                <p class="description">
                                    <span style="color:green;">✓ Token is securely stored (encrypted)</span>
                                    <?php if ( ! empty( $token_source ) ): ?>
                                        <br><strong>Source:</strong> <?php echo esc_html( $token_source ); ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>

                            <p class="description">
                                Enter a new token above and click "Save Settings" to update it.
                            </p>

                            <?php if ( ! empty( $token ) ): ?>
                                <!-- Clear Token Button (separate form to avoid conflicts) -->
                                <form method="post" style="display:inline-block;margin-top:10px;">
                                    <?php wp_nonce_field( 'smdp_clear_token', 'smdp_clear_token_nonce' ); ?>
                                    <button type="submit"
                                            name="smdp_clear_token"
                                            class="button button-secondary"
                                            onclick="return confirm('Are you sure you want to clear the access token? This will disconnect from Square.');"
                                            style="color:#b32d2e;">
                                        Clear Token
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sync Interval (seconds)</th>
                        <td>
                            <select name="smdp_sync_interval">
                                <?php foreach ( $interval_options as $seconds => $label ) : ?>
                                    <option value="<?php echo $seconds; ?>" <?php selected( $interval, $seconds ); ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Manual Sync Only</th>
                        <td>
                            <label>
                               <input type="checkbox" name="smdp_sync_mode" value="1" <?php checked( $sync_mode, 1 ); ?>>
                               Enable manual sync only (automatic syncing will be disabled)
                            </label>
                        </td>
                    </tr>
                </table>

                <h2>PWA Debug Mode</h2>
                <p>Enable debug mode to help with tablet testing and development. This will bypass PWA caching and show a debug panel on the frontend.</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Debug Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="smdp_pwa_debug_mode" value="1" <?php checked( $debug_mode, 1 ); ?>>
                                Enable PWA Debug Mode (bypass caching, show debug tools)
                            </label>
                            <p class="description">When enabled, tablets will always load the latest version of files and display a debug panel with cache-clearing tools.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Cache Version</th>
                        <td>
                            <input type="number" name="smdp_cache_version" id="smdp_cache_version" value="<?php echo esc_attr($cache_version); ?>" min="1" style="width:100px;">
                            <button type="button" class="button" id="smdp-increment-version">Increment Version</button>
                            <p class="description">
                                Current version: <strong>v<?php echo $cache_version; ?></strong><br>
                                Increment this number to force all tablets to reload assets, even without debug mode enabled.
                            </p>
                            <script>
                                jQuery(document).ready(function($){
                                    $('#smdp-increment-version').click(function(){
                                        var $input = $('#smdp_cache_version');
                                        $input.val(parseInt($input.val()) + 1);
                                    });
                                });
                            </script>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Settings', 'primary', 'smdp_save_settings' ); ?>
            </form>

            <hr>
            <!-- Sync Now Button -->
            <form method="post">
                <?php wp_nonce_field( 'smdp_sync_now', 'smdp_nonce_sync' ); ?>
                <?php submit_button( 'Sync Now', 'secondary', 'smdp_sync_now' ); ?>
            </form>
            <hr>
            <!-- Clear Cached Data Section -->
            <h2>Clear Cached Data</h2>
            <p>This will delete all locally cached data (items, mappings, categories, API logs) while preserving your Square Access Token.</p>
            <form method="post">
                <?php wp_nonce_field( 'smdp_clear_all_data', 'smdp_clear_data_nonce' ); ?>
                <?php submit_button( 'Clear All Cached Data', 'secondary', 'clear_all_data' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the categories management page
     */
    public function render_categories_page() {
        if ( isset( $_POST['smdp_add_category'] ) ) {
            check_admin_referer( 'smdp_category_save', 'smdp_cat_nonce' );
            $name = smdp_sanitize_text_field( $_POST['smdp_cat_name'], 100 ); // Category names
            $slug = sanitize_title( $name );
            $categories = get_option( SMDP_CATEGORIES_OPTION, array() );
            $id = 'cat_' . time();
            $categories[ $id ] = array(
                'id'    => $id,
                'name'  => $name,
                'slug'  => $slug,
                'order' => count( $categories ) + 1,
            );
            update_option( SMDP_CATEGORIES_OPTION, $categories );
            echo '<div class="updated"><p>Category added.</p></div>';
        }
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['cat_id'] ) ) {
            // Verify nonce for CSRF protection
            if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'smdp_delete_category' ) ) {
                wp_die( 'Security check failed. Please try again.', 'Security Error', array( 'response' => 403 ) );
            }

            $cat_id = sanitize_text_field( $_GET['cat_id'] );
            $categories = get_option( SMDP_CATEGORIES_OPTION, array() );
            if ( isset( $categories[ $cat_id ] ) ) {
                unset( $categories[ $cat_id ] );
                update_option( SMDP_CATEGORIES_OPTION, $categories );
                echo '<div class="updated"><p>Category deleted.</p></div>';
            }
        }
        $categories = get_option( SMDP_CATEGORIES_OPTION, array() );
        ?>
        <div class="wrap">
            <h1>Square Menu Categories</h1>
            <h2>Add New Category</h2>
            <form method="post">
                <?php wp_nonce_field( 'smdp_category_save', 'smdp_cat_nonce' ); ?>
                <input type="text" name="smdp_cat_name" placeholder="Category Name" required>
                <?php submit_button( 'Add Category', 'primary', 'smdp_add_category', false ); ?>
            </form>
            <h2>Existing Categories</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Order</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $categories ) ) : ?>
                        <?php foreach ( $categories as $cat ) : ?>
                            <tr>
                                <td><?php echo esc_html( $cat['name'] ); ?></td>
                                <td><?php echo esc_html( $cat['slug'] ); ?></td>
                                <td><?php echo esc_html( $cat['order'] ); ?></td>
                                <td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=smdp_categories&action=delete&cat_id=' . $cat['id'] ), 'smdp_delete_category' ) ); ?>" onclick="return confirm('Delete this category?');">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="4">No categories created yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render the items list page (for manual category assignment)
     */
    public function render_items_list() {
        // 1) Handle form submission (categories & sold_out_override)
        if ( isset($_POST['smdp_items_list_nonce']) ) {
            check_admin_referer('smdp_items_list_save','smdp_items_list_nonce');
            $mapping = get_option(SMDP_MAPPING_OPTION, []);
            if (!empty($_POST['item_mapping']) && is_array($_POST['item_mapping'])) {
                foreach ($_POST['item_mapping'] as $item_id => $data) {
                    $mapping[$item_id]['category']          = sanitize_text_field($data['category']);
                    $mapping[$item_id]['sold_out_override'] = sanitize_text_field($data['sold_out_override']);
                }
                update_option(SMDP_MAPPING_OPTION, $mapping);
                echo '<div class="updated"><p>Mappings and sold‑out overrides updated.</p></div>';
            }
        }

        // 2) Load cached data
        $mapping    = get_option(SMDP_MAPPING_OPTION,   []);
        $all_items  = get_option(SMDP_ITEMS_OPTION,     []);
        $categories = get_option(SMDP_CATEGORIES_OPTION,[]);

        // 3) Build list of items that have a category mapping
        $items = [];
        foreach ($all_items as $obj) {
            if (empty($obj['type']) || $obj['type'] !== 'ITEM') continue;
            $id = $obj['id'];
            if (!empty($mapping[$id]['category'])) {
                $obj['order'] = $mapping[$id]['order'];
                $items[] = $obj;
            }
        }
        usort($items, function($a,$b){ return $a['order'] - $b['order']; });

        // 4) Render table
        ?>
        <div class="wrap">
          <h1>Items List</h1>
          <p>
            <button id="smdp-match-categories-btn" class="button button-secondary">
              Match Square Categories
            </button>
            <button id="smdp-sync-soldout-btn" class="button button-secondary">
              Sync Sold Out Status from Square
            </button>
          </p>

          <form method="post" id="smdp-items-list-form">
            <?php wp_nonce_field('smdp_items_list_save','smdp_items_list_nonce'); ?>
            <table class="wp-list-table widefat striped">
              <thead>
                <tr>
                  <th>Thumb</th>
                  <th>Name</th>
                  <th>Reporting Cat ID</th>
                  <th>Square Cat</th>
                  <th>Menu Cat</th>
                  <th>Square Sold Out</th>
                  <th>Plugin Sold Out</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($items): foreach ($items as $obj):
                    $item_id   = $obj['id'];
                    $item_data = $obj['item_data'];

                    // Reporting category & name
                    $rep_id  = $item_data['reporting_category']['id'] ?? '';
                    $sq_name = $categories[$rep_id]['name'] ?? '–';

                    // Thumbnail
                    $thumb = '';
                    if (!empty($item_data['image_ids'][0])) {
                        $img_id = $item_data['image_ids'][0];
                        foreach ($all_items as $i) {
                            if ($i['type']==='IMAGE' && $i['id']===$img_id) {
                                $thumb = $i['image_data']['url'];
                                break;
                            }
                        }
                    }

                    // === Detect sold_out via variation.location_overrides ===
                    $is_sold = false;
                    if (!empty($item_data['variations'])) {
                        foreach ($item_data['variations'] as $var) {
                            $ov_list = $var['item_variation_data']['location_overrides'] ?? [];
                            foreach ($ov_list as $ov) {
                                if (!empty($ov['sold_out'])) {
                                    $is_sold = true;
                                    break 2;  // break out of both loops
                                }
                            }
                        }
                    }
                    $square_sold = $is_sold ? 'Sold Out' : 'Available';
                    // === end sold_out detection ===

                    // Plugin override remains as before
                    $plugin_override = $mapping[$item_id]['sold_out_override'] ?? '';
                ?>
                <tr>
                  <td>
                    <?php if ($thumb): ?>
                      <img src="<?php echo esc_url($thumb); ?>" style="max-width:50px;height:auto;">
                    <?php endif; ?>
                  </td>
                  <td><?php echo esc_html($item_data['name']); ?></td>
                  <td><?php echo esc_html($rep_id); ?></td>
                  <td><?php echo esc_html($sq_name); ?></td>
                  <td>
                    <select name="item_mapping[<?php echo esc_attr($item_id); ?>][category]">
                      <option value="">Unassigned</option>
                      <?php foreach ($categories as $cid => $cat): ?>
                        <option value="<?php echo esc_attr($cid); ?>"
                          <?php selected($mapping[$item_id]['category'], $cid); ?>>
                          <?php echo esc_html($cat['name']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><?php echo esc_html($square_sold); ?></td>
                  <td>
                    <select name="item_mapping[<?php echo esc_attr($item_id); ?>][sold_out_override]">
                      <option value="sold"     <?php selected($plugin_override, 'sold'); ?>>Sold Out</option>
                      <option value="available"<?php selected($plugin_override, 'available'); ?>>Available</option>
                    </select>
                  </td>
                </tr>
                <?php endforeach; else: ?>
                  <tr><td colspan="7">No items found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>

            <?php submit_button('Save Changes'); ?>
          </form>
        </div>

        <script>
        jQuery(function($){
          // Match Square Categories → plugin mapping
          $('#smdp-match-categories-btn').click(function(){
            if(!confirm('Overwrite plugin categories from Square cache?')) return;
            $.post(ajaxurl, {
              action: 'smdp_match_categories',
              _ajax_nonce: '<?php echo wp_create_nonce("smdp_match_categories"); ?>'
            }, function(r){
              if(r.success) location.reload();
              else alert('Error: ' + r.data);
            });
          });

          // Sync Sold Out → plugin override
          $('#smdp-sync-soldout-btn').click(function(){
            if(!confirm('Overwrite plugin sold‑out overrides with Square?')) return;
            $.post(ajaxurl, {
              action: 'smdp_sync_sold_out',
              _ajax_nonce: '<?php echo wp_create_nonce("smdp_sync_sold_out"); ?>'
            }, function(r){
              if(r.success) location.reload();
              else alert('Error: ' + r.data);
            });
          });
        });
        </script>
        <style>
          .wp-list-table th, .wp-list-table td { vertical-align: middle; }
        </style>
        <?php
    }

    /**
     * Render the menu editor page (drag and drop interface)
     */
    public function render_items_page() {
        // Do not force a sync here
        if ( isset($_POST['mapping_json']) ) {
        check_admin_referer('smdp_mapping_save', 'smdp_mapping_nonce');

        // Validate JSON structure
        $json_string = stripslashes($_POST['mapping_json']);
        $posted = json_decode( $json_string, true );

        // Check for JSON decode errors
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            echo '<div class="error"><p>Invalid JSON data: ' . esc_html( json_last_error_msg() ) . '</p></div>';
            error_log( '[SMDP JSON Validation] JSON decode error: ' . json_last_error_msg() );
        } elseif ( ! is_array( $posted ) ) {
            echo '<div class="error"><p>Invalid mapping data: Expected an array.</p></div>';
            error_log( '[SMDP JSON Validation] Mapping data is not an array' );
        } else {
            // Validate structure of each item
            $valid = true;
            foreach( $posted as $item_id => $data ) {
                if ( ! is_array( $data ) ||
                     ! isset( $data['category'] ) ||
                     ! isset( $data['order'] ) ||
                     ! isset( $data['hide_image'] ) ) {
                    $valid = false;
                    error_log( "[SMDP JSON Validation] Invalid item structure for ID: {$item_id}" );
                    break;
                }

                // Validate data types
                if ( ! is_string( $data['category'] ) ||
                     ! is_numeric( $data['order'] ) ||
                     ! in_array( $data['hide_image'], array( 0, 1, '0', '1', true, false ), true ) ) {
                    $valid = false;
                    error_log( "[SMDP JSON Validation] Invalid data types for ID: {$item_id}" );
                    break;
                }
            }

            if ( ! $valid ) {
                echo '<div class="error"><p>Invalid mapping structure. Please try again.</p></div>';
            } else {
                // Process valid data
                $existing = get_option( SMDP_MAPPING_OPTION, array() );
                foreach( $posted as $item_id => $data ) {
                    // ensure the item exists in existing mapping
                    if ( ! isset( $existing[$item_id] ) ) {
                        $existing[$item_id] = array(
                            'category'         => '',
                            'order'            => 0,
                            'hide_image'       => 0,
                            'sold_out_override'=> '',   // default if missing
                        );
                    }
                    // only overwrite these three fields
                    $existing[$item_id]['category']   = sanitize_text_field( $data['category'] );
                    $existing[$item_id]['order']      = intval( $data['order'] );
                    $existing[$item_id]['hide_image'] = (int) $data['hide_image'];
                    // sold_out_override stays untouched
                }
                update_option( SMDP_MAPPING_OPTION, $existing );
                echo '<div class="updated"><p>Mappings updated (overrides preserved).</p></div>';
            }
        }
    }

        $all_objects = get_option(SMDP_ITEMS_OPTION, array());

        // Build image lookup array: image_id => URL
        $image_lookup = array();
        foreach ( $all_objects as $obj ) {
            if ( isset($obj['type']) && $obj['type'] === 'IMAGE' && !empty($obj['image_data']['url']) ) {
                $image_lookup[$obj['id']] = $obj['image_data']['url'];
            }
        }

        // Retrieve ITEM objects
        $items = array();
        foreach ( $all_objects as $obj ) {
            if ( isset($obj['type']) && $obj['type'] === 'ITEM' ) {
                $items[] = $obj;
            }
        }

        // Retrieve active categories from SMDP_CATEGORIES_OPTION
        $categories = get_option(SMDP_CATEGORIES_OPTION, array());

        // Retrieve mapping
        $mapping = get_option(SMDP_MAPPING_OPTION, array());

        // Sort active categories: visible ones first, then hidden (sorted by name)
        $cat_array = array();
        foreach ($categories as $cat) {
            $cat_array[] = $cat;
        }
        usort($cat_array, function($a, $b) {
            $a_hidden = (isset($a['hidden']) && $a['hidden']) ? 1 : 0;
            $b_hidden = (isset($b['hidden']) && $b['hidden']) ? 1 : 0;
            if ($a_hidden == $b_hidden) {
                return strcmp($a['name'], $b['name']);
            }
            return $a_hidden - $b_hidden;
        });

        // Group items by category
        $grouped_items = array();
        foreach ($cat_array as $cat) {
            $grouped_items[$cat['id']] = array();
        }
        $grouped_items['unassigned'] = array();

        foreach ( $items as $item_obj ) {
            $item_id = $item_obj['id'];
            if ( isset($mapping[$item_id]) && $mapping[$item_id]['category'] !== '' ) {
                $cat = $mapping[$item_id]['category'];
            } else {
                $cat = 'unassigned';
            }
            $thumbnail = '';
            if ( isset($item_obj['item_data']['image_ids']) && is_array($item_obj['item_data']['image_ids']) && !empty($item_obj['item_data']['image_ids'][0]) ) {
                $first_img_id = $item_obj['item_data']['image_ids'][0];
                if ( isset($image_lookup[$first_img_id]) ) {
                    $thumbnail = $image_lookup[$first_img_id];
                }
            }
            $grouped_items[$cat][] = array(
                'id'         => $item_id,
                'name'       => $item_obj['item_data']['name'],
                'thumbnail'  => $thumbnail,
                'hide_image' => isset($mapping[$item_id]['hide_image']) ? $mapping[$item_id]['hide_image'] : 0,
                'order'      => isset($mapping[$item_id]['order']) ? $mapping[$item_id]['order'] : 0,
            );
        }

        // Sort items in each group by order
        foreach ( $grouped_items as &$group ) {
             usort($group, function($a, $b) {
                 return intval($a['order']) - intval($b['order']);
             });
        }
        unset($group);

        // Include the template
        include plugin_dir_path( __FILE__ ) . 'templates/admin-menu-editor.php';
    }

    /**
     * Render the API log page
     */
    public function render_api_log_page() {
        $api_logs = get_option( SMDP_API_LOG_OPTION, array() );
        ?>
        <div class="wrap">
            <h1>Square API Log</h1>
            <p>This section displays the API request and raw response received from Square during the catalog sync. (Up to the 10 most recent entries are shown.)</p>
            <?php if ( ! empty( $api_logs ) ) : ?>
                <?php foreach ( $api_logs as $log ) : ?>
                    <div style="margin-bottom: 20px; padding: 10px; border: 1px solid #ddd;">
                        <strong>Timestamp:</strong> <?php echo esc_html( $log['timestamp'] ); ?><br><br>
                        <strong>API Request:</strong>
                        <pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ccc; overflow-y: auto; height: 150px; white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html( $log['catalog_request'] ); ?></pre>
                        <strong>API Response:</strong>
                        <pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ccc; overflow-y: auto; height: 300px; white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html( $log['catalog_response'] ); ?></pre>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p>No API logs recorded yet.</p>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Initialize the admin pages
SMDP_Admin_Pages::instance();

/**
 * Backward compatibility wrapper functions
 */
if ( ! function_exists( 'smdp_render_settings_page' ) ) {
    function smdp_render_settings_page() {
        SMDP_Admin_Pages::instance()->render_settings_page();
    }
}

if ( ! function_exists( 'smdp_render_categories_page' ) ) {
    function smdp_render_categories_page() {
        SMDP_Admin_Pages::instance()->render_categories_page();
    }
}

if ( ! function_exists( 'smdp_render_items_list' ) ) {
    function smdp_render_items_list() {
        SMDP_Admin_Pages::instance()->render_items_list();
    }
}

if ( ! function_exists( 'smdp_render_items_page' ) ) {
    function smdp_render_items_page() {
        SMDP_Admin_Pages::instance()->render_items_page();
    }
}

if ( ! function_exists( 'smdp_render_api_log_page' ) ) {
    function smdp_render_api_log_page() {
        SMDP_Admin_Pages::instance()->render_api_log_page();
    }
}
