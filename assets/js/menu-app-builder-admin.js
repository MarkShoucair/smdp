(function($){
  $(function(){
    // Function to initialize custom color pickers
    function initColorPickers() {
      var $pickers = $('.smdp-color-picker, .smdp-pwa-color-picker');

      // Debug: log how many pickers we found
      if ($pickers.length > 0) {
        console.log('SMDP: Found ' + $pickers.length + ' color pickers to initialize');
      }

      $pickers.each(function(){
        var $textInput = $(this);

        // Skip if already initialized
        if ($textInput.data('color-picker-initialized')) {
          return;
        }

        console.log('SMDP: Initializing color picker');
        $textInput.data('color-picker-initialized', true);

        // Create wrapper
        var $wrapper = $('<div class="smdp-custom-color-picker"></div>');

        // Create HTML5 color input
        var $colorInput = $('<input type="color" class="smdp-color-visual">');
        $colorInput.val($textInput.val() || '#ffffff');

        // Style the text input
        $textInput.css({
          'width': '100px',
          'margin-left': '8px',
          'font-family': 'monospace',
          'text-transform': 'uppercase'
        });

        // Wrap both inputs
        $textInput.before($wrapper);
        $wrapper.append($colorInput);
        $wrapper.append($textInput);

        // Sync color input -> text input
        $colorInput.on('input change', function(){
          $textInput.val($(this).val().toUpperCase());
          $textInput.trigger('change');
          $(document).trigger('smdp-color-changed');
        });

        // Sync text input -> color input
        $textInput.on('input change blur', function(){
          var val = $(this).val();
          // Validate and format hex color
          if (val.match(/^#?[0-9A-Fa-f]{6}$/)) {
            if (!val.startsWith('#')) {
              val = '#' + val;
              $(this).val(val);
            }
            $colorInput.val(val);
            $(document).trigger('smdp-color-changed');
          }
        });
      });
    }

    // Initialize color pickers on page load
    setTimeout(initColorPickers, 100);

    // Also initialize after a longer delay to catch any late-loaded elements
    setTimeout(initColorPickers, 500);

    // Re-initialize when configuration subtabs change
    $(document).on('click', '.smdp-config-subtab', function(){
      setTimeout(initColorPickers, 100);
      setTimeout(initColorPickers, 300);
    });

    // Re-initialize when style subtabs change
    $(document).on('click', '.smdp-style-subtab', function(e){
      e.preventDefault();
      var target = $(this).attr('href');

      // Switch active tab
      $('.smdp-style-subtab').removeClass('active').css({'border-bottom': 'none', 'color': '#666'});
      $(this).addClass('active').css({'border-bottom': '2px solid #2271b1', 'color': '#000'});

      // Show/hide content
      $('.smdp-style-subtab-content').hide();
      $(target).show();

      // Re-initialize color pickers for newly shown content
      setTimeout(initColorPickers, 100);
      setTimeout(initColorPickers, 300);

      return false;
    });

    // Also re-initialize when main tabs change
    $(document).on('click', '.nav-tab', function(){
      setTimeout(initColorPickers, 100);
      setTimeout(initColorPickers, 300);
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
