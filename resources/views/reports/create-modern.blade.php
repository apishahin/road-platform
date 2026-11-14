@php
    $inp  = 'mt-1.5 block w-full rounded-xl border border-slate-300 bg-slate-50/60 p-3 text-[15px] focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20';
@endphp
<div class="mx-auto max-w-2xl px-4 pb-28 pt-5 sm:pb-10" x-data="reportWizard()" data-report-design="modern">

    {{-- Header --}}
    <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">{{ __('report.title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('report.subtitle') }}</p>

    {{-- Progress bar --}}
    <div class="mt-4">
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
            <div class="h-full rounded-full bg-brand-600 transition-all duration-300" :style="'width:' + (step * 25) + '%'"></div>
        </div>
        <div class="mt-2 flex justify-between text-[11px] font-bold text-slate-500 sm:text-xs">
            @foreach ([1, 2, 3, 4] as $n)
                <span class="flex items-center gap-1" :class="step >= {{ $n }} ? 'text-brand-700' : ''">
                    <span class="flex size-5 items-center justify-center rounded-full text-[10px]"
                          :class="step >= {{ $n }} ? 'bg-brand-600 text-white' : 'bg-slate-200 text-slate-600'">{{ \App\Support\IssuePresenter::num($n) }}</span>
                    <span class="hidden sm:inline">{{ __('report.step' . $n) }}</span>
                </span>
            @endforeach
        </div>
    </div>

    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form x-ref="form" method="POST" action="{{ route('report.store') }}" enctype="multipart/form-data" class="mt-4"
          @keydown.enter="if ($event.target.tagName !== 'TEXTAREA') $event.preventDefault()">
        @csrf
        <input type="hidden" name="lat" :value="lat">
        <input type="hidden" name="lng" :value="lng">
        <input type="hidden" name="issue_id" :value="selectedIssue">

        {{-- ===== Step 1: location ===== --}}
        <div x-show="step === 1" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900">
                <span class="grid size-8 place-items-center rounded-xl bg-rose-50 text-rose-600">
                    <svg class="size-4.5 w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-6.5-5.2-6.5-10a6.5 6.5 0 1 1 13 0c0 4.8-6.5 10-6.5 10Z"/><circle cx="12" cy="11" r="2.2"/></svg>
                </span>
                {{ __('report.step1_heading') }}
            </h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('report.step1_hint') }}</p>

            <button type="button" @click="useMyLocation()"
                    class="mt-3 inline-flex items-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-100">
                📍 {{ __('report.use_my_location') }}
            </button>
            <div id="report-map" class="mt-3 h-64 w-full touch-none rounded-2xl border border-slate-300 sm:h-80"></div>

            <div class="mt-4 flex justify-end">
                <button type="button" @click="toStep2()" :disabled="!lat"
                        class="w-full rounded-xl bg-brand-600 px-6 py-3 text-base font-bold text-white shadow-sm transition active:scale-[.98] disabled:opacity-50 sm:w-auto">
                    {{ __('report.next') }} →
                </button>
            </div>
        </div>

        {{-- ===== Step 2: duplicate check ===== --}}
        <div x-show="step === 2" x-cloak class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900">
                <span class="grid size-8 place-items-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="w-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                </span>
                {{ __('report.step2_heading') }}
            </h2>

            <p x-show="loading" class="mt-3 text-sm text-slate-500">{{ __('report.checking_duplicates') }}</p>
            <p x-show="dupError" class="mt-3 text-sm text-red-700">{{ __('report.dup_error') }}</p>
            <p x-show="!loading && !dupError && nearby.length === 0"
               class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">{{ __('report.no_duplicates') }}</p>

            <div x-show="!loading && nearby.length > 0" class="mt-3 space-y-2.5">
                <p class="text-sm text-slate-600">{{ __('report.duplicates_found') }}</p>
                <template x-for="item in nearby" :key="item.id">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                           :class="selectedIssue === item.id ? 'border-brand-600 bg-brand-50 ring-1 ring-brand-600/30' : 'border-slate-200 bg-white hover:bg-slate-50'">
                        <input type="radio" :value="item.id" x-model="selectedIssue" class="mt-1 size-4 text-brand-600">
                        <div class="text-sm">
                            <p class="font-bold text-slate-900" x-text="item.title"></p>
                            <p class="text-xs text-slate-500"
                               x-text="item.category + ' · ' + item.status + ' · ' + Math.round(item.meters) + ' {{ __('report.meters_away') }}'"></p>
                            <a :href="item.url" target="_blank" rel="noopener" class="text-xs font-semibold text-brand-700 underline">{{ __('report.view') }}</a>
                        </div>
                    </label>
                </template>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                       :class="selectedIssue === '' ? 'border-brand-600 bg-brand-50 ring-1 ring-brand-600/30' : 'border-slate-200 bg-white hover:bg-slate-50'">
                    <input type="radio" value="" x-model="selectedIssue" class="mt-1 size-4 text-brand-600">
                    <div class="text-sm">
                        <p class="font-bold text-slate-900">{{ __('report.new_issue') }}</p>
                        <p class="text-xs text-slate-500">{{ __('report.new_issue_hint') }}</p>
                    </div>
                </label>
            </div>

            <div class="mt-4 flex gap-2.5">
                <button type="button" @click="step = 1" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100">← {{ __('report.back') }}</button>
                <button type="button" @click="step = 3" :disabled="loading"
                        class="flex-1 rounded-xl bg-brand-600 px-6 py-3 text-base font-bold text-white shadow-sm transition active:scale-[.98] disabled:opacity-50 sm:flex-none">{{ __('report.next') }} →</button>
            </div>
        </div>

        {{-- ===== Step 3: details ===== --}}
        <div x-show="step === 3" x-cloak class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900">
                <span class="grid size-8 place-items-center rounded-xl bg-amber-50 text-amber-600">
                    <svg class="w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3.5h7L19 8v12.5H7V3.5Z"/><path stroke-linecap="round" d="M13.5 3.5V8H19M9.5 12h5M9.5 15.5h5"/></svg>
                </span>
                {{ __('report.step3_heading') }}
            </h2>
            <p x-show="selectedIssue" class="mt-3 rounded-xl bg-slate-100 p-3 text-xs text-slate-700">{{ __('report.existing_note') }}</p>

            <div x-show="!selectedIssue" class="mt-3 space-y-3.5">
                <div>
                    <label class="text-sm font-semibold text-slate-700">{{ __('report.category') }} *</label>
                    <select name="category_id" data-step3 :required="!selectedIssue" class="{{ $inp }}">
                        <option value="">{{ __('report.select_category') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat['id'] }}" @selected(old('category_id') == $cat['id'])>{{ $cat['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-700">{{ __('report.issue_title') }} *</label>
                    <input type="text" name="title" data-step3 :required="!selectedIssue" maxlength="150"
                           value="{{ old('title') }}" placeholder="{{ __('report.title_placeholder') }}" class="{{ $inp }}">
                </div>
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-slate-700">{{ __('report.road_name') }}</label>
                        <input type="text" name="road_name" maxlength="150" value="{{ old('road_name') }}"
                               placeholder="{{ __('report.road_placeholder') }}" class="{{ $inp }}">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-slate-700">{{ __('report.landmark') }}</label>
                        <input type="text" name="landmark" maxlength="150" value="{{ old('landmark') }}"
                               placeholder="{{ __('report.landmark_placeholder') }}" class="{{ $inp }}">
                    </div>
                </div>
            </div>

            <div class="mt-3.5 rounded-xl border border-slate-200 bg-slate-50 p-3.5">
                <p class="text-sm font-semibold text-slate-700">{{ __('suggest.title') }}</p>
                <p class="text-xs text-slate-500">{{ __('suggest.hint') }}</p>
                <p x-show="areaAuto" class="mt-1 text-xs font-semibold text-amber-700">{{ __('suggest.auto') }}</p>
                <input type="hidden" name="suggested_area_id" :value="areaValue" :disabled="!areaValue">
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <select x-model="division" @change="pickDivision()" aria-label="{{ __('areas.division') }}" class="{{ $inp }};margin-top:0;">
                        <option value="">{{ __('areas.all_divisions') }}</option>
                        <template x-for="a in divisions" :key="a.id"><option :value="String(a.id)" x-text="a.label"></option></template>
                    </select>
                    <select x-show="districts.length > 0" x-model="district" @change="pickDistrict()" aria-label="{{ __('areas.district') }}" class="{{ $inp }};margin-top:0;">
                        <option value="">{{ __('areas.all_districts') }}</option>
                        <template x-for="a in districts" :key="a.id"><option :value="String(a.id)" x-text="a.label"></option></template>
                    </select>
                    <select x-show="upazilas.length > 0" x-model="upazila" @change="pickUpazila()" aria-label="{{ __('areas.upazila') }}" class="{{ $inp }};margin-top:0;">
                        <option value="">{{ __('areas.all_upazilas') }}</option>
                        <template x-for="a in upazilas" :key="a.id"><option :value="String(a.id)" x-text="a.label"></option></template>
                    </select>
                    <select x-show="unions.length > 0" x-model="union" @change="pickUnion()" aria-label="{{ __('areas.union') }}" class="{{ $inp }};margin-top:0;">
                        <option value="">{{ __('areas.all_unions') }}</option>
                        <template x-for="a in unions" :key="a.id"><option :value="String(a.id)" x-text="a.label"></option></template>
                    </select>
                </div>
            </div>

            <div class="mt-3.5">
                <label class="text-sm font-semibold text-slate-700">{{ __('report.description') }} *</label>
                <textarea name="description" data-step3 required rows="4" maxlength="1000"
                          placeholder="{{ __('report.desc_placeholder') }}" class="{{ $inp }}">{{ old('description') }}</textarea>
            </div>

            <div class="mt-4 flex gap-2.5">
                <button type="button" @click="step = 2" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100">← {{ __('report.back') }}</button>
                <button type="button" @click="toStep4()" class="flex-1 rounded-xl bg-brand-600 px-6 py-3 text-base font-bold text-white shadow-sm transition active:scale-[.98] sm:flex-none">{{ __('report.next') }} →</button>
            </div>
        </div>

        {{-- ===== Step 4: photos ===== --}}
        <div x-show="step === 4" x-cloak class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 class="flex items-center gap-2 text-lg font-bold text-slate-900">
                <span class="grid size-8 place-items-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="w-[18px]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="14" rx="2.5"/><circle cx="9" cy="10" r="1.8"/><path stroke-linecap="round" stroke-linejoin="round" d="m5 18 4.5-4.5 3 3 3.5-3.5L20.5 17"/></svg>
                </span>
                {{ __('report.step4_heading') }}
            </h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('report.photos_hint') }}</p>
            <p class="mt-2 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('privacy.photo_warn') }}</p>

            <div class="mt-3" x-data="{ names: [] }">
                <label class="flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center transition-colors hover:border-brand-400 hover:bg-brand-50">
                    <svg class="size-7 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V5m0 0-4 4m4-4 4 4"/><path stroke-linecap="round" d="M4 19h16"/></svg>
                    <span class="text-sm font-bold text-slate-600">{{ __('report.photos_choose') }}</span>
                    <span x-show="names.length === 0" class="text-xs text-slate-400">{{ __('report.photos_none') }}</span>
                    <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="sr-only"
                           @change="names = Array.from($event.target.files).map(f => f.name)">
                </label>
                <ul x-show="names.length > 0" class="mt-2 space-y-1.5 text-xs text-slate-600">
                    <template x-for="n in names" :key="n">
                        <li class="flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-2">
                            <svg class="size-3.5 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            <span class="truncate" x-text="n"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <div class="mt-3 rounded-xl bg-amber-50 p-4 text-xs text-amber-800">⚠️ {{ __('report.claim_notice') }}</div>

            {{-- Sticky action bar (mobile-first) --}}
            <div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 p-3 backdrop-blur sm:static sm:mt-4 sm:border-0 sm:bg-transparent sm:p-0">
                <div class="mx-auto flex max-w-2xl gap-2.5">
                    <button type="button" @click="step = 3" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-100">← {{ __('report.back') }}</button>
                    <button type="submit" class="flex-1 rounded-xl bg-brand-600 px-6 py-3 text-base font-bold text-white shadow-lg shadow-brand-600/25 transition active:scale-[.98] hover:bg-brand-700">{{ __('report.submit') }}</button>
                </div>
            </div>
        </div>
    </form>
</div>
