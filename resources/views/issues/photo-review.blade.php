@extends('layouts.app')

@section('title', __('issues.photo_review_title'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('issues.photo_review_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.photo_review_sub') }}</p>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            @foreach ($errors->all() as $e)
                <div>{{ $e }}</div>
            @endforeach
        </div>
    @endif

    <p class="mt-3 text-xs text-slate-500">{{ \App\Support\IssuePresenter::num($total) }}</p>

    <div class="mt-4 space-y-5">
        @forelse ($rows as $r)
            @php $iss = $r['issue']; @endphp
            <div id="photo-{{ $r['ev']->id }}" class="rounded-xl border border-slate-200 bg-white p-4" data-pr>
                <div style="font-size:13px;color:#475569">
                    @if ($r['kind'] === 'visit')
                        {{ __('issues.photo_review_from_visit') }}
                    @else
                        {{ __('issues.photo_review_from_citizen') }} &mdash; {{ $r['label'] }}
                    @endif
                </div>

                <div style="margin-top:6px;font-weight:700;color:#0f172a">
                    {{ __('issues.photo_issue_no') }}: #{{ $iss->id }} &middot; {{ $iss->title }}
                </div>
                <div style="margin-top:3px;font-size:13px;color:#334155">
                    @if ($iss->category)
                        {{ __('issues.photo_category') }}: {{ $iss->category->name() }}<br>
                    @endif
                    {{ __('issues.photo_area') }}:
                    @if ($r['area'] !== '')
                        {{ $r['area'] }}
                    @else
                        <span style="color:#b45309">{{ __('issues.photo_area_none') }}</span>
                    @endif
                    @if ($r['ev']->taken_at)
                        <br>{{ __('issues.photo_taken') }}: {{ $r['ev']->taken_at->format('d/m/Y') }}
                    @endif
                </div>
                <div style="margin-top:4px;font-size:13px">
                    <a href="{{ \App\Support\IssuePresenter::url($iss) }}" target="_blank" rel="noopener" style="color:#1d4ed8">{{ __('issues.photo_review_open_issue') }}</a>
                    @if ($iss->lat && $iss->lng)
                        &nbsp;&middot;&nbsp;
                        <a href="https://www.openstreetmap.org/?mlat={{ $iss->lat }}&mlon={{ $iss->lng }}#map=17/{{ $iss->lat }}/{{ $iss->lng }}" target="_blank" rel="noopener" style="color:#1d4ed8">{{ __('issues.photo_map') }}</a>
                    @endif
                </div>

                <div class="pr-wrap" style="position:relative;display:inline-block;max-width:100%;margin-top:10px">
                    <img class="pr-img" src="{{ route('photoreview.image', $r['ev']) }}" alt="" style="display:block;max-width:100%;max-height:420px;border-radius:8px;border:1px solid #e2e8f0">
                    <div class="pr-layer" style="position:absolute;left:0;top:0;right:0;bottom:0;display:none;cursor:crosshair;touch-action:none;outline:3px solid #f59e0b;border-radius:8px"></div>
                </div>

                <div class="pr-tools" style="display:none;margin-top:8px;padding:10px;border:1px solid #fcd34d;background:#fffbeb;border-radius:8px">
                    <p style="font-size:12px;color:#92400e;margin:0 0 6px 0">{{ __('issues.photo_blur_hint') }} {{ __('issues.photo_blur_irreversible') }}</p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                        <select class="pr-mode" style="border:1px solid #cbd5e1;border-radius:8px;padding:6px 8px;font-size:13px">
                            <option value="blur">{{ __('issues.photo_blur_blur') }}</option>
                            <option value="black">{{ __('issues.photo_blur_black') }}</option>
                        </select>
                        <button type="button" class="pr-undo" style="border:1px solid #94a3b8;background:#fff;border-radius:8px;padding:6px 12px;font-size:13px">{{ __('issues.photo_blur_undo') }}</button>
                        <button type="button" class="pr-save" style="background:#1d4ed8;color:#fff;font-weight:600;border:0;border-radius:8px;padding:7px 14px;font-size:13px">{{ __('issues.photo_blur_save') }}</button>
                        <button type="button" class="pr-cancel" style="border:1px solid #94a3b8;background:#fff;border-radius:8px;padding:6px 12px;font-size:13px">{{ __('issues.photo_blur_cancel') }}</button>
                    </div>
                </div>

                <form method="POST" action="{{ route('photoreview.blur', $r['ev']) }}" class="pr-blur-form" style="display:none">
                    @csrf
                    <input type="hidden" name="rects" value="">
                </form>

                <div class="pr-actions" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:12px">
                    <button type="button" class="pr-start" style="border:1px solid #1d4ed8;color:#1d4ed8;background:#fff;font-weight:600;border-radius:8px;padding:8px 14px">{{ __('issues.photo_blur_btn') }}</button>

                    <form method="POST" action="{{ route('photoreview.approve', $r['ev']) }}">
                        @csrf
                        <button type="submit" style="background:#16a34a;color:#fff;font-weight:600;border:0;border-radius:8px;padding:8px 16px">{{ __('issues.photo_review_approve') }}</button>
                    </form>

                    <form method="POST" action="{{ route('photoreview.hide', $r['ev']) }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                        @csrf
                        <select name="reason" required style="border:1px solid #cbd5e1;border-radius:8px;padding:7px 8px;font-size:13px">
                            <option value="face">{{ __('issues.photo_reason_face') }}</option>
                            <option value="plate">{{ __('issues.photo_reason_plate') }}</option>
                            <option value="personal">{{ __('issues.photo_reason_personal') }}</option>
                            <option value="other">{{ __('issues.photo_reason_other') }}</option>
                        </select>
                        <button type="submit" style="background:#b91c1c;color:#fff;font-weight:600;border:0;border-radius:8px;padding:8px 16px">{{ __('issues.photo_review_hide') }}</button>
                    </form>
                </div>
                <p style="margin:6px 0 0 0;font-size:12px;color:#64748b">{{ __('issues.photo_hide_hint') }}</p>
            </div>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500">{{ __('issues.photo_review_empty') }}</div>
        @endforelse
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-pr]').forEach(function (card) {
        var start = card.querySelector('.pr-start');
        if (!start) { return; }
        var layer = card.querySelector('.pr-layer');
        var tools = card.querySelector('.pr-tools');
        var actions = card.querySelector('.pr-actions');
        var form = card.querySelector('.pr-blur-form');
        var input = form.querySelector('input[name="rects"]');
        var modeSel = card.querySelector('.pr-mode');
        var rects = [];
        var cur = null;
        var sx = 0, sy = 0, drawing = false;

        function paint(r) {
            var s = r.el.style;
            s.position = 'absolute';
            s.left = (r.x * 100) + '%';
            s.top = (r.y * 100) + '%';
            s.width = (r.w * 100) + '%';
            s.height = (r.h * 100) + '%';
            s.boxSizing = 'border-box';
            if (r.m === 'black') {
                s.background = '#111827';
                s.border = '2px solid #111827';
            } else {
                s.background = 'rgba(255,255,255,.2)';
                s.webkitBackdropFilter = 'blur(10px)';
                s.backdropFilter = 'blur(10px)';
                s.border = '2px dashed #fff';
            }
        }
        function pos(e) {
            var b = layer.getBoundingClientRect();
            return {
                x: Math.min(1, Math.max(0, (e.clientX - b.left) / b.width)),
                y: Math.min(1, Math.max(0, (e.clientY - b.top) / b.height))
            };
        }
        function reset() {
            rects.forEach(function (r) { if (r.el.parentNode) { r.el.parentNode.removeChild(r.el); } });
            rects = [];
            cur = null;
            drawing = false;
        }
        function open(on) {
            layer.style.display = on ? 'block' : 'none';
            tools.style.display = on ? 'block' : 'none';
            actions.style.display = on ? 'none' : 'flex';
        }

        start.addEventListener('click', function () { open(true); });
        card.querySelector('.pr-cancel').addEventListener('click', function () { reset(); open(false); });
        card.querySelector('.pr-undo').addEventListener('click', function () {
            var r = rects.pop();
            if (r && r.el.parentNode) { r.el.parentNode.removeChild(r.el); }
        });
        card.querySelector('.pr-save').addEventListener('click', function () {
            if (!rects.length) { return; }
            input.value = JSON.stringify(rects.map(function (r) {
                return { x: r.x, y: r.y, w: r.w, h: r.h, m: r.m };
            }));
            form.submit();
        });

        layer.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            var p = pos(e);
            sx = p.x; sy = p.y; drawing = true;
            cur = { x: sx, y: sy, w: 0, h: 0, m: modeSel.value, el: document.createElement('div') };
            layer.appendChild(cur.el);
            paint(cur);
            if (layer.setPointerCapture) { layer.setPointerCapture(e.pointerId); }
        });
        layer.addEventListener('pointermove', function (e) {
            if (!drawing || !cur) { return; }
            var p = pos(e);
            cur.x = Math.min(sx, p.x);
            cur.y = Math.min(sy, p.y);
            cur.w = Math.abs(p.x - sx);
            cur.h = Math.abs(p.y - sy);
            paint(cur);
        });
        function finish() {
            if (!drawing || !cur) { return; }
            drawing = false;
            if (cur.w < 0.01 || cur.h < 0.01) {
                if (cur.el.parentNode) { cur.el.parentNode.removeChild(cur.el); }
            } else {
                rects.push(cur);
            }
            cur = null;
        }
        layer.addEventListener('pointerup', finish);
        layer.addEventListener('pointercancel', finish);
    });
})();
</script>
@endpush