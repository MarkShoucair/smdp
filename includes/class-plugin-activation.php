<?php
/**
 * Plugin Activation and Deactivation
 *
 * Handles plugin activation and deactivation hooks.
 *
 * @package Square_Menu_Display
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SMDP_Plugin_Activation
 *
 * Manages plugin activation and deactivation processes.
 */
class SMDP_Plugin_Activation {

    /**
     * Singleton instance
     *
     * @var SMDP_Plugin_Activation
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return SMDP_Plugin_Activation
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
        // Hooks are registered externally using register_activation_hook() and register_deactivation_hook()
        // These must be registered in the main plugin file due to WordPress requirements
    }

    /**
     * Plugin activation callback
     *
     * Sets up default options and schedules cron events.
     */
    public static function activate() {
        // Initialize default options if they don't exist
        if ( false === get_option( SMDP_ACCESS_TOKEN ) ) {
            update_option( SMDP_ACCESS_TOKEN, '' );
        }

        if ( false === get_option( SMDP_SYNC_INTERVAL ) ) {
            update_option( SMDP_SYNC_INTERVAL, 3600 );
        }

        if ( false === get_option( SMDP_SYNC_MODE ) ) {
            update_option( SMDP_SYNC_MODE, 0 ); // default automatic syncing enabled
        }

        if ( false === get_option( SMDP_CATEGORIES_OPTION ) ) {
            update_option( SMDP_CATEGORIES_OPTION, array() );
        }

        if ( false === get_option( SMDP_MAPPING_OPTION ) ) {
            update_option( SMDP_MAPPING_OPTION, array() );
        }

        if ( false === get_option( SMDP_API_LOG_OPTION ) ) {
            update_option( SMDP_API_LOG_OPTION, array() );
        }

        // Initialize PWA/Debug options
        if ( false === get_option( 'smdp_pwa_debug_mode' ) ) {
            update_option( 'smdp_pwa_debug_mode', 0 ); // Debug mode disabled by default
        }

        if ( false === get_option( 'smdp_cache_version' ) ) {
            update_option( 'smdp_cache_version', 1 );
        }

        if ( false === get_option( 'smdp_last_sync_timestamp' ) ) {
            update_option( 'smdp_last_sync_timestamp', 0 );
        }

        // Initialize Help & Bill options
        if ( false === get_option( 'smdp_help_tables' ) ) {
            update_option( 'smdp_help_tables', array() );
        }

        if ( false === get_option( 'smdp_help_item_id' ) ) {
            update_option( 'smdp_help_item_id', '' );
        }

        if ( false === get_option( 'smdp_bill_item_id' ) ) {
            update_option( 'smdp_bill_item_id', '' );
        }

        if ( false === get_option( 'smdp_location_id' ) ) {
            update_option( 'smdp_location_id', '' );
        }

        if ( false === get_option( 'smdp_bill_lookup_method' ) ) {
            update_option( 'smdp_bill_lookup_method', 'customer' ); // Default to customer ID method
        }

        if ( false === get_option( 'smdp_table_item_ids' ) ) {
            update_option( 'smdp_table_item_ids', array() );
        }

        if ( false === get_option( 'smdp_disabled_modifiers' ) ) {
            update_option( 'smdp_disabled_modifiers', array() );
        }

        // Initialize Menu App Builder options
        if ( false === get_option( 'smdp_app_settings' ) ) {
            update_option( 'smdp_app_settings', array(
                'layout' => 'top',
                'promo_timeout' => 600,
                'theme_color' => '#5C7BA6',
                'background_color' => '#ffffff',
                'icon_192' => '',
                'icon_512' => '',
                'apple_touch_icon' => '',
                'app_name' => '',
                'app_short_name' => '',
                'app_description' => '',
                'display_mode' => 'standalone',
                'orientation' => 'any',
                'promo_images' => array()
            ) );
        }

        if ( false === get_option( 'smdp_app_custom_css' ) ) {
            update_option( 'smdp_app_custom_css', "/* Button & card style overrides here */" );
        }

        if ( false === get_option( 'smdp_app_button_styles' ) ) {
            update_option( 'smdp_app_button_styles', array(
                'bg_color' => '#5C7BA6',
                'text_color' => '#ffffff',
                'border_color' => '#5C7BA6',
                'active_bg_color' => '#4A6288',
                'active_text_color' => '#ffffff',
                'active_border_color' => '#4A6288',
                'font_size' => 18,
                'padding_vertical' => 12,
                'padding_horizontal' => 20,
                'border_radius' => 8,
                'border_width' => 1,
                'font_weight' => '600',
                'font_family' => ''
            ) );
        }

        if ( false === get_option( 'smdp_app_catalog' ) ) {
            update_option( 'smdp_app_catalog', array() );
        }

        if ( false === get_option( 'smdp_app_menus' ) ) {
            update_option( 'smdp_app_menus', array() );
        }

        // Schedule cron event if automatic syncing is enabled and not already scheduled
        if ( ! get_option( SMDP_SYNC_MODE ) && ! wp_next_scheduled( SMDP_CRON_HOOK ) ) {
            $interval = get_option( SMDP_SYNC_INTERVAL, 3600 );
            wp_schedule_event( time(), 'smdp_custom_interval', SMDP_CRON_HOOK );
        }

        // IMPORTANT: Register rewrite rules before flushing
        // The standalone menu app class adds rules on 'init' hook, but that has already fired during activation
        // So we need to manually register them here before flushing
        self::register_menu_app_rewrite_rules();

        // Flush rewrite rules for standalone menu app URL
        flush_rewrite_rules();
    }

    /**
     * Register menu app rewrite rules
     *
     * This is called during activation to ensure rewrite rules exist before flushing.
     * Also used by SMDP_Standalone_Menu_App class on 'init' hook.
     */
    public static function register_menu_app_rewrite_rules() {
        // Add rewrite rule: /menu-app/ or /menu-app/table/5/ or /menu-app/category/appetizers/
        add_rewrite_rule(
            '^menu-app/?$',
            'index.php?smdp_menu_app=1',
            'top'
        );

        add_rewrite_rule(
            '^menu-app/table/([0-9]+)/?$',
            'index.php?smdp_menu_app=1&smdp_table=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^menu-app/category/([^/]+)/?$',
            'index.php?smdp_menu_app=1&smdp_category=$matches[1]',
            'top'
        );
    }

    /**
     * Plugin deactivation callback
     *
     * Clears scheduled cron events and flushes rewrite rules.
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( SMDP_CRON_HOOK );

        // Flush rewrite rules to remove standalone menu app URL
        flush_rewrite_rules();
    }
}

// Initialize the class
SMDP_Plugin_Activation::instance();

/**
 * Backward compatibility wrapper functions
 */

if ( ! function_exists( 'smdp_activate' ) ) {
    function smdp_activate() {
        SMDP_Plugin_Activation::activate();
    }
}

if ( ! function_exists( 'smdp_deactivate' ) ) {
    function smdp_deactivate() {
        SMDP_Plugin_Activation::deactivate();
    }
}
