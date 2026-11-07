@extends('layouts.app')

@section('title', $issue->title)
@section('description', $explanation)

@push('head')
    <meta property="og:url" content="{{ url()->current() }}">
@endpush

@section('content')
<div class="mx-auto max-w-6xl px-4 py-6 sm:py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-slate-500">
        <a href="{{ url('/') }}" class="hover:text-slate-800">{{ __('issue.page.home') }}</a>
        <span class="mx-1">/</span>
        <a href="{{ url('/issues') }}" class="hover:text-slate-800">{{ __('issue.page.all_issues') }}</a>
    </nav>

    {{-- Notices for non-active issues --}}
    @if ($issue->disposition === 'duplicate')
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            {{ __('issue.page.duplicate_notice') }}
            @if ($issue->duplicateOf)
                <a href="{{ url('/issues/' . $issue->duplicateOf->public_id) }}" class="font-semibold underline">{{ __('issue.page.view_main') }}</a>
            @endif
        </div>
    @elseif ($issue->disposition === 'rejected')
        <div class="mt-4 rounded-lg border border-slate-300 bg-slate-100 p-4 text-sm text-slate-800">
            {{ __('issue.page.rejected_notice') }}
        </div>
    @elseif ($issue->disposition === 'referred')
        <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
            {{ __('issue.page.referred_notice') }}
        </div>
    @endif

    <div class="mt-4 grid gap-6 lg:grid-cols-3">

        {{-- ═════════ Main column ═════════ --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Header --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                <x-status-badge :issue="$issue" />

                <h1 class="mt-3 text-2xl font-bold leading-snug text-slate-900 sm:text-3xl">{{ $issue->title }}</h1>
                <p class="mt-3 text-slate-600">{{ $explanation }}</p>

                <dl class="mt-6 grid gap-4 border-t border-slate-100 pt-5 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.category') }}</dt>
                        <dd class="font-medium text-slate-900">{{ $issue->category?->name() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.road') }}</dt>
                        <dd class="font-medium text-slate-900">{{ $issue->road_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.landmark') }}</dt>
                        <dd class="font-medium text-slate-900">{{ $issue->landmark ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.area') }}</dt>
                        <dd class="font-medium text-slate-900">
                            @if ($issue->adminArea)
                                {{ app()->getLocale() === 'bn' ? $issue->adminArea->name_bn : ($issue->adminArea->name_en ?: $issue->adminArea->name_bn) }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.verification') }}</dt>
                        <dd class="font-medium text-slate-900">{{ \App\Support\StatusLabel::verification($issue->verification_status) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.reports') }}</dt>
                        <dd class="font-medium text-slate-900">{{ $reportsCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.first_reported') }}</dt>
                        <dd class="font-medium text-slate-900">{{ $firstAt }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.last_activity') }}</dt>
                        <dd class="font-medium text-slate-900">{{ $lastAt }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Timeline --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 class="text-lg font-bold text-slate-900">{{ __('issue.page.timeline') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('issue.page.timeline_note') }}</p>

                @if ($timeline->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">{{ __('issue.page.no_timeline') }}</p>
                @else
                    <ol class="mt-5 space-y-6 border-s border-slate-200 ps-6">
                        @foreach ($timeline as $row)
                            <li class="relative">
                                <span class="absolute -start-[30px] top-1.5 size-3 rounded-full bg-brand-600 ring-4 ring-white"></span>

                                <div class="flex flex-wrap gap-x-2 text-xs text-slate-500">
                                    <time>{{ $row['at'] }}</time>
                                    <span>·</span>
                                    <span>{{ $row['source'] }}</span>
                                    <span>·</span>
                                    <span>{{ $row['actor'] }}</span>
                                </div>

                                <p class="mt-1 font-semibold text-slate-900">{{ $row['label'] }}</p>
                                <p class="mt-0.5 text-sm text-slate-600">{{ $row['reason'] }}</p>

                                @if ($row['url'])
                                    <a href="{{ $row['url'] }}" target="_blank" rel="nofollow noopener noreferrer"
                                       class="mt-1 inline-block text-sm text-brand-700 underline">{{ __('issue.page.source_link') }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            {{-- Reports --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 class="text-lg font-bold text-slate-900">{{ __('issue.page.reports_title') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('issue.page.reports_note') }}</p>

                @forelse ($reports as $report)
                    <div class="mt-4 rounded-lg border border-slate-100 p-4">
                        <p class="whitespace-pre-line text-sm text-slate-800">{{ $report->description }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ \App\Support\IssuePresenter::num($report->created_at?->diffForHumans() ?? '') }}</p>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-slate-500">{{ __('issue.page.no_reports') }}</p>
                @endforelse
            </section>

            {{-- Photos --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 class="text-lg font-bold text-slate-900">{{ __('issue.page.photos') }}</h2>

                @if ($photos->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">{{ __('issue.page.no_photos') }}</p>
                @else
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($photos as $photo)
                            <a data-lightbox="issue" href="{{ $photo->url() }}" target="_blank" rel="noopener"><img src="{{ $photo->url() }}"
                                 alt="{{ $issue->title }}"
                                 loading="lazy"
                                 class="h-32 w-full rounded-lg object-cover"></a>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- ═════════ Sidebar ═════════ --}}
        <aside class="space-y-6">

            {{-- Map --}}
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <h2 class="px-4 pt-4 font-semibold text-slate-900">{{ __('issue.page.map') }}</h2>
                <div class="relative mt-3">
                <iframe src="{{ $mapEmbed }}" title="{{ $issue->title }}" loading="lazy"
                        class="h-56 w-full border-0"></iframe>
                <a data-map data-lat="{{ $issue->lat }}" data-lng="{{ $issue->lng }}"
                   href="https://www.openstreetmap.org/?mlat={{ $issue->lat }}&mlon={{ $issue->lng }}#map=17/{{ $issue->lat }}/{{ $issue->lng }}"
                   style="position:absolute;top:0;left:0;right:0;bottom:0;display:flex;align-items:flex-end;justify-content:flex-end;padding:8px;text-decoration:none;">
                    <span style="background:rgba(255,255,255,.92);color:#0369a1;border-radius:6px;padding:4px 8px;font-size:12px;font-weight:600;box-shadow:0 1px 3px rgba(0,0,0,.3);">{{ __('issue.page.open_map') }}</span>
                </a>
            </div>
                <div class="flex flex-wrap gap-4 p-4 text-sm">
                    <a data-map data-lat="{{ $issue->lat }}" data-lng="{{ $issue->lng }}" href="https://www.openstreetmap.org/?mlat={{ $issue->lat }}&mlon={{ $issue->lng }}#map=17/{{ $issue->lat }}/{{ $issue->lng }}"
                       target="_blank" rel="noopener" class="text-brand-700 underline">{{ __('issue.page.open_map') }}</a>
                    <a href="https://www.google.com/maps?q={{ $issue->lat }},{{ $issue->lng }}"
                       target="_blank" rel="noopener" class="text-brand-700 underline">{{ __('issue.page.directions') }}</a>
                </div>
            </section>

            {{-- Authority --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="font-semibold text-slate-900">{{ __('issue.page.authority') }}</h2>

                @forelse ($authorities as $link)
                    @php
                        $auth = $link->authority;
                        $authName = app()->getLocale() === 'bn' ? $auth->name_bn : ($auth->name_en ?: $auth->name_bn);
                    @endphp
                    <div class="mt-3 rounded-lg border border-slate-200 p-3">
                        <p class="font-medium text-slate-900">{{ $authName }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('issue.authority_state.' . $link->state) }}</p>
                        <p class="mt-2 text-sm text-slate-600">
                            <span class="font-medium">{{ __('issue.page.basis') }}:</span> {{ $link->basis }}
                        </p>
                    </div>
                @empty
                    <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                        {{ __('issue.page.authority_unverified') }}
                    </p>
                    <p class="mt-2 text-xs text-slate-500">{{ __('issue.page.authority_note') }}</p>
                @endforelse
            </section>

            {{-- Share --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5"
                     x-data='{ copied: false, url: @js(url()->current()), title: @js($issue->title) }'>
                <h2 class="font-semibold text-slate-900">{{ __('issue.page.share') }}</h2>
                <button type="button"
                        class="mt-3 w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50"
                        x-on:click="navigator.share ? navigator.share({ title: title, url: url }) : navigator.clipboard.writeText(url).then(() => copied = true)">
                    {{ __('issue.page.share') }}
                </button>
                <p x-show="copied" x-cloak class="mt-2 text-xs text-green-700">{{ __('issue.page.copied') }}</p>
            </section>

            {{-- Confirm (needs login, coming later) --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="font-semibold text-slate-900">{{ __('issue.page.confirm') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('issue.page.confirm_note') }}</p>
                <a href="{{ url('/login') }}"
                   class="mt-3 inline-block w-full rounded-lg bg-brand-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-brand-700">
                    {{ __('issue.page.login_to_confirm') }}
                </a>
            </section>

            {{-- Correction path --}}
            <p class="px-1 text-sm text-slate-500">
                {{ __('issue.page.wrong_info') }}
                <span class="italic">{{ __('issue.page.coming_soon') }}</span>
            </p>
        </aside>
    </div>
</div>
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
    var arrow = 'position:absolute;top:50%;transform:translateY(-50%);color:#fff;font-size:56px;line-height:1;padding:8px 16px;background:none;border:0;cursor:pointer;';
    lb.innerHTML = '<img alt="" style="max-width:92vw;max-height:88vh;border-radius:8px;">'
      + btn('data-x', 'position:absolute;top:10px;right:18px;color:#fff;font-size:40px;line-height:1;background:none;border:0;cursor:pointer;', '&times;')
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
        + btn('data-x', 'font-size:30px;line-height:1;padding:0 8px;background:none;border:0;cursor:pointer;', '&times;')
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