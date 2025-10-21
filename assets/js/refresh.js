// Check if jQuery is loaded
if (typeof jQuery === 'undefined') {
  console.error('[SMDP Refresh] ERROR: jQuery not loaded. Script cannot initialize.');
} else {
  (function($) {
    // Check if smdpRefresh is available
    if (typeof smdpRefresh === 'undefined') {
      console.error('[SMDP Refresh] ERROR: smdpRefresh not defined. Check wp_localize_script() in PHP.');
      return;
    }

  var isPWA = false;

  function log(msg, data) {
    var timestamp = new Date().toISOString();
    var logMsg = '[SMDP Refresh ' + timestamp + '] ' + msg;

    if (data !== undefined) {
      console.log(logMsg, data);
    } else {
      console.log(logMsg);
    }

    // Store in localStorage for debugging (with error handling)
    try {
      var logs = JSON.parse(localStorage.getItem('smdp_debug_logs') || '[]');
      logs.push({ time: timestamp, msg: msg, data: data });
      if (logs.length > 100) logs.shift();
      localStorage.setItem('smdp_debug_logs', JSON.stringify(logs));
    } catch (e) {
      // Corrupted logs - reset them
      localStorage.removeItem('smdp_debug_logs');
      console.warn('[SMDP Refresh] Debug logs corrupted, resetting. Error:', e.message);
    }
  }

  function detectPWA() {
    isPWA = window.matchMedia('(display-mode: standalone)').matches ||
            window.navigator.standalone === true ||
            document.referrer.includes('android-app://');

    log('PWA detected: ' + isPWA);
  }

  function registerServiceWorker() {
    if (!isPWA || !('serviceWorker' in navigator)) return;

    // Use dynamic plugin URL from PHP (or fallback to hard-coded path)
    var swPath = smdpRefresh.pluginUrl
      ? smdpRefresh.pluginUrl + 'service-worker.js'
      : '/wp-content/plugins/square-menu-display-premium-deluxe-pro/service-worker.js';

    navigator.serviceWorker.register(swPath)
      .then(function(registration) {
        log('Service Worker registered', registration.scope);
      })
      .catch(function(err) {
        log('Service Worker registration failed', err);
      });
  }

  // Check cache version and force reload if changed
  function checkCacheVersion(serverVersion, serverDebugMode) {
    // Use provided versions or fall back to initial values from page load
    if (serverVersion === undefined) {
      serverVersion = smdpRefresh.cacheVersion ? parseInt(smdpRefresh.cacheVersion) : 1;
    }
    if (serverDebugMode === undefined) {
      serverDebugMode = smdpRefresh.debugMode ? parseInt(smdpRefresh.debugMode) : 0;
    }

    // Get stored version from localStorage
    var storedVersion = parseInt(localStorage.getItem('smdp_cache_version') || '0');
    var storedDebugMode = parseInt(localStorage.getItem('smdp_debug_mode') || '0');

    log('Cache Version Check', {
      server: serverVersion,
      stored: storedVersion,
      serverDebug: serverDebugMode,
      storedDebug: storedDebugMode
    });

    // First time loading? Just store the versions
    if (storedVersion === 0) {
      log('First load - storing initial versions');
      localStorage.setItem('smdp_cache_version', serverVersion.toString());
      localStorage.setItem('smdp_debug_mode', serverDebugMode.toString());
      return false;
    }

    // Check if version or debug mode changed
    var versionChanged = (serverVersion !== storedVersion);
    var debugModeChanged = (storedDebugMode !== serverDebugMode);

    if (versionChanged || debugModeChanged) {
      var reason = versionChanged ?
        'Cache version changed (v' + storedVersion + ' → v' + serverVersion + ')' :
        'Debug mode ' + (serverDebugMode ? 'enabled' : 'disabled');

      log('🔄 ' + reason + ' - Force clearing cache and reloading');

      forceCacheClearAndReload(serverVersion, serverDebugMode, reason);
      return true; // Prevents further script execution
    }

    // No change - just ensure stored versions are current
    localStorage.setItem('smdp_cache_version', serverVersion.toString());
    localStorage.setItem('smdp_debug_mode', serverDebugMode.toString());

    return false;
  }

  // Fetch fresh cache version from server
  function fetchCacheVersionFromServer(callback) {
    log('Fetching fresh cache version from server...');

    $.ajax({
      url: smdpRefresh.ajaxurl,
      type: 'POST',
      timeout: 10000, // 10 second timeout
      data: {
        action: 'smdp_check_version',
        nonce: smdpRefresh.nonce
      },
      success: function(response) {
        if (response.success) {
          var serverVersion = parseInt(response.data.cache_version);
          var serverDebugMode = parseInt(response.data.debug_mode);

          log('Received fresh cache version from server', {
            cache_version: serverVersion,
            debug_mode: serverDebugMode
          });

          callback(serverVersion, serverDebugMode);
        } else {
          log('❌ Failed to get cache version from server', response);
          // Fall back to initial values
          callback(undefined, undefined);
        }
      },
      error: function(xhr, status, error) {
        log('❌ Error fetching cache version', {status: status, error: error});
        // Fall back to initial values
        callback(undefined, undefined);
      }
    });
  }

  // Aggressive cache clear and reload
  function forceCacheClearAndReload(newVersion, newDebugMode, reason) {
    log('Starting aggressive cache clear...', reason);

    // Update stored versions first
    localStorage.setItem('smdp_cache_version', newVersion.toString());
    localStorage.setItem('smdp_debug_mode', newDebugMode.toString());

    // Guard against missing body
    if (!document.body) {
      log('⚠️ Document body not ready, reloading immediately');
      performHardReload();
      return;
    }

    // Show user-friendly message
    var message = document.createElement('div');
    message.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:40px;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.3);z-index:2147483647;text-align:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:400px;';
    message.innerHTML = `
      <h3 style="margin:0 0 15px;color:#333;font-size:18px;">Updating Menu...</h3>
      <p style="margin:0 0 20px;color:#666;font-size:14px;">${reason}</p>
      <div style="background:#f0f0f0;border-radius:8px;height:8px;overflow:hidden;">
        <div id="smdp-progress-bar" style="background:#f39c12;height:100%;width:0%;transition:width 0.3s;"></div>
      </div>
      <p id="smdp-progress-text" style="margin:15px 0 0;color:#999;font-size:12px;">Starting...</p>
    `;
    document.body.appendChild(message);

    var progressBar = document.getElementById('smdp-progress-bar');
    var progressText = document.getElementById('smdp-progress-text');

    function updateProgress(percent, text) {
      if (progressBar) progressBar.style.width = percent + '%';
      if (progressText) progressText.textContent = text;
    }

    // Clear all caches
    updateProgress(10, 'Clearing browser cache...');

    if ('caches' in window) {
      caches.keys()
        .then(function(names) {
          updateProgress(30, 'Removing cached files...');
          return Promise.all(
            names.map(function(name) {
              log('Deleting cache: ' + name);
              return caches.delete(name);
            })
          );
        })
        .then(function() {
          updateProgress(60, 'Clearing local storage...');

          // Clear localStorage (preserve table number)
          var tableNum = localStorage.getItem('smdp_table_number');
          localStorage.clear();
          if (tableNum) {
            localStorage.setItem('smdp_table_number', tableNum);
          }
          localStorage.setItem('smdp_cache_version', newVersion.toString());
          localStorage.setItem('smdp_debug_mode', newDebugMode.toString());

          // Clear sessionStorage
          sessionStorage.clear();

          updateProgress(80, 'Unregistering service workers...');

          if ('serviceWorker' in navigator) {
            return navigator.serviceWorker.getRegistrations();
          }
          return [];
        })
        .then(function(registrations) {
          return Promise.all(
            registrations.map(function(registration) {
              log('Unregistering service worker');
              return registration.unregister();
            })
          );
        })
        .then(function() {
          updateProgress(90, 'Preparing reload...');
          performHardReload();
        })
        .catch(function(err) {
          log('Cache clear error', err);
          updateProgress(90, 'Preparing reload...');
          performHardReload();
        });
    } else {
      log('No cache API, proceeding to reload');
      updateProgress(90, 'Preparing reload...');
      performHardReload();
    }

    function performHardReload() {
      log('Performing hard reload with cache bust');
      updateProgress(100, 'Reloading...');

      setTimeout(function() {
        // Remove message if it exists
        if (message && message.parentNode) {
          message.remove();
        }

        // Modern cache-busting reload (single method, no race condition)
        var url = window.location.href.split('?')[0].split('#')[0];
        window.location.href = url + '?_cb=' + Date.now();
      }, 1000);
    }
  }

  // Full refresh - reload all menu containers sequentially to avoid rate limiting
  function refreshAllMenus() {
    log('🔄 Refreshing all menu containers...');

    var $containers = $('.smdp-menu-container');

    if ($containers.length === 0) {
      log('⚠️ No .smdp-menu-container elements found on page');
      return;
    }

    // Check if we're inside a menu app
    var $menuApp = $('.smdp-menu-app-fe');
    if ($menuApp.length) {
      // Inside menu app - only refresh the visible section
      var $visibleSection = $menuApp.find('.smdp-app-section[style*="display:block"], .smdp-app-section[style*="display: block"]');
      if ($visibleSection.length === 0) {
        // Fallback: find the section without display:none
        $visibleSection = $menuApp.find('.smdp-app-section').filter(function() {
          return $(this).css('display') !== 'none';
        });
      }

      if ($visibleSection.length) {
        $containers = $visibleSection.find('.smdp-menu-container');
        log('Menu app detected - only refreshing visible category');
      }
    }

    log('Found ' + $containers.length + ' menu container(s)');

    // Convert to array for sequential processing
    var containers = $containers.toArray();
    var delay = 150; // 150ms delay between requests to avoid rate limiting

    function refreshNext(index) {
      if (index >= containers.length) {
        log('✅ All menu containers refreshed');
        return;
      }

      var $container = $(containers[index]);
      var menuId = $container.data('menu-id');

      if (!menuId) {
        log('⚠️ Container has no menu-id attribute, skipping');
        // Continue to next container
        setTimeout(function() {
          refreshNext(index + 1);
        }, delay);
        return;
      }

      log('Refreshing menu container ' + (index + 1) + '/' + containers.length + ': ' + menuId);

      $.ajax({
        url: smdpRefresh.ajaxurl,
        type: 'POST',
        timeout: 15000, // 15 second timeout for menu refresh
        data: {
          action: 'smdp_refresh_menu',
          nonce: smdpRefresh.nonce,
          menu_id: menuId
        },
        success: function(response) {
          if (response.success) {
            log('✅ Menu refreshed successfully: ' + menuId);
            $container.html(response.data);
          } else {
            log('❌ Menu refresh failed: ' + menuId, response);
          }
          // Continue to next container
          setTimeout(function() {
            refreshNext(index + 1);
          }, delay);
        },
        error: function(xhr, status, error) {
          log('❌ AJAX error refreshing menu: ' + menuId, {status: status, error: error, statusCode: xhr.status});
          // Continue to next container even on error
          setTimeout(function() {
            refreshNext(index + 1);
          }, delay);
        }
      });
    }

    // Start refreshing from index 0
    refreshNext(0);
  }

  // Refresh a single menu container
  function refreshSingleMenu($container, silent) {
    var menuId = $container.data('menu-id');

    if (!menuId) {
      log('⚠️ Cannot refresh - container has no menu-id attribute');
      return;
    }

    log('🔄 Refreshing menu container: ' + menuId);

    // Add subtle opacity transition if not silent
    if (!silent) {
      $container.css('opacity', '0.6');
    }

    $.ajax({
      url: smdpRefresh.ajaxurl,
      type: 'POST',
      timeout: 15000,
      data: {
        action: 'smdp_refresh_menu',
        nonce: smdpRefresh.nonce,
        menu_id: menuId
      },
      success: function(response) {
        if (response.success) {
          log('✅ Menu refreshed successfully: ' + menuId);
          $container.html(response.data);

          // Restore opacity with smooth transition
          if (!silent) {
            $container.css('transition', 'opacity 0.2s ease');
            setTimeout(function() {
              $container.css('opacity', '1');
            }, 10);
          }
        } else {
          log('❌ Menu refresh failed: ' + menuId, response);
          if (!silent) {
            $container.css('opacity', '1');
          }
        }
      },
      error: function(xhr, status, error) {
        log('❌ AJAX error refreshing menu: ' + menuId, {status: status, error: error, statusCode: xhr.status});
        if (!silent) {
          $container.css('opacity', '1');
        }
      }
    });
  }

  // Initialize on page load
  $(document).ready(function() {
    log('🚀 Refresh script initializing...');

    detectPWA();
    registerServiceWorker();

    // Check cache version immediately on load
    if (checkCacheVersion()) {
      log('⚠️ Cache version check triggered reload, stopping initialization');
      return;
    }

    // Refresh visible menu container(s) on page load
    setTimeout(function() {
      log('📋 Performing initial menu refresh for visible containers...');

      // Check if we're in a menu app
      var $menuApp = $('.smdp-menu-app-fe');
      if ($menuApp.length) {
        // Menu app - refresh only the visible section
        var $visibleSection = $menuApp.find('.smdp-app-section').filter(function() {
          return $(this).css('display') !== 'none';
        });
        if ($visibleSection.length) {
          var $container = $visibleSection.find('.smdp-menu-container');
          if ($container.length) {
            refreshSingleMenu($container, true); // Silent on initial load
          }
        }
      } else {
        // Standalone shortcode(s) - refresh all visible containers
        $('.smdp-menu-container').each(function() {
          refreshSingleMenu($(this), true); // Silent on initial load
        });
      }
    }, 2000);

    log('✅ Initialization complete - will refresh on category switch and promo dismiss');
  });

  // Expose refresh functions globally
  window.smdpRefreshMenu = refreshSingleMenu; // For category switching

  window.smdpRefreshOnPromoDismiss = function() {
    log('🎯 Promo dismissed - fetching fresh cache version from server...');

    // Fetch fresh cache version from server, then check and refresh
    fetchCacheVersionFromServer(function(serverVersion, serverDebugMode) {
      // Check cache version with fresh server values
      if (checkCacheVersion(serverVersion, serverDebugMode)) {
        log('⚠️ Cache version changed, page will reload');
        return;
      }

      // No version change - refresh visible menu
      var $menuApp = $('.smdp-menu-app-fe');
      if ($menuApp.length) {
        var $visibleSection = $menuApp.find('.smdp-app-section').filter(function() {
          return $(this).css('display') !== 'none';
        });
        if ($visibleSection.length) {
          var $container = $visibleSection.find('.smdp-menu-container');
          if ($container.length) {
            refreshSingleMenu($container);
          }
        }
      } else {
        $('.smdp-menu-container').each(function() {
          refreshSingleMenu($(this));
        });
      }
    });
  };

  // Debug interface
  window.smdpRefreshDebug = {
    refresh: function() {
      log('🔧 Manual refresh requested');
      refreshAllMenus();
    },
    checkVersion: function() {
      log('🔧 Manual version check requested (using initial page load values)');
      return checkCacheVersion();
    },
    fetchAndCheckVersion: function() {
      log('🔧 Manual fetch and check version requested');
      fetchCacheVersionFromServer(function(serverVersion, serverDebugMode) {
        checkCacheVersion(serverVersion, serverDebugMode);
      });
    },
    forceClear: function() {
      log('🔧 Manual force clear requested');
      var version = smdpRefresh.cacheVersion || 1;
      var debugMode = smdpRefresh.debugMode || 0;
      forceCacheClearAndReload(version, debugMode, 'Manual trigger via console');
    },
    getLogs: function() {
      try {
        return JSON.parse(localStorage.getItem('smdp_debug_logs') || '[]');
      } catch (e) {
        console.warn('[SMDP Refresh] Debug logs corrupted, returning empty array');
        return [];
      }
    },
    clearLogs: function() {
      localStorage.removeItem('smdp_debug_logs');
      log('Logs cleared');
    },
    status: function() {
      console.log('=== SMDP Refresh Status ===');
      console.log('PWA Mode:', isPWA);
      console.log('Initial Cache Version (from page load):', smdpRefresh.cacheVersion);
      console.log('Stored Cache Version (localStorage):', localStorage.getItem('smdp_cache_version'));
      console.log('Initial Debug Mode (from page load):', smdpRefresh.debugMode);
      console.log('Stored Debug Mode (localStorage):', localStorage.getItem('smdp_debug_mode'));
      console.log('Containers on page:', $('.smdp-menu-container').length);
      console.log('Note: Use fetchAndCheckVersion() to get fresh server values');
      console.log('=========================');
    }
  };

  })(jQuery);
}
