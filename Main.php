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