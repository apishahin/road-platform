<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('issues.maintenance_title') }}</title>
    <style>
        body { font-family: 'Noto Sans Bengali', ui-sans-serif, system-ui, sans-serif; display: flex; min-height: 100vh; align-items: center; justify-content: center; background: #f8fafc; margin: 0; }
        .card { max-width: 26rem; text-align: center; padding: 2.5rem 2rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; margin: 1rem; }
        h1 { font-size: 1.4rem; margin: 1rem 0 .5rem; color: #0f172a; }
        p { color: #475569; font-size: .95rem; line-height: 1.7; margin: .25rem 0; }
        .ico { display: inline-flex; padding: 1rem; border-radius: 9999px; background: #ecfdf5; color: #059669; }
    </style>
</head>
<body>
    <div class="card">
        <span class="ico">
            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a4.5 4.5 0 0 0-6 6L3 18l3 3 5.7-5.7a4.5 4.5 0 0 0 6-6L14 13l-3-3 3.7-3.7Z"/></svg>
        </span>
        <h1>{{ __('issues.maintenance_title') }}</h1>
        <p>{{ $msg !== '' ? $msg : __('issues.maintenance_text') }}</p>
    </div>
</body>
</html>
