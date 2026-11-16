/*
 * Road Platform service worker.
 * Bump VERSION on every deploy so old caches are dropped.
 *
 * Strategy:
 *  - /build assets, fonts, images      -> cache-first (they are content-hashed)
 *  - citizen page navigations          -> stale-while-revalidate (instant, then fresh)
 *  - /admin and /account pages         -> network only (never cached)
 *  - offline navigations               -> last cached copy, else the /offline page
 *  - POST / non-GET                    -> untouched (forms talk straight to the server)
 */
const VERSION = 'pwa-v2';
const PAGE_CACHE = 'pages-' + VERSION;
const ASSET_CACHE = 'assets-' + VERSION;

const CACHEABLE_PAGE = (path) =>
    path === '/' || path.startsWith('/issues') || path.startsWith('/p/') ||
    path === '/offline' || path === '/manifest.json';

const isAsset = (path) =>
    path.startsWith('/build/') || path.startsWith('/icons/') ||
    /\.(css|js|woff2?|png|jpe?g|webp|svg|gif)$/.test(path);

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(PAGE_CACHE).then((c) => c.addAll(['/offline', '/'])).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys
                .filter((k) => k !== PAGE_CACHE && k !== ASSET_CACHE)
                .map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Hashed build assets & images: cache-first, fill on miss.
    if (isAsset(url.pathname)) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok) {
                    const copy = res.clone();
                    caches.open(ASSET_CACHE).then((c) => c.put(req, copy));
                }
                return res;
            }))
        );
        return;
    }

    // Citizen pages: serve instantly from cache, refresh in the background.
    if (req.mode === 'navigate' && CACHEABLE_PAGE(url.pathname)) {
        event.respondWith(
            caches.match(req).then((cached) => {
                const fresh = fetch(req).then((res) => {
                    if (res.ok) {
                        const copy = res.clone();
                        caches.open(PAGE_CACHE).then((c) => c.put(req, copy));
                    }
                    return res;
                }).catch(() => cached || caches.match('/offline'));

                return cached || fresh;
            })
        );
        return;
    }

    // Everything else (admin, account, login): straight to the network,
    // with the offline page as last resort for navigations.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(() => caches.match('/offline'))
        );
    }
});
