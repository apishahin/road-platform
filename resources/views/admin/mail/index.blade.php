@extends('layouts.app')

@section('title', __('mailsettings.title'))

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('mailsettings.title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('mailsettings.intro') }}</p>
    <p class="mt-2 text-sm">{{ __('mailsettings.current') }}: <strong>{{ $current }}</strong></p>

    @if(session('status'))
        <div style="margin-top:12px;padding:8px 12px;border-radius:8px;background:#ecfdf5;border:1px solid #a7f3d0;font-size:14px;">{{ session('status') }}</div>
    @endif
    @if(session('mail_error'))
        <div style="margin-top:12px;padding:8px 12px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;font-size:14px;">{{ __('mailsettings.test_failed') }}: {{ session('mail_error') }}</div>
    @endif
    @if($errors->any())
        <div style="margin-top:12px;padding:8px 12px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;font-size:14px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.mail.save') }}" autocomplete="off" style="margin-top:16px;">
        @csrf
        <div style="font-weight:600;">{{ __('mailsettings.mode') }}</div>
        @foreach(['log','smtp','gmail'] as $m)
            <label style="display:block;margin-top:6px;"><input type="radio" name="mode" value="{{ $m }}" @checked(old('mode', $s['mode']) === $m)> {{ __('mailsettings.mode_' . $m) }}</label>
        @endforeach

        <p id="hLog" style="margin-top:10px;font-size:13px;color:#64748b;">{{ __('mailsettings.hint_log') }}</p>
        <p id="hSmtp" style="margin-top:10px;font-size:13px;color:#64748b;">{{ __('mailsettings.hint_smtp') }}</p>
        <p id="hGmail" style="margin-top:10px;font-size:13px;color:#64748b;">{{ __('mailsettings.hint_gmail') }}</p>

        <div id="boxSmtp" style="margin-top:8px;">
            <label style="display:block;font-size:14px;">{{ __('mailsettings.host') }}</label>
            <input type="text" name="host" value="{{ old('host', $s['host']) }}" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;">
            <div style="display:flex;gap:8px;margin-top:8px;">
                <div style="flex:1;">
                    <label style="display:block;font-size:14px;">{{ __('mailsettings.port') }}</label>
                    <input type="text" name="port" value="{{ old('port', $s['port']) }}" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block;font-size:14px;">{{ __('mailsettings.encryption') }}</label>
                    <select name="encryption" style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;">
                        @foreach(['tls','ssl','none'] as $e)
                            <option value="{{ $e }}" @selected(old('encryption', $s['encryption']) === $e)>{{ __('mailsettings.enc_' . $e) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div id="boxAuth" style="margin-top:8px;">
            <label style="display:block;font-size:14px;"><span id="lU1">{{ __('mailsettings.username') }}</span><span id="lU2">{{ __('mailsettings.username_gmail') }}</span></label>
            <input type="text" name="username" value="{{ old('username', $s['username']) }}" autocomplete="off" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;">
            <label style="display:block;font-size:14px;margin-top:8px;">{{ __('mailsettings.password') }}</label>
            <input type="password" name="password" value="" autocomplete="new-password" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;">
            <div style="font-size:12px;color:#64748b;margin-top:4px;">{{ $hasPassword ? __('mailsettings.password_set') : __('mailsettings.password_none') }}</div>
        </div>

        <div style="margin-top:12px;">
            <label style="display:block;font-size:14px;">{{ __('mailsettings.from_address') }}</label>
            <input type="text" name="from_address" value="{{ old('from_address', $s['from_address']) }}" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;">
            <label style="display:block;font-size:14px;margin-top:8px;">{{ __('mailsettings.from_name') }}</label>
            <input type="text" name="from_name" value="{{ old('from_name', $s['from_name']) }}" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;">
        </div>

        <button type="submit" style="margin-top:14px;padding:8px 18px;border-radius:8px;background:#0f172a;color:#fff;border:0;">{{ __('mailsettings.save') }}</button>
    </form>

    <h2 style="margin-top:28px;font-weight:600;">{{ __('mailsettings.test_title') }}</h2>
    <form method="POST" action="{{ route('admin.mail.test') }}" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">
        @csrf
        <input type="text" name="to" value="{{ old('to') }}" placeholder="{{ __('mailsettings.test_to') }}" style="flex:1;min-width:220px;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;">
        <button type="submit" style="padding:8px 16px;border-radius:8px;background:#16a34a;color:#fff;border:0;">{{ __('mailsettings.test_send') }}</button>
    </form>
</div>

<script>
(function () {
    function upd() {
        var m = document.querySelector('input[name=mode]:checked').value;
        document.getElementById('boxSmtp').style.display = m === 'smtp' ? 'block' : 'none';
        document.getElementById('boxAuth').style.display = m === 'log' ? 'none' : 'block';
        document.getElementById('hLog').style.display = m === 'log' ? 'block' : 'none';
        document.getElementById('hSmtp').style.display = m === 'smtp' ? 'block' : 'none';
        document.getElementById('hGmail').style.display = m === 'gmail' ? 'block' : 'none';
        document.getElementById('lU1').style.display = m === 'gmail' ? 'none' : 'inline';
        document.getElementById('lU2').style.display = m === 'gmail' ? 'inline' : 'none';
    }
    document.querySelectorAll('input[name=mode]').forEach(function (r) { r.addEventListener('change', upd); });
    upd();
})();
</script>
@endsection