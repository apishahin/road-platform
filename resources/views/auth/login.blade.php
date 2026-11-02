@extends('layouts.app')

@section('title', __('login.title'))

@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-bold text-slate-900">{{ __('login.title') }}</h1>
        <p class="mt-1 text-sm text-slate-600">{{ __('login.intro') }}</p>

        <form method="POST" action="{{ url('/login') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <label for="phone" class="text-sm font-medium text-slate-700">{{ __('login.phone') }}</label>
                <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                       value="{{ old('phone') }}" placeholder="01XXXXXXXXX" required maxlength="30"
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-base">
                @error('phone')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                {{ __('login.send_code') }}
            </button>
        </form>

        <p class="mt-4 text-xs text-slate-500">{{ __('login.privacy') }}</p>
    </div>
</div>
@endsection