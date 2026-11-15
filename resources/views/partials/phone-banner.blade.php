@auth
@php $u = auth()->user(); @endphp
@if ($u->phone === null && ! session('acct_phone_banner_dismissed'))
    <div style="background:#eff6ff;border-bottom:1px solid #bfdbfe;">
        <div style="max-width:1320px;margin-inline:auto;padding:8px 16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span style="font-size:14px;color:#1e40af;">{{ __('acctphone.banner_add') }}</span>
            <a href="{{ route('account.phone') }}" style="font-size:13px;font-weight:600;color:#1d4ed8;text-decoration:underline;">{{ __('acctphone.banner_add_cta') }}</a>
            <form method="POST" action="{{ route('account.phone.dismiss') }}" style="margin-inline-start:auto;">
                @csrf
                <button type="submit" aria-label="{{ __('acctphone.dismiss') }}" style="border:0;background:none;color:#64748b;font-size:16px;line-height:1;cursor:pointer;padding:2px 6px;">&times;</button>
            </form>
        </div>
    </div>
@elseif ($u->phone !== null && $u->phone_verified_at === null && ! session('acct_phone_banner_dismissed'))
    <div style="background:#fffbeb;border-bottom:1px solid #fde68a;">
        <div style="max-width:1320px;margin-inline:auto;padding:8px 16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span style="font-size:14px;color:#92400e;">{{ __('acctphone.banner_verify') }}</span>
            <a href="{{ route('account.phone') }}" style="font-size:13px;font-weight:600;color:#b45309;text-decoration:underline;">{{ __('acctphone.banner_verify_cta') }}</a>
            <form method="POST" action="{{ route('account.phone.dismiss') }}" style="margin-inline-start:auto;">
                @csrf
                <button type="submit" aria-label="{{ __('acctphone.dismiss') }}" style="border:0;background:none;color:#64748b;font-size:16px;line-height:1;cursor:pointer;padding:2px 6px;">&times;</button>
            </form>
        </div>
    </div>
@endif
@endauth
