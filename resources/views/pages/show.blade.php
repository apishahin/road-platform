@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    @if (! $page->is_published)
        <div class="mb-4 rounded border border-yellow-300 bg-yellow-50 px-3 py-2 text-sm text-yellow-900">
            এটি খসড়া। শুধু অ্যাডমিন দেখতে পাচ্ছেন।
            <a class="underline" href="{{ route('admin.pages.edit', $page) }}">সম্পাদনা</a>
        </div>
    @endif

    <h1 class="mb-6 text-2xl font-bold">{{ $page->title() }}</h1>

    <div class="space-y-4 leading-relaxed
        [&_h2]:mt-6 [&_h2]:text-xl [&_h2]:font-bold
        [&_h3]:mt-4 [&_h3]:text-lg [&_h3]:font-semibold
        [&_ul]:list-disc [&_ul]:pl-6
        [&_ol]:list-decimal [&_ol]:pl-6
        [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:pl-3 [&_blockquote]:text-gray-600">
        {!! $page->bodyHtml() !!}
    </div>
</div>
@endsection