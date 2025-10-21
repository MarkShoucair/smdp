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
        add_action( 'wp_ajax_smdp_toggle_category_hidden', array( $this, 'toggle_category_hidden' ) );
        add_action( 'wp_ajax_smdp_add_category', array( $this, 'add_category' ) );
        add_action( 'wp_ajax_smdp_create_category', array( $this, 'create_category' ) );
        add_action( 'wp_ajax_smdp_delete_category', array( $this, 'delete_category' ) );
        add_action( 'wp_ajax_smdp_match_categories', array( $this, 'match_categories' ) );
        add_action( 'wp_ajax_smdp_save_cat_order', array( $this, 'save_category_order' ) );
        add_action( 'wp_ajax_smdp_cleanup_duplicates', array( $this, 'cleanup_duplicates' ) );

        // Sold-Out Management (Admin only)
        add_action( 'wp_ajax_smdp_sync_sold_out', array( $this, 'sync_sold_out' ) );
        add_action( 'wp_ajax_smdp_update_sold_out_override', array( $this, 'update_sold_out_override' ) );

        // Frontend Sync/Refresh (Public + Admin)
        add_action( 'wp_ajax_nopriv_smdp_check_sync', array( $this, 'check_sync' ) );
        add_action( 'wp_ajax_smdp_check_sync', array( $this, 'check_sync' ) );

        add_action( 'wp_ajax_nopriv_smdp_refresh_menu', array( $this, 'refresh_menu' ) );
        add_action( 'wp_ajax_smdp_refresh_menu', array( $this, 'refresh_menu' ) );

        add_action( 'wp_ajax_nopriv_smdp_get_sold_out_status', array( $this, 'get_sold_out_status' ) );
        add_action( 'wp_ajax_smdp_get_sold_out_status', array( $this, 'get_sold_out_status' ) );

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
     * AJAX: Delete a custom category
     */
    public function delete_category() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        check_ajax_referer( 'smdp_delete_category' );

        $category_id = isset( $_POST['category_id'] ) ? sanitize_text_field( $_POST['category_id'] ) : '';

        if ( empty( $category_id ) ) {
            wp_send_json_error( 'Category ID is required.' );
        }

        // Only allow deletion of custom categories (those starting with 'cat_')
        if ( strpos( $category_id, 'cat_' ) !== 0 ) {
            wp_send_json_error( 'Cannot delete Square-synced categories. Only custom categories can be deleted.' );
        }

        // Load categories
        $categories = get_option( SMDP_CATEGORIES_OPTION, array() );
        if ( ! is_array( $categories ) ) {
            $categories = array();
        }

        if ( ! isset( $categories[ $category_id ] ) ) {
            wp_send_json_error( 'Category not found.' );
        }

        // Remove category
        unset( $categories[ $category_id ] );
        update_option( SMDP_CATEGORIES_OPTION, $categories );

        // Remove all items from this category in the mapping
        $mapping = get_option( SMDP_MAPPING_OPTION, array() );
        if ( is_array( $mapping ) ) {
            $updated_mapping = array();
            foreach ( $mapping as $key => $data ) {
                // Keep only items not in the deleted category
                if ( isset( $data['category'] ) && $data['category'] !== $category_id ) {
                    $updated_mapping[ $key ] = $data;
                }
            }
            update_option( SMDP_MAPPING_OPTION, $updated_mapping );
        }

        wp_send_json_success( array(
            'message' => 'Category deleted successfully.'
        ) );
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
     * AJAX: Create a new category from Menu Editor
     */
    public function create_category() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        check_ajax_referer( 'smdp_create_category' );

        $name = isset( $_POST['name'] ) ? smdp_sanitize_text_field( $_POST['name'], 100 ) : '';

        if ( empty( $name ) ) {
            wp_send_json_error( 'Category name is required.' );
        }

        // Generate slug from name
        $slug = sanitize_title( $name );

        // Load existing categories
        $categories = get_option( SMDP_CATEGORIES_OPTION, array() );
        if ( ! is_array( $categories ) ) {
            $categories = array();
        }

        // Create unique ID
        $id = 'cat_' . time();

        // Create new category
        $categories[ $id ] = array(
            'id'    => $id,
            'name'  => $name,
            'slug'  => $slug,
            'order' => count( $categories ) + 1,
            'hidden' => false
        );

        // Save
        update_option( SMDP_CATEGORIES_OPTION, $categories );

        wp_send_json_success( array(
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'message' => 'Category created successfully.'
        ) );
    }

    /**
     * AJAX: Save category order from Menu Editor
     */
    public function save_category_order() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        check_ajax_referer( 'smdp_cat_order' );

        $order_json = isset( $_POST['order'] ) ? $_POST['order'] : '';
        $order_data = json_decode( stripslashes( $order_json ), true );

        if ( ! is_array( $order_data ) ) {
            wp_send_json_error( 'Invalid order data.' );
        }

        // Load categories
        $categories = get_option( SMDP_CATEGORIES_OPTION, array() );
        if ( ! is_array( $categories ) ) {
            $categories = array();
        }

        // Update order for each category
        foreach ( $order_data as $item ) {
            if ( isset( $item['id'] ) && isset( $item['order'] ) ) {
                $cat_id = $item['id'];
                if ( isset( $categories[ $cat_id ] ) ) {
                    $categories[ $cat_id ]['order'] = intval( $item['order'] );
                }
            }
        }

        // Save updated categories
        update_option( SMDP_CATEGORIES_OPTION, $categories );

        // Trigger menu cache rebuild to apply new category order
        if ( class_exists( 'SMDP_Menu_App_Builder' ) ) {
            SMDP_Menu_App_Builder::rest_bootstrap_from_cache( null );
        }

        wp_send_json_success( 'Category order saved successfully.' );
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

        // Check if this is new-style mapping
        $is_new_style = false;
        foreach ($mapping as $key => $data) {
            if (isset($data['instance_id'])) {
                $is_new_style = true;
                break;
            }
        }

        if ($is_new_style) {
            // New-style: Update all instances of each item to match Square category
            foreach ( $items as $obj ) {
                if ( ! isset( $obj['type'] ) || $obj['type'] !== 'ITEM' ) {
                    continue;
                }
                $item_id = $obj['id'];
                $new_cat = $obj['item_data']['reporting_category']['id'] ?? '';

                if ( $new_cat !== '' ) {
                    // Update all instances of this item
                    foreach ($mapping as $instance_id => &$map_data) {
                        if (isset($map_data['item_id']) && $map_data['item_id'] === $item_id) {
                            $map_data['category'] = $new_cat;
                        }
                    }
                    unset($map_data); // Break reference
                }
            }
        } else {
            // Old-style: Direct update
            foreach ( $items as $obj ) {
                if ( ! isset( $obj['type'] ) || $obj['type'] !== 'ITEM' ) {
                    continue;
                }
                $id = $obj['id'];
                $new_cat = $obj['item_data']['reporting_category']['id'] ?? '';
                if ( $new_cat !== '' ) {
                    $mapping[$id]['category'] = $new_cat;
                }
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

        // Check if this is new-style mapping
        $is_new_style = false;
        foreach ($mapping as $key => $data) {
            if (isset($data['instance_id'])) {
                $is_new_style = true;
                break;
            }
        }

        // Reset all sold-out overrides to Auto (empty string)
        // This makes all items automatically use Square's sold-out status
        if ($is_new_style) {
            // New-style: Reset all instances
            foreach ($mapping as $instance_id => &$map_data) {
                $map_data['sold_out_override'] = '';
            }
            unset($map_data); // Break reference
        } else {
            // Old-style: Reset all items
            foreach ($mapping as $item_id => &$map_data) {
                $map_data['sold_out_override'] = '';
            }
            unset($map_data); // Break reference
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
     * AJAX: Get sold-out status only (lightweight, no HTML generation)
     * Returns array of sold-out item IDs for client-side badge updates
     */
    public function get_sold_out_status() {
        check_ajax_referer( 'smdp_refresh_nonce', 'nonce' );

        // Rate limiting: 20 requests per 30 seconds (more lenient since it's lightweight)
        if ( smdp_is_rate_limited( 'get_sold_out_status', 20, 30 ) ) {
            wp_send_json_error( 'Too many status requests. Please wait a moment.' );
        }

        // Get the category slug
        $slug = isset( $_POST['menu_id'] ) ? sanitize_text_field( wp_unslash( $_POST['menu_id'] ) ) : '';

        if ( empty( $slug ) ) {
            wp_send_json_error( 'No menu ID provided' );
        }

        // Get all items and mappings
        $all_items = get_option( SMDP_ITEMS_OPTION, array() );
        $mapping = get_option( SMDP_MAPPING_OPTION, array() );
        $categories = get_option( SMDP_CATEGORIES_OPTION, array() );

        // Find the category by slug
        $category_id = null;
        foreach ( $categories as $cat_id => $cat_data ) {
            if ( isset( $cat_data['slug'] ) && $cat_data['slug'] === $slug ) {
                $category_id = $cat_id;
                break;
            }
        }

        if ( ! $category_id ) {
            wp_send_json_error( 'Category not found' );
        }

        // Find all items in this category that are sold out
        // Also build a content hash to detect menu changes
        $sold_out_items = array();
        $content_data = array(); // For hash generation

        // Check if this is new-style mapping (with instance_id) or old-style
        $is_new_style = false;
        foreach ($mapping as $key => $data) {
            if (isset($data['instance_id'])) {
                $is_new_style = true;
                break;
            }
        }

        // Build items lookup for efficiency
        $items_by_id = array();
        foreach ( $all_items as $obj ) {
            if ( isset( $obj['type'] ) && $obj['type'] === 'ITEM' ) {
                $items_by_id[$obj['id']] = $obj;
            }
        }

        foreach ( $mapping as $key => $map_data ) {
            // Check if item is in this category
            if ( isset( $map_data['category'] ) && $map_data['category'] === $category_id ) {

                // Get the actual item_id (depends on mapping style)
                $item_id = $is_new_style ? $map_data['item_id'] : $key;
                $instance_id = $is_new_style ? $key : $item_id;

                // Find the actual item data
                $item_obj = $items_by_id[$item_id] ?? null;

                if ( $item_obj ) {
                    $item_data = $item_obj['item_data'] ?? array();

                    // Check if sold out using same logic as shortcode
                    // For new-style mapping, we need to check override by instance_id
                    $override_key = $is_new_style ? $instance_id : $item_id;
                    $is_sold = $this->determine_sold_out_status( $override_key, $item_data, $mapping );
                    if ( $is_sold ) {
                        // Return instance_id for new-style, item_id for old-style
                        $sold_out_items[] = $instance_id;
                    }

                    // Build content signature for change detection
                    // Include: item name, description, price, variations, order
                    $content_data[] = array(
                        'id' => $instance_id, // Use instance_id for consistency
                        'name' => $item_data['name'] ?? '',
                        'description' => $item_data['description'] ?? '',
                        'variations' => wp_json_encode( $item_data['variations'] ?? array() ),
                        'order' => $map_data['order'] ?? 0,
                    );
                }
            }
        }

        // Generate content hash to detect if menu structure changed
        // If hash differs from client's cached hash, client should do full refresh
        $content_hash = md5( wp_json_encode( $content_data ) );

        wp_send_json_success( array(
            'sold_out_items' => $sold_out_items,
            'category' => $slug,
            'count' => count( $sold_out_items ),
            'content_hash' => $content_hash, // New: for change detection
            'item_count' => count( $content_data ), // New: for quick count check
        ) );
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

    /**
     * Update sold-out override for a specific item
     *
     * AJAX handler for updating item sold-out status override
     */
    public function update_sold_out_override() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        check_ajax_referer( 'smdp_update_sold_out', '_ajax_nonce' );

        $item_id = isset( $_POST['item_id'] ) ? sanitize_text_field( $_POST['item_id'] ) : '';
        $override = isset( $_POST['override'] ) ? sanitize_text_field( $_POST['override'] ) : '';

        if ( empty( $item_id ) ) {
            wp_send_json_error( 'Item ID is required.' );
        }

        // Validate override value
        if ( ! in_array( $override, array( '', 'sold', 'available' ), true ) ) {
            wp_send_json_error( 'Invalid override value.' );
        }

        // Get current mapping
        $mapping = get_option( SMDP_MAPPING_OPTION, array() );

        // Check if this is new-style or old-style mapping
        $is_new_style = false;
        foreach ( $mapping as $key => $data ) {
            if ( isset( $data['instance_id'] ) ) {
                $is_new_style = true;
                break;
            }
        }

        if ( $is_new_style ) {
            // New-style: Update sold_out_override for all instances of this item
            foreach ( $mapping as $instance_id => &$map_data ) {
                if ( isset( $map_data['item_id'] ) && $map_data['item_id'] === $item_id ) {
                    $map_data['sold_out_override'] = $override;
                }
            }
            unset( $map_data ); // Break reference
        } else {
            // Old-style: Direct update
            if ( ! isset( $mapping[ $item_id ] ) ) {
                $mapping[ $item_id ] = array(
                    'category'          => '',
                    'order'             => 0,
                    'hide_image'        => 0,
                    'sold_out_override' => '',
                );
            }
            $mapping[ $item_id ]['sold_out_override'] = $override;
        }

        update_option( SMDP_MAPPING_OPTION, $mapping );

        wp_send_json_success( array(
            'message' => 'Sold-out override updated successfully.',
            'item_id' => $item_id,
            'override' => $override,
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

    /**
     * AJAX: Cleanup duplicate items in categories
     */
    public function cleanup_duplicates() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        check_ajax_referer( 'smdp_cleanup_duplicates' );

        $mapping = get_option( SMDP_MAPPING_OPTION, array() );

        // Track items by category
        $items_in_categories = array();
        $cleaned_mapping = array();
        $removed_count = 0;

        foreach ( $mapping as $key => $data ) {
            if ( ! isset( $data['item_id'] ) || ! isset( $data['category'] ) ) {
                continue;
            }

            $item_id = $data['item_id'];
            $category = $data['category'];
            $combo_key = $item_id . '|' . $category;

            // If this item+category combination already exists, skip it (it's a duplicate)
            if ( isset( $items_in_categories[ $combo_key ] ) ) {
                $removed_count++;
                continue; // Don't add to cleaned mapping
            }

            // Mark this combination as seen
            $items_in_categories[ $combo_key ] = true;

            // Keep this entry
            $cleaned_mapping[ $key ] = $data;
        }

        update_option( SMDP_MAPPING_OPTION, $cleaned_mapping );

        wp_send_json_success( array(
            'message' => "Removed {$removed_count} duplicate items.",
            'removed_count' => $removed_count
        ) );
    }
}

// Initialize the AJAX handler
SMDP_Ajax_Handler::instance();
