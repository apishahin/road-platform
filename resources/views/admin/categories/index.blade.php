@extends('layouts.app')

@section('title', __('core.categories_title'))

@section('content')
<div class="mx-auto max-w-5xl px-4 py-6 sm:py-8">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">{{ __('core.categories_title') }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-600">{{ __('core.categories_intro') }}</p>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">{{ __('core.categories_note') }}</span>
    </div>

    @if (session('status'))
        <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <h2 class="font-bold text-slate-900">{{ __('core.category_add') }}</h2>
        <form method="POST" action="{{ route('admin.categories.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @csrf
            <label class="text-sm font-semibold text-slate-700">{{ __('core.category_parent') }}
                <select name="parent_id" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm">
                    <option value="">{{ __('core.category_new_main') }}</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->id }}">{{ $parent->name() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold text-slate-700">{{ __('core.category_bn') }}
                <input required name="name_bn" value="{{ old('name_bn') }}" maxlength="100" class="mt-1 block w-full rounded-lg border border-slate-300 p-2.5 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">{{ __('core.category_en') }}
                <input name="name_en" value="{{ old('name_en') }}" maxlength="100" class="mt-1 block w-full rounded-lg border border-slate-300 p-2.5 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">{{ __('core.category_slug') }}
                <input required name="slug" value="{{ old('slug') }}" maxlength="50" pattern="[a-z0-9-]+" placeholder="streetlight-out" class="mt-1 block w-full rounded-lg border border-slate-300 p-2.5 text-sm">
            </label>
            <label class="text-sm font-semibold text-slate-700">{{ __('core.category_order') }}
                <input type="number" name="sort" value="{{ old('sort', 0) }}" min="0" max="65535" class="mt-1 block w-full rounded-lg border border-slate-300 p-2.5 text-sm">
            </label>
            <div class="flex flex-wrap items-end gap-4 pb-1 text-sm font-semibold text-slate-700">
                <label><input type="checkbox" name="is_active" value="1" checked> {{ __('core.category_active') }}</label>
                <label><input type="checkbox" name="is_reportable" value="1" checked> {{ __('core.category_reportable') }}</label>
                <button class="rounded-lg bg-brand-600 px-4 py-2.5 font-bold text-white hover:bg-brand-700">{{ __('core.category_add') }}</button>
            </div>
        </form>
    </section>

    <section class="mt-5 space-y-4">
        @forelse ($parents as $parent)
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3 sm:px-5">
                    <div>
                        <h2 class="font-extrabold text-slate-900">{{ $parent->name() }}</h2>
                        <p class="text-xs text-slate-500">/{{ $parent->slug }} · {{ $parent->is_active ? __('core.active') : __('core.inactive') }} · {{ __('core.category_group_only') }}</p>
                    </div>
                    @include('admin.categories._edit', ['category' => $parent, 'parents' => $parents])
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($parent->children as $child)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-5">
                            <div>
                                <p class="font-semibold text-slate-800">{{ $child->name() }}</p>
                                <p class="text-xs text-slate-500">/{{ $child->slug }} · {{ $child->is_active ? __('core.active') : __('core.inactive') }} · {{ $child->is_reportable ? __('core.category_reportable') : __('core.category_group_only') }}</p>
                            </div>
                            @include('admin.categories._edit', ['category' => $child, 'parents' => $parents])
                        </div>
                    @empty
                        <p class="px-4 py-4 text-sm text-slate-500 sm:px-5">{{ __('core.category_no_children') }}</p>
                    @endforelse
                </div>
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">{{ __('core.category_empty') }}</p>
        @endforelse
    </section>
</div>
@endsection
