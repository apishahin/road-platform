@extends('layouts.app')

@section('title', __('issues.fund_page_title'))

@section('content')
@php $F = \App\Support\Funding::class; @endphp
@php
    $expenses = $expenses ?? collect();
    $spentByHead = $expenses->groupBy('head_id')->map(fn ($g) => (int) $g->sum('amount'));
    $spentTotal = (int) $expenses->sum('amount');
@endphp
<div class="mx-auto max-w-3xl px-4 py-8">
    <h1 style="font-size:24px;font-weight:700;color:#0f172a;">{{ __('issues.fund_page_title') }}</h1>

    @if (session('fund_flash'))
        @php [$fType, $fMsg] = session('fund_flash'); @endphp
        <div style="margin-top:12px;padding:12px 14px;border-radius:10px;font-weight:600;border:1px solid {{ $fType === 'ok' ? '#bbf7d0' : ($fType === 'warn' ? '#fde68a' : '#fecaca') }};background:{{ $fType === 'ok' ? '#f0fdf4' : ($fType === 'warn' ? '#fffbeb' : '#fef2f2') }};color:{{ $fType === 'ok' ? '#166534' : ($fType === 'warn' ? '#92400e' : '#991b1b') }};">{{ $fMsg }}</div>
    @endif
    @if ($errors->any())
        <div style="margin-top:12px;padding:12px 14px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">
            @foreach ($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

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
                        <span>{{ $h->name }}</span><span><strong>&#2547;{{ $F::num($h->amount) }}</strong> <small style="color:#64748b;">&middot; {{ __('issues.fund_spent') }} &#2547;{{ $F::num($spentByHead[$h->id] ?? 0) }}</small></span>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($expenses->count() > 0)
            <h2 style="margin-top:24px;font-size:18px;font-weight:700;color:#0f172a;">{{ __('issues.fund_expenses') }}</h2>
            <p style="margin-top:4px;font-size:14px;color:#475569;">{{ __('issues.fund_spent_total') }}: <strong>&#2547;{{ $F::num($spentTotal) }}</strong> &middot; {{ __('issues.fund_balance') }}: <strong>&#2547;{{ $F::num($raised - $spentTotal) }}</strong></p>
            <div style="margin-top:8px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;">
                @foreach ($expenses as $x)
                    <div style="padding:10px 14px;border-top:{{ $loop->first ? '0' : '1px solid #f1f5f9' }};">
                        <div style="display:flex;justify-content:space-between;gap:8px;">
                            <span>{{ $x->description }}</span><strong>&#2547;{{ $F::num($x->amount) }}</strong>
                        </div>
                        <div style="margin-top:2px;font-size:13px;color:#64748b;">
                            {{ $x->spent_on?->format('d/m/Y') }}
                            @if ($x->head_id && $heads->firstWhere('id', $x->head_id))
                                &middot; {{ $heads->firstWhere('id', $x->head_id)->name }}
                            @endif
                            @if ($x->voucherUrl())
                                &middot; <a href="{{ $x->voucherUrl() }}" target="_blank" rel="noopener" style="color:#1d4ed8;text-decoration:underline;">{{ __('issues.fund_voucher_view') }}</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div id="donate" style="margin-top:24px;padding:16px;border-radius:12px;background:#f0fdf4;border:1px solid #bbf7d0;">
            @if ($canDonate)
                @if (\App\Support\PaySettings::ready())
                    <h2 style="font-size:17px;font-weight:700;color:#166534;">{{ __('issues.fund_pay_title') }}</h2>
                    <form method="POST" action="{{ route('fund.pay.store') }}" style="margin-top:10px;">
                        @csrf
                        <label style="display:block;font-size:14px;font-weight:600;color:#334155;margin-bottom:4px;" for="donate-amount">{{ __('issues.fund_pay_amount') }}</label>
                        <input id="donate-amount" type="number" name="amount" min="{{ \App\Support\PaySettings::minAmount() }}" max="1000000" step="1" required value="{{ old('amount', 100) }}" style="width:100%;padding:10px 12px;border:1px solid #86efac;border-radius:8px;font-size:16px;background:#fff;">
                        <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:10px;">
                            <div style="flex:1 1 160px;">
                                <label style="display:block;font-size:14px;font-weight:600;color:#334155;margin-bottom:4px;" for="donate-name">{{ __('issues.fund_pay_name') }}</label>
                                <input id="donate-name" name="donor_name" maxlength="120" value="{{ old('donor_name') }}" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:15px;background:#fff;">
                            </div>
                            <div style="flex:1 1 160px;">
                                <label style="display:block;font-size:14px;font-weight:600;color:#334155;margin-bottom:4px;" for="donate-contact">{{ __('issues.fund_pay_contact') }}</label>
                                <input id="donate-contact" name="donor_contact" maxlength="150" value="{{ old('donor_contact') }}" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:15px;background:#fff;">
                            </div>
                        </div>
                        @if ($campaign->show_donors)
                            <div style="margin-top:10px;display:flex;flex-direction:column;gap:4px;font-size:14px;color:#334155;">
                                <label><input type="checkbox" name="show_name" value="1" checked> {{ __('issues.fund_pay_show_name') }}</label>
                                <label><input type="checkbox" name="hide_amount" value="1"> {{ __('issues.fund_pay_hide_amount') }}</label>
                            </div>
                        @endif
                        <button type="submit" style="margin-top:12px;width:100%;padding:12px 16px;border-radius:10px;background:#16a34a;color:#fff;font-weight:700;font-size:17px;border:0;cursor:pointer;">{{ __('issues.fund_pay_btn') }}</button>
                        <p style="margin-top:8px;font-size:13px;color:#166534;">{{ __('issues.fund_pay_hint') }}</p>
                    </form>
                @else
                    <p style="font-weight:600;color:#166534;">{{ __('issues.fund_online_soon') }}</p>
                @endif
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
