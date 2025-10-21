jQuery(document).ready(function($) {
  // Debug mode toggle (triple-tap top-RIGHT corner + PIN)
  var cornerTaps = 0;
  var cornerTapTimer = null;
  
  $(document).on('click touchstart', function(e) {
    // Skip if clicking on interactive elements
    if ($(e.target).closest('button, a, input').length) {
      return;
    }
    
    var x = e.clientX || (e.originalEvent.touches && e.originalEvent.touches[0].clientX);
    var y = e.clientY || (e.originalEvent.touches && e.originalEvent.touches[0].clientY);
    var w = $(window).width();
    
    // Check if tap is in top-RIGHT corner (100px x 100px area)
    if (x > w - 100 && y < 100) {
      cornerTaps++;
      
      if (cornerTapTimer) clearTimeout(cornerTapTimer);
      
      if (cornerTaps === 3) {
        cornerTaps = 0;
        showDebugPinModal();
      } else {
        cornerTapTimer = setTimeout(function() {
          cornerTaps = 0;
        }, 500);
      }
    }
  });
  
  function showDebugPinModal() {
    // Check current server-side debug mode status via AJAX first
    $.ajax({
      url: smdpViewBill.ajax_url,
      type: 'POST',
      data: {
        action: 'smdp_get_pwa_debug_status',
        security: smdpViewBill.nonce
      },
      success: function(response) {
        var currentMode = response.success ? response.data.enabled : false;
        renderDebugPinModal(currentMode);
      },
      error: function() {
        // Default to false if we can't check
        renderDebugPinModal(false);
      }
    });
  }
  
  function renderDebugPinModal(currentMode) {
    var modal = $('<div id="smdp-debug-pin-modal" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:transparent;z-index:2147483647;display:flex;align-items:center;justify-content:center;pointer-events:auto;"></div>');
    
    var modalInner = `
      <style>
        body.smdp-debug-pin-modal-open {
          overflow: hidden !important;
          touch-action: none;
        }
        .smdp-debug-pin-inner {
          background: #fff;
          padding: 2rem;
          border-radius: 12px;
          border: 4px solid #f39c12;
          max-width: 400px;
          width: 85vw;
          text-align: center;
          box-shadow: 
            0 0 10px 5px rgba(0,0,0,0.5),
            0 0 20px 10px rgba(0,0,0,0.3),
            0 0 30px 15px rgba(0,0,0,0.2),
            0 0 40px 20px rgba(0,0,0,0.1),
            0 10px 40px rgba(0,0,0,0.4);
          pointer-events: auto;
        }
        .smdp-debug-pin-inner h2 {
          margin: 0 0 1rem;
          font-size: 1.6rem;
          color: #333;
        }
        .smdp-debug-pin-inner p {
          margin: 0 0 1.5rem;
          color: #666;
        }
        .smdp-pin-display {
          display: flex;
          justify-content: center;
          gap: 15px;
          margin-bottom: 2rem;
        }
        .smdp-pin-dot {
          width: 20px;
          height: 20px;
          border: 3px solid #ddd;
          border-radius: 50%;
          background: #fff;
          transition: all 0.2s ease;
        }
        .smdp-pin-dot.filled {
          background: #f39c12;
          border-color: #f39c12;
        }
        .smdp-pin-dot.error {
          background: #e74c3c;
          border-color: #e74c3c;
          animation: shake 0.3s;
        }
        @keyframes shake {
          0%, 100% { transform: translateX(0); }
          25% { transform: translateX(-5px); }
          75% { transform: translateX(5px); }
        }
        .smdp-pin-keypad {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: 10px;
          margin-bottom: 1rem;
        }
        .smdp-pin-key {
          padding: 1.5rem;
          background: #f5f5f5;
          border: 2px solid #ddd;
          border-radius: 8px;
          font-size: 1.5rem;
          font-weight: 600;
          cursor: pointer;
          transition: all 0.1s ease;
          user-select: none;
        }
        .smdp-pin-key:active {
          background: #e0e0e0;
          transform: scale(0.95);
        }
        .smdp-pin-key.disabled {
          opacity: 0;
          pointer-events: none;
        }
        .smdp-pin-key.clear {
          background: #e74c3c;
          color: #fff;
          border-color: #e74c3c;
          font-size: 1rem;
        }
        .smdp-pin-key.clear:active {
          background: #c0392b;
        }
        .smdp-debug-cancel {
          width: 100%;
          padding: 0.75rem 1.5rem;
          background: #95a5a6;
          color: #fff;
          border: none;
          border-radius: 8px;
          font-size: 1rem;
          font-weight: 600;
          cursor: pointer;
          transition: background 0.2s ease;
        }
        .smdp-debug-cancel:hover {
          background: #7f8c8d;
        }
        .smdp-pin-error {
          color: #e74c3c;
          font-size: 0.9rem;
          margin: 1rem 0 0;
          display: none;
          font-weight: 600;
        }
        .smdp-pin-error.active {
          display: block;
        }
        .smdp-debug-status {
          background: #f0f0f0;
          padding: 1rem;
          border-radius: 8px;
          margin-bottom: 1.5rem;
        }
        .smdp-debug-status strong {
          color: ${currentMode ? '#27ae60' : '#e74c3c'};
        }
        .smdp-spinner {
          animation: spin 1s linear infinite;
        }
        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }
      </style>
      <div class="smdp-debug-pin-inner">
        <h2>🔧 PWA Debug Mode</h2>
        <div class="smdp-debug-status">
          <p style="margin:0;">Current Status: <strong>${currentMode ? 'ENABLED ✓' : 'DISABLED ✗'}</strong></p>
        </div>
        <p style="font-size:0.95rem;">Enter admin PIN to ${currentMode ? 'disable' : 'enable'} the debug panel:</p>
        
        <div class="smdp-pin-display">
          <div class="smdp-pin-dot"></div>
          <div class="smdp-pin-dot"></div>
          <div class="smdp-pin-dot"></div>
          <div class="smdp-pin-dot"></div>
        </div>
        
        <div class="smdp-pin-keypad">
          <button class="smdp-pin-key" data-num="1">1</button>
          <button class="smdp-pin-key" data-num="2">2</button>
          <button class="smdp-pin-key" data-num="3">3</button>
          <button class="smdp-pin-key" data-num="4">4</button>
          <button class="smdp-pin-key" data-num="5">5</button>
          <button class="smdp-pin-key" data-num="6">6</button>
          <button class="smdp-pin-key" data-num="7">7</button>
          <button class="smdp-pin-key" data-num="8">8</button>
          <button class="smdp-pin-key" data-num="9">9</button>
          <button class="smdp-pin-key disabled"></button>
          <button class="smdp-pin-key" data-num="0">0</button>
          <button class="smdp-pin-key clear">Clear</button>
        </div>
        
        <p class="smdp-pin-error" id="smdp-debug-pin-error">Incorrect PIN</p>
        
        <button class="smdp-debug-cancel" id="smdp-debug-pin-cancel">Cancel</button>
      </div>
    `;
    
    modal.html(modalInner);
    $('body').append(modal).addClass('smdp-debug-pin-modal-open');
    
    var pinValue = '';
    var $dots = modal.find('.smdp-pin-dot');
    var $keys = modal.find('.smdp-pin-key');
    var $cancel = modal.find('#smdp-debug-pin-cancel');
    var $error = modal.find('#smdp-debug-pin-error');
    
    function updateDisplay() {
      $dots.each(function(index) {
        if (index < pinValue.length) {
          $(this).addClass('filled');
        } else {
          $(this).removeClass('filled');
        }
        $(this).removeClass('error');
      });
    }
    
    $keys.on('click', function() {
      var num = $(this).data('num');
      
      if ($(this).hasClass('clear')) {
        pinValue = '';
        $error.removeClass('active');
        updateDisplay();
      } else if (num !== undefined && pinValue.length < 4) {
        pinValue += num;
        $error.removeClass('active');
        updateDisplay();
        
        if (pinValue.length === 4) {
          setTimeout(checkPin, 200);
        }
      }
    });
    
    function checkPin() {
      if (pinValue === '2439') {
        // Show processing message
        modal.find('.smdp-debug-pin-inner').html(`
          <h2>Processing...</h2>
          <div class="smdp-spinner" style="border:4px solid #f3f3f3;border-top:4px solid #f39c12;border-radius:50%;width:50px;height:50px;animation:spin 1s linear infinite;margin:2rem auto;"></div>
          <p style="color:#666;font-size:0.9rem;">Toggling PWA debug mode...</p>
        `);
        
        // Make AJAX call to toggle server-side debug mode
        $.ajax({
          url: smdpViewBill.ajax_url,
          type: 'POST',
          data: {
            action: 'smdp_toggle_pwa_debug',
            security: smdpViewBill.nonce
          },
          success: function(response) {
            if (response.success) {
              var newMode = response.data.enabled;
              
              // Show success message
              modal.find('.smdp-debug-pin-inner').html(`
                <h2>PWA Debug Mode ${newMode ? 'Enabled' : 'Disabled'}</h2>
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="${newMode ? '#27ae60' : '#e74c3c'}" stroke-width="2" style="margin:1rem auto;display:block;">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <p style="font-size:1.2rem;margin:1rem 0;">PWA Debug mode is now <strong>${newMode ? 'enabled' : 'disabled'}</strong>.</p>
                <p style="color:#666;font-size:0.9rem;">Clearing all caches...</p>
                <div class="smdp-spinner" style="border:4px solid #f3f3f3;border-top:4px solid #27ae60;border-radius:50%;width:40px;height:40px;animation:spin 1s linear infinite;margin:1rem auto;"></div>
              `);
              
              // Aggressive cache clearing sequence
              var tableNum = localStorage.getItem('smdp_table_number');
              
              // Step 1: Clear localStorage (preserve table number only)
              localStorage.clear();
              if (tableNum) {
                localStorage.setItem('smdp_table_number', tableNum);
              }
              
              // Step 2: Clear sessionStorage
              sessionStorage.clear();
              
              // Step 3: Clear all cookies (except essential ones)
              var cookies = document.cookie.split(";");
              for (var i = 0; i < cookies.length; i++) {
                var cookie = cookies[i];
                var eqPos = cookie.indexOf("=");
                var name = eqPos > -1 ? cookie.substr(0, eqPos) : cookie;
                // Don't clear WordPress auth cookies
                if (name.trim().indexOf('wordpress') === -1 && name.trim().indexOf('wp-') === -1) {
                  document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";
                }
              }
              
              // Step 4: Unregister all service workers
              if ('serviceWorker' in navigator) {
                navigator.serviceWorker.getRegistrations().then(function(registrations) {
                  //  Unregistering ' + registrations.length + ' service workers');
                  for (var registration of registrations) {
                    registration.unregister();
                  }
                });
              }
              
              // Step 5: Clear all cache storage
              if ('caches' in window) {
                caches.keys().then(function(names) {
                  //  Clearing ' + names.length + ' cache storages');
                  var deletePromises = names.map(function(name) {
                    return caches.delete(name);
                  });
                  
                  Promise.all(deletePromises).then(function() {
                    //  All caches cleared, performing hard reload...');
                    
                    // Step 6: Force reload from server (bypass all caches)
                    setTimeout(function() {
                      $('body').removeClass('smdp-debug-pin-modal-open');
                      modal.remove();
                      
                      // Multiple reload attempts to ensure cache bypass
                      if (window.location.reload) {
                        // First attempt: hard reload with cache bypass
                        window.location.reload(true);
                      }
                      
                      // Fallback: force reload via URL manipulation
                      setTimeout(function() {
                        var url = window.location.href;
                        var separator = url.indexOf('?') > -1 ? '&' : '?';
                        window.location.href = url + separator + '_cache_bust=' + new Date().getTime();
                      }, 100);
                    }, 1000);
                  });
                });
              } else {
                // No cache API, just hard reload
                //  No cache API, performing hard reload...');
                setTimeout(function() {
                  $('body').removeClass('smdp-debug-pin-modal-open');
                  modal.remove();
                  
                  // Force reload
                  window.location.reload(true);
                  
                  // Fallback
                  setTimeout(function() {
                    var url = window.location.href;
                    var separator = url.indexOf('?') > -1 ? '&' : '?';
                    window.location.href = url + separator + '_cache_bust=' + new Date().getTime();
                  }, 100);
                }, 1000);
              }
            } else {
              // Show error
              modal.find('.smdp-debug-pin-inner').html(`
                <h2>Error</h2>
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2" style="margin:1rem auto;display:block;">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="15" y1="9" x2="9" y2="15"></line>
                  <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                <p style="color:#e74c3c;">Failed to toggle debug mode</p>
                <button class="smdp-debug-cancel" style="width:100%;padding:0.75rem;background:#95a5a6;color:#fff;border:none;border-radius:8px;margin-top:1rem;cursor:pointer;">Close</button>
              `);
              
              modal.find('.smdp-debug-cancel').on('click', function() {
                $('body').removeClass('smdp-debug-pin-modal-open');
                modal.remove();
              });
            }
          },
          error: function() {
            // Show error
            modal.find('.smdp-debug-pin-inner').html(`
              <h2>Connection Error</h2>
              <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2" style="margin:1rem auto;display:block;">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
              </svg>
              <p style="color:#e74c3c;">Failed to connect to server</p>
              <button class="smdp-debug-cancel" style="width:100%;padding:0.75rem;background:#95a5a6;color:#fff;border:none;border-radius:8px;margin-top:1rem;cursor:pointer;">Close</button>
            `);
            
            modal.find('.smdp-debug-cancel').on('click', function() {
              $('body').removeClass('smdp-debug-pin-modal-open');
              modal.remove();
            });
          }
        });
      } else {
        // Show error
        $dots.addClass('error');
        $error.addClass('active');
        
        setTimeout(function() {
          pinValue = '';
          updateDisplay();
        }, 500);
      }
    }
    
    function closeModal() {
      $('body').removeClass('smdp-debug-pin-modal-open');
      modal.remove();
    }
    
    $cancel.on('click', closeModal);
    modal.on('click', function(e) {
      if (e.target.id === 'smdp-debug-pin-modal') {
        closeModal();
      }
    });
  }

  // Helper function to disable bill buttons across all instances
  function disableAllBillButtons(table) {
    // Use the global function from help-request.js if available
    if (typeof window.smdpDisableAllBillButtons === 'function') {
      window.smdpDisableAllBillButtons(table);
    }
  }
  
  // View Bill button click handler
  $(document).on('click', '.smdp-view-bill-btn', function() {
    var table = $(this).data('table');
    
    // Show loading modal
    showBillLoadingModal();
    
    // Fetch bill from Square
    $.ajax({
      url: smdpViewBill.ajax_url,
      type: 'POST',
      data: {
        action: 'smdp_get_bill',
        security: smdpViewBill.nonce,
        table: table
      },
      timeout: 15000,
      success: function(response) {
        if (response.success && response.data) {
          showBillModal(response.data, table);
        } else {
          showBillError(response.data || 'No order found for this table.');
        }
      },
      error: function(xhr, status, error) {
        showBillError('Unable to retrieve bill. Please try again.');
      }
    });
  });
  
  // Handle Request Bill button inside the bill modal
  $(document).on('click', '#smdp-bill-modal .smdp-bill-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    var $btn = $(this);
    var table = $btn.data('table');
    
    // Check if button is already disabled
    if ($btn.prop('disabled')) {
      return;
    }
    
    // Immediately disable button to prevent double-clicks
    $btn.prop('disabled', true);
    
    // Call the bill request AJAX - using the correct nonce
    $.post(
      smdpViewBill.ajax_url,
      {
        action: 'smdp_request_bill',
        security: smdpBill.nonce, // Use the bill nonce, not help nonce
        table: table
      }
    )
    .done(function(resp) {
      // Disable all bill buttons FIRST
      disableAllBillButtons(table);
      
      // Close the bill modal
      $('#smdp-bill-modal').remove();
      $('body').removeClass('smdp-bill-modal-open');
      
      // Show success message - wait for modal overlay to exist
      setTimeout(function() {
        const successIcon = `
          <svg class="smdp-checkmark-circle" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
            <circle class="smdp-checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
            <path class="smdp-checkmark" fill="none" stroke="white" stroke-width="3" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
          </svg>
        `;
        
        if ($('#smdp-modal-overlay').length) {
          $('#smdp-modal').css('border-color', '#3498db');
          $('#smdp-modal-close').css('background', '#3498db');
          $('#smdp-modal-icon').html(successIcon);
          $('#smdp-modal-message').text('Your bill is on the way for Table ' + table + '!');
          $('body').addClass('smdp-help-modal-open');
          $('#smdp-modal-overlay').fadeIn(300);
        }
      }, 100);
    })
    .fail(function(jqXHR) {
      // Close the bill modal first
      $('#smdp-bill-modal').remove();
      $('body').removeClass('smdp-bill-modal-open');
      
      // Extract error message
      let msg = 'Error: ';
      try {
        const json = JSON.parse(jqXHR.responseText);
        msg += json.data || json.message || 'Request failed';
      } catch (e) {
        msg += 'Request failed';
      }
      
      // Show error message - wait for modal overlay to exist
      setTimeout(function() {
        const errorIcon = `
          <svg class="smdp-error-circle" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
            <circle class="smdp-error-circle" cx="26" cy="26" r="25" fill="none"/>
            <path class="smdp-error-x" fill="none" stroke="white" stroke-width="3" d="M16 16 l20 20 M36 16 l-20 20"/>
          </svg>
        `;
        
        if ($('#smdp-modal-overlay').length) {
          $('#smdp-modal').css('border-color', '#e74c3c');
          $('#smdp-modal-close').css('background', '#e74c3c');
          $('#smdp-modal-icon').html(errorIcon);
          $('#smdp-modal-message').text(msg);
          $('body').addClass('smdp-help-modal-open');
          $('#smdp-modal-overlay').fadeIn(300);
        }
      }, 100);
    });
  });
  
  function showBillLoadingModal() {
    var modal = `
      <div id="smdp-bill-modal" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:transparent;z-index:2147483646;display:flex;align-items:center;justify-content:center;pointer-events:auto;">
        <div class="smdp-bill-modal-inner" style="background:#fff;padding:2.5rem;border-radius:12px;border:4px solid #3498db;max-width:500px;width:85vw;text-align:center;box-shadow:0 0 10px 5px rgba(0,0,0,0.5),0 0 20px 10px rgba(0,0,0,0.3),0 0 30px 15px rgba(0,0,0,0.2),0 0 40px 20px rgba(0,0,0,0.1),0 10px 40px rgba(0,0,0,0.4);pointer-events:auto;">
          <div class="smdp-spinner" style="border:4px solid #f3f3f3;border-top:4px solid #3498db;border-radius:50%;width:50px;height:50px;animation:spin 1s linear infinite;margin:0 auto 1rem;"></div>
          <p style="font-size:1.2rem;color:#666;margin:0;">Loading your bill...</p>
        </div>
      </div>
      <style>
        @keyframes spin {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }
        body.smdp-bill-modal-open {
          overflow: hidden !important;
          touch-action: none;
        }
      </style>
    `;
    
    $('body').append(modal).addClass('smdp-bill-modal-open');
  }
  
  function showBillModal(billData, table) {
    var items = billData.line_items || [];
    var total = billData.total_money ? (billData.total_money.amount / 100) : 0;
    var tax = billData.total_tax_money ? (billData.total_tax_money.amount / 100) : 0;
    var tip = billData.total_tip_money ? (billData.total_tip_money.amount / 100) : 0;
    var discount = billData.total_discount_money ? (billData.total_discount_money.amount / 100) : 0;
    
    // Calculate subtotal by summing line items BEFORE tax/tip/discount
    var subtotal = 0;
    items.forEach(function(item) {
      var itemTotal = item.base_price_money ? (item.base_price_money.amount / 100) : 0;
      var quantity = parseFloat(item.quantity) || 1;
      subtotal += itemTotal * quantity;
      
      // Add modifier costs
      if (item.modifiers && item.modifiers.length > 0) {
        item.modifiers.forEach(function(mod) {
          var modPrice = mod.base_price_money ? (mod.base_price_money.amount / 100) : 0;
          subtotal += modPrice * quantity;
        });
      }
    });
    
    var itemsHTML = '';
    items.forEach(function(item) {
      var name = item.name || 'Item';
      var quantity = item.quantity || '1';
      
      // Calculate pre-tax price: base price + modifiers
      var basePrice = item.base_price_money ? (item.base_price_money.amount / 100) : 0;
      var modifierTotal = 0;
      
      if (item.modifiers && item.modifiers.length > 0) {
        item.modifiers.forEach(function(mod) {
          var modPrice = mod.base_price_money ? (mod.base_price_money.amount / 100) : 0;
          modifierTotal += modPrice;
        });
      }
      
      var itemPrice = (basePrice + modifierTotal) * parseFloat(quantity);
      
      itemsHTML += `
        <div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #eee;">
          <div style="flex:1;">
            <div style="font-weight:600;margin-bottom:4px;">${quantity}x ${escapeHtml(name)}</div>
            ${item.modifiers ? formatModifiers(item.modifiers) : ''}
          </div>
          <div style="font-weight:600;white-space:nowrap;margin-left:15px;">$${itemPrice.toFixed(2)}</div>
        </div>
      `;
    });
    
    // Check if bill button should be disabled
    var billBtnDisabled = '';
    var billBtnClass = 'smdp-bill-btn';
    var billBtnText = 'Request Bill';
    var billBtnIcon = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="5" width="20" height="14" rx="2"></rect>
              <line x1="2" y1="10" x2="22" y2="10"></line>
            </svg>`;
    
    // Check localStorage for cooldown
    var key = 'smdp_bill_' + table;
    var ts = parseInt(localStorage.getItem(key), 10);
    if (ts && Date.now() - ts < 5 * 60 * 1000) {
      billBtnDisabled = 'disabled';
      billBtnClass += ' smdp-btn-disabled';
      billBtnText = 'Bill Requested';
      billBtnIcon = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>`;
    }
    
    var modal = `
      <div id="smdp-bill-modal" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:transparent;z-index:2147483646;display:flex;align-items:center;justify-content:center;pointer-events:auto;">
        <div class="smdp-bill-modal-inner" style="background:#fff;padding:2rem;border-radius:12px;border:4px solid #3498db;max-width:600px;width:90vw;max-height:80vh;overflow-y:auto;box-shadow:0 0 10px 5px rgba(0,0,0,0.5),0 0 20px 10px rgba(0,0,0,0.3),0 0 30px 15px rgba(0,0,0,0.2),0 0 40px 20px rgba(0,0,0,0.1),0 10px 40px rgba(0,0,0,0.4);pointer-events:auto;">
          
          <h2 style="margin:0 0 1.5rem;font-size:1.8rem;color:#333;text-align:center;">Table ${escapeHtml(table)} - Bill</h2>
          
          <div style="margin-bottom:1.5rem;">
            ${itemsHTML}
          </div>
          
          <div style="border-top:2px solid #ddd;padding-top:1rem;">
            <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:1rem;">
              <span>Subtotal:</span>
              <span>$${subtotal.toFixed(2)}</span>
            </div>
            ${discount > 0 ? `
              <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:1rem;color:#e74c3c;">
                <span>Discount:</span>
                <span>-$${discount.toFixed(2)}</span>
              </div>
            ` : ''}
            ${tax > 0 ? `
              <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:1rem;">
                <span>Tax:</span>
                <span>$${tax.toFixed(2)}</span>
              </div>
            ` : ''}
            ${tip > 0 ? `
              <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:1rem;">
                <span>Tip:</span>
                <span>$${tip.toFixed(2)}</span>
              </div>
            ` : ''}
            <div style="display:flex;justify-content:space-between;padding:12px 0;font-size:1.3rem;font-weight:700;border-top:2px solid #333;margin-top:8px;">
              <span>Total:</span>
              <span style="color:#27ae60;">$${total.toFixed(2)}</span>
            </div>
          </div>
          
          <div style="display:flex;gap:10px;margin-top:1.5rem;">
            <button class="${billBtnClass}" data-table="${escapeHtml(table)}" ${billBtnDisabled} style="flex:1;padding:0.75rem 1.5rem;background:#27ae60;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:1rem;font-weight:600;transition:background 0.2s ease;display:flex;align-items:center;justify-content:center;gap:8px;">
              ${billBtnIcon}
              ${billBtnText}
            </button>
            <button id="smdp-bill-close" style="flex:1;padding:0.75rem 1.5rem;background:#3498db;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:1rem;font-weight:600;transition:background 0.2s ease;">
              Close
            </button>
          </div>
        </div>
      </div>
      <style>
        body.smdp-bill-modal-open {
          overflow: hidden !important;
          touch-action: none;
        }
        .smdp-bill-btn:not(.smdp-btn-disabled):hover {
          background: #229954 !important;
        }
        .smdp-bill-btn.smdp-btn-disabled {
          opacity: 0.6;
          cursor: not-allowed;
          animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
          0%, 100% { opacity: 0.6; }
          50% { opacity: 0.4; }
        }
        #smdp-bill-close:hover {
          background: #2980b9 !important;
        }
      </style>
    `;
    
    $('#smdp-bill-modal').remove();
    $('body').append(modal);
  }
  
  function showBillError(message) {
    var modal = `
      <div id="smdp-bill-modal" style="position:fixed;top:0;left:0;width:100vw;height:100vh;background:transparent;z-index:2147483646;display:flex;align-items:center;justify-content:center;pointer-events:auto;">
        <div class="smdp-bill-modal-inner" style="background:#fff;padding:2.5rem;border-radius:12px;border:4px solid #e74c3c;max-width:500px;width:85vw;text-align:center;box-shadow:0 0 10px 5px rgba(0,0,0,0.5),0 0 20px 10px rgba(0,0,0,0.3),0 0 30px 15px rgba(0,0,0,0.2),0 0 40px 20px rgba(0,0,0,0.1),0 10px 40px rgba(0,0,0,0.4);pointer-events:auto;">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="2" style="margin:0 auto 1rem;display:block;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="15" y1="9" x2="9" y2="15"></line>
            <line x1="9" y1="9" x2="15" y2="15"></line>
          </svg>
          <h3 style="margin:0 0 1rem;font-size:1.4rem;color:#333;">No Bill Found</h3>
          <p style="margin:0 0 1.5rem;font-size:1.1rem;color:#666;">${escapeHtml(message)}</p>
          <button id="smdp-bill-close" style="padding:0.75rem 2rem;background:#e74c3c;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:1rem;font-weight:600;width:100%;">
            Close
          </button>
        </div>
      </div>
    `;
    
    $('#smdp-bill-modal').remove();
    $('body').append(modal);
  }
  
  function formatModifiers(modifiers) {
    if (!modifiers || !modifiers.length) return '';
    
    var modsHTML = '<div style="font-size:0.9rem;color:#666;margin-top:4px;">';
    modifiers.forEach(function(mod) {
      modsHTML += '• ' + escapeHtml(mod.name) + '<br>';
    });
    modsHTML += '</div>';
    
    return modsHTML;
  }
  
  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
  
  // Close modal
  $(document).on('click', '#smdp-bill-close, #smdp-bill-modal', function(e) {
    if (e.target.id === 'smdp-bill-close' || e.target.id === 'smdp-bill-modal') {
      e.preventDefault();
      e.stopPropagation();
      $('#smdp-bill-modal').remove();
      $('body').removeClass('smdp-bill-modal-open');
    }
  });
  
  // Prevent clicks inside modal from closing
  $(document).on('click', '.smdp-bill-modal-inner', function(e) {
    e.stopPropagation();
  });
});