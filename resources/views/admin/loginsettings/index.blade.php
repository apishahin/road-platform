@extends('layouts.app')

@section('title', __('loginsettings.title'))

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('loginsettings.title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('loginsettings.intro') }}</p>

    <p style="margin-top:12px;padding:8px 12px;border-radius:8px;background:#fffbeb;border:1px solid #fde68a;font-size:14px;">{{ __('loginpw.settings_note') }}</p>

    @if($errors->any())
        <div style="margin-top:12px;padding:8px 12px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;font-size:14px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.loginsettings.save') }}" autocomplete="off" style="margin-top:16px;">
        @csrf
        @php
            $rows = [
                ['otp_enabled', 'otp', 'otp_hint', false],
                ['password_enabled', 'password', 'password_hint', false],
                ['google_enabled', 'google', 'google_hint', false],
                ['registration_open', 'registration', 'registration_hint', false],
                ['password_for_staff', 'staff_password', 'staff_hint', false],
            ];
        @endphp
        @foreach ($rows as $r)
            @php $on = (bool) old($r[0], $s[$r[0]]) && ! $r[3]; @endphp
            <div style="margin-top:14px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;">
                <input type="hidden" name="{{ $r[0] }}" value="0">
                <label style="display:flex;gap:8px;align-items:flex-start;font-size:14px;font-weight:600;">
                    <input type="checkbox" name="{{ $r[0] }}" value="1" style="margin-top:3px;" @checked($on) @disabled($r[3])>
                    <span>{{ __('loginsettings.' . $r[1]) }}</span>
                </label>
                <p style="margin:4px 0 0 24px;font-size:13px;color:#64748b;">{{ __('loginsettings.' . $r[2]) }}</p>
            </div>
        @endforeach

        <div style="margin-top:18px;">
            <label style="display:block;font-size:14px;font-weight:600;">{{ __('loginsettings.provider') }}</label>
            <select name="otp_provider" style="margin-top:6px;width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;">
                @foreach (['log', 'gateway', 'firebase', 'telegram'] as $p)
                    <option value="{{ $p }}" @disabled($p === 'telegram') @selected(old('otp_provider', $s['otp_provider']) === $p)>{{ __('loginsettings.prov_' . $p) }}</option>
                @endforeach
            </select>
        <div style="margin-top:10px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;">
            <p style="font-size:13px;font-weight:600;margin-bottom:8px;">Firebase (Google sends the SMS code)</p>
            <input type="text" name="firebase_api_key" placeholder="AIza..." value="{{ old('firebase_api_key', $s['firebase_api_key'] ?? '') }}" style="width:100%;border:1px solid #cbd5e1;border-radius:6px;padding:7px 10px;font-size:13px;margin-bottom:8px;">
            <input type="text" name="firebase_auth_domain" placeholder="xxxx.firebaseapp.com" value="{{ old('firebase_auth_domain', $s['firebase_auth_domain'] ?? '') }}" style="width:100%;border:1px solid #cbd5e1;border-radius:6px;padding:7px 10px;font-size:13px;margin-bottom:8px;">
            <input type="text" name="firebase_project_id" placeholder="your project id" value="{{ old('firebase_project_id', $s['firebase_project_id'] ?? '') }}" style="width:100%;border:1px solid #cbd5e1;border-radius:6px;padding:7px 10px;font-size:13px;">
            <p style="font-size:12px;color:#64748b;margin-top:6px;">Console: Project settings - General - Your apps (Web app config).</p>
        </div>
        </div>

        <div style="margin-top:18px;">
            <label style="display:block;font-size:14px;font-weight:600;">{{ __('loginsettings.google_phone') }}</label>
            <select name="google_phone" style="margin-top:6px;width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;">
                <option value="required" @selected(old('google_phone', $s['google_phone'] ?? 'required') === 'required')>{{ __('loginsettings.gphone_required') }}</option>
                <option value="optional" @selected(old('google_phone', $s['google_phone'] ?? 'required') === 'optional')>{{ __('loginsettings.gphone_optional') }}</option>
            </select>
            <p style="margin-top:4px;font-size:12px;color:#64748b;">{{ __('loginsettings.gphone_hint') }}</p>
        </div>

        <button type="submit" style="margin-top:18px;padding:10px 18px;border:0;border-radius:8px;background:#ea580c;color:#fff;font-weight:600;cursor:pointer;">{{ __('loginsettings.save') }}</button>
    </form>

    @include('admin.loginsettings._gateways')
</div>
@endsection