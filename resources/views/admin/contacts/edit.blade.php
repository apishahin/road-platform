@extends('layouts.app')

@section('title', __('authcontact.edit_title'))

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">{{ __('authcontact.edit_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('authcontact.private_note') }}</p>
        </div>
        <a href="{{ route('admin.contacts.index') }}" class="text-sm font-semibold text-brand-700 underline">{{ __('authcontact.back_to_list') }}</a>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <h2 class="text-lg font-bold text-slate-900">{{ $contact->name }}</h2>
        @include('admin.contacts._form', ['contact' => $contact])
    </section>
</div>
@endsection
