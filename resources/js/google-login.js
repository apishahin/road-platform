import { initializeApp } from 'firebase/app';
import { getAuth, GoogleAuthProvider, signInWithPopup } from 'firebase/auth';

const root = document.getElementById('google-login');

if (root) {
    const d = root.dataset;
    const status = document.getElementById('gl-status');
    const btn = document.getElementById('gl-btn');

    const say = (key, bad = false) => {
        status.textContent = d[key] || d.textError || '';
        status.style.color = bad ? '#b91c1c' : '#334155';
    };

    // Firebase's codes are safe to turn into specific instructions. Never put
    // the raw exception text on the page: it can contain implementation detail.
    const messageKeyFor = (error) => {
        const code = error && typeof error.code === 'string' ? error.code : '';
        const keys = {
            'auth/popup-closed-by-user': 'textClosed',
            'auth/cancelled-popup-request': 'textClosed',
            'auth/too-many-requests': 'textTooMany',
            'auth/unauthorized-domain': 'textDomain',
            'auth/operation-not-allowed': 'textProvider',
            'auth/popup-blocked': 'textPopupBlocked',
            'auth/network-request-failed': 'textNetwork',
            'auth/account-exists-with-different-credential': 'textAccountExists',
            'auth/invalid-api-key': 'textConfig',
            'auth/invalid-auth-domain': 'textConfig',
            'auth/app-not-authorized': 'textConfig',
            'auth/invalid-credential': 'textConfig',
        };

        if (keys[code]) return keys[code];
        if (error && error.name === 'TypeError') return 'textNetwork';

        return 'textError';
    };

    let auth = null;
    if (!d.apiKey || !d.authDomain || !d.projectId) {
        say('textNotReady', true);
        btn.disabled = true;
    } else {
        try {
            auth = getAuth(initializeApp({ apiKey: d.apiKey, authDomain: d.authDomain, projectId: d.projectId }));
            auth.languageCode = document.documentElement.lang || 'en';
        } catch (e) {
            say(messageKeyFor(e), true);
            btn.disabled = true;
        }
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

            const serverKeys = {
                closed: 'textClosed',
                banned: 'textBanned',
                too_many: 'textTooMany',
                invalid: 'textVerificationFailed',
            };
            const key = serverKeys[j.err] || 'textError';
            say(key, key !== 'textClosed');
        } catch (e) {
            const key = messageKeyFor(e);
            say(key, key !== 'textClosed');
        }
        btn.disabled = false;
    });
}
