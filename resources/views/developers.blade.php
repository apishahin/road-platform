@extends('layouts.app')

@section('title', __('issues.dev_title'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-10">
    <h1 class="text-2xl font-bold">{{ __('issues.dev_title') }}</h1>
    <p class="mt-2 text-sm text-slate-600">{{ __('issues.dev_intro') }}</p>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">{{ __('issues.dev_auth_title') }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ __('issues.dev_auth_body') }}</p>
        <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-green-300">curl -H "X-Api-Key: rp_আপনার_কী" {{ url('/api/v1/stats') }}</pre>
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">{{ __('issues.dev_endpoints') }}</h2>
        <div class="mt-3 space-y-4 text-sm">
            <div>
                <code class="rounded bg-slate-100 px-2 py-1 font-mono text-xs font-bold">GET /api/v1/issues</code>
                <p class="mt-1 text-slate-600">{{ __('issues.dev_ep_issues') }}</p>
                <pre class="mt-1 overflow-x-auto rounded-lg bg-slate-50 p-2 text-xs">curl "{{ url('/api/v1/issues') }}?status=repaired&page=1"</pre>
            </div>
            <div>
                <code class="rounded bg-slate-100 px-2 py-1 text-xs font-bold">GET /api/v1/issues/{id}</code>
                <p class="mt-1 text-slate-600">{{ __('issues.dev_ep_issue') }}</p>
            </div>
            <div>
                <code class="rounded bg-slate-100 px-2 py-1 text-xs font-bold">GET /api/v1/issues/nearby?lat=…&lng=…</code>
                <p class="mt-1 text-slate-600">{{ __('issues.dev_ep_nearby') }}</p>
            </div>
            <div>
                <code class="rounded bg-slate-100 px-2 py-1 text-xs font-bold">GET /api/v1/categories</code> ·
                <code class="rounded bg-slate-100 px-2 py-1 text-xs font-bold">GET /api/v1/areas?level=district</code> ·
                <code class="rounded bg-slate-100 px-2 py-1 text-xs font-bold">GET /api/v1/stats</code>
                <p class="mt-1 text-slate-600">{{ __('issues.dev_ep_misc') }}</p>
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-600">
        <h2 class="font-semibold text-slate-900">{{ __('issues.dev_rules_title') }}</h2>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li>{{ __('issues.dev_rule_1') }}</li>
            <li>{{ __('issues.dev_rule_2') }}</li>
            <li>{{ __('issues.dev_rule_3') }}</li>
        </ul>
    </div>
</div>
@endsection
