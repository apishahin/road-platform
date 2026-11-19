@extends('layouts.app')

@section('title', __('issues.notif_title'))

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8" data-notifications>
    <h1 class="text-2xl font-bold">{{ __('issues.notif_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.notif_sub') }}</p>

    <div class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white">
        @forelse ($items as $n)
            @php $unread = $n->read_at === null; @endphp
            <div class="border-b border-slate-100 px-4 py-3 last:border-0" @if ($unread) style="background:#f0fdf4;" @endif>
                <div class="flex items-start gap-3">
                    <span style="margin-top:2px;display:grid;place-items:center;width:28px;height:28px;border-radius:8px;background:{{ $unread ? '#dcfce7' : '#f1f5f9' }};flex-shrink:0;">
                        <svg style="width:15px;height:15px;color:#15803d;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 18a2.5 2.5 0 0 1-5 0m9.2-2.6H5.3a1.2 1.2 0 0 1-1-1.9l1.4-1.8V8.4a6.3 6.3 0 0 1 12.6 0v3.1l1.4 1.8a1.2 1.2 0 0 1-1 1.9Z"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900">{{ $n->title }}</p>
                        @if ($n->statusEvent)
                            <p class="mt-0.5 text-sm text-slate-600">{{ \App\Support\StatusLabel::forEvent($n->statusEvent) }}</p>
                        @endif
                        <p class="mt-0.5 text-xs text-slate-400">{{ $n->created_at?->format('d M Y, h:i A') }}</p>
                        @if ($n->url)
                            <a href="{{ $n->url }}" class="mt-1 inline-block text-sm font-semibold text-brand-700 hover:underline">{{ __('issues.notif_view') }} &rarr;</a>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="px-4 py-10 text-center text-sm text-slate-500">{{ __('issues.notif_empty') }}</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $items->links() }}</div>
</div>
@endsection
