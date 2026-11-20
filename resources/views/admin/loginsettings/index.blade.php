@extends('layouts.app')

@section('title', __('loginsettings.title'))

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('loginsettings.title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('loginsettings.intro') }}</p>

    @if($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif
    @if(session('status'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.loginsettings.save') }}" autocomplete="off" class="mt-6">
        @csrf
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">{{ __('loginsettings.methods_title') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('loginsettings.methods_hint') }}</p>

            @php
                $rows = [
                    ['otp_enabled', 'otp', 'otp_hint', false],
                    ['google_enabled', 'google', 'google_hint', false],
                    ['password_enabled', 'password', 'password_hint', false],
                    ['registration_open', 'registration', 'registration_hint', false],
                    ['password_for_staff', 'staff_password', 'staff_hint', false],
                ];
            @endphp
            @foreach ($rows as $r)
                @php $on = (bool) old($r[0], $s[$r[0]]) && ! $r[3]; @endphp
                <div class="mt-3 rounded-lg border border-slate-200 p-3">
                    <input type="hidden" name="{{ $r[0] }}" value="0">
                    <label class="flex cursor-pointer gap-3 text-sm font-semibold text-slate-800">
                        <input type="checkbox" name="{{ $r[0] }}" value="1" class="mt-1 h-4 w-4" @checked($on) @disabled($r[3])>
                        <span>
                            {{ __('loginsettings.' . $r[1]) }}
                            <span class="mt-1 block text-xs font-normal leading-5 text-slate-500">{{ __('loginsettings.' . $r[2]) }}</span>
                        </span>
                    </label>
                </div>
            @endforeach
        </section>

        <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">{{ __('loginsettings.otp_title') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('loginsettings.otp_section_hint') }}</p>

            <div class="mt-4">
                <label class="block text-sm font-semibold text-slate-800">{{ __('loginsettings.provider') }}</label>
                <select name="otp_provider" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                    @foreach (['log', 'gateway', 'firebase', 'telegram'] as $p)
                        <option value="{{ $p }}" @disabled($p === 'telegram') @selected(old('otp_provider', $s['otp_provider']) === $p)>{{ __('loginsettings.prov_' . $p) }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('loginsettings.gateway_hint') }}</p>
            </div>

            <div class="mt-5 rounded-lg border border-sky-100 bg-sky-50 p-4">
                <p class="text-sm font-bold text-slate-900">{{ __('loginsettings.firebase_title') }}</p>
                <p class="mt-1 text-xs leading-5 text-slate-600">{{ __('loginsettings.firebase_hint') }}</p>
                <input type="text" name="firebase_api_key" placeholder="AIza..." value="{{ old('firebase_api_key', $s['firebase_api_key'] ?? '') }}" class="mt-3 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                <input type="text" name="firebase_auth_domain" placeholder="xxxx.firebaseapp.com" value="{{ old('firebase_auth_domain', $s['firebase_auth_domain'] ?? '') }}" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                <input type="text" name="firebase_project_id" placeholder="your project id" value="{{ old('firebase_project_id', $s['firebase_project_id'] ?? '') }}" class="mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                <p class="mt-2 text-xs text-slate-500">{{ __('loginsettings.firebase_console') }}</p>
            </div>
        </section>

        <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-900">{{ __('loginsettings.google_title') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('loginsettings.google_section_hint') }}</p>
            <label class="mt-4 block text-sm font-semibold text-slate-800">{{ __('loginsettings.google_phone') }}</label>
            <select name="google_phone" class="mt-1.5 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                <option value="optional" @selected(old('google_phone', $s['google_phone'] ?? 'optional') === 'optional')>{{ __('loginsettings.gphone_optional') }}</option>
                <option value="required" @selected(old('google_phone', $s['google_phone'] ?? 'optional') === 'required')>{{ __('loginsettings.gphone_required') }}</option>
            </select>
            <p class="mt-2 text-xs leading-5 text-slate-500">{{ __('loginsettings.gphone_hint') }}</p>
        </section>

        <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <input type="hidden" name="require_verified_phone" value="0">
            <label class="flex cursor-pointer gap-3 text-sm font-semibold text-slate-800">
                <input type="checkbox" name="require_verified_phone" value="1" class="mt-1 h-4 w-4" @checked((bool) old('require_verified_phone', $s['require_verified_phone'] ?? false))>
                <span>
                    {{ __('acctphone.settings_label') }}
                    <span class="mt-1 block text-xs font-normal leading-5 text-slate-500">{{ __('acctphone.settings_help') }}</span>
                </span>
            </label>
        </section>

        <button type="submit" class="mt-5 rounded-lg bg-emerald-700 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-800">{{ __('loginsettings.save') }}</button>
    </form>

    @include('admin.loginsettings._gateways')
</div>
@endsection
