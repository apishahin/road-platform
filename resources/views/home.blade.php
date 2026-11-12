@extends('layouts.app')

@section('title', __('Find and report road problems'))

@section('content')

{{-- Hero --}}
<section class="bg-slate-900 text-white">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:py-14">
        <h1 class="max-w-3xl text-2xl font-bold leading-snug sm:text-4xl">
            {{ __('Find road problems. Follow the repair.') }}
        </h1>
        <p class="mt-3 max-w-2xl text-base text-slate-300 sm:text-lg">
            {{ __('Report damaged roads, check whether someone already has, and follow what happens next in a public, dated record.') }}
        </p>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <a href="{{ url('/report') }}" class="rounded-lg bg-brand-600 px-5 py-3 text-center font-semibold text-white hover:bg-brand-700">
                {{ __('Report a problem') }}
            </a>
            <a href="{{ url('/issues') }}" class="rounded-lg border border-slate-500 px-5 py-3 text-center font-semibold text-white hover:bg-slate-800">
                {{ __('Browse issues') }}
            </a>
        </div>

        <dl class="mt-8 grid max-w-xl grid-cols-3 gap-2 sm:gap-4">
            <div class="rounded-lg bg-slate-800 px-3 py-3">
                <dt class="text-xs text-slate-400">{{ __('Open issues') }}</dt>
                <dd class="mt-1 text-xl font-bold sm:text-2xl">{{ \App\Support\IssuePresenter::num($stats['open']) }}</dd>
            </div>
            <div class="rounded-lg bg-slate-800 px-3 py-3">
                <dt class="text-xs text-slate-400">{{ __('Verified repaired') }}</dt>
                <dd class="mt-1 text-xl font-bold text-green-400 sm:text-2xl">{{ \App\Support\IssuePresenter::num($stats['repaired']) }}</dd>
            </div>
            <div class="rounded-lg bg-slate-800 px-3 py-3">
                <dt class="text-xs text-slate-400">{{ __('Reports received') }}</dt>
                <dd class="mt-1 text-xl font-bold sm:text-2xl">{{ \App\Support\IssuePresenter::num($stats['reports']) }}</dd>
            </div>
        </dl>
        @if (($stats['notified'] ?? 0) > 0)
            <p class="mt-3 text-sm text-slate-300">{{ __('issues.home_notified', ['count' => \App\Support\IssuePresenter::num($stats['notified'])]) }}</p>
        @endif
    </div>
</section>

{{-- Map + list --}}
<section class="mx-auto max-w-6xl px-4 py-8">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-bold text-slate-900">{{ __('Issues on the map') }}</h2>
        <button id="locate-btn" type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-100 disabled:opacity-60">
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
            {{ __('Use my location') }}
        </button>
    </div>

    <div id="status-filter" class="mb-4 flex flex-wrap gap-2" role="group" aria-label="{{ __('issues.filter_label') }}" data-none="{{ __('issues.filter_none') }}">
        @foreach (['all' => 'issues.filter_all', 'new' => 'issues.filter_new', 'started' => 'issues.filter_started', 'repaired' => 'issues.filter_repaired'] as $key => $label)
            <button type="button" data-filter="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}"
                    class="rounded-full border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
                    style="{{ $key === 'all' ? 'background-color:#0f172a;color:#ffffff;border-color:#0f172a' : '' }}">
                {{ __($label) }}
            </button>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div id="home-map" class="h-[320px] w-full overflow-hidden rounded-xl border border-slate-200 sm:h-[480px]"></div>

            {{-- Legend --}}
            <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
                <span class="font-medium">{{ __('Status colours') }}:</span>
                @foreach ([['#dc2626', 'Reported'], ['#d97706', 'Acknowledged'], ['#2563eb', 'Planned'], ['#4f46e5', 'Work started'], ['#9333ea', 'Completion claimed (not yet verified)'], ['#16a34a', 'Verified repaired']] as [$color, $label])
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2.5 rounded-full" style="background-color: {{ $color }}"></span>{{ __($label) }}
                    </span>
                @endforeach
            </div>
        </div>

        <aside>
            <h3 id="list-title" class="mb-3 font-semibold text-slate-900">{{ __('Recently active') }}</h3>
            <p id="locate-status" class="mb-2 text-sm text-slate-500" aria-live="polite"></p>

            <div id="issue-list" class="space-y-3">
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

{{-- How it works --}}
<section class="border-t border-slate-200 bg-white">
    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-10 md:grid-cols-3">
        @foreach ([
            ['1. Find or report', 'Check the map first. Add to an existing issue, or report a new one with a location and photos.'],
            ['2. Follow the record', 'Every status change is dated and shows who or what supplied it.'],
            ['3. Verify the outcome', 'A repair claim is not the same as a verified repair. The community can confirm it or reopen the issue.'],
        ] as [$title, $text])
            <div>
                <h3 class="font-semibold text-slate-900">{{ __($title) }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ __($text) }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- Recently verified repairs --}}
@if (isset($repaired) && count($repaired) > 0)
    <section class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-8">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">{{ __('issues.home_repaired_title') }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ __('issues.home_repaired_sub') }}</p>
                </div>
                <a href="{{ url('/issues') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('issues.home_view_all') }}</a>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($repaired as $issue)
                    <x-issue-card :issue="$issue" />
                @endforeach
            </div>
        </div>
    </section>
@endif

<section class="border-t border-slate-200 bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-4">
        <p class="text-xs text-slate-500">{{ __('issues.home_privacy') }}</p>
    </div>
</section>

@endsection

@push('scripts')
<script>
    window.roadPlatform = {{ Js::from([
        'issues'      => $mapIssues,
        'center'      => config('roadplatform.map.center'),
        'zoom'        => config('roadplatform.map.zoom'),
        'tileUrl'     => config('roadplatform.map.tile_url'),
        'attribution' => config('roadplatform.map.attribution'),
        'nearbyUrl'   => url('/api/v1/issues/nearby'),
        'i18n'        => [
            'nearYou'    => __('Near you'),
            'noneNearby' => __('No issues found near you.'),
            'locating'   => __('Finding your location...'),
            'denied'     => __('Location permission was denied. You can still browse the map.'),
            'noGeo'      => __('Your browser does not support location.'),
            'locFailed'  => __('Could not get your location.'),
            'loadFailed' => __('Could not load nearby issues.'),
        ],
    ]) }};
</script>
@endpush