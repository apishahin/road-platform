{{-- Stats strip shown under the hero photo slider (the text-hero hosts its own). --}}
@php $S = \App\Support\SiteSections::class; @endphp
<section class="border-b border-slate-200 bg-white" data-stats-row>
    <div class="mx-auto max-w-6xl px-4 py-4 sm:py-5">
        <dl class="flex gap-2.5 overflow-x-auto pb-1 sm:grid sm:grid-cols-3 sm:gap-4 sm:overflow-visible">
            <div class="min-w-[120px] shrink-0 rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
                <dt class="text-[11px] font-medium text-slate-500">{{ __('Open issues') }}</dt>
                <dd class="mt-0.5 text-xl font-extrabold text-slate-900 sm:text-2xl">{{ \App\Support\IssuePresenter::num($stats['open']) }}</dd>
            </div>
            <div class="min-w-[120px] shrink-0 rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
                <dt class="text-[11px] font-medium text-slate-500">{{ __('Verified repaired') }}</dt>
                <dd class="mt-0.5 text-xl font-extrabold text-emerald-600 sm:text-2xl">{{ \App\Support\IssuePresenter::num($stats['repaired']) }}</dd>
            </div>
            <div class="min-w-[120px] shrink-0 rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-slate-200">
                <dt class="text-[11px] font-medium text-slate-500">{{ __('Reports received') }}</dt>
                <dd class="mt-0.5 text-xl font-extrabold text-slate-900 sm:text-2xl">{{ \App\Support\IssuePresenter::num($stats['reports']) }}</dd>
            </div>
        </dl>
        @if (($stats['notified'] ?? 0) > 0)
            <p class="mt-2 text-xs text-slate-500">{{ __('issues.home_notified', ['count' => \App\Support\IssuePresenter::num($stats['notified'])]) }}</p>
        @endif
    </div>
</section>
