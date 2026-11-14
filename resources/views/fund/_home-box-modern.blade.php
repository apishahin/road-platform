@php $fc = \App\Support\Funding::active(); @endphp
@if ($fc)
@php
    $fRaised = \App\Support\Funding::raised($fc);
    $fPct = \App\Support\Funding::percent($fRaised, $fc->goal_amount);
    $fCan = \App\Support\Funding::canDonate($fc, $fRaised);
@endphp
<section id="fund-box" class="px-4 pt-4">
    <div class="mx-auto max-w-6xl overflow-hidden rounded-2xl bg-gradient-to-br from-amber-50 to-orange-100/60 shadow-sm ring-1 ring-amber-200">
        <div class="flex flex-col gap-4 p-4 sm:p-6 lg:flex-row lg:items-center">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="grid size-9 place-items-center rounded-xl bg-emerald-600/10 text-emerald-700">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-4.6-7-10a4 4 0 0 1 7-2.5A4 4 0 0 1 19 11c0 5.4-7 10-7 10Z"/></svg>
                    </span>
                    <h2 class="truncate text-lg font-extrabold text-slate-900">{{ $fc->title }}</h2>
                </div>
                @if ($fc->description)
                    <p class="mt-1 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($fc->description, 160) }}</p>
                @endif
                <div class="mt-2.5 flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm">
                    <span class="font-extrabold text-emerald-700">&#2547;{{ \App\Support\Funding::num($fRaised) }}</span>
                    <span class="text-slate-600">{{ __('issues.fund_goal') }}: &#2547;{{ \App\Support\Funding::num($fc->goal_amount) }}</span>
                    <span class="rounded-full bg-emerald-600/10 px-2.5 py-0.5 text-xs font-extrabold text-emerald-700">{{ \App\Support\Funding::num($fPct) }}%</span>
                </div>
                <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-amber-200/70">
                    <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-green-600 transition-all" style="width:{{ $fPct }}%"></div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    @if (\App\Support\SiteSections::boolOn('fund', 'm_donors'))
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1.5 text-xs font-bold text-slate-700 ring-1 ring-amber-200">
                            <svg class="size-4 text-emerald-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path stroke-linecap="round" d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            {{ \App\Support\Funding::num(\App\Support\Funding::donorCount($fc)) }} {{ __('issues.fund_donors') }}
                        </span>
                    @endif
                    @if (\App\Support\SiteSections::boolOn('fund', 'm_projects'))
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1.5 text-xs font-bold text-slate-700 ring-1 ring-amber-200">
                            <svg class="size-4 text-emerald-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 3 4.06 15.06a.75.75 0 0 0 .53.24H11l-1.5 5.2L19 8.2a.75.75 0 0 0-.53-.7h-6.6l1.63-4.5Z"/></svg>
                            {{ \App\Support\Funding::num(\App\Support\Funding::projectCount()) }} {{ __('issues.fund_projects') }}
                        </span>
                    @endif
                    @if (\App\Support\SiteSections::boolOn('fund', 'm_days') && ($fDaysLeft = \App\Support\Funding::daysLeft($fc)) !== null)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1.5 text-xs font-bold text-slate-700 ring-1 ring-amber-200">
                            <svg class="size-4 text-emerald-700" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4.5" width="18" height="17" rx="2"/><path stroke-linecap="round" d="M8 2.5v4M16 2.5v4M3 10h18"/></svg>
                            {{ __('issues.fund_days_left', ['count' => \App\Support\Funding::num($fDaysLeft)]) }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="flex shrink-0 flex-col gap-2 sm:flex-row lg:flex-col">
                @if ($fCan)
                    <a id="fund-donate-btn" href="{{ route('fund.show') }}#donate"
                       class="inline-flex items-center justify-center gap-1.5 rounded-full bg-emerald-600 px-6 py-3 font-extrabold text-white shadow-lg shadow-emerald-600/25 transition hover:bg-emerald-700 active:scale-[.98]">
                        {{ __('issues.fund_donate') }}
                    </a>
                @else
                    <span class="inline-flex items-center justify-center rounded-full bg-emerald-600/10 px-6 py-3 font-extrabold text-emerald-700">{{ __('issues.fund_goal_met') }}</span>
                @endif
                <a href="{{ route('fund.show') }}" class="text-center text-sm font-bold text-amber-800 underline-offset-2 hover:underline">{{ __('issues.fund_details') }}</a>
                <p class="flex items-center justify-center gap-1 text-[11px] font-semibold text-emerald-800/80">
                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4.5 6v5.25c0 4.7 3.2 8.28 7.5 9.75 4.3-1.47 7.5-5.05 7.5-9.75V6L12 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m9.5 11.75 1.75 1.75 3.25-3.5"/></svg>
                    {{ \App\Support\SiteSections::txt('fund', 'note', __('issues.fund_secure_note')) }}
                </p>
            </div>
        </div>
    </div>
</section>
@endif
