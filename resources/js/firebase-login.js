import { initializeApp } from 'firebase/app';
import { getAuth, RecaptchaVerifier, signInWithPhoneNumber } from 'firebase/auth';

const root = document.getElementById('firebase-login');

if (root) {
    const d = root.dataset;
    const status = document.getElementById('fb-status');
    const sendBtn = document.getElementById('fb-send');
    const form = document.getElementById('fb-code-form');
    const codeInput = document.getElementById('fb-code');
    const confirmBtn = document.getElementById('fb-confirm');
    const box = document.getElementById('fb-recaptcha');

    const say = (key, bad = false) => {
        status.textContent = d[key] || '';
        status.style.color = bad ? '#b91c1c' : '#334155';
    };

    let auth = null;
    try {
        auth = getAuth(initializeApp({ apiKey: d.apiKey, authDomain: d.authDomain, projectId: d.projectId }));
        auth.languageCode = document.documentElement.lang || 'en';
    } catch (e) {
        say('textError', true);
        sendBtn.disabled = true;
    }

    let verifier = null;
    let confirmation = null;
    // Firebase still performs its own abuse checks, but the Admin-selected
    // login resend interval keeps this button consistent with gateway OTP.
    const resendWaitMs = Math.max(15, Number.parseInt(d.resendWaitSeconds || '60', 10) || 60) * 1000;

    const resetVerifier = () => {
        if (verifier) {
            try { verifier.clear(); } catch (e) { /* ignore */ }
        }
        verifier = null;
        box.innerHTML = '';
    };

    const startResendCountdown = () => {
        let seconds = Math.ceil(resendWaitMs / 1000);
        const update = () => {
            if (seconds <= 0) {
                sendBtn.disabled = false;
                sendBtn.hidden = false;
                say('textSent');
                return true;
            }
            sendBtn.disabled = true;
            sendBtn.hidden = true;
            const wait = (d.textResendWait || '').replace('__seconds__', String(seconds));
            status.textContent = [d.textSent || '', wait].filter(Boolean).join(' ');
            status.style.color = '#334155';
            seconds -= 1;
            return false;
        };
        if (!update()) {
            const timer = window.setInterval(() => {
                if (update()) window.clearInterval(timer);
            }, 1000);
        }
    };

    // When the phone is editable, wrap it in an input row before the send button.
    let phoneInput = null;
    if (d.phoneEditable === '1') {
        phoneInput = document.createElement('input');
        phoneInput.type = 'tel';
        phoneInput.inputMode = 'tel';
        phoneInput.autocomplete = 'tel';
        phoneInput.maxLength = 30;
        phoneInput.placeholder = '01XXXXXXXXX';
        phoneInput.required = true;
        phoneInput.className = 'block w-full rounded-lg border border-slate-300 px-3 py-2 text-base';
        sendBtn.parentNode.insertBefore(phoneInput, sendBtn);
    }

    sendBtn.addEventListener('click', async () => {
        if (!auth) return;

        let phone = d.phone;
        if (phoneInput) {
            const raw = phoneInput.value.trim().replace(/[\s-]/g, '');
            let norm = raw.replace(/[^\d+0189]/g, '');
            if (/^01\d{9}$/.test(norm)) norm = '+88' + norm;
            else if (/^8801\d{9}$/.test(norm)) norm = '+' + norm;
            if (!/^\+8801\d{9}$/.test(norm)) {
                say('textError', true);
                return;
            }
            phone = norm;
        }

        sendBtn.disabled = true;
        say('textSending');
        try {
            resetVerifier();
            verifier = new RecaptchaVerifier(auth, box, { size: 'invisible' });
            confirmation = await signInWithPhoneNumber(auth, phone, verifier);
            form.hidden = false;
            codeInput.focus();
            say('textSent');
            sendBtn.textContent = d.textResend;
            startResendCountdown();
        } catch (e) {
            resetVerifier();
            say(e && e.code === 'auth/too-many-requests' ? 'textTooMany' : 'textError', true);
            sendBtn.disabled = false;
            sendBtn.hidden = false;
        }
    });

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        if (!confirmation) return;
        const code = codeInput.value.replace(/\D/g, '');
        if (code.length !== 6) {
            say('textWrong', true);
            return;
        }
        confirmBtn.disabled = true;
        say('textChecking');
        try {
            const cred = await confirmation.confirm(code);
            const idToken = await cred.user.getIdToken();
            const res = await fetch(d.verifyUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': d.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ id_token: idToken }),
            });
            const j = await res.json().catch(() => ({}));
            if (res.ok && j.ok) {
                window.location.href = j.redirect || '/';
                return;
            }
            say('textWrong', true);
        } catch (e) {
            say('textWrong', true);
        }
        confirmBtn.disabled = false;
    });
}