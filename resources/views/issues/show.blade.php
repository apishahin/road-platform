@extends('layouts.app')

@section('title', $issue->title)
@section('description', $explanation)

@push('head')
    <meta property="og:url" content="{{ url()->current() }}">
@endpush

@section('content')
<div class="mx-auto max-w-6xl px-4 py-6 sm:py-8">

    {{-- Breadcrumb --}}
    <nav class="text-sm text-slate-500">
        <a href="{{ url('/') }}" class="hover:text-slate-800">{{ __('issue.page.home') }}</a>
        <span class="mx-1">/</span>
        <a href="{{ url('/issues') }}" class="hover:text-slate-800">{{ __('issue.page.all_issues') }}</a>
    </nav>

    {{-- Notices for non-active issues --}}
    @if ($issue->disposition === 'duplicate')
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            {{ __('issue.page.duplicate_notice') }}
            @if ($issue->duplicateOf)
                <a href="{{ url('/issues/' . $issue->duplicateOf->public_id) }}" class="font-semibold underline">{{ __('issue.page.view_main') }}</a>
            @endif
        </div>
    @elseif ($issue->disposition === 'rejected')
        <div class="mt-4 rounded-lg border border-slate-300 bg-slate-100 p-4 text-sm text-slate-800">
            {{ __('issue.page.rejected_notice') }}
        </div>
    @elseif ($issue->disposition === 'referred')
        <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
            {{ __('issue.page.referred_notice') }}
        </div>
    @endif

    <div class="mt-4 grid gap-6 lg:grid-cols-3">

        {{-- ═════════ Main column ═════════ --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Header --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                <x-status-badge :issue="$issue" />

                <h1 class="mt-3 text-2xl font-bold leading-snug text-slate-900 sm:text-3xl">{{ $issue->title }}</h1>
                <p class="mt-3 text-slate-600">{{ $explanation }}</p>

                <dl class="mt-6 grid gap-4 border-t border-slate-100 pt-5 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.category') }}</dt>
                        <dd class="font-medium text-slate-900">{{ $issue->category?->name() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.road') }}</dt>
                        <dd class="font-medium text-slate-900">{{ $issue->road_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.landmark') }}</dt>
                        <dd class="font-medium text-slate-900">{{ $issue->landmark ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.area') }}</dt>
                        <dd class="font-medium text-slate-900">
                            @if ($issue->adminArea)
                                {{ app()->getLocale() === 'bn' ? $issue->adminArea->name_bn : ($issue->adminArea->name_en ?: $issue->adminArea->name_bn) }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.verification') }}</dt>
                        <dd class="font-medium text-slate-900">{{ \App\Support\StatusLabel::verification($issue->verification_status) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.reports') }}</dt>
                        <dd class="font-medium text-slate-900">{{ $reportsCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.first_reported') }}</dt>
                        <dd class="font-medium text-slate-900">{{ $firstAt }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ __('issue.page.last_activity') }}</dt>
                        <dd class="font-medium text-slate-900">{{ $lastAt }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Timeline --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 class="text-lg font-bold text-slate-900">{{ __('issue.page.timeline') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('issue.page.timeline_note') }}</p>

                @if ($timeline->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">{{ __('issue.page.no_timeline') }}</p>
                @else
                    <ol class="mt-5 space-y-6 border-s border-slate-200 ps-6">
                        @foreach ($timeline as $row)
                            <li class="relative">
                                <span class="absolute -start-[30px] top-1.5 size-3 rounded-full bg-brand-600 ring-4 ring-white"></span>

                                <div class="flex flex-wrap gap-x-2 text-xs text-slate-500">
                                    <time>{{ $row['at'] }}</time>
                                    <span>·</span>
                                    <span>{{ $row['source'] }}</span>
                                    <span>·</span>
                                    <span>{{ $row['actor'] }}</span>
                                </div>

                                <p class="mt-1 font-semibold text-slate-900">{{ $row['label'] }}</p>
                                <p class="mt-0.5 text-sm text-slate-600">{{ $row['reason'] }}</p>

                                @if ($row['url'])
                                    <a href="{{ $row['url'] }}" target="_blank" rel="nofollow noopener noreferrer"
                                       class="mt-1 inline-block text-sm text-brand-700 underline">{{ __('issue.page.source_link') }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            {{-- Reports --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 class="text-lg font-bold text-slate-900">{{ __('issue.page.reports_title') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('issue.page.reports_note') }}</p>

                @forelse ($reports as $report)
                    <div class="mt-4 rounded-lg border border-slate-100 p-4">
                        <p class="whitespace-pre-line text-sm text-slate-800">{{ $report->description }}</p>
                        <p class="mt-2 text-xs text-slate-500">{{ $report->created_at?->diffForHumans() }}</p>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-slate-500">{{ __('issue.page.no_reports') }}</p>
                @endforelse
            </section>

            {{-- Photos --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 class="text-lg font-bold text-slate-900">{{ __('issue.page.photos') }}</h2>

                @if ($photos->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">{{ __('issue.page.no_photos') }}</p>
                @else
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($photos as $photo)
                            <img src="{{ $photo->url() }}"
                                 alt="{{ $issue->title }}"
                                 loading="lazy"
                                 class="h-32 w-full rounded-lg object-cover">
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- ═════════ Sidebar ═════════ --}}
        <aside class="space-y-6">

            {{-- Map --}}
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <h2 class="px-4 pt-4 font-semibold text-slate-900">{{ __('issue.page.map') }}</h2>
                <iframe src="{{ $mapEmbed }}" title="{{ $issue->title }}" loading="lazy"
                        class="mt-3 h-56 w-full border-0"></iframe>
                <div class="flex flex-wrap gap-4 p-4 text-sm">
                    <a href="https://www.openstreetmap.org/?mlat={{ $issue->lat }}&mlon={{ $issue->lng }}#map=17/{{ $issue->lat }}/{{ $issue->lng }}"
                       target="_blank" rel="noopener" class="text-brand-700 underline">{{ __('issue.page.open_map') }}</a>
                    <a href="https://www.google.com/maps?q={{ $issue->lat }},{{ $issue->lng }}"
                       target="_blank" rel="noopener" class="text-brand-700 underline">{{ __('issue.page.directions') }}</a>
                </div>
            </section>

            {{-- Authority --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="font-semibold text-slate-900">{{ __('issue.page.authority') }}</h2>

                @forelse ($authorities as $link)
                    @php
                        $auth = $link->authority;
                        $authName = app()->getLocale() === 'bn' ? $auth->name_bn : ($auth->name_en ?: $auth->name_bn);
                    @endphp
                    <div class="mt-3 rounded-lg border border-slate-200 p-3">
                        <p class="font-medium text-slate-900">{{ $authName }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('issue.authority_state.' . $link->state) }}</p>
                        <p class="mt-2 text-sm text-slate-600">
                            <span class="font-medium">{{ __('issue.page.basis') }}:</span> {{ $link->basis }}
                        </p>
                    </div>
                @empty
                    <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                        {{ __('issue.page.authority_unverified') }}
                    </p>
                    <p class="mt-2 text-xs text-slate-500">{{ __('issue.page.authority_note') }}</p>
                @endforelse
            </section>

            {{-- Share --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5"
                     x-data='{ copied: false, url: @js(url()->current()), title: @js($issue->title) }'>
                <h2 class="font-semibold text-slate-900">{{ __('issue.page.share') }}</h2>
                <button type="button"
                        class="mt-3 w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50"
                        x-on:click="navigator.share ? navigator.share({ title: title, url: url }) : navigator.clipboard.writeText(url).then(() => copied = true)">
                    {{ __('issue.page.share') }}
                </button>
                <p x-show="copied" x-cloak class="mt-2 text-xs text-green-700">{{ __('issue.page.copied') }}</p>
            </section>

            {{-- Confirm (needs login, coming later) --}}
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="font-semibold text-slate-900">{{ __('issue.page.confirm') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('issue.page.confirm_note') }}</p>
                <a href="{{ url('/login') }}"
                   class="mt-3 inline-block w-full rounded-lg bg-brand-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-brand-700">
                    {{ __('issue.page.login_to_confirm') }}
                </a>
            </section>

            {{-- Correction path --}}
            <p class="px-1 text-sm text-slate-500">
                {{ __('issue.page.wrong_info') }}
                <span class="italic">{{ __('issue.page.coming_soon') }}</span>
            </p>
        </aside>
    </div>
</div>
@endsection