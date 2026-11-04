@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-8">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-bold">পেজ ব্যবস্থাপনা</h1>
        <a href="{{ route('admin.pages.create') }}" class="rounded bg-brand-600 px-3 py-2 text-sm text-white">+ নতুন পেজ</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-900">{{ session('status') }}</div>
    @endif

    @if ($pages->isEmpty())
        <p class="text-gray-600">এখনো কোনো পেজ নেই।</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2 pr-3">শিরোনাম</th>
                        <th class="py-2 pr-3">লিংক</th>
                        <th class="py-2 pr-3">অবস্থা</th>
                        <th class="py-2 pr-3">মেনু</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($pages as $p)
                    <tr class="border-b align-top">
                        <td class="py-2 pr-3">{{ $p->title_bn }}</td>
                        <td class="py-2 pr-3"><a class="underline" href="{{ route('page.show', $p->slug) }}">/p/{{ $p->slug }}</a></td>
                        <td class="py-2 pr-3">{{ $p->is_published ? 'প্রকাশিত' : 'খসড়া' }}</td>
                        <td class="py-2 pr-3">{{ $p->in_menu ? 'হ্যাঁ (ক্রম '.$p->menu_order.')' : 'না' }}</td>
                        <td class="py-2 whitespace-nowrap">
                            <a class="underline" href="{{ route('admin.pages.edit', $p) }}">সম্পাদনা</a>
                            <form method="POST" action="{{ route('admin.pages.destroy', $p) }}" class="ml-2 inline"
                                  onsubmit="return confirm('পেজটি মুছে ফেলবেন?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-700 underline">মুছুন</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection