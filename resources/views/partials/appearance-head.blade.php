@php
    $rpa = \App\Support\AppearanceSettings::all();
    $rpaFontCss = \App\Support\AppearanceSettings::fontStylesheet($rpa['font']);
@endphp
@if ($rpaFontCss)
    <link href="{{ $rpaFontCss }}" rel="stylesheet">
@endif
<style>{!! \App\Support\AppearanceSettings::css() !!}</style>