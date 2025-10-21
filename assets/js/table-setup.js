(function(){
  'use strict';
  
  var currentTable = null;
  var tapCount = 0;
  var tapTimer = null;
  
  // Initialize on page load
  function init() {
    // Only initialize if menu app is present and NOT a category filter
    var menuApp = document.querySelector('.smdp-menu-app-fe');
    if (!menuApp) {
      console.log('Menu app not found - table setup skipped');
      return;
    }

    // Check if this is a category filter page (should not show table selector)
    var isCategoryFilter = menuApp.getAttribute('data-category-filter') === '1';
    if (isCategoryFilter) {
      console.log('Category filter detected - table setup skipped');
      return;
    }

    // Check if table selector is disabled
    var buttonSettings = window.smdpButtonSettings || { enableHelp: true, enableBill: true, enableViewBill: true, enableTableBadge: true, enableTableSelector: true };
    if (!buttonSettings.enableTableSelector) {
      console.log('Table selector disabled - table setup skipped');
      return;
    }

    // Check URL parameter first
    var urlTable = getUrlParameter('table');
    if (urlTable) {
      currentTable = urlTable;
      localStorage.setItem('smdp_table_number', urlTable);
      console.log('Table set from URL:', urlTable);
    } else {
      // Check localStorage
      currentTable = localStorage.getItem('smdp_table_number');
    }

    // If no table set, show setup modal
    if (!currentTable) {
      showSetupModal();
    } else {
      updateTableDisplay();
      initializeButtons();
    }

    // Setup gesture listeners
    setupGestures();
  }
  
  // Get URL parameter
  function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    var results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
  }
  
  // Show setup modal
  function showSetupModal() {
    var modal = document.createElement('div');
    modal.id = 'smdp-table-setup-modal';
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:transparent;z-index:2147483647;display:flex;align-items:center;justify-content:center;pointer-events:auto;';
    
    modal.innerHTML = `
      <style>
        #smdp-table-setup-modal {
          overflow: hidden;
          touch-action: none;
        }
        body.smdp-table-setup-open {
          overflow: hidden !important;
          touch-action: none;
        }
        .smdp-table-setup-inner {
          background: #fff;
          padding: 2.5rem;
          border-radius: 12px;
          border: 4px solid #3498db;
          max-width: 450px;
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
        .smdp-table-setup-inner h2 {
          margin: 0 0 1rem;
          font-size: 1.8rem;
          color: #333;
        }
        .smdp-table-setup-inner p {
          margin: 0 0 1.5rem;
          color: #666;
        }
        .smdp-table-setup-inner input {
          width: 100%;
          padding: 1rem;
          font-size: 2rem;
          text-align: center;
          border: 2px solid #ddd;
          border-radius: 8px;
          margin-bottom: 1.5rem;
          box-sizing: border-box;
        }
        .smdp-table-setup-inner button {
          width: 100%;
          padding: 1rem 2rem;
          background: #3498db;
          color: #fff;
          border: none;
          border-radius: 8px;
          font-size: 1.2rem;
          font-weight: 600;
          cursor: pointer;
          transition: background 0.2s ease;
        }
        .smdp-table-setup-inner button:hover {
          background: #2980b9;
        }
        .smdp-table-setup-inner .setup-hint {
          margin: 1rem 0 0;
          font-size: 0.85rem;
          color: #999;
        }
      </style>
      <div class="smdp-table-setup-inner">
        <h2>Table Setup</h2>
        <p>Enter the table number for this tablet:</p>
        <input type="number" id="smdp-table-input" min="1" max="999" placeholder="Table #" autofocus>
        <button id="smdp-table-submit">Save Table Number</button>
        <p class="setup-hint">Triple-tap the table badge to change this later</p>
      </div>
    `;
    
    document.body.appendChild(modal);
    document.body.classList.add('smdp-table-setup-open');
    
    var input = document.getElementById('smdp-table-input');
    var submit = document.getElementById('smdp-table-submit');
    
    // Focus input
    setTimeout(function() { input.focus(); }, 100);
    
    // Handle submit
    function saveTable() {
      var tableNum = input.value.trim();
      if (tableNum && parseInt(tableNum) > 0) {
        currentTable = tableNum;
        localStorage.setItem('smdp_table_number', tableNum);
        document.body.classList.remove('smdp-table-setup-open');
        modal.remove();
        updateTableDisplay();
        initializeButtons();
        updateManifest(tableNum);
        console.log('Table number saved:', tableNum);
      } else {
        input.style.borderColor = '#e74c3c';
        input.focus();
      }
    }
    
    submit.addEventListener('click', saveTable);
    input.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') saveTable();
    });
    
    // Prevent closing by clicking outside during initial setup
    // Only allow closing if table is already set (changing table)
    if (currentTable) {
      modal.addEventListener('click', function(e) {
        if (e.target.id === 'smdp-table-setup-modal') {
          document.body.classList.remove('smdp-table-setup-open');
          modal.remove();
        }
      });
    }
  }
  
  // Update table display badge (next to buttons instead of header)
  function updateTableDisplay() {
    // Remove existing badge
    var existing = document.getElementById('smdp-table-badge');
    if (existing) existing.remove();
    
    // Badge will be added with buttons
  }
  
  // Initialize Help & Bill buttons with table badge - NEW LAYOUT
  function initializeButtons() {
    if (!currentTable) return;

    // Only show buttons if menu app is present on the page (not in admin/editor)
    var menuApp = document.querySelector('.smdp-menu-app-fe');
    if (!menuApp) {
      console.log('Menu app not found on page - buttons hidden');
      return;
    }

    // Get button enable/disable settings (default to enabled if not set)
    var buttonSettings = window.smdpButtonSettings || { enableHelp: true, enableBill: true, enableViewBill: true, enableTableBadge: true };

    // Check if anything is enabled at all - if not, skip everything
    if (!buttonSettings.enableHelp && !buttonSettings.enableBill && !buttonSettings.enableViewBill && !buttonSettings.enableTableBadge) {
      console.log('All action buttons and table badge disabled - nothing to show');
      return;
    }

    // Remove existing buttons
    var existing = document.getElementById('smdp-action-buttons');
    if (existing) existing.remove();

    // Create button container - now vertical stack with right alignment
    var container = document.createElement('div');
    container.id = 'smdp-action-buttons';
    container.style.cssText = 'position:fixed;bottom:20px;right:20px;display:flex;flex-direction:column;gap:10px;z-index:1000;align-items:flex-end;';

    var buttonsToAnimate = [];

    // Table badge (only if enabled)
    if (buttonSettings.enableTableBadge) {
      var tableBadge = document.createElement('div');
      tableBadge.id = 'smdp-table-badge';
      tableBadge.textContent = 'Table ' + currentTable;
      container.appendChild(tableBadge);
    }

    // View Bill button (only if enabled)
    if (buttonSettings.enableViewBill) {
      var viewBillBtn = document.createElement('button');
      viewBillBtn.className = 'smdp-view-bill-btn';
      viewBillBtn.setAttribute('data-table', currentTable);
      viewBillBtn.innerHTML = `
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px;">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
          <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
        <span>View Bill</span>
      `;
      container.appendChild(viewBillBtn);
      buttonsToAnimate.push(viewBillBtn);
    }

    // Bottom row container for Help & Bill buttons (only if at least one is enabled)
    if (buttonSettings.enableHelp || buttonSettings.enableBill) {
      var buttonRow = document.createElement('div');
      buttonRow.className = 'smdp-action-buttons';

      // Help button (only if enabled)
      if (buttonSettings.enableHelp) {
        var helpBtn = document.createElement('button');
        helpBtn.className = 'smdp-help-btn';
        helpBtn.setAttribute('data-table', currentTable);
        helpBtn.innerHTML = `
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
          <span>Request Help</span>
        `;
        buttonRow.appendChild(helpBtn);
        buttonsToAnimate.push(helpBtn);
      }

      // Bill button (only if enabled)
      if (buttonSettings.enableBill) {
        var billBtn = document.createElement('button');
        billBtn.className = 'smdp-bill-btn';
        billBtn.setAttribute('data-table', currentTable);
        billBtn.innerHTML = `
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;">
            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
            <line x1="2" y1="10" x2="22" y2="10"></line>
          </svg>
          <span>Request Bill</span>
        `;
        buttonRow.appendChild(billBtn);
        buttonsToAnimate.push(billBtn);
      }

      container.appendChild(buttonRow);
    }

    // Add click animations to all buttons
    buttonsToAnimate.forEach(function(btn) {
      btn.addEventListener('click', function() {
        this.style.transform = 'scale(0.95)';
        this.style.boxShadow = '0 2px 5px rgba(0,0,0,0.3)';
        setTimeout(function() {
          btn.style.transform = 'scale(1)';
          btn.style.boxShadow = '0 4px 10px rgba(0,0,0,0.3)';
        }, 150);
      });
    });

    // Add hover effects (for non-touch devices)
    buttonsToAnimate.forEach(function(btn) {
      btn.addEventListener('mouseenter', function() {
        if (!('ontouchstart' in window)) {
          this.style.transform = 'translateY(-2px)';
          this.style.boxShadow = '0 6px 15px rgba(0,0,0,0.4)';
        }
      });

      btn.addEventListener('mouseleave', function() {
        if (!('ontouchstart' in window)) {
          this.style.transform = 'translateY(0)';
          this.style.boxShadow = '0 4px 10px rgba(0,0,0,0.3)';
        }
      });
    });

    document.body.appendChild(container);

    console.log('Action buttons initialized for table:', currentTable, 'Settings:', buttonSettings);
  }
  
  // Setup gesture listeners
  function setupGestures() {
    // Triple-tap on table badge with PIN protection
    document.addEventListener('click', function(e) {
      var badge = document.getElementById('smdp-table-badge');
      if (badge && badge.contains(e.target)) {
        tapCount++;
        
        if (tapTimer) clearTimeout(tapTimer);
        
        if (tapCount === 3) {
          tapCount = 0;
          showPinModal();
        } else {
          tapTimer = setTimeout(function() {
            tapCount = 0;
          }, 500);
        }
      }
    });
  }
  
  // Show PIN entry modal
  function showPinModal() {
    var modal = document.createElement('div');
    modal.id = 'smdp-pin-modal';
    modal.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:transparent;z-index:2147483647;display:flex;align-items:center;justify-content:center;pointer-events:auto;';
    
    modal.innerHTML = `
      <style>
        body.smdp-pin-modal-open {
          overflow: hidden !important;
          touch-action: none;
        }
        .smdp-pin-inner {
          background: #fff;
          padding: 2rem;
          border-radius: 12px;
          border: 4px solid #e74c3c;
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
        .smdp-pin-inner h2 {
          margin: 0 0 1rem;
          font-size: 1.6rem;
          color: #333;
        }
        .smdp-pin-inner p {
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
          background: #3498db;
          border-color: #3498db;
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
        .smdp-pin-cancel {
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
        .smdp-pin-cancel:hover {
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
      </style>
      <div class="smdp-pin-inner">
        <h2>Enter PIN</h2>
        <p>Enter the admin PIN to change table settings:</p>
        
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
        
        <p class="smdp-pin-error" id="smdp-pin-error">Incorrect PIN</p>
        
        <button class="smdp-pin-cancel" id="smdp-pin-cancel">Cancel</button>
      </div>
    `;
    
    document.body.appendChild(modal);
    document.body.classList.add('smdp-pin-modal-open');
    
    var pinValue = '';
    var dots = modal.querySelectorAll('.smdp-pin-dot');
    var keys = modal.querySelectorAll('.smdp-pin-key');
    var cancel = document.getElementById('smdp-pin-cancel');
    var error = document.getElementById('smdp-pin-error');
    
    // Update dot display
    function updateDisplay() {
      dots.forEach(function(dot, index) {
        if (index < pinValue.length) {
          dot.classList.add('filled');
        } else {
          dot.classList.remove('filled');
        }
        dot.classList.remove('error');
      });
    }
    
    // Handle number key press
    keys.forEach(function(key) {
      key.addEventListener('click', function() {
        var num = this.getAttribute('data-num');
        
        if (this.classList.contains('clear')) {
          // Clear button
          pinValue = '';
          error.classList.remove('active');
          updateDisplay();
        } else if (num && pinValue.length < 4) {
          // Number button
          pinValue += num;
          error.classList.remove('active');
          updateDisplay();
          
          // Auto-check when 4 digits entered
          if (pinValue.length === 4) {
            setTimeout(checkPin, 200);
          }
        }
      });
    });
    
    // Check PIN
    function checkPin() {
      if (pinValue === '2439') {
        document.body.classList.remove('smdp-pin-modal-open');
        modal.remove();
        showSetupModal();
      } else {
        // Show error
        dots.forEach(function(dot) {
          dot.classList.add('error');
        });
        error.classList.add('active');
        
        // Clear after animation
        setTimeout(function() {
          pinValue = '';
          updateDisplay();
        }, 500);
      }
    }
    
    // Handle cancel
    function closePin() {
      document.body.classList.remove('smdp-pin-modal-open');
      modal.remove();
    }
    
    cancel.addEventListener('click', closePin);
    
    // Click outside to close
    modal.addEventListener('click', function(e) {
      if (e.target.id === 'smdp-pin-modal') {
        closePin();
      }
    });
  }
  
  // Update manifest with table number
  function updateManifest(tableNum) {
    var manifestLink = document.getElementById('smdp-manifest-link');
    if (manifestLink && tableNum) {
      var baseUrl = manifestLink.href.split('?')[0];
      var params = new URLSearchParams(manifestLink.href.split('?')[1]);
      var pageId = params.get('page_id');
      var newUrl = baseUrl + '?page_id=' + pageId + '&table=' + encodeURIComponent(tableNum);
      manifestLink.href = newUrl;
      console.log('[SMDP] Manifest updated with table:', tableNum);
    }
  }

  // Reset table (for admin use)
  function resetTable() {
    localStorage.removeItem('smdp_table_number');
    currentTable = null;
    var buttons = document.getElementById('smdp-action-buttons');
    if (buttons) buttons.remove();
    showSetupModal();
    console.log('[SMDP] Table number reset');
  }

  // Expose for external use
  window.smdpTable = {
    get: function() { return currentTable; },
    set: function(num) {
      currentTable = num;
      localStorage.setItem('smdp_table_number', num);
      updateTableDisplay();
      initializeButtons();
      updateManifest(num);
    },
    reset: resetTable
  };
  
  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();