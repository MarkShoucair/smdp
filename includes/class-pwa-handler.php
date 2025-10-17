<?php
/**
 * PWA Handler
 *
 * Handles Progressive Web App functionality including service worker registration,
 * manifest generation, and PWA-specific features.
 *
 * @package Square_Menu_Display
 * @since 3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SMDP_PWA_Handler
 *
 * Manages PWA functionality for menu app pages.
 */
class SMDP_PWA_Handler {

    /**
     * Singleton instance
     *
     * @var SMDP_PWA_Handler
     */
    private static $instance = null;

    /**
     * Service worker cache version
     *
     * @var int
     */
    private $cache_version;

    /**
     * Get singleton instance
     *
     * @return SMDP_PWA_Handler
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->cache_version = get_option( 'smdp_cache_version', 1 );

        // Intercept service worker request VERY early (before any redirects)
        add_action( 'parse_request', array( $this, 'intercept_service_worker_request' ), 1 );

        // Register service worker on menu app pages
        add_action( 'wp_footer', array( $this, 'register_service_worker_script' ), 5 );
    }

    /**
     * Intercept service worker request before WordPress routing
     *
     * This runs very early in the WordPress request lifecycle to avoid redirects.
     *
     * @param WP $wp WordPress object.
     */
    public function intercept_service_worker_request( $wp ) {
        // Check if this is a request for our service worker
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Parse the URI to get just the path (without query string)
        $parsed_uri = parse_url( $request_uri );
        $path = $parsed_uri['path'] ?? '';

        // Check if the path ends with /smdp-sw.js
        if ( substr( $path, -12 ) === '/smdp-sw.js' ) {
            $this->serve_service_worker();
        }
    }

    /**
     * Serve service worker JavaScript file
     *
     * Generates and serves the service worker with proper headers and scope.
     */
    private function serve_service_worker() {
        // Set proper headers for service worker
        header( 'Content-Type: application/javascript; charset=utf-8' );
        header( 'Service-Worker-Allowed: /' );

        // Cache control based on debug mode
        $debug_mode = get_option( 'smdp_pwa_debug_mode', 0 );
        if ( $debug_mode ) {
            header( 'Cache-Control: no-cache, no-store, must-revalidate' );
            header( 'Pragma: no-cache' );
            header( 'Expires: 0' );
        } else {
            header( 'Cache-Control: max-age=0' );
        }

        // Output service worker code
        echo $this->generate_service_worker_code();
        exit;
    }

    /**
     * Generate service worker JavaScript code
     *
     * @return string Service worker code.
     */
    private function generate_service_worker_code() {
        $cache_version = $this->cache_version;
        $plugin_url = plugin_dir_url( dirname( __FILE__ ) );
        $debug_mode = get_option( 'smdp_pwa_debug_mode', 0 ) ? 'true' : 'false';

        // Get plugin assets to cache
        $assets_to_cache = $this->get_cacheable_assets();

        ob_start();
        ?>
// Service Worker for Square Menu Display Plugin
// Version: <?php echo esc_js( $cache_version ); ?>

// Cache configuration
const CACHE_NAME = 'smdp-menu-v<?php echo esc_js( $cache_version ); ?>';
const DEBUG_MODE = <?php echo $debug_mode; ?>;

// Assets to cache (plugin-specific only)
const CACHE_ASSETS = <?php echo wp_json_encode( $assets_to_cache ); ?>;

// API endpoints to cache
const API_PATTERNS = [
    '/wp-json/smdp/v1/app-catalog',
    '/wp-json/smdp/v1/catalog-search',
];

/**
 * Install event - Pre-cache essential assets
 */
self.addEventListener('install', function(event) {
    if (DEBUG_MODE) {
        console.log('[SMDP SW] Installing service worker v<?php echo esc_js( $cache_version ); ?>');
    }

    // Skip waiting to activate immediately
    self.skipWaiting();

    // Pre-cache essential assets
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            if (DEBUG_MODE) {
                console.log('[SMDP SW] Pre-caching assets:', CACHE_ASSETS);
            }
            return cache.addAll(CACHE_ASSETS).catch(function(error) {
                console.error('[SMDP SW] Pre-cache failed:', error);
            });
        })
    );
});

/**
 * Activate event - Clean up old caches
 */
self.addEventListener('activate', function(event) {
    if (DEBUG_MODE) {
        console.log('[SMDP SW] Activating service worker v<?php echo esc_js( $cache_version ); ?>');
    }

    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.map(function(cacheName) {
                    // Delete old SMDP caches only (don't touch other caches)
                    if (cacheName.startsWith('smdp-') && cacheName !== CACHE_NAME) {
                        if (DEBUG_MODE) {
                            console.log('[SMDP SW] Deleting old cache:', cacheName);
                        }
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(function() {
            // Take control of all pages immediately
            return self.clients.claim();
        })
    );
});

/**
 * Fetch event - Implement caching strategies
 */
self.addEventListener('fetch', function(event) {
    const url = event.request.url;

    // Only handle GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // Check if this is a plugin asset
    if (isPluginAsset(url)) {
        event.respondWith(cacheFirstStrategy(event.request));
        return;
    }

    // Check if this is a menu API call
    if (isMenuAPI(url)) {
        event.respondWith(networkFirstStrategy(event.request));
        return;
    }

    // Don't cache anything else (let browser/site handle it)
});

/**
 * Check if URL is a plugin asset
 */
function isPluginAsset(url) {
    return url.includes('/square-menu-display/assets/');
}

/**
 * Check if URL is a menu API endpoint
 */
function isMenuAPI(url) {
    return API_PATTERNS.some(pattern => url.includes(pattern));
}

/**
 * Cache-first strategy (for static assets)
 */
async function cacheFirstStrategy(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            if (DEBUG_MODE) {
                console.log('[SMDP SW] Cache hit:', request.url);
            }
            return cachedResponse;
        }

        const networkResponse = await fetch(request);

        // Cache successful responses
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch (error) {
        console.error('[SMDP SW] Cache-first failed:', error);
        throw error;
    }
}

/**
 * Network-first strategy (for API calls)
 */
async function networkFirstStrategy(request) {
    try {
        const networkResponse = await fetch(request);

        // Cache successful responses
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch (error) {
        // Network failed, try cache
        if (DEBUG_MODE) {
            console.log('[SMDP SW] Network failed, using cache:', request.url);
        }

        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }

        throw error;
    }
}

/**
 * Periodic background sync (when supported)
 */
self.addEventListener('periodicsync', function(event) {
    if (event.tag === 'menu-refresh') {
        if (DEBUG_MODE) {
            console.log('[SMDP SW] Periodic sync triggered');
        }
        event.waitUntil(syncMenuData());
    }
});

/**
 * Manual sync fallback
 */
self.addEventListener('sync', function(event) {
    if (event.tag === 'menu-refresh') {
        if (DEBUG_MODE) {
            console.log('[SMDP SW] Manual sync triggered');
        }
        event.waitUntil(syncMenuData());
    }
});

/**
 * Sync menu data
 */
async function syncMenuData() {
    try {
        // Notify all clients to refresh
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'REFRESH_MENU',
                timestamp: Date.now()
            });
        });
    } catch (error) {
        console.error('[SMDP SW] Sync error:', error);
    }
}

/**
 * Message handling from main app
 */
self.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys().then(function(cacheNames) {
                return Promise.all(
                    cacheNames.map(function(cacheName) {
                        if (cacheName.startsWith('smdp-')) {
                            if (DEBUG_MODE) {
                                console.log('[SMDP SW] Clearing cache:', cacheName);
                            }
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
        );
    }
});

if (DEBUG_MODE) {
    console.log('[SMDP SW] Service worker loaded - Cache version: <?php echo esc_js( $cache_version ); ?>');
}
<?php
        return ob_get_clean();
    }

    /**
     * Get list of cacheable assets
     *
     * @return array List of asset URLs to pre-cache.
     */
    private function get_cacheable_assets() {
        $plugin_url = plugin_dir_url( dirname( __FILE__ ) );

        $assets = array(
            // CSS
            $plugin_url . 'assets/css/menu-app.css',

            // JavaScript
            $plugin_url . 'assets/js/menu-app-frontend.js',
            $plugin_url . 'assets/js/item-detail.js',
            $plugin_url . 'assets/js/help-request.js',
            $plugin_url . 'assets/js/view-bill.js',
            $plugin_url . 'assets/js/table-setup.js',
        );

        // Add promo images if configured
        $settings = get_option( 'smdp_app_settings', array() );
        if ( ! empty( $settings['promo_images'] ) && is_array( $settings['promo_images'] ) ) {
            foreach ( $settings['promo_images'] as $image_url ) {
                if ( ! empty( $image_url ) ) {
                    $assets[] = $image_url;
                }
            }
        }

        return array_values( array_unique( $assets ) );
    }

    /**
     * Register service worker script on frontend
     *
     * Only registers on pages where the menu app shortcode is rendered.
     */
    public function register_service_worker_script() {
        // Only run on pages where menu app is rendered
        if ( ! SMDP_Debug_Panel::instance() || ! defined( 'SMDP_MENU_APP_RENDERED' ) ) {
            return;
        }

        $sw_url = home_url( '/smdp-sw.js' );
        $debug_mode = get_option( 'smdp_pwa_debug_mode', 0 );
        ?>
        <script>
        // Register SMDP Service Worker
        (function() {
            if ('serviceWorker' in navigator) {
                const swUrl = <?php echo wp_json_encode( $sw_url ); ?>;
                const debugMode = <?php echo $debug_mode ? 'true' : 'false'; ?>;

                window.addEventListener('load', function() {
                    navigator.serviceWorker.register(swUrl, {
                        scope: '/'
                    }).then(function(registration) {
                        if (debugMode) {
                            console.log('[SMDP] Service worker registered:', registration.scope);
                        }

                        // Check for updates periodically
                        setInterval(function() {
                            registration.update();
                        }, 60000); // Check every minute

                        // Listen for messages from service worker
                        navigator.serviceWorker.addEventListener('message', function(event) {
                            if (event.data && event.data.type === 'REFRESH_MENU') {
                                if (debugMode) {
                                    console.log('[SMDP] Menu refresh requested by service worker');
                                }
                                // Trigger menu refresh (will be handled by menu app)
                                window.dispatchEvent(new CustomEvent('smdp-refresh-menu'));
                            }
                        });
                    }).catch(function(error) {
                        console.error('[SMDP] Service worker registration failed:', error);
                    });
                });
            }
        })();
        </script>
        <?php
    }

    /**
     * Unregister service worker (cleanup on deactivation)
     */
    public static function unregister_service_worker() {
        ?>
        <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                for (let registration of registrations) {
                    if (registration.active && registration.active.scriptURL.includes('smdp-sw.js')) {
                        registration.unregister().then(function(success) {
                            if (success) {
                                console.log('[SMDP] Service worker unregistered');
                            }
                        });
                    }
                }
            });
        }
        </script>
        <?php
    }
}
