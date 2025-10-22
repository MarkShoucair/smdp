<?php
/**
 * Admin Assets Manager
 *
 * Handles registration and enqueuing of scripts and styles for admin and frontend.
 *
 * @package Square_Menu_Display
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SMDP_Admin_Assets
 *
 * Manages all script and style enqueuing for the plugin.
 */
class SMDP_Admin_Assets {

    /**
     * Singleton instance
     *
     * @var SMDP_Admin_Assets
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return SMDP_Admin_Assets
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
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_refresh_script' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_item_detail_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'add_cache_version' ), 999 );
    }

    /**
     * Enqueue admin scripts (jQuery UI Sortable)
     *
     * @param string $hook The current admin page hook.
     */
    public function admin_enqueue_scripts( $hook ) {
        if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'smdp_' ) !== false ) {
            wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_script( 'jquery-ui-dialog' );
            // REMOVED: External jQuery UI CSS from Google CDN
            // jQuery UI functionality works without the CSS (sortable doesn't require styling)
            // If UI styling is needed in future, we'll add minimal inline CSS
        }
    }

    /**
     * Enqueue refresh script for frontend
     *
     * Handles menu data refresh polling and cache version management.
     */
    public function enqueue_refresh_script() {
        wp_enqueue_script(
            'smdp-refresh',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/refresh.js',
            array( 'jquery' ),
            '1.1',
            true
        );

        // Get cache version and debug mode from settings
        $cache_version = get_option( 'smdp_cache_version', 1 );
        $debug_mode    = get_option( 'smdp_pwa_debug_mode', 0 );

        wp_localize_script(
            'smdp-refresh',
            'smdpRefresh',
            array(
                'ajaxurl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'smdp_refresh_nonce' ),
                'interval'     => 30000,
                'cacheVersion' => intval( $cache_version ),
                'debugMode'    => intval( $debug_mode ),
                'pluginUrl'    => plugin_dir_url( dirname( __FILE__ ) ), // Dynamic plugin URL
            )
        );
    }

    /**
     * Enqueue item detail popup assets
     *
     * Handles the modal popup for viewing item details on the frontend.
     */
    public function enqueue_item_detail_assets() {
        // JS (in footer)
        wp_enqueue_script(
            'smdp-item-detail',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/item-detail.js',
            array( 'jquery' ),
            '1.0',
            true
        );

        // Pass custom Item Detail styles to JavaScript
        $item_detail_styles = get_option( 'smdp_app_item_detail_styles', array() );

        // Defaults (matches hardcoded values in item-detail.js)
        $defaults = array(
            'modal_bg' => '#ffffff',
            'modal_border_color' => '#3498db',
            'modal_border_width' => 6,
            'modal_border_radius' => 12,
            'modal_box_shadow' => '0 0 30px rgba(52,152,219,0.6), 0 0 60px rgba(52,152,219,0.4)',
            'title_color' => '#000000',
            'title_size' => 24,
            'title_weight' => 'bold',
            'price_color' => '#27ae60',
            'price_size' => 19,
            'price_weight' => 'bold',
            'desc_color' => '#666666',
            'desc_size' => 16,
            'close_btn_bg' => '#3498db',
            'close_btn_text' => '#ffffff',
            'close_btn_hover_bg' => '#2980b9',
        );

        $styles = array_merge( $defaults, $item_detail_styles );

        // Localize script with custom styles
        wp_localize_script(
            'smdp-item-detail',
            'smdpItemDetailStyles',
            $styles
        );

        // CSS (in head)
        wp_enqueue_style(
            'smdp-item-detail',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/item-detail.css',
            array(),
            '1.0'
        );
    }

    /**
     * Add cache-busting version to scripts and styles
     *
     * When debug mode is enabled, adds a dynamic version parameter to force cache refresh.
     */
    public function add_cache_version() {
        $debug_mode    = get_option( 'smdp_pwa_debug_mode', 0 );
        $cache_version = get_option( 'smdp_cache_version', 1 );

        if ( $debug_mode ) {
            global $wp_scripts, $wp_styles;

            // Add version parameter to all plugin scripts
            foreach ( $wp_scripts->registered as $handle => $script ) {
                if ( strpos( $script->src, 'square-menu-display' ) !== false ) {
                    $wp_scripts->registered[ $handle ]->ver = $cache_version . '.' . time();
                }
            }

            // Add version parameter to all plugin styles
            foreach ( $wp_styles->registered as $handle => $style ) {
                if ( strpos( $style->src, 'square-menu-display' ) !== false ) {
                    $wp_styles->registered[ $handle ]->ver = $cache_version . '.' . time();
                }
            }
        }
    }
}

// Initialize the class
SMDP_Admin_Assets::instance();

/**
 * Backward compatibility wrapper functions
 */

if ( ! function_exists( 'smdp_admin_enqueue_scripts' ) ) {
    function smdp_admin_enqueue_scripts( $hook ) {
        SMDP_Admin_Assets::instance()->admin_enqueue_scripts( $hook );
    }
}

if ( ! function_exists( 'smdp_enqueue_refresh_script' ) ) {
    function smdp_enqueue_refresh_script() {
        SMDP_Admin_Assets::instance()->enqueue_refresh_script();
    }
}

if ( ! function_exists( 'smdp_enqueue_item_detail_assets' ) ) {
    function smdp_enqueue_item_detail_assets() {
        SMDP_Admin_Assets::instance()->enqueue_item_detail_assets();
    }
}

if ( ! function_exists( 'smdp_add_cache_version' ) ) {
    function smdp_add_cache_version() {
        SMDP_Admin_Assets::instance()->add_cache_version();
    }
}
