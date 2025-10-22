jQuery(document).ready(function($){
  // Get custom styles from localized script (set in class-admin-assets.php)
  var styles = window.smdpItemDetailStyles || {
    modal_bg: '#ffffff',
    modal_border_color: '#3498db',
    modal_border_width: 6,
    modal_border_radius: 12,
    modal_box_shadow: '0 0 30px rgba(52,152,219,0.6), 0 0 60px rgba(52,152,219,0.4)',
    title_color: '#000000',
    title_size: 24,
    title_weight: 'bold',
    price_color: '#27ae60',
    price_size: 19,
    price_weight: 'bold',
    desc_color: '#666666',
    desc_size: 16,
    close_btn_bg: '#3498db',
    close_btn_text: '#ffffff',
    close_btn_hover_bg: '#2980b9'
  };

  // Inject modal HTML & inline styles once
  if ($('#smdp-item-modal').length === 0) {
    $('body').append(`
      <style>
        /* Modal container - transparent background */
        #smdp-item-modal {
          display: none;
          position: fixed !important;
          top: 0 !important;
          left: 0 !important;
          right: 0 !important;
          bottom: 0 !important;
          width: 100vw !important;
          height: 100vh !important;
          background: transparent !important;
          z-index: 2147483647 !important;
          margin: 0 !important;
          padding: 0 !important;
          overflow-y: auto !important;
          -webkit-overflow-scrolling: touch;
          pointer-events: auto !important; /* Catch all clicks */
        }

        /* Prevent body scroll when modal is open */
        body.smdp-modal-open {
          overflow: hidden !important;
          touch-action: none;
          -webkit-overflow-scrolling: none;
        }

        /* Modifier bubbles styling */
        #smdp-item-modal .smdp-mod-category {
          background: #f5f5f5;
          border: 1px solid #ddd;
          border-radius: 8px;
          padding: 1rem;
          margin: 1rem 0;
        }
        #smdp-item-modal .smdp-mod-category h4 {
          font-size: 1.2rem;
          margin: 0 0 0.5rem;
        }
        #smdp-item-modal .smdp-mod-category ul {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 0.5rem;
          margin: 0;
          padding-left: 1.2em;
          list-style: disc inside;
        }
        #smdp-item-modal .smdp-mod-category li {
          margin-bottom: 0.5rem;
        }

        /* Modal content box with customizable border and glow - USES CUSTOM STYLES */
        #smdp-item-modal .smdp-item-modal-inner {
          position: relative;
          width: 85vw;
          max-width: 600px;
          max-height: 80vh;
          margin: 10vh auto;
          background: ${styles.modal_bg};
          padding: 1.5rem;
          border-radius: ${styles.modal_border_radius}px;
          border: ${styles.modal_border_width}px solid ${styles.modal_border_color};
          overflow-y: auto;
          box-shadow: ${styles.modal_box_shadow};
          pointer-events: auto;
        }

        /* Responsive sizing for different screens */
        @media (max-width: 768px) {
          #smdp-item-modal .smdp-item-modal-inner {
            width: 90vw;
            max-width: none;
            margin: 5vh auto;
            max-height: 90vh;
          }
        }

        @media (min-width: 1200px) {
          #smdp-item-modal .smdp-item-modal-inner {
            max-width: 700px;
          }
        }

        /* Better image sizing in modal */
        #smdp-item-modal #smdp-item-img {
          max-width: 100%;
          max-height: 300px;
          width: auto;
          height: auto;
          display: block;
          margin: 0 auto 1rem;
          border-radius: 8px;
        }

        /* Close button with custom styles */
        #smdp-item-modal #smdp-item-close {
          margin-top: 1.5rem;
          padding: 0.75rem 2rem;
          background: ${styles.close_btn_bg};
          color: ${styles.close_btn_text};
          border: none;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          width: 100%;
          transition: background 0.2s ease;
        }

        #smdp-item-modal #smdp-item-close:hover {
          background: ${styles.close_btn_hover_bg};
        }
      </style>
      <div id="smdp-item-modal">
        <div class="smdp-item-modal-inner">
          <img id="smdp-item-img" src="" alt="">
          <h2 id="smdp-item-name" style="margin:0 0 .5rem; font-size:${styles.title_size}px; font-weight:${styles.title_weight}; color:${styles.title_color};"></h2>
          <p id="smdp-item-price" style="font-weight:${styles.price_weight}; margin:0 0 .5rem; font-size:${styles.price_size}px; color:${styles.price_color};"></p>
          <p id="smdp-item-desc" style="margin:0 0 1rem; color:${styles.desc_color}; line-height:1.5; font-size:${styles.desc_size}px;"></p>
          <div class="smdp-modifiers"></div>
          <button id="smdp-item-close">Close</button>
        </div>
      </div>
    `);
  }

  // Open popup when an item tile is clicked
  $(document).on('click', '.smdp-item-tile, .smdp-menu-item', function(e){
    e.preventDefault();
    e.stopPropagation();

    var $t = $(this);

    // Check if modal is enabled for this context
    // First check if we're inside a menu app (takes precedence)
    var $menuApp = $t.closest('.smdp-menu-app-fe');
    var $shortcodeContainer = $t.closest('.smdp-menu-container');

    var modalEnabled = '1'; // Default to enabled

    if ($menuApp.length) {
      // Inside menu app - use menu app's setting
      modalEnabled = $menuApp.attr('data-modal-enabled');
    } else if ($shortcodeContainer.length) {
      // Standalone shortcode - use shortcode's setting
      modalEnabled = $shortcodeContainer.attr('data-modal-enabled');
    }

    // If modal is explicitly disabled, don't open it
    if (modalEnabled === '0') {
      return;
    }

    // Prevent body scroll
    $('body').addClass('smdp-modal-open');

    // Populate base fields
    $('#smdp-item-img').attr('src',   $t.data('img')   || $t.find('img').attr('src'));
    $('#smdp-item-name').text(        $t.data('name')  || $t.find('h3').text());
    $('#smdp-item-price').text(       $t.data('price') || $t.find('strong, p').first().text());
    $('#smdp-item-desc').text(        $t.data('desc')  || '');

    // Inject cached modifiers HTML
    var modsHtml = $t.find('.smdp-mod-data').html() || '<p style="color:#999; font-style:italic;">No modifiers available</p>';
    $('#smdp-item-modal').find('.smdp-modifiers').html(modsHtml);

    $('#smdp-item-modal').fadeIn(300);
  });

  // Close function
  function closeModal() {
    // Reset scroll positions BEFORE closing
    var $inner = $('#smdp-item-modal .smdp-item-modal-inner');
    $inner.scrollTop(0);
    $('#smdp-item-modal').scrollTop(0);
    
    $('#smdp-item-modal').fadeOut(300, function() {
      // Restore body scroll
      $('body').removeClass('smdp-modal-open');
    });
  }

  // Close popup when clicking close button
  $(document).on('click', '#smdp-item-close', function(e){
    e.preventDefault();
    e.stopPropagation();
    closeModal();
  });

  // Click/tap outside modal to close
  $(document).on('click touchend', '#smdp-item-modal', function(e){
    // Only close if clicking/tapping the overlay, not the modal content
    if (e.target.id === 'smdp-item-modal') {
      e.preventDefault();
      e.stopPropagation();
      closeModal();
    }
  });

  // Prevent clicks inside modal from closing it
  $(document).on('click touchend', '.smdp-item-modal-inner', function(e){
    e.stopPropagation();
  });

  // Close on ESC key
  $(document).on('keydown', function(e) {
    if (e.key === 'Escape' && $('#smdp-item-modal').is(':visible')) {
      closeModal();
    }
  });
});