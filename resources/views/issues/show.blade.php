@extends('layouts.app')

@section('title', $issue->title)
@section('description', $explanation)
@if ($photos->isNotEmpty())
@section('og_image', url($photos->first()->url()))
@endif

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

                {{-- Follow this issue --}}
                <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4" data-follow-box>
                    @auth
                        <form method="POST" action="{{ route('issues.follow', $issue->public_id) }}">
                            @csrf
                            <button type="submit"
                                    style="display:inline-flex;align-items:center;gap:6px;border-radius:10px;padding:8px 14px;font-size:13px;font-weight:700;{{ $isFollowing ? 'background:#f1f5f9;color:#334155;border:1px solid #e2e8f0;' : 'background:#16a34a;color:#fff;border:1px solid #16a34a;' }}">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 18a2.5 2.5 0 0 1-5 0m9.2-2.6H5.3a1.2 1.2 0 0 1-1-1.9l1.4-1.8V8.4a6.3 6.3 0 0 1 12.6 0v3.1l1.4 1.8a1.2 1.2 0 0 1-1 1.9Z"/></svg>
                                {{ $isFollowing ? __('issues.follow_following') : __('issues.follow_btn') }}
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}"
                           style="display:inline-flex;align-items:center;gap:6px;border-radius:10px;padding:8px 14px;font-size:13px;font-weight:700;background:#16a34a;color:#fff;">
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 18a2.5 2.5 0 0 1-5 0m9.2-2.6H5.3a1.2 1.2 0 0 1-1-1.9l1.4-1.8V8.4a6.3 6.3 0 0 1 12.6 0v3.1l1.4 1.8a1.2 1.2 0 0 1-1 1.9Z"/></svg>
                            {{ __('issues.follow_btn') }}
                        </a>
                    @endauth
                    <span style="font-size:12px;color:#94a3b8;">{{ __('issues.follow_hint', ['n' => \App\Support\IssuePresenter::num($followersCount)]) }}</span>
                </div>
            </section>

            @if (($issueView ?? 'simple') === 'simple' && isset($glance))
{{-- At a glance (simple view) --}}
@php
    $gSteps = [
        [__('issues.glance_s1'), true, $firstAt],
        [__('issues.glance_s2'), $glance['notified_date'] !== null, $glance['notified_date']],
        [__('issues.glance_s3'), ($glance['fixed'] > 0 || $glance['claim_date'] !== null), $glance['claim_date']],
        [__('issues.glance_s4'), $glance['repaired'], $glance['repaired_date']],
    ];
    $gClaims = $glance['fixed'] + $glance['still'] + $glance['worse'];
    $gNum = fn ($n) => \App\Support\IssuePresenter::num($n);
@endphp
<section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
    <h2 class="text-lg font-bold text-slate-900">{{ __('issues.glance_title') }}</h2>

    <div style="margin-top:12px;display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;">
        @foreach ($gSteps as $st)
            <div style="border-radius:10px;padding:10px;border:1px solid {{ $st[1] ? '#16a34a' : '#cbd5e1' }};background:{{ $st[1] ? '#f0fdf4' : '#f8fafc' }};">
                <p style="margin:0;font-size:13px;font-weight:700;color:{{ $st[1] ? '#15803d' : '#64748b' }};">{{ $st[1] ? '✓' : '○' }} {{ $st[0] }}</p>
                <p style="margin:4px 0 0;font-size:12px;color:#475569;">{{ $st[1] ? ($st[2] ?: '') : __('issues.glance_pending') }}</p>
            </div>
        @endforeach
    </div>

    <ul style="margin:14px 0 0;padding:0;list-style:none;font-size:14px;line-height:1.7;color:#334155;">
        <li>• {{ __('issues.glance_reports', ['count' => $reportsCount]) }}</li>
@php
    $gWho = [];
    foreach (['citizen', 'volunteer', 'moderator'] as $gr) {
        $gn = (int) ($glance['who']['reporters'][$gr] ?? 0);
        if ($gn > 0) { $gWho[] = __('issues.glance_role_' . $gr, ['count' => $gNum($gn)]); }
    }
    $gSee = [];
    foreach (['volunteer', 'moderator'] as $gr) {
        $gn = (int) ($glance['who']['site']['roles'][$gr] ?? 0);
        if ($gn > 0) { $gSee[] = __('issues.glance_role_' . $gr, ['count' => $gNum($gn)]); }
    }
@endphp
@if ($gWho !== [])
        <li>• {{ __('issues.glance_reporters', ['list' => implode(', ', $gWho)]) }}</li>
@endif
@if ($gSee !== [])
        <li>• {{ __('issues.glance_site_seen', ['who' => implode(', ', $gSee), 'date' => $glance['who']['site']['date'] ?? '-']) }}</li>
@endif

        <li>•
            @if ($glance['notified_date'] !== null)
                {{ __('issues.glance_notified_on', ['date' => $glance['notified_date']]) }}
                @if ($glance['notified_channel'] !== '')
                    {{ __('issues.glance_via', ['how' => $glance['notified_channel']]) }}
                @endif

                @if ($glance['notified_position'] !== '')
                    {{ __('issues.glance_to', ['who' => $glance['notified_position']]) }}
                @endif
            @else
                {{ __('issues.glance_not_notified') }}
            @endif
        </li>

        @if ($gClaims === 0)
            <li>• {{ __('issues.glance_no_claims') }}</li>
        @else
            @if ($glance['fixed'] > 0)
                <li>• {{ __('issues.glance_fixed_claims', ['count' => $gNum($glance['fixed'])]) }}</li>
            @endif

            @if ($glance['still'] > 0)
                <li>• {{ __('issues.glance_still_claims', ['count' => $gNum($glance['still'])]) }}</li>
            @endif

            @if ($glance['worse'] > 0)
                <li>• {{ __('issues.glance_worse_claims', ['count' => $gNum($glance['worse'])]) }}</li>
            @endif

            @if (! $glance['repaired'])
                <li style="color:#92400e;">{{ __('issues.glance_claim_note') }}</li>
            @endif
        @endif

        <li>•
            @if ($glance['repaired'])
                {{ __('issues.glance_verified', ['date' => $glance['repaired_date'] ?? '-']) }}
            @else
                {{ __('issues.glance_not_verified') }}
            @endif
        </li>
    </ul>
</section>
@endif

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

            @include('issues._update-photos-gallery')
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

            @if (($issueView ?? 'simple') === 'detailed')
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

            @if (!empty($notified) && count($notified) > 0)
                <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                    <p class="font-medium text-slate-900">{{ __('issuenotified.title') }}</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5">
                        @foreach ($notified as $n)
                            <li>{{ $n['date'] }}@if ($n['position'] !== ''), {{ $n['position'] }}@endif</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            </section>

            @endif

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

            @auth
@if (in_array($issue->disposition, ['active', 'reopened'], true))
@if ($glance['canVerify'] ?? false)
{{-- Site visit form (volunteer / moderator for this area) --}}
<section id="verify" class="rounded-xl border border-amber-300 bg-amber-50 p-5">
    <h2 class="font-semibold text-slate-900">{{ __('issues.verify_title') }}</h2>
    <p class="mt-1 text-sm text-slate-700">{{ __('issues.verify_note') }}</p>

    @if (($glance['fixed'] ?? 0) > 0)
        <p class="mt-2 text-sm font-medium text-amber-900">{{ __('issues.verify_claims', ['count' => \App\Support\IssuePresenter::num($glance['fixed'])]) }}</p>
    @endif

    @if (session('verify_done'))
        <p class="mt-2 rounded-lg bg-green-50 p-2 text-sm text-green-800">{{ __('issues.verify_done') }}</p>
    @endif

    @if ($errors->any())
        <ul class="mt-2 list-disc pl-5 text-sm text-red-700">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ url('/issues/' . $issue->public_id . '/verify') }}" enctype="multipart/form-data" class="mt-3 space-y-2">
        @csrf
        <label class="flex items-center gap-2 text-sm text-slate-800">
            <input type="radio" name="type" value="repaired" required>
            <span>{{ __('issues.verify_repaired') }}</span>
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-800">
            <input type="radio" name="type" value="still_exists" required>
            <span>{{ __('issues.verify_still') }}</span>
        </label>
        <textarea name="note" rows="2" maxlength="300" required placeholder="{{ __('issues.verify_note_label') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
        <label class="flex items-center gap-2 text-sm text-slate-800">
            <input type="checkbox" name="seen" value="1" required>
            <span>{{ __('issues.verify_seen') }}</span>
        </label>
        @include('issues._update-photo', ['for' => 'verify'])
        <button type="submit" class="w-full rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">{{ __('issues.verify_submit') }}</button>
    </form>
</section>
@endif
{{-- Observation form (logged in) --}}
<section id="confirm" class="rounded-xl border border-slate-200 bg-white p-5">
    <h2 class="font-semibold text-slate-900">{{ __('issues.obs_title') }}</h2>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.obs_note') }}</p>

    @if (session('obs_done'))
        <p class="mt-2 rounded-lg bg-green-50 p-2 text-sm text-green-800">{{ __('issues.obs_thanks') }}</p>
    @endif

    <form method="POST" action="{{ url('/issues/' . $issue->public_id . '/observe') }}" enctype="multipart/form-data" class="mt-3 space-y-2">
        @csrf
        @foreach (['fixed', 'still_exists', 'got_worse'] as $t)
            <label class="flex items-center gap-2 text-sm text-slate-800">
                <input type="radio" name="type" value="{{ $t }}" required @checked(($glance['mine'] ?? null) === $t)>
                <span>{{ __('issues.obs_' . $t) }}</span>
            </label>
        @endforeach
        <textarea name="note" rows="2" maxlength="500" placeholder="{{ __('issues.obs_note_label') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
        @include('issues._update-photo', ['for' => 'observe'])
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('issues.obs_submit') }}</button>
    </form>
</section>
@endif
@else
{{-- Confirm (needs login, coming later) --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="font-semibold text-slate-900">{{ __('issue.page.confirm') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('issue.page.confirm_note') }}</p>
                <a href="{{ url('/login') }}"
                   class="mt-3 inline-block w-full rounded-lg bg-brand-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-brand-700">
                    {{ __('issue.page.login_to_confirm') }}
                </a>
            </section>

            @endauth

{{-- Correction path --}}
            <p class="px-1 text-sm text-slate-500">
                {{ __('issue.page.wrong_info') }}
                <span class="italic">{{ __('issue.page.coming_soon') }}</span>
            </p>
        </aside>
    </div>
</div>
@include('partials.ad-slot', ['slot' => 'issue_bottom'])

@include('partials.photo-compress')
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