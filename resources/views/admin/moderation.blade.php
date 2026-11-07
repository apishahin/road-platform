@extends('layouts.app')

@section('title', __('report.mod_title'))

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('report.mod_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('report.mod_pending', ['count' => $pendingTotal]) }}</p>

    <div class="mt-6 space-y-4">
        @forelse ($reports as $r)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $r->issue->title }}</p>
                        <p class="text-xs text-slate-500">{{ $r->issue->category?->name() }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $r->issue->published_at === null ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">
                        {{ $r->issue->published_at === null ? __('report.mod_new_issue') : __('report.mod_existing_issue') }}
                    </span>
                </div>

                <p class="mt-3 whitespace-pre-line text-sm text-slate-800">{{ $r->description }}</p>

                <p class="mt-2 text-xs text-slate-500">
                    {{ $r->created_at?->diffForHumans() }} ·
                    <a class="text-brand-700 underline" target="_blank" rel="noopener"
                       data-map data-lat="{{ $r->lat }}" data-lng="{{ $r->lng }}" href="https://www.openstreetmap.org/?mlat={{ $r->lat }}&mlon={{ $r->lng }}#map=18/{{ $r->lat }}/{{ $r->lng }}">{{ __('report.mod_open_map') }}</a>
                </p>

                <div class="mt-3 flex flex-wrap gap-2">
                    @forelse ($r->evidence as $ev)
                        @once
                            <script>
                                document.addEventListener('click', function (e) {
                                    var b = e.target.closest('[data-hide-ev]');
                                    if (!b) { return; }
                                    e.preventDefault();
                                    if (!confirm(b.getAttribute('data-confirm'))) { return; }
                                    var box = b.closest('[data-ev-box]');
                                    var sel = box ? box.querySelector('select') : null;
                                    var f = document.createElement('form');
                                    f.method = 'POST';
                                    f.action = b.getAttribute('data-hide-ev');
                                    [['_token', b.getAttribute('data-token')], ['reason', sel ? sel.value : 'other']].forEach(function (p) {
                                        var i = document.createElement('input');
                                        i.type = 'hidden'; i.name = p[0]; i.value = p[1];
                                        f.appendChild(i);
                                    });
                                    document.body.appendChild(f);
                                    f.submit();
                                });
                            </script>
                        <script>
                                (function () {
                                    var ov = null, onKey = null;
                                    function closeBlur() {
                                        if (ov) { ov.parentNode.removeChild(ov); ov = null; }
                                        if (onKey) { document.removeEventListener('keydown', onKey); onKey = null; }
                                    }
                                    function mkBtn(label, bg) {
                                        var x = document.createElement('button');
                                        x.type = 'button';
                                        x.textContent = label;
                                        x.style.cssText = 'background:' + bg + ';color:#fff;border:0;border-radius:8px;padding:8px 16px;font-size:14px;font-weight:600;cursor:pointer;';
                                        return x;
                                    }
                                    document.addEventListener('click', function (e) {
                                        var b = e.target.closest('[data-blur-ev]');
                                        if (!b) { return; }
                                        e.preventDefault();
                                        openBlur(b);
                                    });
                                    function openBlur(b) {
                                        closeBlur();
                                        var rects = [], boxes = [], start = null, cur = null, mode = 'blur';

                                        ov = document.createElement('div');
                                        ov.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;z-index:9999;background:rgba(0,0,0,.88);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:12px;overflow:auto;';

                                        var hint = document.createElement('p');
                                        hint.textContent = b.getAttribute('data-hint');
                                        hint.style.cssText = 'color:#fff;font-size:14px;margin:0 0 10px 0;text-align:center;max-width:640px;';

                                        var wrap = document.createElement('div');
                                        wrap.style.cssText = 'position:relative;line-height:0;';
                                        var img = document.createElement('img');
                                        img.src = b.getAttribute('data-src');
                                        img.draggable = false;
                                        img.style.cssText = 'max-width:92vw;max-height:68vh;display:block;user-select:none;-webkit-user-drag:none;';
                                        var layer = document.createElement('div');
                                        layer.style.cssText = 'position:absolute;top:0;left:0;right:0;bottom:0;cursor:crosshair;touch-action:none;';
                                        wrap.appendChild(img);
                                        wrap.appendChild(layer);

                                        function pos(ev) {
                                            var r = layer.getBoundingClientRect();
                                            return {
                                                x: Math.min(1, Math.max(0, (ev.clientX - r.left) / r.width)),
                                                y: Math.min(1, Math.max(0, (ev.clientY - r.top) / r.height))
                                            };
                                        }
                                        function mkBox() {
                                            var d = document.createElement('div');
                                            d.setAttribute('data-m', mode);
                                            d.style.cssText = mode === 'black'
                                                ? 'position:absolute;background:#000;border:2px solid #f59e0b;box-sizing:border-box;'
                                                : 'position:absolute;background:rgba(255,255,255,.10);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);border:2px dashed #fff;border-radius:6px;box-sizing:border-box;';
                                            layer.appendChild(d);
                                            return d;
                                        }
                                        function place(d, a, c) {
                                            var x = Math.min(a.x, c.x), y = Math.min(a.y, c.y);
                                            var w = Math.abs(a.x - c.x), h = Math.abs(a.y - c.y);
                                            d.style.left = (x * 100) + '%';
                                            d.style.top = (y * 100) + '%';
                                            d.style.width = (w * 100) + '%';
                                            d.style.height = (h * 100) + '%';
                                            return { x: x, y: y, w: w, h: h };
                                        }
                                        layer.addEventListener('pointerdown', function (ev) {
                                            ev.preventDefault();
                                            layer.setPointerCapture(ev.pointerId);
                                            start = pos(ev);
                                            cur = mkBox();
                                            place(cur, start, start);
                                        });
                                        layer.addEventListener('pointermove', function (ev) {
                                            if (!start) { return; }
                                            place(cur, start, pos(ev));
                                        });
                                        layer.addEventListener('pointerup', function (ev) {
                                            if (!start) { return; }
                                            var r = place(cur, start, pos(ev));
                                            if (r.w < 0.01 || r.h < 0.01) {
                                                layer.removeChild(cur);
                                            } else {
                                                r.m = cur.getAttribute('data-m');
                                                rects.push(r);
                                                boxes.push(cur);
                                            }
                                            start = null;
                                            cur = null;
                                        });
                                        layer.addEventListener('pointercancel', function () {
                                            if (cur) { layer.removeChild(cur); }
                                            start = null;
                                            cur = null;
                                        });

                                        var row = document.createElement('div');
                                        row.style.cssText = 'display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;justify-content:center;';
                                        var save = mkBtn(b.getAttribute('data-save'), '#16a34a');
                                        var undo = mkBtn(b.getAttribute('data-undo'), '#b45309');
                                        var cancel = mkBtn(b.getAttribute('data-cancel'), '#475569');
                                        var modeBtns = {};
                                        function paintModes() {
                                            Object.keys(modeBtns).forEach(function (k) {
                                                modeBtns[k].style.borderColor = (k === mode) ? '#fbbf24' : 'transparent';
                                                modeBtns[k].style.opacity = (k === mode) ? '1' : '.6';
                                            });
                                        }
                                        [['blur', b.getAttribute('data-mode-blur'), '#2563eb'], ['black', b.getAttribute('data-mode-black'), '#111827']].forEach(function (m) {
                                            var x = mkBtn(m[1], m[2]);
                                            x.style.border = '2px solid transparent';
                                            x.addEventListener('click', function () { mode = m[0]; paintModes(); });
                                            modeBtns[m[0]] = x;
                                            row.appendChild(x);
                                        });
                                        paintModes();
                                        row.appendChild(save);
                                        row.appendChild(undo);
                                        row.appendChild(cancel);

                                        undo.addEventListener('click', function () {
                                            if (!rects.length) { return; }
                                            rects.pop();
                                            layer.removeChild(boxes.pop());
                                        });
                                        cancel.addEventListener('click', closeBlur);
                                        save.addEventListener('click', function () {
                                            if (!rects.length) { return; }
                                            save.disabled = true;
                                            var f = document.createElement('form');
                                            f.method = 'POST';
                                            f.action = b.getAttribute('data-blur-ev');
                                            [['_token', b.getAttribute('data-token')], ['rects', JSON.stringify(rects)]].forEach(function (p) {
                                                var i = document.createElement('input');
                                                i.type = 'hidden';
                                                i.name = p[0];
                                                i.value = p[1];
                                                f.appendChild(i);
                                            });
                                            document.body.appendChild(f);
                                            f.submit();
                                        });

                                        onKey = function (e) { if (e.key === 'Escape') { closeBlur(); } };
                                        document.addEventListener('keydown', onKey);

                                        ov.appendChild(hint);
                                        ov.appendChild(wrap);
                                        ov.appendChild(row);
                                        document.body.appendChild(ov);
                                    }
                                })();
                            </script>
                        @endonce
                        <div class="w-28" data-ev-box>
                            <a data-lightbox="r{{ $r->id }}" href="{{ route('admin.evidence.photo', $ev) }}" target="_blank" rel="noopener">
                                <img src="{{ route('admin.evidence.photo', $ev) }}" alt="" class="h-24 w-24 rounded-lg border border-slate-200 object-cover" style="{{ $ev->hidden_at ? 'opacity:.3;filter:grayscale(1)' : '' }}">
                            </a>
                            @if ($ev->hidden_at)
                                <p class="mt-1 text-xs font-semibold text-red-700">{{ __('evmod.hidden_label') }}</p>
                            @else
                                <select class="mt-1 w-full rounded border border-slate-300 px-1 py-0.5 text-xs">
                                    @foreach (['face', 'plate', 'personal', 'other'] as $k)
                                        <option value="{{ $k }}">{{ __('evmod.reason_' . $k) }}</option>
                                    @endforeach
                                </select>
                                <button type="button"
                                        data-hide-ev="{{ route('admin.evidence.hide', $ev) }}"
                                        data-token="{{ csrf_token() }}"
                                        data-confirm="{{ __('evmod.confirm') }}"
                                        style="background:#dc2626;color:#fff;border:0;cursor:pointer" class="mt-1 w-full rounded px-2 py-1 text-xs font-semibold">{{ __('evmod.hide_btn') }}</button>
                                @if ($ev->moderation === 'pending')
                                    <button type="button"
                                            data-blur-ev="{{ route('admin.evidence.blur', $ev) }}"
                                            data-src="{{ route('admin.evidence.photo', $ev) }}"
                                            data-token="{{ csrf_token() }}"
                                            data-hint="{{ __('evblur.hint') }}"
                                            data-save="{{ __('evblur.save') }}"
                                            data-undo="{{ __('evblur.undo') }}"
                                            data-cancel="{{ __('evblur.cancel') }}"
                                            data-mode-blur="{{ __('evblur.mode_blur') }}"
                                            data-mode-black="{{ __('evblur.mode_black') }}"
                                            style="background:#334155;color:#fff;border:0;cursor:pointer"
                                            class="mt-1 w-full rounded px-2 py-1 text-xs font-semibold">{{ __('evblur.btn') }}</button>
                                @endif
                                @if ($ev->private_path === null)
                                    <p class="mt-1 text-xs font-semibold text-emerald-700">{{ __('evblur.done_label') }}</p>
                                @endif
                            @endif
                        </div>
                    @empty
                        <span class="text-xs text-slate-400">{{ __('report.mod_no_photos') }}</span>
                    @endforelse
                </div>

                @php
    $areaList ??= \App\Models\AdminArea::whereIn('level', ['division', 'district', 'upazila'])->orderBy('id')->get()->map(fn ($a) => [
        'id' => $a->id,
        'parent_id' => $a->parent_id,
        'label' => app()->getLocale() === 'bn' ? ($a->name_bn ?: $a->name_en) : ($a->name_en ?: $a->name_bn),
    ])->all();
    $issueArea = $r->issue?->adminArea;
    $seed = [];
    if ($r->suggested_area_id) {
        $n = \App\Models\AdminArea::find($r->suggested_area_id);
        while ($n) {
            $seed[$n->level] = $n->id;
            $n = $n->parent_id ? \App\Models\AdminArea::find($n->parent_id) : null;
        }
    }
@endphp

                @if ($r->issue && ($r->issue->road_name || $r->issue->landmark))
                    <div class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-700">
                        <p class="font-semibold">{{ __('areas.claim_title') }}</p>
                        @if ($r->issue->road_name)
                            <p>{{ __('areas.claim_road') }}: {{ $r->issue->road_name }}</p>
                        @endif
                        @if ($r->issue->landmark)
                            <p>{{ __('areas.claim_landmark') }}: {{ $r->issue->landmark }}</p>
                        @endif
                    </div>
                @endif

                <div class="mt-4 flex flex-wrap items-end gap-2">
                    <form method="POST" action="{{ route('admin.moderation.approve', $r) }}"
                          x-data="areaPicker(window.__areas, @js(app()->getLocale()), @js($seed))"
                          class="flex flex-wrap items-end gap-2">
                        @csrf
                        @if ($issueArea)
                            <p class="text-xs text-slate-600">
                                {{ __('areas.already_set') }}
                                {{ app()->getLocale() === 'bn' ? ($issueArea->name_bn ?: $issueArea->name_en) : ($issueArea->name_en ?: $issueArea->name_bn) }}
                            </p>
                        @else
                            <div class="w-full text-xs text-slate-600">
                                <p class="font-semibold">{{ __('areas.pick_area') }}</p>
                                <p>{{ __('areas.pick_hint') }}</p>
@if (! empty($seed))
<p class="mt-1 font-medium text-amber-700">{{ __('suggest.mod_note') }}</p>
@endif
                            </div>
                            <input type="hidden" name="area_id" :value="areaValue" :disabled="!areaValue">
                            <select x-model="division" @change="district = ''; upazila = ''"
                                    aria-label="{{ __('areas.division') }}"
                                    class="rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm">
                                <option value="">{{ __('areas.all_divisions') }}</option>
                                <template x-for="a in roots()" :key="a.id">
                                    <option :value="String(a.id)" x-text="a.label"></option>
                                </template>
                            </select>
                            <select x-model="district" @change="upazila = ''" :disabled="!division"
                                    aria-label="{{ __('areas.district') }}"
                                    class="rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm disabled:bg-slate-100">
                                <option value="">{{ __('areas.all_districts') }}</option>
                                <template x-for="a in children(division)" :key="a.id">
                                    <option :value="String(a.id)" x-text="a.label"></option>
                                </template>
                            </select>
                            <select x-show="children(district).length > 0" x-model="upazila"
                                    aria-label="{{ __('areas.upazila') }}"
                                    class="rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm">
                                <option value="">{{ __('areas.all_upazilas') }}</option>
                                <template x-for="a in children(district)" :key="a.id">
                                    <option :value="String(a.id)" x-text="a.label"></option>
                                </template>
                            </select>
<select x-show="unions.length > 0" x-model="union"
        aria-label="{{ __('areas.union') }}"
        class="rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm">
    <option value="">{{ __('areas.all_unions') }}</option>
    <template x-for="a in unions" :key="a.id">
        <option :value="String(a.id)" x-text="a.label"></option>
    </template>
</select>
                        @endif
                        <button class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">{{ __('report.mod_approve') }}</button>
        <button form="hide-{{ $r->id }}" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">{{ __('report.mod_hide') }}</button>
                    </form>
                    <form id="hide-{{ $r->id }}" method="POST" action="{{ route('admin.moderation.hide', $r) }}" class="hidden">
    @csrf
</form>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600">
                {{ __('report.mod_empty') }}
            </div>
        @endforelse
    </div>
</div>
@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        window.__areas = @js($areaList ?? []);
        window.__areaChildrenUrl = @js(url('/admin/areas'));
Alpine.data('areaPicker', (areas, locale, seed = {}) => ({
            areas: areas,
            division: '',
            pendingUnion: (seed && seed.union) ? seed.union : '',
            district: '',
            upazila: '',
            union: '',
            unions: [],
            init() {
            if (seed && seed.division) {
                this.$nextTick(async () => {
                    this.division = String(seed.division);
                    await this.$nextTick();
                    if (seed.district) { this.district = String(seed.district); await this.$nextTick(); }
                    if (seed.upazila) { this.upazila = String(seed.upazila); }
                });
            }
                this.$watch('upazila', (v) => {
                    this.union = '';
                    this.unions = [];
                    if (!v) { return; }
                    fetch(window.__areaChildrenUrl + '/' + v + '/children', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then((r) => r.ok ? r.json() : [])
                        .then((d) => { if (String(this.upazila) === String(v)) { this.unions = d; if (this.pendingUnion) { const u = String(this.pendingUnion); this.pendingUnion = ''; this.$nextTick(() => { this.union = u; }); } } })
                        .catch(() => {});
                });
            },
            sorted(list) {
                return list.slice().sort((a, b) => a.label.localeCompare(b.label, locale));
            },
            roots() {
                return this.sorted(this.areas.filter(a => !a.parent_id));
            },
            children(parentId) {
                if (!parentId) return [];
                return this.sorted(this.areas.filter(a => String(a.parent_id) === String(parentId)));
            },
            get areaValue() {
                return this.union || this.upazila || this.district || this.division || '';
            },
        }));
    });
</script>
@endpush
@push('scripts')
<script>
(function () {
  var lb = null, items = [], idx = 0;
  var mb = null, map = null, marker = null;
  function btn(attr, css, html) {
    return '<button type="button" ' + attr + ' style="' + css + '">' + html + '</button>';
  }
  function lbBuild() {
    lb = document.createElement('div');
    lb.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);display:none;align-items:center;justify-content:center;';
    var arrow = 'position:absolute;top:50%;transform:translateY(-50%);color:#fff;font-size:56px;line-height:1;padding:8px 16px;';
    lb.innerHTML = '<div style="position:relative;display:inline-block;"><img alt="" style="display:block;max-width:92vw;max-height:88vh;border-radius:8px;">'
      + btn('data-x', 'position:absolute;top:-14px;right:-14px;width:32px;height:32px;border-radius:50%;background:#fff;color:#111;border:0;cursor:pointer;font-size:22px;line-height:32px;padding:0;box-shadow:0 1px 6px rgba(0,0,0,.5);', '&times;')
      + '</div>'
      + btn('data-p', arrow + 'left:6px;', '&lsaquo;')
      + btn('data-n', arrow + 'right:6px;', '&rsaquo;');
    document.body.appendChild(lb);
    lb.addEventListener('click', function (e) {
      if (e.target === lb || e.target.closest('[data-x]')) { lbClose(); }
      else if (e.target.closest('[data-p]')) { lbShow(idx - 1); }
      else if (e.target.closest('[data-n]')) { lbShow(idx + 1); }
    });
  }
  function lbShow(i) {
    idx = (i + items.length) % items.length;
    lb.querySelector('img').src = items[idx].href;
    var d = items.length > 1 ? 'block' : 'none';
    lb.querySelector('[data-p]').style.display = d;
    lb.querySelector('[data-n]').style.display = d;
  }
  function lbOpen(list, i) {
    if (!lb) { lbBuild(); }
    items = list; lb.style.display = 'flex'; document.body.style.overflow = 'hidden'; lbShow(i);
  }
  function lbClose() {
    lb.style.display = 'none'; lb.querySelector('img').src = ''; document.body.style.overflow = '';
  }
  function ensureLeaflet(cb) {
    if (!document.getElementById('lf-css')) {
      var l = document.createElement('link');
      l.id = 'lf-css'; l.rel = 'stylesheet';
      l.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
      document.head.appendChild(l);
    }
    if (window.L && window.L.map) { cb(); return; }
    var s = document.getElementById('lf-js');
    if (!s) {
      s = document.createElement('script');
      s.id = 'lf-js'; s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
      document.head.appendChild(s);
    }
    s.addEventListener('load', cb);
  }
  function mapClose() { mb.style.display = 'none'; }
  function mapOpen(lat, lng, href, label) {
    if (!mb) {
      mb = document.createElement('div');
      mb.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.7);display:none;align-items:center;justify-content:center;';
      mb.innerHTML = '<div style="background:#fff;border-radius:12px;width:min(92vw,720px);padding:12px;">'
        + '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">'
        + '<a data-o target="_blank" rel="noopener" style="color:#0369a1;text-decoration:underline;"></a>'
        + btn('data-x', 'font-size:30px;line-height:1;padding:0 8px;', '&times;')
        + '</div><div data-m style="height:60vh;min-height:320px;border-radius:8px;"></div></div>';
      document.body.appendChild(mb);
      mb.addEventListener('click', function (e) {
        if (e.target === mb || e.target.closest('[data-x]')) { mapClose(); }
      });
    }
    var o = mb.querySelector('[data-o]');
    o.href = href; o.textContent = label;
    mb.style.display = 'flex';
    ensureLeaflet(function () {
      var el = mb.querySelector('[data-m]');
      if (!map) {
        map = L.map(el);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
        marker = L.marker([lat, lng]).addTo(map);
      } else {
        marker.setLatLng([lat, lng]);
      }
      map.setView([lat, lng], 17);
      setTimeout(function () { map.invalidateSize(); }, 60);
    });
  }
  document.addEventListener('click', function (e) {
    var p = e.target.closest('a[data-lightbox]');
    if (p) {
      e.preventDefault();
      var list = Array.prototype.slice.call(document.querySelectorAll('a[data-lightbox="' + p.getAttribute('data-lightbox') + '"]'));
      lbOpen(list, list.indexOf(p));
      return;
    }
    var m = e.target.closest('a[data-map]');
    if (m) {
      var lat = parseFloat(m.getAttribute('data-lat')), lng = parseFloat(m.getAttribute('data-lng'));
      if (isNaN(lat) || isNaN(lng)) { return; }
      e.preventDefault();
      mapOpen(lat, lng, m.href, m.textContent.trim());
    }
  });
  document.addEventListener('keydown', function (e) {
    if (lb && lb.style.display === 'flex') {
      if (e.key === 'Escape') { lbClose(); }
      else if (e.key === 'ArrowLeft') { lbShow(idx - 1); }
      else if (e.key === 'ArrowRight') { lbShow(idx + 1); }
    } else if (mb && mb.style.display === 'flex' && e.key === 'Escape') { mapClose(); }
  });
})();
</script>
@endpush
@endsection