@extends('layouts.app')

@section('title', __('issueactivity.title'))

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ __('issueactivity.title') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ __('issueactivity.issue') }} #{{ \App\Support\IssuePresenter::num($issue->id) }}: <strong>{{ $issue->title }}</strong></p>
        </div>
        <a href="{{ route('admin.issues.index') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('issueactivity.back') }}</a>
    </div>

    <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
        {{ __('issueactivity.private_note') }}
    </div>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="hidden grid-cols-[minmax(130px,1.2fr)_minmax(115px,.8fr)_minmax(190px,1.8fr)_minmax(140px,1fr)] gap-4 border-b border-slate-200 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-slate-500 md:grid">
            <div>{{ __('issueactivity.staff') }}</div>
            <div>{{ __('issueactivity.role') }}</div>
            <div>{{ __('issueactivity.action') }}</div>
            <div>{{ __('issueactivity.time') }}</div>
        </div>

        @forelse ($activity as $item)
            <article class="grid gap-3 border-b border-slate-100 px-5 py-4 last:border-b-0 md:grid-cols-[minmax(130px,1.2fr)_minmax(115px,.8fr)_minmax(190px,1.8fr)_minmax(140px,1fr)] md:gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400 md:hidden">{{ __('issueactivity.staff') }}</p>
                    @if ($item['actor']['name'])
                        <p class="font-semibold text-slate-900">{{ $item['actor']['name'] }}</p>
                    @else
                        <p class="font-semibold text-slate-500">—</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400 md:hidden">{{ __('issueactivity.role') }}</p>
                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ $item['actor']['role'] }}</span>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400 md:hidden">{{ __('issueactivity.action') }}</p>
                    <p class="font-semibold text-slate-900">{{ $item['action'] }}</p>
                    @if ($item['context'] !== [])
                        <ul class="mt-1.5 space-y-1 text-sm leading-5 text-slate-600">
                            @foreach ($item['context'] as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400 md:hidden">{{ __('issueactivity.time') }}</p>
                    <time class="text-sm text-slate-600">{{ $item['when'] }}</time>
                </div>
            </article>
        @empty
            <p class="px-5 py-12 text-center text-sm text-slate-500">{{ __('issueactivity.empty_history') }}</p>
        @endforelse
    </div>
</div>
@endsection
