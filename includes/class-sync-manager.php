<?php
/**
 * Sync Manager Class
 *
 * Handles synchronization of catalog data from Square API.
 *
 * @package Square_Menu_Display
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SMDP_Sync_Manager Class
 *
 * Manages cron scheduling and catalog synchronization with Square API.
 */
class SMDP_Sync_Manager {

    /**
     * Instance of this class
     *
     * @var SMDP_Sync_Manager
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return SMDP_Sync_Manager
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
     * Register all sync-related hooks
     */
    private function register_hooks() {
        // Add custom cron schedule
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedule' ) );

        // Register sync callback
        add_action( SMDP_CRON_HOOK, array( $this, 'sync_items' ) );

        // Display admin notices for sync errors
        add_action( 'admin_notices', array( $this, 'display_sync_errors' ) );
    }

    /**
     * Add custom cron schedule based on plugin settings
     *
     * @param array $schedules Existing cron schedules
     * @return array Modified schedules
     */
    public function add_cron_schedule( $schedules ) {
        $custom_interval = get_option( SMDP_SYNC_INTERVAL, 3600 );
        $schedules['smdp_custom_interval'] = array(
            'interval' => intval( $custom_interval ),
            'display'  => __( 'Square Menu Custom Interval' ),
        );
        return $schedules;
    }

    /**
     * Display admin notices for sync errors
     *
     * Shows persistent error messages when catalog sync fails
     */
    public function display_sync_errors() {
        // Only show on plugin admin pages
        if ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'smdp' ) !== 0 ) {
            return;
        }

        // Check for sync error transient
        $error_message = get_transient( 'smdp_sync_error' );

        if ( $error_message ) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong>Square Menu Sync Error:</strong> <?php echo esc_html( $error_message ); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=smdp_main' ) ); ?>" class="button">
                        Check Settings
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=smdp_api_log' ) ); ?>" class="button">
                        View API Log
                    </a>
                </p>
            </div>
            <?php

            // Delete transient after displaying (only show once per error)
            delete_transient( 'smdp_sync_error' );
        }
    }

    /**
     * Sync items from Square API
     *
     * Fetches catalog data (items, images, categories, modifier lists),
     * processes them, and updates WordPress options.
     */
    public function sync_items() {
        error_log('[SMDP Sync] sync_items() called at ' . date_i18n('c'));

        $access_token = smdp_get_access_token();
        if ( empty( $access_token ) ) {
            error_log('[SMDP Sync] ERROR: No access token found');

            // Store error for admin notice
            set_transient( 'smdp_sync_error', 'No Square access token configured. Please add your token in settings.', 300 );

            return;
        }

        error_log('[SMDP Sync] Access token found, starting catalog fetch');

        $headers = array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
        );

        // Fetch all catalog objects with pagination
        $all_objects = $this->fetch_catalog( $headers );

        // Check if fetch was successful
        if ( is_wp_error( $all_objects ) ) {
            error_log('[SMDP Sync] ERROR: Catalog fetch failed - ' . $all_objects->get_error_message());

            // Store error for admin notice
            set_transient( 'smdp_sync_error', 'Square API error: ' . $all_objects->get_error_message(), 300 );

            return;
        }

        if ( empty( $all_objects ) ) {
            error_log('[SMDP Sync] WARNING: No catalog objects returned from Square');

            // Store warning for admin notice
            set_transient( 'smdp_sync_error', 'No items returned from Square API. Check your access token permissions.', 300 );

            return;
        }

        error_log('[SMDP Sync] Fetched ' . count($all_objects) . ' catalog objects');

        // Remove deleted items
        $all_objects = $this->filter_deleted( $all_objects );
        error_log('[SMDP Sync] After filtering deleted: ' . count($all_objects) . ' objects remain');

        // Cache the full catalog (don't autoload - can be large)
        update_option( SMDP_ITEMS_OPTION, $all_objects, false );
        error_log('[SMDP Sync] Cached catalog objects to ' . SMDP_ITEMS_OPTION);

        // Update item mappings
        $this->update_item_mappings( $all_objects );
        error_log('[SMDP Sync] Updated item mappings');

        // Process categories
        $this->process_categories( $all_objects );
        error_log('[SMDP Sync] Processed categories');

        // Auto-sync sold-out status
        $this->sync_sold_out_status( $all_objects );
        error_log('[SMDP Sync] Synced sold-out status');

        // Log API response
        $this->log_api_response( $headers );

        // Update last sync timestamp
        update_option( 'smdp_last_sync_timestamp', time() );
        error_log('[SMDP Sync] Sync completed successfully at ' . date_i18n('c'));
    }

    /**
     * Last API response body for logging
     *
     * @var string
     */
    private $last_api_response = '';

    /**
     * Fetch catalog from Square API with pagination
     *
     * @param array $headers HTTP headers for API request
     * @return array|WP_Error All catalog objects or WP_Error on failure
     */
    private function fetch_catalog( $headers ) {
        $all_objects = array();
        $cursor = null;
        $catalog_url = '';
        $page_count = 0;
        $max_pages = 100; // Prevent infinite loops

        do {
            $page_count++;

            // Safety: prevent infinite loops
            if ( $page_count > $max_pages ) {
                error_log( '[SMDP Sync] WARNING: Reached max pagination limit' );
                break;
            }

            $catalog_url = 'https://connect.squareup.com/v2/catalog/list?types=ITEM,IMAGE,CATEGORY,MODIFIER_LIST';
            if ( $cursor ) {
                $catalog_url .= '&cursor=' . urlencode( $cursor );
            }

            $response = wp_remote_get( $catalog_url, array( 'headers' => $headers, 'timeout' => 30 ) );

            if ( is_wp_error( $response ) ) {
                return new WP_Error(
                    'api_connection_failed',
                    'Failed to connect to Square API: ' . $response->get_error_message()
                );
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );

            // Store last response for logging (no extra API call needed)
            $this->last_api_response = $body;

            // Check for HTTP errors
            if ( $response_code !== 200 ) {
                return new WP_Error(
                    'api_error',
                    sprintf( 'Square API returned error code %d', $response_code )
                );
            }

            $data = json_decode( $body, true );

            // Check for JSON decode errors
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                return new WP_Error(
                    'json_decode_failed',
                    'Failed to parse Square API response: ' . json_last_error_msg()
                );
            }

            // Check for API errors in response
            if ( isset( $data['errors'] ) && ! empty( $data['errors'] ) ) {
                $error_msg = $data['errors'][0]['detail'] ?? 'Unknown API error';
                return new WP_Error( 'square_api_error', $error_msg );
            }

            if ( ! empty( $data['objects'] ) ) {
                $all_objects = array_merge( $all_objects, $data['objects'] );
            }

            $cursor = isset( $data['cursor'] ) ? $data['cursor'] : null;
        } while ( $cursor );

        return $all_objects;
    }

    /**
     * Filter out deleted objects
     *
     * @param array $objects Catalog objects
     * @return array Filtered objects
     */
    private function filter_deleted( $objects ) {
        $filtered = array_filter( $objects, function( $obj ) {
            return empty( $obj['is_deleted'] );
        });
        return array_values( $filtered );
    }

    /**
     * Update item mappings with reporting categories
     *
     * @param array $objects Catalog objects
     */
    private function update_item_mappings( $objects ) {
        $existing_mapping = get_option( SMDP_MAPPING_OPTION, array() );
        $new_mapping = $existing_mapping;

        // First pass: Build list of valid category IDs from CATEGORY objects
        $valid_category_ids = array();
        foreach ( $objects as $obj ) {
            if ( $obj['type'] === 'CATEGORY' ) {
                $valid_category_ids[] = $obj['id'];
            }
        }

        // Second pass: Assign items to categories
        foreach ( $objects as $obj ) {
            if ( $obj['type'] === 'ITEM' ) {
                $item_id = $obj['id'];
                $cat = 'unassigned';

                // Priority 1: Use display categories array (first category if multiple)
                // BUT only if that category actually exists in our catalog
                if ( ! empty( $obj['item_data']['categories'] ) && is_array( $obj['item_data']['categories'] ) ) {
                    foreach ( $obj['item_data']['categories'] as $category_ref ) {
                        if ( in_array( $category_ref['id'], $valid_category_ids, true ) ) {
                            $cat = $category_ref['id'];
                            break; // Use first valid category
                        }
                    }
                }

                // Priority 2: If no valid display category found, try reporting category
                if ( $cat === 'unassigned' && ! empty( $obj['item_data']['reporting_category']['id'] ) ) {
                    $reporting_cat_id = $obj['item_data']['reporting_category']['id'];
                    if ( in_array( $reporting_cat_id, $valid_category_ids, true ) ) {
                        $cat = $reporting_cat_id;
                    }
                }

                // Create mapping entry for new items only (preserve existing mappings)
                if ( ! isset( $existing_mapping[ $item_id ] ) ) {
                    $new_mapping[ $item_id ] = array(
                        'category'   => $cat,
                        'order'      => 0,
                        'hide_image' => 0,
                    );
                }
            }
        }

        update_option( SMDP_MAPPING_OPTION, $new_mapping, false );
    }

    /**
     * Process category objects
     *
     * Filters, deduplicates by slug, and preserves existing flags.
     * IMPORTANT: Preserves custom categories (IDs starting with 'cat_')
     *
     * @param array $objects Catalog objects
     */
    private function process_categories( $objects ) {
        $existing_categories = get_option( SMDP_CATEGORIES_OPTION, array() );
        $by_slug = array();
        $final_categories = array();

        // First, preserve all custom categories (created manually, not from Square)
        foreach ( $existing_categories as $cat_id => $cat_data ) {
            if ( strpos( $cat_id, 'cat_' ) === 0 ) {
                // This is a custom category - preserve it
                $final_categories[ $cat_id ] = $cat_data;
                // Track slug to prevent duplicates
                if ( isset( $cat_data['slug'] ) ) {
                    $by_slug[ $cat_data['slug'] ] = $cat_id;
                }
            }
        }

        // Now process Square categories
        foreach ( $objects as $obj ) {
            if ( isset( $obj['type'] ) && $obj['type'] === 'CATEGORY' ) {
                $cd   = $obj['category_data'] ?? array();
                $name = $cd['name'] ?? 'Unnamed Category';
                $slug = sanitize_title( $name );
                $id   = $obj['id'];

                // Skip duplicates by slug
                if ( isset( $by_slug[ $slug ] ) ) {
                    continue;
                }

                $by_slug[ $slug ] = $id;

                // Preserve existing flags
                $hidden = $existing_categories[$id]['hidden'] ?? false;
                $order  = $existing_categories[$id]['order']  ?? null;

                $final_categories[$id] = compact( 'id', 'name', 'slug', 'hidden', 'order' );
            }
        }

        update_option( SMDP_CATEGORIES_OPTION, $final_categories, false );
    }

    /**
     * Auto-sync sold-out status from Square data
     *
     * @param array $objects Catalog objects
     */
    private function sync_sold_out_status( $objects ) {
        $mapping = get_option( SMDP_MAPPING_OPTION, array() );

        foreach ( $objects as $obj ) {
            if ( $obj['type'] !== 'ITEM' ) {
                continue;
            }

            $item_id = $obj['id'];
            $data    = $obj['item_data'];
            $is_sold = false;

            // Check location_overrides for sold_out flag
            if ( ! empty( $data['variations'] ) ) {
                foreach ( $data['variations'] as $var ) {
                    $ov_list = $var['item_variation_data']['location_overrides'] ?? array();
                    foreach ( $ov_list as $ov ) {
                        if ( ! empty( $ov['sold_out'] ) ) {
                            $is_sold = true;
                            break 2;
                        }
                    }
                }
            }

            // Initialize mapping if needed
            if ( ! isset( $mapping[ $item_id ] ) ) {
                $mapping[ $item_id ] = array(
                    'category'          => 'unassigned',
                    'order'             => 0,
                    'hide_image'        => 0,
                    'sold_out_override' => '',
                );
            }

            // Only update sold-out status if set to auto-sync (empty string)
            // Non-empty values ('sold' or 'available') are manual overrides - don't touch them
            $current_override = $mapping[ $item_id ]['sold_out_override'] ?? '';
            if ( $current_override === '' ) {
                // Auto mode: update based on Square status, but keep it as empty to maintain auto mode
                // The display logic will check Square data when override is empty
                // So we don't need to set it here - leave it empty for true auto-sync
            }
            // If current_override is 'sold' or 'available', it's manual - don't change it
        }

        update_option( SMDP_MAPPING_OPTION, $mapping, false );
    }

    /**
     * Log API response for debugging
     *
     * Uses the last stored response from fetch_catalog() instead of making a new API call
     *
     * @param array $headers HTTP headers used in request (kept for compatibility)
     */
    private function log_api_response( $headers ) {
        // Use stored response instead of making duplicate API call
        if ( empty( $this->last_api_response ) ) {
            return;
        }

        $catalog_url = 'https://connect.squareup.com/v2/catalog/list?types=ITEM,IMAGE,CATEGORY,MODIFIER_LIST';

        $api_log = get_option( SMDP_API_LOG_OPTION, array() );
        $api_log_entry = array(
            'timestamp'        => current_time( 'mysql' ),
            'catalog_request'  => $catalog_url,
            'catalog_response' => $this->last_api_response,
        );

        array_unshift( $api_log, $api_log_entry );

        // Keep only last 10 entries
        if ( count( $api_log ) > 10 ) {
            $api_log = array_slice( $api_log, 0, 10 );
        }

        update_option( SMDP_API_LOG_OPTION, $api_log, false );
    }
}

// Initialize the sync manager
SMDP_Sync_Manager::instance();

/**
 * Wrapper function for backward compatibility
 * Calls the sync_items method from the Sync Manager singleton
 */
if ( ! function_exists( 'smdp_sync_items' ) ) {
    function smdp_sync_items() {
        SMDP_Sync_Manager::instance()->sync_items();
    }
}
