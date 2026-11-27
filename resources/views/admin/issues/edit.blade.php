@extends('layouts.app')

@section('title', __('issueadmin.edit_title'))

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('issueadmin.edit_title') }}</h1>
    @if ($errors->any())<div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('admin.issues.update', ['issue' => $issue->id]) }}" class="mt-6 space-y-4">
        @csrf
        <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="font-bold text-slate-900">{{ __('issueadmin.edit_title') }}</h2>
            <label class="block text-sm font-medium text-slate-700">{{ __('issueadmin.f_title') }}<input type="text" name="title" value="{{ old('title', $issue->title) }}" maxlength="200" required class="mt-1 block w-full rounded-lg border border-slate-300 p-2.5"></label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-slate-700">{{ __('issueadmin.f_road') }}<input type="text" name="road_name" value="{{ old('road_name', $issue->road_name) }}" maxlength="255" class="mt-1 block w-full rounded-lg border border-slate-300 p-2.5"></label>
                <label class="block text-sm font-medium text-slate-700">{{ __('issueadmin.f_landmark') }}<input type="text" name="landmark" value="{{ old('landmark', $issue->landmark) }}" maxlength="255" class="mt-1 block w-full rounded-lg border border-slate-300 p-2.5"></label>
            </div>
        </section>
        <section class="space-y-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <h2 class="font-bold text-amber-950">{{ __('report.step1') }}</h2>
            <p class="text-sm text-amber-900">{{ __('report.text_only_review') }}</p>
            <label class="block text-sm font-medium text-slate-700">{{ __('report.place_name') }}<input type="text" name="place_name" value="{{ old('place_name', $issue->place_name) }}" maxlength="200" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5"></label>
            <label class="block text-sm font-medium text-slate-700">{{ __('report.location_note') }}<textarea name="location_note" rows="2" maxlength="500" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5">{{ old('location_note', $issue->location_note) }}</textarea></label>
            <label class="block text-sm font-medium text-slate-700">{{ __('report.location_precision') }}
                <select name="location_precision" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5"><option value="exact" @selected(old('location_precision', $issue->location_precision) === 'exact')>{{ __('report.location_exact') }}</option><option value="approximate" @selected(old('location_precision', $issue->location_precision) === 'approximate')>{{ __('report.location_approximate') }}</option><option value="text_only" @selected(old('location_precision', $issue->location_precision) === 'text_only')>{{ __('report.location_text_only') }}</option></select>
            </label>
            <div class="grid gap-4 sm:grid-cols-2"><label class="block text-sm font-medium text-slate-700">Latitude<input type="number" step="0.0000001" name="lat" value="{{ old('lat', $issue->lat) }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5"></label><label class="block text-sm font-medium text-slate-700">Longitude<input type="number" step="0.0000001" name="lng" value="{{ old('lng', $issue->lng) }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5"></label></div>
            <p class="text-xs text-amber-900">{{ __('report.location_admin_release') }}</p>
        </section>
        <div class="flex items-center gap-3"><button type="submit" class="rounded-lg bg-slate-900 px-5 py-2.5 font-bold text-white">{{ __('issueadmin.save') }}</button><a href="{{ route('admin.issues.index') }}" class="text-sm underline">{{ __('issueadmin.back') }}</a></div>
    </form>
</div>
@endsection
