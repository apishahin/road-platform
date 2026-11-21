@extends('layouts.app')

@section('title', __('login.verify_title'))

@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-bold text-slate-900">{{ __('login.verify_title') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('login.verify_intro', ['phone' => $masked]) }}</p>

        @if (app()->environment('local'))
            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                {{ __('login.dev_hint') }}
            </p>
        @endif

        <form method="POST" action="{{ route('login.verify.submit') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <label for="code" class="text-sm font-medium text-slate-700">{{ __('login.code') }}</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                       maxlength="12" required autofocus placeholder="••••••"
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-center text-xl tracking-widest">
                @error('code')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                {{ __('login.verify') }}
            </button>
        </form>

        @if (session('otp_resend_notice'))
            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-900" role="status" aria-live="polite">
                <p class="font-bold">{{ __('login.resend_wait_title') }}</p>
                <p class="mt-0.5">{{ session('otp_resend_notice') }}</p>
            </div>
        @endif

        <div class="mt-4 flex items-center justify-between gap-3 text-sm">
            <div class="min-w-0">
                <form id="otp-resend-form" method="POST" action="{{ route('login.resend') }}" @if ($resendWaitSeconds > 0) hidden data-resend-hidden @endif>
                    @csrf
                    <button id="otp-resend-button" type="submit" class="font-semibold text-brand-700 underline hover:text-brand-800">{{ __('login.resend') }}</button>
                </form>
                <div id="otp-resend-countdown" @if ($resendWaitSeconds <= 0) hidden @endif
                     class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600" aria-live="polite"
                     data-seconds="{{ $resendWaitSeconds }}"
                     data-template="{{ __('login.resend_wait_seconds', ['seconds' => '__seconds__']) }}"></div>
            </div>
            <a href="{{ route('login') }}" class="shrink-0 text-slate-600 underline">{{ __('login.change_phone') }}</a>
        </div>

        @if ($resendWaitSeconds > 0)
            <script>
            (() => {
                const note = document.getElementById('otp-resend-countdown');
                const form = document.getElementById('otp-resend-form');
                if (!note || !form) return;

                let seconds = Number(note.dataset.seconds) || 0;
                const render = () => {
                    if (seconds <= 0) {
                        note.hidden = true;
                        note.textContent = '';
                        form.hidden = false;
                        return true;
                    }
                    note.hidden = false;
                    note.textContent = (note.dataset.template || '').replace('__seconds__', String(seconds));
                    seconds -= 1;
                    return false;
                };
                if (!render()) {
                    const timer = window.setInterval(() => {
                        if (render()) window.clearInterval(timer);
                    }, 1000);
                }
            })();
            </script>
        @endif
    </div>
</div>
@endsection