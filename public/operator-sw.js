const CACHE = 'operator-offline-v4';
const ASSETS = [
    '/js/vendor/qrcode-generator.js',
    '/js/offline/indexed-db.js',
    '/js/offline/connectivity.js',
    '/js/offline/pull-merge.js',
    '/js/offline/local-scan-validator.js',
    '/js/offline/local-print.js',
    '/js/offline/local-registration.js',
    '/js/offline/local-search.js',
    '/js/offline/bootstrap.js',
    '/js/offline/sync-engine.js',
    '/js/offline/operator-bridge.js',
    '/js/offline/print-bridge.js',
    '/js/offline/print-renderer.js',
];

const OFFLINE_FALLBACK_HTML = `<!doctype html><html><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Offline</title>
<style>body{font-family:system-ui,Arial,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{max-width:420px;text-align:center;padding:32px}h1{font-size:20px}p{color:#94a3b8;font-size:14px}
button{margin-top:16px;padding:10px 18px;border:0;border-radius:8px;background:#2563eb;color:#fff;font-size:14px}</style></head>
<body><div class="box"><h1>You are offline</h1>
<p>This page has not been opened on this device yet. Connect to the venue WiFi/hub once to load it, then it will work offline.</p>
<button onclick="location.reload()">Retry</button></div></body></html>`;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(ASSETS)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Never cache the sync API; always go to network, fall back to cached response only if present.
    if (url.pathname.startsWith('/operator/offline/')) {
        event.respondWith(fetch(request).catch(() => caches.match(request)));
        return;
    }

    // Page navigations to operator screens: network-first, cache last good copy, fall back offline.
    const isOperatorPage =
        request.mode === 'navigate' &&
        request.method === 'GET' &&
        url.origin === self.location.origin &&
        url.pathname.startsWith('/operator');

    if (isOperatorPage) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response && response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
                .catch(async () => {
                    const cached = await caches.match(request, { ignoreSearch: true });
                    return cached || new Response(OFFLINE_FALLBACK_HTML, {
                        headers: { 'Content-Type': 'text/html; charset=utf-8' },
                    });
                })
        );
        return;
    }

    // Static offline JS modules: cache-first.
    if (ASSETS.some((a) => url.pathname.endsWith(a))) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request))
        );
    }
});
