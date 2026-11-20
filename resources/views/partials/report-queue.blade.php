{{--
    Offline-first report queue (PWA).

    - Offline submit: the report (text + already-compressed photos) is saved
      into IndexedDB instead of failing — the user sees "saved, will send".
    - Auto-replay: when the connection returns, queued reports are POSTed in
      the background (fresh CSRF token is fetched first). Also runs when the
      service worker fires a Background Sync event (Android Chrome).
    - A small card on the form shows the pending count + manual retry/clear.
--}}
<div id="rq-card" hidden
     class="mb-4 flex items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
    <span class="text-lg leading-none">⏳</span>
    <span class="flex-1" data-rq-text></span>
    <button type="button" data-rq-send class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-bold text-white">{{ __('report.rq_send_now') }}</button>
    <button type="button" data-rq-clear class="text-xs font-medium text-amber-700 underline">{{ __('report.rq_clear') }}</button>
</div>
<div id="rq-note" hidden class="mb-4 rounded-xl border border-green-300 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800"></div>

<script>
(function () {
    'use strict';

    var POST_URL  = @json(route('report.store'));
    var POST_PATH = new URL(POST_URL).pathname;
    var DB_NAME = 'road-platform-pwa', STORE = 'reportQueue';
    var STR = {
        pending: @json(__('report.rq_pending')),
        saved:   @json(__('report.rq_saved')),
        sent:    @json(__('report.rq_sent')),
        failed:  @json(__('report.rq_failed'))
    };

    /* ---------- IndexedDB helpers ---------- */
    function openDb() {
        return new Promise(function (res, rej) {
            var r = indexedDB.open(DB_NAME, 1);
            r.onupgradeneeded = function () {
                if (!r.result.objectStoreNames.contains(STORE)) {
                    r.result.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
                }
            };
            r.onsuccess = function () { res(r.result); };
            r.onerror   = function () { rej(r.error); };
        });
    }
    function tx(mode, fn) {
        return openDb().then(function (db) {
            return new Promise(function (res, rej) {
                var t = db.transaction(STORE, mode);
                var out = fn(t.objectStore(STORE));
                t.oncomplete = function () { res(out && out.result !== undefined ? out.result : out); };
                t.onerror = function () { rej(t.error); };
            });
        });
    }
    var qAll  = function ()    { return tx('readonly', function (s) {
        return new Promise(function (res, rej) {
            var r = s.getAll();
            r.onsuccess = function () { res(r.result || []); };
            r.onerror = function () { rej(r.error); };
        });
    }); };
    var qAdd  = function (it)  { return tx('readwrite', function (s) { s.add(it); }); };
    var qDel  = function (id)  { return tx('readwrite', function (s) { s.delete(id); }); };
    var qPut  = function (it)  { return tx('readwrite', function (s) { s.put(it); }); };

    /* ---------- collect the live form into a queue item ---------- */
    function collectItem(form) {
        var fd = new FormData(form);
        var fields = {}, photos = [];
        fd.forEach(function (v, k) {
            if (k === '_token' || k === '_method') return;
            if (v instanceof File) {
                if (v.size > 0) photos.push({ field: k, name: v.name || 'photo.jpg', type: v.type || 'image/jpeg', blob: v });
            } else {
                fields[k] = v;
            }
        });
        return { fields: fields, photos: photos, at: Date.now(), tries: 0 };
    }

    /* ---------- replay ---------- */
    function freshToken() {
        return fetch(POST_URL, { credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var m = html.match(/name="_token"[^>]*value="([^"]+)"/) || html.match(/value="([^"]+)"[^>]*name="_token"/);
                return m ? m[1] : null;
            });
    }

    function sendOne(item, token) {
        var fd = new FormData();
        Object.keys(item.fields).forEach(function (k) { fd.append(k, item.fields[k]); });
        item.photos.forEach(function (p) { fd.append(p.field, p.blob, p.name); });
        return fetch(POST_URL, {
            method: 'POST', credentials: 'same-origin', redirect: 'follow',
            headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
    }

    var replaying = false;
    function replay() {
        if (replaying || !navigator.onLine || !window.indexedDB) return Promise.resolve();
        replaying = true;
        return qAll().then(function (items) {
            if (!items.length) { render(); return null; }
            return freshToken().then(function (token) {
                if (!token) return null;
                var chain = Promise.resolve();
                items.forEach(function (item) {
                    chain = chain.then(function () {
                        return sendOne(item, token).then(function (res) {
                            if (res.redirected && res.url.indexOf('/report/success') !== -1) {
                                return qDel(item.id).then(function () { note(STR.sent); });
                            }
                            if (res.status === 419) {
                                return freshToken().then(function (t) { token = t || token; });
                            }
                            // 4xx/5xx: count the try, drop hopeless items after 3 attempts
                            item.tries = (item.tries || 0) + 1;
                            if (item.tries >= 3) {
                                return qDel(item.id).then(function () { note(STR.failed); });
                            }
                            return qPut(item);
                        }).catch(function () { /* stay queued */ });
                    });
                });
                return chain;
            });
        }).finally(function () { replaying = false; render(); });
    }

    /* ---------- UI ---------- */
    var card = document.getElementById('rq-card');
    var noteBox = document.getElementById('rq-note');
    function render() {
        qAll().then(function (items) {
            if (!card) return;
            card.hidden = items.length === 0;
            var t = card.querySelector('[data-rq-text]');
            if (t) t.textContent = STR.pending.replace(':n', items.length);
        }).catch(function () {});
    }
    var noteTimer = null;
    function note(msg) {
        if (!noteBox) return;
        noteBox.textContent = msg;
        noteBox.hidden = false;
        clearTimeout(noteTimer);
        noteTimer = setTimeout(function () { noteBox.hidden = true; }, 6000);
    }

    /* ---------- offline submit interception ---------- */
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || !f.action || new URL(f.action).pathname !== POST_PATH) return;
        if (navigator.onLine || !window.indexedDB) return; // normal path

        e.preventDefault();
        e.stopPropagation();
        qAdd(collectItem(f)).then(function () {
            note(STR.saved);
            render();
            if ('serviceWorker' in navigator && 'SyncManager' in window) {
                navigator.serviceWorker.ready.then(function (reg) {
                    reg.sync.register('report-queue').catch(function () {});
                }).catch(function () {});
            }
        }).catch(function () {});
    }, true);

    /* ---------- boot ---------- */
    window.addEventListener('online', function () { replay(); });
    if (card && card.querySelector('[data-rq-send]')) {
        card.querySelector('[data-rq-send]').addEventListener('click', function () { replay(); });
        card.querySelector('[data-rq-clear]').addEventListener('click', function () {
            qAll().then(function (items) {
                var chain = Promise.resolve();
                items.forEach(function (it) { chain = chain.then(function () { return qDel(it.id); }); });
                return chain;
            }).then(render);
        });
    }
    render();
    setTimeout(replay, 2500); // quietly flush anything queued from a previous session
})();
</script>
