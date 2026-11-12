@props(['issue'])

<a href="{{ url('/issues/' . $issue->public_id) }}"
   class="block rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-500 hover:shadow">
    <div class="flex items-start justify-between gap-3">
        <h3 class="font-semibold leading-snug text-slate-900">{{ $issue->title }}</h3>
        <x-status-badge :issue="$issue" class="shrink-0" />
    </div>

    <p class="mt-1 text-sm text-slate-600">
        {{ $issue->category?->name() }}@if ($issue->road_name) · {{ $issue->road_name }}@endif
    </p>

    <p class="mt-2 text-xs text-slate-500">
        {{ __('Last activity') }}: {{ $issue->last_activity_at?->diffForHumans() }}
        · {{ __(':count report(s)', ['count' => \App\Support\IssuePresenter::num($issue->reports_count)]) }}
    </p>
    @if ($issue->getAttribute('authority_notified'))
        <p class="mt-2">
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" style="background-color:#e0f2fe;color:#0369a1">{{ __('issues.notified_tag') }}</span>
        </p>
    @endif
</a>