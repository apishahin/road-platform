@extends('layouts.app')

@section('title', __('issues.fund_page_title'))

@section('content')
@php $F = \App\Support\Funding::class; @endphp
<div class="mx-auto max-w-3xl px-4 py-8">
    <h1 style="font-size:24px;font-weight:700;color:#0f172a;">{{ __('issues.fund_page_title') }}</h1>

    @if (! $campaign)
        <p style="margin-top:16px;color:#475569;">{{ __('issues.fund_none') }}</p>
    @else
        <div style="margin-top:16px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;">
            <h2 style="font-size:20px;font-weight:700;color:#0f172a;">{{ $campaign->title }}</h2>
            @if ($campaign->description)
                <p style="margin-top:6px;color:#475569;font-size:15px;">{!! nl2br(e($campaign->description)) !!}</p>
            @endif
            <div style="margin-top:12px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:6px;">
                <span><strong>&#2547;{{ $F::num($raised) }}</strong> {{ __('issues.fund_raised') }}</span>
                <span>{{ __('issues.fund_goal') }}: &#2547;{{ $F::num($campaign->goal_amount) }}</span>
            </div>
            <div style="margin-top:6px;height:12px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
                <div style="height:100%;width:{{ $percent }}%;background:#16a34a;"></div>
            </div>
        </div>

        @if ($heads->count() > 0)
            <h2 style="margin-top:24px;font-size:18px;font-weight:700;color:#0f172a;">{{ __('issues.fund_heads') }}</h2>
            <div style="margin-top:8px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;">
                @foreach ($heads as $h)
                    <div style="display:flex;justify-content:space-between;padding:10px 14px;border-top:{{ $loop->first ? '0' : '1px solid #f1f5f9' }};">
                        <span>{{ $h->name }}</span><strong>&#2547;{{ $F::num($h->amount) }}</strong>
                    </div>
                @endforeach
            </div>
        @endif

        <div id="donate" style="margin-top:24px;padding:16px;border-radius:12px;background:#f0fdf4;border:1px solid #bbf7d0;">
            @if ($canDonate)
                <p style="font-weight:600;color:#166534;">{{ __('issues.fund_online_soon') }}</p>
            @else
                <p style="font-weight:700;color:#166534;">{{ __('issues.fund_goal_met') }}</p>
            @endif
        </div>

        @if ($campaign->show_donors)
            <h2 style="margin-top:24px;font-size:18px;font-weight:700;color:#0f172a;">{{ __('issues.fund_donors') }}</h2>
            <div style="margin-top:8px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;">
                @forelse ($donors as $d)
                    <div style="display:flex;justify-content:space-between;padding:10px 14px;border-top:{{ $loop->first ? '0' : '1px solid #f1f5f9' }};">
                        <span>{{ $d->donor_name }}</span>
                        <span style="color:#475569;">@if ($d->hide_amount) {{ __('issues.fund_amount_hidden') }} @else &#2547;{{ $F::num($d->amount) }} @endif</span>
                    </div>
                @empty
                    <p style="padding:12px 14px;color:#64748b;">{{ __('issues.fund_donors_none') }}</p>
                @endforelse
            </div>
        @endif
    @endif
</div>
@endsection
