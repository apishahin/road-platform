{{-- "Report first" CTA: a big card on the home hero, a floating button
     everywhere else. Hides while the visitor scrolls down so it never
     covers reading, comes back on scroll-up. --}}
<div id="rp-report-cta" class="rp-report-cta">

    {{-- Hero card (home page only) --}}
    <div class="mx-auto mt-6 max-w-xl px-4" data-rp-cta-card>
        <a href="{{ route('report.create') }}"
           class="group flex items-center gap-4 rounded-2xl bg-brand-600 p-4 text-white shadow-lg shadow-brand-600/25 transition hover:bg-brand-700 active:scale-[0.99]">
            <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-white/15 text-2xl font-black">＋</span>
            <span class="min-w-0 flex-1">
                <span class="block text-[15px] font-extrabold leading-snug">{{ __('reportcta.card_title') }}</span>
                <span class="mt-0.5 block text-[12px] leading-snug text-brand-100">{{ __('reportcta.card_body') }}</span>
            </span>
            <svg class="size-5 shrink-0 text-brand-200 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
    </div>

    {{-- Floating button (after the card scrolls away, or on other pages) --}}
    <a href="{{ route('report.create') }}" data-rp-cta-fab hidden
       class="rp-cta-fab fixed bottom-20 right-4 z-40 flex items-center gap-2 rounded-full bg-brand-600 py-3 pl-4 pr-5 text-[15px] font-extrabold text-white shadow-xl shadow-brand-600/30 transition-all active:scale-95 sm:bottom-6 sm:right-6">
        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14"/>
        </svg>
        {{ __('reportcta.fab') }}
    </a>
</div>

<script>
(function () {
    var wrap = document.getElementById('rp-report-cta');
    if (!wrap) return;

    var card = wrap.querySelector('[data-rp-cta-card]');
    var fab  = wrap.querySelector('[data-rp-cta-fab]');
    var lastY = window.scrollY;

    // On the report page itself the CTA is redundant.
    if (location.pathname.indexOf('/report') === 0) { wrap.remove(); return; }

    function setFab(on) {
        fab.hidden = !on;
        fab.classList.toggle('rp-cta-fab--in', on);
    }

    if (card) {
        var cardVisible = true;
        new IntersectionObserver(function (e) {
            cardVisible = e[0].isIntersecting;
            setFab(!cardVisible);
        }, { rootMargin: '-8px 0px 0px 0px' }).observe(card);
    } else {
        setFab(true);
    }

    // Hide while scrolling down (reading), reveal on any upward move.
    var ticking = false;
    window.addEventListener('scroll', function () {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function () {
            var y = window.scrollY;
            if (card && !cardVisible) {
                setFab(y < lastY || y < 120);
            }
            lastY = y;
            ticking = false;
        });
    }, { passive: true });
})();
</script>

<style>
.rp-cta-fab { transform: translateY(8px); opacity: 0; pointer-events: none; }
.rp-cta-fab--in { transform: translateY(0); opacity: 1; pointer-events: auto; }
@media (prefers-reduced-motion: reduce) { .rp-cta-fab { transition: none; } }
</style>
