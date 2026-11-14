@extends('layouts.app')

@section('title', __('issues.fund_page_title'))

@section('content')
@php
    $F = \App\Support\Funding::class;
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
        {{-- ============ HERO: progress + donate, together ============ --}}
        <div style="margin-top:16px;background:linear-gradient(135deg,#f0fdf4 0%,#ffffff 55%);border:1px solid #bbf7d0;border-radius:16px;padding:20px;box-shadow:0 1px 3px rgba(15,23,42,.06);">
            <h2 style="font-size:20px;font-weight:700;color:#0f172a;">{{ $campaign->title }}</h2>
            @if ($campaign->description)
                <p style="margin-top:6px;color:#475569;font-size:15px;">{!! nl2br(e($campaign->description)) !!}</p>
            @endif

            <div style="margin-top:14px;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                <div>
                    <div style="font-size:28px;font-weight:800;color:#166534;line-height:1;">&#2547;{{ $F::num($raised) }}</div>
                    <div style="margin-top:2px;font-size:13px;color:#64748b;">{{ __('issues.fund_raised') }} &middot; {{ __('issues.fund_goal') }} &#2547;{{ $F::num($campaign->goal_amount) }}</div>
                </div>
                <span style="padding:4px 12px;border-radius:999px;background:#dcfce7;color:#166534;font-weight:800;font-size:15px;">{{ $F::num($percent) }}%</span>
            </div>
            <div style="margin-top:8px;height:14px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
                <div style="height:100%;width:{{ $percent }}%;background:linear-gradient(90deg,#22c55e,#16a34a);border-radius:999px;"></div>
            </div>

            <div id="donate" style="margin-top:18px;">
                @if ($canDonate)
                    @if (\App\Support\PaySettings::ready())
                        <form method="POST" action="{{ route('fund.pay.store') }}" style="background:#ffffff;border:1px solid #bbf7d0;border-radius:12px;padding:16px;">
                            @csrf
                            <h3 style="font-size:16px;font-weight:700;color:#166534;">{{ __('issues.fund_pay_title') }}</h3>
                            <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;">
                                <div style="flex:1 1 130px;">
                                    <label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:4px;" for="donate-amount">{{ __('issues.fund_pay_amount') }}</label>
                                    <input id="donate-amount" type="number" name="amount" min="{{ \App\Support\PaySettings::minAmount() }}" max="1000000" step="1" required value="{{ old('amount', 100) }}" style="width:100%;padding:10px 12px;border:1px solid #86efac;border-radius:8px;font-size:17px;font-weight:700;color:#166534;background:#fff;">
                                </div>
                                <div style="flex:2 1 180px;">
                                    <label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:4px;" for="donate-name">{{ __('issues.fund_pay_name') }}</label>
                                    <input id="donate-name" name="donor_name" maxlength="120" value="{{ old('donor_name') }}" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:15px;background:#fff;">
                                </div>
                                <div style="flex:2 1 180px;">
                                    <label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:4px;" for="donate-contact">{{ __('issues.fund_pay_contact') }}</label>
                                    <input id="donate-contact" name="donor_contact" maxlength="150" value="{{ old('donor_contact') }}" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:15px;background:#fff;">
                                </div>
                            </div>
                            @if ($campaign->show_donors)
                                <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:14px;font-size:13px;color:#334155;">
                                    <label><input type="checkbox" name="show_name" value="1" checked> {{ __('issues.fund_pay_show_name') }}</label>
                                    <label><input type="checkbox" name="hide_amount" value="1"> {{ __('issues.fund_pay_hide_amount') }}</label>
                                </div>
                            @endif
                            <button type="submit" style="margin-top:12px;width:100%;padding:13px 16px;border-radius:10px;background:#16a34a;color:#fff;font-weight:700;font-size:17px;border:0;cursor:pointer;">{{ __('issues.fund_pay_btn') }}</button>
                            <p style="margin-top:8px;font-size:12px;color:#64748b;text-align:center;">{{ __('issues.fund_pay_hint') }}</p>
                        </form>
                    @else
                        <div style="padding:12px 14px;border-radius:10px;background:#fff;border:1px dashed #86efac;color:#166534;font-size:14px;font-weight:600;text-align:center;">
                            {{ __('issues.fund_online_soon') }}
                        </div>
                    @endif
                @else
                    <div style="padding:14px;border-radius:10px;background:#166534;color:#fff;font-weight:700;text-align:center;font-size:16px;">
                        {{ __('issues.fund_goal_met') }}
                    </div>
                @endif
            </div>
        </div>

        {{-- ============ TABS: heads | expenses | donors ============ --}}
        @php
            $tabs = [];
            if ($heads->count() > 0) { $tabs[] = 'heads'; }
            if ($expenses->count() > 0) { $tabs[] = 'expenses'; }
            if ($campaign->show_donors) { $tabs[] = 'donors'; }
            $firstTab = $tabs[0] ?? 'heads';
            $pillOn  = 'padding:8px 16px;border-radius:999px;font-size:14px;font-weight:700;border:1px solid #166534;background:#166534;color:#fff;cursor:pointer;white-space:nowrap;';
            $pillOff = 'padding:8px 16px;border-radius:999px;font-size:14px;font-weight:600;border:1px solid #cbd5e1;background:#fff;color:#334155;cursor:pointer;white-space:nowrap;';
        @endphp
        @if (count($tabs) > 0)
        <div style="margin-top:20px;" x-data="{ tab: '{{ $firstTab }}', on: @js($pillOn), off: @js($pillOff) }">
            <div role="tablist" style="display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;">
                @if (in_array('heads', $tabs))
                    <button type="button" role="tab" @click="tab='heads'" :style="tab==='heads' ? on : off">{{ __('issues.fund_heads') }}</button>
                @endif
                @if (in_array('expenses', $tabs))
                    <button type="button" role="tab" @click="tab='expenses'" :style="tab==='expenses' ? on : off">{{ __('issues.fund_expenses') }}</button>
                @endif
                @if (in_array('donors', $tabs))
                    <button type="button" role="tab" @click="tab='donors'" :style="tab==='donors' ? on : off">{{ __('issues.fund_donors') }}</button>
                @endif
            </div>

            @if (in_array('heads', $tabs))
                <section x-show="tab==='heads'" @if ($firstTab !== 'heads') x-cloak @endif style="margin-top:10px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;">
                    @foreach ($heads as $h)
                        @php $spt = (int) ($spentByHead[$h->id] ?? 0); @endphp
                        <div style="padding:12px 14px;border-top:{{ $loop->first ? '0' : '1px solid #f1f5f9' }};">
                            <div style="display:flex;justify-content:space-between;gap:8px;">
                                <span style="font-weight:600;color:#0f172a;">{{ $h->name }}</span>
                                <strong>&#2547;{{ $F::num($h->amount) }}</strong>
                            </div>
                            @if ($spentTotal > 0)
                                <div style="margin-top:3px;font-size:13px;color:#64748b;">
                                    {{ __('issues.fund_spent') }} &#2547;{{ $F::num($spt) }}
                                    @if ($h->amount > 0)
                                        &middot; {{ $F::num((int) min(100, floor($spt * 100 / $h->amount))) }}%
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endif

            @if (in_array('expenses', $tabs))
                <section x-show="tab==='expenses'" @if ($firstTab !== 'expenses') x-cloak @endif style="margin-top:10px;">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                        <span style="padding:6px 12px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:13px;font-weight:700;">{{ __('issues.fund_spent_total') }}: &#2547;{{ $F::num($spentTotal) }}</span>
                        <span style="padding:6px 12px;border-radius:999px;background:#dcfce7;color:#166534;font-size:13px;font-weight:700;">{{ __('issues.fund_balance') }}: &#2547;{{ $F::num($raised - $spentTotal) }}</span>
                    </div>
                    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;">
                        <h2 style="padding:12px 14px;font-size:15px;font-weight:700;color:#0f172a;border-bottom:1px solid #f1f5f9;">{{ __('issues.fund_expenses') }}</h2>
                        @foreach ($expenses as $x)
                            <div style="padding:10px 14px;border-top:1px solid #f1f5f9;">
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
                </section>
            @endif

            @if (in_array('donors', $tabs))
                <section x-show="tab==='donors'" @if ($firstTab !== 'donors') x-cloak @endif style="margin-top:10px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;">
                    @forelse ($donors as $d)
                        <div style="display:flex;justify-content:space-between;padding:10px 14px;border-top:{{ $loop->first ? '0' : '1px solid #f1f5f9' }};">
                            <span>{{ $d->donor_name }}</span>
                            <span style="color:#475569;">@if ($d->hide_amount) {{ __('issues.fund_amount_hidden') }} @else &#2547;{{ $F::num($d->amount) }} @endif</span>
                        </div>
                    @empty
                        <p style="padding:12px 14px;color:#64748b;">{{ __('issues.fund_donors_none') }}</p>
                    @endforelse
                </section>
            @endif
        </div>
        @endif
    @endif
</div>
@endsection
