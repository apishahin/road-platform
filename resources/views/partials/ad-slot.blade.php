@php $adHtml = \App\Support\AdSettings::slot($slot ?? ''); @endphp
@if ($adHtml !== '')
    <div class="mx-auto my-6 max-w-4xl px-4" data-ad-slot="{{ $slot }}">
        <div class="text-center text-[10px] font-medium uppercase tracking-widest text-slate-400">{{ __('issues.ad_label') }}</div>
        <div class="mt-1 overflow-hidden rounded-xl border border-slate-100 bg-white p-2">{!! $adHtml !!}</div>
    </div>
@endif
