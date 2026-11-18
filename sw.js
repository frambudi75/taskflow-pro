// TaskFlow Pro - Progressive Web App Service Worker
const CACHE_NAME = 'taskflow-pwa-v3.5.0';
const STATIC_ASSETS = [
    './favicon.svg',
    './manifest.json'
];

// Immediate takeover on install
self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch(() => {});
        })
    );
});

// Immediate claim and clean up old caches on activate
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

// Precision Fetch: ONLY handle same-origin static local assets.
// NEVER intercept external domains (Cloudflare insights, beacons, CDNs), PHP files, or HTML navigation.
self.addEventListener('fetch', (event) => {
    // Only handle GET requests
    if (event.request.method !== 'GET') return;

    // Never intercept navigation requests - let browser handle HTML/PHP pages natively
    if (event.request.mode === 'navigate') return;

    let url;
    try {
        url = new URL(event.request.url);
    } catch (e) {
        return;
    }

    // STRICT: Only intercept SAME-ORIGIN requests. Cross-origin (Cloudflare, Google, CDNs) are left 100% to browser native
    if (url.origin !== self.location.origin) {
        return;
    }

    // Never intercept dynamic PHP scripts or API endpoints
    if (url.pathname.endsWith('.php') || url.search.includes('page=') || url.search.length > 0) {
        return;
    }

    // Only cache known static file extensions
    const isStaticAsset = /\.(svg|png|jpg|jpeg|webp|ico|css|js|woff2?)$/i.test(url.pathname);
    if (!isStaticAsset) {
        return;
    }

    // Static assets: Cache-first with network fallback and guaranteed valid Response
    event.respondWith(
        caches.match(event.request).then((cached) => {
            if (cached) return cached;
            return fetch(event.request).then((response) => {
                if (response && response.status === 200 && response.type === 'basic') {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, clone).catch(() => {});
                    });
                }
                return response || new Response('', { status: 404, statusText: 'Not Found' });
            }).catch(() => {
                return cached || new Response('', { status: 404, statusText: 'Not Found' });
            });
        }).catch(() => {
            return new Response('', { status: 404, statusText: 'Not Found' });
        })
    );
});
