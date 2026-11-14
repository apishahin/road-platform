@php $S = \App\Support\SiteSections::class; @endphp
<div data-home-design="modern">

{{-- Hero --}}
@if ($S::on('hero'))
<section class="relative overflow-hidden rounded-b-[2rem] bg-gradient-to-b from-slate-950 via-slate-900 to-slate-900 text-white sm:rounded-b-[3rem]">
    <div class="pointer-events-none absolute -top-24 right-[-10%] h-72 w-72 rounded-full bg-brand-600/25 blur-3xl sm:h-96 sm:w-96"></div>
    <div class="pointer-events-none absolute bottom-[-40%] left-[-10%] h-72 w-72 rounded-full bg-indigo-600/20 blur-3xl"></div>

    <div class="relative mx-auto max-w-6xl px-4 pb-9 pt-8 sm:pb-12 sm:pt-12">
        <h1 class="max-w-3xl font-extrabold leading-tight tracking-tight {{ $S::sizeClass('hero') }}">
            {{ $S::txt('hero', 'title', __('Find road problems. Follow the repair.')) }}
        </h1>
        <p class="mt-3 max-w-2xl text-[15px] leading-relaxed text-slate-300 sm:text-lg">
            {{ $S::txt('hero', 'text', __('Report damaged roads, check whether someone already has, and follow what happens next in a public, dated record.')) }}
        </p>

        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
            <a href="{{ url('/report') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-brand-600 px-6 py-3.5 text-base font-bold text-white shadow-lg shadow-brand-600/30 transition hover:bg-brand-700 active:scale-[.98] sm:py-3">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                {{ __('Report a problem') }}
            </a>
            <a href="{{ url('/issues') }}" class="inline-flex items-center justify-center rounded-full border border-slate-400/60 px-6 py-3.5 text-base font-bold text-white transition hover:bg-white/10 sm:py-3">
                {{ __('Browse issues') }}
            </a>
        </div>

        @if ($S::on('stats'))
        <dl class="mt-6 flex gap-2.5 overflow-x-auto pb-1 sm:grid sm:max-w-2xl sm:grid-cols-3 sm:gap-4 sm:overflow-visible">
            <div class="min-w-[108px] shrink-0 rounded-2xl bg-white/5 px-3.5 py-3 ring-1 ring-white/10 backdrop-blur-sm sm:bg-slate-800">
                <dt class="text-[11px] font-medium text-slate-400">{{ __('Open issues') }}</dt>
                <dd class="mt-1 text-xl font-extrabold sm:text-3xl">{{ \App\Support\IssuePresenter::num($stats['open']) }}</dd>
            </div>
            <div class="min-w-[108px] shrink-0 rounded-2xl bg-white/5 px-3.5 py-3 ring-1 ring-white/10 backdrop-blur-sm sm:bg-slate-800">
                <dt class="text-[11px] font-medium text-slate-400">{{ __('Verified repaired') }}</dt>
                <dd class="mt-1 text-xl font-extrabold text-emerald-400 sm:text-3xl">{{ \App\Support\IssuePresenter::num($stats['repaired']) }}</dd>
            </div>
            <div class="min-w-[108px] shrink-0 rounded-2xl bg-white/5 px-3.5 py-3 ring-1 ring-white/10 backdrop-blur-sm sm:bg-slate-800">
                <dt class="text-[11px] font-medium text-slate-400">{{ __('Reports received') }}</dt>
                <dd class="mt-1 text-xl font-extrabold sm:text-3xl">{{ \App\Support\IssuePresenter::num($stats['reports']) }}</dd>
            </div>
        </dl>
        @if (($stats['notified'] ?? 0) > 0)
            <p class="mt-2.5 text-xs text-slate-400">{{ __('issues.home_notified', ['count' => \App\Support\IssuePresenter::num($stats['notified'])]) }}</p>
        @endif
        @endif
    </div>
</section>
@endif

@if ($S::on('fund'))
    @include('fund._home-box-modern')
@endif

{{-- Map + list --}}
@if ($S::on('map'))
<section class="mx-auto max-w-6xl px-4 py-5 sm:py-7">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-extrabold tracking-tight text-slate-900 {{ $S::sizeClass('map') }}">{{ $S::txt('map', 'title', __('Issues on the map')) }}</h2>
        <button id="locate-btn" type="button"
                class="inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-100 disabled:opacity-60">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
            {{ __('Use my location') }}
        </button>
    </div>

    <div id="status-filter" class="mb-3 flex gap-1.5 overflow-x-auto pb-1 sm:flex-wrap sm:overflow-visible" role="group" aria-label="{{ __('issues.filter_label') }}" data-none="{{ __('issues.filter_none') }}">
        @foreach (['all' => 'issues.filter_all', 'new' => 'issues.filter_new', 'started' => 'issues.filter_started', 'repaired' => 'issues.filter_repaired'] as $key => $label)
            <button type="button" data-filter="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}"
                    class="shrink-0 rounded-full border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 sm:text-sm"
                    style="{{ $key === 'all' ? 'background-color:#0f172a;color:#ffffff;border-color:#0f172a' : '' }}">
                {{ __($label) }}
            </button>
        @endforeach
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div id="home-map" class="h-[300px] w-full overflow-hidden rounded-2xl border border-slate-200 shadow-sm sm:h-[460px]"></div>
            <div class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-500">
                <span class="font-semibold">{{ __('Status colours') }}:</span>
                @foreach ([['#dc2626', 'Reported'], ['#d97706', 'Acknowledged'], ['#2563eb', 'Planned'], ['#4f46e5', 'Work started'], ['#9333ea', 'Completion claimed (not yet verified)'], ['#16a34a', 'Verified repaired']] as [$color, $label])
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2.5 rounded-full" style="background-color: {{ $color }}"></span>{{ __($label) }}
                    </span>
                @endforeach
            </div>
        </div>

        <aside class="rounded-2xl border border-slate-200 bg-white p-3.5 shadow-sm sm:p-4">
            <h3 id="list-title" class="mb-1.5 flex items-center gap-1.5 text-sm font-extrabold text-slate-900"><span class="relative flex size-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-60"></span><span class="relative inline-flex size-2 rounded-full bg-emerald-500"></span></span>{{ __('Recently active') }}</h3>
            <p id="locate-status" class="mb-2 text-xs text-slate-500" aria-live="polite"></p>
            <div id="issue-list" class="space-y-2.5">
                @forelse ($recent as $issue)
                    <x-issue-card :issue="$issue" />
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

{{-- How it works --}}
@if ($S::on('how'))
<section class="border-t border-slate-200 bg-slate-50">
    <div class="mx-auto grid max-w-6xl gap-3 px-4 py-6 sm:gap-4 sm:py-9 md:grid-cols-3">
        @foreach ([
            ['s1t', '1. Find or report', 's1x', 'Check the map first. Add to an existing issue, or report a new one with a location and photos.', 1],
            ['s2t', '2. Follow the record', 's2x', 'Every status change is dated and shows who or what supplied it.', 2],
            ['s3t', '3. Verify the outcome', 's3x', 'A repair claim is not the same as a verified repair. The community can confirm it or reopen the issue.', 3],
        ] as [$tk, $tdef, $xk, $xdef, $num])
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-5">
                <div class="flex size-9 items-center justify-center rounded-full bg-brand-600/10 text-sm font-extrabold text-brand-700">{{ \App\Support\IssuePresenter::num($num) }}</div>
                <h3 class="mt-2.5 font-bold text-slate-900 {{ $S::sizeClass('how') === 'text-xl sm:text-2xl' ? 'text-base' : $S::sizeClass('how') }}">{{ $S::txt('how', $tk, __($tdef)) }}</h3>
                <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ $S::txt('how', $xk, __($xdef)) }}</p>
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- Recently verified repairs --}}
@if ($S::on('repaired') && isset($repaired) && count($repaired) > 0)
    <section class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-5 sm:py-7">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 class="flex items-center gap-2 font-extrabold tracking-tight text-slate-900 {{ $S::sizeClass('repaired') }}">
                        <svg class="size-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="8.5"/></svg>{{ $S::txt('repaired', 'title', __('issues.home_repaired_title')) }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $S::txt('repaired', 'text', __('issues.home_repaired_sub')) }}</p>
                    <a href="{{ route('issues.index', ['tab' => 'repaired']) }}" class="mt-1.5 inline-block text-sm font-bold text-brand-700 hover:underline">{{ __('issues.home_area_solutions') }} &rarr;</a>
                </div>
                <a href="{{ url('/issues') }}" class="text-sm font-bold text-brand-700 hover:underline">{{ __('issues.home_view_all') }}</a>
            </div>
            <div class="mt-3.5 grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                @foreach ($repaired as $issue)
                    <x-issue-card :issue="$issue" />
                @endforeach
            </div>
        </div>
    </section>
@endif

@if ($S::on('privacy'))
<section class="border-t border-slate-200 bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-3">
        <p class="text-xs text-slate-500">{{ $S::txt('privacy', 'text', __('issues.home_privacy')) }}</p>
    </div>
</section>
@endif

</div>
