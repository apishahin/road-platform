// Keeps every Leaflet map inside Bangladesh and masks all neighbouring map tiles.
// Coordinates are [latitude, longitude]; the outline is intentionally used only
// as an on-screen map boundary, not as a legal or administrative boundary.
const fallbackBoundary = [
    [22.041239, 92.672721], [21.324048, 92.652257], [21.475485, 92.303234],
    [20.670883, 92.368554], [21.192195, 92.082886], [21.701570, 92.025215],
    [22.182936, 91.834891], [22.765019, 91.417087], [22.805017, 90.496006],
    [22.392794, 90.586957], [21.836368, 90.272971], [22.039146, 89.847467],
    [21.857116, 89.702050], [21.966179, 89.418863], [22.055708, 89.031961],
    [22.879146, 88.876312], [23.631142, 88.529770], [24.233715, 88.699940],
    [24.501657, 88.084422], [24.866079, 88.306373], [25.238692, 88.931554],
    [25.768066, 88.209789], [26.446526, 88.563049], [26.014407, 89.355094],
    [25.965082, 89.832481], [25.269750, 89.920693], [25.132601, 90.872211],
    [25.147432, 91.799596], [24.976693, 92.376202], [24.130414, 91.915093],
    [24.072639, 91.467730], [23.503527, 91.158963], [22.985264, 91.706475],
    [23.624346, 91.869928], [23.627499, 92.146035], [22.041239, 92.672721],
];

const fallbackBounds = [[20.65, 88.05], [26.47, 92.70]];
const worldMask = [[-85, -180], [-85, 180], [85, 180], [85, -180], [-85, -180]];

function config() {
    const supplied = window.roadPlatformMapConfig || {};
    const boundary = Array.isArray(supplied.boundary) && supplied.boundary.length >= 4
        ? supplied.boundary
        : fallbackBoundary;
    const bounds = Array.isArray(supplied.bounds) && supplied.bounds.length === 2
        ? supplied.bounds
        : fallbackBounds;

    return { boundary, bounds };
}

/** Return true when a latitude/longitude sits in the Bangladesh map outline. */
export function isInBangladesh(lat, lng) {
    const y = Number(lat);
    const x = Number(lng);
    if (!Number.isFinite(y) || !Number.isFinite(x)) return false;

    const ring = config().boundary;
    let inside = false;

    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const [yi, xi] = ring[i];
        const [yj, xj] = ring[j];
        const intersects = ((yi > y) !== (yj > y))
            && (x < ((xj - xi) * (y - yi) / (yj - yi)) + xi);

        if (intersects) inside = !inside;
    }

    return inside;
}

export function bangladeshBounds(L) {
    return L.latLngBounds(config().bounds);
}

/** Options to use while creating an interactive Leaflet map. */
export function bangladeshMapOptions(L, options = {}) {
    return {
        ...options,
        maxBounds: bangladeshBounds(L),
        maxBoundsViscosity: 1,
        worldCopyJump: false,
    };
}

/**
 * Prevents panning beyond the country and covers every tile outside the outline.
 * The opaque mask means neighbouring country labels, roads and terrain never show.
 */
export function restrictMapToBangladesh(map, L) {
    const { boundary } = config();
    const bounds = bangladeshBounds(L);

    map.setMaxBounds(bounds);
    map.options.maxBoundsViscosity = 1;

    L.polygon([worldMask, boundary], {
        pane: 'overlayPane',
        stroke: false,
        fill: true,
        fillColor: '#f8fafc',
        fillOpacity: 1,
        fillRule: 'evenodd',
        interactive: false,
        bubblingMouseEvents: false,
    }).addTo(map);

    L.polyline(boundary, {
        pane: 'overlayPane',
        color: '#047857',
        weight: 1.5,
        opacity: 0.8,
        interactive: false,
        bubblingMouseEvents: false,
    }).addTo(map);

    return bounds;
}
