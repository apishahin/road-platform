@php $rpa = \App\Support\AppearanceSettings::all(); @endphp
@if (in_array($rpa['font'], ['noto_google', 'hind_google'], true))
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ \App\Support\AppearanceSettings::googleFontUrl($rpa['font']) }}" rel="stylesheet">
@endif
<style>{!! \App\Support\AppearanceSettings::css() !!}</style>