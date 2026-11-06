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
                        <a data-lightbox="r{{ $r->id }}" href="{{ route('admin.evidence.photo', $ev) }}" target="_blank" rel="noopener">
                            <img src="{{ route('admin.evidence.photo', $ev) }}" alt="" class="h-24 w-24 rounded-lg border border-slate-200 object-cover">
                        </a>
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
                          x-data="areaPicker(window.__areas, @js(app()->getLocale()))"
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
Alpine.data('areaPicker', (areas, locale) => ({
            areas: areas,
            division: '',
            district: '',
            upazila: '',
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
                return this.upazila || this.district || this.division || '';
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
    lb.innerHTML = '<img alt="" style="max-width:92vw;max-height:88vh;border-radius:8px;">'
      + btn('data-x', 'position:absolute;top:10px;right:18px;color:#fff;font-size:40px;line-height:1;', '&times;')
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