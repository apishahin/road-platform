@extends('layouts.app')

@section('title', __('issueadmin.title'))

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('issueadmin.title') }}</h1>

    @php $keep = array_filter(['area' => $areaId ?: null, 'noarea' => $noArea ? 1 : null]); @endphp
    <form method="GET" action="{{ route('admin.issues.index') }}" class="mt-4">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="hidden" name="area" id="fAreaId" value="{{ $areaId ?: '' }}">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;">
            <select id="fSel0" style="padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;">
                <option value="">{{ __('areas.all_divisions') }}</option>
                @foreach($divisions as $d)
                    <option value="{{ $d['id'] }}">{{ $d['label'] }}</option>
                @endforeach
            </select>
            <select id="fSel1" disabled style="padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;"><option value="">{{ __('areas.all_districts') }}</option></select>
            <select id="fSel2" disabled style="padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;"><option value="">{{ __('areas.all_upazilas') }}</option></select>
            <select id="fSel3" disabled style="padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;"><option value="">{{ __('issuefilter.all_unions') }}</option></select>
        </div>
        <div style="margin-top:8px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;font-size:14px;">
            <label style="display:flex;gap:6px;align-items:center;"><input type="checkbox" name="noarea" value="1" id="fNoArea" @checked($noArea)> {{ __('issuefilter.no_area_only') }}</label>
            <noscript><button type="submit" style="padding:6px 16px;border-radius:8px;background:#0f172a;color:#fff;border:0;">{{ __('issuefilter.apply') }}</button></noscript>
            @if($areaId || $noArea)
                <a href="{{ route('admin.issues.index', ['tab' => $tab]) }}" style="color:#b91c1c;font-size:13px;">{{ __('issuefilter.clear') }}</a>
            @endif
        </div>
    </form>
    <script>
    (function () {
        var sels = [0, 1, 2, 3].map(function (i) { return document.getElementById('fSel' + i); });
        var hid = document.getElementById('fAreaId');
        var chk = document.getElementById('fNoArea');
        var base = @json(url('/areas'));
        var chain = @json($chain);
        var ph = [null, @json(__('areas.all_districts')), @json(__('areas.all_upazilas')), @json(__('issuefilter.all_unions'))];

        function reset(from) {
            for (var i = from; i < 4; i++) {
                sels[i].innerHTML = '';
                var o = document.createElement('option');
                o.value = '';
                o.textContent = ph[i];
                sels[i].appendChild(o);
                sels[i].disabled = true;
            }
        }
        function setHidden() {
            var v = '';
            for (var i = 0; i < 4; i++) { if (sels[i].value) { v = sels[i].value; } }
            hid.value = v;
        }
        function load(i, parentId, selected, cb) {
            fetch(base + '/' + parentId + '/children', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (rows) {
                    reset(i);
                    rows.forEach(function (row) {
                        var o = document.createElement('option');
                        o.value = row.id;
                        o.textContent = row.label;
                        sels[i].appendChild(o);
                    });
                    sels[i].disabled = rows.length === 0;
                    if (selected) { sels[i].value = String(selected); }
                    if (cb) { cb(); }
                });
        }
        function onChange(i) {
            chk.checked = false;
            reset(i + 1);
            setHidden();
            hid.form.submit();
        }
        for (var k = 0; k < 4; k++) {
            (function (idx) { sels[idx].addEventListener('change', function () { onChange(idx); }); })(k);
        }
        chk.addEventListener('change', function () {
            if (chk.checked) { sels[0].value = ''; reset(1); hid.value = ''; } hid.form.submit();
        });
        function init(i) {
            if (i >= chain.length || i >= 3) { return; }
            load(i + 1, chain[i], chain[i + 1] || '', function () { init(i + 1); });
        }
        if (chain.length) {
            sels[0].value = String(chain[0]);
            init(0);
        }
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