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
                       href="https://www.openstreetmap.org/?mlat={{ $r->lat }}&mlon={{ $r->lng }}#map=18/{{ $r->lat }}/{{ $r->lng }}">{{ __('report.mod_open_map') }}</a>
                </p>

                <div class="mt-3 flex flex-wrap gap-2">
                    @forelse ($r->evidence as $ev)
                        <a href="{{ route('admin.evidence.photo', $ev) }}" target="_blank" rel="noopener">
                            <img src="{{ route('admin.evidence.photo', $ev) }}" alt="" class="h-24 w-24 rounded-lg border border-slate-200 object-cover">
                        </a>
                    @empty
                        <span class="text-xs text-slate-400">{{ __('report.mod_no_photos') }}</span>
                    @endforelse
                </div>

                <div class="mt-4 flex gap-2">
                    <form method="POST" action="{{ route('admin.moderation.approve', $r) }}">
                        @csrf
                        <button class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">{{ __('report.mod_approve') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.moderation.hide', $r) }}">
                        @csrf
                        <button class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">{{ __('report.mod_hide') }}</button>
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
@endsection