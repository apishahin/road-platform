@extends('layouts.app')

@section('title', __('acctphone.title'))

@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-bold text-slate-900">{{ __('acctphone.title') }}</h1>

        @if ($errors->any())
            <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</p>
        @endif
        @if (session('status'))
            <p class="mt-3 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700">{{ session('status') }}</p>
        @endif

        @if ($verified)
            <div class="mt-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <p class="font-semibold">{{ __('acctphone.status_verified') }}</p>
                <p class="mt-1">{{ \App\Support\Phone::mask((string) $user->phone) }}</p>
            </div>
            <a href="{{ route('account.delete') }}" class="mt-6 block text-center text-xs text-red-500 underline">{{ __('acctphone.del_link') }}</a>

        <a href="{{ route('home') }}" class="mt-3 block text-center text-sm text-slate-600 underline">{{ __('acctphone.back_home') }}</a>
        @elseif ($provider === 'firebase')
            {{-- Firebase sends the SMS from the browser --}}
            <p class="mt-1 text-sm text-slate-600">
                {{ $noPhone ? __('acctphone.intro_none') : __('acctphone.intro_unverified') }}
            </p>

            @if ($noPhone)
                <form method="POST" action="{{ route('account.phone.send') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="phone" class="text-sm font-medium text-slate-700">{{ __('login.phone') }}</label>
                        <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                               value="{{ old('phone') }}" placeholder="01XXXXXXXXX" required maxlength="30"
                               class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-base">
                    </div>
                    <button type="submit"
                            class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                        {{ __('login.send_code') }}
                    </button>
                </form>
            @else
                <div id="firebase-login" class="mt-5 space-y-4"
                     data-api-key="{{ $fb['apiKey'] }}"
                     data-auth-domain="{{ $fb['authDomain'] }}"
                     data-project-id="{{ $fb['projectId'] }}"
                     data-phone="{{ $target }}"
                     data-verify-url="{{ route('account.phone.firebase') }}"
                     data-csrf="{{ csrf_token() }}"
                     data-text-sending="{{ __('loginsettings.fbl_sending') }}"
                     data-text-sent="{{ __('loginsettings.fbl_sent') }}"
                     data-text-resend="{{ __('loginsettings.fbl_resend') }}"
                     data-text-wrong="{{ __('loginsettings.fbl_code_wrong') }}"
                     data-text-too-many="{{ __('loginsettings.fbl_too_many') }}"
                     data-text-error="{{ __('loginsettings.fbl_error') }}"
                     data-text-checking="{{ __('loginsettings.fbl_checking') }}">

                    <p class="text-sm text-slate-600">{{ __('acctphone.code_to') }} <strong>{{ $masked }}</strong></p>
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
            @endif
        @else
            {{-- log/gateway providers: classic server-side OTP --}}
            <p class="mt-1 text-sm text-slate-600">
                {{ $noPhone ? __('acctphone.intro_none') : __('acctphone.intro_unverified') }}
            </p>

            @if ($noPhone)
                <form method="POST" action="{{ route('account.phone.send') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="phone" class="text-sm font-medium text-slate-700">{{ __('login.phone') }}</label>
                        <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                               value="{{ old('phone') }}" placeholder="01XXXXXXXXX" required maxlength="30"
                               class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-base">
                    </div>
                    <button type="submit"
                            class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                        {{ __('login.send_code') }}
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('account.phone.send') }}" class="mt-5">
                    @csrf
                    <p class="text-sm text-slate-600">{{ __('acctphone.code_to') }} <strong>{{ $masked }}</strong></p>
                    <button type="submit"
                            class="mt-3 w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                        {{ $sent ? __('acctphone.resend') : __('login.send_code') }}
                    </button>
                </form>

                @if ($sent)
                    <form method="POST" action="{{ route('account.phone.verify') }}" class="mt-5 space-y-3">
                        @csrf
                        <label for="code" class="text-sm font-medium text-slate-700">{{ __('login.code') }}</label>
                        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required
                               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-center text-xl tracking-widest">
                        <button type="submit"
                                class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                            {{ __('login.verify') }}
                        </button>
                    </form>
                @endif

                <p class="mt-4 text-xs text-slate-500">
                    {{ __('acctphone.wrong_number') }}
                </p>
            @endif
        @endif

        
    </div>
</div>
@endsection
