@php $inp = 'mt-1.5 block w-full rounded-xl border border-slate-300 bg-slate-50/60 p-3 text-[15px] focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20'; @endphp
<div class="mx-auto max-w-2xl px-4 pb-28 pt-5 sm:pb-10" x-data="reportWizard()" data-report-design="{{ \App\Support\SiteSections::modern('report') ? 'modern' : 'classic' }}">
    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">{{ __('report.title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('report.subtitle') }}</p>

    <div class="mt-4" aria-label="{{ __('report.progress') }}">
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-brand-600 transition-all duration-300" :style="'width:'+((step / 3) * 100)+'%'"></div></div>
        <div class="mt-2 flex justify-between text-[10px] font-bold text-slate-500 sm:text-xs">
            @foreach ([1, 2, 3] as $n)
                <span class="flex items-center gap-1" :class="step >= {{ $n }} ? 'text-brand-700' : ''"><span class="grid size-5 place-items-center rounded-full" :class="step >= {{ $n }} ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-600'">{{ \App\Support\IssuePresenter::num($n) }}</span><span>{{ __('report.step' . $n) }}</span></span>
            @endforeach
        </div>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
    @endif

    <form x-ref="form" method="POST" action="{{ route('report.store') }}" enctype="multipart/form-data" class="mt-4" @submit="validateFinalSubmission($event)" @keydown.enter="if ($event.target.tagName !== 'TEXTAREA') $event.preventDefault()">
        @csrf
        <input type="hidden" name="lat" :value="lat">
        <input type="hidden" name="lng" :value="lng">
        <input type="hidden" name="location_precision" :value="locationPrecision">
        <input type="hidden" name="category_id" :value="categoryId">
        <input type="hidden" name="issue_id" :value="selectedIssue">
        <input type="hidden" name="report_step" :value="step">

        {{-- 1. Location: tap anywhere on the map to place or replace the pin. --}}
        <section x-show="step===1" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 class="text-lg font-bold text-slate-900">{{ __('report.step1_heading') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('report.step1_hint') }}</p>

            <div class="mt-4 h-72 overflow-hidden rounded-2xl border border-slate-300 bg-slate-100 shadow-inner sm:h-80">
                <div id="report-map" class="h-full w-full"></div>
            </div>

            <div class="mt-3 flex justify-center">
                <button type="button" @click="useMyLocation()" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-100">{{ __('report.use_my_location') }}</button>
            </div>

            <details class="mt-3 text-sm text-slate-600">
                <summary class="cursor-pointer font-semibold text-brand-700">{{ __('report.location_more') }}</summary>
                <div class="mt-3 grid gap-3 sm:grid-cols-2"><label>{{ __('report.road_name') }}<input name="road_name" x-model="roadName" maxlength="150" placeholder="{{ __('report.road_placeholder') }}" class="{{ $inp }}"></label><label>{{ __('report.landmark') }}<input name="landmark" x-model="landmark" maxlength="150" placeholder="{{ __('report.landmark_placeholder') }}" class="{{ $inp }}"></label></div>
            </details>

            <div class="mt-5 flex justify-end"><button type="button" @click="toStep2()" class="w-full rounded-xl bg-brand-600 px-6 py-3 text-base font-bold text-white shadow-sm active:scale-[.98] sm:w-auto">{{ __('report.next') }} →</button></div>
        </section>

        {{-- 2. Category and nearby matching remain together before any AI action. --}}
        <section x-show="step===2" x-cloak class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 class="text-lg font-bold text-slate-900">{{ __('report.step2_heading') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('report.category_hint') }}</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="relative text-sm font-semibold text-slate-700" @click.outside="mainCategoryOpen=false">
                    <span>{{ __('report.main_category') }} *</span>
                    <button type="button" @click="mainCategoryOpen=!mainCategoryOpen; subcategoryOpen=false" :aria-expanded="mainCategoryOpen" class="mt-1.5 flex w-full items-center justify-between rounded-xl border border-slate-300 bg-slate-50/60 p-3 text-left text-[15px] font-normal text-slate-800 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20"><span class="truncate" x-text="mainCategoryLabel() || @js(__('report.select_main_category'))"></span><svg class="ml-3 size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="m5.23 7.21 4.77 4.77 4.77-4.77a.75.75 0 0 1 1.06 1.06l-5.3 5.3a.75.75 0 0 1-1.06 0l-5.3-5.3a.75.75 0 0 1 1.06-1.06Z" clip-rule="evenodd"/></svg></button>
                    <div x-show="mainCategoryOpen" x-cloak class="absolute inset-x-0 z-30 mt-1 max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl" role="listbox">
                        <template x-for="group in taxonomy" :key="group.id"><button type="button" @click="chooseMain(group.id)" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-800 hover:bg-brand-50 hover:text-brand-800" :class="String(group.id)===String(mainCategory) ? 'bg-brand-50 text-brand-800' : ''" x-text="group.label"></button></template>
                    </div>
                </div>
                <div x-show="mainCategory" x-cloak class="relative text-sm font-semibold text-slate-700" @click.outside="subcategoryOpen=false">
                    <span>{{ __('report.subcategory') }} *</span>
                    <button type="button" @click="subcategoryOpen=!subcategoryOpen; mainCategoryOpen=false" :aria-expanded="subcategoryOpen" class="mt-1.5 flex w-full items-center justify-between rounded-xl border border-slate-300 bg-slate-50/60 p-3 text-left text-[15px] font-normal text-slate-800 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20"><span class="truncate" x-text="categoryLabel() || @js(__('report.select_subcategory'))"></span><svg class="ml-3 size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="m5.23 7.21 4.77 4.77 4.77-4.77a.75.75 0 0 1 1.06 1.06l-5.3 5.3a.75.75 0 0 1-1.06 0l-5.3-5.3a.75.75 0 0 1 1.06-1.06Z" clip-rule="evenodd"/></svg></button>
                    <div x-show="subcategoryOpen" x-cloak class="absolute inset-x-0 z-30 mt-1 max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 shadow-xl" role="listbox">
                        <template x-for="child in children()" :key="child.id"><button type="button" @click="chooseCategory(child.id)" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-slate-800 hover:bg-brand-50 hover:text-brand-800" :class="String(child.id)===String(categoryId) ? 'bg-brand-50 text-brand-800' : ''" x-text="child.label"></button></template>
                    </div>
                </div>
            </div>

            <div x-show="categoryId" x-cloak class="mt-5 border-t border-slate-100 pt-4">
                <h3 class="font-bold text-slate-800">{{ __('report.similar_heading') }}</h3>
                <p x-show="isTextOnly()" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ __('report.text_only_no_duplicates') }}</p>
                <p x-show="loading" class="mt-3 text-sm text-slate-500">{{ __('report.checking_duplicates') }}</p>
                <p x-show="dupError" class="mt-3 text-sm text-red-700">{{ __('report.dup_error') }}</p>
                <p x-show="!isTextOnly() && !loading && !dupError && nearby.length===0" class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">{{ __('report.no_duplicates') }}</p>
                <div x-show="!isTextOnly() && !loading && nearby.length>0" class="mt-3 space-y-2.5">
                    <p class="text-sm text-slate-600">{{ __('report.duplicates_found') }}</p>
                    <template x-for="item in nearby" :key="item.id"><label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition" :class="selectedIssue===item.id ? 'border-brand-600 bg-brand-50 ring-1 ring-brand-600/30' : 'border-slate-200 bg-white hover:bg-slate-50'"><input type="radio" :value="item.id" x-model="selectedIssue" class="mt-1 size-4 text-brand-600"><span class="text-sm"><strong class="block text-slate-900" x-text="item.title"></strong><span class="text-xs text-slate-500" x-text="item.category+' · '+item.status+' · '+Math.round(item.meters)+' {{ __('report.meters_away') }}'"></span><a :href="item.url" target="_blank" rel="noopener" class="ml-1 text-xs font-semibold text-brand-700 underline">{{ __('report.view') }}</a></span></label></template>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition" :class="selectedIssue==='' ? 'border-brand-600 bg-brand-50 ring-1 ring-brand-600/30' : 'border-slate-200 bg-white hover:bg-slate-50'"><input type="radio" value="" x-model="selectedIssue" class="mt-1 size-4 text-brand-600"><span class="text-sm"><strong class="block text-slate-900">{{ __('report.new_issue') }}</strong><span class="text-xs text-slate-500">{{ __('report.new_issue_hint') }}</span></span></label>
                </div>
            </div>
            <div class="mt-5 flex gap-2.5"><button type="button" @click="step=1" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100">← {{ __('report.back') }}</button><button type="button" @click="toStep3()" :disabled="!categoryId || loading" class="ml-auto flex-1 rounded-xl bg-brand-600 px-6 py-3 text-base font-bold text-white shadow-sm disabled:opacity-50 sm:flex-none">{{ __('report.next') }} →</button></div>
        </section>

        {{-- 3. Evidence and short, optional detail are the final review step. --}}
        <section x-show="step===3" x-cloak class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 class="text-lg font-bold text-slate-900">{{ __('report.step3_heading') }}</h2>
            <p class="mt-1 text-xs text-slate-600">{{ __('report.step3_hint') }}</p>

            <div x-show="!selectedIssue" class="mt-3">
                <label class="text-sm font-semibold text-slate-700">{{ __('report.issue_title') }} <span class="text-red-700">*</span></label>
                <input x-ref="reportTitle" name="title" x-model="title" @input="titleError=false" maxlength="150" :aria-invalid="titleError" placeholder="{{ __('report.title_placeholder') }}" class="{{ $inp }}" :class="titleError ? 'border-red-400 bg-red-50' : ''">
                <p x-show="titleError" x-cloak class="mt-1 text-xs font-semibold text-red-700">{{ __('report.title_required') }}</p>
            </div>

            <div class="mt-3">
                <label class="flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-center transition hover:border-brand-400 hover:bg-brand-50">
                    <span class="grid size-8 place-items-center rounded-full bg-white text-base shadow-sm">📷</span>
                    <span class="text-sm font-bold text-slate-700">{{ __('report.photos_choose') }}</span>
                    <span class="text-[11px] text-slate-500">{{ __('report.photos_preview_hint') }}</span>
                    <input id="photos-main" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only" data-compress data-compress-max="3" @change="updatePhotos($event)">
                </label>
                <label class="mt-1.5 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-600 hover:border-brand-400 hover:text-brand-700"><span>{{ __('report.photos_camera') }}</span><input type="file" accept="image/*" capture="environment" data-camera-for="photos-main" class="sr-only"></label>
                <p class="mt-1 text-center text-[10px] text-slate-400">{{ __('report.photos_compress_note') }}</p>
                <div x-show="photoPreviews.length" x-cloak class="mt-2 grid grid-cols-3 gap-2"><template x-for="photo in photoPreviews" :key="photo.url"><figure class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50"><img :src="photo.url" :alt="photo.name" class="aspect-square w-full object-cover"><figcaption class="truncate px-1.5 py-1 text-[10px] text-slate-500" x-text="photo.name"></figcaption></figure></template></div>
            </div>

            @if ($aiEnabled)
                <div x-show="photoPreviews.length && !selectedIssue" x-cloak class="mt-2">
                    <button type="button" @click="requestAiSuggestion()" :disabled="aiBusy" class="inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 focus:ring-1 focus:ring-indigo-300 disabled:opacity-50" x-text="aiBusy ? @js(__('report.ai_working')) : @js(__('report.ai_action_compact'))"></button>
                    <p x-show="aiError" class="mt-1 text-xs font-semibold text-red-700" x-text="aiError"></p>
                    <div x-show="aiDraft" x-cloak class="mt-2 max-h-28 overflow-y-auto rounded-lg border border-indigo-200 bg-indigo-50 p-2.5 text-xs"><p class="font-bold text-slate-900" x-text="aiDraft && aiDraft.title"></p><p class="mt-1 whitespace-pre-line text-slate-700" x-text="aiDraft && aiDraft.description"></p><div class="mt-2 flex items-center justify-between gap-2"><span class="text-[11px] text-slate-500"><span x-text="aiDraft && aiDraft.confidence"></span>% · <span x-text="aiDraft && aiDraft.uncertainty"></span></span><button type="button" @click="applyAiDraft()" class="rounded-md border border-indigo-300 bg-white px-2 py-1 text-xs font-bold text-indigo-800">{{ __('report.ai_apply') }}</button></div></div>
                </div>
            @endif

            <p x-show="selectedIssue" class="mt-3 rounded-xl bg-slate-100 p-3 text-xs text-slate-700">{{ __('report.existing_note') }}</p>
            <p x-show="aiApplied && !selectedIssue" class="mt-2 text-xs font-semibold text-indigo-800">{{ __('report.ai_edit_after_apply') }}</p>
            <div class="mt-4">
                <div class="flex items-baseline justify-between gap-3"><label class="text-sm font-semibold text-slate-700" x-text="selectedIssue ? @js(__('report.note_for_existing')) : @js(__('report.description'))"></label><span class="text-xs text-slate-500"><span x-text="descriptionWordCount()"></span>/150 {{ __('report.words') }}</span></div>
                <div class="relative"><textarea name="description" x-model="description" @input="limitDescriptionWords()" rows="3" placeholder="{{ __('report.what_seen_placeholder') }}" class="{{ $inp }} pr-12"></textarea><button x-show="voiceSupported" type="button" @click="toggleVoiceTyping()" class="absolute bottom-2 right-2 grid size-8 place-items-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm hover:border-brand-400 hover:text-brand-700" :class="voiceListening ? 'border-rose-300 bg-rose-50 text-rose-700' : ''" :aria-label="voiceListening ? @js(__('report.voice_stop')) : @js(__('report.voice_type'))" :title="voiceListening ? @js(__('report.voice_stop')) : @js(__('report.voice_type'))"><svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"></rect><path d="M5 11a7 7 0 0 0 14 0M12 18v3M8 21h8"></path></svg></button></div>
                <p x-show="voiceListening" class="mt-1 text-xs font-semibold text-rose-700">{{ __('report.voice_listening') }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ __('report.description_optional_hint') }}</p>
            </div>

            <div x-show="!isTextOnly() && hasAreaSuggestion()" x-cloak class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                <div class="flex flex-wrap items-center justify-between gap-2"><div><p class="text-sm font-semibold text-slate-700">{{ __('report.area_suggestion') }}</p><p class="text-xs text-slate-500" x-text="areaLabel()"></p></div><button type="button" @click="areaEditing=!areaEditing" class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs font-bold text-slate-700"><span aria-hidden="true">✎</span>{{ __('report.area_edit') }}</button></div>
                <input type="hidden" name="suggested_area_id" :value="areaValue" :disabled="!areaValue || isTextOnly()">
                <div x-show="areaEditing" x-cloak class="mt-3 grid gap-2 sm:grid-cols-2">
                    <select x-model="division" @change="pickDivision()" aria-label="{{ __('areas.division') }}" class="{{ $inp }};margin-top:0"><option value="">{{ __('areas.all_divisions') }}</option><template x-for="a in divisions" :key="a.id"><option :value="String(a.id)" x-text="a.label"></option></template></select>
                    <select x-show="districts.length>0" x-model="district" @change="pickDistrict()" aria-label="{{ __('areas.district') }}" class="{{ $inp }};margin-top:0"><option value="">{{ __('areas.all_districts') }}</option><template x-for="a in districts" :key="a.id"><option :value="String(a.id)" x-text="a.label"></option></template></select>
                    <select x-show="upazilas.length>0" x-model="upazila" @change="pickUpazila()" aria-label="{{ __('areas.upazila') }}" class="{{ $inp }};margin-top:0"><option value="">{{ __('areas.all_upazilas') }}</option><template x-for="a in upazilas" :key="a.id"><option :value="String(a.id)" x-text="a.label"></option></template></select>
                    <select x-show="unions.length>0" x-model="union" @change="pickUnion()" aria-label="{{ __('areas.union') }}" class="{{ $inp }};margin-top:0"><option value="">{{ __('areas.all_unions') }}</option><template x-for="a in unions" :key="a.id"><option :value="String(a.id)" x-text="a.label"></option></template></select>
                </div>
            </div>
            <p x-show="isTextOnly()" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">{{ __('report.text_only_review') }}</p>
            <div class="mt-4 rounded-xl bg-amber-50 p-4 text-xs text-amber-800">⚠️ {{ __('report.claim_notice') }}</div>
            <div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 p-3 backdrop-blur sm:static sm:mt-5 sm:border-0 sm:bg-transparent sm:p-0"><div class="mx-auto flex max-w-2xl gap-2.5"><button type="button" @click="step=2" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100">← {{ __('report.back') }}</button><button type="submit" class="flex-1 rounded-xl bg-brand-600 px-6 py-3 text-base font-bold text-white shadow-lg shadow-brand-600/25 hover:bg-brand-700">{{ __('report.submit') }}</button></div></div>
        </section>
    </form>
</div>
