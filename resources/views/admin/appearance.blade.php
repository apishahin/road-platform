@extends('layouts.app')

@section('title', 'Appearance')

@section('content')
{{-- The appearance editor has been merged into Homepage & site; keep this page as a friendly redirect. --}}
<script>window.location.replace("{{ route('admin.site.index') }}#appearance");</script>
<div class="mx-auto max-w-3xl px-4 py-12 text-center">
    <p class="text-slate-700">{{ __('issues.appearance_moved') }}
        <a href="{{ route('admin.site.index') }}#appearance" style="color:#047857;font-weight:700;">{{ __('issues.appearance_moved_link') }}</a>
    </p>
</div>

<x-card title="{{ __('pwa.admin_icon_title') }}" class="mt-6">
    <p class="text-sm text-slate-500">{{ __('pwa.admin_icon_body') }}</p>

    <form method="post" action="{{ route('admin.appearance.pwa.icon') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-center gap-3">
        @csrf
        <input type="file" name="icon" accept="image/png,image/jpeg,image/webp" class="text-sm">
        <x-button>{{ __('pwa.admin_icon_save') }}</x-button>
    </form>

    <div class="mt-4 flex items-center gap-3 border-t border-slate-100 pt-4">
        <img src="{{ \App\Support\PwaSettings::icon() ? '/uploads/pwa/icon-192-' . \App\Support\PwaSettings::icon()['file'] . '.png' : '/icons/icon-192.png' }}" class="size-12 rounded-xl border border-slate-200 shadow-sm" alt="">
        <p class="flex-1 text-xs text-slate-500">{{ __('pwa.admin_icon_current') }}</p>
        @if(\App\Support\PwaSettings::icon())
            <form method="post" action="{{ route('admin.appearance.pwa.icon.remove') }}">
                @csrf
                <button class="text-xs font-semibold text-rose-600 hover:underline">{{ __('pwa.admin_icon_remove') }}</button>
            </form>
        @endif
    </div>
</x-card>

@endsection
