/**
 * Gilaf Store Sales Portal - Service Worker
 * STRATEGY: Network-first for ALL requests. Cache is ONLY an offline fallback.
 * Every deploy: bump CACHE_VERSION so old cache is purged automatically.
 */

const CACHE_VERSION = 3;
const CACHE_NAME = 'gilaf-sales-v' + CACHE_VERSION;

// Only cache fonts/icons that rarely change (external CDNs)
const PRECACHE = [
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
];

// ── Install: cache only external CDN assets, activate immediately ──
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function(cache) {
            return cache.addAll(PRECACHE);
        })
    );
    self.skipWaiting(); // Activate new SW immediately, don't wait
});

// ── Activate: delete ALL old caches, take control of all clients ──
self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.filter(function(key) { return key !== CACHE_NAME; })
                    .map(function(key) { return caches.delete(key); })
            );
        }).then(function() {
            return self.clients.claim(); // Take over all open tabs immediately
        })
    );
});

// ── Listen for skip-waiting message from client ──
self.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        caches.keys().then(function(keys) {
            keys.forEach(function(key) { caches.delete(key); });
        });
    }
});

// ── Fetch: ALWAYS network-first. Cache is only offline fallback. ──
self.addEventListener('fetch', function(event) {
    // Skip non-GET requests (form submissions, POST, etc.)
    if (event.request.method !== 'GET') return;

    var url = new URL(event.request.url);

    // Never cache API/sync endpoints or version checks
    if (url.pathname.indexOf('api_sync') > -1 || url.pathname.indexOf('version.php') > -1) {
        event.respondWith(fetch(event.request));
        return;
    }

    // Network-first for EVERYTHING (PHP pages, CSS, JS, images)
    event.respondWith(
        fetch(event.request).then(function(response) {
            // Cache successful responses as offline fallback
            if (response.ok) {
                var clone = response.clone();
                caches.open(CACHE_NAME).then(function(cache) {
                    cache.put(event.request, clone);
                });
            }
            return response;
        }).catch(function() {
            // Offline: try cache
            return caches.match(event.request).then(function(cached) {
                if (cached) return cached;
                // For HTML pages, show offline message
                if (event.request.headers.get('accept') && event.request.headers.get('accept').indexOf('text/html') > -1) {
                    return new Response(
                        '<html><body style="font-family:Inter,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#0f172a;color:#fff;text-align:center;"><div><h1 style="font-size:48px;margin-bottom:8px;">📡</h1><h2>You are Offline</h2><p style="color:#94a3b8;">Please check your internet connection and try again.</p><button onclick="location.reload()" style="margin-top:16px;padding:12px 24px;background:#7c3aed;color:#fff;border:none;border-radius:8px;font-size:14px;cursor:pointer;">Retry</button></div></body></html>',
                        { headers: { 'Content-Type': 'text/html' } }
                    );
                }
                return new Response('', { status: 408 });
            });
        })
    );
});
