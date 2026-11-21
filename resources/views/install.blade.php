@extends('layouts.app')

@section('title', __('pwa.install_primary'))
@section('description', __('pwa.install_page_body'))
@section('canonical', $installUrl)

@section('content')
@php
    $appName = \App\Support\AppearanceSettings::siteName();
    $appIcon = \App\Support\PwaSettings::icon()
        ? '/uploads/pwa/icon-192-' . \App\Support\PwaSettings::icon()['file'] . '.png'
        : '/icons/icon-192.png';
@endphp

<style>
/* This page intentionally uses its own small style layer so it remains polished
   even before a deployment has rebuilt the Tailwind bundle. */
body:has([data-install-page]) #pwa-helper { display: none !important; }
.install-page { max-width: 1060px; margin: 0 auto; padding: 36px 16px 64px; }
.install-hero { overflow: hidden; position: relative; border-radius: 28px; padding: clamp(26px, 5vw, 56px); color: #fff; background: radial-gradient(circle at 86% 18%, rgba(16,185,129,.35), transparent 28%), linear-gradient(135deg,#062c26,#064e3b 55%,#0f766e); box-shadow: 0 22px 55px rgba(6,78,59,.22); }
.install-hero::after { content: ''; position: absolute; width: 290px; height: 290px; right: -118px; bottom: -178px; border: 42px solid rgba(255,255,255,.07); border-radius: 999px; }
.install-kicker { position: relative; z-index: 1; margin: 0 0 12px; color: #a7f3d0; font-size: 12px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
.install-title { position: relative; z-index: 1; max-width: 630px; margin: 0; font-size: clamp(29px, 5vw, 48px); line-height: 1.12; letter-spacing: -.03em; }
.install-lead { position: relative; z-index: 1; max-width: 630px; margin: 18px 0 0; color: #d1fae5; font-size: 16px; line-height: 1.7; }
.install-action-row { position: relative; z-index: 1; display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 26px; }
.install-primary { border: 0; border-radius: 14px; padding: 13px 20px; background: #fff; color: #065f46; font: inherit; font-weight: 800; cursor: pointer; box-shadow: 0 8px 22px rgba(0,0,0,.17); }
.install-primary:hover { background: #ecfdf5; }
.install-primary:focus-visible,.install-secondary:focus-visible { outline: 3px solid #fef08a; outline-offset: 3px; }
.install-secondary { border: 1px solid rgba(255,255,255,.35); border-radius: 14px; padding: 12px 17px; background: rgba(255,255,255,.08); color: #fff; font: inherit; font-weight: 750; cursor: pointer; }
.install-secondary:hover { background: rgba(255,255,255,.16); }
.install-status { flex-basis: 100%; min-height: 20px; color: #d1fae5; font-size: 13px; }
.install-grid { display: grid; grid-template-columns: minmax(0, 1.22fr) minmax(280px, .78fr); gap: 22px; margin-top: 24px; }
.install-panel { border: 1px solid #dbe7e2; border-radius: 22px; background: #fff; padding: clamp(20px, 3vw, 30px); box-shadow: 0 10px 25px rgba(15,23,42,.045); }
.install-brand { display: flex; align-items: center; gap: 14px; }
.install-brand img { width: 64px; height: 64px; border-radius: 18px; box-shadow: 0 8px 18px rgba(15,23,42,.14); }
.install-brand strong { display: block; color: #0f172a; font-size: 19px; }
.install-brand span { display: block; margin-top: 3px; color: #64748b; font-size: 13px; }
.install-device-list { display: grid; gap: 14px; margin: 25px 0 0; }
.install-device { display: grid; grid-template-columns: 36px 1fr; gap: 12px; align-items: start; }
.install-device-icon { display: grid; width: 36px; height: 36px; place-items: center; border-radius: 11px; background: #ecfdf5; color: #047857; font-size: 18px; font-weight: 800; }
.install-device h2 { margin: 1px 0 4px; color: #0f172a; font-size: 15px; }
.install-device p { margin: 0; color: #475569; font-size: 14px; line-height: 1.55; }
.install-qr-panel { display: flex; flex-direction: column; align-items: center; text-align: center; }
.install-qr-panel h2 { margin: 18px 0 8px; color: #0f172a; font-size: 18px; }
.install-qr-panel p { max-width: 310px; margin: 0; color: #475569; font-size: 14px; line-height: 1.58; }
.install-qr { width: min(100%, 220px); margin-top: 20px; border: 10px solid #fff; border-radius: 16px; background: #fff; box-shadow: 0 8px 22px rgba(15,23,42,.12); image-rendering: pixelated; }
.install-link { width: 100%; margin-top: 18px; overflow: auto; border: 1px solid #dbe7e2; border-radius: 10px; padding: 10px; background: #f8fafc; color: #334155; font-family: ui-monospace,SFMono-Regular,Menlo,monospace; font-size: 12px; white-space: nowrap; }
.install-copy { margin-top: 12px; border: 1px solid #94a3b8; border-radius: 10px; padding: 10px 14px; background: #fff; color: #334155; font: inherit; font-size: 13px; font-weight: 800; cursor: pointer; }
.install-copy:hover { background: #f8fafc; }
@media (max-width: 760px) { .install-page { padding-top: 22px; } .install-grid { grid-template-columns: 1fr; } .install-hero { border-radius: 22px; } }
</style>

<main class="install-page" data-install-page data-install-url="{{ $installUrl }}">
    <section class="install-hero" aria-labelledby="install-title">
        <p class="install-kicker">{{ __('pwa.install_page_eyebrow') }}</p>
        <h1 id="install-title" class="install-title">{{ __('pwa.install_page_title', ['name' => $appName]) }}</h1>
        <p class="install-lead">{{ __('pwa.install_page_body') }}</p>
        <div class="install-action-row">
            <button type="button" class="install-primary" data-install-primary>{{ __('pwa.install_primary') }}</button>
            <button type="button" class="install-secondary" data-install-share>{{ __('pwa.install_share') }}</button>
            <p class="install-status" aria-live="polite" data-install-status>{{ __('pwa.install_status_manual') }}</p>
        </div>
    </section>

    <div class="install-grid">
        <section class="install-panel" aria-label="{{ __('pwa.install_primary') }}">
            <div class="install-brand">
                <img src="{{ $appIcon }}" alt="" width="64" height="64">
                <div>
                    <strong>{{ $appName }}</strong>
                    <span>{{ __('pwa.install_ready') }}</span>
                </div>
            </div>

            <div class="install-device-list">
                <article class="install-device">
                    <span class="install-device-icon" aria-hidden="true">▣</span>
                    <div>
                        <h2>{{ __('pwa.install_android_title') }}</h2>
                        <p>{{ __('pwa.install_android_body') }}</p>
                    </div>
                </article>
                <article class="install-device">
                    <span class="install-device-icon" aria-hidden="true">□↑</span>
                    <div>
                        <h2>{{ __('pwa.install_ios_title') }}</h2>
                        <p>{{ __('pwa.install_ios_body') }}</p>
                    </div>
                </article>
                <article class="install-device">
                    <span class="install-device-icon" aria-hidden="true">⌘</span>
                    <div>
                        <h2>{{ __('pwa.install_desktop_title') }}</h2>
                        <p>{{ __('pwa.install_desktop_body') }}</p>
                    </div>
                </article>
            </div>
        </section>

        <aside class="install-panel install-qr-panel" aria-labelledby="install-share-title">
            <img class="install-qr" src="{{ route('pwa.install.qr') }}" alt="{{ __('pwa.install_qr_alt') }}" width="220" height="220">
            <h2 id="install-share-title">{{ __('pwa.install_share_title') }}</h2>
            <p>{{ __('pwa.install_share_body') }}</p>
            <code class="install-link" data-install-link>{{ $installUrl }}</code>
            <button type="button" class="install-copy" data-install-copy>{{ __('pwa.install_copy') }}</button>
        </aside>
    </div>
</main>
@endsection

@push('scripts')
<script>
(() => {
    const page = document.querySelector('[data-install-page]');
    if (!page) return;

    const installUrl = page.dataset.installUrl;
    const install = page.querySelector('[data-install-primary]');
    const share = page.querySelector('[data-install-share]');
    const copy = page.querySelector('[data-install-copy]');
    const status = page.querySelector('[data-install-status]');
    const defaultInstall = install.textContent;
    const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    let promptEvent = null;

    const setStatus = (text) => { status.textContent = text; };
    const copyLink = async () => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(installUrl);
            } else {
                const field = document.createElement('textarea');
                field.value = installUrl;
                field.setAttribute('readonly', '');
                field.style.cssText = 'position:fixed;opacity:0;pointer-events:none;';
                document.body.appendChild(field);
                field.select();
                document.execCommand('copy');
                field.remove();
            }
            setStatus(@json(__('pwa.install_copied')));
        } catch (error) {
            setStatus(installUrl);
        }
    };

    if (standalone) {
        install.disabled = true;
        install.textContent = @json(__('pwa.install_open'));
        install.style.opacity = '.7';
        install.style.cursor = 'default';
        setStatus(@json(__('pwa.install_open')));
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        promptEvent = event;
        if (!standalone) {
            setStatus(@json(__('pwa.install_ready')));
        }
    });

    install.addEventListener('click', async () => {
        if (standalone) return;
        if (!promptEvent) {
            setStatus(@json(__('pwa.install_status_manual')));
            return;
        }

        promptEvent.prompt();
        const choice = await promptEvent.userChoice;
        promptEvent = null;
        if (choice.outcome === 'accepted') {
            setStatus(@json(__('pwa.install_ready')));
        } else {
            setStatus(@json(__('pwa.install_status_declined')));
            install.textContent = defaultInstall;
        }
    });

    copy.addEventListener('click', copyLink);
    share.addEventListener('click', async () => {
        if (navigator.share) {
            try {
                await navigator.share({ title: @json($appName), text: @json(__('pwa.install_page_title', ['name' => $appName])), url: installUrl });
                return;
            } catch (error) {
                if (error && error.name === 'AbortError') return;
            }
        }
        await copyLink();
    });
})();
</script>
@endpush
