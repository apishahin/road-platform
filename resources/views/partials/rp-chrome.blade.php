{{-- Header / footer chrome: port of the approved reference (site-wide, top-level classes only). --}}
@php $th = \App\Support\SiteSections::theme(); @endphp
<style>
.rp-h{--green:#078a48;--green-deep:#003d25;--line:#e0eae4;--ink:#102b20;--body:#53645b;height:64px;background:#fff;border-bottom:1px solid var(--line);}
@media (min-width:1100px){ .rp-h{height:68px;} }
.rp-h .nav-wrap{height:100%;display:flex;align-items:center;justify-content:space-between;gap:16px;}
.rp-h .brand{display:flex;align-items:center;gap:10px;color:var(--ink);text-decoration:none;min-width:0;}
.rp-brand-mark{width:37px;height:37px;border-radius:50% 50% 50% 12px;background:linear-gradient(145deg,#8be34d,#078a48);display:grid;place-items:center;color:#fff;transform:rotate(-8deg);flex-shrink:0;font-weight:800;}
@media (min-width:1100px){ .rp-brand-mark{width:42px;height:42px;} }
.rp-brand-mark svg{transform:rotate(8deg);}
.rp-h .brand-copy strong{display:block;color:var(--green-deep);font-size:18px;font-weight:800;line-height:1.1;}
@media (min-width:1100px){ .rp-h .brand-copy strong{font-size:21px;} }
.rp-h .brand-copy small{display:block;color:var(--body);font-size:10px;margin-top:2px;line-height:1.2;}
@media (min-width:1100px){ .rp-h .brand-copy small{font-size:11px;} }
.rp-h .desktop-nav{display:none;align-items:center;gap:25px;margin-inline:auto;}
@media (min-width:900px){ .rp-h .desktop-nav{display:flex;} }
.rp-h .desktop-nav a{font-size:14px;font-weight:600;color:#35463d;padding:22px 0 18px;border-bottom:3px solid transparent;text-decoration:none;white-space:nowrap;}
.rp-h .desktop-nav a.active,.rp-h .desktop-nav a:hover{color:var(--green);border-color:var(--green);}
.rp-h .nav-actions{display:flex;align-items:center;gap:8px;}
.rp-h .rp-pill{height:37px;border:1px solid var(--line);border-radius:999px;background:#fff;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:0 13px;font-weight:600;font-size:13px;color:var(--ink);text-decoration:none;cursor:pointer;}
@media (min-width:1100px){ .rp-h .rp-pill{height:40px;padding:0 14px;} }
.rp-h .rp-pill:hover{background:#f4fbf6;}
.rp-h .rp-login{background:var(--green);border-color:var(--green);color:#fff;padding-inline:16px;}
.rp-h .rp-login:hover{background:#06763e;}
@media (min-width:1100px){ .rp-h .rp-login{padding-inline:20px;} }
.rp-h .icon-btn{width:37px;padding:0;}
@media (min-width:1100px){ .rp-h .icon-btn{width:40px;} }
.rp-h .rp-report{background:var(--green);border-color:var(--green);color:#fff;font-weight:700;display:none;}
@media (min-width:768px){ .rp-h .rp-report{display:inline-flex;} }
.rp-h .rp-report:hover{background:#06763e;}
.rp-h .rp-report svg{width:15px;height:15px;}
.rp-h .rp-lang{display:none;}
@media (min-width:820px){ .rp-h .rp-lang{display:inline-flex;} }
.rp-h .hamburger{display:inline-flex;}
@media (min-width:900px){ .rp-h .hamburger{display:none;} }
.rp-h .burger-bar{display:block;width:20px;height:2px;background:var(--ink);border-radius:2px;margin:2.5px 0;}

/* mobile drawer */
.rp-mobilemenu{border-top:1px solid var(--line);background:#fff;padding:10px 16px 14px;box-shadow:0 12px 24px rgba(16,43,32,.08);}
@media (min-width:900px){ .rp-mobilemenu{display:none;} }
.rp-mobilemenu a{display:block;padding:9px 4px;font-size:14px;font-weight:600;color:#35463d;text-decoration:none;border-radius:8px;}
.rp-mobilemenu a:hover{background:#f4fbf6;color:var(--green);}
.rp-mobilemenu .mm-box{border:1px solid var(--line);border-radius:12px;background:#f8fbf9;padding:8px;margin-top:6px;}

/* footer */
.rp-foot{--green:#078a48;background:#003d25;color:#fff;padding:26px 0 15px;margin-top:4px;}
@media (min-width:900px){ .rp-foot{padding:34px 0 18px;} }
.rp-foot .footer-main{display:grid;grid-template-columns:1fr;gap:20px;text-align:center;}
@media (min-width:900px){ .rp-foot .footer-main{grid-template-columns:1.2fr 2fr 1fr;align-items:center;gap:30px;text-align:left;} }
.rp-foot .footer-brand{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;justify-content:center;}
@media (min-width:900px){ .rp-foot .footer-brand{justify-content:flex-start;} }
.rp-foot .footer-brand strong{display:block;font-size:19px;font-weight:800;line-height:1.1;color:#fff;}
.rp-foot .footer-brand small{display:block;color:#c8ded1;font-size:11px;margin-top:2px;}
.rp-foot .footer-note{color:#c8ded1;font-size:12px;max-width:340px;margin:10px auto 0;line-height:1.6;}
@media (min-width:900px){ .rp-foot .footer-note{margin-inline:0;} }
.rp-foot .footer-links{display:flex;justify-content:center;gap:10px 20px;flex-wrap:wrap;font-weight:600;font-size:12px;color:#e2eee7;}
@media (min-width:900px){ .rp-foot .footer-links{font-size:13px;gap:22px;} }
.rp-foot .footer-links a{color:inherit;text-decoration:none;}
.rp-foot .footer-links a:hover{color:#fff;text-decoration:underline;}
.rp-foot .socials{display:flex;justify-content:center;gap:10px;}
@media (min-width:900px){ .rp-foot .socials{justify-content:flex-end;} }
.rp-foot .socials a{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;color:#fff;text-decoration:none;}
.rp-foot .socials a:hover{filter:brightness(1.15);}
.rp-foot .footer-bottom{border-top:1px solid rgba(255,255,255,.2);margin-top:20px;padding-top:14px;text-align:center;color:#c8ded1;font-size:11px;}
@media (min-width:900px){ .rp-foot .footer-bottom{margin-top:24px;padding-top:15px;font-size:12px;} }
.rp-backtop{position:fixed;right:14px;bottom:14px;width:39px;height:39px;border:0;border-radius:50%;background:#16a65b;color:#fff;box-shadow:0 2px 8px rgba(16,43,32,.2);z-index:1000;display:grid;place-items:center;cursor:pointer;}
.rp-backtop.hidden{display:none !important;}
.rp-backtop.rp-show{display:grid !important;}
.rp-backtop:hover{background:#148f4f;}
@media (min-width:900px){ .rp-backtop{right:22px;bottom:20px;width:44px;height:44px;} }
@if ($th['on'])
.rp-h,.rp-foot{--green:{{ $th['green'] }};}
.rp-foot{background:{{ $th['green_deep'] }};}
@endif
</style>
