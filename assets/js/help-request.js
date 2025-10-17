jQuery(document).ready(function($) {
  // Append modal HTML to body (if not already present)
  if ($('#smdp-modal-overlay').length === 0) {
    const modalHtml = `
      <style>
        /* Modal container with limited gradient like item detail modal */
        #smdp-modal-overlay {
          display: none;
          position: fixed !important;
          top: 0 !important;
          left: 0 !important;
          right: 0 !important;
          bottom: 0 !important;
          width: 100vw !important;
          height: 100vh !important;
          background: transparent !important;
          z-index: 2147483646 !important;
          margin: 0 !important;
          padding: 0 !important;
          overflow-y: auto !important;
          -webkit-overflow-scrolling: touch;
          pointer-events: auto !important;
        }
        
        /* Modal content with gradient shadow */
        #smdp-modal {
          position: absolute;
          top: 50%;
          left: 50%;
          transform: translate(-50%, -50%);
          background: #fff;
          padding: 2.5rem;
          border-radius: 12px;
          border: 4px solid #27ae60;
          width: 85vw;
          max-width: 500px;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          box-shadow: 
            0 0 10px 5px rgba(0,0,0,0.5),
            0 0 20px 10px rgba(0,0,0,0.3),
            0 0 30px 15px rgba(0,0,0,0.2),
            0 0 40px 20px rgba(0,0,0,0.1),
            0 10px 40px rgba(0,0,0,0.4);
          pointer-events: auto;
        }
        
        #smdp-modal-message {
          margin: 0 0 1.5rem;
          font-size: 1.5rem;
          color: #333;
          text-align: center;
          line-height: 1.4;
          font-weight: 600;
        }
        
        #smdp-modal-close {
          padding: 0.75rem 2rem;
          background: #27ae60;
          color: #fff;
          border: none;
          border-radius: 8px;
          cursor: pointer;
          font-size: 1rem;
          font-weight: 600;
          width: 100%;
          transition: background 0.2s ease;
        }
        
        #smdp-modal-close:hover {
          background: #229954;
        }
        
        /* Prevent body scroll when modal is open */
        body.smdp-help-modal-open {
          overflow: hidden !important;
          touch-action: none;
        }
        
        /* Button disabled state with pulsing animation */
        .smdp-help-btn.smdp-btn-disabled,
        .smdp-bill-btn.smdp-btn-disabled {
          opacity: 0.6;
          cursor: not-allowed;
          animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
          0%, 100% { opacity: 0.6; }
          50% { opacity: 0.4; }
        }
        
        /* Success checkmark animation */
        .smdp-checkmark-circle {
          width: 80px;
          height: 80px;
          border-radius: 50%;
          display: block;
          stroke-width: 3;
          stroke: #27ae60;
          stroke-miterlimit: 10;
          margin: 0 auto 1.5rem;
          box-shadow: inset 0px 0px 0px #27ae60;
          animation: fill 0.4s ease-in-out 0.4s forwards, scale 0.3s ease-in-out 0.9s both;
        }
        
        .smdp-checkmark {
          transform-origin: 50% 50%;
          stroke-dasharray: 48;
          stroke-dashoffset: 48;
          animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }
        
        @keyframes stroke {
          100% { stroke-dashoffset: 0; }
        }
        
        @keyframes scale {
          0%, 100% { transform: none; }
          50% { transform: scale3d(1.1, 1.1, 1); }
        }
        
        @keyframes fill {
          100% { box-shadow: inset 0px 0px 0px 50px #27ae60; }
        }
        
        /* Error X animation */
        .smdp-error-circle {
          width: 80px;
          height: 80px;
          border-radius: 50%;
          display: block;
          stroke-width: 3;
          stroke: #e74c3c;
          stroke-miterlimit: 10;
          margin: 0 auto 1.5rem;
          box-shadow: inset 0px 0px 0px #e74c3c;
          animation: fill-error 0.4s ease-in-out 0.4s forwards, scale 0.3s ease-in-out 0.9s both;
        }
        
        .smdp-error-x {
          transform-origin: 50% 50%;
          stroke-dasharray: 48;
          stroke-dashoffset: 48;
          animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }
        
        @keyframes fill-error {
          100% { box-shadow: inset 0px 0px 0px 50px #e74c3c; }
        }
      </style>
      <div id="smdp-modal-overlay">
        <div id="smdp-modal">
          <div id="smdp-modal-icon"></div>
          <p id="smdp-modal-message"></p>
          <button id="smdp-modal-close">Close</button>
        </div>
      </div>`;
    $('body').append(modalHtml);
  }

  // Utility: disable and schedule re-enable for both Help & Bill buttons
  function disableButton($btn, table) {
    const type = $btn.hasClass('smdp-bill-btn') ? 'bill' : 'help';
    const iconMap = {
      help: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>`,
      bill: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>`
    };
    const textMap = {
      help: '<span>Help Requested</span>',
      bill: '<span>Bill Requested</span>'
    };
    
    $btn.prop('disabled', true)
        .addClass('smdp-btn-disabled')
        .html(iconMap[type] + textMap[type]);

    const key = 'smdp_' + type + '_' + table;
    const now = Date.now();
    localStorage.setItem(key, now);

    const expire = now + 5 * 60 * 1000;
    setTimeout(function() {
      enableButton($btn, table);
    }, expire - now);
  }
  
  // Global function to disable all bill buttons (called from view-bill.js)
  window.smdpDisableAllBillButtons = function(table) {
    $('.smdp-bill-btn[data-table="' + table + '"]').each(function() {
      disableButton($(this), table);
    });
  };

  function enableButton($btn, table) {
    const type = $btn.hasClass('smdp-bill-btn') ? 'bill' : 'help';
    const iconMap = {
      help: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;">
              <circle cx="12" cy="12" r="10"></circle>
              <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>`,
      bill: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px;">
              <rect x="2" y="5" width="20" height="14" rx="2"></rect>
              <line x1="2" y1="10" x2="22" y2="10"></line>
            </svg>`
    };
    const labelMap = {
      help: '<span>Request Help</span>',
      bill: '<span>Request Bill</span>'
    };
    
    $btn.prop('disabled', false)
        .removeClass('smdp-btn-disabled')
        .html(iconMap[type] + labelMap[type]);

    localStorage.removeItem('smdp_' + type + '_' + table);
  }

  // On page load, apply cooldown state for both button types
  $('.smdp-help-btn, .smdp-bill-btn').each(function() {
    const $btn = $(this);
    const table = $btn.data('table');
    const type = $btn.hasClass('smdp-bill-btn') ? 'bill' : 'help';
    const key = 'smdp_' + type + '_' + table;
    const ts = parseInt(localStorage.getItem(key), 10);
    if (ts && Date.now() - ts < 5 * 60 * 1000) {
      disableButton($btn, table);
    }
  });

  // Handle click for both Help & Bill buttons
  $(document).on('click', '.smdp-help-btn, .smdp-bill-btn', function() {
    const $btn = $(this);
    
    // Check if button is already disabled
    if ($btn.prop('disabled')) {
      return;
    }
    
    const table = $btn.data('table');
    const type = $btn.hasClass('smdp-bill-btn') ? 'bill' : 'help';
    const dataObj = (type === 'help') ? smdpHelp : smdpBill;
    const action = (type === 'help') ? 'smdp_request_help' : 'smdp_request_bill';

    $.post(
      dataObj.ajax_url,
      {
        action:   action,
        security: dataObj.nonce,
        table:    table
      }
    )
    .done(function(resp) {
      const msgMap = {
        help: 'Help is on the way for Table ' + table + '!',
        bill: 'Your bill is on the way for Table ' + table + '!'
      };
      
      // Success checkmark SVG
      const successIcon = `
        <svg class="smdp-checkmark-circle" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
          <circle class="smdp-checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
          <path class="smdp-checkmark" fill="none" stroke="white" stroke-width="3" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
        </svg>
      `;
      
      // Update modal styling based on type
      if (type === 'bill') {
        $('#smdp-modal').css('border-color', '#3498db');
        $('#smdp-modal-close').css('background', '#3498db');
      } else {
        $('#smdp-modal').css('border-color', '#27ae60');
        $('#smdp-modal-close').css('background', '#27ae60');
      }
      
      $('#smdp-modal-icon').html(successIcon);
      $('#smdp-modal-message').text(msgMap[type]);
      $('body').addClass('smdp-help-modal-open');
      $('#smdp-modal-overlay').fadeIn(300);
      
      // Disable ALL buttons of this type for this table
      $('.smdp-' + type + '-btn[data-table="' + table + '"]').each(function() {
        disableButton($(this), table);
      });
    })
    .fail(function(jqXHR) {
      let msg = 'Error: ';
      try {
        const json = JSON.parse(jqXHR.responseText);
        msg += json.data || json.message || 'Request failed';
      } catch (e) {
        msg += 'Request failed';
      }
      
      // Error X SVG
      const errorIcon = `
        <svg class="smdp-error-circle" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
          <circle class="smdp-error-circle" cx="26" cy="26" r="25" fill="none"/>
          <path class="smdp-error-x" fill="none" stroke="white" stroke-width="3" d="M16 16 l20 20 M36 16 l-20 20"/>
        </svg>
      `;
      
      $('#smdp-modal').css('border-color', '#e74c3c');
      $('#smdp-modal-close').css('background', '#e74c3c');
      $('#smdp-modal-icon').html(errorIcon);
      $('#smdp-modal-message').text(msg);
      $('body').addClass('smdp-help-modal-open');
      $('#smdp-modal-overlay').fadeIn(300);
    });
  });

  // Close modal when clicking overlay, close button, or tapping outside
  $(document).on('click touchend', '#smdp-modal-close, #smdp-modal-overlay', function(e) {
    if (e.target.id === 'smdp-modal-overlay' || e.target.id === 'smdp-modal-close') {
      e.preventDefault();
      e.stopPropagation();
      $('#smdp-modal-overlay').fadeOut(300, function() {
        $('body').removeClass('smdp-help-modal-open');
      });
    }
  });

  // Prevent clicks inside modal from closing it
  $(document).on('click touchend', '#smdp-modal', function(e) {
    e.stopPropagation();
  });
});