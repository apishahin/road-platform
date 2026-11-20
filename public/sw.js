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
const VERSION = 'pwa-v3';
const PAGE_CACHE = 'pages-' + VERSION;
const ASSET_CACHE = 'assets-' + VERSION;

const CACHEABLE_PAGE = (path) =>
    path === '/' || path.startsWith('/issues') || path.startsWith('/p/') ||
    path === '/report' || path === '/report/success' ||
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
                    // Never cache redirects (e.g. /report -> login for guests):
                    // only store the page when the final URL is the requested one.
                    if (res.ok && new URL(res.url).pathname === url.pathname) {
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

/*
 * Offline report queue — Background Sync (Android Chrome).
 * The report page saves failed/offline submissions into IndexedDB
 * (db: road-platform-pwa, store: reportQueue) and registers the
 * 'report-queue' sync. When the browser is online again we fetch a fresh
 * CSRF token and POST each queued report. iOS Safari has no Background
 * Sync; there the page itself replays on the 'online' event.
 */
const RQ_DB = 'road-platform-pwa';
const RQ_STORE = 'reportQueue';

function rqOpen() {
    return new Promise((res, rej) => {
        const r = indexedDB.open(RQ_DB, 1);
        r.onupgradeneeded = () => {
            if (!r.result.objectStoreNames.contains(RQ_STORE)) {
                r.result.createObjectStore(RQ_STORE, { keyPath: 'id', autoIncrement: true });
            }
        };
        r.onsuccess = () => res(r.result);
        r.onerror = () => rej(r.error);
    });
}
function rqAll() {
    return rqOpen().then((db) => new Promise((res, rej) => {
        const r = db.transaction(RQ_STORE, 'readonly').objectStore(RQ_STORE).getAll();
        r.onsuccess = () => res(r.result || []);
        r.onerror = () => rej(r.error);
    }));
}
function rqDel(id) {
    return rqOpen().then((db) => new Promise((res, rej) => {
        const t = db.transaction(RQ_STORE, 'readwrite');
        t.objectStore(RQ_STORE).delete(id);
        t.oncomplete = () => res();
        t.onerror = () => rej(t.error);
    }));
}

async function rqReplay() {
    const items = await rqAll();
    if (!items.length) return;

    const html = await (await fetch('/report', { credentials: 'same-origin' })).text();
    const m = html.match(/name="_token"[^>]*value="([^"]+)"/) || html.match(/value="([^"]+)"[^>]*name="_token"/);
    let token = m ? m[1] : null;
    if (!token) return;

    for (const item of items) {
        const fd = new FormData();
        Object.keys(item.fields || {}).forEach((k) => fd.append(k, item.fields[k]));
        (item.photos || []).forEach((p) => fd.append(p.field, p.blob, p.name));
        try {
            const res = await fetch('/report', {
                method: 'POST', credentials: 'same-origin', redirect: 'follow',
                headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            if (res.redirected && res.url.indexOf('/report/success') !== -1) {
                await rqDel(item.id);
            } else if (res.status === 419) {
                const h2 = await (await fetch('/report', { credentials: 'same-origin' })).text();
                const m2 = h2.match(/name="_token"[^>]*value="([^"]+)"/);
                token = m2 ? m2[1] : token;
            } else if (res.status >= 400 && res.status < 500 && res.status !== 429) {
                await rqDel(item.id); // invalid data — never going to pass
            }
        } catch (e) {
            // still offline — leave queued for the next sync
            return;
        }
    }
}

self.addEventListener('sync', (event) => {
    if (event.tag === 'report-queue') {
        event.waitUntil(rqReplay());
    }
});
