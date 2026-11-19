{{-- Homepage partners/sponsors strip. Renders nothing unless enabled and non-empty. --}}
@php
    $__sp = \App\Support\SponsorSettings::section();
@endphp
@if ($__sp['on'] && count($__sp['items']) > 0)
<section data-sponsors class="border-t border-slate-200 bg-white">
    <div class="mx-auto max-w-6xl px-4 py-6 sm:py-8">
        <h2 style="font-size:1.15rem;font-weight:700;color:#0f172a;text-align:center;">
            {{ $__sp['title'] !== '' ? $__sp['title'] : __('issues.sponsors_title') }}
        </h2>
        <div style="margin-top:16px;display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:12px;">
            @foreach ($__sp['items'] as $it)
                @php $inner = '<span style="display:flex;align-items:center;gap:8px;">'
                    . ($it['logo'] !== ''
                        ? '<img src="' . e($it['logo']) . '" alt="' . e($it['name']) . '" loading="lazy" style="height:36px;width:auto;max-width:110px;object-fit:contain;">'
                        : '')
                    . '<span style="font-size:13px;font-weight:600;color:#475569;">' . e($it['name']) . '</span></span>'; @endphp
                @if ($it['url'] !== '')
                    <a href="{{ $it['url'] }}" target="_blank" rel="noopener sponsored"
                       style="border:1px solid #e2e8f0;border-radius:12px;padding:8px 14px;background:#f8fafc;text-decoration:none;">{!! $inner !!}</a>
                @else
                    <div style="border:1px solid #e2e8f0;border-radius:12px;padding:8px 14px;background:#f8fafc;">{!! $inner !!}</div>
                @endif
            @endforeach
        </div>
    </div>
</section>
@endif
