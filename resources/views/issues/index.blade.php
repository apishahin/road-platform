@extends('layouts.app')

@section('title', __('issues.title'))

@section('content')
@php
    $toBn = fn ($n) => app()->getLocale() === 'bn'
        ? strtr((string) $n, '0123456789', '০১২৩৪৫৬৭৮৯')
        : (string) $n;
    $hasFilter = $filters['status'] !== '' || $filters['category'] > 0
        || $filters['area'] > 0 || $filters['q'] !== '' || $filters['sort'] !== 'recent';
    $field = 'mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm';
@endphp

<div class="mx-auto max-w-6xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('issues.title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.subtitle') }}</p>

    {{-- Filters --}}
    <form method="GET" action="{{ route('issues.index') }}"
          class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-3">

        <div class="sm:col-span-2 lg:col-span-3">
            <label for="q" class="text-sm font-medium text-slate-700">{{ __('issues.search') }}</label>
            <input id="q" type="text" name="q" value="{{ $filters['q'] }}" maxlength="100"
                   placeholder="{{ __('issues.search_placeholder') }}" class="{{ $field }}">
        </div>

        <div>
            <label for="status" class="text-sm font-medium text-slate-700">{{ __('issues.status') }}</label>
            <select id="status" name="status" class="{{ $field }}">
                <option value="">{{ __('issues.all_statuses') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected($filters['status'] === $s)>
                        {{ __('issues.statuses.' . $s) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="category" class="text-sm font-medium text-slate-700">{{ __('issues.category') }}</label>
            <select id="category" name="category" class="{{ $field }}">
                <option value="">{{ __('issues.all_categories') }}</option>
                @foreach ($categories as $c)
                    <option value="{{ $c['id'] }}" @selected($filters['category'] === $c['id'])>
                        {{ $c['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="area" class="text-sm font-medium text-slate-700">{{ __('issues.area') }}</label>
            <select id="area" name="area" class="{{ $field }}">
                <option value="">{{ __('issues.all_areas') }}</option>
                @foreach ($areas as $a)
                    <option value="{{ $a['id'] }}" @selected($filters['area'] === $a['id'])>
                        {{ $a['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="sort" class="text-sm font-medium text-slate-700">{{ __('issues.sort') }}</label>
            <select id="sort" name="sort" class="{{ $field }}">
                <option value="recent"  @selected($filters['sort'] === 'recent')>{{ __('issues.sort_recent') }}</option>
                <option value="reports" @selected($filters['sort'] === 'reports')>{{ __('issues.sort_reports') }}</option>
                <option value="oldest"  @selected($filters['sort'] === 'oldest')>{{ __('issues.sort_oldest') }}</option>
            </select>
        </div>

        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-2">
            <button type="submit"
                    class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white hover:opacity-90">
                {{ __('issues.apply') }}
            </button>
            @if ($hasFilter)
                <a href="{{ route('issues.index') }}"
                   class="rounded-lg border border-slate-300 px-5 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    {{ __('issues.reset') }}
                </a>
            @endif
        </div>
    </form>

    {{-- Results --}}
    <p class="mt-6 text-sm text-slate-600">
        {{ __('issues.results', ['count' => $toBn($issues->total())]) }}
    </p>

    @if ($issues->count() === 0)
        <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center">
            <p class="font-medium text-slate-800">{{ __('issues.empty') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('issues.empty_hint') }}</p>
        </div>
    @else
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($issues as $issue)
                <x-issue-card :issue="$issue" />
            @endforeach
        </div>

        {{-- Simple pagination --}}
        @if ($issues->hasPages())
            <nav class="mt-8 flex items-center justify-between" aria-label="Pagination">
                @if ($issues->onFirstPage())
                    <span class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-400">
                        ← {{ __('issues.prev') }}
                    </span>
                @else
                    <a href="{{ $issues->previousPageUrl() }}"
                       class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        ← {{ __('issues.prev') }}
                    </a>
                @endif

                <span class="text-sm text-slate-600">
                    {{ __('issues.page_of', ['page' => $toBn($issues->currentPage()), 'total' => $toBn($issues->lastPage())]) }}
                </span>

                @if ($issues->hasMorePages())
                    <a href="{{ $issues->nextPageUrl() }}"
                       class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        {{ __('issues.next') }} →
                    </a>
                @else
                    <span class="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-400">
                        {{ __('issues.next') }} →
                    </span>
                @endif
            </nav>
        @endif
    @endif
</div>
@endsection