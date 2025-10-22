<?php
/**
 * Plugin Name: Square Menu Display Premium Deluxe Pro
 * Description: Square Menu Display with advanced features: category management, drag-and-drop ordering, sold-out sync, and more.
 * Version: 3.0
 * Author: Mark Shoucair (30%) & ChatGPT & Claude (70%)
 */

// Bail early if someone tries to load this outside WP
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =========================================================================
// COMPOSER AUTOLOAD
// =========================================================================
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// =========================================================================
// LOAD PLUGIN CONSTANTS
// =========================================================================
require_once __DIR__ . '/includes/constants.php';

// =========================================================================
// SECURITY: HIDE SENSITIVE OPTIONS FROM options.php
// =========================================================================
add_filter( 'option_page_capability_options', function() {
    // Prevent access to options.php entirely for non-super-admins in multisite
    if ( is_multisite() && ! is_super_admin() ) {
        return 'do_not_allow';
    }
    return 'manage_options';
});

// Hide sensitive encrypted data from appearing in options.php
add_filter( 'pre_update_option', function( $value, $option, $old_value ) {
    // List of sensitive options that should never be displayed/edited via options.php
    $sensitive_options = array(
        'square_menu_access_token',          // Manual access token (encrypted)
        'smdp_access_token',                 // Legacy access token name (encrypted)
        'smdp_oauth_access_token',           // OAuth access token (encrypted)
        'smdp_oauth_refresh_token',          // OAuth refresh token (encrypted)
        'smdp_oauth_app_secret',             // OAuth app secret (encrypted)
        'smdp_square_webhook_signature_key', // Webhook signature key (encrypted)
    );

    // If this is a sensitive option being updated via options.php, block it
    if ( in_array( $option, $sensitive_options ) ) {
        // Check if this is coming from options.php (not our custom forms)
        $referer = wp_get_referer();
        if ( $referer && strpos( $referer, 'options.php' ) !== false ) {
            // Log security event
            error_log( '[SMDP SECURITY] Blocked attempt to modify sensitive option "' . $option . '" via options.php' );

            // Prevent the update by returning the old value
            return $old_value;
        }
    }

    return $value;
}, 10, 3 );

// Hide sensitive options from the options list entirely
add_filter( 'allowed_options', function( $allowed_options ) {
    // Remove sensitive options from ALL option groups to prevent display/editing
    $sensitive_options = array(
        'square_menu_access_token',
        'smdp_access_token',
        'smdp_oauth_access_token',
        'smdp_oauth_refresh_token',
        'smdp_oauth_app_secret',
        'smdp_square_webhook_signature_key',
    );

    foreach ( $allowed_options as $group => &$options ) {
        if ( is_array( $options ) ) {
            $options = array_diff( $options, $sensitive_options );
        }
    }

    return $allowed_options;
});

// Sanitize sensitive options to prevent accidental display (show masked value)
add_filter( 'option_square_menu_access_token', function( $value ) {
    // If we're in admin context but NOT in our own forms, return masked value
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        foreach ( $backtrace as $trace ) {
            // If called from options.php or settings pages (except our own), mask it
            if ( isset( $trace['file'] ) && strpos( $trace['file'], 'options.php' ) !== false ) {
                return '********** (encrypted - hidden for security)';
            }
        }
    }
    return $value;
});

// Apply same masking to other sensitive options
add_filter( 'option_smdp_oauth_access_token', function( $value ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        foreach ( $backtrace as $trace ) {
            if ( isset( $trace['file'] ) && strpos( $trace['file'], 'options.php' ) !== false ) {
                return '********** (encrypted - hidden for security)';
            }
        }
    }
    return $value;
});

add_filter( 'option_smdp_oauth_app_secret', function( $value ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        foreach ( $backtrace as $trace ) {
            if ( isset( $trace['file'] ) && strpos( $trace['file'], 'options.php' ) !== false ) {
                return '********** (encrypted - hidden for security)';
            }
        }
    }
    return $value;
});

add_filter( 'option_smdp_square_webhook_signature_key', function( $value ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 );
        foreach ( $backtrace as $trace ) {
            if ( isset( $trace['file'] ) && strpos( $trace['file'], 'options.php' ) !== false ) {
                return '********** (encrypted - hidden for security)';
            }
        }
    }
    return $value;
});

// =========================================================================
// LOAD PLUGIN ACTIVATION CLASS (must be before register_activation_hook)
// =========================================================================
require_once __DIR__ . '/includes/class-plugin-activation.php';

// Register activation/deactivation hooks
register_activation_hook( __FILE__, 'smdp_activate' );
register_deactivation_hook( __FILE__, 'smdp_deactivate' );

// =========================================================================
// LOAD PLUGIN CLASSES
// =========================================================================

// 1. Admin Pages class (MUST load first - creates parent menu 'smdp_main')
require_once __DIR__ . '/includes/class-admin-pages.php';

// 2. Help Request class (depends on parent menu from Admin Pages)
require_once __DIR__ . '/includes/class-help-request.php';
new SMDP_Help_Request();

// 3. AJAX Handler class
require_once __DIR__ . '/includes/class-ajax-handler.php';

// 4. Shortcode class
require_once __DIR__ . '/includes/class-shortcode.php';

// 5. Sync Manager class
require_once __DIR__ . '/includes/class-sync-manager.php';

// 6. Admin Settings class
require_once __DIR__ . '/includes/class-admin-settings.php';

// 7. Admin Assets class
require_once __DIR__ . '/includes/class-admin-assets.php';

// 8. Debug Panel class
require_once __DIR__ . '/includes/class-debug-panel.php';

// 9. OAuth Handler class
require_once __DIR__ . '/includes/class-oauth-handler.php';

// 10. PWA Handler class
require_once __DIR__ . '/includes/class-pwa-handler.php';
SMDP_PWA_Handler::instance();

// 11. Manifest Generator class
require_once __DIR__ . '/includes/class-manifest-generator.php';
SMDP_Manifest_Generator::instance();

// 12. Menu App Builder class
require_once __DIR__ . '/includes/class-menu-app-builder.php';
add_action( 'plugins_loaded', function() {
    SMDP_Menu_App_Builder::init();
});

// 13. Protection Settings class
require_once __DIR__ . '/includes/class-protection-settings.php';
new SMDP_Protection_Settings();

// 14. Appearance Manager class
require_once __DIR__ . '/includes/class-appearance-manager.php';
new SMDP_Appearance_Manager();

// 15. Standalone Menu App class
require_once __DIR__ . '/includes/class-standalone-menu-app.php';
SMDP_Standalone_Menu_App::instance();

// =========================================================================
// WEBHOOK FILES
// =========================================================================
require_once plugin_dir_path( __FILE__ ) . 'admin-webhooks.php';
require_once plugin_dir_path( __FILE__ ) . 'smdp-webhook.php';