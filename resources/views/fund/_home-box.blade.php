@php $fc = \App\Support\Funding::active(); @endphp
@if ($fc)
@php
    $fRaised = \App\Support\Funding::raised($fc);
    $fPct = \App\Support\Funding::percent($fRaised, $fc->goal_amount);
    $fCan = \App\Support\Funding::canDonate($fc, $fRaised);
@endphp
<section id="fund-box" style="background:#fff7ed;border-top:1px solid #fed7aa;border-bottom:1px solid #fed7aa;">
    <div class="mx-auto max-w-6xl px-4 py-4">
        <h2 style="font-size:20px;font-weight:700;color:#0f172a;">{{ $fc->title }}</h2>
        @if ($fc->description)
            <p style="margin-top:4px;font-size:14px;color:#475569;">{{ \Illuminate\Support\Str::limit($fc->description, 180) }}</p>
        @endif
        <div style="margin-top:10px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:6px;font-size:15px;color:#0f172a;">
            <span><strong>&#2547;{{ \App\Support\Funding::num($fRaised) }}</strong> {{ __('issues.fund_raised') }}</span>
            <span>{{ __('issues.fund_goal') }}: &#2547;{{ \App\Support\Funding::num($fc->goal_amount) }}</span>
        </div>
        <div style="margin-top:6px;height:12px;background:#fde68a;border-radius:999px;overflow:hidden;">
            <div style="height:100%;width:{{ $fPct }}%;background:#16a34a;"></div>
        </div>
        <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
            @if ($fCan)
                <a id="fund-donate-btn" href="{{ route('fund.show') }}#donate" style="display:inline-block;padding:10px 22px;border-radius:999px;background:#16a34a;color:#fff;font-weight:700;font-size:16px;text-decoration:none;">{{ __('issues.fund_donate') }}</a>
            @else
                <span style="font-weight:700;color:#166534;">{{ __('issues.fund_goal_met') }}</span>
            @endif
            <a href="{{ route('fund.show') }}" style="font-size:14px;font-weight:600;color:#9a3412;text-decoration:underline;">{{ __('issues.fund_details') }}</a>
        </div>
    </div>
</section>
@endif
