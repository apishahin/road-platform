@extends('layouts.app')

@section('title', __('report.title'))
{{-- The multi-step report form uses its own focused completion area. --}}
@section('hide_footer', '1')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
@endpush

@section('content')
@php
    // Shared by BOTH designs and by the wizard script below — must live in the
    // parent view: variables set inside an included partial do not leak up.
    $areaDivs = \App\Models\AdminArea::where('level', 'division')->orderBy('name_en')->get()
        ->map(fn ($a) => ['id' => $a->id, 'label' => app()->getLocale() === 'bn' ? ($a->name_bn ?: $a->name_en) : ($a->name_en ?: $a->name_bn)])
        ->values();
@endphp
@if (\App\Support\SiteSections::modern('report'))
    @include('reports.create-modern')
@else
    @include('reports.create-classic')
@endif

@include('partials.report-queue')

<script>
function reportWizard() {
    return {
        step: 1, lat: '', lng: '', selectedIssue: '',
        division: '', district: '', upazila: '', union: '',
        divisions: @json($areaDivs), districts: [], upazilas: [], unions: [],
        areaUrl: @json(url('/areas')), areaTouched: false, areaAuto: false, areaTimer: null, areaSeq: 0,
        nearby: [], loading: false, dupError: false, map: null, marker: null,
        outsideBangladesh: @json(__('report.outside_bangladesh')),

        init() {
        this.watchPoint();
            this.$nextTick(() => {
                const mapTools = window.RoadPlatformMap;
                this.map = L.map('report-map').setView(@json($center), @json($zoom));
                L.tileLayer(@json($tileUrl), { maxZoom: 19, attribution: @json($attribution) }).addTo(this.map);
                mapTools.applyMapDisplay(this.map, L);
                this.map.on('click', (e) => { void this.setPoint(e.latlng.lat, e.latlng.lng); });
            });
            this.$watch('step', (v) => {
                if (v === 1 && this.map) this.$nextTick(() => this.map.invalidateSize());
            });
        },

        async setPoint(lat, lng) {
            await window.RoadPlatformMap.bangladeshGeometryReady().catch(() => null);
            if (!window.RoadPlatformMap.isInBangladesh(lat, lng)) {
                alert(this.outsideBangladesh);
                return;
            }

            this.lat = lat.toFixed(7);
            this.lng = lng.toFixed(7);
            if (this.marker) {
                this.marker.setLatLng([lat, lng]);
                return;
            }
            this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
            this.marker.on('dragend', async (e) => {
                const p = e.target.getLatLng();
                await window.RoadPlatformMap.bangladeshGeometryReady().catch(() => null);
                if (!window.RoadPlatformMap.isInBangladesh(p.lat, p.lng)) {
                    e.target.setLatLng([Number(this.lat), Number(this.lng)]);
                    alert(this.outsideBangladesh);
                    return;
                }
                this.lat = p.lat.toFixed(7);
                this.lng = p.lng.toFixed(7);
            });
        },

        useMyLocation() {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition(async (pos) => {
                const { latitude: lat, longitude: lng } = pos.coords;
                await this.setPoint(lat, lng);
                if (window.RoadPlatformMap.isInBangladesh(lat, lng)) this.map.setView([lat, lng], 17);
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
@include('partials.photo-compress')
@endsection