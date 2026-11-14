@extends('layouts.app')

@section('title', __('issues.fund_admin_title'))

@section('content')
@php
    $F = \App\Support\Funding::class;
    $inp = 'width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:15px;background:#fff;';
    $btn = 'display:inline-block;padding:9px 16px;border-radius:8px;background:#0f172a;color:#fff;font-weight:600;font-size:14px;border:0;cursor:pointer;';
    $card = 'background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-top:16px;';
    $lbl = 'display:block;font-size:14px;font-weight:600;color:#334155;margin-bottom:4px;';
@endphp
<div class="mx-auto max-w-4xl px-4 py-6">
    <h1 style="font-size:22px;font-weight:700;color:#0f172a;">{{ __('issues.fund_admin_title') }}</h1>

    @if (session('status'))
        <div style="margin-top:12px;padding:10px 12px;border-radius:8px;background:#dcfce7;color:#166534;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div style="margin-top:12px;padding:10px 12px;border-radius:8px;background:#fee2e2;color:#991b1b;">
            @foreach ($errors->all() as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <div style="margin-top:14px;display:flex;flex-wrap:wrap;gap:8px;">
        @foreach ($campaigns as $c)
            @php $on = $edit && $edit->id === $c->id; @endphp
            <a href="{{ route('admin.funding.index', ['c' => $c->id]) }}" style="padding:6px 12px;border-radius:999px;font-size:14px;text-decoration:none;border:1px solid {{ $on ? '#0f172a' : '#cbd5e1' }};background:{{ $on ? '#0f172a' : '#fff' }};color:{{ $on ? '#fff' : '#334155' }};">{{ $c->title }}@if ($c->is_active) ({{ __('issues.fund_active_badge') }})@endif</a>
        @endforeach
        <a href="{{ route('admin.funding.index', ['c' => 'new']) }}" style="padding:6px 12px;border-radius:999px;font-size:14px;text-decoration:none;border:1px dashed #64748b;color:#334155;">+ {{ __('issues.fund_new') }}</a>
    </div>

    {{-- Campaign form --}}
    <form method="POST" action="{{ route('admin.funding.save') }}" style="{{ $card }}">
        @csrf
        @if ($edit)
            <input type="hidden" name="id" value="{{ $edit->id }}">
        @endif
        <label style="{{ $lbl }}">{{ __('issues.fund_c_title') }}</label>
        <input name="title" value="{{ old('title', $edit->title ?? '') }}" maxlength="200" required style="{{ $inp }}">

        <label style="{{ $lbl }};margin-top:12px;">{{ __('issues.fund_c_desc') }}</label>
        <textarea name="description" rows="3" style="{{ $inp }}">{{ old('description', $edit->description ?? '') }}</textarea>

        <label style="{{ $lbl }};margin-top:12px;">{{ __('issues.fund_c_goal') }}</label>
        <input type="number" name="goal_amount" min="1" value="{{ old('goal_amount', $edit->goal_amount ?? '') }}" required style="{{ $inp }}">

        <div style="margin-top:12px;display:flex;flex-direction:column;gap:6px;">
            <label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $edit->is_active ?? false))> {{ __('issues.fund_c_active') }}</label>
            <small style="color:#64748b;">{{ __('issues.fund_one_active_hint') }}</small>
            <label><input type="checkbox" name="show_donors" value="1" @checked(old('show_donors', $edit->show_donors ?? false))> {{ __('issues.fund_c_show_donors') }}</label>
        </div>

        <h3 style="margin-top:16px;font-weight:700;color:#0f172a;">{{ __('issues.fund_heads') }}</h3>
        @php
            $hrows = $edit ? $edit->heads->map(fn ($h) => ['id' => $h->id, 'name' => $h->name, 'amount' => $h->amount])->all() : [];
            for ($i = 0; $i < 3; $i++) { $hrows[] = ['id' => '', 'name' => '', 'amount' => '']; }
        @endphp
        @foreach ($hrows as $i => $h)
            <div style="display:flex;gap:8px;margin-top:6px;">
                <input type="hidden" name="heads[{{ $i }}][id]" value="{{ $h['id'] ?? '' }}">
                <input name="heads[{{ $i }}][name]" value="{{ $h['name'] }}" placeholder="{{ __('issues.fund_head_name') }}" maxlength="150" style="{{ $inp }};flex:2;">
                <input type="number" min="0" name="heads[{{ $i }}][amount]" value="{{ $h['amount'] }}" placeholder="{{ __('issues.fund_head_amount') }}" style="{{ $inp }};flex:1;">
            </div>
        @endforeach
        @if ($edit)
            <p style="margin-top:6px;font-size:13px;color:#64748b;">{{ __('issues.fund_heads_total') }}: &#2547;{{ $F::num($edit->heads->sum('amount')) }}</p>
        @endif

        <button type="submit" style="{{ $btn }};margin-top:14px;">{{ __('issues.fund_save') }}</button>
    </form>

    @if ($edit)
        <div style="{{ $card }}">
            <strong>&#2547;{{ $F::num($raised) }}</strong> {{ __('issues.fund_raised') }} / {{ __('issues.fund_goal') }} &#2547;{{ $F::num($edit->goal_amount) }}
        </div>

        {{-- Manual donation --}}
        <form method="POST" action="{{ route('admin.funding.manual') }}" style="{{ $card }}">
            @csrf
            <input type="hidden" name="campaign_id" value="{{ $edit->id }}">
            <h3 style="font-weight:700;color:#0f172a;">{{ __('issues.fund_add_manual') }}</h3>
            <label style="{{ $lbl }};margin-top:8px;">{{ __('issues.fund_d_amount') }}</label>
            <input type="number" name="amount" min="10" required style="{{ $inp }}">
            <label style="{{ $lbl }};margin-top:8px;">{{ __('issues.fund_d_name') }}</label>
            <input name="donor_name" maxlength="120" style="{{ $inp }}">
            <div style="margin-top:8px;display:flex;flex-direction:column;gap:4px;">
                <label><input type="checkbox" name="show_name" value="1"> {{ __('issues.fund_d_show_name') }}</label>
                <label><input type="checkbox" name="hide_amount" value="1"> {{ __('issues.fund_d_hide_amount') }}</label>
            </div>
            <label style="{{ $lbl }};margin-top:8px;">{{ __('issues.fund_d_date') }}</label>
            <input type="date" name="paid_at" style="{{ $inp }}">
            <label style="{{ $lbl }};margin-top:8px;">{{ __('issues.fund_d_note') }}</label>
            <input name="note" maxlength="1000" style="{{ $inp }}">
            <button type="submit" style="{{ $btn }};margin-top:12px;">{{ __('issues.fund_save') }}</button>
        </form>

        {{-- Expenses --}}
        <form method="POST" action="{{ route('admin.funding.expense.store') }}" enctype="multipart/form-data" style="{{ $card }}">
            @csrf
            <input type="hidden" name="campaign_id" value="{{ $edit->id }}">
            <h3 style="font-weight:700;color:#0f172a;">{{ __('issues.fund_add_expense') }}</h3>
            <p style="margin-top:6px;padding:8px 10px;border-radius:8px;background:#fff7ed;color:#9a3412;font-size:13px;">{{ __('issues.fund_voucher_warn') }}</p>
            <label style="{{ $lbl }};margin-top:8px;">{{ __('issues.fund_e_head') }}</label>
            <select name="head_id" style="{{ $inp }}">
                <option value="">{{ __('issues.fund_head_other') }}</option>
                @foreach ($edit->heads as $eh)
                    <option value="{{ $eh->id }}">{{ $eh->name }}</option>
                @endforeach
            </select>
            <label style="{{ $lbl }};margin-top:8px;">{{ __('issues.fund_e_amount') }}</label>
            <input type="number" name="amount" min="1" required style="{{ $inp }}">
            <label style="{{ $lbl }};margin-top:8px;">{{ __('issues.fund_e_date') }}</label>
            <input type="date" name="spent_on" required style="{{ $inp }}">
            <label style="{{ $lbl }};margin-top:8px;">{{ __('issues.fund_e_desc') }}</label>
            <input name="description" maxlength="500" required style="{{ $inp }}">
            <label style="{{ $lbl }};margin-top:8px;">{{ __('issues.fund_e_voucher') }}</label>
            <input type="file" name="voucher" accept=".jpg,.jpeg,.png,.pdf,image/*,application/pdf" style="{{ $inp }}">
            <button type="submit" style="{{ $btn }};margin-top:12px;">{{ __('issues.fund_save') }}</button>
        </form>

        <div style="{{ $card }}">
            <h3 style="font-weight:700;color:#0f172a;">{{ __('issues.fund_expenses') }}</h3>
            @foreach ($expenses as $x)
                <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:6px;padding:8px 0;border-top:1px solid #f1f5f9;font-size:14px;">
                    <span>{{ $x->spent_on?->format('d/m/Y') }} &middot; {{ $x->description }}</span>
                    <span>
                        &#2547;{{ $F::num($x->amount) }}
                        @if ($x->voucherUrl())
                            &middot; <a href="{{ $x->voucherUrl() }}" target="_blank" rel="noopener" style="color:#1d4ed8;text-decoration:underline;">{{ __('issues.fund_voucher_view') }}</a>
                        @endif
                        <form method="POST" action="{{ route('admin.funding.expense.delete', $x->id) }}" style="display:inline;" onsubmit="return confirm('{{ __('issues.fund_delete') }}?')">
                            @csrf
                            <button type="submit" style="margin-left:6px;padding:2px 8px;border-radius:6px;border:1px solid #fca5a5;background:#fff;color:#b91c1c;font-size:12px;cursor:pointer;">{{ __('issues.fund_delete') }}</button>
                        </form>
                    </span>
                </div>
            @endforeach
        </div>

        {{-- Donations list --}}
        <div style="{{ $card }}">
            <h3 style="font-weight:700;color:#0f172a;">{{ __('issues.fund_donations') }}</h3>
            @foreach ($donations as $d)
                <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:6px;padding:8px 0;border-top:1px solid #f1f5f9;font-size:14px;">
                    <span>{{ $d->paid_at?->format('d/m/Y') }} &middot; {{ $d->donor_name ?? '—' }}@if ($d->donor_contact) ({{ $d->donor_contact }})@endif</span>
                    <span>
                        &#2547;{{ $F::num($d->amount) }} &middot; {{ $d->is_manual ? __('issues.fund_manual') : $d->gateway }} &middot; {{ __('issues.fund_st_' . $d->status) }}
                        @if ($d->is_manual && $d->status === 'paid')
                            <form method="POST" action="{{ route('admin.funding.void', $d->id) }}" style="display:inline;" onsubmit="return confirm('{{ __('issues.fund_void') }}?')">
                                @csrf
                                <button type="submit" style="margin-left:6px;padding:2px 8px;border-radius:6px;border:1px solid #fca5a5;background:#fff;color:#b91c1c;font-size:12px;cursor:pointer;">{{ __('issues.fund_void') }}</button>
                            </form>
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
