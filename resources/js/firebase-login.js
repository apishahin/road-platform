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

    const resetVerifier = () => {
        if (verifier) {
            try { verifier.clear(); } catch (e) { /* ignore */ }
        }
        verifier = null;
        box.innerHTML = '';
    };

    sendBtn.addEventListener('click', async () => {
        if (!auth) return;
        sendBtn.disabled = true;
        say('textSending');
        try {
            resetVerifier();
            verifier = new RecaptchaVerifier(auth, box, { size: 'invisible' });
            confirmation = await signInWithPhoneNumber(auth, d.phone, verifier);
            form.hidden = false;
            codeInput.focus();
            say('textSent');
            sendBtn.textContent = d.textResend;
            setTimeout(() => { sendBtn.disabled = false; }, 30000);
        } catch (e) {
            resetVerifier();
            say(e && e.code === 'auth/too-many-requests' ? 'textTooMany' : 'textError', true);
            sendBtn.disabled = false;
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