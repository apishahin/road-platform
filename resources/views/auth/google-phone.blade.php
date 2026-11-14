@extends('layouts.app')

@section('title', __('glogin.phone_title'))

@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-bold text-slate-900">{{ __('glogin.phone_title') }}</h1>
        <p class="mt-1 text-sm text-slate-600">
            {{ __('glogin.phone_intro') }}
            @if ($name !== '')<br><span class="font-medium text-slate-700">{{ $name }}</span>@endif
        </p>

        @if ($errors->any())
            <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</p>
        @endif

        @if ($provider === 'firebase')
            {{-- Google Firebase: phone verified by SMS inside the same popup flow --}}
            <div id="firebase-login" class="mt-5 space-y-4"
                 data-api-key="{{ $fb['apiKey'] }}"
                 data-auth-domain="{{ $fb['authDomain'] }}"
                 data-project-id="{{ $fb['projectId'] }}"
                 data-phone=""
                 data-phone-editable="1"
                 data-verify-url="{{ route('login.google.phone.firebase') }}"
                 data-csrf="{{ csrf_token() }}"
                 data-text-sending="{{ __('loginsettings.fbl_sending') }}"
                 data-text-sent="{{ __('loginsettings.fbl_sent') }}"
                 data-text-resend="{{ __('loginsettings.fbl_resend') }}"
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
            </div>
        @else
            {{-- OTP (log/gateway): send the code to the number the user gives --}}
            <form method="POST" action="{{ route('login.google.phone.send') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="phone" class="text-sm font-medium text-slate-700">{{ __('login.phone') }}</label>
                    <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                           value="{{ old('phone', session('google_phone', '')) }}" placeholder="01XXXXXXXXX" required maxlength="30"
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-base">
                </div>
                <button type="submit"
                        class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                    {{ $sent ? __('glogin.resend_code') : __('login.send_code') }}
                </button>
            </form>

            @if ($sent)
                <form method="POST" action="{{ route('login.google.phone.verify') }}" class="mt-5 space-y-3">
                    @csrf
                    <label for="gcode" class="text-sm font-medium text-slate-700">{{ __('login.code') }}</label>
                    <input id="gcode" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required
                           class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-center text-xl tracking-widest">
                    <button type="submit"
                            class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                        {{ __('login.verify') }}
                    </button>
                </form>
            @endif
        @endif

        <a href="{{ route('login') }}" class="mt-5 block text-center text-sm text-slate-600 underline">{{ __('glogin.back') }}</a>
    </div>
</div>
@endsection
