@extends('layouts.app')

@section('title', __('issueadmin.title'))

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('issueadmin.title') }}</h1>

    @php $keep = array_filter(['area' => $areaId ?: null, 'noarea' => $noArea ? 1 : null]); @endphp
    <form method="GET" action="{{ route('admin.issues.index') }}" class="mt-4">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="hidden" name="area" id="fAreaId" value="{{ $areaId ?: '' }}">
        <div style="position:relative;">
            <input type="text" id="fAreaInput" autocomplete="off" value="{{ $areaLabel }}" placeholder="{{ __('issuefilter.area_ph') }}" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;">
            <div id="fAreaBox" style="position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border-radius:8px;"></div>
        </div>
        <div style="margin-top:8px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;font-size:14px;">
            <label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" name="noarea" value="1" id="fNoArea" @checked($noArea)> {{ __('issuefilter.no_area_only') }}</label>
            <button type="submit" style="padding:6px 16px;border-radius:8px;background:#0f172a;color:#fff;border:0;">{{ __('issuefilter.apply') }}</button>
            @if($areaId || $noArea)
                <a href="{{ route('admin.issues.index', ['tab' => $tab]) }}" style="color:#b91c1c;font-size:13px;">{{ __('issuefilter.clear') }}</a>
            @endif
        </div>
    </form>
    <script>
    (function () {
        var inp = document.getElementById('fAreaInput');
        var hid = document.getElementById('fAreaId');
        var box = document.getElementById('fAreaBox');
        var chk = document.getElementById('fNoArea');
        var url = @json(route('admin.issues.areas'));
        var timer = null;
        chk.addEventListener('change', function () {
            if (chk.checked) { hid.value = ''; inp.value = ''; box.innerHTML = ''; }
        });
        inp.addEventListener('input', function () {
            hid.value = '';
            clearTimeout(timer);
            var s = inp.value.trim();
            box.innerHTML = '';
            if (s.length < 2) { return; }
            timer = setTimeout(function () {
                fetch(url + '?q=' + encodeURIComponent(s), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (rows) {
                        box.innerHTML = '';
                        rows.forEach(function (row) {
                            var b = document.createElement('button');
                            b.type = 'button';
                            b.textContent = row.label;
                            b.style.cssText = 'display:block;width:100%;text-align:left;padding:8px 12px;border:1px solid #e2e8f0;background:#fff;font-size:13px;cursor:pointer;';
                            b.addEventListener('click', function () {
                                hid.value = row.id;
                                inp.value = row.label;
                                chk.checked = false;
                                box.innerHTML = '';
                            });
                            box.appendChild(b);
                        });
                    });
            }, 300);
        });
    })();
    </script>
    <div class="mt-4 flex gap-2 text-sm">
        <a href="{{ route('admin.issues.index', $keep) }}" style="padding:6px 14px;border-radius:999px;border:1px solid #cbd5e1;{{ $tab === 'published' ? 'background:#0f172a;color:#fff;' : '' }}">{{ __('issueadmin.tab_published') }}</a>
        <a href="{{ route('admin.issues.index', $keep + ['tab' => 'unpublished']) }}" style="padding:6px 14px;border-radius:999px;border:1px solid #cbd5e1;{{ $tab === 'unpublished' ? 'background:#0f172a;color:#fff;' : '' }}">{{ __('issueadmin.tab_unpublished') }}</a>
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
                    @if($issue->published_at && ! $issue->unpublished_at)<a href="{{ route('admin.issues.notify', ['issue' => $issue->id]) }}" style="font-size:13px;text-decoration:underline;margin-right:10px;">{{ __('notify.button') }}</a> @endif <a href="{{ route('admin.issues.edit', ['issue' => $issue->id]) }}" style="font-size:13px;text-decoration:underline;">{{ __('issueadmin.edit') }}</a>

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