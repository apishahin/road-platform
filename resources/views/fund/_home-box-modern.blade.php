{{-- Fund strip (reference port): overlaps the hero bottom edge. Self-computed from
     the active funding campaign; per-metric toggles still apply (admin → Homepage & site). --}}
@php
    $fc = \App\Support\Funding::active();
    $S  = \App\Support\SiteSections::class;
@endphp
@if ($fc)
@php
    $fRaised = \App\Support\Funding::raised($fc);
    $fPct    = \App\Support\Funding::percent($fRaised, $fc->goal_amount);
    $fCan    = \App\Support\Funding::canDonate($fc, $fRaised);
    $fDays   = \App\Support\Funding::daysLeft($fc);
@endphp
<div class="rp-wrap fund-wrap">
    <section class="fund-card" id="fund-box">
        <div class="fund-main">
            <div class="fund-icon" aria-hidden="true">
                <svg style="width:26px;height:26px;" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21s-7.5-4.7-9.7-9A5.4 5.4 0 0 1 12 6.5 5.4 5.4 0 0 1 21.7 12C19.5 16.3 12 21 12 21Z"/></svg>
            </div>
            <div>
                <h2>{{ $fc->title }}</h2>
                @if ($fc->description)
                    <p>{{ \Illuminate\Support\Str::limit($fc->description, 90) }}</p>
                @else
                    <p>{{ __('issues.fund_tagline_default') }}</p>
                @endif
                <div class="progress rp-fund-bar" role="progressbar" aria-valuenow="{{ $fPct }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $fPct }}%"></span></div>
                <div class="progress-meta">
                    <span>&#2547; {{ \App\Support\Funding::num($fRaised) }} / &#2547; {{ \App\Support\Funding::num($fc->goal_amount) }}</span>
                    <span>{{ \App\Support\Funding::num($fPct) }}% {{ __('issues.fund_done') }}</span>
                </div>
            </div>
        </div>
        <div class="fund-stats">
            @if ($S::boolOn('fund', 'm_donors'))
                <div class="fund-stat"><b>{{ \App\Support\Funding::num(\App\Support\Funding::donorCount($fc)) }}</b><small>{{ __('issues.fund_donors') }}</small></div>
            @endif
            @if ($S::boolOn('fund', 'm_projects'))
                <div class="fund-stat"><b>{{ \App\Support\Funding::num(\App\Support\Funding::projectCount()) }}</b><small>{{ __('issues.fund_projects') }}</small></div>
            @endif
            @if ($S::boolOn('fund', 'm_days'))
                <div class="fund-stat"><b>{{ $fDays !== null ? \App\Support\Funding::num($fDays) : '—' }}</b><small>{{ __('issues.fund_days_short') }}</small></div>
            @endif
        </div>
        <div class="fund-cta">
            @if ($fCan)
                <a id="fund-donate-btn" href="{{ route('fund.show') }}#donate" class="btn btn-primary">{{ __('issues.fund_donate') }} →</a>
            @else
                <span class="goal-met">{{ __('issues.fund_goal_met') }}</span>
            @endif
            <span class="secure">
                <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.6-3 8.6-7 10-4-1.4-7-5.4-7-10V6l7-3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="m9.5 12 2 2 3.5-4"/></svg>
                {{ $S::txt('fund', 'note', __('issues.fund_secure_note')) }}
            </span>
        </div>
    </section>
</div>
@endif
