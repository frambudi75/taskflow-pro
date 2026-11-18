// TaskFlow Pro - Progressive Web App Service Worker
const CACHE_NAME = 'taskflow-pwa-v3.2.10';
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
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Precision Fetch: ONLY handle static local assets, NEVER touch dynamic PHP or third-party beacons
self.addEventListener('fetch', (event) => {
    // Only handle GET requests
    if (event.request.method !== 'GET') return;

    let url;
    try {
        url = new URL(event.request.url);
    } catch (e) {
        return;
    }

    // Never intercept third-party domains (e.g. Cloudflare Insights, Google Analytics, ad-block targets)
    if (url.origin !== self.location.origin) {
        if (!url.hostname.includes('fonts.googleapis.com') && !url.hostname.includes('fonts.gstatic.com')) {
            return; // Let browser handle natively
        }
    }

    // Only cache static file extensions
    const isStaticAsset = url.pathname.match(/\.(svg|png|jpg|jpeg|webp|ico|css|woff2?)$/i) || url.hostname.includes('fonts.');
    if (!isStaticAsset) {
        // Do NOT call event.respondWith for HTML/PHP pages or dynamic endpoints.
        // Let browser handle all navigation natively with full session cookies and direct server response!
        return;
    }

    // Static assets: Cache-first with network fallback
    event.respondWith(
        caches.match(event.request).then((cached) => {
            if (cached) return cached;
            return fetch(event.request).then((response) => {
                if (response && response.status === 200) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return response;
            }).catch(() => {
                // Return cached or empty valid response (NEVER return undefined)
                return cached || new Response('', { status: 404, statusText: 'Not Found' });
            });
        })
    );
});
