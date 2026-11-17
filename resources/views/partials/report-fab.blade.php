{{-- Floating "Report" button (FAB). Shown on every page; label, colour,
     size, position and on/off are controlled from
     admin → Homepage & site → Floating buttons.
     Position/size use inline styles so no CSS rebuild is needed. --}}
@php
    $__fl = \App\Support\SiteSections::all()['float'] ?? [];
    $__fabOn    = $__fl['fab_on'] ?? true;
    $__fabLabel = ($__fl['fab_label'] ?? '') !== '' ? $__fl['fab_label'] : __('reportcta.fab');
    $__fabColor = $__fl['fab_color'] ?? '#059669';
    $__fb = (int) ($__fl['fab_bottom'] ?? 16);
    $__fs = (int) ($__fl['fab_side'] ?? 16);
    $__fabPosCss = match ($__fl['fab_pos'] ?? 'br') {
        'bl' => "left:{$__fs}px;bottom:{$__fb}px;",
        'bc' => "left:50%;bottom:{$__fb}px;transform:translateX(-50%);",
        default => "right:{$__fs}px;bottom:{$__fb}px;",
    };
    [$__fabPad, $__fabFs, $__fabIc] = match ($__fl['fab_size'] ?? 'md') {
        'sm' => ['8px 12px 8px 10px', '12px', '16px'],
        'lg' => ['14px 20px 14px 16px', '16px', '24px'],
        default => ['10px 16px 10px 14px', '14px', '20px'],
    };
@endphp
@if ($__fabOn)
    <a href="{{ route('report.create') }}" data-rp-fab
       class="fixed z-40 flex items-center gap-2 rounded-full font-bold text-white shadow-lg"
       style="{{ $__fabPosCss }}padding: {{ $__fabPad }}; font-size: {{ $__fabFs }}; background: {{ $__fabColor }}; box-shadow: 0 10px 25px -5px {{ $__fabColor }}4d;">
        <svg style="width: {{ $__fabIc }}; height: {{ $__fabIc }};" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14"/>
        </svg>
        <span>{{ $__fabLabel }}</span>
    </a>
@endif
