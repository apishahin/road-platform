@php
    /** @var \App\Models\AuthorityContact|null $contact */
    $contact ??= null;
    $editing = $contact !== null;
    $selectedAreaId = (int) old('admin_area_id', $contact?->admin_area_id);
    $areaSelection ??= [];
    $areaLabel ??= '';
@endphp

<form method="POST" action="{{ $editing ? route('admin.contacts.update', $contact) : route('admin.contacts.store') }}"
      class="mt-4 space-y-5" data-contact-form
      data-children-url="{{ url('/admin/areas') }}"
      data-selection='@json($areaSelection)'>
    @csrf
    @if ($editing) @method('PUT') @endif

    <fieldset class="rounded-xl border border-slate-200 bg-slate-50 p-3 sm:p-4">
        <legend class="px-1 text-sm font-bold text-slate-800">{{ __('authcontact.location_title') }}</legend>
        <p class="mb-3 text-xs leading-5 text-slate-500">{{ __('authcontact.location_hint') }}</p>
        <input type="hidden" name="admin_area_id" value="{{ $selectedAreaId }}" data-area-id>
        <p data-area-error class="mb-3 hidden rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ __('authcontact.no_area') }}</p>

        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-sm font-semibold text-slate-700">
                {{ __('authcontact.f_division') }}
                <select data-area-level="division" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
                    <option value="">{{ __('areas.all_divisions') }}</option>
                    @foreach ($divisions as $division)
                        <option value="{{ $division->id }}" @selected((string) ($areaSelection['division'] ?? '') === (string) $division->id)>
                            {{ app()->getLocale() === 'bn' ? ($division->name_bn ?: $division->name_en) : ($division->name_en ?: $division->name_bn) }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-semibold text-slate-700">
                {{ __('authcontact.f_district') }}
                <select data-area-level="district" disabled class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100">
                    <option value="">{{ __('areas.all_districts') }}</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-slate-700">
                {{ __('authcontact.f_upazila') }}
                <select data-area-level="upazila" disabled class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100">
                    <option value="">{{ __('areas.all_upazilas') }}</option>
                </select>
            </label>
            <label class="block text-sm font-semibold text-slate-700">
                {{ __('authcontact.f_union') }}
                <select data-area-level="union" disabled class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm disabled:cursor-not-allowed disabled:bg-slate-100">
                    <option value="">{{ __('areas.all_unions') }}</option>
                </select>
            </label>
        </div>
        <p data-area-chosen class="mt-3 text-xs font-semibold text-green-700">{{ $areaLabel }}</p>
    </fieldset>

    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block text-sm font-semibold text-slate-700">{{ __('authcontact.f_name') }}
            <input type="text" name="name" value="{{ old('name', $contact?->name) }}" maxlength="150" required
                   class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </label>
        <label class="block text-sm font-semibold text-slate-700">{{ __('authcontact.f_position') }}
            <input type="text" name="position" value="{{ old('position', $contact?->position) }}" maxlength="150"
                   class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </label>
        <label class="block text-sm font-semibold text-slate-700">{{ __('authcontact.f_phone') }}
            <input type="text" inputmode="tel" name="phone" value="{{ old('phone', $contact?->phone) }}" maxlength="30"
                   class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </label>
        <label class="block text-sm font-semibold text-slate-700">{{ __('authcontact.f_email') }}
            <input type="email" name="email" value="{{ old('email', $contact?->email) }}" maxlength="150"
                   class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </label>
        <label class="block text-sm font-semibold text-slate-700 sm:col-span-2">{{ __('authcontact.f_whatsapp') }}
            <input type="text" inputmode="tel" name="whatsapp" value="{{ old('whatsapp', $contact?->whatsapp) }}" maxlength="30"
                   class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
        </label>
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-brand-700">
            {{ $editing ? __('authcontact.update_btn') : __('authcontact.add_btn') }}
        </button>
        @if ($editing)
            <a href="{{ route('admin.contacts.index') }}" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">{{ __('authcontact.cancel') }}</a>
        @endif
    </div>
</form>

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-contact-form]');
    if (!form) return;

    const byLevel = (level) => form.querySelector('[data-area-level="' + level + '"]');
    const division = byLevel('division');
    const district = byLevel('district');
    const upazila = byLevel('upazila');
    const union = byLevel('union');
    const hidden = form.querySelector('[data-area-id]');
    const chosen = form.querySelector('[data-area-chosen]');
    const error = form.querySelector('[data-area-error]');
    const endpoint = form.dataset.childrenUrl;
    let seed = {};
    try { seed = JSON.parse(form.dataset.selection || '{}') || {}; } catch (e) { seed = {}; }

    const labels = {
        district: @json(__('areas.all_districts')),
        upazila: @json(__('areas.all_upazilas')),
        union: @json(__('areas.all_unions')),
    };
    const text = (select) => select.options[select.selectedIndex]?.text || '';
    const update = () => {
        const selected = union.value || upazila.value || district.value || division.value || '';
        hidden.value = selected;
        const names = [division, district, upazila, union].filter((select) => select.value).map(text);
        chosen.textContent = names.length ? names.join(' / ') : '';
        if (selected) error.classList.add('hidden');
    };
    const reset = (select, placeholder) => {
        select.innerHTML = '';
        select.append(new Option(placeholder, ''));
        select.disabled = true;
    };
    const load = async (parentId, select, level, value = '') => {
        reset(select, labels[level]);
        if (!parentId) return;
        try {
            const response = await fetch(endpoint + '/' + encodeURIComponent(parentId) + '/children', {
                headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
            });
            if (!response.ok) return;
            const rows = await response.json();
            rows.forEach((row) => select.append(new Option(row.label, String(row.id))));
            select.disabled = false;
            if (value && Array.from(select.options).some((option) => option.value === String(value))) {
                select.value = String(value);
            }
        } catch (e) { /* the parent selection remains usable if a request fails */ }
    };
    const clearAfterDivision = () => { reset(district, labels.district); reset(upazila, labels.upazila); reset(union, labels.union); };
    const clearAfterDistrict = () => { reset(upazila, labels.upazila); reset(union, labels.union); };
    const clearAfterUpazila = () => { reset(union, labels.union); };

    division.addEventListener('change', async () => {
        clearAfterDivision(); update();
        await load(division.value, district, 'district');
    });
    district.addEventListener('change', async () => {
        clearAfterDistrict(); update();
        await load(district.value, upazila, 'upazila');
    });
    upazila.addEventListener('change', async () => {
        clearAfterUpazila(); update();
        await load(upazila.value, union, 'union');
    });
    union.addEventListener('change', update);
    form.addEventListener('submit', (event) => {
        if (hidden.value) return;
        event.preventDefault();
        error.classList.remove('hidden');
        division.focus();
    });

    (async () => {
        if (!division.value) { update(); return; }
        update();
        await load(division.value, district, 'district', seed.district);
        if (!district.value) { update(); return; }
        update();
        await load(district.value, upazila, 'upazila', seed.upazila);
        if (!upazila.value) { update(); return; }
        update();
        await load(upazila.value, union, 'union', seed.union);
        update();
    })();
})();
</script>
@endpush
