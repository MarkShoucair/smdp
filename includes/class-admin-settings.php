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
