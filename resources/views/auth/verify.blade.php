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

        <div class="mt-4 flex items-center justify-between gap-3 text-sm">
            <div>
                <form method="POST" action="{{ route('login.resend') }}">
                    @csrf
                    <button id="otp-resend-button" type="submit" @disabled($resendWaitSeconds > 0)
                            class="text-brand-700 underline disabled:cursor-not-allowed disabled:opacity-50">{{ __('login.resend') }}</button>
                </form>
                <p id="otp-resend-countdown" class="mt-1 text-xs text-slate-500" aria-live="polite"
                   data-seconds="{{ $resendWaitSeconds }}"
                   data-template="{{ __('login.resend_wait_seconds', ['seconds' => '__seconds__']) }}"></p>
            </div>
            <a href="{{ route('login') }}" class="shrink-0 text-slate-600 underline">{{ __('login.change_phone') }}</a>
        </div>

        @if ($resendWaitSeconds > 0)
            <script>
            (() => {
                const note = document.getElementById('otp-resend-countdown');
                const button = document.getElementById('otp-resend-button');
                if (!note || !button) return;

                let seconds = Number(note.dataset.seconds) || 0;
                const render = () => {
                    if (seconds <= 0) {
                        button.disabled = false;
                        note.textContent = '';
                        return true;
                    }
                    button.disabled = true;
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