@php
    $__up = \App\Support\UpdatePhotos::publicFor($issue);
@endphp
@if ($__up->isNotEmpty())
    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-bold text-slate-900">{{ __('issues.photos_updates_title') }}</h2>
        <p style="font-size:13px;color:#64748b">{{ __('issues.photos_updates_note') }}</p>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:12px">
            @foreach ($__up as $__ev)
                <figure style="margin:0">
                    <a data-lightbox="issue" href="{{ $__ev->url() }}" target="_blank" rel="noopener">
                        <img src="{{ $__ev->url() }}" alt="" loading="lazy" style="width:100%;height:120px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0">
                    </a>
                    <figcaption style="margin-top:4px;font-size:12px;color:#475569">
                        {{ $__ev->observation_id ? __('issues.photo_cap_citizen') : __('issues.photo_cap_visit') }}
                        @if ($__ev->taken_at)
                            &middot; {{ $__ev->taken_at->format('d/m/Y') }}
                        @endif
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </section>
@endif