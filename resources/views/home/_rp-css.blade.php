{{-- Modern-home design system: direct port of the approved reference CSS,
     scoped under .rp-home so nothing leaks to admin/other pages.
     Admin colour/gradient theme overrides are injected at the bottom. --}}
@php $t = \App\Support\SiteSections::theme(); @endphp
<style>
.rp-home{
    --green:#078a48;--green-2:#0b9953;--green-dark:#00552f;--green-deep:#003d25;
    --green-soft:#eaf8f0;--green-pale:#f4fbf6;--bg:#f7faf8;--ink:#102b20;
    --body:#53645b;--muted:#819087;--line:#e0eae4;--red:#e53935;
    --blue:#2584df;--purple:#8b5cf6;--orange:#f59e0b;
    --cta-a:#006b39;--cta-b:#003d25;
    --shadow:0 2px 8px rgba(16,43,32,.07);--radius:16px;--container:1320px;
    background:var(--bg);color:var(--body);font-size:14px;line-height:1.5;
}
@media (min-width:768px){ .rp-home{font-size:15px;} }
.rp-home img{max-width:100%;}
.rp-wrap{width:min(var(--container),calc(100% - 32px));margin-inline:auto;}
@media (min-width:1100px){ .rp-wrap{width:min(var(--container),calc(100% - 48px));} }

/* ——— hero (slider) ——— */
.rp-home .hero{min-height:290px;position:relative;overflow:hidden;color:#fff;background:#123b28;border-radius:0 0 22px 22px;}
@media (min-width:1100px){ .rp-home .hero{min-height:300px;} }
.rp-home .hero-slide{position:absolute;inset:0;}
.rp-home .hero-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center 52%;}
.rp-home .hero-ov{position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,42,23,.88) 0%,rgba(0,55,29,.66) 45%,rgba(0,30,18,.12) 100%);}
.rp-home .hero-inner{position:relative;min-height:290px;display:flex;align-items:center;}
@media (min-width:1100px){ .rp-home .hero-inner{min-height:300px;} }
.rp-home .hero-copy{max-width:650px;padding:30px 0;position:relative;width:100%;}
.rp-home .eyebrow{display:inline-flex;align-items:center;gap:8px;color:#c8f5d9;font-weight:700;font-size:12px;margin-bottom:10px;}
@media (min-width:768px){ .rp-home .eyebrow{font-size:13px;} }
.rp-home .hero h1{font-size:29px;line-height:1.18;margin:0 0 8px;font-weight:800;letter-spacing:-.5px;color:#fff;max-width:340px;}
.rp-home .hero .hl{color:#d7ff70;}
@media (min-width:768px){ .rp-home .hero h1{font-size:36px;max-width:none;} }
@media (min-width:1100px){ .rp-home .hero h1{font-size:43px;} }
.rp-home .hero-sub{font-size:19px;font-weight:700;margin:0 0 8px;color:#fff;line-height:1.25;max-width:340px;}
@media (min-width:768px){ .rp-home .hero-sub{font-size:20px;max-width:none;} }
@media (min-width:1100px){ .rp-home .hero-sub{font-size:23px;} }
.rp-home .hero-desc{font-size:13px;max-width:330px;color:rgba(255,255,255,.9);margin:0 0 15px;}
@media (min-width:768px){ .rp-home .hero-desc{font-size:15px;max-width:550px;margin-bottom:20px;} }
.rp-home .hero-actions{display:flex;gap:8px;flex-wrap:wrap;}
@media (min-width:768px){ .rp-home .hero-actions{gap:12px;} }
.rp-home .btn{min-height:40px;padding:8px 16px;border-radius:999px;border:1px solid transparent;display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:700;font-size:13px;transition:.2s;text-decoration:none;color:#fff;}
@media (min-width:768px){ .rp-home .btn{min-height:44px;padding:10px 20px;font-size:15px;} }
.rp-home .btn-primary{background:var(--green);color:#fff;box-shadow:0 5px 14px rgba(0,0,0,.12);}
.rp-home .btn-primary:hover{background:#06763e;transform:translateY(-1px);}
.rp-home .btn-outline-light{border-color:rgba(255,255,255,.8);color:#fff;background:rgba(0,0,0,.12);}
.rp-home .btn-outline-light:hover{background:rgba(255,255,255,.14);}
.rp-home .hero-note{position:absolute;right:3%;bottom:26px;font-size:20px;line-height:1.12;font-weight:800;text-align:right;text-shadow:0 2px 12px rgba(0,0,0,.35);transform:rotate(-5deg);display:none;color:#fff;white-space:nowrap;}
.rp-home .hero-note span{color:#f4ed59;}
@media (min-width:1100px){ .rp-home .hero-note{display:block;font-size:26px;bottom:42px;} }
@media (min-width:768px){ .rp-home .hero-note{display:block;} }
.rp-home .dots{display:flex;gap:8px;margin-top:15px;}
.rp-home .dots button{width:8px;height:8px;border-radius:50%;background:#fff;opacity:.75;border:0;padding:0;cursor:pointer;}
.rp-home .dots button.on{background:#16c879;opacity:1;width:18px;border-radius:99px;transition:all .2s;}
.rp-home .hero-arrow{position:absolute;top:50%;transform:translateY(-50%);width:36px;height:36px;border-radius:50%;border:1px solid rgba(255,255,255,.35);background:rgba(0,0,0,.18);color:#fff;display:none;place-items:center;cursor:pointer;}
.rp-home .hero-arrow:hover{background:rgba(255,255,255,.25);}
@media (min-width:1100px){ .rp-home .hero-arrow{display:grid;} }
.rp-home .hero-arrow-l{left:14px;} .rp-home .hero-arrow-r{right:14px;}
/* plain hero (no photo) */
.rp-home .hero-plain{background:linear-gradient(115deg,var(--green-deep) 0%,#0a5a34 70%,var(--green-dark) 100%);}

/* ——— fund strip ——— */
.rp-home .fund-wrap{position:relative;margin-top:-17px;z-index:3;}
@media (min-width:1100px){ .rp-home .fund-wrap{margin-top:-26px;} }
.rp-home .fund-card{min-height:128px;background:linear-gradient(110deg,#effaf3,#e5f6ec);border:1px solid #d8eee0;border-radius:17px;padding:15px;display:grid;gap:12px;box-shadow:var(--shadow);}
@media (min-width:768px){ .rp-home .fund-card{padding:20px 22px;border-radius:20px;gap:15px;} }
.rp-home .fund-main{display:grid;grid-template-columns:43px 1fr;gap:10px;align-items:center;}
@media (min-width:768px){ .rp-home .fund-main{grid-template-columns:52px 1fr;gap:14px;} }
.rp-home .fund-icon{width:43px;height:43px;border-radius:14px;background:#d6f1e0;color:var(--green);display:grid;place-items:center;}
@media (min-width:768px){ .rp-home .fund-icon{width:52px;height:52px;border-radius:15px;} }
.rp-home .fund-main h2{font-size:18px;margin:0 0 1px;font-weight:800;color:var(--ink);}
@media (min-width:1100px){ .rp-home .fund-main h2{font-size:21px;} }
.rp-home .fund-main p{margin:0;color:var(--body);font-size:12px;}
@media (min-width:768px){ .rp-home .fund-main p{font-size:14px;} }
.rp-home .progress-meta{display:flex;justify-content:space-between;gap:12px;margin-top:9px;font-size:11px;font-weight:700;color:var(--ink);}
@media (min-width:768px){ .rp-home .progress-meta{font-size:13px;} }
.rp-home .progress{height:10px;background:#d3e7da;border-radius:99px;overflow:hidden;margin-top:8px;}
.rp-home .progress span{display:block;height:100%;background:var(--green);border-radius:99px;transition:width .4s;}
.rp-home .fund-stats{display:grid;grid-template-columns:repeat(3,1fr);border-top:1px solid #cfe4d6;padding-top:10px;}
@media (min-width:1100px){ .rp-home .fund-stats{border-top:0;border-inline:1px solid #cfe4d6;padding-top:0;} }
.rp-home .fund-stat{text-align:center;padding:3px 5px;border-right:1px solid #cfe4d6;}
.rp-home .fund-stat:last-child{border:0;}
.rp-home .fund-stat b{display:block;font-size:18px;line-height:1.2;color:var(--ink);}
@media (min-width:1100px){ .rp-home .fund-stat b{font-size:21px;} }
.rp-home .fund-stat small{color:var(--body);font-size:10px;}
@media (min-width:768px){ .rp-home .fund-stat small{font-size:12px;} }
.rp-home .fund-cta{text-align:center;display:flex;flex-direction:column;gap:7px;align-items:stretch;}
@media (min-width:1100px){ .rp-home .fund-cta{align-items:center;} }
.rp-home .fund-cta .btn{width:100%;min-height:42px;}
@media (min-width:1100px){ .rp-home .fund-cta .btn{min-height:48px;} }
.rp-home .secure{color:var(--body);font-size:11px;display:inline-flex;align-items:center;gap:5px;justify-content:center;}
@media (min-width:768px){ .rp-home .secure{font-size:12px;} }
.rp-home .goal-met{background:#fff;color:var(--green);font-weight:800;border:1.5px solid var(--green);border-radius:999px;padding:10px 20px;display:inline-block;height:42px;}
@media (min-width:1100px){
    .rp-home .fund-card{grid-template-columns:minmax(300px,1.45fr) minmax(300px,1fr) minmax(170px,.65fr);align-items:center;gap:20px;}
}

/* ——— section head ——— */
.rp-home .rp-sec{padding-top:25px;}
@media (min-width:1100px){ .rp-home .rp-sec{padding-top:32px;} }
.rp-home .section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:11px;}
@media (min-width:1100px){ .rp-home .section-head{align-items:center;gap:16px;margin-bottom:14px;} }
.rp-home .section-title{display:flex;align-items:center;gap:7px;margin:0;font-size:19px;font-weight:800;color:var(--ink);}
@media (min-width:1100px){ .rp-home .section-title{font-size:25px;gap:10px;} }
.rp-home .section-title .symbol{width:28px;height:28px;border-radius:9px;background:var(--green-soft);display:grid;place-items:center;color:var(--green);flex-shrink:0;}
@media (min-width:1100px){ .rp-home .section-title .symbol{width:32px;height:32px;border-radius:10px;} }
.rp-home .text-link{color:var(--green);font-weight:700;font-size:12px;white-space:nowrap;text-decoration:none;}
@media (min-width:768px){ .rp-home .text-link{font-size:14px;} }
.rp-home .text-link:hover{text-decoration:underline;}

/* ——— map + recent ——— */
.rp-home .map-grid{display:grid;grid-template-columns:1fr;gap:14px;}
@media (min-width:1100px){ .rp-home .map-grid{grid-template-columns:minmax(0,2.05fr) minmax(300px,1fr);gap:16px;} }
.rp-home .map-panel,.rp-home .recent-panel{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);overflow:hidden;}
.rp-home .map-top{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 12px;flex-wrap:wrap;}
@media (min-width:768px){ .rp-home .map-top{padding:12px 14px;} }
.rp-home .map-top h3,.rp-home .recent-head h3{margin:0;font-size:15px;font-weight:800;color:var(--ink);}
@media (min-width:1100px){ .rp-home .map-top h3,.rp-home .recent-head h3{font-size:17px;} }
.rp-home .small-outline{border:1px solid #8acaa7;color:var(--green);background:#fff;border-radius:999px;padding:6px 11px;font-size:11px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:5px;cursor:pointer;}
@media (min-width:768px){ .rp-home .small-outline{font-size:13px;padding:7px 13px;} }
.rp-home .small-outline:hover{background:var(--green-pale);}
.rp-home .map-live{height:340px;position:relative;z-index:1;}
@media (min-width:768px){ .rp-home .map-live{height:440px;} }
@media (min-width:1100px){ .rp-home .map-live{height:560px;} }
.rp-home .rp-chips{display:flex;gap:6px;overflow-x:auto;padding:8px 12px 4px;border-bottom:1px solid var(--line);}
.rp-home .rp-chip{flex-shrink:0;border-radius:999px;border:1px solid var(--line);background:#fff;padding:5px 12px;font-size:11px;font-weight:600;color:var(--body);cursor:pointer;}
.rp-home .map-legend{display:flex;justify-content:center;gap:10px 18px;flex-wrap:wrap;padding:9px 8px;font-size:10px;color:var(--body);border-top:1px solid var(--line);}
@media (min-width:768px){ .rp-home .map-legend{font-size:12px;padding:10px 12px;gap:20px;} }
.rp-home .legend-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:5px;}
.rp-home .recent-panel{padding:12px;}
@media (min-width:768px){ .rp-home .recent-panel{padding:14px;} }
.rp-home .recent-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;}
.rp-home .recent-head h3{display:flex;align-items:center;gap:8px;}
.rp-home .recent-head h3:before{content:"";width:8px;height:8px;border-radius:50%;background:#13b978;box-shadow:0 0 0 3px #13b97833;}
.rp-home .issue{display:grid;grid-template-columns:62px 1fr;gap:9px;padding:10px 0;border-bottom:1px solid #edf1ee;color:inherit;text-decoration:none;}
.rp-home .issue:last-of-type{border-bottom:0;}
@media (min-width:768px){ .rp-home .issue{grid-template-columns:76px 1fr;gap:11px;padding:12px 0;} }
.rp-home .issue .thumb{width:62px;height:55px;object-fit:cover;border-radius:10px;background:#e4eee7;display:block;}
@media (min-width:768px){ .rp-home .issue .thumb{width:76px;height:64px;} }
.rp-home .issue .thumb-ph{width:62px;height:55px;border-radius:10px;background:#e4eee7;display:grid;place-items:center;color:var(--green-2);}
@media (min-width:768px){ .rp-home .issue .thumb-ph{width:76px;height:64px;} }
.rp-home .issue-title{display:flex;justify-content:space-between;align-items:flex-start;gap:6px;}
.rp-home .issue h4{font-size:13px;line-height:1.25;margin:0 0 3px;font-weight:700;color:var(--ink);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
@media (min-width:768px){ .rp-home .issue h4{font-size:14px;} }
.rp-home .issue p{margin:0;color:var(--muted);font-size:10px;}
@media (min-width:768px){ .rp-home .issue p{font-size:11px;} }
.rp-home .status{flex-shrink:0;font-size:9px;font-weight:700;padding:2px 7px;border-radius:99px;white-space:nowrap;}
@media (min-width:768px){ .rp-home .status{font-size:10px;padding:3px 8px;} }
.rp-home .status-new{background:#ffeded;color:#d62e2e;}
.rp-home .status-progress{background:#fff3df;color:#bd7600;}
.rp-home .status-active{background:#e8f2ff;color:#1971c8;}
.rp-home .status-done{background:#e7f8ed;color:#078a48;}

/* ——— stats strip ——— */
.rp-home .stats-strip{margin-top:18px;padding:10px 5px;background:var(--green-soft);border:1px solid #dcefe3;border-radius:15px;display:grid;grid-template-columns:repeat(2,1fr);row-gap:10px;}
@media (min-width:1100px){ .rp-home .stats-strip{grid-template-columns:repeat(4,1fr);padding:17px 10px;margin-top:24px;row-gap:0;} }
.rp-home .stat-box{text-align:center;padding:7px 4px;}
@media (min-width:1100px){ .rp-home .stat-box{border-right:1px solid #cfe4d6;padding:0 8px;} .rp-home .stat-box:last-child{border:0;} }
.rp-home .stat-icon{color:var(--green);display:flex;justify-content:center;margin-bottom:2px;}
.rp-home .stat-box strong{display:block;font-size:20px;font-weight:800;line-height:1.2;color:var(--ink);}
@media (min-width:1100px){ .rp-home .stat-box strong{font-size:23px;} }
.rp-home .stat-box small{font-size:11px;color:var(--body);}
@media (min-width:768px){ .rp-home .stat-box small{font-size:13px;} }

/* ——— how it works ——— */
.rp-home .steps{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;}
@media (min-width:1100px){ .rp-home .steps{grid-template-columns:repeat(4,1fr);gap:16px;} }
.rp-home .step-card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:12px 11px;min-height:118px;}
@media (min-width:1100px){ .rp-home .step-card{padding:17px 16px;min-height:138px;} }
.rp-home .step-top{display:flex;align-items:center;gap:8px;margin-bottom:7px;}
.rp-home .step-number{width:25px;height:25px;border-radius:50%;background:var(--green);color:#fff;display:grid;place-items:center;font-weight:700;font-size:12px;flex-shrink:0;}
@media (min-width:1100px){ .rp-home .step-number{width:29px;height:29px;font-size:14px;} }
.rp-home .step-icon{color:var(--green);display:grid;place-items:center;}
.rp-home .step-card h3{margin:0 0 3px;font-size:14px;font-weight:800;color:var(--ink);}
@media (min-width:1100px){ .rp-home .step-card h3{font-size:16px;} }
.rp-home .step-card p{margin:0;color:var(--body);font-size:11px;line-height:1.45;}
@media (min-width:1100px){ .rp-home .step-card p{font-size:13px;} }

/* ——— completed repairs ——— */
.rp-home .repairs{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;}
@media (min-width:768px){ .rp-home .repairs{grid-template-columns:repeat(3,1fr);} }
@media (min-width:1100px){ .rp-home .repairs{grid-template-columns:repeat(4,1fr);gap:16px;} }
.rp-home .repair-card{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden;box-shadow:var(--shadow);color:inherit;text-decoration:none;display:block;transition:transform .15s;}
.rp-home .repair-card:hover{transform:translateY(-2px);}
.rp-home .repair-image{height:100px;position:relative;background:var(--green-soft);}
@media (min-width:1100px){ .rp-home .repair-image{height:145px;} }
.rp-home .repair-image img{width:100%;height:100%;object-fit:cover;display:block;}
.rp-home .repair-ph{position:absolute;inset:0;display:grid;place-items:center;color:var(--green-2);}
.rp-home .repair-image .status{position:absolute;right:8px;top:8px;background:#12a65a;color:#fff;font-size:9px;}
@media (min-width:768px){ .rp-home .repair-image .status{font-size:10px;right:10px;top:10px;} }
.rp-home .repair-info{padding:8px 10px;}
@media (min-width:1100px){ .rp-home .repair-info{padding:10px 12px;} }
.rp-home .repair-info strong{font-size:13px;color:var(--ink);display:block;line-height:1.3;}
@media (min-width:1100px){ .rp-home .repair-info strong{font-size:15px;} }
.rp-home .repair-info small{display:block;color:var(--muted);font-size:10px;margin-top:1px;}
@media (min-width:768px){ .rp-home .repair-info small{font-size:11px;} }

/* ——— community CTA ——— */
.rp-home .community-cta{margin-top:25px;background:linear-gradient(110deg,var(--cta-a),var(--cta-b));border-radius:15px;padding:18px 15px;color:#fff;display:flex;flex-direction:column;align-items:center;text-align:center;gap:12px;overflow:hidden;position:relative;}
@media (min-width:768px){
    .rp-home .community-cta{margin-top:34px;border-radius:18px;min-height:110px;padding:20px 34px;flex-direction:row;justify-content:space-between;text-align:left;gap:20px;}
}
.rp-home .community-cta:before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 10% 30%,rgba(66,200,116,.22),transparent 30%),radial-gradient(circle at 90% 80%,rgba(66,200,116,.18),transparent 25%);}
.rp-home .cta-copy{position:relative;z-index:1;display:flex;align-items:center;gap:12px;flex-direction:column;text-align:center;}
@media (min-width:768px){ .rp-home .cta-copy{flex-direction:row;gap:20px;text-align:left;} }
.rp-home .handshake{width:46px;height:46px;border-radius:50%;background:rgba(255,255,255,.14);display:grid;place-items:center;flex-shrink:0;}
.rp-home .cta-copy h2{margin:0;font-size:18px;font-weight:800;color:#fff;}
@media (min-width:1100px){ .rp-home .cta-copy h2{font-size:23px;} }
.rp-home .cta-copy p{margin:2px 0 0;color:#d1e9da;font-size:12px;}
@media (min-width:768px){ .rp-home .cta-copy p{font-size:14px;} }
.rp-home .cta-btn{position:relative;z-index:1;background:#fff;color:var(--green-dark);min-height:40px;padding:10px 20px;border-radius:999px;font-weight:800;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;white-space:nowrap;transition:.15s;}
.rp-home .cta-btn:hover{background:var(--green-soft);}
@media (min-width:768px){ .rp-home .cta-btn{min-width:155px;min-height:44px;justify-content:center;font-size:15px;} }

/* ——— misc ——— */
.rp-home .rp-privacy{font-size:11px;color:var(--muted);text-align:center;margin:24px 0 14px;}
.rp-home .fund-nohero .fund-wrap{margin-top:20px;}
</style>
@if ($t['on'])
<style>
/* Admin theme override (Homepage & site → Colour theme) */
.rp-home{
    --green:{{ $t['green'] }};--green-dark:{{ $t['green_dark'] }};--green-deep:{{ $t['green_deep'] }};
    --cta-a:{{ $t['cta_from'] }};--cta-b:{{ $t['cta_to'] }};
}
.rp-home .hero .hl{color:{{ $t['accent'] }};}
.rp-home .btn-primary:hover{filter:brightness(.92);}
</style>
@endif
