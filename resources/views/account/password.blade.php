@extends('layouts.app')

@section('title', __('acctpw.title'))

@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-bold text-slate-900">{{ __('acctpw.title') }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $hasPassword ? __('acctpw.intro_change') : __('acctpw.intro_set') }}</p>

        @if (session('pw_ok'))
            <p style="margin-top:12px;padding:8px 12px;border-radius:8px;background:#dcfce7;color:#166534;font-size:14px;">{{ session('pw_ok') }}</p>
        @endif

        @if ($errors->first('pw'))
            <p style="margin-top:12px;padding:8px 12px;border-radius:8px;background:#fee2e2;color:#991b1b;font-size:14px;">{{ $errors->first('pw') }}</p>
        @endif

        <form method="POST" action="{{ route('account.password.save') }}" style="margin-top:16px;">
            @csrf

            @if ($hasPassword)
                <label style="display:block;font-size:14px;font-weight:600;">{{ __('acctpw.current') }}</label>
                <input type="password" name="current_password" autocomplete="current-password" required
                    style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:8px 12px;margin:4px 0 12px 0;">
            @endif

            <label style="display:block;font-size:14px;font-weight:600;">{{ __('acctpw.new') }}</label>
            <input type="password" name="password" autocomplete="new-password" required minlength="8" maxlength="100"
                style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:8px 12px;margin:4px 0 12px 0;">

            <label style="display:block;font-size:14px;font-weight:600;">{{ __('acctpw.confirm') }}</label>
            <input type="password" name="password_confirmation" autocomplete="new-password" required minlength="8" maxlength="100"
                style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:8px 12px;margin:4px 0 16px 0;">

            <button type="submit"
                style="background:#0f172a;color:#fff;border:0;border-radius:8px;padding:10px 18px;font-weight:600;cursor:pointer;">{{ __('acctpw.save') }}</button>
        </form>
    </div>
</div>
@endsection