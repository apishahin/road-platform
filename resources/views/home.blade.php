@extends('layouts.app')

@section('title', __('Find and report road problems'))

@section('content')
@if (\App\Support\SiteSections::modern('home'))
    @include('home.modern')
@else
    @include('home.classic')
@endif
@endsection

@php
    $mapDisplay = \App\Support\AppearanceSettings::mapDisplay();
    $countryBounds = config('roadplatform.map.country_bounds');
    // The dynamic Leaflet bundle reads this before its detailed outline is
    // ready. Start it country-wide, not briefly at the default Dhaka zoom.
    $homeMapCenter = $mapDisplay === 'bangladesh'
        ? [($countryBounds[0][0] + $countryBounds[1][0]) / 2, ($countryBounds[0][1] + $countryBounds[1][1]) / 2]
        : config('roadplatform.map.center');
    $homeMapZoom = $mapDisplay === 'bangladesh' ? 6 : config('roadplatform.map.zoom');
@endphp

@push('scripts')
<script>
    window.roadPlatform = {{ Js::from([
        'issues'      => $mapIssues,
        'center'      => $homeMapCenter,
        'zoom'        => $homeMapZoom,
        'countryBounds' => $countryBounds,
        'tileUrl'     => config('roadplatform.map.tile_url'),
        'attribution' => config('roadplatform.map.attribution'),
        'nearbyUrl'   => url('/api/v1/issues/nearby'),
        'i18n'        => [
            'nearYou'    => __('Near you'),
            'noneNearby' => __('No issues found near you.'),
            'locating'   => __('Finding your location...'),
            'denied'     => __('Location permission was denied. You can still browse the map.'),
            'noGeo'      => __('Your browser does not support location.'),
            'locFailed'  => __('Could not get your location.'),
            'outsideBangladesh' => __('report.outside_bangladesh'),
            'loadFailed' => __('Could not load nearby issues.'),
            'notified'   => __('issues.notified_tag'),
        ],
    ]) }};
</script>
@endpush
