@extends('layouts.app')

@section('title', __('glogin.button'))

@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm text-center">
        <h1 class="text-xl font-bold text-slate-900">{{ __('glogin.button') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('glogin.intro') }}</p>

        <div id="google-login" class="mt-5"
             data-api-key="{{ $fb['apiKey'] }}"
             data-auth-domain="{{ $fb['authDomain'] }}"
             data-project-id="{{ $fb['projectId'] }}"
             data-verify-url="{{ route('login.google.verify') }}"
             data-csrf="{{ csrf_token() }}"
             data-text-signing="{{ __('glogin.status_signing') }}"
             data-text-checking="{{ __('glogin.status_checking') }}"
             data-text-error="{{ __('glogin.error') }}"
             data-text-closed="{{ __('glogin.closed') }}"
             data-text-banned="{{ __('glogin.banned') }}"
             data-text-too-many="{{ __('glogin.too_many') }}">

            <button type="button" id="gl-btn"
                    class="mx-auto flex w-full items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-1 .67-2.28 1.07-3.71 1.07-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.67-.35-1.39-.35-2.09s.14-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                {{ __('glogin.button') }}
            </button>
            <p id="gl-status" class="mt-3 text-sm text-slate-700" aria-live="polite"></p>

            <a href="{{ route('login') }}" class="mt-4 block text-sm text-slate-600 underline">{{ __('glogin.back') }}</a>
        </div>
    </div>
</div>
@endsection
