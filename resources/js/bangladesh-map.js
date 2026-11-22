// Shared Leaflet behaviour for every in-application map.
// The detailed country geometry is kept as a same-origin GeoJSON file so the
// visual mask and the server-side validation use the exact same outline.
const BOUNDARY_URL = '/maps/bangladesh-boundary.geojson';
const COUNTRY_BOUNDS = [[20.60, 88.00], [26.65, 92.72]];
const WORLD_RING = [[-85, -180], [-85, 180], [85, 180], [85, -180], [-85, -180]];
const MAP_DISPLAYS = ['bangladesh', 'world'];

let boundaryPromise;
let boundary;

function validCoordinate(value) {
    return Array.isArray(value) && value.length >= 2
        && Number.isFinite(Number(value[0])) && Number.isFinite(Number(value[1]));
}

function toLatLngRing(ring) {
    if (!Array.isArray(ring)) return [];

    return ring.filter(validCoordinate).map(([lng, lat]) => [Number(lat), Number(lng)]);
}

function normaliseGeometry(geoJson) {
    const geometry = geoJson?.features?.[0]?.geometry;
    const coordinates = geometry?.coordinates;
    const sourcePolygons = geometry?.type === 'Polygon'
        ? [coordinates]
        : (geometry?.type === 'MultiPolygon' ? coordinates : []);

    if (!Array.isArray(sourcePolygons)) return null;

    const polygons = sourcePolygons.map((polygon) => {
        if (!Array.isArray(polygon)) return [];
        return polygon.map(toLatLngRing).filter((ring) => ring.length >= 3);
    }).filter((polygon) => polygon.length > 0);

    if (polygons.length === 0) return null;

    return { geoJson, polygons };
}

async function loadBoundary() {
    if (!boundaryPromise) {
        boundaryPromise = fetch(BOUNDARY_URL, { headers: { Accept: 'application/geo+json, application/json' } })
            .then((response) => {
                if (!response.ok) throw new Error('bangladesh-boundary-unavailable');
                return response.json();
            })
            .then((geoJson) => {
                boundary = normaliseGeometry(geoJson);
                if (!boundary) throw new Error('bangladesh-boundary-invalid');
                return boundary;
            });
    }

    return boundaryPromise;
}

function isWithinBoundingBox(lat, lng) {
    return lat >= COUNTRY_BOUNDS[0][0] && lat <= COUNTRY_BOUNDS[1][0]
        && lng >= COUNTRY_BOUNDS[0][1] && lng <= COUNTRY_BOUNDS[1][1];
}

function pointOnSegment(lat, lng, [latA, lngA], [latB, lngB]) {
    const crossProduct = ((lng - lngA) * (latB - latA)) - ((lat - latA) * (lngB - lngA));
    const epsilon = 0.000000001;

    if (Math.abs(crossProduct) > epsilon) return false;

    return lat >= Math.min(latA, latB) - epsilon && lat <= Math.max(latA, latB) + epsilon
        && lng >= Math.min(lngA, lngB) - epsilon && lng <= Math.max(lngA, lngB) + epsilon;
}

function inRing(lat, lng, ring) {
    let inside = false;

    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const current = ring[i];
        const previous = ring[j];

        if (pointOnSegment(lat, lng, current, previous)) return true;

        const [latA, lngA] = current;
        const [latB, lngB] = previous;
        const intersects = ((latA > lat) !== (latB > lat))
            && (lng < ((lngB - lngA) * (lat - latA) / (latB - latA)) + lngA);

        if (intersects) inside = !inside;
    }

    return inside;
}

function addPane(map, name, zIndex) {
    if (!map.getPane(name)) {
        map.createPane(name);
    }

    const pane = map.getPane(name);
    pane.style.zIndex = String(zIndex);
    pane.style.pointerEvents = 'none';
}

function addFailClosedMask(map, L) {
    addPane(map, 'bangladesh-mask', 350);

    // Do not fall back to a world map if the local boundary file is unavailable.
    L.polygon([WORLD_RING], {
        pane: 'bangladesh-mask',
        stroke: false,
        fill: true,
        fillColor: '#f7f4ec',
        fillOpacity: 1,
        interactive: false,
    }).addTo(map);
}

/** Wait until the detailed Bangladesh outline is available. */
export function bangladeshGeometryReady() {
    return loadBoundary();
}

/** True only after the detailed country outline has confirmed the coordinate. */
export function isInBangladesh(latitude, longitude) {
    const lat = Number(latitude);
    const lng = Number(longitude);

    if (!Number.isFinite(lat) || !Number.isFinite(lng) || !isWithinBoundingBox(lat, lng) || !boundary) {
        return false;
    }

    return boundary.polygons.some((polygon) => {
        const [outer, ...holes] = polygon;
        return inRing(lat, lng, outer) && !holes.some((hole) => inRing(lat, lng, hole));
    });
}

export function bangladeshBounds(L) {
    return L.latLngBounds(COUNTRY_BOUNDS);
}

function geometryBounds(L, geometry) {
    return L.latLngBounds(geometry.polygons.flatMap((polygon) => polygon.flat()));
}

/** Fit the complete detailed silhouette, including the northern and southern tips. */
export function fitBangladeshMap(map, L, options = {}) {
    const defaults = { padding: [20, 20], maxZoom: 8 };

    return bangladeshGeometryReady().then((geometry) => {
        const bounds = geometryBounds(L, geometry);
        if (bounds.isValid()) {
            map.fitBounds(bounds, { ...defaults, ...options });
        }
        return bounds;
    }).catch(() => {
        const bounds = bangladeshBounds(L);
        map.fitBounds(bounds, { ...defaults, ...options });
        return bounds;
    });
}

export function mapDisplayMode(value) {
    return MAP_DISPLAYS.includes(value) ? value : 'bangladesh';
}

/**
 * Locks movement to Bangladesh and covers all external OSM tiles with a neutral
 * surface. Markers and popups remain above the mask and work normally.
 */
export function restrictMapToBangladesh(map, L) {
    const bounds = bangladeshBounds(L);
    map.setMaxBounds(bounds);
    map.options.maxBoundsViscosity = 1;
    map.getContainer().style.backgroundColor = '#f7f4ec';

    return bangladeshGeometryReady().then((geometry) => {
        addPane(map, 'bangladesh-mask', 350);
        addPane(map, 'bangladesh-border', 401);

        // With the even-odd rule, the world is opaque while every Bangladesh
        // polygon is a transparent hole. Additional interior rings stay masked.
        const maskRings = [WORLD_RING, ...geometry.polygons.flatMap((polygon) => polygon)];
        L.polygon(maskRings, {
            pane: 'bangladesh-mask',
            stroke: false,
            fill: true,
            fillColor: '#f7f4ec',
            fillOpacity: 1,
            fillRule: 'evenodd',
            interactive: false,
        }).addTo(map);

        L.geoJSON(geometry.geoJson, {
            pane: 'bangladesh-border',
            interactive: false,
            style: {
                color: '#00875a',
                weight: 1.5,
                opacity: 0.9,
                fill: false,
            },
        }).addTo(map);

        return bounds;
    }).catch(() => {
        addFailClosedMask(map, L);
        return bounds;
    });
}

/**
 * Apply the administrator's shared visual map policy. World mode deliberately
 * changes only browsing/panning: callers must still use isInBangladesh() for
 * point selection, while the server independently validates every request.
 */
export function applyMapDisplay(map, L, display) {
    if (mapDisplayMode(display) !== 'world') {
        return restrictMapToBangladesh(map, L);
    }

    map.setMaxBounds(null);
    map.options.maxBoundsViscosity = 0;
    map.getContainer().style.backgroundColor = '';

    // Homepage markers and client-side report validation need the shared
    // geometry even though no visual mask is drawn in world-browsing mode.
    return bangladeshGeometryReady().catch(() => null);
}
