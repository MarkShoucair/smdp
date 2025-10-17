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

<div class="wrap">
  <h1>Menu Editor</h1>
  <p>Drag and drop items to reorder or move them between categories.<br>
     Use the "Hide Image" checkbox to disable front-end image display.
  </p>

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
                <div class="smdp-category-group <?php echo $hidden_class; ?>" data-category="<?php echo esc_attr($cat['id']); ?>" style="margin-bottom:30px; border:1px solid #ccc; padding:5px; background:#f6f6f6;">
                  <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                       <h2 style="margin:5px 0; display:inline-block;"><?php echo esc_html($cat['name']); ?></h2>
                       <span class="smdp-shortcode" data-shortcode="<?php echo esc_attr($shortcode); ?>" style="cursor:pointer; background:#f0f0f0; padding:2px 5px; border:1px solid #0073aa; border-radius:3px; margin-left:10px;">Copy Shortcode</span>
                    </div>
                    <div>
                       <button type="button" class="smdp-add-item-btn" data-target="<?php echo esc_attr($cat['id']); ?>" style="display:inline-block; margin-right:5px; padding:5px 10px; background:#0073aa; color:#fff; border:none; border-radius:3px; cursor:pointer;">Add Item</button>
                       <button type="button" class="smdp-hide-category-btn" data-catid="<?php echo esc_attr($cat['id']); ?>" style="display:inline-block; padding:5px 10px; background:#f39c12; color:#fff; border:none; border-radius:3px; cursor:pointer;">
                          <?php echo (isset($cat['hidden']) && $cat['hidden']) ? "Show Category" : "Hide Category"; ?>
                       </button>
                    </div>
                  </div>
                  <ul class="smdp-sortable-group" style="display:flex; flex-wrap:wrap; gap:10px; list-style:none; margin:0; padding:0; min-height:220px;">
                     <?php
                     if (isset($grouped_items[$cat['id']]) && count($grouped_items[$cat['id']]) > 0) {
                         foreach ($grouped_items[$cat['id']] as $item) {
                             ?>
                             <li class="smdp-sortable-item" data-item-id="<?php echo esc_attr($item['id']); ?>" style="width:200px; height:200px; padding:10px; border:1px solid #eee; background:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; box-sizing:border-box;">
                                 <?php if (!empty($item['thumbnail'])) : ?>
                                     <img src="<?php echo esc_url($item['thumbnail']); ?>" alt="<?php echo esc_attr($item['name']); ?>" style="max-width:80px; margin-bottom:10px;">
                                 <?php endif; ?>
                                 <div style="text-align:center;">
                                     <span class="smdp-item-name" style="font-size:1.1em; display:block; margin-bottom:5px;"><?php echo esc_html($item['name']); ?></span>
                                     <label style="font-size:0.9em;">
                                         <input type="checkbox" class="smdp-hide-image" data-item-id="<?php echo esc_attr($item['id']); ?>" value="1" <?php checked($item['hide_image'], 1); ?>>
                                         Hide Image
                                     </label>
                                     <button type="button" class="smdp-remove-item" data-item-id="<?php echo esc_attr($item['id']); ?>" style="margin-top:5px; padding:2px 5px; background:#d9534f; color:#fff; border:none; border-radius:3px; cursor:pointer; font-size:0.8em;">Remove</button>
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
                         ?>
                         <li class="smdp-sortable-item" data-item-id="<?php echo esc_attr($item['id']); ?>" style="width:200px; height:200px; padding:10px; border:1px solid #eee; background:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; box-sizing:border-box;">
                             <?php if (!empty($item['thumbnail'])) : ?>
                                 <img src="<?php echo esc_url($item['thumbnail']); ?>" alt="<?php echo esc_attr($item['name']); ?>" style="max-width:80px; margin-bottom:10px;">
                             <?php endif; ?>
                             <div style="text-align:center;">
                                 <span class="smdp-item-name" style="font-size:1.1em; display:block; margin-bottom:5px;"><?php echo esc_html($item['name']); ?></span>
                                 <label style="font-size:0.9em;">
                                     <input type="checkbox" class="smdp-hide-image" data-item-id="<?php echo esc_attr($item['id']); ?>" value="1" <?php checked($item['hide_image'], 1); ?>>
                                     Hide Image
                                 </label>
                                 <button type="button" class="smdp-remove-item" data-item-id="<?php echo esc_attr($item['id']); ?>" style="margin-top:5px; padding:2px 5px; background:#d9534f; color:#fff; border:none; border-radius:3px; cursor:pointer; font-size:0.8em;">Remove</button>
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

  <!-- Modal Dialog for Adding Items -->
  <div id="smdp-add-item-dialog" title="Add Items" style="display:none;">
     <input type="text" id="smdp-add-item-search" placeholder="Search items..." style="width:100%; margin-bottom:10px;" />
     <div id="smdp-add-item-list" style="display:flex; flex-wrap:wrap; gap:10px; max-height:400px; overflow-y:auto;"></div>
  </div>
</div>

<script>
jQuery(document).ready(function($) {
   // Initialize sortable groups.
   $(".smdp-sortable-group").sortable({
      connectWith: ".smdp-sortable-group",
      placeholder: "smdp-sortable-placeholder",
      forcePlaceholderSize: true,
      revert: true,
      tolerance: "pointer",
      helper: "clone"
   }).disableSelection();

   // Build mapping on form submit.
   $("#smdp-items-form").on("submit", function(e) {
      var mapping = {};
      $(".smdp-sortable-item").each(function() {
         var itemId = $(this).data("item-id");
         var parentCategory = $(this).closest(".smdp-category-group").data("category") || "unassigned";
         var order = $(this).index();
         var hideImage = $(this).find(".smdp-hide-image").is(":checked") ? 1 : 0;
         mapping[itemId] = { category: parentCategory, order: order, hide_image: hideImage };
      });
      $("#mapping_json").val(JSON.stringify(mapping));
      return true;
   });

   // Remove Item: move item to Unassigned.
   $("#smdp-items-container").on("click", ".smdp-remove-item", function() {
      var $item = $(this).closest(".smdp-sortable-item");
      $("#smdp-items-container .smdp-category-group[data-category='unassigned'] ul.smdp-sortable-group").append($item);
   });

   // "Add Item" handler.
   $(".smdp-add-item-btn").on("click", function() {
       var targetCategory = $(this).data("target");
       $("#smdp-add-item-dialog").data("targetCategory", targetCategory);

       var gridHtml = "";
       $("#smdp-items-container .smdp-category-group[data-category='unassigned'] .smdp-sortable-item").each(function() {
          var itemId = $(this).data("item-id");
          var itemName = $(this).find(".smdp-item-name").text();
          var thumbHtml = "";
          var $img = $(this).find("img");
          if ($img.length) {
              thumbHtml = "<div style='width:40px; height:40px; margin-bottom:5px;'>" + $img[0].outerHTML + "</div>";
          }
          gridHtml += "<div class='smdp-add-item-option' data-id='" + itemId + "' style='width:45%; border:1px solid #ddd; padding:5px; text-align:center; cursor:pointer; margin-bottom:10px;'>"
              + thumbHtml + "<div>" + itemName + "</div></div>";
       });
       if ( gridHtml === "" ) {
           gridHtml = "<p>No unassigned items available.</p>";
       }
       $("#smdp-add-item-list").html(gridHtml);

       $("#smdp-add-item-list .smdp-add-item-option").off("click").on("click", function() {
          $(this).toggleClass("selected");
       });

       $("#smdp-add-item-search").off("keyup").on("keyup", function() {
          var searchVal = $(this).val().toLowerCase();
          $("#smdp-add-item-list .smdp-add-item-option").each(function() {
              var text = $(this).text().toLowerCase();
              $(this).toggle(text.indexOf(searchVal) > -1);
          });
       });

       $("#smdp-add-item-dialog").dialog({
          modal: true,
          width: 600,
          buttons: {
              "Add": function() {
                  var selectedOptions = $("#smdp-add-item-list .smdp-add-item-option.selected");
                  if ( selectedOptions.length === 0 ) {
                      alert("Please select at least one item.");
                      return;
                  }
                  selectedOptions.each(function() {
                      var selectedId = $(this).data("id");
                      if ( selectedId ) {
                          var $item = $("#smdp-items-container .smdp-category-group[data-category='unassigned'] .smdp-sortable-item[data-item-id='" + selectedId + "']");
                          if ( $item.length ) {
                              $("#smdp-items-container .smdp-category-group[data-category='" + $("#smdp-add-item-dialog").data("targetCategory") + "'] ul.smdp-sortable-group").append($item);
                          }
                      }
                  });
                  $(this).dialog("close");
              },
              "Cancel": function() {
                  $(this).dialog("close");
              }
          }
       });
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

   // Shortcode copy handler.
   $(".smdp-shortcode").on("click", function() {
       var shortcodeText = $(this).data("shortcode");
       if(navigator.clipboard) {
           navigator.clipboard.writeText(shortcodeText).then(function() {
               alert("Shortcode copied to clipboard: " + shortcodeText);
           }, function(err) {
               alert("Failed to copy: " + err);
           });
       } else {
           var tempInput = $("<textarea>");
           $("body").append(tempInput);
           tempInput.val(shortcodeText).select();
           document.execCommand("copy");
           tempInput.remove();
           alert("Shortcode copied to clipboard: " + shortcodeText);
       }
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
