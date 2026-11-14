@php
    $S = \App\Support\SiteSections::class;
    $hs = $S::heroSlider();
    $slides = $hs['slides'];
    $n = count($slides);
@endphp
<section data-hero-slider class="relative overflow-hidden bg-slate-900 text-white {{ $S::modern('home') ? 'rounded-b-[2rem] sm:rounded-b-[3rem]' : '' }}"
         x-data="{
            i: 0, n: {{ $n }}, timer: null,
            go(k) { this.i = (k + this.n) % this.n; },
            start() { if (this.n > 1) { this.timer = setInterval(() => this.go(this.i + 1), 6000); } },
            stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } }
         }"
         x-init="start()" @mouseenter="stop()" @mouseleave="start()">

    <div class="relative h-[430px] sm:h-[500px]">
        @foreach ($slides as $k => $sl)
            @php $img = $sl['img'] !== '' ? $S::heroImgUrl($sl['img']) : $sl['url']; @endphp
            <div x-show="i === {{ $k }}" x-transition:enter="transition ease-out duration-500"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 class="absolute inset-0" {{ $k > 0 ? 'x-cloak' : '' }} role="group" aria-roledescription="slide" aria-label="{{ $k + 1 }} / {{ $n }}">
                <img src="{{ $img }}" alt="" class="h-full w-full object-cover" @if ($k > 0) loading="lazy" @endif>
                <div class="absolute inset-0 bg-gradient-to-r from-slate-950/85 via-slate-900/55 to-slate-900/20"></div>

                <div class="absolute inset-0 flex items-center">
                    <div class="mx-auto w-full max-w-6xl px-4">
                        <div class="max-w-xl">
                            <h2 class="font-extrabold leading-tight tracking-tight text-2xl sm:text-4xl">
                                {{ $sl['title'] !== '' ? $sl['title'] : $S::txt('hero', 'title', __('Find road problems. Follow the repair.')) }}
                            </h2>
                            @if ($sl['text'] !== '')
                                <p class="mt-2.5 text-sm leading-relaxed text-slate-200 sm:mt-3 sm:text-base">{{ $sl['text'] }}</p>
                            @endif
                            <div class="mt-4 flex flex-col gap-2.5 sm:mt-5 sm:flex-row sm:gap-3">
                                <a href="{{ url('/report') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-brand-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-black/25 transition hover:bg-brand-700 active:scale-[.98] sm:text-base">
                                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                                    {{ __('Report a problem') }}
                                </a>
                                <a href="{{ url('/issues') }}" class="inline-flex items-center justify-center rounded-full border border-white/50 px-6 py-3 text-sm font-bold text-white backdrop-blur-sm transition hover:bg-white/10 sm:text-base">
                                    {{ __('Browse issues') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($sl['tag'] !== '')
                    <div class="absolute bottom-16 right-4 hidden -rotate-2 rounded-xl bg-emerald-500/95 px-4 py-2 text-sm font-extrabold text-slate-950 shadow-xl sm:block lg:right-10">
                        {{ $sl['tag'] }}
                    </div>
                @endif
            </div>
        @endforeach

        @if ($n > 1)
            <button type="button" @click="go(i - 1)" aria-label="{{ __('issues.slider_prev') }}"
                    class="absolute left-2 top-1/2 z-10 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/20 text-white backdrop-blur transition hover:bg-white/40 sm:left-4 sm:size-11">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <button type="button" @click="go(i + 1)" aria-label="{{ __('issues.slider_next') }}"
                    class="absolute right-2 top-1/2 z-10 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/20 text-white backdrop-blur transition hover:bg-white/40 sm:right-4 sm:size-11">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
            <div class="absolute bottom-4 left-1/2 z-10 flex -translate-x-1/2 gap-1.5">
                @for ($k = 0; $k < $n; $k++)
                    <button type="button" @click="go({{ $k }})" aria-label="{{ __('issues.slider_go', ['n' => \App\Support\IssuePresenter::num($k + 1)]) }}"
                            class="h-2 rounded-full transition-all" :class="i === {{ $k }} ? 'w-6 bg-white' : 'w-2 bg-white/50 hover:bg-white/80'"></button>
                @endfor
            </div>
        @endif
    </div>
</section>
