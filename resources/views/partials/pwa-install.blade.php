{{-- "Install the app" prompt. Mobile first: a card under the hero on the home
     page, and a floating pill on other pages once the hero scrolls away. --}}
<div id="pwa-helper" class="pwa-helper" hidden>

    {{-- Hero card (home page only) --}}
    <div class="pwa-card mx-auto mt-4 max-w-xl px-4 sm:hidden" data-pwa-card>
        <div class="flex items-center gap-3 rounded-2xl border border-brand-100 bg-white p-3.5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <img src="{{ \App\Support\PwaSettings::icon() ? '/uploads/pwa/icon-192-' . \App\Support\PwaSettings::icon()['file'] . '.png' : '/icons/icon-192.png' }}" alt="" class="size-11 rounded-xl shadow-sm">
            <div class="min-w-0 flex-1">
                <p class="text-[13px] font-bold leading-snug text-slate-900 dark:text-zinc-100">{{ __('pwa.card_title') }}</p>
                <p class="mt-0.5 text-[11.5px] leading-snug text-slate-500 dark:text-zinc-400">{{ __('pwa.card_body') }}</p>
            </div>
            <div class="flex shrink-0 flex-col gap-1.5">
                <button type="button" data-pwa-install class="rounded-lg bg-brand-600 px-3.5 py-1.5 text-[12px] font-bold text-white shadow-sm active:bg-brand-700">
                    {{ __('pwa.install') }}
                </button>
                <button type="button" data-pwa-dismiss class="text-[11px] font-medium text-slate-400">{{ __('pwa.dismiss') }}</button>
            </div>
        </div>
    </div>

    {{-- Floating pill --}}
    <button type="button" data-pwa-pill hidden
            class="pwa-pill fixed bottom-4 left-1/2 z-40 flex -translate-x-1/2 items-center gap-2 rounded-full bg-brand-600 py-2 pl-3 pr-4 text-sm font-bold text-white shadow-lg shadow-brand-600/30 transition-transform active:scale-95">
        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4m-5 8h14"/>
        </svg>
        {{ __('pwa.pill') }}
    </button>
</div>

<script>
(function () {
    var KEY = 'pwa.install.dismissed.until';
    var SNOOZE = 7 * 24 * 3600 * 1000; // 7 days

    var helper = document.getElementById('pwa-helper');
    if (!helper) return;

    var card  = helper.querySelector('[data-pwa-card]');
    var pill  = helper.querySelector('[data-pwa-pill]');
    var btns  = helper.querySelectorAll('[data-pwa-install]');
    var close = helper.querySelector('[data-pwa-dismiss]');
    var deferredPrompt = null;

    var isStandalone = window.matchMedia('(display-mode: standalone)').matches
                       || window.navigator.standalone === true;
    var snoozed = false;
    try { snoozed = Date.now() < (parseInt(localStorage.getItem(KEY) || '0', 10) || 0); } catch (e) {}

    if (isStandalone || snoozed) return; // already installed, or "later" still active

    function show() {
        helper.hidden = false;
        // Pill appears once the hero card scrolls away (or on pages without it).
        if (card) {
            var io = new IntersectionObserver(function (entries) {
                pill.hidden = entries[0].isIntersecting;
            }, { rootMargin: '-8px 0px 0px 0px' });
            io.observe(card);
        } else {
            pill.hidden = false;
        }
    }

    function dismiss() {
        try { localStorage.setItem(KEY, String(Date.now() + SNOOZE)); } catch (e) {}
        helper.remove();
    }

    // Custom/manual-add instructions (iOS Safari, snoozed Android, desktop…)
    function manualHint() {
        var hint = 'Add to Home Screen';
        if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
            hint = 'Safari: Share (□↑) → "' + hint + '"';
        } else {
            hint = 'Menu (⋮) → "' + hint + '" / "Install app"';
        }
        btns.forEach(function (b) { b.textContent = '?'; });
        pill.insertAdjacentHTML('afterend',
            '<div class="pwa-hint fixed bottom-16 left-1/2 z-40 -translate-x-1/2 rounded-xl bg-zinc-900 px-4 py-2.5 text-[12px] font-medium text-white shadow-xl">' +
            hint + '</div>');
        setTimeout(function () {
            var h = document.querySelector('.pwa-hint');
            if (h) h.remove();
            btns.forEach(function (b) { b.textContent = b.dataset.label || b.textContent; });
        }, 4200);
    }

    btns.forEach(function (b) {
        b.dataset.label = b.textContent;
        b.addEventListener('click', function () {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function (choice) {
                    if (choice.outcome === 'accepted') { helper.remove(); }
                    deferredPrompt = null;
                });
            } else {
                manualHint();
            }
        });
    });
    if (close) { close.addEventListener('click', dismiss); }

    pill.addEventListener('click', function () {
        if (deferredPrompt) { deferredPrompt.prompt(); } else { manualHint(); }
    });

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        show();
    });

    // Android keeps the browser UI usable without the event for a while —
    // never leave the helper hidden forever.
    if (/Android/.test(navigator.userAgent) && !isStandalone) {
        show();
    }

    window.addEventListener('appinstalled', function () { helper.remove(); });
})();
</script>
