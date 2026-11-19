@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8" data-audit-log>
    <h1 class="text-2xl font-bold">{{ __('issues.audit_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.audit_sub') }}</p>

    <div class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
        @forelse ($events as $e)
            <div class="flex flex-wrap items-start gap-x-3 gap-y-1 border-b border-slate-100 px-4 py-3 text-sm last:border-0">
                <span class="shrink-0 text-xs text-slate-400" dir="ltr">{{ $e->created_at?->format('Y-m-d H:i') }}</span>
                <span class="font-medium">{{ $e->actor?->display_name ?: ($e->actor?->phone ?? __('issues.audit_system_actor')) }}</span>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700" dir="ltr">{{ $e->action }}</span>
                <span class="text-slate-600" dir="ltr">{{ $e->target_type }}#{{ $e->target_id }}</span>
                @if (is_array($e->details) && $e->details !== [])
                    <details class="w-full">
                        <summary class="cursor-pointer text-xs text-slate-400">{{ __('issues.audit_details') }}</summary>
                        <pre class="mt-1 max-h-40 overflow-auto rounded-lg bg-slate-50 p-2 text-xs" dir="ltr">{{ json_encode($e->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                @endif
            </div>
        @empty
            <div class="px-4 py-6 text-center text-sm text-slate-500">{{ __('issues.audit_none') }}</div>
        @endforelse
    </div>

    @if ($events->hasPages())
        <div class="mt-4">{{ $events->links() }}</div>
    @endif
</div>
@endsection
