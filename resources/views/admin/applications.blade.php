@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-2xl font-bold">{{ __('volapp.admin_title') }}</h1>
        <a href="{{ route('admin.moderators.index') }}" class="text-sm underline">{{ __('volapp.to_moderators') }}</a>
    </div>
    <p class="mb-4 text-xs text-gray-500">{{ __('volapp.admin_phone_note') }}</p>

    @if (session('status'))
        <div class="mb-4 rounded border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-900">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900">
            @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <h2 class="mb-2 font-semibold">{{ __('volapp.pending_list') }} ({{ $pending->count() }})</h2>
    @forelse ($pending as $a)
        <div class="mb-4 rounded border p-4">
            <div class="text-sm">
                <span class="font-semibold">{{ $a->user?->display_name ?: '-' }}</span>
                <span class="mx-1 text-gray-400">|</span>
                <span class="whitespace-nowrap">{{ $a->user?->phone }}</span>
            </div>
            <div class="mt-1 text-sm text-gray-700">{{ __('volapp.area') }}: {{ $paths[$a->id] ?? '' }}</div>
            <div class="mt-1 text-xs text-gray-500">{{ $a->created_at?->format('Y-m-d H:i') }}</div>
            <p class="mt-2 whitespace-pre-line rounded bg-gray-50 p-2 text-sm">{{ $a->message }}</p>

            <div class="mt-3 flex flex-wrap items-start gap-4">
                <form method="POST" action="{{ route('admin.applications.approve', $a) }}"
                      onsubmit="return confirm('{{ e(__('volapp.confirm_approve')) }}')">
                    @csrf
                    <button type="submit" class="rounded bg-brand-600 px-3 py-2 text-sm text-white">{{ __('volapp.approve') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.applications.reject', $a) }}" class="flex flex-1 flex-wrap gap-2">
                    @csrf
                    <input type="text" name="reason" maxlength="500" placeholder="{{ __('volapp.reason_ph') }}" class="min-w-[12rem] flex-1 rounded border px-3 py-2 text-sm">
                    <button type="submit" class="rounded border border-red-300 px-3 py-2 text-sm text-red-700">{{ __('volapp.reject') }}</button>
                </form>
            </div>
        </div>
    @empty
        <p class="mb-8 text-sm text-gray-600">{{ __('volapp.none_pending') }}</p>
    @endforelse

    @if ($decided->isNotEmpty())
        <h2 class="mb-2 mt-8 font-semibold text-gray-700">{{ __('volapp.decided_list') }}</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <tbody>
                    @foreach ($decided as $a)
                        <tr class="border-b align-top">
                            <td class="py-2 pr-3">{{ $a->user?->display_name ?: '-' }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">{{ $a->user?->phone }}</td>
                            <td class="py-2 pr-3">{{ $paths[$a->id] ?? '' }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">{{ __('volapp.status_' . $a->status) }}</td>
                            <td class="py-2 pr-3">{{ $a->decision_note }}</td>
                            <td class="py-2 whitespace-nowrap">{{ $a->decided_at?->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection