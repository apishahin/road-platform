@extends('layouts.app')

@section('title', __('issues.verify_queue_title'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-6 sm:py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('issues.verify_queue_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.verify_queue_sub') }}</p>

    <div class="mt-5 space-y-3">
        @forelse ($issues as $i)
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="font-semibold text-slate-900">{{ $i->title }}</p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ $i->category?->name() }}
                    @if ($i->adminArea)
                        · {{ app()->getLocale() === 'bn' ? $i->adminArea->name_bn : ($i->adminArea->name_en ?: $i->adminArea->name_bn) }}
                    @endif
                </p>
                <p class="mt-2 text-sm font-medium text-amber-800">{{ __('issues.verify_queue_claims', ['count' => \App\Support\IssuePresenter::num($i->fixed_count)]) }}</p>
                <a href="{{ url('/issues/' . $i->public_id) }}#verify" class="mt-2 inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('issues.verify_queue_open') }}</a>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600">
                {{ __('issues.verify_queue_empty') }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $issues->links() }}</div>
</div>
@endsection