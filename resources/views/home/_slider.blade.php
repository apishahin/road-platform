{{-- Hero photo slider (reference port): full-bleed photo · dark green overlay ·
     *highlighted* words · left actions + dots/arrows · right rotated tag.
     Admin-editable in "Homepage & site" → Hero photo slider. --}}
@php
    $__raw = collect($heroSlides ?? []);
    $__slides = $__raw->map(function ($s) {
        return [
            'img_url' => ($s['img'] ?? '') !== '' ? \App\Support\SiteSections::heroImgUrl($s['img']) : ($s['url'] ?? ''),
            'title'   => $s['title'] ?? '',
            'text'    => $s['text'] ?? '',
            'tag'     => $s['tag'] ?? '',
        ];
    })->filter(fn ($s) => $s['img_url'] !== '')->values();
@endphp
@if ($__slides->isEmpty())
    <section class="hero hero-plain" data-hero-slider>
        <div class="rp-wrap hero-inner">
            <div class="hero-copy">
                <div class="eyebrow">● {{ __('issues.hero_eyebrow') }}</div>
                <h1>{{ __('issues.hero_plain_title_1') }}<br><span class="hl">{{ __('issues.hero_plain_title_hl') }}</span></h1>
                <div class="hero-sub">{{ __('issues.hero_plain_sub') }}</div>
                <p class="hero-desc">{{ __('issues.hero_plain_text') }}</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="{{ url('/report') }}">⚑ {{ \App\Support\SiteSections::txt('hero', 'cta_label', __('Report a problem')) }}</a>
                    <a class="btn btn-outline-light" href="#home-map">⌖ {{ __('issues.hero_map_btn') }}</a>
                </div>
                @if (\App\Support\SiteSections::txt('hero', 'cta_sub') !== '')
                    <p style="margin:10px 0 0;font-size:12.5px;color:rgba(255,255,255,.72);">{{ \App\Support\SiteSections::txt('hero', 'cta_sub') }}</p>
                @endif
            </div>
            <div class="hero-note">{{ __('issues.hero_note_1') }}<span>{{ __('issues.hero_note_hl') }}</span></div>
        </div>
    </section>
@else
    <section class="hero" data-hero-slider x-data="{
            i: 0, n: {{ $__slides->count() }}, t: null,
            start() { this.t = setInterval(() => { this.i = (this.i + 1) % this.n }, 6500) },
            go(v) { this.i = v; clearInterval(this.t); this.start() }
        }" x-init="start()">
        @foreach ($__slides as $si => $sl)
            <div class="hero-slide" x-show="i === {{ $si }}" @if ($si > 0) x-cloak @endif x-transition.opacity.duration.600ms>
                <img class="hero-img" src="{{ $sl['img_url'] }}" alt="{{ trim(str_replace('*', '', $sl['title'])) !== '' ? str_replace('*', '', $sl['title']) : config('app.name') }}" @if ($si > 0) loading="lazy" @endif>
                <div class="hero-ov"></div>
                <div class="rp-wrap hero-inner">
                    <div class="hero-copy">
                        <div class="eyebrow">● {{ __('issues.hero_eyebrow') }}</div>
                        @if ($sl['title'] !== '')
                            <h1>{!! \App\Support\SiteSections::heroHl($sl['title']) !!}</h1>
                        @endif
                        @if ($sl['text'] !== '')
                            <p class="hero-desc">{!! \App\Support\SiteSections::heroHl($sl['text']) !!}</p>
                        @endif
                        <div class="hero-actions">
                            <a class="btn btn-primary" href="{{ url('/report') }}">⚑ {{ \App\Support\SiteSections::txt('hero', 'cta_label', __('Report a problem')) }}</a>
                            <a class="btn btn-outline-light" href="#home-map">⌖ {{ __('issues.hero_map_btn') }}</a>
                        </div>
                        @if ($__slides->count() > 1)
                            <div class="dots" role="group" aria-label="Slides">
                                @foreach ($__slides as $di => $__d)
                                    <button type="button" :class="{ 'on': i === {{ $di }} }" @click="go({{ $di }})" aria-label="{{ $di + 1 }}"></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                @if ($sl['tag'] !== '')
                    <div class="hero-note">{!! \App\Support\SiteSections::heroHl($sl['tag']) !!}</div>
                @endif
            </div>
        @endforeach
        @if ($__slides->count() > 1)
            <button type="button" class="hero-arrow hero-arrow-l" @click="go((i - 1 + n) % n)" aria-label="{{ __('issues.slider_prev') }}">
                <svg style="width:18px;height:18px;display:inline;" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
            </button>
            <button type="button" class="hero-arrow hero-arrow-r" @click="go((i + 1) % n)" aria-label="{{ __('issues.slider_next') }}">
                <svg style="width:18px;height:18px;display:inline;" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
            </button>
        @endif
    </section>
@endif
