@extends('layouts.app')

@section('title', __('issueadmin.title'))

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('issueadmin.title') }}</h1>

    <div class="mt-4 flex gap-2 text-sm">
        <a href="{{ route('admin.issues.index') }}" style="padding:6px 14px;border-radius:999px;border:1px solid #cbd5e1;{{ $tab === 'published' ? 'background:#0f172a;color:#fff;' : '' }}">{{ __('issueadmin.tab_published') }}</a>
        <a href="{{ route('admin.issues.index', ['tab' => 'unpublished']) }}" style="padding:6px 14px;border-radius:999px;border:1px solid #cbd5e1;{{ $tab === 'unpublished' ? 'background:#0f172a;color:#fff;' : '' }}">{{ __('issueadmin.tab_unpublished') }}</a>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <div class="mt-6 space-y-3">
        @forelse ($issues as $issue)
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="font-semibold text-slate-900">{{ $issue->title }}</div>
                <div class="mt-1 text-xs text-slate-500">
                    @if ($issue->road_name) {{ $issue->road_name }} &middot; @endif
                    {{ $issue->reports_count }} {{ __('issueadmin.reports') }}
                    @if ($tab === 'published')
                        &middot; {{ __('issueadmin.published') }}: {{ \Illuminate\Support\Carbon::parse($issue->published_at)->diffForHumans() }}
                    @else
                        &middot; {{ __('issueadmin.unpublished') }}: {{ \Illuminate\Support\Carbon::parse($issue->unpublished_at)->diffForHumans() }}
                        @if ($issue->unpublish_reason) &middot; {{ __('issueadmin.reason') }}: {{ __('issueadmin.reason_' . $issue->unpublish_reason) }} @endif
                    @endif
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @if ($tab === 'published')
                        <a href="{{ route('issues.show', $issue->public_id) }}" target="_blank" rel="noopener" style="font-size:13px;text-decoration:underline;">{{ __('issueadmin.view') }}</a>
                    @endif
                    <a href="{{ route('admin.issues.edit', ['issue' => $issue->id]) }}" style="font-size:13px;text-decoration:underline;">{{ __('issueadmin.edit') }}</a>

                    @if ($tab === 'published')
                        <form method="POST" action="{{ route('admin.issues.unpublish', ['issue' => $issue->id]) }}" onsubmit="return confirm('{{ __('issueadmin.unpublish_confirm') }}')" style="display:flex;gap:6px;align-items:center;">
                            @csrf
                            <select name="reason" style="font-size:13px;border:1px solid #cbd5e1;border-radius:6px;padding:3px;">
                                @foreach (['privacy', 'wrong', 'duplicate', 'other'] as $r)
                                    <option value="{{ $r }}">{{ __('issueadmin.reason_' . $r) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" style="background:#dc2626;color:#fff;border:0;border-radius:6px;padding:5px 12px;font-size:13px;cursor:pointer;">{{ __('issueadmin.unpublish') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.issues.republish', ['issue' => $issue->id]) }}" onsubmit="return confirm('{{ __('issueadmin.republish_confirm') }}')">
                            @csrf
                            <button type="submit" style="background:#15803d;color:#fff;border:0;border-radius:6px;padding:5px 12px;font-size:13px;cursor:pointer;">{{ __('issueadmin.republish') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">{{ __('issueadmin.empty') }}</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $issues->links() }}</div>
</div>
@endsection