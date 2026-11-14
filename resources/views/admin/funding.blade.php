@extends('layouts.app')

@section('title', __('issues.fund_admin_title'))

@section('content')
@php
    $F = \App\Support\Funding::class;
    $gws = \App\Support\PaySettings::all();
    $provider = \App\Support\PaySettings::provider();
    $gwReady = \App\Support\PaySettings::ready();
    $expTotal = (int) $expenses->sum('amount');
    $paidDonations = $donations->where('status', 'paid');

    // ---- shared inline styles (file-preview safe, matches /fund page) ----
    $inp  = 'width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;';
    $lbl  = 'display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:4px;';
    $btn  = 'display:inline-block;padding:9px 18px;border-radius:8px;background:#0f172a;color:#fff;font-weight:700;font-size:14px;border:0;cursor:pointer;';
    $card = 'background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 20px;box-shadow:0 1px 3px rgba(15,23,42,.05);';
    $pillOn  = 'padding:7px 14px;border-radius:999px;font-size:13px;font-weight:700;border:1px solid #166534;background:#166534;color:#fff;cursor:pointer;';
    $pillOff = 'padding:7px 14px;border-radius:999px;font-size:13px;font-weight:700;border:1px solid #cbd5e1;background:#fff;color:#334155;cursor:pointer;';
    $stat = 'background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;box-shadow:0 1px 3px rgba(15,23,42,.05);';
    $del  = 'padding:4px 10px;border-radius:8px;border:1px solid #fca5a5;background:#fff;color:#b91c1c;font-size:12px;font-weight:700;cursor:pointer;';
@endphp

<div class="mx-auto max-w-4xl px-4 py-6" x-data="{ tab: '{{ request('tab', 'campaign') }}', on: @js($pillOn), off: @js($pillOff), prov: '{{ $provider }}' }">

    {{-- Header --}}
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
        <div>
            <h1 style="font-size:24px;font-weight:800;color:#0f172a;">{{ __('issues.fund_admin_title') }}</h1>
            <p style="margin-top:2px;font-size:14px;color:#64748b;">{{ __('issues.fund_admin_sub') }}</p>
        </div>
        <a href="{{ route('fund.show') }}" target="_blank" rel="noopener" style="padding:8px 14px;border-radius:10px;border:1px solid #a7f3d0;background:#f0fdf4;color:#166534;font-size:13px;font-weight:700;text-decoration:none;">{{ __('issues.fund_view_public') }} &nearr;</a>
    </div>

    {{-- flashes --}}
    @if (session('status'))
        <div style="margin-top:14px;padding:11px 14px;border-radius:10px;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;font-weight:600;font-size:14px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div style="margin-top:14px;padding:11px 14px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-size:14px;">
            @foreach ($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    {{-- Campaign selector pills --}}
    <div style="margin-top:16px;display:flex;flex-wrap:wrap;gap:8px;">
        @foreach ($campaigns as $c)
            @php $on = $edit && $edit->id === $c->id; @endphp
            <a href="{{ route('admin.funding.index', ['c' => $c->id]) }}" style="padding:7px 14px;border-radius:999px;font-size:13px;font-weight:700;text-decoration:none;border:1px solid {{ $on ? '#0f172a' : '#cbd5e1' }};background:{{ $on ? '#0f172a' : '#fff' }};color:{{ $on ? '#fff' : '#334155' }};">{{ $c->title }}@if ($c->is_active) <span style="opacity:.75;font-weight:600;">· {{ __('issues.fund_active_badge') }}</span>@endif</a>
        @endforeach
        <a href="{{ route('admin.funding.index', ['c' => 'new']) }}" style="padding:7px 14px;border-radius:999px;font-size:13px;font-weight:700;text-decoration:none;border:1px dashed #64748b;color:#334155;background:#fff;">+ {{ __('issues.fund_new') }}</a>
    </div>

    {{-- Stat cards --}}
    @if ($edit)
    <div style="margin-top:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;">
        <div style="{{ $stat }}">
            <div style="font-size:12px;font-weight:700;color:#64748b;">{{ __('issues.fund_stat_raised') }}</div>
            <div style="margin-top:4px;font-size:22px;font-weight:800;color:#166534;line-height:1;">&#2547;{{ $F::num($raised) }}</div>
        </div>
        <div style="{{ $stat }}">
            <div style="font-size:12px;font-weight:700;color:#64748b;">{{ __('issues.fund_stat_goal') }}</div>
            <div style="margin-top:4px;font-size:22px;font-weight:800;color:#0f172a;line-height:1;">&#2547;{{ $F::num($edit->goal_amount) }}</div>
        </div>
        <div style="{{ $stat }}">
            <div style="font-size:12px;font-weight:700;color:#64748b;">{{ __('issues.fund_stat_donors') }}</div>
            <div style="margin-top:4px;font-size:22px;font-weight:800;color:#1d4ed8;line-height:1;">{{ $F::num($paidDonations->count()) }}</div>
        </div>
        <div style="{{ $stat }}">
            <div style="font-size:12px;font-weight:700;color:#64748b;">{{ __('issues.fund_stat_expenses') }}</div>
            <div style="margin-top:4px;font-size:22px;font-weight:800;color:#b45309;line-height:1;">&#2547;{{ $F::num($expTotal) }}</div>
        </div>
    </div>
    @endif

    {{-- Tabs --}}
    <div style="margin-top:18px;display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;">
        <button type="button" role="tab" @click="tab='campaign'" :style="tab==='campaign' ? on : off">{{ __('issues.fund_tab_campaign') }}</button>
        <button type="button" role="tab" @click="tab='expenses'" :style="tab==='expenses' ? on : off">{{ __('issues.fund_tab_expenses') }}</button>
        <button type="button" role="tab" @click="tab='donations'" :style="tab==='donations' ? on : off">{{ __('issues.fund_tab_donations') }}</button>
        <button type="button" role="tab" @click="tab='gateway'" :style="tab==='gateway' ? on : off">{{ __('issues.fund_tab_gateway') }}</button>
        <span style="margin-left:auto;align-self:center;font-size:12px;font-weight:700;padding:5px 12px;border-radius:999px;background:{{ $gwReady ? '#dcfce7' : '#fee2e2' }};color:{{ $gwReady ? '#166534' : '#b91c1c' }};white-space:nowrap;">
            {{ $gwReady ? __('issues.fund_pay_enabled') : __('issues.fund_pay_disabled') }}@if ($gwReady) · {{ __('issues.fund_provider_' . $provider) }}@endif
        </span>
    </div>

    {{-- ============ TAB: CAMPAIGN ============ --}}
    <div x-show="tab==='campaign'" x-cloak style="margin-top:14px;display:flex;flex-direction:column;gap:16px;">
        <form method="POST" action="{{ route('admin.funding.save') }}" style="{{ $card }}">
            @csrf
            @if ($edit)
                <input type="hidden" name="id" value="{{ $edit->id }}">
            @endif
            <h3 style="font-size:16px;font-weight:800;color:#0f172a;margin-bottom:12px;">{{ $edit ? __('issues.fund_save_campaign') : __('issues.fund_new') }}</h3>

            <div style="display:grid;grid-template-columns:1fr;gap:12px;">
                <div>
                    <label style="{{ $lbl }}">{{ __('issues.fund_c_title') }}</label>
                    <input name="title" value="{{ old('title', $edit->title ?? '') }}" maxlength="200" required style="{{ $inp }}">
                </div>
                <div>
                    <label style="{{ $lbl }}">{{ __('issues.fund_c_desc') }}</label>
                    <textarea name="description" rows="3" style="{{ $inp }}">{{ old('description', $edit->description ?? '') }}</textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div>
                        <label style="{{ $lbl }}">{{ __('issues.fund_c_goal') }}</label>
                        <input type="number" name="goal_amount" min="1" value="{{ old('goal_amount', $edit->goal_amount ?? '') }}" required style="{{ $inp }}">
                    </div>
                    <div>
                        <label style="{{ $lbl }}">{{ __('issues.fund_c_ends_at') }}</label>
                        <input type="date" name="ends_at" value="{{ old('ends_at', $edit?->ends_at?->format('Y-m-d') ?? '') }}" style="{{ $inp }}">
                        <small style="color:#94a3b8;">{{ __('issues.fund_c_ends_hint') }}</small>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:6px;justify-content:center;padding-top:8px;">
                        <label style="font-size:13px;font-weight:600;color:#334155;"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $edit->is_active ?? false))> {{ __('issues.fund_c_active') }}</label>
                        <small style="color:#64748b;">{{ __('issues.fund_one_active_hint') }}</small>
                        <label style="font-size:13px;font-weight:600;color:#334155;"><input type="checkbox" name="show_donors" value="1" @checked(old('show_donors', $edit->show_donors ?? false))> {{ __('issues.fund_c_show_donors') }}</label>
                    </div>
                </div>

                <div>
                    <label style="{{ $lbl }}">{{ __('issues.fund_heads') }}</label>
                    @php
                        $hrows = $edit ? $edit->heads->map(fn ($h) => ['id' => $h->id, 'name' => $h->name, 'amount' => $h->amount])->all() : [];
                        for ($i = 0; $i < 3; $i++) { $hrows[] = ['id' => '', 'name' => '', 'amount' => '']; }
                    @endphp
                    <div id="headRows" style="display:flex;flex-direction:column;gap:6px;">
                        @foreach ($hrows as $i => $h)
                            <div class="head-row" style="display:flex;gap:8px;">
                                <input type="hidden" name="heads[{{ $i }}][id]" value="{{ $h['id'] ?? '' }}">
                                <input name="heads[{{ $i }}][name]" value="{{ $h['name'] }}" placeholder="{{ __('issues.fund_head_name') }}" maxlength="150" style="{{ $inp }};flex:2;">
                                <input type="number" min="0" name="heads[{{ $i }}][amount]" value="{{ $h['amount'] }}" placeholder="{{ __('issues.fund_head_amount') }}" style="{{ $inp }};flex:1;">
                            </div>
                        @endforeach
                    </div>
                    @if ($edit)
                        <p style="margin-top:6px;font-size:13px;color:#64748b;">{{ __('issues.fund_heads_total') }}: &#2547;{{ $F::num($edit->heads->sum('amount')) }}</p>
                    @endif
                </div>
            </div>
            <button type="submit" style="{{ $btn }};margin-top:14px;">{{ __('issues.fund_save') }}</button>
        </form>

        @if ($edit)
        <form method="POST" action="{{ route('admin.funding.manual') }}" style="{{ $card }}">
            @csrf
            <input type="hidden" name="campaign_id" value="{{ $edit->id }}">
            <h3 style="font-size:16px;font-weight:800;color:#0f172a;margin-bottom:12px;">{{ __('issues.fund_add_manual') }}</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label style="{{ $lbl }}">{{ __('issues.fund_d_amount') }}</label>
                    <input type="number" name="amount" min="10" required style="{{ $inp }}">
                </div>
                <div>
                    <label style="{{ $lbl }}">{{ __('issues.fund_d_name') }}</label>
                    <input name="donor_name" maxlength="120" style="{{ $inp }}">
                </div>
                <div>
                    <label style="{{ $lbl }}">{{ __('issues.fund_d_date') }}</label>
                    <input type="date" name="paid_at" style="{{ $inp }}">
                </div>
                <div>
                    <label style="{{ $lbl }}">{{ __('issues.fund_d_note') }}</label>
                    <input name="note" maxlength="1000" style="{{ $inp }}">
                </div>
            </div>
            <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:16px;font-size:13px;color:#334155;">
                <label style="font-weight:600;"><input type="checkbox" name="show_name" value="1"> {{ __('issues.fund_d_show_name') }}</label>
                <label style="font-weight:600;"><input type="checkbox" name="hide_amount" value="1"> {{ __('issues.fund_d_hide_amount') }}</label>
            </div>
            <button type="submit" style="{{ $btn }};margin-top:12px;background:#166534;">{{ __('issues.fund_save') }}</button>
        </form>
        @endif
    </div>

    {{-- ============ TAB: EXPENSES ============ --}}
    <div x-show="tab==='expenses'" x-cloak style="margin-top:14px;display:flex;flex-direction:column;gap:16px;">
        @if ($edit)
        <form method="POST" action="{{ route('admin.funding.expense.store') }}" enctype="multipart/form-data" style="{{ $card }}">
            @csrf
            <input type="hidden" name="campaign_id" value="{{ $edit->id }}">
            <h3 style="font-size:16px;font-weight:800;color:#0f172a;margin-bottom:6px;">{{ __('issues.fund_add_expense') }}</h3>
            <p style="margin-bottom:12px;padding:8px 10px;border-radius:8px;background:#fff7ed;color:#9a3412;font-size:13px;">{{ __('issues.fund_voucher_warn') }}</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label style="{{ $lbl }}">{{ __('issues.fund_e_head') }}</label>
                    <select name="head_id" style="{{ $inp }}">
                        <option value="">{{ __('issues.fund_head_other') }}</option>
                        @foreach ($edit->heads as $eh)
                            <option value="{{ $eh->id }}">{{ $eh->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="{{ $lbl }}">{{ __('issues.fund_e_amount') }}</label>
                    <input type="number" name="amount" min="1" required style="{{ $inp }}">
                </div>
                <div>
                    <label style="{{ $lbl }}">{{ __('issues.fund_e_date') }}</label>
                    <input type="date" name="spent_on" required style="{{ $inp }}">
                </div>
                <div>
                    <label style="{{ $lbl }}">{{ __('issues.fund_e_voucher') }}</label>
                    <input type="file" name="voucher" accept=".jpg,.jpeg,.png,.pdf,image/*,application/pdf" style="{{ $inp }};padding:7px;">
                </div>
                <div style="grid-column:1 / -1;">
                    <label style="{{ $lbl }}">{{ __('issues.fund_e_desc') }}</label>
                    <input name="description" maxlength="500" required style="{{ $inp }}">
                </div>
            </div>
            <button type="submit" style="{{ $btn }};margin-top:12px;background:#b45309;">{{ __('issues.fund_save') }}</button>
        </form>

        <div style="{{ $card }};padding:8px 20px;">
            <h3 style="font-size:16px;font-weight:800;color:#0f172a;padding:10px 0;">{{ __('issues.fund_tab_expenses') }}</h3>
            @forelse ($expenses as $x)
                <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:6px;padding:10px 0;border-top:1px solid #f1f5f9;font-size:14px;">
                    <span style="color:#334155;">{{ $x->spent_on?->format('d/m/Y') }} — {{ $x->description }}</span>
                    <span style="font-weight:700;color:#b45309;">
                        &#2547;{{ $F::num($x->amount) }}
                        @if ($x->voucherUrl())
                            <a href="{{ $x->voucherUrl() }}" target="_blank" rel="noopener" style="color:#1d4ed8;text-decoration:underline;font-weight:600;margin-left:6px;">{{ __('issues.fund_voucher_view') }}</a>
                        @endif
                        <form method="POST" action="{{ route('admin.funding.expense.delete', $x->id) }}" style="display:inline;" onsubmit="return confirm('{{ __('issues.fund_delete') }}?')">
                            @csrf
                            <button type="submit" style="{{ $del }};margin-left:6px;">{{ __('issues.fund_delete') }}</button>
                        </form>
                    </span>
                </div>
            @empty
                <p style="padding:14px 0 18px;color:#94a3b8;font-size:14px;">{{ __('issues.fund_no_expenses') }}</p>
            @endforelse
        </div>
        @else
            <div style="{{ $card }};color:#64748b;">{{ __('issues.fund_no_expenses') }}</div>
        @endif
    </div>

    {{-- ============ TAB: DONATIONS ============ --}}
    <div x-show="tab==='donations'" x-cloak style="margin-top:14px;">
        @if ($edit)
        <div style="{{ $card }};padding:8px 20px;">
            <h3 style="font-size:16px;font-weight:800;color:#0f172a;padding:10px 0;">{{ __('issues.fund_tab_donations') }}</h3>
            @forelse ($donations as $d)
                <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:6px;padding:10px 0;border-top:1px solid #f1f5f9;font-size:14px;">
                    <span style="color:#334155;">
                        {{ $d->paid_at?->format('d/m/Y') ?? '—' }} · {{ $d->donor_name ?? __('issues.fund_anon') }}@if ($d->donor_contact) <span style="color:#94a3b8;">({{ $d->donor_contact }})</span>@endif
                    </span>
                    <span>
                        <span style="font-weight:700;color:{{ $d->status === 'paid' ? '#166534' : '#94a3b8' }};">&#2547;{{ $F::num($d->amount) }}</span>
                        <span style="margin-left:6px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;{{ $d->is_manual ? 'background:#f1f5f9;color:#475569;' : 'background:#e0e7ff;color:#3730a3;' }}">{{ $d->is_manual ? __('issues.fund_manual') : __('issues.fund_provider_' . $d->gateway) }}</span>
                        <span style="margin-left:4px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;{{ $d->status === 'paid' ? 'background:#dcfce7;color:#166534;' : ($d->status === 'pending' ? 'background:#fef3c7;color:#92400e;' : 'background:#fee2e2;color:#b91c1c;') }}">{{ __('issues.fund_st_' . $d->status) }}</span>
                        @if ($d->is_manual && $d->status === 'paid')
                            <form method="POST" action="{{ route('admin.funding.void', $d->id) }}" style="display:inline;" onsubmit="return confirm('{{ __('issues.fund_void') }}?')">
                                @csrf
                                <button type="submit" style="{{ $del }};margin-left:6px;">{{ __('issues.fund_void') }}</button>
                            </form>
                        @endif
                    </span>
                </div>
            @empty
                <p style="padding:14px 0 18px;color:#94a3b8;font-size:14px;">{{ __('issues.fund_no_donations') }}</p>
            @endforelse
        </div>
        @else
            <div style="{{ $card }};color:#64748b;">{{ __('issues.fund_no_donations') }}</div>
        @endif
    </div>

    {{-- ============ TAB: GATEWAY ============ --}}
    <div x-show="tab==='gateway'" x-cloak style="margin-top:14px;display:flex;flex-direction:column;gap:16px;">
        <form method="POST" action="{{ route('admin.funding.gateway') }}" style="{{ $card }}">
            @csrf
            <h3 style="font-size:16px;font-weight:800;color:#0f172a;">{{ __('issues.fund_gw_title') }}</h3>
            <p style="margin-top:4px;font-size:13px;font-weight:700;color:{{ $gwReady ? '#166534' : '#b91c1c' }};">
                {{ $gwReady ? __('issues.fund_gw_status_ready') : __('issues.fund_gw_status_off') }}
            </p>

            <label style="{{ $lbl }};margin-top:12px;">{{ __('issues.fund_provider_label') }}</label>
            <select name="provider" x-model="prov" style="{{ $inp }};max-width:260px;">
                <option value="none">{{ __('issues.fund_provider_none') }}</option>
                <option value="uddoktapay">{{ __('issues.fund_provider_uddoktapay') }}</option>
                <option value="sslcommerz">{{ __('issues.fund_provider_sslcommerz') }}</option>
                <option value="bkash">{{ __('issues.fund_provider_bkash') }}</option>
            </select>

            {{-- UddoktaPay --}}
            <div x-show="prov==='uddoktapay'" x-cloak style="margin-top:14px;padding:14px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;">
                <strong style="font-size:14px;color:#0f172a;">UddoktaPay</strong>
                <div style="display:flex;align-items:center;gap:6px;margin-top:2px;">
                    <span style="width:8px;height:8px;border-radius:999px;background:#6366f1;"></span>
                    <span style="font-size:12px;color:#64748b;">{{ __('issues.fund_gw_hint') }}</span>
                </div>
                <label style="{{ $lbl }};margin-top:10px;">{{ __('issues.fund_gw_base') }}</label>
                <input name="up_base_url" value="{{ old('up_base_url', $gws['up_base_url'] ?? '') }}" placeholder="https://pay.your-domain.com" maxlength="200" style="{{ $inp }}">
                <label style="{{ $lbl }};margin-top:10px;">{{ __('issues.fund_gw_key') }}</label>
                <input name="up_api_key" value="{{ old('up_api_key', $gws['up_api_key'] ?? '') }}" maxlength="200" style="{{ $inp }}">
            </div>

            {{-- SSLCommerz --}}
            <div x-show="prov==='sslcommerz'" x-cloak style="margin-top:14px;padding:14px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <span style="width:8px;height:8px;border-radius:999px;background:#2563eb;"></span>
                    <strong style="font-size:14px;color:#0f172a;">SSLCommerz</strong>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;">
                    <div>
                        <label style="{{ $lbl }}">Store ID</label>
                        <input name="ssl_store_id" value="{{ old('ssl_store_id', $gws['ssl_store_id'] ?? '') }}" maxlength="100" style="{{ $inp }}">
                    </div>
                    <div>
                        <label style="{{ $lbl }}">Store Password</label>
                        <input type="password" name="ssl_store_passwd" value="{{ old('ssl_store_passwd', $gws['ssl_store_passwd'] ?? '') }}" maxlength="100" style="{{ $inp }}">
                    </div>
                </div>
                <label style="display:block;margin-top:10px;font-size:13px;font-weight:600;color:#334155;"><input type="checkbox" name="ssl_sandbox" value="1" @checked(old('ssl_sandbox', $gws['ssl_sandbox'] ?? true))> {{ __('issues.fund_sandbox_mode') }}</label>
                <p style="margin-top:6px;font-size:12px;color:#64748b;">{{ __('issues.fund_ssl_help') }}</p>
            </div>

            {{-- bKash --}}
            <div x-show="prov==='bkash'" x-cloak style="margin-top:14px;padding:14px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <span style="width:8px;height:8px;border-radius:999px;background:#db2777;"></span>
                    <strong style="font-size:14px;color:#0f172a;">bKash</strong>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;">
                    <div>
                        <label style="{{ $lbl }}">App Key</label>
                        <input name="bk_app_key" value="{{ old('bk_app_key', $gws['bk_app_key'] ?? '') }}" maxlength="150" style="{{ $inp }}">
                    </div>
                    <div>
                        <label style="{{ $lbl }}">App Secret</label>
                        <input type="password" name="bk_app_secret" value="{{ old('bk_app_secret', $gws['bk_app_secret'] ?? '') }}" maxlength="150" style="{{ $inp }}">
                    </div>
                    <div>
                        <label style="{{ $lbl }}">Username</label>
                        <input name="bk_username" value="{{ old('bk_username', $gws['bk_username'] ?? '') }}" maxlength="100" style="{{ $inp }}">
                    </div>
                    <div>
                        <label style="{{ $lbl }}">Password</label>
                        <input type="password" name="bk_password" value="{{ old('bk_password', $gws['bk_password'] ?? '') }}" maxlength="100" style="{{ $inp }}">
                    </div>
                </div>
                <label style="display:block;margin-top:10px;font-size:13px;font-weight:600;color:#334155;"><input type="checkbox" name="bk_sandbox" value="1" @checked(old('bk_sandbox', $gws['bk_sandbox'] ?? true))> {{ __('issues.fund_sandbox_mode') }}</label>
                <p style="margin-top:6px;font-size:12px;color:#64748b;">{{ __('issues.fund_bkash_help') }}</p>
            </div>

            <div x-show="prov!=='none'" x-cloak style="margin-top:14px;max-width:200px;">
                <label style="{{ $lbl }}">{{ __('issues.fund_gw_min') }}</label>
                <input type="number" name="min_amount" value="{{ old('min_amount', $gws['min_amount'] ?? 10) }}" min="1" style="{{ $inp }}">
            </div>

            <button type="submit" style="{{ $btn }};margin-top:14px;">{{ __('issues.fund_save') }}</button>
        </form>

        {{-- Endpoints reference --}}
        <div style="{{ $card }};background:#f8fafc;">
            <h3 style="font-size:14px;font-weight:800;color:#0f172a;">{{ __('issues.fund_endpoints_title') }}</h3>
            <div style="margin-top:10px;font-size:13px;color:#334155;display:flex;flex-direction:column;gap:6px;">
                <div><strong style="color:#2563eb;">SSLCommerz IPN:</strong> <code style="background:#e2e8f0;padding:2px 6px;border-radius:6px;font-size:12px;">{{ url('/api/fund/sslcommerz/ipn') }}</code></div>
                <div><strong style="color:#6366f1;">UddoktaPay Webhook:</strong> <code style="background:#e2e8f0;padding:2px 6px;border-radius:6px;font-size:12px;">{{ url('/api/fund/uddoktapay/webhook') }}</code></div>
            </div>
        </div>
    </div>
</div>
@endsection
