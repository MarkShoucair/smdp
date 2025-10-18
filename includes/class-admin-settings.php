<?php
/**
 * Admin Settings Registration
 *
 * Handles registration of WordPress settings sections and fields for the plugin.
 *
 * @package Square_Menu_Display
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SMDP_Admin_Settings
 *
 * Manages settings registration for help-request and debug mode settings.
 */
class SMDP_Admin_Settings {

    /**
     * Singleton instance
     *
     * @var SMDP_Admin_Settings
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return SMDP_Admin_Settings
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
        add_action( 'admin_init', array( $this, 'register_help_settings' ) );
        add_action( 'admin_init', array( $this, 'register_debug_settings' ) );
        add_action( 'admin_init', array( $this, 'register_advanced_settings' ) );
        add_action( 'admin_init', array( $this, 'handle_flush_rewrite_rules' ) );
        add_action( 'admin_init', array( $this, 'handle_clear_rate_limits' ) );
    }

    /**
     * Register help-request settings
     *
     * Registers settings fields for help item catalog ID and location ID.
     */
    public function register_help_settings() {
        // Register the two options
        register_setting( 'smdp_settings_group', 'smdp_help_item_id' );
        register_setting( 'smdp_settings_group', 'smdp_location_id' );

        // Add a section on the Settings page
        add_settings_section(
            'smdp_help_section',
            'Help-Request Settings',
            function() {
                echo '<p>Configure your "Request Help" item & location</p>';
            },
            'smdp_settings_page'
        );

        // Field: help item ID
        add_settings_field(
            'smdp_help_item_id',
            'Help Item Catalog ID',
            function() {
                printf(
                    '<input name="smdp_help_item_id" type="text" value="%s" class="regular-text" />',
                    esc_attr( get_option( 'smdp_help_item_id', '' ) )
                );
            },
            'smdp_settings_page',
            'smdp_help_section'
        );

        // Field: location ID
        add_settings_field(
            'smdp_location_id',
            'Location ID',
            function() {
                printf(
                    '<input name="smdp_location_id" type="text" value="%s" class="regular-text" />',
                    esc_attr( get_option( 'smdp_location_id', '' ) )
                );
            },
            'smdp_settings_page',
            'smdp_help_section'
        );
    }

    /**
     * Register debug mode settings
     *
     * Registers settings fields for PWA debug mode and cache version.
     */
    public function register_debug_settings() {
        register_setting( 'smdp_settings_group', 'smdp_pwa_debug_mode' );
        register_setting( 'smdp_settings_group', 'smdp_cache_version' );

        add_settings_section(
            'smdp_debug_section',
            'PWA Debug Mode',
            function() {
                echo '<p>Enable debug mode to bypass PWA caching during development. This adds a version parameter to all assets and shows a debug panel on the frontend.</p>';
            },
            'smdp_settings_page'
        );

        add_settings_field(
            'smdp_pwa_debug_mode',
            'Enable Debug Mode',
            function() {
                $enabled = get_option( 'smdp_pwa_debug_mode', 0 );
                echo '<label>';
                echo '<input type="checkbox" name="smdp_pwa_debug_mode" value="1" ' . checked( $enabled, 1, false ) . '>';
                echo ' Enable PWA Debug Mode (bypass caching, show debug panel)';
                echo '</label>';
            },
            'smdp_settings_page',
            'smdp_debug_section'
        );

        add_settings_field(
            'smdp_cache_version',
            'Cache Version',
            function() {
                $version = get_option( 'smdp_cache_version', 1 );
                echo '<input type="number" name="smdp_cache_version" value="' . esc_attr( $version ) . '" min="1" style="width:100px;">';
                echo '<p class="description">Increment this number to force all tablets to reload assets. Current: v' . $version . '</p>';
                echo '<button type="button" class="button" id="smdp-increment-version">Increment Version</button>';
                echo '<script>
                    jQuery("#smdp-increment-version").click(function(){
                        var $input = jQuery("input[name=smdp_cache_version]");
                        $input.val(parseInt($input.val()) + 1);
                    });
                </script>';
            },
            'smdp_settings_page',
            'smdp_debug_section'
        );
    }

    /**
     * Register advanced settings
     *
     * Adds advanced maintenance tools like flushing rewrite rules.
     */
    public function register_advanced_settings() {
        add_settings_section(
            'smdp_advanced_section',
            'Advanced Settings',
            function() {
                echo '<p>Advanced maintenance tools and troubleshooting options.</p>';
            },
            'smdp_settings_page'
        );

        // Add flush rewrite rules field
        add_settings_field(
            'smdp_flush_rewrite_rules',
            'Menu App URL',
            function() {
                $menu_app_url = home_url( '/menu-app/' );
                $flushed = get_transient( 'smdp_rewrite_rules_flushed' );

                echo '<p class="description">Standalone menu app URL: <strong><a href="' . esc_url( $menu_app_url ) . '" target="_blank">' . esc_html( $menu_app_url ) . '</a></strong></p>';

                if ( $flushed ) {
                    echo '<div class="notice notice-success inline" style="margin: 10px 0; padding: 10px;"><p><strong>Success!</strong> Rewrite rules have been flushed. The menu app URL should now work.</p></div>';
                }

                echo '<p class="description">If the menu app URL returns a 404 error, click the button below to fix it:</p>';
                echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=smdp_main&action=flush_rewrite_rules' ), 'smdp_flush_rewrite_rules' ) ) . '" class="button button-secondary">Fix Menu App URL (Flush Rewrite Rules)</a>';
                echo '<p class="description" style="margin-top: 10px;"><em>This re-registers WordPress URL routing rules. Safe to click anytime the menu app URL isn\'t working.</em></p>';
            },
            'smdp_settings_page',
            'smdp_advanced_section'
        );

        // Add rate limit clearing field
        add_settings_field(
            'smdp_clear_rate_limits',
            'Rate Limit Reset',
            function() {
                $cleared = get_transient( 'smdp_rate_limits_cleared' );

                if ( $cleared ) {
                    echo '<div class="notice notice-success inline" style="margin: 10px 0; padding: 10px;"><p><strong>Success!</strong> All rate limits have been cleared.</p></div>';
                }

                echo '<p class="description">If help/bill buttons are showing "Too many requests" errors, clear the rate limits:</p>';
                echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=smdp_main&action=clear_rate_limits' ), 'smdp_clear_rate_limits' ) ) . '" class="button button-secondary">Clear All Rate Limits</a>';
                echo '<p class="description" style="margin-top: 10px;"><em>This removes all rate limiting blocks. Safe to click if legitimate users are being blocked during testing.</em></p>';
            },
            'smdp_settings_page',
            'smdp_advanced_section'
        );
    }

    /**
     * Handle clear rate limits action
     *
     * Clears all rate limiting transients from the database.
     */
    public function handle_clear_rate_limits() {
        // Check if we're on the settings page with the clear action
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'smdp_main' ) {
            return;
        }

        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'clear_rate_limits' ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'smdp_clear_rate_limits' ) ) {
            wp_die( 'Security check failed' );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        // Clear all rate limit transients
        global $wpdb;
        $deleted = $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_smdp_rl_%' OR option_name LIKE '_transient_timeout_smdp_rl_%'" );

        error_log( "[SMDP] Cleared {$deleted} rate limit transients" );

        // Set transient to show success message
        set_transient( 'smdp_rate_limits_cleared', true, 30 );

        // Redirect back to settings page
        wp_safe_redirect( admin_url( 'admin.php?page=smdp_main' ) );
        exit;
    }

    /**
     * Handle flush rewrite rules action
     *
     * Processes the flush rewrite rules request from admin.
     */
    public function handle_flush_rewrite_rules() {
        // Check if we're on the settings page with the flush action
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'smdp_main' ) {
            return;
        }

        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'flush_rewrite_rules' ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'smdp_flush_rewrite_rules' ) ) {
            wp_die( 'Security check failed' );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        // Register rewrite rules before flushing
        if ( class_exists( 'SMDP_Plugin_Activation' ) ) {
            SMDP_Plugin_Activation::register_menu_app_rewrite_rules();
        }

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set transient to show success message
        set_transient( 'smdp_rewrite_rules_flushed', true, 30 );

        // Redirect back to settings page
        wp_safe_redirect( admin_url( 'admin.php?page=smdp_main' ) );
        exit;
    }
}

// Initialize the class
SMDP_Admin_Settings::instance();

/**
 * Backward compatibility wrapper function for help settings registration
 */
if ( ! function_exists( 'smdp_register_help_settings' ) ) {
    function smdp_register_help_settings() {
        SMDP_Admin_Settings::instance()->register_help_settings();
    }
}

/**
 * Backward compatibility wrapper function for debug settings registration
 */
if ( ! function_exists( 'smdp_register_debug_settings' ) ) {
    function smdp_register_debug_settings() {
        SMDP_Admin_Settings::instance()->register_debug_settings();
    }
}
