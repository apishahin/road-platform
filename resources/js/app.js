import Alpine from 'alpinejs';

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
