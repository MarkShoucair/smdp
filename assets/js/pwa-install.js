(function() {
  'use strict';

  let deferredPrompt = null;
  let installBanner = null;

  /**
   * Initialize PWA install prompt
   */
  function init() {
    // Only run on menu app pages (check for .smdp-menu-app-fe element)
    if (!document.querySelector('.smdp-menu-app-fe')) {
      return;
    }

    // Check if already running as PWA
    if (isPWA()) {
      return;
    }

    // Listen for beforeinstallprompt event
    window.addEventListener('beforeinstallprompt', function(e) {

      // Prevent the mini-infobar from appearing on mobile
      e.preventDefault();

      // Stash the event so it can be triggered later
      deferredPrompt = e;

      // Show custom install prompt only if table number is set
      checkAndShowInstallPrompt();
    });

    // Listen for successful install
    window.addEventListener('appinstalled', function() {
      deferredPrompt = null;
      hideInstallBanner();
      showInstallSuccess();
    });

    // Check if we should show prompt on page load
    // (in case beforeinstallprompt already fired)
    if (deferredPrompt) {
      checkAndShowInstallPrompt();
    }
  }

  /**
   * Check if running as installed PWA
   */
  function isPWA() {
    // Check display mode
    if (window.matchMedia('(display-mode: standalone)').matches) {
      return true;
    }

    // Check iOS standalone
    if (window.navigator.standalone === true) {
      return true;
    }

    // Check if launched from Android app
    if (document.referrer.includes('android-app://')) {
      return true;
    }

    return false;
  }

  /**
   * Check if table is set and show install prompt
   */
  function checkAndShowInstallPrompt() {
    const tableNum = localStorage.getItem('smdp_table_number');

    if (!tableNum) {
      // Listen for table number being set
      document.addEventListener('smdp-table-set', function() {
        showInstallBanner();
      });
      return;
    }

    // Table is set, show install prompt
    showInstallBanner();
  }

  /**
   * Show custom install banner
   * DISABLED: Install prompt is too intrusive
   */
  function showInstallBanner() {
    // Install prompt disabled - users can still install via browser menu
    return;

    if (!deferredPrompt) {
      return;
    }

    if (installBanner) {
      return;
    }

    const tableNum = localStorage.getItem('smdp_table_number') || 'this tablet';

    // Create install banner
    installBanner = document.createElement('div');
    installBanner.id = 'smdp-pwa-install-banner';
    installBanner.style.cssText = `
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #fff;
      padding: 1rem 1.5rem;
      border-radius: 12px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.3);
      z-index: 2147483646;
      display: flex;
      align-items: center;
      gap: 1rem;
      max-width: 90vw;
      animation: slideDown 0.4s ease-out;
    `;

    installBanner.innerHTML = `
      <style>
        @keyframes slideDown {
          from {
            transform: translateX(-50%) translateY(-100px);
            opacity: 0;
          }
          to {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
          }
        }
        @keyframes slideUp {
          from {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
          }
          to {
            transform: translateX(-50%) translateY(-100px);
            opacity: 0;
          }
        }
        #smdp-pwa-install-banner.hiding {
          animation: slideUp 0.3s ease-in forwards;
        }
      </style>
      <div style="flex: 1;">
        <div style="font-weight: 700; font-size: 1.1rem; margin-bottom: 0.25rem;">
          📱 Install Menu App
        </div>
        <div style="font-size: 0.9rem; opacity: 0.9;">
          Quick access for Table ${tableNum}
        </div>
      </div>
      <button id="smdp-pwa-install-btn" style="
        background: #fff;
        color: #667eea;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 700;
        font-size: 1rem;
        cursor: pointer;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        transition: all 0.2s ease;
        white-space: nowrap;
      " onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
        Install Now
      </button>
      <button id="smdp-pwa-install-dismiss" style="
        background: transparent;
        color: #fff;
        border: none;
        padding: 0.5rem;
        cursor: pointer;
        font-size: 1.5rem;
        line-height: 1;
        opacity: 0.7;
        transition: opacity 0.2s ease;
      " onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
        ×
      </button>
    `;

    document.body.appendChild(installBanner);

    // Handle install button click
    const installBtn = document.getElementById('smdp-pwa-install-btn');
    installBtn.addEventListener('click', handleInstallClick);

    // Handle dismiss button click
    const dismissBtn = document.getElementById('smdp-pwa-install-dismiss');
    dismissBtn.addEventListener('click', function() {
      hideInstallBanner();
      // Remember dismissal for 7 days
      const dismissedUntil = Date.now() + (7 * 24 * 60 * 60 * 1000);
      localStorage.setItem('smdp_pwa_install_dismissed', dismissedUntil);
    });

    // Check if previously dismissed
    const dismissedUntil = localStorage.getItem('smdp_pwa_install_dismissed');
    if (dismissedUntil && Date.now() < parseInt(dismissedUntil)) {
      hideInstallBanner();
      return;
    }
  }

  /**
   * Handle install button click
   */
  async function handleInstallClick() {
    if (!deferredPrompt) {
      return;
    }

    // Show the install prompt
    deferredPrompt.prompt();

    // Wait for the user to respond to the prompt
    const { outcome } = await deferredPrompt.userChoice;

    if (outcome === 'accepted') {
      hideInstallBanner();
    }
    // Don't auto-hide if dismissed, let user dismiss manually if they want

    // We can't use the prompt again
    deferredPrompt = null;
  }

  /**
   * Hide install banner
   */
  function hideInstallBanner() {
    if (!installBanner) return;

    installBanner.classList.add('hiding');
    setTimeout(function() {
      if (installBanner && installBanner.parentNode) {
        installBanner.remove();
      }
      installBanner = null;
    }, 300);
  }

  /**
   * Show install success message
   */
  function showInstallSuccess() {
    const tableNum = localStorage.getItem('smdp_table_number') || 'this tablet';

    const successMsg = document.createElement('div');
    successMsg.style.cssText = `
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: #fff;
      color: #333;
      padding: 2rem 2.5rem;
      border-radius: 16px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.3);
      z-index: 2147483647;
      text-align: center;
      animation: fadeIn 0.3s ease-out;
      max-width: 90vw;
    `;

    successMsg.innerHTML = `
      <style>
        @keyframes fadeIn {
          from { opacity: 0; transform: translate(-50%, -50%) scale(0.9); }
          to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
      </style>
      <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
      <div style="font-weight: 700; font-size: 1.3rem; margin-bottom: 0.5rem; color: #27ae60;">
        Successfully Installed!
      </div>
      <div style="font-size: 1rem; color: #666;">
        Menu app for Table ${tableNum} is now on your home screen
      </div>
    `;

    document.body.appendChild(successMsg);

    // Auto-remove after 3 seconds
    setTimeout(function() {
      successMsg.style.animation = 'fadeIn 0.3s ease-in reverse';
      setTimeout(function() {
        if (successMsg.parentNode) {
          successMsg.remove();
        }
      }, 300);
    }, 3000);
  }

  /**
   * Public API
   */
  window.smdpPWAInstall = {
    show: function() {
      showInstallBanner();
    },
    hide: function() {
      hideInstallBanner();
    },
    isAvailable: function() {
      return !!deferredPrompt;
    },
    isPWA: isPWA
  };

  /**
   * Diagnostic function to check PWA requirements
   * Only logs when explicitly called from debug interface
   */
  function diagnose() {
    const manifestLink = document.querySelector('link[rel="manifest"]');
    const diagnosticInfo = {
      https: window.location.protocol === 'https:',
      serviceWorker: 'serviceWorker' in navigator,
      manifest: !!document.querySelector('link[rel="manifest"]'),
      manifestUrl: manifestLink ? manifestLink.href : null,
      tableNumber: localStorage.getItem('smdp_table_number') || 'NOT SET',
      isPWA: isPWA(),
      installPromptAvailable: !!deferredPrompt
    };
    console.log('=== SMDP PWA Diagnostic ===');
    console.log(diagnosticInfo);
    console.log('===========================');
    return diagnosticInfo;
  }

  // Expose diagnostic
  window.smdpPWAInstall.diagnose = diagnose;

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      init();
      // Diagnostics can be run manually via window.smdpPWAInstall.diagnose()
    });
  } else {
    init();
    // Diagnostics can be run manually via window.smdpPWAInstall.diagnose()
  }
})();
