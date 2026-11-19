@extends('layouts.app')

@section('title', __('issues.fl_admin_title'))

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8" data-footer-admin>
    <h1 class="text-2xl font-bold">{{ __('issues.fl_admin_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.fl_admin_sub') }}</p>

    {{-- Built-in links --}}
    <form method="POST" action="{{ route('admin.footer.save') }}" class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
        @csrf
        <h2 class="font-semibold">{{ __('issues.fl_builtin') }}</h2>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @php
                $labels = [
                    'home'        => __('Home'),
                    'issues'      => __('Issues'),
                    'dashboard'   => __('issues.nav_dashboard'),
                    'report'      => __('Report a problem'),
                    'fund'        => __('issues.site_nav_fund'),
                    'impact'      => __('issues.impact_nav'),
                    'leaderboard' => __('issues.lb_nav'),
                    'pages'       => __('issues.fl_pages'),
                ];
            @endphp
            @foreach ($labels as $key => $label)
                <label class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                    <input type="checkbox" name="links[{{ $key }}]" value="1" @checked($builtin[$key]) class="size-4">
                    {{ $label }}
                </label>
            @endforeach
        </div>
        <button class="mt-3 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('issues.sp_save_section') }}</button>
    </form>

    {{-- Custom links --}}
    <form method="POST" action="{{ route('admin.footer.add') }}" class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
        @csrf
        <h2 class="font-semibold">{{ __('issues.fl_add') }}</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div>
                <label class="text-xs font-medium text-slate-600">{{ __('issues.fl_label') }} *</label>
                <input type="text" name="label" value="{{ old('label') }}" maxlength="40" required
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs font-medium text-slate-600">{{ __('issues.fl_url') }} *</label>
                <input type="text" name="url" value="{{ old('url') }}" maxlength="300" required placeholder="/privacy অথবা https://…"
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>
        @if ($errors->any())
            <p class="mt-2 text-sm text-rose-600">{{ $errors->first() }}</p>
        @endif
        <button class="mt-3 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('issues.sp_add_btn') }}</button>
    </form>

    <div class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">{{ __('issues.fl_custom_list') }}</h2>
        @if (count($custom) === 0)
            <p class="mt-3 text-sm text-slate-500">{{ __('issues.fl_empty') }}</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach ($custom as $i => $it)
                    <li class="flex items-center gap-3 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ $it['label'] }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $it['url'] }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.footer.delete') }}" onsubmit="return confirm('{{ __('issues.sp_delete_confirm') }}')">
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
