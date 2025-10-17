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
            return;
        }

        error_log('[SMDP Sync] Access token found, starting catalog fetch');

        $headers = array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type'  => 'application/json',
        );

        // Fetch all catalog objects with pagination
        $all_objects = $this->fetch_catalog( $headers );
        error_log('[SMDP Sync] Fetched ' . count($all_objects) . ' catalog objects');

        // Remove deleted items
        $all_objects = $this->filter_deleted( $all_objects );
        error_log('[SMDP Sync] After filtering deleted: ' . count($all_objects) . ' objects remain');

        // Cache the full catalog
        update_option( SMDP_ITEMS_OPTION, $all_objects );
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
     * Fetch catalog from Square API with pagination
     *
     * @param array $headers HTTP headers for API request
     * @return array All catalog objects
     */
    private function fetch_catalog( $headers ) {
        $all_objects = array();
        $cursor = null;
        $catalog_url = '';

        do {
            $catalog_url = 'https://connect.squareup.com/v2/catalog/list?types=ITEM,IMAGE,CATEGORY,MODIFIER_LIST';
            if ( $cursor ) {
                $catalog_url .= '&cursor=' . urlencode( $cursor );
            }

            $response = wp_remote_get( $catalog_url, array( 'headers' => $headers ) );
            if ( is_wp_error( $response ) ) {
                break;
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

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

        foreach ( $objects as $obj ) {
            if ( $obj['type'] === 'ITEM' ) {
                $item_id = $obj['id'];
                $cat = 'unassigned';

                if ( ! empty( $obj['item_data']['reporting_category']['id'] ) ) {
                    $cat = $obj['item_data']['reporting_category']['id'];
                }

                if ( ! isset( $existing_mapping[ $item_id ] ) ) {
                    $new_mapping[ $item_id ] = array(
                        'category'   => $cat,
                        'order'      => 0,
                        'hide_image' => 0,
                    );
                }
            }
        }

        update_option( SMDP_MAPPING_OPTION, $new_mapping );
    }

    /**
     * Process category objects
     *
     * Filters, deduplicates by slug, and preserves existing flags.
     *
     * @param array $objects Catalog objects
     */
    private function process_categories( $objects ) {
        $existing_categories = get_option( SMDP_CATEGORIES_OPTION, array() );
        $by_slug = array();
        $final_categories = array();

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

        update_option( SMDP_CATEGORIES_OPTION, $final_categories );
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

            // Update sold-out override
            $mapping[ $item_id ]['sold_out_override'] = $is_sold ? 'sold' : 'available';
        }

        update_option( SMDP_MAPPING_OPTION, $mapping );
    }

    /**
     * Log API response for debugging
     *
     * @param array $headers HTTP headers used in request
     */
    private function log_api_response( $headers ) {
        // Get the last response body (from the final pagination request)
        $catalog_url = 'https://connect.squareup.com/v2/catalog/list?types=ITEM,IMAGE,CATEGORY,MODIFIER_LIST';
        $response = wp_remote_get( $catalog_url, array( 'headers' => $headers ) );

        if ( is_wp_error( $response ) ) {
            return;
        }

        $body = wp_remote_retrieve_body( $response );

        $api_log = get_option( SMDP_API_LOG_OPTION, array() );
        $api_log_entry = array(
            'timestamp'        => current_time( 'mysql' ),
            'catalog_request'  => $catalog_url,
            'catalog_response' => $body,
        );

        array_unshift( $api_log, $api_log_entry );

        // Keep only last 10 entries
        if ( count( $api_log ) > 10 ) {
            $api_log = array_slice( $api_log, 0, 10 );
        }

        update_option( SMDP_API_LOG_OPTION, $api_log );
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
