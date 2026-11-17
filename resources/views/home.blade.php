@extends('layouts.app')

@section('title', __('Find and report road problems'))

@section('content')
@if (\App\Support\SiteSections::modern('home'))
    @include('home.modern')
@else
    @include('home.classic')
@endif
@endsection

@push('scripts')
<script>
    window.roadPlatform = {{ Js::from([
        'issues'      => $mapIssues,
        'center'      => config('roadplatform.map.center'),
        'zoom'        => config('roadplatform.map.zoom'),
        'countryBounds' => config('roadplatform.map.country_bounds'),
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
            'loadFailed' => __('Could not load nearby issues.'),
            'notified'   => __('issues.notified_tag'),
        ],
    ]) }};
</script>
@endpush
