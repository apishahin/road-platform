@extends('layouts.app')

@section('title', 'Appearance')

@section('content')
{{-- The appearance editor has been merged into Homepage & site; keep this page as a friendly redirect. --}}
<script>window.location.replace("{{ route('admin.site.index') }}#appearance");</script>
<div class="mx-auto max-w-3xl px-4 py-12 text-center">
    <p class="text-slate-700">{{ __('issues.appearance_moved') }}
        <a href="{{ route('admin.site.index') }}#appearance" style="color:#047857;font-weight:700;">{{ __('issues.appearance_moved_link') }}</a>
    </p>
</div>
@endsection
