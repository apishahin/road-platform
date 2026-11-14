<?php

return [
    'map' => [
        // OSM's own tile server is fine for local development only.
        // In production use a provider (MapTiler, Stadia, Carto...) and set it in .env.
        'tile_url'    => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('MAP_ATTRIBUTION', '&copy; OpenStreetMap contributors'),
        'center'      => [23.8103, 90.4125],   // Dhaka
        'zoom'        => 12,
        // default homepage view = the whole of Bangladesh in frame
        'country_bounds' => [[20.5, 87.9], [26.8, 92.8]],
        'max_markers' => 500,
    ],
];