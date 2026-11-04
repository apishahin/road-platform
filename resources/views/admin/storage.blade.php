@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="text-2xl font-bold">ছবির স্টোরেজ</h1>
    <p class="mt-1 text-sm text-slate-600">ছবি কোথায় জমা হবে তা এখান থেকে বাছুন। R2-এর পাসওয়ার্ড/key এখানে বসে না, শুধু সার্ভারের .env ফাইলে থাকে।</p>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    @if (session('checks'))
        <div class="mt-4 rounded-lg border border-slate-200 bg-white p-3 text-sm">
            <div class="mb-1 font-semibold">পরীক্ষার ফল</div>
            @foreach (session('checks') as $c)
                <div class="{{ $c['result']['ok'] ? 'text-green-700' : 'text-red-700' }}">
                    {{ $c['result']['ok'] ? '✓' : '✗' }} {{ $c['label'] }}: {{ $c['result']['message'] }}
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <div class="text-sm text-slate-500">এখন ব্যবহৃত</div>
        <div class="text-lg font-semibold">{{ $choice === 'r2' ? 'Cloudflare R2' : 'লোকাল ফোল্ডার' }}</div>
        <div class="mt-1 text-xs text-slate-500">প্রাইভেট ডিস্ক: {{ $private }} · পাবলিক ডিস্ক: {{ $public }}</div>
    </div>

    @foreach (['local' => 'লোকাল ফোল্ডার', 'r2' => 'Cloudflare R2'] as $key => $label)
        <div class="mt-4 rounded-xl border {{ $choice === $key ? 'border-brand-600' : 'border-slate-200' }} bg-white p-5">
            <div class="flex items-center justify-between">
                <div class="font-semibold">{{ $label }}</div>
                @if ($choice === $key)
                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">চালু আছে</span>
                @endif
            </div>
            <p class="mt-1 text-sm text-slate-600">
                @if ($key === 'local')
                    ছবি এই সার্ভারের storage ফোল্ডারে থাকে। পরীক্ষার জন্য ভালো।
                @elseif ($r2Ready)
                    .env-এ R2-এর তথ্য সেট করা আছে।
                @else
                    .env-এ R2-এর তথ্য এখনো সম্পূর্ণ সেট করা নেই।
                @endif
            </p>
            <div class="mt-3 flex gap-2">
                <form method="POST" action="{{ route('admin.storage.check') }}">
                    @csrf
                    <input type="hidden" name="target" value="{{ $key }}">
                    <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-100">পরীক্ষা করুন</button>
                </form>
                @if ($choice !== $key)
                    <form method="POST" action="{{ route('admin.storage.update') }}">
                        @csrf
                        <input type="hidden" name="storage" value="{{ $key }}">
                        <button class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700">এটি ব্যবহার করুন</button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach

    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5">
        <div class="font-semibold">কোন ডিস্কে কয়টি ছবি (প্রাইভেট)</div>
        @forelse ($counts as $disk => $n)
            <div class="mt-1 text-sm">{{ $disk ?: '—' }}: {{ $n }} টি</div>
        @empty
            <div class="mt-1 text-sm text-slate-500">এখনো কোনো ছবি নেই।</div>
        @endforelse
    </div>
</div>
@endsection