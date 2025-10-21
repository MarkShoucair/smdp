<?php
/**
 * Shortcode Class
 *
 * Handles the [square_menu] shortcode for displaying menu items.
 *
 * @package Square_Menu_Display
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SMDP_Shortcode Class
 *
 * Registers and renders the [square_menu] shortcode for frontend display.
 */
class SMDP_Shortcode {

    /**
     * Instance of this class
     *
     * @var SMDP_Shortcode
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return SMDP_Shortcode
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Register shortcode
     */
    private function __construct() {
        add_shortcode( 'square_menu', array( $this, 'render' ) );
    }

    /**
     * Render the shortcode
     *
     * Usage: [square_menu category="your-category-slug"]
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render( $atts ) {
        $atts = shortcode_atts( array(
            'category' => '',
        ), $atts, 'square_menu' );

        if ( empty( $atts['category'] ) ) {
            return '<p>Please specify a category.</p>';
        }

        // Find the category ID from the slug
        $categories = get_option( SMDP_CATEGORIES_OPTION, array() );
        $cat_id = $this->get_category_id_by_slug( $categories, $atts['category'] );

        if ( empty( $cat_id ) ) {
            return '<p>Category not found.</p>';
        }

        // Load data
        $mapping   = get_option( SMDP_MAPPING_OPTION, array() );
        $all_items = get_option( SMDP_ITEMS_OPTION, array() );
        $disabled_mods = (array) get_option( 'smdp_disabled_modifiers', array() );

        // Filter & sort items in this category
        $items_to_show = $this->get_category_items( $all_items, $mapping, $cat_id );

        // Build output
        $output = $this->build_menu_grid( $items_to_show, $all_items, $mapping, $disabled_mods );

        // Get modal settings
        $settings = get_option( 'smdp_app_settings', array() );
        $enable_modal_shortcode = isset($settings['enable_modal_shortcode']) ? $settings['enable_modal_shortcode'] : '1';

        // Wrap it with menu container
        $menu_id = esc_attr( $atts['category'] );
        $html  = '<div class="smdp-menu-container" data-menu-id="' . $menu_id . '" data-context="shortcode" data-modal-enabled="' . esc_attr($enable_modal_shortcode) . '">';
        $html .= $output;
        $html .= '</div>';

        return $html;
    }

    /**
     * Get category ID by slug
     *
     * @param array  $categories All categories
     * @param string $slug Category slug
     * @return string Category ID or empty string
     */
    private function get_category_id_by_slug( $categories, $slug ) {
        foreach ( $categories as $cat ) {
            if ( $cat['slug'] === $slug ) {
                return $cat['id'];
            }
        }
        return '';
    }

    /**
     * Get items for a specific category, sorted by order
     *
     * @param array  $all_items All catalog items
     * @param array  $mapping Item mappings
     * @param string $cat_id Category ID
     * @return array Sorted items to display
     */
    private function get_category_items( $all_items, $mapping, $cat_id ) {
        $items_to_show = array();

        // Check if this is old-style mapping or new-style mapping
        $is_new_style = false;
        foreach ($mapping as $key => $data) {
            if (isset($data['instance_id'])) {
                $is_new_style = true;
                break;
            }
        }

        if ($is_new_style) {
            // New-style mapping: instance_id => {item_id, instance_id, category, order, hide_image}
            // Build a lookup of item_id => item_obj
            $items_by_id = array();
            foreach ( $all_items as $obj ) {
                if ( empty( $obj['type'] ) || $obj['type'] !== 'ITEM' ) {
                    continue;
                }
                $items_by_id[$obj['id']] = $obj;
            }

            // Collect items that belong to this category
            foreach ($mapping as $instance_id => $map_data) {
                if ($map_data['category'] === $cat_id && isset($items_by_id[$map_data['item_id']])) {
                    $obj = $items_by_id[$map_data['item_id']];
                    $obj['order'] = $map_data['order'];
                    $obj['instance_id'] = $instance_id;
                    $obj['hide_image'] = $map_data['hide_image'];
                    $items_to_show[] = $obj;
                }
            }
        } else {
            // Old-style mapping: item_id => {category, order, hide_image}
            foreach ( $all_items as $obj ) {
                if ( empty( $obj['type'] ) || $obj['type'] !== 'ITEM' ) {
                    continue;
                }
                $id = $obj['id'];
                if ( isset( $mapping[$id]['category'] ) && $mapping[$id]['category'] === $cat_id ) {
                    $obj['order'] = $mapping[$id]['order'];
                    $items_to_show[] = $obj;
                }
            }
        }

        // Sort by order
        usort( $items_to_show, function( $a, $b ) {
            return intval( $a['order'] ) - intval( $b['order'] );
        });

        return $items_to_show;
    }

    /**
     * Build the menu grid HTML
     *
     * @param array $items_to_show Items to display
     * @param array $all_items All catalog items
     * @param array $mapping Item mappings
     * @param array $disabled_mods Disabled modifier list IDs
     * @return string HTML output
     */
    private function build_menu_grid( $items_to_show, $all_items, $mapping, $disabled_mods ) {
        $output = '<div class="smdp-menu-grid">';

        foreach ( $items_to_show as $obj ) {
            $item_id = $obj['id'];
            $item    = $obj['item_data'];

            // Get item details
            $price = $this->get_item_price( $item );
            $is_sold = $this->is_item_sold_out( $item_id, $item, $mapping );

            // Check if hide_image is stored in the object (new-style) or mapping (old-style)
            if (isset($obj['hide_image'])) {
                $hide_image = ! empty( $obj['hide_image'] );
            } else {
                $hide_image = ! empty( $mapping[$item_id]['hide_image'] );
            }

            $img_url = $this->get_item_image_url( $item, $all_items );

            // Build item tile
            $output .= $this->build_item_tile( $item_id, $item, $price, $is_sold, $hide_image, $img_url, $all_items, $disabled_mods );
        }

        $output .= '</div>';
        $output .= $this->get_inline_css();

        return $output;
    }

    /**
     * Build a single item tile
     *
     * @param string $item_id Item ID
     * @param array  $item Item data
     * @param float  $price Item price
     * @param bool   $is_sold Is sold out
     * @param bool   $hide_image Hide image flag
     * @param string $img_url Image URL
     * @param array  $all_items All catalog items
     * @param array  $disabled_mods Disabled modifier list IDs
     * @return string HTML for item tile
     */
    private function build_item_tile( $item_id, $item, $price, $is_sold, $hide_image, $img_url, $all_items, $disabled_mods ) {
        $classes = 'smdp-menu-item' . ( $is_sold ? ' sold-out-item' : '' );

        // Open wrapper with data attributes
        $output  = '<div class="' . esc_attr( $classes . ' smdp-item-tile' ) . '"';
        $output .= ' data-item-id="' . esc_attr( $item_id ) . '"';
        $output .= ' data-name="'  . esc_attr( $item['name'] ) . '"';
        $output .= ' data-desc="'  . esc_attr( $item['description'] ?? '' ) . '"';
        $output .= ' data-img="'   . esc_url( $img_url ) . '"';
        $output .= ' data-price="' . esc_attr( number_format_i18n( $price, 2 ) ) . '">';

        // Add modifier data (hidden)
        $mod_html = $this->build_modifier_html( $item, $all_items, $disabled_mods );
        $output .= '<div class="smdp-mod-data" style="display:none;">' . $mod_html . '</div>';

        // Item title
        $output .= '<h3>' . esc_html( $item['name'] ) . '</h3>';

        // Sold out banner
        if ( $is_sold ) {
            $output .= '<div class="sold-out-banner">SOLD OUT</div>';
        }

        // Image (if not hidden)
        if ( ! $hide_image && ! empty( $img_url ) ) {
            $output .= '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $item['name'] ) . '" class="smdp-menu-image" />';
        }

        // Description
        if ( ! empty( $item['description'] ) ) {
            $output .= '<p>' . esc_html( $item['description'] ) . '</p>';
        }

        // Price
        $output .= '<p><strong>$' . number_format_i18n( $price, 2 ) . '</strong></p>';

        $output .= '</div>';

        return $output;
    }

    /**
     * Get item price from first variation
     *
     * @param array $item Item data
     * @return float Price in dollars
     */
    private function get_item_price( $item ) {
        if ( isset( $item['variations'][0]['item_variation_data']['price_money']['amount'] ) ) {
            return $item['variations'][0]['item_variation_data']['price_money']['amount'] / 100;
        }
        return 0;
    }

    /**
     * Determine if item is sold out
     *
     * @param string $item_id Item ID
     * @param array  $item Item data
     * @param array  $mapping Item mappings
     * @return bool True if sold out
     */
    private function is_item_sold_out( $item_id, $item, $mapping ) {
        $override = $mapping[$item_id]['sold_out_override'] ?? '';

        if ( $override === 'sold' ) {
            return true;
        } elseif ( $override === 'available' ) {
            return false;
        } else {
            return ! empty( $item['sold_out'] );
        }
    }

    /**
     * Get item image URL
     *
     * @param array $item Item data
     * @param array $all_items All catalog items
     * @return string Image URL or empty string
     */
    private function get_item_image_url( $item, $all_items ) {
        if ( empty( $item['image_ids'][0] ) ) {
            return '';
        }

        $img_id = $item['image_ids'][0];
        foreach ( $all_items as $o_img ) {
            if ( isset( $o_img['type'] ) && $o_img['type'] === 'IMAGE' && $o_img['id'] === $img_id ) {
                return $o_img['image_data']['url'];
            }
        }

        return '';
    }

    /**
     * Build modifier HTML for an item
     *
     * @param array $item Item data
     * @param array $all_items All catalog items
     * @param array $disabled_mods Disabled modifier list IDs
     * @return string HTML for modifiers
     */
    private function build_modifier_html( $item, $all_items, $disabled_mods ) {
        $mod_html = '';

        if ( empty( $item['modifier_list_info'] ) ) {
            return '<p style="color:#666; font-style:italic;">No modifiers available</p>';
        }

        foreach ( $item['modifier_list_info'] as $li ) {
            $list_id = $li['modifier_list_id'];

            // Skip disabled lists
            if ( in_array( $list_id, $disabled_mods, true ) ) {
                continue;
            }

            // Find the matching MODIFIER_LIST object
            foreach ( $all_items as $o ) {
                if ( $o['type'] === 'MODIFIER_LIST' && $o['id'] === $list_id ) {
                    $ml = $o['modifier_list_data'];

                    // Build modifier category
                    $mod_html .= '<div class="smdp-mod-category">';
                    $mod_html .= '<h4>' . esc_html( $ml['name'] ) . '</h4>';
                    $mod_html .= '<ul>';

                    if ( ! empty( $ml['modifiers'] ) && is_array( $ml['modifiers'] ) ) {
                        foreach ( $ml['modifiers'] as $mod ) {
                            if ( empty( $mod['modifier_data'] ) ) {
                                continue;
                            }
                            $m = $mod['modifier_data'];

                            $pr = isset( $m['price_money']['amount'] )
                                ? ' — $' . number_format_i18n( $m['price_money']['amount'] / 100, 2 )
                                : '';

                            $mod_html .= '<li>' . esc_html( $m['name'] ) . esc_html( $pr ) . '</li>';
                        }
                    }

                    $mod_html .= '</ul>';
                    $mod_html .= '</div>';
                    break;
                }
            }
        }

        // If no modifiers after filtering, show a message
        if ( empty( $mod_html ) ) {
            $mod_html = '<p style="color:#666; font-style:italic;">No modifiers available</p>';
        }

        return $mod_html;
    }

    /**
     * Get inline CSS for the menu grid
     *
     * @return string CSS styles
     */
    private function get_inline_css() {
        return '<style>
      .smdp-menu-grid {
          display: grid;
          grid-template-columns: repeat(3,1fr);
          gap: 10px;
      }
      .smdp-menu-item {
          position: relative;
          border:1px solid #eee;
          padding:8px;
          border-radius:4px;
          box-shadow:0 1px 3px rgba(0,0,0,0.1);
          background:#fff;
      }
      .smdp-menu-item h3 {
          margin:0 0 5px;
          font-size:1.2em;
      }
      .smdp-menu-image {
          display:block;
          max-width:100%;
          height:auto;
          margin:5px 0;
          border-radius:4px;
      }
      .sold-out-banner {
          position: absolute;
          bottom: 0;
          right: 0;
          background:red;
          color:#fff;
          padding:2px 6px;
          font-size:1em;
          opacity:1;
          z-index:10;
      }
      .sold-out-item {
          opacity:0.5;
      }
    </style>';
    }
}

// Initialize the shortcode
SMDP_Shortcode::instance();
