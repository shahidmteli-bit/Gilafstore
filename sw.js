/**
 * Gilaf Store — Service Worker
 * Handles caching for offline support and fast loading
 * Network-first strategy for dynamic content, cache-first for static assets
 */

const CACHE_NAME = 'gilaf-store-v1';
const STATIC_CACHE = 'gilaf-static-v1';

// Static assets to pre-cache on install
const PRECACHE_ASSETS = [
    './assets/icons/icon-192x192.png',
    './assets/icons/icon-512x512.png',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap'
];

// Install: pre-cache static assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => cache.addAll(PRECACHE_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Activate: clean old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys.filter(key => key !== CACHE_NAME && key !== STATIC_CACHE)
                    .map(key => caches.delete(key))
            )
        ).then(() => self.clients.claim())
    );
});

// Fetch: Network-first for pages, cache-first for static assets
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Skip non-GET requests
    if (event.request.method !== 'GET') return;

    // Skip admin pages and API calls
    if (url.pathname.includes('/admin/') || url.pathname.includes('/api_') || url.pathname.includes('action=')) return;

    // Static assets (CSS, JS, images, fonts) — cache-first
    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(event.request).then(cached => {
                if (cached) return cached;
                return fetch(event.request).then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(STATIC_CACHE).then(cache => cache.put(event.request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // HTML pages — network-first with cache fallback
    if (event.request.headers.get('accept')?.includes('text/html')) {
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                    }
                    return response;
                })
                .catch(() => caches.match(event.request).then(cached => {
                    return cached || caches.match('./index.php') || offlinePage();
                }))
        );
        return;
    }
});

function isStaticAsset(url) {
    const exts = ['.css', '.js', '.png', '.jpg', '.jpeg', '.webp', '.svg', '.ico', '.woff', '.woff2', '.ttf'];
    return exts.some(ext => url.pathname.endsWith(ext)) ||
           url.hostname === 'cdnjs.cloudflare.com' ||
           url.hostname === 'fonts.googleapis.com' ||
           url.hostname === 'fonts.gstatic.com';
}

function offlinePage() {
    return new Response(`
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Gilaf Store — Offline</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Inter', sans-serif; background: #1a3c34; color: #fff; display: flex; align-items: center; justify-content: center; min-height: 100vh; text-align: center; padding: 24px; }
                .offline-card { max-width: 400px; }
                .offline-icon { font-size: 64px; margin-bottom: 24px; opacity: .7; }
                h1 { font-size: 28px; font-weight: 700; margin-bottom: 12px; }
                p { font-size: 15px; opacity: .8; line-height: 1.6; margin-bottom: 24px; }
                .retry-btn { background: #22c55e; color: #fff; border: none; padding: 12px 32px; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; }
                .retry-btn:hover { background: #16a34a; }
            </style>
        </head>
        <body>
            <div class="offline-card">
                <div class="offline-icon">📡</div>
                <h1>You're Offline</h1>
                <p>It looks like you've lost your internet connection. Gilaf Store needs an active connection to show you the latest products and updates.</p>
                <button class="retry-btn" onclick="location.reload()">Try Again</button>
            </div>
        </body>
        </html>
    `, { headers: { 'Content-Type': 'text/html' } });
}
