@extends('layouts.app')

@section('title', __('issues.sp_admin_title'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8" data-sponsors-admin>
    <h1 class="text-2xl font-bold">{{ __('issues.sp_admin_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.sp_admin_sub') }}</p>

    {{-- Section on/off --}}
    <form method="POST" action="{{ route('admin.sponsors.save') }}" class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
        @csrf
        <label class="flex items-center gap-2 text-sm font-semibold">
            <input type="checkbox" name="on" value="1" @checked($data['on']) class="size-4">
            {{ __('issues.sp_section_on') }}
        </label>
        <div class="mt-3">
            <label class="text-xs font-medium text-slate-600">{{ __('issues.sp_title_label') }}</label>
            <input type="text" name="title" value="{{ $data['title'] }}" maxlength="80"
                   placeholder="{{ __('issues.sponsors_title') }}"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <p class="mt-1 text-xs text-slate-500">{{ __('issues.sp_title_hint') }}</p>
        </div>
        <button class="mt-3 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('issues.sp_save_section') }}</button>
    </form>

    {{-- Add sponsor --}}
    <form method="POST" action="{{ route('admin.sponsors.add') }}" enctype="multipart/form-data" class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
        @csrf
        <h2 class="font-semibold">{{ __('issues.sp_add') }}</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div>
                <label class="text-xs font-medium text-slate-600">{{ __('issues.sp_name') }} *</label>
                <input type="text" name="name" value="{{ old('name') }}" maxlength="80" required
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">{{ __('issues.sp_url') }}</label>
                <input type="text" name="url" value="{{ old('url') }}" maxlength="300" placeholder="https://…"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">{{ __('issues.sp_logo') }}</label>
                <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,.gif" class="mt-1 w-full text-sm">
                <p class="mt-1 text-xs text-slate-500">{{ __('issues.sp_logo_hint') }}</p>
            </div>
        </div>
        @if ($errors->any())
            <p class="mt-2 text-sm text-rose-600">{{ $errors->first() }}</p>
        @endif
        <button class="mt-3 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('issues.sp_add_btn') }}</button>
    </form>

    {{-- Current sponsors --}}
    <div class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">{{ __('issues.sp_list') }}</h2>
        @if (count($data['items']) === 0)
            <p class="mt-3 text-sm text-slate-500">{{ __('issues.sp_empty') }}</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach ($data['items'] as $i => $it)
                    <li class="flex items-center gap-3 py-2.5">
                        @if ($it['logo'] !== '')
                            <img src="{{ $it['logo'] }}" alt="" style="height:32px;width:auto;max-width:90px;object-fit:contain;" class="rounded">
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ $it['name'] }}</p>
                            @if ($it['url'] !== '')
                                <p class="truncate text-xs text-slate-500">{{ $it['url'] }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.sponsors.delete') }}" onsubmit="return confirm('{{ __('issues.sp_delete_confirm') }}')">
                            @csrf
                            <input type="hidden" name="index" value="{{ $i }}">
                            <button class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-100">{{ __('issues.sp_delete') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
