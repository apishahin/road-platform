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

/* Mobile drawer — a contained, touch-friendly control surface rather than a page-length list. */
.rp-h .nav-wrap{position:relative;z-index:2;}
.rp-mobile-backdrop{position:fixed;inset:64px 0 0;background:rgba(10,33,22,.32);backdrop-filter:blur(2px);z-index:1;}
.rp-mobilemenu{position:absolute;inset:100% 0 auto;z-index:3;max-height:calc(100dvh - 64px);overflow-y:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;border-top:1px solid var(--line);background:linear-gradient(180deg,#fff 0%,#f7faf8 100%);padding:14px 16px max(20px,env(safe-area-inset-bottom));box-shadow:0 18px 32px rgba(16,43,32,.16);}
.rp-mobilemenu .rp-mobile-inner{display:grid;gap:10px;max-width:1320px;margin-inline:auto;}
@media (min-width:900px){.rp-mobile-backdrop,.rp-mobilemenu{display:none!important;}}
.rp-mobilemenu a{color:#35463d;text-decoration:none;}
.rp-mobile-primary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;}
.rp-mobile-primary a{display:flex;align-items:center;min-height:46px;padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:#fff;color:#35463d;font-size:14px;font-weight:700;box-shadow:0 1px 1px rgba(16,43,32,.02);}
.rp-mobile-primary a:hover,.rp-mobile-primary a:focus-visible{border-color:#9acbb0;background:#f4fbf6;color:var(--green);outline:none;}
.rp-mobile-utility{display:flex!important;align-items:center;justify-content:space-between;min-height:44px;padding:9px 12px!important;border-radius:11px;color:#53645b!important;font-size:13px!important;font-weight:700!important;}
.rp-mobile-utility:hover,.rp-mobile-utility:focus-visible{background:#edf7f0!important;color:var(--green)!important;outline:none;}
.rp-mobile-login{display:flex!important;align-items:center;justify-content:center;min-height:46px;padding:10px 14px!important;border-radius:12px;background:var(--green);color:#fff!important;font-size:14px!important;font-weight:800!important;box-shadow:0 5px 12px rgba(7,138,72,.18);}
.rp-mobile-login:hover,.rp-mobile-login:focus-visible{background:#06763e!important;outline:none;}

/* Signed-in account block. It keeps personal controls distinct from staff controls. */
.rp-mobile-account{overflow:hidden;border:1px solid #dce8e0;border-radius:15px;background:#fff;box-shadow:0 2px 7px rgba(16,43,32,.04);}
.rp-mobile-account-head{display:flex;align-items:center;gap:10px;padding:11px 12px;border-bottom:1px solid #edf2ee;background:#f7faf8;}
.rp-mobile-avatar{display:grid;width:34px;height:34px;flex:0 0 34px;place-items:center;border-radius:50%;background:linear-gradient(135deg,var(--green),#8acb52);color:#fff;font-size:14px;font-weight:800;}
.rp-mobile-account-name{min-width:0;flex:1;}
.rp-mobile-account-name strong{display:block;overflow:hidden;color:var(--ink);font-size:14px;font-weight:800;line-height:1.2;text-overflow:ellipsis;white-space:nowrap;}
.rp-mobile-account-name small{display:inline-flex;margin-top:3px;border-radius:999px;background:#e7f3eb;padding:2px 7px;color:#287348;font-size:10px;font-weight:800;line-height:1.25;}
.rp-mobile-account-links{display:grid;gap:1px;padding:6px;}
.rp-mobile-account-link{display:flex!important;align-items:center;gap:10px;min-height:43px;padding:8px 9px!important;border-radius:9px;color:#35463d!important;font-size:13px!important;font-weight:700!important;}
.rp-mobile-account-link:hover,.rp-mobile-account-link:focus-visible{background:#f1f8f3!important;color:var(--green)!important;outline:none;}
.rp-mobile-account-link svg{width:17px;height:17px;color:#628273;}
.rp-mobile-account-link .rp-mobile-account-count{margin-left:auto;min-width:20px;padding:2px 6px;border-radius:999px;background:#d92d20;color:#fff;font-size:10px;font-weight:800;text-align:center;}
.rp-mobile-account-link.is-danger{color:#c5261a!important;}
.rp-mobile-account-link.is-danger svg{color:#dc5a50;}
.rp-mobile-account-logout{padding:0 6px 6px;}
.rp-mobile-account-logout button{display:flex;width:100%;align-items:center;gap:10px;min-height:42px;padding:8px 9px;border:0;border-radius:9px;background:#fff1f1;color:#c5261a;font-size:13px;font-weight:800;text-align:left;cursor:pointer;}
.rp-mobile-account-logout button:hover,.rp-mobile-account-logout button:focus-visible{background:#ffe6e6;outline:none;}
.rp-mobile-account-logout svg{width:17px;height:17px;}

/* Staff navigation uses expandable groups so an admin can reach every tool without a cramped wall of controls. */
.rp-mobile-staff{display:grid;gap:8px;padding-top:4px;border-top:1px solid #dce8e0;}
.rp-mobile-staff-heading{display:flex;align-items:center;gap:9px;padding:6px 2px 2px;}
.rp-mobile-staff-emblem{display:grid;width:31px;height:31px;place-items:center;border-radius:10px;background:#e6f4eb;color:var(--green);}
.rp-mobile-staff-emblem svg{width:18px;height:18px;}
.rp-mobile-staff-heading small{display:block;color:#718178;font-size:10px;font-weight:800;letter-spacing:.08em;line-height:1.2;text-transform:uppercase;}
.rp-mobile-staff-heading strong{display:block;margin-top:1px;color:var(--ink);font-size:14px;font-weight:800;line-height:1.3;}
.rp-mobile-staff-group{overflow:hidden;border:1px solid #dce8e0;border-radius:13px;background:#fff;box-shadow:0 1px 2px rgba(16,43,32,.025);}
.rp-mobile-staff-group summary{display:flex;align-items:center;justify-content:space-between;min-height:48px;padding:10px 12px;cursor:pointer;color:#284438;font-size:13px;font-weight:800;list-style:none;}
.rp-mobile-staff-group summary::-webkit-details-marker{display:none;}
.rp-mobile-staff-group summary:hover{background:#f6fbf8;}
.rp-mobile-staff-group summary:focus-visible{outline:3px solid rgba(7,138,72,.28);outline-offset:-3px;}
.rp-mobile-summary-chevron{width:17px;height:17px;color:#6d8075;transition:transform .18s ease;}
.rp-mobile-staff-group[open] .rp-mobile-summary-chevron{transform:rotate(180deg);}
.rp-mobile-staff-links{display:grid;gap:2px;padding:5px;border-top:1px solid #edf2ee;background:#f8fbf9;}
.rp-mobile-staff-link{display:flex!important;align-items:center;gap:10px;min-height:46px;padding:8px 9px!important;border-radius:9px;background:#fff;color:#35463d!important;font-size:13px!important;font-weight:700!important;}
.rp-mobile-staff-link:hover,.rp-mobile-staff-link:focus-visible{background:#edf8f1!important;color:var(--green)!important;outline:none;}
.rp-mobile-staff-link.is-current{background:#e5f5eb!important;color:#076f3b!important;}
.rp-mobile-staff-icon{display:grid;width:29px;height:29px;flex:0 0 29px;place-items:center;border-radius:8px;background:#edf3ef;color:#527363;}
.rp-mobile-staff-link.is-current .rp-mobile-staff-icon{background:#cfeeda;color:#078a48;}
.rp-mobile-staff-icon svg{width:16px;height:16px;}
.rp-mobile-staff-label{min-width:0;flex:1;}
.rp-mobile-staff-badge{min-width:20px;padding:2px 6px;border-radius:999px;background:#e75025;color:#fff;font-size:10px;font-weight:800;line-height:1.3;text-align:center;}
.rp-mobile-link-arrow{width:16px;height:16px;flex:0 0 16px;color:#8b9a92;}
.rp-mobile-quick-link{display:flex!important;align-items:center;gap:10px;min-height:46px;padding:9px 11px!important;border:1px solid #dce8e0;border-radius:12px;background:#fff;color:#35463d!important;font-size:13px!important;font-weight:800!important;box-shadow:0 1px 2px rgba(16,43,32,.025);}
.rp-mobile-quick-link:hover,.rp-mobile-quick-link:focus-visible{border-color:#9acbb0;background:#f3fbf5!important;color:var(--green)!important;outline:none;}
.rp-mobile-quick-icon{display:grid;width:28px;height:28px;place-items:center;border-radius:8px;background:#e7f4eb;color:var(--green);}
.rp-mobile-quick-icon svg{width:16px;height:16px;}
.rp-mobile-quick-link .rp-mobile-link-arrow{margin-left:auto;}
.rp-mobile-fund-link{border-color:#bce2cf;background:#f1faf5;color:#187040!important;}
.rp-mobile-report-link{display:flex!important;align-items:center;justify-content:center;gap:8px;min-height:48px;padding:10px 14px!important;border-radius:12px;background:var(--green);color:#fff!important;font-size:14px!important;font-weight:800!important;box-shadow:0 6px 14px rgba(7,138,72,.2);}
.rp-mobile-report-link:hover,.rp-mobile-report-link:focus-visible{background:#06763e!important;outline:none;}
.rp-mobile-report-link svg{width:17px;height:17px;}
@media (max-width:360px){.rp-mobilemenu{padding-inline:12px;}.rp-mobile-primary{gap:6px;}.rp-mobile-primary a{padding-inline:10px;font-size:13px;}.rp-mobile-staff-link,.rp-mobile-account-link{font-size:12px!important;}}

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
@if ($th['on'])
.rp-h,.rp-foot{--green:{{ $th['green'] }};}
.rp-foot{background:{{ $th['green_deep'] }};}
@endif
</style>
