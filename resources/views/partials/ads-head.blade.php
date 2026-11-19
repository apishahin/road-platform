@if (\App\Support\AdSettings::visible() && \App\Support\AdSettings::publisherId() !== '')
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ \App\Support\AdSettings::publisherId() }}" crossorigin="anonymous"></script>
@endif
