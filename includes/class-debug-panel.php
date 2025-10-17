<?php
/**
 * Debug Panel
 *
 * Handles PWA debug mode functionality including frontend debug panel,
 * cache busting, and admin notices.
 *
 * @package Square_Menu_Display
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SMDP_Debug_Panel
 *
 * Manages debug mode functionality for PWA caching and development.
 */
class SMDP_Debug_Panel {

    /**
     * Singleton instance
     *
     * @var SMDP_Debug_Panel
     */
    private static $instance = null;

    /**
     * Flag to track if menu app shortcode was rendered
     *
     * @var bool
     */
    private static $menu_app_rendered = false;

    /**
     * Get singleton instance
     *
     * @return SMDP_Debug_Panel
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set flag that menu app was rendered (called from menu app shortcode)
     */
    public static function set_menu_app_rendered() {
        self::$menu_app_rendered = true;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'wp_footer', array( $this, 'render_debug_panel' ), 999 );
        add_action( 'init', array( $this, 'service_worker_cache_bust' ) );
        add_action( 'admin_notices', array( $this, 'debug_mode_notice' ) );
    }

    /**
     * Render debug panel on frontend
     *
     * Displays an interactive debug panel with console log capture,
     * cache clearing, and sync status checking.
     */
    public function render_debug_panel() {
        $debug_mode = get_option( 'smdp_pwa_debug_mode', 0 );
        if ( ! $debug_mode ) {
            return;
        }

        // Only render on pages where the menu app shortcode was rendered
        if ( ! self::$menu_app_rendered ) {
            return;
        }

        $cache_version  = get_option( 'smdp_cache_version', 1 );
        $last_sync      = get_option( 'smdp_last_sync_timestamp', 0 );
        $last_sync_date = $last_sync ? date( 'Y-m-d H:i:s', $last_sync ) : 'Never';
        ?>
        <!-- Enhanced Debug Panel with Console Log Capture -->
        <div id="smdp-debug-panel" style="position:fixed;bottom:0;left:0;right:0;background:#1e1e1e;color:#fff;padding:15px;font-family:monospace;font-size:12px;z-index:2147483640;border-top:3px solid #f39c12;display:none;box-shadow:0 -4px 20px rgba(0,0,0,0.5);">
            <div style="max-width:1400px;margin:0 auto;">
                <!-- Main Control Bar -->
                <div style="display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:12px;">
                    <div style="flex:1;min-width:250px;">
                        <strong style="color:#f39c12;font-size:14px;">🔧 DEBUG MODE</strong><br>
                        <span style="color:#888;font-size:11px;">Cache: v<?php echo esc_html( $cache_version ); ?> | Sync: <?php echo esc_html( $last_sync_date ); ?></span>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button onclick="smdpDebug.clearCache()" style="padding:8px 12px;background:#e74c3c;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11px;font-weight:600;transition:all 0.2s;" onmouseover="this.style.background='#c0392b'" onmouseout="this.style.background='#e74c3c'">🗑️ Clear Cache</button>
                        <button onclick="smdpDebug.resetTable()" style="padding:8px 12px;background:#e67e22;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11px;font-weight:600;transition:all 0.2s;" onmouseover="this.style.background='#d35400'" onmouseout="this.style.background='#e67e22'">🏷️ Reset Table</button>
                        <button onclick="smdpDebug.reloadFresh()" style="padding:8px 12px;background:#3498db;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11px;font-weight:600;transition:all 0.2s;" onmouseover="this.style.background='#2980b9'" onmouseout="this.style.background='#3498db'">🔄 Hard Reload</button>
                        <button onclick="smdpDebug.checkSync()" style="padding:8px 12px;background:#27ae60;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11px;font-weight:600;transition:all 0.2s;" onmouseover="this.style.background='#229954'" onmouseout="this.style.background='#27ae60'">✅ Check Sync</button>
                        <button onclick="smdpDebug.showLogs()" style="padding:8px 12px;background:#9b59b6;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11px;font-weight:600;transition:all 0.2s;" onmouseover="this.style.background='#8e44ad'" onmouseout="this.style.background='#9b59b6'">📋 Show Logs</button>
                        <button onclick="smdpDebug.togglePanel()" style="padding:8px 12px;background:#555;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11px;font-weight:600;transition:all 0.2s;" onmouseover="this.style.background='#666'" onmouseout="this.style.background='#555'">⬇️ Hide</button>
                    </div>
                </div>

                <!-- Output Area -->
                <div id="smdp-debug-output" style="display:none;padding:12px;background:#2c2c2c;border-radius:6px;border:1px solid #444;"></div>
            </div>
        </div>

        <!-- Floating Toggle Button -->
        <button id="smdp-debug-toggle" onclick="smdpDebug.togglePanel()" style="position:fixed;bottom:10px;right:10px;width:50px;height:50px;background:#f39c12;color:#fff;border:none;border-radius:50%;cursor:pointer;font-size:20px;box-shadow:0 4px 10px rgba(0,0,0,0.3);z-index:2147483641;display:flex;align-items:center;justify-content:center;transition:all 0.3s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
            🔧
        </button>

        <style>
            #smdp-debug-panel.visible { display:block !important; }
            #smdp-debug-toggle.hidden { display:none !important; }

            /* Scrollbar styling for logs */
            #smdp-logs-container::-webkit-scrollbar {
                width: 8px;
            }
            #smdp-logs-container::-webkit-scrollbar-track {
                background: #1a1a1a;
                border-radius: 4px;
            }
            #smdp-logs-container::-webkit-scrollbar-thumb {
                background: #555;
                border-radius: 4px;
            }
            #smdp-logs-container::-webkit-scrollbar-thumb:hover {
                background: #666;
            }

            /* Live indicator pulse animation */
            @keyframes pulse {
                0%, 100% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.5; transform: scale(1.2); }
            }
        </style>

        <script>
        // Enhanced Debug Panel with Full Console Log Capture
        window.smdpDebug = {
            panel: document.getElementById('smdp-debug-panel'),
            toggle: document.getElementById('smdp-debug-toggle'),
            output: document.getElementById('smdp-debug-output'),
            consoleLogs: [],
            maxLogs: 500,
            currentFilter: 'all',
            autoUpdate: false,

            init: function() {
                // Store cache version and sync date globally
                window.smdpCacheVersion = '<?php echo esc_js( $cache_version ); ?>';
                window.smdpLastSync = '<?php echo esc_js( $last_sync_date ); ?>';

                // Check localStorage for panel state
                var visible = localStorage.getItem('smdp_debug_visible');
                if (visible === 'true') {
                    this.panel.classList.add('visible');
                    this.toggle.classList.add('hidden');
                    // Auto-show logs on page load if panel was previously visible
                    this.showLogs();
                }

                // Intercept all console methods
                this.interceptConsole();

                // Log version info
                console.log('%c🔧 SMDP Debug Mode Active', 'background:#f39c12;color:#fff;padding:5px 10px;font-weight:bold;');
                console.log('Cache Version: v<?php echo esc_js( $cache_version ); ?>');
                console.log('Last Sync: <?php echo esc_js( $last_sync_date ); ?>');
                console.log('Available commands: smdpDebug.clearCache(), smdpDebug.reloadFresh(), smdpDebug.checkSync(), smdpDebug.showLogs()');
            },

            // Intercept all console methods to capture logs
            interceptConsole: function() {
                var self = this;
                var methods = ['log', 'warn', 'error', 'info', 'debug'];

                methods.forEach(function(method) {
                    var original = console[method];
                    console[method] = function() {
                        // Store the log entry
                        var args = Array.prototype.slice.call(arguments);
                        var timestamp = new Date();

                        self.consoleLogs.push({
                            type: method,
                            timestamp: timestamp,
                            args: args,
                            formatted: self.formatArgs(args)
                        });

                        // Keep only the last maxLogs entries
                        if (self.consoleLogs.length > self.maxLogs) {
                            self.consoleLogs.shift();
                        }

                        // Auto-update display if logs are currently showing
                        if (self.autoUpdate) {
                            self.refreshDisplay();
                        }

                        // Call the original console method
                        original.apply(console, arguments);
                    };
                });
            },

            // Format console arguments for display
            formatArgs: function(args) {
                return args.map(function(arg) {
                    if (typeof arg === 'object') {
                        try {
                            // Handle special cases
                            if (arg === null) return 'null';
                            if (arg === undefined) return 'undefined';
                            if (arg instanceof Error) return arg.toString();
                            if (arg instanceof HTMLElement) return arg.outerHTML.substring(0, 100) + '...';

                            // Try to stringify with circular reference handling
                            var cache = [];
                            var str = JSON.stringify(arg, function(key, value) {
                                if (typeof value === 'object' && value !== null) {
                                    if (cache.indexOf(value) !== -1) {
                                        return '[Circular]';
                                    }
                                    cache.push(value);
                                }
                                return value;
                            }, 2);
                            return str;
                        } catch (e) {
                            return String(arg);
                        }
                    }
                    return String(arg);
                }).join(' ');
            },

            togglePanel: function() {
                if (this.panel.classList.contains('visible')) {
                    this.hidePanel();
                } else {
                    this.showPanel();
                }
            },

            showPanel: function() {
                this.panel.classList.add('visible');
                this.toggle.classList.add('hidden');
                localStorage.setItem('smdp_debug_visible', 'true');

                // Auto-show logs when panel opens
                if (!this.autoUpdate) {
                    this.showLogs();
                }
            },

            hidePanel: function() {
                this.panel.classList.remove('visible');
                this.toggle.classList.remove('hidden');
                localStorage.setItem('smdp_debug_visible', 'false');
                // Disable auto-update when panel is hidden
                this.autoUpdate = false;
            },

            log: function(message, type) {
                type = type || 'info';
                var colors = {
                    'info': '#3498db',
                    'success': '#27ae60',
                    'error': '#e74c3c',
                    'warning': '#f39c12'
                };
                var color = colors[type] || colors.info;
                var time = new Date().toLocaleTimeString();

                this.output.style.display = 'block';
                this.output.innerHTML = '<div style="color:' + color + ';margin-bottom:5px;">[' + time + '] ' + message + '</div>' + this.output.innerHTML;

                console.log('[SMDP Debug] ' + message);
            },

            // Show all console logs in the debug panel
            showLogs: function() {
                var self = this;
                this.output.style.display = 'block';
                this.output.innerHTML = '';

                // Enable auto-update and set initial filter
                this.autoUpdate = true;
                this.currentFilter = 'all';

                // Add header with controls
                var header = document.createElement('div');
                header.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-bottom:10px;border-bottom:2px solid #444;';
                header.innerHTML = `
                    <div style="display:flex;align-items:center;gap:10px;">
                        <strong style="color:#f39c12;font-size:13px;">📋 Console Logs (<span id="smdp-log-count">${this.consoleLogs.length}</span>/${this.maxLogs})</strong>
                        <span id="smdp-live-indicator" style="display:inline-flex;align-items:center;gap:4px;font-size:10px;color:#27ae60;">
                            <span style="display:inline-block;width:6px;height:6px;background:#27ae60;border-radius:50%;animation:pulse 1.5s ease-in-out infinite;"></span>
                            LIVE
                        </span>
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <button onclick="smdpDebug.filterLogs('all')" id="smdp-filter-all" style="padding:5px 10px;background:#666;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:10px;font-weight:600;transition:all 0.2s;">All</button>
                        <button onclick="smdpDebug.filterLogs('log')" id="smdp-filter-log" style="padding:5px 10px;background:#3498db;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:10px;font-weight:600;transition:all 0.2s;">Log</button>
                        <button onclick="smdpDebug.filterLogs('warn')" id="smdp-filter-warn" style="padding:5px 10px;background:#f39c12;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:10px;font-weight:600;transition:all 0.2s;">Warn</button>
                        <button onclick="smdpDebug.filterLogs('error')" id="smdp-filter-error" style="padding:5px 10px;background:#e74c3c;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:10px;font-weight:600;transition:all 0.2s;">Error</button>
                        <button onclick="smdpDebug.filterLogs('info')" id="smdp-filter-info" style="padding:5px 10px;background:#9b59b6;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:10px;font-weight:600;transition:all 0.2s;">Info</button>
                        <button onclick="smdpDebug.filterLogs('debug')" id="smdp-filter-debug" style="padding:5px 10px;background:#95a5a6;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:10px;font-weight:600;transition:all 0.2s;">Debug</button>
                        <button onclick="smdpDebug.clearLogs()" style="padding:5px 10px;background:#e74c3c;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:10px;font-weight:600;transition:all 0.2s;">🗑️ Clear</button>
                        <button onclick="smdpDebug.exportLogs()" style="padding:5px 10px;background:#16a085;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:10px;font-weight:600;transition:all 0.2s;">💾 Export</button>
                    </div>
                `;
                this.output.appendChild(header);

                // Add logs container
                var logsContainer = document.createElement('div');
                logsContainer.id = 'smdp-logs-container';
                logsContainer.style.cssText = 'max-height:350px;overflow-y:auto;overflow-x:hidden;';
                this.output.appendChild(logsContainer);

                // Display all logs with proper button highlighting
                this.filterLogs('all');
            },

            // Refresh display without rebuilding header
            refreshDisplay: function() {
                // Update the log count
                var countElement = document.getElementById('smdp-log-count');
                if (countElement) {
                    countElement.textContent = this.consoleLogs.length;
                }

                // Re-render logs with current filter
                this.renderLogs(this.currentFilter);
            },

            filterLogs: function(type) {
                this.currentFilter = type;

                // Update button styles to show active filter
                var buttons = {
                    'all': document.getElementById('smdp-filter-all'),
                    'log': document.getElementById('smdp-filter-log'),
                    'warn': document.getElementById('smdp-filter-warn'),
                    'error': document.getElementById('smdp-filter-error'),
                    'info': document.getElementById('smdp-filter-info'),
                    'debug': document.getElementById('smdp-filter-debug')
                };

                // Reset all buttons
                if (buttons.all) buttons.all.style.background = '#555';
                if (buttons.log) buttons.log.style.background = '#3498db';
                if (buttons.warn) buttons.warn.style.background = '#f39c12';
                if (buttons.error) buttons.error.style.background = '#e74c3c';
                if (buttons.info) buttons.info.style.background = '#9b59b6';
                if (buttons.debug) buttons.debug.style.background = '#95a5a6';

                // Highlight active button
                if (buttons[type]) {
                    var activeColors = {
                        'all': '#666',
                        'log': '#2980b9',
                        'warn': '#e67e22',
                        'error': '#c0392b',
                        'info': '#8e44ad',
                        'debug': '#7f8c8d'
                    };
                    buttons[type].style.background = activeColors[type];
                }

                this.renderLogs(type);
            },

            renderLogs: function(filterType) {
                var container = document.getElementById('smdp-logs-container');
                if (!container) return;

                // Save scroll position
                var scrollPos = container.scrollTop;
                var wasAtBottom = (container.scrollHeight - container.scrollTop - container.clientHeight) < 50;

                // If container is new/empty, default to scrolling to bottom
                if (container.scrollHeight === 0 || container.innerHTML === '') {
                    wasAtBottom = true;
                }

                container.innerHTML = '';

                var typeColors = {
                    'log': '#3498db',
                    'warn': '#f39c12',
                    'error': '#e74c3c',
                    'info': '#9b59b6',
                    'debug': '#95a5a6'
                };

                var typeIcons = {
                    'log': '📝',
                    'warn': '⚠️',
                    'error': '❌',
                    'info': 'ℹ️',
                    'debug': '🐛'
                };

                var filteredLogs = filterType === 'all'
                    ? this.consoleLogs
                    : this.consoleLogs.filter(function(log) { return log.type === filterType; });

                if (filteredLogs.length === 0) {
                    container.innerHTML = '<div style="color:#888;text-align:center;padding:30px;font-size:13px;">No logs to display</div>';
                    return;
                }

                // Display logs (most recent first)
                for (var i = filteredLogs.length - 1; i >= 0; i--) {
                    var log = filteredLogs[i];
                    var logDiv = document.createElement('div');
                    logDiv.style.cssText = 'margin-bottom:8px;padding:8px 10px;background:#1a1a1a;border-left:3px solid ' + typeColors[log.type] + ';border-radius:4px;font-size:11px;transition:all 0.2s;cursor:pointer;';
                    logDiv.onmouseover = function() { this.style.background = '#222'; };
                    logDiv.onmouseout = function() { this.style.background = '#1a1a1a'; };

                    var time = log.timestamp.toLocaleTimeString() + '.' + String(log.timestamp.getMilliseconds()).padStart(3, '0');

                    logDiv.innerHTML = `
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                            <span style="font-size:14px;">${typeIcons[log.type]}</span>
                            <span style="color:${typeColors[log.type]};font-weight:bold;text-transform:uppercase;font-size:9px;letter-spacing:0.5px;">${log.type}</span>
                            <span style="color:#666;font-size:9px;">${time}</span>
                        </div>
                        <div style="color:#fff;word-break:break-word;white-space:pre-wrap;font-family:monospace;line-height:1.4;">${this.escapeHtml(log.formatted)}</div>
                    `;

                    container.appendChild(logDiv);
                }

                // Auto-scroll to bottom if user was already at bottom, otherwise maintain position
                if (wasAtBottom) {
                    container.scrollTop = container.scrollHeight;
                } else {
                    container.scrollTop = scrollPos;
                }
            },

            escapeHtml: function(text) {
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            },

            clearLogs: function() {
                if (confirm('Clear all captured console logs?')) {
                    this.consoleLogs = [];
                    if (this.autoUpdate) {
                        this.refreshDisplay();
                    }
                    console.log('Console logs cleared from debug panel');
                }
            },

            exportLogs: function() {
                var data = {
                    exported: new Date().toISOString(),
                    cacheVersion: window.smdpCacheVersion,
                    lastSync: window.smdpLastSync,
                    totalLogs: this.consoleLogs.length,
                    logs: this.consoleLogs.map(function(log) {
                        return {
                            type: log.type,
                            timestamp: log.timestamp.toISOString(),
                            message: log.formatted
                        };
                    })
                };

                var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'smdp-debug-logs-' + Date.now() + '.json';
                a.click();
                URL.revokeObjectURL(url);

                console.log('Logs exported successfully');
            },

            clearCache: function() {
                this.log('Clearing all caches...', 'info');

                // Clear localStorage (preserve table number)
                var tableNum = localStorage.getItem('smdp_table_number');
                localStorage.clear();
                if (tableNum) {
                    localStorage.setItem('smdp_table_number', tableNum);
                }

                // Clear sessionStorage
                sessionStorage.clear();

                // Unregister service worker
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.getRegistrations().then(function(registrations) {
                        for(var registration of registrations) {
                            registration.unregister();
                        }
                    });
                }

                // Clear cache storage
                if ('caches' in window) {
                    caches.keys().then(function(names) {
                        for (var name of names) {
                            caches.delete(name);
                        }
                    });
                }

                this.log('Cache cleared! Reloading...', 'success');
                setTimeout(function() {
                    location.reload(true);
                }, 1000);
            },

            reloadFresh: function() {
                this.log('Performing hard reload...', 'info');
                setTimeout(function() {
                    location.reload(true);
                }, 500);
            },

            checkSync: function() {
                this.log('Checking sync status...', 'info');
                if (typeof smdpRefresh !== 'undefined' && smdpRefresh.status) {
                    smdpRefresh.status();
                } else {
                    console.log('Sync status not available');
                }
            },

            resetTable: function() {
                if (confirm('Reset tablet number? This will prompt for a new table number.')) {
                    this.log('Resetting table number...', 'info');
                    if (typeof window.smdpTable !== 'undefined' && window.smdpTable.reset) {
                        window.smdpTable.reset();
                        this.log('Table number reset successfully!', 'success');
                    } else {
                        console.error('Table reset function not available');
                    }
                }
            }
        };

        // Initialize on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                smdpDebug.init();
            });
        } else {
            smdpDebug.init();
        }
        </script>
        <?php
    }

    /**
     * Add cache-busting headers in debug mode
     *
     * Prevents service worker from caching in debug mode.
     */
    public function service_worker_cache_bust() {
        $debug_mode = get_option( 'smdp_pwa_debug_mode', 0 );
        if ( ! $debug_mode ) {
            return;
        }

        // Prevent service worker from caching in debug mode
        add_action(
            'wp_head',
            function() {
                echo '<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">';
                echo '<meta http-equiv="Pragma" content="no-cache">';
                echo '<meta http-equiv="Expires" content="0">';
            },
            1
        );
    }

    /**
     * Display admin notice when debug mode is active
     *
     * Shows a warning in WordPress admin when PWA debug mode is enabled.
     */
    public function debug_mode_notice() {
        $debug_mode = get_option( 'smdp_pwa_debug_mode', 0 );
        if ( ! $debug_mode ) {
            return;
        }

        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>⚠️ PWA Debug Mode is Active!</strong> Caching is disabled and debug panel is visible. Remember to disable this in production.</p>';
        echo '</div>';
    }
}

// Initialize the class
SMDP_Debug_Panel::instance();

/**
 * Backward compatibility wrapper functions
 */

if ( ! function_exists( 'smdp_debug_panel' ) ) {
    function smdp_debug_panel() {
        SMDP_Debug_Panel::instance()->render_debug_panel();
    }
}

if ( ! function_exists( 'smdp_service_worker_cache_bust' ) ) {
    function smdp_service_worker_cache_bust() {
        SMDP_Debug_Panel::instance()->service_worker_cache_bust();
    }
}

if ( ! function_exists( 'smdp_debug_mode_notice' ) ) {
    function smdp_debug_mode_notice() {
        SMDP_Debug_Panel::instance()->debug_mode_notice();
    }
}
