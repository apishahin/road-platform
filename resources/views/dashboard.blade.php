@extends('layouts.app')

@section('title', __('issues.dash_title'))

@section('content')
@php
    $N    = fn ($n) => \App\Support\IssuePresenter::num($n);
    $card = 'background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;box-shadow:0 1px 3px rgba(15,23,42,.05);';
@endphp

<div class="mx-auto px-4 py-6" style="max-width:1000px;" data-dashboard>
    <h1 style="font-size:24px;font-weight:800;color:#0f172a;">{{ __('issues.dash_title') }}</h1>
    <p style="margin-top:2px;font-size:14px;color:#64748b;">{{ __('issues.dash_sub') }}</p>

    @php $empty = \App\Models\Issue::active()->count() === 0 && \App\Models\Report::where('moderation', 'approved')->count() === 0; @endphp
    @if ($empty)
        <div style="{{ $card }};margin-top:16px;text-align:center;color:#64748b;font-size:14px;">{{ __('issues.dash_empty') }}</div>
    @else
        <div style="margin-top:16px;">
            @include('partials.stats-panel', ['forceAll' => true])
        </div>
    @endif
</div>

@include('partials.ad-slot', ['slot' => 'dashboard_bottom'])
@endsection
