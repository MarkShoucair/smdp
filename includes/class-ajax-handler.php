<?php
/**
 * AJAX Handler Class
 *
 * Handles all AJAX requests for the Square Menu Display plugin.
 *
 * @package Square_Menu_Display
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SMDP_Ajax_Handler Class
 *
 * Registers and handles all AJAX endpoints for admin and frontend functionality.
 */
class SMDP_Ajax_Handler {

    /**
     * Instance of this class
     *
     * @var SMDP_Ajax_Handler
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return SMDP_Ajax_Handler
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Register all AJAX hooks
     */
    private function __construct() {
        $this->register_hooks();
    }

    /**
     * Register all AJAX action hooks
     */
    private function register_hooks() {
        // Category Management (Admin only)
        add_action( 'wp_ajax_smdp_delete_category', array( $this, 'delete_category' ) );
        add_action( 'wp_ajax_smdp_toggle_category_hidden', array( $this, 'toggle_category_hidden' ) );
        add_action( 'wp_ajax_smdp_add_category', array( $this, 'add_category' ) );
        add_action( 'wp_ajax_smdp_match_categories', array( $this, 'match_categories' ) );

        // Sold-Out Sync (Admin only)
        add_action( 'wp_ajax_smdp_sync_sold_out', array( $this, 'sync_sold_out' ) );

        // Frontend Sync/Refresh (Public + Admin)
        add_action( 'wp_ajax_nopriv_smdp_check_sync', array( $this, 'check_sync' ) );
        add_action( 'wp_ajax_smdp_check_sync', array( $this, 'check_sync' ) );

        add_action( 'wp_ajax_nopriv_smdp_refresh_menu', array( $this, 'refresh_menu' ) );
        add_action( 'wp_ajax_smdp_refresh_menu', array( $this, 'refresh_menu' ) );

        add_action( 'wp_ajax_smdp_check_version', array( $this, 'check_version' ) );
        add_action( 'wp_ajax_nopriv_smdp_check_version', array( $this, 'check_version' ) );

        // Items Status (Public + Admin)
        add_action( 'wp_ajax_smdp_get_items_status', array( $this, 'get_items_status' ) );
        add_action( 'wp_ajax_nopriv_smdp_get_items_status', array( $this, 'get_items_status' ) );

        // Debug Mode (Public + Admin)
        add_action( 'wp_ajax_smdp_get_pwa_debug_status', array( $this, 'get_pwa_debug_status' ) );
        add_action( 'wp_ajax_nopriv_smdp_get_pwa_debug_status', array( $this, 'get_pwa_debug_status' ) );

        add_action( 'wp_ajax_smdp_toggle_pwa_debug', array( $this, 'toggle_pwa_debug' ) );
        add_action( 'wp_ajax_nopriv_smdp_toggle_pwa_debug', array( $this, 'toggle_pwa_debug' ) );
    }

    // ========================================================================
    // CATEGORY MANAGEMENT AJAX HANDLERS
    // ========================================================================

    /**
     * AJAX: Delete a category
     */
    public function delete_category() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        check_ajax_referer( 'smdp_delete_category' );

        $category_id = smdp_sanitize_text_field( $_POST['category_id'], 50 ); // Internal category IDs
        $categories = get_option( SMDP_CATEGORIES_OPTION, array() );

        if ( isset( $categories[$category_id] ) ) {
            unset( $categories[$category_id] );
            update_option( SMDP_CATEGORIES_OPTION, $categories );
            wp_send_json_success();
        } else {
            wp_send_json_error( 'Category not found.' );
        }
    }

    /**
     * AJAX: Toggle category visibility (hidden/visible)
     */
    public function toggle_category_hidden() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        check_ajax_referer( 'smdp_toggle_category_hidden', '_ajax_nonce' );

        $category_id = smdp_sanitize_text_field( $_POST['category_id'], 50 ); // Internal category IDs
        $hidden = smdp_sanitize_text_field( $_POST['hidden'], 1 ); // "1" or "0"

        $categories = get_option( SMDP_CATEGORIES_OPTION, array() );
        if ( isset( $categories[$category_id] ) ) {
            $categories[$category_id]['hidden'] = ( $hidden === '1' ) ? true : false;
            update_option( SMDP_CATEGORIES_OPTION, $categories );
            wp_send_json_success( array( 'hidden' => $categories[$category_id]['hidden'] ) );
        } else {
            wp_send_json_error( 'Category not found.' );
        }
    }

    /**
     * AJAX: Add a category to the active list
     */
    public function add_category() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        check_ajax_referer( 'smdp_add_category' );

        $category_id = smdp_sanitize_text_field( $_POST['category_id'], 50 ); // Internal category IDs

        // Retrieve active categories
        $active_categories = get_option( SMDP_CATEGORIES_OPTION, array() );
        // Retrieve all categories from the sync
        $all_categories = get_option( SMDP_CATEGORIES_OPTION, array() );

        if ( isset( $all_categories[$category_id] ) ) {
            // Only add if not already active
            if ( ! isset( $active_categories[$category_id] ) ) {
                $active_categories[$category_id] = $all_categories[$category_id];
                update_option( SMDP_CATEGORIES_OPTION, $active_categories );
                wp_send_json_success( array( 'name' => $all_categories[$category_id]['name'] ) );
            } else {
                wp_send_json_error( 'Category already added.' );
            }
        } else {
            wp_send_json_error( 'Category not found.' );
        }
    }

    /**
     * AJAX: Match plugin categories with Square reporting categories
     */
    public function match_categories() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        check_ajax_referer( 'smdp_match_categories' );

        $items   = get_option( SMDP_ITEMS_OPTION, array() );
        $mapping = get_option( SMDP_MAPPING_OPTION, array() );

        foreach ( $items as $obj ) {
            if ( ! isset( $obj['type'] ) || $obj['type'] !== 'ITEM' ) {
                continue;
            }
            $id = $obj['id'];
            // Pull the reporting_category.id from the cached item_data
            $new_cat = $obj['item_data']['reporting_category']['id'] ?? '';
            if ( $new_cat !== '' ) {
                $mapping[$id]['category'] = $new_cat;
            }
        }
        update_option( SMDP_MAPPING_OPTION, $mapping );
        wp_send_json_success();
    }

    // ========================================================================
    // SOLD-OUT SYNC AJAX HANDLER
    // ========================================================================

    /**
     * AJAX: Sync sold-out status from Square to plugin overrides
     */
    public function sync_sold_out() {
        // Permissions & nonce
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        check_ajax_referer( 'smdp_sync_sold_out' );

        // Rate limiting: 3 requests per minute
        if ( smdp_is_rate_limited( 'sync_sold_out', 3, 60 ) ) {
            wp_send_json_error( 'Too many sync requests. Please wait a moment before trying again.' );
        }

        // Load mapping & cached catalog
        $mapping   = get_option( SMDP_MAPPING_OPTION, array() );
        $all_items = get_option( SMDP_ITEMS_OPTION, array() );

        // Loop items, detect sold_out via variation.location_overrides
        foreach ( $all_items as $obj ) {
            if ( empty( $obj['type'] ) || $obj['type'] !== 'ITEM' ) {
                continue;
            }
            $item_id   = $obj['id'];
            $item_data = $obj['item_data'];
            $is_sold   = false;

            if ( ! empty( $item_data['variations'] ) ) {
                foreach ( $item_data['variations'] as $var ) {
                    $ov_list = $var['item_variation_data']['location_overrides'] ?? array();
                    foreach ( $ov_list as $ov ) {
                        if ( ! empty( $ov['sold_out'] ) ) {
                            $is_sold = true;
                            break 2;
                        }
                    }
                }
            }

            if ( ! isset( $mapping[ $item_id ] ) ) {
                $mapping[ $item_id ] = array(
                    'category'          => 'unassigned',
                    'order'             => 0,
                    'hide_image'        => 0,
                    'sold_out_override' => '',
                );
            }

            // Write override
            $mapping[ $item_id ]['sold_out_override'] = $is_sold ? 'sold' : 'available';
        }

        // Save & respond
        update_option( SMDP_MAPPING_OPTION, $mapping );
        wp_send_json_success();
    }

    // ========================================================================
    // FRONTEND SYNC/REFRESH AJAX HANDLERS
    // ========================================================================

    /**
     * AJAX: Check if sync has happened and get item statuses
     */
    public function check_sync() {
        check_ajax_referer( 'smdp_refresh_nonce', 'nonce' );

        // Rate limiting: 20 requests per minute (lightweight endpoint)
        if ( smdp_is_rate_limited( 'check_sync', 20, 60 ) ) {
            wp_send_json_error( 'Too many check requests. Please slow down.' );
        }

        $last = (int) get_option( 'smdp_last_sync_timestamp', 0 );

        // Get all items and their sold-out statuses
        $all_items = get_option( SMDP_ITEMS_OPTION, array() );
        $mapping   = get_option( SMDP_MAPPING_OPTION, array() );
        $items_status = array();

        foreach ( $all_items as $obj ) {
            if ( empty( $obj['type'] ) || $obj['type'] !== 'ITEM' ) {
                continue;
            }

            $item_id   = $obj['id'];
            $item_data = $obj['item_data'];

            // Determine sold-out status using same logic as shortcode
            $is_sold = $this->determine_sold_out_status( $item_id, $item_data, $mapping );

            $items_status[] = array(
                'id'      => $item_id,
                'soldout' => $is_sold ? 1 : 0,
            );
        }

        wp_send_json_success( array(
            'last_sync' => $last,
            'items'     => $items_status,
        ) );
    }

    /**
     * AJAX: Return fresh HTML for a single menu container
     */
    public function refresh_menu() {
        check_ajax_referer( 'smdp_refresh_nonce', 'nonce' );

        // Rate limiting: 10 requests per 30 seconds (frontend users)
        if ( smdp_is_rate_limited( 'refresh_menu', 10, 30 ) ) {
            wp_send_json_error( 'Too many refresh requests. Please wait a moment.' );
        }

        // Grab the slug from data-menu-id
        $slug = sanitize_text_field( wp_unslash( $_POST['menu_id'] ) );

        // Build the exact same shortcode used in posts/pages
        $shortcode = sprintf( '[square_menu category="%s"]', $slug );

        // Run it through do_shortcode() to get the full HTML grid
        $html = do_shortcode( $shortcode );

        // Return that HTML as JSON
        wp_send_json_success( $html );
    }

    /**
     * AJAX: Lightweight version check (returns just version numbers)
     */
    public function check_version() {
        check_ajax_referer( 'smdp_refresh_nonce', 'nonce' );

        wp_send_json_success( array(
            'cache_version' => intval( get_option( 'smdp_cache_version', 1 ) ),
            'debug_mode'    => intval( get_option( 'smdp_pwa_debug_mode', 0 ) ),
        ) );
    }

    /**
     * AJAX: Get sold-out status for specific items
     */
    public function get_items_status() {
        check_ajax_referer( 'smdp_refresh_nonce', 'nonce' );

        // Get requested item IDs
        $item_ids = isset( $_POST['item_ids'] ) ? (array) $_POST['item_ids'] : array();

        if ( empty( $item_ids ) ) {
            wp_send_json_error( 'No item IDs provided' );
        }

        // Sanitize item IDs
        $item_ids = array_map( 'sanitize_text_field', $item_ids );

        // Get cached items and mapping
        $all_items = get_option( SMDP_ITEMS_OPTION, array() );
        $mapping = get_option( SMDP_MAPPING_OPTION, array() );

        $statuses = array();

        foreach ( $item_ids as $item_id ) {
            // Find the item in cache
            $item = null;
            foreach ( $all_items as $obj ) {
                if ( $obj['id'] === $item_id ) {
                    $item = $obj;
                    break;
                }
            }

            if ( ! $item || empty( $item['item_data'] ) ) {
                continue;
            }

            $item_data = $item['item_data'];

            // Determine sold-out status
            $is_sold = $this->determine_sold_out_status( $item_id, $item_data, $mapping );

            $statuses[$item_id] = array(
                'sold_out' => $is_sold
            );
        }

        wp_send_json_success( $statuses );
    }

    // ========================================================================
    // DEBUG MODE AJAX HANDLERS
    // ========================================================================

    /**
     * AJAX: Get current PWA debug status (read-only, no nonce required)
     */
    public function get_pwa_debug_status() {
        // No nonce check needed for read-only status
        $debug_mode = get_option( 'smdp_pwa_debug_mode', 0 );
        wp_send_json_success( array(
            'enabled' => (bool) $debug_mode
        ) );
    }

    /**
     * AJAX: Toggle PWA debug mode
     */
    public function toggle_pwa_debug() {
        // Verify nonce
        check_ajax_referer( 'smdp_get_bill', 'security' );

        // Get current status
        $current_mode = get_option( 'smdp_pwa_debug_mode', 0 );

        // Toggle it
        $new_mode = $current_mode ? 0 : 1;

        // Update the option
        update_option( 'smdp_pwa_debug_mode', $new_mode );

        // Also increment cache version when enabling debug mode
        if ( $new_mode ) {
            $cache_version = get_option( 'smdp_cache_version', 1 );
            update_option( 'smdp_cache_version', $cache_version + 1 );
        }

        // Log the change
        error_log( '[SMDP] PWA Debug mode toggled to: ' . ( $new_mode ? 'ENABLED' : 'DISABLED' ) );

        wp_send_json_success( array(
            'enabled' => (bool) $new_mode,
            'cache_version' => get_option( 'smdp_cache_version', 1 )
        ) );
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Determine if an item is sold out based on override and Square data
     *
     * @param string $item_id Item ID
     * @param array  $item_data Item data from Square
     * @param array  $mapping Item mappings
     * @return bool True if sold out, false otherwise
     */
    private function determine_sold_out_status( $item_id, $item_data, $mapping ) {
        $override = $mapping[$item_id]['sold_out_override'] ?? '';

        if ( $override === 'sold' ) {
            return true;
        } elseif ( $override === 'available' ) {
            return false;
        } else {
            // Check Square sold_out status via location_overrides
            $is_sold = false;
            if ( ! empty( $item_data['variations'] ) ) {
                foreach ( $item_data['variations'] as $var ) {
                    $ov_list = $var['item_variation_data']['location_overrides'] ?? array();
                    foreach ( $ov_list as $ov ) {
                        if ( ! empty( $ov['sold_out'] ) ) {
                            $is_sold = true;
                            break 2;
                        }
                    }
                }
            }
            return $is_sold;
        }
    }
}

// Initialize the AJAX handler
SMDP_Ajax_Handler::instance();
