<?php
/**
 * Admin Menu Editor Template
 *
 * Drag-and-drop interface for organizing menu items by category.
 * Full-featured version with Add Item modal, Remove buttons, Hide/Show categories, and Copy Shortcode.
 *
 * @package Square_Menu_Display
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

  <h2>Menu Editor</h2>
  <p>Drag and drop items to reorder or move them between categories.<br>
     Use the "Hide Image" checkbox to disable front-end image display.
  </p>

  <!-- Advanced Items Table Section -->
  <div style="background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,0.04);padding:20px;margin:20px 0;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
      <h2 style="margin:0;">Advanced Item Management</h2>
      <button type="button" id="smdp-toggle-items-table" class="button button-secondary">
        <span class="dashicons dashicons-list-view" style="vertical-align:middle;"></span>
        <span class="toggle-text">Show Items Table</span>
      </button>
    </div>

    <div id="smdp-items-table-container" style="display:none;">
      <p style="margin-bottom:15px;">
        <button type="button" id="smdp-match-categories-btn" class="button button-secondary">
          <span class="dashicons dashicons-category" style="vertical-align:middle;"></span> Match Square Categories
        </button>
        <button type="button" id="smdp-sync-soldout-btn" class="button button-secondary" style="margin-left:10px;">
          <span class="dashicons dashicons-update" style="vertical-align:middle;"></span> Sync Sold Out Status from Square
        </button>
      </p>

      <table class="wp-list-table widefat striped" style="margin-top:10px;">
        <thead>
          <tr>
            <th style="width:60px;">Image</th>
            <th>Item Name</th>
            <th style="width:180px;">Square Category</th>
            <th style="width:200px;">Square Category ID</th>
            <th>Menu Categories</th>
            <th style="width:120px;">Square Status</th>
            <th style="width:150px;">Override</th>
          </tr>
        </thead>
        <tbody>
          <?php
          // Build comprehensive items list
          $all_objects = get_option(SMDP_ITEMS_OPTION, array());
          $mapping = get_option(SMDP_MAPPING_OPTION, array());
          $categories = get_option(SMDP_CATEGORIES_OPTION, array());

          // Build image lookup
          $image_lookup_table = array();
          foreach ($all_objects as $obj) {
              if (isset($obj['type']) && $obj['type'] === 'IMAGE' && !empty($obj['image_data']['url'])) {
                  $image_lookup_table[$obj['id']] = $obj['image_data']['url'];
              }
          }

          // Get all items
          $all_items_for_table = array();
          foreach ($all_objects as $obj) {
              if (isset($obj['type']) && $obj['type'] === 'ITEM') {
                  $all_items_for_table[] = $obj;
              }
          }

          // Sort by name
          usort($all_items_for_table, function($a, $b) {
              return strcmp($a['item_data']['name'], $b['item_data']['name']);
          });

          // Check if new-style mapping
          $is_new_style_table = false;
          foreach ($mapping as $key => $data) {
              if (isset($data['instance_id'])) {
                  $is_new_style_table = true;
                  break;
              }
          }

          foreach ($all_items_for_table as $item_obj):
              $item_id = $item_obj['id'];
              $item_data = $item_obj['item_data'];

              // Get thumbnail
              $thumb = '';
              if (!empty($item_data['image_ids'][0])) {
                  $img_id = $item_data['image_ids'][0];
                  if (isset($image_lookup_table[$img_id])) {
                      $thumb = $image_lookup_table[$img_id];
                  }
              }

              // Get Square reporting category
              $reporting_cat_id = $item_data['reporting_category']['id'] ?? '';
              $reporting_cat_name = '';
              if ($reporting_cat_id && isset($categories[$reporting_cat_id])) {
                  $reporting_cat_name = $categories[$reporting_cat_id]['name'];
              }

              // Find which categories this item is in
              $item_categories = array();
              if ($is_new_style_table) {
                  foreach ($mapping as $map_data) {
                      if (isset($map_data['item_id']) && $map_data['item_id'] === $item_id) {
                          $cat_id = $map_data['category'];
                          if ($cat_id && isset($categories[$cat_id])) {
                              $item_categories[$cat_id] = $categories[$cat_id]['name'];
                          } elseif ($cat_id === 'unassigned') {
                              $item_categories['unassigned'] = 'Unassigned';
                          }
                      }
                  }
              } else {
                  if (isset($mapping[$item_id]) && !empty($mapping[$item_id]['category'])) {
                      $cat_id = $mapping[$item_id]['category'];
                      if (isset($categories[$cat_id])) {
                          $item_categories[$cat_id] = $categories[$cat_id]['name'];
                      }
                  } else {
                      $item_categories['unassigned'] = 'Unassigned';
                  }
              }

              // Detect Square sold-out status
              $is_sold_out = false;
              if (!empty($item_data['variations'])) {
                  foreach ($item_data['variations'] as $var) {
                      $ov_list = $var['item_variation_data']['location_overrides'] ?? array();
                      foreach ($ov_list as $ov) {
                          if (!empty($ov['sold_out'])) {
                              $is_sold_out = true;
                              break 2;
                          }
                      }
                  }
              }

              // Get override
              $sold_out_override = '';
              if ($is_new_style_table) {
                  foreach ($mapping as $map_data) {
                      if (isset($map_data['item_id']) && $map_data['item_id'] === $item_id && isset($map_data['sold_out_override'])) {
                          $sold_out_override = $map_data['sold_out_override'];
                          break;
                      }
                  }
              } else {
                  $sold_out_override = $mapping[$item_id]['sold_out_override'] ?? '';
              }

              $square_status = $is_sold_out ? 'Sold Out' : 'Available';
          ?>
          <tr>
            <td>
              <?php if ($thumb): ?>
                <img src="<?php echo esc_url($thumb); ?>" style="max-width:50px;height:auto;border-radius:3px;">
              <?php endif; ?>
            </td>
            <td><strong><?php echo esc_html($item_data['name']); ?></strong></td>
            <td>
              <?php
              if ($reporting_cat_name) {
                  echo esc_html($reporting_cat_name);
              } else {
                  echo '<em style="color:#999;">None</em>';
              }
              ?>
            </td>
            <td style="font-family:monospace; font-size:0.85em; color:#666;">
              <?php
              if ($reporting_cat_id) {
                  echo esc_html($reporting_cat_id);
              } else {
                  echo '<em style="color:#999;">None</em>';
              }
              ?>
            </td>
            <td>
              <?php
              if (empty($item_categories)) {
                  echo '<em style="color:#999;">No categories</em>';
              } else {
                  echo esc_html(implode(', ', array_unique($item_categories)));
              }
              ?>
            </td>
            <td>
              <span style="display:inline-block; padding:3px 8px; border-radius:3px; font-size:0.9em; <?php echo $is_sold_out ? 'background:#dc3232; color:#fff;' : 'background:#46b450; color:#fff;'; ?>">
                <?php echo esc_html($square_status); ?>
              </span>
            </td>
            <td>
              <select class="smdp-table-sold-out-select" data-item-id="<?php echo esc_attr($item_id); ?>" style="width:100%;">
                <option value="">Auto (<?php echo $is_sold_out ? 'Sold' : 'Available'; ?>)</option>
                <option value="sold" <?php selected($sold_out_override, 'sold'); ?>>Force Sold Out</option>
                <option value="available" <?php selected($sold_out_override, 'available'); ?>>Force Available</option>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Category Management Section -->
  <div style="margin-bottom:20px;">
    <button type="button" id="smdp-toggle-category-order" class="button button-secondary" style="margin-bottom:10px;margin-right:10px;">
      <span class="dashicons dashicons-sort" style="vertical-align:middle;"></span> Sort Categories
    </button>
    <button type="button" id="smdp-add-category-btn" class="button button-primary" style="margin-bottom:10px;">
      <span class="dashicons dashicons-plus-alt" style="vertical-align:middle;"></span> Add Category
    </button>

    <div id="smdp-category-order-panel" style="display:none; background:#fff; border:1px solid #ccd0d4; padding:15px; margin-bottom:15px;">
      <h3 style="margin-top:0;">Reorder Categories (drag to sort)</h3>
      <label style="display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;">
        <input type="checkbox" id="smdp-order-exclude-hidden" checked>
        Exclude hidden categories from list
      </label>
      <ul id="smdp-cat-order" class="smdp-cat-order" style="list-style:none;margin:0;padding:0;max-width:600px;">
        <?php
        // Get categories with current order
        $cat_opt = defined('SMDP_CATEGORIES_OPTION') ? SMDP_CATEGORIES_OPTION : 'square_menu_categories';
        $all_categories = get_option($cat_opt, array());
        if (!is_array($all_categories)) $all_categories = array();

        // Sort by current order
        uasort($all_categories, function($a,$b){
          $oa = isset($a['order']) ? intval($a['order']) : 0;
          $ob = isset($b['order']) ? intval($b['order']) : 0;
          if ($oa === $ob) {
            $an = isset($a['name']) ? $a['name'] : '';
            $bn = isset($b['name']) ? $b['name'] : '';
            return strcasecmp($an, $bn);
          }
          return ($oa < $ob) ? -1 : 1;
        });

        foreach ($all_categories as $cid => $cat):
          $is_hidden = !empty($cat['hidden']);
        ?>
          <li class="smdp-cat-order-item <?php echo $is_hidden ? 'is-hidden' : ''; ?>"
              data-id="<?php echo esc_attr($cid); ?>"
              data-hidden="<?php echo $is_hidden ? '1' : '0'; ?>"
              style="border:1px solid #e5e5e5;border-radius:4px;padding:10px 12px;margin-bottom:8px;background:#fafafa;display:flex;justify-content:space-between;align-items:center;cursor:move;">
            <span>
              <span class="dashicons dashicons-move" style="color:#999;margin-right:8px;"></span>
              <?php echo esc_html($cat['name'] ?? 'Category'); ?>
              <?php echo $is_hidden ? ' <em style="opacity:.6">(hidden)</em>' : ''; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
      <p style="margin-top:15px;">
        <button type="button" class="button button-primary" id="smdp-save-cat-order">Save Category Order</button>
        <span id="smdp-cat-order-status" style="margin-left:10px;"></span>
      </p>
    </div>

    <!-- Add Category Panel -->
    <div id="smdp-add-category-panel" style="display:none; background:#fff; border:1px solid #ccd0d4; padding:15px; margin-bottom:15px;">
      <h3 style="margin-top:0;">Add New Category</h3>
      <p style="margin-bottom:15px;">Enter a name for your new category. A URL-friendly slug will be generated automatically.</p>
      <div style="display:flex;gap:10px;align-items:flex-start;">
        <input type="text" id="smdp-new-cat-name" placeholder="Category Name" style="flex:1;padding:8px;" />
        <button type="button" class="button button-primary" id="smdp-save-new-category">Add Category</button>
      </div>
      <span id="smdp-add-cat-status" style="display:inline-block;margin-top:10px;"></span>
    </div>
  </div>

  <form method="post" id="smdp-items-form">
     <?php wp_nonce_field('smdp_mapping_save','smdp_mapping_nonce'); ?>
     <div id="smdp-items-container">
        <?php
        // Loop through sorted active categories.
        if (!empty($cat_array)) {
            foreach ($cat_array as $cat) {
                $hidden_class = (isset($cat['hidden']) && $cat['hidden']) ? 'hidden-category' : '';
                $shortcode = '[square_menu category="' . esc_attr($cat['slug']) . '"]';
                ?>
                <div class="smdp-category-group <?php echo $hidden_class; ?>" data-category="<?php echo esc_attr($cat['id']); ?>" style="margin-bottom:30px; border:1px solid #ddd; padding:15px; background:#f5f5f5; border-radius:4px;">
                  <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                       <h2 style="margin:5px 0; display:inline-block;"><?php echo esc_html($cat['name']); ?></h2>
                       <button type="button" class="smdp-shortcode button button-secondary" data-shortcode="<?php echo esc_attr($shortcode); ?>" style="margin-left:10px;vertical-align:middle;">
                         <span class="dashicons dashicons-clipboard" style="vertical-align:middle;margin-right:3px;"></span>
                         Copy Shortcode
                       </button>
                    </div>
                    <div>
                       <button type="button" class="smdp-add-item-btn button button-primary" data-target="<?php echo esc_attr($cat['id']); ?>" style="margin-right:5px;">
                         <span class="dashicons dashicons-plus-alt" style="vertical-align:middle;"></span> Add Item
                       </button>
                       <button type="button" class="smdp-hide-category-btn button" data-catid="<?php echo esc_attr($cat['id']); ?>" style="background:#f39c12; color:#fff; border-color:#e08e0b;">
                          <?php echo (isset($cat['hidden']) && $cat['hidden']) ? "Show Category" : "Hide Category"; ?>
                       </button>
                    </div>
                  </div>

                  <!-- Inline Add Item Panel -->
                  <div class="smdp-add-item-panel" data-category="<?php echo esc_attr($cat['id']); ?>" style="display:none; background:#fff; border:1px solid #2271b1; border-radius:4px; padding:15px; margin:10px 0;">
                     <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <h3 style="margin:0;">Add Items to <?php echo esc_html($cat['name']); ?></h3>
                        <button type="button" class="smdp-close-add-panel button" style="padding:2px 8px;">
                           <span class="dashicons dashicons-no-alt" style="vertical-align:middle;"></span>
                        </button>
                     </div>
                     <input type="text" class="smdp-item-search" placeholder="Search items..." style="width:100%; padding:8px; margin-bottom:10px; border:1px solid #ddd; border-radius:3px;" />
                     <div class="smdp-items-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:10px; max-height:400px; overflow-y:auto; padding:5px;">
                        <!-- Items will be populated here by JavaScript -->
                     </div>
                     <div style="margin-top:10px; text-align:right;">
                        <button type="button" class="smdp-add-selected-items button button-primary">
                           <span class="dashicons dashicons-plus" style="vertical-align:middle;"></span> Add Selected Items
                        </button>
                     </div>
                  </div>

                  <ul class="smdp-sortable-group" style="display:flex; flex-wrap:wrap; gap:10px; list-style:none; margin:10px 0; padding:10px; min-height:220px; background:#ffffff; border-radius:4px;">
                     <?php
                     if (isset($grouped_items[$cat['id']]) && count($grouped_items[$cat['id']]) > 0) {
                         foreach ($grouped_items[$cat['id']] as $item) {
                             $instance_id = isset($item['instance_id']) ? $item['instance_id'] : $item['id'];
                             ?>
                             <li class="smdp-sortable-item" data-item-id="<?php echo esc_attr($item['id']); ?>" data-instance-id="<?php echo esc_attr($instance_id); ?>" style="width:200px; height:240px; padding:10px; border:1px solid #eee; background:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; box-sizing:border-box; position:relative;">
                                 <?php
                                 // Determine final sold-out status
                                 $final_sold_status = '';
                                 if (!empty($item['sold_out_override'])) {
                                     $final_sold_status = $item['sold_out_override'];
                                 } elseif (!empty($item['square_sold_out'])) {
                                     $final_sold_status = 'sold';
                                 }

                                 // Show badge if sold out
                                 if ($final_sold_status === 'sold') : ?>
                                     <div class="smdp-sold-badge" style="position:absolute; top:5px; right:5px; background:#dc3232; color:#fff; padding:2px 6px; border-radius:3px; font-size:0.7em; font-weight:bold;">SOLD OUT</div>
                                 <?php endif; ?>

                                 <?php if (!empty($item['thumbnail'])) : ?>
                                     <img src="<?php echo esc_url($item['thumbnail']); ?>" alt="<?php echo esc_attr($item['name']); ?>" style="max-width:80px; margin-bottom:5px;">
                                 <?php endif; ?>
                                 <div style="text-align:center; width:100%;">
                                     <span class="smdp-item-name" style="font-size:1.0em; display:block; margin-bottom:5px; font-weight:500;"><?php echo esc_html($item['name']); ?></span>

                                     <select class="smdp-sold-out-select" data-item-id="<?php echo esc_attr($item['id']); ?>" style="width:100%; font-size:0.8em; margin-bottom:3px; padding:2px;">
                                         <option value="">Auto<?php echo !empty($item['square_sold_out']) ? ' (Sold)' : ' (Available)'; ?></option>
                                         <option value="sold" <?php selected($item['sold_out_override'], 'sold'); ?>>Force Sold Out</option>
                                         <option value="available" <?php selected($item['sold_out_override'], 'available'); ?>>Force Available</option>
                                     </select>

                                     <label style="font-size:0.8em; display:block; margin-bottom:3px;">
                                         <input type="checkbox" class="smdp-hide-image" data-instance-id="<?php echo esc_attr($instance_id); ?>" value="1" <?php checked($item['hide_image'], 1); ?>>
                                         Hide Image
                                     </label>
                                     <button type="button" class="smdp-remove-item" data-instance-id="<?php echo esc_attr($instance_id); ?>" style="position:absolute; top:5px; left:5px; width:24px; height:24px; border-radius:50%; background:#d63638; color:#fff; border:none; cursor:pointer; font-size:16px; line-height:1; padding:0; display:flex; align-items:center; justify-content:center; font-weight:bold; z-index:10;" title="Remove from category">×</button>
                                 </div>
                             </li>
                             <?php
                         }
                     } else {
                         echo '<li class="smdp-dummy" style="width:200px; height:200px; visibility:hidden;">&nbsp;</li>';
                     }
                     ?>
                  </ul>
                </div>
                <?php
            }
        }
        // Render Unassigned group.
        ?>
        <div class="smdp-category-group" data-category="unassigned" style="margin-bottom:30px; border:1px solid #ccc; padding:5px; background:#f6f6f6;">
              <h2 style="margin:5px 0;">Unassigned</h2>
              <ul class="smdp-sortable-group" style="display:flex; flex-wrap:wrap; gap:10px; list-style:none; margin:0; padding:0; min-height:220px;">
                 <?php
                 if (isset($grouped_items['unassigned']) && count($grouped_items['unassigned']) > 0) {
                     foreach ($grouped_items['unassigned'] as $item) {
                         $instance_id = isset($item['instance_id']) ? $item['instance_id'] : $item['id'];
                         ?>
                         <li class="smdp-sortable-item" data-item-id="<?php echo esc_attr($item['id']); ?>" data-instance-id="<?php echo esc_attr($instance_id); ?>" style="width:200px; height:240px; padding:10px; border:1px solid #eee; background:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; box-sizing:border-box; position:relative;">
                             <?php
                             // Determine final sold-out status
                             $final_sold_status = '';
                             if (!empty($item['sold_out_override'])) {
                                 $final_sold_status = $item['sold_out_override'];
                             } elseif (!empty($item['square_sold_out'])) {
                                 $final_sold_status = 'sold';
                             }

                             // Show badge if sold out
                             if ($final_sold_status === 'sold') : ?>
                                 <div class="smdp-sold-badge" style="position:absolute; top:5px; right:5px; background:#dc3232; color:#fff; padding:2px 6px; border-radius:3px; font-size:0.7em; font-weight:bold;">SOLD OUT</div>
                             <?php endif; ?>

                             <?php if (!empty($item['thumbnail'])) : ?>
                                 <img src="<?php echo esc_url($item['thumbnail']); ?>" alt="<?php echo esc_attr($item['name']); ?>" style="max-width:80px; margin-bottom:5px;">
                             <?php endif; ?>
                             <div style="text-align:center; width:100%;">
                                 <span class="smdp-item-name" style="font-size:1.0em; display:block; margin-bottom:5px; font-weight:500;"><?php echo esc_html($item['name']); ?></span>

                                 <select class="smdp-sold-out-select" data-item-id="<?php echo esc_attr($item['id']); ?>" style="width:100%; font-size:0.8em; margin-bottom:3px; padding:2px;">
                                     <option value="">Auto<?php echo !empty($item['square_sold_out']) ? ' (Sold)' : ' (Available)'; ?></option>
                                     <option value="sold" <?php selected($item['sold_out_override'], 'sold'); ?>>Force Sold Out</option>
                                     <option value="available" <?php selected($item['sold_out_override'], 'available'); ?>>Force Available</option>
                                 </select>

                                 <label style="font-size:0.8em; display:block; margin-bottom:3px;">
                                     <input type="checkbox" class="smdp-hide-image" data-instance-id="<?php echo esc_attr($instance_id); ?>" value="1" <?php checked($item['hide_image'], 1); ?>>
                                     Hide Image
                                 </label>
                                 <button type="button" class="smdp-remove-item" data-instance-id="<?php echo esc_attr($instance_id); ?>" style="position:absolute; top:5px; left:5px; width:24px; height:24px; border-radius:50%; background:#d63638; color:#fff; border:none; cursor:pointer; font-size:16px; line-height:1; padding:0; display:flex; align-items:center; justify-content:center; font-weight:bold; z-index:10;" title="Remove from category">×</button>
                             </div>
                         </li>
                         <?php
                     }
                 } else {
                     echo '<li class="smdp-dummy" style="width:200px; height:200px; visibility:hidden;">&nbsp;</li>';
                 }
                 ?>
              </ul>
        </div>
     </div>
     <input type="hidden" name="mapping_json" id="mapping_json" value="">
     <?php submit_button('Save Mappings'); ?>
  </form>

  <!-- Floating Save Button -->
  <div id="smdp-floating-save" style="position:fixed;bottom:30px;right:30px;z-index:9999;display:none;">
    <button type="button" class="button button-primary button-hero" id="smdp-floating-save-btn" style="box-shadow:0 4px 12px rgba(0,0,0,0.3);font-size:16px;padding:12px 24px;">
      <span class="dashicons dashicons-saved" style="vertical-align:middle;margin-right:5px;"></span>
      Save Mappings
    </button>
  </div>

  <!-- Double Confirmation Modal for Match Categories -->
  <div id="smdp-match-categories-modal" class="smdp-confirmation-modal" style="display:none;">
    <div class="smdp-modal-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:99999;"></div>
    <div class="smdp-modal-content" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:30px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);max-width:500px;width:90%;z-index:100000;">
      <div style="text-align:center;margin-bottom:20px;">
        <span class="dashicons dashicons-warning" style="font-size:48px;color:#f56e28;width:48px;height:48px;"></span>
      </div>
      <h2 style="margin:0 0 15px 0;text-align:center;color:#d63638;">Warning: This Will Overwrite All Customizations</h2>
      <p style="font-size:14px;line-height:1.6;margin-bottom:15px;">
        <strong>This action will reset all your category assignments to match Square POS settings.</strong>
      </p>
      <p style="font-size:14px;line-height:1.6;margin-bottom:20px;">
        You will lose:
      </p>
      <ul style="margin:0 0 20px 20px;font-size:14px;line-height:1.8;">
        <li>Custom category assignments</li>
        <li>Items in multiple categories</li>
        <li>Custom category ordering</li>
      </ul>
      <p style="font-size:14px;line-height:1.6;margin-bottom:20px;padding:12px;background:#fff3cd;border-left:4px solid:#f56e28;color:#856404;">
        <strong>Important:</strong> This cannot be undone. Make sure you have a backup if needed.
      </p>
      <div style="margin-bottom:20px;">
        <label style="display:flex;align-items:center;font-size:14px;cursor:pointer;">
          <input type="checkbox" id="smdp-match-confirm-check" style="margin-right:8px;">
          <span>I understand this will overwrite all my customizations</span>
        </label>
      </div>
      <div style="display:flex;gap:10px;justify-content:center;">
        <button type="button" class="button button-large" id="smdp-match-cancel" style="min-width:120px;">Cancel</button>
        <button type="button" class="button button-primary button-large" id="smdp-match-confirm" disabled style="min-width:120px;background:#d63638;border-color:#d63638;">
          <span class="dashicons dashicons-warning" style="vertical-align:middle;margin-right:5px;"></span>
          Proceed Anyway
        </button>
      </div>
    </div>
  </div>

  <!-- Double Confirmation Modal for Sync Sold Out -->
  <div id="smdp-sync-soldout-modal" class="smdp-confirmation-modal" style="display:none;">
    <div class="smdp-modal-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:99999;"></div>
    <div class="smdp-modal-content" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:30px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);max-width:500px;width:90%;z-index:100000;">
      <div style="text-align:center;margin-bottom:20px;">
        <span class="dashicons dashicons-warning" style="font-size:48px;color:#f56e28;width:48px;height:48px;"></span>
      </div>
      <h2 style="margin:0 0 15px 0;text-align:center;color:#d63638;">Warning: This Will Overwrite All Sold-Out Overrides</h2>
      <p style="font-size:14px;line-height:1.6;margin-bottom:15px;">
        <strong>This action will sync all sold-out statuses to match current Square POS data.</strong>
      </p>
      <p style="font-size:14px;line-height:1.6;margin-bottom:20px;">
        You will lose:
      </p>
      <ul style="margin:0 0 20px 20px;font-size:14px;line-height:1.8;">
        <li>All "Force Sold Out" overrides</li>
        <li>All "Force Available" overrides</li>
        <li>Manual sold-out status controls</li>
      </ul>
      <p style="font-size:14px;line-height:1.6;margin-bottom:20px;padding:12px;background:#fff3cd;border-left:4px solid:#f56e28;color:#856404;">
        <strong>Important:</strong> All items will revert to "Auto" mode, following Square POS settings only.
      </p>
      <div style="margin-bottom:20px;">
        <label style="display:flex;align-items:center;font-size:14px;cursor:pointer;">
          <input type="checkbox" id="smdp-sync-confirm-check" style="margin-right:8px;">
          <span>I understand this will reset all sold-out overrides</span>
        </label>
      </div>
      <div style="display:flex;gap:10px;justify-content:center;">
        <button type="button" class="button button-large" id="smdp-sync-cancel" style="min-width:120px;">Cancel</button>
        <button type="button" class="button button-primary button-large" id="smdp-sync-confirm" disabled style="min-width:120px;background:#d63638;border-color:#d63638;">
          <span class="dashicons dashicons-warning" style="vertical-align:middle;margin-right:5px;"></span>
          Proceed Anyway
        </button>
      </div>
    </div>
  </div>


<script>
jQuery(document).ready(function($) {
   // Toggle category order panel
   $("#smdp-toggle-category-order").on("click", function() {
      $("#smdp-category-order-panel").slideToggle(300);
      $("#smdp-add-category-panel").hide();
   });

   // Toggle add category panel
   $("#smdp-add-category-btn").on("click", function() {
      $("#smdp-add-category-panel").slideToggle(300);
      $("#smdp-category-order-panel").hide();
   });

   // Create new category
   $("#smdp-save-new-category").on("click", function() {
      var $btn = $(this);
      var $status = $("#smdp-add-cat-status");
      var $input = $("#smdp-new-cat-name");
      var categoryName = $input.val().trim();

      if (!categoryName) {
         $status.html('<span style="color:#dc3232;">Please enter a category name.</span>');
         return;
      }

      $btn.prop("disabled", true);
      $status.html('<span style="color:#999;">Creating category...</span>');

      $.ajax({
         url: ajaxurl,
         method: "POST",
         data: {
            action: "smdp_create_category",
            name: categoryName,
            _ajax_nonce: "<?php echo wp_create_nonce('smdp_create_category'); ?>"
         },
         success: function(response) {
            if (response.success) {
               $status.html('<span style="color:#46b450;">✓ Category created! Reloading...</span>');
               setTimeout(function() {
                  location.reload();
               }, 1000);
            } else {
               $status.html('<span style="color:#dc3232;">Error: ' + (response.data || 'Unknown error') + '</span>');
               $btn.prop("disabled", false);
            }
         },
         error: function() {
            $status.html('<span style="color:#dc3232;">Error: Failed to create category</span>');
            $btn.prop("disabled", false);
         }
      });
   });

   // Allow Enter key to submit new category
   $("#smdp-new-cat-name").on("keypress", function(e) {
      if (e.which === 13) {
         e.preventDefault();
         $("#smdp-save-new-category").click();
      }
   });

   // Initialize category order sortable
   $("#smdp-cat-order").sortable({
      placeholder: "smdp-cat-placeholder",
      forcePlaceholderSize: true,
      cursor: "move",
      opacity: 0.8
   }).disableSelection();

   // Filter hidden categories in sort list
   $("#smdp-order-exclude-hidden").on("change", function() {
      if ($(this).is(":checked")) {
         $("#smdp-cat-order .smdp-cat-order-item.is-hidden").hide();
      } else {
         $("#smdp-cat-order .smdp-cat-order-item.is-hidden").show();
      }
      $("#smdp-cat-order").sortable("refresh");
   }).trigger("change");

   // Save category order
   $("#smdp-save-cat-order").on("click", function() {
      var $btn = $(this);
      var $status = $("#smdp-cat-order-status");
      var order = [];

      $("#smdp-cat-order .smdp-cat-order-item").each(function(index) {
         order.push({
            id: $(this).data("id"),
            order: index
         });
      });

      $btn.prop("disabled", true);
      $status.html('<span style="color:#999;">Saving...</span>');

      $.ajax({
         url: ajaxurl,
         method: "POST",
         data: {
            action: "smdp_save_cat_order",
            order: JSON.stringify(order),
            _ajax_nonce: "<?php echo wp_create_nonce('smdp_cat_order'); ?>"
         },
         success: function(response) {
            if (response.success) {
               $status.html('<span style="color:#46b450;">✓ Saved! Reloading...</span>');
               setTimeout(function() {
                  location.reload();
               }, 1000);
            } else {
               $status.html('<span style="color:#dc3232;">Error: ' + (response.data || 'Unknown error') + '</span>');
               $btn.prop("disabled", false);
            }
         },
         error: function() {
            $status.html('<span style="color:#dc3232;">Error: Failed to save</span>');
            $btn.prop("disabled", false);
         }
      });
   });

   // Initialize sortable groups.
   $(".smdp-sortable-group").sortable({
      connectWith: ".smdp-sortable-group",
      placeholder: "smdp-sortable-placeholder",
      forcePlaceholderSize: true,
      revert: true,
      tolerance: "pointer",
      helper: "clone"
   }).disableSelection();

   // Build mapping on form submit - supports items in multiple categories
   $("#smdp-items-form").on("submit", function(e) {
      var mappingArray = [];
      $(".smdp-sortable-item").each(function() {
         var itemId = $(this).data("item-id");
         var instanceId = $(this).data("instance-id"); // Unique ID for this specific instance
         var parentCategory = $(this).closest(".smdp-category-group").data("category") || "unassigned";
         var order = $(this).index();
         var hideImage = $(this).find(".smdp-hide-image").is(":checked") ? 1 : 0;

         mappingArray.push({
            item_id: itemId,
            instance_id: instanceId,
            category: parentCategory,
            order: order,
            hide_image: hideImage
         });
      });
      $("#mapping_json").val(JSON.stringify(mappingArray));
      return true;
   });

   // Remove Item: delete this instance (since items can now appear in multiple categories)
   $("#smdp-items-container").on("click", ".smdp-remove-item", function() {
      var $item = $(this).closest(".smdp-sortable-item");
      var itemName = $item.find(".smdp-item-name").text();

      if (confirm("Remove '" + itemName + "' from this category? (The item will still exist in other categories and can be re-added later)")) {
         $item.remove();
      }
   });

   // "Add Item" button - show inline panel
   $(".smdp-add-item-btn").on("click", function() {
       var targetCategory = $(this).data("target");
       var $panel = $(".smdp-add-item-panel[data-category='" + targetCategory + "']");

       // Close all other panels first
       $(".smdp-add-item-panel").not($panel).slideUp(200);

       // Toggle this panel
       if ($panel.is(":visible")) {
           $panel.slideUp(200);
           return;
       }

       // Build the items grid
       var gridHtml = "";
       var seenItems = {}; // Track items we've already added to avoid duplicates

       // Get ALL items from all categories (not just unassigned)
       $("#smdp-items-container .smdp-sortable-item").each(function() {
          var itemId = $(this).data("item-id");

          // Skip if we've already added this item to the list
          if (seenItems[itemId]) {
              return;
          }
          seenItems[itemId] = true;

          var itemName = $(this).find(".smdp-item-name").text();
          var $img = $(this).find("img");
          var imgHtml = "";

          if ($img.length && $img.attr("src")) {
              // Use actual img tag for better reliability
              var imgSrc = $img.attr("src");
              var imgAlt = $img.attr("alt") || itemName;
              imgHtml = "<img src='" + imgSrc + "' alt='" + imgAlt + "' style='width:100%; height:100%; object-fit:cover; border-radius:3px;' />";
          }

          gridHtml += "<div class='smdp-add-item-option' data-id='" + itemId + "' style='border:2px solid #ddd; padding:8px; text-align:center; cursor:pointer; border-radius:4px; transition:all 0.2s;'>"
              + "<div style='width:100%; height:80px; background-color:#f0f0f0; border-radius:3px; margin-bottom:5px; overflow:hidden; display:flex; align-items:center; justify-content:center;'>"
              + (imgHtml || "<span style='color:#999; font-size:0.8em;'>No Image</span>")
              + "</div>"
              + "<div style='font-size:0.9em; font-weight:500;'>" + itemName + "</div>"
              + "</div>";
       });

       if ( gridHtml === "" ) {
           gridHtml = "<p style='text-align:center; padding:20px; color:#666;'>No items available.</p>";
       }

       $panel.find(".smdp-items-grid").html(gridHtml);
       $panel.slideDown(200);

       // Handle item selection (click to toggle)
       $panel.find(".smdp-add-item-option").off("click").on("click", function() {
          if ($(this).hasClass("selected")) {
              $(this).removeClass("selected").css({
                  "border-color": "#ddd",
                  "background-color": "#fff"
              });
          } else {
              $(this).addClass("selected").css({
                  "border-color": "#2271b1",
                  "background-color": "#f0f6fc"
              });
          }
       });
   });

   // Close add panel button
   $(document).on("click", ".smdp-close-add-panel", function() {
       $(this).closest(".smdp-add-item-panel").slideUp(200);
   });

   // Search within add item panel
   $(document).on("keyup", ".smdp-item-search", function() {
       var searchVal = $(this).val().toLowerCase();
       var $panel = $(this).closest(".smdp-add-item-panel");
       $panel.find(".smdp-add-item-option").each(function() {
           var text = $(this).text().toLowerCase();
           $(this).toggle(text.indexOf(searchVal) > -1);
       });
   });

   // Add selected items button
   $(document).on("click", ".smdp-add-selected-items", function() {
       var $panel = $(this).closest(".smdp-add-item-panel");
       var targetCategory = $panel.data("category");
       var $selectedOptions = $panel.find(".smdp-add-item-option.selected");

       if ($selectedOptions.length === 0) {
           alert("Please select at least one item.");
           return;
       }

       $selectedOptions.each(function() {
           var selectedId = $(this).data("id");
           if (selectedId) {
               // Look for ANY instance of this item
               var $sourceItem = $(".smdp-sortable-item[data-item-id='" + selectedId + "']").first();
               if ($sourceItem.length) {
                   // Clone the item
                   var $clone = $sourceItem.clone(true);

                   // Generate a unique instance ID
                   var newInstanceId = selectedId + "_" + Date.now() + "_" + Math.floor(Math.random() * 1000);
                   $clone.attr("data-instance-id", newInstanceId);

                   // Update data-instance-id on child elements
                   $clone.find(".smdp-hide-image").attr("data-instance-id", newInstanceId);
                   $clone.find(".smdp-remove-item").attr("data-instance-id", newInstanceId);

                   // Append clone to target category
                   $(".smdp-category-group[data-category='" + targetCategory + "'] ul.smdp-sortable-group").append($clone);
               }
           }
       });

       // Close panel and clear search
       $panel.find(".smdp-item-search").val("");
       $panel.slideUp(200);
   });

   // Hide/Show Category handler.
   $(".smdp-hide-category-btn").on("click", function() {
       var $btn = $(this);
       var catId = $btn.data("catid");
       var $catGroup = $btn.closest(".smdp-category-group");
       var newState = $catGroup.hasClass("hidden-category") ? "0" : "1";
       $.post(ajaxurl, {
           action: "smdp_toggle_category_hidden",
           category_id: catId,
           hidden: newState,
           _ajax_nonce: "<?php echo wp_create_nonce('smdp_toggle_category_hidden'); ?>"
       }, function(response) {
           if ( response.success ) {
               if(response.data.hidden) {
                   $catGroup.addClass("hidden-category");
                   $btn.text("Show Category");
                   $("#smdp-items-container").append($catGroup);
               } else {
                   $catGroup.removeClass("hidden-category");
                   $btn.text("Hide Category");
               }
           } else {
               alert("Error updating category visibility: " + response.data);
           }
       });
   });

   // Floating save button functionality
   var $floatingSave = $("#smdp-floating-save");
   var $regularSave = $("#smdp-items-form .submit");

   // Show/hide floating button based on scroll
   $(window).on("scroll", function() {
       if ($(window).scrollTop() > 300) {
           $floatingSave.fadeIn(200);
       } else {
           $floatingSave.fadeOut(200);
       }
   });

   // Floating save button click
   $("#smdp-floating-save-btn").on("click", function(e) {
       e.preventDefault();

       var $btn = $(this);
       var originalHtml = $btn.html();

       // Visual feedback - show saving state
       $btn.prop("disabled", true).html('<span class="dashicons dashicons-update dashicons-spin" style="vertical-align:middle;margin-right:5px;"></span>Saving...');

       // Manually build the mapping JSON before submitting - array-based for multi-category support
       var mappingArray = [];
       var itemCount = 0;

       $(".smdp-sortable-item").each(function() {
          var itemId = $(this).data("item-id");
          var instanceId = $(this).data("instance-id"); // Unique ID for this instance

          // Skip if no valid IDs
          if (!itemId || !instanceId) {
              console.warn("Skipping item with missing IDs:", this);
              return;
          }

          var parentCategory = $(this).closest(".smdp-category-group").data("category") || "unassigned";
          var order = $(this).index();
          var hideImage = $(this).find(".smdp-hide-image").is(":checked") ? 1 : 0;

          mappingArray.push({
             item_id: itemId,
             instance_id: instanceId,
             category: parentCategory,
             order: order,
             hide_image: hideImage
          });
          itemCount++;
       });

       var jsonString = JSON.stringify(mappingArray);
       console.log("Floating save - Found " + itemCount + " items");
       console.log("Floating save - JSON length:", jsonString.length);

       // Set the hidden field value
       $("#mapping_json").val(jsonString);

       // Small delay to show the saving state, then submit
       setTimeout(function() {
           var form = document.getElementById("smdp-items-form");
           if (form) {
               console.log("Submitting form via native submit...");
               // Use native DOM submit to avoid jQuery event handlers
               HTMLFormElement.prototype.submit.call(form);
           } else {
               console.error("Form not found!");
               $btn.prop("disabled", false).html(originalHtml);
               alert("Error: Form not found. Please try the regular save button.");
           }
       }, 200);
   });

   // Shortcode copy handler with improved feedback
   $(".smdp-shortcode").on("click", function() {
       var $btn = $(this);
       var shortcodeText = $btn.data("shortcode");
       var originalHtml = $btn.html();

       if(navigator.clipboard) {
           navigator.clipboard.writeText(shortcodeText).then(function() {
               $btn.html('<span class="dashicons dashicons-yes" style="vertical-align:middle;margin-right:3px;"></span>Copied!');
               $btn.css("background-color", "#46b450");
               setTimeout(function() {
                   $btn.html(originalHtml);
                   $btn.css("background-color", "");
               }, 2000);
           }, function(err) {
               $btn.html('<span class="dashicons dashicons-no" style="vertical-align:middle;margin-right:3px;"></span>Failed');
               setTimeout(function() {
                   $btn.html(originalHtml);
               }, 2000);
           });
       } else {
           var tempInput = $("<textarea>");
           $("body").append(tempInput);
           tempInput.val(shortcodeText).select();
           document.execCommand("copy");
           tempInput.remove();
           $btn.html('<span class="dashicons dashicons-yes" style="vertical-align:middle;margin-right:3px;"></span>Copied!');
           $btn.css("background-color", "#46b450");
           setTimeout(function() {
               $btn.html(originalHtml);
               $btn.css("background-color", "");
           }, 2000);
       }
   });

   // Sold-out status change handler
   $(document).on("change", ".smdp-sold-out-select", function() {
       var $select = $(this);
       var itemId = $select.data("item-id");
       var newStatus = $select.val();

       // Update all instances of this item on the page
       $(".smdp-sold-out-select[data-item-id='" + itemId + "']").val(newStatus);

       // Update sold-out badges for all instances
       $(".smdp-sortable-item[data-item-id='" + itemId + "']").each(function() {
           var $item = $(this);
           var $badge = $item.find(".smdp-sold-badge");

           // Remove existing badge
           $badge.remove();

           // Add badge if forcing sold out, or if auto and Square says sold
           if (newStatus === 'sold') {
               $item.prepend('<div class="smdp-sold-badge" style="position:absolute; top:5px; right:5px; background:#dc3232; color:#fff; padding:2px 6px; border-radius:3px; font-size:0.7em; font-weight:bold;">SOLD OUT</div>');
           }
       });

       // Save via AJAX
       $.post(ajaxurl, {
           action: 'smdp_update_sold_out_override',
           item_id: itemId,
           override: newStatus,
           _ajax_nonce: '<?php echo wp_create_nonce('smdp_update_sold_out'); ?>'
       }, function(response) {
           if (!response.success) {
               alert('Error updating sold-out status: ' + (response.data || 'Unknown error'));
               console.error('Sold-out update failed:', response);
           }
       }).fail(function() {
           alert('Failed to save sold-out status. Please try saving the form.');
       });
   });

   // Toggle advanced items table
   $("#smdp-toggle-items-table").on("click", function() {
       var $btn = $(this);
       var $container = $("#smdp-items-table-container");
       var $toggleText = $btn.find(".toggle-text");

       if ($container.is(":visible")) {
           $container.slideUp(300);
           $toggleText.text("Show Items Table");
           $btn.find(".dashicons").removeClass("dashicons-arrow-up-alt2").addClass("dashicons-list-view");
       } else {
           $container.slideDown(300);
           $toggleText.text("Hide Items Table");
           $btn.find(".dashicons").removeClass("dashicons-list-view").addClass("dashicons-arrow-up-alt2");
       }
   });

   // Table sold-out select handler (syncs with card selects)
   $(document).on("change", ".smdp-table-sold-out-select", function() {
       var $select = $(this);
       var itemId = $select.data("item-id");
       var newStatus = $select.val();

       // Update all card instances
       $(".smdp-sold-out-select[data-item-id='" + itemId + "']").val(newStatus).trigger("change");
   });

   // Match Square Categories - Show modal
   $("#smdp-match-categories-btn").on("click", function(e) {
       e.preventDefault();
       e.stopImmediatePropagation();
       $("#smdp-match-categories-modal").fadeIn(200);
       $("#smdp-match-confirm-check").prop("checked", false);
       $("#smdp-match-confirm").prop("disabled", true);
       return false;
   });

   // Match Categories - Enable confirm button when checkbox is checked
   $("#smdp-match-confirm-check").on("change", function() {
       $("#smdp-match-confirm").prop("disabled", !$(this).is(":checked"));
   });

   // Match Categories - Cancel
   $("#smdp-match-cancel, #smdp-match-categories-modal .smdp-modal-overlay").on("click", function() {
       $("#smdp-match-categories-modal").fadeOut(200);
   });

   // Match Categories - Confirm and execute
   $("#smdp-match-confirm").on("click", function() {
       var $modal = $("#smdp-match-categories-modal");
       var $btn = $("#smdp-match-categories-btn");
       var originalText = $btn.html();

       $modal.fadeOut(200);
       $btn.prop("disabled", true).html('<span class="dashicons dashicons-update dashicons-spin" style="vertical-align:middle;"></span> Matching...');

       $.post(ajaxurl, {
           action: 'smdp_match_categories',
           _ajax_nonce: '<?php echo wp_create_nonce("smdp_match_categories"); ?>'
       }, function(response) {
           if (response.success) {
               alert('Categories matched successfully! Page will reload.');
               location.reload();
           } else {
               alert('Error: ' + (response.data || 'Unknown error'));
               $btn.prop("disabled", false).html(originalText);
           }
       }).fail(function() {
           alert('Failed to match categories. Please try again.');
           $btn.prop("disabled", false).html(originalText);
       });
   });

   // Sync Sold Out - Show modal
   $("#smdp-sync-soldout-btn").on("click", function(e) {
       e.preventDefault();
       e.stopImmediatePropagation();
       $("#smdp-sync-soldout-modal").fadeIn(200);
       $("#smdp-sync-confirm-check").prop("checked", false);
       $("#smdp-sync-confirm").prop("disabled", true);
       return false;
   });

   // Sync Sold Out - Enable confirm button when checkbox is checked
   $("#smdp-sync-confirm-check").on("change", function() {
       $("#smdp-sync-confirm").prop("disabled", !$(this).is(":checked"));
   });

   // Sync Sold Out - Cancel
   $("#smdp-sync-cancel, #smdp-sync-soldout-modal .smdp-modal-overlay").on("click", function() {
       $("#smdp-sync-soldout-modal").fadeOut(200);
   });

   // Sync Sold Out - Confirm and execute
   $("#smdp-sync-confirm").on("click", function() {
       var $modal = $("#smdp-sync-soldout-modal");
       var $btn = $("#smdp-sync-soldout-btn");
       var originalText = $btn.html();

       $modal.fadeOut(200);
       $btn.prop("disabled", true).html('<span class="dashicons dashicons-update dashicons-spin" style="vertical-align:middle;"></span> Syncing...');

       $.post(ajaxurl, {
           action: 'smdp_sync_sold_out',
           _ajax_nonce: '<?php echo wp_create_nonce("smdp_sync_sold_out"); ?>'
       }, function(response) {
           if (response.success) {
               alert('Sold-out status synced successfully! Page will reload.');
               location.reload();
           } else {
               alert('Error: ' + (response.data || 'Unknown error'));
               $btn.prop("disabled", false).html(originalText);
           }
       }).fail(function() {
           alert('Failed to sync sold-out status. Please try again.');
           $btn.prop("disabled", false).html(originalText);
       });
   });

   // Hide Image Change Handler (for debugging/visual feedback).
   $(".smdp-hide-image").on("change", function() {
       var $chk = $(this);
       var itemId = $chk.data("item-id");
       console.log("Item " + itemId + " hide image: " + $chk.is(":checked"));
       // Optionally, add or remove a visual class on the item.
       var $item = $chk.closest(".smdp-sortable-item");
       if ($chk.is(":checked")) {
           $item.addClass("image-hidden");
       } else {
           $item.removeClass("image-hidden");
       }
   });
});
</script>

<style>
  .smdp-sortable-placeholder {
     border: 1px dashed #aaa;
     background: #f9f9f9;
     width: 200px;
     height: 200px;
     margin: 3px;
  }
  .smdp-add-item-option.selected {
      background: #e0e0e0;
  }
  .smdp-add-item-btn:hover,
  .smdp-hide-category-btn:hover {
      background-color: #006799;
  }
  .hidden-category {
      opacity: 0.6;
  }
</style>
