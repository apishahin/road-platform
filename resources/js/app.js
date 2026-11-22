import Alpine from 'alpinejs';
import { bangladeshMapOptions, isInBangladesh, restrictMapToBangladesh } from './bangladesh-map';

// Some focused map dialogs load Leaflet directly from a CDN, so make the shared
// Bangladesh-only map behaviour available to both Vite and inline page scripts.
window.RoadPlatformMap = { bangladeshMapOptions, isInBangladesh, restrictMapToBangladesh };
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
