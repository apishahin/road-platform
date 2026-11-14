@php
    $rpLogo = \App\Support\AppearanceSettings::logoUrl();
    $rpTag  = \App\Support\AppearanceSettings::tagline();
@endphp
@if ($rpLogo)
    <img src="{{ $rpLogo }}" alt="" class="rp-brand-logo" style="height:38px;width:auto;max-width:150px;object-fit:contain;">
@else
    <span class="rp-brand-mark" aria-hidden="true">
        <svg style="width:21px;height:21px;" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 7 7 17m10-10H9.5m7.5 0V14.5"/></svg>
    </span>
@endif
<span class="brand-copy">
    <strong>{{ \App\Support\AppearanceSettings::siteName() }}</strong>
    @if ($rpTag !== '')
        <small>{{ $rpTag }}</small>
    @endif
</span>
