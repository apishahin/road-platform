@include('home._rp-css')
<div data-home-design="classic">@php $S = \App\Support\SiteSections::class; @endphp

{{-- Hero (photo slider mode, or the text hero) --}}
@if ($S::on('hero'))
@php $heroSlider = $S::heroSlider(); @endphp
@if ($heroSlider['on'] && count($heroSlider['slides']) > 0)
    <div class="rp-home">@include('home._slider', ['heroSlides' => $heroSlider['slides']])</div>
    @if ($S::on('stats'))
        @include('home._stats-row')
    @endif
@else
<section class="bg-slate-900 text-white">
    <div class="mx-auto max-w-6xl px-4 py-6 sm:py-10">
        <h1 class="max-w-3xl font-bold leading-snug {{ $S::sizeClass('hero') }}">
            {{ $S::txt('hero', 'title', __('Find road problems. Follow the repair.')) }}
        </h1>
        <p class="mt-2 max-w-2xl text-sm text-slate-300 sm:mt-3 sm:text-base">
            {{ $S::txt('hero', 'text', __('Report damaged roads, check whether someone already has, and follow what happens next in a public, dated record.')) }}
        </p>

        <div class="mt-4 flex flex-col gap-2.5 sm:mt-5 sm:flex-row sm:gap-3">
            <a href="{{ url('/report') }}" class="rounded-lg bg-brand-600 px-5 py-2.5 text-center text-sm font-semibold text-white hover:bg-brand-700 sm:text-base">
                {{ \App\Support\SiteSections::txt('hero', 'cta_label', __('Report a problem')) }}
            </a>
            @if (\App\Support\SiteSections::txt('hero', 'cta_sub') !== '')
                <p class="mt-2 text-[12.5px] text-slate-400">{{ \App\Support\SiteSections::txt('hero', 'cta_sub') }}</p>
            @endif
            <a href="{{ url('/issues') }}" class="rounded-lg border border-slate-500 px-5 py-2.5 text-center text-sm font-semibold text-white hover:bg-slate-800 sm:text-base">
                {{ __('Browse issues') }}
            </a>
        </div>

        @if ($S::on('stats'))
        <dl class="mt-5 grid max-w-xl grid-cols-3 gap-2 sm:mt-6 sm:gap-4">
            <div class="rounded-lg bg-slate-800 px-3 py-2.5">
                <dt class="text-[11px] text-slate-400 sm:text-xs">{{ __('Open issues') }}</dt>
                <dd class="mt-0.5 text-lg font-bold sm:text-2xl">{{ \App\Support\IssuePresenter::num($stats['open']) }}</dd>
            </div>
            <div class="rounded-lg bg-slate-800 px-3 py-2.5">
                <dt class="text-[11px] text-slate-400 sm:text-xs">{{ __('Verified repaired') }}</dt>
                <dd class="mt-0.5 text-lg font-bold text-green-400 sm:text-2xl">{{ \App\Support\IssuePresenter::num($stats['repaired']) }}</dd>
            </div>
            <div class="rounded-lg bg-slate-800 px-3 py-2.5">
                <dt class="text-[11px] text-slate-400 sm:text-xs">{{ __('Reports received') }}</dt>
                <dd class="mt-0.5 text-lg font-bold sm:text-2xl">{{ \App\Support\IssuePresenter::num($stats['reports']) }}</dd>
            </div>
        </dl>
        @if (($stats['notified'] ?? 0) > 0)
            <p class="mt-2 text-xs text-slate-300 sm:text-sm">{{ __('issues.home_notified', ['count' => \App\Support\IssuePresenter::num($stats['notified'])]) }}</p>
        @endif
        @endif
    </div>
</section>
@endif
@endif

@include('partials.ad-slot', ['slot' => 'home_after_hero'])

@if ($S::on('fund'))
    @include('fund._home-box')
@endif

{{-- Map + list --}}
@if ($S::on('map'))
<section class="mx-auto max-w-6xl px-4 py-4 sm:py-6">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-bold text-slate-900 {{ $S::sizeClass('map') }}">{{ $S::txt('map', 'title', __('Issues on the map')) }}</h2>
        <button id="locate-btn" type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-medium hover:bg-slate-100 disabled:opacity-60">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
            {{ __('Use my location') }}
        </button>
    </div>

    <div id="status-filter" class="mb-3 flex flex-wrap gap-1.5" role="group" aria-label="{{ __('issues.filter_label') }}" data-none="{{ __('issues.filter_none') }}">
        @foreach (['all' => 'issues.filter_all', 'new' => 'issues.filter_new', 'started' => 'issues.filter_started', 'repaired' => 'issues.filter_repaired'] as $key => $label)
            <button type="button" data-filter="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}"
                    class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 sm:text-sm"
                    style="{{ $key === 'all' ? 'background-color:#0f172a;color:#ffffff;border-color:#0f172a' : '' }}">
                {{ __($label) }}
            </button>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div id="home-map" class="h-[320px] w-full overflow-hidden rounded-xl border border-slate-200 sm:h-[480px]"></div>

            {{-- Legend --}}
            <div class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
                <span class="font-medium">{{ __('Status colours') }}:</span>
                @foreach ([['#dc2626', 'Reported'], ['#d97706', 'Acknowledged'], ['#2563eb', 'Planned'], ['#4f46e5', 'Work started'], ['#9333ea', 'Completion claimed (not yet verified)'], ['#16a34a', 'Verified repaired']] as [$color, $label])
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2.5 rounded-full" style="background-color: {{ $color }}"></span>{{ __($label) }}
                    </span>
                @endforeach
            </div>
        </div>

        <aside>
            <h3 id="list-title" class="mb-2 text-sm font-semibold text-slate-900 sm:text-base">{{ __('Recently active') }}</h3>
            <p id="locate-status" class="mb-2 text-xs text-slate-500 sm:text-sm" aria-live="polite"></p>

            <div id="issue-list" class="space-y-2.5">
                @forelse ($recent as $issue)
                    <x-issue-card :issue="$issue" :photo="$photos[$issue->id] ?? null" />
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-600">
                        {{ __('No reports yet. Be the first to report a problem.') }}
                    </p>
                @endforelse
            </div>
        </aside>
    </div>
</section>
@endif

@if (\App\Support\SiteSections::on('ribbon'))
<section class="border-b border-slate-200 bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-5 sm:py-6">
        @include('partials.stats-panel')
    </div>
</section>
@endif

{{-- How it works (4 steps) --}}
@if ($S::on('how'))
<section class="border-t border-slate-200 bg-white">
    <div class="mx-auto max-w-6xl px-4 py-6 sm:py-8">
        <div class="grid gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['s1t', 'issues.how1_title', 's1x', 'issues.how1_text', 1],
            ['s2t', 'issues.how2_title', 's2x', 'issues.how2_text', 2],
            ['s3t', 'issues.how3_title', 's3x', 'issues.how3_text', 3],
            ['s4t', 'issues.how4_title', 's4x', 'issues.how4_text', 4],
        ] as [$tk, $tdef, $xk, $xdef, $num])
            <div>
                <span class="inline-flex size-7 items-center justify-center rounded-full bg-brand-600/10 text-xs font-extrabold text-brand-700">{{ \App\Support\IssuePresenter::num($num) }}</span>
                <h3 class="mt-1.5 text-base font-semibold text-slate-900">{{ $S::txt('how', $tk, __($tdef)) }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ $S::txt('how', $xk, __($xdef)) }}</p>
            </div>
        @endforeach
        </div>
        @php $howPage = \App\Models\Page::where('slug', 'how-it-works')->where('is_published', true)->first(); @endphp
        @if ($howPage)
            <a href="{{ route('page.show', $howPage->slug) }}" class="mt-4 inline-block text-sm font-semibold text-brand-700 hover:underline">{{ __('issues.how_more') }} &rarr;</a>
        @endif
    </div>
</section>
@endif

{{-- Recently verified repairs --}}
@if ($S::on('repaired') && isset($repaired) && count($repaired) > 0)
    <section class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-5 sm:py-6">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 class="font-bold text-slate-900 {{ $S::sizeClass('repaired') }}">{{ $S::txt('repaired', 'title', __('issues.home_repaired_title')) }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $S::txt('repaired', 'text', __('issues.home_repaired_sub')) }}</p>
                    <a href="{{ route('issues.index', ['tab' => 'repaired']) }}" class="mt-1.5 inline-block text-sm font-semibold text-brand-700 hover:underline">{{ __('issues.home_area_solutions') }} &rarr;</a>
                </div>
                <a href="{{ url('/issues') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('issues.home_view_all') }}</a>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                @foreach ($repaired->take(3) as $issue)
                    <x-issue-card :issue="$issue" :photo="$photos[$issue->id] ?? null" />
                @endforeach
            </div>
        </div>
    </section>
@endif

@if ($S::on('cta'))
    <div class="rp-home">@include('home._cta')</div>
@endif

@if ($S::on('privacy'))
<section class="border-t border-slate-200 bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-3">
        <p class="text-xs text-slate-500">{{ $S::txt('privacy', 'text', __('issues.home_privacy')) }}</p>
    </div>
</section>
@endif
</div>
