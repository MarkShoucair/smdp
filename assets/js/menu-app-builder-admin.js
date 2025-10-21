console.log('[SMDP] menu-app-builder-admin.js file loaded');
(function($){
  $(function(){
    console.log('[SMDP Menu App Builder] Admin JS DOM ready');

    // Function to initialize color pickers
    function initColorPickers() {
      if ($.fn.wpColorPicker) {
        console.log('[SMDP] Initializing color pickers...');
        $('.smdp-color-picker:visible, .smdp-pwa-color-picker:visible').each(function(){
          if (!$(this).hasClass('wp-color-picker')) {
            var $input = $(this);
            // Initialize with change handler for preview updates
            $input.wpColorPicker({
              change: function(event, ui) {
                // Trigger updatePreview if it exists (for category button styles)
                if (typeof updatePreview === 'function') {
                  updatePreview();
                }
              }
            });
            console.log('[SMDP] Initialized color picker:', $(this).attr('name'));
          }
        });
      } else {
        console.warn('[SMDP] wpColorPicker not available');
      }
    }

    // Initialize color pickers on page load
    initColorPickers();

    // Re-initialize when configuration subtabs change
    $(document).on('click', '.smdp-config-subtab', function(){
      console.log('[SMDP] Config subtab clicked, reinitializing color pickers...');
      setTimeout(initColorPickers, 100);
    });

    // Re-initialize when style subtabs change
    $(document).on('click', '.smdp-style-subtab', function(){
      console.log('[SMDP] Style subtab clicked, reinitializing color pickers...');
      setTimeout(initColorPickers, 100);
    });

    var $list = $('#smdp-cat-order');
    if ($list.length && $.fn.sortable) {
      $list.sortable({ containment:'parent' });
    }

    // Exclude hidden toggle
    var $toggle = $('#smdp-order-exclude-hidden');
    function refreshHidden() {
      var on = $toggle.is(':checked');
      if (on) {
        $list.find('.smdp-cat-order-item.is-hidden').hide();
      } else {
        $list.find('.smdp-cat-order-item.is-hidden').show();
      }
    }
    if ($toggle.length) {
      $toggle.on('change', refreshHidden);
      refreshHidden();
    }

    // Save Order
    $('#smdp-save-cat-order').on('click', function(){
      var order = [];
      var $items = ($toggle.length && $toggle.is(':checked'))
        ? $list.find('.smdp-cat-order-item:visible')
        : $list.find('.smdp-cat-order-item');

      $items.each(function(){ order.push($(this).data('id')); });

      var $status = $('#smdp-cat-order-status').text('Saving…');

      // Prefer apiFetch if present
      if (window.wp && wp.apiFetch) {
        wp.apiFetch({
          path: 'smdp/v1/categories-order',
          method: 'POST',
          data: { order: order },
        }).then(function(){ $status.text('Saved.'); })
        .catch(function(err){ console.error(err); $status.text('Failed.'); });
        return;
      }

      // Fallback to fetch
      if (window.SMDP_MENU_APP && SMDP_MENU_APP.rest && SMDP_MENU_APP.rest.base) {
        fetch(SMDP_MENU_APP.rest.base + 'categories-order', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': SMDP_MENU_APP.rest.nonce
          },
          body: JSON.stringify({order: order})
        }).then(function(r){ return r.json().then(function(j){ return {ok:r.ok, j:j}; }); })
        .then(function(res){ $status.text(res.ok ? 'Saved.' : 'Failed.'); if (!res.ok) console.error(res.j); })
        .catch(function(err){ console.error(err); $status.text('Failed.'); });
      } else {
        $status.text('Failed.');
      }
    });
  });
})(jQuery);
