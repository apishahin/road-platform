import Alpine from 'alpinejs';
import {
    applyMapDisplay,
    bangladeshGeometryReady,
    isInBangladesh,
    mapDisplayMode,
    restrictMapToBangladesh,
} from './bangladesh-map';

// Focused map dialogs use Leaflet from a CDN, so expose the same map policy to
// their inline scripts as well as the Vite-powered homepage map. The mode is
// injected by the shared layout, so every application map gets one policy.
const mapDisplay = mapDisplayMode(window.roadPlatformMapDisplay);
window.RoadPlatformMap = {
    bangladeshGeometryReady,
    isInBangladesh,
    restrictMapToBangladesh,
    mapDisplay,
    applyMapDisplay: (map, L) => applyMapDisplay(map, L, mapDisplay),
};
window.dispatchEvent(new Event('road-platform-map-tools-ready'));

window.Alpine = Alpine;
Alpine.start();

// Load the map code only on pages that have a map
if (document.getElementById('home-map')) {
    import('./home-map.js');
}

// Firebase phone login code only on the Firebase verify page
if (document.getElementById('firebase-login')) {
    import('./firebase-login.js');
}

if (document.getElementById('google-login')) {
    import('./google-login.js');
}
