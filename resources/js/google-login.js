import { initializeApp } from 'firebase/app';
import { getAuth, GoogleAuthProvider, signInWithPopup } from 'firebase/auth';

const root = document.getElementById('google-login');

if (root) {
    const d = root.dataset;
    const status = document.getElementById('gl-status');
    const btn = document.getElementById('gl-btn');

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
        btn.disabled = true;
    }

    btn.addEventListener('click', async () => {
        if (!auth) return;
        btn.disabled = true;
        say('textSigning');
        try {
            const provider = new GoogleAuthProvider();
            provider.addScope('email');
            provider.addScope('profile');
            const cred = await signInWithPopup(auth, provider);
            const idToken = await cred.user.getIdToken();
            say('textChecking');

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
            if (res.ok && j.ok && j.next) {
                window.location.href = j.next;
                return;
            }
            const err = j.err || 'error';
            say(err === 'closed' ? 'textClosed' : err === 'banned' ? 'textBanned' : err === 'too_many' ? 'textTooMany' : 'textError', true);
        } catch (e) {
            if (e && e.code === 'auth/popup-closed-by-user') {
                say('textError', false);
            } else {
                say('textError', true);
            }
        }
        btn.disabled = false;
    });
}
