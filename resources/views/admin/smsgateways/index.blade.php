@extends('layouts.app')

@section('title', __('smsgw.title'))

@section('content')
@php
    $in = 'width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;margin:4px 0 12px 0;';
    $lb = 'display:block;font-size:14px;font-weight:600;';
    $btn = 'border:0;border-radius:8px;padding:6px 12px;font-size:13px;font-weight:600;cursor:pointer;';
@endphp
<div style="max-width:860px;margin:0 auto;padding:24px 16px;">
    <h1 style="font-size:22px;font-weight:700;">{{ __('smsgw.title') }}</h1>
    <p style="margin-top:6px;font-size:14px;color:#475569;">{{ __('smsgw.intro') }}</p>
    <p style="margin-top:8px;padding:8px 12px;border-radius:8px;background:#fef3c7;color:#92400e;font-size:13px;">{{ __('smsgw.key_note') }}</p>

    @if (session('gw_ok'))
        <p style="margin-top:12px;padding:8px 12px;border-radius:8px;background:#dcfce7;color:#166534;font-size:14px;">{{ session('gw_ok') }}</p>
    @endif

    @if (session('gw_fail'))
        <p style="margin-top:12px;padding:8px 12px;border-radius:8px;background:#fee2e2;color:#991b1b;font-size:14px;">{{ session('gw_fail') }}</p>
    @endif

    @if ($errors->any())
        <p style="margin-top:12px;padding:8px 12px;border-radius:8px;background:#fee2e2;color:#991b1b;font-size:14px;">{{ $errors->first() }}</p>
    @endif

    <h2 style="margin-top:22px;font-size:17px;font-weight:700;">{{ __('smsgw.list_title') }}</h2>
    <p style="font-size:12px;color:#64748b;">{{ __('smsgw.test_warn') }}</p>

    @forelse ($gateways as $g)
        <div style="margin-top:10px;padding:12px;border:1px solid #e2e8f0;border-radius:8px;">
            <div style="font-weight:600;">
                {{ $g->name }}
                <span style="font-size:12px;font-weight:400;color:#64748b;">
                    - {{ $g->channel === 'whatsapp' ? __('smsgw.ch_wa') : __('smsgw.ch_sms') }}
                    - {{ $g->priority }}
                    - {{ $g->hasKey() ? __('smsgw.key_yes') : __('smsgw.key_no') }}
                </span>
                <span style="margin-left:6px;font-size:12px;padding:2px 8px;border-radius:999px;background:{{ $g->enabled ? '#dcfce7' : '#e2e8f0' }};color:{{ $g->enabled ? '#166534' : '#475569' }};">{{ $g->enabled ? __('smsgw.on') : __('smsgw.off') }}</span>
            </div>
            @if ($g->last_status)
                <div style="margin-top:6px;font-size:12px;color:{{ $g->last_status === 'ok' ? '#166534' : '#991b1b' }};">
                    {{ __('smsgw.last') }}: {{ $g->last_status }} {{ $g->last_used_at?->format('Y-m-d H:i') }} - {{ $g->last_note }}
                </div>
            @endif
            <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                <a href="{{ route('admin.smsgateways.index', ['edit' => $g->id]) }}" style="{{ $btn }}background:#e2e8f0;color:#0f172a;text-decoration:none;">{{ __('smsgw.edit') }}</a>
                <form method="POST" action="{{ route('admin.smsgateways.toggle', $g->id) }}">
                    @csrf
                    <button type="submit" style="{{ $btn }}background:#0f172a;color:#fff;">{{ $g->enabled ? __('smsgw.turn_off') : __('smsgw.turn_on') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.smsgateways.delete', $g->id) }}" onsubmit="return confirm(@js(__('smsgw.delete_confirm')))">
                    @csrf
                    <button type="submit" style="{{ $btn }}background:#fee2e2;color:#991b1b;">{{ __('smsgw.delete') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.smsgateways.test', $g->id) }}" style="display:flex;gap:6px;align-items:center;">
                    @csrf
                    <input type="text" name="phone" placeholder="{{ __('smsgw.test_phone') }}" required style="border:1px solid #cbd5e1;border-radius:8px;padding:6px 10px;font-size:13px;width:210px;">
                    <button type="submit" style="{{ $btn }}background:#1d4ed8;color:#fff;">{{ __('smsgw.test_btn') }}</button>
                </form>
            </div>
        </div>
    @empty
        <p style="margin-top:10px;font-size:14px;color:#64748b;">{{ __('smsgw.empty') }}</p>
    @endforelse

    <h2 style="margin-top:28px;font-size:17px;font-weight:700;">{{ $editing ? __('smsgw.form_edit') : __('smsgw.form_add') }}</h2>
    @if (!$editing)
        <p style="margin-top:6px;font-size:14px;"><a href="{{ route('admin.smsgateways.index', ['preset' => 'bulksmsbd']) }}" style="color:#1d4ed8;text-decoration:underline;">{{ __('smsgw.preset') }}</a></p>
    @endif

    <form method="POST" action="{{ route('admin.smsgateways.save') }}" style="margin-top:12px;padding:16px;border:1px solid #e2e8f0;border-radius:8px;">
        @csrf
        <input type="hidden" name="id" value="{{ old('id', $form['id']) }}">

        <label style="{{ $lb }}">{{ __('smsgw.name') }}</label>
        <input type="text" name="name" maxlength="80" required value="{{ old('name', $form['name']) }}" style="{{ $in }}">

        <label style="{{ $lb }}">{{ __('smsgw.channel') }}</label>
        <select name="channel" style="{{ $in }}">
            <option value="sms" @selected(old('channel', $form['channel']) === 'sms')>{{ __('smsgw.ch_sms') }}</option>
            <option value="whatsapp" @selected(old('channel', $form['channel']) === 'whatsapp')>{{ __('smsgw.ch_wa') }}</option>
        </select>

        <label style="{{ $lb }}">{{ __('smsgw.method') }}</label>
        <select name="method" style="{{ $in }}">
            <option value="GET" @selected(old('method', $form['method']) === 'GET')>GET</option>
            <option value="POST" @selected(old('method', $form['method']) === 'POST')>POST</option>
        </select>

        <label style="{{ $lb }}">{{ __('smsgw.body_type') }}</label>
        <select name="body_type" style="{{ $in }}">
            <option value="query" @selected(old('body_type', $form['body_type']) === 'query')>{{ __('smsgw.bt_query') }}</option>
            <option value="json" @selected(old('body_type', $form['body_type']) === 'json')>{{ __('smsgw.bt_json') }}</option>
            <option value="form" @selected(old('body_type', $form['body_type']) === 'form')>{{ __('smsgw.bt_form') }}</option>
        </select>

        <label style="{{ $lb }}">{{ __('smsgw.url') }}</label>
        <input type="text" name="url" maxlength="500" required value="{{ old('url', $form['url']) }}" style="{{ $in }}">

        <label style="{{ $lb }}">{{ __('smsgw.params') }}</label>
        <textarea name="params" rows="4" style="{{ $in }}font-family:monospace;font-size:13px;">{{ old('params', $form['params']) }}</textarea>

        <label style="{{ $lb }}">{{ __('smsgw.headers') }}</label>
        <textarea name="headers" rows="2" style="{{ $in }}font-family:monospace;font-size:13px;">{{ old('headers', $form['headers']) }}</textarea>

        <label style="{{ $lb }}">{{ __('smsgw.sender_id') }}</label>
        <input type="text" name="sender_id" maxlength="60" value="{{ old('sender_id', $form['sender_id']) }}" style="{{ $in }}">

        <label style="{{ $lb }}">{{ __('smsgw.marker') }}</label>
        <input type="text" name="success_marker" maxlength="100" value="{{ old('success_marker', $form['success_marker']) }}" style="{{ $in }}">

        <label style="{{ $lb }}">{{ __('smsgw.priority') }}</label>
        <input type="number" name="priority" min="1" max="999" required value="{{ old('priority', $form['priority']) }}" style="{{ $in }}">

        <label style="{{ $lb }}">{{ __('smsgw.api_key') }}</label>
        <input type="password" name="api_key" autocomplete="off" maxlength="500" value="" style="{{ $in }}margin-bottom:4px;">
        @if ($editing)
            <p style="font-size:12px;color:#64748b;margin-bottom:8px;">{{ __('smsgw.api_key_keep') }}</p>
            <label style="display:flex;gap:8px;align-items:center;font-size:13px;margin-bottom:12px;">
                <input type="checkbox" name="clear_key" value="1"> {{ __('smsgw.clear_key') }}
            </label>
        @endif

        <input type="hidden" name="enabled" value="0">
        <label style="display:flex;gap:8px;align-items:center;font-size:14px;font-weight:600;margin-bottom:16px;">
            <input type="checkbox" name="enabled" value="1" @checked((bool) old('enabled', $form['enabled']))> {{ __('smsgw.enabled') }}
        </label>

        <button type="submit" style="background:#0f172a;color:#fff;border:0;border-radius:8px;padding:10px 18px;font-weight:600;cursor:pointer;">{{ __('smsgw.save') }}</button>
    </form>
</div>
@endsection