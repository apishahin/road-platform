@props(['title' => null])

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm']) }}>
    @if ($title)
        <h2 class="mb-3 text-base font-bold text-slate-900">{{ $title }}</h2>
    @endif

    {{ $slot }}
</section>
