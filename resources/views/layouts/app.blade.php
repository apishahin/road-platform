<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', __('Road Platform')) — {{ __('Road Platform') }}</title>
    <meta name="description" content="@yield('description', __('A community-powered road repair platform for Bangladesh.'))">

    <meta property="og:site_name" content="{{ __('Road Platform') }}">
    <meta property="og:title" content="@yield('title', __('Road Platform'))">
    <meta property="og:description" content="@yield('description', __('A community-powered road repair platform for Bangladesh.'))">
    <meta property="og:type" content="website">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-800 antialiased">

    {{-- Header --}}
    <header class="sticky top-0 z-[1000] border-b border-slate-200 bg-white/95 backdrop-blur" x-data="{ open: false }">
        <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-bold text-slate-900">
                <span class="inline-flex size-8 items-center justify-center rounded-lg bg-brand-600 text-white">R</span>
                {{ __('Road Platform') }}
            </a>

            <nav class="hidden items-center gap-6 text-sm font-medium text-slate-600 md:flex">
                <a href="{{ route('home') }}" class="hover:text-slate-900">{{ __('Home') }}</a>
                <a href="{{ route('issues.index') }}" class="hover:text-slate-900">{{ __('Issues') }}</a>
                <a href="{{ url('/authorities') }}" class="hover:text-slate-900">{{ __('Authorities') }}</a>
                <a href="{{ url('/how-it-works') }}" class="hover:text-slate-900">{{ __('How it works') }}</a>
            </nav>

            <div class="flex items-center gap-3">
                @if (app()->getLocale() === 'bn')
                    <a href="{{ route('lang', 'en') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">English</a>
                @else
                    <a href="{{ route('lang', 'bn') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">বাংলা</a>
                @endif

                @guest
                    <a href="{{ route('login') }}" class="hidden rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-100 sm:inline-block">{{ __('Login') }}</a>
                @endguest

                @auth
                    <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                        @csrf
                        <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-100">{{ __('login.logout') }}</button>
                    </form>
                @endauth

                @if(auth()->user()?->isModerator())<a href="{{ route('admin.moderation.index') }}" class="hidden text-sm font-medium text-slate-700 hover:text-slate-900 sm:inline-block">Moderation</a>@endif <a href="{{ url('/report') }}" class="hidden rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700 sm:inline-block">{{ __('Report a problem') }}</a>

                <button type="button" class="rounded-lg p-2 hover:bg-slate-100 md:hidden" @click="open = !open" aria-label="Menu">
                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>

        {{-- Mobile menu --}}
        <div x-show="open" x-cloak x-transition class="border-t border-slate-200 bg-white px-4 py-3 md:hidden">
            <div class="flex flex-col gap-3 text-sm font-medium">
                <a href="{{ route('home') }}">{{ __('Home') }}</a>
                <a href="{{ route('issues.index') }}">{{ __('Issues') }}</a>
                <a href="{{ url('/authorities') }}">{{ __('Authorities') }}</a>
                <a href="{{ url('/how-it-works') }}">{{ __('How it works') }}</a>
                @guest
                    <a href="{{ route('login') }}">{{ __('Login') }}</a>
                @endguest
                @auth
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-left">{{ __('login.logout') }}</button>
                    </form>
                @endauth
                @if(auth()->user()?->isModerator())<a href="{{ route('admin.moderation.index') }}">Moderation</a>@endif <a href="{{ url('/report') }}" class="rounded-lg bg-brand-600 px-3 py-2 text-center font-semibold text-white">{{ __('Report a problem') }}</a>
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
        <div class="mx-auto max-w-6xl px-4 py-8 text-sm text-slate-600">
            <p class="max-w-3xl">{{ __('Reports are claims submitted by users. A claim is not proof until it is verified, and every status shows its source.') }}</p>
            <p class="mt-3 text-xs text-slate-400">© {{ now()->year }} {{ __('Road Platform') }}</p>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>