@extends('layouts.app')

@section('title', __('issues.title'))

@section('content')
@php
    $toBn = fn ($n) => app()->getLocale() === 'bn'
        ? strtr((string) $n, ['0' => "\u{09E6}", '1' => "\u{09E7}", '2' => "\u{09E8}", '3' => "\u{09E9}", '4' => "\u{09EA}", '5' => "\u{09EB}", '6' => "\u{09EC}", '7' => "\u{09ED}", '8' => "\u{09EE}", '9' => "\u{09EF}"])
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
          x-data="issueFilter(@js($areas), {{ (int) $filters['area'] }}, @js(app()->getLocale()))"
          class="mt-6 grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-3">

        <div class="sm:col-span-2 lg:col-span-3">
            <label for="q" class="text-sm font-medium text-slate-700">{{ __('issues.search') }}</label>
            <input id="q" type="text" name="q" value="{{ $filters['q'] }}" maxlength="100"
                   placeholder="{{ __('issues.search_placeholder') }}" class="{{ $field }}"
                   @change="$el.form.submit()">
        </div>

        <div>
            <label for="status" class="text-sm font-medium text-slate-700">{{ __('issues.status') }}</label>
            <select id="status" name="status" class="{{ $field }}" @change="$el.form.submit()">
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
            <select id="category" name="category" class="{{ $field }}" @change="$el.form.submit()">
                <option value="">{{ __('issues.all_categories') }}</option>
                @foreach ($categories as $c)
                    <option value="{{ $c['id'] }}" @selected($filters['category'] === $c['id'])>
                        {{ $c['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="sort" class="text-sm font-medium text-slate-700">{{ __('issues.sort') }}</label>
            <select id="sort" name="sort" class="{{ $field }}" @change="$el.form.submit()">
                <option value="recent"  @selected($filters['sort'] === 'recent')>{{ __('issues.sort_recent') }}</option>
                <option value="reports" @selected($filters['sort'] === 'reports')>{{ __('issues.sort_reports') }}</option>
                <option value="oldest"  @selected($filters['sort'] === 'oldest')>{{ __('issues.sort_oldest') }}</option>
            </select>
        </div>

        <div class="sm:col-span-2 lg:col-span-3">
            <span class="text-sm font-medium text-slate-700">{{ __('issues.area') }}</span>
            <input type="hidden" name="area" :value="areaValue" :disabled="!areaValue">
            <div class="mt-1 grid gap-3 sm:grid-cols-3">
                <select x-model="division" @change="district = ''; upazila = ''; send()"
                        aria-label="{{ __('areas.division') }}" class="{{ $field }} !mt-0">
                    <option value="">{{ __('areas.all_divisions') }}</option>
                    <template x-for="a in roots()" :key="a.id">
                        <option :value="String(a.id)" x-text="a.label"></option>
                    </template>
                </select>

                <select x-model="district" @change="upazila = ''; send()" :disabled="!division"
                        aria-label="{{ __('areas.district') }}" class="{{ $field }} !mt-0 disabled:bg-slate-100">
                    <option value="">{{ __('areas.all_districts') }}</option>
                    <template x-for="a in children(division)" :key="a.id">
                        <option :value="String(a.id)" x-text="a.label"></option>
                    </template>
                </select>

                <select x-show="children(district).length > 0" x-model="upazila" @change="send()"
                        aria-label="{{ __('areas.upazila') }}" class="{{ $field }} !mt-0">
                    <option value="">{{ __('areas.all_upazilas') }}</option>
                    <template x-for="a in children(district)" :key="a.id">
                        <option :value="String(a.id)" x-text="a.label"></option>
                    </template>
                </select>
            </div>
        </div>

        <button type="submit" class="sr-only">{{ __('issues.apply') }}</button>

        @if ($hasFilter)
            <div class="sm:col-span-2 lg:col-span-3">
                <a href="{{ route('issues.index') }}"
                   class="inline-block rounded-lg border border-slate-300 px-5 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    {{ __('issues.reset') }}
                </a>
            </div>
        @endif
    </form>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('issueFilter', (areas, selected, locale) => ({
                areas: areas,
                division: '',
                district: '',
                upazila: '',
                init() {
                    const byId = {};
                    areas.forEach(a => { byId[a.id] = a; });
                    const chain = [];
                    let cur = byId[selected];
                    while (cur) {
                        chain.unshift(cur);
                        cur = cur.parent_id ? byId[cur.parent_id] : null;
                    }
                    this.division = chain[0] ? String(chain[0].id) : '';
                    this.district = chain[1] ? String(chain[1].id) : '';
                    this.upazila = chain[2] ? String(chain[2].id) : '';
                },
                sorted(list) {
                    return list.slice().sort((a, b) => a.label.localeCompare(b.label, locale));
                },
                roots() {
                    return this.sorted(this.areas.filter(a => !a.parent_id));
                },
                children(parentId) {
                    if (!parentId) return [];
                    return this.sorted(this.areas.filter(a => String(a.parent_id) === String(parentId)));
                },
                get areaValue() {
                    return this.upazila || this.district || this.division || '';
                },
                send() {
                    this.$nextTick(() => this.$root.submit());
                },
            }));
        });
    </script>
    @endpush

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