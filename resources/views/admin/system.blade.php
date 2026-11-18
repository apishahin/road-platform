@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8" data-system-panel>
    <h1 class="text-2xl font-bold">{{ __('issues.sys_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.sys_sub') }}</p>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    {{-- ============================ Health ============================ --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5" data-health>
        @php $bad = collect($checks)->where('ok', false)->count(); @endphp
        <div class="flex items-center justify-between">
            <div class="font-semibold">{{ __('issues.sys_health') }}</div>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $bad ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">
                {{ $bad ? __('issues.sys_health_warn', ['n' => $bad]) : __('issues.sys_health_ok') }}
            </span>
        </div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @foreach ($checks as $c)
                <div class="flex items-start gap-2 rounded-lg border px-3 py-2 text-sm {{ $c['ok'] ? 'border-slate-100' : 'border-amber-200 bg-amber-50' }}">
                    <span class="{{ $c['ok'] ? 'text-green-600' : 'text-amber-600' }}">{{ $c['ok'] ? '✓' : '⚠' }}</span>
                    <div class="min-w-0">
                        <div class="font-medium">{{ $c['label'] }}</div>
                        <div class="truncate text-xs text-slate-500">{{ $c['hint'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ============================ Info ============================== --}}
    <div class="mt-6 grid gap-4 md:grid-cols-2" data-info>
        @foreach ($info as $title => $rows)
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-sm font-semibold">{{ $title }}</div>
                @foreach ($rows as $k => $v)
                    <div class="flex items-center justify-between gap-3 px-4 py-2 text-sm {{ ! $loop->last ? 'border-b border-slate-50' : '' }}">
                        <span class="text-slate-600">{{ $k }}</span>
                        <span class="truncate font-medium">{{ $v }}</span>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- ============================ Cache ============================= --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5" data-cache>
        <div class="font-semibold">{{ __('issues.sys_cache') }}</div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (['views', 'routes', 'config', 'app', 'all', 'log'] as $t)
                <form method="POST" action="{{ route('admin.system.cache') }}" class="flex items-center justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2">
                    @csrf
                    <input type="hidden" name="type" value="{{ $t }}">
                    <span class="text-sm">{{ __('issues.sys_cache_'.$t) }}</span>
                    <button class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium hover:bg-slate-100">{{ __('issues.sys_clear') }}</button>
                </form>
            @endforeach
        </div>
    </div>

    {{-- ========================= Maintenance ========================== --}}
    <div class="mt-6 rounded-xl border p-5 {{ $maint['on'] ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white' }}" data-maintenance>
        <div class="flex items-center justify-between">
            <div class="font-semibold">{{ __('issues.sys_maintenance') }}</div>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $maint['on'] ? 'bg-amber-200 text-amber-900' : 'bg-green-100 text-green-800' }}">
                {{ $maint['on'] ? __('issues.sys_maintenance_badge_on') : __('issues.sys_maintenance_badge_off') }}
            </span>
        </div>
        <p class="mt-1 text-sm text-slate-600">{{ __('issues.sys_maintenance_desc') }}</p>
        <form method="POST" action="{{ route('admin.system.maintenance') }}" class="mt-3 space-y-2">
            @csrf
            <label class="flex items-center gap-2 text-sm font-medium">
                <input type="checkbox" name="on" value="1" @checked($maint['on']) class="size-4">
                {{ __('issues.sys_maintenance_on_label') }}
            </label>
            <input name="message" value="{{ $maint['message'] }}" placeholder="{{ __('issues.sys_maintenance_msg') }}"
                   class="w-full rounded-lg border-slate-300 text-sm">
            <button class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('issues.sys_save') }}</button>
        </form>
    </div>

    {{-- ========================== Updates ============================= --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5" data-update>
        <div class="font-semibold">{{ __('issues.sys_update') }}</div>
        <div class="mt-1 text-sm text-slate-600">{{ __('issues.sys_version') }}: <span class="font-semibold">{{ config('app.version') }}</span></div>
        <div class="mt-3 text-sm font-medium">{{ __('issues.sys_update_steps_title') }}</div>
        <ol class="mt-1 list-decimal space-y-0.5 ps-5 text-sm text-slate-600">
            @for ($i = 1; $i <= 6; $i++)<li>{{ __('issues.sys_update_s'.$i) }}</li>@endfor
        </ol>
        <form method="POST" action="{{ route('admin.system.migrate') }}" class="mt-3">
            @csrf
            <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-100">{{ __('issues.sys_migrate') }}</button>
        </form>
        @if (session('migrate_out'))
            <pre class="mt-2 max-h-40 overflow-auto rounded-lg bg-slate-50 p-2 text-xs">{{ session('migrate_out') }}</pre>
        @endif
    </div>

    {{-- ========================== Backups ============================= --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5" data-backup>
        <div class="font-semibold">{{ __('issues.sys_backup') }}</div>
        <p class="mt-1 text-sm text-slate-600">{{ __('issues.sys_backup_weekly_hint') }}</p>

        <form method="POST" action="{{ route('admin.system.backups.create') }}" class="mt-3 flex flex-wrap items-center gap-3">
            @csrf
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="uploads" value="1" class="size-4">
                {{ __('issues.sys_backup_uploads') }}
            </label>
            <button class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('issues.sys_backup_create') }}</button>
        </form>

        <div class="mt-4 divide-y divide-slate-100 rounded-lg border border-slate-100">
            @forelse ($backups as $b)
                <div class="flex flex-wrap items-center gap-2 px-3 py-2 text-sm">
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ $b['name'] }}</div>
                        <div class="text-xs text-slate-500">{{ $b['size'] }} · {{ $b['date'] }}</div>
                    </div>
                    <a href="{{ route('admin.system.backups.download', $b['name']) }}"
                       class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-medium hover:bg-slate-100">{{ __('issues.sys_backup_download') }}</a>
                    <form method="POST" action="{{ route('admin.system.backups.delete', $b['name']) }}" onsubmit="return confirm('{{ __('issues.sys_backup_delete') }}?')">
                        @csrf
                        <button class="rounded-lg border border-red-200 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-50">{{ __('issues.sys_backup_delete') }}</button>
                    </form>
                </div>
            @empty
                <div class="px-3 py-2 text-sm text-slate-500">{{ __('issues.sys_backup_none') }}</div>
            @endforelse
        </div>

        <div class="mt-4 rounded-lg border border-dashed border-slate-300 p-3">
            <div class="text-sm font-semibold">{{ __('issues.sys_backup_restore_title') }}</div>
            <p class="mt-1 text-xs leading-5 text-amber-700">{{ __('issues.sys_backup_restore_hint') }}</p>
            <form method="POST" action="{{ route('admin.system.backups.restore') }}" enctype="multipart/form-data" class="mt-2 space-y-2">
                @csrf
                <input type="file" name="backup" accept=".zip" required class="block w-full text-sm">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="confirm" value="1" class="size-4">
                    {{ __('issues.sys_backup_confirm') }}
                </label>
                <button class="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-100">{{ __('issues.sys_backup_restore_btn') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
