{{-- Floating "back to top" button. Appears after scrolling down; colour,
     size, position and on/off are controlled from
     admin → Homepage & site → Floating buttons.
     Position/size use inline styles so no CSS rebuild is needed. --}}
@php
    $__fl = \App\Support\SiteSections::all()['float'] ?? [];
    $__topOn    = $__fl['top_on'] ?? true;
    $__topColor = $__fl['top_color'] ?? '#334155';
    $__topPosCss = match ($__fl['top_pos'] ?? 'bl') {
        'br' => 'right:16px;bottom:16px;',
        'bc' => 'left:50%;bottom:16px;transform:translateX(-50%);',
        default => 'left:16px;bottom:16px;',
    };
    [$__topBox, $__topIc] = match ($__fl['top_size'] ?? 'md') {
        'sm' => ['36px', '16px'],
        'lg' => ['56px', '26px'],
        default => ['44px', '20px'],
    };
@endphp
@if ($__topOn)
    <button type="button" data-rp-top hidden aria-label="{{ __('issues.float_top_label') }}"
            class="fixed z-40 flex items-center justify-center rounded-full font-bold text-white shadow-lg"
            style="{{ $__topPosCss }}width: {{ $__topBox }}; height: {{ $__topBox }}; padding: 0; background: {{ $__topColor }}; box-shadow: 0 10px 25px -5px {{ $__topColor }}4d;">
        <svg style="width: {{ $__topIc }}; height: {{ $__topIc }};" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m5 15 7-7 7 7"/>
        </svg>
    </button>
    <script>
    (function () {
        var btn = document.querySelector('[data-rp-top]');
        if (!btn) return;
        function tick() {
            var show = window.scrollY > 400;
            btn.hidden = !show;
            btn.style.display = show ? 'flex' : 'none';
        }
        window.addEventListener('scroll', tick, { passive: true });
        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        tick();
    })();
    </script>
@endif
