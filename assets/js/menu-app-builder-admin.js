(function($){
  $(function(){
    // Function to initialize color pickers
    function initColorPickers() {
      if ($.fn.wpColorPicker) {
        $('.smdp-color-picker:visible, .smdp-pwa-color-picker:visible').each(function(){
          if (!$(this).hasClass('wp-color-picker')) {
            var $input = $(this);
            // Initialize with change handler for preview updates
            $input.wpColorPicker({
              change: function(event, ui) {
                // Trigger custom event for inline scripts to hook into
                $(document).trigger('smdp-color-changed');
              }
            });
          }
        });
      }
    }

    // Initialize color pickers on page load - use slight delay to ensure DOM is ready
    setTimeout(initColorPickers, 200);

    // Re-initialize when configuration subtabs change
    $(document).on('click', '.smdp-config-subtab', function(){
      setTimeout(initColorPickers, 100);
    });

    // Re-initialize when style subtabs change
    $(document).on('click', '.smdp-style-subtab', function(){
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
        .catch(function(err){ // Error logged $status.text('Failed.'); });
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
        .catch(function(err){ // Error logged $status.text('Failed.'); });
      } else {
        $status.text('Failed.');
      }
    });
  });
})(jQuery);
