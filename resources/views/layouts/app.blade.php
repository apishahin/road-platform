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

    {{-- Footer --}}
    <footer class="border-t border-slate-200 bg-white">
        @php $__site = \App\Support\SiteSections::all(); @endphp
        <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-slate-600 sm:py-8">
            @if ($__site['footer']['links_on'])
                <nav class="mb-4 flex flex-wrap gap-x-5 gap-y-2 text-sm font-medium text-slate-600" aria-label="Footer">
                    <a href="{{ route('home') }}" class="hover:text-slate-900">{{ __('Home') }}</a>
                    <a href="{{ route('issues.index') }}" class="hover:text-slate-900">{{ __('Issues') }}</a>
                    <a href="{{ url('/report') }}" class="hover:text-slate-900">{{ __('Report a problem') }}</a>
                    <a href="{{ route('fund.show') }}" class="hover:text-slate-900">{{ __('issues.site_nav_fund') }}</a>
                    @foreach (\App\Models\Page::menuItems() as $menuPage)
                        <a href="{{ route('page.show', $menuPage->slug) }}" class="hover:text-slate-900">{{ $menuPage->title() }}</a>
                    @endforeach
                </nav>
            @endif
            <p class="max-w-3xl">{{ $__site['footer']['text'] !== '' ? $__site['footer']['text'] : __('Reports are claims submitted by users. A claim is not proof until it is verified, and every status shows its source.') }}</p>
            <p class="mt-3 text-xs text-slate-400">© {{ now()->year }} {{ \App\Support\AppearanceSettings::siteName() }}</p>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
