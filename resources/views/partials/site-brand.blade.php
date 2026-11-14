@php
    $rpLogo = \App\Support\AppearanceSettings::logoUrl();
    $rpTag  = \App\Support\AppearanceSettings::tagline();
@endphp
@if ($rpLogo)
    <img src="{{ $rpLogo }}" alt="" class="h-8 w-auto max-w-[160px] object-contain">
@else
    <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white">R</span>
@endif
<span class="leading-tight">
    <span class="block">{{ \App\Support\AppearanceSettings::siteName() }}</span>
    @if ($rpTag !== '')
        <span class="block text-[10px] font-medium text-slate-400">{{ $rpTag }}</span>
    @endif
</span>