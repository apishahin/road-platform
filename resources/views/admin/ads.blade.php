@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8" data-ads-panel>
    <h1 class="text-2xl font-bold">{{ __('issues.ad_title') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('issues.ad_sub') }}</p>

    @if (session('status'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.ads.save') }}" class="mt-6 space-y-5">
        @csrf

        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <label class="flex items-center gap-2 text-sm font-semibold">
                <input type="checkbox" name="on" value="1" @checked($ads['on']) class="size-4">
                {{ __('issues.ad_on') }}
            </label>
            <label class="mt-3 flex items-center gap-2 text-sm">
                <input type="checkbox" name="hide_for_staff" value="1" @checked($ads['hide_for_staff']) class="size-4">
                {{ __('issues.ad_hide_staff') }}
            </label>

            <div class="mt-4">
                <label class="text-sm font-medium">{{ __('issues.ad_publisher') }}</label>
                <input name="publisher_id" value="{{ $ads['publisher_id'] }}" placeholder="ca-pub-1234567890123456" dir="ltr"
                       class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                <p class="mt-1 text-xs text-slate-500">{{ __('issues.ad_publisher_hint') }}</p>
            </div>

            <div class="mt-4">
                <label class="text-sm font-medium">ads.txt</label>
                <textarea name="ads_txt" rows="3" dir="ltr" class="mt-1 w-full rounded-lg border-slate-300 font-mono text-xs"
                          placeholder="google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0">{{ $ads['ads_txt'] }}</textarea>
                <p class="mt-1 text-xs text-slate-500">{{ __('issues.ad_adstxt_hint') }}</p>
            </div>
        </div>

        @foreach ($slots as $slot)
            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <label class="text-sm font-semibold">{{ __('issues.ad_slot_'.$slot) }}</label>
                <p class="mt-0.5 text-xs text-slate-500">{{ __('issues.ad_slot_'.$slot.'_hint') }}</p>
                <textarea name="slots[{{ $slot }}]" rows="3" dir="ltr"
                          class="mt-2 w-full rounded-lg border-slate-300 font-mono text-xs"
                          placeholder="&lt;ins class=&quot;adsbygoogle&quot; ...&gt;&lt;/ins&gt; &lt;script&gt;...&lt;/script&gt;">{{ $ads['slots'][$slot] }}</textarea>
            </div>
        @endforeach

        <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('issues.sys_save') }}</button>
    </form>
</div>
@endsection
