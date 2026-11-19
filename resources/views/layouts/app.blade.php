<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#059669">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ \App\Support\AppearanceSettings::siteName() }}">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="{{ \App\Http\Controllers\PwaInstallController::iconUrl() ?? '/icons/apple-touch-icon.png' }}">

    <title>@yield('title', __('Road Platform')) — {{ \App\Support\AppearanceSettings::siteName() }}</title>
    <meta name="description" content="@yield('description', __('A community-powered road repair platform for Bangladesh.'))">
    @include('partials.ads-head')

    <meta property="og:site_name" content="{{ \App\Support\AppearanceSettings::siteName() }}">
    <meta property="og:title" content="@yield('title', __('Road Platform'))">
    <meta property="og:description" content="@yield('description', __('A community-powered road repair platform for Bangladesh.'))">
    <meta property="og:type" content="website">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:image" content="@yield('og_image', \App\Support\SiteSections::ogImage())">
    <meta property="og:locale" content="{{ app()->getLocale() === 'bn' ? 'bn_BD' : 'en_US' }}">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title', __('Road Platform'))">
    <meta name="twitter:description" content="@yield('description', __('A community-powered road repair platform for Bangladesh.'))">
    <meta name="twitter:image" content="@yield('og_image', \App\Support\SiteSections::ogImage())">


    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.appearance-head')
    @include('partials.rp-chrome')
    @stack('head')
</head>
<body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-800 antialiased">

    {{-- Header (reference port: 68px, green active underline, pill buttons) --}}
    <header class="rp-h rp-header sticky top-0 z-[1000]" x-data="{ open: false }">
        <div class="nav-wrap" style="max-width:1320px;margin-inline:auto;padding-inline:16px;">
            <a href="{{ route('home') }}" class="brand">
                @include('partials.site-brand')
            </a>

            <nav class="desktop-nav" aria-label="{{ __('Main menu') }}">
                @if (\App\Support\SiteSections::navOn('home'))
                    <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">{{ __('Home') }}</a>
                @endif
                @if (\App\Support\SiteSections::navOn('issues'))
                    <a href="{{ route('issues.index') }}" class="{{ request()->routeIs('issues.*') || request()->is('issues*') ? 'active' : '' }}">{{ __('Issues') }}</a>
                @endif
                @if (\App\Support\SiteSections::navOn('dashboard'))
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">{{ __('issues.nav_dashboard') }}</a>
                @endif
                @foreach (\App\Models\Page::menuItems() as $menuPage)
                    <a href="{{ route('page.show', $menuPage->slug) }}" class="{{ request()->is('p/' . $menuPage->slug) ? 'active' : '' }}">{{ $menuPage->title() }}</a>
                @endforeach
            </nav>

            <div class="nav-actions">
                @if (\App\Support\SiteSections::navOn('lang'))
                @if (app()->getLocale() === 'bn')
                    <a href="{{ route('lang', 'en') }}" class="rp-pill rp-lang"><svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.5 2.3 3.8 5.2 3.8 8.5s-1.3 6.2-3.8 8.5c-2.5-2.3-3.8-5.3-3.8-8.5S9.5 5.8 12 3.5Z"/></svg>English</a>
                @else
                    <a href="{{ route('lang', 'bn') }}" class="rp-pill rp-lang"><svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.5 2.3 3.8 5.2 3.8 8.5s-1.3 6.2-3.8 8.5c-2.5-2.3-3.8-5.3-3.8-8.5S9.5 5.8 12 3.5Z"/></svg>বাংলা</a>
                @endif
                @endif

                @guest
                    <a href="{{ route('login') }}" class="rp-pill rp-login">{{ __('Login') }}</a>
                @endguest

                @include('partials.nav-staff', ['mobile' => false])
                @include('partials.nav-user')
                <button type="button" class="rp-pill icon-btn hamburger" @click="open = !open" aria-label="Menu">
                    <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>

        {{-- Mobile menu --}}
        <div x-show="open" x-cloak x-transition class="rp-mobilemenu">
            <div style="max-width:1320px;margin-inline:auto;">
                @if (\App\Support\SiteSections::navOn('home'))
                    <a href="{{ route('home') }}">{{ __('Home') }}</a>
                @endif
                @if (\App\Support\SiteSections::navOn('issues'))
                    <a href="{{ route('issues.index') }}">{{ __('Issues') }}</a>
                @endif
                @if (\App\Support\SiteSections::navOn('dashboard'))
                    <a href="{{ route('dashboard') }}">{{ __('issues.nav_dashboard') }}</a>
                @endif
                @foreach (\App\Models\Page::menuItems() as $menuPage)
                    <a href="{{ route('page.show', $menuPage->slug) }}">{{ $menuPage->title() }}</a>
                @endforeach
                @if (\App\Support\SiteSections::navOn('lang'))
                    <a href="{{ route('lang', app()->getLocale() === 'bn' ? 'en' : 'bn') }}">◎ {{ app()->getLocale() === 'bn' ? 'English' : 'বাংলা' }}</a>
                @endif
                @guest
                    <a href="{{ route('login') }}">{{ __('Login') }}</a>
                @endguest
                @auth
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                        <a href="{{ route('account.password') }}" class="block rounded-lg bg-white px-2.5 py-2 text-sm font-medium text-slate-700 shadow-sm">{{ __('acctpw.title') }}</a>
                        <a href="{{ route('account.phone') }}" class="mt-1.5 block rounded-lg bg-white px-2.5 py-2 text-sm font-medium text-slate-700 shadow-sm">{{ __('acctphone.nav_phone') }}</a>
                        <a href="{{ route('account.delete') }}" class="mt-1.5 block rounded-lg bg-rose-50 px-2.5 py-2 text-sm font-bold text-rose-600">{{ __('acctphone.del_link') }}</a>
                        <form method="POST" action="{{ route('logout') }}" class="mt-1.5">
                            @csrf
                            <button type="submit" class="block w-full rounded-lg bg-rose-50 px-2.5 py-2 text-left text-sm font-bold text-rose-600">{{ __('login.logout') }}</button>
                        </form>
                    </div>
                @endauth
                @include('partials.nav-staff', ['mobile' => true])
            </div>
        </div>
    </header>

    @include('partials.phone-banner')

    @if (session('status'))
        <div class="bg-green-50 px-4 py-3 text-center text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <main class="flex-1">
        @yield('content')
    </main>

    {{-- Footer (reference port: deep-green 3-column, social circles, light fallback) --}}
    @php
        $__site  = \App\Support\SiteSections::all();
        $__ftr   = $__site['footer'];
        $__dark  = $__ftr['dark'];
        $__fCls  = $__dark ? 'rp-foot' : 'rp-foot rp-foot-light';
        $__tag   = \App\Support\AppearanceSettings::tagline();
        $__socials = [];
        foreach (\App\Support\SiteSections::SOCIALS as $__soc) {
            if (($__ftr[$__soc] ?? '') !== '') {
                $__socials[$__soc] = $__ftr[$__soc];
            }
        }
        $__socBg = ['facebook' => '#1765c1', 'youtube' => '#e32222', 'x' => '#0f1419', 'website' => '#347edb'];
    @endphp
    @include('partials.ad-slot', ['slot' => 'above_footer'])
    <footer class="{{ $__fCls }}" @if ($__dark) data-footer-dark @endif>
        <div style="max-width:1320px;margin-inline:auto;padding-inline:16px;">
            <div class="footer-main">
                <a href="{{ route('home') }}" class="footer-brand">
                    @include('partials.site-brand')
                </a>
                @if ($__ftr['links_on'])
                    <nav class="footer-links" aria-label="Footer">
                        <a href="{{ route('home') }}">{{ __('Home') }}</a>
                        <a href="{{ route('issues.index') }}">{{ __('Issues') }}</a>
                        <a href="{{ route('dashboard') }}">{{ __('issues.nav_dashboard') }}</a>
                        <a href="{{ url('/report') }}">{{ __('Report a problem') }}</a>
                        <a href="{{ route('fund.show') }}">{{ __('issues.site_nav_fund') }}</a>
                        @foreach (\App\Models\Page::menuItems() as $menuPage)
                            <a href="{{ route('page.show', $menuPage->slug) }}">{{ $menuPage->title() }}</a>
                        @endforeach
                    </nav>
                @else
                    <div></div>
                @endif
                <div class="socials">
                    @foreach ($__socials as $__soc => $__url)
                        <a href="{{ $__url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('issues.site_footer_' . $__soc) }}" style="background:{{ $__socBg[$__soc] ?? '#347edb' }};">
                            @if ($__soc === 'facebook')
                                <svg style="width:15px;height:15px;" fill="currentColor" viewBox="0 0 24 24"><path d="M13.5 21v-7.7h2.6l.4-3h-3V8.2c0-.87.24-1.47 1.49-1.47h1.6V4.08A21 21 0 0 0 14.36 4c-2.3 0-3.86 1.4-3.86 3.97v2.33H7.9v3h2.6V21h3Z"/></svg>
                            @elseif ($__soc === 'youtube')
                                <svg style="width:15px;height:15px;" fill="currentColor" viewBox="0 0 24 24"><path d="M22.4 7.6a2.5 2.5 0 0 0-1.76-1.77C19.08 5.5 12 5.5 12 5.5s-7.08 0-8.64.33A2.5 2.5 0 0 0 1.6 7.6 26.2 26.2 0 0 0 1.27 12a26.2 26.2 0 0 0 .33 4.4 2.5 2.5 0 0 0 1.76 1.77c1.56.33 8.64.33 8.64.33s7.08 0 8.64-.33a2.5 2.5 0 0 0 1.76-1.77 26.2 26.2 0 0 0 .33-4.4 26.2 26.2 0 0 0-.33-4.4ZM9.9 15.03V8.97L15.57 12 9.9 15.03Z"/></svg>
                            @elseif ($__soc === 'x')
                                <svg style="width:14px;height:14px;" fill="currentColor" viewBox="0 0 24 24"><path d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.66l-5.21-6.82-5.97 6.82H1.66l7.73-8.84L1.25 2.25h6.83l4.71 6.23 5.45-6.23Zm-1.16 17.52h1.83L7.08 4.13H5.12l11.96 15.64Z"/></svg>
                            @else
                                <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.5 2.3 3.8 5.2 3.8 8.5s-1.3 6.2-3.8 8.5c-2.5-2.3-3.8-5.3-3.8-8.5S9.5 5.8 12 3.5Z"/></svg>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
            @if ($__tag !== '' || ($__ftr['text'] ?? '') !== '')
                <p class="footer-note" style="text-align:center;max-width:none;">{{ $__tag }}{{ $__tag !== '' && ($__ftr['text'] ?? '') !== '' ? ' · ' : '' }}{{ $__ftr['text'] ?? '' }}</p>
            @endif
            <div class="footer-bottom">© {{ now()->year }} {{ \App\Support\AppearanceSettings::siteName() }} &nbsp; | &nbsp; {{ __('issues.footer_rights') }} &nbsp; | &nbsp; {{ __('issues.footer_mission') }}</div>
        </div>
    </footer>
    <style>
    {{-- light-theme fallback when admin turns the dark footer off --}}
    .rp-foot-light{background:#fff;color:#53645b;border-top:1px solid #e0eae4;}
    .rp-foot-light .footer-brand strong{color:#003d25;}
    .rp-foot-light .footer-brand small{color:#53645b;}
    .rp-foot-light .footer-links{color:#35463d;}
    .rp-foot-light .footer-links a:hover{color:#078a48;}
    .rp-foot-light .footer-note{color:#53645b;}
    .rp-foot-light .footer-bottom{border-top-color:#e0eae4;color:#819087;}
    </style>

    @stack('scripts')
@include('partials.pwa-install')
@include('partials.report-fab')
@include('partials.scroll-top')
@include('partials.popup-ad')
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js');
    });
}
</script>
</body>
</html>
