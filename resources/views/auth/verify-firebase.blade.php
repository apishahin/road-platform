@extends('layouts.app')

@section('title', __('login.verify_title'))

@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-bold text-slate-900">{{ __('login.verify_title') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ $masked }}</p>

        <div id="firebase-login" class="mt-5 space-y-4"
             data-api-key="{{ $fb['apiKey'] }}"
             data-auth-domain="{{ $fb['authDomain'] }}"
             data-project-id="{{ $fb['projectId'] }}"
             data-phone="{{ $phone }}"
             data-resend-wait-seconds="{{ $otpResendSeconds }}"
             data-verify-url="{{ route('login.firebase.verify') }}"
             data-csrf="{{ csrf_token() }}"
             data-text-sending="{{ __('loginsettings.fbl_sending') }}"
             data-text-sent="{{ __('loginsettings.fbl_sent') }}"
             data-text-resend="{{ __('loginsettings.fbl_resend') }}"
             data-text-resend-wait="{{ __('login.resend_wait_seconds', ['seconds' => '__seconds__']) }}"
             data-text-wrong="{{ __('loginsettings.fbl_code_wrong') }}"
             data-text-too-many="{{ __('loginsettings.fbl_too_many') }}"
             data-text-error="{{ __('loginsettings.fbl_error') }}"
             data-text-checking="{{ __('loginsettings.fbl_checking') }}">

            <button type="button" id="fb-send"
                    class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">
                {{ __('loginsettings.fbl_send') }}
            </button>
            <div id="fb-recaptcha"></div>
            <p id="fb-status" class="text-sm text-slate-700" aria-live="polite"></p>

            <form id="fb-code-form" class="space-y-3" hidden>
                <label for="fb-code" class="text-sm font-medium text-slate-700">{{ __('login.code') }}</label>
                <input id="fb-code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                       class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-center text-xl tracking-widest">
                <button type="submit" id="fb-confirm"
                        class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">
                    {{ __('loginsettings.fbl_confirm') }}
                </button>
            </form>

            <p class="text-xs text-slate-500">{{ __('loginsettings.fbl_notice') }}</p>
            <a href="{{ route('login') }}" class="block text-center text-sm text-slate-600 underline">{{ __('loginsettings.fbl_change') }}</a>
        </div>
    </div>
</div>
@endsection