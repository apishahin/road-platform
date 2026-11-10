@extends('layouts.app')

@section('title', __('login.title'))

@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-bold text-slate-900">{{ __('login.title') }}</h1>

        @if (! $s['otp_enabled'] && ! $s['password_enabled'])
            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ __('loginpw.none') }}</p>
        @endif

        @if ($s['otp_enabled'])
            <p class="mt-1 text-sm text-slate-600">{{ __('login.intro') }}</p>

            <form method="POST" action="{{ url('/login') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="phone" class="text-sm font-medium text-slate-700">{{ __('login.phone') }}</label>
                    <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                           value="{{ old('phone') }}" placeholder="01XXXXXXXXX" required maxlength="30"
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-base">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                    {{ __('login.send_code') }}
                </button>
            </form>
        @endif

        @if ($s['password_enabled'])
            <div class="{{ $s['otp_enabled'] ? 'mt-6 border-t border-slate-200 pt-5' : 'mt-5' }}">
                <h2 class="text-base font-semibold text-slate-900">{{ __('loginpw.or_password') }}</h2>

                <form method="POST" action="{{ route('login.password') }}" class="mt-3 space-y-3">
                    @csrf
                    <div>
                        <label for="pw_phone" class="text-sm font-medium text-slate-700">{{ __('login.phone') }}</label>
                        <input id="pw_phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                               value="{{ old('phone') }}" placeholder="01XXXXXXXXX" required maxlength="30"
                               class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-base">
                    </div>
                    <div>
                        <label for="pw_password" class="text-sm font-medium text-slate-700">{{ __('loginpw.password') }}</label>
                        <input id="pw_password" name="password" type="password" autocomplete="current-password"
                               required maxlength="100"
                               class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-base">
                        @error('pwlogin')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit"
                            class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                        {{ __('loginpw.login_btn') }}
                    </button>
                </form>
            </div>

            @if ($s['registration_open'])
                <div class="mt-6 border-t border-slate-200 pt-5">
                    <h2 class="text-base font-semibold text-slate-900">{{ __('loginpw.register_title') }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ __('loginpw.register_hint') }}</p>

                    <form method="POST" action="{{ route('login.register') }}" class="mt-3 space-y-3">
                        @csrf
                        <div>
                            <label for="reg_phone" class="text-sm font-medium text-slate-700">{{ __('login.phone') }}</label>
                            <input id="reg_phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                                   value="{{ old('phone') }}" placeholder="01XXXXXXXXX" required maxlength="30"
                                   class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-base">
                        </div>
                        <div>
                            <label for="reg_password" class="text-sm font-medium text-slate-700">{{ __('loginpw.password') }}</label>
                            <input id="reg_password" name="password" type="password" autocomplete="new-password"
                                   required minlength="8" maxlength="100"
                                   class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-base">
                        </div>
                        <div>
                            <label for="reg_password2" class="text-sm font-medium text-slate-700">{{ __('loginpw.password_confirm') }}</label>
                            <input id="reg_password2" name="password_confirmation" type="password" autocomplete="new-password"
                                   required minlength="8" maxlength="100"
                                   class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-base">
                            @error('register')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit"
                                class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                            {{ __('loginpw.register_btn') }}
                        </button>
                    </form>
                </div>
            @endif
        @endif

        <p class="mt-4 text-xs text-slate-500">{{ __('login.privacy') }}</p>
    </div>
</div>
@endsection