// TaskFlow Pro - Progressive Web App Service Worker
const CACHE_NAME = 'taskflow-pwa-v3.2.8';
const STATIC_ASSETS = [
    './favicon.svg',
    './manifest.json'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch(() => {});
        }).then(() => self.skipWaiting())
    );
});

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

// Network-first strategy for dynamic pages, Cache-first for static assets
self.addEventListener('fetch', (event) => {
    // Only handle GET requests
    if (event.request.method !== 'GET') return;

    let url;
    try {
        url = new URL(event.request.url);
    } catch (e) {
        return;
    }

    // Skip non-http/https schemes (e.g. chrome-extension://, data:)
    if (!url.protocol.startsWith('http')) return;

    // Do NOT intercept cross-origin tracking beacons, analytics, or third-party CDNs
    // (Bypasses Cloudflare insights, ad-blockers, external analytics safely)
    if (url.origin !== self.location.origin) {
        if (!url.hostname.includes('fonts.googleapis.com') && !url.hostname.includes('fonts.gstatic.com')) {
            return;
        }
    }

    // Static assets: Cache-first with network fallback
    if (url.pathname.match(/\.(svg|png|jpg|jpeg|webp|ico|css|woff2?)$/i) || url.hostname.includes('fonts.')) {
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
                    // Always return a valid Response, NEVER undefined!
                    return cached || new Response('', { status: 404, statusText: 'Not Found' });
                });
            })
        );
        return;
    }

    // Dynamic pages: Network-first with guaranteed valid Response fallback
    event.respondWith(
        fetch(event.request)
            .then((response) => {
                return response;
            })
            .catch(async () => {
                try {
                    const cached = await caches.match(event.request);
                    if (cached) return cached;
                    if (event.request.mode === 'navigate') {
                        const cachedHome = (await caches.match('./index.php')) || (await caches.match('./'));
                        if (cachedHome) return cachedHome;
                    }
                } catch (e) {}

                // Always return a valid Response so event.respondWith never throws TypeError
                return new Response(
                    '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Offline - TaskFlow Pro</title></head><body style="font-family:sans-serif;text-align:center;padding:50px 20px;color:#334155"><h2 style="font-size:20px;font-weight:800;color:#0f172a">Koneksi Terputus</h2><p style="font-size:14px;color:#64748b;margin:12px 0 24px">Tidak dapat terhubung ke server. Periksa jaringan internet Anda lalu muat ulang halaman.</p><button onclick="window.location.reload()" style="background:#4f46e5;color:#fff;border:none;padding:10px 20px;border-radius:10px;font-weight:700;cursor:pointer">Muat Ulang Halaman</button></body></html>',
                    {
                        status: 503,
                        statusText: 'Service Unavailable',
                        headers: new Headers({ 'Content-Type': 'text/html; charset=utf-8' })
                    }
                );
            })
    );
});
