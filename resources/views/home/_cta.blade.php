@php
    $S = \App\Support\SiteSections::class;
    $brand = \App\Support\AppearanceSettings::all()['brand'];
    $ctaUrl = $S::txt('cta', 'url', '');
    if ($ctaUrl === '') {
        $ctaUrl = url('/report');
    }
@endphp
<section class="text-white" data-cta-band style="background:linear-gradient(100deg, {{ $brand }}, #0f172a);">
    <div class="mx-auto flex max-w-6xl flex-col items-start gap-4 px-4 py-7 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:py-9">
        <div class="flex items-start gap-3.5">
            <span class="mt-0.5 grid size-11 shrink-0 place-items-center rounded-full bg-white/15">
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 6.75 10.75 4.5l-3.5 3.5.7.7a2 2 0 1 1-2.83 2.83l-.7-.7L2 16.75V20.5h3.75l3-3m0 0 2.12 2.12a2 2 0 0 0 2.83 0l.7-.7m-4.65-4.42 2 2 4.25-4.25M13 6.75l1.5-1.5a2 2 0 0 1 2.83 0L19.5 7.5m-5.8 8.5 2.9-2.9"/></svg>
            </span>
            <div class="min-w-0">
                <h2 class="font-extrabold tracking-tight {{ $S::sizeClass('cta') }}">{{ $S::txt('cta', 'title', __('issues.cta_title')) }}</h2>
                <p class="mt-1 text-sm leading-relaxed text-white/85">{{ $S::txt('cta', 'text', __('issues.cta_text')) }}</p>
            </div>
        </div>
        <a href="{{ $ctaUrl }}" class="inline-flex shrink-0 items-center gap-2 rounded-full bg-white px-6 py-3 text-sm font-extrabold text-slate-900 shadow-lg transition hover:bg-slate-100 active:scale-[.98] sm:text-base">
            {{ $S::txt('cta', 'btn', __('issues.cta_btn')) }}
            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12l-7.5 7.5M21 12H3"/></svg>
        </a>
    </div>
</section>
