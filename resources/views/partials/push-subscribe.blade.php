{{-- Push-notification opt-in button (shown on the notifications page when FCM is configured). --}}
@if (\App\Support\PushSettings::on())
<div data-push-box class="mt-4 rounded-xl border border-slate-200 bg-white p-4">
    <div class="flex items-center gap-3">
        <span style="display:grid;place-items:center;width:36px;height:36px;border-radius:10px;background:#eff6ff;flex-shrink:0;">
            <svg style="width:18px;height:18px;color:#1d4ed8;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 18a2.5 2.5 0 0 1-5 0m9.2-2.6H5.3a1.2 1.2 0 0 1-1-1.9l1.4-1.8V8.4a6.3 6.3 0 0 1 12.6 0v3.1l1.4 1.8a1.2 1.2 0 0 1-1 1.9Z"/></svg>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-slate-900">{{ __('issues.push_title') }}</p>
            <p class="text-xs text-slate-500">{{ __('issues.push_sub') }}</p>
        </div>
        <button type="button" data-push-enable
                style="border-radius:10px;background:#16a34a;color:#fff;padding:8px 14px;font-size:13px;font-weight:700;">
            {{ __('issues.push_enable') }}
        </button>
    </div>
    <p data-push-status class="mt-2 hidden text-xs text-slate-500"></p>
</div>

<script>
(function () {
    var cfg = @json(\App\Support\PushSettings::webConfig());
    var btn = document.querySelector('[data-push-enable]');
    var status = document.querySelector('[data-push-status]');
    if (!btn || !cfg.apiKey) { return; }

    if ('Notification' in window && Notification.permission === 'granted') {
        btn.style.display = 'none';
        status.classList.remove('hidden');
        status.textContent = @json(__('issues.push_on_already'));
    }

    function load(src) {
        return new Promise(function (res, rej) {
            var s = document.createElement('script'); s.src = src; s.onload = res; s.onerror = rej;
            document.head.appendChild(s);
        });
    }

    btn.addEventListener('click', async function () {
        btn.disabled = true;
        try {
            await load('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');
            await load('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');

            firebase.initializeApp(cfg);
            var reg = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
            var perm = await Notification.requestPermission();
            if (perm !== 'granted') { throw new Error('denied'); }

            var token = await firebase.messaging().getToken({
                vapidKey: @json(\App\Support\PushSettings::all()['vapid_key']),
                serviceWorkerRegistration: reg,
            });

            var r = await fetch('{{ route('push.token') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: JSON.stringify({ token: token }),
            });
            if (!r.ok) { throw new Error('save failed'); }

            btn.style.display = 'none';
            status.classList.remove('hidden');
            status.textContent = @json(__('issues.push_done'));
        } catch (e) {
            btn.disabled = false;
            status.classList.remove('hidden');
            status.textContent = @json(__('issues.push_failed'));
        }
    });
})();
</script>
@endif
