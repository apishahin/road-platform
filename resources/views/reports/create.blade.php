@extends('layouts.app')

@section('title', __('report.title'))

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
@endpush

@section('content')
@php $inp = 'mt-1 block w-full rounded-lg border border-slate-300 p-2.5 text-sm'; @endphp
<div class="mx-auto max-w-3xl px-4 py-8" x-data="reportWizard()">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('report.title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('report.subtitle') }}</p>

    <div class="mt-6 flex items-center justify-between border-b border-slate-200 pb-4">
        @foreach ([1, 2, 3, 4] as $n)
            <div class="flex items-center gap-2">
                <span class="flex size-7 items-center justify-center rounded-full text-xs font-bold"
                      :class="step >= {{ $n }} ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-600'">{{ $n }}</span>
                <span class="hidden text-xs font-medium text-slate-700 sm:inline">{{ __('report.step' . $n) }}</span>
            </div>
        @endforeach
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form x-ref="form" method="POST" action="{{ route('report.store') }}" enctype="multipart/form-data" class="mt-6"
          @keydown.enter="if ($event.target.tagName !== 'TEXTAREA') $event.preventDefault()">
        @csrf
        <input type="hidden" name="lat" :value="lat">
        <input type="hidden" name="lng" :value="lng">
        <input type="hidden" name="issue_id" :value="selectedIssue">

        {{-- Step 1: location --}}
        <div x-show="step === 1" class="space-y-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('report.step1_heading') }}</h2>
            <p class="text-sm text-slate-600">{{ __('report.step1_hint') }}</p>
            <button type="button" @click="useMyLocation()"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                📍 {{ __('report.use_my_location') }}
            </button>
            <div id="report-map" class="h-80 w-full rounded-xl border border-slate-300"></div>
            <div class="flex justify-end">
                <button type="button" @click="toStep2()" :disabled="!lat"
                        class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
                    {{ __('report.next') }} →
                </button>
            </div>
        </div>

        {{-- Step 2: duplicate check --}}
        <div x-show="step === 2" x-cloak class="space-y-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('report.step2_heading') }}</h2>

            <p x-show="loading" class="text-sm text-slate-500">{{ __('report.checking_duplicates') }}</p>
            <p x-show="dupError" class="text-sm text-red-700">{{ __('report.dup_error') }}</p>
            <p x-show="!loading && !dupError && nearby.length === 0"
               class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-700">{{ __('report.no_duplicates') }}</p>

            <div x-show="!loading && nearby.length > 0" class="space-y-2">
                <p class="text-sm text-slate-600">{{ __('report.duplicates_found') }}</p>
                <template x-for="item in nearby" :key="item.id">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4"
                           :class="selectedIssue === item.id ? 'border-brand-600 bg-brand-50' : 'border-slate-200 bg-white'">
                        <input type="radio" :value="item.id" x-model="selectedIssue" class="mt-1">
                        <div class="text-sm">
                            <p class="font-semibold text-slate-900" x-text="item.title"></p>
                            <p class="text-xs text-slate-500"
                               x-text="item.category + ' · ' + item.status + ' · ' + Math.round(item.meters) + ' {{ __('report.meters_away') }}'"></p>
                            <a :href="item.url" target="_blank" rel="noopener" class="text-xs text-brand-700 underline">{{ __('report.view') }}</a>
                        </div>
                    </label>
                </template>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4"
                       :class="selectedIssue === '' ? 'border-brand-600 bg-brand-50' : 'border-slate-200 bg-white'">
                    <input type="radio" value="" x-model="selectedIssue" class="mt-1">
                    <div class="text-sm">
                        <p class="font-semibold text-slate-900">{{ __('report.new_issue') }}</p>
                        <p class="text-xs text-slate-500">{{ __('report.new_issue_hint') }}</p>
                    </div>
                </label>
            </div>

            <div class="flex justify-between">
                <button type="button" @click="step = 1" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">← {{ __('report.back') }}</button>
                <button type="button" @click="step = 3" :disabled="loading"
                        class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">{{ __('report.next') }} →</button>
            </div>
        </div>

        {{-- Step 3: details --}}
        <div x-show="step === 3" x-cloak class="space-y-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('report.step3_heading') }}</h2>
            <p x-show="selectedIssue" class="rounded-lg bg-slate-100 p-3 text-xs text-slate-700">{{ __('report.existing_note') }}</p>

            <div x-show="!selectedIssue" class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-slate-700">{{ __('report.category') }} *</label>
                    <select name="category_id" data-step3 :required="!selectedIssue" class="{{ $inp }}">
                        <option value="">{{ __('report.select_category') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat['id'] }}" @selected(old('category_id') == $cat['id'])>{{ $cat['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700">{{ __('report.issue_title') }} *</label>
                    <input type="text" name="title" data-step3 :required="!selectedIssue" maxlength="150"
                           value="{{ old('title') }}" placeholder="{{ __('report.title_placeholder') }}" class="{{ $inp }}">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700">{{ __('report.road_name') }}</label>
                        <input type="text" name="road_name" maxlength="150" value="{{ old('road_name') }}"
                               placeholder="{{ __('report.road_placeholder') }}" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700">{{ __('report.landmark') }}</label>
                        <input type="text" name="landmark" maxlength="150" value="{{ old('landmark') }}"
                               placeholder="{{ __('report.landmark_placeholder') }}" class="{{ $inp }}">
                    </div>
                </div>
            </div>

            @php
    $areaDivs = \App\Models\AdminArea::where('level', 'division')->orderBy('name_en')->get()
        ->map(fn ($a) => ['id' => $a->id, 'label' => app()->getLocale() === 'bn' ? ($a->name_bn ?: $a->name_en) : ($a->name_en ?: $a->name_bn)])
        ->values();
@endphp
<div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
    <p class="text-sm font-medium text-slate-700">{{ __('suggest.title') }}</p>
    <p class="text-xs text-slate-500">{{ __('suggest.hint') }}</p>
    <p x-show="areaAuto" class="mt-1 text-xs font-medium text-amber-700">{{ __('suggest.auto') }}</p>
    <input type="hidden" name="suggested_area_id" :value="areaValue" :disabled="!areaValue">
    <div class="mt-2 grid gap-2 sm:grid-cols-2">
        <select x-model="division" @change="pickDivision()" aria-label="{{ __('areas.division') }}" class="{{ $inp }}">
            <option value="">{{ __('areas.all_divisions') }}</option>
            <template x-for="a in divisions" :key="a.id"><option :value="String(a.id)" x-text="a.label"></option></template>
        </select>
        <select x-show="districts.length > 0" x-model="district" @change="pickDistrict()" aria-label="{{ __('areas.district') }}" class="{{ $inp }}">
            <option value="">{{ __('areas.all_districts') }}</option>
            <template x-for="a in districts" :key="a.id"><option :value="String(a.id)" x-text="a.label"></option></template>
        </select>
        <select x-show="upazilas.length > 0" x-model="upazila" @change="pickUpazila()" aria-label="{{ __('areas.upazila') }}" class="{{ $inp }}">
            <option value="">{{ __('areas.all_upazilas') }}</option>
            <template x-for="a in upazilas" :key="a.id"><option :value="String(a.id)" x-text="a.label"></option></template>
        </select>
        <select x-show="unions.length > 0" x-model="union" @change="pickUnion()" aria-label="{{ __('areas.union') }}" class="{{ $inp }}">
            <option value="">{{ __('areas.all_unions') }}</option>
            <template x-for="a in unions" :key="a.id"><option :value="String(a.id)" x-text="a.label"></option></template>
        </select>
    </div>
</div>
<div>
                <label class="text-sm font-medium text-slate-700">{{ __('report.description') }} *</label>
                <textarea name="description" data-step3 required rows="4" maxlength="1000"
                          placeholder="{{ __('report.desc_placeholder') }}" class="{{ $inp }}">{{ old('description') }}</textarea>
            </div>

            <div class="flex justify-between">
                <button type="button" @click="step = 2" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">← {{ __('report.back') }}</button>
                <button type="button" @click="toStep4()" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white">{{ __('report.next') }} →</button>
            </div>
        </div>

        {{-- Step 4: photos --}}
        <div x-show="step === 4" x-cloak class="space-y-4">
            <h2 class="text-lg font-semibold text-slate-900">{{ __('report.step4_heading') }}</h2>
            <p class="text-sm text-slate-600">{{ __('report.photos_hint') }}</p>
            <p class="mt-2 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('privacy.photo_warn') }}</p>
            <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-600">
            <div class="rounded-lg bg-amber-50 p-4 text-xs text-amber-800">⚠️ {{ __('report.claim_notice') }}</div>
            <div class="flex justify-between">
                <button type="button" @click="step = 3" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">← {{ __('report.back') }}</button>
                <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-brand-700">{{ __('report.submit') }}</button>
            </div>
        </div>
    </form>
</div>

<script>
function reportWizard() {
    return {
        step: 1, lat: '', lng: '', selectedIssue: '',
        division: '', district: '', upazila: '', union: '',
        divisions: @json($areaDivs), districts: [], upazilas: [], unions: [],
        areaUrl: @json(url('/areas')), areaTouched: false, areaAuto: false, areaTimer: null, areaSeq: 0,
        nearby: [], loading: false, dupError: false, map: null, marker: null,

        init() {
        this.watchPoint();
            this.$nextTick(() => {
                this.map = L.map('report-map').setView(@json($center), @json($zoom));
                L.tileLayer(@json($tileUrl), { maxZoom: 19, attribution: @json($attribution) }).addTo(this.map);
                this.map.on('click', (e) => this.setPoint(e.latlng.lat, e.latlng.lng));
            });
            this.$watch('step', (v) => {
                if (v === 1 && this.map) this.$nextTick(() => this.map.invalidateSize());
            });
        },

        setPoint(lat, lng) {
            this.lat = lat.toFixed(7);
            this.lng = lng.toFixed(7);
            if (this.marker) {
                this.marker.setLatLng([lat, lng]);
                return;
            }
            this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
            this.marker.on('dragend', (e) => {
                const p = e.target.getLatLng();
                this.lat = p.lat.toFixed(7);
                this.lng = p.lng.toFixed(7);
            });
        },

        useMyLocation() {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition((pos) => {
                this.map.setView([pos.coords.latitude, pos.coords.longitude], 17);
                this.setPoint(pos.coords.latitude, pos.coords.longitude);
            });
        },

        get areaValue() { return this.union || this.upazila || this.district || this.division || ''; },

    async kids(id) {
        if (!id) { return []; }
        try {
            const r = await fetch(this.areaUrl + '/' + id + '/children', { headers: { 'Accept': 'application/json' } });
            return r.ok ? await r.json() : [];
        } catch (e) { return []; }
    },
    async pickDivision() {
        this.areaTouched = true; this.areaAuto = false;
        this.district = ''; this.upazila = ''; this.union = '';
        this.districts = []; this.upazilas = []; this.unions = [];
        this.districts = await this.kids(this.division);
    },
    async pickDistrict() {
        this.areaTouched = true; this.areaAuto = false;
        this.upazila = ''; this.union = '';
        this.upazilas = []; this.unions = [];
        this.upazilas = await this.kids(this.district);
    },
    async pickUpazila() {
        this.areaTouched = true; this.areaAuto = false;
        this.union = '';
        this.unions = [];
        this.unions = await this.kids(this.upazila);
    },
    pickUnion() { this.areaTouched = true; this.areaAuto = false; },

    watchPoint() {
        const go = () => {
            clearTimeout(this.areaTimer);
            this.areaTimer = setTimeout(() => this.locateArea(), 500);
        };
        this.$watch('lat', go);
        this.$watch('lng', go);
    },
    async locateArea() {
        if (this.areaTouched || !this.lat || !this.lng) { return; }
        const seq = ++this.areaSeq;
        let d = null;
        try {
            const r = await fetch(this.areaUrl + '/locate?lat=' + this.lat + '&lng=' + this.lng, { headers: { 'Accept': 'application/json' } });
            if (!r.ok) { return; }
            d = await r.json();
        } catch (e) { return; }
        if (!d || !d.division || seq !== this.areaSeq || this.areaTouched) { return; }
        const ds = await this.kids(d.division.id);
        const up = d.district ? await this.kids(d.district.id) : [];
        const un = d.upazila ? await this.kids(d.upazila.id) : [];
        if (seq !== this.areaSeq || this.areaTouched) { return; }
        this.districts = ds; this.upazilas = up; this.unions = un;
        this.division = String(d.division.id);
        await this.$nextTick();
        this.district = d.district ? String(d.district.id) : '';
        this.upazila = d.upazila ? String(d.upazila.id) : '';
        this.union = d.union ? String(d.union.id) : '';
        this.areaAuto = true;
    },

    toStep2() {
            if (!this.lat) { alert(@json(__('report.pick_point'))); return; }
            this.step = 2;
            this.selectedIssue = '';
            this.loading = true;
            this.dupError = false;
            fetch('/api/v1/issues/nearby?lat=' + this.lat + '&lng=' + this.lng + '&radius=200', { headers: { 'Accept': 'application/json' } })
                .then((r) => { if (!r.ok) throw new Error('bad'); return r.json(); })
                .then((d) => { this.nearby = d.data || []; })
                .catch(() => { this.nearby = []; this.dupError = true; })
                .finally(() => { this.loading = false; });
        },

        toStep4() {
            const fields = this.$refs.form.querySelectorAll('[data-step3]');
            for (const el of fields) {
                if (el.offsetParent !== null && !el.reportValidity()) return;
            }
            this.step = 4;
        },
    };
}
</script>
@endsection