import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// Load the map code only on pages that have a map
if (document.getElementById('home-map')) {
    import('./home-map.js');
}