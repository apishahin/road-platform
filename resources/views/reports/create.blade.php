@extends('layouts.app')

@section('title', __('report.title'))
@section('hide_footer', '1')

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
@endpush

@section('content')
@php
    $areaDivs = \App\Models\AdminArea::where('level', 'division')->orderBy('name_en')->get()
        ->map(fn ($a) => ['id' => $a->id, 'label' => app()->getLocale() === 'bn' ? ($a->name_bn ?: $a->name_en) : ($a->name_en ?: $a->name_bn)])
        ->values();
    $initialReportStep = max(1, min(3, (int) old('report_step', 1)));
@endphp
@include('reports.create-modern')
@include('partials.report-queue')

<script>
function reportWizard() {
    return {
        step: @json($initialReportStep),
        // Keep every essential value after a server-side validation redirect.
        // In particular, a missing final-step title must not discard the pin.
        lat: @json((string) old('lat', '')), lng: @json((string) old('lng', '')), locationPrecision: @json(old('location_precision', 'exact')), placeName: @json(old('place_name', '')), locationNote: @json(old('location_note', '')),
        roadName: @json(old('road_name', '')), landmark: @json(old('landmark', '')),
        mainCategory: '', categoryId: @json((string) old('category_id', '')), title: @json(old('title', '')), description: @json(old('description', '')), titleError: @json($errors->has('title')),
        selectedIssue: @json((string) old('issue_id', '')), nearby: [], loading: false, dupError: false,
        taxonomy: @json($taxonomy),
        photoNames: [], photoPreviews: [], aiConsent: false, aiBusy: false, aiDraft: null, aiError: '', aiApplied: false,
        aiEnabled: @json($aiEnabled), aiUrl: @json(route('report.ai-suggestion')), csrf: @json(csrf_token()),
        division: '', district: '', upazila: '', union: '', areaEditing: false,
        divisions: @json($areaDivs), districts: [], upazilas: [], unions: [],
        areaUrl: @json(url('/areas')), areaTouched: false, areaAuto: false, areaTimer: null, areaSeq: 0,
        map: null, marker: null,
        mainCategoryOpen: false, subcategoryOpen: false,
        outsideBangladesh: @json(__('report.outside_bangladesh')), locale: @json($locale),
        voiceSupported: false, voiceListening: false, recognition: null,

        init() {
            this.restoreCategory();
            this.watchPoint();
            this.voiceSupported = !!(window.SpeechRecognition || window.webkitSpeechRecognition);
            this.$watch('step', (v) => { if (v === 1) this.$nextTick(() => this.ensureMap()); });
            this.$nextTick(() => this.ensureMap());
        },

        main() { return this.taxonomy.find(x => String(x.id) === String(this.mainCategory)); },
        children() { return this.main() ? this.main().children : []; },
        mainCategoryLabel() { return this.main() ? this.main().label : ''; },
        categoryLabel() { const item = this.children().find(x => String(x.id) === String(this.categoryId)); return item ? item.label : ''; },
        restoreCategory() {
            if (!this.categoryId) return;
            for (const group of this.taxonomy) {
                if (group.children.some(child => String(child.id) === String(this.categoryId))) { this.mainCategory = String(group.id); break; }
            }
        },
        chooseMain(id) {
            this.mainCategory = String(id); this.categoryId = ''; this.selectedIssue = ''; this.nearby = []; this.aiDraft = null;
            this.mainCategoryOpen = false; this.subcategoryOpen = false;
        },
        chooseCategory(id) {
            this.categoryId = String(id); this.selectedIssue = ''; this.nearby = []; this.dupError = false; this.subcategoryOpen = false;
            if (this.step === 2) this.findNearby();
        },
        isTextOnly() { return this.locationPrecision === 'text_only'; },
        hasPinnedLocation() { return !this.isTextOnly() && !!this.lat && !!this.lng; },

        ensureMap() {
            if (!window.L) return;
            if (this.map) { this.$nextTick(() => this.map.invalidateSize()); return; }
            const mapTools = window.RoadPlatformMap;
            // The map stays the same size after a pin is chosen. A citizen can
            // always tap it again to replace the pin, without a separate edit UI.
            const mapDisplay = mapTools.mapDisplay || 'bangladesh';
            const countryBounds = L.latLngBounds([[20.60, 88.00], [26.65, 92.72]]).pad(0.04);
            const mapOptions = {
                zoomControl: true, dragging: true, touchZoom: true,
                scrollWheelZoom: true, doubleClickZoom: true, boxZoom: true,
                keyboard: true, tap: true,
            };
            // Respect the admin's global map-display choice. Bangladesh mode
            // is tightly bounded but deliberately has no opaque tile mask.
            if (mapDisplay === 'bangladesh') {
                mapOptions.maxBounds = countryBounds;
                mapOptions.maxBoundsViscosity = 1;
            }
            this.map = L.map('report-map', mapOptions);
            if (mapDisplay === 'bangladesh') this.fitBangladesh();
            else this.map.setView(@json($center), @json($zoom), { animate: false });
            L.tileLayer(@json($tileUrl), { maxZoom: 19, attribution: @json($attribution) }).addTo(this.map);
            this.map.on('click', (e) => { void this.setPoint(e.latlng.lat, e.latlng.lng, 'exact'); });
            if (this.hasPinnedLocation()) {
                const lat = Number(this.lat), lng = Number(this.lng);
                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                    this.addMarker(lat, lng);
                    this.map.setView([lat, lng], 15, { animate: false });
                }
            }
            if (mapDisplay === 'bangladesh') {
                this.$nextTick(() => { if (!this.hasPinnedLocation()) this.fitBangladesh(); });
                // Draw the Bangladesh boundary without an opaque world-mask.
                Promise.resolve(mapTools.bangladeshGeometryReady()).then((geometry) => {
                    L.geoJSON(geometry.geoJson, {
                        interactive: false,
                        style: { color: '#00875a', weight: 1.5, opacity: 0.9, fill: false },
                    }).addTo(this.map);
                }).catch(() => null).finally(() => {
                    if (!this.hasPinnedLocation()) this.fitBangladesh();
                });
            }
        },
        fitBangladesh() {
            if (!this.map || !window.L) return;
            this.map.fitBounds(L.latLngBounds([[20.60, 88.00], [26.65, 92.72]]), { padding: [8, 8], maxZoom: 8, animate: false });
        },
        addMarker(lat, lng) {
            if (this.marker) { this.marker.setLatLng([lat, lng]); return; }
            this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
            this.marker.on('dragend', async (e) => {
                const p = e.target.getLatLng();
                await window.RoadPlatformMap.bangladeshGeometryReady().catch(() => null);
                if (!window.RoadPlatformMap.isInBangladesh(p.lat, p.lng)) { e.target.setLatLng([Number(this.lat), Number(this.lng)]); alert(this.outsideBangladesh); return; }
                this.lat = p.lat.toFixed(7); this.lng = p.lng.toFixed(7); this.locationPrecision = 'exact';
            });
        },
        async setPoint(lat, lng, precision) {
            await window.RoadPlatformMap.bangladeshGeometryReady().catch(() => null);
            if (!window.RoadPlatformMap.isInBangladesh(lat, lng)) { alert(this.outsideBangladesh); return; }
            this.lat = lat.toFixed(7); this.lng = lng.toFixed(7); this.locationPrecision = precision || 'exact';
            this.addMarker(lat, lng);
            // Fly smoothly from the country overview to the selected point so
            // the citizen can keep geographic context while placing a pin.
            if (this.map) this.map.flyTo([lat, lng], Math.max(this.map.getZoom(), 15), {
                animate: true, duration: 1.25, easeLinearity: 0.22,
            });
        },
        useMyLocation() {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition(async (pos) => {
                const {latitude: lat, longitude: lng} = pos.coords;
                await this.setPoint(lat, lng, 'exact');
                if (this.map && window.RoadPlatformMap.isInBangladesh(lat, lng)) this.map.flyTo([lat, lng], 17, {
                    animate: true, duration: 1.25, easeLinearity: 0.22,
                });
            }, () => {}, {enableHighAccuracy: true, timeout: 12000, maximumAge: 60000});
        },

        get areaValue() { return this.union || this.upazila || this.district || this.division || ''; },
        hasAreaSuggestion() { return !!this.areaValue; },
        areaLabel() {
            const find = (rows, id) => (rows || []).find(row => String(row.id) === String(id));
            const path = [
                find(this.divisions, this.division),
                find(this.districts, this.district),
                find(this.upazilas, this.upazila),
                find(this.unions, this.union),
            ].filter(Boolean).map(row => row.label);
            return path.length ? path.join(' › ') : @json(__('report.area_suggestion_ready'));
        },
        async kids(id) {
            if (!id) return [];
            try { const r = await fetch(this.areaUrl + '/' + id + '/children', {headers:{Accept:'application/json'}}); return r.ok ? await r.json() : []; } catch (e) { return []; }
        },
        async pickDivision() { this.areaTouched=true; this.areaAuto=false; this.district='';this.upazila='';this.union='';this.districts=await this.kids(this.division);this.upazilas=[];this.unions=[]; },
        async pickDistrict() { this.areaTouched=true; this.areaAuto=false; this.upazila='';this.union='';this.upazilas=await this.kids(this.district);this.unions=[]; },
        async pickUpazila() { this.areaTouched=true; this.areaAuto=false; this.union='';this.unions=await this.kids(this.upazila); },
        pickUnion() { this.areaTouched=true; this.areaAuto=false; },
        watchPoint() {
            const go = () => { clearTimeout(this.areaTimer); this.areaTimer=setTimeout(() => this.locateArea(), 500); };
            this.$watch('lat', go); this.$watch('lng', go);
        },
        async locateArea() {
            if (this.areaTouched || this.isTextOnly() || !this.lat || !this.lng) return;
            const seq=++this.areaSeq; let d;
            try { const r=await fetch(this.areaUrl+'/locate?lat='+this.lat+'&lng='+this.lng,{headers:{Accept:'application/json'}}); if(!r.ok)return; d=await r.json(); } catch(e){return;}
            if(!d || !d.division || seq!==this.areaSeq || this.areaTouched)return;
            const ds=await this.kids(d.division.id), up=d.district?await this.kids(d.district.id):[], un=d.upazila?await this.kids(d.upazila.id):[];
            if(seq!==this.areaSeq || this.areaTouched)return;
            this.districts=ds;this.upazilas=up;this.unions=un;this.division=String(d.division.id); await this.$nextTick();
            this.district=d.district?String(d.district.id):'';this.upazila=d.upazila?String(d.upazila.id):'';this.union=d.union?String(d.union.id):'';this.areaAuto=true;
        },

        toStep2() {
            if (this.isTextOnly()) { if (!this.placeName.trim()) { alert(@json(__('report.place_required'))); return; } }
            else if (!this.lat || !this.lng) { alert(@json(__('report.pick_point'))); return; }
            this.step=2;
            if (this.categoryId) this.findNearby();
        },
        findNearby() {
            if (!this.categoryId || this.isTextOnly()) return;
            this.loading=true; this.nearby=[]; this.dupError=false;
            fetch('/api/v1/issues/nearby?lat='+encodeURIComponent(this.lat)+'&lng='+encodeURIComponent(this.lng)+'&radius=200&category_id='+encodeURIComponent(this.categoryId), {headers:{Accept:'application/json'}})
                .then(r=>{if(!r.ok)throw new Error('nearby');return r.json();})
                .then(d=>{this.nearby=d.data||[];})
                .catch(()=>{this.nearby=[];this.dupError=true;})
                .finally(()=>{this.loading=false;});
        },
        toStep3() {
            if (!this.categoryId) { alert(@json(__('report.select_category'))); return; }
            this.step=3;
        },

        updatePhotos(event) {
            this.photoPreviews.forEach(photo => { try { URL.revokeObjectURL(photo.url); } catch (e) {} });
            const files = Array.from(event.target.files || []).slice(0, 3);
            this.photoNames = files.map(file => file.name);
            this.photoPreviews = files.map(file => ({name: file.name, url: URL.createObjectURL(file)}));
            this.aiConsent=false; this.aiDraft=null; this.aiError=''; this.aiApplied=false;
        },
        requestAiSuggestion() {
            if (this.aiBusy) return;
            if (!this.aiConsent && !window.confirm(@json(__('report.ai_confirm')))) return;
            this.aiConsent = true;
            this.analyzePhoto();
        },
        async analyzePhoto() {
            const input=document.getElementById('photos-main');
            if (!this.aiEnabled || !this.aiConsent || !input || !input.files || !input.files.length) return;
            this.aiBusy=true; this.aiError=''; this.aiDraft=null;
            const fd=new FormData(); fd.append('photo',input.files[0]); fd.append('consent','1');
            try {
                const r=await fetch(this.aiUrl,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':this.csrf},body:fd});
                const data=await r.json(); if(!r.ok) throw new Error((data.errors&&data.errors.ai&&data.errors.ai[0])||@json(__('report.ai_unavailable')));
                this.aiDraft=data.data;
            } catch(e) { this.aiError=e.message || @json(__('report.ai_unavailable')); }
            finally { this.aiBusy=false; }
        },
        applyAiDraft() {
            if(!this.aiDraft || this.selectedIssue) return;
            this.title=this.aiDraft.title || this.title;
            this.titleError=false;
            this.description=this.wordLimit(this.aiDraft.description || this.description);
            // Type and duplicate review happened in step three. Keep that
            // verified category stable; citizens can go back to revise it.
            this.aiDraft=null; this.aiApplied=true;
        },
        validateFinalSubmission(event) {
            if (this.selectedIssue || this.title.trim()) return;
            event.preventDefault(); this.titleError=true;
            this.$nextTick(() => this.$refs.reportTitle && this.$refs.reportTitle.focus());
        },

        wordLimit(value) {
            const words = String(value || '').trim().split(/\s+/u).filter(Boolean);
            return words.slice(0, 150).join(' ');
        },
        descriptionWordCount() { return String(this.description || '').trim().split(/\s+/u).filter(Boolean).length; },
        limitDescriptionWords() {
            // Do not normalize on every keystroke: trimming here removed a
            // newly typed trailing space, so citizens could not enter a second word.
            // Keep spaces/newlines intact until the actual 150-word limit is crossed.
            if (this.descriptionWordCount() <= 150) return;
            this.description = this.wordLimit(this.description);
        },
        toggleVoiceTyping() {
            if (this.voiceListening && this.recognition) { this.recognition.stop(); return; }
            const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!Recognition) { this.voiceSupported=false; return; }
            const recognition = new Recognition();
            this.recognition = recognition; recognition.lang = this.locale === 'bn' ? 'bn-BD' : 'en-US'; recognition.interimResults = false; recognition.continuous = true;
            recognition.onstart = () => { this.voiceListening = true; };
            recognition.onend = () => { this.voiceListening = false; };
            recognition.onerror = () => { this.voiceListening = false; };
            recognition.onresult = (event) => {
                let spoken = '';
                for (let i=event.resultIndex; i<event.results.length; i++) if (event.results[i].isFinal) spoken += ' ' + event.results[i][0].transcript;
                if (spoken.trim()) this.description = this.wordLimit((this.description + ' ' + spoken.trim()).trim());
            };
            recognition.start();
        },
    };
}
</script>
@include('partials.photo-compress')
@endsection
