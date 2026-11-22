import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const cfg = window.roadPlatform;
const t = cfg.i18n;

const listEl   = document.getElementById('issue-list');
const titleEl  = document.getElementById('list-title');
const statusEl = document.getElementById('locate-status');
const btn      = document.getElementById('locate-btn');
const filterEl = document.getElementById('status-filter');

// Helpers
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
    box.append(a, el('div', 'rp-popup-meta', [issue.category, issue.status].filter(Boolean).join(' \u00b7 ')));
    return box;
}

function badge(issue) {
    const b = el('span', 'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium');
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

    const meta = [issue.category, issue.meters != null ? issue.meters + ' m' : null]
        .filter(Boolean).join(' \u00b7 ');

    a.append(row, el('p', 'mt-1 text-sm text-slate-600', meta));
    if (issue.notified && t.notified) {
        const note = el('p', 'mt-2');
        const tag = el('span', 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', t.notified);
        tag.style.backgroundColor = '#e0f2fe';
        tag.style.color = '#0369a1';
        note.append(tag);
        a.append(note);
    }
    return a;
}

// Bangladesh district map. It is a local, full-country map rather than a
// cropped OpenStreetMap tile layer, so no neighbouring country is ever shown.
let homeView = Array.isArray(cfg.countryBounds) ? L.latLngBounds(cfg.countryBounds) : null;
const map = L.map('home-map', {
    zoomSnap: 0.25,
    maxBounds: homeView || undefined,
    maxBoundsViscosity: 1,
    attributionControl: false,
}).setView(cfg.center, cfg.zoom);
map.getContainer().style.background = '#f7f4ec';

// Default view: the entire Bangladesh silhouette in frame.
function countryView() {
    if (! homeView) { return; }
    map.fitBounds(homeView, { padding: [16, 16] });
}
countryView();

const markers = L.layerGroup().addTo(map);

function districtLabel(feature) {
    const name = feature.properties?.shapeName || '';
    return cfg.districtLabels?.[name] || name;
}

async function loadDistrictMap() {
    try {
        const response = await fetch(cfg.districtMapUrl, { headers: { Accept: 'application/geo+json, application/json' } });
        if (!response.ok) throw new Error('district-map-unavailable');

        const geojson = await response.json();
        const districts = L.geoJSON(geojson, {
            style: {
                color: '#ffffff',
                weight: 1,
                opacity: 0.95,
                fillColor: '#008b68',
                fillOpacity: 1,
            },
            onEachFeature(feature, layer) {
                const label = districtLabel(feature);
                if (label) {
                    layer.bindTooltip(label, {
                        permanent: true,
                        direction: 'center',
                        className: 'bd-district-label',
                        opacity: 1,
                        interactive: false,
                    });
                }
            },
        }).addTo(map);

        homeView = districts.getBounds();
        map.setMaxBounds(homeView.pad(0.03));
        countryView();
        L.control.attribution({ position: 'bottomright', prefix: false })
            .addAttribution(cfg.districtAttribution)
            .addTo(map);
    } catch {
        // The country outline is a same-origin static asset. Keep the map neutral
        // instead of falling back to a world map if the asset is temporarily unavailable.
    }
}

loadDistrictMap();

let filter = 'all';
let nearby = null;   // null = showing the recent list
let me = null;
const recentNodes = Array.from(listEl.children);

function inGroup(i) {
    return filter === 'all' || i.group === filter;
}

function drawMarkers() {
    const items = cfg.issues.filter(inGroup);
    markers.clearLayers();
    items.forEach((issue) => {
        L.marker([issue.lat, issue.lng], { icon: pinIcon(issue.color), title: issue.title, zIndexOffset: 1000 })
            .bindPopup(popupNode(issue))
            .addTo(markers);
    });
    if (nearby === null) {
        // "সব" / All keeps the whole country in frame; a chosen status zooms to its pins.
        if (homeView && filter === 'all') {
            countryView();
        } else if (items.length > 0) {
            map.fitBounds(items.map((i) => [i.lat, i.lng]), { padding: [40, 40], maxZoom: 15 });
        }
    }
}

function showList(items) {
    if (items.length === 0) {
        const msg = filter === 'all' ? t.noneNearby : (filterEl ? filterEl.dataset.none : t.noneNearby);
        listEl.replaceChildren(el('p', 'rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-600', msg));
        return;
    }
    listEl.replaceChildren(...items.map(card));
}

function render() {
    drawMarkers();
    if (nearby === null) {
        if (filter === 'all') {
            listEl.replaceChildren(...recentNodes);
            return;
        }
        showList(cfg.issues.filter(inGroup).slice(0, 8));
        return;
    }
    showList(nearby.filter(inGroup));
}

render();

// Status filter chips
filterEl?.addEventListener('click', (e) => {
    const chip = e.target.closest('[data-filter]');
    if (!chip) return;
    filter = chip.dataset.filter;
    filterEl.querySelectorAll('[data-filter]').forEach((c) => {
        const on = c === chip;
        c.setAttribute('aria-pressed', on ? 'true' : 'false');
        c.style.backgroundColor = on ? '#0f172a' : '';
        c.style.color = on ? '#ffffff' : '';
        c.style.borderColor = on ? '#0f172a' : '';
    });
    render();
});

// "Use my location" (optional, permission-based)
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
                nearby = data;
                render();
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