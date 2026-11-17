{{-- Advertisement / notice popup. Admin → Homepage & site → Advertisement popup:
     text and/or image (uploaded or from URL), optional click-through link,
     show-after delay and re-show frequency. Everything inline-styled. --}}
@php
    $__pp = \App\Support\SiteSections::all()['popup'] ?? [];
    $__ppOn   = $__pp['on'] ?? false;
    $__ppText = $__pp['text'] ?? '';
    $__ppLink = $__pp['link'] ?? '';
    $__ppImg  = $__pp['img'] !== '' ? '/' . \App\Support\SiteSections::POPUP_DIR . '/' . $__pp['img'] : ($__pp['img_url'] ?? '');
    $__ppDelay = (int) ($__pp['delay'] ?? 3);
    $__ppFreq  = $__pp['freq'] ?? 'day';
@endphp
@if ($__ppOn && ($__ppImg !== '' || $__ppText !== ''))
    <div data-rp-popup hidden
         style="position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(2,20,12,.55);">
        <div style="position:relative;max-width:380px;width:100%;background:#fff;border-radius:18px;overflow:hidden;box-shadow:0 24px 60px -12px rgba(0,0,0,.4);">
            <button type="button" data-rp-popup-close aria-label="{{ __('issues.popup_close') }}"
                    style="position:absolute;top:10px;right:10px;z-index:2;width:32px;height:32px;border:0;border-radius:50%;background:rgba(15,23,42,.65);color:#fff;font-size:16px;font-weight:800;cursor:pointer;line-height:1;">✕</button>
            @if ($__ppLink !== '')
                <a href="{{ $__ppLink }}" @if (str_starts_with($__ppLink, 'http')) target="_blank" rel="noopener noreferrer" @endif style="display:block;text-decoration:none;color:inherit;">
            @endif
                @if ($__ppImg !== '')
                    <img src="{{ $__ppImg }}" alt="" style="display:block;width:100%;height:auto;">
                @endif
                @if ($__ppText !== '')
                    <p style="margin:0;padding:18px 20px;font-size:15px;line-height:1.6;color:#0f172a;">{{ $__ppText }}</p>
                @endif
            @if ($__ppLink !== '')
                </a>
            @endif
        </div>
    </div>
    <script>
    (function () {
        var pop = document.querySelector('[data-rp-popup]');
        if (!pop) return;
        var FREQ = @json($__ppFreq);
        var KEY = 'popup.ad.last';
        var now = Date.now();
        try {
            if (FREQ === 'session' && sessionStorage.getItem(KEY)) return;
            if (FREQ === 'day' && now - (parseInt(localStorage.getItem(KEY) || '0', 10) || 0) < 86400000) return;
        } catch (e) {}
        setTimeout(function () { pop.hidden = false; }, {{ $__ppDelay }} * 1000);
        function close() {
            pop.remove();
            try {
                if (FREQ === 'session') { sessionStorage.setItem(KEY, '1'); }
                else if (FREQ === 'day') { localStorage.setItem(KEY, String(now)); }
            } catch (e) {}
        }
        pop.querySelector('[data-rp-popup-close]').addEventListener('click', function (e) {
            e.preventDefault(); e.stopPropagation(); close();
        });
        pop.addEventListener('click', function (e) { if (e.target === pop) close(); });
    })();
    </script>
@endif
