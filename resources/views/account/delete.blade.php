@extends('layouts.app')

@section('title', __('acctphone.del_title'))

@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <div class="rounded-xl border border-red-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-bold text-red-700">{{ __('acctphone.del_title') }}</h1>

        @if ($errors->any())
            <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</p>
        @endif
        @if (session('status'))
            <p class="mt-3 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700">{{ session('status') }}</p>
        @endif

        @if ($isStaff)
            <p class="mt-4 text-sm text-slate-600">{{ __('acctphone.del_staff') }}</p>
        @elseif ($noPhone)
            <p class="mt-4 text-sm text-slate-600">{{ __('acctphone.del_nophone') }}</p>
            <a href="{{ route('account.phone') }}"
               class="mt-4 inline-block rounded-lg border border-brand-600 px-4 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">
                {{ __('acctphone.del_nophone_cta') }}
            </a>
        @else
            <p class="mt-4 text-sm text-slate-600">{{ __('acctphone.del_intro') }}</p>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-slate-600">
                <li>{{ __('acctphone.del_point_personal') }}</li>
                <li>{{ __('acctphone.del_point_reports') }}</li>
                <li>{{ __('acctphone.del_point_undo') }}</li>
            </ul>

            @if (! $sent)
                <form method="POST" action="{{ route('account.delete.send') }}" class="mt-5">
                    @csrf
                    <p class="mb-2 text-sm text-slate-500">{{ __('acctphone.code_to', ['phone' => $masked]) }}</p>
                    <button type="submit"
                            class="w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                        {{ __('acctphone.del_send') }}
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('account.delete.confirm') }}" class="mt-5">
                    @csrf
                    <label for="code" class="text-sm font-medium text-slate-700">{{ __('login.code') }}</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required
                           class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-center text-xl tracking-widest">
                    <button type="submit"
                            class="mt-3 w-full rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                        {{ __('acctphone.del_confirm') }}
                    </button>
                </form>
                <form method="POST" action="{{ route('account.delete.send') }}" class="mt-3 text-center">
                    @csrf
                    <button type="submit" class="text-sm text-slate-500 underline">{{ __('acctphone.resend') }}</button>
                </form>
            @endif
        @endif

        <a href="{{ route('home') }}" class="mt-5 block text-center text-sm text-slate-600 underline">{{ __('acctphone.back_home') }}</a>
    </div>
</div>
@endsection
