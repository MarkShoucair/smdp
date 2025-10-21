// Service Worker for PWA background sync
const CACHE_NAME = 'smdp-menu-v1';

self.addEventListener('install', function(event) {
  self.skipWaiting();
});

self.addEventListener('activate', function(event) {
  event.waitUntil(self.clients.claim());
});

// Periodic background sync (when supported)
self.addEventListener('periodicsync', function(event) {
  if (event.tag === 'menu-refresh') {
    event.waitUntil(syncMenuData());
  }
});

// Manual sync fallback
self.addEventListener('sync', function(event) {
  if (event.tag === 'menu-refresh') {
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
    // Sync error - fail silently
  }
}

// Message handling from main app
self.addEventListener('message', function(event) {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});