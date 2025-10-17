// Service Worker for PWA background sync
const CACHE_NAME = 'smdp-menu-v1';

self.addEventListener('install', function(event) {
  console.log('[Service Worker] Installing');
  self.skipWaiting();
});

self.addEventListener('activate', function(event) {
  console.log('[Service Worker] Activating');
  event.waitUntil(self.clients.claim());
});

// Periodic background sync (when supported)
self.addEventListener('periodicsync', function(event) {
  if (event.tag === 'menu-refresh') {
    console.log('[Service Worker] Periodic sync triggered');
    event.waitUntil(syncMenuData());
  }
});

// Manual sync fallback
self.addEventListener('sync', function(event) {
  if (event.tag === 'menu-refresh') {
    console.log('[Service Worker] Sync triggered');
    event.waitUntil(syncMenuData());
  }
});

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
    console.error('[Service Worker] Sync error:', error);
  }
}

// Message handling from main app
self.addEventListener('message', function(event) {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});