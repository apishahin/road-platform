{{--
    Client-side photo compression + camera capture helper.
    Include once on any page with photo upload inputs.

    Mark inputs with:
      data-compress            → compress each picked image in the browser
                                 (max 1600px, JPEG q0.82) BEFORE upload
      data-compress-max="3"    → optional cap on file count
      data-camera-for="ID"     → camera-only input whose shot is merged into
                                 the main multi-file input with that id

    Zero-risk: every step is wrapped — if anything fails the ORIGINAL file is
    uploaded unchanged, and forms still submit normally without JS.
--}}
<script>
(function () {
    'use strict';

    var pending = 0;

    function compressFile(file) {
        return new Promise(function (resolve) {
            if (!file || !/^image\//.test(file.type)) { resolve(file); return; }
            // Small JPEGs need no work
            if (file.type === 'image/jpeg' && file.size <= 600 * 1024) { resolve(file); return; }

            var finish = function (f) { resolve(f || file); };
            var bmpPromise = window.createImageBitmap
                ? createImageBitmap(file)
                : new Promise(function (res, rej) {
                      var url = URL.createObjectURL(file);
                      var img = new Image();
                      img.onload = function () { res(img); };
                      img.onerror = rej;
                      img.src = url;
                  });

            bmpPromise.then(function (bmp) {
                var w = bmp.width, h = bmp.height;
                if (!w || !h) { finish(file); return; }
                var MAX = 1600;
                var sc = Math.min(1, MAX / Math.max(w, h));
                w = Math.max(1, Math.round(w * sc));
                h = Math.max(1, Math.round(h * sc));
                var cv = document.createElement('canvas');
                cv.width = w; cv.height = h;
                var ctx = cv.getContext('2d');
                if (!ctx) { finish(file); return; }
                ctx.drawImage(bmp, 0, 0, w, h);
                if (bmp.close) { try { bmp.close(); } catch (e) {} }
                cv.toBlob(function (blob) {
                    if (!blob || blob.size >= file.size) { finish(file); return; }
                    var name = (file.name || 'photo').replace(/\.[a-z0-9]+$/i, '') + '.jpg';
                    try {
                        finish(new File([blob], name, { type: 'image/jpeg' }));
                    } catch (e) { finish(file); }
                }, 'image/jpeg', 0.82);
            }).catch(function () { finish(file); });
        });
    }

    // ── Compress files picked in any [data-compress] input ──────────────
    document.addEventListener('change', function (e) {
        var inp = e.target;
        if (!(inp instanceof HTMLInputElement) || inp.type !== 'file') { return; }
        if (inp.dataset.skip === '1') { delete inp.dataset.skip; return; }
        if (!inp.hasAttribute('data-compress')) { return; }
        if (!inp.files || !inp.files.length) { return; }

        pending++;
        var files = Array.prototype.slice.call(inp.files);
        var cap = parseInt(inp.dataset.compressMax || '0', 10);

        Promise.all(files.map(compressFile)).then(function (out) {
            try {
                var dt = new DataTransfer();
                var n = 0;
                out.forEach(function (f) {
                    if (cap && n >= cap) { return; }
                    dt.items.add(f); n++;
                });
                inp.files = dt.files;
            } catch (err) { /* keep originals */ }
            pending--;
        }).catch(function () { pending--; });
    }, true);

    // ── Camera button: merge the shot into the main multi-file input ────
    document.addEventListener('change', function (e) {
        var cam = e.target;
        if (!(cam instanceof HTMLInputElement) || !cam.hasAttribute('data-camera-for')) { return; }
        if (!cam.files || !cam.files.length) { return; }
        var main = document.getElementById(cam.dataset.cameraFor);
        if (!main) { return; }

        pending++;
        compressFile(cam.files[0]).then(function (f) {
            try {
                var dt = new DataTransfer();
                var cap = parseInt(main.dataset.compressMax || '0', 10);
                var cur = Array.prototype.slice.call(main.files || []);
                cur.push(f);
                if (cap) { cur = cur.slice(0, cap); }
                cur.forEach(function (x) { dt.items.add(x); });
                main.dataset.skip = '1';              // don't recompress our own merge
                main.files = dt.files;
                main.dispatchEvent(new Event('change', { bubbles: true })); // refresh Alpine names
            } catch (err) { /* ignore */ }
            cam.value = '';
            pending--;
        }).catch(function () { pending--; });
    }, true);

    // ── Never submit while a compression is still running ───────────────
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) { return; }
        if (pending === 0 || form.dataset.rpWait === '1') { return; }
        if (!form.querySelector('[data-compress],[data-camera-for]')) { return; }
        e.preventDefault();
        var t = setInterval(function () {
            if (pending === 0) {
                clearInterval(t);
                form.dataset.rpWait = '1';
                if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
            }
        }, 100);
    }, true);
})();
</script>
