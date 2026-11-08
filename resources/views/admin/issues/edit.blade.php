@extends('layouts.app')

@section('title', __('issueadmin.edit_title'))

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('issueadmin.edit_title') }}</h1>

    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.issues.update', ['issue' => $issue->id]) }}" class="mt-6 space-y-4">
        @csrf
        <label class="block text-sm font-medium text-slate-700">{{ __('issueadmin.f_title') }}
            <input type="text" name="title" value="{{ old('title', $issue->title) }}" maxlength="200" required style="display:block;width:100%;margin-top:4px;border:1px solid #cbd5e1;border-radius:8px;padding:8px;">
        </label>
        <label class="block text-sm font-medium text-slate-700">{{ __('issueadmin.f_road') }}
            <input type="text" name="road_name" value="{{ old('road_name', $issue->road_name) }}" maxlength="255" style="display:block;width:100%;margin-top:4px;border:1px solid #cbd5e1;border-radius:8px;padding:8px;">
        </label>
        <label class="block text-sm font-medium text-slate-700">{{ __('issueadmin.f_landmark') }}
            <input type="text" name="landmark" value="{{ old('landmark', $issue->landmark) }}" maxlength="255" style="display:block;width:100%;margin-top:4px;border:1px solid #cbd5e1;border-radius:8px;padding:8px;">
        </label>
        <div class="flex items-center gap-3">
            <button type="submit" style="background:#0f172a;color:#fff;border:0;border-radius:8px;padding:8px 18px;cursor:pointer;">{{ __('issueadmin.save') }}</button>
            <a href="{{ route('admin.issues.index') }}" style="font-size:14px;text-decoration:underline;">{{ __('issueadmin.back') }}</a>
        </div>
    </form>
</div>
@endsection