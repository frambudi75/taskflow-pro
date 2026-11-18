// TaskFlow Pro - Progressive Web App Service Worker
const CACHE_NAME = 'taskflow-pwa-v3.3.2';
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

// Precision Fetch: ONLY handle static local assets, NEVER touch dynamic PHP, navigation, or third-party beacons
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

    // Never intercept third-party domains (e.g. Cloudflare Insights, Google Analytics, beacons)
    if (url.origin !== self.location.origin) {
        if (!url.hostname.includes('fonts.googleapis.com') && !url.hostname.includes('fonts.gstatic.com')) {
            return;
        }
    }

    // Only cache static file extensions
    const isStaticAsset = url.pathname.match(/\.(svg|png|jpg|jpeg|webp|ico|css|woff2?)$/i) || url.hostname.includes('fonts.');
    if (!isStaticAsset) {
        return;
    }

    // Static assets: Cache-first with network fallback and guaranteed valid Response
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
                return cached || new Response('', { status: 404, statusText: 'Not Found' });
            });
        }).catch(() => {
            return new Response('', { status: 404, statusText: 'Not Found' });
        })
    );
});
