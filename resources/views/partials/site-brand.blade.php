@php $rpLogo = \App\Support\AppearanceSettings::logoUrl(); @endphp
@if ($rpLogo)
    <img src="{{ $rpLogo }}" alt="" class="h-8 w-auto max-w-[160px] object-contain">
@else
    <span class="inline-flex size-8 items-center justify-center rounded-lg bg-brand-600 text-white">R</span>
@endif
{{ \App\Support\AppearanceSettings::siteName() }}