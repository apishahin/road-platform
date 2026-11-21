@php
    /** @var \Illuminate\Support\Collection $cards */
@endphp

@if ($tab === 'issues')
    <p class="mb-3 text-sm text-slate-600">{{ $issueState === 'unpublished' ? __('workcenter.unpublished_help') : __('workcenter.published_help') }}</p>
@endif

<div class="space-y-3" aria-live="polite">
    @forelse ($cards as $card)
        @php
            $issue = $card['issue'];
            $record = $card['record'];
            $typeClass = match ($card['type']) {
                'moderation' => 'border-amber-200 bg-amber-50/30',
                'verify' => 'border-sky-200 bg-sky-50/30',
                'photos' => 'border-violet-200 bg-violet-50/30',
                default => 'border-emerald-200 bg-emerald-50/30',
            };
            $badgeClass = match ($card['type']) {
                'moderation' => 'bg-amber-100 text-amber-900',
                'verify' => 'bg-sky-100 text-sky-900',
                'photos' => 'bg-violet-100 text-violet-900',
                default => 'bg-emerald-100 text-emerald-900',
            };
        @endphp
        <article class="rounded-2xl border {{ $typeClass }} p-4 shadow-sm sm:p-5" data-work-card="{{ $card['type'] }}-{{ $record->id }}">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                @if (! empty($card['image_url']))
                    <a href="{{ $card['detail_url'] }}" class="block shrink-0" aria-label="{{ __('workcenter.review_detail') }}">
                        <img src="{{ $card['image_url'] }}" alt="" class="size-20 rounded-xl border border-slate-200 object-cover sm:size-24">
                    </a>
                @endif
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $badgeClass }}">{{ __('workcenter.type_' . $card['type']) }}</span>
                        @if ($card['issue_no'])
                            <span class="text-xs font-bold text-slate-500">{{ __('workcenter.issue_number', ['number' => \App\Support\IssuePresenter::num($card['issue_no'])]) }}</span>
                        @endif
                        @if ($card['when'])
                            <span class="text-xs text-slate-500">{{ $card['when'] }}</span>
                        @endif
                    </div>
                    <h2 class="mt-2 text-base font-bold leading-6 text-slate-900">{{ $card['title'] }}</h2>
                    <p class="mt-1 text-sm text-slate-600">{{ $card['area'] }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-700">{{ $card['meta'] }}</p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-200/80 pt-3">
                @if ($card['type'] === 'moderation')
                    <form method="POST" action="{{ route('admin.moderation.approve', $record) }}">
                        @csrf
                        @if ($card['approve_area_id'])
                            <input type="hidden" name="area_id" value="{{ $card['approve_area_id'] }}">
                        @endif
                        <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-800">{{ __('workcenter.approve') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.moderation.hide', $record) }}" onsubmit="return confirm(@js(__('workcenter.hide_confirm')));">
                        @csrf
                        <button type="submit" class="rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50">{{ __('workcenter.hide') }}</button>
                    </form>
                @elseif ($card['type'] === 'photos')
                    <form method="POST" action="{{ route('photoreview.approve', $record) }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-800">{{ __('workcenter.approve') }}</button>
                    </form>
                    <form method="POST" action="{{ route('photoreview.hide', $record) }}" class="flex items-center gap-2" onsubmit="return confirm(@js(__('workcenter.hide_confirm')));">
                        @csrf
                        <select name="reason" class="rounded-lg border border-slate-300 bg-white px-2 py-2 text-xs">
                            @foreach (['face', 'plate', 'personal', 'other'] as $reason)
                                <option value="{{ $reason }}">{{ __('issues.photo_reason_' . $reason) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50">{{ __('workcenter.hide') }}</button>
                    </form>
                @elseif ($card['type'] === 'issues')
                    <a href="{{ route('admin.issues.edit', $record) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">{{ __('workcenter.edit') }}</a>
                    @if ($issue->published_at && ! $issue->unpublished_at)
                        <a href="{{ route('admin.issues.notify', $record) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">{{ __('notify.button') }}</a>
                    @endif
                @endif

                @if ($card['detail_url'])
                    <a href="{{ $card['detail_url'] }}" @if (in_array($card['type'], ['verify', 'issues'], true)) target="_blank" rel="noopener" @endif
                       class="ml-auto rounded-lg px-2 py-2 text-xs font-bold text-brand-700 underline hover:text-brand-900">{{ $card['type'] === 'verify' ? __('workcenter.open_verify') : __('workcenter.review_detail') }}</a>
                @endif
            </div>
        </article>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center">
            <p class="text-base font-bold text-slate-700">{{ __('workcenter.empty_title') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ __('workcenter.empty_help') }}</p>
        </div>
    @endforelse
</div>
