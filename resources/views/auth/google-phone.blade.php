@extends('layouts.app')

@section('title', __('glogin.phone_title'))

@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-bold text-slate-900">{{ __('glogin.phone_title') }}</h1>
        <p class="mt-1 text-sm text-slate-600">
            {{ __('glogin.phone_intro') }}
            @if ($name !== '')<br><span class="font-medium text-slate-700">{{ $name }}</span>@endif
        </p>

        @if ($errors->any())
            <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('login.google.phone.save') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <label for="phone" class="text-sm font-medium text-slate-700">{{ __('login.phone') }}</label>
                <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                       value="{{ old('phone') }}" placeholder="01XXXXXXXXX" required maxlength="30"
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-base">
            </div>
            <button type="submit"
                    class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
                {{ __('glogin.save_number') }}
            </button>
        </form>

        @if ($optional)
            <form method="POST" action="{{ route('login.google.phone.skip') }}" class="mt-3">
                @csrf
                <button type="submit" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    {{ __('glogin.skip') }}
                </button>
            </form>
        @endif

        <a href="{{ route('login') }}" class="mt-5 block text-center text-sm text-slate-600 underline">{{ __('glogin.back') }}</a>
    </div>
</div>
@endsection
