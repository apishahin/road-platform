@extends('layouts.app')

@section('title', __('workcenter.title'))

@section('content')
@php
    $label = fn ($area) => app()->getLocale() === 'bn'
        ? ($area->name_bn ?: $area->name_en)
        : ($area->name_en ?: $area->name_bn);
    $tabs = [
        'all' => 'workcenter.tab_all',
        'moderation' => 'workcenter.tab_moderation',
        'verify' => 'workcenter.tab_verify',
        'photos' => 'workcenter.tab_photos',
    ];
    if ($canManageIssues) {
        $tabs['issues'] = 'workcenter.tab_issues';
    }
@endphp

<div class="mx-auto max-w-6xl px-4 py-6 sm:py-8" data-work-center data-number-locale="{{ app()->getLocale() === 'bn' ? 'bn-BD' : 'en-US' }}">
    <div class="rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-950 via-slate-900 to-brand-950 px-5 py-6 text-white shadow-lg sm:px-8 sm:py-8">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-200">{{ __('workcenter.eyebrow') }}</p>
        <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold sm:text-3xl">{{ __('workcenter.title') }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">{{ __('workcenter.subtitle') }}</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="shrink-0 text-sm font-semibold text-brand-100 underline hover:text-white">{{ __('workcenter.dashboard_link') }}</a>
        </div>
    </div>

    {{-- Keep this in normal document flow: a tall sticky filter was covering the first queue cards below the site header. --}}
    <form method="GET" action="{{ route('admin.work-center') }}" class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" data-work-filter
          data-children-url="{{ url('/admin/areas') }}">
        <input type="hidden" name="tab" value="{{ $tab }}" data-work-tab-input>
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <label class="block min-w-0 flex-1 text-sm font-bold text-slate-700">
                {{ __('workcenter.search_label') }}
                <input type="search" name="q" value="{{ request('q') }}" autocomplete="off" placeholder="{{ __('workcenter.search_placeholder') }}"
                       class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-100" data-work-search>
            </label>
            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700">{{ __('workcenter.search_button') }}</button>
            <a href="{{ route('admin.work-center', ['tab' => $tab]) }}" class="px-2 py-2.5 text-center text-sm font-bold text-slate-600 underline hover:text-slate-900">{{ __('workcenter.clear') }}</a>
        </div>

        <div class="mt-4 border-t border-slate-100 pt-4">
            <div class="mb-2 flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-slate-800">{{ __('workcenter.location_title') }}</p>
                <p class="text-xs text-slate-500">{{ __('workcenter.instant_hint') }}</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <label class="block text-xs font-bold text-slate-600">
                    {{ __('workcenter.division') }}
                    <select name="division" data-work-area="division" class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                        <option value="">{{ __('areas.all_divisions') }}</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}" @selected((int) ($selection['division'] ?? 0) === $division->id)>{{ $label($division) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs font-bold text-slate-600">
                    {{ __('workcenter.district') }}
                    <select name="district" data-work-area="district" disabled class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100">
                        <option value="">{{ __('areas.all_districts') }}</option>
                    </select>
                </label>
                <label class="block text-xs font-bold text-slate-600">
                    {{ __('workcenter.upazila') }}
                    <select name="upazila" data-work-area="upazila" disabled class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100">
                        <option value="">{{ __('areas.all_upazilas') }}</option>
                    </select>
                </label>
                <label class="block text-xs font-bold text-slate-600">
                    {{ __('workcenter.union') }}
                    <select name="union" data-work-area="union" disabled class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100">
                        <option value="">{{ __('areas.all_unions') }}</option>
                    </select>
                </label>
            </div>
        </div>

        @if ($canManageIssues)
            <label class="mt-4 max-w-xs text-xs font-bold text-slate-600" data-issue-state-wrap @if ($tab !== 'issues') hidden @endif>
                {{ __('workcenter.issue_state') }}
                <select name="issue_state" class="mt-1 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm" data-issue-state>
                    <option value="published" @selected($issueState === 'published')>{{ __('workcenter.published') }}</option>
                    <option value="unpublished" @selected($issueState === 'unpublished')>{{ __('workcenter.unpublished') }}</option>
                </select>
            </label>
        @endif
    </form>

    <nav class="mt-5 flex gap-2 overflow-x-auto pb-1" aria-label="{{ __('workcenter.title') }}" data-work-tabs>
        @foreach ($tabs as $key => $translation)
            <a href="{{ route('admin.work-center', array_filter(['tab' => $key, 'q' => request('q'), 'division' => $selection['division'] ?? null, 'district' => $selection['district'] ?? null, 'upazila' => $selection['upazila'] ?? null, 'union' => $selection['union'] ?? null, 'issue_state' => $key === 'issues' ? $issueState : null])) }}"
               data-work-tab="{{ $key }}"
               class="inline-flex shrink-0 items-center gap-2 rounded-xl border px-3.5 py-2.5 text-sm font-bold transition {{ $tab === $key ? 'border-slate-900 bg-slate-900 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50' }}">
                {{ __($translation) }}
                <span class="rounded-full px-2 py-0.5 text-xs {{ $tab === $key ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}" data-work-count="{{ $key }}">{{ \App\Support\IssuePresenter::num($counts[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    <section class="mt-5" data-work-results>
        @include('admin.work-center._results')
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.querySelector('[data-work-center]');
    if (!root) return;

    const form = root.querySelector('[data-work-filter]');
    const results = root.querySelector('[data-work-results]');
    const tabInput = form.querySelector('[data-work-tab-input]');
    const endpoint = form.dataset.childrenUrl;
    const area = (level) => form.querySelector('[data-work-area="' + level + '"]');
    const division = area('division');
    const district = area('district');
    const upazila = area('upazila');
    const union = area('union');
    const search = form.querySelector('[data-work-search]');
    const issueState = form.querySelector('[data-issue-state]');
    const issueStateWrap = form.querySelector('[data-issue-state-wrap]');
    const placeholders = {
        district: @json(__('areas.all_districts')),
        upazila: @json(__('areas.all_upazilas')),
        union: @json(__('areas.all_unions')),
    };
    const seed = @json($selection);
    let typing = null;
    let requestNo = 0;
    const numberFormat = new Intl.NumberFormat(root.dataset.numberLocale || 'en-US');

    const reset = (select, placeholder) => {
        select.innerHTML = '';
        select.append(new Option(placeholder, ''));
        select.disabled = true;
    };
    const resetAfter = (level) => {
        if (level === 'division') {
            reset(district, placeholders.district);
            reset(upazila, placeholders.upazila);
            reset(union, placeholders.union);
        } else if (level === 'district') {
            reset(upazila, placeholders.upazila);
            reset(union, placeholders.union);
        } else if (level === 'upazila') {
            reset(union, placeholders.union);
        }
    };
    const load = async (parentId, select, level, selected = '') => {
        reset(select, placeholders[level]);
        if (!parentId) return;
        try {
            const response = await fetch(endpoint + '/' + encodeURIComponent(parentId) + '/children', {
                headers: { Accept: 'application/json' }, credentials: 'same-origin'
            });
            if (!response.ok) return;
            const rows = await response.json();
            rows.forEach((row) => select.append(new Option(row.label, String(row.id))));
            select.disabled = rows.length === 0;
            if (selected && Array.from(select.options).some((option) => option.value === String(selected))) {
                select.value = String(selected);
            }
        } catch (error) { /* a selected parent remains usable if the connection drops */ }
    };
    const params = () => {
        const data = new FormData(form);
        for (const [key, value] of Array.from(data.entries())) {
            if (!String(value).trim()) data.delete(key);
        }
        return new URLSearchParams(data);
    };
    const setActiveTab = (tab) => {
        root.querySelectorAll('[data-work-tab]').forEach((link) => {
            const active = link.dataset.workTab === tab;
            link.classList.toggle('border-slate-900', active);
            link.classList.toggle('bg-slate-900', active);
            link.classList.toggle('text-white', active);
            link.classList.toggle('shadow-sm', active);
            link.classList.toggle('border-slate-200', !active);
            link.classList.toggle('bg-white', !active);
            link.classList.toggle('text-slate-700', !active);
        });
        if (issueStateWrap) issueStateWrap.hidden = tab !== 'issues';
    };
    const refresh = async () => {
        const serial = ++requestNo;
        const query = params();
        results.classList.add('opacity-50', 'pointer-events-none');
        try {
            const response = await fetch(form.action + '?' + query.toString(), {
                headers: { Accept: 'application/json', 'X-Work-Center': '1' }, credentials: 'same-origin'
            });
            if (!response.ok) throw new Error('request failed');
            const data = await response.json();
            if (serial !== requestNo) return;
            results.innerHTML = data.html;
            Object.entries(data.counts || {}).forEach(([name, count]) => {
                root.querySelectorAll('[data-work-count="' + name + '"]').forEach((node) => node.textContent = numberFormat.format(Number(count)));
            });
            history.replaceState({}, '', form.action + (query.toString() ? '?' + query.toString() : ''));
        } catch (error) {
            if (serial === requestNo) form.submit();
        } finally {
            if (serial === requestNo) results.classList.remove('opacity-50', 'pointer-events-none');
        }
    };

    form.addEventListener('submit', (event) => { event.preventDefault(); refresh(); });
    division.addEventListener('change', async () => { resetAfter('division'); await load(division.value, district, 'district'); refresh(); });
    district.addEventListener('change', async () => { resetAfter('district'); await load(district.value, upazila, 'upazila'); refresh(); });
    upazila.addEventListener('change', async () => { resetAfter('upazila'); await load(upazila.value, union, 'union'); refresh(); });
    union.addEventListener('change', refresh);
    if (issueState) issueState.addEventListener('change', refresh);
    search.addEventListener('input', () => {
        clearTimeout(typing);
        typing = setTimeout(refresh, 280);
    });
    root.querySelectorAll('[data-work-tab]').forEach((link) => link.addEventListener('click', (event) => {
        event.preventDefault();
        tabInput.value = link.dataset.workTab;
        setActiveTab(tabInput.value);
        refresh();
    }));

    (async () => {
        setActiveTab(tabInput.value);
        if (!division.value) return;
        await load(division.value, district, 'district', seed.district || '');
        if (!district.value) return;
        await load(district.value, upazila, 'upazila', seed.upazila || '');
        if (!upazila.value) return;
        await load(upazila.value, union, 'union', seed.union || '');
    })();
})();
</script>
@endpush
