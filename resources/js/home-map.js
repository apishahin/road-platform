import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const cfg = window.roadPlatform;
const t = cfg.i18n;

const listEl   = document.getElementById('issue-list');
const titleEl  = document.getElementById('list-title');
const statusEl = document.getElementById('locate-status');
const btn      = document.getElementById('locate-btn');

// ── Helpers ────────────────────────────────────────────
function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function pinIcon(color) {
    const span = `<span class="road-pin" style="--pin:${color}"></span>`; // color is a server-made hex value
    return L.divIcon({
        className: '',
        html: span,
        iconSize: [24, 24],
        iconAnchor: [12, 12],
        popupAnchor: [0, -12],
    });
}

function popupNode(issue) {
    const box = el('div');
    const a = el('a', 'rp-popup-title', issue.title);
    a.href = issue.url;
    box.append(a, el('div', 'rp-popup-meta', [issue.category, issue.status].filter(Boolean).join(' · ')));
    return box;
}

function badge(issue) {
    const b = el('span', 'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium');
    b.style.backgroundColor = issue.color + '1a';
    b.style.color = issue.color;
    const dot = el('span', 'size-1.5 rounded-full');
    dot.style.backgroundColor = issue.color;
    b.append(dot, document.createTextNode(issue.status));
    return b;
}

function card(issue) {
    const a = el('a', 'block rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-brand-500 hover:shadow');
    a.href = issue.url;

    const row = el('div', 'flex items-start justify-between gap-3');
    row.append(el('h3', 'font-semibold leading-snug text-slate-900', issue.title), badge(issue));

    const meta = [issue.category, issue.meters !== null ? issue.meters + ' m' : null]
        .filter(Boolean).join(' · ');

    a.append(row, el('p', 'mt-1 text-sm text-slate-600', meta));
    return a;
}

// ── Map ────────────────────────────────────────────────
const map = L.map('home-map').setView(cfg.center, cfg.zoom);

L.tileLayer(cfg.tileUrl, { attribution: cfg.attribution, maxZoom: 19 }).addTo(map);

cfg.issues.forEach((issue) => {
    L.marker([issue.lat, issue.lng], { icon: pinIcon(issue.color), title: issue.title })
        .bindPopup(popupNode(issue))
        .addTo(map);
});

if (cfg.issues.length > 0) {
    map.fitBounds(cfg.issues.map((i) => [i.lat, i.lng]), { padding: [40, 40], maxZoom: 15 });
}

// ── "Use my location" (optional, permission-based) ─────
let me = null;

function showList(items) {
    if (items.length === 0) {
        listEl.replaceChildren(el('p', 'rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-600', t.noneNearby));
        return;
    }
    listEl.replaceChildren(...items.map(card));
}

btn?.addEventListener('click', () => {
    if (!navigator.geolocation) {
        statusEl.textContent = t.noGeo;
        return;
    }

    statusEl.textContent = t.locating;
    btn.disabled = true;

    navigator.geolocation.getCurrentPosition(
        async (pos) => {
            const { latitude: lat, longitude: lng } = pos.coords;

            map.setView([lat, lng], 15);
            if (me) me.remove();
            me = L.circleMarker([lat, lng], {
                radius: 8, color: '#ffffff', weight: 3, fillColor: '#2563eb', fillOpacity: 1,
            }).addTo(map);

            try {
                const res = await fetch(`${cfg.nearbyUrl}?lat=${lat}&lng=${lng}&radius=2000`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) throw new Error('bad response');
                const { data } = await res.json();

                titleEl.textContent = t.nearYou;
                statusEl.textContent = '';
                showList(data);
            } catch {
                statusEl.textContent = t.loadFailed;
            }
            btn.disabled = false;
        },
        (err) => {
            statusEl.textContent = err.code === 1 ? t.denied : t.locFailed;
            btn.disabled = false;
        },
        { enableHighAccuracy: true, timeout: 10000 },
    );
});