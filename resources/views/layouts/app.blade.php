<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', __('Road Platform')) — {{ \App\Support\AppearanceSettings::siteName() }}</title>
    <meta name="description" content="@yield('description', __('A community-powered road repair platform for Bangladesh.'))">

    <meta property="og:site_name" content="{{ \App\Support\AppearanceSettings::siteName() }}">
    <meta property="og:title" content="@yield('title', __('Road Platform'))">
    <meta property="og:description" content="@yield('description', __('A community-powered road repair platform for Bangladesh.'))">
    <meta property="og:type" content="website">


    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.appearance-head')
    @stack('head')
</head>
<body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-800 antialiased">

    {{-- Header --}}
    <header class="rp-header sticky top-0 z-[1000] border-b border-slate-200 bg-white/95 backdrop-blur" x-data="{ open: false }">
        <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-bold text-slate-900">
                @include('partials.site-brand')
            </a>

            <nav class="hidden items-center gap-6 text-sm font-medium text-slate-600 md:flex">
                @if (\App\Support\SiteSections::navOn('home'))
                    <a href="{{ route('home') }}" class="hover:text-slate-900">{{ __('Home') }}</a>
                @endif
                @if (\App\Support\SiteSections::navOn('issues'))
                    <a href="{{ route('issues.index') }}" class="hover:text-slate-900">{{ __('Issues') }}</a>
                @endif
                @foreach (\App\Models\Page::menuItems() as $menuPage)
                    <a href="{{ route('page.show', $menuPage->slug) }}" class="hover:text-slate-900">{{ $menuPage->title() }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-3">
                @if (\App\Support\SiteSections::navOn('lang'))
                @if (app()->getLocale() === 'bn')
                    <a href="{{ route('lang', 'en') }}" class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-2.5 py-1 text-xs font-bold text-slate-500 transition-colors hover:border-slate-300 hover:text-slate-900"><svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.5 2.3 3.8 5.2 3.8 8.5s-1.3 6.2-3.8 8.5c-2.5-2.3-3.8-5.3-3.8-8.5S9.5 5.8 12 3.5Z"/></svg>English</a>
                @else
                    <a href="{{ route('lang', 'bn') }}" class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-2.5 py-1 text-xs font-bold text-slate-500 transition-colors hover:border-slate-300 hover:text-slate-900"><svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.5 2.3 3.8 5.2 3.8 8.5s-1.3 6.2-3.8 8.5c-2.5-2.3-3.8-5.3-3.8-8.5S9.5 5.8 12 3.5Z"/></svg>বাংলা</a>
                @endif
                @endif

                @guest
                    <a href="{{ route('login') }}" class="hidden rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-100 sm:inline-block">{{ __('Login') }}</a>
                @endguest

                @include('partials.nav-staff', ['mobile' => false])
                @include('partials.nav-user')
                <button type="button" class="rounded-lg p-2 hover:bg-slate-100 md:hidden" @click="open = !open" aria-label="Menu">
                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>

        {{-- Mobile menu --}}
        <div x-show="open" x-cloak x-transition class="border-t border-slate-200 bg-white px-4 py-3 md:hidden">
            <div class="flex flex-col gap-3 text-sm font-medium">
                @if (\App\Support\SiteSections::navOn('home'))
                    <a href="{{ route('home') }}">{{ __('Home') }}</a>
                @endif
                @if (\App\Support\SiteSections::navOn('issues'))
                    <a href="{{ route('issues.index') }}">{{ __('Issues') }}</a>
                @endif
                @foreach (\App\Models\Page::menuItems() as $menuPage)
                    <a href="{{ route('page.show', $menuPage->slug) }}">{{ $menuPage->title() }}</a>
                @endforeach
                @guest
                    <a href="{{ route('login') }}">{{ __('Login') }}</a>
                @endguest
                @auth
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                        <a href="{{ route('account.password') }}" class="block rounded-lg bg-white px-2.5 py-2 text-sm font-medium text-slate-700 shadow-sm">{{ __('acctpw.title') }}</a>
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

    @if (session('status'))
        <div class="bg-green-50 px-4 py-3 text-center text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <main class="flex-1">
        @yield('content')
    </main>

    {{-- Footer (light or dark theme, social links, back-to-top) --}}
    @php
        $__site  = \App\Support\SiteSections::all();
        $__ftr   = $__site['footer'];
        $__dark  = $__ftr['dark'];
        $__fCls  = $__dark ? 'bg-slate-900 text-slate-300' : 'border-t border-slate-200 bg-white text-slate-600';
        $__aCls  = $__dark ? 'hover:text-white' : 'hover:text-slate-900';
        $__muted = $__dark ? 'text-slate-400' : 'text-slate-500';
        $__faint = $__dark ? 'text-slate-500' : 'text-slate-400';
        $__tag   = \App\Support\AppearanceSettings::tagline();
        $__socials = [];
        foreach (\App\Support\SiteSections::SOCIALS as $__soc) {
            if (($__ftr[$__soc] ?? '') !== '') {
                $__socials[$__soc] = $__ftr[$__soc];
            }
        }
    @endphp
    <footer class="{{ $__fCls }}" @if ($__dark) data-footer-dark @endif>
        <div class="mx-auto max-w-6xl px-4 py-6 text-sm sm:py-8">
            <div class="grid gap-6 sm:grid-cols-3 sm:gap-8">
                {{-- Brand column --}}
                <div>
                    <div class="flex items-center gap-2 text-base font-bold {{ $__dark ? 'text-white' : 'text-slate-900' }}">
                        @include('partials.site-brand')
                    </div>
                    @if ($__tag !== '')
                        <p class="mt-1 text-xs {{ $__muted }}">{{ $__tag }}</p>
                    @endif
                    <p class="mt-3 max-w-xs leading-relaxed {{ $__muted }}">{{ $__ftr['text'] !== '' ? $__ftr['text'] : __('Reports are claims submitted by users. A claim is not proof until it is verified, and every status shows its source.') }}</p>
                </div>

                {{-- Quick links --}}
                @if ($__ftr['links_on'])
                    <nav class="flex flex-wrap content-start gap-x-5 gap-y-2 font-medium sm:justify-center {{ $__dark ? 'text-slate-300' : 'text-slate-600' }}" aria-label="Footer">
                        <a href="{{ route('home') }}" class="{{ $__aCls }}">{{ __('Home') }}</a>
                        <a href="{{ route('issues.index') }}" class="{{ $__aCls }}">{{ __('Issues') }}</a>
                        <a href="{{ url('/report') }}" class="{{ $__aCls }}">{{ __('Report a problem') }}</a>
                        <a href="{{ route('fund.show') }}" class="{{ $__aCls }}">{{ __('issues.site_nav_fund') }}</a>
                        @foreach (\App\Models\Page::menuItems() as $menuPage)
                            <a href="{{ route('page.show', $menuPage->slug) }}" class="{{ $__aCls }}">{{ $menuPage->title() }}</a>
                        @endforeach
                    </nav>
                @else
                    <div></div>
                @endif

                {{-- Social --}}
                <div class="flex items-start gap-2.5 sm:justify-end">
                    @foreach ($__socials as $__soc => $__url)
                        <a href="{{ $__url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('issues.site_footer_' . $__soc) }}"
                           class="grid size-9 place-items-center rounded-full ring-1 transition {{ $__dark ? 'bg-white/10 text-white ring-white/20 hover:bg-white/20' : 'bg-slate-100 text-slate-600 ring-slate-200 hover:bg-slate-200' }}">
                            @if ($__soc === 'facebook')
                                <svg class="size-4" fill="currentColor" viewBox="0 0 24 24"><path d="M13.5 21v-7.7h2.6l.4-3h-3V8.2c0-.87.24-1.47 1.49-1.47h1.6V4.08A21 21 0 0 0 14.36 4c-2.3 0-3.86 1.4-3.86 3.97v2.33H7.9v3h2.6V21h3Z"/></svg>
                            @elseif ($__soc === 'youtube')
                                <svg class="size-4" fill="currentColor" viewBox="0 0 24 24"><path d="M22.4 7.6a2.5 2.5 0 0 0-1.76-1.77C19.08 5.5 12 5.5 12 5.5s-7.08 0-8.64.33A2.5 2.5 0 0 0 1.6 7.6 26.2 26.2 0 0 0 1.27 12a26.2 26.2 0 0 0 .33 4.4 2.5 2.5 0 0 0 1.76 1.77c1.56.33 8.64.33 8.64.33s7.08 0 8.64-.33a2.5 2.5 0 0 0 1.76-1.77 26.2 26.2 0 0 0 .33-4.4 26.2 26.2 0 0 0-.33-4.4ZM9.9 15.03V8.97L15.57 12 9.9 15.03Z"/></svg>
                            @elseif ($__soc === 'x')
                                <svg class="size-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.66l-5.21-6.82-5.97 6.82H1.66l7.73-8.84L1.25 2.25h6.83l4.71 6.23 5.45-6.23Zm-1.16 17.52h1.83L7.08 4.13H5.12l11.96 15.64Z"/></svg>
                            @else
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17M12 3.5c2.5 2.3 3.8 5.2 3.8 8.5s-1.3 6.2-3.8 8.5c-2.5-2.3-3.8-5.3-3.8-8.5S9.5 5.8 12 3.5Z"/></svg>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 border-t pt-4 text-xs {{ $__faint }} {{ $__dark ? 'border-white/10' : 'border-slate-200' }}">
                <p>© {{ now()->year }} {{ \App\Support\AppearanceSettings::siteName() }} — {{ __('issues.footer_rights') }}</p>
            </div>
        </div>
    </footer>

    {{-- Back to top --}}
    <button type="button" id="back-to-top" aria-label="{{ __('issues.back_to_top') }}"
            class="fixed bottom-5 right-5 z-[1000] hidden size-10 place-items-center rounded-full bg-brand-600 text-white shadow-lg shadow-brand-600/30 transition hover:bg-brand-700">
        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m0 0-6 6m6-6 6 6"/></svg>
    </button>
    <script>
        (function () {
            var b = document.getElementById('back-to-top');
            if (! b) { return; }
            window.addEventListener('scroll', function () {
                var on = window.scrollY > 400;
                b.classList.toggle('hidden', ! on);
                b.classList.toggle('grid', on);
            }, { passive: true });
            b.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
        })();
    </script>

    @stack('scripts')
</body>
</html>
