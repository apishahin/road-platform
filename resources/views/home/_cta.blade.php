{{-- Community CTA band (reference port): deep-green gradient + glow handshake; admin sets title/text/button/URL. --}}
@php
    $S = \App\Support\SiteSections::class;
    $ctaUrl = $S::txt('cta', 'url', '');
    if ($ctaUrl === '') { $ctaUrl = url('/report'); }
@endphp
<div class="rp-wrap">
    <div class="community-cta" data-cta-band>
        <div class="cta-copy">
            <span class="handshake" aria-hidden="true">
                <svg style="width:24px;height:24px;" fill="currentColor" viewBox="0 0 24 24"><path d="M11.17 2.06 7.5 5.2l-.06.07a3.5 3.5 0 0 0 4.6 4.96l.6-.48a1.35 1.35 0 0 1 1.76 2.03l-.74.62a6 6 0 0 0 1.44 4.1l4.57-4.68a3.5 3.5 0 0 0 .06-4.83l-3.6-3.86a3.49 3.49 0 0 0-4.96-.07Z"/><path d="M2.06 12.83l3.86 3.6a3.5 3.5 0 0 0 4.83.06l.67-.65-.35-.5a6.06 6.06 0 0 1-1.2-3.2l-.27.22a3.5 3.5 0 0 1-4.95-3.75L2.06 12.83Z"/></svg>
            </span>
            <div>
                <h2>{{ $S::txt('cta', 'title', __('issues.cta_title')) }}</h2>
                <p>{{ $S::txt('cta', 'text', __('issues.cta_text')) }}</p>
            </div>
        </div>
        <a href="{{ $ctaUrl }}" class="cta-btn">{{ $S::txt('cta', 'btn', __('issues.cta_btn')) }} →</a>
    </div>
</div>
