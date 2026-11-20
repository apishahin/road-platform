@extends('layouts.app')

@section('title', __('login.title'))

@php
    $loginDesign = \App\Support\LoginSettings::design();
    $loginLogo = \App\Support\AppearanceSettings::logoUrl();
    $loginSiteName = \App\Support\AppearanceSettings::siteName();
    $loginTagline = \App\Support\AppearanceSettings::tagline();
    $loginMark = mb_substr($loginSiteName, 0, 1);
@endphp

@push('head')
<style>
/* Public login only: three admin-selectable layouts, responsive without any external asset. */
.login-stage{min-height:calc(100vh - 145px);display:grid;place-items:center;padding:36px 16px 52px;background:radial-gradient(circle at 15% 10%,#d9f9e9 0,transparent 27%),radial-gradient(circle at 88% 92%,#e0f2fe 0,transparent 24%),#f8fafc;}
.login-shell{width:min(100%,860px);}
.login-card{overflow:hidden;border:1px solid #dbe7e1;background:#fff;box-shadow:0 20px 55px rgba(16,50,36,.10);}
.login-card-header{padding:26px 28px 22px;text-align:center;border-bottom:1px solid #d1fae5;background:linear-gradient(135deg,#ecfdf5,#f8fffb 58%,#fff);}
.login-brand{display:flex;align-items:center;justify-content:center;gap:11px;}
.login-brand-logo{display:block;max-height:52px;width:auto;max-width:210px;object-fit:contain;}
.login-brand-mark{display:grid;place-items:center;width:50px;height:50px;border-radius:16px;background:#047857;color:#fff;font-size:22px;font-weight:800;box-shadow:0 7px 14px rgba(4,120,87,.20);}
.login-brand-name{font-size:16px;font-weight:800;line-height:1.25;color:#102b20;}
.login-brand-tagline{margin-top:2px;font-size:12px;line-height:1.45;color:#527062;}
.login-card-header h1{margin:15px 0 0;font-size:25px;font-weight:800;line-height:1.2;color:#0f172a;}
.login-card-header>p{margin:6px auto 0;max-width:360px;font-size:14px;line-height:1.65;color:#526478;}
.login-methods{padding:24px 28px 26px;}
.login-design-soft .login-card{max-width:460px;margin:auto;border-radius:20px;}
.login-design-soft .login-card-header .login-brand-name,.login-design-soft .login-card-header .login-brand-tagline{display:none;}
.login-design-split .login-shell{display:grid;grid-template-columns:minmax(280px,.85fr) minmax(400px,1fr);border-radius:24px;overflow:hidden;box-shadow:0 20px 55px rgba(16,50,36,.13);}
.login-design-split .login-card{border:0;border-radius:0;box-shadow:none;}
.login-aside{display:flex;flex-direction:column;justify-content:center;min-height:570px;padding:42px;background:linear-gradient(150deg,#064e3b,#047857 58%,#0f766e);color:#fff;}
.login-aside .login-brand{justify-content:flex-start;}
.login-aside .login-brand-mark{background:#fff;color:#047857;box-shadow:none;}
.login-aside .login-brand-name{color:#fff;font-size:22px;}
.login-aside .login-brand-tagline{color:#d1fae5;}
.login-aside-title{margin-top:48px;font-size:28px;font-weight:800;line-height:1.35;}
.login-aside-copy{margin-top:12px;font-size:14px;line-height:1.75;color:#d1fae5;}
.login-aside-points{display:grid;gap:11px;margin:30px 0 0;padding:0;list-style:none;font-size:13px;font-weight:600;color:#ecfdf5;}
.login-aside-points li{display:flex;gap:9px;align-items:center;}.login-aside-points b{display:grid;place-items:center;width:20px;height:20px;border-radius:99px;background:rgba(255,255,255,.18);font-size:12px;}
.login-design-split .login-card-header .login-brand{display:none;}
.login-design-split .login-card-header{text-align:left;background:#fff;border-bottom:0;padding-bottom:10px;}
.login-design-split .login-card-header>p{margin-left:0;}
.login-design-minimal{background:#fff;align-items:start;padding-top:48px;}.login-design-minimal .login-shell{max-width:480px;}.login-design-minimal .login-card{border-radius:14px;box-shadow:0 8px 28px rgba(15,23,42,.08);}.login-design-minimal .login-card-header{padding:24px 28px 0;text-align:left;border:0;background:#fff;}.login-design-minimal .login-card-header .login-brand{justify-content:flex-start;}.login-design-minimal .login-brand-logo{max-height:40px;max-width:170px;}.login-design-minimal .login-brand-mark{width:40px;height:40px;border-radius:11px;font-size:18px;background:#0f172a;}.login-design-minimal .login-card-header h1{font-size:23px;margin-top:24px;}.login-design-minimal .login-card-header>p{margin-left:0;}.login-design-minimal .login-methods{padding-top:20px;}
@media (max-width:720px){.login-stage{min-height:0;padding:22px 14px 34px;}.login-card-header,.login-methods{padding-left:20px;padding-right:20px;}.login-design-split .login-shell{display:block;border-radius:18px;box-shadow:0 12px 32px rgba(16,50,36,.10);}.login-design-split .login-aside{display:none;}.login-design-split .login-card{border-radius:18px;}.login-design-split .login-card-header .login-brand{display:flex;}.login-design-split .login-card-header{text-align:center;padding-bottom:20px;border-bottom:1px solid #d1fae5;background:linear-gradient(135deg,#ecfdf5,#fff);}.login-design-split .login-card-header>p{margin-left:auto;}.login-design-minimal{padding-top:22px;}.login-design-minimal .login-card-header{padding-left:20px;padding-right:20px;}}
</style>
@endpush

@section('content')
<div class="login-stage login-design-{{ $loginDesign }}" data-login-design="{{ $loginDesign }}">
    <div class="login-shell">
        @if ($loginDesign === 'split')
            <aside class="login-aside">
                <div class="login-brand">
                    @if ($loginLogo)
                        <img src="{{ $loginLogo }}" class="login-brand-logo" alt="{{ $loginSiteName }}">
                    @else
                        <span class="login-brand-mark" aria-hidden="true">{{ $loginMark }}</span>
                    @endif
                    <div>
                        <div class="login-brand-name">{{ $loginSiteName }}</div>
                        @if ($loginTagline !== '')<div class="login-brand-tagline">{{ $loginTagline }}</div>@endif
                    </div>
                </div>
                <h2 class="login-aside-title">{{ __('login.split_title') }}</h2>
                <p class="login-aside-copy">{{ __('login.split_copy') }}</p>
                <ul class="login-aside-points" aria-hidden="true">
                    <li><b>✓</b>{{ __('login.split_point_one') }}</li>
                    <li><b>✓</b>{{ __('login.split_point_two') }}</li>
                    <li><b>✓</b>{{ __('login.split_point_three') }}</li>
                </ul>
            </aside>
        @endif

        <section class="login-card">
            <header class="login-card-header">
                <div class="login-brand">
                    @if ($loginLogo)
                        <img src="{{ $loginLogo }}" class="login-brand-logo" alt="{{ $loginSiteName }}">
                    @else
                        <span class="login-brand-mark login-brand-fallback" aria-label="{{ $loginSiteName }}">{{ $loginMark }}</span>
                    @endif
                    @if ($loginDesign === 'minimal')
                        <div>
                            <div class="login-brand-name">{{ $loginSiteName }}</div>
                            @if ($loginTagline !== '')<div class="login-brand-tagline">{{ $loginTagline }}</div>@endif
                        </div>
                    @endif
                </div>
                <h1>{{ __('login.title') }}</h1>
                <p>{{ __('login.start_intro') }}</p>
            </header>

            <div class="login-methods">
                @include('auth._login-methods')
            </div>
        </section>
    </div>
</div>
@endsection
