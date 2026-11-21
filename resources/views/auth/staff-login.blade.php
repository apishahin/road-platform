<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ __('loginpw.staff_page_title') }} — {{ \App\Support\AppearanceSettings::siteName() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.appearance-head')
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
    <main class="mx-auto flex min-h-screen max-w-md items-center px-4 py-10">
        <section class="w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/70">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500">{{ \App\Support\AppearanceSettings::siteName() }}</p>
                    <h1 class="mt-2 text-2xl font-extrabold text-slate-900">{{ __('loginpw.staff_page_title') }}</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ __('loginpw.staff_page_hint') }}</p>
                </div>
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-slate-900 text-white" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                </span>
            </div>

            @if ($errors->has('stafflogin'))
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first('stafflogin') }}</p>
            @endif

            <form method="POST" action="{{ route('staff.login.submit') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="staff_identifier" class="text-sm font-bold text-slate-700">{{ __('loginpw.staff_identifier') }}</label>
                    <input id="staff_identifier" name="staff_identifier" type="text" inputmode="text" autocomplete="username"
                           value="{{ old('staff_identifier') }}" placeholder="{{ __('loginpw.staff_identifier_placeholder') }}" required maxlength="190"
                           class="mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-base shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200">
                </div>
                <div>
                    <label for="staff_password" class="text-sm font-bold text-slate-700">{{ __('loginpw.password') }}</label>
                    <input id="staff_password" name="password" type="password" autocomplete="current-password" required maxlength="100"
                           class="mt-1.5 block w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-base shadow-sm outline-none transition focus:border-slate-900 focus:ring-2 focus:ring-slate-200">
                </div>
                <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2">
                    {{ __('loginpw.staff_login_btn') }}
                </button>
            </form>

            <p class="mt-5 text-center text-xs leading-5 text-slate-500">{{ __('loginpw.staff_page_note') }}</p>
        </section>
    </main>
</body>
</html>
