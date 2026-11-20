@extends('layouts.app')

@section('title', __('issues.int_admin_title'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8" data-integrations>
    <h1 class="text-2xl font-bold">{{ __('issues.int_admin_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.int_admin_sub') }}</p>

    {{-- ── Push notifications (FCM) ── --}}
    <form method="POST" action="{{ route('admin.integrations.push') }}" class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
        @csrf
        <label class="flex items-center gap-2 text-sm font-bold">
            <input type="checkbox" name="on" value="1" @checked($push['on']) class="size-4">
            {{ __('issues.int_push_on') }}
        </label>
        <p class="mt-1 text-xs text-slate-500">{{ __('issues.int_push_hint') }}</p>

        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            @foreach (['api_key' => 'API key', 'project_id' => 'Project ID', 'app_id' => 'App ID', 'sender_id' => 'Messaging Sender ID', 'vapid_key' => 'Web Push (VAPID) key'] as $f => $lab)
                <div>
                    <label class="text-xs font-medium text-slate-600">{{ $lab }}</label>
                    <input type="text" name="{{ $f }}" value="{{ $push[$f] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            @endforeach
        </div>
        <div class="mt-3">
            <label class="text-xs font-medium text-slate-600">{{ __('issues.int_push_sa') }}</label>
            <textarea name="sa_json" rows="3" placeholder='{"type":"service_account",…}' class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs"></textarea>
            <p class="mt-1 text-xs text-slate-500">
                {{ $pushSa ? '✅ ' . __('issues.int_push_sa_set') : __('issues.int_push_sa_hint') }}
            </p>
        </div>
        @error('sa_json')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
        <button class="mt-3 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('issues.sp_save_section') }}</button>
    </form>

    {{-- ── Facebook auto-share ── --}}
    <form method="POST" action="{{ route('admin.integrations.share') }}" class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
        @csrf
        <label class="flex items-center gap-2 text-sm font-bold">
            <input type="checkbox" name="on" value="1" @checked($share['on']) class="size-4">
            {{ __('issues.int_share_on') }}
        </label>
        <p class="mt-1 text-xs text-slate-500">{{ __('issues.int_share_hint') }}</p>

        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div>
                <label class="text-xs font-medium text-slate-600">{{ __('issues.int_share_page') }}</label>
                <input type="text" name="fb_page_id" value="{{ $share['fb_page_id'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">{{ __('issues.int_share_token') }}</label>
                <input type="password" name="fb_token" value="" placeholder="{{ $shareTok ? '•••••••• (' . __('issues.int_saved') . ')' : '' }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>
        <button class="mt-3 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('issues.sp_save_section') }}</button>
    </form>

    {{-- ── Weekly digest ── --}}
    <form method="POST" action="{{ route('admin.integrations.digest') }}" class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
        @csrf
        <label class="flex items-center gap-2 text-sm font-bold">
            <input type="checkbox" name="on" value="1" @checked($digest['on']) class="size-4">
            {{ __('issues.int_digest_on') }}
        </label>
        <p class="mt-1 text-xs text-slate-500">{{ __('issues.int_digest_hint') }}</p>

        <div class="mt-3">
            <label class="text-xs font-medium text-slate-600">{{ __('issues.int_digest_emails') }}</label>
            <textarea name="emails" rows="2" placeholder="admin@example.org, owner@example.org" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ implode(', ', $digest['emails']) }}</textarea>
        </div>
        <button class="mt-3 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('issues.sp_save_section') }}</button>
    </form>
</div>
@endsection
