@extends('layouts.app')

@section('content')
@php $editing = $page->exists; @endphp
<div class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="mb-4 text-2xl font-bold">{{ $editing ? 'পেজ সম্পাদনা' : 'নতুন পেজ' }}</h1>

    @if ($errors->any())
        <div class="mb-4 rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $editing ? route('admin.pages.update', $page) : route('admin.pages.store') }}"
          class="space-y-4">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div>
            <label class="block text-sm font-semibold" for="slug">লিংকের নাম (ইংরেজি, যেমন: how-it-works)</label>
            <input id="slug" name="slug" type="text" maxlength="60" required
                   value="{{ old('slug', $page->slug) }}" class="w-full rounded border px-3 py-2">
            <p class="text-xs text-gray-600">পেজের ঠিকানা হবে /p/লিংকের-নাম</p>
        </div>

        <div>
            <label class="block text-sm font-semibold" for="title_bn">শিরোনাম (বাংলা)</label>
            <input id="title_bn" name="title_bn" type="text" maxlength="150" required
                   value="{{ old('title_bn', $page->title_bn) }}" class="w-full rounded border px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-semibold" for="title_en">Title (English, ঐচ্ছিক)</label>
            <input id="title_en" name="title_en" type="text" maxlength="150"
                   value="{{ old('title_en', $page->title_en) }}" class="w-full rounded border px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-semibold" for="body_bn">লেখা (বাংলা)</label>
            <textarea id="body_bn" name="body_bn" rows="12" required
                      class="w-full rounded border px-3 py-2">{{ old('body_bn', $page->body_bn) }}</textarea>
            <p class="text-xs text-gray-600">
                লেখার নিয়ম: <code>## বড় শিরোনাম</code>, <code>**গাঢ়**</code>, <code>- তালিকা</code>,
                <code>[লেখা](https://লিংক)</code>। HTML বা script কাজ করবে না।
            </p>
        </div>

        <div>
            <label class="block text-sm font-semibold" for="body_en">Body (English, ঐচ্ছিক — ফাঁকা থাকলে বাংলা দেখাবে)</label>
            <textarea id="body_en" name="body_en" rows="8"
                      class="w-full rounded border px-3 py-2">{{ old('body_en', $page->body_en) }}</textarea>
        </div>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $page->is_published))>
                প্রকাশ করুন
            </label>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="in_menu" value="1" @checked(old('in_menu', $page->in_menu))>
                মেনুতে দেখান (সর্বোচ্চ ৬টি)
            </label>
            <label class="inline-flex items-center gap-2">
                মেনু ক্রম
                <input type="number" name="menu_order" min="0" max="1000"
                       value="{{ old('menu_order', $page->menu_order ?? 100) }}" class="w-24 rounded border px-2 py-1">
            </label>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="rounded bg-brand-600 px-4 py-2 text-white">সংরক্ষণ</button>
            <a href="{{ route('admin.pages.index') }}" class="px-4 py-2 underline">বাতিল</a>
        </div>
    </form>
</div>
@endsection